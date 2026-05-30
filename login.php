<?php
session_start();
include "db.php";
include "rate_limiter.php";

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
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $captcha  = trim($_POST['captcha'] ?? '');

        // ✅ Rate Limiting Check
        if (!checkRateLimit($username, 5, 900)) {
            $remaining = getRateLimitRemainingTime($username);
            $error = "Too many failed login attempts. Please try again in " . ceil($remaining / 60) . " minutes.";
        }
        // Validate CAPTCHA first
        elseif(empty($captcha)) {
            $error = "CAPTCHA code is required.";
        } elseif($captcha !== $_SESSION['captcha_code']) {
            $error = "Incorrect CAPTCHA code. Please try again.";
            $_SESSION['captcha_code'] = strtoupper(bin2hex(random_bytes(3)));
        } elseif(empty($username) || empty($password)) {
            $error = "Username and password are required.";
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $row  = $stmt->fetch(PDO::FETCH_ASSOC);

            if($row && password_verify($password, $row['password'])) {
                // ✅ Clear rate limit on success
                clearRateLimit($username);
                // ✅ Regenerate session ID for security
                session_regenerate_id(true);
                $_SESSION['username'] = $row['username'];
                $_SESSION['user_id']  = $row['id'];
                $_SESSION['session_regenerated'] = true;
                unset($_SESSION['captcha_code']);
                header("Location: dashboard.php");
                exit();
            } else {
                // ✅ Record failed attempt
                recordAttempt($username);
                $error = "Invalid username or password.";
                $_SESSION['captcha_code'] = strtoupper(bin2hex(random_bytes(3)));
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
<title>AIHub Sign-In</title>
<link rel="stylesheet" href="style.css?v=2">
<style>
.error-box {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border: 1px solid #fca5a5;
    border-radius: 10px; padding: 14px 18px; margin-bottom: 24px;
    color: #991b1b; font-size: 14px; text-align: left; font-weight: 600;
    animation: slideDown 0.3s ease;
}
.container h2 {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 28px;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.input-box { position: relative; margin-bottom: 18px; }
.input-box label {
    display: block; color: #475569; font-size: 13px; margin-bottom: 8px;
    font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
}
.input-box input {
    width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0;
    border-radius: 10px; font-size: 15px; box-sizing: border-box;
    background: #f8fafc; color: #1e293b;
    transition: all 0.3s ease;
}
.input-box input:focus {
    outline: none; border-color: #667eea; background: #ffffff;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}
.input-box input.valid   { border-color: #16a34a; background: #f0fdf4; }
.input-box input.invalid { border-color: #dc2626; background: #fef2f2; }
.input-box input::placeholder { color: #94a3b8; }
.field-msg { font-size: 12px; margin-top: 8px; min-height: 16px; text-align: left; padding-left: 2px; }
.field-msg.ok  { color: #16a34a; font-weight: 600; }
.field-msg.err { color: #dc2626; font-weight: 600; }
.show-pass {
    position: absolute; right: 14px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    font-size: 18px; color: #94a3b8; width: auto; padding: 6px;
    transition: all 0.3s ease;
}
.show-pass:hover { color: #667eea; transform: translateY(-50%) scale(1.1); }
.captcha-section {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 2px solid #e2e8f0;
    border-radius: 12px; padding: 20px; margin: 24px 0;
    text-align: center;
}
.captcha-section label {
    display: block; font-size: 12px; color: #475569;
    font-weight: 700; margin-bottom: 12px;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.captcha-box {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 3px solid #667eea;
    border-radius: 10px; padding: 18px; margin-bottom: 12px;
    font-size: 32px; font-weight: 800; letter-spacing: 6px;
    color: #1e293b; font-family: 'Courier New', monospace;
    user-select: none;
}
.captcha-controls {
    display: flex; gap: 8px; justify-content: center;
}
.captcha-refresh {
    background: #e2e8f0; border: none; color: #1f2937;
    padding: 10px 14px; border-radius: 8px; cursor: pointer;
    font-size: 12px; font-weight: 700; transition: all 0.3s ease;
}
.captcha-refresh:hover { background: #cbd5e1; transform: scale(1.05); }
.captcha-input {
    width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0;
    border-radius: 8px; font-size: 16px; text-align: center;
    letter-spacing: 3px; font-weight: bold; box-sizing: border-box;
    background: white; transition: all 0.3s ease;
}
.captcha-input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1); }
.captcha-input.valid { border-color: #16a34a; background: #f0fdf4; }
.captcha-input.invalid { border-color: #dc2626; background: #fef2f2; }
.link { margin-top: 24px; font-size: 14px; color: #64748b; }
.link a { color: #667eea; font-weight: 700; text-decoration: none; transition: all 0.3s ease; }
.link a:hover { color: #764ba2; text-decoration: underline; }
</style>
</head>
<body>
<div class="container">
    <h2>AIHub Sign-In</h2>

    <?php if(!empty($error)): ?>
    <div class="error-box">✗ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" id="loginForm" novalidate>

        <!-- ✅ CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="input-box">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" placeholder="Enter your username"
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   oninput="validateLoginUsername()" required>
        </div>
        <div class="field-msg" id="username-msg"></div>

        <div class="input-box">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" placeholder="Enter your password"
                   oninput="validateLoginPassword()" required>
            <button type="button" class="show-pass" onclick="togglePass()" id="eye">👁</button>
        </div>
        <div class="field-msg" id="password-msg"></div>

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
            <div class="field-msg" id="captcha-msg" style="margin-top: 8px;"></div>
        </div>

        <input type="submit" name="login" value="Login" class="btn">

        <div class="link">Don't have account? <a href="signup.php">Signup</a></div>
        <div class="link"><a href="forgot_password.php">Forgot Password?</a></div>
    </form>
</div>

<script>
function validateLoginUsername() {
    const val = document.getElementById('username').value.trim();
    const el  = document.getElementById('username');
    const msg = document.getElementById('username-msg');
    if(!val)          setField(el, msg, 'err', 'Username is required');
    else if(val.length < 3) setField(el, msg, 'err', 'Username too short');
    else              setField(el, msg, 'ok',  '✓ OK');
}
function validateLoginPassword() {
    const val = document.getElementById('password').value;
    const el  = document.getElementById('password');
    const msg = document.getElementById('password-msg');
    if(!val)           setField(el, msg, 'err', 'Password is required');
    else if(val.length < 6) setField(el, msg, 'err', 'Password too short');
    else               setField(el, msg, 'ok',  '✓ OK');
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
    fetch('login.php?refresh_captcha=1')
        .then(r => r.json())
        .then(d => {
            document.getElementById('captchaCode').textContent = d.code;
            document.getElementById('captcha').value = '';
            document.getElementById('captcha-msg').textContent = '';
            document.getElementById('captcha').classList.remove('valid','invalid');
        });
}
function togglePass() {
    const p = document.getElementById('password');
    const e = document.getElementById('eye');
    if(p.type === 'password') { p.type = 'text';     e.textContent = '🙈'; }
    else                      { p.type = 'password'; e.textContent = '👁'; }
}
function setField(input, msgEl, type, text) {
    input.classList.remove('valid','invalid');
    msgEl.classList.remove('ok','err');
    msgEl.textContent = text;
    if(type==='ok')  { input.classList.add('valid');   msgEl.classList.add('ok'); }
    if(type==='err') { input.classList.add('invalid'); msgEl.classList.add('err'); }
}
document.getElementById('loginForm').addEventListener('submit', function(e) {
    validateLoginUsername();
    validateLoginPassword();
    validateCaptcha();
    if(document.querySelectorAll('.input-box input.invalid, .captcha-input.invalid').length > 0) e.preventDefault();
});
</script>
</body>
</html>