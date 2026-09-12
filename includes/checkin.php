<?php
// checkin.php — POST-only handler for check-in action.
// Enforces a 15-minute check-in window from booking creation time.
// Never outputs HTML; always redirects back to dashboard.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login();

// Reject anything that isn't a plain POST from our own form.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

$user       = current_user();
$user_id    = (int) $user['id'];
$booking_id = (int) ($_POST['booking_id'] ?? 0);

if ($booking_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid booking.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Fetch the booking — must belong to this user, be status='booked', and for today.
// Also pull created_at so we can enforce the 15-minute check-in window.
$stmt = $pdo->prepare(
    "SELECT id, slot_id, booking_date, created_at
       FROM bookings
      WHERE id = ? AND user_id = ? AND status = 'booked' AND booking_date = CURDATE()"
);
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['flash_error'] = 'Check-in not available for this booking.';
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Enforce the 15-minute check-in window. After the window the booking is a no-show
// and counts against the user's late_departure_count (same penalty as late checkout).
$window_expired = strtotime($booking['created_at']) + (15 * 60) < time();

if ($window_expired) {
    // Auto-cancel the expired booking.
    $cancel = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $cancel->execute([$booking_id]);

    // Increment late counter.
    $inc = $pdo->prepare('UPDATE users SET late_departure_count = late_departure_count + 1 WHERE id = ?');
    $inc->execute([$user_id]);

    // Fetch updated count to decide whether to lock bookings.
    $cntStmt = $pdo->prepare('SELECT late_departure_count FROM users WHERE id = ?');
    $cntStmt->execute([$user_id]);
    $new_count = (int) $cntStmt->fetchColumn();
    $_SESSION['late_count'] = $new_count;

    if ($new_count % 3 === 0) {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $lock = $pdo->prepare('UPDATE users SET booking_locked_until = ? WHERE id = ?');
        $lock->execute([$tomorrow, $user_id]);
        $_SESSION['booking_locked_until'] = $tomorrow;
        $_SESSION['flash_error'] = "No-show recorded — check-in window (15 min) expired. You've reached {$new_count} late strikes; booking access is suspended until {$tomorrow}.";
    } else {
        $remaining = 3 - ($new_count % 3);
        $_SESSION['flash_error'] = "No-show recorded — the 15-minute check-in window had expired. Warning {$new_count}/3 — {$remaining} more will suspend your booking access.";
    }

    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Window is still open — record check-in.
$upd = $pdo->prepare(
    "UPDATE bookings SET check_in_time = NOW(), status = 'checked_in' WHERE id = ?"
);
$upd->execute([$booking_id]);

$_SESSION['flash'] = 'Checked in successfully! Your 4-hour slot has started.';
header('Location: ' . BASE_URL . '/public/dashboard.php');
exit;
