<?php
// ============================================
// CONFIRMATION DE COMMANDE REPAS - AWA KA SUGU
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

$titre_page = 'Confirmation - Restaurant Sofia';
$meta_desc = 'Confirmation de votre commande de repas.';

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

$numero_commande = isset($_GET['numero']) ? $_GET['numero'] : '';

$commande = null;
if (!empty($numero_commande)) {
    $stmt = $pdo->prepare("SELECT * FROM commandes_repas WHERE numero_commande = ?");
    $stmt->execute([$numero_commande]);
    $commande = $stmt->fetch();
}

$flash = get_message();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation - Restaurant Sofia</title>
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

        .container-custom { max-width: 800px; margin: 0 auto; padding: 0 20px; }

        .confirmation-section { padding: 40px 0 60px; }

        .confirmation-card {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.05);
            border: 1px solid #EEEAE5;
            text-align: center;
        }
        .confirmation-card .icon {
            font-size: 4rem;
            color: #27AE60;
            display: block;
            margin-bottom: 15px;
        }
        .confirmation-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: #1A2C3E;
            margin-bottom: 10px;
        }
        .confirmation-card .subtitle {
            color: #8A99AA;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }

        .info-box {
            background: #F8F9FA;
            border-radius: 12px;
            padding: 20px;
            text-align: left;
            margin: 20px 0;
            border: 1px solid #EEEAE5;
        }
        .info-box .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #F0F2F5;
        }
        .info-box .row:last-child {
            border-bottom: none;
        }
        .info-box .label {
            color: #8A99AA;
            font-size: 0.85rem;
        }
        .info-box .value {
            font-weight: 600;
            color: #1A2C3E;
            font-size: 0.9rem;
        }
        .info-box .value.price {
            color: #C8922A;
            font-size: 1.1rem;
        }

        .btn-retour {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #C8922A;
            color: #fff;
            padding: 12px 30px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 15px;
            transition: all 0.3s;
        }
        .btn-retour:hover {
            background: #9A6E1A;
            transform: translateY(-2px);
        }

        .btn-retour-menu {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #C8922A;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .btn-retour-menu:hover { color: #9A6E1A; }

        .empty-state {
            text-align: center;
            padding: 40px;
        }
        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            display: block;
            margin-bottom: 15px;
        }

        @media (max-width: 600px) {
            .confirmation-card { padding: 24px; }
            .hero h1 { font-size: 1.8rem; }
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
        <h1>✅ Commande <span>confirmée</span></h1>
        <p>Votre commande de repas a été prise en compte</p>
    </div>
</section>

<div class="container-custom">
    <div class="confirmation-section">
        <a href="menu.php" class="btn-retour-menu">
            <i class="bi bi-arrow-left"></i> Retour au menu
        </a>

        <?php if ($commande): ?>
            <div class="confirmation-card">
                <span class="icon"><i class="bi bi-check-circle-fill"></i></span>
                <h2>Commande confirmée !</h2>
                <p class="subtitle">Nous avons bien reçu votre commande et nous la préparons avec soin.</p>

                <div class="info-box">
                    <div class="row">
                        <span class="label">Numéro de commande</span>
                        <span class="value"><strong><?= htmlspecialchars($commande['numero_commande']) ?></strong></span>
                    </div>
                    <div class="row">
                        <span class="label">Plat</span>
                        <span class="value"><?= htmlspecialchars($commande['nom_plat']) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Quantité</span>
                        <span class="value"><?= $commande['quantite'] ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Total</span>
                        <span class="value price"><?= number_format($commande['total'], 0, ',', ' ') ?> FCFA</span>
                    </div>
                    <div class="row">
                        <span class="label">Client</span>
                        <span class="value"><?= htmlspecialchars($commande['nom_client']) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Adresse</span>
                        <span class="value"><?= htmlspecialchars($commande['adresse_livraison']) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Statut</span>
                        <span class="value">
                            <span style="background:#FEF6E6;padding:2px 12px;border-radius:20px;font-size:0.75rem;color:#E67E22;">
                                En attente de confirmation
                            </span>
                        </span>
                    </div>
                </div>

                <p style="color:#8A99AA;font-size:0.85rem;margin-top:10px;">
                    <i class="bi bi-clock"></i> Livraison prévue sous 45-60 minutes
                </p>

                <a href="menu.php" class="btn-retour">
                    <i class="bi bi-arrow-left"></i> Retourner au menu
                </a>
            </div>
        <?php else: ?>
            <div class="confirmation-card empty-state">
                <i class="bi bi-file-text"></i>
                <h2>Commande non trouvée</h2>
                <p style="color:#8A99AA;font-size:0.95rem;">Nous n'avons pas trouvé de commande avec ce numéro.</p>
                <a href="menu.php" class="btn-retour" style="display:inline-flex;margin-top:15px;">
                    <i class="bi bi-arrow-left"></i> Retour au menu
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>