<?php
/**
 * AutoBlog SaaS - Logout Handler
 */
session_start();
session_destroy();
header('Location: /login.php');
exit;
