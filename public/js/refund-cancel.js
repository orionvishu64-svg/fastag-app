// refund and cancel Page JavaScript
document.addEventListener("DOMContentLoaded", () => {
  // Initialize all functionality
  initializeReadingProgress()
  initializeFadeInAnimations()
  initializeKeyboardShortcuts()
  initializeDocumentActions()

  console.log("refund and cancel page loaded successfully")
})

// Reading Progress Barz
function initializeReadingProgress() {
  const progressBar = document.querySelector(".progress-bar")
  const content = document.querySelector(".refund-content")

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