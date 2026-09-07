<?php
// ============================================
// CLIENTS - ADMIN AWA KA SUGU
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

// Vérification des permissions (Clients visible pour super_admin et directeur uniquement)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Gestion des Clients';

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
// SUPPRESSION
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
    $_SESSION['message_clients'] = 'Client supprimé avec succès !';
    header('Location: clients.php');
    exit;
}

// ============================================
// RECHERCHE
// ============================================
$search = trim($_GET['search'] ?? '');
$where = "";
$params = [];

if (!empty($search)) {
    $where = "WHERE nom LIKE ? OR email LIKE ? OR telephone LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Récupérer les clients avec leurs statistiques
$stmt = $pdo->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM commandes WHERE client_id = c.id) as nb_commandes,
           (SELECT COALESCE(SUM(total), 0) FROM commandes WHERE client_id = c.id AND statut IN ('confirmee', 'livree', 'terminee')) as total_depense
    FROM clients c
    $where
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Statistiques
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_depense = $pdo->query("SELECT COALESCE(SUM(total_depense), 0) FROM clients")->fetchColumn();
$nouveaux = $pdo->query("SELECT COUNT(*) FROM clients WHERE DATE(created_at) = CURDATE()")->fetchColumn();

$message = $_SESSION['message_clients'] ?? '';
unset($_SESSION['message_clients']);

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
            <div class="topbar-title">👥 Gestion des <span>Clients</span></div>
            <div class="topbar-breadcrumb">Administration → Clients</div>
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
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_clients) ?></div>
                    <div class="stat-lbl">Total clients inscrits</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_depense, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Total dépensé</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-person-plus-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $nouveaux ?></div>
                    <div class="stat-lbl">Nouveaux aujourd'hui</div>
                </div>
            </div>
        </div>

        <!-- ===== TOOLBAR ===== -->
        <div class="toolbar" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
            <form method="GET" style="display:flex;gap:8px;align-items:center;flex:1;max-width:450px;">
                <div class="search-box" style="position:relative;flex:1;">
                    <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#8A99AA;"></i>
                    <input type="text" name="search" placeholder="Nom, email ou téléphone..." 
                           value="<?= htmlspecialchars($search) ?>"
                           style="width:100%;padding:8px 12px 8px 36px;border:1.5px solid #E8ECF0;border-radius:8px;font-size:0.85rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;">
                </div>
                <button type="submit" class="btn-admin btn-primary" style="white-space:nowrap;padding:8px 20px;">
                    <i class="bi bi-search"></i> Chercher
                </button>
                <?php if($search): ?>
                <a href="clients.php" class="btn-admin btn-outline" style="white-space:nowrap;">
                    <i class="bi bi-x"></i> Effacer
                </a>
                <?php endif; ?>
            </form>
            <div style="font-size:0.85rem;color:#8A99AA;background:#fff;padding:6px 16px;border-radius:20px;border:1px solid #E8ECF0;">
                <strong style="color:#C8922A;"><?= count($clients) ?></strong> client<?= count($clients) > 1 ? 's' : '' ?>
            </div>
        </div>

        <!-- ===== TABLE ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Liste des clients</div>
                <div style="font-size:0.7rem;color:#8A99AA;">
                    <?= count($clients) ?> résultat<?= count($clients) > 1 ? 's' : '' ?>
                    <?php if($search): ?>
                        pour "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-clients" style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;width:50px;">#</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Client</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Email</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Téléphone</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Commandes</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:right;">Total dépensé</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Points</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Inscrit le</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;width:100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($clients)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state" style="text-align:center;padding:40px;color:#8A99AA;">
                                        <i class="bi bi-people" style="font-size:2.5rem;display:block;margin-bottom:10px;color:#D5D5D5;"></i>
                                        <p style="margin:0;font-size:0.85rem;">
                                            Aucun client trouvé
                                            <?php if($search): ?>
                                                pour "<strong><?= htmlspecialchars($search) ?></strong>"
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach($clients as $c): 
                                $initiale = strtoupper(mb_substr($c['nom'] ?? 'C', 0, 1));
                            ?>
                            <tr style="transition:background 0.2s;">
                                <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;color:#8A99AA;font-size:0.75rem;">#<?= $c['id'] ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#C8922A,#E8B55A);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:#fff;flex-shrink:0;">
                                            <?= $initiale ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:600;color:#1A2C3E;font-size:0.85rem;">
                                                <?= htmlspecialchars($c['nom'] ?? '') ?> <?= htmlspecialchars($c['prenom'] ?? '') ?>
                                            </div>
                                            <div style="font-size:0.6rem;color:#8A99AA;margin-top:1px;">
                                                <i class="bi bi-phone" style="font-size:0.55rem;"></i> <?= htmlspecialchars($c['telephone'] ?? '') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;color:#333;font-size:0.8rem;">
                                    <?= !empty($c['email']) ? htmlspecialchars($c['email']) : '<span style="color:#ccc;">—</span>' ?>
                                </td>
                                <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;font-size:0.8rem;">
                                    <?= htmlspecialchars($c['telephone'] ?? '—') ?>
                                </td>
                                <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                    <span style="display:inline-block;padding:2px 14px;border-radius:12px;font-size:0.7rem;font-weight:600;background:<?= ($c['nb_commandes']??0) > 0 ? 'rgba(200,146,42,0.12)' : '#F0F2F5'; ?>;color:<?= ($c['nb_commandes']??0) > 0 ? '#C8922A' : '#8A99AA'; ?>;">
                                        <?= $c['nb_commandes'] ?? 0 ?>
                                    </span>
                                </td>
                                <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:right;font-weight:600;color:<?= ($c['total_depense']??0) > 0 ? '#C8922A' : '#8A99AA' ?>;font-size:0.85rem;">
                                    <?= number_format($c['total_depense'] ?? 0, 0, ',', ' ') ?> F
                                </td>
                                <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                    <span style="display:inline-block;padding:2px 14px;border-radius:12px;font-size:0.7rem;font-weight:600;background:<?= ($c['points_fidelite']??0) > 0 ? 'rgba(39,174,96,0.12)' : '#F0F2F5'; ?>;color:<?= ($c['points_fidelite']??0) > 0 ? '#1A7A4A' : '#8A99AA'; ?>;">
                                        <?= $c['points_fidelite'] ?? 0 ?> pts
                                    </span>
                                </td>
                                <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;color:#8A99AA;font-size:0.7rem;">
                                    <?= date('d/m/Y', strtotime($c['created_at'] ?? 'now')) ?>
                                </td>
                                <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                    <div style="display:flex;gap:4px;justify-content:center;">
                                        <a href="client_detail.php?id=<?= $c['id'] ?>" class="btn-small blue" title="Voir détails" style="padding:4px 10px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:rgba(41,128,185,0.1);color:#2980B9;transition:all 0.2s;border:none;cursor:pointer;">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="clients.php?supprimer=<?= $c['id'] ?>" class="btn-small red" title="Supprimer" onclick="return confirm('Supprimer ce client définitivement ?')" style="padding:4px 10px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:rgba(231,76,60,0.1);color:#E74C3C;transition:all 0.2s;border:none;cursor:pointer;">
                                            <i class="bi bi-trash3"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>