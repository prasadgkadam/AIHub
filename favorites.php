<?php
session_start();

if(!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

include "db.php";

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch credits
$stmt = $conn->prepare("SELECT credits FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$credits = $stmt->fetchColumn();

// Fetch favorited tools with category info
$stmt = $conn->prepare(
    "SELECT t.id, t.name, t.description, t.url, t.icon, t.credit_cost,
            c.name AS category_name, c.icon AS category_icon
     FROM favorites f
     JOIN ai_tools t  ON t.id = f.tool_id
     LEFT JOIN categories c ON c.id = t.category_id
     WHERE f.user_id = ?
     ORDER BY f.added_at DESC"
);
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIHub - My Favorites</title>
    <link rel="stylesheet" href="dashboard.css?v=4">
    <style>
    .page-wrap  { max-width:1000px; margin:0 auto; padding:30px 20px; }
    .page-title { font-size:24px; font-weight:bold; margin-bottom:4px; }
    .page-sub   { color:#888; font-size:14px; margin-bottom:28px; }
    .fav-grid   {
        display:grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap:20px;
    }
    .fav-card {
        background:white; border-radius:12px;
        padding:22px 18px; text-align:center;
        box-shadow:0 2px 10px rgba(0,0,0,0.08);
        position:relative; transition:transform .15s, box-shadow .15s;
    }
    .fav-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(0,0,0,0.12); }
    .fav-btn {
        position:absolute; top:12px; right:12px;
        background:none; border:none; font-size:20px;
        cursor:pointer; padding:0; width:auto; color:#f59e0b;
        transition:transform .15s;
    }
    .fav-btn:hover { transform:scale(1.2); background:none; }
    .card-icon  { font-size:32px; margin-bottom:10px; }
    .card-cat   {
        display:inline-block; background:#f3f4f6; color:#555;
        padding:2px 10px; border-radius:20px; font-size:11px; margin-bottom:8px;
    }
    .card h2   { font-size:15px; margin-bottom:6px; }
    .card p    { font-size:12px; color:#888; margin-bottom:8px; }
    .credit-cost { font-size:11px; color:#f59e0b; font-weight:bold; display:block; margin-bottom:10px; }
    .open-btn  {
        width:100%; padding:8px; background:black; color:white;
        border:none; border-radius:6px; cursor:pointer; font-size:13px;
    }
    .open-btn:hover { background:#333; }
    .empty-state {
        text-align:center; padding:80px 20px; color:#888;
        grid-column: 1 / -1;
    }
    .empty-icon { font-size:52px; margin-bottom:14px; }
    .back-btn { display:inline-block; margin-bottom:20px; text-decoration:none; color:#555; font-size:14px; }
    .back-btn:hover { color:black; }
    </style>
</head>
<body>

<nav>
    <h2 class="logo">AIHub</h2>
    <ul>
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="favorites.php" style="color:#fbbf24;">★ Favorites</a></li>
        <li><a href="history.php">History</a></li>
        <li><a href="pricing.php">Pricing</a></li>
        <li class="credits-badge">⚡ <?php echo number_format($credits); ?></li>
        <li class="nav-user">👤 <?php echo htmlspecialchars($username); ?></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>

<div class="page-wrap">
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    <div class="page-title">★ My Favorites</div>
    <div class="page-sub"><?php echo count($favorites); ?> saved tool<?php echo count($favorites) != 1 ? 's' : ''; ?></div>

    <div class="fav-grid">
        <?php if(count($favorites) === 0): ?>
        <div class="empty-state">
            <div class="empty-icon">⭐</div>
            <p style="font-size:16px;margin-bottom:8px;">No favorites yet</p>
            <p style="font-size:13px;">Click the ★ on any tool card to save it here.</p>
            <br>
            <a href="dashboard.php"><button class="open-btn" style="width:auto;padding:10px 24px;">Browse Tools</button></a>
        </div>
        <?php endif; ?>

        <?php foreach($favorites as $tool): ?>
        <div class="card fav-card" id="fav-card-<?php echo $tool['id']; ?>">
            <button class="fav-btn" onclick="toggleFav(<?php echo $tool['id']; ?>, this)" title="Remove from favorites">★</button>
            <div class="card-icon"><?php echo $tool['icon']; ?></div>
            <span class="card-cat"><?php echo $tool['category_icon'].' '.htmlspecialchars($tool['category_name']); ?></span>
            <h2><?php echo htmlspecialchars($tool['name']); ?></h2>
            <p><?php echo htmlspecialchars($tool['description']); ?></p>
            <span class="credit-cost">⚡ <?php echo $tool['credit_cost']; ?> credits</span>
            <a href="chat.php?tool=<?php echo urlencode($tool['name']); ?>&url=<?php echo urlencode($tool['url']); ?>">
                <button class="open-btn">Open</button>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function toggleFav(toolId, btn) {
    fetch('toggle_favorite.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'tool_id=' + toolId
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'removed') {
            const card = document.getElementById('fav-card-' + toolId);
            card.style.opacity = '0';
            card.style.transform = 'scale(0.9)';
            card.style.transition = 'all .3s';
            setTimeout(() => { card.remove(); updateCount(); }, 300);
        }
    });
}
function updateCount() {
    const remaining = document.querySelectorAll('.fav-card').length;
    document.querySelector('.page-sub').textContent =
        remaining + ' saved tool' + (remaining !== 1 ? 's' : '');
    if(remaining === 0) location.reload();
}
</script>

</body>
</html>