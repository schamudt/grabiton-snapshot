// Player-Core: Queue, Play/Pause, Netto-Timer (2s/31s)
import { store } from './store.js';

const ms2 = 2000;
const ms31 = 31000;

let elAudio, btnPlay, btnPrev, btnNext, elMeta, elTime;
let tick = null;       // interval id
let lastWall = 0;      // ms timestamp of last tick while playing

function fmt(sec){
  sec = Math.max(0, Math.floor(sec));
  const m = String(Math.floor(sec/60)).padStart(2,'0');
  const s = String(sec%60).padStart(2,'0');
  return `${m}:${s}`;
}

function updateUI(){
  // title
  const now = store.player.now;
  elMeta.textContent = now ? `${now.title || 'Unbenannt'} — ${now.artist || ''}` : 'Nichts ausgewählt';
  // time from audio element
  elTime.textContent = fmt(elAudio.currentTime || 0);
  // play button icon
  btnPlay.textContent = store.player.playing ? '⏸' : '▶';
}

function startTick(){
  if (tick) return;
  lastWall = performance.now();
  tick = setInterval(() => {
    if (!store.player.playing) return;
    const nowTs = performance.now();
    const delta = nowTs - lastWall; // echte Netto-Zeit
    lastWall = nowTs;

    const p = store.player.now;
    if (!p) return;

    p.playedMs = (p.playedMs || 0) + delta;

    // Marker setzen, einmalig
    if (!p.mark2 && p.playedMs >= ms2){
      p.mark2 = true;
      console.log('[plays] mark2s', {song_id: p.id});
      // TODO: API: plays/mark2s
    }
    if (!p.mark31 && p.playedMs >= ms31){
      p.mark31 = true;
      console.log('[plays] mark31s', {song_id: p.id});
      // TODO: API: plays/mark31s
    }

    // Zeit anzeigen
    elTime.textContent = fmt(elAudio.currentTime || 0);
  }, 250);
}

function stopTick(){
  if (tick){ clearInterval(tick); tick = null; }
}

function bindDOM(){
  elAudio = document.getElementById('gio-audio');
  btnPlay = document.getElementById('pl-play');
  btnPrev = document.getElementById('pl-prev');
  btnNext = document.getElementById('pl-next');
  elMeta  = document.getElementById('pl-meta');
  elTime  = document.getElementById('pl-time');

  btnPlay?.addEventListener('click', () => {
    if (!store.player.now){ updateUI(); return; }
    if (store.player.playing) pause(); else play();
  });
  btnPrev?.addEventListener('click', prev);
  btnNext?.addEventListener('click', next);

  elAudio.addEventListener('play', () => {
    store.player.playing = true;
    startTick();
    updateUI();
  });
  elAudio.addEventListener('pause', () => {
    store.player.playing = false;
    stopTick();
    updateUI();
  });
  elAudio.addEventListener('ended', () => {
    stopTick();
    store.player.playing = false;
    next();
  });
  elAudio.addEventListener('timeupdate', () => {
    // Anzeige aktuell halten
    elTime.textContent = fmt(elAudio.currentTime || 0);
  });
}

function load(song){
  // song: {id,title,artist,audio_url}
  if (!song || !song.audio_url) { console.warn('Kein audio_url'); return; }
  // reset marker pro Song
  song.playedMs = 0;
  song.mark2 = false;
  song.mark31 = false;

  store.player.now = song;
  elAudio.src = song.audio_url;
  elAudio.currentTime = 0;
  updateUI();
}

function play(){
  if (!store.player.now){ console.warn('Kein Track geladen'); return; }
  elAudio.play().catch(err => console.error('Audio play()', err));
}

function pause(){ elAudio.pause(); }

function next(){
  const q = store.player.queue || [];
  if (!q.length) { updateUI(); return; }
  // advance index
  store.player.index = (store.player.index ?? -1) + 1;
  if (store.player.index >= q.length) store.player.index = 0;
  const song = q[store.player.index];
  load(song);
  play();
}

function prev(){
  const q = store.player.queue || [];
  if (!q.length) { updateUI(); return; }
  store.player.index = (store.player.index ?? 0) - 1;
  if (store.player.index < 0) store.player.index = Math.max(0, q.length - 1);
  const song = q[store.player.index];
  load(song);
  play();
}

export function setQueue(list){
  store.player.queue = Array.isArray(list) ? list.slice() : [];
  store.player.index = -1;
}

export function enqueue(listOrItem){
  const add = Array.isArray(listOrItem) ? listOrItem : [listOrItem];
  store.player.queue = (store.player.queue || []).concat(add);
  if (!store.player.now && store.player.queue.length){ next(); }
}

export function initPlayer(){
  bindDOM();
  updateUI();
  // Optional zum Testen in der Konsole:
  // window.player.enqueue({id:1,title:'Demo',artist:'Artist',audio_url:'/gio/assets/demo.mp3'})
  window.player = { play, pause, next, prev, setQueue, enqueue, load };
}
