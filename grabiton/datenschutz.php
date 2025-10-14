<?php
// Datenschutzerklärung – GrabItOn (DE/EU)
?><!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Datenschutz – GrabItOn</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{ --bg:#030d2f; --glass:rgba(10,15,40,.45); --stroke:rgba(255,255,255,.12); --text:#e8eefc; --dim:#b7c1e6; }
    html,body{margin:0;padding:0;background:var(--bg);color:var(--text);font:400 16px/1.6 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;}
    a{color:#9fb6ff;text-decoration:none} a:hover{text-decoration:underline}

    /* Layout: Button links, Box rechts */
    .row{max-width:980px;margin:30px auto;padding:0 8px;display:flex;gap:14px;align-items:flex-start}
    @media (max-width:720px){ .row{flex-direction:column;gap:10px} }

    /* Zurück-Button: volle Linie, gleiche Glasoptik wie Box */
    .backbtn{
      display:inline-flex;align-items:center;gap:10px;
      padding:10px 14px;border:1px solid var(--stroke);border-radius:10px;
      background:rgba(255,255,255,.06);backdrop-filter:blur(12px);
      color:var(--text);text-decoration:none;white-space:nowrap;
      transition:background .15s ease,border-color .15s ease,transform .12s ease;
      align-self:flex-start;
    }
    .backbtn:hover{background:rgba(255,255,255,.10);border-color:rgba(255,255,255,.2);text-decoration:none}
    .backbtn:active{transform:translateY(1px)}
    .backbtn svg{width:16px;height:16px;fill:currentColor;opacity:.9}

    .wrap{flex:1;min-width:0;padding:24px;border:1px solid var(--stroke);background:var(--glass);backdrop-filter:blur(16px);border-radius:14px}
    h1{font-size:28px;margin:0 0 12px} h2{font-size:20px;margin:24px 0 8px}
    .dim{color:var(--dim)} ul{margin:6px 0 12px 20px} li{margin:6px 0}
    .box{padding:12px;border:1px solid var(--stroke);border-radius:10px;margin:10px 0;background:rgba(255,255,255,.02)}
    code{background:rgba(255,255,255,.07);padding:.1em .35em;border-radius:6px}
  </style>
</head>
<body>

  <div class="row">
    <!-- führt immer auf die Startseite -->
    <a class="backbtn" href="/grabiton/" aria-label="Zurück">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
      Zurück
    </a>

    <main class="wrap">
      <h1>Datenschutzerklärung</h1>
      <p class="dim">Stand: <?php echo date('d.m.Y'); ?></p>

      <h2>1. Hosting</h2>
      <p>Diese Website wird bei <strong>IONOS SE</strong>, Elgendorfer Str. 57, 56410 Montabaur, gehostet. Es besteht ein Auftragsverarbeitungsvertrag (Art. 28 DSGVO). Der Hoster verarbeitet Server-Logdaten (siehe unten) zur Bereitstellung und Sicherheit der Dienste.</p>

      <h2>2. Zugriffsdaten / Server-Logs</h2>
      <p>Beim Aufruf unserer Seiten werden durch den Browser automatisch Daten übertragen und kurzfristig in Logfiles gespeichert (z. B. IP-Adresse, Datum/Uhrzeit, URL, Referrer, User-Agent, Statuscode).</p>
      <p><em>Zweck/Interesse:</em> Betrieb, Sicherheit (Abwehr von Angriffen), Fehleranalyse (Art. 6 Abs. 1 lit. f DSGVO). <em>Speicherdauer:</em> i. d. R. 7–14 Tage (hosterabhängig), danach Löschung/Anonymisierung.</p>

      <h2>3. Abspielen von Musik / Player</h2>
      <p>Beim Streamen werden Audiodateien vom Webserver ausgeliefert. Es erfolgt keine Profilbildung. Zur Sicherstellung der Funktion werden technische Anfragen verarbeitet (Zeitpunkt, Datei-URL, Statuscodes).</p>
      <p><em>Rechtsgrundlage:</em> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an Betrieb/Usability).</p>

      <h2>4. „Like“-System (einmaliger Klick pro Song ohne Login)</h2>
      <div class="box">
        <p>Sie können Songs mit einem „Like“ (Pokal-Symbol) markieren. Um Mehrfach-Likes zu verhindern, speichern wir <strong>nicht</strong> Ihre IP im Klartext, sondern ausschließlich einen <strong>nicht rückrechenbaren Hash</strong> Ihrer IP mit internem Salt (<code>sha256(SALT + IP)</code>). Der Hash ist einer Person ohne Zusatzinformationen <strong>nicht zuzuordnen</strong>.</p>
        <ul>
          <li><em>Daten:</em> Song-ID, IP-Hash (SHA-256), Zeitstempel</li>
          <li><em>Zweck:</em> Zählen von Likes, Verhinderung von Mehrfachabgaben</li>
          <li><em>Rechtsgrundlage:</em> Art. 6 Abs. 1 lit. f DSGVO (faire Interaktion &amp; Missbrauchsvermeidung)</li>
          <li><em>Speicherdauer:</em> Bis zur Löschung des Songs oder technischer Bereinigung (z. B. nach 12 Monaten Inaktivität)</li>
          <li><em>Empfänger:</em> Hoster (technische Bereitstellung); keine Drittlandübermittlung</li>
        </ul>
        <p><em>Widerspruch:</em> Nach Art. 21 DSGVO können Sie widersprechen. Da der Hash nicht direkt zuordenbar ist, benötigen wir zur Bearbeitung Hinweise (z. B. Datum/Uhrzeit, Internet-Zugangsanbieter), um den Hash in der DB zu finden und zu löschen.</p>
      </div>

      <h2>5. Cookies, Local/Session-Storage</h2>
      <p>Wir setzen derzeit <strong>keine</strong> Tracking-/Marketing-Cookies und <strong>kein</strong> externes Tracking ein. Für die Funktionalität nutzen wir ggf. Browser-seitige Speicher (z. B. <code>sessionStorage</code>) zur Tab-Ansicht (z. B. Player-Status). Diese Daten verlassen Ihren Browser nicht.</p>
      <p><em>Rechtsgrundlage:</em> § 25 Abs. 2 TTDSG (unbedingt erforderlich) i. V. m. Art. 6 Abs. 1 lit. f DSGVO.</p>

      <h2>6. Kontakt</h2>
      <p>Bei Kontakt per E-Mail verarbeiten wir Ihre Mitteilung zur Beantwortung.</p>
      <p><em>Rechtsgrundlage:</em> Art. 6 Abs. 1 lit. b DSGVO (vorvertragliche Kommunikation) oder lit. f (allgemeine Anfragen). <em>Speicherdauer:</em> gemäß gesetzlichen Aufbewahrungen bzw. bis Abschluss der Bearbeitung.</p>

      <h2>7. Administration / Login-Bereich</h2>
      <p>Der interne Admin-Bereich ist passwortgeschützt. Dort können technisch notwendige Cookies/Session-IDs eingesetzt werden (z. B. für Authentifizierung). Zugriff nur für Berechtigte.</p>
      <p><em>Rechtsgrundlage:</em> Art. 6 Abs. 1 lit. f DSGVO (IT-Sicherheit, Zugriffsschutz).</p>

      <h2>8. Rechtsgrundlagen im Überblick</h2>
      <ul>
        <li>Art. 6 Abs. 1 lit. a DSGVO – Einwilligung (falls erforderlich)</li>
        <li>Art. 6 Abs. 1 lit. b DSGVO – Vertrag/Anbahnung</li>
        <li>Art. 6 Abs. 1 lit. f DSGVO – berechtigte Interessen (Betrieb, Sicherheit, Usability, Like-System)</li>
      </ul>

      <h2>9. Empfänger</h2>
      <p>Technische Dienstleister (Hosting/Administration) im Rahmen der Auftragsverarbeitung. Keine Drittlandübermittlung.</p>

      <h2>10. Speicherdauer</h2>
      <p>Personenbezogene Daten werden nur solange verarbeitet, wie erforderlich. Logdaten: i. d. R. 7–14 Tage. Like-Hashes: bis zur Löschung des Songs oder technischer Bereinigung. Gesetzliche Aufbewahrungen bleiben unberührt.</p>

      <h2>11. Ihre Rechte</h2>
      <ul>
        <li>Auskunft (Art. 15), Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung (Art. 18)</li>
        <li>Datenübertragbarkeit (Art. 20)</li>
        <li>Widerspruch (Art. 21) gegen Verarbeitungen auf Grundlage berechtigter Interessen</li>
        <li>Widerruf erteilter Einwilligungen (Art. 7 Abs. 3) mit Wirkung für die Zukunft</li>
      </ul>
      <p>Beschwerderecht bei einer Datenschutz-Aufsichtsbehörde.</p>

      <h2>12. Sicherheit</h2>
      <p>Wir treffen technische und organisatorische Maßnahmen (z. B. Zugriffsbeschränkung, TLS-Verschlüsselung), um Daten zu schützen.</p>

      <h2>13. Externe Links</h2>
      <p>Bei externen Links gelten die Datenschutzhinweise der jeweiligen Anbieter.</p>

      <h2>14. Änderungen dieser Erklärung</h2>
      <p>Wir passen diese Erklärung an, wenn Funktionen oder Rechtslagen sich ändern. Bitte regelmäßig prüfen.</p>

      <h2>15. Verantwortlicher</h2>
      <p>
        <strong>Marko Schmidt</strong><br>
        Gr. Diesdorfer Straße 251<br>
        39108 Magdeburg<br>
        E-Mail: <a href="mailto:contact@grabiton.com">contact@grabiton.com</a>
      </p>
    </main>
  </div>
</body>
</html>
