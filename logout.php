<?php
require_once 'config/session.php';

// Check if user is admin
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    adminLogout();
} else {
    logout();
}
?> 