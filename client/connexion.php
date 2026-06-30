<?php
// ============================================
// CONNEXION CLIENT - Awa Ka Sugu
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

// ============================================
// INCLURE LES FONCTIONS DU PANIER
// ============================================
require_once '../includes/panier_fonctions.php';

// Si déjà connecté en tant que client, rediriger
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

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        $client = $stmt->fetch();
        
        if ($client) {
            if (isset($client['mot_de_passe']) && password_verify($password, $client['mot_de_passe'])) {
                $_SESSION['client_id'] = $client['id'];
                $_SESSION['client_nom'] = ($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? 'Client');
                $_SESSION['client_email'] = $client['email'];
                $_SESSION['client_telephone'] = $client['telephone'] ?? '';
                
                // ✅ CHARGER LE PANIER DEPUIS LA BDD
                $_SESSION['panier'] = chargerPanierClient($client['id'], $pdo);
                
                // Si un panier temporaire existait, le sauvegarder en BDD
                if (!empty($_SESSION['panier_temp'])) {
                    sauvegarderPanierClient($client['id'], $_SESSION['panier_temp'], $pdo);
                    $_SESSION['panier'] = $_SESSION['panier_temp'];
                    unset($_SESSION['panier_temp']);
                }
                
                $redirect = $_GET['redirect'] ?? 'mon_compte.php';
                header("Location: $redirect");
                exit;
            } else {
                $error = 'Email ou mot de passe incorrect.';
            }
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}

$titre_page = 'Connexion';
$meta_desc = 'Connectez-vous à votre compte Awa Ka Sugu.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
/* ========== PAGE CONNEXION ========== */
.connexion-container {
    max-width: 480px;
    margin: 60px auto;
    padding: 0 20px;
}
.connexion-card {
    background: #fff;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.06);
    border: 1px solid rgba(200,146,42,0.08);
}
.connexion-card h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    color: #0D0D0D;
    text-align: center;
    margin-bottom: 8px;
}
.connexion-card .subtitle {
    text-align: center;
    color: #8A99AA;
    font-size: 0.9rem;
    margin-bottom: 25px;
}
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    font-size: 0.8rem;
    color: #0D0D0D;
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
    font-size: 0.9rem;
    transition: all 0.3s;
}
.form-control:focus {
    outline: none;
    border-color: #C8922A;
    box-shadow: 0 0 0 3px rgba(200,146,42,0.08);
}
.btn-connexion {
    width: 100%;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    padding: 15px;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 10px;
}
.btn-connexion:hover {
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
    display: flex;
    align-items: center;
    gap: 10px;
}
.inscription-link {
    text-align: center;
    margin-top: 20px;
    font-size: 0.9rem;
    color: #8A99AA;
}
.inscription-link a {
    color: #C8922A;
    text-decoration: none;
    font-weight: 600;
}
.inscription-link a:hover {
    text-decoration: underline;
}
@media (max-width: 600px) {
    .connexion-card { padding: 25px 20px; }
}
</style>

<div class="connexion-container">
    <div class="connexion-card">
        <h1>🔑 Connexion</h1>
        <p class="subtitle">Connectez-vous à votre compte Awa Ka Sugu</p>
        
        <?php if($error): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" placeholder="votre@email.com" required>
            </div>
            
            <div class="form-group">
                <label>Mot de passe <span class="required">*</span></label>
                <input type="password" name="password" class="form-control" placeholder="Votre mot de passe" required>
            </div>
            
            <button type="submit" class="btn-connexion">
                <i class="bi bi-box-arrow-in-right"></i> Se connecter
            </button>
        </form>
        
        <div class="inscription-link">
            Pas encore de compte ? <a href="inscription.php">Créer un compte</a>
        </div>
        
        <div style="margin-top:15px;text-align:center;font-size:0.8rem;">
            <a href="mot_de_passe_oublie.php" style="color:#8A99AA;text-decoration:none;">Mot de passe oublié ?</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>