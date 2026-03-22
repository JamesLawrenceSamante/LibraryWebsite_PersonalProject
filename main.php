<?php
session_start();
require_once 'connection.php';

// Get statistics - no date filtering anymore
$approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE approval_status = 'approved'"))['count'];
$waiting = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE approval_status = 'pending'"))['count'];
$currently_inside = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM visitor_logs WHERE status = 'active' AND approval_status = 'approved'"))['count'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>New Era University Library</title>
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
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .header-text {
            background: white;
            padding: 15px 30px;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(255, 182, 193, 0.3);
            border: 1px solid rgba(255, 192, 203, 0.3);
            display: inline-block;
        }

        .header-text h1 {
            font-size: 20px;
            font-weight: 600;
            color: #d44e6c;
            text-shadow: 2px 2px 4px rgba(255, 182, 193, 0.2);
        }

        .button-container {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }

        .continue-btn {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #5d3a4a;
            border: none;
            padding: 14px 50px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 154, 158, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .continue-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 154, 158, 0.5);
            background: linear-gradient(135deg, #ff8a8e 0%, #febfdf 100%);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px 20px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(255, 182, 193, 0.2);
            border-left: 5px solid #a8edea;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 192, 203, 0.3);
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(255, 154, 158, 0.3);
        }

        .stat-card.approved {
            border-left-color: #a8edea;
        }

        .stat-card.waiting {
            border-left-color: #fbc2eb;
        }

        .stat-card.inside {
            border-left-color: #ff9a9e;
        }

        .stat-card h3 {
            font-size: 14px;
            color: #b86b7c;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-number.approved {
            background: linear-gradient(135deg, #2e8b8b, #3d6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-number.waiting {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-number.inside {
            background: linear-gradient(135deg, #ff9a9e, #fecfef);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 12px;
            color: #b86b7c;
            font-weight: 500;
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

        /* Custom scrollbar */
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

        /* Container for better spacing */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
        }
    </style>
</head>

<body>
    <img src="images/images.png" alt="NEU Library Logo" class="logo" />

    <div class="main-container">
        <!-- Header with fitted background -->
        <div class="header">
            <div class="header-text">
                <h1>New Era University Library</h1>
            </div>
        </div>

        <!-- Continue Button -->
        <div class="button-container">
            <a href="visitor_login.php" class="continue-btn">Click to Login</a>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card approved">
                <h3>Approved</h3>
                <div class="stat-number approved">
                    <?php echo $approved; ?>
                </div>
                <div class="stat-label">
                    Total approved entries
                </div>
            </div>
            <div class="stat-card waiting">
                <h3>Waiting for Approval</h3>
                <div class="stat-number waiting">
                    <?php echo $waiting; ?>
                </div>
                <div class="stat-label">
                    In line for entry
                </div>
            </div>
            <div class="stat-card inside">
                <h3>Currently Inside</h3>
                <div class="stat-number inside">
                    <?php echo $currently_inside; ?>
                </div>
                <div class="stat-label">
                    Total active visitors
                </div>
            </div>
        </div>
    </div>
</body>

</html>
