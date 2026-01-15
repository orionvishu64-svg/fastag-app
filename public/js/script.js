// drop-in replacement
function safeFetch(...args) {
  return fetch(...args)
    .then((response) => {
      if (!response.ok) {
        const err = new Error("HTTP " + response.status);
        err.response = response;
        throw err;
      }
      return response;
    })
    .catch((err) => {
      try {
        console.error("Network error:", err);
      } catch (e) {}
      try {
        if (typeof alert !== "undefined")
          alert("Network error. Please try again.");
      } catch (e) {}
      throw err;
    });
}

// sidebar
document.getElementById("sidebarToggle")?.addEventListener("click", () => {
  document.body.classList.toggle("sidebar-open");
});

//dashboard reviews
(function () {
  const reviews = [
    {
      name: "Rakesh Verma",
      stars: 5,
      text: "FASTag activation is instant. Customers are satisfied.",
    },
    {
      name: "Amit Sharma",
      stars: 5,
      text: "Dashboard helps me finish installations quickly.",
    },
    {
      name: "Sanjay Meena",
      stars: 4,
      text: "Support team responds fast when issues arise.",
    },
    {
      name: "Rahul Singh",
      stars: 5,
      text: "Best platform for highway FASTag installations.",
    },
    {
      name: "Deepak Yadav",
      stars: 3,
      text: "Overall good, sometimes network issues in rural areas.",
    },
    {
      name: "Manoj Kumar",
      stars: 5,
      text: "KYC and activation flow is very smooth.",
    },
    {
      name: "Vikram Patel",
      stars: 4,
      text: "Commission tracking is clear and transparent.",
    },
    {
      name: "Nitin Choudhary",
      stars: 2,
      text: "Rare delays during peak hours.",
    },
    {
      name: "Pankaj Jain",
      stars: 5,
      text: "Works perfectly for on-site installations.",
    },
    {
      name: "Anil Gupta",
      stars: 1,
      text: "Had one failed activation, resolved later.",
    },
  ];

  const container = document.getElementById("agentReviews");
  if (!container) return;

  const list = [...reviews, ...reviews]; // duplicate for infinite loop

  list.forEach((r) => {
    const div = document.createElement("div");
    div.innerHTML = `
      <strong>${r.name}</strong><br>
      <span class="text-warning">${"★".repeat(r.stars)}${"☆".repeat(
      5 - r.stars
    )}</span>
      <div class="text-muted small">${r.text}</div>
    `;
    container.appendChild(div);
  });

  let y = 0;
  setInterval(() => {
    y += 1;
    container.style.transform = `translateY(-${y}px)`;
    if (y >= container.scrollHeight / 2) {
      y = 0;
      container.style.transform = "translateY(0)";
    }
  }, 40);
})();

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (e) {
    const href = this.getAttribute("href");

    // Skip empty "#" links (like Logout or JS handlers)
    if (!href || href === "#") {
      return;
    }

    e.preventDefault();
    const target = document.querySelector(href);
    if (target) {
      target.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    }
  });
});

// Fade in animation on scroll
const observerOptions = {
  threshold: 0.1,
  rootMargin: "0px 0px -50px 0px",
};

const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add("visible");
    }
  });
}, observerOptions);

// Observe elements for fade-in animation
document
  .querySelectorAll(".feature-card, .bank-card, .post-card")
  .forEach((el) => {
    el.classList.add("fade-in");
    observer.observe(el);
  });

// Newsletter form submission
const newsletterForm = document.querySelector(".newsletter-form");
if (newsletterForm) {
  newsletterForm.addEventListener("submit", function (e) {
    e.preventDefault();
    const email = this.querySelector('input[type="email"]').value;

    if (email) {
      // Show loading state
      const button = this.querySelector("button");
      const originalText = button.textContent;
      button.innerHTML = '<span class="loading"></span> Subscribing...';
      button.disabled = true;

      // Simulate API call
      setTimeout(() => {
        alert("Thank you for subscribing to our newsletter!");
        this.reset();
        button.textContent = originalText;
        button.disabled = false;
      }, 2000);
    }
  });
}

// Add to cart buttons
document.querySelectorAll(".btn").forEach((button) => {
  if (
    button.textContent.includes("Add to Cart") ||
    button.textContent.includes("Select")
  ) {
    button.addEventListener("click", function (e) {
      if (!this.textContent.includes("Add to Cart")) return;

      e.preventDefault();

      const productCard =
        this.closest(".product-card") ||
        this.closest(".bank-card") ||
        this.closest(".category-card");

      if (!productCard) {
        showNotification("Error: Could not identify product", "error");
        return;
      }

      const name =
        productCard.querySelector(".product-name")?.textContent?.trim() ||
        "Unknown Product";

      const priceText = productCard
        .querySelector(".product-price, .price")
        ?.textContent?.replace(/[^\d]/g, "");

      const price = parseInt(priceText, 10) || 0;

      const item = {
        id: Date.now().toString(),
        name,
        price,
        quantity: 1,
      };

      if (typeof window.addToCart === "function") {
        this.disabled = true;
        window.addToCart(item);
        setTimeout(() => (this.disabled = false), 600);
      } else {
        showNotification("Cart system not ready", "error");
      }
    });
  }
});

// Show notification function
function showNotification(message, type = "info") {
  const notification = document.createElement("div");
  notification.className = `notification ${type}`;
  notification.textContent = message;
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
        `;

  document.body.appendChild(notification);

  // Animate in
  setTimeout(() => {
    notification.style.transform = "translateX(0)";
  }, 100);

  // Remove after 3 seconds
  setTimeout(() => {
    notification.style.transform = "translateX(100%)";
    setTimeout(() => {
      document.body.removeChild(notification);
    }, 300);
  }, 3000);
}

// Back to top button
const backToTopButton = document.createElement("button");
backToTopButton.innerHTML = '<i class="fas fa-arrow-up"></i>';
backToTopButton.className = "back-to-top";
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
    `;

document.body.appendChild(backToTopButton);

// Show/hide back to top button
window.addEventListener("scroll", () => {
  if (window.pageYOffset > 300) {
    backToTopButton.style.display = "flex";
  } else {
    backToTopButton.style.display = "none";
  }
});

// Back to top functionality
backToTopButton.addEventListener("click", () => {
  window.scrollTo({
    top: 0,
    behavior: "smooth",
  });
});

// Form validation
document.querySelectorAll("form").forEach((form) => {
  form.addEventListener("submit", function (e) {
    const requiredFields = this.querySelectorAll("[required]");
    let isValid = true;

    requiredFields.forEach((field) => {
      if (!field.value.trim()) {
        isValid = false;
        field.style.borderColor = "#ef4444";
        field.addEventListener(
          "input",
          function () {
            this.style.borderColor = "";
          },
          { once: true }
        );
      }
    });

    if (!isValid) {
      e.preventDefault();
      showNotification("Please fill in all required fields", "error");
    }
  });
});

// Utility functions
function formatCurrency(amount) {
  return new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: "INR",
  }).format(amount);
}

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Search functionality (if needed)
function initializeSearch() {
  const searchInput = document.querySelector("#search");
  if (searchInput) {
    const debouncedSearch = debounce((query) => {
      // Implement search logic here
      console.log("Searching for:", query);
    }, 300);

    searchInput.addEventListener("input", function () {
      debouncedSearch(this.value);
    });
  }
}

// Initialize search on page load
document.addEventListener("DOMContentLoaded", initializeSearch);

// Update navbar login button based on auth state
function getLoggedInEmail() {
  try {
    const storedUserJson =
      localStorage.getItem("app_user") || localStorage.getItem("user");
    if (storedUserJson) {
      const storedUser = JSON.parse(storedUserJson);
      if (storedUser && storedUser.email) {
        return storedUser.email;
      }
      if (storedUser && storedUser.name) {
        return storedUser.name;
      }
    }
  } catch (e) {
    // ignore parse errors
  }
  const fallbackEmail = localStorage.getItem("userEmail");
  return fallbackEmail || null;
}

// Wire global logout on all pages that include the navbar
function wireGlobalLogout() {
  const logoutLink = document.getElementById("nav-logout");
  if (!logoutLink) return;

  logoutLink.addEventListener("click", async (e) => {
    e.preventDefault();
    try {
      // Clear client-side auth state
      localStorage.removeItem("user");
      localStorage.removeItem("userEmail");
      localStorage.removeItem("app_logged_in");
      localStorage.removeItem("app_user");
      // Invalidate server session
      await safeFetch("config/logout.php", { credentials: "include" });
    } catch (_) {}

    // Redirect to a safe page
    if (window.top) {
      window.top.location.href = "index.html";
    } else {
      window.location.href = "index.html";
    }
  });
}

document.addEventListener("DOMContentLoaded", wireGlobalLogout);

// Also wire any explicit logout button on profile page to same flow
function wireProfileLogoutButton() {
  const explicitLogoutBtn = document.querySelector(".logout-btn");
  if (!explicitLogoutBtn) return;

  explicitLogoutBtn.addEventListener("click", async (e) => {
    e.preventDefault();
    try {
      localStorage.removeItem("user");
      localStorage.removeItem("userEmail");
      await safeFetch("config/logout.php", { credentials: "include" });
    } catch (_) {}
    if (window.top) {
      window.top.location.href = "index.html";
    } else {
      window.location.href = "index.html";
    }
  });
}

document.addEventListener("DOMContentLoaded", wireProfileLogoutButton);

// theme helpers
window.theme = {
  copyToClipboard: function (text) {
    if (navigator.clipboard) {
      return navigator.clipboard.writeText(text);
    }
    var ta = document.createElement("textarea");
    ta.value = text;
    ta.style.position = "fixed";
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand("copy");
    } catch (e) {}
    document.body.removeChild(ta);
    return Promise.resolve();
  },
  toast: function (msg, timeout = 2500) {
    var t = document.createElement("div");
    t.className = "toast";
    t.innerText = msg;
    document.body.appendChild(t);
    setTimeout(() => (t.style.opacity = 0), timeout - 200);
    setTimeout(() => t.remove(), timeout);
  },
};
