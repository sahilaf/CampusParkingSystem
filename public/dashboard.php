<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user        = current_user();
$is_locked   = is_booking_locked();
$user_points = refresh_user_points($pdo, $user['id']);
$package_tier = $_SESSION['package_tier'] ?? 'Starter';

// Fetch latest 10 bookings including duration & points cost
$stmt = $pdo->prepare(
    'SELECT b.id, b.booking_date, b.duration_hours, b.points_cost, b.status, s.slot_code, s.zone
       FROM bookings b
       JOIN parking_slots s ON s.id = b.slot_id
      WHERE b.user_id = ?
      ORDER BY b.created_at DESC
      LIMIT 10'
);
$stmt->execute([$user['id']]);
$bookings = $stmt->fetchAll();

// Active count
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status IN ('booked','checked_in')");
$stmt2->execute([$user['id']]);
$active_count = (int) $stmt2->fetchColumn();

// Completed count
$stmt3 = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'completed'");
$stmt3->execute([$user['id']]);
$completed_count = (int) $stmt3->fetchColumn();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

function booking_badge_class(string $status): string {
    return match($status) {
        'booked'     => 'badge-booked',
        'checked_in' => 'badge-checked-in',
        'completed'  => 'badge-completed',
        'cancelled'  => 'badge-cancelled',
        default      => 'badge-cancelled',
    };
}

$page_title = 'Dashboard';
$body_page  = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- pt-24 clears the fixed navbar -->
<div class="pt-24 pb-16 px-margin-mobile md:px-margin-desktop w-full max-w-7xl mx-auto">

    <!-- Booking-lock banner -->
    <?php if ($is_locked): ?>
    <div class="alert alert-warning" role="alert">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">lock</span>
        <div>
            <strong>Booking privileges suspended</strong> — you have 3 late departures on record.
            Unlocks on <strong><?= htmlspecialchars($_SESSION['booking_locked_until'] ?? '—') ?></strong>.
        </div>
    </div>
    <?php endif; ?>

    <!-- Low / exhausted points banner -->
    <?php if ($user_points < 10): ?>
    <div class="alert alert-warning mb-md" role="alert" style="border-left:4px solid var(--clr-amber);">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">warning</span>
        <div class="flex items-center justify-between w-full flex-wrap gap-sm">
            <div>
                <strong>Low or exhausted points balance!</strong>
                You have <strong><?= $user_points ?> points</strong> left. Reserving a parking slot requires 10 points/hour.
            </div>
            <a href="<?= BASE_URL ?>/public/payment.php" class="btn btn-primary" style="padding:6px 14px; font-size:13px;">
                Recharge Points
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Flash success -->
    <?php if ($flash !== ''): ?>
    <div class="alert alert-success" role="status">
        <span class="alert-icon material-symbols-outlined" aria-hidden="true">check_circle</span>
        <?= htmlspecialchars($flash) ?>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="flex items-center justify-between flex-wrap gap-md mb-lg">
        <div class="page-header" style="margin-bottom:0;">
            <h1 class="page-title">
                Welcome back, <?= htmlspecialchars($user['name'] ?? 'Driver') ?> 👋
            </h1>
            <p class="page-subtitle">Here's an overview of your campus parking activity & reward wallet.</p>
        </div>
        <div class="flex items-center gap-sm flex-wrap">
            <a href="<?= BASE_URL ?>/public/payment.php" class="btn btn-outline flex items-center gap-xs">
                <span class="material-symbols-outlined" style="font-size:18px;">add_card</span>
                Recharge Points
            </a>
            <a href="<?= BASE_URL ?>/public/book-slot.php" class="btn btn-primary flex items-center gap-xs">
                <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
                Reserve a Slot
            </a>
        </div>
    </div>

    <!-- Stats strip (4-metric grid) -->
    <div class="dashboard-stats" aria-label="Your parking stats">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-card-label">Reward Points</span>
                <a href="<?= BASE_URL ?>/public/payment.php" style="font-size:11px; font-weight:700; color:var(--clr-secondary);">+ Top Up</a>
            </div>
            <span class="stat-card-value stat-card-value--cyan" style="color:var(--clr-secondary);"><?= $user_points ?> <span style="font-size:16px; font-weight:500;">pts</span></span>
            <span style="font-size:12px; color:var(--clr-text-muted); margin-top:2px;">
                Tier: <strong><?= htmlspecialchars($package_tier) ?></strong> (<?= floor($user_points / 10) ?> hrs available)
            </span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label">Active Bookings</span>
            <span class="stat-card-value stat-card-value--violet"><?= $active_count ?></span>
            <span style="font-size:12px; color:var(--clr-text-muted); margin-top:2px;">Currently scheduled</span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label">Completed Trips</span>
            <span class="stat-card-value stat-card-value--success"><?= $completed_count ?></span>
            <span style="font-size:12px; color:var(--clr-text-muted); margin-top:2px;">All-time parking bays</span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label">Late Departures</span>
            <span class="stat-card-value <?= $is_locked ? 'stat-card-value--terra' : '' ?>">
                <?= (int) ($_SESSION['late_count'] ?? 0) ?> / 3
            </span>
            <span style="font-size:12px; color:var(--clr-text-muted); margin-top:2px;">3 warnings = 24h freeze</span>
        </div>
    </div>

    <!-- Booking history -->
    <section aria-labelledby="historyTitle">

        <h2 class="page-title mb-md" style="font-size:22px;" id="historyTitle">Recent Bookings</h2>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <span class="empty-state-icon material-symbols-outlined" aria-hidden="true" style="font-size:56px;">local_parking</span>
                <p class="empty-state-title">No bookings yet</p>
                <p class="empty-state-desc">Reserve your first campus parking spot to get started with your 100 reward points.</p>
                <a href="<?= BASE_URL ?>/public/book-slot.php" class="btn btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
                    Book a Slot
                </a>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table" aria-label="Your recent bookings">
                    <thead>
                        <tr>
                            <th scope="col">Booking ID</th>
                            <th scope="col">Slot</th>
                            <th scope="col">Zone</th>
                            <th scope="col">Date</th>
                            <th scope="col">Duration & Cost</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): 
                            $duration = (int) ($b['duration_hours'] ?? 1);
                            $pts      = (int) ($b['points_cost'] ?? ($duration * 10));
                        ?>
                        <tr>
                            <td class="font-semi text-muted">
                                #CP-<?= str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT) ?>
                            </td>
                            <td class="font-bold"><?= htmlspecialchars($b['slot_code']) ?></td>
                            <td><?= htmlspecialchars($b['zone']) ?></td>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($b['booking_date']))) ?></td>
                            <td>
                                <strong><?= $duration ?> hr<?= $duration > 1 ? 's' : '' ?></strong>
                                <span class="text-muted" style="font-size:12px;">(<?= $pts ?> pts)</span>
                            </td>
                            <td>
                                <span class="badge <?= booking_badge_class($b['status']) ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $b['status']))) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </section>

</div><!-- /max-w-7xl -->

<?php
// TODO (later update): check-in/check-out interface, notifications, PDF export, vehicles.
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
