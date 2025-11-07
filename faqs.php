<?php
require_once 'config/common_start.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>FASTag — FAQs</title>
<link rel="stylesheet" href="/public/css/styles.css">
<style>
  /* --- Theme colors (match your site) --- */
  :root{
    --bg:#0b0b0b;
    --card:#0f0f10;
    --muted:#bdbdbd;
    --accent1:#ffb84d; /* yellow */
    --accent2:#ff6b3d; /* orange */
    --glass: rgba(255,255,255,0.03);
    --radius:12px;
    --gap:14px;
    --max-width:980px;
  }

  *, *::before, *::after { box-sizing: border-box; }

  html,body{height:100%;}
  body{
    margin:0;
    font-family: Inter, "Segoe UI", Roboto, system-ui, -apple-system, "Helvetica Neue", Arial;
    background: linear-gradient(180deg,#060606 0%, #090909 60%);
    color:#eee;
    -webkit-font-smoothing:antialiased;
    -moz-osx-font-smoothing:grayscale;
    padding:28px 12px; /*lets see what should we with this*/ */
    display:block;
  }

  .wrap{
    width:100%;
    max-width:var(--max-width);
    margin:0 auto;
  }

  header.app-head{
    display:flex;
    align-items:center;
    gap:18px;
    margin-bottom:20px;
  }
  .logo {
    width:52px;
    height:52px;
    border-radius:10px;
    background: linear-gradient(135deg,var(--accent1),var(--accent2));
    display:flex;
    align-items:center;
    justify-content:center;
    color:#1a1a1a;
    font-weight:700;
    box-shadow: 0 6px 22px rgba(0,0,0,0.6), inset 0 -6px 18px rgba(255,255,255,0.02);
  }
  .app-title {
    font-size:20px;
    font-weight:700;
    letter-spacing:0.2px;
  }
  .app-sub { color:var(--muted); font-size:13px; margin-top:2px; }

  main.card {
    background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
    border-radius: var(--radius);
    padding:20px;
    box-shadow: 0 8px 30px rgba(2,2,2,0.6);
    width:100%;
  }

  /* Search & controls */
  .controls {
    display:flex;
    gap:12px;
    align-items:center;
    margin-bottom:16px;
    flex-wrap:wrap;
  }
  .search {
    flex:1;
    display:flex;
    gap:8px;
    align-items:center;
    background: linear-gradient(180deg, rgba(121, 121, 121, 0), rgba(255, 255, 255, 0));
    border-radius:10px;
    padding:8px 12px;
    border:1px solid rgba(255, 255, 255, 0.13);
  }
  .search input{
    background:transparent;border:0;outline:none;color:inherit;font-size:15px;width:100%;
  }
  .btn, button {
    appearance:none;
    -webkit-appearance:none;
    border:0;
    cursor:pointer;
    font-weight:700;
    padding:10px 14px;
    border-radius:10px;
    background: linear-gradient(90deg,var(--accent1),var(--accent2));
    color:#111;
    box-shadow: 0 6px 16px rgba(255,107,61,0.09);
  }
  .ghost {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.18));
    border:1px solid rgba(255, 255, 255, 0.29);
    color:var(--accent1);
    font-weight:600;
  }
  .ghost:hover{
    transform: translateY(-3px); box-shadow: 0 12px 40px rgba(0,0,0,0.6);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.18));
    border:1px solid rgba(255, 255, 255, 0.29);
  }

  /* List layout */
  .faq-grid { display:grid; gap:12px; grid-template-columns: 1fr; }
  .faq-item {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.18));
    border-radius:12px;
    padding:14px;
    border:1px solid rgba(255, 255, 255, 0.35);
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .faq-item:hover{ transform: translateY(-3px); box-shadow: 0 12px 40px rgba(0,0,0,0.6); }

  .q-row {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
  }
  .q-title { font-weight:700; font-size:15px; }
  .q-meta { color:var(--muted); font-size:13px; }

  .q-body {
    margin-top:10px;
    color:#ddd;
    line-height:1.5;
    font-size:14px;
    display:none; /* toggled */
  }

  /* pagination */
  .pager {
    display:flex;
    align-items:center;
    gap:8px;
    justify-content:center;
    margin-top:18px;
  }
  .page-btn {
    background:transparent;
    border:1px solid rgba(255, 255, 255, 0.18);
    color:var(--muted);
    padding:6px 10px;
    border-radius:8px;
  }
  .page-btn.active { background: linear-gradient(90deg,var(--accent1),var(--accent2)); color:#111; border:0; }

  /* small screen adjustments */
  @media (min-width:720px){
    .faq-grid { grid-template-columns: 1fr 1fr; }
  }
  @media (min-width:1100px){
    .faq-grid { grid-template-columns: 1fr 1fr 1fr; }
  }
</style>
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>
  <div class="wrap">
    <header class="app-head">
      <div>
        <div class="app-title">FASTag — Help & FAQs</div>
        <div class="app-sub">Answers to common FASTag questions</div>
      </div>
    </header>

    <main class="card" role="main" aria-labelledby="faq-heading">
      <h2 id="faq-heading">Frequently Asked Questions</h2>

      <div class="controls" aria-hidden="false">
        <div class="search" role="search">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          <input id="qsearch" placeholder="Search questions (install, recharge, not working...)" aria-label="Search FAQs">
        </div>

        <select id="perpage" title="Items per page" style="background:transparent;color:var(--muted);border-radius:10px;padding:8px;border:1px solid rgba(255, 255, 255, 0.28);">
          <option value="4">4 / page</option>
          <option value="6">6 / page</option>
          <option value="9">9 / page</option>
        </select>

        <button id="clear" class="ghost">Clear search</button>
      </div>

      <section id="faq-list" class="faq-grid" aria-live="polite"></section>

      <div class="pager" id="pager" role="navigation" aria-label="FAQ pages"></div>
    </main>
  </div>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="/public/js/script.js"></script>
<script>
/* --- FAQ data (edit or extend) --- */
const FAQS = [
  {
    id: 'install-1',
    q: 'How to install FASTag?',
    a: `<p>FASTag installation is simple — follow these steps:</p>
        <ol>
          <li>Buy or receive a FASTag from your bank or authorized distributor.</li>
          <li>Log into your FASTag provider's portal (or bank app) and register the tag to your vehicle using the tag ID.</li>
          <li>Ensure sufficient balance in the FASTag wallet (recharge if required).</li>
          <li>At the toll plaza, the FASTag will be scanned — the toll will be auto-deducted.</li>
        </ol>
        <p><strong>Tip:</strong> Keep the tag properly pasted on the windshield behind the rear-view mirror for reliable reads.</p>`
  },
  {
    id: 'notworking-1',
    q: 'FASTag not working?',
    a: `<p>If your FASTag fails at a toll plaza, try these checks:</p>
        <ul>
          <li>Confirm the tag is correctly attached to the windshield and the barcode/antenna side is unobstructed.</li>
          <li>Check your FASTag account balance; recharge if low.</li>
          <li>Ask the toll operator to manually check the scanner and enter the tag ID.</li>
          <li>If the tag is damaged, request a replacement from your provider.</li>
        </ul>
        <p>Most issues are either low balance or improper placement. If problem persists, open a support ticket with the provider.</p>`
  },
  {
    id: 'recharge-1',
    q: 'How to recharge your FASTag?',
    a: `<p>Recharging FASTag can be done online via your bank app, provider portal, or UPI:</p>
        <ol>
          <li>Open your FASTag provider/bank app and go to the FASTag wallet section.</li>
          <li>Choose Recharge / Add Money and enter the amount.</li>
          <li>Confirm using UPI / netbanking / debit card.</li>
        </ol>
        <p>Your recharge is usually reflected instantly. Keep receipts for records.</p>`
  },
  {
    id: 'link-1',
    q: 'How to link FASTag to my wallet or bank account?',
    a: `<p>Linking varies by provider; generally:</p>
        <ol>
          <li>Install the provider/bank app.</li>
          <li>Login and choose 'Link FASTag' or 'Manage Tag'.</li>
          <li>Enter vehicle & tag details and verify.</li>
        </ol>
        <p>Once linked, you can enable auto-recharge or view statements.</p>`
  },
  {
    id: 'refund-1',
    q: 'What if toll was deducted twice / refund?',
    a: `<p>If you notice duplicate deductions:</p>
        <ul>
          <li>Collect receipt and time details from the toll operator.</li>
          <li>Raise a dispute via provider's app/support with proof.</li>
          <li>Providers typically investigate and issue refunds within a few business days.</li>
        </ul>`
  },
  {
    id: 'transfer-1',
    q: 'Can I transfer FASTag to another vehicle?',
    a: `<p>FASTag is usually vehicle-bound. Some providers allow transfer after verification; contact provider support. Often, tag replacement is recommended when changing vehicle.</p>`
  },
  {
    id: 'block-1',
    q: 'How to block my FASTag if lost or stolen?',
    a: `<p>If your FASTag is lost/ stolen:</p>
        <ol>
          <li>Immediately contact your FASTag provider and request a block.</li>
          <li>Disable auto-recharge (if enabled).</li>
          <li>Apply for a replacement tag via provider.</li>
        </ol>`
  },
  {
    id: 'statement-1',
    q: 'How to view FASTag transaction statement?',
    a: `<p>Use the provider app or portal. Most providers offer downloadable statements and SMS alerts for each transaction.</p>`
  },
  {
    id: 'charge-1',
    q: 'What are FASTag service charges?',
    a: `<p>Service charges depend on bank/provider. Check the provider’s tariff — usually a one-time tag issuance fee and nominal wallet maintenance charges may apply.</p>`
  }
];

/* --- App state --- */
let state = {
  page: 1,
  perPage: 4,
  filtered: FAQS.slice(),
  expanded: new Set(), // expanded IDs
};

/* --- Utilities --- */
const $ = sel => document.querySelector(sel);
const $$ = sel => Array.from(document.querySelectorAll(sel));

/* robust escapeHtml */
function escapeHtml(s){
  if (s == null) return '';
  return String(s)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#39;');
}
function stripHtml(html){
  const tmp = document.createElement('div');
  tmp.innerHTML = html || '';
  return tmp.textContent || tmp.innerText || '';
}

/* --- Render functions --- */
function renderList(){
  const listEl = $('#faq-list');
  listEl.innerHTML = '';
  const start = (state.page - 1) * state.perPage;
  const pageItems = state.filtered.slice(start, start + state.perPage);

  if (pageItems.length === 0) {
    listEl.innerHTML = `<div style="padding:24px;color:var(--muted)">No matching FAQs.</div>`;
    renderPager();
    return;
  }

  for (const item of pageItems) {
    const itemEl = document.createElement('article');
    itemEl.className = 'faq-item';
    itemEl.setAttribute('data-id', item.id);
    itemEl.innerHTML = `
      <div class="q-row">
        <div style="flex:1">
          <div class="q-title">${escapeHtml(item.q)}</div>
          <div class="q-meta">#${escapeHtml(item.id)}</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <button class="ghost expand-btn" data-id="${item.id}" aria-expanded="false" aria-controls="body-${item.id}">Toggle</button>
        </div>
      </div>
      <div id="body-${item.id}" class="q-body" aria-hidden="true">${item.a}</div>
    `;
    listEl.appendChild(itemEl);
  }

  // Wire events for toggle expand
  $$('.expand-btn').forEach(btn => {
    btn.onclick = (e) => {
      const id = e.currentTarget.dataset.id;
      toggleExpand(id);
    };
    btn.onkeypress = (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        const id = e.currentTarget.dataset.id;
        toggleExpand(id);
      }
    };
  });

  // reflect expanded set to DOM
  $$('.faq-item').forEach(el=>{
    const id = el.getAttribute('data-id');
    const body = el.querySelector('.q-body');
    const expandBtn = el.querySelector('.expand-btn');
    if (state.expanded.has(id)) {
      body.style.display = 'block';
      body.setAttribute('aria-hidden','false');
      if (expandBtn) expandBtn.setAttribute('aria-expanded','true');
    } else {
      body.style.display = 'none';
      body.setAttribute('aria-hidden','true');
      if (expandBtn) expandBtn.setAttribute('aria-expanded','false');
    }
  });

  renderPager();
}

function renderPager(){
  const pager = $('#pager');
  pager.innerHTML = '';
  const total = state.filtered.length;
  const pages = Math.max(1, Math.ceil(total / state.perPage));
  const maxButtons = 7;

  const prev = document.createElement('button');
  prev.className='page-btn';
  prev.textContent = 'Prev';
  prev.disabled = state.page === 1;
  prev.onclick = ()=>{ if(state.page>1){ state.page--; renderList(); scrollTopCard(); } };
  pager.appendChild(prev);

  const startPage = Math.max(1, state.page - Math.floor(maxButtons/2));
  for (let p = startPage; p <= Math.min(pages, startPage + maxButtons -1); p++){
    const b = document.createElement('button');
    b.className = 'page-btn' + (p===state.page? ' active':'');
    b.textContent = p;
    b.onclick = ()=>{ state.page = p; renderList(); scrollTopCard(); };
    pager.appendChild(b);
  }

  const next = document.createElement('button');
  next.className='page-btn';
  next.textContent = 'Next';
  next.disabled = state.page >= pages;
  next.onclick = ()=>{ if(state.page<pages){ state.page++; renderList(); scrollTopCard(); } };
  pager.appendChild(next);
}

/* --- expand / collapse --- */
function toggleExpand(id){
  if (state.expanded.has(id)) {
    state.expanded.delete(id);
  } else {
    state.expanded.add(id);
  }
  renderList();
}

/* --- search / filter --- */
function applyFilter(term){
  term = (term||'').trim().toLowerCase();
  if (!term) {
    state.filtered = FAQS.slice();
  } else {
    state.filtered = FAQS.filter(f => {
      return f.q.toLowerCase().includes(term) || (stripHtml(f.a).toLowerCase().includes(term));
    });
  }
  state.page = 1;
  state.expanded.clear();
  renderList();
}

/* --- helpers --- */
function scrollTopCard(){ const el = document.querySelector('.card'); if(el) el.scrollIntoView({behavior:'smooth',block:'start'}); }

/* --- wire UI --- */
document.addEventListener('DOMContentLoaded', ()=>{
  $('#qsearch').addEventListener('input', (e)=> applyFilter(e.target.value));
  $('#clear').addEventListener('click', ()=>{ $('#qsearch').value=''; applyFilter(''); });
  $('#perpage').addEventListener('change', (e)=>{ state.perPage = parseInt(e.target.value,10); state.page=1; renderList(); });

  // initial render
  applyFilter('');
});
</script>
</body>
</html>
