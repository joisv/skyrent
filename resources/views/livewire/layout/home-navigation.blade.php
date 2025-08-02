<div>
    <div class="w-full h-24 flex items-center justify-between">
        <div class="flex items-center space-x-4 w-[60%] text-slate-950 dark:text-slate-200 font-medium text-base">
          <x-home-navlink :href="route('welcome')" :active="request()->routeIs('welcome')" wire:navigate class="">Home</x-home-navlink>
          <x-home-navlink :href="route('products')" :active="request()->routeIs('products')" wire:navigate class="">Product</x-home-navlink>
          <x-home-navlink href="/" class="">Price</x-home-navlink>
          <x-home-navlink href="/" class="">Reviews</x-home-navlink>
          <x-home-navlink href="/" class="">About us</x-home-navlink>
          <x-home-navlink :href="route('contacts')" :active="request()->routeIs('contacts')" wire:navigate class="">Contact</x-home-navlink>
        </div>
        <div class="flex items-center space-x-3 w-[40%] h-full justify-end">
             <x-mary-theme-toggle/>
        </div>
    </div>
</div>
