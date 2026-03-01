<?php

namespace App\Console\Commands;

use App\Services\OverpassService;
use App\Services\StoreImportService;
use Illuminate\Console\Command;

class ImportOverpassStores extends Command
{
    protected $signature = 'stores:import-overpass
        {city? : De stad of regio om fietswinkels in te zoeken}
        {--all-belgium : Importeer fietswinkels uit alle grote Belgische steden}';

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

    public function handle(OverpassService $overpass, StoreImportService $importer): int
    {
        if ($this->option('all-belgium')) {
            return $this->importMultipleCities($overpass, $importer, self::BELGIAN_CITIES);
        }

        $city = $this->argument('city');

        if (! $city) {
            $this->error('Geef een stadsnaam op, of gebruik --all-belgium.');

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
}
