<div x-data="">
    @if ($errors->any())
        <div x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-[-10px]" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-[-10px]"
            class="fixed top-6 sm:right-20 sm:max-w-lg w-full z-50 p-4 rounded-lg shadow-lg border bg-white border-red-300 dark:bg-gray-800 dark:border-red-500/40">
            <div class="flex items-start gap-3">
                <!-- Icon Error -->
                <div class="flex-shrink-0">
                    <svg class="w-6 h-6 text-red-500 dark:text-red-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M12 5a7 7 0 100 14a7 7 0 000-14z" />
                    </svg>
                </div>
                <!-- Text -->
                <div class="flex-1">
                    <p class="font-medium text-gray-900 dark:text-gray-100">
                        Error
                    </p>
                    <ul class="mt-1 text-sm list-disc list-inside text-gray-700 dark:text-gray-300">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <!-- Close button -->
                <button @click="show = false"
                    class="text-gray-400 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    @endif
    <x-mary-drawer wire:model="showDrawer2" class="w-11/12 lg:w-1/3" title="Transfer iPhone"
        subtitle="Pindahkan kepemilikan iPhone" separator with-close-button close-on-escape right>
        <div class="space-y-5">
            <x-mary-form wire:submit.prevent="transfer" class="space-y-5">
                <x-mary-card shadow class="border">
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('storage/' . $iphone?->gallery->image) }}"
                            class="w-20 h-20 rounded-lg object-cover">

                        <div>
                            <p class="font-semibold text-lg">
                                {{ $iphone->name ?? 'iPhone tidak ditemukan' }}
                            </p>

                            <p class="text-sm text-gray-500">
                                IMEI : {{ $iphone->serial_number ?? 'N/A' }}
                            </p>

                            <x-mary-badge value="{{ $iphone->status ?? 'N/A' }}" class="badge-success mt-2" />
                        </div>
                    </div>
                </x-mary-card>

                {{-- Dari --}}
                <x-mary-input label="Dari" value="Super Admin" readonly />

                {{-- Tujuan --}}
                <x-mary-select label="Transfer Ke" wire:model="receiver_id" :options="$users" option-label="name"
                    option-value="id" placeholder="Pilih Penerima" />

                {{-- Catatan --}}
                <x-mary-textarea label="Catatan" wire:model="notes" rows="3"
                    placeholder="Contoh: Dipindahkan karena kebutuhan operasional" />

                {{-- Ringkasan --}}
                <x-mary-card class="bg-base-200 border">
                    <div class="space-y-2 text-sm">

                        <div class="flex justify-between">
                            <span>Perangkat</span>
                            <span class="font-semibold">
                                {{ $iphone->name ?? 'iPhone tidak ditemukan' }}
                            </span>
                        </div>

                        <div class="flex justify-between">
                            <span>Pemilik Saat Ini</span>
                            <span class="font-semibold">
                                Super Admin
                            </span>
                        </div>

                        {{-- @if ($selectedReceiver)
                            <div class="flex justify-between">
                                <span>Pemilik Baru</span>
                                <span class="font-semibold text-primary">
                                    {{ $selectedReceiver->name }}
                                </span>
                            </div>
                        @endif --}}

                    </div>
                </x-mary-card>

                {{-- Tombol --}}
                <div class="flex justify-end gap-3 pt-3">

                    <x-mary-button label="Batal" wire:click="$set('showDrawer2', false)" />

                    <x-mary-button label="Transfer" icon="o-paper-airplane" class="btn-primary" type="submit"
                        spinner="transfer" />

                </div>
            </x-mary-form>
            {{-- Informasi iPhone --}}

        </div>
    </x-mary-drawer>
    <x-tables.table name="Daftar iPhone" count="{{ $iphones->count() }} iPhone">
        <x-slot name="secondBtn">
            @role('super-admin|admin|staff')
                <button
                    class="flex items-center justify-center w-1/2 px-5 py-2 text-sm disabled:text-gray-700 transition-colors duration-200 disabled:bg-white border rounded-lg gap-x-2 sm:w-auto dark:hover:bg-gray-800 dark:bg-gray-900 hover:bg-gray-100 dark:text-gray-200 dark:border-gray-700 bg-red-500 text-white"
                    wire:click="destroyAlert" @if (!$mySelected) disabled @endif>
                    <span>Bulk delete</span>
                </button>
            @endrole
        </x-slot>
        <x-slot name="addBtn">
            @role('super-admin|admin|staff')
                <x-tables.addbtn type="button" x-data=""
                    @click="window.location.href = '{{ route('iphones.create') }}'">
                    Add iPhone
                </x-tables.addbtn>
            @endrole
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
            @role('super-admin')
                <x-tables.th>Affiliate</x-tables.th>
            @endrole
            <x-tables.th>Dibuat</x-tables.th>
            <x-tables.th>Dirubah</x-tables.th>
            <x-tables.th>Diupdate</x-tables.th>
            @role('super-admin|admin|staff')
                <x-tables.th>Action</x-tables.th>
            @endrole
        </x-slot>
        @php $no = 1; @endphp
        <x-slot name="tbody">
            @foreach ($iphones as $index => $iphone)
                {{-- @dump( $iphone->affiliate->users?->name ?? '-' ) --}}
                <tr>
                    <x-tables.td>
                        <input id="default-{{ $index }}" type="checkbox"
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                            wire:model.live="mySelected" value="{{ $iphone->id }}">
                    </x-tables.td>
                    <x-tables.td>
                        {{ $iphone->name ?? '-' }}
                    </x-tables.td>
                    <x-tables.td>
                        {{ $iphone->serial_number ?? '-' }}
                    </x-tables.td>
                    @role('super-admin')
                        <x-tables.td>
                            {{ $iphone->affiliate?->users->first()?->name ?? '-' }}
                        </x-tables.td>
                    @endrole
                    <x-tables.td>
                        {{ $iphone->user->name ?? '-' }}
                    </x-tables.td>
                    {{-- <x-tables.td>
                        {{ $iphone->status }}
                    </x-tables.td> --}}
                    <x-tables.td>{{ Carbon\Carbon::createFromFormat('Y-m-d', $iphone->created)->format('F j, Y') }}</x-tables.td>
                    <x-tables.td>{{ $iphone->updated_at->format('d M Y') }}</x-tables.td>
                    {{-- <x-tables.td>{{ $iphone->category->name }}</x-tables.td> --}}
                    @role('super-admin|admin|staff')
                        <x-tables.td>
                            <a href="{{ route('iphones.edit', $iphone->id) }}">
                                <x-primary-button type="button">edit</x-primary-button>
                            </a>
                            <x-danger-button type="button"
                                wire:click="destroyAlert({{ $iphone->id }}, 'delete')">delete</x-danger-button>
                            <x-primary-button type="button"
                                wire:click="openModalDrawer({{ $iphone->id }})">transfer</x-primary-button>
                        </x-tables.td>
                    @endrole

                </tr>
            @endforeach
        </x-slot>
    </x-tables.table>
    <div class="w-full mt-5">
        {{ $iphones->links() }}
    </div>
</div>
