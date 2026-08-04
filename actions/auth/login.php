<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Csrf;
use App\Core\Auth;
use App\Core\Validator;
use App\Core\Session;
use App\Core\RateLimiter;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

Csrf::verify();

$v = Validator::make($_POST, [
    'email'    => 'required|email',
    'password' => 'required'
]);

if ($v->fails()) {
    Session::put('errors', $v->errors());
    Session::put('_old', $_POST);
    redirect('auth/login.php');
}

if (!Auth::attempt($_POST['email'], $_POST['password'])) {
    if (Auth::wasLockedOut()) {
        // Tell the user the truth — "invalid password" during a lockout is
        // confusing and made the throttle look broken during testing.
        $wait = (int) ceil(RateLimiter::secondsUntilRetry($_POST['email']) / 60);
        Session::put('errors', [
            'email' => 'Too many failed attempts. Please try again in ' . max(1, $wait) . ' minute(s).',
        ]);
    } else {
        // Deliberately identical whether the email exists or the password is
        // wrong, so the form cannot be used to enumerate accounts.
        Session::put('errors', ['email' => 'Invalid email or password provided.']);
    }
    Session::put('_old', ['email' => $_POST['email']]);
    redirect('auth/login.php');
}

$user = Auth::user();

// No "Welcome back" flash toast here on purpose — the dashboard each role
// lands on already greets the user by name in its own heading, so the toast
// was a redundant "✅ Welcome back, X!" popup on top of a page that says the
// same thing a second later.

// Only ever resume a path INSIDE this application. Without this check a crafted
// `intended` value could turn the login form into an open redirect that bounces
// a freshly authenticated user to an attacker's look-alike page.
$intended = Session::get('intended');
Session::forget('intended');

if (is_string($intended) && $intended !== '' && !preg_match('#^(?:[a-z]+:)?//#i', $intended)) {
    $safePath = ltrim(parse_url($intended, PHP_URL_PATH) ?? '', '/');
    $query    = parse_url($intended, PHP_URL_QUERY);

    // Strip any leading copy of the install directory so url() does not double it.
    $basePath = trim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/');
    if ($basePath !== '' && str_starts_with($safePath, $basePath . '/')) {
        $safePath = substr($safePath, strlen($basePath) + 1);
    }

    if ($safePath !== '' && !str_contains($safePath, '..')) {
        redirect($safePath . ($query ? '?' . $query : ''));
    }
}

$redirectPath = match($user->role) {
    'admin'        => 'admin/dashboard.php',
    'manager'      => 'manager/dashboard.php',
    'receptionist' => 'staff/dashboard.php',
    default        => 'guest/dashboard.php'
};

redirect($redirectPath);
