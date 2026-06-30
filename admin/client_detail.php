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
    die("Erreur de connexion : " . $e->getMessage());
}

$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($client_id <= 0) {
    header('Location: clients.php');
    exit;
}

// Récupérer le client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: clients.php');
    exit;
}

// Récupérer les commandes du client
$stmt = $pdo->prepare("
    SELECT * FROM commandes 
    WHERE client_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$client_id]);
$commandes = $stmt->fetchAll();

// Récupérer le total des commandes
$total_commandes = count($commandes);
$total_depense = $client['total_depense'] ?? 0;
$points = $client['points_fidelite'] ?? 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail client - Admin Awa Ka Sugu</title>
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
        
        .btn-back { background: #6C757D; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; }
        .btn-back:hover { background: #5A6268; color: white; }
        
        .card { border-radius: 12px; border: 1px solid #E8ECF0; overflow: hidden; }
        .card-header { background: #0D0D0D; color: #C8922A; padding: 15px 20px; font-weight: 600; border: none; }
        .card-header i { margin-right: 8px; }
        .card-body { padding: 20px; }
        
        .info-item { 
            display: flex; 
            justify-content: space-between; 
            padding: 8px 0; 
            border-bottom: 1px solid #F0F2F5;
        }
        .info-item:last-child { border-bottom: none; }
        .info-item .label { color: #8A99AA; font-size: 0.8rem; }
        .info-item .value { font-weight: 600; color: #0D0D0D; }
        
        .statut { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .statut-en_attente { background: #FFF3CD; color: #856404; }
        .statut-confirmee { background: #D1ECF1; color: #0C5460; }
        .statut-en_preparation { background: #CCE5FF; color: #004085; }
        .statut-en_livraison { background: #E8D5F5; color: #6A1B9A; }
        .statut-livree { background: #D4EDDA; color: #155724; }
        .statut-annulee { background: #F8D7DA; color: #721C24; }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            text-align: center;
            border: 1px solid #E8ECF0;
            height: 100%;
            transition: all 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
        .stat-card .number { 
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem; 
            font-weight: 700; 
            color: #C8922A;
        }
        .stat-card .label { font-size: 0.7rem; color: #8A99AA; margin-top: 5px; }
        
        .btn-sm-view { background: rgba(41,128,185,0.1); color: #2980B9; padding: 5px 12px; border-radius: 6px; text-decoration: none; font-size: 0.75rem; transition: all 0.2s; }
        .btn-sm-view:hover { background: #2980B9; color: white; }
        
        .empty-state { text-align: center; padding: 40px 20px; color: #8A99AA; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 10px; }
        
        .table th { background: #0D0D0D; color: #C8922A; border: none; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .table td { vertical-align: middle; padding: 10px 15px; }
        .table tr:hover td { background: #FEFBF5; }
        
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
        <li><a href="point_de_vente.php"><i class="bi bi-cash-stack"></i> Point de vente</a></li>
        <li><a href="produits.php"><i class="bi bi-box-seam"></i> Produits</a></li>
        <li><a href="achats.php"><i class="bi bi-cart-check"></i> Achats</a></li>
        <li><a href="commandes.php"><i class="bi bi-receipt"></i> Commandes</a></li>
        <li><a href="clients.php" class="active"><i class="bi bi-people"></i> Clients</a></li>
        <li><a href="plats.php"><i class="bi bi-cup-hot"></i> Restaurant</a></li>
        <li><a href="videos.php"><i class="bi bi-camera-reels"></i> Vidéos</a></li>
        <li><a href="promotions.php"><i class="bi bi-percent"></i> Promotions</a></li>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
    </ul>
</div>

<div class="content">
    <div class="content-header">
        <div>
            <h2><span>👤</span> Détail du client</h2>
            <div class="breadcrumb">
                <a href="dashboard.php">Administration</a> &gt; 
                <a href="clients.php">Clients</a> &gt; 
                Détail
            </div>
        </div>
        <a href="clients.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Retour aux clients
        </a>
    </div>

    <div class="row g-4">
        <!-- Statistiques -->
        <div class="col-md-4">
            <div class="stat-card">
                <div class="number"><?= $total_commandes ?></div>
                <div class="label">Commandes passées</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="number"><?= number_format($total_depense, 0, ',', ' ') ?> F</div>
                <div class="label">Total dépensé</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="number"><?= $points ?></div>
                <div class="label">Points fidélité</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Informations personnelles -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-person"></i> Informations personnelles</div>
                <div class="card-body">
                    <div class="info-item">
                        <span class="label">ID client</span>
                        <span class="value">#<?= $client['id'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Nom complet</span>
                        <span class="value"><?= htmlspecialchars($client['nom'] ?? '') ?> <?= htmlspecialchars($client['prenom'] ?? '') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Email</span>
                        <span class="value"><?= !empty($client['email']) ? htmlspecialchars($client['email']) : '<span style="color:#8A99AA;">Non renseigné</span>' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Téléphone</span>
                        <span class="value"><?= htmlspecialchars($client['telephone'] ?? '') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Inscrit le</span>
                        <span class="value"><?= date('d/m/Y à H:i', strtotime($client['created_at'] ?? 'now')) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Adresse -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-geo-alt"></i> Adresse</div>
                <div class="card-body">
                    <div class="info-item">
                        <span class="label">Adresse</span>
                        <span class="value"><?= !empty($client['adresse_complete']) ? htmlspecialchars($client['adresse_complete']) : '<span style="color:#8A99AA;">Non renseignée</span>' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Quartier</span>
                        <span class="value"><?= !empty($client['quartier']) ? htmlspecialchars($client['quartier']) : '<span style="color:#8A99AA;">Non renseigné</span>' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Commune</span>
                        <span class="value"><?= !empty($client['commune']) ? htmlspecialchars($client['commune']) : '<span style="color:#8A99AA;">Non renseignée</span>' ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Commandes -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-receipt"></i> Historique des commandes</span>
            <span style="font-size:0.7rem;color:rgba(255,255,255,0.4);"><?= $total_commandes ?> commande(s)</span>
        </div>
        <div class="card-body p-0" style="overflow-x:auto;">
            <?php if(empty($commandes)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>Aucune commande pour ce client</p>
                </div>
            <?php else: ?>
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>N° commande</th>
                            <th>Date</th>
                            <th style="text-align:right;">Total</th>
                            <th style="text-align:center;">Statut</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($commandes as $c): 
                            $statut_labels = [
                                'en_attente' => 'En attente',
                                'confirmee' => 'Confirmée',
                                'en_preparation' => 'En préparation',
                                'en_livraison' => 'En livraison',
                                'livree' => 'Livrée',
                                'annulee' => 'Annulée'
                            ];
                            $statut_key = $c['statut'] ?? 'en_attente';
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['numero_commande']) ?></strong></td>
                            <td style="color:#8A99AA;font-size:0.85rem;"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                            <td style="text-align:right;font-weight:600;color:#C8922A;">
                                <?= number_format($c['total'], 0, ',', ' ') ?> FCFA
                            </td>
                            <td style="text-align:center;">
                                <span class="statut statut-<?= $statut_key ?>">
                                    <?= $statut_labels[$statut_key] ?? $statut_key ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <a href="commande_detail.php?id=<?= $c['id'] ?>" class="btn-sm-view">
                                    <i class="bi bi-eye"></i> Voir
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>