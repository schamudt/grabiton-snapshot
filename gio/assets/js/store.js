// /gio/public/assets/js/store.js
// Globaler Daten-Layer für Songs, Artists, Releases + Suche
(() => {
  const state = {
    ready: false,
    currentSongId: null,
    songs: [],
    artists: [],
    releases: [],
    likes: new Map(), // songId -> count
    userLiked: new Set(), // sessionbasiert
  };

  // --- Utils ---
  const byId = (arr, id) => arr.find(x => String(x.id) === String(id)) || null;
  const norm = s => String(s || '').toLowerCase().normalize('NFKD');

  // --- Public API ---
  const api = {
    init(initial = {}) {
      if (state.ready) return;
      state.songs = Array.isArray(initial.songs) ? initial.songs : [];
      state.artists = Array.isArray(initial.artists) ? initial.artists : [];
      state.releases = Array.isArray(initial.releases) ? initial.releases : [];

      // Likes vorbereiten (Counts aus Daten oder 0)
      state.songs.forEach(s => state.likes.set(String(s.id), Number(s.likes || 0)));

      // Session-Likes rekonstruieren
      try {
        const key = 'gio.userLiked';
        const saved = JSON.parse(sessionStorage.getItem(key) || '[]');
        state.userLiked = new Set(saved.map(String));
        // Safety: Counts nicht doppelt erhöhen, nur Status wiederherstellen
      } catch(_) {}

      state.ready = true;
      document.dispatchEvent(new CustomEvent('gio:store:ready'));
    },

    // --- Getter ---
    isReady(){ return state.ready; },
    getSongs(){ return [...state.songs]; },
    getArtists(){ return [...state.artists]; },
    getReleases(){ return [...state.releases]; },

    getSong(id){ return byId(state.songs, id); },
    getArtist(id){ return byId(state.artists, id); },
    getRelease(id){ return byId(state.releases, id); },

    // --- Current Song ---
    getCurrentSong(){ return api.getSong(state.currentSongId); },
    setCurrentSong(id){
      const s = api.getSong(id);
      if (!s) return false;
      state.currentSongId = String(id);
      document.dispatchEvent(new CustomEvent('gio:store:currentSong', { detail: { song: s }}));
      return true;
    },

    // --- Likes (optimistic) ---
    getLikeCount(songId){
      return state.likes.get(String(songId)) || 0;
    },
    hasUserLiked(songId){
      return state.userLiked.has(String(songId));
    },
    async toggleLike(songId){
      const id = String(songId);
      const liked = api.hasUserLiked(id);
      // Optimistic Update
      if (liked){
        state.userLiked.delete(id);
        state.likes.set(id, Math.max(0, api.getLikeCount(id) - 1));
      } else {
        state.userLiked.add(id);
        state.likes.set(id, api.getLikeCount(id) + 1);
      }
      persistUserLiked();

      document.dispatchEvent(new CustomEvent('gio:store:like', { detail: { songId: id, liked: !liked, count: api.getLikeCount(id) }}));

      // Server informieren (falls API schon aktiv)
      try {
        const res = await fetch('/gio/api/likes.toggle.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ songId: id })
        });
        // optional: Ergebnis gegenprüfen
        if (!res.ok) throw new Error('like toggle failed');
      } catch(e){
        // Rollback bei Fehler
        if (liked){
          state.userLiked.add(id);
          state.likes.set(id, api.getLikeCount(id) + 1);
        } else {
          state.userLiked.delete(id);
          state.likes.set(id, Math.max(0, api.getLikeCount(id) - 1));
        }
        persistUserLiked();
        document.dispatchEvent(new CustomEvent('gio:store:like:rollback', { detail: { songId: id }}));
      }
    },

    // --- Suche (einfach, clientseitig) ---
    search(query){
      const q = norm(query).trim();
      if (!q) return { songs: [], artists: [], releases: [] };

      const hitSong = s => [s.title, api.getArtist(s.artistId)?.name, api.getRelease(s.releaseId)?.title]
        .filter(Boolean).some(v => norm(v).includes(q));

      const hitArtist = a => norm(a.name).includes(q);
      const hitRelease = r => norm(r.title).includes(q);

      return {
        songs: state.songs.filter(hitSong),
        artists: state.artists.filter(hitArtist),
        releases: state.releases.filter(hitRelease),
      };
    },
  };

  function persistUserLiked(){
    try {
      sessionStorage.setItem('gio.userLiked', JSON.stringify([...state.userLiked]));
    } catch(_) {}
  }

  // --- Demo-Seed (bis DB steht) ---
  const demo = {
    artists: [
      { id: 1, name: 'Rostlaut' },
      { id: 2, name: 'Neon Welle' },
    ],
    releases: [
      { id: 10, title: 'Bluenight EP', year: 2024 },
    ],
    songs: [
      { id: 100, title: 'Toxisch menschlich', artistId: 1, releaseId: 10, duration: 189, src: '/gio/assets/audio/demo.mp3', likes: 7 },
      { id: 101, title: 'Greif es dir', artistId: 2, releaseId: 10, duration: 203, src: '/gio/assets/audio/demo.mp3', likes: 3 },
    ],
  };

  // Auto-Init bei Laden, falls gewünscht
  document.addEventListener('DOMContentLoaded', () => {
    if (!state.ready) api.init(demo);
  });

  // Expose
  window.gio = window.gio || {};
  window.gio.store = api;
})();
