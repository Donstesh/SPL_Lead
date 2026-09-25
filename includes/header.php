<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($pageTitle ?? 'SPL Lead Intelligence') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f6f7fb; }
        .sidebar { min-height:100vh; background:#111827; color:#fff; }
        .sidebar a { color:#d1d5db; text-decoration:none; display:block; padding:.7rem 1rem; border-radius:.5rem; }
        .sidebar a:hover { background:#1f2937; color:#fff; }
        .metric-card { border:0; box-shadow:0 2px 12px rgba(0,0,0,.06); }
        .brand { font-weight:800; letter-spacing:.02em; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-2 sidebar p-3">
            <div class="brand fs-5 mb-4">SPL Lead Intelligence</div>
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
