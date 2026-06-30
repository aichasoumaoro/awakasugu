<?php
// ============================================
// CONFIRMATION COMMANDE REPAS - Restaurant Sofia
// ============================================

$titre_page = 'Confirmation - Restaurant Sofia';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$numero_commande = isset($_GET['numero']) ? $_GET['numero'] : '';

if (empty($numero_commande)) {
    header('Location: menu.php');
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

$stmt = $pdo->prepare("SELECT * FROM commandes_repas WHERE numero_commande = ?");
$stmt->execute([$numero_commande]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: menu.php');
    exit;
}
?>

<style>
.confirmation-hero {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #2A1A0A 100%);
    padding: 40px 0 30px;
    text-align: center;
}
.confirmation-hero h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    color: #C8922A;
}
.confirmation-container {
    max-width: 600px;
    margin: -20px auto 60px;
    padding: 0 20px;
}
.confirmation-card {
    background: white;
    border-radius: 24px;
    padding: 40px;
    text-align: center;
    box-shadow: 0 15px 40px rgba(0,0,0,0.1);
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
}
.check-icon i {
    font-size: 3rem;
    color: #28A745;
}
.confirmation-card h2 {
    font-family: 'Playfair Display', serif;
    color: #0D0D0D;
}
.confirmation-card .info-box {
    background: #F8F9FA;
    border-radius: 12px;
    padding: 15px 20px;
    margin: 20px 0;
    border-left: 4px solid #C8922A;
    text-align: left;
}
.confirmation-card .info-box p {
    margin: 5px 0;
    font-size: 0.9rem;
}
.confirmation-card .info-box strong {
    color: #0D0D0D;
}
.btn-retour-menu {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-retour-menu:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
    color: white;
}
@media (max-width: 600px) {
    .confirmation-card { padding: 25px 20px; }
}
</style>

<div class="confirmation-hero">
    <div class="container">
        <h1>✅ Commande confirmée</h1>
    </div>
</div>

<div class="confirmation-container">
    <div class="confirmation-card">
        <div class="check-icon">
            <i class="bi bi-check-lg"></i>
        </div>
        <h2>Merci pour votre commande !</h2>
        <p style="color:#8A99AA;">Votre commande a été enregistrée avec succès.</p>
        
        <div class="info-box">
            <p><strong>📦 Numéro de commande :</strong> <?= htmlspecialchars($commande['numero_commande']) ?></p>
            <p><strong>📅 Date :</strong> <?= date('d/m/Y à H:i', strtotime($commande['created_at'])) ?></p>
            <p><strong>👤 Client :</strong> <?= htmlspecialchars($commande['nom_client']) ?></p>
            <p><strong>💰 Total :</strong> <?= number_format($commande['total'], 0, ',', ' ') ?> FCFA</p>
            <p><strong>📌 Type :</strong> 
                <?php 
                $types = ['sur_place' => '🍽️ Sur place', 'a_emporter' => '🛍️ À emporter', 'livraison' => '🚚 Livraison'];
                echo $types[$commande['type_commande']] ?? $commande['type_commande'];
                ?>
            </p>
            <p><strong>💳 Paiement :</strong> 
                <?php 
                $paiements = ['sur_place' => '💵 Sur place', 'orange_money' => '🟠 Orange Money', 'wave' => '🌊 Wave', 'moov_money' => '📱 Moov Money'];
                echo $paiements[$commande['mode_paiement']] ?? $commande['mode_paiement'];
                ?>
            </p>
        </div>
        
        <p style="color:#8A99AA;font-size:0.9rem;">
            <i class="bi bi-clock"></i> Votre commande sera prête dans 15-30 minutes.
        </p>
        
        <a href="menu.php" class="btn-retour-menu">
            <i class="bi bi-arrow-left"></i> Retour au menu
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>