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
};

/* Récupère l'utilisateur depuis localStorage (ou null) */
function rmGetUser() {
  try {
    const raw = localStorage.getItem('rm_user');
    if (!raw) return null;
    const u = JSON.parse(raw);
    return (u && u.role) ? u : null;
  } catch(e) { return null; }
}

/* Récupère le token */
function rmGetToken() {
  return localStorage.getItem('rm_token') || '';
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
  // Si 2 erreurs 401 en moins de 5 secondes → vraie session expirée
  if (_401Count >= 2) {
    clearTimeout(_401Timer);
    _401Count = 0;
    localStorage.removeItem('rm_token');
    localStorage.removeItem('rm_user');
    window.location.href = 'login.html';
    return;
  }
  clearTimeout(_401Timer);
  _401Timer = setTimeout(() => { _401Count = 0; }, 5000);
}

/* apiFetch — silencieux en mode démo, robuste en mode réel */
async function rmFetch(url, options = {}) {
  const token = rmGetToken();

  // Mode démo : token factice → réponse simulée, aucun appel réseau
  if (token.startsWith('demo_')) {
    return new Response(JSON.stringify({ data: [] }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    });
  }

  try {
    const res = await fetch(url, { ...options, headers: rmHeaders() });

    if (res.status === 401) {
      // Ne pas déconnecter sur un 401 isolé (ex: serveur redémarré temporairement)
      // Déconnecter seulement si erreurs répétées
      rmHandle401();
      throw new Error('Session expirée ou non autorisé');
    }

    return res;

  } catch(e) {
    // Erreur réseau (serveur down) → on propage sans déconnecter
    // L'écran appelant affiche une notification, pas de redirect
    throw e;
  }
}
