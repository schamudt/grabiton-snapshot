// /gio/assets/js/player.js
// Globaler Player-Core: Queue, Events, Counter(2s/31s), Volume, API

(() => {
  const audio = document.getElementById('gio-audio');
  if (!audio) { console.error('gio-audio fehlt in shell/player.php'); return; }

  const LS_VOL = 'gio.volume.v1';
  const SS_KEY = (id) => `gio.played.${id}`; // session: 2s/31s flags

  /** @typedef {{id:string,title:string,artistId:string,src:string,cover?:string}} Song */

  // ------- Helpers
  function now() { return performance.now(); }
  function getSong(id){ return (window.gioStore && window.gioStore.getSongById(id)) || null; }
  async function post(url, data){
    try {
      await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)});
    } catch(e){ /* still play; no hard fail */ }
  }
  function sesGetFlags(songId){
    try { return JSON.parse(sessionStorage.getItem(SS_KEY(songId)) || '{}'); } catch { return {}; }
  }
  function sesSetFlags(songId, flags){
    try { sessionStorage.setItem(SS_KEY(songId), JSON.stringify(flags||{})); } catch {}
  }

  // ------- State
  const state = {
    queue: /** @type{string[]} */([]), // Song-IDs
    idx: -1,
    playing: false,
    tickStart: 0,
    playedMs: 0, // kumuliert pro Song
    currentId: null,
    subs: new Set(), // fn(type,payload)
  };

  // ------- Notify
  function notify(type, payload){ state.subs.forEach(fn=>{ try{ fn(type,payload); }catch{} }); }

  // ------- Counters: 2s / 31s (akkumuliert)
  async function checkCounters(){
    const id = state.currentId; if (!id) return;
    const flags = sesGetFlags(id); // { started:true, logged31:true }
    const sec = state.playedMs / 1000;

    if (!flags.started && sec >= 2){
      flags.started = true;
      sesSetFlags(id, flags);
      await post('/gio/api/plays.start.php', { songId: id });
      notify('counter:2s', { id });
    }
    if (!flags.logged31 && sec >= 31){
      flags.logged31 = true;
      sesSetFlags(id, flags);
      await post('/gio/api/plays.log.php', { songId: id });
      notify('counter:31s', { id });
    }
  }

  // ------- Core controls
  function loadSongById(id, autoPlay=true){
    const s = getSong(id);
    if (!s) { console.warn('Song nicht gefunden:', id); return; }

    // Reset counters for this song if neu
    if (state.currentId !== s.id){
      state.playedMs = 0;
      state.tickStart = 0;
      state.currentId = s.id;
      // session flags existieren weiter; zählen aber nur einmal pro Session
    }

    audio.src = s.src;
    audio.load();

    notify('player:load', { song: s });
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

  function flushPlayed(){
    if (state.playing && state.tickStart){
      state.playedMs += Math.max(0, now() - state.tickStart);
      state.tickStart = now();
      checkCounters();
    }
  }

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
    // items: Song-Objekte oder IDs
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

  // ------- Audio events
  audio.addEventListener('timeupdate', () => {
    if (state.playing) { flushPlayed(); }
    notify('player:time', cur());
  });
  audio.addEventListener('play', () => { state.playing = true; state.tickStart = now(); notify('player:play', cur()); });
  audio.addEventListener('pause', () => { flushPlayed(); state.playing = false; notify('player:pause', cur()); });
  audio.addEventListener('ended', () => {
    flushPlayed();
    notify('player:ended', cur());
    next(); // Auto-Next
  });
  audio.addEventListener('loadedmetadata', () => { notify('player:loaded', cur()); });

  // ------- Init volume
  try {
    const v = parseFloat(localStorage.getItem(LS_VOL) || '');
    if (!Number.isNaN(v)) audio.volume = Math.max(0, Math.min(1, v));
  } catch {}

  // ------- Public API
  const api = {
    subscribe(fn){ state.subs.add(fn); return () => state.subs.delete(fn); },
    loadAndPlay(song){ // {id,title,artistId,src,...} oder ID
      const id = typeof song === 'string' ? song : song.id;
      if (!id) return;
      // Queue auf Single setzen, Index 0
      setQueue([id], 0);
      play();
    },
    toggle, play, pause, next, prev, seek, setVolume, mute,
    setQueue, enqueue,
    getState: cur
  };
  window.gioPlayer = api;

  // Backward-Compat: alte Aufrufe weiterleiten
  window.gioSetCurrentSong = (songObj) => {
    // songObj: {id,title,artist,src,cover}
    if (songObj && songObj.id) {
      // ggf. in Store upserten, damit Queue mit IDs funktioniert
      if (window.gioStore) {
        const artist = (songObj.artistId) ? songObj.artistId : (window.gioStore.getArtists()[0]?.id || 'a_demo');
        window.gioStore.upsertSong({
          id: songObj.id, title: songObj.title || 'Unbenannt',
          artistId: artist, src: songObj.src || ''
        });
      }
      api.loadAndPlay(songObj.id);
    }
  };

  // Erste Meldung
  notify('player:ready', cur());
})();
