<?php
// session_handler.php

// --- Configurações de Segurança de Cookies ---

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
ini_set('session.cookie_secure', $isHttps ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');

// --- Nome da Sessão (opcional, mas melhora segurança) ---
// session_name('AudioTOSessionID');

// --- Iniciar Sessão se ainda não iniciada ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Inicialização de variáveis importantes (Fallback defensivo) ---
if (!isset($_SESSION['user_plano_id']) && isset($_SESSION['id_plano_assinatura_ativo'])) {
    $_SESSION['user_plano_id'] = (int) $_SESSION['id_plano_assinatura_ativo'];
}

// --- Funções Auxiliares de Sessão ---

/**
 * Verifica se o usuário está logado.
 * @return bool
 */
function isUserLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Verifica se o usuário logado é administrador.
 * @return bool
 */
function isAdmin(): bool {
    return ($_SESSION['user_funcao'] ?? '') === 'administrador';
}

/**
 * Redireciona se o usuário não estiver logado.
 * @param string $redirectUrl
 */
function requireLogin(string $redirectUrl = 'login.php'): void {
    if (!isUserLoggedIn()) {
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * Redireciona se o usuário não for administrador.
 * @param string $redirectUrl
 */
function requireAdmin(string $redirectUrl = 'login.php'): void {
    if (!isUserLoggedIn()) {
        header("Location: $redirectUrl");
        exit;
    }

    if (!isAdmin()) {
        header("Location: inicio.php");
        exit;
    }
}
?>
