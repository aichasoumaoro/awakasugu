<?php
// ============================================
// PRODUITS - ADMIN AWA KA SUGU
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

// Vérification des permissions
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Gestion des Produits';

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
    $stmt = $pdo->prepare("SELECT image_principale FROM produits WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if ($p && !empty($p['image_principale'])) {
        $f = '../uploads/produits/' . $p['image_principale'];
        if (file_exists($f)) unlink($f);
    }
    $pdo->prepare("DELETE FROM produits WHERE id = ?")->execute([$id]);
    $_SESSION['message_produit'] = 'Produit supprimé avec succès.';
    header('Location: produits.php');
    exit;
}

// ============================================
// RECALCULER LA MARGE POUR UN PRODUIT
// ============================================
if (isset($_GET['recalculer_marge']) && is_numeric($_GET['recalculer_marge'])) {
    $id = (int)$_GET['recalculer_marge'];
    $stmt = $pdo->prepare("SELECT prix, prix_achat FROM produits WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if ($p) {
        $marge = $p['prix'] - $p['prix_achat'];
        $marge_pourcentage = ($p['prix_achat'] > 0) ? round(($marge / $p['prix_achat']) * 100, 2) : 0;
        $pdo->prepare("UPDATE produits SET marge = ?, marge_pourcentage = ? WHERE id = ?")
            ->execute([$marge, $marge_pourcentage, $id]);
        $_SESSION['message_produit'] = 'Marge recalculée avec succès.';
    }
    header('Location: produits.php');
    exit;
}

// ============================================
// RECHERCHE ET FILTRES
// ============================================
$search = trim($_GET['search'] ?? '');
$filtre = trim($_GET['filtre'] ?? '');
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND p.nom LIKE ?";
    $params[] = "%$search%";
}
if ($filtre === 'rupture') {
    $where .= " AND p.stock <= 0";
} elseif ($filtre === 'alerte') {
    $where .= " AND p.stock > 0 AND p.stock <= p.seuil_alerte";
} elseif ($filtre === 'promo') {
    $where .= " AND p.est_promo = 1";
} elseif ($filtre === 'marge_elevee') {
    $where .= " AND p.marge_pourcentage > 50";
} elseif ($filtre === 'marge_faible') {
    $where .= " AND p.marge_pourcentage < 10 AND p.marge_pourcentage > 0";
}

$stmt = $pdo->prepare("
    SELECT p.*, c.nom as categorie_nom
    FROM produits p
    LEFT JOIN categories c ON p.categorie_id = c.id
    $where
    ORDER BY p.id DESC
");
$stmt->execute($params);
$produits = $stmt->fetchAll();

// ============================================
// STATISTIQUES
// ============================================
$total = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$en_promo = $pdo->query("SELECT COUNT(*) FROM produits WHERE est_promo=1")->fetchColumn();
$ruptures = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock<=0")->fetchColumn();
$alertes = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock>0 AND stock<=seuil_alerte")->fetchColumn();

// Statistiques de marge
$stats_marge = $pdo->query("
    SELECT 
        COALESCE(AVG(marge_pourcentage), 0) as marge_moyenne,
        COUNT(CASE WHEN marge_pourcentage > 50 THEN 1 END) as nb_marge_elevee,
        COUNT(CASE WHEN marge_pourcentage < 10 AND marge_pourcentage > 0 THEN 1 END) as nb_marge_faible
    FROM produits 
    WHERE prix_achat > 0
")->fetch();

$message = $_SESSION['message_produit'] ?? '';
unset($_SESSION['message_produit']);

// ============================================
// MAINTENANCE
// ============================================
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
            <div class="topbar-title">📦 Gestion des <span>Produits</span></div>
            <div class="topbar-breadcrumb">Administration → Produits</div>
        </div>
        <div class="topbar-right">
            <a href="produit_ajouter.php" class="btn-admin btn-primary">
                <i class="bi bi-plus-lg"></i> Nouveau produit
            </a>
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
                <div class="stat-icon ic-or"><i class="bi bi-box-seam-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $total ?></div>
                    <div class="stat-lbl">Total produits</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-tag-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $en_promo ?></div>
                    <div class="stat-lbl">En promotion</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-orange"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $alertes ?></div>
                    <div class="stat-lbl">Stock faible</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-x-circle-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $ruptures ?></div>
                    <div class="stat-lbl">Rupture de stock</div>
                </div>
            </div>
        </div>

        <!-- ===== STATISTIQUES MARGE ===== -->
        <div class="stats-row" style="margin-top:10px;">
            <div class="stat-box" style="border-left: 4px solid #8E44AD;">
                <div class="stat-icon ic-purple"><i class="bi bi-percent"></i></div>
                <div>
                    <div class="stat-val" style="color:#8E44AD;"><?= number_format($stats_marge['marge_moyenne'] ?? 0, 1) ?>%</div>
                    <div class="stat-lbl">Marge moyenne</div>
                </div>
            </div>
            <div class="stat-box" style="border-left: 4px solid #27AE60;">
                <div class="stat-icon ic-green"><i class="bi bi-arrow-up-circle"></i></div>
                <div>
                    <div class="stat-val" style="color:#27AE60;"><?= $stats_marge['nb_marge_elevee'] ?? 0 ?></div>
                    <div class="stat-lbl">Marge élevée &gt;50%</div>
                </div>
            </div>
            <div class="stat-box" style="border-left: 4px solid #E74C3C;">
                <div class="stat-icon ic-red"><i class="bi bi-arrow-down-circle"></i></div>
                <div>
                    <div class="stat-val" style="color:#E74C3C;"><?= $stats_marge['nb_marge_faible'] ?? 0 ?></div>
                    <div class="stat-lbl">Marge faible &lt;10%</div>
                </div>
            </div>
            <div class="stat-box" style="border-left: 4px solid #F39C12;">
                <div class="stat-icon ic-orange"><i class="bi bi-calculator"></i></div>
                <div>
                    <div class="stat-val" style="color:#F39C12;font-size:1.2rem;">
                        <a href="achats.php" style="color:#F39C12;text-decoration:none;">← Gérer achats</a>
                    </div>
                    <div class="stat-lbl">Pour calculer la marge</div>
                </div>
            </div>
        </div>

        <!-- ===== TOOLBAR ===== -->
        <div class="toolbar">
            <form method="GET" style="display:flex;gap:10px;flex:1;max-width:380px;">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" placeholder="Rechercher un produit..." value="<?= htmlspecialchars($search) ?>">
                    <?php if($filtre): ?>
                        <input type="hidden" name="filtre" value="<?= htmlspecialchars($filtre) ?>">
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-admin btn-primary">
                    <i class="bi bi-search"></i>
                </button>
                <?php if($search || $filtre): ?>
                    <a href="produits.php" class="btn-admin btn-outline">
                        <i class="bi bi-x"></i> Effacer
                    </a>
                <?php endif; ?>
            </form>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <a href="produits.php" class="btn-small <?= !$filtre && !$search ? 'green' : 'gray' ?>" style="padding:6px 14px;">
                    <i class="bi bi-grid"></i> Tous
                </a>
                <a href="produits.php?filtre=promo" class="btn-small <?= $filtre==='promo' ? 'red' : 'gray' ?>" style="padding:6px 14px;">
                    <i class="bi bi-percent"></i> Promos
                </a>
                <a href="produits.php?filtre=alerte" class="btn-small <?= $filtre==='alerte' ? 'orange' : 'gray' ?>" style="padding:6px 14px;">
                    <i class="bi bi-exclamation-triangle"></i> Stock faible
                </a>
                <a href="produits.php?filtre=rupture" class="btn-small <?= $filtre==='rupture' ? 'red' : 'gray' ?>" style="padding:6px 14px;">
                    <i class="bi bi-x-circle"></i> Rupture
                </a>
                <a href="produits.php?filtre=marge_elevee" class="btn-small <?= $filtre==='marge_elevee' ? 'green' : 'gray' ?>" style="padding:6px 14px;">
                    <i class="bi bi-arrow-up"></i> Marge &gt;50%
                </a>
                <a href="produits.php?filtre=marge_faible" class="btn-small <?= $filtre==='marge_faible' ? 'red' : 'gray' ?>" style="padding:6px 14px;">
                    <i class="bi bi-arrow-down"></i> Marge &lt;10%
                </a>
            </div>
        </div>

        <!-- ===== TABLE ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Catalogue</div>
                <div class="text-muted" style="font-size:0.8rem;"><?= count($produits) ?> produit<?= count($produits) > 1 ? 's' : '' ?></div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-produits">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th style="width:60px;">Image</th>
                                <th>Produit</th>
                                <th>Catégorie</th>
                                <th style="text-align:right;">Prix Vente</th>
                                <th style="text-align:right;">Prix Achat</th>
                                <th style="text-align:right;">Marge</th>
                                <th style="text-align:center;">Stock</th>
                                <th style="text-align:center;">Statut</th>
                                <th style="text-align:center;width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($produits)): ?>
                                <tr>
                                    <td colspan="10">
                                        <div class="empty-state">
                                            <i class="bi bi-box-seam"></i>
                                            <p>Aucun produit trouvé</p>
                                            <a href="produit_ajouter.php" style="color:#C8922A;text-decoration:none;font-size:0.85rem;">
                                                <i class="bi bi-plus-circle"></i> Ajouter votre premier produit
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($produits as $p): 
                                    $marge = $p['prix'] - $p['prix_achat'];
                                    $marge_pct = ($p['prix_achat'] > 0) ? round(($marge / $p['prix_achat']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td style="color:#8A99AA;font-size:0.75rem;">#<?= $p['id'] ?></td>
                                    <td>
                                        <?php if(!empty($p['image_principale']) && file_exists('../uploads/produits/'.$p['image_principale'])): ?>
                                            <img src="../uploads/produits/<?= htmlspecialchars($p['image_principale']) ?>" style="width:48px;height:48px;border-radius:8px;object-fit:cover;" alt="<?= htmlspecialchars($p['nom']) ?>">
                                        <?php else: ?>
                                            <div style="width:48px;height:48px;background:#F0F2F5;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:1.1rem;">
                                                <i class="bi bi-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;color:#0D0D0D;font-size:0.88rem;"><?= htmlspecialchars($p['nom']) ?></div>
                                        <?php if($p['categorie_nom']): ?>
                                            <div style="font-size:0.7rem;color:#8A99AA;margin-top:2px;"><?= htmlspecialchars($p['categorie_nom']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color:#666;font-size:0.82rem;">
                                        <?= htmlspecialchars($p['categorie_nom'] ?? '—') ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <span style="font-weight:700;color:#C8922A;font-size:0.9rem;"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                                        <?php if($p['prix_promo'] && $p['prix_promo'] > 0): ?>
                                            <br><span style="font-size:0.65rem;color:#E74C3C;font-weight:600;">Promo: <?= number_format($p['prix_promo'], 0, ',', ' ') ?> F</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;font-size:0.85rem;color:#666;">
                                        <?php if($p['prix_achat'] > 0): ?>
                                            <?= number_format($p['prix_achat'], 0, ',', ' ') ?> F
                                        <?php else: ?>
                                            <span style="color:#bbb;font-size:0.7rem;">Non défini</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <?php if($p['prix_achat'] > 0): ?>
                                            <span style="font-weight:700;color:<?= $marge_pct > 50 ? '#27AE60' : ($marge_pct > 20 ? '#F39C12' : '#E74C3C') ?>;">
                                                <?= number_format($marge, 0, ',', ' ') ?> F
                                            </span>
                                            <br>
                                            <span style="font-size:0.65rem;color:<?= $marge_pct > 50 ? '#27AE60' : ($marge_pct > 20 ? '#F39C12' : '#E74C3C') ?>;">
                                                (<?= $marge_pct ?>%)
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#bbb;font-size:0.7rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if($p['stock'] <= 0): ?>
                                            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:16px;font-size:0.65rem;font-weight:600;background:rgba(231,76,60,0.1);color:#E74C3C;">
                                                <i class="bi bi-x-circle"></i> Rupture
                                            </span>
                                        <?php elseif($p['stock'] <= ($p['seuil_alerte'] ?? 5)): ?>
                                            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:16px;font-size:0.65rem;font-weight:600;background:rgba(230,126,34,0.1);color:#E67E22;">
                                                <i class="bi bi-exclamation-triangle"></i> <?= $p['stock'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:16px;font-size:0.65rem;font-weight:600;background:rgba(27,122,74,0.1);color:#1A7A4A;">
                                                <i class="bi bi-check-circle"></i> <?= $p['stock'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if($p['est_visible']): ?>
                                            <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:16px;font-size:0.6rem;font-weight:600;background:rgba(27,122,74,0.1);color:#1A7A4A;">
                                                <i class="bi bi-eye"></i> Visible
                                            </span>
                                        <?php else: ?>
                                            <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:16px;font-size:0.6rem;font-weight:600;background:#F0F2F5;color:#8A99AA;">
                                                <i class="bi bi-eye-slash"></i> Masqué
                                            </span>
                                        <?php endif; ?>
                                        <?php if($p['est_promo']): ?>
                                            <br><span style="display:inline-flex;gap:4px;padding:2px 8px;border-radius:12px;font-size:0.55rem;font-weight:600;background:rgba(231,76,60,0.1);color:#E74C3C;margin-top:2px;">Promo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <a href="produit_modifier.php?id=<?= $p['id'] ?>" class="btn-small blue" title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if($p['prix_achat'] > 0): ?>
                                            <a href="produits.php?recalculer_marge=<?= $p['id'] ?>" class="btn-small purple" title="Recalculer la marge" style="background:#8E44AD;color:#fff;">
                                                <i class="bi bi-calculator"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="produits.php?supprimer=<?= $p['id'] ?>" class="btn-small red" onclick="return confirm('Supprimer ce produit définitivement ?')" title="Supprimer">
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
     STYLES SUPPLÉMENTAIRES
     ============================================ -->
<style>
.btn-small.purple {
    background: #8E44AD;
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    text-decoration: none;
}
.btn-small.purple:hover {
    background: #6C3483;
}
</style>

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>