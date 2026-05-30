<?php
session_start();
include "db.php";
include "otp_functions.php";
include "rate_limiter.php";

if(!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$email = $_SESSION['reset_email'];

// Check if OTP exists for this email
$stmt = $conn->prepare("SELECT expiry FROM otp_verification WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->rowCount() == 0) {
    header("Location: forgot_password.php");
    exit();
}

if(isset($_POST['verify'])) {
    // ✅ CSRF Token Validation
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $otp = trim($_POST['otp']);
        
        // ✅ Rate Limiting Check
        if (!checkRateLimit('otp_verify_' . $email, 3, 600)) {
            $remaining = getRateLimitRemainingTime('otp_verify_' . $email);
            $error = "Too many failed OTP attempts. Please try again in " . ceil($remaining / 60) . " minutes.";
        }
        elseif (empty($otp)) {
            $error = "Please enter the OTP";
        } elseif (strlen($otp) !== 6 || !ctype_digit($otp)) {
            $error = "OTP must be 6 digits";
        } else {
            try {
                // Debug: Check if OTP exists in database
                $debug_stmt = $conn->prepare("SELECT otp, expiry FROM otp_verification WHERE email = ?");
                $debug_stmt->execute([$email]);
                
                if ($debug_stmt->rowCount() == 0) {
                    $error = "No OTP found for this email. Please request a new one.";
                } else {
                    $debug_row = $debug_stmt->fetch(PDO::FETCH_ASSOC);
                    $stored_otp = $debug_row['otp'];
                    $stored_expiry = $debug_row['expiry'];
                    
                    // Compare OTPs
                    if ($otp !== $stored_otp) {
                        $error = "OTP is incorrect. Please try again.";
                        recordAttempt('otp_verify_' . $email);
                        incrementOTPAttempts($conn, $email);
                    } elseif (time() > strtotime($stored_expiry)) {
                        $error = "OTP has expired. Please request a new one.";
                        $conn->prepare("DELETE FROM otp_verification WHERE email = ?")->execute([$email]);
                    } else {
                        // ✅ OTP is valid - clear rate limit
                        clearRateLimit('otp_verify_' . $email);
                        // OTP is valid
                        $_SESSION['otp_verified'] = true;
                        $conn->prepare("DELETE FROM otp_verification WHERE email = ?")->execute([$email]);
                        header("Location: reset_password.php");
                        exit();
                    }
                }
            } catch (Exception $e) {
                $error = "Verification error: " . $e->getMessage();
            }
        }
    }
}

if(isset($_POST['resend'])) {
    // Generate and send new OTP
    $new_otp = generateOTP(6);
    storeOTP($conn, $email, $new_otp);

    $send_result = sendOTPEmail($email, $new_otp);
    if ($send_result['success']) {
        $_SESSION['otp_message'] = $send_result['message'];
    } else {
        $conn->prepare("DELETE FROM otp_verification WHERE email = ?")->execute([$email]);
        $_SESSION['otp_message'] = $send_result['message'];
    }
}

$remaining_time = getRemainingTime($conn, $email);
?>
<!DOCTYPE html>
<html>
<head>
<title>Verify OTP</title>
<link rel="stylesheet" href="style.css">
<style>
.error-msg {
    background: #fee2e2;
    color: #991b1b;
    padding: 10px 14px;
    border-radius: 6px;
    margin-bottom: 16px;
    font-size: 14px;
    text-align: left;
    border: 1px solid #fca5a5;
}
.success-msg {
    background: #dcfce7;
    color: #166534;
    padding: 10px 14px;
    border-radius: 6px;
    margin-bottom: 16px;
    font-size: 14px;
    text-align: left;
    border: 1px solid #86efac;
}
.info-msg {
    background: #dbeafe;
    color: #0c4a6e;
    padding: 10px 14px;
    border-radius: 6px;
    margin-bottom: 16px;
    font-size: 14px;
    text-align: left;
    border: 1px solid #7dd3fc;
}
.timer {
    text-align: center;
    font-size: 14px;
    color: #666;
    margin: 15px 0;
}
.timer.expired {
    color: #dc2626;
}
.timer strong {
    font-weight: bold;
}
.resend-link {
    text-align: center;
    margin: 15px 0;
}
.resend-link button {
    background: none;
    border: none;
    color: #0066cc;
    text-decoration: underline;
    cursor: pointer;
    font-size: 14px;
    padding: 0;
}
.resend-link button:hover {
    color: #0052a3;
}
.resend-link button:disabled {
    color: #ccc;
    cursor: not-allowed;
    text-decoration: none;
}
.otp-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.otp-input-wrapper input {
    flex: 1;
    padding-right: 45px;
}
.otp-toggle-btn {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 20px;
    color: #666;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}
.otp-toggle-btn:hover {
    color: #0066cc;
}
.otp-toggle-btn:disabled {
    color: #ccc;
    cursor: not-allowed;
}
</style>
</head>
<body>

<div class="container">
    <h2>Verify Your Email</h2>
    <p style="text-align: center; color: #666; margin-bottom: 20px;">
        We've sent a 6-digit OTP to <strong><?php echo htmlspecialchars($email); ?></strong>
    </p>

    <?php if(!empty($error)): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if(isset($_SESSION['otp_message'])): ?>
    <div class="info-msg"><?php echo htmlspecialchars($_SESSION['otp_message']); ?></div>
    <?php unset($_SESSION['otp_message']); endif; ?>

    <form method="POST" id="otpForm" novalidate>
        <!-- ✅ CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="input-box">
            <div class="otp-input-wrapper">
                <input type="password" name="otp" id="otpInput" placeholder="000000" 
                       maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required>
                <button type="button" class="otp-toggle-btn" id="toggleOtpBtn" title="Show/Hide OTP">
                    👁️
                </button>
            </div>
        </div>

        <div class="timer" id="timerDisplay">
            <span id="timerText"></span>
        </div>

        <input type="submit" name="verify" value="Verify OTP" class="btn">

        <div class="resend-link">
            <p style="font-size: 14px; color: #666;">Didn't receive the code?</p>
            <button type="submit" name="resend" id="resendBtn">Resend OTP</button>
        </div>

        <div class="link">
            <a href="forgot_password.php">← Back</a>
        </div>
    </form>

</div>

<script>
const otpInput = document.getElementById('otpInput');
const timerDisplay = document.getElementById('timerText');
const timerContainer = document.getElementById('timerDisplay');
const resendBtn = document.getElementById('resendBtn');
const toggleBtn = document.getElementById('toggleOtpBtn');
let remainingSeconds = <?php echo $remaining_time; ?>;

// Ensure we have at least some time
if (remainingSeconds <= 0) {
    remainingSeconds = 300; // Default to 5 minutes if database sync issue
}

// Toggle OTP visibility
let isOtpVisible = false;
toggleBtn.addEventListener('click', function(e) {
    e.preventDefault();
    isOtpVisible = !isOtpVisible;
    otpInput.type = isOtpVisible ? 'text' : 'password';
    toggleBtn.textContent = isOtpVisible ? '🙈' : '👁️';
});

// Allow only numbers
otpInput.addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
    this.value = this.value.slice(0, 6);
});

// Timer countdown
function updateTimer() {
    if (remainingSeconds > 0) {
        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = remainingSeconds % 60;
        
        timerDisplay.innerHTML = `OTP expires in <strong>${minutes}:${seconds.toString().padStart(2, '0')}</strong>`;
        timerContainer.classList.remove('expired');
        resendBtn.disabled = true;
        otpInput.disabled = false;
        document.querySelector('input[name="verify"]').disabled = false;
        
        remainingSeconds--;
        setTimeout(updateTimer, 1000);
    } else {
        timerDisplay.innerHTML = "OTP has expired";
        timerContainer.classList.add('expired');
        resendBtn.disabled = false;
        otpInput.disabled = true;
        document.querySelector('input[name="verify"]').disabled = true;
    }
}

updateTimer();

// Handle form submission
document.getElementById('otpForm').addEventListener('submit', function(e) {
    const otp = otpInput.value;
    
    if (e.submitter.name === 'verify' && otp.length !== 6) {
        e.preventDefault();
        alert('Please enter a valid 6-digit OTP');
        return false;
    }
});
</script>

</body>
</html>
