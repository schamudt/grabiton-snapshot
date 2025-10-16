<section>
  <h2>Home</h2>
  <p>Klick auf den Knopf, dann setzen wir Titel/Artist im Player (und optional eine Test-MP3).</p>

  <button id="demo-load">Demo-Song laden</button>
  <p style="font-size:.9em;opacity:.8">Hinweis: Dieser Knopf ruft eine globale JS-Funktion auf, die bereits in <code>app.js</code> definiert ist.</p>
</section>

<!-- /GIO/views/home.php -->
<section class="gio-home">
  <h2>Angesagt</h2>
  <div id="gio-home-list" class="gio-grid"></div>
</section>

<script>
(function(){
  const $list = document.getElementById('gio-home-list');
  let lastIds = [];

  function card(song){
    const a = window.gioStore.getArtistById(song.artistId);
    const el = document.createElement('article');
    el.className = 'gio-card';
    el.innerHTML = `
      <button class="gio-card-play" data-song="${song.id}" aria-label="Play">
        ${song.cover ? `<img src="${song.cover}" alt="">` : ''}
        <div class="meta">
          <div class="title">${song.title}</div>
          <div class="artist">${a ? a.name : 'Unbekannt'}</div>
        </div>
      </button>
      <div class="row">
        <button class="gio-like" data-like="${song.id}">❤ ${(song.likes||0)}</button>
      </div>
    `;
    return el;
  }

  function render(songs){
    $list.innerHTML = '';
    lastIds = songs.map(s => s.id);
    songs.forEach(s => $list.appendChild(card(s)));
  }

  document.addEventListener('gio:home:init', (e) => render(e.detail || []));
  window.gioStore.subscribe((type) => {
    if (type.startsWith('songs:')) render(window.gioStore.getSongs());
  });

  $list.addEventListener('click', (ev) => {
    const btnPlay = ev.target.closest('.gio-card-play');
    if (btnPlay){
      const id = btnPlay.dataset.song;
      const startIndex = Math.max(0, lastIds.indexOf(id));
      window.gioPlayer.setQueue(lastIds, startIndex);
      window.gioPlayer.play();
      return;
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
})();
</script>


