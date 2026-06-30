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

$produits = $pdo->query("SELECT * FROM produits WHERE est_visible = 1 ORDER BY nom")->fetchAll();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'pourcentage';
    $valeur = (float)$_POST['valeur'];
    $produit_id = !empty($_POST['produit_id']) ? (int)$_POST['produit_id'] : null;
    $date_debut = $_POST['date_debut'] ?? date('Y-m-d');
    $date_fin = $_POST['date_fin'] ?? date('Y-m-d', strtotime('+30 days'));
    $est_active = isset($_POST['est_active']) ? 1 : 0;
    
    if (empty($nom) || empty($valeur) || empty($date_debut) || empty($date_fin)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif ($valeur <= 0) {
        $error = 'La valeur de la réduction doit être positive.';
    } elseif (strtotime($date_fin) < strtotime($date_debut)) {
        $error = 'La date de fin doit être postérieure à la date de début.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO promotions (nom, description, type, valeur, produit_id, date_debut, date_fin, est_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$nom, $description, $type, $valeur, $produit_id, $date_debut, $date_fin, $est_active]);
            
            // Si le produit est concerné, mettre à jour est_promo dans produits
            if ($produit_id) {
                // Récupérer le prix du produit
                $stmt = $pdo->prepare("SELECT prix FROM produits WHERE id = ?");
                $stmt->execute([$produit_id]);
                $produit = $stmt->fetch();
                if ($produit) {
                    if ($type == 'pourcentage') {
                        $prix_promo = $produit['prix'] - ($produit['prix'] * $valeur / 100);
                    } else {
                        $prix_promo = max(0, $produit['prix'] - $valeur);
                    }
                    $pdo->prepare("UPDATE produits SET est_promo = 1, prix_promo = ? WHERE id = ?")->execute([$prix_promo, $produit_id]);
                }
            }
            
            $success = 'Promotion ajoutée avec succès !';
            // Rediriger après 2 secondes
            header('refresh:2;url=promotions.php');
        } catch(PDOException $e) {
            $error = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une promotion - Admin Awa Ka Sugu</title>
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
        }
        .btn-save:hover {
            background: linear-gradient(135deg, #9A6E1A, #C8922A);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(200,146,42,0.3);
        }
        .btn-back {
            background: #6C757D;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }
        .btn-back:hover { background: #5A6268; color: white; }
        
        .alert { border-radius: 10px; border: none; border-left: 4px solid; }
        .alert-danger { border-left-color: #E74C3C; background: #FEF3F2; color: #721C24; }
        .alert-success { border-left-color: #27AE60; background: #D4EDDA; color: #0A3622; }
        
        .form-check-label { font-size: 0.85rem; }
        .required { color: #E74C3C; }
        .info-text { font-size: 0.75rem; color: #8A99AA; margin-top: 4px; }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .content { margin-left: 0; padding: 20px 16px; }
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
        <li><a href="promotions.php" class="active"><i class="bi bi-percent"></i> Promotions</a></li>
        <li><a href="videos.php"><i class="bi bi-camera-reels"></i> Vidéos</a></li>
        <li><a href="factures.php"><i class="bi bi-file-earmark-text"></i> Factures</a></li>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
    </ul>
</div>

<div class="content">
    <div class="content-header">
        <div>
            <h2><span>➕</span> Ajouter une promotion</h2>
            <div class="breadcrumb">
                <a href="dashboard.php">Administration</a> &gt; 
                <a href="promotions.php">Promotions</a> &gt; 
                Ajouter
            </div>
        </div>
        <a href="promotions.php" class="btn-back">
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
            <br><small style="color:#666;">Redirection vers la liste des promotions...</small>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <div class="form-title">
            <i class="bi bi-plus-circle"></i> Nouvelle promotion
        </div>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Nom de la promotion <span class="required">*</span></label>
                        <input type="text" name="nom" class="form-control" placeholder="Ex: Soldes d'été, Promo Ramadan..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Description de la promotion..."></textarea>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Statut</label>
                        <div class="form-check">
                            <input type="checkbox" name="est_active" class="form-check-input" id="active" checked>
                            <label class="form-check-label" for="active">✓ Active</label>
                        </div>
                        <div class="info-text">Décochez pour désactiver la promotion</div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Type de réduction <span class="required">*</span></label>
                        <select name="type" class="form-select">
                            <option value="pourcentage">Pourcentage (%)</option>
                            <option value="montant_fixe">Montant fixe (FCFA)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Valeur <span class="required">*</span></label>
                        <input type="number" name="valeur" class="form-control" step="1" placeholder="Ex: 20 pour 20%" required>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Produit concerné</label>
                <select name="produit_id" class="form-select">
                    <option value="">📦 Tous les produits</option>
                    <?php foreach($produits as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="info-text">Sélectionnez un produit pour une promotion ciblée, ou laissez vide pour une promotion globale.</div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Date de début <span class="required">*</span></label>
                        <input type="date" name="date_debut" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Date de fin <span class="required">*</span></label>
                        <input type="date" name="date_fin" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                        <div class="info-text">La promotion sera automatiquement désactivée après cette date.</div>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn-save">
                    <i class="bi bi-save"></i> Enregistrer la promotion
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>