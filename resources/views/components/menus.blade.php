<div class="w-full">
    <x-mary-menu active-bg-color="bg-purple-500/10 ">
        <!-- Logo -->
        <div class="shrink-0 lg:flex items-center lg:mb-8 mb-0 hidden">
            <a href="{{ route('dashboard') }}" wire:navigate class="w-full ">
                @if (!empty($setting->logo_cms))
                    <img src="{{ asset('storage/' . $setting->logo_cms) }}" alt="" srcset=""
                        class="w-full h-28 object-contain">
                        @else
                        <img src="{{ asset('storage/'.$setting->logo_cms) }}" alt="" srcset=""
                            class="w-40 h-40 object-cover">
                    <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                @endif
            </a>
        </div>

        <!-- Navigation Links -->
        <x-mary-menu-item title="Dashboard" icon="o-home" :href="route('dashboard')" :active="request()->routeIs('dashboard')" />

        <x-mary-menu-sub title="Iphones Management" icon="o-device-phone-mobile">
            <x-mary-menu-item title="New iPhone" icon="o-plus-circle" wire:navigate :href="route('iphones')"
                :active="request()->routeIs('iphones') || request()->routeIs('iphones.create')" />
            <x-mary-menu-item title="Bookings" icon="o-calendar-days" wire:navigate :href="route('bookings')"
                :active="request()->routeIs('bookings')" />
        </x-mary-menu-sub>
        <x-mary-menu-sub title="Report" icon="o-chart-bar">
            <x-mary-menu-item title="Revenue" wire:navigate :href="route('reports.revenue')" :active="request()->routeIs('reports.revenue')" icon="o-banknotes" />
            <x-mary-menu-item title="Top Device" icon="o-trophy" wire:navigate :href="route('reports.topdevice')" :active="request()->routeIs('reports.topdevice')" />
        </x-mary-menu-sub>
        <x-mary-menu-separator />

        <x-mary-menu-sub title="Settings" icon="o-cog-6-tooth" icon-classes="text-warning">
            <x-mary-menu-item title="Basic" icon="o-adjustments-horizontal" wire:navigate :href="route('settings.basic')"
                :active="request()->routeIs('settings.basic')" />
            <x-mary-menu-item title="Profile" icon="o-user" wire:navigate :href="route('profile')" :active="request()->routeIs('profile')" />
            <x-mary-menu-item title="Daftar User" icon="o-users" wire:navigate :href="route('settings.users')" :active="request()->routeIs('settings.users')" />

            <x-mary-menu-item title="Slider" icon="o-photo" wire:navigate :href="route('settings.sliders')" :active="request()->routeIs('settings.sliders')" />

        </x-mary-menu-sub>
        <x-mary-menu-separator />
        <x-mary-menu-item title="Payments" icon="o-credit-card" wire:navigate :href="route('payments')" :active="request()->routeIs('payments')" />
        <x-mary-menu-item title="Roles & Permissions" icon="o-key" wire:navigate />
        <x-mary-menu-item title="Notifications" icon="o-bell" wire:navigate />
        <x-mary-menu-item title="FAQ" icon="o-question-mark-circle" wire:navigate :href="route('faq')"
            :active="request()->routeIs('faq')" />
        </x-menu>

        <x-mary-menu-separator />
        <livewire:layout.logout />

</div>
