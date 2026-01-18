<x-home>
    <livewire:hero-section />
    <livewire:review-page />
    <section class="overflow-hidden py-5 lg:py-2 bg-white space-y-2 ">

        <!-- TOP : Right to Left -->
        <div class="relative overflow-hidden">
            <div class="flex w-max animate-marquee-left gap-16">
                <span class="text-[10vw] font-extrabold text-gray-900 whitespace-nowrap">
                    Sewa iPhone no.1 😎
                </span>
                <span class="text-[10vw] font-extrabold text-gray-900 whitespace-nowrap">
                    Sewa iPhone no.1
                </span>
            </div>
        </div>

        <!-- BOTTOM : Left to Right -->
        <div class="relative overflow-hidden">
            <div class="flex w-max animate-marquee-right gap-16">
                <span class="text-[10vw] font-extrabold text-transparent stroke-text whitespace-nowrap">
                    Raya Di Banyuwangi<span class="text-orange-500">.</span>
                </span>
                <span class="text-[10vw] font-extrabold text-transparent stroke-text whitespace-nowrap">
                    Raya Di Banyuwangi<span class="text-orange-500">.</span>
                </span>
            </div>
        </div>

    </section>
    <div class="max-w-screen-2xl mx-auto">
        <livewire:cards lazy="on-load" />
    </div>
</x-home>
