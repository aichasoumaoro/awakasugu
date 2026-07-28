<?php
session_name('PUBLIC_SESSION');
session_start();

if (!isset($_SESSION['client_id'])) {
    header('Location: connexion.php');
    exit;
}

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

if (isset($_POST['produit_id'])) {
    $client_id = $_SESSION['client_id'];
    $produit_id = (int)$_POST['produit_id'];
    
    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE client_id = ? AND produit_id = ?");
    $stmt->execute([$client_id, $produit_id]);
}

header('Location: ma_wishlist.php?msg=supprime');
exit;