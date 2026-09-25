<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Dashboard';

$stats = [
    'total_leads' => (int)db()->query("SELECT COUNT(*) FROM leads")->fetchColumn(),
    'hot_leads' => (int)db()->query("SELECT COUNT(*) FROM leads WHERE priority='hot'")->fetchColumn(),
    'contacted' => (int)db()->query("SELECT COUNT(*) FROM leads WHERE lead_status IN ('contacted','follow_up','interested','quote_sent','negotiation','won')")->fetchColumn(),
    'quotes' => (int)db()->query("SELECT COUNT(*) FROM quotes WHERE status IN ('sent','viewed','accepted')")->fetchColumn(),
    'won' => (int)db()->query("SELECT COUNT(*) FROM leads WHERE lead_status='won'")->fetchColumn(),
    'potential' => (float)db()->query("SELECT COALESCE(SUM(estimated_value_max),0) FROM leads WHERE lead_status NOT IN ('lost','do_not_contact')")->fetchColumn(),
];

$recent = db()->query("
    SELECT id, business_name, city, website_status, lead_score, priority, lead_status
    FROM leads
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Dashboard</h1>
        <div class="text-muted">Welcome, <?= e(user()['name']) ?></div>
    </div>
    <a href="search.php" class="btn btn-dark">Find Leads</a>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Leads', $stats['total_leads']],
        ['Hot Leads', $stats['hot_leads']],
        ['Contacted', $stats['contacted']],
        ['Quotes', $stats['quotes']],
        ['Won', $stats['won']],
        ['Potential Value', '£' . number_format($stats['potential'], 0)],
    ];
    foreach ($cards as [$label, $value]):
    ?>
    <div class="col-md-4 col-xl-2">
        <div class="card metric-card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= e($label) ?></div>
                <div class="fs-3 fw-bold"><?= e((string)$value) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold">Recent Leads</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Business</th><th>Location</th><th>Website</th><th>Score</th><th>Priority</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$recent): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No leads yet. Start your first campaign.</td></tr>
            <?php else: foreach ($recent as $lead): ?>
                <tr>
                    <td><a href="lead.php?id=<?= (int)$lead['id'] ?>"><?= e($lead['business_name']) ?></a></td>
                    <td><?= e($lead['city']) ?></td>
                    <td><?= e($lead['website_status']) ?></td>
                    <td><?= (int)$lead['lead_score'] ?>/100</td>
                    <td><?= e(strtoupper($lead['priority'])) ?></td>
                    <td><?= e(str_replace('_',' ', $lead['lead_status'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
