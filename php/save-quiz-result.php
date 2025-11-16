<?php
// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

require_once 'config.php';

ob_clean();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Debug log function
function debug_log($message) {
    $log_file = __DIR__ . '/debug_quiz.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

debug_log("=== Quiz Save Request Started ===");

// Check if user is logged in
if (!is_logged_in()) {
    ob_clean();
    debug_log("ERROR: User not logged in");
    json_response(false, 'User not logged in');
}

debug_log("User logged in: " . $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ob_clean();
    debug_log("ERROR: Invalid request method");
    json_response(false, 'Invalid request method');
}

// Get JSON data
$json = file_get_contents('php://input');
debug_log("Raw JSON: " . $json);

$quizData = json_decode($json, true);

if (!$quizData) {
    ob_clean();
    debug_log("ERROR: Could not parse JSON");
    json_response(false, 'Invalid quiz data - could not parse JSON');
}

debug_log("Quiz Data: " . print_r($quizData, true));

// Validate required fields
$required_fields = ['user_id', 'book_id', 'total_questions', 'correct_answers', 'score_percentage'];
foreach ($required_fields as $field) {
    if (!isset($quizData[$field])) {
        ob_clean();
        debug_log("ERROR: Missing field - $field");
        json_response(false, "Missing required field: $field");
    }
}

// Extract data
$user_id = (int)$quizData['user_id'];
$book_id = (int)$quizData['book_id'];
$total_questions = (int)$quizData['total_questions'];
$correct_answers = (int)$quizData['correct_answers'];
$score_percentage = (float)$quizData['score_percentage'];
$time_taken = isset($quizData['time_taken']) ? (int)$quizData['time_taken'] : 0;

debug_log("Extracted data - User: $user_id, Book: $book_id, Score: $correct_answers/$total_questions ($score_percentage%)");

// Verify user_id matches session
if ($user_id != $_SESSION['user_id']) {
    ob_clean();
    debug_log("ERROR: User ID mismatch - Session: " . $_SESSION['user_id'] . ", Submitted: $user_id");
    json_response(false, 'User ID mismatch');
}

// Validate data ranges
if ($total_questions <= 0 || $correct_answers < 0 || $correct_answers > $total_questions) {
    ob_clean();
    debug_log("ERROR: Invalid quiz data values");
    json_response(false, 'Invalid quiz data values');
}

try {
    debug_log("Starting database operations...");
    
    // Start transaction
    $conn->begin_transaction();
    debug_log("Transaction started");
    
    // SECURITY FIX: Verify user exists in database before inserting
    $user_check_sql = "SELECT user_id, full_name FROM users WHERE user_id = ? AND status = 'active'";
    $user_check_stmt = $conn->prepare($user_check_sql);
    
    if (!$user_check_stmt) {
        $conn->rollback();
        ob_clean();
        debug_log("ERROR: Failed to prepare user check statement - " . $conn->error);
        json_response(false, 'Database error: Unable to verify user');
    }
    
    $user_check_stmt->bind_param("i", $user_id);
    $user_check_stmt->execute();
    $user_check_result = $user_check_stmt->get_result();
    
    if ($user_check_result->num_rows === 0) {
        $user_check_stmt->close();
        $conn->rollback();
        ob_clean();
        debug_log("ERROR: User not found or inactive - ID: $user_id");
        json_response(false, 'Invalid user account. Please login again.');
    }
    
    $user_data = $user_check_result->fetch_assoc();
    debug_log("User verified: " . $user_data['full_name']);
    $user_check_stmt->close();
    
    // Verify book exists
    $book_check_sql = "SELECT book_id, title FROM books WHERE book_id = ?";
    $book_check_stmt = $conn->prepare($book_check_sql);
    
    if (!$book_check_stmt) {
        $conn->rollback();
        ob_clean();
        debug_log("ERROR: Failed to prepare book check statement - " . $conn->error);
        json_response(false, 'Database error: Unable to verify book');
    }
    
    $book_check_stmt->bind_param("i", $book_id);
    $book_check_stmt->execute();
    $book_check_result = $book_check_stmt->get_result();
    
    if ($book_check_result->num_rows === 0) {
        $book_check_stmt->close();
        $conn->rollback();
        ob_clean();
        debug_log("ERROR: Book not found - ID: $book_id");
        json_response(false, 'Invalid book ID');
    }
    
    $book_data = $book_check_result->fetch_assoc();
    debug_log("Book verified: " . $book_data['title']);
    $book_check_stmt->close();
    
    // Insert quiz attempt
    $sql = "INSERT INTO quiz_attempts (user_id, book_id, total_questions, correct_answers, score_percentage, time_taken, attempt_date) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $conn->rollback();
        ob_clean();
        debug_log("ERROR: Failed to prepare insert statement - " . $conn->error);
        json_response(false, 'Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("iiiddi", $user_id, $book_id, $total_questions, $correct_answers, $score_percentage, $time_taken);
    
    debug_log("Attempting to insert quiz attempt...");
    
    if ($stmt->execute()) {
        $attempt_id = $conn->insert_id;
        debug_log("Quiz attempt inserted successfully! Attempt ID: $attempt_id");
        $stmt->close();
        
        // Log the quiz completion with score
        $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                   VALUES (?, 'quiz_completed', ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        if ($log_stmt) {
            $passed = $score_percentage >= 70 ? 'PASSED' : 'FAILED';
            $log_desc = sprintf(
                "Completed quiz for '%s' - Score: %d/%d (%.1f%%) - %s",
                $book_data['title'],
                $correct_answers,
                $total_questions,
                $score_percentage,
                $passed
            );
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iss", $user_id, $log_desc, $ip);
            $log_stmt->execute();
            debug_log("System log created");
            $log_stmt->close();
        }
        
        // FIXED: Count books completed - ANY attempt counts, not just passing scores
        // Check if this is a re-attempt (book already completed before)
        $reattempt_check_sql = "SELECT COUNT(*) as previous_attempts 
                                FROM quiz_attempts 
                                WHERE user_id = ? AND book_id = ?";
        $reattempt_stmt = $conn->prepare($reattempt_check_sql);
        if (!$reattempt_stmt) {
            $conn->rollback();
            ob_clean();
            debug_log("ERROR: Failed to check for re-attempts");
            json_response(false, 'Failed to check attempt history');
        }
        $reattempt_stmt->bind_param("ii", $user_id, $book_id);
        $reattempt_stmt->execute();
        $reattempt_result = $reattempt_stmt->get_result();
        $reattempt_data = $reattempt_result->fetch_assoc();
        $is_reattempt = $reattempt_data['previous_attempts'] > 0;
        debug_log("Is re-attempt: " . ($is_reattempt ? 'YES' : 'NO') . " (Previous attempts: {$reattempt_data['previous_attempts']})");
        $reattempt_stmt->close();
        
        // Calculate books completed - count DISTINCT books attempted (any score)
        $books_read_sql = "SELECT COUNT(DISTINCT book_id) as books_count 
                          FROM quiz_attempts 
                          WHERE user_id = ?";
        $books_stmt = $conn->prepare($books_read_sql);
        if (!$books_stmt) {
            $conn->rollback();
            ob_clean();
            debug_log("ERROR: Failed to calculate books completed");
            json_response(false, 'Failed to calculate books completed');
        }
        $books_stmt->bind_param("i", $user_id);
        $books_stmt->execute();
        $books_result = $books_stmt->get_result();
        $books_data = $books_result->fetch_assoc();
        $books_count = (int)$books_data['books_count'];
        debug_log("Books completed count (any attempt): $books_count");
        $books_stmt->close();
        
        // Calculate average score
        $avg_score_sql = "SELECT AVG(score_percentage) as avg_score 
                         FROM quiz_attempts 
                         WHERE user_id = ?";
        $avg_stmt = $conn->prepare($avg_score_sql);
        if (!$avg_stmt) {
            $conn->rollback();
            ob_clean();
            debug_log("ERROR: Failed to calculate average score");
            json_response(false, 'Failed to calculate average score');
        }
        $avg_stmt->bind_param("i", $user_id);
        $avg_stmt->execute();
        $avg_result = $avg_stmt->get_result();
        $avg_data = $avg_result->fetch_assoc();
        $average_score = round((float)$avg_data['avg_score'], 1);
        debug_log("Average score: $average_score%");
        $avg_stmt->close();
        
        // Check and assign rewards
        $rewards_earned = [];
        $new_rewards = [];
        
        // Get all active rewards
        $rewards_sql = "SELECT reward_id, reward_name, books_required 
                       FROM rewards 
                       WHERE is_active = TRUE 
                       ORDER BY books_required ASC";
        $rewards_result = $conn->query($rewards_sql);
        
        if ($rewards_result) {
            debug_log("Processing rewards based on $books_count books completed...");
            while ($reward = $rewards_result->fetch_assoc()) {
                debug_log("Checking reward: " . $reward['reward_name'] . " (requires " . $reward['books_required'] . " books)");
                
                if ($books_count >= $reward['books_required']) {
                    // Check if user already has this reward
                    $check_reward_sql = "SELECT user_reward_id FROM user_rewards 
                                        WHERE user_id = ? AND reward_id = ?";
                    $check_stmt = $conn->prepare($check_reward_sql);
                    if ($check_stmt) {
                        $check_stmt->bind_param("ii", $user_id, $reward['reward_id']);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows == 0) {
                            // Award the reward
                            $award_sql = "INSERT INTO user_rewards (user_id, reward_id, earned_date) 
                                         VALUES (?, ?, NOW())";
                            $award_stmt = $conn->prepare($award_sql);
                            if ($award_stmt) {
                                $award_stmt->bind_param("ii", $user_id, $reward['reward_id']);
                                if ($award_stmt->execute()) {
                                    $new_rewards[] = $reward['reward_name'];
                                    debug_log("✅ New reward awarded: " . $reward['reward_name']);
                                }
                                $award_stmt->close();
                            }
                        } else {
                            debug_log("User already has reward: " . $reward['reward_name']);
                        }
                        
                        $rewards_earned[] = $reward['reward_name'];
                        $check_stmt->close();
                    }
                } else {
                    debug_log("Not enough books for reward: " . $reward['reward_name']);
                }
            }
        }
        
        // Count total rewards earned
        $total_rewards_sql = "SELECT COUNT(DISTINCT reward_id) as total FROM user_rewards WHERE user_id = ?";
        $rewards_count_stmt = $conn->prepare($total_rewards_sql);
        $total_rewards = 0;
        if ($rewards_count_stmt) {
            $rewards_count_stmt->bind_param("i", $user_id);
            $rewards_count_stmt->execute();
            $rewards_count_result = $rewards_count_stmt->get_result();
            $rewards_count_data = $rewards_count_result->fetch_assoc();
            $total_rewards = (int)$rewards_count_data['total'];
            debug_log("Total rewards earned: $total_rewards");
            $rewards_count_stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        debug_log("✅ Transaction committed successfully");
        
        $response_data = [
            'attempt_id' => $attempt_id,
            'books_completed' => $books_count,
            'average_score' => $average_score,
            'rewards_earned' => $total_rewards,
            'new_rewards' => $new_rewards,
            'all_rewards' => $rewards_earned,
            'user_name' => $user_data['full_name'],
            'book_title' => $book_data['title'],
            'passed' => $score_percentage >= 70,
            'is_reattempt' => $is_reattempt
        ];
        
        debug_log("Response data: " . print_r($response_data, true));
        debug_log("=== Quiz Save Successful ===");
        
        ob_clean();
        json_response(true, 'Quiz result saved successfully', $response_data);
    } else {
        $error_msg = $stmt->error;
        $stmt->close();
        $conn->rollback();
        ob_clean();
        
        debug_log("ERROR: Failed to insert quiz attempt - $error_msg");
        
        if (strpos($error_msg, 'foreign key constraint') !== false) {
            json_response(false, 'Database error: Invalid user or book reference. Please login again.');
        } else {
            json_response(false, 'Failed to save quiz result: ' . $error_msg);
        }
    }
    
} catch (Exception $e) {
    if (isset($stmt)) $stmt->close();
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    ob_clean();
    debug_log("EXCEPTION: " . $e->getMessage());
    json_response(false, 'Database error: ' . $e->getMessage());
}
?>
