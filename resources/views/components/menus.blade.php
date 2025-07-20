<div class="w-full">
    <x-mary-menu active-bg-color="bg-purple-500/10 ">
        <!-- Logo -->
        <div class="shrink-0 lg:flex items-center lg:mb-8 mb-0 hidden">
            <a href="{{ route('dashboard') }}" wire:navigate>
                <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
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
            <x-mary-menu-item title="Info" icon="o-information-circle" :href="route('settings.info')" :active="request()->routeIs('settings.info')" />
            <x-mary-menu-item title="Profile" icon="o-user" wire:navigate :href="route('profile')" :active="request()->routeIs('profile')" />
            <x-mary-menu-item title="User Permissions" icon="o-shield-check" wire:navigate :href="route('settings.permissions')" :active="request()->routeIs('settings.permissions')" />

        </x-mary-menu-sub>
        <x-mary-menu-separator />
        <x-mary-menu-item title="Roles & Permissions" icon="o-key" wire:navigate />
        <x-mary-menu-item title="Notifications" icon="o-bell" wire:navigate />
        <x-mary-menu-item title="FAQ" icon="o-question-mark-circle" wire:navigate />
        </x-menu>
</div>
