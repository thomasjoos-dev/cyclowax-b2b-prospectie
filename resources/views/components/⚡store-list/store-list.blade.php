@php
    use App\Models\Store;

    $statusOptions = collect(Store::$statusLabels)->map(fn ($label, $value) => [
        'id' => $value,
        'name' => $label,
    ])->values()->toArray();

    $filterTabs = array_merge(['all' => 'Alle'], Store::$statusLabels);

    $badgeClasses = [
        'niet_gecontacteerd' => 'badge-neutral badge-soft',
        'gecontacteerd'      => 'badge-info badge-soft',
        'in_gesprek'         => 'badge-warning badge-soft',
        'partner'            => 'badge-success badge-soft',
        'afgewezen'          => 'badge-error badge-soft',
    ];

    $contactFilterOptions = [
        ['id' => '', 'name' => 'Alle contactinfo'],
        ['id' => 'email', 'name' => 'Heeft email'],
        ['id' => 'phone', 'name' => 'Heeft telefoon'],
        ['id' => 'website', 'name' => 'Heeft website'],
        ['id' => 'complete', 'name' => 'Compleet (alle drie)'],
    ];

    $stats = $this->pipelineStats;

    $teamMembers = config('team.members', []);
@endphp

<div>
    {{-- Header --}}
    <x-header title="Fietswinkels" subtitle="Werklijst voor het salesteam" separator />

    {{-- Pipeline stats --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <x-stat
            title="Totaal"
            :value="$stats['total']"
            icon="o-building-storefront"
        />
        <x-stat
            title="Niet gecontacteerd"
            :value="$stats['niet_gecontacteerd']"
            icon="o-clock"
            color="text-base-content/50"
        />
        <x-stat
            title="In behandeling"
            :value="$stats['in_behandeling']"
            icon="o-chat-bubble-left-right"
            color="text-info"
        />
        <x-stat
            title="Partners"
            :value="$stats['partner']"
            icon="o-check-circle"
            color="text-success"
        />
        <x-stat
            title="Afgewezen"
            :value="$stats['afgewezen']"
            icon="o-x-circle"
            color="text-error"
        />
    </div>

    {{-- Filters --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
        <x-input
            icon="o-magnifying-glass"
            placeholder="Zoek op naam..."
            wire:model.live.debounce.300ms="search"
            clearable
        />
        <x-select
            :options="$this->countryOptions"
            wire:model.live="countryFilter"
            placeholder="Alle landen"
            placeholder-value=""
        />
        <x-select
            :options="$this->cityOptions"
            wire:model.live="cityFilter"
            placeholder="Alle steden"
            placeholder-value=""
        />
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
        <x-select
            :options="$contactFilterOptions"
            wire:model.live="contactFilter"
        />
        <x-select
            :options="$this->teamOptions"
            wire:model.live="assignedFilter"
            placeholder="Alle teamleden"
            placeholder-value=""
        />
    </div>

    {{-- Status tabs --}}
    <div class="flex flex-wrap gap-1 mb-4">
        @foreach ($filterTabs as $value => $label)
            @php
                $isActive = ($value === 'all' && $statusFilter === '') || $statusFilter === $value;
                $count = $this->statusCounts[$value] ?? 0;
            @endphp
            <x-button
                wire:click="$set('statusFilter', '{{ $value === 'all' ? '' : $value }}')"
                label="{{ $label }}"
                badge="{{ $count }}"
                class="{{ $isActive ? 'btn-primary btn-sm' : 'btn-ghost btn-sm' }}"
                badge-classes="{{ $isActive ? 'badge-neutral' : '' }}"
            />
        @endforeach
    </div>

    {{-- Bulk action bar --}}
    @if (count($selectedIds) > 0)
        <div class="flex flex-wrap items-center gap-3 mb-4 p-3 bg-primary/10 rounded-lg border border-primary/20">
            <span class="text-sm font-medium">{{ count($selectedIds) }} winkels geselecteerd</span>
            <x-select
                :options="collect($teamMembers)->map(fn ($m, $k) => ['id' => $k, 'name' => $m['name']])->values()->toArray()"
                wire:model="bulkAssignTo"
                placeholder="Toewijzen aan..."
                placeholder-value=""
                class="select-sm w-48"
            />
            <x-button
                wire:click="assignSelected"
                label="Toewijzen"
                icon="o-user-plus"
                class="btn-primary btn-sm"
                spinner="assignSelected"
                :disabled="$bulkAssignTo === ''"
            />
            <x-button
                wire:click="clearSelection"
                label="Selectie wissen"
                icon="o-x-mark"
                class="btn-ghost btn-sm"
            />
        </div>
    @endif

    {{-- Tabel --}}
    <x-card>
        <x-table
            :headers="$this->headers()"
            :rows="$this->stores"
            :sort-by="$sortBy"
            with-pagination
            show-empty-text
            empty-text="Geen winkels gevonden."
            striped
            selectable
            wire:model.live="selectedIds"
            @row-click="$wire.selectStore($event.detail.id)"
            class="cursor-pointer"
        >
            @scope('cell_contact', $store)
                <div class="flex flex-col gap-1 text-sm">
                    @if ($store->email)
                        <a href="mailto:{{ $store->email }}" class="flex items-center gap-1.5 text-info hover:underline" wire:click.stop>
                            <x-icon name="o-envelope" class="w-3.5 h-3.5" />
                            <span class="truncate max-w-32">{{ Str::after($store->email, '@') }}</span>
                        </a>
                    @endif
                    @if ($store->phone)
                        <a href="tel:{{ $store->phone }}" class="flex items-center gap-1.5 text-base-content/70 hover:underline" wire:click.stop>
                            <x-icon name="o-phone" class="w-3.5 h-3.5" />
                            <span>{{ $store->phone }}</span>
                        </a>
                    @endif
                    @if ($store->website)
                        <a href="{{ $store->website }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1.5 text-base-content/70 hover:underline" wire:click.stop>
                            <x-icon name="o-globe-alt" class="w-3.5 h-3.5" />
                            <span class="truncate max-w-32">{{ parse_url($store->website, PHP_URL_HOST) ?: $store->website }}</span>
                        </a>
                    @endif
                    @if (! $store->email && ! $store->phone && ! $store->website)
                        <x-badge value="Geen contactinfo" class="badge-neutral badge-soft badge-sm" />
                    @endif
                </div>
            @endscope

            @scope('cell_pipeline_status', $store)
                <select
                    wire:change="updateStatus({{ $store->id }}, $event.target.value)"
                    wire:click.stop
                    class="select select-xs select-bordered rounded-full font-medium {{ [
                        'niet_gecontacteerd' => 'badge-neutral badge-soft',
                        'gecontacteerd'      => 'badge-info badge-soft',
                        'in_gesprek'         => 'badge-warning badge-soft',
                        'partner'            => 'badge-success badge-soft',
                        'afgewezen'          => 'badge-error badge-soft',
                    ][$store->pipeline_status] ?? '' }}"
                >
                    @foreach (\App\Models\Store::$statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($store->pipeline_status === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            @endscope

            @scope('cell_assigned_to', $store)
                @if ($store->assigned_to && config('team.members.' . $store->assigned_to))
                    <x-badge :value="config('team.members.' . $store->assigned_to . '.name')" class="badge-soft badge-sm" />
                @else
                    <span class="text-base-content/30">—</span>
                @endif
            @endscope

            @scope('cell_last_contacted_at', $store)
                @if ($store->last_contacted_at)
                    <span class="text-sm text-base-content/70">{{ $store->last_contacted_at->diffForHumans() }}</span>
                @else
                    <span class="text-base-content/30">—</span>
                @endif
            @endscope
        </x-table>
    </x-card>

    {{-- Store detail drawer --}}
    <x-drawer wire:model="showDrawer" right title="{{ $this->selectedStore?->name ?? '' }}" separator with-close-button class="w-11/12 lg:w-1/3">
        @if ($this->selectedStore)
            @php $store = $this->selectedStore; @endphp

            <div class="flex flex-col gap-6">
                {{-- Adres --}}
                <div>
                    <h4 class="text-sm font-semibold text-base-content/50 mb-2">Adres</h4>
                    <div class="text-sm">
                        @if ($store->address)
                            <p>{{ $store->address }}</p>
                        @endif
                        <p>{{ $store->postal_code }} {{ $store->city }}</p>
                        @if ($store->country)
                            <p>{{ $store->country }}</p>
                        @endif
                    </div>
                </div>

                {{-- Contact --}}
                <div>
                    <h4 class="text-sm font-semibold text-base-content/50 mb-2">Contact</h4>
                    <div class="flex flex-col gap-2">
                        @if ($store->email)
                            <a href="mailto:{{ $store->email }}" class="flex items-center gap-2 text-info hover:underline">
                                <x-icon name="o-envelope" class="w-4 h-4" />
                                {{ $store->email }}
                            </a>
                        @endif
                        @if ($store->phone)
                            <a href="tel:{{ $store->phone }}" class="flex items-center gap-2 hover:underline">
                                <x-icon name="o-phone" class="w-4 h-4" />
                                {{ $store->phone }}
                            </a>
                        @endif
                        @if ($store->website)
                            <a href="{{ $store->website }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 hover:underline">
                                <x-icon name="o-globe-alt" class="w-4 h-4" />
                                {{ parse_url($store->website, PHP_URL_HOST) ?: $store->website }}
                            </a>
                        @endif
                        @if (! $store->email && ! $store->phone && ! $store->website)
                            <span class="text-base-content/30 text-sm">Geen contactinformatie beschikbaar</span>
                        @endif
                    </div>
                </div>

                {{-- Toewijzing --}}
                <div>
                    <h4 class="text-sm font-semibold text-base-content/50 mb-2">Toegewezen aan</h4>
                    <select
                        wire:change="assignStore($event.target.value)"
                        class="select select-bordered w-full"
                    >
                        <option value="" @selected(empty($drawerAssignedTo))>Niet toegewezen</option>
                        @foreach ($teamMembers as $key => $member)
                            <option value="{{ $key }}" @selected($drawerAssignedTo === $key)>
                                {{ $member['name'] }} — {{ $member['role'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Pipeline status --}}
                <div>
                    <h4 class="text-sm font-semibold text-base-content/50 mb-2">Pipeline status</h4>
                    <select
                        wire:change="updateStatus({{ $store->id }}, $event.target.value)"
                        class="select select-bordered w-full"
                    >
                        @foreach (\App\Models\Store::$statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($store->pipeline_status === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Laatst gecontacteerd --}}
                <div>
                    <h4 class="text-sm font-semibold text-base-content/50 mb-2">Laatst gecontacteerd</h4>
                    @if ($store->last_contacted_at)
                        <span class="text-sm">{{ $store->last_contacted_at->format('d/m/Y H:i') }} ({{ $store->last_contacted_at->diffForHumans() }})</span>
                    @else
                        <span class="text-base-content/30 text-sm">Nog niet gecontacteerd</span>
                    @endif
                </div>

                {{-- Notities --}}
                <div>
                    <h4 class="text-sm font-semibold text-base-content/50 mb-2">Notities</h4>
                    <textarea
                        wire:model="drawerNotes"
                        class="textarea textarea-bordered w-full h-32"
                        placeholder="Notities over deze winkel..."
                    ></textarea>
                    <x-button
                        wire:click="saveNotes"
                        label="Opslaan"
                        icon="o-check"
                        class="btn-primary btn-sm mt-2"
                        spinner="saveNotes"
                    />
                </div>

                {{-- Discovery source --}}
                @if ($store->discovery_source)
                    <div>
                        <h4 class="text-sm font-semibold text-base-content/50 mb-2">Bron</h4>
                        <x-badge value="{{ ucfirst($store->discovery_source) }}" class="badge-neutral badge-soft badge-sm" />
                    </div>
                @endif
            </div>
        @endif
    </x-drawer>
</div>
