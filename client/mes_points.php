<?php
// ============================================
// MES POINTS FIDÉLITÉ - Awa Ka Sugu
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
    $stmt = $pdo->prepare("INSERT INTO fidelite (client_id, points) VALUES (?, 0)");
    $stmt->execute([$client_id]);
    $fidelite = ['points' => 0, 'total_points_gagnes' => 0];
}

// Récupérer l'historique des points (si une table existe)
$historique = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM fidelite_historique WHERE client_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$client_id]);
    $historique = $stmt->fetchAll();
} catch(PDOException $e) {
    // La table n'existe pas encore
}

$points = $fidelite['points'] ?? 0;
$total_gagnes = $fidelite['total_points_gagnes'] ?? 0;
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
    grid-template-columns: 1fr 1fr;
    gap: 25px;
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
@media (max-width: 768px) {
    .points-grid { grid-template-columns: 1fr; }
    .points-header { flex-direction: column; text-align: center; gap: 20px; }
    .points-big .number { font-size: 2.5rem; }
}
</style>

<div class="points-container">
    <div class="points-header">
        <div>
            <h1>⭐ Mes points fidélité</h1>
            <p>Cumulez des points à chaque commande</p>
        </div>
        <div class="points-big">
            <div class="number"><?= $points ?></div>
            <div class="label">Points disponibles</div>
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
            <h3><i class="bi bi-star-fill"></i> Total des points</h3>
            <div class="value"><?= $points ?></div>
            <div class="sub">Points cumulés sur vos achats</div>
            <div class="progress">
                <div class="progress-bar" style="width: <?= min(($points / 100) * 100, 100) ?>%"></div>
            </div>
            <div style="margin-top:10px;font-size:0.8rem;color:#8A99AA;">
                100 points = 1 000 FCFA de réduction
            </div>
        </div>

        <div class="points-card">
            <h3><i class="bi bi-graph-up"></i> Statistiques</h3>
            <p><strong>Points gagnés :</strong> <?= $total_gagnes ?></p>
            <p><strong>Commandes :</strong> <?= $total_gagnes > 0 ? 'En cours' : 'Aucune' ?></p>
            <div class="sub">Gagnez 1 point pour 1 000 FCFA dépensés</div>
        </div>
    </div>

    <!-- Historique -->
    <?php if(!empty($historique)): ?>
    <div class="points-card" style="margin-top:25px;">
        <h3><i class="bi bi-clock-history"></i> Historique des points</h3>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#F8F9FA;">
                        <th style="padding:10px;text-align:left;font-size:0.7rem;color:#8A99AA;text-transform:uppercase;">Date</th>
                        <th style="padding:10px;text-align:left;font-size:0.7rem;color:#8A99AA;text-transform:uppercase;">Action</th>
                        <th style="padding:10px;text-align:right;font-size:0.7rem;color:#8A99AA;text-transform:uppercase;">Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($historique as $h): ?>
                    <tr style="border-bottom:1px solid #F0EDEA;">
                        <td style="padding:10px;font-size:0.85rem;"><?= date('d/m/Y', strtotime($h['created_at'])) ?></td>
                        <td style="padding:10px;font-size:0.85rem;"><?= htmlspecialchars($h['action']) ?></td>
                        <td style="padding:10px;text-align:right;font-weight:600;color:#C8922A;">
                            +<?= $h['points'] ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>