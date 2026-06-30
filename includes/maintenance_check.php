<?php
// ============================================
// FICHIER DE VÉRIFICATION DE MAINTENANCE (SITE PUBLIC)
// ============================================
// Ce fichier est inclus par includes/header.php et par toutes
// les pages boutique/client. Il bloque l'accès au site public
// si le mode maintenance est activé, SAUF pour l'administratrice
// connectée (vérifiée via la session ADMIN_SESSION séparée).

// On a besoin de $pdo (déjà chargé par config.php dans header.php).
// Si jamais ce fichier est appelé avant config.php, on se connecte nous-mêmes.
if (!isset($pdo)) {
    $host   = 'localhost';
    $dbname = 'awakasugu_db';
    $user   = 'root';
    $pass   = '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        // Si erreur de connexion, on laisse le site continuer normalement
        return;
    }
}

// ============================================
// 1. VÉRIFIER SI L'UTILISATEUR EST ADMIN (Awa)
// ============================================
// On vérifie la VRAIE session admin (ADMIN_SESSION), pas la session
// publique en cours, sans casser la session publique active.
$is_admin = false;

$current_session_name = session_name();
$current_session_id   = session_id();

// Fermer temporairement la session publique pour aller lire l'admin
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    $is_admin = true;
}
session_write_close();

// Restaurer la session publique d'origine
session_name($current_session_name ?: 'PUBLIC_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// 2. VÉRIFIER LA MAINTENANCE GLOBALE
// ============================================
$tableExists = $pdo->query("SHOW TABLES LIKE 'maintenance_globale'")->rowCount() > 0;
if (!$tableExists) {
    return; // Si la table n'existe pas encore, le site continue normalement
}

$stmt = $pdo->query("SELECT site_actif, message_maintenance, date_fin FROM maintenance_globale ORDER BY id DESC LIMIT 1");
$config = $stmt->fetch();

if (!$config) {
    return; // Pas de config enregistrée, le site continue normalement
}

// Si le site est en maintenance (site_actif = 0)
if ($config['site_actif'] == 0) {

    // L'admin connectée passe toujours, peu importe la maintenance
    if ($is_admin) {
        return;
    }

    // Vérifier si la date de fin programmée est dépassée
    if (!empty($config['date_fin'])) {
        $date_fin = new DateTime($config['date_fin']);
        $now = new DateTime();
        if ($now > $date_fin) {
            // Date dépassée : réactiver automatiquement le site
            $pdo->prepare("UPDATE maintenance_globale SET site_actif = 1 WHERE id = (SELECT id FROM (SELECT id FROM maintenance_globale ORDER BY id DESC LIMIT 1) as tmp)")->execute();
            return;
        }
    }

    // ============================================
    // AFFICHER LA PAGE DE MAINTENANCE AU VISITEUR
    // ============================================
    $message = $config['message_maintenance'] ?? 'Site en maintenance. Nous revenons bientôt !';
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Maintenance - Awa Ka Sugu</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Jost:wght@300;400;500;600;700;800&display=swap');
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
            }
            .maintenance-container { max-width: 600px; padding: 40px; }
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
                font-size: 2.5rem;
                color: #C8922A;
                margin-bottom: 15px;
            }
            .maintenance-container p {
                color: rgba(255,255,255,0.6);
                font-size: 1.1rem;
                line-height: 1.6;
                margin-bottom: 20px;
            }
            .maintenance-container .date-info {
                color: rgba(255,255,255,0.3);
                font-size: 0.8rem;
            }
            .logo {
                font-family: 'Playfair Display', serif;
                font-size: 1.5rem;
                color: #C8922A;
                letter-spacing: 3px;
                margin-bottom: 30px;
            }
            .social-links {
                margin-top: 30px;
                display: flex;
                justify-content: center;
                gap: 20px;
            }
            .social-links a {
                color: rgba(255,255,255,0.3);
                font-size: 1.5rem;
                transition: all 0.3s;
            }
            .social-links a:hover {
                color: #C8922A;
                transform: translateY(-3px);
            }
            .maintenance-status {
                display: inline-block;
                background: rgba(200,146,42,0.15);
                color: #C8922A;
                padding: 4px 16px;
                border-radius: 20px;
                font-size: 0.7rem;
                margin-bottom: 20px;
                border: 1px solid rgba(200,146,42,0.2);
            }
        </style>
    </head>
    <body>
        <div class="maintenance-container">
            <div class="logo">AWA KA SUGU</div>
            <div class="maintenance-status"><i class="bi bi-tools"></i> EN MAINTENANCE</div>
            <div class="maintenance-icon"><i class="bi bi-tools"></i></div>
            <h1>Site en maintenance</h1>
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
            <div style="margin-top: 30px; color: rgba(255,255,255,0.2); font-size: 0.7rem;">
                Awa Ka Sugu &copy; <?= date('Y') ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>
