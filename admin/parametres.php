<?php
// ============================================
// PARAMÈTRES - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';
require_once '../includes/functions_securite.php';

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

$page_title = 'Paramètres';

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
// RÉCUPÉRER LES PARAMÈTRES ACTUELS
// ============================================
$parametres = $pdo->query("SELECT * FROM parametres WHERE id = 1")->fetch();

if (!$parametres) {
    // Créer les paramètres par défaut
    $pdo->exec("
        INSERT INTO parametres (id, nom_site, description_site, email_contact, telephone_contact, 
        adresse, email_notification, taux_tva, frais_livraison, delai_livraison, 
        devise, icone, logo, favicon, footer_text, maintenance, updated_at) 
        VALUES (1, 'Awa Ka Sugu', 'Boutique en ligne de vêtements et accessoires', 
        'contact@awakasugu.ml', '+223 75 00 00 00', 'Bamako, Mali', 
        'admin@awakasugu.ml', 18, 1000, '3-5 jours', 'FCFA', '', '', '', 
        '© 2024 Awa Ka Sugu - Tous droits réservés', 0, NOW())
    ");
    $parametres = $pdo->query("SELECT * FROM parametres WHERE id = 1")->fetch();
}

// ============================================
// SAUVEGARDER LES PARAMÈTRES
// ============================================
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_params'])) {
    $nom_site = trim($_POST['nom_site'] ?? 'Awa Ka Sugu');
    $description_site = trim($_POST['description_site'] ?? '');
    $email_contact = trim($_POST['email_contact'] ?? '');
    $telephone_contact = trim($_POST['telephone_contact'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $email_notification = trim($_POST['email_notification'] ?? '');
    $taux_tva = (float)($_POST['taux_tva'] ?? 0);
    $frais_livraison = (float)($_POST['frais_livraison'] ?? 0);
    $delai_livraison = trim($_POST['delai_livraison'] ?? '');
    $devise = trim($_POST['devise'] ?? 'FCFA');
    $footer_text = trim($_POST['footer_text'] ?? '');
    $maintenance = isset($_POST['maintenance']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE parametres SET 
            nom_site = ?, description_site = ?, email_contact = ?, 
            telephone_contact = ?, adresse = ?, email_notification = ?, 
            taux_tva = ?, frais_livraison = ?, delai_livraison = ?, 
            devise = ?, footer_text = ?, maintenance = ?, updated_at = NOW()
            WHERE id = 1
        ");
        $stmt->execute([
            $nom_site, $description_site, $email_contact, 
            $telephone_contact, $adresse, $email_notification,
            $taux_tva, $frais_livraison, $delai_livraison,
            $devise, $footer_text, $maintenance
        ]);
        
        // Mettre à jour la maintenance globale si le paramètre a changé
        if ($maintenance != ($parametres['maintenance'] ?? 0)) {
            $pdo->prepare("
                UPDATE maintenance_globale 
                SET site_actif = ? 
                WHERE id = (SELECT id FROM (SELECT id FROM maintenance_globale ORDER BY id DESC LIMIT 1) as tmp)
            ")->execute([$maintenance ? 0 : 1]);
        }
        
        enregistrer_log_action(
            $pdo,
            $admin_id,
            $admin_nom,
            $admin_info['email'] ?? '',
            'modification paramètres',
            "Mise à jour des paramètres du site"
        );
        
        $success = "Les paramètres ont été mis à jour avec succès !";
        
        // Recharger les paramètres
        $parametres = $pdo->query("SELECT * FROM parametres WHERE id = 1")->fetch();
        
    } catch(PDOException $e) {
        $error = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
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
            <div class="topbar-title">⚙️ <span>Paramètres</span> du site</div>
            <div class="topbar-breadcrumb">Super Admin → Paramètres</div>
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
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ===== FORMULAIRE ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-gear"></i> Configuration générale</div>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <!-- Colonne gauche -->
                        <div>
                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                    Nom du site <span style="color:#E74C3C;">*</span>
                                </label>
                                <input type="text" name="nom_site" value="<?= htmlspecialchars($parametres['nom_site'] ?? 'Awa Ka Sugu') ?>" 
                                       style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                            </div>
                            
                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                    Description du site
                                </label>
                                <textarea name="description_site" rows="3" 
                                          style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;resize:vertical;"><?= htmlspecialchars($parametres['description_site'] ?? '') ?></textarea>
                            </div>
                            
                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                    Email contact
                                </label>
                                <input type="email" name="email_contact" value="<?= htmlspecialchars($parametres['email_contact'] ?? '') ?>" 
                                       style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                            </div>
                            
                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                    Téléphone contact
                                </label>
                                <input type="text" name="telephone_contact" value="<?= htmlspecialchars($parametres['telephone_contact'] ?? '') ?>" 
                                       style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                            </div>
                            
                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                    Adresse
                                </label>
                                <input type="text" name="adresse" value="<?= htmlspecialchars($parametres['adresse'] ?? '') ?>" 
                                       style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                            </div>
                        </div>
                        
                        <!-- Colonne droite -->
                        <div>
                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                    Email notifications
                                </label>
                                <input type="email" name="email_notification" value="<?= htmlspecialchars($parametres['email_notification'] ?? '') ?>" 
                                       style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                            </div>
                            
                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                    Taux TVA (%)
                                </label>
                                <input type="number" name="taux_tva" value="<?= htmlspecialchars($parametres['taux_tva'] ?? 18) ?>" step="0.01" min="0"
                                       style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                            </div>
                            
                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                    Frais de livraison (FCFA)
                                </label>
                                <input type="number" name="frais_livraison" value="<?= htmlspecialchars($parametres['frais_livraison'] ?? 1000) ?>" step="100" min="0"
                                       style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                            </div>
                            
                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                    Délai de livraison
                                </label>
                                <input type="text" name="delai_livraison" value="<?= htmlspecialchars($parametres['delai_livraison'] ?? '3-5 jours') ?>" 
                                       style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                            </div>
                            
                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                    Devise
                                </label>
                                <input type="text" name="devise" value="<?= htmlspecialchars($parametres['devise'] ?? 'FCFA') ?>" 
                                       style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer texte -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                            Texte du footer
                        </label>
                        <input type="text" name="footer_text" value="<?= htmlspecialchars($parametres['footer_text'] ?? '© 2024 Awa Ka Sugu - Tous droits réservés') ?>" 
                               style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                    </div>
                    
                    <!-- Maintenance -->
                    <div style="margin-bottom:20px;padding:16px 20px;background:#FEFBF5;border-radius:8px;border:1px solid rgba(200,146,42,0.15);">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <input type="checkbox" name="maintenance" id="maintenance" value="1" <?= ($parametres['maintenance'] ?? 0) ? 'checked' : '' ?>
                                   style="width:20px;height:20px;accent-color:#C8922A;">
                            <label for="maintenance" style="font-weight:600;color:#1A2C3E;font-size:0.9rem;">
                                Activer le mode maintenance
                            </label>
                            <span style="font-size:0.7rem;color:#8A99AA;background:#F0F2F5;padding:2px 12px;border-radius:12px;">
                                ⚠️ Seuls les administrateurs peuvent accéder au site
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" name="save_params" class="btn-admin btn-primary" style="padding:12px 30px;font-size:0.9rem;">
                        <i class="bi bi-save"></i> Enregistrer les paramètres
                    </button>
                </form>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>