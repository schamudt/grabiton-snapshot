// /gio/assets/js/player.ui.js
// UI-Binder: Meta, Slider, Volume/Mute, 31s-Haken (ohne Like-Click)
(() => {
  if (!window.gioPlayer || !window.gioStore) return;

  const $ = s => document.querySelector(s);
  const el = {
    title: $('.gio-player-title') || $('#gio-title'),
    artist: $('.gio-player-artist') || $('#gio-artist'),
    cover: ($('.gio-player-cover img') || $('#gio-cover')) || null,
    playBtn: $('#gio-play-btn'),
    likeBtn: $('#gio-like-btn'),
    likeCount: $('#gio-like-count'),
    slider: $('.gio-player-slider'),
    vol: $('.gio-vol'),
    mute: $('.gio-mute'),
    tcur: $('.gio-player-timecurrent'),
    tdur: $('.gio-player-timedur'),
    check31: $('.gio-31s')
  };

  // Haken-Element sichern
  function ensureCheckEl(){
    if (el.check31) return el.check31;
    const span = document.createElement('span');
    span.className = 'gio-31s';
    span.title = '31s gezählt';
    span.hidden = true;
    if (el.tdur && el.tdur.parentElement) el.tdur.parentElement.appendChild(span);
    else if (el.tcur && el.tcur.parentElement) el.tcur.parentElement.appendChild(span);
    else document.body.appendChild(span);
    el.check31 = span;
    return span;
  }
  ensureCheckEl();

  let isMuted = false;
  let volMax = el.vol ? (parseFloat(el.vol.max || '100') || 100) : 100;
  let shown31 = false;

  function fmt(t){
    t = Math.max(0, Math.floor(t||0));
    const m = String(Math.floor(t/60));
    const s = String(t%60).padStart(2,'0');
    return `${m}:${s}`;
  }

  function reset31(){
    shown31 = false;
    const c = ensureCheckEl();
    c.hidden = true;
    c.classList.remove('ok');
  }

  function show31(){
    if (shown31) return;
    shown31 = true;
    const c = ensureCheckEl();
    c.hidden = false;
    c.classList.add('ok');
  }

  function updateMeta(song){
    const a = song ? window.gioStore.getArtistById(song.artistId) : null;
    if (el.title)  el.title.textContent  = song?.title || '';
    if (el.artist) el.artist.textContent = a?.name || '';
    if (el.cover && song?.cover) el.cover.src = song.cover;

    // Count aus Store anzeigen, Likes werden durch app.js synchronisiert
    if (el.likeCount && song?.id) {
      const s = window.gioStore.getSongById(song.id);
      el.likeCount.textContent = String(s?.likes ?? 0);
    }

    reset31();
  }

  // Player-Events
  window.gioPlayer.subscribe((type, payload) => {
    if (type === 'player:load' || type === 'player:play' || type === 'player:loaded'){
      updateMeta(payload?.song || window.gioPlayer.getState().song);
    }
    if (type === 'player:time'){
      const st = window.gioPlayer.getState();
      if (el.tcur) el.tcur.textContent = fmt(st.time);
      if (el.tdur) el.tdur.textContent = fmt(st.dur);
      if (el.slider && st.dur){
        el.slider.max = String(Math.floor(st.dur));
        el.slider.value = String(Math.floor(st.time));
      }
      const sec = Math.max((st.playedMs||0)/1000, st.time||0);
      if (sec >= 31) show31();
    }
    if (type === 'player:volume'){
      const v = Math.max(0, Math.min(1, (payload?.v ?? 0)));
      if (el.vol) el.vol.value = String(Math.round(v * volMax));
    }
    if (type === 'player:mute'){
      isMuted = !!payload?.muted;
      if (el.mute) el.mute.classList.toggle('active', isMuted);
    }
    if (type === 'counter:31s'){ show31(); }
  });

  // Buttons
  if (el.playBtn){
    el.playBtn.addEventListener('click', () => window.gioPlayer.toggle());
  }
  // Kein Like-Click-Handler hier.

  if (el.slider){
    el.slider.addEventListener('input', (e) => {
      const v = Number(e.target.value || 0);
      window.gioPlayer.seek(v);
    });
  }
  if (el.vol){
    if (!el.vol.hasAttribute('max')) { el.vol.setAttribute('max', '100'); volMax = 100; }
    el.vol.addEventListener('input', (e) => {
      const raw = Math.max(0, Math.min(volMax, parseFloat(e.target.value) || 0));
      const v01 = volMax === 1 ? raw : (raw / volMax);
      window.gioPlayer.setVolume(v01);
    });
  }
  if (el.mute){
    el.mute.addEventListener('click', () => {
      isMuted = !isMuted;
      window.gioPlayer.mute(isMuted);
    });
  }

  // Initial
  const st = window.gioPlayer.getState();
  if (st.song) updateMeta(st.song);
})();
