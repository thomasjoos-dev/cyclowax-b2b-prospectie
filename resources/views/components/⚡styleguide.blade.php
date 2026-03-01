<?php

use Livewire\Component;

new class extends Component
{
    public string $exampleSelect = '';

    public function render(): mixed
    {
        return $this
            ->view()
            ->layout('components.layouts.app')
            ->title('Styleguide');
    }
};
?>

@php
    use App\Models\Store;

    $exampleOptions = [
        ['id' => '1', 'name' => 'Optie A'],
        ['id' => '2', 'name' => 'Optie B'],
        ['id' => '3', 'name' => 'Optie C'],
    ];
@endphp

<div>
    <x-header title="Styleguide" subtitle="Cyclowax design system referentie" separator />

    {{-- Kleurenpalet --}}
    <x-card title="Kleurenpalet" subtitle="daisyUI thema kleuren" shadow separator class="mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-primary"></div>
                <span class="text-xs font-medium">Primary</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-secondary"></div>
                <span class="text-xs font-medium">Secondary</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-accent"></div>
                <span class="text-xs font-medium">Accent</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-neutral"></div>
                <span class="text-xs font-medium">Neutral</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-info"></div>
                <span class="text-xs font-medium">Info</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-success"></div>
                <span class="text-xs font-medium">Success</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-warning"></div>
                <span class="text-xs font-medium">Warning</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-error"></div>
                <span class="text-xs font-medium">Error</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-base-100 border border-base-300"></div>
                <span class="text-xs font-medium">Base 100</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-base-200"></div>
                <span class="text-xs font-medium">Base 200</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-base-300"></div>
                <span class="text-xs font-medium">Base 300</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <div class="w-full h-16 rounded-lg bg-base-content"></div>
                <span class="text-xs font-medium">Base Content</span>
            </div>
        </div>
    </x-card>

    {{-- Buttons --}}
    <x-card title="Buttons" subtitle="Varianten en maten" shadow separator class="mb-6">
        <div class="space-y-4">
            <div>
                <h4 class="text-sm font-semibold mb-2">Varianten</h4>
                <div class="flex flex-wrap gap-2">
                    <x-button label="Primary" class="btn-primary" />
                    <x-button label="Secondary" class="btn-secondary" />
                    <x-button label="Accent" class="btn-accent" />
                    <x-button label="Neutral" class="btn-neutral" />
                    <x-button label="Ghost" class="btn-ghost" />
                    <x-button label="Outline" class="btn-outline" />
                </div>
            </div>
            <div>
                <h4 class="text-sm font-semibold mb-2">Soft varianten</h4>
                <div class="flex flex-wrap gap-2">
                    <x-button label="Primary" class="btn-primary btn-soft" />
                    <x-button label="Info" class="btn-info btn-soft" />
                    <x-button label="Success" class="btn-success btn-soft" />
                    <x-button label="Warning" class="btn-warning btn-soft" />
                    <x-button label="Error" class="btn-error btn-soft" />
                </div>
            </div>
            <div>
                <h4 class="text-sm font-semibold mb-2">Maten</h4>
                <div class="flex flex-wrap items-center gap-2">
                    <x-button label="Extra small" class="btn-xs" />
                    <x-button label="Small" class="btn-sm" />
                    <x-button label="Normal" />
                    <x-button label="Large" class="btn-lg" />
                </div>
            </div>
            <div>
                <h4 class="text-sm font-semibold mb-2">Met iconen</h4>
                <div class="flex flex-wrap gap-2">
                    <x-button label="Zoeken" icon="o-magnifying-glass" class="btn-primary" />
                    <x-button label="Toevoegen" icon="o-plus" class="btn-success" />
                    <x-button label="Verwijderen" icon="o-trash" class="btn-error btn-outline" />
                    <x-button icon="o-cog-6-tooth" class="btn-circle btn-ghost" />
                </div>
            </div>
        </div>
    </x-card>

    {{-- Badges --}}
    <x-card title="Badges" subtitle="Pipeline status kleuren" shadow separator class="mb-6">
        <div class="space-y-4">
            <div>
                <h4 class="text-sm font-semibold mb-2">Pipeline statussen</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach (Store::$statusLabels as $value => $label)
                        @php
                            $badgeClass = match($value) {
                                'niet_gecontacteerd' => 'badge-neutral badge-soft',
                                'gecontacteerd'      => 'badge-info badge-soft',
                                'in_gesprek'         => 'badge-warning badge-soft',
                                'partner'            => 'badge-success badge-soft',
                                'afgewezen'          => 'badge-error badge-soft',
                                default              => '',
                            };
                        @endphp
                        <x-badge value="{{ $label }}" class="{{ $badgeClass }}" />
                    @endforeach
                </div>
            </div>
            <div>
                <h4 class="text-sm font-semibold mb-2">Overige varianten</h4>
                <div class="flex flex-wrap gap-2">
                    <x-badge value="Default" />
                    <x-badge value="Primary" class="badge-primary" />
                    <x-badge value="Secondary" class="badge-secondary" />
                    <x-badge value="Outline" class="badge-outline" />
                    <x-badge value="Dash" class="badge-dash" />
                </div>
            </div>
        </div>
    </x-card>

    {{-- Input velden --}}
    <x-card title="Input velden" subtitle="Formulier elementen" shadow separator class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl">
            <x-input label="Standaard" placeholder="Typ iets..." />
            <x-input label="Met icoon" icon="o-magnifying-glass" placeholder="Zoeken..." />
            <x-input label="Met hint" hint="Dit is een hulptekst" placeholder="Naam..." />
            <x-input label="Disabled" placeholder="Niet bewerkbaar" disabled />
        </div>
    </x-card>

    {{-- Select --}}
    <x-card title="Select" subtitle="Dropdown selecties" shadow separator class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl">
            <x-select label="Standaard" wire:model="exampleSelect" :options="$exampleOptions" placeholder="Kies een optie..." />
            <x-select label="Met icoon" icon="o-funnel" wire:model="exampleSelect" :options="$exampleOptions" placeholder="Filter..." />
        </div>
    </x-card>

    {{-- Stats --}}
    <x-card title="Statistieken" subtitle="Dashboard elementen" shadow separator class="mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <x-stat title="Totaal" value="142" icon="o-building-storefront" color="text-primary" />
            <x-stat title="Partners" value="23" icon="o-check-circle" color="text-success" />
            <x-stat title="In gesprek" value="18" icon="o-chat-bubble-left-right" color="text-warning" />
            <x-stat title="Afgewezen" value="7" icon="o-x-circle" color="text-error" />
        </div>
    </x-card>

    {{-- Cards --}}
    <x-card title="Cards" subtitle="Container componenten" shadow separator class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-card title="Basis card" subtitle="Met titel en subtitel" shadow>
                Dit is de inhoud van de card.
            </x-card>
            <x-card title="Met actie" shadow>
                Card met een actie-button.
                <x-slot:actions>
                    <x-button label="Actie" class="btn-primary btn-sm" />
                </x-slot:actions>
            </x-card>
            <x-card title="Met separator" shadow separator>
                Card met separator lijn.
            </x-card>
        </div>
    </x-card>

    {{-- Table voorbeeld --}}
    <x-card title="Table" subtitle="Data weergave" shadow separator class="mb-6">
        @php
            $demoHeaders = [
                ['key' => 'name', 'label' => 'Naam'],
                ['key' => 'city', 'label' => 'Stad'],
                ['key' => 'status', 'label' => 'Status'],
            ];
            $demoRows = collect([
                (object) ['id' => 1, 'name' => 'Fietsenwinkel De Wielen', 'city' => 'Gent', 'status' => 'Partner'],
                (object) ['id' => 2, 'name' => 'BikeShop Antwerp', 'city' => 'Antwerpen', 'status' => 'In gesprek'],
                (object) ['id' => 3, 'name' => 'Velo Plus', 'city' => 'Brussel', 'status' => 'Niet gecontacteerd'],
            ]);
        @endphp
        <x-table :headers="$demoHeaders" :rows="$demoRows" striped />
    </x-card>
</div>
