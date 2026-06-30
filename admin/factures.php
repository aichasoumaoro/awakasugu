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

// Récupérer les factures
$factures = $pdo->query("
    SELECT f.*, c.numero_commande, c.nom_client, c.total as commande_total
    FROM factures f 
    JOIN commandes c ON c.id = f.commande_id 
    ORDER BY f.created_at DESC
")->fetchAll();

// Statistiques
$total_factures = count($factures);
$factures_payees = 0;
$factures_attente = 0;
$total_montant = 0;

foreach($factures as $f) {
    if($f['statut_paiement'] == 'payee') {
        $factures_payees++;
    } else {
        $factures_attente++;
    }
    $total_montant += $f['montant_total'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factures - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F5F7FA; display: flex; min-height: 100vh; font-family: 'Jost', sans-serif; }
        
        .sidebar { background: #0D0D0D; min-height: 100vh; position: fixed; top: 0; left: 0; width: 260px; padding: 20px 0; z-index: 100; overflow-y: auto; }
        .sidebar-logo { text-align: center; padding: 20px; border-bottom: 1px solid rgba(200,146,42,0.2); }
        .sidebar-logo h3 { color: #C8922A; font-size: 1.3rem; margin: 0; }
        .sidebar-logo small { color: rgba(255,255,255,0.3); font-size: 0.7rem; }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 12px 24px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s; }
        .sidebar-menu li a:hover, .sidebar-menu li a.active { background: rgba(200,146,42,0.1); color: #C8922A; border-left: 3px solid #C8922A; }
        .sidebar-menu li a i { width: 20px; }
        
        .content { margin-left: 260px; padding: 30px; flex: 1; }
        .content-header {
            background: white;
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #E8ECF0;
        }
        .content-header h2 { font-size: 1.3rem; font-weight: 700; color: #0D0D0D; }
        .content-header h2 span { color: #C8922A; }
        .content-header .breadcrumb { font-size: 0.75rem; color: #8A99AA; margin: 0; }
        .content-header .breadcrumb a { color: #8A99AA; text-decoration: none; }
        .content-header .breadcrumb a:hover { color: #C8922A; }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-box {
            background: #fff;
            border-radius: 12px;
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
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .ic-or { background: rgba(200,146,42,0.1); color: #C8922A; }
        .ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
        .ic-red { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .ic-blue { background: rgba(41,128,185,0.1); color: #2980B9; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: #0D0D0D;
            line-height: 1;
        }
        .stat-lbl { font-size: 0.7rem; color: #8A99AA; margin-top: 2px; }
        
        .card { border-radius: 12px; border: 1px solid #E8ECF0; overflow: hidden; }
        .card-header { background: #0D0D0D; color: #C8922A; padding: 15px 20px; font-weight: 600; border: none; }
        .card-header i { margin-right: 8px; }
        .table { margin-bottom: 0; }
        .table th { background: #0D0D0D; color: #C8922A; border: none; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .table td { vertical-align: middle; padding: 12px 15px; }
        .table tr:hover td { background: #FEFBF5; }
        
        .badge-payee { background: #D4EDDA; color: #1A7A4A; padding: 4px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-attente { background: #FFF3CD; color: #856404; padding: 4px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-annulee { background: #F8D7DA; color: #721C24; padding: 4px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        
        .btn-action { padding: 5px 12px; border-radius: 6px; font-size: 0.75rem; transition: all 0.2s; }
        .btn-action i { font-size: 0.9rem; }
        .btn-action:hover { transform: translateY(-1px); }
        
        .btn-pdf { background: #E74C3C; color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 0.75rem; transition: all 0.3s; }
        .btn-pdf:hover { background: #C0392B; color: white; transform: translateY(-1px); }
        .btn-generer { background: #C8922A; color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 0.75rem; transition: all 0.3s; }
        .btn-generer:hover { background: #9A6E1A; color: white; transform: translateY(-1px); }
        
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
        
        @media (max-width: 1000px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .content { margin-left: 0; padding: 20px 16px; }
            .content-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .table { font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-logo">
        <h3>AWA KA SUGU</h3>
        <small>Administration</small>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Tableau de bord</a></li>
        <li><a href="produits.php"><i class="bi bi-box-seam"></i> Produits</a></li>
        <li><a href="commandes.php"><i class="bi bi-receipt"></i> Commandes</a></li>
        <li><a href="clients.php"><i class="bi bi-people"></i> Clients</a></li>
        <li><a href="plats.php"><i class="bi bi-cup-hot"></i> Restaurant</a></li>
        <li><a href="promotions.php"><i class="bi bi-percent"></i> Promotions</a></li>
        <li><a href="bannieres.php"><i class="bi bi-images"></i> Bannières</a></li>
        <li><a href="factures.php" class="active"><i class="bi bi-file-pdf"></i> Factures</a></li>
        <li><a href="rapports.php"><i class="bi bi-bar-chart"></i> Rapports</a></li>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
    </ul>
</div>

<div class="content">
    <div class="content-header">
        <div>
            <h2><span>📄</span> Gestion des factures</h2>
            <div class="breadcrumb">
                <a href="dashboard.php">Administration</a> &gt; 
                Factures
            </div>
        </div>
        <div>
            <span style="font-size:0.8rem;color:#8A99AA;">
                <i class="bi bi-file-text"></i> <?= $total_factures ?> facture(s)
            </span>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-icon ic-or"><i class="bi bi-file-text"></i></div>
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
            <div class="stat-icon ic-blue"><i class="bi bi-cash"></i></div>
            <div>
                <div class="stat-val"><?= number_format($total_montant, 0, ',', ' ') ?> F</div>
                <div class="stat-lbl">Montant total</div>
            </div>
        </div>
    </div>

    <!-- Tableau des factures -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list"></i> Liste des factures</span>
            <span style="font-size:0.7rem;color:rgba(255,255,255,0.4);">
                <i class="bi bi-calendar3"></i> Triées par date décroissante
            </span>
        </div>
        <div class="card-body p-0" style="overflow-x:auto;">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>N° Commande</th>
                        <th>Client</th>
                        <th>Montant</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th style="text-align:center;">PDF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($factures)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-file-text"></i>
                                    <p>Aucune facture générée pour le moment</p>
                                    <span style="font-size:0.8rem;">Les factures sont générées automatiquement après chaque commande</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($factures as $f): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($f['numero_facture']) ?></strong>
                            </td>
                            <td>
                                <a href="commande_detail.php?id=<?= $f['commande_id'] ?>" style="color:#C8922A;text-decoration:none;">
                                    <?= htmlspecialchars($f['numero_commande']) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($f['client_nom']) ?></strong>
                            </td>
                            <td style="font-weight:700;color:#C8922A;">
                                <?= number_format($f['montant_total'], 0, ',', ' ') ?> FCFA
                            </td>
                            <td style="font-size:0.8rem;color:#8A99AA;">
                                <?= date('d/m/Y H:i', strtotime($f['created_at'])) ?>
                            </td>
                            <td>
                                <?php if($f['statut_paiement'] == 'payee'): ?>
                                    <span class="badge-payee"><i class="bi bi-check-circle"></i> Payée</span>
                                <?php elseif($f['statut_paiement'] == 'annulee'): ?>
                                    <span class="badge-annulee"><i class="bi bi-x-circle"></i> Annulée</span>
                                <?php else: ?>
                                    <span class="badge-attente"><i class="bi bi-clock"></i> En attente</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if(!empty($f['fichier_pdf']) && file_exists('../uploads/factures/'.$f['fichier_pdf'])): ?>
                                    <a href="../uploads/factures/<?= $f['fichier_pdf'] ?>" target="_blank" class="btn-pdf" title="Télécharger le PDF">
                                        <i class="bi bi-file-pdf"></i> PDF
                                    </a>
                                <?php else: ?>
                                    <a href="generer_facture.php?id=<?= $f['commande_id'] ?>" class="btn-generer" title="Générer la facture">
                                        <i class="bi bi-plus-circle"></i> Générer
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>