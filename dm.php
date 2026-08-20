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
$withId = (int) ($_GET['with'] ?? 0);

$rel = sfFriendStatus($conn, $user_id, $withId);
if (!$rel || $rel['status'] !== 'accepted') {
    header("Location: friends.php");
    exit();
}

$stmt = $conn->prepare("SELECT name, username, avatar FROM users WHERE id = ?");
$stmt->bind_param('i', $withId);
$stmt->execute();
$friend = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$friend) {
    header("Location: friends.php");
    exit();
}

$stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$myAvatarRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$myAvatarEmoji = sfAvatarEmoji($myAvatarRow['avatar'] ?? null) ?? '🙂';
$friendAvatarEmoji = sfAvatarEmoji($friend['avatar'] ?? null) ?? '🙂';

$xpRow = $conn->query("SELECT xp FROM users WHERE id='$user_id'")->fetch_assoc();
$canDuel = ((int) ($xpRow['xp'] ?? 0)) >= 5;

$stmt = $conn->prepare("
    SELECT id, sender_id, body, created_at FROM direct_messages
    WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
    ORDER BY id ASC LIMIT 200
");
$stmt->bind_param('iiii', $user_id, $withId, $withId, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$messages = [];
while ($m = $res->fetch_assoc()) $messages[] = $m;
$stmt->close();
$lastId = !empty($messages) ? end($messages)['id'] : 0;

function sfRenderDmAttachment($type, $path, $name) {
    if (!$path) return '';
    if (strpos((string)$type, 'image/') === 0) {
        return '<a href="' . htmlspecialchars($path) . '" target="_blank" rel="noopener"><img src="' . htmlspecialchars($path) . '" style="max-width:220px;border-radius:10px;display:block;margin-bottom:6px;"></a>';
    }
    if (strpos((string)$type, 'audio/') === 0) {
        return '<audio controls src="' . htmlspecialchars($path) . '" style="max-width:220px;display:block;margin-bottom:6px;"></audio>';
    }
    return '<a href="' . htmlspecialchars($path) . '" target="_blank" rel="noopener" style="display:block;margin-bottom:6px;">📎 ' . htmlspecialchars($name ?: 'Attachment') . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — <?php echo htmlspecialchars($friend['name']); ?></title>
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
(function(){ var t = localStorage.getItem('sf-theme') || 'dark'; if (t === 'dark') document.body.classList.add('sf-theme-deep'); })();
</script>
<?php include 'includes/navbar.php'; ?>

<section class="sf-section" style="padding-top:26px;">
    <a href="friends.php" class="text-muted" style="font-size:0.85rem;">&larr; Friends</a>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-1 mb-3">
        <button type="button" id="friendNameToggle" class="mb-0" style="background:none;border:none;padding:0;font:inherit;text-align:left;cursor:pointer;">
            <h2 class="mb-0" style="display:inline;"><?php echo htmlspecialchars($friend['name']); ?></h2>
            <?php if (!empty($friend['username'])): ?> <span class="text-muted" style="font-size:1rem;">@<?php echo htmlspecialchars($friend['username']); ?></span><?php endif; ?>
        </button>
        <?php if ($canDuel): ?>
            <button class="btn-sf-gold" style="margin:0;" onclick="document.getElementById('duelModal').style.display='block'; document.getElementById('duelModal').scrollIntoView({behavior:'smooth'});">⚔️ Duel <?php echo htmlspecialchars($friend['name']); ?></button>
        <?php else: ?>
            <span class="text-muted small" title="Earn at least 5 XP through quizzes to unlock duels">⚔️ Duel (locked — earn 5 XP first)</span>
        <?php endif; ?>
    </div>

    <div id="friendInfoPanel" class="sf-feature-card mb-3" style="display:none;">
        <div class="d-flex align-items-center gap-3">
            <span class="sf-nav-avatar has-emoji" style="width:56px;height:56px;font-size:28px;"><?php echo $friendAvatarEmoji; ?></span>
            <div>
                <h6 class="mb-0"><?php echo htmlspecialchars($friend['name']); ?></h6>
                <?php if (!empty($friend['username'])): ?><p class="text-muted small mb-0">@<?php echo htmlspecialchars($friend['username']); ?></p><?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap mt-3">
            <a href="profile.php?id=<?php echo (int)$withId; ?>" class="sf-pill">Compare</a>
            <button class="sf-pill" onclick="removeFriendFromDm()">Remove friend</button>
            <button class="sf-pill" onclick="blockFriendFromDm()">Block</button>
        </div>
    </div>

    <div id="duelModal" class="sf-feature-card mb-3" style="display:none;max-width:420px;">
        <h6 class="mb-2">Duel <?php echo htmlspecialchars($friend['name']); ?></h6>
        <label class="form-label">Subject</label>
        <input type="text" id="duelSubject" class="form-control mb-2" placeholder="e.g. Mathematics">
        <label class="form-label">Difficulty</label>
        <select id="duelDifficulty" class="form-select mb-2">
            <option value="Easy" selected>Easy</option>
            <option value="Hard">Hard</option>
            <option value="Scholar">Scholar</option>
        </select>
        <label class="form-label">Questions</label>
        <select id="duelCount" class="form-select mb-2">
            <option value="5" selected>5</option>
            <option value="10">10</option>
            <option value="15">15</option>
        </select>
        <label class="form-label">XP stake (each)</label>
        <input type="number" id="duelStake" class="form-control mb-2" value="10" min="5" max="20" step="5">
        <label class="form-label">Time limit</label>
        <select id="duelTimeLimit" class="form-select mb-2">
            <option value="60">1 minute</option>
            <option value="120" selected>2 minutes</option>
            <option value="180">3 minutes</option>
            <option value="300">5 minutes</option>
        </select>
        <small class="text-muted d-block mb-3">Winner gains the stake, loser loses it — XP can go negative.</small>
        <div class="d-flex gap-2">
            <button class="btn-sf-gold" style="margin:0;" onclick="sendDuelFromDm()">Send duel invite</button>
            <button class="sf-pill" onclick="document.getElementById('duelModal').style.display='none'">Cancel</button>
        </div>
    </div>

    <div class="sf-chat-window" id="dmWindow" style="min-height:420px;">
        <?php if (empty($messages)): ?>
            <div class="sf-msg ai">This is the start of your conversation with <?php echo htmlspecialchars($friend['name']); ?>.</div>
        <?php else: ?>
            <?php foreach ($messages as $m): ?>
                <div class="sf-group-msg <?php echo $m['sender_id'] == $user_id ? 'me' : ''; ?>">
                    <span class="sf-msg-avatar sf-dm-avatar"><?php echo $m['sender_id'] == $user_id ? $myAvatarEmoji : $friendAvatarEmoji; ?></span>
                    <?php echo sfRenderDmAttachment($m['attachment_type'] ?? null, $m['attachment_path'] ?? null, $m['attachment_name'] ?? null); ?>
                    <?php if (trim((string)$m['body']) !== ''): ?>
                        <div class="sf-group-msg-body"><?php echo nl2br(htmlspecialchars($m['body'])); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
        <label class="text-muted" style="font-size:0.82rem;">Disappearing messages:</label>
        <select id="disappearMode" class="form-select form-select-sm" style="width:auto;">
            <option value="never" selected>Never</option>
            <option value="after_view">After viewing</option>
            <option value="6h">6 hours</option>
            <option value="12h">12 hours</option>
            <option value="24h">24 hours</option>
            <option value="7d">7 days</option>
        </select>
    </div>

    <form id="dmForm" class="d-flex gap-2 mt-2 align-items-center" enctype="multipart/form-data">
        <input type="file" id="dmFileInput" accept="image/*,application/pdf,.doc,.docx,.txt" style="display:none;">
        <button type="button" id="dmAttachBtn" class="sf-icon-btn" title="Attach a file">📎</button>
        <button type="button" id="dmMicBtn" class="sf-icon-btn" title="Record a voice note">🎙️</button>
        <input type="text" id="dmInput" class="form-control" placeholder="Message <?php echo htmlspecialchars($friend['name']); ?>..." autocomplete="off">
        <button class="btn-sf-gold" style="margin:0;white-space:nowrap;" type="submit">Send</button>
    </form>
    <div id="dmRecordingStatus" class="text-muted small mt-1" style="display:none;">🔴 Recording... tap the mic again to stop and send.</div>
</section>

<?php include 'includes/footer.php'; ?>
<script>
const withId = <?php echo (int) $withId; ?>;
const myUserId = <?php echo (int) $user_id; ?>;
let lastId = <?php echo (int) $lastId; ?>;
const win = document.getElementById('dmWindow');
function escapeHtml(s){ const d=document.createElement('div'); d.innerText=s; return d.innerHTML; }

const myAvatarEmoji = <?php echo json_encode($myAvatarEmoji); ?>;
const friendAvatarEmoji = <?php echo json_encode($friendAvatarEmoji); ?>;

function renderAttachment(type, path, name) {
    if (!path) return '';
    if (type && type.indexOf('image/') === 0) {
        return `<a href="${path}" target="_blank" rel="noopener"><img src="${path}" style="max-width:220px;border-radius:10px;display:block;margin-bottom:6px;"></a>`;
    }
    if (type && type.indexOf('audio/') === 0) {
        return `<audio controls src="${path}" style="max-width:220px;display:block;margin-bottom:6px;"></audio>`;
    }
    return `<a href="${path}" target="_blank" rel="noopener" style="display:block;margin-bottom:6px;">📎 ${escapeHtml(name || 'Attachment')}</a>`;
}

function renderMessage(m) {
    const mine = m.sender_id === myUserId ? 'me' : '';
    const attachmentHtml = renderAttachment(m.attachment_type, m.attachment_path, m.attachment_name);
    const bodyHtml = (m.body && m.body.trim() !== '') ? `<div class="sf-group-msg-body">${escapeHtml(m.body).replace(/\n/g,'<br>')}</div>` : '';
    win.insertAdjacentHTML('beforeend', `<div class="sf-group-msg ${mine}">
        <span class="sf-msg-avatar sf-dm-avatar">${mine ? myAvatarEmoji : friendAvatarEmoji}</span>
        ${attachmentHtml}
        ${bodyHtml}
    </div>`);
    win.scrollTop = win.scrollHeight;
}

function sendPayload(fd) {
    fd.append('action', 'post');
    fd.append('with', withId);
    fd.append('disappear_mode', document.getElementById('disappearMode').value);
    fetch('dm_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) { lastId = data.message.id; renderMessage(data.message); }
            else alert(data.text || 'Could not send — please try again.');
        });
}

document.getElementById('dmForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const input = document.getElementById('dmInput');
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    const fd = new FormData();
    fd.append('body', text);
    sendPayload(fd);
});

// ---- File attach ----
document.getElementById('dmAttachBtn').addEventListener('click', function(){
    document.getElementById('dmFileInput').click();
});
document.getElementById('dmFileInput').addEventListener('change', function(){
    const file = this.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('body', '');
    sendPayload(fd);
    this.value = '';
});

// ---- Voice notes ----
let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;

document.getElementById('dmMicBtn').addEventListener('click', async function(){
    const statusEl = document.getElementById('dmRecordingStatus');
    if (!isRecording) {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
            mediaRecorder.onstop = function(){
                const blob = new Blob(audioChunks, { type: 'audio/webm' });
                const fd = new FormData();
                fd.append('voice', blob, 'voice.webm');
                fd.append('body', '');
                sendPayload(fd);
                stream.getTracks().forEach(t => t.stop());
            };
            mediaRecorder.start();
            isRecording = true;
            statusEl.style.display = 'block';
            this.textContent = '⏹️';
        } catch (err) {
            alert('Could not access your microphone. Check your browser permissions.');
        }
    } else {
        mediaRecorder.stop();
        isRecording = false;
        statusEl.style.display = 'none';
        this.textContent = '🎙️';
    }
});

setInterval(function () {
    fetch(`dm_handler.php?action=fetch&with=${withId}&afterId=${lastId}`)
        .then(r => r.json())
        .then(data => {
            if (data.ok && data.messages.length) {
                data.messages.forEach(m => { lastId = m.id; renderMessage(m); });
            }
        });
}, 4000);

function sendDuelFromDm() {
    const subject = document.getElementById('duelSubject').value.trim();
    if (!subject) { alert('Enter a subject for the duel.'); return; }
    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('friend_id', withId);
    fd.append('subject', subject);
    fd.append('difficulty', document.getElementById('duelDifficulty').value);
    fd.append('count', document.getElementById('duelCount').value);
    fd.append('stake', document.getElementById('duelStake').value);
    fd.append('time_limit', document.getElementById('duelTimeLimit').value);
    fetch('duel_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) { window.location.href = 'duel.php?id=' + d.duelId; }
            else alert(d.text || 'Could not send duel invite.');
        });
}

document.getElementById('friendNameToggle')?.addEventListener('click', function(){
    const panel = document.getElementById('friendInfoPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
});

function removeFriendFromDm() {
    if (!confirm('Remove this friend?')) return;
    const fd = new FormData();
    fd.append('action', 'remove');
    fd.append('target_id', withId);
    fetch('friend_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.ok) window.location.href = 'friends.php'; });
}

function blockFriendFromDm() {
    if (!confirm('Block this person? They will be removed as a friend and won\'t be able to message or duel you.')) return;
    const fd = new FormData();
    fd.append('action', 'block');
    fd.append('target_id', withId);
    fetch('friend_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.ok) window.location.href = 'friends.php'; });
}
</script>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => { navigator.serviceWorker.register('service-worker.js').catch(() => {}); });
}
</script>
</body>
</html>
