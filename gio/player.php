<?php
// /gio/shell/player.php
// Minimaler sichtbarer Player mit stabilen IDs für JS-Bindings.
?>
<div id="gio-player" class="player">
  <div class="player-left">
    <button id="gio-player-prev" class="btn" title="Zurück">«</button>
    <button id="gio-player-toggle" class="btn" title="Play/Pause">▶︎</button>
    <button id="gio-player-next" class="btn" title="Weiter">»</button>
  </div>

  <div class="player-center">
    <div id="gio-player-title" class="title">—</div>
    <div class="timeline">
      <span id="gio-player-time" class="time">0:00</span>
    </div>
  </div>

  <div class="player-right">
    <button id="gio-player-like" class="btn" title="Like">♥</button>
    <span id="gio-player-like-count" class="like-count">0</span>
    <button id="gio-player-mute" class="btn" title="Mute">🔈</button>
    <input id="gio-player-volume" type="range" min="0" max="1" step="0.01" value="1" />
  </div>
</div>
