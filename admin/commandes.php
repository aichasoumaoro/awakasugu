<?php
// ============================================
// COMMANDES - ADMIN AWA KA SUGU
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

// Vérification des permissions (Commandes visible pour tous les rôles)
// Tous les admins peuvent voir les commandes

$page_title = 'Gestion des Commandes';

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

// ============================================
// CHANGER LE STATUT D'UNE COMMANDE
// ============================================
if (isset($_GET['statut']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $nouveau_statut = $_GET['statut'];
    $statuts_valides = ['en_attente', 'confirmee', 'en_preparation', 'en_livraison', 'livree', 'annulee'];
    
    if (in_array($nouveau_statut, $statuts_valides)) {
        $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
        $stmt->execute([$id]);
        $commande = $stmt->fetch();
        
        if ($commande) {
            $stmt = $pdo->prepare("UPDATE commandes SET statut = ? WHERE id = ?");
            $stmt->execute([$nouveau_statut, $id]);
            $_SESSION['message_commande'] = 'Statut de la commande mis à jour !';
        }
        header('Location: commandes.php');
        exit;
    }
}

// ============================================
// SUPPRIMER UNE COMMANDE
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM commandes WHERE id = ?")->execute([$id]);
    $_SESSION['message_commande'] = 'Commande supprimée !';
    header('Location: commandes.php');
    exit;
}

$filtre = $_GET['filtre'] ?? 'toutes';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// ============================================
// REQUÊTE GROUPÉE PAR CLIENT
// ============================================
$sql = "
    SELECT 
        c.nom_client,
        c.telephone,
        c.adresse_livraison,
        COUNT(c.id) as nb_commandes,
        SUM(c.total) as total_global,
        MAX(c.created_at) as derniere_commande,
        GROUP_CONCAT(c.numero_commande SEPARATOR ', ') as commandes,
        GROUP_CONCAT(c.statut SEPARATOR ', ') as statuts,
        GROUP_CONCAT(c.id SEPARATOR ',') as commande_ids,
        MAX(c.id) as derniere_id
    FROM commandes c
    WHERE 1=1
";

$params = [];
if ($filtre != 'toutes') {
    $sql .= " AND c.statut = ?";
    $params[] = $filtre;
}

if (!empty($search)) {
    $sql .= " AND (c.nom_client LIKE ? OR c.telephone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY c.nom_client, c.telephone 
          ORDER BY derniere_commande DESC";

// Compter le nombre de groupes
$count_sql = "SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE 1=1";
$count_params = [];
if ($filtre != 'toutes') {
    $count_sql .= " AND statut = ?";
    $count_params[] = $filtre;
}
if (!empty($search)) {
    $count_sql .= " AND (nom_client LIKE ? OR telephone LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
}

$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($count_params);
$total_groupes = (int)$stmt_count->fetchColumn();
$total_pages = ($total_groupes > 0) ? ceil($total_groupes / $per_page) : 1;

$sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$groupes = $stmt->fetchAll();

// Statistiques
$stats = [
    'toutes' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes")->fetchColumn(),
    'en_attente' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'en_attente'")->fetchColumn(),
    'confirmee' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'confirmee'")->fetchColumn(),
    'en_preparation' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'en_preparation'")->fetchColumn(),
    'en_livraison' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'en_livraison'")->fetchColumn(),
    'livree' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'livree'")->fetchColumn(),
    'annulee' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'annulee'")->fetchColumn(),
];

$message = $_SESSION['message_commande'] ?? '';
unset($_SESSION['message_commande']);

$statut_labels = [
    'en_attente' => ['label' => 'En attente', 'class' => 'statut-en_attente'],
    'confirmee' => ['label' => 'Confirmée', 'class' => 'statut-confirmee'],
    'en_preparation' => ['label' => 'Préparation', 'class' => 'statut-en_preparation'],
    'en_livraison' => ['label' => 'Livraison', 'class' => 'statut-en_livraison'],
    'livree' => ['label' => 'Livrée', 'class' => 'statut-livree'],
    'annulee' => ['label' => 'Annulée', 'class' => 'statut-annulee']
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
            <div class="topbar-title">📦 Gestion des <span>Commandes</span></div>
            <div class="topbar-breadcrumb">Administration → Commandes (groupées par client)</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <?php if($message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row" style="grid-template-columns: repeat(7, 1fr);">
            <div class="stat-box" style="border-top: 3px solid #1A2C3E;">
                <div class="stat-val" style="color:#1A2C3E;"><?= $stats['toutes'] ?></div>
                <div class="stat-lbl">Clients</div>
            </div>
            <div class="stat-box" style="border-top: 3px solid #E67E22;">
                <div class="stat-val" style="color:#E67E22;"><?= $stats['en_attente'] ?></div>
                <div class="stat-lbl">En attente</div>
            </div>
            <div class="stat-box" style="border-top: 3px solid #2980B9;">
                <div class="stat-val" style="color:#2980B9;"><?= $stats['confirmee'] ?></div>
                <div class="stat-lbl">Confirmées</div>
            </div>
            <div class="stat-box" style="border-top: 3px solid #8E44AD;">
                <div class="stat-val" style="color:#8E44AD;"><?= $stats['en_preparation'] ?></div>
                <div class="stat-lbl">Préparation</div>
            </div>
            <div class="stat-box" style="border-top: 3px solid #C8922A;">
                <div class="stat-val" style="color:#C8922A;"><?= $stats['en_livraison'] ?></div>
                <div class="stat-lbl">Livraison</div>
            </div>
            <div class="stat-box" style="border-top: 3px solid #27AE60;">
                <div class="stat-val" style="color:#27AE60;"><?= $stats['livree'] ?></div>
                <div class="stat-lbl">Livrées</div>
            </div>
            <div class="stat-box" style="border-top: 3px solid #E74C3C;">
                <div class="stat-val" style="color:#E74C3C;"><?= $stats['annulee'] ?></div>
                <div class="stat-lbl">Annulées</div>
            </div>
        </div>

        <!-- ===== TOOLBAR ===== -->
        <div class="toolbar">
            <form method="GET" style="display:flex;gap:8px;flex:1;max-width:380px;">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" placeholder="Rechercher un client..." value="<?= htmlspecialchars($search) ?>">
                    <?php if($filtre != 'toutes'): ?><input type="hidden" name="filtre" value="<?= htmlspecialchars($filtre) ?>"><?php endif; ?>
                </div>
                <button type="submit" class="btn-admin btn-primary"><i class="bi bi-search"></i></button>
            </form>
            <div style="display:flex;gap:5px;flex-wrap:wrap;">
                <a href="commandes.php" class="btn-small <?= $filtre == 'toutes' ? 'green' : 'gray' ?>" style="padding:6px 14px;">Tous</a>
                <a href="commandes.php?filtre=en_attente" class="btn-small <?= $filtre == 'en_attente' ? 'orange' : 'gray' ?>" style="padding:6px 14px;">En attente</a>
                <a href="commandes.php?filtre=confirmee" class="btn-small <?= $filtre == 'confirmee' ? 'blue' : 'gray' ?>" style="padding:6px 14px;">Confirmées</a>
                <a href="commandes.php?filtre=en_preparation" class="btn-small <?= $filtre == 'en_preparation' ? 'purple' : 'gray' ?>" style="padding:6px 14px;">Préparation</a>
                <a href="commandes.php?filtre=en_livraison" class="btn-small <?= $filtre == 'en_livraison' ? 'or' : 'gray' ?>" style="padding:6px 14px;">Livraison</a>
                <a href="commandes.php?filtre=livree" class="btn-small <?= $filtre == 'livree' ? 'green' : 'gray' ?>" style="padding:6px 14px;">Livrées</a>
                <a href="commandes.php?filtre=annulee" class="btn-small <?= $filtre == 'annulee' ? 'red' : 'gray' ?>" style="padding:6px 14px;">Annulées</a>
            </div>
        </div>

        <!-- ===== TABLE ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-people"></i> Clients avec leurs commandes</div>
                <div class="text-muted" style="font-size:0.8rem;"><?= $total_groupes ?> client(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-commandes">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Téléphone</th>
                                <th style="text-align:center;">Commandes</th>
                                <th style="text-align:right;">Total</th>
                                <th>Dernière</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($groupes)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <p>Aucun client trouvé</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach($groupes as $g): 
                                $statuts = explode(', ', $g['statuts']);
                                $statut_global = 'en_attente';
                                $statut_priority = ['livree' => 5, 'en_livraison' => 4, 'en_preparation' => 3, 'confirmee' => 2, 'en_attente' => 1, 'annulee' => 0];
                                foreach($statuts as $s) {
                                    $s = trim($s);
                                    if(isset($statut_priority[$s]) && $statut_priority[$s] > $statut_priority[$statut_global]) {
                                        $statut_global = $s;
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($g['nom_client']) ?></strong>
                                    <br><span style="color:#8A99AA;font-size:0.65rem;"><?= $g['nb_commandes'] ?> commande(s)</span>
                                </td>
                                <td><?= htmlspecialchars($g['telephone']) ?></td>
                                <td style="text-align:center;">
                                    <span style="display:inline-block;background:#C8922A;color:white;padding:2px 10px;border-radius:12px;font-size:0.7rem;font-weight:600;">
                                        <?= $g['nb_commandes'] ?>
                                    </span>
                                    <br>
                                    <span style="color:#8A99AA;font-size:0.6rem;">
                                        <?php 
                                        $nums = explode(', ', $g['commandes']);
                                        echo implode(', ', array_slice($nums, 0, 2));
                                        if(count($nums) > 2) echo '...';
                                        ?>
                                    </span>
                                </td>
                                <td style="text-align:right;font-weight:600;color:#C8922A;">
                                    <?= number_format($g['total_global'], 0, ',', ' ') ?> F
                                </td>
                                <td style="font-size:0.7rem;color:#8A99AA;">
                                    <?= date('d/m/Y H:i', strtotime($g['derniere_commande'])) ?>
                                    <br>
                                    <span class="badge-statut statut-<?= $statut_global ?>">
                                        <?= $statut_labels[$statut_global]['label'] ?? $statut_global ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <a href="commandes_client.php?telephone=<?= urlencode($g['telephone']) ?>" class="btn-small blue" title="Voir toutes les commandes du client">
                                        <i class="bi bi-eye"></i>
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

        <!-- ===== PAGINATION ===== -->
        <?php if($total_pages > 1): ?>
        <div style="display:flex;justify-content:center;gap:6px;padding:18px 0;">
            <?php if($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&filtre=<?= $filtre ?>&search=<?= urlencode($search) ?>" style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:6px;text-decoration:none;font-size:0.8rem;color:#666;background:white;border:1px solid #E0E0E0;">
                    <i class="bi bi-chevron-left"></i>
                </a>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <?php if($i == $page): ?>
                    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:6px;font-size:0.8rem;background:#C8922A;color:white;border:1px solid #C8922A;"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&filtre=<?= $filtre ?>&search=<?= urlencode($search) ?>" style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:6px;text-decoration:none;font-size:0.8rem;color:#666;background:white;border:1px solid #E0E0E0;"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?>&filtre=<?= $filtre ?>&search=<?= urlencode($search) ?>" style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:6px;text-decoration:none;font-size:0.8rem;color:#666;background:white;border:1px solid #E0E0E0;">
                    <i class="bi bi-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ===== LÉGENDE ===== -->
        <div style="margin-top:15px;padding:12px 20px;background:#fff;border-radius:10px;border:1px solid #E8ECF0;display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
            <span style="font-size:0.7rem;color:#8A99AA;font-weight:600;">📌 Légende statuts :</span>
            <span class="badge-statut statut-en_attente">En attente</span>
            <span class="badge-statut statut-confirmee">Confirmée</span>
            <span class="badge-statut statut-en_preparation">Préparation</span>
            <span class="badge-statut statut-en_livraison">Livraison</span>
            <span class="badge-statut statut-livree">Livrée</span>
            <span class="badge-statut statut-annulee">Annulée</span>
            <span style="font-size:0.65rem;color:#8A99AA;margin-left:5px;">
                <i class="bi bi-info-circle" style="color:#C8922A;"></i>
                Le statut affiché est le plus avancé parmi les commandes du client
            </span>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>