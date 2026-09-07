<?php
// ============================================
// JOURNAL D'AUDIT - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';
require_once '../includes/functions_securite.php';

// ============================================
// VÉRIFICATION DE CONNEXION
// ============================================
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// ============================================
// RÉCUPÉRATION DES INFOS ADMIN
// ============================================
$admin_info = getAdminInfo();
$admin_role = $admin_info['role'] ?? 'admin';
$admin_nom = $admin_info['nom'] ?? 'Admin';
$admin_id = $admin_info['id'] ?? 0;

// 🔒 SEUL LE SUPER ADMIN PEUT ACCÉDER
if ($admin_role !== 'super_admin') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Journal d\'audit';

// ============================================
// CONNEXION À LA BASE DE DONNÉES
// ============================================
$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données.");
}

// ============================================
// FILTRES
// ============================================
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$admin_id_filter = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 200;

// ============================================
// REQUÊTE DE RÉCUPÉRATION DES LOGS (TABLE logs_actions)
// ============================================
$sql = "SELECT * FROM logs_actions WHERE 1=1";
$params = [];

if (!empty($action)) {
    $sql .= " AND action LIKE ?";
    $params[] = "%$action%";
}

if ($admin_id_filter > 0) {
    $sql .= " AND admin_id = ?";
    $params[] = $admin_id_filter;
}

if (!empty($date_debut)) {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $date_fin;
}

$sql .= " ORDER BY created_at DESC";

// Pagination
$count_sql = str_replace("SELECT *", "SELECT COUNT(*)", $sql);
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_logs = $stmt_count->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

$offset = ($page - 1) * $per_page;
$sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ============================================
// RÉCUPÉRER LA LISTE DES ADMINS POUR LE FILTRE
// ============================================
$admins = $pdo->query("SELECT id, nom, email FROM admin ORDER BY nom")->fetchAll();

// ============================================
// STATISTIQUES (TABLE logs_actions)
// ============================================
$total_actions = $pdo->query("SELECT COUNT(*) FROM logs_actions")->fetchColumn();
$actions_jour = $pdo->query("
    SELECT COUNT(*) FROM logs_actions 
    WHERE DATE(created_at) = CURDATE()
")->fetchColumn();
$actions_semaine = $pdo->query("
    SELECT COUNT(*) FROM logs_actions 
    WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())
")->fetchColumn();
$actions_mois = $pdo->query("
    SELECT COUNT(*) FROM logs_actions 
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
    AND YEAR(created_at) = YEAR(CURDATE())
")->fetchColumn();

// Actions les plus fréquentes
$actions_frequentes = $pdo->query("
    SELECT action, COUNT(*) as nb 
    FROM logs_actions 
    GROUP BY action 
    ORDER BY nb DESC 
    LIMIT 10
")->fetchAll();

// ============================================
// FONCTION POUR FORMATER LES DATES EN FRANÇAIS
// ============================================
function dateFr($date, $format = 'full') {
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    
    $timestamp = strtotime($date);
    $jour_num = (int)date('w', $timestamp);
    $mois_num = (int)date('m', $timestamp) - 1;
    $jour = (int)date('d', $timestamp);
    $annee = date('Y', $timestamp);
    
    if ($format == 'full') {
        return $jours[$jour_num] . ' ' . $jour . ' ' . $mois[$mois_num] . ' ' . $annee;
    } elseif ($format == 'short') {
        return $jour . ' ' . $mois[$mois_num] . ' ' . $annee;
    } elseif ($format == 'day_month') {
        return $jour . ' ' . $mois[$mois_num];
    } elseif ($format == 'month_year') {
        return $mois[$mois_num] . ' ' . $annee;
    }
    return $date;
}

// ============================================
// FONCTION POUR REGROUPER LES LOGS PAR JOUR
// ============================================
function getLogsGroupedByDay($logs) {
    $groups = [];
    $now = new DateTime();
    $today = $now->format('Y-m-d');
    
    foreach ($logs as $log) {
        $log_date = date('Y-m-d', strtotime($log['created_at']));
        $log_year = date('Y', strtotime($log['created_at']));
        $log_month = date('m', strtotime($log['created_at']));
        
        // Vérifier si c'est aujourd'hui
        if ($log_date == $today) {
            $group_key = 'today';
        } else {
            $group_key = $log_date;
        }
        
        if (!isset($groups[$group_key])) {
            $groups[$group_key] = [];
        }
        $groups[$group_key][] = $log;
    }
    
    // Trier les clés (aujourd'hui en premier, puis les plus récentes)
    uksort($groups, function($a, $b) {
        if ($a == 'today') return -1;
        if ($b == 'today') return 1;
        return strtotime($b) - strtotime($a);
    });
    
    return $groups;
}

// Regrouper les logs par jour
$grouped_logs = getLogsGroupedByDay($logs);

// ============================================
// COULEURS DES ACTIONS
// ============================================
$action_colors = [
    'connexion' => '#2980B9',
    'deconnexion' => '#7F8C8D',
    'ajout' => '#27AE60',
    'modification' => '#F39C12',
    'suppression' => '#E74C3C',
    'validation' => '#8E44AD',
    'export' => '#1ABC9C',
    'import' => '#2ECC71',
];

function getActionColor($action) {
    global $action_colors;
    foreach ($action_colors as $key => $color) {
        if (strpos($action, $key) !== false) {
            return $color;
        }
    }
    return '#6C757D';
}

function getActionIcon($action) {
    if (strpos($action, 'connexion') !== false) return 'bi-box-arrow-in-right';
    if (strpos($action, 'deconnexion') !== false) return 'bi-box-arrow-right';
    if (strpos($action, 'ajout') !== false) return 'bi-plus-circle';
    if (strpos($action, 'modification') !== false || strpos($action, 'modif') !== false) return 'bi-pencil';
    if (strpos($action, 'suppression') !== false || strpos($action, 'suppr') !== false) return 'bi-trash3';
    if (strpos($action, 'validation') !== false) return 'bi-check-circle';
    if (strpos($action, 'export') !== false) return 'bi-file-earmark-arrow-down';
    if (strpos($action, 'import') !== false) return 'bi-file-earmark-arrow-up';
    if (strpos($action, 'paramètre') !== false) return 'bi-gear';
    return 'bi-record-circle';
}

// ============================================
// INCLUSION DU HEADER ET DE LA SIDEBAR
// ============================================
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="main">

    <!-- ===== TOPBAR ===== -->
    <div class="topbar">
        <div>
            <div class="topbar-title">📋 Journal d'<span>audit</span></div>
            <div class="topbar-breadcrumb">Super Admin → Audit</div>
        </div>
        <div class="topbar-right">
            <span style="font-size:0.65rem;background:#8E44AD;color:#fff;padding:4px 14px;border-radius:20px;font-weight:600;">
                <i class="bi bi-shield-fill-check"></i> Super Admin
            </span>
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-purple"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-val"><?= $total_actions ?></div>
                    <div class="stat-lbl">Total actions</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-calendar-day"></i></div>
                <div>
                    <div class="stat-val"><?= $actions_jour ?></div>
                    <div class="stat-lbl">Aujourd'hui</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-calendar-week"></i></div>
                <div>
                    <div class="stat-val"><?= $actions_semaine ?></div>
                    <div class="stat-lbl">Cette semaine</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-calendar-month"></i></div>
                <div>
                    <div class="stat-val"><?= $actions_mois ?></div>
                    <div class="stat-lbl">Ce mois</div>
                </div>
            </div>
        </div>

        <!-- ===== FILTRES - DESIGN AÉRÉ ===== -->
        <div class="filter-card" style="padding:24px 28px;">
            <form method="GET">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;align-items:end;">
                    <!-- Action -->
                    <div>
                        <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:6px;">
                            <i class="bi bi-tag" style="color:#C8922A;"></i> Action
                        </label>
                        <input type="text" name="action" class="form-control" placeholder="ex: connexion, ajout..." 
                               value="<?= htmlspecialchars($action) ?>"
                               style="width:100%;padding:10px 16px;border:1.5px solid #E8ECF0;border-radius:10px;font-size:0.9rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;"
                               onfocus="this.style.borderColor='#C8922A';this.style.boxShadow='0 0 0 3px rgba(200,146,42,0.1)'"
                               onblur="this.style.borderColor='#E8ECF0';this.style.boxShadow='none'">
                    </div>
                    
                    <!-- Administrateur -->
                    <div>
                        <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:6px;">
                            <i class="bi bi-person" style="color:#C8922A;"></i> Administrateur
                        </label>
                        <select name="admin_id" class="form-select"
                                style="width:100%;padding:10px 16px;border:1.5px solid #E8ECF0;border-radius:10px;font-size:0.9rem;font-family:'Jost',sans-serif;background:#fff;transition:border-color 0.3s;"
                                onfocus="this.style.borderColor='#C8922A';this.style.boxShadow='0 0 0 3px rgba(200,146,42,0.1)'"
                                onblur="this.style.borderColor='#E8ECF0';this.style.boxShadow='none'">
                            <option value="0">Tous</option>
                            <?php foreach($admins as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= $admin_id_filter == $a['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['nom']) ?> (<?= htmlspecialchars($a['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Date début -->
                    <div>
                        <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:6px;">
                            <i class="bi bi-calendar3" style="color:#C8922A;"></i> Date début
                        </label>
                        <input type="date" name="date_debut" class="form-control" value="<?= htmlspecialchars($date_debut) ?>"
                               style="width:100%;padding:10px 16px;border:1.5px solid #E8ECF0;border-radius:10px;font-size:0.9rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;"
                               onfocus="this.style.borderColor='#C8922A';this.style.boxShadow='0 0 0 3px rgba(200,146,42,0.1)'"
                               onblur="this.style.borderColor='#E8ECF0';this.style.boxShadow='none'">
                    </div>
                    
                    <!-- Date fin -->
                    <div>
                        <label style="display:block;font-size:0.7rem;font-weight:600;color:#5A6B7A;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:6px;">
                            <i class="bi bi-calendar3" style="color:#C8922A;"></i> Date fin
                        </label>
                        <input type="date" name="date_fin" class="form-control" value="<?= htmlspecialchars($date_fin) ?>"
                               style="width:100%;padding:10px 16px;border:1.5px solid #E8ECF0;border-radius:10px;font-size:0.9rem;font-family:'Jost',sans-serif;transition:border-color 0.3s;"
                               onfocus="this.style.borderColor='#C8922A';this.style.boxShadow='0 0 0 3px rgba(200,146,42,0.1)'"
                               onblur="this.style.borderColor='#E8ECF0';this.style.boxShadow='none'">
                    </div>
                </div>
                
                <!-- Bouton Filtrer -->
                <div style="margin-top:18px;display:flex;gap:12px;justify-content:flex-end;">
                    <?php if($action || $admin_id_filter > 0 || $date_debut || $date_fin): ?>
                        <a href="logs.php" class="btn-admin" style="padding:10px 24px;background:#F0F2F5;color:#5A6B7A;border:1px solid #E8ECF0;border-radius:10px;font-weight:600;text-decoration:none;transition:all 0.3s;" onmouseover="this.style.background='#E0E6ED'" onmouseout="this.style.background='#F0F2F5'">
                            <i class="bi bi-x"></i> Effacer
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn-admin btn-primary" style="padding:10px 32px;border-radius:10px;font-weight:600;font-size:0.9rem;display:flex;align-items:center;gap:8px;">
                        <i class="bi bi-funnel"></i> Filtrer
                    </button>
                </div>
            </form>
        </div>

        <!-- ===== ACTIONS FRÉQUENTES ===== -->
        <?php if(!empty($actions_frequentes)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;padding:14px 20px;background:#FAFBFC;border-radius:12px;border:1px solid #E8ECF0;">
            <?php foreach($actions_frequentes as $af): ?>
                <span style="display:inline-block;padding:4px 16px;border-radius:20px;background:<?= getActionColor($af['action']) ?>15;color:<?= getActionColor($af['action']) ?>;font-size:0.72rem;font-weight:600;border:1px solid <?= getActionColor($af['action']) ?>25;transition:all 0.3s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <i class="bi <?= getActionIcon($af['action']) ?>" style="font-size:0.65rem;"></i>
                    <?= htmlspecialchars($af['action']) ?> <span style="background:<?= getActionColor($af['action']) ?>20;padding:0 8px;border-radius:10px;font-weight:700;"><?= $af['nb'] ?></span>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== LISTE DES LOGS AVEC REGROUPEMENT PAR JOUR ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-list"></i> Journal des actions
                    <span style="font-size:0.65rem;font-weight:normal;background:#F0F2F5;padding:2px 14px;border-radius:12px;margin-left:10px;">
                        <?= $total_logs ?> entrées
                    </span>
                </div>
                <a href="logs.php" class="btn-small gray" style="padding:6px 18px;border-radius:8px;font-size:0.75rem;">
                    <i class="bi bi-arrow-repeat"></i> Rafraîchir
                </a>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if(empty($logs)): ?>
                    <div class="empty-state" style="text-align:center;padding:50px;color:#8A99AA;">
                        <i class="bi bi-clock-history" style="font-size:3rem;display:block;margin-bottom:12px;color:#D5D5D5;"></i>
                        <p style="margin:0;font-size:0.9rem;">Aucune action enregistrée</p>
                        <span style="font-size:0.8rem;color:#bbb;display:block;margin-top:4px;">Les actions des administrateurs apparaîtront ici</span>
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped_logs as $date_key => $day_logs): 
                        if (empty($day_logs)) continue;
                        
                        // Déterminer le libellé de la date
                        if ($date_key == 'today') {
                            $date_label = "Aujourd'hui - " . dateFr(date('Y-m-d'), 'full');
                            $date_icon = 'bi-calendar-day';
                            $date_color = '#2980B9';
                            $badge_label = "Aujourd'hui";
                        } else {
                            $date_label = dateFr($date_key, 'full');
                            $date_icon = 'bi-calendar3';
                            $date_color = '#6C757D';
                            $badge_label = dateFr($date_key, 'short');
                        }
                        
                        // Vérifier si c'est aujourd'hui ou un autre jour
                        $is_today = ($date_key == 'today');
                        $default_open = $is_today ? 'open' : '';
                    ?>
                        <!-- Groupe par date -->
                        <div class="log-group" style="border-bottom:1px solid #F0F2F5;">
                            <!-- En-tête du groupe - cliquable -->
                            <div class="log-group-header" onclick="toggleGroup(this)" style="display:flex;align-items:center;justify-content:space-between;padding:14px 22px;background:#F8F9FA;cursor:pointer;transition:background 0.3s;border-left:4px solid <?= $date_color ?>;" onmouseover="this.style.background='#EEF0F2'" onmouseout="this.style.background='#F8F9FA'">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <i class="bi <?= $date_icon ?>" style="color:<?= $date_color ?>;font-size:1.1rem;"></i>
                                    <span style="font-weight:700;color:#1A2C3E;font-size:0.95rem;">
                                        <?= $date_label ?>
                                    </span>
                                    <span style="font-size:0.65rem;color:#8A99AA;background:<?= $is_today ? '#E3F2FD' : '#F0F2F5' ?>;padding:2px 14px;border-radius:12px;<?= $is_today ? 'border:1px solid #2980B9;' : '' ?>">
                                        <?= count($day_logs) ?> action(s)
                                    </span>
                                    <?php if($is_today): ?>
                                        <span style="font-size:0.55rem;background:#2980B9;color:#fff;padding:2px 12px;border-radius:12px;font-weight:600;">En cours</span>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;color:#8A99AA;">
                                    <span style="font-size:0.7rem;" class="toggle-icon">
                                        <i class="bi bi-chevron-down"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Contenu du groupe - affiché par défaut si aujourd'hui -->
                            <div class="log-group-content" style="<?= $is_today ? 'display:block;' : 'display:none;' ?>">
                                <div class="table-container">
                                    <table class="table-audit" style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                                        <thead>
                                            <tr>
                                                <th style="padding:10px 18px;background:#FAFBFC;color:#5A6B7A;font-weight:600;font-size:0.6rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #E8ECF0;width:160px;">
                                                    <i class="bi bi-clock" style="color:#C8922A;"></i> Heure
                                                </th>
                                                <th style="padding:10px 18px;background:#FAFBFC;color:#5A6B7A;font-weight:600;font-size:0.6rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #E8ECF0;">
                                                    <i class="bi bi-person" style="color:#C8922A;"></i> Administrateur
                                                </th>
                                                <th style="padding:10px 18px;background:#FAFBFC;color:#5A6B7A;font-weight:600;font-size:0.6rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #E8ECF0;">
                                                    <i class="bi bi-tag" style="color:#C8922A;"></i> Action
                                                </th>
                                                <th style="padding:10px 18px;background:#FAFBFC;color:#5A6B7A;font-weight:600;font-size:0.6rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #E8ECF0;">
                                                    <i class="bi bi-file-text" style="color:#C8922A;"></i> Détails
                                                </th>
                                                <th style="padding:10px 18px;background:#FAFBFC;color:#5A6B7A;font-weight:600;font-size:0.6rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #E8ECF0;text-align:center;width:120px;">
                                                    <i class="bi bi-wifi" style="color:#C8922A;"></i> IP
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($day_logs as $log): ?>
                                            <tr style="transition:background 0.2s;" onmouseover="this.style.background='#FAFBFC'" onmouseout="this.style.background='transparent'">
                                                <td style="padding:10px 18px;border-bottom:1px solid #F0F2F5;vertical-align:middle;font-size:0.75rem;color:#8A99AA;white-space:nowrap;">
                                                    <i class="bi bi-clock" style="font-size:0.6rem;color:#C8922A;margin-right:4px;"></i>
                                                    <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                                </td>
                                                <td style="padding:10px 18px;border-bottom:1px solid #F0F2F5;vertical-align:middle;">
                                                    <div style="display:flex;align-items:center;gap:10px;">
                                                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#C8922A,#E8B55A);display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:700;color:#fff;flex-shrink:0;">
                                                            <?= strtoupper(mb_substr($log['admin_nom'] ?? 'A', 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <div style="font-weight:600;color:#1A2C3E;font-size:0.85rem;">
                                                                <?= htmlspecialchars($log['admin_nom'] ?? 'Inconnu') ?>
                                                            </div>
                                                            <div style="font-size:0.6rem;color:#8A99AA;">
                                                                <?= htmlspecialchars($log['admin_email'] ?? '') ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="padding:10px 18px;border-bottom:1px solid #F0F2F5;vertical-align:middle;">
                                                    <span style="display:inline-block;padding:3px 14px;border-radius:20px;font-size:0.6rem;font-weight:600;background:<?= getActionColor($log['action']) ?>20;color:<?= getActionColor($log['action']) ?>;border:1px solid <?= getActionColor($log['action']) ?>30;">
                                                        <i class="bi <?= getActionIcon($log['action']) ?>" style="font-size:0.55rem;"></i>
                                                        <?= htmlspecialchars($log['action']) ?>
                                                    </span>
                                                </td>
                                                <td style="padding:10px 18px;border-bottom:1px solid #F0F2F5;vertical-align:middle;font-size:0.78rem;color:#5A6B7A;max-width:280px;word-wrap:break-word;">
                                                    <?php 
                                                    $details = $log['details'] ?? '—';
                                                    if (strlen($details) > 70) {
                                                        echo htmlspecialchars(substr($details, 0, 70)) . '...';
                                                    } else {
                                                        echo htmlspecialchars($details);
                                                    }
                                                    ?>
                                                </td>
                                                <td style="padding:10px 18px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;font-size:0.7rem;color:#8A99AA;font-family:monospace;">
                                                    <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== PAGINATION ===== -->
        <?php if($total_pages > 1): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-top:24px;padding:12px 0;">
            <div style="font-size:0.8rem;color:#8A99AA;">
                <i class="bi bi-info-circle" style="color:#C8922A;"></i>
                Affichage de <?= ($page - 1) * $per_page + 1 ?> à <?= min($page * $per_page, $total_logs) ?> sur <?= $total_logs ?> entrées
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn-small gray" style="padding:7px 16px;border-radius:8px;font-size:0.75rem;display:inline-flex;align-items:center;gap:4px;">
                        <i class="bi bi-chevron-left"></i> Précédent
                    </a>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#C8922A,#E8B55A);color:#fff;font-weight:700;font-size:0.85rem;box-shadow:0 4px 15px rgba(200,146,42,0.3);"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="btn-small gray" style="display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:50%;font-size:0.8rem;border:1px solid #E8ECF0;background:#fff;transition:all 0.3s;" onmouseover="this.style.background='#C8922A';this.style.color='#fff';this.style.borderColor='#C8922A'" onmouseout="this.style.background='#fff';this.style.color='#5A6B7A';this.style.borderColor='#E8ECF0'"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn-small gray" style="padding:7px 16px;border-radius:8px;font-size:0.75rem;display:inline-flex;align-items:center;gap:4px;">
                        Suivant <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>

<script>
// ============================================
// FONCTION POUR AFFICHER/MASQUER UN GROUPE DE LOGS
// ============================================
function toggleGroup(headerElement) {
    const group = headerElement.parentElement;
    const content = group.querySelector('.log-group-content');
    const icon = headerElement.querySelector('.toggle-icon i');
    
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        icon.className = 'bi bi-chevron-up';
    } else {
        content.style.display = 'none';
        icon.className = 'bi bi-chevron-down';
    }
}

// Ouvrir automatiquement le groupe "Aujourd'hui" au chargement
document.addEventListener('DOMContentLoaded', function() {
    const todayGroups = document.querySelectorAll('.log-group');
    todayGroups.forEach(function(group) {
        const header = group.querySelector('.log-group-header');
        const content = group.querySelector('.log-group-content');
        const icon = header.querySelector('.toggle-icon i');
        
        // Si le contenu est visible (style="display:block"), mettre la flèche vers le haut
        if (content.style.display !== 'none') {
            icon.className = 'bi bi-chevron-up';
        }
    });
});
</script>