import ApexCharts from 'apexcharts';

/**
 * insightChart — the one bar-chart setup every Insights card uses.
 *
 * Usage: <div x-data="insightChart(@js($spec))"></div>, where $spec is
 *   categories   x labels (a label may be an array of lines)
 *   series       [{ name, data }]; a null value draws no bar
 *   colors       swatch names per series (or per bar when distributed);
 *                defaults to s1, s2, s3
 *   prefix       put before value labels and the value axis ('$')
 *   suffix       appended to value labels and the value axis ('%')
 *   max          value-axis maximum
 *   horizontal, stacked, distributed
 *
 * Colours come from the page's [data-chart-swatch] elements, which carry
 * brand tokens with their dark-mode steps, so no hex lives here. Value
 * labels are always shown: light-mode teal and orange are under 3:1 on
 * white, so the bar colour alone cannot carry the number. Charts re-theme
 * when the dark toggle flips the class on <html>.
 */
export default function insightChart(spec) {
    return {
        chart: null,
        observer: null,

        init() {
            this.chart = new ApexCharts(this.$el, options(spec));
            this.chart.render();
            this.observer = new MutationObserver(() => this.chart.updateOptions(options(spec), false, false));
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },

        destroy() {
            this.observer?.disconnect();
            this.chart?.destroy();
        },
    };
}

function swatch(name) {
    const el = document.querySelector(`[data-chart-swatch="${name}"]`);

    return el ? getComputedStyle(el).color : undefined;
}

function options(spec) {
    const dark = document.documentElement.classList.contains('dark');
    const prefix = spec.prefix ?? '';
    const suffix = spec.suffix ?? '';
    const format = (v) => (v === null || v === undefined ? '' : `${prefix}${Number(v).toLocaleString()}${suffix}`);
    const horizontal = Boolean(spec.horizontal);
    const many = spec.series.length > 1;
    const valueAxis = { max: spec.max, labels: { formatter: format } };

    return {
        chart: {
            type: 'bar',
            height: '100%',
            stacked: Boolean(spec.stacked),
            fontFamily: 'Inter, sans-serif',
            foreColor: swatch('ink'),
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: false },
        },
        series: spec.series,
        colors: (spec.colors ?? ['s1', 's2', 's3']).map(swatch),
        plotOptions: {
            bar: {
                horizontal,
                distributed: Boolean(spec.distributed),
                borderRadius: 4,
                borderRadiusApplication: 'end',
                columnWidth: many ? '55%' : '40%',
                barHeight: '60%',
                dataLabels: { position: spec.stacked ? 'center' : 'top' },
            },
        },
        dataLabels: {
            enabled: true,
            // An empty stacked segment would still print its 0.
            formatter: (v) => (spec.stacked && !v ? '' : format(v)),
            offsetX: horizontal && !spec.stacked ? 28 : 0,
            offsetY: horizontal || spec.stacked ? 0 : -20,
            style: { colors: [swatch('label')], fontWeight: 500, fontSize: '12px' },
        },
        stroke: { show: many, width: 2, colors: ['transparent'] },
        xaxis: {
            categories: spec.categories,
            ...(horizontal ? valueAxis : {}),
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: horizontal ? {} : valueAxis,
        grid: { borderColor: swatch('grid'), strokeDashArray: 0, padding: { right: horizontal ? 32 : 0 } },
        legend: { show: many && !spec.distributed, position: 'top', horizontalAlign: 'left', fontSize: '12px', markers: { size: 5 } },
        tooltip: { theme: dark ? 'dark' : 'light', y: { formatter: format } },
        theme: { mode: dark ? 'dark' : 'light' },
        states: { active: { filter: { type: 'none' } } },
    };
}
