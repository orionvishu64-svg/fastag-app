// /public/js/profile.js — inline slide-down editor + AJAX profile/address + ADD PARTNER (chooser)
document.addEventListener('DOMContentLoaded', function () {

  // --- tiny toast ---
  function flash(msg, ok = true) {
    const el = document.createElement('div');
    el.textContent = msg;
    el.style = 'position:fixed;right:18px;top:18px;padding:8px 12px;border-radius:8px;' +
               'background:' + (ok ? '#0f9d58' : '#d64545') + ';color:#fff;z-index:10000;';
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2600);
  }

  // CSRF helper
  function getCsrf() {
    const el = document.querySelector('input[name="csrf_token"], input[name="csrf"], input[name="csrf-token"]');
    return el ? el.value : '';
  }

  // POST helper that returns JSON
  async function postForm(url, formData) {
    const res = await fetch(url, { method: 'POST', credentials: 'same-origin', body: formData });
    const text = await res.text();
    try { return JSON.parse(text || '{}'); }
    catch { throw new Error('Invalid JSON: ' + text); }
  }

  // -------------------------------
  // 1) PROFILE FORM (AJAX)
  // -------------------------------
  const profileForm = document.getElementById('profile-form');
  if (profileForm) {
    profileForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = document.getElementById('profile-save-btn');
      if (btn) btn.disabled = true;

      const fd = new FormData();
      fd.append('action', 'profile_update');
      fd.append('name', (document.getElementById('profile-name') || {}).value || '');
      fd.append('phone', (document.getElementById('profile-phone') || {}).value || '');
      const csrf = getCsrf(); if (csrf) fd.append('csrf_token', csrf);

      try {
        const json = await postForm('update_profile.php', fd);
        if (json.success) {
          flash(json.message || 'Profile updated', true);
          if (json.reload) setTimeout(() => location.reload(), 600);
        } else {
          flash(json.message || 'Update failed', false);
        }
      } catch (err) {
        console.error(err);
        flash('Server error', false);
      } finally {
        if (btn) btn.disabled = false;
      }
    });
  }

  // -------------------------------
  // 2) ADDRESS FORM (AJAX)
  // -------------------------------
  const addressForm = document.getElementById('address-form');
  if (addressForm) {
    addressForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = document.getElementById('address-save-btn');
      if (btn) btn.disabled = true;

      const fd = new FormData();
      fd.append('action', 'save_address');
      fd.append('house_no', (document.getElementById('addr-house') || {}).value || '');
      fd.append('landmark', (document.getElementById('addr-landmark') || {}).value || '');
      fd.append('city', (document.getElementById('addr-city') || {}).value || '');
      fd.append('pincode', (document.getElementById('addr-pincode') || {}).value || '');
      const csrf = getCsrf(); if (csrf) fd.append('csrf_token', csrf);

      try {
        const json = await postForm('update_profile.php', fd);
        if (json.success) {
          flash(json.message || 'Address saved', true);
          setTimeout(() => location.reload(), 500);
        } else {
          flash(json.message || 'Address save failed', false);
        }
      } catch (err) {
        console.error(err);
        flash('Server error', false);
      } finally {
        if (btn) btn.disabled = false;
      }
    });
  }

  // --------------------------------------------
  // Helpers for partner editors (edit + add)
  // --------------------------------------------
  function bindEditButton(btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const card = btn.closest('.partner-card');
      if (!card) return;

      const existing = card.querySelector('.inline-editor');
      if (existing) {
        existing.style.height = existing.scrollHeight + 'px';
        requestAnimationFrame(() => {
          existing.style.transition = 'height 220ms ease';
          existing.style.height = '0px';
          setTimeout(() => existing.remove(), 240);
        });
        return;
      }

      const table = btn.dataset.table || '';
      const id = btn.dataset.id || '';
      const editor = document.createElement('div');
      editor.className = 'inline-editor';

      if (table === 'gv_partners') {
        editor.innerHTML = `
          <form class="inline-edit-form">
            <div><label>GV Partner ID
              <input name="gv_partner_id" value="${(btn.dataset.gv_partner_id || '').replace(/"/g,'&quot;')}">
            </label></div>
            <div><label>Name (optional)
              <input name="name" value="${(btn.dataset.name || '').replace(/"/g,'&quot;')}">
            </label></div>
            <div class="inline-actions">
              <button type="submit" class="btn btn-primary">Save</button>
              <button type="button" class="btn btn-secondary cancel">Cancel</button>
            </div>
          </form>`;
      } else {
        editor.innerHTML = `
          <form class="inline-edit-form">
            <div><label>Bank Name
              <input name="bank_name" value="${(btn.dataset.bank_name || '').replace(/"/g,'&quot;')}">
            </label></div>
            <div><label>Partner ID
              <input name="partner_id" value="${(btn.dataset.partner_id || '').replace(/"/g,'&quot;')}">
            </label></div>
            <div><label>Name (optional)
              <input name="name" value="${(btn.dataset.name || '').replace(/"/g,'&quot;')}">
            </label></div>
            <div class="inline-actions">
              <button type="submit" class="btn btn-primary">Save</button>
              <button type="button" class="btn btn-secondary cancel">Cancel</button>
            </div>
          </form>`;
      }

      card.appendChild(editor);
      // slide down
      editor.style.overflow = 'hidden';
      editor.style.height = '0px';
      editor.style.transition = 'height 260ms ease';
      requestAnimationFrame(() => { editor.style.height = editor.scrollHeight + 'px'; });

      editor.querySelector('.cancel').addEventListener('click', () => {
        editor.style.height = editor.scrollHeight + 'px';
        requestAnimationFrame(() => {
          editor.style.transition = 'height 220ms ease';
          editor.style.height = '0px';
          setTimeout(() => editor.remove(), 240);
        });
      });

      editor.querySelector('.inline-edit-form').addEventListener('submit', async function (ev) {
        ev.preventDefault();
        const fd = new FormData();
        fd.append('action', 'update_partner');
        fd.append('id', id);
        fd.append('table', table);
        if (table === 'gv_partners') {
          fd.append('gv_partner_id', this.elements['gv_partner_id'].value.trim());
          fd.append('name', this.elements['name'].value.trim());
        } else {
          fd.append('bank_name', this.elements['bank_name'].value.trim());
          fd.append('partner_id', this.elements['partner_id'].value.trim());
          fd.append('name', this.elements['name'].value.trim());
        }
        const csrf = getCsrf(); if (csrf) fd.append('csrf_token', csrf);

        try {
          const json = await postForm('update_profile.php', fd);
          if (json.success) {
            const titleEl = card.querySelector('.partner-title');
            const editBtn = card.querySelector('.edit-partner-btn');
            if (table === 'gv_partners') {
              const gv = fd.get('gv_partner_id'), nm = fd.get('name');
              if (titleEl) titleEl.textContent = 'GV ' + gv + (nm ? ' — ' + nm : '');
              if (editBtn) { editBtn.dataset.gv_partner_id = gv; editBtn.dataset.name = nm; }
            } else {
              const bank = fd.get('bank_name'), pid = fd.get('partner_id'), nm = fd.get('name');
              if (titleEl) titleEl.textContent = (bank || '') + ' — ' + (pid || '') + (nm ? ' (' + nm + ')' : '');
              if (editBtn) { editBtn.dataset.bank_name = bank; editBtn.dataset.partner_id = pid; editBtn.dataset.name = nm; }
            }
            // slide up
            editor.style.height = editor.scrollHeight + 'px';
            requestAnimationFrame(() => {
              editor.style.transition = 'height 220ms ease';
              editor.style.height = '0px';
              setTimeout(() => editor.remove(), 240);
            });
            flash(json.message || 'Updated', true);
          } else {
            flash(json.message || 'Update failed', false);
          }
        } catch (err) {
          console.error(err);
          flash('Network/server error', false);
        }
      });
    });
  }

  function attachInlineEditorHandlers() {
    document.querySelectorAll('.edit-partner-btn').forEach(btn => {
      // avoid double-binding
      if (!btn._editBound) {
        bindEditButton(btn);
        btn._editBound = true;
      }
    });
  }

  // --------------------------------------------
  // 4) ADD PARTNER (inline chooser -> form)
  // --------------------------------------------
  const addBtn = document.getElementById('add-partner-btn');
  const addHost = document.getElementById('add-partner-editor');
  const grid = document.querySelector('.partners-grid');

  if (addBtn && addHost && grid) {
    addBtn.addEventListener('click', function () {
      // toggle existing editor
      const existing = addHost.querySelector('.inline-editor');
      if (existing) {
        existing.style.height = existing.scrollHeight + 'px';
        requestAnimationFrame(() => {
          existing.style.transition = 'height 220ms ease';
          existing.style.height = '0px';
          setTimeout(() => existing.remove(), 240);
        });
        return;
      }

      // chooser element
      const chooser = document.createElement('div');
      chooser.className = 'inline-editor';
      chooser.innerHTML = `
        <div style="padding:12px">
          <div style="margin-bottom:8px;font-weight:600">Add partner — choose type:</div>
          <div style="display:flex;gap:8px;align-items:center">
            <button class="btn btn-primary" data-type="partner" type="button">Partner ID</button>
            <button class="btn btn-outline" data-type="gv" type="button">GV Partner ID</button>
            <button class="btn btn-link cancel-chooser" style="margin-left:auto" type="button">Cancel</button>
          </div>
        </div>`;
      addHost.appendChild(chooser);
      chooser.style.overflow = 'hidden';
      chooser.style.height = '0px';
      chooser.style.transition = 'height 260ms ease';
      requestAnimationFrame(() => chooser.style.height = chooser.scrollHeight + 'px');

      chooser.querySelector('.cancel-chooser').addEventListener('click', () => {
        chooser.style.height = chooser.scrollHeight + 'px';
        requestAnimationFrame(() => {
          chooser.style.transition = 'height 220ms ease';
          chooser.style.height = '0px';
          setTimeout(() => chooser.remove(), 240);
        });
      });

      chooser.querySelectorAll('[data-type]').forEach(btn => btn.addEventListener('click', function () {
        const type = this.getAttribute('data-type');
        const editor = document.createElement('div');
        editor.className = 'inline-editor';
        if (type === 'gv') {
          editor.innerHTML = `
            <form class="inline-edit-form">
              <div><label>GV Partner ID<br><input name="gv_partner_id" placeholder="e.g. GV-ABC123"></label></div>
              <div><label>Name (optional)<br><input name="name" placeholder="Display name"></label></div>
              <div style="margin-top:8px">
                <button type="submit" class="btn btn-primary">Add GV Partner</button>
                <button type="button" class="btn btn-secondary cancel">Cancel</button>
              </div>
              <input type="hidden" name="table" value="gv_partners">
            </form>`;
        } else {
          editor.innerHTML = `
            <form class="inline-edit-form">
              <div><label>Bank Name<br><input name="bank_name" placeholder="e.g. HDFC/ICICI/SBI"></label></div>
              <div><label>Partner ID<br><input name="partner_id" placeholder="e.g. ABC12345"></label></div>
              <div><label>Name (optional)<br><input name="name" placeholder="Display name"></label></div>
              <div style="margin-top:8px">
                <button type="submit" class="btn btn-primary">Add Partner</button>
                <button type="button" class="btn btn-secondary cancel">Cancel</button>
              </div>
              <input type="hidden" name="table" value="partners">
            </form>`;
        }

        chooser.replaceWith(editor);
        editor.style.overflow = 'hidden';
        editor.style.height = '0px';
        editor.style.transition = 'height 260ms ease';
        requestAnimationFrame(() => editor.style.height = editor.scrollHeight + 'px');

        editor.querySelector('.cancel').addEventListener('click', () => {
          editor.style.height = editor.scrollHeight + 'px';
          requestAnimationFrame(() => {
            editor.style.transition = 'height 220ms ease';
            editor.style.height = '0px';
            setTimeout(() => editor.remove(), 240);
          });
        });

        editor.querySelector('.inline-edit-form').addEventListener('submit', async function (e) {
          e.preventDefault();
          const form = this;
          const table = (form.elements['table'] && form.elements['table'].value) || 'partners';
          const fd = new FormData();
          fd.append('action', 'create_partner');
          fd.append('table', table);

          if (table === 'gv_partners') {
            const gid = (form.elements['gv_partner_id'].value || '').trim();
            const name = (form.elements['name'].value || '').trim();
            if (!gid) { flash('GV Partner ID required', false); return; }
            fd.append('gv_partner_id', gid);
            fd.append('name', name);
          } else {
            const bank = (form.elements['bank_name'].value || '').trim();
            const pid = (form.elements['partner_id'].value || '').trim();
            const name = (form.elements['name'].value || '').trim();
            if (!bank || !pid) { flash('Bank name and Partner ID are required', false); return; }
            fd.append('bank_name', bank);
            fd.append('partner_id', pid);
            fd.append('name', name);
          }

          // attach CSRF if you use it
          if (typeof getCsrf === 'function') {
            const c = getCsrf();
            if (c) fd.append('csrf_token', c);
          }

          try {
            const res = await postForm('update_profile.php', fd);
            if (res && res.success && res.partner) {
              const p = res.partner;
              const card = document.createElement('div');
              card.className = 'partner-card';
              card.setAttribute('data-row-id', p.id);
              card.setAttribute('data-table', table);

              const title = (table === 'gv_partners') ? `GV - ${p.gv_partner_id || ''}${p.name ? ' — ' + p.name : ''}` : `${p.bank_name || ''} ${p.partner_id || ''}${p.name ? ' — ' + p.name : ''}`;
              card.innerHTML = `
                <div class="partner-title">${title}</div>
                <div class="partner-meta">Saved: ${p.created_at || ''}</div>
                <div class="card-actions">
                  <button class="btn btn-secondary edit-partner-btn" data-id="${p.id}" data-table="${table}" data-name="${(p.name||'').replace(/"/g,'&quot;')}">Edit</button>
                  <form method="post" onsubmit="return confirm('Delete partner?');" style="display:inline;">
                    <input type="hidden" name="action" value="delete_partner">
                    <input type="hidden" name="id" value="${p.id}">
                    <input type="hidden" name="table" value="${table}">
                    <button class="btn btn-danger" type="submit">Delete</button>
                  </form>
                </div>`;
              grid.prepend(card);
              if (typeof attachInlineEditorHandlers === 'function') attachInlineEditorHandlers();

              // collapse editor
              editor.style.height = editor.scrollHeight + 'px';
              requestAnimationFrame(() => {
                editor.style.transition = 'height 200ms ease';
                editor.style.height = '0px';
                setTimeout(() => editor.remove(), 240);
              });

              flash(res.message || 'Partner added', true);
            } else {
              flash(res && res.message ? res.message : 'Save failed', false);
            }
          } catch (err) {
            console.error(err);
            flash('Network/server error', false);
          }
        });
      }));
    });
  }

  // init edit handlers on existing cards
  attachInlineEditorHandlers();

});
