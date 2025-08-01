/**
 * Enhanced Chart Configuration for KFZ Application
 * Provides scrollable and scalable kilometer progression charts
 */

// Load fallback first
document.addEventListener('DOMContentLoaded', function() {
    const fallbackScript = document.createElement('script');
    fallbackScript.src = 'js/chart-fallback.js';
    document.head.appendChild(fallbackScript);
});

// Chart.js fallback configuration
const CHART_CONFIG = {
    // CDN URLs with fallbacks
    cdnUrls: [
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
        'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.js'
    ],
    
    // Time range options for filtering
    timeRanges: {
        '30d': { label: 'Letzte 30 Tage', days: 30 },
        '3m': { label: 'Letzte 3 Monate', days: 90 },
        '6m': { label: 'Letzte 6 Monate', days: 180 },
        '1y': { label: 'Letztes Jahr', days: 365 },
        'all': { label: 'Alle Daten', days: null }
    },
    
    // Default chart options with responsive and scroll features
    defaultOptions: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            intersect: false,
            mode: 'index'
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: 'white',
                bodyColor: 'white',
                borderColor: 'rgba(255, 255, 255, 0.1)',
                borderWidth: 1
            }
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Datum'
                }
            },
            y: {
                display: true,
                title: {
                    display: true,
                    text: 'Kilometer'
                }
            }
        }
    }
};

/**
 * Loads Chart.js library with fallback support
 */
async function loadChartJS() {
    // Check if Chart.js is already loaded
    if (typeof Chart !== 'undefined' && Chart.name !== 'SimpleChart') {
        return Promise.resolve(true);
    }
    
    // Try to load from CDN with fallbacks
    for (const url of CHART_CONFIG.cdnUrls) {
        try {
            await loadScript(url);
            if (typeof Chart !== 'undefined' && Chart.name !== 'SimpleChart') {
                console.log('Chart.js loaded successfully from:', url);
                return true;
            }
        } catch (error) {
            console.warn('Failed to load Chart.js from:', url, error);
        }
    }
    
    // If all CDN attempts fail, use fallback
    console.error('Failed to load Chart.js from all CDN sources, using fallback');
    return false;
}

/**
 * Loads a script dynamically
 */
function loadScript(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

/**
 * Creates an enhanced kilometer progression chart with time range filtering
 */
function createKilometerProgressionChart(containerId, data, options = {}) {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Chart container not found:', containerId);
        return null;
    }
    
    // Create chart container with controls
    const chartWrapper = createChartWrapper(container, containerId);
    
    // Initialize chart data
    let currentRange = options.defaultRange || '6m';
    let filteredData = filterDataByTimeRange(data, currentRange);
    
    // Create the chart
    const canvas = chartWrapper.querySelector('canvas');
    if (!canvas) {
        console.error('Canvas not found in chart wrapper');
        return null;
    }
    
    const ctx = canvas.getContext('2d');
    
    const chartConfig = {
        type: 'line',
        data: {
            labels: filteredData.labels,
            datasets: filteredData.datasets
        },
        options: {
            ...CHART_CONFIG.defaultOptions,
            ...options,
            plugins: {
                ...CHART_CONFIG.defaultOptions.plugins,
                title: {
                    display: true,
                    text: options.title || 'Kilometer Progression'
                },
                ...options.plugins
            }
        }
    };
    
    const chart = new Chart(ctx, chartConfig);
    
    // Add event listeners for time range controls
    setupTimeRangeControls(chartWrapper, chart, data);
    
    return chart;
}

/**
 * Creates a chart wrapper with controls
 */
function createChartWrapper(container, chartId) {
    const wrapper = document.createElement('div');
    wrapper.className = 'enhanced-chart-wrapper';
    wrapper.innerHTML = `
        <div class="chart-controls mb-3">
            <div class="row">
                <div class="col-md-8">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Zeitraum">
                        ${Object.entries(CHART_CONFIG.timeRanges).map(([key, config]) => 
                            `<button type="button" class="btn btn-outline-primary time-range-btn" data-range="${key}">${config.label}</button>`
                        ).join('')}
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-outline-secondary btn-sm reset-zoom-btn">
                        <i class="bi bi-zoom-out"></i> Zoom zurücksetzen
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm export-btn">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
            </div>
        </div>
        <div class="chart-container" style="position: relative; height: 400px;">
            <canvas id="${chartId}_canvas"></canvas>
        </div>
        <div class="chart-info mt-2">
            <small class="text-muted">
                <i class="bi bi-info-circle"></i> 
                Verwenden Sie Mausrad zum Zoomen, Ziehen zum Verschieben. 
                Klicken Sie auf die Zeitraum-Buttons für schnelle Filterung.
            </small>
        </div>
    `;
    
    // Replace original container content
    container.innerHTML = '';
    container.appendChild(wrapper);
    
    return wrapper;
}

/**
 * Sets up time range control event listeners
 */
function setupTimeRangeControls(wrapper, chart, originalData) {
    const timeRangeButtons = wrapper.querySelectorAll('.time-range-btn');
    const resetZoomBtn = wrapper.querySelector('.reset-zoom-btn');
    const exportBtn = wrapper.querySelector('.export-btn');
    
    // Set default active button
    if (timeRangeButtons.length > 2) {
        timeRangeButtons[2].classList.add('active'); // 6 months default
    }
    
    // Time range button handlers
    timeRangeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Update active state
            timeRangeButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Filter data and update chart
            const range = this.dataset.range;
            const filteredData = filterDataByTimeRange(originalData, range);
            
            chart.data.labels = filteredData.labels;
            chart.data.datasets = filteredData.datasets;
            if (chart.update) {
                chart.update('active');
            }
        });
    });
    
    // Reset zoom handler
    if (resetZoomBtn) {
        resetZoomBtn.addEventListener('click', function() {
            if (chart.resetZoom) {
                chart.resetZoom();
            }
        });
    }
    
    // Export handler
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            exportChartData(chart, originalData);
        });
    }
}

/**
 * Filters data by time range
 */
function filterDataByTimeRange(data, range) {
    if (range === 'all' || !CHART_CONFIG.timeRanges[range]) {
        return data;
    }
    
    const days = CHART_CONFIG.timeRanges[range].days;
    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - days);
    
    const filteredIndices = [];
    data.labels.forEach((label, index) => {
        const date = parseGermanDate(label);
        if (date >= cutoffDate) {
            filteredIndices.push(index);
        }
    });
    
    return {
        labels: filteredIndices.map(i => data.labels[i]),
        datasets: data.datasets.map(dataset => ({
            ...dataset,
            data: filteredIndices.map(i => dataset.data[i])
        }))
    };
}

/**
 * Parses German date format (dd.mm.yyyy)
 */
function parseGermanDate(dateStr) {
    const parts = dateStr.split('.');
    if (parts.length !== 3) return new Date(dateStr);
    
    const day = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1; // Month is 0-indexed
    const year = parseInt(parts[2], 10);
    
    return new Date(year, month, day);
}

/**
 * Exports chart data as CSV
 */
function exportChartData(chart, data) {
    const csvContent = generateCSV(data);
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    const filename = `kilometer_progression_${new Date().toISOString().split('T')[0]}.csv`;
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

/**
 * Generates CSV content from chart data
 */
function generateCSV(data) {
    let csv = 'Datum,Kilometer,Typ\n';
    
    data.labels.forEach((label, index) => {
        data.datasets.forEach(dataset => {
            if (dataset.data[index] !== null && dataset.data[index] !== undefined) {
                csv += `${label},${dataset.data[index]},${dataset.label}\n`;
            }
        });
    });
    
    return csv;
}

/**
 * Creates an enhanced kilometer progression chart with maintenance and fuel annotations
 */
function createIntegratedKilometerChart(containerId, kilometerData, fuelData, maintenanceData, options = {}) {
    const datasets = [];
    
    // Main kilometer progression line
    datasets.push({
        label: 'Kilometerstand',
        data: kilometerData.values,
        borderColor: 'rgb(75, 192, 192)',
        backgroundColor: 'rgba(75, 192, 192, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.1
    });
    
    // Fuel events as points
    if (fuelData && fuelData.length > 0) {
        datasets.push({
            label: 'Tankungen',
            data: fuelData.map(f => ({ x: f.date, y: f.mileage })),
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgb(255, 99, 132)',
            pointRadius: 6,
            pointHoverRadius: 8,
            showLine: false,
            pointStyle: 'triangle'
        });
    }
    
    // Maintenance events as points
    if (maintenanceData && maintenanceData.length > 0) {
        datasets.push({
            label: 'Wartungen',
            data: maintenanceData.map(m => ({ x: m.date, y: m.mileage })),
            borderColor: 'rgb(255, 159, 64)',
            backgroundColor: 'rgb(255, 159, 64)',
            pointRadius: 8,
            pointHoverRadius: 10,
            showLine: false,
            pointStyle: 'rectRot'
        });
    }
    
    const chartData = {
        labels: kilometerData.labels,
        datasets: datasets
    };
    
    const enhancedOptions = {
        ...options,
        plugins: {
            ...options.plugins,
            tooltip: {
                ...CHART_CONFIG.defaultOptions.plugins.tooltip,
                callbacks: {
                    label: function(context) {
                        const datasetLabel = context.dataset.label;
                        const value = context.parsed.y;
                        
                        if (datasetLabel === 'Tankungen') {
                            const fuelEvent = fuelData[context.dataIndex];
                            return `${datasetLabel}: ${value.toLocaleString('de-DE')} km (${fuelEvent.liters}L, ${fuelEvent.cost}€)`;
                        } else if (datasetLabel === 'Wartungen') {
                            const maintenanceEvent = maintenanceData[context.dataIndex];
                            return `${datasetLabel}: ${value.toLocaleString('de-DE')} km (${maintenanceEvent.type}, ${maintenanceEvent.cost}€)`;
                        } else {
                            return `${datasetLabel}: ${value.toLocaleString('de-DE')} km`;
                        }
                    }
                }
            }
        }
    };
    
    return createKilometerProgressionChart(containerId, chartData, enhancedOptions);
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        loadChartJS().then(success => {
            if (success || typeof Chart !== 'undefined') {
                console.log('Chart.js loaded successfully, charts are now available');
                // Dispatch custom event to signal charts are ready
                document.dispatchEvent(new CustomEvent('chartsReady'));
            } else {
                console.error('Failed to load Chart.js, using fallback implementation');
                // Use fallback implementation
                document.dispatchEvent(new CustomEvent('chartsReady'));
            }
        }).catch(error => {
            console.error('Error loading Chart.js:', error);
            document.dispatchEvent(new CustomEvent('chartsReady'));
        });
    }, 100); // Small delay to ensure fallback is loaded
});