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
$periode = isset($_GET['periode']) ? (int)$_GET['periode'] : 30;
$date_debut = date('Y-m-d', strtotime("-$periode days"));
$date_fin = date('Y-m-d');

// Statuts valides (commandes confirmées ou livrées)
$statuts_valides = "'confirmee', 'livree', 'terminee'";

// ============================================
// 1. STATISTIQUES GLOBALES (COMMANDES VALIDES UNIQUEMENT)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_commandes,
        COALESCE(SUM(total), 0) as ca_total,
        COALESCE(AVG(total), 0) as panier_moyen,
        COALESCE(MIN(total), 0) as panier_min,
        COALESCE(MAX(total), 0) as panier_max,
        COUNT(DISTINCT client_id) as clients_uniques
    FROM commandes 
    WHERE created_at BETWEEN ? AND ?
    AND statut IN ($statuts_valides)
");
$stmt->execute([$date_debut, $date_fin]);
$stats = $stmt->fetch();

// Commandes annulées (pour info)
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as nb_annulees,
        COALESCE(SUM(total), 0) as ca_annule
    FROM commandes 
    WHERE created_at BETWEEN ? AND ?
    AND statut = 'annulee'
");
$stmt->execute([$date_debut, $date_fin]);
$annulations = $stmt->fetch();

// Commandes en attente (pour info)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as nb_attente
    FROM commandes 
    WHERE created_at BETWEEN ? AND ?
    AND statut = 'en_attente'
");
$stmt->execute([$date_debut, $date_fin]);
$attente = $stmt->fetch();

// Commandes par statut (toutes)
$stmt = $pdo->prepare("
    SELECT statut, COUNT(*) as nb, COALESCE(SUM(total), 0) as total 
    FROM commandes 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY statut
");
$stmt->execute([$date_debut, $date_fin]);
$statuts = $stmt->fetchAll();

// ============================================
// 2. ANALYSE DES VENTES PAR JOUR (COMMANDES VALIDES)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as nb_commandes,
        COALESCE(SUM(total), 0) as ca_jour,
        COALESCE(AVG(total), 0) as panier_moyen_jour
    FROM commandes 
    WHERE created_at BETWEEN ? AND ? 
    AND statut IN ($statuts_valides)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$date_debut, $date_fin]);
$ventes_jour = $stmt->fetchAll();

// ============================================
// 3. TOP CLIENTS (COMMANDES VALIDES)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        c.nom_client,
        c.telephone,
        COUNT(c.id) as nb_commandes,
        COALESCE(SUM(c.total), 0) as total_depense,
        COALESCE(AVG(c.total), 0) as panier_moyen
    FROM commandes c
    WHERE c.created_at BETWEEN ? AND ? 
    AND c.statut IN ($statuts_valides)
    GROUP BY c.nom_client, c.telephone
    ORDER BY total_depense DESC
    LIMIT 10
");
$stmt->execute([$date_debut, $date_fin]);
$top_clients = $stmt->fetchAll();

// ============================================
// 4. PRODUITS LES PLUS VENDUS (COMMANDES VALIDES)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        p.nom,
        p.image_principale,
        p.prix,
        COALESCE(SUM(dc.quantite), 0) as total_vendu,
        COALESCE(SUM(dc.quantite * dc.prix_unitaire), 0) as total_ca
    FROM details_commande dc
    JOIN commandes c ON c.id = dc.commande_id
    JOIN produits p ON p.id = dc.produit_id
    WHERE c.created_at BETWEEN ? AND ? 
    AND c.statut IN ($statuts_valides)
    GROUP BY dc.produit_id, p.nom, p.image_principale, p.prix
    ORDER BY total_vendu DESC
    LIMIT 10
");
$stmt->execute([$date_debut, $date_fin]);
$top_produits = $stmt->fetchAll();

// ============================================
// 5. MODES DE PAIEMENT (COMMANDES VALIDES)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(mode_paiement, 'Non défini') as mode_paiement,
        COUNT(*) as nb_utilisations,
        COALESCE(SUM(total), 0) as total_par_paiement
    FROM commandes 
    WHERE created_at BETWEEN ? AND ? 
    AND statut IN ($statuts_valides)
    GROUP BY mode_paiement
    ORDER BY nb_utilisations DESC
");
$stmt->execute([$date_debut, $date_fin]);
$paiements = $stmt->fetchAll();

// ============================================
// 6. MARGE TOTALE (COMMANDES VALIDES)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM((p.prix - p.prix_achat) * dc.quantite), 0) as marge_totale,
        COALESCE(SUM(dc.quantite * dc.prix_unitaire), 0) as ca_total_produits,
        COALESCE(ROUND(SUM((p.prix - p.prix_achat) * dc.quantite) / NULLIF(SUM(dc.quantite * dc.prix_unitaire), 0) * 100, 2), 0) as marge_pourcentage
    FROM details_commande dc
    JOIN commandes c ON c.id = dc.commande_id
    JOIN produits p ON p.id = dc.produit_id
    WHERE c.created_at BETWEEN ? AND ? 
    AND c.statut IN ($statuts_valides) 
    AND p.prix_achat > 0
");
$stmt->execute([$date_debut, $date_fin]);
$marge_stats = $stmt->fetch();

// ============================================
// 7. TAUX DE CONVERSION
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(DISTINCT client_id) FROM commandes WHERE created_at BETWEEN ? AND ? AND client_id IS NOT NULL AND statut IN ($statuts_valides)) as clients_ayant_achete,
        (SELECT COUNT(*) FROM clients WHERE created_at BETWEEN ? AND ?) as clients_inscrits
");
$stmt->execute([$date_debut, $date_fin, $date_debut, $date_fin]);
$conversion = $stmt->fetch();

$taux_conversion = 0;
if (($conversion['clients_inscrits'] ?? 0) > 0) {
    $taux_conversion = round(($conversion['clients_ayant_achete'] / $conversion['clients_inscrits']) * 100, 2);
}

// ============================================
// 8. VENTES PAR HEURE (COMMANDES VALIDES)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        HOUR(created_at) as heure,
        COUNT(*) as nb_commandes,
        COALESCE(SUM(total), 0) as ca_heure
    FROM commandes 
    WHERE created_at BETWEEN ? AND ? 
    AND statut IN ($statuts_valides)
    GROUP BY HOUR(created_at)
    ORDER BY heure ASC
");
$stmt->execute([$date_debut, $date_fin]);
$ventes_heure = $stmt->fetchAll();

// ============================================
// 9. JOURS DE LA SEMAINE (COMMANDES VALIDES)
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        DAYNAME(created_at) as jour,
        COUNT(*) as nb_commandes,
        COALESCE(SUM(total), 0) as ca_jour_semaine
    FROM commandes 
    WHERE created_at BETWEEN ? AND ? 
    AND statut IN ($statuts_valides)
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
    background: var(--bg-card);
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 22px;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-card);
    transition: all 0.3s;
}
.analytics-card:hover {
    border-color: rgba(200,146,42,0.2);
    box-shadow: 0 8px 25px rgba(200,146,42,0.06);
}
.analytics-card .card-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-soft);
}
.analytics-card .card-title i {
    color: var(--gold);
    font-size: 1.1rem;
    text-shadow: 0 0 10px rgba(200,146,42,0.3);
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}
.kpi-box {
    background: var(--bg-card);
    border-radius: 14px;
    padding: 20px;
    text-align: center;
    border: 1px solid var(--border-color);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}
.kpi-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--gold), var(--gold-light));
    opacity: 0;
    transition: opacity 0.3s;
}
.kpi-box:hover {
    transform: translateY(-4px);
    border-color: rgba(200,146,42,0.35);
    box-shadow: 0 12px 30px rgba(200,146,42,0.12);
}
.kpi-box:hover::before {
    opacity: 1;
}
.kpi-box .kpi-value {
    font-size: 1.85rem;
    font-weight: 700;
    color: var(--gold);
    font-family: 'Playfair Display', serif;
    line-height: 1.1;
}
.kpi-box .kpi-label {
    font-size: 0.7rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-top: 6px;
    font-weight: 600;
}
.kpi-box .kpi-sub {
    font-size: 0.68rem;
    color: var(--text-secondary);
    margin-top: 4px;
    opacity: 0.8;
}
.kpi-box.green .kpi-value { color: #27AE60; }
.kpi-box.red .kpi-value { color: #E74C3C; }
.kpi-box.blue .kpi-value { color: #2980B9; }
.kpi-box.purple .kpi-value { color: #8E44AD; }
.kpi-box.gold .kpi-value { color: var(--gold); }
.kpi-box.gray .kpi-value { color: #7F8C8D; }

.chart-container {
    height: 320px;
    position: relative;
}
.chart-container-sm {
    height: 240px;
    position: relative;
}

.table-analytics {
    width: 100%;
    border-collapse: collapse;
}
.table-analytics th {
    background: linear-gradient(135deg, #0D0D0D, #1A1510);
    padding: 12px 14px;
    text-align: left;
    font-size: 0.68rem;
    text-transform: uppercase;
    color: rgba(255,255,255,0.85);
    letter-spacing: 1px;
    font-weight: 600;
}
.table-analytics th:first-child { border-radius: 8px 0 0 0; }
.table-analytics th:last-child { border-radius: 0 8px 0 0; }
.table-analytics td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-soft);
    font-size: 0.85rem;
    color: var(--text-primary);
}
.table-analytics tr:hover td {
    background: rgba(200,146,42,0.03);
}
.table-analytics tr:last-child td {
    border-bottom: none;
}

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
.rank-1 { background: linear-gradient(135deg, #FFD700, #F9A825); color: #0A0A0F; box-shadow: 0 0 12px rgba(255,215,0,0.4); }
.rank-2 { background: linear-gradient(135deg, #C0C0C0, #9E9E9E); color: #0A0A0F; box-shadow: 0 0 12px rgba(192,192,192,0.3); }
.rank-3 { background: linear-gradient(135deg, #CD7F32, #A67B5B); color: #fff; box-shadow: 0 0 12px rgba(205,127,50,0.3); }
.rank-other { background: var(--border-soft); color: var(--text-secondary); }

.period-filter {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 22px;
    align-items: center;
    padding: 14px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 14px;
}
.period-filter a {
    padding: 8px 18px;
    border-radius: 30px;
    text-decoration: none;
    font-size: 0.78rem;
    font-weight: 600;
    background: var(--border-soft);
    color: var(--text-secondary);
    transition: all 0.3s;
    border: 1.5px solid transparent;
}
.period-filter a:hover {
    color: var(--gold);
    border-color: rgba(200,146,42,0.3);
    background: rgba(200,146,42,0.06);
}
.period-filter a.active {
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: #fff;
    box-shadow: 0 6px 20px rgba(200,146,42,0.3);
}
.period-filter .date-info {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 6px;
}
.period-filter .date-info i {
    color: var(--gold);
}

.statut-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 14px 20px;
    background: var(--border-soft);
    border-radius: 12px;
    min-width: 110px;
    border: 1px solid var(--border-color);
    transition: all 0.3s;
}
.statut-stat:hover {
    transform: translateY(-3px);
    border-color: rgba(200,146,42,0.3);
}
.statut-stat .nb {
    font-size: 1.6rem;
    font-weight: 700;
    font-family: 'Playfair Display', serif;
    line-height: 1;
}
.statut-stat .label {
    font-size: 0.62rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    margin-top: 6px;
    letter-spacing: 0.5px;
    font-weight: 600;
}
.statut-stat .ca {
    font-size: 0.6rem;
    color: var(--text-secondary);
    margin-top: 3px;
    opacity: 0.7;
}

.badge-info {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.68rem;
    font-weight: 600;
    background: rgba(200,146,42,0.08);
    color: var(--gold-dark);
    border: 1px solid rgba(200,146,42,0.2);
}

.row-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
    margin-bottom: 22px;
}

@media (max-width: 900px) {
    .row-2col {
        grid-template-columns: 1fr;
    }
    .chart-container {
        height: 260px;
    }
    .chart-container-sm {
        height: 220px;
    }
}
@media (max-width: 768px) {
    .kpi-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .kpi-box {
        padding: 14px 12px;
    }
    .kpi-box .kpi-value {
        font-size: 1.4rem;
    }
    .chart-container {
        height: 220px;
    }
    .chart-container-sm {
        height: 200px;
    }
    .period-filter {
        padding: 12px 14px;
    }
    .period-filter .date-info {
        margin-left: 0;
        width: 100%;
        justify-content: center;
        margin-top: 8px;
    }
    .table-analytics {
        min-width: 500px;
    }
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin: 0 -22px;
        padding: 0 22px;
    }
}
@media (max-width: 480px) {
    .kpi-grid {
        grid-template-columns: 1fr;
    }
    .analytics-card {
        padding: 16px;
    }
    .analytics-card .card-title {
        font-size: 0.95rem;
    }
}
</style>

<div class="main">

    <div class="topbar">
        <div>
            <div class="topbar-title">Analytics — <span>Statistiques avancées</span></div>
            <div class="topbar-breadcrumb">Administration → Analytics</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" class="btn-admin btn-site" target="_blank">
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
            <span class="date-info">
                <i class="bi bi-info-circle"></i>
                Données du <?= date('d/m/Y', strtotime($date_debut)) ?> au <?= date('d/m/Y', strtotime($date_fin)) ?>
                <span class="badge-info" style="margin-left:8px;">
                    <i class="bi bi-check-circle-fill"></i> Commandes validées
                </span>
            </span>
        </div>

        <!-- ===== KPI ===== -->
        <div class="kpi-grid">
            <div class="kpi-box gold">
                <div class="kpi-value"><?= number_format($stats['ca_total'] ?? 0, 0, ',', ' ') ?> F</div>
                <div class="kpi-label">Chiffre d'affaires</div>
                <div class="kpi-sub"><?= $stats['total_commandes'] ?? 0 ?> commande(s) validée(s)</div>
            </div>
            <div class="kpi-box blue">
                <div class="kpi-value"><?= number_format($stats['panier_moyen'] ?? 0, 0, ',', ' ') ?> F</div>
                <div class="kpi-label">Panier moyen</div>
                <div class="kpi-sub">Min: <?= number_format($stats['panier_min'] ?? 0, 0, ',', ' ') ?> F · Max: <?= number_format($stats['panier_max'] ?? 0, 0, ',', ' ') ?> F</div>
            </div>
            <div class="kpi-box green">
                <div class="kpi-value"><?= $stats['clients_uniques'] ?? 0 ?></div>
                <div class="kpi-label">Clients uniques</div>
                <div class="kpi-sub">Taux de conversion : <?= $taux_conversion ?>%</div>
            </div>
            <div class="kpi-box purple">
                <div class="kpi-value"><?= number_format($marge_stats['marge_totale'] ?? 0, 0, ',', ' ') ?> F</div>
                <div class="kpi-label">Marge brute</div>
                <div class="kpi-sub">Taux : <?= $marge_stats['marge_pourcentage'] ?? 0 ?>%</div>
            </div>
            <div class="kpi-box gray">
                <div class="kpi-value"><?= $attente['nb_attente'] ?? 0 ?></div>
                <div class="kpi-label">En attente</div>
                <div class="kpi-sub">Non comptées dans le CA</div>
            </div>
            <div class="kpi-box red">
                <div class="kpi-value"><?= number_format($annulations['ca_annule'] ?? 0, 0, ',', ' ') ?> F</div>
                <div class="kpi-label">Annulations</div>
                <div class="kpi-sub"><?= $annulations['nb_annulees'] ?? 0 ?> commande(s) annulée(s)</div>
            </div>
        </div>

        <!-- ===== GRAPHIQUE VENTES JOUR ===== -->
        <div class="analytics-card">
            <div class="card-title">
                <i class="bi bi-graph-up"></i> Évolution des ventes (commandes validées)
            </div>
            <div class="chart-container">
                <canvas id="ventesChart"></canvas>
            </div>
        </div>

        <!-- ===== GRAPHIQUE VENTES PAR HEURE ===== -->
        <div class="analytics-card">
            <div class="card-title">
                <i class="bi bi-clock-history"></i> Ventes par heure
            </div>
            <div class="chart-container-sm">
                <canvas id="heureChart"></canvas>
            </div>
        </div>

        <!-- ===== 2 COLONNES ===== -->
        <div class="row-2col">

            <!-- TOP CLIENTS -->
            <div class="analytics-card">
                <div class="card-title"><i class="bi bi-trophy"></i> Top 10 clients</div>
                <div class="table-responsive">
                    <table class="table-analytics">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Client</th>
                                <th>Téléphone</th>
                                <th style="text-align:right;">Dépensé</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($top_clients)): ?>
                                <tr><td colspan="4" style="text-align:center;color:var(--text-secondary);padding:30px;">Aucune donnée sur cette période</td></tr>
                            <?php else: ?>
                                <?php $rank = 1; foreach($top_clients as $c): ?>
                                <tr>
                                    <td><span class="rank-badge rank-<?= $rank <= 3 ? $rank : 'other' ?>"><?= $rank ?></span></td>
                                    <td><strong><?= htmlspecialchars($c['nom_client']) ?></strong></td>
                                    <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['telephone'] ?? '-') ?></td>
                                    <td style="text-align:right;font-weight:700;color:var(--gold);">
                                        <?= number_format($c['total_depense'], 0, ',', ' ') ?> F
                                    </td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TOP PRODUITS -->
            <div class="analytics-card">
                <div class="card-title"><i class="bi bi-box-seam"></i> Top 10 produits</div>
                <div class="table-responsive">
                    <table class="table-analytics">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Produit</th>
                                <th style="text-align:center;">Vendu</th>
                                <th style="text-align:right;">CA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($top_produits)): ?>
                                <tr><td colspan="4" style="text-align:center;color:var(--text-secondary);padding:30px;">Aucune donnée sur cette période</td></tr>
                            <?php else: ?>
                                <?php $rank = 1; foreach($top_produits as $p): ?>
                                <tr>
                                    <td><span class="rank-badge rank-<?= $rank <= 3 ? $rank : 'other' ?>"><?= $rank ?></span></td>
                                    <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                                    <td style="text-align:center;font-weight:600;"><?= $p['total_vendu'] ?></td>
                                    <td style="text-align:right;font-weight:700;color:var(--gold);">
                                        <?= number_format($p['total_ca'], 0, ',', ' ') ?> F
                                    </td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== 2 COLONNES (bas) ===== -->
        <div class="row-2col">

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
            <div class="card-title">
                <i class="bi bi-pie-chart"></i> Répartition des statuts
                <span class="badge-info" style="margin-left:auto;">
                    <i class="bi bi-info-circle"></i> Toutes les commandes
                </span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:14px;justify-content:center;">
                <?php if(empty($statuts)): ?>
                    <p style="color:var(--text-secondary);padding:20px;">Aucune commande sur cette période</p>
                <?php else: 
                    foreach($statuts as $s): 
                    $label = [
                        'en_attente' => 'En attente',
                        'confirmee' => 'Confirmée',
                        'en_preparation' => 'Préparation',
                        'en_livraison' => 'Livraison',
                        'livree' => 'Livrée',
                        'terminee' => 'Terminée',
                        'annulee' => 'Annulée'
                    ][$s['statut']] ?? $s['statut'];
                    $color = [
                        'en_attente' => '#F39C12',
                        'confirmee' => '#27AE60',
                        'en_preparation' => '#2980B9',
                        'en_livraison' => '#8E44AD',
                        'livree' => '#27AE60',
                        'terminee' => '#1A7A4A',
                        'annulee' => '#E74C3C'
                    ][$s['statut']] ?? '#7F8C8D';
                ?>
                <div class="statut-stat">
                    <div class="nb" style="color:<?= $color ?>;"><?= $s['nb'] ?></div>
                    <div class="label"><?= $label ?></div>
                    <div class="ca"><?= number_format($s['total'] ?? 0, 0, ',', ' ') ?> F</div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Configuration globale
const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.04)';
const tickColor = isDark ? 'rgba(255,255,255,0.6)' : '#8A99AA';
const legendColor = isDark ? 'rgba(255,255,255,0.8)' : '#1A2C3E';

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
                borderWidth: 3,
                pointBackgroundColor: '#C8922A',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                yAxisID: 'y'
            },
            {
                label: 'Commandes',
                data: ventesNb,
                borderColor: '#2980B9',
                backgroundColor: 'rgba(41,128,185,0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointBackgroundColor: '#2980B9',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'top',
                labels: { font: { size: 11, family: 'Jost' }, boxWidth: 12, padding: 14, color: legendColor }
            },
            tooltip: {
                backgroundColor: '#0D0D0D',
                titleColor: '#C8922A',
                bodyColor: '#fff',
                borderColor: '#C8922A',
                borderWidth: 1,
                padding: 12,
                callbacks: {
                    label: ctx => ctx.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(ctx.raw)
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                position: 'left',
                ticks: {
                    callback: v => v >= 1000000 ? (v/1000000)+'M' : v >= 1000 ? (v/1000)+'k' : v,
                    font: { size: 10 }, color: tickColor
                },
                grid: { color: gridColor }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                ticks: { font: { size: 10 }, color: tickColor },
                grid: { display: false }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 10 }, color: tickColor, maxRotation: 0 }
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
        labels: heureLabels.map(h => String(h).padStart(2, '0') + 'h'),
        datasets: [
            {
                label: 'CA (FCFA)',
                data: heureCa,
                backgroundColor: 'rgba(200,146,42,0.75)',
                borderColor: '#C8922A',
                borderWidth: 1,
                borderRadius: 6,
                yAxisID: 'y'
            },
            {
                label: 'Commandes',
                data: heureNb,
                backgroundColor: 'rgba(41,128,185,0.75)',
                borderColor: '#2980B9',
                borderWidth: 1,
                borderRadius: 6,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'top',
                labels: { font: { size: 10, family: 'Jost' }, boxWidth: 12, padding: 12, color: legendColor }
            },
            tooltip: {
                backgroundColor: '#0D0D0D',
                titleColor: '#C8922A',
                bodyColor: '#fff',
                borderColor: '#C8922A',
                borderWidth: 1,
                padding: 12
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => v >= 1000000 ? (v/1000000)+'M' : v >= 1000 ? (v/1000)+'k' : v,
                    font: { size: 9 }, color: tickColor
                },
                grid: { color: gridColor }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                ticks: { font: { size: 9 }, color: tickColor },
                grid: { display: false }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 9 }, color: tickColor }
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
const paiementColors = ['#C8922A', '#2980B9', '#27AE60', '#E74C3C', '#8E44AD', '#F39C12', '#17A2B8'];

if (paiementData.length > 0) {
    new Chart(ctxPaiement, {
        type: 'doughnut',
        data: {
            labels: paiementLabels.map(l => l ? l.replace(/_/g, ' ').toUpperCase() : 'NON DÉFINI'),
            datasets: [{
                data: paiementData,
                backgroundColor: paiementColors.slice(0, paiementData.length),
                borderWidth: 3,
                borderColor: isDark ? '#1A1F28' : '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 10, family: 'Jost' },
                        padding: 10,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        color: legendColor
                    }
                },
                tooltip: {
                    backgroundColor: '#0D0D0D',
                    titleColor: '#C8922A',
                    bodyColor: '#fff',
                    borderColor: '#C8922A',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: ctx => ctx.label + ': ' + ctx.raw + ' utilisation(s)'
                    }
                }
            }
        }
    });
} else {
    document.getElementById('paiementChart').parentElement.innerHTML = 
        '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#8A99AA;font-size:0.85rem;">Aucune donnée</div>';
}

// ============================================
// 4. GRAPHIQUE JOURS DE LA SEMAINE
// ============================================
const ctxJour = document.getElementById('jourSemaineChart').getContext('2d');
const jourLabels = <?= json_encode(array_column($ventes_jour_semaine, 'jour')) ?>;
const jourCa = <?= json_encode(array_column($ventes_jour_semaine, 'ca_jour_semaine')) ?>;

const joursTraduits = {
    'Monday': 'Lundi', 'Tuesday': 'Mardi', 'Wednesday': 'Mercredi',
    'Thursday': 'Jeudi', 'Friday': 'Vendredi', 'Saturday': 'Samedi', 'Sunday': 'Dimanche'
};
const jourLabelsFr = jourLabels.map(j => joursTraduits[j] || j);

if (jourLabelsFr.length > 0) {
    new Chart(ctxJour, {
        type: 'bar',
        data: {
            labels: jourLabelsFr,
            datasets: [{
                label: 'CA (FCFA)',
                data: jourCa,
                backgroundColor: [
                    'rgba(200,146,42,0.85)', 'rgba(212,168,74,0.85)', 
                    'rgba(224,184,90,0.85)', 'rgba(236,200,106,0.85)', 
                    'rgba(248,216,122,0.85)', 'rgba(212,168,74,0.85)', 
                    'rgba(200,146,42,0.85)'
                ],
                borderColor: '#C8922A',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0D0D0D',
                    titleColor: '#C8922A',
                    bodyColor: '#fff',
                    borderColor: '#C8922A',
                    borderWidth: 1,
                    padding: 12,
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
                        font: { size: 9 }, color: tickColor
                    },
                    grid: { color: gridColor }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 }, color: tickColor }
                }
            }
        }
    });
} else {
    document.getElementById('jourSemaineChart').parentElement.innerHTML = 
        '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#8A99AA;font-size:0.85rem;">Aucune donnée</div>';
}
</script>

<?php include 'includes/footer.php'; ?>