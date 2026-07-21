<?php
// ============================================
// DONNER SON AVIS - RESTAURANT SOFIA
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

$titre_page = 'Donner mon avis - Restaurant Sofia';
$meta_desc = 'Partagez votre expérience au Restaurant Sofia par Awa Ka Sugu.';

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

// Traitement du formulaire d'avis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer_avis'])) {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $note = (int)($_POST['note'] ?? 0);
    $commentaire = trim($_POST['commentaire'] ?? '');

    if (empty($nom) || empty($email) || $note < 1 || $note > 5 || empty($commentaire)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer un email valide.';
    } else {
        try {
            // Utilisation des colonnes EXACTES de votre table avis_clients
            $stmt = $pdo->prepare("
                INSERT INTO avis_clients (nom_client, email, note, commentaire, est_valide, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$nom, $email, $note, $commentaire]);
            $success = true;
        } catch(PDOException $e) {
            $error = 'Erreur lors de l\'envoi de votre avis. Veuillez réessayer.';
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
    <title>Donner mon avis - Restaurant Sofia</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .container-custom { max-width: 700px; margin: 0 auto; padding: 0 20px; }
        .avis-section { padding: 40px 0 60px; }
        .avis-card {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.05);
            border: 1px solid #EEEAE5;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1A2C3E;
            margin-bottom: 6px;
        }
        .form-group label .required { color: #E74C3C; }
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
        textarea.form-control { min-height: 120px; resize: vertical; }

        .btn-envoyer {
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
        .btn-envoyer:hover {
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

        .etoiles-select {
            display: flex;
            gap: 8px;
            font-size: 2rem;
            cursor: pointer;
        }
        .etoiles-select .star { color: #ddd; transition: all 0.2s; }
        .etoiles-select .star.active { color: #F1C40F; }
        .etoiles-select .star:hover { transform: scale(1.2); }

        .success-card { text-align: center; padding: 40px; }
        .success-card .icon { font-size: 4rem; color: #27AE60; display: block; margin-bottom: 15px; }
        .success-card h2 { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: #1A2C3E; margin-bottom: 10px; }
        .success-card p { color: #8A99AA; font-size: 0.95rem; line-height: 1.6; }
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
        .btn-retour:hover { background: #9A6E1A; transform: translateY(-2px); }

        @media (max-width: 600px) {
            .avis-card { padding: 24px; }
            .hero h1 { font-size: 1.8rem; }
            .etoiles-select { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

<!-- ============================================
     FLASH MESSAGE (géré manuellement)
     ============================================ -->
<?php 
// Afficher les messages de session s'ils existent
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
        <h1>Donner mon <span>Avis</span></h1>
        <p>Partagez votre expérience au Restaurant Sofia</p>
    </div>
</section>

<div class="container-custom">
    <div class="avis-section">
        <a href="menu.php" class="btn-retour-menu">
            <i class="bi bi-arrow-left"></i> Retour au menu
        </a>

        <?php if ($success): ?>
            <div class="avis-card success-card">
                <span class="icon"><i class="bi bi-check-circle-fill"></i></span>
                <h2>Merci pour votre avis !</h2>
                <p>Votre avis a été enregistré avec succès.<br>
                Il sera publié après modération par notre équipe.</p>
                <a href="menu.php" class="btn-retour">
                    <i class="bi bi-arrow-left"></i> Retour au menu
                </a>
            </div>
        <?php else: ?>
            <div class="avis-card">
                <?php if ($error): ?>
                    <div class="alert-error">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Votre nom <span class="required">*</span></label>
                        <input type="text" name="nom" class="form-control" placeholder="Votre nom" required>
                    </div>

                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="votre@email.com" required>
                    </div>

                    <div class="form-group">
                        <label>Note <span class="required">*</span></label>
                        <input type="hidden" name="note" id="note_input" value="0">
                        <div class="etoiles-select" id="etoiles">
                            <span class="star" data-value="1"><i class="bi bi-star-fill"></i></span>
                            <span class="star" data-value="2"><i class="bi bi-star-fill"></i></span>
                            <span class="star" data-value="3"><i class="bi bi-star-fill"></i></span>
                            <span class="star" data-value="4"><i class="bi bi-star-fill"></i></span>
                            <span class="star" data-value="5"><i class="bi bi-star-fill"></i></span>
                        </div>
                        <small style="color:#8A99AA;">Cliquez sur les étoiles pour noter</small>
                    </div>

                    <div class="form-group">
                        <label>Votre commentaire <span class="required">*</span></label>
                        <textarea name="commentaire" class="form-control" placeholder="Décrivez votre expérience..." required></textarea>
                    </div>

                    <button type="submit" name="envoyer_avis" class="btn-envoyer">
                        <i class="bi bi-send"></i> Envoyer mon avis
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const etoiles = document.querySelectorAll('.etoiles-select .star');
    const noteInput = document.getElementById('note_input');

    etoiles.forEach(star => {
        star.addEventListener('click', function() {
            const value = parseInt(this.dataset.value);
            noteInput.value = value;
            
            etoiles.forEach(s => {
                const val = parseInt(s.dataset.value);
                if (val <= value) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        });

        star.addEventListener('mouseenter', function() {
            const value = parseInt(this.dataset.value);
            etoiles.forEach(s => {
                const val = parseInt(s.dataset.value);
                if (val <= value) {
                    s.style.color = '#F1C40F';
                } else {
                    s.style.color = '#ddd';
                }
            });
        });

        star.addEventListener('mouseleave', function() {
            const currentValue = parseInt(noteInput.value);
            etoiles.forEach(s => {
                const val = parseInt(s.dataset.value);
                if (val <= currentValue) {
                    s.style.color = '#F1C40F';
                } else {
                    s.style.color = '#ddd';
                }
            });
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>