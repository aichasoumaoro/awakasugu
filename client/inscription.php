<?php
// ============================================
// PAGE D'INSCRIPTION CLIENT - Awa Ka Sugu
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

// Si déjà connecté, rediriger
if (isset($_SESSION['client_id'])) {
    header('Location: mon_compte.php');
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
    die("Erreur : " . $e->getMessage());
}

$error = '';
$success = '';
$email_prefill = isset($_GET['email']) ? htmlspecialchars(trim($_GET['email'])) : '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $quartier = trim($_POST['quartier'] ?? '');
    $commune = trim($_POST['commune'] ?? '');
    $adresse_complete = trim($_POST['adresse_complete'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($nom) || empty($prenom) || empty($email) || empty($telephone) || empty($mot_de_passe)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer un email valide.';
    } elseif (strlen($mot_de_passe) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($mot_de_passe !== $password_confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE telephone = ?");
            $stmt->execute([$telephone]);
            if ($stmt->fetch()) {
                $error = 'Ce numéro de téléphone est déjà utilisé.';
            } else {
                $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO clients (
                        nom, prenom, email, telephone, 
                        quartier, commune, adresse_complete, 
                        mot_de_passe, nb_commandes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
                ");
                if ($stmt->execute([$nom, $prenom, $email, $telephone, $quartier, $commune, $adresse_complete, $hashed_password])) {
                    $success = 'Votre compte a été créé avec succès !';
                    // Redirection après 2 secondes
                    header("refresh:2;url=connexion.php");
                } else {
                    $error = 'Une erreur est survenue. Veuillez réessayer.';
                }
            }
        }
    }
}

$titre_page = 'Inscription';
$meta_desc  = 'Créez votre compte Awa Ka Sugu pour passer commande facilement.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
/* ========== STYLES PAGE INSCRIPTION MODERNISÉE ========== */
.inscription-page {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    background: linear-gradient(135deg, #FBF7F2 0%, #F7EDD8 100%);
    position: relative;
}
.inscription-page::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y="90" font-size="80" opacity="0.03">✦</text></svg>') repeat;
    background-size: 80px 80px;
    pointer-events: none;
}
.inscription-card {
    max-width: 680px;
    width: 100%;
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 25px 60px -15px rgba(0,0,0,0.2);
    overflow: hidden;
    border: 1px solid rgba(200,146,42,0.15);
    position: relative;
    z-index: 1;
}
.inscription-header {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 100%);
    padding: 35px 35px 25px;
    text-align: center;
    border-bottom: 3px solid #C8922A;
    position: relative;
    overflow: hidden;
}
.inscription-header::after {
    content: '✦';
    position: absolute;
    right: -20px;
    top: -20px;
    font-size: 80px;
    color: rgba(200,146,42,0.05);
}
.inscription-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    font-weight: 700;
    color: #C8922A;
    margin-bottom: 6px;
}
.inscription-header p {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.4);
    letter-spacing: 1px;
}
.inscription-body {
    padding: 35px 35px 30px;
}
.alert-error {
    background: #FEF3F2;
    border-left: 4px solid #E74C3C;
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 22px;
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
    margin-bottom: 22px;
    font-size: 0.85rem;
    color: #0A3622;
    display: flex;
    align-items: center;
    gap: 10px;
    text-align: center;
    flex-direction: column;
}
.alert-success .btn-connect {
    display: inline-block;
    background: #C8922A;
    color: #fff;
    padding: 10px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    margin-top: 10px;
    transition: all 0.3s;
}
.alert-success .btn-connect:hover {
    background: #9A6E1A;
    transform: translateY(-2px);
}
.newsletter-badge {
    background: linear-gradient(135deg, rgba(200,146,42,0.08), rgba(200,146,42,0.03));
    border: 1px solid rgba(200,146,42,0.15);
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.85rem;
    color: #0D0D0D;
}
.newsletter-badge i {
    color: #C8922A;
    font-size: 1.4rem;
}
.newsletter-badge strong {
    color: #C8922A;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    font-size: 0.7rem;
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
    font-size: 0.8rem;
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
.btn-inscrire {
    width: 100%;
    background: linear-gradient(135deg, #C8922A, #E2B96A);
    color: #fff;
    font-family: 'Jost', sans-serif;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 15px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.btn-inscrire:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(200,146,42,0.35);
}
.info-text {
    font-size: 0.75rem;
    color: #999;
    text-align: center;
    margin-top: 20px;
    padding: 15px;
    background: #FAFAFA;
    border-radius: 12px;
}
.info-text i {
    color: #C8922A;
    margin-right: 6px;
}
.inscription-footer {
    text-align: center;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #F0EDEA;
}
.inscription-footer a {
    color: #C8922A;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    transition: color 0.3s;
}
.inscription-footer a:hover {
    color: #9A6E1A;
    text-decoration: underline;
}
@media (max-width: 550px) {
    .form-row { grid-template-columns: 1fr; gap: 0; }
    .inscription-body { padding: 20px; }
    .inscription-header { padding: 25px 20px; }
    .inscription-header h1 { font-size: 1.5rem; }
    .newsletter-badge { flex-direction: column; text-align: center; }
}
</style>

<div class="inscription-page">
    <div class="inscription-card">
        <div class="inscription-header">
            <h1>✨ Créer un compte</h1>
            <p>Rejoignez la communauté Awa Ka Sugu</p>
        </div>
        <div class="inscription-body">
            
            <?php if($error): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert-success">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="bi bi-check-circle-fill" style="font-size:1.2rem;"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                    <a href="connexion.php" class="btn-connect">
                        <i class="bi bi-box-arrow-in-right"></i> Se connecter
                    </a>
                </div>
            <?php else: ?>
            
            <?php if($email_prefill): ?>
            <div class="newsletter-badge">
                <i class="bi bi-envelope-paper-fill"></i>
                <div>
                    Vous venez de vous inscrire à notre newsletter !<br>
                    <small style="color:#999;">Complétez votre inscription avec l'email : <strong><?= $email_prefill ?></strong></small>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="bi bi-person"></i> Nom <span class="required">*</span></label>
                        <input type="text" name="nom" class="form-control" placeholder="Votre nom" required>
                    </div>
                    <div class="form-group">
                        <label><i class="bi bi-person-badge"></i> Prénom <span class="required">*</span></label>
                        <input type="text" name="prenom" class="form-control" placeholder="Votre prénom" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="bi bi-envelope"></i> Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="exemple@email.com" value="<?= $email_prefill ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="bi bi-phone"></i> Téléphone <span class="required">*</span></label>
                        <input type="tel" name="telephone" class="form-control" placeholder="77 00 00 00" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="bi bi-geo-alt"></i> Quartier</label>
                    <input type="text" name="quartier" class="form-control" placeholder="Ex: Badalabougou, Hippodrome...">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="bi bi-building"></i> Commune</label>
                        <input type="text" name="commune" class="form-control" placeholder="Ex: Commune I, II, III...">
                    </div>
                    <div class="form-group">
                        <label><i class="bi bi-pin-map"></i> Adresse complète</label>
                        <input type="text" name="adresse_complete" class="form-control" placeholder="Rue, porte...">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="bi bi-lock"></i> Mot de passe <span class="required">*</span></label>
                        <input type="password" name="mot_de_passe" class="form-control" placeholder="•••••••• (min. 6)" required>
                    </div>
                    <div class="form-group">
                        <label><i class="bi bi-shield-lock"></i> Confirmer <span class="required">*</span></label>
                        <input type="password" name="password_confirm" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-inscrire">
                    <i class="bi bi-person-plus"></i> Créer mon compte
                </button>
            </form>
            
            <div class="info-text">
                <i class="bi bi-info-circle"></i>
                En créant un compte, vous pourrez suivre vos commandes<br>
                et bénéficier d'offres exclusives.
            </div>
            
            <!-- ✅ LIEN CORRIGÉ VERS CONNEXION.PHP -->
            <div class="inscription-footer">
                Déjà un compte ? <a href="connexion.php">Connectez-vous</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>