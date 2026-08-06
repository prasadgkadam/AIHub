<?php
session_start();
include "db.php";
include "rate_limiter.php";

$errors = [];

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Generate CAPTCHA ──
if(!isset($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = strtoupper(bin2hex(random_bytes(3)));
}

if(isset($_POST['signup']))
{
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $errors[] = "Security validation failed. Please try again.";
    } else {
        $username = trim($_POST['username']);
        $email    = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm  = $_POST['confirm_password'];
        $captcha  = trim($_POST['captcha'] ?? '');

        // ✅ Rate Limiting Check (prevent brute force signup attempts)
        if (!checkRateLimit('signup_' . $email, 10, 3600)) {
            $remaining = getRateLimitRemainingTime('signup_' . $email);
            $errors[] = "Too many signup attempts from this email. Please try again in " . ceil($remaining / 60) . " minutes.";
        }
        // Validate CAPTCHA first
        elseif(empty($captcha))
            $errors[] = "CAPTCHA code is required.";
        elseif($captcha !== $_SESSION['captcha_code'])
            $errors[] = "Incorrect CAPTCHA code. Please try again.";

        if(empty($username))
            $errors[] = "Username is required.";
        elseif(strlen($username) < 3)
            $errors[] = "Username must be at least 3 characters.";
        elseif(strlen($username) > 30)
            $errors[] = "Username cannot exceed 30 characters.";
        elseif(!preg_match('/^[a-zA-Z0-9_]+$/', $username))
            $errors[] = "Username can only contain letters, numbers and underscores.";

        if(empty($email))
            $errors[] = "Email is required.";
        elseif(!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = "Enter a valid email address.";

        if(empty($password))
            $errors[] = "Password is required.";
        elseif(strlen($password) < 6)
            $errors[] = "Password must be at least 6 characters.";
        elseif(!preg_match('/[A-Z]/', $password))
            $errors[] = "Password must contain at least one uppercase letter.";
        elseif(!preg_match('/[0-9]/', $password))
            $errors[] = "Password must contain at least one number.";

        if(empty($confirm))
            $errors[] = "Please confirm your password.";
        elseif($password !== $confirm)
            $errors[] = "Passwords do not match.";

        if(empty($errors))
        {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if($stmt->rowCount() > 0)
            {
                $stmt2 = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt2->execute([$username]);
                if($stmt2->rowCount() > 0)
                    $errors[] = "This username is already taken.";
                else
                    $errors[] = "This email is already registered.";
            }
        }

        if(empty($errors))
        {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // ✓ FIX: explicitly give 1000 free credits on signup
            $stmt = $conn->prepare(
                "INSERT INTO users (username, email, password, credits) VALUES (?, ?, ?, 1000)"
            );
            $stmt->execute([$username, $email, $hashed]);

            // ✅ Clear rate limit on success
            clearRateLimit('signup_' . $email);
            // ✅ Regenerate session ID for security
            session_regenerate_id(true);
            $_SESSION['username'] = $username;
            $_SESSION['user_id']  = $conn->lastInsertId();
            $_SESSION['session_regenerated'] = true;
            unset($_SESSION['captcha_code']);

            header("Location: dashboard.php");
            exit();
        } else {
            // ✅ Record signup attempt
            recordAttempt('signup_' . $email);
            // Refresh CAPTCHA on error
            $_SESSION['captcha_code'] = strtoupper(bin2hex(random_bytes(3)));
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
<title>AIHub Sign Up</title>
<link rel="stylesheet" href="style.css?v=2">
<style>
.error-box {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border: 1px solid #fca5a5;
    border-radius: 10px; padding: 14px 18px; margin-bottom: 24px; text-align: left;
    font-weight: 600; animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.error-box ul { margin: 0; padding: 0 0 0 20px; }
.error-box ul li { color: #991b1b; font-size: 13px; margin-bottom: 5px; line-height: 1.5; }
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
.password-wrapper { position: relative; }
.toggle-password { position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; font-size: 20px;
    color: #64748b; padding: 8px; transition: color 0.3s ease;
}
.toggle-password:hover { color: #475569; }
.field-msg { font-size: 12px; margin-top: 8px; min-height: 16px; text-align: left; padding-left: 2px; }
.field-msg.ok  { color: #16a34a; font-weight: 600; }
.field-msg.err { color: #dc2626; font-weight: 600; }
.strength-wrap { margin-bottom: 14px; margin-top: 10px; }
.strength-bar  { height: 5px; border-radius: 3px; background: #e2e8f0; overflow: hidden; margin-bottom: 6px; }
.strength-fill { height: 100%; width: 0%; border-radius: 3px; transition: width 0.3s ease, background 0.3s ease; }
.strength-text { font-size: 12px; text-align: left; padding-left: 2px; font-weight: 600; }
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
.container h2 {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 28px;
}
.link { margin-top: 24px; font-size: 14px; color: #64748b; }
.link a { color: #667eea; font-weight: 700; text-decoration: none; transition: all 0.3s ease; }
.link a:hover { color: #764ba2; text-decoration: underline; }
</style>
</head>
<body>
<div class="container">
    <h2>AIHub Sign Up</h2>

    <?php if(!empty($errors)): ?>
    <div class="error-box">
        <ul>
            <?php foreach($errors as $e): ?>
            <li><?php echo htmlspecialchars($e); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" id="signupForm" novalidate>

        <!-- ✅ CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="input-box">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" placeholder="Choose a unique username"
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   oninput="validateUsername()" required>
        </div>
        <div class="field-msg" id="username-msg"></div>

        <div class="input-box">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" placeholder="Enter your email"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   oninput="validateEmail()" required>
        </div>
        <div class="field-msg" id="email-msg"></div>

        <div class="input-box">
            <label for="password">Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Create a strong password"
                       oninput="validatePassword()" required>
                <button type="button" class="toggle-password" onclick="togglePassword('password')" tabindex="-1">👁️</button>
            </div>
        </div>
        <div class="strength-wrap">
            <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
            <div class="strength-text" id="strength-text"></div>
        </div>
        <div class="field-msg" id="password-msg"></div>

        <div class="input-box">
            <label for="confirm">Confirm Password</label>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="confirm" placeholder="Re-enter your password"
                       oninput="validateConfirm()" required>
                <button type="button" class="toggle-password" onclick="togglePassword('confirm')" tabindex="-1">👁️</button>
            </div>
        </div>
        <div class="field-msg" id="confirm-msg"></div>

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

        <input type="submit" name="signup" value="Register" class="btn">

        <div class="link">Already have an account? <a href="login.php">Login</a></div>
    </form>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const isPassword = field.type === 'password';
    field.type = isPassword ? 'text' : 'password';
}
function validateUsername() {
    const val=document.getElementById('username').value.trim();
    const el=document.getElementById('username');
    const msg=document.getElementById('username-msg');
    if(!val){setField(el,msg,'','');return;}
    if(val.length<3)                     setField(el,msg,'err','At least 3 characters required');
    else if(val.length>30)               setField(el,msg,'err','Max 30 characters allowed');
    else if(!/^[a-zA-Z0-9_]+$/.test(val))setField(el,msg,'err','Only letters, numbers and underscores');
    else                                  setField(el,msg,'ok','✓ Username looks good');
}
let emailCheckTimeout;
function validateEmail() {
    const val=document.getElementById('email').value.trim();
    const el=document.getElementById('email');
    const msg=document.getElementById('email-msg');
    
    if(!val){
        setField(el,msg,'','');
        return;
    }
    
    // First validate email format
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
        setField(el,msg,'err','Enter a valid email address');
        return;
    }
    
    // Format looks good, now check if email already exists (with debounce)
    clearTimeout(emailCheckTimeout);
    el.classList.remove('valid','invalid');
    msg.textContent='Checking...';
    msg.className='field-msg';
    
    emailCheckTimeout = setTimeout(() => {
        checkEmailExists(val, el, msg);
    }, 500); // Debounce 500ms to reduce server requests
}

function checkEmailExists(email, el, msg) {
    fetch('check_email.php?email=' + encodeURIComponent(email))
        .then(r => r.json())
        .then(d => {
            if(d.exists) {
                setField(el, msg, 'err', d.message);
                el.dataset.emailExists = 'true';
            } else {
                setField(el, msg, 'ok', d.message);
                el.dataset.emailExists = 'false';
            }
        })
        .catch(err => {
            console.error('Email check failed:', err);
            setField(el, msg, 'err', 'Error checking email availability');
        });
}
function validatePassword() {
    const val=document.getElementById('password').value;
    const el=document.getElementById('password');
    const msg=document.getElementById('password-msg');
    const fill=document.getElementById('strength-fill');
    const txt=document.getElementById('strength-text');
    if(!val){setField(el,msg,'','');fill.style.width='0%';txt.textContent='';return;}
    let score=0;
    if(val.length>=6) score++;
    if(val.length>=10) score++;
    if(/[A-Z]/.test(val)) score++;
    if(/[0-9]/.test(val)) score++;
    if(/[^a-zA-Z0-9]/.test(val)) score++;
    const levels=[
        {pct:'0%',bg:'',label:''},
        {pct:'25%',bg:'#ef4444',label:'Weak'},
        {pct:'50%',bg:'#f97316',label:'Fair'},
        {pct:'75%',bg:'#eab308',label:'Good'},
        {pct:'90%',bg:'#22c55e',label:'Strong'},
        {pct:'100%',bg:'#16a34a',label:'Very strong'},
    ];
    const lv=levels[score]||levels[0];
    fill.style.width=lv.pct;fill.style.background=lv.bg;
    txt.textContent=lv.label;txt.style.color=lv.bg;
    if(val.length<6)          setField(el,msg,'err','At least 6 characters required');
    else if(!/[A-Z]/.test(val))setField(el,msg,'err','Add at least one uppercase letter');
    else if(!/[0-9]/.test(val))setField(el,msg,'err','Add at least one number');
    else                        setField(el,msg,'ok','✓ Password is valid');
    if(document.getElementById('confirm').value) validateConfirm();
}
function validateConfirm() {
    const pass=document.getElementById('password').value;
    const conf=document.getElementById('confirm').value;
    const el=document.getElementById('confirm');
    const msg=document.getElementById('confirm-msg');
    if(!conf){setField(el,msg,'','');return;}
    pass===conf?setField(el,msg,'ok','✓ Passwords match'):setField(el,msg,'err','Passwords do not match');
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
    fetch('signup.php?refresh_captcha=1')
        .then(r => r.json())
        .then(d => {
            document.getElementById('captchaCode').textContent = d.code;
            document.getElementById('captcha').value = '';
            document.getElementById('captcha-msg').textContent = '';
            document.getElementById('captcha').classList.remove('valid','invalid');
        });
}
function setField(input,msgEl,type,text) {
    input.classList.remove('valid','invalid');
    msgEl.classList.remove('ok','err');
    msgEl.textContent=text;
    if(type==='ok') {input.classList.add('valid');msgEl.classList.add('ok');}
    if(type==='err'){input.classList.add('invalid');msgEl.classList.add('err');}
}
document.getElementById('signupForm').addEventListener('submit',function(e){
    validateUsername();
    validateEmail();
    validatePassword();
    validateConfirm();
    validateCaptcha();
    
    // Also check if email already exists (final check before submission)
    const emailField = document.getElementById('email');
    if(emailField.dataset.emailExists === 'true') {
        e.preventDefault();
        return false;
    }
    
    if(document.querySelectorAll('.input-box input.invalid, .captcha-input.invalid').length>0) e.preventDefault();
});
</script>
</body>
</html>