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
            console.error("Network error:", err);
            alert("Network error. Please try again.");
            throw err;
          });
      };
    }
  })();

  const isValidPhone = s => /^[6-9][0-9]{9}$/.test(String(s || "").trim());
  const isAlphaNum = s => /^[A-Za-z0-9]+$/.test(String(s || "").trim());
  const fmtMoney = n => Number(n || 0).toFixed(2);

  const csrfToken = (() => {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  })();

  const addressesContainer = document.getElementById("saved-addresses");
  const addAddressBtn = document.getElementById("add-address-btn");
  const cancelAddressBtn = document.getElementById("cancel-address");
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

  const addressSection = document.getElementById("step1-card");
  const paymentSection = document.getElementById("step2-card");

  let hasPhone = false;
  let selectedAddressId = null;
  let selectedAddressPincode = null;
  let selectedPaymentMethod = null;

  let agentApplied = false;
  let agentIdValue = "";

  const cart = (() => {
    try { return JSON.parse(localStorage.getItem("cart") || "[]"); }
    catch { return []; }
  })();

  let total = 0;

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.innerText = value;
}

  if (paymentSection && !paymentSection.classList.contains('hidden')) {
    paymentSection.classList.add('hidden');
  }

  if (paymentSection && !document.getElementById("payment-back-btn")) {
    const backBtn = document.createElement("button");
    backBtn.id = "payment-back-btn";
    backBtn.type = "button";
    backBtn.className = "btn-outline";
    backBtn.textContent = "← Back to address";
    backBtn.style.marginBottom = "10px";
    paymentSection.insertBefore(backBtn, paymentSection.firstChild);
    backBtn.addEventListener("click", () => {
      if (addressSection) addressSection.style.display = "";
      if (paymentSection) paymentSection.classList.add("hidden");
      if (proceedBtn) { proceedBtn.disabled = false; proceedBtn.textContent = 'Proceed to Payment →'; }
      updateProceedState();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  (function decidePhoneVisibility() {
    const localUser = (() => { try { return JSON.parse(localStorage.getItem("user") || "null"); } catch(e){ return null; } })();
    const localPhone = localUser && localUser.phone;

    if (isValidPhone(localPhone)) {
      hasPhone = true;
      if (phoneContainer) phoneContainer.style.display = "none";
      return;
    }

    safeFetch("config/get_user.php", { credentials: "same-origin" })
      .then(r => r.json())
      .then(({ success, user }) => {
        if (success && isValidPhone(user && user.phone)) {
          hasPhone = true;
          if (phoneContainer) phoneContainer.style.display = "none";
          try {
            const base = (localUser && typeof localUser === "object") ? localUser : {};
            const updated = { ...base, phone: user.phone };
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
        alert("Please enter a valid 10-digit mobile number (starting with 6-9).");
        phoneInput.focus();
        return;
      }
      savePhoneBtn.disabled = true;
      savePhoneBtn.textContent = "Saving...";
      safeFetch("config/save_phone.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken
        },
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
          updateProceedState();
        })
        .catch(err => alert(err.message || "Error saving phone number."))
        .finally(() => {
          savePhoneBtn.disabled = false;
          savePhoneBtn.textContent = "Save";
        });
    });
  }

  function renderCart() {
    if (!orderItemsEl) return;
    orderItemsEl.innerHTML = "";
    total = 0;
    if (!cart.length) {
      orderItemsEl.textContent = "Your cart is empty.";
      orderTotalEl && (orderTotalEl.textContent = fmtMoney(0));
      return;
    }
    cart.forEach(item => {
      const qty = Number(item.quantity || 1);
      const price = Number(item.price || 0);
      const lineTotal = qty * price;
      const lineEl = document.createElement("div");
      lineEl.className = "order-item";
      lineEl.textContent = `${item.name} x ${qty} - ₹${fmtMoney(lineTotal)}`;
      orderItemsEl.appendChild(lineEl);
      total += lineTotal;
    });
    orderTotalEl && (orderTotalEl.textContent = fmtMoney(total));
  }
  renderCart();

  async function loadAddressesAndAttach() {
    try {
      const res = await safeFetch("/config/get_addresses.php", { credentials: "same-origin" });
      let data = [];
      try { data = await res.json(); } catch (_) { data = []; }
      if (!addressesContainer) return;
      addressesContainer.innerHTML = "";

      (data || []).forEach(address => {
        const div = document.createElement("div");
        div.classList.add("address-box");
        div.setAttribute("role", "option");
        div.tabIndex = 0;
        div.textContent = `${address.house_no || ""}${address.landmark ? ", " + address.landmark : ""}, ${address.city || ""}${address.pincode ? ", " + address.pincode : ""}`;
        div.dataset.id = String(address.id);
        if (address.pincode) div.dataset.pincode = String(address.pincode);

        const selectFn = () => {
          document.querySelectorAll(".address-box").forEach((box) => box.classList.remove("selected"));
          div.classList.add("selected");
          selectedAddressId = div.dataset.id;
          selectedAddressPincode = div.dataset.pincode || null;

          const rightAddrEl = document.getElementById('right-selected-address');
          if (rightAddrEl) {
            rightAddrEl.textContent = `${address.house_no || ''}${address.landmark ? ', ' + address.landmark : ''}, ${address.city || ''}${address.pincode ? ', ' + address.pincode : ''}`;
          }

          const itemsCountEl = document.getElementById('items-count');
          if (itemsCountEl) itemsCountEl.textContent = (cart && cart.length) ? (cart.length + ' items') : '0 items';
          const rightSubtotal = document.getElementById('right-subtotal');
          const rightTotal = document.getElementById('right-total');
          if (rightSubtotal) rightSubtotal.textContent = fmtMoney(total);
          if (rightTotal) rightTotal.textContent = fmtMoney(total);

          if (selectedAddressPincode) checkPincode(selectedAddressPincode);

          updateProceedState();
        };

        div.addEventListener("click", selectFn);
        div.addEventListener("keydown", (ev) => {
          if (ev.key === "Enter" || ev.key === " ") {
            ev.preventDefault();
            selectFn();
          }
        });

        addressesContainer.appendChild(div);
      });

      updateProceedState();
    } catch (e) {
      console.warn("Failed to load addresses:", e);
    }
  }
  loadAddressesAndAttach();

  if (addAddressBtn) {
    addAddressBtn.addEventListener("click", () => {
      if (!newAddressForm) return;
      const visible = !(newAddressForm.style.display === "block");
      newAddressForm.style.display = visible ? "block" : "none";
      addAddressBtn.textContent = visible ? "Close" : "+ Add New Address";
    });
  }

  if (saveAddressBtn) {
    saveAddressBtn.addEventListener("click", () => {
      const house_no = (document.getElementById("payment-house-no").value || "").trim();
      const landmark = (document.getElementById("payment-landmark").value || "").trim();
      const city = (document.getElementById("payment-city").value || "").trim();
      const pincode = (document.getElementById("payment-pincode").value || "").trim();

      if (!house_no || !city || !pincode) {
        alert("Please fill all required address fields.");
        return;
      }
      if (!/^[1-9][0-9]{5}$/.test(pincode)) {
        alert("Please enter a valid 6-digit pincode.");
        return;
      }

      saveAddressBtn.disabled = true;
      saveAddressBtn.textContent = "Saving...";

      safeFetch("config/save_address.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
        body: JSON.stringify({ houseNo: house_no, landmark, city, pincode }),
      })
        .then(r => r.json())
        .then(data => {
          if (data && data.success) {
            loadAddressesAndAttach();
            if (newAddressForm) newAddressForm.style.display = "none";
            addAddressBtn && (addAddressBtn.textContent = "+ Add New Address");
          } else {
            alert(data && data.message ? data.message : "Failed to add address.");
          }
        })
        .catch(() => {
          alert("Failed to add address.");
        })
        .finally(() => {
          saveAddressBtn.disabled = false;
          saveAddressBtn.textContent = "Save Address";
        });
    });
  }

  document.querySelectorAll("input[name='payment_method']").forEach(input => {
    input.addEventListener("change", () => {
      selectedPaymentMethod = input.value;
      agentApplied = false;
      agentIdValue = "";

      if (input.value === "agent-id") {
        agentBox.style.display = "block";

        let applyBtn = agentBox.querySelector(".apply-btn");
        if (!applyBtn) {
          applyBtn = document.createElement("button");
          applyBtn.type = "button";
          applyBtn.className = "btn apply-btn";
          applyBtn.textContent = "Apply ID";
          applyBtn.style.marginLeft = "8px";
          agentBox.appendChild(applyBtn);
        }

        applyBtn.onclick = () => {
          const val = agentInput.value.trim();

          if (!val || !isAlphaNum(val)) {
            alert("Please enter a valid Agent ID");
            return;
          }

          agentApplied = true;
          agentIdValue = val;

          total = 0;
          orderTotalEl.textContent = fmtMoney(0);
          document.getElementById("right-total").textContent = fmtMoney(0);

          alert("Agent ID applied. Order amount is ₹0");
          updateProceedState();
        };

      } else {
        agentBox.style.display = "none";
        agentApplied = false;
        agentIdValue = "";
        renderCart();
      }

      updateProceedState();
    });
  });

  if (agentInput) {
    safeFetch("config/get_gv_partner.php", { credentials: "same-origin" })
      .then(r => r.json())
      .then(data => {
        if (data && data.success && data.gv_partner_id) {
          agentInput.value = data.gv_partner_id;
        }
      })
      .catch(err => console.warn("GV Partner prefill failed:", err));

    agentInput.addEventListener("input", function () {
      this.value = this.value.replace(/[^a-zA-Z0-9]/g, "");
    });
  }

  function checkPincode(pincode) {
    const pincodeStatus = document.getElementById('pincode-status');
    const expectedTat = document.getElementById('expected-tat');
    if (!pincode) {
      if (pincodeStatus) pincodeStatus.textContent = 'Select address to check pincode';
      if (expectedTat) expectedTat.textContent = '';
      return;
    }
    if (pincodeStatus) pincodeStatus.textContent = 'Checking pincode...';

    safeFetch('api/pincode_check.php?pincode=' + encodeURIComponent(pincode), { credentials: 'same-origin' })
      .then(r => r.json().catch(() => ({})))
      .then(js => {
        console.debug('pincode_check response:', js);

        const deliverable = js.deliverable ?? js.is_deliverable ?? js.available ?? (js.data && js.data.available) ?? false;
        const tat =
          js.tat ??
          js.expected_tat ??
          js.tat_text ??
          js.delivery_tat ??
          js.message ??
          (js.data && (js.data.tat ?? js.data.tat_text)) ??
          '';

        if (deliverable) {
          if (pincodeStatus) pincodeStatus.textContent = 'Delivery available';
          if (expectedTat) {
            expectedTat.textContent = tat ? `Expected delivery: ${tat}` : '';
          }
        } else {
          if (pincodeStatus) pincodeStatus.textContent = 'Delivery not available to this pincode';
          if (expectedTat) expectedTat.textContent = '';
        }
      })
      .catch((err) => {
        console.warn('Pincode check failed', err);
        if (pincodeStatus) pincodeStatus.textContent = 'Unable to check pincode currently';
        if (expectedTat) expectedTat.textContent = '';
      });
  }

  function updateProceedState() {
    const addressOk = !!selectedAddressId;
    const phoneOk = hasPhone || phoneContainer.style.display === "none";
    let agentOk = true;

    if (selectedPaymentMethod === "agent-id") {
      agentOk = agentApplied;
    }

    proceedBtn.disabled = !(addressOk && phoneOk && agentOk);
  }

  document.addEventListener('change', updateProceedState);
  document.addEventListener('input', updateProceedState);

  if (proceedBtn) {
    proceedBtn.addEventListener("click", (ev) => {
      ev.preventDefault();
      if (!selectedAddressId) { alert("Please select a delivery address."); return; }
      if (!hasPhone) {
        if (phoneContainer) phoneContainer.style.display = "";
        alert("Please add your phone number before placing the order.");
        return;
      }

      if (addressSection) addressSection.style.display = "none";
      if (paymentSection) paymentSection.classList.remove("hidden");

      proceedBtn.disabled = true;
      proceedBtn.textContent = 'Proceeding...';

      let placeOrderBtn = document.getElementById('place-order-btn');
      if (!placeOrderBtn) {
        placeOrderBtn = document.createElement('button');
        placeOrderBtn.id = 'place-order-btn';
        placeOrderBtn.className = 'btn';
        placeOrderBtn.textContent = 'Place Order';
        paymentSection.appendChild(document.createElement('div')).style.height = '8px';
        paymentSection.appendChild(placeOrderBtn);
      }

      const back = document.getElementById('payment-back-btn');
      if (back) back.style.display = '';

      if (!placeOrderBtn.dataset.wired) {
        placeOrderBtn.dataset.wired = "1";
        placeOrderBtn.addEventListener("click", () => placeOrder(placeOrderBtn));
      }
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

async function placeOrder(btn) {
  const chosen = document.querySelector("input[name='payment_method']:checked");
  if (!chosen) {
    alert("Select payment method");
    return;
  }

  // 🟢 UPI → open bottom sheet
  if (chosen.value === "upi") {
    openUpiSheet(cart.length, total);
    return;
  }

  // 🟢 AGENT ID
  if (chosen.value === "agent-id" && !agentApplied) {
    alert("Please apply Agent ID");
    return;
  }

  // Agent order payload
  const payload = {
    payment_method: "agent-id",
    transaction_id: agentIdValue,
    amount: 0,
    address_id: selectedAddressId,
    items: cart.map(i => ({
      bank: i.bank || "",
      product_name: i.name,
      quantity: Number(i.quantity || 1),
      price: Number(i.price || 0)
    }))
  };

  btn.disabled = true;
  btn.innerText = "Placing Order...";

  try {
    const r = await safeFetch("/place_order.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken
      },
      credentials: "same-origin",
      body: JSON.stringify(payload)
    });

    const data = await r.json();
    if (!data.success) throw new Error(data.message || "Order failed");

    localStorage.removeItem("cart");
    window.location.href = `orderplaced.php?order_id=${data.order_id}`;

  } catch (e) {
    alert("Order failed");
    btn.disabled = false;
    btn.innerText = "Place Order";
  }
}
const backdrop = document.getElementById('upi-backdrop');
const sheet = document.getElementById('upi-sheet');

function openUpiSheet(items, amount) {
  setText('upi-items', items);
  setText('upi-amount', amount.toFixed(2));

  backdrop.classList.remove('hidden');
  sheet.classList.remove('hidden');

  requestAnimationFrame(() => {
    backdrop.classList.add('show');
    sheet.classList.add('show');
  });

  document.body.style.overflow = 'hidden';
}

function closeUpiSheet() {
  backdrop.classList.remove('show');
  sheet.classList.remove('show');
  document.body.style.overflow = '';

  setTimeout(() => {
    backdrop.classList.add('hidden');
    sheet.classList.add('hidden');
  }, 300);
}

if (backdrop) backdrop.onclick = closeUpiSheet;
const cancelBtn = document.getElementById('upiCancel');
if (cancelBtn) cancelBtn.onclick = closeUpiSheet;
const upiPayBtn = document.getElementById('upiPayNow');

if (upiPayBtn) {
  upiPayBtn.onclick = async () => {
    upiPayBtn.disabled = true;
    upiPayBtn.innerText = "Initializing UPI…";

    try {
      const res = await safeFetch('/api/create_upi_payment.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          address_id: selectedAddressId,
          items: cart
        })
      }).then(r => r.json());

      if (!res.success || !res.token || !res.order_code) {
        throw new Error(res.message || "Unable to start payment");
      }

      startUpiPolling(res.token);
      startCountdown();

      const upiUrl =
        "upi://pay" +
        "?pa=apnapaymentbbps@yesbank" +
        "&pn=" + encodeURIComponent("ApnaPayment") +
        "&am=" + encodeURIComponent(res.amount) +
        "&cu=INR" +
        "&tn=" + encodeURIComponent("Tag Payment") +
        "&tr=" + encodeURIComponent(res.order_code);

      window.location.href = upiUrl;

    } catch (e) {
      alert(e.message || "Payment failed");
      upiPayBtn.disabled = false;
      upiPayBtn.innerText = "Pay Now";
    }
  };
}
});