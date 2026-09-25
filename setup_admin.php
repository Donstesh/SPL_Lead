<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$count = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();

if ($count > 0) {
    exit('An administrator already exists. Delete setup_admin.php from the server.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
        $error = 'Enter a name, valid email address and a password of at least 12 characters.';
    } else {
        $stmt = db()->prepare("
            INSERT INTO users (name, email, password_hash, role)
            VALUES (?, ?, ?, 'super_admin')
        ");
        $stmt->execute([
            $name,
            strtolower($email),
            password_hash($password, PASSWORD_DEFAULT)
        ]);

        exit('Administrator created successfully. DELETE setup_admin.php now, then open login.php.');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create SPL Administrator</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:600px">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h3">Create SPL Administrator</h1>
            <p class="text-muted">This page only works while the users table is empty.</p>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
                <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
                <div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="12" required></div>
                <button class="btn btn-dark w-100">Create Administrator</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
