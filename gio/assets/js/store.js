// /gio/assets/js/store.js
// Globaler Store + Event-Bus

(() => {
  const LS_KEY = 'gio.store.v1';

  /** @typedef {{id:string,name:string,avatar?:string}} Artist */
  /** @typedef {{id:string,title:string,artistId:string,src:string,duration?:number,cover?:string,likes?:number}} Song */
  /** @typedef {{id:string,title:string,artistId:string,type:'single'|'ep'|'album',year?:number,cover?:string}} Release */

  const initial = {
    artists: [{ id: 'a_demo', name: 'Demo Artist' }],
    songs:   [{ id: 's_demo', title: 'Demo Track', artistId: 'a_demo', src: '/gio/assets/audio/demo.mp3', likes: 0 }],
    releases:[{ id: 'r_demo', title: 'Demo Release', artistId: 'a_demo', type: 'single', year: new Date().getFullYear() }]
  };

  function load(){
    try {
      const raw = localStorage.getItem(LS_KEY);
      if (!raw) return structuredClone(initial);
      const parsed = JSON.parse(raw);
      return { ...structuredClone(initial), ...parsed };
    } catch { return structuredClone(initial); }
  }
  function save(st){ try { localStorage.setItem(LS_KEY, JSON.stringify(st)); } catch {} }

  const subs = new Set();
  function notify(type, payload){ subs.forEach(fn => { try { fn(type, payload); } catch {} }); }

  const state = load();

  const api = {
    subscribe(fn){ subs.add(fn); return () => subs.delete(fn); },

    getState(){ return structuredClone(state); },
    getArtists(){ return structuredClone(state.artists); },
    getSongs(){ return structuredClone(state.songs); },
    getReleases(){ return structuredClone(state.releases); },
    getSongById(id){ return state.songs.find(s => s.id === id) || null; },
    getArtistById(id){ return state.artists.find(a => a.id === id) || null; },

    setArtists(list){ state.artists = Array.isArray(list)? list.slice():[]; save(state); notify('artists:set', state.artists); },
    setSongs(list){ state.songs = Array.isArray(list)? list.slice():[]; save(state); notify('songs:set', state.songs); },
    setReleases(list){ state.releases = Array.isArray(list)? list.slice():[]; save(state); notify('releases:set', state.releases); },

    upsertSong(song){
      const i = state.songs.findIndex(s => s.id === song.id);
      if (i >= 0) state.songs[i] = { ...state.songs[i], ...song };
      else state.songs.push(song);
      save(state); notify('songs:upsert', song);
    },
    likeSong(id, delta=1){
      const s = state.songs.find(x => x.id === id);
      if (!s) return;
      s.likes = Math.max(0, (s.likes||0) + delta);
      save(state); notify('songs:like', { id, likes: s.likes });
    }
  };

  window.gioStore = api;
  notify('store:ready', api.getState());
})();
