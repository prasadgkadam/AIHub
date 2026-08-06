<?php
session_start();

if(!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

include "db.php";

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

$stmt = $conn->prepare("SELECT credits FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$credits = $stmt->fetchColumn();

$stmt = $conn->prepare(
    "SELECT tool_name, tool_url, credits_used, visited_at
     FROM usage_logs WHERE user_id = ?
     ORDER BY visited_at DESC"
);
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $conn->prepare("SELECT COALESCE(SUM(credits_used),0) FROM usage_logs WHERE user_id = ?");
$stmt2->execute([$user_id]);
$total_used = $stmt2->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIHub - History</title>
    <link rel="stylesheet" href="dashboard.css?v=4">
    <style>
    .history-wrap { max-width: 900px; margin: 30px auto; padding: 0 20px; }
    .page-title   { font-size: 26px; font-weight: bold; margin-bottom: 6px; }
    .page-sub     { color: #666; margin-bottom: 24px; font-size: 15px; }
    .stats-row    { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
    .stat-box     {
        background: white; border-radius: 10px; padding: 18px 24px;
        flex: 1; min-width: 140px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); text-align: center;
    }
    .stat-box .num   { font-size: 28px; font-weight: bold; }
    .stat-box .label { font-size: 13px; color: #888; margin-top: 4px; }
    .history-table-wrap {
        background: white; border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden;
    }
    .history-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .history-table th {
        background: #111827; color: white;
        padding: 14px 18px; text-align: left; font-weight: 500;
    }
    .history-table td { padding: 13px 18px; border-bottom: 1px solid #f0f0f0; }
    .history-table tr:last-child td { border-bottom: none; }
    .history-table tr:hover td { background: #f9fafb; }
    .tool-badge {
        display: inline-block; background: #f3f4f6;
        padding: 3px 10px; border-radius: 20px; font-size: 13px; font-weight: 500;
    }
    .empty-state { text-align: center; padding: 60px 20px; color: #888; }
    .empty-state .icon { font-size: 48px; margin-bottom: 12px; }
    .back-btn { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #555; font-size: 14px; }
    .back-btn:hover { color: black; }
    </style>
</head>
<body>

<!-- ✓ FIX: Favorites added to nav -->
<nav>
    <h2 class="logo">AIHub</h2>
    <ul>
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="favorites.php">★ Favorites</a></li>
        <li><a href="history.php">History</a></li>
        <li><a href="pricing.php">Pricing</a></li>
        <li class="credits-badge">⚡ <?php echo number_format($credits); ?></li>
        <li class="nav-user">👤 <?php echo htmlspecialchars($username); ?></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>

<div class="history-wrap">
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    <div class="page-title">Usage History</div>
    <div class="page-sub">All your AI tool activity in one place</div>

    <div class="stats-row">
        <div class="stat-box">
            <div class="num"><?php echo count($logs); ?></div>
            <div class="label">Total visits</div>
        </div>
        <div class="stat-box">
            <div class="num">⚡ <?php echo number_format($total_used); ?></div>
            <div class="label">Credits used</div>
        </div>
        <div class="stat-box">
            <div class="num">⚡ <?php echo number_format($credits); ?></div>
            <div class="label">Credits remaining</div>
        </div>
    </div>

    <div class="history-table-wrap">
        <?php if(count($logs) > 0): ?>
        <table class="history-table">
            <thead>
                <tr><th>#</th><th>AI Tool</th><th>Credits Used</th><th>Date & Time</th></tr>
            </thead>
            <tbody>
                <?php foreach($logs as $i => $log): ?>
                <tr>
                    <td style="color:#aaa;"><?php echo $i + 1; ?></td>
                    <td><span class="tool-badge"><?php echo htmlspecialchars($log['tool_name']); ?></span></td>
                    <td>⚡ <?php echo $log['credits_used']; ?></td>
                    <td><?php echo date('d M Y, h:i A', strtotime($log['visited_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <p>No activity yet. Go use some AI tools!</p>
            <br>
            <a href="dashboard.php"><button>Go to Dashboard</button></a>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>