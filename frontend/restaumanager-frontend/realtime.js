/**
 * RestauManager — Synchronisation temps réel (SSE + Polling fallback)
 * =====================================================================
 * Usage :
 *   <script src="realtime.js"></script>  ← après api-client.js
 *
 *   // S'abonner à un événement
 *   RealtimeSync.on('order.ready',   (data) => { ... });
 *   RealtimeSync.on('order.created', (data) => { ... });
 *
 *   // Démarrer la connexion (appeler après DOMContentLoaded)
 *   RealtimeSync.start();
 *
 *   // Arrêter (optionnel, ex: logout)
 *   RealtimeSync.stop();
 *
 * Événements disponibles :
 *   order.created        → Nouvelle commande sur place
 *   order.item_added     → Article ajouté à une commande
 *   order.item_updated   → Quantité modifiée
 *   order.item_removed   → Article supprimé
 *   order.validated      → Commande envoyée en cuisine
 *   order.ready          → Commande prête (cuisine)
 *   order.cancelled      → Commande annulée
 *   emporter.created     → Nouvelle commande à emporter
 *   table.status_changed → Statut table modifié
 *   ticket.paid          → Paiement enregistré
 */

const RealtimeSync = (() => {

  // ── Configuration ───────────────────────────────────────────────────
  const BASE_URL       = (typeof API !== 'undefined' && API.BASE_URL)
                         ? API.BASE_URL
                         : 'http://localhost:8000/api';
  const POLL_INTERVAL  = 3000;   // ms entre chaque poll (fallback)
  const MAX_SSE_ERRORS = 5;      // tentatives avant de basculer en polling
  const RECONNECT_DELAY = 3000;  // ms avant reconnexion SSE
  const HEARTBEAT_TIMEOUT = 45000; // ms sans heartbeat → reconnexion

  // ── État interne ────────────────────────────────────────────────────
  let _handlers       = {};       // { 'event.type': [fn, fn, …] }
  let _evtSource      = null;     // EventSource actif
  let _pollTimer      = null;     // setInterval pour le polling
  let _reconnectTimer = null;     // setTimeout pour reconnexion SSE
  let _heartbeatTimer = null;     // setTimeout détection silence
  let _lastId         = 0;        // Dernier event ID reçu
  let _sseErrors      = 0;        // Compteur d'erreurs SSE consécutives
  let _mode           = 'sse';    // 'sse' | 'polling'
  let _running        = false;
  let _debugMode      = false;

  // ── Helpers ─────────────────────────────────────────────────────────

  function _log(...args) {
    if (_debugMode) console.log('[RealtimeSync]', ...args);
  }

  function _getToken() {
    return localStorage.getItem('rm_token') || '';
  }

  function _emit(eventType, payload) {
    _log('emit:', eventType, payload);
    const fns = _handlers[eventType] || [];
    const wildcards = _handlers['*'] || [];
    [...fns, ...wildcards].forEach(fn => {
      try { fn(payload, eventType); }
      catch (e) { console.error('[RealtimeSync] Handler error:', e); }
    });
  }

  // ── Réinitialisation du timer heartbeat ─────────────────────────────
  function _resetHeartbeat() {
    clearTimeout(_heartbeatTimer);
    _heartbeatTimer = setTimeout(() => {
      _log('Heartbeat timeout — reconnexion SSE');
      _reconnectSSE();
    }, HEARTBEAT_TIMEOUT);
  }

  // ══ MODE SSE ════════════════════════════════════════════════════════

  function _startSSE() {
    const token = _getToken();
    if (!token) {
      _log('Pas de token — passage en polling');
      _startPolling();
      return;
    }

    _mode = 'sse';
    const url = `${BASE_URL}/stream?token=${encodeURIComponent(token)}&last_id=${_lastId}`;
    _log('SSE connect →', url);

    // Fermer la connexion précédente proprement
    if (_evtSource) {
      _evtSource.close();
      _evtSource = null;
    }

    _evtSource = new EventSource(url);

    // ── Connexion établie ──────────────────────────────────────────
    _evtSource.onopen = () => {
      _log('SSE connected ✓');
      _sseErrors = 0;
      _resetHeartbeat();
      _emit('__connected', { mode: 'sse' });
    };

    // ── Erreur / déconnexion ───────────────────────────────────────
    _evtSource.onerror = (e) => {
      _sseErrors++;
      _log(`SSE error #${_sseErrors}`, e);
      clearTimeout(_heartbeatTimer);
      _evtSource.close();
      _evtSource = null;

      if (_sseErrors >= MAX_SSE_ERRORS) {
        _log('Trop d\'erreurs SSE → basculement polling');
        _startPolling();
      } else {
        _reconnectSSE();
      }
    };

    // ── Écouter TOUS les types d'événements métier ─────────────────
      const EVENTS = [
      'order.created', 'order.item_added', 'order.item_updated',
      'order.item_removed', 'order.validated', 'order.ready',
      'order.cancelled', 'emporter.created',
      'table.created', 'table.status_changed', 'ticket.paid',
      // ✅ Événements menu (ajoutés pour la synchro temps réel)
      'menu.item_created', 'menu.item_updated', 'menu.item_toggled',
      'menu.item_deleted', 'menu.category_created', 'menu.category_updated',
      'menu.category_deleted',
    ];

    EVENTS.forEach(evtType => {
      _evtSource.addEventListener(evtType, (e) => {
        _resetHeartbeat();
        if (e.lastEventId) _lastId = parseInt(e.lastEventId, 10) || _lastId;
        try {
          const payload = JSON.parse(e.data);
          _emit(evtType, payload);
        } catch (err) {
          console.error('[RealtimeSync] Parse error:', err, e.data);
        }
      });
    });

    // ── Heartbeat / commentaires serveur ──────────────────────────
    _evtSource.addEventListener('message', (e) => {
      _resetHeartbeat();
    });
  }

  function _reconnectSSE() {
    if (!_running) return;
    clearTimeout(_reconnectTimer);
    _log(`Reconnexion dans ${RECONNECT_DELAY}ms…`);
    _reconnectTimer = setTimeout(() => {
      if (_running && _mode === 'sse') _startSSE();
    }, RECONNECT_DELAY);
  }

  // ══ MODE POLLING (fallback) ══════════════════════════════════════════

  function _startPolling() {
    if (_pollTimer) clearInterval(_pollTimer);
    _mode = 'polling';
    _log('Polling mode activé — intervalle', POLL_INTERVAL, 'ms');
    _emit('__connected', { mode: 'polling' });

    // Premier poll immédiat pour récupérer le dernier ID
    _poll();
    _pollTimer = setInterval(_poll, POLL_INTERVAL);
  }

  async function _poll() {
    // Ne pas requêter si l'onglet est caché (économie de ressources)
    if (document.visibilityState === 'hidden') return;

    const token = _getToken();
    if (!token) return;

    try {
      const url = `${BASE_URL}/events/latest?last_id=${_lastId}`;
      const res = await fetch(url, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
        },
      });

      if (!res.ok) {
        _log('Poll error HTTP', res.status);
        return;
      }

      const data = await res.json();
      const events = data.events || [];

      if (events.length > 0) {
        _log(`Poll: ${events.length} événement(s) reçu(s)`);
      }

      events.forEach(evt => {
        _lastId = Math.max(_lastId, evt.id);
        _emit(evt.event_type, evt.payload || {});
      });

      // Mise à jour du dernier ID même sans événements
      if (data.last_id && data.last_id > _lastId) {
        _lastId = data.last_id;
      }

    } catch (err) {
      _log('Poll fetch error:', err.message);
    }
  }

  // ── Reprise SSE quand l'onglet redevient visible ─────────────────────
  document.addEventListener('visibilitychange', () => {
    if (!_running) return;
    if (document.visibilityState === 'visible') {
      _log('Onglet redevenu visible');
      if (_mode === 'sse' && (!_evtSource || _evtSource.readyState === EventSource.CLOSED)) {
        _reconnectSSE();
      } else if (_mode === 'polling') {
        // Poll immédiat pour rattraper les événements manqués
        _poll();
      }
    }
  });

  // ══ API PUBLIQUE ════════════════════════════════════════════════════

  return {

    /**
     * S'abonner à un type d'événement.
     * Utiliser '*' pour recevoir tous les événements.
     *
     * @param {string}   eventType  ex: 'order.ready' | '*'
     * @param {Function} callback   fn(payload, eventType)
     * @returns {Function} Désabonnement : const unsub = RealtimeSync.on(...); unsub();
     */
    on(eventType, callback) {
      if (!_handlers[eventType]) _handlers[eventType] = [];
      _handlers[eventType].push(callback);

      // Retourner une fonction de désabonnement
      return () => {
        _handlers[eventType] = (_handlers[eventType] || [])
          .filter(fn => fn !== callback);
      };
    },

    /**
     * Démarrer la synchronisation.
     * À appeler après DOMContentLoaded, une fois l'utilisateur connecté.
     *
     * @param {Object} options
     * @param {boolean} [options.debug=false]    Activer les logs console
     * @param {boolean} [options.polling=false]  Forcer le mode polling
     * @param {number}  [options.lastId=0]       Reprendre depuis un ID donné
     */
    start(options = {}) {
      if (_running) return;
      _running   = true;
      _debugMode = options.debug || false;
      _lastId    = options.lastId || 0;

      _log('Démarrage…');

      // SSE nécessite EventSource (supporté partout sauf très vieux IE)
      const forcePolling = options.polling || !window.EventSource;

      if (forcePolling) {
        _startPolling();
      } else {
        _startSSE();
      }
    },

    /**
     * Arrêter proprement (ex: lors d'un logout).
     */
    stop() {
      _running = false;
      clearTimeout(_reconnectTimer);
      clearTimeout(_heartbeatTimer);
      clearInterval(_pollTimer);
      if (_evtSource) { _evtSource.close(); _evtSource = null; }
      _handlers = {};
      _log('Arrêté.');
    },

    /**
     * État courant de la connexion.
     * @returns {{ mode: string, lastId: number, running: boolean }}
     */
    status() {
      return {
        mode:    _mode,
        lastId:  _lastId,
        running: _running,
        sseState: _evtSource ? _evtSource.readyState : -1,
      };
    },

    /**
     * Active/désactive les logs console.
     */
    debug(enabled = true) {
      _debugMode = enabled;
    },
  };

})();
