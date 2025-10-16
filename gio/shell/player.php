<?php // Shell: Player (deckend Blau, läuft weiter) ?>
<div class="gio-playerbar solid">
  <!-- Cover: sichtbar & klickbar -->
  <img class="gio-player-cover" src="" alt="Aktuelles Cover" style="cursor:pointer">

  <!-- Like-Button (global) -->
  <button id="gio-like-btn" type="button" aria-label="Like" data-song-id="" style="display:inline-block">
    ♥ Like <span id="gio-like-count">0</span>
  </button>

  <div class="gio-player-meta">
    <div class="gio-player-title">–</div>
    <a class="gio-player-artist" href="#">–</a>
  </div>

  <div class="gio-player-center">
    <div class="gio-progress">
      <input class="gio-player-slider" type="range" min="0" max="100" step="0.5" value="0" aria-label="Wiedergabeposition">

      <!-- NEU: Play/Pause-Button -->
      <button id="gio-play-btn" type="button" aria-label="Play/Pause" title="Play/Pause">▶</button>

      <!-- Skip bleibt wie gehabt -->
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

  <audio id="gio-audio" preload="auto"></audio>


</div>

<!-- Skip-Logik wie gehabt -->
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
