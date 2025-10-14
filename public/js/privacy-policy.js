// Privacy Policy Page JavaScript
document.addEventListener("DOMContentLoaded", () => {
  // Initialize page functionality
  initializeTableOfContents()
  initializeScrollSpy()
  initializeSmoothScrolling()
  initializeReadingProgress()
  initializePrintFunctionality()
  initializeContactForm()

  // Add fade-in animations
  initializeFadeInAnimations()
})

// Table of Contents functionality
function initializeTableOfContents() {
  const tocLinks = document.querySelectorAll(".toc-link")
  const sections = document.querySelectorAll(".policy-section")

  // Add click handlers for TOC links
  tocLinks.forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault()
      const targetId = link.getAttribute("href").substring(1)
      const targetSection = document.getElementById(targetId)

      if (targetSection) {
        targetSection.scrollIntoView({
          behavior: "smooth",
          block: "start",
        })

        // Update active state
        updateActiveTocLink(link)
      }
    })
  })
}

// Scroll spy functionality
function initializeScrollSpy() {
  const tocLinks = document.querySelectorAll(".toc-link")
  const sections = document.querySelectorAll(".policy-section")

  const observerOptions = {
    root: null,
    rootMargin: "-20% 0px -70% 0px",
    threshold: 0,
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const id = entry.target.getAttribute("id")
        const correspondingLink = document.querySelector(`.toc-link[href="#${id}"]`)

        if (correspondingLink) {
          updateActiveTocLink(correspondingLink)
        }
      }
    })
  }, observerOptions)

  sections.forEach((section) => {
    if (section.id) {
      observer.observe(section)
    }
  })
}

// Update active TOC link
function updateActiveTocLink(activeLink) {
  const tocLinks = document.querySelectorAll(".toc-link")

  tocLinks.forEach((link) => {
    link.classList.remove("active")
  })

  activeLink.classList.add("active")
}

// Smooth scrolling for all anchor links
function initializeSmoothScrolling() {
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault()
      const target = document.querySelector(this.getAttribute("href"))

      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        })
      }
    })
  })
}

// Reading progress indicator
function initializeReadingProgress() {
  // Create progress bar
  const progressBar = document.createElement("div")
  progressBar.className = "reading-progress"
  progressBar.innerHTML = '<div class="progress-fill"></div>'

  // Add styles
  progressBar.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: rgba(0, 0, 0, 0.1);
        z-index: 9999;
        transition: opacity 0.3s ease;
    `

  const progressFill = progressBar.querySelector(".progress-fill")
  progressFill.style.cssText = `
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        width: 0%;
        transition: width 0.3s ease;
    `

  document.body.appendChild(progressBar)

  // Update progress on scroll
  window.addEventListener("scroll", () => {
    const windowHeight = window.innerHeight
    const documentHeight = document.documentElement.scrollHeight - windowHeight
    const scrollTop = window.pageYOffset
    const progress = (scrollTop / documentHeight) * 100

    progressFill.style.width = Math.min(progress, 100) + "%"

    // Hide progress bar when at top
    if (scrollTop < 100) {
      progressBar.style.opacity = "0"
    } else {
      progressBar.style.opacity = "1"
    }
  })
}

// Print functionality
function initializePrintFunctionality() {
  // Add print button
  const printButton = document.createElement("button")
  printButton.innerHTML = '<i class="fas fa-print"></i> Print Policy'
  printButton.className = "print-btn"
  printButton.style.cssText = `
        position: fixed;
        bottom: 30px;
        left: 30px;
        background: #2563eb;
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 50px;
        cursor: pointer;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: all 0.3s ease;
        z-index: 1000;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    `

  printButton.addEventListener("click", () => {
    window.print()
  })

  printButton.addEventListener("mouseenter", () => {
    printButton.style.transform = "translateY(-2px)"
    printButton.style.boxShadow = "0 6px 20px rgba(0, 0, 0, 0.2)"
  })

  printButton.addEventListener("mouseleave", () => {
    printButton.style.transform = "translateY(0)"
    printButton.style.boxShadow = "0 4px 12px rgba(0, 0, 0, 0.15)"
  })

  document.body.appendChild(printButton)

  // Hide print button on mobile
  if (window.innerWidth <= 768) {
    printButton.style.display = "none"
  }
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

  // Add fade-in class and observe elements
  const elementsToAnimate = document.querySelectorAll(".info-card, .usage-list li, .right-item, .sharing-item")

  elementsToAnimate.forEach((el, index) => {
    el.classList.add("fade-in")
    el.style.animationDelay = `${index * 0.1}s`
    observer.observe(el)
  })
}

// Contact form functionality (if needed)
function initializeContactForm() {
  const contactButtons = document.querySelectorAll(".contact-card .btn")

  contactButtons.forEach((button) => {
    button.addEventListener("click", (e) => {
      if (button.textContent.includes("Contact Us")) {
        // Track contact button click
        const gtag = window.gtag // Declare gtag variable
        if (typeof gtag !== "undefined") {
          gtag("event", "click", {
            event_category: "Privacy Policy",
            event_label: "Contact Us Button",
          })
        }
      }
    })
  })
}

// Keyboard navigation
document.addEventListener("keydown", (e) => {
  // Press 'P' to print
  if (e.key === "p" && e.ctrlKey) {
    e.preventDefault()
    window.print()
  }

  // Press 'T' to focus on table of contents
  if (e.key === "t" && e.ctrlKey) {
    e.preventDefault()
    const firstTocLink = document.querySelector(".toc-link")
    if (firstTocLink) {
      firstTocLink.focus()
    }
  }
})

// Utility functions
function showNotification(message, type = "info") {
  const notification = document.createElement("div")
  notification.className = `notification ${type}`
  notification.textContent = message
  notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${type === "success" ? "#10b981" : type === "error" ? "#ef4444" : "#3b82f6"};
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
      if (document.body.contains(notification)) {
        document.body.removeChild(notification)
      }
    }, 300)
  }, 3000)
}

// Handle responsive behavior
function handleResponsiveChanges() {
  const tocSidebar = document.querySelector(".toc-sidebar")
  const printButton = document.querySelector(".print-btn")

  if (window.innerWidth <= 1024) {
    // Mobile/tablet behavior
    if (printButton) {
      printButton.style.display = "none"
    }
  } else {
    // Desktop behavior
    if (printButton) {
      printButton.style.display = "flex"
    }
  }
}

// Listen for window resize
window.addEventListener("resize", debounce(handleResponsiveChanges, 250))

// Debounce function
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

// Initialize responsive behavior
handleResponsiveChanges()

// Add CSS for fade-in animations
const fadeInStyles = document.createElement("style")
fadeInStyles.textContent = `
    .fade-in {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.6s ease;
    }
    
    .fade-in.visible {
        opacity: 1;
        transform: translateY(0);
    }
    
    .usage-list li.fade-in {
        transform: translateX(-30px);
    }
    
    .usage-list li.fade-in.visible {
        transform: translateX(0);
    }
`

document.head.appendChild(fadeInStyles)
