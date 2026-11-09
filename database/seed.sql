-- Vývojová data pro čistou databázi. Nespouštěj opakovaně na produkci.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

INSERT INTO users (id, username, email, password_hash, name, role, is_active, auth_version, created_at, updated_at, last_login_at) VALUES
(1, 'admin', 'admin@example.test', '$2y$10$IyfzosBo9athhr3eJaSUgO5CN9NwmcLV8jNae08YdGvn/5sSTDCI.', 'Administrátor', 'admin', 1, 1, '2026-10-01 08:00:00', '2026-10-01 08:00:00', NULL),
(2, 'moderator', 'moderator@example.test', '$2y$10$Naoe5k9HEXhtBg02DkI5dOL22JmB8kKr5RVpEYGO6H1g/yY4Spyzu', 'Moderátor', 'moderator', 1, 1, '2026-10-01 08:05:00', '2026-10-01 08:05:00', NULL),
(3, 'user', 'user@example.test', '$2y$10$1eJAQuVQMPTlILHptHXAbOnMEsYOvDgBbQ.NPeSSXydOJh2okaLfu', 'Jan Novák', 'user', 1, 1, '2026-10-01 08:10:00', '2026-10-01 08:10:00', NULL),
(4, 'jana', 'jana@example.test', '$2y$10$DhPtqKA1kMcoOIHZQbt6HeHmfuiHSrIL5h7GIl58gW8Xl3eh/2jfy', 'Jana Svobodová', 'user', 1, 1, '2026-10-01 08:15:00', '2026-10-01 08:15:00', NULL),
(5, 'neaktivni', 'neaktivni@example.test', '$2y$10$bwdR9uNLMg.Ps7l6Hws9f.1G/zZJVYirWk3iXJ95FrD8cMpAf2FM6', 'Petr Neaktivní', 'user', 0, 1, '2026-10-01 08:20:00', '2026-10-01 08:20:00', NULL);

INSERT INTO venues (id, name, street, city, postal_code, description, photo_path, status, proposed_by, reviewed_by, reviewed_at, rejection_reason, created_at, updated_at) VALUES
(1, 'Divadlo Husa na provázku', 'Zelný trh 9', 'Brno', '602 00', 'Divadlo v centru Brna s komorním sálem.', NULL, 'approved', 1, 2, '2026-10-02 09:00:00', NULL, '2026-10-01 09:00:00', '2026-10-02 09:00:00'),
(2, 'Kulturní centrum Semilasso', 'Palackého třída 126', 'Brno', '612 00', 'Kulturní centrum s více sály a parkováním.', NULL, 'approved', 2, 2, '2026-10-02 09:05:00', NULL, '2026-10-01 09:05:00', '2026-10-02 09:05:00'),
(3, 'FIT VUT', 'Božetěchova 2', 'Brno', '612 66', 'Fakulta informačních technologií VUT v Brně.', NULL, 'approved', 1, 2, '2026-10-02 09:10:00', NULL, '2026-10-01 09:10:00', '2026-10-02 09:10:00'),
(4, 'Klub Fléda', 'Štefánikova 24', 'Brno', '602 00', 'Hudební klub poblíž centra.', NULL, 'pending', 3, NULL, NULL, NULL, '2026-11-01 10:00:00', '2026-11-01 10:00:00');

INSERT INTO categories (id, name, slug, parent_id, description, status, proposed_by, reviewed_by, reviewed_at, rejection_reason, created_at, updated_at) VALUES
(1, 'Kultura', 'kultura', NULL, 'Kulturní akce všeho druhu.', 'approved', 1, 2, '2026-10-02 10:00:00', NULL, '2026-10-01 09:30:00', '2026-10-02 10:00:00'),
(2, 'Hudba', 'hudba', 1, 'Hudební akce.', 'approved', 1, 2, '2026-10-02 10:05:00', NULL, '2026-10-01 09:31:00', '2026-10-02 10:05:00'),
(3, 'Koncert', 'koncert', 2, 'Koncerty a vystoupení.', 'approved', 1, 2, '2026-10-02 10:10:00', NULL, '2026-10-01 09:32:00', '2026-10-02 10:10:00'),
(4, 'Divadlo', 'divadlo', 1, 'Divadelní představení.', 'approved', 1, 2, '2026-10-02 10:15:00', NULL, '2026-10-01 09:33:00', '2026-10-02 10:15:00'),
(5, 'Vzdělání', 'vzdelani', NULL, 'Vzdělávací akce.', 'approved', 1, 2, '2026-10-02 10:20:00', NULL, '2026-10-01 09:34:00', '2026-10-02 10:20:00'),
(6, 'Seminář', 'seminar', 5, 'Odborné semináře.', 'approved', 1, 2, '2026-10-02 10:25:00', NULL, '2026-10-01 09:35:00', '2026-10-02 10:25:00'),
(7, 'Sport', 'sport', NULL, 'Sportovní akce.', 'approved', 1, 2, '2026-10-02 10:30:00', NULL, '2026-10-01 09:36:00', '2026-10-02 10:30:00'),
(8, 'Společenské setkání', 'spolecenske-setkani', NULL, 'Setkání a neformální akce.', 'approved', 1, 2, '2026-10-02 10:35:00', NULL, '2026-10-01 09:37:00', '2026-10-02 10:35:00'),
(9, 'Workshop', 'workshop', 6, 'Praktické workshopy.', 'pending', 3, NULL, NULL, NULL, '2026-11-01 11:00:00', '2026-11-01 11:00:00');

INSERT INTO events (id, slug, title, description, starts_at, ends_at, venue_id, category_id, capacity, cover_path, payment_note, status, manager_id, submitted_at, reviewed_by, reviewed_at, rejection_reason, cancelled_reason, created_at, updated_at) VALUES
(1, 'koncert-komorni-hudby', 'Koncert komorní hudby', 'Večer komorní hudby s ukázkami českých a evropských skladatelů.', '2026-10-17 18:00:00', '2026-10-17 21:00:00', 1, 3, NULL, NULL, NULL, 'approved', 3, '2026-09-20 10:00:00', 2, '2026-09-22 09:00:00', NULL, NULL, '2026-09-19 10:00:00', '2026-09-22 09:00:00'),
(2, 'seminar-moderni-php', 'Seminář: Moderní PHP', 'Praktický seminář o moderním PHP, PDO, bezpečnosti a testování.', '2026-11-20 18:00:00', '2026-11-20 21:00:00', 3, 6, 50, NULL, 'Platba převodem na účet 123456789/0100, variabilní symbol z registrace.', 'approved', 3, '2026-10-25 12:00:00', 2, '2026-10-27 09:00:00', NULL, NULL, '2026-10-24 12:00:00', '2026-10-27 09:00:00'),
(3, 'workshop-testovani', 'Workshop: Testování aplikací', 'Workshop zaměřený na unit a integrační testy v PHP.', '2026-12-05 17:00:00', '2026-12-05 20:00:00', 3, 6, 30, NULL, NULL, 'draft', 3, NULL, NULL, NULL, NULL, NULL, '2026-11-02 09:00:00', '2026-11-02 09:00:00'),
(4, 'prednaska-o-bezpecnosti', 'Přednáška o bezpečnosti', 'Přednáška o bezpečnosti webových aplikací a ochraně dat.', '2026-12-12 16:00:00', '2026-12-12 18:00:00', 3, 6, 100, NULL, NULL, 'pending', 4, '2026-11-05 10:00:00', NULL, NULL, NULL, NULL, '2026-11-04 10:00:00', '2026-11-05 10:00:00'),
(5, 'letni-kino', 'Letní kino', 'Promítání filmu pod širým nebem.', '2026-11-28 17:00:00', '2026-11-28 19:00:00', 2, 3, 20, NULL, NULL, 'cancelled', 3, '2026-10-20 10:00:00', 2, '2026-10-22 09:00:00', NULL, 'Málo prodaných vstupenek.', '2026-10-19 10:00:00', '2026-11-02 10:00:00');

INSERT INTO ticket_types (id, event_id, name, price, capacity, created_at, updated_at) VALUES
(1, 1, 'Vstup zdarma', 0.00, NULL, '2026-09-19 10:00:00', '2026-09-19 10:00:00'),
(2, 2, 'Základní', 250.00, 50, '2026-10-24 12:00:00', '2026-10-24 12:00:00'),
(3, 2, 'Student', 150.00, 20, '2026-10-24 12:00:00', '2026-10-24 12:00:00'),
(4, 3, 'Vstupenka', 100.00, 30, '2026-11-02 09:00:00', '2026-11-02 09:00:00'),
(5, 4, 'Vstupenka', 0.00, NULL, '2026-11-04 10:00:00', '2026-11-04 10:00:00'),
(6, 5, 'Vstupenka', 120.00, 20, '2026-10-19 10:00:00', '2026-10-19 10:00:00');

INSERT INTO registrations (id, event_id, user_id, ticket_type_id, status, price, variable_symbol, payment_claimed_at, registered_at, decided_by, decided_at, cancelled_at) VALUES
(1, 1, 3, 1, 'confirmed', 0.00, NULL, NULL, '2026-10-01 09:00:00', NULL, NULL, NULL),
(2, 1, 4, 1, 'confirmed', 0.00, NULL, NULL, '2026-10-02 09:00:00', NULL, NULL, NULL),
(3, 2, 3, 2, 'pending', 250.00, '0000000001', NULL, '2026-11-01 10:00:00', NULL, NULL, NULL),
(4, 2, 4, 3, 'pending', 150.00, '0000000002', NULL, '2026-11-02 10:00:00', NULL, NULL, NULL),
(5, 5, 3, 6, 'cancelled', 120.00, NULL, NULL, '2026-10-25 10:00:00', NULL, NULL, '2026-11-02 10:00:00');

INSERT INTO comments (id, event_id, user_id, body, status, moderated_by, moderated_at, hidden_reason, created_at, updated_at) VALUES
(1, 1, 3, 'Skvělá akce, dobře zorganizované.', 'visible', NULL, NULL, NULL, '2026-10-18 08:00:00', '2026-10-18 08:00:00'),
(2, 1, 4, 'Líbilo se mi to, příště přijdu znovu.', 'visible', NULL, NULL, NULL, '2026-10-18 09:00:00', '2026-10-18 09:00:00'),
(3, 1, 5, 'Tady byl neukázněný komentář.', 'hidden', 2, '2026-10-19 10:00:00', 'Obsahuje nevhodný obsah.', '2026-10-18 10:00:00', '2026-10-19 10:00:00');

INSERT INTO ratings (id, event_id, user_id, value, created_at, updated_at) VALUES
(1, 1, 3, 5, '2026-10-18 08:05:00', '2026-10-18 08:05:00'),
(2, 1, 4, 4, '2026-10-18 09:05:00', '2026-10-18 09:05:00');
