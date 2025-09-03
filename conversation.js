// conversation.js
const socket = io("/", { transports: ["websocket"], withCredentials: true });
let currentOpenTicketId = null;

// --- Load open ticket ---
async function loadOpenTicket() {
  try {
    const res = await fetch("get_conversation.php", { credentials: "include" });
    const data = await res.json();
    const container = document.getElementById("openTicketContainer");
    container.innerHTML = "";

    if (data.success && data.query) {
    const t = data.query;
    currentOpenTicketId = t.id;

    if (data.open) {
        // --- Show open/in-progress ticket ---
        container.innerHTML = `<div class="ticket">
          <h4>Open Ticket: ${t.ticket_id} - ${t.subject}</h4>
          <p><b>Message:</b> ${t.message}</p>
          <div id="openThread"></div>
          <form id="replyForm">
            <textarea id="replyMessage" placeholder="Type your reply..." required></textarea>
            <button type="submit">Reply</button>
          </form>
        </div>`;

        const thread = document.getElementById("openThread");
        data.replies.forEach(r => addMessageToThread(r.reply_text, r.is_admin));

        // --- Handle user reply ---
        document.getElementById("replyForm").addEventListener("submit", (e) => {
            e.preventDefault();
            const msg = document.getElementById("replyMessage").value.trim();
            if (!msg) return;

            socket.emit("sendReply", { query_id: t.id, message: msg });
            document.getElementById("replyMessage").value = "";
            addMessageToThread(msg, 0); // show immediately in UI
        });

    } else {
        // --- Ticket exists but it's closed ---
        container.innerHTML = `<p>You have a closed ticket (#${t.ticket_id}).</p>`;
        currentOpenTicketId = null;
    }

} else {
    container.innerHTML = `<p>No tickets found.</p>`;
    currentOpenTicketId = null;
}
  } catch (err) {
    console.error("Failed to load open ticket:", err);
  }
}

// --- Load closed tickets ---
async function loadClosedTickets() {
  try {
    const res = await fetch("get_closed_conversations.php", { credentials: "include" });
    const data = await res.json();
    const container = document.getElementById("closedTicketsContainer");
    container.innerHTML = "<h3>Closed Tickets</h3>";

    if (data.success && Array.isArray(data.queries) && data.queries.length > 0) {
      data.queries.forEach(t => {
        let html = `<div class="ticket">
          <h4>${t.ticket_id} - ${t.subject}</h4>
          <p><b>Message:</b> ${t.message}</p>`;

        t.replies.forEach(r => {
          html += `<div class="message ${r.is_admin==1 ? "admin" : "user"}">
                     ${r.reply_text}<div class="meta">${r.replied_at}</div>
                   </div>`;
        });

        html += `<div class="meta">Closed on: ${t.closed_at || t.submitted_at}</div></div>`;
        container.innerHTML += html;
      });
    } else {
      container.innerHTML += "<p>No closed tickets.</p>";
    }
  } catch (err) {
    console.error("Failed to load closed tickets:", err);
  }
}

// --- Append message to open thread --- 
function addMessageToThread(text, is_admin) {
  const thread = document.getElementById("openThread");
  if (!thread) return;

  const div = document.createElement("div");
  div.className = `message ${is_admin==1 ? "admin" : "user"}`;
  div.innerHTML = `${text}<div class="meta">just now</div>`;
  thread.appendChild(div);
  thread.scrollTop = thread.scrollHeight;
}

// --- Socket.IO listeners ---

// Refresh open ticket when server sends updated conversation
socket.on("conversationData", (data) => {
  if (data.success) loadOpenTicket();
});

// Add reply confirmation (optional)
socket.on("newReplyAdded", (data) => {
  if (data.success) {
    // reload open ticket to show all messages
    loadOpenTicket();
  }
});

// Listen for live admin replies
socket.on("new_message", (data) => {
  if (data.ticket_id === currentOpenTicketId && data.is_admin === 1) {
    addMessageToThread(data.text, 1);
  }
});

// --- Initial load ---
loadOpenTicket();
loadClosedTickets();

// --- Scroll to the most recent message ---
function scrollToMostRecent({ smooth = true } = {}) {
  const scrollBehavior = smooth ? "smooth" : "auto";

  // Scroll main conversation area
  const conversationArea = document.getElementById("conversationArea");
  if (conversationArea) {
    conversationArea.scrollTo({
      top: conversationArea.scrollHeight,
      behavior: scrollBehavior
    });
  }

  // Scroll the open ticket thread
  const openThread = document.getElementById("openThread");
  if (openThread) {
    openThread.scrollTo({
      top: openThread.scrollHeight,
      behavior: scrollBehavior
    });
  }

  // Scroll all closed ticket threads
  const closedThreads = document.querySelectorAll("#closedTicketsContainer .ticket");
  closedThreads.forEach(ticket => {
    const messages = ticket.querySelectorAll(".message");
    if (messages.length) {
      const lastMessage = messages[messages.length - 1];
      lastMessage.scrollIntoView({ behavior: scrollBehavior, block: "end" });
    }
  });
}

// --- Scroll instantly on page load ---
window.addEventListener("DOMContentLoaded", () => {
  scrollToMostRecent({ smooth: false });
});

// --- Scroll smoothly for new messages/replies ---
socket.on("new_message", () => {
  scrollToMostRecent({ smooth: true });
});

socket.on("newReplyAdded", () => {
  scrollToMostRecent({ smooth: true });
});
