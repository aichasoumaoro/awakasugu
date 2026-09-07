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

// Récupérer les commandes du client
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
$total_global = 0;
foreach($commandes as $c) {
    $total_global += $c['total'];
}

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
                    <i class="bi bi-receipt"></i> <?= count($commandes) ?> commande(s)
                </p>
            </div>
            <div style="text-align:right;">
                <div style="color:#8A99AA;font-size:0.7rem;">Total dépensé</div>
                <div style="font-family:'Playfair Display',serif;font-size:1.8rem;color:#C8922A;font-weight:700;">
                    <?= number_format($total_global, 0, ',', ' ') ?> F
                </div>
            </div>
        </div>

        <!-- ===== TABLEAU DES COMMANDES ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Toutes ses commandes</div>
                <div class="text-muted" style="font-size:0.8rem;"><?= count($commandes) ?> commande(s)</div>
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
                            $statut_labels = [
                                'en_attente' => 'En attente',
                                'confirmee' => 'Confirmée',
                                'en_preparation' => 'Préparation',
                                'en_livraison' => 'Livraison',
                                'livree' => 'Livrée',
                                'annulee' => 'Annulée'
                            ];
                            ?>
                            <?php foreach($commandes as $c): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($c['numero_commande']) ?></strong>
                                </td>
                                <td style="font-size:0.8rem;color:#8A99AA;">
                                    <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                                </td>
                                <td style="text-align:right;font-weight:600;color:#C8922A;">
                                    <?= number_format($c['total'], 0, ',', ' ') ?> F
                                </td>
                                <td>
                                    <span style="background:rgba(200,146,42,0.1);padding:3px 12px;border-radius:20px;font-size:0.65rem;font-weight:600;color:#C8922A;">
                                        <?= $paiements[$c['mode_paiement']] ?? $c['mode_paiement'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-statut statut-<?= $c['statut'] ?>">
                                        <?= $statut_labels[$c['statut']] ?? $c['statut'] ?>
                                    </span>
                                </td>
                                <td style="text-align:center;white-space:nowrap;">
                                    <a href="commande_detail.php?id=<?= $c['id'] ?>" class="btn-small blue" title="Voir détails">
                                        <i class="bi bi-eye"></i> Détail
                                    </a>
                                    <a href="generer_facture.php?id=<?= $c['id'] ?>" class="btn-small red" title="Générer la facture">
                                        <i class="bi bi-file-pdf"></i> PDF
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>