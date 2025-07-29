 <div class="w-[85%]">
     @if ($errors->any())
         <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4">
             <ul class="list-disc list-inside">
                 @foreach ($errors->all() as $error)
                     <li>{{ $error }}</li>
                 @endforeach
             </ul>
         </div>
     @endif
     <form wire:submit="save">
         <div class="space-y-2">
             <x-mary-rating wire:model="rating" class="bg-warning" total="5" />
             <x-mary-textarea wire:model="comment" placeholder="Here ..." hint="Max 1000 chars" rows="5" />
         </div>
         <div class="flex justify-end">
             <button wire:loading.attr="disabled" type="submit"
                 class="py-1 px-4 bg-black disabled:bg-gray-300 text-white hover:bg-white hover:text-black border-2 border-black duration-150 transition-all ease-in">posting</button>
         </div>
     </form>
     <div>
         <div>
             <h1 class="text-3xl font-semibold">Reviews</h1>
         </div>
         <div class="space-y-8 mt-7">
             @foreach ($reviews as $review)
                 <div class="space-y-3">
                     <div class="flex justify-start items-start space-x-4">
                         <div class="w-[40px] h-[40px] flex-none rounded-full overflow-hidden bg-red-500">
                             <img src="https://placehold.co/600x400" alt="" srcset=""
                                 class="object-cover w-full h-full">
                         </div>
                         <div class="space-y-2">
                             <div>
                                 <h3 class="font-semibold text-lg text-gray-600">{{ $review->name }}</h3>
                                 <p class="text-sm text-gray-600 font-medium">{{ $review->created_at->diffForHumans() }}</p>
                             </div>
                              <x-star-rating :rating="$review->rating" />
                             <p class="text-sm">{{ $review->comment }}</p>
                         </div>
                     </div>
                 </div>
             @endforeach
         </div>
     </div>
 </div>
