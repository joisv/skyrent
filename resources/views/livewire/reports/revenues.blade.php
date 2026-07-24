<div x-data class="">
    <div class="flex justify-between">

        <div class="w-[65%] rounded-2xl border bg-white p-6 shadow">
            <div class="flex items-center justify-between mb-5">

                <button wire:click="previousMonth" class="rounded-lg p-2 hover:bg-gray-100">
                    ←
                </button>

                <h2 class="font-semibold text-lg">
                    {{ \Carbon\Carbon::create($year, $month)->translatedFormat('F Y') }}
                </h2>

                <button wire:click="nextMonth" class="rounded-lg p-2 hover:bg-gray-100">
                    →
                </button>

            </div>

            <div class="grid grid-cols-7 gap-2 text-center text-sm text-gray-500 mb-3">

                <div>Sen</div>
                <div>Sel</div>
                <div>Rab</div>
                <div>Kam</div>
                <div>Jum</div>
                <div>Sab</div>
                <div>Min</div>

            </div>

            <div class="grid grid-cols-7 gap-2">

                @foreach ($this->calendar as $day)
                    @php

                        $selected = false;

                        if ($startDate && !$endDate) {
                            $selected = $day['date'] == $startDate;
                        }

                        if ($startDate && $endDate) {
                            $selected = $day['date'] >= $startDate && $day['date'] <= $endDate;
                        }

                    @endphp

                    <button @disabled($day['disabled']) wire:click="selectDate('{{ $day['date'] }}')"
                        class="h-11 rounded-lg transition text-sm
        
            {{ !$day['currentMonth'] ? 'text-gray-400' : '' }}
        
            {{ $day['today'] ? 'border border-orange-500' : '' }}
        
            {{ $day['disabled'] ? ' text-gray-300' : '' }}
        
            {{ $selected ? 'bg-orange-500 text-white' : (!$day['disabled'] ? 'hover:bg-orange-100' : '') }}">
                        {{ $day['day'] }}
                    </button>
                @endforeach

            </div>
        </div>

        <div class="w-[30%]">
            <div>
                <h1 class="text-xl font-medium">Pembayaran</h1>
                <p class="text-lg font-light">jumlah pembayaran pada tanggal yang dipilih</p>
            </div>
            <div id="payment-chart" wire:ignore></div>
        </div>
    </div>
    {{-- Apexxx --}}
    <div class=" flex justify-between">
        <div class="w-[75%] mt-10">
            <div class="w-full">
                <div>
                    <h1 class="text-xl font-medium">Booking Berdasarkan Tanggal</h1>
                    <p class="text-lg font-light">Jumlah booking berdasarkan tanggal pada periode yang dipilih.</p>
                </div>
                <div id="booking-chart" wire:ignore></div>
            </div>
        </div>
        <div class="w-[20%] space-y-2">
            <x-mary-stat title="Hari Ini" description="{{ now()->format('d F Y') }}"
                value="Rp {{ number_format($revenueToday, 0, ',', '.') }}" icon="o-banknotes" color="text-green-600" />
            <button type="button" wire:click="getDetailRevenue">
                <x-mary-stat title="Pendapatan periode" value="Rp {{ number_format($totalIncome, 0, ',', '.') }}"
                    icon="o-currency-dollar" color="text-primary" class="shadow-md"
                    description="{{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }} - {{ \Carbon\Carbon::parse($endDate ?? $startDate)->translatedFormat('d F Y') }}" />
            </button>

            {{-- Pendapatan Bulan Ini --}}
            <x-mary-stat title="Bulan Ini" description="{{ now()->format('F Y') }}"
                value="Rp {{ number_format($revenueThisMonth, 0, ',', '.') }}" icon="o-chart-bar"
                color="text-blue-600" />

            {{-- Total Revenue --}}
            <x-mary-stat title="Total Semua Waktu" value="Rp {{ number_format($revenueTotal, 0, ',', '.') }}"
                icon="o-currency-dollar" color="text-purple-600" />

            {{-- Persentase Kenaikan/Penurunan Bulan Ini --}}
            <x-mary-stat title="Perubahan Bulanan"
                description="{{ $revenueThisMonthPercentage >= 0 ? 'Kenaikan' : 'Penurunan' }}"
                value="{{ $revenueThisMonthPercentage }}%"
                icon="{{ $revenueThisMonthPercentage >= 0 ? 'o-arrow-trending-up' : 'o-arrow-trending-down' }}"
                color="{{ $revenueThisMonthPercentage >= 0 ? 'text-green-600' : 'text-red-600' }}"
                tooltip-right="Dibanding bulan lalu" />
        </div>

    </div>
    {{-- datatables --}}
    <div class="w-full ">
        <div class="mt-10">
            <x-tables.table name="Pendapatan" count="">
                <x-slot name="secondBtn">
                    <button
                        class="flex items-center justify-center w-1/2 px-5 py-2 text-sm disabled:text-gray-700 transition-colors duration-200 disabled:bg-white border rounded-lg gap-x-2 sm:w-auto dark:hover:bg-gray-800 dark:bg-gray-900 hover:bg-gray-100 dark:text-gray-200 dark:border-gray-700 bg-red-500 text-white"
                        wire:click="destroyAlert" @if (!$mySelected) disabled @endif>
                        <span>Bulk delete</span>
                    </button>
                </x-slot>
                <x-slot name="addBtn">
                    {{-- <x-tables.addbtn type="button" x-data="" @click="window.location.href = ''">
            Add iPhone
        </x-tables.addbtn> --}}
                </x-slot>
                <x-slot name="sort">
                    <div class="flex items-center space-x-2 w-1/2 ">
                        <div class="w-fit">
                            <select id="sort_series"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 px-5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                wire:model.live="paginate">
                                <option value="10">10</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="150">150</option>
                            </select>
                        </div>
                        <div class="w-fit">
                            <select id="sort"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 px-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                wire:model.live="sortField">
                                <option value="">Urutkan berdasarkan</option>
                                <option value="amount">Jumlah Pendapatan</option>
                                <option value="created">Tanggal Dibuat</option>
                                <option value="updated_at">Terakhir Diperbarui</option>
                            </select>

                        </div>
                    </div>
                </x-slot>
                <x-slot name="search">
                    <x-search wire:model.live.debounce.500ms="search" />
                </x-slot>
                <x-slot name="thead">
                    <x-tables.th>
                        <input id="selectedAll"
                            type="checkbox"class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                            wire:model.live="selectedAll">
                        {{-- <input type="hidden" wire:model.live="firstId" value="{{ $serieses[0]->id }}"> --}}
                    </x-tables.th>
                    <x-tables.th>Tanggal dibuat</x-tables.th>
                    <x-tables.th>Kode Booking</x-tables.th>
                    <x-tables.th>Customer</x-tables.th>
                    <x-tables.th>Tipe iPhone</x-tables.th>
                    <x-tables.th>Pembayaran</x-tables.th>
                    <x-tables.th>Tipe Pembayaran</x-tables.th>
                    <x-tables.th>Nominal</x-tables.th>
                    <x-tables.th>Dibayar</x-tables.th>
                    <x-tables.th>Kembalian</x-tables.th>
                    <x-tables.th>Dibuat</x-tables.th>
                    {{-- <x-tables.th>Aksi</x-tables.th> --}}

                </x-slot>
                <x-slot name="tbody">
                    @foreach ($paymentsList as $index => $payment)
                        <tr>
                            <x-tables.td>
                                <input id="default-{{ $index }}" type="checkbox"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    wire:model.live="mySelected" value="{{ $payment->id }}">
                            </x-tables.td>

                            <x-tables.td>
                                {{ $payment->paid_at->format('d/m/Y H:i') }}
                            </x-tables.td>

                            <x-tables.td>{{ $payment->booking->booking_code }}</x-tables.td>

                            <x-tables.td>{{ $payment->booking->customer_name }}</x-tables.td>

                            <x-tables.td>{{ $payment->booking->iphone->name }}</x-tables.td>

                            <x-tables.td>{{ $payment->payment->name }}</x-tables.td>

                            <x-tables.td>
                                <x-mary-badge :value="ucfirst($payment->type)" />
                            </x-tables.td>

                            <x-tables.td>
                                Rp {{ number_format($payment->amount, 0, ',', '.') }}
                            </x-tables.td>

                            <x-tables.td>
                                Rp {{ number_format($payment->pay, 0, ',', '.') }}
                            </x-tables.td>

                            <x-tables.td>
                                Rp {{ number_format($payment->change, 0, ',', '.') }}
                            </x-tables.td>

                            <x-tables.td>{{ $payment->user->name }}</x-tables.td>

                        </tr>
                    @endforeach

                </x-slot>
            </x-tables.table>
        </div>
    </div>
    <x-modal name="detail-revenue" :show="$errors->isNotEmpty()">

    </x-modal>
    @script
        <script>
            document.addEventListener('livewire:initialized', () => {

                let paymentChart = new ApexCharts(
                    document.querySelector("#payment-chart"), {
                        chart: {
                            type: 'pie',
                            height: 350
                        },

                        labels: @js($paymentChart['labels'] ?? []),

                        series: @js($paymentChart['series'] ?? []),
                        colors: [
                            '#f97316', // orange-500
                            '#3b82f6', // blue-500
                            '#22c55e', // green-500
                            '#eab308', // yellow-500
                            '#ef4444', // red-500
                            '#a855f7', // purple-500
                        ],
                        noData: {
                            text: 'Tidak ada data',
                            align: 'center',
                            verticalAlign: 'middle',
                            style: {
                                fontSize: '16px'
                            }
                        },

                        legend: {
                            position: 'bottom'
                        },

                        dataLabels: {
                            enabled: true
                        }
                    }
                );

                paymentChart.render();

                Livewire.on('refresh-payment-chart', (e) => {
                    paymentChart.updateOptions({
                        labels: e.labels
                    });

                    paymentChart.updateSeries(e.series);

                });

                var options = {
                    series: [{
                        name: 'Booking',
                        data: @js($bookingChart['series'] ?? [])
                    }],
                    chart: {
                        type: 'bar',
                        height: 350
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '55%',
                            endingShape: 'rounded'
                        },
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: @js($bookingChart['categories'] ?? []),
                    },
                    yaxis: {
                        title: {
                            text: 'Booking'
                        }
                    },
                    fill: {
                        opacity: 1,
                        colors: ['#f97316']
                    },
                };

                let chart = new ApexCharts(document.querySelector("#booking-chart"), options);
                chart.render();

                Livewire.on('refresh-chart-data', (chartData) => {
                    chart.updateOptions({
                        xaxis: {
                            categories: chartData['categories'],
                        }
                    });
                    console.log(chartData['series'])
                    chart.updateSeries([{
                        name: 'Booking',
                        data: chartData['series'],
                    }]);
                });
            });
        </script>
    @endscript
</div>
