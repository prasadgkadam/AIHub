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

$categories = $conn->query("SELECT * FROM categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$tools = $conn->query(
    "SELECT t.*, c.name AS cat_name, c.icon AS cat_icon
     FROM ai_tools t
     LEFT JOIN categories c ON c.id = t.category_id
     WHERE t.is_active = 1
     ORDER BY t.category_id, t.name"
)->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT tool_id FROM favorites WHERE user_id = ?");
$stmt->execute([$user_id]);
$fav_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'tool_id');

$stmt2 = $conn->prepare("SELECT COUNT(*) FROM usage_logs WHERE user_id = ?");
$stmt2->execute([$user_id]);
$visit_count = $stmt2->fetchColumn();

$stmt3 = $conn->prepare("SELECT COALESCE(SUM(credits_used),0) FROM usage_logs WHERE user_id = ?");
$stmt3->execute([$user_id]);
$credits_used = $stmt3->fetchColumn();

$stmt4 = $conn->prepare(
    "SELECT tool_name, credits_used, visited_at FROM usage_logs
     WHERE user_id = ? ORDER BY visited_at DESC LIMIT 5"
);
$stmt4->execute([$user_id]);
$logs = $stmt4->fetchAll(PDO::FETCH_ASSOC);

$fav_count = count($fav_ids);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIHub - Dashboard</title>
    <!-- ✓ FIX: Only dashboard.css — no duplicate inline styles -->
    <link rel="stylesheet" href="dashboard.css?v=5">
</head>
<body>

<nav>
    <h2 class="logo">AIHub</h2>
    <ul>
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="favorites.php">★ Favorites
            <?php if($fav_count > 0): ?>
            <span style="background:#f59e0b;color:black;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:2px;">
                <?php echo $fav_count; ?>
            </span>
            <?php endif; ?>
        </a></li>
        <li><a href="history.php">History</a></li>
        <li><a href="pricing.php">Pricing</a></li>
        <li class="credits-badge">⚡ <?php echo number_format($credits); ?></li>
        <li><a href="profile.php">👤 <?php echo htmlspecialchars($username); ?></a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>

<header>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
    <p class="subtitle">Discover and launch AI tools instantly</p>
    <?php if(isset($_SESSION['success'])): ?>
    <div class="success-msg"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <input type="text" placeholder="Search AI tools..." class="search-box"
           oninput="searchTools(this.value)" id="search-input">
</header>

<div class="stats-bar">
    <div class="stat">
        <span class="stat-num">⚡ <?php echo number_format($credits); ?></span>
        <span class="stat-label">Credits remaining</span>
    </div>
    <div class="stat">
        <span class="stat-num"><?php echo $visit_count; ?></span>
        <span class="stat-label">Tool visits</span>
    </div>
    <div class="stat">
        <span class="stat-num">★ <?php echo $fav_count; ?></span>
        <span class="stat-label">Favorites</span>
    </div>
    <div class="stat">
        <span class="stat-num">⚡ <?php echo number_format($credits_used); ?></span>
        <span class="stat-label">Credits used</span>
    </div>
    <a href="pricing.php" class="top-up-btn">+ Top Up</a>
</div>

<div class="cat-tabs">
    <button class="cat-tab active" onclick="filterCat('all', this)">🔮 All Tools</button>
    <button class="cat-tab" onclick="filterCat('fav', this)">★ Favorites</button>
    <?php foreach($categories as $cat): ?>
    <button class="cat-tab" onclick="filterCat(<?php echo $cat['id']; ?>, this)">
        <?php echo $cat['icon'].' '.htmlspecialchars($cat['name']); ?>
    </button>
    <?php endforeach; ?>
</div>

<section class="tools" id="tools-grid">
    <?php foreach($tools as $tool):
        $is_fav = in_array($tool['id'], $fav_ids);
    ?>
    <div class="card"
         id="card-<?php echo $tool['id']; ?>"
         data-cat="<?php echo $tool['category_id']; ?>"
         data-name="<?php echo strtolower($tool['name'].' '.$tool['cat_name'].' '.$tool['description']); ?>"
         data-fav="<?php echo $is_fav ? '1' : '0'; ?>">

        <button class="fav-btn <?php echo $is_fav ? 'fav-on' : ''; ?>"
                onclick="toggleFav(<?php echo $tool['id']; ?>, this)"
                title="<?php echo $is_fav ? 'Remove from favorites' : 'Add to favorites'; ?>">★</button>

        <div class="card-icon"><?php echo $tool['icon']; ?></div>
        <span class="card-cat-badge"><?php echo $tool['cat_icon'].' '.htmlspecialchars($tool['cat_name']); ?></span>
        <h2><?php echo htmlspecialchars($tool['name']); ?></h2>
        <p><?php echo htmlspecialchars($tool['description']); ?></p>
        <span class="credit-cost">⚡ <?php echo $tool['credit_cost']; ?> credits</span>
        <a href="chat.php?tool=<?php echo urlencode($tool['name']); ?>&url=<?php echo urlencode($tool['url']); ?>">
            <button class="open-btn">Open</button>
        </a>
    </div>
    <?php endforeach; ?>

    <div class="no-results" id="no-results" style="display:none;">
        😕 No tools found. Try a different search or category.
    </div>
</section>

<?php if(count($logs) > 0): ?>
<div class="recent-activity">
    <div class="section-heading">Recent Activity</div>
    <table class="activity-table">
        <thead>
            <tr><th>Tool</th><th>Credits</th><th>Time</th></tr>
        </thead>
        <tbody>
            <?php foreach($logs as $log): ?>
            <tr>
                <td><?php echo htmlspecialchars($log['tool_name']); ?></td>
                <td>⚡ <?php echo $log['credits_used']; ?></td>
                <td><?php echo date('d M, h:i A', strtotime($log['visited_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="history.php" class="view-all">View full history →</a>
</div>
<?php endif; ?>

<script>
let currentCat = 'all';
let currentSearch = '';

function filterCat(cat, btn) {
    currentCat = cat;
    document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('search-input').value = '';
    currentSearch = '';
    applyFilters();
}

function searchTools(query) {
    currentSearch = query.toLowerCase();
    if(currentSearch) {
        currentCat = 'all';
        document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
        document.querySelector('.cat-tab').classList.add('active');
    }
    applyFilters();
}

function applyFilters() {
    const cards = document.querySelectorAll('.card:not(#no-results)');
    let visible = 0;
    cards.forEach(card => {
        const cat   = card.getAttribute('data-cat');
        const name  = card.getAttribute('data-name');
        const isFav = card.getAttribute('data-fav') === '1';
        const catMatch = currentCat === 'all'
            || (currentCat === 'fav' && isFav)
            || cat == currentCat;
        const searchMatch = !currentSearch || name.includes(currentSearch);
        if(catMatch && searchMatch) { card.classList.remove('hidden'); visible++; }
        else                        { card.classList.add('hidden'); }
    });
    document.getElementById('no-results').style.display = visible === 0 ? 'block' : 'none';
}

function toggleFav(toolId, btn) {
    fetch('toggle_favorite.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'tool_id=' + toolId
    })
    .then(r => r.json())
    .then(data => {
        const card = document.getElementById('card-' + toolId);
        if(data.status === 'added') {
            btn.classList.add('fav-on');
            btn.title = 'Remove from favorites';
            card.setAttribute('data-fav', '1');
            showToast('Added to favorites ★');
        } else if(data.status === 'removed') {
            btn.classList.remove('fav-on');
            btn.title = 'Add to favorites';
            card.setAttribute('data-fav', '0');
            showToast('Removed from favorites');
            if(currentCat === 'fav') applyFilters();
        }
    });
}

function showToast(msg) {
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = `
        position:fixed; bottom:24px; left:50%; transform:translateX(-50%);
        background:#111827; color:white; padding:10px 22px;
        border-radius:20px; font-size:14px; z-index:9999;
        animation:fadeInUp .3s ease;
    `;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2200);
}
</script>

<style>
@keyframes fadeInUp {
    from { opacity:0; transform:translateX(-50%) translateY(10px); }
    to   { opacity:1; transform:translateX(-50%) translateY(0); }
}
</style>

</body>
</html>