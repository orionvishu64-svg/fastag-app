<?php include __DIR__ . '/includes/header.php'; ?>

<!-- HEADER -->
<section class="contact-header bg-light py-5 border-bottom">
  <div class="container">
    <h1 class="fw-bold">We're here to help</h1>
    <p class="text-muted mb-0">
      Have questions about FASTag? Need help with your order? We're here to help you 24/7.
    </p>
  </div>
</section>

<!-- TOP CLICKABLE CARDS -->
<section class="py-5">
  <div class="container">
    <div class="row g-4 text-center">

      <!-- CALL -->
      <div class="col-md-4">
        <a href="tel:+919509807591" class="text-decoration-none text-dark">
          <div class="card shadow-sm h-100">
            <div class="card-body p-4">
              <span class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 mb-3 d-inline-block">
                <i class="fas fa-phone fs-4"></i>
              </span>
              <h5 class="fw-semibold">Call Us</h5>
              <p class="text-muted mb-0">+91 9509807591</p>
            </div>
          </div>
        </a>
      </div>

      <!-- EMAIL -->
      <div class="col-md-4">
        <a href="mailto:admin@apnapayment.com" class="text-decoration-none text-dark">
          <div class="card shadow-sm h-100">
            <div class="card-body p-4">
              <span class="bg-success bg-opacity-10 text-success rounded-circle p-3 mb-3 d-inline-block">
                <i class="fas fa-envelope fs-4"></i>
              </span>
              <h5 class="fw-semibold">Email Us</h5>
              <p class="text-muted mb-0">admin@apnapayment.com</p>
            </div>
          </div>
        </a>
      </div>

      <!-- VISIT -->
      <div class="col-md-4">
        <a href="https://maps.app.goo.gl/eN9xQSxAhqWxNkFw6?g_st=aw"
           target="_blank" class="text-decoration-none text-dark">
          <div class="card shadow-sm h-100">
            <div class="card-body p-4">
              <span class="bg-warning bg-opacity-10 text-warning rounded-circle p-3 mb-3 d-inline-block">
                <i class="fas fa-location-dot fs-4"></i>
              </span>
              <h5 class="fw-semibold">Visit Us</h5>
              <p class="text-muted mb-0">Kardhani, Govindpura, Jaipur</p>
            </div>
          </div>
        </a>
      </div>

    </div>
  </div>
</section>

<!-- MAIN CONTENT -->
<section class="contact-content pb-5">
  <div class="container">
    <div class="row g-4">

      <!-- LEFT: FAQ + HOURS -->
      <div class="col-lg-4">

        <!-- FAQ -->
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h5 class="fw-semibold mb-3">Quick Help</h5>

            <button class="btn btn-outline-secondary w-100 text-start mb-2"
              data-bs-toggle="collapse" data-bs-target="#faq1">
              <i class="fas fa-question-circle me-2"></i> How to install FASTag?
            </button>
            <div id="faq1" class="collapse ps-3 text-muted small mb-2">
              Clean windshield and paste FASTag behind the rear-view mirror.
            </div>

            <button class="btn btn-outline-secondary w-100 text-start mb-2"
              data-bs-toggle="collapse" data-bs-target="#faq2">
              <i class="fas fa-exclamation-triangle me-2"></i> FASTag not working?
            </button>
            <div id="faq2" class="collapse ps-3 text-muted small mb-2">
              Ensure balance, correct placement, and no obstruction.
            </div>

            <button class="btn btn-outline-secondary w-100 text-start"
              data-bs-toggle="collapse" data-bs-target="#faq3">
              <i class="fas fa-credit-card me-2"></i> Recharge FASTag
            </button>
            <div id="faq3" class="collapse ps-3 text-muted small">
              Recharge via UPI, bank app, or our portal.
            </div>
          </div>
        </div>

        <!-- OFFICE HOURS -->
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">Office Hours</h5>

            <div class="p-3 bg-light rounded mb-2 d-flex gap-3">
              <i class="fa-solid fa-clock text-success"></i>
              <div><strong>Mon – Fri:</strong> 9:00 AM – 6:00 PM</div>
            </div>

            <div class="p-3 bg-light rounded mb-2 d-flex gap-3">
              <i class="fa-solid fa-clock text-primary"></i>
              <div><strong>Saturday:</strong> 9:00 AM – 4:00 PM</div>
            </div>

            <div class="p-3 bg-light rounded d-flex gap-3">
              <i class="fa-solid fa-clock text-danger"></i>
              <div><strong>Sunday:</strong> Closed</div>
            </div>

          </div>
        </div>

      </div>

      <!-- RIGHT: CONTACT FORM (UNCHANGED) -->
      <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
          <div class="card-body">

            <h5 class="fw-semibold">Send us a Message</h5>
            <p class="text-muted">Fill out the form below and we'll get back to you.</p>

            <form class="contact-form" id="contactForm">

              <div class="row g-3">
                <div class="col-md-6">
                  <label>First Name *</label>
                  <input id="firstName" name="firstName" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label>Last Name *</label>
                  <input id="lastName" name="lastName" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label>Email *</label>
                  <input id="email" name="email" type="email" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label>Phone *</label>
                  <input id="phone" name="phone" class="form-control" required>
                </div>
                <div class="col-12">
                  <label>Subject *</label>
                  <select id="subject" name="subject" class="form-select" required>
                    <option value="">Select subject</option>
                    <option value="general">General Inquiry</option>
                    <option value="order">Order Related</option>
                    <option value="billing">Billing</option>
                    <option value="complaint">Complaint</option>
                    <option value="feedback">Feedback</option>
                  </select>
                </div>
                <div class="col-12">
                  <label>Message *</label>
                  <textarea id="message" name="message" rows="4" class="form-control" required></textarea>
                </div>
              </div>

              <button class="btn btn-primary w-100 mt-4">
                <i class="fas fa-paper-plane"></i> Send Message
              </button>

              <div id="successMessage" class="text-success fw-semibold mt-3" style="display:none;">
                Ticket submitted! ID: <span id="ticketRef"></span>
              </div>
            </form>

            <div class="text-center mt-4">
              <button class="btn btn-outline-secondary"
                onclick="window.location.href='conversations_list.php'">
                🗨️ View All Conversations
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FULL WIDTH MAP + OFFICE INFO -->
<section class="w-100 py-5 bg-light">
<div class="container my-5">
  <div class="card shadow-sm border-0 rounded-4 p-4">
    <div class="row g-4 align-items-center">

      <!-- MAP (SMALL & CLEAN) -->
      <div class="col-lg-5">
        <div class="ratio ratio-4x3 rounded-3 overflow-hidden">
          <iframe
            src="https://www.google.com/maps?q=A-40+Kardhani+Govindpura+Jaipur&output=embed"
            loading="lazy"
            style="border:0;">
          </iframe>
        </div>
      </div>

      <!-- OFFICE INFO -->
      <div class="col-lg-7">
        <h3 class="fw-bold text-primary mb-1">Our Office</h3>
        <p class="text-muted mb-4">
          Visit us for in-person FASTag support and expert assistance
        </p>

        <!-- Address -->
        <div class="d-flex gap-3 align-items-start bg-light rounded-3 p-3 mb-3">
          <i class="fas fa-location-dot text-primary fs-5 mt-1"></i>
          <div>
            <div class="fw-semibold">Address</div>
            <div class="text-muted">
              Apna Payment Services Pvt Ltd<br>
              A-40, Kardhani, Govindpura<br>
              Jaipur, Rajasthan – 302012
            </div>
          </div>
        </div>

        <!-- Phone -->
        <div class="d-flex gap-3 align-items-start bg-light rounded-3 p-3 mb-3">
          <i class="fas fa-phone text-primary fs-5 mt-1"></i>
          <div>
            <div class="fw-semibold">Phone</div>
            <a href="tel:+919509807591"
               class="text-decoration-none text-muted">
              +91 9509807591
            </a>
          </div>
        </div>

        <!-- Email -->
        <div class="d-flex gap-3 align-items-start bg-light rounded-3 p-3">
          <i class="fas fa-envelope text-primary fs-5 mt-1"></i>
          <div>
            <div class="fw-semibold">Email</div>
            <a href="mailto:admin@apnapayment.com"
               class="text-decoration-none text-muted">
              admin@apnapayment.com
            </a>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>
</section>
<script src="/public/js/contact_form.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
