/**
 * Megabre StokMaster Pro
 * Dashboard JavaScript
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

document.addEventListener('DOMContentLoaded', function() {
    // Update current time
    function updateTime() {
        const now = new Date();
        const options = { hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: false };
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('tr-TR', options);
    }
    
    // Update time every second
    updateTime();
    setInterval(updateTime, 1000);
    
    // Initialize charts when Chart.js is loaded
    if (typeof Chart !== 'undefined') {
        // Monthly Sales Chart
        const salesChart = new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'],
                datasets: [{
                    label: 'Satışlar',
                    data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Aylık Satış Grafiği'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('tr-TR') + ' ₺';
                            }
                        }
                    }
                }
            }
        });
        
        // Stock Status Chart
        const stockChart = new Chart(document.getElementById('stockChart'), {
            type: 'doughnut',
            data: {
                labels: ['Stokta', 'Kritik', 'Tükendi'],
                datasets: [{
                    data: [0, 0, 0],
                    backgroundColor: [
                        '#2ecc71',
                        '#f1c40f',
                        '#e74c3c'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: true,
                        text: 'Stok Durumu'
                    }
                }
            }
        });
        
        // Load chart data via AJAX
        fetch('index.php?module=dashboard&action=get-chart-data')
            .then(response => response.json())
            .then(data => {
                // Update sales chart
                salesChart.data.datasets[0].data = data.monthlySales;
                salesChart.update();
                
                // Update stock chart
                stockChart.data.datasets[0].data = [
                    data.stockStatus.inStock,
                    data.stockStatus.critical,
                    data.stockStatus.outOfStock
                ];
                stockChart.update();
            })
            .catch(error => console.error('Error loading chart data:', error));
    }
}); 