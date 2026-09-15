<?php
// ============================================
// PROMOTIONS - Produits en solde (PUBLIC)
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

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

// ============================================
// FONCTION POUR L'IMAGE
// ============================================
function getImageUrl($image) {
    if (empty($image)) {
        return 'https://placehold.co/400x500/F5F5F5/C8922A?text=Produit';
    }
    
    $image = trim($image);
    $image_name = pathinfo($image, PATHINFO_FILENAME);
    $extension = pathinfo($image, PATHINFO_EXTENSION);
    
    $dossiers = [
        'uploads/produits/voile/',
        '../uploads/produits/voile/',
        'uploads/produits/pret a porter femme/',
        '../uploads/produits/pret a porter femme/',
        'uploads/produits/les tallons/',
        '../uploads/produits/les tallons/',
        'uploads/produits/fermés/',
        '../uploads/produits/fermés/',
        'uploads/produits/les turbants/',
        '../uploads/produits/les turbants/',
        'uploads/produits/les foulards/',
        '../uploads/produits/les foulards/',
        'uploads/produits/les foullards/',
        '../uploads/produits/les foullards/',
        'uploads/produits/port-monaie/',
        '../uploads/produits/port-monaie/',
        'uploads/produits/sacs a mains/',
        '../uploads/produits/sacs a mains/',
        'uploads/produits/ensemble tallons sacs/',
        '../uploads/produits/ensemble tallons sacs/',
        'uploads/produits/abayas/',
        '../uploads/produits/abayas/',
        'uploads/produits/abayas pour enfants/',
        '../uploads/produits/abayas pour enfants/',
        'uploads/produits/',
        '../uploads/produits/',
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
// RÉCUPÉRER LES PRODUITS EN PROMOTION
// ============================================
try {
    $stmt = $pdo->query("
        SELECT DISTINCT p.* 
        FROM produits p
        WHERE p.est_visible = 1 
        AND p.est_promo = 1 
        AND p.prix_promo IS NOT NULL 
        AND p.prix_promo > 0 
        AND p.prix_promo < p.prix
        ORDER BY ((p.prix - p.prix_promo) / p.prix * 100) DESC
    ");
    $produits = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $produits = [];
    error_log("Erreur récupération promotions: " . $e->getMessage());
}

$nb_promotions = count($produits);
$reduction_max = 0;
$economie_totale = 0;
if (!empty($produits)) {
    foreach($produits as $p) {
        $reduction = round((($p['prix'] - $p['prix_promo']) / $p['prix']) * 100);
        if ($reduction > $reduction_max) {
            $reduction_max = $reduction;
        }
        $economie_totale += ($p['prix'] - $p['prix_promo']);
    }
}
?>

<style>
/* ═══════════════════════════════════════════
   PROMOTIONS — DESIGN VITRINE COMME LA BOUTIQUE
   ═══════════════════════════════════════════ */

:root {
    --gold: #C8922A;
    --gold-deep: #9A6E1A;
    --gold-light: #E8C070;
    --ink: #0D0D0D;
    --muted: #8A99AA;
    --line: #EEF0F4;
    --line-soft: #F4F6F9;
    --danger: #E74C3C;
    --ease: cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* ── HERO PROMOTIONS ── */
.promotions-header {
    position: relative;
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1510 60%, #0D0D0D 100%);
    padding: 80px 0 70px;
    text-align: center;
    margin-bottom: 50px;
    overflow: hidden;
    isolation: isolate;
}

.promotions-header::before {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 600px;
    height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(200,146,42,0.15) 0%, transparent 70%);
    z-index: 0;
    pointer-events: none;
}

.promotions-header::after {
    content: '';
    position: absolute;
    left: 50%; transform: translateX(-50%);
    bottom: 0;
    width: min(200px, 60%);
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    z-index: 2;
}

.promotions-header .container-custom {
    position: relative;
    z-index: 2;
}

.promotions-header .promo-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--gold-light);
    padding: 8px 20px;
    background: rgba(200,146,42,0.08);
    border: 1px solid rgba(200,146,42,0.2);
    border-radius: 50px;
    margin-bottom: 22px;
    backdrop-filter: blur(8px);
}
.promotions-header .promo-eyebrow i {
    color: var(--gold);
    font-size: 0.75rem;
}

.promotions-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(2rem, 4vw, 3rem);
    font-weight: 600;
    color: #FFFFFF;
    margin: 0 0 12px;
    line-height: 1.1;
    letter-spacing: -0.5px;
}
.promotions-header h1 em {
    color: var(--gold);
    font-style: italic;
    font-weight: 700;
}
.promotions-header > .container-custom > p {
    color: rgba(255,255,255,0.55);
    font-size: 0.95rem;
    font-weight: 300;
    margin: 0 auto 40px;
    max-width: 500px;
    line-height: 1.6;
}

.stats-promo {
    display: flex;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
    max-width: 720px;
    margin: 0 auto;
}

.stats-promo .stat {
    flex: 1;
    min-width: 140px;
    max-width: 200px;
    padding: 18px 20px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    transition: all 0.35s var(--ease);
    position: relative;
    overflow: hidden;
}
.stats-promo .stat::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(200,146,42,0.5), transparent);
    opacity: 0;
    transition: opacity 0.35s var(--ease);
}
.stats-promo .stat:hover {
    border-color: rgba(200,146,42,0.35);
    background: rgba(200,146,42,0.05);
    transform: translateY(-3px);
}
.stats-promo .stat:hover::before { opacity: 1; }

.stats-promo .stat .number {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--gold);
    display: block;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
    line-height: 1;
}
.stats-promo .stat .label {
    font-size: 0.6rem;
    color: rgba(255,255,255,0.45);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 500;
    display: block;
}

/* ── CONTAINER ── */
.container-custom {
    max-width: 1300px;
    margin: 0 auto;
    padding: 0 40px;
}

/* ── SECTION TITLE ── */
.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 32px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--line-soft);
    position: relative;
}
.section-title::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 50px;
    height: 2px;
    background: linear-gradient(90deg, var(--gold), var(--gold-light));
    border-radius: 2px;
}
.section-title i {
    color: var(--gold);
    font-size: 1.15rem;
}
.section-title .count-note {
    font-family: 'Inter', sans-serif;
    font-size: 0.72rem;
    color: var(--muted);
    font-weight: 400;
    margin-left: auto;
    letter-spacing: 0.2px;
}

/* ═══════════════════════════════════════════
   GRILLE PRODUITS — EXACTEMENT COMME LA BOUTIQUE
   Masonry 2 colonnes minimum sur mobile
   ═══════════════════════════════════════════ */
.products-grid {
    display: block;
    column-count: 2;
    column-gap: 14px;
    margin-bottom: 50px;
}

@media (min-width: 700px) {
    .products-grid { column-count: 3; column-gap: 18px; }
}
@media (min-width: 1000px) {
    .products-grid { column-count: 4; column-gap: 22px; }
}
@media (min-width: 1400px) {
    .products-grid { column-count: 5; column-gap: 24px; }
}

/* ── CARTE PRODUIT — comme la boutique ── */
.product-card {
    display: inline-block;
    width: 100%;
    background: #FFFFFF;
    border-radius: 16px;
    overflow: hidden;
    transition: transform 0.4s var(--ease), box-shadow 0.4s var(--ease), border-color 0.4s var(--ease);
    text-decoration: none;
    border: 1px solid var(--line-soft);
    position: relative;
    margin-bottom: 16px;
    break-inside: avoid;
    cursor: pointer;
}
.product-card:hover {
    transform: translateY(-5px);
    border-color: rgba(200,146,42,0.3);
    box-shadow: 0 18px 40px rgba(200,146,42,0.12);
}

.product-image {
    position: relative;
    aspect-ratio: 3 / 4;
    overflow: hidden;
    background: #F4F5F8;
}
.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.6s var(--ease);
}
.product-card:hover .product-image img {
    transform: scale(1.06);
}

/* Badge promo — rouge, propre */
.promo-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: var(--danger);
    color: #fff;
    font-size: 0.58rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 4px;
    letter-spacing: 0.3px;
    box-shadow: 0 4px 12px rgba(231,76,60,0.35);
    z-index: 2;
}

/* Badge "Économisez" */
.promo-save {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: var(--ink);
    font-size: 0.58rem;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 20px;
    letter-spacing: 0.2px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    z-index: 2;
}

/* Infos produit */
.product-info {
    padding: 12px 14px 14px;
    text-align: left;
}

.product-name {
    font-family: 'Inter', sans-serif;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--ink);
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: 0.1px;
    transition: color 0.3s;
}
.product-card:hover .product-name {
    color: var(--gold);
}

.product-prices {
    display: flex;
    align-items: baseline;
    gap: 6px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.price-promo {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--danger);
    letter-spacing: -0.2px;
}
.price-old {
    font-size: 0.7rem;
    color: var(--muted);
    text-decoration: line-through;
    font-weight: 400;
}

/* Bouton "Profiter" — pleine largeur, style boutique */
.btn-quick {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    background: var(--ink);
    color: #fff;
    padding: 9px 14px;
    border-radius: 50px;
    font-family: 'Inter', sans-serif;
    font-size: 0.72rem;
    font-weight: 500;
    text-decoration: none;
    letter-spacing: 0.2px;
    transition: all 0.3s var(--ease);
    border: 1px solid var(--ink);
}
.btn-quick:hover {
    background: var(--gold);
    color: #0A0804;
    border-color: var(--gold);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(200,146,42,0.25);
}
.btn-quick i {
    font-size: 0.72rem;
}

/* ── ÉTAT VIDE ── */
.empty-state {
    text-align: center;
    padding: 70px 30px;
    background: #fff;
    border-radius: 18px;
    border: 1px solid var(--line-soft);
    column-span: all;
    break-inside: avoid;
}
.empty-state i {
    font-size: 2.8rem;
    color: var(--gold);
    opacity: 0.4;
    display: block;
    margin-bottom: 16px;
}
.empty-state p {
    margin-top: 10px;
    color: var(--muted);
    font-size: 0.9rem;
}
.empty-state .btn-quick {
    display: inline-flex;
    width: auto;
    margin-top: 18px;
    padding: 10px 24px;
}

/* ── BOUTON VOIR TOUT ── */
.btn-view-all {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: transparent;
    border: 1.5px solid var(--gold);
    color: var(--gold-deep);
    padding: 13px 34px;
    border-radius: 50px;
    text-decoration: none;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    font-size: 0.82rem;
    letter-spacing: 0.3px;
    transition: all 0.3s var(--ease);
}
.btn-view-all:hover {
    background: var(--gold);
    color: #fff;
    border-color: var(--gold);
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(200,146,42,0.25);
}
.btn-view-all i {
    transition: transform 0.3s;
}
.btn-view-all:hover i { transform: translateX(3px); }

/* ── RESPONSIVE ── */
@media (max-width: 1100px) {
    /* 3 colonnes gérées par media query */
}
@media (max-width: 800px) {
    .container-custom { padding: 0 20px; }
    .promotions-header { padding: 60px 0 50px; margin-bottom: 40px; }
    .promotions-header h1 { font-size: 1.8rem; }
    .promotions-header > .container-custom > p { font-size: 0.85rem; margin-bottom: 30px; }
    .stats-promo { gap: 10px; }
    .stats-promo .stat { padding: 14px 16px; min-width: 100px; }
    .stats-promo .stat .number { font-size: 1.4rem; }
    .section-title { font-size: 1.25rem; margin-bottom: 24px; }
}
@media (max-width: 500px) {
    .container-custom { padding: 0 14px; }
    .promotions-header { padding: 50px 0 44px; margin-bottom: 30px; }
    .promotions-header h1 { font-size: 1.6rem; }
    .stats-promo .stat .number { font-size: 1.3rem; }
    .stats-promo .stat .label { font-size: 0.55rem; letter-spacing: 1px; }
    .products-grid { column-gap: 12px; }
    .product-card { margin-bottom: 12px; }
    .product-info { padding: 10px 12px 12px; }
    .product-name { font-size: 0.75rem; }
    .price-promo { font-size: 0.82rem; }
    .price-old { font-size: 0.65rem; }
    .btn-quick { padding: 8px 12px; font-size: 0.68rem; }
    .promo-badge { font-size: 0.52rem; padding: 3px 8px; }
    .promo-save { font-size: 0.52rem; padding: 2px 8px; }
}
</style>

<!-- ════════ HEADER PROMOTIONS ════════ -->
<div class="promotions-header">
    <div class="container-custom">
        <span class="promo-eyebrow">
            <i class="bi bi-fire"></i> Offres limitées
        </span>
        <h1>Les <em>Promotions</em></h1>
        <p>Profitez de nos meilleures offres du moment sur une sélection exclusive de créations IBA Design.</p>
        
        <div class="stats-promo">
            <div class="stat">
                <span class="number"><?= $nb_promotions ?></span>
                <span class="label">Produits en promo</span>
            </div>
            <div class="stat">
                <span class="number">-<?= $reduction_max ?>%</span>
                <span class="label">Réduction max</span>
            </div>
            <div class="stat">
                <span class="number"><?= number_format($economie_totale, 0, ',', ' ') ?></span>
                <span class="label">FCFA d'économies</span>
            </div>
        </div>
    </div>
</div>

<!-- ════════ PRODUITS ════════ -->
<div class="container-custom">
    <?php if(empty($produits)): ?>
        <div class="empty-state">
            <i class="bi bi-tag"></i>
            <h3 style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--ink);margin:0;">Aucune promotion en cours</h3>
            <p>Revenez bientôt pour découvrir nos nouvelles offres.</p>
            <a href="catalogue.php" class="btn-quick">
                <i class="bi bi-arrow-left"></i> Voir tous les produits
            </a>
        </div>
    <?php else: ?>
        <div class="section-title">
            <i class="bi bi-percent"></i>
            <span>Offres spéciales</span>
            <span class="count-note">
                <?= $nb_promotions ?> produit<?= $nb_promotions > 1 ? 's' : '' ?>
            </span>
        </div>
        
        <div class="products-grid">
            <?php foreach($produits as $p): 
                $reduction = round((($p['prix'] - $p['prix_promo']) / $p['prix']) * 100);
                $economie = $p['prix'] - $p['prix_promo'];
                $img = getImageUrl($p['image_principale'] ?? '');
            ?>
            <a href="produit.php?id=<?= $p['id'] ?>" class="product-card">
                <div class="product-image">
                    <img src="<?= $img ?>" 
                         alt="<?= htmlspecialchars($p['nom']) ?>" 
                         loading="lazy" 
                         onerror="this.src='https://placehold.co/400x500/F5F5F5/C8922A?text=<?= urlencode($p['nom'])?>'">
                    <div class="promo-badge">-<?= $reduction ?>%</div>
                    <?php if ($economie > 0): ?>
                        <div class="promo-save">Économisez <?= number_format($economie, 0, ',', ' ') ?> F</div>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="product-prices">
                        <span class="price-promo"><?= number_format($p['prix_promo'], 0, ',', ' ') ?> FCFA</span>
                        <span class="price-old"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</span>
                    </div>
                    <span class="btn-quick">
                        <i class="bi bi-bag-heart"></i> Profiter de l'offre
                    </span>
                </div>
            </a>
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