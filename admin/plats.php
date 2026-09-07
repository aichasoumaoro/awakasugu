<?php
// ============================================
// PLATS - ADMIN AWA KA SUGU
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

// Vérification des permissions (Plats visible pour super_admin, directeur et admin)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur' && $admin_role !== 'admin') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Gestion des Plats';

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
    die("Erreur : " . $e->getMessage());
}

// ============================================
// AJOUTER UN PLAT
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_plat'])) {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $prix = (float)$_POST['prix'];
    $est_plat_du_jour = isset($_POST['est_plat_du_jour']) ? (int)$_POST['est_plat_du_jour'] : 0;
    $est_visible = isset($_POST['est_visible']) ? (int)$_POST['est_visible'] : 1;
    $categorie = trim($_POST['categorie'] ?? '');
    $photo = '';
    
    // Gestion de l'upload de photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = dirname(__DIR__) . '/restaurant/images/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_info = pathinfo($_FILES['photo']['name']);
        $nom_fichier = $file_info['basename'];
        $extension = strtolower($file_info['extension']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($extension, $allowed_extensions)) {
            $nom_fichier = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', str_replace(' ', '_', $nom_fichier));
            $upload_path = $upload_dir . $nom_fichier;
            
            $counter = 1;
            $original_name = pathinfo($nom_fichier, PATHINFO_FILENAME);
            $ext = pathinfo($nom_fichier, PATHINFO_EXTENSION);
            while (file_exists($upload_path)) {
                $nom_fichier = $original_name . '_' . $counter . '.' . $ext;
                $upload_path = $upload_dir . $nom_fichier;
                $counter++;
            }
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $photo = $nom_fichier;
            }
        }
    }
    
    if (!empty($nom) && $prix > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO plats (nom, description, prix, est_plat_du_jour, est_visible, categorie, photo, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$nom, $description, $prix, $est_plat_du_jour, $est_visible, $categorie, $photo]);
        
        $_SESSION['message_plat'] = 'Plat ajouté avec succès !';
        header('Location: plats.php');
        exit;
    }
}

// ============================================
// MODIFIER UN PLAT
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_plat'])) {
    $id = (int)$_POST['id'];
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $prix = (float)$_POST['prix'];
    $est_plat_du_jour = isset($_POST['est_plat_du_jour']) ? (int)$_POST['est_plat_du_jour'] : 0;
    $est_visible = isset($_POST['est_visible']) ? (int)$_POST['est_visible'] : 1;
    $categorie = trim($_POST['categorie'] ?? '');
    
    $stmt = $pdo->prepare("SELECT photo FROM plats WHERE id = ?");
    $stmt->execute([$id]);
    $current_plat = $stmt->fetch();
    $photo = $current_plat['photo'] ?? '';
    
    $supprimer_photo = isset($_POST['supprimer_photo']) ? true : false;
    
    if ($supprimer_photo && !empty($photo)) {
        $old_photo_path = dirname(__DIR__) . '/restaurant/images/' . $photo;
        if (file_exists($old_photo_path)) {
            unlink($old_photo_path);
        }
        $photo = '';
    }
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = dirname(__DIR__) . '/restaurant/images/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_info = pathinfo($_FILES['photo']['name']);
        $nom_fichier = $file_info['basename'];
        $extension = strtolower($file_info['extension']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($extension, $allowed_extensions)) {
            if (!empty($current_plat['photo'])) {
                $old_photo_path = dirname(__DIR__) . '/restaurant/images/' . $current_plat['photo'];
                if (file_exists($old_photo_path)) {
                    unlink($old_photo_path);
                }
            }
            
            $nom_fichier = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', str_replace(' ', '_', $nom_fichier));
            $upload_path = $upload_dir . $nom_fichier;
            
            $counter = 1;
            $original_name = pathinfo($nom_fichier, PATHINFO_FILENAME);
            $ext = pathinfo($nom_fichier, PATHINFO_EXTENSION);
            while (file_exists($upload_path)) {
                $nom_fichier = $original_name . '_' . $counter . '.' . $ext;
                $upload_path = $upload_dir . $nom_fichier;
                $counter++;
            }
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $photo = $nom_fichier;
            }
        }
    }
    
    if (!empty($nom) && $prix > 0) {
        $stmt = $pdo->prepare("
            UPDATE plats SET 
                nom = ?, description = ?, prix = ?, 
                est_plat_du_jour = ?, est_visible = ?, 
                categorie = ?, photo = ?
            WHERE id = ?
        ");
        $stmt->execute([$nom, $description, $prix, $est_plat_du_jour, $est_visible, $categorie, $photo, $id]);
        
        $_SESSION['message_plat'] = 'Plat modifié avec succès !';
        header('Location: plats.php');
        exit;
    }
}

// ============================================
// SUPPRIMER UN PLAT
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    
    $stmt = $pdo->prepare("SELECT photo FROM plats WHERE id = ?");
    $stmt->execute([$id]);
    $plat = $stmt->fetch();
    
    if (!empty($plat['photo'])) {
        $photo_path = dirname(__DIR__) . '/restaurant/images/' . $plat['photo'];
        if (file_exists($photo_path)) {
            unlink($photo_path);
        }
    }
    
    $pdo->prepare("DELETE FROM plats WHERE id = ?")->execute([$id]);
    $_SESSION['message_plat'] = 'Plat supprimé avec succès !';
    header('Location: plats.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================
$plats = $pdo->query("SELECT * FROM plats ORDER BY created_at DESC")->fetchAll();
$plat_du_jour = $pdo->query("SELECT * FROM plats WHERE est_plat_du_jour = 1 LIMIT 1")->fetch();

$message = $_SESSION['message_plat'] ?? '';
unset($_SESSION['message_plat']);

// Statistiques
$total_plats = $pdo->query("SELECT COUNT(*) FROM plats")->fetchColumn();
$total_visibles = $pdo->query("SELECT COUNT(*) FROM plats WHERE est_visible = 1")->fetchColumn();

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
            <div class="topbar-title">🍽️ Gestion des <span>Plats</span></div>
            <div class="topbar-breadcrumb">Restaurant → Plats</div>
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
                <div class="stat-icon ic-or"><i class="bi bi-cup-hot"></i></div>
                <div>
                    <div class="stat-val"><?= $total_plats ?></div>
                    <div class="stat-lbl">Total plats</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-eye"></i></div>
                <div>
                    <div class="stat-val"><?= $total_visibles ?></div>
                    <div class="stat-lbl">Plats visibles</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-gold"><i class="bi bi-star-fill"></i></div>
                <div>
                    <div class="stat-val" style="font-size:1rem;"><?= $plat_du_jour ? htmlspecialchars($plat_du_jour['nom']) : 'Aucun' ?></div>
                    <div class="stat-lbl">Plat du jour</div>
                </div>
            </div>
        </div>

        <!-- ===== AJOUTER UN PLAT ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-plus-circle"></i> Ajouter un plat</div>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div style="display:grid;grid-template-columns:2fr 1fr 0.8fr 0.8fr 0.8fr;gap:15px;align-items:end;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Nom du plat</label>
                            <input type="text" name="nom" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Jost',sans-serif;" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Prix (FCFA)</label>
                            <input type="number" name="prix" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Jost',sans-serif;" min="1" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Catégorie</label>
                            <select name="categorie" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Jost',sans-serif;">
                                <option value="">Sélectionner</option>
                                <option value="Entrée">Entrée</option>
                                <option value="Plat principal">Plat principal</option>
                                <option value="Dessert">Dessert</option>
                                <option value="Boisson">Boisson</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Plat du jour</label>
                            <select name="est_plat_du_jour" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Jost',sans-serif;">
                                <option value="0">Non</option>
                                <option value="1">Oui</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <button type="submit" name="ajouter_plat" class="btn-admin btn-primary" style="width:100%;padding:10px;">
                                <i class="bi bi-save"></i> Ajouter
                            </button>
                        </div>
                    </div>
                    <div style="margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Description</label>
                            <input type="text" name="description" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Jost',sans-serif;" placeholder="Description du plat...">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Visibilité</label>
                            <select name="est_visible" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Jost',sans-serif;">
                                <option value="1">✅ Visible</option>
                                <option value="0">❌ Caché</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top:10px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Photo du plat</label>
                            <input type="file" name="photo" class="form-control-file" style="padding:8px 0;border:1.5px solid #E0E0E0;border-radius:8px;padding:8px 14px;width:100%;" accept="image/*" onchange="previewImage(this)">
                            <div id="photoPreview" style="margin-top:10px;border:2px dashed #E0E0E0;border-radius:8px;padding:15px;text-align:center;color:#999;min-height:80px;display:flex;align-items:center;justify-content:center;">
                                <span style="font-size:0.85rem;color:#aaa;"><i class="bi bi-image"></i> Aucune photo sélectionnée</span>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top:10px;font-size:0.8rem;color:#999;">
                        <i class="bi bi-info-circle"></i> La photo sera enregistrée dans le dossier <strong>restaurant/images/</strong>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===== LISTE DES PLATS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Liste des plats</div>
                <div class="text-muted" style="font-size:0.8rem;"><?= count($plats) ?> plat(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-plats">
                        <thead>
                            <tr>
                                <th style="width:60px;">Photo</th>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Catégorie</th>
                                <th style="text-align:right;">Prix</th>
                                <th style="text-align:center;">Statut</th>
                                <th style="text-align:center;width:100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($plats)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="bi bi-cup-hot"></i>
                                            <p>Aucun plat enregistré</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($plats as $p): ?>
                                <tr>
                                    <td>
                                        <?php if(!empty($p['photo'])): ?>
                                            <img src="../restaurant/images/<?= htmlspecialchars($p['photo']) ?>" alt="Photo" style="width:50px;height:50px;object-fit:cover;border-radius:8px;border:1px solid #eee;">
                                        <?php else: ?>
                                            <div style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;background:#f5f5f5;border-radius:8px;color:#ccc;font-size:1.2rem;">
                                                <i class="bi bi-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['nom']) ?></strong>
                                        <?php if($p['est_plat_du_jour']): ?>
                                            <span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;background:#FFF3CD;color:#856404;margin-left:6px;">⭐ Plat du jour</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.8rem;color:#666;"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 40)) ?>...</td>
                                    <td><?= htmlspecialchars($p['categorie'] ?? '-') ?></td>
                                    <td style="text-align:right;color:#C8922A;font-weight:600;"><?= number_format($p['prix'], 0, ',', ' ') ?> F</td>
                                    <td style="text-align:center;">
                                        <span class="badge-statut <?= ($p['est_visible'] ?? 1) ? 'badge-visible' : 'badge-cache' ?>">
                                            <?= ($p['est_visible'] ?? 1) ? 'Visible' : 'Caché' ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;white-space:nowrap;">
                                        <a href="plat_modifier.php?id=<?= $p['id'] ?>" class="btn-small blue" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="plats.php?supprimer=<?= $p['id'] ?>" class="btn-small red" onclick="return confirm('Supprimer ce plat ?')" title="Supprimer">
                                            <i class="bi bi-trash3"></i>
                                        </a>
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

<script>
function previewImage(input) {
    const preview = document.getElementById('photoPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Aperçu" style="max-width:150px;max-height:150px;border-radius:8px;">`;
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.innerHTML = `<span style="font-size:0.85rem;color:#aaa;"><i class="bi bi-image"></i> Aucune photo sélectionnée</span>`;
    }
}
</script>