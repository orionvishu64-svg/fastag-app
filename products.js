// Products data
const productsData = [
  /* SBI Products
  {
    id: "sbi-vc4",
    bank: "SBI",
    category: "VC4",
    name: "Car/Jeep/Van",
    price: 400,
    description: "For private cars, jeeps, and vans",
  },
  { id: "sbi-vc5", 
    bank: "SBI", 
    category: "VC5", 
    name: "LCV", 
    price: 400, 
    description: "Light Commercial Vehicle" },
  {
    id: "sbi-vc6",
    bank: "SBI",
    category: "VC6",
    name: "Bus/Truck",
    price: 400,
    description: "For buses and trucks (2 axle)",
  },
  {
    id: "sbi-vc7",
    bank: "SBI",
    category: "VC7",
    name: "Heavy Vehicle",
    price: 400,
    description: "Heavy Commercial Vehicle (3 axle)",
  },
  {
    id: "sbi-vc8",
    bank: "SBI",
    category: "VC8",
    name: "Construction Vehicle",
    price: 400,
    description: "Construction equipment vehicle",
  },
  {
    id: "sbi-vc12",
    bank: "SBI",
    category: "VC12",
    name: "Mini Bus",
    price: 400,
    description: "Mini bus and small commercial vehicles",
  },  */

  /* Bajaj Products
  {
    id: "bajaj-vc4",
    bank: "Bajaj",
    category: "VC4",
    name: "Car/Jeep/Van",
    price: 400,
    description: "For private cars, jeeps, and vans",
  },
  { id: "bajaj-vc5",
    bank: "Bajaj", 
    category: "VC5", 
    name: "LCV", 
    price: 400, 
    description: "Light Commercial Vehicle" },
  {
    id: "bajaj-vc6",
    bank: "Bajaj",
    category: "VC6",
    name: "Bus/Truck",
    price: 400,
    description: "For buses and trucks (2 axle)",
  },
  {
    id: "bajaj-vc7",
    bank: "Bajaj",
    category: "VC7",
    name: "Heavy Vehicle",
    price: 400,
    description: "Heavy Commercial Vehicle (3 axle)",
  },
  {
    id: "bajaj-vc8",
    bank: "Bajaj",
    category: "VC8",
    name: "Construction Vehicle",
    price: 400,
    description: "Construction equipment vehicle",
  },
  {
    id: "bajaj-vc12",
    bank: "Bajaj",
    category: "VC12",
    name: "Mini Bus",
    price: 400,
    description: "Mini bus and small commercial vehicles",
  },  */

  /* IDFC Products
  {
    id: "idfc-vc4",
    bank: "IDFC",
    category: "VC4",
    name: "Car/Jeep/Van",
    price: 400,
    description: "For private cars, jeeps, and vans",
  },
  {
    id: "idfc-vc4max",
    bank: "IDFC",
    category: "VC4 max",
    name: "Car/Jeep/Van",
    price: 500,
    description: "For private cars, jeeps, and vans",
  },
  { id: "idfc-vc5", 
    bank: "IDFC", 
    category: "VC5", 
    name: "LCV", 
    price: 400, 
    description: "Light Commercial Vehicle" },
  {
    id: "idfc-vc6",
    bank: "IDFC",
    category: "VC6",
    name: "Bus/Truck",
    price: 400,
    description: "For buses and trucks (2 axle)",
  },
  {
    id: "idfc-vc7",
    bank: "IDFC",
    category: "VC7",
    name: "Heavy Vehicle",
    price: 400,
    description: "Heavy Commercial Vehicle (3 axle)",
  },
  {
    id: "idfc-vc8",
    bank: "IDFC",
    category: "VC8",
    name: "Construction Vehicle",
    price: 400,
    description: "Construction equipment vehicle",
  },
  {
    id: "idfc-vc12",
    bank: "IDFC",
    category: "VC12",
    name: "Mini Bus",
    price: 400,
    description: "Mini bus and small commercial vehicles",
  },  */

  /* Kotak Products (excluding VC4)
  { id: "kotak-vc5", 
    bank: "Kotak", 
    category: "VC5", 
    name: "LCV", 
    price: 400, 
    description: "Light Commercial Vehicle" },
  {
    id: "kotak-vc6",
    bank: "Kotak",
    category: "VC6",
    name: "Bus/Truck",
    price: 400,
    description: "For buses and trucks (2 axle)",
  },
  {
    id: "kotak-vc7",
    bank: "Kotak",
    category: "VC7",
    name: "Heavy Vehicle",
    price: 400,
    description: "Heavy Commercial Vehicle (3 axle)",
  },
  {
    id: "kotak-vc8",
    bank: "Kotak",
    category: "VC8",
    name: "Construction Vehicle",
    price: 400,
    description: "Construction equipment vehicle",
  },
  {
    id: "kotak-vc12",
    bank: "Kotak",
    category: "VC12",
    name: "Mini Bus",
    price: 400,
    description: "Mini bus and small commercial vehicles",
  },  */
]
   
// Global variables
let filteredProducts = [...productsData]
let currentFilters = {
  search: "",
  bank: "all",
  category: "all",
}

// DOM elements
const searchInput = document.getElementById("searchInput")
const bankFilter = document.getElementById("bankFilter")
const categoryFilter = document.getElementById("categoryFilter")
const productsGrid = document.getElementById("productsGrid")
const resultsCount = document.getElementById("resultsCount")
const noResults = document.getElementById("noResults")

// Initialize products page
document.addEventListener("DOMContentLoaded", () => {
  console.log("DOM fully loaded"); // Debug point
  renderProducts();
  setupEventListeners();
  updateCartCount(); // Ensure cart count is updated on load
  syncAddToCartButtons(); // Synchronize button states on load
});

// Setup event listeners
function setupEventListeners() {
  // Search input with debounce
  let searchTimeout
  searchInput.addEventListener("input", function () {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
      currentFilters.search = this.value.toLowerCase()
      filterProducts()
    }, 300)
  })

  // Bank filter
  bankFilter.addEventListener("change", function () {
    currentFilters.bank = this.value
    filterProducts()
  })

  // Category filter
  categoryFilter.addEventListener("change", function () {
    currentFilters.category = this.value
    filterProducts()
  })

  // Listen for changes in localStorage 'cart' key to update button states across tabs/windows
  window.addEventListener('storage', (event) => {
    if (event.key === 'cart') {
      updateCartCount(); // Update cart count in navbar
      syncAddToCartButtons(); // Synchronize product button states
    }
  });
}

// Filter products based on current filters
function filterProducts() {
  filteredProducts = productsData.filter((product) => {
    const matchesSearch =
      currentFilters.search === "" ||
      product.name.toLowerCase().includes(currentFilters.search) ||
      product.description.toLowerCase().includes(currentFilters.search) ||
      product.bank.toLowerCase().includes(currentFilters.search)

    const matchesBank =
      currentFilters.bank === "all" ||
      product.bank.toLowerCase() === currentFilters.bank.toLowerCase();

    const matchesCategory =
      currentFilters.category === "all" ||
      product.category.toLowerCase() === currentFilters.category.toLowerCase();

    return matchesSearch && matchesBank && matchesCategory
  })
console.log("Filtered products:", filteredProducts); // Debug point
  renderProducts()
}

// Render products
function renderProducts() {
  // Update results count
  resultsCount.textContent = filteredProducts.length

  // Show/hide no results message
  if (filteredProducts.length === 0) {
    productsGrid.style.display = "none"
    noResults.style.display = "block"
    return
  } else {
    productsGrid.style.display = "grid"
    noResults.style.display = "none"
  }

  // Clear existing products
  productsGrid.innerHTML = ""

  // Render each product
  filteredProducts.forEach((product) => {
    const productCard = createProductCard(product)
    productsGrid.appendChild(productCard)
  })

  // Add fade-in animation
  const productCards = productsGrid.querySelectorAll(".product-card")
  productCards.forEach((card, index) => {
    card.style.opacity = "0"
    card.style.transform = "translateY(20px)"
    setTimeout(() => {
      card.style.transition = "all 0.3s ease"
      card.style.opacity = "1"
      card.style.transform = "translateY(0)"
    }, index * 50)
  })

  // After rendering, ensure button states are correct
  syncAddToCartButtons();
}

// Create product card HTML
function createProductCard(product) {
  const card = document.createElement("div")
  card.className = `product-card ${product.bank.toLowerCase()}`

  card.innerHTML = `
    <div class="product-header">
      <div class="product-bank-info">
        <div class="bank-logo-section">
          <div class="bank-logo">${product.bank}</div>
          <span class="bank-name">${product.bank}</span>
        </div>
        <span class="vehicle-category">${product.category}</span>
      </div>
      <h3 class="product-title">${product.name}</h3>
      <p class="product-description">${product.description}</p>
    </div>
    
    <div class="product-content">
      <div class="price-section">
        <div class="price">₹${product.price.toLocaleString()}</div>
        <div class="price-note">Inclusive of all charges</div>
      </div>
      
      <div class="product-details">
        <div class="detail-row">
          <span class="detail-label">Bank:</span>
          <span class="detail-value">${product.bank}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Activation:</span>
          <span class="detail-value">Within 24 hours</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Validity:</span>
          <span class="detail-value">5 years</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Recharge:</span>
          <span class="detail-value">Online/Offline</span>
        </div>
      </div>
    </div>
    
    <div class="product-footer">
      <button class="add-to-cart-btn" data-product-id="${product.id}" onclick="addToCart('${product.id}')">
        <i class="fas fa-shopping-cart"></i>
        Add to Cart
      </button>
    </div>
  `;

  // Attach the product ID to the button for easy lookup
  const addToCartBtn = card.querySelector('.add-to-cart-btn');
  addToCartBtn.dataset.productId = product.id;

  return card;
}

// Function to synchronize the state of "Add to Cart" buttons
function syncAddToCartButtons() {
  const cart = JSON.parse(localStorage.getItem("cart") || "[]");
  const cartProductIds = new Set(cart.map(item => item.id));

  document.querySelectorAll(".add-to-cart-btn").forEach(button => {
    const productId = button.dataset.productId;
    if (cartProductIds.has(productId)) {
      button.innerHTML = '<i class="fas fa-check"></i> Added!';
      button.style.background = "#10b981"; // Green color
      button.disabled = true;
    } else {
      button.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
      button.style.background = ""; // Reset to default
      button.disabled = false;
    }
  });
}


// Add to cart function
function addToCart(productId) {
  const product = productsData.find((p) => p.id === productId)
  if (!product) return

  // Get current cart from localStorage or initialize empty array
  const cart = JSON.parse(localStorage.getItem("cart") || "[]")

  // Check if product already exists in cart
  const existingItem = cart.find((item) => item.id === productId)

  if (existingItem) {
    existingItem.quantity += 1
  } else {
    cart.push({
      ...product,
      quantity: 1,
      bank: product.bank,
      addedAt: new Date().toISOString(),
    })
  }

  // Save to localStorage
  localStorage.setItem("cart", JSON.stringify(cart))

  // Update cart count in navbar
  updateCartCount()

  // Show success notification
  showNotification(`${product.bank} FASTag - ${product.name} added to cart!`, "success")

  // Update the button state immediately and persistently
  syncAddToCartButtons();
}

// Update cart count
function updateCartCount() {
  const cart = JSON.parse(localStorage.getItem("cart") || "[]")
  const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0)

  const cartCountElement = document.querySelector(".cart-count")
  if (cartCountElement) {
    cartCountElement.textContent = totalItems
    cartCountElement.style.display = totalItems > 0 ? "flex" : "none"
  }
}

// Clear all filters
function clearAllFilters() {
  currentFilters = {
    search: "",
    bank: "all",
    category: "all",
  }

  searchInput.value = ""
  bankFilter.value = "all"
  categoryFilter.value = "all"

  filterProducts()
}

// Show notification function
function showNotification(message, type = "info") {
  const notification = document.createElement("div")
  notification.className = `notification ${type}`
  notification.innerHTML = `
    <i class="fas fa-${type === "success" ? "check-circle" : "info-circle"}"></i>
    <span>${message}</span>
  `

  notification.style.cssText = `
    position: fixed;
    top: 100px;
    right: 20px;
    background: ${type === "success" ? "#10b981" : "#3b82f6"};
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
    max-width: 300px;
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

// Export functions for global access
window.addToCart = addToCart
window.clearAllFilters = clearAllFilters
window.updateCartCount = updateCartCount;