<?php
// ============================================
// MON PROFIL - Awa Ka Sugu
// ============================================

// ============================================
// SESSION PUBLIQUE SÉPARÉE
// ============================================
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// VÉRIFICATION MAINTENANCE
// ============================================
require_once '../includes/maintenance_check.php';

if (!isset($_SESSION['client_id'])) {
    header('Location: connexion.php');
    exit;
}

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

$client_id = $_SESSION['client_id'];

// Récupérer les infos du client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $quartier = trim($_POST['quartier'] ?? '');
    $commune = trim($_POST['commune'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($nom) || empty($prenom) || empty($telephone)) {
        $error = 'Les champs nom, prénom et téléphone sont obligatoires.';
    } else {
        // Vérifier si l'email existe déjà pour un autre client
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
            $stmt->execute([$email, $client_id]);
            if ($stmt->fetch()) {
                $error = 'Cet email est déjà utilisé par un autre compte.';
            }
        }
        
        if (empty($error)) {
            // Mettre à jour les informations
            if (!empty($password) && strlen($password) >= 6) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE clients SET 
                        nom = ?, 
                        prenom = ?, 
                        telephone = ?, 
                        email = ?, 
                        adresse_complete = ?, 
                        quartier = ?, 
                        commune = ?, 
                        password = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nom, $prenom, $telephone, $email, $adresse, $quartier, $commune, $hashed_password, $client_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE clients SET 
                        nom = ?, 
                        prenom = ?, 
                        telephone = ?, 
                        email = ?, 
                        adresse_complete = ?, 
                        quartier = ?, 
                        commune = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nom, $prenom, $telephone, $email, $adresse, $quartier, $commune, $client_id]);
            }
            
            $success = 'Votre profil a été mis à jour avec succès !';
            
            // Mettre à jour la session
            $_SESSION['client_nom'] = $nom . ' ' . $prenom;
            
            // Recharger les données
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch();
        }
    }
}

$titre_page = 'Mon profil';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
.compte-container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
.compte-header {
    background: linear-gradient(135deg, #0D0D0D, #1A1A1A);
    border-radius: 20px;
    padding: 35px;
    margin-bottom: 30px;
    color: white;
    border: 1px solid rgba(200,146,42,0.15);
    position: relative;
    overflow: hidden;
}
.compte-header::after {
    content: '✦';
    position: absolute;
    right: -20px;
    top: -20px;
    font-size: 80px;
    color: rgba(200,146,42,0.05);
}
.compte-header h1 { 
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem; 
    color: #C8922A; 
    margin-bottom: 6px;
}
.compte-header p {
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
}
.compte-grid {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 30px;
}
.compte-sidebar {
    background: white;
    border-radius: 16px;
    border: 1px solid #F0EDEA;
    overflow: hidden;
    align-self: start;
}
.compte-sidebar .menu-item {
    padding: 15px 20px;
    border-bottom: 1px solid #F0EDEA;
    transition: all 0.3s;
}
.compte-sidebar .menu-item a {
    color: #0D0D0D;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.85rem;
}
.compte-sidebar .menu-item i {
    color: #C8922A;
    width: 24px;
    font-size: 1.1rem;
}
.compte-sidebar .menu-item:hover {
    background: #FEFBF5;
}
.compte-sidebar .menu-item.active {
    background: #FEFBF5;
    border-left: 3px solid #C8922A;
}
.compte-sidebar .menu-item.logout a {
    color: #E74C3C;
}
.compte-sidebar .menu-item.logout a i {
    color: #E74C3C;
}
.compte-content {
    background: white;
    border-radius: 16px;
    padding: 35px;
    border: 1px solid #F0EDEA;
}
.compte-content .section-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem;
    font-weight: 600;
    color: #0D0D0D;
    margin-bottom: 25px;
}
.compte-content .section-title i {
    color: #C8922A;
    margin-right: 10px;
}
.alert-error {
    background: #FEF3F2;
    border-left: 4px solid #E74C3C;
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 0.85rem;
    color: #721C24;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #D4EDDA;
    border-left: 4px solid #27AE60;
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 0.85rem;
    color: #0A3622;
    display: flex;
    align-items: center;
    gap: 10px;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: #0D0D0D;
    margin-bottom: 6px;
}
.form-group label i {
    color: #C8922A;
    margin-right: 6px;
}
.form-group .required {
    color: #E74C3C;
}
.form-control {
    width: 100%;
    padding: 12px 16px;
    font-family: 'Jost', sans-serif;
    font-size: 0.9rem;
    border: 1.5px solid #E8E8E8;
    border-radius: 12px;
    transition: all 0.3s;
    background: #FAFAFA;
}
.form-control:focus {
    outline: none;
    border-color: #C8922A;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(200,146,42,0.08);
}
.form-control::placeholder {
    color: #B0B0B0;
}
.btn-sauvegarder {
    background: linear-gradient(135deg, #C8922A, #E2B96A);
    color: white;
    border: none;
    padding: 14px 35px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.85rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}
.btn-sauvegarder:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
}
.info-profil {
    background: #FEFBF5;
    border-radius: 12px;
    padding: 15px 20px;
    margin-top: 20px;
    border: 1px solid rgba(200,146,42,0.08);
    font-size: 0.85rem;
}
.info-profil i {
    color: #C8922A;
    margin-right: 8px;
}
@media (max-width: 768px) { 
    .compte-grid { grid-template-columns: 1fr; gap: 20px; }
    .compte-header { padding: 25px 20px; }
    .compte-content { padding: 20px; }
    .form-row { grid-template-columns: 1fr; gap: 0; }
}
</style>

<div class="compte-container">
    <div class="compte-header">
        <h1>👤 Mon profil</h1>
        <p>Modifiez vos informations personnelles</p>
    </div>
    
    <div class="compte-grid">
        <aside class="compte-sidebar">
            <div class="menu-item">
                <a href="mon_compte.php"><i class="bi bi-grid"></i> Tableau de bord</a>
            </div>
            <div class="menu-item">
                <a href="mes_commandes.php"><i class="bi bi-receipt"></i> Mes commandes</a>
            </div>
            <div class="menu-item">
                <a href="ma_wishlist.php"><i class="bi bi-heart"></i> Ma wishlist</a>
            </div>
            <div class="menu-item">
                <a href="mes_factures.php"><i class="bi bi-file-pdf"></i> Mes factures</a>
            </div>
            <div class="menu-item">
                <a href="mes_points.php"><i class="bi bi-star"></i> Points fidélité</a>
            </div>
            <div class="menu-item active">
                <a href="mon_profil.php"><i class="bi bi-person"></i> Mon profil</a>
            </div>
            <div class="menu-item logout">
                <a href="deconnexion.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            </div>
        </aside>
        
        <main class="compte-content">
            <h2 class="section-title"><i class="bi bi-person-gear"></i> Modifier mes informations</h2>
            
            <?php if($error): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="bi bi-person"></i> Nom <span class="required">*</span></label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($client['nom'] ?? '') ?>" placeholder="Votre nom" required>
                    </div>
                    <div class="form-group">
                        <label><i class="bi bi-person-badge"></i> Prénom <span class="required">*</span></label>
                        <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($client['prenom'] ?? '') ?>" placeholder="Votre prénom" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="bi bi-phone"></i> Téléphone <span class="required">*</span></label>
                        <input type="tel" name="telephone" class="form-control" value="<?= htmlspecialchars($client['telephone'] ?? '') ?>" placeholder="77 00 00 00" required>
                    </div>
                    <div class="form-group">
                        <label><i class="bi bi-envelope"></i> Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($client['email'] ?? '') ?>" placeholder="votre@email.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="bi bi-geo-alt"></i> Adresse complète</label>
                    <input type="text" name="adresse" class="form-control" value="<?= htmlspecialchars($client['adresse_complete'] ?? '') ?>" placeholder="Rue, porte, bâtiment...">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="bi bi-building"></i> Quartier</label>
                        <input type="text" name="quartier" class="form-control" value="<?= htmlspecialchars($client['quartier'] ?? '') ?>" placeholder="Ex: Badalabougou">
                    </div>
                    <div class="form-group">
                        <label><i class="bi bi-pin-map"></i> Commune</label>
                        <input type="text" name="commune" class="form-control" value="<?= htmlspecialchars($client['commune'] ?? '') ?>" placeholder="Ex: Commune I">
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:20px;">
                    <label><i class="bi bi-lock"></i> Nouveau mot de passe</label>
                    <input type="password" name="password" class="form-control" placeholder="Laissez vide pour ne pas changer">
                    <small style="color:#999;font-size:0.7rem;">Minimum 6 caractères</small>
                </div>
                
                <button type="submit" class="btn-sauvegarder">
                    <i class="bi bi-save"></i> Sauvegarder les modifications
                </button>
            </form>
            
            <div class="info-profil">
                <i class="bi bi-info-circle"></i>
                <strong>Information :</strong> Votre email vous permet de recevoir les confirmations de commande.
            </div>
        </main>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>