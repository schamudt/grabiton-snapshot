// /gio/js/modules/player.js
// Player mit SQL-Tracking: play_start + Marker 0/5/31 (robust)

import { api } from './api.js';

let audio = null;
let current = {
  song: null,         // { id, title, artist_name, audio_url }
  playId: null,       // number|null
  marks: { m0:false, m5:false, m31:false },
  mark0Timer: null,   // Fallback-Timer
};

function resetState() {
  current.song = null;
  current.playId = null;
  current.marks = { m0:false, m5:false, m31:false };
  if (current.mark0Timer) {
    clearTimeout(current.mark0Timer);
    current.mark0Timer = null;
  }
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

  // Marker 0 sofort bei Start, falls timeupdate spät kommt
  audio.addEventListener('playing', tryMark0);
  audio.addEventListener('loadeddata', tryMark0);

  audio.addEventListener('ended', () => {
    resetState();
  });
}

function tryMark0() {
  if (!current.playId || current.marks.m0) return;
  current.marks.m0 = true;
  api.playMark(current.playId, 0).catch(e => console.error('mark0_fail', e)); // console
}

async function onTimeUpdate() {
  if (!current.playId) return;
  const t = audio.currentTime || 0;

  // Falls playing/loadeddata nicht feuerten, hier m0 spätestens erzwingen
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

  // Gleiches Lied → Toggle
  if (current.song && current.song.id === song.id) {
    if (audio && !audio.paused) { audio.pause(); return; }
    if (audio) { audio.play().catch(console.error); return; }
  }

  // Vorherigen Stop
  stop();

  // Zustand setzen
  current.song = { ...song };
  ensureAudio();
  audio.src = src;

  // play_start
  try {
    const res = await api.playStart(Number(song.id));
    current.playId = Number(res.play_id || 0) || null;
  } catch (e) {
    console.error('play_start_fail', e); // console
    current.playId = null; // lokal weiter abspielen
  }

  current.marks = { m0:false, m5:false, m31:false };

  // Fallback: mark0 nach 400ms schicken, falls Events nicht greifen
  if (current.playId) {
    current.mark0Timer = setTimeout(() => {
      if (!current.marks.m0 && current.playId) {
        current.marks.m0 = true;
        api.playMark(current.playId, 0).catch(err => console.error('mark0_fallback_fail', err)); // console
      }
      current.mark0Timer = null;
    }, 400);
  }

  try {
    await audio.play();
  } catch (err) {
    console.error('audio_play_error', err); // console
  }
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

/* ---------- UI: Play-Button in SongCards ---------- */
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

/* ---------- Public API ---------- */
export function initPlayer() {
  ensureAudio();
  bindGlobalPlayClicks(document);
  window.player = {
    playSong, pause, resume, stop,
    get state() { return { ...current, paused: audio?.paused ?? true, t: audio?.currentTime ?? 0 }; },
  };
}
