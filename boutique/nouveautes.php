<?php
// ============================================
// NOUVEAUTÉS - Awa Ka Sugu
// Version Ultra Moderne - Format Mini
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

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
// ============================================
$recentStmt = $pdo->query("
    SELECT * FROM produits 
    WHERE est_visible = 1 AND est_nouveau = 0
    ORDER BY created_at DESC 
    LIMIT 4
");
$produitsRecents = $recentStmt->fetchAll();

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
        /* ═══════════════════════════════════════════
           NOUVEAUTÉS — DESIGN PREMIUM & ÉLÉGANT
           ═══════════════════════════════════════════ */

        :root {
            --gold: #C8922A;
            --gold-deep: #9A6E1A;
            --gold-light: #E8C070;
            --ink: #0D0D0D;
            --muted: #8A99AA;
            --line: #EEEAE5;
            --line-soft: #F4F1EC;
            --bg: #F7F6F3;
            --danger: #E74C3C;
            --ease: cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--ink);
            overflow-x: hidden;
        }

        ::-webkit-scrollbar { width: 4px; background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 10px; }

        /* ═══════════════════════════════════════════
           HERO — PREMIUM SOMBRE AVEC HALO DORÉ
           ═══════════════════════════════════════════ */
        .hero-ultra {
            position: relative;
            padding: 80px 20px 70px;
            text-align: center;
            background: linear-gradient(135deg, #0D0D0D 0%, #1A1510 60%, #0D0D0D 100%);
            overflow: hidden;
            isolation: isolate;
        }

        /* Halo doré subtil */
        .hero-ultra::before {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 700px;
            height: 700px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(200,146,42,0.15) 0%, transparent 70%);
            z-index: 0;
            pointer-events: none;
        }

        /* Filet doré en bas */
        .hero-ultra::after {
            content: '';
            position: absolute;
            left: 50%; transform: translateX(-50%);
            bottom: 0;
            width: min(240px, 60%);
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            z-index: 2;
        }

        .hero-ultra > * { position: relative; z-index: 2; }

        .hero-ultra .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(200,146,42,0.08);
            color: var(--gold-light);
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
            padding: 8px 22px;
            border-radius: 50px;
            border: 1px solid rgba(200,146,42,0.2);
            margin-bottom: 24px;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .hero-ultra .badge i { color: var(--gold); }

        .hero-ultra h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem, 4.5vw, 3rem);
            font-weight: 600;
            color: #FFFFFF;
            letter-spacing: -0.5px;
            line-height: 1.1;
            margin: 0 0 12px;
        }

        .hero-ultra h1 span {
            color: var(--gold);
            font-style: italic;
            font-weight: 700;
        }

        .hero-ultra .sub {
            font-size: 0.68rem;
            color: rgba(255,255,255,0.4);
            letter-spacing: 4px;
            text-transform: uppercase;
            font-weight: 400;
            margin-top: 6px;
        }

        /* Décorations */
        .hero-ultra .deco {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin: 20px auto 20px;
        }
        .hero-ultra .deco .line {
            width: 36px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }
        .hero-ultra .deco .dot {
            width: 5px;
            height: 5px;
            background: var(--gold);
            border-radius: 50%;
            box-shadow: 0 0 8px rgba(200,146,42,0.6);
        }

        .hero-ultra > p {
            color: rgba(255,255,255,0.55);
            font-size: 0.9rem;
            max-width: 460px;
            margin: 0 auto 36px;
            line-height: 1.6;
            font-weight: 300;
        }

        /* Stats glass */
        .hero-ultra .stats {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
            max-width: 640px;
            margin: 0 auto;
        }

        .hero-ultra .stats .stat {
            flex: 1;
            min-width: 130px;
            max-width: 180px;
            padding: 16px 18px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: all 0.35s var(--ease);
            position: relative;
            overflow: hidden;
        }
        .hero-ultra .stats .stat::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(200,146,42,0.5), transparent);
            opacity: 0;
            transition: opacity 0.35s var(--ease);
        }
        .hero-ultra .stats .stat:hover {
            border-color: rgba(200,146,42,0.3);
            background: rgba(200,146,42,0.05);
            transform: translateY(-3px);
        }
        .hero-ultra .stats .stat:hover::before { opacity: 1; }

        .hero-ultra .stats .stat .number {
            font-family: 'Playfair Display', serif;
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--gold);
            display: block;
            line-height: 1;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }
        .hero-ultra .stats .stat .label {
            font-size: 0.58rem;
            color: rgba(255,255,255,0.45);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 500;
            display: block;
        }

        /* ═══════════════════════════════════════════
           CONTAINER
           ═══════════════════════════════════════════ */
        .container-custom {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ═══════════════════════════════════════════
           SECTION HEADER
           ═══════════════════════════════════════════ */
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin: 46px 0 28px;
            flex-wrap: wrap;
            gap: 14px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--line-soft);
            position: relative;
        }
        .section-head::after {
            content: '';
            position: absolute;
            left: 0; bottom: -1px;
            width: 50px;
            height: 2px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            border-radius: 2px;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--ink);
            line-height: 1.2;
            letter-spacing: -0.3px;
        }
        .section-title em {
            color: var(--gold);
            font-style: italic;
            font-weight: 700;
        }
        .section-title small {
            display: block;
            font-size: 0.72rem;
            font-weight: 400;
            color: var(--muted);
            font-family: 'Inter', sans-serif;
            margin-top: 4px;
            letter-spacing: 0.2px;
        }

        .view-all {
            font-size: 0.76rem;
            font-weight: 500;
            color: var(--gold-deep);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.3s var(--ease);
            padding-bottom: 3px;
            border-bottom: 1px solid transparent;
        }
        .view-all:hover {
            color: var(--gold);
            border-bottom-color: var(--gold);
        }
        .view-all i { transition: transform 0.3s; }
        .view-all:hover i { transform: translateX(3px); }

        /* ═══════════════════════════════════════════
           GRILLE PRODUITS — COMME LA BOUTIQUE
           ═══════════════════════════════════════════ */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            margin-bottom: 40px;
        }

        @media (min-width: 700px) {
            .products-grid { grid-template-columns: repeat(3, 1fr); gap: 18px; }
        }
        @media (min-width: 1000px) {
            .products-grid { grid-template-columns: repeat(4, 1fr); gap: 22px; }
        }
        @media (min-width: 1400px) {
            .products-grid { grid-template-columns: repeat(5, 1fr); gap: 24px; }
        }

        /* ═══════════════════════════════════════════
           CARTE PRODUIT
           ═══════════════════════════════════════════ */
        .product-card {
            background: #FFFFFF;
            border-radius: 16px;
            overflow: hidden;
            text-decoration: none;
            transition: transform 0.4s var(--ease), box-shadow 0.4s var(--ease), border-color 0.4s var(--ease);
            border: 1px solid var(--line-soft);
            position: relative;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 18px 40px rgba(200,146,42,0.12);
            border-color: rgba(200,146,42,0.3);
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
            transition: transform 0.6s var(--ease);
            display: block;
        }

        .product-card:hover .image-wrapper img {
            transform: scale(1.06);
        }

        /* ===== BADGES ===== */
        .badge-new {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--gold);
            color: #fff;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 3;
            box-shadow: 0 4px 12px rgba(200,146,42,0.3);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-new i { font-size: 0.6rem; }

        .badge-coup {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            color: var(--ink);
            font-size: 0.55rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            z-index: 3;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-coup i {
            color: var(--danger);
            font-size: 0.6rem;
        }

        /* ===== OVERLAY AU HOVER ===== */
        .product-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 40px 12px 14px;
            background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, rgba(0,0,0,0) 100%);
            opacity: 0;
            transform: translateY(8px);
            transition: all 0.35s var(--ease);
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .product-card:hover .product-overlay {
            opacity: 1;
            transform: translateY(0);
        }

        .btn-overlay {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.68rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s var(--ease);
            letter-spacing: 0.3px;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }

        .btn-view {
            background: rgba(255,255,255,0.95);
            color: var(--ink);
        }
        .btn-view:hover {
            background: var(--gold);
            color: #fff;
        }

        .btn-buy {
            background: var(--gold);
            color: #fff;
        }
        .btn-buy:hover {
            background: var(--gold-deep);
        }
        .btn-buy i { color: #fff; }

        /* ===== INFOS PRODUIT ===== */
        .product-info {
            padding: 12px 14px 16px;
            text-align: left;
            background: #FFFFFF;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .product-info .category {
            font-size: 0.55rem;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 4px;
        }

        .product-info .name {
            font-family: 'Playfair Display', serif;
            font-size: 0.86rem;
            font-weight: 600;
            color: var(--ink);
            margin: 2px 0 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.3s var(--ease);
            letter-spacing: 0.1px;
        }

        .product-card:hover .product-info .name {
            color: var(--gold);
        }

        .product-info .price {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--gold);
            display: flex;
            align-items: baseline;
            gap: 6px;
            flex-wrap: wrap;
            letter-spacing: -0.2px;
            margin-top: auto;
        }

        .product-info .price .old {
            font-size: 0.7rem;
            color: var(--muted);
            text-decoration: line-through;
            font-weight: 400;
        }

        .product-info .price .promo-badge {
            display: inline-block;
            background: var(--danger);
            color: #fff;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            letter-spacing: 0.3px;
        }

        /* ===== PAGINATION ===== */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin: 30px 0 50px;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 12px;
            border-radius: 50%;
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--ink);
            background: #fff;
            border: 1px solid var(--line);
            transition: all 0.3s var(--ease);
        }

        .pagination a:hover {
            background: var(--gold);
            color: #fff;
            border-color: var(--gold);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(200,146,42,0.25);
        }

        .pagination .active {
            background: var(--gold);
            color: #fff;
            border-color: var(--gold);
            font-weight: 700;
        }

        .pagination .disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        /* ═══════════════════════════════════════════
           SECTION À DÉCOUVRIR
           ═══════════════════════════════════════════ */
        .section-recentes {
            background: #FFFFFF;
            padding: 40px 0 60px;
            border-top: 1px solid var(--line-soft);
            margin-top: 20px;
        }

        .section-recentes .products-grid {
            margin-bottom: 0;
        }

        /* ═══════════════════════════════════════════
           EMPTY STATE
           ═══════════════════════════════════════════ */
        .empty-state {
            text-align: center;
            padding: 70px 30px;
            background: #fff;
            border-radius: 18px;
            border: 1px solid var(--line-soft);
        }

        .empty-state .empty-icon {
            font-size: 2.8rem;
            color: var(--gold);
            opacity: 0.4;
            margin-bottom: 16px;
            display: block;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            color: var(--ink);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 0.88rem;
            margin-bottom: 20px;
        }

        .empty-state .btn-empty {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--ink);
            color: #fff;
            padding: 12px 28px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s var(--ease);
        }

        .empty-state .btn-empty:hover {
            background: var(--gold);
            color: #0A0804;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(200,146,42,0.3);
        }

        /* ═══════════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════════ */
        @media (max-width: 900px) {
            .hero-ultra { padding: 60px 20px 50px; }
            .hero-ultra h1 { font-size: 2rem; }
            .section-head { margin: 36px 0 24px; }
            .section-title { font-size: 1.2rem; }
        }

        @media (max-width: 768px) {
            .container-custom { padding: 0 16px; }
            .hero-ultra { padding: 50px 18px 40px; }
            .hero-ultra .stats { gap: 10px; }
            .hero-ultra .stats .stat { padding: 14px 12px; min-width: 100px; }
            .hero-ultra .stats .stat .number { font-size: 1.4rem; }
            .section-head { flex-direction: column; align-items: flex-start; gap: 8px; }
            .product-info { padding: 10px 12px 14px; }
            .product-info .name { font-size: 0.78rem; }
            .product-info .price { font-size: 0.82rem; }
            .btn-overlay { padding: 6px 12px; font-size: 0.62rem; }
            .badge-new, .badge-coup { font-size: 0.5rem; padding: 3px 8px; }
        }

        @media (max-width: 480px) {
            .hero-ultra h1 { font-size: 1.7rem; }
            .hero-ultra .badge { font-size: 0.55rem; padding: 6px 16px; letter-spacing: 2px; }
            .hero-ultra .sub { font-size: 0.6rem; letter-spacing: 3px; }
            .hero-ultra > p { font-size: 0.82rem; margin-bottom: 28px; }
            .hero-ultra .stats .stat .number { font-size: 1.25rem; }
            .hero-ultra .stats .stat .label { font-size: 0.5rem; letter-spacing: 1px; }
            .products-grid { gap: 12px; }
            .product-info { padding: 10px 10px 12px; }
            .product-info .category { font-size: 0.5rem; letter-spacing: 1.2px; }
            .product-info .name { font-size: 0.72rem; margin-bottom: 4px; }
            .product-info .price { font-size: 0.78rem; }
            .product-info .price .old { font-size: 0.62rem; }
            .pagination a, .pagination span { min-width: 32px; height: 32px; font-size: 0.7rem; }
            .empty-state { padding: 50px 20px; }
            .section-title { font-size: 1.05rem; }
            .section-title small { font-size: 0.65rem; }
        }
    </style>
</head>
<body>

<!-- ═══════════════════════════════════════════
     HERO PREMIUM
     ═══════════════════════════════════════════ -->
<section class="hero-ultra">
    <span class="badge">
        <i class="bi bi-stars"></i> Collection <?= date('Y') ?>
    </span>
    <h1>Nouvelles <span>Créations</span></h1>
    <div class="sub">✦ Dernières tendances ✦</div>
    
    <div class="deco">
        <span class="line"></span>
        <span class="dot"></span>
        <span class="line"></span>
    </div>
    
    <p>Les pièces les plus récentes de la collection IBA Design, sélectionnées avec soin par Awa Doumbia.</p>
    
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

<!-- ═══════════════════════════════════════════
     CONTENU PRINCIPAL
     ═══════════════════════════════════════════ -->
<div class="container-custom">
    <div class="section-head">
        <div class="section-title">
            Toutes les <em>nouveautés</em>
            <small>Les dernières pièces marquées comme nouvelles</small>
        </div>
        <a href="catalogue.php" class="view-all">
            Voir catalogue <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <?php if(empty($produits)): ?>
        <div class="empty-state">
            <i class="bi bi-box-seam empty-icon"></i>
            <h3>Aucune nouveauté</h3>
            <p>Aucun produit n'est actuellement marqué comme nouveau. Revenez bientôt !</p>
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
            <a href="produit.php?id=<?= $p['id'] ?>" class="product-card" data-product-id="<?= $p['id'] ?>">
                <div class="image-wrapper">
                    <img src="<?= $img ?>" 
                         alt="<?= htmlspecialchars($p['nom']) ?>" 
                         loading="lazy" 
                         onerror="this.src='https://placehold.co/400x500/F5F5F5/C8922A?text=<?= urlencode($p['nom'])?>'">
                    
                    <div class="badge-new">
                        <i class="bi bi-star-fill"></i> Nouveau
                    </div>
                    <?php if($index % 4 == 0 && $index > 0): ?>
                    <div class="badge-coup">
                        <i class="bi bi-heart-fill"></i> Coup de cœur
                    </div>
                    <?php endif; ?>
                    
                    <div class="product-overlay">
                        <span class="btn-overlay btn-view">
                            <i class="bi bi-eye"></i> Détail
                        </span>
                        <button class="btn-overlay btn-buy" onclick="ajouterAuPanier(event, <?= $p['id'] ?>, 1)">
                            <i class="bi bi-cart-plus"></i> Ajouter
                        </button>
                    </div>
                </div>
                
                <div class="product-info">
                    <span class="category">Collection IBA</span>
                    <div class="name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="price">
                        <?php if($est_promo): ?>
                            <span class="old"><?= number_format($prix_ancien, 0, ',', ' ') ?> F</span>
                            <?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA
                            <span class="promo-badge">-<?= round((($prix_ancien - $prix_affiché) / $prix_ancien) * 100) ?>%</span>
                        <?php else: ?>
                            <?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA
                        <?php endif; ?>
                    </div>
                </div>
            </a>
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

<!-- ═══════════════════════════════════════════
     SECTION À DÉCOUVRIR
     ═══════════════════════════════════════════ -->
<?php if(!empty($produitsRecents)): ?>
<section class="section-recentes">
    <div class="container-custom">
        <div class="section-head">
            <div class="section-title">
                À <em>découvrir</em>
                <small>D'autres pièces de la collection</small>
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
            <a href="produit.php?id=<?= $p['id'] ?>" class="product-card" data-product-id="<?= $p['id'] ?>">
                <div class="image-wrapper">
                    <img src="<?= $img ?>" 
                         alt="<?= htmlspecialchars($p['nom']) ?>" 
                         loading="lazy" 
                         onerror="this.src='https://placehold.co/400x500/F5F5F5/C8922A?text=<?= urlencode($p['nom'])?>'">
                    
                    <?php if($est_promo): ?>
                        <div class="badge-new" style="background:var(--danger);">
                            <i class="bi bi-percent"></i> -<?= round((($prix_ancien - $prix_affiché) / $prix_ancien) * 100) ?>%
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-overlay">
                        <span class="btn-overlay btn-view">
                            <i class="bi bi-eye"></i> Détail
                        </span>
                        <button class="btn-overlay btn-buy" onclick="ajouterAuPanier(event, <?= $p['id'] ?>, 1)">
                            <i class="bi bi-cart-plus"></i> Ajouter
                        </button>
                    </div>
                </div>
                
                <div class="product-info">
                    <span class="category">Collection IBA</span>
                    <div class="name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="price">
                        <?php if($est_promo): ?>
                            <span class="old"><?= number_format($prix_ancien, 0, ',', ' ') ?> F</span>
                            <?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA
                            <span class="promo-badge">Promo</span>
                        <?php else: ?>
                            <?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     TOAST NOTIFICATION
     ═══════════════════════════════════════════ -->
<div id="toast" class="toast-notification" style="position:fixed;bottom:30px;right:30px;background:#1A1A1A;color:white;padding:14px 20px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.3);display:flex;align-items:center;gap:10px;transform:translateY(100px);opacity:0;transition:all 0.4s ease;z-index:9999;border-left:4px solid #C8922A;font-family:'Inter',sans-serif;">
    <i class="bi bi-check-circle-fill" style="color:#C8922A;"></i>
    <span id="toastMessage">Ajouté au panier</span>
    <button class="toast-close" onclick="closeToast()" style="background:none;border:none;color:rgba(255,255,255,0.3);cursor:pointer;font-size:1.1rem;padding:0 5px;">&times;</button>
</div>

<script>
// ═══════════════════════════════════════════
// AJOUT AU PANIER
// ═══════════════════════════════════════════
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
            const cartBadge = document.getElementById('navCartBadge');
            if (cartBadge && data.count !== undefined) {
                cartBadge.textContent = data.count;
                cartBadge.style.display = data.count > 0 ? 'flex' : 'none';
                cartBadge.style.transform = 'scale(1.3)';
                setTimeout(() => { cartBadge.style.transform = 'scale(1)'; }, 200);
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

// ═══════════════════════════════════════════
// TOAST
// ═══════════════════════════════════════════
let toastTimeout = null;

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    const toastMessage = document.getElementById('toastMessage');
    if (toastMessage) toastMessage.textContent = message;
    
    const icon = toast.querySelector('i');
    if (type === 'error') {
        toast.style.borderLeftColor = '#E74C3C';
        if (icon) icon.className = 'bi bi-x-circle-fill';
    } else if (type === 'warning') {
        toast.style.borderLeftColor = '#F39C12';
        if (icon) icon.className = 'bi bi-exclamation-triangle-fill';
    } else {
        toast.style.borderLeftColor = '#C8922A';
        if (icon) icon.className = 'bi bi-check-circle-fill';
    }
    
    toast.style.transform = 'translateY(0)';
    toast.style.opacity = '1';
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toast.style.transform = 'translateY(100px)';
        toast.style.opacity = '0';
    }, 3000);
}

function closeToast() {
    const toast = document.getElementById('toast');
    if (toast) {
        toast.style.transform = 'translateY(100px)';
        toast.style.opacity = '0';
    }
    clearTimeout(toastTimeout);
}
</script>

<?php require_once '../includes/footer.php'; ?>