<div x-data class="">
    @dump($this->startDate, $this->endDate);
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
    
        {{-- Apexxx --}}
        {{-- <div id="booking-chart" wire:ignore></div> --}}
        <div class="bg-red-500">
            <div id="payment-chart" wire:ignore></div>
        </div>
    </div>
    @script
        {{-- <script>
            document.addEventListener('livewire:initialized', () => {
                // console.log(@js($bookingChart));
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
                            text: 'Revenue'
                        }
                    },
                    fill: {
                        opacity: 1,
                        colors: ['#059669']
                    },
                    tooltip: {
                        y: {
                            formatter: function(val) {
                                return new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR',
                                    maximumFractionDigits: 0
                                }).format(val);
                            }
                        }
                    }
                };

                var chart = new ApexCharts(document.querySelector("#booking-chart"), options);
                chart.render();

                Livewire.on('refresh-chart-data', (chartData) => {
                    console.log(chartData.categories);
                    chart.updateOptions({
                        xaxis: {
                            categories: chartData['categories'],
                        }
                    });

                    chart.updateSeries([{
                        data: chartData['series'],
                    }]);
                });
            });
        </script> --}}
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
                    console.log(e);
                    paymentChart.updateOptions({
                        labels: e.labels
                    });

                    paymentChart.updateSeries(e.series);

                });

            });
        </script>
    @endscript
</div>
