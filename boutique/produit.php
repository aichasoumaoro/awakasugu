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
// AJOUT : PRODUIT PRÉCÉDENT / SUIVANT (mêmes catégorie)
// Pour naviguer avec des flèches sans repasser par le catalogue
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
// RÉCUPÉRER LES AVIS DU PRODUIT
// ============================================

// Vérifier si la colonne est_visible existe
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
// PRODUITS SIMILAIRES - MÊME CATÉGORIE + MÉLANGE
// ============================================
$similaires = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM produits 
        WHERE categorie_id = ? AND id != ? AND est_visible = 1 
        ORDER BY RAND() LIMIT 4
    ");
    $stmt->execute([$produit['categorie_id'], $id]);
    $similaires_categorie = $stmt->fetchAll();
    
    if (count($similaires_categorie) < 4) {
        $exclude_ids = array_merge([$id], array_column($similaires_categorie, 'id'));
        $placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT * FROM produits 
            WHERE est_visible = 1 AND id NOT IN ($placeholders)
            ORDER BY RAND() LIMIT " . (4 - count($similaires_categorie))
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

// Fonction pour l'image du produit
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

$titre_page = 'Détail produit - IBA Design';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
.produit-page { padding: 40px 0 60px; }
.container-custom { max-width: 1200px; margin: 0 auto; padding: 0 20px; }

/* ===== BANDEAU DE RÉASSURANCE (comme catalogue.php) ===== */
.trust-bar {
    display: flex;
    gap: 24px;
    justify-content: center;
    flex-wrap: wrap;
    background: #fff;
    border: 1px solid #F0EBE3;
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 24px;
}
.trust-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #444;
    white-space: nowrap;
}
.trust-item i { color: #C8922A; font-size: 1rem; }
@media (max-width: 700px) {
    .trust-bar { justify-content: flex-start; overflow-x: auto; gap: 18px; padding: 14px 16px; }
}

.produit-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 50px;
    background: white;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.06);
    border: 1px solid rgba(200,146,42,0.08);
}

/* ===== BLOC IMAGE : petit cadre + bouton grand cadre + flèches produit ===== */
.produit-image-wrap { position: relative; }
.produit-image {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #F8F9FA;
    border-radius: 16px;
    padding: 30px;
    min-height: 380px;
    position: relative;
    overflow: hidden;
}
.produit-image img { max-width: 100%; max-height: 420px; object-fit: contain; transition: transform 0.3s; cursor: zoom-in; }
.produit-image img:hover { transform: scale(1.02); }

.btn-grand-cadre {
    position: absolute;
    bottom: 16px;
    right: 16px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(13,13,13,0.75);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    backdrop-filter: blur(4px);
    z-index: 5;
}
.btn-grand-cadre:hover { background: rgba(0,0,0,0.9); }

/* Flèches pour naviguer entre les produits de la catégorie */
.produit-nav-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: rgba(255,255,255,0.92);
    border: 1px solid #EFEFEF;
    color: #1A1A1A;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-decoration: none;
    z-index: 6;
    transition: all 0.25s ease;
}
.produit-nav-arrow:hover { background: #C8922A; color: #fff; }
.produit-nav-arrow.prev { left: 10px; }
.produit-nav-arrow.next { right: 10px; }
.produit-nav-hint {
    text-align: center;
    font-size: 0.72rem;
    color: #999;
    margin-top: 10px;
}

.produit-categorie {
    color: #C8922A;
    text-transform: uppercase;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 1px;
    display: inline-block;
    background: rgba(200,146,42,0.08);
    padding: 4px 14px;
    border-radius: 20px;
}
.produit-nom {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    font-weight: 700;
    color: #0D0D0D;
    margin: 10px 0 15px;
}
.produit-prix {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    font-weight: 700;
    color: #C8922A;
}
.produit-prix-ancien {
    font-size: 1.1rem;
    color: #8A99AA;
    text-decoration: line-through;
    margin-left: 12px;
}
.produit-stock {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-top: 10px;
}
.stock-disponible { background: #D4EDDA; color: #155724; }
.stock-rupture { background: #F8D7DA; color: #721C24; }
.stock-faible { background: #FFF3CD; color: #856404; }

.produit-options {
    margin: 15px 0;
    padding: 15px 0;
    border-top: 1px solid #F0F2F5;
}
.produit-options .option-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #0D0D0D;
    margin-bottom: 8px;
}
.produit-options .option-title i { color: #C8922A; margin-right: 6px; }
.couleurs-list {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.couleur-item {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 3px solid #E0E0E0;
    cursor: pointer;
    transition: all 0.3s;
}
.couleur-item:hover { transform: scale(1.1); border-color: #C8922A; }
.couleur-item.active { border-color: #C8922A; box-shadow: 0 0 0 3px rgba(200,146,42,0.2); }
.tailles-list {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.taille-item {
    padding: 8px 16px;
    border: 2px solid #E0E0E0;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
    min-width: 46px;
    text-align: center;
}
.taille-item:hover { border-color: #C8922A; background: rgba(200,146,42,0.05); }
.taille-item.active { border-color: #1A1A1A; background: #1A1A1A; color: white; }

.produit-description {
    margin: 20px 0;
    padding: 20px 0;
    border-top: 1px solid #F0F2F5;
    border-bottom: 1px solid #F0F2F5;
}
.produit-description h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    color: #0D0D0D;
    margin-bottom: 10px;
}
.produit-description p {
    color: #4A5568;
    font-size: 0.95rem;
    line-height: 1.7;
}
.qte-input {
    width: 80px;
    padding: 10px;
    text-align: center;
    border: 1.5px solid #E0E6ED;
    border-radius: 10px;
    font-size: 0.95rem;
    font-family: 'Jost', sans-serif;
}
.qte-input:focus { outline: none; border-color: #C8922A; }

.produit-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 20px;
}

.produit-actions .btn-group-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.produit-actions .btn-ajouter {
    flex: 1;
    min-width: 160px;
}

.btn-ajouter {
    background: #1A1A1A;
    color: white;
    border: none;
    padding: 15px 20px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    text-decoration: none;
}
.btn-ajouter:hover {
    background: #333;
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
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
    padding: 15px 20px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    text-decoration: none;
    flex: 1;
    min-width: 160px;
}
.btn-commander:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
}
.btn-commander:disabled {
    background: #999;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* ============================================
   PRODUITS SIMILAIRES - petites cartes cliquables
   ============================================ */
.similaires { margin-top: 60px; }
.similaires h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    color: #0D0D0D;
    margin-bottom: 25px;
}
.similaires-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
.similaire-card {
    background: white;
    border-radius: 16px;
    padding: 15px;
    text-align: center;
    text-decoration: none;
    color: inherit;
    border: 1px solid #F0F2F5;
    transition: all 0.3s;
}
.similaire-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    border-color: #C8922A;
}
.similaire-card img { width: 100%; height: 180px; object-fit: cover; border-radius: 10px; }
.similaire-card h5 { font-size: 0.9rem; font-weight: 600; margin: 10px 0 5px; color: #0D0D0D; }
.similaire-card .similaire-prix { color: #C8922A; font-weight: 700; font-size: 0.9rem; }
.produit-meta {
    display: flex;
    gap: 20px;
    margin-top: 15px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: #8A99AA;
}
.produit-meta i { color: #C8922A; }

/* ========================================== */
/* STYLES POUR LA SECTION AVIS CLIENTS        */
/* ========================================== */
.avis-section {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 2px solid #E8ECF0;
}
.avis-section h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem;
    color: #0D0D0D;
}
.avis-resume {
    display: flex;
    align-items: center;
    gap: 20px;
    margin: 15px 0 20px 0;
    background: #F8F9FA;
    padding: 15px 20px;
    border-radius: 10px;
    flex-wrap: wrap;
}
.avis-resume .note-chiffre {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    font-weight: 700;
    color: #C8922A;
}
.avis-resume .etoiles-avis {
    color: #F1C40F;
    font-size: 1.2rem;
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
    font-size: 0.9rem;
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
    max-height: 2000px;
    opacity: 1;
    margin-top: 20px;
}

.avis-card {
    background: #fff;
    border: 1px solid #E8ECF0;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 12px;
    transition: transform 0.2s;
}
.avis-card:hover {
    transform: translateX(5px);
    border-color: rgba(200,146,42,0.3);
}
.avis-card .avis-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}
.avis-card .avis-nom {
    font-weight: 600;
    color: #0D0D0D;
}
.avis-card .avis-date {
    color: #999;
    font-size: 0.75rem;
}
.avis-card .avis-etoiles {
    color: #F1C40F;
    font-size: 0.85rem;
    margin: 5px 0;
}
.avis-card .avis-commentaire {
    margin-top: 5px;
    color: #5A6B7A;
    font-size: 0.9rem;
    line-height: 1.5;
}
.avis-card .avis-recommandation {
    display: inline-block;
    font-size: 0.75rem;
    color: #27AE60;
    background: #D4EDDA;
    padding: 2px 12px;
    border-radius: 12px;
    margin-top: 6px;
}
.avis-vide {
    color: #999;
    font-style: italic;
    padding: 20px 0;
}
.btn-avis {
    display: inline-block;
    background: #C8922A;
    color: #fff;
    padding: 10px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    margin-top: 10px;
    transition: background 0.3s;
    border: none;
    cursor: pointer;
}
.btn-avis:hover {
    background: #9A6E1A;
    color: #fff;
}
.avis-connectez-vous {
    font-size: 0.85rem;
    color: #8A99AA;
    margin-top: 15px;
}
.avis-connectez-vous a {
    color: #C8922A;
    text-decoration: underline;
}

/* ===== LIGHTBOX "GRAND CADRE" ===== */
.image-lightbox-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.92);
    z-index: 10100; opacity: 0; visibility: hidden; transition: opacity 0.3s ease;
    display: flex; align-items: center; justify-content: center; padding: 20px;
}
.image-lightbox-overlay.show { opacity: 1; visibility: visible; }
.image-lightbox-overlay img {
    max-width: 100%; max-height: 90vh; object-fit: contain; border-radius: 8px;
}
.image-lightbox-close {
    position: absolute; top: 18px; right: 18px;
    width: 40px; height: 40px; border-radius: 50%;
    background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25);
    color: #fff; font-size: 1.2rem; display: flex; align-items: center; justify-content: center;
    cursor: pointer;
}

#couleur_input, #taille_input { display: none; }

@media (max-width: 900px) {
    .produit-grid { grid-template-columns: 1fr; gap: 30px; padding: 25px; }
    .produit-image { min-height: 250px; }
}
@media (max-width: 768px) {
    .similaires-grid { grid-template-columns: repeat(2, 1fr); }
    .produit-nom { font-size: 1.5rem; }
    .produit-prix { font-size: 1.8rem; }
    .produit-actions .btn-group-actions {
        flex-direction: column;
    }
    .produit-actions .btn-ajouter,
    .btn-commander {
        min-width: 100%;
        flex: none;
    }
    .produit-nav-arrow { width: 36px; height: 36px; font-size: 0.95rem; }
}
@media (max-width: 500px) {
    .similaires-grid { grid-template-columns: 1fr; }
}
</style>

<div class="produit-page">
    <div class="container-custom">

        <!-- ===== BANDEAU DE RÉASSURANCE ===== -->
        <div class="trust-bar">
            <div class="trust-item"><i class="bi bi-truck"></i> Livraison à Bamako</div>
            <div class="trust-item"><i class="bi bi-shield-check"></i> Paiement sécurisé</div>
            <div class="trust-item"><i class="bi bi-arrow-repeat"></i> Retour facile</div>
        </div>

        <div class="produit-grid">
            <div class="produit-image-wrap">
                <div class="produit-image">
                    <?php 
                    $image = getProductImageDetail($produit['image_principale'] ?? '');
                    ?>
                    <img src="<?= $image ?>" alt="<?= htmlspecialchars($produit['nom']) ?>" onclick="openImageLightbox('<?= $image ?>')">

                    <?php if ($produit_precedent): ?>
                        <a href="produit.php?id=<?= $produit_precedent['id'] ?>" class="produit-nav-arrow prev" title="Produit précédent : <?= htmlspecialchars($produit_precedent['nom']) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($produit_suivant): ?>
                        <a href="produit.php?id=<?= $produit_suivant['id'] ?>" class="produit-nav-arrow next" title="Produit suivant : <?= htmlspecialchars($produit_suivant['nom']) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endif; ?>

                    <button type="button" class="btn-grand-cadre" onclick="openImageLightbox('<?= $image ?>')">
                        <i class="bi bi-arrows-fullscreen"></i> Grand cadre
                    </button>
                </div>
                <?php if ($produit_precedent || $produit_suivant): ?>
                <div class="produit-nav-hint">
                    <i class="bi bi-arrow-left-right"></i> Parcourez les autres produits de cette catégorie
                </div>
                <?php endif; ?>
            </div>

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

                <!-- COULEURS DISPONIBLES -->
                <div class="produit-options">
                    <div class="option-title"><i class="bi bi-palette"></i> Couleurs disponibles <?php if(!empty($couleurs_produit)): ?>(<?= count($couleurs_produit) ?>)<?php endif; ?></div>
                    <?php if(!empty($couleurs_produit)): ?>
                    <div class="couleurs-list">
                        <?php foreach($couleurs_produit as $c): ?>
                        <div class="couleur-item" 
                             style="background-color: <?= $c['code_hex'] ?>; border-color: <?= $c['code_hex'] == '#FFFFFF' ? '#ccc' : '#E0E0E0' ?>;"
                             data-couleur-id="<?= $c['id'] ?>"
                             data-couleur-nom="<?= htmlspecialchars($c['nom']) ?>"
                             onclick="selectionnerCouleur(this)"
                             title="<?= htmlspecialchars($c['nom']) ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <small id="couleur_selectionnee" style="color:#8A99AA;font-size:0.75rem;">Cliquez sur une couleur</small>
                    <?php else: ?>
                    <p style="color:#999;font-size:0.8rem;">Aucune couleur disponible pour ce produit</p>
                    <?php endif; ?>
                </div>

                <!-- TAILLES DISPONIBLES -->
                <div class="produit-options">
                    <div class="option-title"><i class="bi bi-rulers"></i> Tailles disponibles <?php if(!empty($tailles_produit)): ?>(<?= count($tailles_produit) ?>)<?php endif; ?></div>
                    <?php if(!empty($tailles_produit)): ?>
                    <div class="tailles-list">
                        <?php foreach($tailles_produit as $t): ?>
                        <div class="taille-item" data-taille-id="<?= $t['id'] ?>" data-taille-nom="<?= htmlspecialchars($t['nom']) ?>" onclick="selectionnerTaille(this)">
                            <?= htmlspecialchars($t['nom']) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <small id="taille_selectionnee" style="color:#8A99AA;font-size:0.75rem;">Cliquez sur une taille</small>
                    <?php else: ?>
                    <p style="color:#999;font-size:0.8rem;">Aucune taille disponible pour ce produit</p>
                    <?php endif; ?>
                </div>

                <div class="produit-description">
                    <h3>📝 Description</h3>
                    <p><?= nl2br(htmlspecialchars($produit['description'])) ?></p>
                </div>

                <div class="produit-meta">
                    <span><i class="bi bi-tag"></i> Référence: #<?= $produit['id'] ?></span>
                    <span><i class="bi bi-calendar3"></i> Ajouté le <?= date('d/m/Y', strtotime($produit['created_at'])) ?></span>
                </div>

                <?php if($produit['stock'] > 0): ?>
                <div class="produit-actions">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <label class="fw-bold" style="font-size:0.9rem;">Quantité :</label>
                        <input type="number" id="quantite_produit" class="qte-input" value="1" min="1" max="<?= $produit['stock'] ?>">
                    </div>
                    
                    <div class="btn-group-actions">
                        <form action="panier.php" method="POST" style="flex:1;min-width:160px;margin:0;">
                            <input type="hidden" name="produit_id" value="<?= $produit['id'] ?>">
                            <input type="hidden" name="quantite" id="quantite_ajouter" value="1">
                            <input type="hidden" name="couleur_id" id="couleur_input" value="">
                            <input type="hidden" name="taille_id" id="taille_input" value="">
                            <input type="hidden" name="action" value="ajouter">
                            <button type="submit" class="btn-ajouter" id="btnAjouterPanier" style="width:100%;">
                                <i class="bi bi-cart-plus"></i> Ajouter au panier
                            </button>
                        </form>
                        
                        <form action="commande.php" method="GET" style="flex:1;min-width:160px;margin:0;">
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
                    <button class="btn-ajouter" style="margin-top:20px; width:100%;" disabled>
                        <i class="bi bi-x-circle"></i> Indisponible
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECTION AVIS CLIENTS -->
        <!-- ========================================== -->
        <div class="avis-section">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
                <h3>⭐ Avis clients</h3>
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
                            <span class="avis-recommandation">👍 Je recommande</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="avis-vide">Aucun avis pour ce produit pour le moment. Soyez le premier à donner votre avis !</p>
                <?php endif; ?>

                <?php if(isset($_SESSION['client_id'])): ?>
                    <a href="../client/ajouter_avis.php?produit_id=<?= $id ?>" class="btn-avis">
                        ✏️ Laisser un avis
                    </a>
                <?php else: ?>
                    <p class="avis-connectez-vous">
                        <a href="../client/login.php">Connectez-vous</a> pour laisser un avis.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================
             PRODUITS SIMILAIRES - petites cartes cliquables
             ============================================ -->
        <?php if(!empty($similaires)): ?>
        <div class="similaires">
            <h3>✨ Produits similaires</h3>
            <div class="similaires-grid">
                <?php foreach($similaires as $s): 
                    $img = getProductImageDetail($s['image_principale'] ?? '');
                    $sprix = $s['prix'];
                    if (isset($s['est_promo']) && $s['est_promo'] == 1 && isset($s['prix_promo']) && $s['prix_promo'] > 0 && $s['prix_promo'] < $s['prix']) {
                        $sprix = $s['prix_promo'];
                    }
                ?>
                <a href="produit.php?id=<?= $s['id'] ?>" class="similaire-card">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($s['nom']) ?>">
                    <h5><?= htmlspecialchars($s['nom']) ?></h5>
                    <div class="similaire-prix"><?= number_format($sprix, 0, ',', ' ') ?> FCFA</div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================
     LIGHTBOX "GRAND CADRE" (photo en plein écran)
     ============================================ -->
<div class="image-lightbox-overlay" id="imageLightbox" onclick="closeImageLightbox(event)">
    <button class="image-lightbox-close" onclick="closeImageLightbox(event)"><i class="bi bi-x-lg"></i></button>
    <img id="imageLightboxImg" src="" alt="Vue agrandie">
</div>

<script>
// Sélection de la couleur
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

// Sélection de la taille
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

// Mise à jour de la quantité
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

// ==========================================
// FONCTION POUR AFFICHER/MASQUER LES AVIS
// ==========================================
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

// ==========================================
// LIGHTBOX "GRAND CADRE"
// ==========================================
function openImageLightbox(src) {
    document.getElementById('imageLightboxImg').src = src;
    document.getElementById('imageLightbox').classList.add('show');
}
function closeImageLightbox(event) {
    if (event) event.stopPropagation();
    document.getElementById('imageLightbox').classList.remove('show');
}
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeImageLightbox();
    // Navigation clavier entre produits (gauche/droite)
    <?php if ($produit_precedent): ?>
    if (event.key === 'ArrowLeft') window.location.href = 'produit.php?id=<?= $produit_precedent['id'] ?>';
    <?php endif; ?>
    <?php if ($produit_suivant): ?>
    if (event.key === 'ArrowRight') window.location.href = 'produit.php?id=<?= $produit_suivant['id'] ?>';
    <?php endif; ?>
});

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    verifierSelection();
});
</script>

<?php require_once '../includes/footer.php'; ?>