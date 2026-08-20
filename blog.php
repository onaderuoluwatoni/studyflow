<?php
session_start();
$active = 'blog';
$posts = include 'includes/blog_posts.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — Blog</title>
<link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css?v=2" rel="stylesheet">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0b1526">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudyFlow">
<link rel="apple-touch-icon" href="assets/img/icons/apple-touch-icon.png">
</head>
<body>
<script>
(function(){
  var t = localStorage.getItem('sf-theme') || 'dark';
  if (t === 'dark') document.body.classList.add('sf-theme-deep');
})();
</script>
<?php include 'includes/navbar.php'; ?>

<section class="sf-section">
    <h2>Study tips, exam strategy, and student life</h2>
    <div class="row g-3">
        <?php
        $accents = ['gold', 'teal', 'terracotta'];
        $icons = ['🧠', '🗓️', '☕', '🎯', '📝', '💡'];
        $i = 0;
        foreach ($posts as $slug => $p):
            $accent = $accents[$i % count($accents)];
            $icon = $icons[$i % count($icons)];
            $wordCount = str_word_count($p['body'] ?? $p['excerpt']);
            $readMins = max(1, round($wordCount / 200));
            $i++;
        ?>
            <div class="col-md-4">
                <div class="sf-blog-card <?php echo $accent; ?>" style="padding-top:0;">
                    <?php if (!empty($p['image'])): ?>
                        <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="" style="width:calc(100% + 2px);margin:-1px -1px 14px -1px;border-radius:var(--radius) var(--radius) 0 0;height:150px;object-fit:cover;">
                    <?php endif; ?>
                    <div class="sf-blog-card-icon"><?php echo $icon; ?></div>
                    <h6><?php echo htmlspecialchars($p['title']); ?></h6>
                    <p class="text-muted mb-3 flex-grow-1"><?php echo htmlspecialchars($p['excerpt']); ?></p>
                    <div class="d-flex align-items-center justify-content-between">
                        <small class="text-muted"><?php echo $readMins; ?> min read</small>
                        <a href="blog-post.php?slug=<?php echo urlencode($slug); ?>" class="sf-blog-readmore">Read more &rarr;</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js').catch(() => {});
    });
}
</script>
</body>
</html>
