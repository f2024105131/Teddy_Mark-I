-- ============================================================
-- LAUNDRY MANAGEMENT SYSTEM — DATABASE SCHEMA
-- Author: Rohan (Master System Controls)
-- Verified compatible with:
--   • app/Core/Database.php  (Shehroz) → PDO singleton
--   • app/Core/Model.php     (Shehroz) → Active Record base
-- Run order: schema.sql → create_app_user.sql → seed.sql
-- ============================================================

DROP DATABASE IF EXISTS laundry_management;
CREATE DATABASE laundry_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE laundry_management;

-- Ensure consistent behavior across MySQL 5.7 and 8.0
-- (relaxes ONLY_FULL_GROUP_BY, keeps strict mode for data integrity)
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. CUSTOMER & STAFF  (Owner: Shehroz)
-- ============================================================

CREATE TABLE customer (
    customer_id        BIGINT PRIMARY KEY AUTO_INCREMENT,
    full_name          VARCHAR(255) NOT NULL,
    email              VARCHAR(255) NOT NULL UNIQUE,
    phone              VARCHAR(20)  NOT NULL UNIQUE,
    password_hash      VARCHAR(255) NOT NULL,
    address_house      VARCHAR(50)  NOT NULL,
    address_street     VARCHAR(100) NOT NULL,
    address_area       VARCHAR(100) NOT NULL,
    city               VARCHAR(50)  NOT NULL,
    landmark           VARCHAR(100),
    account_status     ENUM('pending','active','suspended','deletion_requested')
                       DEFAULT 'pending',
    email_verified     BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    token_expiry       DATETIME,
    created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME DEFAULT CURRENT_TIMESTAMP
                       ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_status (account_status),
    INDEX idx_customer_email  (email)
) ENGINE=InnoDB;

CREATE TABLE staff (
    staff_id       BIGINT PRIMARY KEY AUTO_INCREMENT,
    full_name      VARCHAR(255) NOT NULL,
    email          VARCHAR(255) NOT NULL UNIQUE,
    phone          VARCHAR(20)  NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('admin','staff') DEFAULT 'staff',
    is_active      BOOLEAN DEFAULT TRUE,
    last_login     DATETIME,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_role (role)
) ENGINE=InnoDB;

-- ============================================================
-- 2. CATALOG  (Owner: Rohan)
-- ============================================================

CREATE TABLE item_category (
    category_id   BIGINT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(50) NOT NULL UNIQUE,
    description   VARCHAR(255),
    is_active     BOOLEAN DEFAULT TRUE,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE item_type (
    item_id     BIGINT PRIMARY KEY AUTO_INCREMENT,
    category_id BIGINT NOT NULL,
    item_name   VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES item_category(category_id),
    UNIQUE KEY uk_category_item (category_id, item_name)
) ENGINE=InnoDB;

CREATE TABLE service (
    service_id     BIGINT PRIMARY KEY AUTO_INCREMENT,
    service_name   VARCHAR(50) NOT NULL UNIQUE,
    description    VARCHAR(255),
    service_type   ENUM('wash_fold','dry_cleaning','ironing',
                        'wash_iron','express','premium',
                        'blanket_cleaning','curtain_cleaning') NOT NULL,
    duration_hours INT DEFAULT 0,
    duration_days  INT DEFAULT 0,
    is_active      BOOLEAN DEFAULT TRUE,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE service_pricing (
    pricing_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    item_id    BIGINT NOT NULL,
    service_id BIGINT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    is_active  BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
               ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id)    REFERENCES item_type(item_id),
    FOREIGN KEY (service_id) REFERENCES service(service_id),
    UNIQUE KEY uk_item_service (item_id, service_id)
) ENGINE=InnoDB;

-- ============================================================
-- 3. SCHEDULING  (Owner: Faizan)
-- ============================================================

CREATE TABLE pickup_slot (
    slot_id      BIGINT PRIMARY KEY AUTO_INCREMENT,
    start_time   TIME NOT NULL,
    end_time     TIME NOT NULL,
    max_capacity INT DEFAULT 10,
    day_of_week  ENUM('monday','tuesday','wednesday','thursday',
                      'friday','saturday','sunday') NOT NULL,
    is_active    BOOLEAN DEFAULT TRUE,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE delivery_slot (
    slot_id      BIGINT PRIMARY KEY AUTO_INCREMENT,
    start_time   TIME NOT NULL,
    end_time     TIME NOT NULL,
    max_capacity INT DEFAULT 10,
    is_active    BOOLEAN DEFAULT TRUE,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE pickup_request (
    pickup_id             BIGINT PRIMARY KEY AUTO_INCREMENT,
    request_number        VARCHAR(50) NOT NULL UNIQUE,
    customer_id           BIGINT NOT NULL,
    slot_id               BIGINT NOT NULL,
    pickup_date           DATE NOT NULL,
    status                ENUM('requested','assigned','picked_up','cancelled')
                          DEFAULT 'requested',
    assigned_address      TEXT NOT NULL,
    special_instructions  TEXT,
    assigned_staff_id     BIGINT,
    is_same_day           BOOLEAN DEFAULT FALSE,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id)       REFERENCES customer(customer_id),
    FOREIGN KEY (slot_id)           REFERENCES pickup_slot(slot_id),
    FOREIGN KEY (assigned_staff_id) REFERENCES staff(staff_id),
    INDEX idx_pickup_date_slot (pickup_date, slot_id),
    INDEX idx_pickup_status    (status)
) ENGINE=InnoDB;

-- ============================================================
-- 4. ORDERS  (Owner: Faizan)
-- ============================================================

CREATE TABLE orders (
    order_id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    customer_id           BIGINT NOT NULL,
    pickup_id             BIGINT,
    order_number          VARCHAR(50) NOT NULL UNIQUE,
    order_date            DATETIME DEFAULT CURRENT_TIMESTAMP,
    order_status          ENUM(
        'pickup_requested','pickup_assigned','picked_up',
        'received_at_laundry','washing','ironing',
        'ready','out_for_delivery','delivered','cancelled'
    ) DEFAULT 'pickup_requested',
    delivery_date         DATE,
    assigned_staff_id     BIGINT,
    washing_started_at    DATETIME,
    washing_completed_at  DATETIME,
    ironing_started_at    DATETIME,
    ironing_completed_at  DATETIME,
    ready_at              DATETIME,
    out_for_delivery_at   DATETIME,
    delivered_at          DATETIME,
    estimated_delivery_date DATE,
    special_instructions  TEXT,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id)       REFERENCES customer(customer_id),
    FOREIGN KEY (pickup_id)         REFERENCES pickup_request(pickup_id),
    FOREIGN KEY (assigned_staff_id) REFERENCES staff(staff_id),
    INDEX idx_orders_status   (order_status),
    INDEX idx_orders_customer (customer_id)
) ENGINE=InnoDB;

CREATE TABLE order_item (
    order_item_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id      BIGINT NOT NULL,
    item_id       BIGINT NOT NULL,
    service_id    BIGINT NOT NULL,
    quantity      INT NOT NULL DEFAULT 1,
    unit_price    DECIMAL(10,2) NOT NULL,
    total_price   DECIMAL(10,2) NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)   REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id)    REFERENCES item_type(item_id),
    FOREIGN KEY (service_id) REFERENCES service(service_id),
    UNIQUE KEY uk_order_item_service (order_id, item_id, service_id)
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    history_id          BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id            BIGINT NOT NULL,
    previous_status     VARCHAR(30),
    new_status          VARCHAR(30) NOT NULL,
    changed_by_staff_id BIGINT,
    changed_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)            REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_staff_id) REFERENCES staff(staff_id)
) ENGINE=InnoDB;

-- ============================================================
-- 5. BILLING & PAYMENTS  (Owner: Shehreen)
-- ============================================================

CREATE TABLE bill (
    bill_id            BIGINT PRIMARY KEY AUTO_INCREMENT,
    bill_number        VARCHAR(50) NOT NULL UNIQUE,
    order_id           BIGINT NOT NULL,
    subtotal           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount         DECIMAL(10,2) DEFAULT 0.00,
    discount_amount    DECIMAL(10,2) DEFAULT 0.00,
    total_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    paid_amount        DECIMAL(10,2) DEFAULT 0.00,
    outstanding_amount DECIMAL(10,2)
                       GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    payment_status     ENUM('unpaid','partially_paid','paid','refunded')
                       DEFAULT 'unpaid',
    generated_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME DEFAULT CURRENT_TIMESTAMP
                       ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
) ENGINE=InnoDB;

CREATE TABLE payment (
    payment_id            BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id              BIGINT NOT NULL,
    bill_id               BIGINT NOT NULL,
    payment_method        ENUM('easypaisa','jazzcash','bank_transfer','cash')
                          NOT NULL,
    amount                DECIMAL(10,2) NOT NULL,
    transaction_id        VARCHAR(100),
    status                ENUM('pending_verification','verified','approved',
                               'rejected','refunded')
                          DEFAULT 'pending_verification',
    rejection_reason      TEXT,
    verified_by_staff_id  BIGINT,
    verified_at           DATETIME,
    approved_by_staff_id  BIGINT,
    approved_at           DATETIME,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)             REFERENCES orders(order_id),
    FOREIGN KEY (bill_id)              REFERENCES bill(bill_id),
    FOREIGN KEY (verified_by_staff_id) REFERENCES staff(staff_id),
    FOREIGN KEY (approved_by_staff_id) REFERENCES staff(staff_id)
) ENGINE=InnoDB;

CREATE TABLE payment_proof (
    proof_id   BIGINT PRIMARY KEY AUTO_INCREMENT,
    payment_id BIGINT NOT NULL,
    file_path  VARCHAR(255) NOT NULL,
    file_name  VARCHAR(100) NOT NULL,
    file_type  VARCHAR(50),
    is_current BOOLEAN DEFAULT TRUE,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payment(payment_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 6. DELIVERY  (Owner: Rohan)
-- ============================================================

CREATE TABLE delivery (
    delivery_id       BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id          BIGINT NOT NULL,
    slot_id           BIGINT NOT NULL,
    assigned_staff_id BIGINT,
    delivery_date     DATE NOT NULL,
    status            ENUM('scheduled','out_for_delivery','delivered',
                           'failed','rescheduled')
                      DEFAULT 'scheduled',
    failure_reason    VARCHAR(255),
    delivery_notes    TEXT,
    delivered_at      DATETIME,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)          REFERENCES orders(order_id),
    FOREIGN KEY (slot_id)           REFERENCES delivery_slot(slot_id),
    FOREIGN KEY (assigned_staff_id) REFERENCES staff(staff_id),
    INDEX idx_delivery_date   (delivery_date),
    INDEX idx_delivery_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 7. COMPLAINTS & REFUNDS  (Owner: Rohan)
-- ============================================================

CREATE TABLE complaint_category (
    category_id   BIGINT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(50) NOT NULL UNIQUE,
    description   VARCHAR(255),
    is_active     BOOLEAN DEFAULT TRUE,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE complaint (
    complaint_id      BIGINT PRIMARY KEY AUTO_INCREMENT,
    complaint_number  VARCHAR(50) NOT NULL UNIQUE,
    customer_id       BIGINT NOT NULL,
    order_id          BIGINT NOT NULL,
    category_id       BIGINT,
    type              ENUM('missing_item','damaged_item','late_delivery',
                           'wrong_billing','poor_cleaning_quality') NOT NULL,
    description       TEXT NOT NULL,
    status            ENUM('open','assigned','under_investigation','resolved',
                           'rejected','escalated','closed')
                      DEFAULT 'open',
    assigned_staff_id BIGINT,
    resolution_notes  TEXT,
    resolved_at       DATETIME,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id)       REFERENCES customer(customer_id),
    FOREIGN KEY (order_id)          REFERENCES orders(order_id),
    FOREIGN KEY (category_id)       REFERENCES complaint_category(category_id),
    FOREIGN KEY (assigned_staff_id) REFERENCES staff(staff_id),
    INDEX idx_complaint_status (status)
) ENGINE=InnoDB;

CREATE TABLE refund (
    refund_id            BIGINT PRIMARY KEY AUTO_INCREMENT,
    payment_id           BIGINT NOT NULL,
    order_id             BIGINT NOT NULL,
    complaint_id         BIGINT NOT NULL,
    amount               DECIMAL(10,2) NOT NULL,
    refund_reason        TEXT NOT NULL,
    status               ENUM('pending','approved','rejected','completed')
                         DEFAULT 'pending',
    approved_by_staff_id BIGINT,
    approved_at          DATETIME,
    completed_at         DATETIME,
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id)           REFERENCES payment(payment_id),
    FOREIGN KEY (order_id)             REFERENCES orders(order_id),
    FOREIGN KEY (complaint_id)         REFERENCES complaint(complaint_id),
    FOREIGN KEY (approved_by_staff_id) REFERENCES staff(staff_id)
) ENGINE=InnoDB;

-- ============================================================
-- 8. RECEIPTS & INVOICES  (Owner: Shehreen)
-- ============================================================

CREATE TABLE receipt (
    receipt_id          BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id            BIGINT NOT NULL UNIQUE,
    receipt_number      VARCHAR(50) NOT NULL UNIQUE,
    printed_by_staff_id BIGINT,
    reprint_count       INT DEFAULT 0,
    generated_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)            REFERENCES orders(order_id),
    FOREIGN KEY (printed_by_staff_id) REFERENCES staff(staff_id)
) ENGINE=InnoDB;

CREATE TABLE invoice (
    invoice_id     BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id       BIGINT NOT NULL UNIQUE,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    generated_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
) ENGINE=InnoDB;

-- ============================================================
-- 9. NOTIFICATIONS  (Owner: Shehreen)
-- ============================================================

CREATE TABLE notification (
    notification_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    customer_id     BIGINT,
    staff_id        BIGINT,
    type            ENUM('account_created','order_created','bill_generated',
                         'status_change','order_delivered','payment_received',
                         'complaint_update','refund_status') NOT NULL,
    channel         ENUM('email','sms','whatsapp','push','in_app')
                    DEFAULT 'email',
    recipient       VARCHAR(255) NOT NULL,
    subject         VARCHAR(255) NOT NULL,
    message_body    TEXT NOT NULL,
    status          ENUM('pending','sent','failed','retry') DEFAULT 'pending',
    sent_at         DATETIME,
    retry_count     INT DEFAULT 0,
    error_message   TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id),
    FOREIGN KEY (staff_id)    REFERENCES staff(staff_id),
    INDEX idx_notification_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 10. STAFF PERFORMANCE  (Owner: Rohan)
-- ============================================================

CREATE TABLE staff_performance (
    performance_id       BIGINT PRIMARY KEY AUTO_INCREMENT,
    staff_id             BIGINT NOT NULL,
    pickups_completed    INT DEFAULT 0,
    deliveries_completed INT DEFAULT 0,
    orders_handled       INT DEFAULT 0,
    complaints_resolved  INT DEFAULT 0,
    average_rating       DECIMAL(3,2),
    total_orders         INT DEFAULT 0,
    weekly_orders        INT DEFAULT 0,
    monthly_orders       INT DEFAULT 0,
    performance_score    DECIMAL(5,2),
    recorded_date        DATE NOT NULL,
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id),
    UNIQUE KEY uk_staff_date (staff_id, recorded_date)
) ENGINE=InnoDB;

-- ============================================================
-- 11. SYSTEM / AUDIT / SECURITY  (Owner: Shehroz + Rohan)
-- ============================================================

CREATE TABLE audit_log (
    audit_id         BIGINT PRIMARY KEY AUTO_INCREMENT,
    module           ENUM('customer','order','payment','complaint',
                          'staff','service','system','pickup','delivery') NOT NULL,
    action           VARCHAR(100) NOT NULL,
    user_id          BIGINT,
    user_type        ENUM('customer','staff','admin','system') NOT NULL,
    old_value        JSON,
    new_value        JSON,
    action_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address       VARCHAR(45),
    user_agent       VARCHAR(255),
    INDEX idx_audit_module (module),
    INDEX idx_audit_time   (action_timestamp)
) ENGINE=InnoDB;

CREATE TABLE system_config (
    config_id           BIGINT PRIMARY KEY AUTO_INCREMENT,
    config_key          VARCHAR(100) NOT NULL UNIQUE,
    config_value        TEXT NOT NULL,
    data_type           ENUM('string','integer','boolean','json','decimal') NOT NULL,
    description         VARCHAR(255),
    updated_by_staff_id BIGINT,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by_staff_id) REFERENCES staff(staff_id)
) ENGINE=InnoDB;

CREATE TABLE password_reset (
    reset_id     BIGINT PRIMARY KEY AUTO_INCREMENT,
    customer_id  BIGINT,
    staff_id     BIGINT,
    token        VARCHAR(255) NOT NULL UNIQUE,
    token_expiry DATETIME NOT NULL,
    used         BOOLEAN DEFAULT FALSE,
    used_at      DATETIME,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id),
    FOREIGN KEY (staff_id)    REFERENCES staff(staff_id)
) ENGINE=InnoDB;

CREATE TABLE account_deletion_request (
    deletion_id           BIGINT PRIMARY KEY AUTO_INCREMENT,
    customer_id           BIGINT NOT NULL,
    request_date          DATETIME DEFAULT CURRENT_TIMESTAMP,
    status                ENUM('pending','approved','rejected') DEFAULT 'pending',
    rejection_reason      TEXT,
    processed_by_staff_id BIGINT,
    processed_at          DATETIME,
    FOREIGN KEY (customer_id)           REFERENCES customer(customer_id),
    FOREIGN KEY (processed_by_staff_id) REFERENCES staff(staff_id)
) ENGINE=InnoDB;

CREATE TABLE login_activity (
    log_id        BIGINT PRIMARY KEY AUTO_INCREMENT,
    customer_id   BIGINT,
    staff_id      BIGINT,
    email_tried   VARCHAR(255),
    ip_address    VARCHAR(45),
    is_successful BOOLEAN NOT NULL,
    attempted_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id)    REFERENCES staff(staff_id)       ON DELETE SET NULL,
    INDEX idx_login_attempt (email_tried, attempted_at)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- END OF SCHEMA
-- ============================================================