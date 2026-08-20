<?php
/**
 * Shared across group chat, DMs, and anywhere else we show a user's
 * avatar/name — keeps the emoji set and color assignment consistent
 * everywhere instead of redefining it per page.
 */

function sfAvatarOptions() {
    return [
        'fox' => '🦊', 'owl' => '🦉', 'cat' => '🐱', 'panda' => '🐼',
        'lion' => '🦁', 'koala' => '🐨', 'penguin' => '🐧', 'wolf' => '🐺',
        'rocket' => '🚀', 'star' => '⭐', 'book' => '📚', 'bulb' => '💡',
    ];
}

function sfAvatarEmoji($avatarKey) {
    $options = sfAvatarOptions();
    return $avatarKey && isset($options[$avatarKey]) ? $options[$avatarKey] : null;
}

/**
 * Deterministic, readable-on-dark-blue color per user, so everyone's name
 * stands out from everyone else's in group chat without needing to store
 * a color preference anywhere.
 */
function sfNameColor($userId) {
    $palette = ['#7dd3fc', '#c4b5fd', '#86efac', '#fca5a5', '#f0abfc', '#67e8f9', '#fdba74', '#a5b4fc'];
    return $palette[((int) $userId) % count($palette)];
}
