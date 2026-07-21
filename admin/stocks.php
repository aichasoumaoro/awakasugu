<?php
// ============================================
// GESTION DES STOCKS - ADMIN AWA KA SUGU
// ============================================

require_once 'session_config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
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
    die("Erreur : " . $e->getMessage());
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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Stocks - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jost', sans-serif; background: #F5F7FA; color: #1A2C3E; display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px;
            background: #0D0D0D;
            border-right: 1px solid rgba(200,146,42,0.2);
            position: fixed;
            top: 0; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(200,146,42,0.15);
        }
        .brand-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: #C8922A;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .brand-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.3);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .admin-user {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 10px 12px;
            background: rgba(200,146,42,0.08);
            border-radius: 10px;
            border: 1px solid rgba(200,146,42,0.15);
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
        .admin-role { font-size: 0.62rem; color: rgba(255,255,255,0.35); letter-spacing: 1px; text-transform: uppercase; }
        .nav-section {
            font-size: 0.58rem;
            color: rgba(255,255,255,0.2);
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
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 0.83rem;
            font-weight: 500;
            border-left: 2px solid transparent;
            transition: all 0.22s;
            margin-bottom: 2px;
        }
        .nav-item i { font-size: 1rem; width: 18px; text-align: center; }
        .nav-item:hover { color: #fff; background: rgba(200,146,42,0.1); border-left-color: rgba(200,146,42,0.5); }
        .nav-item.active { color: #fff; background: rgba(200,146,42,0.15); border-left-color: #C8922A; }
        .nav-item.active i { color: #C8922A; }
        .nav-item.logout:hover { color: #E74C3C; background: rgba(231,76,60,0.1); border-left-color: #E74C3C; }

        .main {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #F5F7FA;
            min-height: 100vh;
        }
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
        .btn-admin {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Jost', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid #E0E6ED;
            color: #5A6B7A;
            background: #fff;
        }
        .btn-admin:hover { border-color: #C8922A; color: #C8922A; }
        .btn-site {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #fff !important;
            border-color: #C8922A !important;
        }
        .btn-site:hover {
            background: linear-gradient(135deg, #9A6E1A, #C8922A) !important;
            color: #fff !important;
        }

        .content { padding: 28px 32px; flex: 1; }
        .alert-success {
            background: #D4EDDA;
            border-left: 4px solid #27AE60;
            color: #0A3622;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-box {
            background: #fff;
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid #E8ECF0;
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .ic-or { background: rgba(200,146,42,0.1); color: #C8922A; }
        .ic-red { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
        .ic-blue { background: rgba(41,128,185,0.1); color: #2980B9; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem; font-weight: 700;
            color: #1A2C3E; line-height: 1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 2px; }

        .table-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #E8ECF0;
        }
        .table-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid #F0F2F5;
        }
        .table-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 600;
            color: #0D0D0D;
        }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0D0D0D;
            color: #C8922A;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 12px 18px;
            text-align: left;
        }
        tbody td {
            padding: 12px 18px;
            font-size: 0.85rem;
            color: #333;
            border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        tbody tr:hover td { background: #FEFBF5; }

        .stock-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .stock-rupture { background: #F8D7DA; color: #721C24; }
        .stock-alerte { background: #FFF3CD; color: #856404; }
        .stock-normal { background: #D4EDDA; color: #1A7A4A; }
        .stock-eleve { background: #E3F2FD; color: #1565C0; }

        .form-stock {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .form-stock input[type="number"] {
            width: 80px;
            padding: 6px 10px;
            border: 1.5px solid #E0E0E0;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: 'Jost', sans-serif;
        }
        .form-stock input[type="number"]:focus {
            outline: none;
            border-color: #C8922A;
        }
        .btn-update {
            padding: 6px 16px;
            border-radius: 6px;
            border: none;
            background: #C8922A;
            color: #fff;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Jost', sans-serif;
        }
        .btn-update:hover { background: #9A6E1A; }

        .empty-state { text-align: center; padding: 40px; color: #999; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 10px; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .form-stock input[type="number"] { width: 60px; }
        }
    </style>
</head>
<body>

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
        <a href="dashboard.php" class="nav-item"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
        <a href="point_de_vente.php" class="nav-item"><i class="bi bi-cash-stack"></i> Point de vente</a>
        <a href="produits.php" class="nav-item"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="commandes.php" class="nav-item"><i class="bi bi-receipt"></i> Commandes</a>
        <a href="clients.php" class="nav-item"><i class="bi bi-people"></i> Clients</a>

        <div class="nav-section">Restaurant</div>
        <a href="plats.php" class="nav-item"><i class="bi bi-cup-hot"></i> Plats</a>
        <a href="commandes_repas.php" class="nav-item"><i class="bi bi-bag-check"></i> Commandes repas</a>
        <a href="reservations.php" class="nav-item"><i class="bi bi-calendar-check"></i> Réservations</a>

        <div class="nav-section">Gestion</div>
        <a href="achats.php" class="nav-item"><i class="bi bi-cart-check"></i> Achats</a>
        <a href="stocks.php" class="nav-item active"><i class="bi bi-bar-chart"></i> Stocks</a>
        <a href="promotions.php" class="nav-item"><i class="bi bi-percent"></i> Promotions</a>
        <a href="videos.php" class="nav-item"><i class="bi bi-camera-reels"></i> Vidéos</a>
        <a href="factures.php" class="nav-item"><i class="bi bi-file-earmark-text"></i> Factures</a>
        <a href="maintenance.php" class="nav-item"><i class="bi bi-tools"></i> Maintenance</a>

        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">📦 Gestion des <span>Stocks</span></div>
            <div class="topbar-breadcrumb">Gestion → Stocks</div>
        </div>
        <div>
            <a href="achats.php" class="btn-admin" style="border-color:#C8922A;color:#C8922A;">
                <i class="bi bi-cart-check"></i> Approvisionner
            </a>
            <a href="../index.php" class="btn-admin btn-site"><i class="bi bi-eye"></i> Voir le site</a>
        </div>
    </div>

    <div class="content">
        <?php if($message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $message ?></div>
        <?php endif; ?>

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

        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">📋 État des stocks</div>
                <div><?= count($produits) ?> produit(s)</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom du produit</th>
                        <th>Prix</th>
                        <th>Stock</th>
                        <th>Seuil alerte</th>
                        <th>Statut</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($produits)): ?>
                        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-box-seam"></i><p>Aucun produit en stock</p></div></td></tr>
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
                            <td>#<?= $p['id'] ?></td>
                            <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                            <td><?= number_format($p['prix'], 0, ',', ' ') ?> F</td>
                            <td style="font-weight:600;font-size:1.1rem;"><?= $p['stock'] ?></td>
                            <td><?= $p['seuil_alerte'] ?? 5 ?></td>
                            <td><span class="stock-badge <?= $stock_class ?>"><?= $stock_label ?></span></td>
                            <td style="text-align:center;">
                                <form method="POST" class="form-stock" style="justify-content:center;">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="number" name="stock" value="<?= $p['stock'] ?>" min="0">
                                    <input type="number" name="seuil_alerte" value="<?= $p['seuil_alerte'] ?? 5 ?>" min="1" style="width:60px;">
                                    <button type="submit" name="mettre_a_jour" class="btn-update">Mettre à jour</button>
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
</body>
</html>