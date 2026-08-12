/* ═══════════════════════════════════════════════════════
   RestauManager — Auth helper partagé
   ═══════════════════════════════════════════════════════ */

const RM_PAGES = {
  admin:           'admin-dashboard.html',
  caissier_restau: 'restaurant-pos.html',
  caissier_caffet: 'restaurant-pos.html',
  serveur_restau:  'serveur.html',
  serveur_caffet:  'serveur.html',
  reception:       'reception.html',
  cuisiner:        'cuisine.html',
};

/* Récupère l'utilisateur depuis localStorage (ou null) */
function rmGetUser() {
  try {
    const raw = localStorage.getItem('rm_user') || localStorage.getItem('user');
    if (!raw) return null;
    const u = JSON.parse(raw);
    return (u && u.role) ? u : null;
  } catch(e) { return null; }
}

/* Récupère le token */
function rmGetToken() {
  return localStorage.getItem('token') || localStorage.getItem('rm_token') || '';
}

/* Vérifie la session — redirige vers login si absente */
function rmRequireAuth(expectedRole) {
  const token = rmGetToken();
  const user  = rmGetUser();
  if (!token || !user) {
    window.location.href = 'login.html';
    return null;
  }
  if (expectedRole && user.role !== expectedRole) {
    window.location.href = RM_PAGES[user.role] || 'login.html';
    return null;
  }
  return user;
}

/* Applique les infos utilisateur dans la sidebar */
function rmApplyUserInfo(user, nameId, avatarId, roleId) {
  const name = user.name || user.username || 'Utilisateur';
  if (nameId   && document.getElementById(nameId))   document.getElementById(nameId).textContent   = name;
  if (avatarId && document.getElementById(avatarId)) document.getElementById(avatarId).textContent = name[0].toUpperCase();
  if (roleId   && document.getElementById(roleId))   document.getElementById(roleId).textContent   = user.role || '';
}

/* Déconnexion */
function rmLogout() {
  if (confirm('Se déconnecter ?')) {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('rm_token');
    localStorage.removeItem('rm_user');
    window.location.href = 'login.html';
  }
}

/* Headers API */
function rmHeaders() {
  return {
    'Content-Type':  'application/json',
    'Accept':        'application/json',
    'Authorization': 'Bearer ' + rmGetToken(),
  };
}

/* ── Gestion des erreurs 401 avec délai anti-boucle ─────────────── */
let _401Count = 0;
let _401Timer = null;

function rmHandle401() {
  _401Count++;
  if (_401Count >= 2) {
    clearTimeout(_401Timer);
    _401Count = 0;
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('rm_token');
    localStorage.removeItem('rm_user');
    window.location.href = 'login.html';
    return;
  }
  clearTimeout(_401Timer);
  _401Timer = setTimeout(() => { _401Count = 0; }, 5000);
}

/* apiFetch — robuste en mode réel */
async function rmFetch(url, options = {}) {
  try {
    const res = await fetch(url, { ...options, headers: rmHeaders() });

    if (res.status === 401) {
      rmHandle401();
      throw new Error('Session expirée ou non autorisé');
    }

    return res;

  } catch(e) {
    throw e;
  }
}