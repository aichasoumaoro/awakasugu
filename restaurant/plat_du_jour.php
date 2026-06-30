<?php
// ============================================
// PLAT DU JOUR - Restaurant Sofia
// ============================================

$titre_page = 'Plat du jour - Restaurant Sofia';
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

$plat_jour = $pdo->query("SELECT * FROM plats WHERE est_plat_du_jour = 1 AND est_visible = 1 LIMIT 1")->fetch();

if (!$plat_jour) {
    header('Location: menu.php');
    exit;
}

// Autres plats recommandés
$autres_plats = $pdo->query("SELECT * FROM plats WHERE id != {$plat_jour['id']} AND est_visible = 1 LIMIT 4")->fetchAll();
?>

<style>
.plat-hero {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #2A1A0A 100%);
    padding: 40px 0 30px;
    text-align: center;
}
.plat-hero h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    color: #C8922A;
}
.plat-container {
    max-width: 1000px;
    margin: -20px auto 60px;
    padding: 0 20px;
}
.plat-card {
    background: white;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 15px 40px rgba(0,0,0,0.1);
}
.plat-image {
    height: 350px;
    overflow: hidden;
    background: #F5F3F0;
}
.plat-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.plat-info {
    padding: 35px;
}
.plat-badge {
    display: inline-block;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    padding: 5px 16px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.plat-info h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    margin: 10px 0;
}
.plat-prix {
    font-size: 2rem;
    font-weight: 700;
    color: #C8922A;
}
.plat-description {
    color: #666;
    line-height: 1.8;
    margin: 15px 0 25px;
}
.btn-commander-plat {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    padding: 14px 35px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s;
}
.btn-commander-plat:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(200,146,42,0.4);
    color: white;
}
.autres-plats {
    margin-top: 50px;
}
.autres-plats h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    margin-bottom: 20px;
}
.autres-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
.autre-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    text-align: center;
    padding: 15px;
    text-decoration: none;
    color: inherit;
    border: 1px solid #F0EBE3;
    transition: all 0.3s;
}
.autre-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    border-color: #C8922A;
}
.autre-card img {
    width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 8px;
}
.autre-card h4 {
    font-size: 0.9rem;
    margin: 10px 0 5px;
}
.autre-card .prix {
    color: #C8922A;
    font-weight: 700;
}
@media (max-width: 768px) {
    .autres-grid { grid-template-columns: repeat(2, 1fr); }
    .plat-image { height: 220px; }
    .plat-info { padding: 25px 20px; }
}
@media (max-width: 500px) {
    .autres-grid { grid-template-columns: 1fr; }
}
</style>

<div class="plat-hero">
    <div class="container">
        <h1>⭐ Plat du jour</h1>
    </div>
</div>

<div class="plat-container">
    <div class="plat-card">
        <div class="plat-image">
            <?php 
            $img = !empty($plat_jour['image']) ? '../uploads/plats/' . $plat_jour['image'] : 'https://placehold.co/800x350/F5F3F0/C8922A?text=' . urlencode($plat_jour['nom']);
            ?>
            <img src="<?= $img ?>" alt="<?= htmlspecialchars($plat_jour['nom']) ?>">
        </div>
        <div class="plat-info">
            <span class="plat-badge">⭐ Plat du jour</span>
            <h1><?= htmlspecialchars($plat_jour['nom']) ?></h1>
            <div class="plat-prix"><?= number_format($plat_jour['prix'], 0, ',', ' ') ?> FCFA</div>
            <div class="plat-description"><?= nl2br(htmlspecialchars($plat_jour['description'])) ?></div>
            <a href="commande_repas.php?plat_id=<?= $plat_jour['id'] ?>" class="btn-commander-plat">
                <i class="bi bi-cart-plus"></i> Commander
            </a>
        </div>
    </div>

    <?php if(!empty($autres_plats)): ?>
    <div class="autres-plats">
        <h3>🍽️ Autres plats</h3>
        <div class="autres-grid">
            <?php foreach($autres_plats as $p): ?>
            <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="autre-card">
                <?php 
                $img = !empty($p['image']) ? '../uploads/plats/' . $p['image'] : 'https://placehold.co/150x120/F5F3F0/C8922A?text=' . urlencode($p['nom']);
                ?>
                <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['nom']) ?>">
                <h4><?= htmlspecialchars($p['nom']) ?></h4>
                <div class="prix"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>