<?php
// ============================================
// GESTION DES ADMINISTRATEURS - AWA KA SUGU
// ============================================
// Seul le Super Admin peut accéder à cette page
// ============================================

require_once '../includes/session_config.php';
require_once '../includes/functions_securite.php';
require_once '../includes/envoi_email.php';

// ============================================
// VÉRIFICATION DE CONNEXION
// ============================================
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// ============================================
// RÉCUPÉRATION DES INFOS ADMIN
// ============================================
$admin_info = getAdminInfo();
$admin_role = $admin_info['role'] ?? 'admin';
$admin_nom = $admin_info['nom'] ?? 'Admin';
$admin_id = $admin_info['id'] ?? 0;

// 🔒 SEUL LE SUPER ADMIN PEUT ACCÉDER
if ($admin_role !== 'super_admin') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Gestion des Administrateurs';

// ============================================
// CONNEXION À LA BASE DE DONNÉES
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
    die("Erreur de connexion à la base de données.");
}

// ============================================
// CRÉER LA TABLE DES PERMISSIONS SI ELLE N'EXISTE PAS
// ============================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS permissions_liste (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cle VARCHAR(50) NOT NULL UNIQUE,
            nom VARCHAR(100) NOT NULL,
            icone VARCHAR(50) DEFAULT 'bi-circle',
            description VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch(PDOException $e) {}

// ============================================
// INSÉRER LES PERMISSIONS PAR DÉFAUT
// ============================================
$permissions_defaut = [
    ['dashboard', 'Tableau de bord', 'bi-speedometer2', 'Accès au tableau de bord'],
    ['produits', 'Produits', 'bi-box-seam', 'Gérer les produits'],
    ['commandes', 'Commandes', 'bi-receipt', 'Gérer les commandes'],
    ['clients', 'Clients', 'bi-people', 'Gérer les clients'],
    ['achats', 'Achats', 'bi-cart-check', 'Gérer les achats'],
    ['stocks', 'Stocks', 'bi-bar-chart', 'Gérer les stocks'],
    ['promotions', 'Promotions', 'bi-percent', 'Gérer les promotions'],
    ['videos', 'Vidéos', 'bi-camera-reels', 'Gérer les vidéos'],
    ['factures', 'Factures', 'bi-file-earmark-text', 'Gérer les factures'],
    ['paiements', 'Paiements', 'bi-credit-card', 'Gérer les paiements'],
    ['livraisons', 'Livraisons', 'bi-truck', 'Gérer les livraisons'],
    ['fidelisation', 'Fidélisation', 'bi-star', 'Gérer la fidélisation'],
    ['rapports', 'Rapports', 'bi-file-earmark-bar-graph', 'Voir les rapports'],
    ['reservations', 'Réservations', 'bi-calendar-check', 'Gérer les réservations'],
    ['plats', 'Plats', 'bi-cup-hot', 'Gérer les plats (restaurant)'],
    ['commandes_repas', 'Commandes repas', 'bi-bag-check', 'Gérer les commandes repas'],
    ['messagerie', 'Messagerie', 'bi-chat-dots', 'Gérer la messagerie'],
    ['parametres', 'Paramètres', 'bi-gear', 'Accès aux paramètres (Super Admin)'],
    ['point_de_vente', 'Point de vente', 'bi-cash-stack', 'Gérer les ventes sur place'],
    ['codes_promo', 'Codes promo', 'bi-tags', 'Gérer les codes promotionnels'],
    ['avis_admin', 'Avis clients', 'bi-star', 'Gérer les avis clients'],
    ['barcode', 'Codes-barres', 'bi-upc-scan', 'Générer des codes-barres'],
    ['analytics', 'Analytics', 'bi-graph-up-arrow', 'Voir les statistiques avancées'],
    ['notifications', 'Notifications', 'bi-bell', 'Gérer les notifications']
];

foreach ($permissions_defaut as $p) {
    $stmt = $pdo->prepare("SELECT id FROM permissions_liste WHERE cle = ?");
    $stmt->execute([$p[0]]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO permissions_liste (cle, nom, icone, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$p[0], $p[1], $p[2], $p[3]]);
    }
}

// ============================================
// RÉCUPÉRER LA LISTE DES PERMISSIONS DISPONIBLES
// ============================================
$permissions_liste = $pdo->query("SELECT * FROM permissions_liste ORDER BY nom")->fetchAll();

// ============================================
// DÉFINITION DES RÔLES
// ============================================
$role_labels = [
    'super_admin' => 'Super Administrateur',
    'directeur' => 'Directeur',
    'admin' => 'Administrateur',
    'admin2' => 'Agent / Vendeur'
];

$role_colors = [
    'super_admin' => '#8E44AD',
    'directeur' => '#C8922A',
    'admin' => '#2980B9',
    'admin2' => '#7F8C8D'
];

$role_icons = [
    'super_admin' => 'bi-shield-fill-check',
    'directeur' => 'bi-crown-fill',
    'admin' => 'bi-person-badge-fill',
    'admin2' => 'bi-person-fill'
];

// ============================================
// TRAITEMENT DES ACTIONS
// ============================================
$success = '';
$error = '';
$email_envoye = false;

// AJOUTER UN ADMIN AVEC PERMISSIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    
    if (empty($nom) || empty($email) || empty($password)) {
        $error = 'Tous les champs sont obligatoires.';
    } else {
        $check = $pdo->prepare("SELECT id FROM admin WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } else {
            $permissions_json = json_encode($permissions);
            
            // Si le rôle est vide, on met NULL
            $role_value = !empty($role) ? $role : null;
            
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO admin (nom, email, mot_de_passe, role, permissions, is_active, created_by) 
                VALUES (?, ?, ?, ?, ?, 1, ?)
            ");
            $stmt->execute([$nom, $email, $hashed_password, $role_value, $permissions_json, $admin_id]);
            $new_admin_id = $pdo->lastInsertId();
            
            // ============================================
            // ENVOI DE L'EMAIL DE CONFIRMATION
            // ============================================
            $sujet = "🔐 Vos identifiants de connexion - Awa Ka Sugu";
            
            $permissions_noms = [];
            foreach ($permissions as $perm) {
                foreach ($permissions_liste as $p) {
                    if ($p['cle'] == $perm) {
                        $permissions_noms[] = $p['nom'];
                        break;
                    }
                }
            }
            
            $role_label = !empty($role) ? ($role_labels[$role] ?? $role) : 'Aucun rôle (tâches uniquement)';
            
            $message_html = "
            <!DOCTYPE html>
            <html lang='fr'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@300;400;500;600&display=swap');
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: 'Inter', Arial, sans-serif; background: #0A0A0A; padding: 30px 15px; }
                    .wrapper { max-width: 620px; margin: 0 auto; }
                    /* ── Header ── */
                    .header { background: linear-gradient(160deg, #0A0A0A 0%, #1C1308 60%, #0A0A0A 100%); padding: 40px 30px 32px; text-align: center; border-radius: 20px 20px 0 0; }
                    .header .brand { font-family: 'Playfair Display', Georgia, serif; font-size: 1.8rem; font-weight: 700; color: #C8922A; letter-spacing: 4px; }
                    .header .divider { width: 50px; height: 1px; background: linear-gradient(90deg, transparent, #C8922A, transparent); margin: 10px auto; }
                    .header .tagline { color: rgba(255,255,255,0.25); font-size: 0.65rem; letter-spacing: 3px; text-transform: uppercase; }
                    .header .welcome-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(200,146,42,0.12); border: 1px solid rgba(200,146,42,0.3); color: #E8B55A; font-size: 0.72rem; font-weight: 600; padding: 6px 16px; border-radius: 20px; margin-top: 16px; letter-spacing: 1px; }
                    /* ── Body ── */
                    .body { background: #ffffff; padding: 36px 32px; }
                    .greeting { font-size: 1.3rem; font-weight: 700; color: #0D0D0D; margin-bottom: 6px; }
                    .greeting span { color: #C8922A; }
                    .subtitle { color: #7A8694; font-size: 0.88rem; margin-bottom: 28px; line-height: 1.6; }
                    /* ── Account card ── */
                    .account-card { background: #F9F9FB; border-radius: 14px; overflow: hidden; margin-bottom: 24px; border: 1px solid #EEEFF2; }
                    .account-card-head { background: linear-gradient(135deg, #0D0D0D, #1A1510); padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; }
                    .account-card-head .ac-title { color: rgba(255,255,255,0.4); font-size: 0.65rem; letter-spacing: 2px; text-transform: uppercase; }
                    .account-card-head .role-pill { background: rgba(200,146,42,0.2); border: 1px solid rgba(200,146,42,0.4); color: #E8B55A; font-size: 0.7rem; font-weight: 600; padding: 3px 12px; border-radius: 20px; }
                    .account-row { display: flex; justify-content: space-between; align-items: center; padding: 11px 20px; border-bottom: 1px solid #F0F1F4; }
                    .account-row:last-child { border-bottom: none; }
                    .account-row .ar-lbl { color: #8A92A3; font-size: 0.8rem; font-weight: 500; }
                    .account-row .ar-val { color: #1A1A2E; font-size: 0.88rem; font-weight: 600; }
                    /* ── Permissions ── */
                    .perms-section { margin-bottom: 24px; }
                    .perms-title { font-size: 0.72rem; font-weight: 600; color: #8A92A3; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; }
                    .perms-list { display: flex; flex-wrap: wrap; gap: 7px; }
                    .perm-tag { background: linear-gradient(135deg, #FFF8EC, #FFF3DB); border: 1px solid rgba(200,146,42,0.25); color: #8A6020; font-size: 0.75rem; font-weight: 600; padding: 4px 12px; border-radius: 20px; }
                    .perm-empty { color: #B0B8C4; font-size: 0.8rem; font-style: italic; }
                    /* ── Password box ── */
                    .pwd-section { margin-bottom: 24px; }
                    .pwd-label { font-size: 0.72rem; font-weight: 600; color: #8A92A3; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; }
                    .pwd-box { background: linear-gradient(135deg, #0D0D0D, #1A1510); border-radius: 12px; padding: 18px 22px; display: flex; align-items: center; justify-content: space-between; border: 1px solid rgba(200,146,42,0.2); }
                    .pwd-box .pwd-hint { color: rgba(255,255,255,0.35); font-size: 0.75rem; letter-spacing: 1px; }
                    .pwd-box .pwd-value { font-family: 'Courier New', monospace; font-size: 1.1rem; font-weight: 700; color: #C8922A; letter-spacing: 2px; }
                    /* ── Warning ── */
                    .warning-note { background: #FFF5F5; border: 1px solid rgba(231,76,60,0.2); border-radius: 10px; padding: 12px 16px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 10px; }
                    .warning-note .wn-icon { font-size: 1rem; margin-top: 1px; }
                    .warning-note .wn-text { font-size: 0.82rem; color: #C0392B; line-height: 1.5; }
                    /* ── CTA ── */
                    .cta-btn { display: block; text-align: center; background: linear-gradient(135deg, #C8922A, #E8B55A); color: #fff; font-weight: 700; font-size: 0.9rem; padding: 15px 30px; border-radius: 30px; text-decoration: none; letter-spacing: 0.5px; margin-bottom: 10px; }
                    /* ── Footer ── */
                    .footer { background: #0D0D0D; padding: 22px 30px; text-align: center; border-radius: 0 0 20px 20px; }
                    .footer .ft-brand { color: #C8922A; font-family: 'Playfair Display', serif; font-size: 0.85rem; margin-bottom: 6px; }
                    .footer .ft-copy { color: rgba(255,255,255,0.18); font-size: 0.65rem; margin-top: 6px; }
                </style>
            </head>
            <body>
                <div class='wrapper'>
                    <div class='header'>
                        <div class='brand'>✦ Awa Ka Sugu ✦</div>
                        <div class='divider'></div>
                        <div class='tagline'>Espace Administration</div>
                        <div class='welcome-badge'>👋 Bienvenue dans l'équipe</div>
                    </div>
                    <div class='body'>
                        <p class='greeting'>Bonjour, <span>$nom</span> !</p>
                        <p class='subtitle'>Un compte administrateur vient d'être créé pour vous sur la plateforme <strong>Awa Ka Sugu</strong>. Voici vos informations d'accès.</p>
                        
                        <div class='account-card'>
                            <div class='account-card-head'>
                                <span class='ac-title'>Informations du compte</span>
                                <span class='role-pill'>$role_label</span>
                            </div>
                            <div class='account-row'><span class='ar-lbl'>Adresse email</span><span class='ar-val'>$email</span></div>
                            <div class='account-row'><span class='ar-lbl'>Créé par</span><span class='ar-val'>$admin_nom</span></div>
                        </div>
                        
                        <div class='perms-section'>
                            <p class='perms-title'>Tâches assignées</p>
                            <div class='perms-list'>";
                            foreach ($permissions as $perm) {
                                foreach ($permissions_liste as $p) {
                                    if ($p['cle'] == $perm) {
                                        $message_html .= "<span class='perm-tag'>" . $p['nom'] . "</span>";
                                        break;
                                    }
                                }
                            }
                            if (empty($permissions)) {
                                $message_html .= "<span class='perm-empty'>Aucune tâche spécifique assignée</span>";
                            }
            $message_html .= "
                            </div>
                        </div>
                        
                        <div class='pwd-section'>
                            <p class='pwd-label'>Votre mot de passe temporaire</p>
                            <div class='pwd-box'>
                                <span class='pwd-hint'>Mot de passe</span>
                                <span class='pwd-value'>$password</span>
                            </div>
                        </div>
                        
                        <div class='warning-note'>
                            <span class='wn-icon'>⚠️</span>
                            <span class='wn-text'><strong>Important :</strong> Pour votre sécurité, changez ce mot de passe dès votre première connexion.</span>
                        </div>
                        
                        <a href='http://localhost/awakasugu/admin/login.php' class='cta-btn'>Accéder à l'administration →</a>
                    </div>
                    <div class='footer'>
                        <div class='ft-brand'>✦ Awa Ka Sugu — Administration ✦</div>
                        <div class='ft-copy'>&copy; 2026 Awa Ka Sugu — Email automatique, merci de ne pas y répondre.</div>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $email_envoye = envoyerEmail($email, $sujet, $message_html);
            
            enregistrer_log_action(
                $pdo,
                $admin_id,
                $admin_nom,
                $admin_info['email'] ?? '',
                'Ajout administrateur',
                "Ajout de l'administrateur '$nom' avec le rôle '$role' et " . count($permissions) . " permissions" . ($email_envoye ? " - Email envoyé" : " - Email NON envoyé")
            );
            
            if ($email_envoye) {
                $success = "✅ L'administrateur <strong>$nom</strong> a été créé avec succès.<br>📧 Un email de confirmation a été envoyé à <strong>$email</strong>.<br>📋 Tâches assignées : " . (empty($permissions_noms) ? 'Aucune' : implode(', ', $permissions_noms));
            } else {
                $success = "⚠️ L'administrateur <strong>$nom</strong> a été créé.<br>❌ Mais l'email de confirmation n'a pas pu être envoyé.";
            }
        }
    }
}

// MODIFIER UN ADMIN (permissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_admin') {
    $edit_id = (int)$_POST['admin_id'];
    $new_role = $_POST['role'] ?? '';
    $new_nom = trim($_POST['nom'] ?? '');
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    
    if ($edit_id == $admin_id) {
        $error = "Vous ne pouvez pas modifier votre propre compte.";
    } else {
        $stmt = $pdo->prepare("SELECT nom, email FROM admin WHERE id = ?");
        $stmt->execute([$edit_id]);
        $admin_modifie = $stmt->fetch();
        
        if ($admin_modifie) {
            $permissions_json = json_encode($permissions);
            $role_value = !empty($new_role) ? $new_role : null;
            $stmt = $pdo->prepare("UPDATE admin SET role = ?, nom = ?, permissions = ? WHERE id = ?");
            $stmt->execute([$role_value, $new_nom, $permissions_json, $edit_id]);
            
            enregistrer_log_action(
                $pdo,
                $admin_id,
                $admin_nom,
                $admin_info['email'] ?? '',
                'Modification administrateur',
                "Modification de l'administrateur '{$admin_modifie['nom']}' - Nouveau rôle: $new_role"
            );
            
            $success = "✅ L'administrateur a été modifié avec succès.";
        } else {
            $error = "Administrateur introuvable.";
        }
    }
}

// ACTIVER/DÉSACTIVER UN ADMIN
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $toggle_id = (int)$_GET['toggle'];
    if ($toggle_id == $admin_id) {
        $error = "Vous ne pouvez pas modifier votre propre compte.";
    } else {
        $stmt = $pdo->prepare("SELECT is_active, nom FROM admin WHERE id = ?");
        $stmt->execute([$toggle_id]);
        $admin_toggle = $stmt->fetch();
        if ($admin_toggle) {
            $new_status = $admin_toggle['is_active'] ? 0 : 1;
            $status_text = $new_status ? 'activé' : 'désactivé';
            $stmt = $pdo->prepare("UPDATE admin SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $toggle_id]);
            
            $success = "✅ Le compte de l'administrateur a été " . $status_text . ".";
        } else {
            $error = "Administrateur introuvable.";
        }
    }
}

// SUPPRIMER UN ADMIN
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if ($delete_id == $admin_id) {
        $error = "Vous ne pouvez pas supprimer votre propre compte.";
    } else {
        $stmt = $pdo->prepare("SELECT nom FROM admin WHERE id = ?");
        $stmt->execute([$delete_id]);
        $admin_delete = $stmt->fetch();
        if ($admin_delete) {
            $stmt = $pdo->prepare("DELETE FROM admin WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "✅ L'administrateur a été supprimé avec succès.";
        } else {
            $error = "Administrateur introuvable.";
        }
    }
}

// ============================================
// RÉCUPÉRER LA LISTE DES ADMINS
// ============================================
$admins = $pdo->query("
    SELECT a.*, 
           (SELECT nom FROM admin WHERE id = a.created_by) as created_by_nom 
    FROM admin a 
    ORDER BY 
        CASE 
            WHEN a.role = 'super_admin' THEN 0 
            WHEN a.role = 'directeur' THEN 1 
            WHEN a.role = 'admin' THEN 2 
            WHEN a.role = 'admin2' THEN 3 
            ELSE 4
        END,
        a.nom
")->fetchAll();

// Statistiques
$total_admins = count($admins);
$admins_actifs = 0;
foreach ($admins as $a) {
    if ($a['is_active']) $admins_actifs++;
}

// ============================================
// INCLUSION DU HEADER ET DE LA SIDEBAR
// ============================================
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="main">

    <div class="topbar">
        <div>
            <div class="topbar-title">👥 Gestion des <span>Administrateurs</span></div>
            <div class="topbar-breadcrumb">Super Admin → Utilisateurs</div>
        </div>
        <div class="topbar-right">
            <span style="font-size:0.65rem;background:#8E44AD;color:#fff;padding:4px 14px;border-radius:20px;font-weight:600;">
                <i class="bi bi-shield-fill-check"></i> Super Admin
            </span>
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <div class="content">

        <?php if($success): ?>
            <div class="alert-success" style="background:#d4edda;padding:15px 20px;border-radius:8px;color:#155724;border:1px solid #c3e6cb;margin-bottom:20px;">
                <i class="bi bi-check-circle-fill"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert-danger" style="background:#f8d7da;padding:15px 20px;border-radius:8px;color:#721c24;border:1px solid #f5c6cb;margin-bottom:20px;">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px;">
            <div class="stat-box" style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.06);display:flex;align-items:center;gap:15px;">
                <div class="stat-icon ic-purple" style="width:45px;height:45px;border-radius:10px;background:#f3e8ff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#8E44AD;"><i class="bi bi-people"></i></div>
                <div>
                    <div class="stat-val" style="font-size:1.5rem;font-weight:700;color:#1A2C3E;"><?= $total_admins ?></div>
                    <div class="stat-lbl" style="font-size:0.75rem;color:#8A99AA;">Total administrateurs</div>
                </div>
            </div>
            <div class="stat-box" style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.06);display:flex;align-items:center;gap:15px;">
                <div class="stat-icon ic-green" style="width:45px;height:45px;border-radius:10px;background:#e8f5e9;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#2E7D32;"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-val" style="font-size:1.5rem;font-weight:700;color:#1A2C3E;"><?= $admins_actifs ?></div>
                    <div class="stat-lbl" style="font-size:0.75rem;color:#8A99AA;">Comptes actifs</div>
                </div>
            </div>
            <div class="stat-box" style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.06);display:flex;align-items:center;gap:15px;">
                <div class="stat-icon ic-red" style="width:45px;height:45px;border-radius:10px;background:#fbe9e7;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#C62828;"><i class="bi bi-person-x"></i></div>
                <div>
                    <div class="stat-val" style="font-size:1.5rem;font-weight:700;color:#1A2C3E;"><?= $total_admins - $admins_actifs ?></div>
                    <div class="stat-lbl" style="font-size:0.75rem;color:#8A99AA;">Comptes inactifs</div>
                </div>
            </div>
            <div class="stat-box" style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.06);display:flex;align-items:center;gap:15px;">
                <div class="stat-icon ic-gold" style="width:45px;height:45px;border-radius:10px;background:#fff8e1;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#C8922A;"><i class="bi bi-shield-fill-check"></i></div>
                <div>
                    <div class="stat-val" style="font-size:1.5rem;font-weight:700;color:#1A2C3E;">1</div>
                    <div class="stat-lbl" style="font-size:0.75rem;color:#8A99AA;">Super Admin</div>
                </div>
            </div>
        </div>

        <!-- ===== FORMULAIRE D'AJOUT AVEC PERMISSIONS ===== -->
        <div class="card-white" style="background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:25px;">
            <div class="card-header" style="padding:18px 25px;border-bottom:1px solid #E8ECF0;display:flex;justify-content:space-between;align-items:center;">
                <div class="card-title" style="font-weight:600;color:#1A2C3E;font-size:1rem;">
                    <i class="bi bi-person-plus" style="color:#C8922A;"></i> Ajouter un administrateur
                </div>
            </div>
            <div class="card-body" style="padding:25px;">
                <form method="POST">
                    <input type="hidden" name="action" value="add_admin">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                Nom complet <span style="color:#E74C3C;">*</span>
                            </label>
                            <input type="text" name="nom" required placeholder="Ex: Awa Doumbia" style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                Email <span style="color:#E74C3C;">*</span>
                            </label>
                            <input type="email" name="email" required placeholder="exemple@awakasugu.ml" style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                Mot de passe <span style="color:#E74C3C;">*</span>
                            </label>
                            <input type="password" name="password" required placeholder="••••••••" minlength="8" style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr auto;gap:12px;margin-top:12px;align-items:end;">
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                Rôle
                            </label>
                            <select name="role" style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;background:#fff;">
                                <option value="">Aucun rôle (tâches uniquement)</option>
                                <option value="directeur">Directeur</option>
                                <option value="admin" selected>Administrateur</option>
                                <option value="admin2">Agent / Vendeur</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-admin btn-primary" style="padding:10px 30px;white-space:nowrap;background:#C8922A;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
                            <i class="bi bi-plus-circle"></i> Ajouter
                        </button>
                    </div>
                    
                    <!-- PERMISSIONS -->
                    <div style="margin-top:18px;padding:16px 20px;background:#F8F9FA;border-radius:10px;border:1px solid #E8ECF0;">
                        <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">
                            <i class="bi bi-list-check"></i> Tâches / Permissions
                        </label>
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">
                            <?php foreach($permissions_liste as $p): ?>
                                <?php if($p['cle'] == 'parametres'): ?>
                                    <label style="display:flex;align-items:center;gap:6px;font-size:0.8rem;color:#8A99AA;cursor:not-allowed;padding:4px;">
                                        <input type="checkbox" disabled style="accent-color:#C8922A;">
                                        <i class="bi <?= $p['icone'] ?>"></i> <?= $p['nom'] ?>
                                        <span style="font-size:0.5rem;color:#999;">(Super Admin)</span>
                                    </label>
                                <?php else: ?>
                                    <label style="display:flex;align-items:center;gap:6px;font-size:0.8rem;color:#333;cursor:pointer;padding:4px;border-radius:4px;transition:background 0.2s;" 
                                           onmouseover="this.style.background='#F0F2F5'" onmouseout="this.style.background='transparent'">
                                        <input type="checkbox" name="permissions[]" value="<?= $p['cle'] ?>" style="accent-color:#C8922A;width:16px;height:16px;">
                                        <i class="bi <?= $p['icone'] ?>" style="color:#C8922A;"></i> <?= $p['nom'] ?>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div style="font-size:0.6rem;color:#8A99AA;margin-top:8px;padding-top:8px;border-top:1px solid #E8ECF0;">
                            <i class="bi bi-info-circle"></i> Sélectionnez les tâches que cet utilisateur pourra effectuer.
                            Les permissions non sélectionnées seront masquées dans son espace.
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===== LISTE DES ADMINISTRATEURS ===== -->
        <div class="card-white" style="background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
            <div class="card-header" style="padding:18px 25px;border-bottom:1px solid #E8ECF0;display:flex;justify-content:space-between;align-items:center;">
                <div class="card-title" style="font-weight:600;color:#1A2C3E;font-size:1rem;">
                    <i class="bi bi-people"></i> Tous les administrateurs
                </div>
                <div style="font-size:0.7rem;color:#8A99AA;background:#F8F9FA;padding:4px 16px;border-radius:20px;border:1px solid #E8ECF0;">
                    <strong style="color:#C8922A;"><?= count($admins) ?></strong> administrateur(s)
                </div>
            </div>
            <div class="card-body" style="padding:5px 0;">
                <?php if(empty($admins)): ?>
                    <div class="empty-state" style="text-align:center;padding:40px;color:#8A99AA;">
                        <i class="bi bi-people" style="font-size:2.5rem;display:block;margin-bottom:10px;color:#D5D5D5;"></i>
                        <p style="margin:0;font-size:0.85rem;">Aucun administrateur trouvé.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($admins as $a): 
                        $permissions_user = !empty($a['permissions']) ? json_decode($a['permissions'], true) : [];
                        $role_display = $a['role'] ? ($role_labels[$a['role']] ?? $a['role']) : 'Sans rôle';
                    ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #F0F2F5;transition:background 0.2s;flex-wrap:wrap;gap:12px;" onmouseover="this.style.background='#FAFBFC'" onmouseout="this.style.background='transparent'">
                        <!-- Info utilisateur -->
                        <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:200px;">
                            <div style="width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;color:#fff;flex-shrink:0;background: <?= $role_colors[$a['role']] ?? '#7F8C8D' ?>;">
                                <?= strtoupper(substr($a['nom'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;color:#1A2C3E;font-size:0.95rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                    <?= htmlspecialchars($a['nom']) ?>
                                    <?php if($a['id'] == $admin_id): ?>
                                        <span style="font-size:0.55rem;background:#8E44AD;color:#fff;padding:1px 12px;border-radius:12px;font-weight:600;">Vous</span>
                                    <?php endif; ?>
                                    <?php if($a['is_active'] == 0): ?>
                                        <span style="font-size:0.55rem;background:#E74C3C;color:#fff;padding:1px 12px;border-radius:12px;font-weight:600;">Désactivé</span>
                                    <?php endif; ?>
                                    <?php if(empty($a['role'])): ?>
                                        <span style="font-size:0.5rem;background:#F39C12;color:#fff;padding:1px 10px;border-radius:10px;font-weight:600;">Sans rôle</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:0.8rem;color:#5A6B7A;">
                                    <?= htmlspecialchars($a['email']) ?>
                                </div>
                                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:3px;">
                                    <?php 
                                    foreach($permissions_user as $up):
                                        foreach($permissions_liste as $pl):
                                            if($pl['cle'] == $up):
                                    ?>
                                        <span style="background:rgba(200,146,42,0.1);color:#C8922A;padding:1px 8px;border-radius:10px;font-size:0.55rem;">
                                            <?= $pl['nom'] ?>
                                        </span>
                                    <?php 
                                            break;
                                        endif;
                                        endforeach;
                                    endforeach; 
                                    ?>
                                    <?php if(empty($permissions_user)): ?>
                                        <span style="color:#999;font-size:0.6rem;">Aucune tâche</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:0.6rem;color:#8A99AA;margin-top:2px;">
                                    <?php if($a['created_by']): ?>
                                        Créé par <?= htmlspecialchars($a['created_by_nom'] ?? 'Inconnu') ?>
                                    <?php endif; ?>
                                    le <?= date('d/m/Y', strtotime($a['created_at'] ?? 'now')) ?>
                                    <?php if($a['last_login']): ?>
                                        • Dernière connexion : <?= date('d/m/Y H:i', strtotime($a['last_login'])) ?>
                                    <?php else: ?>
                                        • Jamais connecté
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <span style="display:inline-block;padding:4px 14px;border-radius:20px;font-size:0.65rem;font-weight:600;color:#fff;background: <?= $role_colors[$a['role']] ?? '#7F8C8D' ?>;">
                                <i class="bi <?= $role_icons[$a['role']] ?? 'bi-person' ?>" style="font-size:0.7rem;"></i>
                                <?= $role_display ?>
                            </span>
                            <span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:0.6rem;font-weight:700;background:<?= $a['is_active'] ? '#E8F5E9' : '#FBE9E7' ?>;color:<?= $a['is_active'] ? '#2E7D32' : '#C62828' ?>;">
                                <i class="bi bi-circle-fill" style="font-size:0.35rem;color:<?= $a['is_active'] ? '#28A745' : '#E74C3C' ?>;"></i>
                                <?= $a['is_active'] ? 'Actif' : 'Inactif' ?>
                            </span>
                            
                            <?php if($a['id'] != $admin_id): ?>
                                <button onclick="openEditModal(<?= $a['id'] ?>, '<?= htmlspecialchars($a['nom']) ?>', '<?= $a['role'] ?>')" class="btn-small blue" title="Modifier" style="padding:5px 12px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:rgba(41,128,185,0.1);color:#2980B9;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='#2980B9';this.style.color='#fff'" onmouseout="this.style.background='rgba(41,128,185,0.1)';this.style.color='#2980B9'">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="?toggle=<?= $a['id'] ?>" class="btn-small <?= $a['is_active'] ? 'gray' : 'green' ?>" title="<?= $a['is_active'] ? 'Désactiver' : 'Activer' ?>" style="padding:5px 12px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:<?= $a['is_active'] ? '#F0F2F5' : 'rgba(40,167,69,0.1)' ?>;color:<?= $a['is_active'] ? '#5A6B7A' : '#28A745' ?>;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='<?= $a['is_active'] ? '#E0E6ED' : '#28A745' ?>';this.style.color='<?= $a['is_active'] ? '#333' : '#fff' ?>'" onmouseout="this.style.background='<?= $a['is_active'] ? '#F0F2F5' : 'rgba(40,167,69,0.1)' ?>';this.style.color='<?= $a['is_active'] ? '#5A6B7A' : '#28A745' ?>'">
                                    <i class="bi <?= $a['is_active'] ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                </a>
                                <a href="?delete=<?= $a['id'] ?>" class="btn-small red" onclick="return confirm('⚠️ Supprimer définitivement cet administrateur ? Cette action est irréversible.');" title="Supprimer" style="padding:5px 12px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:rgba(231,76,60,0.1);color:#E74C3C;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='#E74C3C';this.style.color='#fff'" onmouseout="this.style.background='rgba(231,76,60,0.1)';this.style.color='#E74C3C'">
                                    <i class="bi bi-trash3"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     MODAL D'ÉDITION
     ============================================ -->
<div class="modal-overlay" id="editModal" onclick="if(event.target===this) closeEditModal()" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="modal-content" style="max-width:550px;width:95%;padding:28px;background:#fff;border-radius:12px;position:relative;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-family:'Playfair Display',serif;font-size:1.1rem;margin:0;">
                <i class="bi bi-pencil-square" style="color:#C8922A;"></i> Modifier l'administrateur
            </h3>
            <button onclick="closeEditModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#999;transition:transform 0.3s;line-height:1;" onmouseover="this.style.transform='rotate(90deg)'" onmouseout="this.style.transform='rotate(0deg)'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_admin">
            <input type="hidden" name="admin_id" id="edit_admin_id">
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                    Nom complet
                </label>
                <input type="text" name="nom" id="edit_nom" required style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                    Rôle
                </label>
                <select name="role" id="edit_role" style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;background:#fff;">
                    <option value="">Aucun rôle (tâches uniquement)</option>
                    <option value="directeur">Directeur</option>
                    <option value="admin">Administrateur</option>
                    <option value="admin2">Agent / Vendeur</option>
                </select>
            </div>
            <!-- PERMISSIONS DANS LA MODAL -->
            <div style="margin:12px 0 16px;padding:12px;background:#F8F9FA;border-radius:8px;border:1px solid #E8ECF0;">
                <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                    <i class="bi bi-list-check"></i> Tâches / Permissions
                </label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:4px;">
                    <?php foreach($permissions_liste as $p): ?>
                        <?php if($p['cle'] != 'parametres'): ?>
                        <label style="display:flex;align-items:center;gap:5px;font-size:0.75rem;color:#333;cursor:pointer;padding:2px;">
                            <input type="checkbox" name="permissions[]" value="<?= $p['cle'] ?>" class="edit-permission" style="accent-color:#C8922A;width:14px;height:14px;">
                            <i class="bi <?= $p['icone'] ?>" style="color:#C8922A;font-size:0.7rem;"></i> <?= $p['nom'] ?>
                        </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;border-top:1px solid #E8ECF0;padding-top:16px;">
                <button type="button" class="btn-admin btn-secondary" onclick="closeEditModal()" style="padding:10px 24px;background:#f0f0f0;border:none;border-radius:8px;cursor:pointer;font-weight:600;">
                    Annuler
                </button>
                <button type="submit" class="btn-admin btn-primary" style="padding:10px 28px;background:#C8922A;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;transition:background 0.3s;" onmouseover="this.style.background='#b07a1a'" onmouseout="this.style.background='#C8922A'">
                    <i class="bi bi-save"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>

<script>
function openEditModal(id, nom, role) {
    document.getElementById('edit_admin_id').value = id;
    document.getElementById('edit_nom').value = nom;
    document.getElementById('edit_role').value = role || '';
    
    // Charger les permissions existantes
    <?php foreach($admins as $a): ?>
        if (id == <?= $a['id'] ?>) {
            var perms = <?= json_encode(!empty($a['permissions']) ? json_decode($a['permissions'], true) : []) ?>;
            document.querySelectorAll('.edit-permission').forEach(function(cb) {
                cb.checked = perms.includes(cb.value);
            });
        }
    <?php endforeach; ?>
    
    document.getElementById('editModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});
</script>