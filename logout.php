<?php
session_start();
include "config.php";
include "log_helper.php";

// Log logout activity before destroying session
logActivity($conn, LOG_LOGOUT, 'User logged out');

session_destroy();
header("Location: index.php");
exit();
