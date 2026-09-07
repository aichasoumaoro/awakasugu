<?php
// ============================================
// DÉCONNEXION ADMIN - AWA KA SUGU
// ============================================

// Inclure la configuration de session
require_once '../includes/session_config.php';

// Déconnecter l'admin (fonction définie dans session_config.php)
logoutAdmin();

// Supprimer le cookie de session admin manuellement
// (au cas où la fonction logoutAdmin() ne le ferait pas)
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

// Rediriger vers la page de login
header('Location: login.php');
exit;
?>