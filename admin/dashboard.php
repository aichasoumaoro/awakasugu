<?php
// ============================================
// DASHBOARD - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$admin_info = getAdminInfo();
$admin_role = $admin_info['role'] ?? 'admin';
$admin_nom = $admin_info['nom'] ?? 'Awa Doumbia';
$admin_id = $admin_info['id'] ?? 0;

$page_title = 'Tableau de bord';

$role_labels = [
    'super_admin' => 'Super Administrateur',
    'directeur' => 'Directrice',
    'admin' => 'Administratrice',
    'admin2' => 'Agente'
];
$role_colors = [
    'super_admin' => '#8E44AD',
    'directeur' => '#C8922A',
    'admin' => '#2980B9',
    'admin2' => '#7F8C8D'
];
$role_icons = [
    'super_admin' => 'bi-shield-fill-check',
    'directeur' => 'bi-crown-fill',
    'admin' => 'bi-person-badge-fill',
    'admin2' => 'bi-person-fill'
];

$role_label = $role_labels[$admin_role] ?? 'Administratrice';
$role_color = $role_colors[$admin_role] ?? '#C8922A';
$role_icon = $role_icons[$admin_role] ?? 'bi-person-badge-fill';

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Erreur BDD: " . $e->getMessage());
    die("Erreur de connexion à la base de données.");
}

// ============================================
// FONCTIONS STATISTIQUES
// ============================================
function getTotalProduits($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM produits");
    return (int)$stmt->fetchColumn();
}
function getProduitsRupture($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM produits WHERE stock <= 0");
    return (int)$stmt->fetchColumn();
}
function getTotalClients($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM clients");
    return (int)$stmt->fetchColumn();
}
function getNouveauxClientsMois($pdo) {
    $stmt = $pdo->query("
        SELECT COUNT(*) as nb FROM clients 
        WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ");
    return (int)$stmt->fetchColumn();
}
function getNbCommandesConfirmees($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM commandes WHERE statut IN ('confirmee', 'livree', 'terminee')");
    return (int)$stmt->fetchColumn();
}
function getNbCommandesAttente($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM commandes WHERE statut = 'en_attente'");
    return (int)$stmt->fetchColumn();
}
function getNbVentesSurPlace($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM ventes_boutique WHERE statut = 'confirmee'");
    return (int)$stmt->fetchColumn();
}
function getNbPaiementsAttente($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM paiements WHERE statut = 'en_attente'");
    return (int)$stmt->fetchColumn();
}
function getCaCommandesTotal($pdo) {
    $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM commandes WHERE statut IN ('confirmee', 'livree', 'terminee')");
    return (float)$stmt->fetchColumn();
}
function getCaCommandesMois($pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total), 0) as total FROM commandes 
        WHERE statut IN ('confirmee', 'livree', 'terminee')
        AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ");
    return (float)$stmt->fetchColumn();
}
function getCaVentesSurPlaceTotal($pdo) {
    $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM ventes_boutique WHERE statut = 'confirmee'");
    return (float)$stmt->fetchColumn();
}
function getCaVentesSurPlaceMois($pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total), 0) as total FROM ventes_boutique 
        WHERE statut = 'confirmee'
        AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ");
    return (float)$stmt->fetchColumn();
}
function getAchatsMois($pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total_ligne), 0) as total FROM achats 
        WHERE MONTH(date_achat) = MONTH(CURDATE()) AND YEAR(date_achat) = YEAR(CURDATE())
    ");
    return (float)$stmt->fetchColumn();
}

$total_produits = getTotalProduits($pdo);
$produits_rupture = getProduitsRupture($pdo);
$total_clients = getTotalClients($pdo);
$clients_mois = getNouveauxClientsMois($pdo);
$total_commandes = getNbCommandesConfirmees($pdo);
$commandes_attente = getNbCommandesAttente($pdo);
$ca_commandes_total = getCaCommandesTotal($pdo);
$ca_commandes_mois = getCaCommandesMois($pdo);
$total_ventes_boutique = getNbVentesSurPlace($pdo);
$ca_ventes_boutique_total = getCaVentesSurPlaceTotal($pdo);
$ca_ventes_boutique_mois = getCaVentesSurPlaceMois($pdo);
$nb_paiements_attente = getNbPaiementsAttente($pdo);
$achats_mois = getAchatsMois($pdo);

$ca_total_mois = $ca_commandes_mois + $ca_ventes_boutique_mois;
$ca_total_global = $ca_commandes_total + $ca_ventes_boutique_total;
$benefice_brut = $ca_total_mois - $achats_mois;

$reservations_aujourdhui = $pdo->query("SELECT COUNT(*) FROM reservations WHERE date_reservation = CURDATE()")->fetchColumn();
$reservations_attente = $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn();

$nouveaux_clients = $pdo->query("SELECT id, nom, telephone, created_at FROM clients ORDER BY created_at DESC LIMIT 5")->fetchAll();

$commandes_en_attente = $pdo->query("
    SELECT c.*, cl.nom as client_nom, cl.telephone as client_telephone 
    FROM commandes c
    LEFT JOIN clients cl ON cl.id = c.client_id
    WHERE c.statut = 'en_attente'
    ORDER BY c.created_at DESC 
    LIMIT 20
")->fetchAll();

$alertes_stock = $pdo->query("
    SELECT * FROM produits WHERE stock <= seuil_alerte AND stock > 0 ORDER BY stock ASC LIMIT 5
")->fetchAll();

$ventes_mois = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) FROM commandes 
        WHERE statut IN ('confirmee', 'livree', 'terminee') AND MONTH(created_at) = ? AND YEAR(created_at) = YEAR(CURDATE())
    ");
    $stmt->execute([$i]);
    $ventes_mois[$i] = (float)$stmt->fetchColumn();
}

$ventes_boutique_mois_graph = [];
for($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) FROM ventes_boutique 
        WHERE statut = 'confirmee' AND MONTH(created_at) = ? AND YEAR(created_at) = YEAR(CURDATE())
    ");
    $stmt->execute([$i]);
    $ventes_boutique_mois_graph[$i] = (float)$stmt->fetchColumn();
}

// ============================================
// TOP CATÉGORIES (barres horizontales)
// ============================================
$top_categories = [];
try {
    $stmt = $pdo->query("
        SELECT cat.nom, COUNT(p.id) as nb
        FROM produits p
        JOIN categories cat ON cat.id = p.categorie_id
        GROUP BY cat.id, cat.nom
        ORDER BY nb DESC
        LIMIT 5
    ");
    $top_categories = $stmt->fetchAll();
} catch(PDOException $e) {
    $top_categories = [];
}
$max_cat_nb = 1;
foreach ($top_categories as $tc) {
    if ($tc['nb'] > $max_cat_nb) $max_cat_nb = $tc['nb'];
}

$maintenance_status = $pdo->query("SELECT site_actif, message_maintenance FROM maintenance_globale ORDER BY id DESC LIMIT 1")->fetch();
$site_en_maintenance = $maintenance_status && $maintenance_status['site_actif'] == 0;

$commandes_attente_count = getNbCommandesAttente($pdo);
$nb_ventes_boutique = getNbVentesSurPlace($pdo);
$nb_paiements_attente_count = getNbPaiementsAttente($pdo);

// ============================================
// SUPPRESSION D'UNE COMMANDE
// ============================================
$delete_message = '';
$delete_error = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $commande_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT statut FROM commandes WHERE id = ?");
        $stmt->execute([$commande_id]);
        $commande = $stmt->fetch();
        if ($commande) {
            $stmt = $pdo->prepare("DELETE FROM details_commande WHERE commande_id = ?");
            $stmt->execute([$commande_id]);
            $stmt = $pdo->prepare("DELETE FROM commandes WHERE id = ?");
            $stmt->execute([$commande_id]);
            $delete_message = "La commande a été supprimée avec succès.";
            $commandes_attente_count = getNbCommandesAttente($pdo);
        } else {
            $delete_error = "Commande introuvable.";
        }
    } catch(PDOException $e) {
        $delete_error = "Erreur lors de la suppression.";
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="main">

    <div class="topbar">
        <div>
            <div class="topbar-title">📊 Tableau de <span>bord</span></div>
            <div class="topbar-breadcrumb">Administration → Vue d'ensemble</div>
        </div>
        <div class="topbar-right">
            <a href="commandes.php?filtre=en_attente" class="alert-badge <?= $commandes_attente_count > 0 ? 'has-orders' : 'no-orders' ?>">
                <span class="alert-icon"><i class="bi bi-bell-fill"></i></span>
                <span class="alert-text"><?= $commandes_attente_count ?> commande(s) en attente</span>
                <?php if($commandes_attente_count > 0): ?><span class="alert-count">!</span><?php endif; ?>
            </a>
            <?php if($admin_role === 'super_admin' || $admin_role === 'directeur' || $admin_role === 'admin'): ?>
            <a href="point_de_vente.php" class="btn-admin btn-primary"><i class="bi bi-cash-stack"></i> Nouvelle vente</a>
            <?php endif; ?>
            <a href="maintenance.php" class="btn-admin btn-maintenance">
                <i class="bi bi-tools"></i> <?= $site_en_maintenance ? '🔴 Maintenance active' : '🟢 Site actif' ?>
            </a>
            <a href="../index.php" class="btn-admin btn-site"><i class="bi bi-eye"></i> Voir le site</a>
        </div>
    </div>

    <div class="content">

        <?php if($delete_message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($delete_message) ?></div>
        <?php endif; ?>
        <?php if($delete_error): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($delete_error) ?></div>
        <?php endif; ?>

        <div class="welcome-card">
            <div>
                <h2>
                    Bonjour, <span><?= htmlspecialchars($admin_nom) ?></span>
                    <span class="role-badge" style="background: <?= $role_color ?>;">
                        <i class="bi <?= $role_icon ?>"></i> <?= $role_label ?>
                    </span>
                </h2>
                <p>
                    <?php if($admin_role === 'super_admin'): ?>
                        <i class="bi bi-shield-fill-check" style="color:#8E44AD;"></i> Vous avez tous les droits sur la plateforme.
                    <?php elseif($admin_role === 'directeur'): ?>
                        <i class="bi bi-crown-fill" style="color:#C8922A;"></i> Vous gérez l'ensemble de la boutique.
                    <?php elseif($admin_role === 'admin'): ?>
                        <i class="bi bi-person-badge-fill" style="color:#2980B9;"></i> Vous gérez les commandes, les stocks et le restaurant.
                    <?php else: ?>
                        <i class="bi bi-truck" style="color:#7F8C8D;"></i> Vous gérez les livraisons et les commandes.
                    <?php endif; ?>
                    — Voici un résumé de votre activité sur Awa Ka Sugu.
                </p>
            </div>
            <div class="welcome-icon"><i class="bi <?= $role_icon ?>" style="color: <?= $role_color ?>;"></i></div>
        </div>

        <!-- ============================================
             STATISTIQUES - SUPER ADMIN & DIRECTEUR
             ============================================ -->
        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Produits</span>
                    <div class="stat-icon ic-or"><i class="bi bi-box-seam-fill"></i></div>
                </div>
                <div class="stat-val"><?= $total_produits ?></div>
                <div class="stat-lbl"><?= $produits_rupture ?> en rupture</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Clients</span>
                    <div class="stat-icon ic-blue"><i class="bi bi-people"></i></div>
                </div>
                <div class="stat-val"><?= $total_clients ?></div>
                <div class="stat-lbl">+<?= $clients_mois ?> ce mois</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">CA du mois</span>
                    <div class="stat-icon ic-green"><i class="bi bi-cash"></i></div>
                </div>
                <div class="stat-val"><?= number_format($ca_total_mois, 0, ',', ' ') ?> F</div>
                <div class="stat-lbl">Global (boutique + commandes)</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Bénéfice</span>
                    <div class="stat-icon ic-purple"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
                <div class="stat-val"><?= number_format($benefice_brut, 0, ',', ' ') ?> F</div>
                <div class="stat-lbl">Bénéfice brut du mois</div>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Commandes</span>
                    <div class="stat-icon ic-or"><i class="bi bi-cart"></i></div>
                </div>
                <div class="stat-val"><?= $total_commandes ?></div>
                <div class="stat-lbl">Validées</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Ventes</span>
                    <div class="stat-icon ic-or"><i class="bi bi-cash-stack"></i></div>
                </div>
                <div class="stat-val"><?= $nb_ventes_boutique ?></div>
                <div class="stat-lbl">Sur place</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Paiements</span>
                    <div class="stat-icon ic-gold"><i class="bi bi-credit-card"></i></div>
                </div>
                <div class="stat-val"><?= $nb_paiements_attente_count ?></div>
                <div class="stat-lbl">En attente</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Commandes</span>
                    <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                </div>
                <div class="stat-val"><?= $commandes_attente_count ?></div>
                <div class="stat-lbl">En attente</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================
             STATISTIQUES - ADMIN
             ============================================ -->
        <?php if($admin_role === 'admin'): ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Produits</span>
                    <div class="stat-icon ic-or"><i class="bi bi-box-seam-fill"></i></div>
                </div>
                <div class="stat-val"><?= $total_produits ?></div>
                <div class="stat-lbl">En stock</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">À traiter</span>
                    <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                </div>
                <div class="stat-val"><?= $commandes_attente_count ?></div>
                <div class="stat-lbl">Commandes</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Traitées</span>
                    <div class="stat-icon ic-or"><i class="bi bi-cart"></i></div>
                </div>
                <div class="stat-val"><?= $total_commandes ?></div>
                <div class="stat-lbl">Commandes</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Clients</span>
                    <div class="stat-icon ic-blue"><i class="bi bi-people"></i></div>
                </div>
                <div class="stat-val"><?= $total_clients ?></div>
                <div class="stat-lbl">Inscrits</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================
             STATISTIQUES - AGENT
             ============================================ -->
        <?php if($admin_role === 'admin2'): ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">À livrer</span>
                    <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                </div>
                <div class="stat-val"><?= $commandes_attente_count ?></div>
                <div class="stat-lbl">Commandes</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Livrées</span>
                    <div class="stat-icon ic-or"><i class="bi bi-check-circle"></i></div>
                </div>
                <div class="stat-val"><?= $total_commandes ?></div>
                <div class="stat-lbl">Commandes</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Livraisons</span>
                    <div class="stat-icon ic-blue"><i class="bi bi-truck"></i></div>
                </div>
                <div class="stat-val"><?= $nb_ventes_boutique ?></div>
                <div class="stat-lbl">Effectuées</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-top">
                    <span class="stat-box-label">Aujourd'hui</span>
                    <div class="stat-icon ic-gold"><i class="bi bi-calendar"></i></div>
                </div>
                <div class="stat-val" style="font-size:1.1rem;"><?= date('d/m/Y') ?></div>
                <div class="stat-lbl">Date du jour</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================
             COMMANDES EN ATTENTE
             ============================================ -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-clock-history"></i> Commandes à traiter
                    <span style="font-size:0.65rem;font-weight:normal;background:var(--border-soft);padding:2px 10px;border-radius:12px;margin-left:8px;">
                        <?= count($commandes_en_attente) ?> en attente
                    </span>
                </div>
                <a href="commandes.php?filtre=en_attente" class="btn-small green"><i class="bi bi-check2-circle"></i> Gérer</a>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-commandes">
                        <thead>
                            <tr>
                                <th>N° Commande</th><th>Client</th><th>Date</th><th>Total</th><th>Paiement</th><th>Statut</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($commandes_en_attente)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;padding:25px;color:var(--text-secondary);">
                                        <i class="bi bi-check-circle" style="font-size:1.2rem;display:block;margin-bottom:5px;color:#27AE60;"></i>
                                        Aucune commande en attente
                                    </td>
                                </tr>
                            <?php else: 
                                foreach($commandes_en_attente as $c):
                                    $statutLabels = [
                                        'en_attente' => 'En attente', 'confirmee' => 'Confirmée', 'en_preparation' => 'Préparation',
                                        'en_livraison' => 'Livraison', 'livree' => 'Livrée', 'terminee' => 'Terminée', 'annulee' => 'Annulée'
                                    ];
                                    $statutLabel = $statutLabels[$c['statut']] ?? $c['statut'];
                            ?>
                                <tr>
                                    <td class="fw-600"><?= htmlspecialchars($c['numero_commande'] ?? 'N/A') ?></td>
                                    <td>
                                        <?= htmlspecialchars($c['client_nom'] ?? 'Inconnu') ?>
                                        <div class="text-muted" style="font-size:0.65rem;"><?= htmlspecialchars($c['client_telephone'] ?? '') ?></div>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                                    <td class="text-gold fw-600"><?= number_format($c['total'], 0, ',', ' ') ?> F</td>
                                    <td>
                                        <span style="background:rgba(200,146,42,0.1);padding:2px 10px;border-radius:12px;font-size:0.6rem;color:#C8922A;">
                                            <?= htmlspecialchars($c['mode_paiement'] ?? 'Non défini') ?>
                                        </span>
                                    </td>
                                    <td><span class="badge-statut statut-<?= $c['statut'] ?>"><?= $statutLabel ?></span></td>
                                    <td>
                                        <div class="actions">
                                            <a href="commande_detail.php?id=<?= $c['id'] ?>" class="btn-small blue" title="Voir le détail"><i class="bi bi-eye"></i></a>
                                            <a href="commande_pdf.php?id=<?= $c['id'] ?>" class="btn-small or" title="PDF" target="_blank"><i class="bi bi-file-pdf"></i></a>
                                            <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
                                            <button onclick="confirmDelete('Supprimer cette commande ?')" class="btn-small red" title="Supprimer"><i class="bi bi-trash3"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================
             GRILLE 2 COLONNES - SUPER ADMIN & DIRECTEUR
             ============================================ -->
        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
        <div class="dashboard-grid">
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-calendar-check"></i> Réservations</div>
                    <a href="reservations.php" class="btn-small or">Voir tout</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:14px;">
                        <div style="text-align:center;background:var(--border-soft);border-radius:8px;padding:12px;border:1px solid var(--border-color);">
                            <div style="font-size:1.3rem;font-weight:700;color:#2980B9;"><?= $reservations_aujourdhui ?></div>
                            <div style="font-size:0.6rem;color:var(--text-secondary);">Aujourd'hui</div>
                        </div>
                        <div style="text-align:center;background:var(--border-soft);border-radius:8px;padding:12px;border:1px solid var(--border-color);">
                            <div style="font-size:1.3rem;font-weight:700;color:#E67E22;"><?= $reservations_attente ?></div>
                            <div style="font-size:0.6rem;color:var(--text-secondary);">En attente</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-people"></i> Nouveaux clients</div>
                    <a href="clients.php" class="btn-small or">Voir tout</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if(empty($nouveaux_clients)): ?>
                        <div class="empty-state"><i class="bi bi-people"></i><p>Aucun nouveau client</p></div>
                    <?php else: ?>
                        <?php foreach($nouveaux_clients as $c): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;border-bottom:1px solid var(--border-soft);">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#C8922A,#E8B55A);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:#fff;flex-shrink:0;">
                                    <?= strtoupper(mb_substr($c['nom'] ?? 'C', 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:600;color:var(--text-primary);font-size:0.85rem;"><?= htmlspecialchars($c['nom'] ?? 'Inconnu') ?></div>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);">
                                        <i class="bi bi-telephone" style="font-size:0.55rem;"></i> <?= htmlspecialchars($c['telephone'] ?? '') ?>
                                        <span style="margin:0 4px;">•</span>
                                        <i class="bi bi-calendar3" style="font-size:0.55rem;"></i> <?= date('d/m/Y', strtotime($c['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.55rem;font-weight:600;background:rgba(200,146,42,0.1);color:#C8922A;">
                                <i class="bi bi-person-plus"></i> Client
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============================================
             DONUT (répartition CA) + TOP CATÉGORIES
             ============================================ -->
        <div class="dashboard-grid">
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-pie-chart-fill"></i> Répartition du CA (ce mois)</div>
                </div>
                <div class="card-body donut-card-body">
                    <div class="donut-wrap">
                        <canvas id="donutCA"></canvas>
                        <div class="donut-center">
                            <div class="donut-total"><?= number_format($ca_total_mois, 0, ',', ' ') ?></div>
                            <div class="donut-sub">FCFA</div>
                        </div>
                    </div>
                    <div class="donut-legend">
                        <div class="donut-legend-item">
                            <span class="donut-legend-dot" style="background:#C8922A;"></span>
                            Commandes en ligne
                            <span class="donut-legend-pct"><?= $ca_total_mois > 0 ? round($ca_commandes_mois / $ca_total_mois * 100) : 0 ?>%</span>
                        </div>
                        <div class="donut-legend-item">
                            <span class="donut-legend-dot" style="background:#2980B9;"></span>
                            Ventes sur place
                            <span class="donut-legend-pct"><?= $ca_total_mois > 0 ? round($ca_ventes_boutique_mois / $ca_total_mois * 100) : 0 ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-tags-fill"></i> Top catégories</div>
                    <a href="produits.php" class="btn-small or">Voir tout</a>
                </div>
                <div class="card-body">
                    <?php if(empty($top_categories)): ?>
                        <div class="empty-state"><i class="bi bi-tags"></i><p>Aucune catégorie renseignée</p></div>
                    <?php else: ?>
                        <?php foreach($top_categories as $tc): 
                            $pct = round($tc['nb'] / $max_cat_nb * 100);
                        ?>
                        <div class="hbar-item">
                            <div class="hbar-top">
                                <span><?= htmlspecialchars($tc['nom']) ?></span>
                                <span><?= $tc['nb'] ?> produit<?= $tc['nb'] > 1 ? 's' : '' ?></span>
                            </div>
                            <div class="hbar-track"><div class="hbar-fill" style="width:<?= $pct ?>%;"></div></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================
             GRILLE 2 COLONNES - ADMIN
             ============================================ -->
        <?php if($admin_role === 'admin'): ?>
        <div class="dashboard-grid">
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-calendar-check"></i> Réservations</div>
                    <a href="reservations.php" class="btn-small or">Voir tout</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:14px;">
                        <div style="text-align:center;background:var(--border-soft);border-radius:8px;padding:12px;border:1px solid var(--border-color);">
                            <div style="font-size:1.3rem;font-weight:700;color:#2980B9;"><?= $reservations_aujourdhui ?></div>
                            <div style="font-size:0.6rem;color:var(--text-secondary);">Aujourd'hui</div>
                        </div>
                        <div style="text-align:center;background:var(--border-soft);border-radius:8px;padding:12px;border:1px solid var(--border-color);">
                            <div style="font-size:1.3rem;font-weight:700;color:#E67E22;"><?= $reservations_attente ?></div>
                            <div style="font-size:0.6rem;color:var(--text-secondary);">En attente</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-receipt"></i> Commandes récentes</div>
                    <a href="commandes.php" class="btn-small or">Voir tout</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if(empty($commandes_en_attente)): ?>
                        <div class="empty-state"><i class="bi bi-receipt"></i><p>Aucune commande récente</p></div>
                    <?php else: 
                        $commandes_recentes = array_slice($commandes_en_attente, 0, 5);
                        foreach($commandes_recentes as $c): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;border-bottom:1px solid var(--border-soft);">
                            <div>
                                <div class="fw-600"><?= htmlspecialchars($c['numero_commande'] ?? 'N/A') ?></div>
                                <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($c['client_nom'] ?? 'Inconnu') ?></div>
                            </div>
                            <span style="color:var(--text-secondary);font-size:0.65rem;"><?= number_format($c['total'], 0, ',', ' ') ?> F</span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================
             GRILLE 2 COLONNES - AGENT
             ============================================ -->
        <?php if($admin_role === 'admin2'): ?>
        <div class="dashboard-grid">
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-truck"></i> Commandes à livrer</div>
                    <a href="commandes.php?filtre=en_attente" class="btn-small green">Voir tout</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if(empty($commandes_en_attente)): ?>
                        <div class="empty-state"><i class="bi bi-check-circle" style="color:#27AE60;"></i><p>Aucune commande à livrer</p></div>
                    <?php else: 
                        foreach(array_slice($commandes_en_attente, 0, 5) as $c): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;border-bottom:1px solid var(--border-soft);">
                            <div>
                                <div class="fw-600"><?= htmlspecialchars($c['numero_commande'] ?? 'N/A') ?></div>
                                <div class="text-muted" style="font-size:0.75rem;">
                                    <?= htmlspecialchars($c['client_nom'] ?? 'Inconnu') ?>
                                    <span style="display:block;font-size:0.6rem;">📍 Livraison à Bamako</span>
                                </div>
                            </div>
                            <a href="commande_detail.php?id=<?= $c['id'] ?>" class="btn-small blue"><i class="bi bi-eye"></i></a>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="card-white">
                <div class="card-header"><div class="card-title"><i class="bi bi-calendar-check"></i> Livraisons du jour</div></div>
                <div class="card-body" style="padding:0;">
                    <div style="padding:14px;text-align:center;">
                        <div style="font-size:1.8rem;font-weight:700;color:#C8922A;"><?= $total_commandes ?></div>
                        <div style="color:var(--text-secondary);font-size:0.75rem;">Commandes à livrer aujourd'hui</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================
             GRAPHIQUE - SUPER ADMIN & DIRECTEUR
             ============================================ -->
        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-graph-up"></i> Ventes mensuelles
                    <span style="font-size:0.65rem;font-weight:normal;background:var(--border-soft);padding:2px 8px;border-radius:12px;margin-left:8px;"><?= date('Y') ?></span>
                </div>
            </div>
            <div class="card-body">
                <canvas id="ventesChart" height="200"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================
             ALERTES STOCK
             ============================================ -->
        <?php if(!empty($alertes_stock) && ($admin_role === 'super_admin' || $admin_role === 'directeur' || $admin_role === 'admin')): ?>
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-exclamation-triangle-fill"></i> Stock faible</div>
                <a href="produits.php" class="btn-small or">Voir tout</a>
            </div>
            <div class="card-body">
                <?php foreach($alertes_stock as $p): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 14px;background:rgba(230,126,34,0.08);border-left:3px solid #E67E22;border-radius:6px;margin-bottom:6px;gap:10px;flex-wrap:wrap;">
                    <span style="color:var(--text-primary);font-size:0.8rem;">
                        <strong><?= htmlspecialchars($p['nom']) ?></strong> — Stock restant : <strong><?= $p['stock'] ?></strong>
                    </span>
                    <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
                    <a href="produit_modifier.php?id=<?= $p['id'] ?>" class="btn-small or">Réapprovisionner</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function confirmDelete(message) {
    return confirm(message || 'Êtes-vous sûr de vouloir effectuer cette action ?');
}

<?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
const gridColor = isDark ? 'rgba(255,255,255,0.06)' : '#F0F2F5';
const tickColor = isDark ? 'rgba(255,255,255,0.5)' : '#8A99AA';
const cardBg = isDark ? '#1B2028' : '#ffffff';

// ===== GRAPHIQUE VENTES MENSUELLES =====
const ctx = document.getElementById('ventesChart').getContext('2d');
const labels = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
const commandesData = <?= json_encode(array_values($ventes_mois)) ?>;
const boutiqueData = <?= json_encode(array_values($ventes_boutique_mois_graph)) ?>;

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            { label: 'Commandes en ligne', data: commandesData, backgroundColor: 'rgba(200,146,42,0.7)', borderColor: '#C8922A', borderWidth: 1, borderRadius: 4 },
            { label: 'Ventes sur place', data: boutiqueData, backgroundColor: 'rgba(41,128,185,0.7)', borderColor: '#2980B9', borderWidth: 1, borderRadius: 4 }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'top', labels: { font: { family: 'Jost', size: 10 }, boxWidth: 12, padding: 10, color: tickColor } },
            tooltip: {
                backgroundColor: '#1A2C3E', titleColor: '#C8922A', bodyColor: '#fff',
                callbacks: { label: ctx => ctx.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA' }
            }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, callback: v => v >= 1000000 ? (v/1000000)+'M' : v >= 1000 ? (v/1000)+'k' : v } },
            x: { grid: { display: false }, ticks: { color: tickColor } }
        }
    }
});

// ===== DONUT RÉPARTITION CA =====
const donutCtx = document.getElementById('donutCA').getContext('2d');
new Chart(donutCtx, {
    type: 'doughnut',
    data: {
        labels: ['Commandes en ligne', 'Ventes sur place'],
        datasets: [{
            data: [<?= $ca_commandes_mois ?>, <?= $ca_ventes_boutique_mois ?>],
            backgroundColor: ['#C8922A', '#2980B9'],
            borderColor: cardBg,
            borderWidth: 3,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '72%',
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1A2C3E', titleColor: '#C8922A', bodyColor: '#fff',
                callbacks: { label: ctx => ctx.label + ': ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA' }
            }
        }
    }
});

// Redessiner les graphiques en cas de rotation d'écran mobile
window.addEventListener('resize', function() {
    Chart.instances && Object.values(Chart.instances).forEach(c => c.resize());
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>