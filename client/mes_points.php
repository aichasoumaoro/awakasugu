<?php
// ============================================
// MES POINTS FIDÉLITÉ - Awa Ka Sugu
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

// Vérifier si le client est connecté
if (!isset($_SESSION['client_id'])) {
    header('Location: connexion.php');
    exit;
}

$titre_page = 'Mes points fidélité';
$meta_desc  = 'Consultez vos points fidélité Awa Ka Sugu.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

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

$client_id = $_SESSION['client_id'];

// Récupérer les points du client
$stmt = $pdo->prepare("SELECT * FROM fidelite WHERE client_id = ?");
$stmt->execute([$client_id]);
$fidelite = $stmt->fetch();

if (!$fidelite) {
    // Créer un compte fidélité si inexistant
    $stmt = $pdo->prepare("INSERT INTO fidelite (client_id, points, total_points_gagnes) VALUES (?, 0, 0)");
    $stmt->execute([$client_id]);
    $fidelite = ['points' => 0, 'total_points_gagnes' => 0];
}

// Récupérer l'historique des points
$historique = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM fidelite_historique 
        WHERE client_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$client_id]);
    $historique = $stmt->fetchAll();
} catch(PDOException $e) {
    // La table n'existe pas encore
}

// Récupérer les statistiques des commandes
$commandes_terminees = 0;
$points_potentiels = 0;

try {
    // Commandes boutique terminées
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as total 
        FROM commandes 
        WHERE client_id = ? AND statut IN ('livree', 'terminee', 'confirmee')
    ");
    $stmt->execute([$client_id]);
    $result = $stmt->fetch();
    $commandes_terminees += $result['nb'] ?? 0;
    
    // Commandes repas terminées
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as total 
            FROM commandes_repas 
            WHERE client_id = ? AND statut IN ('livree', 'terminee', 'confirmee')
        ");
        $stmt->execute([$client_id]);
        $result_repas = $stmt->fetch();
        $commandes_terminees += $result_repas['nb'] ?? 0;
    } catch(PDOException $e) {
        // Table commandes_repas n'existe pas ou pas de colonne client_id
    }
    
    // Points potentiels (commandes en attente)
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total), 0) as total 
            FROM commandes 
            WHERE client_id = ? AND statut = 'en_attente'
        ");
        $stmt->execute([$client_id]);
        $result_attente = $stmt->fetch();
        $points_potentiels = floor(($result_attente['total'] ?? 0) / 1000);
    } catch(PDOException $e) {}
    
} catch(PDOException $e) {
    // Ignorer
}

$points = $fidelite['points'] ?? 0;
$total_gagnes = $fidelite['total_points_gagnes'] ?? 0;
$reduction_possible = floor($points / 100) * 1000; // 100 points = 1000 FCFA
?>

<style>
.points-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}
.points-header {
    background: linear-gradient(135deg, #0D0D0D, #1A1A1A);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    border: 1px solid rgba(200,146,42,0.15);
}
.points-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    color: #C8922A;
    margin-bottom: 5px;
}
.points-header p {
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
}
.points-big {
    text-align: center;
}
.points-big .number {
    font-family: 'Playfair Display', serif;
    font-size: 3.5rem;
    font-weight: 700;
    color: #C8922A;
    line-height: 1;
}
.points-big .label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 2px;
}
.points-big .reduction {
    margin-top: 5px;
    font-size: 0.8rem;
    color: #C8922A;
}
.compte-sidebar {
    background: white;
    border-radius: 16px;
    border: 1px solid #F0EDEA;
    overflow: hidden;
    margin-bottom: 30px;
}
.compte-sidebar .menu-item {
    padding: 16px 20px;
    border-bottom: 1px solid #F0EDEA;
    transition: all 0.3s;
}
.compte-sidebar .menu-item a {
    color: #0D0D0D;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.85rem;
}
.compte-sidebar .menu-item i {
    color: #C8922A;
    width: 24px;
    font-size: 1.1rem;
}
.compte-sidebar .menu-item:hover {
    background: #FEFBF5;
}
.compte-sidebar .menu-item.active {
    background: #FEFBF5;
    border-left: 3px solid #C8922A;
}
.compte-sidebar .menu-item.logout a {
    color: #E74C3C;
}
.compte-sidebar .menu-item.logout a i {
    color: #E74C3C;
}
.points-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}
.points-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #F0EDEA;
}
.points-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    color: #0D0D0D;
    margin-bottom: 10px;
}
.points-card h3 i {
    color: #C8922A;
    margin-right: 8px;
}
.points-card .value {
    font-size: 2rem;
    font-weight: 700;
    color: #C8922A;
}
.points-card .value.green {
    color: #27AE60;
}
.points-card .sub {
    color: #8A99AA;
    font-size: 0.85rem;
}
.points-card .progress {
    height: 8px;
    background: #F0EDEA;
    border-radius: 10px;
    margin-top: 15px;
    overflow: hidden;
}
.points-card .progress-bar {
    height: 100%;
    border-radius: 10px;
    background: linear-gradient(90deg, #C8922A, #E8B55A);
}
.points-card .info-text {
    margin-top: 15px;
    padding: 10px;
    background: #FEFBF5;
    border-radius: 8px;
    border: 1px solid rgba(200,146,42,0.1);
}
.points-card .info-text p {
    margin: 5px 0;
    font-size: 0.85rem;
    color: #5A6B7A;
}
.points-card .info-text .highlight {
    color: #C8922A;
    font-weight: 600;
}
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #8A99AA;
}
.empty-state i {
    font-size: 2.5rem;
    display: block;
    margin-bottom: 10px;
    color: #E8E0D8;
}
.historique-table {
    width: 100%;
    border-collapse: collapse;
}
.historique-table th {
    padding: 12px 15px;
    text-align: left;
    font-size: 0.7rem;
    color: #C8922A;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-bottom: 2px solid #F0EDEA;
}
.historique-table td {
    padding: 10px 15px;
    font-size: 0.85rem;
    border-bottom: 1px solid #F0EDEA;
}
.historique-table tr:hover td {
    background: #FEFBF5;
}
.points-positif {
    color: #27AE60;
    font-weight: 600;
}
.points-negatif {
    color: #E74C3C;
    font-weight: 600;
}
.regles-box {
    background: #FEFBF5;
    border-radius: 12px;
    padding: 20px 25px;
    margin-top: 30px;
    border: 1px solid rgba(200,146,42,0.08);
}
.regles-box h3 {
    font-size: 1rem;
    color: #C8922A;
    margin-bottom: 10px;
}
.regles-box ul {
    list-style: none;
    padding: 0;
}
.regles-box ul li {
    padding: 5px 0;
    font-size: 0.85rem;
    color: #5A6B7A;
}
.regles-box ul li i {
    color: #C8922A;
    margin-right: 10px;
}
@media (max-width: 900px) {
    .points-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
    .points-grid { grid-template-columns: 1fr; }
    .points-header { flex-direction: column; text-align: center; gap: 20px; }
    .points-big .number { font-size: 2.5rem; }
}
</style>

<div class="points-container">
    <div class="points-header">
        <div>
            <h1>⭐ Mes points fidélité</h1>
            <p>Cumulez des points à chaque commande terminée</p>
        </div>
        <div class="points-big">
            <div class="number"><?= $points ?></div>
            <div class="label">Points disponibles</div>
            <?php if ($reduction_possible > 0): ?>
            <div class="reduction">= <?= number_format($reduction_possible, 0, ',', ' ') ?> F de réduction</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="compte-sidebar">
        <div class="menu-item">
            <a href="mon_compte.php"><i class="bi bi-grid"></i> Tableau de bord</a>
        </div>
        <div class="menu-item">
            <a href="mes_commandes.php"><i class="bi bi-receipt"></i> Mes commandes</a>
        </div>
        <div class="menu-item">
            <a href="ma_wishlist.php"><i class="bi bi-heart"></i> Ma wishlist</a>
        </div>
        <div class="menu-item">
            <a href="mes_factures.php"><i class="bi bi-file-pdf"></i> Mes factures</a>
        </div>
        <div class="menu-item active">
            <a href="mes_points.php"><i class="bi bi-star"></i> Points fidélité</a>
        </div>
        <div class="menu-item">
            <a href="mon_profil.php"><i class="bi bi-person"></i> Mon profil</a>
        </div>
        <div class="menu-item logout">
            <a href="deconnexion.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        </div>
    </div>

    <div class="points-grid">
        <div class="points-card">
            <h3><i class="bi bi-star-fill"></i> Points disponibles</h3>
            <div class="value"><?= $points ?></div>
            <div class="sub">Points à utiliser sur vos prochains achats</div>
            <?php if ($reduction_possible > 0): ?>
            <div class="info-text">
                <p>💳 <span class="highlight"><?= number_format($reduction_possible, 0, ',', ' ') ?> F</span> de réduction possible</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="points-card">
            <h3><i class="bi bi-graph-up"></i> Statistiques</h3>
            <div class="value green"><?= $total_gagnes ?></div>
            <div class="sub">Points gagnés au total</div>
            <div style="margin-top:10px;">
                <p style="font-size:0.85rem;color:#5A6B7A;margin:3px 0;">
                    <i class="bi bi-check-circle" style="color:#27AE60;"></i> 
                    <?= $commandes_terminees ?> commande(s) terminée(s)
                </p>
                <?php if ($points_potentiels > 0): ?>
                <p style="font-size:0.85rem;color:#5A6B7A;margin:3px 0;">
                    <i class="bi bi-clock" style="color:#C8922A;"></i> 
                    <?= $points_potentiels ?> points en attente de validation
                </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="points-card">
            <h3><i class="bi bi-info-circle"></i> Comment ça marche ?</h3>
            <div style="margin-top:5px;">
                <p style="font-size:0.85rem;color:#5A6B7A;margin:5px 0;">
                    <span class="highlight">1 point</span> = 1 000 FCFA dépensés
                </p>
                <p style="font-size:0.85rem;color:#5A6B7A;margin:5px 0;">
                    <span class="highlight">100 points</span> = 1 000 FCFA de réduction
                </p>
                <div class="progress" style="margin-top:10px;">
                    <div class="progress-bar" style="width: <?= min(($points / 100) * 100, 100) ?>%"></div>
                </div>
                <p style="font-size:0.75rem;color:#8A99AA;margin-top:5px;">
                    Prochain palier : <?= 100 - ($points % 100) ?> points pour 1000 F de réduction
                </p>
            </div>
        </div>
    </div>

    <!-- Historique -->
    <?php if(!empty($historique)): ?>
    <div class="points-card" style="margin-top:0;">
        <h3><i class="bi bi-clock-history"></i> Historique des points</h3>
        <div style="overflow-x:auto;">
            <table class="historique-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th style="text-align:right;">Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($historique as $h): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                        <td><?= htmlspecialchars($h['action']) ?></td>
                        <td style="text-align:right;">
                            <?php if ($h['points'] > 0): ?>
                                <span class="points-positif">+<?= $h['points'] ?></span>
                            <?php else: ?>
                                <span class="points-negatif"><?= $h['points'] ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="points-card" style="margin-top:0;">
        <h3><i class="bi bi-clock-history"></i> Historique des points</h3>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <p>Aucun historique disponible pour le moment.</p>
            <p style="font-size:0.8rem;">Les points seront ajoutés après validation de vos commandes.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Règles -->
    <div class="regles-box">
        <h3><i class="bi bi-book"></i> Règles du programme fidélité</h3>
        <ul>
            <li><i class="bi bi-check-circle"></i> 1 point gagné pour 1 000 FCFA dépensés</li>
            <li><i class="bi bi-check-circle"></i> Les points sont crédités lorsque la commande est <strong>livrée</strong> ou <strong>terminée</strong></li>
            <li><i class="bi bi-check-circle"></i> 100 points = 1 000 FCFA de réduction sur votre prochaine commande</li>
            <li><i class="bi bi-check-circle"></i> Les points sont valables 1 an à partir de leur date d'obtention</li>
            <li><i class="bi bi-check-circle"></i> En cas d'annulation, les points sont retirés</li>
        </ul>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>