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

$total_pages = $pdo->query("SELECT COUNT(*) FROM maintenance")->fetchColumn();

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

        <!-- ============================================
             STATUT GLOBAL (CARTE PRINCIPALE)
             ============================================ -->
        <div class="maintenance-global-card <?= $config['site_actif'] == 1 ? 'is-active' : 'is-maintenance' ?>">
            <div class="mgc-glow"></div>
            
            <div class="mgc-left">
                <div class="mgc-icon">
                    <i class="bi <?= $config['site_actif'] == 1 ? 'bi-globe2' : 'bi-tools' ?>"></i>
                </div>
                <div class="mgc-info">
                    <div class="mgc-title">Maintenance globale</div>
                    <div class="mgc-status">
                        <?php if($config['site_actif'] == 1): ?>
                            <span class="mgc-status-dot active"></span>
                            <span class="mgc-status-text active">Site accessible à tous</span>
                        <?php else: ?>
                            <span class="mgc-status-dot inactive"></span>
                            <span class="mgc-status-text inactive">Site en maintenance</span>
                        <?php endif; ?>
                    </div>
                    <div class="mgc-badge">
                        <i class="bi bi-shield-lock-fill"></i>
                        Admin = accès illimité
                    </div>
                </div>
            </div>
            
            <div class="mgc-right">
                <div class="mgc-state-badge <?= $config['site_actif'] == 1 ? 'active' : 'maintenance' ?>">
                    <?php if($config['site_actif'] == 1): ?>
                        <i class="bi bi-check-circle-fill"></i> ACTIF
                    <?php else: ?>
                        <i class="bi bi-exclamation-triangle-fill"></i> MAINTENANCE
                    <?php endif; ?>
                </div>
                <a href="?toggle_global=1" 
                   class="mgc-btn <?= $config['site_actif'] == 1 ? 'danger' : 'success' ?>" 
                   onclick="return confirm('<?= $config['site_actif'] == 1 ? 'Désactiver tout le site ? Les visiteurs verront la page de maintenance.' : 'Réactiver tout le site ?' ?>')">
                    <i class="bi <?= $config['site_actif'] == 1 ? 'bi-power' : 'bi-play-circle-fill' ?>"></i>
                    <?= $config['site_actif'] == 1 ? 'Désactiver le site' : 'Réactiver le site' ?>
                </a>
            </div>
        </div>

        <!-- ============================================
             MESSAGE DE MAINTENANCE
             ============================================ -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-chat-text"></i> Message de maintenance
                </div>
            </div>
            <div class="card-body">
                <form method="POST" class="msg-form">
                    <div class="msg-form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-chat-text"></i> Message affiché aux visiteurs
                            </label>
                            <input type="text" 
                                   name="message_maintenance" 
                                   value="<?= htmlspecialchars($config['message_maintenance'] ?? 'Site en maintenance. Nous revenons bientôt !') ?>" 
                                   placeholder="Message affiché aux visiteurs..." 
                                   class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-calendar3"></i> Date de fin (optionnel)
                            </label>
                            <input type="datetime-local" 
                                   name="date_fin" 
                                   value="<?= $config['date_fin'] ? date('Y-m-d\TH:i', strtotime($config['date_fin'])) : '' ?>" 
                                   class="form-input">
                        </div>
                        <div class="form-group form-group-btn">
                            <button type="submit" name="update_message" class="btn-admin btn-primary">
                                <i class="bi bi-save"></i> Mettre à jour
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================
             AJOUTER UNE PAGE
             ============================================ -->
        <div class="card-white card-dashed">
            <div class="card-body">
                <form method="POST" class="add-form">
                    <div class="add-form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-link-45deg"></i> Slug (nom URL)
                            </label>
                            <input type="text" name="page" placeholder="ex: a-propos" required class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-type"></i> Titre affiché
                            </label>
                            <input type="text" name="titre_page" placeholder="ex: À propos" required class="form-input">
                        </div>
                        <div class="form-group form-group-ord">
                            <label class="form-label">
                                <i class="bi bi-sort-numeric-down"></i> Ordre
                            </label>
                            <input type="number" name="ordre" value="<?= $total_pages + 1 ?>" min="1" class="form-input">
                        </div>
                        <div class="form-group form-group-btn">
                            <button type="submit" name="add_page" class="btn-admin btn-primary">
                                <i class="bi bi-plus-circle"></i> Ajouter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================
             LISTE DES PAGES
             ============================================ -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-list"></i> Pages du site
                </div>
                <div class="card-count">
                    <strong><?= $total_pages ?></strong> page<?= $total_pages > 1 ? 's' : '' ?>
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-maintenance">
                        <thead>
                            <tr>
                                <th class="col-ord">#</th>
                                <th class="col-page">Page</th>
                                <th class="col-titre">Titre</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($pages)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <i class="bi bi-files"></i>
                                            <p>Aucune page configurée</p>
                                            <span>Ajoutez votre première page ci-dessus</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($pages as $p): ?>
                                <tr>
                                    <td class="col-ord">
                                        <span class="ord-badge"><?= $p['ordre'] ?></span>
                                    </td>
                                    <td class="col-page">
                                        <span class="page-slug">
                                            <?= htmlspecialchars($p['page']) ?>
                                        </span>
                                    </td>
                                    <td class="col-titre">
                                        <?= htmlspecialchars($p['titre_page'] ?? '-') ?>
                                    </td>
                                    <td class="col-actions">
                                        <div class="actions-wrap">
                                            <?php if($p['page'] != 'accueil' && $p['page'] != 'boutique'): ?>
                                                <a href="?delete=1&page=<?= $p['page'] ?>" 
                                                   class="btn-small red" 
                                                   onclick="return confirm('Supprimer cette page ?')">
                                                    <i class="bi bi-trash3"></i> Supprimer
                                                </a>
                                            <?php else: ?>
                                                <span class="badge-essential">
                                                    <i class="bi bi-lock-fill"></i> Essentiel
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
     STYLES SPÉCIFIQUES À LA PAGE MAINTENANCE
     ============================================ -->
<style>
/* ============================================
   CARTE STATUT GLOBAL
   ============================================ */
.maintenance-global-card {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 22px 26px;
    margin-bottom: 24px;
    border-radius: 16px;
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1510 100%);
    border: 1.5px solid rgba(200,146,42,0.35);
    box-shadow: 
        0 0 25px rgba(200,146,42,0.15),
        inset 0 0 25px rgba(200,146,42,0.03);
    overflow: hidden;
    flex-wrap: wrap;
}

.maintenance-global-card.is-maintenance {
    border-color: rgba(231,76,60,0.5);
    box-shadow: 
        0 0 25px rgba(231,76,60,0.2),
        inset 0 0 25px rgba(231,76,60,0.05);
}

.maintenance-global-card .mgc-glow {
    position: absolute;
    top: -50%;
    right: -10%;
    width: 60%;
    height: 200%;
    background: radial-gradient(circle, rgba(200,146,42,0.15) 0%, transparent 60%);
    pointer-events: none;
    animation: mgcShine 8s ease-in-out infinite;
}

.maintenance-global-card.is-maintenance .mgc-glow {
    background: radial-gradient(circle, rgba(231,76,60,0.2) 0%, transparent 60%);
}

@keyframes mgcShine {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(-20px, 20px); }
}

.mgc-left {
    display: flex;
    align-items: center;
    gap: 18px;
    position: relative;
    z-index: 2;
    flex: 1;
    min-width: 0;
}

.mgc-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(200,146,42,0.2), rgba(232,181,90,0.1));
    border: 1.5px solid rgba(200,146,42,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: var(--gold-light);
    box-shadow: 0 0 20px rgba(200,146,42,0.3);
    flex-shrink: 0;
}

.maintenance-global-card.is-maintenance .mgc-icon {
    background: linear-gradient(135deg, rgba(231,76,60,0.2), rgba(192,57,43,0.1));
    border-color: rgba(231,76,60,0.5);
    color: #E74C3C;
    box-shadow: 0 0 20px rgba(231,76,60,0.3);
}

.mgc-info {
    min-width: 0;
}

.mgc-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.15rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 6px;
}

.mgc-status {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.mgc-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.mgc-status-dot.active {
    background: #27AE60;
    box-shadow: 0 0 12px #27AE60;
    animation: pulseGreen 2s ease-in-out infinite;
}

.mgc-status-dot.inactive {
    background: #E74C3C;
    box-shadow: 0 0 12px #E74C3C;
    animation: pulseRed 2s ease-in-out infinite;
}

@keyframes pulseGreen {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.3); }
}

@keyframes pulseRed {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.3); }
}

.mgc-status-text {
    font-size: 0.85rem;
    font-weight: 500;
}

.mgc-status-text.active { color: #6FCF97; }
.mgc-status-text.inactive { color: #FF9B8A; }

.mgc-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.7rem;
    color: rgba(255,255,255,0.5);
    background: rgba(255,255,255,0.05);
    padding: 4px 12px;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.08);
}

.mgc-badge i {
    color: var(--gold);
}

.mgc-right {
    display: flex;
    align-items: center;
    gap: 14px;
    position: relative;
    z-index: 2;
    flex-wrap: wrap;
}

.mgc-state-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.mgc-state-badge.active {
    background: rgba(39,174,96,0.15);
    border: 1.5px solid rgba(39,174,96,0.4);
    color: #6FCF97;
    box-shadow: 0 0 15px rgba(39,174,96,0.2);
}

.mgc-state-badge.maintenance {
    background: rgba(231,76,60,0.15);
    border: 1.5px solid rgba(231,76,60,0.4);
    color: #FF9B8A;
    box-shadow: 0 0 15px rgba(231,76,60,0.2);
    animation: maintenancePulse 2s ease-in-out infinite;
}

@keyframes maintenancePulse {
    0%, 100% { box-shadow: 0 0 15px rgba(231,76,60,0.2); }
    50% { box-shadow: 0 0 25px rgba(231,76,60,0.5); }
}

.mgc-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 10px;
    font-family: 'Jost', sans-serif;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.mgc-btn.success {
    background: linear-gradient(135deg, #27AE60, #1A7A4A);
    color: #fff;
    box-shadow: 0 4px 15px rgba(39,174,96,0.3);
}

.mgc-btn.success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(39,174,96,0.5);
}

.mgc-btn.danger {
    background: linear-gradient(135deg, #E74C3C, #C0392B);
    color: #fff;
    box-shadow: 0 4px 15px rgba(231,76,60,0.3);
}

.mgc-btn.danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(231,76,60,0.5);
}

/* ============================================
   FORMULAIRES
   ============================================ */
.msg-form-grid {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 16px;
    align-items: end;
}

.add-form-grid {
    display: grid;
    grid-template-columns: 1.2fr 1.2fr 0.6fr auto;
    gap: 14px;
    align-items: end;
}

.form-group {
    min-width: 0;
}

.form-label {
    display: block;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 6px;
}

.form-label i {
    color: var(--gold);
    margin-right: 4px;
    font-size: 0.75rem;
}

.form-input {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid var(--border-color);
    border-radius: 10px;
    font-family: 'Jost', sans-serif;
    font-size: 0.85rem;
    background: var(--input-bg);
    color: var(--text-primary);
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(200,146,42,0.1);
}

.form-group-btn {
    flex-shrink: 0;
}

.form-group-btn .btn-admin {
    width: 100%;
    justify-content: center;
    padding: 11px 22px;
    white-space: nowrap;
}

/* ============================================
   CARTE DASHED (Ajouter une page)
   ============================================ */
.card-dashed {
    border: 1.5px dashed rgba(200,146,42,0.4) !important;
    background: rgba(200,146,42,0.02) !important;
}

.card-dashed .card-body {
    padding: 18px 22px;
}

/* ============================================
   TABLEAU MAINTENANCE
   ============================================ */
.table-maintenance {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.table-maintenance thead th {
    padding: 14px 18px;
    background: linear-gradient(135deg, #0D0D0D, #1A1510);
    color: rgba(255,255,255,0.8);
    font-weight: 600;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-align: left;
    white-space: nowrap;
}

.table-maintenance thead th.col-ord,
.table-maintenance thead th.col-actions {
    text-align: center;
}

.table-maintenance tbody td {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-soft);
    vertical-align: middle;
    color: var(--text-primary);
}

.table-maintenance tbody tr {
    transition: background 0.2s ease;
}

.table-maintenance tbody tr:hover {
    background: rgba(200,146,42,0.03);
}

.table-maintenance tbody tr:last-child td {
    border-bottom: none;
}

.col-ord {
    width: 60px;
    text-align: center;
}

.col-actions {
    width: 180px;
    text-align: center;
}

.ord-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: rgba(200,146,42,0.1);
    color: var(--gold);
    border-radius: 50%;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1.5px solid rgba(200,146,42,0.25);
}

.page-slug {
    display: inline-block;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.85rem;
    background: rgba(200,146,42,0.08);
    padding: 4px 14px;
    border-radius: 6px;
    border: 1px solid rgba(200,146,42,0.15);
}

.col-titre {
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.actions-wrap {
    display: flex;
    gap: 6px;
    justify-content: center;
    flex-wrap: wrap;
}

.badge-essential {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.65rem;
    color: var(--text-secondary);
    background: var(--border-soft);
    padding: 4px 12px;
    border-radius: 6px;
    font-weight: 600;
    border: 1px solid var(--border-color);
}

.badge-essential i {
    font-size: 0.7rem;
}

/* ============================================
   RESPONSIVE — TABLETTE
   ============================================ */
@media (max-width: 900px) {
    .msg-form-grid {
        grid-template-columns: 1fr 1fr;
    }
    .msg-form-grid .form-group-btn {
        grid-column: span 2;
    }
    .msg-form-grid .form-group-btn .btn-admin {
        width: 100%;
    }
    
    .add-form-grid {
        grid-template-columns: 1fr 1fr;
    }
    .add-form-grid .form-group-ord {
        grid-column: span 1;
    }
    .add-form-grid .form-group-btn {
        grid-column: span 2;
    }
    .add-form-grid .form-group-btn .btn-admin {
        width: 100%;
    }
}

/* ============================================
   RESPONSIVE — MOBILE
   ============================================ */
@media (max-width: 768px) {
    .maintenance-global-card {
        flex-direction: column;
        align-items: flex-start;
        padding: 20px;
    }
    
    .mgc-left {
        width: 100%;
    }
    
    .mgc-right {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }
    
    .mgc-state-badge {
        justify-content: center;
    }
    
    .mgc-btn {
        justify-content: center;
        width: 100%;
    }
    
    .msg-form-grid,
    .add-form-grid {
        grid-template-columns: 1fr;
    }
    
    .msg-form-grid .form-group-btn,
    .add-form-grid .form-group-btn {
        grid-column: span 1;
    }
    
    /* Tableaux : scroll horizontal */
    .table-maintenance {
        min-width: 500px;
    }
    
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin: 0 -20px;
        padding: 0 20px;
        width: calc(100% + 40px);
    }
    
    .col-actions {
        width: auto;
    }
    
    .actions-wrap {
        flex-direction: column;
        gap: 4px;
    }
    
    .actions-wrap .btn-small,
    .actions-wrap .badge-essential {
        width: 100%;
        justify-content: center;
    }
}

/* ============================================
   PETIT MOBILE
   ============================================ */
@media (max-width: 480px) {
    .mgc-icon {
        width: 50px;
        height: 50px;
        font-size: 1.3rem;
    }
    
    .mgc-title {
        font-size: 1rem;
    }
    
    .mgc-status-text {
        font-size: 0.78rem;
    }
    
    .mgc-badge {
        font-size: 0.65rem;
        padding: 3px 10px;
    }
    
    .card-dashed .card-body {
        padding: 14px 16px;
    }
    
    .form-input {
        padding: 10px 12px;
        font-size: 0.82rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>