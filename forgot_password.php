<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error     = null;
$success   = null;
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));


    /*
    |--------------------------------------------------------------------------
    | VALIDATE EMAIL
    |--------------------------------------------------------------------------
    */

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        /*
        |----------------------------------------------------------------------
        | FIND ACTIVE USER
        |----------------------------------------------------------------------
        */

        $stmt = db()->prepare("
            SELECT id, name, email
            FROM users
            WHERE email = ?
            AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $found = $stmt->fetch();


        /*
        |----------------------------------------------------------------------
        | GENERIC RESPONSE
        |----------------------------------------------------------------------
        |
        | We don't tell an unknown visitor whether an account exists.
        |
        */

        $success = 'If an active account exists for that email address, a password reset request has been created.';


        if ($found) {

            /*
            |------------------------------------------------------------------
            | DELETE OLD UNUSED TOKENS
            |------------------------------------------------------------------
            */

            $delete = db()->prepare("
                DELETE FROM password_resets
                WHERE user_id = ?
                AND used_at IS NULL
            ");

            $delete->execute([$found['id']]);


            /*
            |------------------------------------------------------------------
            | CREATE CRYPTOGRAPHIC TOKEN
            |------------------------------------------------------------------
            */

            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);


            /*
            |------------------------------------------------------------------
            | STORE HASH ONLY
            |------------------------------------------------------------------
            */

            $insert = db()->prepare("
                INSERT INTO password_resets
                (user_id, token_hash, expires_at)
                VALUES
                (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ");

            $insert->execute([$found['id'], $tokenHash]);


            /*
            |------------------------------------------------------------------
            | BUILD RESET URL
            |------------------------------------------------------------------
            */

            global $config;

            $baseUrl = isset($config['app']['base_url'])
                ? rtrim($config['app']['base_url'], '/')
                : '';


            /*
            |------------------------------------------------------------------
            | FALLBACK URL
            |------------------------------------------------------------------
            */

            if ($baseUrl === '') {

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    ? 'https'
                    : 'http';

                $host = $_SERVER['HTTP_HOST'];

                $directory = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

                $baseUrl = $scheme . '://' . $host . $directory;
            }


            $resetLink = $baseUrl
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
        content="width=device-width,initial-scale=1,viewport-fit=cover"
    >

    <meta name="theme-color" content="#111827">

    <title>
        Forgot Password | SPL Lead Intelligence
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

        .reset-container {
            width: 100%;
            max-width: 520px;
        }

        .reset-card {
            border: 0;
            border-radius: 16px;
        }

        .reset-title {
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .reset-subtitle {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .reset-link-box {
            word-break: break-all;
            font-size: 0.85rem;
        }

        .reset-link-box a {
            word-break: break-all;
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

            .reset-title {
                font-size: 1.5rem;
            }

            .reset-subtitle {
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

    <div class="reset-container">

        <div class="card reset-card shadow-lg">

            <div class="card-body p-4 p-sm-5">

                <h1 class="h3 reset-title mb-2">
                    Forgot Password
                </h1>

                <p class="reset-subtitle mb-4">
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

                        <p class="mb-2 mt-2 small">
                            Email delivery has not yet been
                            connected, so use this link to
                            test the password reset:
                        </p>

                        <div class="reset-link-box">

                            <a href="<?= e($resetLink) ?>">
                                <?= e($resetLink) ?>
                            </a>

                        </div>

                    </div>

                <?php endif; ?>


                <?php if (!$success): ?>

                    <form method="post" novalidate>

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
                                inputmode="email"
                                autocapitalize="none"
                                autocorrect="off"
                                spellcheck="false"
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