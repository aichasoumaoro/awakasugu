<?php
// ============================================
// SESSION ADMIN SÉPARÉE
// ============================================
require_once 'session_config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$host   = 'localhost';
$dbname = 'awakasugu_db';
$user   = 'root';
$pass   = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Suppression
if (isset($_GET['supprimer'])) {
    $id   = (int)$_GET['supprimer'];
    $stmt = $pdo->prepare("SELECT image_principale FROM produits WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if ($p && !empty($p['image_principale'])) {
        $f = '../uploads/produits/' . $p['image_principale'];
        if (file_exists($f)) unlink($f);
    }
    $pdo->prepare("DELETE FROM produits WHERE id = ?")->execute([$id]);
    header('Location: produits.php?msg=supprime');
    exit;
}

// Recherche
$search  = trim($_GET['search'] ?? '');
$filtre  = trim($_GET['filtre'] ?? '');
$where   = "WHERE 1=1";
$params  = [];
if ($search) { $where .= " AND p.nom LIKE ?"; $params[] = "%$search%"; }
if ($filtre === 'rupture') { $where .= " AND p.stock <= 0"; }
elseif ($filtre === 'alerte') { $where .= " AND p.stock > 0 AND p.stock <= p.seuil_alerte"; }
elseif ($filtre === 'promo') { $where .= " AND p.est_promo = 1"; }

$stmt = $pdo->prepare("
    SELECT p.*, c.nom as categorie_nom
    FROM produits p
    LEFT JOIN categories c ON p.categorie_id = c.id
    $where
    ORDER BY p.id DESC
");
$stmt->execute($params);
$produits = $stmt->fetchAll();

// Stats
$total      = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$en_promo   = $pdo->query("SELECT COUNT(*) FROM produits WHERE est_promo=1")->fetchColumn();
$ruptures   = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock<=0")->fetchColumn();
$alertes    = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock>0 AND stock<=seuil_alerte")->fetchColumn();

$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits — Awa Ka Sugu Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Jost', sans-serif; background: #F5F7FA; color: #1A2C3E; display: flex; min-height: 100vh; }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 260px;
            background: #0D0D0D;
            border-right: 1px solid rgba(200,146,42,0.18);
            position: fixed;
            top: 0; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(200,146,42,0.12);
        }
        .brand-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #C8922A;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .brand-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.2);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .admin-user {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 16px;
            padding: 10px 12px;
            background: rgba(200,146,42,0.07);
            border-radius: 8px;
            border: 1px solid rgba(200,146,42,0.12);
        }
        .admin-avatar {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, #C8922A, #E2B96A);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; color: #fff; font-weight: 600;
            flex-shrink: 0;
        }
        .admin-name { font-size: 0.82rem; color: #fff; font-weight: 500; }
        .admin-role { font-size: 0.62rem; color: rgba(255,255,255,0.3); letter-spacing: 1px; text-transform: uppercase; }
        .nav-section {
            font-size: 0.58rem;
            color: rgba(255,255,255,0.18);
            letter-spacing: 2.5px;
            text-transform: uppercase;
            padding: 18px 24px 6px;
        }
        .sidebar nav { flex: 1; padding: 8px 12px; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 14px;
            border-radius: 8px;
            color: rgba(255,255,255,0.48);
            text-decoration: none;
            font-size: 0.83rem;
            font-weight: 500;
            border-left: 2px solid transparent;
            transition: all 0.22s;
            margin-bottom: 2px;
        }
        .nav-item i { font-size: 1rem; width: 18px; text-align: center; }
        .nav-item:hover { color: #fff; background: rgba(200,146,42,0.08); border-left-color: rgba(200,146,42,0.4); }
        .nav-item.active { color: #fff; background: rgba(200,146,42,0.12); border-left-color: #C8922A; }
        .nav-item.active i { color: #C8922A; }
        .nav-item.logout { color: rgba(231,76,60,0.6); }
        .nav-item.logout:hover { color: #E74C3C; background: rgba(231,76,60,0.08); border-left-color: #E74C3C; }

        /* ===== MAIN ===== */
        .main {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #F5F7FA;
            min-height: 100vh;
        }

        /* Topbar */
        .topbar {
            background: #fff;
            border-bottom: 1px solid #E8ECF0;
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #0D0D0D;
        }
        .topbar-title span { color: #C8922A; }
        .topbar-breadcrumb { font-size: 0.75rem; color: #999; margin-top: 2px; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .btn-admin {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Jost', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 9px 20px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.22s;
            cursor: pointer;
            border: none;
            white-space: nowrap;
        }
        .btn-or    { background: #C8922A; color: #fff; }
        .btn-or:hover { background: #9A6E1A; color: #fff; transform: translateY(-1px); }
        .btn-outline { border: 1.5px solid #E0E0E0; color: #555; background: #fff; }
        .btn-outline:hover { border-color: #C8922A; color: #C8922A; }

        /* Content */
        .content { padding: 28px 32px; flex: 1; }

        /* Stats */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-box {
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid #E8ECF0;
            transition: all 0.22s;
        }
        .stat-box:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .ic-or     { background: rgba(200,146,42,0.1); color: #C8922A; }
        .ic-green  { background: rgba(27,122,74,0.1);  color: #1A7A4A; }
        .ic-red    { background: rgba(231,76,60,0.1);  color: #E74C3C; }
        .ic-orange { background: rgba(230,126,34,0.1); color: #E67E22; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem; font-weight: 700;
            color: #0D0D0D; line-height: 1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 3px; }

        /* Alert message */
        .alert-ok {
            display: flex; align-items: center; gap: 10px;
            background: #D4EDDA; border-left: 4px solid #27AE60;
            color: #0A3622; padding: 13px 18px; border-radius: 8px;
            font-size: 0.88rem; margin-bottom: 24px;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-box {
            display: flex; align-items: center; gap: 10px;
            background: #fff; border: 1.5px solid #E8ECF0;
            border-radius: 8px; padding: 0 14px; height: 40px;
            transition: border-color 0.2s; flex: 1; max-width: 340px;
        }
        .search-box:focus-within { border-color: #C8922A; }
        .search-box i { color: #bbb; font-size: 0.9rem; }
        .search-box input {
            border: none; outline: none;
            font-family: 'Jost', sans-serif; font-size: 0.85rem;
            color: #333; background: transparent; width: 100%;
        }
        .filtre-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 6px;
            font-family: 'Jost', sans-serif; font-size: 0.75rem;
            font-weight: 600; text-decoration: none;
            border: 1.5px solid #E0E0E0;
            background: #fff; color: #666;
            transition: all 0.2s; white-space: nowrap;
        }
        .filtre-btn:hover { border-color: #C8922A; color: #C8922A; }
        .filtre-btn.actif { background: #C8922A; color: #fff; border-color: #C8922A; }
        .filtre-btn.rouge.actif { background: #E74C3C; border-color: #E74C3C; }
        .filtre-btn.orange.actif { background: #E67E22; border-color: #E67E22; }

        /* Table */
        .table-card {
            background: #fff; border-radius: 14px;
            overflow: hidden; border: 1px solid #E8ECF0;
        }
        .table-card-header {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            border-bottom: 1px solid #F0F2F5;
        }
        .table-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1rem; font-weight: 600; color: #0D0D0D;
        }
        .table-count { font-size: 0.78rem; color: #8A99AA; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0D0D0D; color: #C8922A;
            font-family: 'Jost', sans-serif;
            font-size: 0.68rem; font-weight: 600;
            letter-spacing: 1px; text-transform: uppercase;
            padding: 14px 20px; text-align: left;
        }
        tbody td {
            padding: 14px 20px; font-size: 0.84rem;
            color: #333; border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #FEFBF5; }

        .prod-img {
            width: 48px; height: 48px;
            border-radius: 10px; object-fit: cover;
        }
        .prod-img-placeholder {
            width: 48px; height: 48px;
            background: #F0F2F5; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #ccc; font-size: 1.1rem;
        }
        .prod-name { font-weight: 600; color: #0D0D0D; font-size: 0.88rem; }
        .prod-cat  { font-size: 0.74rem; color: #8A99AA; margin-top: 2px; }

        .prix { font-weight: 700; color: #C8922A; font-size: 0.92rem; }
        .prix-promo { font-size: 0.78rem; color: #bbb; text-decoration: line-through; margin-left: 6px; }

        .stock-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 12px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600;
        }
        .stock-ok     { background: rgba(27,122,74,0.1);  color: #1A7A4A; }
        .stock-alerte { background: rgba(230,126,34,0.1); color: #E67E22; }
        .stock-rupture{ background: rgba(231,76,60,0.1);  color: #E74C3C; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 20px;
            font-size: 0.65rem; font-weight: 600;
        }
        .badge-visible { background: rgba(27,122,74,0.1); color: #1A7A4A; }
        .badge-hidden  { background: #F0F2F5; color: #8A99AA; }
        .badge-new     { background: rgba(200,146,42,0.1); color: #C8922A; }
        .badge-promo   { background: rgba(231,76,60,0.1); color: #E74C3C; }

        .actions { display: flex; gap: 6px; justify-content: center; }
        .btn-act {
            width: 32px; height: 32px; border-radius: 7px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.88rem; text-decoration: none;
            transition: all 0.2s; cursor: pointer; border: none;
        }
        .btn-edit   { background: rgba(41,128,185,0.1);  color: #2980B9; }
        .btn-edit:hover  { background: #2980B9; color: #fff; }
        .btn-delete { background: rgba(231,76,60,0.1);  color: #E74C3C; }
        .btn-delete:hover { background: #E74C3C; color: #fff; }

        .empty-state {
            text-align: center; padding: 60px 20px; color: #8A99AA;
        }
        .empty-state i { font-size: 3rem; display: block; margin-bottom: 16px; }

        @media (max-width: 1100px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">AWA KA SUGU</div>
        <div class="brand-sub">Administration</div>
        <div class="admin-user">
            <div class="admin-avatar">A</div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_nom'] ?? 'Awa Doumbia') ?></div>
                <div class="admin-role">Administratrice</div>
            </div>
        </div>
    </div>
    <nav>
        <div class="nav-section">Principal</div>
        <a href="dashboard.php"      class="nav-item"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
        <a href="point_de_vente.php" class="nav-item"><i class="bi bi-cash-stack"></i> Point de vente</a>
        <a href="produits.php"       class="nav-item active"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="achats.php"         class="nav-item"><i class="bi bi-cart-check"></i> Achats</a>
        <a href="commandes.php"      class="nav-item"><i class="bi bi-receipt"></i> Commandes</a>
        <a href="clients.php"        class="nav-item"><i class="bi bi-people"></i> Clients</a>

        <div class="nav-section">Restaurant</div>
        <a href="plats.php"          class="nav-item"><i class="bi bi-cup-hot"></i> Plats</a>
        <a href="commandes_repas.php"class="nav-item"><i class="bi bi-bag-check"></i> Commandes repas</a>
        <a href="reservations.php"   class="nav-item"><i class="bi bi-calendar-check"></i> Réservations</a>

        <div class="nav-section">Gestion</div>
        <a href="maintenance.php"    class="nav-item"><i class="bi bi-tools"></i> Maintenance</a>
        <a href="stocks.php"         class="nav-item"><i class="bi bi-bar-chart"></i> Stocks</a>
        <a href="promotions.php"     class="nav-item"><i class="bi bi-percent"></i> Promotions</a>
        <a href="bannieres.php"      class="nav-item"><i class="bi bi-images"></i> Bannières</a>
        <a href="videos.php"         class="nav-item"><i class="bi bi-camera-reels"></i> Vidéos</a>
        <a href="factures.php"       class="nav-item"><i class="bi bi-file-earmark-text"></i> Factures</a>
        <a href="rapports.php"       class="nav-item"><i class="bi bi-bar-chart"></i> Rapports</a>

        <div class="nav-section">Compte</div>
        <a href="../index.php"       class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php"         class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<!-- ===== MAIN ===== -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div>
            <div class="topbar-title">📦 Gestion des <span>Produits</span></div>
            <div class="topbar-breadcrumb">Administration → Produits</div>
        </div>
        <div class="topbar-right">
            <a href="produit_ajouter.php" class="btn-admin btn-or">
                <i class="bi bi-plus-lg"></i> Nouveau produit
            </a>
            <a href="../index.php" target="_blank" class="btn-admin btn-outline">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <div class="content">

        <!-- Alert -->
        <?php if($msg == 'supprime'): ?>
        <div class="alert-ok">
            <i class="bi bi-check-circle-fill"></i> Produit supprimé avec succès.
        </div>
        <?php elseif($msg == 'ajoute'): ?>
        <div class="alert-ok">
            <i class="bi bi-check-circle-fill"></i> Produit ajouté avec succès.
        </div>
        <?php elseif($msg == 'modifie'): ?>
        <div class="alert-ok">
            <i class="bi bi-check-circle-fill"></i> Produit modifié avec succès.
        </div>
        <?php endif; ?>

        <!-- Stats -->
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

        <!-- Toolbar -->
        <div class="toolbar">
            <form method="GET" style="display:flex;gap:10px;flex:1;max-width:380px;">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search"
                           placeholder="Rechercher un produit..."
                           value="<?= htmlspecialchars($search) ?>">
                    <?php if($filtre): ?>
                        <input type="hidden" name="filtre" value="<?= htmlspecialchars($filtre) ?>">
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-admin btn-or">
                    <i class="bi bi-search"></i>
                </button>
            </form>
            <a href="produits.php" class="filtre-btn <?= !$filtre && !$search ? 'actif' : '' ?>">
                <i class="bi bi-grid"></i> Tous
            </a>
            <a href="produits.php?filtre=promo" class="filtre-btn <?= $filtre==='promo' ? 'actif' : '' ?>">
                <i class="bi bi-percent"></i> Promos
            </a>
            <a href="produits.php?filtre=alerte" class="filtre-btn orange <?= $filtre==='alerte' ? 'actif orange' : '' ?>">
                <i class="bi bi-exclamation-triangle"></i> Stock faible
            </a>
            <a href="produits.php?filtre=rupture" class="filtre-btn rouge <?= $filtre==='rupture' ? 'actif rouge' : '' ?>">
                <i class="bi bi-x-circle"></i> Rupture
            </a>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">📋 Catalogue</div>
                <div class="table-count"><?= count($produits) ?> produit<?= count($produits) > 1 ? 's' : '' ?></div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Image</th>
                        <th>Produit</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Stock</th>
                        <th>Statut</th>
                        <th>Tags</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($produits)): ?>
                    <tr>
                        <td colspan="9">
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
                    <?php foreach($produits as $p): ?>
                    <tr>
                        <td style="color:#8A99AA;font-size:0.78rem;">#<?= $p['id'] ?></td>
                        <td>
                            <?php if(!empty($p['image_principale']) && file_exists('../uploads/produits/'.$p['image_principale'])): ?>
                                <img src="../uploads/produits/<?= htmlspecialchars($p['image_principale']) ?>"
                                     class="prod-img" alt="<?= htmlspecialchars($p['nom']) ?>">
                            <?php else: ?>
                                <div class="prod-img-placeholder"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="prod-name"><?= htmlspecialchars($p['nom']) ?></div>
                            <?php if($p['categorie_nom']): ?>
                                <div class="prod-cat"><?= htmlspecialchars($p['categorie_nom']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="color:#666;font-size:0.82rem;">
                            <?= htmlspecialchars($p['categorie_nom'] ?? '—') ?>
                        </td>
                        <td>
                            <span class="prix"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</span>
                            <?php if($p['prix_promo']): ?>
                                <span class="prix-promo"><?= number_format($p['prix_promo'], 0, ',', ' ') ?> FCFA</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($p['stock'] <= 0): ?>
                                <span class="stock-badge stock-rupture"><i class="bi bi-x-circle"></i> Rupture</span>
                            <?php elseif($p['stock'] <= ($p['seuil_alerte'] ?? 5)): ?>
                                <span class="stock-badge stock-alerte"><i class="bi bi-exclamation-triangle"></i> <?= $p['stock'] ?> restants</span>
                            <?php else: ?>
                                <span class="stock-badge stock-ok"><i class="bi bi-check-circle"></i> <?= $p['stock'] ?> unités</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($p['est_visible']): ?>
                                <span class="status-badge badge-visible"><i class="bi bi-eye"></i> Visible</span>
                            <?php else: ?>
                                <span class="status-badge badge-hidden"><i class="bi bi-eye-slash"></i> Masqué</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                <?php if($p['est_nouveau']): ?>
                                    <span class="status-badge badge-new">Nouveau</span>
                                <?php endif; ?>
                                <?php if($p['est_promo']): ?>
                                    <span class="status-badge badge-promo">Promo</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="produit_modifier.php?id=<?= $p['id'] ?>" class="btn-act btn-edit" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="produits.php?supprimer=<?= $p['id'] ?>"
                                   class="btn-act btn-delete" title="Supprimer"
                                   onclick="return confirm('Supprimer ce produit définitivement ?')">
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

</body>
</html>