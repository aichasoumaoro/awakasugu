<?php
// ============================================
// GESTION DES ADMINISTRATEURS - AWA KA SUGU
// ============================================
// Seul le Super Admin peut accéder à cette page
// ============================================

require_once '../includes/session_config.php';
require_once '../includes/functions_securite.php';
require_once '../includes/fonctions_email.php'; // ✅ CORRIGÉ : le bon nom du fichier

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

// AJOUTER UN ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    
    if (empty($nom) || empty($email) || empty($password)) {
        $error = 'Tous les champs sont obligatoires.';
    } else {
        $check = $pdo->prepare("SELECT id FROM admin WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO admin (nom, email, mot_de_passe, role, is_active, created_by) 
                VALUES (?, ?, ?, ?, 1, ?)
            ");
            $stmt->execute([$nom, $email, $hashed_password, $role, $admin_id]);
            
            // ============================================
            // ✅ ENVOI DE L'EMAIL DE CONFIRMATION
            // ============================================
            $sujet = "🔐 Vos identifiants de connexion - Awa Ka Sugu";
            
            $message_html = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background: #f5f5f5; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #C8922A, #8E44AD); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .header h1 { margin: 0; font-size: 24px; }
                    .content { padding: 30px 20px; }
                    .info-box { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #C8922A; margin: 15px 0; }
                    .info-box p { margin: 8px 0; }
                    .label { font-weight: 600; color: #555; }
                    .password-box { background: #fff3cd; padding: 12px; border-radius: 5px; border: 1px dashed #ffc107; text-align: center; font-size: 18px; font-weight: bold; color: #856404; margin: 10px 0; }
                    .btn { display: inline-block; background: #C8922A; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
                    .btn:hover { background: #b07a1a; }
                    .footer { text-align: center; padding: 15px; font-size: 12px; color: #888; border-top: 1px solid #eee; margin-top: 20px; }
                    .role-badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; color: white; background: #C8922A; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🏆 Awa Ka Sugu</h1>
                        <p>Bienvenue dans l'équipe !</p>
                    </div>
                    <div class='content'>
                        <h2>Bonjour <strong>$nom</strong> ! 👋</h2>
                        <p>Un compte administrateur a été créé pour vous sur la plateforme <strong>Awa Ka Sugu</strong>.</p>
                        
                        <div class='info-box'>
                            <p><span class='label'>📧 Email :</span> <strong>$email</strong></p>
                            <p><span class='label'>👤 Rôle :</span> <span class='role-badge'>" . ($role_labels[$role] ?? $role) . "</span></p>
                            <p><span class='label'>🆔 Créé par :</span> $admin_nom</p>
                        </div>
                        
                        <p><strong>🔑 Vos identifiants de connexion :</strong></p>
                        <div class='password-box'>
                            📝 Mot de passe : <strong>$password</strong>
                        </div>
                        
                        <p style='font-size: 14px; color: #e74c3c;'><strong>⚠️ Important :</strong> Nous vous recommandons de changer votre mot de passe lors de votre première connexion.</p>
                        
                        <div style='text-align: center;'>
                            <a href='http://localhost/awakasugu/admin/login.php' class='btn'>🔐 Se connecter</a>
                        </div>
                        
                        <p style='margin-top: 20px; font-size: 14px; color: #888;'>Ce compte vous donne accès à l'espace d'administration. Si vous avez des questions, contactez votre administrateur.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 Awa Ka Sugu - Tous droits réservés</p>
                        <p>Cet email a été généré automatiquement. Merci de ne pas y répondre.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            // ✅ Envoi de l'email
            $email_envoye = envoyerEmail($email, $sujet, $message_html);
            
            // Log de l'action
            enregistrer_log_action(
                $pdo,
                $admin_id,
                $admin_nom,
                $admin_info['email'] ?? '',
                'Ajout administrateur',
                "Ajout de l'administrateur '$nom' avec le rôle '$role'" . ($email_envoye ? " - Email envoyé" : " - Email NON envoyé")
            );
            
            // Message de succès
            if ($email_envoye) {
                $success = "✅ L'administrateur <strong>$nom</strong> a été créé avec succès.<br>📧 Un email de confirmation a été envoyé à <strong>$email</strong>.";
            } else {
                $success = "⚠️ L'administrateur <strong>$nom</strong> a été créé.<br>❌ Mais l'email de confirmation n'a pas pu être envoyé. Vérifiez la configuration.";
            }
        }
    }
}

// MODIFIER UN ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_admin') {
    $edit_id = (int)$_POST['admin_id'];
    $new_role = $_POST['role'] ?? 'admin';
    $new_nom = trim($_POST['nom'] ?? '');
    
    if ($edit_id == $admin_id) {
        $error = "Vous ne pouvez pas modifier votre propre rôle.";
    } else {
        $stmt = $pdo->prepare("SELECT nom, email FROM admin WHERE id = ?");
        $stmt->execute([$edit_id]);
        $admin_modifie = $stmt->fetch();
        
        if ($admin_modifie) {
            $stmt = $pdo->prepare("UPDATE admin SET role = ?, nom = ? WHERE id = ?");
            $stmt->execute([$new_role, $new_nom, $edit_id]);
            
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
            
            enregistrer_log_action(
                $pdo,
                $admin_id,
                $admin_nom,
                $admin_info['email'] ?? '',
                'Activation/Désactivation administrateur',
                "Compte '{$admin_toggle['nom']}' $status_text"
            );
            
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
            
            enregistrer_log_action(
                $pdo,
                $admin_id,
                $admin_nom,
                $admin_info['email'] ?? '',
                'Suppression administrateur',
                "Suppression de l'administrateur '{$admin_delete['nom']}'"
            );
            
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

    <!-- ===== TOPBAR ===== -->
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

    <!-- ===== CONTENT ===== -->
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

        <!-- ===== FORMULAIRE D'AJOUT ===== -->
        <div class="card-white" style="background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:25px;">
            <div class="card-header" style="padding:18px 25px;border-bottom:1px solid #E8ECF0;display:flex;justify-content:space-between;align-items:center;">
                <div class="card-title" style="font-weight:600;color:#1A2C3E;font-size:1rem;"><i class="bi bi-person-plus" style="color:#C8922A;"></i> Ajouter un administrateur</div>
            </div>
            <div class="card-body" style="padding:25px;">
                <form method="POST">
                    <input type="hidden" name="action" value="add_admin">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                Nom complet <span style="color:#E74C3C;">*</span>
                            </label>
                            <input type="text" name="nom" required placeholder="Ex: Awa Doumbia" style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                Email <span style="color:#E74C3C;">*</span>
                            </label>
                            <input type="email" name="email" required placeholder="exemple@awakasugu.ml" style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                Mot de passe <span style="color:#E74C3C;">*</span>
                            </label>
                            <input type="password" name="password" required placeholder="••••••••" minlength="8" style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr auto;gap:12px;margin-top:12px;align-items:end;">
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                Rôle <span style="color:#E74C3C;">*</span>
                            </label>
                            <select name="role" style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;background:#fff;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
                                <option value="directeur">Directeur</option>
                                <option value="admin" selected>Administrateur</option>
                                <option value="admin2">Agent / Vendeur</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-admin btn-primary" style="padding:10px 30px;white-space:nowrap;background:#C8922A;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;transition:background 0.3s;" onmouseover="this.style.background='#b07a1a'" onmouseout="this.style.background='#C8922A'">
                            <i class="bi bi-plus-circle"></i> Ajouter
                        </button>
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
                    <?php foreach($admins as $a): ?>
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
                                </div>
                                <div style="font-size:0.8rem;color:#5A6B7A;">
                                    <?= htmlspecialchars($a['email']) ?>
                                </div>
                                <div style="font-size:0.7rem;color:#8A99AA;margin-top:2px;">
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
                                <?= $role_labels[$a['role']] ?? $a['role'] ?>
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
    <div class="modal-content" style="max-width:480px;width:95%;padding:28px;background:#fff;border-radius:12px;position:relative;">
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
                <input type="text" name="nom" id="edit_nom" required style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                    Rôle
                </label>
                <select name="role" id="edit_role" style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;background:#fff;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
                    <option value="directeur">Directeur</option>
                    <option value="admin">Administrateur</option>
                    <option value="admin2">Agent / Vendeur</option>
                </select>
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
    document.getElementById('edit_role').value = role;
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