<?php
/**
 * AutoBlog SaaS - Session Management & Authentication
 */

require_once __DIR__ . '/database.php';

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function loginRequired() {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        // Check if this is an API request
        $reqUri = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = strpos($reqUri, '/api/') !== false
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

        if ($isApi) {
            jsonResponse(['error' => 'Unauthorized. Please login first.'], 401);
        } else {
            header('Location: /login.php');
            exit;
        }
    }
}

function getCurrentUserId() {
    startSession();
    return $_SESSION['user_id'] ?? null;
}

function getActiveSlot() {
    startSession();
    return $_SESSION['active_slot_id'] ?? 1;
}

function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}
