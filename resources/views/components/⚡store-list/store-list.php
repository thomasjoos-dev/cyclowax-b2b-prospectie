<?php

use App\Models\Store;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $cityFilter = '';

    public string $contactFilter = '';

    public string $countryFilter = '';

    public string $assignedFilter = '';

    /** @var array<int, int> */
    public array $selectedIds = [];

    public string $bulkAssignTo = '';

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public bool $showDrawer = false;

    public ?int $selectedStoreId = null;

    public string $drawerNotes = '';

    public string $drawerAssignedTo = '';

    public function updateStatus(int $storeId, string $status): void
    {
        $store = Store::query()->findOrFail($storeId);

        $data = ['pipeline_status' => $status];

        if ($store->pipeline_status === 'niet_gecontacteerd' && $status !== 'niet_gecontacteerd') {
            $data['last_contacted_at'] = now();
        }

        $store->update($data);

        $label = Store::$statusLabels[$status] ?? $status;
        $this->success("Status bijgewerkt naar '{$label}'");
    }

    public function selectStore(int $storeId): void
    {
        $this->selectedStoreId = $storeId;
        $store = Store::query()->findOrFail($storeId);
        $this->drawerNotes = $store->notes ?? '';
        $this->drawerAssignedTo = $store->assigned_to ?? '';
        $this->showDrawer = true;
    }

    public function saveNotes(): void
    {
        Store::query()->findOrFail($this->selectedStoreId)->update([
            'notes' => $this->drawerNotes,
        ]);

        $this->showDrawer = false;
        $this->success('Notities opgeslagen');
    }

    public function assignStore(string $member): void
    {
        $store = Store::query()->findOrFail($this->selectedStoreId);
        $store->update(['assigned_to' => $member ?: null]);

        $this->drawerAssignedTo = $member;

        if ($member) {
            $name = config("team.members.{$member}.name", $member);
            $this->success("Toegewezen aan {$name}");
        } else {
            $this->success('Toewijzing verwijderd');
        }
    }

    public function assignSelected(): void
    {
        if (empty($this->selectedIds) || $this->bulkAssignTo === '') {
            return;
        }

        $assignTo = $this->bulkAssignTo ?: null;

        Store::query()
            ->whereIn('id', $this->selectedIds)
            ->update(['assigned_to' => $assignTo]);

        $count = count($this->selectedIds);

        if ($assignTo) {
            $name = config("team.members.{$assignTo}.name", $assignTo);
            $this->success("{$count} winkels toegewezen aan {$name}");
        } else {
            $this->success("Toewijzing verwijderd voor {$count} winkels");
        }

        $this->selectedIds = [];
        $this->bulkAssignTo = '';
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCityFilter(): void
    {
        $this->resetPage();
    }

    public function updatingContactFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCountryFilter(): void
    {
        $this->cityFilter = '';
        $this->resetPage();
    }

    public function updatingAssignedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int, array{key: string, label: string, class?: string, sortable?: bool}>
     */
    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Naam'],
            ['key' => 'city', 'label' => 'Stad'],
            ['key' => 'contact', 'label' => 'Contact', 'sortable' => false],
            ['key' => 'pipeline_status', 'label' => 'Status'],
            ['key' => 'assigned_to', 'label' => 'Toegewezen', 'sortable' => false],
            ['key' => 'last_contacted_at', 'label' => 'Laatst gecontacteerd'],
        ];
    }

    /** @return array<int, array{id: string, name: string}> */
    #[Computed]
    public function countryOptions(): array
    {
        return Store::query()
            ->whereNotNull('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->map(fn (string $country) => ['id' => $country, 'name' => $country])
            ->toArray();
    }

    /** @return array<int, array{id: string, name: string}> */
    #[Computed]
    public function cityOptions(): array
    {
        return Store::query()
            ->whereNotNull('city')
            ->when($this->countryFilter, fn ($q) => $q->where('country', $this->countryFilter))
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->map(fn (string $city) => ['id' => $city, 'name' => $city])
            ->toArray();
    }

    /** @return array<int, array{id: string, name: string}> */
    #[Computed]
    public function teamOptions(): array
    {
        $options = [['id' => '_unassigned', 'name' => 'Niet toegewezen']];

        foreach (config('team.members', []) as $key => $member) {
            $options[] = ['id' => $key, 'name' => $member['name']];
        }

        return $options;
    }

    /** @return array<string, int> */
    #[Computed]
    public function statusCounts(): array
    {
        $query = Store::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->countryFilter, fn ($q) => $q->where('country', $this->countryFilter))
            ->when($this->cityFilter, fn ($q) => $q->where('city', $this->cityFilter));

        $this->applyContactFilter($query);
        $this->applyAssignedFilter($query);

        $counts = $query
            ->selectRaw('pipeline_status, count(*) as total')
            ->groupBy('pipeline_status')
            ->pluck('total', 'pipeline_status')
            ->toArray();

        return array_merge(
            ['all' => array_sum($counts)],
            array_fill_keys(array_keys(Store::$statusLabels), 0),
            $counts,
        );
    }

    /** @return array<string, int> */
    #[Computed]
    public function pipelineStats(): array
    {
        $counts = $this->statusCounts;

        return [
            'total' => $counts['all'],
            'niet_gecontacteerd' => $counts['niet_gecontacteerd'] ?? 0,
            'in_behandeling' => ($counts['gecontacteerd'] ?? 0) + ($counts['in_gesprek'] ?? 0),
            'partner' => $counts['partner'] ?? 0,
            'afgewezen' => $counts['afgewezen'] ?? 0,
        ];
    }

    #[Computed]
    public function selectedStore(): ?Store
    {
        if (! $this->selectedStoreId) {
            return null;
        }

        return Store::query()->find($this->selectedStoreId);
    }

    #[Computed]
    public function stores()
    {
        $query = Store::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('pipeline_status', $this->statusFilter))
            ->when($this->countryFilter, fn ($q) => $q->where('country', $this->countryFilter))
            ->when($this->cityFilter, fn ($q) => $q->where('city', $this->cityFilter));

        $this->applyContactFilter($query);
        $this->applyAssignedFilter($query);

        return $query
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(25);
    }

    private function applyContactFilter($query): void
    {
        match ($this->contactFilter) {
            'email' => $query->whereNotNull('email'),
            'phone' => $query->whereNotNull('phone'),
            'website' => $query->whereNotNull('website'),
            'complete' => $query->whereNotNull('email')->whereNotNull('phone')->whereNotNull('website'),
            default => null,
        };
    }

    private function applyAssignedFilter($query): void
    {
        match ($this->assignedFilter) {
            '_unassigned' => $query->whereNull('assigned_to'),
            '' => null,
            default => $query->where('assigned_to', $this->assignedFilter),
        };
    }
};
