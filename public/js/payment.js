// payment.js
document.addEventListener("DOMContentLoaded", () => {

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

  function getSelectedPincode() {
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
    const selBox = document.querySelector('.address-box.selected');
    if (selBox && selBox.dataset && selBox.dataset.pincode) return selBox.dataset.pincode;
    return null;
  }

  async function safeFetchJson(url, opts = {}) {
    try {
      const res = await fetch(url, opts);
      const text = await res.text();
      if (!text) throw new Error('Empty response (HTTP ' + res.status + ') from ' + url);
      try {
        const j = JSON.parse(text);
        return { ok: res.ok, status: res.status, json: j, raw: text };
      } catch (e) {
        console.error('Invalid JSON from', url, 'raw:', text);
        throw new Error('Invalid JSON response (HTTP ' + res.status + ') from ' + url);
      }
    } catch (err) {
      console.error('safeFetchJson error for', url, err);
      throw err;
    }
  }

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

  const isValidPhone = (s) => /^[0-9]{10}$/.test(String(s || ""));
  const isAlphaNum = (s) => /^[A-Za-z0-9]+$/.test(String(s || "").trim());

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

  let pinStatusEl = document.getElementById('pincode-status');
  if (!pinStatusEl) {
    pinStatusEl = document.createElement('div');
    pinStatusEl.id = 'pincode-status';
    pinStatusEl.style.margin = '8px 0';
    if (proceedBtn && proceedBtn.parentNode) proceedBtn.parentNode.insertBefore(pinStatusEl, proceedBtn);
  }
  let pinTatEl = document.getElementById('expected-tat');
  if (!pinTatEl) {
    pinTatEl = document.createElement('div');
    pinTatEl.id = 'expected-tat';
    pinTatEl.style.margin = '4px 0 12px 0';
    if (pinStatusEl && pinStatusEl.parentNode) pinStatusEl.parentNode.insertBefore(pinTatEl, pinStatusEl.nextSibling);
  }

  let hasPhone = false;
  let selectedAddressId = null;
  let selectedPaymentMethod = null;
  let agentApplied = false;
  let agentIdValue = "";
  const cart = JSON.parse(localStorage.getItem("cart") || "[]");
  let total = 0;

  (function decidePhoneVisibility() {
    const localUser = JSON.parse(localStorage.getItem("user") || "null");
    const localPhone = localUser && localUser.phone;

    if (isValidPhone(localPhone)) {
      hasPhone = true;
      if (phoneContainer) phoneContainer.style.display = "none";
      return;
    }

    fetch("config/get_user.php", { credentials: "same-origin" })
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

  if (savePhoneBtn) {
    savePhoneBtn.addEventListener("click", () => {
      const phone = (phoneInput.value || "").trim();
      if (!isValidPhone(phone)) {
        alert("Please enter a valid 10-digit mobile number.");
        phoneInput.focus();
        return;
      }
      fetch("config/save_phone.php", {
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

  async function loadAddressesAndAttach() {
    try {
      const res = await safeFetchJson("config/get_addresses.php", { credentials: "same-origin" });
      const data = res.json || [];
      if (!addressesContainer) return;
      addressesContainer.innerHTML = "";
      (data || []).forEach(address => {
        const div = document.createElement("div");
        div.classList.add("address-box");
        div.textContent = `${address.house_no}, ${address.landmark || ""}, ${address.city}, ${address.pincode}`.replace(/,\s*,/g, ",");
        div.dataset.id = address.id;
        if (address.pincode) div.dataset.pincode = address.pincode;
        div.onclick = () => {
          document.querySelectorAll(".address-box").forEach((box) => box.classList.remove("selected"));
          div.classList.add("selected");
          selectedAddressId = address.id;
          if (address.pincode) checkPincodeAndUpdateUI(address.pincode);
        };
        addressesContainer.appendChild(div);
      });
      const first = addressesContainer.querySelector('.address-box');
      if (first) {
        first.classList.add('selected');
        selectedAddressId = first.dataset.id;
        if (first.dataset.pincode) checkPincodeAndUpdateUI(first.dataset.pincode);
      }
    } catch (e) {
    }
  }
  loadAddressesAndAttach();

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

      fetch("config/save_address.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ houseNo: house_no, landmark, city, pincode }),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            alert("Address added!");
            loadAddressesAndAttach();
            if (newAddressForm) newAddressForm.style.display = "none";
            addAddressBtn && (addAddressBtn.textContent = "Add Address");
            setTimeout(() => {
              const first = addressesContainer.querySelector('.address-box');
              if (first && first.dataset.pincode) checkPincodeAndUpdateUI(first.dataset.pincode);
            }, 200);
          } else {
            alert(data.message || "Failed to add address.");
          }
        })
        .catch(() => alert("Failed to add address."));
    };
  }

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
  if (agentInput) {
    fetch("config/get_gv_partner.php", { credentials: "same-origin" })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.gv_partner_id) {
          agentInput.value = data.gv_partner_id;
        }
      })
      .catch(err => console.warn("GV Partner prefill failed:", err));
  }

  if (agentInput) {
    agentInput.addEventListener("input", function () {
      this.value = this.value.replace(/[^a-zA-Z0-9]/g, "");
    });
  }

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

      proceedBtn.disabled = true;
      proceedBtn.textContent = 'Processing...';

      const finalAmount = (method === "agent-id") ? 0 : total;

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
        if (!ok) {
          throw new Error(data.message || JSON.stringify(data));
        }

        const orderId = data.order_id || data.data?.order_id || null;

        if (method === "agent-id") {
          try { localStorage.removeItem("cart"); } catch (_) {}
          window.location.href = orderId ? ('orderplaced.php?order_id=' + encodeURIComponent(orderId)) : 'orderplaced.php';
          return;
        }

        if (method === "upi") {
          window.location.href = `upi_intent.html?amount=${encodeURIComponent(finalAmount)}&address_id=${encodeURIComponent(selectedAddressId)}&order_id=${encodeURIComponent(orderId)}`;
          return;
        }

        try { localStorage.removeItem("cart"); } catch (_) {}
        window.location.href = orderId ? ('orderplaced.php?order_id=' + encodeURIComponent(orderId)) : 'orderplaced.php';
      } catch (err) {
        console.error('Place order failed:', err);
        alert('Order request failed: ' + (err.message || err));
      } finally {
        proceedBtn.disabled = false;
        proceedBtn.textContent = 'Proceed';
      }
    });
  }

  let lastCheckedPin = null;
  let pincodeInFlight = false;
  async function checkPincodeAndUpdateUI(pin) {
    if (!pin) return;
    if (pincodeInFlight) return;
    if (pin === lastCheckedPin) return;
    lastCheckedPin = pin;
    pincodeInFlight = true;

    pinStatusEl.textContent = 'Checking pincode...';
    pinTatEl.textContent = '';

    try {
      const url = '/api/pincode_check.php?pincode=' + encodeURIComponent(pin);
      const res = await safeFetchJson(url, { credentials: 'same-origin' });
      const json = res.json || {};
      if (json.success) {
        const svc = Boolean(json.serviceable);
        const min = json.min_tat_days ?? json.min_tat ?? null;
        const max = json.max_tat_days ?? json.max_tat ?? null;
        const cost = json.shipping_cost ?? null;
        pinStatusEl.textContent = svc ? 'Serviceable' : 'Not serviceable';
        if (svc) {
          pinTatEl.textContent = (min || '?') + (max ? (' - ' + max) : '') + ' days';
          pinStatusEl.style.color = 'green';
          pinTatEl.style.color = 'green';
          if (proceedBtn) proceedBtn.disabled = false;
        } else {
          pinTatEl.textContent = '';
          pinStatusEl.style.color = 'red';
          pinTatEl.style.color = 'inherit';
          if (proceedBtn) proceedBtn.disabled = true;
        }
      } else {
        pinStatusEl.textContent = 'Unable to check pincode';
        pinStatusEl.style.color = 'orange';
        pinTatEl.textContent = '';
        if (proceedBtn) proceedBtn.disabled = false;
      }
    } catch (e) {
      console.warn('Pincode check failed', e);
      pinStatusEl.textContent = 'Error checking pincode';
      pinStatusEl.style.color = 'orange';
      pinTatEl.textContent = '';
      if (proceedBtn) proceedBtn.disabled = false;
    } finally {
      pincodeInFlight = false;
    }
  }

  (function runInitialPincodeCheck() {
    const pin = getSelectedPincode();
    if (!pin) return;
    checkPincodeAndUpdateUI(pin);
  })();

  if (addressesContainer) {
    addressesContainer.addEventListener('click', (ev) => {
      const target = ev.target.closest && ev.target.closest('.address-box');
      if (!target) return;
      const pin = target.dataset.pincode || getSelectedPincode();
      if (pin) checkPincodeAndUpdateUI(pin);
    });
  }

  const pincodeInput = document.getElementById('payment-pincode');
  if (pincodeInput) {
    pincodeInput.addEventListener('blur', () => {
      const v = (pincodeInput.value || '').trim();
      if (/^\d{6}$/.test(v)) checkPincodeAndUpdateUI(v);
    });
  }

});