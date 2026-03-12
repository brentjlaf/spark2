<?php
// File: modules/commerce/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$file     = __DIR__ . '/../../data/commerce.json';
$products = read_json_file($file);
if (!is_array($products)) {
    $products = [];
}

// ── Stats ────────────────────────────────────────────────────────────────────
$totalProducts  = count($products);
$totalActive    = 0;
$totalInactive  = 0;
$totalDraft     = 0;
$totalInventory = 0;
$totalValue     = 0.0;

foreach ($products as $p) {
    $status = $p['status'] ?? 'active';
    if ($status === 'active')       $totalActive++;
    elseif ($status === 'inactive') $totalInactive++;
    else                            $totalDraft++;

    if (!empty($p['track_stock'])) {
        $totalInventory += (int)($p['stock'] ?? 0);
    }
    $totalValue += (float)($p['price'] ?? 0.0);
}

$productsJson = htmlspecialchars(
    json_encode(array_values($products), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
    ENT_QUOTES, 'UTF-8'
);

$isAdmin = user_is_admin();
?>
<style>
/* ── Commerce module – hero gradient override ───────────────────────────── */
.commerce-dashboard .a11y-hero {
    background: linear-gradient(135deg, #0f766e 0%, #0d9488 60%, #14b8a6 100%);
    box-shadow: 0 20px 48px rgba(13, 148, 136, 0.30);
}

.commerce-stat-hint { font-size:.72rem; color:rgba(255,255,255,.65); margin-top:.1rem; }

/* ── Section / toolbar ─────────────────────────────────────────────────── */
.commerce-section { padding:1.25rem 1.75rem; }
.commerce-toolbar { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem; }
.commerce-toolbar-left { display:flex; align-items:center; gap:.5rem; flex:1 1 auto; flex-wrap:wrap; }
.commerce-toolbar-right { display:flex; align-items:center; gap:.5rem; flex-shrink:0; }
.commerce-search { padding:.42rem .8rem; border:1px solid #d1d5db; border-radius:6px; font-size:.875rem; min-width:220px; }
.commerce-search:focus { outline:none; border-color:#0d9488; box-shadow:0 0 0 3px rgba(13,148,136,.12); }
.commerce-status-filter { padding:.42rem .65rem; border:1px solid #d1d5db; border-radius:6px; font-size:.875rem; background:#fff; }

/* ── Compact action buttons (table rows, modal footer) ─────────────────── */
.commerce-btn { display:inline-flex; align-items:center; gap:.4rem; padding:.45rem .9rem; border-radius:6px; font-size:.8rem; font-weight:600; border:1px solid transparent; cursor:pointer; transition:background .15s,box-shadow .15s; }
.commerce-btn--primary { background:#0d9488; color:#fff; border-color:#0d9488; }
.commerce-btn--primary:hover { background:#0f766e; border-color:#0f766e; }
.commerce-btn--danger { background:#ef4444; color:#fff; border-color:#ef4444; }
.commerce-btn--danger:hover { background:#dc2626; border-color:#dc2626; }
.commerce-btn--sm { padding:.3rem .6rem; font-size:.75rem; }
.commerce-btn:disabled { opacity:.5; cursor:not-allowed; }

/* ── Bulk action bar ───────────────────────────────────────────────────── */
.commerce-bulk-bar { display:none; align-items:center; gap:.75rem; padding:.6rem 1rem; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; margin-bottom:.75rem; }
.commerce-bulk-bar.is-visible { display:flex; }
.commerce-bulk-count { font-size:.85rem; font-weight:600; color:#92400e; }
.commerce-bulk-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
.commerce-bulk-btn { padding:.3rem .7rem; border-radius:5px; font-size:.78rem; font-weight:600; border:1px solid; cursor:pointer; transition:background .12s; }
.commerce-bulk-btn--activate   { background:#d1fae5; color:#065f46; border-color:#6ee7b7; }
.commerce-bulk-btn--activate:hover   { background:#a7f3d0; }
.commerce-bulk-btn--deactivate { background:#fef3c7; color:#92400e; border-color:#fcd34d; }
.commerce-bulk-btn--deactivate:hover { background:#fde68a; }
.commerce-bulk-btn--delete     { background:#fee2e2; color:#991b1b; border-color:#fca5a5; }
.commerce-bulk-btn--delete:hover     { background:#fecaca; }

/* ── Product table ─────────────────────────────────────────────────────── */
.commerce-table-wrapper { overflow-x:auto; border:1px solid #e5e7eb; border-radius:8px; }
.commerce-table { width:100%; border-collapse:collapse; font-size:.85rem; }
.commerce-table thead th { background:#f9fafb; color:#6b7280; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; padding:.65rem .9rem; text-align:left; white-space:nowrap; border-bottom:1px solid #e5e7eb; }
.commerce-table thead th.col-cb { width:36px; }
.commerce-table thead th.col-img { width:56px; }
.commerce-table thead th.col-price { text-align:right; }
.commerce-table thead th.col-stock { text-align:center; }
.commerce-table tbody tr { border-bottom:1px solid #f3f4f6; transition:background .1s; }
.commerce-table tbody tr:last-child { border-bottom:none; }
.commerce-table tbody tr:hover { background:#f9fafb; }
.commerce-table tbody tr.is-selected { background:#f0fdfa; }
.commerce-table td { padding:.7rem .9rem; vertical-align:middle; }
.commerce-table td.col-cb { text-align:center; }
.commerce-table td.col-price { text-align:right; font-weight:600; color:#0f766e; }
.commerce-table td.col-stock { text-align:center; }

.commerce-product-thumb { width:40px; height:40px; border-radius:5px; object-fit:cover; background:#f3f4f6; border:1px solid #e5e7eb; display:block; }
.commerce-product-thumb-placeholder { width:40px; height:40px; border-radius:5px; background:#f3f4f6; border:1px solid #e5e7eb; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:.9rem; }
.commerce-product-name { font-weight:600; color:#111827; }
.commerce-product-sku { font-size:.72rem; color:#9ca3af; margin-top:2px; }
.commerce-status-badge { display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .55rem; border-radius:20px; font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; }
.commerce-status-badge--active   { background:#d1fae5; color:#065f46; }
.commerce-status-badge--inactive { background:#fee2e2; color:#991b1b; }
.commerce-status-badge--draft    { background:#fef3c7; color:#92400e; }
.commerce-row-actions { display:flex; gap:.35rem; align-items:center; }

.commerce-empty { padding:3rem 1.5rem; text-align:center; color:#9ca3af; }
.commerce-empty i { font-size:2.5rem; margin-bottom:.75rem; color:#d1d5db; display:block; }
.commerce-empty p { margin:.25rem 0; }
.commerce-empty-hint { font-size:.82rem; }

/* ── Modal ─────────────────────────────────────────────────────────────── */
.commerce-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; align-items:center; justify-content:center; }
.commerce-modal-overlay.is-open { display:flex; }
.commerce-modal { background:#fff; border-radius:12px; width:min(680px,95vw); max-height:90vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.commerce-modal-header { padding:1.1rem 1.4rem; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; }
.commerce-modal-title { font-size:1rem; font-weight:700; color:#111827; margin:0; }
.commerce-modal-close { background:none; border:none; font-size:1.1rem; color:#9ca3af; cursor:pointer; padding:.25rem .4rem; border-radius:4px; }
.commerce-modal-close:hover { color:#111827; background:#f3f4f6; }
.commerce-modal-body { padding:1.25rem 1.4rem; overflow-y:auto; flex:1; }
.commerce-modal-footer { padding:.9rem 1.4rem; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:.6rem; }

/* ── Form inside modal ─────────────────────────────────────────────────── */
.commerce-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:.9rem 1.1rem; }
.commerce-form-group { display:flex; flex-direction:column; gap:.3rem; }
.commerce-form-group.full { grid-column:1/-1; }
.commerce-form-label { font-size:.78rem; font-weight:600; color:#374151; }
.commerce-form-input,
.commerce-form-select,
.commerce-form-textarea { padding:.45rem .7rem; border:1px solid #d1d5db; border-radius:6px; font-size:.85rem; width:100%; box-sizing:border-box; background:#fff; }
.commerce-form-input:focus,
.commerce-form-select:focus,
.commerce-form-textarea:focus { outline:none; border-color:#0d9488; box-shadow:0 0 0 3px rgba(13,148,136,.12); }
.commerce-form-textarea { min-height:80px; resize:vertical; }
.commerce-form-hint { font-size:.72rem; color:#9ca3af; }
.commerce-form-section-title { font-size:.78rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.06em; padding:.5rem 0 .25rem; border-bottom:1px solid #f3f4f6; margin:.25rem 0; grid-column:1/-1; }
.commerce-form-check { display:flex; align-items:center; gap:.5rem; font-size:.85rem; cursor:pointer; }
.commerce-form-check input { width:16px; height:16px; cursor:pointer; accent-color:#0d9488; }

/* ── Alerts & toast ────────────────────────────────────────────────────── */
.commerce-inline-alert { background:#fee2e2; color:#991b1b; padding:.5rem .9rem; border-radius:6px; font-size:.82rem; margin-bottom:.75rem; }
.commerce-toast { position:fixed; bottom:1.5rem; right:1.5rem; background:#111827; color:#fff; padding:.65rem 1.1rem; border-radius:8px; font-size:.85rem; z-index:2000; display:none; box-shadow:0 4px 16px rgba(0,0,0,.25); }
.commerce-toast.show { display:block; animation:fadeInUp .2s ease; }
@keyframes fadeInUp { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
@media(max-width:640px){ .commerce-form-grid{grid-template-columns:1fr;} .commerce-section{padding:1rem 1.1rem;} }
</style>

<div class="content-section" id="commerce">
    <div class="commerce-dashboard" data-products="<?php echo $productsJson; ?>">

        <!-- Hero ─────────────────────────────────────────────────────────── -->
        <header class="a11y-hero">
            <div class="a11y-hero-content">
                <div>
                    <span class="hero-eyebrow">Product Catalog</span>
                    <h2 class="a11y-hero-title">Commerce</h2>
                    <p class="a11y-hero-subtitle">Manage your products, pricing, inventory, and status from one place.</p>
                </div>
                <div class="a11y-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--ghost" id="commerceRefreshBtn">
                        <i class="fas fa-rotate" aria-hidden="true"></i> Refresh
                    </button>
                    <button type="button" class="a11y-btn" id="commerceAddBtn" style="background:#fff;color:#0f766e;border-color:transparent;">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add Product
                    </button>
                </div>
            </div>
            <div class="a11y-overview-grid">
                <div class="a11y-overview-card">
                    <div class="a11y-overview-label">Total Products</div>
                    <div class="a11y-overview-value" id="commerceTotalCount"><?php echo $totalProducts; ?></div>
                    <div class="commerce-stat-hint"><?php echo $totalActive; ?> active</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-label">Active</div>
                    <div class="a11y-overview-value" id="commerceActiveCount"><?php echo $totalActive; ?></div>
                    <div class="commerce-stat-hint">Listed &amp; visible</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-label">Inactive / Draft</div>
                    <div class="a11y-overview-value" id="commerceInactiveCount"><?php echo $totalInactive + $totalDraft; ?></div>
                    <div class="commerce-stat-hint"><?php echo $totalInactive; ?> inactive, <?php echo $totalDraft; ?> draft</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-label">Total Inventory</div>
                    <div class="a11y-overview-value" id="commerceInventoryCount"><?php echo $totalInventory; ?></div>
                    <div class="commerce-stat-hint">Units tracked</div>
                </div>
            </div>
        </header>

        <!-- Product list ──────────────────────────────────────────────────── -->
        <section class="commerce-section" aria-label="Product catalog">

            <div id="commerceInlineAlert" class="commerce-inline-alert" style="display:none;"></div>

            <div class="commerce-toolbar">
                <div class="commerce-toolbar-left">
                    <input type="text" class="commerce-search" id="commerceSearch" placeholder="Search products…" aria-label="Search products">
                    <select class="commerce-status-filter" id="commerceStatusFilter" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
                <div class="commerce-toolbar-right">
                    <span id="commerceMatchCount" style="font-size:.8rem;color:#6b7280;"><?php echo $totalProducts === 1 ? '1 product' : $totalProducts . ' products'; ?></span>
                </div>
            </div>

            <div class="commerce-bulk-bar" id="commerceBulkBar">
                <span class="commerce-bulk-count" id="commerceBulkCount">0 selected</span>
                <div class="commerce-bulk-actions">
                    <button class="commerce-bulk-btn commerce-bulk-btn--activate" id="commerceBulkActivate">Activate</button>
                    <button class="commerce-bulk-btn commerce-bulk-btn--deactivate" id="commerceBulkDeactivate">Deactivate</button>
                    <?php if ($isAdmin): ?>
                    <button class="commerce-bulk-btn commerce-bulk-btn--delete" id="commerceBulkDelete">Delete</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="commerce-table-wrapper" id="commerceTableWrapper">
<?php if (empty($products)): ?>
                <div class="commerce-empty" id="commerceEmpty">
                    <i class="fas fa-store" aria-hidden="true"></i>
                    <p>No products yet.</p>
                    <p class="commerce-empty-hint">Click <strong>Add Product</strong> to create your first listing.</p>
                </div>
<?php else: ?>
                <table class="commerce-table" id="commerceTable">
                    <thead>
                        <tr>
                            <th class="col-cb"><input type="checkbox" id="commerceSelectAll" aria-label="Select all"></th>
                            <th class="col-img"></th>
                            <th>Product</th>
                            <th>Category</th>
                            <th class="col-price">Price</th>
                            <th class="col-stock">Stock</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="commerceTableBody">
<?php foreach ($products as $p):
    $pId     = (int)($p['id'] ?? 0);
    $pName   = htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $pSku    = htmlspecialchars($p['sku'] ?? '', ENT_QUOTES, 'UTF-8');
    $pCat    = htmlspecialchars($p['category'] ?? '', ENT_QUOTES, 'UTF-8');
    $pPrice  = number_format((float)($p['price'] ?? 0), 2);
    $pStock  = (int)($p['stock'] ?? 0);
    $pTrack  = !empty($p['track_stock']);
    $pStatus = $p['status'] ?? 'active';
    $pImg    = $p['image'] ?? '';
    $statusClass = match($pStatus) { 'active' => 'active', 'inactive' => 'inactive', default => 'draft' };
    $statusLabel = ucfirst($pStatus);
    $pJson   = htmlspecialchars(json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
?>
                        <tr class="commerce-product-row" data-id="<?php echo $pId; ?>" data-status="<?php echo htmlspecialchars($pStatus, ENT_QUOTES, 'UTF-8'); ?>" data-search="<?php echo strtolower($pName . ' ' . $pSku . ' ' . $pCat); ?>">
                            <td class="col-cb"><input type="checkbox" class="commerce-row-cb" value="<?php echo $pId; ?>" aria-label="Select <?php echo $pName; ?>"></td>
                            <td>
                                <?php if ($pImg): ?>
                                    <img class="commerce-product-thumb" src="<?php echo htmlspecialchars($pImg, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <div class="commerce-product-thumb-placeholder"><i class="fas fa-box" aria-hidden="true"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="commerce-product-name"><?php echo $pName; ?></div>
                                <?php if ($pSku): ?><div class="commerce-product-sku">SKU: <?php echo $pSku; ?></div><?php endif; ?>
                            </td>
                            <td><?php echo $pCat ?: '—'; ?></td>
                            <td class="col-price">$<?php echo $pPrice; ?></td>
                            <td class="col-stock"><?php echo $pTrack ? $pStock : '—'; ?></td>
                            <td>
                                <span class="commerce-status-badge commerce-status-badge--<?php echo $statusClass; ?>">
                                    <?php echo $statusLabel; ?>
                                </span>
                            </td>
                            <td>
                                <div class="commerce-row-actions">
                                    <button class="commerce-btn commerce-btn--sm" style="background:#f3f4f6;color:#374151;border-color:#e5e7eb;" data-edit="<?php echo $pJson; ?>" title="Edit product">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </button>
                                    <?php if ($isAdmin): ?>
                                    <button class="commerce-btn commerce-btn--sm commerce-btn--danger" data-delete="<?php echo $pId; ?>" data-name="<?php echo $pName; ?>" title="Delete product">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
<?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Add / Edit Modal ─────────────────────────────────────────────────── -->
    <div class="commerce-modal-overlay" id="commerceModalOverlay" role="dialog" aria-modal="true" aria-labelledby="commerceModalTitle">
        <div class="commerce-modal">
            <div class="commerce-modal-header">
                <h3 class="commerce-modal-title" id="commerceModalTitle">Add Product</h3>
                <button class="commerce-modal-close" id="commerceModalClose" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="commerce-modal-body">
                <div id="commerceFormError" style="display:none;" class="commerce-inline-alert"></div>
                <form id="commerceProductForm" novalidate autocomplete="off">
                    <input type="hidden" id="productId" name="id" value="">
                    <div class="commerce-form-grid">
                        <div class="commerce-form-group full">
                            <label class="commerce-form-label" for="productName">Product Name *</label>
                            <input type="text" id="productName" name="name" class="commerce-form-input" required placeholder="e.g. Classic T-Shirt">
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="productSku">SKU</label>
                            <input type="text" id="productSku" name="sku" class="commerce-form-input" placeholder="e.g. TSH-001">
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="productSlug">Slug</label>
                            <input type="text" id="productSlug" name="slug" class="commerce-form-input" placeholder="Auto-generated if empty">
                        </div>

                        <div class="commerce-form-section-title">Pricing</div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="productPrice">Price ($) *</label>
                            <input type="number" id="productPrice" name="price" class="commerce-form-input" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="productComparePrice">Compare Price ($)</label>
                            <input type="number" id="productComparePrice" name="compare_price" class="commerce-form-input" min="0" step="0.01" placeholder="0.00">
                            <span class="commerce-form-hint">Original / crossed-out price</span>
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="productCost">Cost ($)</label>
                            <input type="number" id="productCost" name="cost" class="commerce-form-input" min="0" step="0.01" placeholder="0.00">
                            <span class="commerce-form-hint">Your cost (not shown publicly)</span>
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="productWeight">Weight (kg)</label>
                            <input type="number" id="productWeight" name="weight" class="commerce-form-input" min="0" step="0.001" placeholder="0.000">
                        </div>

                        <div class="commerce-form-section-title">Inventory</div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="productStock">Stock Quantity</label>
                            <input type="number" id="productStock" name="stock" class="commerce-form-input" min="0" step="1" placeholder="0">
                        </div>
                        <div class="commerce-form-group" style="justify-content:flex-end;">
                            <label class="commerce-form-check">
                                <input type="checkbox" id="productTrackStock" name="track_stock">
                                <span>Track stock</span>
                            </label>
                        </div>

                        <div class="commerce-form-section-title">Organisation</div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="productCategory">Category</label>
                            <input type="text" id="productCategory" name="category" class="commerce-form-input" placeholder="e.g. Apparel">
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="productStatus">Status</label>
                            <select id="productStatus" name="status" class="commerce-form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                        <div class="commerce-form-group full">
                            <label class="commerce-form-label" for="productTags">Tags</label>
                            <input type="text" id="productTags" name="tags" class="commerce-form-input" placeholder="e.g. new, sale, featured (comma-separated)">
                        </div>
                        <div class="commerce-form-group full">
                            <label class="commerce-form-label" for="productImage">Image URL</label>
                            <input type="url" id="productImage" name="image" class="commerce-form-input" placeholder="https://…">
                        </div>
                        <div class="commerce-form-group full">
                            <label class="commerce-form-label" for="productDescription">Description</label>
                            <textarea id="productDescription" name="description" class="commerce-form-textarea" placeholder="Product description…"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="commerce-modal-footer">
                <button type="button" class="commerce-btn" style="background:#f3f4f6;color:#374151;border-color:#e5e7eb;" id="commerceModalCancel">Cancel</button>
                <button type="button" class="commerce-btn commerce-btn--primary" id="commerceModalSave">
                    <i class="fas fa-save" aria-hidden="true"></i> Save Product
                </button>
            </div>
        </div>
    </div>

    <div class="commerce-toast" id="commerceToast"></div>
</div>
