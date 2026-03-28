<?php
session_start();
require_once __DIR__ . '/../Models/User.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? (string) $_POST['username'] : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    $userModel = new User();
    $user = $userModel->login($username, $password);

    if ($user) {
        $_SESSION['user'] = $user;
        header('Location: ../index.php');
        exit();
    }

    $_SESSION['flash_error'] = 'Invalid username or password.';
    header('Location: ../Views/login.php');
    exit();
}

header('Location: ../Views/login.php');
exit();
