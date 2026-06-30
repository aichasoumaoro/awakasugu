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
    
    // Récupérer les couleurs et tailles sélectionnées
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
        
        // ✅ AJOUTER LES COULEURS
        if (!empty($couleurs_selectionnees)) {
            $stmt = $pdo->prepare("INSERT INTO produit_couleurs (produit_id, couleur_id) VALUES (?, ?)");
            foreach ($couleurs_selectionnees as $couleur_id) {
                $stmt->execute([$produit_id, $couleur_id]);
            }
        }
        
        // ✅ AJOUTER LES TAILLES
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
        
        .info-text { font-size: 0.7rem; color: #8A99AA; margin-top: 4px; }
        
        /* Styles pour les couleurs */
        .color-option {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 15px;
            margin-bottom: 8px;
        }
        .color-option input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #C8922A; }
        .color-option .color-circle {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            border: 2px solid #ddd;
            display: inline-block;
        }
        .color-option label { font-size: 0.85rem; cursor: pointer; }
        
        .taille-option {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 15px;
            margin-bottom: 8px;
        }
        .taille-option input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #C8922A; }
        .taille-option label { font-size: 0.85rem; font-weight: 600; cursor: pointer; }
        
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
            <h2><span>➕</span> Ajouter un produit</h2>
            <div class="breadcrumb">
                <a href="dashboard.php">Administration</a> &gt; 
                <a href="produits.php">Produits</a> &gt; 
                Ajouter
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

    <div class="form-container">
        <div class="form-title">
            <i class="bi bi-plus-circle"></i> Nouveau produit
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Nom du produit <span class="required">*</span></label>
                        <input type="text" name="nom" class="form-control" placeholder="Ex: Abaya noire élégante" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Description détaillée du produit..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prix (FCFA) <span class="required">*</span></label>
                                <input type="number" name="prix" class="form-control" placeholder="35000" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prix promotionnel</label>
                                <input type="number" name="prix_promo" class="form-control" placeholder="Laisser vide si pas de promo">
                                <div class="info-text">Le prix promo sera affiché en rouge barré sur le site</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Catégorie <span class="required">*</span></label>
                                <select name="categorie_id" class="form-select" required>
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
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Stock initial</label>
                                <input type="number" name="stock" class="form-control" value="0" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <!-- ✅ SECTION COULEURS -->
                    <div class="mb-3">
                        <label class="form-label">Couleurs disponibles</label>
                        <div class="p-3 bg-light rounded" style="border:1px solid #E0E6ED;">
                            <?php foreach($couleurs as $c): ?>
                            <div class="color-option">
                                <input type="checkbox" name="couleurs[]" value="<?= $c['id'] ?>" id="couleur_<?= $c['id'] ?>">
                                <span class="color-circle" style="background-color: <?= $c['code_hex'] ?>; border: 2px solid <?= $c['code_hex'] == '#FFFFFF' ? '#ccc' : '#ddd' ?>;"></span>
                                <label for="couleur_<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="info-text">Sélectionnez les couleurs disponibles pour ce produit</div>
                    </div>
                    
                    <!-- ✅ SECTION TAILLES -->
                    <div class="mb-3">
                        <label class="form-label">Tailles disponibles</label>
                        <div class="p-3 bg-light rounded" style="border:1px solid #E0E6ED;">
                            <?php foreach($tailles as $t): ?>
                            <div class="taille-option">
                                <input type="checkbox" name="tailles[]" value="<?= $t['id'] ?>" id="taille_<?= $t['id'] ?>">
                                <label for="taille_<?= $t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="info-text">Sélectionnez les tailles disponibles pour ce produit</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Seuil d'alerte</label>
                                <input type="number" name="seuil_alerte" class="form-control" value="5" min="0">
                                <div class="info-text">Alerte lorsque le stock atteint ce niveau</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Image principale</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                                <div class="info-text">Formats acceptés : JPG, PNG, WEBP, GIF</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="est_nouveau" class="form-check-input" id="nouveau">
                                <label class="form-check-label" for="nouveau">⭐ Marquer comme nouveau</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="est_visible" class="form-check-input" id="visible" checked>
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
                            <li>Utilisez un nom court et descriptif</li>
                            <li>Ajoutez une image de qualité</li>
                            <li>Le prix promo doit être inférieur au prix normal</li>
                            <li>Le produit sera visible immédiatement si coché</li>
                            <li>Sélectionnez les couleurs et tailles disponibles</li>
                        </ul>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-save">
                <i class="bi bi-save"></i> Enregistrer le produit
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>