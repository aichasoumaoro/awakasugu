<?php
// ============================================
// RAPPORTS - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';

// ============================================
// VÉRIFICATION DE CONNEXION
// ============================================
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// ============================================
// RÉCUPÉRATION DES INFOS ADMIN
// ============================================
$admin_info = getAdminInfo();
$admin_role = $admin_info['role'] ?? 'admin';
$admin_nom = $admin_info['nom'] ?? 'Awa Doumbia';
$admin_id = $admin_info['id'] ?? 0;

$page_title = 'Rapports et Statistiques';

// ============================================
// CONNEXION À LA BASE DE DONNÉES
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
// EXPORT EXCEL AVEC DESIGN
// ============================================
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $type = $_GET['type'] ?? 'ventes';
    
    // Définir le nom du fichier
    $filename = "rapport_" . $type . "_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    switch($type) {
        case 'ventes':
            // En-têtes avec design
            fputcsv($output, ['RAPPORT DES VENTES - AWA KA SUGU']);
            fputcsv($output, ['Généré le: ' . date('d/m/Y H:i')]);
            fputcsv($output, []);
            fputcsv($output, ['Date', 'N° Commande', 'Client', 'Téléphone', 'Montant', 'Statut', 'Mode Paiement', 'Adresse']);
            
            // Données
            $stmt = $pdo->query("
                SELECT c.created_at, c.numero_commande, c.nom_client, c.telephone,
                       c.total, c.statut, c.mode_paiement, c.adresse_livraison
                FROM commandes c
                WHERE c.statut != 'annulee'
                ORDER BY c.created_at DESC
                LIMIT 5000
            ");
            $total_ventes = 0;
            while($row = $stmt->fetch()) {
                $total_ventes += $row['total'];
                fputcsv($output, [
                    date('d/m/Y H:i', strtotime($row['created_at'])),
                    $row['numero_commande'],
                    $row['nom_client'] ?? 'Inconnu',
                    $row['telephone'] ?? '',
                    number_format($row['total'], 0, ',', ' ') . ' F',
                    $row['statut'],
                    $row['mode_paiement'] ?? 'Non défini',
                    $row['adresse_livraison'] ?? ''
                ]);
            }
            
            // Totaux
            fputcsv($output, []);
            fputcsv($output, ['TOTAL GÉNÉRAL', '', '', '', number_format($total_ventes, 0, ',', ' ') . ' F', '', '', '']);
            break;
            
        case 'produits':
            fputcsv($output, ['RAPPORT DES PRODUITS - AWA KA SUGU']);
            fputcsv($output, ['Généré le: ' . date('d/m/Y H:i')]);
            fputcsv($output, []);
            fputcsv($output, ['ID', 'Produit', 'Catégorie', 'Prix Achat', 'Prix Vente', 'Marge (FCFA)', 'Marge (%)', 'Stock', 'Valeur Stock']);
            
            $stmt = $pdo->query("
                SELECT p.id, p.nom, c.nom as categorie, 
                       p.prix_achat, p.prix, 
                       (p.prix - p.prix_achat) as marge,
                       CASE WHEN p.prix_achat > 0 THEN ROUND(((p.prix - p.prix_achat) / p.prix_achat) * 100, 2) ELSE 0 END as marge_pct,
                       p.stock,
                       p.prix * p.stock as valeur_stock
                FROM produits p
                LEFT JOIN categories c ON c.id = p.categorie_id
                WHERE p.est_visible = 1
                ORDER BY p.id DESC
            ");
            while($row = $stmt->fetch()) {
                fputcsv($output, [
                    $row['id'],
                    $row['nom'],
                    $row['categorie'] ?? 'Sans catégorie',
                    number_format($row['prix_achat'] ?? 0, 0, ',', ' ') . ' F',
                    number_format($row['prix'], 0, ',', ' ') . ' F',
                    number_format($row['marge'] ?? 0, 0, ',', ' ') . ' F',
                    ($row['marge_pct'] ?? 0) . '%',
                    $row['stock'],
                    number_format($row['valeur_stock'] ?? 0, 0, ',', ' ') . ' F'
                ]);
            }
            break;
            
        case 'clients':
            fputcsv($output, ['RAPPORT DES CLIENTS - AWA KA SUGU']);
            fputcsv($output, ['Généré le: ' . date('d/m/Y H:i')]);
            fputcsv($output, []);
            fputcsv($output, ['ID', 'Nom', 'Téléphone', 'Email', 'Inscription', 'Commandes', 'Total Achats']);
            
            $stmt = $pdo->query("
                SELECT c.id, c.nom, c.telephone, c.email, c.created_at,
                       (SELECT COUNT(*) FROM commandes WHERE client_id = c.id AND statut != 'annulee') as nb_commandes,
                       (SELECT COALESCE(SUM(total), 0) FROM commandes WHERE client_id = c.id AND statut != 'annulee') as total_achats
                FROM clients c
                ORDER BY c.created_at DESC
                LIMIT 500
            ");
            while($row = $stmt->fetch()) {
                fputcsv($output, [
                    $row['id'],
                    $row['nom'],
                    $row['telephone'] ?? '',
                    $row['email'] ?? '',
                    date('d/m/Y', strtotime($row['created_at'])),
                    $row['nb_commandes'],
                    number_format($row['total_achats'] ?? 0, 0, ',', ' ') . ' F'
                ]);
            }
            break;
            
        case 'marges':
            fputcsv($output, ['RAPPORT DES MARGES - AWA KA SUGU']);
            fputcsv($output, ['Généré le: ' . date('d/m/Y H:i')]);
            fputcsv($output, []);
            fputcsv($output, ['Produit', 'Prix Achat', 'Prix Vente', 'Marge (FCFA)', 'Marge (%)', 'Stock', 'Marge Potentielle']);
            
            $stmt = $pdo->query("
                SELECT nom, prix_achat, prix, 
                       (prix - prix_achat) as marge,
                       CASE WHEN prix_achat > 0 THEN ROUND(((prix - prix_achat) / prix_achat) * 100, 2) ELSE 0 END as marge_pct,
                       stock,
                       (prix - prix_achat) * stock as marge_potentielle
                FROM produits 
                WHERE est_visible = 1 AND prix_achat > 0
                ORDER BY marge_potentielle DESC
            ");
            while($row = $stmt->fetch()) {
                fputcsv($output, [
                    $row['nom'],
                    number_format($row['prix_achat'], 0, ',', ' ') . ' F',
                    number_format($row['prix'], 0, ',', ' ') . ' F',
                    number_format($row['marge'], 0, ',', ' ') . ' F',
                    $row['marge_pct'] . '%',
                    $row['stock'],
                    number_format($row['marge_potentielle'], 0, ',', ' ') . ' F'
                ]);
            }
            break;
    }
    
    fclose($output);
    exit;
}

// ============================================
// EXPORT PDF (via impression)
// ============================================
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Rapport Awa Ka Sugu</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Arial', sans-serif; padding: 30px; background: #fff; color: #333; }
            .header { text-align: center; padding-bottom: 20px; border-bottom: 3px solid #C8922A; margin-bottom: 25px; }
            .header h1 { color: #C8922A; font-size: 24px; letter-spacing: 2px; }
            .header p { color: #999; font-size: 12px; margin-top: 5px; }
            .header .sub { color: #666; font-size: 14px; margin-top: 3px; }
            .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
            .summary-box { background: #F8F9FA; padding: 12px 15px; border-radius: 8px; border-left: 4px solid #C8922A; text-align: center; }
            .summary-box .number { font-size: 20px; font-weight: 700; color: #C8922A; }
            .summary-box .label { font-size: 10px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }
            table { width: 100%; border-collapse: collapse; font-size: 11px; }
            th { background: #0D0D0D; color: #fff; padding: 8px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; }
            td { padding: 6px 10px; border-bottom: 1px solid #eee; }
            tr:nth-child(even) td { background: #F8F9FA; }
            .total-row td { font-weight: 700; background: #FEFBF5 !important; border-top: 2px solid #C8922A; }
            .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; text-align: center; color: #999; font-size: 10px; }
            .badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 8px; font-weight: 600; }
            .badge-success { background: #d4edda; color: #155724; }
            .badge-warning { background: #fff3cd; color: #856404; }
            .badge-danger { background: #f8d7da; color: #721c24; }
            .badge-info { background: #d1ecf1; color: #0c5460; }
            @media print {
                body { padding: 15px; }
                .no-print { display: none; }
                .summary-box { background: #f5f5f5; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>✦ AWA KA SUGU ✦</h1>
            <p>Boutique IBA Design & Restaurant Sofia</p>
            <div class="sub">📊 RAPPORT GÉNÉRAL</div>
            <p>Généré le <?= date('d/m/Y à H:i') ?></p>
        </div>

        <?php
        // Statistiques résumées
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_commandes,
                SUM(CASE WHEN statut != 'annulee' THEN total ELSE 0 END) as ca_total,
                SUM(CASE WHEN statut = 'annulee' THEN total ELSE 0 END) as ca_annule,
                SUM(CASE WHEN statut != 'annulee' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN total ELSE 0 END) as ca_mois,
                COUNT(CASE WHEN statut != 'annulee' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 END) as nb_commandes_mois
            FROM commandes
        ");
        $summary = $stmt->fetch();
        ?>

        <div class="summary">
            <div class="summary-box">
                <div class="number"><?= number_format($summary['total_commandes'] ?? 0, 0, ',', ' ') ?></div>
                <div class="label">Total commandes</div>
            </div>
            <div class="summary-box">
                <div class="number"><?= number_format($summary['nb_commandes_mois'] ?? 0, 0, ',', ' ') ?></div>
                <div class="label">Commandes du mois</div>
            </div>
            <div class="summary-box">
                <div class="number"><?= number_format($summary['ca_total'] ?? 0, 0, ',', ' ') ?> F</div>
                <div class="label">CA total</div>
            </div>
            <div class="summary-box" style="border-left-color: <?= $summary['ca_annule'] > 0 ? '#E74C3C' : '#27AE60' ?>;">
                <div class="number" style="color: <?= $summary['ca_annule'] > 0 ? '#E74C3C' : '#27AE60' ?>;">
                    <?= number_format($summary['ca_mois'] ?? 0, 0, ',', ' ') ?> F
                </div>
                <div class="label">CA du mois</div>
            </div>
        </div>

        <?php if($summary['ca_annule'] > 0): ?>
        <div style="background:#FEF3F2;padding:10px 15px;border-radius:8px;margin-bottom:15px;border-left:4px solid #E74C3C;">
            <span style="color:#E74C3C;font-weight:600;">⚠️ Montant annulé : <?= number_format($summary['ca_annule'], 0, ',', ' ') ?> F</span>
            <span style="color:#999;font-size:11px;margin-left:10px;">(exclu du CA total)</span>
        </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>N° Commande</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Paiement</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("
                    SELECT numero_commande, nom_client, created_at, total, statut, mode_paiement
                    FROM commandes 
                    ORDER BY created_at DESC
                    LIMIT 50
                ");
                $rank = 1;
                while($row = $stmt->fetch()): 
                    $is_annulee = ($row['statut'] == 'annulee');
                    $badge_class = $is_annulee ? 'badge-danger' : 'badge-success';
                    $badge_label = $is_annulee ? 'Annulée' : $row['statut'];
                ?>
                <tr style="<?= $is_annulee ? 'opacity:0.6;' : '' ?>">
                    <td><?= $rank++ ?></td>
                    <td><?= htmlspecialchars($row['numero_commande']) ?></td>
                    <td><?= htmlspecialchars($row['nom_client'] ?? 'Inconnu') ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                    <td style="font-weight:600;color:<?= $is_annulee ? '#E74C3C' : '#C8922A' ?>;">
                        <?= number_format($row['total'], 0, ',', ' ') ?> F
                    </td>
                    <td><span class="badge <?= $badge_class ?>"><?= ucfirst($badge_label) ?></span></td>
                    <td><?= ucfirst(str_replace('_', ' ', $row['mode_paiement'] ?? 'N/A')) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="4" style="text-align:right;font-size:12px;">TOTAL GÉNÉRAL</td>
                    <td style="font-size:12px;color:#C8922A;">
                        <?= number_format($summary['ca_total'] ?? 0, 0, ',', ' ') ?> F
                    </td>
                    <td colspan="2"></td>
                </tr>
                <?php if($summary['ca_annule'] > 0): ?>
                <tr class="total-row" style="color:#E74C3C;">
                    <td colspan="4" style="text-align:right;font-size:11px;">Annulé</td>
                    <td style="font-size:11px;color:#E74C3C;"><?= number_format($summary['ca_annule'], 0, ',', ' ') ?> F</td>
                    <td colspan="2"></td>
                </tr>
                <?php endif; ?>
            </tfoot>
        </table>
        
        <div class="footer">
            Awa Ka Sugu &copy; <?= date('Y') ?> - Tous droits réservés<br>
            Rapport généré automatiquement le <?= date('d/m/Y à H:i') ?>
        </div>
        
        <script>
            window.onload = function() { window.print(); }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// FILTRES
// ============================================
$mois = isset($_GET['mois']) ? (int)$_GET['mois'] : date('m');
$annee = isset($_GET['annee']) ? (int)$_GET['annee'] : date('Y');

// ============================================
// STATISTIQUES (EXCLUT LES ANNULÉES)
// ============================================

// Ventes du mois - Commandes en ligne (exclut annulées)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as ca 
    FROM commandes 
    WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND statut != 'annulee'
");
$stmt->execute([$mois, $annee]);
$stats_mois = $stmt->fetch();

// Commandes annulées du mois
$stmt = $pdo->prepare("
    SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as ca 
    FROM commandes 
    WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND statut = 'annulee'
");
$stmt->execute([$mois, $annee]);
$stats_annulees = $stmt->fetch();

// Ventes du mois - Boutique (exclut annulées)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as ca 
    FROM ventes_boutique 
    WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND statut != 'annulee'
");
$stmt->execute([$mois, $annee]);
$stats_boutique = $stmt->fetch();

// Total commandes de l'année (exclut annulées)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as ca 
    FROM commandes 
    WHERE YEAR(created_at) = ? AND statut != 'annulee'
");
$stmt->execute([$annee]);
$stats_annee = $stmt->fetch();

// Top produits (exclut annulées)
$top_produits = $pdo->query("
    SELECT p.nom, p.image_principale, SUM(dc.quantite) as vendu 
    FROM details_commande dc 
    JOIN commandes c ON c.id = dc.commande_id
    JOIN produits p ON p.id = dc.produit_id 
    WHERE c.statut != 'annulee'
    GROUP BY dc.produit_id 
    ORDER BY vendu DESC 
    LIMIT 10
")->fetchAll();

// Commandes par statut
$stats_statut = $pdo->query("SELECT statut, COUNT(*) as nb FROM commandes GROUP BY statut")->fetchAll();

// Ventes par mois (exclut annulées)
$ventes_mois = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) FROM commandes 
        WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND statut != 'annulee'
    ");
    $stmt->execute([$i, $annee]);
    $ventes_mois[$i] = $stmt->fetchColumn();
}

// Ventes boutique par mois
$ventes_boutique_mois = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) FROM ventes_boutique 
        WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND statut != 'annulee'
    ");
    $stmt->execute([$i, $annee]);
    $ventes_boutique_mois[$i] = $stmt->fetchColumn();
}

// Meilleur mois
$meilleur_mois = max($ventes_mois) > 0 ? array_search(max($ventes_mois), $ventes_mois) : 0;
$meilleur_mois_nom = $meilleur_mois > 0 ? date('F', mktime(0,0,0,$meilleur_mois,1)) : 'Aucune vente';

// Total clients
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();

// Total commandes (exclut annulées)
$total_commandes = $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut != 'annulee'")->fetchColumn();

// CA total (exclut annulées)
$ca_total = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE statut != 'annulee'")->fetchColumn();

// CA annulé total
$ca_annule = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE statut = 'annulee'")->fetchColumn();

// ============================================
// STATISTIQUES DE MARGE
// ============================================
$stats_marge = $pdo->query("
    SELECT 
        COALESCE(SUM((prix - prix_achat) * stock), 0) as marge_totale_potentielle,
        COALESCE(SUM(prix_achat * stock), 0) as valeur_stock_achat,
        COALESCE(SUM(prix * stock), 0) as valeur_stock_vente,
        COALESCE(AVG(CASE WHEN prix_achat > 0 THEN (prix - prix_achat) / prix_achat * 100 ELSE 0 END), 0) as marge_moyenne,
        COUNT(CASE WHEN prix_achat > 0 AND (prix - prix_achat) / prix_achat * 100 > 50 THEN 1 END) as nb_marge_elevee,
        COUNT(CASE WHEN prix_achat > 0 AND (prix - prix_achat) / prix_achat * 100 < 10 THEN 1 END) as nb_marge_faible,
        COUNT(CASE WHEN prix_achat IS NULL OR prix_achat <= 0 THEN 1 END) as nb_sans_marge
    FROM produits 
    WHERE est_visible = 1
")->fetch();

// ============================================
// ACHATS DU MOIS
// ============================================
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_ligne), 0) as total_achats FROM achats WHERE MONTH(date_achat) = ? AND YEAR(date_achat) = ?");
$stmt->execute([$mois, $annee]);
$achats_mois = $stmt->fetchColumn();

// ============================================
// INCLUSION DU HEADER ET DE LA SIDEBAR
// ============================================
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="main">

    <!-- ===== TOPBAR ===== -->
    <div class="topbar">
        <div>
            <div class="topbar-title">📊 Rapports & <span>Statistiques</span></div>
            <div class="topbar-breadcrumb">Administration → Rapports</div>
        </div>
        <div class="topbar-right" style="flex-wrap:wrap;gap:6px;">
            <div class="dropdown" style="position:relative;display:inline-block;">
                <button class="btn-admin btn-primary" style="cursor:pointer;">
                    <i class="bi bi-download"></i> Exporter ▼
                </button>
                <div class="dropdown-menu" style="position:absolute;right:0;top:100%;background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.15);padding:6px 0;min-width:180px;display:none;z-index:100;">
                    <a href="?export=excel&type=ventes" class="dropdown-item" style="display:block;padding:8px 16px;text-decoration:none;color:#333;font-size:0.85rem;">
                        <i class="bi bi-file-earmark-excel" style="color:#27AE60;"></i> Ventes (Excel)
                    </a>
                    <a href="?export=excel&type=produits" class="dropdown-item" style="display:block;padding:8px 16px;text-decoration:none;color:#333;font-size:0.85rem;">
                        <i class="bi bi-file-earmark-excel" style="color:#27AE60;"></i> Produits (Excel)
                    </a>
                    <a href="?export=excel&type=clients" class="dropdown-item" style="display:block;padding:8px 16px;text-decoration:none;color:#333;font-size:0.85rem;">
                        <i class="bi bi-file-earmark-excel" style="color:#27AE60;"></i> Clients (Excel)
                    </a>
                    <a href="?export=excel&type=marges" class="dropdown-item" style="display:block;padding:8px 16px;text-decoration:none;color:#333;font-size:0.85rem;">
                        <i class="bi bi-file-earmark-excel" style="color:#27AE60;"></i> Marges (Excel)
                    </a>
                    <div style="border-top:1px solid #eee;margin:4px 0;"></div>
                    <a href="?export=pdf" class="dropdown-item" style="display:block;padding:8px 16px;text-decoration:none;color:#333;font-size:0.85rem;">
                        <i class="bi bi-file-earmark-pdf" style="color:#E74C3C;"></i> PDF (Impression)
                    </a>
                </div>
            </div>
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <script>
    // Dropdown toggle
    document.querySelector('.dropdown .btn-admin').addEventListener('click', function(e) {
        e.preventDefault();
        var menu = this.parentElement.querySelector('.dropdown-menu');
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    });
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
        }
    });
    </script>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <!-- ===== FILTRES ===== -->
        <div class="filter-card" style="background:#fff;border-radius:12px;padding:20px;margin-bottom:20px;border:1px solid #E8ECF0;">
            <form method="GET" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:15px;align-items:end;">
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;"><i class="bi bi-calendar-month"></i> Mois</label>
                    <select name="mois" class="form-control" style="width:100%;padding:10px;border:1.5px solid #E0E0E0;border-radius:8px;">
                        <?php for($i=1; $i<=12; $i++): ?>
                            <option value="<?= $i ?>" <?= $mois == $i ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;"><i class="bi bi-calendar"></i> Année</label>
                    <select name="annee" class="form-control" style="width:100%;padding:10px;border:1.5px solid #E0E0E0;border-radius:8px;">
                        <?php for($i=date('Y'); $i>=2023; $i--): ?>
                            <option value="<?= $i ?>" <?= $annee == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">&nbsp;</label>
                    <button type="submit" class="btn-admin btn-primary" style="width:100%;padding:10px;justify-content:center;">
                        <i class="bi bi-funnel"></i> Filtrer
                    </button>
                </div>
                <div>
                    <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">&nbsp;</label>
                    <a href="rapports.php" class="btn-admin btn-outline" style="width:100%;padding:10px;justify-content:center;border:1.5px solid #ddd;border-radius:8px;text-decoration:none;color:#666;display:flex;align-items:center;gap:6px;">
                        <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>

        <!-- ===== STATISTIQUES GLOBALES ===== -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-box-seam-fill"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_commandes, 0, ',', ' ') ?></div>
                    <div class="stat-lbl">Total commandes</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_clients, 0, ',', ' ') ?></div>
                    <div class="stat-lbl">Total clients</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($ca_total, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">CA total</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-gold"><i class="bi bi-trophy-fill"></i></div>
                <div>
                    <div class="stat-val" style="font-size:1.1rem;"><?= $meilleur_mois_nom ?></div>
                    <div class="stat-lbl">Meilleur mois</div>
                </div>
            </div>
        </div>

        <!-- ===== STATISTIQUES DU MOIS ===== -->
        <div class="stats-row" style="grid-template-columns: repeat(4, 1fr);">
            <div class="stat-box" style="border-left:3px solid #C8922A;">
                <div>
                    <div class="stat-val" style="color:#C8922A;"><?= number_format($stats_mois['nb'] ?? 0, 0, ',', ' ') ?></div>
                    <div class="stat-lbl">Commandes validées</div>
                </div>
            </div>
            <div class="stat-box" style="border-left:3px solid #E74C3C;">
                <div>
                    <div class="stat-val" style="color:#E74C3C;"><?= number_format($stats_annulees['nb'] ?? 0, 0, ',', ' ') ?></div>
                    <div class="stat-lbl">Commandes annulées</div>
                </div>
            </div>
            <div class="stat-box" style="border-left:3px solid #27AE60;">
                <div>
                    <div class="stat-val" style="color:#27AE60;"><?= number_format(($stats_mois['ca'] ?? 0) + ($stats_boutique['ca'] ?? 0), 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">CA validé du mois</div>
                </div>
            </div>
            <div class="stat-box" style="border-left:3px solid #E74C3C;">
                <div>
                    <div class="stat-val" style="color:#E74C3C;"><?= number_format($stats_annulees['ca'] ?? 0, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Montant annulé</div>
                </div>
            </div>
        </div>

        <!-- ===== STATISTIQUES DE MARGE ===== -->
        <div class="stats-row" style="grid-template-columns: repeat(4, 1fr);">
            <div class="stat-box" style="border-left:4px solid #8E44AD;">
                <div>
                    <div class="stat-val" style="color:#8E44AD;font-size:1.1rem;"><?= number_format($stats_marge['marge_moyenne'] ?? 0, 1) ?>%</div>
                    <div class="stat-lbl">Marge moyenne</div>
                </div>
            </div>
            <div class="stat-box" style="border-left:4px solid #27AE60;">
                <div>
                    <div class="stat-val" style="color:#27AE60;font-size:1.1rem;"><?= number_format($stats_marge['marge_totale_potentielle'] ?? 0, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Marge brute potentielle</div>
                </div>
            </div>
            <div class="stat-box" style="border-left:4px solid #2980B9;">
                <div>
                    <div class="stat-val" style="color:#2980B9;font-size:1.1rem;"><?= number_format($stats_marge['valeur_stock_vente'] ?? 0, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Valeur stock (vente)</div>
                </div>
            </div>
            <div class="stat-box" style="border-left:4px solid #E67E22;">
                <div>
                    <div class="stat-val" style="color:#E67E22;font-size:1.1rem;"><?= number_format($stats_marge['valeur_stock_achat'] ?? 0, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Valeur stock (achat)</div>
                </div>
            </div>
        </div>

        <!-- ===== GRAPHIQUE VENTES ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-graph-up"></i> Ventes mensuelles <?= $annee ?></div>
                <div style="font-size:0.7rem;color:#8A99AA;">Commandes validées vs Boutique</div>
            </div>
            <div class="card-body">
                <canvas id="ventesChart" style="height:220px;width:100%;"></canvas>
            </div>
        </div>

        <!-- ===== GRAPHIQUE MARGES ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-percent"></i> Répartition des marges</div>
                <div style="font-size:0.7rem;color:#8A99AA;">
                    <?= ($stats_marge['nb_marge_elevee'] ?? 0) ?> produits avec marge &gt;50%
                </div>
            </div>
            <div class="card-body" style="display:flex;justify-content:center;">
                <div style="max-width:400px;width:100%;">
                    <canvas id="margeChart" style="height:200px;width:100%;"></canvas>
                </div>
            </div>
        </div>

        <!-- ===== TOP PRODUITS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-trophy"></i> Top 10 des produits</div>
                <div style="font-size:0.7rem;color:#8A99AA;"><?= count($top_produits) ?> produits</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-produits">
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
                                    <td colspan="3">
                                        <div class="empty-state">
                                            <i class="bi bi-inbox"></i>
                                            <p>Aucune vente enregistrée</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $rank = 1; foreach($top_produits as $p): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?= $rank <= 3 ? 'rank-'.$rank : 'rank-other' ?>" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-weight:700;font-size:0.8rem;<?= $rank <= 3 ? 'background:#C8922A;color:#fff;' : 'background:#F0F0F0;color:#666;' ?>">
                                            <?= $rank ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:12px;">
                                            <?php if(!empty($p['image_principale']) && file_exists('../uploads/produits/'.$p['image_principale'])): ?>
                                                <img src="../uploads/produits/<?= htmlspecialchars($p['image_principale']) ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover;">
                                            <?php else: ?>
                                                <div style="width:40px;height:40px;background:#F0F2F5;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#ccc;">
                                                    <i class="bi bi-image"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span style="font-weight:500;"><?= htmlspecialchars($p['nom']) ?></span>
                                        </div>
                                    </td>
                                    <td style="text-align:center;font-weight:700;color:#C8922A;font-size:1.1rem;">
                                        <?= $p['vendu'] ?>
                                    </td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== RÉPARTITION STATUTS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-pie-chart"></i> Répartition des commandes</div>
                <div style="font-size:0.7rem;color:#8A99AA;">Par statut</div>
            </div>
            <div class="card-body" style="display:flex;justify-content:center;">
                <div style="max-width:300px;width:100%;">
                    <canvas id="statutChart" style="height:200px;width:100%;"></canvas>
                </div>
            </div>
        </div>

        <!-- ===== PRODUITS SANS MARGE ===== -->
        <?php if(($stats_marge['nb_sans_marge'] ?? 0) > 0): ?>
        <div class="card-white" style="border-left:4px solid #E74C3C;">
            <div class="card-header">
                <div class="card-title" style="color:#E74C3C;">
                    <i class="bi bi-exclamation-triangle-fill"></i> Produits sans prix d'achat
                    <span style="font-size:0.65rem;font-weight:normal;background:rgba(231,76,60,0.1);padding:2px 10px;border-radius:12px;margin-left:8px;color:#E74C3C;">
                        <?= $stats_marge['nb_sans_marge'] ?? 0 ?> produits
                    </span>
                </div>
                <a href="achats.php" class="btn-small red" style="padding:4px 12px;background:#E74C3C;color:#fff;border-radius:4px;text-decoration:none;font-size:0.7rem;">
                    <i class="bi bi-cart-plus"></i> Enregistrer des achats
                </a>
            </div>
            <div class="card-body" style="padding:10px 16px;">
                <p style="color:#666;font-size:0.85rem;">
                    <i class="bi bi-info-circle" style="color:#C8922A;"></i>
                    Pour calculer les marges, vous devez d'abord enregistrer des achats avec les prix d'achat.
                    Allez dans la section <strong>Achats</strong> pour approvisionner vos produits.
                </p>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ============================================
// GRAPHIQUE VENTES MENSUELLES
// ============================================
const ctx = document.getElementById('ventesChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
        datasets: [
            {
                label: 'Commandes validées',
                data: <?= json_encode(array_values($ventes_mois)) ?>,
                backgroundColor: 'rgba(200,146,42,0.75)',
                borderColor: '#C8922A',
                borderWidth: 2,
                borderRadius: 4,
            },
            {
                label: 'Ventes boutique',
                data: <?= json_encode(array_values($ventes_boutique_mois)) ?>,
                backgroundColor: 'rgba(41,128,185,0.75)',
                borderColor: '#2980B9',
                borderWidth: 2,
                borderRadius: 4,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    font: { size: 11, family: 'Inter' },
                    boxWidth: 12,
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle'
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
                    callback: v => v >= 1000000 ? (v/1000000)+'M' : v >= 1000 ? (v/1000)+'k' : v,
                    font: { size: 10 }
                },
                grid: { color: 'rgba(0,0,0,0.04)' }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

// ============================================
// GRAPHIQUE RÉPARTITION STATUTS
// ============================================
const statutCtx = document.getElementById('statutChart').getContext('2d');
const statutLabels = <?= json_encode(array_column($stats_statut, 'statut')) ?>;
const statutData = <?= json_encode(array_column($stats_statut, 'nb')) ?>;
const statutColors = {
    'en_attente': '#FFC107',
    'confirmee': '#28A745',
    'en_preparation': '#17A2B8',
    'en_livraison': '#6C757D',
    'livree': '#27AE60',
    'terminee': '#28A745',
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
            borderWidth: 3,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: { size: 10, family: 'Inter' },
                    padding: 12,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            }
        }
    }
});

// ============================================
// GRAPHIQUE RÉPARTITION DES MARGES
// ============================================
const margeCtx = document.getElementById('margeChart');
if (margeCtx) {
    const margeData = [
        <?= $stats_marge['nb_marge_elevee'] ?? 0 ?>,
        <?= ($stats_marge['nb_marge_elevee'] > 0 ? max(0, (int)($stats_marge['marge_moyenne'] > 0 ? 50 - $stats_marge['nb_marge_elevee'] : 0)) : 0) ?>,
        <?= $stats_marge['nb_marge_faible'] ?? 0 ?>,
        <?= $stats_marge['nb_sans_marge'] ?? 0 ?>
    ];
    
    new Chart(margeCtx, {
        type: 'doughnut',
        data: {
            labels: ['Marge > 50%', 'Marge 10-50%', 'Marge < 10%', 'Sans prix achat'],
            datasets: [{
                data: margeData,
                backgroundColor: ['#27AE60', '#F39C12', '#E74C3C', '#95A5A6'],
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 9, family: 'Inter' },
                        padding: 10,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            }
        }
    });
}
</script>

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>