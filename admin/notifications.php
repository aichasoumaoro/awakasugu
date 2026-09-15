<?php
// ============================================
// NOTIFICATIONS - ADMIN AWA KA SUGU
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

$page_title = 'Notifications';

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
// CREER LA TABLE SI ELLE N'EXISTE PAS
// ============================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            reference_id INT DEFAULT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            est_lue TINYINT(1) DEFAULT 0,
            INDEX idx_type (type),
            INDEX idx_lue (est_lue)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch(PDOException $e) {}

// ============================================
// TRAITEMENT DES ACTIONS (AVANT LE HEADER)
// ============================================

// MARQUER COMME LUE
if (isset($_GET['lue']) && is_numeric($_GET['lue'])) {
    $id = (int)$_GET['lue'];
    $pdo->prepare("UPDATE notifications SET est_lue = 1 WHERE id = ?")->execute([$id]);
    header('Location: notifications.php');
    exit;
}

// MARQUER TOUT COMME LU
if (isset($_GET['tout_lu'])) {
    $pdo->query("UPDATE notifications SET est_lue = 1");
    header('Location: notifications.php');
    exit;
}

// SUPPRIMER UNE NOTIFICATION
if (isset($_GET['supprimer']) && is_numeric($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$id]);
    header('Location: notifications.php');
    exit;
}

// AJOUTER UNE NOTIFICATION DE TEST
if (isset($_POST['ajouter_notif'])) {
    $type = $_POST['type_notif'] ?? 'systeme';
    $message = trim($_POST['message_notif'] ?? 'Notification de test');
    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO notifications (type, message, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$type, $message]);
        $_SESSION['message_notif'] = 'Notification ajoutée avec succès !';
    }
    header('Location: notifications.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES NOTIFICATIONS
// ============================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';

if ($type_filter) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE type = ? ORDER BY created_at DESC LIMIT " . (int)$limit);
    $stmt->execute([$type_filter]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT " . (int)$limit);
    $stmt->execute();
}
$notifications = $stmt->fetchAll();

// ============================================
// STATISTIQUES
// ============================================
$total_non_lues = $pdo->query("SELECT COUNT(*) FROM notifications WHERE est_lue = 0")->fetchColumn();
$total_notifications = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();

// Types de notifications disponibles
$types = $pdo->query("SELECT DISTINCT type FROM notifications")->fetchAll(PDO::FETCH_COLUMN);

// Récupérer le message de session
$message_notif = $_SESSION['message_notif'] ?? '';
unset($_SESSION['message_notif']);

// ============================================
// INCLUSION DU HEADER ET DE LA SIDEBAR (APRÈS TOUTES LES REDIRECTIONS)
// ============================================
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">

    <!-- ===== TOPBAR ===== -->
    <div class="topbar">
        <div>
            <div class="topbar-title">🔔 <span>Notifications</span></div>
            <div class="topbar-breadcrumb">Administration → Notifications</div>
        </div>
        <div class="topbar-right" style="gap:8px;flex-wrap:wrap;">
            <?php if($total_non_lues > 0): ?>
                <a href="?tout_lu=1" class="btn-admin btn-success" style="padding:6px 16px;background:#27AE60;color:#fff;border:none;border-radius:8px;text-decoration:none;font-size:0.8rem;">
                    <i class="bi bi-check-all"></i> Tout marquer comme lu
                </a>
            <?php endif; ?>
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <?php if($message_notif): ?>
            <div class="alert-success" style="background:#D4EDDA;color:#155724;padding:12px 18px;border-radius:10px;margin-bottom:20px;border-left:4px solid #28A745;">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message_notif) ?>
            </div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row" style="grid-template-columns: repeat(3, 1fr);">
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-bell-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $total_non_lues ?></div>
                    <div class="stat-lbl">Non lues</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-bell"></i></div>
                <div>
                    <div class="stat-val"><?= $total_notifications ?></div>
                    <div class="stat-lbl">Total</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-filter"></i></div>
                <div>
                    <div class="stat-val" style="font-size:0.9rem;">
                        <?php if($type_filter): ?>
                            <span style="color:#C8922A;"><?= ucfirst($type_filter) ?></span>
                            <a href="notifications.php" style="font-size:0.6rem;color:#999;text-decoration:none;">(Effacer)</a>
                        <?php else: ?>
                            Tous les types
                        <?php endif; ?>
                    </div>
                    <div class="stat-lbl">Filtre actif</div>
                </div>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <?php if(!empty($types)): ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;">
            <a href="notifications.php" class="btn-small <?= !$type_filter ? 'green' : 'gray' ?>" style="padding:4px 14px;border-radius:20px;text-decoration:none;font-size:0.75rem;<?= !$type_filter ? 'background:#C8922A;color:#fff;' : 'background:#f0f0f0;color:#666;' ?>">
                <i class="bi bi-grid"></i> Tous
            </a>
            <?php foreach($types as $t): ?>
                <a href="?type=<?= $t ?>" class="btn-small <?= $type_filter == $t ? 'green' : 'gray' ?>" style="padding:4px 14px;border-radius:20px;text-decoration:none;font-size:0.75rem;<?= $type_filter == $t ? 'background:#C8922A;color:#fff;' : 'background:#f0f0f0;color:#666;' ?>">
                    <?= ucfirst($t) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== LISTE DES NOTIFICATIONS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Historique des notifications</div>
                <div class="text-muted" style="font-size:0.8rem;"><?= count($notifications) ?> notification(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if(empty($notifications)): ?>
                    <div class="empty-state" style="text-align:center;padding:40px;">
                        <i class="bi bi-bell-slash" style="font-size:3rem;color:#ccc;display:block;margin-bottom:10px;"></i>
                        <p style="color:#999;">Aucune notification pour le moment</p>
                    </div>
                <?php else: ?>
                    <div style="max-height:500px;overflow-y:auto;">
                        <?php foreach($notifications as $n): 
                            $icon = [
                                'commande' => 'bi-cart-check',
                                'stock' => 'bi-box-seam',
                                'client' => 'bi-person-plus',
                                'paiement' => 'bi-credit-card',
                                'systeme' => 'bi-gear'
                            ][$n['type']] ?? 'bi-bell';
                            
                            $color = [
                                'commande' => '#C8922A',
                                'stock' => '#E67E22',
                                'client' => '#2980B9',
                                'paiement' => '#27AE60',
                                'systeme' => '#8E44AD'
                            ][$n['type']] ?? '#666';
                        ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #F0F0F0;<?= $n['est_lue'] ? '' : 'background:rgba(200,146,42,0.04);border-left:3px solid #C8922A;' ?>">
                            <div style="display:flex;align-items:center;gap:12px;flex:1;">
                                <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:<?= $color ?>20;color:<?= $color ?>;flex-shrink:0;">
                                    <i class="bi <?= $icon ?>"></i>
                                </div>
                                <div>
                                    <div style="font-size:0.85rem;<?= $n['est_lue'] ? 'color:#666;' : 'font-weight:600;color:#1A1A1A;' ?>">
                                        <?= htmlspecialchars($n['message']) ?>
                                    </div>
                                    <div style="font-size:0.65rem;color:#999;margin-top:2px;">
                                        <i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($n['created_at'])) ?>
                                        <span style="margin:0 6px;">•</span>
                                        <span style="color:<?= $color ?>;"><?= ucfirst($n['type']) ?></span>
                                        <?php if(!$n['est_lue']): ?>
                                            <span style="margin:0 6px;">•</span>
                                            <span style="color:#C8922A;font-weight:600;font-size:0.6rem;">
                                                <i class="bi bi-circle-fill" style="font-size:0.35rem;"></i> Non lue
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex;gap:6px;flex-shrink:0;">
                                <?php if(!$n['est_lue']): ?>
                                    <a href="?lue=<?= $n['id'] ?>" class="btn-small green" title="Marquer comme lu" style="padding:4px 8px;background:#27AE60;color:#fff;border-radius:4px;text-decoration:none;font-size:0.7rem;">
                                        <i class="bi bi-check"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="?supprimer=<?= $n['id'] ?>" class="btn-small red" onclick="return confirm('Supprimer cette notification ?')" title="Supprimer" style="padding:4px 8px;background:#E74C3C;color:#fff;border-radius:4px;text-decoration:none;font-size:0.7rem;">
                                    <i class="bi bi-trash3"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== AJOUTER UNE NOTIFICATION TEST ===== -->
        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
        <div class="card-white" style="margin-top:20px;">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-plus-circle"></i> Ajouter une notification de test</div>
            </div>
            <div class="card-body">
                <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <select name="type_notif" style="padding:8px 14px;border:1.5px solid #E0E0E0;border-radius:8px;">
                        <option value="systeme">Système</option>
                        <option value="commande">Commande</option>
                        <option value="stock">Stock</option>
                        <option value="client">Client</option>
                    </select>
                    <input type="text" name="message_notif" placeholder="Message de test..." style="flex:1;min-width:200px;padding:8px 14px;border:1.5px solid #E0E0E0;border-radius:8px;">
                    <button type="submit" name="ajouter_notif" class="btn-admin btn-primary" style="padding:8px 20px;">
                        <i class="bi bi-plus"></i> Ajouter
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /main -->

<?php include 'includes/footer.php'; ?>