<?php

namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin\ServiceController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Admin master data for laundry services + pricing.
 *          Manages BOTH:
 *            • service          (Wash & Fold, Dry Cleaning, ...)
 *            • service_pricing  (item × service unit price matrix)
 *
 * Endpoints cover:
 *   Services:
 *     • list, create, store, edit, update, toggle, delete
 *   Pricing:
 *     • pricing matrix view (items × services)
 *     • update single price
 *     • bulk upsert prices (ON DUPLICATE KEY UPDATE)
 *     • delete a price row
 *   Combined:
 *     • summary, CSV export
 *
 * Safety guards:
 *   • Cannot delete a service in use by order_item or service_pricing
 *   • Pricing is unique per (item_id, service_id) → upsert-safe
 *   • Unit price must be > 0
 * ----------------------------------------------------------
 */
class ServiceController extends Controller
{
    private const PER_PAGE = 25;

    /** Valid service_type enum values from schema */
    private const SERVICE_TYPES = [
        'wash_fold',
        'dry_cleaning',
        'ironing',
        'wash_iron',
        'express',
        'premium',
        'blanket_cleaning',
        'curtain_cleaning',
    ];

    /** Human-readable labels for service_type */
    private const SERVICE_TYPE_LABELS = [
        'wash_fold'        => 'Wash & Fold',
        'dry_cleaning'     => 'Dry Cleaning',
        'ironing'          => 'Ironing Only',
        'wash_iron'        => 'Wash & Iron',
        'express'          => 'Express',
        'premium'          => 'Premium',
        'blanket_cleaning' => 'Blanket Cleaning',
        'curtain_cleaning' => 'Curtain Cleaning',
    ];

    // =========================================================
    //  INDEX — Services list
    // =========================================================
    public function index(): void
    {
        $this->requireAdmin();

        $search = trim($_GET['search'] ?? '');
        $type   = $_GET['type']        ?? '';
        $status = $_GET['status']      ?? '';
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = self::PER_PAGE;
        $offset  = ($page - 1) * $perPage;

        $where  = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[]          = "(service_name LIKE :search OR description LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if ($type !== '' && in_array($type, self::SERVICE_TYPES, true)) {
            $where[]        = "service_type = :type";
            $params['type'] = $type;
        }

        if ($status === 'active') {
            $where[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "is_active = 0";
        }

        $whereSql = implode(' AND ', $where);

        // Total
        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM service WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $totalRows  = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));

        // Rows with counts
        $sql = "SELECT s.*,
                       (SELECT COUNT(*) FROM service_pricing sp
                          WHERE sp.service_id = s.service_id) AS pricing_count,
                       (SELECT COUNT(*) FROM order_item oi
                          WHERE oi.service_id = s.service_id) AS orders_count
                FROM service s
                WHERE {$whereSql}
                ORDER BY s.is_active DESC, s.service_name ASC
                LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $services = $stmt->fetchAll();

        $summary = $this->summaryStats();

        $this->view('admin/catalog/services', [
            'services'    => $services,
            'summary'     => $summary,
            'search'      => $search,
            'type'        => $type,
            'status'      => $status,
            'page'        => $page,
            'perPage'     => $perPage,
            'totalRows'   => $totalRows,
            'totalPages'  => $totalPages,
            'types'       => self::SERVICE_TYPES,
            'typeLabels'  => self::SERVICE_TYPE_LABELS,
        ]);
    }

    // =========================================================
    //  SHOW — Single service detail with pricing rows
    // =========================================================
    public function show(int $id): void
    {
        $this->requireAdmin();

        $service = $this->findService($id);
        if (!$service) {
            $this->notFound('Service not found.');
            return;
        }

        // Pricing rows for this service (joined with items)
        $stmt = $this->db->prepare(
            "SELECT sp.pricing_id, sp.unit_price, sp.is_active,
                    it.item_id, it.item_name,
                    ic.category_id, ic.category_name
             FROM service_pricing sp
             JOIN item_type     it ON it.item_id     = sp.item_id
             JOIN item_category ic ON ic.category_id = it.category_id
             WHERE sp.service_id = :sid
             ORDER BY ic.category_name ASC, it.item_name ASC"
        );
        $stmt->execute(['sid' => $id]);
        $pricing = $stmt->fetchAll();

        // Usage stats
        $stmt = $this->db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM order_item WHERE service_id = :sid) AS orders_count,
                (SELECT COUNT(*) FROM service_pricing WHERE service_id = :sid) AS pricing_count,
                (SELECT COALESCE(AVG(unit_price), 0) FROM service_pricing WHERE service_id = :sid) AS avg_price,
                (SELECT COALESCE(MIN(unit_price), 0) FROM service_pricing WHERE service_id = :sid) AS min_price,
                (SELECT COALESCE(MAX(unit_price), 0) FROM service_pricing WHERE service_id = :sid) AS max_price"
        );
        $stmt->execute(['sid' => $id]);
        $stats = $stmt->fetch() ?: [];

        $this->view('admin/catalog/service_show', [
            'service'     => $service,
            'pricing'     => $pricing,
            'stats'       => $stats,
            'typeLabel'   => self::SERVICE_TYPE_LABELS[$service['service_type']] ?? $service['service_type'],
        ]);
    }

    // =========================================================
    //  SERVICE CRUD
    // =========================================================

    public function create(): void
    {
        $this->requireAdmin();

        $this->view('admin/catalog/service_form', [
            'mode'        => 'create',
            'service'     => null,
            'types'       => self::SERVICE_TYPES,
            'typeLabels'  => self::SERVICE_TYPE_LABELS,
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $serviceName   = trim($_POST['service_name']   ?? '');
        $description   = trim($_POST['description']    ?? '');
        $serviceType   = $_POST['service_type']        ?? '';
        $durationHours = (int) ($_POST['duration_hours'] ?? 0);
        $durationDays  = (int) ($_POST['duration_days']  ?? 0);
        $isActive      = isset($_POST['is_active']) ? 1 : 1;

        $errors = $this->validateService(
            $serviceName, $serviceType, $durationHours, $durationDays, $description
        );

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        try {
            $this->db->prepare(
                "INSERT INTO service
                    (service_name, description, service_type,
                     duration_hours, duration_days, is_active)
                 VALUES (:name, :desc, :type, :hours, :days, :active)"
            )->execute([
                'name'   => $serviceName,
                'desc'   => $description ?: null,
                'type'   => $serviceType,
                'hours'  => $durationHours,
                'days'   => $durationDays,
                'active' => $isActive,
            ]);

            $serviceId = (int) $this->db->lastInsertId();

            $this->audit('service', 'create', $serviceId, null, [
                'service_name' => $serviceName,
                'service_type' => $serviceType,
            ]);

            $this->jsonSuccess([
                'message'    => 'Service created successfully.',
                'service_id' => $serviceId,
                'redirect'   => '/admin/catalog/services/' . $serviceId,
            ]);

        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->jsonError('A service with this name already exists.', 409);
                return;
            }
            error_log('[AdminServiceController::store] ' . $e->getMessage());
            $this->jsonError('Failed to create service.', 500);
        }
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();

        $service = $this->findService($id);
        if (!$service) {
            $this->notFound('Service not found.');
            return;
        }

        $this->view('admin/catalog/service_form', [
            'mode'       => 'edit',
            'service'    => $service,
            'types'      => self::SERVICE_TYPES,
            'typeLabels' => self::SERVICE_TYPE_LABELS,
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $service = $this->findService($id);
        if (!$service) {
            $this->jsonError('Service not found.', 404);
            return;
        }

        $serviceName   = trim($_POST['service_name']    ?? '');
        $description   = trim($_POST['description']     ?? '');
        $serviceType   = $_POST['service_type']         ?? '';
        $durationHours = (int) ($_POST['duration_hours'] ?? 0);
        $durationDays  = (int) ($_POST['duration_days']  ?? 0);
        $isActive      = isset($_POST['is_active']) ? 1 : 0;

        $errors = $this->validateService(
            $serviceName, $serviceType, $durationHours, $durationDays, $description, $id
        );

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE service
                 SET service_name   = :name,
                     description    = :desc,
                     service_type   = :type,
                     duration_hours = :hours,
                     duration_days  = :days,
                     is_active      = :active
                 WHERE service_id = :id"
            )->execute([
                'name'   => $serviceName,
                'desc'   => $description ?: null,
                'type'   => $serviceType,
                'hours'  => $durationHours,
                'days'   => $durationDays,
                'active' => $isActive,
                'id'     => $id,
            ]);

            $this->audit('service', 'update', $id,
                ['service_name' => $service['service_name'], 'service_type' => $service['service_type']],
                ['service_name' => $serviceName, 'service_type' => $serviceType]
            );

            $this->jsonSuccess([
                'message'  => 'Service updated successfully.',
                'redirect' => '/admin/catalog/services/' . $id,
            ]);

        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->jsonError('A service with this name already exists.', 409);
                return;
            }
            error_log('[AdminServiceController::update] ' . $e->getMessage());
            $this->jsonError('Failed to update service.', 500);
        }
    }

    public function toggle(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $service = $this->findService($id);
        if (!$service) {
            $this->jsonError('Service not found.', 404);
            return;
        }

        $newState = !((bool) $service['is_active']);

        try {
            $this->db->prepare(
                "UPDATE service SET is_active = :a WHERE service_id = :id"
            )->execute(['a' => $newState ? 1 : 0, 'id' => $id]);

            // Cascade deactivation to pricing rows when deactivating the service
            if (!$newState) {
                $this->db->prepare(
                    "UPDATE service_pricing SET is_active = 0 WHERE service_id = :id"
                )->execute(['id' => $id]);
            }

            $this->audit('service', 'toggle', $id,
                ['is_active' => (int) $service['is_active']],
                ['is_active' => $newState ? 1 : 0]
            );

            $this->jsonSuccess([
                'message'   => 'Service ' . ($newState ? 'activated' : 'deactivated') . '.',
                'is_active' => $newState,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminServiceController::toggle] ' . $e->getMessage());
            $this->jsonError('Failed to update service.', 500);
        }
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $service = $this->findService($id);
        if (!$service) {
            $this->jsonError('Service not found.', 404);
            return;
        }

        // Guard 1: cannot delete if used by any order item
        $orderCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM order_item WHERE service_id = " . (int) $id
        )->fetchColumn();

        if ($orderCount > 0) {
            $this->jsonError(
                "Cannot delete: this service is used in {$orderCount} order(s). Deactivate it instead to preserve history.",
                422
            );
            return;
        }

        // Guard 2: pricing rows exist — must be removed first
        $pricingCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM service_pricing WHERE service_id = " . (int) $id
        )->fetchColumn();

        try {
            $this->db->beginTransaction();

            if ($pricingCount > 0) {
                $this->db->prepare(
                    "DELETE FROM service_pricing WHERE service_id = :id"
                )->execute(['id' => $id]);
            }

            $this->db->prepare(
                "DELETE FROM service WHERE service_id = :id"
            )->execute(['id' => $id]);

            $this->db->commit();

            $this->audit('service', 'delete', $id,
                ['service_name' => $service['service_name']], null);

            $this->jsonSuccess([
                'message'         => 'Service deleted successfully.',
                'pricing_removed' => $pricingCount,
            ]);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[AdminServiceController::delete] ' . $e->getMessage());
            $this->jsonError('Failed to delete service.', 500);
        }
    }

    // =========================================================
    //  PRICING MATRIX
    // =========================================================

    /**
     * Full pricing matrix: items (rows) × services (columns).
     * Shows price + active state for every combination that exists.
     */
    public function pricingMatrix(): void
    {
        $this->requireAdmin();

        $categoryId = (int) ($_GET['category'] ?? 0);
        $serviceId  = (int) ($_GET['service']  ?? 0);

        // Load services (columns)
        $servicesSql = "SELECT service_id, service_name, service_type, is_active
                        FROM service
                        WHERE 1 = 1";
        $servicesParams = [];

        if ($serviceId > 0) {
            $servicesSql .= " AND service_id = :sid";
            $servicesParams['sid'] = $serviceId;
        }
        $servicesSql .= " ORDER BY is_active DESC, service_name ASC";

        $servicesStmt = $this->db->prepare($servicesSql);
        $servicesStmt->execute($servicesParams);
        $services = $servicesStmt->fetchAll();

        // Load items (rows), grouped by category
        $itemsSql = "SELECT it.item_id, it.item_name, it.is_active,
                            ic.category_id, ic.category_name
                     FROM item_type it
                     JOIN item_category ic ON ic.category_id = it.category_id
                     WHERE 1 = 1";
        $itemsParams = [];

        if ($categoryId > 0) {
            $itemsSql .= " AND it.category_id = :cid";
            $itemsParams['cid'] = $categoryId;
        }

        $itemsSql .= " ORDER BY ic.category_name ASC, it.item_name ASC";

        $itemsStmt = $this->db->prepare($itemsSql);
        $itemsStmt->execute($itemsParams);
        $items = $itemsStmt->fetchAll();

        // Load all pricing rows in one shot
        $pricingStmt = $this->db->query(
            "SELECT pricing_id, item_id, service_id, unit_price, is_active
             FROM service_pricing"
        );
        $pricingRows = $pricingStmt->fetchAll();

        // Index by "item_id:service_id" for O(1) lookup in the view
        $pricingIndex = [];
        foreach ($pricingRows as $row) {
            $key = $row['item_id'] . ':' . $row['service_id'];
            $pricingIndex[$key] = $row;
        }

        // Categories for filter dropdown
        $categories = $this->db->query(
            "SELECT category_id, category_name FROM item_category
             ORDER BY category_name ASC"
        )->fetchAll();

        $this->view('admin/catalog/pricing_matrix', [
            'services'     => $services,
            'items'        => $items,
            'pricingIndex' => $pricingIndex,
            'categories'   => $categories,
            'categoryId'   => $categoryId,
            'serviceId'    => $serviceId,
            'typeLabels'   => self::SERVICE_TYPE_LABELS,
        ]);
    }

    /**
     * Update a single price from the matrix inline editor.
     */
    public function updatePrice(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $itemId    = (int) ($_POST['item_id']    ?? 0);
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $priceRaw  = $_POST['unit_price']        ?? '';

        if ($itemId <= 0 || $serviceId <= 0) {
            $this->jsonError('Invalid item or service.', 422);
            return;
        }

        if (!is_numeric($priceRaw)) {
            $this->jsonError('Unit price must be a number.', 422);
            return;
        }

        $price = (float) $priceRaw;
        if ($price <= 0 || $price > 999999.99) {
            $this->jsonError('Unit price must be greater than 0 and less than 1,000,000.', 422);
            return;
        }

        // Verify item + service exist
        if (!$this->findItem($itemId)) {
            $this->jsonError('Item not found.', 404);
            return;
        }
        if (!$this->findService($serviceId)) {
            $this->jsonError('Service not found.', 404);
            return;
        }

        try {
            // Upsert (unique key on item_id + service_id)
            $this->db->prepare(
                "INSERT INTO service_pricing (item_id, service_id, unit_price, is_active)
                 VALUES (:iid, :sid, :price, 1)
                 ON DUPLICATE KEY UPDATE
                    unit_price = VALUES(unit_price),
                    is_active  = 1,
                    updated_at = CURRENT_TIMESTAMP"
            )->execute([
                'iid'   => $itemId,
                'sid'   => $serviceId,
                'price' => $price,
            ]);

            $this->audit('service', 'price_update', $itemId, null, [
                'item_id'    => $itemId,
                'service_id' => $serviceId,
                'unit_price' => $price,
            ]);

            $this->jsonSuccess([
                'message'    => 'Price saved.',
                'item_id'    => $itemId,
                'service_id' => $serviceId,
                'unit_price' => $price,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminServiceController::updatePrice] ' . $e->getMessage());
            $this->jsonError('Failed to save price.', 500);
        }
    }

    /**
     * Bulk upsert prices from a full matrix form submission.
     * Expects: $_POST['prices'] as [ "item_id:service_id" => price, ... ]
     */
    public function bulkUpdatePricing(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $prices = $_POST['prices'] ?? [];

        if (!is_array($prices) || empty($prices)) {
            $this->jsonError('No prices submitted.', 422);
            return;
        }

        // Parse and validate all rows up front
        $clean = [];
        $errors = [];

        foreach ($prices as $key => $value) {
            // key format: "item_id:service_id"
            if (!preg_match('/^(\d+):(\d+)$/', (string) $key, $m)) {
                continue;
            }
            $itemId    = (int) $m[1];
            $serviceId = (int) $m[2];

            // Empty = skip (don't wipe existing)
            if (trim((string) $value) === '') {
                continue;
            }

            if (!is_numeric($value)) {
                $errors[] = "Invalid price for item {$itemId} / service {$serviceId}.";
                continue;
            }

            $price = (float) $value;
            if ($price <= 0 || $price > 999999.99) {
                $errors[] = "Out-of-range price for item {$itemId} / service {$serviceId}.";
                continue;
            }

            $clean[] = [
                'item_id'    => $itemId,
                'service_id' => $serviceId,
                'price'      => $price,
            ];
        }

        if (!empty($errors)) {
            $this->jsonError(implode(' ', array_slice($errors, 0, 5)), 422);
            return;
        }

        if (empty($clean)) {
            $this->jsonError('No valid prices to save.', 422);
            return;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "INSERT INTO service_pricing (item_id, service_id, unit_price, is_active)
                 VALUES (:iid, :sid, :price, 1)
                 ON DUPLICATE KEY UPDATE
                    unit_price = VALUES(unit_price),
                    is_active  = 1,
                    updated_at = CURRENT_TIMESTAMP"
            );

            $count = 0;
            foreach ($clean as $row) {
                $stmt->execute([
                    'iid'   => $row['item_id'],
                    'sid'   => $row['service_id'],
                    'price' => $row['price'],
                ]);
                $count++;
            }

            $this->db->commit();

            $this->audit('service', 'pricing_bulk_update', 0, null, [
                'rows_updated' => $count,
            ]);

            $this->jsonSuccess([
                'message'      => "{$count} price row(s) saved successfully.",
                'rows_updated' => $count,
            ]);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[AdminServiceController::bulkUpdatePricing] ' . $e->getMessage());
            $this->jsonError('Failed to save prices. Please try again.', 500);
        }
    }

    /**
     * Remove a single pricing row (make an item unavailable for a service).
     */
    public function deletePrice(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $pricingId = (int) ($_POST['pricing_id'] ?? 0);

        if ($pricingId <= 0) {
            $this->jsonError('Invalid pricing row.', 422);
            return;
        }

        // Load row
        $stmt = $this->db->prepare(
            "SELECT * FROM service_pricing WHERE pricing_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $pricingId]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->jsonError('Pricing row not found.', 404);
            return;
        }

        try {
            $this->db->prepare(
                "DELETE FROM service_pricing WHERE pricing_id = :id"
            )->execute(['id' => $pricingId]);

            $this->audit('service', 'price_delete', $pricingId, [
                'item_id'    => $row['item_id'],
                'service_id' => $row['service_id'],
                'unit_price' => $row['unit_price'],
            ], null);

            $this->jsonSuccess(['message' => 'Pricing row removed.']);

        } catch (\Throwable $e) {
            error_log('[AdminServiceController::deletePrice] ' . $e->getMessage());
            $this->jsonError('Failed to remove pricing row.', 500);
        }
    }

    // =========================================================
    //  EXPORT CSV
    // =========================================================
    public function export(): void
    {
        $this->requireAdmin();

        $search = trim($_GET['search'] ?? '');
        $type   = $_GET['type']        ?? '';
        $status = $_GET['status']      ?? '';

        $where  = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[]          = "(service_name LIKE :search OR description LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        if ($type !== '' && in_array($type, self::SERVICE_TYPES, true)) {
            $where[]        = "service_type = :type";
            $params['type'] = $type;
        }
        if ($status === 'active') {
            $where[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "is_active = 0";
        }

        $sql = "SELECT service_id, service_name, service_type,
                       duration_hours, duration_days, is_active,
                       description, created_at, updated_at
                FROM service
                WHERE " . implode(' AND ', $where) . "
                ORDER BY service_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $filename = 'services_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        fputcsv($out, [
            'ID', 'Service Name', 'Type', 'Duration (hours)', 'Duration (days)',
            'Active', 'Description', 'Created', 'Updated',
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['service_id'],
                $r['service_name'],
                self::SERVICE_TYPE_LABELS[$r['service_type']] ?? $r['service_type'],
                $r['duration_hours'],
                $r['duration_days'],
                $r['is_active'] ? 'Yes' : 'No',
                $r['description'] ?? '',
                $r['created_at'],
                $r['updated_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    /**
     * Export the full pricing matrix as CSV.
     */
    public function exportPricing(): void
    {
        $this->requireAdmin();

        $sql = "SELECT ic.category_name, it.item_name,
                       s.service_name, s.service_type,
                       sp.unit_price, sp.is_active
                FROM service_pricing sp
                JOIN item_type     it ON it.item_id     = sp.item_id
                JOIN item_category ic ON ic.category_id = it.category_id
                JOIN service       s  ON s.service_id   = sp.service_id
                ORDER BY ic.category_name ASC, it.item_name ASC, s.service_name ASC";

        $rows = $this->db->query($sql)->fetchAll();

        $filename = 'service_pricing_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Category', 'Item', 'Service', 'Type', 'Unit Price (PKR)', 'Active']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['category_name'],
                $r['item_name'],
                $r['service_name'],
                self::SERVICE_TYPE_LABELS[$r['service_type']] ?? $r['service_type'],
                $r['unit_price'],
                $r['is_active'] ? 'Yes' : 'No',
            ]);
        }

        fclose($out);
        exit;
    }

    // =========================================================
    //  VALIDATION
    // =========================================================

    private function validateService(
        string $name,
        string $type,
        int $durationHours,
        int $durationDays,
        string $description,
        ?int $excludeId = null
    ): array {
        $errors = [];

        // Name
        if ($name === '') {
            $errors['service_name'] = 'Service name is required.';
        } elseif (mb_strlen($name) < 3) {
            $errors['service_name'] = 'Service name must be at least 3 characters.';
        } elseif (mb_strlen($name) > 50) {
            $errors['service_name'] = 'Service name must not exceed 50 characters.';
        } else {
            // Case-insensitive duplicate
            $sql = "SELECT 1 FROM service WHERE LOWER(service_name) = LOWER(:n)";
            $params = ['n' => $name];

            if ($excludeId !== null) {
                $sql .= " AND service_id <> :id";
                $params['id'] = $excludeId;
            }

            $stmt = $this->db->prepare($sql . " LIMIT 1");
            $stmt->execute($params);
            if ($stmt->fetchColumn()) {
                $errors['service_name'] = 'A service with this name already exists.';
            }
        }

        // Type
        if (!in_array($type, self::SERVICE_TYPES, true)) {
            $errors['service_type'] = 'Invalid service type selected.';
        }

        // Duration
        if ($durationHours < 0 || $durationHours > 8760) {   // max 1 year
            $errors['duration_hours'] = 'Duration hours must be between 0 and 8760.';
        }

        if ($durationDays < 0 || $durationDays > 365) {
            $errors['duration_days'] = 'Duration days must be between 0 and 365.';
        }

        if ($durationHours === 0 && $durationDays === 0) {
            $errors['duration_hours'] = 'Provide at least one duration value (hours or days).';
        }

        // Description
        if ($description !== '' && mb_strlen($description) > 255) {
            $errors['description'] = 'Description must not exceed 255 characters.';
        }

        return $errors;
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    private function findService(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM service WHERE service_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function findItem(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM item_type WHERE item_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function summaryStats(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
                (SELECT COUNT(*) FROM service_pricing) AS pricing_rows,
                (SELECT COUNT(DISTINCT item_id) FROM service_pricing WHERE is_active = 1) AS covered_items,
                (SELECT COALESCE(AVG(unit_price), 0) FROM service_pricing WHERE is_active = 1) AS avg_price
             FROM service"
        )->fetch() ?: [];

        return [
            'total'          => (int)   ($row['total']         ?? 0),
            'active'         => (int)   ($row['active']        ?? 0),
            'inactive'       => (int)   ($row['inactive']      ?? 0),
            'pricing_rows'   => (int)   ($row['pricing_rows']  ?? 0),
            'covered_items'  => (int)   ($row['covered_items'] ?? 0),
            'avg_price'      => (float) ($row['avg_price']     ?? 0),
        ];
    }

    private function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            $this->forbidden();
            exit;
        }
    }

    private function isAdmin(): bool
    {
        return ($_SESSION['staff_role'] ?? '') === 'admin';
    }

    private function currentStaffId(): int|string
    {
        return $_SESSION['staff_id'] ?? 0;
    }

    // =========================================================
    //  HOOKS — Audit (stubbed)
    // =========================================================

    private function audit(string $module, string $action, int $entityId,
                            ?array $old, ?array $new): void
    {
        // TODO: (new \App\Core\AuditLogger())->log($module, $action, $entityId, $old, $new);
        error_log(sprintf(
            '[Audit] module=%s action=%s entity=%d old=%s new=%s staff=%s',
            $module, $action, $entityId,
            json_encode($old), json_encode($new),
            $this->currentStaffId()
        ));
    }
}