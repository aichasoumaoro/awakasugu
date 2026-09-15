<?php
// ============================================
// COMMANDES CLIENT - ADMIN AWA KA SUGU
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

$page_title = 'Commandes du client';

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

$telephone = isset($_GET['telephone']) ? $_GET['telephone'] : '';

if (empty($telephone)) {
    header('Location: commandes.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES COMMANDES DU CLIENT
// ============================================
$stmt = $pdo->prepare("
    SELECT * FROM commandes 
    WHERE telephone = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$telephone]);
$commandes = $stmt->fetchAll();

if (empty($commandes)) {
    header('Location: commandes.php');
    exit;
}

$client_nom = $commandes[0]['nom_client'];

// ============================================
// CALCUL DU TOTAL DÉPENSÉ (EXCLUT LES COMMANDES ANNULÉES)
// ============================================
$total_global = 0;
$total_annule = 0;
$nb_commandes = 0;
$nb_annulees = 0;

foreach($commandes as $c) {
    if ($c['statut'] == 'annulee') {
        $total_annule += $c['total'];
        $nb_annulees++;
    } else {
        $total_global += $c['total'];
        $nb_commandes++;
    }
}

// ============================================
// STATUTS POUR L'AFFICHAGE
// ============================================
$statut_labels = [
    'en_attente' => 'En attente',
    'confirmee' => 'Confirmée',
    'en_preparation' => 'Préparation',
    'en_livraison' => 'Livraison',
    'livree' => 'Livrée',
    'terminee' => 'Terminée',
    'annulee' => 'Annulée'
];

$statut_colors = [
    'en_attente' => '#FFC107',
    'confirmee' => '#28A745',
    'en_preparation' => '#17A2B8',
    'en_livraison' => '#6C757D',
    'livree' => '#27AE60',
    'terminee' => '#28A745',
    'annulee' => '#DC3545'
];

// ============================================
// SUPPRIMER UNE COMMANDE (ACTION)
// ============================================
if (isset($_GET['supprimer']) && is_numeric($_GET['supprimer'])) {
    $commande_id = (int)$_GET['supprimer'];
    // Vérifier que la commande appartient bien au client
    $stmt = $pdo->prepare("SELECT id FROM commandes WHERE id = ? AND telephone = ?");
    $stmt->execute([$commande_id, $telephone]);
    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM details_commande WHERE commande_id = ?")->execute([$commande_id]);
        $pdo->prepare("DELETE FROM commandes WHERE id = ?")->execute([$commande_id]);
        $_SESSION['message_commande'] = "Commande supprimée avec succès.";
        header("Location: commandes_client.php?telephone=" . urlencode($telephone));
        exit;
    }
}

// ============================================
// RÉCUPÉRER LE MESSAGE DE SESSION
// ============================================
$message = $_SESSION['message_commande'] ?? '';
unset($_SESSION['message_commande']);

// Maintenance
$maintenance_status = $pdo->query("
    SELECT site_actif, message_maintenance 
    FROM maintenance_globale 
    ORDER BY id DESC LIMIT 1
")->fetch();
$site_en_maintenance = $maintenance_status && $maintenance_status['site_actif'] == 0;

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
            <div class="topbar-title">👤 Commandes de <span><?= htmlspecialchars($client_nom) ?></span></div>
            <div class="topbar-breadcrumb">
                <a href="dashboard.php" style="color:#8A99AA;text-decoration:none;">Administration</a> &gt; 
                <a href="commandes.php" style="color:#8A99AA;text-decoration:none;">Commandes</a> &gt; 
                Client
            </div>
        </div>
        <div class="topbar-right">
            <a href="commandes.php" class="btn-admin btn-outline">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <?php if($message): ?>
            <div class="alert-success" style="background:#D4EDDA;color:#155724;padding:12px 18px;border-radius:10px;margin-bottom:20px;border-left:4px solid #28A745;">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- ===== CARTE CLIENT ===== -->
        <div style="background:#fff;border-radius:12px;padding:20px 24px;margin-bottom:24px;border:1px solid rgba(200,146,42,0.12);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
            <div>
                <h3 style="font-size:1.1rem;color:#0D0D0D;">
                    <i class="bi bi-person" style="color:#C8922A;"></i> <?= htmlspecialchars($client_nom) ?>
                </h3>
                <p style="color:#8A99AA;font-size:0.85rem;margin:0;">
                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($telephone) ?>
                </p>
                <p style="color:#8A99AA;font-size:0.85rem;margin:0;">
                    <i class="bi bi-receipt"></i> 
                    <?= $nb_commandes ?> commande(s) validée(s)
                    <?php if($nb_annulees > 0): ?>
                        <span style="color:#E74C3C;">• <?= $nb_annulees ?> annulée(s)</span>
                    <?php endif; ?>
                </p>
            </div>
            <div style="text-align:right;">
                <div style="color:#8A99AA;font-size:0.7rem;">Total dépensé (hors annulations)</div>
                <div style="font-family:'Playfair Display',serif;font-size:1.8rem;color:#C8922A;font-weight:700;">
                    <?= number_format($total_global, 0, ',', ' ') ?> F
                </div>
                <?php if($total_annule > 0): ?>
                    <div style="font-size:0.7rem;color:#E74C3C;">
                        <i class="bi bi-x-circle"></i> Annulé: <?= number_format($total_annule, 0, ',', ' ') ?> F
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== TABLEAU DES COMMANDES ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Toutes ses commandes</div>
                <div class="text-muted" style="font-size:0.8rem;">
                    <?= count($commandes) ?> commande(s) au total
                    <?php if($nb_annulees > 0): ?>
                        <span style="color:#E74C3C;">• <?= $nb_annulees ?> annulée(s)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-commandes">
                        <thead>
                            <tr>
                                <th>N° Commande</th>
                                <th>Date</th>
                                <th style="text-align:right;">Total</th>
                                <th>Paiement</th>
                                <th>Statut</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $paiements = [
                                'livraison' => '💵 Livraison',
                                'orange_money' => '🟠 Orange Money',
                                'wave' => '🌊 Wave',
                                'moov_money' => '📱 Moov Money',
                                'carte' => '💳 Carte',
                                'especes' => '💰 Espèces'
                            ];
                            ?>
                            <?php foreach($commandes as $c): 
                                $statut = $c['statut'];
                                $statut_label = $statut_labels[$statut] ?? $statut;
                                $statut_color = $statut_colors[$statut] ?? '#6C757D';
                                $est_annulee = ($statut == 'annulee');
                            ?>
                            <tr style="<?= $est_annulee ? 'opacity:0.6;' : '' ?>">
                                <td>
                                    <strong><?= htmlspecialchars($c['numero_commande']) ?></strong>
                                    <?php if($est_annulee): ?>
                                        <span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:0.55rem;font-weight:600;background:#F8D7DA;color:#721C24;margin-left:5px;">
                                            ANNULÉE
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.8rem;color:#8A99AA;">
                                    <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                                </td>
                                <td style="text-align:right;font-weight:600;color:<?= $est_annulee ? '#E74C3C' : '#C8922A' ?>;">
                                    <?= number_format($c['total'], 0, ',', ' ') ?> F
                                </td>
                                <td>
                                    <span style="background:rgba(200,146,42,0.1);padding:3px 12px;border-radius:20px;font-size:0.65rem;font-weight:600;color:#C8922A;">
                                        <?= $paiements[$c['mode_paiement']] ?? $c['mode_paiement'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-statut statut-<?= $statut ?>" style="background:<?= $statut_color ?>20;color:<?= $statut_color ?>;padding:3px 12px;border-radius:20px;font-size:0.65rem;font-weight:600;">
                                        <?= $statut_label ?>
                                    </span>
                                </td>
                                <td style="text-align:center;white-space:nowrap;">
                                    <a href="commande_detail.php?id=<?= $c['id'] ?>" class="btn-small blue" title="Voir détails">
                                        <i class="bi bi-eye"></i> Détail
                                    </a>
                                    <?php if(!$est_annulee): ?>
                                    <a href="generer_facture.php?id=<?= $c['id'] ?>" class="btn-small red" title="Générer la facture">
                                        <i class="bi bi-file-pdf"></i> PDF
                                    </a>
                                    <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
                                    <a href="commandes_client.php?telephone=<?= urlencode($telephone) ?>&supprimer=<?= $c['id'] ?>" 
                                       class="btn-small red" 
                                       onclick="return confirm('Supprimer définitivement cette commande ?')" 
                                       title="Supprimer">
                                        <i class="bi bi-trash3"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== RÉSUMÉ DES STATISTIQUES ===== -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-top:20px;">
            <div style="background:#fff;border-radius:12px;padding:15px;text-align:center;border:1px solid #F0EDEA;">
                <div style="font-size:0.7rem;color:#8A99AA;">Total commandes</div>
                <div style="font-size:1.3rem;font-weight:700;color:#0D0D0D;"><?= count($commandes) ?></div>
            </div>
            <div style="background:#fff;border-radius:12px;padding:15px;text-align:center;border:1px solid #F0EDEA;">
                <div style="font-size:0.7rem;color:#8A99AA;">Commandes validées</div>
                <div style="font-size:1.3rem;font-weight:700;color:#27AE60;"><?= $nb_commandes ?></div>
            </div>
            <div style="background:#fff;border-radius:12px;padding:15px;text-align:center;border:1px solid #F0EDEA;">
                <div style="font-size:0.7rem;color:#8A99AA;">Commandes annulées</div>
                <div style="font-size:1.3rem;font-weight:700;color:#E74C3C;"><?= $nb_annulees ?></div>
            </div>
            <div style="background:#fff;border-radius:12px;padding:15px;text-align:center;border:1px solid #F0EDEA;">
                <div style="font-size:0.7rem;color:#8A99AA;">Total dépensé</div>
                <div style="font-size:1.3rem;font-weight:700;color:#C8922A;"><?= number_format($total_global, 0, ',', ' ') ?> F</div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     STYLES SUPPLÉMENTAIRES
     ============================================ -->
<style>
.statut-annulee {
    background: #F8D7DA !important;
    color: #721C24 !important;
}
.alert-success {
    animation: fadeIn 0.5s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>