<x-home>
    <div class="flex space-x-3 min-h-screen w-full mt-20">
        <div class="w-[70%] flex space-x-3 ">
            <div class="w-[45%] h-[50vh] relative">
                <img src="{{ url('61d2f93392b57c0004c64747 1.png') }}" alt="" srcset=""
                    class="w-full h-full object-contain absolute">
            </div>
            <div class="w-1/2">
                <h1 class="text-3xl font-semibold">iPhone 12 pro MAX</h1>
                <span>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Odio quod adipisci, ea provident quisquam fugit earum nesciunt harum. Pariatur magnam quae dolore, enim eligendi soluta.</span>
            </div>
        </div>
        <div class="w-[30%] h-fit border-2 border-slate-900 p-5">
            <div class="space-y-4">
                <div class="space-y-3">
                    <h1 class="text-2xl font-semibold ">Durasi Sewa</h1>
                    <div class="flex space-x-2 w-full">
                        <div class="p-2 bg-slate-900 text-white w-full font-semibold text-center">
                            <h3>24 Jam</h3>
                        </div>
                        <div class="p-2 border-2 border-slate-900 w-full font-semibold text-center">
                            <h3>12 Jam</h3>
                        </div>
                        <div class="p-2 border-2 border-slate-900 w-full font-semibold text-center">
                            <h3>6 Jam</h3>
                        </div>
                    </div>
                    <div class="border-2 border-slate-900 p-2 w-fit font-semibold">
                        <h1>Durasi custom</h1>
                    </div>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold ">Status</h1>
                    <div class="relative">
                        <div class="bg-green-200 p-2 w-fit">
                            <h1 class="text-green-600 font-semibold">tersedia</h1>
                        </div>
                        <div class="text-sm font-semibold text-slate-500 absolute">
                            <p>tersedia untuk hari ini</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-14 space-y-2">
                <span class="text-2xl font-bold">Rp 85.000.00</span>
                <div class="flex justify-between items-center space-x-4 bg-white text-black text-xl p-3 font-semibold group overflow-hidden cursor-pointer border-2 border-slate-900 p-3">
                        <h1 class="text-xl font-bold">
                            Sewa Sekarang
                        </h1>
                        <div class="w-fit h-full group-hover:translate-x-24 transition duration-200 ease-in-out">
                            <x-icons.arrow1 />
                        </div>
                    </div>
            </div>
        </div>
    </div>
</x-home>
