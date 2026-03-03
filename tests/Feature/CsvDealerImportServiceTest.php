<?php

use App\Services\CsvDealerImportService;

test('parseAddress extracts Belgian address correctly', function () {
    $service = new CsvDealerImportService;

    $result = $service->parseAddress('Place Josephine Charlotte, 5100 Namur (Namur). Be');

    expect($result)->toBe([
        'street' => 'Place Josephine Charlotte',
        'postal_code' => '5100',
        'city' => 'Namur',
    ]);
});

test('parseAddress extracts Dutch address with letter suffix correctly', function () {
    $service = new CsvDealerImportService;

    $result = $service->parseAddress('Kerkstraat 31 A, 8151 AP Lemelerveld (Overijssel). Nl');

    expect($result)->toBe([
        'street' => 'Kerkstraat 31 A',
        'postal_code' => '8151AP',
        'city' => 'Lemelerveld',
    ]);
});

test('parseAddress handles numbered street prefix', function () {
    $service = new CsvDealerImportService;

    $result = $service->parseAddress('25, Grand Place, 7370 Dour (Hainaut). Be');

    expect($result)->toBe([
        'street' => '25, Grand Place',
        'postal_code' => '7370',
        'city' => 'Dour',
    ]);
});

test('parseAddress handles empty address', function () {
    $service = new CsvDealerImportService;

    $result = $service->parseAddress('');

    expect($result)->toBe([
        'street' => null,
        'postal_code' => null,
        'city' => null,
    ]);
});

test('parseAddress does not treat St as NL postal suffix', function () {
    $service = new CsvDealerImportService;

    $result = $service->parseAddress('Leuvensesteenweg 38, 1932 St Stevens Woluwe (Hainaut). Be');

    expect($result)->toBe([
        'street' => 'Leuvensesteenweg 38',
        'postal_code' => '1932',
        'city' => 'St Stevens Woluwe',
    ]);
});

test('parseAddress handles Luxembourg address', function () {
    $service = new CsvDealerImportService;

    $result = $service->parseAddress('Rue de Bonnevoie 12, 1260 Luxembourg (Luxembourg). Lu');

    expect($result)->toBe([
        'street' => 'Rue de Bonnevoie 12',
        'postal_code' => '1260',
        'city' => 'Luxembourg',
    ]);
});

test('parseCsvFile reads dealers from CSV file', function () {
    $csvContent = "Naam,Adres,Land,Lat,Lng,Website,Telefoon,Email\n";
    $csvContent .= "Bike Shop Gent,\"Veldstraat 10, 9000 Gent (Oost-Vlaanderen). Be\",Be,51.05,3.72,https://bikeshop.be,+32 9 123 45 67,info@bikeshop.be\n";
    $csvContent .= "Fietsen Janssens,\"Grote Markt 1, 2000 Antwerpen (Antwerpen). Be\",Be,51.22,4.40,,+32 3 987 65 43,\n";

    $tmpFile = tempnam(sys_get_temp_dir(), 'csv_test_');
    file_put_contents($tmpFile, $csvContent);

    $service = new CsvDealerImportService;
    $dealers = $service->parseCsvFile($tmpFile);

    unlink($tmpFile);

    expect($dealers)->toHaveCount(2)
        ->and($dealers[0])->toMatchArray([
            'name' => 'Bike Shop Gent',
            'address' => 'Veldstraat 10',
            'city' => 'Gent',
            'postal_code' => '9000',
            'country' => 'BE',
            'website' => 'https://bikeshop.be',
            'phone' => '+32 9 123 45 67',
            'email' => 'info@bikeshop.be',
        ])
        ->and($dealers[0]['latitude'])->toBe(51.05)
        ->and($dealers[1])->toMatchArray([
            'name' => 'Fietsen Janssens',
            'city' => 'Antwerpen',
            'postal_code' => '2000',
            'country' => 'BE',
            'website' => null,
            'email' => null,
        ]);
});

test('parseCsvFile filters by country', function () {
    $csvContent = "Naam,Adres,Land,Lat,Lng,Website,Telefoon,Email\n";
    $csvContent .= "BE Shop,\"Rue Test 1, 1000 Bruxelles (Bruxelles). Be\",Be,50.85,4.35,,,\n";
    $csvContent .= "NL Shop,\"Straat 5, 1012 AB Amsterdam (Noord-Holland). Nl\",Nl,52.37,4.89,,,\n";

    $tmpFile = tempnam(sys_get_temp_dir(), 'csv_test_');
    file_put_contents($tmpFile, $csvContent);

    $service = new CsvDealerImportService;
    $dealers = $service->parseCsvFile($tmpFile, 'BE');

    unlink($tmpFile);

    expect($dealers)->toHaveCount(1)
        ->and($dealers[0]['name'])->toBe('BE Shop');
});

test('parseCsvFile deduplicates on name and postal code', function () {
    $csvContent = "Naam,Adres,Land,Lat,Lng,Website,Telefoon,Email\n";
    $csvContent .= "Bike Shop,\"Straat 1, 9000 Gent (Oost-Vlaanderen). Be\",Be,51.05,3.72,,,\n";
    $csvContent .= "Bike Shop,\"Straat 2, 9000 Gent (Oost-Vlaanderen). Be\",Be,51.06,3.73,,,\n";

    $tmpFile = tempnam(sys_get_temp_dir(), 'csv_test_');
    file_put_contents($tmpFile, $csvContent);

    $service = new CsvDealerImportService;
    $dealers = $service->parseCsvFile($tmpFile);

    unlink($tmpFile);

    expect($dealers)->toHaveCount(1);
});

test('parseCsvFile returns empty array for nonexistent file', function () {
    $service = new CsvDealerImportService;

    expect($service->parseCsvFile('/nonexistent/file.csv'))->toBe([]);
});

test('parseCsvFile skips rows with empty name', function () {
    $csvContent = "Naam,Adres,Land,Lat,Lng,Website,Telefoon,Email\n";
    $csvContent .= ",\"Straat 1, 9000 Gent (Oost-Vlaanderen). Be\",Be,51.05,3.72,,,\n";
    $csvContent .= "Real Shop,\"Straat 2, 9000 Gent (Oost-Vlaanderen). Be\",Be,51.06,3.73,,,\n";

    $tmpFile = tempnam(sys_get_temp_dir(), 'csv_test_');
    file_put_contents($tmpFile, $csvContent);

    $service = new CsvDealerImportService;
    $dealers = $service->parseCsvFile($tmpFile);

    unlink($tmpFile);

    expect($dealers)->toHaveCount(1)
        ->and($dealers[0]['name'])->toBe('Real Shop');
});
