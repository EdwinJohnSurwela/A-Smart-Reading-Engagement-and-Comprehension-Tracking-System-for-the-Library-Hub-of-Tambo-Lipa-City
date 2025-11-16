<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['qr_code'])) {
    echo json_encode(['success' => false, 'message' => 'QR code is required']);
    exit();
}

$qr_code = sanitize_input($_GET['qr_code']);

// Fetch book from database by QR code
$sql = "SELECT book_id, title, author, qr_code, genre, recommended_grade_level, description 
        FROM books 
        WHERE qr_code = ? AND is_available = TRUE 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $qr_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $book = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'book' => [
            'bookId' => $book['book_id'],
            'qrCode' => $book['qr_code'],
            'title' => $book['title'],
            'author' => $book['author'],
            'genre' => $book['genre'] ?? 'General',
            'gradeLevel' => $book['recommended_grade_level'] ?? 'All Grades',
            'description' => $book['description'] ?? 'No description available'
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Book not found in database'
    ]);
}

$stmt->close();
?>
