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

// ============================================
// RÉCUPÉRATION DES CATÉGORIES
// ============================================

$categories_masonry = [];
$stmt = $pdo->query("SELECT * FROM categories WHERE type = 'boutique' AND parent_id IS NULL ORDER BY ordre");
$categories_masonry = $stmt->fetchAll();

if (empty($categories_masonry)) {
    $categories_masonry = [
        ['id' => 1, 'nom' => 'Abayas'],
        ['id' => 2, 'nom' => 'Foulards & Turbans'],
        ['id' => 3, 'nom' => 'Sacs'],
        ['id' => 4, 'nom' => 'Chaussures'],
        ['id' => 5, 'nom' => 'Prêt-à-porter'],
    ];
}

$subcategories_data = [];
$stmt = $pdo->query("SELECT * FROM categories WHERE type = 'boutique' AND parent_id IS NOT NULL ORDER BY parent_id, ordre");
$all_subs = $stmt->fetchAll();

foreach ($all_subs as $sub) {
    $parent_id = $sub['parent_id'];
    if (!isset($subcategories_data[$parent_id])) {
        $subcategories_data[$parent_id] = [];
    }
    if ($parent_id == 3 && $sub['id'] == 34) {
        continue;
    }
    if ($parent_id == 4 && ($sub['id'] == 42 || $sub['id'] == 43)) {
        continue;
    }
    if ($parent_id == 5) {
        if ($sub['id'] == 51) {
            $sub['nom'] = 'Ensemble';
            $subcategories_data[$parent_id][] = $sub;
        }
        continue;
    }
    $subcategories_data[$parent_id][] = $sub;
}

if (!isset($subcategories_data[5]) || empty($subcategories_data[5])) {
    $subcategories_data[5] = [['id' => 51, 'nom' => 'Ensemble']];
}

if (empty($subcategories_data)) {
    $subcategories_data = [
        1 => [['id' => 11, 'nom' => 'Abayas Bijoux'], ['id' => 12, 'nom' => 'Abayas Bibi'], ['id' => 13, 'nom' => 'Abayas Stars'], ['id' => 14, 'nom' => 'Abayas Enfant']],
        2 => [['id' => 21, 'nom' => 'Foulards'], ['id' => 22, 'nom' => 'Turbants'], ['id' => 23, 'nom' => 'Voiles']],
        3 => [['id' => 31, 'nom' => 'Sacs à main'], ['id' => 32, 'nom' => 'Porte-monnaie'], ['id' => 33, 'nom' => 'Sacs complets']],
        4 => [['id' => 41, 'nom' => 'Talons'], ['id' => 44, 'nom' => 'Fermées']],
        5 => [['id' => 51, 'nom' => 'Ensemble']],
    ];
}

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

$produits = [];
$total_products = 0;
$total_pages = 0;

// ============================================
// REQUÊTE PRINCIPALE
// ============================================
$sql = "SELECT p.* FROM produits p WHERE p.est_visible = 1";
$params = [];

if ($categorie_id > 0) {
    if ($sous_categorie_id > 0) {
        if ($categorie_id == 5 && $sous_categorie_id == 51) {
            $sql .= " AND (p.categorie_id = 5 OR p.categorie_id IN (51, 52, 53, 54))";
        } else {
            if ($sous_categorie_id != 34 && $sous_categorie_id != 42 && $sous_categorie_id != 43) {
                $sql .= " AND p.categorie_id = ?";
                $params[] = $sous_categorie_id;
            }
        }
    } else {
        if ($categorie_id == 5) {
            $sql .= " AND (p.categorie_id = 5 OR p.categorie_id IN (51, 52, 53, 54))";
        } else {
            $sub_ids = [];
            if (isset($subcategories_data[$categorie_id])) {
                foreach ($subcategories_data[$categorie_id] as $sub) {
                    $sub_ids[] = $sub['id'];
                }
            }
            if (!empty($sub_ids)) {
                $placeholders = implode(',', array_fill(0, count($sub_ids), '?'));
                $sql .= " AND p.categorie_id IN ($placeholders)";
                foreach ($sub_ids as $sub_id) {
                    $params[] = $sub_id;
                }
            } else {
                $sql .= " AND p.categorie_id = ?";
                $params[] = $categorie_id;
            }
        }
    }
}

if (!empty($search)) {
    $sql .= " AND (p.nom LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

switch ($tri) {
    case 'price_asc':
        $sql .= " ORDER BY p.prix ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.prix DESC";
        break;
    default:
        $sql .= " ORDER BY p.created_at DESC";
}

$count_sql = "SELECT COUNT(*) FROM produits p WHERE p.est_visible = 1";
$count_params = [];

if ($categorie_id > 0) {
    if ($sous_categorie_id > 0) {
        if ($categorie_id == 5 && $sous_categorie_id == 51) {
            $count_sql .= " AND (p.categorie_id = 5 OR p.categorie_id IN (51, 52, 53, 54))";
        } else {
            if ($sous_categorie_id != 34 && $sous_categorie_id != 42 && $sous_categorie_id != 43) {
                $count_sql .= " AND p.categorie_id = ?";
                $count_params[] = $sous_categorie_id;
            }
        }
    } else {
        if ($categorie_id == 5) {
            $count_sql .= " AND (p.categorie_id = 5 OR p.categorie_id IN (51, 52, 53, 54))";
        } else {
            $sub_ids = [];
            if (isset($subcategories_data[$categorie_id])) {
                foreach ($subcategories_data[$categorie_id] as $sub) {
                    $sub_ids[] = $sub['id'];
                }
            }
            if (!empty($sub_ids)) {
                $placeholders = implode(',', array_fill(0, count($sub_ids), '?'));
                $count_sql .= " AND p.categorie_id IN ($placeholders)";
                foreach ($sub_ids as $sub_id) {
                    $count_params[] = $sub_id;
                }
            } else {
                $count_sql .= " AND p.categorie_id = ?";
                $count_params[] = $categorie_id;
            }
        }
    }
}

if (!empty($search)) {
    $count_sql .= " AND (p.nom LIKE ? OR p.description LIKE ?)";
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

// ============================================
// RÉCUPÉRER LES PRODUITS EN PROMOTION
// ============================================
$produits_promo = [];
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
        LIMIT 4
    ");
    $produits_promo = $stmt->fetchAll();
} catch(PDOException $e) {
    $produits_promo = [];
    error_log("Erreur récupération promotions: " . $e->getMessage());
}

$videos_recents = $pdo->query("SELECT * FROM videos WHERE est_active = 1 ORDER BY created_at DESC LIMIT 4")->fetchAll();

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
// FONCTIONS POUR LES IMAGES
// ============================================
function getProductImage($image) {
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

function getCategorieNom($id, $categories) {
    foreach ($categories as $cat) {
        if ($cat['id'] == $id) {
            return $cat['nom'];
        }
    }
    return 'Collection';
}

function getCategoryImage($cat) {
    $nom = $cat['nom'];
    
    $image_map = [
        'Abayas' => 'abaya47',
        'Foulards & Turbans' => 'foulards',
        'Sacs' => 'sacs',
        'Chaussures' => 'chaussures',
        'Prêt-à-porter' => 'pret-a-porter',
        'Ensemble' => 'ensemble',
    ];
    
    $image_name = $image_map[$nom] ?? 'default';
    
    $dossiers = [
        '../assets/images/categories/',
        'assets/images/categories/',
        '../uploads/produits/abayas/',
        'uploads/produits/abayas/',
        '../uploads/produits/',
        'uploads/produits/',
    ];
    
    $extensions = ['', '.jpg', '.jpeg', '.png', '.gif', '.webp'];
    
    foreach ($dossiers as $dossier) {
        foreach ($extensions as $ext) {
            $test_path = $dossier . $image_name . $ext;
            if (file_exists($test_path)) {
                return $test_path;
            }
        }
    }
    
    return 'https://placehold.co/200x200/C8922A/FFF?text=' . urlencode(substr($nom, 0, 1));
}

// ============================================
// RÉCUPÉRER LA WISHLIST DU CLIENT
// ============================================
$wishlist_ids = [];
if (isset($_SESSION['client_id'])) {
    $client_id = $_SESSION['client_id'];
    $stmt = $pdo->prepare("SELECT produit_id FROM wishlist WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $wishlist_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ============================================
// VÉRIFIER SI WISHLIST_AJAX.PHP EXISTE
// ============================================
$wishlist_ajax_exists = file_exists('../wishlist_ajax.php');
if (!$wishlist_ajax_exists) {
    $ajax_content = '<?php
session_name("PUBLIC_SESSION");
session_start();

header("Content-Type: application/json");

if (!isset($_SESSION["client_id"])) {
    echo json_encode(["success" => false, "message" => "Veuillez vous connecter"]);
    exit;
}

$client_id = $_SESSION["client_id"];
$action = $_POST["action"] ?? "";
$produit_id = isset($_POST["produit_id"]) ? (int)$_POST["produit_id"] : 0;

if (empty($action) || $produit_id <= 0) {
    echo json_encode(["success" => false, "message" => "Données invalides"]);
    exit;
}

$host = "localhost";
$dbname = "awakasugu_db";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(["success" => false, "message" => "Erreur de connexion"]);
    exit;
}

if ($action === "add") {
    try {
        $check = $pdo->prepare("SELECT id FROM wishlist WHERE client_id = ? AND produit_id = ?");
        $check->execute([$client_id, $produit_id]);
        if ($check->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO wishlist (client_id, produit_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$client_id, $produit_id]);
        }
        echo json_encode(["success" => true, "message" => "Ajouté aux favoris"]);
    } catch(PDOException $e) {
        echo json_encode(["success" => false, "message" => "Erreur lors de l\'ajout"]);
    }
} elseif ($action === "remove") {
    try {
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE client_id = ? AND produit_id = ?");
        $stmt->execute([$client_id, $produit_id]);
        echo json_encode(["success" => true, "message" => "Retiré des favoris"]);
    } catch(PDOException $e) {
        echo json_encode(["success" => false, "message" => "Erreur lors de la suppression"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Action inconnue"]);
}
?>';
    file_put_contents('../wishlist_ajax.php', $ajax_content);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boutique - IBA Design</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #F8F7F5; 
            color: #1A1A1A;
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.85); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(200,146,42,0.2); }
            50% { box-shadow: 0 0 50px rgba(200,146,42,0.4); }
        }
        @keyframes heartBounce {
            0% { transform: scale(1); }
            25% { transform: scale(1.4); }
            50% { transform: scale(0.85); }
            75% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        @keyframes shimmer {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        @keyframes borderPulse {
            0%, 100% { border-color: rgba(200,146,42,0.2); }
            50% { border-color: rgba(200,146,42,0.5); }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes glowPulse {
            0%, 100% { box-shadow: 0 0 20px rgba(200,146,42,0.1), inset 0 0 20px rgba(200,146,42,0.05); }
            50% { box-shadow: 0 0 40px rgba(200,146,42,0.2), inset 0 0 40px rgba(200,146,42,0.1); }
        }

        /* ===== BANNER VITRINE ===== */
        .banner-vitrine {
            position: relative;
            min-height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: linear-gradient(160deg, #0A0A0A 0%, #1A1A1A 50%, #0D0D0D 100%);
            overflow: hidden;
            padding: 40px 20px;
        }

        .banner-vitrine::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(ellipse at 20% 50%, rgba(200,146,42,0.06) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 50%, rgba(200,146,42,0.04) 0%, transparent 60%);
            pointer-events: none;
        }

        .banner-bg-animation {
            position: absolute;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.25;
            animation: orbFloat 20s ease-in-out infinite alternate;
        }
        .orb-1 { width: 500px; height: 500px; top: -10%; left: -5%; background: radial-gradient(circle, rgba(200,146,42,0.2), transparent 70%); animation-delay: 0s; }
        .orb-2 { width: 400px; height: 400px; bottom: -10%; right: -5%; background: radial-gradient(circle, rgba(200,146,42,0.15), transparent 70%); animation-delay: 5s; }
        .orb-3 { width: 300px; height: 300px; top: 50%; left: 50%; transform: translate(-50%, -50%); background: radial-gradient(circle, rgba(200,146,42,0.1), transparent 70%); animation-delay: 10s; animation-duration: 25s; }

        @keyframes orbFloat {
            0% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(30px, -40px) scale(1.1); }
            50% { transform: translate(-20px, 20px) scale(0.9); }
            75% { transform: translate(40px, 30px) scale(1.05); }
            100% { transform: translate(-30px, -20px) scale(1); }
        }

        .banner-content {
            position: relative;
            z-index: 2;
            max-width: 900px;
            animation: fadeInUp 1s ease-out;
        }

        /* ===== BADGE ===== */
        .banner-badge {
            margin-bottom: 20px;
            animation: glowPulse 3s ease-in-out infinite;
        }
        .badge-pulse {
            display: inline-block;
            padding: 8px 28px;
            background: rgba(200,146,42,0.10);
            border: 1px solid rgba(200,146,42,0.2);
            border-radius: 50px;
            color: #C8922A;
            font-family: 'Inter', sans-serif;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 3px;
            backdrop-filter: blur(10px);
            position: relative;
        }
        .badge-pulse::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 50px;
            padding: 2px;
            background: linear-gradient(90deg, transparent, rgba(200,146,42,0.3), transparent);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: borderGlow 3s ease-in-out infinite;
        }
        @keyframes borderGlow { 0%, 100% { opacity: 0.3; } 50% { opacity: 1; } }

        .banner-title {
            font-family: 'Playfair Display', serif;
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 16px;
            color: #FFFFFF;
            text-shadow: 0 4px 60px rgba(0,0,0,0.3);
        }
        .title-line { display: block; }
        .title-line.gold {
            background: linear-gradient(135deg, #C8922A 0%, #E8B55A 30%, #F4D03F 50%, #E8B55A 70%, #C8922A 100%);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 4s ease-in-out infinite;
        }

        .banner-subtitle {
            font-family: 'Inter', sans-serif;
            font-size: 1.1rem;
            color: rgba(255,255,255,0.5);
            line-height: 1.8;
            margin-bottom: 30px;
            font-weight: 300;
            letter-spacing: 0.5px;
        }

        .banner-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }

        .btn-banner {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 34px;
            border-radius: 60px;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            position: relative;
            overflow: hidden;
        }
        .btn-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
            opacity: 0;
            transition: opacity 0.5s;
        }
        .btn-banner:hover::before { opacity: 1; }

        .btn-secondary {
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.8);
            border: 1.5px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
        }
        .btn-secondary:hover { 
            background: rgba(255,255,255,0.12); 
            border-color: rgba(200,146,42,0.3); 
            color: #C8922A; 
            transform: translateY(-4px); 
        }

        .banner-stats {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .stat-item { text-align: center; }
        .stat-number {
            display: block;
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: #C8922A;
            line-height: 1;
        }
        .stat-label {
            display: block;
            font-family: 'Inter', sans-serif;
            font-size: 0.65rem;
            color: rgba(255,255,255,0.35);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 5px;
        }
        .stat-divider { width: 1px; height: 40px; background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.08), transparent); }

        .container { max-width: 1300px; margin: 0 auto; padding: 0 20px 60px; }

        /* ===== BARRE DE RECHERCHE MODERNE ===== */
        .search-wrapper { 
            max-width: 620px; 
            margin: -25px auto 50px; 
            position: relative; 
            z-index: 10; 
        }
        .search-form {
            display: flex;
            background: white;
            border-radius: 60px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.06);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            border: 2px solid transparent;
            padding: 4px;
        }
        .search-form:focus-within {
            border-color: #C8922A;
            box-shadow: 0 8px 50px rgba(200,146,42,0.12);
            transform: translateY(-3px) scale(1.01);
        }
        .search-form:hover {
            box-shadow: 0 8px 40px rgba(0,0,0,0.08);
        }
        .search-form input { 
            flex: 1; 
            padding: 14px 24px; 
            border: none; 
            outline: none;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            background: transparent;
            color: #1A1A1A;
            border-radius: 60px 0 0 60px;
        }
        .search-form input::placeholder {
            color: #B0B0B0;
            font-weight: 300;
            letter-spacing: 0.3px;
        }
        .search-form button {
            padding: 14px 32px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            border: none;
            color: #1A1A1A;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            border-radius: 60px;
            margin: 2px;
        }
        .search-form button:hover { 
            background: linear-gradient(135deg, #9A6E1A, #C8922A); 
            color: white; 
            transform: scale(1.02);
        }
        .search-form button i {
            font-size: 1.1rem;
        }

        /* ===== CATÉGORIES EN RONDS ===== */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 25px;
            margin-bottom: 60px;
        }
        .cat-card { 
            text-align: center; 
            text-decoration: none; 
            animation: fadeInUp 0.6s ease both; 
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .cat-card:nth-child(1) { animation-delay: 0.05s; }
        .cat-card:nth-child(2) { animation-delay: 0.10s; }
        .cat-card:nth-child(3) { animation-delay: 0.15s; }
        .cat-card:nth-child(4) { animation-delay: 0.20s; }
        .cat-card:nth-child(5) { animation-delay: 0.25s; }
        .cat-card:hover { transform: translateY(-8px) scale(1.02); }
        .cat-img {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 15px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.10);
            transition: all 0.4s ease;
            border: 3px solid transparent;
            position: relative;
        }
        .cat-card:hover .cat-img { 
            border-color: #C8922A; 
            box-shadow: 0 15px 50px rgba(200,146,42,0.2); 
            transform: scale(1.05); 
        }
        .cat-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease; }
        .cat-card:hover .cat-img img { transform: scale(1.1); }
        .cat-img .cat-overlay {
            position: absolute;
            inset: 0;
            background: rgba(200,146,42,0.12);
            opacity: 0;
            transition: opacity 0.3s;
            border-radius: 50%;
        }
        .cat-card:hover .cat-img .cat-overlay { opacity: 1; }
        .cat-name { 
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem; 
            font-weight: 600; 
            color: #1A1A1A;
            transition: color 0.3s;
        }
        .cat-card:hover .cat-name { color: #C8922A; }

        .section-head {
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #0D0D0D;
            position: relative;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, #C8922A, transparent);
            border-radius: 2px;
        }
        .section-title em { color: #C8922A; font-style: italic; }
        .section-link {
            font-family: 'Inter', sans-serif;
            font-size: 0.8rem;
            font-weight: 600;
            color: #C8922A;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .section-link:hover { color: #9A6E1A; transform: translateX(5px); }

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
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            overflow: hidden;
        }
        .subcat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(200,146,42,0.05), transparent);
            opacity: 0;
            transition: opacity 0.4s;
        }
        .subcat-card:hover::before { opacity: 1; }
        .subcat-card:hover { transform: translateY(-5px); border-color: #C8922A; box-shadow: 0 15px 40px rgba(200,146,42,0.12); }
        .subcat-card-name { font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 600; color: #1A1A1A; position: relative; }
        .subcat-card-price { font-family: 'Inter', sans-serif; font-size: 0.7rem; color: #C8922A; margin-top: 5px; display: block; position: relative; }

        /* ===== PROMOTIONS ===== */
        .promo-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 60px;
        }
        .promo-card {
            background: #FFFFFF;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            text-decoration: none;
            border: 1px solid #F0F0F0;
            position: relative;
            animation: scaleIn 0.6s ease both;
        }
        .promo-card:nth-child(1) { animation-delay: 0.05s; }
        .promo-card:nth-child(2) { animation-delay: 0.10s; }
        .promo-card:nth-child(3) { animation-delay: 0.15s; }
        .promo-card:nth-child(4) { animation-delay: 0.20s; }
        .promo-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(0,0,0,0.1); }
        .promo-image {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: #F8F8F8;
        }
        .promo-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease; }
        .promo-card:hover .promo-image img { transform: scale(1.08); }
        .promo-badge-red {
            position: absolute;
            top: 12px;
            left: 12px;
            background: linear-gradient(135deg, #E74C3C, #C0392B);
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 5px 16px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(231,76,60,0.4);
            z-index: 2;
            animation: pulseGlow 2s infinite;
        }
        .promo-info { padding: 14px; text-align: center; }
        .promo-name { font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 500; color: #1A1A1A; margin-bottom: 4px; }
        .promo-prices { display: flex; justify-content: center; align-items: center; gap: 8px; margin-bottom: 8px; }
        .promo-price-new { font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: 700; color: #E74C3C; }
        .promo-price-old { font-family: 'Inter', sans-serif; font-size: 0.75rem; color: #8A99AA; text-decoration: line-through; }
        .btn-promo {
            display: inline-block;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            padding: 7px 20px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        .btn-promo:hover { background: linear-gradient(135deg, #9A6E1A, #C8922A); color: white; transform: translateY(-2px); }

        /* ===== VIDÉOS ===== */
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
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .video-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(0,0,0,0.12); }
        .video-thumbnail {
            position: relative;
            aspect-ratio: 16/9;
            overflow: hidden;
            background: #1A1A1A;
        }
        .video-thumbnail img, .video-thumbnail video { width: 100%; height: 100%; object-fit: cover; }
        .play-btn {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            background: rgba(200,146,42,0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.4s ease;
            box-shadow: 0 4px 20px rgba(200,146,42,0.3);
        }
        .video-card:hover .play-btn { transform: translate(-50%, -50%) scale(1.15); background: #C8922A; box-shadow: 0 8px 30px rgba(200,146,42,0.5); }
        .play-btn i { font-size: 1.4rem; color: white; margin-left: 4px; }
        .video-info { padding: 12px; }
        .video-title { font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 600; color: #1A1A1A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .video-meta { font-family: 'Inter', sans-serif; display: flex; justify-content: space-between; align-items: center; font-size: 0.65rem; color: #C8922A; margin-top: 6px; }

        /* ===== SHOP LAYOUT ===== */
        .shop-layout { display: flex; gap: 45px; }
        .sidebar { width: 270px; flex-shrink: 0; }
        .content { flex: 1; }

        .sidebar-title {
            font-family: 'Inter', sans-serif;
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
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            position: relative;
        }
        .sidebar-list a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: #C8922A;
            border-radius: 2px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .sidebar-list a:hover::before, .sidebar-list a.active::before { opacity: 1; }
        .sidebar-list a:hover, .sidebar-list a.active { background: rgba(200,146,42,0.08); color: #C8922A; transform: translateX(5px); }

        .filters-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .sort-select {
            padding: 10px 22px;
            border: 1.5px solid #E0E0E0;
            border-radius: 40px;
            background: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .sort-select:hover, .sort-select:focus { border-color: #C8922A; outline: none; box-shadow: 0 0 0 3px rgba(200,146,42,0.1); }
        .count { 
            font-family: 'Inter', sans-serif;
            font-size: 0.8rem; 
            color: #888; 
            background: #F0F0F0; 
            padding: 6px 18px; 
            border-radius: 40px;
        }

        /* ===== PRODUCTS GRID ===== */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .product-card {
            text-decoration: none;
            position: relative;
            animation: fadeInUp 0.6s ease both;
            background: transparent;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .product-card:nth-child(1) { animation-delay: 0.05s; }
        .product-card:nth-child(2) { animation-delay: 0.10s; }
        .product-card:nth-child(3) { animation-delay: 0.15s; }
        .product-card:nth-child(4) { animation-delay: 0.20s; }
        .product-card:nth-child(5) { animation-delay: 0.25s; }
        .product-card:nth-child(6) { animation-delay: 0.30s; }

        .product-card:hover {
            transform: translateY(-12px);
        }

        .product-img {
            position: relative;
            aspect-ratio: 3/4;
            overflow: hidden;
            background: #F5F3F0;
            border-radius: 20px;
            padding: 8px;
            transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        .product-img::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 22px;
            padding: 2px;
            background: linear-gradient(135deg, 
                rgba(200,146,42,0.3), 
                rgba(200,146,42,0.1),
                rgba(200,146,42,0.3),
                rgba(200,146,42,0.1)
            );
            background-size: 300% 300%;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: borderPulse 3s ease-in-out infinite;
            z-index: 1;
            pointer-events: none;
        }

        .product-card:hover .product-img {
            box-shadow: 0 20px 60px rgba(200,146,42,0.15), 0 0 0 1px rgba(200,146,42,0.2);
            transform: scale(1.02);
        }

        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            border-radius: 12px;
            position: relative;
            z-index: 2;
        }

        .product-card:hover .product-img img {
            transform: scale(1.05);
        }

        .product-img .shine-effect {
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(255,255,255,0.05) 50%,
                transparent 70%
            );
            background-size: 200% 200%;
            animation: shimmer 6s linear infinite;
            z-index: 3;
            pointer-events: none;
            border-radius: 12px;
        }

        .product-badge-promo {
            position: absolute;
            top: 16px;
            left: 16px;
            background: linear-gradient(135deg, #E74C3C, #C0392B);
            color: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 5px 16px;
            border-radius: 20px;
            z-index: 10;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 20px rgba(231,76,60,0.4);
            animation: pulseGlow 2s infinite;
        }

        .wishlist-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            z-index: 10;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            border: none;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            color: #ccc;
        }
        .wishlist-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            background: rgba(255,255,255,0.95);
        }
        .wishlist-btn i {
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }
        .wishlist-btn.liked {
            color: #E74C3C !important;
            background: rgba(255,255,255,0.9);
        }
        .wishlist-btn.liked i {
            text-shadow: 0 0 30px rgba(231,76,60,0.4);
        }
        .wishlist-btn.loading {
            opacity: 0.5;
            pointer-events: none;
        }

        .product-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 50px 18px 20px;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0) 100%);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            display: flex;
            gap: 10px;
            z-index: 5;
            border-radius: 0 0 20px 20px;
        }
        .product-card:hover .product-overlay {
            opacity: 1;
            transform: translateY(0);
        }

        .btn-overlay {
            flex: 1;
            padding: 10px 8px;
            border-radius: 30px;
            font-family: 'Inter', sans-serif;
            font-size: 0.65rem;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            transition: all 0.4s ease;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-view {
            background: rgba(255,255,255,0.95);
            color: #1A1A1A;
        }
        .btn-view:hover { background: #C8922A; color: white; transform: translateY(-3px); }
        .btn-buy {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
        }
        .btn-buy:hover { background: linear-gradient(135deg, #9A6E1A, #C8922A); color: white; transform: translateY(-3px); }

        .product-info {
            padding: 16px 0 0;
            text-align: center;
            background: transparent;
        }
        .product-title {
            font-family: 'Playfair Display', serif;
            font-size: 0.95rem;
            font-weight: 600;
            color: #1A1A1A;
            transition: color 0.3s ease;
        }
        .product-card:hover .product-title {
            color: #C8922A;
        }
        .product-price {
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            color: #C8922A;
            margin-top: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .product-price .old-price {
            font-size: 0.8rem;
            color: #B0B0B0;
            text-decoration: line-through;
            font-weight: 400;
        }
        .product-price .current-price {
            color: #1A1A1A;
            font-weight: 600;
        }
        .product-price .current-price.promo {
            color: #E74C3C;
        }

        .empty-state { text-align: center; padding: 80px; background: white; border-radius: 24px; border: 1px solid #F0EBE3; }
        .empty-state i { font-size: 4rem; color: #C8922A; margin-bottom: 20px; }
        .empty-state h3 { font-family: 'Playfair Display', serif; color: #1A1A1A; margin-bottom: 10px; }
        .empty-state p { font-family: 'Inter', sans-serif; color: #999; }
        .empty-state .btn-retour { 
            display: inline-block; 
            margin-top: 15px; 
            color: #C8922A; 
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            text-decoration: none;
            padding: 10px 30px;
            border: 2px solid #C8922A;
            border-radius: 30px;
            transition: all 0.3s ease;
        }
        .empty-state .btn-retour:hover { background: #C8922A; color: white; }

        .breadcrumb { margin-bottom: 30px; font-family: 'Inter', sans-serif; font-size: 0.8rem; color: #999; }
        .breadcrumb a { color: #999; text-decoration: none; transition: color 0.3s; }
        .breadcrumb a:hover { color: #C8922A; }
        .breadcrumb span { color: #C8922A; font-weight: 500; }

        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 50px; }
        .pagination a, .pagination span {
            width: 42px; height: 42px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            text-decoration: none;
            color: #666;
            background: white;
            border: 1px solid #E0E0E0;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
        }
        .pagination a:hover, .pagination .active {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            border-color: #C8922A;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(200,146,42,0.25);
        }

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
            animation: fadeInUp 0.3s ease;
        }
        .video-modal.active { display: flex; }
        .modal-content {
            position: relative;
            width: 90%;
            max-width: 1000px;
            background: #1A1A1A;
            border-radius: 20px;
            overflow: hidden;
            animation: scaleIn 0.4s ease;
        }
        .modal-video-container { position: relative; padding-bottom: 56.25%; height: 0; }
        .modal-video-container iframe, .modal-video-container video { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
        .modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 10;
        }
        .modal-close:hover { background: rgba(255,255,255,0.2); transform: rotate(90deg); }
        .modal-info { padding: 20px; color: white; }
        .modal-info h3 { font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: 600; }

        .toast-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1A1A1A;
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 9999;
            border-left: 4px solid #C8922A;
            font-family: 'Inter', sans-serif;
        }
        .toast-notification.show { transform: translateY(0); opacity: 1; }
        .toast-notification i { font-size: 1.3rem; }
        .toast-notification .toast-icon { color: #C8922A; }
        .toast-notification .toast-close { background: none; border: none; color: rgba(255,255,255,0.3); cursor: pointer; font-size: 1.2rem; padding: 0 5px; transition: color 0.3s; }
        .toast-notification .toast-close:hover { color: white; }

        @media (max-width: 1000px) {
            .products-grid { grid-template-columns: repeat(2, 1fr); }
            .categories-grid { grid-template-columns: repeat(3, 1fr); }
            .subcat-grid { grid-template-columns: repeat(2, 1fr); }
            .promo-grid { grid-template-columns: repeat(2, 1fr); }
            .videos-grid { grid-template-columns: repeat(2, 1fr); }
            .banner-title { font-size: 3.2rem; }
            .cat-img { width: 120px; height: 120px; }
        }

        @media (max-width: 800px) {
            .shop-layout { flex-direction: column; }
            .sidebar { width: 100%; }
            .sidebar-list { display: flex; flex-wrap: wrap; gap: 8px; }
            .sidebar-list li { margin-bottom: 0; }
            .cat-img { width: 100px; height: 100px; }
            .banner-vitrine { min-height: 50vh; padding: 30px 15px; }
            .banner-title { font-size: 2.4rem; }
            .banner-subtitle { font-size: 0.9rem; }
            .btn-banner { padding: 12px 20px; font-size: 0.7rem; }
            .banner-stats { gap: 20px; flex-wrap: wrap; }
            .stat-divider { display: none; }
            .stat-number { font-size: 1.3rem; }
            .search-wrapper { max-width: 100%; margin: -20px 15px 30px; }
            .categories-grid { gap: 15px; }
            .search-form input { padding: 12px 18px; font-size: 0.85rem; }
            .search-form button { padding: 12px 18px; font-size: 0.75rem; }
        }

        @media (max-width: 600px) {
            .products-grid { grid-template-columns: 1fr; max-width: 350px; margin: 0 auto; }
            .categories-grid { grid-template-columns: repeat(2, 1fr); }
            .subcat-grid { grid-template-columns: 1fr; }
            .promo-grid { grid-template-columns: 1fr; }
            .videos-grid { grid-template-columns: 1fr; }
            .banner-title { font-size: 2rem; }
            .banner-actions { flex-direction: column; align-items: center; }
            .btn-banner { width: 100%; justify-content: center; }
            .cat-img { width: 90px; height: 90px; }
            .toast-notification { bottom: 20px; right: 20px; left: 20px; padding: 14px 18px; }
        }
    </style>
</head>
<body>

<!-- ===== BANNER VITRINE ===== -->
<div class="banner-vitrine">
    <div class="banner-bg-animation">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>
    <div class="banner-content">
        <div class="banner-badge">
            <span class="badge-pulse">✦ Collection Exclusive</span>
        </div>
        <h1 class="banner-title">
            <span class="title-line">Notre</span>
            <span class="title-line gold">Collection</span>
        </h1>
        <p class="banner-subtitle">
            Découvrez notre sélection de créations uniques, <br>alliant tradition et modernité.
        </p>
        <div class="banner-actions">
            <a href="#promotions" class="btn-banner btn-secondary">
                <i class="bi bi-percent"></i> Voir les promos
            </a>
        </div>
        <div class="banner-stats">
            <div class="stat-item">
                <span class="stat-number" data-count="150">0</span>
                <span class="stat-label">Modèles exclusifs</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
                <span class="stat-number" data-count="98">0</span>
                <span class="stat-label">% Satisfait client</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
                <span class="stat-number" data-count="15">0</span>
                <span class="stat-label">Années d'excellence</span>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- ===== BARRE DE RECHERCHE MODERNE ===== -->
    <div class="search-wrapper">
        <form class="search-form" method="GET" action="">
            <?php if ($categorie_id > 0): ?>
                <input type="hidden" name="categorie" value="<?= $categorie_id ?>">
            <?php endif; ?>
            <?php if ($sous_categorie_id > 0): ?>
                <input type="hidden" name="sous_categorie" value="<?= $sous_categorie_id ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Que recherchez-vous ?" value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="bi bi-search"></i> Rechercher</button>
        </form>
    </div>

    <?php if ($categorie_id == 0 && empty($search)): ?>
        <!-- Catégories principales -->
        <div class="categories-grid">
            <?php foreach ($categories_masonry as $cat): ?>
                <a href="?categorie=<?= $cat['id'] ?>" class="cat-card">
                    <div class="cat-img">
                        <img src="<?= getCategoryImage($cat) ?>" 
                             alt="<?= htmlspecialchars($cat['nom']) ?>"
                             onerror="this.src='https://placehold.co/200x200/C8922A/FFF?text=<?= urlencode(substr($cat['nom'], 0, 1)) ?>'">
                        <div class="cat-overlay"></div>
                    </div>
                    <div class="cat-name"><?= htmlspecialchars($cat['nom']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Abayas -->
        <?php if (isset($subcategories_data[1])): ?>
        <div class="section-head">
            <div>
                <h2 class="section-title">Nos <em>abayas</em></h2>
            </div>
            <a href="?categorie=1" class="section-link">Voir toutes →</a>
        </div>
        <div class="subcat-grid">
            <?php foreach ($subcategories_data[1] as $sub): ?>
                <a href="?categorie=1&sous_categorie=<?= $sub['id'] ?>" class="subcat-card">
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
                    <div class="subcat-card-price">Collection exclusive</div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Foulards & Turbans -->
        <?php if (isset($subcategories_data[2])): ?>
        <div class="section-head">
            <div>
                <h2 class="section-title">Nos <em>foulards & turbans</em></h2>
            </div>
            <a href="?categorie=2" class="section-link">Voir toutes →</a>
        </div>
        <div class="subcat-grid">
            <?php foreach ($subcategories_data[2] as $sub): ?>
                <a href="?categorie=2&sous_categorie=<?= $sub['id'] ?>" class="subcat-card">
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
                    <div class="subcat-card-price">Collection exclusive</div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Sacs -->
        <?php if (isset($subcategories_data[3])): ?>
        <div class="section-head">
            <div>
                <h2 class="section-title">Nos <em>sacs</em></h2>
            </div>
            <a href="?categorie=3" class="section-link">Voir toutes →</a>
        </div>
        <div class="subcat-grid">
            <?php foreach ($subcategories_data[3] as $sub): ?>
                <a href="?categorie=3&sous_categorie=<?= $sub['id'] ?>" class="subcat-card">
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
                    <div class="subcat-card-price">Collection exclusive</div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Chaussures -->
        <?php if (isset($subcategories_data[4])): ?>
        <div class="section-head">
            <div>
                <h2 class="section-title">Nos <em>chaussures</em></h2>
            </div>
            <a href="?categorie=4" class="section-link">Voir toutes →</a>
        </div>
        <div class="subcat-grid">
            <?php foreach ($subcategories_data[4] as $sub): ?>
                <a href="?categorie=4&sous_categorie=<?= $sub['id'] ?>" class="subcat-card">
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
                    <div class="subcat-card-price">Collection exclusive</div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Prêt-à-porter -->
        <?php if (isset($subcategories_data[5])): ?>
        <div class="section-head">
            <div>
                <h2 class="section-title">Nos <em>prêt-à-porter</em></h2>
            </div>
            <a href="?categorie=5" class="section-link">Voir toutes →</a>
        </div>
        <div class="subcat-grid">
            <?php foreach ($subcategories_data[5] as $sub): ?>
                <a href="?categorie=5&sous_categorie=<?= $sub['id'] ?>" class="subcat-card">
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
                    <div class="subcat-card-price">Collection exclusive</div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== SECTION PROMOTIONS ===== -->
        <div class="section-head" style="margin-top: 20px;" id="promotions">
            <div>
                <h2 class="section-title">🔥 Nos <em>promotions</em></h2>
                <p style="font-family: 'Inter', sans-serif; font-size: 0.85rem; color: #8A99AA; margin-top: 5px;">Profitez des offres spéciales du moment</p>
            </div>
            <a href="promotions.php" class="section-link">Voir toutes les promos →</a>
        </div>
        
        <?php if(!empty($produits_promo)): ?>
        <div class="promo-grid">
            <?php foreach($produits_promo as $p): 
                $reduction = round((($p['prix'] - $p['prix_promo']) / $p['prix']) * 100);
                $img = getProductImage($p['image_principale'] ?? '');
            ?>
            <a href="produit.php?id=<?= $p['id'] ?>" class="promo-card">
                <div class="promo-image">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>" loading="lazy" onerror="this.src='https://placehold.co/400x500/F5F5F5/C8922A?text=<?= urlencode($p['nom'])?>'">
                    <div class="promo-badge-red">-<?= $reduction ?>%</div>
                </div>
                <div class="promo-info">
                    <div class="promo-name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="promo-prices">
                        <span class="promo-price-new"><?= number_format($p['prix_promo'], 0, ',', ' ') ?> FCFA</span>
                        <span class="promo-price-old"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</span>
                    </div>
                    <span class="btn-promo">Profiter</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 20px; border: 1px solid #F0EBE3; font-family: 'Inter', sans-serif;">
            <i class="bi bi-tag" style="font-size: 2.5rem; color: #CCC;"></i>
            <p style="margin-top: 10px; color: #8A99AA;">Aucune promotion en cours pour le moment.</p>
            <a href="catalogue.php" style="color:#C8922A;font-weight:600;text-decoration:none;">Voir tous les produits →</a>
        </div>
        <?php endif; ?>

        <!-- Vidéos -->
        <div class="section-head" style="margin-top: 20px;">
            <div>
                <h2 class="section-title">Les vidéos d'<em>Awa Doumbia</em></h2>
                <p style="font-family: 'Inter', sans-serif; font-size: 0.85rem; color: #8A99AA; margin-top: 5px;">Découvrez les actualités et conseils de notre ambassadrice</p>
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
        <div style="text-align: center; padding: 40px; background: white; border-radius: 20px; border: 1px solid #F0EBE3; font-family: 'Inter', sans-serif;">
            <i class="bi bi-camera-reels" style="font-size: 2rem; color: #C8922A;"></i>
            <p style="margin-top: 10px; color: #8A99AA;">Bientôt des vidéos exclusives !</p>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ===== PAGE CATÉGORIE AVEC PRODUITS ===== -->
        <div class="breadcrumb">
            <a href="catalogue.php"><i class="bi bi-house"></i> Accueil</a> 
            <i class="bi bi-chevron-right" style="font-size: 0.6rem; margin: 0 8px;"></i>
            <span><?= htmlspecialchars($categorie_nom) ?></span>
            <?php if ($sous_categorie_id > 0): ?> 
                <i class="bi bi-chevron-right" style="font-size: 0.6rem; margin: 0 8px;"></i>
                <span><?= htmlspecialchars($sub_nom_affiché) ?></span>
            <?php endif; ?>
        </div>

        <div class="shop-layout">
            <div class="sidebar">
                <div class="sidebar-title">
                    <i class="bi bi-grid-3x3-gap-fill" style="color: #C8922A;"></i> 
                    <?= htmlspecialchars($categorie_nom) ?>
                </div>
                <ul class="sidebar-list">
                    <li>
                        <a href="?categorie=<?= $categorie_id ?>" class="<?= ($sous_categorie_id == 0) ? 'active' : '' ?>">
                            <i class="bi bi-grid"></i> Tous les produits
                        </a>
                    </li>
                    <?php if (isset($subcategories_data[$categorie_id])): ?>
                        <?php foreach ($subcategories_data[$categorie_id] as $sub): ?>
                            <li>
                                <a href="?categorie=<?= $categorie_id ?>&sous_categorie=<?= $sub['id'] ?>" class="<?= ($sous_categorie_id == $sub['id']) ? 'active' : '' ?>">
                                    <i class="bi bi-tag"></i> <?= htmlspecialchars($sub['nom']) ?>
                                </a>
                            </li>
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
                    <div class="count"><i class="bi bi-box-seam"></i> <?= $total_products ?> produits</div>
                </div>

                <?php if (empty($produits)): ?>
                    <div class="empty-state">
                        <i class="bi bi-box-seam"></i>
                        <h3>Aucun produit trouvé</h3>
                        <p>Dans cette catégorie pour le moment</p>
                        <a href="catalogue.php" class="btn-retour">← Retour à la boutique</a>
                    </div>
                <?php else: ?>
                    <div class="products-grid" id="collection">
                        <?php foreach ($produits as $p): ?>
                            <?php
                            $img = getProductImage($p['image_principale'] ?? '');
                            $prix_affiché = $p['prix'];
                            $prix_ancien = null;
                            $est_promo = false;
                            
                            if (isset($p['est_promo']) && $p['est_promo'] == 1 && 
                                isset($p['prix_promo']) && $p['prix_promo'] > 0 && 
                                $p['prix_promo'] < $p['prix']) {
                                $prix_affiché = $p['prix_promo'];
                                $prix_ancien = $p['prix'];
                                $est_promo = true;
                            }
                            
                            $cat_nom = '';
                            if (!empty($p['categorie_id'])) {
                                $cat_nom = getCategorieNom($p['categorie_id'], $categories_masonry);
                            }
                            
                            $pourcentage_promo = 0;
                            if ($est_promo && $prix_ancien > 0) {
                                $pourcentage_promo = round((1 - $prix_affiché / $prix_ancien) * 100);
                            }
                            
                            $is_liked = in_array($p['id'], $wishlist_ids);
                            ?>
                            <div class="product-card" data-product-id="<?= $p['id'] ?>">
                                <div class="product-img">
                                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>" loading="lazy">
                                    <div class="shine-effect"></div>
                                    
                                    <?php if ($est_promo): ?>
                                        <div class="product-badge-promo">-<?= $pourcentage_promo ?>%</div>
                                    <?php endif; ?>
                                    
                                    <button class="wishlist-btn <?= $is_liked ? 'liked' : '' ?>" 
                                            onclick="toggleWishlist(event, <?= $p['id'] ?>, this)"
                                            title="<?= $is_liked ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>"
                                            style="<?= $is_liked ? 'color:#E74C3C;' : '' ?>">
                                        <i class="bi <?= $is_liked ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                                    </button>
                                    
                                    <div class="product-overlay">
                                        <a href="produit.php?id=<?= $p['id'] ?>" class="btn-overlay btn-view">
                                            <i class="bi bi-eye"></i> Détails
                                        </a>
                                        <a href="commande_directe.php?id=<?= $p['id'] ?>" class="btn-overlay btn-buy">
                                            <i class="bi bi-cart-plus"></i> Acheter
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="product-info">
                                    <div class="product-title"><?= htmlspecialchars($p['nom']) ?></div>
                                    <div class="product-price">
                                        <?php if ($est_promo): ?>
                                            <span class="old-price"><?= number_format($prix_ancien, 0, ',', ' ') ?> FCFA</span>
                                            <span class="current-price promo"><?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA</span>
                                        <?php else: ?>
                                            <span class="current-price"><?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA</span>
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

<!-- Toast Notification -->
<div id="toast" class="toast-notification">
    <i class="bi bi-heart-fill toast-icon"></i>
    <span id="toastMessage">Ajouté aux favoris</span>
    <button class="toast-close" onclick="closeToast()">&times;</button>
</div>

<script>
// ============================================
// WISHLIST FUNCTIONS - CORRIGÉES
// ============================================
function toggleWishlist(event, productId, button) {
    event.preventDefault();
    event.stopPropagation();
    
    const btn = button || event.currentTarget;
    const icon = btn.querySelector('i');
    const isLiked = btn.classList.contains('liked');
    const action = isLiked ? 'remove' : 'add';
    
    <?php if (!isset($_SESSION['client_id'])): ?>
        showToast('Veuillez vous connecter pour ajouter aux favoris', 'warning');
        return;
    <?php endif; ?>
    
    // Changement visuel IMMÉDIAT
    if (action === 'add') {
        btn.classList.add('liked');
        icon.className = 'bi bi-heart-fill';
        btn.style.color = '#E74C3C';
    } else {
        btn.classList.remove('liked');
        icon.className = 'bi bi-heart';
        btn.style.color = '#ccc';
    }
    
    btn.classList.add('loading');
    
    fetch('../wishlist_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=' + action + '&produit_id=' + productId
    })
    .then(response => response.json())
    .then(data => {
        btn.classList.remove('loading');
        
        if (data.success) {
            if (action === 'add') {
                showToast('❤️ Ajouté à vos favoris', 'success');
            } else {
                showToast('Retiré de vos favoris', 'info');
            }
        } else {
            // En cas d'erreur, on revient à l'état précédent
            if (action === 'add') {
                btn.classList.remove('liked');
                icon.className = 'bi bi-heart';
                btn.style.color = '#ccc';
            } else {
                btn.classList.add('liked');
                icon.className = 'bi bi-heart-fill';
                btn.style.color = '#E74C3C';
            }
            showToast(data.message || 'Une erreur est survenue', 'error');
        }
    })
    .catch(error => {
        btn.classList.remove('loading');
        // En cas d'erreur, on revient à l'état précédent
        if (action === 'add') {
            btn.classList.remove('liked');
            icon.className = 'bi bi-heart';
            btn.style.color = '#ccc';
        } else {
            btn.classList.add('liked');
            icon.className = 'bi bi-heart-fill';
            btn.style.color = '#E74C3C';
        }
        showToast('Erreur de connexion au serveur', 'error');
        console.error('Error:', error);
    });
}

// Initialisation des cœurs
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.wishlist-btn.liked').forEach(function(btn) {
        btn.style.color = '#E74C3C';
        const icon = btn.querySelector('i');
        if (icon) icon.className = 'bi bi-heart-fill';
    });
});

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
    
    if (type === 'success') {
        if (icon) icon.className = 'bi bi-heart-fill toast-icon';
        toast.style.borderLeftColor = '#C8922A';
    } else if (type === 'error') {
        if (icon) icon.className = 'bi bi-x-circle-fill toast-icon';
        toast.style.borderLeftColor = '#E74C3C';
    } else if (type === 'warning') {
        if (icon) icon.className = 'bi bi-exclamation-triangle-fill toast-icon';
        toast.style.borderLeftColor = '#F39C12';
    } else {
        if (icon) icon.className = 'bi bi-info-circle-fill toast-icon';
        toast.style.borderLeftColor = '#3498DB';
    }
    
    toast.classList.add('show');
    
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

function closeToast() {
    const toast = document.getElementById('toast');
    if (toast) toast.classList.remove('show');
    clearTimeout(toastTimeout);
}

// ============================================
// VIDEO MODAL
// ============================================
function openVideoModal(url, title, type) {
    const container = document.getElementById('modalVideoContainer');
    if (!container) return;
    
    if (type === 'video') {
        container.innerHTML = '<video controls autoplay><source src="' + url + '" type="video/mp4">Votre navigateur ne supporte pas la lecture vidéo.</video>';
    } else {
        container.innerHTML = '<iframe src="' + url + '" frameborder="0" allowfullscreen></iframe>';
    }
    const titleEl = document.getElementById('modalTitle');
    if (titleEl) titleEl.innerText = title;
    
    const modal = document.getElementById('videoModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeVideoModal() {
    const container = document.getElementById('modalVideoContainer');
    if (container) container.innerHTML = '';
    
    const modal = document.getElementById('videoModal');
    if (modal) modal.classList.remove('active');
    document.body.style.overflow = 'auto';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeVideoModal();
});

// ============================================
// COMPTEURS ANIMÉS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    const animateNumber = (element) => {
        const target = parseInt(element.dataset.count);
        let current = 0;
        const increment = target / 60;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.round(current) + (target > 100 ? '' : '%');
        }, 30);
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;
                if (!element.dataset.animated) {
                    element.dataset.animated = 'true';
                    animateNumber(element);
                }
            }
        });
    }, { threshold: 0.5 });
    
    statNumbers.forEach(num => observer.observe(num));
});
</script>

<?php require_once '../includes/footer.php'; ?>