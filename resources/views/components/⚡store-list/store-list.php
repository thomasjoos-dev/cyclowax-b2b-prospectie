<?php

use App\Models\Store;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function updateStatus(int $storeId, string $status): void
    {
        Store::query()->findOrFail($storeId)->update(['pipeline_status' => $status]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function statusCounts(): array
    {
        $counts = Store::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
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

    #[Computed]
    public function stores()
    {
        return Store::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('pipeline_status', $this->statusFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(25);
    }
};
