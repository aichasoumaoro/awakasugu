<?php
// ============================================
// RÉSERVATION - RESTAURANT SOFIA (Front-end)
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

$titre_page = 'Réservation - Restaurant Sofia';
$meta_desc = 'Réservez votre table au Restaurant Sofia par Awa Ka Sugu.';

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

$success = false;
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $heure = trim($_POST['heure'] ?? '');
    $personnes = (int)($_POST['personnes'] ?? 1);
    $message = trim($_POST['message'] ?? '');

    if (empty($nom) || empty($telephone) || empty($date) || empty($heure)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO reservations (nom_client, telephone, email, date_reservation, heure_reservation, nombre_personnes, message, statut, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW())
            ");
            $stmt->execute([$nom, $telephone, $email, $date, $heure, $personnes, $message]);
            $success = true;
            $_SESSION['reservation_success'] = true;
            header('Location: reservation.php?success=1');
            exit;
        } catch(PDOException $e) {
            $error = 'Erreur lors de la réservation. Veuillez réessayer.';
        }
    }
}

$success_get = isset($_GET['success']) && $_GET['success'] == 1;
$flash = get_message();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation - Restaurant Sofia</title>
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

        .reservation-section { padding: 40px 0 60px; }

        .reservation-card {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.05);
            border: 1px solid #EEEAE5;
        }

        .form-group {
            margin-bottom: 20px;
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

        .btn-reserver {
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
        .btn-reserver:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(200,146,42,0.3);
            color: #FFFFFF;
        }

        .alert-success {
            background: #D4EDDA;
            border-left: 4px solid #27AE60;
            color: #0A3622;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #FEF3F2;
            border-left: 4px solid #E74C3C;
            color: #721C24;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
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

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .reservation-card { padding: 24px; }
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
        <h1>📅 Réservation <span>Sofia</span></h1>
        <p>Réservez votre table et vivez une expérience culinaire unique</p>
    </div>
</section>

<div class="container-custom">
    <div class="reservation-section">
        <a href="menu.php" class="btn-retour-menu">
            <i class="bi bi-arrow-left"></i> Retour au menu
        </a>

        <?php if ($success_get): ?>
            <div class="reservation-card success-card">
                <span class="icon"><i class="bi bi-check-circle-fill"></i></span>
                <h2>✅ Réservation confirmée !</h2>
                <p>Merci pour votre réservation. Nous vous attendons avec plaisir.<br>
                Un message de confirmation vous sera envoyé par téléphone.</p>
                <a href="menu.php" class="btn-retour">
                    <i class="bi bi-arrow-left"></i> Retour au menu
                </a>
            </div>
        <?php else: ?>
            <div class="reservation-card">
                <?php if ($error): ?>
                    <div class="alert-error">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
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

                    <div class="form-row">
                        <div class="form-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" name="date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Heure <span class="required">*</span></label>
                            <input type="time" name="heure" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Nombre de personnes</label>
                        <select name="personnes" class="form-control">
                            <?php for ($i = 1; $i <= 20; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> personne<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Message (optionnel)</label>
                        <textarea name="message" class="form-control" rows="3" placeholder="Demandes spéciales, occasion, etc."></textarea>
                    </div>

                    <button type="submit" class="btn-reserver">
                        <i class="bi bi-calendar-check"></i> Réserver maintenant
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>