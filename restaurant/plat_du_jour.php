<?php
// ============================================
// PLAT DU JOUR - AWA KA SUGU
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

$titre_page = 'Plat du jour - Restaurant Sofia';
$meta_desc = 'Découvrez le plat du jour du Restaurant Sofia par Awa Ka Sugu.';

require_once '../includes/header.php';
require_once '../includes/navbar.php';

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

// Récupérer le plat du jour
$plat = $pdo->query("SELECT * FROM plats WHERE est_plat_du_jour = 1 AND est_visible = 1 LIMIT 1")->fetch();

// Autres plats populaires
$autres_plats = $pdo->query("SELECT * FROM plats WHERE est_visible = 1 AND est_plat_du_jour = 0 ORDER BY RAND() LIMIT 4")->fetchAll();

$flash = get_message();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plat du jour - Restaurant Sofia</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jost', sans-serif; background: #FAF8F5; color: #1A2C3E; }

        .hero {
            background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
            padding: 50px 0 30px;
            text-align: center;
        }
        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: #FFFFFF;
        }
        .hero h1 span { color: #C8922A; }
        .hero p {
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .container-custom { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .plat-jour-section { padding: 40px 0; }

        .plat-jour-card {
            background: linear-gradient(135deg, #1A1A1A 0%, #2D2D2D 100%);
            border-radius: 24px;
            padding: 50px;
            display: flex;
            gap: 50px;
            align-items: center;
            border: 1px solid rgba(200,146,42,0.2);
            position: relative;
            overflow: hidden;
        }
        .plat-jour-card::before {
            content: '⭐';
            position: absolute;
            top: -30px;
            right: -30px;
            font-size: 12rem;
            opacity: 0.05;
        }
        .plat-jour-image {
            flex: 0 0 300px;
            height: 300px;
            border-radius: 16px;
            overflow: hidden;
            border: 3px solid rgba(200,146,42,0.2);
        }
        .plat-jour-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .plat-jour-image img:hover {
            transform: scale(1.05);
        }
        .plat-jour-image .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #2D2D2D;
            color: rgba(255,255,255,0.2);
            font-size: 4rem;
        }
        .plat-jour-info { flex: 1; }
        .plat-jour-info .label {
            display: inline-block;
            background: rgba(200,146,42,0.12);
            color: #C8922A;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 4px 16px;
            border-radius: 20px;
            margin-bottom: 15px;
        }
        .plat-jour-info h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: #FFFFFF;
            margin-bottom: 10px;
        }
        .plat-jour-info .desc {
            color: rgba(255,255,255,0.6);
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        .plat-jour-info .price {
            font-size: 2.5rem;
            font-weight: 700;
            color: #C8922A;
            margin-bottom: 20px;
        }
        .btn-commander {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            padding: 14px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .btn-commander:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(200,146,42,0.3);
            color: #FFFFFF;
        }

        .btn-retour {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #C8922A;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .btn-retour:hover { color: #9A6E1A; }

        .autres-plats { margin-top: 40px; }
        .autres-plats h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: #1A2C3E;
            margin-bottom: 20px;
        }
        .grid-autres {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .card-autre {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid #EEEAE5;
            transition: all 0.3s;
        }
        .card-autre:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            border-color: rgba(200,146,42,0.2);
        }
        .card-autre .photo-mini {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 10px;
            border: 2px solid #EEEAE5;
            display: block;
        }
        .card-autre .photo-mini-empty {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 10px;
            border: 2px solid #EEEAE5;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            color: #ccc;
            font-size: 2rem;
        }
        .card-autre .nom {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 600;
            color: #1A2C3E;
        }
        .card-autre .prix {
            color: #C8922A;
            font-weight: 700;
            margin: 8px 0;
        }
        .btn-small {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #C8922A;
            color: #fff;
            padding: 5px 14px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-small:hover { background: #9A6E1A; }

        .empty-state { text-align: center; padding: 60px; background: #fff; border-radius: 16px; border: 1px solid #EEEAE5; }
        .empty-state i { font-size: 3rem; color: #ddd; display: block; margin-bottom: 15px; }

        @media (max-width: 768px) {
            .plat-jour-card { flex-direction: column; text-align: center; padding: 30px 24px; }
            .plat-jour-info h2 { font-size: 2rem; }
            .plat-jour-image { flex: 0 0 200px; height: 200px; width: 100%; max-width: 300px; }
            .grid-autres { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 500px) {
            .grid-autres { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php if ($flash): ?>
    <div style="background: #D4EDDA; color: #0A3622; padding: 12px 20px; text-align: center;">
        <?= $flash['texte'] ?>
    </div>
<?php endif; ?>

<section class="hero">
    <div class="container-custom">
        <h1>⭐ Plat du <span>Jour</span></h1>
        <p>Le délice du jour, préparé avec amour par Awa Doumbia</p>
    </div>
</section>

<div class="container-custom">

    <div style="padding: 30px 0;">
        <a href="menu.php" class="btn-retour">
            <i class="bi bi-arrow-left"></i> Retour au menu
        </a>

        <?php if ($plat): ?>
        <div class="plat-jour-card">
            <!-- Affichage de la photo du plat -->
            <div class="plat-jour-image">
                <?php if (!empty($plat['photo'])): ?>
                    <img src="../admin/<?= htmlspecialchars($plat['photo']) ?>" alt="<?= htmlspecialchars($plat['nom']) ?>">
                <?php else: ?>
                    <div class="no-image">
                        <i class="bi bi-image"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="plat-jour-info">
                <span class="label">⭐ Plat du jour</span>
                <h2><?= htmlspecialchars($plat['nom']) ?></h2>
                <p class="desc"><?= htmlspecialchars($plat['description'] ?? 'Un délice du jour à ne pas manquer !') ?></p>
                <div class="price"><?= number_format($plat['prix'], 0, ',', ' ') ?> FCFA</div>
                <a href="commande_repas.php?plat_id=<?= $plat['id'] ?>" class="btn-commander">
                    <i class="bi bi-bag-plus"></i> Commander maintenant
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-emoji-frown"></i>
            <h3>Aucun plat du jour</h3>
            <p>Revenez bientôt pour découvrir notre plat du jour.</p>
            <a href="menu.php" class="btn-commander" style="margin-top:15px;display:inline-flex;">
                <i class="bi bi-arrow-left"></i> Voir le menu
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($autres_plats)): ?>
        <div class="autres-plats">
            <h3>🍽️ Autres suggestions</h3>
            <div class="grid-autres">
                <?php foreach ($autres_plats as $p): ?>
                <div class="card-autre">
                    <?php if (!empty($p['photo'])): ?>
                        <img src="../admin/<?= htmlspecialchars($p['photo']) ?>" alt="<?= htmlspecialchars($p['nom']) ?>" class="photo-mini">
                    <?php else: ?>
                        <div class="photo-mini-empty">
                            <i class="bi bi-image"></i>
                        </div>
                    <?php endif; ?>
                    <div class="nom"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="prix"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</div>
                    <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="btn-small">
                        <i class="bi bi-bag-plus"></i> Commander
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>