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
    // Récupérer le nom de l'image pour la supprimer
    $stmt = $pdo->prepare("SELECT image FROM bannieres WHERE id = ?");
    $stmt->execute([$id]);
    $banniere = $stmt->fetch();
    if ($banniere && !empty($banniere['image'])) {
        $fichier = '../uploads/bannieres/' . $banniere['image'];
        if (file_exists($fichier)) {
            unlink($fichier);
        }
    }
    $pdo->prepare("DELETE FROM bannieres WHERE id = ?")->execute([$id]);
    header('Location: bannieres.php?msg=supprime');
    exit;
}

// Changer statut
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE bannieres SET est_active = NOT est_active WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: bannieres.php?msg=toggle');
    exit;
}

// Déplacer une bannière (ordre)
if (isset($_GET['move']) && isset($_GET['direction'])) {
    $id = (int)$_GET['move'];
    $direction = $_GET['direction']; // 'up' ou 'down'
    
    $current = $pdo->prepare("SELECT ordre FROM bannieres WHERE id = ?");
    $current->execute([$id]);
    $current_order = $current->fetchColumn();
    
    if ($current_order !== false) {
        $new_order = $direction === 'up' ? $current_order - 1 : $current_order + 1;
        $pdo->prepare("UPDATE bannieres SET ordre = ? WHERE ordre = ?")->execute([$current_order, $new_order]);
        $pdo->prepare("UPDATE bannieres SET ordre = ? WHERE id = ?")->execute([$new_order, $id]);
    }
    header('Location: bannieres.php');
    exit;
}

$bannieres = $pdo->query("SELECT * FROM bannieres ORDER BY ordre ASC, id ASC")->fetchAll();
$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bannières - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F5F7FA; display: flex; min-height: 100vh; font-family: 'Jost', sans-serif; }
        
        .sidebar { background: #0D0D0D; min-height: 100vh; position: fixed; top: 0; left: 0; width: 260px; padding: 20px 0; z-index: 100; overflow-y: auto; }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(200,146,42,0.2); }
        .sidebar-logo h3 { color: #C8922A; font-size: 1.3rem; margin: 0; }
        .sidebar-logo small { color: rgba(255,255,255,0.3); font-size: 0.7rem; }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 12px 24px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s; }
        .sidebar-menu li a:hover, .sidebar-menu li a.active { background: rgba(200,146,42,0.1); color: #C8922A; border-left: 3px solid #C8922A; }
        .sidebar-menu li a i { width: 20px; }
        
        .content { margin-left: 260px; padding: 30px; flex: 1; }
        .content-header {
            background: white;
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #E8ECF0;
        }
        .content-header h2 { font-size: 1.3rem; font-weight: 700; color: #0D0D0D; }
        .content-header h2 span { color: #C8922A; }
        .content-header .breadcrumb { font-size: 0.75rem; color: #8A99AA; margin: 0; }
        .content-header .breadcrumb a { color: #8A99AA; text-decoration: none; }
        .content-header .breadcrumb a:hover { color: #C8922A; }
        
        .btn-ajouter {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: white;
            padding: 10px 22px;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        .btn-ajouter:hover { background: #9A6E1A; color: white; transform: translateY(-2px); }
        
        .btn-action { padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; transition: all 0.2s; }
        .btn-action i { font-size: 0.9rem; }
        .btn-action:hover { transform: translateY(-1px); }
        
        .badge-active { background: #28A745; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-inactive { background: #DC3545; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        
        .card { border-radius: 12px; border: 1px solid #E8ECF0; overflow: hidden; }
        .card-header { background: #0D0D0D; color: #C8922A; padding: 15px 20px; font-weight: 600; border: none; }
        .card-header i { margin-right: 8px; }
        .table { margin-bottom: 0; }
        .table th { background: #0D0D0D; color: #C8922A; border: none; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .table td { vertical-align: middle; padding: 12px 15px; }
        .table tr:hover td { background: #FEFBF5; }
        
        .banner-preview { 
            width: 100px; 
            height: 60px; 
            object-fit: cover; 
            border-radius: 8px; 
            border: 1px solid #E8ECF0;
        }
        .banner-placeholder {
            width: 100px;
            height: 60px;
            background: #F0F2F5;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8A99AA;
            font-size: 0.7rem;
            border: 1px dashed #D0D5DC;
        }
        .order-badge {
            background: #F0F2F5;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #0D0D0D;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #8A99AA;
        }
        .empty-state i {
            font-size: 3rem;
            display: block;
            margin-bottom: 15px;
            color: #E0E6ED;
        }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .content { margin-left: 0; padding: 20px 16px; }
            .content-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .table { font-size: 0.8rem; }
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
        <li><a href="promotions.php"><i class="bi bi-percent"></i> Promotions</a></li>
        <li><a href="bannieres.php" class="active"><i class="bi bi-images"></i> Bannières</a></li>
        <li><a href="videos.php"><i class="bi bi-camera-reels"></i> Vidéos</a></li>
        <li><a href="factures.php"><i class="bi bi-file-earmark-text"></i> Factures</a></li>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
    </ul>
</div>

<div class="content">
    <div class="content-header">
        <div>
            <h2><span>🖼️</span> Gestion des bannières</h2>
            <div class="breadcrumb">
                <a href="dashboard.php">Administration</a> &gt; 
                Bannières
            </div>
        </div>
        <a href="banniere_ajouter.php" class="btn-ajouter">
            <i class="bi bi-plus-circle"></i> Nouvelle bannière
        </a>
    </div>

    <?php if($msg == 'supprime'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> Bannière supprimée avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif($msg == 'toggle'): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle-fill"></i> Statut de la bannière modifié !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list"></i> Liste des bannières (carrousel accueil)</span>
            <span style="font-size:0.8rem;color:rgba(255,255,255,0.5);"><?= count($bannieres) ?> bannière(s)</span>
        </div>
        <div class="card-body p-0" style="overflow-x:auto;">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th style="width:50px;">Ordre</th>
                        <th style="width:120px;">Image</th>
                        <th>Titre</th>
                        <th>Lien</th>
                        <th>Statut</th>
                        <th style="text-align:center;width:200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($bannieres)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-images"></i>
                                    <p>Aucune bannière pour le moment</p>
                                    <a href="banniere_ajouter.php" class="btn-ajouter" style="display:inline-flex;padding:8px 20px;font-size:0.85rem;">
                                        <i class="bi bi-plus-circle"></i> Ajouter une bannière
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($bannieres as $b): ?>
                        <tr>
                            <td>
                                <span class="order-badge">#<?= $b['ordre'] ?></span>
                            </td>
                            <td>
                                <?php if(!empty($b['image']) && file_exists('../uploads/bannieres/'.$b['image'])): ?>
                                    <img src="../uploads/bannieres/<?= $b['image'] ?>" class="banner-preview" alt="<?= htmlspecialchars($b['titre'] ?? 'Bannière') ?>">
                                <?php else: ?>
                                    <div class="banner-placeholder">
                                        <i class="bi bi-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($b['titre'] ?? 'Sans titre') ?></strong>
                                <?php if(!empty($b['description'])): ?>
                                    <br><small style="color:#8A99AA;"><?= htmlspecialchars(substr($b['description'], 0, 50)) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!empty($b['lien'])): ?>
                                    <a href="<?= $b['lien'] ?>" target="_blank" class="btn btn-sm btn-outline-primary btn-action">
                                        <i class="bi bi-link-45deg"></i> Voir
                                    </a>
                                <?php else: ?>
                                    <span style="color:#8A99AA;font-size:0.8rem;">Aucun lien</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($b['est_active']): ?>
                                    <span class="badge-active"><i class="bi bi-check-circle"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge-inactive"><i class="bi bi-x-circle"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    <a href="banniere_modifier.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-warning btn-action" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="bannieres.php?toggle=<?= $b['id'] ?>" class="btn btn-sm <?= $b['est_active'] ? 'btn-secondary' : 'btn-success' ?> btn-action" title="<?= $b['est_active'] ? 'Désactiver' : 'Activer' ?>">
                                        <i class="bi <?= $b['est_active'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                    </a>
                                    <a href="bannieres.php?supprimer=<?= $b['id'] ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Supprimer cette bannière définitivement ?')" title="Supprimer">
                                        <i class="bi bi-trash"></i>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>