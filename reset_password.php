<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$token = trim(
    $_GET['token']
    ?? $_POST['token']
    ?? ''
);

$error      = null;
$validReset = null;


/*
|--------------------------------------------------------------------------
| VALIDATE TOKEN FORMAT
|--------------------------------------------------------------------------
*/

if (
    $token === ''
    || !preg_match('/^[a-f0-9]{64}$/', $token)
) {

    $error = 'This password reset link is invalid.';

} else {

    /*
    |----------------------------------------------------------------------
    | HASH TOKEN
    |----------------------------------------------------------------------
    */

    $tokenHash = hash('sha256', $token);


    /*
    |----------------------------------------------------------------------
    | FIND RESET REQUEST
    |----------------------------------------------------------------------
    */

    $stmt = db()->prepare("
        SELECT
            pr.id AS reset_id,
            pr.user_id,
            pr.expires_at,
            u.name,
            u.email

        FROM password_resets pr

        INNER JOIN users u
            ON u.id = pr.user_id

        WHERE pr.token_hash = ?

        AND pr.used_at IS NULL

        AND pr.expires_at > NOW()

        AND u.is_active = 1

        LIMIT 1
    ");

    $stmt->execute([$tokenHash]);

    $validReset = $stmt->fetch();


    if (!$validReset) {

        $error = 'This password reset link has expired or has already been used.';
    }
}


/*
|--------------------------------------------------------------------------
| PROCESS NEW PASSWORD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $validReset
) {

    verify_csrf();

    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';


    /*
    |----------------------------------------------------------------------
    | PASSWORD VALIDATION
    |----------------------------------------------------------------------
    */

    if (strlen($password) < 12) {

        $error = 'Your new password must contain at least 12 characters.';

    } elseif ($password !== $confirmPassword) {

        $error = 'The passwords do not match.';

    } else {

        /*
        |------------------------------------------------------------------
        | HASH NEW PASSWORD
        |------------------------------------------------------------------
        */

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);


        /*
        |------------------------------------------------------------------
        | TRANSACTION
        |------------------------------------------------------------------
        */

        db()->beginTransaction();

        try {

            /*
            |--------------------------------------------------------------
            | UPDATE PASSWORD
            |--------------------------------------------------------------
            */

            $update = db()->prepare("
                UPDATE users
                SET password_hash = ?
                WHERE id = ?
            ");

            $update->execute([
                $passwordHash,
                $validReset['user_id']
            ]);


            /*
            |--------------------------------------------------------------
            | MARK TOKEN USED
            |--------------------------------------------------------------
            */

            $used = db()->prepare("
                UPDATE password_resets
                SET used_at = NOW()
                WHERE id = ?
            ");

            $used->execute([$validReset['reset_id']]);


            /*
            |--------------------------------------------------------------
            | INVALIDATE ALL OTHER RESET LINKS
            |--------------------------------------------------------------
            */

            $invalidate = db()->prepare("
                UPDATE password_resets
                SET used_at = NOW()
                WHERE user_id = ?
                AND used_at IS NULL
            ");

            $invalidate->execute([$validReset['user_id']]);


            db()->commit();


            /*
            |--------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------
            */

            set_flash(
                'success',
                'Your password has been changed successfully. You can now sign in with your new password.'
            );

            redirect('login.php');


        } catch (Throwable $e) {

            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $error = 'The password could not be updated. Please try again.';
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
        Reset Password | SPL Lead Intelligence
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

            /*
             * Bigger, easier-to-read help text on mobile —
             * helps users understand the 12-char minimum.
             */
            .form-text {
                font-size: 0.8125rem;
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
                    Reset Password
                </h1>


                <?php if ($error): ?>

                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>

                <?php endif; ?>


                <?php if ($validReset): ?>

                    <p class="reset-subtitle mb-4">

                        Create a new password for

                        <strong>
                            <?= e($validReset['email']) ?>
                        </strong>.

                    </p>


                    <form method="post" novalidate>

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="token"
                            value="<?= e($token) ?>"
                        >


                        <div class="mb-3">

                            <label class="form-label">
                                New Password
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                minlength="12"
                                autocomplete="new-password"
                                required
                            >

                            <div class="form-text">
                                Minimum 12 characters.
                            </div>

                        </div>


                        <div class="mb-4">

                            <label class="form-label">
                                Confirm New Password
                            </label>

                            <input
                                type="password"
                                name="confirm_password"
                                class="form-control"
                                minlength="12"
                                autocomplete="new-password"
                                required
                            >

                        </div>


                        <button
                            type="submit"
                            class="btn btn-dark w-100"
                        >
                            Change Password
                        </button>

                    </form>


                <?php else: ?>

                    <p class="reset-subtitle mb-4">

                        The reset link you used is no longer valid.
                        Request a new one to continue.

                    </p>

                    <div class="mb-3">

                        <a
                            href="forgot_password.php"
                            class="btn btn-dark w-100"
                        >
                            Request Another Reset
                        </a>

                    </div>

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