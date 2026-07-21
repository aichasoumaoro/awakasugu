<?php
// ============================================
// SESSION ADMIN SÉPARÉE
// ============================================
require_once 'session_config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$host   = 'localhost';
$dbname = 'awakasugu_db';
$user   = 'root';
$pass   = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Suppression
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
    header('Location: clients.php?msg=supprime');
    exit;
}

// Recherche
$search  = trim($_GET['search'] ?? '');
$where   = $search ? "WHERE nom LIKE ? OR email LIKE ? OR telephone LIKE ?" : "";
$params  = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$stmt    = $pdo->prepare("SELECT * FROM clients $where ORDER BY created_at DESC");
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Stats
$total_clients  = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_depense  = $pdo->query("SELECT COALESCE(SUM(total_depense), 0) FROM clients")->fetchColumn();
$nouveaux       = $pdo->query("SELECT COUNT(*) FROM clients WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$msg            = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients — Awa Ka Sugu Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #F5F7FA; color: #1A2C3E; display: flex; min-height: 100vh; }

        /* ============================================
           SIDEBAR
           ============================================ */
        .sidebar {
            width: 260px;
            background: #0D0B08;
            border-right: 1px solid rgba(200,146,42,0.12);
            position: fixed;
            top: 0; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 24px 24px 20px;
            border-bottom: 1px solid rgba(200,146,42,0.1);
        }
        .sidebar-brand .logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: #C8922A;
            letter-spacing: 4px;
            text-transform: uppercase;
        }
        .sidebar-brand .sub {
            font-size: 0.55rem;
            color: rgba(255,255,255,0.2);
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .sidebar-brand .user {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 14px;
            padding: 8px 12px;
            background: rgba(200,146,42,0.06);
            border-radius: 8px;
            border: 1px solid rgba(200,146,42,0.08);
        }
        .user-avatar {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; color: #fff; font-weight: 600;
            flex-shrink: 0;
        }
        .user-info .name { font-size: 0.75rem; color: #fff; font-weight: 500; }
        .user-info .role { font-size: 0.55rem; color: rgba(255,255,255,0.25); letter-spacing: 1px; text-transform: uppercase; }

        .nav-section {
            font-size: 0.5rem;
            color: rgba(255,255,255,0.12);
            letter-spacing: 3px;
            text-transform: uppercase;
            padding: 16px 24px 6px;
        }
        .sidebar nav { flex: 1; padding: 6px 12px; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 14px;
            border-radius: 8px;
            color: rgba(255,255,255,0.4);
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 500;
            border-left: 2px solid transparent;
            transition: all 0.2s;
            margin-bottom: 1px;
        }
        .nav-item i { font-size: 0.95rem; width: 18px; text-align: center; }
        .nav-item:hover { color: #fff; background: rgba(200,146,42,0.06); border-left-color: rgba(200,146,42,0.3); }
        .nav-item.active { color: #fff; background: rgba(200,146,42,0.08); border-left-color: #C8922A; }
        .nav-item.active i { color: #C8922A; }
        .nav-item.logout { color: rgba(231,76,60,0.4); margin-top: 4px; }
        .nav-item.logout:hover { color: #E74C3C; background: rgba(231,76,60,0.06); border-left-color: #E74C3C; }

        /* ============================================
           MAIN
           ============================================ */
        .main {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #F5F7FA;
            min-height: 100vh;
        }

        /* ===== TOPBAR ===== */
        .topbar {
            background: #fff;
            border-bottom: 1px solid #E8ECF0;
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar-left { display: flex; flex-direction: column; }
        .topbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: #0D0D0D;
        }
        .topbar-title span { color: #C8922A; }
        .topbar-breadcrumb {
            font-size: 0.7rem;
            color: #8A99AA;
            margin-top: 2px;
        }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .btn-top {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 7px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }
        .btn-top.gold { background: #C8922A; color: #fff; }
        .btn-top.gold:hover { background: #9A6E1A; }
        .btn-top.outline { border: 1.5px solid #E0E0E0; color: #555; background: #fff; }
        .btn-top.outline:hover { border-color: #C8922A; color: #C8922A; }

        /* ===== CONTENT ===== */
        .content { padding: 24px 32px; flex: 1; }

        /* ===== ALERT ===== */
        .alert-success {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #D4EDDA;
            border-left: 4px solid #27AE60;
            color: #0A3622;
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }

        /* ===== STATS ===== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid #E8ECF0;
            transition: all 0.2s;
        }
        .stat-box:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .stat-icon {
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .stat-icon.gold    { background: rgba(200,146,42,0.08); color: #C8922A; }
        .stat-icon.green   { background: rgba(27,122,74,0.08); color: #1A7A4A; }
        .stat-icon.blue    { background: rgba(41,128,185,0.08); color: #2980B9; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: #0D0D0D;
            line-height: 1;
        }
        .stat-lbl { font-size: 0.65rem; color: #8A99AA; margin-top: 2px; }

        /* ===== TOOLBAR ===== */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            border: 1.5px solid #E8ECF0;
            border-radius: 8px;
            padding: 0 14px;
            height: 38px;
            transition: border-color 0.2s;
            flex: 1;
            max-width: 340px;
        }
        .search-box:focus-within { border-color: #C8922A; }
        .search-box i { color: #bbb; font-size: 0.9rem; }
        .search-box input {
            border: none; outline: none;
            font-family: 'Inter', sans-serif;
            font-size: 0.8rem; color: #333;
            background: transparent; width: 100%;
        }
        .search-box input::placeholder { color: #bbb; }
        .toolbar-info { font-size: 0.78rem; color: #8A99AA; }
        .toolbar-info strong { color: #0D0D0D; }

        /* ===== TABLE ===== */
        .table-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #E8ECF0;
        }
        .table-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 24px;
            border-bottom: 1px solid #F0F2F5;
        }
        .table-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 0.95rem;
            font-weight: 600;
            color: #0D0D0D;
        }
        .table-count {
            font-size: 0.75rem;
            color: #8A99AA;
        }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0D0B08;
            color: #C8922A;
            font-family: 'Inter', sans-serif;
            font-size: 0.6rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 12px 18px;
            text-align: left;
        }
        thead th:last-child { text-align: center; }
        tbody td {
            padding: 12px 18px;
            font-size: 0.8rem;
            color: #333;
            border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #FDFBF8; }

        .client-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            display: inline-flex;
            align-items: center; justify-content: center;
            font-size: 0.8rem; font-weight: 700;
            color: #fff; flex-shrink: 0;
        }
        .client-info { display: flex; align-items: center; gap: 10px; }
        .client-name { font-weight: 600; color: #0D0D0D; font-size: 0.82rem; }
        .client-tel { font-size: 0.7rem; color: #8A99AA; margin-top: 1px; }

        .badge {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 3px 10px; border-radius: 16px;
            font-size: 0.65rem; font-weight: 600;
        }
        .badge-gold   { background: rgba(200,146,42,0.08); color: #C8922A; }
        .badge-green  { background: rgba(27,122,74,0.08); color: #1A7A4A; }
        .badge-grey   { background: #F0F2F5; color: #8A99AA; }

        .actions { display: flex; gap: 4px; justify-content: center; }
        .btn-act {
            width: 30px; height: 30px;
            border-radius: 6px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }
        .btn-act.view   { background: rgba(41,128,185,0.08); color: #2980B9; }
        .btn-act.view:hover { background: #2980B9; color: #fff; }
        .btn-act.delete { background: rgba(231,76,60,0.08); color: #E74C3C; }
        .btn-act.delete:hover { background: #E74C3C; color: #fff; }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #8A99AA;
        }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 12px; }
        .empty-state p { font-size: 0.9rem; }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 1100px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 16px; }
            .stats-row { grid-template-columns: 1fr; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
            .topbar { padding: 12px 16px; flex-wrap: wrap; }
            .topbar-title { font-size: 1.1rem; }
        }
        @media (max-width: 600px) {
            table { font-size: 0.75rem; }
            thead th { padding: 8px 12px; font-size: 0.55rem; }
            tbody td { padding: 8px 12px; }
            .client-avatar { width: 28px; height: 28px; font-size: 0.7rem; }
            .client-name { font-size: 0.75rem; }
            .btn-act { width: 26px; height: 26px; font-size: 0.75rem; }
        }
    </style>
</head>
<body>

<!-- ============================================
     SIDEBAR
     ============================================ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo">AWA KA SUGU</div>
        <div class="sub">Administration</div>
        <div class="user">
            <div class="user-avatar">A</div>
            <div class="user-info">
                <div class="name"><?= htmlspecialchars($_SESSION['admin_nom'] ?? 'Awa Doumbia') ?></div>
                <div class="role">Administratrice</div>
            </div>
        </div>
    </div>

    <nav>
        <div class="nav-section">Principal</div>
        <a href="dashboard.php" class="nav-item"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
        <a href="point_de_vente.php" class="nav-item"><i class="bi bi-cash-stack"></i> Point de vente</a>
        <a href="produits.php" class="nav-item"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="achats.php" class="nav-item"><i class="bi bi-cart-check"></i> Achats</a>
        <a href="commandes.php" class="nav-item"><i class="bi bi-receipt"></i> Commandes</a>
        <a href="clients.php" class="nav-item active"><i class="bi bi-people"></i> Clients</a>

        <div class="nav-section">Restaurant</div>
        <a href="plats.php" class="nav-item"><i class="bi bi-cup-hot"></i> Plats</a>
        <a href="commandes_repas.php" class="nav-item"><i class="bi bi-bag-check"></i> Commandes repas</a>
        <a href="reservations.php" class="nav-item"><i class="bi bi-calendar-check"></i> Réservations</a>

        <div class="nav-section">Gestion</div>
        <a href="maintenance.php" class="nav-item"><i class="bi bi-tools"></i> Maintenance</a>
        <a href="stocks.php" class="nav-item"><i class="bi bi-bar-chart"></i> Stocks</a>
        <a href="promotions.php" class="nav-item"><i class="bi bi-percent"></i> Promotions</a>
        <a href="bannieres.php" class="nav-item"><i class="bi bi-images"></i> Bannières</a>
        <a href="videos.php" class="nav-item"><i class="bi bi-camera-reels"></i> Vidéos</a>
        <a href="factures.php" class="nav-item"><i class="bi bi-file-earmark-text"></i> Factures</a>
        <a href="rapports.php" class="nav-item"><i class="bi bi-bar-chart"></i> Rapports</a>

        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<!-- ============================================
     MAIN
     ============================================ -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">👥 Gestion des <span>Clients</span></div>
            <div class="topbar-breadcrumb">Administration → Clients</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" target="_blank" class="btn-top outline">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- Alert -->
        <?php if($msg == 'supprime'): ?>
        <div class="alert-success">
            <i class="bi bi-check-circle-fill"></i>
            Client supprimé avec succès.
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon gold"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_clients) ?></div>
                    <div class="stat-lbl">Total clients inscrits</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon green"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_depense, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Total dépensé</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon blue"><i class="bi bi-person-plus-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $nouveaux ?></div>
                    <div class="stat-lbl">Nouveaux aujourd'hui</div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <form method="GET" style="display:flex;gap:8px;flex:1;max-width:380px;">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search"
                           placeholder="Nom, email ou téléphone..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn-top gold" style="white-space:nowrap;">
                    <i class="bi bi-search"></i> Chercher
                </button>
                <?php if($search): ?>
                <a href="clients.php" class="btn-top outline">
                    <i class="bi bi-x"></i> Effacer
                </a>
                <?php endif; ?>
            </form>
            <div class="toolbar-info">
                <strong><?= count($clients) ?></strong> client<?= count($clients) > 1 ? 's' : '' ?> trouvé<?= count($clients) > 1 ? 's' : '' ?>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">📋 Liste des clients</div>
                <div class="table-count"><?= count($clients) ?> résultat<?= count($clients) > 1 ? 's' : '' ?></div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th style="text-align:center;">Commandes</th>
                        <th>Total dépensé</th>
                        <th style="text-align:center;">Points</th>
                        <th>Inscrit le</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($clients)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="bi bi-people"></i>
                                <p>Aucun client trouvé<?= $search ? ' pour "'.htmlspecialchars($search).'"' : '' ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($clients as $c): ?>
                    <?php $initiale = strtoupper(mb_substr($c['nom'] ?? 'C', 0, 1)); ?>
                    <tr>
                        <td style="color:#8A99AA;font-size:0.75rem;">#<?= $c['id'] ?></td>
                        <td>
                            <div class="client-info">
                                <div class="client-avatar"><?= $initiale ?></div>
                                <div>
                                    <div class="client-name"><?= htmlspecialchars($c['nom'] ?? '') ?> <?= htmlspecialchars($c['prenom'] ?? '') ?></div>
                                    <div class="client-tel"><i class="bi bi-phone" style="font-size:0.65rem;"></i> <?= htmlspecialchars($c['telephone'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= !empty($c['email']) ? htmlspecialchars($c['email']) : '<span style="color:#ccc;">—</span>' ?></td>
                        <td><?= htmlspecialchars($c['telephone'] ?? '') ?></td>
                        <td style="text-align:center;">
                            <span class="badge <?= ($c['nb_commandes']??0) > 0 ? 'badge-gold' : 'badge-grey' ?>">
                                <?= $c['nb_commandes'] ?? 0 ?>
                            </span>
                        </td>
                        <td style="font-weight:600;color:<?= ($c['total_depense']??0) > 0 ? '#C8922A' : '#8A99AA' ?>;">
                            <?= number_format($c['total_depense'] ?? 0, 0, ',', ' ') ?> FCFA
                        </td>
                        <td style="text-align:center;">
                            <span class="badge <?= ($c['points_fidelite']??0) > 0 ? 'badge-green' : 'badge-grey' ?>">
                                <?= $c['points_fidelite'] ?? 0 ?> pts
                            </span>
                        </td>
                        <td style="color:#8A99AA;font-size:0.75rem;">
                            <?= date('d/m/Y', strtotime($c['created_at'] ?? 'now')) ?>
                        </td>
                        <td style="text-align:center;">
                            <div class="actions">
                                <a href="client_detail.php?id=<?= $c['id'] ?>" class="btn-act view" title="Voir détails">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="clients.php?supprimer=<?= $c['id'] ?>"
                                   class="btn-act delete" title="Supprimer"
                                   onclick="return confirm('Supprimer ce client définitivement ?')">
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

</body>
</html>