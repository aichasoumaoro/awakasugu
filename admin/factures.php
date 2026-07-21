<?php
// ============================================
// GESTION DES FACTURES - ADMIN
// DESIGN PREMIUM AVEC REGROUPEMENT PAR CLIENT
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
// METTRE À JOUR LE STATUT DES FACTURES SELON LES COMMANDES
// ============================================
// Une facture est considérée comme payée si la commande est livrée, terminée ou confirmée
$pdo->exec("
    UPDATE factures f
    JOIN commandes c ON c.id = f.commande_id
    SET f.statut_paiement = 'payee'
    WHERE c.statut IN ('livree', 'terminee', 'confirmee')
    AND f.statut_paiement != 'payee'
");

// Si la commande est annulée, la facture est annulée
$pdo->exec("
    UPDATE factures f
    JOIN commandes c ON c.id = f.commande_id
    SET f.statut_paiement = 'annulee'
    WHERE c.statut = 'annulee'
    AND f.statut_paiement != 'annulee'
");

// Vérifier les colonnes disponibles dans la table commandes
$columns = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);
$has_email = in_array('email', $columns);
$has_email_client = in_array('email_client', $columns);
$has_email_clients = in_array('email_clients', $columns);

// Déterminer le nom de la colonne email
$email_column = 'email';
if ($has_email_client) $email_column = 'email_client';
elseif ($has_email_clients) $email_column = 'email_clients';
elseif ($has_email) $email_column = 'email';

// ============================================
// VOIR TOUTES LES FACTURES D'UN CLIENT
// ============================================
$detail_client = null;
$factures_client = [];
if (isset($_GET['voir_client']) && isset($_GET['telephone'])) {
    $telephone = $_GET['telephone'];
    
    $stmt = $pdo->prepare("
        SELECT f.*, c.numero_commande, c.nom_client, c.total as commande_total, c.$email_column as email_client, c.telephone, c.statut as commande_statut
        FROM factures f 
        JOIN commandes c ON c.id = f.commande_id 
        WHERE c.telephone = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$telephone]);
    $factures_client = $stmt->fetchAll();
    
    if (!empty($factures_client)) {
        $detail_client = $factures_client[0];
    }
}

// ============================================
// RÉCUPÉRER TOUTES LES FACTURES
// ============================================
$factures = $pdo->query("
    SELECT f.*, c.numero_commande, c.nom_client, c.total as commande_total, c.$email_column as email_client, c.telephone, c.statut as commande_statut
    FROM factures f 
    JOIN commandes c ON c.id = f.commande_id 
    ORDER BY f.created_at DESC
")->fetchAll();

// ============================================
// GROUPER LES FACTURES PAR CLIENT (TÉLÉPHONE)
// ============================================
$clients = [];
foreach($factures as $f) {
    $telephone = $f['telephone'] ?? 'inconnu';
    if (!isset($clients[$telephone])) {
        $clients[$telephone] = [
            'telephone' => $telephone,
            'nom_client' => $f['nom_client'] ?? 'Inconnu',
            'factures' => [],
            'total_factures' => 0,
            'total_montant' => 0,
            'derniere_facture' => $f['created_at'],
            'email_client' => $f['email_client'] ?? ''
        ];
    }
    $clients[$telephone]['factures'][] = $f;
    $clients[$telephone]['total_factures']++;
    $clients[$telephone]['total_montant'] += $f['montant_total'];
    
    // Mettre à jour la date de la dernière facture
    if (strtotime($f['created_at']) > strtotime($clients[$telephone]['derniere_facture'])) {
        $clients[$telephone]['derniere_facture'] = $f['created_at'];
    }
}

// Trier les clients par date de dernière facture
usort($clients, function($a, $b) {
    return strtotime($b['derniere_facture']) - strtotime($a['derniere_facture']);
});

// Statistiques globales
$total_factures = count($factures);
$total_clients = count($clients);
$total_montant_global = 0;
$factures_payees = 0;
$factures_attente = 0;
$factures_annulees = 0;

foreach($factures as $f) {
    if($f['statut_paiement'] == 'payee') {
        $factures_payees++;
    } elseif($f['statut_paiement'] == 'annulee') {
        $factures_annulees++;
    } else {
        $factures_attente++;
    }
    $total_montant_global += $f['montant_total'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factures - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ========== RESET & BASE ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #F5F7FA;
            color: #1A2C3E;
            display: flex;
            min-height: 100vh;
        }

        /* ========== SIDEBAR ========== */
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

        /* ========== MAIN ========== */
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
            font-family: 'Inter', sans-serif;
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

        /* ========== STATS ========== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: #fff;
            border-radius: 16px;
            padding: 22px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid #E8ECF0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #C8922A, #E8B55A);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .stat-box:hover::before { opacity: 1; }
        .stat-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .ic-or { background: rgba(200,146,42,0.12); color: #C8922A; }
        .ic-green { background: rgba(27,122,74,0.12); color: #1A7A4A; }
        .ic-red { background: rgba(231,76,60,0.12); color: #E74C3C; }
        .ic-blue { background: rgba(41,128,185,0.12); color: #2980B9; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #0D0D0D;
            line-height: 1.1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 2px; }

        /* ========== TABLE ========== */
        .table-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #E8ECF0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid #F0F2F5;
            background: #FAFAFA;
        }
        .table-title {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 600;
            color: #0D0D0D;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-title i { color: #C8922A; }
        .table-count {
            background: #F0F2F5;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            color: #8A99AA;
        }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0D0D0D;
            color: #C8922A;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 14px 18px;
            text-align: left;
        }
        tbody td {
            padding: 14px 18px;
            font-size: 0.85rem;
            color: #333;
            border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        tbody tr:hover td { background: #FEFBF5; }
        tbody tr:last-child td { border-bottom: none; }

        .badge-nb-factures {
            display: inline-block;
            background: #C8922A;
            color: #fff;
            border-radius: 50%;
            padding: 2px 10px;
            font-size: 0.65rem;
            font-weight: 700;
        }

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

        /* ========== MODAL DÉTAIL FACTURE ========== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: #fff;
            border-radius: 24px;
            max-width: 820px;
            width: 95%;
            max-height: 92vh;
            overflow-y: auto;
            padding: 35px 40px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            animation: scaleIn 0.3s ease;
        }
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.92); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-close {
            float: right;
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: #999;
            transition: all 0.3s;
            line-height: 1;
        }
        .modal-close:hover { color: #E74C3C; transform: rotate(90deg); }

        .modal-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: #0D0D0D;
            margin-bottom: 8px;
        }
        .modal-title span { color: #C8922A; }

        .modal-subtitle {
            font-size: 0.9rem;
            color: #8A99AA;
            padding-bottom: 16px;
            border-bottom: 2px solid #F0F2F5;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px 24px;
        }
        .modal-subtitle i { color: #C8922A; margin-right: 6px; }

        /* ===== CARTE FACTURE (NOUVEAU DESIGN) ===== */
        .facture-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 16px;
            border: 1px solid #E8ECF0;
            transition: all 0.3s ease;
            position: relative;
        }
        .facture-card:hover {
            border-color: #C8922A;
            box-shadow: 0 4px 20px rgba(200,146,42,0.08);
        }

        .facture-card .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
        }

        .facture-card .ref {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: #C8922A;
        }

        .facture-card .badge-status {
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-payee {
            background: #D4EDDA;
            color: #1A7A4A;
        }
        .badge-attente {
            background: #FFF3CD;
            color: #856404;
        }
        .badge-annulee {
            background: #F8D7DA;
            color: #721C24;
        }

        .facture-card .date {
            font-size: 0.8rem;
            color: #8A99AA;
        }

        .facture-card .details {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #F0F2F5;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .facture-card .details .info {
            font-size: 0.9rem;
            color: #5A6B7A;
        }
        .facture-card .details .info strong {
            color: #0D0D0D;
        }

        .facture-card .details .montant {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: #C8922A;
        }

        .facture-card .actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .btn-pdf {
            background: #E74C3C;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-pdf:hover {
            background: #C0392B;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231,76,60,0.3);
        }

        .btn-generer {
            background: #C8922A;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-generer:hover {
            background: #9A6E1A;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(200,146,42,0.3);
        }

        .btn-retour {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 28px;
            background: #C8922A;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-retour:hover {
            background: #9A6E1A;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(200,146,42,0.3);
        }

        .status-update {
            background: #E8F5E9;
            border-left: 4px solid #27AE60;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1A5A2E;
            font-size: 0.9rem;
        }
        .status-update i {
            color: #27AE60;
            font-size: 1.2rem;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #8A99AA;
        }
        .empty-state i {
            font-size: 3rem;
            display: block;
            margin-bottom: 15px;
            color: #E0E6ED;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1000px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .topbar { padding: 12px 16px; flex-wrap: wrap; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .stat-box { padding: 16px; }
            .stat-val { font-size: 1.2rem; }
            .table-card { overflow-x: auto; }
            .modal-content { padding: 20px; }
            .facture-card { padding: 16px; }
            .modal-subtitle { flex-direction: column; gap: 4px; }
        }
        @media (max-width: 500px) {
            .stats-row { grid-template-columns: 1fr; }
            .table-header { flex-direction: column; align-items: flex-start; gap: 8px; }
            .facture-card .header { flex-direction: column; align-items: flex-start; }
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
        <a href="factures.php" class="nav-item active"><i class="bi bi-file-earmark-text"></i> Factures</a>
        <a href="paiements.php" class="nav-item"><i class="bi bi-credit-card"></i> Paiements</a>
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
            <div class="topbar-title">📄 Gestion des <span>Factures</span></div>
            <div class="topbar-breadcrumb">Administration → Factures</div>
        </div>
        <div>
            <a href="../index.php" class="btn-admin btn-site"><i class="bi bi-eye"></i> Voir le site</a>
        </div>
    </div>

    <div class="content">

        <!-- Status update info -->
        <div class="status-update">
            <i class="bi bi-arrow-repeat"></i>
            <span><strong>Mise à jour automatique :</strong> Les factures sont marquées comme <strong>payées</strong> lorsque la commande est <strong>livrée, terminée ou confirmée</strong>.</span>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-file-earmark-text"></i></div>
                <div>
                    <div class="stat-val"><?= $total_factures ?></div>
                    <div class="stat-lbl">Total factures</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $factures_payees ?></div>
                    <div class="stat-lbl">Payées</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-clock"></i></div>
                <div>
                    <div class="stat-val"><?= $factures_attente ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-cash-coin"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_montant_global, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Montant total</div>
                </div>
            </div>
        </div>

        <!-- ===== LISTE DES CLIENTS AVEC LEURS FACTURES ===== -->
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">
                    <i class="bi bi-people"></i> Clients
                </div>
                <div class="table-count"><?= $total_clients ?> client(s)</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Téléphone</th>
                        <th style="text-align:center;">Factures</th>
                        <th style="text-align:right;">Total</th>
                        <th>Dernière facture</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($clients)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <p>Aucune facture générée pour le moment</p>
                                    <span style="font-size:0.8rem;color:#bbb;">Les factures sont générées automatiquement après chaque commande</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($clients as $client): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($client['nom_client']) ?></strong></td>
                            <td><?= htmlspecialchars($client['telephone']) ?></td>
                            <td style="text-align:center;">
                                <span class="badge-nb-factures"><?= $client['total_factures'] ?></span>
                            </td>
                            <td style="text-align:right;font-weight:600;color:#C8922A;">
                                <?= number_format($client['total_montant'], 0, ',', ' ') ?> F
                            </td>
                            <td style="font-size:0.8rem;color:#8A99AA;">
                                <?= date('d/m/Y H:i', strtotime($client['derniere_facture'])) ?>
                            </td>
                            <td style="text-align:center;">
                                <a href="factures.php?voir_client=1&telephone=<?= urlencode($client['telephone']) ?>" 
                                   class="btn-detail-client" title="Voir toutes les factures du client">
                                    <i class="bi bi-eye"></i> Voir
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

<!-- ===== MODAL DÉTAIL CLIENT (NOUVEAU DESIGN) ===== -->
<?php if($detail_client && !empty($factures_client)): ?>
<div class="modal-overlay active" id="modalDetail" onclick="if(event.target===this) closeModal()">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
        
        <div class="modal-title">
            👤 Factures de <span><?= htmlspecialchars($detail_client['nom_client'] ?? 'Client') ?></span>
        </div>
        
        <div class="modal-subtitle">
            <span><i class="bi bi-telephone"></i> <?= htmlspecialchars($detail_client['telephone'] ?? 'Téléphone non renseigné') ?></span>
            <?php if(!empty($detail_client['email_client'])): ?>
            <span><i class="bi bi-envelope"></i> <?= htmlspecialchars($detail_client['email_client']) ?></span>
            <?php endif; ?>
            <span><i class="bi bi-file-earmark-text"></i> <?= count($factures_client) ?> facture(s)</span>
            <span><i class="bi bi-cash"></i> Total : <?= number_format(array_sum(array_column($factures_client, 'montant_total')), 0, ',', ' ') ?> F</span>
        </div>
        
        <?php foreach($factures_client as $f): ?>
        <div class="facture-card">
            <div class="header">
                <div>
                    <div class="ref"><?= htmlspecialchars($f['numero_facture']) ?></div>
                    <?php 
                    $badge_class = 'badge-attente';
                    $badge_icon = 'bi-clock';
                    $badge_text = 'En attente';
                    if($f['statut_paiement'] == 'payee') {
                        $badge_class = 'badge-payee';
                        $badge_icon = 'bi-check-circle-fill';
                        $badge_text = 'Payée';
                    } elseif($f['statut_paiement'] == 'annulee') {
                        $badge_class = 'badge-annulee';
                        $badge_icon = 'bi-x-circle-fill';
                        $badge_text = 'Annulée';
                    }
                    ?>
                    <span class="badge-status <?= $badge_class ?>">
                        <i class="bi <?= $badge_icon ?>"></i> <?= $badge_text ?>
                    </span>
                    <span style="margin-left:8px;font-size:0.7rem;color:#8A99AA;">
                        <i class="bi bi-box"></i> <?= $f['commande_statut'] ?? 'inconnu' ?>
                    </span>
                </div>
                <div class="date">
                    <i class="bi bi-calendar3"></i> <?= date('d/m/Y H:i', strtotime($f['created_at'])) ?>
                </div>
            </div>
            
            <div class="details">
                <div class="info">
                    <strong>Commande :</strong> <?= htmlspecialchars($f['numero_commande'] ?? 'N/A') ?>
                </div>
                <div class="montant">
                    <?= number_format($f['montant_total'], 0, ',', ' ') ?> FCFA
                </div>
            </div>
            
            <div class="actions">
                <?php 
                $pdf_existe = !empty($f['fichier_pdf']) && file_exists('../uploads/factures/'.$f['fichier_pdf']);
                ?>
                <?php if($pdf_existe): ?>
                <a href="../uploads/factures/<?= $f['fichier_pdf'] ?>" target="_blank" class="btn-pdf">
                    <i class="bi bi-file-pdf"></i> Télécharger PDF
                </a>
                <?php else: ?>
                <a href="generer_facture.php?id=<?= $f['commande_id'] ?>" class="btn-generer">
                    <i class="bi bi-plus-circle"></i> Générer la facture
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align:center;margin-top:24px;">
            <a href="factures.php" class="btn-retour">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function closeModal() {
    document.getElementById('modalDetail').classList.remove('active');
}

// Fermer la modale avec la touche Echap
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

</body>
</html>