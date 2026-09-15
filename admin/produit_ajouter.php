<?php
// ============================================
// AJOUTER UN PRODUIT - ADMIN AWA KA SUGU
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

$page_title = 'Ajouter un produit';

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
// RÉCUPÉRER LES DONNÉES
// ============================================
$couleurs = $pdo->query("SELECT * FROM couleurs ORDER BY nom")->fetchAll();

$tailles = $pdo->query("SELECT * FROM tailles ORDER BY ordre")->fetchAll();

// Ajouter des tailles de chaussures si elles n'existent pas
$chaussures_tailles = [34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46];
foreach ($chaussures_tailles as $t) {
    $check = $pdo->prepare("SELECT id FROM tailles WHERE nom = ?");
    $check->execute([$t]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO tailles (nom, ordre) VALUES (?, ?)");
        $stmt->execute([$t, $t]);
    }
}
// Recharger les tailles
$tailles = $pdo->query("SELECT * FROM tailles ORDER BY ordre")->fetchAll();

// ============================================
// LISTE DES COULEURS PRÉDÉFINIES
// ============================================
$couleurs_list = [
    ['nom' => 'Blanc', 'code' => '#FFFFFF'],
    ['nom' => 'Noir', 'code' => '#000000'],
    ['nom' => 'Beige', 'code' => '#F5E6D3'],
    ['nom' => 'Crème', 'code' => '#FFF8F0'],
    ['nom' => 'Marron', 'code' => '#8B6914'],
    ['nom' => 'Marron clair', 'code' => '#A67C52'],
    ['nom' => 'Or', 'code' => '#C8922A'],
    ['nom' => 'Doré', 'code' => '#D4AF37'],
    ['nom' => 'Rouge', 'code' => '#E74C3C'],
    ['nom' => 'Bordeaux', 'code' => '#800020'],
    ['nom' => 'Rose', 'code' => '#E91E63'],
    ['nom' => 'Rose poudré', 'code' => '#F4C2C2'],
    ['nom' => 'Bleu', 'code' => '#3498DB'],
    ['nom' => 'Bleu ciel', 'code' => '#87CEEB'],
    ['nom' => 'Bleu marine', 'code' => '#1A2A6C'],
    ['nom' => 'Vert', 'code' => '#27AE60'],
    ['nom' => 'Vert olive', 'code' => '#556B2F'],
    ['nom' => 'Vert émeraude', 'code' => '#50C878'],
    ['nom' => 'Violet', 'code' => '#9B59B6'],
    ['nom' => 'Lavande', 'code' => '#E6E6FA'],
    ['nom' => 'Gris', 'code' => '#808080'],
    ['nom' => 'Gris clair', 'code' => '#D3D3D3'],
    ['nom' => 'Jaune', 'code' => '#F1C40F'],
    ['nom' => 'Orange', 'code' => '#E67E22'],
    ['nom' => 'Turquoise', 'code' => '#40E0D0'],
    ['nom' => 'Mauve', 'code' => '#E0B0FF'],
    ['nom' => 'Corail', 'code' => '#FF7F50'],
    ['nom' => 'Fuchsia', 'code' => '#FF00FF'],
    ['nom' => 'Indigo', 'code' => '#4B0082'],
    ['nom' => 'Kaki', 'code' => '#BDB76B'],
];

// Assurer que toutes les couleurs existent en base
foreach ($couleurs_list as $c) {
    $check = $pdo->prepare("SELECT id FROM couleurs WHERE nom = ?");
    $check->execute([$c['nom']]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO couleurs (nom, code_hex) VALUES (?, ?)");
        $stmt->execute([$c['nom'], $c['code']]);
    }
}
$couleurs = $pdo->query("SELECT * FROM couleurs ORDER BY nom")->fetchAll();

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
    
    // ============================================
    // UPLOAD MULTIPLE DE PHOTOS
    // La 1ère photo uploadée devient l'image de couverture (image_principale)
    // TOUTES les photos (y compris la 1ère) sont enregistrées dans produit_images
    // pour alimenter la galerie de la fiche produit.
    // ============================================
    $image_principale = '';
    $photos_uploadees = []; // liste ordonnée des noms de fichiers uploadés

    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $upload_dir = '../uploads/produits/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $nb_fichiers = count($_FILES['images']['name']);

        for ($i = 0; $i < $nb_fichiers; $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                continue; // on ignore les emplacements vides / en erreur
            }

            $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = 'Format d\'image non autorisé (JPG, PNG, WEBP, GIF)';
                continue;
            }

            $nom_fichier = uniqid() . '_' . $i . '.' . $ext;
            move_uploaded_file($_FILES['images']['tmp_name'][$i], $upload_dir . $nom_fichier);

            if ($image_principale === '') {
                $image_principale = $nom_fichier; // 1ère photo = couverture
            }
            $photos_uploadees[] = $nom_fichier;
        }
    }
    
    if (empty($error)) {
        $stmt = $pdo->prepare("
            INSERT INTO produits (nom, description, prix, prix_promo, categorie_id, stock, seuil_alerte, image_principale, est_nouveau, est_visible, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$nom, $description, $prix, $prix_promo, $categorie_id, $stock, $seuil_alerte, $image_principale, $est_nouveau, $est_visible]);
        $produit_id = $pdo->lastInsertId();
        
        if (!empty($couleurs_selectionnees)) {
            $stmt = $pdo->prepare("INSERT INTO produit_couleurs (produit_id, couleur_id) VALUES (?, ?)");
            foreach ($couleurs_selectionnees as $couleur_id) {
                $stmt->execute([$produit_id, $couleur_id]);
            }
        }
        
        if (!empty($tailles_selectionnees)) {
            $stmt = $pdo->prepare("INSERT INTO produit_tailles (produit_id, taille_id) VALUES (?, ?)");
            foreach ($tailles_selectionnees as $taille_id) {
                $stmt->execute([$produit_id, $taille_id]);
            }
        }

        // Enregistrement de toutes les photos dans la galerie produit_images
        if (!empty($photos_uploadees)) {
            $stmt = $pdo->prepare("INSERT INTO produit_images (produit_id, nom_fichier, ordre) VALUES (?, ?, ?)");
            foreach ($photos_uploadees as $index => $fichier) {
                $stmt->execute([$produit_id, $fichier, $index]);
            }
        }
        
        if (function_exists('enregistrer_log_action')) {
            enregistrer_log_action(
                $pdo,
                $admin_id,
                $admin_nom,
                $admin_info['email'] ?? '',
                'ajout produit',
                "Ajout du produit '$nom'"
            );
        }
        
        $_SESSION['message_produit'] = "Produit '$nom' ajouté avec succès !";
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
            <div class="topbar-title">✦ Ajouter un <span>produit</span></div>
            <div class="topbar-breadcrumb">Administration → Produits → Ajouter</div>
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
                <div class="card-title"><i class="bi bi-plus-circle"></i> Nouveau produit</div>
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
                                <span style="font-weight:400;color:#8A99AA;font-size:0.65rem;margin-left:6px;">(ex: Abaya noire élégante)</span>
                            </label>
                            <input type="text" name="nom" required placeholder="Entrez le nom du produit" 
                                   style="width:100%;padding:12px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.95rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;background:#FAF9F7;"
                                   onfocus="this.style.borderColor='#C8922A';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                   onblur="this.style.borderColor='#E8ECF0';this.style.background='#FAF9F7';this.style.boxShadow='none'">
                        </div>
                        
                        <div>
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                Description
                            </label>
                            <textarea name="description" rows="3" placeholder="Description détaillée du produit..." 
                                      style="width:100%;padding:12px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.95rem;font-family:'Jost',sans-serif;resize:vertical;transition:border-color 0.3s;background:#FAF9F7;"
                                      onfocus="this.style.borderColor='#C8922A';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                      onblur="this.style.borderColor='#E8ECF0';this.style.background='#FAF9F7';this.style.boxShadow='none'"></textarea>
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
                                <input type="number" name="prix" required placeholder="35000" 
                                       style="width:100%;padding:12px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.95rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;background:#FAF9F7;"
                                       onfocus="this.style.borderColor='#C8922A';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                       onblur="this.style.borderColor='#E8ECF0';this.style.background='#FAF9F7';this.style.boxShadow='none'">
                            </div>
                            <div>
                                <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                    Prix promotionnel <span style="font-weight:400;color:#8A99AA;font-size:0.65rem;margin-left:6px;">(optionnel)</span>
                                </label>
                                <input type="number" name="prix_promo" placeholder="Laissez vide" 
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
                                    <option value="11">👗 Abayas Bijoux</option>
                                    <option value="12">👗 Abayas Bibi</option>
                                    <option value="13">👗 Abayas Stars</option>
                                    <option value="14">👗 Abayas Enfant</option>
                                    <option value="21">🧣 Foulards</option>
                                    <option value="22">🧣 Turbants</option>
                                    <option value="23">🧣 Voiles</option>
                                    <option value="31">👜 Sacs à main</option>
                                    <option value="32">👜 Porte-monnaie</option>
                                    <option value="33">👜 Sacs complets</option>
                                    <option value="34">👜 Accessoires</option>
                                    <option value="41">👠 Talons</option>
                                    <option value="42">👠 Ballerines</option>
                                    <option value="43">👠 Sandales</option>
                                    <option value="44">👠 Fermées</option>
                                    <option value="51">👚 Robes</option>
                                    <option value="52">👚 Ensembles</option>
                                    <option value="53">👚 Vestes</option>
                                    <option value="54">👚 Jupes</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                    Stock initial
                                </label>
                                <input type="number" name="stock" value="0" min="0" 
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
                                <?php foreach ($couleurs as $c): ?>
                                <label style="display:flex;align-items:center;gap:10px;padding:8px 14px;background:#FAF9F7;border:2px solid #EEEAE5;border-radius:10px;cursor:pointer;transition:all 0.3s;">
                                    <input type="checkbox" name="couleurs[]" value="<?= $c['id'] ?>" style="width:16px;height:16px;accent-color:#C8922A;">
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
                                    ?>
                                    <label style="display:flex;align-items:center;gap:6px;padding:6px 14px 6px 10px;background:#FAF9F7;border:2px solid #EEEAE5;border-radius:20px;cursor:pointer;transition:all 0.3s;">
                                        <input type="checkbox" name="tailles[]" value="<?= $t['id'] ?>" style="width:15px;height:15px;accent-color:#C8922A;">
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
                                    ?>
                                    <label style="display:flex;align-items:center;gap:6px;padding:6px 14px 6px 10px;background:#FAF9F7;border:2px solid #EEEAE5;border-radius:20px;cursor:pointer;transition:all 0.3s;">
                                        <input type="checkbox" name="tailles[]" value="<?= $t['id'] ?>" style="width:15px;height:15px;accent-color:#C8922A;">
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
                                <input type="number" name="seuil_alerte" value="5" min="0" 
                                       style="width:100%;padding:12px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.95rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;background:#FAF9F7;"
                                       onfocus="this.style.borderColor='#C8922A';this.style.background='#fff';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                       onblur="this.style.borderColor='#E8ECF0';this.style.background='#FAF9F7';this.style.boxShadow='none'">
                                <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">Alerte lorsque le stock atteint ce niveau</div>
                            </div>
                            <div>
                                <label style="display:block;font-size:0.75rem;font-weight:600;color:#1A2C3E;margin-bottom:5px;">
                                    Photos du produit
                                    <span style="font-weight:400;color:#8A99AA;font-size:0.65rem;margin-left:6px;">(la 1ère sera la couverture)</span>
                                </label>
                                <input type="file" name="images[]" accept="image/*" multiple
                                       style="width:100%;padding:10px 16px;border:2px solid #E8ECF0;border-radius:10px;font-size:0.9rem;font-family:'Jost',sans-serif;background:#FAF9F7;transition:border-color 0.3s;"
                                       onfocus="this.style.borderColor='#C8922A';this.style.boxShadow='0 0 0 4px rgba(200,146,42,0.08)'"
                                       onblur="this.style.borderColor='#E8ECF0';this.style.boxShadow='none'"
                                       onchange="previewNewImages(this)">
                                <div style="font-size:0.65rem;color:#8A99AA;margin-top:4px;">Formats : JPG, PNG, WEBP, GIF — vous pouvez en sélectionner plusieurs à la fois</div>
                                <div id="previewNewImages" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;"></div>
                            </div>
                        </div>
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <label style="display:flex;align-items:center;gap:14px;padding:12px 18px;background:#FAF9F7;border:2px solid #EEEAE5;border-radius:12px;cursor:pointer;transition:all 0.3s;">
                                <input type="checkbox" name="est_nouveau" checked style="width:18px;height:18px;accent-color:#C8922A;">
                                <div>
                                    <span style="font-size:0.6rem;padding:3px 12px;border-radius:20px;font-weight:600;text-transform:uppercase;background:linear-gradient(135deg,#C8922A,#E8B55A);color:white;">⭐ Nouveau</span>
                                </div>
                                <span style="font-size:0.65rem;color:#8A99AA;margin-left:auto;">Marquer comme nouveau</span>
                            </label>
                            
                            <label style="display:flex;align-items:center;gap:14px;padding:12px 18px;background:#FAF9F7;border:2px solid #EEEAE5;border-radius:12px;cursor:pointer;transition:all 0.3s;">
                                <input type="checkbox" name="est_visible" checked style="width:18px;height:18px;accent-color:#C8922A;">
                                <div>
                                    <span style="font-size:0.6rem;padding:3px 12px;border-radius:20px;font-weight:600;text-transform:uppercase;background:rgba(39,174,96,0.12);color:#27AE60;border:1px solid rgba(39,174,96,0.15);">● Visible</span>
                                </div>
                                <span style="font-size:0.65rem;color:#8A99AA;margin-left:auto;">Afficher sur le site</span>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-admin btn-primary" style="padding:16px 40px;font-size:0.95rem;width:100%;justify-content:center;border-radius:12px;font-weight:700;">
                        <i class="bi bi-save"></i> Enregistrer le produit
                    </button>
                </form>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<script>
// Aperçu des nouvelles photos sélectionnées avant l'envoi du formulaire
function previewNewImages(input) {
    const container = document.getElementById('previewNewImages');
    container.innerHTML = '';
    if (!input.files) return;

    Array.from(input.files).forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'position:relative;width:70px;height:70px;border-radius:8px;overflow:hidden;border:2px solid ' + (index === 0 ? '#C8922A' : '#E8ECF0') + ';';
            wrapper.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">' +
                (index === 0 ? '<span style="position:absolute;bottom:0;left:0;right:0;background:#C8922A;color:#fff;font-size:0.55rem;text-align:center;padding:2px 0;">Couverture</span>' : '');
            container.appendChild(wrapper);
        };
        reader.readAsDataURL(file);
    });
}
</script>

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>