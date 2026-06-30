<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

$categories = $pdo->query("SELECT * FROM categories WHERE type = 'restaurant' ORDER BY nom")->fetchAll();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = (float)$_POST['prix'];
    $categorie_id = (int)$_POST['categorie_id'];
    $est_visible = isset($_POST['est_visible']) ? 1 : 0;
    $est_plat_du_jour = isset($_POST['est_plat_du_jour']) ? 1 : 0;
    
    // Gestion de l'upload d'image
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/plats/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $image = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image);
        }
    }
    
    if ($est_plat_du_jour) {
        $pdo->exec("UPDATE plats SET est_plat_du_jour = 0");
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO plats (nom, description, prix, categorie_id, image, est_visible, est_plat_du_jour, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$nom, $description, $prix, $categorie_id, $image, $est_visible, $est_plat_du_jour]);
    
    $success = 'Plat ajouté avec succès !';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un plat - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f5f5f5; }
        .sidebar { background: #0D0D0D; min-height: 100vh; position: fixed; top: 0; left: 0; width: 260px; padding: 20px 0; }
        .sidebar-logo { text-align: center; padding: 20px; }
        .sidebar-logo h3 { color: #C8922A; font-size: 1.3rem; }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 12px 24px; color: rgba(255,255,255,0.7); text-decoration: none; }
        .sidebar-menu li a:hover { background: rgba(200,146,42,0.1); color: #C8922A; }
        .content { margin-left: 260px; padding: 30px; }
        .form-container { background: white; border-radius: 16px; padding: 30px; }
        .btn-save { background: #C8922A; color: white; border: none; padding: 12px 30px; border-radius: 8px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-logo"><h3>✨ AWA KA SUGU ✨</h3></div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Tableau de bord</a></li>
        <li><a href="produits.php"><i class="bi bi-box-seam"></i> Produits</a></li>
        <li><a href="commandes.php"><i class="bi bi-receipt"></i> Commandes</a></li>
        <li><a href="clients.php"><i class="bi bi-people"></i> Clients</a></li>
        <li><a href="plats.php" class="active"><i class="bi bi-cup-hot"></i> Restaurant</a></li>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
    </ul>
</div>

<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>➕ Ajouter un plat</h2>
        <a href="plats.php" class="btn btn-secondary">← Retour</a>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Nom du plat *</label>
                <input type="text" name="nom" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Prix (FCFA) *</label>
                    <input type="number" name="prix" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Catégorie</label>
                    <select name="categorie_id" class="form-select">
                        <option value="">Sélectionner</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Image du plat</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-check mb-3">
                        <input type="checkbox" name="est_plat_du_jour" class="form-check-input" id="plat_jour">
                        <label class="form-check-label" for="plat_jour">⭐ Définir comme plat du jour</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mb-3">
                        <input type="checkbox" name="est_visible" class="form-check-input" id="visible" checked>
                        <label class="form-check-label" for="visible">Visible sur le site</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-save"><i class="bi bi-save"></i> Enregistrer</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>