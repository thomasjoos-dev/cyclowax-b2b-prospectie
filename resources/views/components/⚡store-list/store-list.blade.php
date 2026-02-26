@php
    use App\Models\Store;

    $selectClasses = [
        'niet_gecontacteerd' => 'bg-gray-100 text-gray-700 border-gray-300',
        'gecontacteerd'      => 'bg-blue-100 text-blue-700 border-blue-300',
        'in_gesprek'         => 'bg-yellow-100 text-yellow-700 border-yellow-300',
        'partner'            => 'bg-green-100 text-green-700 border-green-300',
        'afgewezen'          => 'bg-red-100 text-red-700 border-red-300',
    ];
@endphp

<div>
    {{-- Filters --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <input
            wire:model.live.debounce.300ms="search"
            type="search"
            placeholder="Zoek op naam..."
            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:max-w-xs"
        >

        <select
            wire:model.live="statusFilter"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
        >
            <option value="">Alle statussen</option>
            @foreach (Store::$statusLabels as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Naam</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Stad</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Pipeline status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Website</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Toegevoegd</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->stores as $store)
                    <tr wire:key="{{ $store->id }}" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $store->name }}
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ $store->city ?: '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <select
                                wire:change="updateStatus({{ $store->id }}, $event.target.value)"
                                class="rounded border px-2 py-1 text-xs font-medium focus:ring-1 focus:ring-indigo-400 {{ $selectClasses[$store->pipeline_status] ?? 'bg-gray-100 text-gray-700 border-gray-300' }}"
                            >
                                @foreach (Store::$statusLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($store->pipeline_status === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            @if ($store->is_existing_customer)
                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                                    Bestaande klant
                                </span>
                            @else
                                <span class="text-gray-400">Prospect</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($store->website)
                                <a
                                    href="{{ $store->website }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="truncate text-indigo-600 hover:text-indigo-800 hover:underline"
                                >
                                    {{ parse_url($store->website, PHP_URL_HOST) ?: $store->website }}
                                </a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ $store->created_at->format('d/m/Y') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                            Geen winkels gevonden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($this->stores->hasPages())
        <div class="mt-4">
            {{ $this->stores->links() }}
        </div>
    @endif
</div>
