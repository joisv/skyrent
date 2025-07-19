<div x-data="{
    createBooking() {
        {{-- $dispatch('booking-create') --}}
        $dispatch('open-modal', 'booking-create')
    },
}">
    <x-tables.table name="Booking">
        <x-slot name="secondBtn">
            <button
                class="flex items-center justify-center w-1/2 px-5 py-2 text-sm disabled:text-gray-700 transition-colors duration-200 disabled:bg-white border rounded-lg gap-x-2 sm:w-auto dark:hover:bg-gray-800 dark:bg-gray-900 hover:bg-gray-100 dark:text-gray-200 dark:border-gray-700 bg-red-500 text-white"
                wire:click="destroyAlert" @if (!$mySelected) disabled @endif>
                <span>Bulk delete</span>
            </button>
        </x-slot>
        <x-slot name="addBtn">
            <x-tables.addbtn type="button" x-data="" @click="createBooking()">
                Add Booking
            </x-tables.addbtn>
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

                        <option disabled selected>Sort by</option>
                        <option value="created_at">Terbaru</option>
                        <option value="updated_at">Updated</option>
                        <option value="start_booking_date">Tanggal Mulai</option>
                        <option value="status">Status</option>
                        <option value="price">Harga</option>
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
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                    wire:model.live="selectedAll">
            </x-tables.th>
            <x-tables.th>Nama</x-tables.th>
            <x-tables.th>iPhone</x-tables.th>
            <x-tables.th>Durasi Sewa</x-tables.th>
            <x-tables.th>Tanggal & Waktu Booking</x-tables.th>
            <x-tables.th>Tanggal & Waktu Mulai</x-tables.th>
            <x-tables.th>Tanggal & Waktu Selesai</x-tables.th>
            <x-tables.th>Status</x-tables.th>
            <x-tables.th>Aksi</x-tables.th>
        </x-slot>

        <x-slot name="tbody">
            @foreach ($bookings as $index => $booking)
                <tr>
                    <x-tables.td>
                        <input id="default-{{ $index }}" type="checkbox"
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                            wire:model.live="mySelected" value="{{ $booking->id }}">
                    </x-tables.td>

                    <x-tables.td>{{ $booking->customer_name }}</x-tables.td>
                    <x-tables.td>{{ $booking->iphone->name ?? '-' }}</x-tables.td>
                    <x-tables.td>{{ $booking->duration }} jam</x-tables.td>
                    <x-tables.td>
                        {{ $booking->requested_booking_date ? \Carbon\Carbon::parse($booking->requested_booking_date)->format('d M Y') . ' ' . $booking->requested_time : '-' }}
                    </x-tables.td>

                    <x-tables.td>
                        {{ $booking->start_booking_date ? \Carbon\Carbon::parse($booking->start_booking_date)->format('d M Y') . ' ' . $booking->start_time : '-' }}
                    </x-tables.td>

                    <x-tables.td>
                        {{ $booking->end_booking_date ? \Carbon\Carbon::parse($booking->end_booking_date)->format('d M Y') . ' ' . $booking->end_time : '-' }}
                    </x-tables.td>

                    <x-tables.td>
                        <x-dropdown align="top" width="48">
                            {{-- Tombol pemicu dropdown --}}
                            <x-slot name="trigger">
                                <button
                                    class="px-2 py-1 rounded text-xs font-semibold 
                    {{ $booking->status === 'pending'
                        ? 'bg-yellow-100 text-yellow-700'
                        : ($booking->status === 'confirmed'
                            ? 'bg-green-100 text-green-700'
                            : ($booking->status === 'cancelled'
                                ? 'bg-red-100 text-red-700'
                                : 'bg-gray-100 text-gray-700')) }}">{{ ucfirst($booking->status) }}
                                </button>
                            </x-slot>

                            {{-- Konten dropdown --}}
                            <x-slot name="content">
                                <div class="p-1">
                                    <button {{ $booking->status === 'pending' ? 'disabled' : '' }}
                                        class="disabled:bg-gray-300 disabled:cursor-not-allowed p-2 text-center w-full text-sm font-semibold hover:bg-yellow-200"
                                        wire:click="updateStatusBooking({{ $booking->id }}, 'pending')">pending</button>
                                </div>
                                <div class="p-1">
                                    <button {{ $booking->status === 'confirmed' ? 'disabled' : '' }}
                                        class="disabled:bg-gray-300 disabled:cursor-not-allowed p-2 text-center w-full text-sm font-semibold hover:bg-green-200"
                                        wire:click="updateStatusBooking({{ $booking->id }}, 'confirmed')">confirmed</button>
                                </div>
                                <div class="p-1">
                                    <button {{ $booking->status === 'returned' ? 'disabled' : '' }}
                                        class="disabled:bg-gray-300 disabled:cursor-not-allowed p-2 text-center w-full text-sm font-semibold hover:bg-purple-200"
                                        wire:click="updateStatusBooking({{ $booking->id }}, 'returned')">returned</button>
                                </div>
                                <div class="p-1">
                                    <button {{ $booking->status === 'cancelled' ? 'disabled' : '' }}
                                        class="disabled:bg-gray-300 disabled:cursor-not-allowed p-2 text-center w-full text-sm font-semibold hover:bg-red-300"
                                        wire:click="updateStatusBooking({{ $booking->id }}, 'cancelled')">cancelled</button>
                                </div>
                            </x-slot>
                        </x-dropdown>

                    </x-tables.td>

                    <x-tables.td>
                        {{-- <a href="{{ route('bookings.edit', $booking->id) }}">
                            <x-primary-button type="button">edit</x-primary-button>
                        </a> --}}
                        <x-danger-button type="button"
                            wire:click="destroyAlert({{ $booking->id }}, 'delete')">delete</x-danger-button>
                    </x-tables.td>
                </tr>
            @endforeach
        </x-slot>

    </x-tables.table>
    <div class="w-full mt-5">
        {{-- {{ $iphones->links() }} --}}
    </div>
    <x-modal name="booking-create" :show="$errors->isNotEmpty()">
        <livewire:booking.create />
    </x-modal>
</div>
