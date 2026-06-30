<?php
// ============================================
// SESSION ADMIN SÉPARÉE
// ============================================
require_once 'session_config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// ============================================
// TOGGLE MAINTENANCE GLOBALE
// ============================================
if (isset($_GET['toggle_global'])) {
    $stmt = $pdo->query("SELECT site_actif FROM maintenance_globale ORDER BY id DESC LIMIT 1");
    $current = $stmt->fetch();
    $new_status = $current['site_actif'] == 1 ? 0 : 1;
    $pdo->prepare("UPDATE maintenance_globale SET site_actif = ? WHERE id = (SELECT id FROM (SELECT id FROM maintenance_globale ORDER BY id DESC LIMIT 1) as tmp)")->execute([$new_status]);
    header('Location: maintenance.php?msg=global');
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
    }
    header('Location: maintenance.php?msg=toggle');
    exit;
}

// ============================================
// AJOUTER UNE NOUVELLE PAGE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_page'])) {
    $page = trim($_POST['page']);
    $titre = trim($_POST['titre_page']);
    $ordre = (int)$_POST['ordre'];
    
    // Vérifier si la page existe déjà
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenance WHERE page = ?");
    $stmt->execute([$page]);
    if ($stmt->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO maintenance (page, titre_page, est_active, ordre) VALUES (?, ?, 1, ?)")->execute([$page, $titre, $ordre]);
        header('Location: maintenance.php?msg=add');
        exit;
    }
}

// ============================================
// SUPPRIMER UNE PAGE
// ============================================
if (isset($_GET['delete']) && isset($_GET['page'])) {
    $page = $_GET['page'];
    $pdo->prepare("DELETE FROM maintenance WHERE page = ?")->execute([$page]);
    header('Location: maintenance.php?msg=delete');
    exit;
}

// ============================================
// MODIFIER LE MESSAGE DE MAINTENANCE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_message'])) {
    $message = trim($_POST['message_maintenance']);
    $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $pdo->prepare("UPDATE maintenance_globale SET message_maintenance = ?, date_fin = ? WHERE id = (SELECT id FROM (SELECT id FROM maintenance_globale ORDER BY id DESC LIMIT 1) as tmp)")->execute([$message, $date_fin]);
    header('Location: maintenance.php?msg=message');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================

// Pages
$pages = $pdo->query("SELECT * FROM maintenance ORDER BY ordre")->fetchAll();

// Configuration globale
$config = $pdo->query("SELECT * FROM maintenance_globale ORDER BY id DESC LIMIT 1")->fetch();

// Statistiques
$total_pages = $pdo->query("SELECT COUNT(*) FROM maintenance")->fetchColumn();
$pages_active = $pdo->query("SELECT COUNT(*) FROM maintenance WHERE est_active = 1")->fetchColumn();
$pages_inactive = $total_pages - $pages_active;

$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Jost', sans-serif; background: #F5F7FA; color: #1A2C3E; display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px;
            background: #0D0D0D;
            border-right: 1px solid rgba(200,146,42,0.18);
            position: fixed;
            top: 0; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(200,146,42,0.12);
        }
        .brand-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #C8922A;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .brand-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.2);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .admin-user {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 16px;
            padding: 10px 12px;
            background: rgba(200,146,42,0.07);
            border-radius: 8px;
            border: 1px solid rgba(200,146,42,0.12);
        }
        .admin-avatar {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, #C8922A, #E2B96A);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; color: #fff; font-weight: 600;
            flex-shrink: 0;
        }
        .admin-name { font-size: 0.82rem; color: #fff; font-weight: 500; }
        .admin-role { font-size: 0.62rem; color: rgba(255,255,255,0.3); letter-spacing: 1px; text-transform: uppercase; }
        .nav-section {
            font-size: 0.58rem;
            color: rgba(255,255,255,0.18);
            letter-spacing: 2.5px;
            text-transform: uppercase;
            padding: 18px 24px 6px;
        }
        .sidebar nav { flex: 1; padding: 8px 12px; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 14px;
            border-radius: 8px;
            color: rgba(255,255,255,0.48);
            text-decoration: none;
            font-size: 0.83rem;
            font-weight: 500;
            border-left: 2px solid transparent;
            transition: all 0.22s;
            margin-bottom: 2px;
        }
        .nav-item i { font-size: 1rem; width: 18px; text-align: center; }
        .nav-item:hover { color: #fff; background: rgba(200,146,42,0.08); border-left-color: rgba(200,146,42,0.4); }
        .nav-item.active { color: #fff; background: rgba(200,146,42,0.12); border-left-color: #C8922A; }
        .nav-item.active i { color: #C8922A; }
        .nav-item.logout { margin-top: auto; color: rgba(231,76,60,0.6); }
        .nav-item.logout:hover { color: #E74C3C; background: rgba(231,76,60,0.08); border-left-color: #E74C3C; }
        .nav-divider {
            height: 1px;
            background: rgba(255,255,255,0.06);
            margin: 8px 14px;
        }

        .main {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #F5F7FA;
            min-height: 100vh;
        }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #E8ECF0;
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #0D0D0D;
        }
        .topbar-title span { color: #C8922A; }
        .topbar-breadcrumb { font-size: 0.75rem; color: #999; margin-top: 2px; }
        .btn-admin {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Jost', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 9px 20px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.22s;
            cursor: pointer;
            border: none;
            white-space: nowrap;
        }
        .btn-or { background: #C8922A; color: #fff; }
        .btn-or:hover { background: #9A6E1A; color: #fff; transform: translateY(-1px); }
        .btn-outline { border: 1.5px solid #E0E0E0; color: #555; background: #fff; }
        .btn-outline:hover { border-color: #C8922A; color: #C8922A; }
        .btn-danger { background: #E74C3C; color: #fff; }
        .btn-danger:hover { background: #C0392B; color: #fff; }
        .btn-success { background: #27AE60; color: #fff; }
        .btn-success:hover { background: #1A7A4A; color: #fff; }
        .btn-sm { padding: 5px 14px; font-size: 0.65rem; }

        .content { padding: 28px 32px; flex: 1; }

        .alert-ok {
            display: flex; align-items: center; gap: 10px;
            background: #D4EDDA; border-left: 4px solid #27AE60;
            color: #0A3622; padding: 13px 18px; border-radius: 8px;
            font-size: 0.88rem; margin-bottom: 24px;
            animation: slideDown 0.4s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-box {
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid #E8ECF0;
            transition: all 0.22s;
        }
        .stat-box:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .ic-or { background: rgba(200,146,42,0.1); color: #C8922A; }
        .ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
        .ic-red { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .ic-blue { background: rgba(52,152,219,0.1); color: #2980B9; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem; font-weight: 700;
            color: #0D0D0D; line-height: 1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 3px; }

        .global-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px 25px;
            border: 1px solid #E8ECF0;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .global-card .status {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .global-card .status .badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-on { background: #D4EDDA; color: #1A7A4A; }
        .badge-off { background: #F8D7DA; color: #C0392B; }
        .info-admin {
            font-size: 0.7rem;
            color: #8A99AA;
            background: #F0F2F5;
            padding: 4px 12px;
            border-radius: 20px;
        }
        .info-admin i { color: #C8922A; }

        .form-message {
            background: #FEFBF5;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(200,146,42,0.15);
            margin-bottom: 28px;
        }
        .form-message .row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        .form-message label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #8A99AA;
            display: block;
            margin-bottom: 4px;
        }
        .form-message input, .form-message textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #E0E0E0;
            border-radius: 8px;
            font-family: 'Jost', sans-serif;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }
        .form-message input:focus, .form-message textarea:focus {
            outline: none; border-color: #C8922A;
            box-shadow: 0 0 0 3px rgba(200,146,42,0.1);
        }

        .table-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #E8ECF0;
        }
        .table-card-header {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            border-bottom: 1px solid #F0F2F5;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1rem; font-weight: 600; color: #0D0D0D;
        }
        .table-count { font-size: 0.75rem; color: #8A99AA; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0D0D0D; color: #C8922A;
            font-size: 0.68rem; font-weight: 600;
            letter-spacing: 1px; text-transform: uppercase;
            padding: 14px 20px; text-align: left;
        }
        tbody td {
            padding: 14px 20px;
            font-size: 0.84rem;
            color: #333;
            border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        tbody tr:hover td { background: #FEFBF5; }

        .badge-status {
            display: inline-block; padding: 4px 14px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600;
        }
        .badge-active { background: #D4EDDA; color: #1A7A4A; }
        .badge-inactive { background: #F8D7DA; color: #C0392B; }

        .btn-toggle {
            padding: 6px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-toggle.off { background: #F8D7DA; color: #C0392B; }
        .btn-toggle.off:hover { background: #C0392B; color: #fff; }
        .btn-toggle.on { background: #D4EDDA; color: #1A7A4A; }
        .btn-toggle.on:hover { background: #1A7A4A; color: #fff; }
        .btn-delete { background: #F8D7DA; color: #C0392B; padding: 4px 12px; border-radius: 6px; text-decoration: none; font-size: 0.7rem; margin-left: 6px; }
        .btn-delete:hover { background: #C0392B; color: #fff; }

        /* Add page form */
        .add-page-form {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px dashed #C8922A;
            margin-bottom: 20px;
            display: flex;
            align-items: end;
            gap: 15px;
            flex-wrap: wrap;
        }
        .add-page-form .field {
            flex: 1;
            min-width: 150px;
        }
        .add-page-form label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #8A99AA;
            display: block;
            margin-bottom: 4px;
        }
        .add-page-form input {
            width: 100%;
            padding: 8px 12px;
            border: 1.5px solid #E0E0E0;
            border-radius: 8px;
            font-family: 'Jost', sans-serif;
            font-size: 0.85rem;
        }
        .add-page-form input:focus {
            outline: none; border-color: #C8922A;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .form-message .row { grid-template-columns: 1fr; }
            .add-page-form { flex-direction: column; align-items: stretch; }
            .add-page-form .field { min-width: auto; }
            table { display: block; overflow-x: auto; }
        }
        @media (max-width: 500px) {
            .stats-row { grid-template-columns: 1fr; }
            .global-card { flex-direction: column; align-items: stretch; text-align: center; }
            .global-card .status { flex-wrap: wrap; justify-content: center; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">AWA KA SUGU</div>
        <div class="brand-sub">Administration</div>
        <div class="admin-user">
            <div class="admin-avatar">A</div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_nom'] ?? 'Awa Doumbia') ?></div>
                <div class="admin-role">Administratrice</div>
            </div>
        </div>
    </div>
    <nav>
        <div class="nav-section">Principal</div>
        <a href="dashboard.php" class="nav-item"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
        <a href="point_de_vente.php" class="nav-item"><i class="bi bi-cash-stack"></i> Point de vente</a>
        <a href="produits.php" class="nav-item"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="commandes.php" class="nav-item"><i class="bi bi-receipt"></i> Commandes</a>
        <a href="clients.php" class="nav-item"><i class="bi bi-people"></i> Clients</a>
        <div class="nav-section">Gestion</div>
        <a href="maintenance.php" class="nav-item active"><i class="bi bi-tools"></i> Maintenance</a>
        <a href="videos.php" class="nav-item"><i class="bi bi-camera-reels"></i> Vidéos</a>
        <div class="nav-divider"></div>
        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <!-- Lien de déconnexion en bas -->
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">🔧 Gestion de la <span>Maintenance</span></div>
            <div class="topbar-breadcrumb">Administration → Maintenance</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" target="_blank" class="btn-admin btn-outline">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <div class="content">
        <?php if($msg == 'toggle'): ?>
            <div class="alert-ok"><i class="bi bi-check-circle-fill"></i> Statut de la page modifié avec succès.</div>
        <?php elseif($msg == 'global'): ?>
            <div class="alert-ok"><i class="bi bi-check-circle-fill"></i> Statut global du site modifié.</div>
        <?php elseif($msg == 'message'): ?>
            <div class="alert-ok"><i class="bi bi-check-circle-fill"></i> Message de maintenance mis à jour.</div>
        <?php elseif($msg == 'add'): ?>
            <div class="alert-ok"><i class="bi bi-check-circle-fill"></i> Nouvelle page ajoutée avec succès.</div>
        <?php elseif($msg == 'delete'): ?>
            <div class="alert-ok"><i class="bi bi-check-circle-fill"></i> Page supprimée avec succès.</div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-files"></i></div>
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
                    <div class="stat-val"><?= $config['site_actif'] == 1 ? 'ON' : 'OFF' ?></div>
                    <div class="stat-lbl">Mode global</div>
                </div>
            </div>
        </div>

        <!-- Maintenance globale -->
        <div class="global-card">
            <div class="status">
                <i class="bi bi-globe2" style="font-size:1.5rem;color:#C8922A;"></i>
                <div>
                    <div style="font-weight:600;font-size:1rem;">Maintenance globale</div>
                    <div style="font-size:0.8rem;color:#8A99AA;">
                        <?php if($config['site_actif'] == 1): ?>
                            <span style="color:#1A7A4A;">✓ Site accessible</span>
                        <?php else: ?>
                            <span style="color:#C0392B;">✗ Site en maintenance</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <span class="badge <?= $config['site_actif'] == 1 ? 'badge-on' : 'badge-off' ?>">
                        <?= $config['site_actif'] == 1 ? 'ACTIF' : 'MAINTENANCE' ?>
                    </span>
                </div>
                <div class="info-admin">
                    <i class="bi bi-shield-lock-fill"></i> Admin = accès illimité
                </div>
            </div>
            <div>
                <a href="?toggle_global=1" class="btn-admin <?= $config['site_actif'] == 1 ? 'btn-danger' : 'btn-success' ?>" onclick="return confirm('<?= $config['site_actif'] == 1 ? 'Désactiver tout le site ?' : 'Réactiver tout le site ?' ?>')">
                    <i class="bi <?= $config['site_actif'] == 1 ? 'bi-power' : 'bi-play-circle' ?>"></i>
                    <?= $config['site_actif'] == 1 ? 'Désactiver le site' : 'Réactiver le site' ?>
                </a>
            </div>
        </div>

        <!-- Message de maintenance -->
        <div class="form-message">
            <form method="POST">
                <div class="row">
                    <div>
                        <label>Message de maintenance</label>
                        <input type="text" name="message_maintenance" value="<?= htmlspecialchars($config['message_maintenance'] ?? 'Site en maintenance. Nous revenons bientôt !') ?>" placeholder="Message affiché aux visiteurs...">
                    </div>
                    <div>
                        <label>Date de fin (optionnel)</label>
                        <input type="datetime-local" name="date_fin" value="<?= $config['date_fin'] ? date('Y-m-d\TH:i', strtotime($config['date_fin'])) : '' ?>">
                    </div>
                    <div>
                        <button type="submit" name="update_message" class="btn-admin btn-or">
                            <i class="bi bi-save"></i> Mettre à jour
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Ajouter une page -->
        <div class="add-page-form">
            <form method="POST" style="display:flex;align-items:end;gap:15px;flex-wrap:wrap;width:100%;">
                <div class="field">
                    <label>Nom de la page (slug)</label>
                    <input type="text" name="page" placeholder="ex: a-propos" required>
                </div>
                <div class="field">
                    <label>Titre affiché</label>
                    <input type="text" name="titre_page" placeholder="ex: À propos" required>
                </div>
                <div class="field" style="flex:0.5;min-width:80px;">
                    <label>Ordre</label>
                    <input type="number" name="ordre" value="<?= $total_pages + 1 ?>" min="1">
                </div>
                <button type="submit" name="add_page" class="btn-admin btn-or">
                    <i class="bi bi-plus-circle"></i> Ajouter
                </button>
            </form>
        </div>

        <!-- Liste des pages -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">📋 Pages du site</div>
                <div class="table-count"><?= $total_pages ?> pages</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Page</th>
                        <th>Titre</th>
                        <th>Statut</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pages as $p): ?>
                    <tr>
                        <td style="color:#8A99AA;font-size:0.78rem;text-align:center;"><?= $p['ordre'] ?></td>
                        <td><strong><?= htmlspecialchars($p['page']) ?></strong></td>
                        <td><?= htmlspecialchars($p['titre_page'] ?? '-') ?></td>
                        <td>
                            <?php if($p['est_active'] == 1): ?>
                                <span class="badge-status badge-active"><i class="bi bi-check-circle"></i> Active</span>
                            <?php else: ?>
                                <span class="badge-status badge-inactive"><i class="bi bi-x-circle"></i> Désactivée</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <a href="?toggle=1&page=<?= $p['page'] ?>" class="btn-toggle <?= $p['est_active'] == 1 ? 'on' : 'off' ?>" onclick="return confirm('<?= $p['est_active'] == 1 ? 'Désactiver cette page ?' : 'Activer cette page ?' ?>')">
                                <i class="bi <?= $p['est_active'] == 1 ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                <?= $p['est_active'] == 1 ? 'Désactiver' : 'Activer' ?>
                            </a>
                            <?php if($p['page'] != 'accueil' && $p['page'] != 'boutique'): ?>
                                <a href="?delete=1&page=<?= $p['page'] ?>" class="btn-delete" onclick="return confirm('Supprimer cette page ?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>