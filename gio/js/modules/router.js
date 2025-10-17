// Hash-Router: #/home, #/search?q=...
let _onChange = null;

function parseLocation(){
  const hash = location.hash || '#/home';
  const [path, qs=''] = hash.slice(1).split('?');
  const seg = path.split('/').filter(Boolean);
  const name = seg[0] || 'home';
  const query = Object.fromEntries(new URLSearchParams(qs));
  return { name, seg, query };
}

function navigateTo(hash){
  if (location.hash !== hash) location.hash = hash;
  else window.dispatchEvent(new HashChangeEvent('hashchange'));
}

function initRouter(cb){
  _onChange = cb;
  if (!location.hash) navigateTo('#/home');
  cb(parseLocation());
  window.addEventListener('hashchange', () => _onChange && _onChange(parseLocation()));
}

export const Router = { parseLocation, navigateTo, initRouter };
