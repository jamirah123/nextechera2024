import Chart from 'chart.js/auto';
import Alpine from 'alpinejs';

export function registerDashboardCharts() {
    Alpine.data('dashboardCharts', () => ({
        charts: [],
        instances: {},

        mount(configs) {
            this.charts = configs || [];
            this.$nextTick(() => this.renderAll());
        },

        renderAll() {
            this.charts.forEach((chart) => {
                const canvas = document.getElementById(`chart-${chart.id}`);

                if (!canvas) {
                    return;
                }

                if (this.instances[chart.id]) {
                    this.instances[chart.id].destroy();
                }

                const isCircular = chart.type === 'doughnut' || chart.type === 'pie';

                this.instances[chart.id] = new Chart(canvas, {
                    type: chart.type,
                    data: {
                        labels: chart.labels,
                        datasets: chart.datasets,
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: isCircular ? 'bottom' : 'top',
                                labels: {
                                    boxWidth: 12,
                                    font: { size: 11 },
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label(context) {
                                        const label = context.dataset.label || '';
                                        const value = context.parsed.y ?? context.parsed;

                                        if (chart.id === 'collections-trend') {
                                            return `${label}: ${Number(value).toLocaleString(undefined, {
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })}`;
                                        }

                                        return `${label}: ${value}`;
                                    },
                                },
                            },
                        },
                        scales: isCircular
                            ? {}
                            : {
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { size: 10 } },
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0,
                                        font: { size: 10 },
                                    },
                                },
                            },
                    },
                });
            });
        },
    }));
}
