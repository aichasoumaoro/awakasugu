<?php
// ============================================
// COMMANDE REPAS - RESTAURANT SOFIA
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

$titre_page = 'Commander un repas - Restaurant Sofia';
$meta_desc = 'Commandez vos plats préférés du Restaurant Sofia par Awa Ka Sugu.';

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

// Récupérer l'ID du plat depuis l'URL
$plat_id = isset($_GET['plat_id']) ? (int)$_GET['plat_id'] : 0;
$plat = null;

if ($plat_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM plats WHERE id = ? AND est_visible = 1");
    $stmt->execute([$plat_id]);
    $plat = $stmt->fetch();
}

$error = '';
$success = false;
$commande_id = 0;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commander'])) {
    $nom = trim($_POST['nom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $plat_id = (int)($_POST['plat_id'] ?? 0);
    $quantite = (int)($_POST['quantite'] ?? 1);
    $instructions = trim($_POST['instructions'] ?? '');

    if (empty($nom) || empty($telephone) || empty($adresse) || $plat_id <= 0) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        // Récupérer le plat
        $stmt = $pdo->prepare("SELECT * FROM plats WHERE id = ? AND est_visible = 1");
        $stmt->execute([$plat_id]);
        $plat_info = $stmt->fetch();

        if ($plat_info) {
            $total = $plat_info['prix'] * $quantite;
            $numero_commande = 'REPAS-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

            // ✅ UTILISATION DES BONNES COLONNES
            // Vérifions d'abord la structure de la table commandes_repas
            // Colonnes: id, numero_commande, nom_plat, quantite, prix_unitaire, total,
            // nom_client, telephone, email, adresse_livraison, instructions, statut, created_at
            
            $stmt = $pdo->prepare("
                INSERT INTO commandes_repas (
                    numero_commande, 
                    nom_plat, 
                    quantite, 
                    prix_unitaire, 
                    total,
                    nom_client, 
                    telephone, 
                    email, 
                    adresse_livraison, 
                    instructions,
                    statut, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW())
            ");
            
            $stmt->execute([
                $numero_commande, 
                $plat_info['nom'],
                $quantite, 
                $plat_info['prix'], 
                $total,
                $nom, 
                $telephone, 
                $email, 
                $adresse, 
                $instructions
            ]);

            $commande_id = $pdo->lastInsertId();
            $success = true;
            
            // Rediriger vers la page de confirmation
            header("Location: confirmation_repas.php?numero=$numero_commande");
            exit;
        } else {
            $error = 'Plat non trouvé. Veuillez choisir un plat valide.';
        }
    }
}

// ✅ INCLURE HEADER APRÈS TOUS LES TRAITEMENTS
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commander un repas - Restaurant Sofia</title>
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

        .container-custom { max-width: 900px; margin: 0 auto; padding: 0 20px; }

        .commande-section { padding: 40px 0 60px; }

        .commande-card {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.05);
            border: 1px solid #EEEAE5;
        }

        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1A2C3E;
            margin-bottom: 6px;
        }
        .form-group label .required {
            color: #E74C3C;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #E0E6ED;
            border-radius: 10px;
            font-family: 'Jost', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #C8922A;
            box-shadow: 0 0 0 3px rgba(200,146,42,0.08);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-commander {
            width: 100%;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-commander:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(200,146,42,0.3);
            color: #FFFFFF;
        }

        .alert-error {
            background: #FEF3F2;
            border-left: 4px solid #E74C3C;
            color: #721C24;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-card {
            text-align: center;
            padding: 40px;
        }
        .success-card .icon {
            font-size: 4rem;
            color: #27AE60;
            display: block;
            margin-bottom: 15px;
        }
        .success-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: #1A2C3E;
            margin-bottom: 10px;
        }
        .success-card p {
            color: #8A99AA;
            font-size: 0.95rem;
            line-height: 1.6;
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

        .plat-resume {
            background: #FEFBF5;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(200,146,42,0.12);
        }
        .plat-resume .nom {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            color: #1A2C3E;
        }
        .plat-resume .prix {
            color: #C8922A;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .plat-resume .badge {
            display: inline-block;
            background: rgba(200,146,42,0.1);
            color: #C8922A;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            margin-top: 4px;
        }

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .commande-card { padding: 24px; }
            .hero h1 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>

<!-- ============================================
     FLASH MESSAGE
     ============================================ -->
<?php 
if (isset($_SESSION['message_success'])): ?>
    <div style="background: #D4EDDA; color: #0A3622; padding: 12px 20px; text-align: center; border-bottom: 2px solid #27AE60;">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_SESSION['message_success']) ?>
    </div>
    <?php unset($_SESSION['message_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['message_error'])): ?>
    <div style="background: #F8D7DA; color: #721C24; padding: 12px 20px; text-align: center; border-bottom: 2px solid #E74C3C;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($_SESSION['message_error']) ?>
    </div>
    <?php unset($_SESSION['message_error']); ?>
<?php endif; ?>

<section class="hero">
    <div class="container-custom">
        <h1>Commander un <span>repas</span></h1>
        <p>Commandez vos plats préférés et recevez-les chez vous</p>
    </div>
</section>

<div class="container-custom">
    <div class="commande-section">
        <a href="menu.php" class="btn-retour-menu">
            <i class="bi bi-arrow-left"></i> Retour au menu
        </a>

        <?php if ($success): ?>
            <div class="commande-card success-card">
                <span class="icon"><i class="bi bi-check-circle-fill"></i></span>
                <h2>Commande confirmée !</h2>
                <p>Votre commande a été enregistrée avec succès.<br>
                Vous recevrez une confirmation par téléphone.</p>
                <a href="menu.php" class="btn-retour">
                    <i class="bi bi-arrow-left"></i> Retour au menu
                </a>
            </div>
        <?php else: ?>
            <div class="commande-card">
                <?php if ($error): ?>
                    <div class="alert-error">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($plat): ?>
                <div class="plat-resume">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                        <div>
                            <span class="nom"><?= htmlspecialchars($plat['nom']) ?></span>
                            <?php if($plat['est_plat_du_jour']): ?>
                                <span class="badge">⭐ Plat du jour</span>
                            <?php endif; ?>
                        </div>
                        <span class="prix"><?= number_format($plat['prix'], 0, ',', ' ') ?> FCFA</span>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i> Plat non trouvé. Veuillez choisir un plat valide.
                </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="plat_id" value="<?= $plat_id ?>">

                    <div class="form-group">
                        <label>Nom complet <span class="required">*</span></label>
                        <input type="text" name="nom" class="form-control" placeholder="Votre nom" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Téléphone <span class="required">*</span></label>
                            <input type="tel" name="telephone" class="form-control" placeholder="77 00 00 00" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" placeholder="votre@email.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Adresse de livraison <span class="required">*</span></label>
                        <input type="text" name="adresse" class="form-control" placeholder="Rue, quartier, porte..." required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Quantité</label>
                            <input type="number" name="quantite" class="form-control" value="1" min="1" max="10">
                        </div>
                        <div class="form-group">
                            <label>Instructions particulières</label>
                            <input type="text" name="instructions" class="form-control" placeholder="Sans oignon, épicé, etc.">
                        </div>
                    </div>

                    <button type="submit" name="commander" class="btn-commander">
                        <i class="bi bi-bag-check"></i> Confirmer ma commande
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>