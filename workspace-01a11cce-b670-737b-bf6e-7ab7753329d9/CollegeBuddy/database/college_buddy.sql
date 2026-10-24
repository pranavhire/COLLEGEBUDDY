-- ============================================================================
--  COLLEGE BUDDY - database setup file
--  File: database/college_buddy.sql
--
--  HOW TO IMPORT (phpMyAdmin):
--    1. Start Apache and MySQL in the XAMPP Control Panel.
--    2. Open http://localhost/phpmyadmin
--    3. Click "Import" (top menu), choose this file, then click "Go".
--  This file creates the database `college_buddy`, all tables, the keys and
--  foreign keys, and realistic sample data. Running it again resets the data.
--
--  DEMO ACCOUNTS
--    Student : student@collegebuddy.com  /  Student@123
--    Admin   : admin@collegebuddy.com    /  Admin@123
--  Passwords are stored with password_hash() (bcrypt), never as plain text.
--  The other sample students also use the password Student@123.
-- ============================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS college_buddy
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE college_buddy;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS requests;
DROP TABLE IF EXISTS resources;
DROP TABLE IF EXISTS listings;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. USERS  (students and admins; role decides what a person can access)
-- ============================================================================
CREATE TABLE users (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,          -- primary key
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL,                         -- login id, must be unique
    password    VARCHAR(255) NOT NULL,                         -- output of password_hash()
    branch      VARCHAR(50)  NOT NULL,                         -- CSE, AIML, ECE, Mechanical, Civil, Other
    year        TINYINT UNSIGNED NOT NULL,                     -- 1 to 4
    semester    TINYINT UNSIGNED NOT NULL,                     -- 1 to 8
    role        ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================================================
-- 2. LISTINGS  (student-to-student marketplace; no online payments)
-- ============================================================================
CREATE TABLE listings (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,                     -- seller (foreign key to users)
    title           VARCHAR(150) NOT NULL,
    category        VARCHAR(60)  NOT NULL,
    description     TEXT NOT NULL,
    price           DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    item_condition  ENUM('New', 'Like New', 'Good', 'Used') NOT NULL DEFAULT 'Good',
    location        VARCHAR(120) NOT NULL,
    image           VARCHAR(255) NULL DEFAULT NULL,            -- path such as uploads/listings/x.jpg
    status          ENUM('Available', 'Sold') NOT NULL DEFAULT 'Available',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_listings_user (user_id),
    KEY idx_listings_category (category),
    KEY idx_listings_status_created (status, created_at),
    CONSTRAINT fk_listings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================================================
-- 3. RESOURCES  (free study resources: notes, papers, practicals...)
-- ============================================================================
CREATE TABLE resources (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,                     -- uploader (foreign key to users)
    title           VARCHAR(150) NOT NULL,
    description     TEXT NULL,
    subject         VARCHAR(100) NOT NULL,
    branch          VARCHAR(50)  NOT NULL,
    year            TINYINT UNSIGNED NOT NULL,
    semester        TINYINT UNSIGNED NOT NULL,
    resource_type   VARCHAR(40)  NOT NULL,
    file_name       VARCHAR(255) NOT NULL,                     -- original file name shown to users
    stored_name     VARCHAR(255) NOT NULL,                     -- random name on disk in uploads/resources/
    file_size       INT UNSIGNED NOT NULL DEFAULT 0,           -- bytes
    file_ext        VARCHAR(10)  NOT NULL,
    download_count  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_resources_stored (stored_name),
    KEY idx_resources_filter (branch, year, semester),
    KEY idx_resources_subject (subject),
    KEY idx_resources_type (resource_type),
    KEY idx_resources_user (user_id),
    CONSTRAINT fk_resources_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================================================
-- 4. REQUESTS  (request board: "Need DSA textbook", ...)
-- ============================================================================
CREATE TABLE requests (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,                         -- requester (foreign key to users)
    title       VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    category    VARCHAR(60)  NOT NULL,
    status      ENUM('Open', 'Closed') NOT NULL DEFAULT 'Open',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_requests_status_created (status, created_at),
    KEY idx_requests_user (user_id),
    CONSTRAINT fk_requests_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================================================
-- 5. MESSAGES  (contact seller / respond to a request, without showing e-mail)
-- ============================================================================
CREATE TABLE messages (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_id     INT UNSIGNED NOT NULL,                       -- foreign key to users
    receiver_id   INT UNSIGNED NOT NULL,                       -- foreign key to users
    context_type  ENUM('listing', 'request') NOT NULL,         -- which kind of item the message is about
    context_id    INT UNSIGNED NOT NULL,                       -- id of that listing or request
    body          TEXT NOT NULL,
    is_read       TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_receiver (receiver_id, is_read),
    KEY idx_messages_sender (sender_id),
    KEY idx_messages_context (context_type, context_id),
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================================================
-- 6. REPORTS  (moderation: students report listings, resources or requests)
-- ============================================================================
CREATE TABLE reports (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,                        -- the student who reported (foreign key)
    item_type    ENUM('listing', 'resource', 'request') NOT NULL,
    item_id      INT UNSIGNED NOT NULL,                        -- id in the matching table (no FK: it can point to 3 tables)
    reason       VARCHAR(60) NOT NULL,
    description  VARCHAR(500) NULL,
    status       ENUM('Pending', 'Resolved', 'Dismissed') NOT NULL DEFAULT 'Pending',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reports_item (item_type, item_id),
    KEY idx_reports_status (status),
    CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================================================
-- SAMPLE DATA
-- ============================================================================

-- Users. Hash for Student@123 and hash for Admin@123 (both made with password_hash()).
INSERT INTO users (id, full_name, email, password, branch, year, semester, role, created_at) VALUES
(1,  'Site Administrator', 'admin@collegebuddy.com',       '$2y$10$fAwYF3jeQn.5JYSNV6VVFu6eSvkwWZ27AX/tOQpJXtQPf4/c9ul2i', 'CSE',        4, 8, 'admin',   NOW() - INTERVAL 90 DAY),
(2,  'Aarav Sharma',       'student@collegebuddy.com',     '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'CSE',        2, 3, 'student', NOW() - INTERVAL 60 DAY),
(3,  'Priya Patil',        'priya.patil@example.com',      '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'AIML',       2, 4, 'student', NOW() - INTERVAL 58 DAY),
(4,  'Rohan Deshmukh',     'rohan.deshmukh@example.com',   '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'ECE',        3, 6, 'student', NOW() - INTERVAL 55 DAY),
(5,  'Sneha Kulkarni',     'sneha.kulkarni@example.com',   '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'CSE',        1, 2, 'student', NOW() - INTERVAL 50 DAY),
(6,  'Kunal Joshi',        'kunal.joshi@example.com',      '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'Mechanical', 2, 3, 'student', NOW() - INTERVAL 48 DAY),
(7,  'Ananya Iyer',        'ananya.iyer@example.com',      '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'CSE',        3, 5, 'student', NOW() - INTERVAL 45 DAY),
(8,  'Vikram Singh',       'vikram.singh@example.com',     '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'Civil',      1, 1, 'student', NOW() - INTERVAL 42 DAY),
(9,  'Neha Gupta',         'neha.gupta@example.com',       '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'AIML',       3, 5, 'student', NOW() - INTERVAL 40 DAY),
(10, 'Aditya Pawar',       'aditya.pawar@example.com',     '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'CSE',        4, 7, 'student', NOW() - INTERVAL 38 DAY),
(11, 'Meera Nair',         'meera.nair@example.com',       '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'ECE',        2, 3, 'student', NOW() - INTERVAL 35 DAY),
(12, 'Rahul More',         'rahul.more@example.com',       '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'Mechanical', 4, 7, 'student', NOW() - INTERVAL 33 DAY),
(13, 'Pooja Jadhav',       'pooja.jadhav@example.com',     '$2y$10$u5lUZKvTIb8XqkcP.oBHGuRNI5Ed0Y7El7zhKUYjp2pN.xpuc.0Vm', 'Other',      1, 2, 'student', NOW() - INTERVAL 30 DAY);

-- Marketplace listings (images in images/samples/ are included with the project)
INSERT INTO listings (id, user_id, title, category, description, price, item_condition, location, image, status, created_at) VALUES
(1,  3,  'Higher Engineering Mathematics (B.S. Grewal)', 'Textbooks', 'Popular first-year maths textbook. Only light highlighting in Chapters 1 to 6. Covers matrices, calculus and differential equations.', 450.00, 'Good', 'Boys Hostel Gate', 'images/samples/textbook.svg', 'Available', NOW() - INTERVAL 2 DAY),
(2,  4,  'Casio fx-991ES Scientific Calculator', 'Calculators', 'Works perfectly and comes with its original cover. Used for two semesters of exams. Battery replaced last month.', 650.00, 'Like New', 'Engineering Block B', 'images/samples/calculator.svg', 'Available', NOW() - INTERVAL 1 DAY),
(3,  6,  'Engineering Drawing Instrument Set', 'Drawing Instruments', 'Complete set: compass with pencil and pen attachments, divider, two set squares, protractor and scale. Box included.', 350.00, 'Good', 'Mechanical Dept.', 'images/samples/drawing-set.svg', 'Available', NOW() - INTERVAL 4 DAY),
(4,  6,  'A2 Drafter Board with T-Square', 'Drawing Instruments', 'Wooden drawing board with a T-square and clamps. Good for first-year drawing sheets. Pickup from the Mechanical department.', 800.00, 'Used', 'Mechanical Dept.', 'images/samples/drafter.svg', 'Available', NOW() - INTERVAL 6 DAY),
(5,  7,  'White Lab Coat (Size M)', 'Lab Equipment', 'Clean cotton-blend lab coat, worn for one semester in the Physics and Chemistry labs. Machine washed.', 250.00, 'Like New', 'Girls Hostel Reception', 'images/samples/lab-coat.svg', 'Available', NOW() - INTERVAL 5 DAY),
(6,  5,  'Data Structures Using C (Reema Thareja)', 'Textbooks', 'Covers arrays, linked lists, stacks, queues, trees and graphs with solved examples. A few pages have pencil notes.', 300.00, 'Good', 'Central Library Gate', 'images/samples/textbook.svg', 'Available', NOW() - INTERVAL 3 DAY),
(7,  9,  'Laptop Backpack (30 L)', 'Bags', 'Water-resistant backpack with a padded laptop sleeve. Zips work smoothly. Small scuff on the bottom corner.', 400.00, 'Used', 'Main Gate', 'images/samples/backpack.svg', 'Available', NOW() - INTERVAL 8 DAY),
(8,  8,  'Physics Lab Practical File (Semester 1)', 'Practical Files', 'Complete practical file with neat circuit diagrams and graphs. All experiments are signed by the lab in-charge.', 150.00, 'Good', 'Civil Block', 'images/samples/notebook.svg', 'Available', NOW() - INTERVAL 3 DAY),
(9,  2,  'Handwritten Engineering Physics Notes (Units 1-4)', 'Notes & Books', 'Handwritten notes with diagrams for Engineering Physics Units 1 to 4. Neat and easy to revise before exams.', 100.00, 'Good', 'Hostel A', 'images/samples/notebook.svg', 'Available', NOW() - INTERVAL 9 DAY),
(10, 10, 'Arduino Uno R3 Starter Kit', 'Electronics Kits', 'Arduino Uno with breadboard, jumper wires, LEDs and sensors. Tested and working. Good for mini projects.', 1200.00, 'Good', 'ECE Lab', 'images/samples/electronics.svg', 'Available', NOW() - INTERVAL 11 DAY),
(11, 13, 'Chemistry Lab Safety Goggles', 'Lab Equipment', 'Anti-fog safety goggles, unused. Bought for the lab but not needed after all.', 80.00, 'New', 'Admin Block', 'images/samples/goggles.svg', 'Sold', NOW() - INTERVAL 12 DAY),
(12, 12, 'Engineering Graph Sheets (A3, Pack of 20)', 'Drawing Instruments', 'Unused graph sheets for practicals and drawings. Sold in a sealed pack.', 120.00, 'New', 'Mechanical Dept.', 'images/samples/notebook.svg', 'Sold', NOW() - INTERVAL 15 DAY),
(13, 11, 'Digital Design by M. Morris Mano', 'Textbooks', 'Good condition. Some highlighting in Chapters 2 to 4, but all pages are intact. Useful for Digital Electronics.', 500.00, 'Good', 'ECE Block', 'images/samples/textbook.svg', 'Available', NOW() - INTERVAL 20 DAY);

-- Study resources (free). The matching PDF files are in uploads/resources/.
INSERT INTO resources (id, user_id, title, description, subject, branch, year, semester, resource_type, file_name, stored_name, file_size, file_ext, download_count, created_at) VALUES
(1,  7,  'Data Structures Notes - Units 1 to 3', 'Arrays, stacks, queues and linked lists with diagrams and worked examples.', 'Data Structures', 'CSE', 2, 3, 'Notes', 'Data_Structures_Notes_Units_1-3.pdf', 'sample-data-structures-notes.pdf', 1721, 'pdf', 48, NOW() - INTERVAL 10 DAY),
(2,  4,  'DBMS Question Bank - Semester 4', 'Important questions on the ER model, normalization, SQL and transactions.', 'Database Management Systems', 'CSE', 2, 4, 'Question Bank', 'DBMS_Question_Bank_Sem4.pdf', 'sample-dbms-question-bank.pdf', 1603, 'pdf', 73, NOW() - INTERVAL 7 DAY),
(3,  5,  'Web Technology Previous Year Paper (2024)', 'Last year paper with hints for the PHP, MySQL and Bootstrap questions.', 'Web Technology', 'CSE', 2, 4, 'Previous Year Paper', 'WT_PYQ_2024.pdf', 'sample-web-tech-pyq.pdf', 1605, 'pdf', 112, NOW() - INTERVAL 5 DAY),
(4,  8,  'Engineering Mathematics I - Notes', 'Matrices, differential and integral calculus, with a formula sheet.', 'Engineering Mathematics I', 'Civil', 1, 1, 'Notes', 'EM1_Notes.pdf', 'sample-engg-maths-notes.pdf', 1441, 'pdf', 39, NOW() - INTERVAL 14 DAY),
(5,  2,  'Microprocessor 8085 - Practical Manual', 'Experiments with 8085 assembly programs, flowcharts and viva questions.', 'Microprocessors', 'ECE', 2, 4, 'Practical', 'Microprocessor_Practical_Manual.pdf', 'sample-microprocessor-practical.pdf', 1389, 'pdf', 27, NOW() - INTERVAL 4 DAY),
(6,  9,  'Machine Learning Notes - Supervised Learning', 'Regression, classification, gradient descent and evaluation metrics.', 'Machine Learning', 'AIML', 3, 5, 'Notes', 'ML_Supervised_Learning_Notes.pdf', 'sample-ml-notes.pdf', 1403, 'pdf', 61, NOW() - INTERVAL 6 DAY),
(7,  10, 'Operating Systems - Assignment Set 1', 'Process scheduling, deadlocks and memory management problems.', 'Operating Systems', 'CSE', 2, 4, 'Assignment', 'OS_Assignment_Set1.pdf', 'sample-os-assignment.pdf', 1321, 'pdf', 22, NOW() - INTERVAL 3 DAY),
(8,  12, 'Thermodynamics - Study Material Units 1 to 3', 'Basic concepts and the first and second laws of thermodynamics, with examples.', 'Thermodynamics', 'Mechanical', 2, 3, 'Study Material', 'Thermodynamics_Units_1-3.pdf', 'sample-thermo-study-material.pdf', 1396, 'pdf', 35, NOW() - INTERVAL 9 DAY),
(9,  11, 'Digital Electronics - Previous Year Paper (Winter 2023)', 'Number systems, K-maps, adders and flip-flop questions.', 'Digital Electronics', 'ECE', 2, 3, 'Previous Year Paper', 'DE_PYQ_Winter2023.pdf', 'sample-digital-electronics-pyq.pdf', 1392, 'pdf', 58, NOW() - INTERVAL 13 DAY),
(10, 6,  'Engineering Drawing - Practical Sheets', 'Sheet-wise drawing practice: projections, sections and isometric views.', 'Engineering Drawing', 'Mechanical', 1, 1, 'Practical', 'Engg_Drawing_Practical_Sheets.pdf', 'sample-engg-drawing-practical.pdf', 1283, 'pdf', 17, NOW() - INTERVAL 16 DAY),
(11, 8,  'Structural Analysis - Question Bank', 'Module-wise questions on beams, deflection and indeterminate structures.', 'Structural Analysis', 'Civil', 2, 4, 'Question Bank', 'Structural_Analysis_QB.pdf', 'sample-structural-qb.pdf', 1474, 'pdf', 19, NOW() - INTERVAL 18 DAY),
(12, 4,  'Computer Networks - Notes Units 1 and 2', 'OSI and TCP/IP models, the data link layer and error detection.', 'Computer Networks', 'CSE', 3, 5, 'Notes', 'CN_Notes_Units_1-2.pdf', 'sample-cn-notes.pdf', 1341, 'pdf', 44, NOW() - INTERVAL 2 DAY);

-- Request board
INSERT INTO requests (id, user_id, title, description, category, status, created_at) VALUES
(1, 5,  'Need DSA textbook', 'Looking for "Data Structures Using C" by Reema Thareja or any good DSA book. Happy to exchange notes or pay in cash on campus.', 'Textbooks & Books', 'Open', NOW() - INTERVAL 2 DAY),
(2, 2,  'Looking for Web Technology notes', 'Need handwritten notes for the PHP, MySQL and JavaScript units. I can share my Data Structures notes in return.', 'Notes & Study Material', 'Open', NOW() - INTERVAL 3 DAY),
(3, 7,  'Need previous year question paper', 'Looking for the last three years of Operating Systems question papers, in any university format.', 'Previous Year Papers', 'Open', NOW() - INTERVAL 1 DAY),
(4, 4,  'Need calculator for tomorrow', 'Need a scientific calculator for the Engineering Mathematics exam tomorrow. Will return it the same evening.', 'Equipment & Instruments', 'Open', NOW() - INTERVAL 6 HOUR),
(5, 9,  'Looking for someone who can share CN notes', 'Computer Networks notes from Unit 3 onwards. I can share my Machine Learning notes in return.', 'Notes & Study Material', 'Open', NOW() - INTERVAL 4 DAY),
(6, 6,  'Need A2 drawing sheets', 'Need five A2 drawing sheets for the drawing practical submission this week.', 'Equipment & Instruments', 'Closed', NOW() - INTERVAL 10 DAY),
(7, 10, 'Need help with the Microprocessor practical', 'Looking for a senior or friend who has completed the 8085 practicals, to explain the steps before the viva.', 'Practical Help', 'Open', NOW() - INTERVAL 5 DAY),
(8, 8,  'Looking for a DBMS textbook', 'Need the Korth or Silberschatz DBMS textbook, preferably the latest edition. Budget under Rs. 400.', 'Textbooks & Books', 'Open', NOW() - INTERVAL 7 DAY);

-- Messages between students (contact seller and responses to requests)
INSERT INTO messages (id, sender_id, receiver_id, context_type, context_id, body, is_read, created_at) VALUES
(1, 5,  3, 'listing', 1, 'Hi Priya, is the Engineering Mathematics textbook still available? Can I collect it from the boys hostel gate tomorrow evening?', 1, NOW() - INTERVAL 1 DAY),
(2, 3,  5, 'listing', 1, 'Yes, it is still available. Please come to the gate after 5 PM. Thanks!', 1, NOW() - INTERVAL 20 HOUR),
(3, 7,  2, 'request', 2, 'Hi Aarav, I have handwritten Web Technology notes. I can send you the PDF if you share your Data Structures notes in return.', 0, NOW() - INTERVAL 2 DAY),
(4, 2,  9, 'request', 5, 'Hi Neha, I have Computer Networks notes for Units 1 and 2 on my resource list. I will check Unit 3 and let you know.', 1, NOW() - INTERVAL 3 DAY),
(5, 10, 2, 'listing', 9, 'Hi Aarav, would you take Rs. 80 for the handwritten physics notes?', 0, NOW() - INTERVAL 5 HOUR);

-- Reports for the admin moderation panel
INSERT INTO reports (id, user_id, item_type, item_id, reason, description, status, created_at) VALUES
(1, 2, 'listing', 13, 'Wrong Information', 'The listing says Good condition, but several pages look heavily highlighted.', 'Pending', NOW() - INTERVAL 1 DAY),
(2, 9, 'request', 8, 'Spam', 'The same textbook request appears to have been posted more than once.', 'Pending', NOW() - INTERVAL 2 DAY),
(3, 4, 'listing', 11, 'Other', 'The item was marked as sold, but it stayed in the list for a while.', 'Resolved', NOW() - INTERVAL 11 DAY);

-- ============================================================================
-- Quick check (optional): these counts should be 13 users, 13 listings, 12 resources, 8 requests
-- ============================================================================
-- SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM listings) AS listings,
--        (SELECT COUNT(*) FROM resources) AS resources, (SELECT COUNT(*) FROM requests) AS requests;
