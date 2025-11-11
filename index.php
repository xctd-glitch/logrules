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
  <title>Smart Redirect Dashboard</title>
  <meta name="referrer" content="no-referrer">
  <meta name="theme-color" content="#0f172a">
  <meta name="application-name" content="Smart Redirect">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <link rel="icon" type="image/svg+xml" sizes="any" href="assets/icons/icon-192.svg">
  <link rel="apple-touch-icon" href="assets/icons/icon-192.svg">
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.7/dist/tailwind.min.css" integrity="sha384-+OYh4nHfTGUQ6faQSdqlxyVay7oRcppDAChDYyQfOrTcJ6YGp2wTuI+BAIvo6z3s" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" integrity="sha384-VlaQt1zArhcXd1LSeX776BF3/f6/Dr7guPmyAnbcWcCYwiVdc+GqOR/mdrIW6DCe" crossorigin="anonymous" defer></script>
</head>
<body>
  <div class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950/95 backdrop-blur">
      <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-6 text-white sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
          <p class="text-xs uppercase tracking-[0.4em] text-blue-300">Smart Redirect Platform</p>
          <h1 class="text-2xl font-semibold leading-tight sm:text-3xl">Command &amp; Control Dashboard</h1>
          <p class="text-sm text-slate-300">Monitor performa, atur konfigurasi, dan akses dokumentasi API dalam satu layar.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button id="toggleTheme" class="btn btn-ghost" type="button" aria-pressed="false" aria-label="Toggle theme">
            <span aria-hidden="true">🌗</span>
            <span class="hidden sm:inline">Mode</span>
          </button>
          <a class="btn btn-primary" href="realtime.php" rel="noreferrer">
            <span aria-hidden="true">📈</span>
            <span>Realtime Monitor</span>
          </a>
        </div>
      </div>
    </header>

    <main class="flex-1">
      <div class="mx-auto flex w-full max-w-7xl flex-col gap-10 px-4 py-10">
        
        <!-- Stats Section -->
        <section aria-labelledby="statsTitle" class="space-y-6">
          <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
              <h2 id="statsTitle" class="text-xl font-semibold text-white">Realtime Performance</h2>
              <p class="text-sm text-slate-300">Statistik 15 menit terakhir dengan indikator status sistem secara langsung.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <div class="status-indicator" id="statusIndicator">
                <span class="status-dot" id="statusPulse" aria-hidden="true"></span>
                <span id="statusText">Idle</span>
              </div>
              <button id="refreshStats" class="btn btn-primary" type="button" aria-label="Refresh statistics">
                <span aria-hidden="true">🔄</span>
                <span>Refresh</span>
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4" id="statsCards">
            <article class="card">
              <div class="card-header">
                <h3 class="card-title">Total Clicks (15m)</h3>
                <span class="text-xs text-muted" id="statsWindow">15m</span>
              </div>
              <p class="card-value" id="statTotal">0</p>
              <p class="card-description">Performa terbaru sistem.</p>
            </article>

            <article class="card">
              <div class="card-header">
                <h3 class="card-title">Variant A</h3>
                <span class="badge badge-success">Active</span>
              </div>
              <p class="card-value" id="statA">0</p>
              <p class="card-description">Distribusi ke landing page eksperimen A.</p>
            </article>

            <article class="card">
              <div class="card-header">
                <h3 class="card-title">Variant B</h3>
                <span class="badge badge-muted">Muted</span>
              </div>
              <p class="card-value" id="statB">0</p>
              <p class="card-description">Distribusi ke landing page eksperimen B.</p>
            </article>

            <article class="card">
              <h3 class="card-title">Top Countries</h3>
              <p class="card-value text-lg" id="statCountries">-</p>
              <p class="card-description">5 negara teratas berdasarkan volume klik.</p>
              <p class="mt-4 text-xs text-muted">Update <span id="statUpdated">-</span></p>
            </article>
          </div>

          <div class="card">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h3 class="text-sm font-semibold text-white">Traffic Trend</h3>
                <p class="text-xs text-slate-400">Per menit dalam jendela 15 menit. Kurva diperbarui otomatis setiap refresh.</p>
              </div>
              <div class="flex items-center gap-3 text-xs text-slate-400">
                <span class="inline-flex items-center gap-1">
                  <span class="h-2 w-2 rounded-full bg-sky-400"></span>Clicks/min
                </span>
                <span class="inline-flex items-center gap-1" id="chartStatus">
                  <span class="h-2 w-2 rounded-full bg-emerald-400"></span>Ready
                </span>
              </div>
            </div>
            <div class="mt-6 h-56 overflow-hidden rounded-xl bg-slate-900/60 p-3">
              <canvas id="statsChart" aria-label="Grafik per menit" role="img"></canvas>
            </div>
          </div>
        </section>

        <!-- Config Section -->
        <section aria-labelledby="configTitle" class="space-y-6">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h2 id="configTitle" class="text-xl font-semibold text-white">System Configuration</h2>
              <p class="text-sm text-slate-300">Kelola kredensial, status sistem, dan parameter routing dengan kontrol aman.</p>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-300">
              <span>Terakhir update:</span>
              <span class="rounded-full bg-slate-200/10 px-3 py-1 font-mono text-sm text-slate-100" id="updatedAt">-</span>
            </div>
          </div>

          <div class="space-y-4">
            <div id="remoteNote" class="hidden rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-100" role="status">
              Konfigurasi terbaru tersedia. Keluar dari input untuk memuat ulang otomatis atau
              <button id="btnReload" type="button" class="ml-1 inline-flex items-center gap-1 text-amber-200 underline-offset-4 hover:underline">
                reload sekarang
              </button>.
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
              <div class="space-y-6">
                <div class="card">
                  <div class="form-group">
                    <label class="form-label" for="apiKey">API Key (X-API-Key)</label>
                    <input id="apiKey" type="password" autocomplete="off" placeholder="tempel API key admin" class="form-input">
                    <p class="text-xs text-muted">Kunci disimpan lokal di browser (localStorage).</p>
                  </div>
                </div>

                <div class="card">
                  <div class="flex items-start justify-between gap-4">
                    <div>
                      <p class="text-sm font-semibold">System Switch</p>
                      <p class="text-xs text-muted">Aktifkan atau hentikan distribusi redirect secara instan.</p>
                    </div>
                    <button id="btnSystem" type="button" role="switch" aria-checked="false" class="switch" data-on="false">
                      <span class="switch-thumb">OFF</span>
                    </button>
                  </div>
                  <p class="mt-3 flex items-center gap-2 text-xs text-muted">
                    <span class="inline-flex h-2 w-2 rounded-full bg-rose-500" id="systemStatusDot" aria-hidden="true"></span>
                    <span id="systemStatusText">System OFF</span>
                  </p>
                </div>
              </div>

              <div class="card">
                <div class="space-y-4">
                  <div class="form-group">
                    <label class="form-label" for="redirectUrl">Redirect URL</label>
                    <input id="redirectUrl" type="url" inputmode="url" autocomplete="off" placeholder="https://example.com/path" class="form-input">
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="allowedCountries">Allowed Countries (CSV)</label>
                    <textarea id="allowedCountries" spellcheck="false" rows="6" class="form-textarea" placeholder="ID,US,GB"></textarea>
                  </div>

                  <div class="flex flex-wrap items-center gap-3">
                    <button id="btnSave" type="button" class="btn btn-primary">
                      <span aria-hidden="true">💾</span>
                      <span>Simpan Perubahan</span>
                    </button>
                    <p class="text-xs text-muted" id="saveFeedback">Menunggu perubahan…</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Logs Section -->
        <section aria-labelledby="logsTitle" class="space-y-4">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h2 id="logsTitle" class="text-xl font-semibold text-white">Realtime Logs</h2>
              <p class="text-sm text-slate-300">Klik untuk memuat snapshot 100 klik terakhir. Data dimuat malas untuk menjaga performa.</p>
            </div>
          </div>

          <details id="logsPanel" class="group rounded-2xl border border-white/15 bg-slate-950/80 p-6 shadow-xl transition">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-semibold text-white">
              <span class="flex items-center gap-2">
                <span aria-hidden="true">🗂️</span> Snapshot Terbaru
              </span>
              <span class="text-xs text-slate-400">Klik untuk expand</span>
            </summary>
            <div class="mt-6 space-y-4" id="logsContainer">
              <div id="logsSkeleton" class="grid gap-2">
                <div class="skeleton h-4"></div>
                <div class="skeleton h-4"></div>
                <div class="skeleton h-4"></div>
              </div>
              <div class="hidden scroll-area max-h-72 overflow-auto rounded-xl border border-slate-800/80 bg-slate-900/70 p-4 font-mono text-xs text-slate-200" id="logsList"></div>
              <p class="text-xs text-slate-400" id="logsMeta"></p>
            </div>
          </details>
        </section>

        <!-- API Docs Section -->
        <section aria-labelledby="docsTitle" class="space-y-6">
          <div>
            <h2 id="docsTitle" class="text-xl font-semibold text-white">API Documentation</h2>
            <p class="text-sm text-slate-300">Endpoint siap produksi dengan contoh kode siap salin.</p>
          </div>

          <div class="grid gap-6 lg:grid-cols-2">
            <article class="card">
              <header class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">GET /api/v1/stats</h3>
                <span class="badge badge-success">Active</span>
              </header>
              <p class="mt-3 text-sm text-muted">Mengambil agregasi klik window terakhir. Ideal untuk monitoring ringan.</p>
              <div class="code-block mt-4">
                <pre id="codeStats">curl -s \
  -H "X-API-Key: &lt;API_KEY&gt;" \
  "<?= htmlspecialchars('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/api.php?path=v1/stats&window=15', ENT_QUOTES, 'UTF-8') ?>"</pre>
              </div>
              <button data-copy="#codeStats" type="button" class="btn btn-secondary mt-4">
                📋 Salin Contoh
              </button>
            </article>

            <article class="card">
              <header class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">POST /api/v1/decision</h3>
                <span class="badge badge-muted">Muted</span>
              </header>
              <p class="mt-3 text-sm text-muted">Membuat keputusan redirect sekaligus logging klik. Gunakan payload valid dengan sanitasi ketat.</p>
              <div class="code-block mt-4">
                <pre id="codeDecision">curl -s -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "click_id": "CID123",
    "country_code": "ID",
    "user_agent": "Mozilla/5.0",
    "ip_address": "203.0.113.9",
    "user_lp": "https://landing.example"
  }' \
  "<?= htmlspecialchars('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/api.php?path=v1/decision', ENT_QUOTES, 'UTF-8') ?>"</pre>
              </div>
              <button data-copy="#codeDecision" type="button" class="btn btn-secondary mt-4">
                📋 Salin Contoh
              </button>
            </article>
          </div>
        </section>

      </div>
    </main>

    <div id="toast" class="toast hidden" role="status" aria-live="polite"></div>
  </div>

  <script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">
    'use strict';

    const root = document.documentElement;
    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => document.querySelectorAll(selector);

    const storage = {
      get key() { return localStorage.getItem('srp_key') ?? ''; },
      set key(value) { localStorage.setItem('srp_key', value); },
      get theme() { return localStorage.getItem('srp_theme') ?? 'dark'; },
      set theme(value) { localStorage.setItem('srp_theme', value); }
    };

    function applyTheme(mode) {
      if (mode === 'light') {
        root.classList.add('light');
      } else {
        root.classList.remove('light');
      }
      $('#toggleTheme').setAttribute('aria-pressed', mode === 'light' ? 'true' : 'false');
    }

    applyTheme(storage.theme);

    $('#toggleTheme').addEventListener('click', () => {
      const next = root.classList.contains('light') ? 'dark' : 'light';
      storage.theme = next;
      applyTheme(next);
    });

    $('#apiKey').value = storage.key;
    $('#apiKey').addEventListener('change', (event) => {
      storage.key = event.target.value.trim();
    });

    const toastEl = $('#toast');
    let toastTimer = 0;

    function toast(message, intent = 'success') {
      window.clearTimeout(toastTimer);
      toastEl.textContent = message;
      toastEl.classList.remove('hidden', 'toast-success', 'toast-error');
      toastEl.classList.add(intent === 'error' ? 'toast-error' : 'toast-success');
      toastEl.setAttribute('role', intent === 'error' ? 'alert' : 'status');
      toastTimer = window.setTimeout(() => {
        toastEl.classList.add('hidden');
      }, 2400);
    }

    const headers = () => ({
      'Content-Type': 'application/json',
      'X-API-Key': storage.key
    });

    let cfg = { system_on: false, redirect_url: '', allowed_countries: [], updated_at: 0 };
    let suspendRender = false;
    let pendingCfg = null;
    let savingSystem = false;

    ['redirectUrl', 'allowedCountries'].forEach((id) => {
      const element = document.getElementById(id);
      element.addEventListener('focus', () => { suspendRender = true; });
      element.addEventListener('blur', () => {
        suspendRender = false;
        if (pendingCfg) {
          cfg = pendingCfg;
          pendingCfg = null;
          render();
        }
        hideRemoteNote();
      });
    });

    function updateSystemSwitch(isOn) {
      const button = $('#btnSystem');
      const thumb = button.querySelector('.switch-thumb');
      button.dataset.on = isOn ? 'true' : 'false';
      button.setAttribute('aria-checked', isOn ? 'true' : 'false');
      thumb.textContent = isOn ? 'ON' : 'OFF';
      $('#systemStatusDot').style.backgroundColor = isOn ? 'var(--color-accent-success)' : 'var(--color-accent-danger)';
      $('#systemStatusText').textContent = isOn ? 'System ON' : 'System OFF';
    }

    function showRemoteNote() {
      $('#remoteNote').classList.remove('hidden');
    }

    function hideRemoteNote() {
      $('#remoteNote').classList.add('hidden');
    }

    function updateTimestamp(timestamp) {
      $('#updatedAt').textContent = timestamp ? new Date(timestamp * 1000).toLocaleString() : '-';
    }

    function render() {
      updateSystemSwitch(Boolean(cfg.system_on));
      updateTimestamp(cfg.updated_at ?? null);
      if (!suspendRender) {
        $('#redirectUrl').value = cfg.redirect_url ?? '';
        $('#allowedCountries').value = Array.isArray(cfg.allowed_countries)
          ? cfg.allowed_countries.join(', ')
          : '';
      }
    }

    async function loadConfig() {
      const response = await fetch('data.php', { headers: headers(), cache: 'no-store' });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const payload = await response.json();
      if (!payload.ok) {
        throw new Error('Payload tidak valid');
      }
      if (suspendRender) {
        pendingCfg = payload.config;
        showRemoteNote();
        return;
      }
      cfg = payload.config;
      render();
    }

    async function toggleSystem() {
      if (savingSystem) return;
      if (!storage.key) {
        toast('Isi API Key terlebih dahulu.', 'error');
        return;
      }

      const wasOn = $('#btnSystem').dataset.on === 'true';
      const nextState = !wasOn;
      updateSystemSwitch(nextState);
      savingSystem = true;

      try {
        const response = await fetch('data.php', {
          method: 'POST',
          headers: headers(),
          cache: 'no-store',
          body: JSON.stringify({ system_on: nextState })
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        if (!payload.ok) {
          throw new Error('Server menolak pembaruan.');
        }

        cfg.system_on = Boolean(payload.config.system_on);
        updateSystemSwitch(cfg.system_on);
        updateTimestamp(payload.config.updated_at ?? null);
        localStorage.setItem('srp_cfg_rev', String(Date.now()));
        toast(cfg.system_on ? 'System aktif.' : 'System nonaktif.');
      } catch (error) {
        updateSystemSwitch(wasOn);
        toast(`Gagal menyimpan status: ${error instanceof Error ? error.message : 'unknown'}`, 'error');
      } finally {
        savingSystem = false;
      }
    }

    $('#btnSystem').addEventListener('click', toggleSystem);

    $('#btnReload').addEventListener('click', () => {
      if (pendingCfg) {
        cfg = pendingCfg;
        pendingCfg = null;
      }
      render();
      hideRemoteNote();
    });

    async function saveNow() {
      if (!storage.key) {
        toast('Isi API Key terlebih dahulu.', 'error');
        return;
      }
      const body = {
        redirect_url: $('#redirectUrl').value.trim(),
        allowed_countries: $('#allowedCountries').value
      };
      $('#saveFeedback').textContent = 'Menyimpan…';
      const response = await fetch('data.php', {
        method: 'POST',
        headers: headers(),
        cache: 'no-store',
        body: JSON.stringify(body)
      });
      if (!response.ok) {
        $('#saveFeedback').textContent = 'Gagal menyimpan.';
        toast(`Gagal menyimpan: ${response.status}`, 'error');
        return;
      }
      const payload = await response.json();
      if (!payload.ok) {
        $('#saveFeedback').textContent = 'Server menolak perubahan.';
        toast('Server menolak perubahan.', 'error');
        return;
      }
      cfg = payload.config;
      render();
      toast('Konfigurasi tersimpan.');
      $('#saveFeedback').textContent = 'Perubahan tersimpan.';
      pendingCfg = null;
      suspendRender = false;
      localStorage.setItem('srp_cfg_rev', String(Date.now()));
    }

    $('#btnSave').addEventListener('click', saveNow);

    window.addEventListener('storage', (event) => {
      if (event.key === 'srp_cfg_rev') {
        loadConfig().catch(() => {});
      }
    });

    async function pollConfig() {
      try {
        await loadConfig();
      } catch (error) {
        $('#saveFeedback').textContent = 'Gagal sinkronisasi.';
      } finally {
        window.setTimeout(pollConfig, 300000);
      }
    }

    function formatCountries(top) {
      if (!Array.isArray(top) || top.length === 0) return '-';
      return top.map((country) => `${country.cc}:${country.c}`).join(' · ');
    }

    const statusIndicator = $('#statusIndicator');
    const statusPulse = $('#statusPulse');
    const statusText = $('#statusText');

    function setStatus(state) {
      statusIndicator.className = 'status-indicator';
      switch (state) {
        case 'loading':
          statusIndicator.classList.add('status-info');
          statusPulse.classList.add('pulse');
          statusText.textContent = 'Refreshing';
          break;
        case 'error':
          statusIndicator.classList.add('status-danger');
          statusPulse.classList.remove('pulse');
          statusText.textContent = 'Error';
          break;
        case 'auth':
          statusIndicator.classList.add('status-warning');
          statusPulse.classList.remove('pulse');
          statusText.textContent = 'API key?';
          break;
        default:
          statusIndicator.classList.add('status-success');
          statusPulse.classList.remove('pulse');
          statusText.textContent = 'Live';
      }
    }

    const chartStatus = $('#chartStatus');
    let statsChart;

    function ensureChart() {
      if (statsChart) return statsChart;
      const ctx = document.getElementById('statsChart');
      statsChart = new window.Chart(ctx, {
        type: 'line',
        data: {
          labels: [],
          datasets: [{
            label: 'Clicks/min',
            data: [],
            tension: 0.35,
            borderColor: '#38bdf8',
            borderWidth: 3,
            pointRadius: 2,
            pointBackgroundColor: '#0ea5e9',
            fill: true,
            backgroundColor: 'rgba(14,165,233,0.12)'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { display: true, ticks: { color: '#94a3b8', maxRotation: 0 } },
            y: { beginAtZero: true, ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.2)' } }
          }
        }
      });
      return statsChart;
    }

    function updateChart(series) {
      const chart = ensureChart();
      const labels = [];
      const values = [];
      (series ?? []).forEach((point) => {
        labels.push(new Date(point.ts * 1000).toLocaleTimeString());
        values.push(point.c);
      });
      chart.data.labels = labels;
      chart.data.datasets[0].data = values;
      chart.update();
    }

    function updateStatsUI(payload) {
      $('#statTotal').textContent = payload.total ?? 0;
      $('#statA').textContent = payload.a ?? 0;
      $('#statB').textContent = payload.b ?? 0;
      $('#statCountries').textContent = formatCountries(payload.top_countries ?? []);
      $('#statUpdated').textContent = payload.generated_at
        ? new Date(payload.generated_at * 1000).toLocaleTimeString()
        : '-';
      $('#statsWindow').textContent = `${payload.window ?? 15}m`;
      chartStatus.innerHTML = '<span class="h-2 w-2 rounded-full bg-emerald-400"></span><span>Sinkron</span>';
      updateChart(payload.series ?? []);
    }

    let statsTimer = 0;

    async function refreshStats() {
      if (!storage.key) {
        setStatus('auth');
        statsTimer = window.setTimeout(refreshStats, 15000);
        return;
      }
      window.clearTimeout(statsTimer);
      setStatus('loading');
      chartStatus.innerHTML = '<span class="h-2 w-2 rounded-full bg-sky-400 animate-pulse"></span><span>Updating</span>';
      try {
        const response = await fetch('api.php?path=v1/stats&window=15', {
          headers: { 'X-API-Key': storage.key },
          cache: 'no-store'
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        if (!payload.ok) {
          throw new Error('Payload tidak valid');
        }
        updateStatsUI(payload);
        setStatus('ok');
      } catch (error) {
        setStatus('error');
        chartStatus.innerHTML = '<span class="h-2 w-2 rounded-full bg-rose-500"></span><span>Error</span>';
        toast(`Gagal memuat statistik: ${error instanceof Error ? error.message : 'unknown'}`, 'error');
      } finally {
        statsTimer = window.setTimeout(refreshStats, 60000);
      }
    }

    $('#refreshStats').addEventListener('click', () => {
      refreshStats().catch(() => {});
    });

    let logsLoaded = false;

    async function loadLogs() {
      if (!storage.key) {
        toast('Isi API Key untuk memuat log.', 'error');
        return;
      }
      $('#logsSkeleton').classList.remove('hidden');
      const list = $('#logsList');
      list.classList.add('hidden');
      $('#logsMeta').textContent = '';
      try {
        const response = await fetch('clicks.php?after_id=0&timeout=1', {
          headers: { 'X-API-Key': storage.key },
          cache: 'no-store'
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        if (!payload.ok) {
          throw new Error('Payload tidak valid');
        }
        const hits = Array.isArray(payload.hits) ? payload.hits.slice(-100).reverse() : [];
        if (hits.length === 0) {
          list.textContent = 'Belum ada data.';
        } else {
          const lines = hits.map((hit) => {
            const ts = new Date(hit.ts * 1000).toLocaleTimeString();
            const cc = String(hit.cc ?? '').toUpperCase();
            const decision = hit.decision === 'A' ? 'A' : 'B';
            const cid = String(hit.cid ?? '').slice(0, 64);
            const lp = String(hit.lp ?? '');
            const ip = String(hit.ip ?? '');
            const ua = String(hit.ua ?? '').slice(0, 160);
            return `[${ts}] ${cc} · ${decision} · ${cid} · ${lp} · ${ip} · ${ua}`;
          });
          list.textContent = lines.join('\n');
        }
        $('#logsSkeleton').classList.add('hidden');
        list.classList.remove('hidden');
        $('#logsMeta').textContent = `Snapshot ${hits.length} klik. Server time: ${new Date((payload.server_time ?? Date.now() / 1000) * 1000).toLocaleTimeString()}`;
        logsLoaded = true;
      } catch (error) {
        $('#logsSkeleton').classList.add('hidden');
        toast(`Gagal memuat log: ${error instanceof Error ? error.message : 'unknown'}`, 'error');
      }
    }

    $('#logsPanel').addEventListener('toggle', (event) => {
      if (event.target.open && !logsLoaded) {
        loadLogs().catch(() => {});
      }
    });

    $$('button[data-copy]').forEach((button) => {
      button.addEventListener('click', () => {
        const targetSelector = button.getAttribute('data-copy');
        if (!targetSelector) return;
        const element = document.querySelector(targetSelector);
        if (!element) return;
        const text = element.textContent ?? '';
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          navigator.clipboard.writeText(text).then(() => {
            toast('Disalin ke clipboard.');
          }).catch(() => {
            toast('Clipboard gagal.', 'error');
          });
          return;
        }
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
          document.execCommand('copy');
          toast('Disalin ke clipboard.');
        } catch (e) {
          toast('Clipboard gagal.', 'error');
        }
        document.body.removeChild(textarea);
      });
    });

    loadConfig().catch(() => {
      $('#saveFeedback').textContent = 'Butuh API key untuk memuat konfigurasi.';
    });
    pollConfig();
    refreshStats().catch(() => {});

    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js.php').catch(() => {});
      });
    }
  </script>
</body>
</html>
