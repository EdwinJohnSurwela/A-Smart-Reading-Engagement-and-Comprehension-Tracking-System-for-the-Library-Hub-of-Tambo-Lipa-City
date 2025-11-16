<?php
session_start();

// Fully clear all quiz/book-related session data
unset($_SESSION['scanned_book']);
unset($_SESSION['book_id']);
unset($_SESSION['book_title']);

// Return JSON for the JS to handle
header('Content-Type: application/json');
echo json_encode(['success' => true]);

header("Location: index.php");
exit();
?>
