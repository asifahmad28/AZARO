<?php
require_once __DIR__ . '/functions.php';
require_login();

$pdo = db();
$uid = (int) user()['id'];
$errors = [];

$stmt = $pdo->prepare("SELECT id,name,email,phone,photo,role,created_at FROM users WHERE id=? LIMIT 1");
$stmt->execute([$uid]);
$me = $stmt->fetch();

if (!$me) {
    redirect('logout.php');
}

$client = $pdo->prepare("SELECT address FROM clients WHERE user_id=? LIMIT 1");
$client->execute([$uid]);
$clientRow = $client->fetch();
$address = $clientRow['address'] ?? '';

/* Buyer order history shown directly on the profile page. */
$orders = [];
if (($me['role'] ?? '') === 'buyer') {
    $orderStmt = $pdo->prepare("
        SELECT o.id, o.date, o.price, o.status, o.courier_status, o.address,
               GROUP_CONCAT(
                   CONCAT(COALESCE(p.name, 'Product'), ' × ', od.quantity)
                   ORDER BY od.id SEPARATOR ', '
               ) AS items_summary
        FROM orders o
        LEFT JOIN order_details od ON od.order_id = o.id
        LEFT JOIN products p ON p.id = od.product_id
        WHERE o.client_id = ?
        GROUP BY o.id
        ORDER BY o.date DESC
    ");
    $orderStmt->execute([$uid]);
    $orders = $orderStmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'photo') {
            if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Please choose a profile picture.');
            }

            $newPhoto = upload_profile_photo('profile_photo');
            if (!$newPhoto) {
                throw new RuntimeException('Please upload a JPG, PNG or WEBP image up to 3 MB.');
            }

            $oldPhoto = (string) ($me['photo'] ?? '');
            $pdo->prepare("UPDATE users SET photo=? WHERE id=?")->execute([$newPhoto, $uid]);
            $_SESSION['user']['photo'] = $newPhoto;

            if ($oldPhoto !== '' && str_starts_with($oldPhoto, 'uploads/profiles/')) {
                $oldFile = __DIR__ . '/' . ltrim($oldPhoto, '/');
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }

            flash('success', 'Profile picture updated successfully.');
            redirect('profile.php');
        }

        if ($action === 'profile') {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $addr = trim($_POST['address'] ?? '');

            if ($name === '') {
                throw new RuntimeException('Name is required.');
            }

            $pdo->prepare("UPDATE users SET name=?,phone=? WHERE id=?")
                ->execute([$name, $phone, $uid]);

            if (($me['role'] ?? '') === 'buyer') {
                $pdo->prepare("
                    INSERT INTO clients(user_id,address) VALUES(?,?)
                    ON DUPLICATE KEY UPDATE address=VALUES(address)
                ")->execute([$uid, $addr]);
            }

            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['phone'] = $phone;
            flash('success', 'Profile updated successfully.');
            redirect('profile.php');
        }

        if ($action === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            $s = $pdo->prepare("SELECT password FROM users WHERE id=?");
            $s->execute([$uid]);
            $row = $s->fetch();

            if (!$row || !password_verify($current, $row['password'])) {
                throw new RuntimeException('Current password is incorrect.');
            }
            if (strlen($new) < 6) {
                throw new RuntimeException('New password must be at least 6 characters.');
            }
            if ($new !== $confirm) {
                throw new RuntimeException('New passwords do not match.');
            }

            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);

            flash('success', 'Password changed successfully.');
            redirect('profile.php');
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$photoPath = (string) ($me['photo'] ?? '');
$photoUrl = $photoPath !== '' ? BASE_URL . '/' . ltrim($photoPath, '/') : '';
$initial = strtoupper(substr(trim((string) $me['name']), 0, 1));
$title = 'My Profile';
include __DIR__ . '/partials/header.php';
?>

<section class="page">
    <div class="container profile-page">

        <div class="profile-hero">
            <div class="profile-avatar profile-avatar-large">
                <?php if ($photoUrl): ?>
                    <img src="<?= e($photoUrl) ?>" alt="<?= e($me['name']) ?>">
                <?php else: ?>
                    <?= e($initial ?: 'U') ?>
                <?php endif; ?>
            </div>
            <div class="profile-hero-copy">
                <span class="eyebrow">MY ACCOUNT</span>
                <h1><?= e($me['name']) ?></h1>
                <p><?= e($me['email']) ?> · <b><?= e(ucfirst($me['role'])) ?></b></p>
            </div>
            <?php if ($me['role'] === 'buyer'): ?>
                <a class="btn btn-light profile-orders-link" href="#order-history">Order History</a>
            <?php endif; ?>
        </div>

        <?php foreach ($errors as $x): ?>
            <div class="flash error" style="position:static;margin:12px 0"><?= e($x) ?></div>
        <?php endforeach; ?>

        <div class="profile-grid">
            <section class="panel">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">PROFILE PHOTO</span>
                        <h2>Change profile picture</h2>
                    </div>
                </div>

                <div class="profile-photo-box">
                    <div class="profile-photo-preview">
                        <?php if ($photoUrl): ?>
                            <img src="<?= e($photoUrl) ?>" alt="Profile picture">
                        <?php else: ?>
                            <?= e($initial ?: 'U') ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-photo-info">
                        <b>Choose a new picture</b>
                        <p class="muted">JPG, PNG or WEBP · maximum 3 MB</p>
                        <form method="post" enctype="multipart/form-data" class="profile-photo-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="photo">
                            <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required>
                            <button type="submit" class="btn btn-primary">Upload Picture</button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">SECURITY</span>
                        <h2>Change password</h2>
                    </div>
                </div>
                <form method="post" class="form-grid">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="field full"><label>Current password</label><input type="password" name="current_password" required></div>
                    <div class="field full"><label>New password</label><input type="password" name="new_password" minlength="6" required></div>
                    <div class="field full"><label>Confirm new password</label><input type="password" name="confirm_password" minlength="6" required></div>
                    <div class="field full"><button class="btn btn-dark">Update Password</button></div>
                </form>
            </section>
        </div>

        <section class="panel profile-details-panel">
            <div class="section-head">
                <div>
                    <span class="eyebrow">ACCOUNT DETAILS</span>
                    <h2>Personal information</h2>
                </div>
            </div>
            <form method="post" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <div class="field full"><label>Full name</label><input name="name" value="<?= e($me['name']) ?>" required></div>
                <div class="field"><label>Email</label><input value="<?= e($me['email']) ?>" disabled></div>
                <div class="field"><label>Phone</label><input name="phone" value="<?= e($me['phone'] ?? '') ?>"></div>
                <?php if ($me['role'] === 'buyer'): ?>
                    <div class="field full"><label>Delivery address</label><textarea name="address" rows="4" placeholder="House/Road, Area, City..."><?= e($address) ?></textarea></div>
                <?php endif; ?>
                <div class="field full"><button class="btn btn-primary">Save Changes</button></div>
            </form>
        </section>

        <?php if ($me['role'] === 'buyer'): ?>
            <section class="panel profile-orders-panel" id="order-history">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">PURCHASE HISTORY</span>
                        <h2>My Order History</h2>
                        <p class="muted">All orders placed from your account.</p>
                    </div>
                    <a class="btn btn-light" href="<?= BASE_URL ?>/orders.php">Open Orders</a>
                </div>

                <?php if (!$orders): ?>
                    <div class="empty-state profile-order-empty">
                        <div class="profile-order-icon">🛍️</div>
                        <h3>No orders yet</h3>
                        <p class="muted">Your purchases will appear here after you place an order.</p>
                        <a class="btn btn-primary" href="<?= BASE_URL ?>/products.php">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <div class="profile-order-list">
                        <?php foreach ($orders as $order): ?>
                            <article class="profile-order-card">
                                <div class="profile-order-top">
                                    <div>
                                        <span class="profile-order-number">ORDER #<?= e($order['id']) ?></span>
                                        <h3><?= e($order['items_summary'] ?: 'Order items') ?></h3>
                                        <small><?= e($order['date']) ?></small>
                                    </div>
                                    <strong><?= money($order['price']) ?></strong>
                                </div>
                                <div class="profile-order-meta">
                                    <span class="status-pill status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
                                    <span><b>Courier:</b> <?= e($order['courier_status'] ?? 'Not sent') ?></span>
                                    <a class="btn btn-light" href="<?= BASE_URL ?>/invoice.php?id=<?= (int) $order['id'] ?>">View Invoice</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
