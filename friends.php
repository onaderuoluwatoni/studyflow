<?php
session_start();
include 'config/db.php';
include 'includes/friends.php';
include 'includes/avatars.php';
$active = 'friends';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$friends = sfGetFriends($conn, $user_id);
$pending = sfGetPendingRequests($conn, $user_id);

$stmt = $conn->prepare("
    SELECT f.id, u.id AS uid, u.name
    FROM friendships f JOIN users u ON u.id = f.addressee_id
    WHERE f.requester_id = ? AND f.status = 'blocked'
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$blocked = [];
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $blocked[] = $r;
$stmt->close();

$myXp = 0;
$xpStmt = $conn->prepare("SELECT xp FROM users WHERE id = ?");
$xpStmt->bind_param('i', $user_id);
$xpStmt->execute();
$myXp = (int) ($xpStmt->get_result()->fetch_assoc()['xp'] ?? 0);
$xpStmt->close();
$canDuel = $myXp >= 10;

// Pending duel invites sent TO me
$stmt = $conn->prepare("
    SELECT fd.*, uc.name AS challenger_name
    FROM friend_duels fd JOIN users uc ON uc.id = fd.challenger_id
    WHERE fd.opponent_id = ? AND fd.status = 'pending'
    ORDER BY fd.created_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$duelInvites = [];
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $duelInvites[] = $r;
$stmt->close();

// My active duels (accepted, still being played) so I can jump back in
$stmt = $conn->prepare("
    SELECT fd.*, uc.name AS challenger_name, uo.name AS opponent_name
    FROM friend_duels fd
    JOIN users uc ON uc.id = fd.challenger_id
    JOIN users uo ON uo.id = fd.opponent_id
    WHERE (fd.challenger_id = ? OR fd.opponent_id = ?) AND fd.status = 'active'
    ORDER BY fd.accepted_at DESC
");
$stmt->bind_param('ii', $user_id, $user_id);
$stmt->execute();
$activeDuels = [];
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $activeDuels[] = $r;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — Friends</title>
<link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css?v=2" rel="stylesheet">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0b1526">
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
    <div class="d-flex align-items-center justify-content-between mb-3">
        <button type="button" id="openAddFriend" class="sf-add-friend-btn" title="Add friend" aria-label="Add friend">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="8" r="4"/>
                <path d="M2 21c0-4 3-7 7-7s7 3 7 7"/>
                <path d="M19 8v6M16 11h6"/>
            </svg>
        </button>
    </div>

    <input type="text" id="friendListSearch" class="form-control mb-3" placeholder="Search your friends...">

    <!-- ADD FRIEND PANEL (hidden until the + icon is tapped) -->
    <div id="addFriendPanel" class="sf-feature-card mb-4" style="display:none;max-width:520px;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Add a friend</h6>
            <button type="button" class="text-muted" style="background:none;border:none;font-size:1.2rem;" onclick="document.getElementById('addFriendPanel').style.display='none';">&times;</button>
        </div>
        <input type="text" id="friendSearch" class="form-control" placeholder="Search by username...">
        <div id="searchResults" class="mt-2"></div>
    </div>

    <!-- DUEL INVITES -->
    <?php if (!empty($duelInvites)): ?>
    <h6 class="sf-section-eyebrow mb-2">⚔️ Duel invites</h6>
    <div class="mb-3">
        <?php foreach ($duelInvites as $d): ?>
            <div class="sf-chatlist-row" style="cursor:default;">
                <div class="sf-chatlist-info">
                    <h6 class="mb-0"><?php echo htmlspecialchars($d['challenger_name']); ?> challenged you</h6>
                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($d['subject']); ?> · <?php echo (int)$d['stake']; ?> XP stake</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn-sf-gold" style="margin:0;padding:6px 14px;font-size:0.85rem;" onclick="acceptDuel(<?php echo (int)$d['id']; ?>, this)">Accept</button>
                    <button class="sf-pill" style="padding:6px 14px;font-size:0.85rem;" onclick="declineDuel(<?php echo (int)$d['id']; ?>, this)">Decline</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ACTIVE DUELS -->
    <?php if (!empty($activeDuels)): ?>
    <h6 class="sf-section-eyebrow mb-2">⚔️ Duels in progress</h6>
    <div class="mb-3">
        <?php foreach ($activeDuels as $d):
            $otherName = ($d['challenger_id'] == $user_id) ? $d['opponent_name'] : $d['challenger_name'];
        ?>
            <a href="duel.php?id=<?php echo (int)$d['id']; ?>" class="sf-chatlist-row">
                <div class="sf-chatlist-info">
                    <h6 class="mb-0">vs <?php echo htmlspecialchars($otherName); ?></h6>
                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($d['subject']); ?> · <?php echo (int)$d['stake']; ?> XP stake · tap to continue</p>
                </div>
                <span class="sf-chatlist-chevron">&rsaquo;</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- PENDING REQUESTS -->
    <?php if (!empty($pending)): ?>
    <h6 class="sf-section-eyebrow mb-2">Friend requests</h6>
    <div class="mb-3">
        <?php foreach ($pending as $p): ?>
            <div class="sf-chatlist-row" style="cursor:default;">
                <div class="sf-chatlist-info">
                    <h6 class="mb-0"><?php echo htmlspecialchars($p['name']); ?></h6>
                    <p class="text-muted small mb-0">wants to be friends</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn-sf-gold" style="margin:0;padding:6px 14px;font-size:0.85rem;" onclick="respondRequest(<?php echo (int)$p['friendship_id']; ?>,'accept',this)">Accept</button>
                    <button class="sf-pill" style="padding:6px 14px;font-size:0.85rem;" onclick="respondRequest(<?php echo (int)$p['friendship_id']; ?>,'decline',this)">Decline</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- MY FRIENDS -->
    <?php if (empty($friends)): ?>
        <p class="text-muted">No friends yet — tap the + icon above to add one.</p>
    <?php else: ?>
    <div id="friendList">
        <?php foreach ($friends as $f): ?>
            <?php $fAvatarEmoji = sfAvatarEmoji($f['avatar'] ?? null) ?? '🙂'; ?>
            <a href="dm.php?with=<?php echo (int)$f['id']; ?>" class="sf-chatlist-row" data-name="<?php echo htmlspecialchars(strtolower($f['name'])); ?>">
                <span class="sf-nav-avatar has-emoji" style="width:48px;height:48px;font-size:24px;flex-shrink:0;"><?php echo $fAvatarEmoji; ?></span>
                <div class="sf-chatlist-info">
                    <h6 class="mb-0"><?php echo htmlspecialchars($f['name']); ?></h6>
                    <p class="text-muted small mb-0">Tap to chat</p>
                </div>
                <span class="sf-chatlist-chevron">&rsaquo;</span>
            </a>
        <?php endforeach; ?>
    </div>
    <p id="friendListNoMatch" class="text-muted mt-2" style="display:none;">No friends match that search.</p>
    <?php endif; ?>

    <!-- BLOCKED -->
    <?php if (!empty($blocked)): ?>
    <h6 class="sf-section-eyebrow mb-2 mt-4">Blocked</h6>
    <div class="row g-3">
        <?php foreach ($blocked as $b): ?>
            <div class="col-md-4">
                <div class="sf-feature-card d-flex justify-content-between align-items-center flex-row">
                    <span><?php echo htmlspecialchars($b['name']); ?></span>
                    <button class="sf-pill" onclick="unblockUser(<?php echo (int)$b['uid']; ?>, this)">Unblock</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
<script>

document.getElementById('friendSearch').addEventListener('input', function(e){
    const q = e.target.value.trim();
    const box = document.getElementById('searchResults');
    if (q.length < 2) { box.innerHTML = ''; return; }
    const fd = new FormData();
    fd.append('action', 'search');
    fd.append('q', q);
    fetch('friend_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.ok || d.results.length === 0) { box.innerHTML = '<p class="text-muted small mt-2">No matches.</p>'; return; }
            box.innerHTML = d.results.map(u => {
                let action = '';
                if (u.status === 'none') action = `<button class="sf-pill" onclick="sendRequest(${u.id}, this)">Add friend</button>`;
                else if (u.status === 'pending') action = `<span class="text-muted small">Pending</span>`;
                else if (u.status === 'accepted') action = `<span class="text-muted small">Already friends</span>`;
                else if (u.status === 'blocked') action = `<span class="text-muted small">Blocked</span>`;
                return `<div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--border);">
                    <span>@${u.username || 'unknown'} <span class="text-muted small">(${u.name})</span></span>${action}</div>`;
            }).join('');
        });
});

function sendRequest(id, btn) {
    const fd = new FormData();
    fd.append('action', 'request');
    fd.append('target_id', id);
    fetch('friend_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.ok) { btn.outerHTML = '<span class="text-muted small">Pending</span>'; } else alert(d.text); });
}

function respondRequest(friendshipId, action, btn) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('friendship_id', friendshipId);
    fetch('friend_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.ok) location.reload(); });
}

function removeFriend(id, btn) {
    if (!confirm('Remove this friend?')) return;
    const fd = new FormData();
    fd.append('action', 'remove');
    fd.append('target_id', id);
    fetch('friend_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.ok) location.reload(); });
}

function blockUser(id, btn) {
    if (!confirm('Block this user?')) return;
    const fd = new FormData();
    fd.append('action', 'block');
    fd.append('target_id', id);
    fetch('friend_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.ok) location.reload(); });
}

function unblockUser(id, btn) {
    const fd = new FormData();
    fd.append('action', 'unblock');
    fd.append('target_id', id);
    fetch('friend_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.ok) location.reload(); });
}

// ---- Add friend panel toggle ----
document.getElementById('openAddFriend').addEventListener('click', function(){
    const panel = document.getElementById('addFriendPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    if (panel.style.display === 'block') document.getElementById('friendSearch').focus();
});

// ---- Search my existing friends list ----
document.getElementById('friendListSearch')?.addEventListener('input', function(e){
    const q = e.target.value.toLowerCase().trim();
    let anyVisible = false;
    document.querySelectorAll('#friendList .sf-chatlist-row').forEach(row => {
        const match = row.dataset.name.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) anyVisible = true;
    });
    const noMatch = document.getElementById('friendListNoMatch');
    if (noMatch) noMatch.style.display = (q !== '' && !anyVisible) ? '' : 'none';
});

// ---- Quiz Duels (invite response only — sending a duel now happens from the chat page) ----
function acceptDuel(duelId, btn) {
    btn.disabled = true;
    btn.innerText = 'Starting...';
    const fd = new FormData();
    fd.append('action', 'accept');
    fd.append('duel_id', duelId);
    fetch('duel_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) window.location.href = 'duel.php?id=' + duelId;
            else { btn.disabled = false; btn.innerText = 'Accept'; alert(d.text || 'Could not accept duel.'); }
        });
}

function declineDuel(duelId, btn) {
    const fd = new FormData();
    fd.append('action', 'decline');
    fd.append('duel_id', duelId);
    fetch('duel_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.ok) location.reload(); });
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
