<div class="max-w-7xl mx-auto p-6"
    x-on:display-duration-options.window="$dispatch('open-modal', 'duration-options-modal')">
    {{-- STEP INDICATOR --}}
    <div class="flex items-center justify-between mb-8">
        {{-- LEFT --}}
        <div>
            {{-- STEP INDICATOR --}}
            <div class="flex items-center gap-4 text-sm">

                @php
                    $steps = [
                        1 => 'iPhone',
                        2 => 'Detail Customer',
                        3 => 'Review',
                    ];
                @endphp

                @foreach ($steps as $number => $label)
                    <div class="flex items-center gap-1">
                        <input type="radio" name="step" value="{{ $number }}" @checked($step === $number)
                            disabled
                            class="appearance-none w-4 h-4 rounded-full border hover:scale-125
                        {{ $step >= $number ? 'bg-black border-black' : 'border-gray-300' }}" />

                        <label class="text-lg font-normal {{ $step >= $number ? 'text-black' : 'text-gray-400' }}">
                            {{ $label }}
                        </label>
                    </div>

                    @if (!$loop->last)
                        <span class="w-6 h-px bg-gray-300"></span>
                    @endif
                @endforeach

            </div>
        </div>

        {{-- RIGHT --}}
        <div class="flex items-center gap-4">

            {{-- SEARCH ICON --}}
            <button class="p-2 rounded hover:bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>

            {{-- NEXT BUTTON --}}
            {{-- <button wire:click="next" class="flex items-center gap-2 px-4 py-2 bg-black text-white text-sm rounded">
                Lanjut
                <span>→</span>
            </button> --}}

        </div>
    </div>


    {{-- CONTENT --}}
    @if ($step === 1)
        <div class="space-y-3 ">
            <div>
                {{-- Date picker --}}
                <div>
                    <label for="requested_booking_date"
                        class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Tanggal
                        booking</label>
                    <livewire:booking.set-date wire:model="requested_booking_date" />
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
                                    {{-- console.log(selectedDates) --}}
                                    {{-- $wire.setTime(dateStr); // Call Livewire method to set time --}}
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
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">

                @foreach ($iphones as $iphone)
                    <button wire:click="selectIphone({{ $iphone->id }})" @disabled(!$iphone->is_available)
                        class="
                    relative rounded-xl border p-4 text-left transition
                    {{ $selectedIphoneId === $iphone->id ? 'border-black ring-2 ring-black' : 'border-gray-200' }}
                    {{ !$iphone->is_available ? 'opacity-40 cursor-not-allowed' : 'hover:border-black' }}
                ">
                        {{-- IMAGE --}}
                        <div class="flex justify-center mb-4">
                            <img src="{{ $iphone->image }}" alt="{{ $iphone->name }}" class="h-40 object-contain">
                        </div>

                        {{-- NAME --}}
                        <p class="text-sm font-medium text-gray-900">
                            {{ $iphone->name }}
                        </p>

                        {{-- CODE / SERIAL --}}
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $iphone->code ?? '—' }}
                        </p>

                        {{-- STATUS --}}
                        @if (!$iphone->is_available)
                            <span class="absolute top-3 right-3 text-xs bg-red-100 text-red-600 px-2 py-1 rounded">
                                Sedang disewa
                            </span>
                        @endif
                    </button>
                @endforeach

            </div>
        </div>
        {{-- <livewire:rent.steps.iphone wire:model="requested_booking_date" wire:model="requested_time" /> --}}
    @elseif ($step === 2)
        <div class="space-y-2">
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
            <div>
                <label for="customer_email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Alamat
                    Customer</label>
                <input type="email" id="customer_email"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    placeholder="Jl. Diponegoro rt 001/rw 004" wire:model="address" />
                @error('address')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label for="customer_email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Tipe
                    Jaminan</label>
                <select wire:model.live="jaminan_type"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    <option value="KTP">KTP</option>
                    <option value="KK">KK</option>
                    <option value="Kartu Pelajar">Kartu Pelajar</option>
                    <option value="SIM">SIM</option>
                    <option value="Kartu Identitas Mahasiswa">Kartu Identitas Mahasiswa</option>
                </select>

                @error('jaminan_type')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror

            </div>
            {{-- Payment --}}
            <div>
                <div>
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
        </div>
    @elseif ($step === 3)
        <div class="bg-white rounded-xl shadow p-6 space-y-6 w-full">

            {{-- Header --}}
            <h2 class="text-lg font-semibold text-center">
                Keempat, review lagi ya jangan sampai ada yang salah
            </h2>

            {{-- STEP 1 : Device --}}
            <div class="border-t pt-4">
                <div class="flex justify-between items-start">
                    <div class="space-y-3 text-sm text-gray-700">
                        <div class="flex justify-between gap-6">
                            <span class="text-gray-500">Pilih yang mau disewa</span>
                            <span class="font-medium text-end">
                                {{ $selectedIphone?->name ?? 'halo dunia' }}
                            </span>
                        </div>

                        <div class="flex justify-between gap-6">
                            <span class="text-gray-500">Mau diambil di cabang mana?</span>
                            <span class="font-medium">
                                {{ $address ?? '-' }}
                            </span>
                        </div>
                    </div>

                    <button wire:click="goToStep(1)" class="text-sm text-blue-600 hover:underline">
                        Edit
                    </button>
                </div>
            </div>

            {{-- STEP 2 : Waktu --}}
            <div class="border-t pt-4">
                <div class="flex justify-between items-start">
                    <div class="space-y-3 text-sm text-gray-700">
                        <div class="flex justify-between gap-6">
                            <span class="text-gray-500">
                                Waktu Pengambilan (Tanggal & Jam)
                            </span>
                            <span class="font-medium">
                                {{ $requested_booking_date }} {{ $requested_time }}
                            </span>
                        </div>

                        <div class="flex justify-between gap-6">
                            <span class="text-gray-500">
                                Estimasi Waktu Pengembalian
                            </span>
                            <span class="font-medium">
                                {{ $end_booking_date }} {{ $end_time }}
                            </span>
                        </div>

                        <div class="flex justify-between gap-6">
                            <span class="text-gray-500">Durasi</span>
                            <span class="font-medium">
                                {{ $selectedDuration }} hari
                            </span>
                        </div>
                    </div>

                    <button wire:click="goToStep(2)" class="text-sm text-blue-600 hover:underline">
                        Edit
                    </button>
                </div>
            </div>

            {{-- STEP 3 : Data Customer --}}
            <div class="border-t pt-4">
                <div class="flex justify-between items-start">
                    <div class="space-y-3 text-sm text-gray-700">
                        <div class="flex justify-between gap-6">
                            <span class="text-gray-500">Nama Lengkap</span>
                            <span class="font-medium">
                                {{ $customer_name ?? '-' }}
                            </span>
                        </div>

                        <div class="flex justify-between gap-6">
                            <span class="text-gray-500">Nomor WhatsApp</span>
                            <span class="font-medium">
                                {{ $countryCode }}{{ $customer_phone }}
                            </span>
                        </div>

                        <div class="flex justify-between gap-6">
                            <span class="text-gray-500">Email</span>
                            <span class="font-medium">
                                {{ $customer_email ?? '-' }}
                            </span>
                        </div>

                        <div class="flex justify-between gap-6">
                            <span class="text-gray-500">Jenis Jaminan</span>
                            <span class="font-medium">
                                {{ $jaminan_type }}
                            </span>
                        </div>
                    </div>

                    <button wire:click="goToStep(3)" class="text-sm text-blue-600 hover:underline">
                        Edit
                    </button>
                </div>
            </div>

            {{-- TOTAL --}}
            <div class="border-t pt-4">
                <div class="flex justify-between text-base font-semibold">
                    <span>Total Harga</span>
                    <span>
                        Rp {{ number_format($price, 0, ',', '.') }}
                    </span>
                </div>
            </div>

        </div>

    @endif

    {{-- NAV --}}
    <div class="flex justify-between mt-8">
        @if ($step > 1)
            <button wire:click="back" class="px-4 py-2 border">
                Kembali
            </button>
        @endif

        @if ($step < 4)
            <button wire:click="next" class="px-6 py-2 bg-black text-white">
                Lanjut →
            </button>
        @else
            <button wire:click="submit" class="px-6 py-2 bg-green-600 text-white">
                Konfirmasi
            </button>
        @endif
    </div>
    <x-modal name="duration-options-modal" :show-close="true" max-width="md">
        {{-- Duration --}}
        <div class="m-3">
            <label for="duration" class="block mb-3 text-xl font-medium text-gray-900 dark:text-white">Pilih
                Durasi</label>
            <div class="grid md:grid-cols-3 grid-cols-2 gap-1" x-data="{
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
                        class="p-3 font-semibold text-base text-center border-2 border-slate-300 cursor-pointer w-full hover:shadow-xl hover:scale-105 transition">
                        {{ $item['hours'] }} jam
                    </div>
                @endforeach
            </div>

            @error('selectedDuration')
                <span class="error">Pilih durasi sewa😊</span>
            @enderror
        </div>
    </x-modal>
</div>
