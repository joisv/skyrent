<div class="mt-10 p-3" wire:init="getIphones">
    <div>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        <div class="grid  grid-cols-2 lg:grid-cols-4 lg:gap-3 gap-x-1 gap-y-4 sm:mt-10 mt-5">
            @empty(!$iphones)

                @forelse ($iphones as $iphone)
                    <div class="space-y-2 flex flex-col justify-center items-center">
                        <img src="{{ asset('storage/' . $iphone->gallery->image) }}" alt=""
                            class="w-full object-contain h-64">
                        <h2 class="text-lg font-semibold text-center">{{ $iphone->name }}</h2>
                        <a href="{{ route('detail', $iphone->slug) }}" wire:navigate class="bg-slate-900 p-2 text-white w-fit px-3 text-center text-sm lg:px-5">Sewa Sekarang</a>
                    </div>
                @empty
                    <p class="text-center col-span-4" wire:loading.remove>No iPhones available at the moment.</p>
                @endforelse
            @endempty

            {{-- Loading Spinner --}}
            <div class="w-full min-h-[70vh] flex justify-center items-center col-span-4" wire:loading.flex>
                <svg width="64px" height="64px" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill="none"
                    class="animate-spin">
                    <g fill="#f43f5e" fill-rule="evenodd" clip-rule="evenodd">
                        <path d="M8 1.5a6.5 6.5 0 100 13 6.5 6.5 0 000-13zM0 8a8 8 0 1116 0A8 8 0 010 8z"
                            opacity=".2"></path>
                        <path
                            d="M7.25.75A.75.75 0 018 0a8 8 0 018 8 .75.75 0 01-1.5 0A6.5 6.5 0 008 1.5a.75.75 0 01-.75-.75z">
                        </path>
                    </g>
                </svg>
                <span class="sr-only">Loading...</span>
            </div>
        </div>

    </div>
</div>
