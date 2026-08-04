-- ─────────────────────────────────────────────────────────────
-- Hotel Reservation System — Database Schema
-- Database: hotel_reservation_db
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ─────────────────────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS hotel_reservation_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hotel_reservation_db;

-- Drop tables if re-importing (order respects FK constraints)
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS approvals;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS extra_charges;
DROP TABLE IF EXISTS booking_guests;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS room_type_images;
DROP TABLE IF EXISTS room_type_amenity;
DROP TABLE IF EXISTS amenities;
DROP TABLE IF EXISTS room_types;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

-- ─────────────────────────────────────────────────────────────
-- 1. USERS — Guests and all staff roles in one table
-- ─────────────────────────────────────────────────────────────
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120)  NOT NULL,
    email           VARCHAR(160)  NOT NULL,
    phone           VARCHAR(20)   NOT NULL,
    nic_passport    VARCHAR(30)   NULL,
    address         VARCHAR(255)  NULL,
    password_hash   VARCHAR(255)  NOT NULL,
    role            ENUM('guest','receptionist','manager','admin') NOT NULL DEFAULT 'guest',
    status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME      NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 2. PASSWORD RESETS — Single-use password reset tokens
-- ─────────────────────────────────────────────────────────────
CREATE TABLE password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(160) NOT NULL,
    token_hash  VARCHAR(64)  NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_resets_email_token (email, token_hash),
    KEY idx_resets_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 3. LOGIN ATTEMPTS — Account throttling & lockout tracking
-- ─────────────────────────────────────────────────────────────
CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(160)  NOT NULL,
    ip_address   VARBINARY(16) NOT NULL,
    successful   TINYINT(1)    NOT NULL DEFAULT 0,
    attempted_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_email_time (email, attempted_at),
    KEY idx_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 4. ROOM TYPES — Category details, base prices, capacities
-- ─────────────────────────────────────────────────────────────
CREATE TABLE room_types (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(80)    NOT NULL,
    slug              VARCHAR(90)    NOT NULL,
    description       TEXT           NOT NULL,
    base_price        DECIMAL(10,2)  NOT NULL,
    max_adults        TINYINT UNSIGNED NOT NULL DEFAULT 2,
    max_children      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    bed_type          ENUM('single','double','twin','queen','king') NOT NULL,
    size_sqft         SMALLINT UNSIGNED NULL,
    cover_image       VARCHAR(255)   NULL,
    is_active         TINYINT(1)     NOT NULL DEFAULT 1,
    created_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_room_types_slug (slug),
    KEY idx_room_types_active_price (is_active, base_price),
    CONSTRAINT chk_room_types_price CHECK (base_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 5. AMENITIES — Master list of hotel amenities
-- ─────────────────────────────────────────────────────────────
CREATE TABLE amenities (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60)  NOT NULL,
    icon        VARCHAR(40)  NOT NULL DEFAULT 'star',
    category    ENUM('room','bathroom','media','services') NOT NULL DEFAULT 'room',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_amenities_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 6. ROOM TYPE AMENITY — Pivot table for many-to-many relationship
-- ─────────────────────────────────────────────────────────────
CREATE TABLE room_type_amenity (
    room_type_id INT UNSIGNED NOT NULL,
    amenity_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (room_type_id, amenity_id),
    CONSTRAINT fk_rta_room_type FOREIGN KEY (room_type_id)
        REFERENCES room_types(id) ON DELETE CASCADE,
    CONSTRAINT fk_rta_amenity FOREIGN KEY (amenity_id)
        REFERENCES amenities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 7. ROOM TYPE IMAGES — Gallery images for each room type
-- ─────────────────────────────────────────────────────────────
CREATE TABLE room_type_images (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_type_id INT UNSIGNED NOT NULL,
    image_path   VARCHAR(255) NOT NULL,
    caption      VARCHAR(120) NULL,
    sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rti_type (room_type_id),
    CONSTRAINT fk_rti_room_type FOREIGN KEY (room_type_id)
        REFERENCES room_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 8. ROOMS — Individual physical room inventory
-- ─────────────────────────────────────────────────────────────
CREATE TABLE rooms (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_number   VARCHAR(10)   NOT NULL,
    room_type_id  INT UNSIGNED  NOT NULL,
    floor         TINYINT UNSIGNED NOT NULL,
    status        ENUM('available','occupied','cleaning','maintenance')
                  NOT NULL DEFAULT 'available',
    notes         VARCHAR(255)  NULL,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rooms_number (room_number),
    KEY idx_rooms_type_status (room_type_id, status),
    CONSTRAINT fk_rooms_type FOREIGN KEY (room_type_id)
        REFERENCES room_types(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 9. BOOKINGS — Primary reservation table
-- ─────────────────────────────────────────────────────────────
CREATE TABLE bookings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_ref     VARCHAR(24)   NOT NULL,
    user_id         INT UNSIGNED  NOT NULL,
    room_id         INT UNSIGNED  NOT NULL,
    check_in        DATE          NOT NULL,
    check_out       DATE          NOT NULL,
    nights          SMALLINT UNSIGNED NOT NULL,
    adults          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    children        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    room_rate       DECIMAL(10,2) NOT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,
    discount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    service_charge  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount    DECIMAL(10,2) NOT NULL,
    status          ENUM('pending','confirmed','checked_in','checked_out',
                         'cancel_requested','cancelled','rejected','no_show')
                    NOT NULL DEFAULT 'pending',
    special_requests VARCHAR(500) NULL,
    source          ENUM('online','walk_in','phone') NOT NULL DEFAULT 'online',
    created_by      INT UNSIGNED  NULL,
    checked_in_at   DATETIME      NULL,
    checked_out_at  DATETIME      NULL,
    cancelled_at    DATETIME      NULL,
    cancel_reason   VARCHAR(255)  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bookings_ref (booking_ref),
    KEY idx_bookings_room_dates (room_id, check_in, check_out, status),
    KEY idx_bookings_user_status (user_id, status),
    KEY idx_bookings_checkin (check_in),
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_room FOREIGN KEY (room_id)
        REFERENCES rooms(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_creator FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_bookings_dates    CHECK (check_out > check_in),
    CONSTRAINT chk_bookings_totals   CHECK (total_amount >= 0 AND discount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 10. BOOKING GUESTS — Additional guests registered during stay
-- ─────────────────────────────────────────────────────────────
CREATE TABLE booking_guests (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id   INT UNSIGNED NOT NULL,
    full_name    VARCHAR(120) NOT NULL,
    nic_passport VARCHAR(30)  NULL,
    is_primary   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bg_booking (booking_id),
    CONSTRAINT fk_bg_booking FOREIGN KEY (booking_id)
        REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 11. EXTRA CHARGES — Additional stay charges (minibar, laundry, etc.)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE extra_charges (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT UNSIGNED  NOT NULL,
    description VARCHAR(160)  NOT NULL,
    qty         DECIMAL(8,2)  NOT NULL DEFAULT 1,
    unit_price  DECIMAL(10,2) NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    added_by    INT UNSIGNED  NOT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ec_booking (booking_id),
    CONSTRAINT fk_ec_booking FOREIGN KEY (booking_id)
        REFERENCES bookings(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ec_staff FOREIGN KEY (added_by)
        REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 12. INVOICES — Issued billing invoice
-- ─────────────────────────────────────────────────────────────
CREATE TABLE invoices (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_no   VARCHAR(24)   NOT NULL,
    booking_id   INT UNSIGNED  NOT NULL,
    issued_by    INT UNSIGNED  NOT NULL,
    issued_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal     DECIMAL(10,2) NOT NULL,
    discount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    service_rate DECIMAL(5,2)  NOT NULL,
    service_amt  DECIMAL(10,2) NOT NULL,
    tax_rate     DECIMAL(5,2)  NOT NULL,
    tax_amount   DECIMAL(10,2) NOT NULL,
    grand_total  DECIMAL(10,2) NOT NULL,
    UNIQUE KEY uq_invoices_no (invoice_no),
    UNIQUE KEY uq_invoices_booking (booking_id),
    CONSTRAINT fk_invoices_booking FOREIGN KEY (booking_id)
        REFERENCES bookings(id) ON DELETE RESTRICT,
    CONSTRAINT fk_invoices_staff FOREIGN KEY (issued_by)
        REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 13. INVOICE ITEMS — Snapshot line items for an invoice
-- ─────────────────────────────────────────────────────────────
CREATE TABLE invoice_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id  INT UNSIGNED  NOT NULL,
    description VARCHAR(160)  NOT NULL,
    qty         DECIMAL(8,2)  NOT NULL DEFAULT 1,
    unit_price  DECIMAL(10,2) NOT NULL,
    line_total  DECIMAL(10,2) NOT NULL,
    KEY idx_invoice_items_invoice (invoice_id),
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id)
        REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 14. PAYMENTS — Payment and refund records
-- ─────────────────────────────────────────────────────────────
CREATE TABLE payments (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id   INT UNSIGNED  NOT NULL,
    amount       DECIMAL(10,2) NOT NULL,
    method       ENUM('cash','card_at_hotel','bank_transfer') NOT NULL,
    type         ENUM('payment','refund') NOT NULL DEFAULT 'payment',
    reference_no VARCHAR(60)   NULL,
    note         VARCHAR(255)  NULL,
    received_by  INT UNSIGNED  NOT NULL,
    paid_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_payments_booking (booking_id),
    KEY idx_payments_paid_at (paid_at),
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id)
        REFERENCES bookings(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_staff FOREIGN KEY (received_by)
        REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 15. REVIEWS — Guest reviews & ratings
-- ─────────────────────────────────────────────────────────────
CREATE TABLE reviews (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id   INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    rating       TINYINT UNSIGNED NOT NULL,
    comment      TEXT         NOT NULL,
    status       ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
    moderated_by INT UNSIGNED NULL,
    moderated_at DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reviews_booking (booking_id),
    KEY idx_reviews_status_rating (status, rating),
    CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id)
        REFERENCES bookings(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_moderator FOREIGN KEY (moderated_by)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_reviews_rating CHECK (rating >= 1 AND rating <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 16. APPROVALS — Manager approval queue (cancellation, refunds, discounts)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE approvals (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id   INT UNSIGNED NOT NULL,
    type         ENUM('cancellation','discount','refund_override') NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reason       VARCHAR(255) NOT NULL,
    actioned_by  INT UNSIGNED NULL,
    actioned_at  DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_approvals_status (status),
    CONSTRAINT fk_approvals_booking FOREIGN KEY (booking_id)
        REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_approvals_requester FOREIGN KEY (requested_by)
        REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_approvals_actioner FOREIGN KEY (actioned_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 17. NOTIFICATIONS — User in-app notifications
-- ─────────────────────────────────────────────────────────────
CREATE TABLE notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(120) NOT NULL,
    message    VARCHAR(255) NOT NULL,
    link       VARCHAR(255) NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user_read (user_id, is_read),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 18. CONTACT MESSAGES — Public site contact form entries
-- ─────────────────────────────────────────────────────────────
CREATE TABLE contact_messages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,
    email      VARCHAR(160) NOT NULL,
    subject    VARCHAR(160) NOT NULL,
    message    TEXT         NOT NULL,
    status     ENUM('unread','read','replied') NOT NULL DEFAULT 'unread',
    ip_address VARBINARY(16) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cm_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 19. SETTINGS — Key-value hotel system configuration
-- ─────────────────────────────────────────────────────────────
CREATE TABLE settings (
    setting_key   VARCHAR(60)  NOT NULL PRIMARY KEY,
    setting_value TEXT         NOT NULL,
    setting_group VARCHAR(40)  NOT NULL DEFAULT 'general',
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 20. AUDIT LOGS — Activity log for security and compliance
-- ─────────────────────────────────────────────────────────────
CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NULL,
    action      VARCHAR(60)   NOT NULL,
    entity      VARCHAR(40)   NULL,
    entity_id   INT UNSIGNED  NULL,
    details     VARCHAR(500)  NULL,
    ip_address  VARBINARY(16) NULL,
    user_agent  VARCHAR(255)  NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_user (user_id),
    KEY idx_audit_action (action),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
