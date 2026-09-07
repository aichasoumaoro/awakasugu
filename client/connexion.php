<?php
// ============================================
// CONNEXION CLIENT - Awa Ka Sugu
// ============================================
// Version sécurisée avec blocage après 3 tentatives
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
// INCLURE LES FONCTIONS
// ============================================
require_once '../includes/panier_fonctions.php';
require_once '../includes/functions_securite.php';

// Si déjà connecté en tant que client, rediriger
if (isset($_SESSION['client_id']) && isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true) {
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
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données.");
}

// ============================================
// NETTOYAGE AUTOMATIQUE
// ============================================
nettoyer_tentatives_anciennes($pdo);
nettoyer_blocages_expires($pdo);

// ============================================
// VÉRIFICATION DU BLOCAGE
// ============================================
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$email = '';
$error = '';
$formulaire_bloque = false;
$message_blocage = '';

// Vérifier si l'IP est bloquée
if (is_ip_bloquee($pdo, $ip)) {
    $formulaire_bloque = true;
    $message_blocage = '⛔ Votre adresse IP a été bloquée pour 15 minutes suite à trop de tentatives échouées.';
}

// ============================================
// TRAITEMENT DU FORMULAIRE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$formulaire_bloque) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        // Vérifier si le compte est bloqué
        $blocage_compte = is_compte_bloque($pdo, $email);
        
        if ($blocage_compte['bloque']) {
            $fin = new DateTime($blocage_compte['fin_blocage']);
            $now = new DateTime();
            $minutes_restantes = $now->diff($fin)->i + 1;
            $formulaire_bloque = true;
            $message_blocage = '⛔ Ce compte est bloqué pour ' . $minutes_restantes . ' minutes. Réessayez plus tard.';
            enregistrer_tentative($pdo, $email, false);
        } else {
            // Rechercher le client
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ?");
            $stmt->execute([$email]);
            $client = $stmt->fetch();
            
            if ($client && password_verify($password, $client['mot_de_passe'])) {
                // ============================================
                // ✅ CONNEXION RÉUSSIE
                // ============================================
                
                enregistrer_tentative($pdo, $email, true);
                
                // Supprimer les anciennes tentatives échouées
                $stmt = $pdo->prepare("DELETE FROM tentatives_connexion WHERE email = ? AND success = 0");
                $stmt->execute([$email]);
                
                debloquer_compte($pdo, $email);
                
                // Démarrer la session client
                $_SESSION['client_id'] = $client['id'];
                $_SESSION['client_nom'] = ($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? 'Client');
                $_SESSION['client_email'] = $client['email'];
                $_SESSION['client_telephone'] = $client['telephone'] ?? '';
                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_created'] = time();
                
                // Charger le panier depuis la BDD
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
                // ============================================
                // ❌ TENTATIVE ÉCHOUÉE
                // ============================================
                
                enregistrer_tentative($pdo, $email, false);
                
                // Compter les tentatives échouées pour ce compte
                $nb_tentatives = compter_tentatives_echouees($pdo, $email);
                $tentatives_restantes = 3 - $nb_tentatives;
                
                if ($nb_tentatives >= 3) {
                    // 🔒 Blocage du compte
                    bloquer_compte($pdo, $email, '3 tentatives échouées sur le compte client ' . $email);
                    
                    // 📧 Alerte au Super Admin (si c'est un compte admin)
                    if (function_exists('email_admin_existe') && email_admin_existe($pdo, $email)) {
                        $sujet = "🔒 ALERTE - Compte client bloqué";
                        $message = "
                            <p><strong>Un compte client a été bloqué suite à 3 tentatives de connexion échouées.</strong></p>
                            <p><strong>Compte visé :</strong> " . htmlspecialchars($email) . "</p>
                            <p><strong>Adresse IP :</strong> " . $ip . "</p>
                            <p><strong>Date/Heure :</strong> " . date('d/m/Y H:i:s') . "</p>
                        ";
                        envoyer_alerte_securite($pdo, $sujet, $message);
                    }
                    
                    $formulaire_bloque = true;
                    $message_blocage = '⛔ Compte bloqué pour 30 minutes suite à 3 tentatives échouées.';
                } else {
                    // Vérifier aussi les tentatives depuis la même IP
                    $nb_tentatives_ip = compter_tentatives_ip_echouees($pdo, $ip);
                    
                    if ($nb_tentatives_ip >= 3) {
                        // 🔒 Blocage IP
                        bloquer_ip($pdo, $ip, '3 tentatives échouées depuis IP ' . $ip);
                        
                        // 📧 Alerte au Super Admin
                        $sujet = "🔒 ALERTE - IP bloquée";
                        $message = "
                            <p><strong>Une adresse IP a été bloquée suite à 3 tentatives de connexion échouées.</strong></p>
                            <p><strong>Adresse IP :</strong> " . $ip . "</p>
                            <p><strong>Email tenté :</strong> " . htmlspecialchars($email) . "</p>
                            <p><strong>Date/Heure :</strong> " . date('d/m/Y H:i:s') . "</p>
                        ";
                        envoyer_alerte_securite($pdo, $sujet, $message);
                        
                        $formulaire_bloque = true;
                        $message_blocage = '⛔ IP bloquée pour 15 minutes suite à trop de tentatives échouées.';
                    } else {
                        $error = '❌ Email ou mot de passe incorrect. Il vous reste ' . $tentatives_restantes . ' tentative(s) avant blocage.';
                    }
                }
            }
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
.form-control:disabled {
    opacity: 0.4;
    cursor: not-allowed;
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
.btn-connexion:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
    background: #555;
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
.alert-blocked {
    background: #FEF3F2;
    border-left: 4px solid #E74C3C;
    color: #721C24;
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}
.alert-blocked i {
    font-size: 1.2rem;
    color: #E74C3C;
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
.security-info {
    text-align: center;
    margin-top: 15px;
    font-size: 0.7rem;
    color: #8A99AA;
}
.security-info i {
    color: #C8922A;
}
@media (max-width: 600px) {
    .connexion-card { padding: 25px 20px; }
}
</style>

<div class="connexion-container">
    <div class="connexion-card">
        <h1>🔑 Connexion</h1>
        <p class="subtitle">Connectez-vous à votre compte Awa Ka Sugu</p>
        
        <?php if($message_blocage): ?>
            <div class="alert-blocked">
                <i class="bi bi-lock-fill"></i>
                <span><?= htmlspecialchars($message_blocage) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" placeholder="votre@email.com" value="<?= htmlspecialchars($email) ?>" required <?= $formulaire_bloque ? 'disabled' : '' ?>>
            </div>
            
            <div class="form-group">
                <label>Mot de passe <span class="required">*</span></label>
                <input type="password" name="password" class="form-control" placeholder="Votre mot de passe" required <?= $formulaire_bloque ? 'disabled' : '' ?>>
            </div>
            
            <button type="submit" class="btn-connexion" <?= $formulaire_bloque ? 'disabled' : '' ?>>
                <i class="bi bi-box-arrow-in-right"></i> Se connecter
            </button>
        </form>
        
        <div class="inscription-link">
            Pas encore de compte ? <a href="inscription.php">Créer un compte</a>
        </div>
        
        <div style="margin-top:15px;text-align:center;font-size:0.8rem;">
            <a href="mot_de_passe_oublie.php" style="color:#8A99AA;text-decoration:none;">Mot de passe oublié ?</a>
        </div>
        
        <div class="security-info">
            <i class="bi bi-shield-check"></i> 
            Sécurisé : 3 tentatives avant blocage
            <?php if($formulaire_bloque): ?>
                <span style="color:#E74C3C;display:block;margin-top:3px;">
                    <i class="bi bi-lock-fill"></i> Formulaire bloqué temporairement
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>