<div class="relative max-w-screen-2xl mx-auto p-3 lg:p-5">
    <div class="w-full h-24 lg:flex items-center justify-between hidden ">
        <div class="flex items-center space-x-4 w-[60%] text-slate-950 dark:text-slate-200 font-medium text-base">
            <x-home-navlink :href="route('welcome')" :active="request()->routeIs('welcome')" wire:navigate class="">Home</x-home-navlink>
            <x-home-navlink :href="route('products')" :active="request()->routeIs('products')" wire:navigate class="">Product</x-home-navlink>
            <x-home-navlink href="/" class="">Price</x-home-navlink>
            <x-home-navlink :href="route('faqs')" :active="request()->routeIs('faqs')" wire:navigate class="">FAQ</x-home-navlink>
            <x-home-navlink :href="route('contacts')" :active="request()->routeIs('contacts')" wire:navigate class="">Contact</x-home-navlink>
        </div>
        <div class="flex items-center space-x-3 w-[40%] h-full justify-end">
            <x-mary-theme-toggle />
        </div>
    </div>
    <div>
        <div x-cloak x-data
            class="flex flex-col lg:hidden w-full max-w-[300px] bg-white h-screen fixed z-50 p-2 right-0 ease-in duration-100"
            :class="{ 'translate-x-full': !setNav, '': setNav }">

            <ul class="mt-3">

                <x-responsif-link :href="route('welcome')" :active="request()->routeIs('welcome')" wire:navigate icon="heroicon-o-home">
                    Home
                </x-responsif-link>

                <x-responsif-link :href="route('products')" :active="request()->routeIs('products')" wire:navigate icon="heroicon-o-archive-box">
                    Product
                </x-responsif-link>

                <x-responsif-link href="/" icon="heroicon-o-currency-dollar">
                    Price
                </x-responsif-link>

                <x-responsif-link :href="route('faqs')" :active="request()->routeIs('faqs')" wire:navigate
                    icon="heroicon-o-question-mark-circle">
                    FAQ
                </x-responsif-link>

                <x-responsif-link :href="route('contacts')" :active="request()->routeIs('contacts')" wire:navigate icon="heroicon-o-envelope">
                    Contact
                </x-responsif-link>
            </ul>
            <button class="absolute bottom-4 right-0" @click="setNav = false">
                <x-icons.arrow color="#350B75" />
            </button>
        </div>
    </div>
