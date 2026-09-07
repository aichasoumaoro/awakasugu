<?php
// ============================================
// COMMANDES REPAS & GÂTEAUX - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';

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
$admin_nom = $admin_info['nom'] ?? 'Awa Doumbia';
$admin_id = $admin_info['id'] ?? 0;

// Vérification des permissions (Commandes repas visible pour super_admin, directeur et admin)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur' && $admin_role !== 'admin') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Commandes Repas & Gâteaux';

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
    die("Erreur : " . $e->getMessage());
}

// ============================================
// CHANGER LE STATUT D'UNE COMMANDE REPAS
// ============================================
if (isset($_GET['statut']) && isset($_GET['id']) && isset($_GET['type'])) {
    $id = (int)$_GET['id'];
    $nouveau_statut = $_GET['statut'];
    $type = $_GET['type'];
    $statuts_valides = ['en_attente', 'confirmee', 'en_preparation', 'terminee', 'annulee'];
    
    if (in_array($nouveau_statut, $statuts_valides)) {
        if ($type == 'repas') {
            $pdo->prepare("UPDATE commandes_repas SET statut = ? WHERE id = ?")->execute([$nouveau_statut, $id]);
        } elseif ($type == 'gateau') {
            $pdo->prepare("UPDATE commandes_gateaux SET statut = ? WHERE id = ?")->execute([$nouveau_statut, $id]);
        }
        $_SESSION['message_commande'] = 'Statut mis à jour avec succès !';
        header('Location: commandes_repas.php');
        exit;
    }
}

// ============================================
// SUPPRIMER UNE COMMANDE REPAS
// ============================================
if (isset($_GET['supprimer']) && isset($_GET['type'])) {
    $id = (int)$_GET['supprimer'];
    $type = $_GET['type'];
    
    if ($type == 'repas') {
        $pdo->prepare("DELETE FROM commandes_repas WHERE id = ?")->execute([$id]);
    } elseif ($type == 'gateau') {
        $pdo->prepare("DELETE FROM commandes_gateaux WHERE id = ?")->execute([$id]);
    }
    $_SESSION['message_commande'] = 'Commande supprimée avec succès !';
    header('Location: commandes_repas.php');
    exit;
}

// ============================================
// VOIR LE DÉTAIL D'UN CLIENT (TOUTES SES COMMANDES)
// ============================================
$detail_client = null;
$commandes_client = [];
if (isset($_GET['voir_client']) && isset($_GET['telephone'])) {
    $telephone = $_GET['telephone'];
    
    $stmt = $pdo->prepare("SELECT *, 'repas' as type FROM commandes_repas WHERE telephone = ? ORDER BY created_at DESC");
    $stmt->execute([$telephone]);
    $commandes_repas_client = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT *, 'gateau' as type FROM commandes_gateaux WHERE telephone = ? ORDER BY created_at DESC");
    $stmt->execute([$telephone]);
    $commandes_gateaux_client = $stmt->fetchAll();
    
    $commandes_client = array_merge($commandes_repas_client, $commandes_gateaux_client);
    usort($commandes_client, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    if (!empty($commandes_client)) {
        $detail_client = $commandes_client[0];
    }
}

// ============================================
// VOIR LE DÉTAIL D'UNE COMMANDE UNIQUE
// ============================================
$detail_commande = null;
$detail_type = null;
if (isset($_GET['voir']) && isset($_GET['type']) && !isset($_GET['voir_client'])) {
    $id = (int)$_GET['voir'];
    $type = $_GET['type'];
    
    if ($type == 'repas') {
        $stmt = $pdo->prepare("SELECT *, 'repas' as type FROM commandes_repas WHERE id = ?");
        $stmt->execute([$id]);
        $detail_commande = $stmt->fetch();
        $detail_type = 'repas';
    } elseif ($type == 'gateau') {
        $stmt = $pdo->prepare("SELECT *, 'gateau' as type FROM commandes_gateaux WHERE id = ?");
        $stmt->execute([$id]);
        $detail_commande = $stmt->fetch();
        $detail_type = 'gateau';
    }
}

// ============================================
// RÉCUPÉRER TOUTES LES COMMANDES
// ============================================
$commandes_repas = $pdo->query("SELECT *, 'repas' as type FROM commandes_repas ORDER BY created_at DESC")->fetchAll();
$commandes_gateaux = $pdo->query("SELECT *, 'gateau' as type FROM commandes_gateaux ORDER BY created_at DESC")->fetchAll();

$commandes = array_merge($commandes_repas, $commandes_gateaux);
usort($commandes, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// ============================================
// GROUPER LES COMMANDES PAR CLIENT
// ============================================
$clients = [];
foreach($commandes as $c) {
    $telephone = $c['telephone'] ?? 'inconnu';
    if (!isset($clients[$telephone])) {
        $clients[$telephone] = [
            'telephone' => $telephone,
            'nom_client' => $c['nom_client'] ?? 'Inconnu',
            'commandes' => [],
            'total_commandes' => 0,
            'total_depense' => 0,
            'derniere_commande' => $c['created_at']
        ];
    }
    $clients[$telephone]['commandes'][] = $c;
    $clients[$telephone]['total_commandes']++;
    
    // Calcul du montant de la commande
    $montant = ($c['type'] == 'gateau') ? ($c['prix'] ?? 0) : ($c['total'] ?? 0);
    
    // SEUL les statuts confirmee, en_preparation, terminee sont comptabilisés dans le total dépensé
    // Les commandes annulees et en_attente ne sont PAS comptabilisées
    if (in_array($c['statut'], ['confirmee', 'en_preparation', 'terminee'])) {
        $clients[$telephone]['total_depense'] += $montant;
    }
    
    if (strtotime($c['created_at']) > strtotime($clients[$telephone]['derniere_commande'])) {
        $clients[$telephone]['derniere_commande'] = $c['created_at'];
    }
}

usort($clients, function($a, $b) {
    return strtotime($b['derniere_commande']) - strtotime($a['derniere_commande']);
});

// Statistiques
$total_commandes = count($commandes);
$commandes_attente = 0;
$ca_repas_mois = 0;
$commandes_confirmees = 0;
$commandes_terminees = 0;
$commandes_annulees = 0;

foreach($commandes as $c) {
    $statut = $c['statut'] ?? 'en_attente';
    $montant = ($c['type'] == 'gateau') ? ($c['prix'] ?? 0) : ($c['total'] ?? 0);
    
    if($statut == 'en_attente') $commandes_attente++;
    elseif($statut == 'confirmee') $commandes_confirmees++;
    elseif($statut == 'terminee') $commandes_terminees++;
    elseif($statut == 'annulee') $commandes_annulees++;
    
    // SEUL les commandes confirmees, en_preparation et terminee sont comptabilisées dans le CA
    // Les commandes annulees et en_attente ne sont PAS comptabilisées
    if (in_array($statut, ['confirmee', 'en_preparation', 'terminee'])) {
        if(date('m', strtotime($c['created_at'])) == date('m')) {
            $ca_repas_mois += $montant;
        }
    }
}

$message = $_SESSION['message_commande'] ?? '';
unset($_SESSION['message_commande']);

$statuts = [
    'en_attente' => ['label' => 'En attente', 'class' => 'statut-en_attente', 'icon' => 'bi-clock-history'],
    'confirmee' => ['label' => 'Confirmée', 'class' => 'statut-confirmee', 'icon' => 'bi-check-circle'],
    'en_preparation' => ['label' => 'Préparation', 'class' => 'statut-en_preparation', 'icon' => 'bi-gear'],
    'terminee' => ['label' => 'Terminée', 'class' => 'statut-terminee', 'icon' => 'bi-check-circle-fill'],
    'annulee' => ['label' => 'Annulée', 'class' => 'statut-annulee', 'icon' => 'bi-x-circle'],
];

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
            <div class="topbar-title">🛵 Commandes <span>Repas & Gâteaux</span></div>
            <div class="topbar-breadcrumb">Restaurant → Commandes</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <?php if($message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-bag-check"></i></div>
                <div>
                    <div class="stat-val"><?= $total_commandes ?></div>
                    <div class="stat-lbl">Total commandes</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $commandes_confirmees + $commandes_terminees ?></div>
                    <div class="stat-lbl">Confirmées / Terminées</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-val"><?= $commandes_attente ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-cash"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($ca_repas_mois, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">CA du mois</div>
                </div>
            </div>
        </div>

        <!-- ===== LISTE DES CLIENTS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-people"></i> Clients</div>
                <div style="font-size:0.7rem;color:#8A99AA;background:#F8F9FA;padding:4px 16px;border-radius:20px;border:1px solid #E8ECF0;">
                    <strong style="color:#C8922A;"><?= count($clients) ?></strong> client(s)
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-commandes" style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Client</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Téléphone</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Nb commandes</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:right;">Total dépensé</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Dernière commande</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;width:100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($clients)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state" style="text-align:center;padding:40px;color:#8A99AA;">
                                            <i class="bi bi-people" style="font-size:2.5rem;display:block;margin-bottom:10px;color:#D5D5D5;"></i>
                                            <p style="margin:0;font-size:0.85rem;">Aucun client</p>
                                            <span style="font-size:0.75rem;color:#bbb;display:block;margin-top:4px;">Les commandes apparaîtront ici</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($clients as $client): 
                                    $initiale = strtoupper(mb_substr($client['nom_client'] ?? 'C', 0, 1));
                                ?>
                                <tr style="transition:background 0.2s;">
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;">
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#C8922A,#E8B55A);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:#fff;flex-shrink:0;">
                                                <?= $initiale ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600;color:#1A2C3E;font-size:0.85rem;">
                                                    <?= htmlspecialchars($client['nom_client']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;font-size:0.8rem;">
                                        <?= htmlspecialchars($client['telephone']) ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <span style="display:inline-block;background:rgba(200,146,42,0.12);color:#C8922A;border-radius:50%;padding:2px 12px;font-size:0.7rem;font-weight:700;min-width:28px;">
                                            <?= $client['total_commandes'] ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:right;font-weight:600;color:<?= ($client['total_depense'] ?? 0) > 0 ? '#C8922A' : '#8A99AA' ?>;font-size:0.85rem;">
                                        <?= number_format($client['total_depense'], 0, ',', ' ') ?> F
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;font-size:0.75rem;color:#8A99AA;">
                                        <i class="bi bi-calendar3" style="font-size:0.6rem;"></i>
                                        <?= date('d/m/Y H:i', strtotime($client['derniere_commande'])) ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <a href="commandes_repas.php?voir_client=1&telephone=<?= urlencode($client['telephone']) ?>" 
                                           class="btn-small blue" title="Voir toutes les commandes du client"
                                           style="padding:4px 14px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:4px;background:rgba(41,128,185,0.1);color:#2980B9;transition:all 0.2s;border:none;cursor:pointer;"
                                           onmouseover="this.style.background='#2980B9';this.style.color='#fff'"
                                           onmouseout="this.style.background='rgba(41,128,185,0.1)';this.style.color='#2980B9'">
                                            <i class="bi bi-eye"></i> Voir
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     MODAL DÉTAIL CLIENT
     ============================================ -->
<?php if($detail_client && !empty($commandes_client)): ?>
<div class="modal-overlay active" id="modalDetail" onclick="if(event.target===this) closeModal()">
    <div class="modal-content" style="max-width:750px;width:95%;max-height:90vh;overflow-y:auto;padding:30px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-family:'Playfair Display',serif;font-size:1.2rem;margin:0;">
                👤 Commandes de <span style="color:#C8922A;"><?= htmlspecialchars($detail_client['nom_client'] ?? 'Client') ?></span>
            </h3>
            <button onclick="closeModal()" style="background:none;border:none;font-size:1.8rem;cursor:pointer;color:#999;transition:transform 0.3s;line-height:1;" onmouseover="this.style.transform='rotate(90deg)'" onmouseout="this.style.transform='rotate(0deg)'">&times;</button>
        </div>
        <div style="background:#F8F9FA;padding:12px 16px;border-radius:8px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:12px 20px;font-size:0.85rem;color:#5A6B7A;border:1px solid #E8ECF0;">
            <span><i class="bi bi-telephone" style="color:#C8922A;"></i> <?= htmlspecialchars($detail_client['telephone'] ?? 'Non renseigné') ?></span>
            <span><i class="bi bi-bag" style="color:#C8922A;"></i> <?= count($commandes_client) ?> commande(s)</span>
            <span style="font-weight:600;color:#C8922A;"><i class="bi bi-cash" style="color:#C8922A;"></i> Total : <?= number_format(array_sum(array_map(function($c) {
                // SEUL les commandes confirmees, en_preparation, terminee sont comptabilisées
                if (in_array($c['statut'], ['confirmee', 'en_preparation', 'terminee'])) {
                    return ($c['type'] == 'gateau') ? ($c['prix'] ?? 0) : ($c['total'] ?? 0);
                }
                return 0;
            }, $commandes_client)), 0, ',', ' ') ?> F</span>
        </div>
        
        <?php foreach($commandes_client as $c): 
            $is_gateau = ($c['type'] == 'gateau');
            $nom_produit = $is_gateau ? ($c['nom_gateau'] ?? 'Gâteau') : ($c['nom_plat'] ?? 'Repas');
            $total = $is_gateau ? ($c['prix'] ?? 0) : ($c['total'] ?? 0);
            $current_statut = $c['statut'] ?? 'en_attente';
            $statut_info = $statuts[$current_statut] ?? ['label' => $current_statut, 'icon' => 'bi-circle'];
            
            // Vérifier si la commande est valide pour le CA
            $est_valide = in_array($current_statut, ['confirmee', 'en_preparation', 'terminee']);
        ?>
        <div style="background:#FFFFFF;border-radius:10px;padding:14px 18px;margin-bottom:10px;border:1px solid <?= $est_valide ? '#E8ECF0' : '#FBE9E7' ?>;transition:all 0.3s;" onmouseover="this.style.borderColor='#C8922A';this.style.boxShadow='0 4px 20px rgba(200,146,42,0.08)'" onmouseout="this.style.borderColor='<?= $est_valide ? '#E8ECF0' : '#FBE9E7' ?>';this.style.boxShadow='none'">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="font-weight:700;color:#C8922A;font-size:0.9rem;">#<?= $c['id'] ?></span>
                    <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.6rem;font-weight:600;background:<?= $is_gateau ? 'rgba(142,68,173,0.12)' : 'rgba(200,146,42,0.12)' ?>;color:<?= $is_gateau ? '#8E44AD' : '#C8922A' ?>;">
                        <?= $is_gateau ? '🎂 Gâteau' : '🍽️ Repas' ?>
                    </span>
                    <span class="badge-status <?= $current_statut ?>" style="padding:3px 12px;border-radius:20px;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;display:inline-flex;align-items:center;gap:4px;background:<?= $current_statut == 'terminee' ? '#E8F5E9' : ($current_statut == 'confirmee' ? '#E3F2FD' : ($current_statut == 'en_preparation' ? '#FFF3E0' : ($current_statut == 'annulee' ? '#FBE9E7' : '#FEF6E6'))) ?>;color:<?= $current_statut == 'terminee' ? '#2E7D32' : ($current_statut == 'confirmee' ? '#1565C0' : ($current_statut == 'en_preparation' ? '#E67E22' : ($current_statut == 'annulee' ? '#C62828' : '#E67E22'))) ?>;">
                        <i class="bi <?= $statut_info['icon'] ?>"></i>
                        <?= $statut_info['label'] ?>
                    </span>
                    <?php if(!$est_valide): ?>
                        <span style="font-size:0.55rem;color:#E74C3C;font-weight:600;">(non comptabilisée)</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.75rem;color:#8A99AA;">
                    <i class="bi bi-calendar3"></i> <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                </div>
            </div>
            <div style="margin-top:6px;padding-top:8px;border-top:1px solid #F0F2F5;font-size:0.82rem;color:#5A6B7A;display:flex;flex-wrap:wrap;gap:8px 16px;">
                <span><strong><?= htmlspecialchars($nom_produit) ?></strong></span>
                <?php if(!$is_gateau && isset($c['quantite'])): ?>
                    <span>Qté: <?= $c['quantite'] ?></span>
                <?php endif; ?>
                <span style="font-weight:700;color:<?= $est_valide ? '#C8922A' : '#8A99AA' ?>;">
                    <?= number_format($total, 0, ',', ' ') ?> F
                </span>
                <?php if(!empty($c['adresse_livraison'])): ?>
                    <span><i class="bi bi-geo-alt" style="color:#8A99AA;"></i> <?= htmlspecialchars($c['adresse_livraison']) ?></span>
                <?php endif; ?>
            </div>
            
            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:10px;">
                <?php foreach($statuts as $key => $s): ?>
                    <?php if($key != $current_statut): ?>
                        <a href="commandes_repas.php?statut=<?= $key ?>&id=<?= $c['id'] ?>&type=<?= $c['type'] ?>" 
                           class="btn-small <?= $key == 'annulee' ? 'red' : 'gray' ?>" 
                           style="font-size:0.6rem;padding:3px 12px;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:<?= $key == 'annulee' ? 'rgba(231,76,60,0.1)' : '#F0F2F5' ?>;color:<?= $key == 'annulee' ? '#E74C3C' : '#5A6B7A' ?>;transition:all 0.2s;border:none;cursor:pointer;"
                           onclick="return confirm('Changer le statut en <?= $s['label'] ?> ?')"
                           onmouseover="this.style.background='<?= $key == 'annulee' ? '#E74C3C' : '#E0E6ED' ?>';this.style.color='#fff'"
                           onmouseout="this.style.background='<?= $key == 'annulee' ? 'rgba(231,76,60,0.1)' : '#F0F2F5' ?>';this.style.color='<?= $key == 'annulee' ? '#E74C3C' : '#5A6B7A' ?>'">
                            <?= $s['label'] ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <a href="commandes_repas.php?supprimer=<?= $c['id'] ?>&type=<?= $c['type'] ?>" 
                   class="btn-small red" style="font-size:0.6rem;padding:3px 12px;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:3px;background:rgba(231,76,60,0.1);color:#E74C3C;transition:all 0.2s;border:none;cursor:pointer;"
                   onclick="return confirm('Supprimer cette commande #<?= $c['id'] ?> ?')"
                   onmouseover="this.style.background='#E74C3C';this.style.color='#fff'"
                   onmouseout="this.style.background='rgba(231,76,60,0.1)';this.style.color='#E74C3C'">
                    <i class="bi bi-trash3"></i> Supprimer
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align:center;margin-top:20px;">
            <a href="commandes_repas.php" class="btn-admin btn-primary" style="padding:10px 28px;justify-content:center;">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     MODAL DÉTAIL COMMANDE UNIQUE
     ============================================ -->
<?php if($detail_commande && !isset($_GET['voir_client'])): ?>
<div class="modal-overlay active" id="modalDetail" onclick="if(event.target===this) closeModal()">
    <div class="modal-content" style="max-width:650px;width:95%;max-height:90vh;overflow-y:auto;padding:30px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-family:'Playfair Display',serif;font-size:1.2rem;margin:0;">
                📋 Détail de la commande <span style="color:#C8922A;">#<?= $detail_commande['id'] ?></span>
            </h3>
            <button onclick="closeModal()" style="background:none;border:none;font-size:1.8rem;cursor:pointer;color:#999;transition:transform 0.3s;line-height:1;" onmouseover="this.style.transform='rotate(90deg)'" onmouseout="this.style.transform='rotate(0deg)'">&times;</button>
        </div>
        
        <?php if($detail_type == 'gateau'): ?>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
                <span style="color:#8A99AA;font-size:0.8rem;">Type</span>
                <span style="font-weight:600;">🎂 Gâteau</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
                <span style="color:#8A99AA;font-size:0.8rem;">Nom du gâteau</span>
                <span style="font-weight:600;"><?= htmlspecialchars($detail_commande['nom_gateau'] ?? '-') ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
                <span style="color:#8A99AA;font-size:0.8rem;">Nom personnalisé</span>
                <span style="font-weight:600;"><?= htmlspecialchars($detail_commande['nom_personnalise'] ?? '-') ?></span>
            </div>
            <?php if(!empty($detail_commande['age'])): ?>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
                <span style="color:#8A99AA;font-size:0.8rem;">Âge</span>
                <span style="font-weight:600;"><?= $detail_commande['age'] ?> ans</span>
            </div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
                <span style="color:#8A99AA;font-size:0.8rem;">Message</span>
                <span style="font-weight:600;"><?= htmlspecialchars($detail_commande['message_inscription'] ?? '-') ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
                <span style="color:#8A99AA;font-size:0.8rem;">Date événement</span>
                <span style="font-weight:600;"><?= date('d/m/Y', strtotime($detail_commande['date_evenement'])) ?></span>
            </div>
        <?php else: ?>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
                <span style="color:#8A99AA;font-size:0.8rem;">Type</span>
                <span style="font-weight:600;">🍽️ Repas</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
                <span style="color:#8A99AA;font-size:0.8rem;">Plat</span>
                <span style="font-weight:600;"><?= htmlspecialchars($detail_commande['nom_plat'] ?? '-') ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
                <span style="color:#8A99AA;font-size:0.8rem;">Quantité</span>
                <span style="font-weight:600;"><?= $detail_commande['quantite'] ?? 1 ?></span>
            </div>
        <?php endif; ?>
        
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
            <span style="color:#8A99AA;font-size:0.8rem;">Client</span>
            <span style="font-weight:600;"><?= htmlspecialchars($detail_commande['nom_client'] ?? 'Inconnu') ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
            <span style="color:#8A99AA;font-size:0.8rem;">Téléphone</span>
            <span style="font-weight:600;"><?= htmlspecialchars($detail_commande['telephone'] ?? '-') ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
            <span style="color:#8A99AA;font-size:0.8rem;">Email</span>
            <span style="font-weight:600;"><?= htmlspecialchars($detail_commande['email'] ?? '-') ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
            <span style="color:#8A99AA;font-size:0.8rem;">Adresse</span>
            <span style="font-weight:600;"><?= htmlspecialchars($detail_commande['adresse_livraison'] ?? '-') ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
            <span style="color:#8A99AA;font-size:0.8rem;">Total</span>
            <span style="font-weight:700;color:#C8922A;font-size:1.1rem;">
                <?= number_format($detail_type == 'gateau' ? ($detail_commande['prix'] ?? 0) : ($detail_commande['total'] ?? 0), 0, ',', ' ') ?> F
            </span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F0F2F5;">
            <span style="color:#8A99AA;font-size:0.8rem;">Statut</span>
            <span style="font-weight:600;">
                <span class="badge-status <?= $detail_commande['statut'] ?>" style="padding:3px 12px;border-radius:20px;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;display:inline-flex;align-items:center;gap:4px;background:<?= $detail_commande['statut'] == 'terminee' ? '#E8F5E9' : ($detail_commande['statut'] == 'confirmee' ? '#E3F2FD' : ($detail_commande['statut'] == 'en_preparation' ? '#FFF3E0' : ($detail_commande['statut'] == 'annulee' ? '#FBE9E7' : '#FEF6E6'))) ?>;color:<?= $detail_commande['statut'] == 'terminee' ? '#2E7D32' : ($detail_commande['statut'] == 'confirmee' ? '#1565C0' : ($detail_commande['statut'] == 'en_preparation' ? '#E67E22' : ($detail_commande['statut'] == 'annulee' ? '#C62828' : '#E67E22'))) ?>;">
                    <i class="bi <?= $statuts[$detail_commande['statut']]['icon'] ?? 'bi-circle' ?>"></i>
                    <?= $statuts[$detail_commande['statut']]['label'] ?? $detail_commande['statut'] ?>
                </span>
            </span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;">
            <span style="color:#8A99AA;font-size:0.8rem;">Date</span>
            <span style="font-weight:600;font-size:0.85rem;"><?= date('d/m/Y à H:i', strtotime($detail_commande['created_at'])) ?></span>
        </div>
        
        <div style="text-align:center;margin-top:20px;">
            <a href="commandes_repas.php" class="btn-admin btn-primary" style="padding:10px 28px;justify-content:center;">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>

<script>
function closeModal() {
    document.getElementById('modalDetail').classList.remove('active');
    window.location.href = 'commandes_repas.php';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>