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

// Filtres
$mois = isset($_GET['mois']) ? (int)$_GET['mois'] : date('m');
$annee = isset($_GET['annee']) ? (int)$_GET['annee'] : date('Y');

// Ventes du mois
$stmt = $pdo->prepare("SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as ca FROM commandes WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
$stmt->execute([$mois, $annee]);
$stats_mois = $stmt->fetch();

// Ventes du mois pour les ventes boutique
$stmt = $pdo->prepare("SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as ca FROM ventes_boutique WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
$stmt->execute([$mois, $annee]);
$stats_boutique = $stmt->fetch();

// Total des commandes de l'année
$stmt = $pdo->prepare("SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as ca FROM commandes WHERE YEAR(created_at) = ?");
$stmt->execute([$annee]);
$stats_annee = $stmt->fetch();

// Top produits
$top_produits = $pdo->query("
    SELECT p.nom, p.image_principale, SUM(dc.quantite) as vendu 
    FROM details_commande dc 
    JOIN produits p ON p.id = dc.produit_id 
    GROUP BY dc.produit_id 
    ORDER BY vendu DESC 
    LIMIT 10
")->fetchAll();

// Commandes par statut
$stats_statut = $pdo->query("SELECT statut, COUNT(*) as nb FROM commandes GROUP BY statut")->fetchAll();

// Ventes par mois
$ventes_mois = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
    $stmt->execute([$i, $annee]);
    $ventes_mois[$i] = $stmt->fetchColumn();
}

// Ventes boutique par mois
$ventes_boutique_mois = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM ventes_boutique WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
    $stmt->execute([$i, $annee]);
    $ventes_boutique_mois[$i] = $stmt->fetchColumn();
}

// Meilleur mois
$meilleur_mois = max($ventes_mois) > 0 ? array_search(max($ventes_mois), $ventes_mois) : 0;
$meilleur_mois_nom = $meilleur_mois > 0 ? date('F', mktime(0,0,0,$meilleur_mois,1)) : 'Aucune vente';

// Total clients
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_commandes = $pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
$ca_total = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM commandes")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .btn-excel { 
            background: #28A745; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 8px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        .btn-excel:hover { background: #1E7E34; color: white; transform: translateY(-2px); }
        
        .stat-card { 
            background: white; 
            border-radius: 12px; 
            padding: 20px; 
            text-align: center; 
            border: 1px solid #E8ECF0;
            transition: all 0.3s;
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.05); }
        .stat-card .number { 
            font-family: 'Playfair Display', serif;
            font-size: 2rem; 
            font-weight: 700; 
            color: #C8922A; 
            line-height: 1.2;
        }
        .stat-card .label { 
            font-size: 0.75rem; 
            color: #8A99AA; 
            margin-top: 5px; 
        }
        .stat-card .icon { 
            font-size: 2rem; 
            color: #C8922A; 
            opacity: 0.3;
            margin-bottom: 5px;
        }
        
        .card { border-radius: 12px; border: 1px solid #E8ECF0; }
        .card-header { background: #0D0D0D; color: #C8922A; padding: 15px 20px; font-weight: 600; border: none; }
        .card-header i { margin-right: 8px; }
        
        .table th { background: #0D0D0D; color: #C8922A; border: none; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .table tr:hover td { background: #FEFBF5; }
        
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .rank-1 { background: #FFD700; color: #0D0D0D; }
        .rank-2 { background: #C0C0C0; color: #0D0D0D; }
        .rank-3 { background: #CD7F32; color: #fff; }
        .rank-other { background: #F0F2F5; color: #8A99AA; }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #E8ECF0;
            margin-bottom: 25px;
        }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .content { margin-left: 0; padding: 20px 16px; }
            .content-header { flex-direction: column; align-items: flex-start; gap: 12px; }
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
        <li><a href="videos.php"><i class="bi bi-camera-reels"></i> Vidéos</a></li>
        <li><a href="factures.php"><i class="bi bi-file-pdf"></i> Factures</a></li>
        <li><a href="rapports.php" class="active"><i class="bi bi-bar-chart"></i> Rapports</a></li>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
    </ul>
</div>

<div class="content">
    <div class="content-header">
        <div>
            <h2><span>📊</span> Rapports et statistiques</h2>
            <div class="breadcrumb">
                <a href="dashboard.php">Administration</a> &gt; 
                Rapports
            </div>
        </div>
        <button class="btn-excel" onclick="alert('📥 Export Excel à implémenter')">
            <i class="bi bi-file-earmark-excel"></i> Exporter
        </button>
    </div>

    <!-- Filtres -->
    <div class="filter-card">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" style="font-weight:600;font-size:0.8rem;">Mois</label>
                <select name="mois" class="form-select">
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?= $i ?>" <?= $mois == $i ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-weight:600;font-size:0.8rem;">Année</label>
                <select name="annee" class="form-select">
                    <?php for($i=date('Y'); $i>=2023; $i--): ?>
                        <option value="<?= $i ?>" <?= $annee == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-warning" style="background:#C8922A;color:white;border:none;padding:10px 25px;font-weight:600;">
                    <i class="bi bi-funnel"></i> Filtrer
                </button>
            </div>
        </form>
    </div>

    <!-- Stats globales -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon"><i class="bi bi-box-seam"></i></div>
                <div class="number"><?= number_format($total_commandes, 0, ',', ' ') ?></div>
                <div class="label">Total commandes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon"><i class="bi bi-people"></i></div>
                <div class="number"><?= number_format($total_clients, 0, ',', ' ') ?></div>
                <div class="label">Total clients</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon"><i class="bi bi-cash"></i></div>
                <div class="number"><?= number_format($ca_total, 0, ',', ' ') ?> F</div>
                <div class="label">CA total</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon"><i class="bi bi-trophy"></i></div>
                <div class="number"><?= $meilleur_mois_nom ?></div>
                <div class="label">Meilleur mois</div>
            </div>
        </div>
    </div>

    <!-- Stats mois -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card" style="border-left:4px solid #C8922A;">
                <div class="number"><?= number_format($stats_mois['nb'] ?? 0, 0, ',', ' ') ?></div>
                <div class="label">Commandes (<?= date('F', mktime(0,0,0,$mois,1)) ?>)</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="border-left:4px solid #2980B9;">
                <div class="number"><?= number_format($stats_boutique['nb'] ?? 0, 0, ',', ' ') ?></div>
                <div class="label">Ventes boutique (<?= date('F', mktime(0,0,0,$mois,1)) ?>)</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="border-left:4px solid #27AE60;">
                <div class="number"><?= number_format(($stats_mois['ca'] ?? 0) + ($stats_boutique['ca'] ?? 0), 0, ',', ' ') ?> F</div>
                <div class="label">CA total (<?= date('F', mktime(0,0,0,$mois,1)) ?>)</div>
            </div>
        </div>
    </div>

    <!-- Graphique -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-graph-up"></i> Ventes mensuelles <?= $annee ?></span>
            <span style="font-size:0.7rem;color:rgba(255,255,255,0.4);">Commandes en ligne vs Boutique</span>
        </div>
        <div class="card-body">
            <canvas id="ventesChart" height="180"></canvas>
        </div>
    </div>

    <!-- Top produits -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-trophy"></i> Top 10 des produits les plus vendus</span>
            <span style="font-size:0.7rem;color:rgba(255,255,255,0.4);"><?= count($top_produits) ?> produits</span>
        </div>
        <div class="card-body p-0" style="overflow-x:auto;">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Produit</th>
                        <th style="text-align:center;">Quantité vendue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($top_produits)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4" style="color:#8A99AA;">
                                <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                                Aucune vente enregistrée
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $rank = 1; foreach($top_produits as $p): ?>
                        <tr>
                            <td>
                                <span class="rank-badge <?= $rank <= 3 ? 'rank-'.$rank : 'rank-other' ?>">
                                    <?= $rank ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if(!empty($p['image_principale']) && file_exists('../uploads/produits/'.$p['image_principale'])): ?>
                                        <img src="../uploads/produits/<?= $p['image_principale'] ?>" style="width:40px;height:40px;object-fit:cover;border-radius:8px;">
                                    <?php else: ?>
                                        <div style="width:40px;height:40px;background:#F0F2F5;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#ccc;">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($p['nom']) ?></span>
                                </div>
                            </td>
                            <td style="text-align:center;font-weight:700;color:#C8922A;">
                                <?= $p['vendu'] ?>
                            </td>
                        </tr>
                        <?php $rank++; endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Statuts commandes -->
    <div class="card">
        <div class="card-header">
            <span><i class="bi bi-pie-chart"></i> Répartition des commandes</span>
        </div>
        <div class="card-body">
            <div style="max-height:250px;">
                <canvas id="statutChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// Graphique ventes mensuelles (combiné)
const ventesCtx = document.getElementById('ventesChart').getContext('2d');
new Chart(ventesCtx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
        datasets: [
            {
                label: 'Commandes en ligne',
                data: <?= json_encode(array_values($ventes_mois)) ?>,
                backgroundColor: 'rgba(200,146,42,0.8)',
                borderColor: '#C8922A',
                borderWidth: 1,
                borderRadius: 4,
            },
            {
                label: 'Ventes boutique',
                data: <?= json_encode(array_values($ventes_boutique_mois)) ?>,
                backgroundColor: 'rgba(41,128,185,0.8)',
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
                    font: { size: 11 },
                    boxWidth: 12,
                    padding: 15
                }
            },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA'
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => v >= 1000000 ? (v/1000000)+'M' : v >= 1000 ? (v/1000)+'k' : v
                }
            }
        }
    }
});

// Graphique statuts commandes
const statutCtx = document.getElementById('statutChart').getContext('2d');
const statutLabels = <?= json_encode(array_column($stats_statut, 'statut')) ?>;
const statutData = <?= json_encode(array_column($stats_statut, 'nb')) ?>;
const statutColors = {
    'en_attente': '#FFC107',
    'confirmee': '#28A745',
    'en_preparation': '#17A2B8',
    'en_livraison': '#6C757D',
    'livree': '#28A745',
    'annulee': '#DC3545'
};

const colors = statutLabels.map(label => statutColors[label] || '#6C757D');

new Chart(statutCtx, {
    type: 'doughnut',
    data: {
        labels: statutLabels.map(l => l.replace('_', ' ').toUpperCase()),
        datasets: [{
            data: statutData,
            backgroundColor: colors,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: { size: 11 },
                    padding: 15
                }
            }
        }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>