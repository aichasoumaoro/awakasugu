<?php
// ============================================
// MAINTENANCE - ADMIN AWA KA SUGU
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

$page_title = 'Gestion de la Maintenance';

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
// TOGGLE MAINTENANCE GLOBALE
// ============================================
if (isset($_GET['toggle_global'])) {
    $stmt = $pdo->query("SELECT site_actif FROM maintenance_globale ORDER BY id DESC LIMIT 1");
    $current = $stmt->fetch();
    $new_status = $current['site_actif'] == 1 ? 0 : 1;
    $pdo->prepare("UPDATE maintenance_globale SET site_actif = ? WHERE id = (SELECT id FROM (SELECT id FROM maintenance_globale ORDER BY id DESC LIMIT 1) as tmp)")->execute([$new_status]);
    $_SESSION['message_maintenance'] = 'Statut global du site modifié.';
    header('Location: maintenance.php');
    exit;
}

// ============================================
// TOGGLE D'UNE PAGE
// ============================================
if (isset($_GET['toggle']) && isset($_GET['page'])) {
    $page = $_GET['page'];
    $stmt = $pdo->prepare("SELECT est_active FROM maintenance WHERE page = ?");
    $stmt->execute([$page]);
    $current = $stmt->fetch();
    if ($current) {
        $new_status = $current['est_active'] == 1 ? 0 : 1;
        $pdo->prepare("UPDATE maintenance SET est_active = ? WHERE page = ?")->execute([$new_status, $page]);
        $_SESSION['message_maintenance'] = 'Statut de la page modifié.';
    }
    header('Location: maintenance.php');
    exit;
}

// ============================================
// AJOUTER UNE NOUVELLE PAGE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_page'])) {
    $page = trim($_POST['page']);
    $titre = trim($_POST['titre_page']);
    $ordre = (int)$_POST['ordre'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenance WHERE page = ?");
    $stmt->execute([$page]);
    if ($stmt->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO maintenance (page, titre_page, est_active, ordre) VALUES (?, ?, 1, ?)")->execute([$page, $titre, $ordre]);
        $_SESSION['message_maintenance'] = 'Nouvelle page ajoutée avec succès.';
    } else {
        $_SESSION['error_maintenance'] = 'Cette page existe déjà.';
    }
    header('Location: maintenance.php');
    exit;
}

// ============================================
// SUPPRIMER UNE PAGE
// ============================================
if (isset($_GET['delete']) && isset($_GET['page'])) {
    $page = $_GET['page'];
    // Ne pas supprimer les pages essentielles
    if ($page != 'accueil' && $page != 'boutique') {
        $pdo->prepare("DELETE FROM maintenance WHERE page = ?")->execute([$page]);
        $_SESSION['message_maintenance'] = 'Page supprimée avec succès.';
    } else {
        $_SESSION['error_maintenance'] = 'Impossible de supprimer une page essentielle.';
    }
    header('Location: maintenance.php');
    exit;
}

// ============================================
// MODIFIER LE MESSAGE DE MAINTENANCE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_message'])) {
    $message = trim($_POST['message_maintenance']);
    $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $pdo->prepare("UPDATE maintenance_globale SET message_maintenance = ?, date_fin = ? WHERE id = (SELECT id FROM (SELECT id FROM maintenance_globale ORDER BY id DESC LIMIT 1) as tmp)")->execute([$message, $date_fin]);
    $_SESSION['message_maintenance'] = 'Message de maintenance mis à jour.';
    header('Location: maintenance.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================
$pages = $pdo->query("SELECT * FROM maintenance ORDER BY ordre")->fetchAll();
$config = $pdo->query("SELECT * FROM maintenance_globale ORDER BY id DESC LIMIT 1")->fetch();

// Statistiques
$total_pages = $pdo->query("SELECT COUNT(*) FROM maintenance")->fetchColumn();
$pages_active = $pdo->query("SELECT COUNT(*) FROM maintenance WHERE est_active = 1")->fetchColumn();
$pages_inactive = $total_pages - $pages_active;

$message = $_SESSION['message_maintenance'] ?? '';
$error = $_SESSION['error_maintenance'] ?? '';
unset($_SESSION['message_maintenance']);
unset($_SESSION['error_maintenance']);

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
            <div class="topbar-title">🔧 Gestion de la <span>Maintenance</span></div>
            <div class="topbar-breadcrumb">Administration → Maintenance</div>
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
                <div class="stat-icon ic-gold"><i class="bi bi-files"></i></div>
                <div>
                    <div class="stat-val"><?= $total_pages ?></div>
                    <div class="stat-lbl">Total des pages</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $pages_active ?></div>
                    <div class="stat-lbl">Pages actives</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-x-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $pages_inactive ?></div>
                    <div class="stat-lbl">Pages désactivées</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-globe2"></i></div>
                <div>
                    <div class="stat-val" style="font-size:1.1rem;color:<?= $config['site_actif'] == 1 ? '#28A745' : '#E74C3C' ?>;">
                        <?= $config['site_actif'] == 1 ? '✓ ON' : '✗ OFF' ?>
                    </div>
                    <div class="stat-lbl">Mode global</div>
                </div>
            </div>
        </div>

        <!-- ===== MAINTENANCE GLOBALE ===== -->
        <div style="background:#fff;border-radius:12px;padding:18px 22px;border:1px solid #E8ECF0;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
            <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
                <i class="bi bi-globe2" style="font-size:1.5rem;color:#C8922A;"></i>
                <div>
                    <div style="font-weight:600;font-size:1rem;">Maintenance globale</div>
                    <div style="font-size:0.8rem;color:#8A99AA;">
                        <?php if($config['site_actif'] == 1): ?>
                            <span style="color:#1A7A4A;"><i class="bi bi-check-circle-fill"></i> Site accessible</span>
                        <?php else: ?>
                            <span style="color:#C0392B;"><i class="bi bi-exclamation-triangle-fill"></i> Site en maintenance</span>
                        <?php endif; ?>
                    </div>
                </div>
                <span style="padding:4px 14px;border-radius:20px;font-size:0.7rem;font-weight:700;background:<?= $config['site_actif'] == 1 ? '#D4EDDA' : '#F8D7DA' ?>;color:<?= $config['site_actif'] == 1 ? '#1A7A4A' : '#C0392B' ?>;">
                    <?= $config['site_actif'] == 1 ? '✅ ACTIF' : '⛔ MAINTENANCE' ?>
                </span>
                <span style="font-size:0.6rem;color:#8A99AA;background:#F0F2F5;padding:3px 14px;border-radius:20px;font-weight:600;">
                    <i class="bi bi-shield-lock-fill" style="color:#C8922A;"></i> Admin = accès illimité
                </span>
            </div>
            <div>
                <a href="?toggle_global=1" class="btn-admin <?= $config['site_actif'] == 1 ? 'btn-danger' : 'btn-success' ?>" onclick="return confirm('<?= $config['site_actif'] == 1 ? 'Désactiver tout le site ?' : 'Réactiver tout le site ?' ?>')" style="padding:10px 24px;">
                    <i class="bi <?= $config['site_actif'] == 1 ? 'bi-power' : 'bi-play-circle' ?>"></i>
                    <?= $config['site_actif'] == 1 ? 'Désactiver le site' : 'Réactiver le site' ?>
                </a>
            </div>
        </div>

        <!-- ===== MESSAGE DE MAINTENANCE ===== -->
        <div style="background:#FEFBF5;border-radius:12px;padding:18px 22px;border:1px solid rgba(200,146,42,0.15);margin-bottom:24px;">
            <form method="POST">
                <div style="display:grid;grid-template-columns:2fr 1fr auto;gap:15px;align-items:end;">
                    <div>
                        <label style="font-size:0.7rem;font-weight:600;color:#8A99AA;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">
                            <i class="bi bi-chat-text"></i> Message de maintenance
                        </label>
                        <input type="text" name="message_maintenance" value="<?= htmlspecialchars($config['message_maintenance'] ?? 'Site en maintenance. Nous revenons bientôt !') ?>" placeholder="Message affiché aux visiteurs..." style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E0E0E0'">
                    </div>
                    <div>
                        <label style="font-size:0.7rem;font-weight:600;color:#8A99AA;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">
                            <i class="bi bi-calendar3"></i> Date de fin
                        </label>
                        <input type="datetime-local" name="date_fin" value="<?= $config['date_fin'] ? date('Y-m-d\TH:i', strtotime($config['date_fin'])) : '' ?>" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.85rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E0E0E0'">
                    </div>
                    <div>
                        <button type="submit" name="update_message" class="btn-admin btn-primary" style="padding:10px 24px;">
                            <i class="bi bi-save"></i> Mettre à jour
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ===== AJOUTER UNE PAGE ===== -->
        <div style="background:#fff;border-radius:12px;padding:16px 20px;border:1.5px dashed rgba(200,146,42,0.4);margin-bottom:24px;">
            <form method="POST" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;width:100%;">
                <div style="flex:1;min-width:140px;">
                    <label style="font-size:0.65rem;font-weight:600;color:#8A99AA;display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:0.5px;">
                        <i class="bi bi-link"></i> Slug (nom)
                    </label>
                    <input type="text" name="page" placeholder="ex: a-propos" required style="width:100%;padding:8px 12px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E0E0E0'">
                </div>
                <div style="flex:1;min-width:140px;">
                    <label style="font-size:0.65rem;font-weight:600;color:#8A99AA;display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:0.5px;">
                        <i class="bi bi-type"></i> Titre affiché
                    </label>
                    <input type="text" name="titre_page" placeholder="ex: À propos" required style="width:100%;padding:8px 12px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E0E0E0'">
                </div>
                <div style="flex:0.5;min-width:70px;">
                    <label style="font-size:0.65rem;font-weight:600;color:#8A99AA;display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:0.5px;">
                        <i class="bi bi-sort-numeric-down"></i> Ordre
                    </label>
                    <input type="number" name="ordre" value="<?= $total_pages + 1 ?>" min="1" style="width:100%;padding:8px 12px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:'Jost',sans-serif;font-size:0.82rem;transition:border-color 0.3s;" onfocus="this.style.borderColor='#C8922A'" onblur="this.style.borderColor='#E0E0E0'">
                </div>
                <button type="submit" name="add_page" class="btn-admin btn-primary" style="padding:9px 20px;white-space:nowrap;">
                    <i class="bi bi-plus-circle"></i> Ajouter
                </button>
            </form>
        </div>

        <!-- ===== LISTE DES PAGES ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Pages du site</div>
                <div style="font-size:0.7rem;color:#8A99AA;background:#F8F9FA;padding:4px 16px;border-radius:20px;border:1px solid #E8ECF0;">
                    <strong style="color:#C8922A;"><?= $total_pages ?></strong> pages
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-maintenance" style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;width:50px;">#</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Page</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Titre</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Statut</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;width:180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($pages)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state" style="text-align:center;padding:40px;color:#8A99AA;">
                                            <i class="bi bi-files" style="font-size:2.5rem;display:block;margin-bottom:10px;color:#D5D5D5;"></i>
                                            <p style="margin:0;font-size:0.85rem;">Aucune page configurée</p>
                                            <span style="font-size:0.75rem;color:#bbb;display:block;margin-top:4px;">Ajoutez votre première page ci-dessus</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($pages as $p): ?>
                                <tr style="transition:background 0.2s;">
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;color:#8A99AA;font-size:0.75rem;">
                                        <?= $p['ordre'] ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;">
                                        <span style="font-weight:600;color:#1A2C3E;font-size:0.85rem;background:rgba(200,146,42,0.06);padding:2px 12px;border-radius:4px;">
                                            <?= htmlspecialchars($p['page']) ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;font-size:0.82rem;color:#5A6B7A;">
                                        <?= htmlspecialchars($p['titre_page'] ?? '-') ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <?php if($p['est_active'] == 1): ?>
                                            <span class="badge-status active" style="padding:3px 12px;border-radius:20px;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;display:inline-block;background:#E8F5E9;color:#2E7D32;">
                                                <i class="bi bi-check-circle"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-status inactive" style="padding:3px 12px;border-radius:20px;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;display:inline-block;background:#FBE9E7;color:#C62828;">
                                                <i class="bi bi-x-circle"></i> Désactivée
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                                            <a href="?toggle=1&page=<?= $p['page'] ?>" class="btn-small <?= $p['est_active'] == 1 ? 'gray' : 'green' ?>" onclick="return confirm('<?= $p['est_active'] == 1 ? 'Désactiver cette page ?' : 'Activer cette page ?' ?>')" style="padding:4px 14px;border-radius:6px;font-size:0.65rem;text-decoration:none;display:inline-flex;align-items:center;gap:4px;background:<?= $p['est_active'] == 1 ? '#F0F2F5' : 'rgba(40,167,69,0.1)' ?>;color:<?= $p['est_active'] == 1 ? '#5A6B7A' : '#28A745' ?>;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='<?= $p['est_active'] == 1 ? '#E0E6ED' : '#28A745' ?>';this.style.color='<?= $p['est_active'] == 1 ? '#333' : '#fff' ?>'" onmouseout="this.style.background='<?= $p['est_active'] == 1 ? '#F0F2F5' : 'rgba(40,167,69,0.1)' ?>';this.style.color='<?= $p['est_active'] == 1 ? '#5A6B7A' : '#28A745' ?>'">
                                                <i class="bi <?= $p['est_active'] == 1 ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                                <?= $p['est_active'] == 1 ? 'Désactiver' : 'Activer' ?>
                                            </a>
                                            <?php if($p['page'] != 'accueil' && $p['page'] != 'boutique'): ?>
                                                <a href="?delete=1&page=<?= $p['page'] ?>" class="btn-small red" onclick="return confirm('Supprimer cette page ?')" style="padding:4px 10px;border-radius:6px;font-size:0.65rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:rgba(231,76,60,0.1);color:#E74C3C;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='#E74C3C';this.style.color='#fff'" onmouseout="this.style.background='rgba(231,76,60,0.1)';this.style.color='#E74C3C'">
                                                    <i class="bi bi-trash3"></i>
                                                </a>
                                            <?php else: ?>
                                                <span style="font-size:0.6rem;color:#8A99AA;background:#F0F2F5;padding:3px 10px;border-radius:4px;">
                                                    <i class="bi bi-lock"></i> Essentiel
                                                </span>
                                            <?php endif; ?>
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

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>