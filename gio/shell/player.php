<!-- /gio/shell/player.php -->
<div class="gio-player">
  <audio id="gio-audio" preload="none"></audio>

  <div class="gio-player-cover">
    <img src="/gio/assets/img/placeholder.jpg" alt="">
  </div>

  <div class="gio-player-meta">
    <div class="gio-player-title"></div>
    <div class="gio-player-artist"></div>
  </div>

  <div class="gio-player-controls">
    <button id="gio-play-btn" type="button">Play / Pause</button>

    <span class="gio-player-timecurrent">0:00</span>
    <input class="gio-player-slider" type="range" min="0" max="100" value="0">
    <span class="gio-player-timedur">0:00</span>

    <!-- Haken für 31s -->
    <span class="gio-31s" title="31s gezählt" hidden>✔</span>
  </div>

  <div class="gio-player-actions">
    <button id="gio-like-btn" type="button">❤</button>
    <span id="gio-like-count">0</span>

    <input class="gio-vol" type="range" min="0" max="100" value="80">
    <button class="gio-mute" type="button">Mute</button>
  </div>
</div>
