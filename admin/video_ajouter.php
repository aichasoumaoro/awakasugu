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

// Récupérer les produits pour le dropdown
$produits = $pdo->query("SELECT id, nom FROM produits WHERE est_visible = 1 ORDER BY nom")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $type = $_POST['type'] ?? 'youtube';
    $url_ou_fichier = trim($_POST['url_ou_fichier'] ?? '');
    $section = $_POST['section'] ?? 'boutique';
    $produit_id = !empty($_POST['produit_id']) ? (int)$_POST['produit_id'] : null;
    $est_active = isset($_POST['est_active']) ? 1 : 0;
    $fichier_video = '';
    
    // Gestion de l'upload de fichier vidéo
    if (isset($_FILES['fichier_video']) && $_FILES['fichier_video']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/videos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['fichier_video']['name'], PATHINFO_EXTENSION));
        $allowed = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mpg', 'mpeg'];
        
        if (in_array($ext, $allowed)) {
            $fichier_video = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['fichier_video']['tmp_name'], $upload_dir . $fichier_video);
            $type = 'local';
        } else {
            $error = 'Format vidéo non autorisé (MP4, WEBM, OGG, MOV, AVI)';
        }
    }
    
    // Si c'est une vidéo en ligne (YouTube, etc.)
    if ($type != 'local' && empty($fichier_video)) {
        if (empty($url_ou_fichier)) {
            $error = "L'URL de la vidéo est obligatoire pour les vidéos en ligne.";
        }
    }
    
    if (empty($titre)) {
        $error = "Le titre est obligatoire.";
    }
    
    if (empty($error)) {
        $stmt = $pdo->prepare("
            INSERT INTO videos (titre, type, url_ou_fichier, fichier_video, section, produit_id, est_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$titre, $type, $url_ou_fichier, $fichier_video, $section, $produit_id, $est_active]);
        
        $success = 'Vidéo ajoutée avec succès !';
        header('refresh:2;url=videos.php');
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une vidéo - Admin Awa Ka Sugu</title>
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
        .btn-secondary { background: #6c757d; color: white; padding: 10px 24px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-secondary:hover { background: #5a6268; color: white; }
        .btn-save { background: #C8922A; color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-save:hover { background: #9A6E1A; }

        .content { padding: 28px 32px; flex: 1; }

        .form-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            border: 1px solid #E8ECF0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.85rem;
        }
        .form-group label .required { color: #E74C3C; }
        
        .form-control, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid #E0E0E0;
            border-radius: 10px;
            font-family: 'Jost', sans-serif;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #C8922A;
            box-shadow: 0 0 0 3px rgba(200,146,42,0.1);
        }
        
        .row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input {
            width: 18px;
            height: 18px;
        }
        .checkbox-group input:checked { accent-color: #C8922A; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: none;
            border-left: 4px solid;
        }
        
        .alert-danger {
            background: #FEF3F2;
            color: #721C24;
            border-left-color: #E74C3C;
        }
        
        .alert-success {
            background: #D4EDDA;
            color: #0A3622;
            border-left-color: #27AE60;
        }
        
        .info-box {
            background: #FFF8E7;
            border-left: 4px solid #C8922A;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .info-box p {
            margin: 5px 0;
            font-size: 0.85rem;
            color: #666;
        }
        
        .type-selector {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background: #F8F8F8;
            border-radius: 12px;
        }
        
        .type-option {
            flex: 1;
            text-align: center;
            cursor: pointer;
        }
        
        .type-option input {
            display: none;
        }
        
        .type-option label {
            display: block;
            padding: 12px;
            border-radius: 10px;
            background: white;
            border: 2px solid #E0E0E0;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .type-option input:checked + label {
            border-color: #C8922A;
            background: rgba(200,146,42,0.1);
            color: #C8922A;
        }
        
        .video-preview {
            margin-top: 15px;
            display: none;
        }
        
        .video-preview video {
            width: 100%;
            max-height: 200px;
            border-radius: 10px;
        }
        
        .info-text { font-size: 0.7rem; color: #8A99AA; margin-top: 4px; }
        
        @media (max-width: 1000px) {
            .main { margin-left: 0; }
            .row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .type-selector { flex-direction: column; }
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
            <div class="topbar-title">➕ Ajouter une <span>vidéo</span></div>
            <div class="topbar-breadcrumb">Administration → Vidéos → Ajouter</div>
        </div>
        <a href="videos.php" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>

    <div class="content">

        <?php if($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Titre de la vidéo <span class="required">*</span></label>
                    <input type="text" name="titre" class="form-control" required placeholder="Ex: Awa Doumbia présente sa nouvelle collection">
                </div>
                
                <!-- Sélection du type de source vidéo -->
                <div class="type-selector">
                    <div class="type-option">
                        <input type="radio" name="source_type" id="source_url" value="url" checked>
                        <label for="source_url">
                            <i class="bi bi-link"></i> Lien en ligne
                        </label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="source_type" id="source_file" value="file">
                        <label for="source_file">
                            <i class="bi bi-upload"></i> Fichier local
                        </label>
                    </div>
                </div>
                
                <!-- Section pour URL (YouTube, TikTok, etc.) -->
                <div id="url_section" class="form-group">
                    <label>URL de la vidéo <span class="required">*</span></label>
                    <input type="text" name="url_ou_fichier" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                    <div class="info-text">Collez l'URL complète de la vidéo (YouTube, TikTok, Instagram, etc.)</div>
                </div>
                
                <!-- Section pour upload de fichier -->
                <div id="file_section" class="form-group" style="display: none;">
                    <label>Fichier vidéo <span class="required">*</span></label>
                    <input type="file" name="fichier_video" class="form-control" accept="video/*">
                    <div class="info-text">Formats acceptés : MP4, WEBM, OGG, MOV, AVI (max 100MB)</div>
                    <div class="video-preview" id="videoPreview">
                        <video controls></video>
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section" class="form-select">
                            <option value="boutique">🛍️ Boutique</option>
                            <option value="restaurant">🍽️ Restaurant</option>
                            <option value="accueil">🏠 Accueil</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Produit associé (optionnel)</label>
                        <select name="produit_id" class="form-select">
                            <option value="">Aucun</option>
                            <?php foreach($produits as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="checkbox-group" style="margin-bottom: 20px;">
                    <input type="checkbox" name="est_active" id="active" checked>
                    <label for="active" style="margin-bottom: 0;">✅ Active (visible sur le site)</label>
                </div>
                
                <div class="info-box">
                    <i class="bi bi-info-circle" style="color: #C8922A;"></i>
                    <strong>Conseil :</strong>
                    <p>📹 Pour une vidéo locale : choisissez "Fichier local" et sélectionnez votre vidéo</p>
                    <p>🔗 Pour une vidéo en ligne : choisissez "Lien en ligne" et collez l'URL (YouTube, TikTok, etc.)</p>
                    <p>🎬 La vidéo s'affichera automatiquement sur la page Vidéos de votre boutique.</p>
                </div>
                
                <button type="submit" class="btn-save">
                    <i class="bi bi-save"></i> Enregistrer la vidéo
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Gérer l'affichage des sections selon le choix
    const sourceUrl = document.getElementById('source_url');
    const sourceFile = document.getElementById('source_file');
    const urlSection = document.getElementById('url_section');
    const fileSection = document.getElementById('file_section');
    const urlInput = document.querySelector('input[name="url_ou_fichier"]');
    const fileInput = document.querySelector('input[name="fichier_video"]');
    const videoPreview = document.getElementById('videoPreview');
    
    function toggleSections() {
        if (sourceUrl.checked) {
            urlSection.style.display = 'block';
            fileSection.style.display = 'none';
            urlInput.required = true;
            fileInput.required = false;
        } else {
            urlSection.style.display = 'none';
            fileSection.style.display = 'block';
            urlInput.required = false;
            fileInput.required = true;
        }
    }
    
    sourceUrl.addEventListener('change', toggleSections);
    sourceFile.addEventListener('change', toggleSections);
    
    // Preview vidéo locale
    fileInput.addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            const video = videoPreview.querySelector('video');
            video.src = URL.createObjectURL(e.target.files[0]);
            videoPreview.style.display = 'block';
            video.load();
        }
    });
    
    toggleSections();
</script>

</body>
</html>