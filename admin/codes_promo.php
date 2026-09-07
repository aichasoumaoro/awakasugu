<?php
// ============================================
// CODES PROMO - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';

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
$admin_nom = $admin_info['nom'] ?? 'Awa Doumbia';
$admin_id = $admin_info['id'] ?? 0;

// Vérification des permissions (Codes promo visible pour super_admin et directeur uniquement)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Gestion des Codes Promo';

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
    die("Erreur : " . $e->getMessage());
}

// ============================================
// AJOUTER UN CODE PROMO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter'])) {
    $code = strtoupper(trim($_POST['code']));
    $type = $_POST['type'];
    $valeur = (float)$_POST['valeur'];
    $min_achat = (float)($_POST['min_achat'] ?? 0);
    $nb_utilisations_max = isset($_POST['nb_utilisations_max']) && $_POST['nb_utilisations_max'] !== '' ? (int)$_POST['nb_utilisations_max'] : null;
    $date_expiration = !empty($_POST['date_expiration']) ? $_POST['date_expiration'] : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO codes_promo 
            (code, type, valeur, min_achat, nb_utilisations_max, date_expiration, est_actif, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$code, $type, $valeur, $min_achat, $nb_utilisations_max, $date_expiration]);
        $_SESSION['message_promo'] = 'Code promo ajouté avec succès !';
    } catch(PDOException $e) {
        $_SESSION['error_promo'] = 'Erreur : ' . $e->getMessage();
    }
    header('Location: codes_promo.php');
    exit;
}

// ============================================
// MODIFIER UN CODE PROMO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier'])) {
    $id = (int)$_POST['id'];
    $code = strtoupper(trim($_POST['code']));
    $type = $_POST['type'];
    $valeur = (float)$_POST['valeur'];
    $min_achat = (float)($_POST['min_achat'] ?? 0);
    $nb_utilisations_max = isset($_POST['nb_utilisations_max']) && $_POST['nb_utilisations_max'] !== '' ? (int)$_POST['nb_utilisations_max'] : null;
    $date_expiration = !empty($_POST['date_expiration']) ? $_POST['date_expiration'] : null;

    try {
        $stmt = $pdo->prepare("
            UPDATE codes_promo 
            SET code = ?, type = ?, valeur = ?, min_achat = ?, nb_utilisations_max = ?, date_expiration = ?
            WHERE id = ?
        ");
        $stmt->execute([$code, $type, $valeur, $min_achat, $nb_utilisations_max, $date_expiration, $id]);
        $_SESSION['message_promo'] = 'Code promo modifié avec succès !';
    } catch(PDOException $e) {
        $_SESSION['error_promo'] = 'Erreur : ' . $e->getMessage();
    }
    header('Location: codes_promo.php');
    exit;
}

// ============================================
// ACTIVER / DÉSACTIVER UN CODE PROMO
// ============================================
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT est_actif FROM codes_promo WHERE id = ?");
    $stmt->execute([$id]);
    $promo = $stmt->fetch();
    if ($promo) {
        $new_status = $promo['est_actif'] ? 0 : 1;
        $pdo->prepare("UPDATE codes_promo SET est_actif = ? WHERE id = ?")->execute([$new_status, $id]);
        $_SESSION['message_promo'] = $new_status ? 'Code promo activé' : 'Code promo désactivé';
    }
    header('Location: codes_promo.php');
    exit;
}

// ============================================
// SUPPRIMER UN CODE PROMO
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM codes_promo WHERE id = ?")->execute([$id]);
    $_SESSION['message_promo'] = 'Code promo supprimé avec succès !';
    header('Location: codes_promo.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================
$codes = $pdo->query("SELECT * FROM codes_promo ORDER BY id DESC")->fetchAll();

// Statistiques
$total_codes = count($codes);
$codes_actifs = 0;
$codes_expires = 0;
foreach($codes as $c) {
    if($c['est_actif']) $codes_actifs++;
    if($c['date_expiration'] && strtotime($c['date_expiration']) < time()) $codes_expires++;
}

$message = $_SESSION['message_promo'] ?? '';
$error = $_SESSION['error_promo'] ?? '';
unset($_SESSION['message_promo']);
unset($_SESSION['error_promo']);

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
            <div class="topbar-title">🏷️ Gestion des <span>Codes promo</span></div>
            <div class="topbar-breadcrumb">Marketing → Codes promo</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <?php if($message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-gold"><i class="bi bi-tags"></i></div>
                <div>
                    <div class="stat-val"><?= $total_codes ?></div>
                    <div class="stat-lbl">Total codes</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $codes_actifs ?></div>
                    <div class="stat-lbl">Codes actifs</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-val"><?= $codes_expires ?></div>
                    <div class="stat-lbl">Codes expirés</div>
                </div>
            </div>
        </div>

        <!-- ===== AJOUTER UN CODE PROMO ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-plus-circle"></i> Ajouter un code promo</div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:12px;align-items:end;">
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Code *</label>
                            <input type="text" name="code" placeholder="EX: BIENVENUE10" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;transition:border-color 0.3s;" required onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Type *</label>
                            <select name="type" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;background:#fff;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
                                <option value="pourcentage">Pourcentage (%)</option>
                                <option value="fixe">Montant fixe (FCFA)</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Valeur *</label>
                            <input type="number" name="valeur" placeholder="10" step="0.01" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;transition:border-color 0.3s;" required onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Achat mini</label>
                            <input type="number" name="min_achat" placeholder="0" step="0.01" value="0" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Utilisations max</label>
                            <input type="number" name="nb_utilisations_max" placeholder="Illimité" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Date expiration</label>
                            <input type="date" name="date_expiration" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E8ECF0'">
                        </div>
                        <button type="submit" name="ajouter" style="background:#C8922A;color:#fff;border:none;border-radius:8px;padding:9px 20px;font-weight:600;cursor:pointer;font-family:'Jost',sans-serif;font-size:0.82rem;transition:all 0.3s;white-space:nowrap;" onmouseover="this.style.background='#9A6E1A'" onmouseout="this.style.background='#C8922A'">
                            <i class="bi bi-plus"></i> Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===== LISTE DES CODES PROMO ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-tags"></i> Liste des codes promo</div>
                <div style="font-size:0.7rem;color:#8A99AA;background:#F8F9FA;padding:4px 16px;border-radius:20px;border:1px solid #E8ECF0;">
                    <strong style="color:#C8922A;"><?= count($codes) ?></strong> code(s)
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-codes" style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Code</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Type</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Valeur</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Achat mini</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Expiration</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Utilisations</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Statut</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;width:120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($codes)): ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state" style="text-align:center;padding:40px;color:#8A99AA;">
                                            <i class="bi bi-tags" style="font-size:2.5rem;display:block;margin-bottom:10px;color:#D5D5D5;"></i>
                                            <p style="margin:0;font-size:0.85rem;">Aucun code promo</p>
                                            <span style="font-size:0.75rem;color:#bbb;display:block;margin-top:4px;">Créez votre premier code promo ci-dessus</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($codes as $c): 
                                    $est_expire = $c['date_expiration'] && strtotime($c['date_expiration']) < time();
                                    $est_utilise_max = $c['nb_utilisations_max'] !== null && $c['nb_utilisations'] >= $c['nb_utilisations_max'];
                                    
                                    $status_label = 'Actif';
                                    $status_class = 'active';
                                    if($est_expire || $est_utilise_max) {
                                        $status_label = 'Expiré';
                                        $status_class = 'expired';
                                    } elseif(!$c['est_actif']) {
                                        $status_label = 'Inactif';
                                        $status_class = 'inactive';
                                    }
                                ?>
                                <tr style="transition:background 0.2s;">
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;">
                                        <span style="font-weight:700;color:#C8922A;font-size:0.9rem;background:rgba(200,146,42,0.08);padding:3px 12px;border-radius:6px;font-family:'Playfair Display',serif;">
                                            <?= htmlspecialchars($c['code']) ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:0.65rem;font-weight:600;background:<?= $c['type'] === 'pourcentage' ? 'rgba(200,146,42,0.1)' : 'rgba(41,128,185,0.1)' ?>;color:<?= $c['type'] === 'pourcentage' ? '#C8922A' : '#2980B9' ?>;">
                                            <?= $c['type'] === 'pourcentage' ? '%' : 'FCFA' ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;font-weight:700;color:#1A2C3E;font-size:0.9rem;">
                                        <?= number_format($c['valeur'], 0) ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;font-size:0.8rem;color:#5A6B7A;">
                                        <?= number_format($c['min_achat'], 0) ?> F
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;font-size:0.75rem;color:#8A99AA;">
                                        <?php if($c['date_expiration']): ?>
                                            <i class="bi bi-calendar3" style="font-size:0.6rem;"></i>
                                            <?= date('d/m/Y', strtotime($c['date_expiration'])) ?>
                                            <?php if($est_expire): ?>
                                                <br><span style="color:#E74C3C;font-size:0.6rem;font-weight:600;">(expiré)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:#8A99AA;">Illimité</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;font-size:0.8rem;">
                                        <?php if($c['nb_utilisations_max'] !== null): ?>
                                            <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-weight:600;background:<?= ($c['nb_utilisations'] >= $c['nb_utilisations_max']) ? 'rgba(231,76,60,0.1)' : 'rgba(40,167,69,0.1)' ?>;color:<?= ($c['nb_utilisations'] >= $c['nb_utilisations_max']) ? '#E74C3C' : '#28A745' ?>;">
                                                <?= $c['nb_utilisations'] ?> / <?= $c['nb_utilisations_max'] ?>
                                            </span>
                                            <?php if($est_utilise_max): ?>
                                                <br><span style="color:#E74C3C;font-size:0.6rem;font-weight:600;">(épuisé)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="display:inline-block;padding:2px 12px;border-radius:12px;background:#F0F2F5;color:#8A99AA;font-weight:600;">
                                                <?= $c['nb_utilisations'] ?> / ∞
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <span class="badge-status <?= $status_class ?>" style="padding:3px 12px;border-radius:20px;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;display:inline-block;">
                                            <?= $status_label ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <?php if(!$est_expire && !$est_utilise_max): ?>
                                                <a href="codes_promo.php?toggle=<?= $c['id'] ?>" class="btn-small <?= $c['est_actif'] ? 'gray' : 'green' ?>" title="<?= $c['est_actif'] ? 'Désactiver' : 'Activer' ?>" style="padding:4px 10px;border-radius:6px;font-size:0.65rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:<?= $c['est_actif'] ? '#F0F2F5' : 'rgba(40,167,69,0.1)' ?>;color:<?= $c['est_actif'] ? '#5A6B7A' : '#28A745' ?>;transition:all 0.2s;border:none;cursor:pointer;">
                                                    <i class="bi bi-<?= $c['est_actif'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($c)) ?>)" class="btn-small blue" title="Modifier" style="padding:4px 10px;border-radius:6px;font-size:0.65rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:rgba(41,128,185,0.1);color:#2980B9;transition:all 0.2s;border:none;cursor:pointer;">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="codes_promo.php?supprimer=<?= $c['id'] ?>" class="btn-small red" onclick="return confirm('Supprimer ce code promo ?')" title="Supprimer" style="padding:4px 10px;border-radius:6px;font-size:0.65rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:rgba(231,76,60,0.1);color:#E74C3C;transition:all 0.2s;border:none;cursor:pointer;">
                                                <i class="bi bi-trash3"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ===== MODAL ÉDITION ===== -->
<div class="modal-overlay" id="editModal" onclick="if(event.target===this) closeEditModal()">
    <div class="modal-content" style="max-width:550px;width:95%;padding:28px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-family:'Playfair Display',serif;font-size:1.1rem;margin:0;">
                ✏️ Modifier le code promo
            </h3>
            <button onclick="closeEditModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#999;transition:transform 0.3s;line-height:1;" onmouseover="this.style.transform='rotate(90deg)'" onmouseout="this.style.transform='rotate(0deg)'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="grid-column:1/-1;">
                    <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Code *</label>
                    <input type="text" name="code" id="edit_code" placeholder="Code" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;" required>
                </div>
                <div>
                    <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Type *</label>
                    <select name="type" id="edit_type" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;background:#fff;">
                        <option value="pourcentage">Pourcentage (%)</option>
                        <option value="fixe">Montant fixe (FCFA)</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Valeur *</label>
                    <input type="number" name="valeur" id="edit_valeur" placeholder="Valeur" step="0.01" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;" required>
                </div>
                <div>
                    <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Achat mini</label>
                    <input type="number" name="min_achat" id="edit_min_achat" placeholder="0" step="0.01" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;">
                </div>
                <div>
                    <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Utilisations max</label>
                    <input type="number" name="nb_utilisations_max" id="edit_nb_utilisations_max" placeholder="Illimité" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;">
                </div>
                <div>
                    <label style="display:block;font-size:0.65rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Date expiration</label>
                    <input type="date" name="date_expiration" id="edit_date_expiration" style="width:100%;padding:9px 12px;border:1.5px solid #E8ECF0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;">
                </div>
                <button type="submit" name="modifier" style="grid-column:1/-1;background:#C8922A;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-weight:600;cursor:pointer;font-family:'Jost',sans-serif;font-size:0.82rem;transition:all 0.3s;" onmouseover="this.style.background='#9A6E1A'" onmouseout="this.style.background='#C8922A'">
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
// ============================================
// MODAL D'ÉDITION
// ============================================
function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_code').value = data.code;
    document.getElementById('edit_type').value = data.type;
    document.getElementById('edit_valeur').value = data.valeur;
    document.getElementById('edit_min_achat').value = data.min_achat;
    document.getElementById('edit_nb_utilisations_max').value = data.nb_utilisations_max || '';
    document.getElementById('edit_date_expiration').value = data.date_expiration ? data.date_expiration.split(' ')[0] : '';
    document.getElementById('editModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});
</script>