/**
 * loading.js  |  Libix Technology
 * Animasi loading overlay cyan modern.
 * Muncul otomatis saat: navigasi halaman, reload, submit form, fetch/XHR.
 */

(function () {
  'use strict';

  /* ─────────────────────────────────────────────
     1. INJECT CSS
  ───────────────────────────────────────────── */
  const style = document.createElement('style');
  style.textContent = `
    /* Overlay  |  putih transparan agar tidak gelap */
    #fl-loading-overlay {
      position: fixed;
      inset: 0;
      z-index: 99999;
      background: rgba(255, 255, 255, 0.75);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.2s ease;
    }
    #fl-loading-overlay.fl-visible {
      opacity: 1;
      pointer-events: all;
    }

    /* Card tengah */
    .fl-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 18px;
      padding: 36px 48px;
      background: #fff;
      border: 2px solid #06b6d4;
      border-radius: 20px;
      box-shadow: 0 0 0 6px rgba(249,115,22,0.08), 0 8px 32px rgba(249,115,22,0.18);
      animation: fl-card-in 0.22s ease forwards;
    }
    @keyframes fl-card-in {
      from { transform: scale(0.9) translateY(10px); opacity: 0; }
      to   { transform: scale(1)   translateY(0);    opacity: 1; }
    }

    /* ── Spinner ring ── */
    .fl-spinner {
      position: relative;
      width: 60px;
      height: 60px;
    }
    .fl-spinner svg {
      width: 60px;
      height: 60px;
      animation: fl-rotate 0.9s linear infinite;
    }
    @keyframes fl-rotate {
      to { transform: rotate(360deg); }
    }
    /* Track tipis oranye muda */
    .fl-track {
      fill: none;
      stroke: rgba(249,115,22,0.15);
      stroke-width: 4;
    }
    /* Arc oranye solid */
    .fl-arc {
      fill: none;
      stroke: #06b6d4;
      stroke-width: 4;
      stroke-linecap: round;
      stroke-dasharray: 100 50;
      filter: drop-shadow(0 0 5px rgba(249,115,22,0.6));
    }

    /* Logo F di tengah spinner */
    .fl-logo {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 18px;
      font-weight: 800;
      color: #06b6d4;
      animation: fl-pulse 1.2s ease-in-out infinite;
      user-select: none;
    }
    @keyframes fl-pulse {
      0%, 100% { opacity: 1;   transform: scale(1);    }
      50%       { opacity: 0.5; transform: scale(0.92); }
    }

    /* ── Progress bar ── */
    .fl-bar-wrap {
      width: 120px;
      height: 3px;
      background: rgba(249,115,22,0.15);
      border-radius: 99px;
      overflow: hidden;
    }
    .fl-bar {
      height: 100%;
      width: 0%;
      border-radius: 99px;
      background: #06b6d4;
      box-shadow: 0 0 8px rgba(249,115,22,0.5);
      animation: fl-indeterminate 1.3s ease-in-out infinite;
    }
    @keyframes fl-indeterminate {
      0%   { width: 0%;   margin-left: 0%;   }
      40%  { width: 60%;  margin-left: 0%;   }
      70%  { width: 30%;  margin-left: 65%;  }
      100% { width: 0%;   margin-left: 100%; }
    }

    /* ── Teks status ── */
    .fl-text {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: #06b6d4;
    }

    /* ── Top bar tipis full-width ── */
    #fl-topbar {
      position: fixed;
      top: 0;
      left: 0;
      height: 3px;
      width: 0%;
      z-index: 100000;
      background: #06b6d4;
      box-shadow: 0 0 10px rgba(249,115,22,0.7);
      opacity: 0;
      transition: width 0.25s ease, opacity 0.3s ease;
    }
    #fl-topbar.fl-running {
      opacity: 1;
      animation: fl-topbar-fill 2s ease-out forwards;
    }
    @keyframes fl-topbar-fill {
      0%   { width: 0%;  }
      40%  { width: 60%; }
      80%  { width: 85%; }
      100% { width: 90%; }
    }
    #fl-topbar.fl-done {
      width: 100% !important;
      animation: none;
      opacity: 0;
      transition: width 0.15s ease, opacity 0.35s ease 0.1s;
    }
  `;
  document.head.appendChild(style);

  /* ─────────────────────────────────────────────
     2. INJECT HTML
  ───────────────────────────────────────────── */
  const overlay = document.createElement('div');
  overlay.id = 'fl-loading-overlay';
  overlay.setAttribute('role', 'status');
  overlay.setAttribute('aria-label', 'Memuat halaman');
  overlay.innerHTML = `
    <div class="fl-card">
      <div class="fl-spinner">
        <svg viewBox="0 0 60 60" aria-hidden="true">
          <circle class="fl-track" cx="30" cy="30" r="24"/>
          <circle class="fl-arc"   cx="30" cy="30" r="24"/>
        </svg>
        <div class="fl-logo">F</div>
      </div>
      <div class="fl-bar-wrap">
        <div class="fl-bar"></div>
      </div>
      <span class="fl-text" id="fl-text">Memuat...</span>
    </div>
  `;
  document.body.appendChild(overlay);

  /* Top bar */
  const topbar = document.createElement('div');
  topbar.id = 'fl-topbar';
  topbar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(topbar);

  /* ─────────────────────────────────────────────
     3. SHOW / HIDE
  ───────────────────────────────────────────── */
  let hideTimer = null;

  function showLoading(label) {
    clearTimeout(hideTimer);
    document.getElementById('fl-text').textContent = label || 'Memuat...';
    topbar.classList.remove('fl-done');
    topbar.classList.add('fl-running');
    overlay.classList.add('fl-visible');
  }

  function hideLoading() {
    topbar.classList.remove('fl-running');
    topbar.classList.add('fl-done');
    overlay.classList.remove('fl-visible');
    hideTimer = setTimeout(() => {
      topbar.classList.remove('fl-done');
      document.getElementById('fl-text').textContent = 'Memuat...';
    }, 500);
  }

  /* ─────────────────────────────────────────────
     4. TRIGGER: KLIK LINK
  ───────────────────────────────────────────── */
  document.addEventListener('click', function (e) {
    const anchor = e.target.closest('a[href]');
    if (!anchor) return;
    const href = anchor.getAttribute('href') || '';
    if (
      anchor.target === '_blank' ||
      href.startsWith('#') ||
      href.startsWith('javascript') ||
      href.startsWith('mailto') ||
      href.startsWith('tel') ||
      href === '' ||
      e.ctrlKey || e.metaKey || e.shiftKey
    ) return;
    showLoading('Membuka halaman...');
  });

  /* ─────────────────────────────────────────────
     5. TRIGGER: UNLOAD (reload / back / forward)
  ───────────────────────────────────────────── */
  window.addEventListener('beforeunload', function () {
    showLoading('Memuat...');
  });

  /* ─────────────────────────────────────────────
     6. TRIGGER: FORM SUBMIT (non-AJAX)
  ───────────────────────────────────────────── */
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.dataset.ajax === 'true' || form.id === 'reviewForm') return;
    showLoading('Memproses...');
  });

  /* ─────────────────────────────────────────────
     7. SELESAI LOAD
  ───────────────────────────────────────────── */
  if (document.readyState === 'complete') {
    hideLoading();
  } else {
    window.addEventListener('load', hideLoading);
  }

  /* ─────────────────────────────────────────────
     8. INTERCEPT FETCH / XHR (threshold 300ms)
  ───────────────────────────────────────────── */
  let fetchCount = 0;
  let fetchTimer = null;

  function fetchStart() {
    fetchCount++;
    clearTimeout(fetchTimer);
    fetchTimer = setTimeout(() => {
      if (fetchCount > 0) showLoading('Memuat data...');
    }, 300);
  }

  function fetchEnd() {
    fetchCount = Math.max(0, fetchCount - 1);
    if (fetchCount === 0) {
      clearTimeout(fetchTimer);
      hideLoading();
    }
  }

  const _fetch = window.fetch;
  window.fetch = function (...args) {
    const url = (typeof args[0] === 'string' ? args[0] : args[0]?.url) || '';
    if (url.includes('submit-review')) return _fetch.apply(this, args);
    fetchStart();
    return _fetch.apply(this, args).finally(fetchEnd);
  };

  const _XHROpen = XMLHttpRequest.prototype.open;
  XMLHttpRequest.prototype.open = function (...args) {
    this.addEventListener('loadstart', fetchStart);
    this.addEventListener('loadend',   fetchEnd);
    return _XHROpen.apply(this, args);
  };

  /* ─────────────────────────────────────────────
     9. PUBLIC API
  ───────────────────────────────────────────── */
  window.FlLoading = { show: showLoading, hide: hideLoading };

})();
