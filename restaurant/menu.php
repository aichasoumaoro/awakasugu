<?php
// ============================================
// RESTAURANT SOFIA - Site Vitrine Premium
// Version 3.0 - Design Luxe
// ============================================

$titre_page = 'Restaurant Sofia - Cuisine d\'Exception';
$meta_desc  = 'Restaurant Sofia à Bamako : cuisine malienne, européenne, pâtisserie et événements. Découvrez notre menu et réservez votre table.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// ===== CONNEXION BDD =====
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

// ===== RÉCUPÉRATION DES DONNÉES =====
// Catégories
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

// Plats
$plats = [];
try {
    $plats = $pdo->query("
        SELECT * FROM plats 
        WHERE est_visible = 1 
        ORDER BY est_plat_du_jour DESC, created_at DESC
    ")->fetchAll();
} catch(PDOException $e) {
    $plats = [];
}

// Plat du jour
$plat_jour = null;
try {
    $plat_jour = $pdo->query("
        SELECT * FROM plats 
        WHERE est_plat_du_jour = 1 AND est_visible = 1 
        LIMIT 1
    ")->fetch();
} catch(PDOException $e) {
    $plat_jour = null;
}

if (!$plat_jour && !empty($plats)) {
    $plat_jour = $plats[0];
}

// ===== FONCTION POUR DÉTERMINER LA CATÉGORIE D'UN PLAT =====
function getPlatCategorie($plat, $categories) {
    if (!empty($plat['categorie_id'])) {
        foreach($categories as $c) {
            if($c['id'] == $plat['categorie_id']) {
                return ['id' => $c['id'], 'nom' => $c['nom'], 'icone' => $c['icone'] ?? '🍽️'];
            }
        }
    }
    
    $nom_lower = strtolower($plat['nom']);
    
    if (strpos($nom_lower, 'samoussa') !== false || strpos($nom_lower, 'beignet') !== false) {
        return ['id' => 1, 'nom' => 'Entrées', 'icone' => '🥗'];
    }
    if (strpos($nom_lower, 'tieboudienne') !== false || strpos($nom_lower, 'yassa') !== false || 
        strpos($nom_lower, 'mafé') !== false || strpos($nom_lower, 'riz') !== false) {
        return ['id' => 2, 'nom' => 'Plats principaux', 'icone' => '🍛'];
    }
    if (strpos($nom_lower, 'bissap') !== false || strpos($nom_lower, 'jus') !== false || 
        strpos($nom_lower, 'thé') !== false) {
        return ['id' => 4, 'nom' => 'Boissons', 'icone' => '🥤'];
    }
    if (strpos($nom_lower, 'gateau') !== false || strpos($nom_lower, 'tarte') !== false) {
        return ['id' => 3, 'nom' => 'Desserts', 'icone' => '🍰'];
    }
    
    return ['id' => 1, 'nom' => 'Plat', 'icone' => '🍽️'];
}
?>

<style>
/* ============================================
   RESTAURANT SOFIA - DESIGN VITRINE PREMIUM
   ============================================ */

@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&family=Inter:wght@300;400;500;600;700;800;900&family=Great+Vibes&display=swap');

/* ===== VARIABLES ===== */
:root {
    --gold: #C8922A;
    --gold-light: #E8B55A;
    --gold-dark: #9A6E1A;
    --gold-gradient: linear-gradient(135deg, #C8922A, #E8B55A);
    --gold-gradient-hover: linear-gradient(135deg, #E8B55A, #C8922A);
    --dark: #0D0B08;
    --dark-light: #1A1612;
    --cream: #FBF8F3;
    --white: #FFFFFF;
    --gray: #6B6B6B;
    --gray-light: #E8E5E0;
    --shadow: 0 20px 60px rgba(0,0,0,0.08);
    --shadow-hover: 0 30px 80px rgba(0,0,0,0.15);
    --shadow-gold: 0 20px 60px rgba(200,146,42,0.25);
    --radius: 20px;
    --transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* ===== RESET & BASE ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--cream);
    color: var(--dark);
    overflow-x: hidden;
}

/* ===== SCROLLBAR ===== */
::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: var(--cream); }
::-webkit-scrollbar-thumb { background: var(--gold-gradient); border-radius: 10px; }

/* ===== NAVBAR ===== */
.restaurant-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: rgba(13, 11, 8, 0.97);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(200,146,42,0.08);
    padding: 0 40px;
    height: 72px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: var(--transition);
}

.restaurant-nav.scrolled {
    box-shadow: 0 4px 30px rgba(0,0,0,0.4);
}

.restaurant-nav .brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
}

.restaurant-nav .brand-icon {
    width: 40px;
    height: 40px;
    background: var(--gold-gradient);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 1.2rem;
    font-weight: 700;
    box-shadow: 0 4px 15px rgba(200,146,42,0.3);
}

.restaurant-nav .brand-text {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--white);
    letter-spacing: -0.5px;
}

.restaurant-nav .brand-text span {
    color: var(--gold);
}

.restaurant-nav .nav-links {
    display: flex;
    align-items: center;
    gap: 35px;
    list-style: none;
}

.restaurant-nav .nav-links a {
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
    transition: var(--transition);
    position: relative;
}

.restaurant-nav .nav-links a::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--gold-gradient);
    transition: var(--transition);
    border-radius: 2px;
}

.restaurant-nav .nav-links a:hover::after,
.restaurant-nav .nav-links a.active::after {
    width: 100%;
}

.restaurant-nav .nav-links a:hover,
.restaurant-nav .nav-links a.active {
    color: var(--gold);
}

.restaurant-nav .nav-cta {
    padding: 10px 28px !important;
    background: var(--gold-gradient) !important;
    color: var(--white) !important;
    border-radius: 30px !important;
    font-weight: 700 !important;
    letter-spacing: 1px !important;
    box-shadow: 0 4px 20px rgba(200,146,42,0.25);
}

.restaurant-nav .nav-cta::after {
    display: none !important;
}

.restaurant-nav .nav-cta:hover {
    transform: translateY(-3px) !important;
    box-shadow: 0 8px 35px rgba(200,146,42,0.4) !important;
}

.restaurant-nav .nav-back-home {
    display: inline-flex !important;
    align-items: center;
    gap: 8px;
    color: rgba(255,255,255,0.2) !important;
    font-size: 0.6rem !important;
    border-right: 1px solid rgba(255,255,255,0.06);
    padding-right: 25px;
    font-weight: 500 !important;
}

.restaurant-nav .nav-back-home:hover {
    color: var(--gold) !important;
}

.restaurant-nav .nav-back-home i {
    font-size: 0.9rem;
}

/* ===== MOBILE MENU ===== */
.mobile-toggle {
    display: none;
    background: none;
    border: none;
    color: var(--white);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 8px;
    transition: var(--transition);
}

.mobile-toggle:hover {
    color: var(--gold);
}

/* ===== HERO SECTION ===== */
.hero {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(165deg, #0D0B08 0%, #1A1612 40%, #0D0B08 100%);
    padding: 120px 24px 80px;
    margin-top: 72px;
    overflow: hidden;
}

.hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(ellipse at 20% 50%, rgba(200,146,42,0.08) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 50%, rgba(200,146,42,0.04) 0%, transparent 60%);
    pointer-events: none;
}

.hero-particles {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
}

.hero-particles .particle {
    position: absolute;
    width: 3px;
    height: 3px;
    background: var(--gold);
    border-radius: 50%;
    opacity: 0.15;
}

.hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 900px;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(200,146,42,0.12);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(200,146,42,0.15);
    padding: 10px 28px;
    border-radius: 50px;
    margin-bottom: 30px;
    color: var(--gold);
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.hero-badge i {
    font-size: 1rem;
}

.hero h1 {
    font-family: 'Playfair Display', serif;
    font-size: 6rem;
    font-weight: 800;
    color: var(--white);
    line-height: 1.05;
    margin-bottom: 8px;
}

.hero h1 span {
    background: var(--gold-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    font-size: 1.1rem;
    color: rgba(255,255,255,0.2);
    letter-spacing: 10px;
    text-transform: uppercase;
    font-weight: 300;
    margin-bottom: 20px;
}

.hero-deco {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin: 30px auto;
}

.hero-deco .line {
    width: 80px;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold));
}

.hero-deco .diamond {
    width: 10px;
    height: 10px;
    background: var(--gold-gradient);
    transform: rotate(45deg);
    flex-shrink: 0;
    box-shadow: 0 0 20px rgba(200,146,42,0.3);
}

.hero p {
    color: rgba(255,255,255,0.4);
    font-size: 1.1rem;
    line-height: 2;
    max-width: 550px;
    margin: 0 auto 40px;
}

.hero-actions {
    display: flex;
    justify-content: center;
    gap: 18px;
    flex-wrap: wrap;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 16px 40px;
    background: var(--gold-gradient);
    color: var(--white);
    border: none;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.9rem;
    text-decoration: none;
    transition: var(--transition);
    box-shadow: 0 8px 35px rgba(200,146,42,0.3);
    cursor: pointer;
    letter-spacing: 0.5px;
}

.btn-primary:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 50px rgba(200,146,42,0.45);
    color: var(--white);
}

.btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 14px 38px;
    border: 2px solid rgba(200,146,42,0.4);
    color: var(--gold);
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: var(--transition);
    background: transparent;
    cursor: pointer;
    letter-spacing: 0.5px;
}

.btn-outline:hover {
    background: rgba(200,146,42,0.1);
    border-color: var(--gold);
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(200,146,42,0.15);
}

.hero-stats {
    display: flex;
    justify-content: center;
    gap: 70px;
    margin-top: 60px;
}

.hero-stat {
    text-align: center;
}

.hero-stat .number {
    font-family: 'Playfair Display', serif;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--white);
    display: block;
    background: var(--gold-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-stat .label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-top: 4px;
}

/* ===== SECTION MENU ===== */
.section-menu {
    padding: 100px 0;
    background: var(--white);
}

.container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
}

.menu-header {
    text-align: center;
    margin-bottom: 60px;
}

.section-label {
    display: inline-block;
    background: rgba(200,146,42,0.08);
    color: var(--gold);
    padding: 8px 24px;
    border-radius: 50px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 3px;
    margin-bottom: 15px;
}

.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 3.5rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 10px;
}

.section-title span {
    color: var(--gold);
}

.section-subtitle {
    color: var(--gray);
    font-size: 1.1rem;
    font-weight: 300;
    max-width: 600px;
    margin: 0 auto;
}

/* ===== CATÉGORIES TABS ===== */
.categories-tabs {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 50px;
}

.cat-tab {
    padding: 12px 30px;
    border-radius: 50px;
    border: 2px solid var(--gray-light);
    background: transparent;
    font-weight: 600;
    font-size: 0.75rem;
    cursor: pointer;
    transition: var(--transition);
    color: var(--gray);
    font-family: 'Inter', sans-serif;
}

.cat-tab:hover {
    border-color: var(--gold);
    color: var(--gold);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(200,146,42,0.1);
}

.cat-tab.active {
    background: var(--gold-gradient);
    border-color: transparent;
    color: var(--white);
    box-shadow: 0 8px 30px rgba(200,146,42,0.3);
    transform: translateY(-3px);
}

/* ===== GRILLE PLATS - DESIGN VITRINE ===== */
.plats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
}

.plat-card {
    background: var(--white);
    border-radius: var(--radius);
    overflow: hidden;
    transition: var(--transition);
    border: 1px solid var(--gray-light);
    box-shadow: var(--shadow);
    position: relative;
}

.plat-card:hover {
    transform: translateY(-15px);
    box-shadow: var(--shadow-hover);
    border-color: rgba(200,146,42,0.2);
}

.plat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gold-gradient);
    opacity: 0;
    transition: var(--transition);
}

.plat-card:hover::before {
    opacity: 1;
}

/* === IMAGE DU PLAT EN HAUT === */
.plat-image {
    position: relative;
    height: 260px;
    overflow: hidden;
    background: linear-gradient(135deg, #f5f3f0, #ebe7e0);
}

.plat-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: var(--transition);
}

.plat-card:hover .plat-image img {
    transform: scale(1.1);
}

.plat-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: var(--gold-gradient);
    color: var(--white);
    font-size: 0.5rem;
    font-weight: 700;
    padding: 6px 18px;
    border-radius: 30px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 20px rgba(200,146,42,0.4);
}

.plat-category-badge {
    position: absolute;
    bottom: 15px;
    left: 15px;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(10px);
    color: var(--white);
    font-size: 0.5rem;
    font-weight: 600;
    padding: 5px 16px;
    border-radius: 30px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* === INFOS DU PLAT === */
.plat-info {
    padding: 22px 24px 24px;
    background: var(--white);
}

.plat-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
}

.plat-desc {
    font-size: 0.8rem;
    color: var(--gray);
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 16px;
    min-height: 44px;
}

.plat-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid var(--gray-light);
}

.plat-price {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gold);
}

.btn-commander {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: 30px;
    background: var(--dark);
    color: var(--white);
    text-decoration: none;
    font-size: 0.65rem;
    font-weight: 600;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-commander:hover {
    background: var(--gold-gradient);
    color: var(--white);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(200,146,42,0.3);
}

/* ===== SECTION GÂTEAUX ÉVÉNEMENTS ===== */
.section-gateaux {
    padding: 100px 0;
    background: var(--cream);
}

.gateaux-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
}

.gateau-card {
    background: var(--white);
    border-radius: var(--radius);
    overflow: hidden;
    transition: var(--transition);
    border: 1px solid var(--gray-light);
    box-shadow: var(--shadow);
    position: relative;
}

.gateau-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gold-gradient);
    opacity: 0;
    transition: var(--transition);
}

.gateau-card:hover::before {
    opacity: 1;
}

.gateau-card:hover {
    transform: translateY(-12px);
    box-shadow: var(--shadow-hover);
}

.gateau-image {
    height: 240px;
    overflow: hidden;
    background: linear-gradient(135deg, #f5f3f0, #ebe7e0);
}

.gateau-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: var(--transition);
}

.gateau-card:hover .gateau-image img {
    transform: scale(1.08);
}

.gateau-info {
    padding: 22px 24px 24px;
}

.gateau-info h4 {
    font-family: 'Playfair Display', serif;
    font-size: 1.15rem;
    color: var(--dark);
    font-weight: 700;
}

.gateau-info p {
    color: var(--gray);
    font-size: 0.85rem;
    margin: 6px 0 14px;
}

.event-tag {
    display: inline-block;
    padding: 4px 16px;
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

/* ===== COMMANDE GÂTEAU ÉVÉNEMENT ===== */
.section-commande-gateau {
    padding: 80px 0;
    background: var(--white);
}

.commande-gateau-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.commande-gateau-info h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2.8rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 15px;
}

.commande-gateau-info h2 span {
    color: var(--gold);
}

.commande-gateau-info p {
    color: var(--gray);
    font-size: 1.05rem;
    line-height: 1.8;
    margin-bottom: 25px;
}

.commande-gateau-form {
    background: var(--cream);
    padding: 40px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-light);
}

.commande-gateau-form .form-group {
    margin-bottom: 20px;
}

.commande-gateau-form label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--dark);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.commande-gateau-form input,
.commande-gateau-form select,
.commande-gateau-form textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--gray-light);
    border-radius: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 0.9rem;
    transition: var(--transition);
    background: var(--white);
}

.commande-gateau-form input:focus,
.commande-gateau-form select:focus,
.commande-gateau-form textarea:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 4px rgba(200,146,42,0.1);
}

.commande-gateau-form textarea {
    min-height: 100px;
    resize: vertical;
}

/* ===== SECTION JEUX ===== */
.section-jeux {
    padding: 100px 0;
    background: linear-gradient(135deg, var(--cream), #f0ebe5);
}

.jeux-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.jeux-info .section-title {
    font-size: 3rem;
}

.jeux-info p {
    color: var(--gray);
    font-size: 1.05rem;
    line-height: 1.8;
    margin: 15px 0 25px;
}

.jeux-features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin: 25px 0 30px;
}

.jeux-feature {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 20px;
    background: var(--white);
    border-radius: 14px;
    transition: var(--transition);
    box-shadow: var(--shadow);
    cursor: default;
    border: 1px solid var(--gray-light);
}

.jeux-feature:hover {
    transform: translateX(6px);
    border-color: var(--gold);
    box-shadow: var(--shadow-hover);
}

.jeux-feature i {
    color: var(--gold);
    font-size: 1.3rem;
    width: 30px;
    text-align: center;
}

.jeux-feature span {
    font-size: 0.85rem;
    color: var(--dark);
    font-weight: 500;
}

.jeux-visual {
    background: linear-gradient(135deg, var(--dark), var(--dark-light));
    border-radius: var(--radius);
    padding: 60px 40px;
    text-align: center;
    border: 2px solid rgba(200,146,42,0.1);
    position: relative;
    overflow: hidden;
}

.jeux-visual::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, rgba(200,146,42,0.05), transparent 70%);
}

.jeux-visual i {
    font-size: 5rem;
    color: var(--gold);
    opacity: 0.3;
    position: relative;
    z-index: 1;
}

.jeux-visual p {
    color: rgba(255,255,255,0.3);
    margin-top: 15px;
    font-size: 0.9rem;
    position: relative;
    z-index: 1;
}

/* ===== SECTION RÉSERVATION ===== */
.section-reservation {
    padding: 100px 0;
    background: var(--white);
}

.reservation-card {
    max-width: 900px;
    margin: 0 auto;
    background: var(--cream);
    border-radius: var(--radius);
    padding: 60px 70px;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid rgba(200,146,42,0.06);
}

.reservation-card .section-title {
    font-size: 3rem;
}

.reservation-features {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
    margin: 35px 0 40px;
}

.reservation-feature {
    text-align: center;
    padding: 25px;
    border-radius: 16px;
    transition: var(--transition);
    background: var(--white);
    border: 1px solid var(--gray-light);
}

.reservation-feature:hover {
    transform: translateY(-5px);
    border-color: var(--gold);
    box-shadow: var(--shadow-gold);
}

.reservation-feature i {
    font-size: 2.5rem;
    color: var(--gold);
    display: block;
    margin-bottom: 12px;
}

.reservation-feature h4 {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--dark);
}

.reservation-feature p {
    font-size: 0.8rem;
    color: var(--gray);
    margin: 0;
}

/* ===== ANIMATIONS ===== */
@keyframes float1 {
    0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.15; }
    50% { transform: translate(40px, -40px) rotate(180deg); opacity: 0.5; }
}

@keyframes float2 {
    0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.15; }
    50% { transform: translate(-40px, -50px) rotate(-180deg); opacity: 0.5; }
}

@keyframes float3 {
    0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.15; }
    50% { transform: translate(30px, -60px) rotate(120deg); opacity: 0.5; }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1200px) {
    .plats-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 992px) {
    .gateaux-grid { grid-template-columns: repeat(2, 1fr); }
    .jeux-content { grid-template-columns: 1fr; gap: 40px; }
    .commande-gateau-content { grid-template-columns: 1fr; gap: 40px; }
    .hero h1 { font-size: 4.5rem; }
    .section-title { font-size: 2.8rem; }
    .reservation-card { padding: 40px 30px; }
}

@media (max-width: 768px) {
    .restaurant-nav { padding: 0 20px; height: 64px; }
    .restaurant-nav .nav-links { display: none; }
    .mobile-toggle { display: block; }
    
    .hero { padding: 100px 20px 60px; margin-top: 64px; }
    .hero h1 { font-size: 3rem; }
    .hero-stats { gap: 30px; flex-wrap: wrap; }
    .hero-stat .number { font-size: 2rem; }
    
    .plats-grid { grid-template-columns: repeat(2, 1fr); }
    .section-title { font-size: 2.2rem; }
    .reservation-features { grid-template-columns: 1fr; }
    .jeux-features { grid-template-columns: 1fr; }
    .commande-gateau-form { padding: 25px; }
    
    .hero-actions { flex-direction: column; align-items: center; }
    .hero-actions .btn-primary,
    .hero-actions .btn-outline { width: 100%; justify-content: center; }
}

@media (max-width: 480px) {
    .plats-grid { grid-template-columns: 1fr; }
    .gateaux-grid { grid-template-columns: 1fr; }
    .hero h1 { font-size: 2.4rem; }
    .hero-subtitle { font-size: 0.7rem; letter-spacing: 4px; }
    .categories-tabs { gap: 6px; }
    .cat-tab { padding: 8px 16px; font-size: 0.65rem; }
    .section-title { font-size: 1.8rem; }
    .reservation-card { padding: 25px 16px; }
    .commande-gateau-info h2 { font-size: 2rem; }
}
</style>

<!-- ============================================
     NAVBAR
     ============================================ -->
<nav class="restaurant-nav" id="mainNav">
    <a href="#" class="brand">
        <div class="brand-icon">🍽️</div>
        <div class="brand-text">Resto<span>Sofia</span></div>
    </a>
    
    <ul class="nav-links">
        <li>
            <a href="<?= SITE_URL ?? '/' ?>/index.php" class="nav-back-home" title="Retour à l'accueil principal">
                <i class="bi bi-house-door"></i> ACCUEIL PRINCIPAL
            </a>
        </li>
        <li><a href="#hero" class="active">ACCUEIL</a></li>
        <li><a href="#menu">MENU</a></li>
        <li><a href="#gateaux">GÂTEAUX</a></li>
        <li><a href="#jeux">JEUX</a></li>
        <li><a href="#reservation" class="nav-cta">RÉSERVER</a></li>
    </ul>
    
    <button class="mobile-toggle" id="mobileToggle" aria-label="Menu">
        <i class="bi bi-list"></i>
    </button>
</nav>

<!-- ============================================
     HERO SECTION
     ============================================ -->
<section class="hero" id="hero">
    <div class="hero-particles">
        <?php for($i = 0; $i < 30; $i++): 
            $size = rand(2, 5);
            $top = rand(5, 95);
            $left = rand(5, 95);
            $anim = 'float' . rand(1, 3);
            $duration = rand(12, 25);
            $delay = rand(0, 10);
        ?>
        <div class="particle" style="
            top: <?= $top ?>%;
            left: <?= $left ?>%;
            width: <?= $size ?>px;
            height: <?= $size ?>px;
            animation: <?= $anim ?> <?= $duration ?>s infinite;
            animation-delay: <?= $delay ?>s;
            opacity: <?= rand(10, 25) / 100 ?>;
        "></div>
        <?php endfor; ?>
    </div>
    
    <div class="hero-content">
        <div class="hero-badge">
            <i class="bi bi-cup-hot-fill"></i>
            Restaurant Sofia
        </div>
        
        <h1>L'Art de la <span>Table</span></h1>
        <div class="hero-subtitle">✦ Cuisine malienne & internationale ✦</div>
        
        <div class="hero-deco">
            <span class="line"></span>
            <span class="diamond"></span>
            <span class="line"></span>
        </div>
        
        <p>
            Découvrez une expérience culinaire unique au cœur de Bamako.<br>
            Des saveurs authentiques, des plats généreux et un cadre chaleureux.
        </p>
        
        <div class="hero-actions">
            <a href="#menu" class="btn-primary">
                <i class="bi bi-menu-app"></i> Voir Notre Menu
            </a>
            <a href="#reservation" class="btn-outline">
                <i class="bi bi-calendar-check"></i> Réserver une table
            </a>
        </div>
        
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="number">120+</span>
                <span class="label">Plats proposés</span>
            </div>
            <div class="hero-stat">
                <span class="number">8</span>
                <span class="label">Années d'excellence</span>
            </div>
            <div class="hero-stat">
                <span class="number">4.9★</span>
                <span class="label">Avis clients</span>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     SECTION MENU
     ============================================ -->
<section class="section-menu" id="menu">
    <div class="container">
        <div class="menu-header">
            <span class="section-label">✦ Notre carte ✦</span>
            <h2 class="section-title">Notre <span>Menu</span></h2>
            <p class="section-subtitle">Des plats préparés avec passion et des ingrédients frais</p>
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
            <?php if(empty($plats)): ?>
                <p style="grid-column: 1 / -1; text-align: center; color: var(--gray); padding: 40px 0;">
                    Aucun plat disponible pour le moment.
                </p>
            <?php else: ?>
                <?php foreach($plats as $plat): 
                    $categorie = getPlatCategorie($plat, $categories);
                ?>
                <div class="plat-card" data-cat="<?= $categorie['id'] ?>">
                    <!-- PHOTO EN HAUT -->
                    <div class="plat-image">
                        <?php 
                        $img = !empty($plat['image']) ? '../uploads/plats/' . $plat['image'] : 'https://placehold.co/400x260/f5f3f0/C8922A?text=' . urlencode($plat['nom']);
                        ?>
                        <img src="<?= $img ?>" alt="<?= htmlspecialchars($plat['nom']) ?>" 
                             loading="lazy"
                             onerror="this.src='https://placehold.co/400x260/f5f3f0/C8922A?text=<?= urlencode($plat['nom']) ?>'">
                        
                        <?php if($plat['est_plat_du_jour']): ?>
                            <span class="plat-badge">⭐ Plat du jour</span>
                        <?php endif; ?>
                        
                        <span class="plat-category-badge">
                            <?= $categorie['icone'] ?> <?= htmlspecialchars($categorie['nom']) ?>
                        </span>
                    </div>
                    
                    <!-- NOM DU PLAT -->
                    <div class="plat-info">
                        <div class="plat-name"><?= htmlspecialchars($plat['nom']) ?></div>
                        <div class="plat-desc">
                            <?= htmlspecialchars(substr($plat['description'] ?? 'Un délice à découvrir', 0, 60)) ?>...
                        </div>
                        
                        <!-- PRIX EN BAS -->
                        <div class="plat-bottom">
                            <span class="plat-price"><?= number_format($plat['prix'], 0, ',', ' ') ?> FCFA</span>
                            <a href="commande_repas.php?plat_id=<?= $plat['id'] ?>" class="btn-commander">
                                <i class="bi bi-cart-plus"></i> Commander
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================================
     SECTION GÂTEAUX ÉVÉNEMENTS
     ============================================ -->
<section class="section-gateaux" id="gateaux">
    <div class="container">
        <div class="menu-header">
            <span class="section-label">🎂 Sur mesure</span>
            <h2 class="section-title">Gâteaux & <span>Événements</span></h2>
            <p class="section-subtitle">Des créations uniques pour toutes vos célébrations</p>
        </div>

        <div class="gateaux-grid">
            <?php 
            $gateaux = [
                ['nom' => 'Gâteaux d\'anniversaire', 'desc' => 'Sur mesure pour vos anniversaires', 'tag' => 'Anniversaire', 'class' => 'event-anniv', 'icon' => '🎂'],
                ['nom' => 'Gâteaux Fête de BAC', 'desc' => 'Pour célébrer la réussite', 'tag' => 'BAC', 'class' => 'event-bac', 'icon' => '🎓'],
                ['nom' => 'Gâteaux Fête de Hadji', 'desc' => 'Spécial Tabaski et Ramadan', 'tag' => 'Hadji', 'class' => 'event-hadj', 'icon' => '🕋'],
                ['nom' => 'Gâteaux de Mariage', 'desc' => 'Pour le plus beau jour', 'tag' => 'Mariage', 'class' => 'event-mariage', 'icon' => '💍'],
                ['nom' => 'Gâteaux d\'Entreprise', 'desc' => 'Pour vos événements pro', 'tag' => 'Entreprise', 'class' => 'event-entreprise', 'icon' => '🏢'],
                ['nom' => 'Spécial Ramadan', 'desc' => 'Douceurs pour le mois sacré', 'tag' => 'Ramadan', 'class' => 'event-ramadan', 'icon' => '🌙'],
            ];
            
            foreach($gateaux as $g): ?>
            <div class="gateau-card">
                <div class="gateau-image">
                    <img src="https://placehold.co/400x260/f5f3f0/C8922A?text=<?= $g['icon'] ?>+<?= urlencode($g['tag']) ?>" 
                         alt="<?= $g['nom'] ?>" loading="lazy">
                </div>
                <div class="gateau-info">
                    <h4><?= $g['nom'] ?></h4>
                    <p><?= $g['desc'] ?></p>
                    <span class="event-tag <?= $g['class'] ?>"><?= $g['icon'] ?> <?= $g['tag'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================
     SECTION COMMANDE GÂTEAU ÉVÉNEMENT
     ============================================ -->
<section class="section-commande-gateau" id="commande-gateau">
    <div class="container">
        <div class="commande-gateau-content">
            <div class="commande-gateau-info">
                <span class="section-label">🎯 Personnalisé</span>
                <h2>Spécifiez votre <span>Événement</span></h2>
                <p>
                    Vous avez un événement spécial ? Commandez votre gâteau sur mesure 
                    et faites de votre célébration un moment inoubliable.
                </p>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <span style="background: var(--cream); padding: 8px 18px; border-radius: 30px; font-size: 0.8rem; border: 1px solid var(--gray-light);">🎂 Anniversaire</span>
                    <span style="background: var(--cream); padding: 8px 18px; border-radius: 30px; font-size: 0.8rem; border: 1px solid var(--gray-light);">💍 Mariage</span>
                    <span style="background: var(--cream); padding: 8px 18px; border-radius: 30px; font-size: 0.8rem; border: 1px solid var(--gray-light);">🎓 BAC</span>
                    <span style="background: var(--cream); padding: 8px 18px; border-radius: 30px; font-size: 0.8rem; border: 1px solid var(--gray-light);">🕋 Hadji</span>
                    <span style="background: var(--cream); padding: 8px 18px; border-radius: 30px; font-size: 0.8rem; border: 1px solid var(--gray-light);">🏢 Entreprise</span>
                </div>
            </div>
            
            <div class="commande-gateau-form">
                <h4 style="font-family: 'Playfair Display', serif; font-size: 1.4rem; margin-bottom: 20px; color: var(--dark);">📝 Commander un gâteau</h4>
                <form action="commande_gateau.php" method="POST">
                    <div class="form-group">
                        <label for="nom_client">Votre nom</label>
                        <input type="text" id="nom_client" name="nom_client" placeholder="Entrez votre nom" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="votre@email.com" required>
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" placeholder="+223 XX XX XX XX" required>
                    </div>
                    <div class="form-group">
                        <label for="evenement">Type d'événement</label>
                        <select id="evenement" name="evenement" required>
                            <option value="">Sélectionnez un événement</option>
                            <option value="anniversaire">🎂 Anniversaire</option>
                            <option value="mariage">💍 Mariage</option>
                            <option value="bac">🎓 Fête de BAC</option>
                            <option value="hadji">🕋 Fête de Hadji</option>
                            <option value="entreprise">🏢 Entreprise</option>
                            <option value="ramadan">🌙 Ramadan</option>
                            <option value="autre">🎉 Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_evenement">Date de l'événement</label>
                        <input type="date" id="date_evenement" name="date_evenement" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Message / Instructions spéciales</label>
                        <textarea id="message" name="message" placeholder="Décrivez votre gâteau idéal..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">
                        <i class="bi bi-send"></i> Envoyer ma commande
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     SECTION JEUX
     ============================================ -->
<section class="section-jeux" id="jeux">
    <div class="container">
        <div class="jeux-content">
            <div class="jeux-info">
                <span class="section-label">🎮 Divertissement</span>
                <h2 class="section-title">Espace <span>Jeux</span> Sofia</h2>
                <p>
                    Détendez-vous et amusez-vous dans notre espace dédié aux jeux ! 
                    Parfait pour les familles, les groupes d'amis et les événements.
                </p>
                
                <div class="jeux-features">
                    <div class="jeux-feature">
                        <i class="bi bi-joystick"></i>
                        <span>Jeux de société</span>
                    </div>
                    <div class="jeux-feature">
                        <i class="bi bi-tv"></i>
                        <span>Jeux vidéo</span>
                    </div>
                    <div class="jeux-feature">
                        <i class="bi bi-dice-6"></i>
                        <span>Jeux de cartes</span>
                    </div>
                    <div class="jeux-feature">
                        <i class="bi bi-trophy"></i>
                        <span>Tournois</span>
                    </div>
                    <div class="jeux-feature">
                        <i class="bi bi-people"></i>
                        <span>Espace enfants</span>
                    </div>
                    <div class="jeux-feature">
                        <i class="bi bi-calendar-event"></i>
                        <span>Événements privés</span>
                    </div>
                </div>
                
                <a href="#" class="btn-primary">
                    <i class="bi bi-controller"></i> Découvrir l'espace jeux
                </a>
            </div>
            
            <div class="jeux-visual">
                <i class="bi bi-controller"></i>
                <p>🎮 Espace jeux - Divertissement pour tous</p>
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px; position: relative; z-index: 1;">
                    <span style="background: rgba(200,146,42,0.1); padding: 6px 14px; border-radius: 20px; font-size: 0.7rem; color: var(--gold);">🎯 Billard</span>
                    <span style="background: rgba(200,146,42,0.1); padding: 6px 14px; border-radius: 20px; font-size: 0.7rem; color: var(--gold);">🃏 Poker</span>
                    <span style="background: rgba(200,146,42,0.1); padding: 6px 14px; border-radius: 20px; font-size: 0.7rem; color: var(--gold);">🎮 PS5</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     SECTION RÉSERVATION
     ============================================ -->
<section class="section-reservation" id="reservation">
    <div class="container">
        <div class="reservation-card">
            <span class="section-label">📅 Réservez</span>
            <h2 class="section-title">Votre <span>Table</span></h2>
            <p class="section-subtitle" style="margin: 0 auto;">
                Offrez-vous un moment d'exception au Restaurant Sofia.
            </p>

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

            <a href="reservation.php" class="btn-primary" style="font-size: 1rem; padding: 18px 50px;">
                <i class="bi bi-calendar-check"></i> Réserver maintenant
            </a>
        </div>
    </div>
</section>

<!-- ============================================
     JAVASCRIPT
     ============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ===== NAVBAR SCROLL =====
    const nav = document.getElementById('mainNav');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            nav.classList.add('scrolled');
        } else {
            nav.classList.remove('scrolled');
        }
    });

    // ===== MOBILE MENU =====
    const toggle = document.getElementById('mobileToggle');
    const navLinks = document.querySelector('.nav-links');
    
    toggle.addEventListener('click', function() {
        const isOpen = navLinks.style.display === 'flex';
        navLinks.style.display = isOpen ? 'none' : 'flex';
        navLinks.style.flexDirection = 'column';
        navLinks.style.position = 'absolute';
        navLinks.style.top = '64px';
        navLinks.style.left = '0';
        navLinks.style.right = '0';
        navLinks.style.background = 'rgba(13, 11, 8, 0.98)';
        navLinks.style.padding = '24px 20px';
        navLinks.style.gap = '16px';
        navLinks.style.borderBottom = '1px solid rgba(200,146,42,0.08)';
        this.innerHTML = isOpen ? '<i class="bi bi-list"></i>' : '<i class="bi bi-x-lg"></i>';
    });

    // ===== FILTRAGE DES PLATS =====
    const tabs = document.querySelectorAll('.cat-tab');
    const platCards = document.querySelectorAll('.plat-card');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            const cat = this.dataset.cat;
            platCards.forEach(card => {
                if (cat === 'all' || card.dataset.cat == cat) {
                    card.style.display = 'block';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'scale(1)';
                    }, 50);
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // ===== NAVIGATION DOUCE =====
    document.querySelectorAll('.nav-links a:not(.nav-back-home):not(.nav-cta), .hero-actions a, .btn-reserver').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                
                if (window.innerWidth <= 768) {
                    navLinks.style.display = 'none';
                    toggle.innerHTML = '<i class="bi bi-list"></i>';
                }
            }
        });
    });

    // ===== ACTIVE LINK ON SCROLL =====
    const sections = document.querySelectorAll('section[id]');
    const navLinkItems = document.querySelectorAll('.nav-links a:not(.nav-back-home):not(.nav-cta)');
    
    window.addEventListener('scroll', function() {
        let current = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop - 100;
            if (window.scrollY >= sectionTop) {
                current = section.getAttribute('id');
            }
        });
        
        navLinkItems.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
            }
        });
    });

    // ===== ANIMATION AU SCROLL =====
    const animateElements = (entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    };

    const observer = new IntersectionObserver(animateElements, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    document.querySelectorAll('.plat-card, .gateau-card, .reservation-feature').forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(40px)';
        card.style.transition = `all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) ${index * 0.05}s`;
        observer.observe(card);
    });

    // ===== ANIMATION DES STATS =====
    const stats = document.querySelectorAll('.hero-stat .number');
    let statsAnimated = false;
    
    function animateStats() {
        if (statsAnimated) return;
        const heroStats = document.querySelector('.hero-stats');
        if (!heroStats) return;
        
        const rect = heroStats.getBoundingClientRect();
        if (rect.top < window.innerHeight && rect.bottom > 0) {
            statsAnimated = true;
            stats.forEach(stat => {
                const text = stat.textContent;
                const num = parseInt(text);
                if (!isNaN(num)) {
                    let current = 0;
                    const increment = Math.ceil(num / 40);
                    const interval = setInterval(() => {
                        current += increment;
                        if (current >= num) {
                            stat.textContent = text;
                            clearInterval(interval);
                        } else {
                            stat.textContent = current + '+';
                        }
                    }, 30);
                }
            });
        }
    }

    window.addEventListener('scroll', animateStats);
    setTimeout(animateStats, 500);

    // ===== DATE MIN POUR LE FORMULAIRE =====
    const dateInput = document.getElementById('date_evenement');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>