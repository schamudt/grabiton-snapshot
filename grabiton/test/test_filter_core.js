// /grabiton/test/test_filter_core.js
// Zweck: Minimaler, ROBUSTER Filter für Tests – kein CSS-Touch, nur hidden toggeln.
// Nutzt Delegation + „gio:main:updated“, funktioniert statisch und mit SPA-Neuladen.
(function(){
  const slug = s => (s||'').toLowerCase();
  const group = t => { const c=(t||'').trim().charAt(0).toUpperCase(); return /[A-ZÄÖÜ]/.test(c) ? c : '#'; };

  function findCtx(root=document){
    const grid = root.querySelector('.artists-grid'); if (!grid) return null;
    let items = Array.from(grid.querySelectorAll('.artist-item'));
    if (!items.length) items = Array.from(grid.children).filter(n => n.nodeType === 1);
    const letters = root.querySelector('.letters');
    const letterBtns = letters ? Array.from(letters.querySelectorAll('.letter, [data-letter]')) : [];
    const search = root.querySelector('#artistSearch') || root.querySelector('.artists-search input[type="search"]');
    const emptyEl = root.querySelector('[data-empty], .artists-empty');
    return { grid, items, letterBtns, search, emptyEl };
  }

  function activeLetter(btns){
    if (!btns || !btns.length) return '';
    const a = btns.find(b => b.classList.contains('is-active') || b.classList.contains('active'));
    let L = (a?.dataset.letter || a?.textContent || '').trim().toUpperCase();
    if (L === 'ALLE' || L === 'ALL' || L === '*') L = '';
    if (L === '…') L = '#';
    return L;
  }

  function apply(ctx, log){
    if (!ctx) { log && log('apply: kein Kontext'); return; }
    const { items, letterBtns, search, emptyEl } = ctx;
    const L = activeLetter(letterBtns);
    const q = slug(search?.value || '');
    let shown = 0;

    for (const it of items){
      const name = it.querySelector('.artist-name')?.textContent || '';
      const code = it.querySelector('.artist-code')?.textContent || '';
      const keep = (q==='' ? true : (slug(name).includes(q) || slug(code).includes(q)))
                && (L==='' ? true : (group(name) === L));
      it.hidden = !keep;
      if (keep) shown++;
    }
    if (emptyEl) emptyEl.style.display = shown ? 'none' : '';
    log && log(`apply: L="${L||'ALLE'}", q="${q}", shown=${shown}`);
  }

  function mount({ logEl }={}){
    const logger = (...args)=>{ if(logEl){ logEl.textContent = `[${new Date().toLocaleTimeString()}] ${args.join(' | ')}\n` + logEl.textContent; } };

    const run = ()=> apply(findCtx(document), logger);

    // Delegation: Letters
    document.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.letters .letter, .letters [data-letter]');
      if (!btn) return;
      const ctx = findCtx(document); if (!ctx || !btn.closest('.letters')) { logger('letters: kein Kontext'); return; }
      ev.preventDefault();
      ctx.letterBtns.forEach(b => b.classList.remove('is-active','active'));
      btn.classList.add('is-active');
      apply(ctx, logger);
    }, true);

    // Delegation: Suche
    const onType = (ev) => {
      if (!ev.target.matches('#artistSearch, .artists-search input[type="search"]')) return;
      const ctx = findCtx(document); apply(ctx, logger);
    };
    document.addEventListener('input', onType, true);
    document.addEventListener('change', onType, true);

    // SPA-Event (falls vorhanden)
    document.addEventListener('gio:main:updated', run);

    // initial
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', run, { once:true });
    } else {
      run();
    }

    return { run };
  }

  window.GIO_TEST_FILTER = { mount };
})();
