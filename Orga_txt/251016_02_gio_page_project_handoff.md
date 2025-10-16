
# GIO Project — Technical Handoff

## Ziel des Projekts
GIO ist eine browserbasierte Musikplattform mit SPA-Light-Struktur. Ziel ist eine modulare, wartbare Webapp mit dynamischem Routing, globalem Datenstore, audiobasiertem Player, Like- und Play-Tracking sowie einer klaren Trennung zwischen UI- und Core-Logik.

---

## 1. Architekturübersicht
**Hosting:** IONOS (PHP 8.x, Apache)  
**Frontend:** Vanilla JavaScript (kein Framework)  
**Backend:** PHP + JSON-Dateien (Übergang zu MySQL geplant)  
**Struktur:**  
```
/gio/
  ├── assets/
  │   └── js/
  │       ├── app.js          → SPA Router + Likes
  │       ├── player.js       → Core Player (Queue, Counter, API)
  │       ├── player.ui.js    → UI für Player (Meta, Slider, Volume, 31s)
  │       └── store.js        → Globaler Song-/Artist-Store
  ├── api/
  │   ├── likes.toggle.php    → Toggle Like per IP
  │   └── likes.get.php       → Status & Count lesen
  ├── views/                  → Inhalte (home, artist, explore, releases)
  ├── shell/                  → Layout-Komponenten (header, sidebar, footer, player)
  ├── data/                   → optionale lokale Persistenz
  └── index.php               → Einstiegspunkt (lädt Shell + JS)
```

---

## 2. Aktueller Funktionsstand

### Routing (`app.js`)
- Lädt Views dynamisch über Fetch.
- Kein Seitenreload (SPA-Light).
- Steuerung über `data-route` Attribute.
- Synchronisiert Like-Status mit Player-Events.

### Player-Core (`player.js`)
- Steuert Queue, Play/Pause, Seek, Volume, Mute.
- Spielzeit-Counter mit 2s / 31s Events.
- `plays.start.php` und `plays.log.php` (Clientaufruf) aktiv.
- Notifies: `player:play`, `player:pause`, `player:time`, `counter:2s`, `counter:31s`.
- Unterstützt Auto-Next bei Song-Ende.

### Player-UI (`player.ui.js`)
- Verknüpft DOM mit Player-State.
- Aktualisiert Titel, Künstler, Cover, Zeiten.
- Zeigt Haken nach 31s.
- Kein Like-Handling (wird von `app.js` übernommen).

### Likes-System
- IP-basiert, pro Song einmalig.
- Speicherung in `/data/likes.json` (außerhalb `/gio/`).
- `likes.toggle.php` schreibt oder entfernt IP.
- `likes.get.php` liefert Count + ob User geliked hat.
- JS zeigt Status in Echtzeit (`is-liked` Klasse).

### Persistenzstruktur (`likes.json`)
```json
{
  "song123": {
    "count": 4,
    "ips": {
      "192.168.0.12": true,
      "192.168.0.33": true
    }
  }
}
```

---

## 3. APIs (Server)

### `/gio/api/likes.toggle.php`
POST `{ songId: string }`  
→ toggelt Like anhand IP, schreibt in `likes.json`  
Antwort:
```json
{ "ok": true, "songId": "song123", "liked": true, "count": 4 }
```

### `/gio/api/likes.get.php`
GET `?songId=song123`  
→ liest aktuellen Status + Count.

---

## 4. Empfohlene nächste Schritte
1. **plays.json / API**
   - Gleiche Struktur wie `likes.json`.
   - Schreiben bei `plays.start.php` und `plays.log.php`.
   - Client: Aufruf über Player bei 2s und 31s.

2. **Global Store Upgrade**
   - Automatisches Laden von Likes/Plays in `store.js`.
   - Reaktive Aktualisierung des UI bei Änderungen.

3. **MySQL Migration (Phase 2)**
   Tabellenentwurf:
   - `artists(id, name, cover)`
   - `songs(id, title, artist_id, src, created_at)`
   - `likes(id, song_id, ip, created_at)`
   - `plays(id, song_id, ip, started_at, logged31_at)`

4. **UI Erweiterungen**
   - Buttons Prev/Next im Player.
   - Seekbar klickbar.
   - „Neu“-Section mit Sortierung.
   - Adminpanel (CRUD Artists/Songs).

5. **Performance**
   - Lazy Load Coverbilder.
   - Cache-Control Header für Assets.
   - Minify JS/CSS bei Deployment.

---

## 5. Zustand zum Handoff
- Keine Console-Fehler.  
- Likes stabil, IP-basiert.  
- Counter (2s/31s) funktional.  
- Player läuft global synchron mit Views.  
- System bereit für plays.json-Integration oder DB-Umstieg.

---

**Letzte geprüfte Umgebung:**  
Domain: https://www.grabiton.com/gio/  
Datum: aktueller Projektstand Oktober 2025
