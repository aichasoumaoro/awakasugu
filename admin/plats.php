<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$host = 'localhost'; $dbname = 'awakasugu_db'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) { die("Erreur : " . $e->getMessage()); }

// Suppression
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $stmt = $pdo->prepare("SELECT image FROM plats WHERE id = ?");
    $stmt->execute([$id]);
    $plat = $stmt->fetch();
    if ($plat && !empty($plat['image'])) {
        $f = '../uploads/plats/' . $plat['image'];
        if (file_exists($f)) unlink($f);
    }
    $pdo->prepare("DELETE FROM plats WHERE id = ?")->execute([$id]);
    header('Location: plats.php?msg=supprime');
    exit;
}

// Plat du jour
if (isset($_GET['plat_jour'])) {
    $id = (int)$_GET['plat_jour'];
    $pdo->exec("UPDATE plats SET est_plat_du_jour = 0");
    $pdo->prepare("UPDATE plats SET est_plat_du_jour = 1 WHERE id = ?")->execute([$id]);
    header('Location: plats.php?msg=plat_jour');
    exit;
}

// Toggle visibilité
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE plats SET est_visible = NOT est_visible WHERE id = ?")->execute([$id]);
    header('Location: plats.php?msg=update');
    exit;
}

$plats = $pdo->query("
    SELECT p.*, c.nom as cat_nom
    FROM plats p
    LEFT JOIN categories_restaurant c ON p.categorie_id = c.id
    ORDER BY p.est_plat_du_jour DESC, p.id DESC
")->fetchAll();

$stats_total    = count($plats);
$stats_visibles = count(array_filter($plats, fn($p) => $p['est_visible']));
$stats_plat_jour = count(array_filter($plats, fn($p) => $p['est_plat_du_jour']));
$stats_prix_moy = $stats_total > 0 ? array_sum(array_column($plats, 'prix')) / $stats_total : 0;

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restaurant Sofia — Gestion des plats</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Jost', sans-serif; background: #F4F4F6; display: flex; min-height: 100vh; }

/* ===== SIDEBAR ===== */
.sidebar {
    width: 260px; background: #0D0D0D;
    border-right: 1px solid rgba(200,146,42,0.18);
    position: fixed; top: 0; left: 0; bottom: 0;
    display: flex; flex-direction: column; z-index: 100; overflow-y: auto;
}
.sidebar-brand { padding: 26px 22px 18px; border-bottom: 1px solid rgba(200,146,42,0.12); }
.brand-logo { font-family: 'Playfair Display', serif; font-size: 1.15rem; font-weight: 700; color: #C8922A; letter-spacing: 3px; }
.brand-sub { font-size: 0.6rem; color: rgba(255,255,255,0.2); letter-spacing: 2px; text-transform: uppercase; margin-top: 2px; }
.admin-user { display: flex; align-items: center; gap: 10px; margin-top: 14px; padding: 9px 12px; background: rgba(200,146,42,0.07); border-radius: 8px; border: 1px solid rgba(200,146,42,0.12); }
.admin-avatar { width: 32px; height: 32px; background: linear-gradient(135deg,#C8922A,#E2B96A); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; color: #fff; font-weight: 700; }
.admin-name { font-size: 0.8rem; color: #fff; font-weight: 500; }
.admin-role { font-size: 0.6rem; color: rgba(255,255,255,0.3); letter-spacing: 1px; text-transform: uppercase; }
.nav-section { font-size: 0.58rem; color: rgba(255,255,255,0.18); letter-spacing: 2.5px; text-transform: uppercase; padding: 16px 22px 5px; }
.sidebar nav { flex: 1; padding: 6px 10px; }
.nav-item { display: flex; align-items: center; gap: 11px; padding: 10px 13px; border-radius: 8px; color: rgba(255,255,255,0.48); text-decoration: none; font-size: 0.82rem; font-weight: 500; border-left: 2px solid transparent; transition: all 0.22s; margin-bottom: 2px; }
.nav-item i { font-size: 1rem; width: 18px; text-align: center; }
.nav-item:hover { color: #fff; background: rgba(200,146,42,0.08); border-left-color: rgba(200,146,42,0.4); }
.nav-item.active { color: #fff; background: rgba(200,146,42,0.12); border-left-color: #C8922A; }
.nav-item.active i { color: #C8922A; }
.nav-item.logout { color: rgba(231,76,60,0.6); margin-top: 6px; }
.nav-item.logout:hover { color: #E74C3C; background: rgba(231,76,60,0.08); border-left-color: #E74C3C; }

/* ===== MAIN ===== */
.main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; background: #F4F4F6; min-height: 100vh; }

/* Topbar */
.topbar { background: #fff; border-bottom: 1px solid #E8E8E8; padding: 15px 32px; display: flex; align-items: center; justify-content: space-between; gap: 14px; position: sticky; top: 0; z-index: 50; }
.topbar-title { font-family: 'Playfair Display', serif; font-size: 1.4rem; font-weight: 700; color: #0D0D0D; }
.topbar-title span { color: #C8922A; }
.topbar-sub { font-size: 0.74rem; color: #999; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 10px; }
.btn-add { display: inline-flex; align-items: center; gap: 7px; background: linear-gradient(135deg,#C8922A,#E2B96A); color: #fff; font-family: 'Jost',sans-serif; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; padding: 10px 22px; border-radius: 8px; text-decoration: none; transition: all 0.25s; box-shadow: 0 4px 14px rgba(200,146,42,0.3); border: none; cursor: pointer; }
.btn-add:hover { background: linear-gradient(135deg,#9A6E1A,#C8922A); transform: translateY(-1px); color: #fff; }
.btn-outline-dark { display: inline-flex; align-items: center; gap: 6px; background: #fff; color: #555; border: 1.5px solid #E0E0E0; font-size: 0.75rem; font-weight: 600; padding: 9px 18px; border-radius: 8px; text-decoration: none; transition: all 0.22s; }
.btn-outline-dark:hover { border-color: #C8922A; color: #C8922A; }

/* Content */
.content { padding: 26px 32px; flex: 1; }

/* Alert */
.alert-ok { display: flex; align-items: center; gap: 10px; background: #D4EDDA; border-left: 4px solid #27AE60; color: #0A3622; padding: 12px 18px; border-radius: 8px; font-size: 0.87rem; margin-bottom: 22px; }

/* Stats */
.stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 18px; margin-bottom: 26px; }
.stat-box { background: #fff; border-radius: 14px; padding: 20px 22px; display: flex; align-items: center; gap: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #F0F0F0; transition: all 0.22s; }
.stat-box:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
.stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
.ic-or { background: rgba(200,146,42,0.1); color: #C8922A; }
.ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
.ic-blue { background: rgba(41,128,185,0.1); color: #2980B9; }
.ic-red { background: rgba(231,76,60,0.1); color: #E74C3C; }
.stat-val { font-family: 'Playfair Display',serif; font-size: 1.6rem; font-weight: 700; color: #0D0D0D; line-height: 1; }
.stat-lbl { font-size: 0.72rem; color: #999; margin-top: 3px; }

/* Toolbar */
.toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 18px; flex-wrap: wrap; }
.search-box { display: flex; align-items: center; gap: 10px; background: #fff; border: 1.5px solid #E8E8E8; border-radius: 8px; padding: 0 14px; height: 38px; transition: border-color 0.2s; flex: 1; max-width: 340px; }
.search-box:focus-within { border-color: #C8922A; }
.search-box i { color: #bbb; font-size: 0.9rem; }
.search-box input { border: none; outline: none; font-family: 'Jost',sans-serif; font-size: 0.84rem; color: #333; background: transparent; width: 100%; }

/* Table card */
.table-card { background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #F0F0F0; }
.table-card-header { display: flex; align-items: center; justify-content: space-between; padding: 17px 22px; border-bottom: 1px solid #F4F4F4; }
.table-card-title { font-family: 'Playfair Display',serif; font-size: 1.02rem; font-weight: 600; color: #0D0D0D; }
.table-count { font-size: 0.77rem; color: #999; }

table { width: 100%; border-collapse: collapse; }
thead th { background: #0D0D0D; color: #C8922A; font-family: 'Jost',sans-serif; font-size: 0.68rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; padding: 13px 18px; text-align: left; border: none; white-space: nowrap; }
tbody td { padding: 13px 18px; font-size: 0.84rem; color: #333; border-bottom: 1px solid #F8F8F8; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: #FEFBF5; }

/* Photo plat */
.plat-img { width: 52px; height: 52px; border-radius: 10px; object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.plat-img-placeholder { width: 52px; height: 52px; background: linear-gradient(135deg,#F5F0E8,#EDE0C8); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
.plat-name { font-weight: 600; color: #0D0D0D; font-size: 0.88rem; margin-bottom: 2px; }
.plat-desc { font-size: 0.74rem; color: #aaa; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px; }

/* Badges */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
.badge-plat-jour { background: linear-gradient(135deg,#C8922A,#E2B96A); color: #fff; }
.badge-visible { background: rgba(27,122,74,0.1); color: #1A7A4A; }
.badge-hidden { background: #F4F4F4; color: #aaa; }
.badge-cat { background: rgba(41,128,185,0.1); color: #2980B9; }

/* Actions */
.actions { display: flex; gap: 6px; }
.btn-act { width: 32px; height: 32px; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.88rem; text-decoration: none; transition: all 0.2s; cursor: pointer; border: none; }
.btn-edit { background: rgba(41,128,185,0.1); color: #2980B9; }
.btn-edit:hover { background: #2980B9; color: #fff; }
.btn-delete { background: rgba(231,76,60,0.1); color: #E74C3C; }
.btn-delete:hover { background: #E74C3C; color: #fff; }
.btn-star { background: rgba(200,146,42,0.1); color: #C8922A; }
.btn-star:hover { background: #C8922A; color: #fff; }
.btn-eye { background: rgba(27,122,74,0.1); color: #1A7A4A; }
.btn-eye:hover { background: #1A7A4A; color: #fff; }

/* Empty */
.empty-state { text-align: center; padding: 70px 20px; color: #ccc; }
.empty-state i { font-size: 3.5rem; display: block; margin-bottom: 16px; color: #ddd; }
.empty-state p { font-size: 0.9rem; color: #bbb; margin-bottom: 16px; }

/* Prix */
.prix { font-family: 'Playfair Display',serif; font-size: 1rem; font-weight: 700; color: #C8922A; }

/* Responsive */
@media (max-width: 1024px) { .sidebar { width: 220px; } .main { margin-left: 220px; } .stats-row { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 768px) { .sidebar { display: none; } .main { margin-left: 0; } .content { padding: 18px 14px; } .stats-row { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">AWA KA SUGU</div>
        <div class="brand-sub">Administration</div>
        <div class="admin-user">
            <div class="admin-avatar">A</div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_nom'] ?? 'Awa Doumbia') ?></div>
                <div class="admin-role">Administratrice</div>
            </div>
        </div>
    </div>
    <nav>
        <div class="nav-section">Principal</div>
        <a href="dashboard.php"       class="nav-item"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
        <a href="produits.php"        class="nav-item"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="commandes.php"       class="nav-item"><i class="bi bi-receipt"></i> Commandes</a>
        <a href="clients.php"         class="nav-item"><i class="bi bi-people"></i> Clients</a>

        <div class="nav-section">Restaurant Sofia</div>
        <a href="plats.php"           class="nav-item active"><i class="bi bi-cup-hot"></i> Plats</a>
        <a href="commandes_repas.php" class="nav-item"><i class="bi bi-bag-check"></i> Commandes repas</a>
        <a href="reservations.php"    class="nav-item"><i class="bi bi-calendar-check"></i> Réservations</a>

        <div class="nav-section">Gestion</div>
        <a href="stocks.php"          class="nav-item"><i class="bi bi-bar-chart"></i> Stocks</a>
        <a href="promotions.php"      class="nav-item"><i class="bi bi-percent"></i> Promotions</a>
        <a href="factures.php"        class="nav-item"><i class="bi bi-file-earmark-text"></i> Factures</a>
        <a href="analytics.php"       class="nav-item"><i class="bi bi-graph-up"></i> Statistiques</a>
        <a href="maintenance.php"     class="nav-item"><i class="bi bi-tools"></i> Maintenance</a>

        <div class="nav-section">Compte</div>
        <a href="../index.php" target="_blank" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php"          class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<!-- ===== MAIN ===== -->
<div class="main">

    <div class="topbar">
        <div>
            <div class="topbar-title">🍽️ Restaurant <span>Sofia</span> — Plats</div>
            <div class="topbar-sub">Administration → Gestion des plats</div>
        </div>
        <div class="topbar-right">
            <a href="../restaurant/menu.php" target="_blank" class="btn-outline-dark">
                <i class="bi bi-eye"></i> Voir le menu
            </a>
            <a href="plat_ajouter.php" class="btn-add">
                <i class="bi bi-plus-lg"></i> Ajouter un plat
            </a>
        </div>
    </div>

    <div class="content">

        <?php if($msg): ?>
        <div class="alert-ok">
            <i class="bi bi-check-circle-fill"></i>
            <?php
            $msgs = [
                'supprime'  => 'Plat supprimé avec succès.',
                'plat_jour' => 'Plat du jour mis à jour avec succès.',
                'update'    => 'Modification enregistrée.',
            ];
            echo $msgs[$msg] ?? 'Action effectuée.';
            ?>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-cup-hot-fill"></i></div>
                <div><div class="stat-val"><?= $stats_total ?></div><div class="stat-lbl">Total plats</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-eye-fill"></i></div>
                <div><div class="stat-val"><?= $stats_visibles ?></div><div class="stat-lbl">Plats visibles</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-star-fill"></i></div>
                <div><div class="stat-val"><?= $stats_plat_jour ?></div><div class="stat-lbl">Plat du jour</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-cash-stack"></i></div>
                <div><div class="stat-val"><?= number_format($stats_prix_moy, 0, ',', ' ') ?></div><div class="stat-lbl">Prix moyen (FCFA)</div></div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Rechercher un plat..." oninput="filterPlats()">
            </div>
            <div class="table-count" id="countLabel"><?= $stats_total ?> plat<?= $stats_total > 1 ? 's' : '' ?></div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">Liste des plats — Restaurant Sofia</div>
                <div class="table-count"><?= $stats_total ?> plat<?= $stats_total > 1 ? 's' : '' ?></div>
            </div>
            <table id="platsTable">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Plat</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th>Plat du jour</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($plats)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-cup-hot"></i>
                                <p>Aucun plat enregistré pour le moment.</p>
                                <a href="plat_ajouter.php" class="btn-add" style="text-decoration:none;">
                                    <i class="bi bi-plus-lg"></i> Ajouter votre premier plat
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($plats as $p): ?>
                    <tr class="plat-row">
                        <td>
                            <?php
                            $img_path = '../uploads/plats/' . $p['image'];
                            if(!empty($p['image']) && file_exists($img_path)):
                            ?>
                                <img src="<?= $img_path ?>" class="plat-img" alt="<?= htmlspecialchars($p['nom']) ?>">
                            <?php else: ?>
                                <div class="plat-img-placeholder">🍽️</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="plat-name"><?= htmlspecialchars($p['nom']) ?></div>
                            <?php if(!empty($p['description'])): ?>
                                <div class="plat-desc"><?= htmlspecialchars($p['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($p['cat_nom']): ?>
                                <span class="badge badge-cat"><?= htmlspecialchars($p['cat_nom']) ?></span>
                            <?php else: ?>
                                <span style="color:#ccc;font-size:0.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="prix"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</span>
                        </td>
                        <td>
                            <?php if($p['est_visible']): ?>
                                <span class="badge badge-visible"><i class="bi bi-eye"></i> Visible</span>
                            <?php else: ?>
                                <span class="badge badge-hidden"><i class="bi bi-eye-slash"></i> Masqué</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($p['est_plat_du_jour']): ?>
                                <span class="badge badge-plat-jour"><i class="bi bi-star-fill"></i> Plat du jour</span>
                            <?php else: ?>
                                <a href="plats.php?plat_jour=<?= $p['id'] ?>"
                                   style="font-size:0.75rem;color:#C8922A;text-decoration:none;font-weight:600;">
                                    <i class="bi bi-star"></i> Définir
                                </a>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <div class="actions" style="justify-content:center;">
                                <a href="plat_modifier.php?id=<?= $p['id'] ?>" class="btn-act btn-edit" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="plats.php?toggle=<?= $p['id'] ?>" class="btn-act btn-eye"
                                   title="<?= $p['est_visible'] ? 'Masquer' : 'Afficher' ?>">
                                    <i class="bi bi-eye<?= $p['est_visible'] ? '-slash' : '' ?>"></i>
                                </a>
                                <a href="plats.php?supprimer=<?= $p['id'] ?>"
                                   class="btn-act btn-delete" title="Supprimer"
                                   onclick="return confirm('Supprimer ce plat définitivement ?')">
                                    <i class="bi bi-trash3"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
function filterPlats() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.plat-row');
    let count = 0;
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const show = text.includes(q);
        row.style.display = show ? '' : 'none';
        if(show) count++;
    });
    document.getElementById('countLabel').textContent = count + ' plat' + (count > 1 ? 's' : '');
}
</script>
</body>
</html>