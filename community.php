<?php
session_start();
include 'config/db.php';
$active = 'community';
$loggedIn = isset($_SESSION['user_id']);
$myGroupIds = [];

if ($loggedIn) {
    $user_id = (int) $_SESSION['user_id'];
    $mine = $conn->query("SELECT group_id FROM community_group_members WHERE user_id='$user_id'");
    if ($mine) while ($r = $mine->fetch_assoc()) $myGroupIds[] = (int) $r['group_id'];
}

$groups = [];
$gres = $conn->query("SELECT g.*, (SELECT COUNT(*) FROM community_group_members m WHERE m.group_id = g.id) AS member_count
                       FROM community_groups g ORDER BY g.id ASC");
if ($gres) while ($g = $gres->fetch_assoc()) $groups[] = $g;

$isPremium = false;
if ($loggedIn) {
    $pRow = $conn->query("SELECT is_premium FROM users WHERE id='$user_id'")->fetch_assoc();
    $isPremium = !empty($pRow['is_premium']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — Community</title>
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
    <h2>Group chats, by subject — ask, answer, learn together</h2>
    <p class="text-muted mb-3" style="max-width:560px;">Join a room on a subject you're studying, post questions, and help others out. Your progress and reputation carry across the whole StudyFlow community.</p>

    <div class="sf-feature-card mb-4" style="max-width:560px;padding:12px 16px;font-size:0.85rem;">
        🔒 For everyone's safety, sharing phone numbers, Snapchat, or other social handles in group chats isn't allowed — keep the conversation on StudyFlow.
    </div>

    <?php if (!$loggedIn): ?>
        <div class="sf-feature-card" style="max-width:520px;">
            <p class="mb-2">Sign in to join group chats and start learning with other students.</p>
            <a href="register.php" class="btn-sf-gold" style="margin:0;">Start Learning</a>
        </div>
    <?php else: ?>
        <div class="mb-4" style="max-width:520px;">
            <label class="form-label">Search existing groups</label>
            <input type="text" id="groupSearch" class="form-control" placeholder="e.g. Mass Communication, Law, Biology...">
            <p class="mt-2 mb-0 text-muted" style="font-size:0.9rem;">
                Group not available?
                <a href="#" id="createGroupLink" style="color:var(--gold-light);font-weight:600;">Create one now</a>
            </p>
        </div>

        <div class="row g-3" id="groupGrid">
            <?php foreach ($groups as $g): ?>
                <?php
                $isMember = in_array((int)$g['id'], $myGroupIds);
                $isDefaultShown = in_array($g['name'], ['Mathematics', 'Sciences'], true);
                ?>
                <div class="col-md-6 group-card-wrap" data-name="<?php echo htmlspecialchars(strtolower($g['name'].' '.$g['subject'])); ?>" style="<?php echo $isDefaultShown ? '' : 'display:none;'; ?>" data-default-shown="<?php echo $isDefaultShown ? '1' : '0'; ?>">
                    <div class="sf-group-card">
                        <div class="sf-group-icon"><?php echo $g['icon']; ?></div>
                        <div class="sf-group-info">
                            <h6><?php echo htmlspecialchars($g['name']); ?></h6>
                            <p class="text-muted"><?php echo htmlspecialchars($g['description']); ?></p>
                            <small class="text-muted"><?php echo (int)$g['member_count']; ?> member<?php echo $g['member_count'] == 1 ? '' : 's'; ?></small>
                        </div>
                        <?php if ($isMember): ?>
                            <a href="group.php?id=<?php echo $g['id']; ?>" class="btn-sf-gold" style="margin:0;">Open chat</a>
                        <?php else: ?>
                            <button class="btn-sf-outline sf-join-btn" style="border:1px solid var(--border);border-radius:8px;padding:10px 16px;" data-group-id="<?php echo $g['id']; ?>">Join</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p id="groupNoMatch" class="text-muted mt-2" style="display:none;">No matching group yet — try "Create one now" above.</p>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
<script>
const isPremium = <?php echo $isPremium ? 'true' : 'false'; ?>;

document.getElementById('groupSearch')?.addEventListener('input', function(e){
    const q = e.target.value.toLowerCase().trim();
    let anyVisible = false;
    document.querySelectorAll('.group-card-wrap').forEach(c => {
        const match = q === '' ? c.dataset.defaultShown === '1' : c.dataset.name.includes(q);
        c.style.display = match ? '' : 'none';
        if (match) anyVisible = true;
    });
    document.getElementById('groupNoMatch').style.display = (q !== '' && !anyVisible) ? '' : 'none';
});

document.getElementById('createGroupLink')?.addEventListener('click', function(e){
    e.preventDefault();
    if (!isPremium) {
        window.location.href = 'pricing.php';
        return;
    }
    const subject = document.getElementById('groupSearch').value.trim();
    if (!subject) { alert('Type the subject you want a group for first.'); return; }
    this.textContent = 'Creating...';
    const fd = new FormData();
    fd.append('action', 'find_or_create_by_subject');
    fd.append('subject', subject);
    fetch('group_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) window.location.href = 'group.php?id=' + data.groupId;
            else { this.textContent = 'Create one now'; alert(data.text || 'Could not create that group.'); }
        });
});

document.querySelectorAll('.sf-join-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const groupId = this.dataset.groupId;
        this.disabled = true;
        this.innerText = 'Joining...';
        const fd = new FormData();
        fd.append('action', 'join');
        fd.append('groupId', groupId);
        fetch('group_handler.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    window.location.href = 'group.php?id=' + groupId;
                } else {
                    this.disabled = false;
                    this.innerText = 'Join';
                    alert(data.text || 'Could not join right now.');
                }
            });
    });
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
