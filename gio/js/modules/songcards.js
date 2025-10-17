// /gio/js/modules/songcards.js
// Rendert SongCards und bindet Like-/Play-Funktionen (SQL-Backend)
// Enthält: render(), bindLikesDelegation(), Auto-Bind + globaler Like-Sync

/* ---------- Render ---------- */
export function render(list = [], container) {
  if (!container) return;
  container.innerHTML = list.map(song => `
    <div class="songcard" data-id="${song.id}">
      <div class="cover" data-audio="${song.audio_url || ''}">
        <img src="${song.cover_url || '/gio/assets/img/cover_placeholder.jpg'}" alt="">
        <div class="overlay">
          <button class="play-btn" title="Abspielen">▶</button>
        </div>
      </div>
      <div class="meta">
        <div class="title">${song.title}</div>
        <div class="artist">${song.artist_name || ''}</div>
        <div class="like-zone">
          <button class="like-btn" title="Gefällt mir">
            <span class="like-icon" aria-pressed="false">♥</span>
            <span class="like-count">0</span>
          </button>
        </div>
      </div>
    </div>
  `).join('');
}

/* ---------- Likes: Delegation ---------- */
let _likesBound = false;

/**
 * Einmaliges Delegations-Binding für .like-btn.
 * Erwartet pro .songcard:
 *  - data-id
 *  - .like-btn, .like-count, optional .like-icon
 */
export function bindLikesDelegation(root = document) {
  if (_likesBound) return;
  _likesBound = true;

  root.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('.like-btn');
    if (!btn) return;

    const card = btn.closest('.songcard');
    if (!card) return;

    const songId = Number(card.dataset.id);
    if (!songId) return;

    const countEl = card.querySelector('.like-count');
    const iconEl  = card.querySelector('.like-icon') || btn;

    // Optimistisch
    const wasLiked = btn.classList.contains('is-liked');
    const prev = parseInt(countEl?.textContent || '0', 10) || 0;

    btn.classList.toggle('is-liked', !wasLiked);
    iconEl?.setAttribute('aria-pressed', String(!wasLiked));
    if (countEl) countEl.textContent = String(Math.max(0, prev + (wasLiked ? -1 : 1)));

    try {
      const { api } = await import('/gio/js/modules/api.js');
      const res = await api.likeToggle(songId);

      // Server-Wahrheit
      const liked = !!res.liked;
      const count = Number(res.count ?? 0);

      btn.classList.toggle('is-liked', liked);
      iconEl?.setAttribute('aria-pressed', String(liked));
      if (countEl) countEl.textContent = String(count);

      // Broadcast → Player und andere Views synchronisieren
      document.dispatchEvent(new CustomEvent('gio:like-updated', {
        detail: { song_id: songId, liked, count }
      }));
    } catch (err) {
      console.error('like_toggle_error', err); // console
      // Rollback
      btn.classList.toggle('is-liked', wasLiked);
      iconEl?.setAttribute('aria-pressed', String(wasLiked));
      if (countEl) countEl.textContent = String(prev);
    }
  });
}

/* ---------- Globaler Sync (Player → Cards) ---------- */
document.addEventListener('gio:like-updated', (ev) => {
  const { song_id, liked, count } = ev.detail || {};
  if (!song_id) return;
  const card = document.querySelector(`.songcard[data-id="${song_id}"]`);
  if (!card) return;

  const btn = card.querySelector('.like-btn');
  const iconEl = card.querySelector('.like-icon') || btn;
  const countEl = card.querySelector('.like-count');

  btn?.classList.toggle('is-liked', !!liked);
  iconEl?.setAttribute('aria-pressed', String(!!liked));
  if (countEl) countEl.textContent = String(Number(count ?? 0));
});

/* ---------- Auto-Bind aktivieren ---------- */
bindLikesDelegation(document);
