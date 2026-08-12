/**
 * RestauManager — API Client
 * Remplace le state local JS par de vrais appels à l'API Laravel
 *
 * Usage: Inclure ce fichier APRÈS restaurant-pos.html
 * Modifier BASE_URL selon votre serveur Laravel
 */

const API = {
    BASE_URL: 'http://localhost:8000/api',
    token: localStorage.getItem('rm_token') || localStorage.getItem('token') || null,

    // ── HTTP HELPERS ────────────────────────────────────────────
    async request(method, endpoint, body = null) {
        const opts = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(this.token ? { 'Authorization': `Bearer ${this.token}` } : {}),
            },
        };
        if (body) opts.body = JSON.stringify(body);

        const res = await fetch(this.BASE_URL + endpoint, opts);
        const data = await res.json();

        if (!res.ok) {
            const msg = data.error || data.message || 'Erreur serveur';
            throw new Error(msg);
        }
        return data;
    },

    get:    (ep)        => API.request('GET',    ep),
    post:   (ep, body)  => API.request('POST',   ep, body),
    put:    (ep, body)  => API.request('PUT',    ep, body),
    patch:  (ep, body)  => API.request('PATCH',  ep, body),
    delete: (ep)        => API.request('DELETE', ep),

    // ── AUTH ────────────────────────────────────────────────────
    async login(username, password, role) {
        const data = await this.post('/auth/login', { username, password, role });
        this.token = data.token;
        localStorage.setItem('rm_token', data.token);
        localStorage.setItem('rm_user',  JSON.stringify(data.user));
        return data;
    },

    async logout() {
        await this.post('/auth/logout');
        this.token = null;
        localStorage.removeItem('rm_token');
        localStorage.removeItem('rm_user');
    },

    getCurrentUser() {
        const u = localStorage.getItem('rm_user');
        return u ? JSON.parse(u) : null;
    },

    // ── MENU ─────────────────────────────────────────────────────
    getMenu:         ()     => API.get('/menu'),
    getMenuBySection:(code) => API.get(`/menu/${code}`),

    // ── TABLES ───────────────────────────────────────────────────
    getTables:       (section) => API.get(`/tables${section ? '?section='+section : ''}`),
    getTable:        (id)      => API.get(`/tables/${id}`),
    createTable:     (data)    => API.post('/tables', data),
    updateTableStatus:(id, status) => API.patch(`/tables/${id}/status`, { status }),

    // ── ORDERS ───────────────────────────────────────────────────
    openOrder:   (tableId, personsCount, notes) =>
        API.post('/orders', { table_id: tableId, persons_count: personsCount, notes }),

    getOrder:    (id) => API.get(`/orders/${id}`),

    addItem:     (orderId, personIndex, menuItemId, qty = 1) =>
        API.post(`/orders/${orderId}/items`, {
            person_index: personIndex,
            menu_item_id: menuItemId,
            quantity:     qty,
        }),

    offerItem:   (orderId, personIndex, menuItemId, reason = '') =>
        API.post(`/orders/${orderId}/offer`, {
            person_index: personIndex,
            menu_item_id: menuItemId,
            free_reason:  reason,
        }),

    updateItem:  (orderId, itemId, changes) =>
        API.put(`/orders/${orderId}/items/${itemId}`, changes),

    removeItem:  (orderId, itemId) =>
        API.delete(`/orders/${orderId}/items/${itemId}`),

    applyDiscount: (orderId, itemId, discountPercent) =>
        API.put(`/orders/${orderId}/items/${itemId}`, { discount_percent: discountPercent }),

    validateOrder: (orderId) => API.patch(`/orders/${orderId}/validate`),
    cancelOrder:   (orderId) => API.patch(`/orders/${orderId}/cancel`),

    // ── TICKETS ──────────────────────────────────────────────────
    generateTicket: (orderId)           => API.post(`/orders/${orderId}/ticket`),
    payTicket:      (ticketId, amount, method) =>
        API.post(`/tickets/${ticketId}/pay`, { paid_amount: amount, payment_method: method }),
    getTicket:      (ticketId) => API.get(`/tickets/${ticketId}`),
    listTickets:    (date)     => API.get(`/tickets${date ? '?date='+date : ''}`),
};


// ══════════════════════════════════════════════════════════════
// INTEGRATION avec le HTML restaurant-pos.html
// Remplacer les fonctions locales par des appels API
// ══════════════════════════════════════════════════════════════

/**
 * Remplace doLogin() — appel API réel
 */
async function doLoginAPI() {
    const username = document.getElementById('login-user').value.trim();
    const password = document.getElementById('login-pass').value.trim();
    if (!username || !password) { notify('Remplissez tous les champs','error'); return; }

    try {
        await API.login(username, password, state.role);
        document.getElementById('login-screen').style.display = 'none';
        document.getElementById('app').classList.add('visible');
        document.getElementById('user-label').textContent =
            (state.role === 'caissier' ? '💰 Caissier: ' : '🛎 Serveur: ') + username;

        // Charger le menu et les tables depuis l'API
        await loadMenuFromAPI(state.currentSection);
        await loadTablesFromAPI();
        notify(`Bienvenue ${username} !`, 'success');
    } catch (e) {
        notify(e.message, 'error');
    }
}

/**
 * Charger le menu d'une section depuis l'API
 */
async function loadMenuFromAPI(sectionCode) {
    try {
        const { data } = await API.getMenuBySection(sectionCode);
        // Remplacer MENUS[sectionCode] par les données de l'API
        MENUS[sectionCode] = data.categories.map(cat => ({
            cat:   cat.name,
            type:  cat.type,
            items: cat.items.map(i => ({
                id:    i.id,
                name:  i.name,
                price: i.price,
            })),
        }));
        renderCategoryList();
    } catch (e) {
        console.error('Erreur chargement menu:', e);
    }
}

/**
 * Charger les tables depuis l'API
 */
async function loadTablesFromAPI() {
    try {
        const { data } = await API.getTables(state.currentSection);
        // Convertir au format local
        state.tables = {};
        data.forEach(t => {
            state.tables[t.id] = {
                id:       t.id,
                num:      t.number,
                section:  t.section?.code || state.currentSection,
                persons:  t.active_order?.persons_count || 0,
                orders:   {},
                api_order_id: t.active_order?.id || null,
                status:   t.status,
            };
        });
        renderTables();
    } catch (e) {
        console.error('Erreur chargement tables:', e);
    }
}

/**
 * Ouvrir une commande via l'API
 */
async function createTableAPI() {
    const num    = document.getElementById('table-num-input').value.trim() || '?';
    const secEl  = document.getElementById('table-section-input');
    const sectionCode = secEl.value;

    // Récupérer l'ID de la section
    try {
        const { data: tables } = await API.getTables();
        // Trouver la table par numéro ou en créer une nouvelle
        // Pour simplifier: POST direct avec section_id
        // (nécessite de récupérer section_id depuis le code)
        notify('Fonctionnalité liée à l\'API — voir TableController::store', 'success');
        closeModal('new-table-modal');
    } catch(e) {
        notify(e.message, 'error');
    }
}

/**
 * Ajouter un article via l'API
 */
async function addItemToCurrentAPI(menuItemId, name, price) {
    if (!state.currentTableId) { notify('Ouvrez une table d\'abord', 'error'); return; }

    const table = state.tables[state.currentTableId];
    if (!table.api_order_id) { notify('Commande non liée à l\'API', 'error'); return; }

    try {
        const { order } = await API.addItem(table.api_order_id, state.currentPerson, menuItemId);
        // Mettre à jour l'état local depuis la réponse API
        syncOrderFromAPI(order);
        notify(`${name} ajouté pour Personne ${state.currentPerson + 1}`);
    } catch (e) {
        notify(e.message, 'error');
    }
}

/**
 * Synchroniser l'état local depuis une réponse API
 */
function syncOrderFromAPI(order) {
    const t = state.tables[state.currentTableId];
    if (!t) return;
    order.persons.forEach(p => {
        t.orders[p.index] = p.items.map(i => ({
            id:       i.id,
            name:     i.name,
            price:    i.unit_price,
            qty:      i.quantity,
            free:     i.is_free,
            discount: i.discount_percent > 0 ? i.discount_percent : null,
        }));
    });
    renderOrderItems();
}

// Exposer les fonctions API
window.API = API;
console.log('✅ RestauManager API Client chargé — Base URL:', API.BASE_URL);
