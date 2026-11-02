function loadAlertesChart(render_type) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: URL_APP + 'api/StatsAlertesMensuelles',
            type: 'GET',
            success: function(response) {
                if (render_type === "1") {
                    // ⚡ TABLE
                    let html = `
                        <table class="table table-hover">
                            <thead><tr><th>Jour</th><th>Nombre d'alertes</th></tr></thead>
                            <tbody>
                    `;
                    response.forEach(r => {
                        html += `<tr><td>${r.day}</td><td>${r.total}</td></tr>`;
                    });
                    html += `</tbody></table>`;
                    resolve(html);
                } else {
                    // ⚡ GRAPHIQUE ApexCharts
                    let days = response.map(r => r.day);
                    let totals = response.map(r => r.total);

                    let options = {
                        chart: { type: 'bar', height: 175, toolbar: { show: false } },
                        title: { text: 'Alertes anomalies - Ce mois', align: 'center' },
                        xaxis: { categories: days, title: { text: 'Jour du mois' } },
                        yaxis: { title: { text: 'Nombre d\'alertes' }, min: 0 },
                        colors: ['#008FFB'],
                        plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
                        dataLabels: { enabled: true },
                        series: [{ name: 'Alertes', data: totals }]
                    };

                    // ⚠️ Ici on ne retourne pas du HTML mais on rend directement le chart
                    resolve({ chartOptions: options });
                }
            },
            error: function(err) {
                reject("Erreur : " + err.statusText);
            }
        });
    });
}
function getAnomaliesZone(render_type) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: URL_APP + 'api/StatsAlertesZones',
            type: 'GET',
            success: function(response) {
                if (render_type === "1") {
                    // ⚡ TABLE
                    let html = `
                        <table class="table table-hover">
                            <thead><tr><th>Zone</th><th>Nombre d'alertes</th></tr></thead>
                            <tbody>
                    `;
                    response.forEach(r => {
                        html += `<tr><td>${r.zone}</td><td>${r.total}</td></tr>`;
                    });
                    html += `</tbody></table>`;
                    resolve(html);

                } else {
                    // ⚡ PIE CHART
                    let zones = response.map(r => r.zone);
                    let totals = response.map(r => r.total);

                    let options = {
                        chart: { type: 'pie', height: 175 },
                        title: {
                            text: 'Répartition des anomalies par zone',
                            align: 'center',
                            style: { fontSize: '13px', fontWeight: 'bold', color: '#333' }
                        },
                        labels: zones,
                        series: totals,
                        colors: ['#008FFB','#FF4560','#00E396','#FEB019','#775DD0'],
                        legend: { position: 'bottom' },
                        dataLabels: {
                            enabled: true,
                            formatter: function (val, opts) {
                                return opts.w.config.labels[opts.seriesIndex] + ": " + val.toFixed(1) + "%";
                            }
                        },
                        tooltip: {
                            theme: 'dark',
                            y: { formatter: val => val + " alertes" }
                        }
                    };

                    resolve({ chartOptions: options });
                }
            },
            error: function(err) {
                reject("Erreur Zones : " + err.statusText);
            }
        });
    });
}
