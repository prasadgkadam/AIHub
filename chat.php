<?php
session_start();

if(!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

include "db.php";

$user_id  = $_SESSION['user_id'];
$tool_url = isset($_GET['url']) ? trim($_GET['url']) : '';

// Fetch the tool from DB by URL — validates it's active AND gets the correct credit cost
$stmt = $conn->prepare("SELECT id, name, credit_cost FROM ai_tools WHERE url = ? AND is_active = 1");
$stmt->execute([$tool_url]);
$tool = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$tool) {
    // URL not in DB or tool inactive — reject
    header("Location: dashboard.php");
    exit();
}

$tool_name = $tool['name'];
$cost      = (int)$tool['credit_cost'];  // admin-defined cost per tool

// Check user has enough credits
$stmt = $conn->prepare("SELECT credits FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if($user['credits'] < $cost) {
    $_SESSION['error'] = "Not enough credits. Please top up.";
    header("Location: pricing.php");
    exit();
}

// Deduct credits and log usage atomically
try {
    $conn->beginTransaction();
    $conn->prepare("UPDATE users SET credits = credits - ? WHERE id = ?")
         ->execute([$cost, $user_id]);
    $conn->prepare(
        "INSERT INTO usage_logs (user_id, tool_name, tool_url, credits_used)
         VALUES (?, ?, ?, ?)"
    )->execute([$user_id, $tool_name, $tool_url, $cost]);
    $conn->commit();
} catch(Exception $e) {
    $conn->rollBack();
    $_SESSION['error'] = "Something went wrong. Please try again.";
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Launching <?php echo htmlspecialchars($tool_name); ?>...</title>
    <link rel="stylesheet" href="dashboard.css?v=4">
    <style>
    body {
        display: flex; justify-content: center; align-items: center;
        min-height: 100vh; background: #f4f4f4; font-family: Arial, sans-serif;
    }
    .launch-box {
        background: white; border-radius: 16px; padding: 50px 40px;
        text-align: center; box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        max-width: 420px; width: 90%;
    }
    .launch-icon { font-size: 52px; margin-bottom: 16px; }
    .launch-box h2 { font-size: 22px; margin-bottom: 10px; }
    .launch-box p  { color: #555; margin-bottom: 6px; }
    .credit-deducted {
        background: #fef9c3; color: #713f12; padding: 8px 16px;
        border-radius: 6px; font-size: 14px; display: inline-block; margin: 16px 0;
    }
    .progress-bar {
        width: 100%; height: 6px; background: #e5e7eb;
        border-radius: 3px; overflow: hidden; margin-top: 20px;
    }
    .progress-fill {
        height: 100%; background: black; border-radius: 3px;
        animation: fill 2s linear forwards;
    }
    @keyframes fill { from{width:0%} to{width:100%} }
    .back-link { margin-top: 16px; display: block; color: #888; font-size: 13px; text-decoration: none; }
    .back-link:hover { color: black; }
    </style>
    <meta http-equiv="refresh" content="2;url=<?php echo htmlspecialchars($tool_url); ?>">
</head>
<body>
<div class="launch-box">
    <div class="launch-icon">🚀</div>
    <h2>Launching <?php echo htmlspecialchars($tool_name); ?></h2>
    <p>You will be redirected in 2 seconds...</p>
    <div class="credit-deducted">⚡ <?php echo $cost; ?> credits deducted</div>
    <div class="progress-bar"><div class="progress-fill"></div></div>
    <a href="<?php echo htmlspecialchars($tool_url); ?>" class="back-link">Click here if not redirected</a>
    <a href="dashboard.php" class="back-link">← Back to dashboard</a>
</div>
</body>
</html>