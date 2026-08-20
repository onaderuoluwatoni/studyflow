<?php
session_start();
include 'config/db.php';
include 'includes/avatars.php';
$active = 'community';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$groupId = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM community_groups WHERE id = ?");
$stmt->bind_param('i', $groupId);
$stmt->execute();
$gres = $stmt->get_result();
$stmt->close();
if (!$gres || $gres->num_rows === 0) {
    header("Location: community.php");
    exit();
}
$group = $gres->fetch_assoc();

$stmt = $conn->prepare("SELECT 1 FROM community_group_members WHERE group_id = ? AND user_id = ?");
$stmt->bind_param('ii', $groupId, $user_id);
$stmt->execute();
$isMember = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$isMember) {
    $stmt = $conn->prepare("INSERT IGNORE INTO community_group_members (group_id, user_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $groupId, $user_id);
    $stmt->execute();
    $stmt->close();
}

// New members only see messages from the moment they joined onward —
// like WhatsApp, not the group's full history before they arrived.
$stmt = $conn->prepare("SELECT joined_at FROM community_group_members WHERE group_id = ? AND user_id = ?");
$stmt->bind_param('ii', $groupId, $user_id);
$stmt->execute();
$joinRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$myJoinedAt = $joinRow['joined_at'] ?? date('Y-m-d H:i:s');

$messages = [];
$stmt = $conn->prepare("SELECT m.id, m.user_id, m.body, m.attachment_path, m.attachment_type, m.attachment_name, m.created_at, u.name, u.is_premium, u.avatar
                       FROM community_messages m JOIN users u ON u.id = m.user_id
                       WHERE m.group_id = ? AND m.created_at >= ? ORDER BY m.id ASC LIMIT 200");
$stmt->bind_param('is', $groupId, $myJoinedAt);
$stmt->execute();
$mres = $stmt->get_result();
while ($m = $mres->fetch_assoc()) $messages[] = $m;
$stmt->close();
$lastId = !empty($messages) ? end($messages)['id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — <?php echo htmlspecialchars($group['name']); ?></title>
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

<section class="sf-section" style="padding-top:26px;">
    <a href="community.php" class="text-muted" style="font-size:0.85rem;">&larr; All groups</a>
    <div class="d-flex align-items-center justify-content-between flex-wrap mt-1 mb-3">
        <h2 style="margin:0;"><?php echo $group['icon']; ?> <?php echo htmlspecialchars($group['name']); ?></h2>
        <button id="leaveBtn" class="btn-sf-outline" style="border:1px solid var(--border);border-radius:8px;padding:8px 14px;font-size:0.85rem;">Leave group</button>
    </div>
    <p class="text-muted" style="max-width:560px;"><?php echo htmlspecialchars($group['description']); ?></p>
    <div class="sf-feature-card mb-3" style="max-width:560px;padding:12px 16px;font-size:0.82rem;">
        🔒 For everyone's safety, don't share phone numbers, Snapchat, or other social handles here — keep the conversation on StudyFlow.
    </div>

    <div class="sf-chat-window" id="groupWindow" style="min-height:420px;">
        <?php if (empty($messages)): ?>
            <div class="sf-msg ai">No messages yet — be the first to ask a question or share something useful in <?php echo htmlspecialchars($group['name']); ?>.</div>
        <?php else: ?>
            <?php foreach ($messages as $m): ?>
                <div class="sf-group-msg <?php echo $m['user_id'] == $user_id ? 'me' : ''; ?>">
                    <span class="sf-group-msg-name" style="color:<?php echo $m['user_id'] == $user_id ? 'var(--gold-light)' : sfNameColor($m['user_id']); ?>">
                        <span class="sf-msg-avatar"><?php echo sfAvatarEmoji($m['avatar']) ?? '🙂'; ?></span>
                        <?php echo htmlspecialchars($m['name']); ?><?php if (!empty($m['is_premium'])): ?> <span title="Premium member" style="color:var(--gold-dark);">★</span><?php endif; ?>
                    </span>
                    <?php if (!empty($m['attachment_path'])): ?>
                        <div class="sf-group-msg-body">
                            <?php if ($m['attachment_type'] === 'image'): ?>
                                <img src="<?php echo htmlspecialchars($m['attachment_path']); ?>" alt="" class="sf-chat-image">
                            <?php elseif ($m['attachment_type'] === 'audio'): ?>
                                <audio controls src="<?php echo htmlspecialchars($m['attachment_path']); ?>" class="sf-chat-audio"></audio>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($m['attachment_path']); ?>" target="_blank" rel="noopener" class="sf-chat-file">📎 <?php echo htmlspecialchars($m['attachment_name']); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (trim($m['body']) !== ''): ?>
                        <div class="sf-group-msg-body"><?php echo nl2br(htmlspecialchars($m['body'])); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <form id="groupForm" class="d-flex gap-2 mt-3 align-items-center">
        <button type="button" id="attachBtn" class="sf-chat-icon-btn" title="Attach a file">📎</button>
        <input type="file" id="fileInput" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt" style="display:none;">
        <button type="button" id="voiceBtn" class="sf-chat-icon-btn" title="Record a voice message">🎙️</button>
        <input type="text" id="groupInput" class="form-control" placeholder="Ask a question or share something with the group..." autocomplete="off">
        <button class="btn-sf-gold" style="margin:0;white-space:nowrap;" type="submit">Post</button>
    </form>
    <small id="uploadStatus" class="text-muted d-block mt-1" style="display:none;"></small>
</section>

<?php include 'includes/footer.php'; ?>
<script>
const groupId = <?php echo (int) $groupId; ?>;
const myUserId = <?php echo (int) $user_id; ?>;
let lastId = <?php echo (int) $lastId; ?>;
const win = document.getElementById('groupWindow');
function escapeHtml(s){ const d=document.createElement('div'); d.innerText=s; return d.innerHTML; }

function attachmentHtml(m) {
    if (!m.attachment_path) return '';
    if (m.attachment_type === 'image') return `<div class="sf-group-msg-body"><img src="${m.attachment_path}" alt="" class="sf-chat-image"></div>`;
    if (m.attachment_type === 'audio') return `<div class="sf-group-msg-body"><audio controls src="${m.attachment_path}" class="sf-chat-audio"></audio></div>`;
    return `<div class="sf-group-msg-body"><a href="${m.attachment_path}" target="_blank" rel="noopener" class="sf-chat-file">📎 ${escapeHtml(m.attachment_name || 'File')}</a></div>`;
}

const namePalette = ['#7dd3fc', '#c4b5fd', '#86efac', '#fca5a5', '#f0abfc', '#67e8f9', '#fdba74', '#a5b4fc'];
function nameColorFor(uid) { return uid === myUserId ? 'var(--gold-light)' : namePalette[uid % namePalette.length]; }

function renderMessage(m) {
    const mine = m.user_id === myUserId ? 'me' : '';
    const bodyHtml = (m.body && m.body.trim() !== '') ? `<div class="sf-group-msg-body">${escapeHtml(m.body).replace(/\n/g,'<br>')}</div>` : '';
    win.insertAdjacentHTML('beforeend', `<div class="sf-group-msg ${mine}">
        <span class="sf-group-msg-name" style="color:${nameColorFor(m.user_id)}">
            <span class="sf-msg-avatar">${m.avatar || '🙂'}</span>
            ${escapeHtml(m.name)}${m.premium ? ' <span title="Premium member" style="color:var(--gold-dark);">\u2605</span>' : ''}
        </span>
        ${attachmentHtml(m)}
        ${bodyHtml}
    </div>`);
    win.scrollTop = win.scrollHeight;
}

document.getElementById('groupForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const input = document.getElementById('groupInput');
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    const fd = new FormData();
    fd.append('action', 'post');
    fd.append('groupId', groupId);
    fd.append('body', text);
    fetch('group_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                lastId = data.message.id;
                renderMessage(data.message);
            } else {
                alert(data.text || 'Could not post — please try again.');
            }
        });
});

document.getElementById('leaveBtn').addEventListener('click', function () {
    if (!confirm('Leave this group?')) return;
    const fd = new FormData();
    fd.append('action', 'leave');
    fd.append('groupId', groupId);
    fetch('group_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if (data.ok) window.location.href = 'community.php'; });
});

// ---- File attachments ----
const statusEl = document.getElementById('uploadStatus');
document.getElementById('attachBtn').addEventListener('click', () => document.getElementById('fileInput').click());
document.getElementById('fileInput').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    uploadAttachment(file);
    this.value = '';
});

function uploadAttachment(file) {
    statusEl.style.display = 'block';
    statusEl.textContent = 'Uploading...';
    const fd = new FormData();
    fd.append('group_id', groupId);
    fd.append('attachment', file);
    fetch('group_upload.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            statusEl.style.display = 'none';
            if (data.ok) { lastId = data.message.id; renderMessage(data.message); }
            else alert(data.text || 'Upload failed.');
        })
        .catch(() => { statusEl.style.display = 'none'; alert('Upload failed — please try again.'); });
}

// ---- Voice messages (MediaRecorder API) ----
let mediaRecorder = null, audioChunks = [], recording = false;
const voiceBtn = document.getElementById('voiceBtn');

voiceBtn.addEventListener('click', async () => {
    if (!recording) {
        if (!navigator.mediaDevices || !window.MediaRecorder) {
            alert('Voice recording is not supported on this browser.');
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            audioChunks = [];
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.ondataavailable = (e) => audioChunks.push(e.data);
            mediaRecorder.onstop = () => {
                stream.getTracks().forEach(t => t.stop());
                const blob = new Blob(audioChunks, { type: 'audio/webm' });
                const file = new File([blob], 'voice-message.webm', { type: 'audio/webm' });
                uploadAttachment(file);
            };
            mediaRecorder.start();
            recording = true;
            voiceBtn.textContent = '⏹️';
            voiceBtn.title = 'Stop recording';
        } catch (err) {
            alert('Microphone access was denied or is unavailable.');
        }
    } else {
        mediaRecorder.stop();
        recording = false;
        voiceBtn.textContent = '🎙️';
        voiceBtn.title = 'Record a voice message';
    }
});

// Lightweight polling so new messages from others show up without a manual reload.
setInterval(function () {
    fetch(`group_handler.php?action=fetch&groupId=${groupId}&afterId=${lastId}`)
        .then(r => r.json())
        .then(data => {
            if (data.ok && data.messages.length) {
                data.messages.forEach(m => { lastId = m.id; renderMessage(m); });
            }
        });
}, 4000);
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
