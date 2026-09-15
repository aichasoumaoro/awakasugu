<?php
// ============================================
// INDEX - AWA KA SUGU
// Page d'accueil complète avec splash screen
// ============================================

// ============================================
// 1. DÉTECTION DE LA SESSION ADMIN
// ============================================
$current_session_name = session_name();
$current_session_id = session_id();
$session_was_active = (session_status() === PHP_SESSION_ACTIVE);

if ($session_was_active) {
    session_write_close();
}

session_name('ADMIN_SESSION');
session_start();

$admin_connecte = false;
$admin_info = null;

if (isset($_SESSION['admin_id']) && 
    isset($_SESSION['admin_logged_in']) && 
    $_SESSION['admin_logged_in'] === true) {
    $admin_connecte = true;
    $admin_info = [
        'id' => $_SESSION['admin_id'] ?? null,
        'nom' => $_SESSION['admin_nom'] ?? 'Administrateur',
        'email' => $_SESSION['admin_email'] ?? '',
        'role' => $_SESSION['admin_role'] ?? 'admin'
    ];
}

session_write_close();

if ($session_was_active) {
    session_name($current_session_name);
    session_start();
} else {
    session_name('PUBLIC_SESSION');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// ============================================
// 1.5 DÉTECTION DU CLIENT CONNECTÉ
// ============================================
$client_connecte = false;
$client_info = null;

if (isset($_SESSION['client_id']) && 
    isset($_SESSION['client_logged_in']) && 
    $_SESSION['client_logged_in'] === true) {
    $client_connecte = true;
    $client_info = [
        'id' => $_SESSION['client_id'] ?? null,
        'nom' => $_SESSION['client_nom'] ?? 'Client',
        'email' => $_SESSION['client_email'] ?? '',
        'telephone' => $_SESSION['client_telephone'] ?? ''
    ];
}

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

// ============================================
// 2. TRAITEMENT DU LOGIN
// ============================================
$login_error = '';
$login_success = '';
$formulaire_bloque = false;
$message_blocage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_login'])) {
    $host = 'localhost';
    $dbname = 'awakasugu_db';
    $user = 'root';
    $pass = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        $login_error = "Erreur de connexion à la base de données.";
    }

    if (!isset($pdo)) {
        $login_error = "Erreur de connexion à la base de données.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $login_error = 'Veuillez remplir tous les champs.';
        } else {
            require_once 'includes/functions_securite.php';
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            
            if (function_exists('nettoyer_tentatives_anciennes')) {
                nettoyer_tentatives_anciennes($pdo);
            }
            if (function_exists('nettoyer_blocages_expires')) {
                nettoyer_blocages_expires($pdo);
            }
            
            $ip_bloquee = false;
            if (function_exists('is_ip_bloquee')) {
                $ip_bloquee = is_ip_bloquee($pdo, $ip);
            }
            
            if ($ip_bloquee) {
                $formulaire_bloque = true;
                $message_blocage = 'Votre adresse IP a été bloquée pour 15 minutes.';
            } else {
                $blocage_compte = ['bloque' => false];
                if (function_exists('is_compte_bloque')) {
                    $blocage_compte = is_compte_bloque($pdo, $email);
                }
                
                if ($blocage_compte['bloque']) {
                    $fin = new DateTime($blocage_compte['fin_blocage']);
                    $now = new DateTime();
                    $minutes_restantes = $now->diff($fin)->i + 1;
                    $formulaire_bloque = true;
                    $message_blocage = 'Ce compte est bloqué pour ' . $minutes_restantes . ' minutes.';
                    if (function_exists('enregistrer_tentative')) {
                        enregistrer_tentative($pdo, $email, false);
                    }
                } else {
                    $stmt = $pdo->prepare("SELECT id, nom, email, mot_de_passe, role, is_active FROM admin WHERE email = ?");
                    $stmt->execute([$email]);
                    $admin = $stmt->fetch();

                    if ($admin && password_verify($password, $admin['mot_de_passe'])) {
                        if ($admin['is_active'] == 0) {
                            $login_error = 'Votre compte admin a été désactivé.';
                            if (function_exists('enregistrer_tentative')) {
                                enregistrer_tentative($pdo, $email, false);
                            }
                        } else {
                            if (function_exists('enregistrer_tentative')) {
                                enregistrer_tentative($pdo, $email, true);
                            }
                            
                            $stmt = $pdo->prepare("DELETE FROM tentatives_connexion WHERE email = ? AND success = 0");
                            $stmt->execute([$email]);
                            
                            if (function_exists('debloquer_compte')) {
                                debloquer_compte($pdo, $email);
                            }
                            
                            if (session_status() === PHP_SESSION_ACTIVE) {
                                session_write_close();
                            }
                            
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

                            if (function_exists('enregistrer_log_action')) {
                                enregistrer_log_action(
                                    $pdo,
                                    $admin['id'],
                                    $admin['nom'],
                                    $admin['email'],
                                    'Connexion depuis la page d\'accueil',
                                    'Connexion admin réussie depuis IP: ' . $ip
                                );
                            }

                            session_write_close();
                            header('Location: admin/dashboard.php');
                            exit;
                        }
                    } else {
                        $stmt = $pdo->prepare("SELECT id, nom, email, mot_de_passe, telephone FROM clients WHERE email = ?");
                        $stmt->execute([$email]);
                        $client = $stmt->fetch();

                        if ($client && password_verify($password, $client['mot_de_passe'])) {
                            if (function_exists('enregistrer_tentative')) {
                                enregistrer_tentative($pdo, $email, true);
                            }
                            
                            $stmt = $pdo->prepare("DELETE FROM tentatives_connexion WHERE email = ? AND success = 0");
                            $stmt->execute([$email]);
                            
                            if (function_exists('debloquer_compte')) {
                                debloquer_compte($pdo, $email);
                            }
                            
                            if (session_status() === PHP_SESSION_ACTIVE) {
                                session_write_close();
                            }
                            
                            session_name('PUBLIC_SESSION');
                            if (session_status() === PHP_SESSION_NONE) {
                                session_start();
                            }
                            session_regenerate_id(true);

                            $_SESSION['client_id'] = (int)$client['id'];
                            $_SESSION['client_nom'] = htmlspecialchars($client['nom'], ENT_QUOTES, 'UTF-8');
                            $_SESSION['client_email'] = htmlspecialchars($client['email'], ENT_QUOTES, 'UTF-8');
                            $_SESSION['client_telephone'] = htmlspecialchars($client['telephone'] ?? '', ENT_QUOTES, 'UTF-8');
                            $_SESSION['client_logged_in'] = true;
                            $_SESSION['client_created'] = time();

                            session_write_close();
                            header('Location: client/mon_compte.php');
                            exit;
                        } else {
                            if (function_exists('enregistrer_tentative')) {
                                enregistrer_tentative($pdo, $email, false);
                            }
                            
                            $nb_tentatives = 0;
                            if (function_exists('compter_tentatives_echouees')) {
                                $nb_tentatives = compter_tentatives_echouees($pdo, $email);
                            }
                            
                            $nb_tentatives_ip = 0;
                            if (function_exists('compter_tentatives_ip_echouees')) {
                                $nb_tentatives_ip = compter_tentatives_ip_echouees($pdo, $ip);
                            }
                            
                            $tentatives_restantes = 3 - $nb_tentatives;
                            
                            if ($nb_tentatives >= 3) {
                                if (function_exists('bloquer_compte')) {
                                    bloquer_compte($pdo, $email, '3 tentatives échouées sur le compte ' . $email);
                                }
                                
                                if (function_exists('email_admin_existe') && email_admin_existe($pdo, $email)) {
                                    $sujet = "ALERTE - Compte administrateur bloqué";
                                    $message = "
                                        <p><strong>Un compte administrateur a été bloqué suite à 3 tentatives de connexion échouées.</strong></p>
                                        <p><strong>Compte visé :</strong> " . htmlspecialchars($email) . "</p>
                                        <p><strong>Adresse IP :</strong> " . $ip . "</p>
                                        <p><strong>Date/Heure :</strong> " . date('d/m/Y H:i:s') . "</p>
                                    ";
                                    if (function_exists('envoyer_alerte_securite')) {
                                        envoyer_alerte_securite($pdo, $sujet, $message);
                                    }
                                }
                                
                                $formulaire_bloque = true;
                                $message_blocage = 'Compte bloqué pour 30 minutes.';
                            } else if ($nb_tentatives_ip >= 3) {
                                if (function_exists('bloquer_ip')) {
                                    bloquer_ip($pdo, $ip, '3 tentatives échouées depuis IP ' . $ip);
                                }
                                
                                $formulaire_bloque = true;
                                $message_blocage = 'IP bloquée pour 15 minutes.';
                            } else {
                                $login_error = 'Email ou mot de passe incorrect. Il vous reste ' . $tentatives_restantes . ' tentative(s).';
                            }
                        }
                    }
                }
            }
        }
    }
}

// ============================================
// 3. SPLASH SCREEN
// ============================================
$show_splash = !isset($_GET['splash_done']);

if ($show_splash) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <title>Bienvenue chez Awa Ka Sugu</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800;900&family=Jost:wght@300;400;500;600;700&display=swap');
            @import url('https://fonts.googleapis.com/css2?family=Amiri&display=swap');

            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Jost', sans-serif;
                min-height: 100vh;
                background: linear-gradient(135deg, #0A0806 0%, #1A1510 50%, #0A0806 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                position: relative;
            }
            .particles {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                overflow: hidden;
                z-index: 1;
            }
            .particle {
                position: absolute;
                background: radial-gradient(circle, rgba(200,146,42,0.4), transparent);
                border-radius: 50%;
                animation: floatParticle 8s infinite ease-in-out;
            }
            @keyframes floatParticle {
                0%, 100% { transform: translateY(0) translateX(0); opacity: 0; }
                50% { opacity: 0.5; }
                100% { transform: translateY(-100px) translateX(50px); opacity: 0; }
            }
            .particle:nth-child(1) { width: 300px; height: 300px; top: -150px; left: -150px; animation-delay: 0s; }
            .particle:nth-child(2) { width: 200px; height: 200px; bottom: -100px; right: -100px; animation-delay: 1s; }
            .particle:nth-child(3) { width: 150px; height: 150px; top: 50%; left: 10%; animation-delay: 2s; }
            .particle:nth-child(4) { width: 100px; height: 100px; bottom: 20%; right: 15%; animation-delay: 0.5s; }
            .particle:nth-child(5) { width: 250px; height: 250px; top: 30%; right: -50px; animation-delay: 1.5s; }
            .particle:nth-child(6) { width: 80px; height: 80px; top: 70%; left: 20%; animation-delay: 2.5s; }

            .splash-container {
                position: relative;
                z-index: 10;
                text-align: center;
                padding: 40px;
                max-width: 600px;
                width: 90%;
                animation: fadeInScale 0.8s ease-out;
            }
            @keyframes fadeInScale {
                from { opacity: 0; transform: scale(0.9); }
                to { opacity: 1; transform: scale(1); }
            }
            .logo-wrapper {
                margin-bottom: 30px;
                animation: floatLogo 3s ease-in-out infinite;
            }
            @keyframes floatLogo {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-10px); }
            }
            .logo-ring {
                position: relative;
                width: 100px;
                height: 100px;
                margin: 0 auto;
            }
            .logo-ring-outer {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                border-radius: 50%;
                background: linear-gradient(135deg, #C8922A, #F5D78C, #C8922A);
                animation: rotateRing 3s linear infinite;
            }
            @keyframes rotateRing {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .logo-ring-inner {
                position: absolute;
                top: 6px;
                left: 6px;
                width: calc(100% - 12px);
                height: calc(100% - 12px);
                border-radius: 50%;
                background: #0D0D0D;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .logo-ring-inner span {
                font-family: 'Playfair Display', serif;
                font-size: 2.5rem;
                font-weight: 800;
                background: linear-gradient(135deg, #C8922A, #F5D78C);
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
            }
            .welcome-message { margin-bottom: 25px; }
            .salutation {
                font-size: 2.2rem;
                font-family: 'Playfair Display', serif;
                color: #FFFFFF;
                text-shadow: 0 0 20px rgba(200,146,42,0.5), 0 2px 5px rgba(0,0,0,0.3);
                margin-bottom: 10px;
                letter-spacing: 2px;
                animation: glowPulse 2s ease-in-out infinite;
                font-weight: 700;
            }
            @keyframes glowPulse {
                0%, 100% { text-shadow: 0 0 10px rgba(200,146,42,0.3), 0 2px 5px rgba(0,0,0,0.3); }
                50% { text-shadow: 0 0 25px rgba(200,146,42,0.6), 0 2px 5px rgba(0,0,0,0.3); }
            }
            .arabic {
                font-size: 1.8rem;
                font-family: 'Amiri', serif;
                color: #C8922A;
                margin-bottom: 15px;
                direction: rtl;
                text-shadow: 0 0 10px rgba(200,146,42,0.3);
            }
            .greeting-text {
                font-size: 1rem;
                color: rgba(255,255,255,0.9);
                line-height: 1.5;
                font-weight: 500;
                text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            }
            .greeting-text strong { color: #C8922A; font-weight: 700; }
            .awa-photo {
                width: 90px;
                height: 90px;
                border-radius: 50%;
                margin: 20px auto;
                border: 2px solid #C8922A;
                overflow: hidden;
                box-shadow: 0 8px 20px rgba(200,146,42,0.3);
                animation: pulseBorder 2s ease-in-out infinite;
            }
            @keyframes pulseBorder {
                0%, 100% { border-color: #C8922A; box-shadow: 0 8px 20px rgba(200,146,42,0.3); }
                50% { border-color: #E8B55A; box-shadow: 0 12px 30px rgba(200,146,42,0.5); }
            }
            .awa-photo img { width: 100%; height: 100%; object-fit: cover; }
            .citation {
                font-style: italic;
                color: rgba(255,255,255,0.7);
                font-size: 0.85rem;
                margin: 15px 0;
                padding: 12px 18px;
                background: rgba(200,146,42,0.1);
                border-radius: 20px;
                border-left: 3px solid #C8922A;
            }
            .citation i { color: #C8922A; margin-right: 8px; }
            .loader {
                margin-top: 25px;
                display: flex;
                justify-content: center;
                gap: 8px;
            }
            .loader-dot {
                width: 8px;
                height: 8px;
                background: #C8922A;
                border-radius: 50%;
                animation: bounce 1.4s ease-in-out infinite;
            }
            .loader-dot:nth-child(1) { animation-delay: 0s; }
            .loader-dot:nth-child(2) { animation-delay: 0.2s; }
            .loader-dot:nth-child(3) { animation-delay: 0.4s; }
            @keyframes bounce {
                0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
                40% { transform: scale(1); opacity: 1; }
            }
            .redirect-text {
                margin-top: 20px;
                font-size: 0.7rem;
                color: rgba(255,255,255,0.4);
                font-weight: 500;
                letter-spacing: 1px;
            }
            @media (max-width: 600px) {
                .salutation { font-size: 1.6rem; }
                .arabic { font-size: 1.3rem; }
                .greeting-text { font-size: 0.85rem; }
                .logo-ring { width: 75px; height: 75px; }
                .logo-ring-inner span { font-size: 1.8rem; }
                .awa-photo { width: 70px; height: 70px; }
            }
        </style>
    </head>
    <body>
        <div class="particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
        <div class="splash-container">
            <div class="logo-wrapper">
                <div class="logo-ring">
                    <div class="logo-ring-outer"></div>
                    <div class="logo-ring-inner"><span>A</span></div>
                </div>
            </div>
            <div class="welcome-message">
                <div class="salutation">As-Salam Alaykoum</div>
                <div class="arabic">السلام عليكم</div>
                <p class="greeting-text">
                    <strong>Awa Doumbia</strong> vous souhaite la bienvenue<br>
                    dans l'univers <strong>Awa Ka Sugu</strong>
                </p>
            </div>
            <div class="awa-photo">
                <img src="assets/images/awa1.jpeg" alt="Awa Doumbia" onerror="this.src='https://via.placeholder.com/90x90/C8922A/FFF?text=Awa'">
            </div>
            <div class="citation">
                <i class="bi bi-chat-quote-fill"></i>
                "Que la paix et la bénédiction d'Allah soient sur vous."
            </div>
            <div class="loader">
                <div class="loader-dot"></div>
                <div class="loader-dot"></div>
                <div class="loader-dot"></div>
            </div>
            <div class="redirect-text">
                Redirection vers le site dans <span id="countdown">5</span> secondes...
            </div>
        </div>
        <script>
            let timeLeft = 5;
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(() => {
                timeLeft--;
                if (countdownElement) countdownElement.textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = '?splash_done=1';
                }
            }, 1000);
        </script>
    </body>
    </html>
    <?php
    exit();
}

// ============================================
// 4. PAGE D'ACCUEIL
// ============================================
$titre_page = 'Accueil';
$meta_desc  = 'Awa Ka Sugu — Boutique IBA Design & Restaurant Sofia. Mode et cuisine malienne à Bamako.';

require_once 'includes/header.php';
require_once 'includes/navbar.php';

// ============================================
// 6. CONNEXION À LA BASE DE DONNÉES
// ============================================
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

// ============================================
// 7. RÉCUPÉRATION DES NOUVEAUTÉS
// ============================================
$nouveautes = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE est_visible = 1 AND est_nouveau = 1 ORDER BY created_at DESC LIMIT 8");
    $stmt->execute();
    $nouveautes = $stmt->fetchAll();
} catch(PDOException $e) {
    $nouveautes = [];
}

$avis_clients = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.nom as produit_nom, 
               p.image_principale as produit_image
        FROM avis_clients a
        LEFT JOIN produits p ON p.id = a.produit_id
        WHERE a.est_visible = 1 AND a.est_valide = 1
        ORDER BY a.created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $avis_clients = $stmt->fetchAll();
} catch(PDOException $e) {
    $avis_clients = [];
}

$note_moyenne = 0;
$total_avis_count = 0;
try {
    $stmt = $pdo->query("
        SELECT COALESCE(AVG(note), 0) as moyenne, COUNT(*) as total 
        FROM avis_clients 
        WHERE est_visible = 1 AND est_valide = 1
    ");
    $result = $stmt->fetch();
    $note_moyenne = round($result['moyenne'], 1);
    $total_avis_count = $result['total'];
} catch(PDOException $e) {
    $note_moyenne = 0;
    $total_avis_count = 0;
}

$flash = get_message();

// ============================================
// 8. FONCTION POUR L'IMAGE DU PRODUIT
// ============================================
function getProductImageHome($image) {
    if (empty($image)) {
        return 'https://placehold.co/400x500/F5F5F5/C8922A?text=Produit';
    }
    
    $image = trim($image);
    $image_name = pathinfo($image, PATHINFO_FILENAME);
    $extension = pathinfo($image, PATHINFO_EXTENSION);
    
    $dossiers = [
        'uploads/produits/voile/',
        'uploads/produits/pret a porter femme/',
        'uploads/produits/les tallons/',
        'uploads/produits/fermés/',
        'uploads/produits/les turbants/',
        'uploads/produits/les foulards/',
        'uploads/produits/les foullards/',
        'uploads/produits/port-monaie/',
        'uploads/produits/sacs a mains/',
        'uploads/produits/sacs-a-mains/',
        'uploads/produits/ensemble tallons sacs/',
        'uploads/produits/ensemble-tallons-sacs/',
        'uploads/produits/abayas/',
        'uploads/produits/abayas pour enfants/',
        'uploads/produits/abayas-pour-enfants/',
        'uploads/produits/',
    ];
    
    $extensions = ['', '.jpeg', '.jpg', '.png', '.gif', '.webp'];
    
    if (!empty($extension)) {
        $extensions = array_merge([$extension], $extensions);
    }
    
    foreach ($dossiers as $dossier) {
        foreach ($extensions as $ext) {
            $path = $dossier . $image_name . $ext;
            if (file_exists($path)) {
                return $path;
            }
        }
    }
    
    return 'https://placehold.co/400x500/F5F5F5/C8922A?text=' . urlencode($image_name);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Awa Ka Sugu — Accueil</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           AWA KA SUGU — DESIGN PREMIUM / GLASS
           ============================================ */

        :root {
            --gold: #C8922A;
            --gold-light: #E8C870;
            --gold-dark: #8A5E10;
            --gold-glow: rgba(200,146,42,0.15);
            --black: #080808;
            --dark: #0D0D0D;
            --text: #1A1A2E;
            --muted: #8A92A3;
            --glass: rgba(255,255,255,0.06);
            --glass-strong: rgba(255,255,255,0.12);
            --glass-border: rgba(255,255,255,0.14);
            --ease: cubic-bezier(0.25, 0.46, 0.45, 0.94);
            --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', 'Jost', sans-serif; background: #FFFFFF; overflow-x: hidden; color: var(--text); }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.001ms !important;
                scroll-behavior: auto !important;
            }
        }

        @keyframes fadeUp { from { opacity:0; transform:translateY(28px); } to { opacity:1; transform:translateY(0); } }
        @keyframes floatSlow { 0%,100% { transform:translate(0,0); } 50% { transform:translate(-16px,-24px); } }
        @keyframes shimmer { 0%,100% { background-position:200% center; } 50% { background-position:0% center; } }

        /* ====== NOUVELLES ANIMATIONS PHOTO HERO ====== */
        /* Ken Burns : zoom lent + léger drift */
        @keyframes kenburns {
            0%   { transform: scale(1.08) translate(0%, 0%) rotate(0deg); }
            25%  { transform: scale(1.14) translate(-1.5%, -1%) rotate(0.4deg); }
            50%  { transform: scale(1.18) translate(1%, 0.5%) rotate(-0.3deg); }
            75%  { transform: scale(1.13) translate(-0.5%, 1.2%) rotate(0.2deg); }
            100% { transform: scale(1.08) translate(0%, 0%) rotate(0deg); }
        }
        /* Split reveal : la moitié gauche part vers la gauche */
        @keyframes splitLeft {
            0%   { transform: translateX(0) scale(1.08); opacity: 1; }
            60%  { opacity: 1; }
            100% { transform: translateX(-12%) scale(1.02); opacity: 0.92; }
        }
        /* Split reveal : la moitié droite part vers la droite */
        @keyframes splitRight {
            0%   { transform: translateX(0) scale(1.08); opacity: 1; }
            60%  { opacity: 1; }
            100% { transform: translateX(12%) scale(1.02); opacity: 0.92; }
        }
        /* Reflet lumineux qui traverse la photo */
        @keyframes sheenSlide {
            0%   { transform: translateX(-150%) skewX(-18deg); opacity: 0; }
            15%  { opacity: 1; }
            60%  { opacity: 0.8; }
            100% { transform: translateX(250%) skewX(-18deg); opacity: 0; }
        }
        /* Léger balancement horizontal (respiration) */
        @keyframes breathe {
            0%, 100% { transform: translateY(0) scale(1); }
            50%      { transform: translateY(-6px) scale(1.005); }
        }
        /* Grain animé */
        @keyframes grainShift {
            0%,100% { transform: translate(0,0); }
            20%     { transform: translate(-1%, 1%); }
            40%     { transform: translate(1%, -1%); }
            60%     { transform: translate(-1%, -1%); }
            80%     { transform: translate(1%, 1%); }
        }

        .reveal { opacity: 0; transform: translateY(40px); transition: opacity 0.9s var(--ease), transform 0.9s var(--ease); will-change: transform, opacity; }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .reveal-left { opacity: 0; transform: translateX(-50px); transition: opacity 0.9s var(--ease), transform 0.9s var(--ease); will-change: transform, opacity; }
        .reveal-left.visible { opacity: 1; transform: translateX(0); }
        .reveal-right { opacity: 0; transform: translateX(50px); transition: opacity 0.9s var(--ease), transform 0.9s var(--ease); will-change: transform, opacity; }
        .reveal-right.visible { opacity: 1; transform: translateX(0); }
        .reveal-scale { opacity: 0; transform: scale(0.92); transition: opacity 0.9s var(--ease), transform 0.9s var(--ease); will-change: transform, opacity; }
        .reveal-scale.visible { opacity: 1; transform: scale(1); }

        .fade-item { opacity: 0; animation: fadeUp 0.9s var(--ease) both; animation-delay: var(--d, 0s); }

        /* ============================================
           HERO — PHOTO ANIMÉE (split + kenburns + sheen)
           ============================================ */
        .hero-lux {
            position: relative;
            min-height: 96vh;
            background: #050403;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
            padding: 70px 0 130px;
        }
        .hero-bg-photo {
            position: absolute;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        /* Conteneur des deux moitiés (split reveal) */
        .hero-split {
            position: absolute;
            inset: 0;
            display: flex;
            z-index: 0;
            will-change: transform;
        }
        .hero-split-half {
            position: relative;
            width: 50.5%;
            height: 100%;
            overflow: hidden;
            will-change: transform;
            backface-visibility: hidden;
        }
        .hero-split-half.left {
            animation: splitLeft 1.6s var(--ease) 0.15s both;
        }
        .hero-split-half.right {
            animation: splitRight 1.6s var(--ease) 0.15s both;
        }
        .hero-split-half img {
            position: absolute;
            top: 0;
            width: 200%;
            height: 100%;
            object-fit: cover;
            object-position: center right;
            display: block;
            animation: kenburns 22s ease-in-out infinite;
            will-change: transform;
        }
        .hero-split-half.left img {
            left: 0;
        }
        .hero-split-half.right img {
            right: 0;
        }

        /* Reflet lumineux qui traverse */
        .hero-sheen {
            position: absolute;
            inset: 0;
            z-index: 2;
            pointer-events: none;
            overflow: hidden;
        }
        .hero-sheen::before {
            content: '';
            position: absolute;
            top: -20%;
            left: 0;
            width: 30%;
            height: 140%;
            background: linear-gradient(
                90deg,
                transparent 0%,
                rgba(255, 255, 255, 0.05) 30%,
                rgba(232, 200, 112, 0.22) 50%,
                rgba(255, 255, 255, 0.05) 70%,
                transparent 100%
            );
            filter: blur(8px);
            animation: sheenSlide 6s ease-in-out 2s infinite;
            will-change: transform;
        }

        /* Grain subtil animé */
        .hero-grain {
            position: absolute;
            inset: -10%;
            z-index: 2;
            pointer-events: none;
            opacity: 0.06;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            animation: grainShift 1.2s steps(2) infinite;
        }

        .hero-bg-overlay {
            position: absolute;
            inset: 0;
            z-index: 3;
            background: linear-gradient(105deg, rgba(5,4,3,0.92) 0%, rgba(5,4,3,0.75) 35%, rgba(5,4,3,0.35) 60%, rgba(5,4,3,0.15) 100%);
        }
        .hero-bg-overlay::after {
            content: '';
            position: absolute;
            left: 0; right: 0; bottom: 0;
            height: 35%;
            background: linear-gradient(to top, #050403, transparent);
        }

        /* Lignes dorées décoratives animées */
        .hero-line {
            position: absolute;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(200,146,42,0.6), transparent);
            z-index: 4;
            transform-origin: left center;
            animation: lineGrow 2.4s var(--ease) 0.5s both;
            pointer-events: none;
        }
        @keyframes lineGrow {
            0% { transform: scaleX(0); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: scaleX(1); opacity: 0.6; }
        }
        .hero-line.l1 { top: 22%; left: 6%; width: 180px; }
        .hero-line.l2 { bottom: 32%; left: 6%; width: 120px; animation-delay: 0.9s; }
        .hero-line.l3 { top: 40%; right: 8%; width: 100px; animation-delay: 1.3s; }

        /* Trait vertical doré à gauche du texte */
        .hero-vline {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, transparent, var(--gold), transparent);
            z-index: 4;
            animation: glowLine 3s ease-in-out infinite;
            transform-origin: top center;
        }
        @keyframes glowLine {
            0%, 100% { opacity: 0.3; transform: scaleY(0.6); }
            50% { opacity: 1; transform: scaleY(1); }
        }

        .hero-lux-inner {
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            padding: 0 60px;
            position: relative;
            z-index: 5;
        }
        .hero-lux-text {
            max-width: 620px;
            position: relative;
            z-index: 5;
            padding-left: 32px;
        }
        .hero-lux-text h1,
        .hero-lux-text p,
        .glass-badge {
            text-shadow: 0 4px 24px rgba(0,0,0,0.55);
        }

        .glass-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 20px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--gold-light);
            background: var(--glass);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            margin-bottom: 24px;
            animation: slideRight 0.9s var(--ease) 0.1s both;
        }
        @keyframes slideRight {
            0% { opacity: 0; transform: translateX(-80px); }
            100% { opacity: 1; transform: translateX(0); }
        }
        .hero-lux-text h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.6rem, 4.6vw, 4.4rem);
            font-weight: 700;
            color: #FFFFFF;
            line-height: 1.08;
            margin-bottom: 20px;
            animation: fadeUp 1s var(--ease) 0.3s both;
        }
        .hero-lux-text h1 .accent {
            display: block;
            font-style: italic;
            background: linear-gradient(120deg, var(--gold-dark), var(--gold), var(--gold-light), var(--gold));
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 6s linear infinite;
        }
        .hero-lux-text p {
            font-size: 1.05rem;
            color: rgba(255,255,255,0.65);
            line-height: 1.8;
            margin-bottom: 34px;
            max-width: 460px;
            animation: fadeUp 1s var(--ease) 0.5s both;
        }
        .hero-lux-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            animation: fadeUp 1s var(--ease) 0.7s both;
        }
        .btn-glass-primary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            color: #0A0804;
            padding: 16px 36px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            transition: transform 0.35s var(--ease-spring), box-shadow 0.35s var(--ease);
            box-shadow: 0 10px 30px rgba(200,146,42,0.25);
        }
        .btn-glass-primary:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 16px 40px rgba(200,146,42,0.4); }
        .btn-glass-outline {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            color: #fff;
            padding: 16px 32px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            transition: transform 0.35s var(--ease-spring), background 0.35s var(--ease), border-color 0.35s var(--ease);
        }
        .btn-glass-outline:hover { transform: translateY(-4px) scale(1.02); background: var(--glass-strong); border-color: rgba(200,146,42,0.5); color: #fff; }

        .hero-dock {
            position: relative;
            z-index: 5;
            max-width: 1000px;
            margin: 50px auto 0;
            padding: 0 40px;
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            animation: fadeUp 1s var(--ease) 0.9s both;
        }
        .dock-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 26px;
            background: var(--glass-strong);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 50px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.35);
            transition: transform 0.3s var(--ease);
        }
        .dock-pill:hover { transform: translateY(-4px); }
        .dock-pill i { color: var(--gold); font-size: 1.05rem; }
        .dock-pill span { color: rgba(255,255,255,0.85); font-size: 0.8rem; font-weight: 600; }

        .hero-wave { position: absolute; left: 0; bottom: -1px; width: 100%; height: 90px; z-index: 4; }

        /* ============================================
           SECTIONS COMMUNES
           ============================================ */
        .section { padding: 80px 0; }
        .container-custom { max-width: 1300px; margin: 0 auto; padding: 0 44px; }

        .section-header { text-align: center; margin-bottom: 52px; }
        .section-eyebrow {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 12px;
            padding: 0 36px;
            position: relative;
        }
        .section-eyebrow::before,
        .section-eyebrow::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 24px;
            height: 1px;
            background: var(--gold);
        }
        .section-eyebrow::before { right: 0; transform: scaleX(-1); }
        .section-eyebrow::after { left: 0; }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: 700;
            color: var(--text);
            line-height: 1.2;
        }
        .section-title em { font-style: italic; color: var(--gold); }
        .section-rule {
            width: 0;
            height: 2.5px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            margin: 0 auto;
            border-radius: 4px;
            transition: width 0.9s var(--ease);
        }
        .section-rule.visible { width: 56px; }

        /* ============================================
           NOS ESPACES
           ============================================ */
        .spaces-section { background: #FFFFFF; padding-bottom: 60px; }
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
        .service-card {
            background: #fff;
            border: 1px solid #F0F0F5;
            border-radius: 24px;
            padding: 36px 24px;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: transform 0.5s var(--ease), box-shadow 0.5s var(--ease), border-color 0.5s var(--ease);
            position: relative;
        }
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(200,146,42,0.06);
            border-color: rgba(200,146,42,0.2);
        }
        .service-icon {
            width: 64px; height: 64px;
            border-radius: 18px;
            background: rgba(200,146,42,0.06);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px;
            transition: all 0.5s var(--ease);
        }
        .service-card:hover .service-icon {
            background: var(--gold);
            transform: rotate(4deg) scale(1.06);
        }
        .service-icon i { font-size: 1.8rem; color: var(--gold); transition: color 0.3s; }
        .service-card:hover .service-icon i { color: #fff; }
        .service-card h3 { font-size: 1rem; font-weight: 700; color: var(--text); margin-bottom: 6px; }
        .service-card p { font-size: 0.8rem; color: var(--muted); line-height: 1.6; }

        /* ============================================
           PRODUITS / NOUVEAUTÉS — GRILLE 2 COLONNES MÊME SUR MOBILE
           ============================================ */
        .products-section { background: #F8F8FA; }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
        .product-card {
            background: #FFFFFF;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #EEF0F4;
            transition: transform 0.5s var(--ease), box-shadow 0.5s var(--ease), border-color 0.5s var(--ease);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 24px 50px rgba(0,0,0,0.08);
            border-color: rgba(200,146,42,0.18);
        }
        .product-card .image {
            position: relative;
            height: 250px;
            overflow: hidden;
            background: #F4F5F8;
            flex-shrink: 0;
        }
        .product-card .image::after {
            content: '';
            position: absolute;
            top: 0; left: -60%;
            width: 40%; height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255,255,255,0.55), transparent);
            transform: skewX(-15deg);
            pointer-events: none;
            transition: left 0.85s var(--ease);
        }
        .product-card:hover .image::after { left: 130%; }
        .product-card .image img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.7s var(--ease);
        }
        .product-card:hover .image img { transform: scale(1.06); }
        .product-card .image .badge {
            position: absolute;
            top: 14px; left: 14px;
            background: var(--gold);
            color: #fff;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(200,146,42,0.3);
        }
        .product-card .info {
            padding: 18px 20px 22px;
            background: #FFFFFF;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .product-card .info .name {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-card .info .price {
            font-family: 'Playfair Display', serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 12px;
        }
        .product-card .info .btn-cart {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--black);
            color: #fff;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.78rem;
            text-decoration: none;
            transition: all 0.3s var(--ease);
            border: 1px solid var(--black);
            width: 100%;
            margin-top: auto;
        }
        .product-card .info .btn-cart:hover {
            background: var(--gold);
            color: #0A0804;
            border-color: var(--gold);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(200,146,42,0.2);
        }

        .btn-view-all {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1.5px solid var(--gold);
            color: var(--gold);
            padding: 12px 34px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s var(--ease);
        }
        .btn-view-all:hover {
            background: var(--gold);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(200,146,42,0.15);
        }

        /* ============================================
           POURQUOI
           ============================================ */
        .why-section { background: #F9F8F6; }
        .why-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 22px;
        }
        .why-card {
            background: #fff;
            border-radius: 24px;
            padding: 34px 24px;
            text-align: center;
            border: 1px solid rgba(200,146,42,0.04);
            transition: all 0.5s var(--ease);
        }
        .why-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(200,146,42,0.06);
            border-color: rgba(200,146,42,0.12);
        }
        .why-icon {
            width: 68px; height: 68px;
            border-radius: 20px;
            background: rgba(200,146,42,0.05);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            transition: all 0.5s var(--ease);
        }
        .why-card:hover .why-icon {
            background: var(--gold);
            transform: rotate(4deg) scale(1.06);
        }
        .why-icon i { font-size: 1.8rem; color: var(--gold); transition: color 0.3s; }
        .why-card:hover .why-icon i { color: #fff; }
        .why-card h4 { font-size: 1rem; font-weight: 700; color: var(--text); margin-bottom: 6px; }
        .why-card p { font-size: 0.8rem; color: var(--muted); line-height: 1.6; }

        /* ============================================
           AVIS CLIENTS — PREMIUM (2 colonnes même sur mobile)
           ============================================ */
        .avis-section {
            position: relative;
            background: linear-gradient(160deg, #FFFDF9 0%, #FFF7EA 55%, #FFFDF9 100%);
            padding: 90px 0;
            overflow: hidden;
        }
        .avis-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            will-change: transform;
        }
        .avis-orb.o1 { width: 420px; height: 420px; top: -180px; left: -140px; background: radial-gradient(circle, rgba(200,146,42,0.14), transparent 70%); animation: floatSlow 15s ease-in-out infinite; }
        .avis-orb.o2 { width: 340px; height: 340px; bottom: -160px; right: -120px; background: radial-gradient(circle, rgba(200,146,42,0.1), transparent 70%); animation: floatSlow 18s ease-in-out infinite reverse; }

        .rating-hero {
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 22px;
            margin: 22px auto 0;
            padding: 20px 36px;
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(200,146,42,0.18);
            border-radius: 26px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 20px 50px rgba(200,146,42,0.08);
        }
        .rating-hero .rating-number {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--gold-dark), var(--gold), var(--gold-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }
        .rating-hero .rating-details { text-align: left; }
        .rating-hero .rating-stars { display: flex; gap: 3px; margin-bottom: 6px; }
        .rating-hero .rating-stars i { font-size: 1rem; }
        .rating-hero .rating-count { font-size: 0.78rem; color: var(--muted); font-weight: 600; }

        .avis-grid {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            margin-top: 44px;
        }
        .avis-card {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 34px 32px;
            border: 1px solid #F1E7D4;
            transition: transform 0.45s var(--ease), box-shadow 0.45s var(--ease), border-color 0.45s var(--ease);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .avis-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 26px 55px rgba(200,146,42,0.12);
            border-color: rgba(200,146,42,0.4);
        }
        .avis-card .quote-icon {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 4.2rem;
            color: rgba(200,146,42,0.07);
            font-family: 'Playfair Display', serif;
            line-height: 1;
        }
        .avis-header { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
        .avis-avatar {
            width: 50px; height: 50px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem; font-weight: 700; color: #fff;
            flex-shrink: 0;
            font-family: 'Playfair Display', serif;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
            border: 2px solid #fff;
            outline: 1px solid rgba(200,146,42,0.25);
        }
        .avis-nom { font-weight: 700; color: var(--text); font-size: 0.95rem; font-family: 'Playfair Display', serif; }
        .avis-date { font-size: 0.65rem; color: var(--muted); margin-top: 2px; }
        .avis-stars { display: flex; gap: 3px; margin-bottom: 14px; }
        .avis-stars i { font-size: 0.88rem; }
        .avis-text { font-size: 0.9rem; color: #4A5568; line-height: 1.75; font-style: italic; position: relative; z-index: 1; }

        /* ============================================
           LOGIN — GLASS PREMIUM
           ============================================ */
        .login-section {
            background: radial-gradient(ellipse at 15% 15%, #1c1710 0%, #0a0806 55%, #050403 100%);
            border-radius: 32px;
            padding: 60px 48px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--glass-border);
            box-shadow: 0 30px 70px rgba(0,0,0,0.35);
        }
        .login-section .login-orb {
            position: absolute;
            width: 320px; height: 320px;
            border-radius: 50%;
            filter: blur(70px);
            pointer-events: none;
            background: radial-gradient(circle, rgba(200,146,42,0.25), transparent 70%);
            top: -120px; right: -80px;
            animation: floatSlow 14s ease-in-out infinite;
        }
        .login-section h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.9rem;
            color: #fff;
            margin-bottom: 8px;
            position: relative; z-index: 1;
        }
        .login-section p { color: rgba(255,255,255,0.4); margin-bottom: 30px; position: relative; z-index: 1; }

        .login-form {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            position: relative; z-index: 1;
            max-width: 440px;
            margin: 0 auto;
        }
        .login-form .input-group { width: 100%; }
        .login-form input {
            width: 100%;
            padding: 16px 22px;
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: border-color 0.3s, background 0.3s, box-shadow 0.3s;
            background: var(--glass);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #fff;
            font-size: 0.9rem;
        }
        .login-form input::placeholder { color: rgba(255,255,255,0.4); }
        .login-form input:focus {
            border-color: var(--gold);
            background: var(--glass-strong);
            box-shadow: 0 0 0 4px rgba(200,146,42,0.1);
        }
        .login-form input:disabled { opacity: 0.3; cursor: not-allowed; }
        .login-form button {
            width: 100%;
            padding: 16px 36px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            color: #0A0804;
            border: none;
            border-radius: 50px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.35s var(--ease-spring), box-shadow 0.35s var(--ease);
            font-size: 0.95rem;
            box-shadow: 0 10px 26px rgba(200,146,42,0.25);
        }
        .login-form button:hover { transform: translateY(-3px); box-shadow: 0 14px 34px rgba(200,146,42,0.4); }
        .login-form button:disabled { opacity: 0.4; cursor: not-allowed; transform: none; box-shadow: none; }

        .login-error {
            background: rgba(231,76,60,0.08);
            color: #FF8A7A;
            padding: 11px 18px;
            border-radius: 14px;
            margin-bottom: 8px;
            border: 1px solid rgba(231,76,60,0.2);
            font-size: 0.82rem;
            position: relative; z-index: 1;
            max-width: 440px;
            margin-left: auto; margin-right: auto;
        }
        .blocage-msg {
            background: rgba(231,76,60,0.08);
            border: 1px solid rgba(231,76,60,0.2);
            color: #FF8A7A;
            padding: 13px 18px;
            border-radius: 14px;
            font-size: 0.85rem;
            margin-bottom: 16px;
            display: flex; align-items: flex-start; gap: 10px;
            font-weight: 600;
            position: relative; z-index: 1;
            text-align: left;
            max-width: 440px;
            margin-left: auto; margin-right: auto;
        }
        .login-already {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 22px;
            padding: 30px;
            position: relative; z-index: 1;
            max-width: 440px;
            margin: 0 auto;
        }
        .login-already .status-icon {
            width: 52px; height: 52px;
            border-radius: 50%;
            background: rgba(46,204,113,0.12);
            border: 1px solid rgba(46,204,113,0.3);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
        }
        .login-already .status-icon i { color: #2ECC71; font-size: 1.3rem; }
        .login-already p { color: rgba(255,255,255,0.65); margin-bottom: 18px; font-size: 0.9rem; }
        .btn-dashboard {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 13px 30px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            color: #0A0804;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            transition: transform 0.35s var(--ease-spring), box-shadow 0.35s var(--ease);
            box-shadow: 0 10px 26px rgba(200,146,42,0.25);
        }
        .btn-dashboard:hover { transform: translateY(-3px); box-shadow: 0 14px 34px rgba(200,146,42,0.4); }
        .logout-link {
            color: rgba(255,255,255,0.3);
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
            margin-top: 14px;
            transition: color 0.3s;
        }
        .logout-link:hover { color: rgba(255,255,255,0.6); }
        .login-secure {
            font-size: 0.68rem;
            color: rgba(255,255,255,0.28);
            margin-top: 18px;
            position: relative; z-index: 1;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .login-secure i { color: var(--gold); }
        .login-secure .blocked-note { color: #FF8A7A; display: flex; align-items: center; gap: 5px; width: 100%; justify-content: center; margin-top: 4px; }

        /* ============================================
           AWA LEGACY
           ============================================ */
        .awa-legacy-section {
            padding: 100px 0;
            background: linear-gradient(160deg, #FFF9F0 0%, #FDF5E6 50%, #FFF9F0 100%);
        }
        .legacy-wrapper {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 60px;
            align-items: center;
        }
        .legacy-photo-card { position: relative; }
        .legacy-photo-frame {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            padding: 6px;
            border-radius: 28px;
            box-shadow: 0 24px 48px rgba(200,146,42,0.12);
        }
        .legacy-photo-frame img { width: 100%; border-radius: 24px; display: block; transition: transform 0.5s; }
        .legacy-photo-frame:hover img { transform: scale(1.02); }
        .legacy-photo-badge {
            position: absolute;
            bottom: -16px; right: 20px;
            background: #fff;
            padding: 10px 20px;
            border-radius: 50px;
            display: flex; align-items: center; gap: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            border: 1px solid rgba(200,146,42,0.08);
        }
        .legacy-photo-badge i { font-size: 1.2rem; color: var(--gold); }
        .legacy-tag {
            display: inline-block;
            background: rgba(200,146,42,0.08);
            color: var(--gold);
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 5px 14px;
            border-radius: 30px;
            margin-bottom: 16px;
        }
        .legacy-content h2 { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 700; color: var(--text); margin-bottom: 16px; }
        .legacy-content h2 span { color: var(--gold); font-style: italic; }
        .legacy-intro { font-size: 0.95rem; color: #4A5568; line-height: 1.7; margin-bottom: 24px; }
        .legacy-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .legacy-stat {
            background: #fff;
            padding: 14px 12px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid rgba(200,146,42,0.06);
            transition: all 0.3s;
        }
        .legacy-stat:hover { transform: translateY(-3px); border-color: rgba(200,146,42,0.15); }
        .legacy-stat-number { font-family: 'Playfair Display', serif; font-size: 1.3rem; font-weight: 800; color: var(--gold); }
        .legacy-stat-label { font-size: 0.62rem; color: #718096; margin-top: 4px; }
        .legacy-actions {
            background: #fff;
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(200,146,42,0.06);
        }
        .legacy-actions h3 { font-size: 0.95rem; font-weight: 700; color: var(--text); margin-bottom: 14px; display: flex; align-items: center; gap: 10px; }
        .legacy-actions h3 i { color: var(--gold); }
        .actions-list { display: flex; flex-direction: column; gap: 10px; }
        .action-item { display: flex; align-items: flex-start; gap: 10px; }
        .action-item i { color: var(--gold); font-size: 0.9rem; margin-top: 2px; }
        .action-item p { font-size: 0.8rem; color: #4A5568; line-height: 1.5; }
        .action-item strong { color: var(--text); }
        .legacy-quote {
            background: linear-gradient(135deg, rgba(200,146,42,0.04), rgba(200,146,42,0.01));
            border-left: 3px solid var(--gold);
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 22px;
        }
        .legacy-quote i { color: var(--gold); font-size: 1.1rem; opacity: 0.4; margin-bottom: 6px; display: block; }
        .legacy-quote p { font-style: italic; color: #2D3748; line-height: 1.6; margin-bottom: 6px; }
        .legacy-quote span { font-size: 0.7rem; color: #A0AEC0; }
        .legacy-social { display: flex; gap: 10px; flex-wrap: wrap; }
        .social-icon-link {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 18px;
            background: #fff;
            border-radius: 50px;
            text-decoration: none;
            border: 1px solid rgba(200,146,42,0.08);
            flex: 1; min-width: 100px;
            justify-content: center;
            transition: all 0.3s;
        }
        .social-icon-link i { font-size: 1rem; }
        .social-icon-link span { font-size: 0.72rem; font-weight: 600; }
        .social-icon-link.tiktok { color: #000; }
        .social-icon-link.tiktok:hover { background: #000; color: #fff; }
        .social-icon-link.instagram { color: #E4405F; }
        .social-icon-link.instagram:hover { background: #E4405F; color: #fff; }
        .social-icon-link.facebook { color: #1877F2; }
        .social-icon-link.facebook:hover { background: #1877F2; color: #fff; }
        .social-icon-link.snapchat { color: #FFBF00; }
        .social-icon-link.snapchat:hover { background: #FFFC00; color: #000; }
        .social-icon-link:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.04); }

        /* ============================================
           RESPONSIVE UNIFIÉ
           ============================================ */
        @media (max-width: 1100px) {
            .cards-grid, .why-grid { grid-template-columns: repeat(2, 1fr); }
            .legacy-wrapper { grid-template-columns: 1fr; gap: 40px; }
            .legacy-photo-card { max-width: 420px; margin: 0 auto; }
        }
        @media (max-width: 992px) {
            .avis-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 860px) {
            .hero-lux-inner { text-align: center; padding: 0 32px; }
            .hero-lux-text { margin: 0 auto; padding-left: 0; }
            .hero-lux-text p { margin-left: auto; margin-right: auto; }
            .hero-lux-actions { justify-content: center; }
            .hero-split-half img { object-position: center; }
            .hero-bg-overlay { background: linear-gradient(180deg, rgba(5,4,3,0.75) 0%, rgba(5,4,3,0.55) 50%, rgba(5,4,3,0.85) 100%); }
            .hero-vline { display: none; }
            .hero-line.l1 { left: 50%; transform: translateX(-50%); width: 120px; }
            .hero-line.l2 { display: none; }
            .hero-line.l3 { display: none; }
            .login-section { padding: 40px 24px; }
            .hero-dock { gap: 10px; }
        }
        @media (max-width: 700px) {
            .cards-grid, .why-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
            .products-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
            .container-custom { padding: 0 20px; }
            .section { padding: 60px 0; }
            .section-title { font-size: 1.6rem; }
            .login-section { padding: 32px 20px; }
            .legacy-social { flex-direction: column; }
            .avis-grid { grid-template-columns: repeat(2, 1fr); max-width: 100%; margin: 24px auto 0; gap: 14px; }
            .avis-card { padding: 22px 16px; }
            .avis-card .quote-icon { font-size: 2.6rem; }
            .avis-header { flex-wrap: wrap; }
            .rating-hero { flex-direction: column; text-align: center; padding: 18px 24px; gap: 10px; }
            .rating-hero .rating-number { font-size: 2.2rem; }
            .rating-hero .rating-details { text-align: center; }
            .rating-hero .rating-stars { justify-content: center; }
            .product-card .image { height: 180px; }
            .product-card .info { padding: 14px 14px 18px; }
            .product-card .info .name { font-size: 0.82rem; }
            .product-card .info .price { font-size: 0.92rem; }
            .product-card .info .btn-cart { font-size: 0.72rem; padding: 8px 14px; }
            .service-card, .why-card { padding: 22px 14px; }
            .service-icon, .why-icon { width: 52px; height: 52px; }
            .hero-lux { padding-bottom: 90px; }
            .dock-pill span { display: none; }
            .dock-pill { padding: 12px; }
        }
        @media (max-width: 480px) {
            .hero-lux-inner { padding: 0 16px; }
            .hero-lux-text h1 { font-size: 2rem; }
            .hero-lux-actions { flex-direction: column; align-items: stretch; }
            .btn-glass-primary, .btn-glass-outline { justify-content: center; }
            .product-card .image { height: 160px; }
            .product-card .info { padding: 12px 10px 14px; }
            .product-card .info .name { font-size: 0.78rem; }
            .product-card .info .price { font-size: 0.85rem; }
            .product-card .info .btn-cart { font-size: 0.68rem; padding: 7px 10px; }
            .avis-grid { grid-template-columns: repeat(2, 1fr); max-width: 100%; gap: 10px; }
            .avis-card { padding: 16px 12px; }
            .avis-card .quote-icon { font-size: 2rem; top: 4px; right: 10px; }
            .avis-nom { font-size: 0.8rem; }
            .avis-text { font-size: 0.78rem; }
            .rating-hero { padding: 16px 18px; }
        }
    </style>
</head>
<body>

<!-- FLASH MESSAGE -->
<?php if ($flash): ?>
    <div style="background: #D4EDDA; color: #0A3622; padding: 12px 20px; text-align: center; font-size: 0.9rem;">
        <?= $flash['texte'] ?>
    </div>
<?php endif; ?>

<!-- ============================================
     HERO — PHOTO ANIMÉE (split reveal + kenburns + sheen + grain)
     ============================================ -->
<section class="hero-lux">
    <div class="hero-bg-photo">

        <!-- Split reveal : la photo se divise en 2 moitiés qui s'écartent -->
        <div class="hero-split">
            <div class="hero-split-half left">
                <img src="assets/images/iba%20design.jpeg" alt="Boutique IBA Design"
                     onerror="this.src='https://placehold.co/1600x1000/050403/C8922A?text=IBA+Design'">
            </div>
            <div class="hero-split-half right">
                <img src="assets/images/iba%20design.jpeg" alt=""
                     onerror="this.src='https://placehold.co/1600x1000/050403/C8922A?text=IBA+Design'">
            </div>
        </div>

        <!-- Reflet lumineux qui traverse la photo -->
        <div class="hero-sheen"></div>

        <!-- Grain subtil animé -->
        <div class="hero-grain"></div>

    </div>

    <div class="hero-bg-overlay"></div>

    <!-- Lignes dorées décoratives animées -->
    <span class="hero-line l1"></span>
    <span class="hero-line l2"></span>
    <span class="hero-line l3"></span>

    <div class="hero-lux-inner">

        <div class="hero-lux-text">
            <span class="hero-vline"></span>
            <span class="glass-badge"><i class="bi bi-stars"></i> Awa Ka Sugu · IBA Design</span>

            <h1>
                L'élégance
                <span class="accent">à la malienne</span>
            </h1>

            <p>
                Découvrez IBA Design, la référence de la mode modeste au Mali.
                Robes, abayas, foulards et accessoires d'exception.
            </p>

            <div class="hero-lux-actions">
                <a href="boutique/catalogue.php" class="btn-glass-primary">
                    <i class="bi bi-bag-heart"></i> Explorer
                </a>
                <a href="restaurant/menu.php" class="btn-glass-outline">
                    <i class="bi bi-cup-hot"></i> Sofia
                </a>
            </div>
        </div>

    </div>

    <div class="hero-dock">
        <div class="dock-pill"><i class="bi bi-truck"></i><span>Livraison Bamako</span></div>
        <div class="dock-pill"><i class="bi bi-shield-check"></i><span>Paiement sécurisé</span></div>
        <div class="dock-pill"><i class="bi bi-award"></i><span>Qualité garantie</span></div>
    </div>

    <svg class="hero-wave" viewBox="0 0 1440 120" preserveAspectRatio="none">
        <path d="M0,60 C240,120 480,0 720,40 C960,80 1200,20 1440,60 L1440,120 L0,120 Z" fill="#FFFFFF"></path>
    </svg>
</section>

<!-- ============================================
     NOS ESPACES
     ============================================ -->
<section class="section spaces-section reveal">
    <div class="container-custom">
        <div class="section-header">
            <span class="section-eyebrow">Nos espaces</span>
            <h2 class="section-title">Tout ce qu'<em>Awa</em> vous offre</h2>
            <div class="section-rule"></div>
        </div>
        <div class="cards-grid">
            <a href="boutique/catalogue.php" class="service-card reveal" style="transition-delay:0.05s;">
                <div class="service-icon"><i class="bi bi-bag-heart"></i></div>
                <h3>IBA Design</h3>
                <p>Mode modeste, robes, abayas, foulards et accessoires</p>
            </a>
            <a href="restaurant/menu.php" class="service-card reveal" style="transition-delay:0.10s;">
                <div class="service-icon"><i class="bi bi-cup-hot"></i></div>
                <h3>Restaurant Sofia</h3>
                <p>Cuisine malienne authentique, plats faits maison</p>
            </a>
            <a href="boutique/promotions.php" class="service-card reveal" style="transition-delay:0.15s;">
                <div class="service-icon"><i class="bi bi-percent"></i></div>
                <h3>Promotions</h3>
                <p>Offres spéciales et réductions exclusives</p>
            </a>
            <a href="boutique/suivi.php" class="service-card reveal" style="transition-delay:0.20s;">
                <div class="service-icon"><i class="bi bi-truck"></i></div>
                <h3>Suivi commande</h3>
                <p>Suivez votre commande en temps réel</p>
            </a>
        </div>
    </div>
</section>

<!-- ============================================
     NOUVEAUTÉS — GRILLE 2 COLONNES MÊME SUR MOBILE
     ============================================ -->
<section class="section products-section reveal">
    <div class="container-custom">
        <div class="section-header">
            <span class="section-eyebrow">Dernières créations</span>
            <h2 class="section-title">Nouvelles <em>arrivées</em></h2>
            <div class="section-rule"></div>
        </div>
        
        <?php if (!empty($nouveautes)): ?>
            <div class="products-grid">
                <?php foreach ($nouveautes as $index => $p): 
                    $image_produit = getProductImageHome($p['image_principale'] ?? '');
                    $prix_affiché = $p['prix'];
                    if (!empty($p['prix_promo']) && $p['prix_promo'] > 0 && $p['prix_promo'] < $p['prix']) {
                        $prix_affiché = $p['prix_promo'];
                    }
                    $delay = 0.05 * ($index + 1);
                ?>
                <div class="product-card reveal" style="transition-delay:<?= $delay ?>s;">
                    <div class="image">
                        <img src="<?= htmlspecialchars($image_produit) ?>" 
                             alt="<?= htmlspecialchars($p['nom']) ?>"
                             loading="lazy"
                             onerror="this.src='https://placehold.co/400x500/F5F5F5/C8922A?text=<?= urlencode($p['nom']) ?>'">
                        <div class="badge">Nouveau</div>
                    </div>
                    <div class="info">
                        <div class="name"><?= htmlspecialchars($p['nom']) ?></div>
                        <div class="price"><?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA</div>
                        <a href="boutique/produit.php?id=<?= $p['id'] ?>" class="btn-cart">
                            <i class="bi bi-bag-plus"></i> Acheter
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center" style="margin-top: 44px;">
                <a href="boutique/nouveautes.php" class="btn-view-all">
                    <i class="bi bi-eye"></i> Voir toutes les nouveautés
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 0;">
                <p style="color: #8A99AA; font-size: 1.1rem;">Aucune nouveauté pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================
     POURQUOI CHOISIR
     ============================================ -->
<section class="section why-section reveal">
    <div class="container-custom">
        <div class="section-header">
            <span class="section-eyebrow">Nos engagements</span>
            <h2 class="section-title">Pourquoi choisir <em>Awa Ka Sugu</em> ?</h2>
            <div class="section-rule"></div>
        </div>
        <div class="why-grid">
            <div class="why-card reveal" style="transition-delay:0.05s;">
                <div class="why-icon"><i class="bi bi-shield-check"></i></div>
                <h4>Paiement sécurisé</h4>
                <p>Orange Money & Wave acceptés</p>
            </div>
            <div class="why-card reveal" style="transition-delay:0.10s;">
                <div class="why-icon"><i class="bi bi-truck"></i></div>
                <h4>Livraison Bamako</h4>
                <p>Livraison rapide partout à Bamako</p>
            </div>
            <div class="why-card reveal" style="transition-delay:0.15s;">
                <div class="why-icon"><i class="bi bi-award"></i></div>
                <h4>Qualité garantie</h4>
                <p>Sélectionné par Awa Doumbia</p>
            </div>
            <div class="why-card reveal" style="transition-delay:0.20s;">
                <div class="why-icon"><i class="bi bi-headset"></i></div>
                <h4>Service client</h4>
                <p>Support réactif 7j/7</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     AVIS CLIENTS — GRILLE 2 COLONNES MÊME SUR MOBILE
     ============================================ -->
<section class="avis-section reveal">
    <span class="avis-orb o1"></span>
    <span class="avis-orb o2"></span>
    <div class="container-custom">
        <div class="section-header">
            <span class="section-eyebrow">Témoignages</span>
            <h2 class="section-title">Ce que disent nos <em>clients</em></h2>
            <div class="section-rule"></div>
            <?php if ($total_avis_count > 0): ?>
            <div class="rating-hero reveal-scale">
                <span class="rating-number"><?= number_format($note_moyenne, 1) ?></span>
                <div class="rating-details">
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?= $i <= round($note_moyenne) ? '-fill' : '' ?>" style="color:<?= $i <= round($note_moyenne) ? '#F1C40F' : '#E0E0E0' ?>;"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="rating-count"><?= $total_avis_count ?> avis vérifiés</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($avis_clients)): ?>
        <div class="avis-grid">
            <?php foreach ($avis_clients as $avis): 
                $initiale = strtoupper(mb_substr($avis['nom_client'] ?? 'C', 0, 1));
                $date_avis = date('d/m/Y', strtotime($avis['created_at']));
                $couleurs_avatar = ['#C8922A', '#8E44AD', '#2980B9', '#27AE60', '#E67E22'];
                $couleur_avatar = $couleurs_avatar[array_rand($couleurs_avatar)];
            ?>
            <div class="avis-card reveal" style="transition-delay:<?= 0.05 * (($avis['id'] ?? 0) % 3 + 1) ?>s;">
                <div class="quote-icon">&rdquo;</div>
                <div class="avis-header">
                    <div class="avis-avatar" style="background:<?= $couleur_avatar ?>;">
                        <?= $initiale ?>
                    </div>
                    <div>
                        <div class="avis-nom"><?= htmlspecialchars($avis['nom_client'] ?? 'Client') ?></div>
                        <div class="avis-date"><?= $date_avis ?></div>
                    </div>
                </div>
                <div class="avis-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi bi-star<?= $i <= $avis['note'] ? '-fill' : '' ?>" style="color:<?= $i <= $avis['note'] ? '#F1C40F' : '#E0E0E0' ?>;"></i>
                    <?php endfor; ?>
                </div>
                <?php if (!empty($avis['commentaire'])): ?>
                <div class="avis-text">
                    "<?= htmlspecialchars(substr($avis['commentaire'], 0, 100)) ?><?= strlen($avis['commentaire']) > 100 ? '...' : '' ?>"
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:30px 0;">
            <p style="color:#8A99AA;font-size:0.9rem;">Aucun avis client pour le moment.</p>
        </div>
        <?php endif; ?>
        
        <div class="text-center" style="margin-top:28px;">
            <a href="boutique/avis.php" style="display:inline-flex;align-items:center;gap:8px;color:#C8922A;font-weight:600;text-decoration:none;font-size:0.85rem;transition:all 0.3s;" onmouseover="this.style.color='#9A6E1A'" onmouseout="this.style.color='#C8922A'">
                Voir tous les avis <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ============================================
     LOGIN SECTION
     ============================================ -->
<section class="section reveal" style="padding: 0 40px 80px;">
    <div class="container-custom">
        <div class="login-section">
            <span class="login-orb"></span>
            <?php if($admin_connecte && $admin_info): ?>
                <h3>Connecté en tant qu'administrateur</h3>
                <p>Bonjour <strong style="color: #E8C870;"><?= htmlspecialchars($admin_info['nom']) ?></strong></p>
                <div class="login-already">
                    <div class="status-icon"><i class="bi bi-check-lg"></i></div>
                    <p>Session administrateur active</p>
                    <a href="admin/dashboard.php" class="btn-dashboard">
                        <i class="bi bi-speedometer2"></i> Tableau de bord
                    </a>
                    <br>
                    <a href="admin/logout.php" class="logout-link">
                        <i class="bi bi-box-arrow-right"></i> Se déconnecter
                    </a>
                </div>
                
            <?php elseif($client_connecte && $client_info): ?>
                <h3>Bonjour <?= htmlspecialchars($client_info['nom']) ?></h3>
                <p>Vous êtes connecté en tant que client</p>
                <div class="login-already">
                    <div class="status-icon"><i class="bi bi-check-lg"></i></div>
                    <p>Session client active</p>
                    <a href="client/mon_compte.php" class="btn-dashboard">
                        <i class="bi bi-person"></i> Mon compte
                    </a>
                    <br>
                    <a href="client/deconnexion.php" class="logout-link">
                        <i class="bi bi-box-arrow-right"></i> Se déconnecter
                    </a>
                </div>
                
            <?php else: ?>
                <h3>Accédez à votre espace</h3>
                <p>Connectez-vous pour gérer vos commandes</p>
                
                <?php if($message_blocage): ?>
                    <div class="blocage-msg">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <span><?= htmlspecialchars($message_blocage) ?></span>
                    </div>
                <?php endif; ?>

                <?php if($login_error): ?>
                    <div class="login-error">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($login_error) ?>
                    </div>
                <?php endif; ?>

                <form class="login-form" method="POST">
                    <input type="hidden" name="action_login" value="1">
                    <div class="input-group">
                        <input type="email" name="email" placeholder="Votre adresse email" required <?= $formulaire_bloque ? 'disabled' : '' ?>>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" placeholder="Votre mot de passe" required <?= $formulaire_bloque ? 'disabled' : '' ?>>
                    </div>
                    <button type="submit" <?= $formulaire_bloque ? 'disabled' : '' ?>>
                        <i class="bi bi-box-arrow-in-right"></i> Se connecter
                    </button>
                </form>
                
                <div class="login-secure">
                    <i class="bi bi-shield-check"></i> Sécurisé : 3 tentatives avant blocage
                    <?php if($formulaire_bloque): ?>
                        <span class="blocked-note">
                            <i class="bi bi-exclamation-octagon-fill"></i> Formulaire bloqué temporairement
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================================
     SECTION AWA DOUMBIA
     ============================================ -->
<section class="awa-legacy-section reveal">
    <div class="container-custom">
        <div class="legacy-wrapper">
            <div class="legacy-photo-card reveal-left">
                <div class="legacy-photo-frame">
                    <img src="assets/images/awa2.jpeg" alt="Awa Doumbia"
                        onerror="this.src='https://via.placeholder.com/500x500/C8922A/FFF?text=Awa'">
                </div>
                <div class="legacy-photo-badge">
                    <i class="bi bi-instagram"></i>
                    <span>@iba_design_223</span>
                </div>
            </div>
            <div class="legacy-content reveal-right">
                <span class="legacy-tag">Un cœur grand comme le Mali</span>
                <h2>Awa Doumbia, <span>bien plus qu'une influenceuse</span></h2>
                <p class="legacy-intro">
                    Connue sous le nom d'<strong>Awa Ka Sugu</strong>, Awa Doumbia est une figure emblématique du Mali.
                    Derrière l'entrepreneure à succès se cache une femme au grand cœur, dévouée aux plus démunis.
                </p>
                <div class="legacy-stats">
                    <div class="legacy-stat">
                        <div class="legacy-stat-number">128M+</div>
                        <div class="legacy-stat-label">FCFA mobilisés</div>
                    </div>
                    <div class="legacy-stat">
                        <div class="legacy-stat-number">309K+</div>
                        <div class="legacy-stat-label">Abonnés TikTok</div>
                    </div>
                    <div class="legacy-stat">
                        <div class="legacy-stat-number">45K+</div>
                        <div class="legacy-stat-label">Abonnés Instagram</div>
                    </div>
                </div>
                <div class="legacy-actions">
                    <h3><i class="bi bi-heart-fill"></i> Ses actions qui ont marqué</h3>
                    <div class="actions-list">
                        <div class="action-item">
                            <i class="bi bi-fire"></i>
                            <p><strong>Incendie de Sougounicoura (2026)</strong> — Awa Doumbia a mobilisé <strong>128 095 086 FCFA</strong> pour les victimes.</p>
                        </div>
                        <div class="action-item">
                            <i class="bi bi-droplet"></i>
                            <p><strong>Don de sang pour les FAMa</strong> — Organisation d'une journée de don de sang.</p>
                        </div>
                        <div class="action-item">
                            <i class="bi bi-moon-stars"></i>
                            <p><strong>Ramadan 2022 à Tambacounda</strong> — Offrande de repas de rupture du jeûne.</p>
                        </div>
                    </div>
                </div>
                <div class="legacy-quote">
                    <i class="bi bi-quote"></i>
                    <p>"Si aujourd'hui je tiens debout… c'est parce que tu ne t'es jamais assise."</p>
                    <span>— Awa Doumbia</span>
                </div>
                <div class="legacy-social">
                    <a href="https://www.tiktok.com/@ibadesign77774343" target="_blank" class="social-icon-link tiktok">
                        <i class="bi bi-tiktok"></i> <span>TikTok</span>
                    </a>
                    <a href="https://www.instagram.com/iba_design_223" target="_blank" class="social-icon-link instagram">
                        <i class="bi bi-instagram"></i> <span>Instagram</span>
                    </a>
                    <a href="https://www.facebook.com/awadoumbia51" target="_blank" class="social-icon-link facebook">
                        <i class="bi bi-facebook"></i> <span>Facebook</span>
                    </a>
                    <a href="https://www.snapchat.com/@awadoumbia51" target="_blank" class="social-icon-link snapchat">
                        <i class="bi bi-snapchat"></i> <span>Snapchat</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// ============================================
// ANIMATIONS AU SCROLL — léger, sans librairie
// ============================================
(function () {
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var targets = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale, .section-rule');

    if (reduceMotion || !('IntersectionObserver' in window)) {
        targets.forEach(function (el) { el.classList.add('visible'); });
        return;
    }

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });

    targets.forEach(function (el) { io.observe(el); });
})();

// ============================================
// PARALLAXE LÉGÈRE SUR LA PHOTO DU HERO
// ============================================
(function () {
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion) return;

    var split = document.querySelector('.hero-split');
    if (!split) return;

    var ticking = false;
    var lastY = 0;

    function onScroll() {
        lastY = window.pageYOffset || document.documentElement.scrollTop;
        if (!ticking) {
            window.requestAnimationFrame(function () {
                // Parallaxe : la photo descend légèrement moins vite que le scroll
                split.style.transform = 'translateY(' + (lastY * 0.25) + 'px)';
                ticking = false;
            });
            ticking = true;
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
})();
</script>

<?php require_once 'includes/footer.php'; ?>