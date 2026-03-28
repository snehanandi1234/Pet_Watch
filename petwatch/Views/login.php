<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
$loginError = '';
if (!empty($_SESSION['flash_error'])) {
    $loginError = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in &mdash; PetWatch</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</head>
<body class="bg-light">
    <main class="d-flex justify-content-center align-items-center min-vh-100 p-3">
        <div class="card shadow-sm" style="max-width: 420px; width: 100%;">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <span class="display-4 d-block" aria-hidden="true">&#x1F43E;</span>
                    <h1 class="h3 mt-2">PetWatch</h1>
                    <p class="text-muted mb-0">Sign in to add or manage listings.</p>
                </div>

                <?php if ($loginError !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="post" action="../Controllers/login.php">
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Your username" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="********" required autocomplete="current-password">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Log in</button>
                    </div>
                </form>

                <p class="text-center mt-3 mb-0"><a href="../index.php" class="link-secondary small">&larr; Back to home</a></p>
            </div>
        </div>
    </main>
</body>
</html>
