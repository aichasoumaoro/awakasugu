<?php
// ============================================
// DÉTAIL D'UNE COMMANDE - Awa Ka Sugu
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

// Vérifier si le client est connecté
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
    die("Erreur de connexion : " . $e->getMessage());
}

$commande_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($commande_id <= 0) {
    header('Location: mes_commandes.php');
    exit;
}

// Récupérer la commande
$stmt = $pdo->prepare("
    SELECT * FROM commandes 
    WHERE id = ? AND client_id = ?
");
$stmt->execute([$commande_id, $_SESSION['client_id']]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: mes_commandes.php');
    exit;
}

// Récupérer les détails
$stmt = $pdo->prepare("SELECT * FROM details_commande WHERE commande_id = ?");
$stmt->execute([$commande_id]);
$details = $stmt->fetchAll();

$titre_page = 'Détail commande';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
.commande-container { max-width: 950px; margin: 40px auto; padding: 0 20px; }
.commande-header {
    background: linear-gradient(135deg, #0D0D0D, #1A1A1A);
    border-radius: 20px;
    padding: 30px 35px;
    margin-bottom: 30px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    border: 1px solid rgba(200,146,42,0.15);
    position: relative;
    overflow: hidden;
}
.commande-header::after {
    content: '📦';
    position: absolute;
    right: -10px;
    top: -10px;
    font-size: 80px;
    opacity: 0.05;
}
.commande-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem;
    color: #C8922A;
    margin-bottom: 4px;
}
.commande-header p {
    color: rgba(255,255,255,0.5);
    font-size: 0.85rem;
}
.commande-header .badge-statut {
    padding: 8px 20px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 0.85rem;
}
.card-commande { 
    background: white; 
    border-radius: 20px; 
    padding: 35px; 
    border: 1px solid #F0EDEA;
}
.section-title { 
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem; 
    font-weight: 600;
    margin: 25px 0 15px; 
    padding-bottom: 12px; 
    border-bottom: 2px solid rgba(200,146,42,0.2);
    color: #0D0D0D;
}
.section-title i {
    color: #C8922A;
    margin-right: 10px;
}
.info-grid { 
    display: grid; 
    grid-template-columns: repeat(2, 1fr); 
    gap: 15px; 
    margin-bottom: 10px;
}
.info-item { 
    background: #F8F9FA;
    padding: 12px 16px;
    border-radius: 12px;
}
.info-item label { 
    font-weight: 600; 
    color: #8A99AA; 
    display: block; 
    margin-bottom: 4px;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.info-item p {
    margin: 0;
    font-size: 0.95rem;
    color: #1A2C3E;
}
.badge-statut {
    display: inline-block;
    padding: 6px 18px;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 600;
}
.statut-en_attente { background: #FFF3CD; color: #856404; }
.statut-confirmee { background: #D1ECF1; color: #0C5460; }
.statut-en_preparation { background: #CCE5FF; color: #004085; }
.statut-en_livraison { background: #E8D5F5; color: #6A1B9A; }
.statut-livree { background: #D4EDDA; color: #155724; }
.statut-annulee { background: #F8D7DA; color: #721C24; }
.table-produits { 
    width: 100%; 
    border-collapse: collapse; 
    margin-top: 10px;
}
.table-produits th, 
.table-produits td { 
    padding: 12px 15px; 
    text-align: left; 
    border-bottom: 1px solid #F0EDEA; 
}
.table-produits th { 
    background: #F8F9FA; 
    color: #C8922A;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.table-produits tr:hover td {
    background: #FEFBF5;
}
.total-ligne {
    text-align: right;
    padding-top: 15px;
    border-top: 2px solid rgba(200,146,42,0.2);
}
.total-ligne span {
    font-family: 'Playfair Display', serif;
    color: #C8922A;
    font-size: 1.5rem;
    font-weight: 700;
}
.btn-retour {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #0D0D0D;
    color: white;
    padding: 12px 25px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    margin-top: 25px;
}
.btn-retour:hover {
    background: #C8922A;
    color: white;
    transform: translateX(-5px);
}
.paiement-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    background: rgba(200,146,42,0.1);
    color: #C8922A;
}
@media (max-width: 768px) { 
    .info-grid { grid-template-columns: 1fr; }
    .commande-header { flex-direction: column; text-align: center; }
    .card-commande { padding: 20px; }
    .table-produits th, .table-produits td { padding: 8px 10px; font-size: 0.8rem; }
}
</style>

<div class="commande-container">
    
    <div class="commande-header">
        <div>
            <h1>📦 Commande #<?= htmlspecialchars($commande['numero_commande'] ?? '#'.str_pad($commande['id'], 6, '0', STR_PAD_LEFT)) ?></h1>
            <p>Passée le <?= date('d/m/Y à H:i', strtotime($commande['created_at'] ?? 'now')) ?></p>
        </div>
        <div>
            <?php 
            $statuts = [
                'en_attente' => ['label' => 'En attente', 'class' => 'statut-en_attente'],
                'confirmee' => ['label' => 'Confirmée', 'class' => 'statut-confirmee'],
                'en_preparation' => ['label' => 'En préparation', 'class' => 'statut-en_preparation'],
                'en_livraison' => ['label' => 'En livraison', 'class' => 'statut-en_livraison'],
                'livree' => ['label' => 'Livrée', 'class' => 'statut-livree'],
                'annulee' => ['label' => 'Annulée', 'class' => 'statut-annulee']
            ];
            $statut_key = $commande['statut'] ?? 'en_attente';
            $statut_info = $statuts[$statut_key] ?? ['label' => $statut_key, 'class' => 'statut-en_attente'];
            ?>
            <span class="badge-statut <?= $statut_info['class'] ?>">
                <?= $statut_info['label'] ?>
            </span>
        </div>
    </div>
    
    <div class="card-commande">
        
        <h3 class="section-title"><i class="bi bi-info-circle"></i> Informations</h3>
        <div class="info-grid">
            <div class="info-item">
                <label>Statut</label>
                <p><span class="badge-statut <?= $statut_info['class'] ?>" style="font-size:0.8rem;"><?= $statut_info['label'] ?></span></p>
            </div>
            <div class="info-item">
                <label>Mode de paiement</label>
                <p>
                    <?php 
                    $modes = [
                        'livraison' => '💵 Paiement à la livraison',
                        'orange_money' => '🟠 Orange Money',
                        'wave' => '🌊 Wave',
                        'moov_money' => '📱 Moov Money',
                        'carte' => '💳 Carte bancaire',
                        'especes' => '💰 Espèces'
                    ];
                    $mode = $commande['mode_paiement'] ?? 'livraison';
                    echo $modes[$mode] ?? $mode;
                    ?>
                </p>
            </div>
            <div class="info-item">
                <label>Nom complet</label>
                <p><?= htmlspecialchars($commande['nom_client'] ?? '') ?></p>
            </div>
            <div class="info-item">
                <label>Téléphone</label>
                <p><?= htmlspecialchars($commande['telephone_client'] ?? $commande['telephone'] ?? '') ?></p>
            </div>
            <div class="info-item" style="grid-column: 1 / -1;">
                <label>Adresse de livraison</label>
                <p><?= nl2br(htmlspecialchars($commande['adresse_livraison'] ?? '')) ?></p>
            </div>
        </div>
        
        <h3 class="section-title"><i class="bi bi-bag"></i> Articles commandés</h3>
        
        <?php if(empty($details)): ?>
            <div style="text-align:center;padding:30px;color:#8A99AA;">
                <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                <p>Aucun détail disponible</p>
            </div>
        <?php else: ?>
            <table class="table-produits">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th style="text-align:center;">Qté</th>
                        <th style="text-align:right;">Prix unitaire</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($details as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['nom_produit'] ?? $d['nom'] ?? '') ?></td>
                        <td style="text-align:center;"><?= $d['quantite'] ?></td>
                        <td style="text-align:right;"><?= number_format($d['prix_unitaire'] ?? 0, 0, ',', ' ') ?> FCFA</td>
                        <td style="text-align:right;font-weight:600;color:#C8922A;">
                            <?= number_format(($d['prix_unitaire'] ?? 0) * ($d['quantite'] ?? 0), 0, ',', ' ') ?> FCFA
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="total-ligne">
            <span>Total : <?= number_format($commande['total'] ?? 0, 0, ',', ' ') ?> FCFA</span>
        </div>
        
        <a href="mes_commandes.php" class="btn-retour">
            <i class="bi bi-arrow-left"></i> Retour à mes commandes
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>