<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#111827">
    <title><?= e($pageTitle ?? 'SPL Lead Intelligence') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html,
        body {
            background: #f6f7fb;
        }

        body {
            -webkit-text-size-adjust: 100%;
        }

        /* ---------- Sidebar (desktop) ---------- */
        .sidebar {
            background: #111827;
            color: #fff;
            min-height: 100vh;
            padding: 1rem;
        }

        .sidebar a {
            color: #d1d5db;
            text-decoration: none;
            display: block;
            padding: .7rem 1rem;
            border-radius: .5rem;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #1f2937;
            color: #fff;
        }

        .brand {
            font-weight: 800;
            letter-spacing: .02em;
            color: #fff;
        }

        .metric-card {
            border: 0;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
        }

        /* ---------- Mobile top bar ---------- */
        .mobile-topbar {
            background: #111827;
            color: #fff;
            padding: .75rem 1rem;
            position: sticky;
            top: 0;
            z-index: 1030;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: max(.75rem, env(safe-area-inset-top));
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
        }

        .mobile-topbar .brand {
            font-size: 1rem;
        }

        .menu-toggle {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, .25);
            color: #fff;
            width: 42px;
            height: 42px;
            border-radius: .5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .menu-toggle:hover,
        .menu-toggle:focus {
            background: rgba(255, 255, 255, .1);
            color: #fff;
            outline: none;
        }

        .menu-toggle svg {
            width: 22px;
            height: 22px;
        }

        /* ---------- Sidebar drawer (mobile) ---------- */
        @media (max-width: 767.98px) {

            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 260px;
                max-width: 82vw;
                min-height: 100vh;
                transform: translateX(-100%);
                transition: transform .25s ease;
                z-index: 1050;
                overflow-y: auto;
                padding-top: max(1rem, env(safe-area-inset-top));
                padding-bottom: max(1rem, env(safe-area-inset-bottom));
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, .5);
                opacity: 0;
                visibility: hidden;
                transition: opacity .25s ease, visibility .25s ease;
                z-index: 1040;
            }

            .sidebar-backdrop.show {
                opacity: 1;
                visibility: visible;
            }

            body.sidebar-open {
                overflow: hidden;
            }

            main {
                padding: 1rem !important;
            }

            /* iOS: prevent zoom on input focus */
            .form-control,
            .form-select {
                font-size: 16px;
                min-height: 48px;
            }

            .btn {
                min-height: 44px;
            }
        }

        /* ---------- Desktop: hide mobile topbar ---------- */
        @media (min-width: 768px) {
            .mobile-topbar {
                display: none;
            }
        }

        /* ---------- Safe-area for notched devices ---------- */
        @supports (padding: max(0px)) {
            main {
                padding-left: max(1rem, env(safe-area-inset-left));
                padding-right: max(1rem, env(safe-area-inset-right));
                padding-bottom: max(1rem, env(safe-area-inset-bottom));
            }
        }
    </style>
</head>
<body>

<!-- Mobile top bar (hidden on desktop) -->
<div class="mobile-topbar">
    <div class="brand">SPL Lead Intelligence</div>
    <button
        type="button"
        class="menu-toggle"
        id="menuToggle"
        aria-label="Toggle navigation"
        aria-controls="sidebar"
        aria-expanded="false"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
</div>

<!-- Backdrop (mobile only) -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="container-fluid">
    <div class="row">

        <aside class="col-md-2 sidebar" id="sidebar">
            <div class="brand fs-5 mb-4 d-none d-md-block">SPL Lead Intelligence</div>

            <button
                type="button"
                class="btn btn-sm btn-outline-light d-md-none mb-3"
                id="sidebarClose"
                aria-label="Close navigation"
            >
                &times; Close
            </button>

            <a href="index.php">Dashboard</a>
            <a href="campaigns.php">Campaigns</a>
            <a href="leads.php">Leads</a>
            <a href="search.php">Find Leads</a>
            <a href="logout.php" class="mt-4">Logout</a>
        </aside>

        <main class="col-md-10 p-4">
            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>