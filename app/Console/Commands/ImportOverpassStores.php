<?php

namespace App\Console\Commands;

use App\Services\OverpassService;
use App\Services\StoreImportService;
use Illuminate\Console\Command;

class ImportOverpassStores extends Command
{
    protected $signature = 'stores:import-overpass {city : De stad of regio om fietswinkels in te zoeken}';

    protected $description = 'Importeer fietswinkels vanuit OpenStreetMap via de Overpass API';

    public function handle(OverpassService $overpass, StoreImportService $importer): int
    {
        $city = $this->argument('city');

        $this->info("Zoeken naar fietswinkels in \"{$city}\" via Overpass API...");

        $stores = $overpass->fetchBicycleShops($city);

        if (empty($stores)) {
            $this->warn('Geen winkels gevonden of API-fout. Controleer de stadsnaam of probeer later opnieuw.');

            return self::FAILURE;
        }

        $result = $importer->import($stores);

        $this->newLine();
        $this->table(
            ['Gevonden', 'Nieuw opgeslagen', 'Duplicaten overgeslagen'],
            [[$result['found'], $result['created'], $result['duplicates']]]
        );

        $this->newLine();
        $this->info("Import voltooid voor \"{$city}\".");

        return self::SUCCESS;
    }
}
