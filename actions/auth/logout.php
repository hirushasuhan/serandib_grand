<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Csrf;
use App\Core\Auth;
use App\Core\Flash;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

Csrf::verify();

Auth::logout();
Flash::info("You have logged out successfully.");
redirect('auth/login.php');
