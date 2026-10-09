import './bootstrap';
import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import insightChart from './components/chart/insight-chart';

// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;

Alpine.data('insightChart', insightChart);

Alpine.start();
