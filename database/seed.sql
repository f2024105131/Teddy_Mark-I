-- ============================================================
-- LAUNDRY MANAGEMENT SYSTEM — SEED DATA
-- Author: Rohan
-- Compatible with: app/Core/Model.php (Shehroz)
-- Run AFTER schema.sql + create_app_user.sql
-- ============================================================

USE laundry_management;

-- ------------------------------------------------------------
-- 1. DEFAULT STAFF
--    Bcrypt hash below = "Password@123"
-- ------------------------------------------------------------
INSERT IGNORE INTO staff (full_name, email, phone, password_hash, role, is_active)
VALUES
('System Admin',  'admin@laundry.com',  '03001234567',
 '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HcU4l5SjG6dGE9p9sZKpA0qF8Bm6qO',
 'admin', TRUE),
('Ali Rider',     'ali@laundry.com',    '03001234568',
 '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HcU4l5SjG6dGE9p9sZKpA0qF8Bm6qO',
 'staff', TRUE),
('Sara Washer',   'sara@laundry.com',   '03001234569',
 '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HcU4l5SjG6dGE9p9sZKpA0qF8Bm6qO',
 'staff', TRUE),
('Bilal Ironman', 'bilal@laundry.com',  '03001234570',
 '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HcU4l5SjG6dGE9p9sZKpA0qF8Bm6qO',
 'staff', TRUE);

-- ------------------------------------------------------------
-- 2. SAMPLE CUSTOMERS
-- ------------------------------------------------------------
INSERT IGNORE INTO customer
(full_name, email, phone, password_hash,
 address_house, address_street, address_area, city, landmark,
 account_status, email_verified)
VALUES
('Ahmed Khan', 'ahmed@example.com', '03211234567',
 '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HcU4l5SjG6dGE9p9sZKpA0qF8Bm6qO',
 '12-A', 'Main Boulevard', 'Gulberg III', 'Lahore', 'Near Mini Market',
 'active', TRUE),
('Fatima Ali', 'fatima@example.com', '03211234568',
 '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HcU4l5SjG6dGE9p9sZKpA0qF8Bm6qO',
 '45', 'Jinnah Road', 'DHA Phase 5', 'Karachi', 'Opposite Park',
 'active', TRUE);

-- ------------------------------------------------------------
-- 3. ITEM CATEGORIES
-- ------------------------------------------------------------
INSERT IGNORE INTO item_category (category_name, description) VALUES
('Men''s Wear',   'Shirts, pants, suits, coats'),
('Women''s Wear', 'Dresses, sarees, blouses, dupattas'),
('Kids Wear',     'Children''s clothing and uniforms'),
('Household',     'Bedding, curtains, blankets, towels');

-- ------------------------------------------------------------
-- 4. ITEM TYPES
-- ------------------------------------------------------------
INSERT IGNORE INTO item_type (category_id, item_name, description) VALUES
(1, 'Shirt',           'Formal or casual shirt'),
(1, 'Pant',            'Trousers or dress pants'),
(1, 'Suit (2pc)',      'Jacket + trousers'),
(1, 'Coat',            'Winter coat or blazer'),
(2, 'Dress',           'Casual or formal dress'),
(2, 'Saree',           'Traditional saree'),
(2, 'Blouse',          'Women''s blouse'),
(3, 'Kids Shirt',      'Children''s shirt'),
(3, 'Kids Pant',       'Children''s trousers'),
(3, 'School Uniform',  'Complete school uniform set'),
(4, 'Blanket',         'Single or double blanket'),
(4, 'Curtain',         'Window or door curtain'),
(4, 'Bed Sheet',       'Single or double bed sheet'),
(4, 'Towel',           'Bath or hand towel');

-- ------------------------------------------------------------
-- 5. SERVICES
-- ------------------------------------------------------------
INSERT IGNORE INTO service
(service_name, description, service_type, duration_hours, duration_days)
VALUES
('Wash & Fold',      'Standard wash and fold service',              'wash_fold',        0,  2),
('Wash & Iron',      'Wash followed by professional ironing',       'wash_iron',        0,  3),
('Dry Cleaning',     'Premium dry cleaning for delicate fabrics',   'dry_cleaning',     0,  4),
('Ironing Only',     'Professional pressing only',                  'ironing',          0,  1),
('Express 24hr',     'Same-day express service',                    'express',         24,  0),
('Premium Care',     'Premium handling for luxury garments',        'premium',          0,  5),
('Blanket Cleaning', 'Deep cleaning for blankets',                  'blanket_cleaning', 0,  3),
('Curtain Cleaning', 'Specialized curtain cleaning',                'curtain_cleaning', 0,  4);

-- ------------------------------------------------------------
-- 6. SERVICE PRICING MATRIX
-- ------------------------------------------------------------
INSERT IGNORE INTO service_pricing (item_id, service_id, unit_price) VALUES
-- Wash & Fold (service_id = 1)
(1, 1, 100.00), (2, 1, 120.00), (3, 1, 300.00), (4, 1, 400.00),
(5, 1, 150.00), (6, 1, 250.00), (7, 1, 100.00), (8, 1, 70.00),
(9, 1, 80.00),  (10,1, 200.00), (11,1, 350.00), (12,1, 300.00),
(13,1, 200.00), (14,1, 50.00),
-- Wash & Iron (service_id = 2)
(1, 2, 150.00), (2, 2, 180.00), (3, 2, 450.00), (4, 2, 550.00),
(5, 2, 220.00), (6, 2, 350.00), (7, 2, 150.00), (8, 2, 100.00),
(9, 2, 120.00), (10,2, 280.00),
-- Dry Cleaning (service_id = 3)
(1, 3, 250.00), (2, 3, 280.00), (3, 3, 700.00), (4, 3, 800.00),
(5, 3, 400.00), (6, 3, 600.00), (11,3, 700.00), (12,3, 600.00),
-- Ironing Only (service_id = 4)
(1, 4, 50.00),  (2, 4, 60.00),  (3, 4, 150.00), (4, 4, 180.00),
(5, 4, 80.00),  (6, 4, 100.00), (8, 4, 40.00),  (9, 4, 40.00),
-- Express 24hr (service_id = 5)
(1, 5, 220.00), (2, 5, 260.00), (3, 5, 650.00), (5, 5, 320.00),
(11,5, 500.00),
-- Blanket Cleaning (service_id = 7)
(11,7, 500.00),
-- Curtain Cleaning (service_id = 8)
(12,8, 450.00);

-- ------------------------------------------------------------
-- 7. PICKUP SLOTS
-- ------------------------------------------------------------
INSERT IGNORE INTO pickup_slot (start_time, end_time, max_capacity, day_of_week) VALUES
('09:00:00','11:00:00',5,'monday'),
('11:00:00','13:00:00',5,'monday'),
('14:00:00','16:00:00',5,'monday'),
('09:00:00','11:00:00',5,'tuesday'),
('11:00:00','13:00:00',5,'tuesday'),
('14:00:00','16:00:00',5,'tuesday'),
('09:00:00','11:00:00',5,'wednesday'),
('11:00:00','13:00:00',5,'wednesday'),
('14:00:00','16:00:00',5,'wednesday'),
('09:00:00','11:00:00',5,'thursday'),
('11:00:00','13:00:00',5,'thursday'),
('14:00:00','16:00:00',5,'thursday'),
('09:00:00','11:00:00',5,'friday'),
('11:00:00','13:00:00',5,'friday'),
('14:00:00','16:00:00',5,'friday'),
('09:00:00','11:00:00',5,'saturday'),
('11:00:00','13:00:00',5,'saturday'),
('14:00:00','16:00:00',5,'saturday');

-- ------------------------------------------------------------
-- 8. DELIVERY SLOTS
-- ------------------------------------------------------------
INSERT IGNORE INTO delivery_slot (start_time, end_time, max_capacity) VALUES
('10:00:00','12:00:00',10),
('12:00:00','14:00:00',10),
('15:00:00','17:00:00',10),
('17:00:00','19:00:00',10),
('19:00:00','21:00:00',10);

-- ------------------------------------------------------------
-- 9. COMPLAINT CATEGORIES
-- ------------------------------------------------------------
INSERT IGNORE INTO complaint_category (category_name, description) VALUES
('Missing Item',          'One or more items not returned'),
('Damaged Item',          'Item returned in damaged condition'),
('Late Delivery',         'Delivery exceeded promised time'),
('Wrong Billing',         'Incorrect charges on bill'),
('Poor Cleaning Quality', 'Items not cleaned properly');

-- ------------------------------------------------------------
-- 10. SYSTEM CONFIG
-- ------------------------------------------------------------
INSERT IGNORE INTO system_config
(config_key, config_value, data_type, description, updated_by_staff_id)
VALUES
('business_name',           'LaundryPro',                'string',  'Business display name',                    1),
('business_email',          'info@laundrypro.com',       'string',  'Business contact email',                   1),
('business_phone',          '03001234567',               'string',  'Business contact phone',                   1),
('tax_percentage',          '5',                         'decimal', 'Tax percentage applied to bills',          1),
('max_pickup_distance_km',  '10',                        'integer', 'Maximum pickup radius in kilometers',      1),
('default_delivery_days',   '3',                         'integer', 'Default delivery duration in days',        1),
('late_fee_amount',         '100',                       'decimal', 'Late pickup/delivery fee in PKR',          1),
('business_open_time',      '09:00',                     'string',  'Daily opening time (HH:MM)',               1),
('business_close_time',     '21:00',                     'string',  'Daily closing time (HH:MM)',               1),
('same_day_enabled',        'true',                      'boolean', 'Allow same-day pickup requests',           1),
('express_surcharge_pct',   '50',                        'decimal', 'Express service surcharge percentage',     1),
('max_items_per_order',     '50',                        'integer', 'Maximum items allowed per order',          1),
('complaint_window_days',   '7',                         'integer', 'Days after delivery to file a complaint',  1),
('currency',                'PKR',                       'string',  'Currency code',                            1),
('currency_symbol',         'Rs.',                       'string',  'Currency display symbol',                  1);

-- ------------------------------------------------------------
-- 11. STAFF PERFORMANCE (demo data — IGNORE-protected)
-- ------------------------------------------------------------
INSERT IGNORE INTO staff_performance
(staff_id, pickups_completed, deliveries_completed, orders_handled,
 complaints_resolved, average_rating, total_orders, weekly_orders,
 monthly_orders, performance_score, recorded_date)
VALUES
(2, 5, 8, 12, 1, 4.50, 12, 12, 40, 85.00, CURDATE()),
(3, 3, 4, 10, 2, 4.70, 10, 10, 35, 88.00, CURDATE()),
(4, 4, 6, 11, 0, 4.20, 11, 11, 30, 80.00, CURDATE());


-- ============================================================
-- END OF SEED DATA
--
-- Default logins (password for ALL: Password@123)
--   Admin : admin@laundry.com
--   Staff : ali@laundry.com / sara@laundry.com / bilal@laundry.com
--   Cust  : ahmed@example.com / fatima@example.com
-- ============================================================