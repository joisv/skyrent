<div x-data="{
    open: false,
    showDatepicker: false,
    selectedDate: @entangle('selectedDate').live,
    selectedDateFormatted: @entangle('selectedDateFormatted').live,
    month: null,
    year: null,
    daysInMonth: [],
    blankdays: [],
    monthNames: [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ],

    selectedHour: @entangle('selectedHour').live,
    selectedMinute: @entangle('selectedMinute').live,
    hours: Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0')),
    minutes: ['00', '15', '30', '45'],


    init() {
        const today = new Date();
        this.month = today.getMonth();
        this.year = today.getFullYear();
        this.selectedDate = today;
        this.selectedDateFormatted = this.formatDate(this.selectedDate);
        this.calculateDays();
    },

    isToday(date) {
        const today = new Date();
        const d = new Date(this.year, this.month, date);
        return today.toDateString() === d.toDateString();
    },

    formatDate(date) {
        const day = date.getDate();
        const month = this.monthNames[date.getMonth()];
        const year = date.getFullYear();
        const time = `${this.selectedHour}:${this.selectedMinute}`;
        return `${day} ${month} ${year}, ${time}`;
    },


    pickDate(date) {
        this.selectedDate = new Date(this.year, this.month, date);
        this.selectedDateFormatted = this.formatDate(this.selectedDate);
    },

    calculateDays() {
        const daysInMonth = new Date(this.year, this.month + 1, 0).getDate();
        const firstDayOfMonth = new Date(this.year, this.month, 1).getDay(); // 0 = Minggu

        this.blankdays = Array(firstDayOfMonth).fill(null);
        this.daysInMonth = Array.from({ length: daysInMonth }, (_, i) => i + 1);
    },

    prevMonth() {
        this.month--;
        if (this.month < 0) {
            this.month = 11;
            this.year--;
        }
        this.calculateDays();
    },

    nextMonth() {
        this.month++;
        if (this.month > 11) {
            this.month = 0;
            this.year++;
        }
        this.calculateDays();
    },

    isSelectedDate(date) {
        const d = new Date(this.year, this.month, date);
        return this.selectedDate.toDateString() === d.toDateString();
    },

    pickDate(date) {
        const picked = new Date(this.year, this.month, date);
        const today = new Date();
        today.setHours(0, 0, 0, 0); // buang jam agar akurat membandingkan tanggal

        if (picked < today) return; // blokir jika tanggal sebelum hari ini

        this.selectedDate = picked;
        this.selectedDateFormatted = this.formatDate(this.selectedDate);
    },

    isPastDate(date) {
        const picked = new Date(this.year, this.month, date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return picked < today;
    },

    createBooking() {
        {{-- $dispatch('booking-create') --}}
        $dispatch('open-modal', 'user-booking-create')
    },

}" x-init="$watch('selectedHour', () => selectedDateFormatted = formatDate(selectedDate));
$watch('selectedMinute', () => selectedDateFormatted = formatDate(selectedDate));">
    <form class="flex space-x-3 min-h-[50vh] w-full mt-20 ">
        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="w-[70%] flex space-x-3 sticky top-10 h-fit">
            <div class="w-[45%] h-[50vh] relative">
                <img src="{{ asset('storage/' . $iphone->gallery->image) }}" alt="" srcset=""
                    class="w-full h-full object-contain absolute">
            </div>
            <div class="w-1/2">
                <h1 class="text-3xl font-semibold">{{ $iphone->name }}</h1>
                <span
                    class="proseprose-base lg:prose-lg prose-code:text-rose-500 prose-a:text-blue-600 mt-5">{!! $iphone->description !!}</span>
            </div>
        </div>
        <div class="w-[30%] h-fit border-2 border-slate-900 p-5 sticky top-10 right-20">
            <div class="space-y-4">
                {{-- BOOKING --}}
                <div class="space-y-3">
                    <h1 class="text-2xl font-semibold ">Tanggal Booking</h1>
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false"
                        @close.stop="open = false">
                        <div @click="open = ! open">
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center p-3 pointer-events-none">
                                    <svg width="30px" height="30px" viewBox="0 0 24.00 24.00" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M20 10V7C20 5.89543 19.1046 5 18 5H6C4.89543 5 4 5.89543 4 7V10M20 10V19C20 20.1046 19.1046 21 18 21H6C4.89543 21 4 20.1046 4 19V10M20 10H4M8 3V7M16 3V7"
                                            stroke="#000000" stroke-width="1.344" stroke-linecap="round"></path>
                                        <rect x="6" y="12" width="3" height="3" rx="0.5"
                                            fill="#000000" />
                                        <rect x="10.5" y="12" width="3" height="3" rx="0.5"
                                            fill="#000000" />
                                        <rect x="15" y="12" width="3" height="3" rx="0.5"
                                            fill="#000000" />
                                    </svg>
                                </div>
                                <input
                                    class="block w-full pl-12 p-4 ps-10 text-gray-900 border border-gray-300 bg-gray-50 focus:ring-slate-900 focus:border-slate-900 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-slate-900 dark:focus:border-slate-900"
                                    placeholder="Pilih tanggal" x-model="selectedDateFormatted" readonly
                                    @click="$refs.dropdownButton.click()" />
                            </div>
                        </div>

                        <div x-show="open" x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                            class="absolute z-50 mt-2 
                    style="display: none;">
                            <div class="rounded-md ring-1 ring-black ring-opacity-5">

                                <!-- Kalender -->
                                <div
                                    class="p-4 bg-white dark:bg-gray-800 text-lg z-10 w-[25vw] border-2 border-slate-900 shadow-xl">
                                    <div class="border-b-2 border-gray-300 pb-4">
                                        <div class="flex items-center  text-lg">
                                            <!-- Hour Picker -->
                                            <div>
                                                <input type="number" x-model="selectedHour" min="0"
                                                    max="23"
                                                    class="w-16 text-center bg-transparent border border-transparent focus:border-gray-400 focus:outline-none px-2 py-1 rounded text-2xl font-bold"
                                                    placeholder="HH">
                                            </div>

                                            <div class="font-bold">:</div>

                                            <!-- Input Menit -->
                                            <div>
                                                <input type="number" x-model="selectedMinute" min="0"
                                                    max="59" step="1"
                                                    class="w-16 text-center bg-transparent border border-transparent focus:border-gray-400 focus:outline-none px-2 py-1 rounded text-2xl font-bold"
                                                    placeholder="MM">
                                            </div>
                                        </div>
                                        <div class="text-lg font-medium text-gray-700 dark:text-gray-200"
                                            x-text="monthNames[month] + ' ' + year"></div>
                                    </div>
                                    <!-- Header navigasi bulan -->
                                    <div class="flex mb-2">
                                        <button type="button" @click="prevMonth()"
                                            class="px-2 py-1 text-gray-600 hover:bg-gray-200 rounded">&lt;</button>

                                        <button type="button" @click="nextMonth()"
                                            class="px-2 py-1 text-gray-600 hover:bg-gray-200 rounded">&gt;</button>
                                    </div>

                                    <!-- Hari -->
                                    <div class="grid grid-cols-7 text-gray-500 mb-1">
                                        <template x-for="day in ['Min','Sen','Sel','Rab','Kam','Jum','Sab']">
                                            <div x-text="day" class="text-center"></div>
                                        </template>
                                    </div>

                                    <!-- Tanggal -->
                                    <div class="grid grid-cols-7 gap-2 font-semibold text-sm">
                                        <!-- Sisipkan hari kosong -->
                                        <template x-for="blank in blankdays">
                                            <div></div>
                                        </template>

                                        <!-- Tanggal -->
                                        <template x-for="(date, index) in daysInMonth" :key="index">
                                            <div @click="!isPastDate(date) && pickDate(date); $refs.dropdownButton?.click()"
                                                x-text="date"
                                                class="text-center cursor-pointer p-4 flex justify-center transition-colors duration-200 ease-in-out border-2 border-transparent"
                                                :class="{
                                                    'bg-slate-900 text-white': isSelectedDate(date),
                                                    'text-gray-400 cursor-not-allowed opacity-50': isPastDate(date),
                                                    'hover:border-slate-900': !isPastDate(date)
                                                }">
                                            </div>
                                        </template>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="space-y-3">
                    <h1 class="text-2xl font-semibold ">Durasi Sewa</h1>
                    <div class="flex space-x-2 w-full" x-data="{
                        activeTab: @entangle('selectedDuration').live,
                        price: @entangle('selectedPrice').live,
                    
                        setActiveTab(tab, priceValue) {
                            console.log(tab, priceValue);
                            this.activeTab = tab;
                            this.price = priceValue;
                        }
                    }">
                        @foreach ($iphone->durations as $item)
                            <div @click="setActiveTab({{ $item['hours'] }}, {{ $item->pivot->price }})"
                                :class="{ 'bg-black text-white': activeTab === {{ $item['hours'] }} }"
                                class="p-2 cursor-pointer text-black w-full font-semibold text-center border-2 border-slate-900">
                                {{ $item['hours'] }} jam
                            </div>
                        @endforeach

                    </div>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold ">Status</h1>
                    <div class="relative">
                        <div class="{{ $is_available === true ? 'bg-green-200' : 'bg-red-200' }} p-2 w-fit">
                            @if ($is_available === true)
                                <h1 class="text-green-600 font-semibold">tersedia</h1>
                                <div class="text-sm font-semibold text-slate-500 absolute -bottom-5 -left-[1px]">
                                    <p>tersedia untuk hari yang dipilih</p>
                                </div>
                            @else
                                <div>
                                    <h1 class="text-red-600 font-semibold">tidak tersedia</h1>
                                    <div class="text-sm font-semibold text-slate-500 absolute -bottom-5 -left-[1px]">
                                        <p>tidak tersedia untuk hari yang dipilih</p>
                                    </div>

                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
            <div class="mt-14 space-y-2" x-data="{ price: @entangle('selectedPrice').live }">
                <span class="text-2xl font-bold" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(price)"></span>
                <button type="button" wire:click="bookingNow"
                    class="flex justify-between items-center space-x-4 bg-white text-black text-xl font-semibold group overflow-hidden cursor-pointer border-2 border-slate-900 p-3 hover:bg-slate-900 hover:text-white transition duration-700 ease-in-out w-full">
                    <h1 class="text-xl font-bold">
                        Sewa Sekarang
                    </h1>
                    <div class="w-fit h-full group-hover:translate-x-24 transition duration-200 ease-in-out">
                        <x-icons.arrow1 />
                    </div>
                </button>
            </div>
        </div>
    </form>
    <div class="w-[65%] h-[100vh]">

    </div>
    <x-modal name="user-booking-create" :show="$errors->isNotEmpty()" rounded="rounded-none" border="border-2 border-slate-900">
        <div class="p-4 border">
            <div class="space-y-4">
                <div>
                    <h1 class="text-xl font-medium ">Nama</h1>
                    <input type="text" wire:model.live.debounce.250ms="customer_name"
                        class="w-full p-2 border-2 border-slate-900 " placeholder="e.g. John Doe">
                </div>
                <div>
                    <h1 class="text-xl font-medium ">Nomor telephone</h1>
                    <div x-data="{
                        countryCode: @entangle('countryCode'),
                        customerPhone: @entangle('customer_phone').lives,
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
                        <select x-model="countryCode" class="w-[40%] p-2 border-2 border-slate-900">
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
                            class="w-full p-2 border-2 border-slate-900" placeholder="8123-4567-8901" />

                    </div>
                </div>
                <div>
                    <h1 class="text-xl font-medium ">Email</h1>
                    <input type="email" wire:model.live.debounce.250ms="customer_email"
                        class="w-full p-2 border-2 border-slate-900 " placeholder="youreemail@example.site">
                </div>
            </div>

            {{-- Invoice --}}
            <div class="mt-7 space-y-5">
                <div class="flex justify-between items-start w-full">
                    {{-- <div class="">
                        <div class="font-medium">
                            <h1 class="text-2xl font-semibold mb-2">Rincian sewa</h1>
                            <h2>{{ $customer_name }}</h2>
                        <h2>{{ $customer_phone }}</h2>
                        <h2>{{ $customer_email }}</h2>
                            <h2>Jois Vanka</h2>
                            <h2>+6283-1321-4535-4231</h2>
                            <h2>joisvanka@gmail.com</h2>
                        </div>
                    </div> --}}
                    {{-- <div class="w-full flex flex-col items-end font-medium">
                        <h2>
                            {{ $selectedDateFormatted }}
                        </h2>
                        <h3 class="font-semibold">Invoice no. #432893</h3>
                    </div> --}}
                </div>
                <div>
                    <h1 class="text-2xl font-semibold mb-4">Deskripsi</h1>
                    <div class="border-y-2 border-gray-400 py-6 my-py-6 space-y-1">

                        <div class="flex justify-between items-center font-medium">
                            <p>Tipe iPhone</p>
                            <p>iPhone 16 pro Max</p>
                        </div>
                        <div class="flex justify-between items-center font-medium">
                            <p>Durasi</p>
                            <p>24 jam</p>
                        </div>
                        <div class="flex justify-between items-center font-medium">
                            <p>Tanggal dan waktu booking</p>
                            <p>{{ $selectedDateFormatted }}</p>
                        </div>
                        <div class="flex justify-between items-center font-medium" x-data="{ price: @entangle('selectedPrice').live }">
                            <p>Harga</p>
                            <span class=" font-bold"
                                x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(price)"></span>
                        </div>
                    </div>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold mb-4">Metode Pembayaran</h1>
                    <div class="border-t-2 border-gray-400 py-6 my-py-6 space-y-2">
                        @if (!empty($payments))
                        <div class="w-48">
                            <x-mary-select wire:model.live="selectedPaymentId" :options="$payments"
                                placeholder="Metode pembayaran" placeholder-value="1" option-value="id"
                                option-label="name" />
                        </div>
                            <img src="{{ asset('storage/' . $selectedPayment->icon) }}" alt=""
                                class="w-48 h-w-48 object-cover rounded-md">
                            {{-- @dump($selectedPayment) --}}
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </x-modal>
</div>
