<?php
// ============================================
// SIDEBAR ADMIN - AWA KA SUGU
// ============================================
$current_page = basename($_SERVER['PHP_SELF']);

// ============================================
// RECUPERER LE ROLE ET LES PERMISSIONS
// ============================================
$user_role = $_SESSION['admin_role'] ?? null;
$user_permissions = [];

if (isset($_SESSION['admin_id'])) {
    try {
        $host = 'localhost';
        $dbname = 'awakasugu_db';
        $user = 'root';
        $pass = '';
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT permissions, role FROM admin WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin_data = $stmt->fetch();
        
        if ($admin_data) {
            $user_role = $admin_data['role'] ?? null;
            if (!empty($admin_data['permissions'])) {
                $user_permissions = json_decode($admin_data['permissions'], true);
            }
        }
    } catch(PDOException $e) {
        $user_permissions = [];
    }
}

// ============================================
// FONCTION POUR VERIFIER L'ACCES
// ============================================
function a_acces($permission, $user_role, $user_permissions) {
    if ($user_role === 'super_admin') return true;
    
    if ($user_role === 'directeur') {
        return $permission !== 'parametres';
    }
    
    if ($user_role === 'admin' || $user_role === 'admin2') {
        if ($permission === 'dashboard') return true;
        return in_array($permission, $user_permissions);
    }
    
    if ($user_role === null || $user_role === '') {
        return in_array($permission, $user_permissions);
    }
    
    return false;
}

// ============================================
// LABELS DES RÔLES
// ============================================
$role_labels = [
    'super_admin' => 'Super Administrateur',
    'directeur' => 'Directeur',
    'admin' => 'Administrateur',
    'admin2' => 'Agent / Vendeur'
];

$role_colors = [
    'super_admin' => '#C8922A',
    'directeur' => '#C8922A',
    'admin' => '#2980B9',
    'admin2' => '#7F8C8D'
];

if ($user_role && isset($role_labels[$user_role])) {
    $role_label_header = $role_labels[$user_role];
    $role_color_header = $role_colors[$user_role];
} else {
    $role_label_header = 'Collaborateur';
    $role_color_header = '#7F8C8D';
}

$admin_nom = $_SESSION['admin_nom'] ?? 'Utilisateur';
$admin_initial = strtoupper(substr($admin_nom, 0, 1));
?>

<!-- ============================================
     SIDEBAR ADMIN
     ============================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- ===== BRAND + USER ===== -->
    <div class="sidebar-brand">
        <div class="brand-logo">AWA KA SUGU</div>
        <div class="brand-sub">Administration</div>
        
        <div class="admin-user">
            <div class="admin-avatar" style="background: linear-gradient(135deg, <?= $role_color_header ?>, <?= $role_color_header ?>cc);">
                <?= $admin_initial ?>
            </div>
            <div class="admin-info">
                <div class="admin-name"><?= htmlspecialchars($admin_nom) ?></div>
                <div class="admin-role" style="color: <?= $role_color_header ?>;">
                    <span class="role-dot" style="background: <?= $role_color_header ?>; box-shadow: 0 0 8px <?= $role_color_header ?>;"></span>
                    <?= $role_label_header ?>
                </div>
            </div>
        </div>
        
        <!-- ===== TOGGLE THÈME ===== -->
        <button type="button" class="theme-toggle" id="themeToggle" aria-label="Changer de thème">
            <i class="bi bi-sun-fill icon-sun"></i>
            <span class="theme-track">
                <span class="theme-thumb"></span>
            </span>
            <i class="bi bi-moon-stars-fill icon-moon"></i>
        </button>
    </div>
    
    <!-- ===== NAVIGATION ===== -->
    <nav class="sidebar-nav">
        
        <div class="nav-section">Principal</div>
        
        <?php if(a_acces('dashboard', $user_role, $user_permissions)): ?>
        <a href="dashboard.php" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Tableau de bord</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('point_de_vente', $user_role, $user_permissions)): ?>
        <a href="point_de_vente.php" class="nav-item <?= $current_page == 'point_de_vente.php' ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i>
            <span>Point de vente</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('produits', $user_role, $user_permissions)): ?>
        <a href="produits.php" class="nav-item <?= $current_page == 'produits.php' ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i>
            <span>Produits</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('commandes', $user_role, $user_permissions)): ?>
        <a href="commandes.php" class="nav-item <?= $current_page == 'commandes.php' ? 'active' : '' ?>">
            <i class="bi bi-bag-check"></i>
            <span>Commandes</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('clients', $user_role, $user_permissions)): ?>
        <a href="clients.php" class="nav-item <?= $current_page == 'clients.php' ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            <span>Clients</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('messagerie', $user_role, $user_permissions)): ?>
        <a href="messagerie.php" class="nav-item <?= $current_page == 'messagerie.php' ? 'active' : '' ?>">
            <i class="bi bi-chat-dots"></i>
            <span>Messagerie</span>
            <?php
            try {
                $stmt = $pdo->query("
                    SELECT COUNT(*) FROM messages m 
                    JOIN conversations c ON m.conversation_id = c.id
                    WHERE m.lu = 0 AND m.expediteur_type = 'client'
                ");
                $nb = $stmt->fetchColumn();
                if ($nb > 0) echo '<span class="badge-red">' . $nb . '</span>';
            } catch(Exception $e) {}
            ?>
        </a>
        <?php endif; ?>
        
        <!-- ===== RESTAURANT ===== -->
        <?php if(a_acces('plats', $user_role, $user_permissions) || a_acces('commandes_repas', $user_role, $user_permissions) || a_acces('reservations', $user_role, $user_permissions)): ?>
        <div class="nav-section">Restaurant</div>
        <?php endif; ?>
        
        <?php if(a_acces('plats', $user_role, $user_permissions)): ?>
        <a href="plats.php" class="nav-item <?= $current_page == 'plats.php' ? 'active' : '' ?>">
            <i class="bi bi-cup-hot"></i>
            <span>Plats</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('commandes_repas', $user_role, $user_permissions)): ?>
        <a href="commandes_repas.php" class="nav-item <?= $current_page == 'commandes_repas.php' ? 'active' : '' ?>">
            <i class="bi bi-basket"></i>
            <span>Commandes repas</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('reservations', $user_role, $user_permissions)): ?>
        <a href="reservations.php" class="nav-item <?= $current_page == 'reservations.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar-check"></i>
            <span>Réservations</span>
        </a>
        <?php endif; ?>
        
        <!-- ===== GESTION ===== -->
        <?php if(a_acces('achats', $user_role, $user_permissions) || a_acces('stocks', $user_role, $user_permissions) || a_acces('promotions', $user_role, $user_permissions) || a_acces('videos', $user_role, $user_permissions) || a_acces('factures', $user_role, $user_permissions) || a_acces('paiements', $user_role, $user_permissions) || a_acces('codes_promo', $user_role, $user_permissions) || a_acces('avis_admin', $user_role, $user_permissions)): ?>
        <div class="nav-section">Gestion</div>
        <?php endif; ?>
        
        <?php if(a_acces('achats', $user_role, $user_permissions)): ?>
        <a href="achats.php" class="nav-item <?= $current_page == 'achats.php' ? 'active' : '' ?>">
            <i class="bi bi-cart-check"></i>
            <span>Achats</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('stocks', $user_role, $user_permissions)): ?>
        <a href="stocks.php" class="nav-item <?= $current_page == 'stocks.php' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart"></i>
            <span>Stocks</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('promotions', $user_role, $user_permissions)): ?>
        <a href="promotions.php" class="nav-item <?= $current_page == 'promotions.php' ? 'active' : '' ?>">
            <i class="bi bi-percent"></i>
            <span>Promotions</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('videos', $user_role, $user_permissions)): ?>
        <a href="videos.php" class="nav-item <?= $current_page == 'videos.php' ? 'active' : '' ?>">
            <i class="bi bi-camera-reels"></i>
            <span>Vidéos</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('factures', $user_role, $user_permissions)): ?>
        <a href="factures.php" class="nav-item <?= $current_page == 'factures.php' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-text"></i>
            <span>Factures</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('paiements', $user_role, $user_permissions)): ?>
        <a href="paiements.php" class="nav-item <?= $current_page == 'paiements.php' ? 'active' : '' ?>">
            <i class="bi bi-credit-card"></i>
            <span>Paiements</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('codes_promo', $user_role, $user_permissions)): ?>
        <a href="codes_promo.php" class="nav-item <?= $current_page == 'codes_promo.php' ? 'active' : '' ?>">
            <i class="bi bi-tags"></i>
            <span>Codes promo</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('avis_admin', $user_role, $user_permissions)): ?>
        <a href="avis_admin.php" class="nav-item <?= $current_page == 'avis_admin.php' ? 'active' : '' ?>">
            <i class="bi bi-star"></i>
            <span>Avis clients</span>
        </a>
        <?php endif; ?>
        
        <!-- ===== OUTILS AVANCÉS ===== -->
        <?php if(a_acces('livraisons', $user_role, $user_permissions) || a_acces('fidelisation', $user_role, $user_permissions) || a_acces('rapports', $user_role, $user_permissions) || a_acces('notifications', $user_role, $user_permissions)): ?>
        <div class="nav-section">Outils avancés</div>
        <?php endif; ?>
        
        <?php if(a_acces('livraisons', $user_role, $user_permissions)): ?>
        <a href="livraisons.php" class="nav-item <?= $current_page == 'livraisons.php' ? 'active' : '' ?>">
            <i class="bi bi-truck"></i>
            <span>Livraisons</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('fidelisation', $user_role, $user_permissions)): ?>
        <a href="fidelisation.php" class="nav-item <?= $current_page == 'fidelisation.php' ? 'active' : '' ?>">
            <i class="bi bi-heart"></i>
            <span>Fidélisation</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('rapports', $user_role, $user_permissions)): ?>
        <a href="rapports.php" class="nav-item <?= $current_page == 'rapports.php' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-bar-graph"></i>
            <span>Rapports</span>
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('notifications', $user_role, $user_permissions)): ?>
        <a href="notifications.php" class="nav-item <?= $current_page == 'notifications.php' ? 'active' : '' ?>">
            <i class="bi bi-bell"></i>
            <span>Notifications</span>
            <?php
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE est_lue = 0");
                $nb = $stmt->fetchColumn();
                if ($nb > 0) echo '<span class="badge-blue">' . $nb . '</span>';
            } catch(Exception $e) {}
            ?>
        </a>
        <?php endif; ?>
        
        <!-- ===== STATISTIQUES ===== -->
        <?php if(a_acces('analytics', $user_role, $user_permissions)): ?>
        <div class="nav-section">Statistiques</div>
        <a href="analytics.php" class="nav-item <?= $current_page == 'analytics.php' ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Analytics</span>
        </a>
        <?php endif; ?>
        
        <!-- ===== SUPER ADMIN ===== -->
        <?php if($user_role === 'super_admin'): ?>
        <div class="nav-section">Super Admin</div>
        <a href="gestion_administrateurs.php" class="nav-item <?= $current_page == 'gestion_administrateurs.php' ? 'active' : '' ?>">
            <i class="bi bi-shield-fill-check"></i>
            <span>Administrateurs</span>
            <span class="badge-super">SUPER</span>
        </a>
        <a href="logs.php" class="nav-item <?= $current_page == 'logs.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i>
            <span>Journal d'audit</span>
            <span class="badge-super">SUPER</span>
        </a>
        <a href="parametres.php" class="nav-item <?= $current_page == 'parametres.php' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i>
            <span>Paramètres</span>
            <span class="badge-super">SUPER</span>
        </a>
        <a href="maintenance.php" class="nav-item <?= $current_page == 'maintenance.php' ? 'active' : '' ?>">
            <i class="bi bi-tools"></i>
            <span>Maintenance</span>
            <span class="badge-super">SUPER</span>
        </a>
        <?php endif; ?>
        
        <!-- ===== COMPTE ===== -->
        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item" target="_blank">
            <i class="bi bi-box-arrow-up-right"></i>
            <span>Voir le site</span>
        </a>
        <a href="logout.php" class="nav-item logout" onclick="return confirm('Voulez-vous vraiment vous déconnecter ?');">
            <i class="bi bi-power"></i>
            <span>Déconnexion</span>
        </a>
        
    </nav>
</aside>

<!-- ===== OVERLAY MOBILE ===== -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
/* ============================================
   SIDEBAR - DESIGN NÉON OR PROFESSIONNEL
   ============================================ */
.sidebar {
    width: 260px;
    background: #0D0D0D;
    background-image: 
        linear-gradient(rgba(200,146,42,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(200,146,42,0.03) 1px, transparent 1px);
    background-size: 30px 30px;
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    z-index: 100;
    overflow-y: auto;
    border-right: 1px solid rgba(200,146,42,0.15);
    transition: transform 0.3s ease;
}
.sidebar::-webkit-scrollbar { width: 5px; }
.sidebar::-webkit-scrollbar-track { background: transparent; }
.sidebar::-webkit-scrollbar-thumb { 
    background: rgba(200,146,42,0.3); 
    border-radius: 10px; 
}
.sidebar::-webkit-scrollbar-thumb:hover { background: rgba(200,146,42,0.5); }

/* ===== BRAND ===== */
.sidebar-brand {
    padding: 24px 20px 18px;
    border-bottom: 1px solid rgba(200,146,42,0.12);
    background: linear-gradient(180deg, rgba(200,146,42,0.05), transparent);
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
}
.sidebar-brand::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(200,146,42,0.1) 0%, transparent 60%);
    pointer-events: none;
    animation: brandGlow 8s ease-in-out infinite;
}
@keyframes brandGlow {
    0%, 100% { transform: translate(0, 0); opacity: 0.6; }
    50% { transform: translate(-15px, 15px); opacity: 1; }
}

.brand-logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: #C8922A;
    letter-spacing: 3px;
    text-shadow: 0 0 20px rgba(200,146,42,0.5);
    position: relative;
    z-index: 1;
    text-align: center;
}
.brand-sub {
    font-size: 0.55rem;
    color: rgba(255,255,255,0.35);
    letter-spacing: 2.5px;
    text-transform: uppercase;
    margin-top: 4px;
    text-align: center;
    position: relative;
    z-index: 1;
}

/* ===== ADMIN USER ===== */
.admin-user {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-top: 16px;
    padding: 10px 12px;
    background: rgba(200,146,42,0.07);
    border: 1px solid rgba(200,146,42,0.18);
    border-radius: 10px;
    position: relative;
    z-index: 1;
    transition: all 0.3s ease;
}
.admin-user:hover {
    background: rgba(200,146,42,0.1);
    border-color: rgba(200,146,42,0.35);
    box-shadow: 0 0 20px rgba(200,146,42,0.15);
}
.admin-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    color: #fff;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 
        0 0 0 2px rgba(200,146,42,0.35),
        0 0 15px rgba(200,146,42,0.4);
}
.admin-info {
    flex: 1;
    min-width: 0;
}
.admin-name {
    font-size: 0.82rem;
    color: #fff;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.admin-role {
    font-size: 0.58rem;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.role-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

/* ===== THEME TOGGLE ===== */
.theme-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    margin-top: 12px;
    padding: 8px 14px;
    background: rgba(200,146,42,0.08);
    border: 1px solid rgba(200,146,42,0.2);
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}
.theme-toggle:hover {
    background: rgba(200,146,42,0.12);
    border-color: rgba(200,146,42,0.4);
    box-shadow: 0 0 20px rgba(200,146,42,0.2);
}
.theme-toggle .icon-sun,
.theme-toggle .icon-moon {
    font-size: 0.85rem;
    transition: all 0.3s ease;
}
.theme-toggle .icon-sun {
    color: #E8B55A;
    text-shadow: 0 0 10px rgba(232,181,90,0.8);
}
.theme-toggle .icon-moon {
    color: rgba(255,255,255,0.3);
}
[data-theme="dark"] .theme-toggle .icon-sun {
    color: rgba(255,255,255,0.3);
    text-shadow: none;
}
[data-theme="dark"] .theme-toggle .icon-moon {
    color: #E8B55A;
    text-shadow: 0 0 10px rgba(232,181,90,0.8);
}

.theme-track {
    flex: 1;
    height: 20px;
    background: rgba(200,146,42,0.15);
    border-radius: 20px;
    position: relative;
    margin: 0 10px;
    border: 1px solid rgba(200,146,42,0.25);
    transition: all 0.3s ease;
}
.theme-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    transition: transform 0.3s ease;
    box-shadow: 0 0 12px rgba(200,146,42,0.8);
}
[data-theme="dark"] .theme-thumb {
    transform: translateX(100%);
    background: linear-gradient(135deg, #E8B55A, #F5D689);
    box-shadow: 0 0 15px rgba(232,181,90,0.9);
}

/* ===== NAV ===== */
.sidebar-nav {
    flex: 1;
    padding: 8px 12px 20px;
    overflow-y: auto;
}
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-thumb { 
    background: rgba(200,146,42,0.2); 
    border-radius: 10px; 
}

.nav-section {
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: rgba(200,146,42,0.5);
    padding: 18px 14px 8px;
    margin-top: 4px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-radius: 8px;
    text-decoration: none;
    color: rgba(255,255,255,0.6);
    font-size: 0.83rem;
    font-weight: 400;
    margin-bottom: 2px;
    transition: all 0.2s ease;
    position: relative;
    border-left: 3px solid transparent;
}
.nav-item i {
    font-size: 1rem;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
    transition: all 0.2s ease;
}
.nav-item span:not([class*="badge"]) {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.nav-item:hover {
    background: rgba(200,146,42,0.08);
    color: #fff;
    border-left-color: rgba(200,146,42,0.5);
}
.nav-item:hover i {
    color: #C8922A;
    text-shadow: 0 0 12px rgba(200,146,42,0.7);
}
.nav-item.active {
    background: linear-gradient(90deg, rgba(200,146,42,0.18), rgba(200,146,42,0.03));
    color: #C8922A;
    border-left-color: #C8922A;
    font-weight: 500;
    box-shadow: inset 0 0 20px rgba(200,146,42,0.08);
}
.nav-item.active i {
    color: #C8922A;
    text-shadow: 0 0 15px rgba(200,146,42,0.9);
}
.nav-item.logout {
    color: rgba(231,76,60,0.7);
}
.nav-item.logout:hover {
    color: #E74C3C;
    background: rgba(231,76,60,0.08);
    border-left-color: #E74C3C;
}
.nav-item.logout:hover i {
    color: #E74C3C;
    text-shadow: 0 0 12px rgba(231,76,60,0.7);
}

/* ===== BADGES ===== */
.badge-red, .badge-blue {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 10px;
    font-size: 0.6rem;
    font-weight: 700;
    color: #fff;
    margin-left: auto;
    flex-shrink: 0;
}
.badge-red {
    background: #E74C3C;
    box-shadow: 0 0 12px rgba(231,76,60,0.6);
    animation: badgePulse 2s ease-in-out infinite;
}
.badge-blue {
    background: #2980B9;
    box-shadow: 0 0 12px rgba(41,128,185,0.6);
}
@keyframes badgePulse {
    0%, 100% { box-shadow: 0 0 12px rgba(231,76,60,0.6); }
    50% { box-shadow: 0 0 20px rgba(231,76,60,1); }
}

.badge-super {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    background: linear-gradient(135deg, rgba(200,146,42,0.25), rgba(232,181,90,0.15));
    color: #E8B55A;
    font-size: 0.52rem;
    font-weight: 700;
    letter-spacing: 0.8px;
    border-radius: 8px;
    border: 1px solid rgba(200,146,42,0.35);
    margin-left: auto;
    flex-shrink: 0;
    text-transform: uppercase;
}

/* ===== OVERLAY MOBILE ===== */
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 99;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.sidebar-overlay.show {
    display: block;
    opacity: 1;
}

/* ============================================
   MODE CLAIR (sidebar reste sombre mais s'adapte)
   ============================================ */
[data-theme="light"] .sidebar,
:root:not([data-theme="dark"]) .sidebar {
    background: #0D0D0D;
}

/* ============================================
   RESPONSIVE - MOBILE
   ============================================ */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
        box-shadow: 20px 0 60px rgba(0,0,0,0.5);
    }
    .sidebar.open {
        transform: translateX(0);
    }
    .sidebar-overlay.show {
        display: block;
    }
}

/* ============================================
   RESPONSIVE - TABLETTE
   ============================================ */
@media (min-width: 769px) and (max-width: 1024px) {
    .sidebar {
        width: 220px;
    }
    .brand-logo { font-size: 0.95rem; letter-spacing: 2px; }
    .nav-item { font-size: 0.78rem; padding: 9px 12px; }
    .nav-item i { font-size: 0.9rem; width: 18px; }
}
</style>

<script>
(function() {
    var btn = document.getElementById('themeToggle');
    var html = document.documentElement;
    
    if (btn) {
        btn.addEventListener('click', function() {
            var current = html.getAttribute('data-theme') || 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem('admin_theme', next); } catch(e) {}
            html.setAttribute('data-theme', next);
        });
    }
    
    // ===== MOBILE SIDEBAR =====
    var mobileToggle = document.getElementById('mobileToggle');
    var overlay = document.getElementById('sidebarOverlay');
    var sidebar = document.getElementById('sidebar');
    
    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
    }
    function openSidebar() {
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('show');
    }
    
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });
    }
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Fermer la sidebar au clic sur un lien (mobile)
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && e.target.closest('.sidebar .nav-item')) {
            closeSidebar();
        }
    });
})();
</script>