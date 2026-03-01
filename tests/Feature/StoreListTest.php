<?php

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('stores page loads successfully', function () {
    Store::factory()->count(3)->create();

    $this->get('/stores')->assertSuccessful();
});

test('search filters stores by name', function () {
    Store::factory()->create(['name' => 'Fietsenhuis Janssens']);
    Store::factory()->create(['name' => 'Bike Center Peeters']);

    Livewire::test('store-list')
        ->set('search', 'Janssens')
        ->assertSee('Fietsenhuis Janssens')
        ->assertDontSee('Bike Center Peeters');
});

test('status filter shows only matching stores', function () {
    Store::factory()->create(['name' => 'Shop A', 'pipeline_status' => 'niet_gecontacteerd']);
    Store::factory()->create(['name' => 'Shop B', 'pipeline_status' => 'partner']);

    Livewire::test('store-list')
        ->set('statusFilter', 'partner')
        ->assertSee('Shop B')
        ->assertDontSee('Shop A');
});

test('city filter shows only stores in selected city', function () {
    Store::factory()->create(['name' => 'Antwerpen Shop', 'city' => 'Antwerpen']);
    Store::factory()->create(['name' => 'Gent Shop', 'city' => 'Gent']);

    Livewire::test('store-list')
        ->set('cityFilter', 'Antwerpen')
        ->assertSee('Antwerpen Shop')
        ->assertDontSee('Gent Shop');
});

test('contact filter shows stores with email', function () {
    Store::factory()->create(['name' => 'With Email', 'email' => 'test@example.com']);
    Store::factory()->create(['name' => 'Without Email', 'email' => null]);

    Livewire::test('store-list')
        ->set('contactFilter', 'email')
        ->assertSee('With Email')
        ->assertDontSee('Without Email');
});

test('contact filter complete shows only stores with all contact info', function () {
    Store::factory()->withCompleteContact()->create(['name' => 'Complete Store']);
    Store::factory()->create(['name' => 'Incomplete Store', 'email' => 'test@example.com', 'phone' => null, 'website' => null]);

    Livewire::test('store-list')
        ->set('contactFilter', 'complete')
        ->assertSee('Complete Store')
        ->assertDontSee('Incomplete Store');
});

test('status update works and sets last_contacted_at when leaving niet_gecontacteerd', function () {
    $store = Store::factory()->create([
        'pipeline_status' => 'niet_gecontacteerd',
        'last_contacted_at' => null,
    ]);

    Livewire::test('store-list')
        ->call('updateStatus', $store->id, 'gecontacteerd');

    $store->refresh();
    expect($store->pipeline_status)->toBe('gecontacteerd')
        ->and($store->last_contacted_at)->not->toBeNull();
});

test('status update does not overwrite last_contacted_at when already contacted', function () {
    $originalDate = now()->subDays(5);
    $store = Store::factory()->create([
        'pipeline_status' => 'gecontacteerd',
        'last_contacted_at' => $originalDate,
    ]);

    Livewire::test('store-list')
        ->call('updateStatus', $store->id, 'in_gesprek');

    $store->refresh();
    expect($store->pipeline_status)->toBe('in_gesprek')
        ->and($store->last_contacted_at->format('Y-m-d'))->toBe($originalDate->format('Y-m-d'));
});

test('saving notes updates the store', function () {
    $store = Store::factory()->create(['notes' => null]);

    Livewire::test('store-list')
        ->call('selectStore', $store->id)
        ->assertSet('showDrawer', true)
        ->set('drawerNotes', 'Interessante winkel, nabellen volgende week')
        ->call('saveNotes')
        ->assertSet('showDrawer', false);

    expect($store->refresh()->notes)->toBe('Interessante winkel, nabellen volgende week');
});

test('selecting a store opens the drawer', function () {
    $store = Store::factory()->create(['name' => 'Test Fietswinkel']);

    Livewire::test('store-list')
        ->call('selectStore', $store->id)
        ->assertSet('showDrawer', true)
        ->assertSet('selectedStoreId', $store->id);
});

test('pipeline stats reflect active filters', function () {
    Store::factory()->create(['city' => 'Antwerpen', 'pipeline_status' => 'niet_gecontacteerd']);
    Store::factory()->create(['city' => 'Antwerpen', 'pipeline_status' => 'partner']);
    Store::factory()->create(['city' => 'Gent', 'pipeline_status' => 'niet_gecontacteerd']);

    $component = Livewire::test('store-list')
        ->set('cityFilter', 'Antwerpen');

    $stats = $component->get('pipelineStats');
    expect($stats['total'])->toBe(2)
        ->and($stats['niet_gecontacteerd'])->toBe(1)
        ->and($stats['partner'])->toBe(1);
});

// --- New tests: Country filter ---

test('country filter shows only stores in selected country', function () {
    Store::factory()->create(['name' => 'Belgian Shop', 'country' => 'BE']);
    Store::factory()->create(['name' => 'German Shop', 'country' => 'DE']);

    Livewire::test('store-list')
        ->set('countryFilter', 'BE')
        ->assertSee('Belgian Shop')
        ->assertDontSee('German Shop');
});

test('pipeline stats react to country filter', function () {
    Store::factory()->create(['country' => 'BE', 'pipeline_status' => 'niet_gecontacteerd']);
    Store::factory()->create(['country' => 'BE', 'pipeline_status' => 'partner']);
    Store::factory()->create(['country' => 'DE', 'pipeline_status' => 'niet_gecontacteerd']);

    $stats = Livewire::test('store-list')
        ->set('countryFilter', 'BE')
        ->get('pipelineStats');

    expect($stats['total'])->toBe(2)
        ->and($stats['niet_gecontacteerd'])->toBe(1)
        ->and($stats['partner'])->toBe(1);
});

test('city options react to country filter', function () {
    Store::factory()->create(['city' => 'Antwerpen', 'country' => 'BE']);
    Store::factory()->create(['city' => 'Berlin', 'country' => 'DE']);

    $cities = Livewire::test('store-list')
        ->set('countryFilter', 'BE')
        ->get('cityOptions');

    $cityNames = collect($cities)->pluck('name')->toArray();
    expect($cityNames)->toContain('Antwerpen')
        ->and($cityNames)->not->toContain('Berlin');
});

// --- New tests: Assigned filter ---

test('assigned filter shows only stores of selected team member', function () {
    Store::factory()->assignedTo('olivier')->create(['name' => 'Olivier Shop']);
    Store::factory()->assignedTo('meik')->create(['name' => 'Meik Shop']);

    Livewire::test('store-list')
        ->set('assignedFilter', 'olivier')
        ->assertSee('Olivier Shop')
        ->assertDontSee('Meik Shop');
});

test('assigned filter unassigned shows only stores without assignment', function () {
    Store::factory()->create(['name' => 'Unassigned Shop', 'assigned_to' => null]);
    Store::factory()->assignedTo('olivier')->create(['name' => 'Assigned Shop']);

    Livewire::test('store-list')
        ->set('assignedFilter', '_unassigned')
        ->assertSee('Unassigned Shop')
        ->assertDontSee('Assigned Shop');
});

// --- New tests: Bulk assignment ---

test('bulk assignment assigns multiple stores at once', function () {
    $stores = Store::factory()->count(3)->create(['assigned_to' => null]);

    Livewire::test('store-list')
        ->set('selectedIds', $stores->pluck('id')->toArray())
        ->set('bulkAssignTo', 'olivier')
        ->call('assignSelected');

    foreach ($stores as $store) {
        expect($store->refresh()->assigned_to)->toBe('olivier');
    }
});

test('bulk assignment clears selection after assigning', function () {
    $stores = Store::factory()->count(2)->create();

    Livewire::test('store-list')
        ->set('selectedIds', $stores->pluck('id')->toArray())
        ->set('bulkAssignTo', 'meik')
        ->call('assignSelected')
        ->assertSet('selectedIds', []);
});

// --- New tests: Individual assignment from drawer ---

test('individual assignment from drawer works', function () {
    $store = Store::factory()->create(['assigned_to' => null]);

    Livewire::test('store-list')
        ->call('selectStore', $store->id)
        ->call('assignStore', 'jakob');

    expect($store->refresh()->assigned_to)->toBe('jakob');
});

test('individual assignment from drawer can remove assignment', function () {
    $store = Store::factory()->assignedTo('olivier')->create();

    Livewire::test('store-list')
        ->call('selectStore', $store->id)
        ->call('assignStore', '');

    expect($store->refresh()->assigned_to)->toBeNull();
});
