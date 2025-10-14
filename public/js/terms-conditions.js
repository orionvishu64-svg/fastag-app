// Terms & Conditions Page JavaScript
document.addEventListener("DOMContentLoaded", () => {
  // Initialize all functionality
  initializeReadingProgress()
  initializeTableOfContents()
  initializeFadeInAnimations()
  initializeKeyboardShortcuts()
  initializeDocumentActions()

  console.log("Terms & Conditions page loaded successfully")
})

// Reading Progress Bar
function initializeReadingProgress() {
  const progressBar = document.querySelector(".progress-bar")
  const content = document.querySelector(".terms-content")

  if (!progressBar || !content) return

  function updateProgress() {
    const contentHeight = content.offsetHeight
    const windowHeight = window.innerHeight
    const scrollTop = window.pageYOffset
    const contentTop = content.offsetTop

    // Calculate progress based on content scroll
    const contentScrolled = Math.max(0, scrollTop - contentTop)
    const maxScroll = Math.max(0, contentHeight - windowHeight)
    const progress = Math.min(100, (contentScrolled / maxScroll) * 100)

    progressBar.style.width = progress + "%"
  }

  // Update progress on scroll
  window.addEventListener("scroll", updateProgress)
  updateProgress() // Initial call
}

// Table of Contents functionality
function initializeTableOfContents() {
  const tocLinks = document.querySelectorAll(".toc-link")
  const sections = document.querySelectorAll(".content-section")

  if (!tocLinks.length || !sections.length) return

  // Smooth scrolling for TOC links
  tocLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault()
      const targetId = this.getAttribute("href").substring(1)
      const targetSection = document.getElementById(targetId)

      if (targetSection) {
        const offsetTop = targetSection.offsetTop - 120 // Account for fixed header
        window.scrollTo({
          top: offsetTop,
          behavior: "smooth",
        })

        // Update active state
        updateActiveTocLink(this)
      }
    })
  })

  // Scroll spy functionality
  function updateScrollSpy() {
    const scrollPosition = window.pageYOffset + 150 // Offset for better UX

    sections.forEach((section, index) => {
      const sectionTop = section.offsetTop
      const sectionBottom = sectionTop + section.offsetHeight
      const sectionId = section.getAttribute("id")
      const correspondingLink = document.querySelector(`.toc-link[href="#${sectionId}"]`)

      if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
        updateActiveTocLink(correspondingLink)
      }
    })
  }

  function updateActiveTocLink(activeLink) {
    tocLinks.forEach((link) => link.classList.remove("active"))
    if (activeLink) {
      activeLink.classList.add("active")
    }
  }

  // Throttled scroll event for better performance
  let scrollTimeout
  window.addEventListener("scroll", () => {
    if (scrollTimeout) {
      clearTimeout(scrollTimeout)
    }
    scrollTimeout = setTimeout(updateScrollSpy, 10)
  })

  // Initial call
  updateScrollSpy()
}

// Fade-in animations
function initializeFadeInAnimations() {
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

  // Observe content sections and cards
  const elementsToAnimate = document.querySelectorAll(`
        .content-section,
        .info-card,
        .warning-card,
        .data-card,
        .contract-info,
        .jurisdiction-info,
        .contact-card,
        .requirement-list,
        .payment-info,
        .prohibited-list,
        .refund-timeline
    `)

  elementsToAnimate.forEach((el) => {
    el.classList.add("fade-in-up")
    observer.observe(el)
  })
}

// Keyboard shortcuts
function initializeKeyboardShortcuts() {
  document.addEventListener("keydown", (e) => {
    // Ctrl/Cmd + P for print
    if ((e.ctrlKey || e.metaKey) && e.key === "p") {
      e.preventDefault()
      window.print()
    }

    // Ctrl/Cmd + T to focus table of contents
    if ((e.ctrlKey || e.metaKey) && e.key === "t") {
      e.preventDefault()
      const firstTocLink = document.querySelector(".toc-link")
      if (firstTocLink) {
        firstTocLink.focus()
      }
    }

    // Escape to close any modals (if implemented)
    if (e.key === "Escape") {
      // Close any open modals or dropdowns
      const openDropdown = document.querySelector(".hamburger-dropdown.show")
      if (openDropdown) {
        openDropdown.classList.remove("show")
        document.querySelector(".hamburger")?.classList.remove("active")
      }
    }
  })
}

// Document actions
function initializeDocumentActions() {
  // Print functionality
  const printButtons = document.querySelectorAll('.print-btn, .action-btn[onclick*="print"]')
  printButtons.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault()
      window.print()
    })
  })

  // Download PDF functionality (placeholder)
  window.downloadPDF = () => {
    showNotification("PDF download feature coming soon!", "info")
  }

  // Share functionality
  window.shareDocument = () => {
    if (navigator.share) {
      navigator
        .share({
          title: "Terms & Conditions - Apna Payment Services",
          text: "Read our Terms & Conditions for FASTag services",
          url: window.location.href,
        })
        .catch((err) => {
          console.log("Error sharing:", err)
          fallbackShare()
        })
    } else {
      fallbackShare()
    }
  }

  function fallbackShare() {
    // Copy URL to clipboard
    navigator.clipboard
      .writeText(window.location.href)
      .then(() => {
        showNotification("Link copied to clipboard!", "success")
      })
      .catch(() => {
        showNotification("Unable to copy link", "error")
      })
  }
}

// Enhanced notification system
function showNotification(message, type = "info", duration = 3000) {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll(".notification")
  existingNotifications.forEach((notification) => {
    notification.remove()
  })

  const notification = document.createElement("div")
  notification.className = `notification ${type}`

  // Create notification content
  const icon = getNotificationIcon(type)
  notification.innerHTML = `
        <div class="notification-content">
            <i class="${icon}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `

  // Style the notification
  notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${getNotificationColor(type)};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 400px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    `

  // Style notification content
  const content = notification.querySelector(".notification-content")
  content.style.cssText = `
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    `

  // Style close button
  const closeBtn = notification.querySelector(".notification-close")
  closeBtn.style.cssText = `
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 4px;
        opacity: 0.8;
        transition: opacity 0.3s ease;
    `

  closeBtn.addEventListener("mouseenter", () => {
    closeBtn.style.opacity = "1"
  })

  closeBtn.addEventListener("mouseleave", () => {
    closeBtn.style.opacity = "0.8"
  })

  document.body.appendChild(notification)

  // Animate in
  setTimeout(() => {
    notification.style.transform = "translateX(0)"
  }, 100)

  // Auto remove
  setTimeout(() => {
    notification.style.transform = "translateX(100%)"
    setTimeout(() => {
      if (document.body.contains(notification)) {
        notification.remove()
      }
    }, 300)
  }, duration)
}

function getNotificationIcon(type) {
  const icons = {
    success: "fas fa-check-circle",
    error: "fas fa-exclamation-circle",
    warning: "fas fa-exclamation-triangle",
    info: "fas fa-info-circle",
  }
  return icons[type] || icons.info
}

function getNotificationColor(type) {
  const colors = {
    success: "#10b981",
    error: "#ef4444",
    warning: "#f59e0b",
    info: "#3b82f6",
  }
  return colors[type] || colors.info
}

// Utility functions
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

function throttle(func, limit) {
  let inThrottle
  return function () {
    const args = arguments
    
    if (!inThrottle) {
      func.apply(this, args)
      inThrottle = true
      setTimeout(() => (inThrottle = false), limit)
    }
  }
}

// Enhanced scroll performance
const optimizedScroll = throttle(() => {
  // Any scroll-based functionality can be added here
}, 16) // ~60fps

window.addEventListener("scroll", optimizedScroll)

// Print event handlers
window.addEventListener("beforeprint", () => {
  console.log("Preparing document for printing...")
  // Add any print preparation logic here
})

window.addEventListener("afterprint", () => {
  console.log("Print dialog closed")
  // Add any post-print cleanup logic here
})

// Page visibility API for performance optimization
document.addEventListener("visibilitychange", () => {
  if (document.hidden) {
    // Page is hidden, pause any animations or heavy operations
    console.log("Page hidden - optimizing performance")
  } else {
    // Page is visible, resume normal operations
    console.log("Page visible - resuming normal operations")
  }
})

// Error handling for missing elements
function safeQuerySelector(selector, context = document) {
  try {
    return context.querySelector(selector)
  } catch (error) {
    console.warn(`Error selecting element: ${selector}`, error)
    return null
  }
}

function safeQuerySelectorAll(selector, context = document) {
  try {
    return context.querySelectorAll(selector)
  } catch (error) {
    console.warn(`Error selecting elements: ${selector}`, error)
    return []
  }
}

// Accessibility enhancements
function enhanceAccessibility() {
  // Add skip link for keyboard navigation
  const skipLink = document.createElement("a")
  skipLink.href = "#main-content"
  skipLink.textContent = "Skip to main content"
  skipLink.className = "skip-link"
  skipLink.style.cssText = `
        position: absolute;
        top: -40px;
        left: 6px;
        background: #2563eb;
        color: white;
        padding: 8px;
        text-decoration: none;
        border-radius: 4px;
        z-index: 10001;
        transition: top 0.3s ease;
    `

  skipLink.addEventListener("focus", () => {
    skipLink.style.top = "6px"
  })

  skipLink.addEventListener("blur", () => {
    skipLink.style.top = "-40px"
  })

  document.body.insertBefore(skipLink, document.body.firstChild)

  // Add main content landmark
  const mainContent = document.querySelector(".terms-content")
  if (mainContent) {
    mainContent.id = "main-content"
    mainContent.setAttribute("role", "main")
  }
}

// Initialize accessibility enhancements
enhanceAccessibility()

// Performance monitoring
const performanceObserver = new PerformanceObserver((list) => {
  const entries = list.getEntries()
  entries.forEach((entry) => {
    if (entry.entryType === "navigation") {
      console.log(`Page load time: ${entry.loadEventEnd - entry.loadEventStart}ms`)
    }
  })
})

try {
  performanceObserver.observe({ entryTypes: ["navigation"] })
} catch (error) {
  console.log("Performance Observer not supported")
}

// Export functions for potential external use
window.TermsConditionsPage = {
  showNotification,
  downloadPDF: window.downloadPDF,
  shareDocument: window.shareDocument,
  updateReadingProgress: initializeReadingProgress,
}
