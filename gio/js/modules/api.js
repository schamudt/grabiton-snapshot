// API-Wrapper. Hier später Token, CSRF, Retry.
const base = '/gio/api/v1';

async function get(url){
  const r = await fetch(url, {headers:{'Accept':'application/json'}});
  return r.json();
}

export const api = {
  async home(){ return get(`${base}/home.php`); },
  async search(q){ if (!q) return {ok:true,data:{songs:[],artists:[],releases:[]}}; return get(`${base}/search.php?q=${encodeURIComponent(q)}`); }
};
