<?php
/**
 * /grabiton/fragments/artist.php
 * Öffentliche Künstler/Band-Seite – Cover ohne Rahmen/Hintergrund, 100 px breit, 50 px Abstand zwischen den Covern.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/db.php';

/* --- Code aus GET oder Pretty-URL extrahieren --- */
$code = $_GET['code'] ?? '';
if ($code === '') {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/artist/(gio\d{8})(?:/|$)#', $uri, $m)) {
        $code = $m[1];
    }
}

/* --- 404 Helper --- */
function render_not_found(string $msg = 'Künstler*in nicht gefunden.'): void {
    http_response_code(404); ?>
    <div class="artist-page nf-wrap">
      <div class="nf-card">
        <h1>404</h1>
        <p><?= htmlspecialchars($msg) ?></p>
        <a class="btn" href="/grabiton/">Zur Startseite</a>
      </div>
    </div>
    <style>
      .nf-wrap{min-height:calc(100vh - 160px);display:grid;place-items:center;padding:48px}
      .nf-card{background:rgba(255,255,255,.04);backdrop-filter:blur(12px);border-radius:16px;padding:28px 32px;max-width:560px;text-align:center}
      .nf-card h1{margin:0 0 8px;color:#fff}
      .nf-card p{margin:0 0 18px;color:#c8d1ff}
      .btn{display:inline-block;padding:10px 16px;border-radius:12px;text-decoration:none;background:#2b5dff;color:#fff}
    </style>
    <?php exit;
}

if (!preg_match('/^gio\d{8}$/', $code)) {
    render_not_found('Ungültiger oder fehlender Künstlercode.');
}

/* --- Daten laden --- */
try {
    $stmt = $pdo->prepare('SELECT id, artist_name, genre, artist_code, avatar_path, banner_path
                           FROM artists WHERE artist_code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $artist = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$artist) render_not_found();

    $artistId   = (int)$artist['id'];
    $artistName = trim((string)($artist['artist_name'] ?? 'Unbenannt'));
    $genre      = trim((string)($artist['genre'] ?? ''));
    $avatar     = trim((string)($artist['avatar_path'] ?? ''));
    $banner     = trim((string)($artist['banner_path'] ?? ''));

    $relStmt = $pdo->prepare('SELECT id, title, release_type, cover_path, release_date
                              FROM releases WHERE artist_id = :aid
                              ORDER BY release_date DESC, id DESC');
    $relStmt->execute([':aid' => $artistId]);
    $releases = $relStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (Throwable $e) {
    render_not_found('Es ist ein Fehler aufgetreten.');
}

$assetBase     = '/grabiton/assets/img';
$coverFallback = $assetBase . '/cover_fallback.jpg';

/* --- Hilfsfunktionen --- */
function year_or_unknown(?string $date): string {
    if (!$date) return '—';
    $y = substr($date, 0, 4);
    return preg_match('/^\d{4}$/', $y) ? $y : '—';
}
function release_type_label(?string $t): string {
    $t = strtolower(trim((string)$t));
    return $t === 'album' ? 'Album' : ($t === 'ep' ? 'EP' : ($t === 'single' ? 'Single' : strtoupper($t)));
}
?>
<div class="artist-page" id="artist-page" data-artist-code="<?= htmlspecialchars($code) ?>">

  <!-- HERO/BANNER -->
  <section class="artist-hero">
    <div class="artist-hero__bg">
      <?php if ($banner !== ''): ?>
        <img src="<?= htmlspecialchars($banner) ?>" alt="" class="hero-img" onerror="this.style.display='none'">
      <?php else: ?>
        <div class="hero-fallback"></div>
      <?php endif; ?>
      <div class="hero-gradient-bottom" aria-hidden="true"></div>
    </div>

    <!-- ID-Badge: absolut im Banner, unterhalb des Headers -->
    <div id="artist-id-badge" class="artist-id-badge"><?= htmlspecialchars($code) ?></div>

    <div class="artist-hero__name">
      <h1 class="artist-title"><?= htmlspecialchars($artistName) ?></h1>
      <?php if ($genre !== ''): ?><div class="artist-genre"><?= htmlspecialchars($genre) ?></div><?php endif; ?>
    </div>

    <?php if ($avatar !== ''): ?>
    <div class="artist-hero__avatar">
      <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="artist-avatar" onerror="this.style.display='none'">
    </div>
    <?php endif; ?>
  </section>

  <!-- CONTENT -->
  <section class="artist-content">
    <div class="artist-content__main">
      <h2 class="section-title">Releases</h2>

      <?php if (empty($releases)): ?>
        <div class="empty-state"><p>Noch keine Veröffentlichungen.</p></div>
      <?php else: ?>
        <div class="release-grid">
          <?php foreach ($releases as $rel):
            $rid   = (int)$rel['id'];
            $cover = trim((string)($rel['cover_path'] ?? '')) ?: $coverFallback;
            $year  = year_or_unknown($rel['release_date'] ?? null);
            $type  = release_type_label($rel['release_type'] ?? null);
            $href  = "/grabiton/releases?release_id={$rid}";
          ?>
            <div class="release-item">
              <a class="release-cover" href="<?= htmlspecialchars($href) ?>" data-nav="push" aria-label="Release öffnen">
                <img src="<?= htmlspecialchars($cover) ?>" alt="Release-Cover"
                     onerror="this.onerror=null;this.src='<?= htmlspecialchars($coverFallback, ENT_QUOTES) ?>';">
              </a>
              <div class="release-meta">
                <span class="release-year"><?= htmlspecialchars($year) ?></span>
                <span class="release-type"><?= htmlspecialchars($type) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="artist-aside">
      <div class="info-card">
        <div class="info-primary">
          <div class="info-name"><?= htmlspecialchars($artistName) ?></div>
          <div class="info-id"><?= htmlspecialchars($code) ?></div>
          <?php if ($genre !== ''): ?><div class="info-genre"><?= htmlspecialchars($genre) ?></div><?php endif; ?>
        </div>
        <div class="artist-bio"><p class="bio-placeholder">— Hier erscheinen Textbausteine aus dem Adminbereich —</p></div>
      </div>
    </aside>
  </section>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@700&display=swap');

:root{ --header-h:72px; --avatar-size:276px; }
.artist-page{ --c-border:rgba(255,255,255,.1); --c-text:#eaf0ff; --c-text-dim:#b6c0ff; color:var(--c-text); }

/* HERO */
.artist-hero{ position:relative; width:100%; min-height:380px; margin-top:calc(-1 * var(--header-h)); }
.hero-img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; transform:translateY(-5px); }
.hero-fallback{
  position:absolute; inset:-5px 0 0 0;
  background-image:
    radial-gradient(800px 280px at 20% 20%, rgba(61,94,255,.25), rgba(0,0,0,0)),
    radial-gradient(900px 360px at 80% 0%, rgba(4,134,170,.20), rgba(0,0,0,0)),
    linear-gradient(180deg, rgba(3,13,47,1) 0%, rgba(3,13,47,.96) 40%, rgba(3,13,47,.94) 100%);
}
.hero-gradient-bottom{
  position:absolute; left:0; right:0; bottom:-1px; height:280px;
  background:linear-gradient(to bottom, rgba(3,13,47,0.00) 0%, rgba(3,13,47,.5) 25%, rgba(3,13,47,.9) 90%);
}

/* ID-Badge: absolut im Banner, NICHT mehr fixed, unter dem Header sichtbar */
.artist-id-badge{
  position:absolute;
  left:24px;
  top:calc(var(--header-h) + 8px); /* <- hier: unterhalb Headerkante */
  z-index:20;                      /* höher als Avatar(5)/Gradient(0) */
  color:#fff;
  font:600 13px/1.2 ui-monospace;
  text-shadow:0 2px 6px rgba(0,0,0,.45);
}

.artist-hero__name{ position:absolute; left:24px; bottom:22px; }
.artist-title{ font-family:'Montserrat',sans-serif; font-weight:700; text-transform:uppercase; font-size:calc(1rem * 4.875); margin:0; text-shadow:0 3px 10px rgba(0,0,0,.6); }
.artist-genre{ margin-top:2px; font-size:15px; color:#dfe6ff; }

.artist-hero__avatar{ position:absolute; right:24px; top:calc(var(--header-h) + 20px); z-index:5; }
.artist-avatar{ width:var(--avatar-size); height:var(--avatar-size); border-radius:50%; object-fit:cover; box-shadow:0 18px 40px rgba(0,0,0,.45), 0 0 0 3px rgba(255,255,255,.15); }

.artist-content{
  display:grid; grid-template-columns:1fr 320px; gap:18px;
  padding:24px 24px 32px; position:relative;
}
@media (max-width:1024px){ .artist-content{ grid-template-columns:1fr; } }

.info-card{ background:rgba(255,255,255,.06); border:1px solid var(--c-border); border-radius:16px; padding:26px 16px; }
.info-primary .info-name{ font-family:'Montserrat',sans-serif; font-weight:700; text-transform:uppercase; font-size:20px; }
.info-primary .info-id{ margin:3px 0 6px; font:600 12px/1.2 ui-monospace; color:#cfd7ff; opacity:.9; }
.info-primary .info-genre{ font-size:13px; color:var(--c-text-dim); }

/* RELEASES – Cover exakt 100px breit, 50px Abstand */
.section-title{ margin:0 0 14px 0; }
.release-grid{
  display:flex;
  flex-wrap:wrap;
  gap:50px; /* Abstand zwischen Covern – 50 px in beide Richtungen */
  align-items:flex-start;
}
.release-item{
  display:flex;
  flex-direction:column;
  align-items:flex-start;
  width:100px;
  background:transparent;
}
.release-cover{
  display:inline-block;
  width:100px;
  height:100px;
  overflow:hidden;
  line-height:0;
  cursor:pointer;
}
.release-cover img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
  transition:transform .25s ease;
}
.release-cover:hover img{ transform:scale(1.03); }
.release-meta{
  display:flex;
  justify-content:space-between;
  align-items:center;
  width:100px;
  margin-top:6px;
}
.release-year{ font-size:12px; color:#bfc7ff; }
.release-type{ font-size:12px; color:#e8ecff; text-align:right; }

.empty-state{ padding:18px; border:1px dashed var(--c-border); border-radius:12px; color:#c9d1ff; background:rgba(255,255,255,.03); }
</style>
