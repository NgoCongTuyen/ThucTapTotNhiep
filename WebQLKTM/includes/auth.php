<?php
    session_start();

    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    function requireLogin() {
        if (!isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }

    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    function getCurrentUser() {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
    }
?>
