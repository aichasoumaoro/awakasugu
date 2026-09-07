<?php
// ============================================
// CONFIGURATION SESSION - AWA KA SUGU
// ============================================

// ============================================
// 1. SESSION ADMIN
// ============================================

function initAdminSession() {
    session_name('ADMIN_SESSION');
    
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['admin_created'])) {
        session_regenerate_id(true);
        $_SESSION['admin_created'] = time();
        $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['admin_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    // Vérification IP
    if (isset($_SESSION['admin_ip']) && $_SESSION['admin_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        session_unset();
        session_destroy();
        return false;
    }
    
    // Expiration 1 heure
    if (isset($_SESSION['admin_created']) && (time() - $_SESSION['admin_created'] > 3600)) {
        session_unset();
        session_destroy();
        return false;
    }
    
    return true;
}

/**
 * Vérifie si l'admin est connecté - VERSION SIMPLIFIÉE ET FIABLE
 */
function isAdminLoggedIn() {
    // Sauvegarder la session actuelle
    $current_session_name = session_name();
    $current_session_id = session_id();
    
    // Basculer vers la session admin
    if ($current_session_name !== 'ADMIN_SESSION') {
        session_write_close();
        session_name('ADMIN_SESSION');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    // ✅ Vérification simple et fiable
    $is_logged = isset($_SESSION['admin_id']) && 
                 isset($_SESSION['admin_logged_in']) && 
                 $_SESSION['admin_logged_in'] === true;
    
    // ✅ Si connecté, on garde les infos dans une variable globale
    if ($is_logged) {
        $GLOBALS['admin_info'] = [
            'id' => $_SESSION['admin_id'] ?? null,
            'nom' => $_SESSION['admin_nom'] ?? 'Admin',
            'email' => $_SESSION['admin_email'] ?? '',
            'role' => $_SESSION['admin_role'] ?? 'admin'
        ];
    }
    
    // Restaurer la session précédente
    if ($current_session_name !== 'ADMIN_SESSION' && $current_session_name !== '') {
        session_write_close();
        session_name($current_session_name);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    return $is_logged;
}

/**
 * Récupère les infos de l'admin (même depuis le site public)
 */
function getAdminInfo() {
    // Si on a déjà les infos dans la variable globale
    if (isset($GLOBALS['admin_info'])) {
        return $GLOBALS['admin_info'];
    }
    
    // Sinon, vérifier la session
    $current_session_name = session_name();
    $current_session_id = session_id();
    
    if ($current_session_name !== 'ADMIN_SESSION') {
        session_write_close();
        session_name('ADMIN_SESSION');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    $info = null;
    if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $info = [
            'id' => $_SESSION['admin_id'] ?? null,
            'nom' => $_SESSION['admin_nom'] ?? 'Admin',
            'email' => $_SESSION['admin_email'] ?? '',
            'role' => $_SESSION['admin_role'] ?? 'admin'
        ];
        $GLOBALS['admin_info'] = $info;
    }
    
    if ($current_session_name !== 'ADMIN_SESSION' && $current_session_name !== '') {
        session_write_close();
        session_name($current_session_name);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    return $info;
}

function getAdminRole() {
    $admin = getAdminInfo();
    return $admin['role'] ?? null;
}

function logoutAdmin() {
    session_name('ADMIN_SESSION');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            'ADMIN_SESSION',
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    $_SESSION = [];
    session_destroy();
    
    // Nettoyer la variable globale
    unset($GLOBALS['admin_info']);
}

// ============================================
// 2. SESSION CLIENT
// ============================================

function initClientSession() {
    session_name('PUBLIC_SESSION');
    
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['client_created'])) {
        session_regenerate_id(true);
        $_SESSION['client_created'] = time();
        $_SESSION['client_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    if (isset($_SESSION['client_ip']) && $_SESSION['client_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        session_unset();
        session_destroy();
        return false;
    }
    
    if (isset($_SESSION['client_created']) && (time() - $_SESSION['client_created'] > 7200)) {
        session_unset();
        session_destroy();
        return false;
    }
    
    return true;
}

function isClientLoggedIn() {
    $current_session_name = session_name();
    $current_session_id = session_id();
    
    if ($current_session_name !== 'PUBLIC_SESSION') {
        session_write_close();
        session_name('PUBLIC_SESSION');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    $is_logged = isset($_SESSION['client_id']) && !empty($_SESSION['client_id']);
    
    if ($current_session_name !== 'PUBLIC_SESSION' && $current_session_name !== '') {
        session_write_close();
        session_name($current_session_name);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    return $is_logged;
}

function getClientInfo() {
    if (!isClientLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['client_id'] ?? null,
        'nom' => $_SESSION['client_nom'] ?? 'Client',
        'email' => $_SESSION['client_email'] ?? '',
        'telephone' => $_SESSION['client_telephone'] ?? ''
    ];
}

function getClientId() {
    return $_SESSION['client_id'] ?? null;
}

function getClientName() {
    return $_SESSION['client_nom'] ?? 'Client';
}

function logoutClient() {
    session_name('PUBLIC_SESSION');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            'PUBLIC_SESSION',
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    $_SESSION = [];
    session_destroy();
}

// ============================================
// 3. COMPATIBILITÉ
// ============================================

function isAdminConnected() {
    return isAdminLoggedIn();
}

// ============================================
// 4. INITIALISATION
// ============================================

$script_path = $_SERVER['SCRIPT_NAME'] ?? '';
$is_admin_path = strpos($script_path, '/admin/') !== false;

if ($is_admin_path) {
    initAdminSession();
} else {
    initClientSession();
}