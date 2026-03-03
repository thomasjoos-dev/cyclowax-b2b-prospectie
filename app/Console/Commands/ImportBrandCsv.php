<?php

namespace App\Console\Commands;

use App\Enums\DiscoverySource;
use App\Enums\PipelineStatus;
use App\Models\Brand;
use App\Models\Store;
use App\Services\CsvDealerImportService;
use App\Services\StoreMatchingService;
use Illuminate\Console\Command;

class ImportBrandCsv extends Command
{
    protected $signature = 'brands:import-csv
        {brand : Merknaam of slug}
        {file : Pad naar het CSV-bestand}
        {--country= : Filter op landcode (bijv. BE, NL)}
        {--dry-run : Toon matches zonder op te slaan}';

    protected $description = 'Importeer dealers uit een CSV-bestand en koppel aan een merk';

    public function handle(CsvDealerImportService $csvService, StoreMatchingService $matchingService): int
    {
        $brand = $this->resolveBrand($this->argument('brand'));

        if (! $brand) {
            $this->error("Merk \"{$this->argument('brand')}\" niet gevonden.");

            return self::FAILURE;
        }

        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("Bestand niet gevonden: {$filePath}");

            return self::FAILURE;
        }

        $country = $this->option('country') ? strtoupper($this->option('country')) : null;
        $isDryRun = $this->option('dry-run');

        $this->info("CSV inlezen voor {$brand->name}".($country ? " (filter: {$country})" : '').'...');

        $dealers = $csvService->parseCsvFile($filePath, $country);

        if (empty($dealers)) {
            $this->warn('Geen dealers gevonden in CSV.');

            return self::FAILURE;
        }

        $this->info(count($dealers).' unieke dealers gevonden.');
        $this->newLine();

        $this->info('Matchen tegen bestaande stores...');
        $matches = $matchingService->match($dealers, $brand->name);

        $this->displayMatchedDealers($matches['matched']);
        $this->displayUnmatchedDealers($matches['unmatched']);

        if (! $isDryRun) {
            $newStores = $this->createNewStores($matches['unmatched']);
            $this->savePivotRecords($brand, $matches['matched'], $newStores);
        }

        $this->displaySummary($matches, $isDryRun, $isDryRun ? 0 : count($matches['unmatched']));

        return self::SUCCESS;
    }

    private function resolveBrand(string $identifier): ?Brand
    {
        return Brand::query()
            ->where('slug', $identifier)
            ->orWhere('name', $identifier)
            ->first();
    }

    /**
     * @param  array<int, array{dealer: array<string, mixed>, store: Store, confidence: float}>  $matched
     */
    private function displayMatchedDealers(array $matched): void
    {
        if (empty($matched)) {
            $this->warn('Geen matches gevonden.');
            $this->newLine();

            return;
        }

        $rows = array_map(fn (array $m) => [
            $m['dealer']['name'],
            $m['dealer']['postal_code'] ?? '-',
            $m['store']->name,
            $m['store']->city ?? '-',
            number_format($m['confidence'] * 100).'%',
        ], $matched);

        $this->info(count($matched).' matches:');
        $this->table(
            ['Dealer', 'Postcode', 'Store', 'Stad', 'Confidence'],
            $rows
        );

        $this->newLine();
    }

    /**
     * @param  array<int, array<string, mixed>>  $unmatched
     */
    private function displayUnmatchedDealers(array $unmatched): void
    {
        if (empty($unmatched)) {
            return;
        }

        $rows = array_map(fn (array $d) => [
            $d['name'],
            $d['city'] ?? '-',
            $d['postal_code'] ?? '-',
        ], $unmatched);

        $this->warn(count($unmatched).' ongematchte dealers:');
        $this->table(
            ['Dealer', 'Stad', 'Postcode'],
            $rows
        );

        $this->newLine();
    }

    /**
     * @param  array<int, array<string, mixed>>  $unmatched
     * @return array<int, Store>
     */
    private function createNewStores(array $unmatched): array
    {
        $stores = [];

        foreach ($unmatched as $dealer) {
            $stores[] = Store::query()->create([
                'name' => $dealer['name'],
                'address' => $dealer['address'] ?? null,
                'city' => $dealer['city'] ?? null,
                'country' => $dealer['country'] ?? null,
                'postal_code' => $dealer['postal_code'] ?? null,
                'phone' => $dealer['phone'] ?? null,
                'email' => $dealer['email'] ?? null,
                'website' => $dealer['website'] ?? null,
                'latitude' => $dealer['latitude'] ?? null,
                'longitude' => $dealer['longitude'] ?? null,
                'discovery_source' => DiscoverySource::CsvImport,
                'pipeline_status' => PipelineStatus::NietGecontacteerd,
                'is_existing_customer' => false,
            ]);
        }

        if (count($stores) > 0) {
            $this->info(count($stores).' nieuwe stores aangemaakt.');
        }

        return $stores;
    }

    /**
     * @param  array<int, array{dealer: array<string, mixed>, store: Store, confidence: float}>  $matched
     * @param  array<int, Store>  $newStores
     */
    private function savePivotRecords(Brand $brand, array $matched, array $newStores = []): void
    {
        $pivotData = [];

        foreach ($matched as $m) {
            $pivotData[$m['store']->id] = [
                'discovery_source' => DiscoverySource::CsvImport->value,
                'discovered_at' => now(),
            ];
        }

        foreach ($newStores as $store) {
            $pivotData[$store->id] = [
                'discovery_source' => DiscoverySource::CsvImport->value,
                'discovered_at' => now(),
            ];
        }

        $brand->stores()->syncWithoutDetaching($pivotData);

        $this->info(count($pivotData).' brand-store koppelingen opgeslagen.');
    }

    /**
     * @param  array{matched: array, unmatched: array}  $matches
     */
    private function displaySummary(array $matches, bool $isDryRun, int $created = 0): void
    {
        $this->newLine();
        $this->info('--- Samenvatting ---');
        $this->info('Matched: '.count($matches['matched']));
        $this->info('Nieuw aangemaakt: '.$created);
        $this->info('Totaal gekoppeld: '.(count($matches['matched']) + $created));

        if ($isDryRun) {
            $this->warn('Dry run — niets opgeslagen.');
        }
    }
}
