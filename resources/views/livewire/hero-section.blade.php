<div class="relative w-full h-auto" x-data="{ openBottomSheet: false }">
    <div
        class="flex flex-col lg:flex-row lg:items-center lg:justify-center lg:space-x-6 max-w-screen-2xl mx-auto space-y-8">
        <!-- Teks & Filter -->
        <div class="w-full lg:w-1/2 md:space-y-9 p-3 lg:p-5 ">
            <div class="space-y-5">
                <h1 class="text-4xl lg:text-6xl font-semibold xl:text-7xl xl:font-medium hidden lg:flex">
                    {{ $setting->tagline }}
                </h1>
                <div class="sm:flex space-x-3 items-center w-full hidden">
                    <button
                        class="bg-slate-900 text-white text-lg lg:text-xl p-3 font-semibold border-2 border-transparent hover:bg-white hover:text-black hover:border-black transition-all duration-100 ease-in-out">
                        Sewa Sekarang
                    </button>
                    <div
                        class="flex items-center space-x-4 bg-white text-black text-lg lg:text-xl p-3 font-semibold group overflow-hidden cursor-pointer border-2 border-slate-900">
                        <h1>Details</h1>
                        <div class="w-full h-full group-hover:translate-x-24 transition duration-200 ease-in-out">
                            <x-icons.arrow1 />
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-3 space-y-3 flex flex-col lg:hidden">

                <h1 class="text-5xl font-bold w-[90%]">
                    {{ $setting->tagline }}
                </h1>
                <p class="text-base font-semibold text-gray-500">Temukan iphone untuk kamu</p>
            </div>
            <livewire:filter-section />
        </div>
        <!-- Slider -->
        <div class="w-full lg:w-1/2">
            <div class="swiper mySwiper w-full h-[40vh] sm:h-[60vh] lg:h-[70vh]">
                <div class="swiper-wrapper ">
                    @foreach ($sliders as $slider)
                        <div class="swiper-slide flex justify-center items-center ">
                            <img src="{{ asset('storage/' . $slider->main) }}" alt="iPhone"
                                class="w-full h-full object-contain">
                        </div>
                    @endforeach
                </div>

            </div>
        </div>


    </div>
    <script>
        // di hero-section
        var heroSwiper = new Swiper(".mySwiper", {
            slidesPerView: 1.2,
            spaceBetween: 1,
            breakpoints: {
                768: {
                    slidesPerView: 1, // di desktop
                    spaceBetween: 0,
                }
            },
            loop: true,
            loopedSlides: 5,
        });
    </script>
</div>
