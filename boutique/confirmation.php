<?php
// ============================================
// PAGE DE CONFIRMATION DE COMMANDE - Awa Ka Sugu
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

$numero_commande = isset($_GET['numero']) ? $_GET['numero'] : '';

if (empty($numero_commande)) {
    header('Location: catalogue.php');
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
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer la commande
$stmt = $pdo->prepare("SELECT * FROM commandes WHERE numero_commande = ?");
$stmt->execute([$numero_commande]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: catalogue.php');
    exit;
}

// Récupérer les détails
$stmt = $pdo->prepare("SELECT * FROM details_commande WHERE commande_id = ?");
$stmt->execute([$commande['id']]);
$details = $stmt->fetchAll();

// Récupérer le message de succès de la session
$success_msg = isset($_SESSION['commande_success']) ? $_SESSION['commande_success'] : null;
unset($_SESSION['commande_success']);

$titre_page = 'Confirmation - Awa Ka Sugu';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation - Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Jost:wght@300;400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Jost', sans-serif; 
            background: #F8F7F5; 
            color: #1A1A1A;
        }
        
        .banner {
            background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
            padding: 50px 20px 40px;
            text-align: center;
        }
        .banner h1 {
            font-size: 2.2rem;
            color: #C8922A;
            font-weight: 700;
        }
        .banner p {
            color: rgba(255,255,255,0.5);
            margin-top: 8px;
        }
        
        .confirmation-container { 
            max-width: 800px; 
            margin: 40px auto; 
            padding: 0 20px; 
        }
        .confirmation-card { 
            background: white; 
            border-radius: 20px; 
            padding: 40px; 
            text-align: center; 
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            border: 1px solid rgba(200,146,42,0.1);
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .check-icon { 
            width: 80px; 
            height: 80px; 
            background: #D4EDDA; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 0 auto 20px;
            animation: popIn 0.6s ease-out;
        }
        @keyframes popIn {
            0% { transform: scale(0); opacity: 0; }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        .check-icon i { 
            font-size: 3rem; 
            color: #28A745; 
        }
        .confirmation-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: #1A1A1A;
            margin-bottom: 10px;
        }
        .confirmation-card .sub-text {
            color: #8A99AA;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }
        .info-box { 
            background: #F8F9FA; 
            border-radius: 12px; 
            padding: 20px 25px; 
            text-align: left; 
            margin: 20px 0;
            border-left: 3px solid #C8922A;
        }
        .info-box h5 {
            font-family: 'Playfair Display', serif;
            color: #C8922A;
            margin-bottom: 15px;
        }
        .info-box p {
            margin-bottom: 6px;
            font-size: 0.9rem;
            color: #333;
        }
        .info-box strong {
            color: #0D0D0D;
        }
        .info-box .total-amount {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            color: #C8922A;
            font-weight: 700;
        }
        
        .table-produits {
            width: 100%;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        .table-produits th {
            color: #C8922A;
            font-weight: 600;
            border-bottom: 2px solid rgba(200,146,42,0.2);
            padding: 8px 5px;
        }
        .table-produits td {
            padding: 8px 5px;
            border-bottom: 1px solid #F0F2F5;
        }
        .table-produits tr:last-child td {
            border-bottom: none;
        }
        
        .alert-email {
            background: #E8F4FD;
            border-left: 4px solid #2980B9;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }
        .alert-email i {
            color: #2980B9;
            margin-right: 10px;
        }
        .alert-email strong {
            color: #1A3A5C;
        }
        
        .btn-continuer { 
            background: linear-gradient(135deg, #C8922A, #E8B55A); 
            color: white; 
            padding: 12px 35px; 
            border-radius: 30px; 
            text-decoration: none; 
            font-weight: 600; 
            display: inline-block; 
            transition: all 0.3s;
            border: none;
            font-size: 0.95rem;
        }
        .btn-continuer:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 20px rgba(200,146,42,0.3);
            color: white;
        }
        .btn-continuer i {
            margin-right: 8px;
        }
        
        .paiement-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            background: rgba(200,146,42,0.1);
            color: #C8922A;
        }
        
        .info-delivery {
            background: #FEFBF5;
            border-radius: 12px;
            padding: 15px 20px;
            margin: 15px 0;
            border: 1px solid rgba(200,146,42,0.1);
        }
        .info-delivery i {
            color: #C8922A;
            margin-right: 8px;
        }
        
        /* Message de succès */
        .success-message {
            background: #D4EDDA;
            border: 1px solid #C3E6CB;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            color: #155724;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeInUp 0.5s ease-out;
        }
        .success-message i {
            font-size: 1.5rem;
        }
        
        .email-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .email-sent {
            background: #D4EDDA;
            color: #155724;
        }
        .email-failed {
            background: #F8D7DA;
            color: #721C24;
        }
        
        @media (max-width: 600px) {
            .confirmation-card { padding: 25px 20px; }
            .info-box { padding: 15px; }
            .table-produits { font-size: 0.75rem; }
            .confirmation-card h2 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<div class="banner">
    <h1>🎉 Commande confirmée !</h1>
    <p>Merci pour votre confiance, <?= htmlspecialchars($commande['nom_client'] ?? '') ?></p>
</div>

<div class="confirmation-container">
    <div class="confirmation-card">
        <div class="check-icon">
            <i class="bi bi-check-lg"></i>
        </div>
        
        <h2>✅ Commande confirmée !</h2>
        <p class="sub-text">Votre commande a été enregistrée avec succès.</p>
        
        <!-- Message de succès -->
        <?php if($success_msg): ?>
        <div class="success-message">
            <i class="bi bi-check-circle-fill"></i>
            <div style="text-align:left;">
                <strong>Commande #<?= htmlspecialchars($success_msg['numero']) ?></strong><br>
                <span>Merci <?= htmlspecialchars($success_msg['nom']) ?>, votre commande a été enregistrée.</span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h5><i class="bi bi-receipt"></i> Détails de votre commande</h5>
            <p><strong>📦 Numéro de commande :</strong> <?= htmlspecialchars($commande['numero_commande'] ?? '') ?></p>
            <p><strong>📅 Date :</strong> <?= date('d/m/Y à H:i', strtotime($commande['created_at'] ?? 'now')) ?></p>
            <p><strong>👤 Client :</strong> <?= htmlspecialchars($commande['nom_client'] ?? '') ?></p>
            <p><strong>📞 Téléphone :</strong> <?= htmlspecialchars($commande['telephone'] ?? 'Non renseigné') ?></p>
            <p><strong>📍 Adresse de livraison :</strong> <?= nl2br(htmlspecialchars($commande['adresse_livraison'] ?? '')) ?></p>
            <p><strong>💰 Mode de paiement :</strong> 
                <?php 
                $paiements = [
                    'livraison' => '💵 Paiement à la livraison',
                    'orange_money' => '🟠 Orange Money',
                    'wave' => '🌊 Wave',
                    'moov_money' => '📱 Moov Money',
                    'carte' => '💳 Carte bancaire',
                    'especes' => '💰 Espèces'
                ];
                $mode = $commande['mode_paiement'] ?? 'livraison';
                echo '<span class="paiement-badge">' . ($paiements[$mode] ?? $mode) . '</span>';
                ?>
            </p>
            <p><strong>💰 Montant total :</strong> <span class="total-amount"><?= number_format($commande['total'] ?? 0, 0, ',', ' ') ?> FCFA</span></p>
            
            <hr style="border-color: rgba(200,146,42,0.15); margin: 15px 0;">
            
            <h6 style="font-weight:700;color:#0D0D0D;margin-bottom:10px;">🛍️ Articles commandés :</h6>
            <table class="table-produits">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th style="text-align:center;">Qté</th>
                        <th style="text-align:right;">Prix</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($details as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['nom_produit'] ?? '') ?></td>
                        <td style="text-align:center;"><?= $d['quantite'] ?? 0 ?></td>
                        <td style="text-align:right;"><?= number_format($d['prix_unitaire'] ?? 0, 0, ',', ' ') ?> F</td>
                        <td style="text-align:right;font-weight:600;color:#C8922A;">
                            <?= number_format(($d['prix_unitaire'] ?? 0) * ($d['quantite'] ?? 0), 0, ',', ' ') ?> F
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="alert-email">
            <i class="bi bi-envelope-fill"></i> 
            <strong>Un email de confirmation vous a été envoyé.</strong><br>
            <small style="color:#666;">Vérifiez votre boîte de réception (pensez à vérifier les spams).</small>
            <br>
            <?php if(isset($success_msg['email_envoye']) && $success_msg['email_envoye']): ?>
                <span class="email-status email-sent">
                    <i class="bi bi-check-circle-fill"></i> Email envoyé
                </span>
            <?php else: ?>
                <span class="email-status email-failed">
                    <i class="bi bi-exclamation-triangle-fill"></i> Envoi en cours
                </span>
            <?php endif; ?>
        </div>
        
        <div class="info-delivery">
            <i class="bi bi-clock-history"></i>
            <strong>Traitement :</strong> Votre commande sera traitée dans les 24h.<br>
            <i class="bi bi-truck"></i>
            <strong>Livraison :</strong> Livraison express partout à Bamako.
        </div>
        
        <a href="catalogue.php" class="btn-continuer">
            <i class="bi bi-arrow-left"></i> Continuer mes achats
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>