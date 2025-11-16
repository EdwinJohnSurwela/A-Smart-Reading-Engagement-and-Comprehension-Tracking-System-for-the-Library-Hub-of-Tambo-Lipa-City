-- =====================================================
-- RESET DATABASE (DROP EVERYTHING SAFELY)
-- =====================================================
DROP DATABASE IF EXISTS library_reading_system;

-- =====================================================
-- SMART READING ENGAGEMENT AND COMPREHENSION TRACKING SYSTEM
-- Library Hub of Tambo, Lipa City - MySQL Database Schema
-- =====================================================

-- Create Database
CREATE DATABASE IF NOT EXISTS library_reading_system;
USE library_reading_system;

-- =====================================================
-- TABLE STRUCTURES FOR ERD
-- =====================================================

-- 1. USERS TABLE (Students, Teachers, Librarians, Admins)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(20) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('student', 'teacher', 'librarian', 'admin') NOT NULL,
    grade_level INT NULL,
    class_section VARCHAR(10) NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. BOOKS TABLE
CREATE TABLE books (
    book_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(100) NOT NULL,
    isbn VARCHAR(20) UNIQUE,
    qr_code VARCHAR(100) UNIQUE NOT NULL,
    qr_code_path VARCHAR(255),
    genre VARCHAR(50),
    recommended_grade_level VARCHAR(10),
    total_pages INT,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    book_cover_url VARCHAR(255),
    description TEXT,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3. BOOK BORROWING RECORDS
CREATE TABLE book_borrowings (
    borrowing_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    borrowed_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_return_date DATE,
    actual_return_date DATE NULL,
    status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
);

-- 4. QUIZ QUESTIONS TABLE
CREATE TABLE quiz_questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    points INT DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- 5. QUIZ ATTEMPTS TABLE
CREATE TABLE quiz_attempts (
    attempt_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    total_questions INT NOT NULL,
    correct_answers INT NOT NULL,
    score_percentage DECIMAL(5,2) NOT NULL,
    time_taken INT,
    attempt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
);

-- 6. QUIZ RESPONSES TABLE (Individual question responses)
CREATE TABLE quiz_responses (
    response_id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    user_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    is_correct BOOLEAN NOT NULL,
    response_time INT,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(question_id) ON DELETE CASCADE
);

-- 7. REWARDS TABLE
CREATE TABLE rewards (
    reward_id INT PRIMARY KEY AUTO_INCREMENT,
    reward_name VARCHAR(100) NOT NULL,
    reward_description TEXT,
    books_required INT NOT NULL,
    reward_type ENUM('physical', 'digital', 'badge') DEFAULT 'physical',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. USER REWARDS TABLE (Tracking earned rewards)
CREATE TABLE user_rewards (
    user_reward_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reward_id INT NOT NULL,
    earned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_claimed BOOLEAN DEFAULT FALSE,
    claimed_date TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES rewards(reward_id) ON DELETE CASCADE
);

-- 9. READING PROGRESS TABLE
CREATE TABLE reading_progress (
    progress_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    pages_read INT DEFAULT 0,
    reading_status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completion_date TIMESTAMP NULL,
    reading_time_minutes INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
);

-- 10. SYSTEM LOGS TABLE (For tracking system activities)
CREATE TABLE system_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 11. PASSWORD RESETS TABLE (For forgot password functionality)
CREATE TABLE password_resets (
    reset_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- =====================================================
-- INSERT SAMPLE DATA
-- =====================================================

-- Insert sample rewards
INSERT INTO rewards (reward_name, reward_description, books_required, reward_type) VALUES
('Pen Reward', 'A special pen for dedicated readers', 10, 'physical'),
('Notebook Reward', 'A beautiful notebook for book lovers', 25, 'physical'),
('Drawing Materials', 'Complete set of colored pencils, crayons, and sketch pad for creative young artists', 50, 'physical'),
('School Supplies Bundle', 'Comprehensive package including notebooks, pens, pencils, erasers, and organizers', 100, 'physical');

-- Insert sample users
INSERT INTO users (student_id, full_name, email, password_hash, user_type, grade_level, class_section) VALUES
('2024-001', 'Juan Dela Cruz', 'juan.delacruz@student.lipacity.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 5, 'A'),
('2024-002', 'Maria Santos', 'maria.santos@student.lipacity.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 4, 'B'),
('2024-003', 'Jose Rizal Jr.', 'jose.rizal@student.lipacity.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 6, 'A'),
('2024-004', 'Ana Reyes', 'ana.reyes@student.lipacity.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 5, 'C'),
('T001', 'Mrs. Elena Garcia', 'elena.garcia@teacher.lipacity.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', NULL, NULL),
('L001', 'Mr. Roberto Hernandez', 'roberto.hernandez@librarian.lipacity.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'librarian', NULL, NULL),
('A001', 'Dr. Carmen Lopez', 'carmen.lopez@admin.lipacity.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NULL);

-- Note: All sample passwords are hashed version of 'password123'

-- Insert sample books
INSERT INTO books (title, author, isbn, qr_code, qr_code_path, genre, recommended_grade_level, total_pages, difficulty_level, description) VALUES
('The Adventures of Tom Sawyer', 'Mark Twain', '978-0486400778', 'QR001', 'qr_codes/QR001.png', 'Adventure', '4-6', 224, 'intermediate', 'Classic adventure story of a mischievous boy in Missouri.'),
('Charlotte''s Web', 'E.B. White', '978-0064400558', 'QR002', 'qr_codes/QR002.png', 'Fiction', '3-5', 192, 'beginner', 'Heartwarming tale of friendship between a pig and spider.'),
('Where the Red Fern Grows', 'Wilson Rawls', '978-0486412670', 'QR003', 'qr_codes/QR003.png', 'Adventure', '4-6', 272, 'intermediate', 'Story of a boy and his hunting dogs in the Ozark Mountains.'),
('The Lion, the Witch and the Wardrobe', 'C.S. Lewis', '978-0064404990', 'QR004', 'qr_codes/QR004.png', 'Fantasy', '3-6', 208, 'intermediate', 'Magical adventure in the land of Narnia.'),
('Bridge to Terabithia', 'Katherine Paterson', '978-0064401845', 'QR005', 'qr_codes/QR005.png', 'Fiction', '4-6', 144, 'intermediate', 'Story of friendship and imagination.'),
('Harry Potter and the Sorcerer''s Stone', 'J.K. Rowling', '978-0439708180', 'QR006', 'qr_codes/QR006.png', 'Fantasy', '3-6', 309, 'intermediate', 'A young wizard discovers his magical heritage and attends Hogwarts.'),
('The Secret Garden', 'Frances Hodgson Burnett', '978-0064401883', 'QR007', 'qr_codes/QR007.png', 'Fiction', '3-5', 256, 'intermediate', 'A lonely girl discovers a hidden garden and transforms lives.'),
('Matilda', 'Roald Dahl', '978-0142410370', 'QR008', 'qr_codes/QR008.png', 'Fiction', '3-5', 240, 'beginner', 'A brilliant girl with telekinetic powers stands up to bullies.'),
('Percy Jackson: The Lightning Thief', 'Rick Riordan', '978-0786838653', 'QR009', 'qr_codes/QR009.png', 'Fantasy', '4-6', 377, 'intermediate', 'A boy discovers he is the son of a Greek god.'),
('Wonder', 'R.J. Palacio', '978-0375869020', 'QR010', 'qr_codes/QR010.png', 'Fiction', '4-6', 310, 'intermediate', 'A boy with facial differences navigates school and friendship.');

-- Insert sample quiz questions for all books
INSERT INTO quiz_questions (book_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty_level, created_by) VALUES
(1, 'What is the name of Tom Sawyer''s best friend?', 'Huckleberry Finn', 'Joe Harper', 'Ben Rogers', 'Sid Sawyer', 'A', 'easy', 7),
(1, 'What does Tom Sawyer convince his friends to do?', 'Go fishing', 'Paint the fence', 'Skip school', 'Steal apples', 'B', 'medium', 7),
(1, 'Where does Tom Sawyer live?', 'New York', 'Boston', 'St. Petersburg', 'Chicago', 'C', 'medium', 7),
(1, 'Who is Tom''s guardian?', 'His mother', 'Aunt Polly', 'His father', 'Uncle John', 'B', 'easy', 7),
(1, 'What genre is ''The Adventures of Tom Sawyer''?', 'Mystery', 'Romance', 'Adventure', 'Horror', 'C', 'easy', 7),
(2, 'What is the name of the pig in Charlotte''s Web?', 'Wilbur', 'Templeton', 'Fern', 'Homer', 'A', 'easy', 7),
(2, 'What type of animal is Charlotte?', 'A butterfly', 'A spider', 'A bee', 'A ladybug', 'B', 'easy', 7),
(2, 'Who saves Wilbur from being slaughtered at the beginning?', 'Charlotte', 'Fern', 'Templeton', 'Mr. Zuckerman', 'B', 'medium', 7),
(2, 'What does Charlotte write in her web to save Wilbur?', 'Good Pig', 'Some Pig', 'Great Pig', 'Best Pig', 'B', 'medium', 7),
(2, 'What happens to Charlotte at the end of the story?', 'She lives happily', 'She dies after laying eggs', 'She moves away', 'She gets sick', 'B', 'hard', 7),
(3, 'What is the name of the main character?', 'Billy', 'Tommy', 'Johnny', 'Bobby', 'A', 'easy', 7),
(3, 'How many dogs does Billy have?', 'One', 'Two', 'Three', 'Four', 'B', 'easy', 7),
(3, 'What are the names of Billy''s dogs?', 'Max and Ruby', 'Old Dan and Little Ann', 'Spot and Rover', 'Duke and Daisy', 'B', 'medium', 7),
(3, 'What do Billy and his dogs hunt?', 'Rabbits', 'Deer', 'Raccoons', 'Bears', 'C', 'medium', 7),
(3, 'Where does the story take place?', 'Texas Plains', 'Ozark Mountains', 'Rocky Mountains', 'Mississippi Delta', 'B', 'hard', 7),
(4, 'How do the children enter Narnia?', 'Through a mirror', 'Through a wardrobe', 'Through a door', 'Through a window', 'B', 'easy', 7),
(4, 'Who is the evil ruler of Narnia?', 'The White Witch', 'The Black Queen', 'The Ice Queen', 'The Snow Witch', 'A', 'easy', 7),
(4, 'Which child enters Narnia first?', 'Peter', 'Susan', 'Edmund', 'Lucy', 'D', 'medium', 7),
(4, 'What does the White Witch give Edmund to tempt him?', 'Ice cream', 'Turkish Delight', 'Chocolate', 'Candy', 'B', 'medium', 7),
(4, 'Who is Aslan?', 'A wizard', 'A dwarf', 'A lion', 'A unicorn', 'C', 'easy', 7),
(5, 'What are the names of the two main characters?', 'Jack and Jill', 'Tom and Lisa', 'Jess and Leslie', 'Mike and Sarah', 'C', 'easy', 7),
(5, 'What is Terabithia?', 'A real country', 'An imaginary kingdom', 'A school', 'A city', 'B', 'easy', 7),
(5, 'How do Jess and Leslie get to Terabithia?', 'By boat', 'By swinging on a rope', 'By walking', 'By climbing a tree', 'B', 'medium', 7),
(5, 'What does Jess love to do?', 'Play football', 'Draw and paint', 'Read books', 'Play video games', 'B', 'medium', 7),
(5, 'What tragic event happens in the story?', 'The school burns down', 'Leslie drowns', 'Jess moves away', 'Terabithia is destroyed', 'B', 'hard', 7),
(6, 'What is the name of the school Harry attends?', 'Beauxbatons', 'Hogwarts', 'Durmstrang', 'Ilvermorny', 'B', 'easy', 7),
(6, 'What position does Harry play in Quidditch?', 'Keeper', 'Beater', 'Seeker', 'Chaser', 'C', 'easy', 7),
(6, 'Who is Harry''s best friend?', 'Draco Malfoy', 'Neville Longbottom', 'Ron Weasley', 'Dean Thomas', 'C', 'easy', 7),
(6, 'What house is Harry sorted into?', 'Slytherin', 'Hufflepuff', 'Ravenclaw', 'Gryffindor', 'D', 'easy', 7),
(6, 'What is hidden in Hogwarts that Voldemort wants?', 'Elder Wand', 'Philosopher''s Stone', 'Horcrux', 'Time Turner', 'B', 'medium', 7),
(7, 'What is the main character''s name in The Secret Garden?', 'Mary Lennox', 'Martha', 'Lily', 'Sarah', 'A', 'easy', 7),
(7, 'Where does Mary find the key to the garden?', 'In a drawer', 'Buried by a robin', 'On a shelf', 'In the library', 'B', 'medium', 7),
(7, 'Who is Colin?', 'Mary''s brother', 'A gardener', 'Mary''s cousin', 'A servant', 'C', 'medium', 7),
(7, 'What is wrong with Colin at the start?', 'He is blind', 'He thinks he is sick', 'He is deaf', 'He has a broken leg', 'B', 'medium', 7),
(7, 'What brings the garden back to life?', 'Magic', 'Rain', 'Care and attention', 'Fertilizer', 'C', 'easy', 7),
(8, 'What special power does Matilda have?', 'Flying', 'Telekinesis', 'Invisibility', 'Super strength', 'B', 'easy', 7),
(8, 'Who is the cruel headmistress?', 'Miss Honey', 'Miss Trunchbull', 'Mrs. Wormwood', 'Miss Phelps', 'B', 'easy', 7),
(8, 'What does Matilda love to do?', 'Watch TV', 'Read books', 'Play sports', 'Cook', 'B', 'easy', 7),
(8, 'Who is Matilda''s kind teacher?', 'Miss Trunchbull', 'Miss Honey', 'Mrs. Phelps', 'Miss Silver', 'B', 'easy', 7),
(8, 'How does Matilda help Miss Honey?', 'Gives her money', 'Uses powers to scare Trunchbull', 'Buys her a house', 'Teaches her magic', 'B', 'medium', 7),
(9, 'Who is Percy Jackson''s father?', 'Zeus', 'Hades', 'Poseidon', 'Apollo', 'C', 'easy', 7),
(9, 'What was stolen at the beginning?', 'Zeus''s lightning bolt', 'Poseidon''s trident', 'Hades''s helmet', 'Athena''s shield', 'A', 'easy', 7),
(9, 'What is the name of Percy''s best friend who is a satyr?', 'Nico', 'Luke', 'Grover', 'Tyson', 'C', 'easy', 7),
(9, 'Who is Annabeth''s mother?', 'Aphrodite', 'Artemis', 'Athena', 'Hera', 'C', 'medium', 7),
(9, 'Where is the entrance to the Underworld?', 'New York', 'Los Angeles', 'Chicago', 'Seattle', 'B', 'medium', 7),
(10, 'What is August''s facial condition called?', 'Treacher Collins syndrome', 'Cleft palate', 'Birth defect', 'Genetic disorder', 'A', 'hard', 7),
(10, 'What is the name of August''s school?', 'Beecher Prep', 'Lincoln Middle', 'Jefferson Elementary', 'Washington Academy', 'A', 'medium', 7),
(10, 'Who becomes August''s first real friend?', 'Jack Will', 'Julian', 'Miles', 'Henry', 'A', 'easy', 7),
(10, 'What is August''s dog''s name?', 'Bear', 'Daisy', 'Max', 'Buddy', 'B', 'medium', 7),
(10, 'What award does August receive at the end?', 'Student of the Year', 'Henry Ward Beecher Medal', 'Perfect Attendance', 'Academic Excellence', 'B', 'medium', 7);

-- =====================================================
-- REFERENCE QUERIES FOR PHP CODE (Use Prepared Statements)
-- =====================================================

-- Reference: Get book by QR code
-- SELECT book_id, title, author, genre, recommended_grade_level, description, total_pages
-- FROM books 
-- WHERE qr_code = ? AND is_available = TRUE;

-- Reference: Get quiz questions for a book
-- SELECT question_id, question_text, option_a, option_b, option_c, option_d, points
-- FROM quiz_questions
-- WHERE book_id = ?
-- ORDER BY question_id;

-- Reference: Save quiz attempt
-- INSERT INTO quiz_attempts (user_id, book_id, total_questions, correct_answers, score_percentage)
-- VALUES (?, ?, ?, ?, ?);

-- Reference: Get all books with QR codes
SELECT book_id, title, author, qr_code, qr_code_path, genre, recommended_grade_level
FROM books
ORDER BY book_id;

-- Reference: Get user by email or student ID
-- SELECT user_id, full_name, user_type, grade_level, status 
-- FROM users 
-- WHERE (email = ? OR student_id = ?) AND status = 'active';

-- Reference: Get quiz results for a student
-- SELECT qa.attempt_id, qa.book_id, b.title, qa.score_percentage, qa.attempt_date
-- FROM quiz_attempts qa
-- JOIN books b ON qa.book_id = b.book_id
-- WHERE qa.user_id = ?
-- ORDER BY qa.attempt_date DESC;

-- Reference: Get all quiz attempts (for admin/teacher dashboard)
-- SELECT qa.attempt_id, u.full_name, b.title, qa.score_percentage, qa.attempt_date
-- FROM quiz_attempts qa
-- JOIN users u ON qa.user_id = u.user_id
-- JOIN books b ON qa.book_id = b.book_id
-- ORDER BY qa.attempt_date DESC;

-- =====================================================
-- SECURITY ENHANCEMENT TABLES
-- =====================================================

-- IP Blacklist Table
CREATE TABLE IF NOT EXISTS ip_blacklist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    reason VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address)
);

-- Two-Factor Authentication Codes
CREATE TABLE IF NOT EXISTS two_factor_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_code (user_id, code)
);

-- Session Management Table
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_session (session_id)
);

-- Login Attempts Tracking
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_ip (username, ip_address),
    INDEX idx_attempt_time (attempt_time)
);
