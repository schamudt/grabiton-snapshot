// SPA-Light Router + Player + Carousel + Sidebar Toggle
// + Likes (robust, optimistic) + PlayCounter(30s)
// + Auto-Queue: nach Songende alle "Angesagt"-Titel einmal durchspielen
(() => {
  const $  = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));

  const main = $('#gio-main');

  /* ------------ Routing ------------- */
  function pathToFragment(pathname){
    const base = '/grabiton';
    let p = pathname.startsWith(base) ? pathname.slice(base.length) : pathname;
    if (p === '' || p === '/' ) return 'fragments/home.php';
    if (p.startsWith('/explore'))  return 'fragments/explore.php';
    if (p.startsWith('/artists'))  return 'fragments/artists.php';
    if (p.startsWith('/releases')) return 'fragments/releases.php';
    return 'fragments/home.php';
  }

  async function loadFragmentByPath(pathname, push){
    const url = pathToFragment(pathname);
    try{
      const res  = await fetch('/grabiton/' + url, { credentials:'same-origin', cache:'no-store' });
      const html = await res.text();
      main.innerHTML = html;
      if (push) history.pushState({p: pathname}, '', pathname);

      bindCovers();
      bindCarousel();
      initLikeCounts(main);   // Zähler/Status laden (Klick-Handler global)
      updateActiveLinks(pathname);
    }catch(e){
      main.innerHTML = `<section class="gio-section"><h1 class="gio-h1">Fehler</h1><p>Inhalt konnte nicht geladen werden.</p></section>`;
    }
  }

  function updateActiveLinks(pathname){
    $$('.gio-navlink, .gio-toplink').forEach(a => a.classList.remove('is-active'));
    if (pathname === '/grabiton/' || pathname === '/grabiton'){
      $$('.gio-nav a[href="/grabiton/"]').forEach(a => a.classList.add('is-active'));
    } else {
      $(`.gio-nav a[href="${pathname}"]`)?.classList.add('is-active');
    }
  }

  function interceptLinks(){
    document.addEventListener('click', (e) => {
      const a = e.target.closest('a[href]');
      if (!a) return;
      if (a.dataset.nav !== 'push') return;
      const href = a.getAttribute('href');
      if (!href || href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('#')) return;
      e.preventDefault();
      loadFragmentByPath(new URL(href, location.origin).pathname, true);
    });
    window.addEventListener('popstate', () => loadFragmentByPath(location.pathname, false));
  }

  /* ------------ Sidebar Toggle ------------ */
  const SIDEBAR_KEY = 'gio_sidebar_collapsed_v1';
  function setSidebarCollapsed(collapsed){
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    const btn = $('.gio-side-toggle');
    if (btn) btn.setAttribute('aria-expanded', (!collapsed).toString());
    try{ sessionStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0'); }catch{}
  }
  function restoreSidebar(){
    const collapsed = (sessionStorage.getItem(SIDEBAR_KEY) === '1');
    setSidebarCollapsed(collapsed);
  }
  function bindSidebarToggle(){
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.gio-side-toggle');
      if (!btn) return;
      e.preventDefault();
      const nowCollapsed = !document.body.classList.contains('sidebar-collapsed');
      setSidebarCollapsed(nowCollapsed);
    });
  }

  /* ------------ Player ------------ */
  const playerBar   = $('.gio-playerbar');
  const audio       = $('#gio-audio');
  const timeCurEl   = $('.gio-player-timecurrent');
  const timeDurEl   = $('.gio-player-timedur');
  const titleEl     = $('.gio-player-title');
  const artistEl    = $('.gio-player-artist');
  const coverEl     = $('.gio-player-cover');
  const sliderEl    = $('.gio-player-slider');
  const muteBtn     = $('.gio-mute');
  const volSlider   = $('.gio-vol');

  const fmt = (sec) => {
    if (!isFinite(sec)) return '0:00';
    sec = Math.max(0, Math.floor(sec));
    const m = Math.floor(sec / 60), s = sec % 60;
    return `${m}:${s < 10 ? '0' : ''}${s}`;
  };

  const STORAGE_KEY = 'gio_player_state_v1';
  const saveState = () => {
    try {
      const payload = {
        src: audio?.src || '',
        title: titleEl?.textContent || '',
        artist: artistEl?.textContent || '',
        artistHref: artistEl?.getAttribute('href') || '',
        cover: coverEl?.getAttribute('src') || '',
        time: audio?.currentTime || 0,
        playing: audio ? !audio.paused : false,
        vol: audio?.volume ?? 1,
        muted: audio?.muted ?? false,
      };
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    } catch {}
  };
  const loadSavedState = () => {
    try { const raw = sessionStorage.getItem(STORAGE_KEY); return raw ? JSON.parse(raw) : null; }
    catch { return null; }
  };

  function showBar(){ if (playerBar){ playerBar.classList.add('is-visible'); playerBar.removeAttribute('hidden'); } }

  function loadMetaUI({ title, artist, artistHref, cover }){
    if (titleEl)  titleEl.textContent = title || 'Unbenannter Track';
    if (artistEl){
      artistEl.textContent = artist || 'Unbekannt';
      if (artistHref) artistEl.setAttribute('href', artistHref); else artistEl.removeAttribute('href');
    }
    if (coverEl){ if (cover) { coverEl.src = cover; coverEl.hidden = false; } else { coverEl.hidden = true; } }
  }

  let currentSrc = "";
  let currentTrigger = null;

  // ---- PlayCounter (30s) ----
  let currentSongId = null;
  let hasSentPlay = false;
  const PLAYED_KEY = 'gio_playcounted_v1';
  const loadPlayedSet = () => {
    try { const arr = JSON.parse(sessionStorage.getItem(PLAYED_KEY) || '[]'); return new Set(arr); } catch { return new Set(); }
  };
  const savePlayedSet = (set) => {
    try { sessionStorage.setItem(PLAYED_KEY, JSON.stringify(Array.from(set))); } catch {}
  };
  const playedSet = loadPlayedSet();

  async function sendPlay(songId){
    try{
      const fd = new FormData();
      fd.append('song_id', String(songId));
      const res = await fetch('/grabiton/api/play.php', { method:'POST', credentials:'same-origin', body: fd });
      await res.json().catch(()=>null);
    }catch{}
  }

  function checkPlayThreshold(){
    if (!audio || currentSongId == null || hasSentPlay) return;
    if (!isFinite(audio.currentTime)) return;
    if (audio.currentTime >= 30) {
      hasSentPlay = true;
      playedSet.add(currentSongId);
      savePlayedSet(playedSet);
      sendPlay(currentSongId);
    }
  }

  function loadSourceIfNeeded(src){
    if (currentSrc !== src){
      audio.src = src || '';
      currentSrc = src || '';
      audio.load();
      try { audio.currentTime = 0; } catch {}
    }
  }
  function togglePlayPause(){
    if (audio.paused) audio.play().catch(()=>{});
    else audio.pause();
  }

  // >>> Neuer Helper: spielt zuverlässig, auch wenn play() zuerst abgelehnt wird
  function playWithRetry(){
    if (!audio) return;
    try {
      const p = audio.play();
      if (p && typeof p.then === 'function') {
        p.catch(() => {
          const kick = () => { audio.play().catch(()=>{}); };
          audio.addEventListener('canplay', kick, { once:true });
          audio.addEventListener('loadeddata', kick, { once:true });
          audio.addEventListener('loadedmetadata', kick, { once:true });
          setTimeout(kick, 150);
        });
      }
    } catch {}
  }

  // --- Likes Helpers (gekürzt auf relevante Teile) ---

  function syncLikeUI(songId, count, liked){
    const s = parseInt(songId,10);
    document.querySelectorAll('.gio-like').forEach(b => {
      if (parseInt(b.dataset.songId||'0',10) === s) {
        b.classList.toggle('is-liked', !!liked);
      }
    });
    document.querySelectorAll('.gio-like-count').forEach(span => {
      if (parseInt(span.dataset.songId||'0',10) === s) {
        span.textContent = String((count|0));
      }
    });
  }

  async function refreshLikeFromServer(songId){
    try {
      const id = parseInt(songId,10);
      if (!id) return;
      const r = await fetch('/grabiton/api/likes_get.php', {
        method:'POST',
        credentials:'same-origin',
        headers:{ 'Content-Type':'application/json' },
        body: JSON.stringify([id])
      });
      const data = await r.json().catch(()=>null);
      if (!data || data.ok !== true) return;
      const counts = data.counts || {};
      const liked  = data.liked  || {};
      syncLikeUI(id, counts[id] ?? 0, !!liked[id]);
    } catch {}
  }

  function primePlayerLikeFromDOM(songId){
    const s = parseInt(songId,10);
    if (!s) return;
    const btn = document.querySelector('[data-role="player-like"]');
    if (!btn) return;
    const cnt = btn.querySelector('.gio-like-count');
    const anyCount = Array.from(document.querySelectorAll('.gio-like-count'))
      .find(el => parseInt(el.dataset.songId||'0',10) === s);
    if (anyCount && cnt) cnt.textContent = String(anyCount.textContent || '0');
    const anyBtn = Array.from(document.querySelectorAll('.gio-like'))
      .find(el => parseInt(el.dataset.songId||'0',10) === s);
    btn.classList.toggle('is-liked', !!(anyBtn && anyBtn.classList.contains('is-liked')));
  }

  function wirePlayerLike(songId) {
    if (!songId) return;
    const btn = document.querySelector('[data-role="player-like"]');
    if (!btn) return;
    btn.dataset.songId = String(songId);
    const cnt = btn.querySelector('.gio-like-count');
    if (cnt) cnt.dataset.songId = String(songId);
    primePlayerLikeFromDOM(songId);
    if (typeof initLikeCounts === 'function') initLikeCounts(btn);
    refreshLikeFromServer(songId);
  }

  /* ------------ Auto-Queue (Angesagt) ------------ */
  let playQueue = [];
  let queueIndex = -1;
  let queueActive = false;

  function buildHipQueue(){
    const list = [];
    const cont = $('#gio-hip'); if (!cont) return list;
    $$('#gio-hip .gio-card .gio-cover').forEach(img => {
      const card = img.closest('.gio-card');
      const sid  = card ? parseInt(card.dataset.songId || '0') : 0;
      list.push({
        songId: sid || null,
        src: img.getAttribute('data-src') || '',
        title: img.getAttribute('data-title') || '',
        artist: img.getAttribute('data-artist') || '',
        artistHref: img.getAttribute('data-artist-href') || '',
        cover: img.getAttribute('data-cover') || '',
        trigger: img
      });
    });
    return list.filter(t => t.src);
  }

  function clearPlayingCovers(){
    document.querySelectorAll('.gio-cover.is-playing').forEach(x => x.classList.remove('is-playing'));
  }

  // Helper: aktuelle Song-ID robust lesen
  function getCurrentSongId(){
    const p = document.querySelector('[data-role="player-like"]');
    if (p && p.getAttribute('data-song-id')) return parseInt(p.getAttribute('data-song-id')||'0',10) || null;
    if (audio && audio.getAttribute('data-song-id')) return parseInt(audio.getAttribute('data-song-id')||'0',10) || null;
    return null;
  }

  function refreshPlayerLike(){
    const sid = getCurrentSongId();
    if (sid) refreshLikeFromServer(sid);
  }

  function playTrack(track){
    if (!track || !track.src) return;
    clearPlayingCovers();
    loadMetaUI({ title: track.title, artist: track.artist, artistHref: track.artistHref, cover: track.cover });
    loadSourceIfNeeded(track.src);
    showBar();
    playWithRetry(); // <<< zuverlässig abspielen
    track.trigger?.classList.add('is-playing');
    currentTrigger = track.trigger || null;

    currentSongId = track.songId || null;
    hasSentPlay = currentSongId != null && playedSet.has(currentSongId);

    if (audio) audio.setAttribute('data-song-id', String(track.songId || ''));

    wirePlayerLike(track.songId || null);
  }

  function startQueueFromSong(songId){
    playQueue = buildHipQueue();
    queueIndex = playQueue.findIndex(t => t.songId === songId);
    queueActive = true;
  }

  function advanceQueue(){
    if (!queueActive || !playQueue.length) return false;
    let nextIndex = (queueIndex >= 0) ? queueIndex + 1 : 0;
    if (nextIndex >= playQueue.length) {
      queueActive = false;
      clearPlayingCovers();
      return false;
    }
    queueIndex = nextIndex;
    playTrack(playQueue[queueIndex]);
    return true;
  }

  function bindPlayer(){
    if (!audio) return;
    audio.volume = 1; audio.muted = false;
    volSlider && (volSlider.value = '100');

    audio.addEventListener('play',  () => {
      currentTrigger?.classList.add('is-playing');
      coverEl?.classList.add('is-playing');
      saveState();
      refreshPlayerLike();
    });

    audio.addEventListener('pause', () => {
      currentTrigger?.classList.remove('is-playing');
      coverEl?.classList.remove('is-playing');
      saveState();
    });

    audio.addEventListener('ended', () => {
      currentTrigger?.classList.remove('is-playing');
      coverEl?.classList.remove('is-playing');
      if (!advanceQueue()) { saveState(); }
    });

    audio.addEventListener('timeupdate', () => {
      timeCurEl && (timeCurEl.textContent = fmt(audio.currentTime || 0));
      timeDurEl && (timeDurEl.textContent = fmt(audio.duration || 0));
      if (sliderEl && isFinite(audio.duration) && audio.duration > 0 && !sliderEl.matches(':active')) {
        sliderEl.value = String(Math.min(100, Math.max(0, (audio.currentTime / audio.duration) * 100)));
      }
      checkPlayThreshold();
    });

    audio.addEventListener('loadedmetadata', () => {
      timeDurEl && (timeDurEl.textContent = fmt(audio.duration || 0));
      sliderEl && (sliderEl.value = '0');
      checkPlayThreshold();
      refreshPlayerLike();
    });

    if (coverEl){
      const coverToggle = (e) => { e.preventDefault(); togglePlayPause(); };
      coverEl.addEventListener('click', coverToggle);
      coverEl.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); togglePlayPause(); }});
      coverEl.setAttribute('tabindex','0'); coverEl.setAttribute('role','button'); coverEl.setAttribute('aria-label','Abspielen/Pausieren');
    }

    if (sliderEl){
      const seek = pct => { if (!isFinite(audio.duration) || audio.duration <= 0) return; audio.currentTime = (pct / 100) * audio.duration; };
      sliderEl.addEventListener('input', e => seek(parseFloat(e.target.value || '0')));
    }

    if (muteBtn){
      let prevVolume = 1;
      const updateMuteIcon = () => {
        const isMuted = audio.muted || audio.volume === 0;
        muteBtn.classList.toggle('is-muted', isMuted);
        muteBtn.setAttribute('aria-pressed', isMuted ? 'true' : 'false');
      };
      muteBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (audio.muted || audio.volume === 0) {
          audio.muted = false;
          if (audio.volume === 0) { audio.volume = prevVolume || 1; volSlider && (volSlider.value = String(Math.round(audio.volume * 100))); }
        } else {
          prevVolume = audio.volume || 1;
          audio.muted = true;
        }
        updateMuteIcon(); saveState();
      });
      audio.addEventListener('volumechange', () => { updateMuteIcon(); saveState(); });
      updateMuteIcon();
    }
    if (volSlider){
      volSlider.addEventListener('input', (e) => {
        const v = Math.min(100, Math.max(0, parseFloat(e.target.value || '0'))) / 100;
        audio.volume = v; if (audio.muted && v > 0) audio.muted = false; saveState();
      });
    }

    const saved = loadSavedState();
    if (saved && saved.src){
      showBar(); loadMetaUI({ title: saved.title, artist: saved.artist, artistHref: saved.artistHref, cover: saved.cover }); loadSourceIfNeeded(saved.src);
      audio.addEventListener('loadedmetadata', () => {
        if (Number.isFinite(saved.time)) { try { audio.currentTime = saved.time; } catch {} }
        audio.volume = typeof saved.vol === 'number' ? saved.vol : 1;
        audio.muted  = !!saved.muted;
        volSlider && (volSlider.value = String(Math.round(audio.volume * 100)));
        if (saved.playing) audio.play().catch(()=>{});
      }, { once: true });
    }

    window.addEventListener('beforeunload', saveState);
  }

  /* -------- Cards & Carousel -------- */
  function bindCovers(){
    document.addEventListener('click', (e) => {
      const img = e.target.closest('.gio-card .gio-cover');
      if (!img) return;

      const card       = img.closest('.gio-card');
      const sid        = card ? parseInt(card.dataset.songId || '0') : 0;

      const src        = img.getAttribute('data-src') || '';
      const title      = img.getAttribute('data-title') || '';
      const artist     = img.getAttribute('data-artist') || '';
      const artistHref = img.getAttribute('data-artist-href') || '';
      const cover      = img.getAttribute('data-cover') || '';
      const isSame = (audio.src && src) ? (audio.src === new URL(src, location.href).toString()) : false;

      if (!isSame){
        clearPlayingCovers();
        loadMetaUI({ title, artist, artistHref, cover });
        loadSourceIfNeeded(src);
        showBar();
        playWithRetry(); // <<< zuverlässig abspielen
        img.classList.add('is-playing');
        currentTrigger = img;

        startQueueFromSong(sid || null);

        currentSongId = sid || null;
        hasSentPlay   = currentSongId != null && playedSet.has(currentSongId);

        if (audio) audio.setAttribute('data-song-id', String(sid || ''));

        wirePlayerLike(sid || null);
      } else {
        if (audio.paused) audio.play().catch(()=>{}); else audio.pause();
      }
    });
  }

  function bindCarousel(){
    $$('.gio-carousel').forEach(car => {
      const track = $('.gio-track', car);
      const prev  = $('.gio-car-btn.-prev', car);
      const next  = $('.gio-car-btn.-next', car);
      let index = 0;

      const getStep = () => {
        const card = track.querySelector('.gio-card');
        if (!card) return 0;
        const rect = card.getBoundingClientRect();
        const styles = getComputedStyle(track);
        const gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
        return rect.width + gap;
      };
      const update = () => {
        const step = getStep();
        track.style.transform = `translateX(${-index * step}px)`;
        const total = track.querySelectorAll('.gio-card').length;
        const vis = Math.max(1, Math.floor(car.clientWidth / (step || 1)));
        prev.disabled = (index <= 0);
        next.disabled = (index >= total - vis);
        prev.classList.toggle('is-off', prev.disabled);
        next.classList.toggle('is-off', next.disabled);
      };

      prev.addEventListener('click', () => { index = Math.max(0, index - 1); update(); });
      next.addEventListener('click', () => {
        const step = getStep();
        const total = track.querySelectorAll('.gio-card').length;
        const vis = Math.max(1, Math.floor(car.clientWidth / (step || 1)));
        index = Math.min(total - vis, index + 1);
        update();
      });

      window.addEventListener('resize', update);
      update();
    });
  }

  /* -------- Likes: Counts + Toggle (robust) -------- */

  if (!window.__GIO_LIKES_BOUND__) window.__GIO_LIKES_BOUND__ = false;
  const pendingLikes = window.__GIO_PENDING_LIKES__ || new Set();
  window.__GIO_PENDING_LIKES__ = pendingLikes;

  function initLikeCounts(context){
    let likeButtons = $$('.gio-like', context);
    if (context && context.classList && context.classList.contains('gio-like')) {
      likeButtons = [context, ...likeButtons];
    }
    if (!likeButtons.length) return;

    const ids = Array.from(new Set(
      likeButtons.map(b => parseInt(b.dataset.songId || '0')).filter(n => n>0)
    ));
    if (!ids.length) return;

    fetch('/grabiton/api/likes_get.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify(ids)
    }).then(r => r.json()).then(data => {
      if (!data || !data.ok) return;
      const counts = data.counts || {};
      const liked  = data.liked  || {};

      ids.forEach(id => {
        const n = counts[id] ?? 0;
        $$('.gio-like-count').forEach(span => {
          if (parseInt(span.dataset.songId||'0') === id) span.textContent = String(n);
        });
        $$('.gio-like').forEach(btn => {
          if (parseInt(btn.dataset.songId||'0') === id) btn.classList.toggle('is-liked', !!liked[id]);
        });
      });
    }).catch(()=>{});
  }

  function bindLikesClickOnce(){
    if (window.__GIO_LIKES_BOUND__) return;
    window.__GIO_LIKES_BOUND__ = true;

    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.gio-like');
      if (!btn) return;

      let sid = parseInt(btn.dataset.songId || '0', 10);
      if (!sid) {
        const a = document.getElementById('gio-audio');
        const aSid = a && a.getAttribute('data-song-id') ? parseInt(a.getAttribute('data-song-id')||'0',10) : 0;
        if (aSid) sid = aSid;
      }
      if (!sid) {
        const p = document.querySelector('[data-role="player-like"]');
        const pSid = p && p.getAttribute('data-song-id') ? parseInt(p.getAttribute('data-song-id')||'0',10) : 0;
        if (pSid) sid = pSid;
      }
      if (!sid || pendingLikes.has(sid)) return;

      const countEl = btn.querySelector('.gio-like-count') ||
        $$('.gio-like-count').find(el => parseInt(el.dataset.songId||'0',10) === sid);
      let current = countEl ? parseInt(countEl.textContent || '0', 10) : 0;
      current = isNaN(current) ? 0 : current;

      const wasLiked = btn.classList.contains('is-liked');

      btn.classList.toggle('is-liked', !wasLiked);
      const optimistic = Math.max(0, current + (wasLiked ? -1 : +1));
      if (countEl) countEl.textContent = String(optimistic);

      pendingLikes.add(sid);

      try {
        const form = new FormData();
        form.append('song_id', String(sid));

        const r = await fetch('/grabiton/api/like.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: form
        });
        const data = await r.json().catch(()=>null);

        if (!r.ok || !data || data.ok !== true) throw new Error('like_failed');

        const serverCount = (data.count ?? optimistic)|0;
        const serverLiked = !!data.liked;

        syncLikeUI(sid, serverCount, serverLiked);
        await refreshLikeFromServer(sid);

      } catch (err) {
        btn.classList.toggle('is-liked', wasLiked);
        if (countEl) countEl.textContent = String(current);
      } finally {
        pendingLikes.delete(sid);
      }
    });
  }

  /* ------------ Init ------------ */
  function init(){
    interceptLinks();
    bindPlayer();
    bindCovers();
    bindCarousel();
    initLikeCounts(main);
    bindLikesClickOnce();
    restoreSidebar();
    bindSidebarToggle();

    const expected = pathToFragment(location.pathname);
    if (expected !== 'fragments/home.php' && location.pathname !== '/grabiton/') {
      loadFragmentByPath(location.pathname, false);
    } else {
      updateActiveLinks(location.pathname);
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

/* === Artist-Navigation ohne Full-Reload (Player läuft weiter) === */
(function () {
  var ROOT = "/grabiton";

  function getCodeFromHref(href) {
    try {
      var u = new URL(href, window.location.origin);
      var code = u.searchParams.get("code");
      return (code && /^gio\d{8}$/i.test(code)) ? code : null;
    } catch (e) { return null; }
  }

  function loadArtistByCode(code, pushUrl) {
    var main = document.getElementById("gio-main");
    if (!main) { window.location.href = pushUrl; return; }

    var fragUrl = ROOT + "/fragments/artist.php?code=" + encodeURIComponent(code);

    fetch(fragUrl, { headers: { "X-Requested-With": "fetch" }, credentials: "same-origin", cache: "no-store" })
      .then(function (r) { if (!r.ok) throw 0; return r.text(); })
      .then(function (html) {
        main.innerHTML = html;
        if (pushUrl) history.pushState({ __gio: "artist", code: code }, "", pushUrl);
        if (typeof updateActiveLinks === "function") updateActiveLinks(window.location.pathname);
        document.dispatchEvent(new Event("gio:main:updated"));
        window.scrollTo(0, 0);
      })
      .catch(function () { window.location.href = pushUrl; });
  }

  document.addEventListener("click", function (ev) {
    var a = ev.target.closest && ev.target.closest('a[href^="/grabiton/?code=gio"]');
    if (!a) return;

    var code = getCodeFromHref(a.getAttribute("href"));
    if (!code) return;

    ev.preventDefault();
    ev.stopPropagation();

    var pretty = ROOT + "/?code=" + code;
    loadArtistByCode(code, pretty);
  }, true);

  window.addEventListener("popstate", function () {
    var params = new URLSearchParams(window.location.search);
    var code = params.get("code");
    if (code && /^gio\d{8}$/i.test(code)) {
      loadArtistByCode(code, null);
    } else {
      if (document.getElementById("gio-main")) {
        fetch(ROOT + "/fragments/home.php", { headers: { "X-Requested-With": "fetch" }, credentials: "same-origin", cache: "no-store" })
          .then(function (r) { if (!r.ok) throw 0; return r.text(); })
          .then(function (html) {
            document.getElementById("gio-main").innerHTML = html;
            if (typeof updateActiveLinks === "function") updateActiveLinks(window.location.pathname);
            document.dispatchEvent(new Event("gio:main:updated"));
            window.scrollTo(0, 0);
          })
          .catch(function () { /* ignorieren */ });
      }
    }
  });
})();
