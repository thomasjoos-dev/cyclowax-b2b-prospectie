<?php

namespace App\Console\Commands;

use App\Enums\DiscoverySource;
use App\Enums\PipelineStatus;
use App\Models\Brand;
use App\Models\Store;
use App\Services\AbusLocatorService;
use App\Services\BassoLocatorService;
use App\Services\SchwalbeLocatorService;
use App\Services\SpecializedLocatorService;
use App\Services\StoreMatchingService;
use App\Services\TrekLocatorService;
use Illuminate\Console\Command;

class ImportBrandLocator extends Command
{
    protected $signature = 'brands:import-locator
        {brand : Merknaam of slug}
        {--country=BE : Landcode (BE of DE)}
        {--file= : Pad naar JSON-bestand met dealer-data (voor merken achter Cloudflare)}
        {--dry-run : Toon matches zonder op te slaan}';

    protected $description = 'Importeer dealer-data van een merkwebsite en koppel aan bestaande stores';

    public function handle(SpecializedLocatorService $specializedService, StoreMatchingService $matchingService): int
    {
        $brand = $this->resolveBrand($this->argument('brand'));

        if (! $brand) {
            $this->error("Merk \"{$this->argument('brand')}\" niet gevonden.");

            return self::FAILURE;
        }

        $country = strtoupper($this->option('country'));
        $isDryRun = $this->option('dry-run');

        $this->info("Dealers ophalen voor {$brand->name} in {$country}...");

        $result = $this->fetchDealers($specializedService, $brand, $country);

        if (empty($result['dealers'])) {
            $this->warn('Geen dealers gevonden.');

            return self::FAILURE;
        }

        $this->info("{$result['queries']} queries uitgevoerd, ".count($result['dealers']).' unieke dealers gevonden.');
        $this->newLine();

        $this->info('Matchen tegen bestaande stores...');
        $matches = $matchingService->match($result['dealers'], $brand->name);

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
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    private function fetchDealers(SpecializedLocatorService $specializedService, Brand $brand, string $country): array
    {
        $file = $this->option('file');

        if ($file) {
            return $this->fetchFromFile($brand, $file);
        }

        return match (mb_strtolower($brand->slug)) {
            'specialized' => $specializedService->fetchDealersForCountry($country, function (int $current, int $total) {
                $this->output->write("\r  Grid sweep: {$current}/{$total} punten");

                if ($current === $total) {
                    $this->newLine();
                }
            }),
            'abus' => app(AbusLocatorService::class)->fetchDealersForCountry($country),
            'basso' => app(BassoLocatorService::class)->fetchDealersForCountry($country),
            'schwalbe' => app(SchwalbeLocatorService::class)->fetchDealersForCountry($country),
            'trek' => app(TrekLocatorService::class)->fetchDealersForCountry($country, function (int $current, int $total) {
                $this->output->write("\r  Pagina: {$current}/{$total}");

                if ($current === $total) {
                    $this->newLine();
                }
            }),
            default => $this->unsupportedLiveImport($brand),
        };
    }

    /**
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    private function fetchFromFile(Brand $brand, string $file): array
    {
        if (! file_exists($file)) {
            $this->error("Bestand niet gevonden: {$file}");

            return ['dealers' => [], 'queries' => 0];
        }

        return match (mb_strtolower($brand->slug)) {
            'abus' => app(AbusLocatorService::class)->parseDealersFromFile($file),
            default => $this->unsupportedFileImport($brand),
        };
    }

    /**
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    private function unsupportedFileImport(Brand $brand): array
    {
        $this->warn("JSON-import voor \"{$brand->name}\" is nog niet geïmplementeerd.");

        return ['dealers' => [], 'queries' => 0];
    }

    /**
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    private function unsupportedLiveImport(Brand $brand): array
    {
        $this->warn("Locator scraping voor \"{$brand->name}\" is nog niet geïmplementeerd.");
        $this->warn('Gebruik --file=pad/naar/bestand.json voor merken die manuele export vereisen.');

        return ['dealers' => [], 'queries' => 0];
    }

    /**
     * @param  array<int, array{dealer: array<string, mixed>, store: \App\Models\Store, confidence: float}>  $matched
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
     * Create new stores for unmatched dealers.
     *
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
                'latitude' => $dealer['latitude'] ?? null,
                'longitude' => $dealer['longitude'] ?? null,
                'discovery_source' => DiscoverySource::BrandLocator,
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
                'discovery_source' => DiscoverySource::BrandLocator->value,
                'discovered_at' => now(),
            ];
        }

        foreach ($newStores as $store) {
            $pivotData[$store->id] = [
                'discovery_source' => DiscoverySource::BrandLocator->value,
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
