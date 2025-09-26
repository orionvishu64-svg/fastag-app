/* productdb.js
 * - Client-side product data layer.
 * - Fetches products from /get_products.php
 * - Caches full-list for the session to avoid refetching repeatedly
 * - Exposes: ProductDB.getAll(opts), ProductDB.getById(id), ProductDB.search(q)
 * - Usage:
 *   const products = await ProductDB.getAll({ bank: 'SBI', category: 'VC4' });
 *   const p = await ProductDB.getById(12);
 */

const ProductDB = (function () {
  const CACHE_KEY = '__productdb_cache';
  let cache = null;              // cached array (null until fetched)
  let lastFetchedAt = 0;

  // Small wrapper fetch that includes credentials and basic error handling
  async function safeFetchJson(url, opts = {}) {
    const res = await fetch(url, Object.assign({ credentials: 'include' }, opts));
    if (!res.ok) {
      const text = await res.text().catch(() => '');
      throw new Error(`Network error: ${res.status} ${text}`);
    }
    const json = await res.json();
    if (!json || json.success === false) {
      throw new Error(json && json.message ? json.message : 'Invalid server response');
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

  // Normalize product fields (casts numbers)
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
      // any extra fields remain as-is
      ...r
    }));
  }

  return {
    /**
     * Get products from server.
     * opts: { force, bank, category, q, limit }
     */
    async getAll(opts = {}) {
      const { force = false, bank = null, category = null, q = null, limit = 0 } = opts;

      // If request is unfiltered and we have cache and not forcing -> return cache
      if (!force && !bank && !category && !q && cache) {
        return cache;
      }

      const url = buildProductsUrl({ bank, category, q, limit });

      const json = await safeFetchJson(url);
      const rows = normalizeProducts(json.products || []);

      // Cache only if unfiltered (complete list)
      if (!bank && !category && !q) {
        cache = rows;
        lastFetchedAt = Date.now();
        try {
          sessionStorage.setItem(CACHE_KEY, JSON.stringify({ ts: lastFetchedAt, data: cache }));
        } catch (_) {}
      }

      return rows;
    },

    /**
     * Get product by DB id or product_id
     * id can be numeric DB id or product_id string
     */
    async getById(id) {
      // check cache first (if present)
      if (cache) {
        const found = cache.find(p => String(p.id) === String(id) || String(p.product_id) === String(id));
        if (found) return found;
      }

      // fallback: fetch single product by searching product_id or id
      // We'll call server search (force)
      try {
        const q = String(id);
        const rows = await this.getAll({ force: true, q, limit: 50 });
        const found = rows.find(p => String(p.id) === String(id) || String(p.product_id) === String(id));
        return found || null;
      } catch (err) {
        console.error('ProductDB.getById error', err);
        return null;
      }
    },

    /**
     * Server-side search (returns results)
     */
    async search(query) {
      if (!query) return [];
      return await this.getAll({ force: true, q: query, limit: 100 });
    },

    /**
     * Clear cached product list (useful after updating DB)
     */
    clearCache() {
      cache = null;
      lastFetchedAt = 0;
      try { sessionStorage.removeItem(CACHE_KEY); } catch (_) {}
    }
  };
})();

// Expose to global (if needed)
window.ProductDB = ProductDB;
