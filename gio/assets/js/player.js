// /gio/assets/js/player.js
// Globaler Player-Core: Queue, Events, Counter(2s/31s), Volume, API
(() => {
  const audio = document.getElementById('gio-audio');
  if (!audio) { console.error('gio-audio fehlt in shell/player.php'); return; }

  const LS_VOL = 'gio.volume.v1';
  const SS_KEY = (id) => `gio.played.${id}`; // session flags: {started,logged31}

  function now() { return performance.now(); }
  function getSong(id){ return (window.gioStore && window.gioStore.getSongById(id)) || null; }
  async function post(url, data){
    try {
      await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)});
    } catch(e){ /* nicht fatal */ }
  }
  function sesGetFlags(songId){
    try { return JSON.parse(sessionStorage.getItem(SS_KEY(songId)) || '{}'); } catch { return {}; }
  }
  function sesSetFlags(songId, flags){
    try { sessionStorage.setItem(SS_KEY(songId), JSON.stringify(flags||{})); } catch {}
  }

  const state = {
    queue: /** @type{string[]} */([]),
    idx: -1,
    playing: false,
    tickStart: 0,
    playedMs: 0,
    currentId: null,
    subs: new Set(),
  };

  function notify(type, payload){ state.subs.forEach(fn=>{ try{ fn(type,payload); }catch{} }); }

  // ------- Counters: 2s / 31s (robust, ohne await-Blockade)
async function checkCounters(){
  const id = state.currentId; if (!id) return;

  // robuste Sekundenermittlung
  const secPlayed = state.playedMs / 1000;
  const secAudio  = audio.currentTime || 0;
  const sec = Math.max(secPlayed, secAudio); // nimm den größeren Wert

  const flags = sesGetFlags(id); // { started:true, logged31:true }

  if (!flags.started && sec >= 2){
    flags.started = true;
    sesSetFlags(id, flags);

    // UI sofort informieren, Request asynchron ohne await
    notify('counter:2s', { id });
    fetch('/gio/api/plays.start.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ songId: id })
    }).catch(()=>{});
  }

  if (!flags.logged31 && sec >= 31){
    flags.logged31 = true;
    sesSetFlags(id, flags);

    // UI sofort informieren, Request asynchron ohne await
    notify('counter:31s', { id });
    fetch('/gio/api/plays.log.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ songId: id })
    }).catch(()=>{});
  }
}

  // ------- Zeitakkumulation
  function flushPlayed(){
    if (state.playing && state.tickStart){
      state.playedMs += Math.max(0, now() - state.tickStart);
      state.tickStart = now();
      checkCounters();
    }
  }

  // ------- Core
  function loadSongById(id, autoPlay=true){
    const s = getSong(id);
    if (!s) { console.warn('Song nicht gefunden:', id); return; }

    const changed = state.currentId !== s.id;
    state.currentId = s.id;

    if (changed){
      state.playedMs = 0;
      state.tickStart = 0;
      notify('player:load', { song: s }); // wichtig für UI-Reset (31s)
    }

    audio.src = s.src;
    audio.load();
    if (autoPlay) play();
  }

  function play(){
    state.playing = true;
    state.tickStart = now();
    const p = audio.play();
    if (p && typeof p.catch === 'function'){ p.catch(()=>{}); }
    notify('player:play', cur());
  }

  function pause(){
    audio.pause();
    flushPlayed();
    state.playing = false;
    notify('player:pause', cur());
  }

  function toggle(){ state.playing ? pause() : play(); }

  function seek(seconds){
    audio.currentTime = Math.max(0, Math.min(seconds, audio.duration || seconds));
    notify('player:seek', { t: audio.currentTime });
  }

  function setVolume(v){ // 0..1
    const val = Math.max(0, Math.min(1, v));
    audio.volume = val;
    try { localStorage.setItem(LS_VOL, String(val)); } catch {}
    notify('player:volume', { v: val });
  }

  function mute(on=true){
    audio.muted = !!on;
    notify('player:mute', { muted: audio.muted });
  }

  function cur(){
    const s = state.currentId ? getSong(state.currentId) : null;
    return {
      song: s,
      time: audio.currentTime || 0,
      dur: audio.duration || 0,
      playing: state.playing,
      idx: state.idx,
      queue: state.queue.slice(),
      playedMs: state.playedMs
    };
  }

  // ------- Queue
  function setQueue(items, startIndex=0){
    state.queue = (items||[]).map(x => typeof x === 'string' ? x : x.id).filter(Boolean);
    state.idx = Math.min(Math.max(0, startIndex|0), state.queue.length-1);
    notify('queue:set', { queue: state.queue.slice(), idx: state.idx });
    if (state.queue.length) loadSongById(state.queue[state.idx], false);
  }
  function enqueue(items){
    const add = (Array.isArray(items) ? items : [items]).map(x=> typeof x==='string'?x:x.id).filter(Boolean);
    state.queue.push(...add);
    notify('queue:enqueue', { queue: state.queue.slice() });
  }
  function next(){
    if (!state.queue.length) return;
    state.idx = (state.idx + 1) % state.queue.length;
    loadSongById(state.queue[state.idx], true);
    notify('queue:next', cur());
  }
  function prev(){
    if (!state.queue.length) return;
    state.idx = (state.idx - 1 + state.queue.length) % state.queue.length;
    loadSongById(state.queue[state.idx], true);
    notify('queue:prev', cur());
  }

  // ------- Audio-Events
  audio.addEventListener('timeupdate', () => {
    if (state.playing) { flushPlayed(); }
    notify('player:time', cur());
  });
  audio.addEventListener('play', () => { state.playing = true; state.tickStart = now(); notify('player:play', cur()); });
  audio.addEventListener('pause', () => { flushPlayed(); state.playing = false; notify('player:pause', cur()); });
  audio.addEventListener('ended', () => { flushPlayed(); notify('player:ended', cur()); next(); });
  audio.addEventListener('loadedmetadata', () => { notify('player:loaded', cur()); });

  // ------- Init Volume aus Storage
  try {
    const v = parseFloat(localStorage.getItem(LS_VOL) || '');
    if (!Number.isNaN(v)) audio.volume = Math.max(0, Math.min(1, v));
  } catch {}

  // ------- Public API
  const api = {
    subscribe(fn){ state.subs.add(fn); return () => state.subs.delete(fn); },
    loadAndPlay(song){ const id = typeof song === 'string' ? song : song.id; if (!id) return; setQueue([id], 0); play(); },
    toggle, play, pause, next, prev, seek, setVolume, mute,
    setQueue, enqueue,
    getState: cur
  };
  window.gioPlayer = api;

  // Backward-Compat: alte Aufrufe
  window.gioSetCurrentSong = (songObj) => {
    if (songObj && songObj.id) {
      if (window.gioStore) {
        const artist = (songObj.artistId) ? songObj.artistId : (window.gioStore.getArtists()[0]?.id || 'a_demo');
        window.gioStore.upsertSong({
          id: songObj.id, title: songObj.title || 'Unbenannt',
          artistId: artist, src: songObj.src || '', cover: songObj.cover || ''
        });
      }
      api.loadAndPlay(songObj.id);
    }
  };

  // Erste UI-Sync
  notify('player:volume', { v: audio.volume });
  notify('player:mute',   { muted: audio.muted });
  notify('player:ready', cur());
})();
