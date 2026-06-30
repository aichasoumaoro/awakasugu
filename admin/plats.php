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

// Suppression
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM plats WHERE id = ?")->execute([$id]);
    header('Location: plats.php?msg=supprime');
    exit;
}

// Définir comme plat du jour
if (isset($_GET['plat_jour'])) {
    $id = (int)$_GET['plat_jour'];
    $pdo->exec("UPDATE plats SET est_plat_du_jour = 0");
    $pdo->prepare("UPDATE plats SET est_plat_du_jour = 1 WHERE id = ?")->execute([$id]);
    header('Location: plats.php?msg=update');
    exit;
}

$plats = $pdo->query("SELECT p.*, c.nom as categorie_nom FROM plats p LEFT JOIN categories c ON p.categorie_id = c.id ORDER BY p.id DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories WHERE type = 'restaurant' ORDER BY nom")->fetchAll();
$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des plats - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f5f5f5; }
        .sidebar { background: #0D0D0D; min-height: 100vh; position: fixed; top: 0; left: 0; width: 260px; padding: 20px 0; }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(200,146,42,0.2); }
        .sidebar-logo h3 { color: #C8922A; font-size: 1.3rem; margin: 0; }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 12px 24px; color: rgba(255,255,255,0.7); text-decoration: none; }
        .sidebar-menu li a:hover, .sidebar-menu li a.active { background: rgba(200,146,42,0.1); color: #C8922A; border-left: 3px solid #C8922A; }
        .content { margin-left: 260px; padding: 30px; }
        .btn-ajouter { background: #C8922A; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; }
        table img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        .btn-plat-jour { background: #28A745; color: white; border: none; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-logo"><h3>✨ AWA KA SUGU ✨</h3><small style="color:rgba(255,255,255,0.4);">Administration</small></div>
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
        <h2>🍽️ Gestion des plats</h2>
        <a href="plat_ajouter.php" class="btn-ajouter"><i class="bi bi-plus-circle"></i> Ajouter un plat</a>
    </div>

    <?php if($msg == 'supprime'): ?>
        <div class="alert alert-success">✅ Plat supprimé !</div>
    <?php endif; ?>
    <?php if($msg == 'update'): ?>
        <div class="alert alert-success">✅ Plat du jour mis à jour !</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-dark text-white">Liste des plats</div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="table-dark">
                    <tr><th>ID</th><th>Image</th><th>Nom</th><th>Catégorie</th><th>Prix</th><th>Plat du jour</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach($plats as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td>
                            <?php if(!empty($p['image']) && file_exists('../uploads/plats/'.$p['image'])): ?>
                                <img src="../uploads/plats/<?= $p['image'] ?>" alt="<?= htmlspecialchars($p['nom']) ?>">
                            <?php else: ?>
                                <span class="text-muted">Aucune</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                        <td><?= $p['categorie_nom'] ?? 'Non classé' ?></td>
                        <td><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</td>
                        <td>
                            <?php if($p['est_plat_du_jour']): ?>
                                <span class="btn-plat-jour">⭐ Plat du jour</span>
                            <?php else: ?>
                                <a href="plats.php?plat_jour=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success">Définir plat du jour</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="plat_modifier.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                            <a href="plats.php?supprimer=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce plat ?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>