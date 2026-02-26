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
    public function stores()
    {
        return Store::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('pipeline_status', $this->statusFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(25);
    }
};
