<?php
// ============================================
// FICHIER DE VÉRIFICATION DE MAINTENANCE
// ============================================

// ============================================
// 1. VÉRIFIER SI L'UTILISATEUR EST ADMIN
// ============================================
$is_admin = false;
$admin_view = isset($_GET['admin_view']) && $_GET['admin_view'] == 1;
$admin_nom = '';

// Sauvegarder la session publique
$old_session_name = session_name();
$old_session_id = session_id();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Vérifier la session admin
session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    $is_admin = true;
    $admin_nom = $_SESSION['admin_nom'] ?? 'Admin';
    $admin_role = $_SESSION['admin_role'] ?? 'admin';
}

// Restaurer la session publique
session_write_close();
if (!empty($old_session_name)) {
    session_name($old_session_name);
} else {
    session_name('PUBLIC_SESSION');
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// 2. CONNEXION À LA BASE DE DONNÉES
// ============================================
$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    return;
}

// ============================================
// 3. VÉRIFIER LA MAINTENANCE GLOBALE
// ============================================
$tableExists = $pdo->query("SHOW TABLES LIKE 'maintenance_globale'")->rowCount() > 0;

if (!$tableExists) {
    return;
}

$stmt = $pdo->query("SELECT site_actif, message_maintenance, date_fin FROM maintenance_globale ORDER BY id DESC LIMIT 1");
$config = $stmt->fetch();

if (!$config) {
    return;
}

// Si le site est en maintenance (site_actif = 0)
if ($config['site_actif'] == 0) {
    
    // ✅ SI L'UTILISATEUR EST ADMIN
    if ($is_admin || $admin_view) {
        return;
    }
    
    // Vérifier si la date de fin est dépassée
    if (!empty($config['date_fin'])) {
        $date_fin = new DateTime($config['date_fin']);
        $now = new DateTime();
        if ($now > $date_fin) {
            // Date dépassée, réactiver le site automatiquement
            $pdo->prepare("UPDATE maintenance_globale SET site_actif = 1 WHERE id = (SELECT id FROM (SELECT id FROM maintenance_globale ORDER BY id DESC LIMIT 1) as tmp)")->execute();
            return;
        }
    }
    
    // Afficher la page de maintenance
    $message = $config['message_maintenance'] ?? 'Site en maintenance. Nous revenons bientôt !';
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Maintenance - Awa Ka Sugu</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Jost:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Jost', sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
                color: #fff;
                text-align: center;
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
                background: radial-gradient(ellipse at 30% 50%, rgba(200,146,42,0.03), transparent 70%);
                pointer-events: none;
            }
            .maintenance-container {
                max-width: 600px;
                padding: 40px;
                position: relative;
                z-index: 1;
            }
            .maintenance-icon {
                font-size: 5rem;
                color: #C8922A;
                margin-bottom: 20px;
                animation: pulse 2s infinite;
            }
            @keyframes pulse {
                0%, 100% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.1); opacity: 0.7; }
            }
            .maintenance-container h1 {
                font-family: 'Playfair Display', serif;
                font-size: 2.2rem;
                color: #C8922A;
                margin-bottom: 12px;
                letter-spacing: 1px;
            }
            .maintenance-container p {
                color: rgba(255,255,255,0.5);
                font-size: 1.05rem;
                line-height: 1.6;
                margin-bottom: 16px;
            }
            .maintenance-container .date-info {
                color: rgba(255,255,255,0.25);
                font-size: 0.8rem;
            }
            .maintenance-container .date-info i {
                color: #C8922A;
                margin-right: 6px;
            }
            .logo {
                font-family: 'Playfair Display', serif;
                font-size: 1.3rem;
                color: #C8922A;
                letter-spacing: 4px;
                margin-bottom: 30px;
            }
            .logo span {
                color: #fff;
                opacity: 0.2;
            }
            .maintenance-status {
                display: inline-block;
                background: rgba(200,146,42,0.12);
                color: #C8922A;
                padding: 4px 18px;
                border-radius: 20px;
                font-size: 0.65rem;
                margin-bottom: 20px;
                border: 1px solid rgba(200,146,42,0.15);
                letter-spacing: 2px;
                text-transform: uppercase;
                font-weight: 600;
            }
            .social-links {
                margin-top: 30px;
                display: flex;
                justify-content: center;
                gap: 16px;
            }
            .social-links a {
                color: rgba(255,255,255,0.2);
                font-size: 1.3rem;
                transition: all 0.3s;
                text-decoration: none;
                width: 44px;
                height: 44px;
                border-radius: 50%;
                border: 1px solid rgba(255,255,255,0.06);
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s;
            }
            .social-links a:hover {
                color: #C8922A;
                transform: translateY(-3px);
                border-color: rgba(200,146,42,0.2);
                background: rgba(200,146,42,0.05);
            }
            .footer-copy {
                margin-top: 30px;
                color: rgba(255,255,255,0.1);
                font-size: 0.65rem;
                letter-spacing: 1px;
            }
            .footer-copy i {
                color: #E74C3C;
                font-size: 0.6rem;
            }
            
            /* ===== BANDEAU ADMIN ===== */
            .admin-banner {
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(13, 13, 13, 0.95);
                border: 1px solid rgba(200,146,42,0.2);
                border-radius: 12px;
                padding: 10px 24px;
                display: flex;
                align-items: center;
                gap: 16px;
                backdrop-filter: blur(10px);
                z-index: 10;
                box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            }
            .admin-banner .avatar {
                width: 30px;
                height: 30px;
                border-radius: 50%;
                background: #C8922A;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.8rem;
                font-weight: 600;
                color: #fff;
                flex-shrink: 0;
            }
            .admin-banner .info {
                color: rgba(255,255,255,0.7);
                font-size: 0.75rem;
            }
            .admin-banner .info strong {
                color: #C8922A;
            }
            .admin-banner .btn-admin-access {
                background: #C8922A;
                color: #0D0D0D;
                padding: 4px 16px;
                border-radius: 20px;
                text-decoration: none;
                font-size: 0.7rem;
                font-weight: 600;
                transition: all 0.3s;
            }
            .admin-banner .btn-admin-access:hover {
                background: #E8B55A;
                transform: translateY(-1px);
            }

            @media (max-width: 600px) {
                .maintenance-container { padding: 20px; }
                .maintenance-container h1 { font-size: 1.8rem; }
                .admin-banner {
                    flex-wrap: wrap;
                    justify-content: center;
                    padding: 12px 16px;
                    bottom: 10px;
                }
                .admin-banner .info { font-size: 0.7rem; text-align: center; }
            }
        </style>
    </head>
    <body>
        <div class="maintenance-container">
            <div class="logo">AWA KA <span>SUGU</span></div>
            <div class="maintenance-status"><i class="bi bi-tools"></i> EN MAINTENANCE</div>
            <div class="maintenance-icon"><i class="bi bi-tools"></i></div>
            <h1>Nous revenons bientôt</h1>
            <p><?= htmlspecialchars($message) ?></p>
            <?php if (!empty($config['date_fin'])): ?>
                <div class="date-info">
                    <i class="bi bi-clock"></i> Retour prévu le : <?= date('d/m/Y à H:i', strtotime($config['date_fin'])) ?>
                </div>
            <?php endif; ?>
            <div class="social-links">
                <a href="#" target="_blank"><i class="bi bi-instagram"></i></a>
                <a href="#" target="_blank"><i class="bi bi-tiktok"></i></a>
                <a href="#" target="_blank"><i class="bi bi-facebook"></i></a>
                <a href="#" target="_blank"><i class="bi bi-youtube"></i></a>
            </div>
            <div class="footer-copy">
                <i class="bi bi-heart-fill"></i> Awa Ka Sugu &copy; <?= date('Y') ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}