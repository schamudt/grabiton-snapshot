// /gio/js/modules/songcards.js
// Rendert SongCards und bindet Like-/Play-Funktionen
// Jetzt mit SQL-basiertem Like-System und Auto-Bind

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

// --- Like Toggle (SQL-Backend) ---
let _likesBound = false;

/**
 * Bindet einmalig eine Event-Delegation für .like-btn auf dem gesamten Dokument.
 * Erwartet pro .songcard:
 *   - data-id="<song_id>"
 *   - .like-btn  (Button/Link)
 *   - .like-count (Span/Div für Zahl)
 *   - optional .like-icon (für aria-pressed)
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

    // Optimistisches UI
    const wasLiked = btn.classList.contains('is-liked');
    const prev = parseInt(countEl?.textContent || '0', 10) || 0;

    btn.classList.toggle('is-liked', !wasLiked);
    iconEl?.setAttribute('aria-pressed', String(!wasLiked));
    if (countEl) countEl.textContent = String(Math.max(0, prev + (wasLiked ? -1 : 1)));

    try {
      const { api } = await import('/gio/js/modules/api.js');
      const res = await api.likeToggle(songId);

      // Server-Wahrheit übernehmen
      btn.classList.toggle('is-liked', !!res.liked);
      iconEl?.setAttribute('aria-pressed', String(!!res.liked));
      if (countEl) countEl.textContent = String(res.count ?? 0);
    } catch (err) {
      console.error('like_toggle_error', err); // console
      // Rollback
      btn.classList.toggle('is-liked', wasLiked);
      iconEl?.setAttribute('aria-pressed', String(wasLiked));
      if (countEl) countEl.textContent = String(prev);
    }
  });
}

// --- Auto-Bind global aktivieren ---
bindLikesDelegation(document);


