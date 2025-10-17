// Bootstrap: Shell-Bindings, Router, Suche, Player
import { Router } from './modules/router.js';
import { store } from './modules/store.js';
import { api } from './modules/api.js';
import { initPlayer } from './modules/player.js';

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
    return;
  }

  if (route.name === 'search') {
    const params = new URLSearchParams(route.query);
    const q = params.get('q') || store.search.q || '';
    const html = await fetch('/gio/views/search.php').then(r=>r.text());
    mainEl.innerHTML = html;
    if (q.length >= 2) {
      store.search.loading = true;
      const res = await api.search(q);
      store.search.results = res?.data || {songs:[],artists:[],releases:[]};
      store.search.loading = false;
      const out = document.getElementById('search-out');
      if (out) out.textContent = JSON.stringify(store.search.results, null, 2);
      const label = document.getElementById('search-label');
      if (label) label.textContent = q;
    }
    return;
  }
// nach: store.search.results = res?.data || {songs:[],artists:[],releases:[]};
const out = document.getElementById('search-out');
const label = document.getElementById('search-label');
if (label) label.textContent = q;
if (out) out.innerHTML = renderResults(store.search.results);
  Router.navigateTo('#/home');
}

function mainInit(){
  mainEl = document.getElementById('gio-main');
  mountShellBindings();
  initPlayer();                 // setzt window.player
  Router.initRouter(render);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mainInit);
} else {
  mainInit();
}

// Hilfsfunktion oben in app.js platzieren
function renderResults(r){
  const s = (r.songs||[]).map(x=>`<li>${x.title} <span class="muted">(#${x.id})</span></li>`).join('') || '<li class="muted">keine Songs</li>';
  const a = (r.artists||[]).map(x=>`<li>${x.name} <span class="muted">(${x.genre||''})</span></li>`).join('') || '<li class="muted">keine Artists</li>';
  const rel = (r.releases||[]).map(x=>`<li>${x.title} <span class="muted">(${x.type||''})</span></li>`).join('') || '<li class="muted">keine Releases</li>';
  return `
    <h3>Songs</h3><ul>${s}</ul>
    <h3>Artists</h3><ul>${a}</ul>
    <h3>Releases</h3><ul>${rel}</ul>
  `;
}
