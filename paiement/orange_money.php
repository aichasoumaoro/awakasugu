<?php
session_start();

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$commande_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Récupérer la commande
$stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: ../boutique/catalogue.php');
    exit;
}

// Configuration Orange Money (à remplacer par les vraies clés d'Awa Doumbia)
$ORANGE_API_URL = "https://api.orange.com/orange-money-webpay/dev/v1/webpayment";
$ORANGE_MERCHANT_KEY = "VOTRE_CLE_MARCHAND";  // À remplacer
$ORANGE_ACCESS_TOKEN = "VOTRE_TOKEN_ACCES";   // À remplacer

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telephone = trim($_POST['telephone'] ?? '');
    $montant = $commande['total'];
    
    if (empty($telephone)) {
        $error = 'Veuillez entrer votre numéro Orange Money.';
    } else {
        // TODO: Appel API Orange Money réel
        // En attendant les clés, simulation pour tester
        $paiement_reussi = true;
        
        if ($paiement_reussi) {
            // Mettre à jour la commande
            $stmt = $pdo->prepare("UPDATE commandes SET statut = 'confirmee', mode_paiement = 'orange_money' WHERE id = ?");
            $stmt->execute([$commande_id]);
            
            // Enregistrer le paiement
            $stmt = $pdo->prepare("
                INSERT INTO paiements (commande_id, mode, montant, statut, reference_transaction, created_at)
                VALUES (?, 'orange_money', ?, 'confirme', ?, NOW())
            ");
            $reference = 'ORANGE-' . $commande['numero_commande'] . '-' . time();
            $stmt->execute([$commande_id, $montant, $reference]);
            
            // Vider le panier
            $_SESSION['panier'] = [];
            
            header("Location: confirmation_paiement.php?commande_id=$commande_id&statut=success");
            exit;
        } else {
            $error = 'Le paiement a échoué. Veuillez réessayer.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Orange Money - Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #FBF7F2; font-family: 'Jost', sans-serif; }
        .navbar { background: #0D0D0D; padding: 15px 0; }
        .navbar a { color: white; text-decoration: none; margin-right: 25px; }
        .navbar-brand { color: #C8922A !important; font-size: 1.5rem; font-weight: bold; }
        .paiement-container { max-width: 500px; margin: 60px auto; padding: 0 20px; }
        .paiement-card { background: white; border-radius: 20px; padding: 35px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .orange-icon { background: #FF6600; width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .orange-icon i { font-size: 2rem; color: white; }
        .montant { font-size: 2rem; font-weight: bold; color: #C8922A; margin: 20px 0; }
        .btn-payer { background: #FF6600; color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 600; width: 100%; cursor: pointer; }
        .btn-payer:hover { background: #E55A00; }
        footer { background: #0D0D0D; color: white; text-align: center; padding: 30px; margin-top: 60px; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="container">
        <a href="../index.php" class="navbar-brand">✨ AWA KA SUGU ✨</a>
        <div>
            <a href="../index.php">Accueil</a>
            <a href="../boutique/catalogue.php">Boutique</a>
            <a href="../restaurant/menu.php">Restaurant</a>
        </div>
    </div>
</nav>

<div class="paiement-container">
    <div class="paiement-card">
        <div class="orange-icon">
            <i class="bi bi-phone"></i>
        </div>
        <h2>Paiement Orange Money</h2>
        <p class="text-muted">Payez votre commande en toute sécurité</p>
        
        <div class="montant">
            <?= number_format($commande['total'], 0, ',', ' ') ?> FCFA
        </div>
        
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Vous allez recevoir une notification sur votre téléphone Orange Money.
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Numéro Orange Money</label>
                <input type="tel" name="telephone" class="form-control" placeholder="+223 XX XX XX XX" required>
                <small class="text-muted">Entrez le numéro associé à votre compte Orange Money</small>
            </div>
            <button type="submit" class="btn-payer">
                <i class="bi bi-lock"></i> Payer <?= number_format($commande['total'], 0, ',', ' ') ?> FCFA
            </button>
        </form>
        
        <div class="mt-3">
            <a href="../boutique/panier.php" class="text-decoration-none">← Retour au panier</a>
        </div>
    </div>
</div>

<footer>
    <div class="container">
        <p>© <?= date('Y') ?> Awa Ka Sugu - Tous droits réservés</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>