// /gio/js/app.js
// Bootstrap: Shell, Router, Suche, Player, SongCards (SQL-Backend)

import { Router } from './modules/router.js';
import { store }  from './modules/store.js';
import { api }    from './modules/api.js';
import { initPlayer } from './modules/player.js';
import { render as renderSongCards } from './modules/songcards.js';

let mainEl = null;

/* ------------ Shell-Bindings ----------- */
function mountShellBindings() {
  document.getElementById('gio-toggle-sidebar')?.addEventListener('click', () => {
    document.getElementById('gio-sidebar')?.classList.toggle('collapsed');
  });

  const form  = document.getElementById('gio-search');
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

/* --------------- Views ----------------- */
async function loadHome() {
  const html = await fetch('/gio/views/home.php').then(r => r.text());
  mainEl.innerHTML = html;

  const trendEl = document.getElementById('home-trending');
  const newEl   = document.getElementById('home-new');

  try {
    const feed = await api.homeFeed();
    const trending = (feed.trending || []).map(s => ({
      id: s.id,
      title: s.title,
      artist_name: s.artist_name || '',
      audio_url: s.audio_url || '/gio/assets/audio/demo_song.mp3',
      cover_url: s.cover_url || '/gio/assets/img/cover_placeholder.jpg',
      duration_sec: Number(s.duration_sec || 0),
    }));
    const newest = (feed.newest || []).map(s => ({
      id: s.id,
      title: s.title,
      artist_name: s.artist_name || '',
      audio_url: s.audio_url || '/gio/assets/audio/demo_song.mp3',
      cover_url: s.cover_url || '/gio/assets/img/cover_placeholder.jpg',
      duration_sec: Number(s.duration_sec || 0),
    }));

    renderSongCards(trending, trendEl);
    renderSongCards(newest,   newEl);

    // Like-Counts nachladen
    const ids = [...new Set([...trending, ...newest].map(x => x.id))];
    if (ids.length) {
      const counts = await api.likeCounts(ids);
      for (const [id, c] of Object.entries(counts)) {
        const el = document.querySelector(`.songcard[data-id="${id}"] .like-count`);
        if (el) el.textContent = String(c);
      }
    }
  } catch (err) {
    console.error('HomeFeed Error', err); // console
    trendEl && (trendEl.innerHTML = '<p class="error">Fehler beim Laden.</p>');
    newEl   && (newEl.innerHTML   = '<p class="error">Fehler beim Laden.</p>');
  }
}

async function loadSearch(route) {
  const params = new URLSearchParams(route.query);
  const q = params.get('q') || store.search.q || '';

  const html = await fetch('/gio/views/search.php').then(r => r.text());
  mainEl.innerHTML = html;

  const label = document.getElementById('search-label');
  if (label) label.textContent = q;

  if (q.length < 2) return;

  const songsEl = document.getElementById('search-songs');
  const othersEl = document.getElementById('search-others');

  try {
    const data = await api.search({ q, type: 'all', limit: 20, offset: 0 });

    const songs = (data.songs || []).map(s => ({
      id: s.id,
      title: s.title,
      artist_name: s.artist_name || '',
      audio_url: s.audio_url || '/gio/assets/audio/demo_song.mp3',
      cover_url: s.cover_url || '/gio/assets/img/cover_placeholder.jpg',
      duration_sec: Number(s.duration_sec || 0),
    }));
    renderSongCards(songs, songsEl);

    const a = (data.artists || []).map(x => `* ${esc(x.name)} ${esc(x.genre || '')}`).join('\n') || '* keine Artists';
    const r = (data.releases || []).map(x => `* ${esc(x.title)} ${esc(x.type || '')}`).join('\n') || '* keine Releases';
    if (othersEl) {
      othersEl.innerHTML = [
        '#### Artists', '',
        `<pre>${a}</pre>`,
        '#### Releases', '',
        `<pre>${r}</pre>`
      ].join('\n');
    }

    // Like-Counts
    const ids = songs.map(s => s.id);
    if (ids.length) {
      const counts = await api.likeCounts(ids);
      for (const [id, c] of Object.entries(counts)) {
        const el = document.querySelector(`.songcard[data-id="${id}"] .like-count`);
        if (el) el.textContent = String(c);
      }
    }
  } catch (err) {
    console.error('Search Error', err); // console
    songsEl && (songsEl.innerHTML = '<p class="error">Fehler bei der Suche.</p>');
  }
}

/* --------------- Router --------------- */
async function render(route) {
  if (!mainEl) mainEl = document.getElementById('gio-main');

  if (route?.name === 'home') {
    await loadHome();
    return;
  }

  if (route?.name === 'search') {
    await loadSearch(route);
    return;
  }

  Router.navigateTo('#/home');
}

/* --------------- Init ----------------- */
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

/* --------------- Utils ---------------- */
function esc(v) {
  return String(v)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
