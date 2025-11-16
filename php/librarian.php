<?php
require_once 'config.php';

// Check if user is logged in as librarian
check_user_type(['librarian']);

$message = '';
$error = '';

// Handle book addition form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_book'])) {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $genre = trim($_POST['genre'] ?? '');
    $recommended_grade = trim($_POST['recommended_grade'] ?? '');
    $total_pages = (int)($_POST['total_pages'] ?? 0);
    $difficulty = $_POST['difficulty'] ?? 'beginner';
    $description = trim($_POST['description'] ?? '');
    $qr_code = trim($_POST['qr_code'] ?? '');

    // Validate required fields
    if (!$title || !$author || !$qr_code) {
        $error = 'Title, Author, and QR Code are required!';
    } else {
        // Check if QR code already exists
        $check_qr_sql = "SELECT book_id FROM books WHERE qr_code = ?";
        $check_qr_stmt = $conn->prepare($check_qr_sql);
        $check_qr_stmt->bind_param("s", $qr_code);
        $check_qr_stmt->execute();
        $check_qr_result = $check_qr_stmt->get_result();
        
        if ($check_qr_result->num_rows > 0) {
            $error = '❌ QR Code already exists! Please use a unique QR code.';
            $check_qr_stmt->close();
        } else {
            $check_qr_stmt->close();
            
            try {
                // Create qr_codes directory if it doesn't exist
                $qr_dir = __DIR__ . '/../qr_codes';
                if (!file_exists($qr_dir)) {
                    mkdir($qr_dir, 0755, true);
                }
                
                $qr_code_path = "qr_codes/{$qr_code}.png";
                $qr_file_path = __DIR__ . '/../' . $qr_code_path;
                
                // Find Python executable
                $python_paths = [
                    'python',      // Try 'python' command first
                    'python3',     // Try 'python3' for Unix systems
                    'C:\\Python312\\python.exe',
                    'C:\\Python311\\python.exe',
                    'C:\\Python310\\python.exe',
                    'C:\\Python39\\python.exe',
                    'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',
                    'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python311\\python.exe',
                ];
                
                $python_cmd = null;
                foreach ($python_paths as $path) {
                    $test_output = shell_exec("\"$path\" --version 2>&1");
                    if ($test_output && stripos($test_output, 'python') !== false) {
                        $python_cmd = $path;
                        break;
                    }
                }
                
                $python_script = __DIR__ . '/../generate_single_qr.py';
                $qr_generated = false;
                
                if ($python_cmd) {
                    // Try Python script
                    $cmd = "\"$python_cmd\" \"$python_script\" \"$qr_code\" \"$qr_file_path\" 2>&1";
                    $output = shell_exec($cmd);
                    
                    if (file_exists($qr_file_path) && filesize($qr_file_path) > 0) {
                        $qr_generated = true;
                        $qr_method = "Python";
                    }
                }
                
                // Fallback to Google Charts API if Python failed
                if (!$qr_generated) {
                    $google_qr_url = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($qr_code) . "&choe=UTF-8";
                    $qr_image = @file_get_contents($google_qr_url);
                    
                    if ($qr_image !== false && strlen($qr_image) > 100) {
                        file_put_contents($qr_file_path, $qr_image);
                        $qr_generated = true;
                        $qr_method = "Google Charts API (Fallback)";
                    }
                }
                
                if (!$qr_generated) {
                    throw new Exception(
                        "Failed to generate QR code. Please:\n" .
                        "1. Install Python from https://www.python.org/downloads/\n" .
                        "2. Run: pip install qrcode[pil]\n" .
                        "3. Run test_qr_generation.py to verify installation\n" .
                        "4. Ensure internet connection for fallback method"
                    );
                }
                
                // Insert book into database
                $sql = "INSERT INTO books (title, author, qr_code, qr_code_path, genre, recommended_grade_level, total_pages, difficulty_level, description, is_available) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssiis", $title, $author, $qr_code, $qr_code_path, $genre, $recommended_grade, $total_pages, $difficulty, $description);
                
                if ($stmt->execute()) {
                    $book_id = $conn->insert_id;
                    $message = "✅ Book added successfully! Book ID: $book_id<br>🎯 QR Code generated: <strong>{$qr_code}.png</strong><br><small>Method: {$qr_method}</small>";
                    
                    // Log the action
                    $log_sql = "INSERT INTO system_logs (user_id, action, description) VALUES (?, 'book_added', ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $log_desc = "Added book: $title by $author (QR: $qr_code)";
                    $log_stmt->bind_param("is", $_SESSION['user_id'], $log_desc);
                    $log_stmt->execute();
                    $log_stmt->close();
                } else {
                    $error = 'Failed to add book to database. Please try again.';
                }
                $stmt->close();
                
            } catch (Exception $e) {
                $error = '❌ ' . nl2br($e->getMessage());
            }
        }
    }
}

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
        $message = "✅ Successfully updated $updated_count question(s)!";
        
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
    
    // Clear any previous error/message from book addition
    $error = '';
    $message = '';
    
    if (isset($_FILES['questions_file']) && $_FILES['questions_file']['error'] == 0) {
        $file = $_FILES['questions_file'];
        
        // Security Check 1: File size limit (500KB max)
        $max_file_size = 500 * 1024; // 500KB
        if ($file['size'] > $max_file_size) {
            $error = "❌ File too large. Maximum size is 500KB.";
        } else {
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Security Check 2: File extension validation
            if ($file_ext !== 'txt') {
                $error = "❌ Only .txt files are allowed.";
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
                            
                            // Return JSON response for AJAX
                            echo json_encode(['success' => true, 'message' => $message]);
                            exit();
                        } else {
                            $error = "❌ No valid questions found in the file. Please check the format.";
                            echo json_encode(['success' => false, 'message' => $error]);
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
        $error = "❌ Please select a file to upload.";
        echo json_encode(['success' => false, 'message' => $error]);
        exit();
    }
}

// Handle book deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_book'])) {
    $book_id = (int)$_POST['book_id'];
    
    // Check if book exists
    $check_sql = "SELECT title, qr_code_path FROM books WHERE book_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $book_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $book_data = $check_result->fetch_assoc();
        $book_title = $book_data['title'];
        $qr_code_path = $book_data['qr_code_path'];
        
        // Delete the book (CASCADE will delete related quiz questions, attempts, etc.)
        $delete_sql = "DELETE FROM books WHERE book_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $book_id);
        
        if ($delete_stmt->execute()) {
            $qr_delete_status = '';
            
            // Delete QR code file if exists
            if ($qr_code_path) {
                $qr_file_full_path = __DIR__ . '/../' . $qr_code_path;
                
                if (file_exists($qr_file_full_path)) {
                    if (unlink($qr_file_full_path)) {
                        $qr_delete_status = "<br>🗑️ QR code image deleted: {$qr_code_path}";
                    } else {
                        $qr_delete_status = "<br>⚠️ Warning: Could not delete QR code image (check permissions)";
                        // Log the error
                        error_log("Failed to delete QR code: " . $qr_file_full_path);
                    }
                } else {
                    $qr_delete_status = "<br>ℹ️ QR code image file not found: {$qr_code_path}";
                }
            }
            
            $message = "✅ Book '{$book_title}' and all related data deleted successfully!{$qr_delete_status}";
            
            // Log the action
            $log_sql = "INSERT INTO system_logs (user_id, action, description) VALUES (?, 'book_deleted', ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_desc = "Deleted book: $book_title (ID: $book_id)" . ($qr_delete_status ? " - QR code: {$qr_code_path}" : "");
            $log_stmt->bind_param("is", $_SESSION['user_id'], $log_desc);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $error = "❌ Failed to delete book. Please try again.";
        }
        $delete_stmt->close();
    } else {
        $error = "❌ Book not found.";
    }
    $check_stmt->close();
}

// Handle quiz question deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_question'])) {
    $question_id = (int)$_POST['question_id'];
    
    $delete_sql = "DELETE FROM quiz_questions WHERE question_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $question_id);
    
    if ($delete_stmt->execute()) {
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

// Fetch statistics
$stats = [];

// Total books
$sql = "SELECT COUNT(*) as total FROM books";
$result = $conn->query($sql);
$stats['total_books'] = $result->fetch_assoc()['total'];

// Currently borrowed (placeholder - would need book_borrowings table)
$stats['currently_borrowed'] = 0;

// Books borrowed today (placeholder)
$stats['borrowed_today'] = 0;

// Quizzes completed
$sql = "SELECT COUNT(*) as total FROM quiz_attempts WHERE DATE(attempt_date) = CURDATE()";
$result = $conn->query($sql);
$stats['quizzes_today'] = $result->fetch_assoc()['total'];

// Most read book
$sql = "SELECT COUNT(*) as read_count FROM quiz_attempts GROUP BY book_id ORDER BY read_count DESC LIMIT 1";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$stats['most_read_count'] = $row ? $row['read_count'] : 0;

// Completion rate
$sql = "SELECT AVG(CASE WHEN score_percentage >= 70 THEN 1 ELSE 0 END) * 100 as completion_rate FROM quiz_attempts";
$result = $conn->query($sql);
$stats['completion_rate'] = round($result->fetch_assoc()['completion_rate'], 0);

// Book inventory with statistics
$sql = "SELECT 
            b.book_id,
            b.qr_code,
            b.title,
            b.author,
            b.recommended_grade_level,
            COUNT(DISTINCT qa.user_id) as times_read,
            IFNULL(AVG(qa.score_percentage), 0) as avg_score
        FROM books b
        LEFT JOIN quiz_attempts qa ON b.book_id = qa.book_id
        GROUP BY b.book_id, b.qr_code, b.title, b.author, b.recommended_grade_level
        ORDER BY times_read DESC";
$book_inventory_result = $conn->query($sql);

// Store books in array for later use
$book_inventory = [];
while ($row = $book_inventory_result->fetch_assoc()) {
    $book_inventory[] = $row;
}

// Recent quiz activity
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
        ORDER BY qa.attempt_date DESC
        LIMIT 10";
$recent_activity_result = $conn->query($sql);

// Store activity in array
$recent_activity = [];
while ($row = $recent_activity_result->fetch_assoc()) {
    $recent_activity[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian Dashboard - Library Hub Tambo</title>
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
            margin-bottom: 20px;
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

        /* Accordion Styles */
        .accordion-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .accordion-header:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .accordion-header h3 {
            margin: 0;
            font-size: 1.2em;
        }

        .accordion-icon {
            font-size: 1.5em;
            transition: transform 0.3s;
        }

        .accordion-icon.active {
            transform: rotate(180deg);
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .accordion-content.active {
            max-height: 1500px;
            transition: max-height 0.5s ease-in;
        }

        .accordion-body {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
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

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Modal Styles */
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
            <p>Librarian Dashboard - Tambo, Lipa City</p>
            <div style="margin-top: 10px;">
                <span style="color: white;">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
                <a href="logout.php" class="btn-logout">🚪 Logout</a>
            </div>
        </div>

        <div class="card">
            <div class="dashboard-header">
                <h2>📚 Librarian Dashboard</h2>
                <div class="nav-links">
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="dashboard">
                <div class="dashboard-card">
                    <h3>📚 Total Books</h3>
                    <div class="stats">
                        <span class="stat-number"><?php echo $stats['total_books']; ?></span>
                    </div>
                </div>
                <div class="dashboard-card">
                    <h3>📝 Quizzes Today</h3>
                    <div class="stats">
                        <span class="stat-number"><?php echo $stats['quizzes_today']; ?></span>
                    </div>
                </div>
                <div class="dashboard-card">
                    <h3>✅ Pass Rate</h3>
                    <div class="stats">
                        <span class="stat-number"><?php echo $stats['completion_rate']; ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Accordion: Add New Book -->
            <div class="accordion-header" onclick="toggleAccordion()">
                <h3>➕ Add New Book</h3>
                <span class="accordion-icon" id="accordionIcon">▼</span>
            </div>
            <div class="accordion-content" id="accordionContent">
                <div class="accordion-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title">Book Title *</label>
                                <input type="text" id="title" name="title" required>
                            </div>
                            <div class="form-group">
                                <label for="author">Author *</label>
                                <input type="text" id="author" name="author" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="qr_code">QR Code *</label>
                                <input type="text" id="qr_code" name="qr_code" placeholder="e.g., QR001" required>
                            </div>
                            <div class="form-group">
                                <label for="genre">Genre</label>
                                <input type="text" id="genre" name="genre" placeholder="e.g., Fiction, Adventure">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="recommended_grade">Recommended Grade Level</label>
                                <select id="recommended_grade" name="recommended_grade">
                                    <option value="">Select Grade Level</option>
                                    <option value="Grade 1-2">Grade 1-2</option>
                                    <option value="Grade 3-4">Grade 3-4</option>
                                    <option value="Grade 5-6">Grade 5-6</option>
                                    <option value="Grade 1-3">Grade 1-3</option>
                                    <option value="Grade 4-6">Grade 4-6</option>
                                    <option value="Grade 1-6">Grade 1-6 (All Elementary)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="difficulty">Difficulty Level</label>
                                <select id="difficulty" name="difficulty">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="total_pages">Total Pages</label>
                            <input type="number" id="total_pages" name="total_pages" min="1" placeholder="Enter number of pages">
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" placeholder="Brief description of the book..."></textarea>
                        </div>

                        <button type="submit" name="add_book" class="btn">➕ Add Book</button>
                    </form>
                </div>
            </div>

            <h3>📚 Book Inventory & QR Codes</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>QR Code</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Grade Level</th>
                        <th>Times Read</th>
                        <th>Avg Score</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($book_inventory) > 0): ?>
                        <?php foreach ($book_inventory as $book): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['qr_code']); ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['recommended_grade_level'] ?? 'N/A'); ?></td>
                            <td><?php echo $book['times_read']; ?></td>
                            <td><?php echo round($book['avg_score'], 1); ?>%</td>
                            <td>
                                <button class="btn btn-small" onclick="openQuizEditor(<?php echo $book['book_id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                    ✏️ Edit Quiz
                                </button>
                                <button class="btn btn-small" style="background: #dc3545;" onclick="deleteBook(<?php echo $book['book_id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                    🗑️ Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #999;">No books found in the system</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3 style="margin-top: 30px;">📊 Recent Quiz Activity</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Student ID</th>
                        <th>Book</th>
                        <th>Score</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_activity) > 0): ?>
                        <?php foreach ($recent_activity as $activity): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($activity['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($activity['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($activity['book_title']); ?></td>
                            <td><?php echo $activity['correct_answers'] . '/' . $activity['total_questions'] . ' (' . round($activity['score_percentage'], 1) . '%)'; ?></td>
                            <td><?php echo $activity['formatted_date']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999;">No quiz activity yet</td>
                        </tr>
                    <?php endif; ?>
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
        function toggleAccordion() {
            const content = document.getElementById('accordionContent');
            const icon = document.getElementById('accordionIcon');
            
            content.classList.toggle('active');
            icon.classList.toggle('active');
        }

        let currentBookId = null;
        let questionsData = [];

        function deleteBook(bookId, bookTitle) {
            if (!confirm(`⚠️ Are you sure you want to delete "${bookTitle}"?\n\nThis will permanently delete:\n• The book record\n• All quiz questions\n• All student quiz attempts\n• QR code file\n\nThis action CANNOT be undone!`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('delete_book', '1');
            formData.append('book_id', bookId);
            
            fetch('librarian.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                alert('✅ Book deleted successfully!');
                location.reload();
            })
            .catch(error => {
                console.error('Error deleting book:', error);
                alert('❌ Error deleting book. Please try again.');
            });
        }

        async function deleteEntireQuiz(bookId, bookTitle) {
            if (!confirm(`⚠️ Are you sure you want to DELETE ALL QUIZ QUESTIONS for "${bookTitle}"?\n\nThis will permanently delete:\n• ALL quiz questions for this book\n• This will NOT delete the book itself\n• Students can no longer take the quiz\n\nThis action CANNOT be undone!`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('delete_quiz', '1');
            formData.append('book_id', bookId);
            
            try {
                const response = await fetch('librarian.php', {
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
                console.error('Error deleting quiz:', error);
                alert('❌ Error deleting quiz. Please try again.');
            }
        }

        async function deleteQuestion(questionId) {
            if (!confirm('⚠️ Are you sure you want to delete this question?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('delete_question', '1');
            formData.append('question_id', questionId);
            
            try {
                const response = await fetch('librarian.php', {
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
                
                // Add existing questions with individual delete buttons
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
                const response = await fetch('librarian.php', {
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
                const response = await fetch('librarian.php', {
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
                const response = await fetch('librarian.php', {
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

        window.onclick = function(event) {
            const modal = document.getElementById('quizEditorModal');
            if (event.target == modal) {
                closeQuizEditor();
            }
        }
    </script>
</body>
</html>