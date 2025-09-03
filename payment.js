document.addEventListener("DOMContentLoaded", () => {
  const user = JSON.parse(localStorage.getItem("user"));
  if (!user) {
    alert("Please log in to continue");
    window.location.href = "login.html";
    return;
  }

  const addressesContainer = document.getElementById("saved-addresses");
  const addAddressBtn = document.getElementById("add-address-btn");
  const newAddressForm = document.getElementById("new-address-form");
  const saveAddressBtn = document.getElementById("save-address");
  const proceedBtn = document.getElementById("proceed-btn");

  let selectedAddressId = null;
  let selectedPaymentMethod = null;
  let agentApplied = false;
  let agentIdValue = "";

  // 🛒 Load order items and total
  const orderItemsEl = document.getElementById("order-items");
  const orderTotalEl = document.getElementById("order-total");
  const cart = JSON.parse(localStorage.getItem("cart") || "[]");
  let total = 0;
  orderItemsEl.innerHTML = "";
  cart.forEach((item) => {
    const line = document.createElement("div");
    const lineTotal = (item.price || 0) * (item.quantity || 1);
    line.textContent = `${item.name} x ${item.quantity} - ₹${lineTotal}`;
    orderItemsEl.appendChild(line);
    total += lineTotal;
  });
  orderTotalEl.textContent = String(total);

  // 📦 Load saved addresses
  fetch("get_addresses.php")
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

  // ➕ Toggle Add Address form
  addAddressBtn.onclick = () => {
    if (newAddressForm.style.display === "none" || newAddressForm.style.display === "") {
      newAddressForm.style.display = "block";
      addAddressBtn.textContent = "Close";
    } else {
      newAddressForm.style.display = "none";
      addAddressBtn.textContent = "Add Address";
    }
  };

  // 💾 Save new address
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

      fetch("add_address.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ house_no, landmark, city, pincode }),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            alert("Address added!");
            location.reload();
          } else {
            alert(data.message || "Failed to add address.");
          }
        });
    };
  }

  // 💳 Payment method selection
  document.querySelectorAll('input[name="payment_method"]').forEach((input) => {
    input.addEventListener("change", () => {
      selectedPaymentMethod = input.value;
      agentApplied = false;

      const agentBox = document.getElementById("agent-id-box");
     // Show/hide agent box and inject styled Apply button
if (input.value === "agent-id") {
  agentBox.style.display = "block";
  agentBox.classList.add("inline");
  if (!document.getElementById("applyidBtn")) {
    const applyBtn = document.createElement("button");
    applyBtn.id = "applyidBtn";
    applyBtn.className = "apply-btn";
    applyBtn.textContent = "Apply ID";
    agentBox.appendChild(applyBtn);

    applyBtn.addEventListener("click", () => {
      const agentInput = document.getElementById("agentid");
      const val = agentInput.value.trim();
      if (!val) { alert("Please enter your Agent ID"); return; }
      if (!/^[a-zA-Z0-9]+$/.test(val)) { alert("Agent ID must contain only letters and numbers."); return; }
      agentApplied = true;
      agentIdValue = val;
      orderTotalEl.textContent = "0";
      alert("✅ Agent ID applied. Your order is now free.");
    });
  }
} else {
  agentBox.style.display = "none";
  agentBox.classList.remove("inline");
  orderTotalEl.textContent = String(total);
}
    });
  });

  // Restrict Agent ID input
  const agentInput = document.getElementById("agentid");
  if (agentInput) {
    agentInput.addEventListener("input", function () {
      this.value = this.value.replace(/[^a-zA-Z0-9]/g, "");
    });
  }

  // 🚀 Proceed to Pay
  proceedBtn.addEventListener("click", function () {
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
      body: JSON.stringify({
        payment_method: method,
        agent_id: agentIdValue || null,
        amount: finalAmount,
        address_id: selectedAddressId,
        items: cart.map((i) => ({
          bank: i.bank || "",
          product_name: i.name,
          quantity: i.quantity || 1,
          price: i.price || 0,
        })),
      }),
    })
      .then((res) => res.json())
.then((data) => {
  if (data.status === "success") {
    if (method === "upi") {
      // Go to UPI intent page, but keep cart until payment is confirmed
      window.location.href = `upi_intent.html?amount=${finalAmount}&address_id=${selectedAddressId}&order_id=${data.order_id}`;
    } else if (method === "agent-id" || method === "cod") {
      // For Agent-ID we can clear cart immediately
      try { localStorage.removeItem("cart"); } catch (_) {}
      window.location.href = "orderplaced.html";  // ✅ confirmation page
    }
  } else {
    alert("Error placing order: " + data.message);
  }
        });
      });
  });
