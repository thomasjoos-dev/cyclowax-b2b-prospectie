<?php

use App\Enums\BrandCategory;
use App\Enums\DiscoverySource;
use App\Models\Brand;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createOrbea(): Brand
{
    return Brand::factory()->create([
        'name' => 'Orbea',
        'slug' => 'orbea',
        'category' => BrandCategory::RaceBike,
    ]);
}

function createTestCsv(string $content): string
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'csv_cmd_test_');
    file_put_contents($tmpFile, $content);

    return $tmpFile;
}

test('command creates pivot records for matched dealers', function () {
    $brand = createOrbea();

    $store = Store::factory()->create([
        'name' => 'Bike Center Gent',
        'postal_code' => '9000',
        'city' => 'Gent',
    ]);

    $csvFile = createTestCsv(
        "Naam,Adres,Land,Lat,Lng,Website,Telefoon,Email\n".
        "Bike Center Gent,\"Veldstraat 10, 9000 Gent (Oost-Vlaanderen). Be\",Be,51.05,3.72,https://example.be,,\n"
    );

    $this->artisan('brands:import-csv', ['brand' => 'orbea', 'file' => $csvFile])
        ->assertSuccessful();

    unlink($csvFile);

    expect($brand->stores()->count())->toBe(1)
        ->and($brand->stores()->first()->id)->toBe($store->id)
        ->and($brand->stores()->first()->pivot->discovery_source)->toBe(DiscoverySource::CsvImport->value);
});

test('unmatched dealers are created as new stores with CsvImport source', function () {
    $brand = createOrbea();

    $storeCountBefore = Store::count();

    $csvFile = createTestCsv(
        "Naam,Adres,Land,Lat,Lng,Website,Telefoon,Email\n".
        "New Fietsenwinkel,\"Hoogstraat 5, 3000 Leuven (Vlaams-Brabant). Be\",Be,50.88,4.70,https://fietsen.be,+32 16 12 34 56,info@fietsen.be\n"
    );

    $this->artisan('brands:import-csv', ['brand' => 'orbea', 'file' => $csvFile])
        ->assertSuccessful();

    unlink($csvFile);

    $newStore = Store::query()->where('name', 'New Fietsenwinkel')->first();

    expect(Store::count())->toBe($storeCountBefore + 1)
        ->and($newStore)->not->toBeNull()
        ->and($newStore->discovery_source)->toBe(DiscoverySource::CsvImport)
        ->and($newStore->city)->toBe('Leuven')
        ->and($newStore->website)->toBe('https://fietsen.be')
        ->and($newStore->phone)->toBe('+32 16 12 34 56')
        ->and($brand->stores()->count())->toBe(1)
        ->and($brand->stores()->first()->id)->toBe($newStore->id);
});

test('dry-run does not create any records', function () {
    createOrbea();

    $storeCountBefore = Store::count();

    $csvFile = createTestCsv(
        "Naam,Adres,Land,Lat,Lng,Website,Telefoon,Email\n".
        "Some Shop,\"Straat 1, 1000 Brussel (Brussel). Be\",Be,50.85,4.35,,,\n"
    );

    $this->artisan('brands:import-csv', [
        'brand' => 'orbea',
        'file' => $csvFile,
        '--dry-run' => true,
    ])->assertSuccessful();

    unlink($csvFile);

    $brand = Brand::query()->where('slug', 'orbea')->first();

    expect($brand->stores()->count())->toBe(0)
        ->and(Store::count())->toBe($storeCountBefore);
});

test('unknown brand returns failure', function () {
    $csvFile = createTestCsv("Naam,Adres,Land,Lat,Lng,Website,Telefoon,Email\n");

    $this->artisan('brands:import-csv', ['brand' => 'nonexistent', 'file' => $csvFile])
        ->assertFailed();

    unlink($csvFile);
});

test('nonexistent file returns failure', function () {
    createOrbea();

    $this->artisan('brands:import-csv', ['brand' => 'orbea', 'file' => '/tmp/does_not_exist.csv'])
        ->assertFailed();
});

test('empty CSV shows warning', function () {
    createOrbea();

    $csvFile = createTestCsv("Naam,Adres,Land,Lat,Lng,Website,Telefoon,Email\n");

    $this->artisan('brands:import-csv', ['brand' => 'orbea', 'file' => $csvFile])
        ->assertFailed();

    unlink($csvFile);
});

test('country filter limits import', function () {
    $brand = createOrbea();

    $csvFile = createTestCsv(
        "Naam,Adres,Land,Lat,Lng,Website,Telefoon,Email\n".
        "BE Shop,\"Straat 1, 1000 Brussel (Brussel). Be\",Be,50.85,4.35,,,\n".
        "NL Shop,\"Straat 5, 1012 AB Amsterdam (Noord-Holland). Nl\",Nl,52.37,4.89,,,\n"
    );

    $this->artisan('brands:import-csv', [
        'brand' => 'orbea',
        'file' => $csvFile,
        '--country' => 'NL',
    ])->assertSuccessful();

    unlink($csvFile);

    expect($brand->stores()->count())->toBe(1)
        ->and(Store::query()->where('name', 'NL Shop')->exists())->toBeTrue()
        ->and(Store::query()->where('name', 'BE Shop')->exists())->toBeFalse();
});
