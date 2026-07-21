<?php
// ============================================
// CONFIRMATION DE PAIEMENT - Awa Ka Sugu
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';
require_once '../includes/config.php';
require_once '../includes/envoi_email.php';

$commande_id = isset($_GET['commande_id']) ? (int)$_GET['commande_id'] : 0;
$statut = isset($_GET['statut']) ? $_GET['statut'] : '';

if ($commande_id == 0) {
    header('Location: ../boutique/catalogue.php');
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

// Récupérer la commande
$stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: ../boutique/catalogue.php');
    exit;
}

$titre_page = 'Confirmation paiement - Awa Ka Sugu';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation paiement - Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #F8F6F3; font-family: 'Jost', sans-serif; }
        .confirmation-container { max-width: 600px; margin: 60px auto; padding: 0 20px; }
        .confirmation-card { background: white; border-radius: 20px; padding: 40px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.06); border: 1px solid #F0EDEA; }
        .icon-waiting { font-size: 4rem; color: #E67E22; display: block; margin-bottom: 15px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        .icon-success { font-size: 4rem; color: #27AE60; display: block; margin-bottom: 15px; }
        .numero-commande { color: #C8922A; font-weight: 700; font-size: 1.2rem; }
        .btn-retour { display: inline-block; background: #C8922A; color: white; padding: 12px 30px; border-radius: 30px; text-decoration: none; font-weight: 600; transition: all 0.3s; margin-top: 20px; }
        .btn-retour:hover { background: #9A6E1A; }
        .info-box { background: #FEFBF5; border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid rgba(200,146,42,0.1); text-align: left; }
        .info-box .label { color: #8A99AA; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .info-box .value { font-weight: 600; color: #1A2C3E; }
        .info-box .row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #F0F2F5; }
        .info-box .row:last-child { border-bottom: none; }
    </style>
</head>
<body>

<div class="confirmation-container">
    <div class="confirmation-card">
        <?php if($statut == 'success'): ?>
            <span class="icon-success"><i class="bi bi-check-circle-fill"></i></span>
            <h2 style="font-family:'Playfair Display',serif;">✅ Paiement confirmé !</h2>
            <p style="color:#8A99AA;">Votre commande a été confirmée avec succès.</p>
        <?php else: ?>
            <span class="icon-waiting"><i class="bi bi-clock-fill"></i></span>
            <h2 style="font-family:'Playfair Display',serif;">⏳ Paiement en attente</h2>
            <p style="color:#8A99AA;">Votre paiement a été enregistré et sera vérifié par notre équipe.</p>
        <?php endif; ?>
        
        <div class="info-box">
            <div class="row">
                <span class="label">Commande</span>
                <span class="value">#<?= htmlspecialchars($commande['numero_commande']) ?></span>
            </div>
            <div class="row">
                <span class="label">Montant</span>
                <span class="value" style="color:#C8922A;"><?= number_format($commande['total'], 0, ',', ' ') ?> FCFA</span>
            </div>
            <div class="row">
                <span class="label">Date</span>
                <span class="value"><?= date('d/m/Y H:i', strtotime($commande['created_at'])) ?></span>
            </div>
            <?php if($statut != 'success'): ?>
            <div class="row">
                <span class="label">Mode de paiement</span>
                <span class="value"><?= ucfirst(str_replace('_', ' ', $commande['mode_paiement'] ?? 'Orange Money')) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if($statut != 'success'): ?>
        <p style="color:#8A99AA;font-size:0.85rem;margin:10px 0;">
            <i class="bi bi-info-circle" style="color:#C8922A;"></i>
            Vous serez notifié dès que votre paiement sera confirmé.
        </p>
        <?php endif; ?>
        
        <a href="../index.php" class="btn-retour">
            <i class="bi bi-house"></i> Retour à l'accueil
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>