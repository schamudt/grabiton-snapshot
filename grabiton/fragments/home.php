<?php
// /grabiton/fragments/home.php
// Home-Fragment (Angesagt + New) – mit dunklem Verlauf unter dem Bandnamen NUR bei echten Covern.

require_once __DIR__ . '/../includes/db.php';

if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}

/* ---------- Helpers ---------- */
function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function coverFallback(){ return '/grabiton/assets/img/cover_fallback.jpg'; }

/**
 * Gibt [src, is_fallback] zurück.
 * - src: finaler Bildpfad (mit Fallback)
 * - is_fallback: true, wenn KEIN individuelles Cover vorhanden war
 */
function coverData($row){
  $p = trim((string)($row['cover_path'] ?? ''));
  if ($p !== '') return [$p, false];
  return [coverFallback(), true];
}
function avatarSrc($row){
  $p = trim((string)($row['avatar_path'] ?? ''));
  return $p !== '' ? $p : '';
}

/** Artist-Link: robust per Query (?code=gio########) */
function artistHref($row){
  $code = trim((string)($row['artist_code'] ?? ''));
  if ($code !== '' && preg_match('/^gio\\d{8}$/', $code)) return "/grabiton/?code={$code}";
  $aid = (int)($row['artist_id'] ?? 0);
  return $aid > 0 ? "/grabiton/artists?artist_id={$aid}" : "/grabiton/artists";
}

/** sort pinned by newest release date desc, then song id desc */
function sortByReleaseDescThenIdDesc(array &$rows): void {
  usort($rows, function($a,$b){
    $da = $a['release_date'] ?? null; $db = $b['release_date'] ?? null;
    if ($da === $db) return ($b['song_id'] ?? 0) <=> ($a['song_id'] ?? 0);
    return strcmp(($db ?? ''), ($da ?? '')); // neueste zuerst
  });
}
/** unique by song_id, keep first occurrence */
function uniqueBySongId(array $rows): array {
  $seen=[]; $out=[];
  foreach($rows as $r){ $id=(int)($r['song_id']??0); if($id>0 && !isset($seen[$id])){ $seen[$id]=1; $out[]=$r; } }
  return $out;
}
/** pad to target by repeating given list IN ORDER (duplicates allowed) */
function padKeepOrderWithRepeats(array $rows, int $target): array {
  if (!$rows) return [];
  if (count($rows) >= $target) return array_slice($rows, 0, $target);
  $out = $rows; $i = 0;
  while (count($out) < $target) { $out[] = $rows[$i % count($rows)]; $i++; }
  return $out;
}

/* ---------- Data ---------- */
$angPinned = $angPool = $newPinned = $newPool = [];

try{
  $selectBase = "
    s.id AS song_id, s.title AS song_title, s.audio_path,
    r.id AS release_id, r.title AS release_title, r.cover_path, r.release_date,
    a.id AS artist_id, a.artist_name, a.avatar_path, a.artist_code
  ";

  // Angesagt (Pinned)
  $angPinned = $pdo->query("
    SELECT $selectBase, COALESCE(s.is_featured,0) AS is_featured
    FROM songs s
    JOIN artists a ON a.id = s.artist_id
    LEFT JOIN releases r ON r.id = s.release_id
    WHERE COALESCE(s.is_hidden,0)=0
      AND COALESCE(s.is_featured,0)=1
  ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Angesagt (Pool)
  $angPool = $pdo->query("
    SELECT $selectBase, COALESCE(s.is_featured,0) AS is_featured
    FROM songs s
    JOIN artists a ON a.id = s.artist_id
    LEFT JOIN releases r ON r.id = s.release_id
    WHERE COALESCE(s.is_hidden,0)=0
      AND COALESCE(s.is_featured,0)=0
  ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // New (Pinned)
  $newPinned = $pdo->query("
    SELECT $selectBase, COALESCE(s.is_new,0) AS is_new
    FROM songs s
    JOIN artists a ON a.id = s.artist_id
    LEFT JOIN releases r ON r.id = s.release_id
    WHERE COALESCE(s.is_hidden,0)=0
      AND COALESCE(s.is_new,0)=1
  ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // New (Pool)
  $newPool = $pdo->query("
    SELECT $selectBase, COALESCE(s.is_new,0) AS is_new
    FROM songs s
    JOIN artists a ON a.id = s.artist_id
    LEFT JOIN releases r ON r.id = s.release_id
    WHERE COALESCE(s.is_hidden,0)=0
      AND COALESCE(s.is_new,0)=0
  ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Sort pinned
  sortByReleaseDescThenIdDesc($angPinned);
  sortByReleaseDescThenIdDesc($newPinned);

  // Pools mischen (nur Pools!)
  try { shuffle($angPool); } catch(Throwable $e) {}
  try { shuffle($newPool); } catch(Throwable $e) {}

  // Deduplizieren
  $angPinned = uniqueBySongId($angPinned);
  $angPool   = array_values(array_filter(uniqueBySongId($angPool), function($r) use ($angPinned){
    static $ids=null; if($ids===null) $ids=array_column($angPinned,'song_id'); return !in_array($r['song_id'],$ids,true);
  }));
  $newPinned = uniqueBySongId($newPinned);
  $newPool   = array_values(array_filter(uniqueBySongId($newPool), function($r) use ($newPinned){
    static $ids=null; if($ids===null) $ids=array_column($newPinned,'song_id'); return !in_array($r['song_id'],$ids,true);
  }));

  // FINAL
  $ang = padKeepOrderWithRepeats(array_merge($angPinned, $angPool), 25);
  $new = array_merge($newPinned, $newPool);

} catch(Throwable $e){
  $ang = $ang ?? []; $new = $new ?? [];
}

/* ---------- Inline-CSS ---------- */
?>
<style>
  .gio-likeinline{ display:inline-flex; align-items:center; gap:6px; margin-top:6px; padding:0; border:0; background:transparent; cursor:pointer; user-select:none; }
  .gio-like-count{ font-weight:700; font-size:16px; line-height:16px; color:#ffffff; min-width:1.2ch; display:inline-block; }
  .gio-likeinline svg{ width:16px; height:16px; fill:#ffffff; display:block; }
  .gio-likeinline.is-liked svg{ filter: drop-shadow(0 0 2px rgba(255,255,255,.35)); }
  .gio-likeinline:active{ transform: translateY(1px); }

  .gio-card{ position:relative; }
  .gio-card .gio-title, .gio-card .gio-meta span, .gio-card .gio-artistlink{
    display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%;
  }
  .gio-card .gio-meta{ margin-top:2px; }

  /* Medienbereich für Overlay */
  .gio-card-media{ position:relative; }

  /* Standard: Shade verstecken */
  .gio-cover-shade{ display:none; }

  /* FIX: Shade aktivieren + rendern, wenn echtes Cover vorhanden ist */
  .gio-card.has-cover .gio-cover-shade{
    display:block;                       /* <— wichtig */
    position:absolute; left:0; right:0; bottom:0;
    height:46%;                          /* etwas höher für sichere Lesbarkeit */
    background: linear-gradient(
      to top,
      rgba(0,0,0,.80) 0%,
      rgba(0,0,0,.60) 55%,
      rgba(0,0,0,0) 100%
    );
    pointer-events:none; z-index:2;      /* über Bild, unter Text */
  }

  /* Link sichtbar und gut lesbar auf dem Overlay */
  .gio-artistlink{
    position:relative; z-index:3;        /* über Shade */
    color:#fff; text-decoration:none;
    text-shadow: 0 2px 6px rgba(0,0,0,.55);
  }
  .gio-avatar-wrap{
    position:absolute; left:8px; bottom:8px; right:8px;
    display:flex; align-items:center; gap:8px; z-index:3;
  }
  .gio-avatar{ width:20px; height:20px; border-radius:50%; object-fit:cover; }
</style>
<?php

/* ---------- Renderer (Titel/Album-Zeile vertauscht) ---------- */
function renderCarousel($rows, $carouselId, $sizeClass=''){
  if (!$rows){
    echo '<p style="opacity:.7">Keine Einträge vorhanden.</p>';
    return;
  } ?>
  <div class="gio-carousel <?php echo safe($sizeClass); ?>" id="<?php echo safe($carouselId); ?>">
    <button class="gio-car-btn -prev" type="button" aria-label="Zurück">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 1 0 1.4L10.41 12l4.3 4.3a1 1 0 0 1-1.41 1.4l-5-5a1 1 0 0 1 0-1.4l5-5a1 1 0 0 1 1.41 0z"/></svg>
    </button>
    <div class="gio-track">
      <?php foreach($rows as $row):
        $songId     = (int)($row['song_id'] ?? 0);
        [$cover, $isFallback] = coverData($row);
        $avatar     = avatarSrc($row);
        $artist     = safe($row['artist_name'] ?? 'Unbekannt');
        $artistLink = artistHref($row);
        $relTitle   = safe($row['release_title'] ?? '');
        $songTitle  = safe($row['song_title'] ?? '');
        $audio      = safe($row['audio_path'] ?? '');
        $cardClass  = $isFallback ? 'is-fallback' : 'has-cover';
      ?>
      <article class="gio-card <?php echo $cardClass; ?>" data-song-id="<?php echo $songId; ?>">
        <div class="gio-card-media">
          <img class="gio-cover"
               src="<?php echo $cover; ?>"
               alt="<?php echo $relTitle !== '' ? $relTitle : $songTitle; ?>"
               data-src="<?php echo $audio; ?>"
               data-title="<?php echo $songTitle; ?>"
               data-artist="<?php echo $artist; ?>"
               data-artist-href="<?php echo safe($artistLink); ?>"
               data-cover="<?php echo $cover; ?>">
          <?php if (!$isFallback): ?>
            <div class="gio-cover-shade" aria-hidden="true"></div>
          <?php endif; ?>
          <div class="gio-avatar-wrap">
            <?php if ($avatar !== ''): ?><img class="gio-avatar" src="<?php echo $avatar; ?>" alt=""><?php endif; ?>
            <a class="gio-artistlink" href="<?php echo safe($artistLink); ?>"><?php echo $artist; ?></a>
          </div>
        </div>

        <div class="gio-card-body">
          <!-- zuerst Songtitel, darunter Release-/Albumname -->
          <div class="gio-title"><?php echo $songTitle !== '' ? $songTitle : ($relTitle !== '' ? $relTitle : ''); ?></div>
          <div class="gio-meta">
            <?php if ($relTitle !== ''): ?><span><?php echo $relTitle; ?></span><?php endif; ?>
          </div>

          <!-- Like: Zahl vor Icon -->
          <button class="gio-likeinline gio-like" type="button" aria-label="Like" data-song-id="<?php echo $songId; ?>">
            <span class="gio-like-count" data-song-id="<?php echo $songId; ?>">0</span>
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M18 2h-2a1 1 0 0 0-1 1v2H9V3a1 1 0 0 0-1-1H6a1 1 0 0 1-1 1v2H3a1 1 0 0 1-1 1v2a5 5 0 0 0 5 5h1.07A7.01 7.01 0 0 0 11 16.92V19H8a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2h-3v-2.08A7.01 7.01 0 0 0 14.93 13H16a5 5 0 0 0 5-5V6a1 1 0 0 0-1-1h-2V3a1 1 0 0 0-1-1Zm-1 6V6h3v2a3 3 0 0 1-3 3h-1V8ZM6 11a3 3 0 0 1-3-3V6h3v3h1v2H6Z"/>
            </svg>
          </button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <button class="gio-car-btn -next" type="button" aria-label="Weiter">
      <svg viewBox="0 0 24 24" aria-hidden="true" style="transform:scaleX(-1)"><path d="M14.7 6.3a1 1 0 0 1 0 1.4L10.41 12l4.3 4.3a1 1 0 0 1-1.41 1.4l-5-5a1 1 0 0 1 0-1.4l5-5a1 1 0 0 1 1.41 0z"/></svg>
    </button>
  </div>
<?php } ?>

<section class="gio-section">
  <h2 class="gio-h2">Angesagt</h2>
  <?php renderCarousel($ang, 'gio-hip', '-large'); ?>
</section>

<section class="gio-section">
  <h2 class="gio-h2">New</h2>
  <?php renderCarousel($new, 'gio-new', '-small'); ?>
</section>
