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
