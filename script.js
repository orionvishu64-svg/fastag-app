// drop-in replacement
function safeFetch(...args) {
  return fetch(...args)
    .then(response => {
      if (!response.ok) {
        const err = new Error("HTTP " + response.status);
        err.response = response;
        throw err;
      }
      return response;
    })
    .catch(err => {
      try { console.error("Network error:", err); } catch (e) {}
      try { if (typeof alert !== 'undefined') alert("Network error. Please try again."); } catch (e) {}
      throw err;
    });
}

// Mobile Navigation Toggle
document.addEventListener("DOMContentLoaded", () => {
  const hamburger = document.querySelector(".hamburger")
  const Menu = document.querySelector(".hamburger-menu")
 if (hamburger && Menu) {
    hamburger.addEventListener("click", () => {
      Menu.classList.toggle("open")
    });
   }
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (e) {
    const href = this.getAttribute("href")

    // Skip empty "#" links (like Logout or JS handlers)
    if (!href || href === "#") {
      return
    }

    e.preventDefault()
    const target = document.querySelector(href)
    if (target) {
      target.scrollIntoView({
        behavior: "smooth",
        block: "start",
      })
    }
  })
})

  // Fade in animation on scroll
  const observerOptions = {
    threshold: 0.1,
    rootMargin: "0px 0px -50px 0px",
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("visible")
      }
    })
  }, observerOptions)

  // Observe elements for fade-in animation
  document.querySelectorAll(".feature-card, .bank-card, .post-card").forEach((el) => {
    el.classList.add("fade-in")
    observer.observe(el)
  })

  // Newsletter form submission
  const newsletterForm = document.querySelector(".newsletter-form")
  if (newsletterForm) {
    newsletterForm.addEventListener("submit", function (e) {
      e.preventDefault()
      const email = this.querySelector('input[type="email"]').value

      if (email) {
        // Show loading state
        const button = this.querySelector("button")
        const originalText = button.textContent
        button.innerHTML = '<span class="loading"></span> Subscribing...'
        button.disabled = true

        // Simulate API call
        setTimeout(() => {
          alert("Thank you for subscribing to our newsletter!")
          this.reset()
          button.textContent = originalText
          button.disabled = false
        }, 2000)
      }
    })
  }


  // Cart functionality
  let cartCount = 0
  const cartCountElement = document.querySelector(".cart-count")

  // Add to cart buttons
  document.querySelectorAll(".btn").forEach((button) => {
    if (button.textContent.includes("Add to Cart") || button.textContent.includes("Select")) {
      button.addEventListener("click", function (e) {
        if (this.textContent.includes("Add to Cart")) {
          e.preventDefault()
         // Try to find the product element (customize selectors as needed)
  const productCard = this.closest(".product-card") || this.closest(".bank-card") || this.closest(".category-card")

  if (productCard) {
    const name = productCard.querySelector(".product-name")?.textContent?.trim() || "Unknown Product"
    const priceText = productCard.querySelector(".product-price, .price")?.textContent?.replace(/[^\d]/g, "")
    const price = parseInt(priceText, 10) || 0

    const item = {
      id: Date.now().toString(),
      name,
      price,
      quantity: 1
    }

    // Get current cart from localStorage
    const existingCart = JSON.parse(localStorage.getItem("cart")) || []
    existingCart.push(item)
    localStorage.setItem("cart", JSON.stringify(existingCart))

    cartCount = existingCart.length
    if (cartCountElement) {
      cartCountElement.textContent = cartCount
      cartCountElement.style.display = "flex"
    }

    showNotification(`${name} added to cart!`, "success")
  } else {
    showNotification("Error: Could not identify product", "error")
  }
} 
    })
        }
    })

  // Show notification function
  function showNotification(message, type = "info") {
    const notification = document.createElement("div")
    notification.className = `notification ${type}`
    notification.textContent = message
    notification.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: ${type === "success" ? "#10b981" : "#3b82f6"};
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `

    document.body.appendChild(notification)

    // Animate in
    setTimeout(() => {
      notification.style.transform = "translateX(0)"
    }, 100)

    // Remove after 3 seconds
    setTimeout(() => {
      notification.style.transform = "translateX(100%)"
      setTimeout(() => {
        document.body.removeChild(notification)
      }, 300)
    }, 3000)
  }

  // Initialize cart count display
  if (cartCountElement) {
    cartCountElement.style.display = cartCount > 0 ? "flex" : "none"
  }

  // Back to top button
  const backToTopButton = document.createElement("button")
  backToTopButton.innerHTML = '<i class="fas fa-arrow-up"></i>'
  backToTopButton.className = "back-to-top"
  backToTopButton.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
        z-index: 1000;
    `

  document.body.appendChild(backToTopButton)

  // Show/hide back to top button
  window.addEventListener("scroll", () => {
    if (window.pageYOffset > 300) {
      backToTopButton.style.display = "flex"
    } else {
      backToTopButton.style.display = "none"
    }
  })

  // Back to top functionality
  backToTopButton.addEventListener("click", () => {
    window.scrollTo({
      top: 0,
      behavior: "smooth",
    })
  })

  // Form validation
  document.querySelectorAll("form").forEach((form) => {
    form.addEventListener("submit", function (e) {
      const requiredFields = this.querySelectorAll("[required]")
      let isValid = true

      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          isValid = false
          field.style.borderColor = "#ef4444"
          field.addEventListener(
            "input",
            function () {
              this.style.borderColor = ""
            },
            { once: true },
          )
        }
      })

      if (!isValid) {
        e.preventDefault()
        showNotification("Please fill in all required fields", "error")
      }
    })
  })

// Utility functions
function formatCurrency(amount) {
  return new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: "INR",
  }).format(amount)
}

function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

// Search functionality (if needed)
function initializeSearch() {
  const searchInput = document.querySelector("#search")
  if (searchInput) {
    const debouncedSearch = debounce((query) => {
      // Implement search logic here
      console.log("Searching for:", query)
    }, 300)

    searchInput.addEventListener("input", function () {
      debouncedSearch(this.value)
    })
  }
}

// Initialize search on page load
document.addEventListener("DOMContentLoaded", initializeSearch)

// Update navbar login button based on auth state
function getLoggedInEmail() {
  try {
    const storedUserJson = localStorage.getItem("user")
    if (storedUserJson) {
      const storedUser = JSON.parse(storedUserJson)
      if (storedUser && storedUser.email) {
        return storedUser.email
      }
    }
  } catch (_) {}
  const fallbackEmail = localStorage.getItem("userEmail")
  return fallbackEmail || null
}

function updateLoginButton() {
  const loginButton = document.querySelector(".login-btn")
  if (!loginButton) return

  const email = getLoggedInEmail()
  if (email) {
    loginButton.setAttribute("href", "profile.html")
    loginButton.innerHTML = `<i class="far fa-user-circle"></i>`
  } else {
    loginButton.setAttribute("href", "login.html")
    loginButton.innerHTML = `<i class="far fa-user-circle"></i>`
  }
}

document.addEventListener("DOMContentLoaded", updateLoginButton)

// Wire global logout on all pages that include the navbar
function wireGlobalLogout() {
  const logoutLink = document.getElementById("nav-logout")
  if (!logoutLink) return

  logoutLink.addEventListener("click", async (e) => {
    e.preventDefault()
    try {
      // Clear client-side auth state
      localStorage.removeItem("user")
      localStorage.removeItem("userEmail")
      // Invalidate server session
      await safeFetch("logout.php", { credentials: "include" })
    } catch (_) {}

    // Redirect to a safe page
    if (window.top) {
      window.top.location.href = "index.html"
    } else {
      window.location.href = "index.html"
    }
  })
}

document.addEventListener("DOMContentLoaded", wireGlobalLogout)

// Also wire any explicit logout button on profile page to same flow
function wireProfileLogoutButton() {
  const explicitLogoutBtn = document.querySelector(".logout-btn")
  if (!explicitLogoutBtn) return

  explicitLogoutBtn.addEventListener("click", async (e) => {
    e.preventDefault()
    try {
      localStorage.removeItem("user")
      localStorage.removeItem("userEmail")
      await safeFetch("logout.php", { credentials: "include" })
    } catch (_) {}
    if (window.top) {
      window.top.location.href = "index.html"
    } else {
      window.location.href = "index.html"
    }
  })
}

document.addEventListener("DOMContentLoaded", wireProfileLogoutButton)