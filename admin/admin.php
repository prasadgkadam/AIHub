<?php
session_start();

if(!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include "../db.php";

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admin   = $_SESSION['admin_username'];
$success = "";
$error   = "";

// ── USERS: Delete ──────────────────────────────────────────
if(isset($_POST['delete_user'])) {
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $uid = (int)$_POST['user_id'];
        $conn->prepare("DELETE FROM usage_logs  WHERE user_id = ?")->execute([$uid]);
        $conn->prepare("DELETE FROM favorites   WHERE user_id = ?")->execute([$uid]);
        $conn->prepare("DELETE FROM users       WHERE id = ?")->execute([$uid]);
        $success = "User deleted successfully.";
    }
}

// ── USERS: Add credits ─────────────────────────────────────
if(isset($_POST['add_credits'])) {
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $uid = (int)$_POST['user_id'];
        $amt = (int)$_POST['credit_amount'];
        if($amt > 0 && $amt <= 100000) {
            $conn->prepare("UPDATE users SET credits = credits + ? WHERE id = ?")
                 ->execute([$amt, $uid]);
            $conn->prepare("INSERT INTO credit_purchases (user_id, credits_added, plan) VALUES (?,?,'admin')")
                 ->execute([$uid, $amt]);
            $success = number_format($amt) . " credits added successfully.";
        } else {
            $error = "Enter a valid amount (1 – 100,000).";
        }
    }
}

// ── CATEGORIES: Add ────────────────────────────────────────
if(isset($_POST['add_category'])) {
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $name = trim($_POST['cat_name']);
        $icon = trim($_POST['cat_icon']);
        if($name && $icon) {
            $conn->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)")
                 ->execute([$name, $icon]);
            $success = "Category \"" . htmlspecialchars($name) . "\" added.";
        } else {
            $error = "Category name and icon are required.";
        }
    }
}

// ── CATEGORIES: Delete ─────────────────────────────────────
if(isset($_POST['delete_category'])) {
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $cid = (int)$_POST['cat_id'];
        // Set tools in this category to NULL category
        $conn->prepare("UPDATE ai_tools SET category_id = NULL WHERE category_id = ?")->execute([$cid]);
        $conn->prepare("DELETE FROM categories WHERE id = ?")->execute([$cid]);
        $success = "Category deleted.";
    }
}

// ── TOOLS: Add ─────────────────────────────────────────────
if(isset($_POST['add_tool'])) {
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $name   = trim($_POST['tool_name']);
        $desc   = trim($_POST['tool_desc']);
        $url    = trim($_POST['tool_url']);
        $icon   = trim($_POST['tool_icon']);
        $cat    = (int)$_POST['tool_cat'];
        $cost   = (int)$_POST['tool_cost'];
        if($name && $url) {
            $conn->prepare(
                "INSERT INTO ai_tools (name, description, url, icon, category_id, credit_cost)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$name, $desc, $url, $icon ?: '🤖', $cat ?: null, $cost ?: 10]);
            $success = "Tool \"" . htmlspecialchars($name) . "\" added.";
        } else {
            $error = "Tool name and URL are required.";
        }
    }
}

// ── TOOLS: Toggle active ───────────────────────────────────
if(isset($_POST['toggle_tool'])) {
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $tid    = (int)$_POST['tool_id'];
        $status = (int)$_POST['tool_status'];
        $conn->prepare("UPDATE ai_tools SET is_active = ? WHERE id = ?")
             ->execute([$status ? 0 : 1, $tid]);
        $success = "Tool status updated.";
    }
}

// ── TOOLS: Delete ──────────────────────────────────────────
if(isset($_POST['delete_tool'])) {
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $tid = (int)$_POST['tool_id'];
        $conn->prepare("DELETE FROM favorites WHERE tool_id = ?")->execute([$tid]);
        $conn->prepare("DELETE FROM ai_tools  WHERE id = ?")->execute([$tid]);
        $success = "Tool deleted.";
    }
}

// ── FAVORITES: Delete ──────────────────────────────────────
if(isset($_POST['delete_fav'])) {
    $fid = (int)$_POST['fav_id'];
    $conn->prepare("DELETE FROM favorites WHERE id = ?")->execute([$fid]);
    $success = "Favorite removed.";
}

// ── Fetch data ─────────────────────────────────────────────
$total_users   = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_logs    = $conn->query("SELECT COUNT(*) FROM usage_logs")->fetchColumn();
$total_credits = $conn->query("SELECT COALESCE(SUM(credits),0) FROM users")->fetchColumn();
$total_favs    = $conn->query("SELECT COUNT(*) FROM favorites")->fetchColumn();

$users = $conn->query(
    "SELECT id, username, email, credits, created_at FROM users ORDER BY created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$logs = $conn->query(
    "SELECT ul.id, u.username, ul.tool_name, ul.credits_used, ul.visited_at
     FROM usage_logs ul JOIN users u ON u.id = ul.user_id
     ORDER BY ul.visited_at DESC LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

$categories = $conn->query(
    "SELECT c.*, COUNT(t.id) AS tool_count
     FROM categories c
     LEFT JOIN ai_tools t ON t.category_id = c.id
     GROUP BY c.id ORDER BY c.id"
)->fetchAll(PDO::FETCH_ASSOC);

$tools = $conn->query(
    "SELECT t.*, c.name AS cat_name, c.icon AS cat_icon
     FROM ai_tools t
     LEFT JOIN categories c ON c.id = t.category_id
     ORDER BY t.category_id, t.name"
)->fetchAll(PDO::FETCH_ASSOC);

$favorites = $conn->query(
    "SELECT f.id, u.username, t.name AS tool_name, t.icon AS tool_icon,
            c.name AS cat_name, f.added_at
     FROM favorites f
     JOIN users u    ON u.id = f.user_id
     JOIN ai_tools t ON t.id = f.tool_id
     LEFT JOIN categories c ON c.id = t.category_id
     ORDER BY f.added_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$tab = $_GET['tab'] ?? 'users';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIHub Admin Panel</title>
    <style>
    *{ margin:0; padding:0; box-sizing:border-box; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Roboto',sans-serif; }
    body { background:linear-gradient(135deg,#f8fafc 0%,#ede9fe 100%); color:#1e293b; min-height:100vh; }

    /* Layout */
    .layout { display:flex; min-height:100vh; }
    .sidebar {
        width:240px; background:linear-gradient(180deg,#ffffff 0%,#f9fafb 100%); border-right:2px solid #e2e8f0;
        padding:0; flex-shrink:0; display:flex; flex-direction:column;
        box-shadow:0 4px 20px rgba(102,126,234,0.1);
    }
    .sidebar-logo {
        padding:28px 24px; border-bottom:1px solid #e2e8f0;
        font-size:22px; font-weight:800;
        color:#1e293b;
    }
    .sidebar-logo span { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .sidebar-admin {
        padding:14px 24px 12px; font-size:12px; color:#64748b;
        border-bottom:1px solid #e2e8f0; font-weight:600;
        text-transform:uppercase; letter-spacing:0.5px;
    }
    .sidebar nav a {
        display:flex; align-items:center; gap:12px;
        padding:12px 24px; color:#64748b; text-decoration:none; font-size:14px;
        border-left:4px solid transparent; transition:all 0.3s ease; font-weight:500;
    }
    .sidebar nav a:hover { background:linear-gradient(90deg,rgba(102,126,234,0.08) 0%,transparent 100%); color:#1e293b; }
    .sidebar nav a.active { background:linear-gradient(90deg,rgba(102,126,234,0.15) 0%,transparent 100%); color:#667eea; border-left-color:#667eea; }
    .nav-count {
        margin-left:auto; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:white;
        padding:2px 10px; border-radius:12px; font-size:11px; font-weight:700;
    }
    .sidebar-footer {
        margin-top:auto; padding:20px 24px;
        border-top:1px solid #e2e8f0; display:flex; flex-direction:column; gap:10px;
    }
    .sidebar-footer a { color:#64748b; text-decoration:none; font-size:13px; font-weight:500; transition:all 0.3s ease; }
    .sidebar-footer a:hover { color:#667eea; }

    /* Main */
    .main { flex:1; padding:32px 36px; overflow-x:auto; min-width:0; }
    .page-title { font-size:24px; font-weight:800; margin-bottom:6px; color:#1e293b; }
    .page-sub { color:#64748b; font-size:14px; margin-bottom:28px; font-weight:500; }

    /* Alerts */
    .alert-success {
        background:linear-gradient(135deg,#dcfce7 0%,#d1fae5 100%); color:#065f46; border:1px solid #a7f3d0;
        padding:13px 18px; border-radius:10px; margin-bottom:24px; font-size:14px; font-weight:600;
    }
    .alert-error {
        background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%); color:#991b1b; border:1px solid #fca5a5;
        padding:13px 18px; border-radius:10px; margin-bottom:24px; font-size:14px; font-weight:600;
    }

    /* Stats */
    .stats-grid {
        display:grid; grid-template-columns:repeat(4,1fr);
        gap:18px; margin-bottom:32px;
    }
    .stat-card {
        background:white; border:1px solid #e2e8f0;
        border-radius:14px; padding:24px; text-align:center;
        box-shadow:0 2px 8px rgba(0,0,0,0.04); transition:all 0.3s ease;
    }
    .stat-card:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(102,126,234,0.12); border-color:#667eea; }
    .stat-card .num { font-size:32px; font-weight:800; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .stat-card .label { font-size:12px; color:#64748b; margin-top:8px; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; }

    /* Card box */
    .box {
        background:white; border:1px solid #e2e8f0;
        border-radius:14px; overflow:hidden; margin-bottom:28px;
        box-shadow:0 2px 8px rgba(0,0,0,0.04);
    }
    .box-header {
        padding:18px 24px; border-bottom:1px solid #e2e8f0;
        font-size:16px; font-weight:700;
        display:flex; justify-content:space-between; align-items:center;
        color:#1e293b; background:linear-gradient(90deg,#f9fafb 0%,transparent 100%);
    }
    .box-count { font-size:13px; color:#64748b; font-weight:600; }
    .box-body { padding:24px; }

    /* Table */
    table { width:100%; border-collapse:collapse; font-size:13px; }
    th {
        background:#f8fafc; color:#64748b; padding:13px 16px;
        text-align:left; font-weight:700; font-size:12px;
        text-transform:uppercase; letter-spacing:0.6px;
        border-bottom:2px solid #e2e8f0;
    }
    td { padding:13px 16px; border-bottom:1px solid #e2e8f0; color:#1e293b; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:#f9fafb; }

    /* Badges */
    .badge {
        display:inline-block; padding:5px 12px; border-radius:8px;
        font-size:12px; font-weight:700;
    }
    .b-purple { background:linear-gradient(135deg,#eff6ff 0%,#f0f4ff 100%); color:#667eea; border:1px solid #dbeafe; }
    .b-green  { background:linear-gradient(135deg,#dcfce7 0%,#d1fae5 100%); color:#065f46; border:1px solid #a7f3d0; }
    .b-yellow { background:linear-gradient(135deg,#fef3c7 0%,#fef9e7 100%); color:#92400e; border:1px solid #fde68a; }
    .b-gray   { background:linear-gradient(135deg,#f3f4f6 0%,#f9fafb 100%); color:#4b5563; border:1px solid #d1d5db; }
    .b-red    { background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%); color:#991b1b; border:1px solid #fca5a5; }

    /* Buttons */
    .btn { padding:8px 16px; border:none; border-radius:8px; cursor:pointer; font-size:12px; font-weight:700; transition:all 0.3s ease; }
    .btn-purple { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:white; box-shadow:0 4px 12px rgba(102,126,234,0.3); }
    .btn-purple:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(102,126,234,0.4); }
    .btn-red    { background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%); color:white; box-shadow:0 4px 12px rgba(239,68,68,0.3); }
    .btn-red:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(239,68,68,0.4); }
    .btn-gray   { background:#e5e7eb; color:#1f2937; border:1px solid #d1d5db; }
    .btn-gray:hover { background:#d1d5db; }
    .btn-green  { background:linear-gradient(135deg,#10b981 0%,#059669 100%); color:white; box-shadow:0 4px 12px rgba(16,185,129,0.3); }
    .btn-green:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(16,185,129,0.4); }

    /* Add form */
    .add-form {
        display:grid; gap:14px;
        grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
        align-items:end;
    }
    .form-group { display:flex; flex-direction:column; gap:6px; }
    .form-group label { font-size:12px; color:#475569; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding:11px 14px; background:#f9fafb; border:2px solid #e2e8f0;
        border-radius:8px; color:#1e293b; font-size:13px; outline:none;
        font-family:inherit; transition:all 0.3s ease;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus { border-color:#667eea; box-shadow:0 0 0 4px rgba(102,126,234,0.1); }
    .form-group select option { background:#ffffff; color:#1e293b; }

    /* Inline credit form */
    .credit-form { display:flex; gap:8px; align-items:center; }
    .credit-form input {
        width:90px; padding:8px 12px; background:#f9fafb;
        border:2px solid #e2e8f0; border-radius:6px;
        color:#1e293b; font-size:12px; outline:none; transition:all 0.3s ease;
    }
    .credit-form input:focus { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,0.1); }

    @media(max-width:768px){
        .sidebar { width:70px; }
        .sidebar nav a span { display:none; }
        .stats-grid { grid-template-columns:repeat(2,1fr); }
        .main { padding:18px; }
    }
    </style>
</head>
<body>
<div class="layout">

<!-- ── Sidebar ── -->
<aside class="sidebar">
    <div class="sidebar-logo">AI<span>Hub</span></div>
    <div class="sidebar-admin">👤 <?php echo htmlspecialchars($admin); ?></div>
    <nav>
        <a href="?tab=users"      class="<?php echo $tab=='users'      ?'active':''; ?>">
            👥 <span>Users</span> <span class="nav-count"><?php echo $total_users; ?></span>
        </a>
        <a href="?tab=logs"       class="<?php echo $tab=='logs'       ?'active':''; ?>">
            📊 <span>Usage Logs</span> <span class="nav-count"><?php echo $total_logs; ?></span>
        </a>
        <a href="?tab=credits"    class="<?php echo $tab=='credits'    ?'active':''; ?>">
            ⚡ <span>Credits</span>
        </a>
        <a href="?tab=categories" class="<?php echo $tab=='categories' ?'active':''; ?>">
            🗂️ <span>Categories</span> <span class="nav-count"><?php echo count($categories); ?></span>
        </a>
        <a href="?tab=tools"      class="<?php echo $tab=='tools'      ?'active':''; ?>">
            🤖 <span>AI Tools</span> <span class="nav-count"><?php echo count($tools); ?></span>
        </a>
        <a href="?tab=favorites"  class="<?php echo $tab=='favorites'  ?'active':''; ?>">
            ★ <span>Favorites</span> <span class="nav-count"><?php echo $total_favs; ?></span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="../dashboard.php">← Main site</a>
        <a href="admin_logout.php">🚪 Logout</a>
    </div>
</aside>

<!-- ── Main ── -->
<main class="main">

    <?php if($success): ?>
    <div class="alert-success">✓ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="alert-error">✗ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Stats (always visible) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="num"><?php echo number_format($total_users); ?></div>
            <div class="label">Users</div>
        </div>
        <div class="stat-card">
            <div class="num"><?php echo number_format($total_logs); ?></div>
            <div class="label">Tool visits</div>
        </div>
        <div class="stat-card">
            <div class="num">⚡ <?php echo number_format($total_credits); ?></div>
            <div class="label">Credits in circulation</div>
        </div>
        <div class="stat-card">
            <div class="num">★ <?php echo number_format($total_favs); ?></div>
            <div class="label">Total favorites</div>
        </div>
    </div>

    <?php // ══════════════ USERS TAB ══════════════
    if($tab === 'users'): ?>

    <div class="page-title">👥 All Users</div>
    <div class="page-sub">View and manage registered users</div>
    <div class="box">
        <div class="box-header">Users <span class="box-count"><?php echo count($users); ?> total</span></div>
        <table>
            <thead><tr><th>#</th><th>Username</th><th>Email</th><th>Credits</th><th>Joined</th><th>Action</th></tr></thead>
            <tbody>
            <?php if(!$users): ?>
                <tr><td colspan="6" style="text-align:center;color:#64748b;padding:30px;">No users yet.</td></tr>
            <?php endif; ?>
            <?php foreach($users as $i => $u): ?>
            <tr>
                <td style="color:#64748b;"><?php echo $i+1; ?></td>
                <td><span class="badge b-purple"><?php echo htmlspecialchars($u['username']); ?></span></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><span class="badge b-yellow">⚡ <?php echo number_format($u['credits']); ?></span></td>
                <td><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('Delete this user? Cannot be undone.');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <button type="submit" name="delete_user" class="btn btn-red">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php // ══════════════ LOGS TAB ══════════════
    elseif($tab === 'logs'): ?>

    <div class="page-title">📊 Usage Logs</div>
    <div class="page-sub">Recent tool activity across all users</div>
    <div class="box">
        <div class="box-header">Activity <span class="box-count">Last 20 entries</span></div>
        <table>
            <thead><tr><th>#</th><th>User</th><th>Tool</th><th>Credits</th><th>Time</th></tr></thead>
            <tbody>
            <?php if(!$logs): ?>
                <tr><td colspan="5" style="text-align:center;color:#64748b;padding:30px;">No activity yet.</td></tr>
            <?php endif; ?>
            <?php foreach($logs as $i => $log): ?>
            <tr>
                <td style="color:#64748b;"><?php echo $i+1; ?></td>
                <td><span class="badge b-purple"><?php echo htmlspecialchars($log['username']); ?></span></td>
                <td><span class="badge b-green"><?php echo htmlspecialchars($log['tool_name']); ?></span></td>
                <td>⚡ <?php echo $log['credits_used']; ?></td>
                <td><?php echo date('d M Y, h:i A', strtotime($log['visited_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php // ══════════════ CREDITS TAB ══════════════
    elseif($tab === 'credits'): ?>

    <div class="page-title">⚡ Manage Credits</div>
    <div class="page-sub">Add credits to any user account</div>
    <div class="box">
        <div class="box-header">Add credits <span class="box-count"><?php echo count($users); ?> users</span></div>
        <table>
            <thead><tr><th>#</th><th>Username</th><th>Email</th><th>Balance</th><th>Add credits</th></tr></thead>
            <tbody>
            <?php foreach($users as $i => $u): ?>
            <tr>
                <td style="color:#64748b;"><?php echo $i+1; ?></td>
                <td><span class="badge b-purple"><?php echo htmlspecialchars($u['username']); ?></span></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><span class="badge b-yellow">⚡ <?php echo number_format($u['credits']); ?></span></td>
                <td>
                    <form method="POST" class="credit-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <input type="number" name="credit_amount" placeholder="e.g. 500" min="1" max="100000" required>
                        <button type="submit" name="add_credits" class="btn btn-purple">+ Add</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php // ══════════════ CATEGORIES TAB ══════════════
    elseif($tab === 'categories'): ?>

    <div class="page-title">🗂️ Categories</div>
    <div class="page-sub">Manage AI tool categories shown on the dashboard</div>

    <!-- Add category form -->
    <div class="box">
        <div class="box-header">Add new category</div>
        <div class="box-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="add-form">
                    <div class="form-group">
                        <label>Icon (emoji)</label>
                        <input type="text" name="cat_icon" placeholder="e.g. 🎯" maxlength="5" required>
                    </div>
                    <div class="form-group">
                        <label>Category Name</label>
                        <input type="text" name="cat_name" placeholder="e.g. Writing" required>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="add_category" class="btn btn-purple" style="height:38px;">+ Add Category</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Categories table -->
    <div class="box">
        <div class="box-header">All categories <span class="box-count"><?php echo count($categories); ?> total</span></div>
        <table>
            <thead><tr><th>#</th><th>Icon</th><th>Name</th><th>Tools</th><th>Created</th><th>Action</th></tr></thead>
            <tbody>
            <?php if(!$categories): ?>
                <tr><td colspan="6" style="text-align:center;color:#64748b;padding:30px;">No categories yet.</td></tr>
            <?php endif; ?>
            <?php foreach($categories as $i => $cat): ?>
            <tr>
                <td style="color:#64748b;"><?php echo $i+1; ?></td>
                <td style="font-size:20px;"><?php echo $cat['icon']; ?></td>
                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                <td><span class="badge b-gray"><?php echo $cat['tool_count']; ?> tools</span></td>
                <td><?php echo date('d M Y', strtotime($cat['created_at'])); ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('Delete this category? Tools in it will become uncategorized.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                        <button type="submit" name="delete_category" class="btn btn-red">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php // ══════════════ TOOLS TAB ══════════════
    elseif($tab === 'tools'): ?>

    <div class="page-title">🤖 AI Tools</div>
    <div class="page-sub">Add, edit or remove AI tools shown on the dashboard</div>

    <!-- Add tool form -->
    <div class="box">
        <div class="box-header">Add new tool</div>
        <div class="box-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="add-form">
                    <div class="form-group">
                        <label>Icon (emoji)</label>
                        <input type="text" name="tool_icon" placeholder="🤖" maxlength="5">
                    </div>
                    <div class="form-group" style="min-width:160px;">
                        <label>Tool Name *</label>
                        <input type="text" name="tool_name" placeholder="e.g. ChatGPT" required>
                    </div>
                    <div class="form-group" style="min-width:200px;">
                        <label>Description</label>
                        <input type="text" name="tool_desc" placeholder="Short description">
                    </div>
                    <div class="form-group" style="min-width:200px;">
                        <label>URL *</label>
                        <input type="url" name="tool_url" placeholder="https://..." required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="tool_cat">
                            <option value="">None</option>
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo $cat['icon'].' '.htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Credit cost</label>
                        <input type="number" name="tool_cost" value="10" min="1" max="1000">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="add_tool" class="btn btn-purple" style="height:38px;">+ Add Tool</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tools table -->
    <div class="box">
        <div class="box-header">All tools <span class="box-count"><?php echo count($tools); ?> total</span></div>
        <table>
            <thead><tr><th>#</th><th>Tool</th><th>Category</th><th>Cost</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(!$tools): ?>
                <tr><td colspan="6" style="text-align:center;color:#64748b;padding:30px;">No tools yet.</td></tr>
            <?php endif; ?>
            <?php foreach($tools as $i => $tool): ?>
            <tr>
                <td style="color:#64748b;"><?php echo $i+1; ?></td>
                <td>
                    <span style="font-size:18px;margin-right:6px;"><?php echo $tool['icon']; ?></span>
                    <strong><?php echo htmlspecialchars($tool['name']); ?></strong>
                    <div style="font-size:11px;color:#64748b;margin-top:2px;"><?php echo htmlspecialchars($tool['description']); ?></div>
                </td>
                <td>
                    <?php if($tool['cat_name']): ?>
                    <span class="badge b-gray"><?php echo $tool['cat_icon'].' '.htmlspecialchars($tool['cat_name']); ?></span>
                    <?php else: ?>
                    <span style="color:#64748b;font-size:12px;">Uncategorized</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge b-yellow">⚡ <?php echo $tool['credit_cost']; ?></span></td>
                <td>
                    <span class="badge <?php echo $tool['is_active'] ? 'b-green' : 'b-red'; ?>">
                        <?php echo $tool['is_active'] ? 'Active' : 'Hidden'; ?>
                    </span>
                </td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <!-- Toggle active/hidden -->
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="tool_id"     value="<?php echo $tool['id']; ?>">
                        <input type="hidden" name="tool_status" value="<?php echo $tool['is_active']; ?>">
                        <button type="submit" name="toggle_tool"
                                class="btn <?php echo $tool['is_active'] ? 'btn-gray' : 'btn-green'; ?>">
                            <?php echo $tool['is_active'] ? 'Hide' : 'Show'; ?>
                        </button>
                    </form>
                    <!-- Delete -->
                    <form method="POST" onsubmit="return confirm('Delete this tool?');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="tool_id" value="<?php echo $tool['id']; ?>">
                        <button type="submit" name="delete_tool" class="btn btn-red">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php // ══════════════ FAVORITES TAB ══════════════
    elseif($tab === 'favorites'): ?>

    <div class="page-title">★ Favorites</div>
    <div class="page-sub">See which tools users have saved as favorites</div>

    <div class="box">
        <div class="box-header">All favorites <span class="box-count"><?php echo count($favorites); ?> total</span></div>
        <table>
            <thead><tr><th>#</th><th>User</th><th>Tool</th><th>Category</th><th>Saved on</th><th>Action</th></tr></thead>
            <tbody>
            <?php if(!$favorites): ?>
                <tr><td colspan="6" style="text-align:center;color:#64748b;padding:30px;">No favorites yet.</td></tr>
            <?php endif; ?>
            <?php foreach($favorites as $i => $fav): ?>
            <tr>
                <td style="color:#64748b;"><?php echo $i+1; ?></td>
                <td><span class="badge b-purple"><?php echo htmlspecialchars($fav['username']); ?></span></td>
                <td>
                    <span style="font-size:16px;margin-right:5px;"><?php echo $fav['tool_icon']; ?></span>
                    <?php echo htmlspecialchars($fav['tool_name']); ?>
                </td>
                <td>
                    <?php if($fav['cat_name']): ?>
                    <span class="badge b-gray"><?php echo htmlspecialchars($fav['cat_name']); ?></span>
                    <?php else: ?>
                    <span style="color:#64748b;font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td><?php echo date('d M Y, h:i A', strtotime($fav['added_at'])); ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('Remove this favorite?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="fav_id" value="<?php echo $fav['id']; ?>">
                        <button type="submit" name="delete_fav" class="btn btn-red">Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

</main>
</div>
</body>
</html>