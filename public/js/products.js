/* /public/js/products.js */
(function () {
  "use strict";

  /* ================== HELPERS ================== */
  const $ = (s, root = document) => {
    try {
      return root.querySelector(s);
    } catch {
      return null;
    }
  };

  const escapeHtml = (v = "") =>
    String(v)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");

  const debounce = (fn, delay = 300) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), delay);
    };
  };

  /* ================== IMAGE ================== */
  function svgPlaceholder(text = "?") {
    const t = text.toString().slice(0, 3).toUpperCase();
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400">
      <rect width="100%" height="100%" fill="#f1f5f9"/>
      <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
        font-size="120" fill="#94a3b8">${t}</text>
    </svg>`;
    return "data:image/svg+xml;base64," + btoa(svg);
  }

  function pickLogo(p) {
    const img = p?.logo || p?.image || p?.image_url || p?.image_path || "";
    if (!img) return svgPlaceholder(p?.bank || "?");
    if (/^https?:\/\//i.test(img)) return img;
    return img.startsWith("/") ? img : "/" + img;
  }

  /* ================== CART ================== */
  function getCart() {
    try {
      return JSON.parse(localStorage.getItem("cart") || "[]");
    } catch {
      return [];
    }
  }

  function productKey(p) {
    if (p?.id || p?.product_id) return `id:${p.id || p.product_id}`;
    return `name:${p?.name}`;
  }

  function isInCart(p) {
    return getCart().some((i) => productKey(i) === productKey(p));
  }

  function addToCart(p, qty) {
    const cart = getCart();
    if (isInCart(p)) return;
    cart.push({ ...p, qty: Number(qty || 1) });
    localStorage.setItem("cart", JSON.stringify(cart));
  }

  /* ================== BANK BADGES ================== */
  function bankBadge(bank = "") {
    const b = bank.toLowerCase();
    if (b.includes("sbi")) return "badge-sbi";
    if (b.includes("bajaj")) return "badge-bajaj";
    if (b.includes("idfc")) return "badge-idfc";
    if (b.includes("kotak")) return "badge-kotak";
    return "bg-secondary";
  }

  /* ================== PRODUCT CARD ================== */
  function createProductCard(product) {
    const col = document.createElement("div");
    col.className = "col";

    window._productIndex = window._productIndex || [];
    const index = window._productIndex.push(product) - 1;

    col.innerHTML = `
      <article class="card h-100 shadow-sm product-card"
        data-product-index="${index}" style="cursor:pointer">

        <img src="${pickLogo(product)}"
          class="card-img-top p-3"
          style="height:160px;object-fit:contain">

        <div class="card-body d-flex flex-column">
          <span class="badge ${bankBadge(product.bank)} text-white mb-2">
            ${escapeHtml(product.bank)}
          </span>

          <h6 class="fw-bold">${escapeHtml(product.name)}</h6>
          <small class="text-muted mb-2">
            ${escapeHtml(product.category)}
          </small>

          <div class="mt-auto fw-bold text-primary">
            ₹${Number(product.price || 0).toLocaleString()}
          </div>
        </div>
      </article>
    `;
    return col;
  }

  // expose safely for bank-pages.js
  window.__createProductCard = createProductCard;

  /* ================== RENDER ================== */
  function renderProducts(products = []) {
    const container = $("#products-container");
    const noResults = $("#noResults");

    container.innerHTML = "";
    window._productIndex = [];

    if (!products.length) {
      noResults?.classList.remove("d-none");
      return;
    }

    noResults?.classList.add("d-none");
    products.forEach((p) => container.appendChild(createProductCard(p)));
  }

  /* ================== FILTERS ================== */
  function uiParams() {
    const pageBank = document.body.dataset.bank || null;
    const bankFilter = $("#bankFilter")?.value;
    const categoryFilter = $("#categoryFilter")?.value;
    const search = $("#searchInput")?.value?.trim() || null;

    return {
      bank:
        pageBank || (bankFilter && bankFilter !== "all" ? bankFilter : null),

      category:
        categoryFilter && categoryFilter !== "all" ? categoryFilter : null,

      q: search,
    };
  }

  async function reloadPageProducts() {
    const params = uiParams();
    const products = await ProductDB.getAll({
      ...params,
      force: true,
    });

    $("#resultsCount") && ($("#resultsCount").textContent = products.length);
    renderProducts(products);
  }

  /* ================== MODAL (BOTTOM SLIDE) ================== */
  function initModal() {
    const modalEl = $("#productModal");
    if (!modalEl) return;

    const modal = new bootstrap.Modal(modalEl);

    const img = modalEl.querySelector(".product-img");
    const title = modalEl.querySelector(".product-title");
    const bankCat = modalEl.querySelector(".product-bank-cat");
    const activation = modalEl.querySelector(".p-activation");
    const balance = modalEl.querySelector(".p-balance");
    const security = modalEl.querySelector(".p-security");
    const tagcost = modalEl.querySelector(".p-tagcost");
    const payout = modalEl.querySelector(".p-payout");
    const price = modalEl.querySelector(".p-price");
    const desc = modalEl.querySelector(".p-desc");
    const qtyInput = modalEl.querySelector(".qty-input");
    const addBtn = modalEl.querySelector(".add-btn");

    window.showProductSheet = function (p) {
      img.src = pickLogo(p);
      title.textContent = p.name;
      bankCat.innerHTML = `
        <span class="badge ${bankBadge(p.bank)} text-white">
          ${p.bank}
        </span> • ${p.category}
      `;

      activation.textContent = `₹${p.activation || 0}`;
      balance.textContent = `₹${p.balance || 0}`;
      security.textContent = `₹${p.security || 0}`;
      tagcost.textContent = `₹${p.tagcost || 0}`;
      payout.textContent = p.payout || "-";
      price.textContent = `₹${p.price || 0}`;
      desc.textContent = p.description || "";

      qtyInput.value = 1;
      addBtn.disabled = isInCart(p);
      addBtn.textContent = isInCart(p) ? "Added" : "Add to Cart";

      addBtn.onclick = () => {
        if (isInCart(p)) return;
        addToCart(p, qtyInput.value);
        addBtn.textContent = "Added";
        addBtn.disabled = true;
      };

      modal.show();
    };
  }

  /* ================== EVENTS ================== */
  document.addEventListener("DOMContentLoaded", () => {
    if (!document.body.dataset.page) return;

    initModal();
    reloadPageProducts();

    $("#searchInput")?.addEventListener(
      "input",
      debounce(reloadPageProducts, 300),
    );
    $("#bankFilter")?.addEventListener("change", reloadPageProducts);
    $("#categoryFilter")?.addEventListener("change", reloadPageProducts);

    document.querySelectorAll(".clear-filters").forEach((btn) =>
      btn.addEventListener("click", () => {
        $("#searchInput") && ($("#searchInput").value = "");
        reloadPageProducts();
      }),
    );
  });

  document.addEventListener("click", (e) => {
    const card = e.target.closest(".product-card");
    if (!card) return;
    const p = window._productIndex?.[card.dataset.productIndex];
    p && window.showProductSheet(p);
  });
})();
