<?php
// ============================================
// GESTION DES COMMANDES REPAS & GÂTEAUX - ADMIN
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
// CHANGER LE STATUT D'UNE COMMANDE REPAS
// ============================================
if (isset($_GET['statut']) && isset($_GET['id']) && isset($_GET['type'])) {
    $id = (int)$_GET['id'];
    $nouveau_statut = $_GET['statut'];
    $type = $_GET['type'];
    $statuts_valides = ['en_attente', 'confirmee', 'en_preparation', 'terminee', 'annulee'];
    
    if (in_array($nouveau_statut, $statuts_valides)) {
        // Récupérer l'ancien statut et les infos
        if ($type == 'repas') {
            $stmt = $pdo->prepare("SELECT * FROM commandes_repas WHERE id = ?");
            $stmt->execute([$id]);
            $commande = $stmt->fetch();
            $ancien_statut = $commande['statut'] ?? 'en_attente';
            $client_id = $commande['client_id'] ?? null;
            $telephone = $commande['telephone'] ?? null;
            $total = $commande['total'] ?? 0;
            
            $stmt = $pdo->prepare("UPDATE commandes_repas SET statut = ? WHERE id = ?");
            $stmt->execute([$nouveau_statut, $id]);
            
        } elseif ($type == 'gateau') {
            $stmt = $pdo->prepare("SELECT * FROM commandes_gateaux WHERE id = ?");
            $stmt->execute([$id]);
            $commande = $stmt->fetch();
            $ancien_statut = $commande['statut'] ?? 'en_attente';
            $client_id = $commande['client_id'] ?? null;
            $telephone = $commande['telephone'] ?? null;
            $total = $commande['prix'] ?? 0;
            
            $stmt = $pdo->prepare("UPDATE commandes_gateaux SET statut = ? WHERE id = ?");
            $stmt->execute([$nouveau_statut, $id]);
        }
        
        // Gestion des points
        $statuts_avec_points = ['terminee'];
        $statuts_sans_points = ['annulee'];
        
        // Trouver le client_id via téléphone si nécessaire
        if (!$client_id && !empty($telephone)) {
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE telephone = ?");
            $stmt->execute([$telephone]);
            $client = $stmt->fetch();
            if ($client) {
                $client_id = $client['id'];
                if ($type == 'repas') {
                    $stmt = $pdo->prepare("UPDATE commandes_repas SET client_id = ? WHERE id = ?");
                    $stmt->execute([$client_id, $id]);
                } elseif ($type == 'gateau') {
                    $stmt = $pdo->prepare("UPDATE commandes_gateaux SET client_id = ? WHERE id = ?");
                    $stmt->execute([$client_id, $id]);
                }
            }
        }
        
        $message = 'Statut mis à jour !';
        
        // Ajouter des points si terminée
        if ($client_id && in_array($nouveau_statut, $statuts_avec_points) && !in_array($ancien_statut, $statuts_avec_points)) {
            $points = floor($total / 1000);
            if ($points > 0) {
                if (ajouterPoints($pdo, $client_id, $id, $points, "Commande #{$id} - " . $type . " - terminée")) {
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
        header('Location: commandes_repas.php');
        exit;
    }
}

// ============================================
// SUPPRIMER UNE COMMANDE REPAS
// ============================================
if (isset($_GET['supprimer']) && isset($_GET['type'])) {
    $id = (int)$_GET['supprimer'];
    $type = $_GET['type'];
    
    if ($type == 'repas') {
        $pdo->prepare("DELETE FROM commandes_repas WHERE id = ?")->execute([$id]);
    } elseif ($type == 'gateau') {
        $pdo->prepare("DELETE FROM commandes_gateaux WHERE id = ?")->execute([$id]);
    }
    $_SESSION['message_commande'] = 'Commande supprimée !';
    header('Location: commandes_repas.php');
    exit;
}

// ============================================
// VOIR LE DÉTAIL D'UN CLIENT (TOUTES SES COMMANDES)
// ============================================
$detail_client = null;
$commandes_client = [];
if (isset($_GET['voir_client']) && isset($_GET['telephone'])) {
    $telephone = $_GET['telephone'];
    
    $stmt = $pdo->prepare("SELECT *, 'repas' as type FROM commandes_repas WHERE telephone = ? ORDER BY created_at DESC");
    $stmt->execute([$telephone]);
    $commandes_repas_client = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT *, 'gateau' as type FROM commandes_gateaux WHERE telephone = ? ORDER BY created_at DESC");
    $stmt->execute([$telephone]);
    $commandes_gateaux_client = $stmt->fetchAll();
    
    $commandes_client = array_merge($commandes_repas_client, $commandes_gateaux_client);
    usort($commandes_client, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    if (!empty($commandes_client)) {
        $detail_client = $commandes_client[0];
    }
}

// ============================================
// VOIR LE DÉTAIL D'UNE COMMANDE UNIQUE
// ============================================
$detail_commande = null;
$detail_type = null;
if (isset($_GET['voir']) && isset($_GET['type']) && !isset($_GET['voir_client'])) {
    $id = (int)$_GET['voir'];
    $type = $_GET['type'];
    
    if ($type == 'repas') {
        $stmt = $pdo->prepare("SELECT *, 'repas' as type FROM commandes_repas WHERE id = ?");
        $stmt->execute([$id]);
        $detail_commande = $stmt->fetch();
        $detail_type = 'repas';
    } elseif ($type == 'gateau') {
        $stmt = $pdo->prepare("SELECT *, 'gateau' as type FROM commandes_gateaux WHERE id = ?");
        $stmt->execute([$id]);
        $detail_commande = $stmt->fetch();
        $detail_type = 'gateau';
    }
}

// ============================================
// RÉCUPÉRER TOUTES LES COMMANDES
// ============================================

$commandes_repas = $pdo->query("SELECT *, 'repas' as type FROM commandes_repas ORDER BY created_at DESC")->fetchAll();
$commandes_gateaux = $pdo->query("SELECT *, 'gateau' as type FROM commandes_gateaux ORDER BY created_at DESC")->fetchAll();

$commandes = array_merge($commandes_repas, $commandes_gateaux);
usort($commandes, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// ============================================
// GROUPER LES COMMANDES PAR CLIENT
// ============================================
$clients = [];
foreach($commandes as $c) {
    $telephone = $c['telephone'] ?? 'inconnu';
    if (!isset($clients[$telephone])) {
        $clients[$telephone] = [
            'telephone' => $telephone,
            'nom_client' => $c['nom_client'] ?? 'Inconnu',
            'commandes' => [],
            'total_commandes' => 0,
            'total_depense' => 0,
            'derniere_commande' => $c['created_at']
        ];
    }
    $clients[$telephone]['commandes'][] = $c;
    $clients[$telephone]['total_commandes']++;
    $clients[$telephone]['total_depense'] += ($c['type'] == 'gateau') ? ($c['prix'] ?? 0) : ($c['total'] ?? 0);
    
    if (strtotime($c['created_at']) > strtotime($clients[$telephone]['derniere_commande'])) {
        $clients[$telephone]['derniere_commande'] = $c['created_at'];
    }
}

usort($clients, function($a, $b) {
    return strtotime($b['derniere_commande']) - strtotime($a['derniere_commande']);
});

// Statistiques
$total_commandes = count($commandes);
$commandes_attente = 0;
$ca_repas_mois = 0;

foreach($commandes as $c) {
    if($c['statut'] == 'en_attente') $commandes_attente++;
    if(date('m', strtotime($c['created_at'])) == date('m')) {
        $ca_repas_mois += ($c['type'] == 'gateau') ? ($c['prix'] ?? 0) : ($c['total'] ?? 0);
    }
}

$message = $_SESSION['message_commande'] ?? '';
unset($_SESSION['message_commande']);

$statuts = [
    'en_attente' => ['label' => 'En attente', 'class' => 'statut-en_attente', 'icon' => 'bi-clock-history'],
    'confirmee' => ['label' => 'Confirmée', 'class' => 'statut-confirmee', 'icon' => 'bi-check-circle'],
    'en_preparation' => ['label' => 'Préparation', 'class' => 'statut-en_preparation', 'icon' => 'bi-gear'],
    'terminee' => ['label' => 'Terminée', 'class' => 'statut-terminee', 'icon' => 'bi-check-circle-fill'],
    'annulee' => ['label' => 'Annulée', 'class' => 'statut-annulee', 'icon' => 'bi-x-circle'],
];

$modes_paiement = [
    'livraison' => 'Paiement à la livraison',
    'orange_money' => 'Orange Money',
    'wave' => 'Wave',
    'moov_money' => 'Moov Money'
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
        /* Vos styles existants (gardez-les) */
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
            grid-template-columns: repeat(3, 1fr);
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
        .ic-red { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
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
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 12px 16px;
            text-align: left;
        }
        tbody td {
            padding: 12px 16px;
            font-size: 0.85rem;
            color: #333;
            border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        tbody tr:hover td { background: #FEFBF5; }

        .badge-statut {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .statut-en_attente { background: #FEF6E6; color: #E67E22; }
        .statut-confirmee { background: #E8F5E9; color: #2E7D32; }
        .statut-en_preparation { background: #E3F2FD; color: #1565C0; }
        .statut-terminee { background: #D4EDDA; color: #1A7A4A; }
        .statut-annulee { background: #F8D7DA; color: #721C24; }

        .badge-type {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .badge-repas { background: rgba(200,146,42,0.15); color: #C8922A; }
        .badge-gateau { background: rgba(142,68,173,0.15); color: #8E44AD; }

        .btn-act {
            padding: 4px 10px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-delete { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .btn-delete:hover { background: #E74C3C; color: #fff; }
        .btn-view { background: rgba(41,128,185,0.1); color: #2980B9; }
        .btn-view:hover { background: #2980B9; color: #fff; }
        .btn-client { background: rgba(200,146,42,0.1); color: #C8922A; }
        .btn-client:hover { background: #C8922A; color: #fff; }

        .btn-statut {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.55rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            border: 1px solid transparent;
            cursor: pointer;
        }
        .btn-statut:hover { transform: scale(0.95); opacity: 0.8; }
        .btn-statut-attente { background: #FEF6E6; color: #E67E22; }
        .btn-statut-confirmee { background: #E8F5E9; color: #2E7D32; }
        .btn-statut-preparation { background: #E3F2FD; color: #1565C0; }
        .btn-statut-terminee { background: #D4EDDA; color: #1A7A4A; }
        .btn-statut-annulee { background: #F8D7DA; color: #721C24; }

        .empty-state { text-align: center; padding: 40px; color: #999; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 10px; }

        .badge-nb-commandes {
            display: inline-block;
            background: #C8922A;
            color: #fff;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 0.6rem;
            font-weight: 700;
            margin-left: 5px;
        }

        /* Modal Détail */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: #fff;
            border-radius: 20px;
            max-width: 850px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: scaleIn 0.3s ease;
        }
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
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
        }
        .modal-close:hover { color: #333; transform: rotate(90deg); }
        .modal-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            color: #0D0D0D;
            margin-bottom: 20px;
        }
        .modal-title span { color: #C8922A; }
        .modal-subtitle {
            font-size: 0.9rem;
            color: #8A99AA;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #F0F2F5;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #F0F2F5;
        }
        .detail-row .label { color: #8A99AA; font-size: 0.8rem; }
        .detail-row .value { font-weight: 600; color: #1A2C3E; font-size: 0.9rem; }

        .commande-client-item {
            background: #F8F9FA;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 10px;
            border-left: 3px solid #C8922A;
        }
        .commande-client-item .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .commande-client-item .header .numero {
            font-weight: 700;
            color: #C8922A;
        }
        .commande-client-item .header .date {
            font-size: 0.8rem;
            color: #8A99AA;
        }
        .commande-client-item .details {
            margin-top: 5px;
            font-size: 0.85rem;
            color: #5A6B7A;
        }
        .statut-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 8px;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .table-card { overflow-x: auto; }
            .modal-content { padding: 20px; }
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
        <a href="commandes_repas.php" class="nav-item active"><i class="bi bi-bag-check"></i> Commandes repas</a>
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

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">🛵 Commandes <span>Repas & Gâteaux</span></div>
            <div class="topbar-breadcrumb">Restaurant → Commandes</div>
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
                <div class="stat-icon ic-or"><i class="bi bi-bag-check"></i></div>
                <div>
                    <div class="stat-val"><?= $total_commandes ?></div>
                    <div class="stat-lbl">Total commandes</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-val"><?= $commandes_attente ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-cash"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($ca_repas_mois, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">CA du mois</div>
                </div>
            </div>
        </div>

        <!-- LISTE DES CLIENTS -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">👥 Clients</div>
                <div><?= count($clients) ?> client(s)</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Téléphone</th>
                        <th>Nb commandes</th>
                        <th>Total dépensé</th>
                        <th>Dernière commande</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($clients)): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-people"></i><p>Aucun client</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach($clients as $client): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($client['nom_client']) ?></strong></td>
                            <td><?= htmlspecialchars($client['telephone']) ?></td>
                            <td>
                                <span class="badge-nb-commandes"><?= $client['total_commandes'] ?></span>
                            </td>
                            <td style="color:#C8922A;font-weight:600;">
                                <?= number_format($client['total_depense'], 0, ',', ' ') ?> F
                            </td>
                            <td style="font-size:0.8rem;color:#8A99AA;">
                                <?= date('d/m/Y H:i', strtotime($client['derniere_commande'])) ?>
                            </td>
                            <td style="text-align:center;">
                                <a href="commandes_repas.php?voir_client=1&telephone=<?= urlencode($client['telephone']) ?>" 
                                   class="btn-act btn-client" title="Voir toutes les commandes du client">
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

<!-- MODAL DÉTAIL CLIENT -->
<?php if($detail_client && !empty($commandes_client)): ?>
<div class="modal-overlay active" id="modalDetail" onclick="if(event.target===this) closeModal()">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
        <div class="modal-title">
            👤 Commandes de <span><?= htmlspecialchars($detail_client['nom_client'] ?? 'Client') ?></span>
        </div>
        <div class="modal-subtitle">
            <i class="bi bi-telephone"></i> <?= htmlspecialchars($detail_client['telephone'] ?? 'Téléphone non renseigné') ?>
            <span style="margin-left:20px;">
                <i class="bi bi-bag"></i> <?= count($commandes_client) ?> commande(s)
            </span>
            <span style="margin-left:20px;">
                <i class="bi bi-cash"></i> Total : <?= number_format(array_sum(array_map(function($c) {
                    return ($c['type'] == 'gateau') ? ($c['prix'] ?? 0) : ($c['total'] ?? 0);
                }, $commandes_client)), 0, ',', ' ') ?> F
            </span>
        </div>
        
        <?php foreach($commandes_client as $c): 
            $is_gateau = ($c['type'] == 'gateau');
            $nom_produit = $is_gateau ? ($c['nom_gateau'] ?? 'Gâteau') : ($c['nom_plat'] ?? 'Repas');
            $total = $is_gateau ? ($c['prix'] ?? 0) : ($c['total'] ?? 0);
            $current_statut = $c['statut'] ?? 'en_attente';
        ?>
        <div class="commande-client-item">
            <div class="header">
                <div>
                    <span class="numero">#<?= $c['id'] ?></span>
                    <span class="badge-type <?= $is_gateau ? 'badge-gateau' : 'badge-repas' ?>">
                        <?= $is_gateau ? '🎂 Gâteau' : '🍽️ Repas' ?>
                    </span>
                    <span class="badge-statut statut-<?= $current_statut ?>">
                        <i class="bi <?= $statuts[$current_statut]['icon'] ?? 'bi-circle' ?>"></i>
                        <?= $statuts[$current_statut]['label'] ?? $current_statut ?>
                    </span>
                </div>
                <div class="date"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></div>
            </div>
            <div class="details">
                <strong><?= htmlspecialchars($nom_produit) ?></strong>
                <?php if(!$is_gateau && isset($c['quantite'])): ?>
                    - Qté: <?= $c['quantite'] ?>
                <?php endif; ?>
                - Total: <strong style="color:#C8922A;"><?= number_format($total, 0, ',', ' ') ?> F</strong>
                <?php if(!empty($c['adresse_livraison'])): ?>
                    <br><i class="bi bi-geo-alt" style="color:#8A99AA;"></i> <?= htmlspecialchars($c['adresse_livraison']) ?>
                <?php endif; ?>
            </div>
            
            <div class="statut-actions">
                <?php foreach($statuts as $key => $s): ?>
                    <?php if($key != $current_statut): ?>
                        <a href="commandes_repas.php?statut=<?= $key ?>&id=<?= $c['id'] ?>&type=<?= $c['type'] ?>" 
                           class="btn-statut btn-statut-<?= $key ?>"
                           onclick="return confirm('Changer le statut en <?= $s['label'] ?> ?')">
                            <?= $s['label'] ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <a href="commandes_repas.php?supprimer=<?= $c['id'] ?>&type=<?= $c['type'] ?>" 
                   class="btn-statut btn-statut-annulee" 
                   onclick="return confirm('Supprimer cette commande #<?= $c['id'] ?> ?')" 
                   style="background:#F8D7DA;color:#721C24;">
                    <i class="bi bi-trash3"></i> Supprimer
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align:center;margin-top:20px;">
            <a href="commandes_repas.php" class="btn-admin" style="background:#C8922A;color:#fff;border-color:#C8922A;">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL DÉTAIL COMMANDE UNIQUE -->
<?php if($detail_commande && !isset($_GET['voir_client'])): ?>
<div class="modal-overlay active" id="modalDetail" onclick="if(event.target===this) closeModal()">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
        <div class="modal-title">
            📋 Détail de la commande <span>#<?= $detail_commande['id'] ?></span>
        </div>
        
        <?php if($detail_type == 'gateau'): ?>
            <div class="detail-row">
                <span class="label">Type</span>
                <span class="value">🎂 Gâteau</span>
            </div>
            <div class="detail-row">
                <span class="label">Nom du gâteau</span>
                <span class="value"><?= htmlspecialchars($detail_commande['nom_gateau'] ?? '-') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Nom personnalisé</span>
                <span class="value"><?= htmlspecialchars($detail_commande['nom_personnalise'] ?? '-') ?></span>
            </div>
            <?php if(!empty($detail_commande['age'])): ?>
            <div class="detail-row">
                <span class="label">Âge</span>
                <span class="value"><?= $detail_commande['age'] ?> ans</span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="label">Message</span>
                <span class="value"><?= htmlspecialchars($detail_commande['message_inscription'] ?? '-') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Date événement</span>
                <span class="value"><?= date('d/m/Y', strtotime($detail_commande['date_evenement'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Instructions</span>
                <span class="value"><?= htmlspecialchars($detail_commande['instructions'] ?? 'Aucune') ?></span>
            </div>
        <?php else: ?>
            <div class="detail-row">
                <span class="label">Type</span>
                <span class="value">🍽️ Repas</span>
            </div>
            <div class="detail-row">
                <span class="label">Plat</span>
                <span class="value"><?= htmlspecialchars($detail_commande['nom_plat'] ?? '-') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Quantité</span>
                <span class="value"><?= $detail_commande['quantite'] ?? 1 ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Instructions</span>
                <span class="value"><?= htmlspecialchars($detail_commande['instructions'] ?? 'Aucune') ?></span>
            </div>
        <?php endif; ?>
        
        <div class="detail-row">
            <span class="label">Client</span>
            <span class="value"><?= htmlspecialchars($detail_commande['nom_client'] ?? 'Inconnu') ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Téléphone</span>
            <span class="value"><?= htmlspecialchars($detail_commande['telephone'] ?? '-') ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Email</span>
            <span class="value"><?= htmlspecialchars($detail_commande['email'] ?? '-') ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Adresse</span>
            <span class="value"><?= htmlspecialchars($detail_commande['adresse_livraison'] ?? '-') ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Total</span>
            <span class="value" style="color:#C8922A;font-size:1.1rem;">
                <?= number_format($detail_type == 'gateau' ? ($detail_commande['prix'] ?? 0) : ($detail_commande['total'] ?? 0), 0, ',', ' ') ?> FCFA
            </span>
        </div>
        <div class="detail-row">
            <span class="label">Statut</span>
            <span class="value">
                <span class="badge-statut statut-<?= $detail_commande['statut'] ?>">
                    <?= $statuts[$detail_commande['statut']]['label'] ?? $detail_commande['statut'] ?>
                </span>
            </span>
        </div>
        <div class="detail-row">
            <span class="label">Date</span>
            <span class="value"><?= date('d/m/Y à H:i', strtotime($detail_commande['created_at'])) ?></span>
        </div>
        
        <div style="text-align:center;margin-top:20px;">
            <a href="commandes_repas.php" class="btn-admin" style="background:#C8922A;color:#fff;border-color:#C8922A;">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function closeModal() {
    document.getElementById('modalDetail').classList.remove('active');
    window.location.href = 'commandes_repas.php';
}
</script>

</body>
</html>