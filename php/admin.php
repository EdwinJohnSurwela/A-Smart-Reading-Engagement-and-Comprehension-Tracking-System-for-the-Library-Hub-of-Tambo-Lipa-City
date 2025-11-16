<?php
require_once 'config.php';

// Check if user is logged in as admin
check_user_type(['admin']);

// Fetch system statistics
$stats = [];

// Total students
$sql = "SELECT COUNT(*) as total FROM users WHERE user_type = 'student' AND status = 'active'";
$result = $conn->query($sql);
$stats['total_students'] = $result->fetch_assoc()['total'];

// Total books read (unique book-student combinations)
$sql = "SELECT COUNT(DISTINCT CONCAT(user_id, '-', book_id)) as total FROM quiz_attempts WHERE score_percentage >= 70";
$result = $conn->query($sql);
$stats['books_read'] = $result->fetch_assoc()['total'];

// Active readers (students who completed at least one quiz)
$sql = "SELECT COUNT(DISTINCT user_id) as total FROM quiz_attempts";
$result = $conn->query($sql);
$stats['active_readers'] = $result->fetch_assoc()['total'];

// Total books available
$sql = "SELECT COUNT(*) as total FROM books";
$result = $conn->query($sql);
$stats['books_available'] = $result->fetch_assoc()['total'];

// Average quiz score
$sql = "SELECT AVG(score_percentage) as avg_score FROM quiz_attempts";
$result = $conn->query($sql);
$stats['average_score'] = round($result->fetch_assoc()['avg_score'], 1);

// Total quizzes taken
$sql = "SELECT COUNT(*) as total FROM quiz_attempts";
$result = $conn->query($sql);
$stats['total_quizzes'] = $result->fetch_assoc()['total'];

// Pen rewards (10 books)
$sql = "SELECT COUNT(*) as total FROM user_rewards WHERE reward_id = 1";
$result = $conn->query($sql);
$stats['pens_given'] = $result->fetch_assoc()['total'];

// Notebook rewards (25 books)
$sql = "SELECT COUNT(*) as total FROM user_rewards WHERE reward_id = 2";
$result = $conn->query($sql);
$stats['notebooks_given'] = $result->fetch_assoc()['total'];

// Recent activity
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
$recent_activity = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Library Hub Tambo</title>
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
            margin-top: 10px;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Library Hub Reading System</h1>
            <p>Admin Dashboard - Tambo, Lipa City</p>
            <div style="margin-top: 10px;">
                <span style="color: white;">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
                <a href="logout.php" class="btn-logout">🚪 Logout</a>
            </div>
        </div>

        <div class="card">
            <div class="dashboard-header">
                <h2>👨‍💼 Admin Dashboard</h2>
            </div>

            <div class="dashboard">
                <div class="dashboard-card">
                    <h3>📊 System Overview</h3>
                    <div class="stats">
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['total_students']); ?></div>
                            <p>Total Students</p>
                        </div>
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['books_read']); ?></div>
                            <p>Books Read</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <h3>📚 Library Statistics</h3>
                    <div class="stats">
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['active_readers']); ?></div>
                            <p>Active Readers</p>
                        </div>
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['books_available']); ?></div>
                            <p>Books Available</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <h3>🎯 Quiz Performance</h3>
                    <div class="stats">
                        <div>
                            <div class="stat-number"><?php echo $stats['average_score']; ?>%</div>
                            <p>Average Score</p>
                        </div>
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['total_quizzes']); ?></div>
                            <p>Quizzes Taken</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <h3>🏆 Rewards Distributed</h3>
                    <div class="stats">
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['pens_given']); ?></div>
                            <p>Pens Given</p>
                        </div>
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['notebooks_given']); ?></div>
                            <p>Notebooks Given</p>
                        </div>
                    </div>
                </div>
            </div>

            <h3>📋 Recent Activity</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Student ID</th>
                        <th>Book</th>
                        <th>Quiz Score</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_activity->num_rows > 0): ?>
                        <?php while ($row = $recent_activity->fetch_assoc()): ?>
                            <?php
                                $status = '✅ Completed';
                                if ($row['score_percentage'] == 100) {
                                    $status = '🏆 Perfect Score';
                                } else if ($row['score_percentage'] < 70) {
                                    $status = '⚠️ Needs Help';
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['formatted_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['book_title']); ?></td>
                                <td><?php echo $row['correct_answers']; ?>/<?php echo $row['total_questions']; ?> (<?php echo round($row['score_percentage']); ?>%)</td>
                                <td><?php echo $status; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999;">No quiz activity yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
