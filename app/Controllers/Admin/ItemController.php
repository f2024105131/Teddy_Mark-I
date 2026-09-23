<?php

namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin\ItemController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Admin master data for the laundry catalog.
 *          Manages BOTH:
 *            • item_category (Men's Wear, Household, ...)
 *            • item_type     (Shirt, Saree, Blanket, ...)
 *
 * Endpoints cover:
 *   Categories:
 *     • list, create, store, edit, update, toggle, delete
 *   Items:
 *     • list, create, store, edit, update, toggle, delete
 *   Combined:
 *     • catalog overview (category tree with counts)
 *     • CSV export
 *
 * Safety guards:
 *   • Cannot delete a category that has items
 *   • Cannot delete an item referenced by orders or pricing
 *   • Unique name per parent (respects uk_category_item)
 *   • Case-insensitive duplicate detection
 * ----------------------------------------------------------
 */
class ItemController extends Controller
{
    private const PER_PAGE = 25;

    // =========================================================
    //  CATALOG OVERVIEW — category tree with item counts
    // =========================================================
    public function index(): void
    {
        $this->requireAdmin();

        $search     = trim($_GET['search']     ?? '');
        $categoryId = (int)  ($_GET['category'] ?? 0);
        $status     = $_GET['status']          ?? ''; // active | inactive | ''
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $perPage    = self::PER_PAGE;
        $offset     = ($page - 1) * $perPage;

        // ---- Item list (paginated) ----
        $where  = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[]          = "(it.item_name LIKE :search OR ic.category_name LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if ($categoryId > 0) {
            $where[]         = "it.category_id = :cid";
            $params['cid']   = $categoryId;
        }

        if ($status === 'active') {
            $where[] = "it.is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "it.is_active = 0";
        }

        $whereSql = implode(' AND ', $where);

        // Total
        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM item_type it
             JOIN item_category ic ON ic.category_id = it.category_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $totalRows  = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));

        // Rows
        $sql = "SELECT it.item_id, it.item_name, it.description,
                       it.is_active, it.created_at, it.updated_at,
                       ic.category_id, ic.category_name,
                       (SELECT COUNT(*) FROM service_pricing sp
                          WHERE sp.item_id = it.item_id) AS pricing_count,
                       (SELECT COUNT(*) FROM order_item oi
                          WHERE oi.item_id = it.item_id) AS orders_count
                FROM item_type it
                JOIN item_category ic ON ic.category_id = it.category_id
                WHERE {$whereSql}
                ORDER BY ic.category_name ASC, it.item_name ASC
                LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        // Categories (for sidebar / tree / dropdown)
        $categories = $this->db->query(
            "SELECT ic.*,
                    (SELECT COUNT(*) FROM item_type it WHERE it.category_id = ic.category_id) AS items_count,
                    (SELECT COUNT(*) FROM item_type it
                     WHERE it.category_id = ic.category_id AND it.is_active = 1) AS active_items_count
             FROM item_category ic
             ORDER BY ic.is_active DESC, ic.category_name ASC"
        )->fetchAll();

        $summary = $this->catalogSummary();

        $this->view('admin/catalog/items', [
            'items'       => $items,
            'categories'  => $categories,
            'summary'     => $summary,
            'search'      => $search,
            'categoryId'  => $categoryId,
            'status'      => $status,
            'page'        => $page,
            'perPage'     => $perPage,
            'totalRows'   => $totalRows,
            'totalPages'  => $totalPages,
        ]);
    }

    // =========================================================
    //  CATEGORY CRUD
    // =========================================================

    public function categories(): void
    {
        $this->requireAdmin();

        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status']      ?? '';

        $where  = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[]          = "category_name LIKE :search";
            $params['search'] = '%' . $search . '%';
        }
        if ($status === 'active') {
            $where[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "is_active = 0";
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT ic.*,
                    (SELECT COUNT(*) FROM item_type it
                     WHERE it.category_id = ic.category_id) AS items_count,
                    (SELECT COUNT(*) FROM item_type it
                     WHERE it.category_id = ic.category_id AND it.is_active = 1) AS active_items_count,
                    (SELECT COUNT(*) FROM service_pricing sp
                     JOIN item_type it ON it.item_id = sp.item_id
                     WHERE it.category_id = ic.category_id) AS pricing_count
                FROM item_category ic
                WHERE {$whereSql}
                ORDER BY ic.is_active DESC, ic.category_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll();

        $this->view('admin/catalog/categories', [
            'categories' => $categories,
            'search'     => $search,
            'status'     => $status,
        ]);
    }

    public function storeCategory(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $name        = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description']   ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 1;

        $errors = $this->validateCategory($name, $description);

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        try {
            $this->db->prepare(
                "INSERT INTO item_category (category_name, description, is_active)
                 VALUES (:name, :desc, :active)"
            )->execute([
                'name'   => $name,
                'desc'   => $description ?: null,
                'active' => $isActive,
            ]);

            $categoryId = (int) $this->db->lastInsertId();

            $this->audit('service', 'category_create', $categoryId, null, [
                'category_name' => $name,
            ]);

            $this->jsonSuccess([
                'message'     => 'Category created successfully.',
                'category_id' => $categoryId,
                'redirect'    => '/admin/catalog/categories',
            ]);

        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->jsonError('A category with this name already exists.', 409);
                return;
            }
            error_log('[AdminItemController::storeCategory] ' . $e->getMessage());
            $this->jsonError('Failed to create category.', 500);
        }
    }

    public function updateCategory(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $category = $this->findCategory($id);
        if (!$category) {
            $this->jsonError('Category not found.', 404);
            return;
        }

        $name        = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description']   ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        $errors = $this->validateCategory($name, $description, $id);

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE item_category
                 SET category_name = :name,
                     description   = :desc,
                     is_active     = :active
                 WHERE category_id = :id"
            )->execute([
                'name'   => $name,
                'desc'   => $description ?: null,
                'active' => $isActive,
                'id'     => $id,
            ]);

            $this->audit('service', 'category_update', $id,
                ['category_name' => $category['category_name'], 'is_active' => (int) $category['is_active']],
                ['category_name' => $name, 'is_active' => $isActive]
            );

            $this->jsonSuccess([
                'message'  => 'Category updated successfully.',
                'redirect' => '/admin/catalog/categories',
            ]);

        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->jsonError('A category with this name already exists.', 409);
                return;
            }
            error_log('[AdminItemController::updateCategory] ' . $e->getMessage());
            $this->jsonError('Failed to update category.', 500);
        }
    }

    public function toggleCategory(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $category = $this->findCategory($id);
        if (!$category) {
            $this->jsonError('Category not found.', 404);
            return;
        }

        $newState = !((bool) $category['is_active']);

        try {
            $this->db->prepare(
                "UPDATE item_category SET is_active = :a WHERE category_id = :id"
            )->execute(['a' => $newState ? 1 : 0, 'id' => $id]);

            $this->audit('service', 'category_toggle', $id,
                ['is_active' => (int) $category['is_active']],
                ['is_active' => $newState ? 1 : 0]
            );

            $this->jsonSuccess([
                'message'   => 'Category ' . ($newState ? 'activated' : 'deactivated') . '.',
                'is_active' => $newState,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminItemController::toggleCategory] ' . $e->getMessage());
            $this->jsonError('Failed to update category.', 500);
        }
    }

    public function deleteCategory(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $category = $this->findCategory($id);
        if (!$category) {
            $this->jsonError('Category not found.', 404);
            return;
        }

        // Guard: cannot delete category with items
        $itemCount = (int) $this->db
            ->query("SELECT COUNT(*) FROM item_type WHERE category_id = " . (int) $id)
            ->fetchColumn();

        if ($itemCount > 0) {
            $this->jsonError(
                "Cannot delete: {$itemCount} item(s) belong to this category. Move or delete them first, or deactivate the category instead.",
                422
            );
            return;
        }

        try {
            $this->db->prepare(
                "DELETE FROM item_category WHERE category_id = :id"
            )->execute(['id' => $id]);

            $this->audit('service', 'category_delete', $id,
                ['category_name' => $category['category_name']], null);

            $this->jsonSuccess(['message' => 'Category deleted successfully.']);

        } catch (\Throwable $e) {
            error_log('[AdminItemController::deleteCategory] ' . $e->getMessage());
            $this->jsonError('Failed to delete category.', 500);
        }
    }

    // =========================================================
    //  ITEM CRUD
    // =========================================================

    public function create(): void
    {
        $this->requireAdmin();

        $categories = $this->db->query(
            "SELECT category_id, category_name, is_active
             FROM item_category
             ORDER BY is_active DESC, category_name ASC"
        )->fetchAll();

        $preselected = (int) ($_GET['category'] ?? 0);

        $this->view('admin/catalog/item_form', [
            'mode'        => 'create',
            'item'        => null,
            'categories'  => $categories,
            'preselected' => $preselected,
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $categoryId  = (int) ($_POST['category_id'] ?? 0);
        $itemName    = trim($_POST['item_name']   ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 1;

        $errors = $this->validateItem($itemName, $categoryId, $description);

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        try {
            $this->db->prepare(
                "INSERT INTO item_type (category_id, item_name, description, is_active)
                 VALUES (:cid, :name, :desc, :active)"
            )->execute([
                'cid'    => $categoryId,
                'name'   => $itemName,
                'desc'   => $description ?: null,
                'active' => $isActive,
            ]);

            $itemId = (int) $this->db->lastInsertId();

            $this->audit('service', 'item_create', $itemId, null, [
                'item_name'   => $itemName,
                'category_id' => $categoryId,
            ]);

            $this->jsonSuccess([
                'message'  => 'Item created successfully.',
                'item_id'  => $itemId,
                'redirect' => '/admin/catalog/items',
            ]);

        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->jsonError('An item with this name already exists in the selected category.', 409);
                return;
            }
            error_log('[AdminItemController::store] ' . $e->getMessage());
            $this->jsonError('Failed to create item.', 500);
        }
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();

        $item = $this->findItem($id);
        if (!$item) {
            $this->notFound('Item not found.');
            return;
        }

        $categories = $this->db->query(
            "SELECT category_id, category_name, is_active
             FROM item_category
             ORDER BY is_active DESC, category_name ASC"
        )->fetchAll();

        $this->view('admin/catalog/item_form', [
            'mode'       => 'edit',
            'item'       => $item,
            'categories' => $categories,
            'preselected' => (int) $item['category_id'],
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $item = $this->findItem($id);
        if (!$item) {
            $this->jsonError('Item not found.', 404);
            return;
        }

        $categoryId  = (int) ($_POST['category_id'] ?? 0);
        $itemName    = trim($_POST['item_name']   ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        $errors = $this->validateItem($itemName, $categoryId, $description, $id);

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE item_type
                 SET category_id = :cid,
                     item_name   = :name,
                     description = :desc,
                     is_active   = :active
                 WHERE item_id = :id"
            )->execute([
                'cid'    => $categoryId,
                'name'   => $itemName,
                'desc'   => $description ?: null,
                'active' => $isActive,
                'id'     => $id,
            ]);

            $this->audit('service', 'item_update', $id,
                ['item_name' => $item['item_name'], 'category_id' => $item['category_id']],
                ['item_name' => $itemName, 'category_id' => $categoryId]
            );

            $this->jsonSuccess([
                'message'  => 'Item updated successfully.',
                'redirect' => '/admin/catalog/items',
            ]);

        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->jsonError('An item with this name already exists in the selected category.', 409);
                return;
            }
            error_log('[AdminItemController::update] ' . $e->getMessage());
            $this->jsonError('Failed to update item.', 500);
        }
    }

    public function toggle(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $item = $this->findItem($id);
        if (!$item) {
            $this->jsonError('Item not found.', 404);
            return;
        }

        $newState = !((bool) $item['is_active']);

        try {
            $this->db->prepare(
                "UPDATE item_type SET is_active = :a WHERE item_id = :id"
            )->execute(['a' => $newState ? 1 : 0, 'id' => $id]);

            $this->audit('service', 'item_toggle', $id,
                ['is_active' => (int) $item['is_active']],
                ['is_active' => $newState ? 1 : 0]
            );

            $this->jsonSuccess([
                'message'   => 'Item ' . ($newState ? 'activated' : 'deactivated') . '.',
                'is_active' => $newState,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminItemController::toggle] ' . $e->getMessage());
            $this->jsonError('Failed to update item.', 500);
        }
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $item = $this->findItem($id);
        if (!$item) {
            $this->jsonError('Item not found.', 404);
            return;
        }

        // Guard: cannot delete if referenced by orders or pricing
        $pricingCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM service_pricing WHERE item_id = " . (int) $id
        )->fetchColumn();

        $orderCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM order_item WHERE item_id = " . (int) $id
        )->fetchColumn();

        if ($orderCount > 0) {
            $this->jsonError(
                "Cannot delete: this item appears in {$orderCount} order(s). Deactivate it instead to preserve order history.",
                422
            );
            return;
        }

        if ($pricingCount > 0) {
            $this->jsonError(
                "Cannot delete: this item has {$pricingCount} pricing row(s). Remove pricing first, or deactivate the item.",
                422
            );
            return;
        }

        try {
            $this->db->prepare("DELETE FROM item_type WHERE item_id = :id")
                    ->execute(['id' => $id]);

            $this->audit('service', 'item_delete', $id,
                ['item_name' => $item['item_name']], null);

            $this->jsonSuccess(['message' => 'Item deleted successfully.']);

        } catch (\Throwable $e) {
            error_log('[AdminItemController::delete] ' . $e->getMessage());
            $this->jsonError('Failed to delete item.', 500);
        }
    }

    // =========================================================
    //  EXPORT CSV
    // =========================================================
    public function export(): void
    {
        $this->requireAdmin();

        $search     = trim($_GET['search']     ?? '');
        $categoryId = (int)  ($_GET['category'] ?? 0);
        $status     = $_GET['status']          ?? '';

        $where  = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[]          = "(it.item_name LIKE :search OR ic.category_name LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        if ($categoryId > 0) {
            $where[]       = "it.category_id = :cid";
            $params['cid'] = $categoryId;
        }
        if ($status === 'active') {
            $where[] = "it.is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "it.is_active = 0";
        }

        $sql = "SELECT it.item_id, it.item_name, it.description, it.is_active,
                       ic.category_name, it.created_at, it.updated_at
                FROM item_type it
                JOIN item_category ic ON ic.category_id = it.category_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY ic.category_name ASC, it.item_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $filename = 'catalog_items_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        fputcsv($out, ['ID', 'Item Name', 'Category', 'Description', 'Active', 'Created', 'Updated']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['item_id'],
                $r['item_name'],
                $r['category_name'],
                $r['description'] ?? '',
                $r['is_active'] ? 'Yes' : 'No',
                $r['created_at'],
                $r['updated_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    // =========================================================
    //  VALIDATION
    // =========================================================

    private function validateCategory(string $name, string $description, ?int $excludeId = null): array
    {
        $errors = [];

        if ($name === '') {
            $errors['category_name'] = 'Category name is required.';
        } elseif (mb_strlen($name) < 2) {
            $errors['category_name'] = 'Category name must be at least 2 characters.';
        } elseif (mb_strlen($name) > 50) {
            $errors['category_name'] = 'Category name must not exceed 50 characters.';
        } else {
            // Case-insensitive duplicate check
            $sql = "SELECT 1 FROM item_category
                    WHERE LOWER(category_name) = LOWER(:n)";
            $params = ['n' => $name];

            if ($excludeId !== null) {
                $sql .= " AND category_id <> :id";
                $params['id'] = $excludeId;
            }

            $stmt = $this->db->prepare($sql . " LIMIT 1");
            $stmt->execute($params);
            if ($stmt->fetchColumn()) {
                $errors['category_name'] = 'A category with this name already exists.';
            }
        }

        if ($description !== '' && mb_strlen($description) > 255) {
            $errors['description'] = 'Description must not exceed 255 characters.';
        }

        return $errors;
    }

    private function validateItem(string $name, int $categoryId, string $description, ?int $excludeId = null): array
    {
        $errors = [];

        if ($categoryId <= 0) {
            $errors['category_id'] = 'Please select a category.';
        } else {
            $cat = $this->findCategory($categoryId);
            if (!$cat) {
                $errors['category_id'] = 'Selected category does not exist.';
            }
        }

        if ($name === '') {
            $errors['item_name'] = 'Item name is required.';
        } elseif (mb_strlen($name) < 2) {
            $errors['item_name'] = 'Item name must be at least 2 characters.';
        } elseif (mb_strlen($name) > 100) {
            $errors['item_name'] = 'Item name must not exceed 100 characters.';
        } elseif ($categoryId > 0) {
            // Unique per (category_id, item_name) — case-insensitive
            $sql = "SELECT 1 FROM item_type
                    WHERE LOWER(item_name) = LOWER(:n) AND category_id = :cid";
            $params = ['n' => $name, 'cid' => $categoryId];

            if ($excludeId !== null) {
                $sql .= " AND item_id <> :id";
                $params['id'] = $excludeId;
            }

            $stmt = $this->db->prepare($sql . " LIMIT 1");
            $stmt->execute($params);
            if ($stmt->fetchColumn()) {
                $errors['item_name'] = 'An item with this name already exists in the selected category.';
            }
        }

        if ($description !== '' && mb_strlen($description) > 255) {
            $errors['description'] = 'Description must not exceed 255 characters.';
        }

        return $errors;
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    private function findCategory(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM item_category WHERE category_id = :id LIMIT 1"
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

    private function catalogSummary(): array
    {
        $row = $this->db->query(
            "SELECT
                (SELECT COUNT(*) FROM item_category)                     AS categories_total,
                (SELECT COUNT(*) FROM item_category WHERE is_active = 1) AS categories_active,
                (SELECT COUNT(*) FROM item_type)                         AS items_total,
                (SELECT COUNT(*) FROM item_type WHERE is_active = 1)     AS items_active,
                (SELECT COUNT(*) FROM service_pricing)                   AS pricing_total"
        )->fetch() ?: [];

        return [
            'categories_total'  => (int) ($row['categories_total']  ?? 0),
            'categories_active' => (int) ($row['categories_active'] ?? 0),
            'items_total'       => (int) ($row['items_total']       ?? 0),
            'items_active'      => (int) ($row['items_active']      ?? 0),
            'pricing_total'     => (int) ($row['pricing_total']     ?? 0),
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