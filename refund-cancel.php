<?php include __DIR__ . '/includes/header.php'; ?>

<style>
.hero-bg {
  position: absolute;
  inset: 0;
  background: url('/uploads/images/terms.jpg') center/cover no-repeat;
  opacity: 0.80;
}

/* Print only policy content */
@media print {
  header, footer, .print-fab, .toc-wrapper {
    display: none !important;
  }
  .policy-print {
    overflow: visible !important;
  }
}
</style>

<main class="main-content">

  <!-- HERO -->
  <section class="py-5 bg-light border-bottom hero-wrapper position-relative">
    <div class="hero-bg"></div>
    <div class="container hero-content position-relative">
      <div class="row">
        <div class="col-lg-6">
          <h1 class="fw-bold mb-2">Cancellation &amp; Refund Policy</h1>
          <p class="text-muted fs-5">
            We are committed to customer satisfaction and maintain a transparent
            cancellation and refund process.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- CONTENT -->
  <section class="py-5">
    <div class="container">
      <div class="row g-4">

        <!-- TABLE OF CONTENTS -->
        <aside class="col-lg-3 toc-wrapper">
          <div class="card border-0 shadow-sm toc-sticky">
            <div class="card-body">
              <h6 class="fw-bold mb-3">Table of Contents</h6>
              <nav>
                <a href="#intro" class="toc-link">Introduction</a>
                <a href="#cancellation" class="toc-link">Cancellation Policy</a>
                <a href="#refunds" class="toc-link">Refunds &amp; Replacements</a>
                <a href="#perishable" class="toc-link">Perishable Items</a>
                <a href="#damaged" class="toc-link">Damaged or Defective Products</a>
                <a href="#warranty" class="toc-link">Warranty Products</a>
                <a href="#processing" class="toc-link">Refund Processing</a>
                <a href="#contact" class="toc-link">Contact Information</a>
              </nav>
            </div>
          </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="col-lg-9 policy-scroll policy-print">

          <section id="intro" class="fade-section mb-5">
            <h3 class="fw-bold">Cancellation &amp; Refund Policy</h3>
            <p>
              <strong>APNA PAYMENT SERVICES PRIVATE LIMITED</strong> is committed to customer satisfaction
              and maintains a flexible cancellation policy to ensure transparency and fairness.
            </p>
          </section>

          <section id="cancellation" class="fade-section mb-5">
            <h4 class="fw-bold">Cancellation</h4>
            <p><strong>Eligibility:</strong></p>
            <p>
              Cancellation requests are accepted only if made immediately after placing the order.
            </p>
            <p><strong>Exceptions:</strong></p>
            <p>
              Orders that have already been processed or communicated to vendors or merchants
              may not be eligible for cancellation.
            </p>
          </section>

          <section id="refunds" class="fade-section mb-5">
            <h4 class="fw-bold">Refunds &amp; Replacements</h4>
            <p>
              Refunds or replacements are provided based on the nature of the product
              and subject to verification.
            </p>
          </section>

          <section id="perishable" class="fade-section mb-5">
            <h4 class="fw-bold">Perishable Items</h4>
            <p>
              Cancellation requests are not accepted for perishable items such as flowers or eatables.
            </p>
            <p>
              However, refunds or replacements may be issued if the delivered product
              is proven to be of poor quality.
            </p>
          </section>

          <section id="damaged" class="fade-section mb-5">
            <h4 class="fw-bold">Damaged or Defective Products</h4>
            <p>
              Any issues related to damaged or defective products must be reported
              to Customer Service within <strong>7 days</strong> of product receipt.
            </p>
            <p>
              Replacement or refund decisions will be subject to verification
              by the respective vendor or merchant.
            </p>
          </section>

          <section id="warranty" class="fade-section mb-5">
            <h4 class="fw-bold">Warranty Products</h4>
            <p>
              For products covered under manufacturer warranty,
              customers are requested to contact the manufacturer directly
              for resolution.
            </p>
          </section>

          <section id="processing" class="fade-section mb-5">
            <h4 class="fw-bold">Refund Processing</h4>
            <p>
              Approved refunds will be processed within
              <strong>6–8 working days</strong> from the date of approval.
            </p>
          </section>

          <section id="contact" class="fade-section mb-5">
            <h4 class="fw-bold">Contact Information</h4>
            <p>
              For any queries or concerns related to cancellations or refunds,
              please contact our Customer Service Team using the contact
              information provided on our website.
            </p>
          </section>

        </div>
      </div>
    </div>
  </section>

</main>

<!-- FLOATING PRINT BUTTON -->
<button class="btn btn-primary print-fab" onclick="window.print()">
  <i class="fas fa-print me-1"></i> Print Policy
</button>

<script>
document.addEventListener("DOMContentLoaded", () => {

  // Fade-in animation
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => e.isIntersecting && e.target.classList.add("show"));
  }, { threshold: 0.15 });

  document.querySelectorAll(".fade-section").forEach(el => io.observe(el));

  // TOC active + smooth scroll
  const links = document.querySelectorAll(".toc-link");
  const sections = document.querySelectorAll(".policy-print section");
  const scrollBox = document.querySelector(".policy-scroll");

  links.forEach(link => {
    link.addEventListener("click", e => {
      e.preventDefault();
      const id = link.getAttribute("href").substring(1);
      const target = document.getElementById(id);
      scrollBox.scrollTo({
        top: target.offsetTop - 20,
        behavior: "smooth"
      });
    });
  });

  scrollBox.addEventListener("scroll", () => {
    let pos = scrollBox.scrollTop + 40;
    sections.forEach(sec => {
      if (pos >= sec.offsetTop && pos < sec.offsetTop + sec.offsetHeight) {
        links.forEach(l => l.classList.remove("active"));
        document
          .querySelector(`.toc-link[href="#${sec.id}"]`)
          ?.classList.add("active");
      }
    });
  });

});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
