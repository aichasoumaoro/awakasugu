<?php
// ============================================
// BANNIERES - ADMIN AWA KA SUGU
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

// Vérification des permissions (Bannières visible pour super_admin et directeur uniquement)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Gestion des Bannières';

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
// SUPPRIMER UNE BANNIÈRE
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $stmt = $pdo->prepare("SELECT image FROM bannieres WHERE id = ?");
    $stmt->execute([$id]);
    $banniere = $stmt->fetch();
    if ($banniere && !empty($banniere['image'])) {
        $fichier = '../uploads/bannieres/' . $banniere['image'];
        if (file_exists($fichier)) {
            unlink($fichier);
        }
    }
    $pdo->prepare("DELETE FROM bannieres WHERE id = ?")->execute([$id]);
    $_SESSION['message_banniere'] = 'Bannière supprimée avec succès !';
    header('Location: bannieres.php');
    exit;
}

// ============================================
// CHANGER STATUT (activer/désactiver)
// ============================================
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE bannieres SET est_active = NOT est_active WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['message_banniere'] = 'Statut de la bannière modifié !';
    header('Location: bannieres.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================
$bannieres = $pdo->query("SELECT * FROM bannieres ORDER BY ordre ASC, id ASC")->fetchAll();

$message = $_SESSION['message_banniere'] ?? '';
unset($_SESSION['message_banniere']);

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
            <div class="topbar-title">🖼️ Gestion des <span>Bannières</span></div>
            <div class="topbar-breadcrumb">Administration → Bannières</div>
        </div>
        <div class="topbar-right">
            <a href="banniere_ajouter.php" class="btn-admin btn-primary">
                <i class="bi bi-plus-circle"></i> Nouvelle bannière
            </a>
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <?php if($message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <?php 
        $total_bannieres = count($bannieres);
        $bannieres_actives = 0;
        foreach($bannieres as $b) {
            if($b['est_active']) $bannieres_actives++;
        }
        ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-images"></i></div>
                <div>
                    <div class="stat-val"><?= $total_bannieres ?></div>
                    <div class="stat-lbl">Total bannières</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-eye"></i></div>
                <div>
                    <div class="stat-val"><?= $bannieres_actives ?></div>
                    <div class="stat-lbl">Bannières actives</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-eye-slash"></i></div>
                <div>
                    <div class="stat-val"><?= $total_bannieres - $bannieres_actives ?></div>
                    <div class="stat-lbl">Bannières inactives</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-arrow-up"></i></div>
                <div>
                    <div class="stat-val"><?= $total_bannieres > 0 ? 'Active' : '—' ?></div>
                    <div class="stat-lbl">Statut carrousel</div>
                </div>
            </div>
        </div>

        <!-- ===== LISTE DES BANNIÈRES ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Liste des bannières</div>
                <div class="text-muted" style="font-size:0.8rem;"><?= count($bannieres) ?> bannière(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-bannieres">
                        <thead>
                            <tr>
                                <th style="width:60px;">Ordre</th>
                                <th style="width:120px;">Image</th>
                                <th>Titre</th>
                                <th>Lien</th>
                                <th style="text-align:center;">Statut</th>
                                <th style="text-align:center;width:180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($bannieres)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="bi bi-images"></i>
                                            <p>Aucune bannière pour le moment</p>
                                            <a href="banniere_ajouter.php" class="btn-admin btn-primary" style="display:inline-flex;padding:8px 20px;font-size:0.85rem;margin-top:10px;">
                                                <i class="bi bi-plus-circle"></i> Ajouter une bannière
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($bannieres as $b): ?>
                                <tr>
                                    <td>
                                        <span style="background:#F0F2F5;padding:2px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;color:#0D0D0D;">#<?= $b['ordre'] ?></span>
                                    </td>
                                    <td>
                                        <?php if(!empty($b['image']) && file_exists('../uploads/bannieres/'.$b['image'])): ?>
                                            <img src="../uploads/bannieres/<?= $b['image'] ?>" style="width:100px;height:60px;object-fit:cover;border-radius:8px;border:1px solid #E8ECF0;" alt="<?= htmlspecialchars($b['titre'] ?? 'Bannière') ?>">
                                        <?php else: ?>
                                            <div style="width:100px;height:60px;background:#F0F2F5;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#8A99AA;font-size:0.7rem;border:1px dashed #D0D5DC;">
                                                <i class="bi bi-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($b['titre'] ?? 'Sans titre') ?></strong>
                                        <?php if(!empty($b['description'])): ?>
                                            <br><span style="font-size:0.7rem;color:#8A99AA;"><?= htmlspecialchars(substr($b['description'], 0, 50)) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if(!empty($b['lien'])): ?>
                                            <a href="<?= $b['lien'] ?>" target="_blank" class="btn-small blue" style="font-size:0.65rem;padding:2px 10px;">
                                                <i class="bi bi-link-45deg"></i> Voir
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#8A99AA;font-size:0.75rem;">Aucun lien</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if($b['est_active']): ?>
                                            <span style="background:#D4EDDA;color:#1A7A4A;padding:3px 12px;border-radius:20px;font-size:0.65rem;font-weight:600;">
                                                <i class="bi bi-check-circle"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span style="background:#F8D7DA;color:#721C24;padding:3px 12px;border-radius:20px;font-size:0.65rem;font-weight:600;">
                                                <i class="bi bi-x-circle"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <a href="banniere_modifier.php?id=<?= $b['id'] ?>" class="btn-small blue" title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="bannieres.php?toggle=<?= $b['id'] ?>" class="btn-small <?= $b['est_active'] ? 'gray' : 'green' ?>" title="<?= $b['est_active'] ? 'Désactiver' : 'Activer' ?>">
                                                <i class="bi <?= $b['est_active'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                            </a>
                                            <a href="bannieres.php?supprimer=<?= $b['id'] ?>" class="btn-small red" onclick="return confirm('Supprimer cette bannière définitivement ?')" title="Supprimer">
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

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>