<?php
// ============================================
// MODIFIER UN PLAT - ADMIN AWA KA SUGU
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
// RÉCUPÉRER LE PLAT
// ============================================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$plat = $pdo->prepare("SELECT * FROM plats WHERE id = ?");
$plat->execute([$id]);
$plat = $plat->fetch();

if (!$plat) {
    header('Location: plats.php');
    exit;
}

// ============================================
// MODIFIER UN PLAT AVEC PHOTO
// ============================================
$debug_message = ''; // Pour le débogage

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_plat'])) {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $prix = (float)$_POST['prix'];
    $est_plat_du_jour = isset($_POST['est_plat_du_jour']) ? (int)$_POST['est_plat_du_jour'] : 0;
    $est_visible = isset($_POST['est_visible']) ? (int)$_POST['est_visible'] : 1;
    $categorie = trim($_POST['categorie'] ?? '');
    $photo = $plat['photo']; // Garder l'ancienne photo par défaut
    $supprimer_photo = isset($_POST['supprimer_photo']) ? true : false;
    
    // Supprimer la photo existante
    if ($supprimer_photo && !empty($plat['photo'])) {
        $old_photo_path = __DIR__ . '/' . $plat['photo'];
        if (file_exists($old_photo_path)) {
            unlink($old_photo_path);
        }
        $photo = '';
    }
    
    // Gestion de l'upload de nouvelle photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/plats/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_info = pathinfo($_FILES['photo']['name']);
        $extension = strtolower($file_info['extension']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($extension, $allowed_extensions)) {
            if (!empty($plat['photo'])) {
                $old_photo_path = __DIR__ . '/' . $plat['photo'];
                if (file_exists($old_photo_path)) {
                    unlink($old_photo_path);
                }
            }
            
            $new_filename = uniqid('plat_') . '.' . $extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $photo = 'uploads/plats/' . $new_filename;
            }
        }
    }
    
    if (!empty($nom) && $prix > 0) {
        try {
            $stmt = $pdo->prepare("
                UPDATE plats SET 
                    nom = ?, description = ?, prix = ?, 
                    est_plat_du_jour = ?, est_visible = ?, 
                    categorie = ?, photo = ?
                WHERE id = ?
            ");
            $stmt->execute([$nom, $description, $prix, $est_plat_du_jour, $est_visible, $categorie, $photo, $id]);
            
            // Vérifier que la modification a bien été faite
            $check = $pdo->prepare("SELECT est_plat_du_jour FROM plats WHERE id = ?");
            $check->execute([$id]);
            $result = $check->fetch();
            
            $_SESSION['message_plat'] = 'Plat modifié avec succès ! (est_plat_du_jour = ' . $result['est_plat_du_jour'] . ')';
            header('Location: plats.php');
            exit;
        } catch(PDOException $e) {
            $debug_message = 'Erreur SQL : ' . $e->getMessage();
        }
    } else {
        $debug_message = 'Veuillez remplir tous les champs obligatoires.';
    }
}

$message = $_SESSION['message_plat'] ?? '';
unset($_SESSION['message_plat']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Plat - Admin Awa Ka Sugu</title>
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
        .btn-secondary {
            background: #6B7A8D;
            color: #fff;
            border-color: #6B7A8D;
        }
        .btn-secondary:hover { background: #5A6B7A; color: #fff; border-color: #5A6B7A; }
        .btn-danger {
            background: #E74C3C;
            color: #fff;
            border-color: #E74C3C;
        }
        .btn-danger:hover { background: #C0392B; color: #fff; border-color: #C0392B; }

        .content { padding: 28px 32px; flex: 1; }
        .alert-success {
            background: #D4EDDA;
            border-left: 4px solid #27AE60;
            color: #0A3622;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #F8D7DA;
            border-left: 4px solid #E74C3C;
            color: #721C24;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .form-card {
            background: #fff;
            border-radius: 16px;
            padding: 30px;
            max-width: 700px;
            margin: 0 auto;
            border: 1px solid #E8ECF0;
        }
        .form-card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            margin-bottom: 25px;
            color: #0D0D0D;
            padding-bottom: 15px;
            border-bottom: 1px solid #E8ECF0;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 0.8rem;
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
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }
        .form-actions .btn-admin {
            padding: 10px 30px;
            font-size: 0.85rem;
        }
        .photo-preview {
            margin-top: 10px;
            border: 2px dashed #E0E0E0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            color: #999;
            position: relative;
        }
        .photo-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
        }
        .photo-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            align-items: center;
        }
        .photo-actions label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #E74C3C;
            cursor: pointer;
        }
        .photo-actions label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .info-box {
            background: #FFF3CD;
            border: 1px solid #FFE69C;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            color: #856404;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .form-card { padding: 20px; }
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
            <div class="topbar-title">🍽️ Modifier un <span>Plat</span></div>
            <div class="topbar-breadcrumb">Restaurant → Plats → Modifier</div>
        </div>
        <div>
            <a href="plats.php" class="btn-admin"><i class="bi bi-arrow-left"></i> Retour</a>
        </div>
    </div>

    <div class="content">
        <?php if($message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $message ?></div>
        <?php endif; ?>

        <?php if($debug_message): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= $debug_message ?></div>
        <?php endif; ?>

        <!-- Info : Statut actuel du plat -->
        <div class="info-box">
            <strong>Statut actuel :</strong> 
            Plat du jour = <?= $plat['est_plat_du_jour'] ? '✅ Oui' : '❌ Non' ?> | 
            Visible = <?= $plat['est_visible'] ? '✅ Oui' : '❌ Non' ?>
        </div>

        <div class="form-card">
            <h3><i class="bi bi-pencil-square"></i> Modifier : <?= htmlspecialchars($plat['nom']) ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $plat['id'] ?>">

                <div class="form-group">
                    <label>Nom du plat *</label>
                    <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($plat['nom']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control" placeholder="Description du plat..." value="<?= htmlspecialchars($plat['description'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Catégorie</label>
                    <select name="categorie" class="form-control">
                        <option value="">Sélectionner</option>
                        <option value="Entrée" <?= ($plat['categorie'] == 'Entrée') ? 'selected' : '' ?>>Entrée</option>
                        <option value="Plat principal" <?= ($plat['categorie'] == 'Plat principal') ? 'selected' : '' ?>>Plat principal</option>
                        <option value="Dessert" <?= ($plat['categorie'] == 'Dessert') ? 'selected' : '' ?>>Dessert</option>
                        <option value="Boisson" <?= ($plat['categorie'] == 'Boisson') ? 'selected' : '' ?>>Boisson</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Prix (FCFA) *</label>
                    <input type="number" name="prix" class="form-control" min="1" step="0.1" value="<?= $plat['prix'] ?>" required>
                </div>

                <div class="form-group">
                    <label>Photo du plat</label>
                    <?php if (!empty($plat['photo'])): ?>
                        <div class="photo-preview" id="photoPreview">
                            <img src="../admin/<?= htmlspecialchars($plat['photo']) ?>" alt="Photo du plat">
                        </div>
                        <div class="photo-actions">
                            <label>
                                <input type="checkbox" name="supprimer_photo" value="1" onchange="togglePhotoPreview(this)">
                                🗑️ Supprimer cette photo
                            </label>
                        </div>
                    <?php else: ?>
                        <div class="photo-preview" id="photoPreview">
                            <i class="bi bi-image" style="font-size:2rem;color:#ccc;"></i>
                            <p style="margin-top:8px;">Aucune photo</p>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top:10px;">
                        <label style="font-weight:400;font-size:0.85rem;color:#666;">
                            <i class="bi bi-upload"></i> Changer la photo
                        </label>
                        <input type="file" name="photo" class="form-control-file" accept="image/*" onchange="previewImage(this)">
                    </div>
                </div>

                <div class="form-group">
                    <label>Plat du jour</label>
                    <select name="est_plat_du_jour" class="form-control" id="platJourSelect">
                        <option value="0" <?= ($plat['est_plat_du_jour'] == 0) ? 'selected' : '' ?>>Non</option>
                        <option value="1" <?= ($plat['est_plat_du_jour'] == 1) ? 'selected' : '' ?>>Oui</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Visibilité</label>
                    <select name="est_visible" class="form-control">
                        <option value="1" <?= ($plat['est_visible'] == 1) ? 'selected' : '' ?>>Visible</option>
                        <option value="0" <?= ($plat['est_visible'] == 0) ? 'selected' : '' ?>>Caché</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" name="modifier_plat" class="btn-admin btn-primary">
                        <i class="bi bi-save"></i> Enregistrer
                    </button>
                    <a href="plats.php" class="btn-admin btn-secondary">
                        <i class="bi bi-x-lg"></i> Annuler
                    </a>
                </div>
            </form>
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
    }
}

function togglePhotoPreview(checkbox) {
    const preview = document.getElementById('photoPreview');
    if (checkbox.checked) {
        preview.innerHTML = `
            <div style="color:#E74C3C;padding:20px;">
                <i class="bi bi-trash3" style="font-size:2rem;"></i>
                <p style="margin-top:8px;">La photo sera supprimée</p>
            </div>
        `;
    } else {
        <?php if (!empty($plat['photo'])): ?>
        preview.innerHTML = `<img src="../admin/<?= htmlspecialchars($plat['photo']) ?>" alt="Photo du plat">`;
        <?php else: ?>
        preview.innerHTML = `
            <i class="bi bi-image" style="font-size:2rem;color:#ccc;"></i>
            <p style="margin-top:8px;">Aucune photo</p>
        `;
        <?php endif; ?>
    }
}
</script>
</body>
</html>