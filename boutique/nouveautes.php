<?php
// ============================================
// NOUVEAUTÉS - Awa Ka Sugu
// Derniers produits ajoutés avec pagination
// ============================================

// ============================================
// SESSION PUBLIQUE SÉPARÉE
// ============================================
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// VÉRIFICATION MAINTENANCE
// ============================================
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

// S'assurer que ce sont des entiers pour la requête
$limit_int = (int)$limit;
$offset_int = (int)$offset;

// Compter le nombre total de nouveautés (30 derniers jours)
$countStmt = $pdo->query("
    SELECT COUNT(*) as total FROM produits 
    WHERE est_visible = 1 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$totalProduits = $countStmt->fetchColumn();
$totalPages = ceil($totalProduits / $limit);

// Récupérer les produits avec pagination
$stmt = $pdo->prepare("
    SELECT * FROM produits 
    WHERE est_visible = 1 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY created_at DESC 
    LIMIT $limit_int OFFSET $offset_int
");
$stmt->execute();
$produits = $stmt->fetchAll();

// Récupérer aussi les produits les plus récents pour la section "À découvrir"
$recentStmt = $pdo->query("
    SELECT * FROM produits 
    WHERE est_visible = 1 
    ORDER BY created_at DESC 
    LIMIT 4
");
$produitsRecents = $recentStmt->fetchAll();
?>

<style>
/* ============================================
   STYLES NOUVEAUTÉS - AWA KA SUGU
   ============================================ */

@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Jost:wght@300;400;500;600;700;800&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Jost', sans-serif;
    background: #F8F7F5;
    color: #1A1A1A;
}

/* ========== BANNIÈRE NOUVEAUTÉS ========== */
.banner-nouveautes {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
    padding: 80px 20px 70px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.banner-nouveautes::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        repeating-linear-gradient(90deg, rgba(200,146,42,0.03) 0px, rgba(200,146,42,0.03) 1px, transparent 1px, transparent 60px),
        repeating-linear-gradient(0deg, rgba(200,146,42,0.02) 0px, rgba(200,146,42,0.02) 1px, transparent 1px, transparent 60px);
    pointer-events: none;
}

.banner-nouveautes::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, #C8922A, #E8B55A, #C8922A, transparent);
    background-size: 200% 100%;
    animation: shimmerLine 4s linear infinite;
}

@keyframes shimmerLine {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.banner-nouveautes .banner-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(200,146,42,0.12);
    backdrop-filter: blur(10px);
    color: #C8922A;
    padding: 8px 24px 8px 20px;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
    border: 1px solid rgba(200,146,42,0.2);
    margin-bottom: 25px;
}

.banner-nouveautes .banner-badge i {
    font-size: 0.8rem;
}

.banner-nouveautes h1 {
    font-family: 'Playfair Display', serif;
    font-size: 3.2rem;
    font-weight: 700;
    color: #FFFFFF;
    line-height: 1.2;
    margin-bottom: 15px;
}

.banner-nouveautes h1 span {
    color: #C8922A;
    position: relative;
}

.banner-nouveautes h1 span::after {
    content: '';
    position: absolute;
    bottom: 4px;
    left: 0;
    right: 0;
    height: 4px;
    background: rgba(200,146,42,0.3);
    border-radius: 2px;
}

.banner-nouveautes .banner-desc {
    color: rgba(255,255,255,0.5);
    font-size: 1.05rem;
    max-width: 500px;
    margin: 0 auto 30px;
    line-height: 1.6;
}

.banner-stats {
    display: flex;
    justify-content: center;
    gap: 60px;
    padding-top: 30px;
    border-top: 1px solid rgba(255,255,255,0.06);
}

.banner-stats .stat-item {
    text-align: center;
}

.banner-stats .stat-number {
    display: block;
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    font-weight: 700;
    color: #C8922A;
    line-height: 1;
}

.banner-stats .stat-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-top: 6px;
}

/* ========== CONTAINER ========== */
.container-custom {
    max-width: 1300px;
    margin: 0 auto;
    padding: 0 20px;
}

/* ========== SECTION HEADER ========== */
.section-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin: 50px 0 35px;
    flex-wrap: wrap;
    gap: 15px;
}

.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    font-weight: 700;
    color: #0D0D0D;
    position: relative;
    padding-left: 20px;
}

.section-title::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #C8922A, #E8B55A);
    border-radius: 4px;
}

.section-title em {
    color: #C8922A;
    font-style: italic;
}

.section-title small {
    display: block;
    font-size: 0.85rem;
    font-weight: 400;
    color: #8A99AA;
    font-family: 'Jost', sans-serif;
    margin-top: 4px;
    padding-left: 0;
}

.section-filter {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.section-filter .filter-link {
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 500;
    color: #888;
    background: white;
    border: 1px solid #E8E5E0;
    text-decoration: none;
    transition: all 0.3s ease;
}

.section-filter .filter-link:hover,
.section-filter .filter-link.active {
    background: #C8922A;
    color: white;
    border-color: #C8922A;
}

/* ========== GRID PRODUITS ========== */
.products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 28px;
    margin-bottom: 50px;
}

/* ========== CARTE PRODUIT ========== */
.product-card {
    background: #FFFFFF;
    border-radius: 20px;
    overflow: hidden;
    text-decoration: none;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    border: 1px solid #F0EBE3;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    position: relative;
}

.product-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 50px rgba(0,0,0,0.1);
    border-color: #C8922A;
}

.product-image-wrapper {
    position: relative;
    aspect-ratio: 3/4;
    overflow: hidden;
    background: #F5F3F0;
}

.product-image-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.product-card:hover .product-image-wrapper img {
    transform: scale(1.08);
}

/* ========== BADGES ========== */
.product-badge-new {
    position: absolute;
    top: 16px;
    left: 16px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #1A1A1A;
    font-size: 0.6rem;
    font-weight: 700;
    padding: 5px 14px;
    border-radius: 30px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    z-index: 2;
    box-shadow: 0 4px 15px rgba(200,146,42,0.3);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.product-badge-new i {
    font-size: 0.5rem;
}

.product-badge-coup {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(8px);
    color: white;
    font-size: 0.55rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 30px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.product-badge-coup i {
    font-size: 0.55rem;
}

/* ========== OVERLAY ========== */
.product-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 60px 20px 30px;
    background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0) 100%);
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    display: flex;
    justify-content: center;
}

.product-card:hover .product-overlay {
    opacity: 1;
    transform: translateY(0);
}

.product-overlay .btn-detail {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #C8922A;
    color: white;
    padding: 12px 32px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    transition: all 0.3s ease;
    letter-spacing: 0.5px;
    box-shadow: 0 8px 25px rgba(200,146,42,0.3);
    border: 2px solid #C8922A;
    transform: scale(0.9);
}

.product-card:hover .product-overlay .btn-detail {
    transform: scale(1);
}

.product-overlay .btn-detail:hover {
    background: transparent;
    color: #C8922A;
    transform: scale(1.05);
}

.product-overlay .btn-detail i {
    font-size: 1rem;
}

/* ========== INFOS PRODUIT ========== */
.product-info {
    padding: 18px 20px 22px;
    text-align: center;
    background: #FFFFFF;
}

.product-category {
    font-size: 0.6rem;
    color: #C8922A;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.product-category i {
    font-size: 0.5rem;
}

.product-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1A1A1A;
    margin: 6px 0 10px;
    font-family: 'Playfair Display', serif;
    transition: color 0.3s ease;
    line-height: 1.3;
}

.product-card:hover .product-name {
    color: #C8922A;
}

.product-price-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding-top: 12px;
    border-top: 1px solid #F0EDE8;
}

.product-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: #C8922A;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.product-price .old-price {
    font-size: 0.8rem;
    color: #BBB;
    text-decoration: line-through;
    font-weight: 400;
}

/* ========== PAGINATION ========== */
.pagination-wrapper {
    display: flex;
    justify-content: center;
    margin: 40px 0 60px;
}

.pagination {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pagination a, .pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 42px;
    height: 42px;
    padding: 0 16px;
    border-radius: 50%;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    color: #666;
    background: white;
    border: 1px solid #E8E5E0;
    transition: all 0.25s ease;
}

.pagination a:hover {
    background: #C8922A;
    color: white;
    border-color: #C8922A;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(200,146,42,0.25);
}

.pagination .active {
    background: #C8922A;
    color: white;
    border-color: #C8922A;
    box-shadow: 0 4px 12px rgba(200,146,42,0.3);
}

.pagination .disabled {
    opacity: 0.3;
    cursor: not-allowed;
    pointer-events: none;
}

.pagination a i, .pagination span i {
    font-size: 0.8rem;
}

/* ========== SECTION À DÉCOUVRIR ========== */
.section-recentes {
    background: #FFFFFF;
    padding: 60px 0 70px;
    border-top: 1px solid #F0EDE8;
    margin-top: 20px;
}

/* ========== EMPTY STATE ========== */
.empty-state {
    text-align: center;
    padding: 80px 40px;
    background: white;
    border-radius: 20px;
    border: 1px solid #F0EBE3;
}

.empty-state .empty-icon {
    font-size: 3.5rem;
    color: #DDD;
    margin-bottom: 15px;
    display: block;
}

.empty-state h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem;
    color: #1A1A1A;
    margin-bottom: 10px;
}

.empty-state p {
    color: #8A99AA;
    margin-bottom: 20px;
}

.empty-state .btn-empty {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #C8922A;
    color: white;
    padding: 10px 32px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.empty-state .btn-empty:hover {
    background: #9A6E1A;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(200,146,42,0.3);
}

/* ========== RESPONSIVE ========== */
@media (max-width: 1100px) {
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 22px;
    }
}

@media (max-width: 800px) {
    .banner-nouveautes {
        padding: 60px 20px 50px;
    }
    
    .banner-nouveautes h1 {
        font-size: 2.2rem;
    }
    
    .banner-nouveautes .banner-desc {
        font-size: 0.9rem;
    }
    
    .banner-stats {
        gap: 35px;
        flex-wrap: wrap;
    }
    
    .banner-stats .stat-number {
        font-size: 1.8rem;
    }
    
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 18px;
    }
    
    .section-title {
        font-size: 1.6rem;
    }
    
    .section-head {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .section-filter {
        width: 100%;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 8px;
    }
}

@media (max-width: 500px) {
    .banner-nouveautes h1 {
        font-size: 1.8rem;
    }
    
    .banner-nouveautes .banner-desc {
        font-size: 0.85rem;
    }
    
    .banner-stats {
        gap: 20px;
    }
    
    .banner-stats .stat-number {
        font-size: 1.4rem;
    }
    
    .banner-stats .stat-label {
        font-size: 0.6rem;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
        max-width: 360px;
        margin: 0 auto 40px;
    }
    
    .pagination a, .pagination span {
        min-width: 38px;
        height: 38px;
        font-size: 0.8rem;
    }
    
    .section-title {
        font-size: 1.3rem;
        padding-left: 15px;
    }
    
    .section-title small {
        font-size: 0.75rem;
    }
}
</style>

<!-- ========== BANNIÈRE NOUVEAUTÉS ========== -->
<div class="banner-nouveautes">
    <div class="banner-badge">
        <i class="bi bi-gem"></i>
        Nouveautés 2026
    </div>
    <h1>Dernières <span>créations</span></h1>
    <p class="banner-desc">
        Les pièces les plus récentes de la collection IBA Design, sélectionnées avec soin pour vous.
    </p>
    <div class="banner-stats">
        <div class="stat-item">
            <span class="stat-number"><?= $totalProduits ?></span>
            <span class="stat-label">Nouveautés</span>
        </div>
        <div class="stat-item">
            <span class="stat-number">30</span>
            <span class="stat-label">Jours</span>
        </div>
        <div class="stat-item">
            <span class="stat-number">
                <i class="bi bi-star-fill" style="font-size:1.6rem;color:#C8922A;"></i>
            </span>
            <span class="stat-label">Collection 2026</span>
        </div>
    </div>
</div>

<!-- ========== CONTENU PRINCIPAL ========== -->
<div class="container-custom">
    <!-- Section Header -->
    <div class="section-head">
        <div>
            <div class="section-title">
                Toutes les nouveautés
                <small>Les dernières pièces ajoutées à la collection</small>
            </div>
        </div>
        <div class="section-filter">
            <a href="#" class="filter-link active">Tous</a>
            <a href="#" class="filter-link">Abayas</a>
            <a href="#" class="filter-link">Accessoires</a>
            <a href="#" class="filter-link">Chaussures</a>
        </div>
    </div>

    <?php if(empty($produits)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="bi bi-box-seam empty-icon"></i>
            <h3>Aucune nouveauté pour le moment</h3>
            <p>Revenez bientôt pour découvrir nos dernières créations.</p>
            <a href="catalogue.php" class="btn-empty">
                <i class="bi bi-grid"></i> Voir le catalogue
            </a>
        </div>
    <?php else: ?>
        <!-- Products Grid -->
        <div class="products-grid">
            <?php foreach($produits as $index => $p): ?>
            <div class="product-card">
                <div class="product-image-wrapper">
                    <?php 
                    $img = !empty($p['image_principale']) && file_exists('../uploads/produits/'.$p['image_principale']) 
                        ? '../uploads/produits/'.$p['image_principale'] 
                        : 'https://placehold.co/400x500/F5F3F0/C8922A?text='.urlencode($p['nom']);
                    ?>
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>" loading="lazy">
                    
                    <!-- Badges -->
                    <div class="product-badge-new">
                        <i class="bi bi-star-fill"></i> Nouveau
                    </div>
                    <?php if($index % 3 == 0): ?>
                    <div class="product-badge-coup">
                        <i class="bi bi-heart-fill"></i> Coup de cœur
                    </div>
                    <?php endif; ?>
                    
                    <!-- Overlay -->
                    <div class="product-overlay">
                        <a href="produit.php?id=<?= $p['id'] ?>" class="btn-detail">
                            <i class="bi bi-eye"></i> Voir détails
                        </a>
                    </div>
                </div>
                
                <div class="product-info">
                    <div class="product-category">
                        <i class="bi bi-tag"></i> Collection IBA
                    </div>
                    <div class="product-name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="product-price-row">
                        <div class="product-price">
                            <i class="bi bi-coin" style="font-size:0.8rem;opacity:0.5;"></i>
                            <?= number_format($p['prix'], 0, ',', ' ') ?> FCFA
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
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
<section class="section-recentes">
    <div class="container-custom">
        <div class="section-head">
            <div>
                <div class="section-title">
                    À découvrir aussi
                    <small>D'autres pièces de la collection</small>
                </div>
            </div>
            <a href="catalogue.php" class="filter-link" style="display:inline-flex;align-items:center;gap:8px;padding:8px 20px;border-radius:30px;font-size:0.75rem;font-weight:500;color:#C8922A;background:rgba(200,146,42,0.1);border:1px solid rgba(200,146,42,0.2);text-decoration:none;transition:all 0.3s;">
                Voir tout <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <?php if(!empty($produitsRecents)): ?>
        <div class="products-grid">
            <?php foreach($produitsRecents as $p): ?>
            <div class="product-card">
                <div class="product-image-wrapper">
                    <?php 
                    $img = !empty($p['image_principale']) && file_exists('../uploads/produits/'.$p['image_principale']) 
                        ? '../uploads/produits/'.$p['image_principale'] 
                        : 'https://placehold.co/400x500/F5F3F0/C8922A?text='.urlencode($p['nom']);
                    ?>
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>" loading="lazy">
                    
                    <div class="product-overlay">
                        <a href="produit.php?id=<?= $p['id'] ?>" class="btn-detail">
                            <i class="bi bi-eye"></i> Voir détails
                        </a>
                    </div>
                </div>
                
                <div class="product-info">
                    <div class="product-category">
                        <i class="bi bi-tag"></i> Collection IBA
                    </div>
                    <div class="product-name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="product-price-row">
                        <div class="product-price">
                            <i class="bi bi-coin" style="font-size:0.8rem;opacity:0.5;"></i>
                            <?= number_format($p['prix'], 0, ',', ' ') ?> FCFA
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>