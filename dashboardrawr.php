<?php
session_start();
require_once 'connection.php';

$check_column = mysqli_query($conn, "SHOW COLUMNS FROM visitor_logs LIKE 'approval_status'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($conn, "ALTER TABLE visitor_logs 
        ADD COLUMN approval_status ENUM('pending', 'approved', 'declined') DEFAULT 'pending' AFTER status,
        ADD COLUMN approved_by INT NULL AFTER approval_status,
        ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
        ADD COLUMN decline_reason TEXT NULL AFTER approved_at");
}

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS blocked_users (
        block_id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100),
        full_name VARCHAR(100),
        reason TEXT,
        blocked_at DATETIME,
        is_active BOOLEAN DEFAULT TRUE
    )
");

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'today';
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

switch ($filter) {
    case 'today':
        $date_condition = "DATE(entry_time) = CURDATE() AND approval_status = 'approved'";
        $filter_text = "Today";
        break;
    case 'week':
        $date_condition = "YEARWEEK(entry_time, 1) = YEARWEEK(CURDATE(), 1) AND approval_status = 'approved'";
        $filter_text = "This Week";
        break;
    case 'month':
        $date_condition = "MONTH(entry_time) = MONTH(CURDATE()) AND YEAR(entry_time) = YEAR(CURDATE()) AND approval_status = 'approved'";
        $filter_text = "This Month";
        break;
    case 'custom':
        $date_condition = "DATE(entry_time) BETWEEN '$start' AND '$end' AND approval_status = 'approved'";
        $filter_text = "$start to $end";
        break;
    default:
        $date_condition = "DATE(entry_time) = CURDATE() AND approval_status = 'approved'";
        $filter_text = "Today";
}

$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE $date_condition"))['count'];
$active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE status = 'active' AND approval_status = 'approved'"))['count'];
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE approval_status = 'pending'"))['count'];
$today_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE DATE(entry_time) = CURDATE()"))['count'];

$reasons = mysqli_query($conn, "SELECT reason, COUNT(*) as count FROM visitor_logs WHERE $date_condition GROUP BY reason ORDER BY count DESC");
$types = mysqli_query($conn, "SELECT user_type, COUNT(*) as count FROM visitor_logs WHERE $date_condition GROUP BY user_type");

$pending_entries = mysqli_query($conn, "SELECT * FROM visitor_logs WHERE approval_status = 'pending' ORDER BY entry_time DESC");
$approved_active = mysqli_query($conn, "SELECT * FROM visitor_logs WHERE approval_status = 'approved' AND status = 'active' ORDER BY entry_time DESC");
$recent = mysqli_query($conn, "SELECT * FROM visitor_logs WHERE approval_status = 'approved' ORDER BY entry_time DESC LIMIT 20");

if (isset($_GET['approve'])) {
    $log_id = mysqli_real_escape_string($conn, $_GET['approve']);
    $admin_id = 1;

    $entry_query = mysqli_query($conn, "SELECT full_name FROM visitor_logs WHERE log_id = '$log_id'");
    $entry = mysqli_fetch_assoc($entry_query);
    $student_name = urlencode($entry['full_name']);

    mysqli_query($conn, "UPDATE visitor_logs SET approval_status = 'approved', status = 'active', approved_by = '$admin_id', approved_at = NOW() WHERE log_id = '$log_id'");

    header("Location: admin_dashboard.php?approved=1&name=$student_name");
    exit();
}

if (isset($_GET['decline'])) {
    $log_id = mysqli_real_escape_string($conn, $_GET['decline']);

    $entry_query = mysqli_query($conn, "SELECT full_name FROM visitor_logs WHERE log_id = '$log_id'");
    $entry = mysqli_fetch_assoc($entry_query);
    $student_name = urlencode($entry['full_name']);

    mysqli_query($conn, "UPDATE visitor_logs SET approval_status = 'declined' WHERE log_id = '$log_id'");

    header("Location: admin_dashboard.php?declined=1&name=$student_name");
    exit();
}

if (isset($_POST['block_from_pending'])) {
    $log_id = mysqli_real_escape_string($conn, $_POST['log_id']);
    $block_reason = mysqli_real_escape_string($conn, $_POST['block_reason']);

    $entry_query = mysqli_query($conn, "SELECT email, full_name FROM visitor_logs WHERE log_id = '$log_id'");
    $entry = mysqli_fetch_assoc($entry_query);

    if ($entry) {
        $email = mysqli_real_escape_string($conn, $entry['email']);
        $name = mysqli_real_escape_string($conn, $entry['full_name']);

        $check_blocked = mysqli_query($conn, "SELECT * FROM blocked_users WHERE email = '$email' AND is_active = 1");

        if (mysqli_num_rows($check_blocked) == 0) {
            mysqli_query($conn, "INSERT INTO blocked_users (email, full_name, reason, blocked_at, is_active) 
                                 VALUES ('$email', '$name', '$block_reason', NOW(), 1)");
        }

        mysqli_query($conn, "UPDATE visitor_logs SET approval_status = 'declined', decline_reason = 'User blocked' WHERE log_id = '$log_id'");
    }

    header("Location: admin_dashboard.php?blocked=1");
    exit();
}

if (isset($_POST['block_user'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $reason = mysqli_real_escape_string($conn, $_POST['block_reason']);

    $check_blocked = mysqli_query($conn, "SELECT * FROM blocked_users WHERE email = '$email' AND is_active = 1");

    if (mysqli_num_rows($check_blocked) == 0) {
        mysqli_query($conn, "INSERT INTO blocked_users (email, full_name, reason, blocked_at, is_active) 
                             VALUES ('$email', '$name', '$reason', NOW(), 1)");
    }

    header("Location: admin_dashboard.php?blocked=1");
    exit();
}

if (isset($_GET['unblock'])) {
    $block_id = mysqli_real_escape_string($conn, $_GET['unblock']);
    mysqli_query($conn, "UPDATE blocked_users SET is_active = 0 WHERE block_id = '$block_id'");

    header("Location: admin_dashboard.php?unblocked=1");
    exit();
}

$blocked = mysqli_query($conn, "SELECT * FROM blocked_users WHERE is_active = 1 ORDER BY blocked_at DESC");

$message = '';
$message_type = '';

if (isset($_GET['approved'])) {
    $message = "Entry approved successfully!";
    $message_type = "success";
}
if (isset($_GET['declined'])) {
    $message = "Entry declined.";
    $message_type = "warning";
}
if (isset($_GET['blocked'])) {
    $message = "User blocked successfully.";
    $message_type = "success";
}
if (isset($_GET['unblocked'])) {
    $message = "User unblocked successfully.";
    $message_type = "success";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard - NEU Library</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #fff5f7 0%, #ffe4e8 100%);
            padding: 20px;
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 15px 25px;
            border-radius: 50px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(255, 182, 193, 0.3);
            border: 1px solid rgba(255, 192, 203, 0.3);
        }

        .header h1 {
            font-size: 20px;
            font-weight: 600;
            color: #d44e6c;
            text-shadow: 2px 2px 4px rgba(255, 182, 193, 0.2);
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .back-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #5d3a4a;
            text-decoration: none;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 3px 8px rgba(255, 154, 158, 0.3);
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(255, 154, 158, 0.4);
            background: linear-gradient(135deg, #ff8a8e 0%, #febfdf 100%);
        }

        .filter-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 50px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: 0 10px 30px rgba(255, 182, 193, 0.2);
            border: 1px solid rgba(255, 192, 203, 0.3);
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #ffe4e8;
            background: white;
            border-radius: 25px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            color: #b85c7a;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: #fff5f7;
            border-color: #ffb6c1;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #5d3a4a;
            border-color: #ffb6c1;
            box-shadow: 0 3px 10px rgba(255, 182, 193, 0.4);
        }

        .date-input {
            padding: 8px 12px;
            border: 2px solid #ffe4e8;
            border-radius: 25px;
            font-size: 13px;
            background: #fff9fa;
            color: #b85c7a;
            transition: all 0.3s ease;
        }

        .date-input:focus {
            outline: none;
            border-color: #ffb6c1;
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 182, 193, 0.2);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(255, 182, 193, 0.2);
            border-left: 5px solid #ff9a9e;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 192, 203, 0.3);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(255, 154, 158, 0.3);
        }

        .stat-card.warning {
            border-left-color: #fbc2eb;
        }

        .stat-card.info {
            border-left-color: #a8edea;
        }

        .stat-card h3 {
            font-size: 13px;
            color: #b86b7c;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            background: linear-gradient(135deg, #d44e6c, #b85c7a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-number.warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-number.info {
            background: linear-gradient(135deg, #2e8b8b, #3d6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 11px;
            color: #b86b7c;
            font-weight: 500;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(255, 182, 193, 0.2);
            border: 1px solid rgba(255, 192, 203, 0.3);
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-header h2 {
            font-size: 16px;
            font-weight: 600;
            color: #d44e6c;
        }

        .badge {
            background: #fff5f7;
            padding: 4px 12px;
            border-radius: 25px;
            font-size: 11px;
            font-weight: 600;
            color: #b85c7a;
            border: 1px solid #ffb6c1;
        }

        .badge.pending {
            background: #fff0e6;
            color: #e67e22;
            border-color: #fbc2eb;
        }

        .badge.approved {
            background: #e8f5e9;
            color: #2e8b8b;
            border-color: #a8edea;
        }

        .badge.active {
            background: #e8f5e9;
            color: #2e8b8b;
            border-color: #a8edea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            text-align: left;
            padding: 8px;
            border-bottom: 2px solid #ffe4e8;
            font-weight: 600;
            color: #b86b7c;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #ffe4e8;
            color: #5d3a4a;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-approve {
            background: #e8f5e9;
            color: #2e8b8b;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            border: 1px solid #a8edea;
        }

        .btn-approve:hover {
            background: #d4edda;
            transform: translateY(-1px);
        }

        .btn-decline {
            background: #ffebee;
            color: #c44f4f;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            border: 1px solid #fbc2c2;
        }

        .btn-decline:hover {
            background: #ffdde1;
            transform: translateY(-1px);
        }

        .btn-block {
            background: #d32f2f;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            border: 1px solid #ff9e9e;
        }

        .btn-block:hover {
            background: #b71c1c;
            transform: translateY(-1px);
        }

        .btn-unblock {
            background: #e8f5e9;
            color: #2e8b8b;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            border: 1px solid #a8edea;
        }

        .btn-unblock:hover {
            background: #d4edda;
            transform: translateY(-1px);
        }

        .message {
            padding: 12px 20px;
            border-radius: 50px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(255, 154, 158, 0.2);
        }

        .message.success {
            background: linear-gradient(135deg, #e8f5e9, #d4edda);
            color: #2e8b8b;
            border: 1px solid #a8edea;
        }

        .message.warning {
            background: linear-gradient(135deg, #fff0e6, #ffe4d6);
            color: #e67e22;
            border: 1px solid #fbc2eb;
        }

        .message-close {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: inherit;
        }

        .tabs {
            display: flex;
            gap: 10px;
            background: white;
            padding: 10px;
            border-radius: 50px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            box-shadow: 0 10px 30px rgba(255, 182, 193, 0.2);
            border: 1px solid rgba(255, 192, 203, 0.3);
        }

        .tab {
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            background: #fff5f7;
            color: #b85c7a;
            transition: all 0.3s ease;
        }

        .tab:hover {
            background: #ffe4e8;
        }

        .tab.active {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #5d3a4a;
            box-shadow: 0 3px 10px rgba(255, 182, 193, 0.4);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .logo {
            position: absolute;
            top: 20px;
            right: 40px;
            width: 120px;
            height: auto;
            transition: all 0.5s ease;
            filter: drop-shadow(0 5px 10px rgba(255, 182, 193, 0.3));
        }

        .logo:hover {
            transform: scale(1.1) rotate(2deg);
        }

        .empty-state {
            color: #b86b7c;
            padding: 40px;
            text-align: center;
            background: #fff5f7;
            border-radius: 20px;
            font-size: 14px;
            border: 2px dashed #ffb6c1;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-email {
            font-size: 11px;
            color: #b86b7c;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(3px);
        }

        .modal-content {
            background: white;
            margin: 15% auto;
            padding: 25px;
            border-radius: 30px;
            width: 350px;
            box-shadow: 0 20px 40px rgba(255, 154, 158, 0.3);
            border: 1px solid rgba(255, 192, 203, 0.5);
        }

        .modal-content h3 {
            margin-bottom: 15px;
            color: #d44e6c;
            font-size: 18px;
        }

        .modal-content input {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 2px solid #ffe4e8;
            border-radius: 25px;
            font-size: 13px;
            background: #fff9fa;
        }

        .modal-content input:focus {
            outline: none;
            border-color: #ffb6c1;
            box-shadow: 0 0 0 4px rgba(255, 182, 193, 0.2);
        }

        .modal-content button {
            padding: 10px 20px;
            margin-right: 10px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .modal-submit {
            background: #d32f2f;
            color: white;
        }

        .modal-submit:hover {
            background: #b71c1c;
            transform: translateY(-1px);
        }

        .modal-cancel {
            background: #fff5f7;
            color: #b85c7a;
        }

        .modal-cancel:hover {
            background: #ffe4e8;
        }

        .progress-bar {
            background: #ffe4e8;
            height: 6px;
            border-radius: 3px;
            width: 100px;
        }

        .progress-fill {
            background: linear-gradient(90deg, #ff9a9e, #fecfef);
            height: 6px;
            border-radius: 3px;
        }

        ::-webkit-scrollbar {
            width: 10px;
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

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>
    <img src="images/images.png" alt="NEU Library Logo" class="logo" />

    <div class="main-container">
        <div class="header">
            <h1>LIBRARY STAFF DASHBOARD</h1>
            <div class="header-actions">
                <a href="visitor_login.php" class="back-btn">← Back to Entry</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
                <button class="message-close" onclick="this.parentElement.style.display='none'">×</button>
            </div>
        <?php endif; ?>

        <div class="filter-bar">
            <a href="?filter=today" class="filter-btn <?php echo $filter == 'today' ? 'active' : ''; ?>">Today</a>
            <a href="?filter=week" class="filter-btn <?php echo $filter == 'week' ? 'active' : ''; ?>">This Week</a>
            <a href="?filter=month" class="filter-btn <?php echo $filter == 'month' ? 'active' : ''; ?>">This Month</a>

            <form method="GET" style="display: flex; gap: 5px; margin-left: auto;">
                <input type="hidden" name="filter" value="custom">
                <input type="date" name="start" class="date-input" value="<?php echo $start; ?>">
                <span style="color: #b86b7c; font-weight: 500;">to</span>
                <input type="date" name="end" class="date-input" value="<?php echo $end; ?>">
                <button type="submit" class="filter-btn">Go</button>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Visitors</h3>
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label"><?php echo $filter_text; ?></div>
            </div>
            <div class="stat-card">
                <h3>Currently Inside</h3>
                <div class="stat-number"><?php echo $active; ?></div>
                <div class="stat-label">Active now</div>
            </div>
            <div class="stat-card warning">
                <h3>Pending</h3>
                <div class="stat-number warning"><?php echo $pending_count; ?></div>
                <div class="stat-label">Waiting for decision</div>
            </div>
            <div class="stat-card info">
                <h3>Today's Entries</h3>
                <div class="stat-number info"><?php echo $today_count; ?></div>
                <div class="stat-label">All statuses</div>
            </div>
        </div>

        <div class="tabs">
            <div class="tab active" onclick="showTab('pending')">Pending (<?php echo $pending_count; ?>)</div>
            <div class="tab" onclick="showTab('approved')">Currently Inside (<?php echo $active; ?>)</div>
            <div class="tab" onclick="showTab('recent')">Recent Activity</div>
            <div class="tab" onclick="showTab('stats')">Statistics</div>
            <div class="tab" onclick="showTab('blocked')">Blocked (<?php echo mysqli_num_rows($blocked); ?>)</div>
        </div>

        <div id="pending-tab" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h2>Pending Entry Approvals</h2>
                    <span class="badge pending"><?php echo $pending_count; ?> waiting</span>
                </div>

                <?php if (mysqli_num_rows($pending_entries) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email/ID</th>
                                <th>Type</th>
                                <th>Program</th>
                                <th>Reason</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($entry = mysqli_fetch_assoc($pending_entries)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($entry['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($entry['email']); ?></td>
                                    <td><?php echo ucfirst($entry['user_type']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['program_department']); ?></td>
                                    <td><?php echo ucfirst($entry['reason']); ?></td>
                                    <td><?php echo date('h:i A', strtotime($entry['entry_time'])); ?></td>
                                    <td class="action-buttons">
                                        <a href="?approve=<?php echo $entry['log_id']; ?>" class="btn-approve"
                                            onclick="return confirm('Approve this entry?')">Approve</a>
                                        <a href="?decline=<?php echo $entry['log_id']; ?>" class="btn-decline"
                                            onclick="return confirm('Decline this entry?')">Decline</a>
                                        <button class="btn-block"
                                            onclick="showBlockModal(<?php echo $entry['log_id']; ?>)">Block</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        No pending approvals at the moment
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="approved-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>Currently Inside Library</h2>
                    <span class="badge active"><?php echo $active; ?> active</span>
                </div>

                <?php if (mysqli_num_rows($approved_active) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Reason</th>
                                <th>Entry Time</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($entry = mysqli_fetch_assoc($approved_active)):
                                $entry_time = strtotime($entry['entry_time']);
                                $duration = time() - $entry_time;
                                $hours = floor($duration / 3600);
                                $minutes = floor(($duration % 3600) / 60);
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($entry['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($entry['program_department']); ?></td>
                                    <td><?php echo ucfirst($entry['reason']); ?></td>
                                    <td><?php echo date('h:i A', $entry_time); ?></td>
                                    <td><?php echo $hours > 0 ? $hours . 'h ' : ''; ?><?php echo $minutes; ?>m</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        No one is currently inside the library
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="recent-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>Recent Visitor Log</h2>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($v = mysqli_fetch_assoc($recent)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($v['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($v['email']); ?></td>
                                <td><?php echo ucfirst($v['user_type']); ?></td>
                                <td><?php echo ucfirst($v['reason']); ?></td>
                                <td><?php echo date('M d, h:i A', strtotime($v['entry_time'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $v['approval_status']; ?>">
                                        <?php echo ucfirst($v['approval_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="stats-tab" class="tab-content">
            <div class="row">
                <div class="card">
                    <h2>By Reason</h2>
                    <table>
                        <?php
                        mysqli_data_seek($reasons, 0);
                        while ($r = mysqli_fetch_assoc($reasons)):
                            ?>
                            <tr>
                                <td><?php echo ucfirst($r['reason']); ?></td>
                                <td style="text-align: right;"><?php echo $r['count']; ?></td>
                                <td style="width: 100px;">
                                    <div class="progress-bar">
                                        <div class="progress-fill"
                                            style="width: <?php echo ($r['count'] / max($total, 1)) * 100; ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </div>

                <div class="card">
                    <h2>By User Type</h2>
                    <table>
                        <?php
                        mysqli_data_seek($types, 0);
                        while ($t = mysqli_fetch_assoc($types)):
                            ?>
                            <tr>
                                <td><?php echo ucfirst($t['user_type']); ?></td>
                                <td style="text-align: right;"><?php echo $t['count']; ?></td>
                                <td style="width: 100px;">
                                    <div class="progress-bar">
                                        <div class="progress-fill"
                                            style="width: <?php echo ($t['count'] / max($total, 1)) * 100; ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>

        <div id="blocked-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>Blocked Users</h2>
                </div>

                <?php if (mysqli_num_rows($blocked) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Reason</th>
                                <th>Blocked Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($b = mysqli_fetch_assoc($blocked)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($b['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($b['email']); ?></td>
                                    <td><?php echo htmlspecialchars($b['reason']); ?></td>
                                    <td><?php echo date('M d, Y - h:i A', strtotime($b['blocked_at'])); ?></td>
                                    <td>
                                        <a href="?unblock=<?php echo $b['block_id']; ?>" class="btn-unblock"
                                            onclick="return confirm('Unblock this user?')">Unblock</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        No blocked users
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="blockModal" class="modal">
            <div class="modal-content">
                <h3>Enter Block Reason</h3>
                <form method="POST" action="">
                    <input type="hidden" name="log_id" id="modal_log_id">
                    <input type="text" name="block_reason" placeholder="Reason for blocking" required>
                    <div style="text-align: right;">
                        <button type="button" class="modal-cancel" onclick="closeBlockModal()">Cancel</button>
                        <button type="submit" name="block_from_pending" class="modal-submit">Block User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            document.getElementById(tabName + '-tab').classList.add('active');

            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        function showBlockModal(logId) {
            document.getElementById('modal_log_id').value = logId;
            document.getElementById('blockModal').style.display = 'block';
        }

        function closeBlockModal() {
            document.getElementById('blockModal').style.display = 'none';
        }

        window.onclick = function (event) {
            const modal = document.getElementById('blockModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        setTimeout(function () {
            const message = document.querySelector('.message');
            if (message) {
                message.style.display = 'none';
            }
        }, 5000);
    </script>
</body>

</html>
