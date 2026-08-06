-- ============================================================================
-- AIHub Audit Logging System
-- ============================================================================
-- Creates the audit_logs table for tracking all admin actions
-- This helps maintain security compliance and troubleshooting capabilities

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) NOT NULL COMMENT 'user, tool, category, favorite, credit',
    entity_id INT COMMENT 'ID of the affected resource',
    details JSON COMMENT 'Additional context as JSON',
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    status ENUM('success', 'failed') DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    KEY idx_admin_id (admin_id),
    KEY idx_created_at (created_at),
    KEY idx_action (action),
    KEY idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Sample Audit Log Entries (what will be logged):
-- ============================================================================
-- 
-- User Deletion:
--   action: 'delete_user'
--   entity_type: 'user'
--   entity_id: [user_id]
--   details: {'username': 'john_doe', 'email': 'john@example.com'}
--
-- Credit Addition:
--   action: 'add_credits'
--   entity_type: 'user'
--   entity_id: [user_id]
--   details: {'username': 'jane_doe', 'credits_added': 5000, 'new_balance': 12000}
--
-- Tool Addition:
--   action: 'add_tool'
--   entity_type: 'tool'
--   entity_id: [tool_id]
--   details: {'name': 'ChatGPT', 'category': 'Writing', 'cost': 10}
--
-- Category Deletion:
--   action: 'delete_category'
--   entity_type: 'category'
--   entity_id: [category_id]
--   details: {'name': 'Research', 'affected_tools': 5}
--
-- ============================================================================
-- Usage Examples:
-- ============================================================================
--
-- View all actions by an admin:
-- SELECT * FROM audit_logs WHERE admin_id = 1 ORDER BY created_at DESC LIMIT 100;
--
-- View all deletions in the past 7 days:
-- SELECT * FROM audit_logs WHERE action LIKE 'delete_%' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);
--
-- Count actions by type:
-- SELECT action, COUNT(*) as count FROM audit_logs GROUP BY action ORDER BY count DESC;
--
-- Track user deletion activity:
-- SELECT admin_id, action, entity_id, details, created_at FROM audit_logs 
-- WHERE entity_type = 'user' AND action = 'delete_user' ORDER BY created_at DESC;
--
-- ============================================================================
