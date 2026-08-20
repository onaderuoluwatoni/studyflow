<?php
session_start();
include 'config/db.php';
$active = 'aitutor';
$loggedIn = isset($_SESSION['user_id']);

$_SESSION['ai_question_count'] = $_SESSION['ai_question_count'] ?? 0;
$_SESSION['ai_image_count'] = $_SESSION['ai_image_count'] ?? 0;
$qUsed = $_SESSION['ai_question_count'];
$iUsed = $_SESSION['ai_image_count'];

$conversations = [];
$activeConvId = 0;
$activeMessages = [];

if ($loggedIn) {
    $user_id = (int) $_SESSION['user_id'];

    // Delete a chat (same reload-based pattern used by flashcards' delete link).
    if (isset($_GET['deleteConv'])) {
        $delId = (int) $_GET['deleteConv'];
        $conn->query("DELETE FROM ai_conversations WHERE id='$delId' AND user_id='$user_id'");
        header("Location: ai-tutor.php");
        exit();
    }

    $convRes = $conn->query("SELECT id, title, updated_at FROM ai_conversations WHERE user_id='$user_id' ORDER BY updated_at DESC LIMIT 50");
    if ($convRes) {
        while ($row = $convRes->fetch_assoc()) $conversations[] = $row;
    }

    if (isset($_GET['c'])) {
        $requested = (int) $_GET['c'];
        $own = $conn->query("SELECT id FROM ai_conversations WHERE id='$requested' AND user_id='$user_id'");
        if ($own && $own->num_rows > 0) {
            $activeConvId = $requested;
            $msgRes = $conn->query("SELECT role, content, image_data_url FROM ai_messages WHERE conversation_id='$activeConvId' ORDER BY id ASC");
            if ($msgRes) {
                while ($m = $msgRes->fetch_assoc()) $activeMessages[] = $m;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>StudyFlow — AI Tutor</title>
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

<div class="sf-tutor-shell">
    <?php if ($loggedIn): ?>
    <aside class="sf-tutor-sidebar" id="tutorSidebar">
        <a href="ai-tutor.php" class="sf-newchat-btn">+ New chat</a>
        <div class="sf-convo-list" id="convoList">
            <?php if (empty($conversations)): ?>
                <p class="sf-convo-empty">Your past chats will show up here.</p>
            <?php endif; ?>
            <?php foreach ($conversations as $c): ?>
                <div class="sf-convo-item <?php echo ($c['id'] == $activeConvId) ? 'active' : ''; ?>" data-conv-id="<?php echo $c['id']; ?>">
                    <a href="ai-tutor.php?c=<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></a>
                    <a href="ai-tutor.php?deleteConv=<?php echo $c['id']; ?>" class="sf-convo-delete" title="Delete chat" onclick="return confirm('Delete this chat?');">&times;</a>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>
    <?php endif; ?>

    <main class="sf-tutor-main">
        <div class="sf-tutor-hero">
            <h2>Ask a question, get a topic explained, or generate a quiz</h2>
            <div class="mt-2">
                <small style="color:rgba(255,255,255,0.65);">Questions asked: <?php echo $qUsed; ?> · <span style="color:var(--gold-light);">Unlimited</span></small>
                <small style="color:rgba(255,255,255,0.65);" class="ms-3">File uploads used: <?php echo $iUsed; ?> / 10</small>
                <span class="sf-usage-meter ms-2"><i style="width:<?php echo min(100, round($iUsed/10*100)); ?>%"></i></span>
            </div>
        </div>

        <section class="sf-section" style="padding-top:26px;">
            <div class="mb-3">
                <span class="sf-pill active" data-mode="ask">Ask a question</span>
                <span class="sf-pill" data-mode="explain">Explain a topic</span>
                <span class="sf-pill" data-mode="summarize">Summarize notes</span>
                <span class="sf-pill" data-mode="quiz">Generate a quiz</span>
                <span class="sf-pill" data-mode="math">Solve math step-by-step</span>
            </div>

            <div class="sf-chat-window" id="chatWindow">
                <?php if (empty($activeMessages)): ?>
                    <div class="sf-msg ai">Hi! I'm your StudyFlow AI Tutor. Pick a mode above and ask me anything — homework questions, tricky topics, or a quiz on demand. I'll show my working step-by-step for maths and science.</div>
                <?php else: ?>
                    <?php foreach ($activeMessages as $m): ?>
                        <?php if ($m['role'] === 'user'): ?>
                            <div class="sf-msg user">
                                <?php if (!empty($m['image_data_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($m['image_data_url']); ?>" class="sf-msg-img" alt="Attached image">
                                <?php endif; ?>
                                <?php echo nl2br(htmlspecialchars($m['content'])); ?>
                            </div>
                        <?php else: ?>
                            <div class="sf-msg ai"><?php echo nl2br(htmlspecialchars($m['content'])); ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="premiumWall" style="display:none;" class="sf-premium-lock mt-3">
                <h6 class="mb-1">You've hit your free-tier limit</h6>
                <p class="text-muted mb-2" id="premiumWallText">Upgrade to Premium for unlimited AI Tutor access.</p>
                <a href="pricing.php" class="btn-sf-gold" style="margin:0;">See Premium plans</a>
            </div>

            <div id="imagePreviewBar" class="sf-image-preview" style="display:none;">
                <img id="imagePreviewThumb" src="" alt="Preview">
                <span id="imagePreviewName"></span>
                <button type="button" id="imagePreviewRemove" title="Remove attachment">&times;</button>
            </div>

            <form id="chatForm" class="d-flex gap-2 mt-3 align-items-center">
                <label class="btn-sf-outline mb-0" style="border:1px solid var(--border);border-radius:8px;padding:10px 14px;cursor:pointer;">
                    📎 <input type="file" id="imageInput" accept="image/*" style="display:none;">
                </label>
                <button type="button" id="voiceBtn" class="btn-sf-outline mb-0" style="border:1px solid var(--border);border-radius:8px;padding:10px 14px;" title="Send a voice message">🎤</button>
                <input type="text" id="chatInput" class="form-control" placeholder="Type your question, or tap 🎤 to speak...">
                <button class="btn-sf-gold" style="margin:0;white-space:nowrap;" type="submit">Send</button>
            </form>
            <small class="text-muted d-block mt-1" id="voiceNote"></small>
        </section>
    </main>
</div>

<script>
let mode = 'ask';
let currentConversationId = <?php echo (int) $activeConvId; ?>;
let attachedImageDataUrl = null;
let attachedImageName = '';

document.querySelectorAll('.sf-pill').forEach(p => {
    p.addEventListener('click', () => {
        document.querySelectorAll('.sf-pill').forEach(x => x.classList.remove('active'));
        p.classList.add('active'); mode = p.dataset.mode;
    });
});

/* ---------- File upload: read + preview, like Claude/ChatGPT ---------- */
const imageInput = document.getElementById('imageInput');
const previewBar = document.getElementById('imagePreviewBar');
const previewThumb = document.getElementById('imagePreviewThumb');
const previewName = document.getElementById('imagePreviewName');

imageInput.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (e) {
        attachedImageDataUrl = e.target.result;
        attachedImageName = file.name;
        previewThumb.src = attachedImageDataUrl;
        previewName.innerText = file.name;
        previewBar.style.display = 'flex';
    };
    reader.readAsDataURL(file);
});

document.getElementById('imagePreviewRemove').addEventListener('click', function () {
    attachedImageDataUrl = null;
    attachedImageName = '';
    imageInput.value = '';
    previewBar.style.display = 'none';
});

/* ---------- Voice message (speech-to-text, like sending a voice note) ---------- */
const voiceBtn = document.getElementById('voiceBtn');
const voiceNote = document.getElementById('voiceNote');
const chatInputEl = document.getElementById('chatInput');
const SpeechRecognitionAPI = window.SpeechRecognition || window.webkitSpeechRecognition;
let recognizer = null;
let isRecording = false;

if (SpeechRecognitionAPI) {
    recognizer = new SpeechRecognitionAPI();
    recognizer.lang = 'en-US';
    recognizer.interimResults = true;
    recognizer.continuous = false;

    recognizer.addEventListener('start', () => {
        isRecording = true;
        voiceBtn.classList.add('recording');
        voiceBtn.innerText = '⏺️';
        voiceNote.innerText = 'Listening... tap the mic again to stop.';
    });

    recognizer.addEventListener('result', (e) => {
        let transcript = '';
        for (let i = 0; i < e.results.length; i++) transcript += e.results[i][0].transcript;
        chatInputEl.value = transcript;
    });

    recognizer.addEventListener('end', () => {
        isRecording = false;
        voiceBtn.classList.remove('recording');
        voiceBtn.innerText = '🎤';
        if (chatInputEl.value.trim()) {
            voiceNote.innerText = 'Voice message transcribed — tap Send, or keep editing it first.';
        } else {
            voiceNote.innerText = '';
        }
    });

    recognizer.addEventListener('error', () => {
        isRecording = false;
        voiceBtn.classList.remove('recording');
        voiceBtn.innerText = '🎤';
        voiceNote.innerText = "Couldn't hear that — check your mic permissions and try again.";
    });

    voiceBtn.addEventListener('click', () => {
        if (isRecording) {
            recognizer.stop();
        } else {
            chatInputEl.value = '';
            recognizer.start();
        }
    });
} else {
    voiceBtn.addEventListener('click', () => {
        voiceNote.innerText = 'Voice messages need Chrome, Edge, or Safari with microphone access enabled.';
    });
}

const win = document.getElementById('chatWindow');
function escapeHtml(s){ const d=document.createElement('div'); d.innerText=s; return d.innerHTML; }

function addSidebarConversation(id, title) {
    const list = document.getElementById('convoList');
    if (!list) return;
    const emptyMsg = list.querySelector('.sf-convo-empty');
    if (emptyMsg) emptyMsg.remove();
    document.querySelectorAll('.sf-convo-item').forEach(el => el.classList.remove('active'));
    const item = document.createElement('div');
    item.className = 'sf-convo-item active';
    item.dataset.convId = id;
    item.innerHTML = `<a href="ai-tutor.php?c=${id}"></a><a href="ai-tutor.php?deleteConv=${id}" class="sf-convo-delete" title="Delete chat" onclick="return confirm('Delete this chat?');">&times;</a>`;
    item.querySelector('a').innerText = title;
    list.prepend(item);
}

document.getElementById('chatForm').addEventListener('submit', function(e){
    e.preventDefault();
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    if (!text) return;

    let userBubble = '';
    if (attachedImageDataUrl) {
        userBubble += `<img src="${attachedImageDataUrl}" class="sf-msg-img" alt="Attached image">`;
    }
    userBubble += escapeHtml(text);
    win.insertAdjacentHTML('beforeend', `<div class="sf-msg user">${userBubble}</div>`);
    win.scrollTop = win.scrollHeight;
    input.value = '';
    input.disabled = true;

    const fd = new FormData();
    fd.append('message', text);
    fd.append('mode', mode);
    fd.append('conversationId', currentConversationId);
    fd.append('hasImage', attachedImageDataUrl ? '1' : '0');
    if (attachedImageDataUrl) fd.append('imageData', attachedImageDataUrl);

    fetch('ai_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            input.disabled = false;
            if (data.gate === 'premium_images' || data.gate === 'premium_questions') {
                document.getElementById('premiumWallText').innerText = data.text;
                document.getElementById('premiumWall').style.display = 'block';
                document.getElementById('chatForm').style.display = 'none';
                return;
            }
            win.insertAdjacentHTML('beforeend', `<div class="sf-msg ai">${escapeHtml(data.text)}</div>`);
            win.scrollTop = win.scrollHeight;

            if (data.conversationId) {
                const wasNew = currentConversationId === 0;
                currentConversationId = data.conversationId;
                history.replaceState(null, '', 'ai-tutor.php?c=' + currentConversationId);
                if (wasNew || data.isNewConversation) {
                    addSidebarConversation(currentConversationId, text.slice(0, 60));
                }
            }

            attachedImageDataUrl = null;
            attachedImageName = '';
            document.getElementById('imageInput').value = '';
            previewBar.style.display = 'none';
        })
        .catch(() => {
            input.disabled = false;
            win.insertAdjacentHTML('beforeend', `<div class="sf-msg ai">Something went wrong reaching the AI Tutor. Please try again.</div>`);
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
