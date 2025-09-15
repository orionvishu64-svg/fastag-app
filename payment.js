document.addEventListener("DOMContentLoaded", () => {

// Ensure safeFetch exists even if no other script defines it
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

  // ----- DOM refs -----
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
  const agentInput = document.getElementById("agentid"); // ✅ declare once here

  // ----- State -----
  let hasPhone = false;
  let selectedAddressId = null;
  let selectedPaymentMethod = null;
  let agentApplied = false;
  let agentIdValue = "";
  let total = 0;

// helper: basic phone check (adjust if you need)
const isValidPhone = (s) => /^[0-9]{10}$/.test(String(s || ""));
const isAlphaNum = (s) => /^[A-Za-z0-9]+$/.test(String(s || "").trim());

// Decide phone visibility
(function decidePhoneVisibility() {
  // safely read from localStorage
  const localUser = JSON.parse(localStorage.getItem("user") || "null");
  const localPhone = localUser && localUser.phone;

  if (isValidPhone(localPhone)) {
    // already have a phone → hide the phone field
    hasPhone = true;
    phoneContainer.style.display = "none";
    return;
  }

  // try server (session user) to get phone
  fetch("get_user.php", { credentials: "same-origin" })
    .then((r) => r.json())
    .then(({ success, user: u }) => {
      if (success && isValidPhone(u && u.phone)) {
        hasPhone = true;
        phoneContainer.style.display = "none";
        // persist phone back to localStorage (avoid spreading null)
        try {
          const base = (localUser && typeof localUser === "object") ? localUser : {};
          const updated = { ...base, phone: u.phone };
          localStorage.setItem("user", JSON.stringify(updated));
        } catch (_) {}
      } else {
        // still no phone → show the phone field
        phoneContainer.style.display = "";
      }
    })
    .catch(() => {
      // on error, show the phone field so user can enter it
      phoneContainer.style.display = "";
    });
})();

  // 2) Save phone
  if (savePhoneBtn) {
    savePhoneBtn.addEventListener("click", () => {
      const phone = (phoneInput.value || "").trim();
      if (!isValidPhone(phone)) {
        alert("Please enter a valid 10-digit Indian mobile number (starts with 6-9).");
        phoneInput.focus();
        return;
      }
      fetch("save_phone.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ phone }),
      })
        .then((r) => r.json())
        .then((data) => {
          if (!data || !data.success) throw new Error(data?.message || "Failed to save phone");
          hasPhone = true;
          phoneContainer.style.display = "none";
          try {
            const updated = { ...localUser, phone };
            localStorage.setItem("user", JSON.stringify(updated));
          } catch (_) {}
          alert("Phone number saved.");
        })
        .catch((err) => alert(err.message || "Error saving phone number."));
    });
  }

  // 3) Order summary
  const cart = JSON.parse(localStorage.getItem("cart") || "[]");
  orderItemsEl.innerHTML = "";
  total = 0;
  cart.forEach((item) => {
    const qty = Number(item.quantity || 1);
    const price = Number(item.price || 0);
    const lineTotal = qty * price;
    const line = document.createElement("div");
    line.textContent = `${item.name} x ${qty} - ₹${lineTotal}`;
    orderItemsEl.appendChild(line);
    total += lineTotal;
  });
  orderTotalEl.textContent = String(total);

  // 4) Load saved addresses
  fetch("get_addresses.php", { credentials: "same-origin" })
    .then((res) => res.json())
    .then((data) => {
      addressesContainer.innerHTML = "";
      data.forEach((address) => {
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
    });

  // 5) Toggle Add Address form
  addAddressBtn.onclick = () => {
    if (newAddressForm.style.display === "none" || newAddressForm.style.display === "") {
      newAddressForm.style.display = "block";
      addAddressBtn.textContent = "Close";
    } else {
      newAddressForm.style.display = "none";
      addAddressBtn.textContent = "Add Address";
    }
  };

  // 6) Save new address
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
        body: JSON.stringify({ houseNo: house_no, landmark, city, pincode }), // ✅ map correctly
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            alert("Address added!");
            // Reload address list without full reload:
            return fetch("get_addresses.php", { credentials: "same-origin" })
              .then((r) => r.json())
              .then((data2) => {
                addressesContainer.innerHTML = "";
                data2.forEach((address) => {
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
                newAddressForm.style.display = "none";
                addAddressBtn.textContent = "Add Address";
              });
          } else {
            alert(data.message || "Failed to add address.");
          }
        });
    };
  }

  // 7) Payment method selection
  document.querySelectorAll('input[name="payment_method"]').forEach((input) => {
    input.addEventListener("change", () => {
      selectedPaymentMethod = input.value;
      agentApplied = false;
      agentIdValue = "";

      if (selectedPaymentMethod === "agent-id") {
        agentBox.style.display = "block";
        if (!document.getElementById("applyidBtn")) {
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
            orderTotalEl.textContent = "0";
            alert("✅ Agent ID applied. Your order is now free.");
          });
        }
      } else {
        agentBox.style.display = "none";
        orderTotalEl.textContent = String(total);
      }
    });
  });

  // Restrict Agent ID input to alphanumeric
  if (agentInput) {
    agentInput.addEventListener("input", function () {
      this.value = this.value.replace(/[^a-zA-Z0-9]/g, "");
    });
  }

  // 8) Proceed to Pay
  proceedBtn.addEventListener("click", function () {
    // ✅ require phone saved
    if (!hasPhone) {
      alert("Please add your phone number before placing the order.");
      phoneContainer.style.display = "block";
      phoneInput?.focus();
      return;
    }

    const selectedMethod = document.querySelector("input[name='payment_method']:checked");
    if (!selectedMethod) {
      alert("Please select a payment method");
      return;
    }
    if (!selectedAddressId) {
      alert("Please select a delivery address.");
      return;
    }

    const method = selectedMethod.value;
    if (method === "agent-id" && !agentApplied) {
      alert("Please apply your Agent ID before proceeding.");
      return;
    }

    const finalAmount = (method === "agent-id") ? 0 : total;

    fetch("place_order.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({
        payment_method: method,
        transaction_id: method === "agent-id" ? agentIdValue : null, // ✅ matches backend
        amount: finalAmount,
        address_id: selectedAddressId,
        items: cart.map((i) => ({
          bank: i.bank || "",
          product_name: i.name,
          quantity: Number(i.quantity || 1),
          price: Number(i.price || 0),
        })),
      }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.status === "success") {
          if (method === "upi") {
            window.location.href = `upi_intent.html?amount=${finalAmount}&address_id=${selectedAddressId}&order_id=${data.order_id}`;
          } else if (method === "agent-id") { // ✅ removed COD
            try { localStorage.removeItem("cart"); } catch (_) {}
            window.location.href = "orderplaced.php";
          }
        } else {
          alert("Error placing order: " + (data.message || "Unknown error"));
        }
      });
  });
});
