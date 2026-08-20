<?php
session_start();
$active = 'pricing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — Pricing</title>
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
    <h2>Everything you need to study is already free.</h2>
    <p class="text-muted mb-4" style="max-width:560px;">Planner, past questions, flashcards, quizzes, AI Tutor, community groups, streaks and XP — all free, always. Premium adds a few extra perks for the people who want more.</p>

    <div class="d-flex justify-content-center">
        <div class="sf-premium-float">
            <div class="sf-premium-glow"></div>
            <div class="sf-premium-badge">✨ PREMIUM</div>
            <div class="sf-premium-price">₦850<span>/mo</span></div>
            <p class="sf-premium-tagline">Faster AI, more tools, extra perks</p>
            <ul class="sf-premium-list">
                <li>⚡ Faster AI responses (priority queue)</li>
                <li>👑 Premium badge beside your name in Community</li>
                <li>👥 Create your own Community group</li>
                <li>📄 PDF summarizer & handwritten notes scanner</li>
                <li>📴 Offline mode (PWA)</li>
                <li>⬇️ Note & resource downloads</li>
                <li>📝 CV generation</li>
                <li>💼 Vacancy & internship opportunities</li>
                <li>🏢 IT placement listings</li>
                <li>🎓 University course guide — JAMB scores & likely universities</li>
            </ul>
            <a href="register.php" class="btn-sf-gold d-block mt-3">Go Premium</a>
        </div>
    </div>
</section>

<style>
.sf-premium-float {
    position: relative;
    max-width: 460px;
    width: 100%;
    padding: 40px 34px 34px;
    border-radius: 24px;
    background: linear-gradient(160deg, #1a2a1f 0%, #0b1526 55%, #142338 100%);
    border: 1px solid rgba(233,201,106,0.35);
    box-shadow: 0 30px 70px rgba(0,0,0,0.45), 0 0 60px rgba(233,201,106,0.12);
    animation: sfFloat 5s ease-in-out infinite;
    overflow: hidden;
}
@keyframes sfFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.sf-premium-glow {
    position: absolute; top: -30%; left: 50%; transform: translateX(-50%);
    width: 320px; height: 320px; border-radius: 50%;
    background: radial-gradient(circle, rgba(233,201,106,0.35) 0%, rgba(233,201,106,0) 70%);
    pointer-events: none;
}
.sf-premium-badge {
    position: relative; display: inline-block;
    background: linear-gradient(135deg, var(--gold-light), var(--gold));
    color: var(--ink); font-weight: 700; font-size: 0.78rem; letter-spacing: 0.08em;
    padding: 6px 14px; border-radius: 999px; margin-bottom: 16px;
}
.sf-premium-price { position: relative; font-family: var(--font-mono); font-size: 2.6rem; font-weight: 700; color: #fff; }
.sf-premium-price span { font-size: 1.1rem; color: rgba(255,255,255,0.55); }
.sf-premium-tagline { position: relative; color: rgba(255,255,255,0.6); margin-bottom: 20px; }
.sf-premium-list { position: relative; list-style: none; padding: 0; margin: 0; }
.sf-premium-list li {
    padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.88); font-size: 0.95rem;
}
.sf-premium-list li:last-child { border-bottom: none; }
</style>


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
