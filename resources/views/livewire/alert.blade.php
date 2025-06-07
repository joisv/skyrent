<div
    x-data="{ show: @entangle('visible') }"
    x-show="show"
    x-transition
    class="fixed top-4 right-4 z-50 max-w-sm w-full px-4 py-3 rounded shadow-lg text-white"
    :class="{
        'bg-green-500': @entangle('type') === 'success',
        'bg-red-500': @entangle('type') === 'error',
        'bg-yellow-500': @entangle('type') === 'warning'
    }"
>
    <div class="flex justify-between items-center bg-green-500">
        <span x-text="@entangle('message')"></span>
        <button @click="$wire.closeAlert()" class="ml-4 text-white font-bold">×</button>
    </div>
</div>
