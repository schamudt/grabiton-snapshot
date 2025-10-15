<?php
/**
 * /grabiton/fragments/artists.php
 * Übersicht aller Artists – Bannerzeile: A–Z … Alle + Suche (eine Linie),
 * quadratische Avatare, Live-Filter, Grid mit 6 pro Zeile.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/db.php';

if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}

/* --- Daten wie in artist.php: alles direkt aus DB, plus Release-Count --- */
try {
  $stmt = $pdo->query("
    SELECT
      a.id,
      a.artist_name,
      a.genre,
      a.artist_code,
      a.avatar_path,
      a.banner_path,
      (SELECT COUNT(1) FROM releases r WHERE r.artist_id = a.id) AS release_count
    FROM artists a
    ORDER BY a.artist_name ASC
  ");
  $artists = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $artists = [];
}

/* Pfade */
$assetBase       = '/grabiton/assets/img';
$avatarFallback  = $assetBase . '/cover_fallback.jpg';
$banner          = $assetBase . '/artistsbanner_place.jpg';

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<div class="artist-page" id="artists-overview" data-js="artists-overview">

  <!-- HERO/BANNER -->
  <section class="artist-hero">
    <div class="artist-hero__bg">
      <?php if ($banner !== ''): ?>
        <img src="<?= esc($banner) ?>" alt="" class="hero-img" onerror="this.style.display='none'">
      <?php else: ?>
        <div class="hero-fallback"></div>
      <?php endif; ?>
      <div class="hero-gradient-bottom" aria-hidden="true"></div>
    </div>

    <!-- Eine Linie: links Titel, rechts A–Z … Alle + Suche -->
    <div class="artist-hero__row -singleline">
      <div class="artist-hero__name">
        <h1 class="artist-title">Artists</h1>
      </div>

      <div class="hero-tools" role="group" aria-label="Filter und Suche">
        <div class="hero-alpha" role="tablist" aria-label="Artists nach Anfangsbuchstaben filtern">
          <?php foreach (range('A','Z') as $L): ?>
            <button type="button" class="alpha-btn" data-letter="<?= $L ?>" role="tab" aria-selected="false"><?= $L ?></button>
          <?php endforeach; ?>
          <button type="button" class="alpha-btn" data-letter="other" role="tab" aria-selected="false">…</button>
          <button type="button" class="alpha-btn -clear" data-letter="all" role="tab" aria-selected="true" title="Alle zeigen">Alle</button>
        </div>

        <div class="hero-search">
          <input type="search" class="artist-search" placeholder="Artist suchen …" aria-label="Artist suchen" autocomplete="off" spellcheck="false">
        </div>
      </div>
    </div>
  </section>

  <!-- GRID -->
  <section class="artist-content -onlygrid">
    <div class="artist-content__main -full">
      <?php if (empty($artists)): ?>
        <div class="empty-state"><p>Noch keine Artists angelegt.</p></div>
      <?php else: ?>
        <div class="artist-grid" id="artist-grid">
          <?php foreach ($artists as $a):
            $name    = trim((string)($a['artist_name'] ?? 'Unbekannt'));
            $code    = trim((string)($a['artist_code'] ?? ''));
            $genre   = trim((string)($a['genre'] ?? ''));
            $avatar  = trim((string)($a['avatar_path'] ?? ''));
            $rcount  = (int)($a['release_count'] ?? 0);
            if ($avatar === '') $avatar = $avatarFallback;

            $href = $code !== ''
              ? ("/grabiton/?code=" . rawurlencode($code))
              : "/grabiton/artists?artist_id=".(int)($a['id'] ?? 0);

            $first = mb_substr($name, 0, 1, 'UTF-8');
            $upper = mb_strtoupper($first, 'UTF-8');
            $group = preg_match('/^[A-Z]$/', $upper) ? $upper : 'other';
          ?>
            <div class="artist-item"
                 data-name="<?= esc($name) ?>"
                 data-group="<?= esc($group) ?>"
                 data-genre="<?= esc($genre) ?>"
                 data-releases="<?= $rcount ?>">
              <a class="artist-thumb" href="<?= esc($href) ?>" data-nav="push" aria-label="<?= esc($name) ?>">
                <img src="<?= esc($avatar) ?>" alt="<?= esc($name) ?>"
                     onerror="this.onerror=null;this.src='<?= esc($avatarFallback) ?>';">
              </a>
              <div class="artist-name-label" title="<?= esc($name) ?>"><?= esc($name) ?></div>
              <?php if ($genre !== '' || $rcount > 0): ?>
                <div class="artist-sub">
                  <?php if ($genre !== ''): ?><span class="g"><?= esc($genre) ?></span><?php endif; ?>
                  <?php if ($rcount > 0): ?><span class="r"><?= $rcount ?> Rel.</span><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@700&display=swap');

:root{ --header-h:72px; }
.artist-page{ --c-border:rgba(255,255,255,.1); --c-text:#eaf0ff; --c-text-dim:#b6c0ff; color:var(--c-text); }

/* HERO */
.artist-hero{ position:relative; width:100%; min-height:360px; margin-top:calc(-1 * var(--header-h)); }
.hero-img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; transform:translateY(-5px); }
.hero-fallback{
  position:absolute; inset:-5px 0 0 0;
  background-image:
    radial-gradient(800px 280px at 20% 20%, rgba(61,94,255,.25), rgba(0,0,0,0)),
    radial-gradient(900px 360px at 80% 0%, rgba(4,134,170,.20), rgba(0,0,0,0)),
    linear-gradient(180deg, rgba(3,13,47,1) 0%, rgba(3,13,47,.96) 40%, rgba(3,13,47,.94) 100%);
}
.hero-gradient-bottom{
  position:absolute; left:0; right:0; bottom:-1px; height:260px;
  background:linear-gradient(to bottom, rgba(3,13,47,0.00) 0%, rgba(3,13,47,.5) 25%, rgba(3,13,47,.9) 90%);
}

/* Eine Linie: Titel links, rechts Buttons + Suche (alle auf gleicher Grundlinie) */
.artist-hero__row.-singleline{
  position:absolute; left:24px; right:24px; bottom:18px;
  display:flex; align-items:flex-end; gap:16px; z-index:2;
}
.artist-hero__name{ flex:0 0 auto; }
.artist-title{
  font-family:'Montserrat',sans-serif; font-weight:700; text-transform:uppercase;
  font-size:calc(1rem * 4.0); margin:0; color:#fff; text-shadow:0 3px 10px rgba(0,0,0,.6);
}

/* Rechtsblock: A–Z … Alle + Suche in einer Zeile */
.hero-tools{
  flex:1 1 auto;
  display:flex; align-items:flex-end; gap:12px; justify-content:flex-end;
  flex-wrap:wrap; /* falls schmal, darf umbrechen */
}
.hero-alpha{
  display:flex; align-items:flex-end; gap:6px; flex-wrap:wrap;
  max-width:calc(100% - 460px); /* Platz für Suche reservieren */
}
.alpha-btn{
  appearance:none; border:1px solid var(--c-border);
  background:rgba(255,255,255,.12); color:#fff; border-radius:10px;
  padding:4px 8px;
  font-size:10px; line-height:1;
  cursor:pointer; white-space:nowrap;
}
.alpha-btn[aria-selected="true"]{ background:rgba(255,255,255,.22); }
.alpha-btn:hover{ background:rgba(255,255,255,.18); }
.alpha-btn.-clear{ font-weight:600; }

.hero-search{ flex:0 0 420px; max-width:42vw; }
.hero-search .artist-search{
  width:100%; background:rgba(0,0,0,.35);
  border:1px solid rgba(255,255,255,.22); border-radius:12px; color:#fff;
  padding:9px 12px; font-size:14px; outline:none;
  backdrop-filter:blur(6px);
}
.hero-search .artist-search::placeholder{ color:#cbd3ff; }

/* GRID: 6 pro Reihe */
.artist-content{ padding:18px 24px 32px; position:relative; }
.artist-content__main.-full{ max-width:none; }
.artist-grid{
  display:grid;
  grid-template-columns: repeat(6, 1fr);
  gap:24px 24px;
}
@media (max-width:1200px){ .artist-grid{ grid-template-columns: repeat(5, 1fr); } }
@media (max-width:992px) { .artist-grid{ grid-template-columns: repeat(4, 1fr); } }
@media (max-width:768px) { .artist-grid{ grid-template-columns: repeat(3, 1fr); } }
@media (max-width:520px) { .artist-grid{ grid-template-columns: repeat(2, 1fr); } }

.artist-item{ display:flex; flex-direction:column; align-items:flex-start; }

/* QUADRATISCHE Avatare (keine Rundung) */
.artist-thumb{
  display:inline-block; width:100px; height:100px;
  overflow:hidden; line-height:0; cursor:pointer;
  box-shadow:0 8px 18px rgba(0,0,0,.35);
}
.artist-thumb img{
  width:100%; height:100%; object-fit:cover; display:block;
  transition:transform .25s ease;
}
.artist-thumb:hover img{ transform:scale(1.03); }

.artist-name-label{
  margin-top:6px; font-size:12px; color:#e8ecff; max-width:100px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.artist-sub{
  margin-top:2px; font-size:11px; color:#cfd7ff; display:flex; gap:8px;
}
.artist-sub .r{ opacity:.9; }

/* Sonstiges */
.section-title{ margin:0 0 14px 0; }
.empty-state{ padding:18px; border:1px dashed var(--c-border); border-radius:12px; color:#c9d1ff; background:rgba(255,255,255,.03); }
</style>

<script>
(() => {
  const root = document.querySelector('[data-js="artists-overview"]');
  if (!root) return;

  const grid   = root.querySelector('#artist-grid');
  const search = root.querySelector('.artist-search');
  const alpha  = Array.from(root.querySelectorAll('.alpha-btn'));

  let activeLetter = 'all';
  let q = '';

  function getItems(){
    // robuster als grid.children (ignoriert Textknoten)
    return grid ? Array.from(grid.querySelectorAll('.artist-item')) : [];
  }

  function applyFilter(){
    const items  = getItems();
    const needle = (q || '').trim().toLowerCase();

    items.forEach(item => {
      const name  = (item.getAttribute('data-name')  || '').toLowerCase();
      const group = (item.getAttribute('data-group') || 'other').toLowerCase();

      const passLetter =
        (activeLetter === 'all') ? true :
        (activeLetter === 'other' ? group === 'other' : group === (activeLetter||'').toLowerCase());

      const passSearch = needle === '' ? true : name.includes(needle);
      item.style.display = (passLetter && passSearch) ? '' : 'none';
    });
  }

  // Klicks auf A–Z / … / Alle
  alpha.forEach(btn => {
    btn.addEventListener('click', () => {
      alpha.forEach(b => b.setAttribute('aria-selected','false'));
      btn.setAttribute('aria-selected','true');
      activeLetter = btn.dataset.letter || 'all';
      applyFilter();
    });
  });

  // Live-Suche (leicht gedrosselt)
  let t;
  if (search) {
    search.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => { q = search.value || ''; applyFilter(); }, 100);
    });
  }

  // Initialzustand: „Alle“ aktiv, alles sichtbar
  const clearBtn = root.querySelector('.alpha-btn.-clear');
  if (clearBtn) {
    alpha.forEach(b => b.setAttribute('aria-selected','false'));
    clearBtn.setAttribute('aria-selected','true');
    activeLetter = 'all';
  }
  applyFilter();
})();
</script>
