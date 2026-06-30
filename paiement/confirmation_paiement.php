<?php
// ============================================
// CONFIRMATION DE PAIEMENT - Awa Ka Sugu
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

// ============================================
// INCLURE LA CONFIGURATION ET L'ENVOI D'EMAIL
// ============================================
require_once '../includes/config.php';
require_once '../includes/envoi_email.php';

$commande_id = isset($_GET['commande_id']) ? (int)$_GET['commande_id'] : 0;
$statut = isset($_GET['statut']) ? $_GET['statut'] : '';

if ($statut != 'success' || $commande_id == 0) {
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

// Mettre à jour le statut de la commande
$stmt = $pdo->prepare("UPDATE commandes SET statut = 'confirmee' WHERE id = ?");
$stmt->execute([$commande_id]);

// Mettre à jour le statut de la facture
$stmt = $pdo->prepare("UPDATE factures SET statut_paiement = 'payee' WHERE commande_id = ?");
$stmt->execute([$commande_id]);

// ============================================
// ENVOI DE L'EMAIL DE CONFIRMATION AVEC BREVO
// ============================================
$email = $commande['email_client'] ?? $commande['email'] ?? '';

if (!empty($email)) {
    $sujet = "✅ Paiement confirmé - Commande Awa Ka Sugu N° " . $commande['numero_commande'];
    
    $message_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Paiement confirmé</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; margin: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #0D0D0D, #1A1A1A); padding: 30px; text-align: center; border-bottom: 3px solid #C8922A; }
            .header h1 { font-family: "Georgia", serif; color: #C8922A; margin: 0; font-size: 1.8rem; letter-spacing: 3px; }
            .header p { color: rgba(255,255,255,0.4); margin: 5px 0 0; font-size: 0.8rem; }
            .content { padding: 30px; }
            .content h2 { font-size: 1.2rem; color: #0D0D0D; margin-bottom: 10px; }
            .content .sub { color: #666; font-size: 0.9rem; margin-bottom: 20px; }
            .info-box { background: #F8F9FA; padding: 15px 20px; border-radius: 10px; margin: 15px 0; border-left: 4px solid #27AE60; }
            .info-box p { margin: 5px 0; font-size: 0.9rem; color: #333; }
            .info-box strong { color: #0D0D0D; }
            .info-box .total { font-size: 1.3rem; font-weight: 700; color: #27AE60; text-align: right; margin-top: 10px; padding-top: 10px; border-top: 2px solid #27AE60; }
            .footer { background: #F8F9FA; padding: 20px; text-align: center; color: #8A99AA; font-size: 0.8rem; border-top: 1px solid #E8ECF0; }
            .badge-success { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; background: #D4EDDA; color: #155724; }
            .btn { display: inline-block; background: #C8922A; color: white; padding: 10px 25px; border-radius: 30px; text-decoration: none; margin-top: 15px; }
            .btn:hover { background: #9A6E1A; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>✦ AWA KA SUGU ✦</h1>
                <p>Boutique IBA Design & Restaurant Sofia</p>
            </div>
            <div class="content">
                <h2>Bonjour ' . htmlspecialchars($commande['nom_client']) . ' !</h2>
                <p class="sub">Votre paiement a été confirmé avec succès.</p>
                
                <div class="info-box">
                    <p><strong>📦 Numéro de commande :</strong> <span style="color:#C8922A;font-weight:700;">' . $commande['numero_commande'] . '</span></p>
                    <p><strong>💳 Mode de paiement :</strong> ' . ucfirst(str_replace('_', ' ', $commande['mode_paiement'] ?? 'Orange Money')) . '</p>
                    <p><strong>✅ Statut :</strong> <span class="badge-success">Payée</span></p>
                    <div class="total">💰 ' . number_format($commande['total'], 0, ',', ' ') . ' FCFA</div>
                </div>
                
                <p style="color:#666;font-size:0.85rem;">Votre commande est en cours de préparation.</p>
                
                <p style="text-align:center;">
                    <a href="' . SITE_URL . '/boutique/suivi.php" class="btn">📦 Suivre ma commande</a>
                </p>
            </div>
            <div class="footer">
                <p>Awa Ka Sugu &copy; ' . date('Y') . ' - Tous droits réservés</p>
                <p style="font-size:0.7rem;">Cet email est généré automatiquement, merci de ne pas y répondre.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Envoyer l'email avec Brevo
    envoyerEmail($email, $sujet, $message_html);
}

// ============================================
// AFFICHAGE DE LA PAGE DE CONFIRMATION
// ============================================
$titre_page = 'Paiement confirmé - Awa Ka Sugu';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
.paiement-container {
    max-width: 600px;
    margin: 60px auto;
    padding: 0 20px;
}
.paiement-card {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    border: 1px solid rgba(200,146,42,0.08);
    text-align: center;
}
.paiement-card .check-icon {
    width: 80px;
    height: 80px;
    background: #D4EDDA;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}
.paiement-card .check-icon i {
    font-size: 3rem;
    color: #28A745;
}
.paiement-card h2 {
    font-family: 'Playfair Display', serif;
    color: #0D0D0D;
    margin-bottom: 10px;
}
.paiement-card .sub {
    color: #8A99AA;
    font-size: 0.95rem;
    margin-bottom: 20px;
}
.paiement-card .info-alert {
    background: #F8F9FA;
    border-radius: 12px;
    padding: 15px 20px;
    margin: 20px 0;
    border-left: 4px solid #C8922A;
    text-align: left;
}
.paiement-card .info-alert p {
    margin: 5px 0;
    font-size: 0.9rem;
}
.paiement-card .info-alert strong {
    color: #0D0D0D;
}
.paiement-card .info-alert .total {
    font-size: 1.2rem;
    font-weight: 700;
    color: #C8922A;
}
.btn-continuer {
    display: inline-block;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    margin-top: 10px;
}
.btn-continuer:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
}
</style>

<div class="paiement-container">
    <div class="paiement-card">
        <div class="check-icon">
            <i class="bi bi-check-lg"></i>
        </div>
        
        <h2>✅ Paiement réussi !</h2>
        <p class="sub">Merci pour votre confiance, <?= htmlspecialchars($commande['nom_client']) ?>.</p>
        
        <div class="info-alert">
            <p><strong>📦 Commande n° :</strong> <?= htmlspecialchars($commande['numero_commande']) ?></p>
            <p><strong>💳 Mode de paiement :</strong> <?= ucfirst(str_replace('_', ' ', $commande['mode_paiement'] ?? 'Orange Money')) ?></p>
            <p><strong>📅 Date :</strong> <?= date('d/m/Y à H:i', strtotime($commande['created_at'])) ?></p>
            <p><strong>💰 Montant :</strong> <span class="total"><?= number_format($commande['total'], 0, ',', ' ') ?> FCFA</span></p>
        </div>
        
        <p style="color:#8A99AA;font-size:0.9rem;">
            <i class="bi bi-envelope"></i> Un email de confirmation vous a été envoyé.
        </p>
        
        <p style="color:#8A99AA;font-size:0.9rem;">
            <i class="bi bi-truck"></i> Votre commande sera livrée sous 24h-48h à Bamako.
        </p>
        
        <a href="../boutique/catalogue.php" class="btn-continuer">
            <i class="bi bi-arrow-left"></i> Continuer mes achats
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>