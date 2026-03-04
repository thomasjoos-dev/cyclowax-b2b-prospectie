<?php

use App\Enums\PipelineStatus;
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

    /** @var array<int, string> */
    public array $cityFilter = [];

    /** @var array<int, string> */
    public array $contactFilter = [];

    /** @var array<int, string> */
    public array $countryFilter = [];

    /** @var array<int, string> */
    public array $assignedFilter = [];

    /** @var array<int, int> */
    public array $brandFilter = [];

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
        $newStatus = PipelineStatus::from($status);

        $data = ['pipeline_status' => $newStatus];

        if ($store->pipeline_status === PipelineStatus::NietGecontacteerd && $newStatus !== PipelineStatus::NietGecontacteerd) {
            $data['last_contacted_at'] = now();
        }

        $store->update($data);

        $this->success("Status bijgewerkt naar '{$newStatus->label()}'");
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

    public function updatingCityFilter(&$value): void
    {
        if (! is_array($value)) {
            $value = [];
        }

        $this->resetPage();
    }

    public function updatingContactFilter(&$value): void
    {
        if (! is_array($value)) {
            $value = [];
        }

        $this->resetPage();
    }

    public function updatingCountryFilter(&$value): void
    {
        if (! is_array($value)) {
            $value = [];
        }

        $this->cityFilter = [];
        $this->resetPage();
    }

    public function updatingAssignedFilter(&$value): void
    {
        if (! is_array($value)) {
            $value = [];
        }

        $this->resetPage();
    }

    public function updatingBrandFilter(&$value): void
    {
        if (! is_array($value)) {
            $value = [];
        }

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
            ->when(count($this->countryFilter) > 0, fn ($q) => $q->whereIn('country', $this->countryFilter))
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->map(fn (string $city) => ['id' => $city, 'name' => $city])
            ->toArray();
    }

    /** @return array<int, array{id: int, name: string}> */
    #[Computed]
    public function brandOptions(): array
    {
        return \App\Models\Brand::query()
            ->whereHas('stores')
            ->orderBy('name')
            ->get()
            ->map(fn (\App\Models\Brand $brand) => ['id' => $brand->id, 'name' => $brand->name])
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
            ->when(count($this->countryFilter) > 0, fn ($q) => $q->whereIn('country', $this->countryFilter))
            ->when(count($this->cityFilter) > 0, fn ($q) => $q->whereIn('city', $this->cityFilter));

        $this->applyContactFilter($query);
        $this->applyAssignedFilter($query);
        $this->applyBrandFilter($query);

        $counts = $query
            ->selectRaw('pipeline_status, count(*) as total')
            ->groupBy('pipeline_status')
            ->pluck('total', 'pipeline_status')
            ->toArray();

        return array_merge(
            ['all' => array_sum($counts)],
            array_fill_keys(array_column(PipelineStatus::cases(), 'value'), 0),
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
            ->when(count($this->countryFilter) > 0, fn ($q) => $q->whereIn('country', $this->countryFilter))
            ->when(count($this->cityFilter) > 0, fn ($q) => $q->whereIn('city', $this->cityFilter));

        $this->applyContactFilter($query);
        $this->applyAssignedFilter($query);
        $this->applyBrandFilter($query);

        return $query
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(25);
    }

    private function applyContactFilter($query): void
    {
        foreach ($this->contactFilter as $filter) {
            match ($filter) {
                'email' => $query->whereNotNull('email'),
                'phone' => $query->whereNotNull('phone'),
                'website' => $query->whereNotNull('website'),
                'complete' => $query->whereNotNull('email')->whereNotNull('phone')->whereNotNull('website'),
                default => null,
            };
        }
    }

    private function applyAssignedFilter($query): void
    {
        if (count($this->assignedFilter) === 0) {
            return;
        }

        $hasUnassigned = in_array('_unassigned', $this->assignedFilter);
        $members = array_filter($this->assignedFilter, fn ($v) => $v !== '_unassigned');

        $query->where(function ($q) use ($hasUnassigned, $members) {
            if ($hasUnassigned) {
                $q->whereNull('assigned_to');
            }

            if (count($members) > 0) {
                $q->orWhereIn('assigned_to', $members);
            }
        });
    }

    private function applyBrandFilter($query): void
    {
        foreach ($this->brandFilter as $brandId) {
            $query->whereHas('brands', fn ($q) => $q->where('brands.id', $brandId));
        }
    }

    public function exportCsv()
    {
        return response()->streamDownload(function () {
            $query = Store::query()
                ->with('brands')
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->when($this->statusFilter, fn ($q) => $q->where('pipeline_status', $this->statusFilter))
                ->when(count($this->countryFilter) > 0, fn ($q) => $q->whereIn('country', $this->countryFilter))
                ->when(count($this->cityFilter) > 0, fn ($q) => $q->whereIn('city', $this->cityFilter));

            $this->applyContactFilter($query);
            $this->applyAssignedFilter($query);
            $this->applyBrandFilter($query);

            $query->orderBy('name');

            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Naam', 'Adres', 'Postcode', 'Stad', 'Land', 'Telefoon', 'Email', 'Website', 'Status', 'Merken']);

            $query->chunk(500, function ($stores) use ($handle) {
                foreach ($stores as $store) {
                    fputcsv($handle, [
                        $store->name,
                        $store->address,
                        $store->postal_code,
                        $store->city,
                        $store->country,
                        $store->phone,
                        $store->email,
                        $store->website,
                        $store->pipeline_status->label(),
                        $store->brands->pluck('name')->implode(', '),
                    ]);
                }
            });

            fclose($handle);
        }, 'stores-export-'.now()->format('Y-m-d').'.csv');
    }
};
