/**
 * Megabre StokMaster Pro
 * Chart Utilities
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Chart.js default configuration
if (typeof Chart !== 'undefined') {
    // Set default font family
    Chart.defaults.font.family = "'Segoe UI', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
    
    // Set default font size
    Chart.defaults.font.size = 12;
    
    // Set default font color
    Chart.defaults.color = '#666';
    
    // Disable animations in print mode
    if (window.matchMedia('print').matches) {
        Chart.defaults.animation = false;
    }
    
    // Default colors for charts
    const chartColors = {
        primary: '#3498db',
        secondary: '#2c3e50',
        success: '#2ecc71',
        danger: '#e74c3c',
        warning: '#f39c12',
        info: '#3498db',
        light: '#ecf0f1',
        dark: '#2c3e50',
        // Additional colors for larger datasets
        color1: '#1abc9c',
        color2: '#9b59b6',
        color3: '#f1c40f',
        color4: '#e67e22',
        color5: '#34495e',
        color6: '#16a085',
        color7: '#8e44ad',
        color8: '#d35400',
        color9: '#7f8c8d',
        color10: '#27ae60'
    };
    
    // Function to generate Chart.js colors
    window.getChartColors = function(count) {
        const colors = Object.values(chartColors);
        
        if (count <= colors.length) {
            return colors.slice(0, count);
        }
        
        // If more colors needed than available, generate random colors
        const result = [...colors];
        
        for (let i = colors.length; i < count; i++) {
            const r = Math.floor(Math.random() * 200);
            const g = Math.floor(Math.random() * 200);
            const b = Math.floor(Math.random() * 200);
            result.push(`rgb(${r}, ${g}, ${b})`);
        }
        
        return result;
    };
    
    // Function to create a line chart
    window.createLineChart = function(canvas, data, options) {
        const ctx = document.getElementById(canvas).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                },
                y: {
                    beginAtZero: true
                }
            }
        };
        
        // Merge default options with custom options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'line',
            data: data,
            options: chartOptions
        });
    };
    
    // Function to create a bar chart
    window.createBarChart = function(canvas, data, options) {
        const ctx = document.getElementById(canvas).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                },
                y: {
                    beginAtZero: true
                }
            }
        };
        
        // Merge default options with custom options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'bar',
            data: data,
            options: chartOptions
        });
    };
    
    // Function to create a pie chart
    window.createPieChart = function(canvas, data, options) {
        const ctx = document.getElementById(canvas).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        };
        
        // Merge default options with custom options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'pie',
            data: data,
            options: chartOptions
        });
    };
    
    // Function to create a doughnut chart
    window.createDoughnutChart = function(canvas, data, options) {
        const ctx = document.getElementById(canvas).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            },
            cutout: '70%'
        };
        
        // Merge default options with custom options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: chartOptions
        });
    };
    
    // Function to create a radar chart
    window.createRadarChart = function(canvas, data, options) {
        const ctx = document.getElementById(canvas).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        };
        
        // Merge default options with custom options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'radar',
            data: data,
            options: chartOptions
        });
    };
    
    // Function to create a polar area chart
    window.createPolarAreaChart = function(canvas, data, options) {
        const ctx = document.getElementById(canvas).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        };
        
        // Merge default options with custom options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'polarArea',
            data: data,
            options: chartOptions
        });
    };
    
    // Function to create a bubble chart
    window.createBubbleChart = function(canvas, data, options) {
        const ctx = document.getElementById(canvas).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                },
                y: {
                    beginAtZero: true
                }
            }
        };
        
        // Merge default options with custom options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'bubble',
            data: data,
            options: chartOptions
        });
    };
    
    // Function to create a scatter chart
    window.createScatterChart = function(canvas, data, options) {
        const ctx = document.getElementById(canvas).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                },
                y: {
                    beginAtZero: true
                }
            }
        };
        
        // Merge default options with custom options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'scatter',
            data: data,
            options: chartOptions
        });
    };
    
    // Function to update chart data
    window.updateChartData = function(chart, newData) {
        chart.data = newData;
        chart.update();
    };
    
    // Function to update chart options
    window.updateChartOptions = function(chart, newOptions) {
        chart.options = { ...chart.options, ...newOptions };
        chart.update();
    };
    
    // Function to create a stacked bar chart
    window.createStackedBarChart = function(canvas, data, options) {
        const ctx = document.getElementById(canvas).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true
                }
            }
        };
        
        // Merge default options with custom options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'bar',
            data: data,
            options: chartOptions
        });
    };
    
    // Function to create a horizontal bar chart
    window.createHorizontalBarChart = function(canvas, data, options) {
        const ctx = document.getElementById(canvas).getContext('2d');
        
        const defaultOptions = {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        };
        
        // Merge default options with custom options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'bar',
            data: data,
            options: chartOptions
        });
    };
    
    // Function to create a combo chart (bar + line)
    window.createComboChart = function(canvas, data, options) {
        const ctx = document.getElementById(canvas).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                },
                y: {
                    beginAtZero: true
                }
            }
        };
        
        // Merge default options with custom options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'bar',
            data: data,
            options: chartOptions
        });
    };
}