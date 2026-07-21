<?php
// ============================================
// DASHBOARD - ADMIN AWA KA SUGU
// ============================================

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
// FONCTIONS STATISTIQUES
// ============================================

/**
 * Calcule le nombre total de produits
 */
function getTotalProduits($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM produits");
    return (int)$stmt->fetchColumn();
}

/**
 * Calcule le nombre de produits en rupture de stock
 */
function getProduitsRupture($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM produits WHERE stock <= 0");
    return (int)$stmt->fetchColumn();
}

/**
 * Calcule le nombre total de clients
 */
function getTotalClients($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM clients");
    return (int)$stmt->fetchColumn();
}

/**
 * Calcule le nombre de nouveaux clients du mois
 */
function getNouveauxClientsMois($pdo) {
    $stmt = $pdo->query("
        SELECT COUNT(*) as nb 
        FROM clients 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
    ");
    return (int)$stmt->fetchColumn();
}

/**
 * Calcule le nombre de commandes confirmées (livrées, confirmées, terminées)
 */
function getNbCommandesConfirmees($pdo) {
    $stmt = $pdo->query("
        SELECT COUNT(*) as nb 
        FROM commandes 
        WHERE statut IN ('confirmee', 'livree', 'terminee')
    ");
    return (int)$stmt->fetchColumn();
}

/**
 * Calcule le nombre de commandes en attente
 */
function getNbCommandesAttente($pdo) {
    $stmt = $pdo->query("
        SELECT COUNT(*) as nb 
        FROM commandes 
        WHERE statut = 'en_attente'
    ");
    return (int)$stmt->fetchColumn();
}

/**
 * Calcule le nombre total de ventes sur place (boutique)
 */
function getNbVentesSurPlace($pdo) {
    $stmt = $pdo->query("
        SELECT COUNT(*) as nb 
        FROM ventes_boutique 
        WHERE statut = 'confirmee'
    ");
    return (int)$stmt->fetchColumn();
}

/**
 * Calcule le nombre de paiements en attente
 */
function getNbPaiementsAttente($pdo) {
    $stmt = $pdo->query("
        SELECT COUNT(*) as nb 
        FROM paiements 
        WHERE statut = 'en_attente'
    ");
    return (int)$stmt->fetchColumn();
}

/**
 * Calcule le CA total des commandes confirmées (livrées, confirmées, terminées)
 */
function getCaCommandesTotal($pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total), 0) as total 
        FROM commandes 
        WHERE statut IN ('confirmee', 'livree', 'terminee')
    ");
    return (float)$stmt->fetchColumn();
}

/**
 * Calcule le CA des commandes du mois (livrées, confirmées, terminées)
 */
function getCaCommandesMois($pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total), 0) as total 
        FROM commandes 
        WHERE statut IN ('confirmee', 'livree', 'terminee')
        AND MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
    ");
    return (float)$stmt->fetchColumn();
}

/**
 * Calcule le CA total des ventes sur place
 */
function getCaVentesSurPlaceTotal($pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total), 0) as total 
        FROM ventes_boutique 
        WHERE statut = 'confirmee'
    ");
    return (float)$stmt->fetchColumn();
}

/**
 * Calcule le CA des ventes sur place du mois
 */
function getCaVentesSurPlaceMois($pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total), 0) as total 
        FROM ventes_boutique 
        WHERE statut = 'confirmee'
        AND MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
    ");
    return (float)$stmt->fetchColumn();
}

/**
 * Calcule le total des achats du mois
 */
function getAchatsMois($pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total_ligne), 0) as total 
        FROM achats 
        WHERE MONTH(date_achat) = MONTH(CURDATE()) 
        AND YEAR(date_achat) = YEAR(CURDATE())
    ");
    return (float)$stmt->fetchColumn();
}

// ============================================
// RÉCUPÉRATION DES STATISTIQUES
// ============================================

// Produits
$total_produits = getTotalProduits($pdo);
$produits_rupture = getProduitsRupture($pdo);

// Clients
$total_clients = getTotalClients($pdo);
$clients_mois = getNouveauxClientsMois($pdo);

// Commandes
$total_commandes = getNbCommandesConfirmees($pdo);
$commandes_attente = getNbCommandesAttente($pdo);
$ca_commandes_total = getCaCommandesTotal($pdo);
$ca_commandes_mois = getCaCommandesMois($pdo);

// Ventes sur place
$total_ventes_boutique = getNbVentesSurPlace($pdo);
$ca_ventes_boutique_total = getCaVentesSurPlaceTotal($pdo);
$ca_ventes_boutique_mois = getCaVentesSurPlaceMois($pdo);

// Paiements
$nb_paiements_attente = getNbPaiementsAttente($pdo);

// Achats
$achats_mois = getAchatsMois($pdo);

// Calcul des totaux
$ca_total_mois = $ca_commandes_mois + $ca_ventes_boutique_mois;
$ca_total_global = $ca_commandes_total + $ca_ventes_boutique_total;
$benefice_brut = $ca_total_mois - $achats_mois;

// Ventes boutique récentes
$ventes_boutique_recents = $pdo->query("
    SELECT * FROM ventes_boutique 
    WHERE statut = 'confirmee'
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

// Réservations
$reservations_aujourdhui = $pdo->query("
    SELECT COUNT(*) FROM reservations WHERE date_reservation = CURDATE()
")->fetchColumn();
$reservations_attente = $pdo->query("
    SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'
")->fetchColumn();

// Nouveaux clients (sans les points)
$nouveaux_clients = $pdo->query("
    SELECT id, nom, telephone, created_at FROM clients ORDER BY created_at DESC LIMIT 5
")->fetchAll();

// Commandes en attente avec infos client
$commandes_en_attente = $pdo->query("
    SELECT c.*, cl.nom as client_nom, cl.telephone as client_telephone 
    FROM commandes c
    LEFT JOIN clients cl ON cl.id = c.client_id
    WHERE c.statut = 'en_attente'
    ORDER BY c.created_at DESC 
    LIMIT 20
")->fetchAll();

// Alertes stock
$alertes_stock = $pdo->query("
    SELECT * FROM produits 
    WHERE stock <= seuil_alerte AND stock > 0 
    ORDER BY stock ASC 
    LIMIT 5
")->fetchAll();

// Ventes mensuelles pour le graphique
$ventes_mois = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) 
        FROM commandes 
        WHERE statut IN ('confirmee', 'livree', 'terminee')
        AND MONTH(created_at) = ? 
        AND YEAR(created_at) = YEAR(CURDATE())
    ");
    $stmt->execute([$i]);
    $ventes_mois[$i] = (float)$stmt->fetchColumn();
}

// Ventes boutique mensuelles pour le graphique
$ventes_boutique_mois_graph = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) 
        FROM ventes_boutique 
        WHERE statut = 'confirmee'
        AND MONTH(created_at) = ? 
        AND YEAR(created_at) = YEAR(CURDATE())
    ");
    $stmt->execute([$i]);
    $ventes_boutique_mois_graph[$i] = (float)$stmt->fetchColumn();
}

// Maintenance
$maintenance_status = $pdo->query("
    SELECT site_actif, message_maintenance 
    FROM maintenance_globale 
    ORDER BY id DESC LIMIT 1
")->fetch();
$site_en_maintenance = $maintenance_status && $maintenance_status['site_actif'] == 0;

// Message de paiement
$message_paiement = $_SESSION['message_paiement'] ?? '';
unset($_SESSION['message_paiement']);

// Statistiques pour affichage
$commandes_attente_count = getNbCommandesAttente($pdo);
$nb_ventes_boutique = getNbVentesSurPlace($pdo);
$nb_paiements_attente_count = getNbPaiementsAttente($pdo);

// ============================================
// GESTION DE LA SUPPRESSION D'UNE COMMANDE
// ============================================
$delete_message = '';
$delete_error = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $commande_id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("SELECT statut FROM commandes WHERE id = ?");
        $stmt->execute([$commande_id]);
        $commande = $stmt->fetch();
        
        if ($commande) {
            $stmt = $pdo->prepare("DELETE FROM details_commande WHERE commande_id = ?");
            $stmt->execute([$commande_id]);
            
            $stmt = $pdo->prepare("DELETE FROM commandes WHERE id = ?");
            $stmt->execute([$commande_id]);
            
            $delete_message = "La commande a été supprimée avec succès.";
            // Rafraîchir le compteur
            $commandes_attente_count = getNbCommandesAttente($pdo);
        } else {
            $delete_error = "Commande introuvable.";
        }
    } catch(PDOException $e) {
        $delete_error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}
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
        .topbar-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        
        /* Alerte commandes en attente */
        .alert-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #E74C3C;
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            animation: pulse 2s infinite;
            text-decoration: none;
            transition: all 0.3s;
        }
        .alert-badge:hover {
            background: #C0392B;
            transform: scale(1.05);
            color: #fff;
        }
        .alert-badge i { font-size: 0.9rem; }
        .alert-badge .count {
            background: rgba(255,255,255,0.25);
            padding: 0 8px;
            border-radius: 12px;
            font-size: 0.7rem;
        }
        .alert-badge.hidden { display: none; }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
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
        .btn-primary { background: #C8922A; color: #fff; border-color: #C8922A; }
        .btn-primary:hover { background: #9A6E1A; color: #fff; }
        .btn-maintenance {
            background: <?= $site_en_maintenance ? '#E74C3C' : '#2ECC71' ?>;
            color: #fff;
            border-color: <?= $site_en_maintenance ? '#E74C3C' : '#2ECC71' ?>;
        }
        .btn-maintenance:hover {
            background: <?= $site_en_maintenance ? '#C0392B' : '#27AE60' ?>;
            color: #fff;
        }
        .btn-site {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #fff !important;
            border-color: #C8922A !important;
            box-shadow: 0 4px 14px rgba(200,146,42,0.25);
        }
        .btn-site:hover {
            background: linear-gradient(135deg, #9A6E1A, #C8922A) !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(200,146,42,0.35);
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
        .alert-danger {
            background: #F8D7DA;
            border-left: 4px solid #E74C3C;
            color: #721C24;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

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
        }
        .welcome-card h2 { font-size: 1.5rem; font-weight: 700; color: #1A2C3E; margin-bottom: 6px; }
        .welcome-card h2 span { color: #C8922A; }
        .welcome-card p { font-size: 0.85rem; color: #8A99AA; }
        .welcome-icon { font-size: 2.5rem; opacity: 0.7; }

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
        .ic-gold { background: rgba(200,146,42,0.15); color: #C8922A; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem; font-weight: 700;
            color: #1A2C3E; line-height: 1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 2px; }

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

        .btn-small {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.2s;
            font-family: 'Jost', sans-serif;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-small.or { background: rgba(200,146,42,0.12); color: #C8922A; }
        .btn-small.or:hover { background: #C8922A; color: #fff; }
        .btn-small.green { background: #1A7A4A; color: #fff; }
        .btn-small.green:hover { background: #145E38; }
        .btn-small.blue { background: rgba(41,128,185,0.12); color: #2980B9; }
        .btn-small.blue:hover { background: #2980B9; color: #fff; }
        .btn-small.red { background: rgba(231,76,60,0.12); color: #E74C3C; }
        .btn-small.red:hover { background: #E74C3C; color: #fff; }
        .btn-small.gray { background: #F0F2F5; color: #5A6B7A; }
        .btn-small.gray:hover { background: #E0E6ED; }

        .badge-statut {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .statut-en_attente { background: #FEF6E6; color: #E67E22; }
        .statut-confirmee { background: #E8F5E9; color: #2E7D32; }
        .statut-en_preparation { background: #FFF3E0; color: #E67E22; }
        .statut-en_livraison { background: #E3F2FD; color: #1565C0; }
        .statut-livree { background: #E8F5E9; color: #1A7A4A; }
        .statut-terminee { background: #E8F5E9; color: #1A7A4A; }
        .statut-annulee { background: #FBE9E7; color: #C62828; }

        .empty-state { text-align: center; padding: 25px; color: #8A99AA; }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 5px; color: #D5D5D5; }
        .empty-state p { margin: 0; font-size: 0.85rem; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        .vente-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            border-bottom: 1px solid #F0F2F5;
        }
        .vente-item:last-child { border-bottom: none; }
        .vente-numero { font-weight: 600; font-size: 0.85rem; }
        .vente-client { font-size: 0.7rem; color: #8A99AA; }

        /* Tableau des commandes */
        .table-container {
            overflow-x: auto;
        }
        .table-commandes {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .table-commandes th {
            text-align: left;
            padding: 12px 16px;
            background: #F8F9FA;
            color: #5A6B7A;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #E8ECF0;
        }
        .table-commandes td {
            padding: 12px 16px;
            border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        .table-commandes tr:hover {
            background: #F8F9FA;
        }
        .table-commandes .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .table-commandes .actions .btn-small {
            font-size: 0.65rem;
            padding: 3px 10px;
        }
        .text-muted { color: #8A99AA; }
        .fw-600 { font-weight: 600; }
        .text-gold { color: #C8922A; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        .modal-box h3 {
            font-family: 'Playfair Display', serif;
            margin-bottom: 10px;
        }
        .modal-box p {
            color: #5A6B7A;
            margin-bottom: 20px;
        }
        .modal-box .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .modal-box .btn-small {
            padding: 8px 24px;
            font-size: 0.8rem;
        }

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
            .table-commandes { font-size: 0.75rem; }
            .table-commandes th, .table-commandes td { padding: 8px 10px; }
        }
    </style>
</head>
<body>

<!-- ===== MODAL DE CONFIRMATION SUPPRESSION ===== -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <h3>⚠️ Confirmer la suppression</h3>
        <p>Êtes-vous sûr de vouloir supprimer cette commande ? Cette action est irréversible.</p>
        <div class="modal-actions">
            <a href="#" class="btn-small gray" onclick="closeDeleteModal()">Annuler</a>
            <a href="#" class="btn-small red" id="confirmDeleteBtn">Supprimer</a>
        </div>
    </div>
</div>

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
            <div class="topbar-title">📊 Tableau de <span>bord</span></div>
            <div class="topbar-breadcrumb">Administration → Vue d'ensemble</div>
        </div>
        <div class="topbar-right">
            <!-- Alerte commandes en attente -->
            <a href="commandes.php?filtre=en_attente" class="alert-badge <?= $commandes_attente_count == 0 ? 'hidden' : '' ?>">
                <i class="bi bi-bell-fill"></i>
                <?= $commandes_attente_count ?> commande(s) en attente
                <span class="count">!</span>
            </a>
            
            <a href="maintenance.php" class="btn-admin btn-maintenance">
                <i class="bi bi-tools"></i>
                <?= $site_en_maintenance ? '🔴 Maintenance active' : '🟢 Site actif' ?>
            </a>
            <a href="point_de_vente.php" class="btn-admin btn-primary">
                <i class="bi bi-cash-stack"></i> Nouvelle vente
            </a>
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <div class="content">

        <?php if($message_paiement): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $message_paiement ?></div>
        <?php endif; ?>
        
        <?php if($delete_message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $delete_message ?></div>
        <?php endif; ?>
        
        <?php if($delete_error): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= $delete_error ?></div>
        <?php endif; ?>

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
                    <div class="stat-lbl">Commandes validées</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="stat-val"><?= $nb_ventes_boutique ?></div>
                    <div class="stat-lbl">Ventes sur place</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-gold"><i class="bi bi-credit-card"></i></div>
                <div>
                    <div class="stat-val"><?= $nb_paiements_attente_count ?></div>
                    <div class="stat-lbl">Paiements en attente</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-val"><?= $commandes_attente_count ?></div>
                    <div class="stat-lbl">Commandes en attente</div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- COMMANDES EN ATTENTE -->
        <!-- ============================================ -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-clock-history"></i> Commandes à traiter
                    <span style="font-size:0.7rem;font-weight:normal;background:#F0F2F5;padding:2px 10px;border-radius:20px;margin-left:8px;">
                        <?= count($commandes_en_attente) ?> en attente
                    </span>
                </div>
                <a href="commandes.php?filtre=en_attente" class="btn-small green">
                    <i class="bi bi-check2-circle"></i> Gérer les commandes
                </a>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-commandes">
                        <thead>
                            <tr>
                                <th>N° Commande</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Paiement</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($commandes_en_attente)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;padding:30px;color:#8A99AA;">
                                        <i class="bi bi-check-circle" style="font-size:1.5rem;display:block;margin-bottom:5px;color:#27AE60;"></i>
                                        ✅ Aucune commande en attente
                                    </td>
                                </tr>
                            <?php else: 
                                foreach($commandes_en_attente as $c):
                                    $statutLabels = [
                                        'en_attente' => 'En attente',
                                        'confirmee' => 'Confirmée',
                                        'en_preparation' => 'Préparation',
                                        'en_livraison' => 'Livraison',
                                        'livree' => 'Livrée',
                                        'terminee' => 'Terminée',
                                        'annulee' => 'Annulée'
                                    ];
                                    $statutLabel = $statutLabels[$c['statut']] ?? $c['statut'];
                            ?>
                                <tr>
                                    <td class="fw-600"><?= htmlspecialchars($c['numero_commande'] ?? 'N/A') ?></td>
                                    <td>
                                        <?= htmlspecialchars($c['client_nom'] ?? 'Inconnu') ?>
                                        <div class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($c['client_telephone'] ?? '') ?></div>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                                    <td class="text-gold fw-600"><?= number_format($c['total'], 0, ',', ' ') ?> F</td>
                                    <td>
                                        <span style="background:rgba(200,146,42,0.1);padding:2px 10px;border-radius:20px;font-size:0.65rem;color:#C8922A;">
                                            <?= htmlspecialchars($c['mode_paiement'] ?? 'Non défini') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-statut statut-<?= $c['statut'] ?>">
                                            <?= $statutLabel ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="commande_detail.php?id=<?= $c['id'] ?>" class="btn-small blue" title="Voir le détail">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="commande_pdf.php?id=<?= $c['id'] ?>" class="btn-small or" title="Télécharger PDF" target="_blank">
                                                <i class="bi bi-file-pdf"></i>
                                            </a>
                                            <button onclick="confirmDelete(<?= $c['id'] ?>)" class="btn-small red" title="Supprimer">
                                                <i class="bi bi-trash3"></i>
                                            </button>
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

        <!-- ============================================ -->
        <!-- GRILLE 2 COLONNES : RÉSERVATIONS / NOUVEAUX CLIENTS -->
        <!-- ============================================ -->
        <div class="dashboard-grid">
            
            <!-- 1. Réservations -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-calendar-check"></i> Réservations</div>
                    <a href="reservations.php" class="btn-small or">Voir tout</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:15px;">
                        <div style="text-align:center;background:#FEFBF5;border-radius:10px;padding:15px;border:1px solid rgba(200,146,42,0.08);">
                            <div style="font-size:1.5rem;font-weight:700;color:#2980B9;"><?= $reservations_aujourdhui ?></div>
                            <div style="font-size:0.65rem;color:#8A99AA;">Aujourd'hui</div>
                        </div>
                        <div style="text-align:center;background:#FEFBF5;border-radius:10px;padding:15px;border:1px solid rgba(200,146,42,0.08);">
                            <div style="font-size:1.5rem;font-weight:700;color:#E67E22;"><?= $reservations_attente ?></div>
                            <div style="font-size:0.65rem;color:#8A99AA;">En attente</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Nouveaux clients -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-people"></i> Nouveaux clients</div>
                    <a href="clients.php" class="btn-small or">Voir tout</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if(empty($nouveaux_clients)): ?>
                        <div class="empty-state">
                            <i class="bi bi-people"></i>
                            <p>Aucun nouveau client</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($nouveaux_clients as $c): ?>
                        <div class="vente-item">
                            <div>
                                <div class="vente-numero"><?= htmlspecialchars($c['nom'] ?? 'Inconnu') ?></div>
                                <div class="vente-client"><?= htmlspecialchars($c['telephone'] ?? '') ?> • <?= date('d/m/Y', strtotime($c['created_at'])) ?></div>
                            </div>
                            <div>
                                <span style="color:#8A99AA;font-size:0.7rem;">Client</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- GRAPHIQUE -->
        <!-- ============================================ -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-graph-up"></i> Ventes mensuelles
                    <span style="font-size:0.7rem;font-weight:normal;background:#F0F2F5;padding:2px 8px;border-radius:20px;margin-left:8px;"><?= date('Y') ?></span>
                </div>
            </div>
            <div class="card-body">
                <canvas id="ventesChart" height="200"></canvas>
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
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:#FEF6E6;border-left:3px solid #E67E22;border-radius:8px;margin-bottom:8px;">
                    <span style="color:#5A6B7A;font-size:0.85rem;"><strong><?= htmlspecialchars($p['nom']) ?></strong> — Stock restant : <strong><?= $p['stock'] ?></strong></span>
                    <a href="produit_modifier.php?id=<?= $p['id'] ?>" class="btn-small or">Réapprovisionner</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /main -->

<script>
// ============================================
// GESTION DE LA SUPPRESSION DE COMMANDE
// ============================================
let deleteId = null;

function confirmDelete(id) {
    deleteId = id;
    document.getElementById('deleteModal').classList.add('active');
    document.getElementById('confirmDeleteBtn').href = '?delete=' + id;
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    deleteId = null;
}

// Fermer la modale en cliquant à l'extérieur
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// ============================================
// GRAPHIQUE
// ============================================
const ctx = document.getElementById('ventesChart').getContext('2d');

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
                label: 'Ventes sur place',
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