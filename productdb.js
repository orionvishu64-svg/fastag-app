/* productdb.js — rewritten
 * - Fetches products from /get_products.php
 * - Normalizes fields (incl. IMAGE → LOGO mapping)
 * - Session cache (load + save) for unfiltered lists
 * - Exposes: ProductDB.getAll(opts), ProductDB.getById(id), ProductDB.search(q), ProductDB.clearCache()
 */

const ProductDB = (function () {
  const CACHE_KEY = '__productdb_cache';
  let cache = null;
  let lastFetchedAt = 0;

  /* ---------------- helpers ---------------- */

  // Load cache from sessionStorage (if any)
  (function hydrateCache() {
    try {
      const raw = sessionStorage.getItem(CACHE_KEY);
      if (!raw) return;
      const { ts, data } = JSON.parse(raw);
      if (Array.isArray(data)) {
        cache = data;
        lastFetchedAt = ts || 0;
      }
    } catch (_) {}
  })();

  // Fetch JSON with basic error handling
  async function safeFetchJson(url, opts = {}) {
    const res = await fetch(url, { credentials: 'include', ...opts });
    if (!res.ok) {
      const text = await res.text().catch(() => '');
      throw new Error(`Network error: ${res.status} ${text}`);
    }
    const json = await res.json();
    if (!json || json.success === false) {
      throw new Error(json?.message || 'Invalid server response');
    }
    return json;
  }

  // Build URL for get_products.php with optional params
  function buildProductsUrl({ bank, category, q, limit } = {}) {
    const params = new URLSearchParams();
    if (bank) params.append('bank', bank);
    if (category) params.append('category', category);
    if (q) params.append('q', q);
    if (limit) params.append('limit', String(limit));
    const qs = params.toString();
    return 'get_products.php' + (qs ? `?${qs}` : '');
  }

  // Turn any image-ish column into a usable web URL
  function normalizeLogo(row) {
    const candidate = row.logo || row.image || row.image_url || row.image_path || '';
    if (!candidate) return '';
    // Absolute (http/https or protocol-relative) — leave as-is
    if (/^https?:\/\//i.test(candidate) || candidate.startsWith('//')) return candidate;
    // Ensure web-rooted path (leading slash)
    return candidate.startsWith('/') ? candidate : '/' + candidate;
  }

  // Normalize product fields and attach `logo`
  function normalizeProducts(rows = []) {
    return rows.map(r => ({
      id: r.id != null ? Number(r.id) : null,
      name: r.name || '',
      description: r.description || '',
      price: r.price != null ? Number(r.price) : 0,
      bank: r.bank || '',
      category: r.category || '',
      product_id: r.product_id || '',
      activation: r.activation != null ? Number(r.activation) : 0,
      security: r.security != null ? Number(r.security) : 0,
      tagcost: r.tagcost != null ? Number(r.tagcost) : 0,
      payout: r.payout || '',
      // keep original fields
      ...r,
      // critical: provide a reliable image src for the frontend
      logo: normalizeLogo(r)
    }));
  }

  /* ---------------- public API ---------------- */

  return {
    /**
     * Get products from server.
     * opts: { force, bank, category, q, limit }
     */
    async getAll(opts = {}) {
      const { force = false, bank = null, category = null, q = null, limit = 0 } = opts;

      // If unfiltered and we have a hydrated cache, return it unless forced
      const unfiltered = !bank && !category && !q;
      if (!force && unfiltered && cache) return cache;

      const url = buildProductsUrl({ bank, category, q, limit });
      const json = await safeFetchJson(url);
      const rows = normalizeProducts(json.products || []);

      // Cache only the complete list (unfiltered)
      if (unfiltered) {
        cache = rows;
        lastFetchedAt = Date.now();
        try {
          sessionStorage.setItem(CACHE_KEY, JSON.stringify({ ts: lastFetchedAt, data: cache }));
        } catch (_) {}
      }

      return rows;
    },

    /**
     * Get a single product by DB id or product_id
     */
    async getById(id) {
      const key = String(id);
      if (cache) {
        const found = cache.find(p => String(p.id) === key || String(p.product_id) === key);
        if (found) return found;
      }
      // fallback: search on server
      try {
        const rows = await this.getAll({ force: true, q: key, limit: 50 });
        return rows.find(p => String(p.id) === key || String(p.product_id) === key) || null;
      } catch (err) {
        console.error('ProductDB.getById error', err);
        return null;
      }
    },

    /**
     * Server-side search (filtered fetch)
     */
    async search(query) {
      if (!query) return [];
      return await this.getAll({ force: true, q: query, limit: 100 });
    },

    /**
     * Clear session cache
     */
    clearCache() {
      cache = null;
      lastFetchedAt = 0;
      try { sessionStorage.removeItem(CACHE_KEY); } catch (_) {}
    }
  };
})();

// Expose globally if needed
window.ProductDB = ProductDB;
