<?php
// Expects optional $active var set by the including page (e.g. "home", "quiz")
if (session_status() === PHP_SESSION_NONE) session_start();
$active = $active ?? '';
$loggedIn = isset($_SESSION['user_id']);
$userXp = 0;

if ($loggedIn) {
    // Pull current XP for the announcement bar. Safe no-op if db.php wasn't
    // already included by the page (we include it here defensively).
    if (!isset($conn)) {
        include_once __DIR__ . '/../config/db.php';
    }
    if (isset($conn)) {
        $uid = (int) $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT xp FROM users WHERE id = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $userXp = (int) $res->fetch_assoc()['xp'];
        }
        $stmt->close();
    }
}

function sfNav($href, $label, $active) {
    $key = strtolower(str_replace([' ', '&'], ['', ''], $label));
    $cls = ($active === $key) ? 'active' : '';
    echo "<a href=\"$href\" class=\"$cls\">$label</a>";
}
?>
<div id="sfInstallBanner" class="sf-install-banner" style="display:none;">
    <span>📲 Add StudyFlow to your Home Screen for quick, app-like access.</span>
    <span>
        <button id="sfInstallBtn" type="button">Add to Home Screen</button>
        <button id="sfInstallDismiss" type="button" class="sf-install-dismiss" aria-label="Dismiss">✕</button>
    </span>
</div>
<div id="sfIosHelp" class="sf-install-banner" style="display:none;">
    <span>📲 To install: tap the Share icon <strong>⬆️</strong> in Safari, then "Add to Home Screen".</span>
    <span><button id="sfIosDismiss" type="button" class="sf-install-dismiss" aria-label="Dismiss">✕</button></span>
</div>
<nav class="sf-topnav">
    <div class="sf-brand">
        <img src="assets/img/logo.svg" alt="StudyFlow logo">
        <span class="sf-brand-text">Study<span style="color:var(--gold-light)">Flow</span></span>
    </div>

    <button class="sf-navtoggle" onclick="document.querySelector('.sf-topnav-links').classList.toggle('open')">&#9776;</button>

    <div class="sf-topnav-links">
        <?php
        sfNav('home.php', 'Home', $active);
        sfNav('subjects.php', 'Subjects', $active);
        sfNav('ai-tutor.php', 'AI Tutor', $active);
        sfNav('quiz.php', 'Quiz', $active);
        sfNav('community.php', 'Community', $active);
        sfNav('friends.php', 'Friends', $active);
        sfNav('resources.php', 'Resources', $active);
        sfNav('pricing.php', 'Pricing', $active);
        ?>
        <?php if ($loggedIn): ?>
            <a href="index.php" class="sf-topnav-cta">Dashboard</a>
            <a href="profile.php" class="sf-nav-avatar-link" title="Profile" aria-label="Profile">
                <?php
                if (isset($conn) && $loggedIn) {
                    $avatarOptions = ['fox'=>'🦊','owl'=>'🦉','cat'=>'🐱','panda'=>'🐼','lion'=>'🦁','koala'=>'🐨','penguin'=>'🐧','wolf'=>'🐺','rocket'=>'🚀','star'=>'⭐','book'=>'📚','bulb'=>'💡'];
                    $avStmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
                    $avStmt->bind_param('i', $uid);
                    $avStmt->execute();
                    $avRow = $avStmt->get_result()->fetch_assoc();
                    $avStmt->close();
                    $myAvatar = $avRow['avatar'] ?? null;
                }
                if (!empty($myAvatar) && isset($avatarOptions[$myAvatar])):
                ?>
                    <span class="sf-nav-avatar has-emoji"><?php echo $avatarOptions[$myAvatar]; ?></span>
                <?php else: ?>
                    <span class="sf-nav-avatar default">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"/>
                        </svg>
                    </span>
                <?php endif; ?>
                <span class="sf-nav-avatar-label">Profile</span>
                <span class="sf-nav-xp-badge">⚡ <?php echo $userXp; ?> XP</span>
            </a>
        <?php else: ?>
            <a href="login.php">Log in</a>
            <a href="register.php" class="sf-topnav-cta">Start Learning</a>
        <?php endif; ?>
        <button type="button" id="sfThemeToggle" class="sf-theme-toggle" title="Switch theme" aria-label="Switch light/dark theme">🌙</button>
        <div id="google_translate_element" class="sf-lang-select"></div>
    </div>
</nav>

<!-- Floating shortcuts: Focus Sounds + AI Tutor + Community, bottom-right on every page -->
<div class="sf-fab-dock" id="sfFabDock">
    <div class="sf-fab-sound-wrap">
        <button type="button" id="sfSoundFab" class="sf-fab sf-fab-sound" title="Focus sounds" aria-label="Toggle focus sounds">🎵</button>
        <div id="sfSoundPanel" class="sf-sound-panel" style="display:none;">
            <div class="sf-sound-title">Focus sounds</div>
            <button type="button" id="sfDeepFocusToggle" class="sf-sound-option" data-sound="off">🎧 Deep Focus</button>
            <input type="range" id="sfSoundVolume" min="0" max="100" value="35" class="mt-2 w-100">
            <div id="sfFabSpotifyWrap" class="mt-2"></div>
        </div>
    </div>
    <a href="community.php" class="sf-fab sf-fab-community" title="Community" aria-label="Open Community">💬</a>
    <a href="ai-tutor.php" class="sf-fab sf-fab-ai" title="AI Tutor" aria-label="Open AI Tutor">✨</a>
</div>
<script>
(function(){
    var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (isStandalone) return; // already installed — never show the banner

    var dismissedUntil = parseInt(localStorage.getItem('sfInstallDismissedUntil') || '0', 10);
    if (Date.now() < dismissedUntil) return;

    var isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    var deferredPrompt = null;

    function dismissFor7Days(bannerEl) {
        localStorage.setItem('sfInstallDismissedUntil', String(Date.now() + 7 * 24 * 60 * 60 * 1000));
        bannerEl.style.display = 'none';
    }

    if (isIos) {
        var iosBanner = document.getElementById('sfIosHelp');
        if (iosBanner) {
            iosBanner.style.display = 'flex';
            document.getElementById('sfIosDismiss').addEventListener('click', function(){ dismissFor7Days(iosBanner); });
        }
    } else {
        window.addEventListener('beforeinstallprompt', function(e){
            e.preventDefault();
            deferredPrompt = e;
            var banner = document.getElementById('sfInstallBanner');
            if (banner) banner.style.display = 'flex';
        });

        var installBtn = document.getElementById('sfInstallBtn');
        var banner = document.getElementById('sfInstallBanner');
        if (installBtn) {
            installBtn.addEventListener('click', function(){
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                deferredPrompt.userChoice.finally(function(){
                    deferredPrompt = null;
                    if (banner) banner.style.display = 'none';
                });
            });
        }
        var dismissBtn = document.getElementById('sfInstallDismiss');
        if (dismissBtn && banner) {
            dismissBtn.addEventListener('click', function(){ dismissFor7Days(banner); });
        }
    }
})();

// ---- Resources dropdown: tap-to-toggle (works on mobile where hover doesn't) ----
(function(){
    document.querySelectorAll('.sf-nav-dropdown-toggle').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            btn.closest('.sf-nav-dropdown').classList.toggle('open');
        });
    });
    document.addEventListener('click', function(){
        document.querySelectorAll('.sf-nav-dropdown.open').forEach(function(d){ d.classList.remove('open'); });
    });
})();

// ---- Dark / Light theme toggle ----
(function(){
    var btn = document.getElementById('sfThemeToggle');
    if (!btn) return;
    function current() { return localStorage.getItem('sf-theme') || 'dark'; }
    function paint() { btn.textContent = current() === 'dark' ? '☀️' : '🌙'; }
    paint();
    btn.addEventListener('click', function(){
        var next = current() === 'dark' ? 'light' : 'dark';
        localStorage.setItem('sf-theme', next);
        document.body.classList.toggle('sf-theme-deep', next === 'dark');
        paint();
    });
})();

// ---- Accent color (set from Profile > Settings, applied on every page) ----
(function(){
    var accent = localStorage.getItem('sf-accent') || 'gold';
    if (accent !== 'gold') document.body.setAttribute('data-accent', accent);
})();

// ---- Draggable floating dock (long-press to move, tap still opens as normal) ----
(function(){
    const dock = document.getElementById('sfFabDock');
    if (!dock) return;

    const saved = JSON.parse(localStorage.getItem('sf-dock-pos') || 'null');
    if (saved && saved.top && saved.left) {
        dock.style.top = saved.top;
        dock.style.left = saved.left;
        dock.style.right = 'auto';
        dock.style.bottom = 'auto';
    }

    let pressTimer = null, dragging = false, moved = false;
    let startX = 0, startY = 0, dockStartX = 0, dockStartY = 0;

    function getPoint(e) {
        return e.touches ? { x: e.touches[0].clientX, y: e.touches[0].clientY } : { x: e.clientX, y: e.clientY };
    }

    function beginDrag(e) {
        const p = getPoint(e);
        startX = p.x; startY = p.y;
        const rect = dock.getBoundingClientRect();
        dockStartX = rect.left; dockStartY = rect.top;
        dragging = true; moved = false;
        dock.classList.add('sf-dock-dragging');
    }

    function onMove(e) {
        if (!dragging) return;
        const p = getPoint(e);
        const dx = p.x - startX, dy = p.y - startY;
        if (Math.abs(dx) > 4 || Math.abs(dy) > 4) moved = true;
        if (!moved) return;
        e.preventDefault();
        let newLeft = dockStartX + dx;
        let newTop = dockStartY + dy;
        newLeft = Math.max(4, Math.min(window.innerWidth - dock.offsetWidth - 4, newLeft));
        newTop = Math.max(4, Math.min(window.innerHeight - dock.offsetHeight - 4, newTop));
        dock.style.left = newLeft + 'px';
        dock.style.top = newTop + 'px';
        dock.style.right = 'auto';
        dock.style.bottom = 'auto';
    }

    function endDrag() {
        clearTimeout(pressTimer);
        if (dragging && moved) {
            localStorage.setItem('sf-dock-pos', JSON.stringify({ top: dock.style.top, left: dock.style.left }));
            // Swallow the click that would otherwise fire on the button under the pointer.
            dock.classList.add('sf-dock-just-dragged');
            setTimeout(() => dock.classList.remove('sf-dock-just-dragged'), 200);
        }
        dragging = false;
        dock.classList.remove('sf-dock-dragging');
    }

    dock.addEventListener('mousedown', (e) => { pressTimer = setTimeout(() => beginDrag(e), 400); });
    dock.addEventListener('touchstart', (e) => { pressTimer = setTimeout(() => beginDrag(e), 400); }, { passive: true });
    ['mouseup','touchend','touchcancel'].forEach(evt => dock.addEventListener(evt, endDrag));
    document.addEventListener('mousemove', onMove);
    document.addEventListener('touchmove', onMove, { passive: false });
    dock.addEventListener('mouseleave', () => clearTimeout(pressTimer));

    // If a drag just happened, block the click so it doesn't also navigate/open.
    dock.addEventListener('click', (e) => {
        if (dock.classList.contains('sf-dock-just-dragged')) { e.preventDefault(); e.stopPropagation(); }
    }, true);
})();

// ---- Focus Sounds (synthesized in-browser via Web Audio API — no external ----
// ---- audio files, so nothing to license, host, or worry about breaking) ----
(function(){
    const fab = document.getElementById('sfSoundFab');
    const panel = document.getElementById('sfSoundPanel');
    const volumeSlider = document.getElementById('sfSoundVolume');
    if (!fab || !panel) return;

    let audioCtx = null, source = null, gainNode = null, filterNode = null;
    let currentSound = localStorage.getItem('sf-sound') || 'off';
    let currentVolume = parseInt(localStorage.getItem('sf-sound-vol') || '35', 10);
    volumeSlider.value = currentVolume;

    fab.addEventListener('click', () => {
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    });
    document.addEventListener('click', (e) => {
        if (!panel.contains(e.target) && e.target !== fab) panel.style.display = 'none';
    });

    function stopSound() {
        if (source) { try { source.stop(); } catch(e){} source.disconnect(); source = null; }
        if (gainNode) { gainNode.disconnect(); gainNode = null; }
        if (filterNode) { filterNode.disconnect(); filterNode = null; }
    }

    function makeNoiseBuffer(ctx, type) {
        const bufferSize = 2 * ctx.sampleRate;
        const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        const data = buffer.getChannelData(0);
        if (type === 'brown') {
            let last = 0;
            for (let i = 0; i < bufferSize; i++) {
                const white = Math.random() * 2 - 1;
                last = (last + 0.02 * white) / 1.02;
                data[i] = last * 3.5;
            }
        } else {
            for (let i = 0; i < bufferSize; i++) data[i] = Math.random() * 2 - 1;
        }
        return buffer;
    }

    function startSound(type) {
        stopSound();
        if (type === 'off') return;
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();

        const buffer = makeNoiseBuffer(audioCtx, type === 'brown' ? 'brown' : 'white');
        source = audioCtx.createBufferSource();
        source.buffer = buffer;
        source.loop = true;

        filterNode = audioCtx.createBiquadFilter();
        if (type === 'rain') { filterNode.type = 'bandpass'; filterNode.frequency.value = 2200; filterNode.Q.value = 0.6; }
        else if (type === 'brown') { filterNode.type = 'lowpass'; filterNode.frequency.value = 500; }
        else { filterNode.type = 'lowpass'; filterNode.frequency.value = 6000; }

        gainNode = audioCtx.createGain();
        gainNode.gain.value = currentVolume / 100 * 0.5;

        source.connect(filterNode);
        filterNode.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        source.start(0);
    }

    document.getElementById('sfDeepFocusToggle')?.addEventListener('click', () => {
        const btn = document.getElementById('sfDeepFocusToggle');
        const isOn = currentSound === 'brown';
        currentSound = isOn ? 'off' : 'brown';
        localStorage.setItem('sf-sound', currentSound);
        btn.classList.toggle('active', !isOn);
        startSound(currentSound);
        fab.textContent = currentSound === 'off' ? '🎵' : '🔊';
    });
    if (currentSound === 'brown') document.getElementById('sfDeepFocusToggle')?.classList.add('active');

    volumeSlider.addEventListener('input', () => {
        currentVolume = parseInt(volumeSlider.value, 10);
        localStorage.setItem('sf-sound-vol', currentVolume);
        if (gainNode) gainNode.gain.value = currentVolume / 100 * 0.5;
    });

    // Show whatever Spotify playlist they already saved from the Focus Timer page —
    // no link/load input here, this is just a shortcut to what they already set up.
    (function(){
        const saved = localStorage.getItem('sf-spotify-url');
        const wrap = document.getElementById('sfFabSpotifyWrap');
        if (!wrap) return;
        const match = saved ? saved.match(/open\.spotify\.com\/(playlist|album|track|show|episode)\/([a-zA-Z0-9]+)/) : null;
        if (!match) {
            wrap.innerHTML = `<p class="text-muted mb-0" style="font-size:0.78rem;">Add a playlist in Focus Timer to see it here.</p>`;
            return;
        }
        const type = match[1];
        const id = match[2];
        const height = (type === 'track' || type === 'episode') ? 152 : 232;
        wrap.innerHTML = `<iframe style="border-radius:12px;" src="https://open.spotify.com/embed/${type}/${id}?utm_source=generator" width="100%" height="${height}" frameborder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>`;
    })();

    // Don't auto-play on load (browsers block audio without a user gesture) —
    // just reflect the saved preference in the UI until they interact.
    if (currentSound !== 'off') fab.textContent = '🔊';
})();
</script>

<!-- Google Translate — lightweight stand-in for full multi-language support -->
<script type="text/javascript">
function sfGoogleTranslateInit() {
    new google.translate.TranslateElement(
        { pageLanguage: 'en', autoDisplay: false },
        'google_translate_element'
    );
}
</script>
<script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=sfGoogleTranslateInit"></script>
