<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM leads WHERE id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch();

if (!$lead) {
    http_response_code(404);
    exit('Lead not found.');
}

$pageTitle = $lead['business_name'];
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e($lead['business_name']) ?></h1>
        <div class="text-muted"><?= e(trim(($lead['address_line1'] ?? '') . ', ' . ($lead['city'] ?? '') . ' ' . ($lead['postcode'] ?? ''))) ?></div>
    </div>
    <div class="text-end">
        <div class="fs-2 fw-bold"><?= (int)$lead['lead_score'] ?>/100</div>
        <div><?= e(strtoupper($lead['priority'])) ?></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Business Intelligence</h2>
                <table class="table">
                    <tr><th>Phone</th><td><?= e($lead['phone']) ?: '—' ?></td></tr>
                    <tr><th>Email</th><td><?= e($lead['public_email']) ?: '—' ?></td></tr>
                    <tr><th>Website</th><td><?= $lead['website_url'] ? '<a target="_blank" rel="noopener" href="'.e($lead['website_url']).'">'.e($lead['website_url']).'</a>' : 'None detected' ?></td></tr>
                    <tr><th>Website Status</th><td><?= e($lead['website_status']) ?></td></tr>
                    <tr><th>Rating</th><td><?= $lead['rating'] !== null ? e((string)$lead['rating']) . ' / 5' : '—' ?></td></tr>
                    <tr><th>Reviews</th><td><?= (int)$lead['review_count'] ?></td></tr>
                    <tr><th>CRM Status</th><td><?= e(str_replace('_',' ', $lead['lead_status'])) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Opportunity</h2>
                <p><strong>Estimated value</strong><br>
                £<?= number_format((float)$lead['estimated_value_min'],0) ?> – £<?= number_format((float)$lead['estimated_value_max'],0) ?></p>
                <p class="text-muted">Service-gap recommendations and automated website audits will appear here in the next phase.</p>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
