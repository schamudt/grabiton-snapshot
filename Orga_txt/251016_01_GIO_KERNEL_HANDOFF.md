# GIO-KERNEL HANDOFF

## Projektstatus: Kernel Stand 1

### Projektname
**gio**

### Zweck
Neuaufbau der Musikplattform mit globalem System:
- einheitlicher Shell-Aufbau (Header, Sidebar, Footer, Player)
- dynamisches Routing ohne Reload
- global gesteuerter Audio-Player
- API-basierte Likes, Plays und Statistik
- später Admin-Backend, Artist-Zugänge und Diagnose-Tools

---

## Technischer Aufbau

### Ordnerstruktur
```
gio/
 ├── public/
 │    ├── index.php
 │    ├── .htaccess
 │    ├── assets/
 │    │    ├── css/site.css
 │    │    ├── js/app.js
 │    │    └── audio/demo.mp3
 │    └── views/
 │         └── home.php
 ├── shell/
 │    ├── header.php
 │    ├── sidebar.php
 │    ├── footer.php
 │    └── player.php
 └── api/
      ├── _bootstrap.php
      ├── likes.get.php
      ├── likes.toggle.php
      ├── plays.log.php
      └── plays.start.php
```

---

## Funktionale Kernpunkte

### Shell
- Wird einmalig geladen, bleibt persistent.  
- Enthält globale Komponenten (Header, Sidebar, Footer, Player).  
- Hauptinhalte (`<main id="gio-main">`) werden dynamisch nachgeladen.

### Routing
- JS-Fetch-basiert über `app.js`
- `[data-route]` als Navigationsanker
- `history.pushState` / `popstate` für Zurück-Funktion
- lädt Views aus `/gio/views/{view}.php`

### Player
- Audioelement `#gio-audio` global
- Play/Pause über Cover oder Button (`#gio-play-btn`)
- Slider `.gio-player-slider` (Seekbar)
- Volume `.gio-vol`, Mute `.gio-mute`
- Zeitdarstellung `.gio-player-timecurrent` / `.gio-player-timedur`
- Automatische UI-Updates bei `timeupdate`, `pause`, `ended`, etc.
- Volume persistiert in `localStorage`
- Häkchen erscheint bei Play-Zählung nach 31 Sekunden

### Likes
- Globale Elemente: `#gio-like-btn`, `#gio-like-count`
- Kommunikation über `/gio/api/likes.toggle.php` & `/gio/api/likes.get.php`
- Optimistic UI, Doppelklick-Schutz (`busy`-State)

### Statistiksystem
- Zwei Messpunkte:
  - 2s-Startzähler → `/gio/api/plays.start.php`
  - 31s-Playzähler → `/gio/api/plays.log.php`
- Kumulative Zeitmessung auch über Pausen hinweg
- Clientseitig über `performance.now()`-Ticker
- Ergebnisse sessionbasiert via `sessionStorage`
- Nur einmal pro Song und Session gezählt

### Demo-View (home.php)
- Button `#demo-load` setzt Song:
  - ruft `window.gioSetCurrentSong({...})` auf
  - spielt `/gio/assets/audio/demo.mp3`
  - löscht alte Counter
  - startet Player

---

## Technische Besonderheiten
- Kein Seitenreload: JS verwaltet komplette App-State.
- Shell bleibt stabil → nur Views wechseln.
- API basiert auf PHP-Sessions (vor DB-Anbindung).
- Alle Pfade unter `/gio/` (keine `/grabiton/`-Reste).
- Browser-Storage:  
  - `localStorage`: Volume  
  - `sessionStorage`: Play-/Start-Counter  
- Player lädt Quelle nur, wenn `dataset.needsLoad = '1'`.
- Kein Neustart beim Pause/Play-Toggle.

---

## Aktueller Zustand
✅ Routing funktioniert  
✅ Player stabil (Play, Pause, Seek, Volume, Mute)  
✅ Likes-API aktiv  
✅ 2s & 31s Counter funktionieren zuverlässig  
✅ Häkchen bei 31s sichtbar  
✅ Code modular, Shell bleibt unangetastet  
✅ Grundlage für weitere Systeme vorhanden

---

## Nächster Schritt
**Kernel 2 – Global Layer:**
1. Globaler Store (`store.js`) für Songs, Artists, Releases  
2. Songcards mit globaler Interaktion  
3. Globale Suche  
4. Vorbereitung Admin-/Artist-Bereich  
5. Vorbereitung DB-Anbindung (MySQL)

---

## Übergabe-Anweisung
Im nächsten Chat einfach schreiben:

```
weiter mit GIO-Kernel Stand 1 — Songcards, Global Store, Suche verbinden
```

→ Dann wird dieser Stand als Grundlage verwendet.
