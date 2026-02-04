// conversation.js
(() => {
  const ENDPOINT_GET_CONV = "/config/get_conversation.php";
  const ENDPOINT_GET_CLOSED = "/config/get_closed_conversation.php";
  const ENDPOINT_ADD_REPLY = "/config/contact_replies.php";

  async function safeFetchJson(url, opts = {}) {
    opts.credentials = opts.credentials ?? "include";
    opts.headers = opts.headers ?? {};
    const res = await fetch(url, opts);
    if (!res.ok) {
      const t = await res.text().catch(() => "");
      throw new Error(`HTTP ${res.status} ${res.statusText} ${t}`);
    }
    const ct = (res.headers.get("content-type") || "").toLowerCase();
    if (ct.includes("application/json")) return res.json();
    const txt = await res.text().catch(() => "");
    try {
      return txt ? JSON.parse(txt) : txt;
    } catch {
      return txt;
    }
  }

  function fmtTimeToIST(ts) {
    if (!ts) return "";
    let d = new Date(ts);
    if (isNaN(d.getTime())) {
      const n = Number(ts);
      if (!isNaN(n)) d = new Date(n * (String(n).length === 10 ? 1000 : 1));
      else return "";
    }
    try {
      const dtf = new Intl.DateTimeFormat("en-IN", {
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
        timeZone: "Asia/Kolkata",
      });
      return dtf.format(d);
    } catch {
      const hh = String(d.getHours()).padStart(2, "0");
      const mm = String(d.getMinutes()).padStart(2, "0");
      return `${hh}:${mm}`;
    }
  }

  function escapeHtml(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function genLocalId() {
    if (window.crypto && window.crypto.randomUUID)
      return "local_" + crypto.randomUUID();
    return (
      "local_" +
      Date.now().toString(36) +
      "_" +
      Math.floor(Math.random() * 90000 + 10000).toString(36)
    );
  }

  function escapeAttr(s) {
    return String(s || "").replace(/(["\\])/g, "\\$1");
  }

  function normalizeTextForCompare(s) {
    if (!s) return "";
    const decoded = String(s)
      .replace(/&amp;/g, "&")
      .replace(/&lt;/g, "<")
      .replace(/&gt;/g, ">")
      .replace(/&quot;/g, '"')
      .replace(/&#39;/g, "'");
    return decoded.replace(/\s+/g, " ").trim().toLowerCase();
  }

  function existsSimilarMessage(
    container,
    { senderLabel, text, tsISO, secondsTol = 3 } = {},
  ) {
    if (!container) return false;
    const normText = normalizeTextForCompare(text);
    const nodes = container.querySelectorAll(".message");
    if (!nodes || !nodes.length) return false;

    let tsMs = null;
    if (tsISO) {
      const d = new Date(tsISO);
      if (!isNaN(d.getTime())) tsMs = d.getTime();
      else {
        const n = Number(tsISO);
        if (!isNaN(n)) tsMs = n * (String(n).length === 10 ? 1000 : 1);
      }
    }

    for (let i = 0; i < nodes.length; i++) {
      const n = nodes[i];
      const existingSender = (
        n.getAttribute("data-sender") || ""
      ).toLowerCase();
      const existingText = (
        n.getAttribute("data-norm-text") || ""
      ).toLowerCase();
      const existingTs = n.getAttribute("data-ts-ms")
        ? Number(n.getAttribute("data-ts-ms"))
        : null;

      if (
        existingText &&
        existingText === normText &&
        existingSender &&
        existingSender === (senderLabel || "").toLowerCase()
      ) {
        if (tsMs && existingTs) {
          if (Math.abs(existingTs - tsMs) <= secondsTol * 1000) return true;
        } else {
          return true;
        }
      } else {
        const body = n.querySelector(".message-body") || null;
        const header = n.querySelector(".message-header strong");
        const timeEl =
          n.querySelector(".message-header .time") ||
          n.querySelector(".meta") ||
          null;
        const existingHeader = header
          ? header.textContent.trim().toLowerCase()
          : "";
        const existingBody = body
          ? normalizeTextForCompare(body.textContent || "")
          : "";
        let existingTs2 = null;
        if (timeEl) {
          const t = new Date((timeEl.textContent || "").trim());
          if (!isNaN(t.getTime())) existingTs2 = t.getTime();
        }
        if (
          existingHeader === (senderLabel || "").toLowerCase() &&
          existingBody === normText
        ) {
          if (tsMs && existingTs2) {
            if (Math.abs(existingTs2 - tsMs) <= secondsTol * 1000) return true;
          } else {
            return true;
          }
        }
      }
    }
    return false;
  }

  function renderMessage(
    msg = {},
    { containerId = "messages_container", prepend = false } = {},
  ) {
    const container = document.getElementById(containerId);
    if (!container) return null;

    const replyId = msg.id ?? msg.inserted_id ?? msg.reply_id ?? null;
    const localId = msg.local_id ?? msg.localId ?? msg.client_msg_id ?? null;
    const text = (msg.reply_text ?? msg.message ?? msg.content ?? "") + "";
    const isAdmin =
      typeof msg.is_admin !== "undefined"
        ? Number(msg.is_admin) === 1
        : Boolean(msg.admin_identifier);
    const senderLabelRaw = isAdmin
      ? msg.admin_identifier || "Admin"
      : msg.sender_name || "You";
    const senderLabel = String(senderLabelRaw || "Unknown").trim();
    const ts = msg.created_at ?? msg.replied_at ?? msg.created_at_ts ?? null;
    const tsISO = ts ? new Date(ts).toISOString() : null;
    const tsText = fmtTimeToIST(ts);

    if (
      replyId &&
      container.querySelector(`[data-reply-id="${escapeAttr(replyId)}"]`)
    )
      return null;
    if (
      !replyId &&
      localId &&
      container.querySelector(`[data-local-id="${escapeAttr(localId)}"]`)
    )
      return null;

    if (
      existsSimilarMessage(container, {
        senderLabel,
        text,
        tsISO,
        secondsTol: 3,
      })
    ) {
      return null;
    }

    const div = document.createElement("div");
    div.className = `message ${isAdmin ? "admin" : "user"}`;
    if (replyId) div.setAttribute("data-reply-id", String(replyId));
    if (localId) div.setAttribute("data-local-id", String(localId));

    div.setAttribute("data-sender", senderLabel.toLowerCase());
    div.setAttribute("data-norm-text", normalizeTextForCompare(text));
    if (tsISO) {
      const tsms = new Date(tsISO).getTime();
      if (!isNaN(tsms)) div.setAttribute("data-ts-ms", String(tsms));
    }

    div.innerHTML = `<div class="message-header"><strong>${escapeHtml(
      senderLabel,
    )}</strong> <span class="time">${escapeHtml(tsText)}</span></div>
                     <div class="message-body">${escapeHtml(text)}</div>`;

    if (prepend) container.prepend(div);
    else container.appendChild(div);
    container.scrollTop = container.scrollHeight;
    return div;
  }

  function renderRepliesContainer(container, replies) {
    if (!container) return;
    container.innerHTML = "";
    (replies || []).forEach((r) => {
      renderMessage(
        {
          id: r.id ?? r.inserted_id ?? r.reply_id,
          local_id: r.local_id ?? r.localId,
          reply_text: r.reply_text ?? r.message ?? "",
          is_admin:
            typeof r.is_admin !== "undefined"
              ? Number(r.is_admin)
              : Boolean(r.admin_identifier),
          created_at: r.replied_at ?? r.created_at ?? r.created_at_ts,
          admin_identifier: r.admin_identifier,
          sender_name: r.sender_name ?? null,
          sender_email: r.sender_email ?? null,
          user_id: r.user_id,
        },
        { containerId: container.id, prepend: false },
      );
    });
  }

  // ===== Load Open Ticket =====
  async function loadOpenTicket() {
    const ticketPublic = (window.TICKET_PUBLIC_ID || "").trim();
    let url = ENDPOINT_GET_CONV;
    if (ticketPublic) url += "?ticket_id=" + encodeURIComponent(ticketPublic);

    try {
      const data = await safeFetchJson(url, { method: "GET" });

      if (
        !data ||
        (Array.isArray(data) && data.length === 0) ||
        (typeof data === "object" && Object.keys(data).length === 0)
      ) {
        return;
      }

      const t =
        data.query || (Array.isArray(data) ? data[0] : data.ticket || data);

      const threadEl = document.getElementById("messages_container");
      if (!threadEl) {
        console.error("messages_container not found");
        return;
      }
      renderRepliesContainer(threadEl, data.replies || []);
    } catch (err) {
      console.error("Failed to load open ticket", err);
      const container = document.getElementById("openTicketContainer");
      if (container)
        container.innerHTML = `<div class="ticket"><p>Error loading ticket: ${escapeHtml(
          err.message || String(err),
        )}</p></div>`;
    }
  }

  // ===== Load Closed Tickets =====
  async function loadClosedTickets() {
    try {
      const data = await safeFetchJson(ENDPOINT_GET_CLOSED, { method: "GET" });

      const container = document.getElementById("closedTicketsContainer");
      if (!container) return;
      container.innerHTML = "";

      if (!data.success || !Array.isArray(data.queries)) {
        container.innerHTML =
          '<div class="alert alert-info">No closed conversations.</div>';
        return;
      }

      const ticketId = window.TICKET_PUBLIC_ID;

      const match = data.queries.find((q) => q.ticket_id === ticketId);
      if (!match) {
        container.innerHTML =
          '<div class="alert alert-warning">No conversation found.</div>';
        return;
      }

      const card = document.createElement("div");
      card.className = "card shadow-sm";

      card.innerHTML = `
      <div class="card-header bg-secondary text-white">
        <strong>${match.subject}</strong> (Closed)
      </div>
      <div class="card-body">
        <div id="messages_container_closed" class="d-flex flex-column gap-2"></div>
      </div>
    `;

      container.appendChild(card);

      const msgBox = card.querySelector("#messages_container_closed");
      renderRepliesContainer(msgBox, match.replies || []);
    } catch (err) {
      console.error("loadClosedTickets failed", err);
    }
  }

  // ===== Post Reply (generic) =====
  async function postReply(queryIdOrTicketId, message, opts = {}) {
    if (!queryIdOrTicketId || !message) throw new Error("Invalid params");
    const localId = opts.local_id || genLocalId();
    const form = new FormData();
    form.append("ticket_id", queryIdOrTicketId);
    form.append("query_id", queryIdOrTicketId);
    form.append("message", message);
    form.append("reply_text", message);
    form.append("local_id", localId);

    const res = await safeFetchJson(ENDPOINT_ADD_REPLY, {
      method: "POST",
      body: form,
      credentials: "include",
    });
    if (!res || res.success === false)
      throw new Error(
        res && res.message ? res.message : "Server rejected reply",
      );
    return res;
  }

  // ===== Socket / Real-time handling =====
  let socket = null;
  function setupSocketIfNeeded() {
    if (typeof io === "undefined") return;
    const contactQueryId = window.CONTACT_QUERY_ID || null;
    if (!contactQueryId) return;

    try {
      socket = io("https://store.apnapayment.com", {
        path: "/socket.io",
        transports: ["websocket"],
        withCredentials: true,
      });

      socket.on("connect", () => {
        socket.emit("join_ticket", { ticket_id: contactQueryId });
        console.log("[socket] joined ticket room", contactQueryId);
      });

      socket.on("new_reply", (payload) => {
        try {
          const normalized = {
            id: payload.id ?? null,
            local_id: payload.local_id ?? payload.localId ?? null,
            reply_text:
              payload.reply_text ?? payload.message ?? payload.content ?? "",
            is_admin:
              typeof payload.is_admin !== "undefined"
                ? Number(payload.is_admin)
                : payload.admin_identifier
                  ? 1
                  : 0,
            created_at:
              payload.replied_at ??
              payload.created_at ??
              payload.timestamp ??
              new Date().toISOString(),
            admin_identifier:
              payload.admin_identifier ?? payload.sender_email ?? null,
            sender_name: payload.sender_name ?? null,
            user_id: payload.user_id ?? null,
          };

          const localId = normalized.local_id;
          if (localId) {
            const esc = (s) => {
              if (window.CSS && typeof CSS.escape === "function")
                return CSS.escape(s);
              return String(s).replace(/(["\\])/g, "\\$1");
            };
            const selector = `#messages_container [data-local-id="${esc(
              localId,
            )}"]`;
            const existing = document.querySelector(selector);
            if (existing) {
              if (normalized.id)
                existing.setAttribute("data-reply-id", String(normalized.id));
              if (normalized.created_at) {
                const tms = new Date(normalized.created_at).getTime();
                if (!isNaN(tms))
                  existing.setAttribute("data-ts-ms", String(tms));
              }
              if (normalized.admin_identifier || normalized.sender_name) {
                const header = existing.querySelector(".message-header strong");
                if (header)
                  header.textContent =
                    normalized.admin_identifier ||
                    normalized.sender_name ||
                    (normalized.is_admin ? "Admin" : "You");
              }
              existing.setAttribute(
                "data-norm-text",
                (function () {
                  try {
                    return (normalized.reply_text || "")
                      .replace(/\s+/g, " ")
                      .trim()
                      .toLowerCase();
                  } catch (e) {
                    return (normalized.reply_text || "").toLowerCase();
                  }
                })(),
              );
              return;
            }
          }

          const container = document.getElementById("messages_container");
          if (!container) return;

          if (
            normalized.id &&
            container.querySelector(`[data-reply-id="${normalized.id}"]`)
          ) {
            return;
          }

          renderMessage(normalized, {
            containerId: "messages_container",
            prepend: false,
          });
        } catch (e) {
          console.error("socket new_reply handler error", e);
        }
      });

      socket.on("disconnect", (reason) => {
        console.log("[socket] disconnected", reason);
      });
    } catch (e) {
      console.warn("Could not initialize socket.io client", e);
    }
  }

  // ===== Initialize =====
  document.addEventListener("DOMContentLoaded", () => {
    const status = (window.TICKET_STATUS || "").toLowerCase();

    if (status === "open" || status === "in_progress") {
      loadOpenTicket().then(() => {
        setupSocketIfNeeded();

        const form = document.getElementById("replyForm");
        if (!form) return;

        form.addEventListener("submit", async (e) => {
          e.preventDefault();

          const msgEl = document.getElementById("replyMessage");
          const msg = msgEl.value.trim();
          if (!msg) return;

          const localId = genLocalId();
          const nowIso = new Date().toISOString();

          renderMessage(
            {
              local_id: localId,
              reply_text: msg,
              is_admin: 0,
              created_at: nowIso,
            },
            { containerId: "messages_container" },
          );

          try {
            const fd = new FormData();
            fd.append("ticket_id", window.TICKET_PUBLIC_ID);
            fd.append("query_id", window.CONTACT_QUERY_ID);
            fd.append("message", msg);
            fd.append("reply_text", msg);
            fd.append("local_id", localId);

            const res = await safeFetchJson(ENDPOINT_ADD_REPLY, {
              method: "POST",
              body: fd,
              credentials: "include",
            });

            if (!res || !res.success) {
              alert("Failed to send message");
            }
          } catch (err) {
            console.error("Send failed", err);
            alert("Send failed");
          }

          msgEl.value = "";
        });
      });
    } else if (status === "closed") {
      loadClosedTickets();
    }
  });

  window.CONVERSATION = {
    reloadOpen: loadOpenTicket,
    reloadClosed: loadClosedTickets,
    postReply,
    renderMessage,
  };
})();
