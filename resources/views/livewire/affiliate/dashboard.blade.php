<div x-data="">
    <x-tables.table name="Daftar Transfer iPhone" count="{{ $iphoneTransfers?->count() }} iPhone">
        <x-slot name="secondBtn">

        </x-slot>
        <x-slot name="addBtn">

        </x-slot>
        <x-slot name="sort">
            <div class="flex items-center space-x-2 w-1/2 ">
                <div class="w-fit">
                    <select id="sort_series"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 px-5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                        wire:model.live="paginate">
                        <option value="10">10</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="150">150</option>
                    </select>
                </div>
                <div class="w-fit">
                    <select id="sort"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 px-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                        wire:model.live="sortField">
                        <option selected>Filter by</option>
                        <option value="created_at">All</option>
                        <option value="updated_at">Updated</option>
                    </select>
                </div>
            </div>
        </x-slot>
        <x-slot name="search">
            <x-search wire:model.live.debounce.500ms="search" />
        </x-slot>
        <x-slot name="thead">
            <x-tables.th>
                <input id="selectedAll"
                    type="checkbox"class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                    wire:model.live="selectedAll">
                {{-- <input type="hidden" wire:model.live="firstId" value="{{ $serieses[0]->id }}"> --}}
            </x-tables.th>
            <x-tables.th>Nama</x-tables.th>
            <x-tables.th>Serial Number</x-tables.th>
            <x-tables.th>Status Transfer</x-tables.th>
            <x-tables.th>Dibuat</x-tables.th>
            <x-tables.th>Dirubah</x-tables.th>
            <x-tables.th>Diupdate</x-tables.th>
            <x-tables.th>Action</x-tables.th>
        </x-slot>
        @php $no = 1; @endphp
        <x-slot name="tbody">
            @if (!empty($iphoneTransfers))
                @foreach ($iphoneTransfers as $index => $iphone)
                    <tr>
                        <x-tables.td>
                            <input id="default-{{ $index }}" type="checkbox"
                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                wire:model.live="mySelected" value="{{ $iphone->id }}">
                        </x-tables.td>
                        <x-tables.td>
                            {{ $iphone['iphone']->name }}
                        </x-tables.td>
                        <x-tables.td>
                            {{ $iphone['iphone']->serial_number ?? '-' }}
                        </x-tables.td>
                        <x-tables.td>
                            @php
                                $status = $iphone['iphone']->transfers->first()?->status;
                            @endphp

                            @if ($status)
                                @switch($status)
                                    @case('pending')
                                        <span
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                            Pending
                                        </span>
                                    @break

                                    @case('in_transit')
                                        <span
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                            In Transit
                                        </span>
                                    @break

                                    @case('transferred')
                                        <span
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                            Transferred
                                        </span>
                                    @break

                                    @default
                                        <span
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </span>
                                @endswitch
                            @else
                                <span class="text-gray-500">-</span>
                            @endif
                        </x-tables.td>
                        <x-tables.td>
                            {{ $iphone['iphone']->user->name }}
                        </x-tables.td>
                        <x-tables.td>{{ Carbon\Carbon::createFromFormat('Y-m-d', $iphone['iphone']->created)->format('F j, Y') }}</x-tables.td>
                        <x-tables.td>{{ $iphone['iphone']->updated_at->format('d M Y') }}</x-tables.td>
                        <x-tables.td>
                            <x-primary-button type="button"
                                wire:click="acceptTransferIphone({{ $iphone['iphone']->transfers->first()?->id }}, 'delete')">terima</x-primary-button>
                        </x-tables.td>

                    </tr>
                @endforeach
            @else
                <tr >
                    <td colspan="8" class="text-center py-8">Tidak ada data.</td>
                </tr>
            @endif

        </x-slot>
    </x-tables.table>
    {{-- <div class="w-full mt-5">
        {{ $iphoneTransfers->links() }}
    </div> --}}
</div>
