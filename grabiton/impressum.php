<?php ?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Impressum – GrabItOn</title>
<style>
  :root{
    --bg:#030d2f; --glass:rgba(10,15,40,.45); --stroke:rgba(255,255,255,.12);
    --text:#e8eefc; --dim:#b7c1e6;
  }
  html,body{margin:0;background:var(--bg);color:var(--text);font:400 16px/1.6 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;}
  a{color:#9fb6ff;text-decoration:none} a:hover{text-decoration:underline}

  /* Layout: Button links, Box rechts – wie Datenschutz */
  .row{max-width:980px;margin:30px auto;padding:0 8px;display:flex;gap:14px;align-items:flex-start}
  @media (max-width:720px){ .row{flex-direction:column;gap:10px} }

  /* Back-Button mit VOLLEM Rahmen (kein dashed) */
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

  .wrap{flex:1;min-width:0;padding:24px;border:1px solid var(--stroke);
        background:var(--glass);backdrop-filter:blur(16px);border-radius:14px}
  h1{font-size:28px;margin:0 0 12px}
  .dim{color:var(--dim)}
</style>
</head>
<body>
<div class="row">
  <a class="backbtn" href="/grabiton/" aria-label="Zurück">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
    Zurück
  </a>

  <main class="wrap">
    <h1>Impressum</h1>
    <p><strong>Marko Schmidt</strong><br>
       Gr. Diesdorfer Straße 251<br>
       39108 Magdeburg<br>
       E-Mail: <a href="mailto:contact@grabiton.com">contact@grabiton.com</a></p>

    <p class="dim">Inhaltlich Verantwortlicher gem. § 18 Abs. 2 MStV: Marko Schmidt (Adresse wie oben)</p>

    <h2>Haftung für Inhalte</h2>
    <p>Als Diensteanbieter sind wir für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt.</p>

    <h2>Haftung für Links</h2>
    <p>Unser Angebot enthält Links zu externen Webseiten Dritter. Auf deren Inhalte haben wir keinen Einfluss; hierfür ist stets der jeweilige Anbieter verantwortlich. Bei Kenntnis von Rechtsverletzungen entfernen wir derartige Links umgehend.</p>

    <h2>Urheberrecht</h2>
    <p>Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem Urheberrecht. Beiträge Dritter sind als solche gekennzeichnet.</p>
  </main>
</div>
</body>
</html>
