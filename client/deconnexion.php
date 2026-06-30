<?php
// ============================================
// DÉCONNEXION CLIENT - SESSION PUBLIQUE SÉPARÉE
// ============================================

// Démarrer la session publique
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// INCLURE LES FONCTIONS DU PANIER
// ============================================
require_once '../includes/config.php';
require_once '../includes/panier_fonctions.php';

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// ✅ Sauvegarder le panier en BDD avant de se déconnecter
if (isset($_SESSION['client_id']) && isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
    sauvegarderPanierClient($_SESSION['client_id'], $_SESSION['panier'], $pdo);
}

// Détruire la session client
$_SESSION = [];
session_destroy();

// ⚠️ Supprimer aussi le cookie côté navigateur
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

// Si on vient du bandeau admin, retourner vers le dashboard admin
if (isset($_GET['retour']) && $_GET['retour'] === 'admin') {
    header('Location: ../admin/dashboard.php');
    exit;
}

// Rediriger vers la page de connexion
header('Location: connexion.php');
exit;
?>