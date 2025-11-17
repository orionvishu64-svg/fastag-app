/* /public/js/products.js */

(function () {
  /* ---------------- small helpers ---------------- */
  const $ = (s, root = document) => { try { return (root || document).querySelector(s); } catch { return null; } };
  const $all = (s, root = document) => { try { return Array.from((root || document).querySelectorAll(s)); } catch { return []; } };
  const escapeHtml = v => (v == null ? '' : String(v)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'));

  // Inline SVG placeholder for missing images
  function svgPlaceholder(text = '?') {
    text = (text || '?').toString().trim().slice(0, 3).toUpperCase();
    const svg =
`<svg xmlns="http://www.w3.org/2000/svg" width="640" height="640">
  <rect width="100%" height="100%" fill="#f0f2f5"/>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
        font-family="Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif"
        font-size="160" fill="#9aa3af">${text}</text>
</svg>`;
    return 'data:image/svg+xml;base64,' + (typeof btoa === 'function'
      ? btoa(svg)
      : Buffer.from(svg, 'utf8').toString('base64'));
  }

  // Normalize any image-ish field into a usable web URL
  function normalizePath(p) {
    if (!p) return '';
    if (/^(?:https?:)?\/\//i.test(p)) return p; // http(s) or protocol-relative
    return p.startsWith('/') ? p : '/' + p;    // ensure web-rooted path
  }

  // Pick the best image src from a product row, or placeholder
  function pickLogo(product) {
    if (!product) return svgPlaceholder('?');
    const cand = product.logo || product.image || product.image_url || product.image_path || '';
    return cand ? normalizePath(cand) : svgPlaceholder(product.bank || product.name || '?');
  }

  // Attach a safe onerror handler to swap to placeholder if the image 404s
  function attachImgFallback(imgEl, product) {
    if (!imgEl) return;
    imgEl.onerror = () => {
      imgEl.onerror = null;
      imgEl.src = svgPlaceholder(product?.bank || product?.name || '?');
    };
  }

  /* ---------- cart helpers (single source of truth = localStorage 'cart') ---------- */
  function getCart() {
    try { return JSON.parse(localStorage.getItem('cart') || '[]'); }
    catch { return []; }
  }
  function setCart(arr) {
    try { localStorage.setItem('cart', JSON.stringify(Array.isArray(arr) ? arr : [])); }
    catch {}
  }
  function productId(obj) {
    // prefer numeric id/product_id; fallback to string key using name
    const nid = Number(obj?.id ?? obj?.product_id);
    if (!Number.isNaN(nid)) return `id:${nid}`;
    const name = (obj?.name || '').toString().trim();
    return name ? `name:${name}` : null;
  }
  function sameProduct(a, b) {
    const ka = productId(a);
    const kb = productId(b);
    return !!ka && !!kb && ka === kb;
  }
  function isInCart(p) {
    const cart = getCart();
    return cart.some(item => sameProduct(item, p));
  }
  function addToCartLocal(p, qty) {
    const cart = getCart();
    if (cart.some(i => sameProduct(i, p))) return cart; // already there
    const item = { ...p };
    // normalize quantity field name for compatibility
    if ('quantity' in item) item.quantity = Math.max(1, Number(qty || item.quantity || 1));
    else item.qty = Math.max(1, Number(qty || item.qty || 1));
    cart.push(item);
    setCart(cart);
    return cart;
  }
  function removeFromCartLocal(p) {
    const cart = getCart().filter(i => !sameProduct(i, p));
    setCart(cart);
    return cart;
  }

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
            <div class="sheet-left">
              <div class="sheet-image-wrap">
                <img src="" alt="product">
              </div>
            </div>
            <div class="sheet-right">
              <h2 class="sheet-title"></h2>
              <div class="sheet-bank-cat"></div>
              <div class="sheet-meta-grid">
                <div><strong>Activation:</strong> <span class="sheet-activation"></span></div>
                <div><strong>Balance:</strong> <span class="sheet-balance"></span></div>
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

    // cache nodes
    const panel = sheet.querySelector('.sheet-panel');
    const backdrop = sheet.querySelector('.sheet-backdrop');
    const closeBtn = sheet.querySelector('.sheet-close');
    const img = sheet.querySelector('.sheet-image-wrap img');
    const title = sheet.querySelector('.sheet-title');
    const bankCat = sheet.querySelector('.sheet-bank-cat');
    const activation = sheet.querySelector('.sheet-activation');
    const balance = sheet.querySelector('.sheet-balance');
    const security = sheet.querySelector('.sheet-security');
    const tagcost = sheet.querySelector('.sheet-tagcost');
    const payout = sheet.querySelector('.sheet-payout');
    const priceEl = sheet.querySelector('.sheet-price');
    const desc = sheet.querySelector('.sheet-desc');
    const qtyInput = sheet.querySelector('.qty-input');
    const qtyInc = sheet.querySelector('.qty-incr');
    const qtyDec = sheet.querySelector('.qty-decr');
    let addBtn = sheet.querySelector('.sheet-add');

    let currentProduct = null; // for storage sync

    function setAddedState(added) {
      if (!addBtn) return;
      if (added) {
        addBtn.textContent = '✔ Added';
        addBtn.disabled = true;
        addBtn.setAttribute('aria-disabled', 'true');
        addBtn.classList.add('is-added');
      } else {
        addBtn.textContent = 'Add to cart';
        addBtn.disabled = false;
        addBtn.removeAttribute('aria-disabled');
        addBtn.classList.remove('is-added');
      }
    }

    function wireAdd(product) {
      addBtn = sheet.querySelector('.sheet-add');
      if (!addBtn) return;
      const newAdd = addBtn.cloneNode(true); // remove old listeners
      addBtn.parentNode.replaceChild(newAdd, addBtn);
      addBtn = newAdd;

      // initialize button state (if product already in cart)
      setAddedState(isInCart(product));

      addBtn.addEventListener('click', function () {
        // Guard: if already in cart, do nothing (button remains disabled)
        if (isInCart(product)) {
          setAddedState(true);
          return;
        }
        const q = Math.max(1, Number(qtyInput.value || 1));
        // immediate UI lock to avoid double clicks
        addBtn.disabled = true;
        addBtn.setAttribute('aria-disabled', 'true');

        // Prefer project-level addToCart if available, but still lock UI
        if (typeof window.addToCart === 'function') {
          try { window.addToCart({ ...product, qty: q }); }
          catch {}
        } else {
          addToCartLocal(product, q);
        }

        setAddedState(true);
        // (Optional) auto-close sheet; does not affect sticky state
        // setTimeout(hide, 500);
      });
    }

    function show(product) {
      if (!product) return;
      try {
        currentProduct = product;
        const src = pickLogo(product);
        img.src = src;
        attachImgFallback(img, product);
        img.alt = product.name || 'product';

        title.textContent = product.name || '';
        bankCat.textContent = `${product.bank || ''} • ${product.category || ''}`;
        activation.textContent = (product.activation != null) ? `₹${product.activation}` : '—';
        balance.textContent = (product.balance != null) ? `₹${product.balance}` : '—';
        security.textContent = (product.security != null) ? `₹${product.security}` : '—';
        tagcost.textContent = (product.tagcost != null) ? `₹${product.tagcost}` : '—';
        payout.textContent = product.payout || '—';
        priceEl.textContent = (product.price != null) ? `₹${Number(product.price).toLocaleString()}` : '₹0';
        desc.textContent = product.description || '';
        qtyInput.value = 1;

        qtyInc.onclick = () => qtyInput.value = Math.max(1, Number(qtyInput.value || 1) + 1);
        qtyDec.onclick = () => qtyInput.value = Math.max(1, Number(qtyInput.value || 1) - 1);
        qtyInput.oninput = () => { if (!qtyInput.value || Number(qtyInput.value) < 1) qtyInput.value = 1; };

        wireAdd(product);
        setAddedState(isInCart(product)); // reflect latest cart state at open time

        sheet.style.display = '';
        panel.classList.remove('panel-up');
        sheet.classList.remove('sheet-open');
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
        setTimeout(() => {
          sheet.style.display = 'none';
          panel.classList.remove('panel-up');
          sheet.classList.remove('sheet-open');
        }, 320);
      } catch {
        sheet.style.display = 'none';
      }
    }

    // Keep button in sync if cart is changed elsewhere (another tab/page or cart screen)
    window.addEventListener('storage', (e) => {
      if (e.key === 'cart' && currentProduct) {
        setAddedState(isInCart(currentProduct));
      }
    });

    // close handlers
    backdrop && backdrop.addEventListener('click', hide);
    closeBtn && closeBtn.addEventListener('click', hide);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });

    // expose API
    window.showProductSheet = show;
    window.hideProductSheet = hide;

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
    try { card.dataset.product = JSON.stringify(product); } catch {}

    const imgSrc = pickLogo(product);

    card.innerHTML = `
      <div class="product-image-wrap">
        <img src="${escapeHtml(imgSrc)}" alt="${escapeHtml(product.name||'')}" loading="lazy">
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

    // attach runtime fallback for broken images
    const img = card.querySelector('img');
    attachImgFallback(img, product);

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
    const bank = opts.bank === undefined ? (document.body?.dataset?.bank) : opts.bank;
    const category = opts.category || null;
    const q = opts.q || null;
    const limit = opts.limit || 0;
    const container = opts.container ? document.querySelector(opts.container) : findContainer();

    try {
      container.innerHTML = '<div class="notice-card">Loading products…</div>';
      const products = (window.ProductDB?.getAll)
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
      const products = (window.ProductDB?.getAll)
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
    const isProductsPage = document.body?.dataset?.page === 'products';
    if (!isProductsPage) return;
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

  /* ---------- delegated click: open sheet ---------- */
  document.addEventListener('click', function (e) {
    const card = e.target.closest && e.target.closest('article.product-card');
    if (!card) return;

    let prod = null;
    try {
      const idx = card.getAttribute('data-product-index');
      if (idx !== null && window._productIndex && window._productIndex[idx]) prod = window._productIndex[idx];
    } catch { prod = null; }
    if (!prod && card.dataset && card.dataset.product) {
      try { prod = JSON.parse(card.dataset.product); } catch { prod = null; }
    }

    if (prod) {
      try {
        initSheet();
        window.showProductSheet(prod);
      } catch (err) {
        console.error('open sheet failed', err);
      }
    }
  }, false);

  // expose for debugging
  window.reloadAndUpdateUI = reloadAndUpdateUI;
  window._cartDebug = { getCart, setCart, addToCartLocal, removeFromCartLocal, isInCart };
})();
