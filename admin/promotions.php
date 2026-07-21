<?php
// ============================================
// PROMOTIONS ADMIN - Gestion des promotions
// ============================================

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

// ============================================
// SYNCHRONISATION - Met à jour les produits en promo
// ============================================
function synchroniserProduitsPromo($pdo) {
    try {
        // 1. Réinitialiser tous les produits
        $pdo->exec("UPDATE produits SET est_promo = 0, prix_promo = NULL");
        
        // 2. Récupérer les promotions actives (avec vérification des dates)
        $stmt = $pdo->query("
            SELECT * FROM promotions 
            WHERE est_active = 1 
            AND date_debut <= CURDATE() 
            AND date_fin >= CURDATE()
        ");
        $promotions = $stmt->fetchAll();
        
        if (empty($promotions)) {
            return true;
        }
        
        foreach($promotions as $promo) {
            if ($promo['produit_id'] && $promo['produit_id'] > 0) {
                if ($promo['type'] == 'pourcentage') {
                    $stmt = $pdo->prepare("
                        UPDATE produits 
                        SET est_promo = 1, 
                            prix_promo = ROUND(prix - (prix * ? / 100), 0)
                        WHERE id = ?
                    ");
                    $stmt->execute([$promo['valeur'], $promo['produit_id']]);
                } elseif ($promo['type'] == 'montant_fixe') {
                    $stmt = $pdo->prepare("
                        UPDATE produits 
                        SET est_promo = 1, 
                            prix_promo = ROUND(prix - ?, 0)
                        WHERE id = ?
                    ");
                    $stmt->execute([$promo['valeur'], $promo['produit_id']]);
                }
            }
        }
        
        return true;
    } catch(PDOException $e) {
        error_log("Erreur synchronisation: " . $e->getMessage());
        return false;
    }
}

// ============================================
// AJOUTER UNE PROMOTION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $nom = $_POST['nom'] ?? '';
    $description = $_POST['description'] ?? '';
    $produit_id = $_POST['produit_id'] ? (int)$_POST['produit_id'] : null;
    $type = $_POST['type'] ?? 'pourcentage';
    $valeur = (float)($_POST['valeur'] ?? 0);
    $date_debut = $_POST['date_debut'] ?? date('Y-m-d');
    $date_fin = $_POST['date_fin'] ?? date('Y-m-d', strtotime('+30 days'));
    
    if ($nom && $valeur > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO promotions (nom, description, produit_id, type, valeur, date_debut, date_fin, est_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$nom, $description, $produit_id, $type, $valeur, $date_debut, $date_fin]);
            synchroniserProduitsPromo($pdo);
            header('Location: promotions.php?msg=ajoute');
            exit;
        } catch(PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez remplir tous les champs obligatoires.";
    }
}

// ============================================
// MODIFIER UNE PROMOTION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier') {
    $id = (int)($_POST['id'] ?? 0);
    $nom = $_POST['nom'] ?? '';
    $description = $_POST['description'] ?? '';
    $produit_id = $_POST['produit_id'] ? (int)$_POST['produit_id'] : null;
    $type = $_POST['type'] ?? 'pourcentage';
    $valeur = (float)($_POST['valeur'] ?? 0);
    $date_debut = $_POST['date_debut'] ?? date('Y-m-d');
    $date_fin = $_POST['date_fin'] ?? date('Y-m-d', strtotime('+30 days'));
    $est_active = isset($_POST['est_active']) ? 1 : 0;
    
    if ($id && $nom && $valeur > 0) {
        try {
            $stmt = $pdo->prepare("
                UPDATE promotions 
                SET nom = ?, description = ?, produit_id = ?, type = ?, valeur = ?, 
                    date_debut = ?, date_fin = ?, est_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$nom, $description, $produit_id, $type, $valeur, $date_debut, $date_fin, $est_active, $id]);
            synchroniserProduitsPromo($pdo);
            header('Location: promotions.php?msg=modifie');
            exit;
        } catch(PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez remplir tous les champs obligatoires.";
    }
}

// ============================================
// SUPPRIMER UNE PROMOTION
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    try {
        $pdo->prepare("DELETE FROM promotions WHERE id = ?")->execute([$id]);
        synchroniserProduitsPromo($pdo);
        header('Location: promotions.php?msg=supprime');
        exit;
    } catch(PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// ============================================
// TOGGLE ACTIF/INACTIF
// ============================================
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    try {
        $stmt = $pdo->prepare("UPDATE promotions SET est_active = NOT est_active WHERE id = ?");
        $stmt->execute([$id]);
        synchroniserProduitsPromo($pdo);
        header('Location: promotions.php?msg=toggle');
        exit;
    } catch(PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// ============================================
// FORCER LA SYNCHRONISATION (utilisé pour le cron)
// ============================================
if (isset($_GET['cron_sync'])) {
    $sync_result = synchroniserProduitsPromo($pdo);
    echo $sync_result ? 'OK' : 'ERROR';
    exit;
}

// ============================================
// RÉCUPÉRATION DES DONNÉES
// ============================================
$promotions = $pdo->query("
    SELECT p.*, pr.nom as produit_nom 
    FROM promotions p 
    LEFT JOIN produits pr ON p.produit_id = pr.id 
    ORDER BY p.created_at DESC
")->fetchAll();

$produits = $pdo->query("SELECT DISTINCT id, nom FROM produits WHERE est_visible = 1 ORDER BY nom")->fetchAll();

$msg = $_GET['msg'] ?? '';
$error = $error ?? '';

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

$stmt = $pdo->query("SELECT COUNT(*) FROM produits WHERE est_promo = 1");
$nb_produits_promo = $stmt->fetchColumn();
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #F0F2F5; 
            color: #1A1A2E; 
            display: flex; 
            min-height: 100vh; 
        }

        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #0D0D0D 0%, #1A1A2E 100%);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            padding: 0;
            overflow-y: auto;
            border-right: 1px solid rgba(200,146,42,0.1);
        }
        .sidebar-logo {
            padding: 28px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            text-align: center;
        }
        .sidebar-logo h3 {
            font-family: 'Inter', sans-serif;
            color: #C8922A;
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 0;
        }
        .sidebar-logo small {
            color: rgba(255,255,255,0.2);
            font-size: 0.55rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: block;
            margin-top: 3px;
        }
        .sidebar-menu {
            list-style: none;
            padding: 12px;
            margin: 0;
        }
        .sidebar-menu .nav-section {
            font-size: 0.5rem;
            color: rgba(255,255,255,0.15);
            text-transform: uppercase;
            letter-spacing: 2px;
            padding: 12px 12px 6px;
            font-weight: 600;
        }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-bottom: 1px;
        }
        .sidebar-menu li a i { font-size: 1rem; width: 20px; text-align: center; }
        .sidebar-menu li a:hover { background: rgba(200,146,42,0.08); color: #fff; }
        .sidebar-menu li a.active { background: rgba(200,146,42,0.12); color: #C8922A; }
        .sidebar-menu li a.active i { color: #C8922A; }
        .sidebar-menu li a.logout:hover { color: #E74C3C; background: rgba(231,76,60,0.08); }

        .content {
            margin-left: 260px;
            padding: 30px 35px;
            flex: 1;
            width: calc(100% - 260px);
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .top-bar h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1A1A2E;
            margin: 0;
        }
        .top-bar h1 span { color: #C8922A; }
        .top-bar .subtitle {
            color: #8A99AA;
            font-size: 0.8rem;
            display: block;
            margin-top: 2px;
            font-weight: 400;
        }

        .btn-gold {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A2E;
            border: none;
            padding: 9px 22px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(200,146,42,0.3); color: #1A1A2E; }

        .alert-modern {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid;
            font-size: 0.85rem;
            animation: slideDown 0.5s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-modern.success { background: #E8F5E9; border-color: #28A745; color: #1A5A2E; }
        .alert-modern.info { background: #E3F2FD; border-color: #2980B9; color: #1A3A5A; }
        .alert-modern.danger { background: #FBE9E7; border-color: #E74C3C; color: #7A1A1A; }

        .sync-info {
            background: #F0F7FF;
            border: 1px solid #D6E9FF;
            border-radius: 10px;
            padding: 12px 18px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .sync-info .label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            color: #2C3E50;
        }
        .sync-info .badge-sync {
            background: #28A745;
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .sync-info .count-produits {
            color: #28A745;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px 18px;
            border: 1px solid #E8ECF0;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
        .stat-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .stat-icon.gold { background: rgba(200,146,42,0.1); color: #C8922A; }
        .stat-icon.green { background: rgba(40,167,69,0.1); color: #28A745; }
        .stat-icon.red { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .stat-icon.blue { background: rgba(41,128,185,0.1); color: #2980B9; }
        .stat-icon.purple { background: rgba(142,68,173,0.1); color: #8E44AD; }
        .stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1A1A2E;
            line-height: 1;
        }
        .stat-label { font-size: 0.6rem; color: #8A99AA; font-weight: 500; margin-top: 2px; }

        .card-modern {
            background: white;
            border-radius: 16px;
            border: 1px solid #E8ECF0;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.02);
        }
        .card-header-modern {
            padding: 14px 20px;
            background: #FAFBFC;
            border-bottom: 1px solid #E8ECF0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header-modern h5 {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1A1A2E;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header-modern h5 i { color: #C8922A; }

        .table-promo {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        .table-promo thead th {
            padding: 12px 16px;
            background: #F8F9FA;
            color: #5A6B7A;
            font-weight: 600;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 2px solid #E8ECF0;
            text-align: left;
        }
        .table-promo tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        .table-promo tbody tr:hover { background: #FAFBFC; }
        .table-promo tbody tr:last-child td { border-bottom: none; }

        .badge-status {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        .badge-status.active { background: #E8F5E9; color: #2E7D32; }
        .badge-status.inactive { background: #FBE9E7; color: #C62828; }
        .badge-status.expired { background: #F5F5F5; color: #6C757D; }

        .empty-state-table {
            text-align: center;
            padding: 40px;
            color: #8A99AA;
        }
        .empty-state-table i {
            font-size: 2.5rem;
            color: #D5D5D5;
            display: block;
            margin-bottom: 10px;
        }
        .empty-state-table p { margin: 0; font-size: 0.85rem; }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .content { margin-left: 0; width: 100%; padding: 20px 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .top-bar { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 500px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<div class="sidebar">
    <div class="sidebar-logo">
        <h3>AWA KA SUGU</h3>
        <small>Administration</small>
    </div>
    <ul class="sidebar-menu">
        <div class="nav-section">Principal</div>
        <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Tableau de bord</a></li>
        <li><a href="produits.php"><i class="bi bi-box-seam"></i> Produits</a></li>
        <li><a href="commandes.php"><i class="bi bi-receipt"></i> Commandes</a></li>
        <li><a href="clients.php"><i class="bi bi-people"></i> Clients</a></li>
        
        <div class="nav-section">Restaurant</div>
        <li><a href="plats.php"><i class="bi bi-cup-hot"></i> Restaurant</a></li>
        
        <div class="nav-section">Marketing</div>
        <li><a href="promotions.php" class="active"><i class="bi bi-percent"></i> Promotions</a></li>
        <li><a href="videos.php"><i class="bi bi-camera-reels"></i> Vidéos</a></li>
        
        <div class="nav-section">Finances</div>
        <li><a href="factures.php"><i class="bi bi-file-earmark-text"></i> Factures</a></li>
        
        <div class="nav-section" style="margin-top:15px;border-top:1px solid rgba(255,255,255,0.05);padding-top:12px;">
            <li><a href="logout.php" class="logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
        </div>
    </ul>
</div>

<!-- ===== CONTENU ===== -->
<div class="content">

    <div class="top-bar">
        <div>
            <h1>🎁 Gestion des <span>promotions</span></h1>
            <span class="subtitle">Gérez les offres promotionnelles de votre boutique</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn-gold" data-bs-toggle="modal" data-bs-target="#addPromoModal">
                <i class="bi bi-plus-circle"></i> Nouvelle promotion
            </button>
        </div>
    </div>

    <!-- Alertes -->
    <?php if($msg == 'ajoute'): ?>
        <div class="alert-modern success" id="alertMessage">
            <i class="bi bi-check-circle-fill"></i> Promotion ajoutée et synchronisée avec succès !
        </div>
    <?php endif; ?>
    <?php if($msg == 'modifie'): ?>
        <div class="alert-modern success" id="alertMessage">
            <i class="bi bi-check-circle-fill"></i> Promotion modifiée avec succès !
        </div>
    <?php endif; ?>
    <?php if($msg == 'supprime'): ?>
        <div class="alert-modern success" id="alertMessage">
            <i class="bi bi-check-circle-fill"></i> Promotion supprimée avec succès !
        </div>
    <?php endif; ?>
    <?php if($msg == 'toggle'): ?>
        <div class="alert-modern success" id="alertMessage">
            <i class="bi bi-check-circle-fill"></i> Statut modifié avec succès !
        </div>
    <?php endif; ?>
    <?php if(isset($error) && $error): ?>
        <div class="alert-modern danger" id="alertMessage">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Sync Info -->
    <div class="sync-info">
        <div class="label">
            <i class="bi bi-arrow-repeat" style="color:#2980B9;"></i>
            <span><strong>Synchro auto :</strong> Les promotions actives sont appliquées aux produits.</span>
            <span class="badge-sync">✅ ACTIVE</span>
        </div>
        <div class="count-produits">
            <i class="bi bi-box-seam"></i> <?= $nb_produits_promo ?> produit(s) en promotion
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-tags"></i></div>
            <div>
                <div class="stat-value"><?= $total_promos ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-value"><?= $actives ?></div>
                <div class="stat-label">Actives</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-clock"></i></div>
            <div>
                <div class="stat-value"><?= $expirees ?></div>
                <div class="stat-label">Expirées</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-eye-slash"></i></div>
            <div>
                <div class="stat-value"><?= $inactives ?></div>
                <div class="stat-label">Inactives</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-box-seam"></i></div>
            <div>
                <div class="stat-value"><?= $nb_produits_promo ?></div>
                <div class="stat-label">Produits en promo</div>
            </div>
        </div>
    </div>

    <!-- Liste -->
    <div class="card-modern">
        <div class="card-header-modern">
            <h5><i class="bi bi-list"></i> Liste des promotions</h5>
            <span style="font-size:0.7rem;color:#8A99AA;"><?= $total_promos ?> promotion(s)</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="table-promo">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Description</th>
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
                            <td colspan="8">
                                <div class="empty-state-table">
                                    <i class="bi bi-tags"></i>
                                    <p>Aucune promotion pour le moment</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($promotions as $p): 
                            $is_expired = ($p['date_fin'] < $now && $p['est_active'] == 1);
                            $status_class = $is_expired ? 'expired' : ($p['est_active'] ? 'active' : 'inactive');
                            $status_label = $is_expired ? 'Expirée' : ($p['est_active'] ? 'Active' : 'Inactive');
                        ?>
                        <tr>
                            <td><strong>#<?= $p['id'] ?></strong></td>
                            <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                            <td><small style="color:#8A99AA;"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 35)) ?></small></td>
                            <td>
                                <?php if($p['produit_id']): ?>
                                    <span style="font-weight:500;"><?= htmlspecialchars($p['produit_nom'] ?? 'Produit #'.$p['produit_id']) ?></span>
                                <?php else: ?>
                                    <span style="color:#8A99AA;">Tous</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-weight:700;color:#C8922A;"><?= $p['valeur'] ?> <?= $p['type'] == 'pourcentage' ? '%' : 'FCFA' ?></span></td>
                            <td style="font-size:0.7rem;"><?= date('d/m/Y', strtotime($p['date_debut'])) ?> → <?= date('d/m/Y', strtotime($p['date_fin'])) ?></td>
                            <td><span class="badge-status <?= $status_class ?>"><?= $status_label ?></span></td>
                            <td style="text-align:center;">
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    <a href="promotion_modifier.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning" title="Modifier"><i class="bi bi-pencil"></i></a>
                                    <a href="promotions.php?toggle=<?= $p['id'] ?>" class="btn btn-sm <?= $p['est_active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>" title="<?= $p['est_active'] ? 'Désactiver' : 'Activer' ?>">
                                        <i class="bi <?= $p['est_active'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                    </a>
                                    <a href="promotions.php?supprimer=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer définitivement ?')" title="Supprimer">
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

<!-- ===== MODAL ===== -->
<div class="modal fade" id="addPromoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#0D0D0D,#1A1A2E);color:#C8922A;border:none;border-radius:16px 16px 0 0;padding:18px 24px;">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nouvelle promotion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="ajouter">
                <div class="modal-body" style="padding:24px;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-600">Nom <span class="text-danger">*</span></label>
                            <input type="text" name="nom" class="form-control" placeholder="Soldes d'été" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-600">Produit</label>
                            <select name="produit_id" class="form-select">
                                <option value="">Tous les produits</option>
                                <?php foreach($produits as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Description..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-600">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="pourcentage">Pourcentage (%)</option>
                                <option value="montant_fixe">Montant fixe (FCFA)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-600">Valeur <span class="text-danger">*</span></label>
                            <input type="number" name="valeur" class="form-control" placeholder="10" step="1" min="0" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-600">Date début <span class="text-danger">*</span></label>
                            <input type="date" name="date_debut" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-600">Date fin <span class="text-danger">*</span></label>
                            <input type="date" name="date_fin" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                        </div>
                    </div>
                    <div class="alert alert-info" style="border-radius:10px;font-size:0.85rem;">
                        <i class="bi bi-info-circle"></i> La promotion sera automatiquement synchronisée avec les produits concernés.
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E8ECF0;padding:16px 24px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn-gold">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const alertMessage = document.getElementById('alertMessage');
    if (alertMessage) {
        setTimeout(function() {
            alertMessage.style.transition = 'all 0.5s ease';
            alertMessage.style.opacity = '0';
            alertMessage.style.transform = 'translateY(-20px)';
            setTimeout(function() {
                alertMessage.style.display = 'none';
            }, 500);
        }, 5000);
    }
});
</script>
</body>
</html>