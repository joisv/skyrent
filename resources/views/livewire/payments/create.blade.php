<div class="p-3">
    {{-- Care about people's approval and you will be their prisoner. --}}
    <div>
        <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Nama
            customer</label>
        <input type="text" id="name"
            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
            placeholder="John" wire:model="name" />
        @error('name')
            <span class="error">Nama tida bole kosong 😒</span>
        @enderror
    </div>
</div>
