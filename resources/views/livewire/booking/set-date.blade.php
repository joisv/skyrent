@php
    if ($isEdit) {
        $finalValue = \Carbon\Carbon::createFromFormat('Y-m-d', $value);
        if ($finalValue !== false) {
            $inicialDate = $finalValue->format('F j, Y');
        }
    } else {
        $inicialDate = $value->format('F j, Y');
    }
@endphp

<div :class="!date ? 'border-b border-b-gray-400' : ''" x-data="{
    date: true,
    currentDate: '',
    dataDate: @entangle('value'),
    setDate: true,
    flatpickrInstance: null,
    {{-- dateInit: @js($inicialDate), --}}
    start_time: null,

    init() {
        let flat = document.querySelector('#flatpickr')
        this.flatpickrInstance = flatpickr(flat, {
            enableTime: true, // Aktifkan jam
            time_24hr: true, // Opsional: gunakan format 24 jam (ubah ke false untuk AM/PM)
            altInput: true,
            altFormat: 'F j, Y',
            dateFormat: 'Y-m-d',
            defaultDate: @js($value),
            onChange: (selectedDates, dateStr, instance) => {
                $wire.date = dateStr;
                let result = this.convertToCustomFormat(selectedDates);
                this.dateInit = result.date; // Update tanggal yang ditampilkan
                this.start_time = result.time; // Update waktu yang ditampilkan
            }
        })
    },

    convertToCustomFormat(inputDateString) {
        const dateObj = new Date(inputDateString); // Konversi string menjadi objek Date

        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        const day = dateObj.getDate(); // Tanggal
        const monthName = monthNames[dateObj.getMonth()]; // Nama bulan
        const year = dateObj.getFullYear(); // Tahun

        const hours = String(dateObj.getHours()).padStart(2, '0'); // Jam (24 jam)
        const minutes = String(dateObj.getMinutes()).padStart(2, '0'); // Menit

        const formattedDate = `${monthName} ${day}, ${year}`; // Contoh: July 19, 2025
        const formattedTime = `${hours}:${minutes}`; // Contoh: 11:38

        return {
            date: formattedDate,
            time: formattedTime
        };
    }
}">
{{ $value }}
    <div x-show="date" x-collapse class="space-y-2">
        <div wire:ignore class="dateShow"
            @inisiasi.window="() => {
            let wrapp = document.querySelector('.dateShow')
            if($event.detail){
                wrapp.classList.add('hidden')
            } else {
                wrapp.classList.remove('hidden')
            }
        }">
            <input id="flatpickr" wire:model="value" wire:ignore type="text" placeholder="YYYY-MM-DD" class="w-full" />
        </div>
    </div>
</div>
