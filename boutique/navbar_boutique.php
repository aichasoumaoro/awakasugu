<?php
// ============================================
// SESSION PUBLIQUE POUR LE PANIER
// ============================================
// Démarrer la session publique si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

$nb_panier = 0;
if (isset($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $item) {
        $nb_panier += $item['quantite'];
    }
}
?>

<style>
.white-nav {
    background: #FFFFFF;
    border-bottom: 1px solid #F0F0F0;
    position: sticky;
    top: 0;
    z-index: 1000;
}
.white-nav-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 40px;
    height: 75px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.white-nav-left {
    display: flex;
    gap: 35px;
}
.white-nav-left a {
    font-family: 'Jost', sans-serif;
    font-size: 0.75rem;
    font-weight: 500;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #1A1A1A;
    text-decoration: none;
    transition: color 0.3s;
}
.white-nav-left a:hover {
    color: #C8922A;
}
.white-logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem;
    font-weight: 500;
    letter-spacing: 4px;
    color: #1A1A1A;
    text-decoration: none;
    transition: color 0.3s;
}
.white-logo:hover {
    color: #C8922A;
}
.white-nav-right {
    display: flex;
    gap: 25px;
    align-items: center;
}
.white-nav-right a {
    color: #1A1A1A;
    font-size: 1.1rem;
    text-decoration: none;
    position: relative;
    transition: color 0.3s;
}
.white-nav-right a:hover {
    color: #C8922A;
}
.cart-count {
    position: absolute;
    top: -8px;
    right: -12px;
    background: #C8922A;
    color: white;
    font-size: 0.6rem;
    font-weight: 600;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: popIn 0.3s ease-out;
}
@keyframes popIn {
    0% { transform: scale(0); }
    70% { transform: scale(1.2); }
    100% { transform: scale(1); }
}
.search-icon {
    background: none;
    border: none;
    font-size: 1.1rem;
    cursor: pointer;
    color: #1A1A1A;
    transition: color 0.3s;
}
.search-icon:hover {
    color: #C8922A;
}
.search-bar {
    max-width: 1400px;
    margin: 0 auto;
    padding: 15px 40px 25px;
    border-top: 1px solid #F0F0F0;
    display: none;
}
.search-bar input {
    width: 100%;
    padding: 12px 18px;
    border: 1.5px solid #E0E0E0;
    border-radius: 8px;
    font-family: 'Jost', sans-serif;
    font-size: 0.95rem;
    outline: none;
    transition: border-color 0.3s;
}
.search-bar input:focus {
    border-color: #C8922A;
}
@media (max-width: 800px) {
    .white-nav-container { padding: 0 20px; height: 60px; }
    .white-nav-left { display: none; }
    .white-logo { font-size: 1rem; }
    .white-nav-right { gap: 18px; }
}
@media (max-width: 480px) {
    .white-nav-right { gap: 14px; }
    .white-nav-right a { font-size: 0.95rem; }
}
</style>

<nav class="white-nav">
    <div class="white-nav-container">
        <div class="white-nav-left">
            <a href="<?= SITE_URL ?>/boutique/catalogue.php">Collection</a>
            <a href="<?= SITE_URL ?>/boutique/nouveautes.php">Nouveautés</a>
            <a href="<?= SITE_URL ?>/boutique/promotions.php">Promotions</a>
        </div>
        <a href="<?= SITE_URL ?>/index.php" class="white-logo">IBA DESIGN</a>
        <div class="white-nav-right">
            <button class="search-icon" onclick="toggleSearch()" aria-label="Rechercher">
                <i class="bi bi-search"></i>
            </button>
            <a href="<?= SITE_URL ?>/boutique/panier.php" aria-label="Panier">
                <i class="bi bi-bag"></i>
                <?php if($nb_panier > 0): ?>
                    <span class="cart-count"><?= $nb_panier ?></span>
                <?php endif; ?>
            </a>
            <?php if(isset($_SESSION['client_id'])): ?>
                <a href="<?= SITE_URL ?>/client/mon_compte.php" aria-label="Mon compte">
                    <i class="bi bi-person-circle"></i>
                </a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/client/connexion.php" aria-label="Connexion">
                    <i class="bi bi-person"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="search-bar" id="searchBar">
        <input type="text" placeholder="Rechercher un produit..." id="searchInput">
    </div>
</nav>

<script>
function toggleSearch() {
    var bar = document.getElementById('searchBar');
    if (bar.style.display === 'none' || bar.style.display === '') {
        bar.style.display = 'block';
        document.getElementById('searchInput')?.focus();
    } else {
        bar.style.display = 'none';
    }
}
document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if(e.key === 'Enter' && this.value.trim().length > 0) {
        window.location.href = '<?= SITE_URL ?>/boutique/catalogue.php?q=' + encodeURIComponent(this.value.trim());
    }
});
// Fermer la recherche en cliquant ailleurs
document.addEventListener('click', function(e) {
    var bar = document.getElementById('searchBar');
    var searchBtn = document.querySelector('.search-icon');
    if (bar && bar.style.display === 'block' && !bar.contains(e.target) && !searchBtn.contains(e.target)) {
        bar.style.display = 'none';
    }
});
</script>