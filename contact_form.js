// contact-form.js
document.addEventListener("DOMContentLoaded", () => {
  const contactForm = document.getElementById("contactForm");
  const successBox = document.getElementById("successMessage");
  const ticketRef = document.getElementById("ticketRef");

  // Connect to Socket.IO with PHP session cookies
  const socket = io("http://15.207.50.101", {
  path: "/socket.io/",
  transports: ["websocket"],
  withCredentials: true,
});

  socket.on("connect", () => console.log("✅ Connected to Socket.IO server"));
  socket.on("disconnect", () => console.log("❌ Disconnected from Socket.IO server"));
  socket.on("errorResponse", (msg) => alert("Error: " + msg));

  // Prefill user details
  fetch("http://15.207.50.101/get_user.php", { credentials: "include" })
    .then(res => res.json())
    .then(data => {
      if (data.success && data.user) {
        document.getElementById("firstName").value = data.user.firstName || "";
        document.getElementById("lastName").value = data.user.lastName || "";
        document.getElementById("email").value = data.user.email || "";
        document.getElementById("phone").value = data.user.phone || "";
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

    if (socket.connected) {
      socket.emit("submitQuery", queryData);
    } else {
      alert("❌ Connection to server failed. Please try again later.");
    }
  });

  // Listen for ticket creation confirmation
  socket.on("newQueryCreated", (data) => {
    if (data.success) {
      if (successBox) successBox.style.display = "block";
      if (ticketRef) ticketRef.textContent = data.ticket_id;
      contactForm.reset();
    } else {
      alert("Failed to create ticket: " + (data.message || "Unknown error"));
    }
  });
});
