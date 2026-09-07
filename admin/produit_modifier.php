<?php
// ============================================
// MODIFIER UN PRODUIT - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';
require_once '../includes/functions_securite.php';

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

// Vérification des permissions
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Modifier un produit';

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
// RÉCUPÉRER LE PRODUIT
// ============================================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: produits.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
$stmt->execute([$id]);
$produit = $stmt->fetch();

if (!$produit) {
    header('Location: produits.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES COULEURS ET TAILLES DU PRODUIT
// ============================================
$stmt = $pdo->prepare("SELECT couleur_id FROM produit_couleurs WHERE produit_id = ?");
$stmt->execute([$id]);
$produit_couleurs = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT taille_id FROM produit_tailles WHERE produit_id = ?");
$stmt->execute([$id]);
$produit_tailles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================
$couleurs = $pdo->query("SELECT * FROM couleurs ORDER BY nom")->fetchAll();
$tailles = $pdo->query("SELECT * FROM tailles ORDER BY ordre")->fetchAll();

// ============================================
// TRAITEMENT DU FORMULAIRE
// ============================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = (float)$_POST['prix'];
    $prix_promo = !empty($_POST['prix_promo']) ? (float)$_POST['prix_promo'] : null;
    $categorie_id = (int)$_POST['categorie_id'];
    $stock = (int)$_POST['stock'];
    $seuil_alerte = (int)$_POST['seuil_alerte'];
    $est_nouveau = isset($_POST['est_nouveau']) ? 1 : 0;
    $est_visible = isset($_POST['est_visible']) ? 1 : 0;
    
    $couleurs_selectionnees = isset($_POST['couleurs']) ? $_POST['couleurs'] : [];
    $tailles_selectionnees = isset($_POST['tailles']) ? $_POST['tailles'] : [];
    
    $image_principale = $produit['image_principale'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/produits/';
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (in_array($ext, $allowed)) {
            if (!empty($image_principale) && file_exists($upload_dir . $image_principale)) {
                unlink($upload_dir . $image_principale);
            }
            $image_principale = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_principale);
        } else {
            $error = 'Format d\'image non autorisé (JPG, PNG, WEBP, GIF)';
        }
    }
    
    if (empty($error)) {
        $stmt = $pdo->prepare("
            UPDATE produits SET 
                nom = ?, description = ?, prix = ?, prix_promo = ?, 
                categorie_id = ?, stock = ?, seuil_alerte = ?, 
                image_principale = ?, est_nouveau = ?, est_visible = ?
            WHERE id = ?
        ");
        $stmt->execute([$nom, $description, $prix, $prix_promo, $categorie_id, $stock, 
                       $seuil_alerte, $image_principale, $est_nouveau, $est_visible, $id]);
        
        // Mettre à jour les couleurs
        $pdo->prepare("DELETE FROM produit_couleurs WHERE produit_id = ?")->execute([$id]);
        if (!empty($couleurs_selectionnees)) {
            $stmt = $pdo->prepare("INSERT INTO produit_couleurs (produit_id, couleur_id) VALUES (?, ?)");
            foreach ($couleurs_selectionnees as $couleur_id) {
                $stmt->execute([$id, $couleur_id]);
            }
        }
        
        // Mettre à jour les tailles
        $pdo->prepare("DELETE FROM produit_tailles WHERE produit_id = ?")->execute([$id]);
        if (!empty($tailles_selectionnees)) {
            $stmt = $pdo->prepare("INSERT INTO produit_tailles (produit_id, taille_id) VALUES (?, ?)");
            foreach ($tailles_selectionnees as $taille_id) {
                $stmt->execute([$id, $taille_id]);
            }
        }
        
        if (function_exists('enregistrer_log_action')) {
            enregistrer_log_action(
                $pdo,
                $admin_id,
                $admin_nom,
                $admin_info['email'] ?? '',
                'modification produit',
                "Modification du produit '$nom'"
            );
        }
        
        $_SESSION['message_produit'] = "Produit '$nom' modifié avec succès !";
        header('Location: produits.php');
        exit;
    }
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
            <div class="topbar-title">✏️ Modifier le <span>produit</span></div>
            <div class="topbar-breadcrumb">Administration → Produits → Modifier</div>
        </div>
        <div class="topbar-right">
            <a href="produits.php" class="btn-admin" style="border-color:#E0E6ED;color:#5A6B7A;">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <?php if($error): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ===== FORMULAIRE ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-pencil-square"></i> 
                    Modifier : <?= htmlspecialchars($produit['nom']) ?>
                    <span style="font-size:0.6rem;font-weight:normal;background:#F0F2F5;padding:2px 12px;border-radius:12px;margin-left:10px;color:#5A6B7A;">
                        #<?= $produit['id'] ?>
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding:24px 28px;">
                <form method="POST" enctype="multipart/form-data">
                    
                    <!-- SECTION 1: Informations générales -->
                    <div style="margin-bottom:30px;">
                        <div style="font-size:0.7rem;font-weight:700;color:#C8922A;text-transform:uppercase;letter-spacing:2px;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #F0EDE8;display:flex;align-items:center;gap:10px;">
                            <i class="bi bi-info-circle"></i> Informations générales
                        </div>
                        
                        <div style="margin-bottom:16px;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                Nom du produit <span style="color:#E74C3C;">*</span>
                            </label>
                            <input type="text" name="nom" required value="<?= htmlspecialchars($produit['nom']) ?>" 
                                   style="width:100%;padding:12px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.95rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;background:#FAF9F7;"
                                   onfocus="this.style.borderColor='#C8922A';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                   onblur="this.style.borderColor='#E8ECF0';this.style.background='#FAF9F7';this.style.boxShadow='none'">
                        </div>
                        
                        <div>
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                Description
                            </label>
                            <textarea name="description" rows="3" 
                                      style="width:100%;padding:12px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.95rem;font-family:'Jost',sans-serif;resize:vertical;transition:border-color 0.3s;background:#FAF9F7;"
                                      onfocus="this.style.borderColor='#C8922A';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                      onblur="this.style.borderColor='#E8ECF0';this.style.background='#FAF9F7';this.style.boxShadow='none'"><?= htmlspecialchars($produit['description']) ?></textarea>
                        </div>
                    </div>
                    
                    <!-- SECTION 2: Prix et catégorie -->
                    <div style="margin-bottom:30px;">
                        <div style="font-size:0.7rem;font-weight:700;color:#C8922A;text-transform:uppercase;letter-spacing:2px;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #F0EDE8;display:flex;align-items:center;gap:10px;">
                            <i class="bi bi-tag"></i> Prix et catégorie
                        </div>
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div>
                                <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                    Prix (FCFA) <span style="color:#E74C3C;">*</span>
                                </label>
                                <input type="number" name="prix" required value="<?= $produit['prix'] ?>" step="0.01"
                                       style="width:100%;padding:12px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.95rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;background:#FAF9F7;"
                                       onfocus="this.style.borderColor='#C8922A';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                       onblur="this.style.borderColor='#E8ECF0';this.style.background='#FAF9F7';this.style.boxShadow='none'">
                            </div>
                            <div>
                                <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                    Prix promotionnel <span style="font-weight:400;color:#8A99AA;font-size:0.65rem;margin-left:6px;">(optionnel)</span>
                                </label>
                                <input type="number" name="prix_promo" value="<?= $produit['prix_promo'] ?>" placeholder="Laissez vide"
                                       style="width:100%;padding:12px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.95rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;background:#FAF9F7;"
                                       onfocus="this.style.borderColor='#C8922A';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                       onblur="this.style.borderColor='#E8ECF0';this.style.background='#FAF9F7';this.style.boxShadow='none'">
                                <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">Le prix promo sera affiché en rouge barré sur le site</div>
                            </div>
                        </div>
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div>
                                <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                    Catégorie <span style="color:#E74C3C;">*</span>
                                </label>
                                <select name="categorie_id" required 
                                        style="width:100%;padding:12px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.95rem;font-family:'Jost',sans-serif;background:#FAF9F7;appearance:none;background-image:url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%228%22 viewBox=%220 0 12 8%22%3E%3Cpath d=%22M1 1l5 5 5-5%22 stroke=%22%238A99AA%22 stroke-width=%222%22 fill=%22none%22 stroke-linecap=%22round%22/%3E%3C/svg%3E');background-repeat:no-repeat;background-position:right 16px center;padding-right:40px;transition:border-color 0.3s;cursor:pointer;"
                                        onfocus="this.style.borderColor='#C8922A';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                        onblur="this.style.borderColor='#E8ECF0';this.style.background='#FAF9F7';this.style.boxShadow='none'">
                                    <option value="">Sélectionner une catégorie</option>
                                    <option value="11" <?= $produit['categorie_id'] == 11 ? 'selected' : '' ?>>👗 Abayas Bijoux</option>
                                    <option value="12" <?= $produit['categorie_id'] == 12 ? 'selected' : '' ?>>👗 Abayas Bibi</option>
                                    <option value="13" <?= $produit['categorie_id'] == 13 ? 'selected' : '' ?>>👗 Abayas Stars</option>
                                    <option value="14" <?= $produit['categorie_id'] == 14 ? 'selected' : '' ?>>👗 Abayas Enfant</option>
                                    <option value="21" <?= $produit['categorie_id'] == 21 ? 'selected' : '' ?>>🧣 Foulards</option>
                                    <option value="22" <?= $produit['categorie_id'] == 22 ? 'selected' : '' ?>>🧣 Turbants</option>
                                    <option value="23" <?= $produit['categorie_id'] == 23 ? 'selected' : '' ?>>🧣 Voiles</option>
                                    <option value="31" <?= $produit['categorie_id'] == 31 ? 'selected' : '' ?>>👜 Sacs à main</option>
                                    <option value="32" <?= $produit['categorie_id'] == 32 ? 'selected' : '' ?>>👜 Porte-monnaie</option>
                                    <option value="33" <?= $produit['categorie_id'] == 33 ? 'selected' : '' ?>>👜 Sacs complets</option>
                                    <option value="34" <?= $produit['categorie_id'] == 34 ? 'selected' : '' ?>>👜 Accessoires</option>
                                    <option value="41" <?= $produit['categorie_id'] == 41 ? 'selected' : '' ?>>👠 Talons</option>
                                    <option value="42" <?= $produit['categorie_id'] == 42 ? 'selected' : '' ?>>👠 Ballerines</option>
                                    <option value="43" <?= $produit['categorie_id'] == 43 ? 'selected' : '' ?>>👠 Sandales</option>
                                    <option value="44" <?= $produit['categorie_id'] == 44 ? 'selected' : '' ?>>👠 Fermées</option>
                                    <option value="51" <?= $produit['categorie_id'] == 51 ? 'selected' : '' ?>>👚 Robes</option>
                                    <option value="52" <?= $produit['categorie_id'] == 52 ? 'selected' : '' ?>>👚 Ensembles</option>
                                    <option value="53" <?= $produit['categorie_id'] == 53 ? 'selected' : '' ?>>👚 Vestes</option>
                                    <option value="54" <?= $produit['categorie_id'] == 54 ? 'selected' : '' ?>>👚 Jupes</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                    Stock
                                </label>
                                <input type="number" name="stock" value="<?= $produit['stock'] ?>" min="0"
                                       style="width:100%;padding:12px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.95rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;background:#FAF9F7;"
                                       onfocus="this.style.borderColor='#C8922A';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                       onblur="this.style.borderColor='#E8ECF0';this.style.background='#FAF9F7';this.style.boxShadow='none'">
                            </div>
                        </div>
                    </div>
                    
                    <!-- SECTION 3: Couleurs et tailles -->
                    <div style="margin-bottom:30px;">
                        <div style="font-size:0.7rem;font-weight:700;color:#C8922A;text-transform:uppercase;letter-spacing:2px;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #F0EDE8;display:flex;align-items:center;gap:10px;">
                            <i class="bi bi-palette"></i> Couleurs et tailles
                        </div>
                        
                        <!-- Couleurs -->
                        <div style="margin-bottom:16px;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                Couleurs disponibles
                            </label>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;">
                                <?php foreach ($couleurs as $c): 
                                    $checked = in_array($c['id'], $produit_couleurs) ? 'checked' : '';
                                ?>
                                <label style="display:flex;align-items:center;gap:10px;padding:8px 14px;background:#FAF9F7;border:2px solid <?= $checked ? '#C8922A' : '#EEEAE5' ?>;border-radius:10px;cursor:pointer;transition:all 0.3s;<?= $checked ? 'background:rgba(200,146,42,0.05);' : '' ?>">
                                    <input type="checkbox" name="couleurs[]" value="<?= $c['id'] ?>" <?= $checked ?> style="width:16px;height:16px;accent-color:#C8922A;">
                                    <span style="width:24px;height:24px;border-radius:50%;background-color:<?= $c['code_hex'] ?>;border:2px solid <?= $c['code_hex'] == '#FFFFFF' ? '#ccc' : '#ddd' ?>;flex-shrink:0;"></span>
                                    <span style="font-size:0.8rem;font-weight:500;color:#1A2C3E;"><?= htmlspecialchars($c['nom']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div style="font-size:0.65rem;color:#8A99AA;margin-top:6px;">Sélectionnez les couleurs disponibles pour ce produit</div>
                        </div>
                        
                        <!-- Tailles -->
                        <div>
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                Tailles disponibles
                            </label>
                            
                            <div style="margin-bottom:10px;">
                                <div style="font-size:0.6rem;color:#8A99AA;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Tailles standard (XS à XXL)</div>
                                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                    <?php 
                                    $standard_tailles = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
                                    foreach ($tailles as $t): 
                                        if (!in_array($t['nom'], $standard_tailles) && !is_numeric($t['nom'])) continue;
                                        $checked = in_array($t['id'], $produit_tailles) ? 'checked' : '';
                                    ?>
                                    <label style="display:flex;align-items:center;gap:6px;padding:6px 14px 6px 10px;background:#FAF9F7;border:2px solid <?= $checked ? '#C8922A' : '#EEEAE5' ?>;border-radius:20px;cursor:pointer;transition:all 0.3s;<?= $checked ? 'background:rgba(200,146,42,0.05);' : '' ?>">
                                        <input type="checkbox" name="tailles[]" value="<?= $t['id'] ?>" <?= $checked ?> style="width:15px;height:15px;accent-color:#C8922A;">
                                        <span style="font-size:0.8rem;font-weight:600;color:#1A2C3E;"><?= htmlspecialchars($t['nom']) ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div>
                                <div style="font-size:0.6rem;color:#8A99AA;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Tailles chaussures (34 à 46)</div>
                                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                    <?php foreach ($tailles as $t): 
                                        if (!is_numeric($t['nom'])) continue;
                                        $num = (int)$t['nom'];
                                        if ($num < 34 || $num > 46) continue;
                                        $checked = in_array($t['id'], $produit_tailles) ? 'checked' : '';
                                    ?>
                                    <label style="display:flex;align-items:center;gap:6px;padding:6px 14px 6px 10px;background:#FAF9F7;border:2px solid <?= $checked ? '#C8922A' : '#EEEAE5' ?>;border-radius:20px;cursor:pointer;transition:all 0.3s;<?= $checked ? 'background:rgba(200,146,42,0.05);' : '' ?>">
                                        <input type="checkbox" name="tailles[]" value="<?= $t['id'] ?>" <?= $checked ?> style="width:15px;height:15px;accent-color:#C8922A;">
                                        <span style="font-size:0.8rem;font-weight:600;color:#1A2C3E;"><?= htmlspecialchars($t['nom']) ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div style="font-size:0.65rem;color:#8A99AA;margin-top:6px;">Sélectionnez les tailles disponibles pour ce produit</div>
                        </div>
                    </div>
                    
                    <!-- SECTION 4: Paramètres -->
                    <div style="margin-bottom:24px;">
                        <div style="font-size:0.7rem;font-weight:700;color:#C8922A;text-transform:uppercase;letter-spacing:2px;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #F0EDE8;display:flex;align-items:center;gap:10px;">
                            <i class="bi bi-gear"></i> Paramètres
                        </div>
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div>
                                <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                    Seuil d'alerte
                                </label>
                                <input type="number" name="seuil_alerte" value="<?= $produit['seuil_alerte'] ?>" min="0"
                                       style="width:100%;padding:12px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.95rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;background:#FAF9F7;"
                                       onfocus="this.style.borderColor='#C8922A';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                       onblur="this.style.borderColor='#E8ECF0';this.style.background='#FAF9F7';this.style.boxShadow='none'">
                                <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">Alerte lorsque le stock atteint ce niveau</div>
                            </div>
                            <div>
                                <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                    Nouvelle image
                                </label>
                                <input type="file" name="image" accept="image/*" 
                                       style="width:100%;padding:10px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.9rem;font-family:'Jost',sans-serif;background:#FAF9F7;transition:border-color 0.3s;"
                                       onfocus="this.style.borderColor='#C8922A';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                       onblur="this.style.borderColor='#E8ECF0';this.style.boxShadow='none'">
                                <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">Formats : JPG, PNG, WEBP, GIF</div>
                                <?php if(!empty($produit['image_principale']) && file_exists('../uploads/produits/'.$produit['image_principale'])): ?>
                                    <div style="margin-top:8px;">
                                        <img src="../uploads/produits/<?= $produit['image_principale'] ?>" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #E8ECF0;">
                                        <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">Image actuelle</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <label style="display:flex;align-items:center;gap:14px;padding:12px 18px;background:#FAF9F7;border:2px solid <?= $produit['est_nouveau'] ? '#C8922A' : '#EEEAE5' ?>;border-radius:12px;cursor:pointer;transition:all 0.3s;<?= $produit['est_nouveau'] ? 'background:rgba(200,146,42,0.05);' : '' ?>">
                                <input type="checkbox" name="est_nouveau" <?= $produit['est_nouveau'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:#C8922A;">
                                <div>
                                    <span style="font-size:0.6rem;padding:3px 12px;border-radius:20px;font-weight:600;text-transform:uppercase;background:linear-gradient(135deg,#C8922A,#E8B55A);color:white;">⭐ Nouveau</span>
                                </div>
                                <span style="font-size:0.65rem;color:#8A99AA;margin-left:auto;">Marquer comme nouveau</span>
                            </label>
                            
                            <label style="display:flex;align-items:center;gap:14px;padding:12px 18px;background:#FAF9F7;border:2px solid <?= $produit['est_visible'] ? '#27AE60' : '#EEEAE5' ?>;border-radius:12px;cursor:pointer;transition:all 0.3s;<?= $produit['est_visible'] ? 'background:rgba(39,174,96,0.05);' : '' ?>">
                                <input type="checkbox" name="est_visible" <?= $produit['est_visible'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:#C8922A;">
                                <div>
                                    <span style="font-size:0.6rem;padding:3px 12px;border-radius:20px;font-weight:600;text-transform:uppercase;background:rgba(39,174,96,0.12);color:#27AE60;border:1px solid rgba(39,174,96,0.15);">● Visible</span>
                                </div>
                                <span style="font-size:0.65rem;color:#8A99AA;margin-left:auto;">Afficher sur le site</span>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-admin btn-primary" style="padding:16px 40px;font-size:0.95rem;width:100%;justify-content:center;border-radius:12px;font-weight:700;">
                        <i class="bi bi-save"></i> Enregistrer les modifications
                    </button>
                </form>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>