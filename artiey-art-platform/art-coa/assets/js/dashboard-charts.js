(function () {
  'use strict';
  if (typeof Chart === 'undefined' || !window.artCoaChartData) return;

  var data = window.artCoaChartData;
  var labels = data.labels || [];
  var values = data.values || [];
  var byType = data.byType || { collector: [], buyer: [], other: [] };
  var L = data.i18n || {
    sales: 'Sales',
    collector: 'Collector',
    buyer: 'Buyer',
    other: 'Other'
  };

  var lineEl = document.getElementById('artCoaSalesChart');
  if (lineEl) {
    new Chart(lineEl, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: L.sales,
          data: values,
          borderColor: '#b32d2e',
          backgroundColor: 'rgba(179, 45, 46, 0.12)',
          fill: true,
          tension: 0.3,
          pointRadius: 4,
          pointBackgroundColor: '#b32d2e'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: { display: true, position: 'bottom' },
          tooltip: { mode: 'index', intersect: false }
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }

  var stackEl = document.getElementById('artCoaSalesStackChart');
  if (stackEl) {
    new Chart(stackEl, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          { label: L.collector, data: byType.collector || [], backgroundColor: '#2271b1' },
          { label: L.buyer, data: byType.buyer || [], backgroundColor: '#00a32a' },
          { label: L.other, data: byType.other || [], backgroundColor: '#8c8f94' }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'bottom' } },
        scales: {
          x: { stacked: true },
          y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }
})();


  if (window.artCoaAuctionChartData) {
    var A = window.artCoaAuctionChartData;
    var Ai = A.i18n || {};
    var volEl = document.getElementById('artCoaAuctionVolumeChart');
    if (volEl && A.monthly) {
      new Chart(volEl, {
        type: 'bar',
        data: {
          labels: A.monthly.labels || [],
          datasets: [
            {
              label: Ai.volume || 'Volume',
              data: A.monthly.volume || [],
              backgroundColor: 'rgba(26, 54, 93, 0.7)',
              yAxisID: 'y'
            },
            {
              label: Ai.sold || 'Sold',
              data: A.monthly.count || [],
              type: 'line',
              borderColor: '#b32d2e',
              backgroundColor: 'transparent',
              yAxisID: 'y1',
              tension: 0.3
            }
          ]
        },
        options: {
          responsive: true,
          scales: {
            y: { beginAtZero: true, position: 'left' },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 } }
          },
          plugins: { legend: { position: 'bottom' } }
        }
      });
    }
    var bidEl = document.getElementById('artCoaAuctionBidsChart');
    if (bidEl && A.daily) {
      new Chart(bidEl, {
        type: 'line',
        data: {
          labels: A.daily.labels || [],
          datasets: [{
            label: Ai.bids || 'Bids',
            data: A.daily.values || [],
            borderColor: '#c9a227',
            backgroundColor: 'rgba(201, 162, 39, 0.15)',
            fill: true,
            tension: 0.3
          }]
        },
        options: {
          responsive: true,
          scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
          plugins: { legend: { position: 'bottom' } }
        }
      });
    }
  }
