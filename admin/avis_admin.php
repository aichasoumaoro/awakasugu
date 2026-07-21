<?php
// ============================================
// GESTION DES AVIS CLIENTS - ADMIN
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
// PUBLIER / CACHER UN AVIS
// ============================================
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT est_visible FROM avis_clients WHERE id = ?");
    $stmt->execute([$id]);
    $avis = $stmt->fetch();
    if ($avis) {
        $new_status = $avis['est_visible'] ? 0 : 1;
        $pdo->prepare("UPDATE avis_clients SET est_visible = ? WHERE id = ?")->execute([$new_status, $id]);
        $_SESSION['message_avis'] = 'Avis mis à jour avec succès !';
    }
    header('Location: avis_admin.php');
    exit;
}

// ============================================
// SUPPRIMER UN AVIS
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM avis_clients WHERE id = ?")->execute([$id]);
    $_SESSION['message_avis'] = 'Avis supprimé avec succès !';
    header('Location: avis_admin.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================
$avis = $pdo->query("SELECT * FROM avis_clients ORDER BY created_at DESC")->fetchAll();

// Statistiques
$total_avis = $pdo->query("SELECT COUNT(*) FROM avis_clients")->fetchColumn();
$avis_visibles = $pdo->query("SELECT COUNT(*) FROM avis_clients WHERE est_visible = 1")->fetchColumn();
$avis_non_visibles = $pdo->query("SELECT COUNT(*) FROM avis_clients WHERE est_visible = 0")->fetchColumn();
$note_moyenne = $pdo->query("SELECT COALESCE(AVG(note), 0) FROM avis_clients WHERE est_visible = 1")->fetchColumn();

$message = $_SESSION['message_avis'] ?? '';
unset($_SESSION['message_avis']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Avis - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jost', sans-serif; background: #F5F7FA; color: #1A2C3E; display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px;
            background: #0D0D0D;
            border-right: 1px solid rgba(200,146,42,0.2);
            position: fixed;
            top: 0; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(200,146,42,0.15);
        }
        .brand-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: #C8922A;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .brand-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.3);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .admin-user {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 10px 12px;
            background: rgba(200,146,42,0.08);
            border-radius: 10px;
            border: 1px solid rgba(200,146,42,0.15);
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
        .admin-role { font-size: 0.62rem; color: rgba(255,255,255,0.35); letter-spacing: 1px; text-transform: uppercase; }
        .nav-section {
            font-size: 0.58rem;
            color: rgba(255,255,255,0.2);
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
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 0.83rem;
            font-weight: 500;
            border-left: 2px solid transparent;
            transition: all 0.22s;
            margin-bottom: 2px;
        }
        .nav-item i { font-size: 1rem; width: 18px; text-align: center; }
        .nav-item:hover { color: #fff; background: rgba(200,146,42,0.1); border-left-color: rgba(200,146,42,0.5); }
        .nav-item.active { color: #fff; background: rgba(200,146,42,0.15); border-left-color: #C8922A; }
        .nav-item.active i { color: #C8922A; }
        .nav-item.logout:hover { color: #E74C3C; background: rgba(231,76,60,0.1); border-left-color: #E74C3C; }

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
            padding: 8px 18px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid #E0E6ED;
            color: #5A6B7A;
            background: #fff;
        }
        .btn-admin:hover { border-color: #C8922A; color: #C8922A; }
        .btn-site {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #fff !important;
            border-color: #C8922A !important;
        }
        .btn-site:hover {
            background: linear-gradient(135deg, #9A6E1A, #C8922A) !important;
            color: #fff !important;
        }

        .content { padding: 28px 32px; flex: 1; }
        .alert-success {
            background: #D4EDDA;
            border-left: 4px solid #27AE60;
            color: #0A3622;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-box {
            background: #fff;
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid #E8ECF0;
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .ic-or { background: rgba(200,146,42,0.1); color: #C8922A; }
        .ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
        .ic-red { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .ic-blue { background: rgba(41,128,185,0.1); color: #2980B9; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem; font-weight: 700;
            color: #1A2C3E; line-height: 1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 2px; }

        .table-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #E8ECF0;
        }
        .table-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid #F0F2F5;
        }
        .table-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 600;
            color: #0D0D0D;
        }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0D0D0D;
            color: #C8922A;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 12px 18px;
            text-align: left;
        }
        tbody td {
            padding: 12px 18px;
            font-size: 0.85rem;
            color: #333;
            border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        tbody tr:hover td { background: #FEFBF5; }

        .badge-statut {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-visible { background: #D4EDDA; color: #1A7A4A; }
        .badge-cache { background: #F8D7DA; color: #721C24; }

        .etoiles {
            color: #F1C40F;
            font-size: 0.8rem;
        }

        .btn-act {
            width: 32px; height: 32px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
        }
        .btn-toggle { background: rgba(41,128,185,0.1); color: #2980B9; }
        .btn-toggle:hover { background: #2980B9; color: #fff; }
        .btn-delete { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .btn-delete:hover { background: #E74C3C; color: #fff; }

        .empty-state { text-align: center; padding: 40px; color: #999; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 10px; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
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

        <div class="nav-section">Restaurant</div>
        <a href="plats.php" class="nav-item"><i class="bi bi-cup-hot"></i> Plats</a>
        <a href="commandes_repas.php" class="nav-item"><i class="bi bi-bag-check"></i> Commandes repas</a>
        <a href="reservations.php" class="nav-item"><i class="bi bi-calendar-check"></i> Réservations</a>

        <div class="nav-section">Gestion</div>
        <a href="achats.php" class="nav-item"><i class="bi bi-cart-check"></i> Achats</a>
        <a href="stocks.php" class="nav-item"><i class="bi bi-bar-chart"></i> Stocks</a>
        <a href="promotions.php" class="nav-item"><i class="bi bi-percent"></i> Promotions</a>
        <a href="videos.php" class="nav-item"><i class="bi bi-camera-reels"></i> Vidéos</a>
        <a href="factures.php" class="nav-item"><i class="bi bi-file-earmark-text"></i> Factures</a>
        <a href="maintenance.php" class="nav-item"><i class="bi bi-tools"></i> Maintenance</a>
        <a href="avis_admin.php" class="nav-item active"><i class="bi bi-star"></i> Avis clients</a>

        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">⭐ Gestion des <span>Avis</span></div>
            <div class="topbar-breadcrumb">Avis clients</div>
        </div>
        <div>
            <a href="../index.php" class="btn-admin btn-site"><i class="bi bi-eye"></i> Voir le site</a>
        </div>
    </div>

    <div class="content">
        <?php if($message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $message ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-star"></i></div>
                <div>
                    <div class="stat-val"><?= $total_avis ?></div>
                    <div class="stat-lbl">Total avis</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-eye"></i></div>
                <div>
                    <div class="stat-val"><?= $avis_visibles ?></div>
                    <div class="stat-lbl">Avis publiés</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-eye-slash"></i></div>
                <div>
                    <div class="stat-val"><?= $avis_non_visibles ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-graph-up"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($note_moyenne, 1) ?></div>
                    <div class="stat-lbl">Note moyenne</div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">📋 Liste des avis</div>
                <div><?= count($avis) ?> avis</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Note</th>
                        <th>Commentaire</th>
                        <th>Recommandation</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($avis)): ?>
                        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-star"></i><p>Aucun avis</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach($avis as $a): ?>
                        <tr>
                            <td>#<?= $a['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($a['nom'] ?? 'Anonyme') ?></strong>
                                <br><small style="color:#8A99AA;"><?= htmlspecialchars($a['email'] ?? '') ?></small>
                            </td>
                            <td>
                                <div class="etoiles">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="bi bi-star<?= $i <= $a['note'] ? '-fill' : '' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td style="max-width:200px;font-size:0.8rem;color:#5A6B7A;">
                                <?= htmlspecialchars(substr($a['commentaire'] ?? '', 0, 60)) ?>...
                            </td>
                            <td style="font-size:0.75rem;"><?= htmlspecialchars($a['recommandation'] ?? '-') ?></td>
                            <td style="font-size:0.75rem;color:#999;"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
                            <td>
                                <span class="badge-statut <?= $a['est_visible'] ? 'badge-visible' : 'badge-cache' ?>">
                                    <?= $a['est_visible'] ? '✅ Publié' : '⏳ En attente' ?>
                                </span>
                            </td>
                            <td style="text-align:center;white-space:nowrap;">
                                <a href="avis_admin.php?toggle=<?= $a['id'] ?>" class="btn-act btn-toggle" title="Changer statut">
                                    <i class="bi bi-<?= $a['est_visible'] ? 'eye-slash' : 'eye' ?>"></i>
                                </a>
                                <a href="avis_admin.php?supprimer=<?= $a['id'] ?>" class="btn-act btn-delete" onclick="return confirm('Supprimer cet avis ?')">
                                    <i class="bi bi-trash3"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>