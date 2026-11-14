<?php
require __DIR__ . '/config.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$pdo = null;
$dbError = null;

try {
    $pdo = db();
} catch (Throwable $exception) {
    error_log('Portfolio database error: ' . $exception->getMessage());
    $dbError = 'The message form is temporarily unavailable. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_message') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $old = ['name' => $name, 'email' => $email, 'message' => $message];

        if ($dbError !== null) {
            set_contact_state([], $old);
            set_flash('danger', $dbError);
            redirect('index.php#contact');
        }

        if (!csrf_ok()) {
            set_contact_state([], $old);
            set_flash('danger', 'Your session expired. Please try again.');
            redirect('index.php#contact');
        }

        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            set_flash('success', 'Thanks for saying hi!');
            redirect('index.php#contact');
        }

        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors['name'] = 'Enter your name (2 to 80 characters).';
        }

        if ($email === '' || mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if (mb_strlen($message) < 5 || mb_strlen($message) > 1000) {
            $errors['message'] = 'Write a message between 5 and 1000 characters.';
        }

        if ($errors) {
            set_contact_state($errors, $old);
            set_flash('danger', 'Please check the fields below and try again.');
            redirect('index.php#contact');
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO messages (name, email, message) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, $message]);
            unset($_SESSION['csrf']);
            set_flash('success', 'Thanks for saying hi! Your message is safely in my inbox.');
        } catch (Throwable $exception) {
            error_log('Message insert error: ' . $exception->getMessage());
            set_contact_state([], $old);
            set_flash('danger', 'Something went wrong while saving your message. Please try again.');
        }

        redirect('index.php#contact');
    }

    if ($action === 'setup_owner') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($dbError !== null) {
            set_flash('danger', $dbError);
            redirect('index.php?view=messages');
        }

        if (!csrf_ok()) {
            set_flash('danger', 'Your session expired. Please try again.');
            redirect('index.php?view=messages');
        }

        $errors = [];

        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
            $errors[] = 'Choose a username with 3 to 30 letters, numbers, dots, dashes, or underscores.';
        }

        if (mb_strlen($password) < 8) {
            $errors[] = 'Use a password with at least 8 characters.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'The passwords do not match.';
        }

        if ($errors) {
            set_flash('danger', implode(' ', $errors));
            redirect('index.php?view=messages');
        }

        if (owner_count($pdo) > 0) {
            set_flash('danger', 'An owner account already exists. Please sign in.');
            redirect('index.php?view=messages');
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO owners (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['owner_id'] = (int) $pdo->lastInsertId();
            $_SESSION['owner_username'] = $username;
            unset($_SESSION['csrf']);
            set_flash('success', 'Owner account created. You are now signed in.');
        } catch (Throwable $exception) {
            error_log('Owner setup error: ' . $exception->getMessage());
            set_flash('danger', 'The owner account could not be created. Please try again.');
        }

        redirect('index.php?view=messages');
    }

    if ($action === 'login') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($dbError !== null) {
            set_flash('danger', $dbError);
            redirect('index.php?view=messages');
        }

        if (!csrf_ok()) {
            set_flash('danger', 'Your session expired. Please try again.');
            redirect('index.php?view=messages');
        }

        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM owners WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $account = $stmt->fetch();

        if ($account && password_verify($password, $account['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['owner_id'] = (int) $account['id'];
            $_SESSION['owner_username'] = $account['username'];
            unset($_SESSION['csrf']);
            set_flash('success', 'Welcome back, ' . $account['username'] . '.');
            redirect('index.php?view=messages');
        }

        usleep(400000);
        set_flash('danger', 'Those details did not match an owner account.');
        redirect('index.php?view=messages');
    }

    if ($action === 'logout') {
        if (!csrf_ok()) {
            set_flash('danger', 'Your session expired. Please try again.');
            redirect('index.php?view=messages');
        }

        unset($_SESSION['owner_id'], $_SESSION['owner_username']);
        session_regenerate_id(true);
        set_flash('success', 'You have been signed out.');
        redirect('index.php');
    }

    if ($action === 'delete_message') {
        $owner = current_owner();

        if (!$owner) {
            http_response_code(403);
            exit('Access denied.');
        }

        if ($dbError !== null) {
            set_flash('danger', $dbError);
            redirect('index.php?view=messages');
        }

        if (!csrf_ok()) {
            set_flash('danger', 'Your session expired. Please try again.');
            redirect('index.php?view=messages');
        }

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ?');
            $stmt->execute([$id]);
            set_flash($stmt->rowCount() > 0 ? 'success' : 'warning', $stmt->rowCount() > 0 ? 'Message deleted.' : 'That message was already gone.');
        }

        redirect('index.php?view=messages');
    }
}

$view = ($_GET['view'] ?? '') === 'messages' ? 'messages' : 'home';
$owner = current_owner();
$messages = [];
$totalMessages = 0;
$latestMessageAt = null;
$authMode = null;

if ($view === 'messages' && $dbError === null) {
    if (!$owner) {
        $authMode = owner_count($pdo) === 0 ? 'setup' : 'login';
    } else {
        try {
            $stmt = $pdo->query('SELECT id, name, email, message, created_at FROM messages ORDER BY created_at DESC, id DESC LIMIT 100');
            $messages = $stmt->fetchAll();
            $totalMessages = (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
            $latestMessageAt = $messages[0]['created_at'] ?? null;
        } catch (Throwable $exception) {
            error_log('Inbox query error: ' . $exception->getMessage());
            $dbError = 'The inbox could not be loaded. Please try again later.';
        }
    }
}

if ($view === 'messages') {
    header('Cache-Control: no-store, private');
}

$flash = get_flash();
$contactState = $view === 'home' ? get_contact_state() : ['errors' => [], 'old' => []];
$old = $contactState['old'];
$errors = $contactState['errors'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $view === 'messages' ? 'Owner Inbox | Samson Desalign' : 'Samson Desalign | Grade 12 Web Developer' ?></title>
    <?php if ($view === 'messages'): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <meta name="description" content="The personal portfolio of Samson Desalign, a Grade 12 student at Kings learning web development with HTML, CSS, PHP and MySQL.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --navy: #102a43;
            --navy-deep: #081c2e;
            --teal: #0f766e;
            --teal-dark: #0b5d57;
            --teal-soft: #d9efeb;
            --gold: #e7aa2d;
            --gold-soft: #fbf1d6;
            --canvas: #f5f4ef;
            --card: #ffffff;
            --text: #182430;
            --subtle: #5d6b7a;
            --border: #e2e5e6;
            --corner: 22px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
            background: var(--canvas);
            color: var(--text);
            line-height: 1.7;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        .navbar-brand {
            font-family: "Sora", system-ui, sans-serif;
            letter-spacing: -0.03em;
        }

        a {
            color: var(--teal);
            text-decoration: none;
        }

        a:hover {
            color: var(--teal-dark);
        }

        .skip-link {
            position: absolute;
            top: -60px;
            left: 1rem;
            z-index: 2000;
            background: var(--gold);
            color: var(--navy-deep);
            padding: 0.6rem 1rem;
            border-radius: 0 0 10px 10px;
            font-weight: 700;
        }

        .skip-link:focus {
            top: 0;
        }

        .site-nav {
            background: rgba(245, 244, 239, 0.92);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(16, 42, 67, 0.08);
            padding: 0.9rem 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            color: var(--navy) !important;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: var(--navy);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            letter-spacing: 0.02em;
        }

        .brand-dot {
            color: var(--gold);
        }

        .site-nav .nav-link {
            color: var(--navy);
            font-size: 0.92rem;
            font-weight: 500;
            padding: 0.5rem 0.85rem !important;
            border-radius: 999px;
        }

        .site-nav .nav-link:hover,
        .site-nav .nav-link:focus {
            color: var(--teal);
            background: rgba(15, 118, 110, 0.07);
        }

        .navbar-toggler {
            border: 0;
            box-shadow: none !important;
        }

        .btn {
            border-radius: 999px;
            font-weight: 600;
            padding: 0.75rem 1.4rem;
        }

        .btn-gold {
            background: var(--gold);
            border: 1px solid var(--gold);
            color: var(--navy-deep);
        }

        .btn-gold:hover,
        .btn-gold:focus {
            background: #f2bd4f;
            border-color: #f2bd4f;
            color: var(--navy-deep);
            transform: translateY(-1px);
        }

        .btn-brand {
            background: var(--navy);
            border: 1px solid var(--navy);
            color: #ffffff;
        }

        .btn-brand:hover,
        .btn-brand:focus {
            background: var(--teal);
            border-color: var(--teal);
            color: #ffffff;
        }

        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.32);
            color: #ffffff;
        }

        .btn-outline-light:hover,
        .btn-outline-light:focus {
            background: #ffffff;
            border-color: #ffffff;
            color: var(--navy);
        }

        .hero {
            position: relative;
            overflow: hidden;
            background: var(--navy-deep);
            color: #ffffff;
            padding: 9.5rem 0 6.5rem;
        }

        .hero::before {
            content: "";
            position: absolute;
            top: -180px;
            right: -120px;
            width: 560px;
            height: 560px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(15, 118, 110, 0.42), transparent 68%);
        }

        .hero::after {
            content: "";
            position: absolute;
            bottom: -260px;
            left: -180px;
            width: 520px;
            height: 520px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(231, 170, 45, 0.16), transparent 68%);
        }

        .hero .container {
            position: relative;
            z-index: 2;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.76rem;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.05);
            padding: 0.5rem 0.9rem;
            border-radius: 999px;
            margin-bottom: 1.75rem;
        }

        .eyebrow .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #65d6a6;
            box-shadow: 0 0 0 4px rgba(101, 214, 166, 0.14);
        }

        .hero h1 {
            font-size: clamp(2.7rem, 6vw, 5rem);
            line-height: 1.03;
            letter-spacing: -0.045em;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .hero h1 span {
            color: #7bd6c8;
        }

        .hero-lead {
            font-size: 1.1rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.72);
            max-width: 38rem;
            margin-bottom: 2rem;
        }

        .hero-lead strong {
            color: #ffffff;
            font-weight: 600;
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem;
            margin-top: 2.5rem;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.55);
        }

        .hero-meta i {
            color: var(--gold);
            margin-right: 0.35rem;
        }

        .profile-wrap {
            position: relative;
            max-width: 390px;
            margin-left: auto;
        }

        .profile-card {
            background: linear-gradient(155deg, rgba(255, 255, 255, 0.11), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 26px;
            padding: 1.6rem;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.28);
            backdrop-filter: blur(8px);
        }

        .profile-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.7rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.55);
        }

        .availability {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }

        .availability span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #65d6a6;
            box-shadow: 0 0 0 4px rgba(101, 214, 166, 0.14);
        }

        .monogram {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 2rem auto 1.4rem;
            display: grid;
            place-items: center;
            font-family: "Sora", sans-serif;
            font-size: 3.2rem;
            font-weight: 600;
            background: radial-gradient(circle at 30% 25%, #1d8f83, #0a4a46 70%);
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: inset 0 0 0 8px rgba(255, 255, 255, 0.045);
        }

        .profile-card h2 {
            font-size: 1.35rem;
            text-align: center;
            margin-bottom: 0.2rem;
        }

        .profile-role {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.84rem;
            margin-bottom: 1.3rem;
        }

        .profile-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            justify-content: center;
        }

        .profile-tags span {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
            padding: 0.32rem 0.65rem;
            border-radius: 999px;
            color: rgba(255, 255, 255, 0.82);
        }

        .profile-line {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 1.4rem 0;
        }

        .profile-note {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .profile-note i {
            color: var(--gold);
        }

        .float-chip {
            position: absolute;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #ffffff;
            color: var(--navy);
            border-radius: 999px;
            padding: 0.55rem 0.9rem;
            font-size: 0.72rem;
            font-weight: 700;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
            animation: float 5s ease-in-out infinite;
        }

        .float-chip i {
            color: var(--teal);
        }

        .chip-one {
            top: -16px;
            left: -24px;
        }

        .chip-two {
            bottom: -16px;
            right: -24px;
            animation-delay: 1.5s;
        }

        @keyframes float {
            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        .section {
            padding: 6.5rem 0;
            scroll-margin-top: 84px;
        }

        .section-alt {
            background: var(--card);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .section-kicker {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            font-weight: 700;
            color: var(--teal);
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: clamp(2rem, 4vw, 3.05rem);
            line-height: 1.12;
            font-weight: 600;
            margin-bottom: 1.25rem;
        }

        .lead-copy {
            font-size: 1.1rem;
            color: var(--subtle);
            max-width: 62ch;
        }

        .fact-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.1rem 1.2rem;
            height: 100%;
        }

        .fact-card span {
            display: block;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--subtle);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .fact-card strong {
            font-family: "Sora", sans-serif;
            font-size: 1.15rem;
            font-weight: 600;
        }

        .about-list {
            list-style: none;
            padding: 0;
            margin: 2rem 0 0;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .about-list li {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--navy);
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 0.45rem 0.8rem;
        }

        .about-list i {
            color: var(--teal);
        }

        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--corner);
            padding: 2rem;
            height: 100%;
        }

        .panel-title {
            font-size: 1.35rem;
            margin-bottom: 0.5rem;
        }

        .panel-intro {
            font-size: 0.9rem;
            color: var(--subtle);
            margin-bottom: 0.5rem;
        }

        .skill-item {
            margin-top: 1.5rem;
        }

        .skill-head {
            display: flex;
            justify-content: space-between;
            font-size: 0.84rem;
            font-weight: 600;
            margin-bottom: 0.45rem;
        }

        .skill-head span:last-child {
            color: var(--subtle);
            font-weight: 500;
        }

        .skill-bar {
            height: 8px;
            background: #e9ebe8;
            border-radius: 999px;
            overflow: hidden;
        }

        .skill-bar span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, var(--teal), #3aa79b);
            border-radius: 999px;
        }

        .learning-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #7a570d;
            background: var(--gold-soft);
            border: 1px solid #f0dca4;
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            margin-bottom: 1rem;
        }

        .learning-list {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0 0;
        }

        .learning-list li {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-top: 1px solid var(--border);
        }

        .learning-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--teal-soft);
            color: var(--teal);
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            font-size: 1.1rem;
        }

        .learning-list strong {
            display: block;
            font-size: 0.94rem;
        }

        .learning-list span {
            display: block;
            font-size: 0.84rem;
            color: var(--subtle);
            margin-top: 0.15rem;
        }

        .project-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--corner);
            overflow: hidden;
            height: 100%;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 24px 50px rgba(16, 42, 67, 0.1);
        }

        .project-visual {
            height: 250px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 2rem;
        }

        .visual-one {
            background: linear-gradient(135deg, #dff1ed, #b8ddd4);
        }

        .visual-two {
            background: linear-gradient(135deg, #f7e8ca, #ecd08f);
        }

        .project-number {
            position: absolute;
            top: 1rem;
            right: 1.4rem;
            font-family: "Sora", sans-serif;
            font-size: 1rem;
            font-weight: 600;
            color: rgba(16, 42, 67, 0.45);
        }

        .browser {
            width: 100%;
            max-width: 310px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 18px 35px rgba(16, 42, 67, 0.16);
            overflow: hidden;
            transform: rotate(-2deg);
            transition: transform 0.3s ease;
        }

        .project-card:hover .browser {
            transform: rotate(0);
        }

        .browser-bar {
            height: 28px;
            background: #f1f3f2;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 0 10px;
            border-bottom: 1px solid #e4e7e5;
        }

        .browser-bar span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #ccd3d0;
        }

        .browser-body {
            padding: 14px;
        }

        .browser-line {
            height: 7px;
            border-radius: 999px;
            background: #dde3e0;
            margin-bottom: 7px;
        }

        .browser-w-70 {
            width: 70%;
        }

        .browser-w-40 {
            width: 40%;
        }

        .browser-blocks {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-top: 12px;
        }

        .browser-blocks div {
            height: 35px;
            border-radius: 6px;
            background: #e6efec;
        }

        .browser-dark .browser-body {
            background: var(--navy);
        }

        .browser-dark .browser-line {
            background: rgba(255, 255, 255, 0.22);
        }

        .browser-dark .browser-blocks div {
            background: rgba(255, 255, 255, 0.1);
        }

        .project-body {
            padding: 1.6rem;
        }

        .project-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 0.8rem;
        }

        .project-tags span {
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--teal);
            background: var(--teal-soft);
            padding: 0.25rem 0.55rem;
            border-radius: 6px;
        }

        .project-body h3 {
            font-size: 1.28rem;
            margin-bottom: 0.5rem;
        }

        .project-body p {
            color: var(--subtle);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .project-status {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--subtle);
        }

        .project-status i {
            color: var(--teal);
        }

        .contact-section {
            position: relative;
            overflow: hidden;
            background: var(--navy);
            color: #ffffff;
            padding: 6.5rem 0;
            scroll-margin-top: 84px;
        }

        .contact-section::before {
            content: "";
            position: absolute;
            top: -220px;
            right: -140px;
            width: 520px;
            height: 520px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(15, 118, 110, 0.34), transparent 68%);
        }

        .contact-section .container {
            position: relative;
            z-index: 2;
        }

        .contact-section .section-kicker {
            color: #7bd6c8;
        }

        .contact-section .section-title {
            color: #ffffff;
        }

        .contact-copy {
            color: rgba(255, 255, 255, 0.68);
            font-size: 1.03rem;
            max-width: 34rem;
        }

        .contact-note {
            display: flex;
            gap: 0.9rem;
            margin-top: 2rem;
            padding: 1rem 1.1rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.05);
            border-radius: 14px;
            max-width: 34rem;
        }

        .contact-note i {
            color: var(--gold);
            font-size: 1.2rem;
        }

        .contact-note strong {
            display: block;
            font-size: 0.84rem;
        }

        .contact-note span {
            font-size: 0.77rem;
            color: rgba(255, 255, 255, 0.58);
        }

        .form-card {
            background: #ffffff;
            color: var(--text);
            border-radius: var(--corner);
            padding: 2rem;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.25);
        }

        .form-label {
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 0.4rem;
        }

        .form-control {
            background: #f8f9f7;
            border: 1px solid #dde2df;
            border-radius: 12px;
            padding: 0.75rem 0.9rem;
            font-size: 0.94rem;
            color: var(--text);
        }

        .form-control:focus {
            background: #ffffff;
            border-color: var(--teal);
            box-shadow: 0 0 0 0.2rem rgba(15, 118, 110, 0.12);
        }

        .form-control.is-invalid {
            border-color: #c0392b;
            background: #fff8f7;
        }

        .invalid-feedback {
            font-size: 0.76rem;
        }

        .honeypot {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        .form-note {
            font-size: 0.75rem;
            color: var(--subtle);
            margin-top: 0.85rem;
            margin-bottom: 0;
        }

        .alert {
            border-radius: 12px;
            font-size: 0.86rem;
            border: 0;
        }

        .site-footer {
            background: var(--navy-deep);
            color: rgba(255, 255, 255, 0.48);
            padding: 1.75rem 0;
            font-size: 0.78rem;
        }

        .site-footer a {
            color: rgba(255, 255, 255, 0.7);
        }

        .site-footer a:hover {
            color: var(--gold);
        }

        .inbox-page {
            background: var(--canvas);
        }

        .inbox-nav {
            background: rgba(245, 244, 239, 0.94);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(16, 42, 67, 0.08);
            padding: 1rem 0;
        }

        .inbox-main {
            padding: 4rem 0 5rem;
            min-height: calc(100vh - 150px);
        }

        .inbox-title {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 600;
            margin-bottom: 0.4rem;
        }

        .inbox-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1rem 1.2rem;
        }

        .stat-card span {
            display: block;
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--subtle);
            font-weight: 600;
            margin-bottom: 0.2rem;
        }

        .stat-card strong {
            font-family: "Sora", sans-serif;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .auth-wrap {
            max-width: 480px;
            margin: 2.5rem auto;
        }

        .auth-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--corner);
            padding: 2.2rem;
            box-shadow: 0 20px 50px rgba(16, 42, 67, 0.08);
        }

        .auth-mark {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: var(--navy);
            color: #ffffff;
            display: grid;
            place-items: center;
            font-family: "Sora", sans-serif;
            font-weight: 600;
            margin-bottom: 1.25rem;
        }

        .auth-card h1 {
            font-size: 1.7rem;
            margin-bottom: 0.5rem;
        }

        .auth-card > p {
            font-size: 0.9rem;
            color: var(--subtle);
        }

        .message-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 1.4rem;
            margin-bottom: 1rem;
        }

        .message-head {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .message-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--teal-soft);
            color: var(--teal-dark);
            display: grid;
            place-items: center;
            font-weight: 700;
            flex: 0 0 auto;
        }

        .message-who {
            flex: 1;
            min-width: 0;
        }

        .message-who h2 {
            font-size: 1rem;
            margin: 0 0 0.1rem;
        }

        .message-who a {
            font-size: 0.8rem;
            color: var(--subtle);
            overflow-wrap: anywhere;
        }

        .message-time {
            font-size: 0.7rem;
            color: var(--subtle);
            white-space: nowrap;
            padding-top: 0.2rem;
        }

        .message-text {
            margin: 1rem 0 0;
            font-size: 0.92rem;
            color: var(--text);
            line-height: 1.75;
            overflow-wrap: anywhere;
        }

        .message-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .inbox-empty {
            text-align: center;
            padding: 4rem 1.5rem;
            background: var(--card);
            border: 1px dashed #cfd6d4;
            border-radius: var(--corner);
        }

        .inbox-empty i {
            font-size: 2.2rem;
            color: var(--teal);
        }

        .inbox-empty h2 {
            font-size: 1.3rem;
            margin: 1rem 0 0.4rem;
        }

        .inbox-empty p {
            color: var(--subtle);
            font-size: 0.88rem;
            margin-bottom: 0;
        }

        .inbox-note {
            font-size: 0.75rem;
            color: var(--subtle);
        }

        .btn-outline-dark {
            border-radius: 999px;
        }

        .btn-outline-danger {
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 600;
        }

        @media (max-width: 767.98px) {
            .inbox-stats {
                grid-template-columns: 1fr;
            }

            .message-head {
                flex-wrap: wrap;
            }

            .message-time {
                width: 100%;
                padding-left: 60px;
                white-space: normal;
            }
        }

        @media (max-width: 991.98px) {
            .hero {
                padding: 8rem 0 5rem;
            }

            .hero h1 {
                font-size: clamp(2.6rem, 8vw, 4rem);
            }

            .profile-wrap {
                margin: 0 auto;
            }

            .float-chip {
                display: none;
            }

            .section {
                padding: 5rem 0;
            }

            .contact-section {
                padding: 5rem 0;
            }
        }

        @media (max-width: 575.98px) {
            .hero {
                padding-top: 7.5rem;
            }

            .hero h1 {
                font-size: 2.55rem;
            }

            .hero .btn {
                width: 100%;
            }

            .hero-meta {
                gap: 0.8rem;
            }

            .section {
                padding: 4rem 0;
            }

            .form-card,
            .panel {
                padding: 1.4rem;
            }

            .project-visual {
                height: 220px;
            }

            .section-title {
                font-size: 2rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="<?= $view === 'messages' ? 'inbox-page' : '' ?>">
    <?php if ($view === 'home'): ?>
    <a class="skip-link" href="#main">Skip to content</a>

    <nav class="navbar navbar-expand-lg sticky-top site-nav" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <span class="brand-mark">SD</span>
                <span>Samson<span class="brand-dot">.</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#skills">Skills</a></li>
                    <li class="nav-item"><a class="nav-link" href="#projects">Projects</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-gold btn-sm px-3 py-2" href="#contact">Say hi</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main id="main">
        <section class="hero" id="home">
            <div class="container">
                <div class="row align-items-center g-5">
                    <div class="col-lg-7">
                        <div class="eyebrow"><span class="dot"></span> Grade 12 &middot; Kings</div>
                        <h1>I build clean websites<br>that feel <span>simple.</span></h1>
                        <p class="hero-lead">
                            Hi, I'm <strong>Samson Desalign</strong> &mdash; a Grade 12 student at Kings learning web development one project at a time.
                            I care about clear layouts, thoughtful details, and code I can understand.
                        </p>
                        <div class="d-flex flex-wrap gap-3">
                            <a class="btn btn-gold btn-lg" href="#projects">Explore my work <i class="bi bi-arrow-down-right ms-1"></i></a>
                            <a class="btn btn-outline-light btn-lg" href="#contact">Say hi</a>
                        </div>
                        <div class="hero-meta">
                            <span><i class="bi bi-code-slash"></i> HTML &middot; CSS &middot; PHP</span>
                            <span><i class="bi bi-geo-alt"></i> Kings</span>
                            <span><i class="bi bi-mortarboard"></i> Grade 12</span>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="profile-wrap">
                            <div class="float-chip chip-one"><i class="bi bi-code-slash"></i> HTML &amp; CSS</div>
                            <div class="float-chip chip-two"><i class="bi bi-database"></i> PHP + MySQL</div>
                            <div class="profile-card">
                                <div class="profile-top">
                                    <span class="availability"><span></span> Open to learning</span>
                                    <span>2026</span>
                                </div>
                                <div class="monogram">SD</div>
                                <h2>Samson Desalign</h2>
                                <p class="profile-role">Grade 12 &middot; Web development</p>
                                <div class="profile-tags">
                                    <span>HTML</span>
                                    <span>CSS</span>
                                    <span>PHP</span>
                                    <span>MySQL</span>
                                </div>
                                <div class="profile-line"></div>
                                <div class="profile-note"><i class="bi bi-mortarboard"></i> Kings</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="about">
            <div class="container">
                <div class="row g-4 g-lg-5 align-items-start">
                    <div class="col-lg-4">
                        <p class="section-kicker">01 &mdash; About me</p>
                        <h2 class="section-title">A student with a builder's mindset.</h2>
                    </div>
                    <div class="col-lg-8">
                        <p class="lead-copy">
                            I'm Samson, a Grade 12 student at Kings with a growing interest in web development. I like turning an idea into a page that is easy to use, looks neat on every screen, and does something useful.
                        </p>
                        <p class="text-secondary mt-3 mb-4">
                            My main focus is web development. I started with HTML and CSS, then moved into PHP and MySQL so my pages could store real information. I'm still learning, and every project helps me write cleaner code and make better design choices.
                        </p>
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <div class="fact-card">
                                    <span>Grade</span>
                                    <strong>12</strong>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="fact-card">
                                    <span>School</span>
                                    <strong>Kings</strong>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="fact-card">
                                    <span>Main focus</span>
                                    <strong>Web development</strong>
                                </div>
                            </div>
                        </div>
                        <ul class="about-list">
                            <li><i class="bi bi-check2-circle"></i> Clean layouts</li>
                            <li><i class="bi bi-check2-circle"></i> Mobile-friendly pages</li>
                            <li><i class="bi bi-check2-circle"></i> Learning by building</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section-alt" id="skills">
            <div class="container">
                <div class="row g-4 g-lg-5">
                    <div class="col-lg-6">
                        <div class="panel">
                            <p class="section-kicker">02 &mdash; Skills</p>
                            <h2 class="panel-title">What I can build</h2>
                            <p class="panel-intro">The tools I use for school projects and personal practice.</p>
                            <div class="skill-item">
                                <div class="skill-head"><span>HTML &amp; CSS</span><span>Comfortable</span></div>
                                <div class="skill-bar"><span style="width: 82%"></span></div>
                            </div>
                            <div class="skill-item">
                                <div class="skill-head"><span>Responsive layouts</span><span>Comfortable</span></div>
                                <div class="skill-bar"><span style="width: 75%"></span></div>
                            </div>
                            <div class="skill-item">
                                <div class="skill-head"><span>PHP &amp; MySQL</span><span>Learning</span></div>
                                <div class="skill-bar"><span style="width: 55%"></span></div>
                            </div>
                            <div class="skill-item">
                                <div class="skill-head"><span>JavaScript basics</span><span>Learning</span></div>
                                <div class="skill-bar"><span style="width: 45%"></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="panel">
                            <span class="learning-badge"><i class="bi bi-book"></i> Currently learning</span>
                            <h2 class="panel-title">Always adding something new</h2>
                            <p class="panel-intro">These are the topics I'm working on right now.</p>
                            <ul class="learning-list">
                                <li>
                                    <div class="learning-icon"><i class="bi bi-window-stack"></i></div>
                                    <div>
                                        <strong>Advanced frontend development</strong>
                                        <span>Better layouts, cleaner components, and more confident CSS.</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="learning-icon"><i class="bi bi-database-check"></i></div>
                                    <div>
                                        <strong>PHP and MySQL</strong>
                                        <span>Connecting forms to a database and handling data safely.</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="learning-icon"><i class="bi bi-phone"></i></div>
                                    <div>
                                        <strong>Responsive design</strong>
                                        <span>Making the same page feel right on a phone, tablet, and desktop.</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="projects">
            <div class="container">
                <div class="row g-4 align-items-end mb-4 mb-lg-5">
                    <div class="col-lg-8">
                        <p class="section-kicker">03 &mdash; Projects</p>
                        <h2 class="section-title mb-0">Small projects, real progress.</h2>
                    </div>
                    <div class="col-lg-4">
                        <p class="text-secondary mb-0">Each project taught me something I could use in the next one.</p>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <article class="project-card">
                            <div class="project-visual visual-one">
                                <span class="project-number">01</span>
                                <div class="browser">
                                    <div class="browser-bar"><span></span><span></span><span></span></div>
                                    <div class="browser-body">
                                        <div class="browser-line browser-w-70"></div>
                                        <div class="browser-line browser-w-40"></div>
                                        <div class="browser-blocks"><div></div><div></div><div></div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="project-body">
                                <div class="project-tags"><span>HTML</span><span>CSS</span></div>
                                <h3>Portfolio using HTML and CSS</h3>
                                <p>My first personal website, built from scratch with semantic HTML and responsive CSS.</p>
                                <div class="project-status"><i class="bi bi-check-circle"></i> First build completed</div>
                            </div>
                        </article>
                    </div>
                    <div class="col-md-6">
                        <article class="project-card">
                            <div class="project-visual visual-two">
                                <span class="project-number">02</span>
                                <div class="browser browser-dark">
                                    <div class="browser-bar"><span></span><span></span><span></span></div>
                                    <div class="browser-body">
                                        <div class="browser-line browser-w-70"></div>
                                        <div class="browser-line browser-w-40"></div>
                                        <div class="browser-blocks"><div></div><div></div><div></div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="project-body">
                                <div class="project-tags"><span>PHP</span><span>MySQL</span></div>
                                <h3>Portfolio with PHP</h3>
                                <p>The site you're viewing now, with a database-backed message form and a private inbox.</p>
                                <div class="project-status"><i class="bi bi-arrow-repeat"></i> In progress</div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="contact-section" id="contact">
            <div class="container">
                <div class="row g-5 align-items-center">
                    <div class="col-lg-5">
                        <p class="section-kicker">04 &mdash; Contact</p>
                        <h2 class="section-title">Say hi.</h2>
                        <p class="contact-copy">
                            Have a question, a suggestion, or just want to say hello? Send a short message. I read every one.
                        </p>
                        <div class="contact-note">
                            <i class="bi bi-envelope-paper"></i>
                            <div>
                                <strong>Messages go to a private inbox.</strong>
                                <span>Only the site owner can sign in and read them.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="form-card">
                            <?php if ($flash): ?>
                                <div class="alert alert-<?= e($flash['type']) ?> mb-4" role="alert">
                                    <?= e($flash['text']) ?>
                                </div>
                            <?php endif; ?>
                            <form method="post" action="index.php">
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <div class="honeypot" aria-hidden="true">
                                    <label for="website">Website</label>
                                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="name">Your name</label>
                                        <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>" minlength="2" maxlength="80" autocomplete="name" required>
                                        <?php if (isset($errors['name'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="email">Email address</label>
                                        <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" maxlength="120" autocomplete="email" required>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="message">Your message</label>
                                        <textarea class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>" id="message" name="message" rows="5" minlength="5" maxlength="1000" placeholder="Hi Samson, I wanted to..." required><?= e($old['message'] ?? '') ?></textarea>
                                        <?php if (isset($errors['message'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['message']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                                        <button class="btn btn-brand" type="submit">Send message <i class="bi bi-send ms-1"></i></button>
                                        <span class="form-note">Your details are only used to reply to you.</span>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
            <span>&copy; <?= date('Y') ?> Samson Desalign. Built with PHP, MySQL, and Bootstrap.</span>
            <div class="d-flex gap-3">
                <a href="#home">Back to top</a>
                <a href="index.php?view=messages"><?= $owner ? 'Messages' : 'Owner sign in' ?></a>
            </div>
        </div>
    </footer>
    <?php else: ?>
    <a class="skip-link" href="#main">Skip to content</a>
    <nav class="inbox-nav" aria-label="Owner navigation">
        <div class="container d-flex align-items-center justify-content-between gap-3">
            <a class="navbar-brand" href="index.php">
                <span class="brand-mark">SD</span>
                <span>Samson<span class="brand-dot">.</span></span>
            </a>
            <a class="btn btn-outline-dark btn-sm px-3" href="index.php"><i class="bi bi-arrow-left me-1"></i> View site</a>
        </div>
    </nav>

    <main class="inbox-main" id="main">
        <div class="container">
            <?php if ($dbError !== null): ?>
                <div class="auth-wrap">
                    <div class="auth-card">
                        <div class="auth-mark">SD</div>
                        <p class="section-kicker mb-2">Owner inbox</p>
                        <h1>Inbox unavailable</h1>
                        <p><?= e($dbError) ?></p>
                        <a class="btn btn-brand w-100 mt-2" href="index.php">Back to the portfolio</a>
                    </div>
                </div>
            <?php elseif (!$owner): ?>
                <div class="auth-wrap">
                    <?php if ($flash): ?>
                        <div class="alert alert-<?= e($flash['type']) ?> mb-3" role="alert"><?= e($flash['text']) ?></div>
                    <?php endif; ?>
                    <div class="auth-card">
                        <div class="auth-mark">SD</div>
                        <?php if ($authMode === 'setup'): ?>
                            <p class="section-kicker mb-2">First-time setup</p>
                            <h1>Create the owner account</h1>
                            <p>This setup appears only once. Choose a username and a password you will remember. Only this account can read the messages.</p>
                            <form method="post" action="index.php" class="mt-4">
                                <input type="hidden" name="action" value="setup_owner">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <div class="mb-3">
                                    <label class="form-label" for="setup-username">Username</label>
                                    <input class="form-control" type="text" id="setup-username" name="username" minlength="3" maxlength="30" autocomplete="username" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="setup-password">Password</label>
                                    <input class="form-control" type="password" id="setup-password" name="password" minlength="8" maxlength="100" autocomplete="new-password" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" for="setup-confirm">Confirm password</label>
                                    <input class="form-control" type="password" id="setup-confirm" name="confirm_password" minlength="8" maxlength="100" autocomplete="new-password" required>
                                </div>
                                <button class="btn btn-brand w-100" type="submit">Create owner account</button>
                            </form>
                            <p class="inbox-note mt-3 mb-0">Use at least 8 characters for the password.</p>
                        <?php else: ?>
                            <p class="section-kicker mb-2">Owner access</p>
                            <h1>Welcome back</h1>
                            <p>Sign in to read the private messages sent through the portfolio.</p>
                            <form method="post" action="index.php" class="mt-4">
                                <input type="hidden" name="action" value="login">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <div class="mb-3">
                                    <label class="form-label" for="login-username">Username</label>
                                    <input class="form-control" type="text" id="login-username" name="username" maxlength="30" autocomplete="username" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" for="login-password">Password</label>
                                    <input class="form-control" type="password" id="login-password" name="password" maxlength="100" autocomplete="current-password" required>
                                </div>
                                <button class="btn btn-brand w-100" type="submit">Sign in</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <p class="inbox-note text-center mt-3 mb-0">Private area. Messages are never shown without signing in.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
                    <div>
                        <p class="section-kicker mb-2">Owner inbox</p>
                        <h1 class="inbox-title">Messages</h1>
                        <p class="text-secondary mb-0">Signed in as <strong><?= e($owner['username']) ?></strong>. Only you can see this page.</p>
                    </div>
                    <form method="post" action="index.php">
                        <input type="hidden" name="action" value="logout">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <button class="btn btn-outline-dark btn-sm px-3" type="submit"><i class="bi bi-box-arrow-right me-1"></i> Sign out</button>
                    </form>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> mt-4 mb-0" role="alert"><?= e($flash['text']) ?></div>
                <?php endif; ?>

                <div class="inbox-stats">
                    <div class="stat-card">
                        <span>Total messages</span>
                        <strong><?= $totalMessages ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Latest message</span>
                        <strong><?= $latestMessageAt ? e(date('M j', strtotime($latestMessageAt))) : 'None yet' ?></strong>
                    </div>
                    <div class="stat-card">
                        <span>Access</span>
                        <strong>Owner only</strong>
                    </div>
                </div>

                <?php if (!$messages): ?>
                    <div class="inbox-empty">
                        <i class="bi bi-inbox"></i>
                        <h2>No messages yet</h2>
                        <p>When someone says hi through the portfolio, it will appear here.</p>
                    </div>
                <?php else: ?>
                    <?php if ($totalMessages > count($messages)): ?>
                        <p class="inbox-note">Showing the latest 100 of <?= $totalMessages ?> messages.</p>
                    <?php endif; ?>
                    <?php foreach ($messages as $message): ?>
                        <?php
                        $createdAt = strtotime($message['created_at']);
                        $createdLabel = $createdAt ? date('M j, Y', $createdAt) . ' at ' . date('g:i A', $createdAt) : $message['created_at'];
                        ?>
                        <article class="message-card">
                            <div class="message-head">
                                <div class="message-avatar"><?= e(mb_strtoupper(mb_substr($message['name'], 0, 1))) ?></div>
                                <div class="message-who">
                                    <h2><?= e($message['name']) ?></h2>
                                    <a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a>
                                </div>
                                <time class="message-time" datetime="<?= e($message['created_at']) ?>"><?= e($createdLabel) ?></time>
                            </div>
                            <p class="message-text"><?= nl2br(e($message['message'])) ?></p>
                            <div class="message-actions">
                                <form method="post" action="index.php" onsubmit="return confirm('Delete this message?');">
                                    <input type="hidden" name="action" value="delete_message">
                                    <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash3 me-1"></i> Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
            <span>&copy; <?= date('Y') ?> Samson Desalign. Private owner area.</span>
            <a href="index.php">Back to portfolio</a>
        </div>
    </footer>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
