<?php

namespace App\Console\Commands;

use App\Services\OverpassService;
use App\Services\StoreImportService;
use Illuminate\Console\Command;

class ImportOverpassStores extends Command
{
    protected $signature = 'stores:import-overpass
        {city? : De stad of regio om fietswinkels in te zoeken}
        {--all-belgium : Importeer fietswinkels uit alle grote Belgische steden}
        {--all-germany : Importeer fietswinkels uit alle Duitse Bundesländer}';

    protected $description = 'Importeer fietswinkels vanuit OpenStreetMap via de Overpass API';

    /** @var list<string> */
    private const BELGIAN_CITIES = [
        'Antwerpen',
        'Bruxelles - Brussel',
        'Brugge',
        'Gent',
        'Leuven',
        'Mechelen',
        'Namur',
        'Liège',
        'Charleroi',
        'Mons',
        'Hasselt',
        'Kortrijk',
        'Oostende',
        'Aalst',
        'Turnhout',
        'Roeselare',
        'Genk',
        'Sint-Niklaas',
        'Waregem',
        'Dendermonde',
    ];

    /** @var list<string> */
    private const GERMAN_STATES = [
        'Baden-Württemberg',
        'Bayern',
        'Berlin',
        'Brandenburg',
        'Bremen',
        'Hamburg',
        'Hessen',
        'Mecklenburg-Vorpommern',
        'Niedersachsen',
        'Nordrhein-Westfalen',
        'Rheinland-Pfalz',
        'Saarland',
        'Sachsen',
        'Sachsen-Anhalt',
        'Schleswig-Holstein',
        'Thüringen',
    ];

    public function handle(OverpassService $overpass, StoreImportService $importer): int
    {
        if ($this->option('all-belgium')) {
            return $this->importMultipleCities($overpass, $importer, self::BELGIAN_CITIES);
        }

        if ($this->option('all-germany')) {
            return $this->importMultipleRegions($overpass, $importer, self::GERMAN_STATES);
        }

        $city = $this->argument('city');

        if (! $city) {
            $this->error('Geef een stadsnaam op, of gebruik --all-belgium of --all-germany.');

            return self::FAILURE;
        }

        return $this->importCity($overpass, $importer, $city);
    }

    private function importCity(OverpassService $overpass, StoreImportService $importer, string $city): int
    {
        $this->info("Zoeken naar fietswinkels in \"{$city}\" via Overpass API...");

        $stores = $overpass->fetchBicycleShops($city);

        if (empty($stores)) {
            $this->warn("Geen winkels gevonden voor \"{$city}\".");

            return self::FAILURE;
        }

        $result = $importer->import($stores, $city);

        $this->table(
            ['Stad', 'Gevonden', 'Nieuw', 'Duplicaten', 'Stad ingevuld'],
            [[$city, $result['found'], $result['created'], $result['duplicates'], $result['updated']]]
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $cities
     */
    private function importMultipleCities(OverpassService $overpass, StoreImportService $importer, array $cities): int
    {
        $this->info('Importeren van fietswinkels uit '.count($cities).' Belgische steden...');
        $this->newLine();

        $totals = ['found' => 0, 'created' => 0, 'duplicates' => 0, 'updated' => 0];
        $rows = [];

        foreach ($cities as $city) {
            $this->info("→ {$city}...");

            $stores = $overpass->fetchBicycleShops($city);

            if (empty($stores)) {
                $this->warn("  Geen resultaten voor \"{$city}\", overgeslagen.");
                $rows[] = [$city, 0, 0, 0, 0];

                continue;
            }

            $result = $importer->import($stores, $city);

            $totals['found'] += $result['found'];
            $totals['created'] += $result['created'];
            $totals['duplicates'] += $result['duplicates'];
            $totals['updated'] += $result['updated'];

            $rows[] = [$city, $result['found'], $result['created'], $result['duplicates'], $result['updated']];
        }

        $this->newLine();
        $this->table(
            ['Stad', 'Gevonden', 'Nieuw', 'Duplicaten', 'Stad ingevuld'],
            $rows
        );

        $this->newLine();
        $this->info("Totaal: {$totals['found']} gevonden, {$totals['created']} nieuw opgeslagen, {$totals['duplicates']} duplicaten overgeslagen, {$totals['updated']} steden aangevuld.");

        return self::SUCCESS;
    }

    private function importRegion(OverpassService $overpass, StoreImportService $importer, string $region): array
    {
        $this->info("→ {$region}...");

        $stores = $overpass->fetchBicycleShopsInRegion($region);

        if (empty($stores)) {
            $this->warn("  Geen resultaten voor \"{$region}\", overgeslagen.");

            return ['found' => 0, 'created' => 0, 'duplicates' => 0, 'updated' => 0, 'geocoded' => 0];
        }

        $this->info("  {$region}: ".count($stores).' winkels gevonden.');

        // Geocode stores without city
        $missingCount = collect($stores)->filter(fn ($s) => empty($s['city']) && ! empty($s['latitude']))->count();

        $geocoded = 0;
        if ($missingCount > 0) {
            $this->info("  Geocoding {$missingCount} winkels zonder stad...");

            $geocoded = $importer->geocodeMissingCities($stores, function (int $current, int $total) {
                if ($current % 25 === 0 || $current === $total) {
                    $this->output->write("\r  Geocoding: {$current}/{$total}");
                }
            });

            $this->newLine();
            $this->info("  {$geocoded} steden ingevuld via geocoding.");
        }

        $result = $importer->import($stores, fallbackCountry: 'DE');
        $result['geocoded'] = $geocoded;

        return $result;
    }

    /**
     * @param  list<string>  $regions
     */
    private function importMultipleRegions(OverpassService $overpass, StoreImportService $importer, array $regions): int
    {
        $this->info('Importeren van fietswinkels uit '.count($regions).' Duitse Bundesländer...');
        $this->newLine();

        $totals = ['found' => 0, 'created' => 0, 'duplicates' => 0, 'updated' => 0, 'geocoded' => 0];
        $rows = [];

        foreach ($regions as $region) {
            $result = $this->importRegion($overpass, $importer, $region);

            $totals['found'] += $result['found'];
            $totals['created'] += $result['created'];
            $totals['duplicates'] += $result['duplicates'];
            $totals['updated'] += $result['updated'];
            $totals['geocoded'] += $result['geocoded'];

            $rows[] = [
                $region,
                $result['found'],
                $result['created'],
                $result['duplicates'],
                $result['updated'],
                $result['geocoded'],
            ];

            $this->newLine();
        }

        $this->table(
            ['Bundesland', 'Gevonden', 'Nieuw', 'Duplicaten', 'Stad ingevuld', 'Geocoded'],
            $rows
        );

        $this->newLine();
        $this->info("Totaal: {$totals['found']} gevonden, {$totals['created']} nieuw opgeslagen, {$totals['duplicates']} duplicaten overgeslagen, {$totals['updated']} steden aangevuld, {$totals['geocoded']} geocoded.");

        return self::SUCCESS;
    }
}
