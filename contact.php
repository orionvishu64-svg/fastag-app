<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us - Apna Payment Services</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="contact.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
    <!-- Header -->
    <section class="contact-header">
        <div class="container">
            <h1>We're here to help</h1>
            <p>Have questions about FASTag? Need help with your order? We're here to help you 24/7.</p>
        </div>
    </section>

    <!-- Contact Content -->
    <section class="contact-content">
        <div class="container">
            <div class="contact-grid">
                <!-- Contact Information -->
                <div class="contact-info">
                    <div class="info-card">
                        <h2>Get in Touch</h2>
                        <p>Reach out to us through any of these channels</p>
                        
                        <div class="contact-methods">
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="method-details">
                                    <h3>Phone Support</h3>
                                    <p>+91 9509807591</p>
                                    <span>Mon-Sun: 10:00 AM - 9:00 PM</span>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="method-details">
                                    <h3>Email Support</h3>
                                    <p>admin@apnapayment.com</p>
                                    <span>Response within 2 hours</span>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="method-details">
                                    <h3>Office Address</h3>
                                    <p>Apna Payment Services Pvt Ltd,<br>
                                    A-40, KARDHANI, GOVINDPURA,<br>
          JAIPUR, RAJASTHAN, 302012,<br>
          KARDHANI, Rajasthan, PIN: 302012</p>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="method-details">
                                    <h3>Working Hours</h3>
                                    <p>Mon - sun : 10AM-10PM
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Help -->
                    <div class="quick-help">
                        <h3>Quick Help</h3>
                        <div class="help-buttons">
                            <button class="help-btn">
                                <i class="fas fa-question-circle"></i>
                                How to install FASTag?
                            </button>
                            <button class="help-btn">
                                <i class="fas fa-exclamation-triangle"></i>
                                FASTag not working?
                            </button>
                            <button class="help-btn">
                                <i class="fas fa-credit-card"></i>
                                Recharge your FASTag
                            </button>
                            <button class="help-btn">
                                <i class="fas fa-truck"></i>
                                Track your order
                            </button>
                        </div>
                    </div>
                </div>
        <!-- Contact Form -->
        <div class="contact-form-section">
          <div class="form-card">
            <h2>Send us a Message</h2>
            <p>Fill out the form below and we'll get back to you as soon as possible</p>

            <form class="contact-form" id="contactForm">
              <div class="form-row">
                <div class="form-group">
                  <label for="firstName">First Name *</label>
                  <input type="text" id="firstName" name="firstName" required>
                </div>
                <div class="form-group">
                  <label for="lastName">Last Name *</label>
                  <input type="text" id="lastName" name="lastName" required>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="email">Email Address *</label>
                  <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                  <label for="phone">Phone Number *</label>
                  <input type="tel" id="phone" name="phone" required>
                </div>
              </div>
              <div class="form-group">
                <label for="subject">Subject *</label>
                <select id="subject" name="subject" required>
                  <option value="">Select a subject</option>
                  <option value="general">General Inquiry</option>
                  <option value="order">Order Related</option>
                  <option value="billing">Billing Question</option>
                  <option value="complaint">Complaint</option>
                  <option value="feedback">Feedback</option>
                </select>
              </div>
              <div class="form-group">
                <label for="orderNumber">Order Number (if applicable)</label>
                <input type="text" id="orderNumber" name="orderNumber" placeholder="Enter your order number">
              </div>
              <div class="form-group">
                <label for="message">Message *</label>
                <textarea id="message" name="message" rows="5" placeholder="Please describe your inquiry in detail..." required></textarea>
              </div>
              <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i> Send Message
              </button>
              <div id="successMessage" style="display:none; margin-top:20px; color: #22c55e; font-weight:600;">
                ✅ Your ticket has been submitted! Ticket ID: <span id="ticketRef"></span>
              </div>
            </form>

            <div style="margin-top:20px; text-align:center;">
              <button onclick="window.location.href='conversation.php'">
                🗨️ View All Conversations
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <script src="contact_form.js"></script>
   <script src="script.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
