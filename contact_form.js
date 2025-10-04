document.addEventListener("DOMContentLoaded", () => {
  const contactForm = document.getElementById("contactForm");
  const successBox = document.getElementById("successMessage");
  const ticketRef = document.getElementById("ticketRef");

  // Prefill user details
  fetch("get_user.php", { credentials: "include" })
    .then(res => res.json())
    .then(data => {
      if (data.success && data.user) {
        document.getElementById("firstName").value = data.user.firstName || "";
        document.getElementById("lastName").value  = data.user.lastName || "";
        document.getElementById("email").value     = data.user.email || "";
        document.getElementById("phone").value     = data.user.phone || "";
      }
    })
    .catch(err => console.error("❌ Failed to load user details:", err));

  // Submit ticket
  contactForm.addEventListener("submit", (e) => {
    e.preventDefault();

    const queryData = {
      firstName: document.getElementById("firstName").value.trim(),
      lastName: document.getElementById("lastName").value.trim(),
      email: document.getElementById("email").value.trim(),
      phone: document.getElementById("phone").value.trim(),
      subject: document.getElementById("subject").value.trim(),
      message: document.getElementById("message").value.trim(),
      orderNumber: document.getElementById("orderNumber")?.value.trim() || null,
    };

    // Validate required fields
    if (!queryData.firstName || !queryData.lastName || !queryData.email || !queryData.subject || !queryData.message) {
      return alert("Please fill all required fields.");
    }

    // Send query to backend
    fetch("contact_queries.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({
        subject: queryData.subject,
        message: queryData.message
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
  if (successBox) successBox.style.display = "block";
  if (ticketRef) ticketRef.textContent = data.ticket_id;
  contactForm.reset();
  // Redirect user to conversation page for this ticket
  // Make sure the ticket_id in response is the same "public" id used by conversation.php
  if (data.ticket_id) {
    window.location.href = `/conversation.php?ticket_id=${encodeURIComponent(data.ticket_id)}`;
  }
} else {
  alert("Failed to create ticket: " + (data.message || "Unknown error"));
}
    })

    .catch(err => {
      console.error("❌ Failed to submit query:", err);
      alert("Network error while submitting query.");
    });
  });
});
