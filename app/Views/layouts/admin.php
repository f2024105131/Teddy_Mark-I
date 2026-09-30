<?php
/**
 * Admin Layout
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Chrome for every admin page — sidebar, header,
 *          search, user menu, flash messages, footer scripts.
 *
 * Usage:
 *   In your controller:
 *       $this->view('admin/dashboard/index', $data);
 *   In your view file, output content only (no HTML/body).
 *   The layout wraps it automatically.
 *
 * Expected variables (all optional):
 *   $title       — page title (falls back to 'Admin')
 *   $currentPage — matched against sidebar links for active state
 *   $content     — rendered view HTML (set by Controller::view())
 *
 * ⚠️ Verify against Shehroz's staff.php / customer.php:
 *   • If his layouts use Bootstrap 5, this matches.
 *   • If they use a different CSS framework or CDN, swap the
 *     <link>/<script> tags to match.
 *   • If his Controller::view() uses a different placeholder
 *     name than $content, update <?= $content ?> accordingly.
 * ----------------------------------------------------------
 */

$staffName = $_SESSION['staff_name'] ?? 'Admin';
$staffRole = $_SESSION['staff_role'] ?? 'admin';
$staffEmail= $_SESSION['staff_email'] ?? '';
$title     = $title ?? 'Admin';
$currentPage = $currentPage ?? '';

// Sidebar nav — grouped by section
$nav = [
    'Operations' => [
        ['label' => 'Dashboard',       'icon' => 'bi-speedometer2',      'url' => '/admin/dashboard',        'slug' => 'dashboard'],
        ['label' => 'Pickup Monitor',  'icon' => 'bi-truck',             'url' => '/admin/pickups/monitor',  'slug' => 'pickups'],
        ['label' => 'Complaints',      'icon' => 'bi-exclamation-circle','url' => '/admin/complaints',       'slug' => 'complaints'],
        ['label' => 'Refunds',         'icon' => 'bi-arrow-counterclockwise','url' => '/admin/refunds',      'slug' => 'refunds'],
    ],
    'Catalog' => [
        ['label' => 'Items',           'icon' => 'bi-basket',            'url' => '/admin/catalog/items',      'slug' => 'catalog-items'],
        ['label' => 'Categories',      'icon' => 'bi-tags',              'url' => '/admin/catalog/categories', 'slug' => 'catalog-categories'],
        ['label' => 'Services',        'icon' => 'bi-tag',               'url' => '/admin/catalog/services',   'slug' => 'catalog-services'],
        ['label' => 'Pricing Matrix',  'icon' => 'bi-grid-3x3-gap',      'url' => '/admin/catalog/pricing',    'slug' => 'catalog-pricing'],
    ],
    'People' => [
        ['label' => 'Customers',       'icon' => 'bi-people',            'url' => '/admin/customers',          'slug' => 'customers'],
        ['label' => 'Staff',           'icon' => 'bi-person-badge',      'url' => '/admin/staff',              'slug' => 'staff'],
        ['label' => 'Deletion Requests','icon' => 'bi-person-x',         'url' => '/admin/customers/deletion-requests', 'slug' => 'deletion-requests'],
    ],
    'Reports' => [
        ['label' => 'All Reports',     'icon' => 'bi-bar-chart',         'url' => '/admin/reports',            'slug' => 'reports'],
        ['label' => 'Sales',           'icon' => 'bi-cash-coin',         'url' => '/admin/reports/sales',      'slug' => 'reports-sales'],
        ['label' => 'Orders',          'icon' => 'bi-bag-check',         'url' => '/admin/reports/orders',     'slug' => 'reports-orders'],
        ['label' => 'Staff Performance','icon' => 'bi-graph-up',         'url' => '/admin/reports/staff-performance', 'slug' => 'reports-staff'],
    ],
    'System' => [
        ['label' => 'Settings',        'icon' => 'bi-gear',              'url' => '/admin/settings',           'slug' => 'settings'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['_csrf'] ?? '') ?>">
    <title><?= htmlspecialchars($title) ?> · LaundryPro Admin</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-w: 250px;
            --topbar-h: 60px;
            --sidebar-bg: #1e293b;
            --sidebar-fg: #cbd5e1;
            --sidebar-hover-bg: #334155;
            --sidebar-active-bg: #0ea5e9;
            --accent: #0ea5e9;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }
        .admin-sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
            color: var(--sidebar-fg);
            overflow-y: auto;
            z-index: 1030;
            transition: transform .25s ease;
        }
        .admin-sidebar .brand {
            padding: 1rem 1.25rem;
            font-weight: 700;
            font-size: 1.15rem;
            color: #fff;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .admin-sidebar .brand i {
            color: var(--accent);
            font-size: 1.5rem;
        }
        .admin-sidebar nav { padding: .75rem 0 2rem; }
        .admin-sidebar .nav-section {
            padding: 1rem 1.25rem .35rem;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            font-weight: 600;
        }
        .admin-sidebar a {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .55rem 1.25rem;
            color: var(--sidebar-fg);
            text-decoration: none;
            font-size: .9rem;
            transition: background .15s, color .15s;
        }
        .admin-sidebar a:hover {
            background: var(--sidebar-hover-bg);
            color: #fff;
        }
        .admin-sidebar a.active {
            background: var(--sidebar-active-bg);
            color: #fff;
            font-weight: 500;
        }
        .admin-sidebar a i {
            width: 18px;
            text-align: center;
            font-size: 1rem;
        }

        .admin-main {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .admin-topbar {
            height: var(--topbar-h);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0 1.25rem;
            position: sticky;
            top: 0;
            z-index: 1020;
        }
        .admin-topbar .search-wrap {
            flex: 1;
            max-width: 480px;
            position: relative;
        }
        .admin-topbar .search-wrap input {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: .5rem .75rem .5rem 2.25rem;
            font-size: .9rem;
            background: #f8fafc;
        }
        .admin-topbar .search-wrap input:focus {
            outline: none;
            border-color: var(--accent);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(14,165,233,.15);
        }
        .admin-topbar .search-wrap i.bi-search {
            position: absolute;
            left: .75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        /* Search dropdown */
        .search-results {
            position: absolute;
            top: calc(100% + 6px);
            left: 0; right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(15,23,42,.08);
            max-height: 420px;
            overflow-y: auto;
            display: none;
            z-index: 1050;
        }
        .search-results.open { display: block; }
        .search-results .cat-label {
            padding: .5rem .85rem .25rem;
            font-size: .7rem;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: .05em;
            font-weight: 600;
        }
        .search-results a.item {
            display: flex;
            gap: .65rem;
            padding: .55rem .85rem;
            color: #0f172a;
            text-decoration: none;
            border-bottom: 1px solid #f1f5f9;
        }
        .search-results a.item:hover { background: #f8fafc; }
        .search-results a.item:last-child { border-bottom: none; }
        .search-results .item .ico {
            width: 32px; height: 32px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #475569;
        }
        .search-results .item .txt { flex: 1; min-width: 0; }
        .search-results .item .txt .t {
            font-weight: 500; font-size: .88rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .search-results .item .txt .s {
            font-size: .78rem; color: #64748b;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .search-results .empty {
            padding: 1rem;
            text-align: center;
            color: #64748b;
            font-size: .85rem;
        }

        .admin-content {
            padding: 1.5rem;
            flex: 1;
        }

        /* Flash messages */
        .flash-wrap { padding: 0 1.5rem; margin-top: 1rem; }

        /* Responsive */
        @media (max-width: 991px) {
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.open { transform: translateX(0); }
            .admin-main { margin-left: 0; }
        }
        .sidebar-toggle { display: none; background: none; border: none; font-size: 1.4rem; color: #475569; }
        @media (max-width: 991px) { .sidebar-toggle { display: block; } }

        /* Utility */
        .page-title { font-weight: 600; font-size: 1.35rem; margin: 0; }
        .user-pill {
            display: flex; align-items: center; gap: .5rem;
            padding: .35rem .75rem;
            border-radius: 999px;
            background: #f1f5f9;
            cursor: pointer;
            border: none;
        }
        .user-pill .avatar {
            width: 30px; height: 30px;
            background: var(--accent);
            color: #fff;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: .8rem;
        }
    </style>
</head>
<body>

<!-- ============ SIDEBAR ============ -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="brand">
        <i class="bi bi-droplet-half"></i>
        <span>LaundryPro</span>
    </div>
    <nav>
        <?php foreach ($nav as $section => $links): ?>
            <div class="nav-section"><?= htmlspecialchars($section) ?></div>
            <?php foreach ($links as $link):
                $isActive = $currentPage === $link['slug'];
            ?>
                <a href="<?= htmlspecialchars($link['url']) ?>"
                   class="<?= $isActive ? 'active' : '' ?>">
                    <i class="bi <?= htmlspecialchars($link['icon']) ?>"></i>
                    <span><?= htmlspecialchars($link['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>

<!-- ============ MAIN ============ -->
<div class="admin-main">

    <!-- Topbar -->
    <header class="admin-topbar">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>

        <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text"
                   id="globalSearch"
                   placeholder="Search customers, orders, items, services…"
                   autocomplete="off">
            <div class="search-results" id="searchResults"></div>
        </div>

        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="/admin/pickups/monitor" class="btn btn-sm btn-light position-relative">
                <i class="bi bi-bell"></i>
            </a>
            <div class="dropdown">
                <button class="user-pill dropdown-toggle" data-bs-toggle="dropdown">
                    <span class="avatar"><?= htmlspecialchars(strtoupper(substr($staffName, 0, 1))) ?></span>
                    <span class="d-none d-md-inline"><?= htmlspecialchars($staffName) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2">
                        <div class="fw-semibold"><?= htmlspecialchars($staffName) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($staffEmail) ?></div>
                        <span class="badge bg-primary mt-1"><?= htmlspecialchars($staffRole) ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/admin/settings"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><a class="dropdown-item" href="/staff/orders"><i class="bi bi-box-seam me-2"></i>Staff Panel</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Flash messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="flash-wrap">
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['flash_success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="flash-wrap">
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['flash_error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Content -->
    <main class="admin-content">
        <?= $content ?? '' ?>
    </main>

    <!-- Footer -->
    <footer class="text-center text-muted py-3 small border-top bg-white">
        &copy; <?= date('Y') ?> LaundryPro Admin Panel
    </footer>
</div>

<!-- ============ SCRIPTS ============ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ---------- Sidebar toggle (mobile) ----------
    const sidebar = document.getElementById('adminSidebar');
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });

    // ---------- Global search ----------
    const input   = document.getElementById('globalSearch');
    const results = document.getElementById('searchResults');
    if (!input || !results) return;

    let debounce;
    let lastQuery = '';

    function hideResults() {
        results.classList.remove('open');
        results.innerHTML = '';
    }

    input.addEventListener('input', (e) => {
        clearTimeout(debounce);
        const q = e.target.value.trim();

        if (q.length < 2) {
            hideResults();
            return;
        }

        debounce = setTimeout(async () => {
            if (q === lastQuery) return;
            lastQuery = q;

            try {
                const res = await fetch(
                    '/api/global-search?q=' + encodeURIComponent(q),
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );
                const json = await res.json();
                if (!json.success) { hideResults(); return; }

                const data = json.data;
                if (data.total === 0) {
                    results.innerHTML = '<div class="empty">No results for "' +
                        escapeHtml(data.query) + '"</div>';
                    results.classList.add('open');
                    return;
                }

                let html = '';
                for (const [category, items] of Object.entries(data.categories)) {
                    if (!items.length) continue;
                    html += '<div class="cat-label">' + escapeHtml(category) + '</div>';
                    for (const item of items) {
                        html += `
                            <a class="item" href="${escapeAttr(item.url)}">
                                <span class="ico"><i class="bi ${escapeAttr(item.icon)}"></i></span>
                                <span class="txt">
                                    <span class="t">${escapeHtml(item.title)}</span>
                                    <span class="s">${escapeHtml(item.subtitle || item.meta || '')}</span>
                                </span>
                            </a>`;
                    }
                }
                results.innerHTML = html;
                results.classList.add('open');
            } catch (err) {
                console.error('Search error:', err);
                hideResults();
            }
        }, 250);
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-wrap')) hideResults();
    });

    // Escape key
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hideResults();
    });

    // ---------- CSRF helper for AJAX ----------
    window.csrfToken = csrf;

    // ---------- Auto-hide flash after 5s ----------
    document.querySelectorAll('.alert-dismissible').forEach(el => {
        setTimeout(() => {
            const a = bootstrap.Alert.getOrCreateInstance(el);
            a?.close();
        }, 5000);
    });

    // ---------- Small HTML escape helpers ----------
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }
    function escapeAttr(s) {
        return escapeHtml(s);
    }
})();
</script>

<?php if (!empty($scripts)): ?>
    <?php foreach ((array) $scripts as $script): ?>
        <script src="<?= htmlspecialchars($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>