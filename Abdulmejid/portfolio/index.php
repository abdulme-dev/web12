<?php
session_start();

$dbHost = getenv('PORTFOLIO_DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('PORTFOLIO_DB_PORT') ?: '3306';
$dbName = getenv('PORTFOLIO_DB_NAME') ?: 'abdulmejid_portfolio';
$dbUser = getenv('PORTFOLIO_DB_USER');
$dbPassword = getenv('PORTFOLIO_DB_PASSWORD');

if ($dbUser === false || $dbPassword === false) {
    http_response_code(500);
    exit('Set the MySQL username and password in the environment before starting the site.');
}

if (!preg_match('/\A[A-Za-z0-9_]+\z/', $dbName) || filter_var($dbPort, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
    http_response_code(500);
    exit('The database settings are invalid. Check the database name and port.');
}

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $serverDsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $server = new PDO($serverDsn, $dbUser, $dbPassword, $options);
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db = new PDO($serverDsn . ";dbname={$dbName}", $dbUser, $dbPassword, $options);
    $db->exec('CREATE TABLE IF NOT EXISTS messages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        message VARCHAR(1000) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit('The database is not ready. Check your MySQL settings and make sure the database user can create databases.');
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= strtoupper(substr($part, 0, 1));
    }
    return $letters !== '' ? $letters : '?';
}

function prettyDate($value) {
    $time = strtotime($value);
    return $time ? date('M j, Y \a\t g:i A', $time) : $value;
}

$page = $_GET['page'] ?? 'home';
$error = '';
$name = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($name === '' || $message === '') {
        $error = 'Please enter your name and a message.';
    } elseif (strlen($name) > 80 || strlen($message) > 1000) {
        $error = 'Please keep your name under 80 characters and your message under 1,000 characters.';
    } else {
        $statement = $db->prepare('INSERT INTO messages (name, message) VALUES (?, ?)');
        $statement->execute([$name, $message]);
        $_SESSION['flash'] = 'Your hi has been sent. Thank you for reaching out.';
        header('Location: index.php?sent=1#contact');
        exit;
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$messages = [];
$messageCount = 0;
$latestMessage = 'No messages yet';

if ($page === 'messages') {
    $statement = $db->prepare('SELECT id, name, message, created_at FROM messages ORDER BY id DESC');
    $statement->execute();
    $messages = $statement->fetchAll();
    $messageCount = count($messages);
    if ($messageCount > 0) {
        $latestMessage = prettyDate($messages[0]['created_at']);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Abdulmejid is a grade 12 student at Kings School focused on cybersecurity, Python, and web development.">
    <title><?= $page === 'messages' ? 'Inbox' : 'Abdulmejid' ?> | Student Portfolio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1b1c18;
            --primary-dark: #30322b;
            --primary-soft: #ebe8dd;
            --secondary: #c9f16f;
            --light: #f5f3ec;
            --canvas: #ffffff;
            --dark: #1b1c18;
            --subtle: #73756a;
            --border: #e2ded1;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 84px;
        }

        body {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            margin: 0;
            padding-top: 84px;
            overflow-x: hidden;
            background: var(--canvas);
            color: var(--dark);
            font-family: "Open Sans", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        main {
            flex: 1 0 auto;
        }

        ::selection {
            background: var(--secondary);
            color: var(--dark);
        }

        a {
            color: inherit;
        }

        section {
            scroll-margin-top: 84px;
        }

        .site-navbar {
            min-height: 84px;
            padding: 0;
            background: rgba(255, 253, 248, 0.96);
            border-bottom: 1px solid rgba(226, 222, 209, 0.84);
            box-shadow: 0 8px 30px rgba(27, 28, 24, 0.05);
            backdrop-filter: blur(16px);
        }

        .site-navbar .container {
            min-height: 84px;
        }

        .mobile-brand {
            color: var(--dark);
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.05em;
            text-decoration: none;
        }

        .mobile-brand span {
            color: var(--primary);
        }

        .navbar-toggler {
            padding: 7px 10px;
            border: 1px solid var(--border);
            border-radius: 11px;
            box-shadow: none !important;
        }

        .navbar-collapse {
            min-height: 84px;
        }

        .nav-side {
            flex: 1;
            align-items: center;
            gap: 2px;
        }

        .nav-end {
            justify-content: flex-end;
        }

        .site-navbar .nav-link {
            padding: 12px 14px !important;
            border-radius: 999px;
            color: var(--dark);
            font-size: 0.93rem;
            font-weight: 600;
            transition: color 0.2s ease, background 0.2s ease;
        }

        .site-navbar .nav-link:hover,
        .site-navbar .nav-link.active {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .site-navbar .inbox-link {
            margin-left: 8px;
            padding: 10px 17px !important;
            background: var(--primary);
            color: #fff !important;
        }

        .site-navbar .inbox-link:hover,
        .site-navbar .inbox-link.active {
            background: var(--primary-dark);
        }

        .brand-center {
            display: grid;
            width: 62px;
            height: 62px;
            flex: 0 0 auto;
            place-items: center;
            background: var(--secondary);
            border-radius: 18px;
            box-shadow: 0 8px 20px rgba(201, 241, 111, 0.34);
            color: var(--primary);
            font-size: 1.9rem;
            font-weight: 800;
            line-height: 1;
            text-decoration: none;
            transition: transform 0.2s ease;
        }

        .brand-center:hover {
            color: var(--primary);
            transform: rotate(-4deg) scale(1.04);
        }

        .hero {
            position: relative;
            overflow: hidden;
            padding: 76px 0 64px;
            background: var(--light);
        }

        .hero::before {
            position: absolute;
            top: -240px;
            right: -180px;
            width: 680px;
            height: 680px;
            background: radial-gradient(circle, rgba(201, 241, 111, 0.24), transparent 68%);
            border-radius: 50%;
            content: "";
        }

        .hero::after {
            position: absolute;
            bottom: 40px;
            left: -70px;
            width: 170px;
            height: 170px;
            background: radial-gradient(circle, rgba(27, 28, 24, 0.1), transparent 70%);
            border-radius: 50%;
            content: "";
        }

        .hero .container {
            position: relative;
            z-index: 2;
        }

        .hero-greeting {
            margin: 0 0 8px;
            color: var(--primary);
            font-size: 1.2rem;
            font-weight: 700;
        }

        .hero-name {
            margin: 0 0 13px;
            font-size: clamp(3.4rem, 7.2vw, 6.5rem);
            font-weight: 800;
            letter-spacing: -0.075em;
            line-height: 0.94;
        }

        .typed-line {
            min-height: 42px;
            margin-bottom: 22px;
            color: var(--primary);
            font-size: clamp(1.15rem, 2.1vw, 1.55rem);
            font-weight: 700;
        }

        .typed-cursor {
            display: inline-block;
            width: 3px;
            height: 1.15em;
            margin-left: 4px;
            background: var(--secondary);
            vertical-align: -0.14em;
            animation: blink 0.9s steps(2, start) infinite;
        }

        @keyframes blink {
            0%,
            45% {
                opacity: 1;
            }

            46%,
            100% {
                opacity: 0;
            }
        }

        .hero-copy {
            max-width: 560px;
            margin-bottom: 30px;
            color: var(--muted);
            font-size: 1.02rem;
            line-height: 1.8;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 28px;
        }

        .btn-brand,
        .btn-ghost,
        .btn-send {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 23px;
            border-radius: 999px;
            font-size: 0.92rem;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;
        }

        .btn-brand {
            background: var(--primary);
            border: 1px solid var(--primary);
            color: #fff;
        }

        .btn-brand:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-ghost {
            background: #fff;
            border: 1px solid var(--border);
            color: var(--dark);
        }

        .btn-ghost:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .hero-facts {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 18px;
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 600;
        }

        .hero-facts span {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .hero-facts i {
            color: var(--primary);
            font-size: 0.95rem;
        }

        .hero-art {
            position: relative;
            display: grid;
            width: 100%;
            max-width: 480px;
            margin-left: auto;
            aspect-ratio: 1 / 1;
            place-items: center;
        }

        .hero-orbit {
            position: absolute;
            inset: 8%;
            border: 1px dashed rgba(27, 28, 24, 0.18);
            border-radius: 50%;
            animation: orbit 30s linear infinite;
        }

        .hero-orbit::after {
            position: absolute;
            top: 16%;
            right: 5%;
            width: 14px;
            height: 14px;
            background: var(--secondary);
            border: 4px solid var(--surface);
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(27, 28, 24, 0.12);
            content: "";
        }

        @keyframes orbit {
            to {
                transform: rotate(360deg);
            }
        }

        .hero-blob {
            position: absolute;
            inset: 13% 5% 4% 12%;
            background: var(--primary);
            border-radius: 45% 55% 60% 40% / 50% 45% 55% 50%;
            opacity: 0.06;
            transform: rotate(-10deg);
        }

        .hero-card {
            position: relative;
            z-index: 2;
            width: 88%;
            overflow: hidden;
            padding: 24px;
            background: linear-gradient(145deg, #1e1f1a, #131410);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            box-shadow: 0 30px 70px rgba(27, 28, 24, 0.24);
            color: #fff;
        }

        .hero-card::before {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px);
            background-size: 28px 28px;
            content: "";
            pointer-events: none;
        }

        .hero-card::after {
            position: absolute;
            top: -90px;
            right: -80px;
            width: 240px;
            height: 240px;
            background: radial-gradient(circle, rgba(201, 241, 111, 0.18), transparent 68%);
            content: "";
            pointer-events: none;
        }

        .hero-card-top {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 24px;
            color: rgba(255, 255, 255, 0.48);
            font-family: monospace;
            font-size: 0.67rem;
        }

        .window-dots {
            display: flex;
            gap: 5px;
        }

        .window-dots i {
            width: 7px;
            height: 7px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 50%;
        }

        .window-dots i:first-child {
            background: var(--secondary);
        }

        .security-mark {
            position: relative;
            z-index: 2;
            display: grid;
            width: 104px;
            height: 104px;
            margin: 2px auto 20px;
            place-items: center;
            background: var(--secondary);
            border: 1px solid rgba(27, 28, 24, 0.06);
            border-radius: 30px;
            box-shadow: 0 0 0 12px rgba(201, 241, 111, 0.1);
            color: var(--dark);
            font-size: 3rem;
        }

        .security-label {
            position: relative;
            z-index: 2;
            margin: 0 0 20px;
            text-align: center;
        }

        .security-label strong {
            display: block;
            margin-bottom: 4px;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .security-label span {
            color: rgba(255, 255, 255, 0.48);
            font-size: 0.76rem;
        }

        .terminal-stack {
            position: relative;
            z-index: 2;
            display: grid;
            gap: 8px;
        }

        .terminal-stack div {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 11px;
            background: rgba(255, 255, 255, 0.055);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 9px;
            font-family: monospace;
            font-size: 0.7rem;
        }

        .terminal-stack span {
            color: rgba(255, 255, 255, 0.42);
        }

        .terminal-stack strong {
            color: var(--secondary);
            font-weight: 600;
        }

        .float-pill {
            position: absolute;
            z-index: 3;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 13px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 999px;
            box-shadow: 0 12px 30px rgba(27, 28, 24, 0.1);
            font-size: 0.76rem;
            font-weight: 700;
        }

        .float-pill i {
            color: var(--primary);
            font-size: 0.95rem;
        }

        .float-pill.one {
            top: 3%;
            right: -2%;
        }

        .float-pill.two {
            bottom: 4%;
            left: -2%;
        }

        .section {
            padding: 96px 0;
        }

        .section-light {
            background: var(--light);
        }

        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 15px;
            color: var(--primary);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .section-tag::before {
            width: 24px;
            height: 3px;
            background: var(--secondary);
            border-radius: 3px;
            content: "";
        }

        .section-title {
            margin: 0 0 20px;
            font-size: clamp(2.35rem, 4.5vw, 3.7rem);
            font-weight: 800;
            letter-spacing: -0.06em;
            line-height: 1.08;
        }

        .title-mark {
            position: relative;
            z-index: 1;
            white-space: nowrap;
        }

        .title-mark::after {
            position: absolute;
            right: -3px;
            bottom: 6px;
            left: -3px;
            z-index: -1;
            height: 11px;
            background: var(--secondary);
            border-radius: 2px;
            content: "";
            transform: rotate(-1deg);
        }

        .body-copy {
            color: var(--muted);
            font-size: 0.98rem;
            line-height: 1.82;
        }

        .check-list {
            display: grid;
            gap: 13px;
            margin: 25px 0 28px;
        }

        .check-list div {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #3f4150;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .check-list i {
            color: var(--primary);
            font-size: 1rem;
        }

        .student-card {
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: 0 20px 55px rgba(27, 28, 24, 0.06);
        }

        .student-top {
            position: relative;
            overflow: hidden;
            padding: 32px;
            background: linear-gradient(145deg, #1e1f1a, #2d2f27);
            color: #fff;
        }

        .student-top::after {
            position: absolute;
            top: -95px;
            right: -85px;
            width: 240px;
            height: 240px;
            border: 38px solid rgba(255, 231, 32, 0.14);
            border-radius: 50%;
            content: "";
        }

        .student-top::before {
            position: absolute;
            right: 30px;
            bottom: -34px;
            color: rgba(255, 255, 255, 0.06);
            content: "A";
            font-size: 170px;
            font-weight: 800;
            line-height: 1;
        }

        .student-badge {
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 11px;
            margin-bottom: 24px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .student-badge::before {
            width: 7px;
            height: 7px;
            background: var(--secondary);
            border-radius: 50%;
            content: "";
        }

        .grade-row {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: flex-end;
            gap: 18px;
        }

        .grade-number {
            color: var(--secondary);
            font-size: 6rem;
            font-weight: 800;
            letter-spacing: -0.09em;
            line-height: 0.8;
        }

        .grade-row h3 {
            margin: 0 0 4px;
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .grade-row p {
            margin: 0;
            color: rgba(255, 255, 255, 0.62);
            font-size: 0.84rem;
        }

        .student-status {
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 26px;
            padding-top: 19px;
            border-top: 1px solid rgba(255, 255, 255, 0.16);
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.78rem;
        }

        .student-status i {
            color: var(--secondary);
        }

        .student-body {
            padding: 10px 28px 18px;
        }

        .student-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 24px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }

        .student-row:last-child {
            border-bottom: 0;
        }

        .student-row span {
            color: var(--muted);
            font-size: 0.8rem;
        }

        .student-row strong {
            text-align: right;
            font-size: 0.86rem;
            font-weight: 700;
        }

        .skill-item {
            margin-bottom: 24px;
        }

        .skill-item:last-child {
            margin-bottom: 0;
        }

        .skill-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 8px;
        }

        .skill-head h6 {
            margin: 0;
            font-size: 0.86rem;
            font-weight: 700;
        }

        .skill-head span {
            color: var(--primary);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .progress {
            height: 7px;
            overflow: hidden;
            background: #e6e2d6;
            border-radius: 999px;
        }

        .progress-bar {
            border-radius: 999px;
        }

        .bar-primary {
            background: var(--primary);
        }

        .bar-yellow {
            background: var(--secondary);
        }

        .bar-dark {
            background: var(--dark);
        }

        .bar-mid {
            background: #a8c96a;
        }

        .learning-card {
            padding: 28px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: 0 18px 45px rgba(27, 28, 24, 0.05);
        }

        .focus-tabs {
            display: flex;
            gap: 5px;
            padding: 5px;
            margin-bottom: 24px;
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 15px;
        }

        .focus-tabs .nav-link {
            flex: 1;
            padding: 11px 10px;
            border-radius: 11px;
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .focus-tabs .nav-link:hover {
            color: var(--primary);
        }

        .focus-tabs .nav-link.active {
            background: var(--primary);
            color: #fff;
        }

        .focus-row {
            display: flex;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }

        .focus-row:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .focus-icon {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            place-items: center;
            background: var(--primary-soft);
            border-radius: 12px;
            color: var(--primary);
            font-size: 1.05rem;
        }

        .focus-row h6 {
            margin: 1px 0 3px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .focus-row p {
            margin: 0;
            color: var(--muted);
            font-size: 0.79rem;
            line-height: 1.65;
        }

        .focus-row small {
            display: block;
            margin-top: 5px;
            color: var(--primary);
            font-size: 0.7rem;
            font-weight: 700;
        }

        .project-card {
            height: 100%;
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 26px;
            box-shadow: 0 14px 38px rgba(27, 28, 24, 0.045);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .project-card:hover {
            box-shadow: 0 22px 50px rgba(27, 28, 24, 0.09);
            transform: translateY(-5px);
        }

        .project-media {
            position: relative;
            height: 275px;
            overflow: hidden;
        }

        .project-media::before {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255, 255, 255, 0.055) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.055) 1px, transparent 1px);
            background-size: 32px 32px;
            content: "";
        }

        .project-media.edr {
            background: radial-gradient(circle at 28% 22%, #3c3f32, transparent 52%), var(--dark);
        }

        .project-media.tool {
            background: radial-gradient(circle at 82% 20%, rgba(201, 241, 111, 0.78), transparent 55%), #eae6da;
        }

        .project-label,
        .project-status {
            position: absolute;
            z-index: 2;
            top: 22px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .project-label {
            left: 22px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: rgba(255, 255, 255, 0.74);
        }

        .project-status {
            right: 22px;
            background: var(--secondary);
            color: var(--dark);
        }

        .project-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            z-index: 2;
            display: grid;
            width: 108px;
            height: 108px;
            place-items: center;
            background: var(--secondary);
            border-radius: 30px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.22);
            color: var(--dark);
            font-size: 3rem;
            transform: translate(-50%, -50%);
            transition: transform 0.3s ease;
        }

        .project-media.tool .project-icon {
            background: var(--dark);
            color: var(--secondary);
        }

        .project-media.tool .project-label {
            background: rgba(27, 28, 24, 0.07);
            border-color: rgba(27, 28, 24, 0.12);
            color: rgba(27, 28, 24, 0.66);
        }

        .project-media.tool .project-status {
            background: var(--dark);
            color: var(--secondary);
        }

        .project-media.tool .project-code {
            color: rgba(27, 28, 24, 0.36);
        }

        .project-media.tool .project-code span {
            background: rgba(27, 28, 24, 0.16);
        }

        .project-card:hover .project-icon {
            transform: translate(-50%, -50%) rotate(-4deg) scale(1.06);
        }

        .project-code {
            position: absolute;
            right: 22px;
            bottom: 18px;
            left: 22px;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 7px;
            color: rgba(255, 255, 255, 0.38);
            font-family: monospace;
            font-size: 0.68rem;
        }

        .project-code span {
            height: 4px;
            background: rgba(255, 255, 255, 0.16);
            border-radius: 999px;
        }

        .project-code span:nth-child(1) {
            width: 42px;
        }

        .project-code span:nth-child(2) {
            width: 26px;
        }

        .project-code span:nth-child(3) {
            width: 58px;
        }

        .project-body {
            padding: 25px;
        }

        .project-body h3 {
            margin: 0 0 9px;
            font-size: 1.42rem;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .project-body p {
            margin: 0 0 19px;
            color: var(--muted);
            font-size: 0.88rem;
            line-height: 1.75;
        }

        .tag-row {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .tag {
            padding: 5px 10px;
            background: var(--primary-soft);
            border-radius: 999px;
            color: var(--primary);
            font-size: 0.69rem;
            font-weight: 700;
        }

        .project-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-top: 18px;
            margin-top: 20px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 0.74rem;
            font-weight: 600;
        }

        .project-footer i {
            color: var(--primary);
        }

        .contact-info {
            margin-top: 30px;
        }

        .contact-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }

        .contact-row:last-child {
            border-bottom: 0;
        }

        .contact-icon {
            display: grid;
            width: 44px;
            height: 44px;
            flex: 0 0 auto;
            place-items: center;
            background: var(--primary-soft);
            border-radius: 13px;
            color: var(--primary);
            font-size: 1.05rem;
        }

        .contact-row span {
            display: block;
            margin-bottom: 2px;
            color: var(--muted);
            font-size: 0.72rem;
        }

        .contact-row strong {
            font-size: 0.88rem;
            font-weight: 700;
        }

        .form-card {
            padding: 30px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 26px;
            box-shadow: 0 20px 55px rgba(27, 28, 24, 0.06);
        }

        .form-card h3 {
            margin: 0 0 5px;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .form-card > p {
            margin: 0 0 22px;
            color: var(--muted);
            font-size: 0.82rem;
        }

        .form-floating > .form-control {
            min-height: 58px;
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 13px;
            color: var(--dark);
            font-size: 0.88rem;
        }

        .form-floating > textarea.form-control {
            min-height: 122px;
        }

        .form-floating > label {
            color: var(--muted);
            font-size: 0.82rem;
        }

        .form-control:focus {
            background: var(--surface);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(27, 28, 24, 0.08);
        }

        .btn-send {
            background: var(--primary);
            border: 1px solid var(--primary);
            color: #fff;
        }

        .btn-send:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: #fff;
            transform: translateY(-2px);
        }

        .form-alert {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 11px;
            font-size: 0.79rem;
            line-height: 1.5;
        }

        .form-alert.error {
            background: #fff0f0;
            border: 1px solid #f1c8c8;
            color: #a83232;
        }

        .form-alert.success {
            background: #f2f8e3;
            border: 1px solid #d4e7ad;
            color: #49630f;
        }

        .site-footer {
            padding: 27px 0;
            background: var(--dark);
            color: rgba(255, 255, 255, 0.6);
        }

        .footer-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            font-size: 0.78rem;
        }

        .footer-row a {
            color: #fff;
            text-decoration: none;
        }

        .footer-row a:hover {
            color: var(--secondary);
        }

        .footer-credit a {
            color: var(--secondary) !important;
            text-decoration: underline !important;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 17px;
        }

        .back-top {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 60;
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            background: var(--primary);
            border-radius: 50%;
            box-shadow: 0 10px 24px rgba(27, 28, 24, 0.24);
            color: #fff;
            font-size: 1rem;
            opacity: 0;
            pointer-events: none;
            text-decoration: none;
            transform: translateY(10px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .back-top.show {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        .back-top:hover {
            background: var(--primary-dark);
            color: #fff;
        }

        .inbox-hero {
            position: relative;
            overflow: hidden;
            padding: 72px 0 38px;
            background: var(--light);
        }

        .inbox-hero::before {
            position: absolute;
            top: -160px;
            right: -100px;
            width: 430px;
            height: 430px;
            background: radial-gradient(circle, rgba(201, 241, 111, 0.22), transparent 68%);
            border-radius: 50%;
            content: "";
        }

        .inbox-hero .container {
            position: relative;
            z-index: 2;
        }

        .inbox-title {
            margin: 0;
            font-size: clamp(2.9rem, 6vw, 4.7rem);
            font-weight: 800;
            letter-spacing: -0.07em;
            line-height: 1;
        }

        .inbox-subtitle {
            max-width: 480px;
            margin: 16px 0 0;
            color: var(--muted);
            font-size: 0.94rem;
            line-height: 1.75;
        }

        .inbox-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 18px;
        }

        .summary-card {
            padding: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 10px 28px rgba(27, 28, 24, 0.04);
        }

        .summary-card span {
            display: block;
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 0.67rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .summary-card strong {
            font-size: 1.32rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.2;
        }

        .summary-card strong.count {
            color: var(--primary);
            font-size: 2rem;
        }

        .message-list {
            display: grid;
            gap: 13px;
            padding-bottom: 75px;
        }

        .message-card {
            padding: 23px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 19px;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        .message-card:hover {
            border-color: #cdc8b8;
            transform: translateY(-2px);
        }

        .message-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 16px;
        }

        .message-person {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .message-avatar {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            place-items: center;
            background: var(--primary);
            border-radius: 50%;
            color: var(--secondary);
            font-size: 0.75rem;
            font-weight: 800;
        }

        .message-person h2 {
            margin: 0 0 2px;
            font-size: 0.92rem;
            font-weight: 700;
        }

        .message-person a {
            display: block;
            overflow: hidden;
            color: var(--muted);
            font-size: 0.77rem;
            text-overflow: ellipsis;
            text-decoration: none;
            white-space: nowrap;
        }

        .message-person a:hover {
            color: var(--primary);
        }

        .message-head time {
            flex: 0 0 auto;
            color: var(--muted);
            font-size: 0.7rem;
            white-space: nowrap;
        }

        .message-body {
            margin: 0;
            color: #4a4c5a;
            font-size: 0.9rem;
            line-height: 1.75;
        }

        .empty-state {
            padding: 68px 25px;
            background: var(--surface);
            border: 1px dashed #c9c4b7;
            border-radius: 24px;
            text-align: center;
        }

        .empty-icon {
            display: grid;
            width: 60px;
            height: 60px;
            margin: 0 auto 17px;
            place-items: center;
            background: var(--secondary);
            border-radius: 18px;
            color: var(--primary);
            font-size: 1.45rem;
        }

        .empty-state h2 {
            margin: 0 0 7px;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .empty-state p {
            max-width: 370px;
            margin: 0 auto 20px;
            color: var(--muted);
            font-size: 0.86rem;
            line-height: 1.7;
        }

        @media (max-width: 991.98px) {
            body {
                padding-top: 72px;
            }

            section {
                scroll-margin-top: 72px;
            }

            .site-navbar,
            .site-navbar .container {
                min-height: 72px;
            }

            .navbar-collapse {
                min-height: 0;
                padding: 10px 0 18px;
            }

            .nav-side {
                align-items: flex-start;
                flex: none;
                width: 100%;
            }

            .nav-end {
                justify-content: flex-start;
            }

            .site-navbar .nav-link {
                padding: 8px 0 !important;
            }

            .site-navbar .inbox-link {
                margin-top: 7px;
                margin-left: 0;
                padding: 9px 15px !important;
            }

            .hero {
                padding: 58px 0 50px;
            }

            .hero-art {
                margin: 10px auto 0;
            }

            .section {
                padding: 72px 0;
            }

            .contact-info {
                margin-top: 22px;
            }
        }

        @media (max-width: 575.98px) {
            .hero-name {
                font-size: clamp(3.05rem, 15vw, 4.4rem);
            }

            .hero-copy {
                font-size: 0.95rem;
            }

            .hero-art {
                max-width: 350px;
            }

            .hero-card {
                width: 90%;
                padding: 20px;
            }

            .float-pill {
                padding: 8px 11px;
                font-size: 0.68rem;
            }

            .float-pill.one {
                right: 0;
            }

            .float-pill.two {
                left: 0;
            }

            .section-title {
                font-size: clamp(2.1rem, 10vw, 2.75rem);
            }

            .grade-number {
                font-size: 4.7rem;
            }

            .student-top {
                padding: 26px;
            }

            .student-body {
                padding: 8px 22px 14px;
            }

            .learning-card,
            .form-card {
                padding: 22px;
            }

            .project-media {
                height: 235px;
            }

            .inbox-title {
                font-size: clamp(2.7rem, 13vw, 3.6rem);
            }

            .inbox-summary {
                grid-template-columns: 1fr;
            }

            .message-head {
                flex-direction: column;
                gap: 8px;
            }

            .footer-row {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>
<body <?= $page === 'messages' ? '' : 'data-bs-spy="scroll" data-bs-target="#mainNav" data-bs-root-margin="0px 0px -70%" data-bs-smooth-scroll="true" tabindex="0"' ?>>
    <nav class="navbar navbar-expand-lg fixed-top site-navbar" id="mainNav">
        <div class="container">
            <a class="mobile-brand d-lg-none" href="index.php">A<span>.</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list fs-4"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav nav-side">
                    <li class="nav-item"><a class="nav-link <?= $page === 'home' ? 'active' : '' ?>" href="index.php#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#skills">Skills</a></li>
                </ul>
                <a class="brand-center d-none d-lg-grid" href="index.php#home" aria-label="Abdulmejid home">A</a>
                <ul class="navbar-nav nav-side nav-end">
                    <li class="nav-item"><a class="nav-link" href="index.php#projects">Projects</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#contact">Contact</a></li>
                    <li class="nav-item"><a class="nav-link inbox-link <?= $page === 'messages' ? 'active' : '' ?>" href="index.php?page=messages"><i class="bi bi-inbox me-1"></i>Inbox</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <?php if ($page === 'messages'): ?>
        <main>
            <section class="inbox-hero">
                <div class="container">
                    <div class="row align-items-end g-4">
                        <div class="col-lg-7">
                            <div class="section-tag">Private inbox</div>
                            <h1 class="inbox-title">Every hello,<br>one place.</h1>
                            <p class="inbox-subtitle">Messages sent from the portfolio contact form are saved here. This page reads directly from the same local database.</p>
                        </div>
                        <div class="col-lg-5">
                            <div class="inbox-summary">
                                <div class="summary-card">
                                    <span>Messages</span>
                                    <strong class="count"><?= $messageCount ?></strong>
                                </div>
                                <div class="summary-card">
                                    <span>Latest hello</span>
                                    <strong><?= e($latestMessage) ?></strong>
                                </div>
                            </div>
                            <a class="btn-ghost" href="index.php"><i class="bi bi-arrow-left"></i>Back to portfolio</a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section pt-4">
                <div class="container">
                    <?php if ($messageCount === 0): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-envelope-open"></i></div>
                            <h2>No messages yet</h2>
                            <p>When someone sends a hi from the portfolio, their message will appear right here.</p>
                            <a class="btn-brand" href="index.php#contact">Send the first hi <i class="bi bi-arrow-up-right"></i></a>
                        </div>
                    <?php else: ?>
                        <div class="message-list">
                            <?php foreach ($messages as $item): ?>
                                <article class="message-card">
                                    <div class="message-head">
                                        <div class="message-person">
                                            <span class="message-avatar"><?= e(initials($item['name'])) ?></span>
                                            <div>
                                                <h2><?= e($item['name']) ?></h2>
                                                <span>Sent a hello</span>
                                            </div>
                                        </div>
                                        <time datetime="<?= e($item['created_at']) ?>"><?= e(prettyDate($item['created_at'])) ?></time>
                                    </div>
                                    <p class="message-body"><?= nl2br(e($item['message'])) ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    <?php else: ?>
        <main>
            <section class="hero" id="home">
                <div class="container">
                    <div class="row align-items-center g-5">
                        <div class="col-lg-7">
                            <p class="hero-greeting">I'm</p>
                            <h1 class="hero-name">Abdulmejid.</h1>
                            <div class="typed-line"><span id="typedText">Cybersecurity Student</span><span class="typed-cursor"></span></div>
                            <p class="hero-copy">A grade 12 student at Kings School with a growing focus on cybersecurity. I build small Python security projects and learn how clean front-end code, PHP, and databases work together.</p>
                            <div class="hero-actions">
                                <a class="btn-brand" href="#projects">View my projects <i class="bi bi-arrow-down-right"></i></a>
                                <a class="btn-ghost" href="#contact">Send a hi</a>
                            </div>
                            <div class="hero-facts">
                                <span><i class="bi bi-shield-check"></i>Cybersecurity focus</span>
                                <span><i class="bi bi-mortarboard"></i>Kings School</span>
                                <span><i class="bi bi-braces"></i>Python learner</span>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="hero-art">
                                <div class="hero-orbit"></div>
                                <div class="hero-blob"></div>
                                <div class="hero-card">
                                    <div class="hero-card-top">
                                        <span class="window-dots"><i></i><i></i><i></i></span>
                                        <span>abdulmejid@kings:~</span>
                                    </div>
                                    <div class="security-mark"><i class="bi bi-shield-lock-fill"></i></div>
                                    <p class="security-label"><strong>Security minded</strong><span>Learning today, protecting tomorrow.</span></p>
                                    <div class="terminal-stack">
                                        <div><span>grade</span><strong>12</strong></div>
                                        <div><span>school</span><strong>Kings School</strong></div>
                                        <div><span>focus</span><strong>cybersecurity</strong></div>
                                    </div>
                                </div>
                                <div class="float-pill one"><i class="bi bi-braces"></i>Python</div>
                                <div class="float-pill two"><i class="bi bi-code-slash"></i>Frontend</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section" id="about">
                <div class="container">
                    <div class="row align-items-center g-5">
                        <div class="col-lg-6">
                            <div class="section-tag">About me</div>
                            <h2 class="section-title">A young learner,<br><span class="title-mark">security minded.</span></h2>
                            <p class="body-copy">I am Abdulmejid, a grade 12 student at Kings School. My main interest is cybersecurity, and I like understanding how systems work, how they can fail, and how to protect them. I am also strengthening my front-end skills and learning PHP with MySQL.</p>
                            <div class="check-list">
                                <div><i class="bi bi-patch-check-fill"></i>Grade 12 student at Kings School</div>
                                <div><i class="bi bi-patch-check-fill"></i>Cybersecurity is my main focus</div>
                                <div><i class="bi bi-patch-check-fill"></i>Building Python security projects</div>
                            </div>
                            <a class="btn-brand" href="#contact">Let's connect <i class="bi bi-arrow-up-right"></i></a>
                        </div>
                        <div class="col-lg-6">
                            <div class="student-card">
                                <div class="student-top">
                                    <span class="student-badge">Student profile</span>
                                    <div class="grade-row">
                                        <div class="grade-number">12</div>
                                        <div>
                                            <h3>Kings School</h3>
                                            <p>Cybersecurity focus</p>
                                        </div>
                                    </div>
                                    <div class="student-status"><i class="bi bi-circle-fill"></i>Grade 12 · Class of 2026</div>
                                </div>
                                <div class="student-body">
                                    <div class="student-row"><span>Name</span><strong>Abdulmejid</strong></div>
                                    <div class="student-row"><span>School</span><strong>Kings School</strong></div>
                                    <div class="student-row"><span>Focus</span><strong>Cybersecurity</strong></div>
                                    <div class="student-row"><span>Learning</span><strong>Frontend, PHP &amp; MySQL</strong></div>
                                    <div class="student-row"><span>Projects</span><strong>2 Python builds</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section section-light" id="skills">
                <div class="container">
                    <div class="row g-5 align-items-start">
                        <div class="col-lg-6">
                            <div class="section-tag">What I'm learning</div>
                            <h2 class="section-title">Building a strong<br><span class="title-mark">technical base.</span></h2>
                            <p class="body-copy mb-5">I am improving my front-end skills and learning how to connect PHP to MySQL databases. Python is where I practice cybersecurity ideas by building small, useful tools.</p>
                            <div class="skill-item">
                                <div class="skill-head"><h6>Advanced frontend</h6><span>85%</span></div>
                                <div class="progress" role="progressbar" aria-label="Advanced frontend" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bar-primary" style="width: 85%"></div></div>
                            </div>
                            <div class="skill-item">
                                <div class="skill-head"><h6>PHP &amp; MySQL</h6><span>75%</span></div>
                                <div class="progress" role="progressbar" aria-label="PHP and MySQL" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bar-yellow" style="width: 75%"></div></div>
                            </div>
                            <div class="skill-item">
                                <div class="skill-head"><h6>Python for security</h6><span>80%</span></div>
                                <div class="progress" role="progressbar" aria-label="Python for security" aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bar-dark" style="width: 80%"></div></div>
                            </div>
                            <div class="skill-item">
                                <div class="skill-head"><h6>Cybersecurity fundamentals</h6><span>70%</span></div>
                                <div class="progress" role="progressbar" aria-label="Cybersecurity fundamentals" aria-valuenow="70" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bar-mid" style="width: 70%"></div></div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="learning-card">
                                <ul class="nav nav-pills focus-tabs" role="tablist">
                                    <li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-bs-toggle="pill" data-bs-target="#education" role="tab" aria-selected="true">Education</button></li>
                                    <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="pill" data-bs-target="#focus" role="tab" aria-selected="false">Current focus</button></li>
                                </ul>
                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="education" role="tabpanel">
                                        <div class="focus-row">
                                            <div class="focus-icon"><i class="bi bi-mortarboard-fill"></i></div>
                                            <div><h6>Grade 12 Student</h6><p>Studying at Kings School with a clear interest in technology and cybersecurity.</p><small>Kings School · 2025 to 2026</small></div>
                                        </div>
                                        <div class="focus-row">
                                            <div class="focus-icon"><i class="bi bi-shield-check"></i></div>
                                            <div><h6>Cybersecurity Focus</h6><p>Learning core security ideas through self-study and practical Python projects.</p><small>Independent learning</small></div>
                                        </div>
                                        <div class="focus-row">
                                            <div class="focus-icon"><i class="bi bi-award-fill"></i></div>
                                            <div><h6>Class of 2026</h6><p>Preparing for the next step while continuing to build real project experience.</p><small>Graduation year</small></div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="focus" role="tabpanel">
                                        <div class="focus-row">
                                            <div class="focus-icon"><i class="bi bi-window-stack"></i></div>
                                            <div><h6>Advanced frontend</h6><p>Responsive layouts, Bootstrap, accessible forms, and cleaner interface design.</p><small>HTML · CSS · Bootstrap</small></div>
                                        </div>
                                        <div class="focus-row">
                                            <div class="focus-icon"><i class="bi bi-database-fill"></i></div>
                                            <div><h6>PHP &amp; MySQL</h6><p>Connecting forms to databases, storing data safely, and displaying useful results.</p><small>Server-side development</small></div>
                                        </div>
                                        <div class="focus-row">
                                            <div class="focus-icon"><i class="bi bi-terminal-fill"></i></div>
                                            <div><h6>Python security tools</h6><p>Practising detection, monitoring, and reporting with small Python applications.</p><small>Python · Security</small></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section" id="projects">
                <div class="container">
                    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
                        <div>
                            <div class="section-tag">Selected projects</div>
                            <h2 class="section-title mb-0">Two Python projects.<br><span class="title-mark">One clear goal.</span></h2>
                        </div>
                        <a class="btn-ghost" href="#contact">Ask me about them <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div class="row g-4 mt-2">
                        <div class="col-lg-6">
                            <article class="project-card">
                                <div class="project-media edr">
                                    <span class="project-label">Python · Security</span>
                                    <span class="project-status">In progress</span>
                                    <div class="project-icon"><i class="bi bi-shield-exclamation"></i></div>
                                    <div class="project-code"><span></span><span></span><span></span></div>
                                </div>
                                <div class="project-body">
                                    <h3>EDR System</h3>
                                    <p>A Python-based endpoint detection and response project that watches system activity, highlights suspicious behaviour, and gives the user a simple alert view.</p>
                                    <div class="tag-row">
                                        <span class="tag">Python</span>
                                        <span class="tag">Threat detection</span>
                                        <span class="tag">Monitoring</span>
                                    </div>
                                    <div class="project-footer"><span><i class="bi bi-cpu me-1"></i>Endpoint security</span><span>Grade 12 project</span></div>
                                </div>
                            </article>
                        </div>
                        <div class="col-lg-6">
                            <article class="project-card">
                                <div class="project-media tool">
                                    <span class="project-label">Python · Tool</span>
                                    <span class="project-status">In progress</span>
                                    <div class="project-icon"><i class="bi bi-terminal"></i></div>
                                    <div class="project-code"><span></span><span></span><span></span></div>
                                </div>
                                <div class="project-body">
                                    <h3>Cybersecurity Software</h3>
                                    <p>A Python security tool designed to check common risks, scan files, and produce clear reports that are easy to understand and act on.</p>
                                    <div class="tag-row">
                                        <span class="tag">Python</span>
                                        <span class="tag">File scanning</span>
                                        <span class="tag">Reporting</span>
                                    </div>
                                    <div class="project-footer"><span><i class="bi bi-bug me-1"></i>Security utility</span><span>Grade 12 project</span></div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section section-light" id="contact">
                <div class="container">
                    <div class="row g-5">
                        <div class="col-lg-5">
                            <div class="section-tag">Contact</div>
                            <h2 class="section-title">Let's work<br><span class="title-mark">together.</span></h2>
                            <p class="body-copy">Have a question, a project idea, or just want to say hello? Send a short message and it will be saved in the inbox.</p>
                            <div class="contact-info">
                                <div class="contact-row">
                                    <div class="contact-icon"><i class="bi bi-mortarboard-fill"></i></div>
                                    <div><span>School</span><strong>Kings School</strong></div>
                                </div>
                                <div class="contact-row">
                                    <div class="contact-icon"><i class="bi bi-shield-lock-fill"></i></div>
                                    <div><span>Focus</span><strong>Cybersecurity</strong></div>
                                </div>
                                <div class="contact-row">
                                    <div class="contact-icon"><i class="bi bi-inbox-fill"></i></div>
                                    <div><span>Message inbox</span><strong><a href="index.php?page=messages">View sent messages</a></strong></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="form-card">
                                <h3>Send a hi</h3>
                                <p>Keep it short. Your message goes straight to the local inbox.</p>
                                <?php if ($error !== ''): ?>
                                    <div class="form-alert error"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
                                <?php endif; ?>
                                <?php if ($flash !== ''): ?>
                                    <div class="form-alert success"><i class="bi bi-check-circle me-1"></i><?= e($flash) ?></div>
                                <?php endif; ?>
                                <form method="post" action="index.php#contact">
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input class="form-control" type="text" id="name" name="name" value="<?= e($name) ?>" placeholder="Your name" maxlength="80" required>
                                                <label for="name">Your name</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <textarea class="form-control" id="message" name="message" placeholder="Write your message" maxlength="1000" required><?= e($message) ?></textarea>
                                                <label for="message">Your message</label>
                                            </div>
                                        </div>
                                        <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3">
                                            <button class="btn-send" type="submit">Send message <i class="bi bi-arrow-up-right"></i></button>
                                            <span class="small text-muted">Stored locally. No spam.</span>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    <?php endif; ?>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-row">
                <span>&copy; <?= date('Y') ?> Abdulmejid. Built as a grade 12 portfolio.</span>
                <div class="footer-links">
                    <a href="index.php#home">Home</a>
                    <a href="index.php#projects">Projects</a>
                    <a href="index.php#contact">Contact</a>
                    <a href="index.php?page=messages">Inbox</a>
                    <span class="footer-credit">Template inspiration: <a href="https://freewebsitecreate.net" target="_blank" rel="noreferrer">Free Website Create</a></span>
                </div>
            </div>
        </div>
    </footer>

    <a class="back-top" id="backTop" href="<?= $page === 'messages' ? 'index.php' : '#home' ?>" aria-label="Back to top"><i class="bi bi-arrow-up"></i></a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const typedText = document.getElementById("typedText");
        if (typedText) {
            const phrases = ["Cybersecurity Student", "Python Learner", "Frontend Developer", "Grade 12 Student"];
            let phraseIndex = 0;
            let charIndex = 0;
            let removing = false;
            const type = () => {
                const word = phrases[phraseIndex];
                charIndex += removing ? -1 : 1;
                typedText.textContent = word.slice(0, charIndex);
                let delay = removing ? 50 : 95;
                if (!removing && charIndex === word.length) {
                    removing = true;
                    delay = 1600;
                } else if (removing && charIndex === 0) {
                    removing = false;
                    phraseIndex = (phraseIndex + 1) % phrases.length;
                    delay = 320;
                }
                window.setTimeout(type, delay);
            };
            type();
        }

        const backTop = document.getElementById("backTop");
        const syncBackTop = () => {
            backTop.classList.toggle("show", window.scrollY > 420);
        };
        window.addEventListener("scroll", syncBackTop);
        syncBackTop();
    </script>
</body>
</html>
