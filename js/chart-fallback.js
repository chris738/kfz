/**
 * Simple Chart.js fallback implementation for KFZ Application
 * Provides basic chart functionality when Chart.js CDN fails
 */

// Simple Chart fallback class
class SimpleChart {
    constructor(ctx, config) {
        this.ctx = ctx;
        this.config = config;
        this.canvas = ctx.canvas;
        this.data = config.data;
        this.options = config.options || {};
        
        this.init();
    }
    
    init() {
        // Create a simple HTML table representation of the chart data
        this.createTableView();
    }
    
    createTableView() {
        const container = this.canvas.parentElement;
        container.innerHTML = '';
        
        const wrapper = document.createElement('div');
        wrapper.className = 'simple-chart-fallback';
        wrapper.innerHTML = `
            <div class="alert alert-info mb-3">
                <i class="bi bi-info-circle"></i> 
                <strong>Chart-Ansicht nicht verfügbar</strong><br>
                Die Daten werden in Tabellenform dargestellt.
            </div>
            <div class="chart-fallback-content"></div>
        `;
        
        container.appendChild(wrapper);
        
        const content = wrapper.querySelector('.chart-fallback-content');
        
        if (this.config.type === 'line') {
            this.createLineChartFallback(content);
        } else if (this.config.type === 'bar') {
            this.createBarChartFallback(content);
        } else if (this.config.type === 'pie') {
            this.createPieChartFallback(content);
        }
    }
    
    createLineChartFallback(container) {
        const table = document.createElement('table');
        table.className = 'table table-striped table-sm';
        
        let html = '<thead><tr><th>Datum</th>';
        this.data.datasets.forEach(dataset => {
            html += `<th>${dataset.label || 'Werte'}</th>`;
        });
        html += '</tr></thead><tbody>';
        
        this.data.labels.forEach((label, index) => {
            html += `<tr><td>${label}</td>`;
            this.data.datasets.forEach(dataset => {
                const value = dataset.data[index];
                if (value !== null && value !== undefined) {
                    html += `<td>${typeof value === 'number' ? value.toLocaleString('de-DE') : value}</td>`;
                } else {
                    html += `<td>-</td>`;
                }
            });
            html += '</tr>';
        });
        
        html += '</tbody>';
        table.innerHTML = html;
        container.appendChild(table);
    }
    
    createBarChartFallback(container) {
        this.createLineChartFallback(container); // Same as line chart for now
    }
    
    createPieChartFallback(container) {
        const list = document.createElement('ul');
        list.className = 'list-group';
        
        if (this.data.datasets[0] && this.data.datasets[0].data) {
            const total = this.data.datasets[0].data.reduce((sum, val) => sum + val, 0);
            
            this.data.labels.forEach((label, index) => {
                const value = this.data.datasets[0].data[index];
                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                
                const item = document.createElement('li');
                item.className = 'list-group-item d-flex justify-content-between align-items-center';
                item.innerHTML = `
                    ${label}
                    <span>
                        <span class="badge bg-primary rounded-pill me-2">${value}</span>
                        <small class="text-muted">${percentage}%</small>
                    </span>
                `;
                list.appendChild(item);
            });
        }
        
        container.appendChild(list);
    }
    
    update() {
        // Re-create the fallback view
        this.createTableView();
    }
    
    resetZoom() {
        // No-op for fallback
        console.log('Zoom reset not available in fallback mode');
    }
}

// Fallback Chart object
window.ChartFallback = SimpleChart;

// Override Chart if not available
if (typeof Chart === 'undefined') {
    window.Chart = SimpleChart;
    console.log('Using SimpleChart fallback implementation');
}