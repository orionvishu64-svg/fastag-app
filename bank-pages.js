/* bank-pages.js
 *
 * Logic for bank-specific pages (SBI, Bajaj, Kotak, IDFC, etc.)
 * - Detects bank for the page
 * - Wires category / VC buttons to filter products by category+bank
 * - Keeps page-specific UI (benefits, notices) unchanged
 *
 * Expected HTML:
 *  - Category buttons/cards should have data-category attribute (e.g. data-category="VC4")
 *  - body may include data-bank="SBI" to explicitly set bank for page
 *  - product grid container same as products.js (no change)
 *
 * Usage:
 *  - Include after productdb.js and products.js.
 */

(function () {
  if (!window.ProductDB || !window.reloadProducts) {
    console.error('bank-pages.js requires productdb.js and products.js');
    return;
  }

  // Detect bank from body attribute or filename
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

  // Safe selector with CSS.escape
  function qsSafe(selector, root = document) {
    try {
      return root.querySelector(selector);
    } catch (e) {
      return null;
    }
  }

  // Wire category cards/buttons on page
  function wireCategoryButtons() {
    // Buttons should have class .category-card and data-category attribute
    const buttons = Array.from(document.querySelectorAll('.category-card'));
    if (!buttons.length) return;

    const pageBank = detectBankFromPage();

    function clearActive() {
      buttons.forEach(b => b.classList.remove('active'));
    }

    buttons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const cat = btn.dataset.category || btn.getAttribute('data-category');
        if (!cat) {
          // If category is not set, toggle show all for bank
          clearActive();
          btn.classList.add('active');
          window.reloadProducts({ bank: pageBank }); // show all bank products
          return;
        }
        // apply active state and reload products for bank+category
        clearActive();
        btn.classList.add('active');
        window.reloadProducts({ bank: pageBank, category: cat });
      });
    });
  }

  // Wire search within bank page (if you have an input with id #bank-search)
  function wireSearch() {
    const search = document.querySelector('#bank-search');
    if (!search) return;
    let timeout = null;
    const bank = detectBankFromPage();

    search.addEventListener('input', (e) => {
      const q = e.target.value || '';
      if (timeout) clearTimeout(timeout);
      timeout = setTimeout(() => {
        window.reloadProducts({ bank, q });
      }, 300);
    });
  }

  // Initial load and wiring
  document.addEventListener('DOMContentLoaded', () => {
    const bank = detectBankFromPage();
    // Load all products for this bank initially
    window.reloadProducts({ bank });

    // Wire categories, search (if present)
    wireCategoryButtons();
    wireSearch();
  });
})();