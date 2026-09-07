<?php
// ============================================
// PROMOTIONS - ADMIN AWA KA SUGU
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

$page_title = 'Gestion des Promotions';

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
// SYNCHRONISATION - Met à jour les produits en promo
// ============================================
function synchroniserProduitsPromo($pdo) {
    try {
        $pdo->exec("UPDATE produits SET est_promo = 0, prix_promo = NULL");
        
        $stmt = $pdo->query("
            SELECT * FROM promotions 
            WHERE est_active = 1 
            AND date_debut <= CURDATE() 
            AND date_fin >= CURDATE()
        ");
        $promotions = $stmt->fetchAll();
        
        if (empty($promotions)) {
            return true;
        }
        
        foreach($promotions as $promo) {
            if ($promo['produit_id'] && $promo['produit_id'] > 0) {
                if ($promo['type'] == 'pourcentage') {
                    $stmt = $pdo->prepare("
                        UPDATE produits 
                        SET est_promo = 1, 
                            prix_promo = ROUND(prix - (prix * ? / 100), 0)
                        WHERE id = ?
                    ");
                    $stmt->execute([$promo['valeur'], $promo['produit_id']]);
                } elseif ($promo['type'] == 'montant_fixe') {
                    $stmt = $pdo->prepare("
                        UPDATE produits 
                        SET est_promo = 1, 
                            prix_promo = ROUND(prix - ?, 0)
                        WHERE id = ?
                    ");
                    $stmt->execute([$promo['valeur'], $promo['produit_id']]);
                }
            }
        }
        
        return true;
    } catch(PDOException $e) {
        error_log("Erreur synchronisation: " . $e->getMessage());
        return false;
    }
}

// ============================================
// AJOUTER UNE PROMOTION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $nom = $_POST['nom'] ?? '';
    $description = $_POST['description'] ?? '';
    $produit_id = $_POST['produit_id'] ? (int)$_POST['produit_id'] : null;
    $type = $_POST['type'] ?? 'pourcentage';
    $valeur = (float)($_POST['valeur'] ?? 0);
    $date_debut = $_POST['date_debut'] ?? date('Y-m-d');
    $date_fin = $_POST['date_fin'] ?? date('Y-m-d', strtotime('+30 days'));
    
    if ($nom && $valeur > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO promotions (nom, description, produit_id, type, valeur, date_debut, date_fin, est_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$nom, $description, $produit_id, $type, $valeur, $date_debut, $date_fin]);
            synchroniserProduitsPromo($pdo);
            $_SESSION['message_promo'] = 'Promotion ajoutée avec succès !';
            header('Location: promotions.php');
            exit;
        } catch(PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez remplir tous les champs obligatoires.";
    }
}

// ============================================
// MODIFIER UNE PROMOTION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier') {
    $id = (int)($_POST['id'] ?? 0);
    $nom = $_POST['nom'] ?? '';
    $description = $_POST['description'] ?? '';
    $produit_id = $_POST['produit_id'] ? (int)$_POST['produit_id'] : null;
    $type = $_POST['type'] ?? 'pourcentage';
    $valeur = (float)($_POST['valeur'] ?? 0);
    $date_debut = $_POST['date_debut'] ?? date('Y-m-d');
    $date_fin = $_POST['date_fin'] ?? date('Y-m-d', strtotime('+30 days'));
    $est_active = isset($_POST['est_active']) ? 1 : 0;
    
    if ($id && $nom && $valeur > 0) {
        try {
            $stmt = $pdo->prepare("
                UPDATE promotions 
                SET nom = ?, description = ?, produit_id = ?, type = ?, valeur = ?, 
                    date_debut = ?, date_fin = ?, est_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$nom, $description, $produit_id, $type, $valeur, $date_debut, $date_fin, $est_active, $id]);
            synchroniserProduitsPromo($pdo);
            $_SESSION['message_promo'] = 'Promotion modifiée avec succès !';
            header('Location: promotions.php');
            exit;
        } catch(PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez remplir tous les champs obligatoires.";
    }
}

// ============================================
// SUPPRIMER UNE PROMOTION
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    try {
        $pdo->prepare("DELETE FROM promotions WHERE id = ?")->execute([$id]);
        synchroniserProduitsPromo($pdo);
        $_SESSION['message_promo'] = 'Promotion supprimée avec succès !';
        header('Location: promotions.php');
        exit;
    } catch(PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// ============================================
// TOGGLE ACTIF/INACTIF
// ============================================
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    try {
        $stmt = $pdo->prepare("UPDATE promotions SET est_active = NOT est_active WHERE id = ?");
        $stmt->execute([$id]);
        synchroniserProduitsPromo($pdo);
        $_SESSION['message_promo'] = 'Statut modifié avec succès !';
        header('Location: promotions.php');
        exit;
    } catch(PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// ============================================
// FORCER LA SYNCHRONISATION (utilisé pour le cron)
// ============================================
if (isset($_GET['cron_sync'])) {
    $sync_result = synchroniserProduitsPromo($pdo);
    echo $sync_result ? 'OK' : 'ERROR';
    exit;
}

// ============================================
// RÉCUPÉRATION DES DONNÉES
// ============================================
$promotions = $pdo->query("
    SELECT p.*, pr.nom as produit_nom 
    FROM promotions p 
    LEFT JOIN produits pr ON p.produit_id = pr.id 
    ORDER BY p.created_at DESC
")->fetchAll();

$produits = $pdo->query("SELECT DISTINCT id, nom FROM produits WHERE est_visible = 1 ORDER BY nom")->fetchAll();

$message = $_SESSION['message_promo'] ?? '';
unset($_SESSION['message_promo']);
$error = $error ?? '';

$total_promos = count($promotions);
$actives = 0;
$expirees = 0;
$inactives = 0;
$now = date('Y-m-d');
foreach($promotions as $p) {
    if ($p['est_active'] == 0) {
        $inactives++;
    } elseif ($p['date_fin'] < $now) {
        $expirees++;
    } else {
        $actives++;
    }
}

$stmt = $pdo->query("SELECT COUNT(*) FROM produits WHERE est_promo = 1");
$nb_produits_promo = $stmt->fetchColumn();

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
            <div class="topbar-title">🎁 Gestion des <span>Promotions</span></div>
            <div class="topbar-breadcrumb">Marketing → Promotions</div>
        </div>
        <div class="topbar-right">
            <button class="btn-admin btn-primary" onclick="openModal()">
                <i class="bi bi-plus-lg"></i> Nouvelle promotion
            </button>
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
        <?php if($error): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ===== SYNC INFO ===== -->
        <div class="sync-info">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <i class="bi bi-arrow-repeat" style="color:#2980B9;font-size:1.2rem;"></i>
                <span style="font-size:0.85rem;">
                    <strong>Synchro auto :</strong> Les promotions actives sont automatiquement appliquées aux produits.
                </span>
                <span style="background:#27AE60;color:white;padding:2px 12px;border-radius:20px;font-size:0.55rem;font-weight:700;letter-spacing:0.5px;">
                    ✅ ACTIF
                </span>
            </div>
            <div style="font-size:0.8rem;color:#27AE60;font-weight:600;">
                <i class="bi bi-box-seam"></i> <?= $nb_produits_promo ?> produit(s) en promotion
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-gold"><i class="bi bi-tags-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $total_promos ?></div>
                    <div class="stat-lbl">Total promotions</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $actives ?></div>
                    <div class="stat-lbl">Actives</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-orange"><i class="bi bi-clock-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $expirees ?></div>
                    <div class="stat-lbl">Expirées</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-eye-slash-fill"></i></div>
                <div>
                    <div class="stat-val"><?= $inactives ?></div>
                    <div class="stat-lbl">Inactives</div>
                </div>
            </div>
        </div>

        <!-- ===== LISTE DES PROMOTIONS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Liste des promotions</div>
                <div style="font-size:0.7rem;color:#8A99AA;"><?= $total_promos ?> promotion(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-produits">
                        <thead>
                            <tr>
                                <th style="width:50px;">ID</th>
                                <th>Nom</th>
                                <th>Produit</th>
                                <th style="text-align:center;">Réduction</th>
                                <th style="text-align:center;">Dates</th>
                                <th style="text-align:center;">Statut</th>
                                <th style="text-align:center;width:120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($promotions)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="bi bi-tags"></i>
                                            <p>Aucune promotion pour le moment</p>
                                            <button class="btn-admin btn-primary" onclick="openModal()" style="margin-top:10px;">
                                                <i class="bi bi-plus-circle"></i> Créer une promotion
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($promotions as $p): 
                                    $is_expired = ($p['date_fin'] < $now && $p['est_active'] == 1);
                                    $status_class = $is_expired ? 'expired' : ($p['est_active'] ? 'active' : 'inactive');
                                    $status_label = $is_expired ? 'Expirée' : ($p['est_active'] ? 'Active' : 'Inactive');
                                ?>
                                <tr>
                                    <td style="color:#8A99AA;font-size:0.75rem;">#<?= $p['id'] ?></td>
                                    <td>
                                        <div style="font-weight:600;color:#0D0D0D;"><?= htmlspecialchars($p['nom']) ?></div>
                                        <?php if($p['description']): ?>
                                            <div style="font-size:0.65rem;color:#8A99AA;margin-top:2px;"><?= htmlspecialchars(substr($p['description'], 0, 40)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($p['produit_id']): ?>
                                            <span style="font-weight:500;font-size:0.8rem;"><?= htmlspecialchars($p['produit_nom'] ?? 'Produit #'.$p['produit_id']) ?></span>
                                        <?php else: ?>
                                            <span style="color:#8A99AA;font-size:0.75rem;">Tous les produits</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span style="font-weight:700;color:#C8922A;font-size:1rem;">
                                            <?= $p['valeur'] ?> <?= $p['type'] == 'pourcentage' ? '%' : 'FCFA' ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;font-size:0.7rem;color:#5A6B7A;">
                                        <?= date('d/m/Y', strtotime($p['date_debut'])) ?><br>
                                        <span style="font-size:0.55rem;color:#8A99AA;">→</span>
                                        <?= date('d/m/Y', strtotime($p['date_fin'])) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge-status <?= $status_class ?>"><?= $status_label ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;">
                                            <a href="promotion_modifier.php?id=<?= $p['id'] ?>" class="btn-small blue" title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="promotions.php?toggle=<?= $p['id'] ?>" class="btn-small <?= $p['est_active'] ? 'gray' : 'green' ?>" title="<?= $p['est_active'] ? 'Désactiver' : 'Activer' ?>">
                                                <i class="bi <?= $p['est_active'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                            </a>
                                            <a href="promotions.php?supprimer=<?= $p['id'] ?>" class="btn-small red" onclick="return confirm('Supprimer définitivement cette promotion ?')" title="Supprimer">
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
     MODAL AJOUT PROMOTION (PERSONNALISÉ)
     ============================================ -->
<div class="modal-overlay" id="addPromoModal">
    <div class="modal-box" style="max-width:700px;width:95%;padding:0;overflow:hidden;border-radius:16px;background:#fff;box-shadow:0 25px 60px rgba(0,0,0,0.3);animation:modalIn 0.3s ease;">
        
        <!-- Header -->
        <div style="background:linear-gradient(135deg,#0D0D0D,#1A1A2E);padding:20px 28px;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-family:'Playfair Display',serif;color:#C8922A;margin:0;font-size:1.1rem;">
                <i class="bi bi-plus-circle"></i> Nouvelle promotion
            </h3>
            <button type="button" onclick="closeModal()" style="background:none;border:none;color:rgba(255,255,255,0.5);font-size:1.5rem;cursor:pointer;transition:color 0.3s;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <!-- Body -->
        <form method="POST" style="padding:24px 28px;">
            <input type="hidden" name="action" value="ajouter">
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div style="grid-column:1 / -1;">
                    <label style="display:block;font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:#5A6B7A;margin-bottom:4px;">
                        Nom <span style="color:#E74C3C;">*</span>
                    </label>
                    <input type="text" name="nom" required placeholder="Soldes d'été" 
                           style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-size:0.85rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;">
                </div>
                
                <div style="grid-column:1 / -1;">
                    <label style="display:block;font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:#5A6B7A;margin-bottom:4px;">
                        Produit
                    </label>
                    <select name="produit_id" style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-size:0.85rem;font-family:'Jost',sans-serif;background:#fff;transition:border-color 0.3s;">
                        <option value="">Tous les produits</option>
                        <?php foreach($produits as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="grid-column:1 / -1;">
                    <label style="display:block;font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:#5A6B7A;margin-bottom:4px;">
                        Description
                    </label>
                    <textarea name="description" rows="2" placeholder="Description de la promotion..." 
                              style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-size:0.85rem;font-family:'Jost',sans-serif;resize:vertical;transition:border-color 0.3s;"></textarea>
                </div>
                
                <div>
                    <label style="display:block;font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:#5A6B7A;margin-bottom:4px;">
                        Type <span style="color:#E74C3C;">*</span>
                    </label>
                    <select name="type" required style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-size:0.85rem;font-family:'Jost',sans-serif;background:#fff;transition:border-color 0.3s;">
                        <option value="pourcentage">Pourcentage (%)</option>
                        <option value="montant_fixe">Montant fixe (FCFA)</option>
                    </select>
                </div>
                
                <div>
                    <label style="display:block;font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:#5A6B7A;margin-bottom:4px;">
                        Valeur <span style="color:#E74C3C;">*</span>
                    </label>
                    <input type="number" name="valeur" required placeholder="10" step="1" min="0"
                           style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-size:0.85rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;">
                </div>
                
                <div>
                    <label style="display:block;font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:#5A6B7A;margin-bottom:4px;">
                        Date début <span style="color:#E74C3C;">*</span>
                    </label>
                    <input type="date" name="date_debut" required value="<?= date('Y-m-d') ?>"
                           style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-size:0.85rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;">
                </div>
                
                <div>
                    <label style="display:block;font-weight:600;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:#5A6B7A;margin-bottom:4px;">
                        Date fin <span style="color:#E74C3C;">*</span>
                    </label>
                    <input type="date" name="date_fin" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                           style="width:100%;padding:10px 14px;border:1.5px solid #E8ECF0;border-radius:8px;font-size:0.85rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;">
                </div>
            </div>
            
            <!-- Info -->
            <div style="background:#F0F7FF;border:1px solid #D6E9FF;border-radius:10px;padding:12px 16px;margin:16px 0 20px;font-size:0.82rem;color:#1A3A5A;display:flex;align-items:center;gap:10px;">
                <i class="bi bi-info-circle" style="color:#2980B9;font-size:1.1rem;"></i>
                La promotion sera automatiquement synchronisée avec les produits concernés.
            </div>
            
            <!-- Footer -->
            <div style="display:flex;gap:10px;justify-content:flex-end;border-top:1px solid #E8ECF0;padding-top:16px;">
                <button type="button" onclick="closeModal()" class="btn-admin btn-secondary" style="padding:10px 24px;">
                    Annuler
                </button>
                <button type="submit" class="btn-admin btn-primary" style="padding:10px 28px;">
                    <i class="bi bi-check-lg"></i> Créer la promotion
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>

<script>
function openModal() {
    document.getElementById('addPromoModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('addPromoModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Fermer en cliquant sur l'overlay
document.getElementById('addPromoModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Fermer avec Echap
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>