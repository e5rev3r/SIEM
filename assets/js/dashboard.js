/**
 * CentralLog — Dashboard Charts (Chart.js)
 */
document.addEventListener('DOMContentLoaded', function() {
    const data = window.chartData;
    if (!data) return;

    // Color scheme
    const colors = {
        CRITICAL: '#dc3545',
        ERROR:    '#fd7e14',
        WARNING:  '#ffc107',
        INFO:     '#6c757d'
    };

    // Events Per Hour — Line Chart
    const ephCtx = document.getElementById('eventsPerHourChart');
    if (ephCtx) {
        new Chart(ephCtx, {
            type: 'line',
            data: {
                labels: data.eventsPerHour.labels.map(l => l.substring(11, 16)), // "HH:MM"
                datasets: [{
                    label: 'Events',
                    data: data.eventsPerHour.values,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointBackgroundColor: '#0d6efd',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                    x: { grid: { color: 'rgba(255,255,255,0.05)' } }
                }
            }
        });
    }

    // Severity Breakdown — Doughnut
    const sevCtx = document.getElementById('severityChart');
    if (sevCtx) {
        new Chart(sevCtx, {
            type: 'doughnut',
            data: {
                labels: data.severity.labels,
                datasets: [{
                    data: data.severity.values,
                    backgroundColor: data.severity.labels.map(l => colors[l] || '#6c757d'),
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#adb5bd', padding: 15 }
                    }
                }
            }
        });
    }

    // Top IPs — Horizontal Bar
    const ipCtx = document.getElementById('topIPsChart');
    if (ipCtx) {
        new Chart(ipCtx, {
            type: 'bar',
            data: {
                labels: data.topIPs.labels,
                datasets: [{
                    label: 'Events',
                    data: data.topIPs.values,
                    backgroundColor: '#0dcaf0',
                    borderRadius: 4,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    // Auto-refresh every 30 seconds
    setInterval(function() {
        location.reload();
    }, 30000);
});
