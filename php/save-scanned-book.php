<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    json_response(false, 'Invalid request method');
}

// Get JSON data
$json = file_get_contents('php://input');
$bookData = json_decode($json, true);

if (!$bookData || !isset($bookData['bookId'])) {
    json_response(false, 'Invalid book data');
}

// Save to session
$_SESSION['scanned_book'] = $bookData;

json_response(true, 'Book saved to session', $bookData);
?>
