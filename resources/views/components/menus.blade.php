<div class="w-full">
    <x-mary-menu active-bg-color="bg-purple-500/10 ">
        <!-- Logo -->
        <div class="shrink-0 lg:flex items-center lg:mb-8 mb-0 hidden">
            <a href="{{ route('dashboard') }}" wire:navigate>
                <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
            </a>
        </div>

        <!-- Navigation Links -->
        <x-mary-menu-sub title="Dashboard" icon="m-cursor-arrow-ripple">
            <x-mary-menu-item title="main" :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate />
            <x-mary-menu-item title="analitycs" wire:navigate />
        </x-mary-menu-sub>
        <x-mary-menu-sub title="Iphones Management" icon="s-user-group">
            <x-mary-menu-item title="New iPhone" wire:navigate :href="route('iphones')" :active="request()->routeIs('iphones') || request()->routeIs('iphones.create')" />
            <x-mary-menu-item title="Orders" wire:navigate />
        </x-mary-menu-sub>
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
