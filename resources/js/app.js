import Chart from 'chart.js/auto';

// Suppress benign ResizeObserver loop errors from polluting logs
window.addEventListener('error', (e) => {
    if (e.message === 'ResizeObserver loop completed with undelivered notifications.') {
        e.stopImmediatePropagation();
    }
});

// Keep the `dark` class on <html> in step with the saved Flux appearance. wire:navigate
// copies the server's <html> attributes over the page, and the server never sends the class
// Flux adds in the browser; back/forward restores snapshots taken in whichever mode was
// active then. The observer stays connected for the whole visit because the progress bar
// also rewrites the class mid-navigation. Corrections run as a microtask, before the next
// paint, and only when the class disagrees, so the observer cannot loop.
const syncAppearance = () => {
    const stored = localStorage.getItem('flux.appearance');
    const shouldBeDark = stored === 'dark'
        || (stored !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    if (document.documentElement.classList.contains('dark') !== shouldBeDark) {
        document.documentElement.classList.toggle('dark', shouldBeDark);
    }
};

// Only pages that run @fluxAppearance (which defines window.Flux in <head>) follow the
// saved appearance; the public welcome page stays as designed.
if (window.Flux) {
    new MutationObserver(syncAppearance).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    document.addEventListener('livewire:navigated', syncAppearance);
}

// x-capitalize: upper-cases the first letter of every word as the user types, so names and
// street lines are entered consistently. Mirrors the server-side CapitalizesWords trait,
// which is what actually guarantees the stored value.
document.addEventListener('alpine:init', () => {
    window.Alpine.directive('capitalize', (el) => {
        el.addEventListener('input', () => {
            const next = el.value.replace(/(?<=^|[\s\-'’.])\p{L}/gu, (char) => char.toUpperCase());

            // Bail before re-dispatching, or the event we fire below loops forever.
            if (next === el.value) {
                return;
            }

            const start = el.selectionStart;
            const end = el.selectionEnd;

            el.value = next;
            el.setSelectionRange(start, end);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
});

// Dashboard charts. Each canvas wrapper declares x-data="chart(config)" with a Chart.js
// type, labels and datasets; colours follow the light/dark theme and the chart is
// rebuilt when Flux toggles the `dark` class.
const CHART_PALETTE = ['#059669', '#0ea5e9', '#f59e0b', '#8b5cf6', '#ef4444', '#14b8a6', '#ec4899', '#64748b'];

document.addEventListener('alpine:init', () => {
    window.Alpine.data('chart', (config) => ({
        chart: null,
        observer: null,

        init() {
            this.render();

            this.observer = new MutationObserver(() => this.render());
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },

        render() {
            this.chart?.destroy();

            const dark = document.documentElement.classList.contains('dark');
            const text = dark ? '#a1a1aa' : '#52525b';
            const grid = dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
            const circular = ['doughnut', 'pie'].includes(config.type);

            const datasets = config.datasets.map((dataset, index) => ({
                borderWidth: circular ? 0 : 2,
                tension: 0.3,
                ...dataset,
                backgroundColor: circular
                    ? CHART_PALETTE
                    : (dataset.color ?? CHART_PALETTE[index % CHART_PALETTE.length]) + (config.type === 'line' ? '33' : ''),
                borderColor: dataset.color ?? CHART_PALETTE[index % CHART_PALETTE.length],
                fill: config.type === 'line',
            }));

            this.chart = new Chart(this.$refs.canvas, {
                type: config.type,
                data: { labels: config.labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: circular || datasets.length > 1, position: 'bottom', labels: { color: text } },
                    },
                    scales: circular ? {} : {
                        x: { ticks: { color: text }, grid: { display: false } },
                        y: { beginAtZero: true, ticks: { color: text, precision: 0 }, grid: { color: grid } },
                    },
                },
            });
        },

        destroy() {
            this.observer?.disconnect();
            this.chart?.destroy();
        },
    }));
});
