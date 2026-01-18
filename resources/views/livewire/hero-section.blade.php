<div class="relative w-full h-auto" x-data="{ openBottomSheet: false }">
    {{-- <div
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
                        <a href="{{ route('detail', $slider->iphone->slug) }}" class="swiper-slide flex justify-center items-center ">
                            <img src="{{ asset('storage/' . $slider->main) }}" alt="iPhone"
                                class="w-full h-full object-contain">
                        </a>
                    @endforeach
                </div>
                <div class="swiper-pagination"></div>
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
            pagination: {
                el: ".swiper-pagination",
            },
            loop: true,
            loopedSlides: 5,
        });
    </script> --}}
    <section class="max-w-screen-2xl mx-auto px-6 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">

            <!-- LEFT CONTENT -->
            <div class="flex flex-col items-center lg:items-start w-full ">
                <div class="flex flex-col justify-center items-center lg:items-start">

                    <!-- Badge -->
                    <div
                        class="inline-block mb-6 px-4 py-1 text-sm font-medium border rounded-full
                mx-auto lg:mx-0">
                        Skyrental™
                    </div>

                    <!-- Heading -->
                    <h1
                        class="text-5xl md:text-6xl font-extrabold leading-tight mb-6
               text-center lg:text-left">
                        <span class="bg-gradient-to-r from-orange-400 to-orange-500 bg-clip-text text-transparent">
                            Sewa iPhone
                        </span><br>
                        <span class="bg-gradient-to-r from-orange-500 to-pink-500 bg-clip-text text-transparent">
                            no.1 di Banyuwangi
                        </span>
                    </h1>

                </div>


                <p class="text-gray-600 mb-8 text-center lg:text-left md:text-lg">
                    {{ $setting->tagline }}
                </p>
                <div class="w-full">
                    <livewire:filter-section />
                </div>
            </div>

            <!-- RIGHT IMAGE -->
            <div class="flex justify-center lg:justify-end -z-50">
                <img src="{{ url('ip17.png') }}" alt="iPhone" class="max-w-48 md:max-w-md drop-shadow-2xl" />
            </div>

        </div>
    </section>
</div>
