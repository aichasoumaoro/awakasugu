<?php
// ============================================
// RESTAURANT SOFIA - Menu Complet Premium
// ============================================

$titre_page = 'Restaurant Sofia';
$meta_desc  = 'Découvrez le Restaurant Sofia : cuisine malienne, européenne, pâtisserie et événements.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Connexion BDD
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

// Récupérer les catégories principales
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories_restaurant ORDER BY ordre");
    $categories = $stmt->fetchAll();
} catch(PDOException $e) {
    $categories = [
        ['id' => 1, 'nom' => 'Cuisine Malienne', 'icone' => '🇲🇱', 'ordre' => 1],
        ['id' => 2, 'nom' => 'Cuisine Européenne', 'icone' => '🇪🇺', 'ordre' => 2],
        ['id' => 3, 'nom' => 'Pâtisserie & Desserts', 'icone' => '🍰', 'ordre' => 3],
        ['id' => 4, 'nom' => 'Boissons', 'icone' => '🥤', 'ordre' => 4],
        ['id' => 5, 'nom' => 'Gâteaux Événements', 'icone' => '🎂', 'ordre' => 5],
        ['id' => 6, 'nom' => 'Fast Food', 'icone' => '🍔', 'ordre' => 6],
    ];
}

// Récupérer tous les plats
$plats = $pdo->query("SELECT * FROM plats WHERE est_visible = 1 ORDER BY est_plat_du_jour DESC, created_at DESC")->fetchAll();

// Plat du jour
$plat_jour = $pdo->query("SELECT * FROM plats WHERE est_plat_du_jour = 1 AND est_visible = 1 LIMIT 1")->fetch();
if (!$plat_jour && !empty($plats)) {
    $plat_jour = $plats[0];
}
?>

<style>
/* ============================================
   DESIGN RESTAURANT SOFIA - PREMIUM
   ============================================ */

@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Jost:wght@300;400;500;600;700;800&family=Great+Vibes&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* ===== VARIABLES ===== */
:root {
    --gold: #C8922A;
    --gold-light: #E8B55A;
    --gold-dark: #9A6E1A;
    --dark: #0D0D0D;
    --dark-light: #1A1510;
    --cream: #F8F5F0;
    --white: #FFFFFF;
    --text: #0D0D0D;
    --text-light: #8A99AA;
}

/* ===== NAVBAR RESTAURANT ===== */
.restaurant-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: rgba(13, 13, 13, 0.95);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(200,146,42,0.1);
    padding: 0 40px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.restaurant-nav .nav-brand {
    font-family: 'Great Vibes', cursive;
    font-size: 1.8rem;
    color: var(--gold);
    text-decoration: none;
    letter-spacing: 2px;
}

.restaurant-nav .nav-brand span {
    color: var(--white);
}

.restaurant-nav .nav-links {
    display: flex;
    align-items: center;
    gap: 25px;
    list-style: none;
}

.restaurant-nav .nav-links a {
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    font-size: 0.75rem;
    font-weight: 500;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: all 0.3s;
    position: relative;
}

.restaurant-nav .nav-links a::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--gold);
    transition: width 0.3s;
}

.restaurant-nav .nav-links a:hover::after,
.restaurant-nav .nav-links a.active::after {
    width: 100%;
}

.restaurant-nav .nav-links a:hover,
.restaurant-nav .nav-links a.active {
    color: var(--gold);
}

.nav-cta {
    padding: 8px 24px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--dark) !important;
    border-radius: 30px;
    font-weight: 700 !important;
}

.nav-cta::after {
    display: none !important;
}

.nav-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(200,146,42,0.3);
    color: var(--white) !important;
}

/* ✅ LIEN RETOUR ACCUEIL PRINCIPAL */
.nav-back-home {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: rgba(255,255,255,0.3) !important;
    font-size: 0.65rem !important;
    border-right: 1px solid rgba(255,255,255,0.1);
    padding-right: 20px;
}

.nav-back-home:hover {
    color: var(--gold) !important;
}

.nav-back-home i {
    font-size: 0.8rem;
}

/* ===== HERO SECTION ===== */
.hero-restaurant {
    position: relative;
    min-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(165deg, #0A0806 0%, #1A1510 40%, #0D0A07 100%);
    overflow: hidden;
    padding: 100px 20px;
    margin-top: 70px;
}

.hero-restaurant::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(ellipse at 30% 50%, rgba(200,146,42,0.06) 0%, transparent 60%);
    animation: glowRotate 20s linear infinite;
}

@keyframes glowRotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.hero-restaurant .bg-pattern {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 20% 80%, rgba(200,146,42,0.03) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(200,146,42,0.03) 0%, transparent 50%);
    pointer-events: none;
}

.hero-restaurant .hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 900px;
}

.hero-restaurant .hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(200,146,42,0.12);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(200,146,42,0.2);
    padding: 8px 24px 8px 20px;
    border-radius: 50px;
    margin-bottom: 25px;
    color: var(--gold);
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.hero-restaurant .hero-badge i {
    font-size: 0.9rem;
}

.hero-restaurant h1 {
    font-family: 'Great Vibes', cursive;
    font-size: 5rem;
    color: var(--white);
    margin-bottom: 10px;
    letter-spacing: 2px;
}

.hero-restaurant h1 span {
    color: var(--gold);
}

.hero-restaurant .hero-subtitle {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem;
    color: rgba(255,255,255,0.3);
    letter-spacing: 6px;
    text-transform: uppercase;
    margin-bottom: 20px;
}

.hero-restaurant .hero-deco-line {
    width: 100px;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    margin: 20px auto;
}

.hero-restaurant p {
    color: rgba(255,255,255,0.5);
    font-size: 1.05rem;
    line-height: 1.8;
    margin-bottom: 30px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.hero-restaurant .hero-actions {
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
}

.btn-hero {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 35px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s;
    letter-spacing: 1px;
}

.btn-hero.primary {
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--dark);
    box-shadow: 0 8px 30px rgba(200,146,42,0.3);
}

.btn-hero.primary:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(200,146,42,0.5);
    color: var(--white);
}

.btn-hero.outline {
    border: 2px solid rgba(200,146,42,0.4);
    color: var(--gold);
}

.btn-hero.outline:hover {
    background: rgba(200,146,42,0.1);
    transform: translateY(-4px);
    border-color: var(--gold);
}

/* ===== SECTION MENU ===== */
.section-menu {
    padding: 60px 0 80px;
    background: var(--white);
}

.section-menu .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.menu-header {
    text-align: center;
    margin-bottom: 50px;
}

.menu-header .menu-label {
    display: inline-block;
    background: rgba(200,146,42,0.08);
    color: var(--gold);
    padding: 4px 18px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 10px;
}

.menu-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2.8rem;
    color: var(--dark);
}

.menu-header h2 span {
    color: var(--gold);
}

.menu-header p {
    color: var(--text-light);
    font-size: 1rem;
}

/* ===== CATÉGORIES TABS ===== */
.categories-tabs {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 40px;
}

.cat-tab {
    padding: 10px 28px;
    border-radius: 50px;
    border: 2px solid #E8E5E0;
    background: transparent;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s;
    color: #666;
    font-family: 'Jost', sans-serif;
}

.cat-tab:hover {
    border-color: var(--gold);
    color: var(--gold);
    transform: translateY(-2px);
}

.cat-tab.active {
    background: var(--gold);
    border-color: var(--gold);
    color: var(--white);
    box-shadow: 0 4px 15px rgba(200,146,42,0.3);
}

/* ===== GRILLE PLATS ===== */
.plats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
}

.plat-card {
    background: var(--white);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.4s;
    border: 1px solid #F0EDE8;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

.plat-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
    border-color: rgba(200,146,42,0.3);
}

.plat-image {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: #F5F3F0;
}

.plat-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s;
}

.plat-card:hover .plat-image img {
    transform: scale(1.08);
}

.plat-image .plat-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--dark);
    font-size: 0.55rem;
    font-weight: 700;
    padding: 4px 14px;
    border-radius: 30px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(200,146,42,0.3);
}

.plat-image .plat-category-badge {
    position: absolute;
    bottom: 15px;
    left: 15px;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(10px);
    color: #fff;
    font-size: 0.55rem;
    font-weight: 600;
    padding: 4px 14px;
    border-radius: 30px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.plat-info {
    padding: 20px;
}

.plat-info .plat-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 4px;
}

.plat-info .plat-desc {
    font-size: 0.8rem;
    color: var(--text-light);
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 12px;
}

.plat-info .plat-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.plat-info .plat-price {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gold);
}

.btn-plat-commander {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 20px;
    border-radius: 30px;
    background: var(--dark);
    color: var(--white);
    text-decoration: none;
    font-size: 0.7rem;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-plat-commander:hover {
    background: var(--gold);
    color: var(--dark);
    transform: translateY(-2px);
}

/* ===== SECTION GÂTEAUX ===== */
.section-gateaux {
    padding: 60px 0;
    background: var(--cream);
}

.gateaux-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
}

.gateau-card {
    background: var(--white);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid #F0EDE8;
    transition: all 0.4s;
}

.gateau-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.08);
}

.gateau-card .gateau-image {
    height: 220px;
    overflow: hidden;
    background: #F5F3F0;
}

.gateau-card .gateau-info {
    padding: 20px;
}

.gateau-card .gateau-info h4 {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    color: var(--dark);
}

.gateau-card .gateau-info p {
    color: var(--text-light);
    font-size: 0.85rem;
    margin: 5px 0 10px;
}

.gateau-card .event-type {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.event-anniv { background: #FFF3CD; color: #856404; }
.event-bac { background: #E8F4FD; color: #0C5460; }
.event-hadj { background: #E8F5E9; color: #155724; }
.event-mariage { background: #F3E5F5; color: #6A1B9A; }
.event-entreprise { background: #E3F2FD; color: #1565C0; }
.event-ramadan { background: #FEF3E2; color: #D35400; }

/* ===== SECTION SALLE DE JEUX ===== */
.section-jeux {
    padding: 60px 0;
    background: var(--white);
}

.jeux-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    align-items: center;
}

.jeux-features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin: 20px 0;
}

.jeux-feature {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    background: var(--cream);
    border-radius: 12px;
}

.jeux-feature i {
    color: var(--gold);
    font-size: 1.2rem;
}

.jeux-feature span {
    font-size: 0.85rem;
    color: var(--dark);
    font-weight: 500;
}

/* ===== SECTION RÉSERVATION ===== */
.section-reservation {
    padding: 80px 0;
    background: linear-gradient(135deg, var(--cream), #F0EDE8);
}

.reservation-card {
    max-width: 900px;
    margin: 0 auto;
    background: var(--white);
    border-radius: 24px;
    padding: 50px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.05);
    border: 1px solid rgba(200,146,42,0.08);
}

.reservation-card h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    color: var(--dark);
    margin-bottom: 10px;
}

.reservation-card h2 span {
    color: var(--gold);
}

.reservation-card p {
    color: var(--text-light);
    margin-bottom: 25px;
}

.reservation-features {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.reservation-feature {
    text-align: center;
    padding: 15px;
}

.reservation-feature i {
    font-size: 2rem;
    color: var(--gold);
    display: block;
    margin-bottom: 8px;
}

.reservation-feature h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--dark);
}

.reservation-feature p {
    font-size: 0.75rem;
    color: var(--text-light);
    margin: 0;
}

.btn-reserver {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--dark);
    padding: 14px 40px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.95rem;
    transition: all 0.3s;
    box-shadow: 0 8px 25px rgba(200,146,42,0.25);
}

.btn-reserver:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(200,146,42,0.4);
    color: var(--white);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1100px) {
    .plats-grid { grid-template-columns: repeat(3, 1fr); }
    .gateaux-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 850px) {
    .restaurant-nav { padding: 0 20px; height: 60px; }
    .restaurant-nav .nav-links { display: none; }
    .hero-restaurant h1 { font-size: 3rem; }
    .plats-grid { grid-template-columns: repeat(2, 1fr); }
    .jeux-content { grid-template-columns: 1fr; }
    .reservation-features { grid-template-columns: 1fr; }
    .jeux-features { grid-template-columns: 1fr; }
}

@media (max-width: 550px) {
    .hero-restaurant h1 { font-size: 2.2rem; }
    .plats-grid { grid-template-columns: 1fr; }
    .gateaux-grid { grid-template-columns: 1fr; }
    .menu-header h2 { font-size: 2rem; }
    .reservation-card { padding: 30px 20px; }
}
</style>

<!-- ===== NAVBAR RESTAURANT ===== -->
<nav class="restaurant-nav">
    <a href="#" class="nav-brand">Resto<span>Sofia</span></a>
    <ul class="nav-links">
        <!-- ✅ LIEN RETOUR ACCUEIL PRINCIPAL -->
        <li>
            <a href="<?= SITE_URL ?>/index.php" class="nav-back-home" title="Retour à l'accueil principal">
                <i class="bi bi-house-door"></i> Accueil
            </a>
        </li>
        <li><a href="#home" class="active">Accueil</a></li>
        <li><a href="#menu">Notre Menu</a></li>
        <li><a href="#gateaux">Gâteaux</a></li>
        <li><a href="#jeux">Jeux</a></li>
        <li><a href="#reservation" class="nav-cta">Réserver</a></li>
    </ul>
</nav>

<!-- ===== HERO SECTION ===== -->
<section class="hero-restaurant" id="home">
    <div class="bg-pattern"></div>
    <div class="hero-content">
        <div class="hero-badge">
            <i class="bi bi-cup-hot-fill"></i>
            Restaurant Sofia
        </div>
        <h1>L'Art de la <span>Table</span></h1>
        <div class="hero-subtitle">✦ Cuisine malienne & internationale ✦</div>
        <div class="hero-deco-line"></div>
        <p>
            Découvrez une expérience culinaire unique au cœur de Bamako.<br>
            Des saveurs authentiques, des plats généreux et un cadre chaleureux.
        </p>
        <div class="hero-actions">
            <a href="#menu" class="btn-hero primary">
                <i class="bi bi-menu-app"></i> Voir Notre Menu
            </a>
            <a href="#reservation" class="btn-hero outline">
                <i class="bi bi-calendar-check"></i> Réserver une table
            </a>
        </div>
    </div>
</section>

<!-- ===== SECTION MENU ===== -->
<section class="section-menu" id="menu">
    <div class="container">
        <div class="menu-header">
            <span class="menu-label">✦ Notre carte ✦</span>
            <h2>Notre <span>Menu</span></h2>
            <p>Des plats préparés avec passion et des ingrédients frais</p>
        </div>

        <div class="categories-tabs">
            <button class="cat-tab active" data-cat="all">🍽️ Tous</button>
            <?php foreach($categories as $cat): ?>
                <button class="cat-tab" data-cat="<?= $cat['id'] ?>">
                    <?= $cat['icone'] ?? '🍽️' ?> <?= htmlspecialchars($cat['nom']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="plats-grid" id="platsGrid">
            <?php foreach($plats as $plat): 
                $cat_nom = 'Plat';
                $cat_icone = '🍽️';
                $cat_id = 1;
                
                if (!empty($plat['categorie_id'])) {
                    foreach($categories as $c) {
                        if($c['id'] == $plat['categorie_id']) {
                            $cat_nom = $c['nom'];
                            $cat_icone = $c['icone'] ?? '🍽️';
                            $cat_id = $c['id'];
                            break;
                        }
                    }
                } else {
                    $nom_lower = strtolower($plat['nom']);
                    if (strpos($nom_lower, 'samoussa') !== false || strpos($nom_lower, 'beignet') !== false) {
                        $cat_nom = 'Entrées'; $cat_icone = '🥗'; $cat_id = 1;
                    } elseif (strpos($nom_lower, 'tieboudienne') !== false || strpos($nom_lower, 'yassa') !== false || strpos($nom_lower, 'mafé') !== false) {
                        $cat_nom = 'Plats principaux'; $cat_icone = '🍛'; $cat_id = 2;
                    } elseif (strpos($nom_lower, 'bissap') !== false || strpos($nom_lower, 'jus') !== false || strpos($nom_lower, 'thé') !== false) {
                        $cat_nom = 'Boissons'; $cat_icone = '🥤'; $cat_id = 4;
                    } elseif (strpos($nom_lower, 'gateau') !== false || strpos($nom_lower, 'tarte') !== false) {
                        $cat_nom = 'Desserts'; $cat_icone = '🍰'; $cat_id = 3;
                    }
                }
            ?>
            <div class="plat-card" data-cat="<?= $cat_id ?>">
                <div class="plat-image">
                    <?php 
                    $img = !empty($plat['image']) ? '../uploads/plats/' . $plat['image'] : 'https://placehold.co/400x250/F5F3F0/C8922A?text=' . urlencode($plat['nom']);
                    ?>
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($plat['nom']) ?>" 
                         onerror="this.src='https://placehold.co/400x250/F5F3F0/C8922A?text=<?= urlencode($plat['nom']) ?>'">
                    <?php if($plat['est_plat_du_jour']): ?>
                        <span class="plat-badge">⭐ Plat du jour</span>
                    <?php endif; ?>
                    <span class="plat-category-badge"><?= $cat_icone ?> <?= htmlspecialchars($cat_nom) ?></span>
                </div>
                <div class="plat-info">
                    <div class="plat-name"><?= htmlspecialchars($plat['nom']) ?></div>
                    <div class="plat-desc"><?= htmlspecialchars(substr($plat['description'] ?? '', 0, 60)) ?>...</div>
                    <div class="plat-bottom">
                        <span class="plat-price"><?= number_format($plat['prix'], 0, ',', ' ') ?> FCFA</span>
                        <a href="commande_repas.php?plat_id=<?= $plat['id'] ?>" class="btn-plat-commander">
                            <i class="bi bi-cart-plus"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== SECTION GÂTEAUX ===== -->
<section class="section-gateaux" id="gateaux">
    <div class="container">
        <div class="menu-header">
            <span class="menu-label">🎂 Sur mesure</span>
            <h2>Gâteaux & <span>Événements</span></h2>
            <p>Des créations uniques pour toutes vos célébrations</p>
        </div>

        <div class="gateaux-grid">
            <div class="gateau-card">
                <div class="gateau-image">
                    <img src="https://placehold.co/400x250/F5F3F0/C8922A?text=🎂+Anniversaire" alt="Gâteau anniversaire">
                </div>
                <div class="gateau-info">
                    <h4>Gâteaux d'anniversaire</h4>
                    <p>Sur mesure pour vos anniversaires</p>
                    <span class="event-type event-anniv">🎂 Anniversaire</span>
                </div>
            </div>
            <div class="gateau-card">
                <div class="gateau-image">
                    <img src="https://placehold.co/400x250/F5F3F0/C8922A?text=🎓+BAC" alt="Gâteau BAC">
                </div>
                <div class="gateau-info">
                    <h4>Gâteaux Fête de BAC</h4>
                    <p>Pour célébrer la réussite</p>
                    <span class="event-type event-bac">🎓 BAC</span>
                </div>
            </div>
            <div class="gateau-card">
                <div class="gateau-image">
                    <img src="https://placehold.co/400x250/F5F3F0/C8922A?text=🕋+Hadji" alt="Gâteau Hadji">
                </div>
                <div class="gateau-info">
                    <h4>Gâteaux Fête de Hadji</h4>
                    <p>Spécial Tabaski et Ramadan</p>
                    <span class="event-type event-hadj">🕋 Hadji</span>
                </div>
            </div>
            <div class="gateau-card">
                <div class="gateau-image">
                    <img src="https://placehold.co/400x250/F5F3F0/C8922A?text=💍+Mariage" alt="Gâteau mariage">
                </div>
                <div class="gateau-info">
                    <h4>Gâteaux de Mariage</h4>
                    <p>Pour le plus beau jour</p>
                    <span class="event-type event-mariage">💍 Mariage</span>
                </div>
            </div>
            <div class="gateau-card">
                <div class="gateau-image">
                    <img src="https://placehold.co/400x250/F5F3F0/C8922A?text=🏢+Entreprise" alt="Gâteau entreprise">
                </div>
                <div class="gateau-info">
                    <h4>Gâteaux d'Entreprise</h4>
                    <p>Pour vos événements pro</p>
                    <span class="event-type event-entreprise">🏢 Entreprise</span>
                </div>
            </div>
            <div class="gateau-card">
                <div class="gateau-image">
                    <img src="https://placehold.co/400x250/F5F3F0/C8922A?text=🌙+Ramadan" alt="Gâteau Ramadan">
                </div>
                <div class="gateau-info">
                    <h4>Spécial Ramadan</h4>
                    <p>Douceurs pour le mois sacré</p>
                    <span class="event-type event-ramadan">🌙 Ramadan</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== SECTION SALLE DE JEUX ===== -->
<section class="section-jeux" id="jeux">
    <div class="container">
        <div class="jeux-content">
            <div class="jeux-info">
                <span class="menu-label" style="display:inline-block;background:rgba(200,146,42,0.08);color:var(--gold);padding:4px 18px;border-radius:30px;font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:2px;margin-bottom:10px;">🎮 Divertissement</span>
                <h2>Espace <span>Jeux</span> Sofia</h2>
                <p>
                    Détendez-vous et amusez-vous dans notre espace dédié aux jeux ! 
                    Parfait pour les familles, les groupes d'amis et les événements.
                </p>
                <div class="jeux-features">
                    <div class="jeux-feature"><i class="bi bi-joystick"></i><span>Jeux de société</span></div>
                    <div class="jeux-feature"><i class="bi bi-tv"></i><span>Jeux vidéo</span></div>
                    <div class="jeux-feature"><i class="bi bi-dice-6"></i><span>Jeux de cartes</span></div>
                    <div class="jeux-feature"><i class="bi bi-trophy"></i><span>Tournois</span></div>
                    <div class="jeux-feature"><i class="bi bi-people"></i><span>Espace enfants</span></div>
                    <div class="jeux-feature"><i class="bi bi-calendar-event"></i><span>Événements privés</span></div>
                </div>
                <a href="#" class="btn-hero primary" style="margin-top:15px;">
                    <i class="bi bi-controller"></i> Découvrir l'espace jeux
                </a>
            </div>
            <div style="text-align:center;padding:40px;background:#F5F3F0;border-radius:20px;min-height:300px;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                <i class="bi bi-controller" style="font-size:5rem;color:var(--gold);opacity:0.3;"></i>
                <p style="color:var(--text-light);font-size:0.9rem;margin-top:15px;">Espace jeux - Photo à venir</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== RÉSERVATION ===== -->
<section class="section-reservation" id="reservation">
    <div class="container">
        <div class="reservation-card">
            <h2>📅 Réservez votre <span>table</span></h2>
            <p>Offrez-vous un moment d'exception au Restaurant Sofia.</p>

            <div class="reservation-features">
                <div class="reservation-feature">
                    <i class="bi bi-clock"></i>
                    <h4>Horaires</h4>
                    <p>Lun - Sam : 8h - 21h</p>
                </div>
                <div class="reservation-feature">
                    <i class="bi bi-people"></i>
                    <h4>Groupes</h4>
                    <p>Jusqu'à 50 personnes</p>
                </div>
                <div class="reservation-feature">
                    <i class="bi bi-tag"></i>
                    <h4>Événements</h4>
                    <p>Sur mesure</p>
                </div>
            </div>

            <a href="reservation.php" class="btn-reserver">
                <i class="bi bi-calendar-check"></i> Réserver maintenant
            </a>
        </div>
    </div>
</section>

<script>
// Filtrage des plats par catégorie
document.querySelectorAll('.cat-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const cat = this.dataset.cat;
        document.querySelectorAll('.plat-card').forEach(card => {
            if (cat === 'all' || card.dataset.cat == cat) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

// Navigation douce
document.querySelectorAll('.restaurant-nav .nav-links a:not(.nav-back-home):not(.nav-cta)').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        document.querySelectorAll('.restaurant-nav .nav-links a:not(.nav-back-home)').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
    });
});

// Animation au scroll
const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

document.querySelectorAll('.plat-card, .gateau-card').forEach((card, index) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px)';
    card.style.transition = `all 0.5s ease ${index * 0.05}s`;
    observer.observe(card);
});
</script>

<?php require_once '../includes/footer.php'; ?>