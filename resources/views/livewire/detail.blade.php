<div x-data="{
    open: false,
    showDatepicker: false,
    selectedDateFormatted: @entangle('selectedDateFormatted').live,
    month: null,
    year: null,
    daysInMonth: [],
    blankdays: [],
    monthNames: [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ],

    selectedDate: @entangle('selectedDate').live,
    selectedHour: @entangle('selectedHour').live,
    selectedMinute: @entangle('selectedMinute').live,
    hours: Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0')),
    minutes: ['00', '15', '30', '45'],

    price: @entangle('selectedPrice').live,

    init() {
        if (this.selectedDate) {
            this.selectedDate = new Date(this.selectedDate);
        } else {
            const today = new Date();
            this.selectedDate = today;
        }

        this.month = this.selectedDate.getMonth();
        this.year = this.selectedDate.getFullYear();
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
$watch('selectedMinute', () => selectedDateFormatted = formatDate(selectedDate));" class="max-w-screen-2xl mx-auto">
    <div class="md:flex xl:space-x-3 min-h-[70vh] w-full xl:mt-20 ">
        <div class="w-full xl:w-[70%] lg:flex md:space-x-3 xl:sticky top-10 h-fit ">
            <div class="lg:w-[45%] h-[40vh] relative">
                <img src="" data-src="{{ asset('storage/' . $iphone->gallery->image) }}" alt=""
                    srcset="" class="w-full lazy h-full object-contain absolute">
            </div>
            <div class="lg:w-1/2 lg:my-0 p-2 mt-6">
                <div class="flex justify-end space-x-1 items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"
                        class="w-5 h-5 text-yellow-400">
                        <path
                            d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                    </svg>
                    <span class="text-sm font-semibold">( {{ $avgRating }} )</span>
                </div>
                <div class="flex items-center justify-between">
                    <h1 class="text-3xl font-semibold">{{ $iphone->name }}</h1>
                    <div>
                        <div class="flex space-x-1 md:hidden">
                            <h1 class="text-base font-medium">Rp</h1>
                            <h1 class="text-xl font-bold " x-text="new Intl.NumberFormat('id-ID').format(price)">
                            </h1>
                        </div>
                    </div>

                </div>
                <div
                    class="prose prose-base lg:prose-lg prose-invert text-black prose-li:text-black prose-a:text-blue-600 max-w-none md:flex flex-col hidden">
                    {!! $iphone->description !!}</div>
            </div>
        </div>
        <div
            class="md:full xl:w-[30%] h-fit md:border-2 border-y-gray-300 md:border-slate-900 p-3 md:p-5 xl:sticky top-10 right-20 ">
            <div class="space-y-4">
                {{-- BOOKING --}}
                <div class="flex flex-col-reverse">
                    <div class="space-y-3 mt-3">
                        <h1 class="text-lg md:text-2xl font-semibold ">Tanggal</h1>
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
                                        @click="window.dispatchEvent(new CustomEvent('open-bottom-sheet', { detail: { id: 'sheetTanggalB' } }))" />
                                </div>
                            </div>

                            <div x-show="open" x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95" class="absolute z-50 mt-2 hidden sm:flex"
                                style="display: none;">
                                <div class="rounded-md ring-1 ring-black ring-opacity-5">

                                    <!-- Kalender -->
                                    <div
                                        class="p-4 bg-white dark:bg-gray-800 text-lg z-10 w-full lg:w-[25vw] border-2 border-slate-900 shadow-xl">
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
                        <h1 class="text-lg md:text-2xl font-semibold ">Durasi</h1>
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
                                    class="p-2.5 cursor-pointer text-black w-full font-semibold text-center border-2 border-slate-900">
                                    {{ $item['hours'] }} jam
                                </div>
                            @endforeach

                        </div>
                    </div>

                </div>
            </div>
            <div class="mt-5 space-y-2 hidden sm:flex flex-col" x-data="{ price: @entangle('selectedPrice').live }">
                <span class="text-xl sm:text-2xl font-bold hidden md:flex"
                    x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(price)"></span>
                <button type="button" wire:click="bookingNow"
                    class="flex justify-between {{ $is_available ? '' : 'bg-gray-300' }} items-center space-x-4 bg-black text-white text-xl font-semibold group overflow-hidden cursor-pointer p-4 hover:bg-gray-300 border-2 hover:border-black hover:text-black transition duration-700 ease-in-out w-full hidden sm:flex">
                    <div class="text-start">
                        <h1 class="text-xl font-bold">
                            {{ $is_available ? 'Booking Sekarang' : 'Tidak tersedia' }}
                        </h1>
                    </div>
                    <div class="w-fit h-full group-hover:translate-x-24 transition duration-200 ease-in-out">
                        <svg width="64" height="20" viewBox="0 0 64 16" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M63.7071 8.70711C64.0976 8.31658 64.0976 7.68342 63.7071 7.29289L57.3431 0.928932C56.9526 0.538408 56.3195 0.538408 55.9289 0.928932C55.5384 1.31946 55.5384 1.95262 55.9289 2.34315L61.5858 8L55.9289 13.6569C55.5384 14.0474 55.5384 14.6805 55.9289 15.0711C56.3195 15.4616 56.9526 15.4616 57.3431 15.0711L63.7071 8.70711ZM0 8V9H63V8V7H0V8Z"
                                fill="white" />
                        </svg>
                    </div>
                </button>
                <span
                    class="text-xs italic text-red-500">{{ !$is_available ? 'tidak tersedia untuk tanggal yang dipilih' : '' }}</span>
            </div>
            <div x-data="{ expanded: false }" class="flex flex-col md:hidden">
                <div class="border-b-2 border-gray-200 py-4 mt-3 text-lg md:text-2xl font-semibold flex justify-between items-center cursor-pointer"
                    @click="expanded = ! expanded">
                    <h1>Deskripsi</h1>
                    <div>
                        <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none"
                            class="ease-in duration-200" :class="expanded ? 'rotate-180' : ''"
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

                <div x-show="expanded" x-collapse class="mt-4" x-cloak>
                    <div
                        class="prose prose-base lg:prose-lg prose-invert text-black prose-li:text-black prose-a:text-blue-600 max-w-none md:flex flex-col">
                        {!! $iphone->description !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{--  --}}
    <span class="fixed bottom-16 text-sm italic text-red-500">{{ !$is_available ? 'tidak tersedia untuk tanggal yang dipilih' : '' }}</span>
    
    <button type="button" wire:click="bookingNow"
        class="flex justify-between {{ $is_available ? '' : 'bg-gray-300' }} items-center space-x-4 bg-black text-white text-xl font-semibold group overflow-hidden cursor-pointer p-4 hover:bg-gray-300 hover:text-black transition duration-700 ease-in-out w-full fixed bottom-0 sm:hidden hover:border-2 hover:border-black">
        <div class="text-start">
            <h1 class="text-xl font-bold">
                {{ $is_available ? 'Booking Sekarang' : 'Tidak tersedia' }}
            </h1>
            
        </div>
        <div class="w-fit h-full group-hover:translate-x-24 transition duration-200 ease-in-out">
            <svg width="64" height="20" viewBox="0 0 64 16" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M63.7071 8.70711C64.0976 8.31658 64.0976 7.68342 63.7071 7.29289L57.3431 0.928932C56.9526 0.538408 56.3195 0.538408 55.9289 0.928932C55.5384 1.31946 55.5384 1.95262 55.9289 2.34315L61.5858 8L55.9289 13.6569C55.5384 14.0474 55.5384 14.6805 55.9289 15.0711C56.3195 15.4616 56.9526 15.4616 57.3431 15.0711L63.7071 8.70711ZM0 8V9H63V8V7H0V8Z"
                    fill="white" />
            </svg>
        </div>
    </button>
    {{-- Reviews --}}
    <div class="xl:w-[65%] p-3 xl:p-0 xl:mt-0" x-data="{ reviewsOpen: false }">
        <div class="border-b-2 border-gray-200 py-4 text-lg md:text-2xl font-semibold flex justify-between items-center cursor-pointer"
            @click="reviewsOpen = ! reviewsOpen">
            <h1>Lihat Reviews</h1>
            <div>
                <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" class="ease-in duration-200"
                    :class="reviewsOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg">
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
        <div class="space-y-5" x-show="reviewsOpen" x-collapse x-cloak class="">

            <div class="space-y-3 mt-4">
                <div class="flex justify-start items-center space-x-4">
                    <div class="w-[40px] sm:w-[60px] h-[40px] sm:h-[60px] rounded-full overflow-hidden">
                        <img src="https://placehold.co/600x400" alt="" srcset=""
                            class="object-cover w-full h-full">
                    </div>
                    <div class="space-y-2">
                        <div>
                            <h3 class="font-semibold text-lg sm:text-xl text-gray-600">{{ $name }}</h3>
                            <p class="text-sm text-gray-600 font-medium">Tulis review dengan nama anonimous</p>
                        </div>
                    </div>
                </div>
                <livewire:reviews :iphone_id="$selectedIphoneId" :rating="$rating" :name="$name" :avgRating="$avgRating" :reviews="$reviews" />
            </div>
        </div>
    </div>
    <livewire:cards lazy="on-load" :title="'Mungkin anda tertarik'" />
    <x-modal name="user-booking-create" :show="$errors->isNotEmpty()" rounded="rounded-none" border="border-2 border-slate-900">
        <form wire:submit="booking">
            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="p-4 border" x-on:close-modal.window="show = false">
                <div class="space-y-4">
                    <div>
                        <h1 class="text-xl font-medium ">Nama</h1>
                        <input type="text" wire:model.live.debounce.250ms="customer_name"
                            class="w-full p-2 border-2 border-slate-900 " placeholder="e.g. John Doe">
                    </div>
                    <div>
                        <h1 class="text-xl font-medium ">Nomor Whatsapp</h1>
                        <div x-data="{
                            countryCode: @entangle('countryCode'),
                            customerPhone: @entangle('customer_phone').live,
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
                                <p>{{ $iphone->name }}</p>
                            </div>
                            <div class="flex justify-between items-center font-medium">
                                <p>Durasi</p>
                                <p>24 jam</p>
                            </div>
                            <div class="flex justify-between items-center font-medium">
                                <p>Tanggal booking</p>
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
                        <div class="sm:flex justify-between">

                            <h1 class="text-2xl font-semibold mb-4">Metode Pembayaran</h1>
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
                        <img src="{{ asset('storage/' . $selectedPayment->icon) }}" alt=""
                            class="w-48 h-w-48 object-cover rounded-md">

                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300 italic leading-relaxed">
                            <span>{{ $selectedPayment->description }}</span>
                        </div>

                    </div>
                </div>
            </div>
            <button type="submit" class="p-3 font-semibold text-white bg-black w-full disabled:bg-gray-400"
                wire:loading.attr="disabled">Buat Boooking</button>
        </form>
    </x-modal>
    <x-bottom-sheet id="sheetTanggalB" title="Form Booking">
        <!-- Kalender -->
        <div class=" bg-white dark:bg-gray-800 text-lg z-10 w-full ">
            <div class="border-b-2 border-gray-300 pb-4">
                <div class="flex items-center  text-lg">
                    <!-- Hour Picker -->
                    <div>
                        <input type="number" x-model="selectedHour" min="0" max="23"
                            class="w-16 text-center bg-transparent border border-transparent focus:border-gray-400 focus:outline-none px-2 py-1 rounded text-2xl font-bold"
                            placeholder="HH">
                    </div>

                    <div class="font-bold">:</div>

                    <!-- Input Menit -->
                    <div>
                        <input type="number" x-model="selectedMinute" min="0" max="59" step="1"
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
                    <div @click="!isPastDate(date) && pickDate(date); $refs.dropdownButton?.click()" x-text="date"
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
    </x-bottom-sheet>
</div>
