// Router + Suche + Likes
(() => {
  const main = document.getElementById('gio-main');
  if (!main) return;

  /* ---------- Hash & Navigation ---------- */
  function parseHash(h) {
    // "#/search?q=rostlaut" → { view:"search", params:{q:"rostlaut"} }
    const s = (h || '').replace(/^#\//, '');
    const [path, qstr] = s.split('?', 2);
    const params = {};
    if (qstr) {
      for (const kv of qstr.split('&')) {
        if (!kv) continue;
        const [k, v=''] = kv.split('=', 2);
        params[decodeURIComponent(k)] = decodeURIComponent(v);
      }
    }
    return { view: path || 'home', params };
  }

  function buildHash(view, params) {
    const usp = new URLSearchParams(params || {});
    const q = String(usp);
    return q ? `#/${view}?${q}` : `#/${view}`;
  }

  function load(view) {
    // view kann "search" oder "artists" sein; Query wird separat behandelt
    const [basename] = String(view).split('?', 1);
    fetch(`/gio/views/${basename}.php`, { credentials: 'same-origin' })
      .then(r => r.text())
      .then(html => { main.innerHTML = html; afterViewLoaded(basename); })
      .catch(err => { main.innerHTML = `Fehler: ${err.message}`; });
  }

  function navigateTo(view, params = {}, replace = false) {
    const hash = buildHash(view, params);
    const state = { view, params };
    if (replace) history.replaceState(state, '', hash);
    else history.pushState(state, '', hash);
    load(view);
  }

  // Delegiertes Routing für <a data-route> und href="#/..."
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a');
    if (!a) return;

    const dataRoute = a.getAttribute('data-route');
    if (dataRoute) {
      e.preventDefault();
      navigateTo(dataRoute);
      return;
    }

    const href = a.getAttribute('href') || '';
    if (href.startsWith('#/')) {
      e.preventDefault();
      const { view, params } = parseHash(href);
      navigateTo(view, params);
    }
  });

  // Back/Forward
  window.addEventListener('popstate', (e) => {
    const st = e.state;
    if (st && st.view) load(st.view);
    else {
      const { view } = parseHash(location.hash);
      load(view);
    }
  });

  // Initial
  const init = parseHash(location.hash);
  history.replaceState({ view: init.view, params: init.params }, '', buildHash(init.view, init.params));
  load(init.view);

  /* ---------- Header-Suche ---------- */
  function bindHeaderSearch() {
    const form = document.getElementById('gio-search-form');
    const input = document.getElementById('gio-search-input');
    if (!form || !input || form.dataset.bound === '1') return;
    form.dataset.bound = '1';

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const q = input.value.trim();
      if (!q) return;
      navigateTo('search', { q });
    });
  }
  bindHeaderSearch();

  /* ---------- View-spezifische Initialisierung ---------- */
  function afterViewLoaded(view) {
    if (view === 'search') {
      runSearchFromHash();
      bindHeaderSearch(); // falls Header neu gerendert wurde
    }
  }

  async function runSearchFromHash() {
    const { params } = parseHash(location.hash);
    const q = (params.q || '').trim();
    const out = document.getElementById('gio-search-output');
    if (!out) return;
    if (!q) { out.textContent = 'Bitte Suchbegriff eingeben…'; return; }

    out.textContent = 'Suche…';
    try {
      const r = await fetch(`/gio/api/search.php?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
      const j = await r.json();
      if (!j?.ok) { out.textContent = 'Fehler bei der Suche.'; return; }
      out.innerHTML = renderSearchResults(j, q);
      // interne Links in Ergebnissen funktionieren via globalem Click-Handler
    } catch (e) {
      out.textContent = 'Netzwerkfehler.';
    }
  }

  function renderSection(title, items, mapFn) {
    if (!items || !items.length) return '';
    const lis = items.map(mapFn).join('');
    return `
      <section class="gio-search-section">
        <h3>${title}</h3>
        <ul class="gio-search-list">${lis}</ul>
      </section>
    `;
  }

  function renderSearchResults(j, q) {
    const s = renderSection('Songs', j.songs, it =>
      `<li><a href="${it.route}">${escapeHtml(it.title)}</a><span class="sub"> – ${escapeHtml(it.artist || '')}</span></li>`
    );
    const a = renderSection('Artists', j.artists, it =>
      `<li><a href="${it.route}">${escapeHtml(it.name)}</a><span class="sub"> ${escapeHtml(it.genre || '')}</span></li>`
    );
    const p = renderSection('Pages', j.pages, it =>
      `<li><a href="${it.route}">${escapeHtml(it.title)}</a><span class="sub"> ${escapeHtml(it.snippet || '')}</span></li>`
    );

    const none = (!s && !a && !p) ? `<p>Keine Treffer für „${escapeHtml(q)}“.</p>` : '';
    return s + a + p + none;
  }

  function escapeHtml(x) {
    return String(x)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }
})();

/* ---------- Likes ---------- */
(() => {
  const btn = document.getElementById('gio-like-btn');
  const count = document.getElementById('gio-like-count');
  if (!btn || !count) return;
  if (btn.dataset.bound === '1') return;
  btn.dataset.bound = '1';

  let busy = false;

  async function toggleLike(songId) {
    if (!songId || busy) return;
    busy = true;
    btn.disabled = true;
    btn.style.opacity = "0.6";
    try {
      const r = await fetch('/gio/api/likes.toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ songId }),
        credentials: 'same-origin'
      });
      const j = await r.json();
      const data = j?.data || j || {};
      if (j?.ok) {
        const cnt = (typeof data.count !== 'undefined') ? data.count : 0;
        const liked = !!data.liked;
        count.textContent = String(cnt);
        btn.classList.toggle('is-liked', liked);
        if (window.gioStore) {
          const s = window.gioStore.getSongById?.(songId);
          if (s) window.gioStore.upsertSong({ ...s, likes: cnt });
        }
      }
    } catch (err) {
      console.error('Like-Error', err);
    } finally {
      btn.style.opacity = "1";
      btn.disabled = false;
      busy = false;
    }
  }

  btn.addEventListener('click', (e) => {
    e.preventDefault();
    const id = btn.dataset.songId || '';
    toggleLike(id);
  });

  window.gioLikesToggle = toggleLike;

  (function attachPlayerLikeSync(){
    if (!window.gioPlayer?.subscribe) return;
    window.gioPlayer.subscribe((type, payload) => {
      if (type !== 'player:load' && type !== 'player:play' && type !== 'player:loaded') return;
      const s = (payload && payload.song) || window.gioPlayer.getState?.().song;
      if (!s || !s.id) return;

      btn.dataset.songId = s.id;

      fetch(`/gio/api/likes.get.php?songId=${encodeURIComponent(s.id)}`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(j => {
          const data = j?.data || j || {};
          if (!j?.ok) return;
          const cnt = (typeof data.count !== 'undefined') ? data.count : 0;
          const liked = !!data.liked;
          count.textContent = String(cnt);
          btn.classList.toggle('is-liked', liked);
        })
        .catch(() => {});
    });
  })();
})();

/* ---------- Demo ---------- */
document.addEventListener('click', (e) => {
  const btn = e.target.closest('#demo-load');
  if (!btn) return;
  e.preventDefault();

  const TEST_ID = 'demo-002';
  const TEST_MP3 = '/gio/assets/audio/demo.mp3';
  const artistId = (window.gioStore?.getArtists?.()?.[0]?.id) || 'a_demo';

  window.gioStore?.upsertSong?.({ id: TEST_ID, title: 'Demo Song', artistId, src: TEST_MP3 });

  const likeBtn = document.getElementById('gio-like-btn');
  const likeCount = document.getElementById('gio-like-count');
  if (likeBtn) likeBtn.dataset.songId = TEST_ID;
  if (likeCount) likeCount.dataset.songId = TEST_ID;

  if (window.gioPlayer?.loadAndPlay) {
    window.gioPlayer.loadAndPlay(TEST_ID);
  } else if (typeof window.gioSetCurrentSong === 'function') {
    window.gioSetCurrentSong({ id: TEST_ID, title: 'Demo Song', artist: 'Demo Artist', src: TEST_MP3 });
  }
});
