<?php
// ============================================
// LOGIN ADMIN - AWA KA SUGU
// ============================================
// Version sécurisée avec blocage après 3 tentatives
// ============================================

require_once '../includes/session_config.php';
require_once '../includes/functions_securite.php';

if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
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
            $stmt = $pdo->prepare("
                SELECT id, nom, email, mot_de_passe, role, is_active 
                FROM admin 
                WHERE email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['mot_de_passe'])) {
                
                if ($admin['is_active'] == 0) {
                    $error = 'Votre compte a été désactivé. Contactez le Super Admin.';
                    enregistrer_tentative($pdo, $email, false);
                } else {
                    enregistrer_tentative($pdo, $email, true);
                    
                    $stmt = $pdo->prepare("DELETE FROM tentatives_connexion WHERE email = ? AND success = 0");
                    $stmt->execute([$email]);
                    
                    debloquer_compte($pdo, $email);
                    
                    session_name('ADMIN_SESSION');
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    session_regenerate_id(true);
                    
                    $_SESSION['admin_id'] = (int)$admin['id'];
                    $_SESSION['admin_nom'] = htmlspecialchars($admin['nom'], ENT_QUOTES, 'UTF-8');
                    $_SESSION['admin_email'] = htmlspecialchars($admin['email'], ENT_QUOTES, 'UTF-8');
                    $_SESSION['admin_role'] = $admin['role'] ?? 'admin';
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_created'] = time();
                    
                    $update = $pdo->prepare("UPDATE admin SET last_login = NOW() WHERE id = ?");
                    $update->execute([$admin['id']]);
                    
                    enregistrer_log_action(
                        $pdo,
                        $admin['id'],
                        $admin['nom'],
                        $admin['email'],
                        'Connexion',
                        'Connexion réussie depuis IP: ' . $ip
                    );
                    
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                enregistrer_tentative($pdo, $email, false);
                
                $nb_tentatives = compter_tentatives_echouees($pdo, $email);
                $tentatives_restantes = 3 - $nb_tentatives;
                
                if ($nb_tentatives >= 3) {
                    bloquer_compte($pdo, $email, '3 tentatives échouées sur le compte ' . $email);
                    
                    $sujet = "🔒 ALERTE - Compte administrateur bloqué";
                    $message = "
                        <p><strong>Un compte administrateur a été bloqué suite à 3 tentatives de connexion échouées.</strong></p>
                        <p><strong>Compte visé :</strong> " . htmlspecialchars($email) . "</p>
                        <p><strong>Adresse IP :</strong> " . $ip . "</p>
                        <p><strong>Date/Heure :</strong> " . date('d/m/Y H:i:s') . "</p>
                        <p style='color:#E74C3C;font-weight:bold;'>
                            ⚠️ Si vous n'êtes pas à l'origine de ces tentatives, 
                            vérifiez immédiatement la sécurité de votre compte.
                        </p>
                    ";
                    envoyer_alerte_securite($pdo, $sujet, $message);
                    
                    $formulaire_bloque = true;
                    $message_blocage = '⛔ Compte bloqué pour 30 minutes suite à 3 tentatives échouées. Un email d\'alerte a été envoyé au Super Admin.';
                } else {
                    $nb_tentatives_ip = compter_tentatives_ip_echouees($pdo, $ip);
                    
                    if ($nb_tentatives_ip >= 3) {
                        bloquer_ip($pdo, $ip, '3 tentatives échouées depuis IP ' . $ip);
                        
                        $sujet = "🔒 ALERTE - IP bloquée";
                        $message = "
                            <p><strong>Une adresse IP a été bloquée suite à 3 tentatives de connexion échouées.</strong></p>
                            <p><strong>Adresse IP :</strong> " . $ip . "</p>
                            <p><strong>Email tenté :</strong> " . htmlspecialchars($email) . "</p>
                            <p><strong>Date/Heure :</strong> " . date('d/m/Y H:i:s') . "</p>
                        ";
                        envoyer_alerte_securite($pdo, $sujet, $message);
                        
                        $formulaire_bloque = true;
                        $message_blocage = '⛔ IP bloquée pour 15 minutes suite à trop de tentatives échouées. Un email d\'alerte a été envoyé au Super Admin.';
                    } else {
                        $error = '❌ Email ou mot de passe incorrect. Il vous reste ' . $tentatives_restantes . ' tentative(s) avant blocage du compte.';
                    }
                }
            }
        }
    }
}

$from_home = isset($_GET['from']) && $_GET['from'] === 'home';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           LOGIN ADMIN - COMPACT & RAFFINÉ
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0a0a;
            background-image: 
                linear-gradient(rgba(200,146,42,0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(200,146,42,0.05) 1px, transparent 1px);
            background-size: 40px 40px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        body::before {
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
        
        /* ===== WRAPPER COMPACT ===== */
        .admin-wrapper {
            position: relative;
            width: 100%;
            max-width: 400px; /* ← RÉDUIT (avant 520px) */
            background: rgba(10,10,10,0.96);
            border: 2px solid #C8922A;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 
                0 0 20px rgba(200,146,42,0.5),
                0 0 40px rgba(200,146,42,0.25),
                inset 0 0 20px rgba(200,146,42,0.08);
            animation: neonPulse 3s ease-in-out infinite;
            z-index: 1;
        }
        @keyframes neonPulse {
            0%, 100% { 
                box-shadow: 0 0 20px rgba(200,146,42,0.5), 0 0 40px rgba(200,146,42,0.25), inset 0 0 20px rgba(200,146,42,0.08);
            }
            50% { 
                box-shadow: 0 0 30px rgba(200,146,42,0.75), 0 0 60px rgba(200,146,42,0.35), inset 0 0 30px rgba(200,146,42,0.12);
            }
        }
        
        /* ===== COINS DÉCORATIFS ===== */
        .neon-corner {
            position: absolute;
            width: 16px;
            height: 16px;
            border: 2px solid #C8922A;
            z-index: 4;
            pointer-events: none;
        }
        .neon-corner.tl { top: -2px; left: -2px; border-right: none; border-bottom: none; border-radius: 18px 0 0 0; }
        .neon-corner.tr { top: -2px; right: -2px; border-left: none; border-bottom: none; border-radius: 0 18px 0 0; }
        .neon-corner.bl { bottom: -2px; left: -2px; border-right: none; border-top: none; border-radius: 0 0 0 18px; }
        .neon-corner.br { bottom: -2px; right: -2px; border-left: none; border-top: none; border-radius: 0 0 18px 0; }
        
        /* ===== HEADER COMPACT ===== */
        .admin-header {
            background: linear-gradient(135deg, #0D0D0D 0%, #1A1510 100%);
            padding: 25px 30px 20px;
            text-align: center;
            border-bottom: 2px solid #C8922A;
            position: relative;
            overflow: hidden;
        }
        .admin-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(200,146,42,0.15) 0%, transparent 60%);
            animation: shine 8s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes shine {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(20px, -20px); }
        }
        
        .admin-header .logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.35rem;
            font-weight: 700;
            color: #C8922A;
            letter-spacing: 4px;
            margin-bottom: 5px;
            text-shadow: 0 0 20px rgba(200,146,42,0.5);
            position: relative;
            z-index: 2;
        }
        .admin-header .logo-sub {
            color: rgba(255,255,255,0.35);
            font-size: 0.58rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            position: relative;
            z-index: 2;
            margin-bottom: 10px;
        }
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(200,146,42,0.15);
            border: 1px solid rgba(200,146,42,0.4);
            color: #E8B55A;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            position: relative;
            z-index: 2;
        }
        .admin-badge i {
            color: #C8922A;
            text-shadow: 0 0 8px rgba(200,146,42,0.8);
            font-size: 0.7rem;
        }
        
        /* ===== BODY COMPACT ===== */
        .admin-body {
            padding: 25px 28px 22px;
        }
        
        /* ===== ALERTES ===== */
        .admin-alert {
            padding: 10px 13px;
            border-radius: 9px;
            margin-bottom: 15px;
            font-size: 0.74rem;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.5;
            animation: slideDown 0.4s ease;
        }
        @keyframes slideDown {
            0% { opacity: 0; transform: translateY(-8px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .admin-alert.error {
            background: rgba(231,76,60,0.1);
            border: 1px solid rgba(231,76,60,0.4);
            color: #ff8fa8;
        }
        .admin-alert.error i { color: #E74C3C; margin-top: 2px; font-size: 0.9rem; }
        .admin-alert.blocked {
            background: rgba(231,76,60,0.15);
            border: 1px solid rgba(231,76,60,0.6);
            color: #ff8fa8;
            font-weight: 600;
        }
        .admin-alert.blocked i { color: #E74C3C; margin-top: 2px; font-size: 0.9rem; }
        .admin-alert.info {
            background: rgba(41,128,185,0.1);
            border: 1px solid rgba(41,128,185,0.4);
            color: #7fc8ff;
        }
        .admin-alert.info i { color: #2980B9; margin-top: 2px; font-size: 0.9rem; }
        
        /* ===== CHAMPS COMPACTS ===== */
        .admin-field {
            position: relative;
            margin-bottom: 16px;
        }
        .admin-field label {
            display: block;
            color: rgba(255,255,255,0.5);
            font-size: 0.62rem;
            font-weight: 600;
            margin-bottom: 6px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }
        .admin-field label i {
            color: #C8922A;
            margin-right: 5px;
            font-size: 0.72rem;
        }
        .admin-field .required {
            color: #E74C3C;
        }
        .admin-input-wrap {
            position: relative;
        }
        .admin-input-wrap input {
            width: 100%;
            padding: 11px 14px 11px 40px;
            border: 1.5px solid rgba(200,146,42,0.2);
            border-radius: 10px;
            background: rgba(255,255,255,0.03);
            color: #fff;
            font-size: 0.85rem;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            outline: none;
        }
        .admin-input-wrap input::placeholder {
            color: rgba(255,255,255,0.2);
            font-size: 0.78rem;
        }
        .admin-input-wrap input:focus {
            border-color: #C8922A;
            background: rgba(255,255,255,0.05);
            box-shadow: 0 0 0 3px rgba(200,146,42,0.1);
        }
        .admin-input-wrap input:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .admin-input-wrap .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(200,146,42,0.6);
            font-size: 0.9rem;
            pointer-events: none;
            transition: 0.3s;
        }
        .admin-input-wrap input:focus ~ .input-icon {
            color: #C8922A;
            text-shadow: 0 0 10px rgba(200,146,42,0.8);
        }
        
        /* Toggle password */
        .admin-toggle-pwd {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(200,146,42,0.6);
            cursor: pointer;
            font-size: 0.9rem;
            padding: 4px;
            transition: 0.3s;
        }
        .admin-toggle-pwd:hover {
            color: #C8922A;
            text-shadow: 0 0 10px rgba(200,146,42,0.8);
        }
        
        /* ===== BOUTON LOGIN COMPACT ===== */
        .admin-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #0a0a0a;
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-top: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 5px 18px rgba(200,146,42,0.3);
            position: relative;
            overflow: hidden;
        }
        .admin-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }
        .admin-btn:hover:not(:disabled)::before {
            left: 100%;
        }
        .admin-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #E8B55A, #F5D689);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(200,146,42,0.5);
        }
        .admin-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background: #444;
            color: #888;
            box-shadow: none;
        }
        
        /* ===== INFO SÉCURITÉ COMPACT ===== */
        .admin-security-info {
            text-align: center;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(200,146,42,0.12);
            font-size: 0.62rem;
            color: rgba(200,146,42,0.7);
            letter-spacing: 0.4px;
        }
        .admin-security-info i {
            color: #C8922A;
            margin-right: 4px;
            text-shadow: 0 0 8px rgba(200,146,42,0.7);
        }
        
        /* ===== SESSION BADGE COMPACT ===== */
        .admin-session-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 12px;
            padding: 9px 12px;
            background: rgba(200,146,42,0.06);
            border: 1px solid rgba(200,146,42,0.15);
            border-radius: 9px;
            font-size: 0.6rem;
            color: rgba(200,146,42,0.75);
            letter-spacing: 0.4px;
            flex-wrap: wrap;
        }
        .admin-session-badge .dot-ok {
            color: #27AE60;
            font-size: 0.45rem;
        }
        .admin-session-badge .dot-ko {
            color: #E74C3C;
            font-size: 0.45rem;
        }
        .admin-session-badge .status-text {
            color: #27AE60;
            font-weight: 600;
        }
        .admin-session-badge .status-text.ko {
            color: #E74C3C;
        }
        .admin-session-badge i {
            color: #C8922A;
            font-size: 0.7rem;
        }
        
        /* ===== FOOTER COMPACT ===== */
        .admin-footer {
            text-align: center;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .admin-footer a {
            color: rgba(200,146,42,0.6);
            text-decoration: none;
            font-size: 0.72rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }
        .admin-footer a:hover {
            color: #C8922A;
            text-shadow: 0 0 10px rgba(200,146,42,0.7);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 420px) {
            .admin-header { padding: 22px 22px 18px; }
            .admin-header .logo { font-size: 1.15rem; letter-spacing: 3px; }
            .admin-body { padding: 22px 20px 20px; }
            .admin-input-wrap input { font-size: 0.82rem; padding: 10px 12px 10px 38px; }
            .admin-btn { font-size: 0.78rem; padding: 11px; }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    
    <div class="neon-corner tl"></div>
    <div class="neon-corner tr"></div>
    <div class="neon-corner bl"></div>
    <div class="neon-corner br"></div>
    
    <!-- ===== HEADER ===== -->
    <div class="admin-header">
        <div class="logo">✦ AWA KA SUGU ✦</div>
        <div class="logo-sub">Espace Administration</div>
        <div class="admin-badge">
            <i class="bi bi-shield-lock-fill"></i>
            Zone sécurisée
        </div>
    </div>
    
    <!-- ===== BODY ===== -->
    <div class="admin-body">
        
        <?php if($from_home): ?>
            <div class="admin-alert info">
                <i class="bi bi-arrow-right-circle-fill"></i>
                <span>Vous venez de la page d'accueil.</span>
            </div>
        <?php endif; ?>
        
        <?php if($message_blocage): ?>
            <div class="admin-alert blocked">
                <i class="bi bi-lock-fill"></i>
                <span><?= htmlspecialchars($message_blocage) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="admin-alert error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            
            <div class="admin-field">
                <label>Email <span class="required">*</span></label>
                <div class="admin-input-wrap">
                    <input type="email" name="email" placeholder="admin@awakasugu.ml" 
                           value="<?= htmlspecialchars($email) ?>" required 
                           <?= $formulaire_bloque ? 'disabled' : '' ?>>
                    <i class="bi bi-envelope input-icon"></i>
                </div>
            </div>
            
            <div class="admin-field">
                <label>Mot de passe <span class="required">*</span></label>
                <div class="admin-input-wrap">
                    <input type="password" name="password" id="password" 
                           placeholder="••••••••" required 
                           <?= $formulaire_bloque ? 'disabled' : '' ?>>
                    <i class="bi bi-lock input-icon"></i>
                    <button type="button" class="admin-toggle-pwd" onclick="togglePassword()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="admin-btn" <?= $formulaire_bloque ? 'disabled' : '' ?>>
                <i class="bi bi-box-arrow-in-right"></i> Se connecter
            </button>
            
        </form>
        
        <?php if(!$formulaire_bloque): ?>
            <div class="admin-security-info">
                <i class="bi bi-shield-check"></i>
                3 tentatives autorisées avant blocage
            </div>
        <?php else: ?>
            <div class="admin-security-info" style="color:#E74C3C;">
                <i class="bi bi-shield-lock-fill"></i>
                Formulaire bloqué temporairement
            </div>
        <?php endif; ?>
        
        <div class="admin-session-badge">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Session séparée</span>
            <?php if(!$formulaire_bloque): ?>
                <span class="dot-ok">●</span>
                <span class="status-text">Sécurisé</span>
            <?php else: ?>
                <span class="dot-ko">●</span>
                <span class="status-text ko">Bloqué</span>
            <?php endif; ?>
        </div>
        
        <div class="admin-footer">
            <a href="../index.php">
                <i class="bi bi-arrow-left"></i> Retour au site
            </a>
        </div>
        
    </div>
    
</div>

<script>
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
</script>

</body>
</html>