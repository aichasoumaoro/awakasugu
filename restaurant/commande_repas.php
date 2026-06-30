<?php
// ============================================
// COMMANDE REPAS - Restaurant Sofia
// ============================================

$titre_page = 'Commander - Restaurant Sofia';
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
    die("Erreur : " . $e->getMessage());
}

$plat_id = isset($_GET['plat_id']) ? (int)$_GET['plat_id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM plats WHERE id = ? AND est_visible = 1");
$stmt->execute([$plat_id]);
$plat = $stmt->fetch();

if (!$plat) {
    header('Location: menu.php');
    exit;
}

$client_id = $_SESSION['client_id'] ?? null;
$client_nom = '';
$client_telephone = '';
$client_email = '';

if ($client_id) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    if ($client) {
        $client_nom = $client['nom'] . ' ' . ($client['prenom'] ?? '');
        $client_telephone = $client['telephone'];
        $client_email = $client['email'];
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? $client_nom);
    $telephone = trim($_POST['telephone'] ?? $client_telephone);
    $email = trim($_POST['email'] ?? $client_email);
    $type_commande = $_POST['type_commande'] ?? 'sur_place';
    $adresse = trim($_POST['adresse'] ?? '');
    $quantite = max(1, (int)$_POST['quantite']);
    $mode_paiement = $_POST['mode_paiement'] ?? 'sur_place';
    $instructions = trim($_POST['instructions'] ?? '');
    
    if (empty($nom) || empty($telephone)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif ($type_commande == 'livraison' && empty($adresse)) {
        $error = 'Veuillez entrer une adresse de livraison.';
    } else {
        $numero_commande = 'REPAS-' . date('Ymd') . '-' . rand(1000, 9999);
        $total = $plat['prix'] * $quantite;
        
        $stmt = $pdo->prepare("
            INSERT INTO commandes_repas (numero_commande, client_id, nom_client, telephone, email, type_commande, adresse_livraison, mode_paiement, total, instructions, statut, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'recue', NOW())
        ");
        $stmt->execute([$numero_commande, $client_id, $nom, $telephone, $email, $type_commande, $adresse, $mode_paiement, $total, $instructions]);
        $commande_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("
            INSERT INTO details_commande_repas (commande_id, plat_id, nom_plat, quantite, prix_unitaire, sous_total)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$commande_id, $plat['id'], $plat['nom'], $quantite, $plat['prix'], $total]);
        
        header("Location: confirmation_repas.php?numero=$numero_commande");
        exit;
    }
}
?>

<style>
.commande-hero {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #2A1A0A 100%);
    padding: 40px 0 30px;
    text-align: center;
}
.commande-hero h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    color: #C8922A;
}
.commande-container {
    max-width: 800px;
    margin: -20px auto 60px;
    padding: 0 20px;
}
.plat-summary {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: 1px solid rgba(200,146,42,0.08);
}
.plat-summary img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 12px;
}
.plat-summary .info h3 {
    font-size: 1.2rem;
    margin: 0;
}
.plat-summary .info .prix {
    color: #C8922A;
    font-weight: 700;
    font-size: 1.2rem;
}
.form-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: 1px solid rgba(200,146,42,0.08);
}
.form-card .form-group {
    margin-bottom: 18px;
}
.form-card label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: #0D0D0D;
    margin-bottom: 5px;
}
.form-card label .required {
    color: #E74C3C;
}
.form-card .form-control,
.form-card .form-select {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid #E0E6ED;
    border-radius: 10px;
    font-size: 0.9rem;
    transition: all 0.3s;
}
.form-card .form-control:focus,
.form-card .form-select:focus {
    border-color: #C8922A;
    box-shadow: 0 0 0 3px rgba(200,146,42,0.08);
}
.btn-commander {
    width: 100%;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    border: none;
    padding: 14px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-commander:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
}
.alert-error {
    background: #FEF3F2;
    border-left: 4px solid #E74C3C;
    color: #721C24;
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
}
@media (max-width: 600px) {
    .plat-summary { flex-direction: column; text-align: center; }
    .form-card { padding: 20px; }
    .commande-hero h1 { font-size: 1.6rem; }
}
</style>

<div class="commande-hero">
    <div class="container">
        <h1>🍽️ Commander</h1>
    </div>
</div>

<div class="commande-container">
    <div class="plat-summary">
        <?php 
        $img = !empty($plat['image']) ? '../uploads/plats/' . $plat['image'] : 'https://placehold.co/100x100/F5F3F0/C8922A?text=' . urlencode($plat['nom']);
        ?>
        <img src="<?= $img ?>" alt="<?= htmlspecialchars($plat['nom']) ?>">
        <div class="info">
            <h3><?= htmlspecialchars($plat['nom']) ?></h3>
            <div class="prix"><?= number_format($plat['prix'], 0, ',', ' ') ?> FCFA</div>
        </div>
    </div>
    
    <div class="form-card">
        <?php if($error): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nom complet <span class="required">*</span></label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($client_nom) ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Téléphone <span class="required">*</span></label>
                        <input type="tel" name="telephone" class="form-control" value="<?= htmlspecialchars($client_telephone) ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($client_email) ?>" placeholder="votre@email.com">
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Type de commande <span class="required">*</span></label>
                        <select name="type_commande" class="form-select" id="typeCommande" required>
                            <option value="sur_place">🍽️ Sur place</option>
                            <option value="a_emporter">🛍️ À emporter</option>
                            <option value="livraison">🚚 Livraison à domicile</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Quantité</label>
                        <input type="number" name="quantite" class="form-control" value="1" min="1">
                    </div>
                </div>
            </div>
            
            <div class="form-group" id="adresseDiv" style="display: none;">
                <label>Adresse de livraison</label>
                <textarea name="adresse" class="form-control" rows="2" placeholder="Quartier, rue, point de repère..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Mode de paiement <span class="required">*</span></label>
                <select name="mode_paiement" class="form-select" required>
                    <option value="sur_place">💵 Paiement sur place</option>
                    <option value="orange_money">🟠 Orange Money</option>
                    <option value="wave">🌊 Wave</option>
                    <option value="moov_money">📱 Moov Money</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Instructions supplémentaires</label>
                <textarea name="instructions" class="form-control" rows="2" placeholder="Préférences, demandes spéciales..."></textarea>
            </div>
            
            <button type="submit" class="btn-commander">
                <i class="bi bi-check-circle"></i> Confirmer la commande
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('typeCommande').addEventListener('change', function() {
    document.getElementById('adresseDiv').style.display = this.value === 'livraison' ? 'block' : 'none';
});
</script>

<?php require_once '../includes/footer.php'; ?>