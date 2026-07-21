<?php
// ============================================
// COMMANDE GÂTEAU - RESTAURANT SOFIA
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

$titre_page = 'Commander un gâteau - Restaurant Sofia';
$meta_desc = 'Commandez votre gâteau événementiel personnalisé au Restaurant Sofia.';

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

// Récupérer l'ID du gâteau depuis l'URL
$gateau_id = isset($_GET['gateau_id']) ? (int)$_GET['gateau_id'] : 0;
$gateau = null;

if ($gateau_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM plats WHERE id = ? AND est_visible = 1 AND categorie_id = 10");
    $stmt->execute([$gateau_id]);
    $gateau = $stmt->fetch();
}

$error = '';
$success = false;
$commande_id = 0;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commander'])) {
    $nom_client = trim($_POST['nom_client'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $date_evenement = trim($_POST['date_evenement'] ?? '');
    $type_evenement = trim($_POST['type_evenement'] ?? '');
    $nom_personnalise = trim($_POST['nom_personnalise'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $message_inscription = trim($_POST['message_inscription'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $gateau_id = (int)($_POST['gateau_id'] ?? 0);
    $prix = (float)($_POST['prix'] ?? 0);

    if (empty($nom_client) || empty($telephone) || empty($adresse) || empty($date_evenement) || $gateau_id <= 0) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        // Récupérer le gâteau
        $stmt = $pdo->prepare("SELECT * FROM plats WHERE id = ? AND est_visible = 1");
        $stmt->execute([$gateau_id]);
        $gateau_info = $stmt->fetch();

        if ($gateau_info) {
            $numero_commande = 'GATEAU-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                INSERT INTO commandes_gateaux (
                    numero_commande, 
                    gateau_id, 
                    nom_gateau, 
                    prix,
                    nom_client, 
                    telephone, 
                    email, 
                    adresse_livraison, 
                    date_evenement,
                    type_evenement,
                    nom_personnalise,
                    age,
                    message_inscription,
                    instructions,
                    statut, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW())
            ");
            
            $stmt->execute([
                $numero_commande, 
                $gateau_info['id'],
                $gateau_info['nom'],
                $gateau_info['prix'],
                $nom_client, 
                $telephone, 
                $email, 
                $adresse,
                $date_evenement,
                $type_evenement,
                $nom_personnalise,
                $age,
                $message_inscription,
                $instructions
            ]);

            $commande_id = $pdo->lastInsertId();
            $success = true;
            
            $_SESSION['message_success'] = 'Votre commande de gâteau a été enregistrée avec succès !';
            header('Location: commande_gateau.php?success=1');
            exit;
        } else {
            $error = 'Gâteau non trouvé. Veuillez choisir un gâteau valide.';
        }
    }
}

// Vérifier si la commande est un succès
$success_get = isset($_GET['success']) && $_GET['success'] == 1;

// ✅ INCLURE HEADER APRÈS TOUS LES TRAITEMENTS
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commander un gâteau - Restaurant Sofia</title>
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

        .alert-success {
            background: #D4EDDA;
            border-left: 4px solid #27AE60;
            color: #0A3622;
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

        .gateau-resume {
            background: #FEFBF5;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(200,146,42,0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .gateau-resume .nom {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            color: #1A2C3E;
        }
        .gateau-resume .prix {
            color: #C8922A;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .gateau-resume .badge {
            display: inline-block;
            background: rgba(200,146,42,0.1);
            color: #C8922A;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            margin-top: 4px;
        }
        
        .info-personnalisation {
            background: #F0F8FF;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(41,128,185,0.15);
        }
        .info-personnalisation h4 {
            color: #2980B9;
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-personnalisation p {
            font-size: 0.8rem;
            color: #5A6B7A;
        }

        .champ-conditionnel {
            display: none;
            padding: 10px 15px;
            background: #FFF8E1;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #F1C40F;
        }
        .champ-conditionnel.visible {
            display: block;
        }

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .commande-card { padding: 24px; }
            .hero h1 { font-size: 1.8rem; }
            .gateau-resume { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<!-- ============================================
     FLASH MESSAGE
     ============================================ -->
<?php if ($success_get): ?>
    <div class="alert-success" style="background: #D4EDDA; color: #0A3622; padding: 15px 20px; text-align: center; border-bottom: 2px solid #27AE60;">
        <i class="bi bi-check-circle-fill"></i> Votre commande de gâteau a été enregistrée avec succès ! 
        <a href="menu.php#gateaux" style="color:#0A3622;text-decoration:underline;font-weight:600;">Retour au menu</a>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['message_error'])): ?>
    <div style="background: #F8D7DA; color: #721C24; padding: 12px 20px; text-align: center; border-bottom: 2px solid #E74C3C;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($_SESSION['message_error']) ?>
    </div>
    <?php unset($_SESSION['message_error']); ?>
<?php endif; ?>

<section class="hero">
    <div class="container-custom">
        <h1>Commander un <span>Gâteau</span></h1>
        <p>Personnalisez votre gâteau événementiel</p>
    </div>
</section>

<div class="container-custom">
    <div class="commande-section">
        <a href="menu.php#gateaux" class="btn-retour-menu">
            <i class="bi bi-arrow-left"></i> Retour au menu
        </a>

        <?php if ($success): ?>
            <div class="commande-card success-card">
                <span class="icon"><i class="bi bi-check-circle-fill"></i></span>
                <h2>Commande confirmée !</h2>
                <p>Votre commande de gâteau a été enregistrée avec succès.<br>
                Vous recevrez une confirmation par téléphone.</p>
                <a href="menu.php#gateaux" class="btn-retour">
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

                <?php if ($gateau): ?>
                <div class="gateau-resume">
                    <div>
                        <span class="nom"><?= htmlspecialchars($gateau['nom']) ?></span>
                        <span class="badge">🎂 Personnalisable</span>
                    </div>
                    <span class="prix"><?= number_format($gateau['prix'], 0, ',', ' ') ?> FCFA</span>
                </div>

                <!-- Info personnalisation -->
                <div class="info-personnalisation">
                    <h4><i class="bi bi-pencil-square"></i> Personnalisation</h4>
                    <p>Remplissez les champs ci-dessous pour personnaliser votre gâteau selon vos besoins.</p>
                </div>
                <?php else: ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i> Gâteau non trouvé. Veuillez choisir un gâteau valide.
                </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="gateau_id" value="<?= $gateau_id ?>">
                    <input type="hidden" name="prix" value="<?= $gateau ? $gateau['prix'] : 0 ?>">

                    <div class="form-group">
                        <label>Nom complet <span class="required">*</span></label>
                        <input type="text" name="nom_client" class="form-control" placeholder="Votre nom" required>
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
                            <label>Date de l'événement <span class="required">*</span></label>
                            <input type="date" name="date_evenement" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Type d'événement</label>
                            <select name="type_evenement" class="form-control">
                                <option value="">Sélectionnez</option>
                                <option value="anniversaire">Anniversaire</option>
                                <option value="mariage">Mariage</option>
                                <option value="bapteme">Baptême</option>
                                <option value="hadj">Hadj / Mecque</option>
                                <option value="aid">Aïd</option>
                                <option value="ramadan">Ramadan</option>
                                <option value="entreprise">Entreprise</option>
                                <option value="naissance">Naissance</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                    </div>

                    <!-- ===== PERSONNALISATION DU GÂTEAU ===== -->
                    <div class="form-group">
                        <label>Nom à inscrire sur le gâteau</label>
                        <input type="text" name="nom_personnalise" class="form-control" placeholder="Ex: Aïcha, Mariage de Fatou et Moussa...">
                        <small style="color:#8A99AA;font-size:0.7rem;">Le nom ou message principal à inscrire sur le gâteau</small>
                    </div>

                    <!-- Champ Âge (visible pour anniversaire) -->
                    <div class="champ-conditionnel <?= (strpos($gateau['nom'] ?? '', 'Anniversaire') !== false || strpos($gateau['nom'] ?? '', 'anniversaire') !== false) ? 'visible' : '' ?>" id="champ_age">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Âge (pour anniversaire)</label>
                            <input type="number" name="age" class="form-control" placeholder="Ex: 25" min="1" max="120">
                            <small style="color:#8A99AA;font-size:0.7rem;">Indiquez l'âge pour un gâteau d'anniversaire</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Message d'inscription</label>
                        <input type="text" name="message_inscription" class="form-control" placeholder="Ex: Joyeux Anniversaire, Bonne Fête, Hadj Mabrour...">
                        <small style="color:#8A99AA;font-size:0.7rem;">Texte supplémentaire à inscrire sur le gâteau</small>
                    </div>

                    <div class="form-group">
                        <label>Instructions particulières</label>
                        <textarea name="instructions" class="form-control" rows="3" placeholder="Couleur, thème, parfum, décoration spéciale..."></textarea>
                    </div>

                    <button type="submit" name="commander" class="btn-commander">
                        <i class="bi bi-cake"></i> Confirmer ma commande
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Afficher/cacher le champ âge selon le type d'événement sélectionné
document.addEventListener('DOMContentLoaded', function() {
    const typeEvenement = document.querySelector('select[name="type_evenement"]');
    const champAge = document.getElementById('champ_age');
    
    if (typeEvenement && champAge) {
        // Fonction pour vérifier si on doit afficher le champ âge
        function toggleAgeField() {
            const valeur = typeEvenement.value;
            if (valeur === 'anniversaire') {
                champAge.classList.add('visible');
            } else {
                champAge.classList.remove('visible');
            }
        }
        
        // Vérifier au chargement
        toggleAgeField();
        
        // Vérifier au changement
        typeEvenement.addEventListener('change', toggleAgeField);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>