<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>StudyFlow — Focus timer</title>

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
    <style>
        .sf-timer-shell {
            min-height: 100vh;
            background: var(--ink);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .sf-timer-card {
            background: var(--ink-2);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 3rem 3.5rem;
            text-align: center;
            color: #fff;
            max-width: 420px;
            width: 100%;
        }
        #timer {
            font-family: var(--font-mono);
            font-size: 4.2rem;
            font-weight: 700;
            color: var(--gold-light);
            letter-spacing: 0.02em;
            margin: 1.5rem 0 2rem;
        }
        .sf-back-link { color: rgba(255,255,255,0.6); font-size: 0.9rem; }
        .sf-back-link:hover { color: var(--gold-light); }
    </style>
</head>

<body>
<script>
(function(){
  var t = localStorage.getItem('sf-theme') || 'dark';
  if (t === 'dark') document.body.classList.add('sf-theme-deep');
})();
</script>

<div class="sf-timer-shell">
    <div class="sf-timer-card">
        <div class="sf-brand justify-content-center">
            <img src="assets/img/logo.svg" alt="StudyFlow logo">
            <span class="sf-brand-text" style="color:#fff;">Focus timer</span>
        </div>

        <div class="sf-timer-inputs mb-3 d-flex align-items-center justify-content-center gap-1">
            <input type="number" id="hoursInput" min="0" max="12" value="0">
            <span>h</span>
            <input type="number" id="minsInput" min="0" max="59" value="25">
            <span>m</span>
        </div>

        <div id="timer">25:00</div>

        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <button class="btn-sf-gold" id="startBtn" onclick="startTimer()">Start focus</button>
            <button class="btn-sf-gold" id="stopBtn" onclick="stopTimer()">Stop</button>
            <button class="btn-sf-outline" style="border-color:rgba(255,255,255,0.25); color:#fff;" onclick="resetTimer()">Reset</button>
        </div>

        <div class="mt-4 sf-sound-picker">
            <p class="text-muted mb-2" style="font-size:0.85rem;">Background sound</p>
            <button type="button" class="sf-sound-btn" id="deepFocusBtn" onclick="toggleDeepFocus()">🎧 Deep Focus</button>

            <div class="mt-3">
                <label class="text-muted d-block mb-1" style="font-size:0.85rem;">Or paste a Spotify playlist, album, or track link</label>
                <div class="d-flex gap-2">
                    <input type="text" id="spotifyUrlInput" class="form-control" placeholder="https://open.spotify.com/playlist/...">
                    <button type="button" class="btn-sf-gold" style="margin:0;white-space:nowrap;" onclick="loadSpotifyEmbed()">Load</button>
                </div>
                <a href="https://open.spotify.com" target="_blank" rel="noopener" class="text-muted d-block mt-1" style="font-size:0.82rem;">On mobile? Log into Spotify here first, then paste your link above.</a>
                <div id="spotifyEmbedWrap" class="mt-3"></div>
            </div>
        </div>

        <div class="mt-4">
            <a href="index.php" class="sf-back-link">&larr; Back to dashboard</a>
        </div>
    </div>
</div>

<style>
.sf-sound-btn {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.14);
    color: rgba(255,255,255,0.85);
    border-radius: 999px;
    padding: 8px 16px;
    font-size: 0.88rem;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}
.sf-sound-btn:hover { border-color: var(--gold-light); color: var(--gold-light); }
.sf-sound-btn.active { background: rgba(233,201,106,0.16); border-color: var(--gold-light); color: var(--gold-light); }
</style>

<script>
let time = 25 * 60;
let interval = null;

function readInputsToTime() {
    const h = Math.max(0, parseInt(document.getElementById('hoursInput').value) || 0);
    const m = Math.max(0, parseInt(document.getElementById('minsInput').value) || 0);
    return (h * 3600) + (m * 60);
}

function renderTime() {
    let minutes = Math.floor(time / 60);
    let seconds = time % 60;
    minutes = String(minutes).padStart(2, "0");
    seconds = String(seconds).padStart(2, "0");
    document.getElementById("timer").innerText = minutes + ":" + seconds;
}

function playAlarm() {
    // Short beeping alarm using the Web Audio API — no external audio file needed
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        let t = ctx.currentTime;
        for (let i = 0; i < 4; i++) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = "sine";
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.25, t);
            osc.connect(gain).connect(ctx.destination);
            osc.start(t);
            osc.stop(t + 0.35);
            t += 0.5;
        }
    } catch (e) { /* audio not supported, fail silently */ }
}

// ---- Deep Focus ambient tone (soft brown noise) ----
let deepFocusCtx = null;
let deepFocusNode = null;
let deepFocusOn = false;

function toggleDeepFocus() {
    const btn = document.getElementById('deepFocusBtn');
    if (deepFocusOn) {
        if (deepFocusNode) deepFocusNode.stop();
        if (deepFocusCtx) deepFocusCtx.close();
        deepFocusNode = null;
        deepFocusCtx = null;
        deepFocusOn = false;
        btn.classList.remove('active');
        return;
    }
    try {
        deepFocusCtx = new (window.AudioContext || window.webkitAudioContext)();
        const bufferSize = 4096;
        const node = deepFocusCtx.createScriptProcessor(bufferSize, 1, 1);
        let lastOut = 0;
        node.onaudioprocess = function(e) {
            const output = e.outputBuffer.getChannelData(0);
            for (let i = 0; i < bufferSize; i++) {
                const white = Math.random() * 2 - 1;
                output[i] = (lastOut + (0.02 * white)) / 1.02;
                lastOut = output[i];
                output[i] *= 3.0; // gentle volume boost after filtering
            }
        };
        const gain = deepFocusCtx.createGain();
        gain.gain.value = 0.12; // soft, background-level volume
        node.connect(gain).connect(deepFocusCtx.destination);
        deepFocusNode = node;
        deepFocusOn = true;
        btn.classList.add('active');
    } catch (e) {
        alert('Your browser does not support ambient audio.');
    }
}

// ---- Spotify embed ----
function buildSpotifyEmbedHtml(url) {
    // Accepts playlist/album/track/show/episode links, with or without query params
    const match = url.match(/open\.spotify\.com\/(playlist|album|track|show|episode)\/([a-zA-Z0-9]+)/);
    if (!match) return null;
    const type = match[1];
    const id = match[2];
    const height = (type === 'track' || type === 'episode') ? 152 : 352;
    return `<iframe style="border-radius:12px;" src="https://open.spotify.com/embed/${type}/${id}?utm_source=generator" width="100%" height="${height}" frameborder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>`;
}

function loadSpotifyEmbed() {
    const url = document.getElementById('spotifyUrlInput').value.trim();
    const wrap = document.getElementById('spotifyEmbedWrap');
    const html = buildSpotifyEmbedHtml(url);
    if (!html) {
        alert("That doesn't look like a Spotify playlist, album, or track link. Copy it from the Share button in Spotify.");
        return;
    }
    wrap.innerHTML = html;
    localStorage.setItem('sf-spotify-url', url);
}

// Restore the last-used Spotify link, if any
(function(){
    const saved = localStorage.getItem('sf-spotify-url');
    if (saved) {
        document.getElementById('spotifyUrlInput').value = saved;
        const html = buildSpotifyEmbedHtml(saved);
        if (html) document.getElementById('spotifyEmbedWrap').innerHTML = html;
    }
})();

function startTimer() {
    clearInterval(interval);
    if (time <= 0) time = readInputsToTime();
    document.getElementById('hoursInput').disabled = true;
    document.getElementById('minsInput').disabled = true;

    interval = setInterval(() => {
        renderTime();
        if (time <= 0) {
            clearInterval(interval);
            playAlarm();
            document.getElementById('hoursInput').disabled = false;
            document.getElementById('minsInput').disabled = false;
            return;
        }
        time--;
    }, 1000);
}

function stopTimer() {
    clearInterval(interval);
    document.getElementById('hoursInput').disabled = false;
    document.getElementById('minsInput').disabled = false;
}

function resetTimer() {
    clearInterval(interval);
    document.getElementById('hoursInput').disabled = false;
    document.getElementById('minsInput').disabled = false;
    time = readInputsToTime();
    renderTime();
}

document.getElementById('hoursInput').addEventListener('change', resetTimer);
document.getElementById('minsInput').addEventListener('change', resetTimer);
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
