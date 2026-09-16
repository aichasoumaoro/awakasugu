<?php
// ============================================
// RAPPORTS - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';
require_once '../includes/functions_securite.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$admin_info = getAdminInfo();
$admin_role = $admin_info['role'] ?? 'admin';
$admin_nom = $admin_info['nom'] ?? 'Admin';
$admin_id = $admin_info['id'] ?? 0;

if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Rapports & Statistiques';

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données.");
}

// ============================================
// FILTRES DE PÉRIODE
// ============================================
$periode = $_GET['periode'] ?? 'mois';
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');

switch ($periode) {
    case 'jour':
        $date_debut = date('Y-m-d');
        $date_fin = date('Y-m-d');
        break;
    case 'semaine':
        $date_debut = date('Y-m-d', strtotime('monday this week'));
        $date_fin = date('Y-m-d');
        break;
    case 'mois':
        $date_debut = date('Y-m-01');
        $date_fin = date('Y-m-d');
        break;
    case 'trimestre':
        $trimestre = ceil(date('n') / 3);
        $date_debut = date('Y-' . str_pad(($trimestre - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01');
        $date_fin = date('Y-m-d');
        break;
    case 'annee':
        $date_debut = date('Y-01-01');
        $date_fin = date('Y-m-d');
        break;
}

// ============================================
// STATISTIQUES
// ============================================
$ca_total = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE statut IN ('confirmee', 'livree', 'terminee') AND DATE(created_at) BETWEEN ? AND ?");
$ca_total->execute([$date_debut, $date_fin]);
$ca_commandes = (float)$ca_total->fetchColumn();

$ca_boutique = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM ventes_boutique WHERE statut = 'confirmee' AND DATE(created_at) BETWEEN ? AND ?");
$ca_boutique->execute([$date_debut, $date_fin]);
$ca_ventes_boutique = (float)$ca_boutique->fetchColumn();

$ca_periode = $ca_commandes + $ca_ventes_boutique;

$nb_commandes = $pdo->prepare("SELECT COUNT(*) FROM commandes WHERE statut IN ('confirmee', 'livree', 'terminee') AND DATE(created_at) BETWEEN ? AND ?");
$nb_commandes->execute([$date_debut, $date_fin]);
$total_commandes = (int)$nb_commandes->fetchColumn();

$nb_clients = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE DATE(created_at) BETWEEN ? AND ?");
$nb_clients->execute([$date_debut, $date_fin]);
$nouveaux_clients = (int)$nb_clients->fetchColumn();

$panier_moyen = $total_commandes > 0 ? $ca_commandes / $total_commandes : 0;

$produits_vendus = $pdo->prepare("
    SELECT COALESCE(SUM(dc.quantite), 0) 
    FROM details_commande dc
    JOIN commandes c ON c.id = dc.commande_id
    WHERE c.statut IN ('confirmee', 'livree', 'terminee') 
    AND DATE(c.created_at) BETWEEN ? AND ?
");
$produits_vendus->execute([$date_debut, $date_fin]);
$total_produits_vendus = (int)$produits_vendus->fetchColumn();

// Évolution CA 12 mois
$evolution_ca = [];
$evolution_labels = [];
for ($i = 11; $i >= 0; $i--) {
    $mois = date('Y-m', strtotime("-$i months"));
    $evolution_labels[] = date('M Y', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE statut IN ('confirmee', 'livree', 'terminee') AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$mois]);
    $cmd = (float)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM ventes_boutique WHERE statut = 'confirmee' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$mois]);
    $bout = (float)$stmt->fetchColumn();
    
    $evolution_ca[] = $cmd + $bout;
}

// Top 10 produits
$top_produits = $pdo->prepare("
    SELECT dc.produit_id, dc.nom_produit, SUM(dc.quantite) as qte_vendue, SUM(dc.prix_unitaire * dc.quantite) as ca_total
    FROM details_commande dc
    JOIN commandes c ON c.id = dc.commande_id
    WHERE c.statut IN ('confirmee', 'livree', 'terminee')
    AND DATE(c.created_at) BETWEEN ? AND ?
    GROUP BY dc.produit_id, dc.nom_produit
    ORDER BY qte_vendue DESC
    LIMIT 10
");
$top_produits->execute([$date_debut, $date_fin]);
$top_10_produits = $top_produits->fetchAll();

// Top catégories
$top_categories = $pdo->prepare("
    SELECT cat.nom as categorie, COUNT(DISTINCT c.id) as nb_commandes, COALESCE(SUM(dc.prix_unitaire * dc.quantite), 0) as ca
    FROM commandes c
    JOIN details_commande dc ON dc.commande_id = c.id
    JOIN produits p ON p.id = dc.produit_id
    JOIN categories cat ON cat.id = p.categorie_id
    WHERE c.statut IN ('confirmee', 'livree', 'terminee')
    AND DATE(c.created_at) BETWEEN ? AND ?
    GROUP BY cat.id, cat.nom
    ORDER BY ca DESC
    LIMIT 8
");
$top_categories->execute([$date_debut, $date_fin]);
$top_8_categories = $top_categories->fetchAll();

// Paiements
$paiements = $pdo->prepare("
    SELECT COALESCE(mode_paiement, 'Non défini') as mode, COUNT(*) as nb, COALESCE(SUM(total), 0) as total
    FROM commandes
    WHERE statut IN ('confirmee', 'livree', 'terminee')
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY mode_paiement
    ORDER BY total DESC
");
$paiements->execute([$date_debut, $date_fin]);
$repartition_paiements = $paiements->fetchAll();

// Stats clients
$stats_clients = $pdo->query("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END) as ce_mois,
           SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as aujourdhui
    FROM clients
")->fetch();

// Top clients
$meilleurs_clients = $pdo->prepare("
    SELECT cl.id, cl.nom, cl.telephone, COUNT(c.id) as nb_commandes, COALESCE(SUM(c.total), 0) as total_achats
    FROM clients cl
    LEFT JOIN commandes c ON c.client_id = cl.id AND c.statut IN ('confirmee', 'livree', 'terminee')
    WHERE DATE(c.created_at) BETWEEN ? AND ? OR c.id IS NULL
    GROUP BY cl.id, cl.nom, cl.telephone
    HAVING nb_commandes > 0
    ORDER BY total_achats DESC
    LIMIT 10
");
$meilleurs_clients->execute([$date_debut, $date_fin]);
$top_clients = $meilleurs_clients->fetchAll();

// Statuts
$statuts = $pdo->prepare("SELECT statut, COUNT(*) as nb FROM commandes WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY statut");
$statuts->execute([$date_debut, $date_fin]);
$repartition_statuts = $statuts->fetchAll();

// Comparaison période précédente
$duree = (strtotime($date_fin) - strtotime($date_debut)) / 86400 + 1;
$date_debut_prec = date('Y-m-d', strtotime($date_debut . " -$duree days"));
$date_fin_prec = date('Y-m-d', strtotime($date_fin . " -$duree days"));

$ca_prec = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE statut IN ('confirmee', 'livree', 'terminee') AND DATE(created_at) BETWEEN ? AND ?");
$ca_prec->execute([$date_debut_prec, $date_fin_prec]);
$ca_periode_prec = (float)$ca_prec->fetchColumn();

$evolution_pct = $ca_periode_prec > 0 ? (($ca_periode - $ca_periode_prec) / $ca_periode_prec) * 100 : 0;

$statutLabels = [
    'en_attente' => 'En attente', 'confirmee' => 'Confirmée', 'en_preparation' => 'En préparation',
    'en_livraison' => 'En livraison', 'livree' => 'Livrée', 'terminee' => 'Terminée', 'annulee' => 'Annulée'
];

// ============================================
// EXPORT PDF (page HTML optimisée impression)
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Rapport Awa Ka Sugu — <?= date('d/m/Y') ?></title>
        <style>
            @page { size: A4; margin: 12mm 10mm; }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                color: #1A2C3E;
                font-size: 10pt;
                line-height: 1.4;
            }
            .pdf-header {
                background: linear-gradient(135deg, #0D0D0D 0%, #1A1510 100%);
                color: #fff;
                padding: 24px 28px;
                border-radius: 12px;
                margin-bottom: 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: 2px solid #C8922A;
            }
            .pdf-header-left h1 {
                font-family: 'Georgia', serif;
                font-size: 22pt;
                color: #C8922A;
                letter-spacing: 3px;
                margin-bottom: 6px;
            }
            .pdf-header-left .sub {
                color: rgba(255,255,255,0.6);
                font-size: 8pt;
                letter-spacing: 3px;
                text-transform: uppercase;
            }
            .pdf-header-right { text-align: right; }
            .pdf-header-right .titre {
                font-size: 14pt;
                font-weight: bold;
                color: #E8B55A;
                margin-bottom: 4px;
            }
            .pdf-header-right .dates {
                font-size: 8.5pt;
                color: rgba(255,255,255,0.7);
            }
            .section-title {
                background: linear-gradient(90deg, #C8922A 0%, transparent 100%);
                color: #fff;
                padding: 8px 16px;
                font-size: 11pt;
                font-weight: bold;
                letter-spacing: 1px;
                text-transform: uppercase;
                border-radius: 6px 0 0 6px;
                margin: 20px 0 12px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
                margin-bottom: 8px;
            }
            .kpi-card {
                background: #FAFBFC;
                border: 1px solid #E8ECF0;
                border-left: 4px solid #C8922A;
                border-radius: 8px;
                padding: 12px 14px;
            }
            .kpi-label {
                font-size: 7.5pt;
                color: #8A99AA;
                text-transform: uppercase;
                letter-spacing: 1px;
                font-weight: 600;
                margin-bottom: 6px;
            }
            .kpi-value {
                font-family: 'Georgia', serif;
                font-size: 16pt;
                font-weight: bold;
                color: #1A2C3E;
                margin-bottom: 2px;
            }
            .kpi-sub { font-size: 8pt; color: #8A99AA; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 9pt; }
            thead th {
                background: #0D0D0D;
                color: #E8B55A;
                padding: 8px 12px;
                text-align: left;
                font-size: 8pt;
                text-transform: uppercase;
                letter-spacing: 1px;
                font-weight: 600;
            }
            thead th:first-child { border-radius: 6px 0 0 0; }
            thead th:last-child { border-radius: 0 6px 0 0; }
            tbody td {
                padding: 8px 12px;
                border-bottom: 1px solid #F0F2F5;
                vertical-align: middle;
            }
            tbody tr:nth-child(even) { background: #FAFBFC; }
            tbody tr:last-child td { border-bottom: 2px solid #C8922A; }
            .rank {
                display: inline-block;
                width: 22px;
                height: 22px;
                border-radius: 50%;
                background: #F0F2F5;
                color: #8A99AA;
                text-align: center;
                line-height: 22px;
                font-weight: bold;
                font-size: 8.5pt;
            }
            .rank.gold { background: linear-gradient(135deg, #FFD700, #F9A825); color: #0A0A0F; }
            .rank.silver { background: linear-gradient(135deg, #C0C0C0, #9E9E9E); color: #0A0A0F; }
            .rank.bronze { background: linear-gradient(135deg, #CD7F32, #A67B5B); color: #fff; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .text-gold { color: #C8922A; font-weight: bold; }
            .badge-statut {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 12px;
                font-size: 7.5pt;
                font-weight: 600;
            }
            .statut-en_attente { background: #FFF3CD; color: #856404; }
            .statut-confirmee { background: #D4EDDA; color: #155724; }
            .statut-en_preparation { background: #FFF3CD; color: #856404; }
            .statut-en_livraison { background: #CCE5FF; color: #004085; }
            .statut-livree { background: #D4EDDA; color: #155724; }
            .statut-terminee { background: #D4EDDA; color: #155724; }
            .statut-annulee { background: #F8D7DA; color: #721C24; }
            .ca-box {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                margin-bottom: 12px;
            }
            .ca-item {
                background: #FAFBFC;
                border: 1px solid #E8ECF0;
                border-radius: 8px;
                padding: 12px;
                text-align: center;
            }
            .ca-item .label {
                font-size: 8pt;
                color: #8A99AA;
                text-transform: uppercase;
                margin-bottom: 6px;
            }
            .ca-item .value {
                font-family: 'Georgia', serif;
                font-size: 14pt;
                font-weight: bold;
                color: #C8922A;
            }
            .ca-item.total {
                background: linear-gradient(135deg, #0D0D0D, #1A1510);
                border-color: #C8922A;
            }
            .ca-item.total .label { color: rgba(255,255,255,0.6); }
            .ca-item.total .value { color: #E8B55A; }
            .two-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
            .pdf-footer {
                margin-top: 24px;
                padding-top: 12px;
                border-top: 2px solid #C8922A;
                text-align: center;
                font-size: 8pt;
                color: #8A99AA;
            }
            .pdf-footer strong { color: #C8922A; }
            .no-print { text-align: center; margin: 20px 0; }
            .no-print button {
                background: linear-gradient(135deg, #C8922A, #E8B55A);
                color: #fff;
                border: none;
                padding: 12px 32px;
                border-radius: 30px;
                font-weight: bold;
                font-size: 11pt;
                cursor: pointer;
                margin: 0 6px;
                box-shadow: 0 4px 15px rgba(200,146,42,0.3);
            }
            .no-print .back {
                background: #F0F2F5;
                color: #1A2C3E;
                box-shadow: none;
            }
            @media print {
                .no-print { display: none !important; }
                .section-title { page-break-after: avoid; }
                table { page-break-inside: avoid; }
            }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button onclick="window.print()">Imprimer / Enregistrer en PDF</button>
            <button class="back" onclick="window.history.back()">Retour</button>
        </div>

        <div class="pdf-header">
            <div class="pdf-header-left">
                <h1>✦ AWA KA SUGU ✦</h1>
                <div class="sub">Boutique IBA Design · Restaurant Sofia</div>
            </div>
            <div class="pdf-header-right">
                <div class="titre">RAPPORT D'ACTIVITÉ</div>
                <div class="dates">Du <?= date('d/m/Y', strtotime($date_debut)) ?> au <?= date('d/m/Y', strtotime($date_fin)) ?></div>
            </div>
        </div>

        <div class="section-title">📊 Indicateurs clés de performance</div>
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">CA Total</div>
                <div class="kpi-value"><?= number_format($ca_periode, 0, ',', ' ') ?> F</div>
                <div class="kpi-sub">Commandes + Boutique</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Commandes</div>
                <div class="kpi-value"><?= number_format($total_commandes) ?></div>
                <div class="kpi-sub"><?= number_format($total_produits_vendus) ?> produits vendus</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Panier moyen</div>
                <div class="kpi-value"><?= number_format($panier_moyen, 0, ',', ' ') ?> F</div>
                <div class="kpi-sub">Par commande</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Nouveaux clients</div>
                <div class="kpi-value"><?= number_format($nouveaux_clients) ?></div>
                <div class="kpi-sub">Sur la période</div>
            </div>
        </div>

        <div class="section-title">💰 Répartition du chiffre d'affaires</div>
        <div class="ca-box">
            <div class="ca-item">
                <div class="label">Commandes en ligne</div>
                <div class="value"><?= number_format($ca_commandes, 0, ',', ' ') ?> F</div>
            </div>
            <div class="ca-item">
                <div class="label">Ventes sur place</div>
                <div class="value"><?= number_format($ca_ventes_boutique, 0, ',', ' ') ?> F</div>
            </div>
            <div class="ca-item total">
                <div class="label">Total</div>
                <div class="value"><?= number_format($ca_periode, 0, ',', ' ') ?> F</div>
            </div>
        </div>

        <?php if (!empty($top_10_produits)): ?>
        <div class="section-title">🏆 Top 10 produits les plus vendus</div>
        <table>
            <thead>
                <tr>
                    <th style="width:50px;text-align:center;">Rang</th>
                    <th>Produit</th>
                    <th style="text-align:center;width:100px;">Qté</th>
                    <th style="text-align:right;width:130px;">CA généré</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_10_produits as $i => $p): ?>
                <tr>
                    <td class="text-center">
                        <span class="rank <?= $i == 0 ? 'gold' : ($i == 1 ? 'silver' : ($i == 2 ? 'bronze' : '')) ?>"><?= $i + 1 ?></span>
                    </td>
                    <td><strong><?= htmlspecialchars($p['nom_produit']) ?></strong></td>
                    <td class="text-center"><?= number_format($p['qte_vendue']) ?></td>
                    <td class="text-right text-gold"><?= number_format($p['ca_total'], 0, ',', ' ') ?> F</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="two-cols">
            <?php if (!empty($top_8_categories)): ?>
            <div>
                <div class="section-title">🏷️ Répartition par catégorie</div>
                <table>
                    <thead>
                        <tr>
                            <th>Catégorie</th>
                            <th style="text-align:center;width:70px;">Cdes</th>
                            <th style="text-align:right;width:100px;">CA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_8_categories as $cat): ?>
                        <tr>
                            <td><?= htmlspecialchars($cat['categorie']) ?></td>
                            <td class="text-center"><?= $cat['nb_commandes'] ?></td>
                            <td class="text-right text-gold"><?= number_format($cat['ca'], 0, ',', ' ') ?> F</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($repartition_paiements)): ?>
            <div>
                <div class="section-title">💳 Moyens de paiement</div>
                <table>
                    <thead>
                        <tr>
                            <th>Mode</th>
                            <th style="text-align:center;width:70px;">Nb</th>
                            <th style="text-align:right;width:100px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($repartition_paiements as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['mode']) ?></td>
                            <td class="text-center"><?= $p['nb'] ?></td>
                            <td class="text-right text-gold"><?= number_format($p['total'], 0, ',', ' ') ?> F</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($repartition_statuts)): ?>
        <div class="section-title">📋 Statuts des commandes</div>
        <table>
            <thead>
                <tr>
                    <th>Statut</th>
                    <th style="text-align:center;width:100px;">Nombre</th>
                    <th style="text-align:right;width:100px;">Pourcentage</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_statuts = array_sum(array_column($repartition_statuts, 'nb'));
                foreach ($repartition_statuts as $s): 
                    $pct = $total_statuts > 0 ? round(($s['nb'] / $total_statuts) * 100, 1) : 0;
                ?>
                <tr>
                    <td><span class="badge-statut statut-<?= $s['statut'] ?>"><?= $statutLabels[$s['statut']] ?? $s['statut'] ?></span></td>
                    <td class="text-center"><strong><?= $s['nb'] ?></strong></td>
                    <td class="text-right"><?= $pct ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (!empty($top_clients)): ?>
        <div class="section-title">👑 Meilleurs clients</div>
        <table>
            <thead>
                <tr>
                    <th style="width:50px;text-align:center;">Rang</th>
                    <th>Client</th>
                    <th>Téléphone</th>
                    <th style="text-align:center;width:80px;">Cdes</th>
                    <th style="text-align:right;width:130px;">Total achats</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_clients as $i => $c): ?>
                <tr>
                    <td class="text-center">
                        <span class="rank <?= $i == 0 ? 'gold' : ($i == 1 ? 'silver' : ($i == 2 ? 'bronze' : '')) ?>"><?= $i + 1 ?></span>
                    </td>
                    <td><strong><?= htmlspecialchars($c['nom']) ?></strong></td>
                    <td><?= htmlspecialchars($c['telephone'] ?? '-') ?></td>
                    <td class="text-center"><?= $c['nb_commandes'] ?></td>
                    <td class="text-right text-gold"><?= number_format($c['total_achats'], 0, ',', ' ') ?> F</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="section-title">👥 Statistiques clients</div>
        <div class="ca-box">
            <div class="ca-item">
                <div class="label">Total clients</div>
                <div class="value"><?= number_format($stats_clients['total'] ?? 0) ?></div>
            </div>
            <div class="ca-item">
                <div class="label">Ce mois</div>
                <div class="value"><?= number_format($stats_clients['ce_mois'] ?? 0) ?></div>
            </div>
            <div class="ca-item">
                <div class="label">Aujourd'hui</div>
                <div class="value"><?= number_format($stats_clients['aujourdhui'] ?? 0) ?></div>
            </div>
        </div>

        <div class="pdf-footer">
            <strong>✦ AWA KA SUGU — IBA Design & Restaurant Sofia ✦</strong><br>
            Rapport généré le <?= date('d/m/Y à H:i') ?> · Document confidentiel<br>
            &copy; <?= date('Y') ?> Awa Ka Sugu — Tous droits réservés
        </div>

    </body>
    </html>
    <?php
    exit;
}

// ============================================
// EXPORT EXCEL (.xlsx via HTML table formaté)
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="Rapport_AwaKaSugu_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" 
          xmlns:x="urn:schemas-microsoft-com:office:excel" 
          xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="UTF-8">
        <!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>Rapport Awa Ka Sugu</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                            <x:FreezePanes/>
                            <x:SplitHorizontal>5</x:SplitHorizontal>
                            <x:TopRowBottomPane>5</x:SplitHorizontal>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; }
            td, th { 
                font-family: Calibri, Arial, sans-serif; 
                font-size: 11pt; 
                padding: 8px 12px; 
                vertical-align: middle;
                border: 1px solid #D0D0D0;
            }
            th { 
                background-color: #1A1510; 
                color: #E8B55A; 
                font-weight: bold; 
                text-align: center;
                font-size: 10pt;
                text-transform: uppercase;
            }
            .title {
                background-color: #C8922A;
                color: white;
                font-size: 18pt;
                font-weight: bold;
                text-align: center;
                padding: 16px;
                border: 2px solid #8A5E10;
            }
            .subtitle {
                background-color: #0D0D0D;
                color: #E8B55A;
                font-weight: bold;
                font-size: 12pt;
                padding: 10px 14px;
                text-align: left;
            }
            .section-row {
                background-color: #C8922A;
                color: white;
                font-weight: bold;
                font-size: 12pt;
                padding: 10px 14px;
                border: 1px solid #8A5E10;
            }
            .kpi-label { 
                background-color: #FAFBFC; 
                color: #8A99AA; 
                font-size: 9pt; 
                text-transform: uppercase;
                font-weight: 600;
            }
            .kpi-value { 
                background-color: #FFF8E1; 
                color: #C8922A; 
                font-weight: bold; 
                font-size: 14pt; 
                text-align: center;
            }
            .total-row { 
                background-color: #C8922A; 
                color: white;
                font-weight: bold; 
                font-size: 12pt;
            }
            .gold { color: #C8922A; font-weight: bold; }
            .center { text-align: center; }
            .right { text-align: right; }
            .rank-1 { background-color: #FFD700; color: #0A0A0F; font-weight: bold; text-align: center; }
            .rank-2 { background-color: #C0C0C0; color: #0A0A0F; font-weight: bold; text-align: center; }
            .rank-3 { background-color: #CD7F32; color: white; font-weight: bold; text-align: center; }
            .rank-other { background-color: #F0F2F5; color: #8A99AA; font-weight: bold; text-align: center; }
            .empty-cell { border: none; }
        </style>
    </head>
    <body>
        <table>
            <!-- EN-TÊTE -->
            <tr><td colspan="5" class="title">✦ AWA KA SUGU ✦</td></tr>
            <tr><td colspan="5" class="subtitle" style="text-align:center;font-size:13pt;">
                RAPPORT D'ACTIVITÉ — Du <?= date('d/m/Y', strtotime($date_debut)) ?> au <?= date('d/m/Y', strtotime($date_fin)) ?>
            </td></tr>
            <tr><td colspan="5" class="empty-cell" style="height:12px;"></td></tr>

            <!-- KPIs -->
            <tr><td colspan="5" class="section-row">📊 INDICATEURS CLÉS DE PERFORMANCE</td></tr>
            <tr>
                <td class="kpi-label">CA Total</td>
                <td class="kpi-value"><?= number_format($ca_periode, 0, ',', ' ') ?> FCFA</td>
                <td class="kpi-label">Commandes</td>
                <td class="kpi-value"><?= $total_commandes ?></td>
                <td class="kpi-label">Produits vendus</td>
            </tr>
            <tr>
                <td class="kpi-label">Panier moyen</td>
                <td class="kpi-value"><?= number_format($panier_moyen, 0, ',', ' ') ?> FCFA</td>
                <td class="kpi-label">Nouveaux clients</td>
                <td class="kpi-value"><?= $nouveaux_clients ?></td>
                <td class="kpi-value"><?= $total_produits_vendus ?></td>
            </tr>
            <tr><td colspan="5" class="empty-cell" style="height:12px;"></td></tr>

            <!-- RÉPARTITION CA -->
            <tr><td colspan="5" class="section-row">💰 RÉPARTITION DU CHIFFRE D'AFFAIRES</td></tr>
            <tr>
                <th>Type</th>
                <th colspan="4">Montant</th>
            </tr>
            <tr>
                <td>Commandes en ligne</td>
                <td colspan="4" class="right gold"><?= number_format($ca_commandes, 0, ',', ' ') ?> FCFA</td>
            </tr>
            <tr>
                <td>Ventes sur place</td>
                <td colspan="4" class="right gold"><?= number_format($ca_ventes_boutique, 0, ',', ' ') ?> FCFA</td>
            </tr>
            <tr class="total-row">
                <td>TOTAL</td>
                <td colspan="4" class="right"><?= number_format($ca_periode, 0, ',', ' ') ?> FCFA</td>
            </tr>
            <tr><td colspan="5" class="empty-cell" style="height:12px;"></td></tr>

            <!-- TOP PRODUITS -->
            <?php if (!empty($top_10_produits)): ?>
            <tr><td colspan="5" class="section-row">🏆 TOP 10 PRODUITS LES PLUS VENDUS</td></tr>
            <tr>
                <th class="center" style="width:60px;">Rang</th>
                <th>Produit</th>
                <th class="center" style="width:120px;">Qté vendue</th>
                <th class="right" style="width:150px;">CA généré</th>
                <th></th>
            </tr>
            <?php foreach ($top_10_produits as $i => $p): ?>
            <tr>
                <td class="<?= $i == 0 ? 'rank-1' : ($i == 1 ? 'rank-2' : ($i == 2 ? 'rank-3' : 'rank-other')) ?>"><?= $i + 1 ?></td>
                <td style="font-weight:bold;"><?= htmlspecialchars($p['nom_produit']) ?></td>
                <td class="center"><?= number_format($p['qte_vendue']) ?></td>
                <td class="right gold"><?= number_format($p['ca_total'], 0, ',', ' ') ?> FCFA</td>
                <td></td>
            </tr>
            <?php endforeach; ?>
            <tr><td colspan="5" class="empty-cell" style="height:12px;"></td></tr>
            <?php endif; ?>

            <!-- TOP CATÉGORIES -->
            <?php if (!empty($top_8_categories)): ?>
            <tr><td colspan="5" class="section-row">🏷️ RÉPARTITION PAR CATÉGORIE</td></tr>
            <tr>
                <th>Catégorie</th>
                <th class="center">Nb commandes</th>
                <th class="right">CA</th>
                <th colspan="2"></th>
            </tr>
            <?php foreach ($top_8_categories as $cat): ?>
            <tr>
                <td><?= htmlspecialchars($cat['categorie']) ?></td>
                <td class="center"><?= $cat['nb_commandes'] ?></td>
                <td class="right gold"><?= number_format($cat['ca'], 0, ',', ' ') ?> FCFA</td>
                <td colspan="2"></td>
            </tr>
            <?php endforeach; ?>
            <tr><td colspan="5" class="empty-cell" style="height:12px;"></td></tr>
            <?php endif; ?>

            <!-- PAIEMENTS -->
            <?php if (!empty($repartition_paiements)): ?>
            <tr><td colspan="5" class="section-row">💳 MOYENS DE PAIEMENT</td></tr>
            <tr>
                <th>Mode de paiement</th>
                <th class="center">Nombre</th>
                <th class="right">Total</th>
                <th colspan="2"></th>
            </tr>
            <?php foreach ($repartition_paiements as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['mode']) ?></td>
                <td class="center"><?= $p['nb'] ?></td>
                <td class="right gold"><?= number_format($p['total'], 0, ',', ' ') ?> FCFA</td>
                <td colspan="2"></td>
            </tr>
            <?php endforeach; ?>
            <tr><td colspan="5" class="empty-cell" style="height:12px;"></td></tr>
            <?php endif; ?>

            <!-- STATUTS -->
            <?php if (!empty($repartition_statuts)): ?>
            <tr><td colspan="5" class="section-row">📋 STATUTS DES COMMANDES</td></tr>
            <tr>
                <th>Statut</th>
                <th class="center">Nombre</th>
                <th class="right">Pourcentage</th>
                <th colspan="2"></th>
            </tr>
            <?php 
            $total_statuts = array_sum(array_column($repartition_statuts, 'nb'));
            foreach ($repartition_statuts as $s): 
                $pct = $total_statuts > 0 ? round(($s['nb'] / $total_statuts) * 100, 1) : 0;
            ?>
            <tr>
                <td><?= $statutLabels[$s['statut']] ?? $s['statut'] ?></td>
                <td class="center"><?= $s['nb'] ?></td>
                <td class="right"><?= $pct ?>%</td>
                <td colspan="2"></td>
            </tr>
            <?php endforeach; ?>
            <tr><td colspan="5" class="empty-cell" style="height:12px;"></td></tr>
            <?php endif; ?>

            <!-- TOP CLIENTS -->
            <?php if (!empty($top_clients)): ?>
            <tr><td colspan="5" class="section-row">👑 MEILLEURS CLIENTS</td></tr>
            <tr>
                <th class="center" style="width:60px;">Rang</th>
                <th>Client</th>
                <th>Téléphone</th>
                <th class="center" style="width:80px;">Commandes</th>
                <th class="right" style="width:150px;">Total achats</th>
            </tr>
            <?php foreach ($top_clients as $i => $c): ?>
            <tr>
                <td class="<?= $i == 0 ? 'rank-1' : ($i == 1 ? 'rank-2' : ($i == 2 ? 'rank-3' : 'rank-other')) ?>"><?= $i + 1 ?></td>
                <td style="font-weight:bold;"><?= htmlspecialchars($c['nom']) ?></td>
                <td><?= htmlspecialchars($c['telephone'] ?? '-') ?></td>
                <td class="center"><?= $c['nb_commandes'] ?></td>
                <td class="right gold"><?= number_format($c['total_achats'], 0, ',', ' ') ?> FCFA</td>
            </tr>
            <?php endforeach; ?>
            <tr><td colspan="5" class="empty-cell" style="height:12px;"></td></tr>
            <?php endif; ?>

            <!-- STATS CLIENTS -->
            <tr><td colspan="5" class="section-row">👥 STATISTIQUES CLIENTS GLOBALES</td></tr>
            <tr>
                <td class="kpi-label">Total clients</td>
                <td class="kpi-value"><?= number_format($stats_clients['total'] ?? 0) ?></td>
                <td class="kpi-label">Ce mois</td>
                <td class="kpi-value"><?= number_format($stats_clients['ce_mois'] ?? 0) ?></td>
                <td class="kpi-label">Aujourd'hui</td>
            </tr>
            <tr>
                <td colspan="4"></td>
                <td class="kpi-value"><?= number_format($stats_clients['aujourdhui'] ?? 0) ?></td>
            </tr>
            <tr><td colspan="5" class="empty-cell" style="height:20px;"></td></tr>

            <!-- FOOTER -->
            <tr>
                <td colspan="5" style="text-align:center;padding:16px;background:#F0F2F5;color:#8A99AA;font-size:10pt;">
                    ✦ Rapport généré le <?= date('d/m/Y à H:i') ?> · Awa Ka Sugu · Document confidentiel ✦
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    exit;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">Rapports & <span>Statistiques</span></div>
            <div class="topbar-breadcrumb">Administration → Rapports</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" class="btn-admin btn-site" target="_blank">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <div class="content">

        <!-- FILTRES -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-calendar-range"></i> Période d'analyse
                </div>
            </div>
            <div class="card-body">
                <div class="periode-tabs">
                    <a href="?periode=jour" class="periode-tab <?= $periode == 'jour' ? 'active' : '' ?>"><i class="bi bi-calendar-day"></i> Aujourd'hui</a>
                    <a href="?periode=semaine" class="periode-tab <?= $periode == 'semaine' ? 'active' : '' ?>"><i class="bi bi-calendar-week"></i> Semaine</a>
                    <a href="?periode=mois" class="periode-tab <?= $periode == 'mois' ? 'active' : '' ?>"><i class="bi bi-calendar-month"></i> Mois</a>
                    <a href="?periode=trimestre" class="periode-tab <?= $periode == 'trimestre' ? 'active' : '' ?>"><i class="bi bi-calendar3"></i> Trimestre</a>
                    <a href="?periode=annee" class="periode-tab <?= $periode == 'annee' ? 'active' : '' ?>"><i class="bi bi-calendar"></i> Année</a>
                </div>
                
                <form method="GET" class="date-form">
                    <input type="hidden" name="periode" value="personnalise">
                    <div class="date-input-group">
                        <label><i class="bi bi-calendar-event"></i> Du</label>
                        <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
                    </div>
                    <div class="date-input-group">
                        <label><i class="bi bi-calendar-event"></i> Au</label>
                        <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">
                    </div>
                    <button type="submit" class="btn-admin btn-primary">
                        <i class="bi bi-filter"></i> Filtrer
                    </button>
                </form>
                
                <div class="periode-info">
                    <i class="bi bi-info-circle"></i>
                    Analyse du <strong><?= date('d/m/Y', strtotime($date_debut)) ?></strong> au <strong><?= date('d/m/Y', strtotime($date_fin)) ?></strong>
                    <span class="badge-evolution <?= $evolution_pct >= 0 ? 'positive' : 'negative' ?>">
                        <i class="bi bi-arrow-<?= $evolution_pct >= 0 ? 'up' : 'down' ?>-right"></i>
                        <?= number_format(abs($evolution_pct), 1) ?>% vs période précédente
                    </span>
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">CA Total</span>
                    <div class="stat-icon ic-gold"><i class="bi bi-cash-stack"></i></div>
                </div>
                <div class="stat-val"><?= number_format($ca_periode, 0, ',', ' ') ?> F</div>
                <div class="stat-lbl">Commandes + Ventes boutique</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Commandes</span>
                    <div class="stat-icon ic-blue"><i class="bi bi-bag-check"></i></div>
                </div>
                <div class="stat-val"><?= number_format($total_commandes) ?></div>
                <div class="stat-lbl"><?= number_format($total_produits_vendus) ?> produits vendus</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Panier moyen</span>
                    <div class="stat-icon ic-green"><i class="bi bi-cart-check"></i></div>
                </div>
                <div class="stat-val"><?= number_format($panier_moyen, 0, ',', ' ') ?> F</div>
                <div class="stat-lbl">Par commande</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Nouveaux clients</span>
                    <div class="stat-icon ic-or"><i class="bi bi-people"></i></div>
                </div>
                <div class="stat-val"><?= number_format($nouveaux_clients) ?></div>
                <div class="stat-lbl">Sur la période</div>
            </div>
        </div>

        <!-- EXPORT -->
        <div class="card-white export-card">
            <div class="card-body">
                <div class="export-header">
                    <i class="bi bi-download"></i>
                    <div>
                        <div class="export-title">Exporter le rapport</div>
                        <div class="export-sub">Téléchargez ou imprimez votre rapport</div>
                    </div>
                </div>
                <div class="export-buttons">
                    <a href="?export=pdf&periode=<?= $periode ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>" 
                       target="_blank" 
                       class="export-btn export-pdf">
                        <i class="bi bi-file-earmark-pdf-fill"></i>
                        <div>
                            <strong>PDF / Imprimer</strong>
                            <span>Document professionnel</span>
                        </div>
                    </a>
                    <a href="?export=excel&periode=<?= $periode ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>" 
                       class="export-btn export-excel">
                        <i class="bi bi-file-earmark-excel-fill"></i>
                        <div>
                            <strong>Excel (.xls)</strong>
                            <span>Tableur organisé</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- CA + GRAPHIQUE -->
        <div class="dashboard-grid">
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-pie-chart-fill"></i> Répartition du CA</div>
                </div>
                <div class="card-body">
                    <div class="ca-detail-row">
                        <span><i class="bi bi-bag"></i> Commandes en ligne</span>
                        <strong><?= number_format($ca_commandes, 0, ',', ' ') ?> F</strong>
                    </div>
                    <div class="ca-detail-row">
                        <span><i class="bi bi-shop"></i> Ventes sur place</span>
                        <strong><?= number_format($ca_ventes_boutique, 0, ',', ' ') ?> F</strong>
                    </div>
                    <div class="ca-detail-row total">
                        <span><i class="bi bi-cash"></i> Total</span>
                        <strong><?= number_format($ca_periode, 0, ',', ' ') ?> F</strong>
                    </div>
                </div>
            </div>

            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-graph-up-arrow"></i> Évolution CA (12 mois)</div>
                </div>
                <div class="card-body">
                    <canvas id="evolutionChart" style="max-height:220px;"></canvas>
                </div>
            </div>
        </div>

        <!-- TOP PRODUITS -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-trophy-fill" style="color:#FFD700;"></i> Top 10 produits les plus vendus
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($top_10_produits)): ?>
                    <div class="empty-state"><i class="bi bi-box-seam"></i><p>Aucun produit vendu</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-rapport">
                            <thead>
                                <tr>
                                    <th style="width:60px;text-align:center;">Rang</th>
                                    <th>Produit</th>
                                    <th style="text-align:center;">Qté vendue</th>
                                    <th style="text-align:right;">CA généré</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_10_produits as $i => $p): ?>
                                <tr>
                                    <td style="text-align:center;">
                                        <span class="rank-badge rank-<?= $i < 3 ? ($i + 1) : 'other' ?>"><?= $i + 1 ?></span>
                                    </td>
                                    <td class="fw-600"><?= htmlspecialchars($p['nom_produit']) ?></td>
                                    <td style="text-align:center;"><span class="qte-badge"><?= number_format($p['qte_vendue']) ?></span></td>
                                    <td style="text-align:right;" class="text-gold fw-600"><?= number_format($p['ca_total'], 0, ',', ' ') ?> F</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TOP CATÉGORIES -->
        <?php if (!empty($top_8_categories)): ?>
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-tags-fill"></i> Répartition par catégorie</div>
            </div>
            <div class="card-body">
                <?php 
                $max_ca_cat = max(array_column($top_8_categories, 'ca'));
                foreach ($top_8_categories as $cat): 
                    $pct = $max_ca_cat > 0 ? ($cat['ca'] / $max_ca_cat) * 100 : 0;
                ?>
                <div class="hbar-item">
                    <div class="hbar-top">
                        <span><?= htmlspecialchars($cat['categorie']) ?></span>
                        <span><?= number_format($cat['ca'], 0, ',', ' ') ?> F · <?= $cat['nb_commandes'] ?> cde<?= $cat['nb_commandes'] > 1 ? 's' : '' ?></span>
                    </div>
                    <div class="hbar-track"><div class="hbar-fill" style="width:<?= $pct ?>%;"></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- PAIEMENTS + STATUTS -->
        <div class="dashboard-grid">
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-credit-card-fill"></i> Moyens de paiement</div>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if (empty($repartition_paiements)): ?>
                        <div class="empty-state"><i class="bi bi-credit-card"></i><p>Aucun paiement</p></div>
                    <?php else: ?>
                        <?php foreach ($repartition_paiements as $p): ?>
                        <div class="list-row">
                            <div class="list-row-left">
                                <div class="list-icon ic-blue"><i class="bi bi-credit-card"></i></div>
                                <div>
                                    <div class="list-name"><?= htmlspecialchars($p['mode']) ?></div>
                                    <div class="list-sub"><?= $p['nb'] ?> paiement<?= $p['nb'] > 1 ? 's' : '' ?></div>
                                </div>
                            </div>
                            <div class="list-val"><?= number_format($p['total'], 0, ',', ' ') ?> F</div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-clipboard-check-fill"></i> Statuts des commandes</div>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if (empty($repartition_statuts)): ?>
                        <div class="empty-state"><i class="bi bi-clipboard"></i><p>Aucune commande</p></div>
                    <?php else: 
                        foreach ($repartition_statuts as $s): ?>
                        <div class="list-row">
                            <div class="list-row-left">
                                <span class="badge-statut statut-<?= $s['statut'] ?>">
                                    <?= $statutLabels[$s['statut']] ?? $s['statut'] ?>
                                </span>
                            </div>
                            <div class="list-val"><?= number_format($s['nb']) ?></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- TOP CLIENTS -->
        <?php if (!empty($top_clients)): ?>
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-award-fill" style="color:#FFD700;"></i> Meilleurs clients
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                    <table class="table-rapport">
                        <thead>
                            <tr>
                                <th style="width:60px;text-align:center;">Rang</th>
                                <th>Client</th>
                                <th>Téléphone</th>
                                <th style="text-align:center;">Cdes</th>
                                <th style="text-align:right;">Total achats</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_clients as $i => $c): ?>
                            <tr>
                                <td style="text-align:center;">
                                    <span class="rank-badge rank-<?= $i < 3 ? ($i + 1) : 'other' ?>"><?= $i + 1 ?></span>
                                </td>
                                <td>
                                    <div class="client-cell">
                                        <div class="client-avatar"><?= strtoupper(mb_substr($c['nom'], 0, 1)) ?></div>
                                        <span class="fw-600"><?= htmlspecialchars($c['nom']) ?></span>
                                    </div>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($c['telephone'] ?? '-') ?></td>
                                <td style="text-align:center;"><span class="qte-badge"><?= $c['nb_commandes'] ?></span></td>
                                <td style="text-align:right;" class="text-gold fw-600"><?= number_format($c['total_achats'], 0, ',', ' ') ?> F</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- STATS CLIENTS -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-people-fill"></i> Statistiques clients globales</div>
            </div>
            <div class="card-body">
                <div class="mini-stats">
                    <div class="mini-stat">
                        <div class="mini-stat-icon ic-gold"><i class="bi bi-people"></i></div>
                        <div>
                            <div class="mini-stat-val"><?= number_format($stats_clients['total'] ?? 0) ?></div>
                            <div class="mini-stat-lbl">Total clients</div>
                        </div>
                    </div>
                    <div class="mini-stat">
                        <div class="mini-stat-icon ic-green"><i class="bi bi-calendar-month"></i></div>
                        <div>
                            <div class="mini-stat-val"><?= number_format($stats_clients['ce_mois'] ?? 0) ?></div>
                            <div class="mini-stat-lbl">Ce mois</div>
                        </div>
                    </div>
                    <div class="mini-stat">
                        <div class="mini-stat-icon ic-blue"><i class="bi bi-calendar-day"></i></div>
                        <div>
                            <div class="mini-stat-val"><?= number_format($stats_clients['aujourdhui'] ?? 0) ?></div>
                            <div class="mini-stat-lbl">Aujourd'hui</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.periode-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-soft); }
.periode-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 18px; border-radius: 30px;
    font-size: 0.78rem; font-weight: 600; text-decoration: none;
    color: var(--text-secondary); background: var(--border-soft);
    border: 1.5px solid transparent; transition: all 0.3s;
    white-space: nowrap;
}
.periode-tab:hover { color: var(--gold); border-color: rgba(200,146,42,0.3); background: rgba(200,146,42,0.06); }
.periode-tab.active {
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: #fff; border-color: transparent;
    box-shadow: 0 6px 20px rgba(200,146,42,0.3);
}

.date-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 16px; }
.date-input-group { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 150px; }
.date-input-group label {
    font-size: 0.65rem; font-weight: 600; color: var(--text-secondary);
    text-transform: uppercase; letter-spacing: 0.8px;
    display: flex; align-items: center; gap: 4px;
}
.date-input-group label i { color: var(--gold); }
.date-input-group input {
    padding: 10px 14px; border: 1.5px solid var(--border-color);
    border-radius: 10px; font-family: 'Jost', sans-serif;
    font-size: 0.85rem; background: var(--input-bg);
    color: var(--text-primary); transition: all 0.3s;
}
.date-input-group input:focus {
    outline: none; border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(200,146,42,0.1);
}

.periode-info {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 18px;
    background: linear-gradient(135deg, rgba(200,146,42,0.06), rgba(200,146,42,0.02));
    border: 1px solid rgba(200,146,42,0.15);
    border-radius: 10px; font-size: 0.82rem; flex-wrap: wrap;
}
.periode-info i { color: var(--gold); }
.periode-info strong { color: var(--gold-dark); }
.badge-evolution {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 12px; border-radius: 20px;
    font-size: 0.7rem; font-weight: 700; margin-left: auto;
}
.badge-evolution.positive { background: rgba(39,174,96,0.12); color: #1A7A4A; border: 1px solid rgba(39,174,96,0.25); }
.badge-evolution.negative { background: rgba(231,76,60,0.12); color: #C0392B; border: 1px solid rgba(231,76,60,0.25); }

.export-card {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1510 100%);
    border: 1.5px solid rgba(200,146,42,0.35);
    box-shadow: 0 0 25px rgba(200,146,42,0.15);
}
.export-card .card-body { padding: 22px; }
.export-header {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 18px; padding-bottom: 16px;
    border-bottom: 1px solid rgba(200,146,42,0.2);
}
.export-header > i {
    font-size: 2rem; color: var(--gold-light);
    text-shadow: 0 0 20px rgba(232,181,90,0.6);
}
.export-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.15rem; font-weight: 700; color: #fff;
}
.export-sub { font-size: 0.78rem; color: rgba(255,255,255,0.5); margin-top: 2px; }
.export-buttons { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.export-btn {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 20px; border-radius: 12px;
    text-decoration: none; border: 1.5px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    transition: all 0.3s ease; cursor: pointer;
    font-family: inherit; text-align: left; width: 100%;
}
.export-btn > i { font-size: 1.8rem; flex-shrink: 0; }
.export-btn > div { flex: 1; min-width: 0; }
.export-btn strong {
    display: block; font-size: 0.88rem; font-weight: 700;
    margin-bottom: 2px; color: #fff;
}
.export-btn span {
    display: block; font-size: 0.7rem; color: rgba(255,255,255,0.5);
}
.export-btn:hover { transform: translateY(-3px); border-color: rgba(200,146,42,0.5); }
.export-btn.export-pdf > i { color: #E74C3C; }
.export-btn.export-pdf:hover { background: rgba(231,76,60,0.1); border-color: #E74C3C; box-shadow: 0 8px 25px rgba(231,76,60,0.25); }
.export-btn.export-excel > i { color: #27AE60; }
.export-btn.export-excel:hover { background: rgba(39,174,96,0.1); border-color: #27AE60; box-shadow: 0 8px 25px rgba(39,174,96,0.25); }

.ca-detail-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 0; border-bottom: 1px solid var(--border-soft); font-size: 0.88rem;
}
.ca-detail-row:last-child { border-bottom: none; }
.ca-detail-row span { display: flex; align-items: center; gap: 8px; }
.ca-detail-row span i { color: var(--gold); font-size: 1rem; }
.ca-detail-row strong { font-family: 'Playfair Display', serif; font-size: 1.05rem; font-weight: 700; }
.ca-detail-row.total {
    padding-top: 16px; border-top: 2px solid rgba(200,146,42,0.2);
    border-bottom: none; margin-top: 8px;
}
.ca-detail-row.total strong { color: var(--gold); font-size: 1.2rem; }

.table-responsive { overflow-x: auto; }
.table-rapport { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.table-rapport thead th {
    padding: 14px 18px; background: linear-gradient(135deg, #0D0D0D, #1A1510);
    color: rgba(255,255,255,0.85); font-weight: 600; font-size: 0.68rem;
    text-transform: uppercase; letter-spacing: 1px; text-align: left; white-space: nowrap;
}
.table-rapport tbody td {
    padding: 14px 18px; border-bottom: 1px solid var(--border-soft);
    vertical-align: middle;
}
.table-rapport tbody tr:hover { background: rgba(200,146,42,0.03); }
.table-rapport tbody tr:last-child td { border-bottom: none; }

.client-cell { display: flex; align-items: center; gap: 10px; }
.client-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.8rem; flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(200,146,42,0.25);
}
.qte-badge {
    display: inline-block; padding: 4px 12px;
    background: rgba(200,146,42,0.1); color: var(--gold-dark);
    border-radius: 20px; font-weight: 700; font-size: 0.78rem;
    border: 1px solid rgba(200,146,42,0.2);
}

.list-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 22px; border-bottom: 1px solid var(--border-soft);
    transition: background 0.2s;
}
.list-row:hover { background: rgba(200,146,42,0.03); }
.list-row:last-child { border-bottom: none; }
.list-row-left { display: flex; align-items: center; gap: 12px; }
.list-icon {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.list-icon.ic-blue { background: rgba(41,128,185,0.12); color: #2980B9; }
.list-name { font-size: 0.88rem; font-weight: 600; }
.list-sub { font-size: 0.7rem; color: var(--text-secondary); margin-top: 2px; }
.list-val { font-family: 'Playfair Display', serif; font-size: 0.95rem; font-weight: 700; }

.mini-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.mini-stat {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 18px; background: var(--border-soft);
    border-radius: 12px; border: 1px solid var(--border-color);
    transition: all 0.3s;
}
.mini-stat:hover { transform: translateY(-2px); border-color: rgba(200,146,42,0.3); }
.mini-stat-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
}
.mini-stat-icon.ic-gold { background: rgba(200,146,42,0.15); color: var(--gold); }
.mini-stat-icon.ic-blue { background: rgba(41,128,185,0.12); color: #2980B9; }
.mini-stat-icon.ic-green { background: rgba(39,174,96,0.12); color: #27AE60; }
.mini-stat-val { font-family: 'Playfair Display', serif; font-size: 1.4rem; font-weight: 700; line-height: 1; }
.mini-stat-lbl { font-size: 0.72rem; color: var(--text-secondary); margin-top: 4px; }

@media (max-width: 900px) {
    .dashboard-grid { grid-template-columns: 1fr; }
    .mini-stats { grid-template-columns: 1fr; }
    .export-buttons { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .periode-tabs { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 12px; -webkit-overflow-scrolling: touch; }
    .periode-tab { flex-shrink: 0; }
    .date-form { flex-direction: column; align-items: stretch; }
    .date-form .btn-admin { width: 100%; justify-content: center; }
    .periode-info { flex-direction: column; align-items: flex-start; }
    .badge-evolution { margin-left: 0; }
    .table-rapport { min-width: 620px; }
    .table-rapport thead th, .table-rapport tbody td { padding: 10px 12px; font-size: 0.78rem; }
    .list-row { padding: 12px 16px; }
    .list-val { font-size: 0.85rem; }
    .stats-row { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
    .stats-row { grid-template-columns: 1fr; }
    .periode-tab { font-size: 0.72rem; padding: 7px 14px; }
    .mini-stat-val { font-size: 1.2rem; }
    .mini-stat-icon { width: 38px; height: 38px; font-size: 1rem; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('evolutionChart');
    if (!ctx) return;
    
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : '#F0F2F5';
    const tickColor = isDark ? 'rgba(255,255,255,0.5)' : '#8A99AA';
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($evolution_labels) ?>,
            datasets: [{
                label: 'CA',
                data: <?= json_encode($evolution_ca) ?>,
                borderColor: '#C8922A',
                backgroundColor: (context) => {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return 'rgba(200,146,42,0.1)';
                    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    gradient.addColorStop(0, 'rgba(200,146,42,0.35)');
                    gradient.addColorStop(1, 'rgba(200,146,42,0)');
                    return gradient;
                },
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#C8922A',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0D0D0D',
                    titleColor: '#C8922A',
                    bodyColor: '#fff',
                    borderColor: '#C8922A',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: { label: (ctx) => 'CA: ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA' }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: {
                        color: tickColor,
                        callback: v => v >= 1000000 ? (v/1000000)+'M' : v >= 1000 ? (v/1000)+'k' : v,
                        font: { size: 10 }
                    }
                },
                x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 10 } } }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>