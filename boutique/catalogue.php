<?php
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// TRAITEMENT AJOUT AU PANIER (AJAX) - DOIT ÊTRE EN PREMIER
// ============================================
if (isset($_POST['action']) && $_POST['action'] == 'ajouter_panier') {
    header('Content-Type: application/json');
    
    $host = 'localhost';
    $dbname = 'awakasugu_db';
    $user = 'root';
    $pass = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion BDD']);
        exit;
    }
    
    $produit_id = (int)$_POST['produit_id'];
    $quantite = (int)$_POST['quantite'];
    $couleur_id = isset($_POST['couleur_id']) ? (int)$_POST['couleur_id'] : 0;
    $taille_id = isset($_POST['taille_id']) ? (int)$_POST['taille_id'] : 0;
    
    if ($produit_id <= 0 || $quantite <= 0) {
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND est_visible = 1");
    $stmt->execute([$produit_id]);
    $produit = $stmt->fetch();
    
    if (!$produit) {
        echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
        exit;
    }

    // Sécurité : les couleurs/tailles doivent appartenir à ce produit.
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM produit_couleurs WHERE produit_id = ?");
        $stmt->execute([$produit_id]);
        $nb_couleurs = (int)$stmt->fetchColumn();

        if ($nb_couleurs > 0 && $couleur_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Veuillez choisir une couleur']);
            exit;
        }

        if ($couleur_id > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM produit_couleurs WHERE produit_id = ? AND couleur_id = ?");
            $stmt->execute([$produit_id, $couleur_id]);
            if ((int)$stmt->fetchColumn() === 0) {
                echo json_encode(['success' => false, 'message' => 'Couleur invalide pour ce produit']);
                exit;
            }
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM produit_tailles WHERE produit_id = ?");
        $stmt->execute([$produit_id]);
        $nb_tailles = (int)$stmt->fetchColumn();

        if ($nb_tailles > 0 && $taille_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Veuillez choisir une taille']);
            exit;
        }

        if ($taille_id > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM produit_tailles WHERE produit_id = ? AND taille_id = ?");
            $stmt->execute([$produit_id, $taille_id]);
            if ((int)$stmt->fetchColumn() === 0) {
                echo json_encode(['success' => false, 'message' => 'Taille invalide pour ce produit']);
                exit;
            }
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Impossible de vérifier les variantes du produit']);
        exit;
    }

    $prix = ($produit['prix_promo'] && $produit['prix_promo'] > 0 && $produit['prix_promo'] < $produit['prix']) 
            ? $produit['prix_promo'] 
            : $produit['prix'];
    
    $couleur_nom = '';
    $couleur_hex = '';
    $taille_nom = '';
    
    if ($couleur_id > 0) {
        $stmt = $pdo->prepare("SELECT nom, code_hex FROM couleurs WHERE id = ?");
        $stmt->execute([$couleur_id]);
        $couleur = $stmt->fetch();
        $couleur_nom = $couleur['nom'] ?? '';
        $couleur_hex = $couleur['code_hex'] ?? '';
    }
    
    if ($taille_id > 0) {
        $stmt = $pdo->prepare("SELECT nom FROM tailles WHERE id = ?");
        $stmt->execute([$taille_id]);
        $taille = $stmt->fetch();
        $taille_nom = $taille['nom'] ?? '';
    }
    
    $cle_panier = $produit_id . '_' . $couleur_id . '_' . $taille_id;
    
    if (!isset($_SESSION['panier'])) {
        $_SESSION['panier'] = [];
    }
    
    if (isset($_SESSION['panier'][$cle_panier])) {
        $_SESSION['panier'][$cle_panier]['quantite'] += $quantite;
    } else {
        $_SESSION['panier'][$cle_panier] = [
            'id' => $produit['id'],
            'nom' => $produit['nom'],
            'prix' => $prix,
            'quantite' => $quantite,
            'couleur_id' => $couleur_id,
            'couleur_nom' => $couleur_nom,
            'couleur_hex' => $couleur_hex,
            'taille_id' => $taille_id,
            'taille_nom' => $taille_nom,
            'image' => $produit['image_principale']
        ];
    }
    
    if (isset($_SESSION['client_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM panier WHERE client_id = ?");
            $stmt->execute([$_SESSION['client_id']]);
            
            if (!empty($_SESSION['panier'])) {
                $stmt = $pdo->prepare("INSERT INTO panier (client_id, produit_id, quantite) VALUES (?, ?, ?)");
                foreach ($_SESSION['panier'] as $item) {
                    $stmt->execute([$_SESSION['client_id'], $item['id'], $item['quantite']]);
                }
            }
        } catch(PDOException $e) {
            error_log("Erreur sauvegarde panier BDD: " . $e->getMessage());
        }
    }
    
    $total = 0;
    $count_total = 0;
    foreach ($_SESSION['panier'] as $item) {
        $total += $item['prix'] * $item['quantite'];
        $count_total += $item['quantite'];
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Produit ajouté au panier',
        'total' => $total,
        'count' => $count_total
    ]);
    exit;
}

// ============================================
// DÉTAIL PRODUIT POUR LE TIROIR RAPIDE (AJAX)
// ============================================
if (isset($_POST['action']) && $_POST['action'] == 'get_produit_detail') {
    header('Content-Type: application/json');

    $host = 'localhost';
    $dbname = 'awakasugu_db';
    $user = 'root';
    $pass = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion BDD']);
        exit;
    }

    $produit_id = isset($_POST['produit_id']) ? (int)$_POST['produit_id'] : 0;
    if ($produit_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Produit invalide']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND est_visible = 1");
    $stmt->execute([$produit_id]);
    $p = $stmt->fetch();

    if (!$p) {
        echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
        exit;
    }

    // Couleurs/tailles réellement liées à CE produit via les tables de liaison
    $couleurs = [];
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.nom, c.code_hex
            FROM produit_couleurs pc
            INNER JOIN couleurs c ON c.id = pc.couleur_id
            WHERE pc.produit_id = ?
            ORDER BY c.nom ASC
        ");
        $stmt->execute([$produit_id]);
        $couleurs = $stmt->fetchAll();
    } catch(PDOException $e) {
        $couleurs = [];
    }

    $tailles = [];
    try {
        $stmt = $pdo->prepare("
            SELECT t.id, t.nom
            FROM produit_tailles pt
            INNER JOIN tailles t ON t.id = pt.taille_id
            WHERE pt.produit_id = ?
            ORDER BY t.id ASC
        ");
        $stmt->execute([$produit_id]);
        $tailles = $stmt->fetchAll();
    } catch(PDOException $e) {
        $tailles = [];
    }

    $est_promo = (isset($p['est_promo']) && $p['est_promo'] == 1 && isset($p['prix_promo']) && $p['prix_promo'] > 0 && $p['prix_promo'] < $p['prix']);
    $prix_affiche = $est_promo ? (float)$p['prix_promo'] : (float)$p['prix'];
    $prix_ancien = $est_promo ? (float)$p['prix'] : null;

    echo json_encode([
        'success' => true,
        'id' => (int)$p['id'],
        'nom' => $p['nom'],
        'image' => getProductImage($p['image_principale'] ?? ''),
        'prix' => $prix_affiche,
        'prix_ancien' => $prix_ancien,
        'stock' => (int)($p['stock'] ?? 0),
        'couleurs' => $couleurs,
        'tailles' => $tailles,
    ]);
    exit;
}

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
    $sql .= " AND p.nom LIKE ?";
    $term = "%" . $search . "%";
    $params[] = $term;
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

// ✅ OPTIMISATION : Comptage avec la même requête
$count_sql = str_replace("SELECT p.*", "SELECT COUNT(*) as total", $sql);
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_products = (int)$stmt_count->fetchColumn();
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
}

// ============================================
// RÉCUPÉRATION DES VIDÉOS POUR LA SECTION "NOS VIDÉOS"
// ============================================
function getYoutubeId($url) {
    if (empty($url)) return '';
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/', $url, $matches);
    return $matches[1] ?? '';
}
function getYoutubeThumb($url) {
    $id = getYoutubeId($url);
    return !empty($id) ? "https://img.youtube.com/vi/{$id}/hqdefault.jpg" : '';
}
function isLocalVideo($video) {
    if (!empty($video['fichier_video'])) {
        $path = '../uploads/videos/' . $video['fichier_video'];
        if (file_exists($path)) return true;
        if (($video['type'] ?? '') == 'local') return true;
    }
    return false;
}
function getVideoFileUrl($video) {
    if (!empty($video['fichier_video'])) {
        return '../uploads/videos/' . $video['fichier_video'];
    }
    return '#';
}

$videos_apercu = [];
try {
    $stmt = $pdo->query("SELECT * FROM videos WHERE est_active = 1 ORDER BY created_at DESC LIMIT 10");
    $videos_apercu = $stmt->fetchAll();
} catch(PDOException $e) {
    $videos_apercu = [];
}

// ============================================
// PRODUITS RECOMMANDÉS ("Vous aimerez aussi") — mélange aléatoire
// ============================================
$produits_recommandes = [];
if (!empty($produits)) {
    try {
        $exclude_ids = array_column($produits, 'id');
        $placeholders_ex = implode(',', array_fill(0, count($exclude_ids), '?'));
        $sql_reco = "SELECT * FROM produits WHERE est_visible = 1 AND id NOT IN ($placeholders_ex) ORDER BY RAND() LIMIT 8";
        $stmt_reco = $pdo->prepare($sql_reco);
        $stmt_reco->execute($exclude_ids);
        $produits_recommandes = $stmt_reco->fetchAll();
    } catch(PDOException $e) {
        $produits_recommandes = [];
    }
}

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

// ============================================
// COMPTEUR PANIER POUR LE HEADER
// ============================================
$cart_count = 0;
if (isset($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $item) {
        $cart_count += $item['quantite'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boutique - IBA Design</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #F8F7F5; 
            color: #1A1A1A;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== BANNIÈRE ===== */
        .banner-vitrine {
            position: relative;
            min-height: 45vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            padding: 40px 20px;
            background: #0A0A0A;
        }
        .banner-vitrine .banner-bg-image {
            position: absolute;
            inset: 0;
            z-index: 1;
            overflow: hidden;
        }
        .banner-vitrine .banner-bg-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: blur(4px) brightness(0.35);
            transform: scale(1.05);
        }
        .banner-vitrine .banner-bg-image .overlay-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.6) 50%, rgba(0,0,0,0.85) 100%);
        }
        .banner-content {
            position: relative;
            z-index: 3;
            max-width: 800px;
            animation: fadeInUp 0.8s ease-out;
        }
        .banner-badge { margin-bottom: 16px; }
        .badge-pulse {
            display: inline-block;
            padding: 6px 24px;
            background: rgba(200,146,42,0.12);
            border: 1px solid rgba(200,146,42,0.2);
            border-radius: 50px;
            color: #C8922A;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .banner-title {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 12px;
            color: #FFFFFF;
        }
        .title-line { display: block; }
        .title-line.gold {
            color: #C8922A;
        }
        .banner-subtitle {
            font-size: 1rem;
            color: rgba(255,255,255,0.7);
            line-height: 1.6;
            margin-bottom: 24px;
            font-weight: 300;
        }
        .banner-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        .btn-banner {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1.5px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.8);
        }
        .btn-banner.gold-border {
            background: rgba(200,146,42,0.12);
            border-color: rgba(200,146,42,0.3);
        }
        .btn-banner.gold-border:hover {
            background: rgba(200,146,42,0.25);
            border-color: #C8922A;
            transform: translateY(-2px);
        }
        .banner-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .stat-item { text-align: center; }
        .stat-number {
            display: block;
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #C8922A;
            line-height: 1;
        }
        .stat-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.35);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .stat-divider { width: 1px; height: 30px; background: rgba(255,255,255,0.08); }

        /* CONTENEUR PRINCIPAL */
        .container { 
            max-width: 1300px; 
            margin: 0 auto; 
            padding: 40px 20px 40px; 
        }

        /* ===== BANDEAU DE RÉASSURANCE ===== */
        .trust-bar {
            display: flex;
            gap: 24px;
            justify-content: center;
            flex-wrap: wrap;
            background: #fff;
            border: 1px solid #F0EBE3;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 30px;
        }
        .trust-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #444;
            white-space: nowrap;
        }
        .trust-item i { color: #C8922A; font-size: 1rem; }
        @media (max-width: 700px) {
            .trust-bar { justify-content: flex-start; overflow-x: auto; gap: 18px; padding: 14px 16px; }
        }

        /* ===== CATEGORIES ===== */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        .cat-card { 
            text-align: center; 
            text-decoration: none; 
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        .cat-card:hover { transform: translateY(-5px); }
        .cat-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            border: 2px solid transparent;
            transition: border-color 0.3s ease;
        }
        .cat-card:hover .cat-img { 
            border-color: #C8922A; 
        }
        .cat-img img { width: 100%; height: 100%; object-fit: cover; }
        .cat-name { 
            font-size: 0.85rem; 
            font-weight: 600; 
            color: #1A1A1A;
            transition: color 0.3s;
        }
        .cat-card:hover .cat-name { color: #C8922A; }

        .section-head {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #0D0D0D;
            position: relative;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 40px;
            height: 3px;
            background: #C8922A;
            border-radius: 2px;
        }
        .section-title em { color: #C8922A; font-style: italic; }
        .section-link {
            font-size: 0.8rem;
            font-weight: 600;
            color: #C8922A;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
        }
        .section-link:hover { color: #9A6E1A; }

        .subcat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 16px;
            margin-bottom: 40px;
        }
        .subcat-card {
            background: white;
            border-radius: 12px;
            padding: 14px;
            text-decoration: none;
            text-align: center;
            border: 1px solid #F0EBE3;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .subcat-card:hover {
            transform: translateY(-3px);
            border-color: #C8922A;
            box-shadow: 0 10px 30px rgba(200,146,42,0.08);
        }
        .subcat-card-name { font-size: 0.82rem; font-weight: 600; color: #1A1A1A; }
        .subcat-card-price { font-size: 0.65rem; color: #C8922A; margin-top: 4px; display: block; }

        /* ===== PROMOTIONS ===== */
        .promo-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        .promo-card {
            background: #FFFFFF;
            border-radius: 14px;
            overflow: hidden;
            transition: all 0.3s ease;
            text-decoration: none;
            border: 1px solid #F0F0F0;
        }
        .promo-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.08); }
        .promo-image {
            position: relative;
            height: 200px;
            overflow: hidden;
            background: #F8F8F8;
        }
        .promo-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
        .promo-card:hover .promo-image img { transform: scale(1.05); }
        .promo-badge-red {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #E74C3C;
            color: white;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 20px;
        }
        .promo-info { padding: 12px; text-align: center; }
        .promo-name { font-size: 0.82rem; font-weight: 500; color: #1A1A1A; margin-bottom: 4px; }
        .promo-prices { display: flex; justify-content: center; align-items: center; gap: 6px; margin-bottom: 6px; }
        .promo-price-new { font-size: 0.9rem; font-weight: 700; color: #E74C3C; }
        .promo-price-old { font-size: 0.7rem; color: #8A99AA; text-decoration: line-through; }
        .btn-promo {
            display: inline-block;
            background: #C8922A;
            color: #fff;
            padding: 5px 16px;
            border-radius: 30px;
            font-size: 0.6rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s;
        }
        .btn-promo:hover { background: #9A6E1A; }

        /* ============================================
           SECTION "NOS VIDÉOS" — cartes reels
           ============================================ */
        .videos-section { margin-top: 10px; margin-bottom: 40px; }
        .videos-scroll {
            display: flex;
            gap: 14px;
            overflow-x: auto;
            padding-bottom: 10px;
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x proximity;
        }
        .video-reel-card {
            flex: 0 0 150px;
            scroll-snap-align: start;
            position: relative;
            aspect-ratio: 9 / 16;
            border-radius: 18px;
            overflow: hidden;
            background: #0D0D0D;
            text-decoration: none;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transition: transform 0.35s ease, box-shadow 0.35s ease;
        }
        .video-reel-card:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 16px 40px rgba(0,0,0,0.25);
        }
        .video-reel-card video,
        .video-reel-card img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .video-reel-play {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.15);
            transition: opacity 0.3s ease;
        }
        .video-reel-card:hover .video-reel-play { opacity: 0; }
        .video-reel-play i {
            font-size: 2.1rem;
            color: rgba(255,255,255,0.92);
            text-shadow: 0 2px 12px rgba(0,0,0,0.6);
        }
        .video-reel-overlay {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            padding: 30px 10px 10px;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, transparent 100%);
            z-index: 2;
        }
        .video-reel-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .video-reel-views {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .video-reel-card.empty-thumb {
            background: linear-gradient(135deg, #1A1A1A, #0D0D0D);
        }
        @media (max-width: 600px) {
            .video-reel-card { flex-basis: 120px; }
        }

        /* ===== SHOP LAYOUT ===== */
        .shop-layout { display: block; }
        .content { flex: 1; }

        /* ===== SOUS-CATÉGORIES : barre collante horizontale ===== */
        .sidebar {
            width: 100%;
            position: sticky;
            top: 75px;
            z-index: 500;
            background: #F8F7F5;
            padding: 14px 0 16px;
            margin-bottom: 24px;
            border-bottom: 1px solid #EFE9E0;
        }
        .sidebar-title {
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1A1A1A;
        }
        .sidebar-list {
            list-style: none;
            display: flex;
            gap: 8px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding: 2px 2px 4px;
            margin: 0;
            scrollbar-width: none;
        }
        .sidebar-list::-webkit-scrollbar { display: none; }
        .sidebar-list li { margin: 0; flex-shrink: 0; }
        .sidebar-list a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            white-space: nowrap;
            background: #fff;
            border: 1px solid #EFE9E0;
            border-radius: 30px;
            color: #666;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .sidebar-list a:hover,
        .sidebar-list a.active {
            background: #C8922A;
            border-color: #C8922A;
            color: #fff;
        }

        .filters-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .sort-select {
            padding: 8px 18px;
            border: 1.5px solid #E0E0E0;
            border-radius: 30px;
            background: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.8rem;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .sort-select:hover,
        .sort-select:focus {
            border-color: #C8922A;
            outline: none;
        }
        .count { 
            font-size: 0.8rem; 
            color: #888; 
            background: #F0F0F0; 
            padding: 4px 16px; 
            border-radius: 30px;
        }

        /* ===== PRODUCTS GRID — masonry, sur tous les écrans ===== */
        .products-grid {
            display: block;
            column-count: 2;
            column-gap: 16px;
        }
        @media (min-width: 700px) {
            .products-grid { column-count: 3; column-gap: 20px; }
        }
        @media (min-width: 1000px) {
            .products-grid { column-count: 4; column-gap: 22px; }
        }
        @media (min-width: 1400px) {
            .products-grid { column-count: 5; column-gap: 24px; }
        }

        .product-card {
            display: inline-block;
            width: 100%;
            text-decoration: none;
            position: relative;
            animation: fadeInUp 0.5s ease both;
            background: transparent;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 18px;
            break-inside: avoid;
            cursor: pointer;
            transition: transform 0.4s ease;
        }
        .product-card:nth-child(1) { animation-delay: 0.05s; }
        .product-card:nth-child(2) { animation-delay: 0.10s; }
        .product-card:nth-child(3) { animation-delay: 0.15s; }
        .product-card:nth-child(4) { animation-delay: 0.20s; }
        .product-card:nth-child(5) { animation-delay: 0.25s; }
        .product-card:nth-child(6) { animation-delay: 0.30s; }
        .product-card:hover { transform: translateY(-6px); }

        .product-img {
            position: relative;
            aspect-ratio: auto;
            min-height: 170px;
            overflow: hidden;
            background: #F5F3F0;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: box-shadow 0.4s ease;
        }
        .product-card:hover .product-img {
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }
        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .product-card:hover .product-img img { transform: scale(1.05); }

        .product-badge-promo {
            position: absolute;
            top: 12px;
            left: 12px;
            background: #E74C3C;
            color: white;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 20px;
            z-index: 10;
            text-transform: uppercase;
        }
        .product-badge-new {
            position: absolute;
            top: 12px;
            left: 12px;
            background: #1A1A1A;
            color: #fff;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 20px;
            z-index: 10;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .wishlist-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 10;
            background: rgba(255,255,255,0.9);
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            color: #ccc;
        }
        .wishlist-btn:hover {
            transform: scale(1.1);
            background: rgba(255,255,255,0.95);
        }
        .wishlist-btn i { font-size: 0.95rem; transition: all 0.3s; }
        .wishlist-btn.liked { color: #E74C3C; }
        .wishlist-btn.liked i { text-shadow: 0 0 20px rgba(231,76,60,0.3); }

        /* ===== LOGO PANIER FLOTTANT (ouvre le tiroir de sélection) ===== */
        .quick-add-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            z-index: 10;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255,255,255,0.95);
            border: 1.5px solid #C8922A;
            color: #C8922A;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 3px 12px rgba(0,0,0,0.15);
            transition: all 0.25s ease;
        }
        .quick-add-btn:hover {
            background: #C8922A;
            color: #fff;
            transform: scale(1.08);
        }

        .product-info {
            padding: 10px 2px 0;
            text-align: left;
        }
        .product-title {
            font-family: 'Playfair Display', serif;
            font-size: 0.88rem;
            font-weight: 600;
            color: #1A1A1A;
            transition: color 0.3s;
        }
        .product-card:hover .product-title { color: #C8922A; }
        .product-price {
            font-size: 0.92rem;
            font-weight: 600;
            color: #C8922A;
            margin-top: 3px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 6px;
            flex-wrap: wrap;
        }
        .product-price .old-price {
            font-size: 0.72rem;
            color: #B0B0B0;
            text-decoration: line-through;
            font-weight: 400;
        }
        .product-price .current-price.promo { color: #E74C3C; }

        .empty-state { text-align: center; padding: 60px; background: white; border-radius: 20px; border: 1px solid #F0EBE3; }
        .empty-state i { font-size: 3rem; color: #C8922A; margin-bottom: 16px; display: block; }
        .empty-state h3 { font-family: 'Playfair Display', serif; color: #1A1A1A; margin-bottom: 8px; }
        .empty-state p { color: #999; }
        .empty-state .btn-retour { 
            display: inline-block; 
            margin-top: 12px; 
            color: #C8922A; 
            font-weight: 600;
            text-decoration: none;
            padding: 8px 28px;
            border: 2px solid #C8922A;
            border-radius: 30px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .empty-state .btn-retour:hover { background: #C8922A; color: white; }

        .breadcrumb { margin-bottom: 20px; font-size: 0.8rem; color: #999; }
        .breadcrumb a { color: #999; text-decoration: none; transition: color 0.3s; cursor: pointer; }
        .breadcrumb a:hover { color: #C8922A; }
        .breadcrumb span { color: #C8922A; font-weight: 500; }

        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 40px; }
        .pagination a, .pagination span {
            width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            text-decoration: none;
            color: #666;
            background: white;
            border: 1px solid #E0E0E0;
            transition: all 0.3s;
            font-weight: 500;
            cursor: pointer;
        }
        .pagination a:hover, .pagination .active {
            background: #C8922A;
            color: #fff;
            border-color: #C8922A;
        }

        /* ===== "VOUS AIMEREZ AUSSI" ===== */
        .reco-section { margin-top: 50px; }
        .reco-scroll {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 10px;
            -webkit-overflow-scrolling: touch;
        }
        .reco-card {
            flex: 0 0 150px;
            text-decoration: none;
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #F0F0F0;
            transition: transform 0.3s ease;
        }
        .reco-card:hover { transform: translateY(-4px); }
        .reco-card img { width: 100%; height: 190px; object-fit: cover; display: block; }
        .reco-card .reco-info { padding: 10px; }
        .reco-card .reco-name {
            font-size: 0.75rem; font-weight: 600; color: #1A1A1A; margin-bottom: 4px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .reco-card .reco-price { font-size: 0.8rem; font-weight: 700; color: #C8922A; }

        /* ===== TOAST ===== */
        .toast-notification {
            position: fixed;
            bottom: 82px;
            right: 30px;
            background: #1A1A1A;
            color: white;
            padding: 14px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            z-index: 9999;
            border-left: 4px solid #C8922A;
            font-family: 'Inter', sans-serif;
        }
        .toast-notification.show { transform: translateY(0); opacity: 1; }
        .toast-notification i { font-size: 1.2rem; }
        .toast-notification .toast-close {
            background: none;
            border: none;
            color: rgba(255,255,255,0.3);
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0 5px;
        }
        .toast-notification .toast-close:hover { color: white; }

        .cart-badge { display: none !important; }

        /* ===== DESIGN — ajustements par largeur ===== */
        @media (max-width: 1000px) {
            .categories-grid { grid-template-columns: repeat(3, 1fr); }
            .subcat-grid { grid-template-columns: repeat(2, 1fr); }
            .promo-grid { grid-template-columns: repeat(2, 1fr); }
            .banner-title { font-size: 2.8rem; }
            .cat-img { width: 100px; height: 100px; }
        }
        @media (max-width: 800px) {
            .banner-vitrine { min-height: 40vh; }
            .banner-title { font-size: 2.2rem; }
            .banner-subtitle { font-size: 0.85rem; }
            .banner-stats { gap: 16px; flex-wrap: wrap; }
            .stat-divider { display: none; }
            .cat-img { width: 80px; height: 80px; }
        }
        @media (max-width: 600px) {
            .categories-grid { grid-template-columns: repeat(2, 1fr); }
            .subcat-grid { grid-template-columns: 1fr 1fr; }
            .promo-grid { grid-template-columns: 1fr; }
            .banner-title { font-size: 1.8rem; }
            .banner-actions { flex-direction: column; align-items: center; }
            .btn-banner { width: 100%; justify-content: center; }
            .toast-notification { bottom: 82px; right: 20px; left: 20px; padding: 12px 16px; }
        }

        /* ============================================
           TIROIR DE SÉLECTION AVANT AJOUT AU PANIER
           (nouveau design inspiré Shein : galerie + grand cadre)
           ============================================ */
        .options-sheet-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            z-index: 10050; opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .options-sheet-overlay.show { opacity: 1; visibility: visible; }
        .options-sheet {
            position: fixed; left: 0; right: 0; bottom: 0;
            background: #fff; border-radius: 20px 20px 0 0;
            z-index: 10051; padding: 0 0 calc(16px + env(safe-area-inset-bottom));
            transform: translateY(100%); transition: transform 0.35s cubic-bezier(.32,.72,0,1);
            box-shadow: 0 -10px 40px rgba(0,0,0,0.2);
            max-height: 88vh; overflow-y: auto;
            max-width: 480px; margin: 0 auto;
        }
        .options-sheet.show { transform: translateY(0); }
        .options-sheet-handle { width: 40px; height: 4px; background: #E0E0E0; border-radius: 3px; margin: 10px auto 6px; }
        .options-sheet-close {
            position: absolute; top: 12px; right: 16px; background: #fff; border: none;
            width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: #666; cursor: pointer; font-size: 1rem; z-index: 3; box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }

        /* Galerie image principale du tiroir */
        .sheet-gallery { position: relative; padding: 0 18px; margin-bottom: 4px; }
        .sheet-gallery-main {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            max-height: 220px;
            border-radius: 14px;
            overflow: hidden;
            background: #F5F3F0;
        }
        .sheet-gallery-main img { width: 100%; height: 100%; object-fit: cover; }
        .sheet-expand-btn {
            position: absolute; bottom: 10px; right: 10px;
            display: flex; align-items: center; gap: 6px;
            background: rgba(0,0,0,0.55); color: #fff; border: none;
            padding: 6px 12px; border-radius: 20px; font-size: 0.68rem; font-weight: 600;
            cursor: pointer; backdrop-filter: blur(4px);
        }
        .sheet-expand-btn:hover { background: rgba(0,0,0,0.75); }

        .sheet-title-row { padding: 14px 18px 0; }
        .options-sheet-name { font-family: 'Playfair Display', serif; font-weight: 600; font-size: 1rem; color: #1A1A1A; margin-bottom: 6px; }
        .options-sheet-price { font-size: 1.1rem; font-weight: 700; color: #C8922A; display: flex; gap: 8px; align-items: center; }
        .options-sheet-old-price { font-size: 0.75rem; color: #B0B0B0; text-decoration: line-through; font-weight: 400; }
        .sheet-stock-note { font-size: 0.7rem; color: #888; margin-top: 4px; }

        .options-body { padding: 4px 18px 0; }
        .options-group { margin-bottom: 16px; margin-top: 16px; }
        .options-group-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 8px; display: block; }
        .options-chips { display: flex; gap: 8px; flex-wrap: wrap; }
        .options-chip {
            padding: 7px 16px; border-radius: 20px; background: #F5F5F5; border: 1.5px solid #EFEFEF;
            font-size: 0.78rem; color: #444; cursor: pointer; transition: all 0.2s;
        }
        .options-chip.selected { background: #1A1A1A; color: #fff; border-color: #1A1A1A; font-weight: 600; }
        .color-chip { display: flex; align-items: center; gap: 6px; }
        .color-chip .dot { width: 14px; height: 14px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.15); }
        .color-chip.selected .dot { box-shadow: 0 0 0 2px #fff, 0 0 0 3px #1A1A1A; }

        /* Tailles en grille, façon capture d'écran (boutons carrés) */
        .size-chips { display: grid; grid-template-columns: repeat(auto-fill, minmax(52px, 1fr)); gap: 8px; }
        .size-chip {
            padding: 10px 4px; border-radius: 10px; background: #F5F5F5; border: 1.5px solid #EFEFEF;
            font-size: 0.8rem; font-weight: 600; color: #444; cursor: pointer; text-align: center; transition: all 0.2s;
        }
        .size-chip.selected { background: #1A1A1A; color: #fff; border-color: #1A1A1A; }

        .options-qty-row { display: flex; align-items: center; justify-content: space-between; margin: 18px 0 10px; }
        .options-qty { display: flex; align-items: center; gap: 14px; background: #F5F5F5; border-radius: 24px; padding: 4px 6px; }
        .options-qty button { width: 30px; height: 30px; border-radius: 50%; border: none; background: #fff; font-size: 1.1rem; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

        .options-sheet-footer { padding: 8px 18px 4px; }
        .options-add-btn {
            width: 100%; background: #1A1A1A; color: #fff; border: none; border-radius: 12px; padding: 15px;
            font-weight: 700; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;
        }
        .options-add-btn:hover { background: #333; }
        .options-add-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .options-sheet-loading { text-align: center; padding: 50px 0; color: #999; }

        /* ===== LIGHTBOX (grand cadre) ===== */
        .image-lightbox-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.92);
            z-index: 10100; opacity: 0; visibility: hidden; transition: opacity 0.3s ease;
            display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .image-lightbox-overlay.show { opacity: 1; visibility: visible; }
        .image-lightbox-overlay img {
            max-width: 100%; max-height: 90vh; object-fit: contain; border-radius: 8px;
        }
        .image-lightbox-close {
            position: absolute; top: 18px; right: 18px;
            width: 40px; height: 40px; border-radius: 50%;
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25);
            color: #fff; font-size: 1.2rem; display: flex; align-items: center; justify-content: center;
            cursor: pointer;
        }
    </style>
</head>
<body>

<!-- ===== BANNER ===== -->
<div class="banner-vitrine">
    <div class="banner-bg-image">
        <img src="iba design.jpeg" alt="IBA Design Boutique" onerror="this.style.display='none'">
        <div class="overlay-gradient"></div>
    </div>
    <div class="banner-content">
        <div class="banner-badge">
            <span class="badge-pulse">Collection Exclusive</span>
        </div>
        <h1 class="banner-title">
            <span class="title-line">Notre</span>
            <span class="title-line gold">Collection</span>
        </h1>
        <p class="banner-subtitle">
            Découvrez notre sélection de créations uniques, alliant tradition et modernité.
        </p>
        <div class="banner-actions">
            <a href="#promotions" class="btn-banner gold-border" onclick="document.getElementById('promotions').scrollIntoView({behavior:'smooth'}); return false;">
                <i class="bi bi-percent"></i> Voir les promos
            </a>
        </div>
        <div class="banner-stats">
            <div class="stat-item">
                <span class="stat-number">150+</span>
                <span class="stat-label">Modèles exclusifs</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
                <span class="stat-number">98%</span>
                <span class="stat-label">Satisfaction client</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
                <span class="stat-number">7</span>
                <span class="stat-label">Années d'excellence</span>
            </div>
        </div>
    </div>
</div>

<div class="container">

    <!-- ===== BANDEAU DE RÉASSURANCE ===== -->
    <div class="trust-bar">
        <div class="trust-item"><i class="bi bi-truck"></i> Livraison à Bamako</div>
        <div class="trust-item"><i class="bi bi-shield-check"></i> Paiement sécurisé</div>
        <div class="trust-item"><i class="bi bi-arrow-repeat"></i> Retour facile</div>
    </div>

    <?php if ($categorie_id == 0 && empty($search)): ?>
        <!-- Categories -->
        <div class="categories-grid" id="categories-grid">
            <?php foreach ($categories_masonry as $cat): ?>
                <a href="?categorie=<?= $cat['id'] ?>" class="cat-card" data-url="?categorie=<?= $cat['id'] ?>">
                    <div class="cat-img">
                        <img src="<?= getCategoryImage($cat) ?>" 
                             alt="<?= htmlspecialchars($cat['nom']) ?>"
                             loading="lazy"
                             onerror="this.src='https://placehold.co/200x200/C8922A/FFF?text=<?= urlencode(substr($cat['nom'], 0, 1)) ?>'">
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
            <a href="?categorie=1" class="section-link" data-url="?categorie=1">Voir toutes →</a>
        </div>
        <div class="subcat-grid" id="subcat-1">
            <?php foreach ($subcategories_data[1] as $sub): ?>
                <a href="?categorie=1&sous_categorie=<?= $sub['id'] ?>" class="subcat-card" data-url="?categorie=1&sous_categorie=<?= $sub['id'] ?>">
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
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
            <a href="?categorie=2" class="section-link" data-url="?categorie=2">Voir toutes →</a>
        </div>
        <div class="subcat-grid" id="subcat-2">
            <?php foreach ($subcategories_data[2] as $sub): ?>
                <a href="?categorie=2&sous_categorie=<?= $sub['id'] ?>" class="subcat-card" data-url="?categorie=2&sous_categorie=<?= $sub['id'] ?>">
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
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
            <a href="?categorie=3" class="section-link" data-url="?categorie=3">Voir toutes →</a>
        </div>
        <div class="subcat-grid" id="subcat-3">
            <?php foreach ($subcategories_data[3] as $sub): ?>
                <a href="?categorie=3&sous_categorie=<?= $sub['id'] ?>" class="subcat-card" data-url="?categorie=3&sous_categorie=<?= $sub['id'] ?>">
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
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
            <a href="?categorie=4" class="section-link" data-url="?categorie=4">Voir toutes →</a>
        </div>
        <div class="subcat-grid" id="subcat-4">
            <?php foreach ($subcategories_data[4] as $sub): ?>
                <a href="?categorie=4&sous_categorie=<?= $sub['id'] ?>" class="subcat-card" data-url="?categorie=4&sous_categorie=<?= $sub['id'] ?>">
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
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
            <a href="?categorie=5" class="section-link" data-url="?categorie=5">Voir toutes →</a>
        </div>
        <div class="subcat-grid" id="subcat-5">
            <?php foreach ($subcategories_data[5] as $sub): ?>
                <a href="?categorie=5&sous_categorie=<?= $sub['id'] ?>" class="subcat-card" data-url="?categorie=5&sous_categorie=<?= $sub['id'] ?>">
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== PROMOTIONS ===== -->
        <div class="section-head" style="margin-top: 10px;" id="promotions">
            <div>
                <h2 class="section-title">Nos <em>promotions</em></h2>
                <p style="font-size: 0.8rem; color: #8A99AA; margin-top: 4px;">Profitez des offres spéciales du moment</p>
            </div>
            <a href="promotions.php" class="section-link">Voir toutes →</a>
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
                        <span class="promo-price-new"><?= number_format($p['prix_promo'], 0, ',', ' ') ?> F</span>
                        <span class="promo-price-old"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                    </div>
                    <span class="btn-promo">Profiter</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:30px;background:white;border-radius:16px;border:1px solid #F0EBE3;margin-bottom:30px;">
            <i class="bi bi-tag" style="font-size:2rem;color:#CCC;"></i>
            <p style="margin-top:8px;color:#8A99AA;font-size:0.85rem;">Aucune promotion en cours.</p>
            <a href="catalogue.php" style="color:#C8922A;font-weight:600;text-decoration:none;font-size:0.85rem;">Voir tous les produits →</a>
        </div>
        <?php endif; ?>

        <!-- ============================================
             SECTION "NOS VIDÉOS" — juste après les promotions
             ============================================ -->
        <div class="section-head" style="margin-top: 10px;">
            <div>
                <h2 class="section-title">Nos <em>vidéos</em></h2>
                <p style="font-size: 0.8rem; color: #8A99AA; margin-top: 4px;">Découvrez nos créations en mouvement</p>
            </div>
            <a href="videos.php" class="section-link">Voir toutes →</a>
        </div>

        <?php if (!empty($videos_apercu)): ?>
        <div class="videos-section">
            <div class="videos-scroll">
                <?php foreach ($videos_apercu as $v):
                    $titreV = htmlspecialchars($v['titre'] ?? 'Vidéo');
                    $vues = number_format($v['vues'] ?? 0, 0, ',', ' ');
                    $local = isLocalVideo($v);
                    $thumbYoutube = $local ? '' : getYoutubeThumb($v['url_ou_fichier'] ?? '');
                ?>
                <a href="videos.php?video=<?= (int)$v['id'] ?>" class="video-reel-card <?= (!$local && empty($thumbYoutube)) ? 'empty-thumb' : '' ?>">
                    <?php if ($local): ?>
                        <video muted loop preload="metadata" playsinline
                               onmouseover="this.play().catch(()=>{})"
                               onmouseout="this.pause(); this.currentTime = 0;">
                            <source src="<?= getVideoFileUrl($v) ?>" type="video/mp4">
                        </video>
                    <?php elseif (!empty($thumbYoutube)): ?>
                        <img src="<?= $thumbYoutube ?>" alt="<?= $titreV ?>" loading="lazy">
                    <?php endif; ?>
                    <div class="video-reel-play"><i class="bi bi-play-circle-fill"></i></div>
                    <div class="video-reel-overlay">
                        <div class="video-reel-title"><?= $titreV ?></div>
                        <div class="video-reel-views"><i class="bi bi-eye"></i> <?= $vues ?> vues</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:30px;background:white;border-radius:16px;border:1px solid #F0EBE3;margin-bottom:30px;">
            <i class="bi bi-camera-reels" style="font-size:2rem;color:#CCC;"></i>
            <p style="margin-top:8px;color:#8A99AA;font-size:0.85rem;">Aucune vidéo pour le moment.</p>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ===== PAGE CATEGORIE AVEC PRODUITS ===== -->
        <div class="breadcrumb">
            <a href="catalogue.php"><i class="bi bi-house"></i> Accueil</a> 
            <i class="bi bi-chevron-right" style="font-size: 0.6rem; margin: 0 6px;"></i>
            <span><?= htmlspecialchars($categorie_nom) ?></span>
            <?php if ($sous_categorie_id > 0): ?> 
                <i class="bi bi-chevron-right" style="font-size: 0.6rem; margin: 0 6px;"></i>
                <span><?= htmlspecialchars($sub_nom_affiché) ?></span>
            <?php endif; ?>
        </div>

        <div class="shop-layout">
            <!-- Barre de sous-catégories : collante -->
            <div class="sidebar">
                <div class="sidebar-title">
                    <i class="bi bi-grid-3x3-gap-fill" style="color: #C8922A;"></i> 
                    <?= htmlspecialchars($categorie_nom) ?>
                </div>
                <ul class="sidebar-list">
                    <li>
                        <a href="?categorie=<?= $categorie_id ?>" class="<?= ($sous_categorie_id == 0) ? 'active' : '' ?>" data-url="?categorie=<?= $categorie_id ?>">
                            <i class="bi bi-grid"></i> Tous les produits
                        </a>
                    </li>
                    <?php if (isset($subcategories_data[$categorie_id])): ?>
                        <?php foreach ($subcategories_data[$categorie_id] as $sub): ?>
                            <li>
                                <a href="?categorie=<?= $categorie_id ?>&sous_categorie=<?= $sub['id'] ?>" class="<?= ($sous_categorie_id == $sub['id']) ? 'active' : '' ?>" data-url="?categorie=<?= $categorie_id ?>&sous_categorie=<?= $sub['id'] ?>">
                                    <i class="bi bi-tag"></i> <?= htmlspecialchars($sub['nom']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="content" id="shop-content">
                <div class="filters-bar">
                    <select class="sort-select" onchange="window.location.href = this.value">
                        <option value="?<?= http_build_query(array_merge($_GET, ['tri' => 'newest', 'page' => 1])) ?>" <?= $tri == 'newest' ? 'selected' : '' ?>>Plus récents</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['tri' => 'price_asc', 'page' => 1])) ?>" <?= $tri == 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['tri' => 'price_desc', 'page' => 1])) ?>" <?= $tri == 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
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
                            
                            $pourcentage_promo = 0;
                            if ($est_promo && $prix_ancien > 0) {
                                $pourcentage_promo = round((1 - $prix_affiché / $prix_ancien) * 100);
                            }

                            $est_nouveau = false;
                            if (!$est_promo && !empty($p['created_at'])) {
                                $est_nouveau = (strtotime($p['created_at']) >= strtotime('-14 days'));
                            }
                            
                            $is_liked = in_array($p['id'], $wishlist_ids);
                            ?>
                            <!-- ===== CARTE CLIQUABLE → PAGE PRODUIT (façon Shein) ===== -->
                            <a href="produit.php?id=<?= $p['id'] ?>" class="product-card" data-product-id="<?= $p['id'] ?>">
                                <div class="product-img">
                                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>" loading="lazy">
                                    
                                    <?php if ($est_promo): ?>
                                        <div class="product-badge-promo">-<?= $pourcentage_promo ?>%</div>
                                    <?php elseif ($est_nouveau): ?>
                                        <div class="product-badge-new">Nouveau</div>
                                    <?php endif; ?>
                                    
                                    <button class="wishlist-btn <?= $is_liked ? 'liked' : '' ?>" 
                                            onclick="toggleWishlist(event, <?= $p['id'] ?>, this)"
                                            title="<?= $is_liked ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
                                        <i class="bi <?= $is_liked ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                                    </button>

                                    <!-- Ouvre le tiroir de sélection (couleur/taille/quantité) -->
                                    <button class="quick-add-btn" onclick="openOptionsSheet(event, <?= $p['id'] ?>)" title="Ajouter au panier">
                                        <i class="bi bi-bag-plus"></i>
                                    </button>
                                </div>
                                
                                <div class="product-info">
                                    <div class="product-title"><?= htmlspecialchars($p['nom']) ?></div>
                                    <div class="product-price">
                                        <?php if ($est_promo): ?>
                                            <span class="old-price"><?= number_format($prix_ancien, 0, ',', ' ') ?> F</span>
                                            <span class="current-price promo"><?= number_format($prix_affiché, 0, ',', ' ') ?> F</span>
                                        <?php else: ?>
                                            <span class="current-price"><?= number_format($prix_affiché, 0, ',', ' ') ?> F</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" data-url="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"><i class="bi bi-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php 
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            for ($i = $start; $i <= $end; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" data-url="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" data-url="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"><i class="bi bi-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- ===== "VOUS AIMEREZ AUSSI" — mélange aléatoire toutes catégories ===== -->
                    <?php if (!empty($produits_recommandes)): ?>
                    <div class="reco-section">
                        <div class="section-head">
                            <h2 class="section-title">Vous aimerez <em>aussi</em></h2>
                        </div>
                        <div class="reco-scroll">
                            <?php foreach ($produits_recommandes as $r):
                                $rprix = (isset($r['est_promo']) && $r['est_promo'] == 1 && isset($r['prix_promo']) && $r['prix_promo'] > 0 && $r['prix_promo'] < $r['prix'])
                                    ? $r['prix_promo'] : $r['prix'];
                                $rimg = getProductImage($r['image_principale'] ?? '');
                            ?>
                            <a href="produit.php?id=<?= $r['id'] ?>" class="reco-card">
                                <img src="<?= $rimg ?>" alt="<?= htmlspecialchars($r['nom']) ?>" loading="lazy" onerror="this.src='https://placehold.co/300x400/F5F5F5/C8922A?text=<?= urlencode($r['nom']) ?>'">
                                <div class="reco-info">
                                    <div class="reco-name"><?= htmlspecialchars($r['nom']) ?></div>
                                    <div class="reco-price"><?= number_format($rprix, 0, ',', ' ') ?> F</div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Toast -->
<div id="toast" class="toast-notification">
    <i class="bi bi-cart-plus"></i>
    <span id="toastMessage">Ajouté au panier</span>
    <button class="toast-close" onclick="closeToast()">&times;</button>
</div>

<!-- ============================================
     TIROIR DE SÉLECTION (couleur / taille / quantité)
     ============================================ -->
<div class="options-sheet-overlay" id="optionsSheetOverlay" onclick="closeOptionsSheet()"></div>
<div class="options-sheet" id="optionsSheet">
    <button class="options-sheet-close" onclick="closeOptionsSheet()"><i class="bi bi-x-lg"></i></button>
    <div class="options-sheet-handle"></div>
    <div id="optionsSheetContent">
        <div class="options-sheet-loading"><i class="bi bi-hourglass-split"></i> Chargement...</div>
    </div>
</div>

<!-- ============================================
     LIGHTBOX "GRAND CADRE" (photo en plein écran)
     ============================================ -->
<div class="image-lightbox-overlay" id="imageLightbox" onclick="closeImageLightbox(event)">
    <button class="image-lightbox-close" onclick="closeImageLightbox(event)"><i class="bi bi-x-lg"></i></button>
    <img id="imageLightboxImg" src="" alt="Vue agrandie">
</div>

<script>
// ============================================
// AJAX CATÉGORIES - CHARGEMENT SANS RELOAD
// ============================================
document.addEventListener('click', function(e) {
    const link = e.target.closest('[data-url]');
    if (!link) return;
    
    e.preventDefault();
    const url = link.getAttribute('data-url');
    if (!url) return;
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            const newContent = doc.getElementById('shop-content');
            const currentContent = document.getElementById('shop-content');
            
            if (newContent && currentContent) {
                currentContent.innerHTML = newContent.innerHTML;
                history.pushState({}, '', url);
                window.scrollTo({ top: 200, behavior: 'smooth' });
            } else {
                window.location.href = url;
            }
        })
        .catch(error => {
            console.error('Erreur AJAX:', error);
            window.location.href = url;
        });
});

// ============================================
// WISHLIST
// ============================================
function toggleWishlist(event, productId, button) {
    event.preventDefault();
    event.stopPropagation();
    
    const btn = button || event.currentTarget;
    const icon = btn.querySelector('i');
    const isLiked = btn.classList.contains('liked');
    const action = isLiked ? 'remove' : 'add';
    
    <?php if (!isset($_SESSION['client_id'])): ?>
        showToast('Veuillez vous connecter', 'warning');
        return;
    <?php endif; ?>
    
    if (action === 'add') {
        btn.classList.add('liked');
        icon.className = 'bi bi-heart-fill';
    } else {
        btn.classList.remove('liked');
        icon.className = 'bi bi-heart';
    }
    
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.6';
    
    fetch('../wishlist_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=' + action + '&produit_id=' + productId
    })
    .then(response => response.json())
    .then(data => {
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
        
        if (!data.success) {
            if (action === 'add') {
                btn.classList.remove('liked');
                icon.className = 'bi bi-heart';
            } else {
                btn.classList.add('liked');
                icon.className = 'bi bi-heart-fill';
            }
            showToast(data.message || 'Erreur', 'error');
        } else {
            showToast(data.message, 'success');
        }
    })
    .catch(error => {
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
        if (action === 'add') {
            btn.classList.remove('liked');
            icon.className = 'bi bi-heart';
        } else {
            btn.classList.add('liked');
            icon.className = 'bi bi-heart-fill';
        }
        showToast('Erreur de connexion', 'error');
    });
}

// ============================================
// TOAST
// ============================================
let toastTimeout = null;

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    const toastMessage = document.getElementById('toastMessage');
    
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

// ============================================
// TIROIR DE SÉLECTION AVANT AJOUT AU PANIER
// ============================================
let sheetProduitId = null;
let sheetCouleurId = 0;
let sheetTailleId = 0;
let sheetQty = 1;

function openOptionsSheet(event, produitId) {
    event.preventDefault();
    event.stopPropagation();

    sheetProduitId = produitId;
    sheetCouleurId = 0;
    sheetTailleId = 0;
    sheetQty = 1;

    const content = document.getElementById('optionsSheetContent');
    content.innerHTML = '<div class="options-sheet-loading"><i class="bi bi-hourglass-split"></i> Chargement...</div>';

    document.getElementById('optionsSheetOverlay').classList.add('show');
    document.getElementById('optionsSheet').classList.add('show');
    document.body.style.overflow = 'hidden';

    const formData = new FormData();
    formData.append('action', 'get_produit_detail');
    formData.append('produit_id', produitId);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                content.innerHTML = '<div class="options-sheet-loading">Produit introuvable.</div>';
                return;
            }
            renderOptionsSheet(data);
        })
        .catch(() => {
            content.innerHTML = '<div class="options-sheet-loading">Erreur de connexion.</div>';
        });
}

function renderOptionsSheet(data) {
    const content = document.getElementById('optionsSheetContent');

    let html = `
        <div class="sheet-gallery">
            <div class="sheet-gallery-main">
                <img src="${data.image}" alt="${data.nom}">
                <button type="button" class="sheet-expand-btn" onclick="openImageLightbox('${data.image}')">
                    <i class="bi bi-arrows-fullscreen"></i> Grand cadre
                </button>
            </div>
        </div>
        <div class="sheet-title-row">
            <div class="options-sheet-name">${data.nom}</div>
            <div class="options-sheet-price">
                <span>${new Intl.NumberFormat('fr-FR').format(data.prix)} F</span>
                ${data.prix_ancien ? `<span class="options-sheet-old-price">${new Intl.NumberFormat('fr-FR').format(data.prix_ancien)} F</span>` : ''}
            </div>
            ${data.stock !== undefined ? `<div class="sheet-stock-note">${data.stock > 0 ? data.stock + ' en stock' : 'Rupture de stock'}</div>` : ''}
        </div>
        <div class="options-body">
    `;

    if (data.couleurs && data.couleurs.length > 0) {
        html += `<div class="options-group"><span class="options-group-label">Couleur</span><div class="options-chips" id="colorChips">`;
        data.couleurs.forEach((c) => {
            html += `<div class="options-chip color-chip" data-id="${c.id}" onclick="selectSheetColor(this, ${c.id})">
                        <span class="dot" style="background:${c.code_hex || '#ccc'}"></span>${c.nom}
                     </div>`;
        });
        html += `</div></div>`;
    }

    if (data.tailles && data.tailles.length > 0) {
        html += `<div class="options-group"><span class="options-group-label">Taille</span><div class="size-chips" id="sizeChips">`;
        data.tailles.forEach((t) => {
            html += `<div class="size-chip" data-id="${t.id}" onclick="selectSheetSize(this, ${t.id})">${t.nom}</div>`;
        });
        html += `</div></div>`;
    }

    html += `
        <div class="options-qty-row">
            <span class="options-group-label" style="margin-bottom:0;">Quantité</span>
            <div class="options-qty">
                <button type="button" onclick="changeSheetQty(-1)">−</button>
                <span id="sheetQtyVal">1</span>
                <button type="button" onclick="changeSheetQty(1)">+</button>
            </div>
        </div>
        </div>
        <div class="options-sheet-footer">
            <button type="button" class="options-add-btn" id="optionsAddBtn" onclick="confirmSheetAdd()">
                <i class="bi bi-bag-check"></i> Ajouter au panier
            </button>
        </div>
    `;

    content.innerHTML = html;
}

function selectSheetColor(chip, id) {
    document.querySelectorAll('#colorChips .options-chip').forEach(c => c.classList.remove('selected'));
    chip.classList.add('selected');
    sheetCouleurId = id;
}

function selectSheetSize(chip, id) {
    document.querySelectorAll('#sizeChips .size-chip').forEach(c => c.classList.remove('selected'));
    chip.classList.add('selected');
    sheetTailleId = id;
}

function changeSheetQty(delta) {
    sheetQty = Math.max(1, sheetQty + delta);
    const el = document.getElementById('sheetQtyVal');
    if (el) el.textContent = sheetQty;
}

function confirmSheetAdd() {
    const colorChips = document.getElementById('colorChips');
    const sizeChips = document.getElementById('sizeChips');

    if (colorChips && sheetCouleurId === 0) {
        showToast('Veuillez choisir une couleur', 'warning');
        return;
    }
    if (sizeChips && sheetTailleId === 0) {
        showToast('Veuillez choisir une taille', 'warning');
        return;
    }

    const btn = document.getElementById('optionsAddBtn');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Ajout...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'ajouter_panier');
    formData.append('produit_id', sheetProduitId);
    formData.append('quantite', sheetQty);
    formData.append('couleur_id', sheetCouleurId);
    formData.append('taille_id', sheetTailleId);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.innerHTML = original;
            btn.disabled = false;
            if (data.success) {
                showToast(data.message || 'Produit ajouté au panier', 'success');
                closeOptionsSheet();
                // ✅ MISE À JOUR IMMÉDIATE DU COMPTEUR
                updateCartCounter(data.count);
            } else {
                showToast(data.message || "Erreur lors de l'ajout", 'error');
            }
        })
        .catch(() => {
            btn.innerHTML = original;
            btn.disabled = false;
            showToast('Erreur de connexion', 'error');
        });
}

// ============================================
// ✅ MISE À JOUR IMMÉDIATE DU COMPTEUR PANIER
// ============================================
function updateCartCounter(count) {
    // 1️⃣ Met à jour le badge dans le header
    const badges = document.querySelectorAll('.cart-badge, #cart-count, .nb-articles');
    badges.forEach(badge => {
        badge.textContent = count;
        if (count > 0) {
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'inline-block';
        }
    });
    
    // 2️⃣ Met à jour le texte du panier si présent
    const cartTexts = document.querySelectorAll('.panier-count, .cart-count');
    cartTexts.forEach(el => {
        el.textContent = count;
    });
    
    // 3️⃣ Met à jour les icônes de panier avec badge
    const cartIcons = document.querySelectorAll('.panier-icon, .cart-icon');
    cartIcons.forEach(el => {
        if (count > 0) {
            el.innerHTML = '<i class="bi bi-cart-fill"></i> <span class="badge">' + count + '</span>';
        } else {
            el.innerHTML = '<i class="bi bi-cart"></i>';
        }
    });
    
    // 4️⃣ Force le re-rendu du compteur si dans le menu
    const menuCart = document.querySelector('.menu-cart-count');
    if (menuCart) {
        menuCart.textContent = count;
    }
}

function closeOptionsSheet() {
    const overlay = document.getElementById('optionsSheetOverlay');
    const sheet = document.getElementById('optionsSheet');
    if (overlay) overlay.classList.remove('show');
    if (sheet) sheet.classList.remove('show');
    document.body.style.overflow = '';
    sheetProduitId = null;
    sheetCouleurId = 0;
    sheetTailleId = 0;
    sheetQty = 1;
}

// ============================================
// LIGHTBOX "GRAND CADRE"
// ============================================
function openImageLightbox(src) {
    document.getElementById('imageLightboxImg').src = src;
    document.getElementById('imageLightbox').classList.add('show');
}
function closeImageLightbox(event) {
    if (event) event.stopPropagation();
    document.getElementById('imageLightbox').classList.remove('show');
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeOptionsSheet();
        closeImageLightbox();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>