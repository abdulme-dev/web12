<?php
session_start();

try {
    $db = new PDO('sqlite:' . __DIR__ . '/portfolio.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        message TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
} catch (PDOException $exception) {
    http_response_code(500);
    exit('The database could not be opened. Please make sure the portfolio folder is writable.');
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
$email = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($name === '' || $email === '' || $message === '') {
        $error = 'Please fill in all three fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($name) > 80 || strlen($email) > 120 || strlen($message) > 1000) {
        $error = 'Please keep your name and email short and your message under 1,000 characters.';
    } else {
        $statement = $db->prepare('INSERT INTO messages (name, email, message) VALUES (?, ?, ?)');
        $statement->execute([$name, $email, $message]);
        $_SESSION['flash'] = 'Your hi has been sent. Thank you for stopping by.';
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
    $messages = $db->query('SELECT * FROM messages ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
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
    <meta name="description" content="Portfolio of Abdulmejid, a grade 12 student learning web development.">
    <title><?= $page === 'messages' ? 'Inbox' : 'Abdulmejid' ?> | Student Portfolio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --paper: #f5f3ec;
            --surface: #fffdf8;
            --ink: #1b1c18;
            --muted: #73756a;
            --line: #e2ded1;
            --lime: #c9f16f;
            --lime-dark: #b3e24f;
            --soft: #ebe8dd;
            --radius: 24px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: "DM Sans", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: inherit;
        }

        ::selection {
            background: var(--lime);
            color: var(--ink);
        }

        .site-nav {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(245, 243, 236, 0.9);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(14px);
        }

        .navbar {
            padding: 18px 0;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            color: var(--ink);
            font-weight: 700;
            letter-spacing: -0.03em;
            text-decoration: none;
        }

        .brand-mark {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            background: var(--ink);
            color: var(--lime);
            border-radius: 11px;
            font-family: "Instrument Serif", serif;
            font-size: 23px;
            line-height: 1;
        }

        .navbar-nav {
            gap: 8px;
        }

        .nav-link {
            color: var(--muted);
            font-size: 0.92rem;
            font-weight: 500;
            padding: 8px 13px !important;
            border-radius: 999px;
            transition: color 0.2s ease, background 0.2s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--ink);
            background: var(--soft);
        }

        .nav-inbox {
            color: var(--ink);
            border: 1px solid var(--line);
            background: var(--surface);
        }

        .navbar-toggler {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 7px 10px;
            box-shadow: none !important;
        }

        .hero {
            padding: 84px 0 58px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 24px;
            color: var(--muted);
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            width: 8px;
            height: 8px;
            background: var(--lime-dark);
            border-radius: 50%;
            content: "";
        }

        .hero-title {
            max-width: 700px;
            margin: 0 0 24px;
            font-size: clamp(3rem, 6.2vw, 5.8rem);
            font-weight: 600;
            letter-spacing: -0.065em;
            line-height: 0.96;
        }

        .hero-title em {
            font-family: "Instrument Serif", serif;
            font-weight: 400;
            letter-spacing: -0.02em;
        }

        .hero-copy {
            max-width: 560px;
            margin-bottom: 30px;
            color: var(--muted);
            font-size: 1.08rem;
            line-height: 1.75;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-ink,
        .btn-quiet {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 13px 21px;
            border-radius: 999px;
            font-size: 0.93rem;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;
        }

        .btn-ink {
            background: var(--ink);
            color: #fff;
            border: 1px solid var(--ink);
        }

        .btn-ink:hover {
            background: #30322b;
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-quiet {
            background: transparent;
            color: var(--ink);
            border: 1px solid #cbc7bb;
        }

        .btn-quiet:hover {
            background: var(--surface);
            border-color: var(--ink);
            transform: translateY(-2px);
        }

        .hero-panel {
            position: relative;
            min-height: 430px;
            padding: 28px;
            overflow: hidden;
            background: var(--ink);
            color: #fff;
            border-radius: 30px;
            box-shadow: 0 24px 60px rgba(27, 28, 24, 0.12);
        }

        .hero-panel::before {
            position: absolute;
            top: -110px;
            right: -90px;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(201, 241, 111, 0.32), transparent 68%);
            content: "";
        }

        .hero-panel::after {
            position: absolute;
            right: -18px;
            bottom: -70px;
            color: rgba(255, 255, 255, 0.07);
            content: "A";
            font-family: "Instrument Serif", serif;
            font-size: 290px;
            line-height: 1;
        }

        .panel-top,
        .status-row,
        .mini-info {
            position: relative;
            z-index: 2;
        }

        .panel-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 52px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .status-row {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .avatar {
            display: grid;
            width: 74px;
            height: 74px;
            flex: 0 0 auto;
            place-items: center;
            background: var(--lime);
            color: var(--ink);
            border-radius: 50%;
            font-family: "Instrument Serif", serif;
            font-size: 38px;
        }

        .status-row h2 {
            margin: 0 0 5px;
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: -0.03em;
        }

        .status-row p {
            margin: 0;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        .panel-rule {
            position: relative;
            z-index: 2;
            height: 1px;
            margin: 32px 0 24px;
            background: rgba(255, 255, 255, 0.13);
        }

        .mini-info {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 34px;
        }

        .mini-info span {
            padding: 8px 12px;
            color: rgba(255, 255, 255, 0.78);
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 999px;
            font-size: 0.79rem;
        }

        .status-note {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 11px;
            margin: 0;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.86rem;
        }

        .status-dot {
            width: 9px;
            height: 9px;
            background: var(--lime);
            border-radius: 50%;
            box-shadow: 0 0 0 5px rgba(201, 241, 111, 0.12);
        }

        .stats {
            padding: 28px 0;
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
        }

        .stat {
            padding: 4px 28px;
            border-right: 1px solid var(--line);
        }

        .stat:first-child {
            padding-left: 0;
        }

        .stat:last-child {
            border-right: 0;
        }

        .stat strong {
            display: block;
            margin-bottom: 4px;
            font-family: "Instrument Serif", serif;
            font-size: 2rem;
            font-weight: 400;
            line-height: 1;
        }

        .stat span {
            color: var(--muted);
            font-size: 0.83rem;
        }

        .section {
            padding: 96px 0;
        }

        .section-kicker {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 22px;
            color: var(--muted);
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .section-kicker span {
            display: grid;
            width: 28px;
            height: 28px;
            place-items: center;
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 50%;
            font-size: 0.7rem;
        }

        .section-title {
            margin: 0 0 20px;
            font-size: clamp(2.2rem, 4vw, 3.6rem);
            font-weight: 600;
            letter-spacing: -0.055em;
            line-height: 1.02;
        }

        .section-title em {
            font-family: "Instrument Serif", serif;
            font-weight: 400;
            letter-spacing: -0.02em;
        }

        .body-copy {
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.8;
        }

        .about-card {
            padding: 28px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
        }

        .about-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .about-list li {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 20px;
            padding: 15px 0;
            border-bottom: 1px solid var(--line);
        }

        .about-list li:first-child {
            padding-top: 0;
        }

        .about-list li:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .about-list span {
            color: var(--muted);
            font-size: 0.84rem;
        }

        .about-list strong {
            text-align: right;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .skill-card {
            height: 100%;
            padding: 26px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            transition: transform 0.25s ease, border-color 0.25s ease;
        }

        .skill-card:hover {
            border-color: #c7c2b5;
            transform: translateY(-4px);
        }

        .skill-icon {
            display: grid;
            width: 44px;
            height: 44px;
            margin-bottom: 32px;
            place-items: center;
            background: var(--lime);
            border-radius: 13px;
            font-size: 1.15rem;
        }

        .skill-card h3 {
            margin: 0 0 10px;
            font-size: 1.05rem;
            font-weight: 600;
            letter-spacing: -0.025em;
        }

        .skill-card p {
            margin: 0;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.7;
        }

        .tag-row {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 18px;
        }

        .tag {
            padding: 5px 10px;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 999px;
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 500;
        }

        .project-card {
            display: flex;
            height: 100%;
            flex-direction: column;
            padding: 26px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 35px rgba(27, 28, 24, 0.07);
        }

        .project-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 42px;
        }

        .project-number {
            color: var(--muted);
            font-family: "DM Sans", sans-serif;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        .project-arrow {
            display: grid;
            width: 32px;
            height: 32px;
            place-items: center;
            background: var(--soft);
            border-radius: 50%;
            font-size: 0.85rem;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .project-card:hover .project-arrow {
            background: var(--lime);
            transform: rotate(-35deg);
        }

        .project-card h3 {
            margin: 0 0 10px;
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: -0.035em;
        }

        .project-card p {
            margin: 0;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.7;
        }

        .project-card .tag-row {
            margin-top: auto;
            padding-top: 22px;
        }

        .contact-wrap {
            padding: 46px;
            background: var(--ink);
            color: #fff;
            border-radius: 32px;
        }

        .contact-wrap .section-kicker {
            color: rgba(255, 255, 255, 0.55);
        }

        .contact-wrap .section-kicker span {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .contact-wrap .section-title {
            margin-bottom: 18px;
        }

        .contact-copy {
            max-width: 420px;
            margin: 0;
            color: rgba(255, 255, 255, 0.62);
            font-size: 0.96rem;
            line-height: 1.75;
        }

        .contact-links {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 34px;
        }

        .contact-links a {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            width: fit-content;
            color: rgba(255, 255, 255, 0.76);
            font-size: 0.86rem;
            text-decoration: none;
        }

        .contact-links a:hover {
            color: var(--lime);
        }

        .form-panel {
            padding: 26px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 22px;
        }

        .form-label {
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.04em;
        }

        .form-control {
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            color: #fff;
            font-size: 0.92rem;
            box-shadow: none;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.35);
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.09);
            border-color: var(--lime);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(201, 241, 111, 0.12);
        }

        textarea.form-control {
            min-height: 112px;
            resize: vertical;
        }

        .form-note {
            margin: 14px 0 0;
            color: rgba(255, 255, 255, 0.38);
            font-size: 0.74rem;
            line-height: 1.6;
        }

        .btn-send {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 20px;
            background: var(--lime);
            border: 1px solid var(--lime);
            border-radius: 999px;
            color: var(--ink);
            font-size: 0.9rem;
            font-weight: 700;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .btn-send:hover {
            background: var(--lime-dark);
            border-color: var(--lime-dark);
            transform: translateY(-2px);
        }

        .form-alert {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 11px;
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .form-alert.error {
            background: rgba(255, 133, 133, 0.12);
            border: 1px solid rgba(255, 133, 133, 0.24);
            color: #ffc1c1;
        }

        .form-alert.success {
            background: rgba(201, 241, 111, 0.12);
            border: 1px solid rgba(201, 241, 111, 0.24);
            color: var(--lime);
        }

        .site-footer {
            padding: 28px 0 36px;
        }

        .footer-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            color: var(--muted);
            font-size: 0.8rem;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
        }

        .footer-links a {
            color: var(--muted);
            text-decoration: none;
        }

        .footer-links a:hover {
            color: var(--ink);
        }

        .inbox-hero {
            padding: 64px 0 34px;
        }

        .inbox-title {
            margin: 0;
            font-size: clamp(2.8rem, 6vw, 5rem);
            font-weight: 600;
            letter-spacing: -0.065em;
            line-height: 1;
        }

        .inbox-title em {
            font-family: "Instrument Serif", serif;
            font-weight: 400;
            letter-spacing: -0.02em;
        }

        .inbox-subtitle {
            max-width: 480px;
            margin: 18px 0 0;
            color: var(--muted);
            font-size: 0.98rem;
            line-height: 1.75;
        }

        .inbox-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 28px;
        }

        .summary-card {
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 18px;
        }

        .summary-card span {
            display: block;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 0.73rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .summary-card strong {
            font-family: "Instrument Serif", serif;
            font-size: 1.7rem;
            font-weight: 400;
            line-height: 1.05;
        }

        .message-list {
            display: grid;
            gap: 14px;
            padding-bottom: 80px;
        }

        .message-card {
            padding: 24px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 20px;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        .message-card:hover {
            border-color: #c8c3b6;
            transform: translateY(-2px);
        }

        .message-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
        }

        .message-person {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
        }

        .message-avatar {
            display: grid;
            width: 44px;
            height: 44px;
            flex: 0 0 auto;
            place-items: center;
            background: var(--lime);
            border-radius: 50%;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .message-person h2 {
            margin: 0 0 3px;
            font-size: 0.98rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .message-person a {
            display: block;
            overflow: hidden;
            color: var(--muted);
            font-size: 0.8rem;
            text-overflow: ellipsis;
            text-decoration: none;
            white-space: nowrap;
        }

        .message-person a:hover {
            color: var(--ink);
        }

        .message-head time {
            flex: 0 0 auto;
            color: var(--muted);
            font-size: 0.74rem;
            white-space: nowrap;
        }

        .message-body {
            margin: 0;
            color: #4e5047;
            font-size: 0.94rem;
            line-height: 1.75;
        }

        .empty-state {
            padding: 72px 24px;
            background: var(--surface);
            border: 1px dashed #c9c4b7;
            border-radius: 24px;
            text-align: center;
        }

        .empty-icon {
            display: grid;
            width: 58px;
            height: 58px;
            margin: 0 auto 18px;
            place-items: center;
            background: var(--lime);
            border-radius: 18px;
            font-size: 1.4rem;
        }

        .empty-state h2 {
            margin: 0 0 8px;
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: -0.03em;
        }

        .empty-state p {
            margin: 0 auto 20px;
            max-width: 380px;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.7;
        }

        @media (max-width: 991.98px) {
            .hero {
                padding-top: 60px;
            }

            .hero-panel {
                min-height: 380px;
            }

            .section {
                padding: 72px 0;
            }

            .contact-wrap {
                padding: 34px;
            }
        }

        @media (max-width: 767.98px) {
            .navbar-collapse {
                padding-top: 14px;
            }

            .navbar-nav {
                align-items: flex-start !important;
                gap: 2px;
            }

            .nav-inbox {
                margin-top: 8px;
            }

            .hero {
                padding: 48px 0 44px;
            }

            .hero-title {
                font-size: clamp(2.8rem, 15vw, 4.2rem);
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .stat {
                padding: 16px 0;
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }

            .stat:first-child {
                padding-top: 0;
            }

            .stat:last-child {
                padding-bottom: 0;
                border-bottom: 0;
            }

            .contact-wrap {
                padding: 28px 20px;
                border-radius: 24px;
            }

            .form-panel {
                padding: 20px;
            }

            .footer-row {
                align-items: flex-start;
                flex-direction: column;
            }

            .inbox-title {
                font-size: clamp(2.6rem, 13vw, 4rem);
            }

            .inbox-summary {
                grid-template-columns: 1fr;
            }

            .message-head {
                flex-direction: column;
                gap: 9px;
            }
        }
    </style>
</head>
<body>
    <header class="site-nav">
        <div class="container">
            <nav class="navbar navbar-expand-md p-0">
                <a class="brand" href="index.php">
                    <span class="brand-mark">A</span>
                    <span>Abdulmejid</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="bi bi-list"></i>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php#about">About</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php#work">Work</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php#contact">Contact</a></li>
                        <li class="nav-item"><a class="nav-link nav-inbox <?= $page === 'messages' ? 'active' : '' ?>" href="index.php?page=messages"><i class="bi bi-inbox me-1"></i>Inbox</a></li>
                    </ul>
                </div>
            </nav>
        </div>
    </header>

    <?php if ($page === 'messages'): ?>
        <main>
            <section class="inbox-hero">
                <div class="container">
                    <div class="row align-items-end g-4">
                        <div class="col-lg-7">
                            <div class="eyebrow">Private inbox</div>
                            <h1 class="inbox-title">Every hello,<br><em>in one place.</em></h1>
                            <p class="inbox-subtitle">Messages sent from the portfolio contact form appear here. This page is connected to the same local database.</p>
                        </div>
                        <div class="col-lg-5">
                            <div class="inbox-summary">
                                <div class="summary-card">
                                    <span>Total messages</span>
                                    <strong><?= $messageCount ?></strong>
                                </div>
                                <div class="summary-card">
                                    <span>Latest hello</span>
                                    <strong><?= e($latestMessage) ?></strong>
                                </div>
                            </div>
                            <a class="btn-quiet" href="index.php"><i class="bi bi-arrow-left"></i>Back to portfolio</a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="container">
                <?php if ($messageCount === 0): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-envelope-open"></i></div>
                        <h2>No messages yet</h2>
                        <p>When someone sends a hi from the portfolio, their message will show up here.</p>
                        <a class="btn-ink" href="index.php#contact">Send the first hi <i class="bi bi-arrow-up-right"></i></a>
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
                                            <a href="mailto:<?= e($item['email']) ?>"><?= e($item['email']) ?></a>
                                        </div>
                                    </div>
                                    <time datetime="<?= e($item['created_at']) ?>"><?= e(prettyDate($item['created_at'])) ?></time>
                                </div>
                                <p class="message-body"><?= nl2br(e($item['message'])) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    <?php else: ?>
        <main>
            <section class="hero">
                <div class="container">
                    <div class="row align-items-center g-5">
                        <div class="col-lg-7">
                            <div class="eyebrow">Grade 12 student &middot; 2026</div>
                            <h1 class="hero-title">Curious mind.<br>Clean code.<br><em>Real projects.</em></h1>
                            <p class="hero-copy">I am Abdulmejid, a grade 12 student learning how to turn ideas into simple, useful websites. I enjoy clean design, solving small problems, and getting a little better with every project.</p>
                            <div class="hero-actions">
                                <a class="btn-ink" href="#contact">Send a hi <i class="bi bi-arrow-up-right"></i></a>
                                <a class="btn-quiet" href="#work">View my work</a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="hero-panel">
                                <div class="panel-top">
                                    <span>Student portfolio</span>
                                    <span>2026</span>
                                </div>
                                <div class="status-row">
                                    <div class="avatar">A</div>
                                    <div>
                                        <h2>Abdulmejid</h2>
                                        <p>Learner &middot; Builder &middot; Student</p>
                                    </div>
                                </div>
                                <div class="panel-rule"></div>
                                <div class="mini-info">
                                    <span>HTML &amp; CSS</span>
                                    <span>PHP</span>
                                    <span>UI design</span>
                                </div>
                                <p class="status-note"><span class="status-dot"></span>Open to learning and small projects</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="stats">
                <div class="container">
                    <div class="stats-grid">
                        <div class="stat">
                            <strong>12</strong>
                            <span>Grade level</span>
                        </div>
                        <div class="stat">
                            <strong>03</strong>
                            <span>Projects built</span>
                        </div>
                        <div class="stat">
                            <strong>2026</strong>
                            <span>Graduation year</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section" id="about">
                <div class="container">
                    <div class="row g-5">
                        <div class="col-lg-6">
                            <div class="section-kicker"><span>01</span>About me</div>
                            <h2 class="section-title">A student who likes<br><em>making things work.</em></h2>
                            <p class="body-copy">My favorite part of web development is the moment a page finally feels right: the layout is clear, the buttons work, and someone can use it without asking questions. I am still learning, and that is the part I enjoy most.</p>
                        </div>
                        <div class="col-lg-5 offset-lg-1">
                            <div class="about-card">
                                <ul class="about-list">
                                    <li><span>School</span><strong>Grade 12</strong></li>
                                    <li><span>Focus</span><strong>Web development</strong></li>
                                    <li><span>Tools</span><strong>HTML, CSS, PHP</strong></li>
                                    <li><span>Currently</span><strong>Building my portfolio</strong></li>
                                    <li><span>Goal</span><strong>Keep learning by doing</strong></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section pt-0">
                <div class="container">
                    <div class="section-kicker"><span>02</span>What I am learning</div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="skill-card">
                                <div class="skill-icon"><i class="bi bi-code-slash"></i></div>
                                <h3>Front-end basics</h3>
                                <p>Building responsive pages with clean HTML, modern CSS, and Bootstrap components.</p>
                                <div class="tag-row">
                                    <span class="tag">HTML</span>
                                    <span class="tag">CSS</span>
                                    <span class="tag">Bootstrap</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="skill-card">
                                <div class="skill-icon"><i class="bi bi-server"></i></div>
                                <h3>PHP &amp; data</h3>
                                <p>Connecting forms to a database, saving information, and showing it back in a clear way.</p>
                                <div class="tag-row">
                                    <span class="tag">PHP</span>
                                    <span class="tag">SQLite</span>
                                    <span class="tag">Forms</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="skill-card">
                                <div class="skill-icon"><i class="bi bi-pen"></i></div>
                                <h3>Simple design</h3>
                                <p>Using spacing, type, and color to make a page feel calm, readable, and easy to use.</p>
                                <div class="tag-row">
                                    <span class="tag">UI design</span>
                                    <span class="tag">Layout</span>
                                    <span class="tag">Details</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section pt-0" id="work">
                <div class="container">
                    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
                        <div>
                            <div class="section-kicker"><span>03</span>Selected work</div>
                            <h2 class="section-title mb-0">Small projects,<br><em>real practice.</em></h2>
                        </div>
                        <a class="btn-quiet" href="#contact">Ask me about them <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <article class="project-card">
                                <div class="project-top">
                                    <span class="project-number">PROJECT 01</span>
                                    <span class="project-arrow"><i class="bi bi-arrow-up-right"></i></span>
                                </div>
                                <h3>Study Planner</h3>
                                <p>A simple weekly planner for keeping track of homework, tests, and study time without the clutter.</p>
                                <div class="tag-row">
                                    <span class="tag">HTML</span>
                                    <span class="tag">CSS</span>
                                    <span class="tag">JavaScript</span>
                                </div>
                            </article>
                        </div>
                        <div class="col-md-4">
                            <article class="project-card">
                                <div class="project-top">
                                    <span class="project-number">PROJECT 02</span>
                                    <span class="project-arrow"><i class="bi bi-arrow-up-right"></i></span>
                                </div>
                                <h3>Class Library</h3>
                                <p>A small book catalog where students can browse titles and see which books are available.</p>
                                <div class="tag-row">
                                    <span class="tag">PHP</span>
                                    <span class="tag">SQLite</span>
                                    <span class="tag">Bootstrap</span>
                                </div>
                            </article>
                        </div>
                        <div class="col-md-4">
                            <article class="project-card">
                                <div class="project-top">
                                    <span class="project-number">PROJECT 03</span>
                                    <span class="project-arrow"><i class="bi bi-arrow-up-right"></i></span>
                                </div>
                                <h3>Weather Dashboard</h3>
                                <p>A clean weather page that turns a public API response into a quick, readable forecast.</p>
                                <div class="tag-row">
                                    <span class="tag">API</span>
                                    <span class="tag">JavaScript</span>
                                    <span class="tag">UI</span>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section pt-0" id="contact">
                <div class="container">
                    <div class="contact-wrap">
                        <div class="row g-5 align-items-center">
                            <div class="col-lg-5">
                                <div class="section-kicker"><span>04</span>Say hello</div>
                                <h2 class="section-title">Send me a hi.</h2>
                                <p class="contact-copy">Have a question, a project idea, or just want to say hello? Leave a short message. It will be saved in the inbox page.</p>
                                <div class="contact-links">
                                    <a href="mailto:hello@example.com"><i class="bi bi-envelope"></i>hello@example.com</a>
                                    <a href="index.php?page=messages"><i class="bi bi-inbox"></i>View the inbox</a>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="form-panel">
                                    <?php if ($error !== ''): ?>
                                        <div class="form-alert error"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
                                    <?php endif; ?>
                                    <?php if ($flash !== ''): ?>
                                        <div class="form-alert success"><i class="bi bi-check-circle me-1"></i><?= e($flash) ?></div>
                                    <?php endif; ?>
                                    <form method="post" action="index.php#contact">
                                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                        <div class="row g-3">
                                            <div class="col-sm-6">
                                                <label class="form-label" for="name">Your name</label>
                                                <input class="form-control" type="text" id="name" name="name" value="<?= e($name) ?>" placeholder="Your name" maxlength="80" required>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label" for="email">Email</label>
                                                <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="you@example.com" maxlength="120" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="message">Your message</label>
                                                <textarea class="form-control" id="message" name="message" placeholder="Say hi, ask a question, or share an idea..." maxlength="1000" required><?= e($message) ?></textarea>
                                            </div>
                                            <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3">
                                                <button class="btn-send" type="submit">Send hi <i class="bi bi-arrow-up-right"></i></button>
                                                <p class="form-note mb-0">Stored locally. No spam, ever.</p>
                                            </div>
                                        </div>
                                    </form>
                                </div>
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
                <span>&copy; <?= date('Y') ?> Abdulmejid &middot; Grade 12 portfolio</span>
                <div class="footer-links">
                    <a href="index.php">Home</a>
                    <a href="index.php#work">Work</a>
                    <a href="index.php#contact">Contact</a>
                    <a href="index.php?page=messages">Inbox</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
