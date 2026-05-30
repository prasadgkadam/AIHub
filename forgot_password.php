<?php
session_start();
include "db.php";
include "otp_functions.php";
include "rate_limiter.php";

$error = "";

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_POST['check'])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $rate_key = 'forgot_pwd_' . strtolower($email);

        if (empty($email)) {
            $error = "Please enter your email address.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (!checkRateLimit($rate_key, 3, 1800)) {
            $remaining = getRateLimitRemainingTime($rate_key, 1800);
            $error = "Too many password reset attempts. Please try again in " . ceil($remaining / 60) . " minutes.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() == 1) {
                $otp = generateOTP(6);

                try {
                    storeOTP($conn, $email, $otp);

                    $verify_stmt = $conn->prepare("SELECT id FROM otp_verification WHERE email = ?");
                    $verify_stmt->execute([$email]);

                    if ($verify_stmt->rowCount() == 0) {
                        $error = "Failed to store OTP in database. Please try again.";
                    } else {
                        $send_result = sendOTPEmail($email, $otp);

                        if ($send_result['success']) {
                            clearRateLimit($rate_key);
                            $_SESSION['reset_email'] = $email;
                            $_SESSION['otp_message'] = $send_result['message'];

                            header("Location: verify_otp.php");
                            exit();
                        }

                        $conn->prepare("DELETE FROM otp_verification WHERE email = ?")->execute([$email]);
                        recordAttempt($rate_key);
                        $error = $send_result['message'];
                    }
                } catch (Exception $e) {
                    $error = "Could not create password reset OTP. Please try again.";
                }
            } else {
                recordAttempt($rate_key);
                $error = "No account found with that email address";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Forgot Password</title>
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
</style>
</head>
<body>

<div class="container">

    <h2>Forgot Password</h2>

    <form method="POST">

        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <?php if(!empty($error)): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="input-box">
            <input type="email" name="email" placeholder="Enter your Email"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
        </div>

        <input type="submit" name="check" value="Send OTP" class="btn">

        <div class="link">
            <a href="login.php">Back to Login</a>
        </div>

    </form>

</div>

</body>
</html>
