<?php
// ============================================
// STOCKS - ADMIN AWA KA SUGU
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

$page_title = 'Gestion des Stocks';

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
// METTRE À JOUR LE STOCK
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mettre_a_jour'])) {
    $id = (int)$_POST['id'];
    $stock = (int)$_POST['stock'];
    $seuil_alerte = (int)$_POST['seuil_alerte'];
    
    if ($id > 0 && $stock >= 0) {
        $pdo->prepare("UPDATE produits SET stock = ?, seuil_alerte = ? WHERE id = ?")->execute([$stock, $seuil_alerte, $id]);
        $_SESSION['message_stock'] = 'Stock mis à jour avec succès !';
        header('Location: stocks.php');
        exit;
    }
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================
$produits = $pdo->query("SELECT * FROM produits ORDER BY stock ASC")->fetchAll();

// Statistiques
$total_produits = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$produits_rupture = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock <= 0")->fetchColumn();
$produits_alerte = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock > 0 AND stock <= seuil_alerte")->fetchColumn();
$valeur_stock = $pdo->query("SELECT COALESCE(SUM(prix * stock), 0) FROM produits")->fetchColumn();
$total_unites = $pdo->query("SELECT COALESCE(SUM(stock), 0) FROM produits")->fetchColumn();

$message = $_SESSION['message_stock'] ?? '';
unset($_SESSION['message_stock']);

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
            <div class="topbar-title">📦 Gestion des <span>Stocks</span></div>
            <div class="topbar-breadcrumb">Gestion → Stocks</div>
        </div>
        <div class="topbar-right">
            <a href="achats.php" class="btn-admin" style="border-color:#C8922A;color:#C8922A;">
                <i class="bi bi-cart-check"></i> Approvisionner
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
                <div class="stat-icon ic-or"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="stat-val"><?= $total_produits ?></div>
                    <div class="stat-lbl">Produits</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="stat-val"><?= $produits_rupture ?></div>
                    <div class="stat-lbl">En rupture</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-cash"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($valeur_stock, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Valeur du stock</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-box"></i></div>
                <div>
                    <div class="stat-val"><?= $total_unites ?></div>
                    <div class="stat-lbl">Unités en stock</div>
                </div>
            </div>
        </div>

        <!-- ===== TABLEAU DES STOCKS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> État des stocks</div>
                <div style="font-size:0.7rem;color:#8A99AA;"><?= count($produits) ?> produit(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-produits">
                        <thead>
                            <tr>
                                <th style="width:50px;">ID</th>
                                <th>Nom du produit</th>
                                <th style="text-align:right;">Prix</th>
                                <th style="text-align:center;">Stock</th>
                                <th style="text-align:center;">Seuil alerte</th>
                                <th style="text-align:center;">Statut</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($produits)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="bi bi-box-seam"></i>
                                            <p>Aucun produit en stock</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($produits as $p): 
                                    $stock_class = 'stock-normal';
                                    $stock_label = '✅ Normal';
                                    if($p['stock'] <= 0) {
                                        $stock_class = 'stock-rupture';
                                        $stock_label = '❌ Rupture';
                                    } elseif($p['stock'] <= $p['seuil_alerte']) {
                                        $stock_class = 'stock-alerte';
                                        $stock_label = '⚠️ Alerte';
                                    } elseif($p['stock'] > 50) {
                                        $stock_class = 'stock-eleve';
                                        $stock_label = '📦 Élevé';
                                    }
                                ?>
                                <tr>
                                    <td style="color:#8A99AA;font-size:0.75rem;">#<?= $p['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                                    <td style="text-align:right;font-weight:600;color:#C8922A;">
                                        <?= number_format($p['prix'], 0, ',', ' ') ?> F
                                    </td>
                                    <td style="text-align:center;font-weight:700;font-size:1.1rem;">
                                        <?= $p['stock'] ?>
                                    </td>
                                    <td style="text-align:center;"><?= $p['seuil_alerte'] ?? 5 ?></td>
                                    <td style="text-align:center;">
                                        <span class="stock-badge <?= $stock_class ?>"><?= $stock_label ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <form method="POST" class="form-stock" style="justify-content:center;">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <input type="number" name="stock" value="<?= $p['stock'] ?>" min="0" class="stock-input">
                                            <input type="number" name="seuil_alerte" value="<?= $p['seuil_alerte'] ?? 5 ?>" min="1" class="stock-input" style="width:55px;">
                                            <button type="submit" name="mettre_a_jour" class="btn-update">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
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