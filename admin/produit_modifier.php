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
    die("Erreur de connexion : " . $e->getMessage());
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: produits.php');
    exit;
}

// Récupérer le produit
$stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
$stmt->execute([$id]);
$produit = $stmt->fetch();

if (!$produit) {
    header('Location: produits.php');
    exit;
}

// Récupérer les catégories
$categories = $pdo->query("SELECT * FROM categories WHERE type = 'boutique' AND parent_id IS NOT NULL ORDER BY parent_id, ordre")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = (float)$_POST['prix'];
    $prix_promo = !empty($_POST['prix_promo']) ? (float)$_POST['prix_promo'] : null;
    $categorie_id = (int)$_POST['categorie_id'];
    $stock = (int)$_POST['stock'];
    $seuil_alerte = (int)$_POST['seuil_alerte'];
    $est_nouveau = isset($_POST['est_nouveau']) ? 1 : 0;
    $est_visible = isset($_POST['est_visible']) ? 1 : 0;
    
    // Gestion de l'upload d'image
    $image_principale = $produit['image_principale'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/produits/';
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (in_array($ext, $allowed)) {
            // Supprimer l'ancienne image
            if (!empty($image_principale) && file_exists($upload_dir . $image_principale)) {
                unlink($upload_dir . $image_principale);
            }
            $image_principale = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_principale);
        } else {
            $error = 'Format d\'image non autorisé (JPG, PNG, WEBP, GIF)';
        }
    }
    
    if (empty($error)) {
        $stmt = $pdo->prepare("
            UPDATE produits SET 
                nom = ?, description = ?, prix = ?, prix_promo = ?, 
                categorie_id = ?, stock = ?, seuil_alerte = ?, 
                image_principale = ?, est_nouveau = ?, est_visible = ?
            WHERE id = ?
        ");
        $stmt->execute([$nom, $description, $prix, $prix_promo, $categorie_id, $stock, 
                       $seuil_alerte, $image_principale, $est_nouveau, $est_visible, $id]);
        
        $success = 'Produit modifié avec succès !';
        // Recharger le produit
        $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
        $stmt->execute([$id]);
        $produit = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier produit - Admin Awa Ka Sugu</title>
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
        
        .btn-back { background: #6C757D; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; }
        .btn-back:hover { background: #5A6268; color: white; }
        
        .form-container { background: white; border-radius: 12px; padding: 30px; border: 1px solid #E8ECF0; }
        .form-container .form-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(200,146,42,0.15);
        }
        .form-container .form-title i { color: #C8922A; margin-right: 8px; }
        
        .form-label { font-weight: 600; font-size: 0.8rem; color: #0D0D0D; }
        .form-label .required { color: #E74C3C; }
        .form-control, .form-select {
            border: 1.5px solid #E0E6ED;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #C8922A;
            box-shadow: 0 0 0 3px rgba(200,146,42,0.1);
        }
        
        .btn-save {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: white;
            border: none;
            padding: 12px 35px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-save:hover {
            background: linear-gradient(135deg, #9A6E1A, #C8922A);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(200,146,42,0.3);
        }
        
        .alert { border-radius: 10px; border: none; border-left: 4px solid; }
        .alert-danger { border-left-color: #E74C3C; background: #FEF3F2; color: #721C24; }
        .alert-success { border-left-color: #27AE60; background: #D4EDDA; color: #0A3622; }
        
        .form-check-input:checked { background-color: #C8922A; border-color: #C8922A; }
        .form-check-label { font-size: 0.85rem; }
        
        .preview-img { 
            width: 100px; 
            height: 100px; 
            object-fit: cover; 
            border-radius: 8px; 
            margin-top: 10px;
            border: 2px solid #E8ECF0;
        }
        .current-image-label {
            font-size: 0.75rem;
            color: #8A99AA;
            margin-top: 5px;
        }
        .info-text { font-size: 0.7rem; color: #8A99AA; margin-top: 4px; }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .content { margin-left: 0; padding: 20px 16px; }
            .content-header { flex-direction: column; align-items: flex-start; gap: 12px; }
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
        <li><a href="point_de_vente.php"><i class="bi bi-cash-stack"></i> Point de vente</a></li>
        <li><a href="produits.php" class="active"><i class="bi bi-box-seam"></i> Produits</a></li>
        <li><a href="achats.php"><i class="bi bi-cart-check"></i> Achats</a></li>
        <li><a href="commandes.php"><i class="bi bi-receipt"></i> Commandes</a></li>
        <li><a href="clients.php"><i class="bi bi-people"></i> Clients</a></li>
        <li><a href="plats.php"><i class="bi bi-cup-hot"></i> Restaurant</a></li>
        <li><a href="videos.php"><i class="bi bi-camera-reels"></i> Vidéos</a></li>
        <li><a href="promotions.php"><i class="bi bi-percent"></i> Promotions</a></li>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
    </ul>
</div>

<div class="content">
    <div class="content-header">
        <div>
            <h2><span>✏️</span> Modifier le produit</h2>
            <div class="breadcrumb">
                <a href="dashboard.php">Administration</a> &gt; 
                <a href="produits.php">Produits</a> &gt; 
                Modifier
            </div>
        </div>
        <a href="produits.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <div class="form-title">
            <i class="bi bi-pencil-square"></i> Modifier : <?= htmlspecialchars($produit['nom']) ?>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Nom du produit <span class="required">*</span></label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($produit['nom']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($produit['description']) ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prix (FCFA) <span class="required">*</span></label>
                                <input type="number" name="prix" class="form-control" value="<?= $produit['prix'] ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prix promotionnel</label>
                                <input type="number" name="prix_promo" class="form-control" value="<?= $produit['prix_promo'] ?>" placeholder="Laisser vide si pas de promo">
                                <div class="info-text">Le prix promo sera affiché en rouge barré sur le site</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Catégorie</label>
                                <select name="categorie_id" class="form-select">
                                    <option value="">Sélectionner</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $produit['categorie_id'] == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Stock</label>
                                <input type="number" name="stock" class="form-control" value="<?= $produit['stock'] ?>" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Seuil d'alerte</label>
                                <input type="number" name="seuil_alerte" class="form-control" value="<?= $produit['seuil_alerte'] ?>" min="0">
                                <div class="info-text">Alerte lorsque le stock atteint ce niveau</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nouvelle image</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                                <div class="info-text">Formats acceptés : JPG, PNG, WEBP, GIF</div>
                                <?php if(!empty($produit['image_principale']) && file_exists('../uploads/produits/'.$produit['image_principale'])): ?>
                                    <div>
                                        <img src="../uploads/produits/<?= $produit['image_principale'] ?>" class="preview-img">
                                        <div class="current-image-label">Image actuelle</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="est_nouveau" class="form-check-input" id="nouveau" <?= $produit['est_nouveau'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="nouveau">⭐ Marquer comme nouveau</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="est_visible" class="form-check-input" id="visible" <?= $produit['est_visible'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="visible">👁️ Visible sur le site</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-info" style="border-left-color:#2980B9; background:#E8F4FD;">
                        <i class="bi bi-info-circle" style="color:#2980B9;"></i>
                        <strong>Conseil :</strong>
                        <ul style="margin-top:10px;font-size:0.85rem;color:#1A3A5C;padding-left:20px;">
                            <li>Modifiez le prix promo pour créer une offre</li>
                            <li>Changez l'image pour mettre à jour le produit</li>
                            <li>Le produit reste visible si la case est cochée</li>
                        </ul>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-save">
                <i class="bi bi-save"></i> Enregistrer les modifications
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>