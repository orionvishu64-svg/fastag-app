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
    return null;
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
    const card = document.createElement('article');
    // add bank class if present (normalized)
    const bankClass = (product.bank || '').toString().trim();
    card.className = 'product-card' + (bankClass ? ' ' + bankClass.toLowerCase() : '');
    card.setAttribute('data-bank', product.bank || '');

    // header (bank + badges)
    const header = document.createElement('div');
    header.className = 'product-header';

    const bankInfo = document.createElement('div');
    bankInfo.className = 'product-bank-info';

    const bankLogo = document.createElement('div');
    bankLogo.className = 'bank-logo-section';
    // if product has a logo URL use img, else show bank short text
    if (product.logo) {
      const img = document.createElement('img');
      img.src = product.logo;
      img.alt = product.bank || '';
      img.style.maxHeight = '44px';
      bankLogo.appendChild(img);
    } else {
      bankLogo.innerHTML = `<div class="chip bank-chip">${escapeHtml(product.bank || '')}</div>`;
    }

    const rightHeader = document.createElement('div');
    rightHeader.className = 'product-header-right';
    rightHeader.innerHTML = `<div class="chip cat-chip">${escapeHtml(product.category || '')}</div>`;

    bankInfo.appendChild(bankLogo);
    bankInfo.appendChild(rightHeader);
    header.appendChild(bankInfo);

    // content
    const content = document.createElement('div');
    content.className = 'product-content';

    const title = document.createElement('h3');
    title.className = 'product-title';
    title.textContent = product.name || '';

    const desc = document.createElement('p');
    desc.className = 'product-description';
    desc.textContent = product.description || '';

    // price section
    const priceSection = document.createElement('div');
    priceSection.className = 'price-section';
    const priceEl = document.createElement('div');
    priceEl.className = 'price';
    priceEl.textContent = (product.price != null) ? '₹' + Number(product.price).toLocaleString() : '₹0';
    priceSection.appendChild(priceEl);

    const subtext = document.createElement('div');
    subtext.className = 'product-subtext';
    subtext.textContent = 'Inclusive of all charges';

    // meta row
    const meta = document.createElement('div');
    meta.className = 'product-meta';
    meta.innerHTML = `
      <div><small><strong>Activation:</strong> ${escapeHtml(product.activation || '')}${product.activation ? '&#8377;' : ''}</small></div>
      <div><small><strong>Security:</strong> ${escapeHtml(product.security || '')}${product.security ? '&#8377;' : ''}</small></div>
      <div><small><strong>Tagcost:</strong> ${escapeHtml(product.tagcost || '')}${product.tagcost ? '&#8377;' : ''}</small></div>
      <div><small><strong>Payout:</strong> ${escapeHtml(product.payout || '')}${product.payout ? '&#8377;' : ''}</small></div>
    `;

    // footer / actions
    const footer = document.createElement('div');
    footer.className = 'product-card-footer';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'add-to-cart-btn';
    btn.textContent = 'Add To Cart';
    // data attributes for existing cart integration
    btn.dataset.productId = product.product_id || '';
    btn.dataset.dbId = product.id || '';
    btn.dataset.price = product.price || 0;
    btn.addEventListener('click', function () {
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
        try {
          let cart = JSON.parse(localStorage.getItem('cart') || '[]');
          cart.push(prodPayload);
          localStorage.setItem('cart', JSON.stringify(cart));
          // update cart count if present
          const cc = document.querySelector('.cart-count');
          if (cc) cc.textContent = cart.length;
        } catch (e) { console.error('add to cart error', e); }
      }
    });

    footer.appendChild(btn);

    // assemble content
    content.appendChild(title);
    content.appendChild(desc);
    content.appendChild(priceSection);
    content.appendChild(subtext);
    content.appendChild(meta);
    content.appendChild(footer);

    card.appendChild(header);
    card.appendChild(content);

    return card;
  }
function renderProductsList(products, container) {
    container = container || findContainer();
    container.innerHTML = '';
    if (!products || products.length === 0) {
      container.innerHTML = '<div class="notice-card">No products found.</div>';
      return null;
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
    const bankValRaw = document.getElementById('bankFilter')?.value || '';
    const categoryValRaw = document.getElementById('categoryFilter')?.value || '';
    const q = document.getElementById('searchInput')?.value?.trim() || '';
    const bank = (bankValRaw && bankValRaw !== 'all') ? bankValRaw : null;
    const category = (categoryValRaw && categoryValRaw !== 'all') ? categoryValRaw : null;
    return {
      bank: bank,
      category: category,
      q: q || null
    }


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

      // Render cards using already fetched products to avoid duplicate fetch
      const container = findContainer();
      renderProductsList(products, container);
    } catch (err) {
      console.error('reloadAndUpdateUI error', err);
      updateResultsCount(0);
      showNoResults(true);
      // render an empty set so renderer shows no products
      const container = findContainer();
      renderProductsList([], container);
    }
  };

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
    const isProductsPage = document.body && document.body.dataset && document.body.dataset.page === 'products';
    if (!isProductsPage) {
      // don't auto-load products or wire products-page specific UI on bank pages
      return null;
    }

    // initial load via UI glue (counts + render)
    reloadAndUpdateUI();

    // search input (debounced)
    const search = document.getElementById('searchInput');
    if (search) {
      search.addEventListener('input', debounce(() => reloadAndUpdateUI(), 300));
    }

    // clear filters button
    const clearBtn = document.querySelector('.clear-filters');
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        clearAllFilters();
        reloadAndUpdateUI();
      });
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
