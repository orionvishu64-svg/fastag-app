document.addEventListener("DOMContentLoaded", () => {

  // ensure safeFetch exists (keeps your original behavior but returns Response)
  (function () {
    if (typeof window.safeFetch !== "function") {
      window.safeFetch = function safeFetch(...args) {
        return fetch(...args)
          .then(response => {
            if (!response.ok) {
              const err = new Error("HTTP " + response.status);
              err.response = response;
              throw err;
            }
            return response;
          })
          .catch(err => {
            try { console.error("Network error:", err); } catch (e) {}
            try { if (typeof alert !== 'undefined') alert("Network error. Please try again."); } catch (e) {}
            throw err;
          });
      };
    }
  })();

  // --- helpers ---
  function getSelectedPincode() {
    // Try a few selectors - adapt if your markup differs
    const selectors = [
      '#customLocation',
      'input[name="pincode"]',
      'input#payment-pincode',
      '.address-box.selected',
      '.selected-address',
      'select[name="address"]'
    ];
    for (const sel of selectors) {
      const el = document.querySelector(sel);
      if (!el) continue;
      const val = (el.value || el.textContent || '').trim();
      if (!val) continue;
      const m = val.match(/(\d{5,6})/);
      if (m) return m[1];
      if (/^\d{4,7}$/.test(val)) return val;
    }
    return null;
  }

// --- network helper: safeFetchJson + debugFetchJson ---
// Place this at top of payment.js BEFORE any usage.
async function safeFetchJson(url, opts = {}) {
  try {
    const res = await fetch(url, opts);
    const text = await res.text();
    if (!text) {
      // no body — include status for debugging
      throw new Error('Empty response (HTTP ' + res.status + ') from ' + url);
    }
    try {
      const j = JSON.parse(text);
      return { ok: res.ok, status: res.status, json: j, raw: text };
    } catch (e) {
      // Invalid JSON — show raw body in console for debugging
      console.error('Invalid JSON from', url, 'raw:', text);
      throw new Error('Invalid JSON response (HTTP ' + res.status + ') from ' + url);
    }
  } catch (err) {
    // Network/CORS/fetch error or our thrown error above
    console.error('safeFetchJson error for', url, err);
    // bubble up so callers can show UI message
    throw err;
  }
}

// Optional more-verbose helper you can use temporarily for debugging
async function debugFetchJson(url, opts = {}) {
  try {
    console.log('[debugFetchJson] request', url, opts);
    const res = await fetch(url, opts);
    console.log('[debugFetchJson] status', res.status, 'type', res.type);
    const text = await res.text();
    console.log('[debugFetchJson] raw len', text.length, 'preview', text.slice(0,300));
    if (!text) throw new Error('Empty response (HTTP ' + res.status + ')');
    try {
      const json = JSON.parse(text);
      return { ok: res.ok, status: res.status, json, raw: text };
    } catch (e) {
      console.error('[debugFetchJson] invalid JSON raw:', text);
      throw new Error('Invalid JSON');
    }
  } catch (err) {
    console.error('[debugFetchJson] failed for', url, err);
    alert('Network/request error: ' + (err.message || err));
    throw err;
  }
}

  // basic validators used in original script
  const isValidPhone = (s) => /^[0-9]{10}$/.test(String(s || ""));
  const isAlphaNum = (s) => /^[A-Za-z0-9]+$/.test(String(s || "").trim());

  // --- DOM refs (from your file) ---
  const addressesContainer = document.getElementById("saved-addresses");
  const addAddressBtn = document.getElementById("add-address-btn");
  const newAddressForm = document.getElementById("new-address-form");
  const saveAddressBtn = document.getElementById("save-address");
  const proceedBtn = document.getElementById("proceed-btn");

  const phoneContainer = document.getElementById("phone-container");
  const phoneInput = document.getElementById("payment-user-phone");
  const savePhoneBtn = document.getElementById("save-phone-btn");

  const orderItemsEl = document.getElementById("order-items");
  const orderTotalEl = document.getElementById("order-total");

  const agentBox = document.getElementById("agent-id-box");
  const agentInput = document.getElementById("agentid");

  // state
  let hasPhone = false;
  let selectedAddressId = null;
  let selectedPaymentMethod = null;
  let agentApplied = false;
  let agentIdValue = "";
  const cart = JSON.parse(localStorage.getItem("cart") || "[]"); // use stored cart
  let total = 0;

  // decide phone visibility (keeps your logic)
  (function decidePhoneVisibility() {
    const localUser = JSON.parse(localStorage.getItem("user") || "null");
    const localPhone = localUser && localUser.phone;

    if (isValidPhone(localPhone)) {
      hasPhone = true;
      if (phoneContainer) phoneContainer.style.display = "none";
      return;
    }

    fetch("get_user.php", { credentials: "same-origin" })
      .then(r => r.json())
      .then(({ success, user: u }) => {
        if (success && isValidPhone(u && u.phone)) {
          hasPhone = true;
          if (phoneContainer) phoneContainer.style.display = "none";
          try {
            const base = (localUser && typeof localUser === "object") ? localUser : {};
            const updated = { ...base, phone: u.phone };
            localStorage.setItem("user", JSON.stringify(updated));
          } catch (_) {}
        } else {
          if (phoneContainer) phoneContainer.style.display = "";
        }
      })
      .catch(() => {
        if (phoneContainer) phoneContainer.style.display = "";
      });
  })();

  // save phone
  if (savePhoneBtn) {
    savePhoneBtn.addEventListener("click", () => {
      const phone = (phoneInput.value || "").trim();
      if (!isValidPhone(phone)) {
        alert("Please enter a valid 10-digit mobile number.");
        phoneInput.focus();
        return;
      }
      fetch("save_phone.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ phone }),
      })
        .then(r => r.json())
        .then(data => {
          if (!data || !data.success) throw new Error(data?.message || "Failed to save phone");
          hasPhone = true;
          if (phoneContainer) phoneContainer.style.display = "none";
          try {
            const localUser = JSON.parse(localStorage.getItem("user") || "null") || {};
            const updated = { ...localUser, phone };
            localStorage.setItem("user", JSON.stringify(updated));
          } catch (_) {}
          alert("Phone number saved.");
        })
        .catch(err => alert(err.message || "Error saving phone number."));
    });
  }

  // order summary
  orderItemsEl && (orderItemsEl.innerHTML = "");
  total = 0;
  cart.forEach(item => {
    const qty = Number(item.quantity || 1);
    const price = Number(item.price || 0);
    const lineTotal = qty * price;
    if (orderItemsEl) {
      const line = document.createElement("div");
      line.textContent = `${item.name} x ${qty} - ₹${lineTotal}`;
      orderItemsEl.appendChild(line);
    }
    total += lineTotal;
  });
  orderTotalEl && (orderTotalEl.textContent = String(total));

  // load addresses
  fetch("get_addresses.php", { credentials: "same-origin" })
    .then(res => res.json())
    .then(data => {
      if (!addressesContainer) return;
      addressesContainer.innerHTML = "";
      (data || []).forEach(address => {
        const div = document.createElement("div");
        div.classList.add("address-box");
        div.textContent = `${address.house_no}, ${address.landmark || ""}, ${address.city}, ${address.pincode}`.replace(/,\s*,/g, ",");
        div.dataset.id = address.id;
        div.onclick = () => {
          document.querySelectorAll(".address-box").forEach((box) => box.classList.remove("selected"));
          div.classList.add("selected");
          selectedAddressId = address.id;
        };
        addressesContainer.appendChild(div);
      });
    })
    .catch(() => { /* ignore address load errors for now */ });

  // toggle add address form
  if (addAddressBtn) {
    addAddressBtn.onclick = () => {
      if (!newAddressForm) return;
      if (newAddressForm.style.display === "none" || newAddressForm.style.display === "") {
        newAddressForm.style.display = "block";
        addAddressBtn.textContent = "Close";
      } else {
        newAddressForm.style.display = "none";
        addAddressBtn.textContent = "Add Address";
      }
    };
  }

  // save address
  if (saveAddressBtn) {
    saveAddressBtn.onclick = () => {
      const house_no = document.getElementById("payment-house-no").value.trim();
      const landmark = document.getElementById("payment-landmark").value.trim();
      const city = document.getElementById("payment-city").value.trim();
      const pincode = document.getElementById("payment-pincode").value.trim();

      if (!house_no || !city || !pincode) {
        alert("Please fill all required address fields.");
        return;
      }

      fetch("save_address.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ houseNo: house_no, landmark, city, pincode }),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            alert("Address added!");
            return fetch("get_addresses.php", { credentials: "same-origin" })
              .then((r) => r.json())
              .then((data2) => {
                if (!addressesContainer) return;
                addressesContainer.innerHTML = "";
                (data2 || []).forEach((address) => {
                  const div = document.createElement("div");
                  div.classList.add("address-box");
                  div.textContent = `${address.house_no}, ${address.landmark || ""}, ${address.city}, ${address.pincode}`.replace(/,\s*,/g, ",");
                  div.dataset.id = address.id;
                  div.onclick = () => {
                    document.querySelectorAll(".address-box").forEach((box) => box.classList.remove("selected"));
                    div.classList.add("selected");
                    selectedAddressId = address.id;
                  };
                  addressesContainer.appendChild(div);
                });
                if (newAddressForm) newAddressForm.style.display = "none";
                addAddressBtn && (addAddressBtn.textContent = "Add Address");
              });
          } else {
            alert(data.message || "Failed to add address.");
          }
        })
        .catch(() => alert("Failed to add address."));
    };
  }

  // payment method selection
  document.querySelectorAll('input[name="payment_method"]').forEach((input) => {
    input.addEventListener("change", () => {
      selectedPaymentMethod = input.value;
      agentApplied = false;
      agentIdValue = "";

      if (selectedPaymentMethod === "agent-id") {
        agentBox && (agentBox.style.display = "block");
        if (!document.getElementById("applyidBtn") && agentBox) {
          const applyBtn = document.createElement("button");
          applyBtn.id = "applyidBtn";
          applyBtn.className = "apply-btn";
          applyBtn.textContent = "Apply ID";
          agentBox.appendChild(applyBtn);

          applyBtn.addEventListener("click", () => {
            const val = (agentInput.value || "").trim();
            if (!val) return alert("Please enter your Agent ID");
            if (!isAlphaNum(val)) return alert("Agent ID must contain only letters and numbers.");
            agentApplied = true;
            agentIdValue = val;
            orderTotalEl && (orderTotalEl.textContent = "0");
            alert("✅ Agent ID applied. Your order is now free.");
          });
        }
      } else {
        if (agentBox) agentBox.style.display = "none";
        orderTotalEl && (orderTotalEl.textContent = String(total));
      }
    });
  });

  // restrict agent input
  if (agentInput) {
    agentInput.addEventListener("input", function () {
      this.value = this.value.replace(/[^a-zA-Z0-9]/g, "");
    });
  }

  // Proceed to pay (main action)
  if (proceedBtn) {
    proceedBtn.addEventListener("click", async function (ev) {
      ev.preventDefault();

      if (!hasPhone) {
        alert("Please add your phone number before placing the order.");
        phoneContainer && (phoneContainer.style.display = "block");
        phoneInput && phoneInput.focus();
        return;
      }

      const selectedMethodEl = document.querySelector("input[name='payment_method']:checked");
      if (!selectedMethodEl) {
        alert("Please select a payment method");
        return;
      }
      if (!selectedAddressId) {
        alert("Please select a delivery address.");
        return;
      }

      const method = selectedMethodEl.value;
      if (method === "agent-id" && !agentApplied) {
        alert("Please apply your Agent ID before proceeding.");
        return;
      }

      const finalAmount = (method === "agent-id") ? 0 : total;

      // Build payload (same shape as your original)
      const payload = {
        payment_method: method,
        transaction_id: method === "agent-id" ? agentIdValue : null,
        amount: finalAmount,
        address_id: selectedAddressId,
        items: cart.map((i) => ({
          bank: i.bank || "",
          product_name: i.name,
          quantity: Number(i.quantity || 1),
          price: Number(i.price || 0),
        })),
      };

      try {
        const r = await safeFetchJson('/place_order.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        });

        const data = r.json;

        const ok = !!(data && (data.success === true || data.status === "success"));
        if (ok) {
          const orderId = data.order_id || data.data?.order_id || null;
          if (method === "upi") {
            window.location.href = `upi_intent.html?amount=${encodeURIComponent(finalAmount)}&address_id=${encodeURIComponent(selectedAddressId)}&order_id=${encodeURIComponent(orderId)}`;
          } else if (method === "agent-id") {
            try { localStorage.removeItem("cart"); } catch (_) {}
            window.location.href = "orderplaced.php";
          } else {
            window.location.href = orderId ? ('orderplaced.php?order_id=' + encodeURIComponent(orderId)) : 'orderplaced.php';
          }
        } else {
          alert("Error placing order: " + (data.message || JSON.stringify(data)));
        }
      } catch (err) {
        console.error('Place order failed:', err);
        alert('Order request failed: ' + err.message);
      }
    });
  }

  // Single pincode check using the safe helper (remove your stray lines)
  (function runInitialPincodeCheck() {
    const pin = getSelectedPincode();
    if (!pin) return;
    const url = '/api/pincode_check.php?pincode=' + encodeURIComponent(pin); // use 'pincode' param
    safeFetchJson(url, { credentials: 'same-origin' })
      .then(r => {
        const json = r.json;
        if (json && json.success) {
          // show cost: json.data.shipping_cost, TAT: json.data.min_tat_days - max_tat_days
          console.log('Pincode serviceable', json);
        } else {
          console.warn('Pincode not serviceable or server returned error', json);
        }
      })
      .catch(err => {
        console.warn('Pincode check failed:', err.message);
      });
  })();

});
