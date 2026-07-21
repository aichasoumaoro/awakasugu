<?php
// ============================================
// GESTION DES PLATS - ADMIN AWA KA SUGU
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
// AJOUTER UN PLAT AVEC PHOTO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_plat'])) {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $prix = (float)$_POST['prix'];
    $est_plat_du_jour = isset($_POST['est_plat_du_jour']) ? (int)$_POST['est_plat_du_jour'] : 0;
    $est_visible = isset($_POST['est_visible']) ? (int)$_POST['est_visible'] : 1;
    $categorie = trim($_POST['categorie'] ?? '');
    $photo = '';
    
    // Gestion de l'upload de photo - VERS LE DOSSIER RESTAURANT/IMAGES
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        // Chemin vers le dossier images du restaurant
        $upload_dir = dirname(__DIR__) . '/restaurant/images/';
        
        // Créer le dossier s'il n'existe pas
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_info = pathinfo($_FILES['photo']['name']);
        // Garder le nom original du fichier
        $nom_fichier = $file_info['basename'];
        $extension = strtolower($file_info['extension']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($extension, $allowed_extensions)) {
            // Nettoyer le nom du fichier (enlever les caractères spéciaux)
            $nom_fichier = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', str_replace(' ', '_', $nom_fichier));
            $upload_path = $upload_dir . $nom_fichier;
            
            // Si le fichier existe déjà, ajouter un suffixe
            $counter = 1;
            $original_name = pathinfo($nom_fichier, PATHINFO_FILENAME);
            $ext = pathinfo($nom_fichier, PATHINFO_EXTENSION);
            while (file_exists($upload_path)) {
                $nom_fichier = $original_name . '_' . $counter . '.' . $ext;
                $upload_path = $upload_dir . $nom_fichier;
                $counter++;
            }
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $photo = $nom_fichier;
            }
        }
    }
    
    if (!empty($nom) && $prix > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO plats (nom, description, prix, est_plat_du_jour, est_visible, categorie, photo, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$nom, $description, $prix, $est_plat_du_jour, $est_visible, $categorie, $photo]);
        
        $_SESSION['message_plat'] = 'Plat ajouté avec succès !';
        header('Location: plats.php');
        exit;
    }
}

// ============================================
// MODIFIER UN PLAT
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_plat'])) {
    $id = (int)$_POST['id'];
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $prix = (float)$_POST['prix'];
    $est_plat_du_jour = isset($_POST['est_plat_du_jour']) ? (int)$_POST['est_plat_du_jour'] : 0;
    $est_visible = isset($_POST['est_visible']) ? (int)$_POST['est_visible'] : 1;
    $categorie = trim($_POST['categorie'] ?? '');
    
    // Récupérer la photo actuelle
    $stmt = $pdo->prepare("SELECT photo FROM plats WHERE id = ?");
    $stmt->execute([$id]);
    $current_plat = $stmt->fetch();
    $photo = $current_plat['photo'] ?? '';
    
    // Gestion de la suppression de photo
    $supprimer_photo = isset($_POST['supprimer_photo']) ? true : false;
    
    if ($supprimer_photo && !empty($photo)) {
        $old_photo_path = dirname(__DIR__) . '/restaurant/images/' . $photo;
        if (file_exists($old_photo_path)) {
            unlink($old_photo_path);
        }
        $photo = '';
    }
    
    // Gestion de l'upload de nouvelle photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = dirname(__DIR__) . '/restaurant/images/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_info = pathinfo($_FILES['photo']['name']);
        $nom_fichier = $file_info['basename'];
        $extension = strtolower($file_info['extension']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($extension, $allowed_extensions)) {
            // Supprimer l'ancienne photo
            if (!empty($current_plat['photo'])) {
                $old_photo_path = dirname(__DIR__) . '/restaurant/images/' . $current_plat['photo'];
                if (file_exists($old_photo_path)) {
                    unlink($old_photo_path);
                }
            }
            
            // Nettoyer le nom du fichier
            $nom_fichier = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', str_replace(' ', '_', $nom_fichier));
            $upload_path = $upload_dir . $nom_fichier;
            
            // Si le fichier existe déjà, ajouter un suffixe
            $counter = 1;
            $original_name = pathinfo($nom_fichier, PATHINFO_FILENAME);
            $ext = pathinfo($nom_fichier, PATHINFO_EXTENSION);
            while (file_exists($upload_path)) {
                $nom_fichier = $original_name . '_' . $counter . '.' . $ext;
                $upload_path = $upload_dir . $nom_fichier;
                $counter++;
            }
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $photo = $nom_fichier;
            }
        }
    }
    
    if (!empty($nom) && $prix > 0) {
        $stmt = $pdo->prepare("
            UPDATE plats SET 
                nom = ?, description = ?, prix = ?, 
                est_plat_du_jour = ?, est_visible = ?, 
                categorie = ?, photo = ?
            WHERE id = ?
        ");
        $stmt->execute([$nom, $description, $prix, $est_plat_du_jour, $est_visible, $categorie, $photo, $id]);
        
        $_SESSION['message_plat'] = 'Plat modifié avec succès !';
        header('Location: plats.php');
        exit;
    }
}

// ============================================
// SUPPRIMER UN PLAT
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    
    // Récupérer la photo pour la supprimer
    $stmt = $pdo->prepare("SELECT photo FROM plats WHERE id = ?");
    $stmt->execute([$id]);
    $plat = $stmt->fetch();
    
    if (!empty($plat['photo'])) {
        $photo_path = dirname(__DIR__) . '/restaurant/images/' . $plat['photo'];
        if (file_exists($photo_path)) {
            unlink($photo_path);
        }
    }
    
    $pdo->prepare("DELETE FROM plats WHERE id = ?")->execute([$id]);
    $_SESSION['message_plat'] = 'Plat supprimé avec succès !';
    header('Location: plats.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================
$plats = $pdo->query("SELECT * FROM plats ORDER BY created_at DESC")->fetchAll();
$plat_du_jour = $pdo->query("SELECT * FROM plats WHERE est_plat_du_jour = 1 LIMIT 1")->fetch();

$message = $_SESSION['message_plat'] ?? '';
unset($_SESSION['message_plat']);

// ============================================
// STATISTIQUES
// ============================================
$total_plats = $pdo->query("SELECT COUNT(*) FROM plats")->fetchColumn();
$total_visibles = $pdo->query("SELECT COUNT(*) FROM plats WHERE est_visible = 1")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Plats - Admin Awa Ka Sugu</title>
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
        .btn-primary {
            background: #C8922A;
            color: #fff;
            border-color: #C8922A;
        }
        .btn-primary:hover { background: #9A6E1A; color: #fff; border-color: #9A6E1A; }
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
            grid-template-columns: repeat(3, 1fr);
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
        .ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem; font-weight: 700;
            color: #1A2C3E; line-height: 1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 2px; }

        .form-card {
            background: #fff;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 30px;
            border: 1px solid #E8ECF0;
        }
        .form-card h3 { font-size: 1.1rem; margin-bottom: 20px; color: #0D0D0D; }
        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 0.8fr 0.8fr 0.8fr;
            gap: 15px;
            align-items: end;
        }
        .form-group { margin-bottom: 0; }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #E0E0E0;
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: 'Jost', sans-serif;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #C8922A;
        }
        .form-control select { cursor: pointer; }
        .form-control-file {
            padding: 8px 0;
            border: 1.5px solid #E0E0E0;
            border-radius: 8px;
            padding: 8px 14px;
            width: 100%;
        }
        .photo-preview {
            margin-top: 10px;
            border: 2px dashed #E0E0E0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            color: #999;
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .photo-preview img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
        }
        .photo-preview .no-photo {
            font-size: 0.85rem;
            color: #aaa;
        }

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
        .badge-statut {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-visible { background: #D4EDDA; color: #1A7A4A; }
        .badge-cache { background: #F8D7DA; color: #721C24; }
        .badge-plat_jour { background: #FFF3CD; color: #856404; }
        .btn-act {
            width: 32px; height: 32px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
        }
        .btn-edit { background: rgba(41,128,185,0.1); color: #2980B9; }
        .btn-edit:hover { background: #2980B9; color: #fff; }
        .btn-delete { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .btn-delete:hover { background: #E74C3C; color: #fff; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 10px; }
        .plat-photo {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        .plat-photo-empty {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            border-radius: 8px;
            color: #ccc;
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .form-grid { grid-template-columns: 1fr 1fr; }
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
        <a href="plats.php" class="nav-item active"><i class="bi bi-cup-hot"></i> Plats</a>
        <a href="commandes_repas.php" class="nav-item"><i class="bi bi-bag-check"></i> Commandes repas</a>
        <a href="reservations.php" class="nav-item"><i class="bi bi-calendar-check"></i> Réservations</a>

        <div class="nav-section">Gestion</div>
        <a href="achats.php" class="nav-item"><i class="bi bi-cart-check"></i> Achats</a>
        <a href="stocks.php" class="nav-item"><i class="bi bi-bar-chart"></i> Stocks</a>
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
            <div class="topbar-title">🍽️ Gestion des <span>Plats</span></div>
            <div class="topbar-breadcrumb">Restaurant → Plats</div>
        </div>
        <div>
            <a href="../index.php" class="btn-admin btn-site"><i class="bi bi-eye"></i> Voir le site</a>
        </div>
    </div>

    <div class="content">
        <?php if($message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $message ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-cup-hot"></i></div>
                <div>
                    <div class="stat-val"><?= $total_plats ?></div>
                    <div class="stat-lbl">Total plats</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-eye"></i></div>
                <div>
                    <div class="stat-val"><?= $total_visibles ?></div>
                    <div class="stat-lbl">Plats visibles</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-star-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $plat_du_jour ? $plat_du_jour['nom'] : 'Aucun' ?></div>
                    <div class="stat-lbl">Plat du jour</div>
                </div>
            </div>
        </div>

        <!-- Formulaire ajout - CORRIGÉ -->
        <div class="form-card">
            <h3><i class="bi bi-plus-circle"></i> Ajouter un plat</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nom du plat</label>
                        <input type="text" name="nom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Prix (FCFA)</label>
                        <input type="number" name="prix" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Catégorie</label>
                        <select name="categorie" class="form-control">
                            <option value="">Sélectionner</option>
                            <option value="Entrée">Entrée</option>
                            <option value="Plat principal">Plat principal</option>
                            <option value="Dessert">Dessert</option>
                            <option value="Boisson">Boisson</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Plat du jour</label>
                        <select name="est_plat_du_jour" class="form-control">
                            <option value="0">Non</option>
                            <option value="1">Oui</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="ajouter_plat" class="btn-admin btn-primary" style="width:100%;">
                            <i class="bi bi-save"></i> Ajouter
                        </button>
                    </div>
                </div>
                <div style="margin-top:10px;">
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Description du plat...">
                    </div>
                </div>
                <div style="margin-top:10px;">
                    <div class="form-group">
                        <label>Photo du plat</label>
                        <input type="file" name="photo" class="form-control-file" accept="image/*" onchange="previewImage(this)">
                        <div class="photo-preview" id="photoPreview">
                            <span class="no-photo"><i class="bi bi-image"></i> Aucune photo sélectionnée</span>
                        </div>
                    </div>
                </div>
                <div style="margin-top:10px;">
                    <div class="form-group">
                        <label>Visibilité</label>
                        <select name="est_visible" class="form-control">
                            <option value="1">✅ Visible</option>
                            <option value="0">❌ Caché</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:10px;font-size:0.8rem;color:#999;">
                    <i class="bi bi-info-circle"></i> La photo sera enregistrée dans le dossier <strong>restaurant/images/</strong>
                </div>
            </form>
        </div>

        <!-- Liste des plats -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">📋 Liste des plats</div>
                <div><?= count($plats) ?> plat(s)</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Description</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($plats)): ?>
                        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-cup-hot"></i><p>Aucun plat enregistré</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach($plats as $p): ?>
                        <tr>
                            <td>
                                <?php if(!empty($p['photo'])): ?>
                                    <img src="../restaurant/images/<?= htmlspecialchars($p['photo']) ?>" alt="Photo" class="plat-photo">
                                <?php else: ?>
                                    <div class="plat-photo-empty"><i class="bi bi-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>#<?= $p['id'] ?></td>
                            <td><strong><?= htmlspecialchars($p['nom']) ?></strong>
                                <?php if($p['est_plat_du_jour']): ?>
                                    <span class="badge-statut badge-plat_jour" style="margin-left:6px;">⭐ Plat du jour</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.8rem;color:#666;"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 40)) ?>...</td>
                            <td><?= htmlspecialchars($p['categorie'] ?? '-') ?></td>
                            <td style="color:#C8922A;font-weight:600;"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</td>
                            <td>
                                <span class="badge-statut <?= ($p['est_visible'] ?? 1) ? 'badge-visible' : 'badge-cache' ?>">
                                    <?= ($p['est_visible'] ?? 1) ? '✅ Visible' : '❌ Caché' ?>
                                </span>
                            </td>
                            <td style="text-align:center;white-space:nowrap;">
                                <a href="plat_modifier.php?id=<?= $p['id'] ?>" class="btn-act btn-edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="plats.php?supprimer=<?= $p['id'] ?>" class="btn-act btn-delete" onclick="return confirm('Supprimer ce plat ?')">
                                    <i class="bi bi-trash3"></i>
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

<script>
function previewImage(input) {
    const preview = document.getElementById('photoPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Aperçu">`;
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.innerHTML = `<span class="no-photo"><i class="bi bi-image"></i> Aucune photo sélectionnée</span>`;
    }
}
</script>
</body>
</html>