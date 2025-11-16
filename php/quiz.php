<?php
session_start();

// Clear any error flags
unset($_SESSION['_clear_on_next_load']);

require_once 'config.php';

check_user_type(['student']);

$scanned_book = $_SESSION['scanned_book'] ?? null;

if (!$scanned_book) {
    header("Location: index.php");
    exit();
}

// Extract book details
$book_title = $scanned_book['title'];
$book_author = $scanned_book['author'];
$book_id = $scanned_book['bookId'];

// Safely get session values WITH VALIDATION
$user_id = $_SESSION['user_id'] ?? 0;
$student_name = $_SESSION['full_name'] ?? 'Unknown';
$student_id = $_SESSION['student_id'] ?? 'N/A';

// SECURITY FIX: Verify user exists and is active
if ($user_id <= 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user_verify_sql = "SELECT user_id, full_name, status FROM users WHERE user_id = ? AND user_type = 'student'";
$user_verify_stmt = $conn->prepare($user_verify_sql);
$user_verify_stmt->bind_param("i", $user_id);
$user_verify_stmt->execute();
$user_verify_result = $user_verify_stmt->get_result();

if ($user_verify_result->num_rows === 0) {
    $user_verify_stmt->close();
    session_destroy();
    die("Invalid user session. Please <a href='login.php'>login again</a>.");
}

$user_verify_data = $user_verify_result->fetch_assoc();
if ($user_verify_data['status'] !== 'active') {
    $user_verify_stmt->close();
    session_destroy();
    die("Your account is inactive. Please contact the administrator.");
}
$user_verify_stmt->close();

// FIXED: Count books completed - ANY attempt counts as completing a book
$progress_sql = "SELECT 
                    COUNT(DISTINCT qa.book_id) as books_completed,
                    COALESCE(AVG(qa.score_percentage), 0) as average_score
                 FROM quiz_attempts qa
                 WHERE qa.user_id = ?";
$progress_stmt = $conn->prepare($progress_sql);
$progress_stmt->bind_param("i", $user_id);
$progress_stmt->execute();
$progress_result = $progress_stmt->get_result();
$progress_data = $progress_result->fetch_assoc();

$books_completed = (int)($progress_data['books_completed'] ?? 0);
$average_score = round((float)($progress_data['average_score'] ?? 0), 1);

$progress_stmt->close();

// Get rewards earned count
$rewards_count_sql = "SELECT COUNT(DISTINCT reward_id) as total FROM user_rewards WHERE user_id = ?";
$rewards_count_stmt = $conn->prepare($rewards_count_sql);
$rewards_count_stmt->bind_param("i", $user_id);
$rewards_count_stmt->execute();
$rewards_count_result = $rewards_count_stmt->get_result();
$rewards_count_data = $rewards_count_result->fetch_assoc();
$rewards_earned = (int)($rewards_count_data['total'] ?? 0);
$rewards_count_stmt->close();

$rewards_sql = "SELECT r.reward_id, r.reward_name, r.reward_description, r.books_required, r.reward_type,
                       CASE WHEN ur.user_reward_id IS NOT NULL THEN TRUE ELSE FALSE END as is_earned
                FROM rewards r
                LEFT JOIN user_rewards ur ON r.reward_id = ur.reward_id AND ur.user_id = ?
                WHERE r.is_active = TRUE
                ORDER BY r.books_required ASC";
$rewards_stmt = $conn->prepare($rewards_sql);
$rewards_stmt->bind_param("i", $user_id);
$rewards_stmt->execute();
$rewards_result = $rewards_stmt->get_result();
$all_rewards = [];
while ($row = $rewards_result->fetch_assoc()) {
    $all_rewards[] = $row;
}
$rewards_stmt->close();

$questions_sql = "SELECT question_id, question_text, option_a, option_b, option_c, option_d, correct_answer 
                  FROM quiz_questions 
                  WHERE book_id = ? 
                  ORDER BY question_id ASC";
$stmt = $conn->prepare($questions_sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$questions_result = $stmt->get_result();

$questions = [];
while ($row = $questions_result->fetch_assoc()) {
    $questions[] = $row;
}
$stmt->close();

if (empty($questions)) {
    die("No quiz questions found for this book.");
}

$total_questions = count($questions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - Library Hub Tambo</title>
    <!-- Add favicon for book icon -->
    <link rel="icon" type="image/svg+xml" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.2/svgs/solid/book.svg">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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
            max-width: 700px;
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

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .progress-bar {
            background: #e9ecef;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .progress-fill {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            transition: width 0.3s ease;
        }

        .question {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .question h4 {
            margin-bottom: 15px;
            color: #333;
            font-size: 1.1em;
        }

        .options label {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .options label:hover {
            background: #e9ecef;
        }

        .options input[type="radio"] {
            margin-right: 10px;
            cursor: pointer;
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

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-navigation {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-nav-left {
            margin-right: auto;
        }

        .btn-nav-right {
            margin-left: auto;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
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

        .stats {
            display: flex;
            justify-content: space-around;
            margin: 30px 0;
            flex-wrap: wrap;
            gap: 20px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }

        .rewards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .reward-item {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .reward-item.earned {
            background: #d4edda;
            border-color: #c3e6cb;
        }

        .reward-item h4 {
            margin-bottom: 10px;
            color: #856404;
        }

        .reward-item.earned h4 {
            color: #155724;
        }

        .link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .link:hover {
            text-decoration: underline;
        }

        .question-counter {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 8px;
        }

        .question-counter strong {
            color: #667eea;
            font-size: 1.1em;
        }

        .question-nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            gap: 5px;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .question-nav-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .question-nav-btn:hover {
            background: #e9ecef;
            transform: scale(1.05);
        }

        .question-nav-btn.answered {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .question-nav-btn.current {
            background: #667eea;
            color: white;
            border-color: #667eea;
            font-weight: bold;
        }

        .toggle-nav-btn {
            background: #6c757d;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .toggle-nav-btn:hover {
            background: #5a6268;
        }

        .question-nav-container {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .question-nav-container.open {
            max-height: 500px;
        }

        .quiz-summary {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .quiz-summary h4 {
            color: #856404;
            margin-bottom: 10px;
        }

        .summary-stats {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
        }

        .summary-stat {
            text-align: center;
        }

        .summary-stat .number {
            font-size: 1.5em;
            font-weight: bold;
            color: #856404;
        }

        .summary-stat .label {
            font-size: 0.9em;
            color: #666;
        }

        @media (max-width: 768px) {
            .question-nav-grid {
                grid-template-columns: repeat(auto-fill, minmax(35px, 1fr));
            }

            .question-nav-btn {
                width: 35px;
                height: 35px;
                font-size: 0.8em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Library Hub Reading System</h1>
            <p id="welcomeMsg">Welcome, <?php echo htmlspecialchars($student_name); ?>!</p>
            <a href="logout.php" class="btn-logout">🚪 Logout</a>
        </div>

        <div class="card">
            <div style="background: #f0f4ff; padding: 20px; border-radius: 10px; margin-bottom: 20px;" id="progressSection">
                <h3 style="color: #667eea; margin-bottom: 15px;">📊 Your Reading Progress</h3>
                <div class="stats">
                    <div>
                        <div class="stat-number" id="booksCompletedStat"><?php echo $books_completed; ?></div>
                        <p>Books Completed</p>
                    </div>
                    <div>
                        <div class="stat-number" id="averageScoreStat"><?php echo $average_score; ?>%</div>
                        <p>Average Score</p>
                    </div>
                    <div>
                        <div class="stat-number" id="rewardsEarnedStat"><?php echo $rewards_earned; ?></div>
                        <p>Rewards Earned</p>
                    </div>
                </div>

                <h4 style="color: #667eea; margin-top: 20px; margin-bottom: 10px;">🏆 Available Rewards</h4>
                <div class="rewards" id="rewardsContainer">
                    <?php foreach ($all_rewards as $reward): ?>
                        <div class="reward-item <?php echo $reward['is_earned'] ? 'earned' : ''; ?>">
                            <h4><?php echo htmlspecialchars($reward['reward_name']); ?></h4>
                            <p style="font-size: 0.9em; margin-bottom: 8px;"><?php echo htmlspecialchars($reward['reward_description']); ?></p>
                            <p style="font-size: 0.85em; color: #666;">
                                <?php 
                                if ($reward['is_earned']) {
                                    echo '✅ Earned!';
                                } else {
                                    echo 'Read ' . $reward['books_required'] . ' books to unlock';
                                }
                                ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <h2>📖 Book Comprehension Quiz</h2>
            <div id="quizContent">
                <div class="alert">
                    <strong>Book:</strong> "<?php echo htmlspecialchars($book_title); ?>" by <?php echo htmlspecialchars($book_author); ?>
                </div>
                
                <div class="question-counter">
                    <strong>Question <span id="currentQuestion">1</span> of <span id="totalQuestions"><?php echo $total_questions; ?></span></strong>
                    <button class="toggle-nav-btn" onclick="toggleQuestionNav()">
                        <span id="navToggleText">📋 Show All Questions</span>
                    </button>
                </div>

                <div class="question-nav-container" id="questionNavContainer">
                    <div class="quiz-summary">
                        <h4>📊 Quiz Overview</h4>
                        <div class="summary-stats">
                            <div class="summary-stat">
                                <div class="number" id="totalQuestionsCount"><?php echo $total_questions; ?></div>
                                <div class="label">Total Questions</div>
                            </div>
                            <div class="summary-stat">
                                <div class="number" id="answeredCount">0</div>
                                <div class="label">Answered</div>
                            </div>
                            <div class="summary-stat">
                                <div class="number" id="unansweredCount"><?php echo $total_questions; ?></div>
                                <div class="label">Remaining</div>
                            </div>
                        </div>
                    </div>
                    <p style="font-size: 0.9em; color: #666; margin-bottom: 10px;">
                        💚 Green = Answered | 🔵 Blue = Current | ⚪ White = Not Answered
                    </p>
                    <div class="question-nav-grid" id="questionNavGrid">
                        <!-- Question navigation buttons will be generated here -->
                    </div>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                </div>

                <div class="question" id="questionContainer">
                    <!-- Questions will be loaded here by JavaScript -->
                </div>

                <div class="btn-navigation">
                    <button class="btn btn-secondary btn-nav-left" id="prevBtn" onclick="previousQuestion()" style="display: none;">
                        ← Previous Question
                    </button>
                    <button class="btn btn-nav-right" id="nextBtn" onclick="nextQuestion()">
                        Next Question →
                    </button>
                    <button class="btn btn-nav-right" id="submitBtn" onclick="submitQuiz()" style="display: none;">
                        Submit Quiz
                    </button>
                </div>
            </div>

            <div id="quizResults" style="display: none;">
                <h3>🎉 Quiz Completed!</h3>
                <div class="stats">
                    <div>
                        <div class="stat-number" id="finalScore">0/0</div>
                        <p>Final Score</p>
                    </div>
                    <div>
                        <div class="stat-number" id="percentage">0%</div>
                        <p>Percentage</p>
                    </div>
                </div>

                <div id="rewardsEarnedSection" style="margin-top: 20px; display: none;">
                    <h4 style="color: #667eea; margin-bottom: 15px;">🎉 New Rewards Earned!</h4>
                    <div class="rewards" id="newRewardsContainer">
                        <!-- New rewards will be added here dynamically -->
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;">
                    <button class="btn" onclick="window.location.href='clear-scanned-book.php'">📚 Scan Another Book</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const questionsData = <?php echo json_encode($questions); ?>;
        const PHP_BOOK_ID = <?php echo $book_id; ?>;
        const PHP_USER_ID = <?php echo $user_id; ?>;
        const PHP_BOOK_TITLE = '<?php echo addslashes($book_title); ?>';
        const PHP_TOTAL_QUESTIONS = <?php echo $total_questions; ?>;

        let currentQuestionIndex = 0;
        let score = 0;
        let userAnswers = new Array(questionsData.length).fill(null);
        let quizStartTime = Date.now();

        function initializeQuestionNavigation() {
            const navGrid = document.getElementById('questionNavGrid');
            navGrid.innerHTML = '';
            
            questionsData.forEach((_, index) => {
                const btn = document.createElement('button');
                btn.className = 'question-nav-btn';
                btn.textContent = index + 1;
                btn.onclick = () => jumpToQuestion(index);
                navGrid.appendChild(btn);
            });
            
            updateQuestionNavigation();
        }

        function updateQuestionNavigation() {
            const buttons = document.querySelectorAll('.question-nav-btn');
            
            buttons.forEach((btn, index) => {
                btn.classList.remove('answered', 'current');
                
                if (index === currentQuestionIndex) {
                    btn.classList.add('current');
                } else if (userAnswers[index] !== null) {
                    btn.classList.add('answered');
                }
            });
            
            // Update summary stats
            const answeredCount = userAnswers.filter(a => a !== null).length;
            document.getElementById('answeredCount').textContent = answeredCount;
            document.getElementById('unansweredCount').textContent = questionsData.length - answeredCount;
        }

        function toggleQuestionNav() {
            const container = document.getElementById('questionNavContainer');
            const toggleText = document.getElementById('navToggleText');
            
            container.classList.toggle('open');
            
            if (container.classList.contains('open')) {
                toggleText.textContent = '📋 Hide Questions';
            } else {
                toggleText.textContent = '📋 Show All Questions';
            }
        }

        function jumpToQuestion(index) {
            if (index < 0 || index >= questionsData.length) {
                return;
            }
            
            // Save current answer before jumping
            saveCurrentAnswer();
            
            currentQuestionIndex = index;
            loadQuestion();
        }

        function loadQuestion() {
            if (currentQuestionIndex >= questionsData.length || currentQuestionIndex < 0) {
                return;
            }

            const question = questionsData[currentQuestionIndex];
            const questionContainer = document.getElementById('questionContainer');
            
            // Get previously selected answer if exists
            const previousAnswer = userAnswers[currentQuestionIndex];
            
            let optionsHTML = `
                <h4>${currentQuestionIndex + 1}. ${question.question_text}</h4>
                <div class="options">
                    <label><input type="radio" name="answer" value="A" ${previousAnswer === 'A' ? 'checked' : ''}> ${question.option_a}</label>
                    <label><input type="radio" name="answer" value="B" ${previousAnswer === 'B' ? 'checked' : ''}> ${question.option_b}</label>
                    <label><input type="radio" name="answer" value="C" ${previousAnswer === 'C' ? 'checked' : ''}> ${question.option_c}</label>
                    <label><input type="radio" name="answer" value="D" ${previousAnswer === 'D' ? 'checked' : ''}> ${question.option_d}</label>
                </div>
            `;
            
            questionContainer.innerHTML = optionsHTML;
            document.getElementById('currentQuestion').textContent = currentQuestionIndex + 1;
            
            updateNavigationButtons();
            updateProgress();
            updateQuestionNavigation();
            
            // Scroll to top of question
            questionContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function updateNavigationButtons() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');

            // Show/hide previous button
            if (currentQuestionIndex === 0) {
                prevBtn.style.display = 'none';
            } else {
                prevBtn.style.display = 'inline-block';
            }

            // Show/hide next and submit buttons
            if (currentQuestionIndex === questionsData.length - 1) {
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'inline-block';
            } else {
                nextBtn.style.display = 'inline-block';
                submitBtn.style.display = 'none';
            }
        }

        function previousQuestion() {
            // Save current answer before going back
            saveCurrentAnswer();
            
            currentQuestionIndex--;
            if (currentQuestionIndex < 0) {
                currentQuestionIndex = 0;
            }
            
            loadQuestion();
        }

        function nextQuestion() {
            const selectedAnswer = document.querySelector('input[name="answer"]:checked');
            
            if (!selectedAnswer) {
                alert("Please select an answer before proceeding!");
                return;
            }
            
            // Save current answer
            saveCurrentAnswer();
            
            currentQuestionIndex++;
            
            if (currentQuestionIndex < questionsData.length) {
                loadQuestion();
            }
            
            updateProgress();
        }

        function saveCurrentAnswer() {
            const selectedAnswer = document.querySelector('input[name="answer"]:checked');
            
            if (selectedAnswer) {
                userAnswers[currentQuestionIndex] = selectedAnswer.value;
                updateQuestionNavigation();
            }
        }

        function updateProgress() {
            const answeredCount = userAnswers.filter(a => a !== null).length;
            const progress = (answeredCount / questionsData.length) * 100;
            document.getElementById('progressFill').style.width = progress + '%';
        }

        function submitQuiz() {
            // Save the last answer
            saveCurrentAnswer();
            
            // Check if all questions are answered
            const unanswered = userAnswers.filter(a => a === null).length;
            if (unanswered > 0) {
                const answeredCount = userAnswers.filter(a => a !== null).length;
                const confirmMsg = `⚠️ Quiz Submission Summary:\n\n` +
                                  `Total Questions: ${questionsData.length}\n` +
                                  `Answered: ${answeredCount}\n` +
                                  `Unanswered: ${unanswered}\n\n` +
                                  `Do you want to submit anyway?\n\n` +
                                  `Note: Unanswered questions will be marked as incorrect.`;
                
                if (!confirm(confirmMsg)) {
                    return;
                }
            }

            // Calculate score
            score = 0;
            const responses = [];
            
            questionsData.forEach((question, index) => {
                const userAnswer = userAnswers[index];
                const isCorrect = userAnswer === question.correct_answer;
                
                if (isCorrect) {
                    score++;
                }
                
                responses.push({
                    question_id: question.question_id,
                    user_answer: userAnswer || 'N/A',
                    is_correct: isCorrect
                });
            });

            const percentage = Math.round((score / questionsData.length) * 100);
            const timeTaken = Math.floor((Date.now() - quizStartTime) / 1000);

            const quizResult = {
                user_id: PHP_USER_ID,
                book_id: PHP_BOOK_ID,
                total_questions: questionsData.length,
                correct_answers: score,
                score_percentage: percentage,
                time_taken: timeTaken,
                responses: responses
            };

            console.log('[Quiz] Submitting quiz result:', quizResult);

            // Disable submit button to prevent double submission
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Submitting...';

            fetch('save-quiz-result.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                body: JSON.stringify(quizResult)
            })
            .then(response => {
                console.log('[Quiz] Response status:', response.status);
                
                return response.text().then(text => {
                    console.log('[Quiz] Raw response:', text);
                    
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('[Quiz] JSON parse error:', e);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }
                });
            })
            .then(data => {
                console.log('[Quiz] Parsed data:', data);
                
                if (data.success) {
                    // Update the display with new statistics
                    document.getElementById('finalScore').textContent = score + '/' + questionsData.length;
                    document.getElementById('percentage').textContent = percentage + '%';

                    // Update the progress stats at the top of the page
                    document.getElementById('booksCompletedStat').textContent = data.data.books_completed;
                    document.getElementById('averageScoreStat').textContent = data.data.average_score + '%';
                    document.getElementById('rewardsEarnedStat').textContent = data.data.rewards_earned;

                    // Animate the stats update
                    ['booksCompletedStat', 'averageScoreStat', 'rewardsEarnedStat'].forEach(id => {
                        const elem = document.getElementById(id);
                        elem.style.transition = 'all 0.5s ease';
                        elem.style.transform = 'scale(1.2)';
                        elem.style.color = '#28a745';
                        setTimeout(() => {
                            elem.style.transform = 'scale(1)';
                            elem.style.color = '#667eea';
                        }, 1000);
                    });

                    // Show new rewards if any
                    if (data.data.new_rewards && data.data.new_rewards.length > 0) {
                        const rewardsSection = document.getElementById('rewardsEarnedSection');
                        const rewardsContainer = document.getElementById('newRewardsContainer');
                        
                        rewardsContainer.innerHTML = '';
                        data.data.new_rewards.forEach(reward => {
                            const rewardDiv = document.createElement('div');
                            rewardDiv.className = 'reward-item earned';
                            rewardDiv.innerHTML = `
                                <h4>🎁 ${reward}</h4>
                                <p>Just Unlocked!</p>
                            `;
                            rewardsContainer.appendChild(rewardDiv);
                        });
                        
                        rewardsSection.style.display = 'block';
                    }

                    // Hide quiz content and show results
                    document.getElementById('quizContent').style.display = 'none';
                    document.getElementById('quizResults').style.display = 'block';
                    document.getElementById('progressFill').style.width = '100%';

                    // Build success message
                    let successMsg = `✅ Quiz Submitted Successfully!\n\n`;
                    successMsg += `Book: ${PHP_BOOK_TITLE}\n`;
                    successMsg += `Your Score: ${score}/${questionsData.length} (${percentage}%)\n`;
                    
                    if (data.data.is_reattempt) {
                        successMsg += `\n📝 This was a re-attempt!\n`;
                        if (percentage >= 70) {
                            successMsg += `Great job improving your score! Your new score has been recorded.\n`;
                        } else {
                            successMsg += `Keep practicing! Your score has been updated.\n`;
                        }
                    } else {
                        successMsg += `\n🎉 Book Completed!\n`;
                        successMsg += `This book now counts toward your Books Completed.\n`;
                        if (percentage >= 70) {
                            successMsg += `Excellent work on passing the quiz!\n`;
                        } else {
                            successMsg += `You can re-attempt this quiz to improve your score.\n`;
                        }
                    }
                    
                    successMsg += `\n📊 Your Progress Has Been Updated!\n`;
                    successMsg += `• Books Completed: ${data.data.books_completed}\n`;
                    successMsg += `• Average Score: ${data.data.average_score}%\n`;
                    successMsg += `• Total Rewards: ${data.data.rewards_earned}\n`;
                    
                    if (data.data.new_rewards && data.data.new_rewards.length > 0) {
                        successMsg += `\n🏆 NEW REWARDS EARNED:\n`;
                        data.data.new_rewards.forEach(reward => {
                            successMsg += `• ${reward}\n`;
                        });
                    }
                    
                    alert(successMsg);
                    
                    // Scroll to top to show updated progress and results
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Quiz';
                    alert('❌ Error saving quiz result: ' + data.message + '\n\nPlease try again or contact support if the problem persists.');
                    console.error('[Quiz] Error:', data);
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Quiz';
                console.error('[Quiz] Fetch error:', error);
                alert('❌ Failed to save quiz result. Please check your internet connection and try again.\n\nError: ' + error.message);
            });
        }

        // Initialize question navigation and load first question on page load
        initializeQuestionNavigation();
        loadQuestion();

        document.getElementById('scanAnotherBtn')?.addEventListener('click', () => {
            fetch('clear-scanned-book.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.replace('index.php');
                    } else {
                        alert('Failed to reset quiz session.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error while clearing session.');
                });
        });
    </script>
</body>
</html>
