<?php
// ============================================
// PAGE D'INSCRIPTION CLIENT - Awa Ka Sugu
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

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
/* ============================================
   PAGE INSCRIPTION - VERSION ÉCLAIRCIE & AGRANDIE
   ============================================ */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');

@keyframes pageEnter {
    0% { opacity: 0; transform: translateY(30px) scale(0.98); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes fadeInUp {
    0% { opacity: 0; transform: translateY(20px); }
    100% { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
    0% { opacity: 0; }
    100% { opacity: 1; }
}

/* ===== PAGE FOND CLAIR ===== */
.signup-light-page {
    min-height: calc(100vh - 140px);
    background: linear-gradient(135deg, #FAF7F2 0%, #F5EDE0 50%, #FBF8F3 100%);
    background-image: 
        radial-gradient(circle at 20% 20%, rgba(200,146,42,0.06) 0%, transparent 40%),
        radial-gradient(circle at 80% 80%, rgba(232,181,90,0.05) 0%, transparent 40%),
        linear-gradient(rgba(200,146,42,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(200,146,42,0.04) 1px, transparent 1px);
    background-size: 100% 100%, 100% 100%, 40px 40px, 40px 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 50px 20px;
    font-family: 'Poppins', sans-serif;
    position: relative;
    overflow: hidden;
}

/* ===== WRAPPER AGRANDI ===== */
.signup-card {
    position: relative;
    width: 100%;
    max-width: 1000px;  /* ← AGRANDI (avant 680px) */
    background: #ffffff;
    border-radius: 24px;
    border: 1.5px solid rgba(200,146,42,0.25);
    box-shadow: 
        0 10px 40px rgba(200,146,42,0.12),
        0 2px 8px rgba(0,0,0,0.04);
    overflow: hidden;
    animation: pageEnter 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 2;
}

/* ===== COINS DÉCORATIFS ===== */
.neon-corner-light {
    position: absolute;
    width: 24px;
    height: 24px;
    border: 2px solid #C8922A;
    z-index: 4;
    pointer-events: none;
}
.neon-corner-light.tl { top: 10px; left: 10px; border-right: none; border-bottom: none; border-radius: 12px 0 0 0; }
.neon-corner-light.tr { top: 10px; right: 10px; border-left: none; border-bottom: none; border-radius: 0 12px 0 0; }
.neon-corner-light.bl { bottom: 10px; left: 10px; border-right: none; border-top: none; border-radius: 0 0 0 12px; }
.neon-corner-light.br { bottom: 10px; right: 10px; border-left: none; border-top: none; border-radius: 0 0 12px 0; }

/* ===== HEADER ===== */
.signup-header {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1510 100%);
    padding: 35px 45px 30px;
    text-align: center;
    position: relative;
    overflow: hidden;
    border-bottom: 2px solid #C8922A;
}
.signup-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at 50% 50%, rgba(200,146,42,0.15) 0%, transparent 50%);
    animation: shine 8s ease-in-out infinite;
}
@keyframes shine {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(20px, -20px); }
}
.signup-header .brand {
    font-family: 'Playfair Display', serif;
    font-size: 1rem;
    font-weight: 700;
    color: #C8922A;
    letter-spacing: 5px;
    margin-bottom: 12px;
    position: relative;
    z-index: 2;
}
.signup-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
    letter-spacing: 2px;
    position: relative;
    z-index: 2;
}
.signup-header h1 span {
    color: #C8922A;
    text-shadow: 0 0 20px rgba(200,146,42,0.6);
}
.signup-header p {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.5);
    letter-spacing: 1px;
    position: relative;
    z-index: 2;
}

/* ===== BODY AGRANDI ===== */
.signup-body {
    padding: 45px 55px 40px;  /* ← AGRANDI */
    background: #ffffff;
    animation: fadeIn 0.6s ease 0.4s both;
}

/* ===== ALERTES ===== */
.light-alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 0.85rem;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    line-height: 1.5;
}
.light-alert.error {
    background: #FEF3F2;
    border-left: 4px solid #E74C3C;
    color: #721C24;
}
.light-alert.error i { color: #E74C3C; margin-top: 2px; }
.light-alert.success {
    background: #E8F8EE;
    border-left: 4px solid #27AE60;
    color: #0A3622;
    flex-direction: column;
    text-align: center;
    align-items: center;
}
.light-alert.success i { color: #27AE60; font-size: 1.2rem; }
.light-alert.success .btn-go-light {
    display: inline-block;
    margin-top: 10px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #fff;
    padding: 10px 28px;
    border-radius: 25px;
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 600;
    transition: 0.3s;
    box-shadow: 0 4px 15px rgba(200,146,42,0.3);
}
.light-alert.success .btn-go-light:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(200,146,42,0.5);
}

/* ===== NEWSLETTER ===== */
.newsletter-light {
    background: linear-gradient(135deg, rgba(200,146,42,0.08), rgba(200,146,42,0.03));
    border: 1px solid rgba(200,146,42,0.25);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 0.85rem;
    color: #1A2C3E;
}
.newsletter-light i {
    color: #C8922A;
    font-size: 1.5rem;
}
.newsletter-light strong { color: #C8922A; }

/* ===== FORMULAIRE ===== */
.form-row-light {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.field-light {
    margin-bottom: 20px;
}
.field-light label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: #1A2C3E;
    margin-bottom: 8px;
}
.field-light label i {
    color: #C8922A;
    margin-right: 6px;
    font-size: 0.85rem;
}
.field-light .required { color: #E74C3C; }
.field-light .input-light {
    position: relative;
}
.field-light .input-light input {
    width: 100%;
    padding: 15px 18px 15px 48px;  /* ← AGRANDI */
    border: 1.5px solid #E8ECF0;
    border-radius: 12px;
    background: #FAFBFC;
    font-family: 'Poppins', sans-serif;
    font-size: 0.92rem;  /* ← AGRANDI */
    color: #1A2C3E;
    transition: all 0.3s;
}
.field-light .input-light input:focus {
    outline: none;
    border-color: #C8922A;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(200,146,42,0.08);
}
.field-light .input-light input::placeholder {
    color: #B0B8C4;
    font-size: 0.88rem;
}
.field-light .input-light .icon-light {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: #C8922A;
    font-size: 1rem;
    pointer-events: none;
    transition: 0.3s;
}
.field-light .input-light input:focus ~ .icon-light {
    color: #9A6E1A;
    transform: translateY(-50%) scale(1.1);
}

/* ===== BOUTON SUBMIT ===== */
.btn-submit-light {
    width: 100%;
    padding: 17px;  /* ← AGRANDI */
    margin-top: 15px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    border: none;
    border-radius: 12px;
    color: #fff;
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 0.95rem;  /* ← AGRANDI */
    letter-spacing: 1.5px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.4s;
    box-shadow: 0 6px 20px rgba(200,146,42,0.3);
    position: relative;
    overflow: hidden;
}
.btn-submit-light::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.6s;
}
.btn-submit-light:hover::before { left: 100%; }
.btn-submit-light:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(200,146,42,0.45);
}

/* ===== INFO TEXT ===== */
.info-light {
    text-align: center;
    margin-top: 22px;
    padding: 16px;
    background: #FAFBFC;
    border-radius: 12px;
    font-size: 0.78rem;
    color: #6B7A8D;
    line-height: 1.6;
    border: 1px dashed rgba(200,146,42,0.25);
}
.info-light i {
    color: #C8922A;
    margin-right: 5px;
}

/* ===== FOOTER LIEN ===== */
.footer-link-light {
    text-align: center;
    margin-top: 24px;
    padding-top: 22px;
    border-top: 1px solid #F0EDEA;
    font-size: 0.88rem;
    color: #6B7A8D;
}
.footer-link-light a {
    color: #C8922A;
    text-decoration: none;
    font-weight: 700;
    transition: 0.3s;
    position: relative;
}
.footer-link-light a::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 1px;
    background: #C8922A;
    transition: width 0.3s;
}
.footer-link-light a:hover::after { width: 100%; }

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) {
    .signup-card { max-width: 100%; }
    .signup-body { padding: 35px 30px 30px; }
    .signup-header { padding: 30px 30px 25px; }
}
@media (max-width: 650px) {
    .signup-body { padding: 25px 22px; }
    .signup-header { padding: 25px 22px 20px; }
    .signup-header h1 { font-size: 1.5rem; }
    .form-row-light { grid-template-columns: 1fr; gap: 0; }
}
</style>

<div class="signup-light-page">
    
    <div class="signup-card">
        
        <div class="neon-corner-light tl"></div>
        <div class="neon-corner-light tr"></div>
        <div class="neon-corner-light bl"></div>
        <div class="neon-corner-light br"></div>
        
        <!-- ===== HEADER ===== -->
        <div class="signup-header">
            <div class="brand">✦ AWA KA SUGU ✦</div>
            <h1>Créer un <span>compte</span></h1>
            <p>Rejoignez notre communauté</p>
        </div>
        
        <!-- ===== BODY ===== -->
        <div class="signup-body">
            
            <?php if($error): ?>
                <div class="light-alert error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="light-alert success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                    <a href="connexion.php" class="btn-go-light">
                        <i class="bi bi-box-arrow-in-right"></i> Se connecter
                    </a>
                </div>
            <?php else: ?>
            
            <?php if($email_prefill): ?>
            <div class="newsletter-light">
                <i class="bi bi-envelope-paper-fill"></i>
                <div>
                    Vous venez de vous inscrire à notre newsletter !<br>
                    <small style="color:#8A99AA;">Email pré-rempli : <strong><?= $email_prefill ?></strong></small>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                
                <div class="form-row-light">
                    <div class="field-light">
                        <label>Nom <span class="required">*</span></label>
                        <div class="input-light">
                            <input type="text" name="nom" placeholder="Votre nom" required>
                            <i class="bi bi-person icon-light"></i>
                        </div>
                    </div>
                    <div class="field-light">
                        <label>Prénom <span class="required">*</span></label>
                        <div class="input-light">
                            <input type="text" name="prenom" placeholder="Votre prénom" required>
                            <i class="bi bi-person icon-light"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-row-light">
                    <div class="field-light">
                        <label>Email <span class="required">*</span></label>
                        <div class="input-light">
                            <input type="email" name="email" placeholder="exemple@email.com" value="<?= $email_prefill ?>" required>
                            <i class="bi bi-envelope icon-light"></i>
                        </div>
                    </div>
                    <div class="field-light">
                        <label>Téléphone <span class="required">*</span></label>
                        <div class="input-light">
                            <input type="tel" name="telephone" placeholder="77 00 00 00" required>
                            <i class="bi bi-phone icon-light"></i>
                        </div>
                    </div>
                </div>
                
                <div class="field-light">
                    <label>Quartier</label>
                    <div class="input-light">
                        <input type="text" name="quartier" placeholder="Ex: Badalabougou, Hippodrome...">
                        <i class="bi bi-geo-alt icon-light"></i>
                    </div>
                </div>
                
                <div class="form-row-light">
                    <div class="field-light">
                        <label>Commune</label>
                        <div class="input-light">
                            <input type="text" name="commune" placeholder="Ex: Commune I, II...">
                            <i class="bi bi-building icon-light"></i>
                        </div>
                    </div>
                    <div class="field-light">
                        <label>Adresse complète</label>
                        <div class="input-light">
                            <input type="text" name="adresse_complete" placeholder="Rue, porte...">
                            <i class="bi bi-pin-map icon-light"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-row-light">
                    <div class="field-light">
                        <label>Mot de passe <span class="required">*</span></label>
                        <div class="input-light">
                            <input type="password" name="mot_de_passe" placeholder="min. 6 caractères" required>
                            <i class="bi bi-lock icon-light"></i>
                        </div>
                    </div>
                    <div class="field-light">
                        <label>Confirmer <span class="required">*</span></label>
                        <div class="input-light">
                            <input type="password" name="password_confirm" placeholder="••••••••" required>
                            <i class="bi bi-shield-lock icon-light"></i>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit-light">
                    <i class="bi bi-person-plus"></i> Créer mon compte
                </button>
                
            </form>
            
            <div class="info-light">
                <i class="bi bi-info-circle"></i>
                En créant un compte, vous pourrez suivre vos commandes et bénéficier d'offres exclusives.
            </div>
            
            <div class="footer-link-light">
                Déjà un compte ? <a href="connexion.php">Connectez-vous</a>
            </div>
            
            <?php endif; ?>
            
        </div>
        
    </div>
    
</div>

<?php require_once '../includes/footer.php'; ?>