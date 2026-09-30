<?php

/**
 * Application Routes
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Register every URL endpoint for the app.
 *
 * Route groups:
 *   • Public / auth       → Shehroz owns (declared by him)
 *   • /admin/*            → Admin role only
 *   • /staff/*            → Staff + Admin roles
 *   • /customer/*         → Customer role only
 *   • /api/*              → Authenticated staff (admin + staff)
 *
 * ⚠️ Router API assumption:
 *   This file assumes Shehroz's Router.php supports:
 *       $router->get($uri, [Controller::class, 'method']);
 *       $router->post($uri, [Controller::class, 'method']);
 *       $router->group(['prefix' => '...', 'middleware' => [...]], closure);
 *   If his API differs, adjust the syntax — the URL/method/middleware
 *   mapping stays the same.
 * ----------------------------------------------------------
 */

use App\Controllers\Admin\{
    DashboardController,
    CustomerController,
    StaffController,
    ItemController,
    ServiceController,
    PickupMonitorController,
    ComplaintController as AdminComplaintController,
    ReportController,
    SettingsController
};

use App\Controllers\Staff\{
    OrderController,
    PickupController,
    DeliveryController,
    ComplaintController as StaffComplaintController
};

use App\Controllers\Customer\{
    ComplaintController as CustomerComplaintController
};

use App\Controllers\Api\GlobalSearchController;

// ============================================================
//  PUBLIC ROUTES (guest — no auth)
//  Shehroz owns auth flow — you may already declare these.
//  Kept here as comments for reference only.
// ============================================================
// $router->get('/',               [HomeController::class,        'index']);
// $router->get('/login',          [AuthController::class,        'showLogin']);
// $router->post('/login',         [AuthController::class,        'login']);
// $router->get('/register',       [AuthController::class,        'showRegister']);
// $router->post('/register',      [AuthController::class,        'register']);
// $router->get('/logout',         [AuthController::class,        'logout']);
// ...

// ============================================================
//  ADMIN ROUTES  (role: admin)
// ============================================================
$router->group(['prefix' => '/admin', 'middleware' => ['AuthMiddleware', 'RoleMiddleware:admin']], function ($router) {

    // ----- Dashboard -----
    $router->get('/dashboard',              [DashboardController::class, 'index']);
    $router->get('/',                       [DashboardController::class, 'index']);

    // ----- Customers -----
    // Static paths BEFORE {id} wildcards
    $router->get('/customers',                              [CustomerController::class, 'index']);
    $router->get('/customers/export',                       [CustomerController::class, 'export']);
    $router->get('/customers/deletion-requests',            [CustomerController::class, 'deletionRequests']);
    $router->post('/customers/deletion-requests/{id}',      [CustomerController::class, 'processDeletion']);
    $router->get('/customers/{id}',                         [CustomerController::class, 'show']);
    $router->get('/customers/{id}/edit',                    [CustomerController::class, 'edit']);
    $router->post('/customers/{id}',                        [CustomerController::class, 'update']);
    $router->post('/customers/{id}/suspend',                [CustomerController::class, 'suspend']);
    $router->post('/customers/{id}/activate',               [CustomerController::class, 'activate']);
    $router->post('/customers/{id}/verify-email',           [CustomerController::class, 'verifyEmail']);
    $router->post('/customers/{id}/reset-password',         [CustomerController::class, 'resetPassword']);

    // ----- Staff -----
    $router->get('/staff',                                  [StaffController::class, 'index']);
    $router->get('/staff/export',                           [StaffController::class, 'export']);
    $router->get('/staff/performance-overview',             [StaffController::class, 'performanceOverview']);
    $router->get('/staff/create',                           [StaffController::class, 'create']);
    $router->post('/staff',                                 [StaffController::class, 'store']);
    $router->get('/staff/{id}',                             [StaffController::class, 'show']);
    $router->get('/staff/{id}/edit',                        [StaffController::class, 'edit']);
    $router->post('/staff/{id}',                            [StaffController::class, 'update']);
    $router->post('/staff/{id}/toggle-active',              [StaffController::class, 'toggleActive']);
    $router->post('/staff/{id}/change-role',                [StaffController::class, 'changeRole']);
    $router->post('/staff/{id}/reset-password',             [StaffController::class, 'resetPassword']);
    $router->get('/staff/{id}/performance',                 [StaffController::class, 'performance']);

    // ----- Catalog: Items -----
    $router->get('/catalog/items',                          [ItemController::class, 'index']);
    $router->get('/catalog/items/export',                   [ItemController::class, 'export']);
    $router->get('/catalog/items/create',                   [ItemController::class, 'create']);
    $router->post('/catalog/items',                         [ItemController::class, 'store']);
    $router->get('/catalog/items/{id}/edit',                [ItemController::class, 'edit']);
    $router->post('/catalog/items/{id}',                    [ItemController::class, 'update']);
    $router->post('/catalog/items/{id}/toggle',             [ItemController::class, 'toggle']);
    $router->post('/catalog/items/{id}/delete',             [ItemController::class, 'delete']);

    // ----- Catalog: Categories -----
    $router->get('/catalog/categories',                     [ItemController::class, 'categories']);
    $router->post('/catalog/categories',                    [ItemController::class, 'storeCategory']);
    $router->post('/catalog/categories/{id}',               [ItemController::class, 'updateCategory']);
    $router->post('/catalog/categories/{id}/toggle',        [ItemController::class, 'toggleCategory']);
    $router->post('/catalog/categories/{id}/delete',        [ItemController::class, 'deleteCategory']);

    // ----- Catalog: Services -----
    $router->get('/catalog/services',                       [ServiceController::class, 'index']);
    $router->get('/catalog/services/export',                [ServiceController::class, 'export']);
    $router->get('/catalog/services/create',                [ServiceController::class, 'create']);
    $router->post('/catalog/services',                      [ServiceController::class, 'store']);
    $router->get('/catalog/services/{id}',                  [ServiceController::class, 'show']);
    $router->get('/catalog/services/{id}/edit',             [ServiceController::class, 'edit']);
    $router->post('/catalog/services/{id}',                 [ServiceController::class, 'update']);
    $router->post('/catalog/services/{id}/toggle',          [ServiceController::class, 'toggle']);
    $router->post('/catalog/services/{id}/delete',          [ServiceController::class, 'delete']);

    // ----- Catalog: Pricing matrix -----
    $router->get('/catalog/pricing',                        [ServiceController::class, 'pricingMatrix']);
    $router->get('/catalog/pricing/export',                 [ServiceController::class, 'exportPricing']);
    $router->post('/catalog/pricing/update',                [ServiceController::class, 'updatePrice']);
    $router->post('/catalog/pricing/bulk-update',           [ServiceController::class, 'bulkUpdatePricing']);
    $router->post('/catalog/pricing/delete',                [ServiceController::class, 'deletePrice']);

    // ----- Pickup Monitor -----
    $router->get('/pickups/monitor',                        [PickupMonitorController::class, 'index']);
    $router->get('/pickups/monitor/live',                   [PickupMonitorController::class, 'liveFeed']);
    $router->get('/pickups/monitor/export',                 [PickupMonitorController::class, 'export']);
    $router->post('/pickups/monitor/bulk-assign',           [PickupMonitorController::class, 'bulkAssign']);
    $router->post('/pickups/monitor/{id}/reassign',         [PickupMonitorController::class, 'reassign']);
    $router->post('/pickups/monitor/{id}/reschedule',       [PickupMonitorController::class, 'reschedule']);
    $router->post('/pickups/monitor/{id}/force-status',     [PickupMonitorController::class, 'forceStatus']);
    $router->post('/pickups/monitor/deliveries/{id}/reassign', [PickupMonitorController::class, 'reassignDelivery']);

    // ----- Complaints -----
    $router->get('/complaints',                             [AdminComplaintController::class, 'index']);
    $router->get('/complaints/export',                      [AdminComplaintController::class, 'export']);
    $router->get('/complaints/escalations',                 [AdminComplaintController::class, 'escalations']);
    $router->get('/complaints/categories',                  [AdminComplaintController::class, 'categories']);
    $router->post('/complaints/categories',                 [AdminComplaintController::class, 'storeCategory']);
    $router->post('/complaints/categories/{id}',            [AdminComplaintController::class, 'updateCategory']);
    $router->post('/complaints/categories/{id}/toggle',     [AdminComplaintController::class, 'toggleCategory']);
    $router->post('/complaints/categories/{id}/delete',     [AdminComplaintController::class, 'deleteCategory']);
    $router->get('/complaints/{id}',                        [AdminComplaintController::class, 'show']);
    $router->post('/complaints/{id}/assign',                [AdminComplaintController::class, 'assign']);
    $router->post('/complaints/{id}/resolve',               [AdminComplaintController::class, 'resolve']);
    $router->post('/complaints/{id}/reject',                [AdminComplaintController::class, 'reject']);
    $router->post('/complaints/{id}/close',                 [AdminComplaintController::class, 'close']);

    // ----- Refunds -----
    $router->get('/refunds',                                [AdminComplaintController::class, 'refunds']);
    $router->post('/refunds/{id}/approve',                  [AdminComplaintController::class, 'approveRefund']);
    $router->post('/refunds/{id}/reject',                   [AdminComplaintController::class, 'rejectRefund']);
    $router->post('/refunds/{id}/complete',                 [AdminComplaintController::class, 'completeRefund']);

    // ----- Reports -----
    $router->get('/reports',                                [ReportController::class, 'index']);
    $router->get('/reports/overview',                       [ReportController::class, 'overview']);
    $router->get('/reports/export',                         [ReportController::class, 'export']);

    $router->get('/reports/sales',                          [ReportController::class, 'sales']);
    $router->get('/reports/sales/json',                     [ReportController::class, 'salesJson']);

    $router->get('/reports/orders',                         [ReportController::class, 'orders']);
    $router->get('/reports/orders/json',                    [ReportController::class, 'ordersJson']);

    $router->get('/reports/customers',                      [ReportController::class, 'customers']);
    $router->get('/reports/customers/json',                 [ReportController::class, 'customersJson']);

    $router->get('/reports/staff-performance',              [ReportController::class, 'staffPerformance']);
    $router->get('/reports/staff-performance/json',         [ReportController::class, 'staffPerformanceJson']);

    $router->get('/reports/complaints',                     [ReportController::class, 'complaints']);
    $router->get('/reports/complaints/json',                [ReportController::class, 'complaintsJson']);

    $router->get('/reports/refunds',                        [ReportController::class, 'refunds']);
    $router->get('/reports/refunds/json',                   [ReportController::class, 'refundsJson']);

    $router->get('/reports/items',                          [ReportController::class, 'items']);
    $router->get('/reports/items/json',                     [ReportController::class, 'itemsJson']);

    $router->get('/reports/deliveries',                     [ReportController::class, 'deliveries']);
    $router->get('/reports/deliveries/json',                [ReportController::class, 'deliveriesJson']);

    // ----- Settings -----
    $router->get('/settings',                               [SettingsController::class, 'index']);
    $router->get('/settings/export',                        [SettingsController::class, 'export']);
    $router->post('/settings/update',                       [SettingsController::class, 'update']);
    $router->post('/settings/update-single',                [SettingsController::class, 'updateSingle']);
    $router->post('/settings/store',                        [SettingsController::class, 'store']);
    $router->post('/settings/delete',                       [SettingsController::class, 'delete']);
    $router->post('/settings/reset',                        [SettingsController::class, 'reset']);
});

// ============================================================
//  STAFF ROUTES  (role: staff OR admin)
// ============================================================
$router->group(['prefix' => '/staff', 'middleware' => ['AuthMiddleware', 'RoleMiddleware:staff,admin']], function ($router) {

    // ----- Orders -----
    $router->get('/orders',                                 [OrderController::class, 'index']);
    $router->get('/orders/{id}',                            [OrderController::class, 'show']);
    $router->post('/orders/{id}/assign',                    [OrderController::class, 'assign']);
    $router->post('/orders/{id}/status',                    [OrderController::class, 'updateStatus']);
    $router->post('/orders/{id}/note',                      [OrderController::class, 'addNote']);
    $router->post('/orders/{id}/mark-received',             [OrderController::class, 'markReceived']);
    $router->post('/orders/{id}/mark-ready',                [OrderController::class, 'markReady']);

    // ----- Pickups -----
    $router->get('/pickups',                                [PickupController::class, 'index']);
    $router->get('/pickups/{id}',                           [PickupController::class, 'show']);
    $router->post('/pickups/{id}/assign',                   [PickupController::class, 'assign']);
    $router->post('/pickups/{id}/mark-picked-up',           [PickupController::class, 'markPickedUp']);
    $router->post('/pickups/{id}/mark-failed',              [PickupController::class, 'markFailed']);
    $router->post('/pickups/{id}/reschedule',               [PickupController::class, 'reschedule']);

    // ----- Deliveries -----
    $router->get('/deliveries',                             [DeliveryController::class, 'index']);
    $router->get('/deliveries/{id}',                        [DeliveryController::class, 'show']);
    $router->post('/deliveries/{id}/assign',                [DeliveryController::class, 'assign']);
    $router->post('/deliveries/{id}/out-for-delivery',      [DeliveryController::class, 'markOutForDelivery']);
    $router->post('/deliveries/{id}/delivered',             [DeliveryController::class, 'markDelivered']);
    $router->post('/deliveries/{id}/failed',                [DeliveryController::class, 'markFailed']);
    $router->post('/deliveries/{id}/reschedule',            [DeliveryController::class, 'reschedule']);

    // ----- Complaints -----
    $router->get('/complaints',                             [StaffComplaintController::class, 'index']);
    $router->get('/complaints/{id}',                        [StaffComplaintController::class, 'show']);
    $router->post('/complaints/{id}/assign',                [StaffComplaintController::class, 'assign']);
    $router->post('/complaints/{id}/investigate',           [StaffComplaintController::class, 'startInvestigation']);
    $router->post('/complaints/{id}/note',                  [StaffComplaintController::class, 'addNote']);
    $router->post('/complaints/{id}/resolve',               [StaffComplaintController::class, 'resolve']);
    $router->post('/complaints/{id}/reject',                [StaffComplaintController::class, 'reject']);
    $router->post('/complaints/{id}/escalate',              [StaffComplaintController::class, 'escalate']);
    $router->post('/complaints/{id}/request-refund',        [StaffComplaintController::class, 'requestRefund']);
});

// ============================================================
//  CUSTOMER ROUTES  (role: customer)
//  Rohan only owns the complaints sub-group.
//  Faizan (orders, pickups, profile) and Shehreen (billing,
//  payments) will declare their own customer routes elsewhere.
// ============================================================
$router->group(['prefix' => '/customer', 'middleware' => ['AuthMiddleware', 'RoleMiddleware:customer']], function ($router) {

    // ----- Complaints (Rohan) -----
    $router->get('/complaints',                             [CustomerComplaintController::class, 'index']);
    $router->get('/complaints/create/{order_id}',           [CustomerComplaintController::class, 'create']);
    $router->post('/complaints',                            [CustomerComplaintController::class, 'store']);
    $router->get('/complaints/{id}',                        [CustomerComplaintController::class, 'show']);
    $router->post('/complaints/{id}/cancel',                [CustomerComplaintController::class, 'cancel']);

    // (Faizan / Shehreen add their own /customer/* routes here)
});

// ============================================================
//  API ROUTES  (auth required — admin + staff)
// ============================================================
$router->group(['prefix' => '/api', 'middleware' => ['AuthMiddleware']], function ($router) {
    $router->get('/global-search',                          [GlobalSearchController::class, 'search']);
});