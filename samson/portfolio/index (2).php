<?php
include "db.php";

$sent = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $message = trim($_POST["message"]);

    if ($name != "" && $message != "") {
        $stmt = mysqli_prepare($connect, "INSERT INTO messages (name, message) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $name, $message);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Samuel — Grade 12 Portfolio</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>

<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="index.php">Samuel</a>
        <nav class="nav">
            <a href="#about">About</a>
            <a href="#learning">Learning</a>
            <a href="#projects">Projects</a>
            <a href="#hi">Send Hi</a>
            <a class="nav-cta" href="messages.php">Messages</a>
        </nav>
    </div>
</header>

<section class="hero">
    <div class="container">
        <p class="hero-tag">Portfolio</p>
        <h1>Samuel</h1>
        <p class="hero-lead">Grade 12 student at Kings School, focused on web development.</p>
        <div class="hero-meta">
            <span>Grade 12</span>
            <span>Kings School</span>
            <span>Web Development</span>
        </div>
        <div class="hero-actions">
            <a class="btn btn-accent" href="#hi">Send me a Hi</a>
            <a class="btn btn-plain" href="messages.php">See messages</a>
        </div>
    </div>
</section>

<section class="section" id="about">
    <div class="container split">
        <div class="split-label">
            <p class="section-tag">About</p>
        </div>
        <div class="split-body">
            <h2>I build simple websites and I am learning how the web really works.</h2>
            <p>My name is Samuel. I am in grade 12 at Kings School and my focus is web development. I like taking an idea and turning it into a real page that people can open in a browser.</p>
            <p>Right now I am working on my backend skills, writing PHP and storing data with MySQL.</p>
        </div>
    </div>
</section>

<section class="section section-alt" id="learning">
    <div class="container split">
        <div class="split-label">
            <p class="section-tag">Learning</p>
        </div>
        <div class="split-body">
            <h2>What I am learning</h2>
            <div class="grid-3">
                <div class="info-card">
                    <span class="info-num">01</span>
                    <h3>Backend basics</h3>
                    <p>How a server receives a request, runs code and sends an answer back to the browser.</p>
                </div>
                <div class="info-card">
                    <span class="info-num">02</span>
                    <h3>PHP</h3>
                    <p>Writing PHP pages that handle forms, check data and work with files on the server.</p>
                </div>
                <div class="info-card">
                    <span class="info-num">03</span>
                    <h3>MySQL</h3>
                    <p>Creating databases and tables, then inserting and reading rows with SQL queries.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" id="projects">
    <div class="container split">
        <div class="split-label">
            <p class="section-tag">Projects</p>
        </div>
        <div class="split-body">
            <h2>My projects</h2>
            <div class="project">
                <div class="project-head">
                    <span class="project-num">01</span>
                    <h3>Portfolio with HTML, CSS and JS</h3>
                </div>
                <p>A personal portfolio built with HTML for structure, CSS for style and JavaScript for small interactions like a menu and a dark button.</p>
                <div class="tags">
                    <span class="tag">HTML</span>
                    <span class="tag">CSS</span>
                    <span class="tag">JavaScript</span>
                </div>
            </div>
            <div class="project">
                <div class="project-head">
                    <span class="project-num">02</span>
                    <h3>Portfolio with PHP</h3>
                </div>
                <p>The same portfolio idea rebuilt with PHP and MySQL. It has a contact form that saves every message into a database table.</p>
                <div class="tags">
                    <span class="tag">PHP</span>
                    <span class="tag">MySQL</span>
                    <span class="tag">Bootstrap</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section section-alt" id="hi">
    <div class="container split">
        <div class="split-label">
            <p class="section-tag">Contact</p>
        </div>
        <div class="split-body">
            <h2>Send me a Hi</h2>
            <p class="form-intro">Write your name and a short message. It is saved in MySQL and appears on the messages page.</p>

            <?php if ($sent): ?>
                <div class="notice">Hi message sent. <a href="messages.php">View messages</a></div>
            <?php endif; ?>

            <form method="post" class="hi-form">
                <div class="mb-3">
                    <label class="form-label" for="name">Your name</label>
                    <input class="form-control" type="text" id="name" name="name" placeholder="e.g. Sara" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="message">Your message</label>
                    <textarea class="form-control" id="message" name="message" rows="4" placeholder="Hi! ..." required></textarea>
                </div>
                <button class="btn btn-accent" type="submit">Send Hi</button>
            </form>
        </div>
    </div>
</section>

<footer class="site-footer">
    <div class="container footer-wrap">
        <span>Samuel · Grade 12 · Kings School</span>
        <span>© 2026 Samuel</span>
    </div>
</footer>

<script src="bootstrap.bundle.min.js"></script>
</body>
</html>
