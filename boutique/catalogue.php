<?php
session_name('PUBLIC_SESSION');
session_start();
$titre_page = 'Boutique - IBA Design';
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

// Fonctions pour les vidéos
function getYoutubeId($url) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/', $url, $matches);
    return $matches[1] ?? '';
}

function getVideoUrl($video) {
    if ($video['type'] == 'local' && !empty($video['fichier_video'])) {
        return '../uploads/videos/' . $video['fichier_video'];
    }
    return "https://www.youtube.com/embed/" . getYoutubeId($video['url_ou_fichier']);
}

$categorie_id = isset($_GET['categorie']) ? (int)$_GET['categorie'] : 0;
$sous_categorie_id = isset($_GET['sous_categorie']) ? (int)$_GET['sous_categorie'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$tri = isset($_GET['tri']) ? $_GET['tri'] : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;

// ============================================
// CATÉGORIES AVEC LEURS SOUS-CATÉGORIES
// ============================================

$categories_masonry = [
    ['id' => 1, 'nom' => 'Abayas', 'img' => 'abayas.jpg'],
    ['id' => 2, 'nom' => 'Foulards & Turbans', 'img' => 'foulards.jpg'],
    ['id' => 3, 'nom' => 'Sacs & Accessoires', 'img' => 'sacs.jpg'],
    ['id' => 4, 'nom' => 'Chaussures', 'img' => 'chaussures.jpg'],
    ['id' => 5, 'nom' => 'Prêt-à-porter', 'img' => 'pret-a-porter.jpg'],
];

$subcategories_data = [
    1 => [ // Abayas
        ['id' => 11, 'nom' => 'Abayas Bijoux', 'prix' => '35 000 – 50 000 FCFA'],
        ['id' => 12, 'nom' => 'Abayas Bibi', 'prix' => '50 000 – 80 000 FCFA'],
        ['id' => 13, 'nom' => 'Abayas Stars', 'prix' => '90 000 – 150 000 FCFA'],
        ['id' => 14, 'nom' => 'Abayas Enfant', 'prix' => 'Dès 25 000 FCFA'],
    ],
    2 => [ // Foulards & Turbans
        ['id' => 21, 'nom' => 'Foulards', 'prix' => 'Dès 5 000 FCFA'],
        ['id' => 22, 'nom' => 'Turbants', 'prix' => 'Dès 8 000 FCFA'],
        ['id' => 23, 'nom' => 'Voiles', 'prix' => 'Dès 6 000 FCFA'],
    ],
    3 => [ // Sacs & Accessoires
        ['id' => 31, 'nom' => 'Sacs à main', 'prix' => 'Dès 15 000 FCFA'],
        ['id' => 32, 'nom' => 'Porte-monnaie', 'prix' => 'Dès 8 000 FCFA'],
        ['id' => 33, 'nom' => 'Sacs complets', 'prix' => 'Dès 25 000 FCFA'],
        ['id' => 34, 'nom' => 'Accessoires', 'prix' => 'Dès 3 000 FCFA'],
    ],
    4 => [ // Chaussures
        ['id' => 41, 'nom' => 'Talons', 'prix' => 'Dès 18 000 FCFA'],
        ['id' => 42, 'nom' => 'Ballerines', 'prix' => 'Dès 12 000 FCFA'],
        ['id' => 43, 'nom' => 'Sandales', 'prix' => 'Dès 10 000 FCFA'],
        ['id' => 44, 'nom' => 'Fermées', 'prix' => 'Dès 15 000 FCFA'],
    ],
    5 => [ // Prêt-à-porter
        ['id' => 51, 'nom' => 'Robes', 'prix' => 'Dès 20 000 FCFA'],
        ['id' => 52, 'nom' => 'Ensembles', 'prix' => 'Dès 35 000 FCFA'],
        ['id' => 53, 'nom' => 'Vestes', 'prix' => 'Dès 25 000 FCFA'],
        ['id' => 54, 'nom' => 'Jupes', 'prix' => 'Dès 15 000 FCFA'],
    ],
];

$categorie_nom = '';
if ($categorie_id > 0) {
    foreach ($categories_masonry as $cat) {
        if ($cat['id'] == $categorie_id) {
            $categorie_nom = $cat['nom'];
            break;
        }
    }
}

$sub_nom_affiché = '';
if ($sous_categorie_id > 0) {
    foreach ($subcategories_data as $cat_id => $subs) {
        foreach ($subs as $sub) {
            if ($sub['id'] == $sous_categorie_id) {
                $sub_nom_affiché = $sub['nom'];
                break 2;
            }
        }
    }
}

// ============================================
// RÉCUPÉRATION DES PRODUITS DEPUIS produits_abayas
// ============================================

$produits = [];
$total_products = 0;
$total_pages = 0;

// Vérifier si la table produits_abayas existe
try {
    $test = $pdo->query("SELECT 1 FROM produits_abayas LIMIT 1");
    $table_exists = true;
} catch(PDOException $e) {
    $table_exists = false;
}

if ($table_exists) {
    // Utiliser la table produits_abayas
    $sql = "SELECT * FROM produits_abayas WHERE est_visible = 1";
    $params = [];

    if ($categorie_id > 0) {
        if ($sous_categorie_id > 0) {
            $sql .= " AND sous_categorie_id = ?";
            $params[] = $sous_categorie_id;
        } else {
            $sql .= " AND categorie_id = ?";
            $params[] = $categorie_id;
        }
    }

    if (!empty($search)) {
        $sql .= " AND (nom LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    switch ($tri) {
        case 'price_asc':
            $sql .= " ORDER BY prix ASC";
            break;
        case 'price_desc':
            $sql .= " ORDER BY prix DESC";
            break;
        default:
            $sql .= " ORDER BY created_at DESC";
    }

    // Comptage
    $count_sql = "SELECT COUNT(*) FROM produits_abayas WHERE est_visible = 1";
    $count_params = [];

    if ($categorie_id > 0) {
        if ($sous_categorie_id > 0) {
            $count_sql .= " AND sous_categorie_id = ?";
            $count_params[] = $sous_categorie_id;
        } else {
            $count_sql .= " AND categorie_id = ?";
            $count_params[] = $categorie_id;
        }
    }

    if (!empty($search)) {
        $count_sql .= " AND (nom LIKE ? OR description LIKE ?)";
        $count_params[] = "%$search%";
        $count_params[] = "%$search%";
    }

    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($count_params);
    $total_products = $stmt_count->fetchColumn();
    $total_pages = ceil($total_products / $per_page);

    $offset = ($page - 1) * $per_page;
    $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produits = $stmt->fetchAll();
    
} else {
    // Fallback sur la table produits
    $sql = "SELECT * FROM produits WHERE est_visible = 1";
    $params = [];

    if ($categorie_id > 0) {
        if ($sous_categorie_id > 0) {
            $sql .= " AND categorie_id = ?";
            $params[] = $sous_categorie_id;
        } else {
            $sub_ids = [];
            if (isset($subcategories_data[$categorie_id])) {
                foreach ($subcategories_data[$categorie_id] as $sub) {
                    $sub_ids[] = $sub['id'];
                }
            }
            if (!empty($sub_ids)) {
                $placeholders = implode(',', array_fill(0, count($sub_ids), '?'));
                $sql .= " AND (categorie_id = ? OR categorie_id IN ($placeholders))";
                $params[] = $categorie_id;
                foreach ($sub_ids as $sub_id) {
                    $params[] = $sub_id;
                }
            } else {
                $sql .= " AND categorie_id = ?";
                $params[] = $categorie_id;
            }
        }
    }

    if (!empty($search)) {
        $sql .= " AND (nom LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    switch ($tri) {
        case 'price_asc':
            $sql .= " ORDER BY prix ASC";
            break;
        case 'price_desc':
            $sql .= " ORDER BY prix DESC";
            break;
        default:
            $sql .= " ORDER BY created_at DESC";
    }

    $count_sql = "SELECT COUNT(*) FROM produits WHERE est_visible = 1";
    $count_params = [];

    if ($categorie_id > 0) {
        if ($sous_categorie_id > 0) {
            $count_sql .= " AND categorie_id = ?";
            $count_params[] = $sous_categorie_id;
        } else {
            $sub_ids = [];
            if (isset($subcategories_data[$categorie_id])) {
                foreach ($subcategories_data[$categorie_id] as $sub) {
                    $sub_ids[] = $sub['id'];
                }
            }
            if (!empty($sub_ids)) {
                $placeholders = implode(',', array_fill(0, count($sub_ids), '?'));
                $count_sql .= " AND (categorie_id = ? OR categorie_id IN ($placeholders))";
                $count_params[] = $categorie_id;
                foreach ($sub_ids as $sub_id) {
                    $count_params[] = $sub_id;
                }
            } else {
                $count_sql .= " AND categorie_id = ?";
                $count_params[] = $categorie_id;
            }
        }
    }

    if (!empty($search)) {
        $count_sql .= " AND (nom LIKE ? OR description LIKE ?)";
        $count_params[] = "%$search%";
        $count_params[] = "%$search%";
    }

    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($count_params);
    $total_products = $stmt_count->fetchColumn();
    $total_pages = ceil($total_products / $per_page);

    $offset = ($page - 1) * $per_page;
    $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produits = $stmt->fetchAll();
}

// Récupérer les vidéos récentes pour l'accueil
$videos_recents = $pdo->query("SELECT * FROM videos WHERE est_active = 1 ORDER BY created_at DESC LIMIT 4")->fetchAll();

// ============================================
// FONCTION POUR RÉCUPÉRER L'IMAGE
// ============================================

function getProductImage($image) {
    if (empty($image)) {
        return 'https://placehold.co/400x500/F5F5F5/C8922A?text=Abaya';
    }
    
    $paths = [
        '../uploads/abayas/' . $image,
        '../uploads/produits/abayas/' . $image,
        '../uploads/produits/' . $image,
        'uploads/abayas/' . $image,
        'uploads/produits/abayas/' . $image,
        'uploads/produits/' . $image,
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return 'https://placehold.co/400x500/F5F5F5/C8922A?text=' . urlencode(str_replace('.jpeg', '', $image));
}

// ============================================
// FONCTION POUR LE NOM DE LA SOUS-CATÉGORIE
// ============================================

function getSousCategorieNom($id, $subcategories_data) {
    foreach ($subcategories_data as $cat_id => $subs) {
        foreach ($subs as $sub) {
            if ($sub['id'] == $id) {
                return $sub['nom'];
            }
        }
    }
    return 'Collection';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boutique - IBA Design</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Jost:wght@300;400;500;600;700;800&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jost', sans-serif; background: #F8F7F5; color: #1A1A1A; }
        .banner {
            background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
            padding: 70px 20px 60px;
            text-align: center;
        }
        .banner h1 { font-size: 2.8rem; color: #C8922A; font-weight: 700; }
        .banner p { color: rgba(255,255,255,0.5); margin-top: 12px; }
        .container { max-width: 1300px; margin: 0 auto; padding: 0 20px 60px; }
        .search-wrapper { max-width: 500px; margin: -30px auto 50px; }
        .search-form {
            display: flex;
            background: white;
            border-radius: 60px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .search-form input { flex: 1; padding: 15px 25px; border: none; outline: none; }
        .search-form button {
            padding: 15px 30px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            border: none;
            color: #1A1A1A;
            font-weight: 700;
            cursor: pointer;
        }
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 25px;
            margin-bottom: 60px;
        }
        .cat-card { text-align: center; text-decoration: none; }
        .cat-img {
            width: 100%;
            aspect-ratio: 1/1;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .cat-img img { width: 100%; height: 100%; object-fit: cover; }
        .cat-name { font-size: 0.9rem; font-weight: 600; color: #1A1A1A; }
        .section-head {
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 700; color: #0D0D0D; }
        .section-title em { color: #C8922A; font-style: italic; }
        .section-link { font-size: 0.8rem; font-weight: 600; color: #C8922A; text-decoration: none; }
        .subcat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 20px;
            margin-bottom: 60px;
        }
        .subcat-card {
            background: white;
            border-radius: 16px;
            padding: 18px;
            text-decoration: none;
            text-align: center;
            border: 1px solid #F0EBE3;
            transition: all 0.3s;
        }
        .subcat-card:hover { transform: translateY(-5px); border-color: #C8922A; }
        .subcat-card-name { font-size: 0.85rem; font-weight: 700; color: #1A1A1A; }
        .subcat-card-price { font-size: 0.7rem; color: #C8922A; margin-top: 5px; display: block; }
        
        /* Section vidéos */
        .videos-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 60px;
        }
        .video-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        .video-thumbnail {
            position: relative;
            aspect-ratio: 16/9;
            overflow: hidden;
            background: #1A1A1A;
        }
        .video-thumbnail img, .video-thumbnail video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .play-btn {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 45px;
            height: 45px;
            background: rgba(200,146,42,0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .video-card:hover .play-btn {
            transform: translate(-50%, -50%) scale(1.1);
            background: #C8922A;
        }
        .play-btn i {
            font-size: 1.3rem;
            color: white;
            margin-left: 3px;
        }
        .video-info {
            padding: 12px;
        }
        .video-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1A1A1A;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .video-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.65rem;
            color: #C8922A;
            margin-top: 6px;
        }
        
        .shop-layout { display: flex; gap: 45px; }
        .sidebar { width: 270px; flex-shrink: 0; }
        .content { flex: 1; }
        .sidebar-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid #E8E8E8;
            color: #1A1A1A;
        }
        .sidebar-list { list-style: none; }
        .sidebar-list li { margin-bottom: 6px; }
        .sidebar-list a {
            display: block;
            padding: 10px 15px;
            color: #666;
            text-decoration: none;
            border-radius: 12px;
            font-size: 0.85rem;
            transition: all 0.25s;
        }
        .sidebar-list a:hover, .sidebar-list a.active { background: rgba(200,146,42,0.1); color: #C8922A; transform: translateX(5px); }
        .filters-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .sort-select {
            padding: 9px 20px;
            border: 1.5px solid #E0E0E0;
            border-radius: 40px;
            background: white;
            font-size: 0.8rem;
            cursor: pointer;
        }
        .count { font-size: 0.8rem; color: #888; background: #F0F0F0; padding: 6px 18px; border-radius: 40px; }
        .products-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; }
        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            text-decoration: none;
            transition: all 0.4s;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }
        .product-card:hover { transform: translateY(-10px); box-shadow: 0 25px 45px rgba(0,0,0,0.12); }
        .product-img {
            position: relative;
            aspect-ratio: 3/4;
            overflow: hidden;
            background: #F5F3F0;
        }
        .product-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .product-card:hover .product-img img { transform: scale(1.08); }
        .product-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            padding: 25px 20px;
            transform: translateY(100%);
            transition: transform 0.35s;
            display: flex;
            gap: 12px;
        }
        .product-card:hover .product-overlay { transform: translateY(0); }
        .btn-overlay {
            flex: 1;
            padding: 10px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            transition: all 0.25s;
        }
        .btn-view { background: white; color: #1A1A1A; }
        .btn-view:hover { background: #C8922A; color: white; }
        .btn-buy { background: #C8922A; color: white; }
        .btn-buy:hover { background: #9A6E1A; }
        .product-info { padding: 18px; text-align: center; }
        .product-title { font-size: 0.88rem; font-weight: 600; color: #1A1A1A; margin-bottom: 5px; }

        /* STYLE POUR LE PRIX PROMOTIONNEL */
        .product-price {
            font-size: 0.95rem;
            font-weight: 700;
            color: #C8922A;
        }
        .product-price .old-price {
            font-size: 0.75rem;
            color: #999;
            text-decoration: line-through;
            font-weight: 400;
            margin-right: 8px;
        }
        .product-price .promo-badge {
            display: inline-block;
            background: #E74C3C;
            color: white;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 5px;
        }
        .product-badge-promo {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #E74C3C;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            z-index: 5;
            box-shadow: 0 2px 10px rgba(231,76,60,0.3);
            text-transform: uppercase;
        }

        .empty-state { text-align: center; padding: 70px; background: white; border-radius: 24px; }
        .empty-state i { font-size: 3rem; color: #C8922A; margin-bottom: 15px; }
        .breadcrumb { margin-bottom: 30px; font-size: 0.8rem; color: #999; }
        .breadcrumb a { color: #999; text-decoration: none; }
        .breadcrumb a:hover { color: #C8922A; }
        .breadcrumb span { color: #C8922A; font-weight: 500; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 50px; }
        .pagination a, .pagination span { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; text-decoration: none; color: #666; background: white; border: 1px solid #E0E0E0; transition: all 0.25s; }
        .pagination a:hover, .pagination .active { background: #C8922A; color: white; border-color: #C8922A; }
        
        /* Modal vidéo */
        .video-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        .video-modal.active { display: flex; }
        .modal-content {
            position: relative;
            width: 90%;
            max-width: 1000px;
            background: #1A1A1A;
            border-radius: 20px;
            overflow: hidden;
        }
        .modal-video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
        }
        .modal-video-container iframe, .modal-video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        .modal-close {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
        }
        .modal-info { padding: 20px; color: white; }
        .modal-info h3 { font-size: 1rem; font-weight: 600; }
        
        @media (max-width: 1000px) {
            .products-grid { grid-template-columns: repeat(2, 1fr); }
            .categories-grid { grid-template-columns: repeat(3, 1fr); }
            .subcat-grid { grid-template-columns: repeat(2, 1fr); }
            .videos-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 800px) {
            .shop-layout { flex-direction: column; }
            .sidebar { width: 100%; }
            .sidebar-list { display: flex; flex-wrap: wrap; gap: 8px; }
            .sidebar-list li { margin-bottom: 0; }
        }
        @media (max-width: 600px) {
            .products-grid { grid-template-columns: 1fr; }
            .categories-grid { grid-template-columns: repeat(2, 1fr); }
            .subcat-grid { grid-template-columns: 1fr; }
            .videos-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="banner">
    <h1>IBA DESIGN</h1>
    <p>L'élégance à la malienne</p>
</div>

<div class="container">
    <div class="search-wrapper">
        <form class="search-form" method="GET" action="">
            <?php if ($categorie_id > 0): ?>
                <input type="hidden" name="categorie" value="<?= $categorie_id ?>">
            <?php endif; ?>
            <?php if ($sous_categorie_id > 0): ?>
                <input type="hidden" name="sous_categorie" value="<?= $sous_categorie_id ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Rechercher un produit..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Rechercher</button>
        </form>
    </div>

    <?php if ($categorie_id == 0 && empty($search)): ?>
        <div class="categories-grid">
            <?php foreach ($categories_masonry as $cat): ?>
                <a href="?categorie=<?= $cat['id'] ?>" class="cat-card">
                    <div class="cat-img">
                        <img src="<?= SITE_URL ?>/assets/images/categories/<?= $cat['img'] ?>" 
                             alt="<?= htmlspecialchars($cat['nom']) ?>"
                             onerror="this.src='https://placehold.co/200x200/C8922A/FFF?text=<?= urlencode(substr($cat['nom'], 0, 1)) ?>'">
                    </div>
                    <div class="cat-name"><?= htmlspecialchars($cat['nom']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Section Abayas -->
        <div class="section-head">
            <div>
                <h2 class="section-title">Nos <em>abayas</em></h2>
            </div>
            <a href="?categorie=1" class="section-link">Voir toutes →</a>
        </div>
        <div class="subcat-grid">
            <?php foreach ($subcategories_data[1] as $sub): ?>
                <a href="?categorie=1&sous_categorie=<?= $sub['id'] ?>" class="subcat-card">
                    <div class="subcat-card-name"><?= $sub['nom'] ?></div>
                    <div class="subcat-card-price"><?= $sub['prix'] ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Section Sacs & Accessoires -->
        <div class="section-head">
            <div>
                <h2 class="section-title">Nos <em>sacs & accessoires</em></h2>
            </div>
            <a href="?categorie=3" class="section-link">Voir toutes →</a>
        </div>
        <div class="subcat-grid">
            <?php foreach ($subcategories_data[3] as $sub): ?>
                <a href="?categorie=3&sous_categorie=<?= $sub['id'] ?>" class="subcat-card">
                    <div class="subcat-card-name"><?= $sub['nom'] ?></div>
                    <div class="subcat-card-price"><?= $sub['prix'] ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Section Chaussures -->
        <div class="section-head">
            <div>
                <h2 class="section-title">Nos <em>chaussures</em></h2>
            </div>
            <a href="?categorie=4" class="section-link">Voir toutes →</a>
        </div>
        <div class="subcat-grid">
            <?php foreach ($subcategories_data[4] as $sub): ?>
                <a href="?categorie=4&sous_categorie=<?= $sub['id'] ?>" class="subcat-card">
                    <div class="subcat-card-name"><?= $sub['nom'] ?></div>
                    <div class="subcat-card-price"><?= $sub['prix'] ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Section Vidéos Awa Doumbia -->
        <div class="section-head" style="margin-top: 40px;">
            <div>
                <h2 class="section-title">Les vidéos d'<em>Awa Doumbia</em></h2>
                <p style="font-size: 0.85rem; color: #8A99AA; margin-top: 5px;">Découvrez les actualités et conseils de notre ambassadrice</p>
            </div>
            <a href="videos.php" class="section-link">Voir toutes →</a>
        </div>
        
        <?php if(!empty($videos_recents)): ?>
        <div class="videos-grid">
            <?php foreach($videos_recents as $video): 
                $isLocal = false;
                
                if ($video['type'] == 'local' && !empty($video['fichier_video'])) {
                    $isLocal = true;
                }
                if (empty($video['type']) && !empty($video['fichier_video'])) {
                    $isLocal = true;
                }
                if (!empty($video['fichier_video']) && file_exists('../uploads/videos/' . $video['fichier_video'])) {
                    $isLocal = true;
                }
                
                $videoUrl = '';
                $thumbUrl = '';
                $videoType = 'iframe';
                
                if ($isLocal) {
                    $videoUrl = '../uploads/videos/' . $video['fichier_video'];
                    $thumbUrl = $videoUrl;
                    $videoType = 'video';
                } else {
                    $youtubeId = getYoutubeId($video['url_ou_fichier']);
                    if (!empty($youtubeId)) {
                        $videoUrl = "https://www.youtube.com/embed/" . $youtubeId . "?autoplay=1";
                        $thumbUrl = "https://img.youtube.com/vi/" . $youtubeId . "/mqdefault.jpg";
                    } else {
                        $videoUrl = $video['url_ou_fichier'];
                        $thumbUrl = 'https://placehold.co/400x225/C8922A/FFF?text=Video';
                    }
                    $videoType = 'iframe';
                }
            ?>
                <div class="video-card" onclick="openVideoModal('<?= $videoUrl ?>', '<?= htmlspecialchars($video['titre']) ?>', '<?= $videoType ?>')">
                    <div class="video-thumbnail">
                        <?php if($isLocal): ?>
                            <video src="<?= $thumbUrl ?>" muted></video>
                        <?php else: ?>
                            <img src="<?= $thumbUrl ?>" alt="<?= htmlspecialchars($video['titre']) ?>" onerror="this.src='https://placehold.co/400x225/C8922A/FFF?text=<?= urlencode($video['titre']) ?>'">
                        <?php endif; ?>
                        <div class="play-btn">
                            <i class="bi bi-play-fill"></i>
                        </div>
                    </div>
                    <div class="video-info">
                        <div class="video-title"><?= htmlspecialchars($video['titre']) ?></div>
                        <div class="video-meta">
                            <span><i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($video['created_at'])) ?></span>
                            <span><i class="bi bi-camera-reels"></i> <?= $isLocal ? 'LOCAL' : 'YOUTUBE' ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 20px;">
            <i class="bi bi-camera-reels" style="font-size: 2rem; color: #C8922A;"></i>
            <p style="margin-top: 10px; color: #8A99AA;">Bientôt des vidéos exclusives !</p>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="breadcrumb">
            <a href="catalogue.php">Accueil</a> > <span><?= htmlspecialchars($categorie_nom) ?></span>
            <?php if ($sous_categorie_id > 0): ?> > <span><?= htmlspecialchars($sub_nom_affiché) ?></span><?php endif; ?>
        </div>

        <div class="shop-layout">
            <div class="sidebar">
                <div class="sidebar-title"><?= htmlspecialchars($categorie_nom) ?></div>
                <ul class="sidebar-list">
                    <li><a href="?categorie=<?= $categorie_id ?>" class="<?= ($sous_categorie_id == 0) ? 'active' : '' ?>">Tous les produits</a></li>
                    <?php if (isset($subcategories_data[$categorie_id])): ?>
                        <?php foreach ($subcategories_data[$categorie_id] as $sub): ?>
                            <li><a href="?categorie=<?= $categorie_id ?>&sous_categorie=<?= $sub['id'] ?>" class="<?= ($sous_categorie_id == $sub['id']) ? 'active' : '' ?>"><?= $sub['nom'] ?></a></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="content">
                <div class="filters-bar">
                    <select class="sort-select" onchange="window.location.href = this.value">
                        <option value="?<?= http_build_query(array_merge($_GET, ['tri' => 'newest', 'page' => 1])) ?>" <?= $tri == 'newest' ? 'selected' : '' ?>>📅 Plus récents</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['tri' => 'price_asc', 'page' => 1])) ?>" <?= $tri == 'price_asc' ? 'selected' : '' ?>>💰 Prix croissant</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['tri' => 'price_desc', 'page' => 1])) ?>" <?= $tri == 'price_desc' ? 'selected' : '' ?>>💰 Prix décroissant</option>
                    </select>
                    <div class="count"><?= $total_products ?> produits</div>
                </div>

                <?php if (empty($produits)): ?>
                    <div class="empty-state">
                        <i class="bi bi-box-seam"></i>
                        <p>Aucun produit trouvé dans cette catégorie</p>
                        <a href="catalogue.php" style="color: #C8922A;">← Retour à la boutique</a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($produits as $p): ?>
                            <?php
                            // Récupérer l'image
                            $img = getProductImage($p['image_principale'] ?? '');
                            
                            // Déterminer le prix
                            $prix_affiché = $p['prix'];
                            $prix_ancien = null;
                            $est_promo = false;
                            
                            if (!empty($p['prix_promo']) && $p['prix_promo'] > 0 && $p['prix_promo'] < $p['prix']) {
                                $prix_affiché = $p['prix_promo'];
                                $prix_ancien = $p['prix'];
                                $est_promo = true;
                            }
                            
                            // Récupérer le nom de la sous-catégorie
                            $sous_cat_nom = '';
                            if (!empty($p['sous_categorie_id'])) {
                                $sous_cat_nom = getSousCategorieNom($p['sous_categorie_id'], $subcategories_data);
                            }
                            ?>
                            <div class="product-card">
                                <div class="product-img">
                                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>">
                                    
                                    <?php if ($est_promo): ?>
                                        <div class="product-badge-promo">-<?= round((1 - $p['prix_promo'] / $p['prix']) * 100) ?>%</div>
                                    <?php endif; ?>
                                    
                                    <div class="product-overlay">
                                        <a href="produit.php?id=<?= $p['id'] ?>" class="btn-overlay btn-view">Voir détails</a>
                                        <a href="commande_directe.php?id=<?= $p['id'] ?>" class="btn-overlay btn-buy">Acheter</a>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <div class="product-title"><?= htmlspecialchars($p['nom']) ?></div>
                                    <?php if ($sous_cat_nom): ?>
                                        <div style="font-size: 0.7rem; color: #999; margin-bottom: 5px;"><?= htmlspecialchars($sous_cat_nom) ?></div>
                                    <?php endif; ?>
                                    <div class="product-price">
                                        <?php if ($est_promo): ?>
                                            <span class="old-price"><?= number_format($prix_ancien, 0, ',', ' ') ?> FCFA</span>
                                            <span><?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA</span>
                                            <span class="promo-badge">Promo</span>
                                        <?php else: ?>
                                            <?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"><i class="bi bi-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php 
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            for ($i = $start; $i <= $end; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"><i class="bi bi-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal vidéo -->
<div id="videoModal" class="video-modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeVideoModal()">&times;</button>
        <div class="modal-video-container" id="modalVideoContainer"></div>
        <div class="modal-info">
            <h3 id="modalTitle"></h3>
        </div>
    </div>
</div>

<script>
function openVideoModal(url, title, type) {
    const container = document.getElementById('modalVideoContainer');
    if (type === 'video') {
        container.innerHTML = '<video controls autoplay><source src="' + url + '" type="video/mp4">Votre navigateur ne supporte pas la lecture vidéo.</video>';
    } else {
        container.innerHTML = '<iframe src="' + url + '" frameborder="0" allowfullscreen></iframe>';
    }
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('videoModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeVideoModal() {
    document.getElementById('modalVideoContainer').innerHTML = '';
    document.getElementById('videoModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

document.getElementById('videoModal').addEventListener('click', function(e) {
    if (e.target === this) closeVideoModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeVideoModal();
});
</script>

<?php require_once '../includes/footer.php'; ?>