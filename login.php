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
        content="width=device-width,initial-scale=1,viewport-fit=cover"
    >

    <meta name="theme-color" content="#111827">

    <title>
        SPL Lead Intelligence Login
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        html,
        body {
            height: 100%;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            background: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            -webkit-text-size-adjust: 100%;
        }

        .login-container {
            width: 100%;
            max-width: 460px;
        }

        .login-card {
            border: 0;
            border-radius: 16px;
        }

        .login-title {
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .login-subtitle {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .forgot-link {
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        /*
         * iOS Safari zooms on input focus when font-size < 16px.
         * Force form controls to 16px on small screens so the
         * keyboard doesn't zoom the whole page.
         */
        @media (max-width: 575.98px) {

            .form-control {
                font-size: 16px;
                min-height: 48px;
            }

            .btn {
                min-height: 48px;
                font-size: 16px;
            }

            .card-body {
                padding: 1.5rem 1.25rem !important;
            }

            .login-title {
                font-size: 1.5rem;
            }

            .login-subtitle {
                font-size: 0.875rem;
                margin-bottom: 1.25rem !important;
            }
        }

        /*
         * Safe-area inset for iPhone X+ notch/home indicator
         */
        @supports (padding: max(0px)) {

            body {
                padding-left: max(1rem, env(safe-area-inset-left));
                padding-right: max(1rem, env(safe-area-inset-right));
                padding-bottom: max(1rem, env(safe-area-inset-bottom));
                padding-top: max(1rem, env(safe-area-inset-top));
            }
        }

    </style>

</head>

<body>

    <div class="login-container">

        <div class="card login-card shadow-lg">

            <div class="card-body p-4 p-sm-5">

                <h1 class="h3 login-title mb-1">
                    SPL Lead Intelligence
                </h1>

                <p class="login-subtitle mb-4">
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


                <form method="post" novalidate>

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
                            inputmode="email"
                            autocapitalize="none"
                            autocorrect="off"
                            spellcheck="false"
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