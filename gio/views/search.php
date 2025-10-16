<!-- /gio/views/search.php -->
<section class="gio-search-view">
  <h2>Suche</h2>
  <div id="gio-search-summary" class="muted"></div>

  <h3>Songs</h3>
  <div id="gio-search-songs" class="gio-grid"></div>

  <h3>Artists</h3>
  <div id="gio-search-artists" class="gio-grid"></div>

  <h3>Releases</h3>
  <div id="gio-search-releases" class="gio-grid"></div>
</section>

<script>
(() => {
  const $ = (s,r=document)=>r.querySelector(s);
  const $songs = $('#gio-search-songs');
  const $artists = $('#gio-search-artists');
  const $releases = $('#gio-search-releases');
  const $sum = $('#gio-search-summary');

  function getQ(){
    const u = new URL(location.href);
    return (u.searchParams.get('q')||'').trim().toLowerCase();
  }

  function highlight(txt, q){
    if (!q) return txt;
    const i = txt.toLowerCase().indexOf(q);
    if (i < 0) return txt;
    return txt.slice(0,i) + '<mark>' + txt.slice(i, i+q.length) + '</mark>' + txt.slice(i+q.length);
  }

  function render(){
    const q = getQ();
    const st = window.gioStore.getState();

    // Filter
    const fSong = q
      ? st.songs.filter(s => [s.title, s.id].some(v=> (v||'').toLowerCase().includes(q)) ||
                             (window.gioStore.getArtistById(s.artistId)||{name:''}).name.toLowerCase().includes(q))
      : st.songs.slice();

    const fArtists = q
      ? st.artists.filter(a => [a.name, a.id].some(v=> (v||'').toLowerCase().includes(q)))
      : st.artists.slice();

    const fReleases = q
      ? st.releases.filter(r => [r.title, r.id, r.type, String(r.year||'')].some(v=> (v||'').toLowerCase().includes(q)))
      : st.releases.slice();

    $sum.textContent = q ? `Treffer für „${q}“: Songs ${fSong.length}, Artists ${fArtists.length}, Releases ${fReleases.length}` :
                           `Keine Suchphrase. Zeige alle.`;

    // Songs
    $songs.innerHTML = '';
    fSong.forEach(s => {
      const a = window.gioStore.getArtistById(s.artistId);
      const el = document.createElement('article');
      el.className = 'gio-card';
      el.innerHTML = `
        <button class="gio-card-play" data-song="${s.id}">
          ${s.cover ? `<img src="${s.cover}" alt="">` : ''}
          <div class="meta">
            <div class="title">${highlight(s.title, q)}</div>
            <div class="artist">${a?highlight(a.name,q):'Unbekannt'}</div>
          </div>
        </button>
        <div class="row">
          <button class="gio-like" data-like="${s.id}">❤ ${(s.likes||0)}</button>
        </div>
      `;
      $songs.appendChild(el);
    });

    // Artists
    $artists.innerHTML = '';
    fArtists.forEach(a => {
      const el = document.createElement('article');
      el.className = 'gio-card';
      el.innerHTML = `
        <div class="meta">
          <div class="title">${highlight(a.name, q)}</div>
          <div class="artist muted">ID: ${a.id}</div>
        </div>
      `;
      $artists.appendChild(el);
    });

    // Releases
    $releases.innerHTML = '';
    fReleases.forEach(r => {
      const a = window.gioStore.getArtistById(r.artistId);
      const el = document.createElement('article');
      el.className = 'gio-card';
      el.innerHTML = `
        <div class="meta">
          <div class="title">${highlight(r.title, q)}</div>
          <div class="artist">${a?highlight(a.name,q):'Unbekannt'} · ${r.type}${r.year?` · ${r.year}`:''}</div>
        </div>
      `;
      $releases.appendChild(el);
    });
  }

  // Interaktionen Songs
  document.currentScript.parentElement.addEventListener('click', (ev) => {
    const btnPlay = ev.target.closest('.gio-card-play');
    if (btnPlay){
      const id = btnPlay.dataset.song;
      const s = window.gioStore.getSongById(id);
      if (s && window.gioSetCurrentSong){
        window.gioSetCurrentSong({
          id: s.id, title: s.title,
          artist: (window.gioStore.getArtistById(s.artistId)||{}).name || '',
          src: s.src, cover: s.cover || ''
        });
      }
    }
    const btnLike = ev.target.closest('.gio-like');
    if (btnLike){
      const id = btnLike.dataset.like;
      window.gioStore.likeSong(id, 1);
      if (window.gioLikesToggle) window.gioLikesToggle(id);
      const s = window.gioStore.getSongById(id);
      if (s) btnLike.textContent = `❤ ${s.likes||0}`;
    }
  });

  // initial + auf URL-Änderung reagieren
  render();
  window.addEventListener('popstate', render);
  // wenn Store sich ändert (z. B. neue Daten)
  window.gioStore.subscribe((type)=>{ if (type.startsWith('songs:')||type.startsWith('artists:')||type.startsWith('releases:')) render(); });
})();
</script>
