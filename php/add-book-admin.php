<?php
session_start();
require_once 'config.php';

// Check if user is admin
check_user_type(['admin']);

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
                // Generate QR code image using Google Charts API (fallback method)
                $qr_code_path = "qr_codes/{$qr_code}.png";
                $google_qr_url = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($qr_code) . "&choe=UTF-8";
                
                // Create qr_codes directory if it doesn't exist
                if (!file_exists(__DIR__ . '/../qr_codes')) {
                    mkdir(__DIR__ . '/../qr_codes', 0755, true);
                }
                
                // Download QR code from Google Charts API
                $qr_image = @file_get_contents($google_qr_url);
                if ($qr_image !== false) {
                    file_put_contents(__DIR__ . '/../qr_codes/' . $qr_code . '.png', $qr_image);
                } else {
                    throw new Exception("Failed to generate QR code image");
                }
                
                // Insert book into database
                $sql = "INSERT INTO books (title, author, qr_code, qr_code_path, genre, recommended_grade_level, total_pages, difficulty_level, description, is_available) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssss", $title, $author, $qr_code, $qr_code_path, $genre, $recommended_grade, $total_pages, $difficulty, $description);
                
                if ($stmt->execute()) {
                    $book_id = $conn->insert_id;
                    $message = "✅ Book added successfully! Book ID: $book_id<br>🎯 QR Code generated and saved: <strong>{$qr_code}.png</strong>";
                    
                    // Log the action
                    $log_sql = "INSERT INTO system_logs (user_id, action, description) VALUES (?, 'book_added', ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $log_desc = "Added book: $title by $author (QR: $qr_code)";
                    $log_stmt->bind_param("is", $_SESSION['user_id'], $log_desc);
                    $log_stmt->execute();
                    $log_stmt->close();
                } else {
                    $error = 'Failed to add book. Please try again.';
                }
                $stmt->close();
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all books (removed isbn from query)
$books_sql = "SELECT book_id, title, author, qr_code, qr_code_path, genre, difficulty_level, recommended_grade_level FROM books ORDER BY created_at DESC";
$books_result = $conn->query($books_sql);
$books = [];
while ($row = $books_result->fetch_assoc()) {
    $books[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book - Admin Panel</title>
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
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            font-size: 14px;
        }

        textarea {
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        table tr:hover {
            background: #f8f9fa;
        }

        .back-link {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .qr-preview {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }

        .qr-preview img {
            max-width: 200px;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 10px;
            background: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Add New Book</h1>
            <p><a href="admin.php" class="back-link">← Back to Admin Dashboard</a></p>
        </div>

        <div class="card">
            <?php if ($message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

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
                        <input type="text" id="genre" name="genre" placeholder="e.g., Adventure, Fiction">
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
                    <input type="number" id="total_pages" name="total_pages" placeholder="Enter number of pages">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Enter book description..."></textarea>
                </div>

                <button type="submit" class="btn">➕ Add Book</button>
            </form>
        </div>

        <div class="card">
            <h2>📖 All Books in System</h2>
            <table>
                <thead>
                    <tr>
                        <th>Book ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>QR Code</th>
                        <th>QR Image</th>
                        <th>Genre</th>
                        <th>Grade Level</th>
                        <th>Difficulty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td><?php echo $book['book_id']; ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['qr_code']); ?></td>
                            <td>
                                <?php if ($book['qr_code_path'] && file_exists(__DIR__ . '/../' . $book['qr_code_path'])): ?>
                                    <a href="../<?php echo $book['qr_code_path']; ?>" target="_blank" title="View QR Code">
                                        📷 View
                                    </a>
                                <?php else: ?>
                                    ❌ Missing
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($book['genre'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($book['recommended_grade_level'] ?? 'N/A'); ?></td>
                            <td><?php echo ucfirst($book['difficulty_level']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
