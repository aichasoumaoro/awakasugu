<?php
// ============================================
// DASHBOARD - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$admin_info = getAdminInfo();
$admin_role = $admin_info['role'] ?? 'admin';
$admin_nom = $admin_info['nom'] ?? 'Awa Doumbia';
$admin_id = $admin_info['id'] ?? 0;

$page_title = 'Tableau de bord';

// ============================================
// COULEURS DES RÔLES (sans violet)
// ============================================
$role_labels = [
    'super_admin' => 'Super Administrateur',
    'directeur' => 'Directrice',
    'admin' => 'Administratrice',
    'admin2' => 'Agente'
];
$role_colors = [
    'super_admin' => '#0D0D0D',
    'directeur' => '#C8922A',
    'admin' => '#2980B9',
    'admin2' => '#7F8C8D'
];
$role_icons = [
    'super_admin' => 'bi-shield-fill-check',
    'directeur' => 'bi-crown-fill',
    'admin' => 'bi-person-badge-fill',
    'admin2' => 'bi-person-fill'
];

$role_label = $role_labels[$admin_role] ?? 'Administratrice';
$role_color = $role_colors[$admin_role] ?? '#C8922A';
$role_icon = $role_icons[$admin_role] ?? 'bi-person-badge-fill';

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Erreur BDD: " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

// ============================================
// FONCTIONS STATISTIQUES
// ============================================
function getTotalProduits($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM produits");
    return (int)$stmt->fetchColumn();
}
function getProduitsRupture($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM produits WHERE stock <= 0");
    return (int)$stmt->fetchColumn();
}
function getTotalClients($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM clients");
    return (int)$stmt->fetchColumn();
}
function getNouveauxClientsMois($pdo) {
    $stmt = $pdo->query("
        SELECT COUNT(*) as nb FROM clients 
        WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ");
    return (int)$stmt->fetchColumn();
}
function getNbCommandesConfirmees($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM commandes WHERE statut IN ('confirmee', 'livree', 'terminee')");
    return (int)$stmt->fetchColumn();
}
function getNbCommandesAttente($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM commandes WHERE statut = 'en_attente'");
    return (int)$stmt->fetchColumn();
}
function getNbVentesSurPlace($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM ventes_boutique WHERE statut = 'confirmee'");
    return (int)$stmt->fetchColumn();
}
function getNbPaiementsAttente($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM paiements WHERE statut = 'en_attente'");
    return (int)$stmt->fetchColumn();
}
function getCaCommandesTotal($pdo) {
    $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM commandes WHERE statut IN ('confirmee', 'livree', 'terminee')");
    return (float)$stmt->fetchColumn();
}
function getCaCommandesMois($pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total), 0) as total FROM commandes 
        WHERE statut IN ('confirmee', 'livree', 'terminee')
        AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ");
    return (float)$stmt->fetchColumn();
}
function getCaVentesSurPlaceTotal($pdo) {
    $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM ventes_boutique WHERE statut = 'confirmee'");
    return (float)$stmt->fetchColumn();
}
function getCaVentesSurPlaceMois($pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total), 0) as total FROM ventes_boutique 
        WHERE statut = 'confirmee'
        AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ");
    return (float)$stmt->fetchColumn();
}
function getAchatsMois($pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total_ligne), 0) as total FROM achats 
        WHERE MONTH(date_achat) = MONTH(CURDATE()) AND YEAR(date_achat) = YEAR(CURDATE())
    ");
    return (float)$stmt->fetchColumn();
}

function getStatsMarge($pdo) {
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM((prix - prix_achat) * stock), 0) as marge_totale_potentielle,
            COALESCE(SUM(prix * stock), 0) as valeur_stock_vente,
            COALESCE(SUM(prix_achat * stock), 0) as valeur_stock_achat,
            COALESCE(AVG(marge_pourcentage), 0) as marge_moyenne,
            COUNT(*) as nb_produits_avec_marge
        FROM produits 
        WHERE est_visible = 1 AND prix_achat > 0
    ");
    return $stmt->fetch();
}

function getAdminsConnectes($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT nom, email, role, last_activity 
            FROM admin 
            WHERE is_active = 1 
            AND last_activity IS NOT NULL 
            AND TIMESTAMPDIFF(MINUTE, last_activity, NOW()) < 5
        ");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

$total_produits = getTotalProduits($pdo);
$produits_rupture = getProduitsRupture($pdo);
$total_clients = getTotalClients($pdo);
$clients_mois = getNouveauxClientsMois($pdo);
$total_commandes = getNbCommandesConfirmees($pdo);
$commandes_attente = getNbCommandesAttente($pdo);
$ca_commandes_total = getCaCommandesTotal($pdo);
$ca_commandes_mois = getCaCommandesMois($pdo);
$total_ventes_boutique = getNbVentesSurPlace($pdo);
$ca_ventes_boutique_total = getCaVentesSurPlaceTotal($pdo);
$ca_ventes_boutique_mois = getCaVentesSurPlaceMois($pdo);
$nb_paiements_attente = getNbPaiementsAttente($pdo);
$achats_mois = getAchatsMois($pdo);

$ca_total_mois = $ca_commandes_mois + $ca_ventes_boutique_mois;
$ca_total_global = $ca_commandes_total + $ca_ventes_boutique_total;
$benefice_brut = $ca_total_mois - $achats_mois;

$stats_marge = getStatsMarge($pdo);
$marge_totale_potentielle = $stats_marge['marge_totale_potentielle'] ?? 0;
$valeur_stock_vente = $stats_marge['valeur_stock_vente'] ?? 0;
$valeur_stock_achat = $stats_marge['valeur_stock_achat'] ?? 0;
$marge_moyenne = $stats_marge['marge_moyenne'] ?? 0;
$nb_produits_avec_marge = $stats_marge['nb_produits_avec_marge'] ?? 0;

$admins_connectes = [];
if ($admin_role === 'super_admin' || $admin_role === 'directeur') {
    $admins_connectes = getAdminsConnectes($pdo);
}

$reservations_aujourdhui = $pdo->query("SELECT COUNT(*) FROM reservations WHERE date_reservation = CURDATE()")->fetchColumn();
$reservations_attente = $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn();

// ============================================
// 3 DERNIERS CLIENTS (au lieu de 5)
// ============================================
$nouveaux_clients = $pdo->query("
    SELECT id, nom, telephone, created_at 
    FROM clients 
    ORDER BY created_at DESC 
    LIMIT 3
")->fetchAll();

// ============================================
// 3 DERNIÈRES COMMANDES EN ATTENTE (au lieu de 20)
// ============================================
$commandes_en_attente = $pdo->query("
    SELECT c.*, cl.nom as client_nom, cl.telephone as client_telephone 
    FROM commandes c
    LEFT JOIN clients cl ON cl.id = c.client_id
    WHERE c.statut = 'en_attente'
    ORDER BY c.created_at DESC 
    LIMIT 3
")->fetchAll();

// Compteur total de commandes en attente (pour le badge)
$commandes_attente_total = getNbCommandesAttente($pdo);

$alertes_stock = $pdo->query("
    SELECT * FROM produits WHERE stock <= seuil_alerte AND stock > 0 ORDER BY stock ASC LIMIT 5
")->fetchAll();

$top_produits_marge = $pdo->query("
    SELECT 
        id, nom, 
        prix_achat, 
        prix, 
        (prix - prix_achat) as marge, 
        marge_pourcentage,
        stock,
        (prix - prix_achat) * stock as marge_potentielle
    FROM produits 
    WHERE est_visible = 1 AND prix_achat > 0 
    ORDER BY marge_potentielle DESC 
    LIMIT 5
")->fetchAll();

$ventes_mois = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) FROM commandes 
        WHERE statut IN ('confirmee', 'livree', 'terminee') AND MONTH(created_at) = ? AND YEAR(created_at) = YEAR(CURDATE())
    ");
    $stmt->execute([$i]);
    $ventes_mois[$i] = (float)$stmt->fetchColumn();
}

$ventes_boutique_mois_graph = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) FROM ventes_boutique 
        WHERE statut = 'confirmee' AND MONTH(created_at) = ? AND YEAR(created_at) = YEAR(CURDATE())
    ");
    $stmt->execute([$i]);
    $ventes_boutique_mois_graph[$i] = (float)$stmt->fetchColumn();
}

$top_categories = [];
try {
    $stmt = $pdo->query("
        SELECT cat.nom, COUNT(p.id) as nb
        FROM produits p
        JOIN categories cat ON cat.id = p.categorie_id
        GROUP BY cat.id, cat.nom
        ORDER BY nb DESC
        LIMIT 5
    ");
    $top_categories = $stmt->fetchAll();
} catch(PDOException $e) {
    $top_categories = [];
}
$max_cat_nb = 1;
foreach ($top_categories as $tc) {
    if ($tc['nb'] > $max_cat_nb) $max_cat_nb = $tc['nb'];
}

$maintenance_status = $pdo->query("SELECT site_actif, message_maintenance FROM maintenance_globale ORDER BY id DESC LIMIT 1")->fetch();
$site_en_maintenance = $maintenance_status && $maintenance_status['site_actif'] == 0;

$commandes_attente_count = getNbCommandesAttente($pdo);
$nb_ventes_boutique = getNbVentesSurPlace($pdo);
$nb_paiements_attente_count = getNbPaiementsAttente($pdo);

// ============================================
// SUPPRESSION D'UNE COMMANDE
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
            $commandes_attente_count = getNbCommandesAttente($pdo);
        } else {
            $delete_error = "Commande introuvable.";
        }
    } catch(PDOException $e) {
        $delete_error = "Erreur lors de la suppression.";
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* ============================================
   DASHBOARD - DESIGN NÉON OR
   ============================================ */
:root {
    --gold: #C8922A;
    --gold-light: #E8B55A;
    --gold-pale: #F5D689;
    --dark: #0D0D0D;
    --dark-soft: #1A1510;
    --text-primary: #1A2C3E;
    --text-secondary: #6B7A8D;
    --border-soft: #F0F2F5;
    --bg-light: #FAFBFC;
}

/* ===== WELCOME CARD NÉON ===== */
.welcome-card {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1510 100%);
    border: 1.5px solid var(--gold);
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
    box-shadow: 
        0 0 20px rgba(200,146,42,0.25),
        inset 0 0 20px rgba(200,146,42,0.05);
}
.welcome-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 60%;
    height: 200%;
    background: radial-gradient(circle, rgba(200,146,42,0.15) 0%, transparent 60%);
    pointer-events: none;
    animation: shine 8s ease-in-out infinite;
}
@keyframes shine {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(-20px, -20px); }
}
.welcome-card h2 {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    color: #fff;
    margin: 0 0 6px 0;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 2;
}
.welcome-card h2 span:first-of-type {
    color: var(--gold);
    text-shadow: 0 0 15px rgba(200,146,42,0.6);
}
.welcome-card p {
    color: rgba(255,255,255,0.55);
    font-size: 0.85rem;
    margin: 0;
    line-height: 1.6;
    position: relative;
    z-index: 2;
}
.welcome-card .welcome-icon {
    font-size: 3rem;
    color: var(--gold);
    opacity: 0.35;
    position: relative;
    z-index: 2;
    text-shadow: 0 0 30px rgba(200,146,42,0.7);
}
.welcome-card .role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    border-radius: 20px;
    color: #fff;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    border: 1px solid rgba(200,146,42,0.4);
    box-shadow: 0 0 15px rgba(200,146,42,0.3);
}

/* ===== STATS BOX NÉON ===== */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 22px;
}
.stat-box {
    background: #fff;
    border: 1.5px solid var(--border-soft);
    border-radius: 14px;
    padding: 18px 20px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}
.stat-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--gold), var(--gold-light));
    opacity: 0;
    transition: opacity 0.3s;
}
.stat-box:hover {
    transform: translateY(-3px);
    border-color: rgba(200,146,42,0.4);
    box-shadow: 
        0 12px 30px rgba(200,146,42,0.15),
        0 0 25px rgba(200,146,42,0.1);
}
.stat-box:hover::before {
    opacity: 1;
}
.stat-box-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.stat-box-label {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 1px;
}
.stat-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.stat-icon.ic-or { background: rgba(200,146,42,0.12); color: var(--gold); }
.stat-icon.ic-blue { background: rgba(41,128,185,0.12); color: #2980B9; }
.stat-icon.ic-green { background: rgba(39,174,96,0.12); color: #27AE60; }
.stat-icon.ic-red { background: rgba(231,76,60,0.12); color: #E74C3C; }
.stat-icon.ic-gold { background: rgba(232,181,90,0.15); color: #9A6E1A; }
.stat-icon.ic-purple { background: rgba(13,13,13,0.08); color: #0D0D0D; }
.stat-val {
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.1;
    margin-bottom: 4px;
}
.stat-lbl {
    font-size: 0.72rem;
    color: var(--text-secondary);
}

/* ===== ALERT BADGE ===== */
.alert-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    border: 1.5px solid transparent;
}
.alert-badge.has-orders {
    background: rgba(200,146,42,0.1);
    border-color: rgba(200,146,42,0.3);
    color: var(--gold);
    animation: pulseGold 2s infinite;
}
@keyframes pulseGold {
    0%, 100% { box-shadow: 0 0 0 0 rgba(200,146,42,0.4); }
    50% { box-shadow: 0 0 0 8px rgba(200,146,42,0); }
}
.alert-badge.has-orders:hover {
    background: rgba(200,146,42,0.2);
    transform: translateY(-1px);
}
.alert-badge.no-orders {
    background: rgba(39,174,96,0.08);
    border-color: rgba(39,174,96,0.2);
    color: #27AE60;
}
.alert-count {
    background: #E74C3C;
    color: #fff;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: 700;
}

/* ===== CARD WHITE NÉON ===== */
.card-white {
    background: #fff;
    border: 1.5px solid var(--border-soft);
    border-radius: 14px;
    margin-bottom: 22px;
    overflow: hidden;
    transition: all 0.3s;
}
.card-white:hover {
    border-color: rgba(200,146,42,0.2);
    box-shadow: 0 8px 25px rgba(200,146,42,0.06);
}
.card-white .card-header {
    padding: 16px 22px;
    border-bottom: 1px solid var(--border-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #FAFBFC, #F7F4EF);
}
.card-white .card-title {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.92rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.card-white .card-title i {
    color: var(--gold);
    font-size: 1rem;
}

/* ===== BOUTONS SMALL ===== */
.btn-small {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 14px;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    border: 1.5px solid transparent;
    cursor: pointer;
}
.btn-small.or {
    background: rgba(200,146,42,0.1);
    color: var(--gold);
    border-color: rgba(200,146,42,0.2);
}
.btn-small.or:hover {
    background: var(--gold);
    color: #fff;
    box-shadow: 0 4px 15px rgba(200,146,42,0.4);
}
.btn-small.green {
    background: rgba(39,174,96,0.1);
    color: #27AE60;
    border-color: rgba(39,174,96,0.2);
}
.btn-small.green:hover {
    background: #27AE60;
    color: #fff;
    box-shadow: 0 4px 15px rgba(39,174,96,0.4);
}
.btn-small.blue {
    background: rgba(41,128,185,0.1);
    color: #2980B9;
}
.btn-small.blue:hover {
    background: #2980B9;
    color: #fff;
}
.btn-small.red {
    background: rgba(231,76,60,0.1);
    color: #E74C3C;
}
.btn-small.red:hover {
    background: #E74C3C;
    color: #fff;
}

/* ===== DASHBOARD GRID ===== */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    margin-bottom: 22px;
}

/* ===== DONUT ===== */
.donut-card-body {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 20px;
}
.donut-wrap {
    position: relative;
    width: 160px;
    height: 160px;
    flex-shrink: 0;
}
.donut-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}
.donut-total {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary);
}
.donut-sub {
    font-size: 0.7rem;
    color: var(--text-secondary);
    letter-spacing: 1px;
    text-transform: uppercase;
}
.donut-legend {
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1;
}
.donut-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    color: var(--text-primary);
}
.donut-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 0 8px currentColor;
}
.donut-legend-pct {
    margin-left: auto;
    font-weight: 700;
    color: var(--text-primary);
}

/* ===== HBAR ===== */
.hbar-item {
    margin-bottom: 14px;
}
.hbar-item:last-child { margin-bottom: 0; }
.hbar-top {
    display: flex;
    justify-content: space-between;
    font-size: 0.82rem;
    margin-bottom: 6px;
    color: var(--text-primary);
}
.hbar-top span:last-child {
    color: var(--text-secondary);
    font-size: 0.72rem;
}
.hbar-track {
    height: 8px;
    background: var(--border-soft);
    border-radius: 4px;
    overflow: hidden;
    position: relative;
}
.hbar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--gold), var(--gold-light));
    border-radius: 4px;
    box-shadow: 0 0 8px rgba(200,146,42,0.5);
    transition: width 0.8s ease;
}

/* ===== TABLE NÉON ===== */
.table-container {
    overflow-x: auto;
}
.table-commandes {
    width: 100%;
    border-collapse: collapse;
}
.table-commandes thead th {
    background: linear-gradient(135deg, #0D0D0D, #1A1510);
    color: rgba(255,255,255,0.7);
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 12px 16px;
    text-align: left;
    white-space: nowrap;
}
.table-commandes thead th:first-child { border-radius: 0; }
.table-commandes tbody td {
    padding: 12px 16px;
    font-size: 0.82rem;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border-soft);
    vertical-align: middle;
}
.table-commandes tbody tr {
    transition: background 0.2s;
}
.table-commandes tbody tr:hover {
    background: rgba(200,146,42,0.03);
}
.table-commandes tbody tr:last-child td {
    border-bottom: none;
}

/* ===== BADGES STATUT ===== */
.badge-statut {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.statut-en_attente { background: rgba(232,181,90,0.15); color: #9A6E1A; }
.statut-confirmee { background: rgba(39,174,96,0.12); color: #1E8449; }
.statut-en_preparation { background: rgba(41,128,185,0.12); color: #21618C; }
.statut-en_livraison { background: rgba(200,146,42,0.12); color: var(--gold); }
.statut-livree { background: rgba(39,174,96,0.15); color: #1E8449; }
.statut-terminee { background: rgba(13,13,13,0.08); color: #0D0D0D; }
.statut-annulee { background: rgba(231,76,60,0.12); color: #C0392B; }

/* ===== TEXT UTILS ===== */
.fw-600 { font-weight: 600; }
.text-muted { color: var(--text-secondary); }
.text-gold { color: var(--gold); }

/* ===== ACTIONS ===== */
.actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--text-secondary);
}
.empty-state i {
    font-size: 2rem;
    opacity: 0.3;
    display: block;
    margin-bottom: 8px;
}
.empty-state p {
    font-size: 0.82rem;
    margin: 0;
}

/* ===== ADMIN CONNECTÉS BADGE ===== */
.admin-connected-bar {
    background: linear-gradient(135deg, rgba(39,174,96,0.08), rgba(39,174,96,0.03));
    border-left: 4px solid #27AE60;
    padding: 10px 18px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.admin-connected-bar .status-dot {
    width: 8px;
    height: 8px;
    background: #27AE60;
    border-radius: 50%;
    box-shadow: 0 0 10px #27AE60;
    animation: pulseDot 2s infinite;
}
@keyframes pulseDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.2); }
}
.admin-connected-bar .admin-tag {
    background: rgba(200,146,42,0.12);
    padding: 3px 14px;
    border-radius: 12px;
    font-size: 0.72rem;
    color: var(--text-primary);
    border: 1px solid rgba(200,146,42,0.2);
}

/* ===== ALERTES ===== */
.alert-success {
    background: linear-gradient(135deg, rgba(39,174,96,0.1), rgba(39,174,96,0.05));
    border-left: 4px solid #27AE60;
    padding: 12px 18px;
    border-radius: 10px;
    color: #155724;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.85rem;
}
.alert-danger {
    background: linear-gradient(135deg, rgba(231,76,60,0.1), rgba(231,76,60,0.05));
    border-left: 4px solid #E74C3C;
    padding: 12px 18px;
    border-radius: 10px;
    color: #721C24;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.85rem;
}

/* ===== BOUTON VOIR TOUT ===== */
.btn-see-all {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 600;
    text-decoration: none;
    background: rgba(200,146,42,0.1);
    color: var(--gold);
    border: 1.5px solid rgba(200,146,42,0.2);
    transition: all 0.3s;
}
.btn-see-all:hover {
    background: var(--gold);
    color: #fff;
    box-shadow: 0 4px 15px rgba(200,146,42,0.4);
    transform: translateX(3px);
}
.btn-see-all i {
    transition: transform 0.3s;
}
.btn-see-all:hover i {
    transform: translateX(3px);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1100px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .dashboard-grid { grid-template-columns: 1fr; }
}
@media (max-width: 700px) {
    .stats-row { grid-template-columns: 1fr; }
    .welcome-card { flex-direction: column; text-align: center; gap: 15px; }
    .welcome-card .welcome-icon { display: none; }
    .donut-card-body { flex-direction: column; }
}
</style>

<div class="main">

    <div class="topbar">
        <div>
            <div class="topbar-title">📊 Tableau de <span>bord</span></div>
            <div class="topbar-breadcrumb">Administration → Vue d'ensemble</div>
        </div>
        <div class="topbar-right">
            <a href="commandes.php?filtre=en_attente" class="alert-badge <?= $commandes_attente_count > 0 ? 'has-orders' : 'no-orders' ?>">
                <span class="alert-icon"><i class="bi bi-bell-fill"></i></span>
                <span class="alert-text"><?= $commandes_attente_count ?> commande(s) en attente</span>
                <?php if($commandes_attente_count > 0): ?><span class="alert-count">!</span><?php endif; ?>
            </a>
            <?php if($admin_role === 'super_admin' || $admin_role === 'directeur' || $admin_role === 'admin'): ?>
            <a href="point_de_vente.php" class="btn-admin btn-primary"><i class="bi bi-cash-stack"></i> Nouvelle vente</a>
            <?php endif; ?>
            <a href="maintenance.php" class="btn-admin btn-maintenance">
                <i class="bi bi-tools"></i> <?= $site_en_maintenance ? '🔴 Maintenance active' : '🟢 Site actif' ?>
            </a>
            <a href="../index.php" class="btn-admin btn-site"><i class="bi bi-eye"></i> Voir le site</a>
        </div>
    </div>

    <div class="content">

        <?php if($delete_message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($delete_message) ?></div>
        <?php endif; ?>
        <?php if($delete_error): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($delete_error) ?></div>
        <?php endif; ?>

        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
            <?php if(!empty($admins_connectes)): ?>
            <div class="admin-connected-bar">
                <span class="status-dot"></span>
                <span style="font-weight:600;color:#155724;font-size:0.85rem;">👤 Admin(s) connecté(s) :</span>
                <?php 
                $role_labels_short = ['super_admin' => '⭐ Super', 'directeur' => '👑 Dir.', 'admin' => '🛠️ Admin', 'admin2' => '📦 Agent'];
                foreach($admins_connectes as $admin_conn):
                ?>
                <span class="admin-tag">
                    <?= htmlspecialchars($admin_conn['nom']) ?>
                    <span style="color:var(--text-secondary);font-size:0.62rem;">(<?= $role_labels_short[$admin_conn['role']] ?? $admin_conn['role'] ?>)</span>
                </span>
                <?php endforeach; ?>
                <span style="font-size:0.65rem;color:var(--text-secondary);margin-left:auto;">
                    <i class="bi bi-clock"></i> Actif(s) depuis moins de 5 min
                </span>
            </div>
            <?php else: ?>
            <div style="background:rgba(232,181,90,0.08);padding:10px 18px;border-radius:8px;margin-bottom:15px;border-left:4px solid #E8B55A;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <i class="bi bi-person" style="color:#C8922A;font-size:0.9rem;"></i>
                <span style="font-size:0.8rem;color:#8A6020;">Aucun administrateur connecté actuellement</span>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="welcome-card">
            <div style="position:relative;z-index:2;">
                <h2>
                    Bonjour, <span><?= htmlspecialchars($admin_nom) ?></span>
                    <span class="role-badge" style="background: <?= $role_color ?>;">
                        <i class="bi <?= $role_icon ?>"></i> <?= $role_label ?>
                    </span>
                </h2>
                <p>
                    <?php if($admin_role === 'super_admin'): ?>
                        <i class="bi bi-shield-fill-check" style="color:var(--gold);"></i> Vous avez tous les droits sur la plateforme.
                    <?php elseif($admin_role === 'directeur'): ?>
                        <i class="bi bi-crown-fill" style="color:var(--gold);"></i> Vous gérez l'ensemble de la boutique.
                    <?php elseif($admin_role === 'admin'): ?>
                        <i class="bi bi-person-badge-fill" style="color:#7fc8ff;"></i> Vous gérez les commandes, les stocks et le restaurant.
                    <?php else: ?>
                        <i class="bi bi-truck" style="color:rgba(255,255,255,0.5);"></i> Vous gérez les livraisons et les commandes.
                    <?php endif; ?>
                    — Voici un résumé de votre activité sur Awa Ka Sugu.
                </p>
            </div>
            <div class="welcome-icon"><i class="bi <?= $role_icon ?>"></i></div>
        </div>

        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Produits</span>
                    <div class="stat-icon ic-or"><i class="bi bi-box-seam-fill"></i></div>
                </div>
                <div class="stat-val"><?= $total_produits ?></div>
                <div class="stat-lbl"><?= $produits_rupture ?> en rupture</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Clients</span>
                    <div class="stat-icon ic-blue"><i class="bi bi-people"></i></div>
                </div>
                <div class="stat-val"><?= $total_clients ?></div>
                <div class="stat-lbl">+<?= $clients_mois ?> ce mois</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">CA du mois</span>
                    <div class="stat-icon ic-green"><i class="bi bi-cash"></i></div>
                </div>
                <div class="stat-val"><?= number_format($ca_total_mois, 0, ',', ' ') ?> F</div>
                <div class="stat-lbl">Global (boutique + commandes)</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Bénéfice brut</span>
                    <div class="stat-icon ic-gold"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
                <div class="stat-val"><?= number_format($benefice_brut, 0, ',', ' ') ?> F</div>
                <div class="stat-lbl">CA - Achats du mois</div>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Commandes</span>
                    <div class="stat-icon ic-or"><i class="bi bi-cart"></i></div>
                </div>
                <div class="stat-val"><?= $total_commandes ?></div>
                <div class="stat-lbl">Validées</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Ventes</span>
                    <div class="stat-icon ic-or"><i class="bi bi-cash-stack"></i></div>
                </div>
                <div class="stat-val"><?= $nb_ventes_boutique ?></div>
                <div class="stat-lbl">Sur place</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Paiements</span>
                    <div class="stat-icon ic-gold"><i class="bi bi-credit-card"></i></div>
                </div>
                <div class="stat-val"><?= $nb_paiements_attente_count ?></div>
                <div class="stat-lbl">En attente</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Commandes</span>
                    <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                </div>
                <div class="stat-val"><?= $commandes_attente_count ?></div>
                <div class="stat-lbl">En attente</div>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-box" style="border-left: 4px solid #27AE60;">
                <div class="stat-box-top">
                    <span class="stat-box-label">Marge brute potentielle</span>
                    <div class="stat-icon ic-green"><i class="bi bi-cash-stack"></i></div>
                </div>
                <div class="stat-val" style="color:#27AE60;"><?= number_format($marge_totale_potentielle, 0, ',', ' ') ?> F</div>
                <div class="stat-lbl">Sur stock actuel</div>
            </div>
            <div class="stat-box" style="border-left: 4px solid #2980B9;">
                <div class="stat-box-top">
                    <span class="stat-box-label">Valeur stock (vente)</span>
                    <div class="stat-icon ic-blue"><i class="bi bi-cart"></i></div>
                </div>
                <div class="stat-val" style="color:#2980B9;"><?= number_format($valeur_stock_vente, 0, ',', ' ') ?> F</div>
                <div class="stat-lbl">Prix de vente</div>
            </div>
            <div class="stat-box" style="border-left: 4px solid #E67E22;">
                <div class="stat-box-top">
                    <span class="stat-box-label">Valeur stock (achat)</span>
                    <div class="stat-icon ic-gold"><i class="bi bi-bag"></i></div>
                </div>
                <div class="stat-val" style="color:#E67E22;"><?= number_format($valeur_stock_achat, 0, ',', ' ') ?> F</div>
                <div class="stat-lbl">Prix d'achat</div>
            </div>
            <div class="stat-box" style="border-left: 4px solid #0D0D0D;">
                <div class="stat-box-top">
                    <span class="stat-box-label">Marge moyenne</span>
                    <div class="stat-icon ic-purple"><i class="bi bi-percent"></i></div>
                </div>
                <div class="stat-val" style="color:#0D0D0D;"><?= number_format($marge_moyenne, 1) ?>%</div>
                <div class="stat-lbl"><?= $nb_produits_avec_marge ?> produits</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($admin_role === 'admin'): ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Produits</span>
                    <div class="stat-icon ic-or"><i class="bi bi-box-seam-fill"></i></div>
                </div>
                <div class="stat-val"><?= $total_produits ?></div>
                <div class="stat-lbl">En stock</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">À traiter</span>
                    <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                </div>
                <div class="stat-val"><?= $commandes_attente_count ?></div>
                <div class="stat-lbl">Commandes</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Traitées</span>
                    <div class="stat-icon ic-or"><i class="bi bi-cart"></i></div>
                </div>
                <div class="stat-val"><?= $total_commandes ?></div>
                <div class="stat-lbl">Commandes</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Clients</span>
                    <div class="stat-icon ic-blue"><i class="bi bi-people"></i></div>
                </div>
                <div class="stat-val"><?= $total_clients ?></div>
                <div class="stat-lbl">Inscrits</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($admin_role === 'admin2'): ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">À livrer</span>
                    <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                </div>
                <div class="stat-val"><?= $commandes_attente_count ?></div>
                <div class="stat-lbl">Commandes</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Livrées</span>
                    <div class="stat-icon ic-or"><i class="bi bi-check-circle"></i></div>
                </div>
                <div class="stat-val"><?= $total_commandes ?></div>
                <div class="stat-lbl">Commandes</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Livraisons</span>
                    <div class="stat-icon ic-blue"><i class="bi bi-truck"></i></div>
                </div>
                <div class="stat-val"><?= $nb_ventes_boutique ?></div>
                <div class="stat-lbl">Effectuées</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Aujourd'hui</span>
                    <div class="stat-icon ic-gold"><i class="bi bi-calendar"></i></div>
                </div>
                <div class="stat-val" style="font-size:1.1rem;"><?= date('d/m/Y') ?></div>
                <div class="stat-lbl">Date du jour</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(($admin_role === 'super_admin' || $admin_role === 'directeur') && !empty($top_produits_marge)): ?>
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-trophy"></i> Top 5 produits par marge potentielle</div>
                <a href="produits.php" class="btn-small or">Voir tout</a>
            </div>
            <div class="card-body" style="padding:0;">
                <?php foreach($top_produits_marge as $p): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--border-soft);">
                    <div>
                        <div style="font-weight:600;font-size:0.88rem;color:var(--text-primary);"><?= htmlspecialchars($p['nom']) ?></div>
                        <div style="font-size:0.72rem;color:var(--text-secondary);">
                            Achat: <?= number_format($p['prix_achat'], 0, ',', ' ') ?> F | 
                            Vente: <?= number_format($p['prix'], 0, ',', ' ') ?> F | 
                            Stock: <?= $p['stock'] ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:700;color:#27AE60;font-size:0.9rem;">
                            <?= number_format($p['marge_potentielle'], 0, ',', ' ') ?> F
                        </div>
                        <div style="font-size:0.68rem;color:<?= $p['marge_pourcentage'] > 50 ? '#27AE60' : ($p['marge_pourcentage'] > 20 ? '#E8B55A' : '#E74C3C') ?>;">
                            Marge: <?= $p['marge_pourcentage'] ?>%
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================
             3 DERNIÈRES COMMANDES EN ATTENTE
             ============================================ -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-clock-history"></i> Dernières commandes à traiter
                    <span style="font-size:0.65rem;font-weight:normal;background:rgba(200,146,42,0.1);color:var(--gold);padding:2px 10px;border-radius:12px;margin-left:8px;border:1px solid rgba(200,146,42,0.2);">
                        <?= $commandes_attente_total ?> en attente
                    </span>
                </div>
                <a href="commandes.php?filtre=en_attente" class="btn-see-all">
                    Voir tout <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-commandes">
                        <thead>
                            <tr>
                                <th>N° Commande</th><th>Client</th><th>Date</th><th>Total</th><th>Paiement</th><th>Statut</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($commandes_en_attente)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">
                                        <i class="bi bi-check-circle" style="font-size:1.4rem;display:block;margin-bottom:8px;color:#27AE60;"></i>
                                        Aucune commande en attente
                                    </td>
                                </tr>
                            <?php else: 
                                foreach($commandes_en_attente as $c):
                                    $statutLabels = [
                                        'en_attente' => 'En attente', 'confirmee' => 'Confirmée', 'en_preparation' => 'Préparation',
                                        'en_livraison' => 'Livraison', 'livree' => 'Livrée', 'terminee' => 'Terminée', 'annulee' => 'Annulée'
                                    ];
                                    $statutLabel = $statutLabels[$c['statut']] ?? $c['statut'];
                            ?>
                                <tr>
                                    <td class="fw-600"><?= htmlspecialchars($c['numero_commande'] ?? 'N/A') ?></td>
                                    <td>
                                        <?= htmlspecialchars($c['client_nom'] ?? 'Inconnu') ?>
                                        <div class="text-muted" style="font-size:0.65rem;"><?= htmlspecialchars($c['client_telephone'] ?? '') ?></div>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                                    <td class="text-gold fw-600"><?= number_format($c['total'], 0, ',', ' ') ?> F</td>
                                    <td>
                                        <span style="background:rgba(200,146,42,0.1);padding:2px 10px;border-radius:12px;font-size:0.6rem;color:var(--gold);border:1px solid rgba(200,146,42,0.2);">
                                            <?= htmlspecialchars($c['mode_paiement'] ?? 'Non défini') ?>
                                        </span>
                                    </td>
                                    <td><span class="badge-statut statut-<?= $c['statut'] ?>"><?= $statutLabel ?></span></td>
                                    <td>
                                        <div class="actions">
                                            <a href="commande_detail.php?id=<?= $c['id'] ?>" class="btn-small blue" title="Voir le détail"><i class="bi bi-eye"></i></a>
                                            <a href="commande_pdf.php?id=<?= $c['id'] ?>" class="btn-small or" title="PDF" target="_blank"><i class="bi bi-file-pdf"></i></a>
                                            <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
                                            <button onclick="confirmDelete('Supprimer cette commande ?')" class="btn-small red" title="Supprimer"><i class="bi bi-trash3"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
        <div class="dashboard-grid">
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-calendar-check"></i> Réservations</div>
                    <a href="reservations.php" class="btn-small or">Voir tout</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:16px;">
                        <div style="text-align:center;background:linear-gradient(135deg,rgba(41,128,185,0.08),rgba(41,128,185,0.03));border-radius:10px;padding:14px;border:1px solid rgba(41,128,185,0.15);">
                            <div style="font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;color:#2980B9;"><?= $reservations_aujourdhui ?></div>
                            <div style="font-size:0.62rem;color:var(--text-secondary);letter-spacing:0.5px;text-transform:uppercase;">Aujourd'hui</div>
                        </div>
                        <div style="text-align:center;background:linear-gradient(135deg,rgba(232,181,90,0.1),rgba(232,181,90,0.03));border-radius:10px;padding:14px;border:1px solid rgba(200,146,42,0.15);">
                            <div style="font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;color:#C8922A;"><?= $reservations_attente ?></div>
                            <div style="font-size:0.62rem;color:var(--text-secondary);letter-spacing:0.5px;text-transform:uppercase;">En attente</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================
                 3 DERNIERS CLIENTS
                 ============================================ -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-people"></i> Derniers clients</div>
                    <a href="clients.php" class="btn-see-all">
                        Voir tout <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if(empty($nouveaux_clients)): ?>
                        <div class="empty-state"><i class="bi bi-people"></i><p>Aucun client inscrit</p></div>
                    <?php else: ?>
                        <?php foreach($nouveaux_clients as $c): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 18px;border-bottom:1px solid var(--border-soft);">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#C8922A,#E8B55A);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:#fff;flex-shrink:0;box-shadow:0 4px 12px rgba(200,146,42,0.3);">
                                    <?= strtoupper(mb_substr($c['nom'] ?? 'C', 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:600;color:var(--text-primary);font-size:0.85rem;"><?= htmlspecialchars($c['nom'] ?? 'Inconnu') ?></div>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);">
                                        <i class="bi bi-telephone" style="font-size:0.55rem;"></i> <?= htmlspecialchars($c['telephone'] ?? '') ?>
                                        <span style="margin:0 4px;">•</span>
                                        <i class="bi bi-calendar3" style="font-size:0.55rem;"></i> <?= date('d/m/Y', strtotime($c['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.55rem;font-weight:600;background:rgba(200,146,42,0.1);color:var(--gold);border:1px solid rgba(200,146,42,0.2);">
                                <i class="bi bi-person-plus"></i> Client
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-pie-chart-fill"></i> Répartition du CA (ce mois)</div>
                </div>
                <div class="card-body donut-card-body">
                    <div class="donut-wrap">
                        <canvas id="donutCA"></canvas>
                        <div class="donut-center">
                            <div class="donut-total"><?= number_format($ca_total_mois, 0, ',', ' ') ?></div>
                            <div class="donut-sub">FCFA</div>
                        </div>
                    </div>
                    <div class="donut-legend">
                        <div class="donut-legend-item">
                            <span class="donut-legend-dot" style="background:#C8922A;color:#C8922A;"></span>
                            Commandes en ligne
                            <span class="donut-legend-pct"><?= $ca_total_mois > 0 ? round($ca_commandes_mois / $ca_total_mois * 100) : 0 ?>%</span>
                        </div>
                        <div class="donut-legend-item">
                            <span class="donut-legend-dot" style="background:#2980B9;color:#2980B9;"></span>
                            Ventes sur place
                            <span class="donut-legend-pct"><?= $ca_total_mois > 0 ? round($ca_ventes_boutique_mois / $ca_total_mois * 100) : 0 ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-tags-fill"></i> Top catégories</div>
                    <a href="produits.php" class="btn-small or">Voir tout</a>
                </div>
                <div class="card-body" style="padding:18px 22px;">
                    <?php if(empty($top_categories)): ?>
                        <div class="empty-state"><i class="bi bi-tags"></i><p>Aucune catégorie renseignée</p></div>
                    <?php else: ?>
                        <?php foreach($top_categories as $tc): 
                            $pct = round($tc['nb'] / $max_cat_nb * 100);
                        ?>
                        <div class="hbar-item">
                            <div class="hbar-top">
                                <span><?= htmlspecialchars($tc['nom']) ?></span>
                                <span><?= $tc['nb'] ?> produit<?= $tc['nb'] > 1 ? 's' : '' ?></span>
                            </div>
                            <div class="hbar-track"><div class="hbar-fill" style="width:<?= $pct ?>%;"></div></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(!empty($alertes_stock) && ($admin_role === 'super_admin' || $admin_role === 'directeur' || $admin_role === 'admin')): ?>
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-exclamation-triangle-fill" style="color:#E67E22;"></i> Stock faible</div>
                <a href="produits.php" class="btn-small or">Voir tout</a>
            </div>
            <div class="card-body" style="padding:14px;">
                <?php foreach($alertes_stock as $p): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:linear-gradient(135deg,rgba(230,126,34,0.08),rgba(230,126,34,0.02));border-left:3px solid #E67E22;border-radius:8px;margin-bottom:8px;gap:10px;flex-wrap:wrap;">
                    <span style="color:var(--text-primary);font-size:0.82rem;">
                        <strong><?= htmlspecialchars($p['nom']) ?></strong> — Stock restant : <strong style="color:#E67E22;"><?= $p['stock'] ?></strong>
                        <?php if($p['prix_achat'] > 0): ?>
                            <span style="font-size:0.7rem;color:var(--text-secondary);">| Achat: <?= number_format($p['prix_achat'], 0, ',', ' ') ?> F | Vente: <?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                        <?php endif; ?>
                    </span>
                    <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
                    <a href="achats.php" class="btn-small or">Réapprovisionner</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function confirmDelete(message) {
    return confirm(message || 'Êtes-vous sûr de vouloir effectuer cette action ?');
}

<?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
const gridColor = isDark ? 'rgba(255,255,255,0.06)' : '#F0F2F5';
const tickColor = isDark ? 'rgba(255,255,255,0.5)' : '#8A99AA';
const cardBg = isDark ? '#1B2028' : '#ffffff';

// ===== DONUT RÉPARTITION CA =====
const donutEl = document.getElementById('donutCA');
if (donutEl) {
    const donutCtx = donutEl.getContext('2d');
    new Chart(donutCtx, {
        type: 'doughnut',
        data: {
            labels: ['Commandes en ligne', 'Ventes sur place'],
            datasets: [{
                data: [<?= $ca_commandes_mois ?>, <?= $ca_ventes_boutique_mois ?>],
                backgroundColor: ['#C8922A', '#2980B9'],
                borderColor: cardBg,
                borderWidth: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0D0D0D',
                    titleColor: '#C8922A',
                    bodyColor: '#fff',
                    borderColor: '#C8922A',
                    borderWidth: 1,
                    callbacks: { 
                        label: ctx => ctx.label + ': ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA' 
                    }
                }
            }
        }
    });
}

window.addEventListener('resize', function() {
    if (window.Chart && Chart.instances) {
        Object.values(Chart.instances).forEach(c => c.resize());
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>