<?php
session_start();

// Already logged in as admin
if(isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit();
}

include "../db.php";
include "../rate_limiter.php";

$error = "";

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Generate CAPTCHA ──
if(!isset($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = strtoupper(bin2hex(random_bytes(3)));
}

if(isset($_POST['login']))
{
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } 
    // ✅ Rate Limiting - Admin login is highest risk (3 attempts per 15 minutes)
    elseif (!checkRateLimit('admin_login_' . $_POST['username'], 3, 900)) {
        $remaining_time = ceil(getRateLimitRemainingTime('admin_login_' . $_POST['username'], 900) / 60);
        $error = "Too many failed login attempts. Please try again in $remaining_time minutes.";
    }
    else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $captcha  = trim($_POST['captcha'] ?? '');

        // Validate CAPTCHA first
        if(empty($captcha)) {
            $error = "CAPTCHA code is required.";
            recordAttempt('admin_login_' . $username);
        } elseif($captcha !== $_SESSION['captcha_code']) {
            $error = "Incorrect CAPTCHA code. Please try again.";
            $_SESSION['captcha_code'] = strtoupper(bin2hex(random_bytes(3)));
            recordAttempt('admin_login_' . $username);
        } elseif(empty($username) || empty($password)) {
            $error = "Username and password are required.";
            recordAttempt('admin_login_' . $username);
        } else {
            $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if($row && password_verify($password, $row['password']))
            {
                $_SESSION['admin_id']       = $row['id'];
                $_SESSION['admin_username'] = $row['username'];
                unset($_SESSION['captcha_code']);
                clearRateLimit('admin_login_' . $username);
                session_regenerate_id(true);
                header("Location: admin.php");
                exit();
            }
            else
            {
                $error = "Invalid admin credentials";
                $_SESSION['captcha_code'] = strtoupper(bin2hex(random_bytes(3)));
                recordAttempt('admin_login_' . $username);
            }
        }
    }
}

// Generate new CAPTCHA on page load
if(isset($_GET['refresh_captcha'])) {
    $_SESSION['captcha_code'] = strtoupper(bin2hex(random_bytes(3)));
    exit(json_encode(['code' => $_SESSION['captcha_code']]));
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AIHub Admin Login</title>
    <style>
    *{ margin:0; padding:0; box-sizing:border-box; font-family:Arial,sans-serif; }
    body {
        min-height:100vh; display:flex; justify-content:center; align-items:center;
        background:linear-gradient(135deg, #dbeafe 0%, #ede9fe 100%);
    }
    .login-box {
        background:#ffffff; border:1px solid #e2e8f0;
        border-radius:14px; padding:44px 40px; width:380px; text-align:center;
        box-shadow:0 10px 25px rgba(0,0,0,0.1);
    }
    .admin-badge {
        display:inline-block; background:#3b82f6; color:white;
        padding:4px 14px; border-radius:20px; font-size:12px;
        font-weight:bold; margin-bottom:20px; letter-spacing:.05em;
    }
    h2 { color:#1e293b; font-size:22px; margin-bottom:28px; }
    .input-group { margin-bottom:18px; text-align:left; position:relative; }
    .input-group label { display:block; color:#475569; font-size:13px; margin-bottom:6px; font-weight:500; }
    .input-group input {
        width:100%; padding:12px 14px; background:#f9fafb;
        border:1px solid #d1d5db; border-radius:7px;
        color:#1e293b; font-size:15px; outline:none;
        transition: all 0.3s ease;
    }
    .input-group input:focus { border-color:#3b82f6; background:#ffffff; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
    .show-pass {
        position:absolute; right:12px; top:50%; transform:translateY(-50%);
        background:none; border:none; cursor:pointer;
        font-size:18px; color:#94a3b8; padding:6px; width:auto;
        transition:all 0.3s ease;
    }
    .show-pass:hover { color:#3b82f6; transform:translateY(-50%) scale(1.15); }
    .btn {
        width:100%; padding:13px; background:#3b82f6; color:white;
        border:none; border-radius:7px; font-size:16px;
        font-weight:bold; cursor:pointer; margin-top:6px;
    }
    .btn:hover { background:#2563eb; }
    .error-msg {
        background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;
        padding:10px 14px; border-radius:6px; font-size:13px;
        margin-bottom:18px; text-align:left;
    }
    .back-link { display:block; margin-top:20px; color:#64748b; font-size:13px; text-decoration:none; }
    .back-link:hover { color:#1e293b; }
    .captcha-section {
        background: #f9fafb; border: 1px solid #e5e7eb;
        border-radius: 8px; padding: 16px; margin: 20px 0;
        text-align: center;
    }
    .captcha-section label {
        display: block; font-size: 12px; color: #475569;
        font-weight: 500; margin-bottom: 10px;
    }
    .captcha-box {
        background: white; border: 2px solid #3b82f6;
        border-radius: 6px; padding: 16px; margin-bottom: 12px;
        font-size: 28px; font-weight: bold; letter-spacing: 4px;
        color: #1e293b; font-family: 'Courier New', monospace;
        user-select: none;
    }
    .captcha-controls {
        display: flex; gap: 8px; justify-content: center;
    }
    .captcha-refresh {
        background: #e5e7eb; border: none; color: #1f2937;
        padding: 8px 12px; border-radius: 4px; cursor: pointer;
        font-size: 12px; font-weight: bold; transition: all .2s;
    }
    .captcha-refresh:hover { background: #d1d5db; }
    .captcha-input {
        width: 100%; padding: 12px; border: 1.5px solid #d1d5db;
        border-radius: 4px; font-size: 16px; text-align: center;
        letter-spacing: 2px; font-weight: bold; box-sizing: border-box;
    }
    .captcha-input:focus { outline: none; border-color: #3b82f6; }
    .captcha-input.valid { border-color: #16a34a; }
    .captcha-input.invalid { border-color: #dc2626; }
    .field-msg { font-size: 12px; margin-top: 8px; min-height: 16px; text-align: center; }
    .field-msg.ok { color: #16a34a; }
    .field-msg.err { color: #dc2626; }
    </style>
</head>
<body>

<div class="login-box">
    <span class="admin-badge">ADMIN PANEL</span>
    <h2>AIHub Administration</h2>

    <?php if(!empty($error)): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" id="adminLoginForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <div class="input-group">
            <label>Admin Username</label>
            <input type="text" name="username" id="username" placeholder="Enter admin username"
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
        </div>
        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" id="password" placeholder="Enter password" required>
            <button type="button" class="show-pass" onclick="togglePass()" id="eye">👁</button>
        </div>

        <!-- CAPTCHA Section -->
        <div class="captcha-section">
            <label>Security Verification</label>
            <div class="captcha-box" id="captchaCode"><?php echo $_SESSION['captcha_code']; ?></div>
            <div class="captcha-controls">
                <button type="button" class="captcha-refresh" onclick="refreshCaptcha()">🔄 Refresh</button>
            </div>
            <div style="margin-top: 10px;">
                <input type="text" name="captcha" id="captcha" class="captcha-input"
                       placeholder="Enter the code above" oninput="validateCaptcha()" required>
            </div>
            <div class="field-msg" id="captcha-msg"></div>
        </div>

        <button type="submit" name="login" class="btn">Login to Admin Panel</button>
    </form>

    <a href="../index.php" class="back-link">← Back to main site</a>
</div>

<script>
function togglePass() {
    const p = document.getElementById('password');
    const e = document.getElementById('eye');
    if(p.type === 'password') {
        p.type = 'text';
        e.textContent = '🙈';
    } else {
        p.type = 'password';
        e.textContent = '👁';
    }
}
function validateCaptcha() {
    const val = document.getElementById('captcha').value.toUpperCase().trim();
    const el  = document.getElementById('captcha');
    const msg = document.getElementById('captcha-msg');
    if(!val)     setField(el, msg, 'err', 'CAPTCHA code is required');
    else if(val.length !== 6) setField(el, msg, 'err', 'Code must be 6 characters');
    else         setField(el, msg, 'ok',  '✓ Code entered');
}
function refreshCaptcha() {
    fetch('admin_login.php?refresh_captcha=1')
        .then(r => r.json())
        .then(d => {
            document.getElementById('captchaCode').textContent = d.code;
            document.getElementById('captcha').value = '';
            document.getElementById('captcha-msg').textContent = '';
            document.getElementById('captcha').classList.remove('valid','invalid');
        });
}
function setField(input, msgEl, type, text) {
    input.classList.remove('valid','invalid');
    msgEl.classList.remove('ok','err');
    msgEl.textContent = text;
    if(type==='ok')  { input.classList.add('valid');   msgEl.classList.add('ok'); }
    if(type==='err') { input.classList.add('invalid'); msgEl.classList.add('err'); }
}
document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
    validateCaptcha();
    if(document.querySelector('.captcha-input.invalid')) e.preventDefault();
});
</script>
</body>
</html>
