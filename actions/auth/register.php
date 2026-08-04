<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Csrf;
use App\Core\Auth;
use App\Core\Validator;
use App\Core\Session;
use App\Core\Flash;
use App\Core\RateLimiter;
use App\Models\User;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

Csrf::verify();

// Registration is rate-limited too, otherwise a script can create thousands
// of guest accounts and flood the bookings table.
if (RateLimiter::tooManyRegistrations($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')) {
    Flash::error('Too many accounts created from this connection. Please try again later.');
    redirect('auth/register.php');
}

$v = Validator::make($_POST, [
    'full_name'        => 'required|min:3|max:120',
    'email'            => 'required|email|max:160|unique:users,email',
    'phone'            => 'required|phone',
    // `password` enforces the full policy: length + upper + lower + digit +
    // symbol + not-guessable. `min:8` alone used to let "99999" through.
    'password'         => 'required|password|confirmed',
    'password_confirm' => 'required',
    'terms'            => 'accepted',
]);

if ($v->fails()) {
    Session::put('errors', $v->errors());
    Session::put('_old', $_POST);
    redirect('auth/register.php');
}

$user = User::create([
    'full_name'     => $_POST['full_name'],
    'email'         => strtolower(trim($_POST['email'])),
    'phone'         => $_POST['phone'],
    'password_hash' => User::hashPassword($_POST['password']),
    'role'          => 'guest',
    'status'        => 'active'
]);

Auth::attempt($user->email, $_POST['password']);
Flash::success("Account registered successfully! Welcome to Serendib Grand.");
redirect('guest/dashboard.php');
