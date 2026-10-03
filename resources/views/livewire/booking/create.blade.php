<div class="p-5 h-fit" @close-modal="show = false">
    <form wire:submit.prevent="save">
        <div class="text-xl font-semibold">
            <h1>Make new booking</h1>
        </div>
        <div class="mt-4 space-y-3">
            {{-- select --}}
            <div class="text-white">
                <div class="relative text-black" x-data="{
                    state: false,
                    filter: '',
                    value: 'iPhone 12',
                
                    toggle: function() {
                        this.state = !this.state;
                        this.filter = '';
                    },
                    close: function() {
                        this.state = false;
                    },
                
                    selectIphone: function(id, value) {
                        $wire.selectedIphone = value; // update the Livewire property
                        $wire.selectedIphoneId = id; // update the Livewire property
                        $wire.getDuration();
                        this.close();
                    }
                
                }" @click.away="close()">
                    <input type="text" class="hidden">
                    <span class="inline-block w-full rounded-md shadow-sm"
                        @click="toggle(); $nextTick(() => $refs.filterinput.focus());">
                        <button type="button"
                            class="relative z-0 w-full py-2 pl-3 pr-10 text-left transition duration-150 ease-in-out bg-white border border-gray-300 rounded-md cursor-default focus:outline-none focus:shadow-outline-blue focus:border-blue-300 sm:text-sm sm:leading-5">
                            <span class="block truncate" x-text="$wire.selectedIphone ?? 'Pilih tipe iPhone'"></span>

                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" viewBox="0 0 20 20" fill="none"
                                    stroke="currentColor">
                                    <path d="M7 7l3-3 3 3m0 6l-3 3-3-3" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                            </span>
                        </button>
                    </span>
                    {{-- ketika click toggle --}}
                    <div x-show="state" class="absolute z-10 w-full mt-1 bg-white rounded-md shadow-lg p-2">
                        <input type="text" class="w-full rounded-md py-1 px-2 mb-1 border border-gray-400"
                            wire:model.live.debounce.500ms="search" x-ref="filterinput">
                        <ul
                            class="py-1 overflow-auto text-base leading-6 rounded-md shadow-xs max-h-60 focus:outline-none sm:text-sm sm:leading-5">
                            @foreach ($iphonesQuery as $iphone)
                                @php
                                    $isRented = in_array(strtolower($iphone->status ?? ''), ['rented', 'disewa'])
                                        || $iphone->bookings()->whereIn('status', ['rented', 'disewa'])->exists();
                                    $isMaintenance = in_array(strtolower($iphone->status ?? ''), ['maintenance', 'perawatan']);
                                @endphp
                                <li
                                    class="relative py-2 px-3 mb-1 select-none rounded-md transition-colors {{ $isRented || $isMaintenance ? 'bg-gray-50 opacity-80 cursor-not-allowed' : 'hover:bg-blue-50 cursor-pointer' }}"
                                    @click="
                                        @if($isRented)
                                            $wire.alertRentedUnit('{{ addslashes($iphone->name) }}', '{{ addslashes($iphone->asset_code ?? '') }}');
                                        @elseif($isMaintenance)
                                            $wire.alertMaintenanceUnit('{{ addslashes($iphone->name) }}');
                                        @else
                                            selectIphone('{{ $iphone->id }}', '{{ addslashes($iphone->name) }}');
                                        @endif
                                    ">
                                    <div class="flex items-center justify-between">
                                        <div class="flex flex-col pr-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-medium text-sm {{ $isRented ? 'text-gray-500 line-through' : 'text-gray-900' }}">
                                                    {{ $iphone->name }}
                                                </span>
                                                @if($iphone->storage)
                                                    <span class="text-xs text-gray-500 font-normal">({{ $iphone->storage }})</span>
                                                @endif
                                            </div>
                                            <span class="text-xs text-gray-500">
                                                SN: {{ $iphone->serial_number ?? '-' }} • Aset: {{ $iphone->asset_code ?? '-' }}
                                            </span>
                                        </div>
                                        <div class="flex-shrink-0">
                                            @if($isRented)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300">
                                                    🔒 Sedang Disewa
                                                </span>
                                            @elseif($isMaintenance)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 border border-red-300">
                                                    🛠️ Perawatan
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                    ✓ Tersedia
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            @error('selectedIphoneId')
                <span class="error">Pilih iphone yang tersedia</span>
            @enderror
            <div>
                <label for="customer_name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Nama
                    customer</label>
                <input type="text" id="customer_name"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    placeholder="John" wire:model="customer_name" />
                @error('customer_name')
                    <span class="error">Nama tida bole kosong 😒</span>
                @enderror
            </div>
            <div>
                <label for="customer_phone" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                    Nomor Customer
                </label>

                <div x-data="{
                    countryCode: @entangle('countryCode'),
                    customerPhone: @entangle('customer_phone'),
                    countries: [
                        { code: '+62', name: 'Indonesia', flag: 'ID' },
                        { code: '+60', name: 'Malaysia', flag: 'MY' },
                        { code: '+65', name: 'Singapore', flag: 'SG' },
                        { code: '+66', name: 'Thailand', flag: 'TH' },
                        { code: '+63', name: 'Philippines', flag: 'PH' },
                        { code: '+95', name: 'Myanmar', flag: 'MM' },
                        { code: '+855', name: 'Cambodia', flag: 'KH' },
                        { code: '+856', name: 'Laos', flag: 'LA' },
                        { code: '+84', name: 'Vietnam', flag: 'VN' },
                        { code: '+673', name: 'Brunei', flag: 'BN' }
                    ]
                }" class="flex gap-2">

                    <!-- Dropdown Kode Negara -->
                    <select x-model="countryCode"
                        class="w-[40%] bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2.5">
                        <template x-for="country in countries" :key="country.code">
                            <option :value="country.code" x-text="country.flag + ' ' + country.code"></option>
                        </template>
                    </select>

                    <!-- Input Nomor -->
                    <input type="tel" id="customer_phone" x-model="customerPhone"
                        @input="
        let raw = $event.target.value.replace(/[^0-9]/g, '');
        customerPhone = raw.match(/.{1,4}/g)?.join('-') || '';
    "
                        class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg 
           focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 
           dark:text-white p-2.5"
                        placeholder="8123-4567-8901" />

                </div>

                @error('customer_phone')
                    <span class="error">Nomor tida bole kosong 😒</span>
                @enderror
            </div>
            <div>
                <label for="customer_email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Email
                    customer optional</label>
                <input type="email" id="customer_email"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    placeholder="jhondoe@gmail.com" wire:model="customer_email" />
                @error('customer_email')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>
            {{-- Date picker --}}
            <div>
                <label for="requested_booking_date"
                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Tanggal booking</label>
                {{-- <livewire:booking.set-date wire:model="requested_booking_date" /> --}}
            </div>
            @error('requested_booking_date')
                <span class="error">Pilih tanggal sewa</span>
            @enderror
            {{-- Time picker --}}
            <div wire:ignore>
                <label for="timepicker" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Waktu
                    mulai</label>
                {{-- Initialize flatpickr for time selection --}}
                <div class="border-gray-300" x-data="{
                    timepickerinstance: null,
                
                    init() {
                        let timepick = document.querySelector('#timepicker')
                        this.timepickerinstance = flatpickr(timepick, {
                            enableTime: true,
                            noCalendar: true,
                            dateFormat: 'H:i',
                            time_24hr: true,
                            defaultDate: @js($requested_time ? \Carbon\Carbon::parse($requested_time)->format('H:i') : null),
                            onChange: (selectedDates, dateStr, instance) => {
                                $wire.requested_time = dateStr; // Update Livewire property
                            }
                        })
                    },
                }">
                    <input id="timepicker" wire:ignore type="text" placeholder="YYYY-MM-DD"
                        class="w-full border-gray-300 rounded-sm" />
                </div>
                @error('requested_time')
                    <span class="error">Pilih jam sewa</span>
                @enderror
            </div>
            {{-- Duration --}}
            <div>
                <label for="duration"
                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Durasi</label>
                <div class="flex space-x-2 justify-start" x-data="{
                    activeTab: @entangle('selectedDuration').live,
                    price: @entangle('selectedPrice').live,
                
                    setActiveTab(tab, priceValue) {
                        this.activeTab = tab;
                        this.price = priceValue;
                    }
                }">
                    @foreach ($durations as $item)
                        <div @click="setActiveTab({{ $item['hours'] }}, {{ $item['price'] }})"
                            :class="{ 'bg-black text-white': activeTab === {{ $item['hours'] }} }"
                            class="p-2 font-medium text-sm w-20 text-center border-2 border-slate-300 cursor-pointer rounded-md">
                            {{ $item['hours'] }} jam
                        </div>
                    @endforeach
                </div>

                @error('selectedDuration')
                    <span class="error">Pilih durasi sewa😊</span>
                @enderror
            </div>
            {{-- Payment --}}
            <div>
                <div class="flex justify-between">

                    <h1 class="text-xl font-semibold mb-4">Metode Pembayaran</h1>
                    <div class="space-y-2">
                        @if (!empty($payments))
                            <div class="w-48">
                                <x-mary-select wire:model.live="selectedPaymentId" :options="$payments"
                                    placeholder="Metode pembayaran" placeholder-value="1" option-value="id"
                                    option-label="name" />
                            </div>
                        @endif
                    </div>
                </div>
                <div class="mt-1 text-sm text-gray-600 dark:text-gray-300 italic leading-relaxed">
                    <span>{{ $selectedPayment->description }}</span>
                </div>

            </div>
            <div x-data="{ price: @entangle('selectedPrice').live }">
                <div class="text-gray-700 text-xl font-semibold">
                    <span x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(price)"></span>
                </div>
            </div>
            <hr>
            <x-mary-checkbox label="Kirim pesan WhatsApp ke customer" wire:model="sendWhatsapp"
                hint="Centang jika ingin mengirim pesan otomatis" />


            <button type="submit" class="w-full p-3 text-center bg-black text-white font-semibold text-xl">Buat
                booking</button>
        </div>
    </form>
</div>
