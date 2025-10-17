// /gio/js/modules/player.js
// Player mit SQL-Tracking + UI-Bindings: play_start + Marker 0/5/31 + sichtbare Steuerung

import { api } from './api.js';

let audio = null;
let ui = {
  root: null,
  prev: null,
  toggle: null,
  next: null,
  title: null,
  time: null,
  likeBtn: null,
  likeCount: null,
  mute: null,
  vol: null,
};

let current = {
  song: null,         // { id, title, artist_name, audio_url }
  playId: null,       // number|null
  marks: { m0:false, m5:false, m31:false },
  mark0Timer: null,
};

function resetState() {
  current.song = null;
  current.playId = null;
  current.marks = { m0:false, m5:false, m31:false };
  if (current.mark0Timer) { clearTimeout(current.mark0Timer); current.mark0Timer = null; }
  updateTitle('—');
  updateTime(0);
  setToggleVisual(true);
}

function ensureAudio() {
  if (audio) return audio;
  audio = new Audio();
  audio.preload = 'auto';
  audio.crossOrigin = 'anonymous';
  bindAudioEvents();
  return audio;
}

function bindAudioEvents() {
  audio.addEventListener('timeupdate', onTimeUpdate);
  audio.addEventListener('playing', () => { tryMark0(); setToggleVisual(false); });
  audio.addEventListener('pause',   () => setToggleVisual(true));
  audio.addEventListener('loadeddata', tryMark0);
  audio.addEventListener('ended', () => {
    setToggleVisual(true);
    resetState();
  });
}

function fmt(t) {
  t = Math.max(0, Math.floor(t));
  const m = Math.floor(t/60);
  const s = t % 60;
  return m + ':' + String(s).padStart(2,'0');
}

function updateTitle(text) {
  if (ui.title) ui.title.textContent = text;
}

function updateTime(t) {
  if (ui.time) ui.time.textContent = fmt(t);
}

function setToggleVisual(paused) {
  if (!ui.toggle) return;
  ui.toggle.textContent = paused ? '▶︎' : '⏸';
}

function tryMark0() {
  if (!current.playId || current.marks.m0) return;
  current.marks.m0 = true;
  api.playMark(current.playId, 0).catch(e => console.error('mark0_fail', e)); // console
}

async function onTimeUpdate() {
  updateTime(audio.currentTime || 0);
  if (!current.playId) return;
  const t = audio.currentTime || 0;

  if (!current.marks.m0 && t >= 0.05) {
    current.marks.m0 = true;
    api.playMark(current.playId, 0).catch(e => console.error('mark0_fail', e)); // console
  }
  if (!current.marks.m5 && t >= 5) {
    current.marks.m5 = true;
    api.playMark(current.playId, 5).catch(e => console.error('mark5_fail', e)); // console
  }
  if (!current.marks.m31 && t >= 31) {
    current.marks.m31 = true;
    api.playMark(current.playId, 31).catch(e => console.error('mark31_fail', e)); // console
  }
}

async function playSong(song) {
  if (!song || !song.id) return;
  const src = song.audio_url || '/gio/assets/audio/demo_song.mp3';

  // gleiches Lied → Toggle
  if (current.song && current.song.id === song.id) {
    if (audio && !audio.paused) { audio.pause(); return; }
    if (audio) { audio.play().catch(console.error); return; }
  }

  stop(); // alten stoppen

  current.song = { ...song };
  ensureAudio();
  audio.src = src;

  updateTitle(`${song.title || ''} — ${song.artist_name || ''}`);
  updateTime(0);
  setToggleVisual(true);

  try {
    const res = await api.playStart(Number(song.id));
    current.playId = Number(res.play_id || 0) || null;
  } catch (e) {
    console.error('play_start_fail', e); // console
    current.playId = null;
  }

  current.marks = { m0:false, m5:false, m31:false };

  // mark0 Fallback
  if (current.playId) {
    current.mark0Timer = setTimeout(() => {
      if (!current.marks.m0 && current.playId) {
        current.marks.m0 = true;
        api.playMark(current.playId, 0).catch(err => console.error('mark0_fallback_fail', err)); // console
      }
      current.mark0Timer = null;
    }, 400);
  }

  // Player-Like-Zähler laden
  refreshPlayerLike();

  try { await audio.play(); } catch (err) { console.error('audio_play_error', err); }
}

function pause() { if (audio) audio.pause(); }
function resume() { if (audio && audio.paused) audio.play().catch(console.error); }

function stop() {
  if (audio) {
    audio.pause();
    audio.currentTime = 0;
    audio.src = '';
  }
  resetState();
}

/* ---------- UI Bindings ---------- */
function bindGlobalPlayClicks(root = document) {
  root.addEventListener('click', (ev) => {
    const btn = ev.target.closest('.play-btn');
    if (!btn) return;
    const card = btn.closest('.songcard');
    if (!card) return;

    const id = Number(card.dataset.id || 0);
    const cover = card.querySelector('.cover');
    const src = cover?.dataset.audio || '/gio/assets/audio/demo_song.mp3';
    const title = card.querySelector('.title')?.textContent?.trim() || '';
    const artist_name = card.querySelector('.artist')?.textContent?.trim() || '';

    playSong({ id, audio_url: src, title, artist_name });
  });
}

function bindPlayerUI() {
  ui.root   = document.getElementById('gio-player') || null;
  if (!ui.root) return;

  ui.prev   = document.getElementById('gio-player-prev');
  ui.toggle = document.getElementById('gio-player-toggle');
  ui.next   = document.getElementById('gio-player-next');
  ui.title  = document.getElementById('gio-player-title');
  ui.time   = document.getElementById('gio-player-time');
  ui.likeBtn   = document.getElementById('gio-player-like');
  ui.likeCount = document.getElementById('gio-player-like-count');
  ui.mute   = document.getElementById('gio-player-mute');
  ui.vol    = document.getElementById('gio-player-volume');

  ui.toggle?.addEventListener('click', () => {
    if (!audio) return;
    if (audio.paused) resume(); else pause();
  });

  ui.mute?.addEventListener('click', () => {
    if (!audio) return;
    audio.muted = !audio.muted;
    ui.mute.textContent = audio.muted ? '🔇' : '🔈';
  });

  ui.vol?.addEventListener('input', (e) => {
    if (!audio) return;
    const v = Number(e.target.value);
    audio.volume = Math.min(1, Math.max(0, v));
  });

  // Like im Player
    // Like im Player
  ui.likeBtn?.addEventListener('click', async () => {
    if (!current.song?.id) return;
    try {
      const res = await api.likeToggle(current.song.id);
      const liked = !!res.liked;
      const count = Number(res.count ?? 0);

      if (ui.likeCount) ui.likeCount.textContent = String(count);
      ui.likeBtn.classList.toggle('is-liked', liked);
      ui.likeBtn.setAttribute('aria-pressed', String(liked));

      // Broadcast an alle UIs
      document.dispatchEvent(new CustomEvent('gio:like-updated', {
        detail: { song_id: current.song.id, liked, count }
      }));
    } catch (e) {
      console.error('player_like_fail', e); // console
    }
  });

}

async function refreshPlayerLike() {
  if (!current.song?.id || !ui.likeCount) return;
  try {
    const map = await api.likeCounts([current.song.id]);
    ui.likeCount.textContent = String(map[String(current.song.id)] ?? 0);
  } catch (e) {
    // still ok
  }
}

/* ---------- Public API ---------- */
export function initPlayer() {
  ensureAudio();
  bindGlobalPlayClicks(document);
  bindPlayerUI();
  window.player = {
    playSong, pause, resume, stop,
    get state() { return { ...current, paused: audio?.paused ?? true, t: audio?.currentTime ?? 0 }; },
  };
}
