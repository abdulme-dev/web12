<?php
include "db.php";

$result = mysqli_query($connect, "SELECT * FROM messages ORDER BY id DESC");
$messages = array();

while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages — Samuel</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>

<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="index.php">Samuel</a>
        <nav class="nav">
            <a href="index.php#about">About</a>
            <a href="index.php#learning">Learning</a>
            <a href="index.php#projects">Projects</a>
            <a href="index.php#hi">Send Hi</a>
            <a class="nav-cta" href="messages.php">Messages</a>
        </nav>
    </div>
</header>

<main class="container page-head">
    <p class="section-tag">Messages</p>
    <h1>Hi messages</h1>
    <p class="page-sub"><?php echo count($messages); ?> message(s) sent to Samuel</p>

    <div class="hero-actions">
        <a class="btn btn-accent" href="index.php#hi">Send a Hi</a>
        <a class="btn btn-plain" href="index.php">Back home</a>
    </div>

    <ul class="message-list">
        <?php if (count($messages) == 0): ?>
            <li class="empty">
                <strong>No messages yet</strong>
                Be the first to say hi.
            </li>
        <?php endif; ?>

        <?php foreach ($messages as $m): ?>
            <li class="message-item">
                <div class="meta">
                    <span class="who"><?php echo htmlspecialchars($m["name"]); ?></span>
                    <span class="when"><?php echo date("M j, Y g:i A", strtotime($m["created_at"])); ?></span>
                </div>
                <div class="text"><?php echo htmlspecialchars($m["message"]); ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
</main>

<footer class="site-footer">
    <div class="container footer-wrap">
        <span>Samuel · Grade 12 · Kings School</span>
        <span>© 2026 Samuel</span>
    </div>
</footer>

</body>
</html>
