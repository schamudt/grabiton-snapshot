// Hash-Router: #/home, #/search?q=...
let _cb = null;

export function parseLocation(){
  const hash = location.hash || '#/home';
  const [path, qs=''] = hash.slice(1).split('?'); // remove '#'
  const seg = path.split('/').filter(Boolean);
  const name = seg[0] || 'home';
  const query = Object.fromEntries(new URLSearchParams(qs));
  return { name, seg, query };
}

export function navigateTo(hash){ if (location.hash !== hash) location.hash = hash; else window.dispatchEvent(new HashChangeEvent('hashchange')); }

export function initRouter(cb){ _cb = cb; if (!location.hash) navigateTo('#/home'); cb(parseLocation()); }

export function onRouteChange(cb){
  window.addEventListener('hashchange', () => cb(parseLocation()));
}
