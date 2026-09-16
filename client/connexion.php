<?php
// ============================================
// CONNEXION CLIENT - Awa Ka Sugu
// ============================================
// Version sécurisée avec blocage après 3 tentatives
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';
require_once '../includes/panier_fonctions.php';
require_once '../includes/functions_securite.php';

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

nettoyer_tentatives_anciennes($pdo);
nettoyer_blocages_expires($pdo);

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$email = '';
$error = '';
$formulaire_bloque = false;
$message_blocage = '';

if (is_ip_bloquee($pdo, $ip)) {
    $formulaire_bloque = true;
    $message_blocage = '⛔ Votre adresse IP a été bloquée pour 15 minutes suite à trop de tentatives échouées.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$formulaire_bloque) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $blocage_compte = is_compte_bloque($pdo, $email);
        
        if ($blocage_compte['bloque']) {
            $fin = new DateTime($blocage_compte['fin_blocage']);
            $now = new DateTime();
            $minutes_restantes = $now->diff($fin)->i + 1;
            $formulaire_bloque = true;
            $message_blocage = '⛔ Ce compte est bloqué pour ' . $minutes_restantes . ' minutes. Réessayez plus tard.';
            enregistrer_tentative($pdo, $email, false);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ?");
            $stmt->execute([$email]);
            $client = $stmt->fetch();
            
            if ($client && password_verify($password, $client['mot_de_passe'])) {
                enregistrer_tentative($pdo, $email, true);
                $stmt = $pdo->prepare("DELETE FROM tentatives_connexion WHERE email = ? AND success = 0");
                $stmt->execute([$email]);
                debloquer_compte($pdo, $email);
                
                $_SESSION['client_id'] = $client['id'];
                $_SESSION['client_nom'] = ($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? 'Client');
                $_SESSION['client_email'] = $client['email'];
                $_SESSION['client_telephone'] = $client['telephone'] ?? '';
                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_created'] = time();
                
                $_SESSION['panier'] = chargerPanierClient($client['id'], $pdo);
                
                if (!empty($_SESSION['panier_temp'])) {
                    sauvegarderPanierClient($client['id'], $_SESSION['panier_temp'], $pdo);
                    $_SESSION['panier'] = $_SESSION['panier_temp'];
                    unset($_SESSION['panier_temp']);
                }
                
                $redirect = $_GET['redirect'] ?? 'mon_compte.php';
                header("Location: $redirect");
                exit;
            } else {
                enregistrer_tentative($pdo, $email, false);
                $nb_tentatives = compter_tentatives_echouees($pdo, $email);
                $tentatives_restantes = 3 - $nb_tentatives;
                
                if ($nb_tentatives >= 3) {
                    bloquer_compte($pdo, $email, '3 tentatives échouées sur le compte client ' . $email);
                    if (function_exists('email_admin_existe') && email_admin_existe($pdo, $email)) {
                        $sujet = "🔒 ALERTE - Compte client bloqué";
                        $message = "<p><strong>Un compte client a été bloqué suite à 3 tentatives de connexion échouées.</strong></p>
                            <p><strong>Compte visé :</strong> " . htmlspecialchars($email) . "</p>
                            <p><strong>Adresse IP :</strong> " . $ip . "</p>
                            <p><strong>Date/Heure :</strong> " . date('d/m/Y H:i:s') . "</p>";
                        envoyer_alerte_securite($pdo, $sujet, $message);
                    }
                    $formulaire_bloque = true;
                    $message_blocage = '⛔ Compte bloqué pour 30 minutes suite à 3 tentatives échouées.';
                } else {
                    $nb_tentatives_ip = compter_tentatives_ip_echouees($pdo, $ip);
                    if ($nb_tentatives_ip >= 3) {
                        bloquer_ip($pdo, $ip, '3 tentatives échouées depuis IP ' . $ip);
                        $sujet = "🔒 ALERTE - IP bloquée";
                        $message = "<p><strong>Une adresse IP a été bloquée suite à 3 tentatives de connexion échouées.</strong></p>
                            <p><strong>Adresse IP :</strong> " . $ip . "</p>
                            <p><strong>Email tenté :</strong> " . htmlspecialchars($email) . "</p>
                            <p><strong>Date/Heure :</strong> " . date('d/m/Y H:i:s') . "</p>";
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
/* ============================================
   PAGE CONNEXION - NÉON DIAGONAL
   ============================================ */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');

/* ===== ANIMATION DE TRANSITION ENTRE PAGES ===== */
.page-transition-overlay {
    position: fixed;
    inset: 0;
    background: #0a0a0a;
    background-image: 
        linear-gradient(rgba(200,146,42,0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(200,146,42,0.05) 1px, transparent 1px);
    background-size: 40px 40px;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 20px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.5s ease;
}
.page-transition-overlay.active {
    opacity: 1;
    pointer-events: all;
}
.page-transition-overlay .transition-logo {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    font-weight: 700;
    color: #C8922A;
    letter-spacing: 6px;
    text-shadow: 0 0 30px rgba(200,146,42,0.8);
    animation: logoPulse 1.2s ease-in-out infinite;
}
@keyframes logoPulse {
    0%, 100% { opacity: 0.5; transform: scale(0.98); }
    50% { opacity: 1; transform: scale(1.02); }
}
.page-transition-overlay .transition-bar {
    width: 200px;
    height: 3px;
    background: rgba(200,146,42,0.15);
    border-radius: 3px;
    overflow: hidden;
    position: relative;
}
.page-transition-overlay .transition-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: -40%;
    width: 40%;
    height: 100%;
    background: linear-gradient(90deg, transparent, #C8922A, transparent);
    animation: loadingBar 1.2s ease-in-out infinite;
    box-shadow: 0 0 15px rgba(200,146,42,0.9);
}
@keyframes loadingBar {
    0% { left: -40%; }
    100% { left: 100%; }
}

/* ===== PAGE ===== */
.login-neon-page {
    min-height: calc(100vh - 140px);
    background: #0a0a0a;
    background-image: 
        linear-gradient(rgba(200,146,42,0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(200,146,42,0.05) 1px, transparent 1px);
    background-size: 40px 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    font-family: 'Poppins', sans-serif;
    position: relative;
    overflow: hidden;
}
.login-neon-page::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at 30% 30%, rgba(200,146,42,0.08) 0%, transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(232,181,90,0.05) 0%, transparent 50%);
    pointer-events: none;
    animation: bgPulse 8s ease-in-out infinite;
}
@keyframes bgPulse {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}

.neon-wrapper {
    position: relative;
    width: 100%;
    max-width: 750px;
    min-height: 450px;
    background: transparent;
    border: 2px solid #C8922A;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 
        0 0 25px rgba(200,146,42,0.6),
        0 0 50px rgba(200,146,42,0.3),
        inset 0 0 25px rgba(200,146,42,0.1);
    animation: neonPulse 3s ease-in-out infinite;
}
@keyframes neonPulse {
    0%, 100% { 
        box-shadow: 0 0 25px rgba(200,146,42,0.6), 0 0 50px rgba(200,146,42,0.3), inset 0 0 25px rgba(200,146,42,0.1);
    }
    50% { 
        box-shadow: 0 0 35px rgba(200,146,42,0.8), 0 0 70px rgba(200,146,42,0.4), inset 0 0 35px rgba(200,146,42,0.15);
    }
}

.neon-form-box {
    position: absolute;
    top: 0;
    left: 0;
    width: 50%;
    height: 100%;
    background: rgba(10,10,10,0.95);
    padding: 50px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    z-index: 3;
    transition: 0.5s;
}
.neon-form-box h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    color: #fff;
    text-align: center;
    margin-bottom: 30px;
    letter-spacing: 2px;
    font-weight: 700;
}
.neon-form-box h2 span {
    color: #C8922A;
    text-shadow: 0 0 15px rgba(200,146,42,0.8);
}

.neon-field {
    position: relative;
    margin-bottom: 22px;
}
.neon-field label {
    display: block;
    font-size: 0.7rem;
    color: rgba(255,255,255,0.6);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 6px;
    font-weight: 500;
}
.neon-field .required { color: #E74C3C; }
.neon-input-wrap {
    position: relative;
}
.neon-input-wrap input {
    width: 100%;
    padding: 10px 0;
    border: none;
    border-bottom: 2px solid rgba(200,146,42,0.3);
    background: transparent;
    color: #fff;
    font-size: 0.95rem;
    font-family: 'Poppins', sans-serif;
    transition: 0.3s;
    outline: none;
}
.neon-input-wrap input:focus {
    border-bottom-color: #C8922A;
    box-shadow: 0 5px 15px -10px rgba(200,146,42,0.8);
}
.neon-input-wrap input:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.neon-input-wrap input::placeholder {
    color: rgba(255,255,255,0.25);
    font-size: 0.85rem;
}
.neon-input-wrap .field-icon {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(200,146,42,0.6);
    font-size: 1rem;
    pointer-events: none;
    transition: 0.3s;
}
.neon-input-wrap input:focus ~ .field-icon {
    color: #C8922A;
    text-shadow: 0 0 10px rgba(200,146,42,0.8);
}

.neon-toggle-pwd {
    position: absolute;
    right: 22px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: rgba(200,146,42,0.6);
    cursor: pointer;
    font-size: 1rem;
    padding: 4px;
    transition: 0.3s;
}
.neon-toggle-pwd:hover {
    color: #C8922A;
    text-shadow: 0 0 10px rgba(200,146,42,0.8);
}

.neon-btn {
    width: 100%;
    padding: 12px;
    margin-top: 15px;
    background: transparent;
    border: 2px solid #C8922A;
    border-radius: 30px;
    color: #C8922A;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 0.9rem;
    letter-spacing: 2px;
    text-transform: uppercase;
    cursor: pointer;
    transition: 0.4s;
    position: relative;
    overflow: hidden;
    box-shadow: 0 0 15px rgba(200,146,42,0.3);
}
.neon-btn:hover:not(:disabled) {
    background: #C8922A;
    color: #0a0a0a;
    box-shadow: 0 0 30px rgba(200,146,42,0.9), 0 0 60px rgba(200,146,42,0.5);
    transform: translateY(-2px);
}
.neon-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
    border-color: #666;
    color: #666;
    box-shadow: none;
}

.neon-bottom-text {
    text-align: center;
    margin-top: 18px;
    font-size: 0.78rem;
    color: rgba(255,255,255,0.5);
}
.neon-bottom-text a {
    color: #C8922A;
    text-decoration: none;
    font-weight: 600;
    transition: 0.3s;
    position: relative;
}
.neon-bottom-text a:hover {
    text-shadow: 0 0 10px rgba(200,146,42,0.9);
}

/* Lien avec effet de transition */
.transition-link {
    cursor: pointer;
}
.transition-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 1px;
    background: #C8922A;
    transition: width 0.3s;
    box-shadow: 0 0 8px rgba(200,146,42,0.9);
}
.transition-link:hover::after {
    width: 100%;
}

.neon-alert {
    padding: 12px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 0.82rem;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    line-height: 1.5;
}
.neon-alert.error {
    background: rgba(231,76,60,0.1);
    border: 1px solid rgba(231,76,60,0.4);
    color: #ff8fa8;
}
.neon-alert.error i { color: #E74C3C; margin-top: 2px; }
.neon-alert.blocked {
    background: rgba(231,76,60,0.15);
    border: 1px solid rgba(231,76,60,0.6);
    color: #ff8fa8;
    font-weight: 600;
}
.neon-alert.blocked i { color: #E74C3C; margin-top: 2px; text-shadow: 0 0 10px rgba(231,76,60,0.8); }

.neon-info-box {
    position: absolute;
    top: 0;
    right: 0;
    width: 50%;
    height: 100%;
    background: linear-gradient(135deg, #C8922A 0%, #E8B55A 50%, #F5D689 100%);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 40px;
    text-align: center;
    color: #0a0a0a;
    z-index: 2;
}
.neon-info-box::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, transparent 0%, transparent 50%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0.2) 100%);
    pointer-events: none;
}
.neon-info-box::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 60%);
    pointer-events: none;
    animation: shine 6s ease-in-out infinite;
}
@keyframes shine {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(20px, -20px); }
}

.neon-info-content {
    position: relative;
    z-index: 2;
}
.neon-info-box .brand-mini {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 4px;
    margin-bottom: 8px;
}
.neon-info-box .brand-line {
    width: 50px;
    height: 2px;
    background: #0a0a0a;
    margin: 10px auto 25px;
    border-radius: 2px;
}
.neon-info-box h3 {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 15px;
}
.neon-info-box h3 span {
    display: block;
    color: #fff;
    text-shadow: 0 2px 15px rgba(0,0,0,0.3);
}
.neon-info-box p {
    font-size: 0.85rem;
    line-height: 1.6;
    color: rgba(10,10,10,0.85);
    max-width: 260px;
    margin: 0 auto;
    font-weight: 500;
}

.neon-info-box .shop-features {
    margin-top: 25px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    text-align: left;
    max-width: 220px;
    margin-left: auto;
    margin-right: auto;
}
.neon-info-box .shop-feature {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'Poppins', sans-serif;
    font-size: 0.8rem;
    font-weight: 600;
    color: #0a0a0a;
    background: rgba(255,255,255,0.25);
    border: 1px solid rgba(255,255,255,0.4);
    border-radius: 10px;
    padding: 8px 14px;
    backdrop-filter: blur(4px);
    transition: 0.3s;
}
.neon-info-box .shop-feature:hover {
    background: rgba(255,255,255,0.4);
    transform: translateX(4px);
}
.neon-info-box .shop-feature i {
    font-size: 1rem;
    color: #0a0a0a;
}

.neon-corner {
    position: absolute;
    width: 20px;
    height: 20px;
    border: 2px solid #C8922A;
    z-index: 4;
    pointer-events: none;
}
.neon-corner.tl { top: -2px; left: -2px; border-right: none; border-bottom: none; border-radius: 20px 0 0 0; }
.neon-corner.tr { top: -2px; right: -2px; border-left: none; border-bottom: none; border-radius: 0 20px 0 0; }
.neon-corner.bl { bottom: -2px; left: -2px; border-right: none; border-top: none; border-radius: 0 0 0 20px; }
.neon-corner.br { bottom: -2px; right: -2px; border-left: none; border-top: none; border-radius: 0 0 20px 0; }

.neon-security {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px solid rgba(200,146,42,0.15);
    font-size: 0.68rem;
    color: rgba(200,146,42,0.8);
    letter-spacing: 0.5px;
}
.neon-security i { color: #C8922A; font-size: 0.8rem; text-shadow: 0 0 8px rgba(200,146,42,0.7); }

@media (max-width: 768px) {
    .neon-wrapper { max-width: 420px; min-height: auto; }
    .neon-form-box { position: relative; width: 100%; padding: 40px 30px; }
    .neon-info-box { display: none; }
}
@media (max-width: 420px) {
    .neon-form-box { padding: 30px 22px; }
    .neon-form-box h2 { font-size: 1.6rem; }
}
</style>

<!-- ===== OVERLAY DE TRANSITION ===== -->
<div class="page-transition-overlay" id="pageTransition">
    <div class="transition-logo">✦ AWA KA SUGU ✦</div>
    <div class="transition-bar"></div>
</div>

<div class="login-neon-page">
    
    <div class="neon-wrapper">
        
        <div class="neon-corner tl"></div>
        <div class="neon-corner tr"></div>
        <div class="neon-corner bl"></div>
        <div class="neon-corner br"></div>
        
        <div class="neon-form-box">
            
            <h2>Connex<span>ion</span></h2>
            
            <?php if($message_blocage): ?>
                <div class="neon-alert blocked">
                    <i class="bi bi-lock-fill"></i>
                    <span><?= htmlspecialchars($message_blocage) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="neon-alert error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                
                <div class="neon-field">
                    <label>Email <span class="required">*</span></label>
                    <div class="neon-input-wrap">
                        <input type="email" name="email" placeholder="votre@email.com" 
                               value="<?= htmlspecialchars($email) ?>" required 
                               <?= $formulaire_bloque ? 'disabled' : '' ?>>
                        <i class="bi bi-person field-icon"></i>
                    </div>
                </div>
                
                <div class="neon-field">
                    <label>Mot de passe <span class="required">*</span></label>
                    <div class="neon-input-wrap">
                        <input type="password" name="password" id="password" 
                               placeholder="••••••••" required 
                               <?= $formulaire_bloque ? 'disabled' : '' ?>>
                        <i class="bi bi-lock field-icon"></i>
                    </div>
                    <button type="button" class="neon-toggle-pwd" onclick="togglePassword()" 
                            style="right: 22px; top: 60%;">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
                
                <button type="submit" class="neon-btn" <?= $formulaire_bloque ? 'disabled' : '' ?>>
                    Se connecter
                </button>
                
            </form>
            
            <div class="neon-bottom-text">
                Pas encore de compte ? 
                <a href="inscription.php" class="transition-link" data-transition>
                    Créer un compte
                </a>
            </div>
            
            <div class="neon-bottom-text" style="margin-top:6px;">
                <a href="mot_de_passe_oublie.php">Mot de passe oublié ?</a>
            </div>
            
            <div class="neon-security">
                <i class="bi bi-shield-check"></i>
                <span>Sécurisé · 3 tentatives avant blocage</span>
            </div>
            
        </div>
        
        <div class="neon-info-box">
            <div class="neon-info-content">
                <div class="brand-mini">✦ AWA KA SUGU ✦</div>
                <div class="brand-line"></div>
                <h3>VOTRE <span>BOUTIQUE</span></h3>
                <p>
                    Mode, beauté et accessoires tendance. 
                    Commandez en ligne et faites-vous livrer 
                    rapidement partout au Mali.
                </p>
                <div class="shop-features">
                    <div class="shop-feature">
                        <i class="bi bi-truck"></i>
                        <span>Livraison rapide</span>
                    </div>
                    <div class="shop-feature">
                        <i class="bi bi-shield-check"></i>
                        <span>Paiement sécurisé</span>
                    </div>
                    <div class="shop-feature">
                        <i class="bi bi-tags"></i>
                        <span>Prix imbattables</span>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    
</div>

<script>
// ===== TOGGLE PASSWORD =====
function togglePassword() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// ===== ANIMATION DE TRANSITION VERS INSCRIPTION =====
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('pageTransition');
    const links = document.querySelectorAll('[data-transition]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('href');
            
            // Activer l'overlay
            overlay.classList.add('active');
            
            // Attendre la fin de l'animation avant de naviguer
            setTimeout(() => {
                window.location.href = url;
            }, 700);
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>