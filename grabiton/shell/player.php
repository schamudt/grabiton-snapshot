<?php // Shell: Player (deckend Blau, läuft weiter) ?>
<div class="gio-playerbar solid" hidden>
  <img class="gio-player-cover" src="" alt="Aktuelles Cover" hidden>
     
  <!-- WICHTIG: data-role="player-like" + eigene Player-Klasse -->
  <button class="gio-like gio-likeinline gio-like--player" data-role="player-like" type="button" aria-label="Like" data-song-id="">
    <span class="gio-like-count" data-song-id="">0</span>
    <svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16">
      <path d="M18 2h-2a1 1 0 0 0-1 1v2H9V3a1 1 0 0 0-1-1H6a1 1 0 0 1-1 1v2H3a1 1 0 0 1-1 1v2a5 5 0 0 0 5 5h1.07A7.01 7.01 0 0 0 11 16.92V19H8a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2h-3v-2.08A7.01 7.01 0 0 0 14.93 13H16a5 5 0 0 0 5-5V6a1 1 0 0 0-1-1h-2V3a1 1 0 0 0-1-1Zm-1 6V6h3v2a3 3 0 0 1-3 3h-1V8ZM6 11a3 3 0 0 1-3-3V6h3v3h1v2H6Z"/>
    </svg>
  </button>

  <div class="gio-player-meta">
    <div class="gio-player-title">–</div>
    <a class="gio-player-artist" href="#">–</a>
  </div>

  <div class="gio-player-center">
    <div class="gio-progress">
      <input class="gio-player-slider" type="range" min="0" max="100" step="0.5" value="0" aria-label="Wiedergabeposition">
      <button class="gio-skip" type="button" aria-label="Nächster Titel" title="Nächster Titel">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
          <path d="M5 5.5v13l9-6.5-9-6.5zM20 5.5v13h-2v-13h2z"/>
        </svg>
      </button>
      <div class="gio-player-time">
        <span class="gio-player-timecurrent">0:00</span> / <span class="gio-player-timedur">0:00</span>
      </div>
    </div>
    <div class="gio-audio-ctrl">
      <button class="gio-mute" type="button" aria-label="Ton aus/an" aria-pressed="false">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M3 10v4h4l5 5V5L7 10H3zm13.5 2a4.5 4.5 0 0 0-2.5-4.03v8.06A4.5 4.5 0 0 0 16.5 12z"/>
        </svg>
      </button>
      <input class="gio-vol" type="range" min="0" max="100" step="1" value="100" aria-label="Lautstärke">
    </div>
  </div>

  <audio id="gio-audio" preload="none"></audio>
</div>

<script>
(function(){
  const btn   = document.querySelector('.gio-skip');
  const audio = document.querySelector('audio') || document.getElementById('gio-audio');
  if (!btn || !audio) return;
  btn.addEventListener('click', function(ev){
    ev.preventDefault();
    ev.stopPropagation();
    if (typeof window.gioNext === 'function') { try { window.gioNext(); return; } catch(e){} }
    try { audio.pause(); audio.dispatchEvent(new Event('ended', { bubbles: true })); }
    catch (e) { try { audio.currentTime = Math.max(0, (audio.duration || 0) - 0.05); } catch(_) {} }
  });
})();
</script>
