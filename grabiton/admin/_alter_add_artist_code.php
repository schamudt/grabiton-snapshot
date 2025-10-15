<?php
// /grabiton/admin/_alter_add_artist_code.php
require_once __DIR__ . '/../includes/db.php';

// ---- 1) Prüfen, ob Spalte 'artist_code' existiert ----
$hasColumn = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM artists LIKE 'artist_code'")->fetch();
    $hasColumn = (bool)$col;
} catch (Throwable $e) {
    exit("Fehler beim Lesen der Tabellenspalten: " . htmlspecialchars($e->getMessage()));
}

// ---- 2) Wenn nicht vorhanden: Spalte hinzufügen ----
if (!$hasColumn) {
    try {
        $pdo->exec("ALTER TABLE artists ADD COLUMN artist_code VARCHAR(11) NULL");
        echo "<p>Spalte <code>artist_code</code> hinzugefügt.</p>";
    } catch (Throwable $e) {
        exit("<p style='color:#f66'>Fehler beim ALTER TABLE: " . htmlspecialchars($e->getMessage()) . "</p>");
    }
} else {
    echo "<p>Spalte <code>artist_code</code> ist bereits vorhanden.</p>";
}

// ---- 3) Einzigartigen Index prüfen/erstellen ----
$hasIndex = false;
try {
    $stmt = $pdo->query("SHOW INDEX FROM artists");
    while ($row = $stmt->fetch()) {
        if (isset($row['Key_name']) && $row['Key_name'] === 'ux_artists_artist_code') {
            $hasIndex = true;
            break;
        }
    }
} catch (Throwable $e) {
    exit("<p style='color:#f66'>Fehler beim Lesen der Indizes: " . htmlspecialchars($e->getMessage()) . "</p>");
}

if (!$hasIndex) {
    try {
        $pdo->exec("CREATE UNIQUE INDEX ux_artists_artist_code ON artists(artist_code)");
        echo "<p>Einzigartiger Index <code>ux_artists_artist_code</code> erstellt.</p>";
    } catch (Throwable $e) {
        // Falls Name kollidiert, versuchen wir fallback-Name
        try {
            $pdo->exec("CREATE UNIQUE INDEX ux_artists_artist_code_2 ON artists(artist_code)");
            echo "<p>Index <code>ux_artists_artist_code</code> war belegt – <code>ux_artists_artist_code_2</code> erstellt.</p>";
        } catch (Throwable $e2) {
            echo "<p style='color:#f66'>Konnte keinen Unique-Index erstellen: " . htmlspecialchars($e2->getMessage()) . "</p>";
        }
    }
} else {
    echo "<p>Einzigartiger Index ist bereits vorhanden.</p>";
}

// ---- 4) Für bestehende Datensätze Codes nachtragen ----
function makeCode(): string {
    return 'gio' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
}

try {
    $select = $pdo->query("SELECT id FROM artists WHERE artist_code IS NULL OR artist_code = ''");
    $needs = $select->fetchAll();

    $check  = $pdo->prepare("SELECT id FROM artists WHERE artist_code = ? LIMIT 1");
    $update = $pdo->prepare("UPDATE artists SET artist_code = ? WHERE id = ?");

    $assigned = 0;
    foreach ($needs as $row) {
        do {
            $code = makeCode();
            $check->execute([$code]);
            $exists = $check->fetch();
        } while ($exists);

        $update->execute([$code, (int)$row['id']]);
        $assigned++;
    }

    echo "<p>Neu vergebene Codes: <strong>$assigned</strong>.</p>";
} catch (Throwable $e) {
    echo "<p style='color:#f66'>Fehler beim Nachtragen der Codes: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><strong>Fertig.</strong></p>";
