<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $stmt = db()->prepare("
        INSERT INTO campaigns
        (name, business_type, location_text, radius_miles, min_rating, min_reviews, website_filter, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
    ");

    $stmt->execute([
        trim($_POST['name']),
        trim($_POST['business_type']),
        trim($_POST['location_text']),
        (float)$_POST['radius_miles'],
        $_POST['min_rating'] !== '' ? (float)$_POST['min_rating'] : null,
        $_POST['min_reviews'] !== '' ? (int)$_POST['min_reviews'] : null,
        $_POST['website_filter'],
        user()['id'],
    ]);

    set_flash('success', 'Campaign created.');
    redirect('campaigns.php');
}

$campaigns = db()->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM leads l WHERE l.campaign_id = c.id) AS lead_count
    FROM campaigns c
    ORDER BY c.created_at DESC
")->fetchAll();

$pageTitle = 'Campaigns';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">New Campaign</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3"><label class="form-label">Campaign Name</label><input class="form-control" name="name" required placeholder="Hounslow Barbers"></div>
                    <div class="mb-3"><label class="form-label">Business Type</label><input class="form-control" name="business_type" required placeholder="Barber"></div>
                    <div class="mb-3"><label class="form-label">Location</label><input class="form-control" name="location_text" required placeholder="Hounslow, London"></div>
                    <div class="mb-3"><label class="form-label">Radius (miles)</label><input class="form-control" type="number" min="1" step="0.5" name="radius_miles" value="10" required></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Min Rating</label><input class="form-control" type="number" min="0" max="5" step="0.1" name="min_rating"></div>
                        <div class="col-6 mb-3"><label class="form-label">Min Reviews</label><input class="form-control" type="number" min="0" name="min_reviews"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Website Filter</label>
                        <select class="form-select" name="website_filter">
                            <option value="any">Any</option>
                            <option value="none">No Website</option>
                            <option value="social_only">Social Only</option>
                            <option value="existing">Existing Website</option>
                            <option value="poor">Poor Website</option>
                        </select>
                    </div>
                    <button class="btn btn-dark w-100">Create Campaign</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Campaigns</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Type</th><th>Location</th><th>Radius</th><th>Leads</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$campaigns): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No campaigns yet.</td></tr>
                    <?php else: foreach ($campaigns as $campaign): ?>
                        <tr>
                            <td><?= e($campaign['name']) ?></td>
                            <td><?= e($campaign['business_type']) ?></td>
                            <td><?= e($campaign['location_text']) ?></td>
                            <td><?= e($campaign['radius_miles']) ?> mi</td>
                            <td><?= (int)$campaign['lead_count'] ?></td>
                            <td><?= e($campaign['status']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
