<?php
/**
 * CampusConnect - global settings.
 * Edit DB_* if your WAMP MySQL user/password/port differ from the defaults.
 */
declare(strict_types=1);

// ---- Database (WAMP default: root / empty password) -------------------
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');            // use 3307 if your WAMP MySQL/MariaDB listens there
define('DB_NAME', 'campus_connect');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Application -------------------------------------------------------
define('APP_NAME', 'CampusConnect');
define('APP_BASE', '/campus_connect'); // URL path of the project (no trailing slash)
define('APP_ROOT', dirname(__DIR__));
define('APP_TIMEZONE', 'Asia/Colombo');
define('CAMPUS_NAME', 'SLIATE Campus');

// ---- Community rules -----------------------------------------------------
define('POST_MAX', 2000);             // characters per post
define('COMMENT_MAX', 500);
define('POSTS_PER_PAGE', 10);
define('REPORTS_TO_HIDE', 3);         // distinct reports that auto-hide a post/comment until a moderator reviews it
define('MAX_CLUB_JOINS', 8);          // clubs a student may belong to at once
define('POST_COOLDOWN_SEC', 10);      // minimum seconds between two posts by the same user
define('CHECKIN_EARLY_MIN', 30);      // check-in opens this many minutes before an event starts
define('DEFAULT_PASSWORD', 'student123');

define('APP_DEBUG', true);            // set to false in production

date_default_timezone_set(APP_TIMEZONE);
