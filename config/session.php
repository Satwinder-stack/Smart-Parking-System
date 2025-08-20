<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdminLoggedIn() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header("Location: admin_login.php");
        exit();
    }
}

function logout() {
    session_destroy();
    header("Location: login.php");
    exit();
}

function adminLogout() {
    session_destroy();
    header("Location: admin_login.php");
    exit();
}
?> 