<?php

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_render(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $class = $flash['type'] === 'error' ? 'alert alert-error' : 'alert alert-success';
    echo '<div class="' . $class . '">' . h($flash['message']) . '</div>';
}
