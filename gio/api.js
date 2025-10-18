// /gio/js/modules/api.js
// Leichter Fetch-Wrapper: get, post, qs. Wirft bei HTTP-Fehlern mit detail.

const DEFAULT_TIMEOUT_MS = 12000;

function withTimeout(promise, ms = DEFAULT_TIMEOUT_MS) {
  return new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error('timeout')), ms);
    promise.then(v => { clearTimeout(t); resolve(v); }, e => { clearTimeout(t); reject(e); });
  });
}

async function fetchJSON(url, opts = {}) {
  const res = await withTimeout(fetch(url, opts));
  const text = await res.text(); // robust gegen leere Antworten
  let data = null;
  if (text) {
    try { data = JSON.parse(text); } catch { /* non-JSON */ }
  }
  if (!res.ok) {
    const err = new Error('http_error');
    err.status = res.status;
    err.statusText = res.statusText;
    err.body = data ?? text;
    err.url = url;
    throw err;
  }
  return data ?? {};
}

export function qs(params = {}) {
  const u = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v === undefined || v === null) continue;
    u.append(k, String(v));
  }
  const s = u.toString();
  return s ? `?${s}` : '';
}

export async function get(url, params) {
  const full = params ? `${url}${qs(params)}` : url;
  return fetchJSON(full, {
    method: 'GET',
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin',
  });
}

export async function post(url, body = {}) {
  return fetchJSON(url, {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    credentials: 'same-origin',
  });
}

// Optionale Helfer für spezifische APIs
export const api = {
  homeFeed() { return get('/gio/api/v1/home/feed.php'); },
  search(params) { return get('/gio/api/v1/search.php', params); },
  likeToggle(song_id) { return post('/gio/api/v1/likes/like_song.php', { song_id }); },
  likeCounts(ids) { return get('/gio/api/v1/likes/like_song_count.php', { ids: ids.join(',') }); },
  playStart(song_id) { return post('/gio/api/v1/plays/start.php', { song_id }); },
  playMark(play_id, at) { return post('/gio/api/v1/plays/mark.php', { play_id, at }); },
};

export default { get, post, qs, api };
