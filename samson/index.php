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
}

$flash = get_flash();
$contactState = get_contact_state();
$old = $contactState['old'];
$errors = $contactState['errors'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Samson Desalign | Grade 12 Web Developer</title>
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
            --text-soft: #5d6b7a;
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
            color: var(--text-soft);
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
            color: var(--text-soft);
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
            color: var(--text-soft);
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
            color: var(--text-soft);
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
            color: var(--text-soft);
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
            color: var(--text-soft);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .project-status {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--text-soft);
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
            color: var(--text-soft);
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
<body>
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
                <a href="index.php">Home</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
