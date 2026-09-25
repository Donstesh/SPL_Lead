<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = null;
$success = null;
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $email = strtolower(
        trim($_POST['email'] ?? '')
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDATE EMAIL
    |--------------------------------------------------------------------------
    */

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid email address.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | FIND ACTIVE USER
        |--------------------------------------------------------------------------
        */

        $stmt = db()->prepare("
            SELECT
                id,
                name,
                email
            FROM users
            WHERE email = ?
            AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | GENERIC RESPONSE
        |--------------------------------------------------------------------------
        |
        | We don't tell an unknown visitor whether an account exists.
        |
        */

        $success =
            'If an active account exists for that email address, a password reset request has been created.';


        if ($user) {

            /*
            |--------------------------------------------------------------------------
            | DELETE OLD UNUSED TOKENS
            |--------------------------------------------------------------------------
            */

            $delete = db()->prepare("
                DELETE FROM password_resets
                WHERE user_id = ?
                AND used_at IS NULL
            ");

            $delete->execute([
                $user['id']
            ]);


            /*
            |--------------------------------------------------------------------------
            | CREATE CRYPTOGRAPHIC TOKEN
            |--------------------------------------------------------------------------
            */

            $token = bin2hex(
                random_bytes(32)
            );

            $tokenHash = hash(
                'sha256',
                $token
            );


            /*
            |--------------------------------------------------------------------------
            | STORE HASH ONLY
            |--------------------------------------------------------------------------
            */

            $insert = db()->prepare("
                INSERT INTO password_resets
                (
                    user_id,
                    token_hash,
                    expires_at
                )
                VALUES
                (
                    ?,
                    ?,
                    DATE_ADD(
                        NOW(),
                        INTERVAL 30 MINUTE
                    )
                )
            ");

            $insert->execute([
                $user['id'],
                $tokenHash
            ]);


            /*
            |--------------------------------------------------------------------------
            | BUILD RESET URL
            |--------------------------------------------------------------------------
            */

            global $config;

            $baseUrl = isset(
                $config['app']['base_url']
            )
                ? rtrim(
                    $config['app']['base_url'],
                    '/'
                )
                : '';


            /*
            |--------------------------------------------------------------------------
            | FALLBACK URL
            |--------------------------------------------------------------------------
            */

            if ($baseUrl === '') {

                $scheme =
                    !empty($_SERVER['HTTPS'])
                    && $_SERVER['HTTPS'] !== 'off'
                        ? 'https'
                        : 'http';

                $host =
                    $_SERVER['HTTP_HOST'];

                $directory =
                    rtrim(
                        dirname(
                            $_SERVER['SCRIPT_NAME']
                        ),
                        '/'
                    );

                $baseUrl =
                    $scheme
                    . '://'
                    . $host
                    . $directory;
            }


            $resetLink =
                $baseUrl
                . '/reset_password.php?token='
                . urlencode($token);
        }
    }
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
        Forgot Password | SPL Lead Intelligence
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

        .reset-container {
            max-width: 560px;
        }

        .reset-card {
            border: 0;
            border-radius: 16px;
        }

        .reset-link-box {
            word-break: break-all;
        }

    </style>

</head>

<body>

<div class="container py-5 reset-container">

    <div class="card reset-card shadow-lg mt-5">

        <div class="card-body p-5">

            <h1 class="h3 fw-bold mb-2">
                Forgot Password
            </h1>

            <p class="text-muted mb-4">
                Enter the email address associated
                with your SPL Lead Intelligence account.
            </p>


            <?php if ($error): ?>

                <div class="alert alert-danger">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <?php if ($success): ?>

                <div class="alert alert-success">

                    <?= e($success) ?>

                </div>

            <?php endif; ?>


            <?php if ($resetLink): ?>

                <div class="alert alert-warning">

                    <strong>
                        Development Reset Link
                    </strong>

                    <p class="mb-2 mt-2">
                        Email delivery has not yet been
                        connected, so use this link to
                        test the password reset:
                    </p>

                    <div class="reset-link-box">

                        <a
                            href="<?= e($resetLink) ?>"
                        >
                            <?= e($resetLink) ?>
                        </a>

                    </div>

                </div>

            <?php endif; ?>


            <?php if (!$success): ?>

                <form method="post">

                    <?= csrf_field() ?>

                    <div class="mb-4">

                        <label class="form-label">
                            Email Address
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            autocomplete="email"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        class="btn btn-dark w-100"
                    >
                        Request Password Reset
                    </button>

                </form>

            <?php endif; ?>


            <div class="text-center mt-4">

                <a
                    href="login.php"
                    class="text-decoration-none"
                >
                    Back to Sign In
                </a>

            </div>

        </div>

    </div>

</div>

</body>

</html>