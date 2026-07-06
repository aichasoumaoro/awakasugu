<?php
// ============================================
// RESTAURANT SOFIA — Menu Vitrine Premium
// Awa Doumbia | Bamako, Mali
// ============================================

// Connexion BDD AVANT header pour éviter tout conflit
$host = 'localhost'; $dbname = 'awakasugu_db'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) { die("Erreur BDD : " . $e->getMessage()); }

// Catégories
try {
    $categories = $pdo->query("SELECT * FROM categories_restaurant ORDER BY ordre ASC")->fetchAll();
} catch(Exception $e) {
    $categories = [
        ['id'=>1,'nom'=>'Cuisine Malienne','icone'=>'🇲🇱'],
        ['id'=>2,'nom'=>'Cuisine Européenne','icone'=>'🌍'],
        ['id'=>3,'nom'=>'Desserts & Pâtisserie','icone'=>'🍰'],
        ['id'=>4,'nom'=>'Boissons','icone'=>'🥤'],
        ['id'=>5,'nom'=>'Boulangerie','icone'=>'🥖'],
    ];
}

// Tous les plats visibles
try {
    $plats = $pdo->query("SELECT * FROM plats WHERE est_visible=1 ORDER BY est_plat_du_jour DESC, categorie_id ASC, id DESC")->fetchAll();
} catch(Exception $e) { $plats = []; }

// Plat du jour
try {
    $plat_jour = $pdo->query("SELECT * FROM plats WHERE est_plat_du_jour=1 AND est_visible=1 LIMIT 1")->fetch();
} catch(Exception $e) { $plat_jour = null; }
if(!$plat_jour && !empty($plats)) $plat_jour = $plats[0];

// Regrouper plats par catégorie
$plats_par_cat = [];
foreach($plats as $p) {
    $plats_par_cat[$p['categorie_id'] ?? 0][] = $p;
}

$titre_page = 'Restaurant Sofia — Cuisine d\'Exception';
$meta_desc  = 'Restaurant Sofia à Bamako : cuisine malienne authentique, européenne, pâtisserie artisanale. Réservez votre table.';
require_once '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restaurant Sofia — Cuisine d'Exception | Bamako</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* =============================================
   RESTAURANT SOFIA — DESIGN VITRINE PREMIUM
   ============================================= */
:root {
    --gold: #C8922A;
    --gold-l: #E8B55A;
    --gold-d: #9A6E1A;
    --noir: #0D0B08;
    --noir2: #1A1612;
    --creme: #FBF8F3;
    --blanc: #FFFFFF;
    --gris: #6B6B6B;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:'Jost',sans-serif;background:var(--creme);color:var(--noir);overflow-x:hidden;}
::-webkit-scrollbar{width:6px;}
::-webkit-scrollbar-thumb{background:var(--gold);border-radius:10px;}
a{text-decoration:none;transition:all 0.3s;}
img{display:block;max-width:100%;}

/* ===== NAVBAR RESTAURANT ===== */
.resto-nav {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 9999;
    background: rgba(13,11,8,0.96);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(200,146,42,0.15);
    height: 68px;
    display: flex;
    align-items: center;
    padding: 0 40px;
    justify-content: space-between;
    transition: all 0.4s;
}
.resto-nav.scrolled {
    box-shadow: 0 4px 30px rgba(0,0,0,0.5);
    border-bottom-color: rgba(200,146,42,0.3);
}
.resto-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}
.resto-brand-icon {
    width: 42px; height: 42px;
    background: linear-gradient(135deg, var(--gold), var(--gold-l));
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    box-shadow: 0 4px 15px rgba(200,146,42,0.3);
}
.resto-brand-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    font-weight: 700;
    color: #fff;
}
.resto-brand-name span { color: var(--gold); font-style: italic; }
.resto-links {
    display: flex;
    align-items: center;
    gap: 4px;
    list-style: none;
}
.resto-links a {
    font-family: 'Jost', sans-serif;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.7);
    padding: 8px 16px;
    border-radius: 6px;
    transition: all 0.25s;
    position: relative;
}
.resto-links a::after {
    content: '';
    position: absolute;
    bottom: 4px; left: 16px; right: 16px;
    height: 1.5px;
    background: var(--gold);
    transform: scaleX(0);
    transition: transform 0.25s;
}
.resto-links a:hover, .resto-links a.active { color: var(--gold); }
.resto-links a:hover::after, .resto-links a.active::after { transform: scaleX(1); }
.resto-accueil-link {
    font-size: 0.72rem !important;
    color: rgba(255,255,255,0.4) !important;
}
.btn-reserver {
    background: linear-gradient(135deg, var(--gold), var(--gold-l));
    color: var(--noir) !important;
    font-weight: 700 !important;
    padding: 10px 22px !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 14px rgba(200,146,42,0.35);
}
.btn-reserver:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(200,146,42,0.45);
}
.btn-reserver::after { display: none !important; }
.nav-burger {
    display: none;
    background: none;
    border: 1px solid rgba(255,255,255,0.15);
    color: #fff;
    font-size: 1.4rem;
    padding: 6px 10px;
    border-radius: 8px;
    cursor: pointer;
}

/* ===== HERO ===== */
.hero {
    position: relative;
    height: 100vh;
    min-height: 680px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.hero-bg {
    position: absolute;
    inset: 0;
    background: url('https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=1920&q=80') center/cover no-repeat;
    filter: blur(3px) brightness(0.35);
    transform: scale(1.05);
    animation: bgzoom 20s ease-in-out infinite alternate;
}
@keyframes bgzoom {
    from { transform: scale(1.05); }
    to   { transform: scale(1.12); }
}
.hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(13,11,8,0.3) 0%, rgba(13,11,8,0.7) 60%, var(--noir) 100%);
}
.hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    padding: 0 20px;
    max-width: 800px;
}
.hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 0.7rem;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: var(--gold);
    background: rgba(200,146,42,0.1);
    border: 1px solid rgba(200,146,42,0.25);
    padding: 8px 22px;
    border-radius: 30px;
    margin-bottom: 28px;
    animation: fadeInDown 0.8s ease both;
}
.hero-tag::before, .hero-tag::after {
    content: '✦';
    font-size: 0.5rem;
    opacity: 0.7;
}
.hero-titre {
    font-family: 'Playfair Display', serif;
    font-size: clamp(3rem, 8vw, 5.5rem);
    font-weight: 800;
    color: #fff;
    line-height: 1.05;
    margin-bottom: 20px;
    animation: fadeInUp 0.9s ease 0.2s both;
}
.hero-titre .gold { color: var(--gold); font-style: italic; }
.hero-sous {
    font-size: 0.88rem;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.45);
    margin-bottom: 20px;
    animation: fadeInUp 0.9s ease 0.3s both;
}
.hero-desc {
    font-size: 1.05rem;
    color: rgba(255,255,255,0.6);
    line-height: 1.8;
    max-width: 560px;
    margin: 0 auto 40px;
    animation: fadeInUp 0.9s ease 0.4s both;
}
.hero-btns {
    display: flex;
    gap: 14px;
    justify-content: center;
    flex-wrap: wrap;
    animation: fadeInUp 0.9s ease 0.55s both;
}
.btn-hero-or {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    background: linear-gradient(135deg, var(--gold), var(--gold-l));
    color: var(--noir);
    font-family: 'Jost', sans-serif;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 1px;
    padding: 15px 34px;
    border-radius: 50px;
    box-shadow: 0 8px 24px rgba(200,146,42,0.35);
    transition: all 0.3s;
}
.btn-hero-or:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(200,146,42,0.5); color: var(--noir); }
.btn-hero-ghost {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    background: transparent;
    color: #fff;
    font-family: 'Jost', sans-serif;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 1px;
    padding: 15px 34px;
    border-radius: 50px;
    border: 1px solid rgba(255,255,255,0.25);
    transition: all 0.3s;
}
.btn-hero-ghost:hover { border-color: var(--gold); color: var(--gold); }

/* Stats hero */
.hero-stats {
    position: absolute;
    bottom: 40px; left: 0; right: 0;
    z-index: 2;
    display: flex;
    justify-content: center;
    gap: 60px;
    animation: fadeInUp 1s ease 0.8s both;
}
.hero-stat strong {
    display: block;
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    font-weight: 700;
    color: var(--gold);
    line-height: 1;
}
.hero-stat span {
    font-size: 0.68rem;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.35);
    margin-top: 4px;
    display: block;
}

/* ===== PLAT DU JOUR BANDEAU ===== */
.plat-jour-banner {
    background: linear-gradient(135deg, var(--noir), var(--noir2));
    border-top: 2px solid var(--gold);
    padding: 36px 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
}
.plj-left { display: flex; align-items: center; gap: 20px; }
.plj-badge {
    background: linear-gradient(135deg, var(--gold), var(--gold-l));
    color: var(--noir);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    padding: 6px 16px;
    border-radius: 20px;
    display: block;
    margin-bottom: 8px;
}
.plj-nom {
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 4px;
}
.plj-desc {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.45);
}
.plj-right { display: flex; align-items: center; gap: 20px; }
.plj-prix {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--gold);
    white-space: nowrap;
}
.btn-plj {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--gold), var(--gold-l));
    color: var(--noir);
    font-family: 'Jost', sans-serif;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 12px 26px;
    border-radius: 8px;
    transition: all 0.3s;
    white-space: nowrap;
}
.btn-plj:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(200,146,42,0.4); color: var(--noir); }

/* ===== CONTAINER ===== */
.container { max-width: 1300px; margin: 0 auto; padding: 0 40px; }

/* ===== ONGLETS CATÉGORIES ===== */
.menu-section { padding: 80px 0; }
.menu-header { text-align: center; margin-bottom: 56px; }
.menu-header-tag {
    font-size: 0.68rem;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 12px;
    display: block;
}
.menu-header-titre {
    font-family: 'Playfair Display', serif;
    font-size: clamp(2rem, 4vw, 3rem);
    font-weight: 700;
    color: var(--noir);
    margin-bottom: 12px;
}
.menu-header-titre em { color: var(--gold); font-style: italic; }
.menu-header-desc { font-size: 0.92rem; color: var(--gris); max-width: 480px; margin: 0 auto; line-height: 1.7; }

/* Tabs catégories */
.cat-tabs {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 52px;
}
.cat-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Jost', sans-serif;
    font-size: 0.82rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    padding: 11px 22px;
    border-radius: 40px;
    border: 1.5px solid #E0DAD0;
    background: #fff;
    color: var(--gris);
    cursor: pointer;
    transition: all 0.28s;
}
.cat-tab:hover { border-color: var(--gold); color: var(--gold); }
.cat-tab.active {
    background: linear-gradient(135deg, var(--gold), var(--gold-l));
    color: var(--noir);
    border-color: transparent;
    box-shadow: 0 6px 18px rgba(200,146,42,0.3);
}
.cat-tab .tab-icone { font-size: 1.1rem; }
.cat-tab .tab-count {
    background: rgba(0,0,0,0.12);
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
}

/* ===== GRILLE PLATS ===== */
.cat-section { display: none; }
.cat-section.active { display: block; animation: fadeIn 0.4s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

.cat-section-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 32px;
    padding-bottom: 20px;
    border-bottom: 1px solid #EDE8DF;
}
.cat-section-icon { font-size: 2rem; }
.cat-section-nom {
    font-family: 'Playfair Display', serif;
    font-size: 1.7rem;
    font-weight: 700;
    color: var(--noir);
}
.cat-section-count { font-size: 0.78rem; color: var(--gris); margin-top: 3px; }

.plats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
}

/* ===== CARTE PLAT ===== */
.plat-card {
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    transition: all 0.38s;
    border: 1px solid #F0EBE0;
    display: flex;
    flex-direction: column;
}
.plat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 24px 50px rgba(0,0,0,0.12);
    border-color: transparent;
}
.plat-img-wrap {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: linear-gradient(135deg, #F5F0E8, #EDE8D8);
}
.plat-img-wrap img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}
.plat-card:hover .plat-img-wrap img { transform: scale(1.07); }
.plat-placeholder {
    width: 100%; height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: linear-gradient(135deg, #1A1612, #2A2218);
}
.plat-placeholder span { font-size: 3.5rem; opacity: 0.6; }
.plat-placeholder p {
    font-family: 'Playfair Display', serif;
    font-size: 0.85rem;
    color: rgba(200,146,42,0.5);
    letter-spacing: 1px;
}
.plat-badge-jour {
    position: absolute;
    top: 12px; left: 12px;
    background: linear-gradient(135deg, var(--gold), var(--gold-l));
    color: var(--noir);
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    padding: 5px 12px;
    border-radius: 20px;
    box-shadow: 0 4px 10px rgba(200,146,42,0.4);
}
.plat-badge-dispo {
    position: absolute;
    top: 12px; right: 12px;
    background: rgba(231,76,60,0.9);
    color: #fff;
    font-size: 0.6rem;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 20px;
}

/* Infos sous la photo — séparées */
.plat-body { padding: 18px 18px 0; flex: 1; }
.plat-nom {
    font-family: 'Playfair Display', serif;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--noir);
    margin-bottom: 6px;
    line-height: 1.3;
}
.plat-desc {
    font-size: 0.78rem;
    color: var(--gris);
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.plat-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
    font-size: 0.72rem;
    color: #bbb;
}
.plat-meta i { color: var(--gold); }

/* Prix SÉPARÉ du reste */
.plat-footer {
    padding: 14px 18px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid #F5F0E8;
    margin-top: 14px;
}
.plat-prix {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gold);
}
.plat-prix span {
    font-family: 'Jost', sans-serif;
    font-size: 0.72rem;
    font-weight: 500;
    color: var(--gris);
    margin-left: 3px;
}
.btn-commander {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--noir);
    color: #fff;
    font-family: 'Jost', sans-serif;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    padding: 9px 18px;
    border-radius: 8px;
    transition: all 0.28s;
}
.btn-commander:hover {
    background: linear-gradient(135deg, var(--gold), var(--gold-l));
    color: var(--noir);
    transform: translateY(-1px);
}

/* ===== SECTION ESPACE ENFANTS ===== */
.enfants-section {
    background: linear-gradient(135deg, #1A1612, #0D0B08);
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}
.enfants-section::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(200,146,42,0.1), transparent 70%);
    border-radius: 50%;
}
.enfants-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}
.enfants-texte .tag {
    font-size: 0.68rem;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 16px;
    display: block;
}
.enfants-texte h2 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.8rem, 3.5vw, 2.6rem);
    font-weight: 700;
    color: #fff;
    margin-bottom: 18px;
}
.enfants-texte h2 em { color: var(--gold); font-style: italic; }
.enfants-texte p {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.5);
    line-height: 1.8;
    margin-bottom: 28px;
}
.enfants-features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 32px;
}
.enfants-feat {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: rgba(200,146,42,0.06);
    border: 1px solid rgba(200,146,42,0.12);
    border-radius: 12px;
    padding: 14px;
}
.enfants-feat .ico { font-size: 1.6rem; flex-shrink: 0; }
.enfants-feat h4 { font-size: 0.85rem; font-weight: 600; color: #fff; margin-bottom: 3px; }
.enfants-feat p { font-size: 0.75rem; color: rgba(255,255,255,0.4); margin: 0; line-height: 1.5; }
.enfants-visuel {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    height: 420px;
    background: linear-gradient(135deg, #2A2218, #1A1612);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 20px;
}
.enfants-visuel .big-emoji { font-size: 6rem; }
.enfants-visuel p {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    color: rgba(200,146,42,0.5);
    font-style: italic;
}

/* ===== SECTION RÉSERVATION ===== */
.resa-section {
    background: var(--creme);
    padding: 80px 0;
}
.resa-card {
    background: var(--noir);
    border-radius: 24px;
    padding: 60px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
    position: relative;
    overflow: hidden;
}
.resa-card::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 320px; height: 320px;
    background: radial-gradient(circle, rgba(200,146,42,0.12), transparent 70%);
    border-radius: 50%;
}
.resa-texte .tag { font-size: 0.68rem; letter-spacing: 3px; text-transform: uppercase; color: var(--gold); margin-bottom: 14px; display: block; }
.resa-texte h2 { font-family: 'Playfair Display', serif; font-size: 2.2rem; font-weight: 700; color: #fff; margin-bottom: 16px; }
.resa-texte p { font-size: 0.9rem; color: rgba(255,255,255,0.5); line-height: 1.8; margin-bottom: 28px; }
.resa-info { display: flex; flex-direction: column; gap: 12px; }
.resa-info-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.88rem;
    color: rgba(255,255,255,0.6);
}
.resa-info-item i { color: var(--gold); font-size: 1rem; width: 18px; }
.resa-form { position: relative; z-index: 1; }
.resa-form .form-group { margin-bottom: 16px; }
.resa-form label { font-size: 0.72rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: rgba(255,255,255,0.4); margin-bottom: 7px; display: block; }
.resa-form input,
.resa-form select,
.resa-form textarea {
    width: 100%;
    background: rgba(255,255,255,0.06);
    border: 1.5px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 12px 16px;
    font-family: 'Jost', sans-serif;
    font-size: 0.88rem;
    color: #fff;
    transition: all 0.28s;
    outline: none;
}
.resa-form input::placeholder,
.resa-form textarea::placeholder { color: rgba(255,255,255,0.25); }
.resa-form input:focus,
.resa-form select:focus,
.resa-form textarea:focus { border-color: var(--gold); background: rgba(200,146,42,0.06); }
.resa-form select option { background: var(--noir); color: #fff; }
.resa-form .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.btn-resa {
    width: 100%;
    background: linear-gradient(135deg, var(--gold), var(--gold-l));
    color: var(--noir);
    font-family: 'Jost', sans-serif;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 1px;
    padding: 14px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    box-shadow: 0 6px 18px rgba(200,146,42,0.3);
    margin-top: 6px;
}
.btn-resa:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(200,146,42,0.45); }

/* ===== SECTION INFOS PRATIQUES ===== */
.infos-section {
    background: #fff;
    padding: 70px 0;
    border-top: 1px solid #EDE8DF;
}
.infos-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 40px; }
.info-card { text-align: center; padding: 30px 20px; }
.info-icon {
    width: 64px; height: 64px;
    background: rgba(200,146,42,0.08);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; margin: 0 auto 18px;
    border: 1px solid rgba(200,146,42,0.15);
}
.info-title { font-family: 'Playfair Display', serif; font-size: 1.15rem; font-weight: 700; color: var(--noir); margin-bottom: 10px; }
.info-text { font-size: 0.85rem; color: var(--gris); line-height: 1.7; }

/* ===== FOOTER RESTO ===== */
.resto-footer {
    background: var(--noir);
    border-top: 1px solid rgba(200,146,42,0.15);
    padding: 40px;
    text-align: center;
}
.resto-footer-logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gold);
    margin-bottom: 10px;
}
.resto-footer p { font-size: 0.8rem; color: rgba(255,255,255,0.3); }
.resto-footer a { color: var(--gold); }

/* ===== VIDE ===== */
.plats-vide {
    grid-column: 1/-1;
    text-align: center;
    padding: 60px 20px;
    color: #ccc;
}
.plats-vide i { font-size: 3rem; display: block; margin-bottom: 14px; }
.plats-vide p { font-size: 0.9rem; }

/* ===== ANIMATIONS ===== */
@keyframes fadeInDown {from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeInUp   {from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}

/* ===== RESPONSIVE ===== */
@media(max-width:1100px){.plats-grid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:900px){
    .resto-nav{padding:0 20px;}
    .resto-links{display:none;}
    .nav-burger{display:block;}
    .plats-grid{grid-template-columns:repeat(2,1fr);}
    .enfants-grid,.resa-card{grid-template-columns:1fr;gap:36px;}
    .resa-card{padding:36px 24px;}
    .infos-grid{grid-template-columns:1fr 1fr;}
    .hero-stats{gap:30px;}
    .plat-jour-banner{flex-direction:column;align-items:flex-start;gap:16px;}
}
@media(max-width:600px){
    .container{padding:0 16px;}
    .plats-grid{grid-template-columns:1fr 1fr;gap:14px;}
    .hero-stats{display:none;}
    .cat-tabs{gap:6px;}
    .cat-tab{padding:9px 14px;font-size:0.76rem;}
    .infos-grid{grid-template-columns:1fr;}
    .enfants-features{grid-template-columns:1fr;}
    .resa-form .form-row{grid-template-columns:1fr;}
}
@media(max-width:420px){
    .plats-grid{grid-template-columns:1fr;}
}
</style>

<!-- ===== NAVBAR ===== -->
<nav class="resto-nav" id="restoNav">
    <a href="../index.php" class="resto-brand">
        <div class="resto-brand-icon">🍽️</div>
        <div class="resto-brand-name">Resto<span>Sofia</span></div>
    </a>
    <ul class="resto-links" id="restoLinks">
        <li><a href="../index.php" class="resto-accueil-link"><i class="bi bi-house"></i> Accueil principal</a></li>
        <li><a href="#accueil" class="active">Accueil</a></li>
        <li><a href="#menu">Menu</a></li>
        <li><a href="#gateaux">Gâteaux</a></li>
        <li><a href="#jeux">Jeux</a></li>
        <li><a href="#reservation" class="btn-reserver"><i class="bi bi-calendar-check"></i> Réserver</a></li>
    </ul>
    <button class="nav-burger" id="navBurger" onclick="toggleNav()">
        <i class="bi bi-list"></i>
    </button>
</nav>

<!-- ===== HERO ===== -->
<section class="hero" id="accueil">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-tag">Restaurant Sofia</div>
        <h1 class="hero-titre">
            L'Art de la <span class="gold" id="typed-text"></span>
        </h1>
        <p class="hero-sous">✦ Cuisine Malienne & Internationale ✦</p>
        <p class="hero-desc">
            Découvrez une expérience culinaire unique au cœur de Bamako.
            Des saveurs authentiques, des plats généreux et un cadre chaleureux.
        </p>
        <div class="hero-btns">
            <a href="#menu" class="btn-hero-or">
                <i class="bi bi-menu-button-wide"></i> Voir Notre Menu
            </a>
            <a href="#reservation" class="btn-hero-ghost">
                <i class="bi bi-calendar-check"></i> Réserver une table
            </a>
        </div>
    </div>
    <div class="hero-stats">
        <div class="hero-stat">
            <strong><?= count($plats) ?>+</strong>
            <span>Plats au menu</span>
        </div>
        <div class="hero-stat">
            <strong><?= count($categories) ?></strong>
            <span>Catégories</span>
        </div>
        <div class="hero-stat">
            <strong>5★</strong>
            <span>Note clients</span>
        </div>
        <div class="hero-stat">
            <strong>2 000F</strong>
            <span>Espace enfants</span>
        </div>
    </div>
</section>

<!-- ===== PLAT DU JOUR ===== -->
<?php if($plat_jour): ?>
<div class="plat-jour-banner">
    <div class="plj-left">
        <div style="font-size:3rem;">🌟</div>
        <div>
            <span class="plj-badge">✦ Plat du jour</span>
            <div class="plj-nom"><?= htmlspecialchars($plat_jour['nom']) ?></div>
            <div class="plj-desc"><?= htmlspecialchars(mb_substr($plat_jour['description'] ?? '', 0, 80)) ?>...</div>
        </div>
    </div>
    <div class="plj-right">
        <div class="plj-prix"><?= number_format($plat_jour['prix'], 0, ',', ' ') ?> FCFA</div>
        <a href="commande_repas.php?plat_id=<?= $plat_jour['id'] ?>" class="btn-plj">
            <i class="bi bi-cart-plus"></i> Commander maintenant
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ===== MENU PAR CATÉGORIES ===== -->
<section class="menu-section" id="menu">
    <div class="container">
        <div class="menu-header">
            <span class="menu-header-tag">Notre sélection</span>
            <h2 class="menu-header-titre">Notre <em>Menu</em></h2>
            <p class="menu-header-desc">Cuisine malienne authentique, spécialités européennes et pâtisserie artisanale — préparés avec passion chaque jour.</p>
        </div>

        <!-- Onglets catégories -->
        <div class="cat-tabs" id="catTabs">
            <button class="cat-tab active" onclick="showCat('all', this)">
                <span class="tab-icone">🍽️</span>
                Tout voir
                <span class="tab-count"><?= count($plats) ?></span>
            </button>
            <?php foreach($categories as $cat):
                $count_cat = count($plats_par_cat[$cat['id']] ?? []);
                if($count_cat == 0) continue;
            ?>
            <button class="cat-tab" onclick="showCat(<?= $cat['id'] ?>, this)">
                <span class="tab-icone"><?= $cat['icone'] ?? '🍽️' ?></span>
                <?= htmlspecialchars($cat['nom']) ?>
                <span class="tab-count"><?= $count_cat ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Section "Tout voir" -->
        <div class="cat-section active" id="cat-all">
            <?php foreach($categories as $cat):
                $plats_cat = $plats_par_cat[$cat['id']] ?? [];
                if(empty($plats_cat)) continue;
            ?>
            <div class="cat-section-header">
                <span class="cat-section-icon"><?= $cat['icone'] ?? '🍽️' ?></span>
                <div>
                    <div class="cat-section-nom"><?= htmlspecialchars($cat['nom']) ?></div>
                    <div class="cat-section-count"><?= count($plats_cat) ?> plat<?= count($plats_cat) > 1 ? 's' : '' ?></div>
                </div>
            </div>
            <div class="plats-grid" style="margin-bottom:48px;">
                <?php foreach($plats_cat as $p): echo renderPlatCard($p); endforeach; ?>
            </div>
            <?php endforeach; ?>

            <!-- Plats sans catégorie -->
            <?php if(!empty($plats_par_cat[0]) || !empty($plats_par_cat[''])): 
                $sans_cat = array_merge($plats_par_cat[0] ?? [], $plats_par_cat[''] ?? []);
                if(!empty($sans_cat)):
            ?>
            <div class="cat-section-header">
                <span class="cat-section-icon">🍽️</span>
                <div>
                    <div class="cat-section-nom">Autres spécialités</div>
                    <div class="cat-section-count"><?= count($sans_cat) ?> plat<?= count($sans_cat) > 1 ? 's' : '' ?></div>
                </div>
            </div>
            <div class="plats-grid" style="margin-bottom:48px;">
                <?php foreach($sans_cat as $p): echo renderPlatCard($p); endforeach; ?>
            </div>
            <?php endif; endif; ?>

            <?php if(empty($plats)): ?>
            <div class="plats-vide">
                <i class="bi bi-cup-hot"></i>
                <p>Aucun plat disponible pour le moment.<br>Revenez bientôt !</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sections par catégorie -->
        <?php foreach($categories as $cat):
            $plats_cat = $plats_par_cat[$cat['id']] ?? [];
            if(empty($plats_cat)) continue;
        ?>
        <div class="cat-section" id="cat-<?= $cat['id'] ?>">
            <div class="cat-section-header">
                <span class="cat-section-icon"><?= $cat['icone'] ?? '🍽️' ?></span>
                <div>
                    <div class="cat-section-nom"><?= htmlspecialchars($cat['nom']) ?></div>
                    <div class="cat-section-count"><?= count($plats_cat) ?> plat<?= count($plats_cat) > 1 ? 's' : '' ?></div>
                </div>
            </div>
            <div class="plats-grid">
                <?php foreach($plats_cat as $p): echo renderPlatCard($p); endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</section>

<!-- ===== ESPACE ENFANTS ===== -->
<section class="enfants-section" id="jeux">
    <div class="container">
        <div class="enfants-grid">
            <div class="enfants-texte">
                <span class="tag">✦ Espace dédié</span>
                <h2>L'Espace <em>Enfants</em></h2>
                <p>
                    Restaurant Sofia pense à toute la famille ! Un espace de jeux sécurisé
                    et animé attend vos enfants pendant que vous savourez votre repas.
                    Jeux interactifs, animation et menu enfant dès 2 000 FCFA.
                </p>
                <div class="enfants-features">
                    <div class="enfants-feat">
                        <div class="ico">🎮</div>
                        <div>
                            <h4>Jeux interactifs</h4>
                            <p>Consoles, jeux de société et activités créatives</p>
                        </div>
                    </div>
                    <div class="enfants-feat">
                        <div class="ico">🍕</div>
                        <div>
                            <h4>Menu enfant</h4>
                            <p>Plats adaptés dès 2 000 FCFA</p>
                        </div>
                    </div>
                    <div class="enfants-feat">
                        <div class="ico">🛡️</div>
                        <div>
                            <h4>Espace sécurisé</h4>
                            <p>Surveillé en permanence par notre équipe</p>
                        </div>
                    </div>
                    <div class="enfants-feat">
                        <div class="ico">🎂</div>
                        <div>
                            <h4>Anniversaires</h4>
                            <p>Organisation d'événements pour enfants</p>
                        </div>
                    </div>
                </div>
                <a href="#reservation" class="btn-hero-or" style="display:inline-flex;width:fit-content;">
                    <i class="bi bi-calendar-check"></i> Réserver avec espace enfants
                </a>
            </div>
            <div class="enfants-visuel">
                <div class="big-emoji">🎡</div>
                <p>L'aventure commence ici !</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== INFOS PRATIQUES ===== -->
<section class="infos-section">
    <div class="container">
        <div class="menu-header" style="margin-bottom:48px;">
            <span class="menu-header-tag">Infos pratiques</span>
            <h2 class="menu-header-titre">Nous <em>trouver</em></h2>
        </div>
        <div class="infos-grid">
            <div class="info-card">
                <div class="info-icon">📍</div>
                <div class="info-title">Notre adresse</div>
                <div class="info-text">
                    Sebenikoro, face mosquée Mahi Ouattara<br>
                    Bamako, Mali<br>
                    <a href="https://wa.me/22374740303" style="color:var(--gold);font-weight:600;">+223 74 74 03 03</a>
                </div>
            </div>
            <div class="info-card">
                <div class="info-icon">🕐</div>
                <div class="info-title">Horaires d'ouverture</div>
                <div class="info-text">
                    Lundi — Samedi<br>
                    <strong>8h00 — 21h00</strong><br>
                    Dimanche sur réservation
                </div>
            </div>
            <div class="info-card">
                <div class="info-icon">💳</div>
                <div class="info-title">Paiements acceptés</div>
                <div class="info-text">
                    🟠 Orange Money<br>
                    💙 Wave<br>
                    💚 Moov Money · Espèces
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== RÉSERVATION ===== -->
<section class="resa-section" id="reservation">
    <div class="container">
        <div class="resa-card">
            <div class="resa-texte">
                <span class="tag">✦ Table pour vous</span>
                <h2>Réserver votre table</h2>
                <p>Garantissez votre place au Restaurant Sofia. Réservation confirmée par SMS dans les 30 minutes.</p>
                <div class="resa-info">
                    <div class="resa-info-item"><i class="bi bi-geo-alt-fill"></i> Sebenikoro, Bamako</div>
                    <div class="resa-info-item"><i class="bi bi-telephone-fill"></i> +223 74 74 03 03</div>
                    <div class="resa-info-item"><i class="bi bi-tiktok"></i> @sofiaboulangerie74740303</div>
                    <div class="resa-info-item"><i class="bi bi-clock-fill"></i> Lun–Sam : 8h – 21h</div>
                    <div class="resa-info-item"><i class="bi bi-people-fill"></i> Privatisation disponible</div>
                </div>
            </div>
            <div class="resa-form">
                <form action="reservation.php" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Votre nom</label>
                            <input type="text" name="nom" placeholder="Awa Doumbia" required>
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="telephone" placeholder="+223 XX XX XX XX" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date souhaitée</label>
                            <input type="date" name="date_reservation" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>Heure</label>
                            <select name="heure">
                                <option value="08:00">08h00</option>
                                <option value="09:00">09h00</option>
                                <option value="10:00">10h00</option>
                                <option value="11:00">11h00</option>
                                <option value="12:00">12h00</option>
                                <option value="13:00">13h00</option>
                                <option value="14:00">14h00</option>
                                <option value="15:00">15h00</option>
                                <option value="18:00">18h00</option>
                                <option value="19:00">19h00</option>
                                <option value="20:00">20h00</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre de personnes</label>
                            <select name="nb_personnes">
                                <?php for($i=1;$i<=20;$i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> personne<?= $i>1?'s':'' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Type de table</label>
                            <select name="type_table">
                                <option value="interieure">Salle intérieure</option>
                                <option value="terrasse">Terrasse</option>
                                <option value="vip">Salon VIP</option>
                                <option value="enfants">Avec espace enfants</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Message (optionnel)</label>
                        <textarea name="message" rows="2" placeholder="Occasion spéciale, allergie, demande particulière..."></textarea>
                    </div>
                    <button type="submit" class="btn-resa">
                        <i class="bi bi-calendar-check"></i> Confirmer la réservation
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ===== FOOTER RESTO ===== -->
<footer class="resto-footer">
    <div class="resto-footer-logo">Restaurant Sofia</div>
    <p>
        Une création d'<a href="https://www.tiktok.com/@awadoumbia223" target="_blank">Awa Doumbia</a> · Bamako, Mali<br>
        © <?= date('Y') ?> Restaurant Sofia — Tous droits réservés.
    </p>
</footer>

<?php
// Fonction d'affichage d'une carte plat
function renderPlatCard($p) {
    $img_path = '../uploads/plats/' . $p['image'];
    $has_img  = !empty($p['image']) && file_exists($img_path);
    $prix     = number_format($p['prix'], 0, ',', ' ');
    $nom      = htmlspecialchars($p['nom']);
    $desc     = htmlspecialchars($p['description'] ?? '');
    $dispo    = $p['est_disponible_aujourd_hui'] ?? 1;
    $plat_j   = $p['est_plat_du_jour'] ?? 0;
    $temps    = $p['temps_preparation'] ?? 20;

    ob_start();
    ?>
    <div class="plat-card">
        <div class="plat-img-wrap">
            <?php if($has_img): ?>
                <img src="<?= $img_path ?>" alt="<?= $nom ?>" loading="lazy">
            <?php else: ?>
                <div class="plat-placeholder">
                    <span>🍽️</span>
                    <p><?= mb_substr($nom, 0, 18) ?></p>
                </div>
            <?php endif; ?>
            <?php if($plat_j): ?>
                <div class="plat-badge-jour">⭐ Plat du jour</div>
            <?php endif; ?>
            <?php if(!$dispo): ?>
                <div class="plat-badge-dispo">Indisponible</div>
            <?php endif; ?>
        </div>
        <div class="plat-body">
            <div class="plat-nom"><?= $nom ?></div>
            <?php if($desc): ?>
                <div class="plat-desc"><?= $desc ?></div>
            <?php endif; ?>
            <div class="plat-meta">
                <span><i class="bi bi-clock"></i> <?= $temps ?> min</span>
            </div>
        </div>
        <div class="plat-footer">
            <div class="plat-prix">
                <?= $prix ?> <span>FCFA</span>
            </div>
            <?php if($dispo): ?>
            <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="btn-commander">
                <i class="bi bi-cart-plus"></i> Commander
            </a>
            <?php else: ?>
            <span style="font-size:0.75rem;color:#ccc;">Non disponible</span>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<script>
// Typed text animation
const words = ['Table', 'Saveur', 'Partage', 'Fête'];
let wi = 0, ci = 0, deleting = false;
const el = document.getElementById('typed-text');
function typeWriter() {
    if(!el) return;
    const word = words[wi];
    if(!deleting) {
        el.textContent = word.substring(0, ci + 1);
        ci++;
        if(ci === word.length) { deleting = true; setTimeout(typeWriter, 1800); return; }
    } else {
        el.textContent = word.substring(0, ci - 1);
        ci--;
        if(ci === 0) { deleting = false; wi = (wi + 1) % words.length; }
    }
    setTimeout(typeWriter, deleting ? 60 : 100);
}
typeWriter();

// Navbar scroll
window.addEventListener('scroll', () => {
    document.getElementById('restoNav').classList.toggle('scrolled', window.scrollY > 50);
});

// Toggle nav mobile
function toggleNav() {
    const links = document.getElementById('restoLinks');
    const isOpen = links.style.display === 'flex';
    links.style.display = isOpen ? '' : 'flex';
    links.style.flexDirection = 'column';
    links.style.position = 'absolute';
    links.style.top = '68px';
    links.style.left = '0';
    links.style.right = '0';
    links.style.background = '#0D0B08';
    links.style.padding = '16px 24px';
    links.style.borderBottom = '1px solid rgba(200,146,42,0.15)';
}
document.addEventListener('click', e => {
    if(!e.target.closest('.resto-nav')) {
        document.getElementById('restoLinks').style.display = '';
    }
});

// Tabs catégories
function showCat(id, btn) {
    document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.cat-section').forEach(s => s.classList.remove('active'));
    const target = document.getElementById('cat-' + id);
    if(target) target.classList.add('active');
}

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        const target = document.querySelector(a.getAttribute('href'));
        if(target) { e.preventDefault(); target.scrollIntoView({behavior:'smooth', block:'start'}); }
    });
});
</script>