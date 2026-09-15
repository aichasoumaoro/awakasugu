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
    if ($user_role === 'super_admin') {
        return true;
    }
    
    if ($user_role === 'directeur') {
        if ($permission === 'parametres') {
            return false;
        }
        return true;
    }
    
    if ($user_role === 'admin') {
        if ($permission === 'dashboard') {
            return true;
        }
        return in_array($permission, $user_permissions);
    }
    
    if ($user_role === 'admin2') {
        if ($permission === 'dashboard') {
            return true;
        }
        return in_array($permission, $user_permissions);
    }
    
    if ($user_role === null || $user_role === '') {
        if ($permission === 'dashboard') {
            return in_array('dashboard', $user_permissions);
        }
        return in_array($permission, $user_permissions);
    }
    
    return false;
}

// ============================================
// DEFINITION DES ROLES POUR L'AFFICHAGE
// ============================================
$role_labels = [
    'super_admin' => 'Super Administrateur',
    'directeur' => 'Directeur',
    'admin' => 'Administrateur',
    'admin2' => 'Agent / Vendeur'
];

$role_colors = [
    'super_admin' => '#8E44AD',
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
?>
<!-- ============================================
     SIDEBAR
     ============================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">AWA KA SUGU</div>
        <div class="brand-sub">Administration</div>
        <div class="admin-user">
            <div class="admin-avatar" style="background: <?= $role_color_header ?>;">
                <?= strtoupper(substr($admin_nom, 0, 1)) ?>
            </div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($admin_nom) ?></div>
                <div class="admin-role" style="color: <?= $role_color_header ?>;">
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $role_color_header ?>;margin-right:6px;"></span>
                    <?= $role_label_header ?>
                    <?php if(empty($user_role)): ?>
                        <span style="font-size:0.5rem;color:#8A99AA;font-weight:300;">(sans role)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ===== BOUTON CLAIR / SOMBRE ===== -->
        <button type="button" class="theme-toggle" id="themeToggle" title="Changer de theme">
            <i class="bi bi-sun-fill" style="font-size:0.9rem;"></i>
            <span class="theme-toggle-track">
                <span class="theme-toggle-thumb"></span>
            </span>
            <i class="bi bi-moon-stars-fill" style="font-size:0.9rem;"></i>
            <span class="theme-toggle-label" id="themeToggleLabel">Clair</span>
        </button>
    </div>
    <nav>
        <div class="nav-section">Principal</div>
        
        <?php if(a_acces('dashboard', $user_role, $user_permissions)): ?>
        <a href="dashboard.php" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            Tableau de bord
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('point_de_vente', $user_role, $user_permissions)): ?>
        <a href="point_de_vente.php" class="nav-item <?= $current_page == 'point_de_vente.php' ? 'active' : '' ?>">
            Point de vente
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('produits', $user_role, $user_permissions)): ?>
        <a href="produits.php" class="nav-item <?= $current_page == 'produits.php' ? 'active' : '' ?>">
            Produits
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('commandes', $user_role, $user_permissions)): ?>
        <a href="commandes.php" class="nav-item <?= $current_page == 'commandes.php' ? 'active' : '' ?>">
            Commandes
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('clients', $user_role, $user_permissions)): ?>
        <a href="clients.php" class="nav-item <?= $current_page == 'clients.php' ? 'active' : '' ?>">
            Clients
        </a>
        <?php endif; ?>

        <?php if(a_acces('messagerie', $user_role, $user_permissions)): ?>
        <a href="messagerie.php" class="nav-item <?= $current_page == 'messagerie.php' ? 'active' : '' ?>">
            Messagerie
            <?php
            try {
                $host = 'localhost';
                $dbname = 'awakasugu_db';
                $user = 'root';
                $pass = '';
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
                $stmt = $pdo->query("
                    SELECT COUNT(*) as nb 
                    FROM messages m 
                    JOIN conversations c ON m.conversation_id = c.id
                    WHERE m.lu = 0 AND m.expediteur_type = 'client'
                ");
                $nb_non_lus = $stmt->fetchColumn();
                if ($nb_non_lus > 0) {
                    echo '<span class="badge-messagerie">' . $nb_non_lus . '</span>';
                }
            } catch(Exception $e) {}
            ?>
        </a>
        <?php endif; ?>

        <?php if(a_acces('plats', $user_role, $user_permissions) || a_acces('commandes_repas', $user_role, $user_permissions) || a_acces('reservations', $user_role, $user_permissions)): ?>
        <div class="nav-section">Restaurant</div>
        <?php endif; ?>
        
        <?php if(a_acces('plats', $user_role, $user_permissions)): ?>
        <a href="plats.php" class="nav-item <?= $current_page == 'plats.php' ? 'active' : '' ?>">
            Plats
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('commandes_repas', $user_role, $user_permissions)): ?>
        <a href="commandes_repas.php" class="nav-item <?= $current_page == 'commandes_repas.php' ? 'active' : '' ?>">
            Commandes repas
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('reservations', $user_role, $user_permissions)): ?>
        <a href="reservations.php" class="nav-item <?= $current_page == 'reservations.php' ? 'active' : '' ?>">
            Reservations
        </a>
        <?php endif; ?>

        <?php if(a_acces('achats', $user_role, $user_permissions) || a_acces('stocks', $user_role, $user_permissions) || a_acces('promotions', $user_role, $user_permissions) || a_acces('videos', $user_role, $user_permissions) || a_acces('factures', $user_role, $user_permissions) || a_acces('paiements', $user_role, $user_permissions) || a_acces('codes_promo', $user_role, $user_permissions) || a_acces('avis_admin', $user_role, $user_permissions)): ?>
        <div class="nav-section">Gestion</div>
        <?php endif; ?>
        
        <?php if(a_acces('achats', $user_role, $user_permissions)): ?>
        <a href="achats.php" class="nav-item <?= $current_page == 'achats.php' ? 'active' : '' ?>">
            Achats
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('stocks', $user_role, $user_permissions)): ?>
        <a href="stocks.php" class="nav-item <?= $current_page == 'stocks.php' ? 'active' : '' ?>">
            Stocks
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('promotions', $user_role, $user_permissions)): ?>
        <a href="promotions.php" class="nav-item <?= $current_page == 'promotions.php' ? 'active' : '' ?>">
            Promotions
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('videos', $user_role, $user_permissions)): ?>
        <a href="videos.php" class="nav-item <?= $current_page == 'videos.php' ? 'active' : '' ?>">
            Videos
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('factures', $user_role, $user_permissions)): ?>
        <a href="factures.php" class="nav-item <?= $current_page == 'factures.php' ? 'active' : '' ?>">
            Factures
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('paiements', $user_role, $user_permissions)): ?>
        <a href="paiements.php" class="nav-item <?= $current_page == 'paiements.php' ? 'active' : '' ?>">
            Paiements
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('codes_promo', $user_role, $user_permissions)): ?>
        <a href="codes_promo.php" class="nav-item <?= $current_page == 'codes_promo.php' ? 'active' : '' ?>">
            Codes promo
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('avis_admin', $user_role, $user_permissions)): ?>
        <a href="avis_admin.php" class="nav-item <?= $current_page == 'avis_admin.php' ? 'active' : '' ?>">
            Avis clients
        </a>
        <?php endif; ?>

        <?php if(a_acces('livraisons', $user_role, $user_permissions) || a_acces('fidelisation', $user_role, $user_permissions) || a_acces('rapports', $user_role, $user_permissions) || a_acces('notifications', $user_role, $user_permissions)): ?>
        <div class="nav-section">Outils avances</div>
        <?php endif; ?>
        
        <?php if(a_acces('livraisons', $user_role, $user_permissions)): ?>
        <a href="livraisons.php" class="nav-item <?= $current_page == 'livraisons.php' ? 'active' : '' ?>">
            Livraisons
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('fidelisation', $user_role, $user_permissions)): ?>
        <a href="fidelisation.php" class="nav-item <?= $current_page == 'fidelisation.php' ? 'active' : '' ?>">
            Fidelisation
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('rapports', $user_role, $user_permissions)): ?>
        <a href="rapports.php" class="nav-item <?= $current_page == 'rapports.php' ? 'active' : '' ?>">
            Rapports
        </a>
        <?php endif; ?>
        
        <?php if(a_acces('notifications', $user_role, $user_permissions)): ?>
        <a href="notifications.php" class="nav-item <?= $current_page == 'notifications.php' ? 'active' : '' ?>">
            Notifications
            <?php
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as nb FROM notifications WHERE est_lue = 0");
                $nb_notif = $stmt->fetchColumn();
                if ($nb_notif > 0) {
                    echo '<span class="badge-messagerie" style="background:#2980B9;">' . $nb_notif . '</span>';
                }
            } catch(Exception $e) {}
            ?>
        </a>
        <?php endif; ?>

        <?php if(a_acces('analytics', $user_role, $user_permissions)): ?>
        <div class="nav-section">Statistiques</div>
        <a href="analytics.php" class="nav-item <?= $current_page == 'analytics.php' ? 'active' : '' ?>">
            Analytics
        </a>
        <?php endif; ?>

        <?php if($user_role === 'super_admin'): ?>
        <div class="nav-section">Super Admin</div>
        <a href="gestion_administrateurs.php" class="nav-item <?= $current_page == 'gestion_administrateurs.php' ? 'active' : '' ?>">
            Administrateurs
            <span class="badge-role">Super</span>
        </a>
        <a href="logs.php" class="nav-item <?= $current_page == 'logs.php' ? 'active' : '' ?>">
            Journal d'audit
            <span class="badge-role">Super</span>
        </a>
        <a href="parametres.php" class="nav-item <?= $current_page == 'parametres.php' ? 'active' : '' ?>">
            Parametres
            <span class="badge-role">Super</span>
        </a>
        <a href="maintenance.php" class="nav-item <?= $current_page == 'maintenance.php' ? 'active' : '' ?>">
            Maintenance
            <span class="badge-role">Super</span>
        </a>
        <?php endif; ?>

        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item">Voir le site</a>
        <a href="logout.php" class="nav-item logout">Deconnexion</a>
    </nav>
</aside>

<style>
/* ===== STYLES SIDEBAR ===== */
.badge-messagerie {
    display: inline-flex; align-items: center; justify-content: center;
    background: #E74C3C; color: #fff; font-size: 0.55rem; font-weight: 700;
    min-width: 18px; height: 18px; border-radius: 50%; 
    margin-left: auto; margin-right: 8px; line-height: 1;
    padding: 0 5px;
}

.badge-role {
    display: inline-block;
    background: rgba(142,68,173,0.25);
    color: #8E44AD;
    font-size: 0.5rem;
    font-weight: 700;
    padding: 1px 8px;
    border-radius: 10px;
    margin-left: auto;
    margin-right: 8px;
    text-transform: uppercase;
}

.nav-item {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    width: 100%;
    text-decoration: none;
    color: rgba(255,255,255,0.6);
    padding: 10px 16px;
    border-radius: 8px;
    transition: all 0.2s ease;
    font-size: 0.85rem;
    font-weight: 400;
    border-left: 3px solid transparent;
}
.nav-item:hover { 
    background: rgba(255,255,255,0.08); 
    color: #fff; 
    border-left-color: rgba(200,146,42,0.5);
}
.nav-item.active { 
    background: rgba(200,146,42,0.15); 
    color: #C8922A; 
    border-left-color: #C8922A;
    font-weight: 500;
}
.nav-item .badge-messagerie { margin-left: auto; }
.nav-item.logout { color: rgba(231,76,60,0.5); }
.nav-item.logout:hover { color: #E74C3C; background: rgba(231,76,60,0.08); border-left-color: #E74C3C; }

.nav-section {
    font-size: 0.55rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: rgba(255,255,255,0.2);
    padding: 16px 16px 6px;
    font-weight: 600;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    margin-bottom: 4px;
}

/* ===== SIDEBAR BRAND ===== */
.sidebar-brand {
    padding: 24px 20px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.brand-logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem;
    font-weight: 700;
    color: #C8922A;
    letter-spacing: 2px;
}
.brand-sub {
    font-size: 0.5rem;
    color: rgba(255,255,255,0.25);
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-top: 2px;
}
.admin-user {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    padding: 10px 12px;
    background: rgba(255,255,255,0.04);
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.06);
}
.admin-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    color: #fff;
    font-weight: 600;
    flex-shrink: 0;
}
.admin-name { 
    font-size: 0.8rem; 
    color: #fff; 
    font-weight: 500; 
}
.admin-role { 
    font-size: 0.55rem; 
    letter-spacing: 0.5px; 
    text-transform: uppercase; 
}

/* ===== THEME TOGGLE (BOUTON CLAIR/SOMBRE) ===== */
.theme-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    margin-top: 12px;
    padding: 8px 12px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 30px;
    cursor: pointer;
    color: rgba(255,255,255,0.6);
    font-size: 0.8rem;
    transition: all 0.3s ease;
}
.theme-toggle:hover { 
    background: rgba(255,255,255,0.1);
    border-color: rgba(200,146,42,0.3);
}
.theme-toggle .bi { font-size: 0.9rem; }
.theme-toggle-track {
    flex: 1;
    height: 20px;
    background: rgba(255,255,255,0.12);
    border-radius: 20px;
    position: relative;
    margin: 0 4px;
}
.theme-toggle-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #C8922A;
    transition: transform 0.3s ease;
    box-shadow: 0 2px 8px rgba(200,146,42,0.3);
}
[data-theme="dark"] .theme-toggle-thumb { 
    transform: translateX(24px); 
    background: #E8B55A;
}
.theme-toggle-label {
    font-size: 0.6rem;
    font-weight: 600;
    color: rgba(255,255,255,0.5);
    min-width: 32px;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
[data-theme="dark"] .theme-toggle-label {
    color: #C8922A;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .sidebar { 
        position: fixed; 
        bottom: 0; left: 0; right: 0; top: auto !important; 
        height: auto !important; 
        background: #0D0D0D; 
        border-top: 1px solid rgba(255,255,255,0.08);
        z-index: 9999; 
        padding: 0; 
        display: flex; 
        overflow-x: auto; 
        box-shadow: 0 -2px 20px rgba(0,0,0,0.08); 
        max-height: 60px; 
    }
    .sidebar .sidebar-brand { display: none !important; }
    .sidebar nav { 
        display: flex; 
        align-items: center; 
        gap: 2px; 
        padding: 4px 6px; 
        width: 100%; 
        overflow-x: auto; 
    }
    .sidebar .nav-item { 
        flex-direction: column; 
        align-items: center; 
        padding: 4px 8px; 
        min-width: 50px; 
        font-size: 0.5rem; 
        text-align: center; 
        white-space: nowrap;
        border-left: none;
        border-radius: 6px;
    }
    .sidebar .nav-item.active {
        background: rgba(200,146,42,0.2);
        border-left: none;
    }
    .sidebar .nav-item .badge-messagerie { 
        position: absolute; 
        top: -4px; 
        right: -4px; 
        width: 14px; 
        height: 14px; 
        font-size: 0.4rem; 
    }
    .sidebar .badge-role { display: none; }
    .sidebar .nav-section { display: none; }
    .sidebar .theme-toggle { display: none !important; }
    .main { padding-bottom: 70px !important; }
}
</style>

<script>
(function() {
    var btn = document.getElementById('themeToggle');
    var label = document.getElementById('themeToggleLabel');
    
    function updateThemeLabel(theme) {
        if (label) {
            label.textContent = theme === 'dark' ? 'Sombre' : 'Clair';
        }
    }
    
    if (btn) {
        btn.addEventListener('click', function() {
            var current = document.documentElement.getAttribute('data-theme') || 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem('admin_theme', next); } catch(e) {}
            document.documentElement.setAttribute('data-theme', next);
            updateThemeLabel(next);
        });
    }
    
    // Initialiser le label
    var initialTheme = document.documentElement.getAttribute('data-theme') || 'light';
    updateThemeLabel(initialTheme);
})();
</script>