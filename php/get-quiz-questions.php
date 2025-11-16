<?php
require_once 'config.php';

// Check if user is logged in as librarian or teacher
check_user_type(['librarian', 'teacher']);

header('Content-Type: application/json');

if (!isset($_GET['book_id'])) {
    echo json_encode(['success' => false, 'message' => 'Book ID is required']);
    exit();
}

$book_id = (int)$_GET['book_id'];

// Fetch quiz questions for this book
$sql = "SELECT question_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty_level 
        FROM quiz_questions 
        WHERE book_id = ? 
        ORDER BY question_id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

$questions = [];
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}

$stmt->close();

if (count($questions) > 0) {
    echo json_encode([
        'success' => true,
        'questions' => $questions
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No quiz questions found for this book'
    ]);
}
?>
