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

$commande_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($commande_id <= 0) {
    header('Location: commandes.php');
    exit;
}

// Récupérer la commande
$stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: commandes.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DÉTAILS AVEC COULEURS ET TAILLES
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        dc.*,
        p.nom as produit_nom,
        p.image_principale,
        c.nom as couleur_nom_complet,
        c.code_hex as couleur_code,
        t.nom as taille_nom_complet
    FROM details_commande dc
    LEFT JOIN produits p ON p.id = dc.produit_id
    LEFT JOIN couleurs c ON c.id = dc.couleur_id
    LEFT JOIN tailles t ON t.id = dc.taille_id
    WHERE dc.commande_id = ?
");
$stmt->execute([$commande_id]);
$details = $stmt->fetchAll();

// Si aucune couleur/taille trouvée via les ID, essayer avec les noms
if (!empty($details) && empty($details[0]['couleur_nom_complet'])) {
    $stmt = $pdo->prepare("
        SELECT 
            dc.*,
            p.nom as produit_nom,
            p.image_principale,
            dc.couleur_nom as couleur_nom_complet,
            NULL as couleur_code,
            dc.taille_nom as taille_nom_complet
        FROM details_commande dc
        LEFT JOIN produits p ON p.id = dc.produit_id
        WHERE dc.commande_id = ?
    ");
    $stmt->execute([$commande_id]);
    $details = $stmt->fetchAll();
}

// Traitement du changement de statut
if (isset($_GET['changer_statut']) && isset($_GET['statut'])) {
    $new_statut = $_GET['statut'];
    $allowed_statuts = ['en_attente', 'confirmee', 'en_preparation', 'en_livraison', 'livree', 'annulee'];
    if (in_array($new_statut, $allowed_statuts)) {
        $pdo->prepare("UPDATE commandes SET statut = ? WHERE id = ?")->execute([$new_statut, $commande_id]);
        header('Location: commande_detail.php?id=' . $commande_id . '&msg=statut');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail commande - Admin Awa Ka Sugu</title>
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
        
        .statut { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .statut-en_attente { background: #FFF3CD; color: #856404; }
        .statut-confirmee { background: #D1ECF1; color: #0C5460; }
        .statut-en_preparation { background: #CCE5FF; color: #004085; }
        .statut-en_livraison { background: #E8D5F5; color: #6A1B9A; }
        .statut-livree { background: #D4EDDA; color: #155724; }
        .statut-annulee { background: #F8D7DA; color: #721C24; }
        
        .info-box { background: #F8F9FA; padding: 15px 20px; border-radius: 10px; border-left: 3px solid #C8922A; }
        .info-box p { margin-bottom: 6px; font-size: 0.9rem; }
        .info-box strong { color: #C8922A; }
        
        .card { border-radius: 12px; border: 1px solid #E8ECF0; overflow: hidden; }
        .card-header { background: #0D0D0D; color: #C8922A; padding: 15px 20px; font-weight: 600; border: none; }
        .card-header i { margin-right: 8px; }
        .table th { background: #0D0D0D; color: #C8922A; border: none; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .table td { vertical-align: middle; padding: 12px 15px; }
        .table tr:hover td { background: #FEFBF5; }
        
        .table-total { background: #FEFBF5; font-weight: 700; }
        .table-total td { color: #C8922A; font-size: 1.1rem; }
        
        .badge-paiement {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(200,146,42,0.1);
            color: #C8922A;
        }
        
        .badge-couleur {
            display: inline-block;
            padding: 4px 12px 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid #E8ECF0;
            background: #F8F9FA;
        }
        .badge-couleur .color-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
            border: 1px solid rgba(0,0,0,0.1);
        }
        .badge-taille {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            background: #E8ECF0;
            color: #0D0D0D;
            border: 1px solid #D5D8DD;
        }
        .badge-taille i {
            margin-right: 4px;
            font-size: 0.6rem;
        }
        
        .produit-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .produit-cell .thumb {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            background: #F5F3F0;
            border: 1px solid #EEEAE5;
            flex-shrink: 0;
        }
        .produit-cell .info {
            display: flex;
            flex-direction: column;
        }
        .produit-cell .info .nom {
            font-weight: 600;
            color: #1A1A1A;
            font-size: 0.9rem;
        }
        .produit-cell .info .ref {
            font-size: 0.65rem;
            color: #8A99AA;
        }
        
        .btn-group .dropdown-menu { border-radius: 10px; border: 1px solid #E8ECF0; }
        .dropdown-item { padding: 8px 20px; font-size: 0.85rem; }
        .dropdown-item:hover { background: #FEFBF5; color: #C8922A; }
        .dropdown-item.text-danger:hover { background: #FEF3F2; color: #E74C3C; }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .content { margin-left: 0; padding: 20px 16px; }
            .content-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .produit-cell .thumb { width: 40px; height: 40px; }
            .produit-cell .info .nom { font-size: 0.8rem; }
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
        <li><a href="commandes.php" class="active"><i class="bi bi-receipt"></i> Commandes</a></li>
        <li><a href="clients.php"><i class="bi bi-people"></i> Clients</a></li>
        <li><a href="plats.php"><i class="bi bi-cup-hot"></i> Restaurant</a></li>
        <li><a href="videos.php"><i class="bi bi-camera-reels"></i> Vidéos</a></li>
        <li><a href="promotions.php"><i class="bi bi-percent"></i> Promotions</a></li>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
    </ul>
</div>

<div class="content">
    <div class="content-header">
        <div>
            <h2><span>📦</span> Détail de la commande #<?= htmlspecialchars($commande['numero_commande']) ?></h2>
            <div class="breadcrumb">
                <a href="dashboard.php">Administration</a> &gt; 
                <a href="commandes.php">Commandes</a> &gt; 
                Détail
            </div>
        </div>
        <a href="commandes.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Retour aux commandes
        </a>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'statut'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> Statut de la commande mis à jour avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-person"></i> Informations client</div>
                <div class="card-body">
                    <div class="info-box">
                        <p><strong>Nom :</strong> <?= htmlspecialchars($commande['nom_client']) ?></p>
                        <p><strong>Téléphone :</strong> <?= htmlspecialchars($commande['telephone']) ?></p>
                        <p><strong>Email :</strong> <?= htmlspecialchars($commande['email'] ?? 'Non renseigné') ?></p>
                        <p><strong>Adresse :</strong> <?= nl2br(htmlspecialchars($commande['adresse_livraison'] ?? '')) ?></p>
                        <?php if(!empty($commande['commune'])): ?>
                            <p><strong>Commune :</strong> <?= htmlspecialchars($commande['commune']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-info-circle"></i> Informations commande</div>
                <div class="card-body">
                    <div class="info-box">
                        <p><strong>N° commande :</strong> <?= htmlspecialchars($commande['numero_commande']) ?></p>
                        <p><strong>Date :</strong> <?= date('d/m/Y à H:i', strtotime($commande['created_at'])) ?></p>
                        <p><strong>Mode de paiement :</strong> 
                            <?php 
                            $paiements = [
                                'livraison' => '💵 Paiement à la livraison',
                                'orange_money' => '🟠 Orange Money',
                                'wave' => '🌊 Wave'
                            ];
                            $mode = $commande['mode_paiement'] ?? 'livraison';
                            echo '<span class="badge-paiement">' . ($paiements[$mode] ?? $mode) . '</span>';
                            ?>
                        </p>
                        <p><strong>Mode de livraison :</strong> 
                            <?php 
                            $livraisons = [
                                'livraison' => '🚚 Livraison à domicile',
                                'retrait_boutique' => '🏪 Retrait en boutique'
                            ];
                            $mode_liv = $commande['mode_livraison'] ?? 'livraison';
                            echo '<span class="badge-paiement">' . ($livraisons[$mode_liv] ?? $mode_liv) . '</span>';
                            ?>
                        </p>
                        <p><strong>Statut :</strong> 
                            <?php 
                            $statut_labels = [
                                'en_attente' => 'En attente',
                                'confirmee' => 'Confirmée',
                                'en_preparation' => 'En préparation',
                                'en_livraison' => 'En livraison',
                                'livree' => 'Livrée',
                                'annulee' => 'Annulée'
                            ];
                            $statut_key = $commande['statut'] ?? 'en_attente';
                            ?>
                            <span class="statut statut-<?= $statut_key ?>">
                                <?= $statut_labels[$statut_key] ?? $statut_key ?>
                            </span>
                        </p>
                        <p><strong>Total :</strong> <span style="font-size:1.2rem;font-weight:700;color:#C8922A;"><?= number_format($commande['total'], 0, ',', ' ') ?> FCFA</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><i class="bi bi-bag"></i> Articles commandés</div>
        <div class="card-body p-0" style="overflow-x:auto;">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th style="min-width:200px;">Produit</th>
                        <th style="text-align:center;">Quantité</th>
                        <th style="text-align:center;min-width:130px;">Couleur</th>
                        <th style="text-align:center;min-width:90px;">Taille</th>
                        <th style="text-align:right;">Prix unitaire</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($details)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4" style="color:#8A99AA;">
                                <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                                Aucun détail disponible
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($details as $d): 
                            // Récupérer l'image du produit
                            $image_path = '';
                            if (!empty($d['image_principale'])) {
                                $image_name = pathinfo($d['image_principale'], PATHINFO_FILENAME);
                                $dossiers = [
                                    '../uploads/produits/voile/',
                                    'uploads/produits/voile/',
                                    '../uploads/produits/pret a porter femme/',
                                    'uploads/produits/pret a porter femme/',
                                    '../uploads/produits/les tallons/',
                                    'uploads/produits/les tallons/',
                                    '../uploads/produits/fermés/',
                                    'uploads/produits/fermés/',
                                    '../uploads/produits/les turbants/',
                                    'uploads/produits/les turbants/',
                                    '../uploads/produits/les foulards/',
                                    'uploads/produits/les foulards/',
                                    '../uploads/produits/les foullards/',
                                    'uploads/produits/les foullards/',
                                    '../uploads/produits/port-monaie/',
                                    'uploads/produits/port-monaie/',
                                    '../uploads/produits/sacs a mains/',
                                    'uploads/produits/sacs a mains/',
                                    '../uploads/produits/ensemble tallons sacs/',
                                    'uploads/produits/ensemble tallons sacs/',
                                    '../uploads/produits/abayas/',
                                    'uploads/produits/abayas/',
                                    '../uploads/produits/abayas pour enfants/',
                                    'uploads/produits/abayas pour enfants/',
                                    '../uploads/produits/',
                                    'uploads/produits/',
                                ];
                                $extensions = ['', '.jpeg', '.jpg', '.png', '.gif', '.webp'];
                                foreach ($dossiers as $dossier) {
                                    foreach ($extensions as $ext) {
                                        $test_path = $dossier . $image_name . $ext;
                                        if (file_exists($test_path)) {
                                            $image_path = $test_path;
                                            break 2;
                                        }
                                    }
                                }
                            }
                            if (empty($image_path)) {
                                $image_path = 'https://placehold.co/50x50/F5F5F5/C8922A?text=' . urlencode(substr($d['produit_nom'] ?? 'P', 0, 1));
                            }
                            
                            // Récupérer les noms de couleur et taille
                            $couleur_nom = $d['couleur_nom_complet'] ?? $d['couleur_nom'] ?? $d['couleur'] ?? null;
                            $taille_nom = $d['taille_nom_complet'] ?? $d['taille_nom'] ?? $d['taille'] ?? null;
                            $couleur_code = $d['couleur_code'] ?? null;
                            
                            $has_couleur = !empty($couleur_nom);
                            $has_taille = !empty($taille_nom);
                            $total_ligne = ($d['prix_unitaire'] ?? 0) * ($d['quantite'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <div class="produit-cell">
                                    <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($d['produit_nom'] ?? '') ?>" class="thumb" onerror="this.src='https://placehold.co/50x50/F5F5F5/C8922A?text=<?= urlencode(substr($d['produit_nom'] ?? 'P', 0, 1)) ?>'">
                                    <div class="info">
                                        <span class="nom"><?= htmlspecialchars($d['produit_nom'] ?? $d['nom_produit'] ?? 'Produit inconnu') ?></span>
                                        <span class="ref">Réf: #<?= $d['produit_id'] ?? '' ?></span>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:center;font-weight:600;"><?= $d['quantite'] ?></td>
                            <td style="text-align:center;">
                                <?php if($has_couleur): ?>
                                    <span class="badge-couleur">
                                        <span class="color-dot" style="background-color: <?= htmlspecialchars($couleur_code ?? '#CCCCCC') ?>;"></span>
                                        <?= htmlspecialchars($couleur_nom) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.7rem;">Non spécifiée</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if($has_taille): ?>
                                    <span class="badge-taille">
                                        <i class="bi bi-rulers"></i> <?= htmlspecialchars($taille_nom) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.7rem;">Non spécifiée</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;"><?= number_format($d['prix_unitaire'], 0, ',', ' ') ?> FCFA</td>
                            <td style="text-align:right;font-weight:600;color:#C8922A;">
                                <?= number_format($total_ligne, 0, ',', ' ') ?> FCFA
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-total">
                        <td colspan="5" class="text-end"><strong>TOTAL</strong></td>
                        <td style="text-align:right;font-size:1.1rem;">
                            <strong><?= number_format($commande['total'], 0, ',', ' ') ?> FCFA</strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <a href="commandes.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Retour aux commandes
        </a>
        
        <?php if($commande['statut'] != 'livree' && $commande['statut'] != 'annulee'): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-warning dropdown-toggle" style="background:#C8922A;color:white;border:none;padding:10px 25px;font-weight:600;" data-bs-toggle="dropdown">
                    <i class="bi bi-pencil"></i> Changer le statut
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="?id=<?= $commande_id ?>&changer_statut=1&statut=en_attente">📋 En attente</a></li>
                    <li><a class="dropdown-item" href="?id=<?= $commande_id ?>&changer_statut=1&statut=confirmee">✅ Confirmée</a></li>
                    <li><a class="dropdown-item" href="?id=<?= $commande_id ?>&changer_statut=1&statut=en_preparation">🔧 En préparation</a></li>
                    <li><a class="dropdown-item" href="?id=<?= $commande_id ?>&changer_statut=1&statut=en_livraison">🚚 En livraison</a></li>
                    <li><a class="dropdown-item" href="?id=<?= $commande_id ?>&changer_statut=1&statut=livree">📦 Livrée</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="?id=<?= $commande_id ?>&changer_statut=1&statut=annulee" onclick="return confirm('Annuler cette commande ?')">❌ Annuler la commande</a></li>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if($commande['statut'] == 'livree'): ?>
            <span style="color:#27AE60;font-weight:600;"><i class="bi bi-check-circle-fill"></i> Commande livrée</span>
        <?php elseif($commande['statut'] == 'annulee'): ?>
            <span style="color:#E74C3C;font-weight:600;"><i class="bi bi-x-circle-fill"></i> Commande annulée</span>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>