/**
 * WP Site Monitor Dashboard
 * Simple JavaScript implementation (in production, this would be React+TypeScript)
 */
(function() {
    'use strict';
    
    const { apiUrl, nonce } = window.wpSiteMonitor || {};
    
    if (!apiUrl) {
        console.error('WP Site Monitor: API URL not found');
        return;
    }
    
    class Dashboard {
        constructor() {
            this.data = null;
            this.isLoading = false;
            this.init();
        }
        
        init() {
            this.render();
            this.fetchData();
        }
        
        async fetchData() {
            this.isLoading = true;
            this.renderLoading();
            
            try {
                const response = await fetch(`${apiUrl}/status`, {
                    headers: {
                        'X-WP-Nonce': nonce
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.data = result.data;
                    this.render();
                }
            } catch (error) {
                console.error('Error fetching data:', error);
                this.renderError();
            } finally {
                this.isLoading = false;
            }
        }
        
        async runManualCheck() {
            if (this.isLoading) return;
            
            this.isLoading = true;
            const button = document.querySelector('.refresh-button');
            if (button) {
                button.disabled = true;
                button.textContent = 'Checking...';
            }
            
            try {
                const response = await fetch(`${apiUrl}/check`, {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': nonce
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.data = result.data;
                    this.render();
                }
            } catch (error) {
                console.error('Error running check:', error);
            } finally {
                this.isLoading = false;
                if (button) {
                    button.disabled = false;
                    button.textContent = 'Run Check Now';
                }
            }
        }
        
        renderLoading() {
            const root = document.getElementById('wp-site-monitor-root');
            root.innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <p>Loading monitoring data...</p>
                </div>
            `;
        }
        
        renderError() {
            const root = document.getElementById('wp-site-monitor-root');
            root.innerHTML = `
                <div class="loading">
                    <p>Error loading data. Please refresh the page.</p>
                </div>
            `;
        }
        
        render() {
            if (!this.data) {
                this.renderLoading();
                return;
            }
            
            const root = document.getElementById('wp-site-monitor-root');
            const { performance, security, health } = this.data;
            
            root.innerHTML = `
                <div class="monitor-dashboard">
                    <div class="dashboard-header">
                        <h1>🔍 Site Monitor Dashboard</h1>
                        <p class="last-check">Last checked: ${this.formatDate(this.data.last_check)}</p>
                        <button class="refresh-button" onclick="dashboard.runManualCheck()">
                            Run Check Now
                        </button>
                    </div>
                    
                    <div class="stats-grid">
                        ${this.renderPerformanceCard(performance)}
                        ${this.renderSecurityCard(security)}
                        ${this.renderHealthCard(health)}
                    </div>
                    
                    <div class="detail-section">
                        <h2>📊 Performance Details</h2>
                        ${this.renderPerformanceDetails(performance)}
                    </div>
                    
                    <div class="detail-section">
                        <h2>🔒 Security Status</h2>
                        ${this.renderSecurityDetails(security)}
                    </div>
                </div>
            `;
        }
        
        renderPerformanceCard(data) {
            return `
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon performance">⚡</div>
                        <h3 class="stat-title">Performance</h3>
                    </div>
                    <p class="stat-value">${data.page_load_time}ms</p>
                    <p class="stat-subtitle">Average Page Load Time</p>
                    <p class="stat-subtitle">${data.database_queries} DB Queries</p>
                </div>
            `;
        }
        
        renderSecurityCard(data) {
            const outdatedCount = (data.outdated_plugins?.length || 0) + (data.outdated_themes?.length || 0);
            return `
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon security">🔒</div>
                        <h3 class="stat-title">Security</h3>
                    </div>
                    <p class="stat-value">${outdatedCount}</p>
                    <p class="stat-subtitle">Outdated Components</p>
                    <p class="stat-subtitle">SSL: ${data.ssl_status?.enabled ? '✅ Enabled' : '❌ Disabled'}</p>
                </div>
            `;
        }
        
        renderHealthCard(data) {
            const healthScore = data.health_score;
            const grade = healthScore?.grade || 'N/A';
            const score = healthScore?.score || 0;
            
            return `
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon health">❤️</div>
                        <h3 class="stat-title">Health Score</h3>
                    </div>
                    <p class="stat-value">${score}/100</p>
                    <span class="health-score grade-${grade.toLowerCase()}">Grade: ${grade}</span>
                    ${healthScore?.issues?.length > 0 ? `
                        <ul class="issues-list">
                            ${healthScore.issues.map(issue => `<li>${issue}</li>`).join('')}
                        </ul>
                    ` : ''}
                </div>
            `;
        }
        
        renderPerformanceDetails(data) {
            return `
                <div class="metric-row">
                    <span class="metric-label">Page Load Time</span>
                    <span class="metric-value">${data.page_load_time}ms</span>
                </div>
                <div class="metric-row">
                    <span class="metric-label">Database Queries</span>
                    <span class="metric-value">${data.database_queries}</span>
                </div>
                <div class="metric-row">
                    <span class="metric-label">Memory Usage</span>
                    <span class="metric-value">${data.memory_usage?.current} (${data.memory_usage?.percentage}%)</span>
                </div>
                <div class="metric-row">
                    <span class="metric-label">Active Plugins</span>
                    <span class="metric-value">${data.active_plugins_count}</span>
                </div>
                <div class="metric-row">
                    <span class="metric-label">PHP Version</span>
                    <span class="metric-value">${data.php_version}</span>
                </div>
                <div class="metric-row">
                    <span class="metric-label">WordPress Version</span>
                    <span class="metric-value">${data.wp_version}</span>
                </div>
            `;
        }
        
        renderSecurityDetails(data) {
            let html = '';
            
            if (data.outdated_plugins && data.outdated_plugins.length > 0) {
                html += '<h3 style="margin-top:0;font-size:16px;">Outdated Plugins</h3>';
                html += '<ul class="issues-list">';
                data.outdated_plugins.forEach(plugin => {
                    html += `<li class="critical">${plugin.name}: v${plugin.current_version} → v${plugin.new_version}</li>`;
                });
                html += '</ul>';
            }
            
            if (data.outdated_themes && data.outdated_themes.length > 0) {
                html += '<h3 style="font-size:16px;">Outdated Themes</h3>';
                html += '<ul class="issues-list">';
                data.outdated_themes.forEach(theme => {
                    html += `<li>${theme.name}: v${theme.current_version} → v${theme.new_version}</li>`;
                });
                html += '</ul>';
            }
            
            if (!data.outdated_plugins?.length && !data.outdated_themes?.length) {
                html += '<p style="color:#2e7d32;">✅ All plugins and themes are up to date!</p>';
            }
            
            html += `
                <div class="metric-row">
                    <span class="metric-label">SSL Certificate</span>
                    <span class="metric-value">${data.ssl_status?.enabled ? '✅ Enabled' : '❌ Not Enabled'}</span>
                </div>
            `;
            
            return html;
        }
        
        formatDate(dateString) {
            if (!dateString) return 'Never';
            const date = new Date(dateString);
            return date.toLocaleString();
        }
    }
    
    // Initialize dashboard when DOM is ready
    let dashboard;
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            dashboard = new Dashboard();
        });
    } else {
        dashboard = new Dashboard();
    }
    
    // Make dashboard accessible globally for button clicks
    window.dashboard = dashboard;
})();
