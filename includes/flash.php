<?php
declare(strict_types=1);

use App\Core\Flash;

$messages = Flash::all();
if (!empty($messages)):
?>
  <div class="toast-container" id="toast-container">
    <?php foreach ($messages as $type => $msg): ?>
      <div class="toast toast--<?= e($type) ?>" role="alert">
        <span class="toast__icon">
          <?php
            echo match($type) {
              'success' => icon('check-circle', 18),
              'error'   => icon('x-circle', 18),
              'warning' => icon('alert-triangle', 18),
              default   => icon('info', 18)
            };
          ?>
        </span>
        <div><?= e($msg) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
