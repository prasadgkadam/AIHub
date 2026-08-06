<?php
session_start();
include "db.php";

if(!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

// Check if OTP has been verified
if(!isset($_SESSION['otp_verified'])) {
    header("Location: verify_otp.php");
    exit();
}

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$email = $_SESSION['reset_email'];

if(isset($_POST['reset']))
{
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $password = $_POST['password'];
        $confirm  = $_POST['confirm'];

        if($password != $confirm)
            $error = "Passwords do not match";
        elseif(strlen($password) < 6)
            $error = "Password must be at least 6 characters";
        elseif(!preg_match('/[A-Z]/', $password))
            $error = "Password must contain at least one uppercase letter";
        elseif(!preg_match('/[0-9]/', $password))
            $error = "Password must contain at least one number";
        else
        {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE users SET password = ? WHERE email = ?")
                 ->execute([$hash, $email]);
            unset($_SESSION['reset_email']);
            unset($_SESSION['otp_verified']);
            $_SESSION['success'] = "Password updated successfully. Please log in.";
            header("Location: login.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Reset Password</title>
<link rel="stylesheet" href="style.css">
<style>
.error-msg {
    background: #fee2e2; color: #991b1b; padding: 10px 14px;
    border-radius: 6px; margin-bottom: 16px; font-size: 14px;
    text-align: left; border: 1px solid #fca5a5;
}
.input-box { position: relative; margin-bottom: 6px; }
.input-wrapper { position: relative; display: flex; align-items: center; }
.input-wrapper input {
    flex: 1; padding: 14px; padding-right: 45px; border: 1.5px solid #ccc;
    border-radius: 4px; font-size: 15px; box-sizing: border-box; transition: border-color .2s;
}
.input-box input {
    width: 100%; padding: 14px; border: 1.5px solid #ccc;
    border-radius: 4px; font-size: 15px; box-sizing: border-box; transition: border-color .2s;
}
.toggle-password-btn {
    position: absolute; right: 12px; background: none; border: none;
    cursor: pointer; font-size: 20px; color: #666; padding: 8px;
    display: flex; align-items: center; justify-content: center;
    transition: color 0.2s;
}
.toggle-password-btn:hover { color: #0066cc; }
.toggle-password-btn:disabled { color: #ccc; cursor: not-allowed; }
.input-box input:focus   { outline: none; border-color: #555; }
.input-wrapper input:focus { outline: none; border-color: #555; }
.input-box input.valid   { border-color: #16a34a; }
.input-box input.invalid { border-color: #dc2626; }
.input-wrapper input.valid   { border-color: #16a34a; }
.input-wrapper input.invalid { border-color: #dc2626; }
.field-msg { font-size: 12px; margin-bottom: 14px; min-height: 16px; text-align: left; padding-left: 2px; }
.field-msg.ok  { color: #16a34a; }
.field-msg.err { color: #dc2626; }
.strength-wrap { margin-bottom: 14px; }
.strength-bar  { height: 4px; border-radius: 2px; background: #e5e7eb; overflow: hidden; margin-bottom: 4px; }
.strength-fill { height: 100%; width: 0%; border-radius: 2px; transition: width .3s, background .3s; }
.strength-text { font-size: 11px; text-align: left; padding-left: 2px; }
</style>
</head>
<body>
<div class="container">
    <h2>Reset Password</h2>

    <?php if(!empty($error)): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" id="resetForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="input-box">
            <div class="input-wrapper">
                <input type="password" name="password" id="rpass"
                       placeholder="New Password" oninput="validatePass()" required>
                <button type="button" class="toggle-password-btn" id="togglePass" title="Show/Hide Password">
                    👁️
                </button>
            </div>
        </div>
        <div class="strength-wrap">
            <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
            <div class="strength-text" id="strength-text"></div>
        </div>
        <div class="field-msg" id="pass-msg"></div>

        <div class="input-box">
            <div class="input-wrapper">
                <input type="password" name="confirm" id="rconf"
                       placeholder="Confirm Password" oninput="validateConfirm()" required>
                <button type="button" class="toggle-password-btn" id="toggleConf" title="Show/Hide Password">
                    👁️
                </button>
            </div>
        </div>
        <div class="field-msg" id="conf-msg"></div>

        <input type="submit" name="reset" value="Update Password" class="btn">

        <div class="link"><a href="login.php">← Back to Login</a></div>
    </form>
</div>

<script>
// Toggle password visibility
let passVisible = false;
let confVisible = false;

const togglePassBtn = document.getElementById('togglePass');
const toggleConfBtn = document.getElementById('toggleConf');
const passInput = document.getElementById('rpass');
const confInput = document.getElementById('rconf');

togglePassBtn.addEventListener('click', function(e) {
    e.preventDefault();
    passVisible = !passVisible;
    passInput.type = passVisible ? 'text' : 'password';
    togglePassBtn.textContent = passVisible ? '🙈' : '👁️';
});

toggleConfBtn.addEventListener('click', function(e) {
    e.preventDefault();
    confVisible = !confVisible;
    confInput.type = confVisible ? 'text' : 'password';
    toggleConfBtn.textContent = confVisible ? '🙈' : '👁️';
});

function validatePass() {
    const val  = document.getElementById('rpass').value;
    const el   = document.getElementById('rpass');
    const msg  = document.getElementById('pass-msg');
    const fill = document.getElementById('strength-fill');
    const txt  = document.getElementById('strength-text');
    if(!val) { setField(el,msg,'',''); fill.style.width='0%'; txt.textContent=''; return; }
    let score = 0;
    if(val.length >= 6)           score++;
    if(val.length >= 10)          score++;
    if(/[A-Z]/.test(val))         score++;
    if(/[0-9]/.test(val))         score++;
    if(/[^a-zA-Z0-9]/.test(val))  score++;
    const levels=[
        {pct:'0%',bg:'',label:''},
        {pct:'25%',bg:'#ef4444',label:'Weak'},
        {pct:'50%',bg:'#f97316',label:'Fair'},
        {pct:'75%',bg:'#eab308',label:'Good'},
        {pct:'90%',bg:'#22c55e',label:'Strong'},
        {pct:'100%',bg:'#16a34a',label:'Very strong'},
    ];
    const lv = levels[score] || levels[0];
    fill.style.width = lv.pct; fill.style.background = lv.bg;
    txt.textContent = lv.label; txt.style.color = lv.bg;
    if(val.length < 6)            setField(el, msg, 'err', 'At least 6 characters required');
    else if(!/[A-Z]/.test(val))   setField(el, msg, 'err', 'Add at least one uppercase letter');
    else if(!/[0-9]/.test(val))   setField(el, msg, 'err', 'Add at least one number');
    else                           setField(el, msg, 'ok',  '✓ Password is valid');
    if(document.getElementById('rconf').value) validateConfirm();
}
function validateConfirm() {
    const pass = document.getElementById('rpass').value;
    const conf = document.getElementById('rconf').value;
    const el   = document.getElementById('rconf');
    const msg  = document.getElementById('conf-msg');
    if(!conf) { setField(el, msg, '', ''); return; }
    pass === conf
        ? setField(el, msg, 'ok',  '✓ Passwords match')
        : setField(el, msg, 'err', 'Passwords do not match');
}
function setField(input, msgEl, type, text) {
    input.classList.remove('valid','invalid');
    msgEl.classList.remove('ok','err');
    msgEl.textContent = text;
    if(type==='ok')  { input.classList.add('valid');   msgEl.classList.add('ok'); }
    if(type==='err') { input.classList.add('invalid'); msgEl.classList.add('err'); }
}
document.getElementById('resetForm').addEventListener('submit', function(e) {
    validatePass();
    validateConfirm();
    if(document.querySelectorAll('.input-box input.invalid').length > 0) e.preventDefault();
});
</script>
</body>
</html>