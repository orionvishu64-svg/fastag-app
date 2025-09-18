/* products.js — single-file renderer + Blinkit-style bottom sheet
   - Renders products into #products-container / .products-grid
   - Clicking a product card opens the full-screen sheet (reliable)
   - No legacy modal left behind
   - addToCart fallback uses localStorage
*/

(function () {
  /* ---------------- small helpers ---------------- */
  const $ = (s, root = document) => { try { return (root || document).querySelector(s); } catch { return null; } };
  const $all = (s, root = document) => { try { return Array.from((root || document).querySelectorAll(s)); } catch { return []; } };
  const escapeHtml = v => (v == null ? '' : String(v)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'));

  /* ---------- container finder ---------- */
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

  /* ---------- product sheet (single source of truth) ---------- */
  function initSheet() {
    if (window.__productSheet) return window.__productSheet;

    // Create sheet DOM
    const sheet = document.createElement('div');
    sheet.id = 'productFullSheet';
    sheet.className = 'product-full-sheet';
    sheet.style.display = 'none';
    sheet.innerHTML = `
      <div class="sheet-backdrop" data-dismiss="true"></div>
      <div class="sheet-panel" role="dialog" aria-modal="true">
        <div class="sheet-handle"></div>
        <div class="sheet-body">
          <button class="sheet-close" aria-label="Close">✕</button>
          <div class="sheet-content">
            <div class="sheet-left"><div class="sheet-image-wrap"><img src="" alt="product"></div></div>
            <div class="sheet-right">
              <h2 class="sheet-title"></h2>
              <div class="sheet-bank-cat"></div>
              <div class="sheet-meta-grid">
                <div><strong>Activation:</strong> <span class="sheet-activation"></span></div>
                <div><strong>Security:</strong> <span class="sheet-security"></span></div>
                <div><strong>Tagcost:</strong> <span class="sheet-tagcost"></span></div>
                <div><strong>Payout:</strong> <span class="sheet-payout"></span></div>
              </div>
              <div class="sheet-price"></div>
              <div class="sheet-desc"></div>
              <div class="sheet-actions">
                <div class="sheet-qty">
                  <button class="qty-decr" aria-label="decrease">−</button>
                  <input class="qty-input" type="number" value="1" min="1" />
                  <button class="qty-incr" aria-label="increase">+</button>
                </div>
                <button class="btn sheet-add">Add to cart</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(sheet);

    // cache nodes, defensive
    const panel = sheet.querySelector('.sheet-panel');
    const backdrop = sheet.querySelector('.sheet-backdrop');
    const closeBtn = sheet.querySelector('.sheet-close');
    const img = sheet.querySelector('.sheet-image-wrap img');
    const title = sheet.querySelector('.sheet-title');
    const bankCat = sheet.querySelector('.sheet-bank-cat');
    const activation = sheet.querySelector('.sheet-activation');
    const security = sheet.querySelector('.sheet-security');
    const tagcost = sheet.querySelector('.sheet-tagcost');
    const payout = sheet.querySelector('.sheet-payout');
    const priceEl = sheet.querySelector('.sheet-price');
    const desc = sheet.querySelector('.sheet-desc');
    const qtyInput = sheet.querySelector('.qty-input');
    const qtyInc = sheet.querySelector('.qty-incr');
    const qtyDec = sheet.querySelector('.qty-decr');
    let addBtn = sheet.querySelector('.sheet-add');

    // helper to wire add button safely
    function wireAdd(product) {
      // always fetch current addBtn node
      addBtn = sheet.querySelector('.sheet-add');
      if (!addBtn) return;
      // remove previous listeners by replacing node with clone
      const newAdd = addBtn.cloneNode(true);
      addBtn.parentNode.replaceChild(newAdd, addBtn);
      addBtn = newAdd;
      addBtn.addEventListener('click', function () {
        const q = Math.max(1, Number(qtyInput.value || 1));
        if (typeof addToCart === 'function') {
          addToCart(Object.assign({}, product, { qty: q }));
        } else {
          const cart = JSON.parse(localStorage.getItem('cart') || '[]');
          cart.push(Object.assign({}, product, { qty: q }));
          localStorage.setItem('cart', JSON.stringify(cart));
        }
        // small feedback then close
        addBtn.textContent = '✔ Added';
        setTimeout(hide, 500);
      });
    }

    // show/hide functions
    function show(product) {
      if (!product) return;
      try {
        img.src = product.logo || '';
        img.alt = product.name || '';
        title.textContent = product.name || '';
        bankCat.textContent = `${product.bank || ''} • ${product.category || ''}`;
        activation.textContent = (product.activation != null) ? `₹${product.activation}` : '—';
        security.textContent = (product.security != null) ? `₹${product.security}` : '—';
        tagcost.textContent = (product.tagcost != null) ? `₹${product.tagcost}` : '—';
        payout.textContent = product.payout || '—';
        priceEl.textContent = product.price != null ? `₹${Number(product.price).toLocaleString()}` : '₹0';
        desc.textContent = product.description || '';
        qtyInput.value = 1;

        // wire qty controls
        qtyInc.onclick = () => qtyInput.value = Math.max(1, Number(qtyInput.value || 1) + 1);
        qtyDec.onclick = () => qtyInput.value = Math.max(1, Number(qtyInput.value || 1) - 1);
        qtyInput.oninput = () => { if (!qtyInput.value || Number(qtyInput.value) < 1) qtyInput.value = 1; };

        // wire add
        wireAdd(product);

        // show sheet with CSS transition
        sheet.style.display = '';
        // ensure panel reset
        panel.classList.remove('panel-up');
        sheet.classList.remove('sheet-open');

        // show next frame
        requestAnimationFrame(() => {
          sheet.classList.add('sheet-open');
          panel.classList.add('panel-up');
        });
      } catch (err) {
        console.error('sheet show error', err);
      }
    }

    function hide() {
      try {
        panel.classList.remove('panel-up');
        sheet.classList.remove('sheet-open');
        // reset after transition
        setTimeout(() => {
          sheet.style.display = 'none';
          panel.classList.remove('panel-up');
          sheet.classList.remove('sheet-open');
        }, 320);
      } catch (err) {
        // never crash
        sheet.style.display = 'none';
      }
    }

    // close handlers
    backdrop && backdrop.addEventListener('click', hide);
    closeBtn && closeBtn.addEventListener('click', hide);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });

    // expose API
    window.showProductSheet = show;
    window.hideProductSheet = hide;

    // store
    window.__productSheet = { show, hide, sheet, panel };
    return window.__productSheet;
  }

  /* ---------- product card creation & renderer ---------- */
  function createProductCard(product) {
    product = product || {};
    const card = document.createElement('article');
    const bankClass = (product.bank || '').toString().trim().toLowerCase().replace(/\s+/g, '-');
    card.className = 'product-card' + (bankClass ? ' ' + bankClass : '');

    // index & dataset for retrieval
    window._productIndex = window._productIndex || [];
    const idx = window._productIndex.push(product) - 1;
    card.setAttribute('data-product-index', String(idx));
    try { card.dataset.product = JSON.stringify(product); } catch (e) { /* ignore */ }

    card.innerHTML = `
      <div class="product-image-wrap">
        <img src="${escapeHtml(product.logo||'')}" alt="${escapeHtml(product.name||'')}">
        <button class="add-overlay">ADD</button>
      </div>
      <div class="product-info">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div class="cat-chip">${escapeHtml(product.category||'')}</div>
          <div class="bank-small">${escapeHtml(product.bank||'')}</div>
        </div>
        <h3 class="product-title">${escapeHtml(product.name||'')}</h3>
        <div class="price">${product.price!=null? '₹' + Number(product.price).toLocaleString() : ''}</div>
      </div>
    `;

    // add button
    const btn = card.querySelector('.add-overlay');
    if (btn) {
      btn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        if (typeof addToCart === 'function') addToCart(product);
        else {
          const cart = JSON.parse(localStorage.getItem('cart') || '[]');
          cart.push(product);
          localStorage.setItem('cart', JSON.stringify(cart));
          console.info('Added to cart (fallback localStorage)', product);
        }
      });
    }

    return card;
  }

  function renderProductsList(products, container) {
    container = container || findContainer();
    container.innerHTML = '';
    if (!products || products.length === 0) {
      container.innerHTML = '<div class="notice-card">No products found.</div>';
      return;
    }
    // reset index
    window._productIndex = [];

    const frag = document.createDocumentFragment();
    for (const p of products) frag.appendChild(createProductCard(p || {}));
    container.appendChild(frag);
  }

  /* ---------- loader & UI glue (ProductDB) ---------- */
  async function loadAndRender(opts = {}) {
    const bank = opts.bank === undefined ? (document.body && document.body.dataset && document.body.dataset.bank) : opts.bank;
    const category = opts.category || null;
    const q = opts.q || null;
    const limit = opts.limit || 0;
    const container = opts.container ? document.querySelector(opts.container) : findContainer();

    try {
      container.innerHTML = '<div class="notice-card">Loading products…</div>';
      const products = (window.ProductDB && typeof window.ProductDB.getAll === 'function')
        ? await window.ProductDB.getAll({ force: false, bank, category, q, limit })
        : [];
      renderProductsList(products, container);
    } catch (err) {
      console.error('Failed to load products', err);
      container.innerHTML = '<div class="notice-card">Failed to load products.</div>';
    }
  }
  window.reloadProducts = (opts) => loadAndRender(opts);

  function uiToParams() {
    const bankValRaw = document.getElementById('bankFilter')?.value || '';
    const categoryValRaw = document.getElementById('categoryFilter')?.value || '';
    const q = document.getElementById('searchInput')?.value?.trim() || '';
    const bank = (bankValRaw && bankValRaw !== 'all') ? bankValRaw : null;
    const category = (categoryValRaw && categoryValRaw !== 'all') ? categoryValRaw : null;
    return { bank, category, q: q || null };
  }

  async function reloadAndUpdateUI() {
    const params = uiToParams();
    try {
      const products = (window.ProductDB && typeof window.ProductDB.getAll === 'function')
        ? await window.ProductDB.getAll({ force: true, bank: params.bank, category: params.category, q: params.q })
        : [];
      const resultsCountEl = document.getElementById('resultsCount');
      if (resultsCountEl) resultsCountEl.textContent = String(products.length);
      const noEl = document.getElementById('noResults');
      if (noEl) noEl.style.display = products.length === 0 ? '' : 'none';
      renderProductsList(products, findContainer());
    } catch (err) {
      console.error('reloadAndUpdateUI error', err);
      const resultsCountEl = document.getElementById('resultsCount');
      if (resultsCountEl) resultsCountEl.textContent = '0';
      const noEl = document.getElementById('noResults');
      if (noEl) noEl.style.display = '';
      renderProductsList([], findContainer());
    }
  }

  function debounce(fn, wait = 300) { let t = null; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); }; }

  /* ---------- init on DOM ready if products page ---------- */
  document.addEventListener('DOMContentLoaded', function () {
    const isProductsPage = document.body && document.body.dataset && document.body.dataset.page === 'products';
    if (!isProductsPage) return;
    // ensure sheet created
    initSheet();
    reloadAndUpdateUI();

    const search = document.getElementById('searchInput');
    if (search) search.addEventListener('input', debounce(() => reloadAndUpdateUI(), 300));
    const clearBtn = document.querySelector('.clear-filters');
    if (clearBtn) clearBtn.addEventListener('click', () => { const s = document.getElementById('searchInput'); if (s) s.value = ''; reloadAndUpdateUI(); });
    const bankSel = document.getElementById('bankFilter');
    if (bankSel) bankSel.addEventListener('change', () => reloadAndUpdateUI());
    const catSel = document.getElementById('categoryFilter');
    if (catSel) catSel.addEventListener('change', () => reloadAndUpdateUI());
  });

  /* ---------- delegated click: show sheet for any product card click ---------- */
  document.addEventListener('click', function (e) {
    const card = e.target.closest && e.target.closest('article.product-card');
    if (!card) return;
    // ignore add-overlay clicks
    if (e.target.closest && e.target.closest('.add-overlay, .add-to-cart, .overlay-add, .btn.add-to-cart')) return;

    // get product from index / dataset
    let prod = null;
    try {
      const idx = card.getAttribute('data-product-index');
      if (idx !== null && window._productIndex && window._productIndex[idx]) prod = window._productIndex[idx];
    } catch (err) { prod = null; }
    if (!prod && card.dataset && card.dataset.product) {
      try { prod = JSON.parse(card.dataset.product); } catch (err) { prod = null; }
    }

    if (prod) {
      try {
        initSheet(); // ensure sheet exists
        // open sheet (sheet API handles show/hide robustly)
        window.showProductSheet(prod);
      } catch (err) {
        console.error('open sheet failed', err);
      }
    }
  }, false);

  // expose debug API
  window.reloadAndUpdateUI = reloadAndUpdateUI;
})();
