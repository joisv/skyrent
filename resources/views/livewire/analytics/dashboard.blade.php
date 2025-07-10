<div class="space-y-3" x-data="{

    activeTab: $persist('views'),

    setActiveTab(tab) {

        this.activeTab = tab

    }
}">
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
                    Revenue
                </x-slot>
                <x-slot name="value">
                    {{ '' }}
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
        <button type="button" @click.prevent="setActiveTab('conversion')"
            :class="{ 'ring-4 ring-gray-800': activeTab === 'conversion' }"
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
    {{-- Stop trying to control. --}}
</div>
