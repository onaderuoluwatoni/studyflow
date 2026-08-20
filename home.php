<?php
session_start();
$active = 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow: Study smarter, not harder</title>
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

<!-- HERO -->
<section class="sf-hero">
    <h1>Study smarter, not harder. Everything you need, in one place.</h1>
    <p class="lead">StudyFlow is the all-in-one platform that helps students stay organized, study smarter, and succeed academically.</p>
    <a href="register.php" class="btn-sf-gold">Start Learning</a>
    <a href="login.php" class="btn-sf-ghost">See what's inside</a>
</section>

<!-- FEATURED SUBJECTS -->
<section class="sf-section" id="features">
    <h2>Jump straight into what you're studying</h2>
    <div class="row g-3">
        <?php
        $subjects = [
            ['Software Engineering', '🧩', 'SDLC, UML, testing, design patterns'],
            ['Mathematics', '📐', 'Calculus, algebra, statistics, mechanics'],
            ['Systems Analysis & Design', '🗂️', 'DFDs, ERDs, feasibility studies'],
            ['Computer Science', '💻', 'Data structures, algorithms, databases'],
            ['Physics', '🔬', 'Mechanics, waves, electricity & magnetism'],
            ['Economics', '📊', 'Micro, macro, development economics'],
        ];
        foreach ($subjects as $s) {
            echo '<div class="col-6 col-md-4 col-lg-2"><div class="sf-feature-card">
                    <div class="sf-feature-icon">'.$s[1].'</div>
                    <h6>'.$s[0].'</h6>
                    <small class="text-muted">'.$s[2].'</small>
                  </div></div>';
        }
        ?>
    </div>
</section>

<!-- CORE MODULES -->
<section class="sf-section" style="background:var(--paper-2);">
    <h2>Built for the way students actually revise</h2>
    <div class="row g-3">
        <?php
        $modules = [
            ['📚', 'Study Resources', 'Notes, textbooks, worksheets, video & audio lessons.', 'resources.php'],
            ['🧠', 'AI Study Assistant', 'Ask questions, get topics explained, generate quizzes.', 'ai-tutor.php'],
            ['📝', 'Past Questions', 'Practice with real exam-style questions by subject.', 'past-questions.php'],
            ['⏱️', 'Quiz & Practice Tests', 'Timed quizzes, instant scoring, leaderboards.', 'quiz.php'],
            ['🗂️', 'Flashcards', 'Ready-made decks, spaced repetition, build your own.', 'flashcards.php'],
            ['📅', 'Study Planner', 'Daily schedule, timetable, assignment tracker, exam countdown.', 'planner.php'],
        ];
        foreach ($modules as $m) {
            echo '<div class="col-md-4"><a href="'.$m[3].'" style="color:inherit;"><div class="sf-feature-card">
                    <div class="sf-feature-icon">'.$m[0].'</div>
                    <h5>'.$m[1].'</h5>
                    <p class="text-muted mb-0">'.$m[2].'</p>
                  </div></a></div>';
        }
        ?>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="sf-section">
    <h2>Trusted by students prepping for real exams</h2>
    <div class="sf-marquee">
        <div class="sf-marquee-track">
            <?php
            $testimonials = [
                ['StudyFlow\'s past questions saved me before my exam — I finally understood the material the night before.', 'Amaka Chukwuemeka, 200L Computing Sciences'],
                ['The AI tutor explaining my math step-by-step is like having a TA at 2am.', 'Tunde Adewale, 100L Mechanical Engineering'],
                ['I built my whole semester timetable in ten minutes and actually stuck to it.', 'Ngozi Ibe, 300L Economics'],
                ['Flashcards plus spaced repetition took my recall from panicked to confident.', 'Chinonso Okeke, 200L Biochemistry'],
                ['The quiz leaderboard actually got me competitive about revising — in a good way.', 'Aisha Suleiman, 100L Computer Science'],
                ['Having notes and past questions in one place cut my prep time in half.', 'Emeka Nwosu, SS3, Federal Government College'],
            ];
            // Render the list twice back-to-back for a seamless infinite scroll
            for ($loop = 0; $loop < 2; $loop++) {
                foreach ($testimonials as $t) {
                    echo '<div class="sf-testimonial"><p>"'.htmlspecialchars($t[0]).'"</p><div class="who">'.htmlspecialchars($t[1]).'</div></div>';
                }
            }
            ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="sf-section text-center" style="background:var(--ink); color:#fff;">
    <h2 style="color:#fff;">Ready to stop cramming and start studying smart?</h2>
    <a href="register.php" class="btn-sf-gold">Start Learning — it's free</a>
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
