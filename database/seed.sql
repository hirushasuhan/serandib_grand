-- ─────────────────────────────────────────────────────────────
-- Hotel Reservation System — Seed Data
-- ─────────────────────────────────────────────────────────────

USE hotel_reservation_db;

-- ─────────────────────────────────────────────────────────────
-- 1. USERS
-- ─────────────────────────────────────────────────────────────
INSERT INTO users (id, full_name, email, phone, nic_passport, address, password_hash, role, status) VALUES
(1, 'System Admin', 'admin@hotel.test', '0771234567', '199012345678', '123 Galle Road, Colombo 03', '$2y$10$.dlbyhotkTJHHLZNsTJV0uzsW4h6XYKX9FY822PbUXnEfe4aDSkKq', 'admin', 'active'),
(2, 'Operations Manager', 'manager@hotel.test', '0772345678', '198823456789', '45 Beach Road, Mount Lavinia', '$2y$10$hk0R88oA7Jw2l3htPoDmFuZm6B9tOz6rpKYHu1cLpv9UHu8lccBp.', 'manager', 'active'),
(3, 'FrontDesk Receptionist', 'reception@hotel.test', '0773456789', '199534567890', '78 Temple Road, Negombo', '$2y$10$E2Dr/Jtxm6/h7i5hiNxu.eHmgN0yD11WjyY68t3f3Rlz1FGb0UgbG', 'receptionist', 'active'),
(4, 'Night Receptionist', 'reception2@hotel.test', '0774567890', '199645678901', '12 Main Street, Colombo 04', '$2y$10$E2Dr/Jtxm6/h7i5hiNxu.eHmgN0yD11WjyY68t3f3Rlz1FGb0UgbG', 'receptionist', 'active'),
(5, 'Kasun Perera', 'guest@hotel.test', '0715678901', '199256789012', '15 Kandy Road, Kurunegala', '$2y$10$Y4.Ot6fmxs5bobyMQWNTsOkEZTIynIhhTt9naCLe2iDRU2aOESQZe', 'guest', 'active'),
(6, 'Nimali Fernando', 'nimali@gmail.com', '0716789012', '199467890123', '88 Galle Road, Bentota', '$2y$10$Y4.Ot6fmxs5bobyMQWNTsOkEZTIynIhhTt9naCLe2iDRU2aOESQZe', 'guest', 'active'),
(7, 'David Smith', 'david.smith@example.com', '+447911123456', 'N78945612', '42 London Way, UK', '$2y$10$Y4.Ot6fmxs5bobyMQWNTsOkEZTIynIhhTt9naCLe2iDRU2aOESQZe', 'guest', 'active'),
(8, 'Elena Rostova', 'elena.r@example.com', '+79161234567', 'P65432109', '10 Moscow Ave, Russia', '$2y$10$Y4.Ot6fmxs5bobyMQWNTsOkEZTIynIhhTt9naCLe2iDRU2aOESQZe', 'guest', 'active');

-- ─────────────────────────────────────────────────────────────
-- 2. SETTINGS
-- ─────────────────────────────────────────────────────────────
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('hotel_name', 'Serendib Grand Resort & Spa', 'general'),
('hotel_tagline', 'Luxury Oceanfront Haven in Sri Lanka', 'general'),
('hotel_address', '100 Beach Front Drive, Bentota 80500, Sri Lanka', 'general'),
('hotel_phone', '+94 34 227 5000', 'general'),
('hotel_email', 'info@serendibgrand.lk', 'general'),
('tax_rate', '8.00', 'finance'),
('service_charge', '10.00', 'finance'),
('cancellation_window_hours', '24', 'booking'),
('max_active_bookings_per_guest', '3', 'booking'),
('max_stay_nights', '30', 'booking');

-- ─────────────────────────────────────────────────────────────
-- 3. AMENITIES
-- ─────────────────────────────────────────────────────────────
INSERT INTO amenities (id, name, icon, category) VALUES
(1, 'High-Speed Wi-Fi', 'wifi', 'room'),
(2, 'Air Conditioning', 'snowflake', 'room'),
(3, 'Ocean View Balcony', 'sun', 'room'),
(4, 'King Size Bed', 'bed', 'room'),
(5, 'Smart LED TV 55"', 'tv', 'media'),
(6, 'Mini Bar & Refrigerator', 'coffee', 'room'),
(7, 'Private Jacuzzi Bath', 'bath', 'bathroom'),
(8, 'Luxury Toiletries Set', 'gift', 'bathroom'),
(9, '24/7 Room Service', 'bell', 'services'),
(10, 'In-Room Safe', 'lock', 'room'),
(11, 'Tea & Coffee Maker', 'coffee', 'room'),
(12, 'Swimming Pool Access', 'umbrella', 'services');

-- ─────────────────────────────────────────────────────────────
-- 4. ROOM TYPES
-- ─────────────────────────────────────────────────────────────
INSERT INTO room_types (id, name, slug, description, base_price, max_adults, max_children, bed_type, size_sqft, cover_image, is_active) VALUES
(1, 'Standard Single', 'standard-single', 'Cozy and elegant single room designed for business travelers or solo explorers. Features a comfortable single bed, dedicated work desk, and high-speed Wi-Fi.', 12000.00, 1, 0, 'single', 250, 'uploads/rooms/standard_single.jpg', 1),
(2, 'Standard Double', 'standard-double', 'Comfortable double room with garden views. Features a plush queen bed, modern ensuite bathroom, and sleek contemporary amenities.', 18000.00, 2, 1, 'double', 340, 'uploads/rooms/standard_double.jpg', 1),
(3, 'Deluxe Ocean View Double', 'deluxe-double', 'Spacious deluxe room featuring a private balcony overlooking the Indian Ocean. Includes a king bed, deep soaking tub, mini bar, and 55" Smart TV.', 28000.00, 2, 1, 'king', 450, 'uploads/rooms/deluxe_double.jpg', 1),
(4, 'Family Grand Suite', 'family-suite', 'Luxurious two-bedroom suite perfect for family holidays. Includes one king bed and two twin beds, separate living area, ocean view balcony, and twin bathrooms.', 45000.00, 4, 2, 'twin', 750, 'uploads/rooms/family_suite.jpg', 1),
(5, 'Presidential Ocean Suite', 'presidential-suite', 'The pinnacle of luxury with panoramic 180-degree ocean views, private infinity jacuzzi balcony, master king bedroom, dining pavilion, and dedicated butler service.', 85000.00, 2, 2, 'king', 1200, 'uploads/rooms/presidential_suite.jpg', 1);

-- ─────────────────────────────────────────────────────────────
-- 5. ROOM TYPE AMENITY PIVOT
-- ─────────────────────────────────────────────────────────────
INSERT INTO room_type_amenity (room_type_id, amenity_id) VALUES
(1, 1), (1, 2), (1, 5), (1, 10), (1, 11),
(2, 1), (2, 2), (2, 5), (2, 6), (2, 10), (2, 11),
(3, 1), (3, 2), (3, 3), (3, 4), (3, 5), (3, 6), (3, 8), (3, 9), (3, 10), (3, 11), (3, 12),
(4, 1), (4, 2), (4, 3), (4, 5), (4, 6), (4, 8), (4, 9), (4, 10), (4, 11), (4, 12),
(5, 1), (5, 2), (5, 3), (5, 4), (5, 5), (5, 6), (5, 7), (5, 8), (5, 9), (5, 10), (5, 11), (5, 12);

-- ─────────────────────────────────────────────────────────────
-- 6. ROOMS (40 physical rooms on Floors 1-4)
-- ─────────────────────────────────────────────────────────────
INSERT INTO rooms (id, room_number, room_type_id, floor, status, is_active) VALUES
-- Floor 1: Standard Single & Double
(1, '101', 1, 1, 'available', 1),
(2, '102', 1, 1, 'available', 1),
(3, '103', 1, 1, 'available', 1),
(4, '104', 1, 1, 'available', 1),
(5, '105', 2, 1, 'available', 1),
(6, '106', 2, 1, 'available', 1),
(7, '107', 2, 1, 'available', 1),
(8, '108', 2, 1, 'available', 1),
(9, '109', 2, 1, 'available', 1),
(10, '110', 2, 1, 'cleaning', 1),

-- Floor 2: Standard Double & Deluxe Double
(11, '201', 2, 2, 'available', 1),
(12, '202', 2, 2, 'available', 1),
(13, '203', 2, 2, 'available', 1),
(14, '204', 3, 2, 'available', 1),
(15, '205', 3, 2, 'occupied', 1),
(16, '206', 3, 2, 'available', 1),
(17, '207', 3, 2, 'available', 1),
(18, '208', 3, 2, 'available', 1),
(19, '209', 3, 2, 'cleaning', 1),
(20, '210', 3, 2, 'maintenance', 1),

-- Floor 3: Deluxe Double & Family Suite
(21, '301', 3, 3, 'available', 1),
(22, '302', 3, 3, 'available', 1),
(23, '303', 3, 3, 'available', 1),
(24, '304', 3, 3, 'available', 1),
(25, '305', 3, 3, 'occupied', 1),
(26, '306', 4, 3, 'available', 1),
(27, '307', 4, 3, 'available', 1),
(28, '308', 4, 3, 'available', 1),
(29, '309', 4, 3, 'available', 1),
(30, '310', 4, 3, 'available', 1),

-- Floor 4: Suites
(31, '401', 4, 4, 'available', 1),
(32, '402', 4, 4, 'available', 1),
(33, '403', 4, 4, 'available', 1),
(34, '404', 4, 4, 'available', 1),
(35, '405', 5, 4, 'occupied', 1),
(36, '406', 5, 4, 'available', 1),
(37, '407', 5, 4, 'available', 1),
(38, '408', 5, 4, 'available', 1),
(39, '409', 5, 4, 'available', 1),
(40, '410', 5, 4, 'available', 1);

-- ─────────────────────────────────────────────────────────────
-- 7. BOOKINGS
-- ─────────────────────────────────────────────────────────────
INSERT INTO bookings (id, booking_ref, user_id, room_id, check_in, check_out, nights, adults, children, room_rate, subtotal, discount, service_charge, tax_amount, total_amount, status, special_requests, source, created_by, checked_in_at, checked_out_at) VALUES
(1, 'HRS-20260715-1A2B', 5, 15, '2026-07-28', '2026-07-31', 3, 2, 0, 28000.00, 84000.00, 0.00, 8400.00, 6720.00, 99120.00, 'checked_in', 'Quiet room away from elevator, honeymoon setup.', 'online', NULL, '2026-07-28 14:30:00', NULL),
(2, 'HRS-20260720-3C4D', 6, 25, '2026-07-29', '2026-08-02', 4, 2, 1, 28000.00, 112000.00, 10000.00, 10200.00, 8160.00, 120360.00, 'checked_in', 'Late check-in expected around 8 PM.', 'online', NULL, '2026-07-29 19:15:00', NULL),
(3, 'HRS-20260725-5E6F', 7, 35, '2026-07-30', '2026-08-04', 5, 2, 1, 85000.00, 425000.00, 0.00, 42500.00, 34000.00, 501500.00, 'checked_in', 'Airport transfer requested.', 'online', NULL, '2026-07-30 12:10:00', NULL),
(4, 'HRS-20260726-7G8H', 8, 26, '2026-08-05', '2026-08-10', 5, 3, 1, 45000.00, 225000.00, 0.00, 22500.00, 18000.00, 265500.00, 'confirmed', 'Extra baby cot needed.', 'online', NULL, NULL, NULL),
(5, 'HRS-20260727-9I0J', 5, 5, '2026-08-12', '2026-08-15', 3, 2, 0, 18000.00, 54000.00, 0.00, 5400.00, 4320.00, 63720.00, 'pending', 'High floor requested.', 'online', NULL, NULL, NULL),
(6, 'HRS-20260701-11AA', 6, 6, '2026-07-10', '2026-07-14', 4, 2, 0, 18000.00, 72000.00, 0.00, 7200.00, 5760.00, 84960.00, 'checked_out', 'Ground floor near garden.', 'walk_in', 3, '2026-07-10 11:00:00', '2026-07-14 10:30:00');

-- ─────────────────────────────────────────────────────────────
-- 8. INVOICES & PAYMENTS
-- ─────────────────────────────────────────────────────────────
INSERT INTO invoices (id, invoice_no, booking_id, issued_by, issued_at, subtotal, discount, service_rate, service_amt, tax_rate, tax_amount, grand_total) VALUES
(1, 'INV-2026-000101', 6, 3, '2026-07-14 10:30:00', 72000.00, 0.00, 10.00, 7200.00, 8.00, 5760.00, 84960.00);

INSERT INTO invoice_items (id, invoice_id, description, qty, unit_price, line_total) VALUES
(1, 1, 'Standard Double Room - 4 Nights (10-14 Jul 2026)', 4.00, 18000.00, 72000.00);

INSERT INTO payments (id, booking_id, amount, method, type, reference_no, note, received_by, paid_at) VALUES
(1, 6, 84960.00, 'card_at_hotel', 'payment', 'TXN-998877', 'Full payment settling invoice INV-2026-000101', 3, '2026-07-14 10:30:00'),
(2, 1, 50000.00, 'cash', 'payment', 'CASH-001', 'Advance partial deposit at check-in', 3, '2026-07-28 14:35:00'),
(3, 2, 60000.00, 'bank_transfer', 'payment', 'SLB-883311', '50% advance bank deposit', 3, '2026-07-29 10:00:00');

-- ─────────────────────────────────────────────────────────────
-- 9. REVIEWS
-- ─────────────────────────────────────────────────────────────
INSERT INTO reviews (id, booking_id, user_id, rating, comment, status, moderated_by, moderated_at) VALUES
(1, 6, 6, 5, 'Absolute luxury! The staff was incredibly welcoming and the room views were breathtaking. Will definitely come back again!', 'approved', 2, '2026-07-15 09:00:00');

-- ─────────────────────────────────────────────────────────────
-- 10. NOTIFICATIONS
-- ─────────────────────────────────────────────────────────────
INSERT INTO notifications (id, user_id, title, message, link, is_read) VALUES
(1, 5, 'Booking Confirmed!', 'Your booking HRS-20260715-1A2B for Deluxe Ocean View Double is checked in.', 'guest/my-bookings.php', 0),
(2, 6, 'Welcome to Serendib Grand', 'We hope you enjoy your stay in Room 205.', 'guest/dashboard.php', 1);

-- ─────────────────────────────────────────────────────────────
-- 11. AUDIT LOGS
-- ─────────────────────────────────────────────────────────────
INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address, user_agent) VALUES
(1, 'system.initialized', 'settings', 1, '{"message":"Database seeded successfully"}', INET6_ATON('127.0.0.1'), 'System Seed Script'),
(3, 'booking.checkin', 'bookings', 1, '{"ref":"HRS-20260715-1A2B","room":"205"}', INET6_ATON('127.0.0.1'), 'Mozilla/5.0');
