<?php
require_once 'config.php';

// Check if user is logged in as teacher
check_user_type(['teacher']);

// Fetch statistics
$stats = [];

// Total students in system (teachers can see all)
$sql = "SELECT COUNT(*) as total FROM users WHERE user_type = 'student' AND status = 'active'";
$result = $conn->query($sql);
$stats['total_students'] = $result->fetch_assoc()['total'];

// Average quiz score
$sql = "SELECT AVG(score_percentage) as avg_score FROM quiz_attempts";
$result = $conn->query($sql);
$stats['average_score'] = round($result->fetch_assoc()['avg_score'], 1);

// Total books read
$sql = "SELECT COUNT(DISTINCT CONCAT(user_id, '-', book_id)) as total FROM quiz_attempts WHERE score_percentage >= 70";
$result = $conn->query($sql);
$stats['total_books_read'] = $result->fetch_assoc()['total'];

// Average books per student
$stats['avg_books_per_student'] = $stats['total_students'] > 0 ? 
    round($stats['total_books_read'] / $stats['total_students'], 1) : 0;

// Pen rewards
$sql = "SELECT COUNT(*) as total FROM user_rewards WHERE reward_id = 1";
$result = $conn->query($sql);
$stats['pens_given'] = $result->fetch_assoc()['total'];

// Notebook rewards
$sql = "SELECT COUNT(*) as total FROM user_rewards WHERE reward_id = 2";
$result = $conn->query($sql);
$stats['notebooks_given'] = $result->fetch_assoc()['total'];

// Student progress
$sql = "SELECT 
            u.full_name,
            u.student_id,
            b.title as book_title,
            qa.correct_answers,
            qa.total_questions,
            qa.score_percentage,
            DATE_FORMAT(qa.attempt_date, '%b %d, %Y') as formatted_date
        FROM quiz_attempts qa
        INNER JOIN users u ON qa.user_id = u.user_id
        INNER JOIN books b ON qa.book_id = b.book_id
        WHERE u.user_type = 'student'
        ORDER BY qa.attempt_date DESC
        LIMIT 20";
$student_progress = $conn->query($sql);

// Handle quiz question update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_questions'])) {
    $book_id = (int)$_POST['book_id'];
    $questions = $_POST['questions'] ?? [];
    
    $updated_count = 0;
    
    foreach ($questions as $q_id => $q_data) {
        $question_text = trim($q_data['text']);
        $option_a = trim($q_data['option_a']);
        $option_b = trim($q_data['option_b']);
        $option_c = trim($q_data['option_c']);
        $option_d = trim($q_data['option_d']);
        $correct_answer = $q_data['correct'];
        
        $update_sql = "UPDATE quiz_questions 
                      SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?
                      WHERE question_id = ? AND book_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssssii", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $q_id, $book_id);
        
        if ($update_stmt->execute()) {
            $updated_count++;
        }
        $update_stmt->close();
    }
    
    if ($updated_count > 0) {
        // Log the action
        $log_sql = "INSERT INTO system_logs (user_id, action, description) VALUES (?, 'quiz_updated', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $log_desc = "Updated quiz questions for book ID: $book_id";
        $log_stmt->bind_param("is", $_SESSION['user_id'], $log_desc);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

// Handle adding new question
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_question'])) {
    $book_id = (int)$_POST['book_id'];
    $question_text = trim($_POST['question_text']);
    $option_a = trim($_POST['option_a']);
    $option_b = trim($_POST['option_b']);
    $option_c = trim($_POST['option_c']);
    $option_d = trim($_POST['option_d']);
    $correct_answer = $_POST['correct_answer'];
    $difficulty = $_POST['difficulty'] ?? 'medium';
    
    $insert_sql = "INSERT INTO quiz_questions (book_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty_level, created_by) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $user_id = $_SESSION['user_id'];
    $insert_stmt->bind_param("isssssssi", $book_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $difficulty, $user_id);
    
    if ($insert_stmt->execute()) {
        $message = "✅ Question added successfully!";
    } else {
        $error = "❌ Failed to add question.";
    }
    $insert_stmt->close();
}

// Handle file upload for questions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_questions'])) {
    $book_id = (int)$_POST['book_id'];
    
    if (isset($_FILES['questions_file']) && $_FILES['questions_file']['error'] == 0) {
        $file = $_FILES['questions_file'];
        
        // Security Check 1: File size limit (500KB max)
        $max_file_size = 500 * 1024; // 500KB
        if ($file['size'] > $max_file_size) {
            echo json_encode(['success' => false, 'message' => "❌ File too large. Maximum size is 500KB."]);
            exit();
        } else {
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Security Check 2: File extension validation
            if ($file_ext !== 'txt') {
                echo json_encode(['success' => false, 'message' => "❌ Only .txt files are allowed."]);
                exit();
            } else {
                // Security Check 3: MIME type validation
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($file['tmp_name']);
                
                $allowed_mimes = ['text/plain', 'application/octet-stream'];
                if (!in_array($mime_type, $allowed_mimes)) {
                    $error = "❌ Invalid file type. Only plain text files are allowed.";
                } else {
                    // Security Check 4: File name sanitization
                    $original_filename = basename($file['name']);
                    $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $original_filename);
                    
                    // Security Check 5: Read and validate file content
                    $content = file_get_contents($file['tmp_name']);
                    
                    // Check for null bytes (file upload attack)
                    if (strpos($content, "\0") !== false) {
                        $error = "❌ Invalid file content detected.";
                    } else {
                        // Security Check 6: Validate UTF-8 encoding
                        if (!mb_check_encoding($content, 'UTF-8')) {
                            $content = mb_convert_encoding($content, 'UTF-8', 'auto');
                        }
                        
                        // Security Check 7: Remove any PHP/HTML/JavaScript code
                        $content = strip_tags($content);
                        
                        $lines = explode("\n", $content);
                        $questions_added = 0;
                        $i = 0;
                        
                        while ($i < count($lines)) {
                            $line = trim($lines[$i]);
                            
                            if (empty($line)) {
                                $i++;
                                continue;
                            }
                            
                            if (strpos($line, 'Q:') === 0 || strpos($line, 'Question:') === 0) {
                                $question_text = sanitize_input(trim(substr($line, strpos($line, ':') + 1)));
                                $option_a = '';
                                $option_b = '';
                                $option_c = '';
                                $option_d = '';
                                $correct_answer = '';
                                
                                for ($j = 1; $j <= 5; $j++) {
                                    $i++;
                                    if ($i >= count($lines)) break;
                                    
                                    $option_line = trim($lines[$i]);
                                    
                                    if (strpos($option_line, 'A)') === 0 || strpos($option_line, 'A.') === 0) {
                                        $option_a = sanitize_input(trim(substr($option_line, 2)));
                                    } elseif (strpos($option_line, 'B)') === 0 || strpos($option_line, 'B.') === 0) {
                                        $option_b = sanitize_input(trim(substr($option_line, 2)));
                                    } elseif (strpos($option_line, 'C)') === 0 || strpos($option_line, 'C.') === 0) {
                                        $option_c = sanitize_input(trim(substr($option_line, 2)));
                                    } elseif (strpos($option_line, 'D)') === 0 || strpos($option_line, 'D.') === 0) {
                                        $option_d = sanitize_input(trim(substr($option_line, 2)));
                                    } elseif (strpos($option_line, 'ANSWER:') === 0 || strpos($option_line, 'Answer:') === 0) {
                                        $answer_raw = trim(substr($option_line, strpos($option_line, ':') + 1));
                                        // Validate answer is A, B, C, or D only
                                        if (in_array(strtoupper($answer_raw), ['A', 'B', 'C', 'D'])) {
                                            $correct_answer = strtoupper($answer_raw);
                                        }
                                    }
                                }
                                
                                // Validate all fields before insertion
                                if (!empty($question_text) && !empty($option_a) && !empty($option_b) && 
                                    !empty($option_c) && !empty($option_d) && !empty($correct_answer)) {
                                    
                                    // Additional length validation
                                    if (strlen($question_text) > 500 || strlen($option_a) > 255 || 
                                        strlen($option_b) > 255 || strlen($option_c) > 255 || strlen($option_d) > 255) {
                                        continue; // Skip if text too long
                                    }
                                    
                                    $insert_sql = "INSERT INTO quiz_questions (book_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty_level, created_by) 
                                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'medium', ?)";
                                    $insert_stmt = $conn->prepare($insert_sql);
                                    $user_id = $_SESSION['user_id'];
                                    $insert_stmt->bind_param("issssssi", $book_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $user_id);
                                    
                                    if ($insert_stmt->execute()) {
                                        $questions_added++;
                                    }
                                    $insert_stmt->close();
                                }
                            }
                            
                            $i++;
                        }
                        
                        if ($questions_added > 0) {
                            $message = "✅ Successfully uploaded $questions_added question(s) from file!";
                            
                            // Log the upload action
                            $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                                       VALUES (?, 'questions_uploaded', ?, ?)";
                            $log_stmt = $conn->prepare($log_sql);
                            $log_desc = "Uploaded $questions_added questions for book ID: $book_id from file: $safe_filename";
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $log_stmt->bind_param("iss", $_SESSION['user_id'], $log_desc, $ip);
                            $log_stmt->execute();
                            $log_stmt->close();
                            
                            // Return JSON response
                            echo json_encode(['success' => true, 'message' => $message]);
                            exit();
                        } else {
                            echo json_encode(['success' => false, 'message' => "❌ No valid questions found in the file. Please check the format."]);
                            exit();
                        }
                    }
                }
            }
        }
        
        // Security: Delete the uploaded temporary file
        if (file_exists($file['tmp_name'])) {
            @unlink($file['tmp_name']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => "❌ Please select a file to upload."]);
        exit();
    }
}

// Handle quiz question deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_question'])) {
    $question_id = (int)$_POST['question_id'];
    
    $delete_sql = "DELETE FROM quiz_questions WHERE question_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $question_id);
    
    if ($delete_stmt->execute()) {
        // Log the action
        $log_sql = "INSERT INTO system_logs (user_id, action, description) VALUES (?, 'question_deleted', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $log_desc = "Deleted quiz question ID: $question_id";
        $log_stmt->bind_param("is", $_SESSION['user_id'], $log_desc);
        $log_stmt->execute();
        $log_stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Question deleted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete question.']);
    }
    $delete_stmt->close();
    exit();
}

// Handle entire quiz deletion (all questions for a book)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_quiz'])) {
    $book_id = (int)$_POST['book_id'];
    
    // Count how many questions will be deleted
    $count_sql = "SELECT COUNT(*) as total FROM quiz_questions WHERE book_id = ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $book_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_data = $count_result->fetch_assoc();
    $questions_count = $count_data['total'];
    $count_stmt->close();
    
    if ($questions_count == 0) {
        echo json_encode(['success' => false, 'message' => 'No questions found for this book.']);
        exit();
    }
    
    // Delete all questions for this book
    $delete_sql = "DELETE FROM quiz_questions WHERE book_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $book_id);
    
    if ($delete_stmt->execute()) {
        // Log the action
        $log_sql = "INSERT INTO system_logs (user_id, action, description) VALUES (?, 'quiz_deleted', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $log_desc = "Deleted entire quiz ($questions_count questions) for book ID: $book_id";
        $log_stmt->bind_param("is", $_SESSION['user_id'], $log_desc);
        $log_stmt->execute();
        $log_stmt->close();
        
        echo json_encode(['success' => true, 'message' => "Successfully deleted $questions_count question(s)!"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete quiz.']);
    }
    $delete_stmt->close();
    exit();
}

// Get all books for quiz management
$books_sql = "SELECT book_id, title, author, qr_code FROM books ORDER BY title ASC";
$books_result = $conn->query($books_sql);
$all_books = [];
while ($row = $books_result->fetch_assoc()) {
    $all_books[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Library Hub Tambo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .btn-logout {
            background: #dc3545;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-logout:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .nav-links a {
            color: #667eea;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s;
            margin: 0 5px;
        }

        .nav-links a:hover {
            background: #667eea;
            color: white;
        }

        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .dashboard-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .dashboard-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }

        .stats p {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e1e1;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table {
                font-size: 0.9em;
            }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close-modal {
            font-size: 28px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            background: none;
            border: none;
        }

        .close-modal:hover {
            color: #333;
        }

        .question-block {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .question-block label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .question-block input[type="text"],
        .question-block textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .question-block textarea {
            min-height: 60px;
            resize: vertical;
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }

        .option-input {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .option-input input[type="radio"] {
            cursor: pointer;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .file-upload-area {
            border: 3px dashed #667eea;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            margin-top: 15px;
        }

        .file-upload-area:hover {
            border-color: #764ba2;
            background: #e9ecef;
        }

        .file-upload-area.drag-over {
            border-color: #28a745;
            background: #d4edda;
            transform: scale(1.02);
        }

        .file-upload-icon {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .file-upload-text {
            color: #666;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .file-upload-text strong {
            color: #667eea;
            font-weight: 600;
        }

        .file-upload-hint {
            color: #999;
            font-size: 14px;
        }

        .file-input-hidden {
            display: none;
        }

        .file-name-display {
            margin-top: 15px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 5px;
            color: #333;
            font-weight: 600;
            display: none;
        }

        .file-name-display.active {
            display: block;
        }

        .remove-file-btn {
            margin-left: 10px;
            color: #dc3545;
            cursor: pointer;
            font-weight: bold;
        }

        .remove-file-btn:hover {
            color: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Library Hub Reading System</h1>
            <p>Teacher Dashboard - Tambo, Lipa City</p>
            <div style="margin-top: 10px;">
                <span style="color: white;">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
                <a href="logout.php" class="btn-logout">🚪 Logout</a>
            </div>
        </div>

        <div class="card">
            <div class="dashboard-header">
                <h2>👩‍🏫 Teacher Dashboard</h2>                
            </div>

            <div class="dashboard">
                <div class="dashboard-card">
                    <h3>🎓 Class Performance</h3>
                    <div class="stats">
                        <div>
                            <div class="stat-number">28</div>
                            <p>Students in Class</p>
                        </div>
                        <div>
                            <div class="stat-number">85%</div>
                            <p>Average Quiz Score</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <h3>📖 Reading Progress</h3>
                    <div class="stats">
                        <div>
                            <div class="stat-number">234</div>
                            <p>Total Books Read</p>
                        </div>
                        <div>
                            <div class="stat-number">8.4</div>
                            <p>Avg Books/Student</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <h3>🏆 Achievements</h3>
                    <div class="stats">
                        <div>
                            <div class="stat-number">15</div>
                            <p>Pen Rewards</p>
                        </div>
                        <div>
                            <div class="stat-number">4</div>
                            <p>Notebook Rewards</p>
                        </div>
                    </div>
                </div>
            </div>

            <h3 style="margin-top: 30px;">📝 Quiz Management</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Book ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>QR Code</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_books as $book): ?>
                    <tr>
                        <td><?php echo $book['book_id']; ?></td>
                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                        <td><?php echo htmlspecialchars($book['author']); ?></td>
                        <td><?php echo htmlspecialchars($book['qr_code']); ?></td>
                        <td>
                            <button class="btn btn-small" onclick="openQuizEditor(<?php echo $book['book_id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                ✏️ Edit Quiz
                            </button>
                            <button class="btn btn-small" style="background: #dc3545;" onclick="deleteEntireQuiz(<?php echo $book['book_id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                🗑️ Delete Quiz
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3>👥 Student Progress Tracking</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Student ID</th>
                        <th>Book Title</th>
                        <th>Quiz Score</th>
                        <th>Percentage</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody id="studentProgressTable">
                    <tr>
                        <td>Juan Dela Cruz</td>
                        <td>2024-001</td>
                        <td>Charlotte's Web</td>
                        <td>4/5</td>
                        <td>80%</td>
                        <td>Dec 6, 2024</td>
                    </tr>
                    <tr>
                        <td>Maria Santos</td>
                        <td>2024-002</td>
                        <td>Where the Red Fern Grows</td>
                        <td>5/5</td>
                        <td>100%</td>
                        <td>Dec 5, 2024</td>
                    </tr>
                    <tr>
                        <td>Jose Rizal Jr.</td>
                        <td>2024-003</td>
                        <td>Bridge to Terabithia</td>
                        <td>3/5</td>
                        <td>60%</td>
                        <td>Dec 4, 2024</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quiz Editor Modal -->
    <div id="quizEditorModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 id="quizEditorTitle">Edit Quiz Questions</h2>
                <button class="close-modal" onclick="closeQuizEditor()">&times;</button>
            </div>
            <div id="quizEditorBody" style="max-height: 60vh; overflow-y: auto;">
                <p style="text-align: center; padding: 20px;">Loading questions...</p>
            </div>
            <div style="margin-top: 20px; text-align: right;">
                <button class="btn" onclick="saveQuizChanges()">💾 Save Changes</button>
                <button class="btn btn-secondary" onclick="closeQuizEditor()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Load quiz results from localStorage
        function loadQuizResults() {
            const results = JSON.parse(localStorage.getItem('quizResults') || '[]');
            const tableBody = document.getElementById('studentProgressTable');
            
            // Add new results to the table
            results.forEach(result => {
                const row = document.createElement('tr');
                row.style.backgroundColor = '#fffacd'; // Highlight new entries
                row.innerHTML = `
                    <td>${result.studentName}</td>
                    <td>${result.studentId}</td>
                    <td>${result.bookTitle}</td>
                    <td>${result.score}/${result.totalQuestions}</td>
                    <td>${result.percentage}%</td>
                    <td>${result.date}</td>
                `;
                tableBody.insertBefore(row, tableBody.firstChild);
            });
        }

        // Load results when page loads
        loadQuizResults();

        let currentBookId = null;
        let questionsData = [];

        async function deleteQuestion(questionId) {
            if (!confirm('⚠️ Are you sure you want to delete this question?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('delete_question', '1');
            formData.append('question_id', questionId);
            
            try {
                const response = await fetch('teacher.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ Question deleted successfully!');
                    closeQuizEditor();
                    location.reload();
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (error) {
                console.error('Error deleting question:', error);
                alert('❌ Error deleting question. Please try again.');
            }
        }

        async function openQuizEditor(bookId, bookTitle) {
            currentBookId = bookId;
            document.getElementById('quizEditorTitle').textContent = `Edit Quiz: ${bookTitle}`;
            document.getElementById('quizEditorModal').classList.add('active');
            
            try {
                const response = await fetch(`get-quiz-questions.php?book_id=${bookId}`);
                const data = await response.json();
                
                if (data.success) {
                    questionsData = data.questions;
                    renderQuizEditor(data.questions, bookTitle);
                } else {
                    // Even if no questions found, still show add/upload sections
                    renderQuizEditor([], bookTitle);
                }
            } catch (error) {
                console.error('Error loading questions:', error);
                // Show add/upload sections even on error
                renderQuizEditor([], bookTitle);
            }
        }

        async function deleteEntireQuiz(bookId, bookTitle) {
            if (!confirm(`⚠️ Are you sure you want to DELETE ALL QUIZ QUESTIONS for "${bookTitle}"?\n\nThis will permanently delete:\n• ALL quiz questions for this book\n• This will NOT delete the book itself\n• Students can no longer take the quiz\n\nThis action CANNOT be undone!`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('delete_quiz', '1');
            formData.append('book_id', bookId);
            
            try {
                const response = await fetch('teacher.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ ' + data.message);
                    // Force page reload to reflect changes
                    window.location.reload();
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (error) {
                console.error('Error deleting quiz:', error);
                alert('❌ Error deleting quiz. Please try again.');
            }
        }

        function renderQuizEditor(questions, bookTitle) {
            const editorBody = document.getElementById('quizEditorBody');
            
            let html = '';
            
            // Add "Delete Entire Quiz" button at the top if there are questions
            if (questions.length > 0) {
                html += `
                    <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="margin: 0 0 5px 0; color: #856404;">⚠️ Danger Zone</h4>
                                <p style="margin: 0; color: #856404; font-size: 0.9em;">Delete all ${questions.length} question(s) for this book</p>
                            </div>
                            <button type="button" class="btn btn-small" style="background: #dc3545; padding: 8px 15px;" onclick="deleteEntireQuiz(${currentBookId}, '${bookTitle.replace(/'/g, "\\'")}')">
                                🗑️ Delete Entire Quiz
                            </button>
                        </div>
                    </div>
                `;
            }
            
            if (questions.length > 0) {
                html += '<form id="quizEditorForm">';
                
                // Add existing questions with delete button
                questions.forEach((q, index) => {
                    html += `
                        <div class="question-block" id="question-${q.question_id}">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <h4 style="margin: 0;">Question ${index + 1}</h4>
                                <button type="button" class="btn btn-small" style="background: #dc3545; padding: 5px 10px;" onclick="deleteQuestion(${q.question_id})">
                                    🗑️ Delete
                                </button>
                            </div>
                            <label>Question Text:</label>
                            <textarea name="questions[${q.question_id}][text]" required>${q.question_text}</textarea>
                            
                            <label>Options:</label>
                            <div class="options-grid">
                                <div class="option-input">
                                    <input type="radio" name="questions[${q.question_id}][correct]" value="A" ${q.correct_answer === 'A' ? 'checked' : ''}>
                                    <input type="text" name="questions[${q.question_id}][option_a]" value="${q.option_a}" placeholder="Option A" required>
                                </div>
                                <div class="option-input">
                                    <input type="radio" name="questions[${q.question_id}][correct]" value="B" ${q.correct_answer === 'B' ? 'checked' : ''}>
                                    <input type="text" name="questions[${q.question_id}][option_b]" value="${q.option_b}" placeholder="Option B" required>
                                </div>
                                <div class="option-input">
                                    <input type="radio" name="questions[${q.question_id}][correct]" value="C" ${q.correct_answer === 'C' ? 'checked' : ''}>
                                    <input type="text" name="questions[${q.question_id}][option_c]" value="${q.option_c}" placeholder="Option C" required>
                                </div>
                                <div class="option-input">
                                    <input type="radio" name="questions[${q.question_id}][correct]" value="D" ${q.correct_answer === 'D' ? 'checked' : ''}>
                                    <input type="text" name="questions[${q.question_id}][option_d]" value="${q.option_d}" placeholder="Option D" required>
                                </div>
                            </div>
                            <small style="color: #666;">Select the radio button next to the correct answer</small>
                        </div>
                    `;
                });
                
                html += '</form>';
            } else {
                // Show info message when no questions exist
                html += `
                    <div style="background: #e7f3ff; border: 2px solid #2196F3; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                        <h4 style="margin: 0 0 5px 0; color: #1976D2;">📝 No Quiz Questions Yet</h4>
                        <p style="margin: 0; color: #1976D2; font-size: 0.9em;">This book doesn't have any quiz questions. Add your first question below!</p>
                    </div>
                `;
            }
            
            // Add "Add New Question" section (always show)
            html += `
                <div style="margin-top: ${questions.length > 0 ? '30px' : '0'}; padding-top: ${questions.length > 0 ? '20px' : '0'}; border-top: ${questions.length > 0 ? '2px solid #667eea' : 'none'};">
                    <h3>➕ Add New Question</h3>
                    <form id="addQuestionForm" style="margin-top: 20px;">
                        <div class="question-block">
                            <label>Question Text:</label>
                            <textarea id="newQuestionText" placeholder="Enter your question here..." required></textarea>
                            
                            <label>Options:</label>
                            <div class="options-grid">
                                <div class="option-input">
                                    <input type="radio" name="newCorrect" value="A" required>
                                    <input type="text" id="newOptionA" placeholder="Option A" required>
                                </div>
                                <div class="option-input">
                                    <input type="radio" name="newCorrect" value="B">
                                    <input type="text" id="newOptionB" placeholder="Option B" required>
                                </div>
                                <div class="option-input">
                                    <input type="radio" name="newCorrect" value="C">
                                    <input type="text" id="newOptionC" placeholder="Option C" required>
                                </div>
                                <div class="option-input">
                                    <input type="radio" name="newCorrect" value="D">
                                    <input type="text" id="newOptionD" placeholder="Option D" required>
                                </div>
                            </div>
                            <label>Difficulty:</label>
                            <select id="newDifficulty" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                <option value="easy">Easy</option>
                                <option value="medium" selected>Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                            <button type="button" class="btn" onclick="addNewQuestion()" style="margin-top: 15px;">➕ Add Question</button>
                        </div>
                    </form>
                </div>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #667eea;">
                    <h3>📤 Upload Questions from File</h3>
                    <form id="uploadQuestionsForm" enctype="multipart/form-data" style="margin-top: 20px;">
                        <div class="question-block">
                            <input type="file" id="questionsFile" accept=".txt" class="file-input-hidden">
                            
                            <div class="file-upload-area" id="fileUploadArea">
                                <div class="file-upload-icon">📁</div>
                                <div class="file-upload-text">
                                    <strong>Click to browse</strong> or drag and drop your file here
                                </div>
                                <div class="file-upload-hint">
                                    Supported format: .txt (Text File)
                                </div>
                            </div>
                            
                            <div class="file-name-display" id="fileNameDisplay">
                                <span id="fileName"></span>
                                <span class="remove-file-btn" onclick="removeFile()">✖</span>
                            </div>
                            
                            <small style="color: #666; display: block; margin-top: 15px;">
                                <strong>Format example:</strong><br>
                                Q: What is the capital of France?<br>
                                A) London<br>
                                B) Paris<br>
                                C) Berlin<br>
                                D) Madrid<br>
                                ANSWER: B
                            </small>
                            <button type="button" class="btn" onclick="uploadQuestions()" style="margin-top: 15px;">📤 Upload File</button>
                        </div>
                    </form>
                </div>
            `;
            
            editorBody.innerHTML = html;
            
            // Initialize drag and drop after rendering
            setTimeout(() => {
                initializeDragAndDrop();
            }, 100);
        }

        function initializeDragAndDrop() {
            const fileInput = document.getElementById('questionsFile');
            const fileUploadArea = document.getElementById('fileUploadArea');
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const fileNameSpan = document.getElementById('fileName');

            if (!fileUploadArea || !fileInput) return;

            // Click to browse
            fileUploadArea.addEventListener('click', () => {
                fileInput.click();
            });

            // File selected via browse
            fileInput.addEventListener('change', (e) => {
                handleFileSelect(e.target.files[0]);
            });

            // Drag and drop events
            fileUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                fileUploadArea.classList.add('drag-over');
            });

            fileUploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                fileUploadArea.classList.remove('drag-over');
            });

            fileUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                fileUploadArea.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const file = files[0];
                    
                    // Check if it's a .txt file
                    if (file.name.endsWith('.txt')) {
                        // Create a new FileList-like object
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        fileInput.files = dataTransfer.files;
                        
                        handleFileSelect(file);
                    } else {
                        alert('❌ Please upload a .txt file only!');
                    }
                }
            });

            function handleFileSelect(file) {
                if (file) {
                    fileNameSpan.textContent = `📄 ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
                    fileNameDisplay.classList.add('active');
                    fileUploadArea.style.borderColor = '#28a745';
                }
            }
        }

        function removeFile() {
            const fileInput = document.getElementById('questionsFile');
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const fileUploadArea = document.getElementById('fileUploadArea');
            
            fileInput.value = '';
            fileNameDisplay.classList.remove('active');
            fileUploadArea.style.borderColor = '#667eea';
        }

        function closeQuizEditor() {
            document.getElementById('quizEditorModal').classList.remove('active');
            currentBookId = null;
        }

        async function saveQuizChanges() {
            const form = document.getElementById('quizEditorForm');
            const formData = new FormData(form);
            formData.append('update_questions', '1');
            formData.append('book_id', currentBookId);
            
            try {
                const response = await fetch('teacher.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                
                alert('✅ Quiz questions updated successfully!');
                closeQuizEditor();
                location.reload();
            } catch (error) {
                console.error('Error saving questions:', error);
                alert('❌ Error saving questions. Please try again.');
            }
        }

        async function addNewQuestion() {
            const questionText = document.getElementById('newQuestionText').value.trim();
            const optionA = document.getElementById('newOptionA').value.trim();
            const optionB = document.getElementById('newOptionB').value.trim();
            const optionC = document.getElementById('newOptionC').value.trim();
            const optionD = document.getElementById('newOptionD').value.trim();
            const correctAnswer = document.querySelector('input[name="newCorrect"]:checked')?.value;
            const difficulty = document.getElementById('newDifficulty').value;
            
            if (!questionText || !optionA || !optionB || !optionC || !optionD || !correctAnswer) {
                alert('❌ Please fill in all fields and select the correct answer!');
                return;
            }
            
            const formData = new FormData();
            formData.append('add_question', '1');
            formData.append('book_id', currentBookId);
            formData.append('question_text', questionText);
            formData.append('option_a', optionA);
            formData.append('option_b', optionB);
            formData.append('option_c', optionC);
            formData.append('option_d', optionD);
            formData.append('correct_answer', correctAnswer);
            formData.append('difficulty', difficulty);
            
            try {
                const response = await fetch('teacher.php', {
                    method: 'POST',
                    body: formData
                });
                
                alert('✅ Question added successfully!');
                closeQuizEditor();
                location.reload();
            } catch (error) {
                console.error('Error adding question:', error);
                alert('❌ Error adding question. Please try again.');
            }
        }

        async function uploadQuestions() {
            const fileInput = document.getElementById('questionsFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('❌ Please select a file to upload!');
                return;
            }
            
            const formData = new FormData();
            formData.append('upload_questions', '1');
            formData.append('book_id', currentBookId);
            formData.append('questions_file', file);
            
            try {
                const response = await fetch('teacher.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ ' + data.message);
                    closeQuizEditor();
                    location.reload();
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (error) {
                console.error('Error uploading questions:', error);
                alert('❌ Error uploading questions. Please try again.');
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('quizEditorModal');
            if (event.target == modal) {
                closeQuizEditor();
            }
        }
    </script>
</body>
</html>
