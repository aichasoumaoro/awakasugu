<?php
// ============================================
// GESTION DES PAIEMENTS - ADMIN AWA KA SUGU
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
// CHANGER LE STATUT D'UN PAIEMENT
// ============================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $statut = $_GET['statut'] ?? 'valide';
    
    if ($statut == 'valide') {
        $stmt = $pdo->prepare("UPDATE paiements SET statut = 'confirme' WHERE id = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("SELECT commande_id FROM paiements WHERE id = ?");
        $stmt->execute([$id]);
        $paiement = $stmt->fetch();
        
        if ($paiement && $paiement['commande_id']) {
            $stmt = $pdo->prepare("UPDATE commandes SET statut = 'confirmee' WHERE id = ?");
            $stmt->execute([$paiement['commande_id']]);
        }
        
        $_SESSION['message_paiement'] = 'Paiement validé avec succès !';
    } elseif ($statut == 'rejete') {
        $stmt = $pdo->prepare("UPDATE paiements SET statut = 'echoue' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['message_paiement'] = 'Paiement rejeté.';
    }
    
    header('Location: paiements.php');
    exit;
}

// ============================================
// RÉCUPÉRER TOUS LES PAIEMENTS
// ============================================
$paiements = $pdo->query("
    SELECT p.*, c.numero_commande, c.nom_client, c.total as commande_total,
           c.telephone, c.notes as commande_notes
    FROM paiements p
    LEFT JOIN commandes c ON p.commande_id = c.id
    ORDER BY p.created_at DESC
")->fetchAll();

// ============================================
// GROUPER LES PAIEMENTS PAR CLIENT (TÉLÉPHONE)
// ============================================
$clients = [];
foreach($paiements as $p) {
    $telephone = $p['telephone'] ?? 'inconnu';
    if (!isset($clients[$telephone])) {
        $clients[$telephone] = [
            'telephone' => $telephone,
            'nom_client' => $p['nom_client'] ?? 'Inconnu',
            'paiements' => [],
            'total_paiements' => 0,
            'total_montant' => 0,
            'dernier_paiement' => $p['created_at'],
            'nom_deposant' => '-'
        ];
    }
    $clients[$telephone]['paiements'][] = $p;
    $clients[$telephone]['total_paiements']++;
    $clients[$telephone]['total_montant'] += $p['montant'];
    
    // Extraire le nom du déposant
    $nom_deposant = '-';
    if (!empty($p['commande_notes'])) {
        if (preg_match('/Nom:\s*([^\n]+)/', $p['commande_notes'], $matches)) {
            $nom_deposant = trim($matches[1]);
        }
    }
    if ($nom_deposant != '-') {
        $clients[$telephone]['nom_deposant'] = $nom_deposant;
    }
    
    // Mettre à jour la date du dernier paiement
    if (strtotime($p['created_at']) > strtotime($clients[$telephone]['dernier_paiement'])) {
        $clients[$telephone]['dernier_paiement'] = $p['created_at'];
    }
}

// Trier les clients par date du dernier paiement
usort($clients, function($a, $b) {
    return strtotime($b['dernier_paiement']) - strtotime($a['dernier_paiement']);
});

// Statistiques globales
$total_paiements = count($paiements);
$total_clients = count($clients);
$total_montant_global = 0;
$paiements_attente = 0;
$paiements_valides = 0;
$paiements_rejetes = 0;

foreach($paiements as $p) {
    if($p['statut'] == 'en_attente') $paiements_attente++;
    elseif($p['statut'] == 'confirme') $paiements_valides++;
    elseif($p['statut'] == 'echoue') $paiements_rejetes++;
    $total_montant_global += $p['montant'];
}

$message = $_SESSION['message_paiement'] ?? '';
unset($_SESSION['message_paiement']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiements - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
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
        .ic-red { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
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
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .statut-en_attente { background: #FEF6E6; color: #E67E22; }
        .statut-confirme { background: #E8F5E9; color: #2E7D32; }
        .statut-echoue { background: #F8D7DA; color: #721C24; }

        .badge-mode {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .mode-orange_money { background: #FF6600; color: #fff; }
        .mode-wave { background: #1A7A4A; color: #fff; }
        .mode-moov_money { background: #E63E2E; color: #fff; }

        .btn-small {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            cursor: pointer;
        }
        .btn-small.green { background: #1A7A4A; color: #fff; }
        .btn-small.green:hover { background: #145E38; }
        .btn-small.red { background: #E74C3C; color: #fff; }
        .btn-small.red:hover { background: #C0392B; }

        .badge-nb-paiements {
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
        .paiement-detail-item {
            background: #F8F9FA;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 10px;
            border-left: 3px solid #C8922A;
        }
        .paiement-detail-item .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .paiement-detail-item .header .date {
            font-size: 0.8rem;
            color: #8A99AA;
        }
        .paiement-detail-item .details {
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
        <a href="reservations.php" class="nav-item"><i class="bi bi-calendar-check"></i> Réservations</a>
        <div class="nav-section">Gestion</div>
        <a href="achats.php" class="nav-item"><i class="bi bi-cart-check"></i> Achats</a>
        <a href="stocks.php" class="nav-item"><i class="bi bi-bar-chart"></i> Stocks</a>
        <a href="promotions.php" class="nav-item"><i class="bi bi-percent"></i> Promotions</a>
        <a href="videos.php" class="nav-item"><i class="bi bi-camera-reels"></i> Vidéos</a>
        <a href="factures.php" class="nav-item"><i class="bi bi-file-earmark-text"></i> Factures</a>
        <a href="paiements.php" class="nav-item active"><i class="bi bi-credit-card"></i> Paiements</a>
        <a href="maintenance.php" class="nav-item"><i class="bi bi-tools"></i> Maintenance</a>
        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">💳 Gestion des <span>Paiements</span></div>
            <div class="topbar-breadcrumb">Gestion → Paiements</div>
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
                <div class="stat-icon ic-or"><i class="bi bi-credit-card"></i></div>
                <div>
                    <div class="stat-val"><?= $total_paiements ?></div>
                    <div class="stat-lbl">Total paiements</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-val"><?= $paiements_attente ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $paiements_valides ?></div>
                    <div class="stat-lbl">Validés</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-cash"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_montant_global, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Total des paiements</div>
                </div>
            </div>
        </div>

        <!-- ============================================
        LISTE DES CLIENTS AVEC LEURS PAIEMENTS
        ============================================ -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">👥 Clients</div>
                <div class="table-count"><?= $total_clients ?> client(s)</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Téléphone</th>
                        <th style="text-align:center;">Paiements</th>
                        <th style="text-align:right;">Total</th>
                        <th>Dernier paiement</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($clients)): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-credit-card"></i><p>Aucun paiement</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach($clients as $client): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($client['nom_client']) ?></strong></td>
                            <td><?= htmlspecialchars($client['telephone']) ?></td>
                            <td style="text-align:center;">
                                <span class="badge-nb-paiements"><?= $client['total_paiements'] ?></span>
                            </td>
                            <td style="text-align:right;font-weight:600;color:#C8922A;">
                                <?= number_format($client['total_montant'], 0, ',', ' ') ?> F
                            </td>
                            <td style="font-size:0.8rem;color:#8A99AA;">
                                <?= date('d/m/Y H:i', strtotime($client['dernier_paiement'])) ?>
                            </td>
                            <td style="text-align:center;">
                                <a href="paiements.php?voir_client=1&telephone=<?= urlencode($client['telephone']) ?>" 
                                   class="btn-detail-client" title="Voir tous les paiements du client">
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

<!-- ============================================
     MODAL DÉTAIL CLIENT (TOUS SES PAIEMENTS)
     ============================================ -->
<?php if(isset($_GET['voir_client']) && isset($_GET['telephone'])): 
    $telephone = $_GET['telephone'];
    $paiements_client = [];
    $client_info = null;
    
    foreach($paiements as $p) {
        if($p['telephone'] == $telephone) {
            $paiements_client[] = $p;
            if(!$client_info) {
                $client_info = $p;
            }
        }
    }
    
    if(!empty($paiements_client)):
?>
<div class="modal-overlay active" id="modalDetail" onclick="if(event.target===this) closeModal()">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
        <div class="modal-title">
            👤 Paiements de <span><?= htmlspecialchars($client_info['nom_client'] ?? 'Client') ?></span>
        </div>
        <div class="modal-subtitle">
            <i class="bi bi-telephone"></i> <?= htmlspecialchars($telephone) ?>
            <span style="margin-left:20px;">
                <i class="bi bi-credit-card"></i> <?= count($paiements_client) ?> paiement(s)
            </span>
            <span style="margin-left:20px;">
                <i class="bi bi-cash"></i> Total : <?= number_format(array_sum(array_column($paiements_client, 'montant')), 0, ',', ' ') ?> F
            </span>
        </div>
        
        <?php foreach($paiements_client as $p): 
            $mode_label = '';
            $mode_class = '';
            if($p['mode'] == 'orange_money') { $mode_class = 'mode-orange_money'; $mode_label = 'Orange Money'; }
            elseif($p['mode'] == 'wave') { $mode_class = 'mode-wave'; $mode_label = 'Wave'; }
            elseif($p['mode'] == 'moov_money') { $mode_class = 'mode-moov_money'; $mode_label = 'Moov Money'; }
            
            $statut_label = '';
            if($p['statut'] == 'en_attente') $statut_label = '⏳ En attente';
            elseif($p['statut'] == 'confirme') $statut_label = '✅ Confirmé';
            elseif($p['statut'] == 'echoue') $statut_label = '❌ Échoué';
            
            $nom_deposant = '-';
            if (!empty($p['commande_notes'])) {
                if (preg_match('/Nom:\s*([^\n]+)/', $p['commande_notes'], $matches)) {
                    $nom_deposant = trim($matches[1]);
                }
            }
        ?>
        <div class="paiement-detail-item">
            <div class="header">
                <div>
                    <span style="font-weight:600;color:#C8922A;">#<?= $p['id'] ?></span>
                    <span class="badge-mode <?= $mode_class ?>"><?= $mode_label ?></span>
                    <span class="badge-statut statut-<?= $p['statut'] ?>"><?= $statut_label ?></span>
                </div>
                <div class="date"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></div>
            </div>
            <div class="details">
                <strong>Commande :</strong> <?= htmlspecialchars($p['numero_commande'] ?? 'N/A') ?>
                - <strong>Montant :</strong> <span style="color:#C8922A;font-weight:600;"><?= number_format($p['montant'], 0, ',', ' ') ?> F</span>
                <br>
                <strong>Déposant :</strong> <?= htmlspecialchars($nom_deposant) ?>
                - <strong>Téléphone :</strong> <?= htmlspecialchars($p['telephone_paiement'] ?? '-') ?>
                <?php if($p['reference_transaction']): ?>
                <br><strong>Réf :</strong> <?= htmlspecialchars($p['reference_transaction']) ?>
                <?php endif; ?>
            </div>
            <?php if($p['statut'] == 'en_attente'): ?>
            <div style="margin-top:8px;display:flex;gap:5px;">
                <a href="paiements.php?action=valider&id=<?= $p['id'] ?>&statut=valide" class="btn-small green" onclick="return confirm('Valider ce paiement ?')">Valider</a>
                <a href="paiements.php?action=valider&id=<?= $p['id'] ?>&statut=rejete" class="btn-small red" onclick="return confirm('Rejeter ce paiement ?')">Rejeter</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align:center;margin-top:20px;">
            <a href="paiements.php" class="btn-admin" style="background:#C8922A;color:#fff;border-color:#C8922A;">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>
</div>
<?php endif; endif; ?>

<script>
function closeModal() {
    document.getElementById('modalDetail').classList.remove('active');
    window.location.href = 'paiements.php';
}
</script>

</body>
</html>