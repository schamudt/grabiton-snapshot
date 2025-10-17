// /gio/js/app.js
// Bootstrap: Shell-Bindings, Router, Suche, Player, Rendering
import { Router } from './modules/router.js';
import { store } from './modules/store.js';
import { api } from './modules/api.js';
import { initPlayer } from './modules/player.js';

let mainEl = null;

/* -------- Rendering-Helfer für die Suchseite -------- */
function esc(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

function renderResults(r){
  const songs = Array.isArray(r?.songs) ? r.songs : [];
  const artists = Array.isArray(r?.artists) ? r.artists : [];
  const releases = Array.isArray(r?.releases) ? r.releases : [];

  const s = songs.length
    ? songs.map(x=>`<li><strong>${esc(x.title)}</strong> <span class="muted">#${esc(x.id)}</span></li>`).join('')
    : '<li class="muted">keine Songs</li>';

  const a = artists.length
    ? artists.map(x=>`<li><strong>${esc(x.name)}</strong> <span class="muted">${esc(x.genre||'')}</span></li>`).join('')
    : '<li class="muted">keine Artists</li>';

  const rel = releases.length
    ? releases.map(x=>`<li><strong>${esc(x.title)}</strong> <span class="muted">${esc(x.type||'')}</span></li>`).join('')
    : '<li class="muted">keine Releases</li>';

  return `
    <h3>Songs</h3>
    <ul>${s}</ul>
    <h3>Artists</h3>
    <ul>${a}</ul>
    <h3>Releases</h3>
    <ul>${rel}</ul>
  `;
}

/* ----------------- Shell-Bindings ------------------- */
function mountShellBindings(){
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

/* ------------------- Routing-Render ------------------ */
async function render(route){
  if (!mainEl) mainEl = document.getElementById('gio-main');

  if (route.name === 'home') {
    const html = await fetch('/gio/views/home.php').then(r=>r.text());
    mainEl.innerHTML = html;
    return;
  }

  if (route.name === 'search') {
    const params = new URLSearchParams(route.query);
    const q = params.get('q') || store.search.q || '';
    const html = await fetch('/gio/views/search.php').then(r=>r.text());
    mainEl.innerHTML = html;

    const label = document.getElementById('search-label');
    if (label) label.textContent = q;

    if (q.length >= 2) {
      store.search.loading = true;
      const res = await api.search(q);
      store.search.results = res?.data || {songs:[],artists:[],releases:[]};
      store.search.loading = false;

      const out = document.getElementById('search-out');
      if (out) out.innerHTML = renderResults(store.search.results);
    } else {
      const out = document.getElementById('search-out');
      if (out) out.innerHTML = '<span class="muted">mind. 2 Zeichen eingeben…</span>';
    }
    return;
  }

  Router.navigateTo('#/home');
}

/* -------------------- Bootstrap ---------------------- */
function mainInit(){
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
