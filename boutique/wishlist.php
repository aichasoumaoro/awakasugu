<?php
// ============================================
// LISTE DE SOUHAITS - Awa Ka Sugu
// ============================================

// ============================================
// SESSION PUBLIQUE SÉPARÉE
// ============================================
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// VÉRIFICATION MAINTENANCE
// ============================================
require_once '../includes/maintenance_check.php';

// ============================================
// CONNEXION BDD + ACTIONS AJOUTER/SUPPRIMER
// (déplacé avant header.php pour pouvoir rediriger
// sans déclencher "headers already sent")
// ============================================
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

// Vérifier si client est connecté
$client_id = $_SESSION['client_id'] ?? null;

// Ajouter à la wishlist
if (isset($_GET['ajouter'])) {
    $produit_id = (int)$_GET['ajouter'];
    if ($client_id) {
        $stmt = $pdo->prepare("SELECT * FROM wishlist WHERE client_id = ? AND produit_id = ?");
        $stmt->execute([$client_id, $produit_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO wishlist (client_id, produit_id) VALUES (?, ?)");
            $stmt->execute([$client_id, $produit_id]);
        }
    }
    header('Location: wishlist.php');
    exit;
}

// Supprimer de la wishlist
if (isset($_GET['supprimer'])) {
    $produit_id = (int)$_GET['supprimer'];
    if ($client_id) {
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE client_id = ? AND produit_id = ?");
        $stmt->execute([$client_id, $produit_id]);
    }
    header('Location: wishlist.php');
    exit;
}

$titre_page = 'Ma wishlist - IBA Design';
$meta_desc = 'Retrouvez tous vos produits favoris.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Récupérer les produits de la wishlist
$produits = [];
if ($client_id) {
    $stmt = $pdo->prepare("
        SELECT p.* FROM produits p
        INNER JOIN wishlist w ON w.produit_id = p.id
        WHERE w.client_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$client_id]);
    $produits = $stmt->fetchAll();
}
?>

<style>
/* ========== PAGE WISHLIST ========== */
.wishlist-header {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
    padding: 50px 0 40px;
    text-align: center;
    margin-bottom: 40px;
}
.wishlist-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.5rem;
    color: #C8922A;
    margin-bottom: 10px;
}
.wishlist-header p {
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
}
.container-custom {
    max-width: 1300px;
    margin: 0 auto;
    padding: 0 20px 60px;
}
.products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
}
.product-card {
    background: #FFFFFF;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s;
    text-decoration: none;
    border: 1px solid #F0F0F0;
    position: relative;
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 25px rgba(0,0,0,0.08);
}
.product-image {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: #F8F8F8;
}
.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}
.product-card:hover .product-image img {
    transform: scale(1.03);
}
.product-info {
    padding: 16px;
    text-align: center;
}
.product-name {
    font-size: 0.9rem;
    font-weight: 500;
    color: #1A1A1A;
    margin-bottom: 6px;
}
.product-price {
    font-size: 0.95rem;
    font-weight: 700;
    color: #C8922A;
    margin-bottom: 10px;
}
.btn-voir {
    display: block;
    background: #C8922A;
    color: white;
    text-align: center;
    padding: 8px;
    border-radius: 30px;
    text-decoration: none;
    font-size: 0.75rem;
    font-weight: 600;
    transition: background 0.3s;
    margin-top: 5px;
}
.btn-voir:hover {
    background: #9A6E1A;
    color: white;
}
.btn-supprimer {
    background: none;
    border: none;
    color: #E74C3C;
    cursor: pointer;
    font-size: 0.75rem;
    margin-top: 8px;
    transition: color 0.3s;
}
.btn-supprimer:hover {
    color: #C0392B;
}
.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 16px;
    grid-column: 1/-1;
}
.empty-state i {
    font-size: 3.5rem;
    color: #E8E0D8;
    display: block;
    margin-bottom: 15px;
}
.empty-state h3 {
    font-family: 'Playfair Display', serif;
    color: #0D0D0D;
    margin-bottom: 10px;
}
.empty-state p {
    color: #8A99AA;
    font-size: 0.95rem;
    margin-bottom: 20px;
}
.btn-primary-custom {
    display: inline-block;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #fff;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-primary-custom:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
    color: white;
}
.btn-outline-custom {
    display: inline-block;
    background: transparent;
    color: #0D0D0D;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    border: 2px solid #0D0D0D;
    transition: all 0.3s;
}
.btn-outline-custom:hover {
    background: #0D0D0D;
    color: #C8922A;
}
.heart-icon {
    color: #E74C3C;
    font-size: 1.5rem;
}
@media (max-width: 1100px) {
    .products-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 800px) {
    .products-grid { grid-template-columns: repeat(2, 1fr); }
    .container-custom { padding: 0 20px 40px; }
    .wishlist-header h1 { font-size: 1.8rem; }
}
@media (max-width: 500px) {
    .products-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Header -->
<div class="wishlist-header">
    <div class="container-custom" style="padding-bottom:0;">
        <h1>❤️ Ma wishlist</h1>
        <p>Mes coups de cœur</p>
    </div>
</div>

<div class="container-custom">
    <div class="products-grid">
        <?php if(!$client_id): ?>
            <div class="empty-state">
                <i class="bi bi-heart"></i>
                <h3>Connectez-vous pour voir vos favoris</h3>
                <p>Créez un compte ou connectez-vous pour sauvegarder vos produits préférés.</p>
                <div style="display:flex; gap:15px; justify-content:center; flex-wrap:wrap;">
                    <a href="<?= SITE_URL ?>/client/connexion.php" class="btn-primary-custom">
                        <i class="bi bi-box-arrow-in-right"></i> Se connecter
                    </a>
                    <a href="<?= SITE_URL ?>/client/inscription.php" class="btn-outline-custom">
                        <i class="bi bi-person-plus"></i> Créer un compte
                    </a>
                </div>
            </div>
        <?php elseif(empty($produits)): ?>
            <div class="empty-state">
                <i class="bi bi-heart"></i>
                <h3>Votre wishlist est vide</h3>
                <p>Ajoutez vos produits préférés en cliquant sur le cœur ❤️</p>
                <a href="catalogue.php" class="btn-primary-custom">
                    <i class="bi bi-bag"></i> Découvrir la boutique
                </a>
            </div>
        <?php else: ?>
            <?php foreach($produits as $p): 
                $img = !empty($p['image_principale']) && file_exists('../uploads/produits/'.$p['image_principale']) 
                    ? '../uploads/produits/'.$p['image_principale'] 
                    : 'https://placehold.co/400x300/F8F8F8/C8922A?text='.urlencode($p['nom']);
            ?>
            <div class="product-card">
                <div class="product-image">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>">
                </div>
                <div class="product-info">
                    <div class="product-name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="product-price"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</div>
                    <a href="produit.php?id=<?= $p['id'] ?>" class="btn-voir">Voir détails</a>
                    <div>
                        <a href="wishlist.php?supprimer=<?= $p['id'] ?>" class="btn-supprimer" onclick="return confirm('Retirer ce produit de votre wishlist ?')">
                            <i class="bi bi-trash"></i> Retirer
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>