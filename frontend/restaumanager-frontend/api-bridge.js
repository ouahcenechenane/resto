/**
 * RestauManager — API Bridge
 * ============================
 * Remplace les données demo hardcodées par de vrais appels à l'API Laravel.
 * Fonctionne en monkey-patching les variables/fonctions après leur définition.
 *
 * Inclure APRÈS auth.js et AVANT </body> sur chaque page.
 * Nécessite api-client.js (pour API.BASE_URL).
 */

const BASE_URL = (typeof API !== 'undefined') ? API.BASE_URL : 'http://localhost:8000/api';

/* ── Helper fetch authentifié ─────────────────────────────── */
async function apiBridge(method, endpoint, body = null) {
  const token = localStorage.getItem('rm_token');
  if (!token) return null;

  try {
    const opts = {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    };
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(BASE_URL + endpoint, opts);
    if (!res.ok) return null;
    return await res.json();
  } catch (e) {
    console.warn('[API Bridge]', endpoint, e.message);
    return null;
  }
}

/* ── Détecter la page courante ────────────────────────────── */
const PAGE = (() => {
  const p = location.pathname.split('/').pop();
  if (p.includes('cuisine'))      return 'cuisine';
  if (p.includes('serveur'))      return 'serveur';
  if (p.includes('restaurant-pos') || p.includes('pos')) return 'pos';
  if (p.includes('reception'))    return 'reception';
  if (p.includes('admin'))        return 'admin';
  return 'unknown';
})();

console.log(`[API Bridge] Page: ${PAGE}`);

/* ════════════════════════════════════════════════════════════
   PAGE CUISINE
   ════════════════════════════════════════════════════════════ */
if (PAGE === 'cuisine') {

  /* Convertir commande API → format tablesData */
  function apiOrderToTable(order) {
    const ordersMap = {};
    (order.persons || []).forEach(p => {
      ordersMap[p.person_index - 1] = (p.items || []).map(i => ({
        name:     i.item_name,
        price:    parseFloat(i.unit_price),
        qty:      i.quantity,
        free:     i.is_free,
        returned: false,
        note:     i.note || '',
      }));
    });
    return {
      id:          order.id,           // ID réel (number)
      num:         order.table?.number || order.order_number || '?',
      section:     order.table?.section?.code || 'emporter',
      persons:     order.persons_count,
      validatedAt: new Date(order.updated_at || order.created_at),
      status:      order.status,
      orders:      ordersMap,
      type:        order.type || 'sur_place',
      client_name: order.client_name || null,
      order_number:order.order_number || null,
    };
  }

  /* Charger les commandes cuisine depuis l'API */
  async function loadKitchenOrders() {
    const data = await apiBridge('GET', '/cuisine/orders');
    if (!data) return;
    tablesData = data.map(apiOrderToTable);
    if (typeof render === 'function') render();
  }

  /* Override markDone → appel API PATCH /cuisine/{id}/ready */
  window.addEventListener('load', () => {
    /* Sauvegarder l'original */
    const _markDone = window.markDone;
    window.markDone = async function(tableId) {
      /* tableId peut être l'ID réel (number) ou l'ancien format string */
      const order = tablesData?.find(t => String(t.id) === String(tableId));
      if (order && !isNaN(order.id)) {
        /* Appel API */
        await apiBridge('PATCH', `/cuisine/${order.id}/ready`);
      }
      /* Mettre à jour l'état local immédiatement (UI réactive) */
      if (tablesData) {
        tablesData = tablesData.filter(t => String(t.id) !== String(tableId));
      }
      if (typeof render === 'function') render();
      if (typeof notify === 'function') notify('✓ Commande marquée prête', 'ok');
    };

    /* Override render pour toujours lire depuis l'API */
    const _render = window.render;
    window.render = async function() {
      await loadKitchenOrders();
    };

    /* Chargement initial */
    loadKitchenOrders();
  });
}

/* ════════════════════════════════════════════════════════════
   PAGE ADMIN DASHBOARD
   ════════════════════════════════════════════════════════════ */
if (PAGE === 'admin') {

  /* Convertir commande API → format LIVE_ORDERS */
  function apiOrderToLive(order) {
    const items = [];
    (order.persons || []).forEach(p => {
      (p.items || []).forEach(i => items.push(i.item_name));
    });
    const h = new Date(order.opened_at || order.created_at);
    return {
      id:      order.id,
      table:   order.table?.number || order.order_number || '?',
      section: order.table?.section?.code || order.type || 'emporter',
      server:  order.user?.name || '—',
      items:   items.slice(0, 3).join(', ') || '—',
      status:  { open:'pending', validated:'cooking', ready:'ready', billed:'served', paid:'served' }[order.status] || 'pending',
      sentAt:  h.getHours() + ':' + String(h.getMinutes()).padStart(2,'0'),
      note:    order.notes || '',
      elapsed: 0,
      order_id: order.id,
    };
  }

  /* Convertir table API → format LIVE_TABLES */
  function apiTableToLive(table) {
    return {
      id:      table.id,
      num:     table.number,
      section: table.section?.code || 'salle',
      status:  table.status,
      server:  table.active_order ? '—' : null,
    };
  }

  /* Convertir ticket API → format TICKETS */
  function apiTicketToLocal(ticket) {
    const snap = ticket.snapshot || {};
    const h = new Date(ticket.printed_at);
    return {
      id:      ticket.id,
      num:     ticket.ticket_number || `#${String(ticket.id).padStart(4,'0')}`,
      table:   ticket.order?.table?.number || '—',
      section: ticket.order?.table?.section?.name || 'À emporter',
      persons: snap.persons_count || 1,
      amount:  parseFloat(ticket.total_amount),
      paid:    parseFloat(ticket.paid_amount || 0),
      method:  ticket.payment_method || 'cash',
      hour:    h.getHours() + 'h' + String(h.getMinutes()).padStart(2,'0'),
      status:  ticket.status,
    };
  }

  async function loadAdminData() {
    /* Charger tables */
    const tablesRes = await apiBridge('GET', '/tables');
    if (tablesRes?.data) {
      LIVE_TABLES = tablesRes.data.map(apiTableToLive);
    }

    /* Charger commandes actives */
    const ordersRes = await apiBridge('GET', '/orders?status=validated');
    if (ordersRes?.data) {
      LIVE_ORDERS = ordersRes.data.map(apiOrderToLive);
    }

    /* Charger tickets du jour */
    const ticketsRes = await apiBridge('GET', '/tickets');
    if (ticketsRes?.data) {
      TICKETS = ticketsRes.data.map(apiTicketToLocal);
    }

    /* Recharger le dashboard */
    if (typeof renderDashboard === 'function')   renderDashboard();
    if (typeof renderLiveTables === 'function')  renderLiveTables();
    if (typeof renderLiveOrders === 'function')  renderLiveOrders();
    if (typeof renderTickets === 'function')     renderTickets();
    if (typeof updateOrdersKpis === 'function')  updateOrdersKpis();

    /* Badges */
    const badgeTables = document.getElementById('badge-tables');
    const badgeOrders = document.getElementById('badge-orders');
    if (badgeTables) badgeTables.textContent = (LIVE_TABLES || []).filter(t => t.status === 'occupied').length;
    if (badgeOrders) badgeOrders.textContent = (LIVE_ORDERS || []).filter(o => o.status !== 'served').length;
  }

  async function loadAdminStats() {
    const stats = await apiBridge('GET', '/admin/stats/dashboard');
    if (!stats) return;
    const el = id => document.getElementById(id);
    const fmt = n => new Intl.NumberFormat('fr-DZ').format(Math.round(n || 0)) + ' DA';

    if (el('stat-today-ca'))  el('stat-today-ca').textContent  = fmt(stats.today?.revenue);
    if (el('stat-today-cnt')) el('stat-today-cnt').textContent = stats.today?.orders || 0;
    if (el('stat-week'))      el('stat-week').textContent      = fmt(stats.week?.revenue);
    if (el('stat-month'))     el('stat-month').textContent     = fmt(stats.month?.revenue);
    if (el('stat-free'))      el('stat-free').textContent      = fmt(stats.today?.free_total);
    if (el('stat-disc'))      el('stat-disc').textContent      = fmt(stats.today?.discount_total);
    if (el('stat-free-count'))     el('stat-free-count').textContent     = stats.today?.free_count || 0;
    if (el('stat-returned-count')) el('stat-returned-count').textContent = stats.today?.returned_count || 0;
  }

  window.addEventListener('load', () => {
    loadAdminData();
    loadAdminStats();

    /* Rafraîchir toutes les 30 secondes */
    setInterval(loadAdminData, 30000);

    /* Écouter table.created SSE → recharger les tables en direct */
    if (typeof RealtimeSync !== 'undefined') {
      RealtimeSync.on('table.created', async (data) => {
        await loadAdminData();
        if (typeof notify === 'function') notify(`🪑 Table ${data.number || ''} ajoutée`, 'info');
      });

      /* order.created : recharger commandes + stats */
      RealtimeSync.on('order.created', async (data) => {
        const ordersRes = await apiBridge('GET', '/orders?status=validated');
        if (ordersRes?.data) {
          LIVE_ORDERS = ordersRes.data.map(apiOrderToLive);
          if (typeof renderLiveOrders === 'function') renderLiveOrders();
        }
        const badgeOrders = document.getElementById('badge-orders');
        if (badgeOrders) badgeOrders.textContent =
          (LIVE_ORDERS || []).filter(o => o.status !== 'served').length;
      });
    }
  });
}

/* ════════════════════════════════════════════════════════════
   PAGE SERVEUR
   ════════════════════════════════════════════════════════════ */
if (PAGE === 'serveur') {

  /* Convertir table API → format S.tables */
  function apiTableToState(table) {
    const activeOrder = table.active_order;
    const orders = {};
    const persons = activeOrder?.persons_count || 2;
    for (let p = 0; p < persons; p++) orders[p] = [];
    return {
      id:          table.id,
      num:         table.number,
      section:     table.section?.code || 'salle',
      persons:     persons,
      orders:      orders,
      note:        '',
      ownerId:     activeOrder ? (activeOrder.user_id || null) : null,
      ownerName:   null,
      val:         activeOrder?.status === 'validated' ? new Date() : null,
      api_order_id: activeOrder?.id || null,
      status:      table.status,
    };
  }

  async function loadServeurTables(section) {
    const sec = section || (S?.section) || 'salle';
    const data = await apiBridge('GET', `/tables?section=${sec}`);

    // Si l'API échoue, on garde les données démo hardcodées — pas de retour silencieux
    if (!data?.data) {
      console.info('[API Bridge] Tables: mode hors-ligne, données démo conservées');
      if (typeof renderTblGrid === 'function')     renderTblGrid();
      if (typeof renderOrderPanel === 'function')  renderOrderPanel();
      return;
    }

    data.data.forEach(t => {
      const key = `${t.section?.code || 'salle'}_${t.number}`;
      if (S?.tables) {
        S.tables[key] = apiTableToState(t);
      }
    });

    if (typeof renderTblChips === 'function')    renderTblChips();
    if (typeof renderTblGrid === 'function')     renderTblGrid();
    if (typeof renderOrderPanel === 'function')  renderOrderPanel();
  }

  async function loadServeurMenu(section) {
    const sec = section || (S?.section) || 'salle';
    const data = await apiBridge('GET', `/menu/${sec}`);

    // Si l'API échoue, garder le menu démo hardcodé et forcer le rendu
    if (!data?.data?.categories) {
      console.info('[API Bridge] Menu: mode hors-ligne, menu démo conservé');
      if (typeof renderCatPills === 'function') renderCatPills();
      if (typeof renderProds === 'function')    renderProds();
      return;
    }

    /* Convertir au format MENUS[section] */
    const cats = data.data.categories.map(cat => ({
      cat:   cat.name,
      items: (cat.items || [])
        .filter(i => i.is_available !== false)
        .map(i => ({ id: i.id, name: i.name, price: parseFloat(i.price) })),
    }));

    // N'écraser que si des catégories ont été trouvées
    if (MENUS && cats.length > 0) MENUS[sec] = cats;

    if (typeof renderCatPills === 'function') renderCatPills();
    if (typeof renderProds === 'function')    renderProds();
  }

  /* Override confirmTable → POST /api/tables pour persister la nouvelle table */
  window.addEventListener('load', () => {
    const _originalConfirmTable = window.confirmTable;
    window.confirmTable = async function() {
      const num = document.getElementById('tbl-num').value.trim()
                  || String(Object.values(S?.tables || {}).length + 1);
      const sec = document.getElementById('tbl-sec').value;
      const persons = S?.newP || 2;

      /* Appel API pour créer la table en base */
const SEC_KEY_TO_ID = { salle: 2, terrasse: 3, caffet: 4 };
const res = await apiBridge('POST', '/tables', {
    number:     String(num),
    section_id: SEC_KEY_TO_ID[sec] || 2,
    capacity:   persons,
});

      if (res && (res.id || res.data?.id)) {
        /* Succès API : utiliser l'ID réel */
        const tableData = res.data || res;
        const realId = tableData.id;
        const orders = {};
        for (let p = 0; p < persons; p++) orders[p] = [];

        if (S?.tables) {
          const key = `${sec}_${num}`;
          S.tables[key] = {
            id:        realId,
            num:       String(num),
            section:   sec,
            persons:   persons,
            orders,
            note:      '',
            ownerId:   SRV?.id || null,
            ownerName: SRV?.name?.split(' ')[0] || null,
            val:       null,
            status:    'available',
          };
          if (S.tableId && S.tables[S.tableId] === undefined) {
            S.tableId = key;
          } else {
            S.tableId = key;
          }
          S.person  = 0;
          S.section = sec;
        }

        /* Fermer modal et re-render */
        if (typeof cm === 'function') cm('m-new-table');
        if (typeof renderCatPills   === 'function') renderCatPills();
        if (typeof renderProds      === 'function') renderProds();
        if (typeof renderTblChips   === 'function') renderTblChips();
        if (typeof renderOrderPanel === 'function') renderOrderPanel();
        if (typeof renderTblGrid    === 'function') renderTblGrid();
        if (typeof notify           === 'function') notify(`Table ${num} créée`, 'ok');
      } else {
        /* Echec API : fallback comportement original */
        if (typeof _originalConfirmTable === 'function') {
          _originalConfirmTable();
        } else {
          const id = `tbl_${Date.now()}`;
          const orders = {};
          for (let p = 0; p < persons; p++) orders[p] = [];
          if (S?.tables) {
            S.tables[id] = { id, num, section: sec, persons, orders, note: '',
              ownerId: SRV?.id || null, ownerName: SRV?.name?.split(' ')[0] || null, val: null };
          }
          if (typeof cm === 'function') cm('m-new-table');
          if (typeof renderTblChips === 'function') renderTblChips();
          if (typeof notify === 'function') notify(`Table ${num} créée (hors-ligne)`, 'warn');
        }
      }
    };

    /* Écouter table.created SSE → recharger la liste des tables */
    if (typeof RealtimeSync !== 'undefined') {
      RealtimeSync.on('table.created', async (data) => {
        await loadServeurTables(S?.section);
        if (typeof notify === 'function') notify(`🪑 Table ${data.number || ''} ajoutée`, 'info');
      });
    }
  });

  /* Override addDish → POST /api/orders/{id}/items */
  /* Le backend auto-valide dès le 1er article → cuisine voit immédiatement */
  window.addEventListener('load', () => {
    window.addDish = async function(name, price) {
      if (!S?.tableId) { notify && notify('Sélectionnez une table', 'err'); return; }
      const tbl = S.tables[S.tableId];
      const person = S.person;

      /* 1. UI locale immédiate */
      if (!tbl.orders[person]) tbl.orders[person] = [];
      tbl.orders[person].push({ name, price, qty: 1, free: false, returned: false, note: '' });
      if (typeof renderOrderItems === 'function') renderOrderItems();
      if (typeof renderTblChips  === 'function') renderTblChips();

      /* 2. Appel API → le backend auto-valide + envoie SSE à la cuisine */
      if (tbl.api_order_id) {
        const menuItem = findMenuItemByName(name);
        if (menuItem?.id) {
          const res = await apiBridge('POST', `/orders/${tbl.api_order_id}/items`, {
            menu_item_id: menuItem.id,
            quantity:     1,
            person_index: person + 1,
          });
          if (res) notify(`✓ ${name} → cuisine`, 'ok');
          else     notify(`${name} ajouté (hors-ligne)`, 'info');
        }
      } else {
        /* Pas de commande → créer d'abord */
        await createOrderAndAdd(tbl, name, person);
      }
    };

    /* sendKitchen : renvoi manuel si besoin */
    window.sendKitchen = async function() {
      if (!S?.tableId) return;
      const tbl = S.tables[S.tableId];
      tbl.val = new Date();
      if (typeof renderTblChips === 'function') renderTblChips();
      if (tbl.api_order_id) {
        await apiBridge('PATCH', `/orders/${tbl.api_order_id}/validate`);
        notify('✓ Commande confirmée en cuisine', 'ok');
      }
    };

    /* Charger les vraies données */
    loadServeurMenu(S?.section || 'salle');
    loadServeurTables(S?.section || 'salle');
  });

  function findMenuItemByName(name) {
    const sec = S?.section || 'salle';
    const cats = MENUS?.[sec] || [];
    for (const cat of cats) {
      const item = (cat.items || []).find(i => i.name === name);
      if (item) return item;
    }
    return null;
  }

  async function createOrderAndAdd(tbl, itemName, personIdx) {
    const tableData = await apiBridge('GET', `/tables/${tbl.id}`);
    if (!tableData?.data?.active_order) {
      /* Créer une commande */
      const order = await apiBridge('POST', '/orders', {
        table_id:      tbl.id,
        persons_count: tbl.persons,
      });
      if (order?.id) {
        tbl.api_order_id = order.id;
        const menuItem = findMenuItemByName(itemName);
        if (menuItem?.id) {
          await apiBridge('POST', `/orders/${order.id}/items`, {
            menu_item_id: menuItem.id,
            quantity:     1,
            person_index: personIdx + 1,
          });
        }
      }
    } else {
      tbl.api_order_id = tableData.data.active_order.id;
    }
  }
}

/* ════════════════════════════════════════════════════════════
   PAGE RESTAURANT-POS (CAISSIER)
   ════════════════════════════════════════════════════════════ */
if (PAGE === 'pos') {

  function apiTableToPOS(table) {
    const ao = table.active_order;
    const persons = ao?.persons_count || 2;
    const orders  = {};
    for (let p = 0; p < persons; p++) orders[p] = [];
    return {
      id:           table.id,
      num:          table.number,
      section:      table.section?.code || 'salle',
      persons:      persons,
      orders:       orders,
      val:          ao?.status === 'validated' ? new Date() : null,
      api_order_id: ao?.id || null,
      status:       table.status,
    };
  }

async function loadPOSTables(section) {
    const sec = section || S?.section || 'salle';
    const data = await apiBridge('GET', `/tables?section=${sec}`);
    if (!data?.data) return;

    data.data.forEach(t => {
      const sectionCode = t.section?.code || 'salle';
      const key = `${sectionCode}_${t.number}`;

      // Conserver les articles du panier si la table est déjà sélectionnée
      const existing = S?.tables?.[key];
      const newTable = apiTableToPOS(t);

      if (existing && S.tableId === key) {
        // Ne pas écraser le panier en cours
        newTable.orders = existing.orders;
        newTable.history = existing.history || [];
      }

      if (S?.tables) {
        S.tables[key] = newTable;
      }

      // Synchroniser aussi par ID réel pour les recherches croisées
      if (S?.tables && t.id) {
        S.tables[key].real_id = t.id;
      }
    });
  }

  async function loadPOSMenu(section) {
    const sec = section || S?.section || 'salle';
    const data = await apiBridge('GET', `/menu/${sec}`);
    if (!data?.data?.categories) return;

    const cats = data.data.categories.map(cat => ({
      cat:   cat.name,
      items: (cat.items || [])
        .filter(i => i.is_available !== false)
        .map(i => ({ id: i.id, name: i.name, price: parseFloat(i.price) })),
    }));

    if (MENUS) MENUS[sec] = cats;
    if (typeof renderCatPills === 'function') renderCatPills();
    if (typeof renderProds === 'function')    renderProds();
  }

  window.addEventListener('load', () => {
    /* Override addDish POS — auto-validation → cuisine voit en temps réel */
    window.addDish = async function(name, price) {
      if (!S?.tableId) { notify && notify('Sélectionnez une table', 'err'); return; }
      const tbl = S.tables[S.tableId];
      const p   = S.person;

      /* 1. UI locale immédiate */
      if (!tbl.orders[p]) tbl.orders[p] = [];
      const menuItem = findPOSMenuItem(name);
      const menuItemId = menuItem ? menuItem.id : null;
      tbl.orders[p].push({ name, price, qty: 1, free: false, returned: false, note: '', menu_item_id: menuItemId });
      if (typeof renderOrderItems === 'function') renderOrderItems();
      if (typeof renderTblSelect  === 'function') renderTblSelect();

      /* 2. Créer commande si besoin */
      if (!tbl.api_order_id) {
        const order = await apiBridge('POST', '/orders', {
          table_id:      tbl.id,
          persons_count: tbl.persons || 1,
        });
        if (order?.id) tbl.api_order_id = order.id;
      }

      /* 3. Ajouter article → backend auto-valide + SSE vers cuisine */
      if (tbl.api_order_id) {
        const menuItem = findPOSMenuItem(name);
        if (menuItem?.id) {
          const res = await apiBridge('POST', `/orders/${tbl.api_order_id}/items`, {
            menu_item_id: menuItem.id,
            quantity:     1,
            person_index: p + 1,
          });
          if (res) notify(`✓ ${name} → cuisine`, 'ok');
          else     notify(`${name} ajouté`, 'info');
        }
      }
    };

    loadPOSMenu(S?.section || 'salle');
    loadPOSTables(S?.section || 'salle');

    /* Écouter table.created SSE → recharger les tables pour le caissier */
    if (typeof RealtimeSync !== 'undefined') {
      RealtimeSync.on('table.created', async (data) => {
        await loadPOSTables(S?.section);
        if (typeof notify === 'function') notify(`🪑 Table ${data.number || ''} disponible`, 'info');
      });

      /* order.validated : une commande serveur arrive → rafraîchir les tables */
      RealtimeSync.on('order.validated', async (data) => {
        await loadPOSTables(S?.section);
        if (typeof notify === 'function') notify(`🍽 Nouvelle commande T${data.table_number || ''}`, 'info');
      });

      /* order.created : mettre à jour le statut des tables */
      RealtimeSync.on('order.created', async (data) => {
        await loadPOSTables(S?.section);
      });
    }
  });

  function findPOSMenuItem(name) {
    const sec = S?.section || 'salle';
    const cats = MENUS?.[sec] || [];
    for (const cat of cats) {
      const item = (cat.items || []).find(i => i.name === name);
      if (item) return item;
    }
    return null;
  }
}

/* ════════════════════════════════════════════════════════════
   PAGE RÉCEPTION (À EMPORTER)
   ════════════════════════════════════════════════════════════ */
if (PAGE === 'reception') {

  async function loadReceptionMenu() {
    const data = await apiBridge('GET', '/menu/emporter');
    if (!data?.data?.categories) {
      /* Fallback: charger le menu salle */
      const fallback = await apiBridge('GET', '/menu/salle');
      if (!fallback?.data?.categories) return;
      data = fallback;
    }
    /* CATEGORIES dans reception.html */
    if (CATEGORIES) {
      CATEGORIES = data.data.categories.map(cat => ({
        id:   cat.id,
        name: cat.name,
        items: (cat.items || [])
          .filter(i => i.is_available !== false)
          .map(i => ({ id: i.id, name: i.name, price: parseFloat(i.price) })),
      }));
      if (typeof renderCatPills === 'function') renderCatPills();
      if (typeof renderProds === 'function')    renderProds();
    }
  }

  async function loadReceptionOrders() {
    const data = await apiBridge('GET', '/emporter');
    if (!data?.data) return;

    /* Injecter dans l'historique si la variable existe */
    if (ORDERS !== undefined) {
      ORDERS = data.data.map(o => ({
        id:       o.id,
        num:      o.order_number || `#${String(o.id).padStart(3,'0')}`,
        client:   o.client_name || 'Client',
        items:    (o.persons?.[0]?.items || []).map(i => ({
          name:  i.item_name,
          price: parseFloat(i.unit_price),
          qty:   i.quantity,
        })),
        subtotal: parseFloat(o.total_amount || 0),
        net:      parseFloat(o.total_amount || 0),
        remise:   0,
        mode:     'especes',
        status:   o.status,
        time:     new Date(o.created_at).toLocaleTimeString('fr-DZ', { hour:'2-digit', minute:'2-digit' }),
      }));
      if (typeof renderHisto === 'function') renderHisto();
      if (typeof updateHistoBadge === 'function') updateHistoBadge();
    }
  }

  window.addEventListener('load', () => {
    loadReceptionMenu();
    loadReceptionOrders();
    // Fallback polling toutes les 2min — la synchronisation principale est via SSE
    setInterval(loadReceptionOrders, 120000);
  });
}

console.log(`[API Bridge] Chargé pour: ${PAGE}`);