// public/assets/js/main.js

let trendChart = null;

// Fungsi inisialisasi grafik Chart.js
function initChart(chartData) {
    const ctx = document.getElementById('eggTrendChart').getContext('2d');
    
    trendChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'RINGAN',
                    data: chartData.ringan,
                    backgroundColor: 'rgba(22, 163, 74, 0.85)',
                    borderColor: 'rgb(22, 163, 74)',
                    borderWidth: 1
                },
                {
                    label: 'SEDANG',
                    data: chartData.sedang,
                    backgroundColor: 'rgba(202, 138, 4, 0.85)',
                    borderColor: 'rgb(202, 138, 4)',
                    borderWidth: 1
                },
                {
                    label: 'BERAT',
                    data: chartData.berat,
                    backgroundColor: 'rgba(234, 88, 12, 0.85)',
                    borderColor: 'rgb(234, 88, 12)',
                    borderWidth: 1
                },
                {
                    label: 'BUSUK',
                    data: chartData.busuk,
                    backgroundColor: 'rgba(30, 41, 59, 0.85)',
                    borderColor: 'rgb(30, 41, 59)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true,
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            family: "'Plus Jakarta Sans', sans-serif",
                            weight: '600'
                        }
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: {
                            family: "'JetBrains Mono', monospace"
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            family: "'Plus Jakarta Sans', sans-serif",
                            weight: '600',
                            size: 11
                        },
                        boxWidth: 12
                    }
                }
            }
        }
    });
}

// Fungsi memperbarui isi grafik
function updateChart(chartData) {
    if (!trendChart) {
        initChart(chartData);
        return;
    }
    
    trendChart.data.labels = chartData.labels;
    trendChart.data.datasets[0].data = chartData.ringan;
    trendChart.data.datasets[1].data = chartData.sedang;
    trendChart.data.datasets[2].data = chartData.berat;
    trendChart.data.datasets[3].data = chartData.busuk;
    trendChart.update('none'); // Update tanpa animasi penuh untuk performa
}

// Fungsi mengambil data dari API backend
function fetchStats() {
    const pollIndicator = document.getElementById('poll-indicator');
    
    fetch('api/stats.php')
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Tandai polling aktif/sukses
                pollIndicator.classList.add('active');
                pollIndicator.innerHTML = '<span class="blink-dot"></span>Real-time Polling';

                // Update Counter Hari Ini
                document.getElementById('today-ringan').innerText = data.today.ringan;
                document.getElementById('today-sedang').innerText = data.today.sedang;
                document.getElementById('today-berat').innerText = data.today.berat;
                document.getElementById('today-busuk').innerText = data.today.busuk;

                // Update Counter Total
                document.getElementById('total-ringan').innerText = `Total: ${data.totals.ringan}`;
                document.getElementById('total-sedang').innerText = `Total: ${data.totals.sedang}`;
                document.getElementById('total-berat').innerText = `Total: ${data.totals.berat}`;
                document.getElementById('total-busuk').innerText = `Total: ${data.totals.busuk}`;

                // Update Log Terakhir
                const logsTableBody = document.getElementById('latest-logs');
                if (data.latest.length === 0) {
                    logsTableBody.innerHTML = `
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 20px;">
                                Belum ada data pemilahan telur.
                            </td>
                        </tr>
                    `;
                } else {
                    let html = '';
                    data.latest.forEach(row => {
                        const badgeClass = row.category;
                        const labelText = row.category.toUpperCase();
                        
                        html += `
                            <tr>
                                <td><span class="badge ${badgeClass}">${labelText}</span></td>
                                <td style="text-align: right;" class="mono">${parseFloat(row.weight).toFixed(2)}g</td>
                                <td style="text-align: right;" class="mono">${row.gas_value}</td>
                                <td style="text-align: right; font-size: 0.8rem; color: var(--color-text-muted);" class="mono">
                                    ${row.formatted_time.split(' ')[1]}
                                </td>
                            </tr>
                        `;
                    });
                    logsTableBody.innerHTML = html;
                }

                // Update Grafik
                updateChart(data.chart);
            }
        })
        .catch(error => {
            console.error('Error fetching statistics:', error);
            pollIndicator.classList.remove('active');
            pollIndicator.innerHTML = 'Disconnected';
        });
}

// Jalankan pengambilan data pertama kali
document.addEventListener('DOMContentLoaded', () => {
    fetchStats();
    
    // Polling setiap 3 detik
    setInterval(fetchStats, 3000);
});
