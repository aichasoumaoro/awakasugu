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
// FILTRES
// ============================================
$mois = isset($_GET['mois']) ? (int)$_GET['mois'] : date('m');
$annee = isset($_GET['annee']) ? (int)$_GET['annee'] : date('Y');

// ============================================
// STATISTIQUES
// ============================================

// Ventes du mois - Commandes en ligne
$stmt = $pdo->prepare("SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as ca FROM commandes WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
$stmt->execute([$mois, $annee]);
$stats_mois = $stmt->fetch();

// Ventes du mois - Boutique
$stmt = $pdo->prepare("SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as ca FROM ventes_boutique WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
$stmt->execute([$mois, $annee]);
$stats_boutique = $stmt->fetch();

// Total commandes de l'année
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
        <div class="topbar-right">
            <button class="btn-admin btn-primary" onclick="alert('📥 Export Excel à implémenter')">
                <i class="bi bi-file-earmark-excel"></i> Exporter
            </button>
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <!-- ===== FILTRES ===== -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label"><i class="bi bi-calendar-month"></i> Mois</label>
                    <select name="mois" class="form-select">
                        <?php for($i=1; $i<=12; $i++): ?>
                            <option value="<?= $i ?>" <?= $mois == $i ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><i class="bi bi-calendar"></i> Année</label>
                    <select name="annee" class="form-select">
                        <?php for($i=date('Y'); $i>=2023; $i--): ?>
                            <option value="<?= $i ?>" <?= $annee == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn-admin btn-primary" style="width:100%;justify-content:center;">
                        <i class="bi bi-funnel"></i> Filtrer
                    </button>
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
        <div class="stats-row" style="grid-template-columns: repeat(3, 1fr);">
            <div class="stat-box" style="border-left:3px solid #C8922A;">
                <div>
                    <div class="stat-val" style="color:#C8922A;"><?= number_format($stats_mois['nb'] ?? 0, 0, ',', ' ') ?></div>
                    <div class="stat-lbl">Commandes (<?= date('F', mktime(0,0,0,$mois,1)) ?>)</div>
                </div>
            </div>
            <div class="stat-box" style="border-left:3px solid #2980B9;">
                <div>
                    <div class="stat-val" style="color:#2980B9;"><?= number_format($stats_boutique['nb'] ?? 0, 0, ',', ' ') ?></div>
                    <div class="stat-lbl">Ventes boutique (<?= date('F', mktime(0,0,0,$mois,1)) ?>)</div>
                </div>
            </div>
            <div class="stat-box" style="border-left:3px solid #27AE60;">
                <div>
                    <div class="stat-val" style="color:#27AE60;"><?= number_format(($stats_mois['ca'] ?? 0) + ($stats_boutique['ca'] ?? 0), 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">CA total (<?= date('F', mktime(0,0,0,$mois,1)) ?>)</div>
                </div>
            </div>
        </div>

        <!-- ===== GRAPHIQUE ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-graph-up"></i> Ventes mensuelles <?= $annee ?></div>
                <div style="font-size:0.7rem;color:#8A99AA;">Commandes en ligne vs Boutique</div>
            </div>
            <div class="card-body">
                <canvas id="ventesChart" style="height:220px;width:100%;"></canvas>
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
                                        <span class="rank-badge <?= $rank <= 3 ? 'rank-'.$rank : 'rank-other' ?>">
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

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique ventes mensuelles
const ctx = document.getElementById('ventesChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
        datasets: [
            {
                label: 'Commandes en ligne',
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

// Graphique statuts
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
</script>

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>