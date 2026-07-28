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
    $section = $_POST['section'] ?? 'boutique';
    $produit_id = !empty($_POST['produit_id']) ? (int)$_POST['produit_id'] : null;
    $est_active = isset($_POST['est_active']) ? 1 : 0;
    $fichier_video = '';
    
    if (empty($titre)) {
        $error = "Le titre est obligatoire.";
    }
    
    // Gestion de l'upload de fichier vidéo
    if (isset($_FILES['fichier_video']) && $_FILES['fichier_video']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/videos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['fichier_video']['name'], PATHINFO_EXTENSION));
        $allowed = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mpg', 'mpeg'];
        
        if (in_array($ext, $allowed)) {
            $fichier_video = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['fichier_video']['tmp_name'], $upload_dir . $fichier_video);
        } else {
            $error = 'Format vidéo non autorisé (MP4, WEBM, OGG, MOV, AVI)';
        }
    } else {
        $error = "Veuillez sélectionner un fichier vidéo.";
    }
    
    if (empty($error)) {
        $stmt = $pdo->prepare("
            INSERT INTO videos (titre, type, url_ou_fichier, fichier_video, section, produit_id, est_active, created_at)
            VALUES (?, 'local', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$titre, '', $fichier_video, $section, $produit_id, $est_active]);
        
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
        .btn-secondary { background: #6c757d; color: white; padding: 10px 24px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-secondary:hover { background: #5a6268; color: white; transform: translateY(-2px); }
        .btn-save { 
            background: linear-gradient(135deg, #C8922A, #E8B55A); 
            color: #1A1A1A; 
            border: none; 
            padding: 14px 35px; 
            border-radius: 12px; 
            font-weight: 700; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center; 
            gap: 10px; 
            transition: all 0.3s;
            font-size: 0.95rem;
            box-shadow: 0 4px 15px rgba(200,146,42,0.3);
        }
        .btn-save:hover { 
            background: linear-gradient(135deg, #9A6E1A, #C8922A); 
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(200,146,42,0.4);
            color: white;
        }

        .content { padding: 28px 32px; flex: 1; max-width: 900px; }

        .form-container {
            background: white;
            border-radius: 20px;
            padding: 35px;
            border: 1px solid #E8ECF0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.04);
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1A1A1A;
            font-size: 0.9rem;
        }
        .form-group label .required { color: #E74C3C; }
        
        .form-control, .form-select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #E8ECF0;
            border-radius: 12px;
            font-family: 'Jost', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #F8F9FA;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #C8922A;
            background: white;
            box-shadow: 0 0 0 4px rgba(200,146,42,0.1);
        }
        
        .row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
        }
        
        .checkbox-group input {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            cursor: pointer;
        }
        .checkbox-group input:checked { accent-color: #C8922A; }
        .checkbox-group label {
            font-size: 0.95rem;
            cursor: pointer;
            margin-bottom: 0;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: none;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
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
            background: linear-gradient(135deg, #FFF8E7, #FFFDF5);
            border: 1px solid rgba(200,146,42,0.2);
            border-radius: 14px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .info-box .icon {
            color: #C8922A;
            font-size: 1.2rem;
            margin-right: 10px;
        }
        
        .info-box p {
            margin: 4px 0;
            font-size: 0.85rem;
            color: #555;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .upload-area {
            border: 2px dashed #D0D5DD;
            border-radius: 16px;
            padding: 40px 20px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            background: #FAFBFC;
        }
        
        .upload-area:hover {
            border-color: #C8922A;
            background: #FFFDF5;
        }
        
        .upload-area .icon-upload {
            font-size: 3rem;
            color: #C8922A;
            opacity: 0.5;
        }
        
        .upload-area p {
            margin-top: 10px;
            color: #8A99AA;
            font-size: 0.9rem;
        }
        
        .upload-area .format {
            font-size: 0.7rem;
            color: #B0B8C4;
            margin-top: 5px;
        }
        
        .file-selected {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: #F0F7FF;
            border-radius: 12px;
            margin-top: 10px;
            border: 1px solid #C8922A;
        }
        
        .file-selected.active {
            display: flex;
        }
        
        .file-selected i {
            color: #27AE60;
            font-size: 1.2rem;
        }
        
        .file-selected .file-name {
            font-weight: 500;
            color: #1A1A1A;
        }
        
        .file-selected .file-size {
            color: #8A99AA;
            font-size: 0.8rem;
            margin-left: auto;
        }
        
        .video-preview {
            margin-top: 15px;
            border-radius: 12px;
            overflow: hidden;
            background: #0D0D0D;
            display: none;
        }
        
        .video-preview.active {
            display: block;
        }
        
        .video-preview video {
            width: 100%;
            max-height: 300px;
            display: block;
        }
        
        .preview-label {
            padding: 10px 16px;
            background: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.5);
            font-size: 0.7rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 1000px) {
            .main { margin-left: 0; }
            .row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .form-container { padding: 20px; }
            .upload-area { padding: 25px 15px; }
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
            <div class="topbar-title">📹 Ajouter une <span>vidéo</span></div>
            <div class="topbar-breadcrumb">Administration → Vidéos → Ajouter</div>
        </div>
        <a href="videos.php" class="btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>

    <div class="content">

        <?php if($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                
                <div class="form-group">
                    <label>Titre de la vidéo <span class="required">*</span></label>
                    <input type="text" name="titre" class="form-control" required placeholder="Ex: Awa Doumbia présente sa nouvelle collection">
                </div>
                
                <!-- Upload vidéo -->
                <div class="form-group">
                    <label>Fichier vidéo <span class="required">*</span></label>
                    <div class="upload-area" id="uploadArea">
                        <i class="bi bi-cloud-upload icon-upload"></i>
                        <p><strong>Cliquez pour sélectionner</strong> ou glissez-déposez</p>
                        <div class="format">MP4, WEBM, OGG, MOV, AVI (max 100MB)</div>
                    </div>
                    <input type="file" name="fichier_video" id="fileInput" class="form-control" accept="video/*" style="display: none;" required>
                    
                    <div class="file-selected" id="fileSelected">
                        <i class="bi bi-check-circle-fill"></i>
                        <span class="file-name" id="fileName">video.mp4</span>
                        <span class="file-size" id="fileSize">2.5 MB</span>
                        <button type="button" onclick="removeFile()" style="background:none;border:none;color:#E74C3C;cursor:pointer;font-size:1.2rem;">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                    
                    <div class="video-preview" id="videoPreview">
                        <div class="preview-label"><i class="bi bi-eye"></i> Aperçu</div>
                        <video id="previewVideo" controls></video>
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
                
                <div class="checkbox-group">
                    <input type="checkbox" name="est_active" id="active" checked>
                    <label for="active">✅ Active (visible sur le site)</label>
                </div>
                
                <div class="info-box">
                    <p><span class="icon">💡</span> <strong>Conseils :</strong></p>
                    <p>📹 Téléchargez une vidéo au format MP4 pour une meilleure compatibilité</p>
                    <p>🎬 La vidéo s'affichera automatiquement sur la page Vidéos de votre boutique</p>
                    <p>🏷️ Vous pouvez associer cette vidéo à un produit spécifique</p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="bi bi-save"></i> Enregistrer la vidéo
                    </button>
                    <a href="videos.php" style="display:inline-flex;align-items:center;gap:8px;padding:14px 25px;border:2px solid #E8ECF0;border-radius:12px;text-decoration:none;color:#666;font-weight:500;transition:all 0.3s;">
                        <i class="bi bi-x-circle"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Upload area click
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    const fileSelected = document.getElementById('fileSelected');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const videoPreview = document.getElementById('videoPreview');
    const previewVideo = document.getElementById('previewVideo');
    
    uploadArea.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#C8922A';
        this.style.background = 'rgba(200,146,42,0.05)';
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = '#D0D5DD';
        this.style.background = '#FAFBFC';
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#D0D5DD';
        this.style.background = '#FAFBFC';
        
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFile(e.dataTransfer.files[0]);
        }
    });
    
    fileInput.addEventListener('change', function() {
        if (this.files.length) {
            handleFile(this.files[0]);
        }
    });
    
    function handleFile(file) {
        // Afficher le nom du fichier
        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024 / 1024).toFixed(1) + ' MB';
        fileSelected.classList.add('active');
        
        // Afficher la prévisualisation
        const url = URL.createObjectURL(file);
        previewVideo.src = url;
        videoPreview.classList.add('active');
        
        // Changer le style de l'upload area
        uploadArea.style.borderColor = '#27AE60';
        uploadArea.style.background = 'rgba(39,174,96,0.05)';
    }
    
    function removeFile() {
        fileInput.value = '';
        fileSelected.classList.remove('active');
        videoPreview.classList.remove('active');
        previewVideo.src = '';
        uploadArea.style.borderColor = '#D0D5DD';
        uploadArea.style.background = '#FAFBFC';
    }
</script>

</body>
</html>