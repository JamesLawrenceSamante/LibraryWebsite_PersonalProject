<?php
session_start();
require_once 'connection.php';

$welcome_message = '';
$instant_welcome = true;

if ($instant_welcome) {
    $welcome_message = "Welcome to New Era University Library! Please login to continue.";
}

if (isset($_GET['welcome']) && !empty($_GET['welcome'])) {
    $student_name = htmlspecialchars($_GET['welcome']);
    $welcome_message = "Welcome to the library, " . $student_name . "! Your entry has been approved.";
}

$message = '';
$message_type = '';

$check_column = mysqli_query($conn, "SHOW COLUMNS FROM visitor_logs LIKE 'approval_status'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($conn, "ALTER TABLE visitor_logs 
        ADD COLUMN approval_status ENUM('pending', 'approved', 'declined') DEFAULT 'pending' AFTER status,
        ADD COLUMN approved_by INT NULL AFTER approval_status,
        ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
        ADD COLUMN decline_reason TEXT NULL AFTER approved_at");
}

if (isset($_POST['visitor_login'])) {
    if (!isset($_POST['identifier']) || empty($_POST['identifier'])) {
        $message = "Please enter your email or student ID.";
        $message_type = "error";
    } else {
        $identifier = mysqli_real_escape_string($conn, $_POST['identifier']);
        $reason = isset($_POST['reason']) ? mysqli_real_escape_string($conn, $_POST['reason']) : '';

        $user_category = isset($_POST['user_category']) ? mysqli_real_escape_string($conn, $_POST['user_category']) : 'student';

        if ($user_category == 'student' && empty($reason)) {
            $message = "Please select a reason for your visit.";
            $message_type = "error";
        } else {
            $check_blocked = mysqli_query($conn, "SELECT * FROM blocked_users WHERE email = '$identifier' AND is_active = 1");

            if ($check_blocked && mysqli_num_rows($check_blocked) > 0) {
                $message = "Access Denied: You are blocked from using the library.";
                $message_type = "error";
            } else {
                $user_query = "SELECT * FROM users WHERE email = '$identifier' OR student_id = '$identifier' OR username = '$identifier'";
                $user_result = mysqli_query($conn, $user_query);

                if ($user_result && mysqli_num_rows($user_result) > 0) {
                    $user = mysqli_fetch_assoc($user_result);

                    if ($user_category == 'admin') {
                        if ($user['user_type'] != 'admin' && $user['user_type'] != 'staff') {
                            $message = "Access Denied: You are not authorized as an administrator.";
                            $message_type = "error";
                        } else {
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['user_type'] = $user['user_type'];
                            $_SESSION['full_name'] = $user['full_name'];
                            header("Location: admin_dashboard.php");
                            exit();
                        }
                    } else {
                        $entry_time = date('Y-m-d H:i:s');
                        $user_type = ($user['user_type'] == 'staff' || $user['user_type'] == 'admin') ? 'faculty' : 'student';
                        $program = $user['department'] ?? 'N/A';

                        $log_sql = "INSERT INTO visitor_logs (user_id, email, full_name, user_type, program_department, reason, entry_time, status) 
                                    VALUES ('{$user['user_id']}', '{$user['email']}', '{$user['full_name']}', '$user_type', '$program', '$reason', '$entry_time', 'pending')";

                        if (mysqli_query($conn, $log_sql)) {
                            $log_id = mysqli_insert_id($conn);

                            mysqli_query($conn, "UPDATE visitor_logs SET approval_status = 'pending' WHERE log_id = '$log_id'");

                            $student_name = $user['full_name'];
                            $message = "pending_notification:" . $student_name;
                            $message_type = "pending";
                        } else {
                            $message = "Error: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    }
                } else {
                    $message = "Invalid credentials. Please check your username and try again.";
                    $message_type = "error";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>NEU Library Entry</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #fff5f7 0%, #ffe4e8 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            position: relative;
        }

        .container {
            background: white;
            width: 400px;
            margin: auto;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(255, 182, 193, 0.3);
            border: 1px solid rgba(255, 192, 203, 0.3);
            position: relative;
            z-index: 1;
        }

        h1 {
            font-size: 24px;
            color: #d44e6c;
            margin-bottom: 5px;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(255, 182, 193, 0.2);
        }

        .subtitle {
            color: #b86b7c;
            font-size: 14px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ffe4e8;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #b85c7a;
            font-size: 14px;
            font-weight: 600;
        }

        input,
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ffe4e8;
            border-radius: 12px;
            font-size: 14px;
            background: #fff9fa;
            transition: all 0.3s ease;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #ffb6c1;
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 182, 193, 0.2);
        }

        .notification-center {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            display: none;
            pointer-events: none;
        }

        .notification-center.active {
            display: block;
        }

        .notification-box {
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            min-width: 300px;
            max-width: 450px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            animation: boxPop 0.3s ease-out;
        }

        @keyframes boxPop {
            0% {
                opacity: 0;
                transform: scale(0.9);
            }

            50% {
                transform: scale(1.02);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes boxFade {
            0% {
                opacity: 1;
                transform: scale(1);
            }

            100% {
                opacity: 0;
                transform: scale(0.9);
            }
        }

        .notification-box.fade-out {
            animation: boxFade 0.2s ease-out forwards;
        }

        .error {
            background: #fff0f0;
            color: #c44f4f;
            padding: 12px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: left;
            border-left: 4px solid #ff8a8a;
        }

        .success {
            background: #f0fff0;
            color: #6b8e6b;
            padding: 12px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: left;
            border-left: 4px solid #98d698;
        }

        .pending {
            background: #fff0e6;
            color: #c49b6b;
            padding: 12px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: left;
            border-left: 4px solid #ffb77c;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #5d3a4a;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin-top: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 154, 158, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 154, 158, 0.5);
            background: linear-gradient(135deg, #ff8a8e 0%, #febfdf 100%);
        }

        button:active {
            transform: translateY(0);
        }

        .admin-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }

        .admin-link a {
            color: #b86b7c;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .admin-link a:hover {
            color: #d44e6c;
            text-decoration: underline;
        }

        .note {
            background: #fff5f9;
            color: #b85c7a;
            padding: 12px;
            border-radius: 12px;
            font-size: 13px;
            margin-top: 20px;
            text-align: center;
            border: 1px dashed #ffb6c1;
        }

        .category-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            padding: 5px;
            background: #fff5f7;
            border-radius: 50px;
            border: 1px solid #ffe4e8;
        }

        .category-option {
            flex: 1;
            text-align: center;
        }

        .category-option input[type="radio"] {
            display: none;
        }

        .category-option label {
            display: block;
            padding: 10px 15px;
            background: transparent;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0;
            font-weight: 500;
            color: #b86b7c;
        }

        .category-option input[type="radio"]:checked+label {
            background: linear-gradient(135deg, #ffdde1 0%, #fecfef 100%);
            color: #b85c7a;
            box-shadow: 0 3px 10px rgba(255, 182, 193, 0.4);
        }

        .info-box {
            background: #fff5f9;
            color: #b85c7a;
            padding: 12px;
            border-radius: 12px;
            font-size: 13px;
            margin-top: 15px;
            text-align: left;
            border-left: 4px solid #ffb6c1;
        }

        .logo {
            position: absolute;
            top: 40px;
            left: 40px;
            width: 150px;
            height: auto;
            transition: all 0.5s ease;
            filter: drop-shadow(0 5px 10px rgba(255, 182, 193, 0.3));
            z-index: 1001;
        }

        .logo:hover {
            transform: scale(1.1) rotate(2deg);
        }

        ::placeholder {
            color: #dba1b0;
            opacity: 0.7;
        }

        select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23b85c7a'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 40px;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #ffe4e8;
        }

        ::-webkit-scrollbar-thumb {
            background: #ffb6c1;
            border-radius: 20px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #ff9aa9;
        }
    </style>
</head>
<img src="images/images.png" alt="NEU Library Logo" class="logo" />

<body>
    <div id="notificationCenter" class="notification-center">
        <div id="notificationBox" class="notification-box">
            <span id="notificationMessage"></span>
        </div>
    </div>

    <div class="container">
        <h1>New Era University Library</h1>
        <div class="subtitle">Welcome to the official NEU Library System</div>

        <?php if ($message): ?>
            <?php if (strpos($message, 'pending_notification:') === 0): ?>
                <?php $student_name = substr($message, 19); ?>
                <script>
                    window.onload = function () {
                        showCenterNotification("Welcome to NEU Library, <?php echo $student_name; ?>! Please wait for admin approval before entering.");
                    };
                </script>
            <?php else: ?>
                <div class="<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="category-selector">
                <div class="category-option">
                    <input type="radio" name="user_category" id="student" value="student" checked>
                    <label for="student">Student</label>
                </div>
                <div class="category-option">
                    <input type="radio" name="user_category" id="admin" value="admin">
                    <label for="admin">Admin</label>
                </div>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="identifier" placeholder="Username" required>
            </div>

            <div class="form-group" id="reason-group">
                <label>Reason for Visit</label>
                <select name="reason">
                    <option value="">Select a reason</option>
                    <option value="reading">Reading</option>
                    <option value="researching">Researching</option>
                    <option value="computer">Use Computer</option>
                    <option value="meeting">Meeting</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <button type="submit" name="visitor_login" id="submit-btn">Log In</button>
        </form>

        <div class="note" id="student-note">
            Students: Your entry will be pending admin approval
        </div>

        <div class="info-box" id="admin-note" style="display: none;">
            Administrators: You will be redirected to the admin dashboard after successful login
        </div>
    </div>

    <script>
        function showCenterNotification(message) {
            const center = document.getElementById('notificationCenter');
            const box = document.getElementById('notificationBox');
            const messageSpan = document.getElementById('notificationMessage');

            messageSpan.textContent = message;
            center.classList.add('active');

            setTimeout(function () {
                box.classList.add('fade-out');

                setTimeout(function () {
                    center.classList.remove('active');
                    box.classList.remove('fade-out');
                }, 200);
            }, 1000);
        }

        window.addEventListener('load', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const welcomeParam = urlParams.get('welcome');

            showCenterNotification("Welcome to New Era University Library! Please login to continue.");

            if (welcomeParam) {
                showCenterNotification('Welcome to the library, ' + decodeURIComponent(welcomeParam) + '! Your entry has been approved.');
            }

            const statusParam = urlParams.get('status');
            const nameParam = urlParams.get('name');
            const reasonParam = urlParams.get('reason');

            if (statusParam === 'declined' && nameParam) {
                let message = 'Sorry, ' + decodeURIComponent(nameParam) + '. Your entry request has been declined.';
                if (reasonParam) {
                    message += ' Reason: ' + decodeURIComponent(reasonParam);
                }
                showCenterNotification(message);
            }
        });

        const studentRadio = document.getElementById('student');
        const adminRadio = document.getElementById('admin');
        const reasonGroup = document.getElementById('reason-group');
        const reasonSelect = document.querySelector('select[name="reason"]');
        const submitBtn = document.getElementById('submit-btn');
        const studentNote = document.getElementById('student-note');
        const adminNote = document.getElementById('admin-note');

        function updateUI() {
            if (adminRadio.checked) {
                reasonGroup.style.display = 'none';
                reasonSelect.required = false;
                submitBtn.textContent = 'Login as Admin';
                studentNote.style.display = 'none';
                adminNote.style.display = 'block';
            } else {
                reasonGroup.style.display = 'block';
                reasonSelect.required = true;
                submitBtn.textContent = 'Log In';
                studentNote.style.display = 'block';
                adminNote.style.display = 'none';
            }
        }

        studentRadio.addEventListener('change', updateUI);
        adminRadio.addEventListener('change', updateUI);

        updateUI();
    </script>
</body>

</html>
