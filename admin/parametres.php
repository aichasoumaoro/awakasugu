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
// CRÉER LA TABLE DES PARAMÈTRES FONCTIONNALITÉS SI ELLE N'EXISTE PAS
// ============================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS parametres_fonctionnalites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cle VARCHAR(100) NOT NULL UNIQUE,
            valeur TEXT,
            description VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch(PDOException $e) {}

// ============================================
// INSÉRER LES PARAMÈTRES DE FIDÉLITÉ PAR DÉFAUT SI NÉCESSAIRE
// ============================================
$params_defaut_fidelite = [
    'fidelite_seuil_points' => ['50000', 'Montant d\'achat pour gagner 1 point (FCFA)'],
    'fidelite_points_par_seuil' => ['1', 'Nombre de points gagnés par seuil'],
    'fidelite_reduction_points' => ['10', 'Nombre de points nécessaires pour une réduction'],
    'fidelite_reduction_montant' => ['1000', 'Montant de la réduction (FCFA)'],
    'fidelite_points_expiration' => ['365', 'Jours avant expiration des points (0 = jamais)'],
    'fidelite_actif' => ['1', 'Activer/désactiver le système (1=actif, 0=inactif)'],
    'fidelite_points_par_commande' => ['1', 'Points bonus par commande (fixe)']
];

foreach ($params_defaut_fidelite as $cle => $data) {
    $stmt = $pdo->prepare("SELECT id FROM parametres_fonctionnalites WHERE cle = ?");
    $stmt->execute([$cle]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO parametres_fonctionnalites (cle, valeur, description) VALUES (?, ?, ?)");
        $stmt->execute([$cle, $data[0], $data[1]]);
    }
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
// RÉCUPÉRER LES PARAMÈTRES DE FIDÉLITÉ
// ============================================
$stmt = $pdo->query("SELECT cle, valeur, description FROM parametres_fonctionnalites WHERE cle LIKE 'fidelite_%'");
$params_fidelite = $stmt->fetchAll(PDO::FETCH_ASSOC);
$fidelite_values = [];
foreach ($params_fidelite as $p) {
    $fidelite_values[$p['cle']] = $p;
}

// ============================================
// SAUVEGARDER LES PARAMÈTRES
// ============================================
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ============================================
    // SAUVEGARDER LES PARAMÈTRES GÉNÉRAUX
    // ============================================
    if (isset($_POST['save_params'])) {
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
            
            // Mettre à jour la maintenance globale
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
    // SAUVEGARDER LES PARAMÈTRES DE FIDÉLITÉ
    // ============================================
    if (isset($_POST['save_fidelite'])) {
        try {
            foreach ($_POST as $cle => $valeur) {
                if (strpos($cle, 'fidelite_') === 0) {
                    $stmt = $pdo->prepare("UPDATE parametres_fonctionnalites SET valeur = ? WHERE cle = ?");
                    $stmt->execute([$valeur, $cle]);
                }
            }
            
            enregistrer_log_action(
                $pdo,
                $admin_id,
                $admin_nom,
                $admin_info['email'] ?? '',
                'modification paramètres fidélité',
                "Mise à jour des paramètres de fidélité"
            );
            
            $success_fidelite = "Les paramètres de fidélité ont été mis à jour avec succès !";
            
            // Recharger les paramètres
            $stmt = $pdo->query("SELECT cle, valeur, description FROM parametres_fonctionnalites WHERE cle LIKE 'fidelite_%'");
            $params_fidelite = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $fidelite_values = [];
            foreach ($params_fidelite as $p) {
                $fidelite_values[$p['cle']] = $p;
            }
            
        } catch(PDOException $e) {
            $error_fidelite = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
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
        <?php if(isset($success_fidelite)): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success_fidelite) ?></div>
        <?php endif; ?>
        <?php if(isset($error_fidelite)): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error_fidelite) ?></div>
        <?php endif; ?>

        <!-- ============================================
        SECTION PARAMÈTRES GÉNÉRAUX
        ============================================ -->
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

        <!-- ============================================
        SECTION PARAMÈTRES DE FIDÉLITÉ
        ============================================ -->
        <div class="card-white" style="margin-top:30px;">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-star" style="color:#C8922A;"></i> Programme de fidélité
                </div>
                <div style="font-size:0.7rem;color:#8A99AA;">
                    <i class="bi bi-info-circle"></i> Configurez les règles du programme
                </div>
            </div>
            <div class="card-body">
                
                <!-- Aperçu des règles actuelles -->
                <div style="background:#FEFBF5;border-radius:10px;padding:15px 20px;margin-bottom:20px;border:1px solid rgba(200,146,42,0.1);border-left:4px solid #C8922A;">
                    <div style="display:flex;flex-wrap:wrap;gap:20px;justify-content:space-between;">
                        <div>
                            <div style="font-size:0.75rem;color:#8A99AA;">Règle actuelle</div>
                            <div style="font-weight:600;color:#0D0D0D;">
                                <?= number_format((int)($fidelite_values['fidelite_seuil_points']['valeur'] ?? 50000), 0, ' ', ' ') ?> FCFA d'achat = 
                                <span style="color:#C8922A;"><?= $fidelite_values['fidelite_points_par_seuil']['valeur'] ?? 1 ?></span> point(s)
                            </div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem;color:#8A99AA;">Réduction</div>
                            <div style="font-weight:600;color:#0D0D0D;">
                                <span style="color:#C8922A;"><?= $fidelite_values['fidelite_reduction_points']['valeur'] ?? 10 ?></span> points = 
                                <span style="color:#27AE60;"><?= number_format((int)($fidelite_values['fidelite_reduction_montant']['valeur'] ?? 1000), 0, ' ', ' ') ?> FCFA</span>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem;color:#8A99AA;">Statut</div>
                            <div>
                                <?php if(($fidelite_values['fidelite_actif']['valeur'] ?? 1) == 1): ?>
                                    <span style="background:#D4EDDA;color:#155724;padding:3px 14px;border-radius:20px;font-size:0.75rem;font-weight:600;">
                                        <i class="bi bi-check-circle"></i> Actif
                                    </span>
                                <?php else: ?>
                                    <span style="background:#F8D7DA;color:#721C24;padding:3px 14px;border-radius:20px;font-size:0.75rem;font-weight:600;">
                                        <i class="bi bi-x-circle"></i> Inactif
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        
                        <!-- Seuil pour 1 point -->
                        <div style="margin-bottom:12px;">
                            <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                <i class="bi bi-coin"></i> Seuil pour 1 point (FCFA)
                            </label>
                            <input type="number" name="fidelite_seuil_points" 
                                   value="<?= $fidelite_values['fidelite_seuil_points']['valeur'] ?? 50000 ?>"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;"
                                   min="100" step="1000">
                            <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">
                                Exemple: 50 000 FCFA = 1 point
                            </div>
                        </div>
                        
                        <!-- Points par seuil -->
                        <div style="margin-bottom:12px;">
                            <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                <i class="bi bi-star"></i> Points par seuil
                            </label>
                            <input type="number" name="fidelite_points_par_seuil" 
                                   value="<?= $fidelite_values['fidelite_points_par_seuil']['valeur'] ?? 1 ?>"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;"
                                   min="1" step="1">
                            <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">
                                Nombre de points gagnés par seuil atteint
                            </div>
                        </div>
                        
                        <!-- Points pour réduction -->
                        <div style="margin-bottom:12px;">
                            <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                <i class="bi bi-gift"></i> Points pour une réduction
                            </label>
                            <input type="number" name="fidelite_reduction_points" 
                                   value="<?= $fidelite_values['fidelite_reduction_points']['valeur'] ?? 10 ?>"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;"
                                   min="1" step="1">
                            <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">
                                Exemple: 10 points = 1 000 FCFA de réduction
                            </div>
                        </div>
                        
                        <!-- Montant de la réduction -->
                        <div style="margin-bottom:12px;">
                            <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                <i class="bi bi-cash"></i> Montant de la réduction (FCFA)
                            </label>
                            <input type="number" name="fidelite_reduction_montant" 
                                   value="<?= $fidelite_values['fidelite_reduction_montant']['valeur'] ?? 1000 ?>"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;"
                                   min="100" step="100">
                            <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">
                                Montant de réduction pour le nombre de points défini
                            </div>
                        </div>
                        
                        <!-- Expiration des points -->
                        <div style="margin-bottom:12px;">
                            <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                <i class="bi bi-clock"></i> Expiration des points (jours)
                            </label>
                            <input type="number" name="fidelite_points_expiration" 
                                   value="<?= $fidelite_values['fidelite_points_expiration']['valeur'] ?? 365 ?>"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;"
                                   min="0" step="30">
                            <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">
                                0 = jamais, 365 = 1 an
                            </div>
                        </div>
                        
                        <!-- Points bonus par commande -->
                        <div style="margin-bottom:12px;">
                            <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                <i class="bi bi-plus-circle"></i> Points bonus par commande
                            </label>
                            <input type="number" name="fidelite_points_par_commande" 
                                   value="<?= $fidelite_values['fidelite_points_par_commande']['valeur'] ?? 1 ?>"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;"
                                   min="0" step="1">
                            <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">
                                Points bonus fixe en plus pour chaque commande
                            </div>
                        </div>
                        
                        <!-- Activer/désactiver -->
                        <div style="margin-bottom:12px;">
                            <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                <i class="bi bi-power"></i> Statut du programme
                            </label>
                            <select name="fidelite_actif" 
                                    style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;">
                                <option value="1" <?= ($fidelite_values['fidelite_actif']['valeur'] ?? 1) == 1 ? 'selected' : '' ?>>
                                    ✅ Actif
                                </option>
                                <option value="0" <?= ($fidelite_values['fidelite_actif']['valeur'] ?? 1) == 0 ? 'selected' : '' ?>>
                                    ❌ Inactif
                                </option>
                            </select>
                            <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">
                                Désactiver le programme de fidélité temporairement
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Exemple de calcul -->
                    <div style="margin-top:15px;padding:15px;background:#F8F9FA;border-radius:8px;border:1px dashed #E0E6ED;">
                        <div style="font-size:0.8rem;color:#666;margin-bottom:8px;">
                            <i class="bi bi-calculator" style="color:#C8922A;"></i> 
                            <strong>Aperçu du calcul :</strong>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;font-size:0.8rem;">
                            <div>
                                <span style="color:#8A99AA;">Pour un achat de 150 000 FCFA :</span>
                                <div style="font-weight:600;color:#C8922A;">
                                    <?php 
                                    $seuil = (int)($fidelite_values['fidelite_seuil_points']['valeur'] ?? 50000);
                                    $pts_par_seuil = (int)($fidelite_values['fidelite_points_par_seuil']['valeur'] ?? 1);
                                    $points_ex = floor(150000 / $seuil) * $pts_par_seuil;
                                    echo $points_ex . ' point(s)';
                                    ?>
                                </div>
                            </div>
                            <div>
                                <span style="color:#8A99AA;">Points disponibles :</span>
                                <div style="font-weight:600;color:#C8922A;">
                                    <?php 
                                    $pts_reduc = (int)($fidelite_values['fidelite_reduction_points']['valeur'] ?? 10);
                                    echo $points_ex . ' points';
                                    ?>
                                </div>
                            </div>
                            <div>
                                <span style="color:#8A99AA;">Réduction possible :</span>
                                <div style="font-weight:600;color:#27AE60;">
                                    <?php 
                                    $montant_reduc = (int)($fidelite_values['fidelite_reduction_montant']['valeur'] ?? 1000);
                                    $reduc_ex = floor($points_ex / $pts_reduc) * $montant_reduc;
                                    echo number_format($reduc_ex, 0, ' ', ' ') . ' FCFA';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="save_fidelite" class="btn-admin btn-primary" style="padding:12px 30px;font-size:0.9rem;margin-top:15px;background:linear-gradient(135deg,#C8922A,#E8B55A);">
                        <i class="bi bi-star"></i> Enregistrer les paramètres de fidélité
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