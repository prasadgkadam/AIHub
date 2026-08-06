<?php
/**
 * Rate Limiter Helper Functions
 * Prevents brute force attacks by limiting login/OTP attempts
 */

/**
 * Check if an identifier has exceeded rate limit
 * @param string $identifier Email, username, IP, etc.
 * @param int $max_attempts Maximum attempts allowed
 * @param int $time_window Time window in seconds (default 900 = 15 min)
 * @return bool true if allowed, false if blocked
 */
function checkRateLimit($identifier, $max_attempts = 5, $time_window = 900) {
    $session_key = 'rate_limit_' . md5($identifier);
    
    if (!isset($_SESSION[$session_key])) {
        $_SESSION[$session_key] = [
            'attempts' => 0,
            'first_attempt' => time()
        ];
    }
    
    $data = &$_SESSION[$session_key];
    $elapsed = time() - $data['first_attempt'];
    
    // Reset if time window expired
    if ($elapsed > $time_window) {
        $data['attempts'] = 0;
        $data['first_attempt'] = time();
    }
    
    // Check if limit exceeded
    if ($data['attempts'] >= $max_attempts) {
        return false; // Blocked
    }
    
    return true; // Allowed
}

/**
 * Record a failed attempt
 * @param string $identifier Email, username, IP, etc.
 */
function recordAttempt($identifier) {
    $session_key = 'rate_limit_' . md5($identifier);
    if (!isset($_SESSION[$session_key])) {
        $_SESSION[$session_key] = ['attempts' => 0, 'first_attempt' => time()];
    }
    $_SESSION[$session_key]['attempts']++;
}

/**
 * Clear rate limit for successful action
 * @param string $identifier Email, username, IP, etc.
 */
function clearRateLimit($identifier) {
    $session_key = 'rate_limit_' . md5($identifier);
    unset($_SESSION[$session_key]);
}

/**
 * Get remaining time until rate limit resets
 * @param string $identifier Email, username, IP, etc.
 * @param int $time_window Time window in seconds (default 900 = 15 min)
 * @return int Remaining seconds
 */
function getRateLimitRemainingTime($identifier, $time_window = 900) {
    $session_key = 'rate_limit_' . md5($identifier);
    if (!isset($_SESSION[$session_key])) {
        return 0;
    }
    $elapsed = time() - $_SESSION[$session_key]['first_attempt'];
    $remaining = $time_window - $elapsed;
    return max(0, $remaining);
}

/**
 * Get current attempt count
 * @param string $identifier Email, username, IP, etc.
 * @return int Current attempt count
 */
function getAttemptCount($identifier) {
    $session_key = 'rate_limit_' . md5($identifier);
    if (!isset($_SESSION[$session_key])) {
        return 0;
    }
    return $_SESSION[$session_key]['attempts'];
}
?>
