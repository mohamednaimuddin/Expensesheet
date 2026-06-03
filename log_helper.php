<?php
/**
 * Activity Log Helper
 * Include this file and use logActivity() to track user actions
 */

function logActivity($conn, $action, $description = '') {
    // Get user info from session
    $user_id = $_SESSION['user_id'] ?? 0;
    $username = $_SESSION['username'] ?? 'Unknown';
    $company_id = $_SESSION['company_id'] ?? 0;
    $company_name = $_SESSION['company_name'] ?? 'Unknown';
     // Get IP address
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    // Insert log entry
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, username, company_id, company_name, action, description, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        die("logActivity SQL prepare failed: " . $conn->error);
    }
    $stmt->bind_param("isissss", $user_id, $username, $company_id, $company_name, $action, $description, $ip_address);
    if (!$stmt->execute()) {
        die("logActivity SQL execute failed: " . $stmt->error);
    }
    $stmt->close();
}

// Common action types for consistency
define('LOG_LOGIN', 'LOGIN');
define('LOG_LOGOUT', 'LOGOUT');
define('LOG_ADD_EXPENSE', 'ADD_EXPENSE');
define('LOG_EDIT_EXPENSE', 'EDIT_EXPENSE');
define('LOG_DELETE_EXPENSE', 'DELETE_EXPENSE');
define('LOG_ADD_ADVANCE', 'ADD_ADVANCE');
define('LOG_EDIT_ADVANCE', 'EDIT_ADVANCE');
define('LOG_DELETE_ADVANCE', 'DELETE_ADVANCE');
define('LOG_ADD_VEHICLE', 'ADD_VEHICLE');
define('LOG_EDIT_VEHICLE', 'EDIT_VEHICLE');
define('LOG_DELETE_VEHICLE', 'DELETE_VEHICLE');
define('LOG_ADD_USER', 'ADD_USER');
define('LOG_EDIT_USER', 'EDIT_USER');
define('LOG_DELETE_USER', 'DELETE_USER');
define('LOG_ADD_COMPANY', 'ADD_COMPANY');
define('LOG_EDIT_COMPANY', 'EDIT_COMPANY');
define('LOG_DELETE_COMPANY', 'DELETE_COMPANY');
define('LOG_EXPORT', 'EXPORT');
define('LOG_VIEW_REPORT', 'VIEW_REPORT');
?>
