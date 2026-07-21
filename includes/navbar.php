<?php
// ============================================
// SESSION PUBLIQUE POUR LE PANIER
// ============================================
// NE PAS vérifier l'admin ici
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
// ✅ VÉRIFICATION ADMIN DISCRÈTE (sans afficher le bandeau)
// ============================================
$est_admin_connecte = false;
$admin_nom = '';

// Vérifier la session admin sans interférer avec la session publique
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

// Restaurer la session publique
session_write_close();
if (!empty($old_session_name)) {
    session_name($old_session_name);
} else {
    session_name('PUBLIC_SESSION');
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ALERTE : client connecté en parallèle (pour le bandeau admin)
// ============================================
$client_en_parallele = isset($_SESSION['client_id']) ? ($_SESSION['client_nom'] ?? 'un client') : null;
?>

<?php if (!empty($est_admin_connecte)): ?>
<div class="admin-mode-bar">
    <i class="bi bi-shield-lock-fill"></i>
    <span>Mode Administrateur — Vous naviguez en tant qu'<strong><?= htmlspecialchars($admin_nom) ?></strong></span>
    <?php if ($client_en_parallele): ?>
        <span class="admin-mode-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Compte client "<?= htmlspecialchars($client_en_parallele) ?>" toujours connecté sur ce navigateur
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
/* ========== NAVBAR ========== */
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
    padding: 0 40px;
    height: 75px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

/* ===== LOGO ===== */
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
    width: 48px;
    height: 48px;
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
    font-size: 0.95rem;
    font-weight: 800;
    letter-spacing: 3px;
    background: linear-gradient(135deg, #C8922A, #F5D78C, #C8922A);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    text-transform: uppercase;
    line-height: 1.1;
}
.logo-slogan-premium {
    font-family: 'Jost', sans-serif;
    font-size: 0.48rem;
    letter-spacing: 2px;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    margin-top: 3px;
}

/* ===== MENU ===== */
.nav-menu {
    display: flex;
    align-items: center;
    gap: 2px;
    list-style: none;
    margin: 0;
    padding: 0;
    flex: 1;
    justify-content: center;
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
.nav-link:hover {
    color: #C8922A;
    background: rgba(200,146,42,0.08);
}
.nav-link.active {
    color: #C8922A;
    background: rgba(200,146,42,0.12);
}

/* Dropdown */
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
.has-dropdown:hover .nav-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
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

/* ===== ACTIONS ===== */
.nav-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.nav-search {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 30px;
    padding: 0 14px;
    height: 36px;
    transition: all 0.3s;
}
.nav-search:focus-within {
    border-color: #C8922A;
    box-shadow: 0 0 0 3px rgba(200,146,42,0.12);
    background: rgba(200,146,42,0.06);
}
.nav-search i { color: rgba(255,255,255,0.35); font-size: 0.8rem; }
.nav-search:focus-within i { color: #C8922A; }
.nav-search input {
    background: transparent;
    border: none;
    outline: none;
    color: #fff;
    font-size: 0.78rem;
    width: 120px;
    font-family: 'Jost', sans-serif;
}
.nav-search input::placeholder { color: rgba(255,255,255,0.25); }

.nav-panier {
    position: relative;
    display: flex;
    align-items: center;
    gap: 6px;
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    padding: 0 14px;
    height: 36px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 30px;
    font-size: 0.78rem;
    font-weight: 500;
    transition: all 0.3s;
    font-family: 'Jost', sans-serif;
}
.nav-panier:hover {
    color: #C8922A;
    border-color: rgba(200,146,42,0.4);
    background: rgba(200,146,42,0.08);
}
.nav-panier-text { font-size: 0.7rem; }
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
}

/* ===== BOUTON CONNEXION / ADMIN ===== */
.nav-connexion {
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: 'Jost', sans-serif;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    padding: 0 18px;
    height: 36px;
    border-radius: 30px;
    text-decoration: none;
    transition: all 0.3s;
    white-space: nowrap;
}

/* ✅ BOUTON CONNEXION NORMAL */
.nav-connexion.normal {
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #0D0D0D !important;
    box-shadow: 0 4px 14px rgba(200,146,42,0.25);
}
.nav-connexion.normal:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(200,146,42,0.4);
    color: #fff !important;
}
.nav-connexion.normal i { font-size: 0.9rem; }

/* ✅ BOUTON ADMIN (quand connecté) */
.nav-connexion.admin {
    background: rgba(200,146,42,0.12);
    border: 1.5px solid rgba(200,146,42,0.4);
    color: #C8922A !important;
}
.nav-connexion.admin:hover {
    background: rgba(200,146,42,0.2);
    border-color: #C8922A;
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(200,146,42,0.15);
    color: #C8922A !important;
}
.nav-connexion.admin .admin-badge {
    background: #C8922A;
    color: #0D0D0D;
    font-size: 0.5rem;
    font-weight: 700;
    padding: 1px 8px;
    border-radius: 20px;
    margin-left: 2px;
    text-transform: uppercase;
}
.nav-connexion.admin i { 
    font-size: 0.9rem;
    color: #C8922A;
}

.nav-burger {
    display: none;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.7);
    font-size: 1.2rem;
    padding: 6px 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}
.nav-burger:hover { border-color: #C8922A; color: #C8922A; }

/* ===== RESPONSIVE ===== */
@media (max-width: 1100px) {
    .nav-search input { width: 80px; }
    .nav-container { padding: 0 20px; }
    .logo-title-premium { font-size: 0.8rem; }
    .logo-circle { width: 40px; height: 40px; }
}
@media (max-width: 900px) {
    .nav-menu { display: none; }
    .nav-burger { display: block; }
    .nav-container { flex-wrap: wrap; height: auto; padding: 10px 16px; }
    .nav-actions { flex-wrap: wrap; justify-content: flex-end; }
    .nav-menu.open {
        display: flex;
        flex-direction: column;
        width: 100%;
        background: #141414;
        padding: 10px;
        border-radius: 12px;
        margin-top: 8px;
        border: 1px solid rgba(200,146,42,0.2);
        gap: 2px;
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
    .nav-panier-text { display: none; }
    .nav-connexion.admin .admin-badge { display: none; }
}
@media (max-width: 600px) {
    .nav-search { flex: 1; }
    .nav-search input { width: 100%; }
    .nav-connexion span { display: none; }
    .nav-connexion { padding: 0 12px; }
    .logo-title-premium { font-size: 0.7rem; letter-spacing: 1px; }
    .logo-slogan-premium { display: none; }
    .logo-circle { width: 35px; height: 35px; }
    .nav-container { padding: 8px 12px; }
}
</style>

<header class="site-header" id="siteHeader">
    <div class="nav-container">

        <!-- ===== LOGO ===== -->
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

        <!-- ===== MENU ===== -->
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

        <!-- ===== ACTIONS ===== -->
        <div class="nav-actions">
            <form class="nav-search" action="<?= SITE_URL ?>/boutique/catalogue.php" method="GET">
                <i class="bi bi-search"></i>
                <input type="search" name="q" placeholder="Rechercher..." value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
            </form>

            <a href="<?= SITE_URL ?>/boutique/panier.php" class="nav-panier">
                <i class="bi bi-cart3"></i>
                <span class="nav-panier-text">Panier</span>
                <?php if($nb_panier > 0): ?>
                    <span class="nav-badge"><?= $nb_panier ?></span>
                <?php endif; ?>
            </a>

            <?php if($est_admin_connecte): ?>
                <!-- ✅ ADMIN CONNECTÉ : Bouton "Admin" avec badge -->
                <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-connexion admin" title="Accéder à l'administration">
                    <i class="bi bi-person-circle"></i>
                    <span>Admin <span class="admin-badge">⚡</span></span>
                </a>
            <?php elseif(isset($_SESSION['client_id'])): ?>
                <!-- Client connecté -->
                <a href="<?= SITE_URL ?>/client/mon_compte.php" class="nav-connexion normal">
                    <i class="bi bi-person-check"></i>
                    <span>Compte</span>
                </a>
            <?php else: ?>
                <!-- Utilisateur non connecté -->
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

<script>
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
</script>