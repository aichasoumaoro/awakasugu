<?php
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// TRAITEMENT AJOUT AU PANIER (AJAX)
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

    $images_supplementaires = [];
    try {
        $stmt = $pdo->prepare("
            SELECT nom_fichier FROM produit_images 
            WHERE produit_id = ? 
            ORDER BY ordre ASC, id ASC
        ");
        $stmt->execute([$produit_id]);
        $images_supplementaires = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(PDOException $e) {
        $images_supplementaires = [];
    }

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

    function getProductImageSheet($image) {
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

    $images_galerie = [];
    $image_principale_url = getProductImageSheet($p['image_principale'] ?? '');
    $images_galerie[] = $image_principale_url;
    
    foreach ($images_supplementaires as $img_path) {
        $img_url = getProductImageSheet($img_path);
        if (!in_array($img_url, $images_galerie)) {
            $images_galerie[] = $img_url;
        }
    }

    echo json_encode([
        'success' => true,
        'id' => (int)$p['id'],
        'nom' => $p['nom'],
        'image' => $image_principale_url,
        'images_galerie' => $images_galerie,
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

$is_homepage = ($categorie_id == 0 && empty($search));

$produits = [];
$total_products = 0;
$total_pages = 0;

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boutique - IBA Design</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --ink:#14110B;
            --gold:#C8922A;
            --gold-light:#E8C482;
            --gold-deep:#9A6E1A;
            --cream:#FBF9F5;
            --ivory:#FFFFFF;
            --line:#EDE6D8;
            --line-soft:#F4EFE6;
            --promo:#C0392B;
            --muted:#8A857A;
            --nav-safe-top: 92px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--ink);
            padding-top: var(--nav-safe-top);
            -webkit-font-smoothing: antialiased;
            letter-spacing: -0.005em;
        }

        @media (max-width: 600px) {
            body { padding-top: calc(var(--nav-safe-top) - 10px); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.001ms !important; animation-iteration-count: 1 !important; transition-duration: 0.001ms !important; }
        }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        @keyframes softPulse {
            0%, 100% { opacity: 0.85; transform: scale(1); }
            50%      { opacity: 1;    transform: scale(1.06); }
        }

        .reveal { opacity: 0; transform: translateY(22px); transition: opacity .8s cubic-bezier(.2,.7,.2,1), transform .8s cubic-bezier(.2,.7,.2,1); }
        .reveal.in-view { opacity: 1; transform: translateY(0); }

        /* ============ NAV CATÉGORIES ============ */
        .top-categories-nav{
            background: var(--ivory);
            border-bottom: 1px solid var(--line-soft);
            padding: 22px 0 16px;
        }
        .top-categories-scroll{
            display:flex;
            gap: 26px;
            overflow-x:auto;
            justify-content: center;
            padding: 2px 20px 6px;
            max-width:1300px;
            margin:0 auto;
            scrollbar-width:none;
        }
        .top-categories-scroll::-webkit-scrollbar{ display:none; }
        .top-cat-item{
            display:flex; flex-direction:column; align-items:center; gap:9px;
            text-decoration:none; flex:0 0 auto; width:78px;
            cursor:pointer;
        }
        .top-cat-circle{
            width:60px; height:60px; border-radius:50%;
            overflow:hidden; background: var(--cream);
            display:flex; align-items:center; justify-content:center;
            border:1px solid var(--line);
            transition: all .4s cubic-bezier(.2,.7,.2,1);
            font-size:1.3rem; color: var(--gold);
        }
        .top-cat-circle img{ width:100%; height:100%; object-fit:cover; }
        .top-cat-item span{
            font-size:.68rem;
            font-weight:500;
            color: var(--muted);
            white-space:nowrap;
            text-align:center;
            letter-spacing:.3px;
            transition:color .3s;
        }
        .top-cat-item:hover .top-cat-circle{
            transform: translateY(-3px);
            border-color: var(--gold);
            box-shadow: 0 10px 22px rgba(200,146,42,.14);
        }
        .top-cat-item:hover span{ color: var(--ink); }
        .top-cat-item.active .top-cat-circle{
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(200,146,42,.12);
        }
        .top-cat-item.active span{ color: var(--gold-deep); font-weight:600; }

        @media (max-width:600px){
            .top-categories-scroll{ justify-content:flex-start; gap:18px; padding-left:16px; padding-right:16px; }
            .top-cat-circle{ width:54px; height:54px; }
            .top-cat-item{ width:66px; }
            .top-cat-item span{ font-size:.62rem; }
        }

        /* ============ HERO VITRINE ============ */
        .banner-vitrine {
            position: relative;
            min-height: clamp(200px, 34vh, 380px);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            padding: clamp(34px, 7vw, 64px) 22px clamp(30px, 6vw, 56px);
            background: #0A0805;
            isolation: isolate;
        }
        .banner-vitrine .banner-bg-image {
            position: absolute;
            inset: 0;
            z-index: 1;
            overflow: hidden;
            will-change: transform;
        }
        .banner-vitrine .banner-bg-image img {
            width: 100%;
            height: 112%;
            object-fit: cover;
            filter: blur(5px) brightness(0.32) saturate(0.9);
            transform: scale(1.06);
        }
        .banner-vitrine .banner-bg-image .overlay-gradient {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 50% 12%, rgba(200,146,42,0.14), transparent 55%),
                linear-gradient(180deg, rgba(0,0,0,0.30) 0%, rgba(0,0,0,0.60) 55%, rgba(10,8,5,0.96) 100%);
        }
        .banner-vitrine::before,
        .banner-vitrine::after{
            content:'';
            position:absolute; left:50%; transform:translateX(-50%);
            width: min(280px, 60%); height:1px;
            background: linear-gradient(90deg, transparent, rgba(200,146,42,.7), transparent);
            z-index: 4;
        }
        .banner-vitrine::before{ top: 22px; }
        .banner-vitrine::after { bottom: 22px; }

        .banner-content {
            position: relative;
            z-index: 3;
            max-width: 760px;
        }
        .banner-badge { margin-bottom: 16px; opacity:0; animation: fadeInUp .7s ease-out .1s forwards; }
        .badge-pulse {
            display: inline-flex;
            align-items:center;
            gap:8px;
            padding: 6px 20px;
            background: rgba(200,146,42,0.10);
            border: 1px solid rgba(200,146,42,0.28);
            border-radius: 50px;
            color: #E8C482;
            font-size: 0.62rem;
            font-weight: 500;
            letter-spacing: 2.4px;
            text-transform: uppercase;
            backdrop-filter: blur(6px);
        }
        .badge-pulse .dot{
            width:5px; height:5px; border-radius:50%;
            background:#E8C482;
            box-shadow: 0 0 0 3px rgba(200,146,42,0.15);
            animation: softPulse 2.6s ease-in-out infinite;
        }
        .banner-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.6rem, 3.2vw, 2.5rem);
            font-weight: 500;
            line-height: 1.12;
            margin-bottom: 14px;
            color: #FFFFFF;
            letter-spacing: -0.01em;
        }
        .title-line { display: block; opacity:0; animation: fadeInUp .8s ease-out forwards; }
        .title-line:nth-child(1){ animation-delay: .22s; }
        .title-line:nth-child(2){ animation-delay: .34s; }
        .title-line.gold {
            font-style: italic;
            font-weight: 600;
            background: linear-gradient(100deg,#F0D089,#C8922A 55%,#8F6512);
            background-size: 200% auto;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: fadeInUp .8s ease-out .34s forwards, shimmer 7s linear infinite 1.2s;
        }
        .banner-subtitle {
            font-size: clamp(0.76rem, 1.15vw, 0.88rem);
            color: rgba(255,255,255,0.62);
            line-height: 1.7;
            margin: 0 auto 22px;
            max-width: 400px;
            font-weight: 300;
            letter-spacing: .2px;
            opacity:0; animation: fadeInUp .8s ease-out .46s forwards;
        }
        .banner-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 0;
            opacity:0; animation: fadeInUp .8s ease-out .56s forwards;
        }
        .btn-banner {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 0.74rem;
            font-weight: 500;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            text-decoration: none;
            transition: all 0.35s cubic-bezier(.2,.7,.2,1);
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.04);
            color: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            cursor: pointer;
        }
        .btn-banner.gold-border {
            background: linear-gradient(100deg, var(--gold-light), var(--gold) 60%, var(--gold-deep));
            border-color: transparent;
            color:#14110B;
            box-shadow: 0 10px 26px rgba(200,146,42,0.32);
        }
        .btn-banner.gold-border:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(200,146,42,0.42);
        }

        .banner-wave{
            position:absolute; left:0; right:0; bottom:-1px; z-index:3;
            line-height:0;
        }
        .banner-wave svg{ width:100%; height:auto; display:block; }

        /* ============ CONTAINER ============ */
        .container {
            max-width: 1320px;
            margin: 0 auto;
            padding: 42px 24px 60px;
        }

        /* ============ CATEGORIES ============ */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 26px;
            margin-bottom: 60px;
        }
        .cat-card {
            text-align: center;
            text-decoration: none;
            transition: transform 0.4s cubic-bezier(.2,.7,.2,1);
            cursor: pointer;
        }
        .cat-card:hover { transform: translateY(-4px); }
        .cat-img {
            width: 128px;
            height: 128px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 14px;
            border: 1px solid var(--line);
            padding: 4px;
            background: var(--ivory);
            transition: all .4s cubic-bezier(.2,.7,.2,1);
            position: relative;
        }
        .cat-img::after{
            content: '';
            position: absolute;
            inset: 6px;
            border-radius: 50%;
            border: 1px solid transparent;
            transition: border-color .4s;
            pointer-events: none;
        }
        .cat-img img {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 50%;
            transition: transform .6s cubic-bezier(.2,.7,.2,1);
        }
        .cat-card:hover .cat-img {
            border-color: var(--gold);
            box-shadow: 0 16px 34px rgba(200,146,42,.16);
        }
        .cat-card:hover .cat-img::after{ border-color: rgba(200,146,42,.25); }
        .cat-card:hover .cat-img img{ transform: scale(1.06); }
        .cat-name {
            font-family: 'Playfair Display', serif;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--ink);
            letter-spacing: .2px;
            transition: color 0.3s;
        }
        .cat-card:hover .cat-name { color: var(--gold-deep); }

        /* ============ SECTION HEAD ============ */
        .section-head {
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--line-soft);
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.55rem;
            font-weight: 500;
            color: var(--ink);
            letter-spacing: -0.01em;
            line-height: 1.2;
        }
        .section-title em {
            color: var(--gold);
            font-style: italic;
            font-weight: 600;
        }
        .section-head p {
            font-size: 0.78rem;
            color: var(--muted);
            margin-top: 4px;
            letter-spacing: .2px;
            font-weight: 400;
        }
        .section-link {
            font-size: 0.74rem;
            font-weight: 500;
            color: var(--gold-deep);
            text-decoration: none;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            border-bottom: 1px solid transparent;
            padding-bottom: 2px;
        }
        .section-link:hover {
            color: var(--gold);
            border-bottom-color: var(--gold);
        }

        /* ============ SOUS-CATÉGORIES ============ */
        .subcat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 60px;
        }
        .subcat-card {
            background: var(--ivory);
            border-radius: 14px;
            padding: 22px 20px;
            text-decoration: none;
            text-align: left;
            border: 1px solid var(--line-soft);
            transition: all .4s cubic-bezier(.2,.7,.2,1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .subcat-card::before{
            content:'';
            position:absolute; top:0; left:0;
            width:0; height:1px;
            background: linear-gradient(90deg, var(--gold), transparent);
            transition: width .5s cubic-bezier(.2,.7,.2,1);
        }
        .subcat-card:hover::before{ width:100%; }
        .subcat-card:hover {
            transform: translateY(-3px);
            border-color: var(--line);
            box-shadow: 0 20px 40px rgba(20,15,5,.08);
        }
        .subcat-card-icon {
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(200,146,42,0.08);
            color: var(--gold);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            transition: all .4s;
        }
        .subcat-card:hover .subcat-card-icon{
            background: var(--gold);
            color: #fff;
        }
        .subcat-card-name {
            font-family: 'Playfair Display', serif;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--ink);
            letter-spacing: .1px;
        }
        .subcat-card-cta {
            font-size: 0.62rem;
            font-weight: 500;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1.6px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .3s;
        }
        .subcat-card:hover .subcat-card-cta{
            color: var(--gold-deep);
            gap: 10px;
        }

        /* ============================================================
           PROMOTIONS — GRILLE MASONRY COMME LA BOUTIQUE
           ============================================================ */
        .promo-grid {
            display: block;
            column-count: 2;
            column-gap: 14px;
            margin-bottom: 60px;
        }
        @media (min-width: 700px) {
            .promo-grid { column-count: 3; column-gap: 18px; }
        }
        @media (min-width: 1000px) {
            .promo-grid { column-count: 4; column-gap: 22px; }
        }
        @media (min-width: 1400px) {
            .promo-grid { column-count: 5; column-gap: 24px; }
        }

        .promo-card {
            display: inline-block;
            width: 100%;
            background: var(--ivory);
            border-radius: 6px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(.2,.7,.2,1);
            text-decoration: none;
            border: 1px solid transparent;
            position: relative;
            margin-bottom: 16px;
            break-inside: avoid;
            cursor: pointer;
        }
        .promo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(20,15,5,.10);
            border-color: var(--line-soft);
        }

        .promo-image {
            position: relative;
            aspect-ratio: 3 / 4;
            overflow: hidden;
            background: #F6F4EF;
        }
        .promo-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s cubic-bezier(.2,.7,.2,1);
        }
        .promo-card:hover .promo-image img { transform: scale(1.05); }

        .promo-badge-red {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--promo);
            color: white;
            font-size: 0.54rem;
            font-weight: 600;
            padding: 4px 9px;
            border-radius: 2px;
            letter-spacing: 1px;
            text-transform: uppercase;
            z-index: 2;
        }

        .promo-info {
            padding: 12px 14px 14px;
            text-align: left;
            background: var(--ivory);
        }
        .promo-name {
            font-family: 'Inter', sans-serif;
            font-size: 0.78rem;
            font-weight: 400;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: .1px;
            margin-bottom: 8px;
            transition: color .3s;
        }
        .promo-card:hover .promo-name { color: var(--gold-deep); }

        .promo-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .promo-prices {
            display: flex;
            align-items: baseline;
            gap: 7px;
            flex-wrap: wrap;
        }
        .promo-price-new {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--promo);
            letter-spacing: -.01em;
        }
        .promo-price-old {
            font-size: 0.7rem;
            color: var(--muted);
            text-decoration: line-through;
            font-weight: 400;
        }

        /* ============ VIDÉOS ============ */
        .videos-section { margin-top: 8px; margin-bottom: 60px; }
        .videos-scroll {
            display: flex;
            gap: 14px;
            overflow-x: auto;
            padding: 4px 2px 14px;
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x proximity;
        }
        .video-reel-card {
            flex: 0 0 148px;
            scroll-snap-align: start;
            position: relative;
            aspect-ratio: 9 / 16;
            border-radius: 14px;
            overflow: hidden;
            background: #0D0D0D;
            text-decoration: none;
            box-shadow: 0 10px 24px rgba(20,15,5,.14);
            transition: transform .4s cubic-bezier(.2,.7,.2,1), box-shadow .4s;
        }
        .video-reel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 18px 40px rgba(20,15,5,.22);
        }
        .video-reel-card video,
        .video-reel-card img {
            position: absolute;
            inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
        }
        .video-reel-play {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.12);
            transition: opacity 0.3s ease;
        }
        .video-reel-card:hover .video-reel-play { opacity: 0; }
        .video-reel-play i {
            font-size: 2rem;
            color: rgba(255,255,255,0.92);
            text-shadow: 0 2px 12px rgba(0,0,0,0.6);
        }
        .video-reel-overlay {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            padding: 34px 10px 12px;
            background: linear-gradient(to top, rgba(0,0,0,0.88) 0%, transparent 100%);
            z-index: 2;
        }
        .video-reel-title {
            font-size: 0.7rem;
            font-weight: 500;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: .1px;
        }
        .video-reel-card.empty-thumb {
            background: linear-gradient(135deg, #1A1A1A, #0D0D0D);
        }
        @media (max-width: 600px) {
            .video-reel-card { flex-basis: 118px; }
        }

        /* ============ PRODUITS — GRILLE MASONRY ============ */
        .shop-layout { display: block; }
        .content { flex: 1; }

        .sidebar {
            width: 100%;
            position: sticky;
            top: 75px;
            z-index: 500;
            background: var(--cream);
            padding: 16px 0 18px;
            margin-bottom: 28px;
            border-bottom: 1px solid var(--line-soft);
            transition: box-shadow .3s ease;
        }
        .sidebar.is-stuck{ box-shadow: 0 10px 22px rgba(20,15,5,.05); }
        .sidebar-title {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 14px;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: .1px;
        }
        .sidebar-title i { color: var(--gold); font-size: 0.9rem; }
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
            gap: 7px;
            padding: 9px 18px;
            white-space: nowrap;
            background: transparent;
            border: 1px solid var(--line);
            border-radius: 50px;
            color: var(--ink);
            text-decoration: none;
            font-size: 0.76rem;
            font-weight: 500;
            letter-spacing: .2px;
            transition: all 0.3s cubic-bezier(.2,.7,.2,1);
            cursor: pointer;
        }
        .sidebar-list a i { font-size: 0.8rem; color: var(--gold); transition: color .3s ease; }
        .sidebar-list a:hover {
            border-color: var(--gold);
            color: var(--gold-deep);
            background: rgba(200,146,42,.04);
        }
        .sidebar-list a.active {
            background: var(--ink);
            border-color: var(--ink);
            color: #fff;
            font-weight: 500;
        }
        .sidebar-list a.active i { color: var(--gold-light); }

        .filters-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 26px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .sort-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .sort-wrap > i.bi-sort-down {
            position: absolute;
            left: 15px;
            font-size: 0.8rem;
            color: var(--gold);
            pointer-events: none;
        }
        .sort-chevron {
            position: absolute;
            right: 15px;
            font-size: 0.6rem;
            color: var(--muted);
            pointer-events: none;
        }
        .sort-select {
            appearance: none;
            -webkit-appearance: none;
            padding: 10px 32px 10px 36px;
            border: 1px solid var(--line);
            border-radius: 50px;
            background: var(--ivory);
            font-family: 'Inter', sans-serif;
            font-size: 0.76rem;
            font-weight: 500;
            color: var(--ink);
            cursor: pointer;
            letter-spacing: .2px;
            transition: all 0.3s ease;
        }
        .sort-select:hover,
        .sort-select:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 6px 16px rgba(200,146,42,.12);
        }

        /* ============ GRILLE MASONRY PRODUITS ============ */
        .products-grid {
            display: block;
            column-count: 2;
            column-gap: 18px;
        }
        @media (min-width: 700px) {
            .products-grid { column-count: 3; column-gap: 22px; }
        }
        @media (min-width: 1000px) {
            .products-grid { column-count: 4; column-gap: 24px; }
        }
        @media (min-width: 1400px) {
            .products-grid { column-count: 5; column-gap: 26px; }
        }

        .product-card {
            display: inline-block;
            width: 100%;
            text-decoration: none;
            position: relative;
            background: var(--ivory);
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 22px;
            break-inside: avoid;
            cursor: pointer;
            border: 1px solid transparent;
            transition: transform .45s cubic-bezier(.2,.7,.2,1), box-shadow .45s ease, border-color .3s;
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(20,15,5,.10);
            border-color: var(--line-soft);
        }

        .product-img {
            position: relative;
            aspect-ratio: 3 / 4;
            min-height: 170px;
            overflow: hidden;
            background: #F6F4EF;
        }
        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s cubic-bezier(.2,.7,.2,1);
        }
        .product-card:hover .product-img img { transform: scale(1.05); }

        .product-badge-promo {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--promo);
            color: white;
            font-size: 0.54rem;
            font-weight: 600;
            padding: 4px 9px;
            border-radius: 2px;
            z-index: 10;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .product-badge-new {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--ink);
            color: #fff;
            font-size: 0.54rem;
            font-weight: 600;
            padding: 4px 9px;
            border-radius: 2px;
            z-index: 10;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .wishlist-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(6px);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #B8B2A6;
            overflow: visible;
        }
        .wishlist-btn:hover {
            transform: scale(1.08);
            color: var(--promo);
        }
        .wishlist-btn i { font-size: 0.86rem; transition: all 0.25s; }
        .wishlist-btn.liked { color: var(--promo); }
        .wishlist-btn.liked i { text-shadow: 0 0 10px rgba(192,57,43,0.35); }
        .wishlist-btn.pop i { animation: heartPop .45s ease; }
        @keyframes heartPop {
            0%   { transform: scale(1); }
            35%  { transform: scale(1.5); }
            60%  { transform: scale(0.85); }
            100% { transform: scale(1); }
        }
        .heart-particle {
            position: absolute;
            top: 50%; left: 50%;
            font-size: 0.6rem;
            color: var(--promo);
            pointer-events: none;
            z-index: 11;
            animation: heartBurst .65s ease-out forwards;
        }
        @keyframes heartBurst {
            0%   { transform: translate(-50%, -50%) scale(0.4); opacity: 1; }
            100% { transform: translate(calc(-50% + var(--tx)), calc(-50% + var(--ty))) scale(0.9) rotate(var(--rot)); opacity: 0; }
        }

        .product-info {
            padding: 12px 14px 14px;
            text-align: left;
        }
        .product-title {
            font-family: 'Inter', sans-serif;
            font-size: 0.78rem;
            font-weight: 400;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: .1px;
            transition: color .3s;
        }
        .product-card:hover .product-title { color: var(--gold-deep); }

        .product-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 8px;
        }
        .product-price {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--ink);
            display: flex;
            align-items: baseline;
            gap: 7px;
            flex-wrap: wrap;
            letter-spacing: -.01em;
        }
        .product-price .old-price {
            font-size: 0.7rem;
            color: var(--muted);
            text-decoration: line-through;
            font-weight: 400;
        }
        .product-price .current-price.promo { color: var(--promo); }

        .quick-add-btn {
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--ink);
            border: none;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(.2,.7,.2,1);
        }
        .quick-add-btn:hover {
            background: var(--gold);
            transform: scale(1.08);
        }

        .empty-state {
            text-align: center;
            padding: 70px 30px;
            background: var(--ivory);
            border-radius: 14px;
            border: 1px solid var(--line-soft);
        }
        .empty-state i {
            font-size: 2.6rem;
            color: var(--gold);
            margin-bottom: 16px;
            display: block;
            opacity: .6;
        }
        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-weight: 500;
            color: var(--ink);
            margin-bottom: 8px;
            font-size: 1.15rem;
        }
        .empty-state p { color: var(--muted); font-size: 0.85rem; }
        .empty-state .btn-retour {
            display: inline-block;
            margin-top: 18px;
            color: var(--gold-deep);
            font-weight: 500;
            text-decoration: none;
            padding: 10px 28px;
            border: 1px solid var(--gold);
            border-radius: 50px;
            font-size: 0.78rem;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            transition: all 0.3s;
            cursor: pointer;
        }
        .empty-state .btn-retour:hover {
            background: var(--gold);
            color: #fff;
            border-color: var(--gold);
        }

        .breadcrumb {
            margin-bottom: 22px;
            font-size: 0.76rem;
            color: var(--muted);
            letter-spacing: .2px;
        }
        .breadcrumb a {
            color: var(--muted);
            text-decoration: none;
            transition: color 0.3s;
            cursor: pointer;
        }
        .breadcrumb a:hover { color: var(--gold); }
        .breadcrumb span { color: var(--ink); font-weight: 500; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 46px;
        }
        .pagination a, .pagination span {
            width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            text-decoration: none;
            color: var(--ink);
            background: var(--ivory);
            border: 1px solid var(--line);
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.8rem;
            cursor: pointer;
        }
        .pagination a:hover, .pagination .active {
            background: var(--ink);
            color: #fff;
            border-color: var(--ink);
        }

        .reco-section { margin-top: 66px; }
        .reco-scroll {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            padding: 4px 2px 14px;
            -webkit-overflow-scrolling: touch;
        }
        .reco-card {
            flex: 0 0 152px;
            text-decoration: none;
            background: var(--ivory);
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid transparent;
            transition: all .4s cubic-bezier(.2,.7,.2,1);
        }
        .reco-card:hover {
            transform: translateY(-3px);
            border-color: var(--line-soft);
            box-shadow: 0 16px 32px rgba(20,15,5,.10);
        }
        .reco-image { position: relative; }
        .reco-card img {
            width: 100%;
            height: 175px;
            object-fit: cover;
            display: block;
            transition: transform .6s;
        }
        .reco-card:hover img{ transform: scale(1.05); }
        .reco-info { padding: 11px 12px 12px; text-align: left; }
        .reco-card .reco-name {
            font-family: 'Inter', sans-serif;
            font-size: 0.74rem;
            font-weight: 400;
            color: var(--ink);
            margin-bottom: 7px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: .1px;
        }
        .reco-card:hover .reco-name { color: var(--gold-deep); }
        .reco-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
        }
        .reco-card .reco-price {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--ink);
            letter-spacing: -.01em;
        }
        .reco-footer .quick-add-btn { width: 26px; height: 26px; font-size: 0.7rem; }

        .toast-notification {
            position: fixed;
            bottom: 82px;
            right: 30px;
            background: var(--ink);
            color: white;
            padding: 14px 20px;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.28);
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(.2,.7,.2,1);
            z-index: 9999;
            border-left: 3px solid var(--gold);
            font-family: 'Inter', sans-serif;
            font-size: 0.82rem;
        }
        .toast-notification.show { transform: translateY(0); opacity: 1; }
        .toast-notification i { font-size: 1.05rem; }
        .toast-notification .toast-close {
            background: none;
            border: none;
            color: rgba(255,255,255,0.3);
            cursor: pointer;
            font-size: 1rem;
            padding: 0 4px;
        }
        .toast-notification .toast-close:hover { color: white; }

        @media (max-width: 1000px) {
            .categories-grid { grid-template-columns: repeat(3, 1fr); }
            .subcat-grid { grid-template-columns: repeat(2, 1fr); }
            .cat-img { width: 104px; height: 104px; }
        }
        @media (max-width: 800px) {
            .cat-img { width: 88px; height: 88px; }
        }
        @media (max-width: 600px) {
            .container{ padding: 32px 16px 50px; }
            .banner-vitrine { min-height: 0; padding: 30px 18px 26px; }
            .banner-badge { margin-bottom: 10px; }
            .banner-actions { flex-direction: column; align-items: center; }
            .categories-grid { grid-template-columns: repeat(3, 1fr); gap: 16px; }
            .subcat-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .btn-banner { width: 100%; justify-content: center; }
            .toast-notification { bottom: 82px; right: 20px; left: 20px; padding: 12px 16px; }
            .section-title { font-size: 1.3rem; }
            .products-grid { column-count: 2; column-gap: 14px; }
            .product-card { margin-bottom: 16px; }
            .product-info { padding: 10px 10px 12px; }
            .product-title { font-size: 0.72rem; }
            .product-price { font-size: 0.82rem; }
        }

        /* ============ TIROIR DE SÉLECTION ============ */
        .options-sheet-overlay {
            position: fixed; inset: 0; background: rgba(10,8,4,0.55); backdrop-filter: blur(2px);
            z-index: 10050; opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .options-sheet-overlay.show { opacity: 1; visibility: visible; }
        .options-sheet {
            position: fixed; left: 0; right: 0; bottom: 0;
            background: #fff; border-radius: 24px 24px 0 0;
            z-index: 10051; padding: 0 0 calc(16px + env(safe-area-inset-bottom));
            transform: translateY(100%); transition: transform 0.35s cubic-bezier(.32,.72,0,1);
            box-shadow: 0 -16px 50px rgba(0,0,0,0.22);
            max-height: 88vh; overflow-y: auto;
            max-width: 480px; margin: 0 auto;
        }
        .options-sheet::before{
            content:''; position:sticky; top:0; display:block; z-index:2;
            height:3px; margin:0 0 -3px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light) 50%, var(--gold));
            border-radius: 24px 24px 0 0;
        }
        .options-sheet.show { transform: translateY(0); }
        .options-sheet-handle { width: 40px; height: 3px; background: #EAD9BA; border-radius: 3px; margin: 12px auto 6px; }
        .options-sheet-close {
            position: absolute; top: 12px; right: 16px; background: #fff; border: none;
            width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: #666; cursor: pointer; font-size: 1rem; z-index: 3; box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }
        .sheet-gallery { padding: 0 18px; margin-bottom: 4px; }
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
        .sheet-galerie-vignettes {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-start;
            padding: 10px 0 4px;
        }
        .sheet-galerie-vignettes .vignette-item {
            width: 56px;
            height: 56px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #F5F3F0;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sheet-galerie-vignettes .vignette-item:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
        }
        .sheet-galerie-vignettes .vignette-item.active {
            border-color: var(--gold);
            box-shadow: 0 0 0 2px rgba(200,146,42,0.25);
        }
        .sheet-galerie-vignettes .vignette-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .sheet-title-row { padding: 14px 18px 0; }
        .options-sheet-name { font-family: 'Playfair Display', serif; font-weight: 500; font-size: 1rem; color: var(--ink); margin-bottom: 6px; }
        .options-sheet-price { font-size: 1.1rem; font-weight: 600; color: var(--ink); display: flex; gap: 8px; align-items: center; letter-spacing: -.01em; }
        .options-sheet-old-price { font-size: 0.75rem; color: var(--muted); text-decoration: line-through; font-weight: 400; }
        .sheet-stock-note { font-size: 0.7rem; color: var(--muted); margin-top: 4px; display:flex; align-items:center; gap:5px; }
        .options-body { padding: 4px 18px 0; }
        .options-group { margin-bottom: 16px; margin-top: 16px; }
        .options-group-label { font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1.4px; color: var(--muted); margin-bottom: 10px; display: block; }
        .options-chips { display: flex; gap: 8px; flex-wrap: wrap; }
        .options-chip {
            padding: 7px 16px; border-radius: 20px; background: var(--ivory); border: 1px solid var(--line);
            font-size: 0.76rem; color: var(--ink); cursor: pointer; transition: all 0.2s;
        }
        .options-chip.selected { background: var(--ink); color: #fff; border-color: var(--ink); font-weight: 500; }
        .color-chip { display: flex; align-items: center; gap: 6px; }
        .color-chip .dot { width: 14px; height: 14px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.15); }
        .color-chip.selected .dot { box-shadow: 0 0 0 2px #fff, 0 0 0 3px var(--ink); }
        .size-chips { display: grid; grid-template-columns: repeat(auto-fill, minmax(52px, 1fr)); gap: 8px; }
        .size-chip {
            padding: 10px 4px; border-radius: 8px; background: var(--ivory); border: 1px solid var(--line);
            font-size: 0.8rem; font-weight: 500; color: var(--ink); cursor: pointer; text-align: center; transition: all 0.2s;
        }
        .size-chip.selected { background: var(--ink); color: #fff; border-color: var(--ink); }
        .options-qty-row { display: flex; align-items: center; justify-content: space-between; margin: 18px 0 10px; }
        .options-qty { display: flex; align-items: center; gap: 14px; background: var(--ivory); border: 1px solid var(--line); border-radius: 24px; padding: 4px 6px; }
        .options-qty button { width: 30px; height: 30px; border-radius: 50%; border: none; background: #fff; font-size: 1.1rem; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .options-sheet-footer { padding: 8px 18px 4px; }
        .options-add-btn {
            width: 100%; background: var(--ink); color: #fff; border: none; border-radius: 12px; padding: 15px;
            font-weight: 500; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 1.4px;
            display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;
            transition: background .25s ease, transform .2s ease;
        }
        .options-add-btn:hover { background: var(--gold-deep); transform: translateY(-1px); }
        .options-add-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .options-sheet-loading { text-align: center; padding: 50px 0; color: var(--muted); }

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
        @media (max-width: 480px) {
            .sheet-galerie-vignettes .vignette-item { width: 44px; height: 44px; }
        }
    </style>
</head>
<body>

<?php if (!$is_homepage): ?>
<div class="top-categories-nav">
    <div class="top-categories-scroll">
        <a href="catalogue.php" class="top-cat-item" data-url="catalogue.php">
            <div class="top-cat-circle"><i class="bi bi-grid"></i></div>
            <span>Tout</span>
        </a>
        <?php foreach ($categories_masonry as $cat): ?>
            <a href="?categorie=<?= $cat['id'] ?>" class="top-cat-item <?= ($categorie_id == $cat['id']) ? 'active' : '' ?>" data-url="?categorie=<?= $cat['id'] ?>">
                <div class="top-cat-circle">
                    <img src="<?= getCategoryImage($cat) ?>" 
                         alt="<?= htmlspecialchars($cat['nom']) ?>"
                         loading="lazy"
                         onerror="this.src='https://placehold.co/200x200/C8922A/FFF?text=<?= urlencode(substr($cat['nom'], 0, 1)) ?>'">
                </div>
                <span><?= htmlspecialchars($cat['nom']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="banner-vitrine">
    <div class="banner-bg-image" id="heroParallax">
        <img src="iba design.jpeg" alt="IBA Design Boutique" fetchpriority="high" decoding="async" onerror="this.style.display='none'">
        <div class="overlay-gradient"></div>
    </div>
    <div class="banner-content">
        <div class="banner-badge">
            <span class="badge-pulse"><span class="dot"></span> Collection Exclusive</span>
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
    </div>
    <div class="banner-wave">
        <svg viewBox="0 0 1440 90" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,32 C240,80 480,0 720,24 C960,48 1200,88 1440,40 L1440,90 L0,90 Z" fill="#FBF9F5"></path>
        </svg>
    </div>
</div>

<div class="container">

    <?php if ($is_homepage): ?>
        <!-- Categories -->
        <div class="categories-grid reveal-group" id="categories-grid">
            <?php foreach ($categories_masonry as $cat): ?>
                <a href="?categorie=<?= $cat['id'] ?>" class="cat-card reveal" data-url="?categorie=<?= $cat['id'] ?>">
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
        <div class="section-head reveal">
            <div>
                <h2 class="section-title">Nos <em>abayas</em></h2>
                <p>Sélection intemporelle</p>
            </div>
            <a href="?categorie=1" class="section-link" data-url="?categorie=1">Voir toutes →</a>
        </div>
        <div class="subcat-grid reveal-group" id="subcat-1">
            <?php foreach ($subcategories_data[1] as $sub): ?>
                <a href="?categorie=1&sous_categorie=<?= $sub['id'] ?>" class="subcat-card reveal" data-url="?categorie=1&sous_categorie=<?= $sub['id'] ?>">
                    <div class="subcat-card-icon"><i class="bi bi-stars"></i></div>
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
                    <div class="subcat-card-cta">Découvrir <i class="bi bi-arrow-right"></i></div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Foulards & Turbans -->
        <?php if (isset($subcategories_data[2])): ?>
        <div class="section-head reveal">
            <div>
                <h2 class="section-title">Nos <em>foulards & turbans</em></h2>
                <p>Élégance et raffinement</p>
            </div>
            <a href="?categorie=2" class="section-link" data-url="?categorie=2">Voir toutes →</a>
        </div>
        <div class="subcat-grid reveal-group" id="subcat-2">
            <?php foreach ($subcategories_data[2] as $sub): ?>
                <a href="?categorie=2&sous_categorie=<?= $sub['id'] ?>" class="subcat-card reveal" data-url="?categorie=2&sous_categorie=<?= $sub['id'] ?>">
                    <div class="subcat-card-icon"><i class="bi bi-stars"></i></div>
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
                    <div class="subcat-card-cta">Découvrir <i class="bi bi-arrow-right"></i></div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Sacs -->
        <?php if (isset($subcategories_data[3])): ?>
        <div class="section-head reveal">
            <div>
                <h2 class="section-title">Nos <em>sacs</em></h2>
                <p>L'accessoire signature</p>
            </div>
            <a href="?categorie=3" class="section-link" data-url="?categorie=3">Voir toutes →</a>
        </div>
        <div class="subcat-grid reveal-group" id="subcat-3">
            <?php foreach ($subcategories_data[3] as $sub): ?>
                <a href="?categorie=3&sous_categorie=<?= $sub['id'] ?>" class="subcat-card reveal" data-url="?categorie=3&sous_categorie=<?= $sub['id'] ?>">
                    <div class="subcat-card-icon"><i class="bi bi-stars"></i></div>
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
                    <div class="subcat-card-cta">Découvrir <i class="bi bi-arrow-right"></i></div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Chaussures -->
        <?php if (isset($subcategories_data[4])): ?>
        <div class="section-head reveal">
            <div>
                <h2 class="section-title">Nos <em>chaussures</em></h2>
                <p>Pour marcher avec grâce</p>
            </div>
            <a href="?categorie=4" class="section-link" data-url="?categorie=4">Voir toutes →</a>
        </div>
        <div class="subcat-grid reveal-group" id="subcat-4">
            <?php foreach ($subcategories_data[4] as $sub): ?>
                <a href="?categorie=4&sous_categorie=<?= $sub['id'] ?>" class="subcat-card reveal" data-url="?categorie=4&sous_categorie=<?= $sub['id'] ?>">
                    <div class="subcat-card-icon"><i class="bi bi-stars"></i></div>
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
                    <div class="subcat-card-cta">Découvrir <i class="bi bi-arrow-right"></i></div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Prêt-à-porter -->
        <?php if (isset($subcategories_data[5])): ?>
        <div class="section-head reveal">
            <div>
                <h2 class="section-title">Nos <em>prêt-à-porter</em></h2>
                <p>Le quotidien raffiné</p>
            </div>
            <a href="?categorie=5" class="section-link" data-url="?categorie=5">Voir toutes →</a>
        </div>
        <div class="subcat-grid reveal-group" id="subcat-5">
            <?php foreach ($subcategories_data[5] as $sub): ?>
                <a href="?categorie=5&sous_categorie=<?= $sub['id'] ?>" class="subcat-card reveal" data-url="?categorie=5&sous_categorie=<?= $sub['id'] ?>">
                    <div class="subcat-card-icon"><i class="bi bi-stars"></i></div>
                    <div class="subcat-card-name"><?= htmlspecialchars($sub['nom']) ?></div>
                    <div class="subcat-card-cta">Découvrir <i class="bi bi-arrow-right"></i></div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- PROMOTIONS -->
        <div class="section-head reveal" style="margin-top: 10px;" id="promotions">
            <div>
                <h2 class="section-title">Nos <em>promotions</em></h2>
                <p>Profitez des offres spéciales du moment</p>
            </div>
            <a href="promotions.php" class="section-link">Voir toutes →</a>
        </div>
        
        <?php if(!empty($produits_promo)): ?>
        <div class="promo-grid reveal-group">
            <?php foreach($produits_promo as $p): 
                $reduction = round((($p['prix'] - $p['prix_promo']) / $p['prix']) * 100);
                $img = getProductImage($p['image_principale'] ?? '');
            ?>
            <a href="produit.php?id=<?= $p['id'] ?>" class="promo-card reveal">
                <div class="promo-image">
                    <img src="<?= $img ?>" 
                         alt="<?= htmlspecialchars($p['nom']) ?>" 
                         loading="lazy" 
                         decoding="async" 
                         onerror="this.src='https://placehold.co/400x500/F5F5F5/C8922A?text=<?= urlencode($p['nom'])?>'">
                    <div class="promo-badge-red">-<?= $reduction ?>%</div>
                </div>
                <div class="promo-info">
                    <div class="promo-name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="promo-footer">
                        <div class="promo-prices">
                            <span class="promo-price-new"><?= number_format($p['prix_promo'], 0, ',', ' ') ?> F</span>
                            <span class="promo-price-old"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                        </div>
                        <button class="quick-add-btn" onclick="openOptionsSheet(event, <?= $p['id'] ?>)" title="Ajouter au panier">
                            <i class="bi bi-cart-plus"></i>
                        </button>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:30px;background:var(--ivory);border-radius:14px;border:1px solid var(--line-soft);margin-bottom:30px;">
            <i class="bi bi-tag" style="font-size:2rem;color:#CCC;"></i>
            <p style="margin-top:8px;color:var(--muted);font-size:0.85rem;">Aucune promotion en cours.</p>
            <a href="catalogue.php" style="color:var(--gold-deep);font-weight:500;text-decoration:none;font-size:0.85rem;">Voir tous les produits →</a>
        </div>
        <?php endif; ?>

        <!-- NOS VIDÉOS (sans nombre de vues) -->
        <div class="section-head reveal" style="margin-top: 10px;">
            <div>
                <h2 class="section-title">Nos <em>vidéos</em></h2>
                <p>Découvrez nos créations en mouvement</p>
            </div>
            <a href="videos.php" class="section-link">Voir toutes →</a>
        </div>

        <?php if (!empty($videos_apercu)): ?>
        <div class="videos-section">
            <div class="videos-scroll">
                <?php foreach ($videos_apercu as $v):
                    $titreV = htmlspecialchars($v['titre'] ?? 'Vidéo');
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
                    <div class="video-reel-play"><i class="bi bi-play-circle"></i></div>
                    <div class="video-reel-overlay">
                        <div class="video-reel-title"><?= $titreV ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:30px;background:var(--ivory);border-radius:14px;border:1px solid var(--line-soft);margin-bottom:30px;">
            <i class="bi bi-camera-reels" style="font-size:2rem;color:#CCC;"></i>
            <p style="margin-top:8px;color:var(--muted);font-size:0.85rem;">Aucune vidéo pour le moment.</p>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- PAGE CATEGORIE AVEC PRODUITS -->
        <div class="breadcrumb">
            <a href="catalogue.php"><i class="bi bi-house"></i> Accueil</a> 
            <i class="bi bi-chevron-right" style="font-size: 0.55rem; margin: 0 8px; opacity:.5;"></i>
            <span><?= htmlspecialchars($categorie_nom) ?></span>
            <?php if ($sous_categorie_id > 0): ?> 
                <i class="bi bi-chevron-right" style="font-size: 0.55rem; margin: 0 8px; opacity:.5;"></i>
                <span><?= htmlspecialchars($sub_nom_affiché) ?></span>
            <?php endif; ?>
        </div>

        <div class="shop-layout">
            <div class="sidebar" id="shopSidebar">
                <div class="sidebar-title">
                    <i class="bi bi-grid-3x3-gap"></i> 
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
                    <div class="sort-wrap">
                        <i class="bi bi-sort-down"></i>
                        <select class="sort-select" onchange="window.location.href = this.value">
                            <option value="?<?= http_build_query(array_merge($_GET, ['tri' => 'newest', 'page' => 1])) ?>" <?= $tri == 'newest' ? 'selected' : '' ?>>Plus récents</option>
                            <option value="?<?= http_build_query(array_merge($_GET, ['tri' => 'price_asc', 'page' => 1])) ?>" <?= $tri == 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                            <option value="?<?= http_build_query(array_merge($_GET, ['tri' => 'price_desc', 'page' => 1])) ?>" <?= $tri == 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                        </select>
                        <i class="bi bi-chevron-down sort-chevron"></i>
                    </div>
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
                            <a href="produit.php?id=<?= $p['id'] ?>" class="product-card reveal" data-product-id="<?= $p['id'] ?>">
                                <div class="product-img">
                                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>" loading="lazy" decoding="async">
                                    
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
                                </div>

                                <div class="product-info">
                                    <div class="product-title"><?= htmlspecialchars($p['nom']) ?></div>
                                    <div class="product-footer">
                                        <div class="product-price">
                                            <?php if ($est_promo): ?>
                                                <span class="old-price"><?= number_format($prix_ancien, 0, ',', ' ') ?> F</span>
                                                <span class="current-price promo"><?= number_format($prix_affiché, 0, ',', ' ') ?> F</span>
                                            <?php else: ?>
                                                <span class="current-price"><?= number_format($prix_affiché, 0, ',', ' ') ?> F</span>
                                            <?php endif; ?>
                                        </div>
                                        <button class="quick-add-btn" onclick="openOptionsSheet(event, <?= $p['id'] ?>)" title="Ajouter au panier">
                                            <i class="bi bi-cart-plus"></i>
                                        </button>
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

                    <?php if (!empty($produits_recommandes)): ?>
                    <div class="reco-section">
                        <div class="section-head reveal">
                            <div>
                                <h2 class="section-title">Vous aimerez <em>aussi</em></h2>
                            </div>
                        </div>
                        <div class="reco-scroll">
                            <?php foreach ($produits_recommandes as $r):
                                $rprix = (isset($r['est_promo']) && $r['est_promo'] == 1 && isset($r['prix_promo']) && $r['prix_promo'] > 0 && $r['prix_promo'] < $r['prix'])
                                    ? $r['prix_promo'] : $r['prix'];
                                $rimg = getProductImage($r['image_principale'] ?? '');
                            ?>
                            <a href="produit.php?id=<?= $r['id'] ?>" class="reco-card">
                                <div class="reco-image">
                                    <img src="<?= $rimg ?>" alt="<?= htmlspecialchars($r['nom']) ?>" loading="lazy" decoding="async" onerror="this.src='https://placehold.co/300x400/F5F5F5/C8922A?text=<?= urlencode($r['nom']) ?>'">
                                </div>
                                <div class="reco-info">
                                    <div class="reco-name"><?= htmlspecialchars($r['nom']) ?></div>
                                    <div class="reco-footer">
                                        <div class="reco-price"><?= number_format($rprix, 0, ',', ' ') ?> F</div>
                                        <button class="quick-add-btn" onclick="openOptionsSheet(event, <?= $r['id'] ?>)" title="Ajouter au panier">
                                            <i class="bi bi-cart-plus"></i>
                                        </button>
                                    </div>
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

<!-- TIROIR DE SÉLECTION -->
<div class="options-sheet-overlay" id="optionsSheetOverlay" onclick="closeOptionsSheet()"></div>
<div class="options-sheet" id="optionsSheet">
    <button class="options-sheet-close" onclick="closeOptionsSheet()"><i class="bi bi-x-lg"></i></button>
    <div class="options-sheet-handle"></div>
    <div id="optionsSheetContent">
        <div class="options-sheet-loading"><i class="bi bi-hourglass-split"></i> Chargement...</div>
    </div>
</div>

<!-- LIGHTBOX -->
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
                initRevealObserver();
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
function spawnHeartBurst(btn) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    btn.classList.remove('pop');
    void btn.offsetWidth;
    btn.classList.add('pop');

    const count = 6;
    for (let i = 0; i < count; i++) {
        const p = document.createElement('i');
        p.className = 'bi bi-heart-fill heart-particle';
        const angle = (Math.PI * 2 * i) / count + (Math.random() * 0.5 - 0.25);
        const distance = 22 + Math.random() * 14;
        p.style.setProperty('--tx', Math.cos(angle) * distance + 'px');
        p.style.setProperty('--ty', Math.sin(angle) * distance + 'px');
        p.style.setProperty('--rot', (Math.random() * 60 - 30) + 'deg');
        btn.appendChild(p);
        setTimeout(() => p.remove(), 700);
    }
}

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
        spawnHeartBurst(btn);
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
        toast.style.borderLeftColor = '#C0392B';
        toast.querySelector('i').className = 'bi bi-x-circle-fill';
    } else if (type === 'warning') {
        toast.style.borderLeftColor = '#C8922A';
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
// TIROIR DE SÉLECTION
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

    let images = data.images_galerie || [];
    if (!images.length && data.image) {
        images = [data.image];
    }

    let vignettesHtml = '';
    images.forEach((src, index) => {
        const activeClass = index === 0 ? 'active' : '';
        vignettesHtml += `
            <div class="vignette-item ${activeClass}" data-src="${src}" onclick="changerImageSheet(this, '${src}')">
                <img src="${src}" alt="Image ${index + 1}">
            </div>
        `;
    });

    let mainImage = images.length > 0 ? images[0] : '';

    let html = `
        <div class="sheet-gallery">
            <div class="sheet-gallery-main" id="sheetGalleryMain">
                <img id="sheetMainImage" src="${mainImage}" alt="${data.nom}">
                <button type="button" class="sheet-expand-btn" onclick="openImageLightbox(document.getElementById('sheetMainImage').src)">
                    <i class="bi bi-arrows-fullscreen"></i> Grand cadre
                </button>
            </div>
            <div class="sheet-galerie-vignettes" id="sheetGalerieVignettes">
                ${vignettesHtml}
            </div>
        </div>
        <div class="sheet-title-row">
            <div class="options-sheet-name">${data.nom}</div>
            <div class="options-sheet-price">
                <span>${new Intl.NumberFormat('fr-FR').format(data.prix)} F</span>
                ${data.prix_ancien ? `<span class="options-sheet-old-price">${new Intl.NumberFormat('fr-FR').format(data.prix_ancien)} F</span>` : ''}
            </div>
            ${data.stock !== undefined ? `<div class="sheet-stock-note">${data.stock > 0 ? '<i class=\"bi bi-check-circle-fill\" style=\"color:var(--gold)\"></i> ' + data.stock + ' en stock' : '<i class=\"bi bi-x-circle-fill\" style=\"color:#C0392B\"></i> Rupture de stock'}</div>` : ''}
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

function changerImageSheet(element, src) {
    const mainImg = document.getElementById('sheetMainImage');
    if (mainImg) {
        mainImg.src = src;
    }
    
    const expandBtn = document.querySelector('.sheet-expand-btn');
    if (expandBtn) {
        expandBtn.setAttribute('onclick', "openImageLightbox('" + src + "')");
    }
    
    document.querySelectorAll('#sheetGalerieVignettes .vignette-item').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
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

function updateCartCounter(count) {
    const badge = document.getElementById('navCartBadge');
    if (!badge) return;

    badge.textContent = count;
    badge.style.display = count > 0 ? 'flex' : 'none';

    badge.style.transform = 'scale(1.3)';
    setTimeout(() => { badge.style.transform = 'scale(1)'; }, 200);
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
// LIGHTBOX
// ============================================
function openImageLightbox(src) {
    if (!src) return;
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

// ============================================
// ANIMATIONS
// ============================================
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
let revealObserver = null;

function initRevealObserver() {
    const targets = document.querySelectorAll('.reveal:not(.in-view)');
    if (!targets.length) return;

    if (prefersReducedMotion) {
        targets.forEach(el => el.classList.add('in-view'));
        return;
    }

    if (!revealObserver) {
        revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    }
    targets.forEach(el => revealObserver.observe(el));
}

function initHeroParallax() {
    if (prefersReducedMotion) return;
    const hero = document.getElementById('heroParallax');
    if (!hero) return;
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(() => {
            const offset = Math.min(window.scrollY * 0.15, 60);
            hero.style.transform = 'translateY(' + offset + 'px)';
            ticking = false;
        });
    }, { passive: true });
}

function initStickySidebarShadow() {
    const sidebar = document.getElementById('shopSidebar');
    if (!sidebar) return;
    const sentinel = document.createElement('div');
    sidebar.parentNode.insertBefore(sentinel, sidebar);
    const obs = new IntersectionObserver(([entry]) => {
        sidebar.classList.toggle('is-stuck', !entry.isIntersecting);
    }, { threshold: 1 });
    obs.observe(sentinel);
}

document.addEventListener('DOMContentLoaded', () => {
    initRevealObserver();
    initHeroParallax();
    initStickySidebarShadow();
});
</script>

<?php require_once '../includes/footer.php'; ?>