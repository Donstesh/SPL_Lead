<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $email = strtolower(
        trim($_POST['email'] ?? '')
    );

    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare("
        SELECT *
        FROM users
        WHERE email = ?
        AND is_active = 1
        LIMIT 1
    ");

    $stmt->execute([$email]);

    $found = $stmt->fetch();

    if (
        $found
        && password_verify(
            $password,
            $found['password_hash']
        )
    ) {

        login_user($found);

        db()->prepare("
            UPDATE users
            SET last_login_at = NOW()
            WHERE id = ?
        ")->execute([
            $found['id']
        ]);

        redirect('index.php');
    }

    $error = 'Invalid email address or password.';
}

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title>
        SPL Lead Intelligence Login
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            min-height: 100vh;
            background: #111827;
        }

        .login-container {
            max-width: 480px;
        }

        .login-card {
            border: 0;
            border-radius: 16px;
        }

        .login-title {
            font-weight: 800;
        }

        .forgot-link {
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

    </style>

</head>

<body>

<div class="container py-5 login-container">

    <div class="card login-card shadow-lg mt-5">

        <div class="card-body p-5">

            <h1 class="h3 login-title mb-1">
                SPL Lead Intelligence
            </h1>

            <p class="text-muted mb-4">
                Sales intelligence dashboard
            </p>


            <?php if ($error): ?>

                <div class="alert alert-danger">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <?php foreach (get_flashes() as $flash): ?>

                <div
                    class="alert alert-<?= e($flash['type']) ?>"
                >

                    <?= e($flash['message']) ?>

                </div>

            <?php endforeach; ?>


            <form method="post">

                <?= csrf_field() ?>


                <div class="mb-3">

                    <label class="form-label">
                        Email
                    </label>

                    <input
                        class="form-control"
                        type="email"
                        name="email"
                        autocomplete="email"
                        required
                    >

                </div>


                <div class="mb-2">

                    <label class="form-label">
                        Password
                    </label>

                    <input
                        class="form-control"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <div class="text-end mb-4">

                    <a
                        href="forgot_password.php"
                        class="forgot-link"
                    >
                        Forgot your password?
                    </a>

                </div>


                <button
                    class="btn btn-dark w-100"
                    type="submit"
                >
                    Sign In
                </button>

            </form>

        </div>

    </div>

</div>

</body>

</html>


