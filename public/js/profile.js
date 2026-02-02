// /public/js/profile.js
document.addEventListener("DOMContentLoaded", function () {
  function flash(msg, ok = true) {
    const el = document.createElement("div");
    el.textContent = msg;
    el.style =
      "position:fixed;right:18px;top:18px;padding:8px 12px;border-radius:8px;" +
      "background:" +
      (ok ? "#0f9d58" : "#d64545") +
      ";color:#fff;z-index:10000;";
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2600);
  }

  function getCsrf() {
    const el = document.querySelector(
      'input[name="csrf_token"], input[name="csrf"], input[name="csrf-token"]'
    );
    return el ? el.value : "";
  }

  async function postForm(url, formData) {
    const res = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      body: formData,
    });
    const text = await res.text();
    try {
      return JSON.parse(text || "{}");
    } catch {
      throw new Error("Invalid JSON: " + text);
    }
  }

  function bindEditPartnerButton(btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      const card = btn.closest(".partner-card");
      if (!card) return;

      const existing = card.querySelector(".partner-editor-inline");
      if (existing) {
        existing.style.height = existing.scrollHeight + "px";
        requestAnimationFrame(() => {
          existing.style.transition = "height 220ms ease";
          existing.style.height = "0px";
          setTimeout(() => existing.remove(), 240);
        });
        return;
      }

      const table = btn.dataset.table || "";
      const id = btn.dataset.id || "";
      const editor = document.createElement("div");
      editor.className = "partner-editor partner-editor-inline";

      if (table === "gv_partners") {
        editor.innerHTML = `
  <div class="editor-head mb-3">
    <h5 class="mb-0">Edit GV Partner</h5>
  </div>

  <form class="inline-edit-form">
    <div class="mb-3">
      <label class="form-label">GV Partner ID</label>
      <input class="form-control"
             name="gv_partner_id"
             value="${(btn.dataset.gv_partner_id || "").replace(
               /"/g,
               "&quot;"
             )}">
    </div>

    <div class="mb-3">
      <label class="form-label">Name (optional)</label>
      <input class="form-control"
             name="name"
             value="${(btn.dataset.name || "").replace(/"/g, "&quot;")}">
    </div>
            <div class="editor-actions">
              <button type="submit" class="btn-save">Save</button>
              <button type="button" class="btn-cancel">Cancel</button>
            </div>
          </form>`;
      } else {
        editor.innerHTML = `
  <div class="editor-head mb-3">
    <h5 class="mb-0">Edit Partner</h5>
  </div>

  <form class="inline-edit-form">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Bank Name</label>
        <input class="form-control"
               name="bank_name"
               value="${(btn.dataset.bank_name || "").replace(/"/g, "&quot;")}">
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label">Partner ID</label>
        <input class="form-control"
               name="partner_id"
               value="${(btn.dataset.partner_id || "").replace(
                 /"/g,
                 "&quot;"
               )}">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Name (optional)</label>
      <input class="form-control"
             name="name"
             value="${(btn.dataset.name || "").replace(/"/g, "&quot;")}">
    </div>
            <div class="editor-actions">
              <button type="submit" class="btn-save">Save</button>
              <button type="button" class="btn-cancel">Cancel</button>
            </div>
          </form>`;
      }

      card.appendChild(editor);

      editor.style.overflow = "hidden";
      editor.style.height = "0px";
      editor.style.transition = "height 260ms ease";
      requestAnimationFrame(() => {
        editor.style.height = editor.scrollHeight + "px";
      });

      const cancelBtn = editor.querySelector(".btn-cancel");
      if (cancelBtn) {
        cancelBtn.addEventListener("click", () => {
          editor.style.height = editor.scrollHeight + "px";
          requestAnimationFrame(() => {
            editor.style.transition = "height 220ms ease";
            editor.style.height = "0px";
            setTimeout(() => editor.remove(), 240);
          });
        });
      }

      const form = editor.querySelector(".inline-edit-form");
      form.addEventListener("submit", async function (ev) {
        ev.preventDefault();

        const fd = new FormData();
        fd.append("action", "update_partner");
        fd.append("id", id);
        fd.append("table", table);

        if (table === "gv_partners") {
          fd.append(
            "gv_partner_id",
            this.elements["gv_partner_id"].value.trim()
          );
          fd.append("name", this.elements["name"].value.trim());
        } else {
          fd.append("bank_name", this.elements["bank_name"].value.trim());
          fd.append("partner_id", this.elements["partner_id"].value.trim());
          fd.append("name", this.elements["name"].value.trim());
        }
        const csrf = getCsrf();
        if (csrf) fd.append("csrf_token", csrf);

        try {
          const json = await postForm("config/update_profile.php", fd);
          if (json.success) {
            const titleEl = card.querySelector(".partner-title");
            const editBtn = card.querySelector(".edit-partner-btn");

            if (table === "gv_partners") {
              const gv = fd.get("gv_partner_id");
              const nm = fd.get("name");
              if (titleEl)
                titleEl.textContent = "GV - " + gv + (nm ? " — " + nm : "");
              if (editBtn) {
                editBtn.dataset.gv_partner_id = gv;
                editBtn.dataset.name = nm;
              }
            } else {
              const bank = fd.get("bank_name");
              const pid = fd.get("partner_id");
              const nm = fd.get("name");
              let txt = (bank || "") + " — " + (pid || "");
              if (nm) txt += " (" + nm + ")";
              if (titleEl) titleEl.textContent = txt;
              if (editBtn) {
                editBtn.dataset.bank_name = bank;
                editBtn.dataset.partner_id = pid;
                editBtn.dataset.name = nm;
              }
            }

            editor.style.height = editor.scrollHeight + "px";
            requestAnimationFrame(() => {
              editor.style.transition = "height 220ms ease";
              editor.style.height = "0px";
              setTimeout(() => editor.remove(), 240);
            });

            flash(json.message || "Updated", true);
          } else {
            flash(json.message || "Update failed", false);
          }
        } catch (err) {
          console.error(err);
          flash("Network/server error", false);
        }
      });
    });
  }

  function attachPartnerInlineEditorHandlers() {
    document.querySelectorAll(".edit-partner-btn").forEach((btn) => {
      if (!btn._editBound) {
        bindEditPartnerButton(btn);
        btn._editBound = true;
      }
    });
  }

  const addPartnerBtn = document.getElementById("add-partner-btn");
  const addPartnerHost = document.getElementById("add-partner-editor");
  const partnersGrid = document.querySelector(".partners-grid");

  if (addPartnerBtn && addPartnerHost && partnersGrid) {
    addPartnerBtn.addEventListener("click", function () {
      const existing = addPartnerHost.querySelector(".partner-editor");
      if (existing) {
        existing.style.height = existing.scrollHeight + "px";
        requestAnimationFrame(() => {
          existing.style.transition = "height 220ms ease";
          existing.style.height = "0px";
          setTimeout(() => existing.remove(), 240);
        });
        return;
      }

      const chooser = document.createElement("div");
      chooser.className = "partner-editor";
      chooser.innerHTML = `
  <div class="editor-head mb-2">
    <h5 class="mb-0">Add Partner</h5>
  </div>

  <p class="text-muted small mb-3">
    Choose what type of partner ID you want to add.
  </p>

  <div class="btn-group w-100 mb-2">
    <button class="btn btn-primary" data-type="partner" type="button">Partner ID</button>
    <button class="btn btn-outline-secondary" data-type="gv" type="button">GV Partner ID</button>
  </div>
`;
      addPartnerHost.appendChild(chooser);

      chooser.style.overflow = "hidden";
      chooser.style.height = "0px";
      chooser.style.transition = "height 260ms ease";
      requestAnimationFrame(
        () => (chooser.style.height = chooser.scrollHeight + "px")
      );

      const cancelChooser = chooser.querySelector(".btn-cancel");
      if (cancelChooser) {
        cancelChooser.addEventListener("click", () => {
          chooser.style.height = chooser.scrollHeight + "px";
          requestAnimationFrame(() => {
            chooser.style.transition = "height 220ms ease";
            chooser.style.height = "0px";
            setTimeout(() => chooser.remove(), 240);
          });
        });
      }

      chooser.querySelectorAll("[data-type]").forEach((button) => {
        button.addEventListener("click", function () {
          const type = this.getAttribute("data-type");
          const editor = document.createElement("div");
          editor.className = "partner-editor";

          if (type === "gv") {
            editor.innerHTML = `
  <div class="editor-head mb-3">
    <h5 class="mb-0">Add GV Partner</h5>
  </div>

  <form class="inline-edit-form">
    <div class="mb-3">
      <label class="form-label">GV Partner ID</label>
      <input class="form-control" name="gv_partner_id" placeholder="e.g. GV-ABC123">
    </div>

    <div class="mb-3">
      <label class="form-label">Name (optional)</label>
      <input class="form-control" name="name" placeholder="Display name">
    </div>
                <div class="editor-actions">
                  <button type="submit" class="btn-save">Add GV Partner</button>
                  <button type="button" class="btn-cancel">Cancel</button>
                </div>
                <input type="hidden" name="table" value="gv_partners">
              </form>`;
          } else {
            editor.innerHTML = `
  <div class="editor-head mb-3">
    <h5 class="mb-0">Add Partner</h5>
  </div>

  <form class="inline-edit-form">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Bank Name</label>
        <input class="form-control" name="bank_name" placeholder="e.g. HDFC / SBI">
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label">Partner ID</label>
        <input class="form-control" name="partner_id" placeholder="e.g. ABC12345">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Name (optional)</label>
      <input class="form-control" name="name" placeholder="Display name">
    </div>
                <div class="editor-actions">
                  <button type="submit" class="btn-save">Add Partner</button>
                  <button type="button" class="btn-cancel">Cancel</button>
                </div>
                <input type="hidden" name="table" value="partners">
              </form>`;
          }

          chooser.replaceWith(editor);
          editor.style.overflow = "hidden";
          editor.style.height = "0px";
          editor.style.transition = "height 260ms ease";
          requestAnimationFrame(
            () => (editor.style.height = editor.scrollHeight + "px")
          );

          const cancelBtn = editor.querySelector(".btn-cancel");
          if (cancelBtn) {
            cancelBtn.addEventListener("click", () => {
              editor.style.height = editor.scrollHeight + "px";
              requestAnimationFrame(() => {
                editor.style.transition = "height 220ms ease";
                editor.style.height = "0px";
                setTimeout(() => editor.remove(), 240);
              });
            });
          }

          const form = editor.querySelector(".inline-edit-form");
          form.addEventListener("submit", async function (e) {
            e.preventDefault();
            const table =
              (this.elements["table"] && this.elements["table"].value) ||
              "partners";
            const fd = new FormData();
            fd.append("action", "create_partner");
            fd.append("table", table);

            if (table === "gv_partners") {
              const gid = (this.elements["gv_partner_id"].value || "").trim();
              const name = (this.elements["name"].value || "").trim();
              if (!gid) {
                flash("GV Partner ID required", false);
                return;
              }
              fd.append("gv_partner_id", gid);
              fd.append("name", name);
            } else {
              const bank = (this.elements["bank_name"].value || "").trim();
              const pid = (this.elements["partner_id"].value || "").trim();
              const name = (this.elements["name"].value || "").trim();
              if (!bank || !pid) {
                flash("Bank name and Partner ID are required", false);
                return;
              }
              fd.append("bank_name", bank);
              fd.append("partner_id", pid);
              fd.append("name", name);
            }

            const csrf = getCsrf();
            if (csrf) fd.append("csrf_token", csrf);

            try {
              const res = await postForm("config/update_profile.php", fd);
              if (res && res.success && res.partner) {
                const p = res.partner;
                const card = document.createElement("div");
                card.className = "partner-card";
                card.setAttribute("data-row-id", p.id);
                card.setAttribute("data-table", table);

                let title;
                if (table === "gv_partners") {
                  title = `GV - ${p.gv_partner_id || ""}${
                    p.name ? " — " + p.name : ""
                  }`;
                } else {
                  title = `${p.name || ""} — ${p.partner_id || ""}${
                    p.bank_name ? " (" + p.bank_name + ")" : ""
                  }`;
                }

                card.innerHTML = `
                  <div class="partner-title">${title}</div>
                  <div class="partner-meta">Saved: ${p.created_at || ""}</div>
                  <div class="card-actions">
                    <button class="btn btn-secondary edit-partner-btn"
                            data-id="${p.id}"
                            data-table="${table}"
                            data-bank_name="${(p.bank_name || "").replace(
                              /"/g,
                              "&quot;"
                            )}"
                            data-partner_id="${(p.partner_id || "").replace(
                              /"/g,
                              "&quot;"
                            )}"
                            data-gv_partner_id="${(
                              p.gv_partner_id || ""
                            ).replace(/"/g, "&quot;")}"
                            data-name="${(p.name || "").replace(
                              /"/g,
                              "&quot;"
                            )}">
                      Edit
                    </button>
                    <form method="post" onsubmit="return confirm('Delete partner?');" style="display:inline;">
                      <input type="hidden" name="action" value="delete_partner">
                      <input type="hidden" name="id" value="${p.id}">
                      <input type="hidden" name="table" value="${table}">
                      <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                    </form>
                  </div>`;

                partnersGrid.prepend(card);
                attachPartnerInlineEditorHandlers();

                editor.style.height = editor.scrollHeight + "px";
                requestAnimationFrame(() => {
                  editor.style.transition = "height 200ms ease";
                  editor.style.height = "0px";
                  setTimeout(() => editor.remove(), 240);
                });

                flash(res.message || "Partner added", true);
              } else {
                flash(res && res.message ? res.message : "Save failed", false);
              }
            } catch (err) {
              console.error(err);
              flash("Network/server error", false);
            }
          });
        });
      });
    });
  }

  attachPartnerInlineEditorHandlers();

  function bindEditAddressButton(btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      const card = btn.closest(".address-card");
      if (!card) return;

      const existing = card.querySelector(".address-editor-inline");
      if (existing) {
        existing.style.height = existing.scrollHeight + "px";
        requestAnimationFrame(() => {
          existing.style.transition = "height 220ms ease";
          existing.style.height = "0px";
          setTimeout(() => existing.remove(), 240);
        });
        return;
      }

      const id = btn.dataset.id || "";
      const house = btn.dataset.house_no || "";
      const landmark = btn.dataset.landmark || "";
      const city = btn.dataset.city || "";
      const pincode = btn.dataset.pincode || "";

      const editor = document.createElement("div");
      editor.className = "address-editor address-editor-inline";
      editor.innerHTML = `
  <div class="editor-head mb-3">
    <h5 class="mb-0">Edit Address</h5>
  </div>

  <form class="inline-edit-form">
    <div class="mb-3">
      <label class="form-label">House / Flat No</label>
      <input class="form-control" name="house_no" value="${house}">
    </div>

    <div class="mb-3">
      <label class="form-label">Landmark</label>
      <input class="form-control" name="landmark" value="${landmark}">
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">City</label>
        <input class="form-control" name="city" value="${city}">
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label">Pincode</label>
        <input class="form-control" name="pincode" value="${pincode}">
      </div>
    </div>
          </div>
          <div class="editor-actions">
            <button type="submit" class="btn-save">Save</button>
            <button type="button" class="btn-cancel">Cancel</button>
          </div>
        </form>`;

      card.appendChild(editor);

      editor.style.overflow = "hidden";
      editor.style.height = "0px";
      editor.style.transition = "height 260ms ease";
      requestAnimationFrame(
        () => (editor.style.height = editor.scrollHeight + "px")
      );

      const cancelBtn = editor.querySelector(".btn-cancel");
      cancelBtn.addEventListener("click", () => {
        editor.style.height = editor.scrollHeight + "px";
        requestAnimationFrame(() => {
          editor.style.transition = "height 220ms ease";
          editor.style.height = "0px";
          setTimeout(() => editor.remove(), 240);
        });
      });

      const form = editor.querySelector(".inline-edit-form");
      form.addEventListener("submit", async function (ev) {
        ev.preventDefault();

        const fd = new FormData();
        fd.append("action", "save_address");
        fd.append("id", id);
        fd.append("house_no", this.elements["house_no"].value.trim());
        fd.append("landmark", this.elements["landmark"].value.trim());
        fd.append("city", this.elements["city"].value.trim());
        fd.append("pincode", this.elements["pincode"].value.trim());
        const csrf = getCsrf();
        if (csrf) fd.append("csrf_token", csrf);

        try {
          const json = await postForm("config/update_profile.php", fd);
          if (json.success) {
            flash(json.message || "Address updated", true);
            setTimeout(() => location.reload(), 500);
          } else {
            flash(json.message || "Update failed", false);
          }
        } catch (err) {
          console.error(err);
          flash("Network/server error", false);
        }
      });
    });
  }

  function attachAddressInlineEditorHandlers() {
    document.querySelectorAll(".edit-address-btn").forEach((btn) => {
      if (!btn._editBound) {
        bindEditAddressButton(btn);
        btn._editBound = true;
      }
    });
  }

  attachAddressInlineEditorHandlers();

  const addAddressBtn = document.getElementById("add-address-btn");
  const addAddressHost = document.getElementById("add-address-editor");

  if (addAddressBtn && addAddressHost) {
    addAddressBtn.addEventListener("click", function () {
      const existing = addAddressHost.querySelector(".address-editor");
      if (existing) {
        existing.style.height = existing.scrollHeight + "px";
        requestAnimationFrame(() => {
          existing.style.transition = "height 220ms ease";
          existing.style.height = "0px";
          setTimeout(() => existing.remove(), 240);
        });
        return;
      }

      const editor = document.createElement("div");
      editor.className = "address-editor";
      editor.innerHTML = `
  <div class="editor-head mb-3">
    <h5 class="mb-0">Add Address</h5>
  </div>

  <p class="text-muted small">Save a new shipping address.</p>

  <form class="inline-edit-form">
    <div class="mb-3">
      <label class="form-label">House / Flat No</label>
      <input class="form-control" name="house_no">
    </div>

    <div class="mb-3">
      <label class="form-label">Landmark</label>
      <input class="form-control" name="landmark">
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">City</label>
        <input class="form-control" name="city">
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label">Pincode</label>
        <input class="form-control" name="pincode">
      </div>
    </div>
          </div>
          <div class="editor-actions">
            <button type="submit" class="btn-save">Save Address</button>
            <button type="button" class="btn-cancel">Cancel</button>
          </div>
        </form>`;

      addAddressHost.appendChild(editor);

      editor.style.overflow = "hidden";
      editor.style.height = "0px";
      editor.style.transition = "height 260ms ease";
      requestAnimationFrame(
        () => (editor.style.height = editor.scrollHeight + "px")
      );

      const cancelBtn = editor.querySelector(".btn-cancel");
      cancelBtn.addEventListener("click", () => {
        editor.style.height = editor.scrollHeight + "px";
        requestAnimationFrame(() => {
          editor.style.transition = "height 220ms ease";
          editor.style.height = "0px";
          setTimeout(() => editor.remove(), 240);
        });
      });

      const form = editor.querySelector(".inline-edit-form");
      form.addEventListener("submit", async function (e) {
        e.preventDefault();

        const fd = new FormData();
        fd.append("action", "create_address");
        fd.append("house_no", this.elements["house_no"].value.trim());
        fd.append("landmark", this.elements["landmark"].value.trim());
        fd.append("city", this.elements["city"].value.trim());
        fd.append("pincode", this.elements["pincode"].value.trim());
        const csrf = getCsrf();
        if (csrf) fd.append("csrf_token", csrf);

        try {
          const json = await postForm("config/update_profile.php", fd);
          if (json.success) {
            flash(json.message || "Address saved", true);
            setTimeout(() => location.reload(), 500);
          } else {
            flash(json.message || "Address save failed", false);
          }
        } catch (err) {
          console.error(err);
          flash("Network/server error", false);
        }
      });
    });
  }
});
