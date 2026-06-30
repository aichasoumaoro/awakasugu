<?php
// ============================================
// COMMANDE DIRECTE - Awa Ka Sugu
// Achat direct sans passer par le panier
// ============================================

// ============================================
// SESSION PUBLIQUE SÉPARÉE
// ============================================
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// VÉRIFICATION MAINTENANCE
// ============================================
require_once '../includes/maintenance_check.php';

$produit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($produit_id <= 0) {
    header('Location: catalogue.php');
    exit;
}

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Récupérer le produit
$stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND est_visible = 1");
$stmt->execute([$produit_id]);
$produit = $stmt->fetch();

if (!$produit) {
    header('Location: catalogue.php');
    exit;
}

// ============================================
// UTILISATION DU PANIER AVEC SESSION SÉPARÉE
// ============================================
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// Ajouter directement le produit au panier avec quantité 1
$_SESSION['panier'][$produit_id] = [
    'id' => $produit['id'],
    'nom' => $produit['nom'],
    'prix' => $produit['prix_promo'] ?: $produit['prix'],
    'quantite' => 1,
    'image' => $produit['image_principale']
];

// Rediriger vers la page de commande
header('Location: commande.php');
exit;
?>