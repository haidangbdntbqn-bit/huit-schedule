// Biểu đồ trang tổng quan (Chart.js)
(function () {
    const el = document.getElementById('chartData');
    if (!el || typeof Chart === 'undefined') return;
    const d = JSON.parse(el.textContent);

    new Chart(document.getElementById('chartDays'), {
        type: 'bar',
        data: { labels: d.days, datasets: d.dayDatasets },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } },
            scales: { x: { stacked: true }, y: { stacked: true, title: { display: true, text: 'Số tiết' } } } },
    });

    new Chart(document.getElementById('chartBranches'), {
        type: 'doughnut',
        data: { labels: d.branches, datasets: [{ data: d.branchData, backgroundColor: d.branchColors }] },
        options: { plugins: { legend: { position: 'bottom' } } },
    });

    new Chart(document.getElementById('chartTeachers'), {
        type: 'bar',
        data: { labels: d.teachers, datasets: [{ label: 'Tiết/tuần', data: d.teacherData, backgroundColor: '#1565c0' }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false } } },
    });

    new Chart(document.getElementById('chartSessions'), {
        type: 'pie',
        data: { labels: d.sessions, datasets: [{ data: d.sessionData, backgroundColor: ['#ffc107', '#0d6efd', '#6f42c1'] }] },
        options: { plugins: { legend: { position: 'bottom' } } },
    });
})();
