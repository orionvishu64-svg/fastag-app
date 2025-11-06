// Cart functionality
class CartManager {
  constructor() {
    this.cart = this.loadCart();
    this.freeShippingThreshold = 500;
    this.shippingCost = 50;

    this.init();
  }

  init() {
    this.renderCart();
    this.setupEventListeners();
    this.updateCartCount();
  }

  // Normalize cart on load: ensure numbers for price & quantity and consistent keys
  loadCart() {
    const raw = localStorage.getItem("cart") || "[]";
    let cart = [];
    try {
      cart = JSON.parse(raw);
      if (!Array.isArray(cart)) cart = [];
    } catch (e) {
      console.error("Invalid cart JSON in localStorage:", e, raw);
      cart = [];
    }

    const sanitizeNumber = v => {
      if (v === null || v === undefined) return NaN;
      if (typeof v === "number") return v;
      // remove anything that's not digit, dot or minus (handles commas, currency symbols)
      const cleaned = String(v).replace(/[^0-9.\-]/g, "");
      return cleaned === "" ? NaN : Number(cleaned);
    };

    cart = cart.map(item => {
      // support multiple incoming keys for quantity
      const incomingQty = item.quantity ?? item.qty ?? item.q ?? 1;
      const price = sanitizeNumber(item.price ?? item.amount ?? item.rupee_price ?? 0);
      const qty = sanitizeNumber(incomingQty);

      return {
        ...item,
        // store canonical numeric types
        price: Number.isFinite(price) ? price : 0,
        quantity: Number.isFinite(qty) && qty > 0 ? Math.floor(qty) : 1
      };
    });

    // Persist normalized cart so future loads are consistent
    try {
      localStorage.setItem("cart", JSON.stringify(cart));
    } catch (e) {
      console.warn("Failed to write normalized cart to localStorage", e);
    }

    return cart;
  }

  // Save cart ensuring numeric price & quantity are stored
  saveCart() {
    const normalized = this.cart.map(i => ({
      ...i,
      price: Number(i.price) || 0,
      quantity: Math.max(1, parseInt(i.quantity || 1, 10))
    }));

    localStorage.setItem("cart", JSON.stringify(normalized));
    this.cart = normalized; // keep in-memory consistent
    this.updateCartCount();
  }

  setupEventListeners() {
    // Clear cart button
    const clearCartBtn = document.getElementById("clearCartBtn");
    if (clearCartBtn) {
      clearCartBtn.addEventListener("click", () => this.clearCart());
    }

    // Checkout button
    const checkoutBtn = document.getElementById("checkoutBtn");
    if (checkoutBtn) {
      checkoutBtn.addEventListener("click", () => this.proceedToCheckout());
    }
  }

  renderCart() {
    const emptyCart = document.getElementById("emptyCart");
    const cartContent = document.getElementById("cartContent");
    const itemsList = document.getElementById("itemsList");
    const itemCount = document.getElementById("itemCount");

    if (!itemsList || !itemCount || !emptyCart || !cartContent) {
      console.warn("Cart DOM elements missing.");
    }

    if (this.cart.length === 0) {
      if (emptyCart) emptyCart.style.display = "flex";
      if (cartContent) cartContent.style.display = "none";
      if (itemsList) itemsList.innerHTML = "";
      if (itemCount) itemCount.textContent = "0";
      this.updateOrderSummary();
      return;
    }

    // show cart content
    if (emptyCart) emptyCart.style.display = "none";
    if (cartContent) cartContent.style.display = "block";

    // Update item count (sum of quantities)
    const totalItems = this.cart.reduce((sum, item) => {
      const q = parseInt(item.quantity || 0, 10);
      return sum + (Number.isFinite(q) ? q : 0);
    }, 0);
    if (itemCount) itemCount.textContent = totalItems;

    // Render cart items
    if (itemsList) itemsList.innerHTML = "";
    this.cart.forEach((item, index) => {
      const itemElement = this.createCartItemElement(item, index);
      if (itemsList) itemsList.appendChild(itemElement);
    });

    // Update order summary
    this.updateOrderSummary();
  }

  createCartItemElement(item, index) {
    // Define standard quantity options
    const standardQuantities = [1, 5, 10, 20, 25, 50, 100];

    // Build options: value should be the quantity number, label the same
    const optionsHtml = standardQuantities.map(qty => {
      const selected = Number(item.quantity) === qty ? 'selected' : '';
      return `<option value="${qty}" ${selected}>${qty}</option>`;
    }).join('');

    // Unique ID for the datalist (if you prefer datalist; kept for compatibility)
    const datalistId = `quantity-options-${item.id ?? 'noid'}-${index}`;
    const itemDiv = document.createElement("div");
    itemDiv.className = "cart-item";
    // Coerce price & quantity for display
    const displayPrice = Number(item.price) || 0;
    const displayQuantity = parseInt(item.quantity || 1, 10) || 1;

    itemDiv.innerHTML = `
            <div class="item-image">
                ${item.bank ?? ''}
            </div>
            <div class="item-details">
                <div class="item-name">${item.name ?? item.title ?? "Unnamed item"}</div>
                <div class="item-badges">
                    <span class="item-badge bank">${item.bank ?? ''}</span>
                    <span class="item-badge category">${item.category ?? ''}</span>
                </div>
                <div class="item-price">₹${displayPrice.toLocaleString()} </div>
            </div> 
            
            <div class="item-actions">
                <div class="quantity-controls">
                    <input type="number" 
                           class="quantity-input" 
                           value="${displayQuantity}" 
                           min="1" 
                           data-index="${index}"
                           list="${datalistId}"
                           style="height: 40px; padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; width: 80px; font-size: 1em;">
                    <datalist id="${datalistId}">
                        ${optionsHtml}
                    </datalist>
                    <button class="save-quantity-btn" data-index="${index}"
                            style="padding: 8px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9em; transition: background-color 0.2s ease;">Save</button>
                </div>
                <button class="remove-btn" data-index="${index}" title="Remove item">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;

    // Attach event listeners for the new quantity input and save button
    this.attachItemEventListeners(itemDiv, index);

    return itemDiv;
  }

  attachItemEventListeners(itemDiv, index) {
    const quantityInput = itemDiv.querySelector('.quantity-input');
    const saveQuantityBtn = itemDiv.querySelector('.save-quantity-btn');
    const removeBtn = itemDiv.querySelector('.remove-btn');

    // Add event listener for quantity input change (for immediate visual feedback, not saving)
    if (quantityInput) {
      quantityInput.addEventListener('input', (e) => {
        // Add simple validation visual feedback
        const val = parseInt(e.target.value, 10);
        if (isNaN(val) || val < 1) {
          e.target.style.borderColor = 'red';
        } else {
          e.target.style.borderColor = '#ccc'; // Reset to default
        }
      });
    }

    // Add event listener for save button click
    if (saveQuantityBtn) {
      saveQuantityBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const input = itemDiv.querySelector('.quantity-input');
        let newQuantity = parseInt(input.value, 10);

        if (isNaN(newQuantity) || newQuantity < 1) {
          this.showCustomAlert("Please enter a valid quantity (minimum 1).");
          // Revert displayed value to stored value
          input.value = this.cart[index] ? this.cart[index].quantity : 1;
          input.style.borderColor = '';
          return;
        }

        this.updateQuantity(index, newQuantity);
        this.showNotification(`Quantity saved as ${newQuantity}`, "success");
      });
    }

    if (removeBtn) {
      removeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.removeItem(index);
      });
    }
  }

  updateQuantity(index, newQuantity) {
    if (index < 0 || index >= this.cart.length) {
      console.error('Invalid cart index:', index);
      return;
    }

    if (newQuantity <= 0) {
      this.removeItem(index);
      return;
    }

    // Ensure numeric storage
    this.cart[index].quantity = Math.max(1, parseInt(newQuantity, 10));
    this.saveCart();
    this.renderCart(); // Re-render to update totals and input states
  }

  removeItem(index) {
    const item = this.cart[index];
    if (!item) return;
    this.cart.splice(index, 1);
    this.saveCart();
    this.renderCart();

    this.showNotification(`${item.bank ?? ''} FASTag - ${item.name ?? ''} removed from cart`, "info");
  }

  clearCart() {
    if (this.cart.length === 0) return;

    this.showCustomConfirmation("Are you sure you want to clear your cart?", () => {
      this.cart = [];
      this.saveCart();
      this.renderCart();

      this.showNotification("Cart cleared successfully", "info");
    });
  }

  // Custom confirmation modal (replaces alert/confirm)
  showCustomConfirmation(message, onConfirm) {
    const modalId = 'customConfirmationModal';
    let modal = document.getElementById(modalId);

    if (!modal) {
      modal = document.createElement('div');
      modal.id = modalId;
      modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        `;
      modal.innerHTML = `
            <div style="
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 400px;
                width: 90%;
            ">
                <p style="font-size: 1.1em; margin-bottom: 25px;">${message}</p>
                <div style="display: flex; justify-content: center; gap: 15px;">
                    <button id="confirmYes" style="
                        background: #10b981;
                        color: white;
                        padding: 12px 25px;
                        border: none;
                        border-radius: 8px;
                        cursor: pointer;
                        font-size: 1em;
                        transition: background 0.3s ease;
                    ">Yes</button>
                    <button id="confirmNo" style="
                        background: #ef4444;
                        color: white;
                        padding: 12px 25px;
                        border: none;
                        border-radius: 8px;
                        cursor: pointer;
                        font-size: 1em;
                        transition: background 0.3s ease;
                    ">No</button>
                </div>
            </div>
        `;
      document.body.appendChild(modal);
    } else {
      modal.querySelector('p').textContent = message;
      modal.style.display = 'flex';
    }

    const confirmYes = modal.querySelector('#confirmYes');
    const confirmNo = modal.querySelector('#confirmNo');

    // Remove existing listeners to prevent duplicates
    const newConfirmYes = confirmYes.cloneNode(true);
    confirmYes.parentNode.replaceChild(newConfirmYes, confirmYes);
    const newConfirmNo = confirmNo.cloneNode(true);
    confirmNo.parentNode.replaceChild(newConfirmNo, confirmNo);

    newConfirmYes.addEventListener('click', () => {
      onConfirm();
      modal.style.display = 'none';
    });
    newConfirmNo.addEventListener('click', () => {
      modal.style.display = 'none';
    });
  }

  // Custom alert modal (replaces alert)
  showCustomAlert(message) {
    const modalId = 'customAlertModal';
    let modal = document.getElementById(modalId);

    if (!modal) {
      modal = document.createElement('div');
      modal.id = modalId;
      modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        `;
      modal.innerHTML = `
            <div style="
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 400px;
                width: 90%;
            ">
                <p style="font-size: 1.1em; margin-bottom: 25px;">${message}</p>
                <button id="alertOk" style="
                    background: #3b82f6;
                    color: white;
                    padding: 12px 25px;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 1em;
                    transition: background 0.3s ease;
                ">OK</button>
            </div>
        `;
      document.body.appendChild(modal);
    } else {
      modal.querySelector('p').textContent = message;
      modal.style.display = 'flex';
    }

    const alertOk = modal.querySelector('#alertOk');
    const newAlertOk = alertOk.cloneNode(true);
    alertOk.parentNode.replaceChild(newAlertOk, alertOk);

    newAlertOk.addEventListener('click', () => {
      modal.style.display = 'none';
    });
  }

  updateOrderSummary() {
    // Make sure to coerce to numbers before arithmetic
    const subtotal = this.cart.reduce((sum, item) => {
      const price = Number(item.price) || 0;
      const qty = Number(item.quantity) || 0;
      return sum + price * qty;
    }, 0);

    const shipping = subtotal >= this.freeShippingThreshold ? 0 : this.shippingCost;
    const total = subtotal + shipping;

    // Update DOM elements (guard in case missing)
    const subtotalEl = document.getElementById("subtotal");
    const shippingEl = document.getElementById("shipping");
    const totalEl = document.getElementById("total");

    if (subtotalEl) subtotalEl.textContent = `₹${(Number.isFinite(subtotal) ? subtotal : 0).toLocaleString()}`;
    if (shippingEl) shippingEl.textContent = shipping === 0 ? "Free" : `₹${shipping.toLocaleString()}`;
    if (totalEl) totalEl.textContent = `₹${(Number.isFinite(total) ? total : 0).toLocaleString()}`;

    // Update shipping info
    const shippingInfo = document.getElementById("shippingInfo");
    const freeShippingAmount = document.getElementById("freeShippingAmount");

    if (shippingInfo && freeShippingAmount) {
      if (subtotal < this.freeShippingThreshold) {
        const remaining = this.freeShippingThreshold - subtotal;
        freeShippingAmount.textContent = remaining.toLocaleString();
        shippingInfo.style.display = "block";
      } else {
        shippingInfo.style.display = "none";
      }
    }

    // Enable/disable checkout button
    const checkoutBtn = document.getElementById("checkoutBtn");
    if (checkoutBtn) {
      checkoutBtn.disabled = this.cart.length === 0;
    }
  }

  // replace proceedToCheckout() in cart.js with this:
  proceedToCheckout() {
    if (this.cart.length === 0) {
      this.showNotification("Your cart is empty", "error");
      return;
    }

    const checkoutBtn = document.getElementById("checkoutBtn");
    if (checkoutBtn) {
      checkoutBtn.disabled = true;
      checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    }

    // Check server-side session to verify login (more reliable than localStorage)
    fetch('/config/get_user.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (checkoutBtn) {
          checkoutBtn.disabled = false;
          checkoutBtn.innerHTML = 'Proceed to Checkout';
        }

        if (data && data.success) {
          // Logged in → go to payment
          window.location.href = "payment.php";
        } else {
          // Not logged in → redirect to login, preserve return path
          const returnUrl = encodeURIComponent(window.location.pathname + window.location.search);
          window.location.href = `index.html?return=${returnUrl}`;
        }
      })
      .catch(err => {
        console.error('Session check failed', err);
        if (checkoutBtn) {
          checkoutBtn.disabled = false;
          checkoutBtn.innerHTML = 'Proceed to Checkout';
        }
        // Safe fallback: send user to login page
        const returnUrl = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.href = `index.html?return=${returnUrl}`;
      });
  }

  // update cart count
  updateCartCount() {
    const totalItems = this.cart.reduce((sum, item) => {
      const q = Math.max(1, parseInt(item.quantity || 1, 10));
      return sum + (Number.isFinite(q) ? q : 0);
    }, 0);

    const cartCountElement = document.querySelector(".cart-count");

    if (cartCountElement) {
      cartCountElement.textContent = totalItems;
      cartCountElement.style.display = totalItems > 0 ? "flex" : "none";
    }
  }

  showNotification(message, type = "info") {
    const notification = document.createElement("div");
    notification.className = `notification ${type}`;
    notification.innerHTML = `
            <i class="fas fa-${type === "success" ? "check-circle" : type === "error" ? "exclamation-circle" : "info-circle"}"></i>
            <span>${message}</span>
        `;

    notification.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: ${type === "success" ? "#10aeb9ff" : type === "error" ? "#f36363ff" : "#2b69ceff"};
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 350px;
        `;

    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
      notification.style.transform = "translateX(0)";
    }, 100);

    // Remove after 4 seconds
    setTimeout(() => {
      notification.style.transform = "translateX(100%)";
      setTimeout(() => {
        if (document.body.contains(notification)) {
          document.body.removeChild(notification);
        }
      }, 300);
    }, 4000);
  }
}

document.addEventListener("DOMContentLoaded", function () {
  window.cartManager = new CartManager();
});
