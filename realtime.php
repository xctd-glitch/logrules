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
    "script-src 'self' https://cdn.jsdelivr.net 'nonce-$nonce'; " .
    "style-src 'self' 'nonce-$nonce'; img-src 'self' data:; connect-src 'self'; font-src 'self'; " .
    "manifest-src 'self'; worker-src 'self'; base-uri 'self'; frame-ancestors 'none';"
);
?>
<!doctype html>
<html lang="id" class="page">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Realtime Clicks · SRP</title>
  <meta name="referrer" content="no-referrer" />
  <meta name="theme-color" content="#0f172a" />
  <meta name="application-name" content="Smart Redirect" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="mobile-web-app-capable" content="yes" />
  <link rel="icon" type="image/svg+xml" sizes="any" href="assets/icons/icon-192.svg" />
  <link rel="apple-touch-icon" href="assets/icons/icon-192.svg" />
  <link rel="manifest" href="manifest.webmanifest" />
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin />
  <style nonce="<?= htmlspecialchars($nonce, ENT_QUOTES) ?>">
    :root {
      color-scheme: light dark;
      --bg: #f8fafc;
      --fg: #111827;
      --card-bg: #ffffff;
      --card-border: #e5e7eb;
      --muted: #6b7280;
      --pill-a-bg: #ecfdf5;
      --pill-a-fg: #047857;
      --pill-b-bg: #fef2f2;
      --pill-b-fg: #b91c1c;
      --radius: 0.5rem;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    html.dark {
      --bg: #0f172a;
      --fg: #e2e8f0;
      --card-bg: #1e293b;
      --card-border: #334155;
      --muted: #94a3b8;
      --pill-a-bg: #064e3b;
      --pill-a-fg: #bbf7d0;
      --pill-b-bg: #7f1d1d;
      --pill-b-fg: #fecaca;
    }
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      min-height: 100vh;
      background: var(--bg);
      color: var(--fg);
      font-size: 16px;
      line-height: 1.5;
    }
    a {
      color: inherit;
      text-decoration: none;
    }
    .container {
      max-width: 1080px;
      margin: 0 auto;
      padding: 1.5rem;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }
    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      flex-wrap: wrap;
    }
    h1 {
      font-size: 1.5rem;
      margin: 0;
    }
    .controls {
      display: inline-flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.5rem 0.85rem;
      border-radius: var(--radius);
      border: 1px solid var(--card-border);
      background: var(--card-bg);
      color: inherit;
      cursor: pointer;
      font-weight: 500;
    }
    .btn:focus-visible {
      outline: 2px solid #2563eb;
      outline-offset: 2px;
    }
    .card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: var(--radius);
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
      padding: 1.25rem;
    }
    .muted {
      font-size: 0.8rem;
      color: var(--muted);
    }
    .pill {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.5rem;
      border-radius: 999px;
      font-size: 0.75rem;
      border: 1px solid transparent;
    }
    .pill-a {
      background: var(--pill-a-bg);
      color: var(--pill-a-fg);
      border-color: rgba(16, 185, 129, 0.45);
    }
    .pill-b {
      background: var(--pill-b-bg);
      color: var(--pill-b-fg);
      border-color: rgba(239, 68, 68, 0.45);
    }
    table {
      border-collapse: collapse;
      width: 100%;
      font-size: 0.88rem;
    }
    th, td {
      padding: 0.6rem 0.75rem;
      border-bottom: 1px solid var(--card-border);
      vertical-align: middle;
    }
    th {
      text-align: left;
      color: var(--muted);
      position: sticky;
      top: 0;
      background: var(--card-bg);
      z-index: 1;
    }
    tbody tr:hover {
      background: rgba(148, 163, 184, 0.15);
    }
    input {
      width: 100%;
      padding: 0.6rem 0.75rem;
      border-radius: var(--radius);
      border: 1px solid var(--card-border);
      background: #fff;
      color: #111827;
      font-size: 0.95rem;
    }
    html.dark input {
      background: #0f172a;
      border-color: #334155;
      color: #e2e8f0;
    }
    .grid {
      display: grid;
      gap: 1rem;
    }
    @media (min-width: 768px) {
      .grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" integrity="sha384-VlaQt1zArhcXd1LSeX776BF3/f6/Dr7guPmyAnbcWcCYwiVdc+GqOR/mdrIW6DCe" crossorigin="anonymous" defer></script>
</head>
<body>
  <div class="container">
    <header>
      <h1>Realtime Clicks</h1>
      <div class="controls">
        <button id="toggleTheme" class="btn" type="button" aria-pressed="false">🌗</button>
        <a href="index.php" class="btn" rel="noreferrer">Dashboard</a>
      </div>
    </header>

    <section class="card" aria-label="Autentikasi">
      <label class="muted" for="apiKey">API Key (X-API-Key)</label>
      <input id="apiKey" type="password" autocomplete="off" placeholder="tempel API key untuk admin" />
    </section>

    <section class="grid">
      <div class="card">
        <div class="muted">Total (15m)</div>
        <div id="mTotal" style="font-size:2rem;font-weight:600;">0</div>
        <div style="margin-top:0.75rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
          <span class="pill pill-a">A: <span id="mA">0</span></span>
          <span class="pill pill-b">B: <span id="mB">0</span></span>
        </div>
      </div>
      <div class="card">
        <div class="muted">Top Countries (15m)</div>
        <div id="mCountries" style="margin-top:0.75rem;font-family:'JetBrains Mono','SFMono-Regular',Consolas,monospace;">-</div>
      </div>
      <div class="card">
        <div class="muted">Server Time</div>
        <div id="srvTime" style="font-size:2rem;font-weight:600;">-</div>
      </div>
    </section>

    <section class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;">
        <div class="muted">Traffic (per menit, 15m)</div>
        <div class="muted">auto refresh</div>
      </div>
      <canvas id="chart" height="120" style="margin-top:1rem;"></canvas>
    </section>

    <section class="card" style="overflow:auto;">
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
    </section>
  </div>

  <script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES) ?>">
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
        get key() {
          return localStorage.getItem('srp_key') ?? '';
        },
        set key(value) {
          localStorage.setItem('srp_key', value);
        }
      };

      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js.php').catch(() => {});
      }

      const html = document.documentElement;
      const toggleTheme = $('#toggleTheme');
      toggleTheme.addEventListener('click', () => {
        html.classList.toggle('dark');
        toggleTheme.setAttribute('aria-pressed', String(html.classList.contains('dark')));
      });

      $('#apiKey').value = store.key;
      $('#apiKey').addEventListener('change', (event) => {
        store.key = event.target.value.trim();
      });

      const headers = () => ({ 'X-API-Key': store.key });
      const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      })[char]);
      const formatTime = (timestamp) => new Date(timestamp * 1000).toLocaleTimeString();

      let lastId = 0;
      let chart;
      const chartLabels = [];
      const chartData = [];

      function ensureChart() {
        if (chart) {
          return;
        }
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
              backgroundColor: 'rgba(37,99,235,0.25)'
            }]
          },
          options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
              x: { display: true },
              y: { beginAtZero: true }
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
          row.innerHTML = `
            <td>${formatTime(hit.ts)}</td>
            <td style="text-transform:uppercase;font-family:'JetBrains Mono',monospace;text-align:center;">${escape(hit.cc)}</td>
            <td style="text-align:center;">${hit.decision === 'A' ? '<span class="pill pill-a">A</span>' : '<span class="pill pill-b">B</span>'}</td>
            <td style="font-family:'JetBrains Mono',monospace;">${escape(hit.cid)}</td>
            <td style="font-family:'JetBrains Mono',monospace;">${escape(hit.lp)}</td>
            <td style="font-family:'JetBrains Mono',monospace;">${escape(hit.ip)}</td>
            <td style="font-family:'JetBrains Mono',monospace;">${escape(hit.device ?? '')}</td>
            <td>${escape(hit.ua.slice(0, 120))}${hit.ua.length > 120 ? '…' : ''}</td>`;
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
          /* transient failure ignored */
        } finally {
          window.setTimeout(poll, 400);
        }
      }

      poll();
    });
  </script>
</body>
</html>
