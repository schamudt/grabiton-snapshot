// /gio/js/modules/player.js
// Player-Core: Queue, Play/Pause, Netto-Timer (0s/5s/31s)
import { store } from './store.js';

const ms5  = 5000;
const ms31 = 31000;

async function startIfNeeded(){
  if (store.player.play_id || !store.player?.now?.id) return;
  try{
    const r = await fetch('/gio/api/v1/plays/start.php',{
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({song_id: store.player.now.id})
    });
    const x = await r.json();
    if (x?.ok) store.player.play_id = x.data.play_id;
  }catch(e){}
}

function mark(at){
  if (!store.player.play_id) { console.warn('mark skipped, no play_id', at); return; }
  fetch('/gio/api/v1/plays/mark.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({play_id: store.player.play_id, at})
  }).catch(()=>{});
}

let elAudio, btnPlay, btnPrev, btnNext, elMeta, elTime;
let tick = null;
let lastWall = 0;

function fmt(sec){
  sec = Math.max(0, Math.floor(sec));
  const m = String(Math.floor(sec/60)).padStart(2,'0');
  const s = String(sec%60).padStart(2,'0');
  return `${m}:${s}`;
}

function updateUI(){
  const now = store.player.now;
  elMeta.textContent = now ? `${now.title || 'Unbenannt'} — ${now.artist || ''}` : 'Nichts ausgewählt';
  elTime.textContent = fmt(elAudio.currentTime || 0);
  btnPlay.textContent = store.player.playing ? '⏸' : '▶';
}

function startTick(){
  if (tick) return;
  lastWall = performance.now();
  tick = setInterval(() => {
    if (!store.player.playing) return;
    const nowTs = performance.now();
    const delta = nowTs - lastWall;
    lastWall = nowTs;

    const p = store.player.now;
    if (!p) return;

    p.playedMs = (p.playedMs || 0) + delta;

    if (!p.mark5 && p.playedMs >= ms5){
      p.mark5 = true;
      mark(5);
      console.log('[plays] mark5s', {song_id: p.id});
    }
    if (!p.mark31 && p.playedMs >= ms31){
      p.mark31 = true;
      mark(31);
      console.log('[plays] mark31s', {song_id: p.id});
    }

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
  btnLike = document.getElementById('pl-like');
  elLikeCount = document.getElementById('pl-like-count');

   btnLike?.addEventListener('click', async () => {
    const s = store.player.now;
    if (!s?.id) return;
    try {
      const r = await fetch('/gio/api/v1/likes/like_song.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ song_id: s.id })
      });
      const x = await r.json();
      if (x?.ok) {
        btnLike.textContent = x.data.liked ? '♥' : '♡';
        elLikeCount.textContent = String(x.data.count ?? 0);
      }
    } catch(e) {
      console.error('like_song.php', e);
    }
  });
  btnPlay?.addEventListener('click', () => {
    if (!store.player.now){ updateUI(); return; }
    if (store.player.playing) pause(); else play();
  });
  btnPrev?.addEventListener('click', prev);
  btnNext?.addEventListener('click', next);

  elAudio.addEventListener('play', async () => {
    const p = store.player.now;
    store.player.playing = true;

    await startIfNeeded();   // play_id sicher holen
    if (p && !p.mark0){
      p.mark0 = true;
      mark(0);
      console.log('[plays] mark0s', {song_id: p.id});
    }

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
    elTime.textContent = fmt(elAudio.currentTime || 0);
  });
}

function load(song){
  if (!song || !song.audio_url) { console.warn('Kein audio_url'); return; }
  // neuen Track sauber initialisieren
  store.player.play_id = null; // neue play_id pro Song
  song.playedMs = 0;
  song.mark0 = false;
  song.mark5 = false;
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

function load(song){
  if (!song || !song.audio_url) { console.warn('Kein audio_url'); return; }
  // Reset Likes bei neuem Track
  if (btnLike) btnLike.textContent = '♡';
  if (elLikeCount) elLikeCount.textContent = '0';
  // ... Rest wie bisher ...
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
  window.player = { play, pause, next, prev, setQueue, enqueue, load };
}
