@php
    use App\Models\Store;

    $pillClasses = [
        'niet_gecontacteerd' => 'bg-gray-100 text-gray-700',
        'gecontacteerd'      => 'bg-blue-100 text-blue-700',
        'in_gesprek'         => 'bg-yellow-100 text-yellow-700',
        'partner'            => 'bg-green-100 text-green-700',
        'afgewezen'          => 'bg-red-100 text-red-700',
    ];

    $tabs = array_merge(['all' => 'Alle'], Store::$statusLabels);
@endphp

<div>
    {{-- Zoekbalk --}}
    <div class="mb-4">
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
            </svg>
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Zoek op naam..."
                class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm shadow-sm focus:border-black focus:ring-1 focus:ring-black"
            >
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex flex-wrap gap-x-1">
            @foreach ($tabs as $value => $label)
                @php
                    $isActive = ($value === 'all' && $statusFilter === '') || $statusFilter === $value;
                    $count = $this->statusCounts[$value] ?? 0;
                @endphp
                <button
                    wire:click="$set('statusFilter', '{{ $value === 'all' ? '' : $value }}')"
                    class="flex items-center gap-1.5 whitespace-nowrap rounded-t px-4 py-2.5 text-sm font-medium transition-colors {{ $isActive
                        ? 'bg-black text-white'
                        : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700' }}"
                >
                    {{ $label }}
                    <span class="rounded-full px-1.5 py-0.5 text-xs {{ $isActive ? 'bg-gray-700 text-gray-100' : 'bg-gray-100 text-gray-600' }}">
                        {{ $count }}
                    </span>
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Paginatie boven --}}
    @if ($this->stores->hasPages())
        <div class="border border-b-0 border-t-0 border-gray-200 bg-white px-4 py-2">
            {{ $this->stores->links() }}
        </div>
    @endif

    {{-- Table --}}
    <div class="overflow-x-auto rounded-b-lg border border-t-0 border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Naam</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Stad</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Website</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Pipeline status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Datum</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->stores as $store)
                    <tr wire:key="{{ $store->id }}" class="hover:bg-gray-50">
                        <td class="px-4 py-4 font-medium text-gray-900">
                            {{ $store->name }}
                        </td>
                        <td class="px-4 py-4 text-gray-500">
                            {{ $store->city }}
                        </td>
                        <td class="px-4 py-4">
                            @if ($store->website)
                                <a
                                    href="{{ $store->website }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="truncate text-gray-900 hover:underline"
                                >
                                    {{ parse_url($store->website, PHP_URL_HOST) ?: $store->website }}
                                </a>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            <div class="relative inline-flex items-center">
                                <select
                                    wire:change="updateStatus({{ $store->id }}, $event.target.value)"
                                    class="cursor-pointer appearance-none rounded-full py-0.5 pl-3 pr-6 text-xs font-medium focus:outline-none focus:ring-1 focus:ring-black {{ $pillClasses[$store->pipeline_status] ?? 'bg-gray-100 text-gray-700' }}"
                                >
                                    @foreach (Store::$statusLabels as $value => $label)
                                        <option value="{{ $value }}" @selected($store->pipeline_status === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <svg class="pointer-events-none absolute right-1.5 h-3 w-3 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            @if ($store->is_existing_customer)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                    Bestaande klant
                                </span>
                            @else
                                <span class="text-gray-400">Prospect</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-gray-500">
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

    {{-- Paginatie onder --}}
    @if ($this->stores->hasPages())
        <div class="mt-4">
            {{ $this->stores->links() }}
        </div>
    @endif
</div>
