// warte bis gioStore existiert, dann init
(function waitForStore(){
  if (!window.gioStore) { setTimeout(waitForStore, 50); return; }
  window.addEventListener('DOMContentLoaded', () => {
    const st = window.gioStore.getState();
    document.dispatchEvent(new CustomEvent('gio:home:init', { detail: st.songs }));
  });
})();

// store laden
// <script src="/gio/assets/js/store.js"></script> muss im HTML vor app.js eingebunden sein
if (!window.gioStore) { console.error('gioStore fehlt'); }

// Beispiel: Player-Demo an Store koppeln
window.addEventListener('DOMContentLoaded', () => {
  const st = window.gioStore.getState();
  // Optional: Home-List initial render trigger
  document.dispatchEvent(new CustomEvent('gio:home:init', { detail: st.songs }));
});

// Minimaler Start: lädt die Home-View in <main id="gio-main">.
(function () {
  const main = document.getElementById('gio-main');

  function load(view) {
    fetch(`/gio/views/${view}.php`, { credentials: 'same-origin' })
      .then(r => r.text())
      .then(html => { main.innerHTML = html; })
      .catch(err => { main.innerHTML = `<pre>Fehler: ${err.message}</pre>`; });
  }

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

// === Stabiler Like-Button am Player (ein Button, globale Steuerung) ===
(function(){
  const btn   = document.getElementById('gio-like-btn');
  const count = document.getElementById('gio-like-count');
  if (!btn || !count) return;

  let busy = false;

  async function toggleLike(songId) {
    if (!songId || busy) return;
    busy = true;
    btn.style.opacity = "0.6";

    try {
      const fd = new FormData();
      fd.append('song_id', songId);
      const r = await fetch('/gio/api/likes.toggle.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const j = await r.json();
      if (j.ok && j.data) {
        count.textContent = j.data.count;
        btn.classList.toggle('is-liked', j.data.liked);
      }
    } catch (err) {
      console.error('Like-Error', err);
    }

    btn.style.opacity = "1";
    busy = false;
  }

  btn.addEventListener('click', (e) => {
    e.preventDefault();
    const id = btn.dataset.songId || '';
    toggleLike(id);
  });
})();

// === Globale Funktion: aktuellen Song in den Player setzen ===
(function(){
  const likeBtn   = document.getElementById('gio-like-btn');
  const likeCount = document.getElementById('gio-like-count');
  const audio     = document.getElementById('gio-audio');

  async function refreshLike(songId){
    if (!songId) return;
    const fd = new FormData();
    fd.append('ids[]', songId);
    const r = await fetch('/gio/api/likes.get.php', { method:'POST', body: fd, credentials:'same-origin' });
    const j = await r.json().catch(()=>null);
    if (!j || !j.ok) return;
    const info = j.data.items[songId] || { liked:false, count:0 };
    if (likeCount) likeCount.textContent = info.count;
    if (likeBtn)   likeBtn.classList.toggle('is-liked', !!info.liked);
  }

  // öffentlich verfügbar machen
  window.gioSetCurrentSong = function(meta){
    // meta: { id, title, artist, artistHref, cover, src }
    const id = meta?.id || '';
    if (!id) return;

    // Song-ID an Like-UI hängen
    if (likeBtn)   likeBtn.dataset.songId   = id;
    if (likeCount) likeCount.dataset.songId = id;

    // UI-Texte/Bilder aktualisieren
    const t = document.querySelector('.gio-player-title');
    const a = document.querySelector('.gio-player-artist');
    const c = document.querySelector('.gio-player-cover');

    if (t) t.textContent = meta.title  || '–';
    if (a) { a.textContent = meta.artist || '–'; a.href = meta.artistHref || '#'; }
    if (c) {
      if (meta.cover) { c.src = meta.cover; c.hidden = false; }
      else            { c.hidden = true; }
    }

    // Audio-Quelle + Marker für einmaliges Laden
    if (audio && meta.src) { audio.src = meta.src; audio.dataset.needsLoad = '1'; }
    if (audio && meta.id)  { audio.dataset.songId = meta.id; } // für 30s-Counter

    // Like-Status passend zur neuen ID
    refreshLike(id);
  };
})();

// === Demo-Knopf: Song setzen und sicher abspielen (funktioniert mit innerHTML-Views) ===
document.addEventListener('click', function(e){
  const btn = e.target.closest('#demo-load');
  if (!btn) return;
  e.preventDefault();

  if (typeof window.gioSetCurrentSong !== 'function') {
    alert('gioSetCurrentSong fehlt'); 
    return;
  }

  // Deine Test-MP3 (Pfad ggf. anpassen)
  const TEST_MP3 = '/gio/assets/audio/demo.mp3';

  // Reset für wiederholte 30s-Tests derselben ID:
  sessionStorage.removeItem('gio_play_demo-002');
  const pc = document.querySelector('.gio-playcheck'); if (pc) pc.remove();

  // Player-UI & Audio setzen
  window.gioSetCurrentSong({
    id: 'demo-002',
    title: 'Demo Song',
    artist: 'Demo Artist',
    src: TEST_MP3
  });

  // Abspielen (ohne Neustart bei Pause)
  const audio = document.getElementById('gio-audio');
  if (!audio) return;
  try {
    audio.muted = false;
    audio.volume = 1;
    if (audio.dataset.needsLoad === '1') { audio.load(); audio.dataset.needsLoad = '0'; }
    audio.play().catch(err => {
      console.warn('Autoplay blockiert:', err);
      alert('Konnte nicht abspielen. Klicke Play oder prüfe die MP3-URL.');
    });
  } catch(err) {
    console.error(err);
  }
});

// === 31s-Playcounter + 2s-Startzähler (kumulativ über Pausen) ===
(function(){
  const audio = document.getElementById('gio-audio');
  if (!audio) return;

  const START_THRESHOLD_MS = 2000;  // 2 Sekunden
  const PLAY_THRESHOLD_MS  = 31000; // 31 Sekunden

  let timer = null;
  let lastTs = null;       // letzter Messzeitpunkt (ms)
  let accumMs = 0;         // kumulierte Hörzeit (ms)
  let currentSongId = null;

  function clearTicker(){
    if (timer) { clearInterval(timer); timer = null; }
    lastTs = null;
  }

  async function fireStartOnce(songId){
    const key = 'gio_start_' + songId;
    if (sessionStorage.getItem(key) === '1') return;
    try {
      const fd = new FormData();
      fd.append('song_id', songId);
      await fetch('/gio/api/plays.start.php', { method:'POST', body: fd, credentials:'same-origin' });
      sessionStorage.setItem(key, '1');
      console.debug('[start] gezählt (>=2s):', songId);
    } catch(e) {
      console.warn('[start] Fehler', e);
    }
  }

  async function firePlayOnce(songId){
    const key = 'gio_play_' + songId;
    if (sessionStorage.getItem(key) === '1') return;
    try {
      const fd = new FormData();
      fd.append('song_id', songId);
      const r = await fetch('/gio/api/plays.log.php', { method:'POST', body: fd, credentials:'same-origin' });
      const j = await r.json().catch(()=>null);
      if (!j || !j.ok) throw new Error('API fail');
      sessionStorage.setItem(key, '1');

      // kleines UI-Häkchen
      const t = document.querySelector('.gio-player-time');
      if (t && !t.querySelector('.gio-playcheck')) {
        const ok = document.createElement('span');
        ok.className = 'gio-playcheck';
        ok.textContent = ' ✓ 30s gezählt';
        ok.style.marginLeft = '6px';
        ok.style.opacity = '0.8';
        t.appendChild(ok);
      }
      console.debug('[plays] gezählt (>=31s):', songId);
    } catch(e) {
      console.warn('[plays] Fehler', e);
    }
  }

  function resetForSong(songId){
    currentSongId = songId || null;
    accumMs = 0;
    lastTs = null;
    clearTicker();
  }

  function startTicker(){
    if (timer) return;
    lastTs = performance.now();
    timer = setInterval(() => {
      if (audio.paused) return;
      const songId = audio.dataset.songId || '';
      if (!songId) return;

      // Songwechsel während Ticker?
      if (songId !== currentSongId) {
        resetForSong(songId);
        lastTs = performance.now();
        return;
      }

      const now = performance.now();
      accumMs += now - (lastTs || now);
      lastTs = now;

      // 2s-Start (einmal pro Session/Song)
      if (sessionStorage.getItem('gio_start_' + songId) !== '1' && accumMs >= START_THRESHOLD_MS) {
        fireStartOnce(songId);
      }

      // 31s-Play (einmal pro Session/Song)
      if (sessionStorage.getItem('gio_play_' + songId) !== '1' && accumMs >= PLAY_THRESHOLD_MS) {
        clearTicker();
        firePlayOnce(songId);
      }
    }, 250);
  }

  // Neu initialisieren bei neuer Quelle
  audio.addEventListener('loadedmetadata', () => {
    resetForSong(audio.dataset.songId || '');
  });

  // Start/Stop passend zum tatsächlichen Abspielstatus
  audio.addEventListener('playing', () => {
    const songId = audio.dataset.songId || '';
    if (songId !== currentSongId) resetForSong(songId);
    startTicker();
  });
  audio.addEventListener('pause', clearTicker);
  audio.addEventListener('ended', clearTicker);
})();


// === Play/Pause + Zeit/Slider (ohne Neustart) ===
(function(){
  const audio   = document.getElementById('gio-audio');
  const playBtn = document.getElementById('gio-play-btn');
  const cover   = document.querySelector('.gio-player-cover');
  const tNowEl  = document.querySelector('.gio-player-timecurrent');
  const tDurEl  = document.querySelector('.gio-player-timedur');
  const slider  = document.querySelector('.gio-player-slider');
  if (!audio) return;

  // Anzeige mm:ss
  const fmt = s => {
    if (!isFinite(s)) return '0:00';
    const m = Math.floor(s / 60);
    const sec = Math.floor(s % 60);
    return m + ':' + (sec < 10 ? '0' + sec : sec);
  };

  function updateUI(){
    if (tNowEl) tNowEl.textContent = fmt(audio.currentTime || 0);
    if (tDurEl) tDurEl.textContent = fmt(audio.duration || 0);
    if (slider && isFinite(audio.duration) && audio.duration > 0) {
      slider.value = (audio.currentTime / audio.duration) * 100;
    }
    if (playBtn) {
      const paused = audio.paused;
      playBtn.textContent = paused ? '▶' : '⏸';
      playBtn.setAttribute('aria-pressed', paused ? 'false' : 'true');
    }
  }

  // nur laden, wenn wirklich neue Quelle gesetzt wurde
  function ensureLoadedOnce() {
    if (audio.dataset.needsLoad === '1') {
      audio.load();
      audio.dataset.needsLoad = '0';
    }
  }

  function tryPlay() {
    audio.muted = false;
    audio.volume = 1;
    ensureLoadedOnce();          // lädt nur beim ersten Play nach src-Wechsel
    audio.play().catch(err => {
      console.warn('Play blockiert', err);
    });
  }

  // Slider → Seek
  if (slider) {
    slider.addEventListener('input', () => {
      if (!isFinite(audio.duration) || audio.duration <= 0) return;
      const pct = Math.max(0, Math.min(100, Number(slider.value || 0)));
      audio.currentTime = (pct / 100) * audio.duration;
    });
  }

  // Click-Targets (Play-Button + Cover)
  if (playBtn) playBtn.addEventListener('click', (e)=>{ e.preventDefault(); audio.paused ? tryPlay() : audio.pause(); });
  if (cover)   cover.addEventListener('click',   (e)=>{ e.preventDefault(); audio.paused ? tryPlay() : audio.pause(); });

  // Laufende Updates
  audio.addEventListener('timeupdate', updateUI);
  audio.addEventListener('durationchange', updateUI);
  audio.addEventListener('loadedmetadata', updateUI);
  audio.addEventListener('play', updateUI);
  audio.addEventListener('pause', updateUI);
  audio.addEventListener('ended', updateUI);

  updateUI(); // Initial
})();

// === Lautstärke & Mute (mit Persistenz) ===
(function(){
  const audio = document.getElementById('gio-audio');
  const vol   = document.querySelector('.gio-vol');   // <input type="range">
  const mute  = document.querySelector('.gio-mute');  // <button aria-pressed="false">
  if (!audio || !vol || !mute) return;

  // Startwerte laden/syncen
  const savedVol = Number(localStorage.getItem('gio_vol') || 100); // 0..100
  const clamped  = Math.max(0, Math.min(100, savedVol));
  audio.volume   = clamped / 100;
  audio.muted    = (clamped === 0);
  vol.value      = String(clamped);
  updateMuteUI();

  function updateMuteUI(){
    const isMuted = audio.muted || audio.volume === 0;
    mute.setAttribute('aria-pressed', isMuted ? 'true' : 'false');
    mute.classList.toggle('is-muted', isMuted);
  }

  // Slider → Volume setzen (0..100)
  vol.addEventListener('input', () => {
    const v = Math.max(0, Math.min(100, Number(vol.value || 0)));
    audio.volume = v / 100;
    // Wenn 0 => gemutet, sonst entmuten
    audio.muted = (v === 0);
    localStorage.setItem('gio_vol', String(v));
    updateMuteUI();
  });

  // Mute-Button toggeln
  mute.addEventListener('click', (e) => {
    e.preventDefault();
    const current = Math.max(0, Math.min(100, Number(vol.value || 0)));
    if (!audio.muted && current > 0) {
      // stummschalten, aktuellen Wert merken
      mute.dataset.prev = String(current);
      audio.muted = true;
      vol.value = '0';
      localStorage.setItem('gio_vol', '0');
    } else {
      // entstummen: vorherige Lautstärke oder 100
      const prev = Math.max(1, Math.min(100, Number(mute.dataset.prev || 100)));
      audio.muted = false;
      audio.volume = prev / 100;
      vol.value = String(prev);
      localStorage.setItem('gio_vol', String(prev));
    }
    updateMuteUI();
  });

  // Falls Volume anderswo geändert wird (z. B. System/Autoplay), UI nachziehen
  audio.addEventListener('volumechange', () => {
    const v = Math.round((audio.volume || 0) * 100);
    vol.value = String(v);
    localStorage.setItem('gio_vol', String(v));
    updateMuteUI();
  });
})();

