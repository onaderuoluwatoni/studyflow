<?php
session_start();
include 'config/db.php';
$active = 'resources';

$loggedIn = isset($_SESSION['user_id']);
$mySubjects = [];

if ($loggedIn) {
    $user_id = (int) $_SESSION['user_id'];
    $res = $conn->query("SELECT subject_name FROM subjects WHERE user_id='$user_id' ORDER BY id DESC");
    while ($row = $res->fetch_assoc()) {
        $mySubjects[] = $row['subject_name'];
    }
}

$categories = [
    'flashcards' => ['label' => '🗂️ Flashcards', 'suffix' => ''],
    'notes' => ['label' => '📝 Notes', 'suffix' => 'Core Notes'],
    'textbooks' => ['label' => '📚 Textbooks', 'suffix' => 'Recommended Textbook Guide'],
    'worksheets' => ['label' => '🧾 Worksheets', 'suffix' => 'Practice Worksheet'],
    'video' => ['label' => '🎥 Video lessons', 'suffix' => 'Video Walkthrough'],
    'audio' => ['label' => '🎧 Audio lessons', 'suffix' => 'Audio Revision'],
];

if ($loggedIn && isset($_GET['delete_card'])) {
    $delId = (int) $_GET['delete_card'];
    $stmt = $conn->prepare("DELETE FROM flashcards WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $delId, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: resources.php#flashcards");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — Resources</title>
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
    <h2>Notes, textbooks, worksheets, video & audio — for your subjects</h2>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="past-questions.php" class="sf-feature-card d-block text-decoration-none" style="text-align:center;">
                <div style="font-size:1.6rem;">📝</div>
                <div class="mt-1" style="font-weight:600;color:var(--ink);">Past Questions</div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="planner.php" class="sf-feature-card d-block text-decoration-none" style="text-align:center;">
                <div style="font-size:1.6rem;">📅</div>
                <div class="mt-1" style="font-weight:600;color:var(--ink);">Study Planner</div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="pomodoro.php" class="sf-feature-card d-block text-decoration-none" style="text-align:center;">
                <div style="font-size:1.6rem;">⏱️</div>
                <div class="mt-1" style="font-weight:600;color:var(--ink);">Focus Timer</div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="blog.php" class="sf-feature-card d-block text-decoration-none" style="text-align:center;">
                <div style="font-size:1.6rem;">✍️</div>
                <div class="mt-1" style="font-weight:600;color:var(--ink);">Blog</div>
            </a>
        </div>
    </div>

    <?php if (!$loggedIn): ?>
        <div class="sf-feature-card" style="max-width:560px;">
            <p class="mb-2">Sign in and add your subjects to build your own resource shelf.</p>
            <a href="register.php" class="btn-sf-gold" style="margin:0;">Start Learning</a>
        </div>
    <?php elseif (empty($mySubjects)): ?>
        <div class="sf-feature-card mb-3" style="max-width:560px;">
            <p class="mb-2">Add subjects to see notes, textbooks, worksheets, and video/audio lessons matched to them — but flashcards work right away, no subject needed.</p>
            <a href="subjects/add_subject.php" class="btn-sf-gold" style="margin:0;">Add a subject</a>
        </div>

        <span class="sf-pill active" style="pointer-events:none;">🗂️ Flashcards</span>
        <div class="mt-3">
            <?php
            function sfFormatCardBack($back) {
                $escaped = htmlspecialchars($back);
                $escaped = preg_replace('/^Key points:$/m', '<strong>Key points:</strong>', $escaped);
                return nl2br($escaped);
            }
            $stmt = $conn->prepare("SELECT * FROM flashcards WHERE user_id = ? ORDER BY id DESC");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $cards = $stmt->get_result();
            ?>
            <div class="row g-3 mb-4" id="cardGrid">
                <?php if ($cards->num_rows > 0): while ($c = $cards->fetch_assoc()): ?>
                    <div class="col-md-4" data-card-id="<?php echo $c['id']; ?>">
                        <div class="sf-flip-card" onclick="this.classList.toggle('flipped')">
                            <div class="sf-flip-inner">
                                <div class="sf-flip-front"><?php echo htmlspecialchars($c['front']); ?></div>
                                <div class="sf-flip-back"><?php echo sfFormatCardBack($c['back']); ?></div>
                            </div>
                        </div>
                        <div class="text-end mt-1">
                            <a href="resources.php?delete_card=<?php echo $c['id']; ?>" class="text-muted" style="font-size:0.8rem;" onclick="event.stopPropagation();">Delete</a>
                        </div>
                    </div>
                <?php endwhile; else: ?>
                    <p class="text-muted" id="emptyCardsMsg">No flashcards yet — create your first one below.</p>
                <?php endif; $stmt->close(); ?>
            </div>
            <div class="sf-feature-card" style="max-width:520px;">
                <h6>Add anything you want to remember</h6>
                <p class="text-muted mb-2" style="font-size:0.88rem;">Type a word, term, or concept — StudyFlow's AI figures out the easiest way to remember it and builds the back of the card for you.</p>
                <form id="addCardForm">
                    <input type="text" name="front" id="cardFrontInput" class="form-control mb-2" placeholder="e.g. Photosynthesis, Mitosis, Opportunity cost..." required>
                    <button class="btn-sf-gold" style="margin:0;" type="submit" id="addCardBtn">Generate flashcard</button>
                </form>
                <small class="text-danger d-block mt-2" id="cardErrorNote" style="display:none;"></small>
            </div>
        </div>
    <?php else: ?>

        <div class="mb-4">
            <?php $first = true; foreach ($categories as $key => $cat): ?>
                <span class="sf-pill <?php echo $first ? 'active' : ''; ?>" data-tab="<?php echo $key; ?>"><?php echo $cat['label']; ?></span>
            <?php $first = false; endforeach; ?>
        </div>

        <?php $first = true; foreach ($categories as $key => $cat): ?>
            <div class="res-tab" data-tab="<?php echo $key; ?>" style="<?php echo $first ? '' : 'display:none;'; ?>">
                <?php if ($key === 'flashcards'): ?>
                    <?php
                    function sfFormatCardBack($back) {
                        $escaped = htmlspecialchars($back);
                        $escaped = preg_replace('/^Key points:$/m', '<strong>Key points:</strong>', $escaped);
                        return nl2br($escaped);
                    }
                    $stmt = $conn->prepare("SELECT * FROM flashcards WHERE user_id = ? ORDER BY id DESC");
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $cards = $stmt->get_result();
                    ?>
                    <div class="row g-3 mb-4" id="cardGrid">
                        <?php if ($cards->num_rows > 0): while ($c = $cards->fetch_assoc()): ?>
                            <div class="col-md-4" data-card-id="<?php echo $c['id']; ?>">
                                <div class="sf-flip-card" onclick="this.classList.toggle('flipped')">
                                    <div class="sf-flip-inner">
                                        <div class="sf-flip-front"><?php echo htmlspecialchars($c['front']); ?></div>
                                        <div class="sf-flip-back"><?php echo sfFormatCardBack($c['back']); ?></div>
                                    </div>
                                </div>
                                <div class="text-end mt-1">
                                    <a href="resources.php?delete_card=<?php echo $c['id']; ?>" class="text-muted" style="font-size:0.8rem;" onclick="event.stopPropagation();">Delete</a>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <p class="text-muted" id="emptyCardsMsg">No flashcards yet — create your first one below.</p>
                        <?php endif; $stmt->close(); ?>
                    </div>

                    <div class="sf-feature-card" style="max-width:520px;">
                        <h6>Add anything you want to remember</h6>
                        <p class="text-muted mb-2" style="font-size:0.88rem;">Type a word, term, or concept — StudyFlow's AI figures out the easiest way to remember it and builds the back of the card for you.</p>
                        <form id="addCardForm">
                            <input type="text" name="front" id="cardFrontInput" class="form-control mb-2" placeholder="e.g. Photosynthesis, Mitosis, Opportunity cost..." required>
                            <button class="btn-sf-gold" style="margin:0;" type="submit" id="addCardBtn">Generate flashcard</button>
                        </form>
                        <small class="text-danger d-block mt-2" id="cardErrorNote" style="display:none;"></small>
                    </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($mySubjects as $subj): ?>
                        <div class="col-md-4">
                            <div class="sf-feature-card">
                                <h6 class="mb-1"><?php echo htmlspecialchars($subj); ?> — <?php echo $cat['suffix']; ?></h6>
                                <small class="text-muted">From your registered subjects</small><br>
                                <button type="button" class="btn-sf-outline mt-2" style="padding:6px 14px;border:1px solid var(--border);border-radius:8px;"
                                    onclick="openResource('<?php echo htmlspecialchars(addslashes($subj)); ?>','<?php echo htmlspecialchars(addslashes($cat['suffix'])); ?>','<?php echo htmlspecialchars(addslashes($key)); ?>')">Open</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php $first = false; endforeach; ?>

        <!-- Simple viewer modal -->
        <div class="modal fade" id="resourceModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="resourceModalTitle">Resource</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="resourceModalBody">Loading...</div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.sf-pill').forEach(p => {
    p.addEventListener('click', () => {
        document.querySelectorAll('.sf-pill').forEach(x => x.classList.remove('active'));
        p.classList.add('active');
        document.querySelectorAll('.res-tab').forEach(t => t.style.display = (t.dataset.tab === p.dataset.tab) ? '' : 'none');
    });
});
// Jump straight to the Flashcards tab if linked with #flashcards
if (window.location.hash === '#flashcards') {
    const flashPill = document.querySelector('.sf-pill[data-tab="flashcards"]');
    if (flashPill) flashPill.click();
}

// ---- Flashcards (moved here from the old standalone flashcards.php) ----
function sfFormatBackHtml(back) {
    const escaped = back.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const bolded = escaped.replace(/^Key points:$/m, '<strong>Key points:</strong>');
    return bolded.replace(/\n/g, '<br>');
}
const addCardForm = document.getElementById('addCardForm');
if (addCardForm) {
    addCardForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const input = document.getElementById('cardFrontInput');
        const btn = document.getElementById('addCardBtn');
        const errNote = document.getElementById('cardErrorNote');
        const term = input.value.trim();
        if (!term) return;

        errNote.style.display = 'none';
        btn.disabled = true;
        btn.innerText = 'Generating meaning...';

        const fd = new FormData();
        fd.append('front', term);

        fetch('flashcard_handler.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerText = 'Generate flashcard';

                if (!data.ok) {
                    errNote.innerText = data.text || 'Something went wrong — please try again.';
                    errNote.style.display = 'block';
                    return;
                }

                const emptyMsg = document.getElementById('emptyCardsMsg');
                if (emptyMsg) emptyMsg.remove();

                const grid = document.getElementById('cardGrid');
                const col = document.createElement('div');
                col.className = 'col-md-4';
                col.dataset.cardId = data.id;
                col.innerHTML = `
                    <div class="sf-flip-card" onclick="this.classList.toggle('flipped')">
                        <div class="sf-flip-inner">
                            <div class="sf-flip-front"></div>
                            <div class="sf-flip-back"></div>
                        </div>
                    </div>
                    <div class="text-end mt-1">
                        <a href="resources.php?delete_card=${data.id}" class="text-muted" style="font-size:0.8rem;" onclick="event.stopPropagation();">Delete</a>
                    </div>`;
                col.querySelector('.sf-flip-front').innerText = data.front;
                col.querySelector('.sf-flip-back').innerHTML = sfFormatBackHtml(data.back);
                grid.prepend(col);

                if (!data.aiOk) {
                    errNote.innerText = 'Card saved, but the AI Tutor may not be connected yet — you can delete and retry once it is.';
                    errNote.style.display = 'block';
                }

                input.value = '';
                input.focus();
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerText = 'Generate flashcard';
                errNote.innerText = 'Could not reach the server — please try again.';
                errNote.style.display = 'block';
            });
    });
}

// Free, publicly available sources used to build real links for each
// resource category — no material is hosted or copied, StudyFlow just
// points students to the right search on a trusted free platform.
const sourcesByCategory = {
    notes: [
        { name: 'Khan Academy', url: q => `https://www.khanacademy.org/search?page_search_query=${q}` },
        { name: 'LibreTexts', url: q => `https://query.libretexts.org/Special:Search?qid=&fpid=230&q=${q}` },
        { name: 'SparkNotes', url: q => `https://www.sparknotes.com/search?q=${q}` },
        { name: 'BBC Bitesize', url: q => `https://www.bbc.co.uk/bitesize/search?q=${q}` },
    ],
    textbooks: [
        { name: 'OpenStax', url: q => `https://openstax.org/search?q=${q}` },
        { name: 'Open Textbook Library', url: q => `https://open.umn.edu/opentextbooks/textbooks?term=${q}` },
        { name: 'MIT OpenCourseWare', url: q => `https://ocw.mit.edu/search/?q=${q}` },
        { name: 'Internet Archive (Open Library)', url: q => `https://openlibrary.org/search?q=${q}` },
    ],
    worksheets: [
        { name: 'Khan Academy practice', url: q => `https://www.khanacademy.org/search?page_search_query=${q}` },
        { name: 'CK-12', url: q => `https://www.ck12.org/search/?q=${q}` },
        { name: 'Corbettmaths', url: q => `https://corbettmaths.com/?s=${q}` },
        { name: 'Physics & Maths Tutor', url: q => `https://www.physicsandmathstutor.com/?s=${q}` },
    ],
    video: [
        { name: 'YouTube', url: q => `https://www.youtube.com/results?search_query=${q}+full+lesson+english` },
        { name: 'Khan Academy videos', url: q => `https://www.khanacademy.org/search?page_search_query=${q}` },
        { name: 'freeCodeCamp (YouTube)', url: q => `https://www.youtube.com/results?search_query=freecodecamp+${q}` },
        { name: 'Crash Course (YouTube)', url: q => `https://www.youtube.com/results?search_query=crash+course+${q}` },
    ],
    audio: [
        { name: 'YouTube (audio lessons)', url: q => `https://www.youtube.com/results?search_query=${q}+audio+lesson+english` },
        { name: 'LibriVox (free audiobooks)', url: q => `https://librivox.org/search?q=${q}` },
        { name: 'Internet Archive audio', url: q => `https://archive.org/search?query=${q}+lecture` },
    ],
};

function openResource(subject, type, category) {
    document.getElementById('resourceModalTitle').innerText = `${subject} — ${type}`;
    const q = encodeURIComponent(subject);
    const sources = sourcesByCategory[category] || sourcesByCategory.notes;
    const links = sources.map(s => `<a href="${s.url(q)}" target="_blank" rel="noopener" class="btn-sf-outline d-block mb-2" style="border:1px solid var(--border);border-radius:8px;padding:10px 14px;">Open on ${s.name} ↗</a>`).join('');
    document.getElementById('resourceModalBody').innerHTML =
        `<p class="text-muted mb-3">Free, publicly available ${type.toLowerCase()} for ${subject} — sourced live from these platforms:</p>${links}`;
    new bootstrap.Modal(document.getElementById('resourceModal')).show();
}
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
