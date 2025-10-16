// /gio/assets/js/search.js
// Header-Suche: URL steuern, Router triggern, Rendering in /views/search.php

(() => {
  const form = document.getElementById('gio-search');
  const input = document.getElementById('gio-search-input');
  if (!form || !input) return;

  // kleinen Debounce für Live-Suche
  let t = 0;
  function pushAndTrigger(q) {
    const url = q ? `/gio/search?q=${encodeURIComponent(q)}` : `/gio/search`;
    history.pushState({}, '', url);
    // Router von Kernel-1 hört auf popstate
    window.dispatchEvent(new PopStateEvent('popstate'));
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    pushAndTrigger(input.value.trim());
  });

  input.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => pushAndTrigger(input.value.trim()), 250);
  });
})();
