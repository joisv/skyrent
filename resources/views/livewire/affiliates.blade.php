<div x-data="">
    <x-mary-drawer wire:model="showCreateDrawer" title="Tambah Affiliate" subtitle="Masukkan informasi affiliate baru"
        separator with-close-button close-on-escape right class="w-11/12 lg:w-2/5">
        <x-mary-form wire:submit="store">

            <div class="space-y-5">

                {{-- Informasi Dasar --}}
                <div>
                    <h3 class="font-semibold text-base mb-3">
                        Informasi Dasar
                    </h3>

                    <div class="space-y-4">

                        <x-mary-input label="Kode Affiliate" wire:model.live="code" placeholder="BWI" />

                        <x-mary-input label="Nama Affiliate" wire:model.live="name" placeholder="Affiliate Banyuwangi"
                            x-on:blur="$dispatch('setslug')" />

                        <x-mary-input label="Slug" wire:model.live="slug" placeholder="affiliate-banyuwangi" />

                        <x-mary-textarea label="Deskripsi" wire:model.live="description" rows="3" />

                    </div>
                </div>

                <hr class="my-6 border-gray-200 dark:border-gray-700">

                {{-- Kontak --}}
                <div>

                    <h3 class="font-semibold text-base mb-3">
                        Kontak
                    </h3>

                    <div class="space-y-4">

                        <x-mary-input label="Email" type="email" wire:model.live="email" />

                        <x-mary-input label="Nomor Telepon" wire:model.live="phone" />

                    </div>

                </div>

                <hr class="my-6 border-gray-200 dark:border-gray-700">

                {{-- Lokasi --}}
                <div>

                    <h3 class="font-semibold text-base mb-3">
                        Lokasi
                    </h3>

                    <div class="space-y-4">

                        <x-mary-textarea label="Alamat" wire:model.live="address" rows="2" />

                        <div class="grid grid-cols-2 gap-4">

                            <x-mary-input label="Kota" wire:model.live="city" />

                            <x-mary-input label="Provinsi" wire:model.live="province" />

                        </div>

                        <x-mary-input label="Kode Pos" wire:model.live="postal_code" />

                    </div>

                </div>

                <hr class="my-6 border-gray-200 dark:border-gray-700">

                {{-- Google Maps --}}
                <div>

                    <h3 class="font-semibold text-base mb-3">
                        Lokasi Maps
                    </h3>

                    <div class="grid grid-cols-2 gap-4">

                        <x-mary-input label="Latitude" wire:model.live="latitude" />

                        <x-mary-input label="Longitude" wire:model.live="longitude" />

                    </div>

                </div>

                <hr class="my-6 border-gray-200 dark:border-gray-700">

                {{-- Upload --}}
                {{-- <div>

                    <h3 class="font-semibold text-base mb-3">
                        Branding
                    </h3>

                    <div class="space-y-4">

                        <x-mary-file label="Logo" wire:model="logo" accept="image/*" />

                        <x-mary-file label="Banner" wire:model="banner" accept="image/*" />

                    </div>

                </div> 

                 <hr class="my-6 border-gray-200 dark:border-gray-700"> --}}

                {{-- Status --}}
                <x-mary-toggle label="Affiliate Aktif" wire:model.live="is_active" />

            </div>

            <x-slot:actions>

                <x-mary-button label="Batal" @click="$wire.showCreateDrawer = false" />

                <x-mary-button label="Simpan" icon="o-check" class="bg-orange-500 text-white hover:bg-orange-600"
                    type="submit" spinner="store" />

            </x-slot:actions>

        </x-mary-form>

    </x-mary-drawer>
    <x-tables.table name="Affiliate" count="{{ $affiliates->count() }}">
        <x-slot name="secondBtn">
            {{-- <button
                class="flex items-center justify-center w-1/2 px-5 py-2 text-sm disabled:text-gray-700 transition-colors duration-200 disabled:bg-white border rounded-lg gap-x-2 sm:w-auto dark:hover:bg-gray-800 dark:bg-gray-900 hover:bg-gray-100 dark:text-gray-200 dark:border-gray-700 bg-red-500 text-white"
                wire:click="destroyAlert" @if (!$mySelected) disabled @endif>
                <span>Bulk delete</span>
            </button> --}}
        </x-slot>
        <x-slot name="addBtn">
            <x-mary-button label="Tambah Affiliate" icon="o-plus" class="bg-orange-500 text-white hover:bg-orange-600"
                type="button" wire:click="$set('showCreateDrawer', true)" />
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
                <input id="selectedAll" type="checkbox"
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded" wire:model.live="selectedAll">
            </x-tables.th>

            <x-tables.th>Kode</x-tables.th>
            <x-tables.th>Nama Affiliate</x-tables.th>
            <x-tables.th>Kota</x-tables.th>
            <x-tables.th>Kontak</x-tables.th>
            <x-tables.th>Status</x-tables.th>
            <x-tables.th>Dibuat</x-tables.th>
            <x-tables.th>Aksi</x-tables.th>
        </x-slot>

        <x-slot name="tbody">
            @forelse($affiliates as $index => $affiliate)
                <tr class="hover:bg-gray-50">

                    <x-tables.td>
                        <input id="affiliate-{{ $index }}" type="checkbox"
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded"
                            wire:model.live="mySelected" value="{{ $affiliate->id }}">
                    </x-tables.td>

                    {{-- Kode --}}
                    <x-tables.td>
                        <span class="font-semibold">
                            {{ $affiliate->code }}
                        </span>
                    </x-tables.td>

                    {{-- Nama --}}
                    <x-tables.td>
                        <div>
                            <div class="font-medium">
                                {{ $affiliate->name }}
                            </div>

                            <div class="text-xs text-gray-500">
                                {{ $affiliate->slug }}
                            </div>
                        </div>
                    </x-tables.td>

                    {{-- Kota --}}
                    <x-tables.td>
                        {{ $affiliate->city ?? '-' }}
                    </x-tables.td>

                    {{-- Kontak --}}
                    <x-tables.td>
                        <div>
                            <div>{{ $affiliate->phone ?? '-' }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $affiliate->email ?? '-' }}
                            </div>
                        </div>
                    </x-tables.td>

                    {{-- Status --}}
                    <x-tables.td>
                        @if ($affiliate->is_active)
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">
                                Active
                            </span>
                        @else
                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700">
                                Inactive
                            </span>
                        @endif
                    </x-tables.td>

                    {{-- Dibuat --}}
                    <x-tables.td>
                        {{ $affiliate->created_at->format('d M Y') }}
                    </x-tables.td>

                    {{-- Aksi --}}
                    <x-tables.td>
                        <div class="flex gap-2">

                            <x-primary-button type="button" wire:click="edit({{ $affiliate->id }})">
                                Edit
                            </x-primary-button>

                            <x-danger-button type="button"
                                wire:click="destroyAlert({{ $affiliate->id }}, 'delete')">
                                Delete
                            </x-danger-button>
                            <x-primary-button type="button"
                                wire:click="DetailAffiliateuser({{ $affiliate->id }})">Detail</x-primary-button>
                        </div>
                    </x-tables.td>

                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center py-8 text-gray-500">
                        Belum ada data affiliate.
                    </td>
                </tr>
            @endforelse
        </x-slot>
    </x-tables.table>
    <div class="w-full mt-5">
        {{ $affiliates->links() }}
    </div>
    <x-modal name="detail-affiliate" :show="$errors->isNotEmpty()" maxWidth="4xl">
        <div class="p-4" @close-modal.window="show = false">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-800">
                    Detail Affiliate
                </h2>

                <p class="text-sm text-gray-500">
                    Detail informasi affiliate.
                </p>
            </div>
            <x-mary-tabs wire:model="selectedTab">
                <x-mary-tab name="users-tab" label="Detail Affiliate" icon="o-users">
                    <div class="space-y-6">

                        {{-- Header --}}
                        <div class="bg-white rounded-xl shadow p-6">
                            <div class="flex items-center gap-6">

                                @if ($detailAffiliate?->logo)
                                    <img src="{{ Storage::url($detailAffiliate?->logo) }}"
                                        class="w-24 h-24 rounded-xl object-cover">
                                @else
                                    <div class="w-24 h-24 rounded-xl bg-gray-200 flex items-center justify-center">
                                        No Logo
                                    </div>
                                @endif

                                <div>
                                    <h1 class="text-2xl font-bold">
                                        {{ $detailAffiliate?->name }}
                                    </h1>

                                    <p class="text-gray-500">
                                        Kode : {{ $detailAffiliate?->code }}
                                    </p>

                                    <p class="text-gray-500">
                                        {{ $detailAffiliate?->email }}
                                    </p>

                                    <span
                                        class="inline-flex mt-2 px-3 py-1 rounded-full text-sm
                    {{ $detailAffiliate?->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $detailAffiliate?->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>

                            </div>
                        </div>

                        {{-- Statistik --}}
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                            <button wire:click="assignAffiliateToUser({{ $detailAffiliate?->id }})" type="button"
                                class="bg-white rounded-xl shadow p-5 hover:bg-orange-200 border-2 border-transparent hover:border-orange-400 transition duration-200">
                                <p class="text-gray-500">Pengguna Affiliate</p>
                                <h2 class="text-lg font-bold">
                                    {{ $detailAffiliate?->users->first()?->name ?? 'Belum ada pengguna' }}
                                </h2>
                            </button>

                            <button type="button" wire:click="listIphones({{ $detailAffiliate?->id }})"
                                class="bg-white rounded-xl shadow p-5 hover:bg-orange-200 border-2 border-transparent hover:border-orange-400 transition duration-200">
                                <p class="text-gray-500">iPhone</p>
                                <h2 class="text-xl font-bold">
                                    {{ $iphones?->count() }}
                                </h2>
                            </button>

                            <button type="button" wire:click="listBookings({{ $detailAffiliate?->id }})"
                                class="bg-white rounded-xl shadow p-5 hover:bg-orange-200 border-2 border-transparent hover:border-orange-400 transition duration-200">
                                <p class="text-gray-500">Booking</p>
                                <h2 class="text-xl font-bold">
                                    {{ $detailAffiliate?->bookings->count() }}
                                </h2>
                            </button>
                            {{-- Transfer iPhone --}}
                            {{-- <div class="bg-white rounded-xl shadow p-5">
                                <p class="text-gray-500">Transfer iPhone</p>
                                <h2 class="text-xl font-bold">
                                    {{ $detailAffiliate?->transferIn->count() }}
                                </h2>
                            </div> --}}
                            <button @click="$dispatch('open-modal', 'revenue')" type="button"
                                class="bg-white rounded-xl shadow p-5">
                                <p class="text-gray-500">Pendapatan</p>
                                <h2 class="text-xl font-bold">
                                    Rp
                                    {{ number_format($revenues?->sum('amount'), 0, ',', '.') }}
                                </h2>
                            </button>


                        </div>

                        {{-- Informasi --}}
                        <div class="bg-white rounded-xl shadow" x-data="{ detailAffiliate: true }">

                            <div class="border-b px-6 py-4 flex justify-between"
                                @click="detailAffiliate = ! detailAffiliate">
                                <div>
                                    <h2 class="font-semibold">
                                        Informasi Affiliate
                                    </h2>
                                </div>
                                <div>
                                    <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none"
                                        class="ease-in duration-200" :class="detailAffiliate ? 'rotate-180' : ''"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round">
                                        </g>
                                        <g id="SVGRepo_iconCarrier">
                                            <path
                                                d="M15 11L12.2121 13.7879C12.095 13.905 11.905 13.905 11.7879 13.7879L9 11M7 21H17C19.2091 21 21 19.2091 21 17V7C21 4.79086 19.2091 3 17 3H7C4.79086 3 3 4.79086 3 7V17C3 19.2091 4.79086 21 7 21Z"
                                                stroke="rgb(31, 41, 55)" stroke-width="2" stroke-linecap="round">
                                            </path>
                                        </g>
                                    </svg>
                                </div>
                            </div>

                            <div class="grid md:grid-cols-2 gap-6 p-6" x-show="detailAffiliate" x-collapse x-cloak>

                                <div>
                                    <label class="text-gray-500 text-sm">Nama</label>
                                    <p>{{ $detailAffiliate?->name }}</p>
                                </div>

                                <div>
                                    <label class="text-gray-500 text-sm">Kode</label>
                                    <p>{{ $detailAffiliate?->code }}</p>
                                </div>

                                <div>
                                    <label class="text-gray-500 text-sm">Email</label>
                                    <p>{{ $detailAffiliate?->email }}</p>
                                </div>

                                <div>
                                    <label class="text-gray-500 text-sm">Telepon</label>
                                    <p>{{ $detailAffiliate?->phone ?: '-' }}</p>
                                </div>

                                <div>
                                    <label class="text-gray-500 text-sm">Kota</label>
                                    <p>{{ $detailAffiliate?->city ?: '-' }}</p>
                                </div>

                                <div>
                                    <label class="text-gray-500 text-sm">Provinsi</label>
                                    <p>{{ $detailAffiliate?->province ?: '-' }}</p>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="text-gray-500 text-sm">Alamat</label>
                                    <p>{{ $detailAffiliate?->address ?: '-' }}</p>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="text-gray-500 text-sm">Deskripsi</label>
                                    <p>
                                        {{ $detailAffiliate?->description ?: '-' }}
                                    </p>
                                </div>

                            </div>

                        </div>

                    </div>
                </x-mary-tab>
                <x-mary-tab name="Analitycs" label="Analitycs" icon="o-document-chart-bar">
                    <div>Comings Soon</div>
                </x-mary-tab>
            </x-mary-tabs>



        </div>
    </x-modal>
    <x-modal name="revenue" :show="$errors->isNotEmpty()" maxWidth="4xl">
        <div class="space-y-6 p-4">

            {{-- Ringkasan --}}
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">

                <div class="bg-white border rounded-xl p-6">
                    <p class="text-sm text-gray-500">Pendapatan Hari ini</p>

                    <h2 class="mt-2 text-xl font-bold text-green-600">
                        Rp
                        {{ number_format($revenues?->sum('amount'), 0, ',', '.') }}
                    </h2>
                </div>
                <div class="bg-white border rounded-xl p-6">
                    <p class="text-sm text-gray-500">Total Pendapatan</p>

                    <h2 class="mt-2 text-xl font-bold text-green-600">
                        Rp
                        {{ number_format($detailAffiliate?->bookings->sum(fn($booking) => $booking->revenue?->amount ?? 0), 0, ',', '.') }}
                    </h2>
                </div>

                <div class="bg-white border rounded-xl p-6">
                    <p class="text-sm text-gray-500">Booking Hari ini</p>

                    <h2 class="mt-2 text-xl font-bold text-orange-500">
                        {{ $bookingsToday?->count() }}
                    </h2>
                </div>

            </div>

            {{-- Riwayat Pendapatan --}}
            <div class="bg-white border rounded-xl overflow-hidden">

                <div class="px-6 py-5 border-b">
                    <h2 class="text-lg font-semibold">
                        Riwayat Pendapatan Hari ini
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Pendapatan yang diterima dari setiap booking.
                    </p>
                </div>
                @if (!empty($revenues))
                    @forelse ($revenues as $revenue)
                        @if ($revenue->booking)
                            <div
                                class="flex items-center justify-between px-6 py-5 border-b last:border-b-0 hover:bg-gray-50">

                                <div>

                                    <h3 class="font-semibold text-gray-800">
                                        {{ $revenue->booking->iphone->name }}
                                    </h3>

                                    <p class="text-sm text-gray-500 mt-1">
                                        {{ $revenue->booking->iphone->serial_number }}
                                    </p>
                                    <p class="text-sm text-gray-400">
                                        {{ \Carbon\Carbon::parse($revenue->created)->translatedFormat('d F Y • H:i') }}
                                    </p>

                                </div>

                                <div class="text-right">

                                    <p class="text-xl font-bold text-green-600">
                                        Rp {{ number_format($revenue->amount, 0, ',', '.') }}
                                    </p>

                                    <span
                                        class="inline-flex px-2 py-1 mt-2 rounded-full bg-orange-100 text-orange-600 text-xs font-medium">
                                        {{ ucfirst($revenue->type) }}
                                    </span>

                                </div>

                            </div>
                        @endif

                    @empty

                        <div class="py-16 text-center text-gray-500">
                            Belum ada pendapatan.
                        </div>
                    @endforelse

                @endif

            </div>

        </div>
    </x-modal>
    <x-modal name="assign-user-to-affiliate" :show="$errors->isNotEmpty()" maxWidth="xl">
        <div class="p-5" @close-modal-detail-affiliate.window="show = false">
            <div class="mb-5 ">
                <h2 class="text-lg font-semibold text-gray-800">
                    Assign Affiliate to User
                </h2>

                <p class="text-sm text-gray-500">
                    Pilih salah satu user untuk affiliate ini.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                @foreach ($users as $user)
                    <button wire:click="assignUser('{{ $user?->id }}')"
                        class="group w-full text-left rounded-xl border transition duration-200 p-5
    
                    {{ $selectedUser?->id == $user?->id
                        ? 'border-orange-600 bg-orange-50'
                        : 'border-gray-200 hover:border-orange-300 hover:bg-gray-50' }}">

                        <div class="flex justify-between items-center">

                            <div>

                                <h3 class="font-semibold text-gray-800">
                                    {{ $user?->name }}
                                </h3>

                            </div>

                            @if ($selectedUser == $user?->id)
                                <div
                                    class="h-8 w-8 rounded-full bg-orange-600 text-white flex items-center justify-center">

                                    ✓

                                </div>
                            @endif

                        </div>

                    </button>
                @endforeach

            </div>
        </div>
    </x-modal>
    <x-modal name="list-iphones" :show="$errors->isNotEmpty()" maxWidth="4xl">
        <div class="p-5" @close-modal-detail-affiliate.window="show = false">
            <div class="mb-5 ">
                <h2 class="text-lg font-semibold text-gray-800">
                    List iPhone Affiliate
                </h2>

                <p class="text-sm text-gray-500">
                    List iPhone affiliate.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if (!empty($iphones))
                    @foreach ($iphones as $iphone)
                        <div
                            class="group w-full text-left rounded-xl border transition duration-200 p-5
                        border-gray-200 hover:border-orange-300 hover:bg-gray-50">

                            <div class="flex justify-between items-center">

                                <div>

                                    <h3 class="font-semibold text-gray-800">
                                        {{ $iphone?->name }}
                                    </h3>
                                    <p>{{ $iphone?->serial_number }}</p>
                                </div>

                            </div>

                        </div>
                    @endforeach
                @else
                    <p class="">
                        Tidak ada iPhone yang ditemukan.
                    </p>
                @endif

            </div>
        </div>
    </x-modal>
</div>
