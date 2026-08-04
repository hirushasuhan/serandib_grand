<?php
declare(strict_types=1);

use App\Core\Auth;

Auth::requireRole(['receptionist', 'manager', 'admin']);
