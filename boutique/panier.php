<?php
// ============================================
// PANIER D'ACHAT - AWA KA SUGU
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

require_once '../includes/maintenance_check.php';
require_once '../includes/panier_fonctions.php';

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur BDD: " . $e->getMessage());
}

if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

try {
    $stmt = $pdo->query("SELECT cle, valeur FROM parametres_fonctionnalites WHERE cle LIKE 'fidelite_%'");
    $params_fidelite = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch(PDOException $e) {
    $params_fidelite = [];
}

$seuil_points = $params_fidelite['fidelite_seuil_points'] ?? 50000;
$points_par_seuil = $params_fidelite['fidelite_points_par_seuil'] ?? 1;
$reduction_points = $params_fidelite['fidelite_reduction_points'] ?? 10;
$reduction_montant = $params_fidelite['fidelite_reduction_montant'] ?? 1000;
$fidelite_actif = $params_fidelite['fidelite_actif'] ?? 1;

$wishlist_ids = [];
if (isset($_SESSION['client_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT produit_id FROM wishlist WHERE client_id = ?");
        $stmt->execute([$_SESSION['client_id']]);
        $wishlist_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(PDOException $e) {
        $wishlist_ids = [];
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'ajouter') {
    $produit_id = (int)$_POST['produit_id'];
    $quantite = (int)$_POST['quantite'];
    $couleur_id = isset($_POST['couleur_id']) ? (int)$_POST['couleur_id'] : 0;
    $taille_id = isset($_POST['taille_id']) ? (int)$_POST['taille_id'] : 0;
    
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

    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND est_visible = 1");
    $stmt->execute([$produit_id]);
    $produit = $stmt->fetch();

    if ($produit) {
        $prix = ($produit['prix_promo'] && $produit['prix_promo'] > 0 && $produit['prix_promo'] < $produit['prix']) 
                ? $produit['prix_promo'] 
                : $produit['prix'];
        
        $cle_panier = $produit_id . '_' . $couleur_id . '_' . $taille_id;
        
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
            sauvegarderPanierClient($_SESSION['client_id'], $_SESSION['panier'], $pdo);
        }
    }
    header('Location: panier.php');
    exit;
}

if (isset($_GET['modifier'])) {
    $cle = $_GET['modifier'];
    $qte = (int)$_GET['qte'];
    if (isset($_SESSION['panier'][$cle])) {
        if ($qte <= 0) {
            unset($_SESSION['panier'][$cle]);
        } else {
            $_SESSION['panier'][$cle]['quantite'] = $qte;
        }
    }
    if (empty($_SESSION['panier'])) {
        unset($_SESSION['code_promo']);
        unset($_SESSION['reduction_points']);
    }
    if (isset($_SESSION['client_id'])) {
        sauvegarderPanierClient($_SESSION['client_id'], $_SESSION['panier'], $pdo);
    }
    header('Location: panier.php');
    exit;
}

if (isset($_GET['supprimer'])) {
    $cle = $_GET['supprimer'];
    unset($_SESSION['panier'][$cle]);
    if (empty($_SESSION['panier'])) {
        unset($_SESSION['code_promo']);
        unset($_SESSION['reduction_points']);
    }
    if (isset($_SESSION['client_id'])) {
        sauvegarderPanierClient($_SESSION['client_id'], $_SESSION['panier'], $pdo);
    }
    header('Location: panier.php');
    exit;
}

if (isset($_GET['vider'])) {
    $_SESSION['panier'] = [];
    unset($_SESSION['code_promo']);
    unset($_SESSION['reduction_points']);
    if (isset($_SESSION['client_id'])) {
        viderPanierBDD($_SESSION['client_id'], $pdo);
    }
    header('Location: panier.php');
    exit;
}

if (isset($_POST['appliquer_promo'])) {
    $code = strtoupper(trim($_POST['code_promo']));
    
    if (empty($code)) {
        $_SESSION['message_promo'] = 'Veuillez saisir un code promo.';
        $_SESSION['message_promo_type'] = 'warning';
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM codes_promo 
            WHERE code = ? AND est_actif = 1
            AND (date_expiration IS NULL OR date_expiration > NOW())
            AND (nb_utilisations_max IS NULL OR nb_utilisations < nb_utilisations_max)
        ");
        $stmt->execute([$code]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($promo) {
            $total = 0;
            foreach ($_SESSION['panier'] as $item) {
                $total += $item['prix'] * $item['quantite'];
            }
            
            if ($total >= $promo['min_achat']) {
                if ($promo['type'] == 'pourcentage') {
                    $reduction = $total * ($promo['valeur'] / 100);
                } else {
                    $reduction = $promo['valeur'];
                }
                if ($reduction > $total) {
                    $reduction = $total;
                }
                
                $_SESSION['code_promo'] = [
                    'code' => $promo['code'],
                    'id' => $promo['id'],
                    'type' => $promo['type'],
                    'valeur' => $promo['valeur'],
                    'reduction' => $reduction
                ];
                $_SESSION['message_promo'] = 'Code promo "' . $promo['code'] . '" appliqué !';
                $_SESSION['message_promo_type'] = 'success';
            } else {
                $_SESSION['message_promo'] = 'Achat minimum de ' . number_format($promo['min_achat'], 0, ',', ' ') . ' FCFA requis.';
                $_SESSION['message_promo_type'] = 'warning';
            }
        } else {
            $_SESSION['message_promo'] = 'Code promo invalide ou expiré.';
            $_SESSION['message_promo_type'] = 'error';
        }
    }
    header('Location: panier.php');
    exit;
}

if (isset($_GET['supprimer_promo'])) {
    unset($_SESSION['code_promo']);
    $_SESSION['message_promo'] = 'Code promo retiré.';
    $_SESSION['message_promo_type'] = 'success';
    header('Location: panier.php');
    exit;
}

if (isset($_POST['appliquer_points'])) {
    $points_a_utiliser = (int)$_POST['points_a_utiliser'];
    $client_id = $_SESSION['client_id'] ?? 0;
    
    if ($client_id > 0 && $points_a_utiliser > 0) {
        $stmt = $pdo->prepare("SELECT points FROM points_fidelite WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $pts = $stmt->fetch();
        
        if ($pts && $pts['points'] >= $points_a_utiliser) {
            $nb_lots = floor($points_a_utiliser / $reduction_points);
            $reduction = $nb_lots * $reduction_montant;
            
            $_SESSION['reduction_points'] = [
                'points_utilises' => $points_a_utiliser,
                'montant' => $reduction
            ];
            
            $_SESSION['message_points'] = $points_a_utiliser . ' points utilisés !';
            $_SESSION['message_points_type'] = 'success';
            
            $pdo->prepare("UPDATE points_fidelite SET points = points - ? WHERE client_id = ?")
                ->execute([$points_a_utiliser, $client_id]);
            
            $pdo->prepare("INSERT INTO historique_points (client_id, points, type, description) VALUES (?, ?, 'utilisation', ?)")
                ->execute([$client_id, -$points_a_utiliser, "Utilisation de $points_a_utiliser points"]);
            
        } else {
            $_SESSION['message_points'] = 'Points insuffisants !';
            $_SESSION['message_points_type'] = 'error';
        }
    } else {
        $_SESSION['message_points'] = 'Veuillez vous connecter.';
        $_SESSION['message_points_type'] = 'warning';
    }
    header('Location: panier.php');
    exit;
}

if (isset($_GET['retirer_points'])) {
    if (isset($_SESSION['reduction_points'])) {
        $points_utilises = $_SESSION['reduction_points']['points_utilises'];
        $client_id = $_SESSION['client_id'] ?? 0;
        
        if ($client_id > 0 && $points_utilises > 0) {
            $pdo->prepare("UPDATE points_fidelite SET points = points + ? WHERE client_id = ?")
                ->execute([$points_utilises, $client_id]);
            
            $pdo->prepare("INSERT INTO historique_points (client_id, points, type, description) VALUES (?, ?, 'restitution', ?)")
                ->execute([$client_id, $points_utilises, "Restitution de $points_utilises points"]);
        }
        unset($_SESSION['reduction_points']);
        $_SESSION['message_points'] = 'Points restitués.';
        $_SESSION['message_points_type'] = 'success';
    }
    header('Location: panier.php');
    exit;
}

$total = 0;
foreach ($_SESSION['panier'] as $item) {
    $total += $item['prix'] * $item['quantite'];
}

$reduction_appliquee = 0;
if (isset($_SESSION['code_promo'])) {
    $reduction_appliquee = $_SESSION['code_promo']['reduction'] ?? 0;
    if ($reduction_appliquee > $total) {
        $reduction_appliquee = $total;
        $_SESSION['code_promo']['reduction'] = $reduction_appliquee;
    }
    if ($total == 0) {
        unset($_SESSION['code_promo']);
        $reduction_appliquee = 0;
    }
}

$reduction_points_montant = 0;
$points_utilises = 0;
if (isset($_SESSION['reduction_points'])) {
    $points_utilises = $_SESSION['reduction_points']['points_utilises'] ?? 0;
    $reduction_points_montant = $_SESSION['reduction_points']['montant'] ?? 0;
}

$total_apres_reductions = $total - $reduction_appliquee - $reduction_points_montant;
if ($total_apres_reductions < 0) $total_apres_reductions = 0;

$livraison_texte = '3h';

$points_disponibles = 0;
if (isset($_SESSION['client_id'])) {
    $stmt = $pdo->prepare("SELECT points FROM points_fidelite WHERE client_id = ?");
    $stmt->execute([$_SESSION['client_id']]);
    $pts = $stmt->fetch();
    $points_disponibles = $pts['points'] ?? 0;
}

$message_promo = $_SESSION['message_promo'] ?? '';
$message_promo_type = $_SESSION['message_promo_type'] ?? 'success';
unset($_SESSION['message_promo'], $_SESSION['message_promo_type']);

$message_points = $_SESSION['message_points'] ?? '';
$message_points_type = $_SESSION['message_points_type'] ?? 'success';
unset($_SESSION['message_points'], $_SESSION['message_points_type']);

function getProductImageForCart($image) {
    if (empty($image)) return '';
    
    $image = trim($image);
    $image_name = pathinfo($image, PATHINFO_FILENAME);
    $extension = pathinfo($image, PATHINFO_EXTENSION);
    
    $dossiers = [
        '../uploads/produits/',
        '../uploads/produits/voile/',
        '../uploads/produits/pret a porter femme/',
        '../uploads/produits/les tallons/',
        '../uploads/produits/fermés/',
        '../uploads/produits/les turbants/',
        '../uploads/produits/les foulards/',
        '../uploads/produits/les foullards/',
        '../uploads/produits/port-monaie/',
        '../uploads/produits/sacs a mains/',
        '../uploads/produits/ensemble tallons sacs/',
        '../uploads/produits/abayas/',
        '../uploads/produits/abayas pour enfants/',
        '../uploads/produits/Port-monaie/',
        '../uploads/produits/port-monnaie/',
        'uploads/produits/',
        'uploads/',
    ];
    
    $extensions = ['', '.jpeg', '.jpg', '.png', '.gif', '.webp'];
    if (!empty($extension)) {
        $extensions = array_merge([$extension], $extensions);
    }
    
    foreach ($dossiers as $dossier) {
        foreach ($extensions as $ext) {
            $test_path = $dossier . $image_name . $ext;
            if (file_exists($test_path)) return $test_path;
        }
    }
    
    if (!empty($extension)) {
        foreach ($dossiers as $dossier) {
            $test_path = $dossier . $image;
            if (file_exists($test_path)) return $test_path;
        }
    }
    
    return '';
}

$produits_suggeres = [];
if (!empty($_SESSION['panier'])) {
    try {
        $ids_panier = [];
        foreach ($_SESSION['panier'] as $item) {
            $ids_panier[] = (int)$item['id'];
        }
        $ids_panier = array_unique($ids_panier);
        
        if (!empty($ids_panier)) {
            $placeholders = implode(',', array_fill(0, count($ids_panier), '?'));
            $sql_sug = "SELECT * FROM produits WHERE est_visible = 1 AND id NOT IN ($placeholders) ORDER BY RAND() LIMIT 4";
            $stmt_sug = $pdo->prepare($sql_sug);
            $stmt_sug->execute($ids_panier);
            $produits_suggeres = $stmt_sug->fetchAll();
        }
    } catch(PDOException $e) {
        $produits_suggeres = [];
    }
}

$titre_page = 'Mon panier - IBA Design';
$meta_desc = 'Consultez et gérez votre panier d\'achat.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
:root {
    --gold: #C8922A;
    --gold-deep: #9A6E1A;
    --gold-light: #E8C070;
    --ink: #0D0D0D;
    --muted: #8A99AA;
    --line: #EEF0F4;
    --line-soft: #F4F6F9;
    --success: #27AE60;
    --danger: #E74C3C;
    --radius: 18px;
    --shadow-sm: 0 2px 8px rgba(13,13,13,0.04);
    --shadow-md: 0 8px 24px rgba(13,13,13,0.06);
    --ease: cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

* { box-sizing: border-box; }

.panier-container {
    max-width: 1180px;
    margin: 0 auto;
    padding: 30px 20px 70px;
}

.panier-header { text-align: center; padding: 10px 0 40px; }
.panier-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    font-weight: 600;
    color: var(--ink);
    margin: 0 0 6px;
    letter-spacing: -0.5px;
}
.panier-header h1 span { color: var(--gold); font-style: italic; }
.panier-header p { color: var(--muted); font-size: 0.9rem; margin: 0; }
.panier-header .badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(200,146,42,0.08);
    color: var(--gold-deep);
    font-size: 0.72rem;
    font-weight: 600;
    padding: 5px 16px;
    border-radius: 30px;
    border: 1px solid rgba(200,146,42,0.15);
    margin-top: 14px;
}

.panier-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 26px;
    margin-top: 10px;
}

.cart-card {
    background: #fff;
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--line-soft);
    overflow: hidden;
    transition: box-shadow 0.3s var(--ease);
}
.cart-card:hover { box-shadow: var(--shadow-md); }

.cart-card-header {
    padding: 16px 24px;
    background: linear-gradient(180deg, #FCFCFD 0%, #F8F9FB 100%);
    border-bottom: 1px solid var(--line-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.cart-card-header h3 {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--ink);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 9px;
}
.cart-card-header h3 i { color: var(--gold); font-size: 1rem; }
.cart-card-header .count {
    font-size: 0.68rem;
    color: var(--muted);
    background: #fff;
    padding: 4px 14px;
    border-radius: 20px;
    border: 1px solid var(--line);
    font-weight: 500;
}

.product-row {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 24px;
    border-bottom: 1px solid var(--line-soft);
    transition: background 0.25s var(--ease);
    position: relative;
}
.product-row::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: var(--gold);
    opacity: 0;
    transition: opacity 0.3s var(--ease);
}
.product-row:hover { background: #FCFCFD; }
.product-row:hover::before { opacity: 1; }
.product-row:last-child { border-bottom: none; }

.product-img {
    width: 72px;
    height: 72px;
    border-radius: 12px;
    background: #F5F6F8;
    border: 1px solid var(--line);
    flex-shrink: 0;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s var(--ease);
}
.product-row:hover .product-img { transform: scale(1.04); }
.product-img img { width: 100%; height: 100%; object-fit: cover; }
.product-img .fallback { color: var(--gold); font-size: 1.5rem; opacity: 0.35; }

.product-info { flex: 1; min-width: 0; }
.product-info .name {
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--ink);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 4px;
}
.product-info .meta {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 4px;
}
.product-info .meta span {
    font-size: 0.62rem;
    color: #6B7280;
    background: #F4F6F9;
    padding: 3px 11px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
}
.product-info .meta .dot {
    width: 8px; height: 8px; border-radius: 50%;
    display: inline-block;
    border: 1px solid rgba(0,0,0,0.06);
}

.product-price {
    font-size: 0.8rem; color: var(--muted); font-weight: 500;
    min-width: 70px; text-align: right;
    font-variant-numeric: tabular-nums;
}

.qty-box {
    display: flex;
    align-items: center;
    border: 1.5px solid var(--line);
    border-radius: 10px;
    overflow: hidden;
    flex-shrink: 0;
    background: #fff;
    transition: border-color 0.25s var(--ease);
}
.qty-box:hover { border-color: var(--gold); }
.qty-box button {
    width: 32px; height: 36px;
    border: none; background: transparent;
    font-size: 1rem; color: var(--muted);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s var(--ease);
}
.qty-box button:hover { background: var(--gold); color: #fff; }
.qty-box input {
    width: 36px; height: 36px;
    border: none;
    border-left: 1.5px solid var(--line);
    border-right: 1.5px solid var(--line);
    text-align: center;
    font-size: 0.82rem; font-weight: 600;
    color: var(--ink); font-family: inherit;
    background: #fff;
    font-variant-numeric: tabular-nums;
}
.qty-box input:focus { outline: none; }

.product-total {
    font-weight: 700; font-size: 0.88rem; color: var(--gold);
    min-width: 90px; text-align: right;
    font-variant-numeric: tabular-nums;
}

.btn-remove {
    width: 32px; height: 32px; border-radius: 9px;
    background: #FFF5F5;
    border: 1px solid rgba(231,76,60,0.1);
    color: var(--danger);
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; font-size: 0.75rem;
    flex-shrink: 0;
    transition: all 0.25s var(--ease);
}
.btn-remove:hover {
    background: var(--danger); color: #fff; transform: rotate(8deg);
}

.cart-actions {
    padding: 16px 24px;
    background: #FCFCFD;
    border-top: 1px solid var(--line-soft);
    display: flex; gap: 10px; flex-wrap: wrap;
    justify-content: space-between;
}
.cart-actions a {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 20px; border-radius: 10px;
    border: 1.5px solid var(--line);
    background: #fff;
    font-weight: 600; font-size: 0.76rem;
    color: #6B7280;
    text-decoration: none;
    transition: all 0.25s var(--ease);
}
.cart-actions a:hover {
    border-color: var(--gold); color: var(--gold-deep);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(200,146,42,0.08);
}
.cart-actions a.danger:hover {
    border-color: var(--danger); color: var(--danger);
    box-shadow: 0 4px 12px rgba(231,76,60,0.08);
}

.sidebar {
    display: flex; flex-direction: column; gap: 18px;
    position: sticky; top: 100px; align-self: start;
}

.recap-card, .promo-card, .points-card {
    background: #fff;
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--line-soft);
    overflow: hidden;
    transition: box-shadow 0.3s var(--ease);
}
.recap-card:hover, .promo-card:hover, .points-card:hover { box-shadow: var(--shadow-md); }

.recap-card-header, .promo-card-header, .points-card-header {
    padding: 16px 22px;
    background: linear-gradient(180deg, #FCFCFD 0%, #F8F9FB 100%);
    border-bottom: 1px solid var(--line-soft);
    font-weight: 700; font-size: 0.82rem;
    color: var(--ink);
    display: flex; align-items: center; gap: 9px;
}
.recap-card-header i, .promo-card-header i, .points-card-header i {
    color: var(--gold); font-size: 0.95rem;
}

.recap-body { padding: 20px 22px; }
.recap-row {
    display: flex; justify-content: space-between;
    padding: 7px 0; font-size: 0.83rem; align-items: center;
}
.recap-row .label { color: var(--muted); }
.recap-row .value { font-weight: 600; color: var(--ink); font-variant-numeric: tabular-nums; }
.recap-row .value.green { color: var(--success); }
.recap-row .value.gold { color: var(--gold); }

.recap-divider {
    border: none;
    border-top: 1.5px dashed var(--line);
    margin: 12px 0;
}
.recap-total {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1510 100%);
    border-radius: 12px;
    padding: 16px 20px;
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 14px;
    box-shadow: 0 6px 18px rgba(13,13,13,0.15);
    position: relative; overflow: hidden;
}
.recap-total::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    opacity: 0.5;
}
.recap-total .label {
    color: rgba(255,255,255,0.5);
    font-size: 0.68rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 1.5px;
}
.recap-total .amount {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem; font-weight: 700;
    color: var(--gold); letter-spacing: -0.3px;
}
.btn-checkout {
    display: flex; align-items: center; justify-content: center; gap: 9px;
    width: 100%; padding: 15px;
    margin-top: 16px; border-radius: 12px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    border: none; font-weight: 700; font-size: 0.9rem;
    color: #0A0804;
    text-decoration: none;
    transition: all 0.3s var(--ease);
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(200,146,42,0.22);
}
.btn-checkout:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 26px rgba(200,146,42,0.35);
}

.promo-card-body, .points-card-body { padding: 16px 20px; }
.promo-input-group { display: flex; gap: 8px; }
.promo-input {
    flex: 1;
    padding: 9px 14px;
    border: 1.5px solid var(--line);
    border-radius: 10px;
    font-size: 0.78rem; font-family: inherit;
    background: #FAFBFC;
    text-transform: uppercase;
    transition: all 0.25s var(--ease);
    letter-spacing: 0.5px; font-weight: 500;
}
.promo-input::placeholder { text-transform: none; letter-spacing: 0; color: #B0B7C3; }
.promo-input:focus {
    outline: none;
    border-color: var(--gold);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(200,146,42,0.08);
}
.btn-promo {
    padding: 9px 18px; border-radius: 10px;
    border: none; background: var(--ink);
    color: #fff; font-weight: 600;
    font-size: 0.75rem; cursor: pointer;
    transition: all 0.25s var(--ease);
    white-space: nowrap;
}
.btn-promo:hover { background: var(--gold); color: #0A0804; transform: translateY(-1px); }

.promo-applied {
    display: flex; justify-content: space-between; align-items: center;
    background: linear-gradient(135deg, #F0FBF4 0%, #E8F8EE 100%);
    border: 1px solid rgba(39,174,96,0.15);
    border-radius: 10px;
    padding: 10px 14px;
}
.promo-applied .code {
    font-weight: 700; font-size: 0.78rem;
    color: var(--ink); letter-spacing: 1px;
}
.promo-applied .amount {
    font-weight: 700; color: var(--success);
    font-size: 0.8rem; font-variant-numeric: tabular-nums;
}
.btn-undo {
    background: rgba(231,76,60,0.1);
    color: var(--danger);
    border: none; padding: 3px 11px;
    border-radius: 7px; font-size: 0.65rem;
    font-weight: 700; cursor: pointer;
    transition: all 0.2s var(--ease);
    text-decoration: none;
    display: inline-flex; align-items: center;
}
.btn-undo:hover { background: var(--danger); color: #fff; transform: scale(1.05); }

.promo-msg {
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.72rem; font-weight: 500;
    display: flex; align-items: center; gap: 6px;
    animation: fadeIn 0.3s var(--ease);
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.promo-msg.success { background: #D4EDDA; color: #0A3622; }
.promo-msg.error { background: #F8D7DA; color: #721C24; }
.promo-msg.warning { background: #FFF3CD; color: #856404; }

.points-select {
    flex: 1;
    padding: 9px 14px;
    border: 1.5px solid var(--line);
    border-radius: 10px;
    font-size: 0.76rem; font-family: inherit;
    background: #FAFBFC;
    cursor: pointer;
    transition: all 0.25s var(--ease);
    font-weight: 500; color: var(--ink);
}
.points-select:focus {
    outline: none; border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(200,146,42,0.08);
}
.points-info {
    font-size: 0.7rem; color: var(--muted);
    margin-top: 10px; line-height: 1.5;
    display: flex; align-items: flex-start; gap: 6px;
}
.points-info i { color: var(--gold); margin-top: 2px; flex-shrink: 0; }
.points-info strong { color: var(--gold-deep); font-weight: 700; }
.points-empty {
    padding: 12px;
    background: #FAFBFC;
    border-radius: 10px;
    text-align: center;
    font-size: 0.74rem; color: var(--muted);
    border: 1px dashed var(--line);
}
.points-empty strong { color: var(--gold); font-weight: 700; }

.empty-cart { text-align: center; padding: 70px 20px; }
.empty-cart .icon {
    width: 84px; height: 84px; border-radius: 50%;
    background: linear-gradient(135deg, #F8F4EE 0%, #FDF9F2 100%);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
    font-size: 2.1rem; color: var(--gold);
    border: 1px solid rgba(200,146,42,0.12);
    animation: floatSlow 3s ease-in-out infinite;
}
@keyframes floatSlow {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
}
.empty-cart h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem; font-weight: 600;
    color: var(--ink); margin: 0 0 8px;
}
.empty-cart p {
    color: var(--muted); font-size: 0.88rem; margin: 0 0 24px;
}
.btn-primary {
    display: inline-flex; align-items: center; gap: 9px;
    padding: 13px 32px;
    border-radius: 50px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: #0A0804; font-weight: 700; font-size: 0.86rem;
    text-decoration: none;
    transition: all 0.3s var(--ease);
    box-shadow: 0 6px 18px rgba(200,146,42,0.22);
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 26px rgba(200,146,42,0.35);
}

/* ═══════════════════════════════════════════
   UPSELL — CARTE PRODUIT ÉPURÉE SANS BLOC NOIR
   ═══════════════════════════════════════════ */
.upsell-section {
    margin-top: 60px;
    padding-top: 46px;
    border-top: 1px solid var(--line-soft);
}
.upsell-header { text-align: center; margin-bottom: 36px; }
.upsell-header .eyebrow {
    display: inline-block;
    font-size: 0.6rem; font-weight: 700;
    letter-spacing: 3px; text-transform: uppercase;
    color: var(--gold); margin-bottom: 8px;
}
.upsell-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem; font-weight: 600;
    color: var(--ink); margin: 0;
}
.upsell-header h2 em { color: var(--gold); font-style: italic; }

.upsell-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 22px;
}

/* CARTE PRODUIT — design épuré, AUCUN bloc noir */
.upsell-card {
    display: block;
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    text-decoration: none;
    border: 1px solid var(--line-soft);
    transition: transform 0.4s var(--ease), box-shadow 0.4s var(--ease), border-color 0.4s var(--ease);
    cursor: pointer;
    position: relative;
}
.upsell-card:hover {
    transform: translateY(-4px);
    border-color: rgba(200,146,42,0.35);
    box-shadow: 0 16px 34px rgba(20,15,5,0.08);
}

/* Image */
.upsell-card .up-img {
    position: relative;
    aspect-ratio: 3 / 4;
    overflow: hidden;
    background: #F4F5F8;
}
.upsell-card .up-img img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.6s var(--ease);
    display: block;
}
.upsell-card:hover .up-img img { transform: scale(1.05); }

/* Badge promo */
.upsell-card .up-badge {
    position: absolute;
    top: 10px; left: 10px;
    background: #E74C3C;
    color: #fff;
    font-size: 0.58rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 4px;
    z-index: 3;
    letter-spacing: 0.3px;
}

/* Bouton favoris */
.upsell-card .up-heart {
    position: absolute;
    top: 10px; right: 10px;
    z-index: 3;
    background: #fff;
    border: none;
    width: 30px; height: 30px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    color: #C9C9C9;
    transition: all 0.25s var(--ease);
    padding: 0;
}
.upsell-card .up-heart:hover { transform: scale(1.1); color: #E74C3C; }
.upsell-card .up-heart i { font-size: 0.85rem; }
.upsell-card .up-heart.liked { color: #E74C3C; }

/* Bloc infos — FOND BLANC, aucune couleur sombre */
.upsell-card .up-info {
    background: #fff;
    padding: 12px 14px 14px;
}
.upsell-card .up-name {
    font-family: 'Inter', sans-serif;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--ink);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 8px;
    letter-spacing: 0.1px;
}
.upsell-card:hover .up-name { color: var(--gold); }

/* Ligne bas — prix à gauche, panier à droite, SANS fond */
.upsell-card .up-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.upsell-card .up-price {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--gold);
    display: flex;
    align-items: baseline;
    gap: 5px;
    flex-wrap: wrap;
    line-height: 1.2;
}
.upsell-card .up-price .up-old {
    font-size: 0.68rem;
    color: #B0B0B0;
    text-decoration: line-through;
    font-weight: 400;
}
.upsell-card .up-price .up-new-promo {
    color: #E74C3C;
}

/* Bouton panier rond noir discret */
.upsell-card .up-cart {
    flex-shrink: 0;
    width: 32px; height: 32px;
    border-radius: 50%;
    background: #0D0D0D;
    border: none;
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s var(--ease);
    padding: 0;
}
.upsell-card .up-cart:hover {
    background: var(--gold);
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(200,146,42,0.3);
}

@media (max-width: 992px) {
    .panier-grid { grid-template-columns: 1fr 300px; gap: 20px; }
    .upsell-grid { grid-template-columns: repeat(3, 1fr); gap: 18px; }
}
@media (max-width: 768px) {
    .panier-grid { grid-template-columns: 1fr; }
    .sidebar { order: -1; position: static; }
    .panier-header h1 { font-size: 1.7rem; }
    .upsell-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
    .upsell-header h2 { font-size: 1.3rem; }
}
@media (max-width: 576px) {
    .panier-container { padding: 20px 12px 50px; }
    .panier-header { padding: 6px 0 28px; }
    .product-row { flex-wrap: wrap; padding: 14px 16px; gap: 10px; }
    .product-row::before { display: none; }
    .product-img { width: 56px; height: 56px; }
    .product-info { flex: 1 1 calc(100% - 70px); }
    .product-price { display: none; }
    .product-total { min-width: 80px; font-size: 0.82rem; margin-left: auto; }
    .cart-actions { justify-content: center; }
    .cart-actions a { font-size: 0.72rem; padding: 7px 14px; }
    .recap-total .amount { font-size: 1.15rem; }
    .recap-total { padding: 14px 16px; }
    .upsell-card .up-name { font-size: 0.75rem; }
    .upsell-card .up-price { font-size: 0.82rem; }
    .upsell-card .up-cart { width: 28px; height: 28px; font-size: 0.78rem; }
}
</style>

<div class="panier-container">

    <div class="panier-header">
        <h1>Mon <span>Panier</span></h1>
        <p>Vérifiez et finalisez votre commande</p>
        <?php if (!empty($_SESSION['panier'])): ?>
            <span class="badge">
                <i class="bi bi-bag-check"></i>
                <?= count($_SESSION['panier']) ?> article<?= count($_SESSION['panier']) > 1 ? 's' : '' ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if (empty($_SESSION['panier'])): ?>

        <div class="cart-card">
            <div class="empty-cart">
                <div class="icon"><i class="bi bi-bag-x"></i></div>
                <h3>Votre panier est vide</h3>
                <p>Découvrez notre collection et ajoutez vos articles préférés</p>
                <a href="catalogue.php" class="btn-primary">
                    <i class="bi bi-bag-heart"></i> Découvrir la boutique
                </a>
            </div>
        </div>

    <?php else: ?>

    <div class="panier-grid">

        <div>
            <div class="cart-card">
                <div class="cart-card-header">
                    <h3><i class="bi bi-bag"></i> Articles</h3>
                    <span class="count"><?= count($_SESSION['panier']) ?> article<?= count($_SESSION['panier']) > 1 ? 's' : '' ?></span>
                </div>

                <?php foreach ($_SESSION['panier'] as $cle => $item): ?>
                <div class="product-row">
                    <div class="product-img">
                        <?php $image_path = getProductImageForCart($item['image'] ?? ''); ?>
                        <?php if ($image_path): ?>
                            <img src="<?= htmlspecialchars($image_path) ?>" 
                                 alt="<?= htmlspecialchars($item['nom']) ?>"
                                 onerror="this.style.display='none';this.parentElement.querySelector('.fallback').style.display='flex';">
                            <span class="fallback" style="display:none;"><i class="bi bi-bag"></i></span>
                        <?php else: ?>
                            <span class="fallback"><i class="bi bi-bag"></i></span>
                        <?php endif; ?>
                    </div>

                    <div class="product-info">
                        <div class="name"><?= htmlspecialchars($item['nom']) ?></div>
                        <div class="meta">
                            <?php if (!empty($item['couleur_nom'])): ?>
                                <span><span class="dot" style="background:<?= htmlspecialchars($item['couleur_hex'] ?? '#ccc') ?>;"></span> <?= htmlspecialchars($item['couleur_nom']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item['taille_nom'])): ?>
                                <span><?= htmlspecialchars($item['taille_nom']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="product-price"><?= number_format($item['prix'], 0, ',', ' ') ?> F</div>

                    <div class="qty-box">
                        <button onclick="changeQte('<?= urlencode($cle) ?>', -1, <?= $item['quantite'] ?>)">−</button>
                        <input type="number" value="<?= $item['quantite'] ?>" min="1"
                               onchange="setQte('<?= urlencode($cle) ?>', this.value)">
                        <button onclick="changeQte('<?= urlencode($cle) ?>', 1, <?= $item['quantite'] ?>)">+</button>
                    </div>

                    <div class="product-total"><?= number_format($item['prix'] * $item['quantite'], 0, ',', ' ') ?> FCFA</div>

                    <a href="panier.php?supprimer=<?= urlencode($cle) ?>" class="btn-remove"
                       onclick="return confirm('Retirer cet article du panier ?')">
                        <i class="bi bi-trash3"></i>
                    </a>
                </div>
                <?php endforeach; ?>

                <div class="cart-actions">
                    <a href="catalogue.php"><i class="bi bi-arrow-left"></i> Continuer mes achats</a>
                    <a href="panier.php?vider=1" class="danger" onclick="return confirm('Vider tout le panier ?')">
                        <i class="bi bi-trash"></i> Vider le panier
                    </a>
                </div>
            </div>
        </div>

        <div class="sidebar">

            <div class="recap-card">
                <div class="recap-card-header">
                    <i class="bi bi-receipt"></i> Récapitulatif
                </div>
                <div class="recap-body">
                    <div class="recap-row">
                        <span class="label">Sous-total</span>
                        <span class="value"><?= number_format($total, 0, ',', ' ') ?> FCFA</span>
                    </div>
                    <?php if ($reduction_appliquee > 0): ?>
                    <div class="recap-row">
                        <span class="label">Code promo</span>
                        <span class="value green">− <?= number_format($reduction_appliquee, 0, ',', ' ') ?> FCFA</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($reduction_points_montant > 0): ?>
                    <div class="recap-row">
                        <span class="label">Points fidélité</span>
                        <span class="value gold">− <?= number_format($reduction_points_montant, 0, ',', ' ') ?> FCFA</span>
                    </div>
                    <?php endif; ?>
                    <div class="recap-row">
                        <span class="label">Livraison</span>
                        <span class="value" style="color:var(--gold);font-weight:700;"><?= $livraison_texte ?></span>
                    </div>
                    <hr class="recap-divider">
                    <div class="recap-total">
                        <span class="label">Total</span>
                        <span class="amount"><?= number_format($total_apres_reductions, 0, ',', ' ') ?> F</span>
                    </div>
                    <a href="commande.php" class="btn-checkout">
                        <i class="bi bi-bag-check"></i> Passer la commande
                    </a>
                </div>
            </div>

            <div class="promo-card">
                <div class="promo-card-header">
                    <i class="bi bi-ticket-perforated"></i> Code promo
                </div>
                <div class="promo-card-body">
                    <?php if (isset($_SESSION['code_promo'])): ?>
                        <div class="promo-applied">
                            <span class="code"><?= htmlspecialchars($_SESSION['code_promo']['code']) ?></span>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="amount">− <?= number_format($reduction_appliquee, 0, ',', ' ') ?> F</span>
                                <a href="panier.php?supprimer_promo=1" class="btn-undo">✕</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="promo-input-group">
                                <input type="text" name="code_promo" class="promo-input" placeholder="Entrez votre code">
                                <button type="submit" name="appliquer_promo" class="btn-promo">Appliquer</button>
                            </div>
                        </form>
                    <?php endif; ?>
                    <?php if ($message_promo): ?>
                        <div class="promo-msg <?= $message_promo_type ?>">
                            <?= htmlspecialchars($message_promo) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($_SESSION['client_id']) && $fidelite_actif == 1): ?>
            <div class="points-card">
                <div class="points-card-header">
                    <i class="bi bi-star-fill"></i> Points fidélité
                </div>
                <div class="points-card-body">
                    <?php if (isset($_SESSION['reduction_points'])): ?>
                        <div class="promo-applied">
                            <span class="code"><?= $points_utilises ?> pts</span>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="amount" style="color:var(--gold);">− <?= number_format($reduction_points_montant, 0, ',', ' ') ?> F</span>
                                <a href="panier.php?retirer_points=1" class="btn-undo">✕</a>
                            </div>
                        </div>
                    <?php elseif ($points_disponibles >= $reduction_points): ?>
                        <form method="POST">
                            <div class="promo-input-group">
                                <select name="points_a_utiliser" class="points-select">
                                    <?php
                                    $max_pts = floor($points_disponibles / $reduction_points) * $reduction_points;
                                    for ($i = $reduction_points; $i <= min($max_pts, 200); $i += $reduction_points):
                                        $red = floor($i / $reduction_points) * $reduction_montant; ?>
                                        <option value="<?= $i ?>"><?= $i ?> pts = <?= number_format($red, 0, ',', ' ') ?> F</option>
                                    <?php endfor; ?>
                                </select>
                                <button type="submit" name="appliquer_points" class="btn-promo">Utiliser</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="points-empty">
                            <i class="bi bi-info-circle" style="color:var(--gold);"></i>
                            Vous avez <strong><?= number_format($points_disponibles) ?></strong> pts
                        </div>
                    <?php endif; ?>
                    <div class="points-info">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>
                            <?php if ($points_disponibles > 0): ?>
                                <strong><?= number_format($points_disponibles) ?></strong> pts disponibles · jusqu'à <strong><?= floor($points_disponibles / $reduction_points) * $reduction_montant ?> F</strong> de réduction
                            <?php else: ?>
                                Gagnez des points à chaque commande !
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($message_points): ?>
                        <div class="promo-msg <?= $message_points_type ?>">
                            <?= htmlspecialchars($message_points) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- UPSELL -->
    <?php if (!empty($produits_suggeres)): ?>
    <div class="upsell-section">
        <div class="upsell-header">
            <span class="eyebrow">Complétez votre commande</span>
            <h2>Vous aimerez <em>aussi</em></h2>
        </div>
        <div class="upsell-grid">
            <?php foreach ($produits_suggeres as $sug): 
                $img_sug = getProductImageForCart($sug['image_principale'] ?? '');
                
                $prix_sug = $sug['prix'];
                $prix_ancien_sug = null;
                $est_promo_sug = false;
                
                if (isset($sug['est_promo']) && $sug['est_promo'] == 1 && 
                    isset($sug['prix_promo']) && $sug['prix_promo'] > 0 && 
                    $sug['prix_promo'] < $sug['prix']) {
                    $prix_sug = $sug['prix_promo'];
                    $prix_ancien_sug = $sug['prix'];
                    $est_promo_sug = true;
                }
                
                $pourcentage_promo = 0;
                if ($est_promo_sug && $prix_ancien_sug > 0) {
                    $pourcentage_promo = round((1 - $prix_sug / $prix_ancien_sug) * 100);
                }
                
                $is_liked = in_array($sug['id'], $wishlist_ids);
            ?>
            <a href="produit.php?id=<?= $sug['id'] ?>" class="upsell-card">
                <div class="up-img">
                    <?php if ($img_sug): ?>
                        <img src="<?= htmlspecialchars($img_sug) ?>" 
                             alt="<?= htmlspecialchars($sug['nom']) ?>"
                             loading="lazy"
                             onerror="this.src='https://placehold.co/300x400/F5F5F5/C8922A?text=Produit'">
                    <?php else: ?>
                        <img src="https://placehold.co/300x400/F5F5F5/C8922A?text=Produit" alt="Produit">
                    <?php endif; ?>

                    <?php if ($est_promo_sug): ?>
                        <div class="up-badge">-<?= $pourcentage_promo ?>%</div>
                    <?php endif; ?>

                    <button class="up-heart <?= $is_liked ? 'liked' : '' ?>" 
                            onclick="toggleWishlist(event, <?= $sug['id'] ?>, this)"
                            title="<?= $is_liked ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
                        <i class="bi <?= $is_liked ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                    </button>
                </div>

                <div class="up-info">
                    <div class="up-name"><?= htmlspecialchars($sug['nom']) ?></div>
                    <div class="up-bottom">
                        <div class="up-price">
                            <?php if ($est_promo_sug): ?>
                                <span class="up-old"><?= number_format($prix_ancien_sug, 0, ',', ' ') ?> F</span>
                                <span class="up-new-promo"><?= number_format($prix_sug, 0, ',', ' ') ?> F</span>
                            <?php else: ?>
                                <?= number_format($prix_sug, 0, ',', ' ') ?> FCFA
                            <?php endif; ?>
                        </div>
                        <button class="up-cart" 
                                onclick="openOptionsSheet(event, <?= $sug['id'] ?>)" 
                                title="Ajouter au panier">
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

<script>
function changeQte(cle, delta, current) {
    const newQte = Math.max(1, current + delta);
    window.location.href = 'panier.php?modifier=' + encodeURIComponent(cle) + '&qte=' + newQte;
}
function setQte(cle, val) {
    const newQte = Math.max(1, parseInt(val) || 1);
    window.location.href = 'panier.php?modifier=' + encodeURIComponent(cle) + '&qte=' + newQte;
}

function toggleWishlist(event, productId, button) {
    event.preventDefault();
    event.stopPropagation();
    
    const btn = button || event.currentTarget;
    const icon = btn.querySelector('i');
    const isLiked = btn.classList.contains('liked');
    const action = isLiked ? 'remove' : 'add';
    
    <?php if (!isset($_SESSION['client_id'])): ?>
        alert('Veuillez vous connecter pour ajouter aux favoris');
        return;
    <?php endif; ?>
    
    if (action === 'add') {
        btn.classList.add('liked');
        icon.className = 'bi bi-heart-fill';
    } else {
        btn.classList.remove('liked');
        icon.className = 'bi bi-heart';
    }
    
    fetch('../wishlist_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=' + action + '&produit_id=' + productId
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            if (action === 'add') {
                btn.classList.remove('liked');
                icon.className = 'bi bi-heart';
            } else {
                btn.classList.add('liked');
                icon.className = 'bi bi-heart-fill';
            }
        }
    })
    .catch(() => {
        if (action === 'add') {
            btn.classList.remove('liked');
            icon.className = 'bi bi-heart';
        } else {
            btn.classList.add('liked');
            icon.className = 'bi bi-heart-fill';
        }
    });
}

function openOptionsSheet(event, produitId) {
    event.preventDefault();
    event.stopPropagation();
    window.location.href = 'produit.php?id=' + produitId + '&ajout=1';
}
</script>

<?php require_once '../includes/footer.php'; ?>