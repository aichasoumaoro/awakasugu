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

// ============================================
// RÉCUPÉRER TOUTES LES COMMANDES (BOUTIQUE + REPAS)
// ============================================
$commandes = [];

// 1. Commandes boutique avec client_id
$stmt = $pdo->prepare("
    SELECT *, 'boutique' as type_commande 
    FROM commandes 
    WHERE client_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$client_id]);
$commandes_boutique = $stmt->fetchAll();

// 2. Commandes repas avec client_id (si la colonne existe)
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'commandes_repas'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SHOW COLUMNS FROM commandes_repas LIKE 'client_id'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                SELECT *, 'repas' as type_commande 
                FROM commandes_repas 
                WHERE client_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$client_id]);
            $commandes_repas = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("
                SELECT *, 'repas' as type_commande 
                FROM commandes_repas 
                WHERE telephone = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$client['telephone']]);
            $commandes_repas = $stmt->fetchAll();
        }
    } else {
        $commandes_repas = [];
    }
} catch(PDOException $e) {
    $commandes_repas = [];
}

// 3. Commandes boutique sans client_id mais avec le même téléphone
if (!empty($client['telephone'])) {
    $stmt = $pdo->prepare("
        SELECT *, 'boutique' as type_commande 
        FROM commandes 
        WHERE (client_id IS NULL OR client_id = 0)
        AND telephone = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$client['telephone']]);
    $commandes_boutique_sans_id = $stmt->fetchAll();
} else {
    $commandes_boutique_sans_id = [];
}

// 4. Commandes repas sans client_id mais avec le même téléphone
$commandes_repas_sans_id = [];
if (!empty($client['telephone'])) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'commandes_repas'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                SELECT *, 'repas' as type_commande 
                FROM commandes_repas 
                WHERE (client_id IS NULL OR client_id = 0)
                AND telephone = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$client['telephone']]);
            $commandes_repas_sans_id = $stmt->fetchAll();
        }
    } catch(PDOException $e) {
        $commandes_repas_sans_id = [];
    }
}

// Fusionner toutes les commandes
$commandes = array_merge(
    $commandes_boutique,
    $commandes_repas,
    $commandes_boutique_sans_id,
    $commandes_repas_sans_id
);

// Supprimer les doublons
$unique = [];
foreach ($commandes as $c) {
    $key = ($c['id'] ?? 0) . '_' . ($c['type_commande'] ?? 'boutique');
    $unique[$key] = $c;
}
$commandes = array_values($unique);

// Trier par date décroissante (la plus récente en premier)
usort($commandes, function($a, $b) {
    $date_a = $a['created_at'] ?? '1970-01-01';
    $date_b = $b['created_at'] ?? '1970-01-01';
    return strtotime($date_b) - strtotime($date_a);
});

// ============================================
// RÉCUPÉRER LA DERNIÈRE COMMANDE
// ============================================
$derniere_commande = !empty($commandes) ? $commandes[0] : null;

// ============================================
// STATISTIQUES
// ============================================
$nb_commandes = count($commandes);
$total_depense = 0;
$nb_factures = 0;

// Total dépensé (uniquement sur les commandes confirmées ou livrées)
foreach($commandes as $c) {
    $statut = $c['statut'] ?? 'en_attente';
    if ($statut == 'livree' || $statut == 'confirmee' || $statut == 'terminee') {
        $total_depense += (float)($c['total'] ?? 0);
    }
}

// Nombre de factures
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as nb 
        FROM factures f
        JOIN commandes c ON c.id = f.commande_id
        WHERE c.client_id = ? OR c.telephone = ?
    ");
    $stmt->execute([$client_id, $client['telephone'] ?? '']);
    $nb_factures = $stmt->fetchColumn();
} catch(PDOException $e) {
    $nb_factures = 0;
}

// ============================================
// INCLUSION DU HEADER (APRÈS LES REDIRECTIONS)
// ============================================
$titre_page = 'Mon compte';
$meta_desc  = 'Gérez votre profil, vos commandes et vos points fidélité.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Statuts pour l'affichage
$statuts = [
    'en_attente' => ['label' => 'En attente', 'class' => 'statut-en_attente'],
    'confirmee' => ['label' => 'Confirmée', 'class' => 'statut-confirmee'],
    'en_preparation' => ['label' => 'En préparation', 'class' => 'statut-en_preparation'],
    'en_livraison' => ['label' => 'En livraison', 'class' => 'statut-en_livraison'],
    'livree' => ['label' => 'Livrée', 'class' => 'statut-livree'],
    'terminee' => ['label' => 'Terminée', 'class' => 'statut-terminee'],
    'annulee' => ['label' => 'Annulée', 'class' => 'statut-annulee']
];
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

/* Carte de la dernière commande */
.derniere-commande {
    background: #FEFBF5;
    border-radius: 16px;
    padding: 20px 25px;
    margin-bottom: 30px;
    border: 1px solid rgba(200,146,42,0.15);
    border-left: 4px solid #C8922A;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}
.derniere-commande .info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.derniere-commande .info .numero {
    font-weight: 700;
    font-size: 1.1rem;
    color: #0D0D0D;
}
.derniere-commande .info .numero span {
    color: #C8922A;
}
.derniere-commande .info .date {
    font-size: 0.85rem;
    color: #8A99AA;
}
.derniere-commande .info .type {
    font-size: 0.7rem;
    padding: 2px 10px;
    border-radius: 12px;
    background: rgba(200,146,42,0.1);
    color: #C8922A;
    display: inline-block;
}
.derniere-commande .total {
    text-align: right;
}
.derniere-commande .total .prix {
    font-size: 1.5rem;
    font-weight: 700;
    color: #C8922A;
}
.derniere-commande .total .label {
    font-size: 0.65rem;
    color: #8A99AA;
    text-transform: uppercase;
    letter-spacing: 1px;
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
.statut-terminee { background: #D4EDDA; color: #155724; }
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
    .derniere-commande {
        flex-direction: column;
        text-align: center;
    }
    .derniere-commande .total {
        text-align: center;
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
            
            <!-- ============================================
            DERNIÈRE COMMANDE - EN ÉVIDENCE
            ============================================ -->
            <?php if($derniere_commande): 
                $type_label = ($derniere_commande['type_commande'] ?? 'boutique') == 'repas' ? '🍽️ Repas' : '🛍️ Boutique';
                $type_class = ($derniere_commande['type_commande'] ?? 'boutique') == 'repas' ? 'badge-repas' : 'badge-boutique';
                $statut_key = $derniere_commande['statut'] ?? 'en_attente';
                $statut_info = $statuts[$statut_key] ?? ['label' => $statut_key, 'class' => 'statut-en_attente'];
            ?>
            <div class="derniere-commande">
                <div class="info">
                    <div class="numero">
                        📦 Dernière commande : <span>#<?= htmlspecialchars($derniere_commande['numero_commande'] ?? '#'.str_pad($derniere_commande['id'], 6, '0', STR_PAD_LEFT)) ?></span>
                        <span class="badge-type <?= $type_class ?>"><?= $type_label ?></span>
                    </div>
                    <div class="date">
                        <i class="bi bi-calendar3"></i> <?= date('d/m/Y à H:i', strtotime($derniere_commande['created_at'] ?? 'now')) ?>
                    </div>
                    <div>
                        <span class="statut <?= $statut_info['class'] ?>">
                            <?= $statut_info['label'] ?>
                        </span>
                    </div>
                </div>
                <div class="total">
                    <div class="prix"><?= number_format($derniere_commande['total'] ?? 0, 0, ',', ' ') ?> FCFA</div>
                    <div class="label">Total de la commande</div>
                    <a href="commande_detail.php?id=<?= $derniere_commande['id'] ?>&type=<?= $derniere_commande['type_commande'] ?? 'boutique' ?>" 
                       class="btn-voir" style="margin-top:5px;display:inline-block;">
                        <i class="bi bi-eye"></i> Voir le détail
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- ============================================
            LISTE DES DERNIÈRES COMMANDES
            ============================================ -->
            <h2 class="section-title"><i class="bi bi-clock-history"></i> Mes dernières commandes</h2>
            
            <?php if(empty($commandes)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>Vous n'avez pas encore passé de commande.</p>
                    <a href="menu.php" class="btn-boutique" style="margin-right:10px;">
                        <i class="bi bi-bag"></i> Commander un repas
                    </a>
                    <a href="../boutique/catalogue.php" class="btn-boutique">
                        <i class="bi bi-shop"></i> Voir la boutique
                    </a>
                </div>
            <?php else: ?>
                <table class="commandes-table">
                    <thead>
                        <tr>
                            <th>N° commande</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Afficher les 10 dernières commandes
                        $afficher = array_slice($commandes, 0, 10);
                        foreach($afficher as $c): 
                            $type_label = ($c['type_commande'] ?? 'boutique') == 'repas' ? '🍽️ Repas' : '🛍️ Boutique';
                            $type_class = ($c['type_commande'] ?? 'boutique') == 'repas' ? 'badge-repas' : 'badge-boutique';
                            $statut_key = $c['statut'] ?? 'en_attente';
                            $statut_info = $statuts[$statut_key] ?? ['label' => $statut_key, 'class' => 'statut-en_attente'];
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['numero_commande'] ?? '#'.str_pad($c['id'], 6, '0', STR_PAD_LEFT)) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($c['created_at'] ?? 'now')) ?></td>
                            <td>
                                <span class="badge-type <?= $type_class ?>"><?= $type_label ?></span>
                            </td>
                            <td style="color:#C8922A;font-weight:600;"><?= number_format($c['total'] ?? 0, 0, ',', ' ') ?> FCFA</td>
                            <td>
                                <span class="statut <?= $statut_info['class'] ?>">
                                    <?= $statut_info['label'] ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <a href="commande_detail.php?id=<?= $c['id'] ?>&type=<?= $c['type_commande'] ?? 'boutique' ?>" class="btn-voir">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if(count($commandes) > 10): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="mes_commandes.php" class="btn-voir" style="font-size:0.9rem;">
                            Voir toutes mes commandes (<?= count($commandes) ?>) →
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- ============================================
            INFORMATIONS DU PROFIL
            ============================================ -->
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
                <div style="margin-top:15px;">
                    <a href="mon_profil.php" class="btn-voir">
                        <i class="bi bi-pencil"></i> Modifier mes informations
                    </a>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>