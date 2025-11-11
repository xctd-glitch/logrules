<?php

declare(strict_types=1);

$nonce = base64_encode(random_bytes(18));

header('Content-Type: text/html; charset=utf-8');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!doctype html>
<html lang="id" class="page">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Smart Redirect Dashboard · Offline</title>
  <meta name="theme-color" content="#0f172a" />
  <meta name="referrer" content="no-referrer" />
  <meta name="application-name" content="Smart Redirect" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="mobile-web-app-capable" content="yes" />
  <link rel="manifest" href="manifest.webmanifest" />
  <link rel="icon" type="image/svg+xml" sizes="any" href="assets/icons/icon-192.svg" />
  <link rel="apple-touch-icon" href="assets/icons/icon-192.svg" />
  <style nonce="<?= htmlspecialchars($nonce, ENT_QUOTES) ?>">
    :root {
      color-scheme: light dark;
      font-family: 'Inter', 'SF Pro Text', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background-color: #0f172a;
      color: #e2e8f0;
    }

    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2.5rem 1.5rem;
      background: radial-gradient(circle at top, rgba(14, 165, 233, 0.18), transparent 55%), #0f172a;
    }

    .card {
      width: min(420px, 100%);
      padding: 2rem;
      border-radius: 1.25rem;
      background: rgba(15, 23, 42, 0.88);
      box-shadow: 0 40px 120px rgba(15, 23, 42, 0.45);
      border: 1px solid rgba(148, 163, 184, 0.25);
      text-align: center;
    }

    h1 {
      margin: 0 0 1rem 0;
      font-size: 1.5rem;
      letter-spacing: -0.02em;
    }

    p {
      margin: 0 0 1.5rem 0;
      color: rgba(226, 232, 240, 0.82);
      line-height: 1.6;
    }

    button {
      appearance: none;
      border: 0;
      border-radius: 9999px;
      background: linear-gradient(120deg, #0ea5e9, #38bdf8);
      color: #0f172a;
      font-weight: 600;
      padding: 0.75rem 1.5rem;
      font-size: 1rem;
      cursor: pointer;
      transition: transform 180ms ease, box-shadow 180ms ease;
      box-shadow: 0 18px 48px rgba(14, 165, 233, 0.35);
    }

    button:hover {
      transform: translateY(-1px);
      box-shadow: 0 26px 60px rgba(14, 165, 233, 0.45);
    }

    button:focus-visible {
      outline: 3px solid rgba(148, 163, 184, 0.6);
      outline-offset: 4px;
    }
  </style>
</head>
<body>
  <main class="card" role="status" aria-live="polite">
    <h1>Anda sedang offline</h1>
    <p>Smart Redirect Dashboard masih dapat digunakan secara terbatas. Periksa koneksi internet Anda lalu muat ulang halaman untuk sinkronisasi data terbaru.</p>
    <button type="button" id="reload" aria-label="Muat ulang dashboard">Muat ulang</button>
  </main>
  <script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES) ?>">
    document.getElementById('reload')?.addEventListener('click', () => {
      window.location.reload();
    });
  </script>
</body>
</html>
