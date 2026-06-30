<?php
// ============================================
// SESSION ADMIN SÉPARÉE — DÉCONNEXION COMPLÈTE
// ============================================
session_name('ADMIN_SESSION');
session_start();

// Vider les données de session
$_SESSION = [];

// Détruire les données côté serveur
session_destroy();

// ⚠️ IMPORTANT : session_destroy() ne supprime PAS le cookie
// dans le navigateur. On doit le faire manuellement, sinon le
// cookie ADMIN_SESSION reste vivant dans Chrome/Edge même après
// déconnexion, et peut redonner accès à l'admin par erreur.
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