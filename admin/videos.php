<?php
// ============================================
// VIDÉOS - ADMIN AWA KA SUGU
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

$page_title = 'Gestion des Vidéos';

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
// SUPPRESSION D'UNE VIDÉO
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    // Récupérer le fichier vidéo pour le supprimer
    $stmt = $pdo->prepare("SELECT fichier_video FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $video = $stmt->fetch();
    if ($video && !empty($video['fichier_video'])) {
        $f = '../uploads/videos/' . $video['fichier_video'];
        if (file_exists($f)) unlink($f);
    }
    $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$id]);
    $_SESSION['message_video'] = 'Vidéo supprimée avec succès !';
    header('Location: videos.php');
    exit;
}

// ============================================
// ACTIVATION/DÉSACTIVATION
// ============================================
if (isset($_GET['activer'])) {
    $id = (int)$_GET['activer'];
    $pdo->prepare("UPDATE videos SET est_active = 1 WHERE id = ?")->execute([$id]);
    $_SESSION['message_video'] = 'Vidéo activée avec succès !';
    header('Location: videos.php');
    exit;
}

if (isset($_GET['desactiver'])) {
    $id = (int)$_GET['desactiver'];
    $pdo->prepare("UPDATE videos SET est_active = 0 WHERE id = ?")->execute([$id]);
    $_SESSION['message_video'] = 'Vidéo désactivée avec succès !';
    header('Location: videos.php');
    exit;
}

// ============================================
// RÉCUPÉRER TOUTES LES VIDÉOS
// ============================================
$videos = $pdo->query("SELECT * FROM videos ORDER BY est_active DESC, created_at DESC")->fetchAll();

$message = $_SESSION['message_video'] ?? '';
unset($_SESSION['message_video']);

// ============================================
// STATISTIQUES
// ============================================
$total_videos = count($videos);
$videos_actives = 0;
$videos_inactives = 0;
foreach($videos as $v) {
    if($v['est_active']) $videos_actives++;
    else $videos_inactives++;
}

// ============================================
// FONCTIONS UTILITAIRES
// ============================================
function getYoutubeId($url) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/', $url, $matches);
    return $matches[1] ?? '';
}

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
            <div class="topbar-title">📹 Gestion des <span>Vidéos</span></div>
            <div class="topbar-breadcrumb">Marketing → Vidéos</div>
        </div>
        <div class="topbar-right">
            <a href="video_ajouter.php" class="btn-admin btn-primary">
                <i class="bi bi-plus-lg"></i> Nouvelle vidéo
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
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-camera-reels"></i></div>
                <div>
                    <div class="stat-val"><?= $total_videos ?></div>
                    <div class="stat-lbl">Total vidéos</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $videos_actives ?></div>
                    <div class="stat-lbl">Actives</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-x-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $videos_inactives ?></div>
                    <div class="stat-lbl">Inactives</div>
                </div>
            </div>
        </div>

        <!-- ===== LISTE DES VIDÉOS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-camera-reels"></i> Liste des vidéos</div>
                <div style="font-size:0.7rem;color:#8A99AA;"><?= count($videos) ?> vidéo(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-produits">
                        <thead>
                            <tr>
                                <th style="width:140px;">Aperçu</th>
                                <th>Titre</th>
                                <th>Type</th>
                                <th style="text-align:center;">Statut</th>
                                <th style="text-align:center;">Date</th>
                                <th style="text-align:center;width:120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($videos)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="bi bi-camera-reels"></i>
                                            <p>Aucune vidéo trouvée</p>
                                            <a href="video_ajouter.php" style="color:#C8922A;text-decoration:none;">
                                                <i class="bi bi-plus-circle"></i> Ajouter votre première vidéo
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($videos as $v): ?>
                                <tr>
                                    <td>
                                        <div class="video-preview">
                                            <?php if($v['type'] == 'local' && $v['fichier_video']): ?>
                                                <video src="../uploads/videos/<?= htmlspecialchars($v['fichier_video']) ?>" muted></video>
                                            <?php elseif($v['type'] == 'youtube' && $v['url_ou_fichier']): ?>
                                                <iframe src="https://www.youtube.com/embed/<?= getYoutubeId($v['url_ou_fichier']) ?>" frameborder="0" allowfullscreen></iframe>
                                            <?php else: ?>
                                                <div style="display:flex;align-items:center;justify-content:center;height:100%;background:#1A1A1A;color:#C8922A;font-size:1.5rem;">
                                                    <i class="bi bi-camera-reels"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($v['titre']) ?></strong>
                                        <?php if(!empty($v['description'])): ?>
                                            <br><span style="font-size:0.7rem;color:#8A99AA;"><?= htmlspecialchars(substr($v['description'], 0, 40)) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($v['type'] == 'local'): ?>
                                            <span class="badge-local">📁 Fichier local</span>
                                        <?php else: ?>
                                            <span class="badge-status active" style="background:rgba(200,146,42,0.1);color:#C8922A;">🎬 <?= htmlspecialchars($v['type']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if($v['est_active']): ?>
                                            <span class="badge-status active">✓ Active</span>
                                        <?php else: ?>
                                            <span class="badge-status inactive">✗ Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;font-size:0.75rem;color:#8A99AA;">
                                        <?= date('d/m/Y', strtotime($v['created_at'])) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;">
                                            <?php if($v['est_active']): ?>
                                                <a href="videos.php?desactiver=<?= $v['id'] ?>" class="btn-small gray" title="Désactiver">
                                                    <i class="bi bi-eye-slash"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="videos.php?activer=<?= $v['id'] ?>" class="btn-small green" title="Activer">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="videos.php?supprimer=<?= $v['id'] ?>" class="btn-small red" title="Supprimer"
                                               onclick="return confirm('Supprimer cette vidéo définitivement ?')">
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