<?php
// ============================================
// AJOUTER UN AVIS - CLIENT
// ============================================

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

// Vérifier que le client est connecté
if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

// Connexion à la BDD
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

$produit_id = isset($_GET['produit_id']) ? (int)$_GET['produit_id'] : 0;

if ($produit_id <= 0) {
    header('Location: ../index.php');
    exit;
}

$client_id = $_SESSION['client_id'];

// Récupérer les informations du client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    die("Client non trouvé.");
}

// Récupérer le nom du produit
$stmt = $pdo->prepare("SELECT nom FROM produits WHERE id = ?");
$stmt->execute([$produit_id]);
$produit = $stmt->fetch();
$nom_produit = $produit['nom'] ?? 'Produit';

// Initialisation des variables
$erreur = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier que les champs existent
    $note = isset($_POST['note']) ? (int)$_POST['note'] : 0;
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
    $recommandation = isset($_POST['recommandation']) ? 1 : 0;

    if ($note >= 1 && $note <= 5 && !empty($commentaire)) {
        // Récupérer le nom du client depuis la table clients
        $nom_client = $client['nom'] ?? 'Client';
        
        $sql = "INSERT INTO avis_clients 
                (client_id, produit_id, nom_client, note, commentaire, recommandation, est_visible, est_valide, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 0, 0, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $client_id, 
            $produit_id, 
            $nom_client, 
            $note, 
            $commentaire, 
            $recommandation
        ]);

        // Message de succès
        $_SESSION['message_avis'] = '✅ Merci ! Votre avis a été envoyé avec succès. Il sera publié après validation par l\'administrateur.';
        header("Location: ../boutique/produit.php?id=$produit_id");
        exit;
    } else {
        $erreur = '⚠️ Veuillez donner une note (1 à 5 étoiles) et un commentaire.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laisser un avis - Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Jost', sans-serif;
            background: #F5F7FA;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: #fff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: #0D0D0D;
            margin-bottom: 5px;
        }
        h1 span { color: #C8922A; }
        .sous-titre {
            color: #8A99AA;
            font-size: 0.9rem;
            margin-bottom: 25px;
        }
        .sous-titre strong { color: #0D0D0D; }
        label {
            display: block;
            font-weight: 500;
            color: #1A2C3E;
            margin-top: 20px;
            margin-bottom: 5px;
        }
        textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #E0E6ED;
            border-radius: 10px;
            font-family: 'Jost', sans-serif;
            font-size: 0.95rem;
            transition: border-color 0.3s;
            background: #F8F9FA;
            resize: vertical;
            min-height: 120px;
        }
        textarea:focus {
            outline: none;
            border-color: #C8922A;
            background: #fff;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding: 10px 16px;
            background: #F8F9FA;
            border-radius: 10px;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #C8922A;
            cursor: pointer;
        }
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: 400;
        }
        .btn-submit {
            background: #C8922A;
            color: #fff;
            border: none;
            padding: 14px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Jost', sans-serif;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 25px;
            width: 100%;
        }
        .btn-submit:hover { background: #9A6E1A; }
        .btn-retour {
            display: inline-block;
            margin-top: 15px;
            color: #8A99AA;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn-retour:hover { color: #C8922A; }
        .erreur {
            background: #F8D7DA;
            color: #721C24;
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #E74C3C;
        }

        /* ========================================== */
        /* STYLES POUR LES ÉTOILES CLICABLES          */
        /* ========================================== */
        .etoiles-choix {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
            padding: 10px 12px;
            background: #F8F9FA;
            border-radius: 10px;
        }
        .etoiles-choix input[type="radio"] {
            display: none;
        }
        .etoiles-choix label {
            font-size: 2.2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
            margin: 0;
            padding: 0;
            line-height: 1;
        }
        .etoiles-choix label:hover,
        .etoiles-choix label:hover ~ label {
            color: #F1C40F;
        }
        .etoiles-choix input[type="radio"]:checked ~ label {
            color: #F1C40F;
        }
        .etoiles-choix label:active {
            transform: scale(0.9);
        }
        #appreciation {
            font-size: 0.9rem;
            font-weight: 500;
            color: #C8922A;
            margin-top: 8px;
            display: block;
            min-height: 24px;
        }
        .info-etoiles {
            color: #8A99AA;
            font-size: 0.8rem;
            margin-top: 5px;
            display: block;
        }
        /* ========================================== */
    </style>
</head>
<body>

<div class="container">
    <h1>⭐ Laisser un <span>avis</span></h1>
    <p class="sous-titre">
        Pour le produit : <strong><?= htmlspecialchars($nom_produit) ?></strong>
    </p>

    <?php if(!empty($erreur)): ?>
        <div class="erreur"><?= $erreur ?></div>
    <?php endif; ?>

    <form method="POST" id="avisForm">
        <!-- NOTE AVEC ÉTOILES CLICABLES -->
        <label>Votre note :</label>
        <div class="etoiles-choix" id="etoilesContainer">
            <input type="radio" name="note" id="star5" value="5">
            <label for="star5" title="Excellent">⭐</label>
            
            <input type="radio" name="note" id="star4" value="4">
            <label for="star4" title="Très bien">⭐</label>
            
            <input type="radio" name="note" id="star3" value="3">
            <label for="star3" title="Bien">⭐</label>
            
            <input type="radio" name="note" id="star2" value="2">
            <label for="star2" title="Moyen">⭐</label>
            
            <input type="radio" name="note" id="star1" value="1">
            <label for="star1" title="Mauvais">⭐</label>
        </div>
        <span id="appreciation"></span>
        <span class="info-etoiles">Cliquez sur une étoile pour donner votre note</span>

        <!-- COMMENTAIRE -->
        <label>Votre commentaire :</label>
        <textarea name="commentaire" placeholder="Partagez votre expérience avec ce produit..." required></textarea>

        <!-- RECOMMANDATION -->
        <div class="checkbox-group">
            <input type="checkbox" name="recommandation" id="recommandation" value="1">
            <label for="recommandation">👍 Je recommande ce produit à d'autres personnes</label>
        </div>

        <button type="submit" class="btn-submit">📩 Envoyer mon avis</button>
    </form>

    <a href="../boutique/produit.php?id=<?= $produit_id ?>" class="btn-retour">
        ← Retour au produit
    </a>
</div>

<script>
// ============================================
// GESTION DES ÉTOILES ET DE L'APPRÉCIATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const etoiles = document.querySelectorAll('.etoiles-choix input[type="radio"]');
    const appreciation = document.getElementById('appreciation');

    // Tableau des appréciations
    const appreciations = {
        1: '😞 Mauvais - Nous allons nous améliorer',
        2: '😐 Moyen - Nous pouvons faire mieux',
        3: '🙂 Bien - Merci pour votre retour',
        4: '😊 Très bien - Nous sommes ravis',
        5: '🌟 Excellent - Merci beaucoup !'
    };

    // Ajouter un événement à chaque étoile
    etoiles.forEach(function(etoile) {
        etoile.addEventListener('change', function() {
            const note = parseInt(this.value);
            if (note >= 1 && note <= 5) {
                appreciation.textContent = appreciations[note] || '';
                appreciation.style.color = '#C8922A';
            }
        });
    });
});
</script>

</body>
</html>