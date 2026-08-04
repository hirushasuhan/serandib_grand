<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/guards/guest.php';

use App\Core\Auth;
use App\Models\Booking;
use App\Models\Review;
use App\Core\Csrf;
use App\Core\Validator;
use App\Core\Flash;

$bookingId = (int)($_GET['booking_id'] ?? 0);
$booking = Booking::findForUser($bookingId, Auth::id());

if (!$booking || $booking->status !== 'checked_out') {
    Flash::error("Reviews can only be submitted for completed stays after checkout.");
    redirect('guest/my-bookings.php');
}

if ($booking->review()) {
    Flash::info("You have already submitted a review for this stay.");
    redirect('guest/my-bookings.php');
}

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $v = Validator::make($_POST, [
        'rating'  => 'required|integer|min:1|max:5',
        'comment' => 'required|min:10|max:1000'
    ]);

    if ($v->fails()) {
        $_SESSION['errors'] = $v->errors();
        redirect('guest/review.php?booking_id=' . $bookingId);
    }

    Review::create([
        'booking_id' => $bookingId,
        'user_id'    => Auth::id(),
        'rating'     => (int)$_POST['rating'],
        'comment'    => $_POST['comment'],
        'status'     => 'pending'
    ]);

    Flash::success("Thank you for your feedback! Your review has been submitted for moderation.");
    redirect('guest/my-bookings.php');
}

$pageTitle = 'Leave a Review';

require __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="app-shell__content">
    <?php require __DIR__ . '/../includes/nav.php'; ?>

    <main id="main" class="app-shell__main">
      
      <div style="max-width: 600px; margin-inline: auto;">
        
        <div class="card" style="padding: var(--space-8);">
          <h1 class="page-title">Rate Your Stay</h1>
          <p class="text-muted" style="margin-bottom: var(--space-6);">
            Ref: <strong><?= e($booking->booking_ref) ?></strong> &bull; <?= e($booking->room()?->roomType()?->name) ?>
          </p>

          <form method="post" action="<?= url('guest/review.php?booking_id=' . $bookingId) ?>" data-validate>
            <?= Csrf::field() ?>

            <div class="form-group">
              <label class="form-label">Star Rating (1 to 5 Stars)</label>
              <?php /* <option> text is rendered by the OS/browser as a native
                       popup — it cannot contain the SVG icon() markup used
                       everywhere else on the page, so this stays plain text. */ ?>
              <select name="rating" class="form-select" required>
                <option value="5" selected>5/5 — Exceptional Stay</option>
                <option value="4">4/5 — Very Good</option>
                <option value="3">3/5 — Average</option>
                <option value="2">2/5 — Poor</option>
                <option value="1">1/5 — Very Poor</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="review-comment">Your Experience Comments</label>
              <?php /* FIX: this field was named "message" while the handler above
                       validates and inserts $_POST['comment']. Since $_POST never
                       contained a "comment" key, every submission failed the
                       `required` rule and the guest could never actually leave a
                       review — the page loaded fine, but its one purpose silently
                       never worked. */ ?>
              <textarea id="review-comment" name="comment" rows="5" class="form-textarea" placeholder="Tell us about the room cleanliness, staff service, food, and amenities..." required minlength="10" maxlength="1000"><?= e(old('comment')) ?></textarea>
              <div class="form-hint">Minimum 10 characters.</div>
              <?php if (isset($errors['comment'])): ?><div class="form-error"><?= e($errors['comment']) ?></div><?php endif; ?>
            </div>

            <button type="submit" class="btn btn--primary btn--block">Submit Guest Review &rarr;</button>
          </form>

        </div>

      </div>

    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
