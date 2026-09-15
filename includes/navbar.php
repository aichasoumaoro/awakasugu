<?php
// ============================================
// SESSION PUBLIQUE POUR LE PANIER
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

$nb_panier = 0;
if (isset($_SESSION['panier']) && is_array($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $item) {
        $nb_panier += $item['quantite'] ?? 0;
    }
}
$page_actuelle = basename($_SERVER['PHP_SELF']);

// ============================================
// VÉRIFICATION ADMIN DISCRÈTE
// ============================================
$est_admin_connecte = false;
$admin_nom = '';

$old_session_name = session_name();
$old_session_id = session_id();
session_write_close();

session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    $est_admin_connecte = true;
    $admin_nom = $_SESSION['admin_nom'] ?? 'Admin';
}

session_write_close();
if (!empty($old_session_name)) {
    session_name($old_session_name);
} else {
    session_name('PUBLIC_SESSION');
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client_en_parallele = isset($_SESSION['client_id']) ? ($_SESSION['client_nom'] ?? 'un client') : null;
?>

<?php if (!empty($est_admin_connecte)): ?>
<div class="admin-mode-bar">
    <i class="bi bi-shield-lock-fill"></i>
    <span>Mode Administrateur — Vous naviguez en tant qu'<strong><?= htmlspecialchars($admin_nom) ?></strong></span>
    <?php if ($client_en_parallele): ?>
        <span class="admin-mode-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Compte client "<?= htmlspecialchars($client_en_parallele) ?>" toujours connecté
        </span>
        <a href="<?= SITE_URL ?>/client/deconnexion.php?retour=admin" class="admin-mode-btn admin-mode-btn-warn">
            <i class="bi bi-box-arrow-right"></i> Déconnecter ce client
        </a>
    <?php endif; ?>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="admin-mode-btn">
        <i class="bi bi-speedometer2"></i> Retour à mon espace
    </a>
</div>
<style>
.admin-mode-bar {
    background: linear-gradient(135deg, #C8922A, #9A6E1A);
    color: #fff;
    font-family: 'Jost', sans-serif;
    font-size: 0.78rem;
    font-weight: 500;
    padding: 9px 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
    text-align: center;
    position: relative;
    z-index: 10000;
}
.admin-mode-bar strong { font-weight: 700; }
.admin-mode-warning {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(0,0,0,0.25);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.72rem;
}
.admin-mode-warning i { color: #FFE0A3; }
.admin-mode-btn-warn {
    background: rgba(231,76,60,0.85) !important;
}
.admin-mode-btn-warn:hover {
    background: rgba(192,57,43,0.95) !important;
}
.admin-mode-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(0,0,0,0.2);
    color: #fff;
    text-decoration: none;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 600;
    transition: all 0.2s;
    white-space: nowrap;
}
.admin-mode-btn:hover { background: rgba(0,0,0,0.35); color: #fff; }
@media (max-width: 600px) {
    .admin-mode-bar { font-size: 0.7rem; padding: 8px 14px; }
}
</style>
<?php endif; ?>

<style>
/* ============================================
   BASE — DESIGN "APP MOBILE" APPLIQUÉ SUR TOUS LES ÉCRANS
   ============================================ */
.site-header {
    position: sticky;
    top: 0;
    z-index: 9999;
    background: #0D0D0D;
    border-bottom: 1px solid rgba(200,146,42,0.3);
    box-shadow: 0 4px 30px rgba(0,0,0,0.4);
}
.nav-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
    height: 75px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.nav-logo {
    flex-shrink: 0;
    text-decoration: none;
    transition: transform 0.3s ease;
}
.nav-logo:hover { transform: translateY(-2px); }
.logo-premium {
    display: flex;
    align-items: center;
    gap: 12px;
}
.logo-circle {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    filter: drop-shadow(0 0 10px rgba(200,146,42,0.4));
    transition: all 0.3s ease;
}
.nav-logo:hover .logo-circle {
    filter: drop-shadow(0 0 18px rgba(200,146,42,0.7));
    transform: scale(1.05);
}
.logo-svg { width: 100%; height: 100%; }
.logo-text-premium {
    display: flex;
    flex-direction: column;
}
.logo-title-premium {
    font-family: 'Playfair Display', serif;
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 2px;
    background: linear-gradient(135deg, #C8922A, #F5D78C, #C8922A);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    text-transform: uppercase;
    line-height: 1.1;
}
.logo-slogan-premium {
    font-family: 'Jost', sans-serif;
    font-size: 0.42rem;
    letter-spacing: 1.5px;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    margin-top: 3px;
}

/* Menu desktop classique — remplacé partout par le burger + barre du bas */
.nav-menu {
    display: none;
    align-items: center;
    gap: 2px;
    list-style: none;
    margin: 0;
    padding: 0;
    flex: 1;
    justify-content: center;
}
.nav-menu.open {
    display: flex;
    flex-direction: column;
    position: absolute;
    top: 75px;
    left: 12px;
    right: 12px;
    width: auto;
    background: #141414;
    padding: 10px;
    border-radius: 12px;
    border: 1px solid rgba(200,146,42,0.2);
    box-shadow: 0 20px 50px rgba(0,0,0,0.6);
    gap: 2px;
    z-index: 10002;
}
.nav-menu.open .nav-link {
    width: 100%;
    justify-content: flex-start;
    padding: 10px 14px;
    border-radius: 8px;
}
.nav-menu.open .nav-dropdown {
    position: static;
    opacity: 1;
    visibility: visible;
    transform: none;
    background: transparent;
    padding-left: 20px;
    box-shadow: none;
    border: none;
}
.nav-link {
    font-family: 'Jost', sans-serif;
    font-size: 0.78rem;
    font-weight: 500;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.6);
    padding: 6px 14px;
    text-decoration: none;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 6px;
    border-radius: 30px;
    position: relative;
}
/* ===== ICÔNES DU MENU EN OR ===== */
.nav-link i {
    color: #C8922A;
    transition: color 0.3s, transform 0.3s;
}
.nav-link:hover {
    color: #C8922A;
    background: rgba(200,146,42,0.08);
}
.nav-link:hover i {
    color: #E8C870;
    transform: scale(1.1);
}
.nav-link.active {
    color: #C8922A;
    background: rgba(200,146,42,0.12);
}
.nav-link.active i {
    color: #E8C870;
}
.has-dropdown { position: relative; }
.nav-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    background: #0D0D0D;
    border: 1px solid rgba(200,146,42,0.3);
    border-radius: 12px;
    padding: 6px;
    min-width: 200px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all 0.3s;
    z-index: 1000;
    box-shadow: 0 20px 50px rgba(0,0,0,0.6);
}
.nav-dropdown a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    color: rgba(255,255,255,0.6);
    font-size: 0.78rem;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s;
    font-family: 'Jost', sans-serif;
}
.nav-dropdown a:hover {
    background: rgba(200,146,42,0.12);
    color: #C8922A;
}
.nav-dropdown a i { color: #C8922A; width: 18px; }

.nav-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

/* ===== ICÔNES RONDES (recherche / panier / messagerie) — EN OR ===== */
.mobile-only-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: rgba(200,146,42,0.08);
    border: 1px solid rgba(200,146,42,0.25);
    color: #C8922A;
    text-decoration: none;
    font-size: 1.05rem;
    transition: all 0.3s;
    flex-shrink: 0;
    cursor: pointer;
}
.mobile-only-icon i {
    color: #C8922A;
    transition: color 0.3s, transform 0.3s;
}
.mobile-only-icon:hover {
    color: #E8C870;
    border-color: rgba(200,146,42,0.6);
    background: rgba(200,146,42,0.15);
}
.mobile-only-icon:hover i {
    color: #E8C870;
    transform: scale(1.1);
}
.mobile-only-icon .nav-badge { top: -4px; right: -4px; }

/* ===== RECHERCHE — overlay plein écran ===== */
.nav-search {
    position: fixed;
    top: 0; left: 0; right: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    height: 60px;
    background: #0D0D0D;
    border: none;
    border-bottom: 1px solid rgba(200,146,42,0.3);
    border-radius: 0;
    padding: 0 16px;
    z-index: 10001;
    transform: translateY(-100%);
    opacity: 0;
    pointer-events: none;
    transition: all 0.3s ease;
}
.nav-search.active {
    transform: translateY(0);
    opacity: 1;
    pointer-events: auto;
}
.nav-search > i {
    color: #C8922A;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.nav-search input {
    background: transparent;
    border: none;
    outline: none;
    color: #fff;
    font-size: 0.85rem;
    width: 100%;
    font-family: 'Jost', sans-serif;
}
.nav-search input::placeholder { color: rgba(255,255,255,0.25); }
.nav-search-close {
    display: block;
    background: none;
    border: none;
    color: #C8922A;
    font-size: 1.3rem;
    cursor: pointer;
    flex-shrink: 0;
    transition: color 0.2s, transform 0.2s;
}
.nav-search-close:hover {
    color: #E8C870;
    transform: rotate(90deg);
}

.search-results {
    position: absolute;
    top: 60px;
    left: 0; right: 0;
    width: 100%;
    max-height: calc(100vh - 60px);
    overflow-y: auto;
    background: #141414;
    border: none;
    border-radius: 0 0 12px 12px;
    padding: 10px;
    display: none;
    z-index: 10000;
    box-shadow: 0 20px 50px rgba(0,0,0,0.8);
}
.search-results.active { display: block; }
.search-category {
    font-family: 'Jost', sans-serif;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #C8922A;
    padding: 8px 10px 4px;
    border-bottom: 1px solid rgba(200,146,42,0.2);
    margin-bottom: 4px;
}
.search-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s;
}
.search-item:hover { background: rgba(200,146,42,0.1); }
.search-item img {
    width: 45px; height: 45px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
}
.search-icon {
    width: 40px; height: 40px;
    border-radius: 8px;
    background: rgba(200,146,42,0.15);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.search-icon i { color: #C8922A; font-size: 1.2rem; }
.search-item-name {
    font-family: 'Jost', sans-serif;
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
    margin-bottom: 2px;
}
.search-item-price {
    font-family: 'Jost', sans-serif;
    font-size: 0.75rem;
    color: #C8922A;
}
.search-empty, .search-error {
    text-align: center;
    padding: 20px;
    color: rgba(255,255,255,0.5);
    font-family: 'Jost', sans-serif;
    font-size: 0.85rem;
}
.search-loading {
    text-align: center;
    padding: 20px;
    color: rgba(255,255,255,0.3);
    font-size: 0.85rem;
}

/* ===== PANIER — icône ronde EN OR ===== */
.nav-panier {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    padding: 0;
    border-radius: 50%;
    color: #C8922A;
    text-decoration: none;
    border: 1px solid rgba(200,146,42,0.25);
    background: rgba(200,146,42,0.08);
    transition: all 0.3s;
    flex-shrink: 0;
}
.nav-panier i {
    color: #C8922A;
    transition: color 0.3s, transform 0.3s;
}
.nav-panier:hover {
    color: #E8C870;
    border-color: rgba(200,146,42,0.6);
    background: rgba(200,146,42,0.15);
}
.nav-panier:hover i {
    color: #E8C870;
    transform: scale(1.1);
}
.nav-panier-text { display: none; }
.nav-badge {
    position: absolute;
    top: -5px; right: -5px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #0D0D0D;
    font-size: 0.55rem;
    font-weight: 800;
    width: 16px; height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #0D0D0D;
    transition: transform 0.2s ease;
}

/* Compte / connexion desktop classique : géré par la barre du bas */
.nav-connexion { display: none !important; }

/* Burger — EN OR */
.nav-burger {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    background: rgba(200,146,42,0.08);
    border: 1px solid rgba(200,146,42,0.25);
    color: #C8922A;
    font-size: 1.15rem;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
}
.nav-burger i {
    color: #C8922A;
    transition: color 0.3s, transform 0.3s;
}
.nav-burger:hover {
    border-color: #C8922A;
    color: #E8C870;
    background: rgba(200,146,42,0.15);
}
.nav-burger:hover i {
    color: #E8C870;
    transform: rotate(90deg);
}

/* ===== BARRE DE NAVIGATION DU BAS — icônes EN OR ===== */
body { padding-bottom: 68px; }

.bottom-nav {
    display: flex;
    position: fixed;
    bottom: 0; left: 0; right: 0;
    z-index: 9999;
    justify-content: center;
    align-items: center;
    background: #0D0D0D;
    border-top: 1px solid rgba(200,146,42,0.25);
    box-shadow: 0 -4px 25px rgba(0,0,0,0.35);
    padding: 6px 4px calc(6px + env(safe-area-inset-bottom));
}
.bottom-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    text-decoration: none;
    color: rgba(200,146,42,0.55);
    font-family: 'Jost', sans-serif;
    font-size: 0.62rem;
    font-weight: 500;
    flex: 0 1 110px;
    padding: 4px 8px;
    position: relative;
    transition: color 0.3s;
}
.bottom-nav-item i {
    font-size: 1.25rem;
    color: rgba(200,146,42,0.55);
    transition: color 0.3s, transform 0.3s;
}
.bottom-nav-item:hover {
    color: #C8922A;
}
.bottom-nav-item:hover i {
    color: #C8922A;
    transform: translateY(-2px);
}
.bottom-nav-item.active {
    color: #E8C870;
}
.bottom-nav-item.active i {
    color: #E8C870;
    transform: translateY(-2px);
    filter: drop-shadow(0 0 8px rgba(200,146,42,0.6));
}
.bottom-nav-item .bn-badge {
    position: absolute;
    top: -2px; right: 22%;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #0D0D0D;
    font-size: 0.5rem;
    font-weight: 800;
    min-width: 15px; height: 15px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid #0D0D0D;
}

/* Header transparent au-dessus d'une bannière */
body.has-hero .site-header {
    position: fixed;
    top: 0; left: 0; right: 0;
    background: transparent;
    border-bottom-color: transparent;
    box-shadow: none;
    transition: background 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}
body.has-hero .site-header.scrolled {
    background: #0D0D0D;
    border-bottom-color: rgba(200,146,42,0.3);
    box-shadow: 0 4px 30px rgba(0,0,0,0.4);
}

@media (max-width: 600px) {
    .logo-title-premium { font-size: 0.68rem; letter-spacing: 1px; }
    .logo-slogan-premium { display: none; }
    .logo-circle { width: 34px; height: 34px; }
    .nav-container { padding: 0 12px; gap: 8px; }
    .bottom-nav-item span { font-size: 0.58rem; }
}
</style>

<header class="site-header" id="siteHeader">
    <div class="nav-container">

        <a href="<?= SITE_URL ?>" class="nav-logo">
            <div class="logo-premium">
                <div class="logo-circle">
                    <svg viewBox="0 0 100 100" class="logo-svg" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%"   stop-color="#C8922A"/>
                                <stop offset="50%"  stop-color="#F5D78C"/>
                                <stop offset="100%" stop-color="#9A6E1A"/>
                            </linearGradient>
                        </defs>
                        <circle cx="50" cy="50" r="49" fill="#0D0D0D"/>
                        <circle cx="50" cy="50" r="47" fill="none" stroke="url(#g1)" stroke-width="1.8"/>
                        <circle cx="50" cy="50" r="40" fill="none" stroke="url(#g1)" stroke-width="0.6" opacity="0.5"/>
                        <text x="22" y="60" font-family="Georgia, 'Times New Roman', serif" font-size="34" font-weight="700" fill="url(#g1)">I</text>
                        <text x="40" y="60" font-family="Georgia, 'Times New Roman', serif" font-size="34" font-weight="700" fill="url(#g1)">D</text>
                        <g opacity="0.85">
                            <line x1="8"  y1="68" x2="22" y2="58" stroke="url(#g1)" stroke-width="0.9"/>
                            <line x1="10" y1="72" x2="13" y2="66" stroke="url(#g1)" stroke-width="0.8"/>
                            <line x1="13" y1="74" x2="16" y2="68" stroke="url(#g1)" stroke-width="0.8"/>
                            <line x1="16" y1="75" x2="19" y2="69" stroke="url(#g1)" stroke-width="0.8"/>
                            <line x1="19" y1="74" x2="21" y2="69" stroke="url(#g1)" stroke-width="0.8"/>
                        </g>
                        <g opacity="0.85">
                            <line x1="92" y1="68" x2="78" y2="58" stroke="url(#g1)" stroke-width="0.9"/>
                            <line x1="90" y1="72" x2="87" y2="66" stroke="url(#g1)" stroke-width="0.8"/>
                            <line x1="87" y1="74" x2="84" y2="66" stroke="url(#g1)" stroke-width="0.8"/>
                            <line x1="84" y1="75" x2="81" y2="69" stroke="url(#g1)" stroke-width="0.8"/>
                            <line x1="81" y1="74" x2="79" y2="69" stroke="url(#g1)" stroke-width="0.8"/>
                        </g>
                        <circle cx="50" cy="78" r="1.5" fill="url(#g1)"/>
                        <line x1="30" y1="78" x2="46" y2="78" stroke="url(#g1)" stroke-width="0.6" opacity="0.5"/>
                        <line x1="54" y1="78" x2="70" y2="78" stroke="url(#g1)" stroke-width="0.6" opacity="0.5"/>
                    </svg>
                </div>
                <div class="logo-text-premium">
                    <span class="logo-title-premium">AWA KA SUGU</span>
                    <span class="logo-slogan-premium">✦ ÉLÉGANCE & SAVOUREUSE ✦</span>
                </div>
            </div>
        </a>

        <ul class="nav-menu" id="navMenu">
            <li>
                <a href="<?= SITE_URL ?>" class="nav-link <?= $page_actuelle == 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-house-door"></i> Accueil
                </a>
            </li>
            <li class="has-dropdown">
                <a href="<?= SITE_URL ?>/boutique/catalogue.php" class="nav-link">
                    <i class="bi bi-bag"></i> Boutique
                    <i class="bi bi-chevron-down" style="font-size:0.5rem;"></i>
                </a>
                <div class="nav-dropdown">
                    <a href="<?= SITE_URL ?>/boutique/catalogue.php"><i class="bi bi-grid-3x3-gap"></i> Tous les produits</a>
                    <a href="<?= SITE_URL ?>/boutique/nouveautes.php"><i class="bi bi-stars"></i> Nouveautés</a>
                    <a href="<?= SITE_URL ?>/boutique/promotions.php"><i class="bi bi-percent"></i> Promotions</a>
                </div>
            </li>
            <li>
                <a href="<?= SITE_URL ?>/restaurant/menu.php" class="nav-link">
                    <i class="bi bi-cup-hot"></i> Restaurant Sofia
                </a>
            </li>
            <li>
                <a href="<?= SITE_URL ?>/boutique/videos.php" class="nav-link">
                    <i class="bi bi-camera-reels"></i> Vidéos
                </a>
            </li>
            <li>
                <a href="<?= SITE_URL ?>/boutique/suivi.php" class="nav-link">
                    <i class="bi bi-truck"></i> Suivi
                </a>
            </li>
        </ul>

        <div class="nav-actions">

            <button type="button" class="mobile-only-icon" id="mobileSearchToggle" title="Rechercher">
                <i class="bi bi-search"></i>
            </button>

            <div class="nav-search" id="globalSearch">
                <i class="bi bi-search"></i>
                <input type="search" id="searchInput" placeholder="Rechercher..." autocomplete="off">
                <button type="button" class="nav-search-close" id="mobileSearchClose"><i class="bi bi-x-lg"></i></button>
                <div class="search-results" id="searchResults"></div>
            </div>

            <a href="<?= SITE_URL ?>/boutique/panier.php" class="nav-panier">
                <i class="bi bi-cart3"></i>
                <span class="nav-badge" id="navCartBadge" style="<?= $nb_panier > 0 ? '' : 'display:none;' ?>"><?= $nb_panier ?></span>
            </a>

            <a href="<?= SITE_URL ?>/client/messagerie.php" class="mobile-only-icon" title="Messagerie">
                <i class="bi bi-chat-dots"></i>
            </a>

            <?php if($est_admin_connecte): ?>
                <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-connexion admin" title="Accéder à l'administration">
                    <i class="bi bi-person-circle"></i>
                    <span>Admin <span class="admin-badge">⚡</span></span>
                </a>
            <?php elseif(isset($_SESSION['client_id'])): ?>
                <a href="<?= SITE_URL ?>/client/mon_compte.php" class="nav-connexion normal">
                    <i class="bi bi-person-check"></i>
                    <span>Compte</span>
                </a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/client/connexion.php" class="nav-connexion normal">
                    <i class="bi bi-person"></i>
                    <span>Connexion</span>
                </a>
            <?php endif; ?>

            <button class="nav-burger" id="burgerBtn">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </div>
</header>

<!-- ===== BARRE DE NAVIGATION DU BAS ===== -->
<nav class="bottom-nav" id="bottomNav">
    <a href="<?= SITE_URL ?>" class="bottom-nav-item <?= $page_actuelle == 'index.php' ? 'active' : '' ?>">
        <i class="bi bi-house-door<?= $page_actuelle == 'index.php' ? '-fill' : '' ?>"></i>
        <span>Accueil</span>
    </a>
    <a href="<?= SITE_URL ?>/boutique/catalogue.php" class="bottom-nav-item <?= $page_actuelle == 'catalogue.php' ? 'active' : '' ?>">
        <i class="bi bi-grid<?= $page_actuelle == 'catalogue.php' ? '-fill' : '' ?>"></i>
        <span>Catégories</span>
    </a>
    <a href="<?= SITE_URL ?>/boutique/suivi.php" class="bottom-nav-item <?= $page_actuelle == 'suivi.php' ? 'active' : '' ?>">
        <i class="bi bi-truck"></i>
        <span>Suivi</span>
    </a>
    <?php if($est_admin_connecte): ?>
        <a href="<?= SITE_URL ?>/admin/dashboard.php" class="bottom-nav-item <?= $page_actuelle == 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i>
            <span>Compte</span>
        </a>
    <?php elseif(isset($_SESSION['client_id'])): ?>
        <a href="<?= SITE_URL ?>/client/mon_compte.php" class="bottom-nav-item <?= $page_actuelle == 'mon_compte.php' ? 'active' : '' ?>">
            <i class="bi bi-person-check"></i>
            <span>Compte</span>
        </a>
    <?php else: ?>
        <a href="<?= SITE_URL ?>/client/connexion.php" class="bottom-nav-item <?= $page_actuelle == 'connexion.php' ? 'active' : '' ?>">
            <i class="bi bi-person"></i>
            <span>Compte</span>
        </a>
    <?php endif; ?>
    <a href="<?= SITE_URL ?>/client/messagerie.php" class="bottom-nav-item <?= $page_actuelle == 'messagerie.php' ? 'active' : '' ?>">
        <i class="bi bi-chat-dots"></i>
        <span>Messages</span>
    </a>
</nav>

<script>
// ============================================
// NAVBAR BURGER
// ============================================
const burgerBtn = document.getElementById('burgerBtn');
const navMenu   = document.getElementById('navMenu');

burgerBtn?.addEventListener('click', () => {
    navMenu.classList.toggle('open');
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.site-header') && navMenu?.classList.contains('open')) {
        navMenu.classList.remove('open');
    }
});

// ============================================
// RECHERCHE GLOBALE AJAX - UNIVERSEL & RAPIDE
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchInput || !searchResults) return;
    
    let debounceTimer = null;
    
    searchResults.style.display = 'none';
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        
        if (query.length < 1) {
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            return;
        }
        
        searchResults.style.display = 'block';
        searchResults.innerHTML = '<div class="search-loading">Recherche...</div>';
        
        debounceTimer = setTimeout(() => {
            const baseUrl = window.location.origin + '/awakasugu';
            const url = baseUrl + '/recherche.php?q=' + encodeURIComponent(query);
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    afficherResultats(data);
                })
                .catch(error => {
                    console.error('Erreur recherche:', error);
                    searchResults.innerHTML = '<div class="search-error">Erreur de recherche</div>';
                });
        }, 150);
    });
    
    function afficherResultats(data) {
        if (data.erreur) {
            searchResults.innerHTML = '<div class="search-error">Erreur de recherche</div>';
            return;
        }
        
        const produits = data.produits || [];
        const plats = data.plats || [];
        const pages = data.pages || [];
        const categories = data.categories || [];
        
        const total = produits.length + plats.length + pages.length + categories.length;
        
        if (total === 0) {
            searchResults.innerHTML = '<div class="search-empty">Aucun résultat trouvé</div>';
            return;
        }
        
        let html = '';
        
        if (pages.length > 0) {
            html += '<div class="search-category">📄 Pages</div>';
            pages.forEach(page => {
                html += `
                    <a href="/awakasugu/${page.url}" class="search-item">
                        <div class="search-icon"><i class="bi ${page.icone}"></i></div>
                        <div>
                            <div class="search-item-name">${page.titre}</div>
                            <div class="search-item-price">Cliquez pour ouvrir</div>
                        </div>
                    </a>
                `;
            });
        }
        
        if (categories.length > 0) {
            html += '<div class="search-category">📂 Catégories</div>';
            categories.forEach(cat => {
                html += `
                    <a href="/awakasugu/boutique/catalogue.php?categorie=${cat.id}" class="search-item">
                        <div>
                            <div class="search-item-name">${cat.nom}</div>
                            <div class="search-item-price">Catégorie</div>
                        </div>
                    </a>
                `;
            });
        }
        
        if (produits.length > 0) {
            html += '<div class="search-category">🛍️ Produits</div>';
            produits.forEach(p => {
                const img = p.image_principale ? '/awakasugu/' + p.image_principale : 'https://placehold.co/50x50/C8922A/FFF?text=P';
                const prix = p.prix_promo && p.prix_promo > 0 && p.prix_promo < p.prix 
                    ? p.prix_promo 
                    : p.prix;
                
                html += `
                    <a href="/awakasugu/boutique/produit.php?id=${p.id}" class="search-item">
                        <img src="${img}" alt="${p.nom}" onerror="this.src='https://placehold.co/50x50/F0F0F0/999?text=P'">
                        <div>
                            <div class="search-item-name">${p.nom}</div>
                            <div class="search-item-price">${Number(prix).toLocaleString('fr-FR')} FCFA</div>
                        </div>
                    </a>
                `;
            });
        }
        
        if (plats.length > 0) {
            html += '<div class="search-category">🍽️ Restaurant Sofia</div>';
            plats.forEach(plat => {
                html += `
                    <a href="/awakasugu/restaurant/menu.php#plat-${plat.id}" class="search-item">
                        <div>
                            <div class="search-item-name">${plat.nom}</div>
                            <div class="search-item-price">${Number(plat.prix).toLocaleString('fr-FR')} FCFA</div>
                        </div>
                    </a>
                `;
            });
        }
        
        searchResults.innerHTML = html;
        searchResults.style.display = 'block';
    }
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#globalSearch')) {
            searchResults.style.display = 'none';
        }
    });
    
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchResults.style.display = 'none';
            searchInput.blur();
        }
    });
});

// ============================================
// RECHERCHE EN OVERLAY (toujours actif)
// ============================================
const mobileSearchToggle = document.getElementById('mobileSearchToggle');
const mobileSearchClose  = document.getElementById('mobileSearchClose');
const navSearchBox       = document.getElementById('globalSearch');

mobileSearchToggle?.addEventListener('click', () => {
    navSearchBox?.classList.add('active');
    document.getElementById('searchInput')?.focus();
});
mobileSearchClose?.addEventListener('click', () => {
    navSearchBox?.classList.remove('active');
    document.getElementById('searchResults')?.classList.remove('active');
    document.getElementById('searchResults').style.display = 'none';
});

// ============================================
// HEADER TRANSPARENT AU-DESSUS D'UNE BANNIÈRE
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.banner-vitrine')) {
        document.body.classList.add('has-hero');
    }
    const siteHeader = document.getElementById('siteHeader');
    function updateHeaderOnScroll() {
        if (!siteHeader) return;
        const banniere = document.querySelector('.banner-vitrine');
        const seuil = banniere ? Math.max(banniere.offsetHeight - 90, 60) : 60;
        if (window.scrollY > seuil) {
            siteHeader.classList.add('scrolled');
        } else {
            siteHeader.classList.remove('scrolled');
        }
    }
    window.addEventListener('scroll', updateHeaderOnScroll);
    updateHeaderOnScroll();
});
</script>