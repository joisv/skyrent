<div x-data="{ poster: false, permalink: false, }">
    <form wire:submit="save">
        <x-primary-button type="submit" class="disabled:bg-gray-600" wire:loading.attr="disabled">
            <div class="flex items-center space-x-1 w-full">
                <x-icons.loading wire:loading />
                <h2>
                    Save
                </h2>
            </div>
        </x-primary-button>
        <x-primary-button type="button" class="ml-2" wire:click="test">
            <div class="flex items-center space-x-1 w-full">
                <h2>
                    test
                </h2>
            </div>
        </x-primary-button>
        <button type="button" class="absolute -top-16 -right-5 sm:-right-12 lg:hidden flex bg-blue-500 w-10 sm:w-20 p-1"
            @click="toggleSetting">
            <x-icons.setting />
        </button>
        <div class="flex w-full space-x-2">
            <div class="w-full space-y-3">
                <div>
                    <div class="w-full">
                        <input type="text"
                            class="border-x-0 border-t-0 w-full placeholder:text-gray-400 border-b-2 border-b-gray-300 focus:ring-0 py-2 px-1 focus:border-t-0 focus:border-b-red-500 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:placeholder-gray-400 dark:focus:border-blue-500"
                            placeholder="iPhone 16 pro MAX" id="name" wire:model="name" x-on:blur="$dispatch('setslug')">
                        @error('name')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div wire:ignore class=" prose-base lg:prose-lg prose-code:text-rose-500 prose-a:text-blue-600">
                    <div id="summernote" ></div>
                </div>
            </div>
            {{-- Setting series --}}
            <div id="series_setting"
                class="fixed lg:translate-x-0 right-0 w-full sm:w-[35vw] lg:w-[24vw] xl:w-[23vw] bg-white p-5 rounded-sm top-0 h-screen space-y-4 overflow-y-auto ease-in duration-100">
                <div class="flex items-center space-x-2">
                    <button type="button" class="z-50 lg:hidden flex " @click="toggleSetting">
                        <x-icons.setting default="#000000" />
                    </button>
                    <h1
                        class="text-base font-semibold text-gray-700 hover:text-blue-700 ease-in duration-100 cursor-pointer">
                        Setelan Series</h1>
                </div>
                {{-- Poster --}}
                <div class="space-y-2 pb-2" :class="poster ? 'border-b border-gray-400' : ''" x-cloak>
                    <div :class="!poster ? ' border-b border-b-gray-400' : ''">
                        <button type="button" @click="poster = ! poster"
                            class="flex space-x-4 gray w-full py-2 cursor-pointer">
                            <div>
                                <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none"
                                    class="ease-in duration-200" :class="poster ? 'rotate-180' : ''"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                    <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round">
                                    </g>
                                    <g id="SVGRepo_iconCarrier">
                                        <path
                                            d="M15 11L12.2121 13.7879C12.095 13.905 11.905 13.905 11.7879 13.7879L9 11M7 21H17C19.2091 21 21 19.2091 21 17V7C21 4.79086 19.2091 3 17 3H7C4.79086 3 3 4.79086 3 7V17C3 19.2091 4.79086 21 7 21Z"
                                            stroke="rgb(31, 41, 55)" stroke-width="2" stroke-linecap="round">
                                        </path>
                                    </g>
                                </svg>
                            </div>
                            <div class="text-start w-full">
                                <x-input-label for="poster">Poster</x-input-label>
                                @error('gallery_id')
                                    <span class="error">{{ $message }}</span>
                                @enderror
                                @if ($urlPoster)
                                    <div class="w-full relative">
                                        <img src="{{ asset('storage/' . $urlPoster) }}" alt=""
                                            srcset="" class="w-1/2 h-20 object-contain object-left">
                                        <div wire:click="removePoster"
                                            class="absolute h-5 w-5 rounded-lg flex items-center justify-center -top-3 right-1/2 bg-gray-400 text-white hover:bg-rose-500">
                                            x</div>
                                    </div>
                                @endif
                            </div>
                        </button>
                        <div x-show="poster" x-collapse>
                            <x-primary-button type="button" x-data
                                x-on:click="$dispatch('open-modal', 'add-poster')">{{ $urlPoster ? 'change poster' : 'add poster' }}</x-primary-button>
                        </div>
                    </div>
                </div>
                 @error('gallery_id')
                    <span class="error">{{ $message }}</span>
                @enderror
                {{-- Date picker --}}
                <livewire:iphones.set-date wire:model="date" :isEdit="true" />
                 {{-- Permalink --}}
                <livewire:iphones.set-slug wire:model="slug" />
                @error('slug')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </form>
    <script>
        window.addEventListener('livewire:init', function() {

            $('#summernote').summernote({
                tabsize: 2,
                height: 500, // set editor height
                minHeight: null, // set minimum height of editor
                maxHeight: null, // set maximum height of editor
                focus: true,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    //   ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                 callbacks: {
                    onInit: function() {
                        $('#summernote').summernote('code', @json($description));
                        $('.note-group-select-from-files').first().remove();
                    },
                    onChange: function(contents, $editable) {
                        @this.set('description', contents, true);
                    }
                }
            });

        })
    </script>
    <x-modal name="add-poster" :show="$errors->isNotEmpty()">
       <livewire:galleries.gallery />
    </x-modal>
</div>
