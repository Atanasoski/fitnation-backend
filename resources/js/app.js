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

// Initialize components on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // FilePond (must run before user can submit forms with file inputs)
    import('./components/filepond-init').then((module) => {
        module.initFilePond();
    });

    // Chart imports
    if (document.querySelector('#userProgressChart')) {
        import('./components/chart/user-progress').then(module => module.initUserProgressChart());
    }
});
