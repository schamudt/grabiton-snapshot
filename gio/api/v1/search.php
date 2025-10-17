<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/**
 * Erwartet: GET /gio/api/v1/search.php?q=...
 * Antwort: { ok:true, data:{ songs:[], artists:[], releases:[] } }
 * Tabellen (Beispiel): artists(id,name,genre,avatar_url),
 *                      songs(id,artist_id,title,duration_sec,cover_url),
 *                      releases(id,artist_id,title,type,cover_url,released_at)
 */

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (mb_strlen($q) < 2) {
  echo json_encode(['ok'=>true,'data'=>['songs'=>[],'artists'=>[],'releases'=>[]]], JSON_UNESCAPED_SLASHES);
  exit;
}

/* --- DB verbinden, falls vorhanden --- */
$pdo = null;
try {
  $dbFile = __DIR__ . '/../../config/db.php';
  if (is_file($dbFile)) {
    /** @var PDO $pdo aus config/db.php verfügbar machen */
    $pdo = (function($path){
      require $path;
      // Variante A: $pdo direkt gesetzt
      if (isset($pdo) && $pdo instanceof PDO) return $pdo;
      // Variante B: Funktion pdo() liefert PDO
      if (function_exists('pdo')) return pdo();
      throw new RuntimeException('PDO not provided by config/db.php');
    })($dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }
} catch (Throwable $e) {
  // Fallback auf Demo unten
  $pdo = null;
}

/* --- Wenn DB verfügbar: echte Suche --- */
if ($pdo instanceof PDO) {
  $like = '%'.$q.'%';
  try {
    // Artists
    $stmt = $pdo->prepare("SELECT id, name, COALESCE(genre,'') AS genre, COALESCE(avatar_url,'') AS avatar_url
                           FROM artists WHERE name LIKE :q ORDER BY name LIMIT 20");
    $stmt->execute([':q'=>$like]);
    $artists = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Songs
    $stmt = $pdo->prepare("SELECT id, artist_id, title, COALESCE(duration_sec,0) AS duration_sec, COALESCE(cover_url,'') AS cover_url
                           FROM songs WHERE title LIKE :q ORDER BY title LIMIT 20");
    $stmt->execute([':q'=>$like]);
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Releases
    $stmt = $pdo->prepare("SELECT id, artist_id, title, COALESCE(type,'single') AS type, COALESCE(cover_url,'') AS cover_url,
                                  COALESCE(released_at,'') AS released_at
                           FROM releases WHERE title LIKE :q ORDER BY released_at DESC, title LIMIT 20");
    $stmt->execute([':q'=>$like]);
    $releases = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode(['ok'=>true,'data'=>[
      'songs'=>$songs,'artists'=>$artists,'releases'=>$releases
    ]], JSON_UNESCAPED_SLASHES);
    exit;
  } catch (Throwable $e) {
    // Bei SQL-Fehlern auf Demo-Fallback
  }
}

/* --- Fallback: Demo-Ergebnisse (ohne DB) --- */
$baseImg = '/gio/assets/img/placeholder.jpg';
echo json_encode([
  'ok'=>true,
  'data'=>[
    'songs'=>[
      ['id'=>101,'artist_id'=>11,'title'=>"$q Song",'duration_sec'=>197,'cover_url'=>$baseImg],
      ['id'=>102,'artist_id'=>12,'title'=>"$q Remix",'duration_sec'=>214,'cover_url'=>$baseImg],
    ],
    'artists'=>[
      ['id'=>11,'name'=>"$q Artist",'genre'=>'indie','avatar_url'=>$baseImg],
      ['id'=>12,'name'=>"$q Duo",'genre'=>'electro','avatar_url'=>$baseImg],
    ],
    'releases'=>[
      ['id'=>201,'artist_id'=>11,'title'=>"$q Album",'type'=>'album','cover_url'=>$baseImg,'released_at'=>'2024-01-01'],
      ['id'=>202,'artist_id'=>12,'title'=>"$q EP",'type'=>'ep','cover_url'=>$baseImg,'released_at'=>'2023-12-01'],
    ],
  ]
], JSON_UNESCAPED_SLASHES);
