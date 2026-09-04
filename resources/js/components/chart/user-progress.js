export const initUserProgressChart = () => {
    const chartElement = document.querySelector('#userProgressChart');

    if (!chartElement) {
        return;
    }

    // Get data from data attribute or JSON script tag
    let chartData = [];
    let chartLabels = [];

    // Try to get data from data attribute
    const dataAttribute = chartElement.getAttribute('data-chart-data');
    if (dataAttribute) {
        try {
            const parsed = JSON.parse(dataAttribute);
            chartData = parsed.map((item) => item.count);
            chartLabels = parsed.map((item) => item.label);
        } catch (e) {
            console.error('Error parsing chart data:', e);
        }
    }

    // Fallback: try to get from JSON script tag
    if (chartData.length === 0) {
        const dataScript = document.querySelector('#userProgressChartData');
        if (dataScript) {
            try {
                const parsed = JSON.parse(dataScript.textContent);
                chartData = parsed.map((item) => item.count);
                chartLabels = parsed.map((item) => item.label);
            } catch (e) {
                console.error('Error parsing chart data from script:', e);
            }
        }
    }

    if (chartData.length === 0) {
        console.warn('No chart data available');
        return;
    }

    // Check if dark mode is active
    const isDarkMode = document.documentElement.classList.contains('dark') ||
        window.matchMedia('(prefers-color-scheme: dark)').matches;

    const chartOptions = {
        series: [
            {
                name: 'Workouts',
                data: chartData,
            },
        ],
        colors: ['#14b3c1'],
        chart: {
            fontFamily: 'Outfit, sans-serif',
            height: 310,
            type: 'area',
            width: '100%',
            toolbar: {
                show: false,
            },
            zoom: {
                enabled: false,
            },
            selection: {
                enabled: false,
            },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 800,
            },
        },
        fill: {
            gradient: {
                enabled: true,
                opacityFrom: 0.55,
                opacityTo: 0,
                stops: [0, 100],
            },
        },
        stroke: {
            curve: 'smooth',
            width: 2,
        },
        markers: {
            size: 4,
            strokeWidth: 2,
            strokeColors: ['#14b3c1'],
            hover: {
                size: 6,
            },
        },
        grid: {
            xaxis: {
                lines: {
                    show: false,
                },
            },
            yaxis: {
                lines: {
                    show: true,
                    opacity: isDarkMode ? 0.1 : 0.3,
                },
            },
            padding: {
                top: 0,
                right: 0,
                bottom: 0,
                left: 0,
            },
        },
        dataLabels: {
            enabled: false,
        },
        tooltip: {
            enabled: true,
            theme: isDarkMode ? 'dark' : 'light',
            style: {
                fontSize: '12px',
                fontFamily: 'Outfit, sans-serif',
            },
            y: {
                formatter: function (val) {
                    return val + (val === 1 ? ' workout' : ' workouts');
                },
            },
        },
        xaxis: {
            categories: chartLabels,
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            tooltip: {
                enabled: false,
            },
            labels: {
                style: {
                    colors: isDarkMode ? '#9CA3AF' : '#6B7280',
                    fontSize: '12px',
                    fontFamily: 'Outfit, sans-serif',
                },
            },
        },
        yaxis: {
            title: {
                text: 'Workouts',
                style: {
                    color: isDarkMode ? '#9CA3AF' : '#6B7280',
                    fontSize: '12px',
                    fontFamily: 'Outfit, sans-serif',
                },
            },
            labels: {
                style: {
                    colors: isDarkMode ? '#9CA3AF' : '#6B7280',
                    fontSize: '12px',
                    fontFamily: 'Outfit, sans-serif',
                },
                formatter: function (val) {
                    return Math.round(val);
                },
            },
            min: 0,
            forceNiceScale: true,
        },
        legend: {
            show: false,
        },
    };

    const chart = new ApexCharts(chartElement, chartOptions);
    chart.render();

    // Update chart on dark mode toggle
    const observer = new MutationObserver(() => {
        const isDark = document.documentElement.classList.contains('dark');
        chart.updateOptions({
            grid: {
                yaxis: {
                    lines: {
                        opacity: isDark ? 0.1 : 0.3,
                    },
                },
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
            },
            xaxis: {
                labels: {
                    style: {
                        colors: isDark ? '#9CA3AF' : '#6B7280',
                    },
                },
            },
            yaxis: {
                title: {
                    style: {
                        color: isDark ? '#9CA3AF' : '#6B7280',
                    },
                },
                labels: {
                    style: {
                        colors: isDark ? '#9CA3AF' : '#6B7280',
                    },
                },
            },
        });
    });

    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });

    return chart;
};

export default initUserProgressChart;
