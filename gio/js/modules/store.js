// Zentraler Zustand, erweiterbar
export const store = {
  search: { q:'', results:null, loading:false },
  player: { queue:[], now:null, playing:false },
  auth: { token:null, role:'public' }
};
