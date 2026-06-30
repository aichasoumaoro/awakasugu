<?php
// ============================================
// ADMIN - GESTION DE LA FIDÉLITÉ
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
// AJOUTER DES POINTS À UN CLIENT
// ============================================
if (isset($_POST['ajouter_points']) && isset($_POST['client_id']) && isset($_POST['points'])) {
    $client_id = (int)$_POST['client_id'];
    $points = (int)$_POST['points'];
    $motif = trim($_POST['motif'] ?? 'Ajout manuel');
    
    if ($points > 0) {
        // Vérifier si le client existe dans la table fidelite
        $stmt = $pdo->prepare("SELECT id FROM fidelite WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $fidelite = $stmt->fetch();
        
        if ($fidelite) {
            $stmt = $pdo->prepare("UPDATE fidelite SET points = points + ? WHERE client_id = ?");
            $stmt->execute([$points, $client_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO fidelite (client_id, points) VALUES (?, ?)");
            $stmt->execute([$client_id, $points]);
        }
        
        // Ajouter dans l'historique (si la table existe)
        try {
            $stmt = $pdo->prepare("INSERT INTO fidelite_historique (client_id, points, action, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$client_id, $points, $motif]);
        } catch(PDOException $e) {
            // La table n'existe pas encore
        }
        
        header('Location: fidelite.php?msg=ajout');
        exit;
    }
}

// ============================================
// RETIRER DES POINTS À UN CLIENT
// ============================================
if (isset($_POST['retirer_points']) && isset($_POST['client_id']) && isset($_POST['points'])) {
    $client_id = (int)$_POST['client_id'];
    $points = (int)$_POST['points'];
    $motif = trim($_POST['motif'] ?? 'Retrait manuel');
    
    if ($points > 0) {
        $stmt = $pdo->prepare("SELECT points FROM fidelite WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $fidelite = $stmt->fetch();
        
        if ($fidelite && $fidelite['points'] >= $points) {
            $stmt = $pdo->prepare("UPDATE fidelite SET points = points - ? WHERE client_id = ?");
            $stmt->execute([$points, $client_id]);
            
            // Ajouter dans l'historique
            try {
                $stmt = $pdo->prepare("INSERT INTO fidelite_historique (client_id, points, action, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$client_id, -$points, $motif]);
            } catch(PDOException $e) {
                // La table n'existe pas encore
            }
            
            header('Location: fidelite.php?msg=retrait');
            exit;
        } else {
            header('Location: fidelite.php?msg=erreur_solde');
            exit;
        }
    }
}

// ============================================
// RECHERCHE ET FILTRES
// ============================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$order = isset($_GET['order']) ? $_GET['order'] : 'points_desc';

// Construction de la requête
$sql = "
    SELECT 
        c.id as client_id,
        c.nom,
        c.prenom,
        c.email,
        c.telephone,
        c.total_depense,
        c.nb_commandes,
        COALESCE(f.points, 0) as points
    FROM clients c
    LEFT JOIN fidelite f ON f.client_id = c.id
    WHERE 1=1
";

$params = [];
if (!empty($search)) {
    $sql .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR c.email LIKE ? OR c.telephone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

switch ($order) {
    case 'points_asc':
        $sql .= " ORDER BY points ASC";
        break;
    case 'nom_asc':
        $sql .= " ORDER BY c.nom ASC, c.prenom ASC";
        break;
    case 'nom_desc':
        $sql .= " ORDER BY c.nom DESC, c.prenom DESC";
        break;
    case 'depense_desc':
        $sql .= " ORDER BY c.total_depense DESC";
        break;
    default:
        $sql .= " ORDER BY points DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Statistiques
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_points = $pdo->query("SELECT COALESCE(SUM(points), 0) FROM fidelite")->fetchColumn();
$clients_avec_points = $pdo->query("SELECT COUNT(*) FROM fidelite WHERE points > 0")->fetchColumn();

$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fidélité - Admin Awa Ka Sugu</title>
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
        .nav-item.logout { color: rgba(231,76,60,0.6); }
        .nav-item.logout:hover { color: #E74C3C; background: rgba(231,76,60,0.08); border-left-color: #E74C3C; }

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
        .topbar-right { display: flex; align-items: center; gap: 10px; }
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
        .btn-success { background: #27AE60; color: #fff; }
        .btn-success:hover { background: #1A7A4A; color: #fff; }
        .btn-danger { background: #E74C3C; color: #fff; }
        .btn-danger:hover { background: #C0392B; color: #fff; }

        .content { padding: 28px 32px; flex: 1; }

        .alert-ok {
            display: flex; align-items: center; gap: 10px;
            background: #D4EDDA; border-left: 4px solid #27AE60;
            color: #0A3622; padding: 13px 18px; border-radius: 8px;
            font-size: 0.88rem; margin-bottom: 24px;
            animation: slideDown 0.4s ease;
        }
        .alert-error {
            display: flex; align-items: center; gap: 10px;
            background: #F8D7DA; border-left: 4px solid #E74C3C;
            color: #721C24; padding: 13px 18px; border-radius: 8px;
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
        .stat-box:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .ic-or { background: rgba(200,146,42,0.1); color: #C8922A; }
        .ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
        .ic-blue { background: rgba(41,128,185,0.1); color: #2980B9; }
        .ic-purple { background: rgba(142,68,173,0.1); color: #8E44AD; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem; font-weight: 700;
            color: #0D0D0D; line-height: 1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 2px; }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-box {
            display: flex; align-items: center; gap: 10px;
            background: #fff; border: 1.5px solid #E8ECF0;
            border-radius: 8px; padding: 0 14px; height: 40px;
            transition: border-color 0.2s; flex: 1; max-width: 340px;
        }
        .search-box:focus-within { border-color: #C8922A; }
        .search-box i { color: #bbb; font-size: 0.9rem; }
        .search-box input {
            border: none; outline: none;
            font-family: 'Jost', sans-serif; font-size: 0.85rem;
            color: #333; background: transparent; width: 100%;
        }
        .order-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 6px;
            font-family: 'Jost', sans-serif; font-size: 0.7rem;
            font-weight: 600; text-decoration: none;
            border: 1.5px solid #E0E0E0;
            background: #fff; color: #666;
            transition: all 0.2s; white-space: nowrap;
        }
        .order-btn:hover { border-color: #C8922A; color: #C8922A; }
        .order-btn.active { background: #C8922A; color: #fff; border-color: #C8922A; }

        .table-card {
            background: #fff; border-radius: 14px;
            overflow: hidden; border: 1px solid #E8ECF0;
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
            font-size: 0.65rem; font-weight: 600;
            letter-spacing: 1px; text-transform: uppercase;
            padding: 12px 16px; text-align: left;
        }
        tbody td {
            padding: 12px 16px; font-size: 0.84rem;
            color: #333; border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        tbody tr:hover td { background: #FEFBF5; }

        .badge-points {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-points.eleve { background: #D4EDDA; color: #1A7A4A; }
        .badge-points.moyen { background: #FFF3CD; color: #856404; }
        .badge-points.faible { background: #F8D7DA; color: #721C24; }

        .btn-ajouter-points {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            border: none;
            background: #27AE60;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-ajouter-points:hover { background: #1A7A4A; }
        .btn-retirer-points {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            border: none;
            background: #E74C3C;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-retirer-points:hover { background: #C0392B; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-box h3 {
            font-family: 'Playfair Display', serif;
            margin-bottom: 15px;
        }
        .modal-box .form-group {
            margin-bottom: 15px;
        }
        .modal-box label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #666;
            margin-bottom: 4px;
        }
        .modal-box input, .modal-box textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #E0E0E0;
            border-radius: 8px;
            font-family: 'Jost', sans-serif;
            font-size: 0.9rem;
        }
        .modal-box input:focus, .modal-box textarea:focus {
            outline: none;
            border-color: #C8922A;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        @media (max-width: 1100px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
            table { display: block; overflow-x: auto; }
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
        <a href="achats.php" class="nav-item"><i class="bi bi-cart-check"></i> Achats</a>
        <a href="commandes.php" class="nav-item"><i class="bi bi-receipt"></i> Commandes</a>
        <a href="clients.php" class="nav-item"><i class="bi bi-people"></i> Clients</a>
        <div class="nav-section">Gestion</div>
        <a href="fidelite.php" class="nav-item active"><i class="bi bi-star"></i> Fidélité</a>
        <a href="maintenance.php" class="nav-item"><i class="bi bi-tools"></i> Maintenance</a>
        <a href="videos.php" class="nav-item"><i class="bi bi-camera-reels"></i> Vidéos</a>
        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">⭐ Gestion de la <span>Fidélité</span></div>
            <div class="topbar-breadcrumb">Administration → Fidélité</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" target="_blank" class="btn-admin btn-outline">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <div class="content">
        <?php if($msg == 'ajout'): ?>
            <div class="alert-ok"><i class="bi bi-check-circle-fill"></i> Points ajoutés avec succès !</div>
        <?php elseif($msg == 'retrait'): ?>
            <div class="alert-ok"><i class="bi bi-check-circle-fill"></i> Points retirés avec succès !</div>
        <?php elseif($msg == 'erreur_solde'): ?>
            <div class="alert-error"><i class="bi bi-exclamation-triangle-fill"></i> Solde insuffisant pour effectuer ce retrait.</div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-people"></i></div>
                <div>
                    <div class="stat-val"><?= $total_clients ?></div>
                    <div class="stat-lbl">Total clients</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-star-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $total_points ?></div>
                    <div class="stat-lbl">Total points</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-person-check"></i></div>
                <div>
                    <div class="stat-val"><?= $clients_avec_points ?></div>
                    <div class="stat-lbl">Clients avec points</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-purple"><i class="bi bi-graph-up"></i></div>
                <div>
                    <div class="stat-val"><?= round($total_clients > 0 ? ($clients_avec_points / $total_clients) * 100 : 0, 1) ?>%</div>
                    <div class="stat-lbl">Taux de participation</div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <form method="GET" style="display:flex;gap:10px;flex:1;max-width:380px;">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" placeholder="Rechercher un client..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn-admin btn-or"><i class="bi bi-search"></i></button>
            </form>
            <a href="?order=points_desc" class="order-btn <?= $order == 'points_desc' ? 'active' : '' ?>">⬇ Points</a>
            <a href="?order=points_asc" class="order-btn <?= $order == 'points_asc' ? 'active' : '' ?>">⬆ Points</a>
            <a href="?order=nom_asc" class="order-btn <?= $order == 'nom_asc' ? 'active' : '' ?>">⬆ Nom</a>
            <a href="?order=nom_desc" class="order-btn <?= $order == 'nom_desc' ? 'active' : '' ?>">⬇ Nom</a>
            <a href="?order=depense_desc" class="order-btn <?= $order == 'depense_desc' ? 'active' : '' ?>">💰 Dépense</a>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">📋 Clients et leurs points</div>
                <div class="table-count"><?= count($clients) ?> client(s)</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th style="text-align:center;">Points</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($clients)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:40px;color:#8A99AA;">
                            <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                            Aucun client trouvé
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($clients as $c): 
                        $points_class = 'faible';
                        if($c['points'] >= 100) $points_class = 'eleve';
                        elseif($c['points'] >= 30) $points_class = 'moyen';
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($c['prenom'] ?? '') ?> <?= htmlspecialchars($c['nom'] ?? '') ?></strong>
                            <br><small style="color:#8A99AA;font-size:0.65rem;">
                                <?= $c['nb_commandes'] ?? 0 ?> commande(s)
                            </small>
                        </td>
                        <td><?= htmlspecialchars($c['telephone']) ?></td>
                        <td><?= htmlspecialchars($c['email']) ?></td>
                        <td style="text-align:center;">
                            <span class="badge-points <?= $points_class ?>">
                                <?= $c['points'] ?> pts
                            </span>
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <button class="btn-ajouter-points" onclick="openAjouterModal(<?= $c['client_id'] ?>, '<?= htmlspecialchars($c['prenom'] ?? '') ?> <?= htmlspecialchars($c['nom'] ?? '') ?>')">
                                <i class="bi bi-plus-circle"></i> Ajouter
                            </button>
                            <button class="btn-retirer-points" onclick="openRetirerModal(<?= $c['client_id'] ?>, '<?= htmlspecialchars($c['prenom'] ?? '') ?> <?= htmlspecialchars($c['nom'] ?? '') ?>', <?= $c['points'] ?>)">
                                <i class="bi bi-dash-circle"></i> Retirer
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== MODAL AJOUTER POINTS ===== -->
<div class="modal-overlay" id="modalAjouter">
    <div class="modal-box">
        <h3>⭐ Ajouter des points</h3>
        <p style="color:#8A99AA;font-size:0.85rem;margin-bottom:15px;">
            Client : <strong id="ajouterNom"></strong>
        </p>
        <form method="POST">
            <input type="hidden" name="client_id" id="ajouterClientId">
            <div class="form-group">
                <label>Nombre de points à ajouter</label>
                <input type="number" name="points" min="1" value="10" required>
            </div>
            <div class="form-group">
                <label>Motif</label>
                <input type="text" name="motif" placeholder="Ex: Commande fidélité" value="Ajout manuel">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-admin btn-outline" onclick="closeModal('modalAjouter')">Annuler</button>
                <button type="submit" name="ajouter_points" class="btn-admin btn-success">
                    <i class="bi bi-plus-circle"></i> Ajouter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== MODAL RETIRER POINTS ===== -->
<div class="modal-overlay" id="modalRetirer">
    <div class="modal-box">
        <h3>📤 Retirer des points</h3>
        <p style="color:#8A99AA;font-size:0.85rem;margin-bottom:15px;">
            Client : <strong id="retirerNom"></strong><br>
            <span style="font-size:0.8rem;color:#666;">Solde actuel : <strong id="retirerSolde">0</strong> points</span>
        </p>
        <form method="POST">
            <input type="hidden" name="client_id" id="retirerClientId">
            <div class="form-group">
                <label>Nombre de points à retirer</label>
                <input type="number" name="points" min="1" max="100" value="5" required>
            </div>
            <div class="form-group">
                <label>Motif</label>
                <input type="text" name="motif" placeholder="Ex: Utilisation points" value="Retrait manuel">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-admin btn-outline" onclick="closeModal('modalRetirer')">Annuler</button>
                <button type="submit" name="retirer_points" class="btn-admin btn-danger">
                    <i class="bi bi-dash-circle"></i> Retirer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAjouterModal(clientId, nom) {
    document.getElementById('ajouterClientId').value = clientId;
    document.getElementById('ajouterNom').textContent = nom;
    document.getElementById('modalAjouter').classList.add('active');
}

function openRetirerModal(clientId, nom, solde) {
    document.getElementById('retirerClientId').value = clientId;
    document.getElementById('retirerNom').textContent = nom;
    document.getElementById('retirerSolde').textContent = solde;
    document.getElementById('modalRetirer').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Fermer les modals en cliquant en dehors
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

</body>
</html>