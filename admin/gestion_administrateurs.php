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
// FONCTION DE GÉNÉRATION DE L'EMAIL D'IDENTIFIANTS
// Design inspiré de functions_securite.php
// ============================================
function generer_email_identifiants($nom, $email, $password, $role_label, $admin_nom, $permissions_liste, $permissions) {
    $date = date('d/m/Y à H:i');
    
    // Construction des tags de permissions
    $permissions_tags_html = '';
    $permissions_noms = [];
    if (!empty($permissions)) {
        foreach ($permissions as $perm) {
            foreach ($permissions_liste as $p) {
                if ($p['cle'] == $perm) {
                    $permissions_noms[] = $p['nom'];
                    $permissions_tags_html .= '
                    <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-block;margin:3px;">
                      <tr><td style="background-color:#FFF8EC;border:1px solid #E9D4A6;border-radius:20px;padding:6px 14px;font-family:Arial,sans-serif;font-size:11.5px;font-weight:bold;color:#8A6020;">' . htmlspecialchars($p['nom']) . '</td></tr>
                    </table>';
                    break;
                }
            }
        }
    }
    if (empty($permissions_tags_html)) {
        $permissions_tags_html = '<span style="font-family:Arial,sans-serif;font-size:12px;color:#B0B8C4;font-style:italic;">Aucune tâche spécifique assignée</span>';
    }
    
    return '
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Vos identifiants - Awa Ka Sugu</title>
    </head>
    <body style="margin:0;padding:0;background-color:#EFEFF2;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#EFEFF2;">
    <tr><td align="center" style="padding:32px 12px;">

    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">

      <tr><td style="background-color:#C8922A;font-size:0;line-height:4px;height:4px;">&nbsp;</td></tr>

      <tr><td style="background-color:#0D0D0D;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
          <td align="center" style="padding:32px 30px 26px;">
            <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
              <td style="width:52px;height:52px;border-radius:50%;border:1.5px solid #C8922A;background-color:#161310;text-align:center;vertical-align:middle;font-family:Georgia,\'Times New Roman\',serif;font-weight:bold;font-size:19px;color:#C8922A;">AK</td>
            </tr></table>
            <div style="height:14px;line-height:14px;font-size:1px;">&nbsp;</div>
            <div style="font-family:Georgia,\'Times New Roman\',serif;font-size:22px;font-weight:bold;letter-spacing:4px;color:#C8922A;text-transform:uppercase;">Awa Ka Sugu</div>
            <div style="height:8px;line-height:8px;font-size:1px;">&nbsp;</div>
            <div style="font-family:Arial,sans-serif;font-size:9.5px;letter-spacing:2px;color:#8a8378;text-transform:uppercase;">Espace Administration</div>
            <div style="height:16px;line-height:16px;font-size:1px;">&nbsp;</div>
            <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
              <td style="background-color:#1C2B1A;border:1px solid #C8922A;border-radius:20px;padding:7px 18px;font-family:Arial,sans-serif;font-size:10.5px;color:#E8B55A;letter-spacing:1px;text-transform:uppercase;font-weight:bold;">&#128075; Bienvenue dans l\'équipe</td>
            </tr></table>
          </td>
        </tr></table>
      </td></tr>

      <tr><td style="padding:32px 30px 8px;">

        <p style="margin:0 0 8px;font-family:Arial,sans-serif;font-size:16.5px;font-weight:bold;color:#0D0D0D;">Bonjour <span style="color:#C8922A;">' . htmlspecialchars($nom) . '</span>,</p>
        <p style="margin:0 0 24px;font-family:Arial,sans-serif;font-size:13px;color:#6B7A8D;line-height:1.7;">Un compte administrateur vient d\'être créé pour vous sur la plateforme <strong>Awa Ka Sugu</strong>. Voici vos informations d\'accès sécurisées.</p>

        <!-- CARTE COMPTE -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F9F9FB;border:1px solid #ECEDF1;border-radius:12px;margin-bottom:22px;">
          <tr><td style="background-color:#0D0D0D;padding:11px 18px;border-radius:11px 11px 0 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
              <td style="font-family:Arial,sans-serif;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8a8378;">Informations du compte</td>
              <td align="right"><span style="background-color:#C8922A;color:#ffffff;font-family:Arial,sans-serif;font-size:10.5px;font-weight:bold;padding:4px 12px;border-radius:20px;">' . htmlspecialchars($role_label) . '</span></td>
            </tr></table>
          </td></tr>
          <tr><td style="padding:12px 18px;border-bottom:1px solid #F0F1F4;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
              <td style="font-family:Arial,sans-serif;font-size:12px;color:#8A92A3;">&#128231; Adresse email</td>
              <td align="right" style="font-family:Arial,sans-serif;font-size:12px;font-weight:bold;color:#1A1A2E;">' . htmlspecialchars($email) . '</td>
            </tr></table>
          </td></tr>
          <tr><td style="padding:12px 18px;border-bottom:1px solid #F0F1F4;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
              <td style="font-family:Arial,sans-serif;font-size:12px;color:#8A92A3;">&#128100; Créé par</td>
              <td align="right" style="font-family:Arial,sans-serif;font-size:12px;font-weight:bold;color:#1A1A2E;">' . htmlspecialchars($admin_nom) . '</td>
            </tr></table>
          </td></tr>
          <tr><td style="padding:12px 18px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
              <td style="font-family:Arial,sans-serif;font-size:12px;color:#8A92A3;">&#128197; Date de création</td>
              <td align="right" style="font-family:\'Courier New\',monospace;font-size:12px;font-weight:bold;color:#1A1A2E;">' . $date . '</td>
            </tr></table>
          </td></tr>
        </table>

        <!-- PERMISSIONS -->
        <div style="font-family:Arial,sans-serif;font-size:10px;font-weight:bold;color:#8A92A3;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px;">&#128203; Tâches assignées</div>
        <div style="margin-bottom:24px;line-height:1.4;">
          ' . $permissions_tags_html . '
        </div>

        <!-- MOT DE PASSE -->
        <div style="font-family:Arial,sans-serif;font-size:10px;font-weight:bold;color:#8A92A3;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px;">&#128273; Votre mot de passe temporaire</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0D0D0D;border:1px solid #C8922A;border-radius:14px;margin-bottom:24px;">
          <tr><td style="padding:20px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
              <td style="font-family:Arial,sans-serif;font-size:11px;color:#8a8378;letter-spacing:1.5px;text-transform:uppercase;">Mot de passe</td>
              <td align="right" style="font-family:\'Courier New\',monospace;font-size:19px;font-weight:bold;color:#E8B55A;letter-spacing:3px;background-color:#1A1510;padding:8px 16px;border-radius:8px;">' . htmlspecialchars($password) . '</td>
            </tr></table>
          </td></tr>
        </table>

        <!-- INFO -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F0F7FF;border:1px solid #C6DCF3;border-radius:12px;margin-bottom:16px;">
          <tr><td style="padding:15px 18px;">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
              <td valign="top" style="font-size:20px;padding-right:12px;">&#128161;</td>
              <td style="font-family:Arial,sans-serif;font-size:12.5px;color:#2C5F8A;line-height:1.6;"><strong>Astuce :</strong> Copiez ce mot de passe et conservez-le en lieu sûr. Vous pourrez le modifier à tout moment depuis votre espace personnel.</td>
            </tr></table>
          </td></tr>
        </table>

        <!-- WARNING -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFF5F5;border:1px solid #F3C6C0;border-radius:12px;margin-bottom:24px;">
          <tr><td style="padding:15px 18px;">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
              <td valign="top" style="font-size:20px;padding-right:12px;">&#9888;&#65039;</td>
              <td style="font-family:Arial,sans-serif;font-size:12.5px;color:#B33A2A;line-height:1.6;"><strong style="color:#922B21;">Important :</strong> Pour votre sécurité, changez ce mot de passe dès votre première connexion. Ne partagez jamais vos identifiants avec qui que ce soit.</td>
            </tr></table>
          </td></tr>
        </table>

        <!-- CTA -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;"><tr><td align="center">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="background-color:#C8922A;border-radius:26px;">
            <a href="http://localhost/awakasugu/admin/login.php" style="display:block;padding:15px 10px;font-family:Arial,sans-serif;font-size:13.5px;font-weight:bold;color:#ffffff;text-decoration:none;letter-spacing:0.5px;">Accéder à l\'administration &rarr;</a>
          </td></tr></table>
        </td></tr></table>

      </td></tr>

      <tr><td style="background-color:#0A0A0A;padding:20px 30px;text-align:center;">
        <div style="font-family:Georgia,serif;font-size:12px;font-weight:bold;color:#C8922A;margin-bottom:6px;">Awa Ka Sugu &mdash; Administration</div>
        <div style="font-family:Arial,sans-serif;font-size:9px;color:#3a3a3a;">&copy; ' . date('Y') . ' Awa Ka Sugu &mdash; Email automatique, merci de ne pas y répondre.</div>
      </td></tr>

    </table>

    </td></tr>
    </table>
    </body>
    </html>
    ';
}

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
            
            $message_html = generer_email_identifiants(
                $nom, $email, $password, $role_label, $admin_nom, $permissions_liste, $permissions
            );
            
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