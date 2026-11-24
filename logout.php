<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
Audit::log('logout', 'user', Auth::id(), 'Signed out');
Auth::logout();
Auth::start();
flash('success', 'You have been signed out.');
redirect('login');
