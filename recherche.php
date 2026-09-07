<?php
// ============================================
// RECHERCHE GLOBALE AJAX - UNIVERSELLE
// ============================================
header('Content-Type: application/json');

if (!isset($_GET['q']) || trim($_GET['q']) === '') {
    echo json_encode(['produits' => [], 'plats' => [], 'pages' => [], 'categories' => []]);
    exit;
}

$q = trim($_GET['q']);
$q_lower = strtolower($q);

// Connexion à la base de données
$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['erreur' => 'Connexion impossible']);
    exit;
}

// ============================================
// 1. RECHERCHE PRODUITS
// ============================================
$produits = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, nom, description, prix, prix_promo, image_principale, est_nouveau, est_promo
        FROM produits 
        WHERE est_visible = 1 
        AND (nom LIKE :q OR description LIKE :q OR couleurs LIKE :q OR tailles LIKE :q)
        ORDER BY est_nouveau DESC, created_at DESC
        LIMIT 10
    ");
    $stmt->execute([':q' => "%$q%"]);
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// ============================================
// 2. RECHERCHE ABOYAS
// ============================================
if (empty($produits)) {
    try {
        $stmt3 = $pdo->prepare("
            SELECT id, nom, description, prix, prix_promo, image_principale
            FROM produits_abayas 
            WHERE est_visible = 1 
            AND (nom LIKE :q OR description LIKE :q)
            LIMIT 10
        ");
        $stmt3->execute([':q' => "%$q%"]);
        $produits_abayas = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($produits_abayas)) {
            foreach ($produits_abayas as $abaya) {
                $produits[] = [
                    'id' => $abaya['id'],
                    'nom' => $abaya['nom'],
                    'description' => $abaya['description'],
                    'prix' => $abaya['prix'],
                    'prix_promo' => $abaya['prix_promo'],
                    'image_principale' => $abaya['image_principale'],
                    'est_nouveau' => 1
                ];
            }
        }
    } catch(Exception $e) {}
}

// ============================================
// 3. RECHERCHE PLATS DU RESTAURANT
// ============================================
$plats = [];
try {
    $stmt2 = $pdo->prepare("
        SELECT id, nom, description, prix
        FROM plats 
        WHERE est_visible = 1 
        AND (nom LIKE :q OR description LIKE :q)
        LIMIT 6
    ");
    $stmt2->execute([':q' => "%$q%"]);
    $plats = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// ============================================
// 4. RECHERCHE PAGES DU SITE
// ============================================
$pages = [];

$page_keywords = [
    ['titre' => 'Accueil', 'url' => 'index.php', 'icone' => 'bi-house-door', 'mots' => ['accueil', 'home', 'awa', 'sugu']],
    ['titre' => 'Boutique - Tous les produits', 'url' => 'boutique/catalogue.php', 'icone' => 'bi-bag', 'mots' => ['boutique', 'produits', 'catalogue', 'shop', 'achat']],
    ['titre' => 'Nouveautés', 'url' => 'boutique/nouveautes.php', 'icone' => 'bi-stars', 'mots' => ['nouveau', 'nouveaute', 'nouveautes', 'new', 'arrivage', 'derniere']],
    ['titre' => 'Promotions', 'url' => 'boutique/promotions.php', 'icone' => 'bi-percent', 'mots' => ['promo', 'promotion', 'promotions', 'reduction', 'solde', 'soldes', 'discount']],
    ['titre' => 'Restaurant Sofia', 'url' => 'restaurant/menu.php', 'icone' => 'bi-cup-hot', 'mots' => ['restaurant', 'sofia', 'menu', 'manger', 'plat', 'repas', 'cuisine']],
    ['titre' => 'Vidéos', 'url' => 'boutique/videos.php', 'icone' => 'bi-camera-reels', 'mots' => ['video', 'videos', 'video', 'tiktok', 'instagram']],
    ['titre' => 'Suivi de commande', 'url' => 'boutique/suivi.php', 'icone' => 'bi-truck', 'mots' => ['suivi', 'commande', 'livraison', 'tracking', 'suivre']],
    ['titre' => 'Panier', 'url' => 'boutique/panier.php', 'icone' => 'bi-cart3', 'mots' => ['panier', 'cart', 'achat']],
    ['titre' => 'Avis clients', 'url' => 'boutique/avis.php', 'icone' => 'bi-chat-heart', 'mots' => ['avis', 'testimonial', 'commentaire', 'client', 'feedback']],
    ['titre' => 'Connexion', 'url' => 'client/connexion.php', 'icone' => 'bi-person', 'mots' => ['connexion', 'login', 'compte', 'client']],
    ['titre' => 'Mon compte', 'url' => 'client/mon_compte.php', 'icone' => 'bi-person-circle', 'mots' => ['compte', 'profile', 'profil', 'client']],
];

foreach ($page_keywords as $page) {
    foreach ($page['mots'] as $mot) {
        if (strpos($q_lower, $mot) !== false) {
            $pages[] = [
                'titre' => $page['titre'],
                'url' => $page['url'],
                'icone' => $page['icone']
            ];
            break;
        }
    }
}

// ============================================
// 5. RECHERCHE CATEGORIES
// ============================================
$categories = [];
try {
    $stmt4 = $pdo->prepare("
        SELECT id, nom, description
        FROM categories 
        WHERE nom LIKE :q OR description LIKE :q
        LIMIT 5
    ");
    $stmt4->execute([':q' => "%$q%"]);
    $categories = $stmt4->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// ============================================
// 6. FILTRE SPÉCIAL : NOUVEAUTÉS
// ============================================
if (strpos($q_lower, 'nouveau') !== false || strpos($q_lower, 'nouveaute') !== false || strpos($q_lower, 'new') !== false) {
    $stmt_new = $pdo->prepare("
        SELECT id, nom, description, prix, prix_promo, image_principale, est_nouveau
        FROM produits 
        WHERE est_visible = 1 AND est_nouveau = 1
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt_new->execute();
    $produits_new = $stmt_new->fetchAll(PDO::FETCH_ASSOC);
    $produits = array_merge($produits, $produits_new);
    
    $pages[] = [
        'titre' => 'Voir toutes les nouveautés',
        'url' => 'boutique/nouveautes.php',
        'icone' => 'bi-stars'
    ];
}

// ============================================
// 7. FILTRE SPÉCIAL : PROMOTIONS
// ============================================
if (strpos($q_lower, 'promo') !== false || strpos($q_lower, 'promotion') !== false || strpos($q_lower, 'reduction') !== false || strpos($q_lower, 'solde') !== false) {
    $stmt_promo = $pdo->prepare("
        SELECT id, nom, description, prix, prix_promo, image_principale, est_promo
        FROM produits 
        WHERE est_visible = 1 AND est_promo = 1
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt_promo->execute();
    $produits_promo = $stmt_promo->fetchAll(PDO::FETCH_ASSOC);
    $produits = array_merge($produits, $produits_promo);
    
    $pages[] = [
        'titre' => 'Voir toutes les promotions',
        'url' => 'boutique/promotions.php',
        'icone' => 'bi-percent'
    ];
}

// ============================================
// ENVOI DE LA RÉPONSE
// ============================================
echo json_encode([
    'produits' => $produits,
    'plats' => $plats,
    'pages' => $pages,
    'categories' => $categories
]);
?>