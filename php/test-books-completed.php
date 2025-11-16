<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$user_id = $_SESSION['user_id'];

echo "<h2>Books Completed Debug</h2>";
echo "<p><strong>User ID:</strong> $user_id</p>";

// Show all quiz attempts
echo "<h3>All Quiz Attempts:</h3>";
$sql = "SELECT attempt_id, book_id, score_percentage, attempt_date 
        FROM quiz_attempts 
        WHERE user_id = ? 
        ORDER BY attempt_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Attempt ID</th><th>Book ID</th><th>Score %</th><th>Status</th><th>Date</th></tr>";
while ($row = $result->fetch_assoc()) {
    $status = $row['score_percentage'] >= 70 ? 'Passed ✅' : 'Attempted ✔️';
    echo "<tr>";
    echo "<td>{$row['attempt_id']}</td>";
    echo "<td>{$row['book_id']}</td>";
    echo "<td>{$row['score_percentage']}%</td>";
    echo "<td>{$status}</td>";
    echo "<td>{$row['attempt_date']}</td>";
    echo "</tr>";
}
echo "</table>";
$stmt->close();

// Count books completed (ANY attempt)
echo "<h3>Books Completed Count:</h3>";
$count_sql = "SELECT COUNT(DISTINCT book_id) as books_count 
              FROM quiz_attempts 
              WHERE user_id = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_data = $count_result->fetch_assoc();
echo "<p><strong>Books Completed (any score): {$count_data['books_count']}</strong></p>";
$count_stmt->close();

// Show all books attempted
echo "<h3>Books Attempted:</h3>";
$attempted_sql = "SELECT DISTINCT b.book_id, b.title, 
                  COUNT(qa.attempt_id) as attempt_count,
                  MAX(qa.score_percentage) as best_score,
                  MIN(qa.attempt_date) as first_attempt,
                  MAX(qa.attempt_date) as last_attempt
                  FROM quiz_attempts qa
                  INNER JOIN books b ON qa.book_id = b.book_id
                  WHERE qa.user_id = ?
                  GROUP BY b.book_id, b.title
                  ORDER BY first_attempt DESC";
$attempted_stmt = $conn->prepare($attempted_sql);
$attempted_stmt->bind_param("i", $user_id);
$attempted_stmt->execute();
$attempted_result = $attempted_stmt->get_result();

if ($attempted_result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Book ID</th><th>Title</th><th>Attempts</th><th>Best Score</th><th>First Attempt</th><th>Last Attempt</th></tr>";
    while ($row = $attempted_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['book_id']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>{$row['attempt_count']}</td>";
        echo "<td>{$row['best_score']}%</td>";
        echo "<td>{$row['first_attempt']}</td>";
        echo "<td>{$row['last_attempt']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No books attempted yet.</p>";
}
$attempted_stmt->close();

// Show books that would qualify for rewards (passed with 70%+)
echo "<h3>Books Passed (≥70%):</h3>";
$passed_sql = "SELECT DISTINCT b.book_id, b.title, MAX(qa.score_percentage) as best_score
               FROM quiz_attempts qa
               INNER JOIN books b ON qa.book_id = b.book_id
               WHERE qa.user_id = ? AND qa.score_percentage >= 70
               GROUP BY b.book_id, b.title";
$passed_stmt = $conn->prepare($passed_sql);
$passed_stmt->bind_param("i", $user_id);
$passed_stmt->execute();
$passed_result = $passed_stmt->get_result();

if ($passed_result->num_rows > 0) {
    echo "<ul>";
    while ($row = $passed_result->fetch_assoc()) {
        echo "<li>Book ID: {$row['book_id']} - {$row['title']} (Best Score: {$row['best_score']}%)</li>";
    }
    echo "</ul>";
} else {
    echo "<p>⚠️ No books passed with 70%+ yet. Keep trying!</p>";
}
$passed_stmt->close();

echo "<hr>";
echo "<p><strong>Note:</strong> Any quiz attempt counts as completing a book! Re-attempts update your score but don't change the books completed count.</p>";
echo "<p><a href='quiz.php'>← Back to Quiz</a></p>";
?>
