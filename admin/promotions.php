<?php
// ============================================
// SESSION ADMIN SÉPARÉE
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

// Suppression
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM promotions WHERE id = ?")->execute([$id]);
    header('Location: promotions.php?msg=supprime');
    exit;
}

// Récupérer les promotions
$promotions = $pdo->query("
    SELECT p.*, pr.nom as produit_nom 
    FROM promotions p 
    LEFT JOIN produits pr ON p.produit_id = pr.id 
    ORDER BY p.created_at DESC
")->fetchAll();

$produits = $pdo->query("SELECT * FROM produits ORDER BY nom")->fetchAll();
$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f5f5f5; display: flex; min-height: 100vh; }
        .sidebar { background: #0D0D0D; min-height: 100vh; position: fixed; top: 0; left: 0; width: 260px; padding: 20px 0; z-index: 100; }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(200,146,42,0.2); }
        .sidebar-logo h3 { color: #C8922A; font-size: 1.3rem; margin: 0; }
        .sidebar-logo small { color: rgba(255,255,255,0.3); font-size: 0.7rem; }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 12px 24px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s; }
        .sidebar-menu li a:hover, .sidebar-menu li a.active { background: rgba(200,146,42,0.1); color: #C8922A; border-left: 3px solid #C8922A; }
        .sidebar-menu li a i { width: 20px; }
        .content { margin-left: 260px; padding: 30px; flex: 1; }
        .btn-ajouter { background: #C8922A; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; border: none; transition: all 0.3s; }
        .btn-ajouter:hover { background: #9A6E1A; color: white; transform: translateY(-2px); }
        .badge-active { background: #28A745; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-inactive { background: #DC3545; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-expired { background: #6C757D; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .table th { background: #0D0D0D; color: #C8922A; border: none; }
        .table td { vertical-align: middle; }
        .btn-action { padding: 5px 10px; font-size: 0.8rem; border-radius: 6px; }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-box {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid #E8ECF0;
            transition: all 0.22s;
        }
        .stat-box:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .ic-or { background: rgba(200,146,42,0.1); color: #C8922A; }
        .ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
        .ic-red { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .ic-blue { background: rgba(41,128,185,0.1); color: #2980B9; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem; font-weight: 700;
            color: #0D0D0D;
            line-height: 1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 2px; }
        .card { border-radius: 12px; overflow: hidden; border: 1px solid #E8ECF0; }
        .card-header { background: #0D0D0D; color: #C8922A; padding: 15px 20px; font-weight: 600; border: none; }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .content { margin-left: 0; padding: 20px 16px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-logo">
        <h3>AWA KA SUGU</h3>
        <small>Administration</small>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Tableau de bord</a></li>
        <li><a href="produits.php"><i class="bi bi-box-seam"></i> Produits</a></li>
        <li><a href="commandes.php"><i class="bi bi-receipt"></i> Commandes</a></li>
        <li><a href="clients.php"><i class="bi bi-people"></i> Clients</a></li>
        <li><a href="plats.php"><i class="bi bi-cup-hot"></i> Restaurant</a></li>
        <li><a href="promotions.php" class="active"><i class="bi bi-percent"></i> Promotions</a></li>
        <li><a href="videos.php"><i class="bi bi-camera-reels"></i> Vidéos</a></li>
        <li><a href="factures.php"><i class="bi bi-file-earmark-text"></i> Factures</a></li>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
    </ul>
</div>

<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>🎁 Gestion des promotions</h2>
            <p style="color:#8A99AA;font-size:0.85rem;">Gérez les offres promotionnelles de votre boutique</p>
        </div>
        <a href="promotion_ajouter.php" class="btn-ajouter">
            <i class="bi bi-plus-circle"></i> Nouvelle promotion
        </a>
    </div>

    <?php if($msg == 'supprime'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> Promotion supprimée avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <?php 
    $total_promos = count($promotions);
    $actives = 0;
    $expirees = 0;
    $inactives = 0;
    $now = date('Y-m-d');
    foreach($promotions as $p) {
        if ($p['est_active'] == 0) {
            $inactives++;
        } elseif ($p['date_fin'] < $now) {
            $expirees++;
        } else {
            $actives++;
        }
    }
    ?>
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-icon ic-or"><i class="bi bi-tags"></i></div>
            <div>
                <div class="stat-val"><?= $total_promos ?></div>
                <div class="stat-lbl">Total promotions</div>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon ic-green"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-val"><?= $actives ?></div>
                <div class="stat-lbl">Actives</div>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon ic-red"><i class="bi bi-clock"></i></div>
            <div>
                <div class="stat-val"><?= $expirees ?></div>
                <div class="stat-lbl">Expirées</div>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon ic-blue"><i class="bi bi-eye-slash"></i></div>
            <div>
                <div class="stat-val"><?= $inactives ?></div>
                <div class="stat-lbl">Inactives</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list"></i> Liste des promotions</span>
            <span style="font-size:0.8rem;color:rgba(255,255,255,0.5);"><?= $total_promos ?> promotion(s)</span>
        </div>
        <div class="card-body p-0" style="overflow-x:auto;">
            <table class="table table-bordered mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Produit</th>
                        <th>Réduction</th>
                        <th>Dates</th>
                        <th>Statut</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($promotions)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4" style="color:#8A99AA;">
                                <i class="bi bi-tags" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                                Aucune promotion pour le moment
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($promotions as $p): 
                            $now = date('Y-m-d');
                            $is_expired = ($p['date_fin'] < $now && $p['est_active'] == 1);
                        ?>
                        <tr>
                            <td>#<?= $p['id'] ?></td>
                            <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                            <td><?= $p['produit_nom'] ?? '<span style="color:#8A99AA;">Tous les produits</span>' ?></td>
                            <td>
                                <span style="font-weight:700;color:#C8922A;">
                                    <?= $p['valeur'] ?> <?= $p['type'] == 'pourcentage' ? '%' : 'FCFA' ?>
                                </span>
                            </td>
                            <td style="font-size:0.8rem;">
                                <?= date('d/m/Y', strtotime($p['date_debut'])) ?><br>
                                <small style="color:#8A99AA;">→ <?= date('d/m/Y', strtotime($p['date_fin'])) ?></small>
                            </td>
                            <td>
                                <?php if($is_expired): ?>
                                    <span class="badge-expired">Expirée</span>
                                <?php elseif($p['est_active']): ?>
                                    <span class="badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <a href="promotion_modifier.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning btn-action" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="promotions.php?supprimer=<?= $p['id'] ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Supprimer cette promotion définitivement ?')" title="Supprimer">
                                    <i class="bi bi-trash"></i>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>