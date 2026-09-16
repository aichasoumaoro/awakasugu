<?php
// ============================================
// HEADER ADMIN - AWA KA SUGU
// ============================================
if (defined('ADMIN_HEADER_LOADED')) {
    return;
}
define('ADMIN_HEADER_LOADED', true);

if (!isset($admin_role)) {
    $admin_info = getAdminInfo();
    $admin_role = $admin_info['role'] ?? 'admin';
    $admin_nom = $admin_info['nom'] ?? 'Awa Doumbia';
    $admin_id = $admin_info['id'] ?? 0;
}

$role_labels_header = [
    'super_admin' => 'Super Administrateur',
    'directeur' => 'Directrice',
    'admin' => 'Administratrice',
    'admin2' => 'Agente'
];

$role_colors_header = [
    'super_admin' => '#C8922A',
    'directeur' => '#C8922A',
    'admin' => '#2980B9',
    'admin2' => '#7F8C8D'
];

$role_icons_header = [
    'super_admin' => 'bi-shield-fill-check',
    'directeur' => 'bi-crown-fill',
    'admin' => 'bi-person-badge-fill',
    'admin2' => 'bi-person-fill'
];

$role_label_header = $role_labels_header[$admin_role] ?? 'Administratrice';
$role_color_header = $role_colors_header[$admin_role] ?? '#C8922A';
$role_icon_header = $role_icons_header[$admin_role] ?? 'bi-person-badge-fill';

$page_title = $page_title ?? 'Administration';

if (!isset($commandes_attente_count)) {
    if (isset($_SESSION['commandes_attente_count'])) {
        $commandes_attente_count = $_SESSION['commandes_attente_count'];
    } else {
        $commandes_attente_count = 0;
    }
}

// ============================================
// MISE À JOUR DE LA DERNIÈRE ACTIVITÉ
// ============================================
if (isset($_SESSION['admin_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("UPDATE admin SET last_activity = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
    } catch(PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Anti-flash du thème -->
    <script>
    (function(){
        try {
            var t = localStorage.getItem('admin_theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        } catch(e) {}
    })();
    </script>

    <title><?= htmlspecialchars($page_title) ?> — Awa Ka Sugu Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        /* ============================================
           VARIABLES DE THÈME
           ============================================ */
        :root {
            --gold: #C8922A;
            --gold-light: #E8B55A;
            --gold-dark: #9A6E1A;
            --gold-pale: #F5D689;
            
            --bg-page: #F5F7FA;
            --bg-card: #FFFFFF;
            --bg-topbar: #FFFFFF;
            --bg-sidebar: linear-gradient(180deg, #0D0D0D 0%, #1A1510 100%);
            --text-primary: #1A2C3E;
            --text-secondary: #8A99AA;
            --border-color: #E8ECF0;
            --border-soft: #F0F2F5;
            --shadow-card: 0 1px 3px rgba(0,0,0,0.03);
            --shadow-hover: 0 8px 25px rgba(0,0,0,0.06);
            --input-bg: #FFFFFF;
            --welcome-bg: linear-gradient(135deg, #0D0D0D 0%, #1A1510 100%);
        }
        
        [data-theme="dark"] {
            --bg-page: #0F1218;
            --bg-card: #1A1F28;
            --bg-topbar: #1A1F28;
            --bg-sidebar: linear-gradient(180deg, #0A0A0A 0%, #12100C 100%);
            --text-primary: #E8ECF0;
            --text-secondary: #96A3B0;
            --border-color: #2A2F3A;
            --border-soft: #232833;
            --shadow-card: 0 1px 3px rgba(0,0,0,0.4);
            --shadow-hover: 0 8px 25px rgba(0,0,0,0.5);
            --input-bg: #232833;
            --welcome-bg: linear-gradient(135deg, #0A0A0A 0%, #1A1510 100%);
        }

        /* ============================================
           RESET
           ============================================ */
        *, *::before, *::after { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        
        html { 
            -webkit-text-size-adjust: 100%; 
            overflow-x: hidden; 
            scroll-behavior: smooth;
        }
        
        body { 
            font-family: 'Jost', sans-serif; 
            background: var(--bg-page); 
            color: var(--text-primary); 
            display: flex; 
            min-height: 100vh; 
            transition: background 0.25s ease, color 0.25s ease;
            overflow-x: hidden;
            max-width: 100vw;
            font-size: 15px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ============================================
           SIDEBAR
           ============================================ */
        .sidebar {
            width: 260px;
            background: var(--bg-sidebar);
            background-image: 
                linear-gradient(rgba(200,146,42,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(200,146,42,0.03) 1px, transparent 1px);
            background-size: 30px 30px;
            border-right: 1px solid rgba(200,146,42,0.15);
            position: fixed;
            top: 0; 
            left: 0; 
            bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { 
            background: rgba(200,146,42,0.3); 
            border-radius: 10px; 
        }
        .sidebar::-webkit-scrollbar-thumb:hover { 
            background: rgba(200,146,42,0.5); 
        }

        /* ===== BRAND ===== */
        .sidebar-brand {
            padding: 26px 22px 20px;
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
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--gold);
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
        .admin-role .role-dot {
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
            color: var(--gold-light);
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
            color: var(--gold-light);
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
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            transition: transform 0.3s ease;
            box-shadow: 0 0 12px rgba(200,146,42,0.8);
        }
        [data-theme="dark"] .theme-thumb {
            transform: translateX(100%);
            background: linear-gradient(135deg, var(--gold-light), var(--gold-pale));
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
            color: rgba(200,146,42,0.55);
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
            color: var(--gold);
            text-shadow: 0 0 12px rgba(200,146,42,0.7);
        }
        .nav-item.active {
            background: linear-gradient(90deg, rgba(200,146,42,0.18), rgba(200,146,42,0.03));
            color: var(--gold);
            border-left-color: var(--gold);
            font-weight: 500;
            box-shadow: inset 0 0 20px rgba(200,146,42,0.08);
        }
        .nav-item.active i {
            color: var(--gold);
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
            color: var(--gold-light);
            font-size: 0.52rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            border-radius: 8px;
            border: 1px solid rgba(200,146,42,0.35);
            margin-left: auto;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        /* ============================================
           MOBILE TOGGLE (BOUTON HAMBURGER)
           ============================================ */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 14px;
            left: 14px;
            z-index: 10001;
            background: linear-gradient(135deg, #0D0D0D, #1A1510);
            color: var(--gold);
            border: 1.5px solid rgba(200,146,42,0.5);
            width: 44px;
            height: 44px;
            border-radius: 12px;
            font-size: 1.4rem;
            box-shadow: 
                0 4px 15px rgba(0,0,0,0.4),
                0 0 25px rgba(200,146,42,0.35);
            cursor: pointer;
            transition: all 0.3s ease;
            align-items: center;
            justify-content: center;
            padding: 0;
            line-height: 1;
        }
        .mobile-toggle:hover,
        .mobile-toggle:active { 
            transform: scale(1.05);
            box-shadow: 
                0 4px 20px rgba(0,0,0,0.5),
                0 0 30px rgba(200,146,42,0.5);
        }
        .mobile-toggle i {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ============================================
           OVERLAY MOBILE
           ============================================ */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.65);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }
        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* ============================================
           MAIN CONTENT
           ============================================ */
        .main {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-page);
            min-height: 100vh;
            min-width: 0;
            transition: margin-left 0.3s ease;
        }

        /* ===== TOPBAR ===== */
        .topbar {
            background: var(--bg-topbar);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 50;
            flex-wrap: wrap;
            transition: background 0.25s ease, border-color 0.25s ease;
        }
        .topbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }
        .topbar-title span { 
            color: var(--gold);
        }
        .topbar-breadcrumb { 
            font-size: 0.72rem; 
            color: var(--text-secondary); 
            margin-top: 2px; 
        }
        .topbar-right { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            flex-wrap: wrap; 
        }

        /* ===== ALERTE COMMANDES ===== */
        .alert-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            font-family: 'Jost', sans-serif;
        }
        .alert-badge.has-orders {
            background: rgba(231, 76, 60, 0.12);
            color: #E74C3C;
            border: 1.5px solid rgba(231, 76, 60, 0.3);
            animation: alertPulse 2s ease-in-out infinite;
        }
        .alert-badge.has-orders .alert-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: #E74C3C;
            border-radius: 50%;
            color: white;
            font-size: 0.7rem;
            flex-shrink: 0;
            box-shadow: 0 0 15px rgba(231,76,60,0.5);
        }
        .alert-badge.has-orders .alert-text {
            color: #E74C3C;
            font-weight: 600;
        }
        .alert-badge.has-orders .alert-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            background: #E74C3C;
            color: white;
            border-radius: 50%;
            font-size: 0.65rem;
            font-weight: 700;
            animation: countPulse 1.5s ease-in-out infinite;
        }
        .alert-badge.no-orders {
            background: var(--border-soft);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            opacity: 0.6;
        }
        .alert-badge.no-orders .alert-icon,
        .alert-badge.no-orders .alert-count { display: none; }
        .alert-badge.no-orders:hover { opacity: 0.9; }

        @keyframes alertPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.2); border-color: rgba(231, 76, 60, 0.3); }
            50% { box-shadow: 0 0 20px 5px rgba(231, 76, 60, 0.15); border-color: rgba(231, 76, 60, 0.6); }
        }
        @keyframes countPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 10px rgba(231, 76, 60, 0.3); }
            50% { transform: scale(1.1); box-shadow: 0 0 20px rgba(231, 76, 60, 0.5); }
        }

        /* ===== BUTTONS ===== */
        .btn-admin {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Jost', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.25s ease;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            background: var(--bg-card);
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-admin:hover { 
            border-color: var(--gold); 
            color: var(--gold); 
            box-shadow: 0 0 15px rgba(200,146,42,0.15);
        }
        .btn-primary { 
            background: linear-gradient(135deg, var(--gold), var(--gold-light)); 
            color: #fff; 
            border-color: var(--gold); 
            box-shadow: 0 4px 15px rgba(200,146,42,0.25);
        }
        .btn-primary:hover { 
            background: linear-gradient(135deg, var(--gold-dark), var(--gold)); 
            color: #fff; 
            box-shadow: 0 8px 25px rgba(200,146,42,0.4);
            transform: translateY(-1px);
        }
        .btn-success { background: #27AE60; color: #fff; border-color: #27AE60; }
        .btn-success:hover { background: #1A7A4A; color: #fff; }
        .btn-danger { background: #E74C3C; color: #fff; border-color: #E74C3C; }
        .btn-danger:hover { background: #C0392B; color: #fff; }
        .btn-warning { background: #F39C12; color: #fff; border-color: #F39C12; }
        .btn-warning:hover { background: #D68910; color: #fff; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary); }
        .btn-outline:hover { border-color: var(--gold); color: var(--gold); }
        .btn-sm { padding: 5px 12px; font-size: 0.68rem; }
        .btn-site { 
            background: linear-gradient(135deg, var(--gold), var(--gold-light)); 
            color: #fff !important; 
            border-color: var(--gold) !important; 
            box-shadow: 0 4px 15px rgba(200,146,42,0.25);
        }
        .btn-site:hover { 
            background: linear-gradient(135deg, var(--gold-dark), var(--gold)) !important; 
            color: #fff !important; 
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(200,146,42,0.4);
        }
        .btn-secondary { 
            background: var(--border-soft); 
            color: var(--text-secondary); 
            border-color: var(--border-color); 
        }
        .btn-secondary:hover { 
            background: var(--border-color); 
            color: var(--text-primary); 
        }

        .btn-small {
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.68rem;
            text-decoration: none;
            transition: all 0.2s;
            font-family: 'Jost', sans-serif;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-small.or { background: rgba(200,146,42,0.12); color: var(--gold); }
        .btn-small.or:hover { background: var(--gold); color: #fff; box-shadow: 0 4px 15px rgba(200,146,42,0.4); }
        .btn-small.green { background: rgba(40,167,69,0.12); color: #28A745; }
        .btn-small.green:hover { background: #28A745; color: #fff; }
        .btn-small.blue { background: rgba(41,128,185,0.12); color: #2980B9; }
        .btn-small.blue:hover { background: #2980B9; color: #fff; }
        .btn-small.red { background: rgba(231,76,60,0.12); color: #E74C3C; }
        .btn-small.red:hover { background: #E74C3C; color: #fff; }
        .btn-small.gray { background: var(--border-soft); color: var(--text-secondary); }
        .btn-small.gray:hover { background: var(--border-color); color: var(--text-primary); }
        .btn-small.orange { background: rgba(230,126,34,0.12); color: #E67E22; }
        .btn-small.orange:hover { background: #E67E22; color: #fff; }

        /* ===== CONTENT ===== */
        .content { 
            padding: 24px 32px; 
            flex: 1; 
            min-width: 0; 
        }
        
        .alert-success {
            background: rgba(39,174,96,0.1);
            border-left: 4px solid #27AE60;
            color: #27AE60;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex; 
            align-items: center; 
            gap: 10px;
            font-size: 0.85rem;
        }
        [data-theme="dark"] .alert-success { color: #6FCF97; }
        
        .alert-danger {
            background: rgba(231,76,60,0.1);
            border-left: 4px solid #E74C3C;
            color: #E74C3C;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex; 
            align-items: center; 
            gap: 10px;
            font-size: 0.85rem;
        }
        
        .alert-info {
            background: rgba(41,128,185,0.1);
            border-left: 4px solid #2980B9;
            color: #2980B9;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex; 
            align-items: center; 
            gap: 10px;
            font-size: 0.85rem;
        }
        [data-theme="dark"] .alert-info { color: #6FB4E0; }

        /* ===== CARDS ===== */
        .card-white {
            background: var(--bg-card);
            border-radius: 14px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            transition: all 0.25s ease;
        }
        .card-white:hover {
            border-color: rgba(200,146,42,0.25);
            box-shadow: var(--shadow-hover), 0 0 25px rgba(200,146,42,0.06);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border-soft);
            flex-wrap: wrap;
            gap: 8px;
            background: linear-gradient(135deg, rgba(200,146,42,0.03), transparent);
        }
        .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 0.98rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-title i { 
            color: var(--gold); 
            font-size: 1rem;
            text-shadow: 0 0 10px rgba(200,146,42,0.4);
        }
        .card-body { padding: 20px 22px; }

        /* ===== WELCOME CARD ===== */
        .welcome-card {
            background: var(--welcome-bg);
            border-radius: 14px;
            padding: 26px 30px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            border: 1.5px solid rgba(200,146,42,0.35);
            box-shadow: 
                0 0 25px rgba(200,146,42,0.15),
                inset 0 0 25px rgba(200,146,42,0.03);
            position: relative;
            overflow: hidden;
        }
        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 60%;
            height: 200%;
            background: radial-gradient(circle, rgba(200,146,42,0.15) 0%, transparent 60%);
            animation: welcomeShine 8s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes welcomeShine {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(-20px, 20px); }
        }
        .welcome-card h2 { 
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem; 
            font-weight: 700; 
            color: #fff; 
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .welcome-card h2 > span:first-of-type { 
            color: var(--gold-light); 
            text-shadow: 0 0 20px rgba(232,181,90,0.6);
        }
        .welcome-card p { 
            font-size: 0.85rem; 
            color: rgba(255,255,255,0.6);
            position: relative;
            z-index: 1;
        }
        .welcome-icon { 
            font-size: 3rem; 
            opacity: 0.4; 
            color: var(--gold-light);
            text-shadow: 0 0 30px rgba(232,181,90,0.7);
            position: relative;
            z-index: 1;
        }
        .role-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 14px;
            font-size: 0.65rem;
            font-weight: 600;
            color: #fff;
            border: 1px solid rgba(200,146,42,0.4);
            box-shadow: 0 0 12px rgba(200,146,42,0.3);
        }

        /* ===== STATISTIQUES ===== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            min-width: 0;
            position: relative;
            overflow: hidden;
        }
        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--gold), var(--gold-light));
            opacity: 0;
            transition: opacity 0.3s;
        }
        .stat-box:hover { 
            transform: translateY(-3px); 
            border-color: rgba(200,146,42,0.4);
            box-shadow: var(--shadow-hover), 0 0 25px rgba(200,146,42,0.1);
        }
        .stat-box:hover::before { opacity: 1; }
        .stat-box-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }
        .stat-box-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--text-secondary);
        }
        .stat-icon {
            width: 42px; 
            height: 42px;
            border-radius: 12px;
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .ic-or { background: rgba(200,146,42,0.12); color: var(--gold); }
        .ic-green { background: rgba(27,122,74,0.12); color: #1A7A4A; }
        .ic-blue { background: rgba(41,128,185,0.12); color: #2980B9; }
        .ic-purple { background: rgba(13,13,13,0.08); color: #0D0D0D; }
        [data-theme="dark"] .ic-purple { background: rgba(255,255,255,0.08); color: #E8ECF0; }
        .ic-red { background: rgba(231,76,60,0.12); color: #E74C3C; }
        .ic-gold { background: rgba(200,146,42,0.18); color: var(--gold); }
        .ic-orange { background: rgba(230,126,34,0.12); color: #E67E22; }
        
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
        }
        .stat-lbl { 
            font-size: 0.7rem; 
            color: var(--text-secondary); 
        }

        /* ===== GRILLE DASHBOARD ===== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* ===== DONUT ===== */
        .donut-card-body { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
            flex-wrap: wrap; 
            justify-content: center; 
        }
        .donut-wrap { 
            position: relative; 
            width: 150px; 
            height: 150px; 
            flex-shrink: 0; 
        }
        .donut-center {
            position: absolute; 
            inset: 0;
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center;
        }
        .donut-center .donut-total { 
            font-family: 'Playfair Display', serif; 
            font-size: 1.15rem; 
            font-weight: 700; 
            color: var(--text-primary); 
        }
        .donut-center .donut-sub { 
            font-size: 0.6rem; 
            color: var(--text-secondary); 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }
        .donut-legend { 
            display: flex; 
            flex-direction: column; 
            gap: 10px; 
            min-width: 0; 
        }
        .donut-legend-item { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 0.78rem; 
            color: var(--text-primary); 
        }
        .donut-legend-dot { 
            width: 10px; 
            height: 10px; 
            border-radius: 50%; 
            flex-shrink: 0;
            box-shadow: 0 0 8px currentColor;
        }
        .donut-legend-pct { 
            margin-left: auto; 
            font-weight: 700; 
            color: var(--text-secondary); 
            font-size: 0.72rem; 
        }

        /* ===== HBAR ===== */
        .hbar-item { margin-bottom: 14px; }
        .hbar-item:last-child { margin-bottom: 0; }
        .hbar-top { 
            display: flex; 
            justify-content: space-between; 
            font-size: 0.8rem; 
            color: var(--text-primary); 
            margin-bottom: 6px; 
            gap: 8px; 
        }
        .hbar-top span:last-child { 
            color: var(--text-secondary); 
            font-size: 0.72rem; 
            white-space: nowrap; 
        }
        .hbar-track { 
            height: 8px; 
            background: var(--border-soft); 
            border-radius: 10px; 
            overflow: hidden; 
        }
        .hbar-fill { 
            height: 100%; 
            border-radius: 10px; 
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            box-shadow: 0 0 10px rgba(200,146,42,0.5);
            transition: width 0.8s ease;
        }

        /* ===== BADGES STATUTS ===== */
        .badge-statut {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.62rem;
            font-weight: 600;
        }
        .statut-en_attente { background: rgba(230,126,34,0.12); color: #E67E22; }
        .statut-confirmee { background: rgba(46,125,50,0.12); color: #2E7D32; }
        .statut-en_preparation { background: rgba(230,126,34,0.12); color: #E67E22; }
        .statut-en_livraison { background: rgba(21,101,192,0.12); color: #1565C0; }
        .statut-livree { background: rgba(26,122,74,0.12); color: #1A7A4A; }
        .statut-terminee { background: rgba(26,122,74,0.12); color: #1A7A4A; }
        .statut-annulee { background: rgba(198,40,40,0.12); color: #C62828; }

        .badge-status {
            display: inline-flex; 
            align-items: center; 
            gap: 4px;
            padding: 3px 12px; 
            border-radius: 20px;
            font-size: 0.62rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }
        .badge-status.active { background: rgba(46,125,50,0.12); color: #2E7D32; }
        .badge-status.inactive { background: rgba(198,40,40,0.12); color: #C62828; }
        .badge-status.expired { background: var(--border-soft); color: var(--text-secondary); }

        .rank-badge {
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            width: 30px; 
            height: 30px; 
            border-radius: 50%;
            font-weight: 700; 
            font-size: 0.72rem;
        }
        .rank-1 { background: linear-gradient(135deg, #FFD700, #F9A825); color: #0A0A0F; box-shadow: 0 0 15px rgba(255,215,0,0.5); }
        .rank-2 { background: linear-gradient(135deg, #C0C0C0, #9E9E9E); color: #0A0A0F; box-shadow: 0 0 15px rgba(192,192,192,0.4); }
        .rank-3 { background: linear-gradient(135deg, #CD7F32, #A67B5B); color: #fff; box-shadow: 0 0 15px rgba(205,127,50,0.4); }
        .rank-other { background: var(--border-soft); color: var(--text-secondary); }

        .badge-local {
            background: rgba(200,146,42,0.1); 
            color: var(--gold);
            padding: 3px 12px; 
            border-radius: 20px; 
            font-size: 0.62rem; 
            font-weight: 600; 
            display: inline-block;
            border: 1px solid rgba(200,146,42,0.2);
        }
        .badge-nb-reservations {
            display: inline-block; 
            background: linear-gradient(135deg, var(--gold), var(--gold-light)); 
            color: #fff;
            border-radius: 50%; 
            padding: 2px 10px; 
            font-size: 0.62rem; 
            font-weight: 700; 
            min-width: 28px; 
            text-align: center;
            box-shadow: 0 0 12px rgba(200,146,42,0.4);
        }

        .stock-badge { 
            display: inline-block; 
            padding: 3px 12px; 
            border-radius: 20px; 
            font-size: 0.62rem; 
            font-weight: 700; 
        }
        .stock-rupture { background: rgba(198,40,40,0.12); color: #C62828; }
        .stock-alerte { background: rgba(133,100,4,0.12); color: #856404; }
        .stock-normal { background: rgba(26,122,74,0.12); color: #1A7A4A; }
        .stock-eleve { background: rgba(21,101,192,0.12); color: #1565C0; }

        /* ===== TABLES ===== */
        .table-container { 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch; 
        }
        .table-commandes, .table-produits, .table-achats {
            width: 100%; 
            border-collapse: collapse; 
            font-size: 0.82rem;
        }
        .table-commandes th, .table-produits th, .table-achats th {
            text-align: left; 
            padding: 12px 14px;
            background: linear-gradient(135deg, #0D0D0D, #1A1510);
            color: rgba(255,255,255,0.8);
            font-weight: 600; 
            font-size: 0.68rem; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            white-space: nowrap;
        }
        .table-commandes td, .table-produits td, .table-achats td {
            padding: 12px 14px; 
            border-bottom: 1px solid var(--border-soft);
            vertical-align: middle; 
            color: var(--text-primary);
        }
        .table-commandes tr:hover, .table-produits tr:hover, .table-achats tr:hover { 
            background: rgba(200,146,42,0.03); 
        }
        .table-commandes .actions, .table-produits .actions, .table-achats .actions { 
            display: flex; 
            gap: 4px; 
            flex-wrap: wrap; 
        }
        .table-commandes .actions .btn-small, 
        .table-produits .actions .btn-small, 
        .table-achats .actions .btn-small { 
            font-size: 0.62rem; 
            padding: 3px 8px; 
        }
        .text-muted { color: var(--text-secondary); }
        .fw-600 { font-weight: 600; }
        .text-gold { color: var(--gold); }

        /* ===== FILTER CARD ===== */
        .filter-card { 
            background: var(--bg-card); 
            border-radius: 14px; 
            padding: 20px 24px; 
            border: 1px solid var(--border-color); 
            margin-bottom: 24px; 
        }
        .filter-card .form-label { 
            font-weight: 600; 
            font-size: 0.72rem; 
            text-transform: uppercase; 
            letter-spacing: 0.8px; 
            color: var(--text-secondary); 
        }
        .filter-card .form-select {
            border-radius: 8px; 
            border: 1.5px solid var(--border-color); 
            padding: 10px 14px;
            font-size: 0.85rem; 
            width: 100%; 
            font-family: 'Jost', sans-serif;
            background: var(--input-bg); 
            color: var(--text-primary);
        }
        .filter-card .form-select:focus { 
            border-color: var(--gold); 
            box-shadow: 0 0 0 3px rgba(200,146,42,0.1); 
            outline: none; 
        }

        /* ===== SYNC INFO ===== */
        .sync-info {
            background: rgba(41,128,185,0.08); 
            border: 1px solid rgba(41,128,185,0.2); 
            border-radius: 10px;
            padding: 12px 18px; 
            margin-bottom: 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 10px;
        }

        /* ===== VIDEO PREVIEW ===== */
        .video-preview { 
            width: 130px; 
            height: 75px; 
            background: #1A1A1A; 
            border-radius: 8px; 
            overflow: hidden; 
            position: relative; 
            flex-shrink: 0; 
        }
        .video-preview video, .video-preview iframe { 
            width: 100%; 
            height: 100%; 
            border: none; 
            object-fit: cover; 
        }
        .video-preview iframe { pointer-events: none; }

        /* ===== FORM STOCK ===== */
        .form-stock { 
            display: flex; 
            gap: 6px; 
            align-items: center; 
            flex-wrap: wrap; 
            justify-content: center; 
        }
        .stock-input {
            width: 70px; 
            padding: 6px 10px; 
            border: 1.5px solid var(--border-color); 
            border-radius: 6px;
            font-size: 0.82rem; 
            font-family: 'Jost', sans-serif; 
            text-align: center;
            background: var(--input-bg); 
            color: var(--text-primary);
        }
        .stock-input:focus { 
            outline: none; 
            border-color: var(--gold); 
            box-shadow: 0 0 0 3px rgba(200,146,42,0.1); 
        }
        .btn-update {
            padding: 6px 14px; 
            border-radius: 6px; 
            border: none; 
            background: linear-gradient(135deg, var(--gold), var(--gold-light)); 
            color: #fff;
            font-weight: 600; 
            font-size: 0.82rem; 
            cursor: pointer; 
            transition: all 0.2s;
            font-family: 'Jost', sans-serif; 
            display: inline-flex; 
            align-items: center; 
            gap: 4px;
        }
        .btn-update:hover { 
            background: linear-gradient(135deg, var(--gold-dark), var(--gold)); 
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(200,146,42,0.4);
        }

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%;
            background: rgba(0,0,0,0.6); 
            z-index: 9999; 
            justify-content: center; 
            align-items: center; 
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: var(--bg-card); 
            border-radius: 20px; 
            max-width: 750px; 
            width: 100%; 
            max-height: 90vh;
            overflow-y: auto; 
            padding: 32px; 
            box-shadow: 
                0 20px 60px rgba(0,0,0,0.4),
                0 0 40px rgba(200,146,42,0.15);
            animation: modalIn 0.3s ease;
            color: var(--text-primary);
            border: 1.5px solid rgba(200,146,42,0.25);
        }
        @keyframes modalIn { 
            from { opacity: 0; transform: scale(0.9) translateY(20px); } 
            to { opacity: 1; transform: scale(1) translateY(0); } 
        }
        .modal-close { 
            float: right; 
            background: none; 
            border: none; 
            font-size: 1.8rem; 
            cursor: pointer; 
            color: var(--text-secondary); 
            transition: all 0.3s; 
            line-height: 1; 
        }
        .modal-close:hover { 
            color: var(--gold); 
            transform: rotate(90deg); 
        }
        .modal-title { 
            font-family: 'Playfair Display', serif; 
            font-size: 1.35rem; 
            color: var(--text-primary); 
            margin-bottom: 10px; 
        }
        .modal-title span { color: var(--gold); }
        .modal-subtitle { 
            font-size: 0.85rem; 
            color: var(--text-secondary); 
            margin-bottom: 16px; 
            padding-bottom: 12px; 
            border-bottom: 1px solid var(--border-soft); 
        }

        .reservation-item { 
            background: var(--border-soft); 
            border-radius: 10px; 
            padding: 14px 18px; 
            margin-bottom: 10px; 
            border-left: 3px solid var(--gold); 
        }
        .reservation-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 8px; 
        }
        .reservation-date { 
            font-size: 0.75rem; 
            color: var(--text-secondary); 
        }
        .reservation-details { 
            margin-top: 6px; 
            font-size: 0.85rem; 
            color: var(--text-primary); 
        }
        .statut-actions { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 4px; 
            margin-top: 10px; 
        }
        .btn-statut {
            padding: 4px 12px; 
            border-radius: 6px; 
            font-size: 0.62rem; 
            font-weight: 600; 
            text-decoration: none;
            transition: all 0.2s; 
            display: inline-flex; 
            align-items: center; 
            gap: 4px; 
            border: 1px solid transparent;
        }
        .btn-statut:hover { 
            transform: scale(0.95); 
            opacity: 0.85; 
        }
        .btn-statut.statut-en_attente { background: rgba(230,126,34,0.12); color: #E67E22; }
        .btn-statut.statut-confirmee { background: rgba(46,125,50,0.12); color: #2E7D32; }
        .btn-statut.statut-terminee { background: rgba(26,122,74,0.12); color: #1A7A4A; }
        .btn-statut.statut-annulee { background: rgba(198,40,40,0.12); color: #721C24; }

        /* ===== EMPTY STATE ===== */
        .empty-state { 
            text-align: center; 
            padding: 30px; 
            color: var(--text-secondary); 
        }
        .empty-state i { 
            font-size: 2rem; 
            display: block; 
            margin-bottom: 8px; 
            color: var(--border-color); 
        }
        .empty-state p { 
            margin: 0; 
            font-size: 0.85rem; 
        }

        /* ============================================
           RESPONSIVE — TABLETTE
           ============================================ */
        @media (max-width: 1024px) and (min-width: 769px) {
            .sidebar { width: 220px; }
            .main { margin-left: 220px; }
            .brand-logo { font-size: 0.95rem; letter-spacing: 2px; }
            .nav-item { font-size: 0.78rem; padding: 9px 12px; }
            .nav-item i { font-size: 0.9rem; width: 18px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 900px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .donut-card-body { flex-direction: column; }
        }

        /* ============================================
           RESPONSIVE — MOBILE
           ============================================ */
        @media (max-width: 768px) {
            /* Afficher le bouton hamburger */
            .mobile-toggle { 
                display: flex !important;
            }
            
            /* Sidebar cachée par défaut */
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
                box-shadow: 5px 0 30px rgba(0,0,0,0.5);
                z-index: 1000;
            }
            
            .sidebar.open {
                transform: translateX(0);
                box-shadow: 
                    5px 0 40px rgba(0,0,0,0.6),
                    0 0 50px rgba(200,146,42,0.3);
            }
            
            /* Main pleine largeur */
            .main { 
                margin-left: 0;
            }
            
            .content { 
                padding: 14px; 
            }
            
            /* Topbar : espace pour le bouton */
            .topbar {
                padding: 14px 14px 14px 68px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .topbar-title { 
                font-size: 1.15rem; 
            }
            .topbar-breadcrumb { 
                font-size: 0.68rem; 
            }
            .topbar-right {
                width: 100%;
                flex-wrap: wrap;
                gap: 8px;
            }
            .topbar-right .btn-admin,
            .topbar-right .alert-badge {
                flex: 1 1 auto;
                justify-content: center;
                font-size: 0.72rem;
                padding: 8px 12px;
                white-space: nowrap;
            }
            .topbar-right .alert-badge .alert-text { 
                white-space: normal; 
            }

            /* Stats */
            .stats-row { 
                grid-template-columns: 1fr 1fr; 
                gap: 10px; 
            }
            .stat-box { 
                padding: 14px 16px; 
                gap: 10px; 
                border-radius: 12px; 
            }
            .stat-icon { 
                width: 34px; 
                height: 34px; 
                font-size: 0.95rem; 
                border-radius: 10px; 
            }
            .stat-val { 
                font-size: 1.2rem; 
            }
            .stat-lbl { 
                font-size: 0.62rem; 
            }
            .stat-box-label { 
                font-size: 0.58rem; 
            }

            /* Welcome card */
            .welcome-card { 
                padding: 18px; 
                flex-direction: column; 
                align-items: flex-start; 
            }
            .welcome-card h2 { 
                font-size: 1.05rem; 
            }
            .welcome-icon { 
                display: none; 
            }

            /* Cards */
            .card-header { 
                padding: 14px 18px; 
            }
            .card-body { 
                padding: 16px 18px; 
            }
            .card-title { 
                font-size: 0.88rem; 
            }

            /* Tableaux : scroll horizontal */
            .table-container {
                -webkit-overflow-scrolling: touch;
                margin: 0 -18px;
                padding: 0 18px;
                width: calc(100% + 36px);
            }
            .table-commandes, 
            .table-produits, 
            .table-achats { 
                min-width: 640px; 
            }

            /* Donut */
            .donut-wrap { 
                width: 130px; 
                height: 130px; 
            }
            .donut-legend { 
                width: 100%; 
            }

            /* Video */
            .video-preview { 
                width: 70px; 
                height: 40px; 
            }
            
            /* Alertes */
            .alert-badge.has-orders .alert-icon { 
                width: 22px; 
                height: 22px; 
                font-size: 0.55rem; 
            }
            .alert-badge.has-orders .alert-count { 
                width: 16px; 
                height: 16px; 
                font-size: 0.5rem; 
            }
        }

        /* ============================================
           PETIT MOBILE
           ============================================ */
        @media (max-width: 480px) {
            .stats-row { 
                grid-template-columns: 1fr; 
            }
            .stat-box { 
                flex-direction: row; 
                align-items: center; 
            }
            .stat-box-top { 
                flex: 1; 
            }
            .topbar-right { 
                flex-direction: column; 
            }
            .topbar-right .btn-admin,
            .topbar-right .alert-badge { 
                width: 100%; 
            }
            .content { 
                padding: 12px; 
            }
            .card-body { 
                padding: 14px; 
            }
            .donut-card-body { 
                align-items: center; 
            }
            .hbar-top { 
                font-size: 0.72rem; 
            }
            .mobile-toggle {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
                top: 12px;
                left: 12px;
            }
            .topbar {
                padding: 12px 12px 12px 62px;
            }
        }
    </style>
</head>
<body>

<!-- ===== BOUTON HAMBURGER ===== -->
<button class="mobile-toggle" id="mobileToggle" type="button" aria-label="Ouvrir le menu">
    <i class="bi bi-list"></i>
</button>

<!-- ===== OVERLAY ===== -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>