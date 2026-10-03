<?php
// ============================================
// DÉTAIL D'UNE COMMANDE - Awa Ka Sugu
// ============================================

// ============================================
// SESSION PUBLIQUE SÉPARÉE
// ============================================
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// VÉRIFICATION MAINTENANCE
// ============================================
require_once '../includes/maintenance_check.php';

// ============================================
// VÉRIFICATION CONNEXION CLIENT
// ============================================
if (!isset($_SESSION['client_id'])) {
    header('Location: connexion.php');
    exit;
}

$client_id = $_SESSION['client_id'];

// ============================================
// RÉCUPÉRATION DES PARAMÈTRES
// ============================================
$commande_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type_commande = isset($_GET['type']) ? $_GET['type'] : 'boutique';

if ($commande_id <= 0) {
    header('Location: mes_commandes.php');
    exit;
}

// ============================================
// CONNEXION BDD
// ============================================
$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// ============================================
// RÉCUPÉRATION DU CLIENT
// ============================================
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    session_destroy();
    header('Location: connexion.php');
    exit;
}

// ============================================
// RÉCUPÉRATION DE LA COMMANDE
// ============================================
$commande = null;
$details = [];
$type_label = '';

if ($type_commande === 'repas') {
    // ========== COMMANDE REPAS ==========
    try {
        // Vérifier si la table commandes_repas a un client_id
        $has_client_id = false;
        $check = $pdo->query("SHOW COLUMNS FROM commandes_repas LIKE 'client_id'");
        if ($check->rowCount() > 0) $has_client_id = true;
        
        if ($has_client_id) {
            $stmt = $pdo->prepare("SELECT * FROM commandes_repas WHERE id = ? AND client_id = ?");
            $stmt->execute([$commande_id, $client_id]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM commandes_repas WHERE id = ? AND telephone = ?");
            $stmt->execute([$commande_id, $client['telephone'] ?? '']);
        }
        $commande = $stmt->fetch();
        
        if ($commande) {
            // Chercher les détails dans différentes tables possibles
            $tables_details = ['details_commande_repas', 'details_commandes_repas', 'details_repas'];
            foreach ($tables_details as $tbl) {
                try {
                    $check = $pdo->query("SHOW TABLES LIKE '$tbl'");
                    if ($check->rowCount() > 0) {
                        $stmt = $pdo->prepare("SELECT * FROM $tbl WHERE commande_id = ?");
                        $stmt->execute([$commande_id]);
                        $details = $stmt->fetchAll();
                        break;
                    }
                } catch(PDOException $e) { continue; }
            }
        }
    } catch(PDOException $e) {
        $commande = null;
    }
    $type_label = 'Commande Restaurant';
} else {
    // ========== COMMANDE BOUTIQUE ==========
    try {
        $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ? AND (client_id = ? OR telephone = ?)");
        $stmt->execute([$commande_id, $client_id, $client['telephone'] ?? '']);
        $commande = $stmt->fetch();
        
        if ($commande) {
            $stmt = $pdo->prepare("SELECT * FROM details_commande WHERE commande_id = ?");
            $stmt->execute([$commande_id]);
            $details = $stmt->fetchAll();
        }
    } catch(PDOException $e) {
        $commande = null;
    }
    $type_label = 'Commande Boutique';
}

// Si commande introuvable → redirection
if (!$commande) {
    header('Location: mes_commandes.php?error=Commande introuvable');
    exit;
}

// ============================================
// STATUTS
// ============================================
$statuts = [
    'en_attente' => ['label' => 'En attente', 'class' => 'statut-en_attente', 'icon' => 'bi-hourglass-split', 'color' => '#856404'],
    'confirmee' => ['label' => 'Confirmée', 'class' => 'statut-confirmee', 'icon' => 'bi-check-circle', 'color' => '#0C5460'],
    'en_preparation' => ['label' => 'En préparation', 'class' => 'statut-en_preparation', 'icon' => 'bi-tools', 'color' => '#004085'],
    'en_livraison' => ['label' => 'En livraison', 'class' => 'statut-en_livraison', 'icon' => 'bi-truck', 'color' => '#6A1B9A'],
    'livree' => ['label' => 'Livrée', 'class' => 'statut-livree', 'icon' => 'bi-check2-circle', 'color' => '#155724'],
    'terminee' => ['label' => 'Terminée', 'class' => 'statut-terminee', 'icon' => 'bi-check-all', 'color' => '#155724'],
    'annulee' => ['label' => 'Annulée', 'class' => 'statut-annulee', 'icon' => 'bi-x-circle', 'color' => '#721C24']
];

$statut_key = $commande['statut'] ?? 'en_attente';
$statut_info = $statuts[$statut_key] ?? ['label' => $statut_key, 'class' => 'statut-en_attente', 'icon' => 'bi-circle', 'color' => '#666'];

// ============================================
// MODE DE PAIEMENT
// ============================================
$paiements = [
    'livraison' => 'Paiement à la livraison',
    'orange_money' => 'Orange Money',
    'wave' => 'Wave',
    'moov_money' => 'Moov Money',
    'carte' => 'Carte bancaire',
    'especes' => 'Espèces'
];
$mode_paiement = $commande['mode_paiement'] ?? 'livraison';

// ============================================
// HEADER
// ============================================
$titre_page = 'Détail de la commande - Awa Ka Sugu';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail de la commande - Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Jost:wght@300;400;500;600;700&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Jost', sans-serif; 
            background: #F8F7F5; 
            color: #1A1A1A;
        }
        
        /* ===== BANNIÈRE ===== */
        .banner {
            background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
            padding: 45px 20px 35px;
            text-align: center;
        }
        .banner h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: #C8922A;
            font-weight: 700;
        }
        .banner p {
            color: rgba(255,255,255,0.55);
            margin-top: 8px;
            font-size: 0.9rem;
        }
        .banner .order-ref {
            display: inline-block;
            margin-top: 15px;
            padding: 6px 20px;
            background: rgba(200,146,42,0.15);
            border: 1px solid rgba(200,146,42,0.4);
            border-radius: 25px;
            color: #E8B55A;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        /* ===== CONTENEUR ===== */
        .detail-container { 
            max-width: 900px; 
            margin: 40px auto; 
            padding: 0 20px; 
        }
        
        /* ===== CARTE PRINCIPALE ===== */
        .detail-card { 
            background: white; 
            border-radius: 20px; 
            padding: 35px; 
            box-shadow: 0 5px 25px rgba(0,0,0,0.06);
            border: 1px solid rgba(200,146,42,0.1);
            animation: fadeInUp 0.5s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ===== EN-TÊTE STATUT ===== */
        .status-header {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 1px dashed rgba(200,146,42,0.2);
            margin-bottom: 25px;
        }
        .status-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
        }
        .status-icon.statut-en_attente { background: #FFF3CD; color: #856404; }
        .status-icon.statut-confirmee { background: #D1ECF1; color: #0C5460; }
        .status-icon.statut-en_preparation { background: #CCE5FF; color: #004085; }
        .status-icon.statut-en_livraison { background: #E8D5F5; color: #6A1B9A; }
        .status-icon.statut-livree,
        .status-icon.statut-terminee { background: #D4EDDA; color: #155724; }
        .status-icon.statut-annulee { background: #F8D7DA; color: #721C24; }
        
        .status-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: #0D0D0D;
            margin-bottom: 6px;
        }
        .status-header p {
            color: #8A99AA;
            font-size: 0.85rem;
        }
        
        .badge-statut-big {
            display: inline-block;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 10px;
        }
        .badge-statut-big.statut-en_attente { background: #FFF3CD; color: #856404; }
        .badge-statut-big.statut-confirmee { background: #D1ECF1; color: #0C5460; }
        .badge-statut-big.statut-en_preparation { background: #CCE5FF; color: #004085; }
        .badge-statut-big.statut-en_livraison { background: #E8D5F5; color: #6A1B9A; }
        .badge-statut-big.statut-livree,
        .badge-statut-big.statut-terminee { background: #D4EDDA; color: #155724; }
        .badge-statut-big.statut-annulee { background: #F8D7DA; color: #721C24; }
        
        /* ===== SECTION INFO ===== */
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: #0D0D0D;
            margin: 25px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid rgba(200,146,42,0.2);
        }
        .section-title i {
            color: #C8922A;
            margin-right: 8px;
        }
        
        /* ===== LIGNES INFO ===== */
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #F0F2F5;
            font-size: 0.9rem;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-row .label {
            color: #8A99AA;
            width: 180px;
            flex-shrink: 0;
        }
        .info-row .value {
            color: #0D0D0D;
            font-weight: 500;
            flex: 1;
        }
        
        /* ===== TABLEAU PRODUITS ===== */
        .table-produits {
            width: 100%;
            margin-top: 10px;
            font-size: 0.85rem;
            border-collapse: collapse;
        }
        .table-produits th {
            color: #C8922A;
            font-weight: 600;
            border-bottom: 2px solid rgba(200,146,42,0.2);
            padding: 10px 8px;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table-produits td {
            padding: 12px 8px;
            border-bottom: 1px solid #F0F2F5;
            color: #333;
        }
        .table-produits tr:last-child td {
            border-bottom: none;
        }
        .table-produits .produit-nom {
            font-weight: 500;
            color: #0D0D0D;
        }
        
        /* ===== TOTAL ===== */
        .total-box {
            background: linear-gradient(135deg, #FEFBF5, #FFF8EC);
            border-radius: 12px;
            padding: 20px 25px;
            margin-top: 20px;
            border: 1px solid rgba(200,146,42,0.2);
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 0.9rem;
        }
        .total-row.final {
            border-top: 1px solid rgba(200,146,42,0.2);
            margin-top: 8px;
            padding-top: 12px;
            font-size: 1.05rem;
        }
        .total-row.final .label {
            font-weight: 600;
            color: #0D0D0D;
        }
        .total-row.final .value {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #C8922A;
        }
        
        /* ===== ACTIONS ===== */
        .actions-box {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px dashed rgba(200,146,42,0.2);
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 26px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            border: 2px solid transparent;
            cursor: pointer;
        }
        .btn-primary-gold {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: white;
        }
        .btn-primary-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(200,146,42,0.3);
            color: white;
        }
        .btn-outline-gold {
            background: transparent;
            color: #C8922A;
            border-color: #C8922A;
        }
        .btn-outline-gold:hover {
            background: #C8922A;
            color: white;
            transform: translateY(-2px);
        }
        .btn-facture {
            background: #F0F2F5;
            color: #555;
        }
        .btn-facture:hover {
            background: #E0E6ED;
            color: #0D0D0D;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 600px) {
            .detail-card { padding: 20px 15px; }
            .banner h1 { font-size: 1.5rem; }
            .info-row {
                flex-direction: column;
                gap: 3px;
            }
            .info-row .label {
                width: 100%;
                font-size: 0.75rem;
            }
            .table-produits {
                font-size: 0.75rem;
            }
            .table-produits th,
            .table-produits td {
                padding: 8px 4px;
            }
            .actions-box {
                flex-direction: column;
            }
            .btn-action {
                width: 100%;
                justify-content: center;
            }
            .status-icon {
                width: 60px;
                height: 60px;
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>

<!-- ===== BANNIÈRE ===== -->
<div class="banner">
    <h1><?= $type_label ?></h1>
    <p>Détails de votre commande</p>
    <div class="order-ref">
        <i class="bi bi-receipt"></i> 
        #<?= htmlspecialchars($commande['numero_commande'] ?? '#'.str_pad($commande['id'], 6, '0', STR_PAD_LEFT)) ?>
    </div>
</div>

<div class="detail-container">
    <div class="detail-card">
        
        <!-- ===== EN-TÊTE STATUT ===== -->
        <div class="status-header">
            <div class="status-icon <?= $statut_info['class'] ?>">
                <i class="bi <?= $statut_info['icon'] ?>"></i>
            </div>
            <h2><?= $statut_info['label'] ?></h2>
            <p>
                <?php if($statut_key === 'en_attente'): ?>
                    Votre commande est en cours de traitement
                <?php elseif($statut_key === 'confirmee'): ?>
                    Votre commande a été confirmée
                <?php elseif($statut_key === 'en_preparation'): ?>
                    Votre commande est en préparation
                <?php elseif($statut_key === 'en_livraison'): ?>
                    Votre commande est en route
                <?php elseif($statut_key === 'livree'): ?>
                    Votre commande a été livrée
                <?php elseif($statut_key === 'terminee'): ?>
                    Votre commande est terminée
                <?php elseif($statut_key === 'annulee'): ?>
                    Votre commande a été annulée
                <?php endif; ?>
            </p>
        </div>
        
        <!-- ===== INFORMATIONS COMMANDE ===== -->
        <h3 class="section-title"><i class="bi bi-info-circle"></i> Informations</h3>
        
        <div class="info-row">
            <span class="label">Numéro de commande</span>
            <span class="value">#<?= htmlspecialchars($commande['numero_commande'] ?? '#'.str_pad($commande['id'], 6, '0', STR_PAD_LEFT)) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Date de commande</span>
            <span class="value"><?= date('d/m/Y à H:i', strtotime($commande['created_at'] ?? 'now')) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Type</span>
            <span class="value"><?= $type_label ?></span>
        </div>
        <div class="info-row">
            <span class="label">Client</span>
            <span class="value"><?= htmlspecialchars($commande['nom_client'] ?? $client['nom'] ?? '') ?></span>
        </div>
        <?php if(!empty($commande['telephone'])): ?>
        <div class="info-row">
            <span class="label">Téléphone</span>
            <span class="value"><?= htmlspecialchars($commande['telephone']) ?></span>
        </div>
        <?php endif; ?>
        <?php if(!empty($commande['adresse_livraison'])): ?>
        <div class="info-row">
            <span class="label">Adresse de livraison</span>
            <span class="value"><?= nl2br(htmlspecialchars($commande['adresse_livraison'])) ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row">
            <span class="label">Mode de paiement</span>
            <span class="value"><?= htmlspecialchars($paiements[$mode_paiement] ?? $mode_paiement) ?></span>
        </div>
        
        <!-- ===== ARTICLES ===== -->
        <h3 class="section-title"><i class="bi bi-bag-check"></i> Articles commandés</h3>
        
        <?php if(empty($details)): ?>
            <p style="text-align:center;color:#8A99AA;padding:20px;font-size:0.9rem;">
                <i class="bi bi-inbox"></i> Aucun détail disponible pour cette commande.
            </p>
        <?php else: ?>
            <table class="table-produits">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th style="text-align:center;width:80px;">Qté</th>
                        <th style="text-align:right;width:120px;">Prix unitaire</th>
                        <th style="text-align:right;width:120px;">Sous-total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sous_total_calcule = 0;
                    foreach($details as $d): 
                        $prix = $d['prix_unitaire'] ?? $d['prix'] ?? 0;
                        $qte = $d['quantite'] ?? 1;
                        $sous_total = $prix * $qte;
                        $sous_total_calcule += $sous_total;
                    ?>
                    <tr>
                        <td class="produit-nom"><?= htmlspecialchars($d['nom_produit'] ?? $d['nom'] ?? 'Produit') ?></td>
                        <td style="text-align:center;"><?= $qte ?></td>
                        <td style="text-align:right;"><?= number_format($prix, 0, ',', ' ') ?> F</td>
                        <td style="text-align:right;font-weight:600;color:#C8922A;">
                            <?= number_format($sous_total, 0, ',', ' ') ?> F
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- ===== TOTAL ===== -->
            <div class="total-box">
                <?php 
                $frais_livraison = $commande['frais_livraison'] ?? 0;
                $reduction = $commande['reduction'] ?? 0;
                $total_final = $commande['total'] ?? $sous_total_calcule;
                ?>
                <div class="total-row">
                    <span class="label">Sous-total</span>
                    <span class="value"><?= number_format($sous_total_calcule, 0, ',', ' ') ?> FCFA</span>
                </div>
                <?php if($frais_livraison > 0): ?>
                <div class="total-row">
                    <span class="label">Frais de livraison</span>
                    <span class="value">+ <?= number_format($frais_livraison, 0, ',', ' ') ?> FCFA</span>
                </div>
                <?php endif; ?>
                <?php if($reduction > 0): ?>
                <div class="total-row" style="color:#27AE60;">
                    <span class="label">Réduction</span>
                    <span class="value">- <?= number_format($reduction, 0, ',', ' ') ?> FCFA</span>
                </div>
                <?php endif; ?>
                <div class="total-row final">
                    <span class="label">Total à payer</span>
                    <span class="value"><?= number_format($total_final, 0, ',', ' ') ?> FCFA</span>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- ===== ACTIONS ===== -->
        <div class="actions-box">
            <a href="mes_commandes.php" class="btn-action btn-outline-gold">
                <i class="bi bi-arrow-left"></i> Retour à mes commandes
            </a>
            
            <?php if(!empty($commande['numero_commande'])): ?>
            <a href="facture_pdf.php?numero=<?= urlencode($commande['numero_commande']) ?>&type=<?= $type_commande ?>" 
               class="btn-action btn-facture" target="_blank">
                <i class="bi bi-file-pdf"></i> Télécharger la facture
            </a>
            <?php endif; ?>
            
            <a href="../boutique/catalogue.php" class="btn-action btn-primary-gold">
                <i class="bi bi-shop"></i> Continuer mes achats
            </a>
        </div>
        
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>