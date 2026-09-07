<?php
// ============================================
// GESTION DES AVIS CLIENTS - ADMIN
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

// Vérification des permissions (Avis visible pour super_admin et directeur uniquement)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Gestion des Avis';

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
// PUBLIER / CACHER UN AVIS
// ============================================
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT est_visible FROM avis_clients WHERE id = ?");
    $stmt->execute([$id]);
    $avis = $stmt->fetch();
    if ($avis) {
        $new_status = $avis['est_visible'] ? 0 : 1;
        $pdo->prepare("UPDATE avis_clients SET est_visible = ? WHERE id = ?")->execute([$new_status, $id]);
        $_SESSION['message_avis'] = 'Avis mis à jour avec succès !';
    }
    header('Location: avis_admin.php');
    exit;
}

// ============================================
// SUPPRIMER UN AVIS
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM avis_clients WHERE id = ?")->execute([$id]);
    $_SESSION['message_avis'] = 'Avis supprimé avec succès !';
    header('Location: avis_admin.php');
    exit;
}

// ============================================
// VALIDER UN AVIS (passer est_valide = 1 et est_visible = 1)
// ============================================
if (isset($_GET['valider'])) {
    $id = (int)$_GET['valider'];
    $pdo->prepare("UPDATE avis_clients SET est_valide = 1, est_visible = 1 WHERE id = ?")->execute([$id]);
    $_SESSION['message_avis'] = 'Avis validé et publié avec succès !';
    header('Location: avis_admin.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================
$avis = $pdo->query("SELECT * FROM avis_clients ORDER BY created_at DESC")->fetchAll();

// Statistiques
$total_avis = $pdo->query("SELECT COUNT(*) FROM avis_clients")->fetchColumn();
$avis_visibles = $pdo->query("SELECT COUNT(*) FROM avis_clients WHERE est_visible = 1")->fetchColumn();
$avis_non_visibles = $pdo->query("SELECT COUNT(*) FROM avis_clients WHERE est_visible = 0")->fetchColumn();
$note_moyenne = $pdo->query("SELECT COALESCE(AVG(note), 0) FROM avis_clients WHERE est_visible = 1")->fetchColumn();

$message = $_SESSION['message_avis'] ?? '';
unset($_SESSION['message_avis']);

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
            <div class="topbar-title">⭐ Gestion des <span>Avis</span></div>
            <div class="topbar-breadcrumb">Marketing → Avis clients</div>
        </div>
        <div class="topbar-right">
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
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-gold"><i class="bi bi-star"></i></div>
                <div>
                    <div class="stat-val"><?= $total_avis ?></div>
                    <div class="stat-lbl">Total avis</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-eye"></i></div>
                <div>
                    <div class="stat-val"><?= $avis_visibles ?></div>
                    <div class="stat-lbl">Avis publiés</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-orange"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-val"><?= $avis_non_visibles ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-graph-up"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($note_moyenne, 1) ?></div>
                    <div class="stat-lbl">Note moyenne</div>
                </div>
            </div>
        </div>

        <!-- ===== LISTE DES AVIS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Liste des avis</div>
                <div style="font-size:0.7rem;color:#8A99AA;background:#F8F9FA;padding:4px 16px;border-radius:20px;border:1px solid #E8ECF0;">
                    <strong style="color:#C8922A;"><?= count($avis) ?></strong> avis
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-avis" style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;width:50px;">#</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Client</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Note</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Commentaire</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Recommandation</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Date</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Statut</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;width:130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($avis)): ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state" style="text-align:center;padding:40px;color:#8A99AA;">
                                            <i class="bi bi-star" style="font-size:2.5rem;display:block;margin-bottom:10px;color:#D5D5D5;"></i>
                                            <p style="margin:0;font-size:0.85rem;">Aucun avis</p>
                                            <span style="font-size:0.75rem;color:#bbb;display:block;margin-top:4px;">Les avis des clients apparaîtront ici</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($avis as $a): 
                                    $initiale = strtoupper(mb_substr($a['nom_client'] ?? 'A', 0, 1));
                                ?>
                                <tr style="transition:background 0.2s;">
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;color:#8A99AA;font-size:0.75rem;">#<?= $a['id'] ?></td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;">
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#C8922A,#E8B55A);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:#fff;flex-shrink:0;">
                                                <?= $initiale ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600;color:#1A2C3E;font-size:0.85rem;">
                                                    <?= htmlspecialchars($a['nom_client'] ?? 'Anonyme') ?>
                                                </div>
                                                <div style="font-size:0.6rem;color:#8A99AA;margin-top:1px;">
                                                    <i class="bi bi-person" style="font-size:0.55rem;"></i> Client #<?= $a['client_id'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <div style="display:flex;gap:2px;justify-content:center;">
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <i class="bi bi-star<?= $i <= $a['note'] ? '-fill' : '' ?>" style="color:<?= $i <= $a['note'] ? '#F1C40F' : '#E0E0E0' ?>;font-size:0.85rem;"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;font-size:0.8rem;color:#5A6B7A;max-width:180px;">
                                        <?php if(!empty($a['commentaire'])): ?>
                                            <?= htmlspecialchars(substr($a['commentaire'], 0, 50)) ?>
                                            <?= strlen($a['commentaire']) > 50 ? '...' : '' ?>
                                        <?php else: ?>
                                            <span style="color:#bbb;font-size:0.7rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <?php if(isset($a['recommandation']) && $a['recommandation'] == 1): ?>
                                            <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.65rem;font-weight:600;background:rgba(40,167,69,0.1);color:#28A745;">
                                                <i class="bi bi-hand-thumbs-up"></i> Oui
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#bbb;font-size:0.7rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;font-size:0.75rem;color:#8A99AA;">
                                        <i class="bi bi-calendar3" style="font-size:0.6rem;"></i>
                                        <?= date('d/m/Y', strtotime($a['created_at'])) ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <?php if($a['est_visible']): ?>
                                            <span class="badge-status active" style="padding:3px 12px;border-radius:20px;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;display:inline-block;background:#E8F5E9;color:#2E7D32;">
                                                <i class="bi bi-check-circle"></i> Publié
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-status inactive" style="padding:3px 12px;border-radius:20px;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;display:inline-block;background:#FFF3E0;color:#E67E22;">
                                                <i class="bi bi-clock"></i> En attente
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <?php if(!$a['est_visible']): ?>
                                                <a href="avis_admin.php?valider=<?= $a['id'] ?>" class="btn-small green" title="Valider et publier" onclick="return confirm('Valider et publier cet avis ?')" style="padding:4px 10px;border-radius:6px;font-size:0.65rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:rgba(40,167,69,0.1);color:#28A745;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='#28A745';this.style.color='#fff'" onmouseout="this.style.background='rgba(40,167,69,0.1)';this.style.color='#28A745'">
                                                    <i class="bi bi-check-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="avis_admin.php?toggle=<?= $a['id'] ?>" class="btn-small blue" title="<?= $a['est_visible'] ? 'Cacher' : 'Publier' ?>" style="padding:4px 10px;border-radius:6px;font-size:0.65rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:rgba(41,128,185,0.1);color:#2980B9;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='#2980B9';this.style.color='#fff'" onmouseout="this.style.background='rgba(41,128,185,0.1)';this.style.color='#2980B9'">
                                                <i class="bi bi-<?= $a['est_visible'] ? 'eye-slash' : 'eye' ?>"></i>
                                            </a>
                                            <a href="avis_admin.php?supprimer=<?= $a['id'] ?>" class="btn-small red" onclick="return confirm('Supprimer cet avis ?')" title="Supprimer" style="padding:4px 10px;border-radius:6px;font-size:0.65rem;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:rgba(231,76,60,0.1);color:#E74C3C;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='#E74C3C';this.style.color='#fff'" onmouseout="this.style.background='rgba(231,76,60,0.1)';this.style.color='#E74C3C'">
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