<?php
// ============================================
// ADMIN - COMMANDES D'UN CLIENT SPÉCIFIQUE
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

$telephone = isset($_GET['telephone']) ? $_GET['telephone'] : '';

if (empty($telephone)) {
    header('Location: commandes.php');
    exit;
}

// ✅ CORRECTION : Commentaire déplacé sur sa propre ligne
$stmt = $pdo->prepare("
    SELECT * FROM commandes 
    WHERE telephone = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$telephone]);
$commandes = $stmt->fetchAll();

if (empty($commandes)) {
    header('Location: commandes.php');
    exit;
}

$client_nom = $commandes[0]['nom_client'];
$total_global = 0;
foreach($commandes as $c) {
    $total_global += $c['total'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes de <?= htmlspecialchars($client_nom) ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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

        .client-card {
            background: #fff;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 25px;
            border: 1px solid rgba(200,146,42,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .client-card .info h3 { font-size: 1.1rem; color: #0D0D0D; }
        .client-card .info p { color: #8A99AA; font-size: 0.85rem; margin: 0; }
        .client-card .total { text-align: right; }
        .client-card .total .amount {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: #C8922A;
            font-weight: 700;
        }
        .client-card .total .label { color: #8A99AA; font-size: 0.7rem; }

        .table-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #E8ECF0;
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

        .badge-paiement {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            background: rgba(200,146,42,0.1);
            color: #C8922A;
        }

        .btn-pdf {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            background: #E74C3C;
            color: white;
            transition: all 0.3s;
        }
        .btn-pdf:hover { background: #C0392B; color: white; }

        .btn-detail {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            background: rgba(41,128,185,0.1);
            color: #2980B9;
            transition: all 0.3s;
        }
        .btn-detail:hover { background: #2980B9; color: white; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 16px; }
            .client-card { flex-direction: column; text-align: center; }
            .client-card .total { text-align: center; }
            table { display: block; overflow-x: auto; }
            .topbar { flex-direction: column; align-items: flex-start; gap: 10px; }
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
        <div class="nav-section">Restaurant</div>
        <a href="plats.php" class="nav-item"><i class="bi bi-cup-hot"></i> Plats</a>
        <a href="commandes_repas.php" class="nav-item"><i class="bi bi-bag-check"></i> Commandes repas</a>
        <a href="reservations.php" class="nav-item"><i class="bi bi-calendar-check"></i> Réservations</a>
        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">👤 Commandes de <span><?= htmlspecialchars($client_nom) ?></span></div>
            <div class="topbar-breadcrumb">Administration → <a href="commandes.php" style="color:#999;text-decoration:none;">Commandes</a> → Client</div>
        </div>
        <div class="topbar-right">
            <a href="commandes.php" class="btn-admin btn-outline">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <div class="content">
        <!-- Carte client -->
        <div class="client-card">
            <div class="info">
                <h3><i class="bi bi-person" style="color:#C8922A;"></i> <?= htmlspecialchars($client_nom) ?></h3>
                <p><i class="bi bi-telephone"></i> <?= htmlspecialchars($telephone) ?></p>
                <p><i class="bi bi-receipt"></i> <?= count($commandes) ?> commande(s)</p>
            </div>
            <div class="total">
                <div class="label">Total dépensé</div>
                <div class="amount"><?= number_format($total_global, 0, ',', ' ') ?> FCFA</div>
            </div>
        </div>

        <!-- Tableau des commandes -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">📋 Toutes ses commandes</div>
                <div class="table-count"><?= count($commandes) ?> commande(s)</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>N° Commande</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Paiement</th>
                        <th>Statut</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $paiements = [
                        'livraison' => '💵 Livraison',
                        'orange_money' => '🟠 Orange Money',
                        'wave' => '🌊 Wave',
                        'moov_money' => '📱 Moov Money',
                        'carte' => '💳 Carte',
                        'especes' => '💰 Espèces'
                    ];
                    $statut_labels = [
                        'en_attente' => 'En attente',
                        'confirmee' => 'Confirmée',
                        'en_preparation' => 'Préparation',
                        'en_livraison' => 'Livraison',
                        'livree' => 'Livrée',
                        'annulee' => 'Annulée'
                    ];
                    ?>
                    <?php foreach($commandes as $c): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($c['numero_commande']) ?></strong>
                        </td>
                        <td style="font-size:0.8rem;color:#8A99AA;">
                            <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                        </td>
                        <td style="font-weight:600;color:#C8922A;">
                            <?= number_format($c['total'], 0, ',', ' ') ?> FCFA
                        </td>
                        <td>
                            <span class="badge-paiement">
                                <?= $paiements[$c['mode_paiement']] ?? $c['mode_paiement'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-statut statut-<?= $c['statut'] ?>">
                                <?= $statut_labels[$c['statut']] ?? $c['statut'] ?>
                            </span>
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <a href="commande_detail.php?id=<?= $c['id'] ?>" class="btn-detail" title="Voir détails">
                                <i class="bi bi-eye"></i> Détail
                            </a>
                            <a href="generer_facture.php?id=<?= $c['id'] ?>" class="btn-pdf" title="Générer la facture">
                                <i class="bi bi-file-pdf"></i> PDF
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>