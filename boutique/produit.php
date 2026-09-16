<?php
// ============================================
// PAGE PRODUIT - Awa Ka Sugu
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: catalogue.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND est_visible = 1");
$stmt->execute([$id]);
$produit = $stmt->fetch();

if (!$produit) {
    header('Location: catalogue.php');
    exit;
}

// Récupérer la catégorie
$categorie = null;
if ($produit['categorie_id']) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$produit['categorie_id']]);
    $categorie = $stmt->fetch();
}

// Récupérer les couleurs UNIQUES du produit
$stmt = $pdo->prepare("
    SELECT DISTINCT c.* FROM couleurs c
    JOIN produit_couleurs pc ON pc.couleur_id = c.id
    WHERE pc.produit_id = ?
");
$stmt->execute([$id]);
$couleurs_produit = $stmt->fetchAll();

// Récupérer les tailles UNIQUES du produit
$stmt = $pdo->prepare("
    SELECT DISTINCT t.* FROM tailles t
    JOIN produit_tailles pt ON pt.taille_id = t.id
    WHERE pt.produit_id = ?
");
$stmt->execute([$id]);
$tailles_produit = $stmt->fetchAll();

// ============================================
// GALERIE D'IMAGES SUPPLÉMENTAIRES
// ============================================
$images_supplementaires = [];
try {
    $stmt = $pdo->prepare("
        SELECT nom_fichier FROM produit_images 
        WHERE produit_id = ? 
        ORDER BY ordre ASC, id ASC
    ");
    $stmt->execute([$id]);
    $images_supplementaires = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $images_supplementaires = [];
}

// ============================================
// PRODUIT PRÉCÉDENT / SUIVANT
// ============================================
$produit_precedent = null;
$produit_suivant = null;
try {
    $stmt = $pdo->prepare("
        SELECT id, nom, image_principale FROM produits
        WHERE categorie_id = ? AND est_visible = 1 AND id < ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$produit['categorie_id'], $id]);
    $produit_precedent = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT id, nom, image_principale FROM produits
        WHERE categorie_id = ? AND est_visible = 1 AND id > ?
        ORDER BY id ASC LIMIT 1
    ");
    $stmt->execute([$produit['categorie_id'], $id]);
    $produit_suivant = $stmt->fetch();
} catch(PDOException $e) {
    $produit_precedent = null;
    $produit_suivant = null;
}

// ============================================
// AVIS DU PRODUIT
// ============================================
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM avis_clients LIKE 'est_visible'");
    $colonne_est_visible = $stmt->fetch();
} catch(PDOException $e) {
    $colonne_est_visible = false;
}

if ($colonne_est_visible) {
    $stmt = $pdo->prepare("
        SELECT * FROM avis_clients 
        WHERE produit_id = ? AND est_visible = 1 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$id]);
    $avis_produit = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(AVG(note), 0) AS moyenne,
            COUNT(*) AS total
        FROM avis_clients 
        WHERE produit_id = ? AND est_visible = 1
    ");
    $stmt->execute([$id]);
    $stats_avis = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("
        SELECT * FROM avis_clients 
        WHERE produit_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$id]);
    $avis_produit = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(AVG(note), 0) AS moyenne,
            COUNT(*) AS total
        FROM avis_clients 
        WHERE produit_id = ?
    ");
    $stmt->execute([$id]);
    $stats_avis = $stmt->fetch();
}

// ============================================
// PRODUITS SIMILAIRES
// ============================================
$similaires = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM produits 
        WHERE categorie_id = ? AND id != ? AND est_visible = 1 
        ORDER BY RAND() LIMIT 8
    ");
    $stmt->execute([$produit['categorie_id'], $id]);
    $similaires_categorie = $stmt->fetchAll();
    
    if (count($similaires_categorie) < 8) {
        $exclude_ids = array_merge([$id], array_column($similaires_categorie, 'id'));
        $placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT * FROM produits 
            WHERE est_visible = 1 AND id NOT IN ($placeholders)
            ORDER BY RAND() LIMIT " . (8 - count($similaires_categorie))
        );
        $stmt->execute($exclude_ids);
        $similaires_autres = $stmt->fetchAll();
        $similaires = array_merge($similaires_categorie, $similaires_autres);
    } else {
        $similaires = $similaires_categorie;
    }
} catch(PDOException $e) {
    $similaires = [];
}

// Fonction image produit
function getProductImageDetail($image) {
    if (empty($image)) {
        return 'https://placehold.co/600x600/F5F5F5/C8922A?text=Produit';
    }
    
    $image = trim($image);
    $image_name = pathinfo($image, PATHINFO_FILENAME);
    $extension = pathinfo($image, PATHINFO_EXTENSION);
    
    $dossiers = [
        '../uploads/produits/voile/',
        'uploads/produits/voile/',
        '../uploads/produits/pret a porter femme/',
        'uploads/produits/pret a porter femme/',
        '../uploads/produits/les tallons/',
        'uploads/produits/les tallons/',
        '../uploads/produits/fermés/',
        'uploads/produits/fermés/',
        '../uploads/produits/les turbants/',
        'uploads/produits/les turbants/',
        '../uploads/produits/les foulards/',
        'uploads/produits/les foulards/',
        '../uploads/produits/les foullards/',
        'uploads/produits/les foullards/',
        '../uploads/produits/port-monaie/',
        'uploads/produits/port-monaie/',
        '../uploads/produits/sacs a mains/',
        'uploads/produits/sacs a mains/',
        '../uploads/produits/ensemble tallons sacs/',
        'uploads/produits/ensemble tallons sacs/',
        '../uploads/produits/abayas/',
        'uploads/produits/abayas/',
        '../uploads/produits/abayas pour enfants/',
        'uploads/produits/abayas pour enfants/',
        '../uploads/produits/',
        'uploads/produits/',
    ];
    
    $extensions = ['', '.jpeg', '.jpg', '.png', '.gif', '.webp'];
    
    if (!empty($extension)) {
        $extensions = array_merge([$extension], $extensions);
    }
    
    foreach ($dossiers as $dossier) {
        foreach ($extensions as $ext) {
            $test_path = $dossier . $image_name . $ext;
            if (file_exists($test_path)) {
                return $test_path;
            }
        }
    }
    
    return 'https://placehold.co/600x600/F5F5F5/C8922A?text=' . urlencode($image_name);
}

$prix_affiché = $produit['prix'];
$prix_ancien = null;
if ($produit['prix_promo'] && $produit['prix_promo'] > 0 && $produit['prix_promo'] < $produit['prix']) {
    $prix_affiché = $produit['prix_promo'];
    $prix_ancien = $produit['prix'];
}

$titre_page = 'Détail produit - Awa Ka Sugu';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
/* ============================================
   PAGE PRODUIT - DESIGN NÉON OR
   ============================================ */
.produit-page { 
    padding: 40px 0 60px;
    background: #F5F7FA;
}
.container-custom { 
    max-width: 1300px; 
    margin: 0 auto; 
    padding: 0 24px; 
}

/* ===== BANDEAU RÉASSURANCE ===== */
.trust-bar {
    display: flex;
    gap: 24px;
    justify-content: center;
    flex-wrap: wrap;
    background: #fff;
    border: 1.5px solid rgba(200,146,42,0.15);
    border-radius: 16px;
    padding: 16px 24px;
    margin-bottom: 26px;
    box-shadow: 0 2px 12px rgba(200,146,42,0.04);
}
.trust-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.82rem;
    font-weight: 500;
    color: #1A2C3E;
    white-space: nowrap;
}
.trust-item i { 
    color: #C8922A; 
    font-size: 1.1rem;
    text-shadow: 0 0 10px rgba(200,146,42,0.3);
}

/* ===== GRILLE PRINCIPALE ===== */
.produit-grid {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 50px;
    background: #fff;
    border-radius: 20px;
    padding: 38px;
    box-shadow: 
        0 4px 20px rgba(0,0,0,0.04),
        0 0 40px rgba(200,146,42,0.04);
    border: 1.5px solid rgba(200,146,42,0.12);
}

/* ===== BLOC IMAGE ===== */
.produit-image-wrap {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.produit-image {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #FAFBFC, #F5F7FA);
    border-radius: 16px;
    padding: 30px;
    min-height: 420px;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(200,146,42,0.08);
}
.produit-image img {
    max-width: 100%;
    max-height: 460px;
    object-fit: contain;
    transition: transform 0.4s cubic-bezier(.2,.7,.2,1);
    cursor: zoom-in;
}
.produit-image img:hover { 
    transform: scale(1.03); 
}

/* Galerie vignettes */
.galerie-vignettes {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: flex-start;
    padding: 4px 0;
}
.galerie-vignettes .vignette-item {
    width: 76px;
    height: 76px;
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #F8F9FA;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.galerie-vignettes .vignette-item:hover {
    border-color: #C8922A;
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(200,146,42,0.2);
}
.galerie-vignettes .vignette-item.active {
    border-color: #C8922A;
    box-shadow: 0 0 0 3px rgba(200,146,42,0.2);
}
.galerie-vignettes .vignette-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Bouton Grand cadre */
.btn-grand-cadre {
    position: absolute;
    bottom: 16px;
    right: 16px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #0D0D0D, #1A1510);
    color: #fff;
    border: 1.5px solid rgba(200,146,42,0.4);
    padding: 9px 18px;
    border-radius: 30px;
    font-family: 'Jost', sans-serif;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    backdrop-filter: blur(6px);
    z-index: 5;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.btn-grand-cadre:hover { 
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(200,146,42,0.4);
}

/* Navigation produits */
.produit-nav-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255,255,255,0.95);
    border: 1.5px solid rgba(200,146,42,0.25);
    color: #1A2C3E;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-decoration: none;
    z-index: 6;
    transition: all 0.25s ease;
}
.produit-nav-arrow:hover { 
    background: linear-gradient(135deg, #C8922A, #E8B55A); 
    color: #fff;
    box-shadow: 0 6px 20px rgba(200,146,42,0.4);
    transform: translateY(-50%) scale(1.08);
}
.produit-nav-arrow.prev { left: 12px; }
.produit-nav-arrow.next { right: 12px; }
.produit-nav-hint {
    text-align: center;
    font-size: 0.72rem;
    color: #8A99AA;
    margin-top: 10px;
}
.produit-nav-hint i { color: #C8922A; }

/* ===== INFO PRODUIT ===== */
.produit-categorie {
    color: #C8922A;
    text-transform: uppercase;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 1.4px;
    display: inline-block;
    background: rgba(200,146,42,0.08);
    padding: 5px 16px;
    border-radius: 20px;
    border: 1px solid rgba(200,146,42,0.2);
}
.produit-nom {
    font-family: 'Playfair Display', serif;
    font-size: 2.1rem;
    font-weight: 700;
    color: #0D0D0D;
    margin: 14px 0 18px;
    line-height: 1.2;
}
.produit-prix {
    font-family: 'Playfair Display', serif;
    font-size: 2.3rem;
    font-weight: 700;
    color: #C8922A;
    text-shadow: 0 0 20px rgba(200,146,42,0.15);
}
.produit-prix-ancien {
    font-size: 1.1rem;
    color: #8A99AA;
    text-decoration: line-through;
    margin-left: 12px;
    font-weight: 400;
}
.produit-stock {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 18px;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 600;
    margin-top: 12px;
}
.stock-disponible { 
    background: rgba(39,174,96,0.1); 
    color: #1A7A4A;
    border: 1px solid rgba(39,174,96,0.2);
}
.stock-rupture { 
    background: rgba(231,76,60,0.1); 
    color: #C0392B;
    border: 1px solid rgba(231,76,60,0.2);
}
.stock-faible { 
    background: rgba(243,156,18,0.1); 
    color: #B9770E;
    border: 1px solid rgba(243,156,18,0.2);
}

/* ===== OPTIONS ===== */
.produit-options {
    margin: 20px 0;
    padding: 20px 0;
    border-top: 1px solid #F0F2F5;
}
.produit-options .option-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1A2C3E;
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.produit-options .option-title i { 
    color: #C8922A; 
    font-size: 0.95rem;
}
.couleurs-list {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.couleur-item {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 3px solid #E0E0E0;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}
.couleur-item:hover { 
    transform: scale(1.1); 
    border-color: #C8922A; 
}
.couleur-item.active { 
    border-color: #C8922A; 
    box-shadow: 0 0 0 4px rgba(200,146,42,0.2);
    transform: scale(1.08);
}
.couleur-item.active::after {
    content: '✓';
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    text-shadow: 0 0 4px rgba(0,0,0,0.5);
}
.tailles-list {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.taille-item {
    padding: 10px 20px;
    border: 2px solid #E0E0E0;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
    min-width: 50px;
    text-align: center;
    color: #1A2C3E;
}
.taille-item:hover { 
    border-color: #C8922A; 
    background: rgba(200,146,42,0.05); 
}
.taille-item.active { 
    border-color: #0D0D0D; 
    background: #0D0D0D; 
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

/* ===== DESCRIPTION ===== */
.produit-description {
    margin: 22px 0;
    padding: 22px 0;
    border-top: 1px solid #F0F2F5;
    border-bottom: 1px solid #F0F2F5;
}
.produit-description h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.15rem;
    color: #0D0D0D;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.produit-description h3 i {
    color: #C8922A;
}
.produit-description p {
    color: #4A5568;
    font-size: 0.95rem;
    line-height: 1.75;
}

/* ===== MÉTA ===== */
.produit-meta {
    display: flex;
    gap: 20px;
    margin: 18px 0;
    flex-wrap: wrap;
    font-size: 0.82rem;
    color: #8A99AA;
}
.produit-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}
.produit-meta i { 
    color: #C8922A; 
}

/* ===== QUANTITÉ ===== */
.qte-group {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding: 14px 18px;
    background: #FAFBFC;
    border-radius: 12px;
    border: 1px solid #F0F2F5;
}
.qte-group label {
    font-weight: 600;
    font-size: 0.85rem;
    color: #1A2C3E;
    display: flex;
    align-items: center;
    gap: 6px;
}
.qte-group label i { color: #C8922A; }
.qte-input {
    width: 90px;
    padding: 11px 14px;
    text-align: center;
    border: 1.5px solid #E0E6ED;
    border-radius: 10px;
    font-size: 0.95rem;
    font-family: 'Jost', sans-serif;
    font-weight: 600;
    background: #fff;
}
.qte-input:focus { 
    outline: none; 
    border-color: #C8922A; 
    box-shadow: 0 0 0 3px rgba(200,146,42,0.1);
}

/* ===== ACTIONS ===== */
.produit-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 8px;
}
.btn-group-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.btn-ajouter {
    background: #0D0D0D;
    color: white;
    border: none;
    padding: 16px 22px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    text-decoration: none;
    font-family: 'Jost', sans-serif;
    flex: 1;
    min-width: 180px;
}
.btn-ajouter:hover {
    background: #1A1A1A;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.25);
}
.btn-ajouter:disabled { 
    background: #ccc; 
    cursor: not-allowed; 
    transform: none; 
    box-shadow: none; 
}
.btn-commander {
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    border: none;
    padding: 16px 22px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    text-decoration: none;
    font-family: 'Jost', sans-serif;
    flex: 1;
    min-width: 180px;
    box-shadow: 0 4px 15px rgba(200,146,42,0.3);
}
.btn-commander:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(200,146,42,0.45);
}
.btn-commander:disabled {
    background: #999;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* ============================================
   SECTION AVIS
   ============================================ */
.avis-section {
    margin-top: 50px;
    padding: 32px 0 0;
    border-top: 2px solid #F0F2F5;
}
.avis-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 8px;
}
.avis-section h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    color: #0D0D0D;
    display: flex;
    align-items: center;
    gap: 10px;
}
.avis-section h3 i {
    color: #C8922A;
}
.avis-resume {
    display: flex;
    align-items: center;
    gap: 22px;
    margin: 18px 0 22px;
    background: linear-gradient(135deg, #FAFBFC, #F7F4EF);
    padding: 18px 24px;
    border-radius: 12px;
    border: 1px solid rgba(200,146,42,0.12);
    flex-wrap: wrap;
}
.avis-resume .note-chiffre {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    font-weight: 700;
    color: #C8922A;
    line-height: 1;
}
.avis-resume .etoiles-avis {
    color: #F1C40F;
    font-size: 1.25rem;
    text-shadow: 0 0 8px rgba(241,196,15,0.3);
}
.avis-resume .nb-avis {
    color: #8A99AA;
    font-size: 0.9rem;
}

.btn-voir-avis {
    background: transparent;
    border: 2px solid #C8922A;
    color: #C8922A;
    padding: 10px 24px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 0.85rem;
    font-family: 'Jost', sans-serif;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-voir-avis:hover {
    background: #C8922A;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(200,146,42,0.3);
}
.btn-voir-avis.active {
    background: #C8922A;
    color: white;
}

.avis-list-container {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.6s ease, opacity 0.4s ease, margin 0.4s ease;
    opacity: 0;
    margin-top: 0;
}
.avis-list-container.open {
    max-height: 3000px;
    opacity: 1;
    margin-top: 20px;
}

.avis-card {
    background: #fff;
    border: 1px solid #F0F2F5;
    border-left: 3px solid #C8922A;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 12px;
    transition: all 0.2s;
}
.avis-card:hover {
    transform: translateX(4px);
    border-color: rgba(200,146,42,0.3);
    border-left-color: #9A6E1A;
}
.avis-card .avis-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.avis-card .avis-nom {
    font-weight: 600;
    color: #0D0D0D;
    font-size: 0.9rem;
}
.avis-card .avis-date {
    color: #999;
    font-size: 0.72rem;
}
.avis-card .avis-etoiles {
    color: #F1C40F;
    font-size: 0.85rem;
    margin: 6px 0;
}
.avis-card .avis-commentaire {
    margin-top: 6px;
    color: #5A6B7A;
    font-size: 0.9rem;
    line-height: 1.6;
}
.avis-card .avis-recommandation {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.72rem;
    color: #1A7A4A;
    background: rgba(39,174,96,0.1);
    padding: 3px 12px;
    border-radius: 12px;
    margin-top: 8px;
    font-weight: 600;
}
.avis-vide {
    color: #8A99AA;
    font-style: italic;
    padding: 24px 0;
    text-align: center;
    font-size: 0.88rem;
}
.btn-avis {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #fff;
    padding: 12px 26px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    margin-top: 14px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(200,146,42,0.25);
}
.btn-avis:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(200,146,42,0.4);
}
.avis-connectez-vous {
    font-size: 0.85rem;
    color: #8A99AA;
    margin-top: 16px;
    text-align: center;
}
.avis-connectez-vous a {
    color: #C8922A;
    text-decoration: none;
    font-weight: 600;
    border-bottom: 1px dashed #C8922A;
}
.avis-connectez-vous a:hover {
    color: #9A6E1A;
}

/* ============================================
   PRODUITS SIMILAIRES - GRILLE MASONRY COMPACTE
   ============================================ */
.similaires-section { 
    margin-top: 60px; 
}
.similaires-section .section-head {
    margin-bottom: 28px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 12px;
    padding-bottom: 14px;
    border-bottom: 1px solid #F0F2F5;
}
.similaires-section .section-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.55rem;
    font-weight: 500;
    color: #0D0D0D;
    letter-spacing: -0.01em;
    line-height: 1.2;
}
.similaires-section .section-title em {
    color: #C8922A;
    font-style: italic;
    font-weight: 600;
}
.similaires-section .section-link {
    font-size: 0.74rem;
    font-weight: 500;
    color: #9A6E1A;
    text-decoration: none;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    border-bottom: 1px solid transparent;
    padding-bottom: 2px;
}
.similaires-section .section-link:hover {
    color: #C8922A;
    border-bottom-color: #C8922A;
}

/* Grille masonry COMPACTE */
.similaires-grid {
    display: block;
    column-count: 2;
    column-gap: 14px;
}
@media (min-width: 700px) {
    .similaires-grid { column-count: 3; column-gap: 16px; }
}
@media (min-width: 1000px) {
    .similaires-grid { column-count: 4; column-gap: 18px; }
}
@media (min-width: 1300px) {
    .similaires-grid { column-count: 5; column-gap: 18px; }
}

/* Card produit COMPACTE */
.similaire-card {
    display: inline-block;
    width: 100%;
    text-decoration: none;
    position: relative;
    background: #fff;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 16px;
    break-inside: avoid;
    cursor: pointer;
    border: 1px solid transparent;
    transition: transform .4s cubic-bezier(.2,.7,.2,1), box-shadow .4s ease, border-color .3s;
}
.similaire-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(20,15,5,.10);
    border-color: #F4EFE6;
}

/* IMAGE COMPACTE : aspect-ratio 1/1 au lieu de 3/4 */
.similaire-image {
    position: relative;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    background: #F6F4EF;
}
.similaire-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.7s cubic-bezier(.2,.7,.2,1);
}
.similaire-card:hover .similaire-image img { 
    transform: scale(1.05); 
}

.similaire-badge-promo {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #C0392B;
    color: white;
    font-size: 0.5rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 2px;
    z-index: 10;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.similaire-badge-new {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #0D0D0D;
    color: #fff;
    font-size: 0.5rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 2px;
    z-index: 10;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* INFO COMPACTE */
.similaire-info {
    padding: 10px 12px 12px;
    text-align: left;
}
.similaire-name {
    font-family: 'Inter', sans-serif;
    font-size: 0.72rem;
    font-weight: 400;
    color: #14110B;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: .1px;
    transition: color .3s;
    margin-bottom: 6px;
}
.similaire-card:hover .similaire-name { 
    color: #9A6E1A; 
}

.similaire-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
}
.similaire-prices {
    display: flex;
    align-items: baseline;
    gap: 6px;
    flex-wrap: wrap;
}
.similaire-price-current {
    font-size: 0.8rem;
    font-weight: 600;
    color: #14110B;
    letter-spacing: -.01em;
}
.similaire-price-current.promo { 
    color: #C0392B; 
}
.similaire-price-old {
    font-size: 0.65rem;
    color: #8A857A;
    text-decoration: line-through;
    font-weight: 400;
}

/* ============================================
   LIGHTBOX
   ============================================ */
.image-lightbox-overlay {
    position: fixed; 
    inset: 0; 
    background: rgba(0,0,0,0.94);
    z-index: 10100; 
    opacity: 0; 
    visibility: hidden; 
    transition: opacity 0.3s ease;
    display: flex; 
    align-items: center; 
    justify-content: center; 
    padding: 20px;
}
.image-lightbox-overlay.show { 
    opacity: 1; 
    visibility: visible; 
}
.image-lightbox-overlay img {
    max-width: 100%; 
    max-height: 90vh; 
    object-fit: contain; 
    border-radius: 8px;
}
.image-lightbox-close {
    position: absolute; 
    top: 20px; 
    right: 20px;
    width: 44px; 
    height: 44px; 
    border-radius: 50%;
    background: rgba(255,255,255,0.12); 
    border: 1px solid rgba(255,255,255,0.25);
    color: #fff; 
    font-size: 1.2rem; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
}
.image-lightbox-close:hover {
    background: rgba(200,146,42,0.3);
    border-color: #C8922A;
    transform: rotate(90deg);
}

#couleur_input, #taille_input { display: none; }

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1024px) {
    .produit-grid { 
        gap: 35px; 
        padding: 30px; 
    }
}
@media (max-width: 900px) {
    .produit-grid { 
        grid-template-columns: 1fr; 
        gap: 30px; 
        padding: 25px; 
    }
    .produit-image { 
        min-height: 320px; 
    }
    .produit-nom { 
        font-size: 1.7rem; 
    }
    .produit-prix { 
        font-size: 1.9rem; 
    }
}
@media (max-width: 700px) {
    .similaires-grid { 
        column-count: 3; 
        column-gap: 12px; 
    }
    .similaire-card { 
        margin-bottom: 12px; 
    }
    .similaire-info { 
        padding: 8px 10px 10px; 
    }
    .similaire-name { 
        font-size: 0.68rem; 
    }
    .similaire-price-current { 
        font-size: 0.75rem; 
    }
}
@media (max-width: 600px) {
    .container-custom { 
        padding: 0 16px; 
    }
    .trust-bar { 
        justify-content: flex-start; 
        overflow-x: auto; 
        gap: 18px; 
        padding: 14px 18px;
        flex-wrap: nowrap;
    }
    .produit-grid { 
        padding: 20px 18px; 
        gap: 24px; 
    }
    .produit-image { 
        min-height: 260px; 
        padding: 20px; 
    }
    .produit-nom { 
        font-size: 1.45rem; 
    }
    .produit-prix { 
        font-size: 1.7rem; 
    }
    .btn-group-actions { 
        flex-direction: column; 
    }
    .btn-ajouter, 
    .btn-commander { 
        min-width: 100%; 
        flex: none; 
    }
    .produit-nav-arrow { 
        width: 36px; 
        height: 36px; 
        font-size: 1rem; 
    }
    .galerie-vignettes .vignette-item { 
        width: 60px; 
        height: 60px; 
    }
    .qte-group {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    .similaires-grid { 
        column-count: 2; 
        column-gap: 12px; 
    }
}
@media (max-width: 480px) {
    .galerie-vignettes .vignette-item { 
        width: 52px; 
        height: 52px; 
    }
    .produit-nom { 
        font-size: 1.3rem; 
    }
    .produit-prix { 
        font-size: 1.5rem; 
    }
    .similaires-grid { 
        column-count: 2; 
        column-gap: 10px; 
    }
}
</style>

<div class="produit-page">
    <div class="container-custom">

        
        <div class="produit-grid">
            <!-- IMAGE -->
            <div class="produit-image-wrap">
                <div class="produit-image" id="produitImageContainer">
                    <?php 
                    $image_principale = getProductImageDetail($produit['image_principale'] ?? '');
                    ?>
                    <img id="produitImagePrincipale" 
                         src="<?= $image_principale ?>" 
                         alt="<?= htmlspecialchars($produit['nom']) ?>" 
                         onclick="openImageLightbox(this.src)">

                    <?php if ($produit_precedent): ?>
                        <a href="produit.php?id=<?= $produit_precedent['id'] ?>" 
                           class="produit-nav-arrow prev" 
                           title="Produit précédent : <?= htmlspecialchars($produit_precedent['nom']) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($produit_suivant): ?>
                        <a href="produit.php?id=<?= $produit_suivant['id'] ?>" 
                           class="produit-nav-arrow next" 
                           title="Produit suivant : <?= htmlspecialchars($produit_suivant['nom']) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endif; ?>

                    <button type="button" 
                            class="btn-grand-cadre" 
                            onclick="openImageLightbox(document.getElementById('produitImagePrincipale').src)">
                        <i class="bi bi-arrows-fullscreen"></i> Grand cadre
                    </button>
                </div>

                <!-- GALERIE VIGNETTES -->
                <div class="galerie-vignettes" id="galerieVignettes">
                    <div class="vignette-item active" 
                         data-src="<?= $image_principale ?>" 
                         onclick="changerImagePrincipale(this, '<?= $image_principale ?>')">
                        <img src="<?= $image_principale ?>" alt="Image principale">
                    </div>
                    
                    <?php foreach($images_supplementaires as $img_path): 
                        $img_url = getProductImageDetail($img_path);
                    ?>
                        <div class="vignette-item" 
                             data-src="<?= $img_url ?>" 
                             onclick="changerImagePrincipale(this, '<?= $img_url ?>')">
                            <img src="<?= $img_url ?>" alt="Image supplémentaire">
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($produit_precedent || $produit_suivant): ?>
                <div class="produit-nav-hint">
                    <i class="bi bi-arrow-left-right"></i> Parcourez les autres produits de cette catégorie
                </div>
                <?php endif; ?>
            </div>

            <!-- INFO PRODUIT -->
            <div class="produit-info">
                <?php if($categorie): ?>
                    <span class="produit-categorie"><?= htmlspecialchars($categorie['nom']) ?></span>
                <?php endif; ?>
                
                <h1 class="produit-nom"><?= htmlspecialchars($produit['nom']) ?></h1>
                
                <div>
                    <span class="produit-prix"><?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA</span>
                    <?php if($prix_ancien): ?>
                        <span class="produit-prix-ancien"><?= number_format($prix_ancien, 0, ',', ' ') ?> FCFA</span>
                    <?php endif; ?>
                </div>

                <?php 
                $stock_class = 'stock-disponible';
                $stock_text = 'En stock';
                if($produit['stock'] <= 0) {
                    $stock_class = 'stock-rupture';
                    $stock_text = 'Rupture de stock';
                } elseif($produit['stock'] <= 5) {
                    $stock_class = 'stock-faible';
                    $stock_text = 'Plus que ' . $produit['stock'] . ' exemplaire(s)';
                }
                ?>
                <div class="produit-stock <?= $stock_class ?>">
                    <i class="bi bi-box-seam"></i> <?= $stock_text ?>
                </div>

                <!-- COULEURS -->
                <div class="produit-options">
                    <div class="option-title">
                        <i class="bi bi-palette"></i> 
                        Couleurs disponibles <?php if(!empty($couleurs_produit)): ?>(<?= count($couleurs_produit) ?>)<?php endif; ?>
                    </div>
                    <?php if(!empty($couleurs_produit)): ?>
                    <div class="couleurs-list">
                        <?php foreach($couleurs_produit as $c): ?>
                        <div class="couleur-item" 
                             style="background-color: <?= $c['code_hex'] ?>;" 
                             data-couleur-id="<?= $c['id'] ?>"
                             data-couleur-nom="<?= htmlspecialchars($c['nom']) ?>"
                             onclick="selectionnerCouleur(this)"
                             title="<?= htmlspecialchars($c['nom']) ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <small id="couleur_selectionnee" style="color:#8A99AA;font-size:0.75rem;margin-top:6px;display:block;">
                        Cliquez sur une couleur
                    </small>
                    <?php else: ?>
                    <p style="color:#999;font-size:0.8rem;">Aucune couleur disponible</p>
                    <?php endif; ?>
                </div>

                <!-- TAILLES -->
                <div class="produit-options">
                    <div class="option-title">
                        <i class="bi bi-rulers"></i> 
                        Tailles disponibles <?php if(!empty($tailles_produit)): ?>(<?= count($tailles_produit) ?>)<?php endif; ?>
                    </div>
                    <?php if(!empty($tailles_produit)): ?>
                    <div class="tailles-list">
                        <?php foreach($tailles_produit as $t): ?>
                        <div class="taille-item" 
                             data-taille-id="<?= $t['id'] ?>" 
                             data-taille-nom="<?= htmlspecialchars($t['nom']) ?>" 
                             onclick="selectionnerTaille(this)">
                            <?= htmlspecialchars($t['nom']) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <small id="taille_selectionnee" style="color:#8A99AA;font-size:0.75rem;margin-top:6px;display:block;">
                        Cliquez sur une taille
                    </small>
                    <?php else: ?>
                    <p style="color:#999;font-size:0.8rem;">Aucune taille disponible</p>
                    <?php endif; ?>
                </div>

                <!-- DESCRIPTION -->
                <div class="produit-description">
                    <h3><i class="bi bi-file-text"></i> Description</h3>
                    <p><?= nl2br(htmlspecialchars($produit['description'])) ?></p>
                </div>

                <!-- MÉTA -->
                <div class="produit-meta">
                    <span><i class="bi bi-tag"></i> Référence: #<?= $produit['id'] ?></span>
                    <span><i class="bi bi-calendar3"></i> Ajouté le <?= date('d/m/Y', strtotime($produit['created_at'])) ?></span>
                </div>

                <!-- ACTIONS -->
                <?php if($produit['stock'] > 0): ?>
                <div class="produit-actions">
                    <div class="qte-group">
                        <label><i class="bi bi-123"></i> Quantité :</label>
                        <input type="number" 
                               id="quantite_produit" 
                               class="qte-input" 
                               value="1" 
                               min="1" 
                               max="<?= $produit['stock'] ?>">
                    </div>
                    
                    <div class="btn-group-actions">
                        <form action="panier.php" method="POST" style="flex:1;min-width:180px;margin:0;">
                            <input type="hidden" name="produit_id" value="<?= $produit['id'] ?>">
                            <input type="hidden" name="quantite" id="quantite_ajouter" value="1">
                            <input type="hidden" name="couleur_id" id="couleur_input" value="">
                            <input type="hidden" name="taille_id" id="taille_input" value="">
                            <input type="hidden" name="action" value="ajouter">
                            <button type="submit" class="btn-ajouter" id="btnAjouterPanier" style="width:100%;">
                                <i class="bi bi-cart-plus"></i> Ajouter au panier
                            </button>
                        </form>
                        
                        <form action="commande.php" method="GET" style="flex:1;min-width:180px;margin:0;">
                            <input type="hidden" name="produit_id" value="<?= $produit['id'] ?>">
                            <input type="hidden" name="quantite" id="quantite_commander" value="1">
                            <input type="hidden" name="couleur_id" id="couleur_input_commander" value="">
                            <input type="hidden" name="taille_id" id="taille_input_commander" value="">
                            <button type="submit" class="btn-commander" id="btnCommander" style="width:100%;">
                                <i class="bi bi-lightning-fill"></i> Commander
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                    <button class="btn-ajouter" style="margin-top:20px;width:100%;" disabled>
                        <i class="bi bi-x-circle"></i> Indisponible
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- AVIS CLIENTS -->
        <!-- ========================================== -->
        <div class="avis-section">
            <div class="avis-header-row">
                <h3><i class="bi bi-star-fill"></i> Avis clients</h3>
                <button class="btn-voir-avis" id="btnVoirAvis" onclick="toggleAvis()">
                    <i class="bi bi-chevron-down" id="avisIcon"></i> 
                    <span id="avisBtnText">Voir les avis</span>
                </button>
            </div>

            <div class="avis-resume">
                <span class="note-chiffre"><?= number_format($stats_avis['moyenne'] ?? 0, 1) ?></span>
                <span class="etoiles-avis">
                    <?php 
                    $moyenne = round($stats_avis['moyenne'] ?? 0);
                    for($i=1; $i<=5; $i++): ?>
                        <i class="bi bi-star<?= $i <= $moyenne ? '-fill' : '' ?>"></i>
                    <?php endfor; ?>
                </span>
                <span class="nb-avis">(<?= $stats_avis['total'] ?? 0 ?> avis)</span>
            </div>

            <div class="avis-list-container" id="avisListContainer">
                <?php if(!empty($avis_produit)): ?>
                    <?php foreach($avis_produit as $avis): ?>
                    <div class="avis-card">
                        <div class="avis-header">
                            <span class="avis-nom"><?= htmlspecialchars($avis['nom_client'] ?? 'Anonyme') ?></span>
                            <span class="avis-date"><?= date('d/m/Y', strtotime($avis['created_at'])) ?></span>
                        </div>
                        <div class="avis-etoiles">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="bi bi-star<?= $i <= $avis['note'] ? '-fill' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="avis-commentaire"><?= htmlspecialchars($avis['commentaire'] ?? '') ?></p>
                        <?php if(!empty($avis['recommandation']) && $avis['recommandation'] == 1): ?>
                            <span class="avis-recommandation">
                                <i class="bi bi-hand-thumbs-up-fill"></i> Je recommande
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="avis-vide">Aucun avis pour ce produit pour le moment. Soyez le premier à donner votre avis !</p>
                <?php endif; ?>

                <?php if(isset($_SESSION['client_id'])): ?>
                    <a href="../client/ajouter_avis.php?produit_id=<?= $id ?>" class="btn-avis">
                        <i class="bi bi-pencil-square"></i> Laisser un avis
                    </a>
                <?php else: ?>
                    <p class="avis-connectez-vous">
                        <a href="../client/login.php">Connectez-vous</a> pour laisser un avis.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- PRODUITS SIMILAIRES - GRILLE MASONRY COMPACTE -->
        <!-- ============================================ -->
        <?php if(!empty($similaires)): ?>
        <div class="similaires-section">
            <div class="section-head">
                <h3 class="section-title">Vous aimerez <em>aussi</em></h3>
                <a href="catalogue.php" class="section-link">
                    Voir tout <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            
            <div class="similaires-grid">
                <?php foreach($similaires as $s): 
                    $img = getProductImageDetail($s['image_principale'] ?? '');
                    $est_promo = (isset($s['est_promo']) && $s['est_promo'] == 1 && 
                                  isset($s['prix_promo']) && $s['prix_promo'] > 0 && 
                                  $s['prix_promo'] < $s['prix']);
                    $sprix = $est_promo ? $s['prix_promo'] : $s['prix'];
                    $sprix_old = $est_promo ? $s['prix'] : null;
                    $pct_promo = 0;
                    if ($est_promo && $sprix_old > 0) {
                        $pct_promo = round((1 - $sprix / $sprix_old) * 100);
                    }
                    $est_nouveau = (!$est_promo && !empty($s['created_at']) && 
                                    strtotime($s['created_at']) >= strtotime('-14 days'));
                ?>
                <a href="produit.php?id=<?= $s['id'] ?>" class="similaire-card">
                    <div class="similaire-image">
                        <img src="<?= $img ?>" 
                             alt="<?= htmlspecialchars($s['nom']) ?>" 
                             loading="lazy" 
                             onerror="this.src='https://placehold.co/400x500/F5F5F5/C8922A?text=<?= urlencode($s['nom']) ?>'">
                        
                        <?php if ($est_promo): ?>
                            <div class="similaire-badge-promo">-<?= $pct_promo ?>%</div>
                        <?php elseif ($est_nouveau): ?>
                            <div class="similaire-badge-new">Nouveau</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="similaire-info">
                        <div class="similaire-name"><?= htmlspecialchars($s['nom']) ?></div>
                        <div class="similaire-footer">
                            <div class="similaire-prices">
                                <?php if ($est_promo): ?>
                                    <span class="similaire-price-old"><?= number_format($sprix_old, 0, ',', ' ') ?> F</span>
                                    <span class="similaire-price-current promo"><?= number_format($sprix, 0, ',', ' ') ?> F</span>
                                <?php else: ?>
                                    <span class="similaire-price-current"><?= number_format($sprix, 0, ',', ' ') ?> F</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- LIGHTBOX -->
<div class="image-lightbox-overlay" id="imageLightbox" onclick="closeImageLightbox(event)">
    <button class="image-lightbox-close" onclick="closeImageLightbox(event)">
        <i class="bi bi-x-lg"></i>
    </button>
    <img id="imageLightboxImg" src="" alt="Vue agrandie">
</div>

<script>
// ============================================
// GALERIE : changer l'image principale
// ============================================
function changerImagePrincipale(element, src) {
    document.getElementById('produitImagePrincipale').src = src;
    
    const btnGrandCadre = document.querySelector('.btn-grand-cadre');
    if (btnGrandCadre) {
        btnGrandCadre.setAttribute('onclick', "openImageLightbox('" + src + "')");
    }
    
    document.querySelectorAll('.vignette-item').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
}

// ============================================
// SÉLECTION COULEUR
// ============================================
function selectionnerCouleur(element) {
    document.querySelectorAll('.couleur-item').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
    
    const couleurId = element.dataset.couleurId;
    const couleurNom = element.dataset.couleurNom;
    
    document.getElementById('couleur_input').value = couleurId;
    document.getElementById('couleur_input_commander').value = couleurId;
    
    document.getElementById('couleur_selectionnee').textContent = 'Couleur sélectionnée : ' + couleurNom;
    verifierSelection();
}

// ============================================
// SÉLECTION TAILLE
// ============================================
function selectionnerTaille(element) {
    document.querySelectorAll('.taille-item').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
    
    const tailleId = element.dataset.tailleId;
    const tailleNom = element.dataset.tailleNom;
    
    document.getElementById('taille_input').value = tailleId;
    document.getElementById('taille_input_commander').value = tailleId;
    
    document.getElementById('taille_selectionnee').textContent = 'Taille sélectionnée : ' + tailleNom;
    verifierSelection();
}

// ============================================
// QUANTITÉ
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const qteInput = document.getElementById('quantite_produit');
    const qteAjouter = document.getElementById('quantite_ajouter');
    const qteCommander = document.getElementById('quantite_commander');
    
    if (qteInput) {
        qteInput.addEventListener('change', function() {
            const val = parseInt(this.value) || 1;
            qteAjouter.value = val;
            qteCommander.value = val;
        });
        
        qteInput.addEventListener('input', function() {
            const val = parseInt(this.value) || 1;
            qteAjouter.value = val;
            qteCommander.value = val;
        });
    }
});

// ============================================
// VÉRIFICATION SÉLECTION
// ============================================
function verifierSelection() {
    const couleur = document.getElementById('couleur_input').value;
    const taille = document.getElementById('taille_input').value;
    const btnPanier = document.getElementById('btnAjouterPanier');
    const btnCommander = document.getElementById('btnCommander');
    if (!btnPanier || !btnCommander) return;
    
    const couleurItems = document.querySelectorAll('.couleur-item');
    const tailleItems = document.querySelectorAll('.taille-item');
    
    let selectionOk = true;
    let message = '';
    
    if (couleurItems.length > 0 && !couleur) {
        selectionOk = false;
        message = 'Choisir couleur';
    }
    
    if (tailleItems.length > 0 && !taille) {
        selectionOk = false;
        message = message ? 'Couleur & taille' : 'Choisir taille';
    }
    
    if (selectionOk) {
        btnPanier.disabled = false;
        btnPanier.innerHTML = '<i class="bi bi-cart-plus"></i> Ajouter au panier';
        btnCommander.disabled = false;
        btnCommander.innerHTML = '<i class="bi bi-lightning-fill"></i> Commander';
    } else {
        btnPanier.disabled = true;
        btnPanier.innerHTML = '<i class="bi bi-exclamation-circle"></i> ' + message;
        btnCommander.disabled = true;
        btnCommander.innerHTML = '<i class="bi bi-exclamation-circle"></i> ' + message;
    }
}

// ============================================
// TOGGLE AVIS
// ============================================
function toggleAvis() {
    const container = document.getElementById('avisListContainer');
    const icon = document.getElementById('avisIcon');
    const btnText = document.getElementById('avisBtnText');
    const btn = document.getElementById('btnVoirAvis');
    
    container.classList.toggle('open');
    btn.classList.toggle('active');
    
    if (container.classList.contains('open')) {
        icon.className = 'bi bi-chevron-up';
        btnText.textContent = 'Masquer les avis';
    } else {
        icon.className = 'bi bi-chevron-down';
        btnText.textContent = 'Voir les avis';
    }
}

// ============================================
// LIGHTBOX
// ============================================
function openImageLightbox(src) {
    if (!src) return;
    document.getElementById('imageLightboxImg').src = src;
    document.getElementById('imageLightbox').classList.add('show');
}

function closeImageLightbox(event) {
    if (event) event.stopPropagation();
    document.getElementById('imageLightbox').classList.remove('show');
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeImageLightbox();
    <?php if ($produit_precedent): ?>
    if (event.key === 'ArrowLeft') window.location.href = 'produit.php?id=<?= $produit_precedent['id'] ?>';
    <?php endif; ?>
    <?php if ($produit_suivant): ?>
    if (event.key === 'ArrowRight') window.location.href = 'produit.php?id=<?= $produit_suivant['id'] ?>';
    <?php endif; ?>
});

// ============================================
// INIT
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    verifierSelection();
});
</script>

<?php require_once '../includes/footer.php'; ?>