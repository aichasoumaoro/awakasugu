<?php
// ============================================
// SIDEBAR ADMIN - AWA KA SUGU
// À inclure dans toutes les pages admin
// ============================================

// Récupérer le nom du fichier courant pour la classe active
$current_file = basename($_SERVER['PHP_SELF']);
?>

<style>
/* ===== SIDEBAR MODERNISÉE ===== */
.admin-sidebar {
    background: linear-gradient(180deg, #0D0D0D 0%, #1A1A1A 100%);
    min-height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    width: 280px;
    padding: 30px 20px;
    z-index: 1000;
    box-shadow: 4px 0 20px rgba(0,0,0,0.3);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

/* ===== SCROLLBAR PERSONNALISÉE ===== */
.admin-sidebar::-webkit-scrollbar {
    width: 4px;
}
.admin-sidebar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}
.admin-sidebar::-webkit-scrollbar-thumb {
    background: #C8922A;
    border-radius: 4px;
}

/* ===== LOGO ===== */
.sidebar-logo {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 2px solid rgba(200,146,42,0.2);
    flex-shrink: 0;
}
.sidebar-logo h3 {
    font-family: 'Times New Roman', serif;
    font-style: italic;
    font-size: 1.6rem;
    font-weight: 700;
    background: linear-gradient(135deg, #C8922A 0%, #F5D76E 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 0 15px rgba(200,146,42,0.3);
    letter-spacing: 2px;
    margin: 0;
}
.sidebar-logo small {
    display: block;
    font-size: 0.6rem;
    color: rgba(255,255,255,0.3);
    letter-spacing: 3px;
    margin-top: 6px;
    -webkit-text-fill-color: rgba(255,255,255,0.3);
}

/* ===== MENU ===== */
.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    flex: 1;
}
.sidebar-menu li {
    margin-bottom: 4px;
}
.sidebar-menu li a {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 16px;
    border-radius: 10px;
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 500;
    transition: all 0.25s ease;
    position: relative;
}
.sidebar-menu li a i {
    font-size: 1.1rem;
    width: 22px;
    color: rgba(200,146,42,0.5);
    transition: all 0.25s;
    text-align: center;
}
.sidebar-menu li a:hover {
    background: rgba(200,146,42,0.08);
    color: #C8922A;
    transform: translateX(4px);
}
.sidebar-menu li a:hover i {
    color: #C8922A;
}
.sidebar-menu li a.active {
    background: linear-gradient(90deg, rgba(200,146,42,0.15), transparent);
    color: #C8922A;
    border-left: 3px solid #C8922A;
}
.sidebar-menu li a.active i {
    color: #C8922A;
}

/* ===== SÉPARATEUR ===== */
.sidebar-separator {
    margin: 15px 0 12px;
    border-top: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
}

/* ===== BOUTON MAINTENANCE DANS LE MENU ===== */
.sidebar-menu li a.maintenance-link {
    color: rgba(255,255,255,0.5);
}
.sidebar-menu li a.maintenance-link i {
    color: rgba(200,146,42,0.4);
}
.sidebar-menu li a.maintenance-link:hover {
    color: #C8922A;
    background: rgba(200,146,42,0.08);
}
.sidebar-menu li a.maintenance-link:hover i {
    color: #C8922A;
}

/* ===== SITE & DECONNEXION EN BAS ===== */
.sidebar-bottom {
    flex-shrink: 0;
    padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,0.06);
    margin-top: auto;
}
.sidebar-bottom li a {
    color: rgba(255,255,255,0.4);
}
.sidebar-bottom li a i {
    color: rgba(200,146,42,0.3);
}
.sidebar-bottom li a:hover {
    color: #C8922A;
}
.sidebar-bottom li a:hover i {
    color: #C8922A;
}
.sidebar-bottom li a.logout-link {
    color: rgba(231,76,60,0.5);
}
.sidebar-bottom li a.logout-link i {
    color: rgba(231,76,60,0.3);
}
.sidebar-bottom li a.logout-link:hover {
    color: #E74C3C;
    background: rgba(231,76,60,0.08);
}
.sidebar-bottom li a.logout-link:hover i {
    color: #E74C3C;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .admin-sidebar {
        width: 280px;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        box-shadow: 0 0 30px rgba(0,0,0,0.5);
    }
    .admin-sidebar.open {
        transform: translateX(0);
    }
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 999;
    }
    .sidebar-overlay.active {
        display: block;
    }
}

/* ===== TOGGLE BUTTON ===== */
.sidebar-toggle {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1001;
    background: #0D0D0D;
    border: 1px solid rgba(200,146,42,0.3);
    color: #C8922A;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s;
}
.sidebar-toggle:hover {
    background: rgba(200,146,42,0.1);
    border-color: #C8922A;
}

@media (max-width: 768px) {
    .sidebar-toggle {
        display: flex;
        align-items: center;
        gap: 10px;
    }
}
</style>

<!-- ===== TOGGLE BUTTON MOBILE ===== -->
<button class="sidebar-toggle" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
    <span style="font-size:0.7rem;font-weight:600;color:rgba(255,255,255,0.6);">Menu</span>
</button>

<!-- ===== OVERLAY MOBILE ===== -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- ===== SIDEBAR ===== -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-logo">
        <h3>AWA KA SUGU</h3>
        <small>LE MARCHÉ D'AWA</small>
    </div>
    
    <ul class="sidebar-menu">
        <!-- DASHBOARD -->
        <li>
            <a href="dashboard.php" class="<?= $current_file == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Tableau de bord
            </a>
        </li>
        
        <!-- BOUTIQUE -->
        <li>
            <a href="produits.php" class="<?= in_array($current_file, ['produits.php', 'produit_ajouter.php', 'produit_modifier.php']) ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i> Produits
            </a>
        </li>
        <li>
            <a href="point_de_vente.php" class="<?= $current_file == 'point_de_vente.php' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> Point de vente
            </a>
        </li>
        <li>
            <a href="achats.php" class="<?= $current_file == 'achats.php' ? 'active' : '' ?>">
                <i class="bi bi-cart-check"></i> Achats
            </a>
        </li>
        <li>
            <a href="commandes.php" class="<?= in_array($current_file, ['commandes.php', 'commande_details.php']) ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i> Commandes
            </a>
        </li>
        <li>
            <a href="clients.php" class="<?= $current_file == 'clients.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Clients
            </a>
        </li>
        
        <!-- RESTAURANT -->
        <li>
            <a href="plats.php" class="<?= $current_file == 'plats.php' ? 'active' : '' ?>">
                <i class="bi bi-cup-hot"></i> Plats
            </a>
        </li>
        <li>
            <a href="commandes_repas.php" class="<?= $current_file == 'commandes_repas.php' ? 'active' : '' ?>">
                <i class="bi bi-bag-check"></i> Commandes repas
            </a>
        </li>
        <li>
            <a href="reservations.php" class="<?= $current_file == 'reservations.php' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i> Réservations
            </a>
        </li>
        
        <li class="sidebar-separator"></li>
        
        <!-- GESTION -->
        <li>
            <a href="maintenance.php" class="<?= $current_file == 'maintenance.php' ? 'active' : '' ?>">
                <i class="bi bi-tools"></i> Maintenance
            </a>
        </li>
        <li>
            <a href="videos.php" class="<?= in_array($current_file, ['videos.php', 'video_ajouter.php', 'video_modifier.php']) ? 'active' : '' ?>">
                <i class="bi bi-camera-reels"></i> Vidéos
            </a>
        </li>
        <li>
            <a href="promotions.php" class="<?= $current_file == 'promotions.php' ? 'active' : '' ?>">
                <i class="bi bi-percent"></i> Promotions
            </a>
        </li>
        <li>
            <a href="bannieres.php" class="<?= $current_file == 'bannieres.php' ? 'active' : '' ?>">
                <i class="bi bi-images"></i> Bannières
            </a>
        </li>
        <li>
            <a href="stocks.php" class="<?= $current_file == 'stocks.php' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart"></i> Stocks
            </a>
        </li>
        <li>
            <a href="factures.php" class="<?= $current_file == 'factures.php' ? 'active' : '' ?>">
                <i class="bi bi-file-pdf"></i> Factures
            </a>
        </li>
        <li>
            <a href="rapports.php" class="<?= $current_file == 'rapports.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i> Rapports
            </a>
        </li>
    </ul>
    
    <!-- ===== BAS DU MENU ===== -->
    <ul class="sidebar-menu sidebar-bottom">
        <li>
            <a href="../index.php" target="_blank">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </li>
        <li>
            <a href="logout.php" class="logout-link">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>
        </li>
    </ul>
</aside>

<script>
// ===== TOGGLE SIDEBAR MOBILE =====
function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}

// Fermer le sidebar en cliquant sur un lien (mobile)
document.querySelectorAll('.admin-sidebar .sidebar-menu a').forEach(link => {
    link.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            // Ne pas fermer si c'est un lien externe ou logout
            if (this.getAttribute('target') !== '_blank' && !this.href.includes('logout.php')) {
                const sidebar = document.getElementById('adminSidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }
        }
    });
});

// Fermer avec la touche Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
    }
});
</script>