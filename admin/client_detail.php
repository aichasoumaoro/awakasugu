<?php
// ============================================
// CLIENT DETAIL - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';

// ============================================
// VÉRIFICATION DE CONNEXION
// ============================================
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// ============================================
// RÉCUPÉRATION DES INFOS ADMIN
// ============================================
$admin_info = getAdminInfo();
$admin_role = $admin_info['role'] ?? 'admin';
$admin_nom = $admin_info['nom'] ?? 'Awa Doumbia';
$admin_id = $admin_info['id'] ?? 0;

// Vérification des permissions (Client Detail visible pour super_admin et directeur uniquement)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Détail du client';

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
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($client_id <= 0) {
    header('Location: clients.php');
    exit;
}

// Récupérer le client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: clients.php');
    exit;
}

// Récupérer les commandes du client
$stmt = $pdo->prepare("
    SELECT * FROM commandes 
    WHERE client_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$client_id]);
$commandes = $stmt->fetchAll();

// Récupérer le total des commandes
$total_commandes = count($commandes);
$total_depense = $client['total_depense'] ?? 0;
$points = $client['points_fidelite'] ?? 0;

// Statistiques des commandes par statut
$statuts = [
    'en_attente' => 0,
    'confirmee' => 0,
    'en_preparation' => 0,
    'en_livraison' => 0,
    'livree' => 0,
    'annulee' => 0
];
foreach($commandes as $c) {
    $statut = $c['statut'] ?? 'en_attente';
    if(isset($statuts[$statut])) $statuts[$statut]++;
}

$statut_labels = [
    'en_attente' => 'En attente',
    'confirmee' => 'Confirmée',
    'en_preparation' => 'Préparation',
    'en_livraison' => 'Livraison',
    'livree' => 'Livrée',
    'annulee' => 'Annulée'
];

// Maintenance
$maintenance_status = $pdo->query("
    SELECT site_actif, message_maintenance 
    FROM maintenance_globale 
    ORDER BY id DESC LIMIT 1
")->fetch();
$site_en_maintenance = $maintenance_status && $maintenance_status['site_actif'] == 0;

// ============================================
// INCLUSION DU HEADER ET DE LA SIDEBAR
// ============================================
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="main">

    <!-- ===== TOPBAR ===== -->
    <div class="topbar">
        <div>
            <div class="topbar-title">👤 Détail du <span>client</span></div>
            <div class="topbar-breadcrumb">
                <a href="dashboard.php" style="color:#8A99AA;text-decoration:none;">Administration</a> &gt; 
                <a href="clients.php" style="color:#8A99AA;text-decoration:none;">Clients</a> &gt; 
                Détail
            </div>
        </div>
        <div class="topbar-right">
            <a href="clients.php" class="btn-admin btn-outline">
                <i class="bi bi-arrow-left"></i> Retour aux clients
            </a>
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row" style="grid-template-columns: repeat(3, 1fr);">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-receipt"></i></div>
                <div>
                    <div class="stat-val"><?= $total_commandes ?></div>
                    <div class="stat-lbl">Commandes passées</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-cash"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_depense, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Total dépensé</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-gold"><i class="bi bi-star"></i></div>
                <div>
                    <div class="stat-val"><?= $points ?></div>
                    <div class="stat-lbl">Points fidélité</div>
                </div>
            </div>
        </div>

        <!-- ===== INFOS CLIENT & ADRESSE ===== -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
            
            <!-- Informations personnelles -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-person"></i> Informations personnelles</div>
                </div>
                <div class="card-body">
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #F0F2F5;">
                        <span style="color:#8A99AA;font-size:0.8rem;">ID client</span>
                        <span style="font-weight:600;color:#0D0D0D;">#<?= $client['id'] ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #F0F2F5;">
                        <span style="color:#8A99AA;font-size:0.8rem;">Nom complet</span>
                        <span style="font-weight:600;color:#0D0D0D;"><?= htmlspecialchars($client['nom'] ?? '') ?> <?= htmlspecialchars($client['prenom'] ?? '') ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #F0F2F5;">
                        <span style="color:#8A99AA;font-size:0.8rem;">Email</span>
                        <span style="font-weight:600;color:#0D0D0D;"><?= !empty($client['email']) ? htmlspecialchars($client['email']) : '<span style="color:#8A99AA;font-weight:400;">Non renseigné</span>' ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #F0F2F5;">
                        <span style="color:#8A99AA;font-size:0.8rem;">Téléphone</span>
                        <span style="font-weight:600;color:#0D0D0D;"><?= htmlspecialchars($client['telephone'] ?? '') ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;">
                        <span style="color:#8A99AA;font-size:0.8rem;">Inscrit le</span>
                        <span style="font-weight:600;color:#0D0D0D;"><?= date('d/m/Y à H:i', strtotime($client['created_at'] ?? 'now')) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Adresse -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-geo-alt"></i> Adresse</div>
                </div>
                <div class="card-body">
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #F0F2F5;">
                        <span style="color:#8A99AA;font-size:0.8rem;">Adresse</span>
                        <span style="font-weight:600;color:#0D0D0D;"><?= !empty($client['adresse_complete']) ? htmlspecialchars($client['adresse_complete']) : '<span style="color:#8A99AA;font-weight:400;">Non renseignée</span>' ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #F0F2F5;">
                        <span style="color:#8A99AA;font-size:0.8rem;">Quartier</span>
                        <span style="font-weight:600;color:#0D0D0D;"><?= !empty($client['quartier']) ? htmlspecialchars($client['quartier']) : '<span style="color:#8A99AA;font-weight:400;">Non renseigné</span>' ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;">
                        <span style="color:#8A99AA;font-size:0.8rem;">Commune</span>
                        <span style="font-weight:600;color:#0D0D0D;"><?= !empty($client['commune']) ? htmlspecialchars($client['commune']) : '<span style="color:#8A99AA;font-weight:400;">Non renseignée</span>' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== HISTORIQUE DES COMMANDES ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-receipt"></i> Historique des commandes</div>
                <div class="text-muted" style="font-size:0.8rem;"><?= $total_commandes ?> commande(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-commandes">
                        <thead>
                            <tr>
                                <th>N° Commande</th>
                                <th>Date</th>
                                <th style="text-align:right;">Total</th>
                                <th style="text-align:center;">Statut</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($commandes)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="bi bi-inbox"></i>
                                            <p>Aucune commande pour ce client</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($commandes as $c): 
                                    $statut_key = $c['statut'] ?? 'en_attente';
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['numero_commande']) ?></strong></td>
                                    <td style="color:#8A99AA;font-size:0.85rem;"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                                    <td style="text-align:right;font-weight:600;color:#C8922A;">
                                        <?= number_format($c['total'], 0, ',', ' ') ?> F
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge-statut statut-<?= $statut_key ?>">
                                            <?= $statut_labels[$statut_key] ?? $statut_key ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="commande_detail.php?id=<?= $c['id'] ?>" class="btn-small blue">
                                            <i class="bi bi-eye"></i> Voir
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== STATISTIQUES PAR STATUT ===== -->
        <?php if(!empty($commandes)): ?>
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-top:24px;">
            <?php foreach($statuts as $key => $count): ?>
                <div style="background:#fff;border-radius:10px;padding:12px;text-align:center;border:1px solid #E8ECF0;">
                    <div style="font-size:1.2rem;font-weight:700;color:#C8922A;"><?= $count ?></div>
                    <div style="font-size:0.6rem;color:#8A99AA;"><?= $statut_labels[$key] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>