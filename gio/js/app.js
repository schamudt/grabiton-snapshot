// /gio/js/app.js
// Bootstrap: Shell-Bindings, Router, Suche, Player, SongCards
import { Router } from './modules/router.js';
import { store } from './modules/store.js';
import { api } from './modules/api.js';
import { initPlayer } from './modules/player.js';
import { renderSongCards } from './modules/songcards.js';

let mainEl = null;

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

async function render(route){
  if (!mainEl) mainEl = document.getElementById('gio-main');

  if (route.name === 'home') {
    const html = await fetch('/gio/views/home.php').then(r=>r.text());
    mainEl.innerHTML = html;

    // Demo-Daten bis DB aktiv ist
    const demo = [{
      id: 1,
      title: 'Demo Song',
      artist: 'Artist',
      audio_url: '/gio/assets/demo.mp3',
      cover_url: '/gio/assets/img/placeholder.jpg',
      duration: '3:17'
    }];
    const elNew = document.getElementById('home-new');
    const elTrend = document.getElementById('home-trending');
    renderSongCards(elNew, demo);
    renderSongCards(elTrend, []);
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
      const data = res?.data || {songs:[],artists:[],releases:[]};
      store.search.results = data;
      store.search.loading = false;

      // Songs als SongCards rendern
      const songs = (data.songs || []).map(s => ({
        id: s.id,
        title: s.title,
        artist: '', // optional später aus Join
        audio_url: '/gio/assets/demo.mp3', // bis echte URLs da sind
        cover_url: s.cover_url || '/gio/assets/img/placeholder.jpg',
        duration: toMMSS(s.duration_sec)
      }));
      renderSongCards(document.getElementById('search-songs'), songs);

      // Artists/Releases minimal
      const others = document.getElementById('search-others');
      if (others) {
        const a = (data.artists||[]).map(x=>`<li>${esc(x.name)} <span class="muted">${esc(x.genre||'')}</span></li>`).join('') || '<li class="muted">keine Artists</li>';
        const r = (data.releases||[]).map(x=>`<li>${esc(x.title)} <span class="muted">${esc(x.type||'')}</span></li>`).join('') || '<li class="muted">keine Releases</li>';
        others.innerHTML = `<h4>Artists</h4><ul>${a}</ul><h4>Releases</h4><ul>${r}</ul>`;
      }
    }
    return;
  }

  Router.navigateTo('#/home');
}

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

/* -------- Utils -------- */
function toMMSS(sec){
  const n = Number(sec||0);
  const m = Math.floor(n/60);
  const s = Math.floor(n%60);
  return (m>0 || s>0) ? `${String(m).padStart(1,'0')}:${String(s).padStart(2,'0')}` : '';
}
function esc(v){
  return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
