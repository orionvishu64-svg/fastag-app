/* products.js (merged)
 *
 * Combined renderer + UI glue:
 * - Renders product cards into #products-container using ProductDB
 * - Exposes window.reloadProducts({ bank, category, q, limit })
 * - Wires searchInput, bankFilter, categoryFilter, resultsCount, noResults and Clear All Filters
 *
 * Requirements:
 * - productdb.js must be loaded before this file (provides ProductDB)
 * - If you have a global addToCart(product) function, this file will call it; otherwise it falls back to localStorage.
 */

/* =========================
   Renderer / product card
   ========================= */

(function () {
  if (!window.ProductDB) {
    console.error('ProductDB missing — include productdb.js before products.js');
    return;
  }

  // Safe fetch wrapper (reused)
  async function safeFetchJson(url, opts = {}) {
    const res = await fetch(url, Object.assign({ credentials: 'include' }, opts));
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  // DOM helpers
  function $(sel, root = document) {
    try {
      return root.querySelector(sel);
    } catch (e) {
      return null;
    }
  }
  function $all(sel, root = document) {
    try {
      return Array.from(root.querySelectorAll(sel));
    } catch (e) {
      return [];
    }
  }

  // Escape text for HTML (small utility)
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // Detect bank from body data attribute or path
  function detectBankFromPage() {
    const b = document.body && document.body.dataset && document.body.dataset.bank;
    if (b) return b;
    const path = location.pathname.split('/').pop().toLowerCase();
    if (path.includes('bajaj')) return 'Bajaj';
    if (path.includes('sbi')) return 'SBI';
    if (path.includes('kotak')) return 'Kotak';
    if (path.includes('idfc')) return 'IDFC';
    return null;
  }

  // Default container selectors (update if your HTML uses other containers)
  const containerSelectors = ['#products-container', '.products-grid', '.products-list'];
  function findContainer() {
    for (const sel of containerSelectors) {
      const el = $(sel);
      if (el) return el;
    }
    // fallback create container under main
    const fallback = document.createElement('div');
    fallback.className = 'products-grid';
    (document.querySelector('main') || document.body).appendChild(fallback);
    return fallback;
  }

  // Build the product card — adapt classes/structure to your CSS if needed.
  function createProductCard(product) {
    const card = document.createElement('div');
    card.className = 'product-card';

    // store dataset keys used by cart
    card.dataset.dbId = product.id;
    card.dataset.productId = product.product_id || '';
    card.dataset.bank = product.bank || '';
    card.dataset.category = product.category || '';

    // Top badges or chips (bank & category)
    const top = document.createElement('div');
    top.className = 'product-card-top';
    top.innerHTML = `
      <div class="chip bank-chip">${escapeHtml(product.bank || '')}</div>
      <div class="chip cat-chip">${escapeHtml(product.category || '')}</div>
    `;

    // Body content (title, description, price)
    const body = document.createElement('div');
    body.className = 'product-card-body';
    body.innerHTML = `
      <h3 class="product-title">${escapeHtml(product.name)}</h3>
      <p class="product-desc">${escapeHtml(product.description || '')}</p>
      <div class="product-price">₹${Number(product.price).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}</div>
      <div class="product-subtext">Inclusive of all charges</div>

      <div class="product-meta">
        <div><small><strong>Bank:</strong> ${escapeHtml(product.bank || '')}</small></div>
        <div><small><strong>Activation:</strong> ${escapeHtml((product.activation) || '')}${product.activation ? '&#8377;' : ''}</small></div>
        <div><small><strong>Security:</strong> ${escapeHtml((product.security) || '')}${product.security ? '&#8377;' : ''}</small></div>
        <div><small><strong>Tag-cost:</strong> ${escapeHtml((product.tagcost) || '')}${product.tagcost ? '&#8377;' : ''}</small></div>
        <div><small><strong>Payout:</strong> ${escapeHtml(String(product.payout) || '')}</small></div>
      </div>
    `;

    // Footer & Add-to-cart button
    const footer = document.createElement('div');
    footer.className = 'product-card-footer';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'add-to-cart-btn';
    btn.textContent = 'Add to Cart';
    // Preserve data attributes used by earlier cart code:
    btn.dataset.productId = product.product_id || '';
    btn.dataset.dbId = product.id;
    btn.dataset.price = product.price;
    btn.dataset.name = product.name;

    btn.addEventListener('click', function (e) {
      // prefer an existing global addToCart function if present
      const prodPayload = {
        id: product.id,
        product_id: product.product_id,
        name: product.name,
        price: product.price,
        bank: product.bank,
        category: product.category
      };
      if (typeof window.addToCart === 'function') {
        window.addToCart(prodPayload);
      } else {
        // fallback cart behavior: simple localStorage push + update cart count
        try {
          let cart = JSON.parse(localStorage.getItem('cart') || '[]');
          cart.push(prodPayload);
          localStorage.setItem('cart', JSON.stringify(cart));
          if (typeof window.updateCartCount === 'function') window.updateCartCount();
        } catch (err) {
          console.error('add-to-cart fallback error', err);
        }
      }
    });

    footer.appendChild(btn);

    // assemble
    card.appendChild(top);
    card.appendChild(body);
    card.appendChild(footer);

    return card;
  }

  // Render a list of products into container
  async function renderProductsList(products, container) {
    container = container || findContainer();
    container.innerHTML = '';
    if (!products || products.length === 0) {
      container.innerHTML = '<div class="notice-card">No products found.</div>';
      return;
    }
    const frag = document.createDocumentFragment();
    for (const p of products) {
      const card = createProductCard(p);
      frag.appendChild(card);
    }
    container.appendChild(frag);
  }

  // Public loader that fetches and renders (supports opts: { bank, category, q, limit, container })
  async function loadAndRender(opts = {}) {
    const bank = opts.bank === undefined ? detectBankFromPage() : opts.bank;
    const category = opts.category || null;
    const q = opts.q || null;
    const limit = opts.limit || 0;
    const container = opts.container ? document.querySelector(opts.container) : findContainer();

    try {
      container.innerHTML = '<div class="notice-card">Loading products…</div>';
      const products = await ProductDB.getAll({ force: false, bank, category, q, limit });
      await renderProductsList(products, container);
    } catch (err) {
      console.error('Failed to load products', err);
      container.innerHTML = `<div class="notice-card">Failed to load products.</div>`;
    }
  }

  // make reload available globally
  window.reloadProducts = (opts) => loadAndRender(opts);

  // Auto load on DOM ready (but the UI glue below will also kick off a load via reloadAndUpdateUI)
  document.addEventListener('DOMContentLoaded', () => {
    // do not auto call loadAndRender here to avoid double-loading — UI glue controls initial load
    // loadAndRender();
  });

  /* =========================
     UI glue (merged from products-ui.js)
     ========================= */

  // Map UI control values -> ProductDB params
  function uiToParams() {
    const bankVal = document.getElementById('bankFilter')?.value || 'all';
    const categoryVal = document.getElementById('categoryFilter')?.value || 'all';
    const q = document.getElementById('searchInput')?.value?.trim() || '';
    return {
      bank: bankVal === 'all' ? null : bankVal,
      category: categoryVal === 'all' ? null : categoryVal,
      q: q || null
    };
  }

  function showNoResults(show) {
    const el = document.getElementById('noResults');
    if (!el) return;
    el.style.display = show ? '' : 'none';
  }

  function updateResultsCount(n) {
    const el = document.getElementById('resultsCount');
    if (!el) return;
    el.textContent = String(n);
  }

  function clearAllFilters() {
    const s = document.getElementById('searchInput');
    const b = document.getElementById('bankFilter');
    const c = document.getElementById('categoryFilter');
    if (s) s.value = '';
    if (b) b.value = 'all';
    if (c) c.value = 'all';
    reloadAndUpdateUI();
  }

  // Expose globally for HTML button onclick
  window.clearAllFilters = clearAllFilters;

  // Core: fetch products (count) then render and update UI
  async function reloadAndUpdateUI() {
    const params = uiToParams();
    try {
      // Get matching products (force server fetch to ensure count reflects filters)
      const products = await ProductDB.getAll({ force: true, bank: params.bank, category: params.category, q: params.q });
      updateResultsCount(products.length);
      showNoResults(products.length === 0);

      // Render cards (products.js provides reloadProducts)
      window.reloadProducts({ bank: params.bank, category: params.category, q: params.q });
    } catch (err) {
      console.error('reloadAndUpdateUI error', err);
      updateResultsCount(0);
      showNoResults(true);
      // still attempt to render so the renderer can show an error notice if needed
      window.reloadProducts({ bank: params.bank, category: params.category, q: params.q });
    }
  }

  // Debounce helper
  function debounce(fn, wait = 300) {
    let t = null;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  }

  // Wire controls on DOMContentLoaded
  document.addEventListener('DOMContentLoaded', function () {
    // initial load via UI glue (counts + render)
    reloadAndUpdateUI();

    // search input (debounced)
    const search = document.getElementById('searchInput');
    if (search) {
      search.addEventListener('input', debounce(() => reloadAndUpdateUI(), 350));
    }

    // bank filter
    const bankSel = document.getElementById('bankFilter');
    if (bankSel) {
      bankSel.addEventListener('change', () => reloadAndUpdateUI());
    }

    // category filter
    const catSel = document.getElementById('categoryFilter');
    if (catSel) {
      catSel.addEventListener('change', () => reloadAndUpdateUI());
    }
  });

})();
