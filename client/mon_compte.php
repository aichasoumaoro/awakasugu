<?php
// ============================================
// MON COMPTE - Awa Ka Sugu
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
// ✅ VÉRIFICATION DE CONNEXION (AVANT LE HEADER)
// ============================================
if (!isset($_SESSION['client_id'])) {
    header('Location: connexion.php');
    exit;
}

// ============================================
// CONNEXION À LA BASE DE DONNÉES
// ============================================
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

// ============================================
// RÉCUPÉRATION DES DONNÉES
// ============================================
$client_id = $_SESSION['client_id'];
$client_nom = $_SESSION['client_nom'] ?? '';

// Récupérer les infos du client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    session_destroy();
    header('Location: connexion.php');
    exit;
}

// Récupérer les commandes du client
$commandes = $pdo->prepare("
    SELECT * FROM commandes 
    WHERE client_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$commandes->execute([$client_id]);
$commandes = $commandes->fetchAll();

// Récupérer les commandes sans client_id (avant connexion)
$stmt = $pdo->prepare("
    SELECT * FROM commandes 
    WHERE client_id IS NULL 
    AND telephone = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$client['telephone']]);
$commandes_sans_id = $stmt->fetchAll();

// Fusionner les commandes
$commandes = array_merge($commandes, $commandes_sans_id);
usort($commandes, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Statistiques
$nb_commandes = count($commandes);
$points = 0;
$total_depense = 0;
$nb_factures = 0;

// Points de fidélité
$stmt = $pdo->prepare("SELECT points FROM fidelite WHERE client_id = ?");
$stmt->execute([$client_id]);
$fidelite = $stmt->fetch();
$points = $fidelite['points'] ?? 0;

// Total dépensé
foreach($commandes as $c) {
    if($c['statut'] == 'livree' || $c['statut'] == 'confirmee') {
        $total_depense += $c['total'];
    }
}

// Nombre de factures
$stmt = $pdo->prepare("
    SELECT COUNT(*) as nb 
    FROM factures f
    JOIN commandes c ON c.id = f.commande_id
    WHERE c.client_id = ? OR c.telephone = ?
");
$stmt->execute([$client_id, $client['telephone']]);
$nb_factures = $stmt->fetchColumn();

// ============================================
// INCLUSION DU HEADER (APRÈS LES REDIRECTIONS)
// ============================================
$titre_page = 'Mon compte';
$meta_desc  = 'Gérez votre profil, vos commandes et vos points fidélité.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
.compte-container {
    max-width: 1300px;
    margin: 40px auto;
    padding: 0 40px;
}
.compte-header {
    background: linear-gradient(135deg, #0D0D0D, #1A1A1A);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 40px;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    border: 1px solid rgba(200,146,42,0.15);
    position: relative;
    overflow: hidden;
}
.compte-header::after {
    content: '✦';
    position: absolute;
    right: -20px;
    top: -20px;
    font-size: 80px;
    color: rgba(200,146,42,0.05);
}
.compte-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    color: #C8922A;
    margin-bottom: 8px;
}
.compte-header p {
    color: rgba(255,255,255,0.5);
}
.compte-stats {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}
.stat-card {
    background: rgba(200,146,42,0.12);
    border-radius: 14px;
    padding: 15px 25px;
    text-align: center;
    min-width: 90px;
    border: 1px solid rgba(200,146,42,0.1);
}
.stat-card .number {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    font-weight: 700;
    color: #C8922A;
    line-height: 1;
}
.stat-card .label {
    font-size: 0.6rem;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 5px;
}
.compte-grid {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 30px;
}
.compte-sidebar {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #F0EDEA;
    overflow: hidden;
    align-self: start;
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
.compte-content {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #F0EDEA;
    padding: 30px;
}
.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    font-weight: 600;
    margin-bottom: 20px;
    color: #0D0D0D;
}
.section-title i {
    color: #C8922A;
    margin-right: 10px;
}
.commandes-table {
    width: 100%;
    border-collapse: collapse;
}
.commandes-table th,
.commandes-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #F0EDEA;
}
.commandes-table th {
    color: #C8922A;
    font-weight: 600;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.commandes-table tr:hover td {
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
.statut-annulee { background: #F8D7DA; color: #721C24; }
.btn-voir {
    background: none;
    border: none;
    color: #C8922A;
    cursor: pointer;
    font-size: 0.8rem;
    text-decoration: none;
    transition: color 0.3s;
}
.btn-voir:hover {
    color: #9A6E1A;
    text-decoration: underline;
}
.btn-boutique {
    display: inline-block;
    background: linear-gradient(135deg, #C8922A, #E2B96A);
    color: #fff;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    margin-top: 20px;
}
.btn-boutique:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
}
.info-profil {
    background: #FEFBF5;
    border-radius: 12px;
    padding: 20px 25px;
    margin-top: 30px;
    border: 1px solid rgba(200,146,42,0.08);
}
.info-profil h3 {
    font-size: 1rem;
    margin-bottom: 15px;
    color: #C8922A;
}
.info-profil .info-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.info-profil p {
    margin-bottom: 6px;
    font-size: 0.85rem;
    color: #333;
}
.info-profil strong {
    color: #C8922A;
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
}
@media (max-width: 900px) {
    .compte-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    .compte-header {
        flex-direction: column;
        text-align: center;
        gap: 20px;
        padding: 30px 20px;
    }
    .compte-container {
        padding: 0 20px;
    }
    .commandes-table th,
    .commandes-table td {
        padding: 8px 10px;
        font-size: 0.75rem;
    }
    .info-profil .info-row {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 600px) {
    .commandes-table {
        display: block;
        overflow-x: auto;
    }
    .compte-stats {
        justify-content: center;
    }
    .stat-card {
        padding: 10px 15px;
        min-width: 70px;
    }
    .stat-card .number {
        font-size: 1.3rem;
    }
}
</style>

<div class="compte-container">
    <div class="compte-header">
        <div>
            <h1>👋 Bonjour, <?= htmlspecialchars($client_nom) ?></h1>
            <p>Bienvenue dans votre espace personnel Awa Ka Sugu</p>
        </div>
        <div class="compte-stats">
            <div class="stat-card">
                <div class="number"><?= $nb_commandes ?></div>
                <div class="label">Commandes</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $points ?></div>
                <div class="label">Points</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= number_format($total_depense, 0, ',', ' ') ?></div>
                <div class="label">Dépensé (F)</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $nb_factures ?></div>
                <div class="label">Factures</div>
            </div>
        </div>
    </div>
    
    <div class="compte-grid">
        <aside class="compte-sidebar">
            <div class="menu-item active">
                <a href="mon_compte.php">
                    <i class="bi bi-grid"></i> Tableau de bord
                </a>
            </div>
            <div class="menu-item">
                <a href="mes_commandes.php">
                    <i class="bi bi-receipt"></i> Mes commandes
                </a>
            </div>
            <div class="menu-item">
                <a href="ma_wishlist.php">
                    <i class="bi bi-heart"></i> Ma wishlist
                </a>
            </div>
            <div class="menu-item">
                <a href="mes_factures.php">
                    <i class="bi bi-file-pdf"></i> Mes factures
                </a>
            </div>
            <div class="menu-item">
                <a href="mes_points.php">
                    <i class="bi bi-star"></i> Points fidélité
                </a>
            </div>
            <div class="menu-item">
                <a href="mon_profil.php">
                    <i class="bi bi-person"></i> Mon profil
                </a>
            </div>
            <div class="menu-item logout">
                <a href="deconnexion.php">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </aside>
        
        <main class="compte-content">
            <h2 class="section-title"><i class="bi bi-clock-history"></i> Mes dernières commandes</h2>
            
            <?php if(empty($commandes)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>Vous n'avez pas encore passé de commande.</p>
                    <a href="<?= SITE_URL ?>/boutique/catalogue.php" class="btn-boutique">
                        <i class="bi bi-bag"></i> Découvrir la boutique
                    </a>
                </div>
            <?php else: ?>
                <table class="commandes-table">
                    <thead>
                        <tr>
                            <th>N° commande</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($commandes as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['numero_commande'] ?? '#'.str_pad($c['id'], 6, '0', STR_PAD_LEFT)) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($c['created_at'] ?? 'now')) ?></td>
                            <td style="color:#C8922A;font-weight:600;"><?= number_format($c['total'] ?? 0, 0, ',', ' ') ?> FCFA</td>
                            <td>
                                <?php 
                                $statuts = [
                                    'en_attente' => 'En attente',
                                    'confirmee' => 'Confirmée',
                                    'en_preparation' => 'En préparation',
                                    'en_livraison' => 'En livraison',
                                    'livree' => 'Livrée',
                                    'annulee' => 'Annulée'
                                ];
                                $statut_key = $c['statut'] ?? 'en_attente';
                                ?>
                                <span class="statut statut-<?= $statut_key ?>">
                                    <?= $statuts[$statut_key] ?? $statut_key ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <a href="commande_detail.php?id=<?= $c['id'] ?>" class="btn-voir">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if(count($commandes) >= 10): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="mes_commandes.php" class="btn-voir">Voir toutes mes commandes →</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Informations du profil -->
            <div class="info-profil">
                <h3><i class="bi bi-person-circle"></i> Informations du compte</h3>
                <div class="info-row">
                    <div>
                        <p><strong>Nom :</strong> <?= htmlspecialchars($client['nom'] ?? '') ?> <?= htmlspecialchars($client['prenom'] ?? '') ?></p>
                        <p><strong>Email :</strong> <?= htmlspecialchars($client['email'] ?? '') ?></p>
                    </div>
                    <div>
                        <p><strong>Téléphone :</strong> <?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></p>
                        <p><strong>Adresse :</strong> <?= htmlspecialchars($client['adresse_complete'] ?? 'Non renseignée') ?></p>
                    </div>
                </div> 
            </div>
        </main>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>