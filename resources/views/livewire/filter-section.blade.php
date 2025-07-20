<div x-data="{
    open: false,
    showDatepicker: false,
    selectedDate: null,
    selectedDateFormatted: @entangle('selectedDateFormatted'),
    month: null,
    year: null,
    daysInMonth: [],
    blankdays: [],
    monthNames: [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ],
    activeTab: null, // Default active tab
    price: 0,

    setActiveTab(hours, price) {
        this.activeTab = hours;
        this.price = price;
        {{-- $wire.setDuration(hours, price); --}}
    },

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
        const month = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][date.getMonth()];
        const year = date.getFullYear();
        return `${day} ${month} ${year}`;
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

    searchIphones() {
        this.$dispatch('open-modal', 'ipip');
        $wire.getIphoneByDate();
    },

    formatRupiah(number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(number).replace(/\s+/g, ' ');
    }


}">
    <div class="items-center w-full relative" x-cloak>
        <div class="space-y-2 w-[92%]">
            <h1 class="text-lg font-semibold">cek ketersediaan iPhone</h1>
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                <div @click="open = ! open">
                    <div class="relative">
                        <div class="absolute inset-y-0 start-0 flex items-center p-3 pointer-events-none">
                            <svg width="30px" height="30px" viewBox="0 0 24.00 24.00" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M20 10V7C20 5.89543 19.1046 5 18 5H6C4.89543 5 4 5.89543 4 7V10M20 10V19C20 20.1046 19.1046 21 18 21H6C4.89543 21 4 20.1046 4 19V10M20 10H4M8 3V7M16 3V7"
                                    stroke="#000000" stroke-width="1.344" stroke-linecap="round"></path>
                                <rect x="6" y="12" width="3" height="3" rx="0.5" fill="#000000" />
                                <rect x="10.5" y="12" width="3" height="3" rx="0.5" fill="#000000" />
                                <rect x="15" y="12" width="3" height="3" rx="0.5" fill="#000000" />
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
                    x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute z-50 mt-2 
                    style="display: none;">
                    <div class="rounded-md ring-1 ring-black ring-opacity-5">
                        <!-- Kalender -->
                        <div
                            class="p-4 bg-white dark:bg-gray-800 text-lg z-10 w-[30vw] border-2 border-slate-900 shadow-xl">
                            <!-- Header navigasi bulan -->
                            <div class="flex items-center justify-between mb-2">
                                <button @click="prevMonth()"
                                    class="px-2 py-1 text-gray-600 hover:bg-gray-200 rounded">&lt;</button>
                                <div class="text-lg font-medium text-gray-700 dark:text-gray-200"
                                    x-text="monthNames[month] + ' ' + year"></div>
                                <button @click="nextMonth()"
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
                                    <div @click="pickDate(date); $refs.dropdownButton?.click()" x-text="date"
                                        class="text-center cursor-pointer p-4 flex justify-center transition-colors duration-200 ease-in-out border-2 border-transparent hover:border-slate-900"
                                        :class="{
                                            'bg-slate-900 text-white': isSelectedDate(date)
                                        }">
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <button @click="searchIphones()" wire:loading.attr="disabled"
            class="ml-2 bg-slate-900 text-white p-3 font-semibold hover:bg-white hover:text-black border-2 border-transparent hover:border-black transition-all duration-100 ease-in-out absolute end-0 bottom-0">
            <x-icons.search default="30px" />
        </button>
    </div>
    <x-modal name="ipip" :show="$errors->isNotEmpty()" rounded="rounded-none" border="border-2 border-slate-800" >
        @if (isset($iphoneByDate) && $iphoneByDate->isNotEmpty())
            @foreach ($iphoneByDate as $iphone)
                <div class="p-3 space-y-2 w-full flex items-start justify-between relative border border-slate-400" >
                    <div class="p-2">
                        <div class="space-y-1">
                            <h1 class="text-xl font-semibold">{{ $iphone->name }}</h1>
                            <div class="flex border border-gray-400 rounded-lg w-fit p-1">
                                {{-- cable --}}
                                <svg fill="#a1a1a1" height="23px" width="23px" version="1.1" id="Layer_1"
                                    xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                    viewBox="0 0 512 512" xml:space="preserve">
                                    <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                    <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                    <g id="SVGRepo_iconCarrier">
                                        <g>
                                            <g>
                                                <g>
                                                    <path
                                                        d="M332.804,256h-8.533v-93.867c0-14.114-11.486-25.6-25.6-25.6h-85.333c-14.123,0-25.6,11.486-25.6,25.6V256h-8.533 c-9.412,0-17.067,7.654-17.067,17.067v170.667c0,9.412,7.654,17.067,17.067,17.067h34.133v34.133 c0,9.412,7.654,17.067,17.067,17.067h51.2c9.412,0,17.067-7.654,17.067-17.067V460.8h34.133c9.412,0,17.067-7.654,17.067-17.067 V273.067C349.871,263.654,342.217,256,332.804,256z M230.404,494.933V460.8h51.2l0.009,34.133H230.404z M298.671,204.8 c0,4.719-3.823,8.533-8.533,8.533h-68.267c-4.719,0-8.533-3.814-8.533-8.533v-25.6c0-4.719,3.814-8.533,8.533-8.533h68.267 c4.71,0,8.533,3.814,8.533,8.533V204.8z">
                                                    </path>
                                                    <path
                                                        d="M298.667,51.2h-85.333c-4.702,0-8.533,3.823-8.533,8.533c0,4.71,3.831,8.533,8.533,8.533h85.333 c4.702,0,8.533-3.823,8.533-8.533C307.2,55.023,303.369,51.2,298.667,51.2z">
                                                    </path>
                                                    <path
                                                        d="M332.8,0H179.2c-14.114,0-25.6,11.486-25.6,25.6v68.267c0,14.114,11.486,25.6,25.6,25.6h153.6 c14.114,0,25.6-11.486,25.6-25.6V25.6C358.4,11.486,346.914,0,332.8,0z M298.667,85.333h-85.333c-14.114,0-25.6-11.486-25.6-25.6 s11.486-25.6,25.6-25.6h85.333c14.114,0,25.6,11.486,25.6,25.6S312.781,85.333,298.667,85.333z">
                                                    </path>
                                                </g>
                                            </g>
                                        </g>
                                    </g>
                                </svg>
                                {{-- adapter --}}
                                <svg fill="#a1a1a1" width="23px" height="23px" viewBox="0 0 32 32"
                                    style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;"
                                    version="1.1" xml:space="preserve" xmlns="http://www.w3.org/2000/svg"
                                    xmlns:serif="http://www.serif.com/" xmlns:xlink="http://www.w3.org/1999/xlink">
                                    <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                    <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                    <g id="SVGRepo_iconCarrier">
                                        <g id="Icon"></g>
                                        <path
                                            d="M25,8c0,-0.552 -0.448,-1 -1,-1l-16,0c-0.552,0 -1,0.448 -1,1l0,16c0,0.552 0.448,1 1,1l16,0c0.552,0 1,-0.448 1,-1l0,-16Zm-2,1l-0,14c0,0 -14,0 -14,0c0,0 0,-14 0,-14l14,-0Z">
                                        </path>
                                        <path
                                            d="M20.5,23.5c0,-0.276 -0.224,-0.5 -0.5,-0.5l-8,0c-0.276,0 -0.5,0.224 -0.5,0.5l0,3c0,0.276 0.224,0.5 0.5,0.5l8,-0c0.276,0 0.5,-0.224 0.5,-0.5l0,-3Zm-1,0.5l0,2c0,-0 -7,-0 -7,-0c0,-0 0,-2 0,-2l7,0Z">
                                        </path>
                                        <path
                                            d="M21.5,5.5c0,-0.276 -0.224,-0.5 -0.5,-0.5l-10,0c-0.276,0 -0.5,0.224 -0.5,0.5l0,3c0,0.276 0.224,0.5 0.5,0.5l10,-0c0.276,-0 0.5,-0.224 0.5,-0.5l0,-3Zm-1,0.5l0,2c-0,-0 -9,-0 -9,-0c-0,-0 0,-2 0,-2l9,0Z">
                                        </path>
                                        <path
                                            d="M14.5,5.5l0,-2.095c0,-0.276 -0.224,-0.5 -0.5,-0.5c-0.276,-0 -0.5,0.224 -0.5,0.5l0,2.095c0,0.276 0.224,0.5 0.5,0.5c0.276,-0 0.5,-0.224 0.5,-0.5Z">
                                        </path>
                                        <path
                                            d="M18.5,5.5l0,-2.095c0,-0.276 -0.224,-0.5 -0.5,-0.5c-0.276,-0 -0.5,0.224 -0.5,0.5l0,2.095c0,0.276 0.224,0.5 0.5,0.5c0.276,-0 0.5,-0.224 0.5,-0.5Z">
                                        </path>
                                        <path
                                            d="M15.553,11.776l-2,4c-0.078,0.155 -0.069,0.339 0.022,0.487c0.091,0.147 0.252,0.237 0.425,0.237l3.191,-0c-0,-0 -1.638,3.276 -1.638,3.276c-0.124,0.247 -0.023,0.548 0.223,0.671c0.247,0.124 0.548,0.023 0.671,-0.223l2,-4c0.078,-0.155 0.069,-0.339 -0.022,-0.487c-0.091,-0.147 -0.252,-0.237 -0.425,-0.237l-3.191,-0c0,0 1.638,-3.276 1.638,-3.276c0.124,-0.247 0.023,-0.548 -0.223,-0.671c-0.247,-0.124 -0.548,-0.023 -0.671,0.223Z">
                                        </path>
                                        <path
                                            d="M15.5,26.5l0,2.095c0,0.277 0.224,0.5 0.5,0.5l3,0c0.276,0 0.5,-0.224 0.5,-0.5c0,-0.276 -0.224,-0.5 -0.5,-0.5l-2.5,0c-0,0 0,-1.595 0,-1.595c0,-0.276 -0.224,-0.5 -0.5,-0.5c-0.276,-0 -0.5,0.224 -0.5,0.5Z">
                                        </path>
                                    </g>
                                </svg>
                                {{-- bag --}}
                                <svg fill="#a1a1a1" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg"
                                    xmlns:xlink="http://www.w3.org/1999/xlink" width="20px" height="20px"
                                    viewBox="0 0 43.181 43.181" xml:space="preserve">
                                    <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                    <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                    <g id="SVGRepo_iconCarrier">
                                        <g>
                                            <g>
                                                <path
                                                    d="M25.629,11.02h7.332c-9.344-9.21-13.398-9.21-22.742,0h7.332C20.669,8.475,22.51,8.475,25.629,11.02z">
                                                </path>
                                                <path
                                                    d="M39.629,13.02H7.541c-2.762,0-5.264,2.223-5.59,4.965l-1.915,16.12c-0.326,2.742,1.649,4.965,4.41,4.965h34.29 c2.763,0,4.735-2.223,4.41-4.965L40.76,14.022C40.426,13.642,40.047,13.305,39.629,13.02z">
                                                </path>
                                            </g>
                                        </g>
                                    </g>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-5 flex space-x-5 text-sm font-semibold text-gray-600">
                            <button>Detail</button>
                            <button>Refund</button>
                            <button>Reschedule</button>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        @foreach ($iphone->durations as $duration)
                            <div @click="setActiveTab({{ $duration->hours }}, {{ $duration->pivot->price }}, )"
                                :class="{ 'bg-black text-white': activeTab === {{ $duration->hours }} }"
                                class="px-3 py-1 font-medium text-sm  text-center cursor-pointer border border-slate-400 text-black">
                                {{ $duration->hours }} jam
                            </div>
                        @endforeach

                    </div>
                    <div class="">
                        <div class="text-xl font-bold text-red-500">
                            <h1 x-text="formatRupiah(price)"></h1>
                        </div>
                        <button
                            class="absolute bottom-4 box-border bg-black py-2 px-4 text-white text-base font-semibold right-5">booking</button>
                    </div>
                </div>
            @endforeach
        @else
        @endif
    </x-modal>
</div>
