<?php
// ============================================
// NOUVEAUTÉS - Awa Ka Sugu
// Version Ultra Moderne - Format Mini
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

$titre_page = 'Nouveautés - IBA Design';
$meta_desc = 'Découvrez les dernières nouveautés de la collection IBA Design.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// ============================================
// RÉCUPÉRATION DES PRODUITS MARQUÉS "NOUVEAU"
// ============================================
$countStmt = $pdo->query("
    SELECT COUNT(*) as total FROM produits 
    WHERE est_visible = 1 AND est_nouveau = 1
");
$totalProduits = $countStmt->fetchColumn();
$totalPages = ceil($totalProduits / $limit);

// Récupérer les produits avec pagination (uniquement est_nouveau = 1)
$stmt = $pdo->prepare("
    SELECT * FROM produits 
    WHERE est_visible = 1 AND est_nouveau = 1
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute();
$produits = $stmt->fetchAll();

// ============================================
// RÉCUPÉRATION DES PRODUITS "À DÉCOUVRIR"
// (produits récents qui ne sont PAS marqués comme nouveaux)
// ============================================
$recentStmt = $pdo->query("
    SELECT * FROM produits 
    WHERE est_visible = 1 AND est_nouveau = 0
    ORDER BY created_at DESC 
    LIMIT 4
");
$produitsRecents = $recentStmt->fetchAll();

// ============================================
// FONCTION POUR L'IMAGE - AVEC TOUS LES DOSSIERS
// ============================================
function getImageUrl($image) {
    if (empty($image)) {
        return 'https://placehold.co/400x500/F5F5F5/C8922A?text=Produit';
    }
    
    $image = trim($image);
    $image_name = pathinfo($image, PATHINFO_FILENAME);
    $extension = pathinfo($image, PATHINFO_EXTENSION);
    
    // Tous les dossiers possibles
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
        '../uploads/',
        'uploads/',
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
    
    return 'https://placehold.co/400x500/F5F5F5/C8922A?text=' . urlencode($image_name);
}

// ============================================
// FONCTION AJOUT PANIER (AJAX)
// ============================================
function getPanierCount() {
    $count = 0;
    if (isset($_SESSION['panier'])) {
        foreach ($_SESSION['panier'] as $item) {
            $count += $item['quantite'];
        }
    }
    return $count;
}
$panier_count = getPanierCount();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveautés - IBA Design</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           ULTRA MODERN - MINI FORMAT
           ============================================ */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #F7F6F3;
            color: #1A1A1A;
            overflow-x: hidden;
        }
        
        ::-webkit-scrollbar {
            width: 4px;
            background: #F7F6F3;
        }
        ::-webkit-scrollbar-thumb {
            background: #C8922A;
            border-radius: 10px;
        }
        
        /* ========== HERO ULTRA MINIMAL ========== */
        .hero-ultra {
            padding: 50px 20px 30px;
            text-align: center;
            background: #FFFFFF;
            border-bottom: 1px solid #EEEAE5;
        }
        
        .hero-ultra .badge {
            display: inline-block;
            background: rgba(200,146,42,0.06);
            color: #C8922A;
            font-size: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 3px;
            padding: 4px 16px;
            border-radius: 20px;
            margin-bottom: 10px;
        }
        
        .hero-ultra h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: #0D0D0D;
            letter-spacing: -0.5px;
        }
        
        .hero-ultra h1 span {
            color: #C8922A;
        }
        
        .hero-ultra .sub {
            font-size: 0.6rem;
            color: #B0B0B0;
            letter-spacing: 5px;
            text-transform: uppercase;
            font-weight: 300;
            margin-top: 4px;
        }
        
        .hero-ultra .deco {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin: 12px auto 14px;
        }
        
        .hero-ultra .deco .line {
            width: 30px;
            height: 1px;
            background: #C8922A;
        }
        
        .hero-ultra .deco .dot {
            width: 4px;
            height: 4px;
            background: #C8922A;
            border-radius: 50%;
        }
        
        .hero-ultra p {
            color: #8A99AA;
            font-size: 0.8rem;
            max-width: 400px;
            margin: 0 auto 16px;
            line-height: 1.5;
        }
        
        .hero-ultra .stats {
            display: flex;
            justify-content: center;
            gap: 30px;
        }
        
        .hero-ultra .stats .stat .number {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: #C8922A;
            display: block;
        }
        
        .hero-ultra .stats .stat .label {
            font-size: 0.5rem;
            color: #B0B0B0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* ========== CONTAINER ========== */
        .container-custom {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 16px;
        }
        
        /* ========== SECTION HEADER ========== */
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: #0D0D0D;
        }
        
        .section-title em {
            color: #C8922A;
            font-style: italic;
        }
        
        .section-title small {
            display: block;
            font-size: 0.65rem;
            font-weight: 400;
            color: #8A99AA;
            font-family: 'Inter', sans-serif;
        }
        
        .view-all {
            font-size: 0.65rem;
            font-weight: 500;
            color: #C8922A;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
        }
        
        .view-all:hover {
            color: #9A6E1A;
        }
        
        /* ========== GRILLE PRODUITS - FORMAT MINI ========== */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }
        
        .product-card {
            background: #FFFFFF;
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            transition: all 0.35s ease;
            border: 1px solid #EEEAE5;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            border-color: rgba(200,146,42,0.12);
        }
        
        /* ===== IMAGE ===== */
        .product-card .image-wrapper {
            position: relative;
            aspect-ratio: 3/4;
            overflow: hidden;
            background: #F8F6F4;
        }
        
        .product-card .image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .product-card:hover .image-wrapper img {
            transform: scale(1.03);
        }
        
        /* ===== BADGES MINI ===== */
        .badge-new {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #C8922A;
            color: #FFFFFF;
            font-size: 0.4rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 2;
        }
        
        .badge-coup {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            color: white;
            font-size: 0.35rem;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 16px;
            text-transform: uppercase;
            z-index: 2;
        }
        
        /* ===== OVERLAY ===== */
        .product-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 30px 12px 12px;
            background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, rgba(0,0,0,0) 100%);
            opacity: 0;
            transform: translateY(6px);
            transition: all 0.3s ease;
            display: flex;
            gap: 6px;
            justify-content: center;
        }
        
        .product-card:hover .product-overlay {
            opacity: 1;
            transform: translateY(0);
        }
        
        .btn-overlay {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: none;
            cursor: pointer;
        }
        
        .btn-view {
            background: rgba(255,255,255,0.95);
            color: #1A1A1A;
        }
        
        .btn-view:hover {
            background: #C8922A;
            color: #FFFFFF;
        }
        
        .btn-buy {
            background: #C8922A;
            color: #FFFFFF;
        }
        
        .btn-buy:hover {
            background: #9A6E1A;
        }
        
        /* ===== INFOS - COMPACT ===== */
        .product-info {
            padding: 10px 12px 14px;
            text-align: center;
            background: #FFFFFF;
        }
        
        .product-info .category {
            font-size: 0.4rem;
            color: #C8922A;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
            display: inline-block;
        }
        
        .product-info .name {
            font-family: 'Playfair Display', serif;
            font-size: 0.75rem;
            font-weight: 600;
            color: #1A1A1A;
            margin: 2px 0;
            transition: color 0.3s ease;
        }
        
        .product-card:hover .product-info .name {
            color: #C8922A;
        }
        
        .product-info .divider {
            width: 16px;
            height: 1.5px;
            background: #C8922A;
            margin: 3px auto 5px;
            border-radius: 2px;
            opacity: 0.2;
            transition: all 0.3s ease;
        }
        
        .product-card:hover .product-info .divider {
            width: 24px;
            opacity: 1;
        }
        
        .product-info .price {
            font-size: 0.8rem;
            font-weight: 700;
            color: #C8922A;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        
        .product-info .price .old {
            font-size: 0.55rem;
            color: #B0B0B0;
            text-decoration: line-through;
            font-weight: 400;
        }
        
        .product-info .price .promo {
            display: inline-block;
            background: #E74C3C;
            color: white;
            font-size: 0.35rem;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 16px;
        }
        
        /* ========== PAGINATION ========== */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin: 20px 0 40px;
        }
        
        .pagination {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 10px;
            border-radius: 50%;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 500;
            color: #888;
            background: white;
            border: 1px solid #EEEAE5;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: #C8922A;
            color: white;
            border-color: #C8922A;
        }
        
        .pagination .active {
            background: #C8922A;
            color: white;
            border-color: #C8922A;
        }
        
        .pagination .disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        /* ========== SECTION À DÉCOUVRIR ========== */
        .section-recentes {
            background: #FFFFFF;
            padding: 30px 0 40px;
            border-top: 1px solid #EEEAE5;
        }
        
        .section-recentes .products-grid {
            margin-bottom: 0;
        }
        
        /* ========== EMPTY ========== */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 12px;
            border: 1px solid #EEEAE5;
        }
        
        .empty-state .empty-icon {
            font-size: 2rem;
            color: #DDD;
            margin-bottom: 8px;
            display: block;
        }
        
        .empty-state h3 {
            font-size: 1rem;
            color: #1A1A1A;
            margin-bottom: 4px;
        }
        
        .empty-state p {
            color: #8A99AA;
            font-size: 0.8rem;
            margin-bottom: 12px;
        }
        
        .empty-state .btn-empty {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #C8922A;
            color: white;
            padding: 6px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.7rem;
            transition: all 0.3s ease;
        }
        
        .empty-state .btn-empty:hover {
            background: #9A6E1A;
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 1100px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 14px;
            }
        }
        
        @media (max-width: 900px) {
            .hero-ultra {
                padding: 40px 20px 25px;
            }
            .hero-ultra h1 {
                font-size: 1.8rem;
            }
            .hero-ultra .stats {
                gap: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .section-title {
                font-size: 1rem;
            }
            .section-head {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }
            .hero-ultra .sub {
                font-size: 0.5rem;
                letter-spacing: 3px;
            }
        }
        
        @media (max-width: 480px) {
            .hero-ultra h1 {
                font-size: 1.5rem;
            }
            .hero-ultra .badge {
                font-size: 0.45rem;
                padding: 3px 12px;
            }
            .hero-ultra .stats {
                gap: 12px;
            }
            .hero-ultra .stats .stat .number {
                font-size: 1rem;
            }
            .hero-ultra .stats .stat .label {
                font-size: 0.45rem;
            }
            .products-grid {
                grid-template-columns: 1fr;
                max-width: 280px;
                margin: 0 auto 25px;
            }
            .pagination a, .pagination span {
                min-width: 28px;
                height: 28px;
                font-size: 0.65rem;
            }
            .section-title {
                font-size: 0.9rem;
            }
            .section-title small {
                font-size: 0.55rem;
            }
        }
    </style>
</head>
<body>

<!-- ========== HERO ULTRA MINIMAL ========== -->
<section class="hero-ultra">
    <div class="badge">✦ 2026</div>
    <h1>Nouvelles <span>Créations</span></h1>
    <div class="sub">✦ Dernières tendances ✦</div>
    <div class="deco">
        <span class="line"></span>
        <span class="dot"></span>
        <span class="line"></span>
    </div>
    <p>Les pièces les plus récentes de la collection IBA Design.</p>
    <div class="stats">
        <div class="stat">
            <span class="number"><?= $totalProduits ?></span>
            <span class="label">Nouveautés</span>
        </div>
        <div class="stat">
            <span class="number">✦</span>
            <span class="label">Collection</span>
        </div>
        <div class="stat">
            <span class="number"><?= date('Y') ?></span>
            <span class="label">Année</span>
        </div>
    </div>
</section>

<!-- ========== CONTENU PRINCIPAL ========== -->
<div class="container-custom">
    <div class="section-head">
        <div>
            <div class="section-title">
                Toutes les nouveautés
                <small>Les dernières pièces marquées comme nouvelles</small>
            </div>
        </div>
        <a href="catalogue.php" class="view-all">
            Voir catalogue <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <?php if(empty($produits)): ?>
        <div class="empty-state">
            <i class="bi bi-box-seam empty-icon"></i>
            <h3>Aucune nouveauté</h3>
            <p>Aucun produit n'est actuellement marqué comme nouveau.</p>
            <a href="catalogue.php" class="btn-empty">
                <i class="bi bi-grid"></i> Voir le catalogue
            </a>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach($produits as $index => $p): 
                $img = getImageUrl($p['image_principale'] ?? '');
                $est_promo = !empty($p['prix_promo']) && $p['prix_promo'] > 0 && $p['prix_promo'] < $p['prix'];
                $prix_affiché = $est_promo ? $p['prix_promo'] : $p['prix'];
                $prix_ancien = $est_promo ? $p['prix'] : null;
            ?>
            <div class="product-card" data-product-id="<?= $p['id'] ?>">
                <div class="image-wrapper">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>" loading="lazy" onerror="this.src='https://placehold.co/400x500/F5F5F5/C8922A?text=<?= urlencode($p['nom'])?>'">
                    
                    <div class="badge-new">★ Nouveau</div>
                    <?php if($index % 3 == 0 && $index > 0): ?>
                    <div class="badge-coup">♥ Coup de cœur</div>
                    <?php endif; ?>
                    
                    <div class="product-overlay">
                        <a href="produit.php?id=<?= $p['id'] ?>" class="btn-overlay btn-view">
                            <i class="bi bi-eye"></i> Détail
                        </a>
                        <button class="btn-overlay btn-buy" onclick="ajouterAuPanier(event, <?= $p['id'] ?>, 1)">
                            <i class="bi bi-cart-plus"></i> Ajouter
                        </button>
                    </div>
                </div>
                
                <div class="product-info">
                    <span class="category">Collection IBA</span>
                    <div class="name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="divider"></div>
                    <div class="price">
                        <?php if($est_promo): ?>
                            <span class="old"><?= number_format($prix_ancien, 0, ',', ' ') ?> FCFA</span>
                            <?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA
                            <span class="promo">Promo</span>
                        <?php else: ?>
                            <?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">
                        <i class="bi bi-chevron-left"></i>
                    </span>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                if($start > 1): ?>
                    <a href="?page=1">1</a>
                    <?php if($start > 2): ?>
                        <span class="disabled">…</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for($i = $start; $i <= $end; $i++): ?>
                    <?php if($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if($end < $totalPages): ?>
                    <?php if($end < $totalPages - 1): ?>
                        <span class="disabled">…</span>
                    <?php endif; ?>
                    <a href="?page=<?= $totalPages ?>"><?= $totalPages ?></a>
                <?php endif; ?>
                
                <?php if($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">
                        <i class="bi bi-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ========== SECTION À DÉCOUVRIR ========== -->
<?php if(!empty($produitsRecents)): ?>
<section class="section-recentes">
    <div class="container-custom">
        <div class="section-head">
            <div>
                <div class="section-title">
                    À découvrir
                    <small>D'autres pièces de la collection (non marquées nouvelles)</small>
                </div>
            </div>
            <a href="catalogue.php" class="view-all">
                Voir tout <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <div class="products-grid">
            <?php foreach($produitsRecents as $p): 
                $img = getImageUrl($p['image_principale'] ?? '');
                $est_promo = !empty($p['prix_promo']) && $p['prix_promo'] > 0 && $p['prix_promo'] < $p['prix'];
                $prix_affiché = $est_promo ? $p['prix_promo'] : $p['prix'];
                $prix_ancien = $est_promo ? $p['prix'] : null;
            ?>
            <div class="product-card" data-product-id="<?= $p['id'] ?>">
                <div class="image-wrapper">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>" loading="lazy" onerror="this.src='https://placehold.co/400x500/F5F5F5/C8922A?text=<?= urlencode($p['nom'])?>'">
                    <div class="product-overlay">
                        <a href="produit.php?id=<?= $p['id'] ?>" class="btn-overlay btn-view">
                            <i class="bi bi-eye"></i> Détail
                        </a>
                        <button class="btn-overlay btn-buy" onclick="ajouterAuPanier(event, <?= $p['id'] ?>, 1)">
                            <i class="bi bi-cart-plus"></i> Ajouter
                        </button>
                    </div>
                </div>
                
                <div class="product-info">
                    <span class="category">Collection IBA</span>
                    <div class="name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="divider"></div>
                    <div class="price">
                        <?php if($est_promo): ?>
                            <span class="old"><?= number_format($prix_ancien, 0, ',', ' ') ?> FCFA</span>
                            <?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA
                            <span class="promo">Promo</span>
                        <?php else: ?>
                            <?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ========== TOAST NOTIFICATION ========== -->
<div id="toast" class="toast-notification" style="position:fixed;bottom:30px;right:30px;background:#1A1A1A;color:white;padding:14px 20px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.3);display:flex;align-items:center;gap:10px;transform:translateY(100px);opacity:0;transition:all 0.4s ease;z-index:9999;border-left:4px solid #C8922A;font-family:'Inter',sans-serif;">
    <i class="bi bi-cart-plus" style="color:#C8922A;"></i>
    <span id="toastMessage">Ajouté au panier</span>
    <button class="toast-close" onclick="closeToast()" style="background:none;border:none;color:rgba(255,255,255,0.3);cursor:pointer;font-size:1.1rem;padding:0 5px;">&times;</button>
</div>

<script>
// ============================================
// AJOUTER AU PANIER - FONCTION AJAX
// ============================================
function ajouterAuPanier(event, produitId, quantite) {
    event.preventDefault();
    event.stopPropagation();
    
    if (!produitId || produitId <= 0) {
        showToast('Produit invalide', 'error');
        return;
    }
    
    const btn = event.currentTarget;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Ajout...';
    btn.style.opacity = '0.7';
    btn.style.pointerEvents = 'none';
    
    const formData = new FormData();
    formData.append('action', 'ajouter_panier');
    formData.append('produit_id', produitId);
    formData.append('quantite', quantite || 1);
    formData.append('couleur_id', 0);
    formData.append('taille_id', 0);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
        
        if (data.success) {
            // Mettre à jour le compteur du panier si présent
            const cartBadge = document.querySelector('.cart-badge .cart-count');
            if (cartBadge) {
                cartBadge.textContent = data.count || 0;
            }
            showToast(data.message || 'Produit ajouté au panier', 'success');
        } else {
            showToast(data.message || 'Erreur lors de l\'ajout', 'error');
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
        showToast('Erreur de connexion', 'error');
        console.error('Error:', error);
    });
}

// ============================================
// TOAST NOTIFICATION
// ============================================
let toastTimeout = null;

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    const toastMessage = document.getElementById('toastMessage');
    const icon = toast.querySelector('.toast-icon');
    
    if (toastMessage) toastMessage.textContent = message;
    
    if (type === 'error') {
        toast.style.borderLeftColor = '#E74C3C';
        toast.querySelector('i').className = 'bi bi-x-circle-fill';
    } else if (type === 'warning') {
        toast.style.borderLeftColor = '#F39C12';
        toast.querySelector('i').className = 'bi bi-exclamation-triangle-fill';
    } else {
        toast.style.borderLeftColor = '#C8922A';
        toast.querySelector('i').className = 'bi bi-check-circle-fill';
    }
    
    toast.classList.add('show');
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => toast.classList.remove('show'), 3000);
}

function closeToast() {
    const toast = document.getElementById('toast');
    if (toast) toast.classList.remove('show');
    clearTimeout(toastTimeout);
}
</script>

<?php require_once '../includes/footer.php'; ?>