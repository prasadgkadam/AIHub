<?php
/**
 * ============================================================================
 * Audit Logging Helper Functions
 * ============================================================================
 * 
 * This file provides functions for logging admin actions to the audit_logs table
 * for security compliance, troubleshooting, and activity tracking.
 * 
 * Include this file in admin.php to use audit logging functions:
 *   include "audit_logger.php";
 */

/**
 * Log an admin action to the audit_logs table
 * 
 * @param object  $conn           - PDO database connection
 * @param int     $admin_id       - ID of the admin performing the action
 * @param string  $action         - Action name (e.g., 'delete_user', 'add_credits')
 * @param string  $entity_type    - Type of entity affected ('user', 'tool', 'category', etc.)
 * @param int     $entity_id      - ID of the affected resource
 * @param array   $details        - Additional context as associative array (will be JSON encoded)
 * @param bool    $status         - true for 'success', false for 'failed'
 * 
 * @return bool - true if logged successfully, false if failed
 * 
 * @example:
 *   logAction($conn, $_SESSION['admin_id'], 'delete_user', 'user', 5, 
 *            ['username' => 'john', 'email' => 'john@example.com']);
 */
function logAction($conn, $admin_id, $action, $entity_type, $entity_id, $details = [], $status = true) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $status_str = $status ? 'success' : 'failed';
        $details_json = json_encode($details);
        
        $stmt = $conn->prepare(
            "INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, details, ip_address, user_agent, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        return $stmt->execute([
            $admin_id,
            $action,
            $entity_type,
            $entity_id,
            $details_json,
            $ip_address,
            $user_agent,
            $status_str
        ]);
    } catch (Exception $e) {
        error_log("Audit logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent audit logs for an admin
 * 
 * @param object  $conn       - PDO database connection
 * @param int     $admin_id   - Admin ID (optional, if null gets logs for all admins)
 * @param int     $limit      - Number of records to return (default 100)
 * 
 * @return array - Array of audit log records
 * 
 * @example:
 *   $logs = getAdminLogs($conn, $_SESSION['admin_id'], 50);
 */
function getAdminLogs($conn, $admin_id = null, $limit = 100) {
    try {
        if ($admin_id) {
            $stmt = $conn->prepare(
                "SELECT * FROM audit_logs WHERE admin_id = ? 
                 ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->execute([$admin_id, $limit]);
        } else {
            $stmt = $conn->prepare(
                "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->execute([$limit]);
        }
        
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON details
        foreach ($logs as &$log) {
            $log['details'] = json_decode($log['details'], true);
        }
        
        return $logs;
    } catch (Exception $e) {
        error_log("Failed to retrieve audit logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get action count statistics
 * 
 * @param object  $conn     - PDO database connection
 * @param string  $time_range - Time period ('today', 'week', 'month', 'all')
 * 
 * @return array - Actions grouped by type with counts
 * 
 * @example:
 *   $stats = getActionStats($conn, 'week');
 */
function getActionStats($conn, $time_range = 'all') {
    try {
        $where_clause = '';
        
        switch ($time_range) {
            case 'today':
                $where_clause = "WHERE DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $where_clause = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $where_clause = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            default:
                $where_clause = "";
        }
        
        $stmt = $conn->prepare(
            "SELECT action, COUNT(*) as count, status 
             FROM audit_logs $where_clause
             GROUP BY action, status 
             ORDER BY count DESC"
        );
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to retrieve action stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Get logs for a specific entity
 * 
 * @param object  $conn        - PDO database connection
 * @param string  $entity_type - Type of entity ('user', 'tool', 'category', etc.)
 * @param int     $entity_id   - ID of the entity
 * 
 * @return array - All logs related to this entity
 * 
 * @example:
 *   $userLogs = getEntityLogs($conn, 'user', 42);
 */
function getEntityLogs($conn, $entity_type, $entity_id) {
    try {
        $stmt = $conn->prepare(
            "SELECT * FROM audit_logs 
             WHERE entity_type = ? AND entity_id = ? 
             ORDER BY created_at DESC"
        );
        $stmt->execute([$entity_type, $entity_id]);
        
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON details
        foreach ($logs as &$log) {
            $log['details'] = json_decode($log['details'], true);
        }
        
        return $logs;
    } catch (Exception $e) {
        error_log("Failed to retrieve entity logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Clear old audit logs (retention policy)
 * Keeps logs for 90 days, deletes older ones
 * 
 * @param object  $conn       - PDO database connection
 * @param int     $days       - Number of days to keep (default 90)
 * 
 * @return int - Number of records deleted
 * 
 * @example:
 *   $deleted = clearOldLogs($conn, 90);  // Keep 90 days of history
 */
function clearOldLogs($conn, $days = 90) {
    try {
        $stmt = $conn->prepare(
            "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
        
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Failed to clear old logs: " . $e->getMessage());
        return 0;
    }
}

/**
 * Export audit logs to CSV
 * 
 * @param object  $conn      - PDO database connection
 * @param string  $filename  - Output filename (default: audit_logs_YYYYMMDD.csv)
 * @param string  $time_range - Time period filter ('today', 'week', 'month', 'all')
 * 
 * @return bool - true if export successful
 * 
 * @example:
 *   exportLogs($conn, 'audit_export.csv', 'month');
 */
function exportLogs($conn, $filename = null, $time_range = 'all') {
    try {
        if (!$filename) {
            $filename = 'audit_logs_' . date('Ymd') . '.csv';
        }
        
        $logs = getAdminLogs($conn, null, 10000);  // Get more records for export
        
        // Filter by time range
        $filtered_logs = [];
        foreach ($logs as $log) {
            $log_date = strtotime($log['created_at']);
            $cutoff_date = time();
            
            switch ($time_range) {
                case 'today':
                    $cutoff_date = strtotime('today');
                    break;
                case 'week':
                    $cutoff_date = strtotime('-7 days');
                    break;
                case 'month':
                    $cutoff_date = strtotime('-30 days');
                    break;
            }
            
            if ($log_date >= $cutoff_date) {
                $filtered_logs[] = $log;
            }
        }
        
        // Create CSV
        $handle = fopen($filename, 'w');
        if (!$handle) return false;
        
        // Headers
        fputcsv($handle, ['ID', 'Admin ID', 'Action', 'Entity Type', 'Entity ID', 'Details', 'IP Address', 'Status', 'Created At']);
        
        // Data rows
        foreach ($filtered_logs as $log) {
            fputcsv($handle, [
                $log['id'],
                $log['admin_id'],
                $log['action'],
                $log['entity_type'],
                $log['entity_id'],
                json_encode($log['details']),
                $log['ip_address'],
                $log['status'],
                $log['created_at']
            ]);
        }
        
        fclose($handle);
        return true;
    } catch (Exception $e) {
        error_log("Failed to export logs: " . $e->getMessage());
        return false;
    }
}
?>
