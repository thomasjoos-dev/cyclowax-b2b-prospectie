<?php

namespace App\Console\Commands;

use App\Enums\DiscoverySource;
use App\Enums\PipelineStatus;
use App\Models\Store;
use Illuminate\Console\Command;

class ImportCyclowaxCustomers extends Command
{
    protected $signature = 'stores:import-cyclowax
        {file : Pad naar het reviewed CSV-bestand}
        {--dry-run : Toon wat er zou gebeuren zonder op te slaan}';

    protected $description = 'Importeer Cyclowax klantenbestand uit reviewed match-rapport CSV';

    /** @var array<string, string> */
    private array $countryMap = [
        'Verenigde Arabische Emiraten' => 'AE',
        'Puerto Rico' => 'PR',
        'Taiwan' => 'TW',
        'Trinidad en Tobago' => 'TT',
        'Dominicaanse Republiek' => 'DO',
    ];

    private int $updated = 0;

    private int $created = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $isDryRun = $this->option('dry-run');

        if (! file_exists($filePath)) {
            $this->error("Bestand niet gevonden: {$filePath}");

            return self::FAILURE;
        }

        $rows = $this->parseCsv($filePath);

        if (empty($rows)) {
            $this->error('Geen rijen gevonden in CSV.');

            return self::FAILURE;
        }

        $this->info(count($rows).' rijen ingelezen.');
        $this->newLine();

        foreach ($rows as $row) {
            $action = strtoupper(trim($row['actie'] ?? ''));

            if ($action === 'UPDATE') {
                $this->processUpdate($row, $isDryRun);
            } elseif ($action === 'CREATE') {
                $this->processCreate($row, $isDryRun);
            } else {
                $this->warn("Onbekende actie '{$action}' voor {$row['cyclowax_naam']} — overgeslagen.");
                $this->skipped++;
            }
        }

        $this->displaySummary($isDryRun);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');

        if (! $handle) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ';');

        if (! $headers) {
            fclose($handle);

            return [];
        }

        $headers = array_map('trim', $headers);

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if (count($data) === count($headers)) {
                $rows[] = array_combine($headers, $data);
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function processUpdate(array $row, bool $isDryRun): void
    {
        $storeId = trim($row['match_store_id'] ?? '');

        if (! $storeId || ! is_numeric($storeId)) {
            $this->warn("UPDATE zonder match_store_id: {$row['cyclowax_naam']} — overgeslagen.");
            $this->skipped++;

            return;
        }

        $store = Store::find((int) $storeId);

        if (! $store) {
            $this->warn("Store #{$storeId} niet gevonden: {$row['cyclowax_naam']} — overgeslagen.");
            $this->skipped++;

            return;
        }

        $pipelineStatus = $this->resolvePipelineStatus($row['pipeline_status'] ?? '');
        $isCustomer = $pipelineStatus === PipelineStatus::Partner;

        $updates = [
            'pipeline_status' => $pipelineStatus,
            'is_existing_customer' => $isCustomer,
        ];

        // Contactgegevens alleen bijwerken als ze niet al gevuld zijn
        $phone = trim($row['telefoon'] ?? '');
        $email = trim($row['email'] ?? '');
        $assignedTo = trim($row['verkoper'] ?? '');

        if ($phone && ! $store->phone) {
            $updates['phone'] = $phone;
        }
        if ($email && ! $store->email) {
            $updates['email'] = $email;
        }
        if ($assignedTo && ! $store->assigned_to) {
            $updates['assigned_to'] = $assignedTo;
        }

        if ($isDryRun) {
            $statusLabel = $pipelineStatus->label();
            $this->line("  UPDATE #{$storeId} <comment>{$store->name}</comment> → {$statusLabel}");
        } else {
            $store->update($updates);
        }

        $this->updated++;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function processCreate(array $row, bool $isDryRun): void
    {
        $handelsnaam = trim($row['handelsnaam'] ?? '');
        $name = $handelsnaam !== '' ? $handelsnaam : trim($row['cyclowax_naam'] ?? '');

        if (! $name) {
            $this->warn('CREATE zonder naam — overgeslagen.');
            $this->skipped++;

            return;
        }

        $city = trim($row['stad'] ?? '');
        $country = $this->resolveCountry(trim($row['land'] ?? ''));
        $pipelineStatus = $this->resolvePipelineStatus($row['pipeline_status'] ?? '');
        $isCustomer = $pipelineStatus === PipelineStatus::Partner;

        if ($isDryRun) {
            $statusLabel = $pipelineStatus->label();
            $this->line("  CREATE <info>{$name}</info> ({$city}, {$country}) → {$statusLabel}");
        } else {
            Store::query()->create([
                'name' => $name,
                'city' => $city ?: null,
                'country' => $country ?: null,
                'phone' => trim($row['telefoon'] ?? '') ?: null,
                'email' => trim($row['email'] ?? '') ?: null,
                'assigned_to' => trim($row['verkoper'] ?? '') ?: null,
                'pipeline_status' => $pipelineStatus,
                'is_existing_customer' => $isCustomer,
                'discovery_source' => DiscoverySource::CyclowaxCrm,
            ]);
        }

        $this->created++;
    }

    private function resolvePipelineStatus(string $status): PipelineStatus
    {
        return match (strtolower(trim($status))) {
            'partner' => PipelineStatus::Partner,
            'gecontacteerd' => PipelineStatus::Gecontacteerd,
            'in_gesprek' => PipelineStatus::InGesprek,
            'afgewezen' => PipelineStatus::Afgewezen,
            default => PipelineStatus::NietGecontacteerd,
        };
    }

    private function resolveCountry(string $country): string
    {
        // Al een ISO-code (2 letters) → direct teruggeven
        if (strlen($country) === 2 && ctype_alpha($country)) {
            return strtoupper($country);
        }

        return $this->countryMap[$country] ?? $country;
    }

    private function displaySummary(bool $isDryRun): void
    {
        $this->newLine();
        $this->info('--- Samenvatting ---');
        $this->info("Updated: {$this->updated}");
        $this->info("Created: {$this->created}");
        $this->info("Skipped: {$this->skipped}");

        if ($isDryRun) {
            $this->warn('Dry run — niets opgeslagen.');
        }
    }
}
