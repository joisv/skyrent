<x-home>
    <x-slot name="overide">
        {!! seo($overide) !!}
    </x-slot>
    <div class="max-w-6xl mx-auto items-center ">
        {{-- mobile --}}
        <div class="w-full p-3 overflow-hidden" x-data="{
        
            activeTab: $persist('whatsapp'),
        
            setActiveTab(tab) {
        
                this.activeTab = tab
        
            },
        
        }">
            <div class="flex w-full justify-end text-end">
                <h4 class="w-1/2 text-lg font-semibold text-gray-400">Jl. KH. Imam Bahri, Dusun Kopen,
                    Desa
                    Genteng Kulon, Kec.
                    Genteng, Kab. Banyuwangi</h4>
            </div>
            <div class="mt-20">
                <h1 class="text-5xl font-extrabold">
                    Contacts
                </h1>
                <div class=" mt-8 border-b-2 pb-3 mySwiperContacts">
                    <div class="flex space-x-3 swiper-wrapper font-semibold text-gray-400">
                        <button @click.prevent="setActiveTab('whatsapp')"
                            :class="{ 'text-gray-900': activeTab === 'whatsapp' }" class="swiper-slide">
                            <h3>whatsapp</h3>
                        </button>
                        <button @click.prevent="setActiveTab('instagram')"
                            :class="{ 'text-gray-800': activeTab === 'instagram' }" class="swiper-slide">
                            <h3>instagram</h3>
                        </button>
                        <button class="swiper-slide">
                            <h3>facebook</h3>
                        </button>
                        <button class="swiper-slide">
                            <h3>email</h3>
                        </button>
                        <button class="swiper-slide">
                            <h3>telegram</h3>
                        </button>
                        <button class="swiper-slide">
                            <h3>youtube</h3>
                        </button>
                        <button class="swiper-slide">
                            <h3>twitter</h3>
                        </button>
                        {{-- <h3>instagram</h3>
                        <h3>tiktok</h3>
                        <h3>facebook</h3>
                        <h3>email</h3>
                        <h3>twitter</h3>
                        <h3>telegram</h3> --}}
                    </div>
                </div>
                <div class="mt-14">
                    <div x-cloak x-show="activeTab === 'whatsapp'" class="w-[80%] text-3xl font-extrabold space-y-2">
                        <h1 >Butuh bantuan cepat? Hubungi kami via WhatsApp</h1>
                        <h2 class="italic">Respon cepat jam 09.00 – 21.00 WIB</h2>
                    </div>
                    <div x-cloak x-show="activeTab === 'instagram'" class="w-[80%] text-3xl font-extrabold space-y-2">
                        <h1>Ikuti kami di Instagram untuk update terbaru 📷</h1>
                        <h2>DM juga bisa kalau ada pertanyaan 😉</h2>
                    </div>
                </div>
            </div>
        </div>
        <script>
            // di hero-section
            var contactSwiper = new Swiper(".mySwiperContacts", {
                spaceBetween: 0,
                slidesPerView: 3,
                loop: true,
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                },
            });
        </script>
    </div>
</x-home>
