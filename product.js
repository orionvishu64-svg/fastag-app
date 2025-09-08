// products.js — DB-driven product renderer (uses product_id)
(function () {
  function qs(s, r=document){ return r.querySelector(s); }
  function fmt(n){ return Number(n).toFixed(2); }

  const bank = document.body.dataset.bank || null;
  const container = qs('#products-container') || (function(){ const d=document.createElement('div'); d.id='products-container'; document.body.appendChild(d); return d; })();

  const addedKey = 'myshop_added_ids_v1';
  function getAdded() { try { return JSON.parse(localStorage.getItem(addedKey) || '[]'); } catch(e) { return []; } }
  function saveAdded(arr) { localStorage.setItem(addedKey, JSON.stringify(arr)); }

  function createCard(p) {
    const card = document.createElement('div'); card.className='product-card'; card.dataset.pid = p.product_id || '';
    const top = document.createElement('div'); top.className='product-top';
    const name = document.createElement('h3'); name.textContent = p.name;
    const cat = document.createElement('div'); cat.className='product-cat'; cat.textContent = p.category || '';
    top.appendChild(name); top.appendChild(cat);
    const desc = document.createElement('p'); desc.className='product-desc'; desc.textContent = p.description || '';
    const price = document.createElement('div'); price.className='product-price'; price.textContent = '₹ ' + fmt(p.price || 0);
    const btn = document.createElement('button'); btn.type='button'; btn.className='add-cart-btn'; btn.dataset.productId = p.product_id; btn.textContent = 'Add to cart';

    // restore "added" state
    const arr = getAdded();
    if (arr.indexOf(p.product_id) !== -1) { btn.classList.add('added'); btn.textContent = 'Added'; }

    btn.addEventListener('click', function () {
      const id = this.dataset.productId;
      if (!id) return;
      this.disabled = true;
      fetch('cart.php?action=add', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ product_id: id, qty: 1 })
      })
      .then(r => r.json())
      .then(data => {
        if (data && data.success) {
          btn.classList.add('added');
          btn.textContent = 'Added';
          const cur = getAdded();
          if (cur.indexOf(id) === -1) { cur.push(id); saveAdded(cur); }
          // notify cart UI
          window.dispatchEvent(new CustomEvent('cart-updated'));
        } else {
          alert((data && data.error) ? data.error : 'Could not add to cart');
        }
      })
      .catch(err => { console.error(err); alert('Network error'); })
      .finally(() => this.disabled = false);
    });

    card.appendChild(top); card.appendChild(desc); card.appendChild(price); card.appendChild(btn);
    return card;
  }

  function render(list) {
    container.innerHTML = '';
    if (!list || list.length === 0) { container.innerHTML = '<p class="no-products">No products found.</p>'; return; }
    const grid = document.createElement('div'); grid.className = 'products-grid';
    list.forEach(p => grid.appendChild(createCard(p)));
    container.appendChild(grid);
  }

  function fetchProducts() {
    let url = 'get_products.php';
    if (bank) url += '?bank=' + encodeURIComponent(bank);
    fetch(url)
      .then(r => r.json())
      .then(json => {
        if (Array.isArray(json)) render(json);
        else { console.error('Unexpected products response', json); render([]); }
      })
      .catch(err => { console.error('Fetch error', err); container.innerHTML = '<p class="error">Unable to load products.</p>'; });
  }

  document.addEventListener('DOMContentLoaded', fetchProducts);
})();
