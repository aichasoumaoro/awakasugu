<?php
// ============================================
// ADMIN - GESTION DES COMMANDES GROUPÉES PAR CLIENT
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
// FONCTION POUR AJOUTER DES POINTS
// ============================================
function ajouterPoints($pdo, $client_id, $commande_id, $points, $action) {
    if ($points <= 0 || !$client_id) return false;
    
    try {
        // Ajouter les points dans la table clients
        $stmt = $pdo->prepare("UPDATE clients SET points_fidelite = points_fidelite + ? WHERE id = ?");
        $stmt->execute([$points, $client_id]);
        
        // Ajouter dans l'historique
        $stmt = $pdo->prepare("
            INSERT INTO fidelite_historique (client_id, commande_id, points, action, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$client_id, $commande_id, $points, $action]);
        
        return true;
    } catch(PDOException $e) {
        error_log("Erreur ajout points : " . $e->getMessage());
        return false;
    }
}

// ============================================
// FONCTION POUR RETIRER DES POINTS
// ============================================
function retirerPoints($pdo, $client_id, $commande_id, $points, $action) {
    if ($points <= 0 || !$client_id) return false;
    
    try {
        $stmt = $pdo->prepare("UPDATE clients SET points_fidelite = GREATEST(points_fidelite - ?, 0) WHERE id = ?");
        $stmt->execute([$points, $client_id]);
        
        $stmt = $pdo->prepare("
            INSERT INTO fidelite_historique (client_id, commande_id, points, action, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$client_id, $commande_id, -$points, $action]);
        
        return true;
    } catch(PDOException $e) {
        error_log("Erreur retrait points : " . $e->getMessage());
        return false;
    }
}

// ============================================
// CHANGER LE STATUT D'UNE COMMANDE (BOUTIQUE)
// ============================================
if (isset($_GET['statut']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $nouveau_statut = $_GET['statut'];
    $statuts_valides = ['en_attente', 'confirmee', 'en_preparation', 'en_livraison', 'livree', 'annulee'];
    
    if (in_array($nouveau_statut, $statuts_valides)) {
        // Récupérer l'ancien statut et les infos
        $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
        $stmt->execute([$id]);
        $commande = $stmt->fetch();
        $ancien_statut = $commande['statut'] ?? 'en_attente';
        $client_id = $commande['client_id'] ?? null;
        $telephone = $commande['telephone'] ?? null;
        $total = $commande['total'] ?? 0;
        
        // Mettre à jour le statut
        $stmt = $pdo->prepare("UPDATE commandes SET statut = ? WHERE id = ?");
        $stmt->execute([$nouveau_statut, $id]);
        
        // Gestion des points
        $statuts_avec_points = ['livree'];
        $statuts_sans_points = ['annulee'];
        
        // Trouver le client_id via téléphone si nécessaire
        if (!$client_id && !empty($telephone)) {
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE telephone = ?");
            $stmt->execute([$telephone]);
            $client = $stmt->fetch();
            if ($client) {
                $client_id = $client['id'];
                $stmt = $pdo->prepare("UPDATE commandes SET client_id = ? WHERE id = ?");
                $stmt->execute([$client_id, $id]);
            }
        }
        
        $message = 'Statut mis à jour !';
        
        // Ajouter des points si livrée
        if ($client_id && in_array($nouveau_statut, $statuts_avec_points) && !in_array($ancien_statut, $statuts_avec_points)) {
            $points = floor($total / 1000);
            if ($points > 0) {
                if (ajouterPoints($pdo, $client_id, $id, $points, "Commande #{$id} - boutique - livrée")) {
                    $message .= ' +' . $points . ' points gagnés !';
                }
            }
        }
        
        // Retirer des points si annulée
        if ($client_id && in_array($nouveau_statut, $statuts_sans_points) && in_array($ancien_statut, $statuts_avec_points)) {
            $points = floor($total / 1000);
            if ($points > 0) {
                if (retirerPoints($pdo, $client_id, $id, $points, "Annulation commande #{$id}")) {
                    $message .= ' -' . $points . ' points retirés.';
                }
            }
        }
        
        $_SESSION['message_commande'] = $message;
        header('Location: commandes.php');
        exit;
    }
}

// ============================================
// SUPPRIMER UNE COMMANDE
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM commandes WHERE id = ?")->execute([$id]);
    $_SESSION['message_commande'] = 'Commande supprimée !';
    header('Location: commandes.php');
    exit;
}

$filtre = $_GET['filtre'] ?? 'toutes';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// ============================================
// REQUÊTE GROUPÉE PAR CLIENT
// ============================================
$sql = "
    SELECT 
        c.nom_client,
        c.telephone,
        c.adresse_livraison,
        COUNT(c.id) as nb_commandes,
        SUM(c.total) as total_global,
        MAX(c.created_at) as derniere_commande,
        GROUP_CONCAT(c.numero_commande SEPARATOR ', ') as commandes,
        GROUP_CONCAT(c.statut SEPARATOR ', ') as statuts,
        GROUP_CONCAT(c.id SEPARATOR ',') as commande_ids,
        MAX(c.id) as derniere_id
    FROM commandes c
    WHERE 1=1
";

$params = [];
if ($filtre != 'toutes') {
    $sql .= " AND c.statut = ?";
    $params[] = $filtre;
}

if (!empty($search)) {
    $sql .= " AND (c.nom_client LIKE ? OR c.telephone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY c.nom_client, c.telephone 
          ORDER BY derniere_commande DESC";

// Compter le nombre de groupes
$count_sql = "SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE 1=1";
$count_params = [];
if ($filtre != 'toutes') {
    $count_sql .= " AND statut = ?";
    $count_params[] = $filtre;
}
if (!empty($search)) {
    $count_sql .= " AND (nom_client LIKE ? OR telephone LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
}

$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($count_params);
$total_groupes = (int)$stmt_count->fetchColumn();
$total_pages = ($total_groupes > 0) ? ceil($total_groupes / $per_page) : 1;

$sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$groupes = $stmt->fetchAll();

// Statistiques
$stats = [
    'toutes' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes")->fetchColumn(),
    'en_attente' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'en_attente'")->fetchColumn(),
    'confirmee' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'confirmee'")->fetchColumn(),
    'en_preparation' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'en_preparation'")->fetchColumn(),
    'en_livraison' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'en_livraison'")->fetchColumn(),
    'livree' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'livree'")->fetchColumn(),
    'annulee' => (int)$pdo->query("SELECT COUNT(DISTINCT nom_client, telephone) FROM commandes WHERE statut = 'annulee'")->fetchColumn(),
];

$message = $_SESSION['message_commande'] ?? '';
unset($_SESSION['message_commande']);

$statut_labels = [
    'en_attente' => ['label' => 'En attente', 'class' => 'statut-en_attente'],
    'confirmee' => ['label' => 'Confirmée', 'class' => 'statut-confirmee'],
    'en_preparation' => ['label' => 'Préparation', 'class' => 'statut-en_preparation'],
    'en_livraison' => ['label' => 'Livraison', 'class' => 'statut-en_livraison'],
    'livree' => ['label' => 'Livrée', 'class' => 'statut-livree'],
    'annulee' => ['label' => 'Annulée', 'class' => 'statut-annulee']
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Vos styles existants */
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
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 25px;
        }
        .stat-box {
            background: #fff;
            border-radius: 10px;
            padding: 12px 10px;
            text-align: center;
            border: 1px solid #E8ECF0;
            transition: all 0.22s;
        }
        .stat-box:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
        .stat-box .number {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem; font-weight: 700;
        }
        .stat-box .label {
            font-size: 0.55rem; color: #8A99AA; text-transform: uppercase; letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .stat-box.toutes .number { color: #1A2C3E; }
        .stat-box.attente .number { color: #E67E22; }
        .stat-box.confirmee .number { color: #2980B9; }
        .stat-box.preparation .number { color: #8E44AD; }
        .stat-box.livraison .number { color: #C8922A; }
        .stat-box.livree .number { color: #27AE60; }
        .stat-box.annulee .number { color: #E74C3C; }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
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
        .filtre-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 6px;
            font-family: 'Jost', sans-serif; font-size: 0.7rem;
            font-weight: 600; text-decoration: none;
            border: 1.5px solid #E0E0E0;
            background: #fff; color: #666;
            transition: all 0.2s; white-space: nowrap;
        }
        .filtre-btn:hover { border-color: #C8922A; color: #C8922A; }
        .filtre-btn.actif { background: #C8922A; color: #fff; border-color: #C8922A; }
        .filtre-btn.rouge.actif { background: #E74C3C; border-color: #E74C3C; }
        .filtre-btn.orange.actif { background: #E67E22; border-color: #E67E22; }

        .table-card {
            background: #fff; border-radius: 14px;
            overflow: hidden; border: 1px solid #E8ECF0;
        }
        .table-card-header {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-bottom: 1px solid #F0F2F5;
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
            padding: 12px 16px; font-size: 0.82rem;
            color: #333; border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        tbody tr:hover td { background: #FEFBF5; }

        .badge-statut {
            display: inline-block; padding: 3px 10px; border-radius: 20px;
            font-size: 0.65rem; font-weight: 600;
        }
        .statut-en_attente { background: #FEF6E6; color: #E67E22; }
        .statut-confirmee { background: #E8F5E9; color: #2E7D32; }
        .statut-en_preparation { background: #E3F2FD; color: #1565C0; }
        .statut-en_livraison { background: #F3E5F5; color: #6A1B9A; }
        .statut-livree { background: #E8F5E9; color: #1A7A4A; }
        .statut-annulee { background: #FFEBEE; color: #C62828; }

        .btn-act {
            width: 32px; height: 32px;
            border-radius: 7px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.85rem; text-decoration: none;
            transition: all 0.2s; border: none;
        }
        .btn-edit { background: rgba(41,128,185,0.1); color: #2980B9; }
        .btn-edit:hover { background: #2980B9; color: #fff; }

        .btn-detail-client {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            background: rgba(200,146,42,0.1);
            color: #C8922A;
            transition: all 0.3s;
        }
        .btn-detail-client:hover { background: #C8922A; color: #fff; }

        .badge-commande {
            display: inline-block;
            background: #C8922A;
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .empty-state {
            text-align: center; padding: 50px 20px; color: #8A99AA;
        }
        .empty-state i { font-size: 3rem; display: block; margin-bottom: 16px; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 6px;
            padding: 18px 0;
        }
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
            color: #666;
            background: white;
            border: 1px solid #E0E0E0;
        }
        .pagination a:hover { background: #C8922A; color: white; border-color: #C8922A; }
        .pagination .active { background: #C8922A; color: white; border-color: #C8922A; }
        .pagination .disabled { opacity: 0.4; cursor: not-allowed; }

        @media (max-width: 1100px) { .stats-row { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 16px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
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
        <a href="commandes.php" class="nav-item active"><i class="bi bi-receipt"></i> Commandes</a>
        <a href="clients.php" class="nav-item"><i class="bi bi-people"></i> Clients</a>
        <div class="nav-section">Restaurant</div>
        <a href="plats.php" class="nav-item"><i class="bi bi-cup-hot"></i> Plats</a>
        <a href="commandes_repas.php" class="nav-item"><i class="bi bi-bag-check"></i> Commandes repas</a>
        <a href="reservations.php" class="nav-item"><i class="bi bi-calendar-check"></i> Réservations</a>
        <div class="nav-section">Gestion</div>
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
            <div class="topbar-title">📦 Gestion des <span>Commandes</span></div>
            <div class="topbar-breadcrumb">Administration → Commandes (groupées par client)</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" target="_blank" class="btn-admin btn-outline">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <div class="content">
        <?php if($message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $message ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box toutes">
                <div class="number"><?= $stats['toutes'] ?></div>
                <div class="label">Clients</div>
            </div>
            <div class="stat-box attente">
                <div class="number"><?= $stats['en_attente'] ?></div>
                <div class="label">En attente</div>
            </div>
            <div class="stat-box confirmee">
                <div class="number"><?= $stats['confirmee'] ?></div>
                <div class="label">Confirmées</div>
            </div>
            <div class="stat-box preparation">
                <div class="number"><?= $stats['en_preparation'] ?></div>
                <div class="label">Préparation</div>
            </div>
            <div class="stat-box livraison">
                <div class="number"><?= $stats['en_livraison'] ?></div>
                <div class="label">Livraison</div>
            </div>
            <div class="stat-box livree">
                <div class="number"><?= $stats['livree'] ?></div>
                <div class="label">Livrées</div>
            </div>
            <div class="stat-box annulee">
                <div class="number"><?= $stats['annulee'] ?></div>
                <div class="label">Annulées</div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <form method="GET" style="display:flex;gap:8px;flex:1;max-width:380px;">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" placeholder="Rechercher un client..." value="<?= htmlspecialchars($search) ?>">
                    <?php if($filtre != 'toutes'): ?><input type="hidden" name="filtre" value="<?= htmlspecialchars($filtre) ?>"><?php endif; ?>
                </div>
                <button type="submit" class="btn-admin btn-or"><i class="bi bi-search"></i></button>
            </form>
            <a href="commandes.php" class="filtre-btn <?= $filtre == 'toutes' ? 'actif' : '' ?>">Tous</a>
            <a href="commandes.php?filtre=en_attente" class="filtre-btn orange <?= $filtre == 'en_attente' ? 'actif orange' : '' ?>">En attente</a>
            <a href="commandes.php?filtre=confirmee" class="filtre-btn <?= $filtre == 'confirmee' ? 'actif' : '' ?>">Confirmées</a>
            <a href="commandes.php?filtre=en_preparation" class="filtre-btn <?= $filtre == 'en_preparation' ? 'actif' : '' ?>">Préparation</a>
            <a href="commandes.php?filtre=en_livraison" class="filtre-btn <?= $filtre == 'en_livraison' ? 'actif' : '' ?>">Livraison</a>
            <a href="commandes.php?filtre=livree" class="filtre-btn <?= $filtre == 'livree' ? 'actif' : '' ?>">Livrées</a>
            <a href="commandes.php?filtre=annulee" class="filtre-btn rouge <?= $filtre == 'annulee' ? 'actif rouge' : '' ?>">Annulées</a>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">👥 Clients avec leurs commandes</div>
                <div class="table-count"><?= $total_groupes ?> client(s)</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Téléphone</th>
                        <th style="text-align:center;">Commandes</th>
                        <th style="text-align:right;">Total</th>
                        <th>Dernière</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($groupes)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p>Aucun client trouvé</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($groupes as $g): 
                        $statuts = explode(', ', $g['statuts']);
                        $statut_global = 'en_attente';
                        $statut_priority = ['livree' => 5, 'en_livraison' => 4, 'en_preparation' => 3, 'confirmee' => 2, 'en_attente' => 1, 'annulee' => 0];
                        foreach($statuts as $s) {
                            $s = trim($s);
                            if(isset($statut_priority[$s]) && $statut_priority[$s] > $statut_priority[$statut_global]) {
                                $statut_global = $s;
                            }
                        }
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($g['nom_client']) ?></strong>
                            <br><small style="color:#8A99AA;font-size:0.65rem;">
                                <?= $g['nb_commandes'] ?> commande(s)
                            </small>
                        </td>
                        <td><?= htmlspecialchars($g['telephone']) ?></td>
                        <td style="text-align:center;">
                            <span class="badge-commande">
                                <?= $g['nb_commandes'] ?>
                            </span>
                            <br>
                            <small style="color:#8A99AA;font-size:0.6rem;">
                                <?php 
                                $nums = explode(', ', $g['commandes']);
                                echo implode(', ', array_slice($nums, 0, 2));
                                if(count($nums) > 2) echo '...';
                                ?>
                            </small>
                        </td>
                        <td style="text-align:right;font-weight:600;color:#C8922A;">
                            <?= number_format($g['total_global'], 0, ',', ' ') ?> FCFA
                        </td>
                        <td style="font-size:0.7rem;color:#8A99AA;">
                            <?= date('d/m/Y H:i', strtotime($g['derniere_commande'])) ?>
                            <br>
                            <span class="badge-statut statut-<?= $statut_global ?>">
                                <?= $statut_labels[$statut_global]['label'] ?? $statut_global ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <a href="commandes_client.php?telephone=<?= urlencode($g['telephone']) ?>" class="btn-detail-client" title="Voir toutes les commandes du client">
                                <i class="bi bi-eye"></i> Voir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&filtre=<?= $filtre ?>&search=<?= urlencode($search) ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i class="bi bi-chevron-left"></i></span>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <?php if($i == $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&filtre=<?= $filtre ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?>&filtre=<?= $filtre ?>&search=<?= urlencode($search) ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i class="bi bi-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Légende -->
        <div style="margin-top:15px;padding:12px 20px;background:#fff;border-radius:10px;border:1px solid #E8ECF0;display:flex;flex-wrap:wrap;gap:15px;align-items:center;">
            <span style="font-size:0.7rem;color:#8A99AA;font-weight:600;">📌 Légende statuts :</span>
            <span class="badge-statut statut-en_attente">En attente</span>
            <span class="badge-statut statut-confirmee">Confirmée</span>
            <span class="badge-statut statut-en_preparation">Préparation</span>
            <span class="badge-statut statut-en_livraison">Livraison</span>
            <span class="badge-statut statut-livree">Livrée</span>
            <span class="badge-statut statut-annulee">Annulée</span>
            <span style="font-size:0.7rem;color:#8A99AA;margin-left:10px;">
                <i class="bi bi-info-circle" style="color:#C8922A;"></i>
                Le statut affiché est le plus avancé parmi les commandes du client
            </span>
        </div>
    </div>
</div>

</body>
</html>