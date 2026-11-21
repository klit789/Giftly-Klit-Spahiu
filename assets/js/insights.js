document.addEventListener('DOMContentLoaded', () => {
  const data = window.giftlyInsights;
  if (!data || !window.Chart) return;
  Chart.defaults.font.family = 'Manrope, Arial, sans-serif';
  Chart.defaults.color = '#706e69';
  const colors = ['#F04427', '#FFD928', '#20201E', '#61a985', '#ff9d76', '#8399b4', '#d2ad42', '#8f79a6'];
  const build = (id, type, set, options = {}) => {
    const canvas = document.querySelector(`#chart-${id}`);
    if (!canvas) return;
    new Chart(canvas, {
      type,
      data: { labels: set.labels, datasets: [{ label: options.label || 'Items', data: set.values, backgroundColor: type === 'line' ? 'rgba(240,68,39,.14)' : colors, borderColor: type === 'line' ? '#F04427' : '#fff', borderWidth: type === 'line' ? 2 : 1, borderRadius: type === 'bar' ? 7 : 0, fill: type === 'line', tension: .36, pointRadius: type === 'line' ? 4 : 0 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: type === 'doughnut' || type === 'pie', position: 'bottom' }, tooltip: { displayColors: true, callbacks: { label: item => ` ${Number(item.raw).toLocaleString()} ${options.unit || ''}` } } }, scales: type === 'doughnut' || type === 'pie' ? {} : { y: { beginAtZero: true, grid: { color: 'rgba(112,110,105,.12)' }, ticks: { precision: 0 } }, x: { grid: { display: false } } }, ...options.config }
    });
  };
  build('category', 'bar', data.category, { label: 'Items needed', unit: 'items' });
  build('monthly', 'line', data.monthly, { label: 'Completed donations', unit: 'donations' });
  build('season', 'bar', data.season, { label: 'Items needed', unit: 'items' });
  build('sizes', 'bar', data.sizes, { label: 'Items needed', unit: 'items', config: { indexAxis: 'y' } });
  build('statuses', 'doughnut', data.statuses, { label: 'Pledges', unit: 'pledges' });
  build('urgency', 'doughnut', data.urgency, { label: 'Requests', unit: 'requests' });
  document.querySelector('[data-print-report]')?.addEventListener('click', () => window.print());
  document.querySelector('[data-charity-search]')?.addEventListener('input', event => {
    const query = event.target.value.trim().toLocaleLowerCase();
    document.querySelectorAll('[data-searchable-row]').forEach(row => { row.hidden = !row.textContent.toLocaleLowerCase().includes(query); });
  });
});
