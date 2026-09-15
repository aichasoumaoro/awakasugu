<?php
// ============================================
// MOBILE HEADER - ADMIN AWA KA SUGU
// ============================================

// Détecter si l'utilisateur est sur mobile
function isMobile() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $mobileAgents = ['Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone', 'Opera Mini', 'IEMobile'];
    foreach ($mobileAgents as $agent) {
        if (stripos($userAgent, $agent) !== false) return true;
    }
    return false;
}
$is_mobile = isMobile();
?>
<?php if($is_mobile): ?>
<style>
/* ===== MODE MOBILE ===== */
@media (max-width: 768px) {
    .sidebar { 
        position: fixed; 
        bottom: 0; 
        left: 0; 
        right: 0; 
        top: auto !important;
        height: auto !important;
        background: #fff;
        border-top: 1px solid #E0E0E0;
        z-index: 9999;
        padding: 0;
        display: flex;
        overflow-x: auto;
        box-shadow: 0 -2px 20px rgba(0,0,0,0.08);
        max-height: 60px;
    }
    .sidebar .sidebar-inner {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        width: 100%;
        justify-content: space-around;
    }
    .sidebar .sidebar-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 4px 6px;
        min-width: 50px;
        text-decoration: none;
        color: #666;
        font-size: 0.5rem;
    }
    .sidebar .sidebar-item i {
        font-size: 1.2rem;
        margin-bottom: 1px;
    }
    .sidebar .sidebar-item.active {
        color: #C8922A;
    }
    .main { 
        padding-bottom: 70px !important;
    }
    .topbar-right {
        flex-wrap: wrap;
        gap: 4px;
    }
    .topbar-right .btn-admin {
        font-size: 0.7rem;
        padding: 4px 10px;
    }
    .stats-row {
        grid-template-columns: 1fr 1fr !important;
        gap: 8px;
    }
    .stat-box {
        padding: 10px !important;
    }
    .stat-val {
        font-size: 1.1rem !important;
    }
    .dashboard-grid {
        grid-template-columns: 1fr !important;
    }
    .table-container {
        overflow-x: auto;
    }
    .card-white {
        padding: 10px !important;
    }
    .card-title {
        font-size: 0.9rem !important;
    }
    .toolbar {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    .search-box {
        max-width: 100% !important;
    }
    .produit-grid {
        grid-template-columns: 1fr !important;
    }
    .btn-admin {
        padding: 6px 12px !important;
        font-size: 0.7rem !important;
    }
    .welcome-card {
        flex-direction: column !important;
        text-align: center;
    }
    .welcome-card h2 {
        font-size: 1rem !important;
    }
    .donut-wrap {
        width: 180px !important;
        margin: 0 auto !important;
    }
    .topbar-title {
        font-size: 1.1rem !important;
    }
}
</style>
<?php endif; ?>