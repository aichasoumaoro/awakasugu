<?php
// ============================================
// ANALYTICS - ADMIN AWA KA SUGU
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

$page_title = 'Analytics - Statistiques avancées';

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
// PÉRIODE
// ============================================
$periode = isset($_GET['periode']) ? $_GET['periode'] : '30';
$date_debut = date('Y-m-d', strtotime("-$periode days"));
$date_fin = date('Y-m-d');

// ============================================
// 1. STATISTIQUES GLOBALES
// ============================================

// Commandes
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_commandes,
        SUM(CASE WHEN statut != 'annulee' THEN total ELSE 0 END) as ca_total,
        SUM(CASE WHEN statut = 'annulee' THEN total ELSE 0 END) as ca_annule,
        AVG(total) as panier_moyen,
        MIN(total) as panier_min,
        MAX(total) as panier_max,
        COUNT(DISTINCT client_id) as clients_uniques
    FROM commandes 
    WHERE created_at BETWEEN ? AND ?
");
$stmt->execute([$date_debut, $date_fin]);
$stats = $stmt->fetch();

// Commandes par statut
$stmt = $pdo->prepare("
    SELECT statut, COUNT(*) as nb, COALESCE(SUM(total), 0) as total 
    FROM commandes 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY statut
");
$stmt->execute([$date_debut, $date_fin]);
$statuts = $stmt->fetchAll();

// ============================================
// 2. ANALYSE DES VENTES PAR JOUR
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as nb_commandes,
        SUM(total) as ca_jour,
        AVG(total) as panier_moyen_jour
    FROM commandes 
    WHERE created_at BETWEEN ? AND ? AND statut != 'annulee'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$date_debut, $date_fin]);
$ventes_jour = $stmt->fetchAll();

// ============================================
// 3. TOP CLIENTS (par montant dépensé)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        c.nom_client,
        c.telephone,
        COUNT(c.id) as nb_commandes,
        SUM(c.total) as total_depense,
        AVG(c.total) as panier_moyen
    FROM commandes c
    WHERE c.created_at BETWEEN ? AND ? AND c.statut != 'annulee'
    GROUP BY c.nom_client, c.telephone
    ORDER BY total_depense DESC
    LIMIT 10
");
$stmt->execute([$date_debut, $date_fin]);
$top_clients = $stmt->fetchAll();

// ============================================
// 4. PRODUITS LES PLUS VENDUS
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        p.nom,
        p.image_principale,
        p.prix,
        SUM(dc.quantite) as total_vendu,
        SUM(dc.quantite * dc.prix_unitaire) as total_ca
    FROM details_commande dc
    JOIN commandes c ON c.id = dc.commande_id
    JOIN produits p ON p.id = dc.produit_id
    WHERE c.created_at BETWEEN ? AND ? AND c.statut != 'annulee'
    GROUP BY dc.produit_id
    ORDER BY total_vendu DESC
    LIMIT 10
");
$stmt->execute([$date_debut, $date_fin]);
$top_produits = $stmt->fetchAll();

// ============================================
// 5. ANALYSE DES MODES DE PAIEMENT
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        mode_paiement,
        COUNT(*) as nb_utilisations,
        SUM(total) as total_par_paiement
    FROM commandes 
    WHERE created_at BETWEEN ? AND ? AND statut != 'annulee'
    GROUP BY mode_paiement
    ORDER BY nb_utilisations DESC
");
$stmt->execute([$date_debut, $date_fin]);
$paiements = $stmt->fetchAll();

// ============================================
// 6. ANALYSE DE LA MARGE TOTALE
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        SUM((p.prix - p.prix_achat) * dc.quantite) as marge_totale,
        SUM(dc.quantite * dc.prix_unitaire) as ca_total_produits,
        ROUND(SUM((p.prix - p.prix_achat) * dc.quantite) / SUM(dc.quantite * dc.prix_unitaire) * 100, 2) as marge_pourcentage
    FROM details_commande dc
    JOIN commandes c ON c.id = dc.commande_id
    JOIN produits p ON p.id = dc.produit_id
    WHERE c.created_at BETWEEN ? AND ? AND c.statut != 'annulee' AND p.prix_achat > 0
");
$stmt->execute([$date_debut, $date_fin]);
$marge_stats = $stmt->fetch();

// ============================================
// 7. TAUX DE CONVERSION (si clients existent)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(DISTINCT client_id) FROM commandes WHERE created_at BETWEEN ? AND ? AND client_id IS NOT NULL) as clients_ayant_achete,
        (SELECT COUNT(*) FROM clients WHERE created_at BETWEEN ? AND ?) as clients_inscrits
");
$stmt->execute([$date_debut, $date_fin, $date_debut, $date_fin]);
$conversion = $stmt->fetch();

$taux_conversion = 0;
if (($conversion['clients_inscrits'] ?? 0) > 0) {
    $taux_conversion = round(($conversion['clients_ayant_achete'] / $conversion['clients_inscrits']) * 100, 2);
}

// ============================================
// 8. VENTES PAR HEURE (pour optimiser les campagnes)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        HOUR(created_at) as heure,
        COUNT(*) as nb_commandes,
        SUM(total) as ca_heure
    FROM commandes 
    WHERE created_at BETWEEN ? AND ? AND statut != 'annulee'
    GROUP BY HOUR(created_at)
    ORDER BY heure ASC
");
$stmt->execute([$date_debut, $date_fin]);
$ventes_heure = $stmt->fetchAll();

// ============================================
// 9. JOURS DE LA SEMAINE LES PLUS ACTIFS
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        DAYNAME(created_at) as jour,
        COUNT(*) as nb_commandes,
        SUM(total) as ca_jour_semaine
    FROM commandes 
    WHERE created_at BETWEEN ? AND ? AND statut != 'annulee'
    GROUP BY DAYNAME(created_at)
    ORDER BY FIELD(DAYNAME(created_at), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
");
$stmt->execute([$date_debut, $date_fin]);
$ventes_jour_semaine = $stmt->fetchAll();

// ============================================
// INCLUSION DU HEADER ET DE LA SIDEBAR
// ============================================
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
.analytics-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #F0EDEA;
}
.analytics-card .card-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    font-weight: 600;
    color: #0D0D0D;
    margin-bottom: 15px;
}
.analytics-card .card-title i {
    color: #C8922A;
    margin-right: 8px;
}
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
}
.kpi-box {
    background: #F8F9FA;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    border: 1px solid #F0EDEA;
    transition: all 0.3s;
}
.kpi-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}
.kpi-box .kpi-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #C8922A;
    font-family: 'Playfair Display', serif;
}
.kpi-box .kpi-label {
    font-size: 0.7rem;
    color: #8A99AA;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}
.kpi-box .kpi-sub {
    font-size: 0.65rem;
    color: #B0B0B0;
    margin-top: 2px;
}
.kpi-box.green .kpi-value { color: #27AE60; }
.kpi-box.red .kpi-value { color: #E74C3C; }
.kpi-box.blue .kpi-value { color: #2980B9; }
.kpi-box.purple .kpi-value { color: #8E44AD; }
.kpi-box.gold .kpi-value { color: #C8922A; }
.chart-container {
    height: 280px;
    position: relative;
}
.chart-container-sm {
    height: 200px;
    position: relative;
}
.table-analytics {
    width: 100%;
    border-collapse: collapse;
}
.table-analytics th {
    background: #F8F9FA;
    padding: 10px 12px;
    text-align: left;
    font-size: 0.7rem;
    text-transform: uppercase;
    color: #8A99AA;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #E8ECF0;
}
.table-analytics td {
    padding: 10px 12px;
    border-bottom: 1px solid #F0EDEA;
    font-size: 0.85rem;
}
.table-analytics tr:hover td {
    background: #FEFBF5;
}
.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    font-weight: 700;
    font-size: 0.75rem;
}
.rank-1 { background: #C8922A; color: #fff; }
.rank-2 { background: #B0B0B0; color: #fff; }
.rank-3 { background: #CD7F32; color: #fff; }
.rank-other { background: #F0F0F0; color: #666; }
.period-filter {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.period-filter a {
    padding: 6px 16px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 0.8rem;
    background: #F0F0F0;
    color: #666;
    transition: all 0.3s;
}
.period-filter a:hover,
.period-filter a.active {
    background: #C8922A;
    color: #fff;
}
@media (max-width: 768px) {
    .kpi-grid { grid-template-columns: 1fr 1fr; }
    .chart-container { height: 200px; }
}
</style>

<div class="main">

    <div class="topbar">
        <div>
            <div class="topbar-title">📈 <span>Analytics</span></div>
            <div class="topbar-breadcrumb">Administration → Analytics → Statistiques avancées</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <div class="content">

        <!-- ===== FILTRE PÉRIODE ===== -->
        <div class="period-filter">
            <a href="?periode=7" class="<?= $periode == 7 ? 'active' : '' ?>">7 jours</a>
            <a href="?periode=30" class="<?= $periode == 30 ? 'active' : '' ?>">30 jours</a>
            <a href="?periode=90" class="<?= $periode == 90 ? 'active' : '' ?>">90 jours</a>
            <a href="?periode=365" class="<?= $periode == 365 ? 'active' : '' ?>">1 an</a>
            <span style="font-size:0.75rem;color:#8A99AA;margin-left:auto;">
                <i class="bi bi-calendar"></i> Du <?= date('d/m/Y', strtotime($date_debut)) ?> au <?= date('d/m/Y', strtotime($date_fin)) ?>
            </span>
        </div>

        <!-- ===== KPI ===== -->
        <div class="kpi-grid">
            <div class="kpi-box gold">
                <div class="kpi-value"><?= number_format($stats['ca_total'] ?? 0, 0, ',', ' ') ?> F</div>
                <div class="kpi-label">Chiffre d'affaires</div>
                <div class="kpi-sub"><?= $stats['total_commandes'] ?? 0 ?> commandes</div>
            </div>
            <div class="kpi-box blue">
                <div class="kpi-value"><?= number_format($stats['panier_moyen'] ?? 0, 0, ',', ' ') ?> F</div>
                <div class="kpi-label">Panier moyen</div>
                <div class="kpi-sub">Min: <?= number_format($stats['panier_min'] ?? 0, 0, ',', ' ') ?> F • Max: <?= number_format($stats['panier_max'] ?? 0, 0, ',', ' ') ?> F</div>
            </div>
            <div class="kpi-box green">
                <div class="kpi-value"><?= $stats['clients_uniques'] ?? 0 ?></div>
                <div class="kpi-label">Clients uniques</div>
                <div class="kpi-sub">Taux conversion: <?= $taux_conversion ?>%</div>
            </div>
            <div class="kpi-box purple">
                <div class="kpi-value"><?= number_format($marge_stats['marge_totale'] ?? 0, 0, ',', ' ') ?> F</div>
                <div class="kpi-label">Marge brute</div>
                <div class="kpi-sub">Taux: <?= $marge_stats['marge_pourcentage'] ?? 0 ?>%</div>
            </div>
            <div class="kpi-box red">
                <div class="kpi-value"><?= number_format($stats['ca_annule'] ?? 0, 0, ',', ' ') ?> F</div>
                <div class="kpi-label">Annulations</div>
                <div class="kpi-sub"><?= round(($stats['ca_annule'] / max($stats['ca_total'] + $stats['ca_annule'], 1)) * 100, 1) ?>% du CA total</div>
            </div>
        </div>

        <!-- ===== GRAPHIQUE VENTES JOUR ===== -->
        <div class="analytics-card">
            <div class="card-title"><i class="bi bi-graph-up"></i> Évolution des ventes</div>
            <div class="chart-container">
                <canvas id="ventesChart"></canvas>
            </div>
        </div>

        <!-- ===== GRAPHIQUE VENTES PAR HEURE ===== -->
        <div class="analytics-card">
            <div class="card-title"><i class="bi bi-clock-history"></i> Ventes par heure (période sélectionnée)</div>
            <div class="chart-container-sm">
                <canvas id="heureChart"></canvas>
            </div>
        </div>

        <!-- ===== 2 COLONNES ===== -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

            <!-- TOP CLIENTS -->
            <div class="analytics-card">
                <div class="card-title"><i class="bi bi-trophy"></i> Top 10 clients</div>
                <table class="table-analytics">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Téléphone</th>
                            <th style="text-align:right;">Dépensé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($top_clients)): ?>
                            <tr><td colspan="4" style="text-align:center;color:#999;padding:20px;">Aucune donnée</td></tr>
                        <?php else: ?>
                            <?php $rank = 1; foreach($top_clients as $c): ?>
                            <tr>
                                <td><span class="rank-badge rank-<?= $rank <= 3 ? $rank : 'other' ?>"><?= $rank ?></span></td>
                                <td><strong><?= htmlspecialchars($c['nom_client']) ?></strong></td>
                                <td><?= htmlspecialchars($c['telephone'] ?? '-') ?></td>
                                <td style="text-align:right;font-weight:600;color:#C8922A;">
                                    <?= number_format($c['total_depense'], 0, ',', ' ') ?> F
                                </td>
                            </tr>
                            <?php $rank++; endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- TOP PRODUITS -->
            <div class="analytics-card">
                <div class="card-title"><i class="bi bi-box-seam"></i> Top 10 produits</div>
                <table class="table-analytics">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Produit</th>
                            <th style="text-align:center;">Vendu</th>
                            <th style="text-align:right;">CA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($top_produits)): ?>
                            <tr><td colspan="4" style="text-align:center;color:#999;padding:20px;">Aucune donnée</td></tr>
                        <?php else: ?>
                            <?php $rank = 1; foreach($top_produits as $p): ?>
                            <tr>
                                <td><span class="rank-badge rank-<?= $rank <= 3 ? $rank : 'other' ?>"><?= $rank ?></span></td>
                                <td><?= htmlspecialchars($p['nom']) ?></td>
                                <td style="text-align:center;font-weight:600;"><?= $p['total_vendu'] ?></td>
                                <td style="text-align:right;font-weight:600;color:#C8922A;">
                                    <?= number_format($p['total_ca'], 0, ',', ' ') ?> F
                                </td>
                            </tr>
                            <?php $rank++; endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== 2 COLONNES (bas) ===== -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

            <!-- MODES DE PAIEMENT -->
            <div class="analytics-card">
                <div class="card-title"><i class="bi bi-credit-card"></i> Modes de paiement</div>
                <div class="chart-container-sm">
                    <canvas id="paiementChart"></canvas>
                </div>
            </div>

            <!-- JOURS DE LA SEMAINE -->
            <div class="analytics-card">
                <div class="card-title"><i class="bi bi-calendar-week"></i> Jours les plus actifs</div>
                <div class="chart-container-sm">
                    <canvas id="jourSemaineChart"></canvas>
                </div>
            </div>
        </div>

        <!-- ===== STATUTS COMMANDES ===== -->
        <div class="analytics-card">
            <div class="card-title"><i class="bi bi-pie-chart"></i> Répartition des statuts</div>
            <div style="display:flex;flex-wrap:wrap;gap:20px;justify-content:center;">
                <?php foreach($statuts as $s): 
                    $label = [
                        'en_attente' => 'En attente',
                        'confirmee' => 'Confirmée',
                        'en_preparation' => 'Préparation',
                        'en_livraison' => 'Livraison',
                        'livree' => 'Livrée',
                        'annulee' => 'Annulée'
                    ][$s['statut']] ?? $s['statut'];
                    $color = [
                        'en_attente' => '#FFC107',
                        'confirmee' => '#28A745',
                        'en_preparation' => '#17A2B8',
                        'en_livraison' => '#6C757D',
                        'livree' => '#27AE60',
                        'annulee' => '#DC3545'
                    ][$s['statut']] ?? '#6C757D';
                ?>
                <div style="text-align:center;min-width:80px;">
                    <div style="font-size:1.5rem;font-weight:700;color:<?= $color ?>;"><?= $s['nb'] ?></div>
                    <div style="font-size:0.6rem;color:#8A99AA;text-transform:uppercase;"><?= $label ?></div>
                    <div style="font-size:0.55rem;color:#B0B0B0;"><?= number_format($s['total'] ?? 0, 0, ',', ' ') ?> F</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ============================================
// 1. GRAPHIQUE VENTES JOUR
// ============================================
const ctxVentes = document.getElementById('ventesChart').getContext('2d');
const ventesLabels = <?= json_encode(array_column($ventes_jour, 'date')) ?>;
const ventesCa = <?= json_encode(array_column($ventes_jour, 'ca_jour')) ?>;
const ventesNb = <?= json_encode(array_column($ventes_jour, 'nb_commandes')) ?>;

new Chart(ctxVentes, {
    type: 'line',
    data: {
        labels: ventesLabels.map(d => {
            const date = new Date(d);
            return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
        }),
        datasets: [
            {
                label: 'CA (FCFA)',
                data: ventesCa,
                borderColor: '#C8922A',
                backgroundColor: 'rgba(200,146,42,0.1)',
                fill: true,
                tension: 0.4,
                yAxisID: 'y'
            },
            {
                label: 'Commandes',
                data: ventesNb,
                borderColor: '#2980B9',
                backgroundColor: 'rgba(41,128,185,0.1)',
                fill: true,
                tension: 0.4,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: { font: { size: 10 }, boxWidth: 12, padding: 10 }
            },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(ctx.raw)
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => v >= 1000000 ? (v/1000000)+'M' : v >= 1000 ? (v/1000)+'k' : v,
                    font: { size: 9 }
                },
                grid: { color: 'rgba(0,0,0,0.04)' }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                ticks: { font: { size: 9 } },
                grid: { display: false }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 9 } }
            }
        }
    }
});

// ============================================
// 2. GRAPHIQUE VENTES PAR HEURE
// ============================================
const ctxHeure = document.getElementById('heureChart').getContext('2d');
const heureLabels = <?= json_encode(array_column($ventes_heure, 'heure')) ?>;
const heureCa = <?= json_encode(array_column($ventes_heure, 'ca_heure')) ?>;
const heureNb = <?= json_encode(array_column($ventes_heure, 'nb_commandes')) ?>;

new Chart(ctxHeure, {
    type: 'bar',
    data: {
        labels: heureLabels.map(h => h + 'h'),
        datasets: [
            {
                label: 'CA (FCFA)',
                data: heureCa,
                backgroundColor: 'rgba(200,146,42,0.7)',
                borderColor: '#C8922A',
                borderWidth: 1,
                borderRadius: 4,
                yAxisID: 'y'
            },
            {
                label: 'Commandes',
                data: heureNb,
                backgroundColor: 'rgba(41,128,185,0.7)',
                borderColor: '#2980B9',
                borderWidth: 1,
                borderRadius: 4,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: { font: { size: 9 }, boxWidth: 10, padding: 8 }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { 
                    callback: v => v >= 1000000 ? (v/1000000)+'M' : v >= 1000 ? (v/1000)+'k' : v,
                    font: { size: 8 }
                },
                grid: { color: 'rgba(0,0,0,0.04)' }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                ticks: { font: { size: 8 } },
                grid: { display: false }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 8 } }
            }
        }
    }
});

// ============================================
// 3. GRAPHIQUE MODES DE PAIEMENT
// ============================================
const ctxPaiement = document.getElementById('paiementChart').getContext('2d');
const paiementLabels = <?= json_encode(array_column($paiements, 'mode_paiement')) ?>;
const paiementData = <?= json_encode(array_column($paiements, 'nb_utilisations')) ?>;
const paiementColors = ['#C8922A', '#2980B9', '#27AE60', '#E74C3C', '#8E44AD', '#F39C12'];

new Chart(ctxPaiement, {
    type: 'doughnut',
    data: {
        labels: paiementLabels.map(l => l ? l.replace('_', ' ').toUpperCase() : 'N/A'),
        datasets: [{
            data: paiementData,
            backgroundColor: paiementColors.slice(0, paiementData.length),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { font: { size: 9 }, padding: 8, usePointStyle: true, pointStyle: 'circle' }
            }
        }
    }
});

// ============================================
// 4. GRAPHIQUE JOURS DE LA SEMAINE
// ============================================
const ctxJour = document.getElementById('jourSemaineChart').getContext('2d');
const jourLabels = <?= json_encode(array_column($ventes_jour_semaine, 'jour')) ?>;
const jourCa = <?= json_encode(array_column($ventes_jour_semaine, 'ca_jour_semaine')) ?>;

// Traduire les jours en français
const joursTraduits = {
    'Monday': 'Lundi', 'Tuesday': 'Mardi', 'Wednesday': 'Mercredi',
    'Thursday': 'Jeudi', 'Friday': 'Vendredi', 'Saturday': 'Samedi', 'Sunday': 'Dimanche'
};
const jourLabelsFr = jourLabels.map(j => joursTraduits[j] || j);

new Chart(ctxJour, {
    type: 'bar',
    data: {
        labels: jourLabelsFr,
        datasets: [{
            label: 'CA (FCFA)',
            data: jourCa,
            backgroundColor: ['#C8922A', '#D4A84A', '#E0B85A', '#ECC86A', '#F8D87A', '#D4A84A', '#C8922A'],
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => 'CA: ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA'
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { 
                    callback: v => v >= 1000000 ? (v/1000000)+'M' : v >= 1000 ? (v/1000)+'k' : v,
                    font: { size: 8 }
                },
                grid: { color: 'rgba(0,0,0,0.04)' }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 9 } }
            }
        }
    }
});
</script>

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>