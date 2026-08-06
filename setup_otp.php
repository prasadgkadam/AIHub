<?php
session_start();
include "db.php";

// Only allow access if not already set up (you can add authentication here)
$setup_key = $_GET['key'] ?? null;
$expected_key = md5('aihub_otp_setup'); // Change this to something unique

$message = "";
$success = false;

// Check if table already exists
$stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'aihub' AND TABLE_NAME = 'otp_verification'");
$stmt->execute();
$table_exists = $stmt->rowCount() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    if ($setup_key !== $expected_key) {
        $message = "Invalid setup key. Please use the correct setup URL.";
    } else {
        try {
            // Create OTP table
            $sql = "CREATE TABLE IF NOT EXISTS otp_verification (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                otp VARCHAR(10) NOT NULL,
                expiry DATETIME NOT NULL,
                attempts INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_email (email)
            )";
            
            $conn->exec($sql);
            
            // Create index
            $conn->exec("CREATE INDEX idx_email_expiry ON otp_verification(email, expiry)");
            
            $message = "✓ OTP table created successfully!";
            $success = true;
            $table_exists = true;
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>OTP Setup</title>
<link rel="stylesheet" href="style.css">
<style>
.setup-container {
    max-width: 600px;
    margin: 50px auto;
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.setup-title {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}
.status-box {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 14px;
}
.status-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}
.status-info {
    background: #dbeafe;
    color: #0c4a6e;
    border: 1px solid #7dd3fc;
}
.status-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
.setup-btn {
    width: 100%;
    padding: 12px;
    background: #0066cc;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s;
}
.setup-btn:hover {
    background: #0052a3;
}
.setup-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}
.instructions {
    background: #f9fafb;
    padding: 15px;
    border-radius: 6px;
    margin-top: 20px;
    font-size: 14px;
    line-height: 1.6;
}
.instructions h4 {
    margin-top: 0;
}
code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
}
</style>
</head>
<body>

<div class="setup-container">
    <h2 class="setup-title">OTP System Setup</h2>
    
    <?php if (!empty($message)): ?>
    <div class="status-box <?php echo $success ? 'status-success' : 'status-error'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($table_exists): ?>
    <div class="status-box status-success">
        ✓ OTP table is already set up and ready to use!
    </div>
    <?php else: ?>
    <div class="status-box status-info">
        The OTP verification table needs to be created. Click the button below to set it up.
    </div>
    
    <form method="POST">
        <input type="hidden" name="key" value="<?php echo urlencode($expected_key); ?>">
        <button type="submit" name="setup" class="setup-btn">Create OTP Table</button>
    </form>
    
    <div class="instructions">
        <h4>What this does:</h4>
        <ul>
            <li>Creates the <code>otp_verification</code> table</li>
            <li>Stores OTP codes with expiry times (5 minutes)</li>
            <li>Tracks failed verification attempts (max 3)</li>
            <li>Creates indexes for fast queries</li>
        </ul>
    </div>
    <?php endif; ?>
    
</div>

</body>
</html>
