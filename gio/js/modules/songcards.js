// /gio/js/modules/songcards.js
// Rendert SongCards und bindet Cover=Play sowie Like-Toggle.
// Abhängigkeiten: window.player (aus player.js), fetch-APIs like_song.php und like_song_count.php
import { store } from './store.js';

export function renderSongCards(container, list){
  if (!container) return;
  const safe = Array.isArray(list) ? list : [];
  container.innerHTML = safe.map(cardHTML).join('');
  bindEvents(container, safe);
  preloadLikeCounts(safe);
}

function cardHTML(s){
  const id   = esc(s.id);
  const t    = esc(s.title || 'Unbekannt');
  const a    = esc(s.artist || '');
  const cover= esc(s.cover_url || '/gio/assets/img/placeholder.jpg');
  const dur  = esc(s.duration || ''); // z.B. "3:17"; optional
  const audio= esc(s.audio_url || '');
  return `
  <article class="song-card" data-id="${id}" data-audio="${audio}">
    <div class="cover" tabindex="0">
      <img src="${cover}" alt="">
      <span class="meta">${dur}</span>
    </div>
    <div class="body">
      <div class="title">${t}</div>
      <div class="artist">${a}</div>
      <div class="row">
        <button class="btn like" type="button">♡</button>
        <span class="count">0</span>
      </div>
    </div>
  </article>`;
}

function bindEvents(container, list){
  // Cover-Klick = Play
  container.addEventListener('click', ev => {
    const cover = ev.target.closest('.cover');
    if (cover){
      const card = cover.closest('.song-card');
      const id = toInt(card?.dataset?.id);
      const song = list.find(x => x.id === id);
      const cur = store?.player?.now;
      if (cur && cur.id === id){
        if (store.player.playing) window.player.pause();
        else window.player.play();
        return;
      }

      if (song) window.player.enqueue(song);
      return;
    }
    // Like-Toggle
    const likeBtn = ev.target.closest('.btn.like');
    if (likeBtn){
      const card = likeBtn.closest('.song-card');
      const id = toInt(card?.dataset?.id);
      toggleLike(card, id);
    }
  });

  // Tastatur: Enter/Space auf Cover
 container.addEventListener('keydown', ev => {
  const cover = ev.target.closest?.('.cover');
  if (!cover) return;
  if (ev.key === 'Enter' || ev.key === ' '){
    ev.preventDefault();
    const card = cover.closest('.song-card');
    const id = toInt(card?.dataset?.id);
    const song = list.find(x => x.id === id);

    const cur = store?.player?.now;
    if (cur && cur.id === id){
      if (store.player.playing) window.player.pause();
      else window.player.play();
      return;
    }
    if (song) window.player.enqueue(song);
  }
});
}

async function toggleLike(card, id){
  if (!id) return;
  try{
    const r = await fetch('/gio/api/v1/likes/like_song.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ song_id: id })
    });
    const x = await r.json();
    if (x?.ok){
      const btn = card.querySelector('.btn.like');
      const c   = card.querySelector('.count');
      if (btn) btn.textContent = x.data.liked ? '♥' : '♡';
      if (c)   c.textContent   = String(x.data.count ?? 0);
    }
  }catch(_e){}
}

async function preloadLikeCounts(list){
  const ids = list.map(x => x?.id).filter(Boolean).join(',');
  if (!ids) return;
  try{
    const r = await fetch(`/gio/api/v1/likes/like_song_count.php?ids=${ids}`);
    const x = await r.json();
    if (!x?.ok) return;
    for (const [id, count] of Object.entries(x.data)){
      const el = document.querySelector(`.song-card[data-id="${cssq(id)}"] .count`);
      if (el) el.textContent = String(count);
    }
  }catch(_e){}
}

/* ---------- Utils ---------- */
function esc(v){
  return String(v)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;');
}
function cssq(v){ return String(v).replace(/"/g,'\\"'); }
function toInt(v){ const n = Number(v); return Number.isFinite(n) ? n : 0; }
