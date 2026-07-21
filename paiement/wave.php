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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telephone_paiement = trim($_POST['telephone_paiement'] ?? '');
    $nom_deposant = trim($_POST['nom_deposant'] ?? '');
    $montant = $commande['total'];
    
    if (empty($telephone_paiement)) {
        $error = 'Veuillez entrer le numéro de téléphone qui va effectuer le paiement.';
    } elseif (empty($nom_deposant)) {
        $error = 'Veuillez entrer le nom du déposant.';
    } else {
        try {
            // Démarrer la transaction
            $pdo->beginTransaction();
            
            // Mettre à jour la commande
            $stmt = $pdo->prepare("
                UPDATE commandes 
                SET statut = 'en_attente', 
                    mode_paiement = 'wave',
                    notes = CONCAT(COALESCE(notes, ''), '\nDépôt Wave - Téléphone: ', ?, ' - Nom: ', ?)
                WHERE id = ?
            ");
            $stmt->execute([$telephone_paiement, $nom_deposant, $commande_id]);
            
            // Générer une référence
            $reference = 'WAVE-' . $commande['numero_commande'] . '-' . time();
            
            // Enregistrer le paiement dans la table paiements
            $stmt = $pdo->prepare("
                INSERT INTO paiements (
                    commande_id, 
                    mode, 
                    montant, 
                    statut, 
                    reference_transaction,
                    telephone_paiement,
                    created_at
                ) VALUES (?, ?, ?, 'en_attente', ?, ?, NOW())
            ");
            $stmt->execute([
                $commande_id,
                'wave',
                $montant,
                $reference,
                $telephone_paiement
            ]);
            
            // Valider la transaction
            $pdo->commit();
            
            $_SESSION['message_paiement'] = 'Votre paiement a été enregistré. Nous vérifierons le dépôt et validerons votre commande.';
            header("Location: confirmation_paiement.php?commande_id=$commande_id&statut=attente");
            exit;
            
        } catch(PDOException $e) {
            // Annuler la transaction en cas d'erreur
            $pdo->rollBack();
            $error = 'Erreur lors de l\'enregistrement du paiement. Veuillez réessayer.';
            error_log("Erreur paiement Wave: " . $e->getMessage());
        }
    }
}

$titre_page = 'Paiement Wave - Awa Ka Sugu';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Wave - Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #F8F6F3; font-family: 'Jost', sans-serif; }
        .paiement-container { max-width: 550px; margin: 40px auto; padding: 0 20px; }
        .paiement-card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.06); border: 1px solid #F0EDEA; }
        .method-icon { background: linear-gradient(135deg, #1A7A4A, #27AE60); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .method-icon i { font-size: 2.5rem; color: white; }
        .montant { font-size: 2.2rem; font-weight: 700; color: #C8922A; margin: 15px 0; text-align: center; }
        .info-box { background: #F0F9F4; border-left: 4px solid #1A7A4A; padding: 15px 20px; border-radius: 10px; margin: 20px 0; }
        .info-box i { color: #1A7A4A; margin-right: 10px; }
        .form-control { border-radius: 10px; padding: 12px 16px; border: 1.5px solid #E8E0D8; font-family: 'Jost', sans-serif; }
        .form-control:focus { border-color: #1A7A4A; box-shadow: 0 0 0 3px rgba(26,122,74,0.1); }
        .btn-payer { background: linear-gradient(135deg, #1A7A4A, #27AE60); color: white; border: none; padding: 16px; border-radius: 12px; font-weight: 700; font-size: 1rem; width: 100%; cursor: pointer; transition: all 0.3s; }
        .btn-payer:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(26,122,74,0.3); }
        .btn-payer:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .numero-entreprise { background: #F5F0EB; border-radius: 10px; padding: 15px; text-align: center; margin: 15px 0; }
        .numero-entreprise .numero { font-size: 1.3rem; font-weight: 700; color: #1A7A4A; letter-spacing: 2px; }
        .alert-danger { background: #FDE8E8; border-left: 4px solid #E74C3C; padding: 12px 16px; border-radius: 10px; color: #721C24; margin-bottom: 15px; }
        .alert-danger i { margin-right: 10px; }
        .text-muted { color: #8A99AA !important; }
        footer { background: #0D0D0D; color: white; text-align: center; padding: 30px; margin-top: 60px; }
    </style>
</head>
<body>

<div class="paiement-container">
    <div class="paiement-card">
        <div class="method-icon">
            <i class="bi bi-wifi"></i>
        </div>
        <h2 style="font-family: 'Playfair Display', serif; text-align: center; color: #0D0D0D;">Wave</h2>
        <p style="text-align: center; color: #8A99AA;">Effectuez votre paiement en toute sécurité</p>
        
        <div class="montant">
            <?= number_format($commande['total'], 0, ',', ' ') ?> FCFA
        </div>

        <!-- Numéro de l'entreprise pour le dépôt -->
        <div class="numero-entreprise">
            <i class="bi bi-info-circle" style="color:#1A7A4A;"></i>
            <span style="color:#5A6B7A;font-size:0.85rem;">Effectuez le dépôt sur le numéro :</span>
            <div class="numero">+223 74 74 03 03</div>
            <span style="color:#8A99AA;font-size:0.7rem;">(Wave)</span>
        </div>

        <div class="info-box">
            <i class="bi bi-info-circle-fill"></i>
            <strong style="color:#5A6B7A;">Instructions :</strong>
            <p style="margin:5px 0 0 0;color:#5A6B7A;font-size:0.9rem;">
                Après avoir effectué le dépôt sur notre numéro Wave, veuillez remplir le formulaire ci-dessous avec les informations du déposant pour que nous puissions vérifier votre paiement.
            </p>
        </div>
        
        <?php if($error): ?>
            <div class="alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label" style="font-weight:600;">Nom du déposant <span style="color:#E74C3C;">*</span></label>
                <input type="text" name="nom_deposant" class="form-control" placeholder="Ex: Awa Doumbia" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label" style="font-weight:600;">Numéro de téléphone du déposant <span style="color:#E74C3C;">*</span></label>
                <input type="tel" name="telephone_paiement" class="form-control" placeholder="+223 74 74 03 03" required>
                <small class="text-muted" style="font-size:0.7rem;">Le numéro utilisé pour effectuer le dépôt Wave</small>
            </div>
            
            <div class="mb-3" style="background:#F5F0EB;border-radius:10px;padding:15px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                    <span style="color:#5A6B7A;">Montant à payer</span>
                    <span style="font-size:1.3rem;font-weight:700;color:#1A7A4A;"><?= number_format($commande['total'], 0, ',', ' ') ?> FCFA</span>
                </div>
            </div>
            
            <button type="submit" class="btn-payer" id="btnPayer">
                <i class="bi bi-check-circle"></i> Confirmer le paiement
            </button>
        </form>
        
        <div style="text-align:center;margin-top:15px;">
            <a href="../boutique/panier.php" style="color:#8A99AA;text-decoration:none;font-size:0.85rem;">
                <i class="bi bi-arrow-left"></i> Retour au panier
            </a>
        </div>
    </div>
</div>

<script>
document.querySelector('form').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnPayer');
    btn.innerHTML = '<i class="bi bi-spinner bi-spin"></i> Traitement en cours...';
    btn.disabled = true;
});
</script>

</body>
</html>