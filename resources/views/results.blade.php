<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="A/B Results" details="{{ $feature }}">
        <x-slot:icon>
            <x-pulse::icons.scale />
        </x-slot:icon>
        <x-slot:actions>
            @if($type === 'total')
            <small>These are the results over the entire lifetime of the feature</small>
            @endif
        </x-slot:actions>
    </x-pulse::card-header>

    <div class="grid gap-3 mx-px mb-px">
        <div class="mt-3 relative">
            <x-pulse::select
                wire:model.live="type"
                label="Chart type"
                :options="[
                    'total' => 'total conversions',
                    'timeseries' => 'conversions over time',
                ]"
                class="flex-1"
                @change="loading = true"
            />
        </div>
        <div class="mt-3 relative">
            <div 
                class="h-48" 
                x-data="flagpostResultChart({
                    type: '{{ $type }}',
                    results: {{ Js::from($results->all()) }}
                })"
            >
                <canvas x-ref="canvas" class="ring-1 ring-gray-900/5 dark:ring-gray-100/10 bg-gray-50 dark:bg-gray-800 rounded-md shadow-sm"></canvas>
            </div>
        </div>
    </div>
</x-pulse::card>

@script
<script>
let chart = null;
Alpine.data('flagpostResultChart', (config) => ({
    init() {

        /*
         * Initialise charts based on the results type.
         * TODO: This needs a big-ol cleanup, and perhaps just stored in separate components
         */
        if (chart !== null) {
            chart.destroy();
        }

        let datasets = null;
        if (config.type === 'total') {
            datasets = config.results.map(item => ({
                label: item.value,
                data: [{ y: item.value, x: item.converted }]
            }));
        } else if (config.type === 'timeseries') {
            let keys = Object.keys(config.results);
            datasets = keys.map(key => ({
                label: key,
                data: config.results[key].reduce((a, v) => ({
                    ...a,
                    [v.time]: v.converted
                }), {})
            }));
        }

        type = config.type;
        chart = new Chart(
            this.$refs.canvas,
            {
                type: config.type === 'total' ? 'bar' : 'line',
                data: {
                    datasets: datasets,
                    //labels: config.results.map(item => item.value)
                },
                options: {
                    indexAxis: config.type === 'total' ? 'y' : 'x',
                    maintainAspectRatio: false,
                    layout: {
                        autoPadding: false,
                        padding: 15
                    },
                    datasets: {
                        bar: {
                            barThickness: 5,
                            borderWidth: 0,
                            borderCapstyle: 'round',
                            borderRadius: 2,
                            borderSkipped: false,
                            barPercentage: 1,
                            categoryPercentage: 1,
                        }
                    },
                    scales: {
                        y: { display: false}
                    }
                },
            }
        );
    }
}))
</script>
@endscript
