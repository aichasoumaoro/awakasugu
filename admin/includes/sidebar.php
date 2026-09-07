<?php
// ============================================
// SIDEBAR ADMIN - AWA KA SUGU
// ============================================
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- ============================================
     SIDEBAR (id="sidebar" requis pour le toggle mobile de header.php)
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
                </div>
            </div>
        </div>

        <!-- ===== AJOUT : TOGGLE CLAIR / SOMBRE ===== -->
        <button type="button" class="theme-toggle" id="themeToggle" title="Changer de thème clair/sombre">
            <i class="bi bi-sun-fill"></i>
            <span class="theme-toggle-track"><span class="theme-toggle-thumb"></span></span>
            <i class="bi bi-moon-stars-fill"></i>
        </button>
    </div>
    <nav>
        <div class="nav-section">Principal</div>
        
        <a href="dashboard.php" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Tableau de bord
        </a>
        
        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur' || $admin_role === 'admin'): ?>
        <a href="point_de_vente.php" class="nav-item <?= $current_page == 'point_de_vente.php' ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i> Point de vente
        </a>
        <?php endif; ?>
        
        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
        <a href="produits.php" class="nav-item <?= $current_page == 'produits.php' ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i> Produits
        </a>
        <?php endif; ?>
        
        <a href="commandes.php" class="nav-item <?= $current_page == 'commandes.php' ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> Commandes
        </a>
        
        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
        <a href="clients.php" class="nav-item <?= $current_page == 'clients.php' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Clients
        </a>
        <?php endif; ?>

        <a href="messagerie.php" class="nav-item <?= $current_page == 'messagerie.php' ? 'active' : '' ?>">
            <i class="bi bi-chat-dots"></i> Messagerie
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

        <div class="nav-section">Restaurant</div>
        
        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur' || $admin_role === 'admin'): ?>
        <a href="plats.php" class="nav-item <?= $current_page == 'plats.php' ? 'active' : '' ?>">
            <i class="bi bi-cup-hot"></i> Plats
        </a>
        <a href="commandes_repas.php" class="nav-item <?= $current_page == 'commandes_repas.php' ? 'active' : '' ?>">
            <i class="bi bi-bag-check"></i> Commandes repas
        </a>
        <a href="reservations.php" class="nav-item <?= $current_page == 'reservations.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar-check"></i> Réservations
        </a>
        <?php endif; ?>

        <div class="nav-section">Gestion</div>
        
        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
        <a href="achats.php" class="nav-item <?= $current_page == 'achats.php' ? 'active' : '' ?>">
            <i class="bi bi-cart-check"></i> Achats
        </a>
        <?php endif; ?>
        
        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur' || $admin_role === 'admin'): ?>
        <a href="stocks.php" class="nav-item <?= $current_page == 'stocks.php' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart"></i> Stocks
        </a>
        <a href="promotions.php" class="nav-item <?= $current_page == 'promotions.php' ? 'active' : '' ?>">
            <i class="bi bi-percent"></i> Promotions
        </a>
        <a href="videos.php" class="nav-item <?= $current_page == 'videos.php' ? 'active' : '' ?>">
            <i class="bi bi-camera-reels"></i> Vidéos
        </a>
        <?php endif; ?>
        
        <?php if($admin_role === 'super_admin' || $admin_role === 'directeur'): ?>
        <a href="factures.php" class="nav-item <?= $current_page == 'factures.php' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-text"></i> Factures
        </a>
        <a href="paiements.php" class="nav-item <?= $current_page == 'paiements.php' ? 'active' : '' ?>">
            <i class="bi bi-credit-card"></i> Paiements
        </a>
        <a href="codes_promo.php" class="nav-item <?= $current_page == 'codes_promo.php' ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Codes promo
        </a>
        <a href="avis_admin.php" class="nav-item <?= $current_page == 'avis_admin.php' ? 'active' : '' ?>">
            <i class="bi bi-star"></i> Avis clients
        </a>
        <?php endif; ?>
        
        <?php if($admin_role === 'super_admin'): ?>
        <a href="maintenance.php" class="nav-item <?= $current_page == 'maintenance.php' ? 'active' : '' ?>">
            <i class="bi bi-tools"></i> Maintenance
        </a>
        <?php endif; ?>

        <?php if($admin_role === 'super_admin'): ?>
        <div class="nav-section">Super Admin</div>
        <a href="gestion_administrateurs.php" class="nav-item <?= $current_page == 'gestion_administrateurs.php' ? 'active' : '' ?>">
            <i class="bi bi-person-gear"></i> Administrateurs
            <span class="badge-role">Super</span>
        </a>
        <a href="logs.php" class="nav-item <?= $current_page == 'logs.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Journal d'audit
            <span class="badge-role">Super</span>
        </a>
        <a href="parametres.php" class="nav-item <?= $current_page == 'parametres.php' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i> Paramètres
            <span class="badge-role">Super</span>
        </a>
        <?php endif; ?>

        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<style>
.badge-messagerie {
    display: inline-flex; align-items: center; justify-content: center;
    background: #E74C3C; color: white; font-size: 0.6rem; font-weight: 700;
    width: 20px; height: 20px; border-radius: 50%; margin-left: auto; margin-right: 10px; line-height: 1;
}
.nav-item { display: flex; align-items: center; justify-content: flex-start; width: 100%; }
.nav-item .badge-messagerie { margin-left: auto; }
@keyframes pulse-messagerie {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.5); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 5px rgba(231, 76, 60, 0.2); }
}
.nav-item .badge-messagerie { animation: pulse-messagerie 2s infinite; }
</style>

<!-- ===== AJOUT : SCRIPT DU TOGGLE CLAIR / SOMBRE ===== -->
<script>
(function() {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    btn.addEventListener('click', function() {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem('admin_theme', next); } catch(e) {}
        document.documentElement.setAttribute('data-theme', next);
        location.reload();
    });
})();
</script>