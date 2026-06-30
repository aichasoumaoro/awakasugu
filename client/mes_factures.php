<?php
// ============================================
// MES FACTURES - Awa Ka Sugu
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

$client_id = $_SESSION['client_id'];

// Récupérer les infos du client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: deconnexion.php');
    exit;
}

// Récupérer les factures du client
$stmt = $pdo->prepare("
    SELECT f.*, c.numero_commande, c.created_at as commande_date 
    FROM factures f
    JOIN commandes c ON c.id = f.commande_id
    WHERE c.client_id = ? OR c.telephone = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$client_id, $client['telephone']]);
$factures = $stmt->fetchAll();

$titre_page = 'Mes factures';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
/* ========== PAGE MES FACTURES ========== */
.compte-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
.compte-header {
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
.compte-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    color: #C8922A;
    margin-bottom: 5px;
}
.compte-header p {
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
}
.compte-stats {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}
.stat-card {
    background: rgba(200,146,42,0.12);
    border-radius: 12px;
    padding: 12px 22px;
    text-align: center;
    border: 1px solid rgba(200,146,42,0.1);
}
.stat-card .number {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: #C8922A;
}
.stat-card .label {
    font-size: 0.65rem;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 1px;
}
.compte-grid {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 30px;
}
.compte-sidebar {
    background: white;
    border-radius: 16px;
    border: 1px solid #F0EDEA;
    overflow: hidden;
}
.compte-sidebar .menu-item {
    padding: 15px 20px;
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
.compte-content {
    background: white;
    border-radius: 16px;
    padding: 30px;
    border: 1px solid #F0EDEA;
}
.compte-content .section-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem;
    font-weight: 600;
    color: #0D0D0D;
    margin-bottom: 20px;
}
.compte-content .section-title i {
    color: #C8922A;
    margin-right: 10px;
}
.table-factures {
    width: 100%;
    border-collapse: collapse;
}
.table-factures th,
.table-factures td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #F0EDEA;
}
.table-factures th {
    color: #C8922A;
    font-weight: 600;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.table-factures tr:hover td {
    background: #FEFBF5;
}
.badge-statut {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}
.statut-payee { background: #D4EDDA; color: #155724; }
.statut-en_attente { background: #FFF3CD; color: #856404; }
.statut-annulee { background: #F8D7DA; color: #721C24; }

.btn-pdf {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-decoration: none;
    background: #E74C3C;
    color: white;
    transition: all 0.3s;
}
.btn-pdf:hover {
    background: #C0392B;
    color: white;
    transform: translateY(-2px);
}
.btn-pdf i { font-size: 0.9rem; }

.empty-state {
    text-align: center;
    padding: 50px 20px;
}
.empty-state i {
    font-size: 3.5rem;
    color: #E8E0D8;
    display: block;
    margin-bottom: 15px;
}
.empty-state p {
    color: #8A99AA;
    font-size: 0.95rem;
    margin-bottom: 20px;
}
.btn-boutique {
    display: inline-block;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #fff;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-boutique:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
    color: white;
}
@media (max-width: 768px) {
    .compte-grid { grid-template-columns: 1fr; gap: 20px; }
    .compte-header { flex-direction: column; text-align: center; gap: 20px; padding: 25px 20px; }
    .compte-stats { justify-content: center; }
    .table-factures th, .table-factures td { padding: 8px 10px; font-size: 0.8rem; }
    .compte-content { padding: 20px; }
}
@media (max-width: 600px) {
    .table-factures { display: block; overflow-x: auto; }
    .stat-card { padding: 8px 15px; }
    .stat-card .number { font-size: 1.2rem; }
}
</style>

<div class="compte-container">
    <div class="compte-header">
        <div>
            <h1>📄 Mes factures</h1>
            <p>Retrouvez toutes vos factures</p>
        </div>
        <div class="compte-stats">
            <div class="stat-card">
                <div class="number"><?= count($factures) ?></div>
                <div class="label">Factures</div>
            </div>
        </div>
    </div>
    
    <div class="compte-grid">
        <aside class="compte-sidebar">
            <div class="menu-item">
                <a href="mon_compte.php"><i class="bi bi-grid"></i> Tableau de bord</a>
            </div>
            <div class="menu-item">
                <a href="mes_commandes.php"><i class="bi bi-receipt"></i> Mes commandes</a>
            </div>
            <div class="menu-item">
                <a href="ma_wishlist.php"><i class="bi bi-heart"></i> Ma wishlist</a>
            </div>
            <div class="menu-item active">
                <a href="mes_factures.php"><i class="bi bi-file-pdf"></i> Mes factures</a>
            </div>
            <div class="menu-item">
                <a href="mes_points.php"><i class="bi bi-star"></i> Points fidélité</a>
            </div>
            <div class="menu-item">
                <a href="mon_profil.php"><i class="bi bi-person"></i> Mon profil</a>
            </div>
            <div class="menu-item logout">
                <a href="deconnexion.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            </div>
        </aside>
        
        <main class="compte-content">
            <h2 class="section-title"><i class="bi bi-file-pdf"></i> Historique des factures</h2>
            
            <?php if(empty($factures)): ?>
                <div class="empty-state">
                    <i class="bi bi-file-pdf"></i>
                    <p>Vous n'avez pas encore de facture.</p>
                    <a href="../boutique/catalogue.php" class="btn-boutique">
                        <i class="bi bi-bag"></i> Découvrir la boutique
                    </a>
                </div>
            <?php else: ?>
                <table class="table-factures">
                    <thead>
                        <tr>
                            <th>N° Facture</th>
                            <th>N° Commande</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th style="text-align:center;">PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($factures as $f): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($f['numero_facture']) ?></strong></td>
                            <td><?= htmlspecialchars($f['numero_commande']) ?></td>
                            <td style="color:#8A99AA;font-size:0.85rem;">
                                <?= date('d/m/Y', strtotime($f['created_at'])) ?>
                            </td>
                            <td style="font-weight:600;color:#C8922A;">
                                <?= number_format($f['montant_total'], 0, ',', ' ') ?> FCFA
                            </td>
                            <td>
                                <?php 
                                $statut_labels = [
                                    'payee' => 'Payée',
                                    'en_attente' => 'En attente',
                                    'annulee' => 'Annulée'
                                ];
                                $statut_class = [
                                    'payee' => 'statut-payee',
                                    'en_attente' => 'statut-en_attente',
                                    'annulee' => 'statut-annulee'
                                ];
                                ?>
                                <span class="badge-statut <?= $statut_class[$f['statut_paiement']] ?? 'statut-en_attente' ?>">
                                    <?= $statut_labels[$f['statut_paiement']] ?? $f['statut_paiement'] ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <?php if(!empty($f['fichier_pdf']) && file_exists('../uploads/factures/'.$f['fichier_pdf'])): ?>
                                    <a href="../uploads/factures/<?= $f['fichier_pdf'] ?>" target="_blank" class="btn-pdf">
                                        <i class="bi bi-file-pdf"></i> Télécharger
                                    </a>
                                <?php else: ?>
                                    <span style="color:#BBB;font-size:0.75rem;">Non disponible</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>