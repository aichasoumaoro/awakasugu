<?php
// ============================================
// GESTION DES RÉSERVATIONS - ADMIN
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
// CHANGER LE STATUT D'UNE RÉSERVATION
// ============================================
if (isset($_GET['statut']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $statut = $_GET['statut'];
    $statuts_valides = ['en_attente', 'confirmee', 'terminee', 'annulee'];
    
    if (in_array($statut, $statuts_valides)) {
        $pdo->prepare("UPDATE reservations SET statut = ? WHERE id = ?")->execute([$statut, $id]);
        $_SESSION['message_reservation'] = 'Statut mis à jour avec succès !';
        header('Location: reservations.php');
        exit;
    }
}

// ============================================
// SUPPRIMER UNE RÉSERVATION
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM reservations WHERE id = ?")->execute([$id]);
    $_SESSION['message_reservation'] = 'Réservation supprimée avec succès !';
    header('Location: reservations.php');
    exit;
}

// ============================================
// VOIR TOUTES LES RÉSERVATIONS D'UN CLIENT
// ============================================
$detail_client = null;
$reservations_client = [];
if (isset($_GET['voir_client']) && isset($_GET['telephone'])) {
    $telephone = $_GET['telephone'];
    
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE telephone = ? ORDER BY date_reservation DESC, heure_reservation DESC");
    $stmt->execute([$telephone]);
    $reservations_client = $stmt->fetchAll();
    
    if (!empty($reservations_client)) {
        $detail_client = $reservations_client[0];
    }
}

// ============================================
// RÉCUPÉRER TOUTES LES RÉSERVATIONS
// ============================================
$reservations = $pdo->query("SELECT * FROM reservations ORDER BY date_reservation DESC, heure_reservation DESC")->fetchAll();

// ============================================
// GROUPER LES RÉSERVATIONS PAR CLIENT (TÉLÉPHONE)
// ============================================
$clients = [];
foreach($reservations as $r) {
    $telephone = $r['telephone'] ?? 'inconnu';
    if (!isset($clients[$telephone])) {
        $clients[$telephone] = [
            'telephone' => $telephone,
            'nom_client' => $r['nom_client'] ?? 'Inconnu',
            'reservations' => [],
            'total_reservations' => 0,
            'derniere_reservation' => $r['date_reservation'] . ' ' . $r['heure_reservation'],
            'statut_global' => 'en_attente'
        ];
    }
    $clients[$telephone]['reservations'][] = $r;
    $clients[$telephone]['total_reservations']++;
    
    // Mettre à jour la dernière réservation
    $date_resa = $r['date_reservation'] . ' ' . $r['heure_reservation'];
    if ($date_resa > $clients[$telephone]['derniere_reservation']) {
        $clients[$telephone]['derniere_reservation'] = $date_resa;
    }
    
    // Déterminer le statut global (le plus avancé)
    $statut_priority = ['terminee' => 4, 'confirmee' => 3, 'en_attente' => 2, 'annulee' => 0];
    if (!isset($clients[$telephone]['statut_global']) || 
        $statut_priority[$r['statut']] > $statut_priority[$clients[$telephone]['statut_global']]) {
        $clients[$telephone]['statut_global'] = $r['statut'];
    }
}

// Trier les clients par date de dernière réservation
usort($clients, function($a, $b) {
    return strtotime($b['derniere_reservation']) - strtotime($a['derniere_reservation']);
});

// Statistiques
$total_reservations = count($reservations);
$reservations_attente = $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn();
$reservations_aujourdhui = $pdo->query("SELECT COUNT(*) FROM reservations WHERE date_reservation = CURDATE()")->fetchColumn();

$message = $_SESSION['message_reservation'] ?? '';
unset($_SESSION['message_reservation']);

$statuts = [
    'en_attente' => ['label' => 'En attente', 'class' => 'statut-en_attente', 'icon' => 'bi-clock-history'],
    'confirmee' => ['label' => 'Confirmée', 'class' => 'statut-confirmee', 'icon' => 'bi-check-circle'],
    'terminee' => ['label' => 'Terminée', 'class' => 'statut-terminee', 'icon' => 'bi-check-circle-fill'],
    'annulee' => ['label' => 'Annulée', 'class' => 'statut-annulee', 'icon' => 'bi-x-circle'],
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservations - Admin Awa Ka Sugu</title>
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
            transition: all 0.3s ease;
        }
        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
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
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .statut-en_attente { background: #FEF6E6; color: #E67E22; }
        .statut-confirmee { background: #E8F5E9; color: #2E7D32; }
        .statut-terminee { background: #D4EDDA; color: #1A7A4A; }
        .statut-annulee { background: #F8D7DA; color: #721C24; }

        .btn-act {
            width: 32px; height: 32px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-delete { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .btn-delete:hover { background: #E74C3C; color: #fff; transform: scale(1.1); }
        .btn-view { background: rgba(41,128,185,0.1); color: #2980B9; }
        .btn-view:hover { background: #2980B9; color: #fff; }

        .btn-statut-group {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            justify-content: center;
        }
        .btn-statut {
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            border: 1px solid transparent;
        }
        .btn-statut:hover {
            transform: scale(0.95);
            opacity: 0.8;
        }
        .btn-statut-attente { background: #FEF6E6; color: #E67E22; border-color: #FEF6E6; }
        .btn-statut-confirmee { background: #E8F5E9; color: #2E7D32; border-color: #E8F5E9; }
        .btn-statut-terminee { background: #D4EDDA; color: #1A7A4A; border-color: #D4EDDA; }
        .btn-statut-annulee { background: #F8D7DA; color: #721C24; border-color: #F8D7DA; }

        .badge-nb-reservations {
            display: inline-block;
            background: #C8922A;
            color: #fff;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 0.6rem;
            font-weight: 700;
            margin-left: 5px;
        }

        .empty-state { text-align: center; padding: 40px; color: #999; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 10px; }

        /* Modal Détail Client */
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
            max-width: 800px;
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
            margin-bottom: 15px;
        }
        .modal-title span { color: #C8922A; }
        .modal-subtitle {
            font-size: 0.9rem;
            color: #8A99AA;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #F0F2F5;
        }

        .reservation-client-item {
            background: #F8F9FA;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 10px;
            border-left: 3px solid #C8922A;
        }
        .reservation-client-item .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .reservation-client-item .header .date {
            font-size: 0.8rem;
            color: #8A99AA;
        }
        .reservation-client-item .details {
            margin-top: 5px;
            font-size: 0.85rem;
            color: #5A6B7A;
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
        <a href="commandes_repas.php" class="nav-item"><i class="bi bi-bag-check"></i> Commandes repas</a>
        <a href="reservations.php" class="nav-item active"><i class="bi bi-calendar-check"></i> Réservations</a>

        <div class="nav-section">Gestion</div>
        <a href="achats.php" class="nav-item"><i class="bi bi-cart-check"></i> Achats</a>
        <a href="stocks.php" class="nav-item"><i class="bi bi-bar-chart"></i> Stocks</a>
        <a href="promotions.php" class="nav-item"><i class="bi bi-percent"></i> Promotions</a>
        <a href="videos.php" class="nav-item"><i class="bi bi-camera-reels"></i> Vidéos</a>
        <a href="factures.php" class="nav-item"><i class="bi bi-file-earmark-text"></i> Factures</a>
        <a href="maintenance.php" class="nav-item"><i class="bi bi-tools"></i> Maintenance</a>
        <a href="avis_admin.php" class="nav-item"><i class="bi bi-star"></i> Avis clients</a>

        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">📅 Gestion des <span>Réservations</span></div>
            <div class="topbar-breadcrumb">Restaurant → Réservations</div>
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
                <div class="stat-icon ic-or"><i class="bi bi-calendar-check"></i></div>
                <div>
                    <div class="stat-val"><?= $total_reservations ?></div>
                    <div class="stat-lbl">Total réservations</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-val"><?= $reservations_attente ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-calendar-day"></i></div>
                <div>
                    <div class="stat-val"><?= $reservations_aujourdhui ?></div>
                    <div class="stat-lbl">Aujourd'hui</div>
                </div>
            </div>
        </div>

        <!-- ============================================
        LISTE DES CLIENTS AVEC LEURS RÉSERVATIONS
        ============================================ -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">👥 Clients avec leurs réservations</div>
                <div class="table-count"><?= count($clients) ?> client(s)</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Téléphone</th>
                        <th style="text-align:center;">Réservations</th>
                        <th>Dernière réservation</th>
                        <th>Statut</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($clients)): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>Aucune réservation</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach($clients as $client): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($client['nom_client']) ?></strong></td>
                            <td><?= htmlspecialchars($client['telephone']) ?></td>
                            <td style="text-align:center;">
                                <span class="badge-nb-reservations"><?= $client['total_reservations'] ?></span>
                            </td>
                            <td style="font-size:0.8rem;color:#8A99AA;">
                                <?= date('d/m/Y H:i', strtotime($client['derniere_reservation'])) ?>
                            </td>
                            <td>
                                <span class="badge-statut statut-<?= $client['statut_global'] ?>">
                                    <i class="bi <?= $statuts[$client['statut_global']]['icon'] ?? 'bi-circle' ?>"></i>
                                    <?= $statuts[$client['statut_global']]['label'] ?? $client['statut_global'] ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <a href="reservations.php?voir_client=1&telephone=<?= urlencode($client['telephone']) ?>" 
                                   class="btn-act btn-view" title="Voir toutes les réservations du client">
                                    <i class="bi bi-eye"></i>
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

<!-- ============================================
     MODAL DÉTAIL CLIENT (TOUTES SES RÉSERVATIONS)
     ============================================ -->
<?php if($detail_client && !empty($reservations_client)): ?>
<div class="modal-overlay active" id="modalDetail" onclick="if(event.target===this) closeModal()">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
        <div class="modal-title">
            👤 Réservations de <span><?= htmlspecialchars($detail_client['nom_client'] ?? 'Client') ?></span>
        </div>
        <div class="modal-subtitle">
            <i class="bi bi-telephone"></i> <?= htmlspecialchars($detail_client['telephone'] ?? 'Téléphone non renseigné') ?>
            <span style="margin-left:20px;">
                <i class="bi bi-calendar-check"></i> <?= count($reservations_client) ?> réservation(s)
            </span>
        </div>
        
        <?php foreach($reservations_client as $r): ?>
        <div class="reservation-client-item">
            <div class="header">
                <div>
                    <span class="badge-statut statut-<?= $r['statut'] ?>">
                        <i class="bi <?= $statuts[$r['statut']]['icon'] ?? 'bi-circle' ?>"></i>
                        <?= $statuts[$r['statut']]['label'] ?? $r['statut'] ?>
                    </span>
                    <span style="font-weight:600;color:#C8922A;margin-left:10px;">
                        <?= $r['nb_personnes'] ?? 1 ?> personne(s)
                    </span>
                </div>
                <div class="date"><?= date('d/m/Y H:i', strtotime($r['date_reservation'] . ' ' . $r['heure_reservation'])) ?></div>
            </div>
            <div class="details">
                <i class="bi bi-geo-alt" style="color:#8A99AA;"></i> 
                <?= htmlspecialchars($r['notes'] ?? 'Aucune remarque') ?>
                <?php if(!empty($r['email'])): ?>
                    <br><i class="bi bi-envelope" style="color:#8A99AA;"></i> <?= htmlspecialchars($r['email']) ?>
                <?php endif; ?>
            </div>
            
            <!-- ACTIONS : Changer le statut -->
            <div class="btn-statut-group" style="margin-top:8px;justify-content:flex-start;">
                <?php foreach($statuts as $key => $s): ?>
                    <?php if($key != $r['statut']): ?>
                        <a href="reservations.php?statut=<?= $key ?>&id=<?= $r['id'] ?>" 
                           class="btn-statut btn-statut-<?= $key ?>"
                           onclick="return confirm('Changer le statut en <?= $s['label'] ?> ?')">
                            <?= $s['label'] ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <a href="reservations.php?supprimer=<?= $r['id'] ?>" 
                   class="btn-statut btn-statut-annulee" 
                   onclick="return confirm('Supprimer cette réservation #<?= $r['id'] ?> ?')">
                    <i class="bi bi-trash3"></i> Supprimer
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align:center;margin-top:20px;">
            <a href="reservations.php" class="btn-admin" style="background:#C8922A;color:#fff;border-color:#C8922A;">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function closeModal() {
    document.getElementById('modalDetail').classList.remove('active');
    window.location.href = 'reservations.php';
}
</script>

</body>
</html>