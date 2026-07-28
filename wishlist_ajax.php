<?php
// ============================================
// AJAX WISHLIST - Gestion des favoris
// ============================================
session_name('PUBLIC_SESSION');
session_start();

header('Content-Type: application/json');

// Vérifier si le client est connecté
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter', 'redirect' => 'connexion.php']);
    exit;
}

$client_id = $_SESSION['client_id'];
$action = $_POST['action'] ?? '';
$produit_id = isset($_POST['produit_id']) ? (int)$_POST['produit_id'] : 0;

if (empty($action) || $produit_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

// Connexion à la base de données
$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

if ($action === 'add') {
    // Ajouter à la wishlist
    try {
        // Vérifier si déjà présent
        $check = $pdo->prepare("SELECT id FROM wishlist WHERE client_id = ? AND produit_id = ?");
        $check->execute([$client_id, $produit_id]);
        
        if ($check->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO wishlist (client_id, produit_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$client_id, $produit_id]);
            echo json_encode(['success' => true, 'message' => 'Ajouté aux favoris', 'action' => 'add']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Déjà dans vos favoris', 'action' => 'add']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout: ' . $e->getMessage()]);
    }
} elseif ($action === 'remove') {
    // Retirer de la wishlist
    try {
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE client_id = ? AND produit_id = ?");
        $stmt->execute([$client_id, $produit_id]);
        echo json_encode(['success' => true, 'message' => 'Retiré des favoris', 'action' => 'remove']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}