<?php
// ============================================
// MA WISHLIST - Awa Ka Sugu
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
// ✅ VÉRIFICATION CONNEXION - AVANT TOUT HEADER
// ============================================
if (!isset($_SESSION['client_id'])) {
    header('Location: connexion.php');
    exit;
}

// ============================================
// ✅ SUPPRESSION D'UN PRODUIT - AVANT TOUT HEADER
// ============================================
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

$client_id = $_SESSION['client_id'];

if (isset($_POST['supprimer'])) {
    $produit_id = (int)$_POST['produit_id'];
    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE client_id = ? AND produit_id = ?");
    $stmt->execute([$client_id, $produit_id]);
    header('Location: ma_wishlist.php?msg=supprime');
    exit;
}

// ============================================
// ✅ INCLURE HEADER ET NAVBAR APRÈS TOUTES LES REDIRECTIONS
// ============================================
$titre_page = 'Ma wishlist';
$meta_desc  = 'Retrouvez vos produits favoris sur Awa Ka Sugu.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Récupérer les produits de la wishlist
$stmt = $pdo->prepare("
    SELECT w.*, p.nom, p.prix, p.image_principale, p.stock, p.id as produit_id
    FROM wishlist w
    JOIN produits p ON p.id = w.produit_id
    WHERE w.client_id = ?
    ORDER BY w.created_at DESC
");
$stmt->execute([$client_id]);
$wishlist = $stmt->fetchAll();

$nb_produits = count($wishlist);

// ============================================
// FONCTION POUR L'IMAGE
// ============================================
function getWishlistImage($image) {
    if (empty($image)) {
        return 'https://placehold.co/400x400/F8F8F8/C8922A?text=Produit';
    }
    
    $image = trim($image);
    $image_name = pathinfo($image, PATHINFO_FILENAME);
    $extension = pathinfo($image, PATHINFO_EXTENSION);
    
    $dossiers = [
        '../uploads/produits/',
        'uploads/produits/',
        '../uploads/produits/abayas/',
        'uploads/produits/abayas/',
        '../uploads/produits/abayas pour enfants/',
        'uploads/produits/abayas pour enfants/',
        '../uploads/produits/sacs a mains/',
        'uploads/produits/sacs a mains/',
        '../uploads/produits/port-monaie/',
        'uploads/produits/port-monaie/',
        '../uploads/produits/ensemble tallons sacs/',
        'uploads/produits/ensemble tallons sacs/',
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
        '../uploads/produits/voile/',
        'uploads/produits/voile/',
        '../assets/images/categories/',
        'assets/images/categories/',
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
    
    return 'https://placehold.co/400x400/F8F8F8/C8922A?text=' . urlencode($image_name);
}

$msg = $_GET['msg'] ?? '';
?>

<style>
.wishlist-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}
.alert-success {
    background: #D4EDDA;
    border-left: 4px solid #27AE60;
    color: #0A3622;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.wishlist-header {
    background: linear-gradient(135deg, #0D0D0D, #1A1A1A);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    border: 1px solid rgba(200,146,42,0.15);
}
.wishlist-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    color: #C8922A;
    margin-bottom: 5px;
}
.wishlist-header p {
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
}
.wishlist-stats {
    display: flex;
    gap: 15px;
}
.stat-card {
    background: rgba(200,146,42,0.12);
    border-radius: 12px;
    padding: 12px 22px;
    text-align: center;
    border: 1px solid rgba(200,146,42,0.1);
}
.stat-card .number {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: #C8922A;
}
.stat-card .label {
    font-size: 0.65rem;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 1px;
}
.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
}
.wishlist-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #F0EDEA;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.wishlist-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(0,0,0,0.10);
}
.wishlist-card .product-image {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: #F8F8F8;
}
.wishlist-card .product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.wishlist-card:hover .product-image img {
    transform: scale(1.05);
}
.wishlist-card .product-info {
    padding: 16px;
    text-align: center;
}
.wishlist-card .product-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #0D0D0D;
    margin-bottom: 4px;
    font-family: 'Playfair Display', serif;
}
.wishlist-card .product-price {
    font-size: 0.95rem;
    font-weight: 700;
    color: #C8922A;
}
.wishlist-card .actions {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.btn-remove {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    border: none;
    background: #F8D7DA;
    color: #721C24;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-remove:hover {
    background: #E74C3C;
    color: white;
    transform: translateY(-2px);
}
.btn-add-cart {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    border: none;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #1A1A1A;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}
.btn-add-cart:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    color: white;
    transform: translateY(-2px);
}
.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 20px;
    border: 1px solid #F0EDEA;
}
.empty-state i {
    font-size: 4rem;
    color: #E8E0D8;
    display: block;
    margin-bottom: 20px;
}
.empty-state h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    color: #0D0D0D;
    margin-bottom: 10px;
}
.empty-state p {
    color: #8A99AA;
    margin-bottom: 20px;
}
.btn-boutique {
    display: inline-block;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #1A1A1A;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-boutique:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(200,146,42,0.3);
}
.compte-sidebar {
    background: white;
    border-radius: 16px;
    border: 1px solid #F0EDEA;
    overflow: hidden;
    margin-bottom: 30px;
}
.compte-sidebar .menu-item {
    padding: 16px 20px;
    border-bottom: 1px solid #F0EDEA;
    transition: all 0.3s;
}
.compte-sidebar .menu-item:last-child {
    border-bottom: none;
}
.compte-sidebar .menu-item a {
    color: #0D0D0D;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.85rem;
    transition: color 0.3s;
}
.compte-sidebar .menu-item i {
    color: #C8922A;
    width: 24px;
    font-size: 1.1rem;
}
.compte-sidebar .menu-item:hover {
    background: #FEFBF5;
}
.compte-sidebar .menu-item:hover a {
    color: #C8922A;
}
.compte-sidebar .menu-item.active {
    background: #FEFBF5;
    border-left: 3px solid #C8922A;
}
.compte-sidebar .menu-item.logout a {
    color: #E74C3C;
}
.compte-sidebar .menu-item.logout a i {
    color: #E74C3C;
}
.compte-sidebar .menu-item.logout:hover {
    background: #FDE8E8;
}
@media (max-width: 992px) {
    .wishlist-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
    .wishlist-grid { grid-template-columns: 1fr; }
    .wishlist-header { flex-direction: column; text-align: center; gap: 20px; }
    .wishlist-stats { justify-content: center; }
}
</style>

<div class="wishlist-container">
    <?php if($msg == 'supprime'): ?>
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> Produit retiré de votre wishlist.</div>
    <?php endif; ?>

    <div class="wishlist-header">
        <div>
            <h1>❤️ Ma wishlist</h1>
            <p>Retrouvez tous vos produits favoris</p>
        </div>
        <div class="wishlist-stats">
            <div class="stat-card">
                <div class="number"><?= $nb_produits ?></div>
                <div class="label">Produits</div>
            </div>
        </div>
    </div>

    <div class="compte-sidebar">
        <div class="menu-item">
            <a href="mon_compte.php"><i class="bi bi-grid"></i> Tableau de bord</a>
        </div>
        <div class="menu-item">
            <a href="mes_commandes.php"><i class="bi bi-receipt"></i> Mes commandes</a>
        </div>
        <div class="menu-item active">
            <a href="ma_wishlist.php"><i class="bi bi-heart"></i> Ma wishlist</a>
        </div>
        <div class="menu-item">
            <a href="mes_factures.php"><i class="bi bi-file-pdf"></i> Mes factures</a>
        </div>
        <div class="menu-item">
            <a href="mes_points.php"><i class="bi bi-star"></i> Points fidélité</a>
        </div>
        <div class="menu-item">
            <a href="mon_profil.php"><i class="bi bi-person"></i> Mon profil</a>
        </div>
        <div class="menu-item logout">
            <a href="deconnexion.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        </div>
    </div>

    <?php if(empty($wishlist)): ?>
        <div class="empty-state">
            <i class="bi bi-heart"></i>
            <h3>Votre wishlist est vide</h3>
            <p>Ajoutez vos produits préférés en cliquant sur le cœur ❤️</p>
            <a href="../boutique/catalogue.php" class="btn-boutique">
                <i class="bi bi-bag"></i> Découvrir la boutique
            </a>
        </div>
    <?php else: ?>
        <div class="wishlist-grid">
            <?php foreach($wishlist as $item): ?>
            <div class="wishlist-card" id="wishlist-item-<?= $item['produit_id'] ?>">
                <div class="product-image">
                    <img src="<?= getWishlistImage($item['image_principale']) ?>" 
                         alt="<?= htmlspecialchars($item['nom']) ?>"
                         onerror="this.src='https://placehold.co/400x400/F8F8F8/C8922A?text=<?= urlencode($item['nom'])?>'">
                </div>
                <div class="product-info">
                    <div class="product-name"><?= htmlspecialchars($item['nom']) ?></div>
                    <div class="product-price"><?= number_format($item['prix'], 0, ',', ' ') ?> FCFA</div>
                    <div class="actions">
                        <a href="../boutique/produit.php?id=<?= $item['produit_id'] ?>" class="btn-add-cart">
                            <i class="bi bi-eye"></i> Voir
                        </a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="produit_id" value="<?= $item['produit_id'] ?>">
                            <button type="submit" name="supprimer" class="btn-remove" onclick="return confirm('Supprimer ce produit de votre wishlist ?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>