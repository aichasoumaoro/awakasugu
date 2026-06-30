<?php
// ============================================
// PROMOTIONS - Produits en solde
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

$titre_page = 'Promotions - IBA Design';
$meta_desc = 'Profitez des promotions et offres spéciales de la collection IBA Design.';
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
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les produits en promotion
$stmt = $pdo->query("
    SELECT * FROM produits 
    WHERE est_visible = 1 
    AND est_promo = 1 
    AND prix_promo IS NOT NULL 
    AND prix_promo > 0 
    AND prix_promo < prix
    ORDER BY ((prix - prix_promo) / prix * 100) DESC
");
$produits = $stmt->fetchAll();

// Récupérer les promotions actives depuis la table promotions
$promotions = $pdo->query("
    SELECT p.*, pr.nom as promo_nom, pr.description as promo_description
    FROM promotions pr
    LEFT JOIN produits p ON p.id = pr.produit_id
    WHERE pr.est_active = 1 
    AND pr.date_fin >= CURDATE()
    AND (p.id IS NULL OR p.est_visible = 1)
    ORDER BY pr.date_fin ASC
    LIMIT 3
")->fetchAll();
?>

<style>
/* ========== PAGE PROMOTIONS ========== */
.promotions-header {
    background: linear-gradient(135deg, #1A1A1A 0%, #2D2D2D 100%);
    padding: 60px 0 50px;
    text-align: center;
    margin-bottom: 50px;
    position: relative;
    overflow: hidden;
}
.promotions-header::before {
    content: '🔥';
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 80px;
    opacity: 0.05;
}
.promotions-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.5rem;
    color: #C8922A;
    margin-bottom: 15px;
}
.promotions-header p {
    color: rgba(255,255,255,0.6);
    font-size: 0.9rem;
}
.container-custom {
    max-width: 1300px;
    margin: 0 auto;
    padding: 0 40px;
}
.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1A1A1A;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.section-title i {
    color: #C8922A;
}
.products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    margin-bottom: 40px;
}
.product-card {
    background: #FFFFFF;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s;
    text-decoration: none;
    border: 1px solid #F0F0F0;
    position: relative;
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 25px rgba(0,0,0,0.08);
}
.product-image {
    position: relative;
    height: 280px;
    overflow: hidden;
    background: #F8F8F8;
}
.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}
.product-card:hover .product-image img {
    transform: scale(1.03);
}
.promo-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: #E74C3C;
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 20px;
    box-shadow: 0 4px 10px rgba(231,76,60,0.3);
    z-index: 2;
    animation: pulse-badge 2s infinite;
}
@keyframes pulse-badge {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
.product-info {
    padding: 16px;
    text-align: center;
}
.product-name {
    font-size: 0.9rem;
    font-weight: 500;
    color: #1A1A1A;
    margin-bottom: 6px;
}
.product-prices {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}
.price-promo {
    font-size: 1.1rem;
    font-weight: 700;
    color: #E74C3C;
}
.price-old {
    font-size: 0.85rem;
    color: #8A99AA;
    text-decoration: line-through;
}
.btn-quick {
    display: inline-block;
    background: #C8922A;
    color: white;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
    text-decoration: none;
    margin-top: 5px;
    transition: background 0.3s;
}
.btn-quick:hover {
    background: #9A6E1A;
    color: white;
}
.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 16px;
    grid-column: 1/-1;
}
.empty-state i {
    font-size: 3rem;
    color: #CCC;
}
.empty-state p {
    margin-top: 15px;
    color: #8A99AA;
}
.btn-view-all {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    border: 1.5px solid #C8922A;
    color: #C8922A;
    padding: 12px 32px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-view-all:hover {
    background: #C8922A;
    color: white;
}
@media (max-width: 1100px) {
    .products-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 800px) {
    .products-grid { grid-template-columns: repeat(2, 1fr); }
    .container-custom { padding: 0 20px; }
    .promotions-header h1 { font-size: 1.8rem; }
}
@media (max-width: 500px) {
    .products-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Header -->
<div class="promotions-header">
    <div class="container-custom">
        <h1>🔥 Promotions</h1>
        <p>Les meilleures offres du moment</p>
    </div>
</div>

<div class="container-custom">
    <?php if(empty($produits)): ?>
        <div class="empty-state">
            <i class="bi bi-tag"></i>
            <p>Aucune promotion en cours pour le moment.</p>
            <a href="catalogue.php" class="btn-quick" style="display:inline-block;margin-top:10px;">Voir tous les produits →</a>
        </div>
    <?php else: ?>
        <div class="section-title">
            <i class="bi bi-percent"></i>
            <span>Offres spéciales</span>
        </div>
        
        <div class="products-grid">
            <?php foreach($produits as $p): 
                $reduction = round((($p['prix'] - $p['prix_promo']) / $p['prix']) * 100);
                $img = !empty($p['image_principale']) && file_exists('../uploads/produits/'.$p['image_principale']) 
                    ? '../uploads/produits/'.$p['image_principale'] 
                    : 'https://placehold.co/400x500/F8F8F8/C8922A?text='.urlencode($p['nom']);
            ?>
            <div class="product-card">
                <div class="product-image">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>">
                    <div class="promo-badge">-<?= $reduction ?>%</div>
                </div>
                <div class="product-info">
                    <div class="product-name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="product-prices">
                        <span class="price-promo"><?= number_format($p['prix_promo'], 0, ',', ' ') ?> FCFA</span>
                        <span class="price-old"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</span>
                    </div>
                    <a href="produit.php?id=<?= $p['id'] ?>" class="btn-quick">Profiter de l'offre</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin: 20px 0 40px;">
            <a href="catalogue.php" class="btn-view-all">
                Voir toute la collection <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>