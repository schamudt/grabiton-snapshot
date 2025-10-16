
# GIO Page Project — Context

## Projektüberblick
GIO ist eine Musikplattform auf Basis von PHP, JavaScript und MySQL, gehostet bei IONOS. Die Architektur folgt einem SPA-Light-Ansatz mit globalem Router und Store. Player, Sidebar, Header und Footer liegen als separate Fragmente unter `/gio/shell/`. Die Views (z. B. `home`, `artist`, `explore`) werden per Fetch nachgeladen und ersetzen dynamisch den Seiteninhalt.

## Aktueller Stand
- Routing über `app.js`, keine Seitenreloads
- Player (`player.js`) global mit Queue, Counter (2 s / 31 s), Volume, Mute
- UI-Handling in `player.ui.js`
- Likes über IP gesteuert (einmal pro Song/IP), persistiert in `likes.json`
- Plays-API geplant (`plays.json` analog zu Likes)
- Kein Fehler in Console; stabile Kommunikation zwischen JS und PHP

## Technische Kernmodule
- **app.js** – Router, globaler Event-Hub, Like-Handler
- **store.js** – Globaler Datenspeicher für Songs, Artists, Releases
- **player.js** – Core-Logik: Queue, Counters, Volume, API
- **player.ui.js** – UI-Binder für Meta, Slider, Haken, Volume
- **PHP-APIs:** `/gio/api/likes.toggle.php`, `/gio/api/likes.get.php`  
  Speichern IP-basiert in `/data/likes.json` außerhalb von `/gio`

## Struktur
```
/gio/
  /assets/
    /js/
      app.js
      player.js
      player.ui.js
      store.js
  /api/
    likes.toggle.php
    likes.get.php
  /views/
    home.php
    artist.php
  /shell/
    header.php
    sidebar.php
    footer.php
    player.php
  /data/  (optional, alternativ /data außerhalb gio)
    likes.json
```
Host root: `www.grabiton.com/gio/`

## Nächste Schritte
1. Plays-Tracking mit `plays.json` (gleiche Logik wie Likes)
2. Migration zu MySQL (Tabellen: artists, songs, releases, likes, plays)
3. Player-UI erweitern (Prev/Next, Seekbar, Keyboard Shortcuts)
4. Section „Neu“ auf Home mit Sortierung nach Aktualität
5. Admin-Backend für CRUD (Artists/Songs)

## Zustand
System stabil, alle APIs antworten korrekt, Console fehlerfrei.
