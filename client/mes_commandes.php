<?php
// ============================================
// MES COMMANDES - Awa Ka Sugu
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

// Vérifier si le client est connecté
if (!isset($_SESSION['client_id']) || empty($_SESSION['client_id'])) {
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

$client_id = (int)$_SESSION['client_id'];

// Récupérer le téléphone du client
$stmt = $pdo->prepare("SELECT telephone, email, nom FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

$commandes = [];
$reservations = [];

// ============================================
// 1. RÉCUPÉRER LES COMMANDES DE LA TABLE `commandes` (BOUTIQUE)
// ============================================
try {
    $stmt = $pdo->prepare("
        SELECT *, 'boutique' as type_commande 
        FROM commandes 
        WHERE client_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$client_id]);
    $commandes_boutique = $stmt->fetchAll();
    
    if ($client && !empty($client['telephone'])) {
        $stmt2 = $pdo->prepare("
            SELECT *, 'boutique' as type_commande 
            FROM commandes 
            WHERE (client_id IS NULL OR client_id = 0)
            AND telephone = ?
            ORDER BY created_at DESC
        ");
        $stmt2->execute([$client['telephone']]);
        $commandes_boutique_sans_id = $stmt2->fetchAll();
        $commandes_boutique = array_merge($commandes_boutique, $commandes_boutique_sans_id);
    }
    $commandes = array_merge($commandes, $commandes_boutique);
} catch(PDOException $e) {
    error_log("Table commandes: " . $e->getMessage());
}

// ============================================
// 2. RÉCUPÉRER LES COMMANDES DE LA TABLE `commandes_repas` (REPAS)
// ============================================
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'commandes_repas'");
    if ($stmt->rowCount() > 0) {
        // Récupérer les commandes repas par téléphone (pas de client_id)
        if ($client && !empty($client['telephone'])) {
            $stmt = $pdo->prepare("
                SELECT *, 'repas' as type_commande 
                FROM commandes_repas 
                WHERE telephone = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$client['telephone']]);
            $commandes_repas = $stmt->fetchAll();
            $commandes = array_merge($commandes, $commandes_repas);
        }
    }
} catch(PDOException $e) {
    error_log("Table commandes_repas: " . $e->getMessage());
}

// ============================================
// 3. RÉCUPÉRER LES RÉSERVATIONS PAR TÉLÉPHONE
// ============================================
try {
    if ($client && !empty($client['telephone'])) {
        $stmt = $pdo->prepare("
            SELECT *, 'reservation' as type_commande 
            FROM reservations 
            WHERE telephone = ?
            ORDER BY date_reservation DESC, heure_reservation DESC
        ");
        $stmt->execute([$client['telephone']]);
        $reservations = $stmt->fetchAll();
    }
} catch(PDOException $e) {
    error_log("Table reservations: " . $e->getMessage());
}

// ============================================
// 4. FUSIONNER COMMANDES + RÉSERVATIONS
// ============================================
$items = array_merge($commandes, $reservations);

// Supprimer les doublons
$unique = [];
foreach ($items as $item) {
    $key = ($item['id'] ?? 0) . '_' . ($item['type_commande'] ?? 'boutique');
    $unique[$key] = $item;
}
$items = array_values($unique);

// Trier par date décroissante
usort($items, function($a, $b) {
    $date_a = $a['created_at'] ?? $a['date_reservation'] ?? '1970-01-01';
    $date_b = $b['created_at'] ?? $b['date_reservation'] ?? '1970-01-01';
    return strtotime($date_b) - strtotime($date_a);
});

// ============================================
// STATISTIQUES
// ============================================
$nb_commandes = count($commandes);
$nb_reservations = count($reservations);
$total_items = count($items);
$total_depense = 0;
foreach($commandes as $c) {
    $total_depense += (float)($c['total'] ?? 0);
}

$titre_page = 'Mes commandes & réservations';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$statuts_labels = [
    'en_attente' => 'En attente',
    'confirmee' => 'Confirmée',
    'en_preparation' => 'En préparation',
    'en_livraison' => 'En livraison',
    'livree' => 'Livrée',
    'terminee' => 'Terminée',
    'annulee' => 'Annulée'
];
?>

<style>
/* ========== PAGE MES COMMANDES ========== */
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
.table-commandes {
    width: 100%;
    border-collapse: collapse;
}
.table-commandes th,
.table-commandes td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #F0EDEA;
}
.table-commandes th {
    color: #C8922A;
    font-weight: 600;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.table-commandes tr:hover td {
    background: #FEFBF5;
}
.statut {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}
.statut-en_attente { background: #FFF3CD; color: #856404; }
.statut-confirmee { background: #D1ECF1; color: #0C5460; }
.statut-en_preparation { background: #CCE5FF; color: #004085; }
.statut-en_livraison { background: #E8D5F5; color: #6A1B9A; }
.statut-livree { background: #D4EDDA; color: #155724; }
.statut-terminee { background: #D4EDDA; color: #155724; }
.statut-annulee { background: #F8D7DA; color: #721C24; }
.btn-detail {
    background: none;
    border: none;
    color: #C8922A;
    cursor: pointer;
    font-size: 0.8rem;
    text-decoration: none;
    transition: color 0.3s;
}
.btn-detail:hover {
    color: #9A6E1A;
    text-decoration: underline;
}
.badge-type {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.6rem;
    font-weight: 600;
    margin-left: 5px;
}
.badge-boutique {
    background: #D1ECF1;
    color: #0C5460;
}
.badge-repas {
    background: #FFF3CD;
    color: #856404;
}
.badge-reservation {
    background: #E8D5F5;
    color: #6A1B9A;
}
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
    .table-commandes th, .table-commandes td { padding: 8px 10px; font-size: 0.8rem; }
    .compte-content { padding: 20px; }
}
@media (max-width: 600px) {
    .table-commandes { display: block; overflow-x: auto; }
    .stat-card { padding: 8px 15px; }
    .stat-card .number { font-size: 1.2rem; }
}
</style>

<div class="compte-container">
    <div class="compte-header">
        <div>
            <h1>📦 Mes commandes & réservations</h1>
            <p>Retrouvez l'historique de toutes vos commandes et réservations</p>
        </div>
        <div class="compte-stats">
            <div class="stat-card">
                <div class="number"><?= $total_items ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $nb_commandes ?></div>
                <div class="label">Commandes</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $nb_reservations ?></div>
                <div class="label">Réservations</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= number_format($total_depense, 0, ',', ' ') ?></div>
                <div class="label">Dépensé (F)</div>
            </div>
        </div>
    </div>
    
    <div class="compte-grid">
        <aside class="compte-sidebar">
            <div class="menu-item">
                <a href="mon_compte.php"><i class="bi bi-grid"></i> Tableau de bord</a>
            </div>
            <div class="menu-item active">
                <a href="mes_commandes.php"><i class="bi bi-receipt"></i> Mes commandes</a>
            </div>
            <div class="menu-item">
                <a href="ma_wishlist.php"><i class="bi bi-heart"></i> Ma wishlist</a>
            </div>
            <div class="menu-item">
                <a href="mes_factures.php"><i class="bi bi-file-pdf"></i> Mes factures</a>
            </div>
            <div class="menu-item">
                <a href="mon_profil.php"><i class="bi bi-person"></i> Mon profil</a>
            </div>
            <div class="menu-item logout">
                <a href="deconnexion.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            </div>
        </aside>
        
        <main class="compte-content">
            <h2 class="section-title"><i class="bi bi-clock-history"></i> Historique</h2>
            
            <?php if(empty($items)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>Vous n'avez pas encore de commande ni de réservation.</p>
                    <a href="menu.php" class="btn-boutique" style="margin-bottom:10px;">
                        <i class="bi bi-bag"></i> Commander un repas
                    </a>
                    <a href="../boutique/catalogue.php" class="btn-boutique">
                        <i class="bi bi-shop"></i> Voir la boutique
                    </a>
                </div>
            <?php else: ?>
                <table class="table-commandes">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Détail</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): 
                            $type = $item['type_commande'] ?? 'boutique';
                            
                            if ($type == 'reservation') {
                                $numero = 'RES-' . str_pad($item['id'], 6, '0', STR_PAD_LEFT);
                                $date = date('d/m/Y', strtotime($item['date_reservation']));
                                $heure = substr($item['heure_reservation'] ?? '00:00', 0, 5);
                                $detail = $item['nb_personnes'] . ' pers. à ' . $heure;
                                $montant = '-';
                                $statut_key = $item['statut'] ?? 'en_attente';
                                $type_label = '📅 Réservation';
                                $type_class = 'badge-reservation';
                                $link = '#';
                            } else {
                                $numero = $item['numero_commande'] ?? 'N/A';
                                $date = date('d/m/Y', strtotime($item['created_at'] ?? 'now'));
                                $detail = $item['nom_plat'] ?? 'Commande';
                                $montant = number_format($item['total'] ?? 0, 0, ',', ' ') . ' FCFA';
                                $statut_key = $item['statut'] ?? 'en_attente';
                                
                                if ($type == 'repas') {
                                    $type_label = '🍽️ Repas';
                                    $type_class = 'badge-repas';
                                } else {
                                    $type_label = '🛍️ Boutique';
                                    $type_class = 'badge-boutique';
                                }
                                $link = 'commande_detail.php?id=' . ($item['id'] ?? 0) . '&type=' . $type;
                            }
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($numero) ?></strong></td>
                            <td style="color:#8A99AA;font-size:0.85rem;">
                                <?= $date ?>
                            </td>
                            <td>
                                <span class="badge-type <?= $type_class ?>">
                                    <?= $type_label ?>
                                </span>
                            </td>
                            <td style="font-size:0.85rem;color:#5A6B7A;">
                                <?= htmlspecialchars($detail) ?>
                            </td>
                            <td style="font-weight:600;color:#C8922A;">
                                <?= $montant ?>
                            </td>
                            <td>
                                <span class="statut statut-<?= $statut_key ?>">
                                    <?= $statuts_labels[$statut_key] ?? $statut_key ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($type != 'reservation'): ?>
                                <a href="<?= $link ?>" class="btn-detail">
                                    <i class="bi bi-eye"></i> Voir
                                </a>
                                <?php else: ?>
                                <span style="color:#8A99AA;font-size:0.7rem;">
                                    <i class="bi bi-calendar-check"></i>
                                </span>
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