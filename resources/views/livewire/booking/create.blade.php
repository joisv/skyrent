<div class="p-5 h-[90vh]">
    <div class="text-xl font-semibold">
        <h1>Make new booking</h1>
    </div>
    <div class="mt-4 space-y-3">
        <div>
            <label for="customer_name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Nama
                customer</label>
            <input type="text" id="customer_name"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                placeholder="John" required />
        </div>
        <div>
            <label for="customer_phone" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Nomor
                customer</label>
            <input type="number" id="customer_phone"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                placeholder="628316547875" required />
        </div>
        <div>
            <label for="customer_email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Email
                customer</label>
            <input type="email" id="customer_email"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                placeholder="jhondoe@gmail.com" required />
        </div>
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

                selectIphone: function(value) {
                    $wire.selectedIphone = value; // update the Livewire property
                    this.close();
                }
            
            }" @click.away="close()">
                <input type="text" class="hidden">
                <span class="inline-block w-full rounded-md shadow-sm"
                    @click="toggle(); $nextTick(() => $refs.filterinput.focus());">
                    <button
                        class="relative z-0 w-full py-2 pl-3 pr-10 text-left transition duration-150 ease-in-out bg-white border border-gray-300 rounded-md cursor-default focus:outline-none focus:shadow-outline-blue focus:border-blue-300 sm:text-sm sm:leading-5">
                        <span class="block truncate" x-text="$wire.selectedIphone ?? 'Pilih tipe iPhone'"></span>

                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" viewBox="0 0 20 20" fill="none" stroke="currentColor">
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
                            <li
                                class="relative py-1 pl-3 mb-1 text-gray-900 select-none pr-9 hover:bg-gray-100 cursor-pointer rounded-md">
                                <span class="block font-normal truncate" @click="selectIphone('{{$iphone->name}}')">{{ $iphone->name }}</span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-700">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </li>
                        @endforeach

                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
