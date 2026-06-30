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

// Fonction pour extraire l'ID YouTube
function getYoutubeId($url) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/', $url, $matches);
    return $matches[1] ?? '';
}

// ============================================
// STATISTIQUES GÉNÉRALES
// ============================================

// Produits
$total_produits = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$produits_rupture = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock <= 0")->fetchColumn();
$valeur_stock = $pdo->query("SELECT COALESCE(SUM(prix * stock), 0) FROM produits")->fetchColumn();

// Clients
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$clients_mois = $pdo->query("SELECT COUNT(*) FROM clients WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();

// ============================================
// COMMANDES EN LIGNE
// ============================================
$total_commandes = $pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
$commandes_mois = $pdo->query("SELECT COUNT(*) FROM commandes WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
$commandes_attente = $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'en_attente'")->fetchColumn();
$ca_commandes_mois = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();

// ============================================
// VENTES EN BOUTIQUE (POS)
// ============================================
$total_ventes_boutique = $pdo->query("SELECT COUNT(*) FROM ventes_boutique")->fetchColumn();
$ventes_boutique_mois = $pdo->query("SELECT COUNT(*) FROM ventes_boutique WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
$ca_ventes_boutique_mois = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM ventes_boutique WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
$ventes_boutique_recents = $pdo->query("SELECT * FROM ventes_boutique ORDER BY created_at DESC LIMIT 5")->fetchAll();

// ============================================
// ACHATS (Approvisionnement)
// ============================================
$total_achats = $pdo->query("SELECT COUNT(*) FROM achats")->fetchColumn();
$achats_mois = $pdo->query("SELECT COALESCE(SUM(total_ligne), 0) FROM achats WHERE MONTH(date_achat) = MONTH(CURDATE()) AND YEAR(date_achat) = YEAR(CURDATE())")->fetchColumn();
$achats_recents = $pdo->query("SELECT * FROM achats ORDER BY date_achat DESC LIMIT 5")->fetchAll();

// ============================================
// STATISTIQUES GLOBALES
// ============================================
$ca_total_mois = $ca_commandes_mois + $ca_ventes_boutique_mois;
$benefice_brut = $ca_total_mois - $achats_mois;

// Ventes mensuelles (Commandes en ligne)
$ventes_mois = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE MONTH(created_at) = ? AND YEAR(created_at) = YEAR(CURDATE())");
    $stmt->execute([$i]);
    $ventes_mois[$i] = $stmt->fetchColumn();
}

// Ventes boutique mensuelles
$ventes_boutique_mois_graph = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM ventes_boutique WHERE MONTH(created_at) = ? AND YEAR(created_at) = YEAR(CURDATE())");
    $stmt->execute([$i]);
    $ventes_boutique_mois_graph[$i] = $stmt->fetchColumn();
}

// Top ventes (produits)
$best_sellers = $pdo->query("
    SELECT p.nom, SUM(dc.quantite) as vendu
    FROM details_commande dc
    JOIN produits p ON p.id = dc.produit_id
    GROUP BY dc.produit_id
    ORDER BY vendu DESC
    LIMIT 5
")->fetchAll();

// Dernières commandes
$dernieres_commandes = $pdo->query("SELECT * FROM commandes ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Alertes stock
$alertes_stock = $pdo->query("SELECT * FROM produits WHERE stock <= seuil_alerte AND stock > 0 ORDER BY stock ASC LIMIT 5")->fetchAll();

// ============================================
// STATUT MAINTENANCE
// ============================================
$maintenance_status = $pdo->query("SELECT site_actif, message_maintenance FROM maintenance_globale ORDER BY id DESC LIMIT 1")->fetch();
$site_en_maintenance = $maintenance_status && $maintenance_status['site_actif'] == 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Awa Ka Sugu Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Jost', sans-serif; background: #F5F7FA; color: #1A2C3E; display: flex; min-height: 100vh; }

        /* ===== SIDEBAR ===== */
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

        /* ===== MAIN ===== */
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
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
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
        .btn-primary {
            background: #C8922A;
            color: #fff;
            border-color: #C8922A;
        }
        .btn-primary:hover { background: #9A6E1A; color: #fff; border-color: #9A6E1A; }
        
        .btn-maintenance {
            background: <?= $site_en_maintenance ? '#E74C3C' : '#2ECC71' ?>;
            color: #fff;
            border-color: <?= $site_en_maintenance ? '#E74C3C' : '#2ECC71' ?>;
        }
        .btn-maintenance:hover {
            background: <?= $site_en_maintenance ? '#C0392B' : '#27AE60' ?>;
            color: #fff;
            border-color: <?= $site_en_maintenance ? '#C0392B' : '#27AE60' ?>;
        }

        .content { padding: 28px 32px; flex: 1; }

        /* Welcome */
        .welcome-card {
            background: linear-gradient(135deg, #fff 0%, #FEF9F0 100%);
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            border: 1px solid rgba(200,146,42,0.15);
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .welcome-card h2 { font-size: 1.5rem; font-weight: 700; color: #1A2C3E; margin-bottom: 6px; }
        .welcome-card h2 span { color: #C8922A; }
        .welcome-card p { font-size: 0.85rem; color: #8A99AA; }
        .welcome-icon { font-size: 2.5rem; opacity: 0.7; }

        /* Stats */
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
            transition: all 0.22s;
        }
        .stat-box:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
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
        .ic-red { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem; font-weight: 700;
            color: #1A2C3E; line-height: 1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 2px; }

        /* Cards */
        .card-white {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #E8ECF0;
            margin-bottom: 28px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            border-bottom: 1px solid #F0F2F5;
        }
        .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 600;
            color: #1A2C3E;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-title i { color: #C8922A; font-size: 1.1rem; }
        .card-body { padding: 18px 24px; }

        /* Alertes stock */
        .alert-stock {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            background: #FEF6E6;
            border-left: 3px solid #E67E22;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .alert-stock span { color: #5A6B7A; font-size: 0.85rem; }
        .btn-small {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.2s;
            font-family: 'Jost', sans-serif;
            font-weight: 600;
        }
        .btn-small.or { background: rgba(200,146,42,0.12); color: #C8922A; }
        .btn-small.or:hover { background: #C8922A; color: #fff; }
        .btn-small.green { background: #1A7A4A; color: #fff; }
        .btn-small.green:hover { background: #145E38; }
        .btn-small.blue { background: rgba(41,128,185,0.12); color: #2980B9; }
        .btn-small.blue:hover { background: #2980B9; color: #fff; }

        .badge-statut {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .statut-en_attente { background: #FEF6E6; color: #E67E22; }
        .statut-confirmee { background: #E8F5E9; color: #2E7D32; }
        .statut-livree { background: #E8F5E9; color: #1A7A4A; }

        .btn-detail {
            background: rgba(200,146,42,0.08);
            padding: 5px 10px;
            border-radius: 6px;
            color: #C8922A;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-detail:hover { background: #C8922A; color: #fff; }

        .empty-state { text-align: center; padding: 30px; color: #8A99AA; }
        .empty-state i { font-size: 2rem; margin-bottom: 10px; display: block; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        /* Vente item */
        .vente-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #F0F2F5;
        }
        .vente-item:last-child { border-bottom: none; }
        .vente-info .vente-numero { font-weight: 600; font-size: 0.85rem; }
        .vente-info .vente-client { font-size: 0.7rem; color: #8A99AA; }
        .vente-total { color: #C8922A; font-weight: 700; font-size: 0.9rem; }

        .achat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #F0F2F5;
        }
        .achat-item:last-child { border-bottom: none; }
        .achat-produit { font-weight: 600; font-size: 0.85rem; }
        .achat-fournisseur { font-size: 0.65rem; color: #8A99AA; }

        @media (max-width: 1100px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .dashboard-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .dashboard-grid { grid-template-columns: 1fr; }
            .topbar-right { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
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
        <a href="dashboard.php" class="nav-item active"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
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

        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<!-- ===== MAIN ===== -->
<div class="main">

    <div class="topbar">
        <div>
            <div class="topbar-title">📊 Tableau de <span>bord</span></div>
            <div class="topbar-breadcrumb">Administration → Vue d'ensemble</div>
        </div>
        <div class="topbar-right">
            <!-- Bouton Maintenance (à droite) -->
            <a href="maintenance.php" class="btn-admin btn-maintenance">
                <i class="bi bi-tools"></i>
                <?= $site_en_maintenance ? '🔴 Maintenance active' : '🟢 Site actif' ?>
            </a>
            <a href="point_de_vente.php" class="btn-admin btn-primary">
                <i class="bi bi-cash-stack"></i> Nouvelle vente
            </a>
            <a href="../index.php" target="_blank" class="btn-admin">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <div class="content">

        <!-- Welcome -->
        <div class="welcome-card">
            <div>
                <h2>✨ Bonjour, <span><?= htmlspecialchars($_SESSION['admin_nom'] ?? 'Awa Doumbia') ?></span> !</h2>
                <p>Voici un résumé de votre activité sur Awa Ka Sugu.</p>
            </div>
            <div class="welcome-icon"><i class="bi bi-stars"></i></div>
        </div>

        <!-- ============================================ -->
        <!-- STATISTIQUES GÉNÉRALES -->
        <!-- ============================================ -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-box-seam-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $total_produits ?></div>
                    <div class="stat-lbl">Produits (<?= $produits_rupture ?> en rupture)</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-people"></i></div>
                <div>
                    <div class="stat-val"><?= $total_clients ?></div>
                    <div class="stat-lbl">Clients (+<?= $clients_mois ?> ce mois)</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-cash"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($ca_total_mois, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">CA du mois (global)</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-purple"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($benefice_brut, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Bénéfice brut</div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- STATISTIQUES DÉTAILLÉES -->
        <!-- ============================================ -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-cart"></i></div>
                <div>
                    <div class="stat-val"><?= $total_commandes ?></div>
                    <div class="stat-lbl">Commandes en ligne</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="stat-val"><?= $total_ventes_boutique ?></div>
                    <div class="stat-lbl">Ventes en boutique</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-receipt"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($ca_commandes_mois, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">CA Commandes en ligne</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-shop"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($ca_ventes_boutique_mois, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">CA Ventes boutique</div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- GRAPHIQUE ET TOP VENTES -->
        <!-- ============================================ -->
        <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">

            <!-- Graphique combiné -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-graph-up"></i> Ventes mensuelles
                        <span style="font-size:0.7rem;font-weight:normal;background:#F0F2F5;padding:2px 8px;border-radius:20px;margin-left:8px;"><?= date('Y') ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="ventesChart" height="250"></canvas>
                </div>
            </div>

            <!-- Top ventes produits -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-trophy-fill"></i> Meilleures ventes</div>
                </div>
                <div class="card-body">
                    <?php if(empty($best_sellers)): ?>
                        <div class="empty-state"><i class="bi bi-trophy"></i><p>Aucune vente</p></div>
                    <?php else: ?>
                        <?php foreach($best_sellers as $p): ?>
                        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
                            <span style="font-size:0.85rem;"><?= htmlspecialchars($p['nom']) ?></span>
                            <span style="color:#C8922A;font-weight:600;"><?= $p['vendu'] ?> vendus</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ALERTES STOCK -->
        <!-- ============================================ -->
        <?php if(!empty($alertes_stock)): ?>
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-exclamation-triangle-fill"></i> Stock faible</div>
                <a href="produits.php" class="btn-small or">Voir tout</a>
            </div>
            <div class="card-body">
                <?php foreach($alertes_stock as $p): ?>
                <div class="alert-stock">
                    <span><strong><?= htmlspecialchars($p['nom']) ?></strong> — Stock restant : <strong><?= $p['stock'] ?></strong></span>
                    <a href="produit_modifier.php?id=<?= $p['id'] ?>" class="btn-small or">Réapprovisionner</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- COMMANDES EN ATTENTE -->
        <!-- ============================================ -->
        <?php if($commandes_attente > 0): ?>
        <div style="background:#E8F5E9;border-left:3px solid #1A7A4A;padding:14px 20px;border-radius:12px;margin-bottom:28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <p style="color:#2E7D32;font-size:0.85rem;margin:0;">
                <i class="bi bi-clock-history"></i> <strong><?= $commandes_attente ?> commande(s)</strong> en attente de traitement.
            </p>
            <a href="commandes.php?filtre=en_attente" class="btn-small green">Traiter →</a>
        </div>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- 3 COLONNES : Commandes / Ventes boutique / Achats -->
        <!-- ============================================ -->
        <div class="dashboard-grid">

            <!-- Dernières commandes en ligne -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-receipt"></i> Commandes en ligne</div>
                    <a href="commandes.php" class="btn-small or">Voir tout</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if(empty($dernieres_commandes)): ?>
                        <div class="empty-state"><i class="bi bi-inbox"></i><p>Aucune commande</p></div>
                    <?php else: ?>
                        <?php foreach($dernieres_commandes as $c): ?>
                        <div class="vente-item" style="padding:10px 16px;">
                            <div class="vente-info">
                                <div class="vente-numero"><?= htmlspecialchars($c['numero_commande']) ?></div>
                                <div class="vente-client"><?= htmlspecialchars($c['nom_client']) ?> • <?= date('d/m/Y', strtotime($c['created_at'])) ?></div>
                            </div>
                            <div>
                                <span class="badge-statut statut-<?= $c['statut'] ?>" style="font-size:0.65rem;">
                                    <?= ['en_attente'=>'En attente','confirmee'=>'Confirmée','en_preparation'=>'Préparation','en_livraison'=>'Livraison','livree'=>'Livrée','annulee'=>'Annulée'][$c['statut']] ?? $c['statut'] ?>
                                </span>
                                <span style="color:#C8922A;font-weight:600;margin-left:10px;"><?= number_format($c['total'], 0, ',', ' ') ?> F</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dernières ventes en boutique -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-shop"></i> Ventes boutique</div>
                    <a href="point_de_vente.php" class="btn-small blue">Nouvelle</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if(empty($ventes_boutique_recents)): ?>
                        <div class="empty-state"><i class="bi bi-cart-x"></i><p>Aucune vente en boutique</p></div>
                    <?php else: ?>
                        <?php foreach($ventes_boutique_recents as $v): ?>
                        <div class="vente-item" style="padding:10px 16px;">
                            <div class="vente-info">
                                <div class="vente-numero"><?= htmlspecialchars($v['numero_vente']) ?></div>
                                <div class="vente-client"><?= htmlspecialchars($v['client_nom']) ?> • <?= date('d/m/Y', strtotime($v['created_at'])) ?></div>
                            </div>
                            <div>
                                <span style="background:rgba(200,146,42,0.1);padding:2px 10px;border-radius:20px;font-size:0.65rem;color:#C8922A;"><?= $v['mode_paiement'] ?></span>
                                <span style="color:#C8922A;font-weight:700;margin-left:10px;"><?= number_format($v['total'], 0, ',', ' ') ?> F</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Derniers achats (approvisionnement) -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-box-seam"></i> Approvisionnements</div>
                    <a href="achats.php" class="btn-small or">Voir tout</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if(empty($achats_recents)): ?>
                        <div class="empty-state"><i class="bi bi-box"></i><p>Aucun achat</p></div>
                    <?php else: ?>
                        <?php foreach($achats_recents as $a): ?>
                        <div class="achat-item" style="padding:10px 16px;">
                            <div>
                                <div class="achat-produit"><?= htmlspecialchars($a['nom_produit']) ?></div>
                                <div class="achat-fournisseur"><?= htmlspecialchars($a['fournisseur_nom'] ?? 'Inconnu') ?> • <?= $a['quantite'] ?> unités</div>
                            </div>
                            <div style="color:#2980B9;font-weight:600;"><?= number_format($a['total_ligne'], 0, ',', ' ') ?> F</div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('ventesChart').getContext('2d');

// Données combinées
const labels = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
const commandesData = <?= json_encode(array_values($ventes_mois)) ?>;
const boutiqueData = <?= json_encode(array_values($ventes_boutique_mois_graph)) ?>;

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Commandes en ligne',
                data: commandesData,
                backgroundColor: 'rgba(200,146,42,0.7)',
                borderColor: '#C8922A',
                borderWidth: 1,
                borderRadius: 4,
            },
            {
                label: 'Ventes boutique',
                data: boutiqueData,
                backgroundColor: 'rgba(41,128,185,0.7)',
                borderColor: '#2980B9',
                borderWidth: 1,
                borderRadius: 4,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    font: { family: 'Jost', size: 11 },
                    boxWidth: 12,
                    padding: 15
                }
            },
            tooltip: {
                backgroundColor: '#1A2C3E',
                titleColor: '#C8922A',
                bodyColor: '#fff',
                callbacks: {
                    label: ctx => ctx.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA'
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#E8ECF0' },
                ticks: {
                    color: '#8A99AA',
                    callback: v => v >= 1000000 ? (v/1000000)+'M' : v >= 1000 ? (v/1000)+'k' : v
                }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#8A99AA' }
            }
        }
    }
});
</script>
</body>
</html>