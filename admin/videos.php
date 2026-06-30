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

// Suppression d'une vidéo
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    // Récupérer le fichier vidéo pour le supprimer
    $stmt = $pdo->prepare("SELECT fichier_video FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $video = $stmt->fetch();
    if ($video && !empty($video['fichier_video'])) {
        $f = '../uploads/videos/' . $video['fichier_video'];
        if (file_exists($f)) unlink($f);
    }
    $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$id]);
    header('Location: videos.php?msg=supprime');
    exit;
}

// Activation/Désactivation
if (isset($_GET['activer'])) {
    $id = (int)$_GET['activer'];
    $pdo->prepare("UPDATE videos SET est_active = 1 WHERE id = ?")->execute([$id]);
    header('Location: videos.php?msg=active');
    exit;
}

if (isset($_GET['desactiver'])) {
    $id = (int)$_GET['desactiver'];
    $pdo->prepare("UPDATE videos SET est_active = 0 WHERE id = ?")->execute([$id]);
    header('Location: videos.php?msg=desactive');
    exit;
}

// Récupérer toutes les vidéos
$videos = $pdo->query("SELECT * FROM videos ORDER BY est_active DESC, created_at DESC")->fetchAll();

$msg = $_GET['msg'] ?? '';

function getYoutubeId($url) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/', $url, $matches);
    return $matches[1] ?? '';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vidéos - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jost', sans-serif; background: #F5F7FA; display: flex; min-height: 100vh; }
        
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
        .btn-or { background: #C8922A; color: #fff; }
        .btn-or:hover { background: #9A6E1A; color: #fff; transform: translateY(-1px); }
        .btn-outline { border: 1.5px solid #E0E0E0; color: #555; background: #fff; }
        .btn-outline:hover { border-color: #C8922A; color: #C8922A; }
        
        .content { padding: 28px 32px; flex: 1; }

        .alert-ok {
            background: #D4EDDA;
            border-left: 4px solid #27AE60;
            color: #0A3622;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .table-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #E8ECF0;
        }
        
        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #F0F0F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0D0D0D;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead th {
            background: #0D0D0D;
            color: #C8922A;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            padding: 15px 20px;
            text-align: left;
        }
        
        tbody td {
            padding: 15px 20px;
            font-size: 0.85rem;
            color: #333;
            border-bottom: 1px solid #F8F8F8;
        }
        
        tbody tr:hover td {
            background: #FEFBF5;
        }
        
        .video-preview {
            width: 120px;
            height: 70px;
            background: #1A1A1A;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }
        
        .video-preview video, .video-preview iframe {
            width: 100%;
            height: 100%;
            border: none;
            object-fit: cover;
        }
        
        .badge-active {
            background: rgba(27,122,74,0.1);
            color: #1A7A4A;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-inactive {
            background: rgba(231,76,60,0.1);
            color: #E74C3C;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-local {
            background: rgba(200,146,42,0.1);
            color: #C8922A;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-edit {
            background: rgba(41,128,185,0.1);
            color: #2980B9;
        }
        
        .btn-edit:hover {
            background: #2980B9;
            color: white;
        }
        
        .btn-delete {
            background: rgba(231,76,60,0.1);
            color: #E74C3C;
        }
        
        .btn-delete:hover {
            background: #E74C3C;
            color: white;
        }
        
        .btn-active {
            background: rgba(27,122,74,0.1);
            color: #1A7A4A;
        }
        
        .btn-active:hover {
            background: #1A7A4A;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
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
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .ic-or { background: rgba(200,146,42,0.1); color: #C8922A; }
        .ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
        .ic-red { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: #0D0D0D;
            line-height: 1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 2px; }

        @media (max-width: 1000px) {
            .main { margin-left: 0; }
        }
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
        <a href="dashboard.php" class="nav-item"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
        <a href="point_de_vente.php" class="nav-item"><i class="bi bi-cash-stack"></i> Point de vente</a>
        <a href="produits.php" class="nav-item"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="achats.php" class="nav-item"><i class="bi bi-cart-check"></i> Achats</a>
        <a href="commandes.php" class="nav-item"><i class="bi bi-receipt"></i> Commandes</a>
        <a href="clients.php" class="nav-item"><i class="bi bi-people"></i> Clients</a>

        <div class="nav-section">Restaurant</div>
        <a href="plats.php" class="nav-item"><i class="bi bi-cup-hot"></i> Plats</a>
        <a href="commandes_repas.php" class="nav-item"><i class="bi bi-bag-check"></i> Commandes repas</a>
        <a href="reservations.php" class="nav-item"><i class="bi bi-calendar-check"></i> Réservations</a>

        <div class="nav-section">Gestion</div>
        <a href="maintenance.php" class="nav-item"><i class="bi bi-tools"></i> Maintenance</a>
        <a href="stocks.php" class="nav-item"><i class="bi bi-bar-chart"></i> Stocks</a>
        <a href="promotions.php" class="nav-item"><i class="bi bi-percent"></i> Promotions</a>
        <a href="bannieres.php" class="nav-item"><i class="bi bi-images"></i> Bannières</a>
        <a href="videos.php" class="nav-item active"><i class="bi bi-camera-reels"></i> Vidéos</a>
        <a href="factures.php" class="nav-item"><i class="bi bi-file-earmark-text"></i> Factures</a>
        <a href="rapports.php" class="nav-item"><i class="bi bi-bar-chart"></i> Rapports</a>

        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<!-- ===== MAIN ===== -->
<div class="main">

    <div class="topbar">
        <div>
            <div class="topbar-title">📹 Gestion des <span>Vidéos</span></div>
            <div class="topbar-breadcrumb">Administration → Vidéos</div>
        </div>
        <div class="topbar-right" style="display:flex;gap:15px;">
            <a href="video_ajouter.php" class="btn-admin btn-or">
                <i class="bi bi-plus-lg"></i> Nouvelle vidéo
            </a>
            <a href="../boutique/videos.php" target="_blank" class="btn-admin btn-outline">
                <i class="bi bi-eye"></i> Voir sur le site
            </a>
        </div>
    </div>

    <div class="content">

        <!-- Stats -->
        <?php 
        $total_videos = count($videos);
        $videos_actives = 0;
        $videos_inactives = 0;
        foreach($videos as $v) {
            if($v['est_active']) $videos_actives++;
            else $videos_inactives++;
        }
        ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-camera-reels"></i></div>
                <div>
                    <div class="stat-val"><?= $total_videos ?></div>
                    <div class="stat-lbl">Total vidéos</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $videos_actives ?></div>
                    <div class="stat-lbl">Actives</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-x-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $videos_inactives ?></div>
                    <div class="stat-lbl">Inactives</div>
                </div>
            </div>
        </div>

        <?php if($msg == 'supprime'): ?>
            <div class="alert-ok"><i class="bi bi-check-circle-fill"></i> Vidéo supprimée avec succès.</div>
        <?php elseif($msg == 'ajoute'): ?>
            <div class="alert-ok"><i class="bi bi-check-circle-fill"></i> Vidéo ajoutée avec succès.</div>
        <?php elseif($msg == 'active'): ?>
            <div class="alert-ok"><i class="bi bi-check-circle-fill"></i> Vidéo activée.</div>
        <?php elseif($msg == 'desactive'): ?>
            <div class="alert-ok"><i class="bi bi-check-circle-fill"></i> Vidéo désactivée.</div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-header">
                <h3><i class="bi bi-camera-reels"></i> Liste des vidéos</h3>
                <span style="font-size: 0.8rem; color: #999;"><?= count($videos) ?> vidéo(s)</span>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Aperçu</th>
                        <th>Titre</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($videos)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-camera-reels"></i>
                                    <p>Aucune vidéo trouvée</p>
                                    <a href="video_ajouter.php" style="color: #C8922A;">Ajouter votre première vidéo</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($videos as $v): ?>
                        <tr>
                            <td>
                                <div class="video-preview">
                                    <?php if($v['type'] == 'local' && $v['fichier_video']): ?>
                                        <video src="../uploads/videos/<?= $v['fichier_video'] ?>"></video>
                                    <?php elseif($v['type'] == 'youtube' && $v['url_ou_fichier']): ?>
                                        <iframe src="https://www.youtube.com/embed/<?= getYoutubeId($v['url_ou_fichier']) ?>" frameborder="0"></iframe>
                                    <?php else: ?>
                                        <i class="bi bi-camera-reels" style="font-size: 2rem; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #C8922A;"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><strong><?= htmlspecialchars($v['titre']) ?></strong></td>
                            <td>
                                <?php if($v['type'] == 'local'): ?>
                                    <span class="badge-local">📁 Fichier local</span>
                                <?php else: ?>
                                    <span class="badge-active">🎬 <?= $v['type'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($v['est_active']): ?>
                                    <span class="badge-active">✓ Active</span>
                                <?php else: ?>
                                    <span class="badge-inactive">✗ Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.75rem;"><?= date('d/m/Y', strtotime($v['created_at'])) ?></td>
                            <td style="text-align:center;">
                                <div class="actions" style="justify-content:center;">
                                    <?php if($v['est_active']): ?>
                                        <a href="?desactiver=<?= $v['id'] ?>" class="btn-icon btn-delete" title="Désactiver">
                                            <i class="bi bi-eye-slash"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="?activer=<?= $v['id'] ?>" class="btn-icon btn-active" title="Activer">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?supprimer=<?= $v['id'] ?>" class="btn-icon btn-delete" title="Supprimer"
                                       onclick="return confirm('Supprimer cette vidéo définitivement ?')">
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