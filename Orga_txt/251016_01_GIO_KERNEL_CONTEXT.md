# GIO-KERNEL CONTEXT

## Projektname
**gio**

## Ziel
Neuaufbau eines modularen Musik-Webplayers mit:
- globalem Router und globalem State-Management
- Player mit Like-, Play-, Volume- und Statistik-Funktionen
- Admin-Backend und zukünftigem Artist-Login
- sauberer, erweiterbarer Struktur auf Webspace /gio/

---

## Technischer Stand (Kernel 1)

### Struktur
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

## Funktionalität

### Shell
Header, Sidebar, Footer und Player bilden das feste Layout (werden nie neu geladen).  
Main-Content liegt in `<main id="gio-main">` und wird dynamisch nachgeladen.

### Routing
Ein minimalistischer Router lädt Views per `fetch()`.  
Navigation über `[data-route]`-Attribute, `pushState` und `popstate`.

### Player
- Globale Instanz `gio-audio`
- Cover klickbar für Play/Pause
- Play/Pause-Button `gio-play-btn`
- Slider `.gio-player-slider`
- Volume `.gio-vol`, Mute `.gio-mute`
- Zeitdarstellung `.gio-player-timecurrent` / `.gio-player-timedur`
- UI-Update synchron mit Audio-Events

### Likes
Globale Buttons mit `gio-like-btn` und `gio-like-count`  
Kommunikation über `likes.toggle.php` und `likes.get.php`.

### Statistik
Zwei Zähler:
- 2s → `/gio/api/plays.start.php`
- 31s → `/gio/api/plays.log.php`
beide sessionbasiert, clientseitig über Performance-Zeitmessung kumulativ gezählt.

### Volume/Mute
Lautstärke wird in `localStorage` persistiert.  
Mute-Toggle speichert den letzten Wert vor Stummschaltung.

### Demo-View
Button `#demo-load` simuliert Songwechsel:
- ruft `gioSetCurrentSong({...})` auf
- spielt `/gio/assets/audio/demo.mp3`
- resettet Session-Counter

---

## Aktueller Status
✅ Shell, Routing, Player, Likes, Stats, Volume, UI  
✅ 2s/31s Counter funktionieren zuverlässig  
✅ Kein Reload, alle Module modular  
✅ Fehlerfreie API-Kommunikation  
✅ Grundlage für Erweiterung steht

---

## Nächste Schritte
1. Globaler Daten-Store (`store.js`) mit Song-/Artist-/Release-Infos
2. Songcards und globale Suche
3. Admin-Backend (Login, CRUD, Sichtbarkeit)
4. Echte DB-Anbindung (MySQL)
5. Diagnose-Tools, Statistiken, Speicherabfragen

---

## Weiterarbeit
Im nächsten Chat:
> „weiter mit GIO-Kernel Stand 1 — Songcards, Global Store, Suche verbinden.“

Damit wird dieser Kontext wiederhergestellt.
