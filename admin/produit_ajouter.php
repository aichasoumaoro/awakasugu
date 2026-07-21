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

// Récupérer les couleurs et tailles
$couleurs = $pdo->query("SELECT * FROM couleurs ORDER BY nom")->fetchAll();
$tailles = $pdo->query("SELECT * FROM tailles ORDER BY ordre")->fetchAll();

// Ajouter des tailles de chaussures si elles n'existent pas
$chaussures_tailles = [34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46];
foreach ($chaussures_tailles as $t) {
    $check = $pdo->prepare("SELECT id FROM tailles WHERE nom = ?");
    $check->execute([$t]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO tailles (nom, ordre) VALUES (?, ?)");
        $stmt->execute([$t, $t]);
    }
}
// Recharger les tailles
$tailles = $pdo->query("SELECT * FROM tailles ORDER BY ordre")->fetchAll();

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
    
    $couleurs_selectionnees = isset($_POST['couleurs']) ? $_POST['couleurs'] : [];
    $tailles_selectionnees = isset($_POST['tailles']) ? $_POST['tailles'] : [];
    
    $image_principale = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/produits/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $image_principale = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_principale);
        } else {
            $error = 'Format d\'image non autorisé (JPG, PNG, WEBP, GIF)';
        }
    }
    
    if (empty($error)) {
        $stmt = $pdo->prepare("
            INSERT INTO produits (nom, description, prix, prix_promo, categorie_id, stock, seuil_alerte, image_principale, est_nouveau, est_visible, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$nom, $description, $prix, $prix_promo, $categorie_id, $stock, $seuil_alerte, $image_principale, $est_nouveau, $est_visible]);
        $produit_id = $pdo->lastInsertId();
        
        if (!empty($couleurs_selectionnees)) {
            $stmt = $pdo->prepare("INSERT INTO produit_couleurs (produit_id, couleur_id) VALUES (?, ?)");
            foreach ($couleurs_selectionnees as $couleur_id) {
                $stmt->execute([$produit_id, $couleur_id]);
            }
        }
        
        if (!empty($tailles_selectionnees)) {
            $stmt = $pdo->prepare("INSERT INTO produit_tailles (produit_id, taille_id) VALUES (?, ?)");
            foreach ($tailles_selectionnees as $taille_id) {
                $stmt->execute([$produit_id, $taille_id]);
            }
        }
        
        $success = 'Produit ajouté avec succès !';
        header('refresh:2;url=produits.php');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F5F7FA; display: flex; min-height: 100vh; font-family: 'Inter', sans-serif; }
        
        /* ===== SIDEBAR ===== */
        .sidebar { 
            background: #0D0B08; 
            min-height: 100vh; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 260px; 
            padding: 20px 0; 
            z-index: 100; 
            overflow-y: auto; 
        }
        .sidebar-logo { 
            text-align: center; 
            padding: 20px 24px; 
            border-bottom: 1px solid rgba(200,146,42,0.15); 
        }
        .sidebar-logo h3 { 
            color: #C8922A; 
            font-size: 1.2rem; 
            margin: 0; 
            font-weight: 700;
            letter-spacing: 2px;
        }
        .sidebar-logo small { 
            color: rgba(255,255,255,0.25); 
            font-size: 0.65rem; 
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .sidebar-menu { list-style: none; padding: 10px 0; }
        .sidebar-menu li a { 
            display: flex; 
            align-items: center; 
            gap: 14px; 
            padding: 12px 24px; 
            color: rgba(255,255,255,0.5); 
            text-decoration: none; 
            transition: all 0.3s; 
            font-size: 0.85rem;
            border-left: 3px solid transparent;
        }
        .sidebar-menu li a:hover, 
        .sidebar-menu li a.active { 
            background: rgba(200,146,42,0.06); 
            color: #C8922A; 
            border-left-color: #C8922A;
        }
        .sidebar-menu li a i { width: 22px; font-size: 1.1rem; }
        .sidebar-menu .divider {
            height: 1px;
            background: rgba(255,255,255,0.05);
            margin: 10px 20px;
        }
        
        /* ===== CONTENT ===== */
        .content { margin-left: 260px; padding: 30px; flex: 1; }
        
        .content-header {
            background: white;
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #EEEAE5;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .content-header h2 { 
            font-size: 1.2rem; 
            font-weight: 700; 
            color: #0D0D0D; 
            font-family: 'Playfair Display', serif;
        }
        .content-header h2 span { color: #C8922A; }
        .content-header .breadcrumb { 
            font-size: 0.7rem; 
            color: #8A99AA; 
            margin: 0; 
        }
        .content-header .breadcrumb a { 
            color: #8A99AA; 
            text-decoration: none; 
        }
        .content-header .breadcrumb a:hover { color: #C8922A; }
        
        .btn-back { 
            background: #F0EDE8; 
            color: #1A1A1A; 
            padding: 8px 18px; 
            border-radius: 8px; 
            text-decoration: none; 
            transition: all 0.3s; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 0.8rem;
            font-weight: 500;
        }
        .btn-back:hover { background: #E0DCD5; color: #1A1A1A; }
        
        /* ============================================
           FORMULAIRE - DESIGN COMPLÈTEMENT REFONDU
           ============================================ */
        
        .form-wrapper {
            background: white;
            border-radius: 20px;
            padding: 0;
            border: 1px solid #EEEAE5;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, #0D0B08, #1A1612);
            padding: 25px 30px;
            border-bottom: 3px solid #C8922A;
        }
        .form-header h3 {
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .form-header h3 i {
            color: #C8922A;
            font-size: 1.3rem;
        }
        .form-header .sub {
            color: rgba(255,255,255,0.3);
            font-size: 0.7rem;
            font-weight: 300;
            margin-top: 4px;
            letter-spacing: 1px;
        }
        
        .form-body {
            padding: 30px;
        }
        
        /* ===== SECTION STYLES ===== */
        .form-section {
            margin-bottom: 28px;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .form-section .section-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: #C8922A;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #F0EDE8;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-section .section-label i {
            font-size: 0.8rem;
        }
        .form-section .section-label .line {
            flex: 1;
            height: 1px;
            background: #F0EDE8;
        }
        
        /* ===== CHAMPS MODERNES ===== */
        .field-group {
            margin-bottom: 18px;
        }
        .field-group:last-child {
            margin-bottom: 0;
        }
        
        .field-group .field-label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: #0D0D0D;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .field-group .field-label .required {
            color: #E74C3C;
            margin-left: 2px;
        }
        .field-group .field-label .hint {
            font-weight: 400;
            color: #B0B0B0;
            font-size: 0.6rem;
            text-transform: none;
            margin-left: 6px;
        }
        
        .field-group .field-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #EEEAE5;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
            background: #FAF9F7;
            color: #1A1A1A;
        }
        .field-group .field-control:focus {
            outline: none;
            border-color: #C8922A;
            background: white;
            box-shadow: 0 0 0 4px rgba(200,146,42,0.08);
        }
        .field-group .field-control::placeholder {
            color: #B0B0B0;
        }
        
        .field-group textarea.field-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .field-group select.field-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%238A99AA' stroke-width='2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
            cursor: pointer;
        }
        
        /* ===== CHAMPS EN LIGNE ===== */
        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        /* ===== COULEURS ===== */
        .colors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 8px;
        }
        
        .color-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: #FAF9F7;
            border: 2px solid #EEEAE5;
            border-radius: 10px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .color-item:hover {
            border-color: #C8922A;
            background: rgba(200,146,42,0.03);
        }
        .color-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #C8922A;
            cursor: pointer;
            flex-shrink: 0;
        }
        .color-item .color-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #ddd;
            flex-shrink: 0;
        }
        .color-item .color-name {
            font-size: 0.75rem;
            font-weight: 500;
            color: #1A1A1A;
        }
        
        /* ===== TAILLES ===== */
        .tailles-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .taille-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px 6px 10px;
            background: #FAF9F7;
            border: 2px solid #EEEAE5;
            border-radius: 20px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .taille-item:hover {
            border-color: #C8922A;
            background: rgba(200,146,42,0.03);
        }
        .taille-item input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: #C8922A;
            cursor: pointer;
        }
        .taille-item .taille-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: #1A1A1A;
        }
        
        .tailles-section {
            margin-top: 12px;
        }
        .tailles-section .sub-label {
            font-size: 0.6rem;
            color: #8A99AA;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: block;
        }
        
        /* ===== TOGGLES MODERNES ===== */
        .toggles-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .toggle-modern {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            background: #FAF9F7;
            border: 2px solid #EEEAE5;
            border-radius: 12px;
            transition: all 0.3s;
        }
        .toggle-modern:hover {
            border-color: rgba(200,146,42,0.2);
        }
        .toggle-modern input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #C8922A;
            cursor: pointer;
            flex-shrink: 0;
        }
        .toggle-modern .toggle-content {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toggle-modern .toggle-content .badge-preview {
            font-size: 0.6rem;
            padding: 3px 12px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-new {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: white;
        }
        .badge-visible {
            background: rgba(39,174,96,0.12);
            color: #27AE60;
            border: 1px solid rgba(39,174,96,0.15);
        }
        .toggle-modern .toggle-desc {
            font-size: 0.6rem;
            color: #8A99AA;
            margin-left: auto;
        }
        
        /* ===== BOUTON ===== */
        .btn-submit {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(200,146,42,0.25);
            width: 100%;
            justify-content: center;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #9A6E1A, #C8922A);
            transform: translateY(-2px);
            box-shadow: 0 8px 35px rgba(200,146,42,0.35);
            color: white;
        }
        .btn-submit i {
            font-size: 1.1rem;
        }
        
        /* ===== RIGHT SIDEBAR ===== */
        .right-sidebar {
            background: #F8F9FA;
            border-radius: 16px;
            padding: 24px 20px;
            border: 1px solid #EEEAE5;
            position: sticky;
            top: 20px;
        }
        .right-sidebar h4 {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0D0D0D;
            margin-bottom: 15px;
        }
        .right-sidebar h4 i {
            color: #C8922A;
            margin-right: 8px;
        }
        .right-sidebar .tip-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .right-sidebar .tip-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #EEEAE5;
            color: #666;
            font-size: 0.8rem;
            line-height: 1.4;
        }
        .right-sidebar .tip-list li:last-child {
            border-bottom: none;
        }
        .right-sidebar .tip-list li i {
            color: #C8922A;
            font-size: 0.9rem;
            margin-top: 2px;
        }
        .right-sidebar .tip-list li strong {
            color: #0D0D0D;
        }
        .badge-preview {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #EEEAE5;
            flex-wrap: wrap;
        }
        .badge-preview .badge-item {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-new-preview {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: white;
        }
        .badge-promo-preview {
            background: #E74C3C;
            color: white;
        }
        .badge-visible-preview {
            background: rgba(39,174,96,0.1);
            color: #27AE60;
        }
        
        .alert { border-radius: 12px; border: none; border-left: 4px solid; }
        .alert-danger { border-left-color: #E74C3C; background: #FEF3F2; color: #721C24; }
        .alert-success { border-left-color: #27AE60; background: #D4EDDA; color: #0A3622; }
        
        .info-text { font-size: 0.6rem; color: #8A99AA; margin-top: 4px; }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .content { margin-left: 0; padding: 16px; }
            .content-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .right-sidebar { position: static; margin-top: 20px; }
            .field-row { grid-template-columns: 1fr; }
            .toggles-row { grid-template-columns: 1fr; }
            .colors-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); }
            .form-body { padding: 20px; }
            .form-header { padding: 20px; }
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
        <div class="divider"></div>
        <li><a href="plats.php"><i class="bi bi-cup-hot"></i> Restaurant</a></li>
        <li><a href="videos.php"><i class="bi bi-camera-reels"></i> Vidéos</a></li>
        <li><a href="promotions.php"><i class="bi bi-percent"></i> Promotions</a></li>
        <div class="divider"></div>
        <li><a href="logout.php" style="color:rgba(231,76,60,0.5);"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
    </ul>
</div>

<div class="content">
    <div class="content-header">
        <div>
            <h2><span>✦</span> Ajouter un produit</h2>
            <div class="breadcrumb">
                <a href="dashboard.php">Administration</a> › 
                <a href="produits.php">Produits</a> › 
                <span style="color:#C8922A;">Ajouter</span>
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
            <br><small style="color:#666;">Redirection vers la liste des produits...</small>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- FORMULAIRE GAUCHE - DESIGN COMPLÈTEMENT REFONDU -->
        <div class="col-lg-8">
            <div class="form-wrapper">
                <!-- HEADER -->
                <div class="form-header">
                    <h3>
                        <i class="bi bi-plus-circle"></i>
                        Nouveau produit
                    </h3>
                    <div class="sub">Remplissez les informations ci-dessous pour ajouter un produit</div>
                </div>
                
                <!-- BODY -->
                <div class="form-body">
                    <form method="POST" enctype="multipart/form-data">
                        
                        <!-- SECTION 1: Informations générales -->
                        <div class="form-section">
                            <div class="section-label">
                                <i class="bi bi-info-circle"></i> Informations générales
                                <span class="line"></span>
                            </div>
                            
                            <div class="field-group">
                                <label class="field-label">
                                    Nom du produit <span class="required">*</span>
                                    <span class="hint">(ex: Abaya noire élégante)</span>
                                </label>
                                <input type="text" name="nom" class="field-control" placeholder="Entrez le nom du produit" required>
                            </div>
                            
                            <div class="field-group">
                                <label class="field-label">Description</label>
                                <textarea name="description" class="field-control" placeholder="Description détaillée du produit..."></textarea>
                            </div>
                        </div>
                        
                        <!-- SECTION 2: Prix et catégorie -->
                        <div class="form-section">
                            <div class="section-label">
                                <i class="bi bi-tag"></i> Prix et catégorie
                                <span class="line"></span>
                            </div>
                            
                            <div class="field-row">
                                <div class="field-group">
                                    <label class="field-label">Prix (FCFA) <span class="required">*</span></label>
                                    <input type="number" name="prix" class="field-control" placeholder="35000" required>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">Prix promotionnel <span class="hint">(optionnel)</span></label>
                                    <input type="number" name="prix_promo" class="field-control" placeholder="Laisser vide">
                                    <div class="info-text">Le prix promo sera affiché en rouge barré sur le site</div>
                                </div>
                            </div>
                            
                            <div class="field-row">
                                <div class="field-group">
                                    <label class="field-label">Catégorie <span class="required">*</span></label>
                                    <select name="categorie_id" class="field-control" required>
                                        <option value="">Sélectionner une catégorie</option>
                                        <optgroup label="👗 Abayas">
                                            <option value="11">Abayas Bijoux</option>
                                            <option value="12">Abayas Bibi</option>
                                            <option value="13">Abayas Stars</option>
                                            <option value="14">Abayas Enfant</option>
                                        </optgroup>
                                        <optgroup label="🧣 Foulards & Turbans">
                                            <option value="21">Foulards</option>
                                            <option value="22">Turbants</option>
                                            <option value="23">Voiles</option>
                                        </optgroup>
                                        <optgroup label="👜 Sacs & Accessoires">
                                            <option value="31">Sacs à main</option>
                                            <option value="32">Porte-monnaie</option>
                                            <option value="33">Sacs complets</option>
                                            <option value="34">Accessoires</option>
                                        </optgroup>
                                        <optgroup label="👠 Chaussures">
                                            <option value="41">Talons</option>
                                            <option value="42">Ballerines</option>
                                            <option value="43">Sandales</option>
                                            <option value="44">Fermées</option>
                                        </optgroup>
                                        <optgroup label="👚 Prêt-à-porter">
                                            <option value="51">Robes</option>
                                            <option value="52">Ensembles</option>
                                            <option value="53">Vestes</option>
                                            <option value="54">Jupes</option>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">Stock initial</label>
                                    <input type="number" name="stock" class="field-control" value="0" min="0">
                                </div>
                            </div>
                        </div>
                        
                        <!-- SECTION 3: Couleurs et tailles -->
                        <div class="form-section">
                            <div class="section-label">
                                <i class="bi bi-palette"></i> Couleurs et tailles
                                <span class="line"></span>
                            </div>
                            
                            <!-- Couleurs -->
                            <div class="field-group">
                                <label class="field-label">Couleurs disponibles</label>
                                <div class="colors-grid">
                                    <?php 
                                    $couleurs_list = [
                                        ['nom' => 'Blanc', 'code' => '#FFFFFF'],
                                        ['nom' => 'Noir', 'code' => '#000000'],
                                        ['nom' => 'Beige', 'code' => '#F5E6D3'],
                                        ['nom' => 'Crème', 'code' => '#FFF8F0'],
                                        ['nom' => 'Marron', 'code' => '#8B6914'],
                                        ['nom' => 'Marron clair', 'code' => '#A67C52'],
                                        ['nom' => 'Or', 'code' => '#C8922A'],
                                        ['nom' => 'Doré', 'code' => '#D4AF37'],
                                        ['nom' => 'Rouge', 'code' => '#E74C3C'],
                                        ['nom' => 'Bordeaux', 'code' => '#800020'],
                                        ['nom' => 'Rose', 'code' => '#E91E63'],
                                        ['nom' => 'Rose poudré', 'code' => '#F4C2C2'],
                                        ['nom' => 'Bleu', 'code' => '#3498DB'],
                                        ['nom' => 'Bleu ciel', 'code' => '#87CEEB'],
                                        ['nom' => 'Bleu marine', 'code' => '#1A2A6C'],
                                        ['nom' => 'Vert', 'code' => '#27AE60'],
                                        ['nom' => 'Vert olive', 'code' => '#556B2F'],
                                        ['nom' => 'Vert émeraude', 'code' => '#50C878'],
                                        ['nom' => 'Violet', 'code' => '#9B59B6'],
                                        ['nom' => 'Lavande', 'code' => '#E6E6FA'],
                                        ['nom' => 'Gris', 'code' => '#808080'],
                                        ['nom' => 'Gris clair', 'code' => '#D3D3D3'],
                                        ['nom' => 'Jaune', 'code' => '#F1C40F'],
                                        ['nom' => 'Orange', 'code' => '#E67E22'],
                                        ['nom' => 'Turquoise', 'code' => '#40E0D0'],
                                        ['nom' => 'Mauve', 'code' => '#E0B0FF'],
                                        ['nom' => 'Corail', 'code' => '#FF7F50'],
                                        ['nom' => 'Fuchsia', 'code' => '#FF00FF'],
                                        ['nom' => 'Indigo', 'code' => '#4B0082'],
                                        ['nom' => 'Kaki', 'code' => '#BDB76B'],
                                    ];
                                    
                                    foreach ($couleurs_list as $c):
                                        $check = $pdo->prepare("SELECT id FROM couleurs WHERE nom = ?");
                                        $check->execute([$c['nom']]);
                                        $existing = $check->fetch();
                                        
                                        if ($existing) {
                                            $id = $existing['id'];
                                        } else {
                                            $stmt = $pdo->prepare("INSERT INTO couleurs (nom, code_hex) VALUES (?, ?)");
                                            $stmt->execute([$c['nom'], $c['code']]);
                                            $id = $pdo->lastInsertId();
                                        }
                                    ?>
                                    <label class="color-item">
                                        <input type="checkbox" name="couleurs[]" value="<?= $id ?>">
                                        <span class="color-dot" style="background-color: <?= $c['code'] ?>; border: 2px solid <?= $c['code'] == '#FFFFFF' ? '#ccc' : '#ddd' ?>;"></span>
                                        <span class="color-name"><?= $c['nom'] ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="info-text">Sélectionnez les couleurs disponibles pour ce produit</div>
                            </div>
                            
                            <!-- Tailles -->
                            <div class="field-group">
                                <label class="field-label">Tailles disponibles</label>
                                
                                <div class="tailles-section">
                                    <span class="sub-label">Tailles standard (XS à XXL)</span>
                                    <div class="tailles-grid">
                                        <?php 
                                        $standard_tailles = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
                                        foreach ($tailles as $t): 
                                            if (!in_array($t['nom'], $standard_tailles) && !is_numeric($t['nom'])) continue;
                                        ?>
                                        <label class="taille-item">
                                            <input type="checkbox" name="tailles[]" value="<?= $t['id'] ?>">
                                            <span class="taille-name"><?= htmlspecialchars($t['nom']) ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="tailles-section">
                                    <span class="sub-label">Tailles chaussures (34 à 46)</span>
                                    <div class="tailles-grid">
                                        <?php foreach ($tailles as $t): 
                                            if (!is_numeric($t['nom'])) continue;
                                            $num = (int)$t['nom'];
                                            if ($num < 34 || $num > 46) continue;
                                        ?>
                                        <label class="taille-item">
                                            <input type="checkbox" name="tailles[]" value="<?= $t['id'] ?>">
                                            <span class="taille-name"><?= htmlspecialchars($t['nom']) ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="info-text">Sélectionnez les tailles disponibles pour ce produit</div>
                            </div>
                        </div>
                        
                        <!-- SECTION 4: Paramètres -->
                        <div class="form-section">
                            <div class="section-label">
                                <i class="bi bi-gear"></i> Paramètres
                                <span class="line"></span>
                            </div>
                            
                            <div class="field-row">
                                <div class="field-group">
                                    <label class="field-label">Seuil d'alerte</label>
                                    <input type="number" name="seuil_alerte" class="field-control" value="5" min="0">
                                    <div class="info-text">Alerte lorsque le stock atteint ce niveau</div>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">Image principale</label>
                                    <input type="file" name="image" class="field-control" accept="image/*" style="padding:8px 16px;">
                                    <div class="info-text">Formats : JPG, PNG, WEBP, GIF</div>
                                </div>
                            </div>
                            
                            <div class="toggles-row">
                                <label class="toggle-modern">
                                    <input type="checkbox" name="est_nouveau" checked>
                                    <div class="toggle-content">
                                        <span class="badge-preview badge-new">⭐ Nouveau</span>
                                    </div>
                                    <span class="toggle-desc">Marquer comme nouveau</span>
                                </label>
                                
                                <label class="toggle-modern">
                                    <input type="checkbox" name="est_visible" checked>
                                    <div class="toggle-content">
                                        <span class="badge-preview badge-visible">● Visible</span>
                                    </div>
                                    <span class="toggle-desc">Afficher sur le site</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Bouton -->
                        <div style="margin-top: 10px;">
                            <button type="submit" class="btn-submit">
                                <i class="bi bi-save"></i> Enregistrer le produit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- RIGHT SIDEBAR - GARDÉ COMME AVANT -->
        <div class="col-lg-4">
            <div class="right-sidebar">
                <h4><i class="bi bi-info-circle"></i> Conseils</h4>
                <ul class="tip-list">
                    <li>
                        <i class="bi bi-pencil"></i>
                        <div>
                            <strong>Nom</strong><br>
                            Utilisez un nom court et descriptif
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-tag"></i>
                        <div>
                            <strong>Prix</strong><br>
                            Le prix promo doit être inférieur au prix normal
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-palette"></i>
                        <div>
                            <strong>Couleurs & Tailles</strong><br>
                            Sélectionnez les options disponibles
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-image"></i>
                        <div>
                            <strong>Image</strong><br>
                            Ajoutez une image de qualité
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-star"></i>
                        <div>
                            <strong>Nouveau</strong><br>
                            Cochez pour apparaître dans les nouveautés
                        </div>
                    </li>
                </ul>
                
                <div class="badge-preview">
                    <span class="badge-item badge-new-preview">⭐ Nouveau</span>
                    <span class="badge-item badge-promo-preview">-30%</span>
                    <span class="badge-item badge-visible-preview">● Visible</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>