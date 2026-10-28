<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>MESHTRIFTY Admin</title>
  <style>
    :root { color-scheme: light; --paper: #f4f2ec; --ink: #20211e; --muted: #777870; --line: rgba(32,33,30,.15); --orange: #fb5938; --sans: Arial, sans-serif; }
    * { box-sizing: border-box; }
    [hidden] { display: none !important; }
    body { margin: 0; background: var(--paper); color: var(--ink); font: 14px/1.5 var(--sans); }
    button, input, select, textarea { font: inherit; }
    button { cursor: pointer; }
    .topbar { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 18px max(5vw, 20px); border-bottom: 1px solid var(--line); }
    .brand { font-weight: 800; letter-spacing: -.08em; text-decoration: none; color: var(--ink); }
    .brand span { color: var(--orange); }
    .layout { width: min(1200px, 92vw); margin: 36px auto 70px; }
    h1 { margin: 0 0 7px; font-size: clamp(28px, 4vw, 40px); letter-spacing: -.06em; }
    .muted { color: var(--muted); }
    .login-card, .panel, .editor { padding: 24px; border: 1px solid var(--line); background: rgba(255,255,255,.35); }
    .login-card { width: min(430px, 100%); margin: 10vh auto; }
    .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    label { display: grid; gap: 6px; font-size: 12px; font-weight: 700; }
    label.wide { grid-column: 1 / -1; }
    input, select, textarea { width: 100%; min-height: 42px; padding: 9px 10px; border: 1px solid var(--line); border-radius: 0; background: transparent; color: var(--ink); }
    textarea { min-height: 74px; resize: vertical; }
    .actions { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 16px; }
    .button { min-height: 42px; padding: 0 15px; border: 1px solid var(--ink); background: var(--ink); color: white; font-weight: 700; }
    .button.secondary { background: transparent; color: var(--ink); }
    .button.danger { border-color: #9c2e1b; background: #9c2e1b; }
    .error { min-height: 20px; margin: 10px 0 0; color: #a22b19; }
    .tabs { display: flex; gap: 8px; margin: 26px 0 15px; border-bottom: 1px solid var(--line); }
    .tab { padding: 11px 15px; border: 0; border-bottom: 2px solid transparent; background: transparent; }
    .tab[aria-selected="true"] { border-color: var(--orange); font-weight: 700; }
    .section-heading { display: flex; align-items: center; justify-content: space-between; gap: 15px; margin-bottom: 15px; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,.25); font-size: 12px; }
    th, td { padding: 11px 10px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
    th { font-size: 10px; letter-spacing: .08em; text-transform: uppercase; }
    td .button { min-height: 32px; padding: 0 9px; font-size: 11px; }
    .product-thumb { width: 48px; height: 58px; object-fit: cover; background: #e8e6df; }
    .editor { margin: 0 0 20px; }
    .check-row { display: flex; align-items: center; gap: 8px; min-height: 42px; }
    .check-row input { width: 18px; min-height: auto; }
    @media (max-width: 650px) {
      .layout { width: min(100% - 24px, 1200px); margin-top: 24px; }
      .login-card, .panel, .editor { padding: 16px; }
      .form-grid { grid-template-columns: 1fr; }
      label.wide { grid-column: auto; }
      th, td { padding: 9px 7px; }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <a class="brand" href="website.html">MESH<span>TRIFTY</span> <small>ADMIN</small></a>
    <button class="button secondary" id="logout" type="button" hidden>Sign out</button>
  </header>
  <main class="layout">
    <section class="login-card" id="login-view">
      <h1>Admin sign in</h1>
      <p class="muted">Use your administrator account to manage the store.</p>
      <form id="login-form">
        <div class="form-grid">
          <label class="wide">Username<input name="username" type="text" autocomplete="username" maxlength="64" pattern="[A-Za-z0-9._\-]{3,64}" required></label>
          <label class="wide">Password<input name="password" type="password" autocomplete="current-password" required></label>
        </div>
        <p class="error" id="login-error" role="alert"></p>
        <button class="button" type="submit">Sign in</button>
      </form>
    </section>

    <section id="dashboard" hidden>
      <h1>Store dashboard</h1>
      <p class="muted">Signed in as <span id="admin-name"></span>. Changes to active products appear in the storefront.</p>
      <nav class="tabs" aria-label="Admin sections">
        <button class="tab" type="button" data-tab="products" aria-selected="true">Products</button>
        <button class="tab" type="button" data-tab="orders" aria-selected="false">Orders</button>
        <button class="tab" type="button" data-tab="accounts" aria-selected="false">Customer accounts</button>
      </nav>
      <p class="error" id="dashboard-message" role="status"></p>

      <section class="panel" id="products-panel">
        <div class="section-heading"><h2>Products & inventory</h2><button class="button" id="new-product" type="button">Add product</button></div>
        <form class="editor" id="product-form" hidden>
          <h3 id="product-form-title">Add product</h3>
          <input type="hidden" name="id">
          <div class="form-grid">
            <label>Name<input name="name" maxlength="120" required></label>
            <label>Category<select name="category" required><option>Clothing</option><option>Shoes</option><option>Accessories</option></select></label>
            <label>Price (PHP)<input name="price" type="number" min="0.01" max="10000000" step="0.01" required></label>
            <label>Stock quantity<input name="stock" type="number" min="0" max="1000000" step="1" required></label>
            <label>Tag<input name="tag" maxlength="40"></label>
            <label>Image URL<input name="image" type="url" maxlength="2048" placeholder="https://..." required></label>
            <label class="wide">Product note<textarea name="note" maxlength="255"></textarea></label>
            <label class="wide">Release date & time (optional)<input name="release_at" type="datetime-local"><span class="muted">Uses your browser's local timezone. The item appears automatically at this time.</span></label>
            <label class="check-row"><input name="active" type="checkbox" checked> Visible in storefront</label>
          </div>
          <p class="error" id="product-error" role="alert"></p>
          <div class="actions"><button class="button" type="submit">Save product</button><button class="button secondary" id="cancel-product" type="button">Cancel</button></div>
        </form>
        <div class="table-wrap" id="products-table"></div>
      </section>

      <section class="panel" id="orders-panel" hidden>
        <div class="section-heading"><h2>Orders</h2><button class="button secondary refresh" type="button">Refresh</button></div>
        <div class="table-wrap" id="orders-table"></div>
      </section>

      <section class="panel" id="accounts-panel" hidden>
        <div class="section-heading"><h2>Customer accounts</h2><button class="button secondary refresh" type="button">Refresh</button></div>
        <div class="table-wrap" id="accounts-table"></div>
      </section>
    </section>
  </main>
  <script>
    const apiUrl = "api.php";
    let csrfToken = "";
    let products = [];
    const escapeHtml = value => String(value ?? "").replace(/[&<>"']/g, character => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[character]);
    const money = value => new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" }).format(Number(value));

    async function request(action, method = "GET", data = {}) {
      const options = { method, headers: {} };
      let url = apiUrl;
      if (method === "GET") {
        url += `?action=${encodeURIComponent(action)}`;
      } else {
        options.headers["Content-Type"] = "application/json";
        options.headers["X-CSRF-Token"] = csrfToken;
        options.body = JSON.stringify({ action, ...data });
      }
      const response = await fetch(url, options);
      const result = await response.json();
      if (!response.ok || result.status !== "success") {
        throw new Error(result.message || "The request failed.");
      }
      if (result.csrf_token) csrfToken = result.csrf_token;
      return result;
    }

    function showError(id, message) {
      document.getElementById(id).textContent = message;
    }

    async function showDashboard(admin) {
      document.getElementById("login-view").hidden = true;
      document.getElementById("dashboard").hidden = false;
      document.getElementById("logout").hidden = false;
      document.getElementById("admin-name").textContent = admin.name;
      try {
        await loadProducts();
      } catch (error) {
        showError("dashboard-message", error.message);
      }
    }

    function renderProducts() {
      document.getElementById("products-table").innerHTML = `<table><thead><tr><th>Item</th><th>Category</th><th>Price</th><th>Stock</th><th>Visibility / release</th><th></th></tr></thead><tbody>${products.map(product => `
        <tr><td><img class="product-thumb" src="${escapeHtml(product.image)}" alt=""><br>${escapeHtml(product.name)}</td>
        <td>${escapeHtml(product.category)}</td><td>${money(product.price)}</td><td>${escapeHtml(product.stock)}</td>
        <td>${!Number(product.active) ? "Hidden" : product.release_at && new Date(product.release_at) > new Date() ? `Scheduled<br>${escapeHtml(new Date(product.release_at).toLocaleString())}` : "Live"}</td><td><button class="button secondary edit-product" data-id="${escapeHtml(product.id)}" type="button">Edit</button>
        ${Number(product.active) ? `<button class="button danger archive-product" data-id="${escapeHtml(product.id)}" type="button">Hide</button>` : ""}</td></tr>`).join("")}</tbody></table>`;
    }

    async function loadProducts() {
      const result = await request("admin_products");
      products = result.products;
      renderProducts();
    }

    function openProductEditor(product = null) {
      const form = document.getElementById("product-form");
      form.reset();
      form.elements.id.value = product?.id || "";
      form.elements.name.value = product?.name || "";
      form.elements.note.value = product?.note || "";
      form.elements.price.value = product?.price || "";
      form.elements.category.value = product?.category || "Clothing";
      form.elements.tag.value = product?.tag || "";
      form.elements.image.value = product?.image || "";
      form.elements.stock.value = product?.stock ?? 0;
      form.elements.release_at.value = product?.release_at ? toLocalDateTimeInput(new Date(product.release_at)) : "";
      form.elements.active.checked = product ? Number(product.active) === 1 : true;
      document.getElementById("product-form-title").textContent = product ? "Edit product" : "Add product";
      showError("product-error", "");
      form.hidden = false;
      form.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function toLocalDateTimeInput(date) {
      const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
      return local.toISOString().slice(0, 16);
    }

    async function loadOrders() {
      const result = await request("admin_orders");
      document.getElementById("orders-table").innerHTML = result.orders.length ? `<table><thead><tr><th>Order</th><th>Customer / delivery</th><th>Items</th><th>Total</th><th>Status</th></tr></thead><tbody>${result.orders.map(order => `
        <tr><td>${escapeHtml(order.order_number)}<br><span class="muted">${escapeHtml(order.created_at)}</span></td>
        <td>${escapeHtml(order.customer_name)}<br>${escapeHtml(order.email)}<br>${escapeHtml(order.address)}, ${escapeHtml(order.city)} ${escapeHtml(order.postal)}</td>
        <td>${order.items.map(item => `${escapeHtml(item.product_name)} · ${escapeHtml(item.size)} × ${escapeHtml(item.quantity)}`).join("<br>")}</td>
        <td>${money(order.total)}<br>${escapeHtml(order.payment_method)}</td>
        <td><select class="order-status" data-id="${escapeHtml(order.id)}">${["Pending","Processing","Shipped","Completed","Cancelled"].map(status => `<option${status === order.status ? " selected" : ""}>${status}</option>`).join("")}</select></td></tr>`).join("")}</tbody></table>` : '<p class="muted">No orders yet.</p>';
    }

    async function loadAccounts() {
      const result = await request("admin_accounts");
      document.getElementById("accounts-table").innerHTML = result.accounts.length ? `<table><thead><tr><th>Name</th><th>Email</th><th>Joined</th></tr></thead><tbody>${result.accounts.map(account => `
        <tr><td>${escapeHtml(account.name)}</td><td>${escapeHtml(account.email)}</td><td>${escapeHtml(account.created_at)}</td></tr>`).join("")}</tbody></table>` : '<p class="muted">No customer accounts yet.</p>';
    }

    document.getElementById("login-form").addEventListener("submit", async event => {
      event.preventDefault();
      const form = event.currentTarget;
      showError("login-error", "");
      const fields = new FormData(form);
      try {
        const result = await request("admin_login", "POST", { username: fields.get("username"), password: fields.get("password") });
        form.reset();
        await showDashboard(result.admin);
      } catch (error) {
        showError("login-error", error.message);
      }
    });

    document.getElementById("logout").addEventListener("click", async () => {
      try {
        await request("admin_logout", "POST");
        document.getElementById("dashboard").hidden = true;
        document.getElementById("logout").hidden = true;
        document.getElementById("login-view").hidden = false;
      } catch (error) {
        showError("dashboard-message", error.message);
      }
    });

    document.querySelectorAll("[data-tab]").forEach(button => button.addEventListener("click", async () => {
      document.querySelectorAll("[data-tab]").forEach(tab => tab.setAttribute("aria-selected", String(tab === button)));
      const section = button.dataset.tab;
      document.getElementById("products-panel").hidden = section !== "products";
      document.getElementById("orders-panel").hidden = section !== "orders";
      document.getElementById("accounts-panel").hidden = section !== "accounts";
      showError("dashboard-message", "");
      try {
        if (section === "orders") await loadOrders();
        if (section === "accounts") await loadAccounts();
      } catch (error) {
        showError("dashboard-message", error.message);
      }
    }));

    document.getElementById("new-product").addEventListener("click", () => openProductEditor());
    document.getElementById("cancel-product").addEventListener("click", () => { document.getElementById("product-form").hidden = true; });
    document.getElementById("product-form").addEventListener("submit", async event => {
      event.preventDefault();
      const fields = new FormData(event.currentTarget);
      const product = Object.fromEntries(fields.entries());
      product.price = Number(product.price);
      product.stock = Number(product.stock);
      product.active = fields.has("active");
      product.release_at = product.release_at ? new Date(product.release_at).toISOString() : null;
      if (!product.id) delete product.id;
      try {
        await request("save_product", "POST", product);
        document.getElementById("product-form").hidden = true;
        await loadProducts();
      } catch (error) {
        showError("product-error", error.message);
      }
    });

    document.getElementById("products-table").addEventListener("click", async event => {
      const edit = event.target.closest(".edit-product");
      const archive = event.target.closest(".archive-product");
      if (edit) openProductEditor(products.find(product => Number(product.id) === Number(edit.dataset.id)));
      if (archive && confirm("Hide this product from the storefront?")) {
        try {
          await request("delete_product", "POST", { id: Number(archive.dataset.id) });
          await loadProducts();
        } catch (error) {
          showError("dashboard-message", error.message);
        }
      }
    });

    document.getElementById("orders-table").addEventListener("change", async event => {
      if (!event.target.matches(".order-status")) return;
      try {
        await request("update_order_status", "POST", { id: Number(event.target.dataset.id), status: event.target.value });
        showError("dashboard-message", "Order status updated.");
      } catch (error) {
        showError("dashboard-message", error.message);
        await loadOrders();
      }
    });

    document.querySelectorAll(".refresh").forEach(button => button.addEventListener("click", async () => {
      try {
        if (button.closest("#orders-panel")) await loadOrders();
        else await loadAccounts();
      } catch (error) {
        showError("dashboard-message", error.message);
      }
    }));

    (async () => {
      try {
        const result = await request("admin_session");
        if (result.admin) await showDashboard(result.admin);
      } catch (error) {
        showError("login-error", error.message);
      }
    })();
  </script>
</body>
</html>
