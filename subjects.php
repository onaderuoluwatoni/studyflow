<?php
session_start();
$active = 'subjects';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — Subjects</title>
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
    <h2>Pick a subject to see notes, past questions, and quizzes</h2>
    <p class="text-muted">Secondary school and university level — Nigerian curricula (WAEC/NECO/JAMB) and international subjects, all in one place.</p>

    <input type="text" id="subjSearch" class="form-control mb-2" style="max-width:420px;" placeholder="Search any subject — e.g. Mass Communication, Nursing, Law...">
    <div id="noMatch" class="sf-feature-card" style="display:none;max-width:420px;">
        <p class="mb-2">Not in the list yet? You can still study it — search works for any subject on Quiz, Past Questions, AI Tutor and Resources.</p>
        <div class="d-flex gap-2 flex-wrap">
            <a id="goQuiz" href="#" class="btn-sf-gold" style="margin:0;">Quiz me on this</a>
            <a id="goPQ" href="#" class="btn-sf-outline" style="border:1px solid var(--border);border-radius:8px;padding:8px 14px;">Find past questions</a>
        </div>
    </div>

    <?php
    // Categorized subject library — secondary + university, Nigerian + international.
    $subjectGroups = [
        'Secondary school (WAEC / NECO / JAMB)' => [
            'Mathematics','English Language','Biology','Chemistry','Physics','Agricultural Science',
            'Geography','Economics','Government','Literature in English','Christian Religious Studies',
            'Islamic Religious Studies','Civic Education','Further Mathematics','Commerce','Financial Accounting',
            'Marketing','Data Processing','Computer Studies','French','Yoruba','Igbo','Hausa','History',
            'Fine Arts','Music','Physical Education','Home Economics','Food and Nutrition','Technical Drawing',
            'Basic Electricity','Woodwork','Metalwork','Visual Art','Health Education',
        ],
        'Computing & Engineering' => [
            'Computer Science','Software Engineering','Systems Analysis & Design','Computing Culture',
            'Information Technology','Cybersecurity','Data Science','Artificial Intelligence','Machine Learning',
            'Electrical Engineering','Electronic Engineering','Mechanical Engineering','Civil Engineering',
            'Chemical Engineering','Petroleum Engineering','Mechatronics Engineering','Computer Engineering',
            'Aerospace Engineering','Biomedical Engineering','Materials Science & Engineering','Robotics',
            'Network Engineering','Telecommunications Engineering','Structural Engineering','Environmental Engineering',
        ],
        'Sciences' => [
            'Mathematics','Statistics','Physics','Chemistry','Biology','Biochemistry','Microbiology',
            'Botany','Zoology','Geology','Marine Biology','Astronomy','Astrophysics','Environmental Science',
            'Applied Physics','Industrial Chemistry','Forensic Science','Actuarial Science','Meteorology',
        ],
        'Health & Medicine' => [
            'Medicine & Surgery (MBBS)','Nursing Science','Pharmacy','Physiotherapy','Dentistry',
            'Medical Laboratory Science','Public Health','Radiography','Anatomy','Physiology',
            'Nutrition & Dietetics','Optometry','Veterinary Medicine','Psychology','Clinical Psychology',
            'Speech Therapy','Occupational Therapy',
        ],
        'Social Sciences & Humanities' => [
            'Mass Communication','Sociology','Political Science','International Relations','Philosophy',
            'Anthropology','Criminology','Social Work','Peace & Conflict Studies','Linguistics',
            'History & International Studies','Religious Studies','Library & Information Science',
            'Archaeology','Gender Studies','Development Studies',
        ],
        'Business & Management' => [
            'Accounting','Banking & Finance','Business Administration','Economics','Marketing',
            'Human Resource Management','Insurance','Entrepreneurship','Public Administration',
            'Taxation','Project Management','Supply Chain & Logistics','Actuarial Science','Office Technology & Management',
        ],
        'Law' => [
            'Law (LLB)','Common Law','International Law','Commercial Law','Criminal Law','Islamic Law (Sharia)',
        ],
        'Arts, Languages & Education' => [
            'English Language','English Literature','French','Spanish','German','Chinese (Mandarin)',
            'Arabic','Yoruba','Igbo','Hausa','Linguistics','Theatre Arts','Music','Fine & Applied Arts',
            'Creative Writing','Education (B.Ed / Education courses)','Educational Psychology','Curriculum Studies',
            'Guidance & Counselling','Early Childhood Education',
        ],
        'Agriculture & Environment' => [
            'Agricultural Science','Agricultural Economics','Crop Science','Animal Science','Forestry & Wildlife',
            'Fisheries & Aquaculture','Soil Science','Environmental Management','Urban & Regional Planning',
            'Architecture','Estate Management','Quantity Surveying','Geography','Geomatics/Surveying',
        ],
    ];

    $icons = ['🧩','📐','🔬','🧬','⚗️','💻','📊','💰','📖','🏛️','📈','🌍','🏥','⚖️','🎨','🌾','🗣️','🛠️'];
    $ic = 0;
    foreach ($subjectGroups as $group => $list) {
        echo '<h6 class="sf-section-eyebrow mt-4 subj-group-heading">' . htmlspecialchars($group) . '</h6>';
        echo '<div class="row g-3 mb-2 subj-group-row">';
        foreach ($list as $s) {
            $icon = $icons[$ic % count($icons)]; $ic++;
            echo '<div class="col-6 col-md-3 subj-card" data-name="'.strtolower($s).'">
                    <a href="past-questions.php?subject='.urlencode($s).'" style="color:inherit;">
                    <div class="sf-feature-card"><div class="sf-feature-icon">'.$icon.'</div><h6 class="mb-0">'.htmlspecialchars($s).'</h6></div>
                    </a></div>';
        }
        echo '</div>';
    }
    ?>
    <p id="subjHint" class="text-muted mt-3">Start typing above to search from every subject we cover.</p>
</section>

<?php include 'includes/footer.php'; ?>
<script>
const searchInput = document.getElementById('subjSearch');
const noMatch = document.getElementById('noMatch');
const subjHint = document.getElementById('subjHint');
const allCards = document.querySelectorAll('.subj-card');
const allHeadings = document.querySelectorAll('.subj-group-heading');
const allRows = document.querySelectorAll('.subj-group-row');

function hideEverything() {
    allCards.forEach(c => c.style.display = 'none');
    allHeadings.forEach(h => h.style.display = 'none');
    allRows.forEach(r => r.style.display = 'none');
    if (noMatch) noMatch.style.display = 'none';
}
hideEverything(); // nothing shown until the person actually searches

searchInput.addEventListener('input', function(e){
    const q = e.target.value.toLowerCase().trim();

    if (q === '') {
        hideEverything();
        if (subjHint) subjHint.style.display = '';
        return;
    }
    if (subjHint) subjHint.style.display = 'none';

    let anyVisible = false;
    allCards.forEach(c => {
        const match = c.dataset.name.includes(q);
        c.style.display = match ? '' : 'none';
        if (match) anyVisible = true;
    });
    allHeadings.forEach(h => {
        let next = h.nextElementSibling, hasVisible = false;
        if (next && next.classList.contains('subj-group-row')) {
            hasVisible = [...next.children].some(c => c.style.display !== 'none');
        }
        h.style.display = hasVisible ? '' : 'none';
        if (next) next.style.display = hasVisible ? '' : 'none';
    });
    if (q !== '' && !anyVisible) {
        noMatch.style.display = 'block';
        document.getElementById('goQuiz').href = 'quiz.php?subject=' + encodeURIComponent(e.target.value.trim());
        document.getElementById('goPQ').href = 'past-questions.php?subject=' + encodeURIComponent(e.target.value.trim());
    } else {
        noMatch.style.display = 'none';
    }
});
</script>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js').catch(() => {});
    });
}
</script>
</body>
</html>
