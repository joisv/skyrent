<div class="max-w-screen-2xl mx-auto p-3 mt-5 sm:mt-0 space-y-3 ">
    <h1 class="text-2xl font-semibold">
        Reviews
    </h1>
    <div class="swiper mySwiperReview">
        <div class="swiper-wrapper">
            @if (!empty($reviews))
                @foreach ($reviews as $review)
                    <div class="swiper-slide bg-gray-200 bg-opacity-30 rounded-md" x-data="{
                        name: @js($review->name),
                        colors: ['bg-red-500', 'bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500'],
                        get initial() { return this.name.charAt(0).toUpperCase() },
                        get randomColor() { return this.colors[Math.floor(Math.random() * this.colors.length)] }
                    }">
                        <div class="p-2">
                            {{-- Display the rating using a custom component or a simple star rating --}}
                            <x-star-rating :rating="$review->rating" size="text-yellow-400 w-6 h-6" />

                            <div class="mt-5">
                                <div x-data="{
                                    fullText: @js($review->comment),
                                    maxLength: 200
                                }" class="mb-3">
                                    <span
                                        x-text="fullText.length > maxLength 
                        ? fullText.substring(0, maxLength) + '...' 
                        : fullText">
                                    </span>
                                </div>
                            </div>

                            <div class="border-t border-gray-300 p-2 flex items-center mt-5 space-x-2">
                                <div :class="randomColor"
                                    class="w-[40px] h-[40px] flex-none rounded-full flex items-center justify-center text-white font-bold text-lg">
                                    <span x-text="initial"></span>
                                </div>
                                <div>
                                    <h1 class="text-base font-semibold" x-text="name"></h1>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif


        </div>
    </div>
    <script>
        reviewSwiper = new Swiper(".mySwiperReview", {
            loop: true,
            autoplay: {
                delay: 2500,
                disableOnInteraction: false,
            },
            spaceBetween: 30,
            centeredSlides: false,
            breakpoints: {
                0: {
                    slidesPerView: 1, // default untuk mobile
                },
                640: {
                    slidesPerView: 2, // ≥ 640px
                },
                768: {
                    slidesPerView: 3, // ≥ 768px
                },
            },
        });
    </script>
    {{-- Nothing in the world is as soft and yielding as water. --}}
</div>
