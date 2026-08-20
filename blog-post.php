<?php
session_start();
$active = 'blog';
$posts = include 'includes/blog_posts.php';
$slug = $_GET['slug'] ?? '';
$post = $posts[$slug] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $post ? htmlspecialchars($post['title']) . ' — StudyFlow Blog' : 'Post not found — StudyFlow'; ?></title>
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

<section class="sf-section" style="max-width:760px;">
    <?php if ($post): ?>
        <h2><?php echo htmlspecialchars($post['title']); ?></h2>
        <?php if (!empty($post['image'])): ?>
            <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="" style="width:100%;max-height:360px;object-fit:cover;border-radius:var(--radius);margin:16px 0;">
        <?php endif; ?>
        <div style="font-size:1.05rem; line-height:1.8; white-space:pre-line;">
            <?php echo htmlspecialchars($post['body']); ?>
        </div>
        <?php if (!empty($post['video_query'])): ?>
            <div class="sf-feature-card mt-4" style="max-width:480px;">
                <h6 class="mb-2">🎥 Watch more on this</h6>
                <a href="https://www.youtube.com/results?search_query=<?php echo urlencode($post['video_query'].' english'); ?>" target="_blank" rel="noopener" class="btn-sf-outline d-inline-block" style="border:1px solid var(--border);border-radius:8px;padding:10px 16px;">Find free videos on YouTube ↗</a>
            </div>
        <?php endif; ?>
        <a href="blog.php" class="btn-sf-outline mt-4 d-inline-block" style="border:1px solid var(--border);border-radius:8px;padding:10px 18px;">&larr; Back to blog</a>
    <?php else: ?>
        <h2>Post not found</h2>
        <p class="text-muted">That article doesn't exist.</p>
        <a href="blog.php" class="btn-sf-gold">Back to blog</a>
    <?php endif; ?>
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
