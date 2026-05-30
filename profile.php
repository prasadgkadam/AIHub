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
$success  = "";
$errors   = [];

// ── Fetch full user record ─────────────────────────────────────────
$stmt = $conn->prepare("SELECT id, username, email, credits, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ── Handle: Update Profile (username / email) ──────────────────────
if(isset($_POST['update_profile'])) {
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $errors[] = "Security validation failed. Please try again.";
    } else {
        $new_username = trim($_POST['username']);
        $new_email    = trim($_POST['email']);

        if(empty($new_username) || strlen($new_username) < 3)
            $errors[] = "Username must be at least 3 characters.";
        elseif(!preg_match('/^[a-zA-Z0-9_]+$/', $new_username))
            $errors[] = "Username can only contain letters, numbers and underscores.";

        if(empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL))
            $errors[] = "Enter a valid email address.";

        if(empty($errors)) {
            // Check uniqueness (exclude self)
            $chk = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $chk->execute([$new_username, $new_email, $user_id]);
            if($chk->rowCount() > 0) {
                $errors[] = "Username or email is already taken by another account.";
            } else {
                $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?")
                     ->execute([$new_username, $new_email, $user_id]);
                $_SESSION['username'] = $new_username;
                $user['username']     = $new_username;
                $user['email']        = $new_email;
                $success = "Profile updated successfully.";
            }
        }
    }
}

// ── Handle: Change Password ────────────────────────────────────────
if(isset($_POST['change_password'])) {
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $errors[] = "Security validation failed. Please try again.";
    } else {
        $current  = $_POST['current_password'];
        $new_pass = $_POST['new_password'];
        $confirm  = $_POST['confirm_password'];

        // Verify current password
        $chk = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $chk->execute([$user_id]);
        $hash = $chk->fetchColumn();

        if(!password_verify($current, $hash)) {
            $errors[] = "Current password is incorrect.";
        } elseif(strlen($new_pass) < 6) {
            $errors[] = "New password must be at least 6 characters.";
        } elseif(!preg_match('/[A-Z]/', $new_pass)) {
            $errors[] = "New password must contain at least one uppercase letter.";
        } elseif(!preg_match('/[0-9]/', $new_pass)) {
            $errors[] = "New password must contain at least one number.";
        } elseif($new_pass !== $confirm) {
            $errors[] = "New passwords do not match.";
        } else {
            $conn->prepare("UPDATE users SET password = ? WHERE id = ?")
                 ->execute([password_hash($new_pass, PASSWORD_DEFAULT), $user_id]);
            $success = "Password changed successfully.";
        }
    }
}

// ── Stats ──────────────────────────────────────────────────────────
$stmt2 = $conn->prepare("SELECT COUNT(*) FROM usage_logs WHERE user_id = ?");
$stmt2->execute([$user_id]);
$total_visits = $stmt2->fetchColumn();

$stmt3 = $conn->prepare("SELECT COALESCE(SUM(credits_used),0) FROM usage_logs WHERE user_id = ?");
$stmt3->execute([$user_id]);
$credits_used = $stmt3->fetchColumn();

$stmt4 = $conn->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
$stmt4->execute([$user_id]);
$fav_count = $stmt4->fetchColumn();

// ── Top 5 most-used tools ──────────────────────────────────────────
$stmt5 = $conn->prepare(
    "SELECT tool_name, COUNT(*) AS uses, SUM(credits_used) AS spent
     FROM usage_logs WHERE user_id = ?
     GROUP BY tool_name ORDER BY uses DESC LIMIT 5"
);
$stmt5->execute([$user_id]);
$top_tools = $stmt5->fetchAll(PDO::FETCH_ASSOC);

// ── Member since ───────────────────────────────────────────────────
$member_since = isset($user['created_at'])
    ? date('d M Y', strtotime($user['created_at']))
    : 'N/A';

$username = $user['username'];
$credits  = $user['credits'];
$avatar   = strtoupper(substr($username, 0, 2)); // 2-letter avatar
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIHub – My Profile</title>
    <link rel="stylesheet" href="dashboard.css?v=4">
    <style>
    /* ── Layout ── */
    .profile-wrap {
        max-width: 960px;
        margin: 32px auto 60px;
        padding: 0 20px;
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 24px;
        align-items: start;
    }
    @media(max-width:720px) {
        .profile-wrap { grid-template-columns: 1fr; }
    }

    .back-btn {
        display: inline-block;
        margin: 24px 0 0 20px;
        text-decoration: none;
        color: #555;
        font-size: 14px;
    }
    .back-btn:hover { color: black; }

    /* ── Card base ── */
    .card-box {
        background: white;
        border-radius: 14px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .card-box-header {
        background: #111827;
        color: white;
        padding: 14px 20px;
        font-size: 14px;
        font-weight: 600;
        letter-spacing: .3px;
    }
    .card-box-body { padding: 22px 20px; }

    /* ── Avatar card ── */
    .avatar-card { text-align: center; }
    .avatar-circle {
        width: 88px; height: 88px;
        background: linear-gradient(135deg, #1f2937, #374151);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 30px; font-weight: bold; color: #fbbf24;
        margin: 0 auto 16px;
        letter-spacing: 2px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.15);
    }
    .avatar-name { font-size: 20px; font-weight: bold; margin-bottom: 4px; }
    .avatar-email { font-size: 13px; color: #888; margin-bottom: 14px; word-break: break-all; }
    .member-badge {
        display: inline-block;
        background: #f3f4f6;
        color: #555;
        font-size: 12px;
        padding: 4px 12px;
        border-radius: 20px;
        margin-bottom: 20px;
    }

    /* ── Stats grid ── */
    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 4px;
    }
    .stat-pill {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 12px 10px;
        text-align: center;
    }
    .stat-pill .num   { font-size: 20px; font-weight: bold; color: #111827; }
    .stat-pill .lbl   { font-size: 11px; color: #888; margin-top: 2px; }
    .stat-pill.gold .num { color: #f59e0b; }

    /* ── Forms ── */
    .form-group { margin-bottom: 16px; }
    .form-group label {
        display: block; font-size: 13px; font-weight: 600;
        color: #374151; margin-bottom: 6px;
    }
    .form-group input {
        width: 100%; padding: 11px 14px;
        border: 1.5px solid #d1d5db; border-radius: 8px;
        font-size: 14px; transition: border-color .2s;
        box-sizing: border-box;
    }
    .form-group input:focus { outline: none; border-color: #374151; }
    .form-group input.valid   { border-color: #16a34a; }
    .form-group input.invalid { border-color: #dc2626; }
    .field-hint { font-size: 11px; margin-top: 4px; min-height: 14px; }
    .field-hint.ok  { color: #16a34a; }
    .field-hint.err { color: #dc2626; }

    .save-btn {
        width: 100%; padding: 11px;
        background: #111827; color: white;
        border: none; border-radius: 8px;
        font-size: 14px; font-weight: 600;
        cursor: pointer; margin-top: 4px;
        transition: background .15s;
    }
    .save-btn:hover { background: #1f2937; }

    /* password strength */
    .strength-bar  { height: 4px; background: #e5e7eb; border-radius: 2px; margin: 6px 0 3px; overflow: hidden; }
    .strength-fill { height: 100%; width: 0; border-radius: 2px; transition: width .3s, background .3s; }
    .strength-lbl  { font-size: 11px; }

    /* ── Alerts ── */
    .alert {
        padding: 12px 16px; border-radius: 8px;
        font-size: 14px; margin-bottom: 20px;
    }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .alert ul { margin: 0; padding-left: 18px; }
    .alert ul li { margin-bottom: 3px; }

    /* ── Top tools table ── */
    .top-tools-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .top-tools-table th {
        background: #f9fafb;
        color: #555;
        padding: 9px 14px;
        text-align: left;
        font-weight: 600;
        border-bottom: 1px solid #e5e7eb;
    }
    .top-tools-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #f3f4f6;
        color: #333;
    }
    .top-tools-table tr:last-child td { border-bottom: none; }
    .top-tools-table tr:hover td { background: #f9fafb; }
    .rank-badge {
        display: inline-flex; align-items: center; justify-content: center;
        width: 22px; height: 22px; border-radius: 50%;
        font-size: 11px; font-weight: bold; background: #f3f4f6; color: #555;
    }
    .rank-badge.gold   { background: #fef9c3; color: #854d0e; }
    .rank-badge.silver { background: #f1f5f9; color: #475569; }
    .rank-badge.bronze { background: #fef3c7; color: #92400e; }
    .empty-tools { text-align: center; padding: 28px; color: #aaa; font-size: 14px; }

    /* ── Divider ── */
    .section-divider {
        border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;
    }

    /* ── Password toggle ── */
    .password-group {
        position: relative;
    }
    .password-toggle-icon {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #888;
        font-size: 18px;
        user-select: none;
        transition: color .2s;
    }
    .password-toggle-icon:hover {
        color: #333;
    }
    .password-group input {
        padding-right: 40px;
    }
    </style>
</head>
<body>

<nav>
    <h2 class="logo">AIHub</h2>
    <ul>
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="favorites.php">★ Favorites</a></li>
        <li><a href="history.php">History</a></li>
        <li><a href="pricing.php">Pricing</a></li>
        <li><a href="profile.php" style="color:#fbbf24;">👤 Profile</a></li>
        <li class="credits-badge">⚡ <?php echo number_format($credits); ?></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>

<a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

<div class="profile-wrap">

    <!-- ── LEFT COLUMN ── -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Avatar card -->
        <div class="card-box">
            <div class="card-box-body avatar-card">
                <div class="avatar-circle"><?php echo htmlspecialchars($avatar); ?></div>
                <div class="avatar-name"><?php echo htmlspecialchars($username); ?></div>
                <div class="avatar-email"><?php echo htmlspecialchars($user['email']); ?></div>
                <span class="member-badge">📅 Member since <?php echo $member_since; ?></span>

                <div class="stats-grid">
                    <div class="stat-pill gold">
                        <div class="num">⚡ <?php echo number_format($credits); ?></div>
                        <div class="lbl">Credits left</div>
                    </div>
                    <div class="stat-pill gold">
                        <div class="num">⚡ <?php echo number_format($credits_used); ?></div>
                        <div class="lbl">Credits used</div>
                    </div>
                    <div class="stat-pill">
                        <div class="num"><?php echo number_format($total_visits); ?></div>
                        <div class="lbl">Tool visits</div>
                    </div>
                    <div class="stat-pill">
                        <div class="num">★ <?php echo number_format($fav_count); ?></div>
                        <div class="lbl">Favorites</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top used tools -->
        <div class="card-box">
            <div class="card-box-header">🏆 Top Tools Used</div>
            <div class="card-box-body" style="padding:0;">
                <?php if(count($top_tools) > 0): ?>
                <table class="top-tools-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tool</th>
                            <th>Uses</th>
                            <th>Credits</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($top_tools as $i => $t): ?>
                        <?php
                            $rankClass = match($i) {
                                0 => 'gold', 1 => 'silver', 2 => 'bronze', default => ''
                            };
                        ?>
                        <tr>
                            <td><span class="rank-badge <?php echo $rankClass; ?>"><?php echo $i+1; ?></span></td>
                            <td><?php echo htmlspecialchars($t['tool_name']); ?></td>
                            <td><?php echo $t['uses']; ?>x</td>
                            <td>⚡ <?php echo number_format($t['spent']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-tools">No tools used yet.<br>Go explore the dashboard!</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ── RIGHT COLUMN ── -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Alerts -->
        <?php if(!empty($success)): ?>
        <div class="alert alert-success">✓ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if(!empty($errors)): ?>
        <div class="alert alert-error">
            <ul><?php foreach($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <!-- Edit profile -->
        <div class="card-box">
            <div class="card-box-header">✏️ Edit Profile</div>
            <div class="card-box-body">
                <form method="POST" id="profileForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" id="p_username"
                               value="<?php echo htmlspecialchars($user['username']); ?>"
                               oninput="validatePUsername()" required>
                        <div class="field-hint" id="p_username_msg"></div>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" id="p_email"
                               value="<?php echo htmlspecialchars($user['email']); ?>"
                               oninput="validatePEmail()" required>
                        <div class="field-hint" id="p_email_msg"></div>
                    </div>
                    <button type="submit" name="update_profile" class="save-btn">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Change password -->
        <div class="card-box">
            <div class="card-box-header">🔒 Change Password</div>
            <div class="card-box-body">
                <form method="POST" id="passForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label>Current Password</label>
                        <div class="password-group">
                            <input type="password" name="current_password" id="cp_current"
                                   placeholder="Enter current password"
                                   oninput="validateCurrent()" required>
                            <span class="password-toggle-icon" onclick="togglePassword('cp_current')">👁️</span>
                        </div>
                        <div class="field-hint" id="cp_current_msg"></div>
                    </div>
                    <hr class="section-divider">
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="password-group">
                            <input type="password" name="new_password" id="cp_new"
                                   placeholder="Enter new password"
                                   oninput="validateNewPass()" required>
                            <span class="password-toggle-icon" onclick="togglePassword('cp_new')">👁️</span>
                        </div>
                        <div class="strength-bar"><div class="strength-fill" id="cp_fill"></div></div>
                        <div class="strength-lbl" id="cp_str_lbl"></div>
                        <div class="field-hint" id="cp_new_msg"></div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="password-group">
                            <input type="password" name="confirm_password" id="cp_confirm"
                                   placeholder="Repeat new password"
                                   oninput="validateConfirmPass()" required>
                            <span class="password-toggle-icon" onclick="togglePassword('cp_confirm')">👁️</span>
                        </div>
                        <div class="field-hint" id="cp_confirm_msg"></div>
                    </div>
                    <button type="submit" name="change_password" class="save-btn">Update Password</button>
                </form>
            </div>
        </div>

        <!-- Danger zone -->
        <div class="card-box" style="border:1px solid #fee2e2;">
            <div class="card-box-header" style="background:#991b1b;">⚠️ Account Info</div>
            <div class="card-box-body" style="font-size:13px;color:#555;line-height:1.7;">
                <strong>User ID:</strong> #<?php echo $user_id; ?><br>
                <strong>Username:</strong> <?php echo htmlspecialchars($username); ?><br>
                <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?><br>
                <strong>Credits Balance:</strong> ⚡ <?php echo number_format($credits); ?><br>
                <strong>Member since:</strong> <?php echo $member_since; ?>
            </div>
        </div>

    </div>
</div><!-- /profile-wrap -->

<script>
/* ── Password toggle ── */
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const type = input.type === 'password' ? 'text' : 'password';
    input.type = type;
}

/* ── Profile form validation ── */
function validatePUsername() {
    const val = document.getElementById('p_username').value.trim();
    const el  = document.getElementById('p_username');
    const msg = document.getElementById('p_username_msg');
    if(!val)                             return setF(el, msg, '', '');
    if(val.length < 3)                   return setF(el, msg, 'err', 'At least 3 characters required');
    if(val.length > 30)                  return setF(el, msg, 'err', 'Max 30 characters');
    if(!/^[a-zA-Z0-9_]+$/.test(val))    return setF(el, msg, 'err', 'Letters, numbers and underscores only');
    setF(el, msg, 'ok', '✓ Looks good');
}
function validatePEmail() {
    const val = document.getElementById('p_email').value.trim();
    const el  = document.getElementById('p_email');
    const msg = document.getElementById('p_email_msg');
    if(!val) return setF(el, msg, '', '');
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)
        ? setF(el, msg, 'ok', '✓ Valid email')
        : setF(el, msg, 'err', 'Enter a valid email');
}

/* ── Password form validation ── */
function validateCurrent() {
    const val = document.getElementById('cp_current').value;
    const el  = document.getElementById('cp_current');
    const msg = document.getElementById('cp_current_msg');
    !val ? setF(el, msg, 'err', 'Required') : setF(el, msg, 'ok', '✓');
}
function validateNewPass() {
    const val  = document.getElementById('cp_new').value;
    const el   = document.getElementById('cp_new');
    const msg  = document.getElementById('cp_new_msg');
    const fill = document.getElementById('cp_fill');
    const lbl  = document.getElementById('cp_str_lbl');
    if(!val) { setF(el, msg, '', ''); fill.style.width='0'; lbl.textContent=''; return; }
    let score = 0;
    if(val.length >= 6)           score++;
    if(val.length >= 10)          score++;
    if(/[A-Z]/.test(val))         score++;
    if(/[0-9]/.test(val))         score++;
    if(/[^a-zA-Z0-9]/.test(val))  score++;
    const lvl = [{w:'0',c:'',t:''},{w:'20%',c:'#ef4444',t:'Weak'},{w:'45%',c:'#f97316',t:'Fair'},{w:'65%',c:'#eab308',t:'Good'},{w:'85%',c:'#22c55e',t:'Strong'},{w:'100%',c:'#16a34a',t:'Very strong'}];
    const l = lvl[score] || lvl[0];
    fill.style.width = l.w; fill.style.background = l.c;
    lbl.textContent = l.t; lbl.style.color = l.c;
    if(val.length < 6)             setF(el, msg, 'err', 'At least 6 characters');
    else if(!/[A-Z]/.test(val))    setF(el, msg, 'err', 'Add an uppercase letter');
    else if(!/[0-9]/.test(val))    setF(el, msg, 'err', 'Add a number');
    else                            setF(el, msg, 'ok', '✓ Password valid');
    if(document.getElementById('cp_confirm').value) validateConfirmPass();
}
function validateConfirmPass() {
    const np  = document.getElementById('cp_new').value;
    const cf  = document.getElementById('cp_confirm').value;
    const el  = document.getElementById('cp_confirm');
    const msg = document.getElementById('cp_confirm_msg');
    if(!cf) return setF(el, msg, '', '');
    np === cf ? setF(el, msg, 'ok', '✓ Passwords match') : setF(el, msg, 'err', 'Does not match');
}

function setF(input, msgEl, type, text) {
    input.classList.remove('valid', 'invalid');
    msgEl.classList.remove('ok', 'err');
    msgEl.textContent = text;
    if(type === 'ok')  { input.classList.add('valid');   msgEl.classList.add('ok'); }
    if(type === 'err') { input.classList.add('invalid'); msgEl.classList.add('err'); }
}

document.getElementById('profileForm').addEventListener('submit', function(e) {
    validatePUsername(); validatePEmail();
    if(document.querySelectorAll('#profileForm input.invalid').length > 0) e.preventDefault();
});
document.getElementById('passForm').addEventListener('submit', function(e) {
    validateCurrent(); validateNewPass(); validateConfirmPass();
    if(document.querySelectorAll('#passForm input.invalid').length > 0) e.preventDefault();
});
</script>
</body>
</html>