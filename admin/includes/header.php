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
    'super_admin' => '#8E44AD',
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">

    <!-- Anti-flash du thème — avant le CSS pour éviter le clignotement clair/sombre -->
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        /* ============================================
           VARIABLES DE THÈME (clair / sombre)
           ============================================ */
        :root {
            --bg-page: #F5F7FA;
            --bg-card: #FFFFFF;
            --bg-topbar: #FFFFFF;
            --text-primary: #1A2C3E;
            --text-secondary: #8A99AA;
            --border-color: #E8ECF0;
            --border-soft: #F0F2F5;
            --accent: #C8922A;
            --accent-dark: #9A6E1A;
            --shadow-card: 0 1px 3px rgba(0,0,0,0.03);
            --input-bg: #FFFFFF;
            --welcome-bg: linear-gradient(135deg, #FEF9F0 0%, #FFFFFF 100%);
        }
        [data-theme="dark"] {
            --bg-page: #12151A;
            --bg-card: #1B2028;
            --bg-topbar: #1B2028;
            --text-primary: #E8ECF0;
            --text-secondary: #96A3B0;
            --border-color: #2A2F3A;
            --border-soft: #232833;
            --accent: #D9A94A;
            --accent-dark: #C8922A;
            --shadow-card: 0 1px 3px rgba(0,0,0,0.4);
            --input-bg: #232833;
            --welcome-bg: linear-gradient(135deg, rgba(200,146,42,0.08) 0%, #1B2028 100%);
        }

        *, *::before, *::after { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        
        html { -webkit-text-size-adjust: 100%; overflow-x: hidden; }
        body { 
            font-family: 'Jost', sans-serif; 
            background: var(--bg-page); 
            color: var(--text-primary); 
            display: flex; 
            min-height: 100vh; 
            transition: background 0.25s ease, color 0.25s ease;
            overflow-x: hidden;
            max-width: 100vw;
        }

        /* ===== SIDEBAR (toujours sombre/dorée) ===== */
        .sidebar {
            width: 260px;
            background: #0D0D0D;
            border-right: 1px solid rgba(200,146,42,0.12);
            position: fixed;
            top: 0; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(200,146,42,0.3); border-radius: 10px; }

        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(200,146,42,0.1);
        }
        .brand-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: #C8922A;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .brand-sub {
            font-size: 0.55rem;
            color: rgba(255,255,255,0.25);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .admin-user {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 18px;
            padding: 10px 12px;
            background: rgba(200,146,42,0.06);
            border-radius: 8px;
            border: 1px solid rgba(200,146,42,0.1);
        }
        .admin-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            color: #fff;
            font-weight: 600;
            flex-shrink: 0;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.06);
        }
        .admin-name { 
            font-size: 0.8rem; 
            color: #fff; 
            font-weight: 500; 
        }
        .admin-role { 
            font-size: 0.58rem; 
            letter-spacing: 0.5px; 
            text-transform: uppercase; 
        }

        /* ===== TOGGLE CLAIR / SOMBRE ===== */
        .theme-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            width: 100%;
            margin-top: 12px;
            padding: 7px 12px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 30px;
            cursor: pointer;
            color: rgba(255,255,255,0.55);
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }
        .theme-toggle:hover { border-color: rgba(200,146,42,0.35); }
        .theme-toggle-track {
            flex: 1;
            height: 18px;
            background: rgba(255,255,255,0.08);
            border-radius: 20px;
            position: relative;
            margin: 0 6px;
        }
        .theme-toggle-thumb {
            position: absolute;
            top: 2px; left: 2px;
            width: 14px; height: 14px;
            border-radius: 50%;
            background: #C8922A;
            transition: transform 0.25s ease;
        }
        [data-theme="dark"] .theme-toggle-thumb { transform: translateX(18px); }

        .nav-section {
            font-size: 0.55rem;
            color: rgba(255,255,255,0.15);
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 16px 24px 6px;
            font-weight: 600;
        }
        .sidebar nav { 
            flex: 1; 
            padding: 6px 12px; 
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 9px 14px;
            border-radius: 6px;
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 400;
            border-left: 2px solid transparent;
            transition: all 0.2s;
            margin-bottom: 1px;
        }
        .nav-item i { 
            font-size: 0.95rem; 
            width: 18px; 
            text-align: center; 
        }
        .nav-item:hover { 
            color: #fff; 
            background: rgba(200,146,42,0.08); 
            border-left-color: rgba(200,146,42,0.4); 
        }
        .nav-item.active { 
            color: #fff; 
            background: rgba(200,146,42,0.12); 
            border-left-color: #C8922A; 
        }
        .nav-item.active i { 
            color: #C8922A; 
        }
        .nav-item.logout { 
            color: rgba(231,76,60,0.5); 
        }
        .nav-item.logout:hover { 
            color: #E74C3C; 
            background: rgba(231,76,60,0.08); 
            border-left-color: #E74C3C; 
        }
        .nav-item .badge-role {
            margin-left: auto;
            font-size: 0.5rem;
            padding: 1px 8px;
            border-radius: 10px;
            background: rgba(200,146,42,0.15);
            color: #C8922A;
            font-weight: 600;
        }

        /* ===== MOBILE TOGGLE ===== */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 1001;
            background: #0D0D0D;
            color: #C8922A;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            cursor: pointer;
            transition: all 0.3s ease;
            align-items: center;
            justify-content: center;
        }
        .mobile-toggle:hover { transform: scale(1.05); }

        /* ===== OVERLAY MOBILE (assombrit derrière la sidebar ouverte) ===== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 99;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .sidebar-overlay.show { display: block; opacity: 1; }

        /* ===== MAIN CONTENT ===== */
        .main {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-page);
            min-height: 100vh;
            min-width: 0; /* important pour éviter le débordement horizontal sur mobile */
        }
        .topbar {
            background: var(--bg-topbar);
            border-bottom: 1px solid var(--border-color);
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 50;
            flex-wrap: wrap;
        }
        .topbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .topbar-title span { 
            color: var(--accent); 
        }
        .topbar-breadcrumb { 
            font-size: 0.7rem; 
            color: var(--text-secondary); 
            margin-top: 1px; 
        }
        .topbar-right { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            flex-wrap: wrap; 
        }

        /* ===== ALERTE COMMANDES EN ATTENTE ===== */
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
        .alert-badge.no-orders .alert-icon { display: none; }
        .alert-badge.no-orders .alert-count { display: none; }
        .alert-badge.no-orders:hover { opacity: 0.8; }

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
            font-size: 0.72rem;
            font-weight: 600;
            padding: 7px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            background: var(--bg-card);
            cursor: pointer;
        }
        .btn-admin:hover { border-color: var(--accent); color: var(--accent); }
        .btn-primary { background: #C8922A; color: #fff; border-color: #C8922A; }
        .btn-primary:hover { background: #9A6E1A; color: #fff; }
        .btn-success { background: #27AE60; color: #fff; border-color: #27AE60; }
        .btn-success:hover { background: #1A7A4A; color: #fff; }
        .btn-danger { background: #E74C3C; color: #fff; border-color: #E74C3C; }
        .btn-danger:hover { background: #C0392B; color: #fff; }
        .btn-warning { background: #F39C12; color: #fff; border-color: #F39C12; }
        .btn-warning:hover { background: #D68910; color: #fff; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary); }
        .btn-outline:hover { border-color: var(--accent); color: var(--accent); }
        .btn-sm { padding: 4px 10px; font-size: 0.65rem; }
        .btn-site { background: #C8922A; color: #fff !important; border-color: #C8922A !important; }
        .btn-site:hover { background: #9A6E1A !important; color: #fff !important; transform: none; }
        .btn-secondary { background: var(--border-soft); color: var(--text-secondary); border-color: var(--border-color); }
        .btn-secondary:hover { background: var(--border-color); color: var(--text-primary); }

        .btn-small {
            padding: 3px 12px;
            border-radius: 16px;
            font-size: 0.65rem;
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
        .btn-small.or { background: rgba(200,146,42,0.12); color: #C8922A; }
        .btn-small.or:hover { background: #C8922A; color: #fff; }
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
        .content { padding: 24px 32px; flex: 1; min-width: 0; }
        .alert-success {
            background: rgba(39,174,96,0.1);
            border-left: 4px solid #27AE60;
            color: #27AE60;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
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
            display: flex; align-items: center; gap: 10px;
            font-size: 0.85rem;
        }
        .alert-info {
            background: rgba(41,128,185,0.1);
            border-left: 4px solid #2980B9;
            color: #2980B9;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
            font-size: 0.85rem;
        }
        [data-theme="dark"] .alert-info { color: #6FB4E0; }

        /* ===== CARDS ===== */
        .card-white {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 22px;
            border-bottom: 1px solid var(--border-soft);
            flex-wrap: wrap;
            gap: 8px;
        }
        .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-title i { color: var(--accent); font-size: 1rem; }
        .card-body { padding: 18px 22px; }

        /* ===== WELCOME CARD ===== */
        .welcome-card {
            background: var(--welcome-bg);
            border-radius: 12px;
            padding: 22px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            border: 1px solid rgba(200,146,42,0.12);
        }
        .welcome-card h2 { font-size: 1.2rem; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
        .welcome-card h2 span { color: var(--accent); }
        .welcome-card p { font-size: 0.82rem; color: var(--text-secondary); }
        .welcome-icon { font-size: 2rem; opacity: 0.5; color: var(--accent); }
        .role-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 600;
            color: #fff;
            margin-left: 8px;
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
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
            min-width: 0;
        }
        .stat-box:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .stat-box-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }
        .stat-box-label {
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--text-secondary);
        }
        .stat-icon {
            width: 40px; height: 40px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .ic-or { background: rgba(200,146,42,0.12); color: #C8922A; }
        .ic-green { background: rgba(27,122,74,0.12); color: #1A7A4A; }
        .ic-blue { background: rgba(41,128,185,0.12); color: #2980B9; }
        .ic-purple { background: rgba(142,68,173,0.12); color: #8E44AD; }
        .ic-red { background: rgba(231,76,60,0.12); color: #E74C3C; }
        .ic-gold { background: rgba(200,146,42,0.18); color: #C8922A; }
        .ic-orange { background: rgba(230,126,34,0.12); color: #E67E22; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.55rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
        }
        .stat-lbl { font-size: 0.68rem; color: var(--text-secondary); }

        /* ===== GRILLE DASHBOARD (donut, top catégories, réservations...) ===== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* ===== DONUT CHART CARD ===== */
        .donut-card-body { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; justify-content: center; }
        .donut-wrap { position: relative; width: 150px; height: 150px; flex-shrink: 0; }
        .donut-center {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .donut-center .donut-total { font-family: 'Playfair Display', serif; font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
        .donut-center .donut-sub { font-size: 0.55rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
        .donut-legend { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
        .donut-legend-item { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: var(--text-primary); }
        .donut-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .donut-legend-pct { margin-left: auto; font-weight: 700; color: var(--text-secondary); font-size: 0.7rem; }

        /* ===== BARRES HORIZONTALES (top catégories) ===== */
        .hbar-item { margin-bottom: 14px; }
        .hbar-item:last-child { margin-bottom: 0; }
        .hbar-top { display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--text-primary); margin-bottom: 5px; gap: 8px; }
        .hbar-top span:last-child { color: var(--text-secondary); font-size: 0.7rem; white-space: nowrap; }
        .hbar-track { height: 8px; background: var(--border-soft); border-radius: 10px; overflow: hidden; }
        .hbar-fill { height: 100%; border-radius: 10px; background: linear-gradient(90deg, #C8922A, #E8B55A); }

        /* ===== BADGES ===== */
        .badge-statut {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
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
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 12px; border-radius: 20px;
            font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .badge-status.active { background: rgba(46,125,50,0.12); color: #2E7D32; }
        .badge-status.inactive { background: rgba(198,40,40,0.12); color: #C62828; }
        .badge-status.expired { background: var(--border-soft); color: var(--text-secondary); }

        .rank-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 50%;
            font-weight: 700; font-size: 0.7rem;
        }
        .rank-1 { background: linear-gradient(135deg, #FFD700, #F9A825); color: #0A0A0F; }
        .rank-2 { background: linear-gradient(135deg, #C0C0C0, #9E9E9E); color: #0A0A0F; }
        .rank-3 { background: linear-gradient(135deg, #CD7F32, #A67B5B); color: #fff; }
        .rank-other { background: var(--border-soft); color: var(--text-secondary); }

        .badge-local {
            background: rgba(200,146,42,0.1); color: #C8922A;
            padding: 3px 12px; border-radius: 20px; font-size: 0.6rem; font-weight: 600; display: inline-block;
        }
        .badge-nb-reservations {
            display: inline-block; background: #C8922A; color: #fff;
            border-radius: 50%; padding: 2px 10px; font-size: 0.6rem; font-weight: 700; min-width: 28px; text-align: center;
        }

        .stock-badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 0.6rem; font-weight: 700; }
        .stock-rupture { background: rgba(198,40,40,0.12); color: #C62828; }
        .stock-alerte { background: rgba(133,100,4,0.12); color: #856404; }
        .stock-normal { background: rgba(26,122,74,0.12); color: #1A7A4A; }
        .stock-eleve { background: rgba(21,101,192,0.12); color: #1565C0; }

        /* ===== TABLES ===== */
        .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-commandes, .table-produits, .table-achats {
            width: 100%; border-collapse: collapse; font-size: 0.82rem;
        }
        .table-commandes th, .table-produits th, .table-achats th {
            text-align: left; padding: 10px 14px;
            background: var(--border-soft); color: var(--text-secondary);
            font-weight: 600; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        .table-commandes td, .table-produits td, .table-achats td {
            padding: 10px 14px; border-bottom: 1px solid var(--border-soft);
            vertical-align: middle; color: var(--text-primary);
        }
        .table-commandes tr:hover, .table-produits tr:hover, .table-achats tr:hover { background: var(--border-soft); }
        .table-commandes .actions, .table-produits .actions, .table-achats .actions { display: flex; gap: 4px; flex-wrap: wrap; }
        .table-commandes .actions .btn-small, .table-produits .actions .btn-small, .table-achats .actions .btn-small { font-size: 0.6rem; padding: 2px 8px; }
        .text-muted { color: var(--text-secondary); }
        .fw-600 { font-weight: 600; }
        .text-gold { color: var(--accent); }

        /* ===== FILTER CARD ===== */
        .filter-card { background: var(--bg-card); border-radius: 12px; padding: 20px 24px; border: 1px solid var(--border-color); margin-bottom: 24px; }
        .filter-card .form-label { font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-secondary); }
        .filter-card .form-select {
            border-radius: 8px; border: 1.5px solid var(--border-color); padding: 9px 14px;
            font-size: 0.85rem; width: 100%; font-family: 'Jost', sans-serif;
            background: var(--input-bg); color: var(--text-primary);
        }
        .filter-card .form-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(200,146,42,0.1); outline: none; }

        /* ===== SYNC INFO ===== */
        .sync-info {
            background: rgba(41,128,185,0.08); border: 1px solid rgba(41,128,185,0.2); border-radius: 10px;
            padding: 12px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
        }

        /* ===== VIDEO PREVIEW ===== */
        .video-preview { width: 130px; height: 75px; background: #1A1A1A; border-radius: 8px; overflow: hidden; position: relative; flex-shrink: 0; }
        .video-preview video, .video-preview iframe { width: 100%; height: 100%; border: none; object-fit: cover; }
        .video-preview iframe { pointer-events: none; }

        /* ===== FORM STOCK ===== */
        .form-stock { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; justify-content: center; }
        .stock-input {
            width: 65px; padding: 5px 8px; border: 1.5px solid var(--border-color); border-radius: 6px;
            font-size: 0.8rem; font-family: 'Jost', sans-serif; text-align: center;
            background: var(--input-bg); color: var(--text-primary);
        }
        .stock-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(200,146,42,0.1); }
        .btn-update {
            padding: 5px 14px; border-radius: 6px; border: none; background: #C8922A; color: #fff;
            font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: all 0.2s;
            font-family: 'Jost', sans-serif; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-update:hover { background: #9A6E1A; transform: scale(1.05); }

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: var(--bg-card); border-radius: 20px; max-width: 750px; width: 100%; max-height: 90vh;
            overflow-y: auto; padding: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalIn 0.3s ease;
            color: var(--text-primary);
        }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.9) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-close { float: right; background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--text-secondary); transition: all 0.3s; line-height: 1; }
        .modal-close:hover { color: var(--text-primary); transform: rotate(90deg); }
        .modal-title { font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--text-primary); margin-bottom: 10px; }
        .modal-title span { color: var(--accent); }
        .modal-subtitle { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border-soft); }

        .reservation-item { background: var(--border-soft); border-radius: 10px; padding: 14px 18px; margin-bottom: 10px; border-left: 3px solid #C8922A; }
        .reservation-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .reservation-date { font-size: 0.75rem; color: var(--text-secondary); }
        .reservation-details { margin-top: 6px; font-size: 0.82rem; color: var(--text-primary); }
        .statut-actions { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 10px; }
        .btn-statut {
            padding: 3px 12px; border-radius: 6px; font-size: 0.6rem; font-weight: 600; text-decoration: none;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent;
        }
        .btn-statut:hover { transform: scale(0.95); opacity: 0.8; }
        .btn-statut.statut-en_attente { background: rgba(230,126,34,0.12); color: #E67E22; }
        .btn-statut.statut-confirmee { background: rgba(46,125,50,0.12); color: #2E7D32; }
        .btn-statut.statut-terminee { background: rgba(26,122,74,0.12); color: #1A7A4A; }
        .btn-statut.statut-annulee { background: rgba(198,40,40,0.12); color: #721C24; }

        /* ===== EMPTY STATE ===== */
        .empty-state { text-align: center; padding: 30px; color: var(--text-secondary); }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 8px; color: var(--border-color); }
        .empty-state p { margin: 0; font-size: 0.85rem; }

        /* ============================================
           RESPONSIVE — TOUT LE DASHBOARD
           ============================================ */
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .dashboard-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 900px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .donut-card-body { flex-direction: column; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; width: 280px; }
            .sidebar.open { transform: translateX(0); box-shadow: 20px 0 50px rgba(0,0,0,0.4); }
            .mobile-toggle { display: flex; }
            .main { margin-left: 0; }
            .content { padding: 14px; }
            .stats-row { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-box { padding: 12px 14px; gap: 8px; border-radius: 12px; }
            .stat-icon { width: 32px; height: 32px; font-size: 0.9rem; border-radius: 10px; }
            .stat-val { font-size: 1.15rem; }
            .stat-lbl { font-size: 0.6rem; }
            .stat-box-label { font-size: 0.55rem; }

            /* Topbar : laisse la place au bouton mobile flottant + empile proprement */
            .topbar {
                padding: 12px 14px 12px 60px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .topbar-title { font-size: 1.05rem; }
            .topbar-breadcrumb { font-size: 0.62rem; }
            .topbar-right {
                width: 100%;
                gap: 8px;
            }
            .topbar-right .btn-admin,
            .topbar-right .alert-badge {
                flex: 1 1 auto;
                justify-content: center;
                font-size: 0.68rem;
                padding: 7px 10px;
                white-space: nowrap;
            }
            .topbar-right .alert-badge .alert-text { white-space: normal; }

            .welcome-card { padding: 16px; flex-direction: column; align-items: flex-start; }
            .welcome-card h2 { font-size: 1rem; }
            .welcome-icon { display: none; }

            .card-header { padding: 12px 16px; }
            .card-body { padding: 14px 16px; }
            .card-title { font-size: 0.85rem; }

            /* Tableaux : scroll horizontal fluide, jamais de débordement de page */
            .table-container {
                -webkit-overflow-scrolling: touch;
                margin: 0 -16px;
                padding: 0 16px;
                width: calc(100% + 32px);
            }
            .table-commandes, .table-produits, .table-achats { min-width: 640px; }

            /* Donut chart : plus compact, centré */
            .donut-wrap { width: 130px; height: 130px; }
            .donut-legend { width: 100%; }

            /* Graphique Chart.js : hauteur réduite */
            #ventesChart { max-height: 220px !important; }

            .video-preview { width: 70px; height: 40px; }
            .alert-badge.has-orders .alert-icon { width: 22px; height: 22px; font-size: 0.55rem; }
            .alert-badge.has-orders .alert-count { width: 16px; height: 16px; font-size: 0.5rem; }
        }
        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr; }
            .stat-box { flex-direction: row; align-items: center; }
            .stat-box-top { flex: 1; }
            .topbar-right { flex-direction: column; }
            .topbar-right .btn-admin,
            .topbar-right .alert-badge { width: 100%; }
            .content { padding: 10px; }
            .card-body { padding: 12px; }
            .donut-card-body { align-items: center; }
            .hbar-top { font-size: 0.7rem; }
        }
    </style>
</head>
<body>

<!-- ===== MOBILE TOGGLE ===== -->
<button class="mobile-toggle" id="mobileToggle" aria-label="Toggle sidebar">
    <i class="bi bi-list"></i>
</button>

<!-- ===== OVERLAY MOBILE ===== -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('mobileToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function closeSidebar() {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('show');
    }
    function openSidebar() {
        sidebar?.classList.add('open');
        overlay?.classList.add('show');
    }

    if (toggle && sidebar) {
        toggle.addEventListener('click', function() {
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });
    }
    overlay?.addEventListener('click', closeSidebar);

    // Fermer la sidebar automatiquement si on clique un lien (mobile)
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && e.target.closest('.sidebar .nav-item')) {
            closeSidebar();
        }
    });
});

function confirmDelete(message) {
    return confirm(message || 'Êtes-vous sûr de vouloir effectuer cette action ?');
}
</script>