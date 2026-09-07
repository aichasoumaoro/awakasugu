<?php
// ============================================
// LOGIN ADMIN - AWA KA SUGU
// ============================================
// Version sécurisée avec blocage après 3 tentatives
// ============================================

require_once '../includes/session_config.php';
require_once '../includes/functions_securite.php';

// Rediriger si déjà connecté
if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Connexion à la base de données
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
            // Rechercher l'admin
            $stmt = $pdo->prepare("
                SELECT id, nom, email, mot_de_passe, role, is_active 
                FROM admin 
                WHERE email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            // Vérifier si l'admin existe et mot de passe correct
            if ($admin && password_verify($password, $admin['mot_de_passe'])) {
                
                // Vérifier si le compte est actif
                if ($admin['is_active'] == 0) {
                    $error = 'Votre compte a été désactivé. Contactez le Super Admin.';
                    enregistrer_tentative($pdo, $email, false);
                } else {
                    // ============================================
                    // ✅ CONNEXION RÉUSSIE
                    // ============================================
                    
                    enregistrer_tentative($pdo, $email, true);
                    
                    // Supprimer les anciennes tentatives échouées pour ce compte
                    $stmt = $pdo->prepare("
                        DELETE FROM tentatives_connexion 
                        WHERE email = ? AND success = 0
                    ");
                    $stmt->execute([$email]);
                    
                    debloquer_compte($pdo, $email);
                    
                    // Initialiser la session admin
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
                    
                    // Mettre à jour la date de dernière connexion
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
                // ============================================
                // ❌ TENTATIVE ÉCHOUÉE
                // ============================================
                
                enregistrer_tentative($pdo, $email, false);
                
                $nb_tentatives = compter_tentatives_echouees($pdo, $email);
                $tentatives_restantes = 3 - $nb_tentatives;
                
                if ($nb_tentatives >= 3) {
                    // 🔒 Blocage du compte
                    bloquer_compte($pdo, $email, '3 tentatives échouées sur le compte ' . $email);
                    
                    // 📧 Envoyer alerte au Super Admin
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
                    // Vérifier aussi les tentatives depuis la même IP
                    $nb_tentatives_ip = compter_tentatives_ip_echouees($pdo, $ip);
                    
                    if ($nb_tentatives_ip >= 3) {
                        // 🔒 Blocage IP
                        bloquer_ip($pdo, $ip, '3 tentatives échouées depuis IP ' . $ip);
                        
                        // 📧 Envoyer alerte au Super Admin
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

// ============================================
// RÉCUPÉRATION DES INFOS POUR LA PAGE
// ============================================
$role_labels = [
    'super_admin' => 'Super Administrateur',
    'directeur' => 'Directrice',
    'admin' => 'Administratrice',
    'admin2' => 'Agente'
];

$role_colors = [
    'super_admin' => '#8E44AD',
    'directeur' => '#C8922A',
    'admin' => '#2980B9',
    'admin2' => '#7F8C8D'
];

$from_home = isset($_GET['from']) && $_GET['from'] === 'home';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Jost', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
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
            background: radial-gradient(ellipse at 30% 50%, rgba(200,146,42,0.05), transparent 70%);
            pointer-events: none;
        }
        .login-container {
            background: rgba(26, 26, 26, 0.95);
            border-radius: 24px;
            padding: 45px 40px;
            max-width: 420px;
            width: 100%;
            border: 1px solid rgba(200,146,42,0.15);
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
        }
        .login-container .logo {
            text-align: center;
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: #C8922A;
            letter-spacing: 4px;
            margin-bottom: 5px;
        }
        .login-container .logo-sub {
            text-align: center;
            color: rgba(255,255,255,0.2);
            font-size: 0.6rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 30px;
        }
        .decor-line {
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #C8922A, transparent);
            margin: 0 auto 25px;
        }
        .login-container .form-group {
            margin-bottom: 20px;
        }
        .login-container label {
            display: block;
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 6px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .login-container label i {
            color: #C8922A;
            margin-right: 6px;
        }
        .login-container input {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            background: rgba(255,255,255,0.04);
            color: #fff;
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: 'Jost', sans-serif;
        }
        .login-container input::placeholder {
            color: rgba(255,255,255,0.2);
        }
        .login-container input:focus {
            outline: none;
            border-color: #C8922A;
            background: rgba(255,255,255,0.07);
            box-shadow: 0 0 0 3px rgba(200,146,42,0.1);
        }
        .login-container input:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .login-container .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .login-container .btn-login:hover {
            background: linear-gradient(135deg, #9A6E1A, #C8922A);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(200,146,42,0.3);
        }
        .login-container .btn-login:active {
            transform: translateY(0);
        }
        .login-container .btn-login:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            background: #555;
            color: #999;
        }
        .login-container .btn-login:disabled:hover {
            background: #555;
            color: #999;
            transform: none;
            box-shadow: none;
        }
        .login-container .error {
            background: rgba(231,76,60,0.12);
            border-left: 3px solid #E74C3C;
            color: #E74C3C;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .login-container .error i {
            font-size: 1.1rem;
            margin-top: 2px;
        }
        .login-container .info-msg {
            background: rgba(46,204,113,0.12);
            border-left: 3px solid #27AE60;
            color: #27AE60;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .login-container .security-badge {
            background: rgba(200,146,42,0.05);
            border: 1px solid rgba(200,146,42,0.08);
            border-radius: 10px;
            padding: 10px 14px;
            text-align: center;
            color: rgba(255,255,255,0.2);
            font-size: 0.6rem;
            margin-top: 20px;
            letter-spacing: 0.5px;
        }
        .security-badge i { 
            color: #C8922A; 
            margin-right: 6px;
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.15);
        }
        .login-footer a {
            color: rgba(200,146,42,0.5);
            text-decoration: none;
            transition: color 0.3s;
        }
        .login-footer a:hover {
            color: #C8922A;
        }
        .tentative-info {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.3);
            text-align: center;
            margin-top: 10px;
        }
        .blocage-message {
            background: rgba(231,76,60,0.15);
            border-left: 3px solid #E74C3C;
            color: #E74C3C;
            padding: 15px 18px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-weight: 600;
        }
        .blocage-message i {
            font-size: 1.2rem;
            margin-top: 2px;
        }
        @media (max-width: 480px) {
            .login-container { padding: 30px 20px; }
            .login-container .logo { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo">✦ AWA KA SUGU</div>
    <div class="logo-sub">Espace Administration</div>
    <div class="decor-line"></div>

    <?php if($from_home): ?>
        <div class="info-msg">
            <i class="bi bi-arrow-right-circle-fill"></i>
            <span>Vous venez de la page d'accueil. Connectez-vous pour accéder à votre tableau de bord.</span>
        </div>
    <?php endif; ?>

    <?php if($message_blocage): ?>
        <div class="blocage-message">
            <i class="bi bi-lock-fill"></i>
            <span><?= htmlspecialchars($message_blocage) ?></span>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label><i class="bi bi-envelope"></i> Email</label>
            <input type="email" name="email" placeholder="admin@awakasugu.ml" value="<?= htmlspecialchars($email) ?>" required <?= $formulaire_bloque ? 'disabled' : '' ?>>
        </div>
        <div class="form-group">
            <label><i class="bi bi-lock"></i> Mot de passe</label>
            <input type="password" name="password" placeholder="••••••••" required <?= $formulaire_bloque ? 'disabled' : '' ?>>
        </div>
        <button type="submit" class="btn-login" <?= $formulaire_bloque ? 'disabled' : '' ?>>
            <i class="bi bi-box-arrow-in-right"></i> Se connecter
        </button>
    </form>

    <?php if(!$formulaire_bloque): ?>
        <div class="tentative-info">
            <i class="bi bi-shield-check"></i> 
            Sécurité : 3 tentatives autorisées avant blocage
        </div>
    <?php else: ?>
        <div class="tentative-info" style="color:#E74C3C;">
            <i class="bi bi-shield-lock-fill"></i> 
            Formulaire bloqué temporairement
        </div>
    <?php endif; ?>

    <div class="security-badge">
        <i class="bi bi-shield-lock-fill"></i> 
        Session admin séparée du site public
        <?php if(!$formulaire_bloque): ?>
            <span style="display:inline-block;margin-left:10px;color:#27AE60;">
                <i class="bi bi-check-circle-fill" style="color:#27AE60;"></i> Sécurisé
            </span>
        <?php else: ?>
            <span style="display:inline-block;margin-left:10px;color:#E74C3C;">
                <i class="bi bi-x-circle-fill" style="color:#E74C3C;"></i> Bloqué
            </span>
        <?php endif; ?>
    </div>

    <div class="login-footer">
        <a href="../index.php">← Retour au site</a>
    </div>
</div>

</body>
</html>