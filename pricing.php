<?php
session_start();

if(!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

include "db.php";

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$success  = "";
$error    = "";

if(isset($_POST['buy_plan'])) {
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $plan    = $_POST['plan'];
        $credits = 0;
        if($plan === 'basic')   $credits = 5000;
        if($plan === 'pro')     $credits = 15000;
        if($plan === 'premium') $credits = 50000;
        if($credits > 0) {
            $conn->prepare("UPDATE users SET credits = credits + ? WHERE id = ?")
                 ->execute([$credits, $user_id]);
            $conn->prepare("INSERT INTO credit_purchases (user_id, credits_added, plan) VALUES (?, ?, ?)")
                 ->execute([$user_id, $credits, $plan]);
            $success = number_format($credits) . " credits added to your account!";
        }
    }
}

$stmt = $conn->prepare("SELECT credits FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$credits_bal = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIHub - Pricing</title>
    <link rel="stylesheet" href="dashboard.css?v=4">
    <style>
    .pricing-wrap  { max-width: 960px; margin: 0 auto; padding: 40px 20px; }
    .pricing-title { text-align: center; font-size: 30px; font-weight: bold; margin-bottom: 8px; }
    .pricing-sub   { text-align: center; color: #666; margin-bottom: 40px; font-size: 16px; }
    .plans-grid    { display: flex; gap: 24px; justify-content: center; flex-wrap: wrap; }
    .plan-card {
        background: white; border-radius: 16px; padding: 32px 28px; width: 260px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.09); text-align: center;
        position: relative; border: 2px solid transparent; transition: border .2s;
    }
    .plan-card:hover { border-color: #d1d5db; }
    .plan-card.popular { border-color: black; }
    .popular-badge {
        position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
        background: black; color: white; font-size: 12px; padding: 4px 16px;
        border-radius: 20px; font-weight: 500; white-space: nowrap;
    }
    .plan-name     { font-size: 20px; font-weight: bold; margin-bottom: 6px; }
    .plan-price    { font-size: 36px; font-weight: bold; margin: 12px 0 4px; }
    .plan-price span { font-size: 16px; font-weight: normal; color: #888; }
    .plan-credits  { font-size: 15px; color: #555; margin-bottom: 20px; }
    .plan-features { list-style: none; text-align: left; margin-bottom: 24px; font-size: 14px; }
    .plan-features li { padding: 5px 0; color: #444; }
    .plan-features li::before { content: "✓  "; color: green; font-weight: bold; }
    .plan-btn { width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 15px; cursor: pointer; font-weight: 500; }
    .plan-btn-dark  { background: black; color: white; }
    .plan-btn-dark:hover  { background: #333; }
    .plan-btn-light { background: #f3f4f6; color: black; }
    .plan-btn-light:hover { background: #e5e7eb; }
    .current-credits {
        text-align: center; background: white; border-radius: 10px;
        padding: 16px; margin-bottom: 32px; font-size: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .success-msg {
        background: #dcfce7; color: #166534; padding: 12px 20px;
        border-radius: 8px; text-align: center; margin-bottom: 24px; font-size: 15px;
    }
    .back-btn { display:inline-block; margin-bottom:20px; text-decoration:none; color:#555; font-size:14px; }
    .back-btn:hover { color:black; }
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
        <li class="credits-badge">⚡ <?php echo number_format($credits_bal); ?></li>
        <li class="nav-user">👤 <?php echo htmlspecialchars($username); ?></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>

<div class="pricing-wrap">
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

    <?php if($success): ?>
    <div class="success-msg">🎉 <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="pricing-title">Top Up Your Credits</div>
    <div class="pricing-sub">Choose a plan to keep using all AI tools</div>

    <div class="current-credits">
        Your current balance: <strong>⚡ <?php echo number_format($credits_bal); ?> credits</strong>
    </div>

    <div class="plans-grid">

        <div class="plan-card">
            <div class="plan-name">Basic</div>
            <div class="plan-price">₹99 <span>/one-time</span></div>
            <div class="plan-credits">⚡ 5,000 credits</div>
            <ul class="plan-features">
                <li>500 tool visits</li>
                <li>All AI tools</li>
                <li>Usage history</li>
                <li>No expiry</li>
            </ul>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="plan" value="basic">
                <button type="submit" name="buy_plan" class="plan-btn plan-btn-light">Buy Basic</button>
            </form>
        </div>

        <div class="plan-card popular">
            <span class="popular-badge">Most Popular</span>
            <div class="plan-name">Pro</div>
            <div class="plan-price">₹249 <span>/one-time</span></div>
            <div class="plan-credits">⚡ 15,000 credits</div>
            <ul class="plan-features">
                <li>1,500 tool visits</li>
                <li>All AI tools</li>
                <li>Usage history</li>
                <li>Priority access</li>
                <li>No expiry</li>
            </ul>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="plan" value="pro">
                <button type="submit" name="buy_plan" class="plan-btn plan-btn-dark">Buy Pro</button>
            </form>
        </div>

        <div class="plan-card">
            <div class="plan-name">Premium</div>
            <div class="plan-price">₹699 <span>/one-time</span></div>
            <div class="plan-credits">⚡ 50,000 credits</div>
            <ul class="plan-features">
                <li>5,000 tool visits</li>
                <li>All AI tools</li>
                <li>Full history</li>
                <li>Priority access</li>
                <li>No expiry</li>
            </ul>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="plan" value="premium">
                <button type="submit" name="buy_plan" class="plan-btn plan-btn-light">Buy Premium</button>
            </form>
        </div>

    </div>
</div>

</body>
</html>