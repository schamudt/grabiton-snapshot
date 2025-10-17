// /gio/js/app.js
// Bootstrap: Shell-Bindings, Router, Suche, Player, SongCards

import { Router } from './modules/router.js';
import { store } from './modules/store.js';
import { api } from './modules/api.js';
import { initPlayer } from './modules/player.js';
import { render as renderSongCards } from './modules/songcards.js';

let mainEl = null;

/* ---------- Shell ---------- */
function mountShellBindings() {
  document.getElementById('gio-toggle-sidebar')?.addEventListener('click', () => {
    document.getElementById('gio-sidebar')?.classList.toggle('collapsed');
  });

  const form = document.getElementById('gio-search');
  const input = document.getElementById('gio-q');

  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const q = (input?.value || '').trim();
    if (q.length < 2) return;
    Router.navigateTo(`#/search?q=${encodeURIComponent(q)}`);
  });

  input?.addEventListener('input', (e) => {
    store.search.q = e.target.value;
  });
}

/* ---------- HOME VIEW (SQL-Feed) ---------- */
async function loadHome() {
  if (!mainEl) mainEl = document.getElementById('gio-main');

  // view skeleton laden
  mainEl.innerHTML = '<div class="loading">Lade Inhalte…</div>';
  const html = await fetch('/gio/views/home.php').then(r => r.text());
  mainEl.innerHTML = html;

  const elNew = document.getElementById('home-new');
  const elTrend = document.getElementById('home-trending');

  try {
    const feed = await api.homeFeed(); // { trending:[], newest:[] }

    // SongCards rendern
    renderSongCards(elTrend, Array.isArray(feed.trending) ? feed.trending : []);
    renderSongCards(elNew, Array.isArray(feed.newest) ? feed.newest : []);

    // Like-Counts nachladen und einblenden
    const ids = [
      ...new Set([
        ...(feed.trending || []).map(s => s.id),
        ...(feed.newest || []).map(s => s.id),
      ]),
    ];
    if (ids.length) {
      const counts = await api.likeCounts(ids);
      for (const [id, c] of Object.entries(counts)) {
        const el = document.querySelector(`.songcard[data-id="${id}"] .like-count`);
        if (el) el.textContent = String(c);
      }
    }
  } catch (err) {
    console.error('HomeFeed Error', err); // console
    mainEl.querySelector('.loading')?.remove();
    const msg = document.createElement('p');
    msg.className = 'error';
    msg.textContent = 'Fehler beim Laden des Feeds.';
    mainEl.appendChild(msg);
  }
}

/* ---------- SEARCH VIEW (SQL) ---------- */
async function loadSearch(route) {
  if (!mainEl) mainEl = document.getElementById('gio-main');

  const params = new URLSearchParams(route.query);
  const q = params.get('q') || store.search.q || '';

  const html = await fetch('/gio/views/search.php').then(r => r.text());
  mainEl.innerHTML = html;

  const label = document.getElementById('search-label');
  if (label) label.textContent = q;

  if (q.length < 2) return;

  try {
    store.search.loading = true;

    // API liefert {query,type,songs,artists,releases,page}
    const data = await api.search({ q, type: 'all', limit: 20, offset: 0 });

    store.search.results = data;
    store.search.loading = false;

    // Songs als SongCards rendern
    const songs = (data.songs || []).map(s => ({
      id: s.id,
      title: s.title,
      artist: s.artist_name || '',
      audio_url: s.audio_url || '/gio/assets/audio/demo_song.mp3', // bis echte URLs vorhanden sind
      cover_url: s.cover_url || '/gio/assets/img/placeholder.jpg',
      duration: toMMSS(s.duration_sec),
    }));
    renderSongCards(document.getElementById('search-songs'), songs);

    // Artists / Releases einfach listen
    const others = document.getElementById('search-others');
    if (others) {
      const a = (data.artists || []).map(x => `* ${esc(x.name)} ${esc(x.genre || '')}`).join('\n') || '* keine Artists';
      const r = (data.releases || []).map(x => `* ${esc(x.title)} ${esc(x.type || '')}`).join('\n') || '* keine Releases';
      others.innerHTML = `
#### Artists

${escHtml(a)}
#### Releases

${escHtml(r)}
`;
    }

    // Like-Counts für Suchsongs
    const ids = songs.map(s => s.id);
    if (ids.length) {
      const counts = await api.likeCounts(ids);
      for (const [id, c] of Object.entries(counts)) {
        const el = document.querySelector(`.songcard[data-id="${id}"] .like-count`);
        if (el) el.textContent = String(c);
      }
    }
  } catch (err) {
    store.search.loading = false;
    console.error('Search Error', err); // console
    const el = document.getElementById('search-songs');
    if (el) el.innerHTML = '<p class="error">Fehler bei der Suche.</p>';
  }
}

/* ---------- Router ---------- */
async function render(route) {
  if (!mainEl) mainEl = document.getElementById('gio-main');

  if (route.name === 'home') {
    await loadHome();
    return;
  }

  if (route.name === 'search') {
    await loadSearch(route);
    return;
  }

  // Fallback
  Router.navigateTo('#/home');
}

/* ---------- Init ---------- */
function mainInit() {
  mainEl = document.getElementById('gio-main');
  mountShellBindings();
  initPlayer();
  Router.initRouter(render);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mainInit);
} else {
  mainInit();
}

/* ---------- Utils ---------- */
function toMMSS(sec) {
  const n = Number(sec || 0);
  const m = Math.floor(n / 60);
  const s = Math.floor(n % 60);
  if (m === 0 && s === 0) return '';
  return `${String(m).padStart(1, '0')}:${String(s).padStart(2, '0')}`;
}

function esc(v) {
  return String(v)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
function escHtml(txt) {
  // ersetzt Zeilenumbrüche in <pre>-ähnlicher Darstellung
  return `<pre>${esc(txt)}</pre>`;
}

// Für manuelle Tests
window.loadHome = loadHome;
