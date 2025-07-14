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
            <x-mary-menu-item title="Revenue" wire:navigate :href="route('reports.revenue')"
                :active="request()->routeIs('reports.revenue')" icon="o-banknotes" wire:navigate />
            <x-mary-menu-item title="Top Device" icon="o-trophy" wire:navigate />
            <x-mary-menu-item title="Booking Sumary" icon="o-calendar-days" wire:navigate />
        </x-mary-menu-sub>
        <x-mary-menu-separator />

        <x-mary-menu-sub title="Settings" icon="o-cog-6-tooth" icon-classes="text-warning">
            <x-mary-menu-item title="Basic" icon="o-adjustments-horizontal" wire:navigate :href="route('settings.basic')"
                :active="request()->routeIs('settings.basic')" />
            <x-mary-menu-item title="Info" icon="o-information-circle" wire:navigate :href="route('settings.info')"
                :active="request()->routeIs('settings.info')" />
            <x-mary-menu-item title="Profile" icon="o-user" wire:navigate :href="route('profile')" :active="request()->routeIs('profile')" />
        </x-mary-menu-sub>
        <x-mary-menu-separator />
        <x-mary-menu-item title="Roles & Permissions" icon="o-key" wire:navigate />
        <x-mary-menu-item title="Notifications" icon="o-bell" wire:navigate />
        <x-mary-menu-item title="FAQ" icon="o-question-mark-circle" wire:navigate />
        {{-- <x-mary-menu-sub title="Shops" icon="o-rectangle-group">
                <x-mary-menu-item title="products" wire:navigate :href="route('products')" :active="request()->routeIs('products') ||
                    request()->routeIs('product.create') ||
                    request()->routeIs('product.edit')" />
                <x-mary-menu-item title="categories" wire:navigate :href="route('categories')" :active="request()->routeIs('categories')" />
                <x-mary-menu-item title="orders" wire:navigate />
            </x-mary-menu-sub>
            <x-mary-menu-sub title="Blog" icon="o-document">
                <x-mary-menu-item title="posts" wire:navigate />
            </x-mary-menu-sub>
            <x-mary-menu-sub title="Settings" icon="c-cog-8-tooth">
                <x-mary-menu-item title="basic" wire:navigate :href="route('basics')" :active="request()->routeIs('basics')" />
                <x-mary-menu-item title="my account" wire:navigate :href="route('profile')" :active="request()->routeIs('profile')" />
                <x-mary-menu-item title="feedback" wire:navigate />
            </x-mary-menu-sub> --}}
        {{-- <x-theme-toggle /> --}}
        </x-menu>
</div>
