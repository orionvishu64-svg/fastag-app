// Cart functionality
class CartManager {
  constructor() {
    this.cart = this.loadCart()
    this.freeShippingThreshold = 500
    this.shippingCost = 50

    this.init()
  }

  init() {
    this.renderCart()
    this.setupEventListeners()
    this.updateCartCount()
  }

  loadCart() {
    const cart = JSON.parse(localStorage.getItem("cart") || "[]");
    return cart;
  }

  saveCart() {
    localStorage.setItem("cart", JSON.stringify(this.cart));
    this.updateCartCount();
  }

  setupEventListeners() {
    // Clear cart button
    const clearCartBtn = document.getElementById("clearCartBtn")
    if (clearCartBtn) {
      clearCartBtn.addEventListener("click", () => this.clearCart())
    }

    // Checkout button
    const checkoutBtn = document.getElementById("checkoutBtn")
    if (checkoutBtn) {
      checkoutBtn.addEventListener("click", () => this.proceedToCheckout())
    }
  }

  renderCart() {
    const emptyCart = document.getElementById("emptyCart")
    const cartContent = document.getElementById("cartContent")
    const itemsList = document.getElementById("itemsList")
    const itemCount = document.getElementById("itemCount")

    if (this.cart.length === 0) {
      emptyCart.style.display = "flex"
      cartContent.style.display = "none"
      return
    }

    // show cart content
    emptyCart.style.display = "none"
    cartContent.style.display = "block"

    // Update item count
    const totalItems = this.cart.reduce((sum, item) => sum + item.quantity, 0)
    itemCount.textContent = totalItems

    // Render cart items
    itemsList.innerHTML = ""
    this.cart.forEach((item, index) => {
      const itemElement = this.createCartItemElement(item, index)
      itemsList.appendChild(itemElement)
    })

    // Update order summary
    this.updateOrderSummary()
  }

  createCartItemElement(item, index) {
    // Define standard quantity options
    const standardQuantities = [1, 5, 10, 20, 25, 50, 100];
    // Ensure the current item's quantity is among the options, and selected
    const optionsHtml = standardQuantities.map(qty => `
      <option value="${Number(qty) || 1}">${qty}</option>
    `).join('');

    // Unique ID for the datalist
    const datalistId = `quantity-options-${item.id}-${index}`;
    const itemDiv = document.createElement("div")
    itemDiv.className = "cart-item"
    itemDiv.innerHTML = `
            <div class="item-image">
                ${item.bank}
            </div>
            <div class="item-details">
                <div class="item-name">${item.name}</div>
                <div class="item-badges">
                    <span class="item-badge bank">${item.bank}</span>
                    <span class="item-badge category">${item.category}</span>
                </div>
               <div class="item-price">₹${(typeof item.price === 'number' ? item.price : 0).toLocaleString()} </div>
                <div class="item-specs">
                    Validity: 5 years • Activation: Within 24 hours
                </div>
            </div> 
            
            <div class="item-actions">
                <div class="quantity-controls">
                    <input type="number" 
                           class="quantity-input" 
                           value="${item.quantity}" 
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
        // Optional: Add visual feedback here if the input value is invalid
        // e.g., change border color if less than 1
        if (parseInt(e.target.value, 10) < 1) {
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
        let newQuantity = parseInt(quantityInput.value, 10);

        if (isNaN(newQuantity) || newQuantity < 1) {
          window.cartManager.showCustomAlert("Please enter a valid quantity (minimum 1).");
          quantityInput.value = this.cart[index].quantity; // Revert to current saved quantity
          quantityInput.style.borderColor = ''; // Clear error visual
          return;
        }
        this.updateQuantity(index, newQuantity);
        this.showNotification(`Quantity saved as ${newQuantity}`, "success");
      });
    }

    removeBtn.addEventListener('click', (e) => {
      e.preventDefault();
      this.removeItem(index);
    });
  }

  updateQuantity(index, newQuantity) {
    if (index < 0 || index >= this.cart.length) {
      console.error('Invalid cart index:', index);
      return;
    }

    if (newQuantity <= 0) {
      this.removeItem(index)
      return
    }

    this.cart[index].quantity = newQuantity
    this.saveCart()
    this.renderCart() // Re-render to update totals and input states

    // Notification is now handled by the save button click
    // this.showNotification(`Quantity updated to ${newQuantity}`, "success")
  }

  removeItem(index) {
    const item = this.cart[index]
    this.cart.splice(index, 1)
    this.saveCart()
    this.renderCart()

    this.showNotification(`${item.bank} FASTag - ${item.name} removed from cart`, "info")
  }

  clearCart() {
    if (this.cart.length === 0) return

    // Replace confirm() with a custom modal for better UX
    this.showCustomConfirmation("Are you sure you want to clear your cart?", () => {
      this.cart = []
      this.saveCart()
      this.renderCart()

      this.showNotification("Cart cleared successfully", "info")
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

    const subtotal = this.cart.reduce((sum, item) => sum + item.price * item.quantity, 0)
    const shipping = subtotal >= this.freeShippingThreshold ? 0 : this.shippingCost
    const total = subtotal + shipping

    // Update DOM elements
    document.getElementById("subtotal").textContent = `₹${subtotal.toLocaleString()}`
    document.getElementById("shipping").textContent = shipping === 0 ? "Free" : `₹${shipping}`
    document.getElementById("total").textContent = `₹${total.toLocaleString()}`


    // Update shipping info
    const shippingInfo = document.getElementById("shippingInfo")
    const freeShippingAmount = document.getElementById("freeShippingAmount")

    if (subtotal < this.freeShippingThreshold) {
      const remaining = this.freeShippingThreshold - subtotal
      freeShippingAmount.textContent = remaining.toLocaleString()
      shippingInfo.style.display = "block"
    } else {
      shippingInfo.style.display = "none"
    }

    // Enable/disable checkout button
    const checkoutBtn = document.getElementById("checkoutBtn")
    if (checkoutBtn) {
      checkoutBtn.disabled = this.cart.length === 0
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
  fetch('get_user.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (checkoutBtn) {
        checkoutBtn.disabled = false;
        checkoutBtn.innerHTML = 'Proceed to Checkout';
      }

      if (data && data.success) {
        // Logged in → go to payment
        window.location.href = "payment.html";
      } else {
        // Not logged in → redirect to login, preserve return path
        const returnUrl = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.href = `login.html?return=${returnUrl}`;
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
      window.location.href = `login.html?return=${returnUrl}`;
    });
}

  // update cart count
  updateCartCount() {
    const totalItems = this.cart.reduce((sum, item) => sum + Math.max(1, parseInt(item.quantity || 1, 10)), 0)
    const cartCountElement = document.querySelector(".cart-count")

    if (cartCountElement) {
      cartCountElement.textContent = totalItems
      cartCountElement.style.display = totalItems > 0 ? "flex" : "none"
    }
  }

  showNotification(message, type = "info") {
    const notification = document.createElement("div")
    notification.className = `notification ${type}`
    notification.innerHTML = `
            <i class="fas fa-${type === "success" ? "check-circle" : type === "error" ? "exclamation-circle" : "info-circle"}"></i>
            <span>${message}</span>
        `

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
        `

    document.body.appendChild(notification)

    // Animate in
    setTimeout(() => {
      notification.style.transform = "translateX(0)"
    }, 100)

    // Remove after 4 seconds
    setTimeout(() => {
      notification.style.transform = "translateX(100%)"
      setTimeout(() => {
        if (document.body.contains(notification)) {
          document.body.removeChild(notification)
        }
      }, 300)
    }, 4000)
  }
}

document.addEventListener("DOMContentLoaded", function () {
  window.cartManager = new CartManager();
});