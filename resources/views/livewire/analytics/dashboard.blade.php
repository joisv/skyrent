<div class="space-y-3" x-data="{

    activeTab: $persist('revenue'),

    setActiveTab(tab) {

        this.activeTab = tab

    },

    init() {
        if (this.activeTab == 'revenue') $wire.getRevenues()
    }
}" x-init="$watch('activeTab', value => console.log(value))">
    <div class="md:grid md:grid-cols-4 md:gap-3 space-y-3 md:space-y-0">
        <button @click.prevent="setActiveTab('users')" :class="{ 'ring-4 ring-gray-900': activeTab === 'users' }"
            class="inline-block rounded-sm text-sm font-medium w-full">
            <x-analytics-card>
                <x-slot name="icon">
                    <x-icons.users width="45px" height="45px" />
                </x-slot>
                <x-slot name="title">
                    User
                </x-slot>
                <x-slot name="value">
                    {{ '' }}
                </x-slot>
            </x-analytics-card>
        </button>
        <button @click.prevent="setActiveTab('revenue')"
            :class="{ 'ring-4 ring-emerald-600': activeTab === 'revenue' }"
            class="inline-block rounded-sm text-sm font-medium w-full">
            <x-analytics-card bgIcon="bg-emerald-600">
                <x-slot name="icon">
                    <x-icons.money width="45px" height="45px" />
                </x-slot>
                <x-slot name="title">
                    Revenues
                </x-slot>
                <x-slot name="value">
                    <div x-data="{ total: {{ $total_revenues }} }"
                        x-text="new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(total)">
                    </div>
                </x-slot>

            </x-analytics-card>
        </button>
        <button @click.prevent="setActiveTab('views')" :class="{ 'ring-4 ring-rose-400': activeTab === 'views' }"
            class="inline-block rounded-sm text-sm font-medium w-full">
            <x-analytics-card bgIcon="bg-rose-500">
                <x-slot name="icon">
                    <x-icons.eye width="45px" height="45px" />
                </x-slot>
                <x-slot name="title">
                    Views
                </x-slot>
                <x-slot name="value">
                    {{ '' }}
                </x-slot>
            </x-analytics-card>
        </button>
        <button type="button" @click.prevent="setActiveTab('bookings')"
            :class="{ 'ring-4 ring-gray-800': activeTab === 'bookings' }"
            class="inline-block rounded-sm text-sm font-medium w-full">
            <x-analytics-card>
                <x-slot name="icon">
                    <x-icons.basket width="45px" height="45px" />
                </x-slot>
                <x-slot name="title">
                    Bookings
                </x-slot>
                <x-slot name="value">
                    {{ '' }} %
                </x-slot>
            </x-analytics-card>
        </button>
    </div>
    <div class="w-full lg:flex lg:space-x-2 space-y-3 lg:space-y-0">
        <div class="w-full bg-white p-2">
            <div class="content min-h-[50vh]">
                <div x-cloak x-show="activeTab === 'revenue'">
                    <div class="flex">
                        <div class="lg:w-[70%]">
                            <livewire:analytics.revenue-chart />
                        </div>
                        <div class="w-[30%] bg-red-500 h-20">
                            <div wire:loading wire:target="getRevenues">
                                Checking availability of bookings...
                            </div>
                            @dump($bookings)
                        </div>
                    </div>
                </div>
                <div x-cloak x-show="activeTab === 'bookings'">
                    <div class="flex">
                        <div class="lg:w-[70%]">
                            <livewire:analytics.bookings-chart />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- Stop trying to control. --}}
</div>
