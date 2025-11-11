<?php

declare(strict_types=1);

$nonce = base64_encode(random_bytes(18));

header('Content-Type: text/html; charset=utf-8');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header(
    'Content-Security-Policy: default-src \'self\'; ' .
    "script-src 'self' https://cdn.jsdelivr.net 'nonce-{$nonce}'; " .
    "style-src 'self' https://cdn.jsdelivr.net 'nonce-{$nonce}'; " .
    "img-src 'self' data:; connect-src 'self'; font-src 'self'; " .
    "manifest-src 'self'; worker-src 'self'; base-uri 'self'; frame-ancestors 'none';"
);

?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Realtime Clicks · SRP</title>
  <meta name="referrer" content="no-referrer">
  <meta name="theme-color" content="#0f172a">
  <meta name="application-name" content="Smart Redirect">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <link rel="icon" type="image/svg+xml" sizes="any" href="assets/icons/icon-192.svg">
  <link rel="apple-touch-icon" href="assets/icons/icon-192.svg">
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="assets/css/realtime.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" integrity="sha384-VlaQt1zArhcXd1LSeX776BF3/f6/Dr7guPmyAnbcWcCYwiVdc+GqOR/mdrIW6DCe" crossorigin="anonymous" defer></script>
</head>
<body>
  <div class="container">
    <header class="page-header">
      <h1 class="page-title">Realtime Clicks</h1>
      <div class="controls">
        <button id="toggleTheme" class="btn btn-ghost" type="button" aria-pressed="false" aria-label="Toggle theme">
          <span aria-hidden="true">🌗</span>
        </button>
        <a href="index.php" class="btn btn-secondary" rel="noreferrer">Dashboard</a>
      </div>
    </header>

    <section class="auth-section" aria-label="Autentikasi">
      <div class="form-group">
        <label class="form-label" for="apiKey">API Key (X-API-Key)</label>
        <input id="apiKey" type="password" autocomplete="off" placeholder="tempel API key untuk admin" class="form-input">
      </div>
    </section>

    <section class="metrics-grid">
      <div class="metric-card">
        <div class="metric-label">Total (15m)</div>
        <div class="metric-value" id="mTotal">0</div>
        <div class="metric-pills">
          <span class="pill pill-a">A: <span id="mA">0</span></span>
          <span class="pill pill-b">B: <span id="mB">0</span></span>
        </div>
      </div>

      <div class="metric-card">
        <div class="metric-label">Top Countries (15m)</div>
        <div class="font-mono text-sm" id="mCountries" style="margin-top: var(--spacing-md);">-</div>
      </div>

      <div class="metric-card">
        <div class="metric-label">Server Time</div>
        <div class="server-time" id="srvTime">-</div>
      </div>
    </section>

    <section class="chart-container">
      <div class="chart-header">
        <div>
          <div class="chart-title">Traffic (per menit, 15m)</div>
          <div class="text-xs text-muted">auto refresh</div>
        </div>
      </div>
      <div class="chart-wrapper">
        <canvas id="chart" aria-label="Traffic chart"></canvas>
      </div>
    </section>

    <section class="table-container">
      <div class="table-wrapper scroll-area">
        <table aria-label="Realtime clicks">
          <thead>
            <tr>
              <th scope="col">Time</th>
              <th scope="col">CC</th>
              <th scope="col">Dec</th>
              <th scope="col">Click ID</th>
              <th scope="col">LP</th>
              <th scope="col">IP</th>
              <th scope="col">Device</th>
              <th scope="col">UA</th>
            </tr>
          </thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
    </section>
  </div>

  <script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">
    'use strict';

    const ready = (callback) => {
      if (document.readyState === 'complete' || document.readyState === 'interactive') {
        callback();
        return;
      }
      document.addEventListener('DOMContentLoaded', callback, { once: true });
    };

    ready(() => {
      const $ = (selector) => document.querySelector(selector);
      const store = {
        get key() { return localStorage.getItem('srp_key') ?? ''; },
        set key(value) { localStorage.setItem('srp_key', value); }
      };

      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js.php').catch(() => {});
      }

      const html = document.documentElement;
      const toggleTheme = $('#toggleTheme');
      
      const savedTheme = localStorage.getItem('srp_theme') ?? 'dark';
      if (savedTheme === 'light') {
        html.classList.add('light');
        toggleTheme.setAttribute('aria-pressed', 'true');
      }

      toggleTheme.addEventListener('click', () => {
        html.classList.toggle('light');
        const isLight = html.classList.contains('light');
        toggleTheme.setAttribute('aria-pressed', String(isLight));
        localStorage.setItem('srp_theme', isLight ? 'light' : 'dark');
      });

      $('#apiKey').value = store.key;
      $('#apiKey').addEventListener('change', (event) => {
        store.key = event.target.value.trim();
      });

      const headers = () => ({ 'X-API-Key': store.key });
      
      const escape = (value) => {
        const text = String(value ?? '');
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      };

      const formatTime = (timestamp) => new Date(timestamp * 1000).toLocaleTimeString();

      let lastId = 0;
      let chart;
      const chartLabels = [];
      const chartData = [];

      function ensureChart() {
        if (chart) return;
        const ctx = document.getElementById('chart');
        chart = new window.Chart(ctx, {
          type: 'line',
          data: {
            labels: chartLabels,
            datasets: [{
              label: 'Clicks/min',
              data: chartData,
              tension: 0.25,
              fill: false,
              borderColor: '#2563eb',
              backgroundColor: 'rgba(37,99,235,0.25)',
              borderWidth: 2,
              pointRadius: 3,
              pointBackgroundColor: '#2563eb'
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              x: { 
                display: true,
                ticks: { color: '#94a3b8' },
                grid: { color: 'rgba(148,163,184,0.1)' }
              },
              y: { 
                beginAtZero: true,
                ticks: { color: '#94a3b8' },
                grid: { color: 'rgba(148,163,184,0.1)' }
              }
            }
          }
        });
      }

      function updateChart(series) {
        ensureChart();
        chartLabels.length = 0;
        chartData.length = 0;
        for (const point of series) {
          chartLabels.push(new Date(point.ts * 1000).toLocaleTimeString());
          chartData.push(point.c);
        }
        chart.update();
      }

      function appendRows(hits) {
        const tbody = $('#rows');
        const fragment = document.createDocumentFragment();
        for (const hit of hits) {
          const row = document.createElement('tr');
          const decisionPill = hit.decision === 'A' 
            ? '<span class="pill pill-a">A</span>' 
            : '<span class="pill pill-b">B</span>';
          
          row.innerHTML = `
            <td>${formatTime(hit.ts)}</td>
            <td class="cell-mono cell-center cell-uppercase">${escape(hit.cc)}</td>
            <td class="cell-center">${decisionPill}</td>
            <td class="cell-mono">${escape(hit.cid)}</td>
            <td class="cell-mono cell-truncate">${escape(hit.lp)}</td>
            <td class="cell-mono">${escape(hit.ip)}</td>
            <td class="cell-mono">${escape(hit.device ?? '')}</td>
            <td class="cell-truncate">${escape(hit.ua.slice(0, 120))}${hit.ua.length > 120 ? '…' : ''}</td>`;
          fragment.insertBefore(row, fragment.firstChild);
        }
        tbody.insertBefore(fragment, tbody.firstChild);
        while (tbody.rows.length > 200) {
          tbody.deleteRow(-1);
        }
      }

      function applyStats(stats) {
        $('#mTotal').textContent = stats.total;
        $('#mA').textContent = stats.a;
        $('#mB').textContent = stats.b;
        $('#mCountries').textContent = stats.top_countries.length
          ? stats.top_countries.map((country) => `${country.cc}:${country.c}`).join('  ')
          : '-';
        if (stats.generated_at) {
          $('#srvTime').textContent = new Date(stats.generated_at * 1000).toLocaleTimeString();
        }
      }

      async function poll() {
        if (!store.key) {
          window.setTimeout(poll, 1000);
          return;
        }
        try {
          const response = await fetch(`clicks.php?after_id=${lastId}&timeout=20`, {
            headers: headers(),
            cache: 'no-store'
          });
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
          }
          const payload = await response.json();
          if (!payload.ok) {
            throw new Error('Bad payload');
          }
          if (Array.isArray(payload.hits) && payload.hits.length) {
            appendRows(payload.hits);
            lastId = payload.last_id || lastId;
          }
          if (payload.stats) {
            updateChart(payload.stats.series ?? []);
            applyStats(payload.stats);
          }
        } catch (error) {
          console.error('Poll error:', error);
        } finally {
          window.setTimeout(poll, 400);
        }
      }

      poll();
    });
  </script>
</body>
</html>
