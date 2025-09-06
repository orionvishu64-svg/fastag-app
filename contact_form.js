let socket;

document.addEventListener("DOMContentLoaded", () => {
  const contactForm = document.getElementById("contactForm");
  const successBox = document.getElementById("successMessage");
  const ticketRef = document.getElementById("ticketRef");

  // Connect to Socket.IO
  socket = io("http://15.207.50.101", {
    path: "/socket.io/",
    transports: ["websocket"],
    withCredentials: true,
  });

  socket.on("connect", () => console.log("✅ Connected to Socket.IO server"));
  socket.on("disconnect", () => console.log("❌ Disconnected from Socket.IO server"));
  socket.on("errorResponse", (msg) => alert("Error: " + msg));

  // Prefill user details
  safeFetch("http://15.207.50.101/get_user.php", { credentials: "include" })
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

      // ✅ Join the new ticket room so we get replies
      joinTicketRoom(data.ticket_id);
    } else {
      alert("Failed to create ticket: " + (data.message || "Unknown error"));
    }
  });

  // ----- START realtime patch -----

  // Helper: get ticket ID from page
  function getTicketId() {
    const el = document.getElementById("ticketRef") || document.getElementById("ticket_id") || document.querySelector("[name='ticket_id']");
    if (el) return el.value || el.textContent || el.innerText;
    if (window.TICKET_ID) return window.TICKET_ID;
    return null;
  }

  // Join room
  function joinTicketRoom(ticketId) {
    if (!ticketId) return;
    console.log("➡️ joining ticket room: ticket_" + ticketId);
    socket.emit("openTicketRoom", { ticket_id: ticketId });
  }

  // Render one reply into #messages
  function renderReply(reply) {
    const container = document.getElementById("messages"); // ✅ container in your HTML
    if (!container) {
      console.warn("No #messages container found");
      return;
    }

    const div = document.createElement("div");
    div.className = (reply.sender && reply.sender === "admin") ? "reply admin-reply" : "reply user-reply";
    div.innerHTML = `
      <div class="reply-meta">
        <strong>${reply.sender || "user"}</strong> • <small>${reply.created_at || ""}</small>
      </div>
      <div class="reply-text">${reply.message || ""}</div>
    `;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
  }

  // If ticket already exists on page, join immediately
  const existingTicketId = getTicketId();
  if (existingTicketId) {
    joinTicketRoom(existingTicketId);
  }

  // Listen for full conversation updates
  socket.on("conversationData", (conv) => {
    console.log("conversationData received", conv);
    const container = document.getElementById("messages");
    if (!container) return;

    if (Array.isArray(conv)) {
      container.innerHTML = "";
      conv.forEach((m) => renderReply(m));
    } else if (conv.message) {
      renderReply(conv);
    }
  });

  // Listen for single new replies
  socket.on("newReplyAdded", (msg) => {
    console.log("newReplyAdded", msg);
    renderReply(msg);
  });

  // Optional: after saving reply via PHP, call Node so admin also sees instantly
  function notifyNodeAfterSave(ticketId) {
    if (!ticketId) return;
    fetch("http://15.207.50.101:3000/notify-reply", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ ticket_id: ticketId }),
    }).catch((err) => console.warn("notifyNode failed", err));
  }

  // ----- END realtime patch -----
});
