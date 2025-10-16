// Router + Likes + Demo-Hook (ohne direkte Audio-/Counter-Logik)
(() => {
  const main = document.getElementById('gio-main');

  function load(view) {
    fetch(`/gio/views/${view}.php`, { credentials: 'same-origin' })
      .then(r => r.text())
      .then(html => { main.innerHTML = html; })
      .catch(err => { main.innerHTML = `\nFehler: ${err.message}\n`; });
  }

  // Delegiertes Routing
  document.addEventListener('click', (e) => {
    const link = e.target.closest('[data-route]');
    if (!link) return;
    e.preventDefault();
    const view = link.getAttribute('data-route');
    load(view);
    history.pushState({ view }, '', `#/${view}`);
  });

  window.addEventListener('popstate', (e) => {
    const view = (e.state && e.state.view) || 'home';
    load(view);
  });

  const initial = location.hash.replace('#/','') || 'home';
  load(initial);
})();

// Stabiler Like-Button am Player (kein Audiozugriff)
(() => {
  const btn = document.getElementById('gio-like-btn');
  const count = document.getElementById('gio-like-count');
  if (!btn || !count) return;

  let busy = false;

  // FIX: JSON-Body {songId} statt FormData
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

  // Exponiere für andere Views
  window.gioLikesToggle = toggleLike;

  // --- Punkt 5: Initialen Like-Status beim Songwechsel holen ---
  (function attachPlayerLikeSync(){
    if (!window.gioPlayer) return;
    window.gioPlayer.subscribe((type, payload) => {
      if (type !== 'player:load' && type !== 'player:play' && type !== 'player:loaded') return;
      const s = (payload && payload.song) || window.gioPlayer.getState().song;
      if (!s || !s.id) return;

      // Button an aktuelle Song-ID koppeln
      btn.dataset.songId = s.id;

      // Aktuellen Like-Status vom Server holen
      fetch(`/gio/api/likes.get.php?songId=${encodeURIComponent(s.id)}`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(j => {
          const data = j?.data || j || {};
          if (!j?.ok) return;
          const cnt = (typeof data.count !== 'undefined') ? data.count : 0;
          const liked = !!(data.liked);
          count.textContent = String(cnt);
          btn.classList.toggle('is-liked', liked);
        })
        .catch(() => {});
    });
  })();
})();

// Demo-Button: Song in Store registrieren und über Player-Core abspielen
document.addEventListener('click', (e) => {
  const btn = e.target.closest('#demo-load');
  if (!btn) return;
  e.preventDefault();

  const TEST_ID = 'demo-002';
  const TEST_MP3 = '/gio/assets/audio/demo.mp3';
  const artistId = (window.gioStore?.getArtists()?.[0]?.id) || 'a_demo';

  // sicherstellen, dass der Track im Store existiert
  if (window.gioStore?.upsertSong) {
    window.gioStore.upsertSong({ id: TEST_ID, title: 'Demo Song', artistId, src: TEST_MP3 });
  }

  // Like-UI an neue ID hängen (wird durch Player-Subscribe ebenfalls gesetzt)
  const likeBtn = document.getElementById('gio-like-btn');
  const likeCount = document.getElementById('gio-like-count');
  if (likeBtn) likeBtn.dataset.songId = TEST_ID;
  if (likeCount) likeCount.dataset.songId = TEST_ID;

  // Abspielen über Player-Core
  if (window.gioPlayer?.loadAndPlay) {
    window.gioPlayer.loadAndPlay(TEST_ID);
  } else if (typeof window.gioSetCurrentSong === 'function') {
    window.gioSetCurrentSong({ id: TEST_ID, title: 'Demo Song', artist: 'Demo Artist', src: TEST_MP3 });
  }
});
