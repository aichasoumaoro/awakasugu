<?php
// ============================================
// PAIEMENTS - ADMIN AWA KA SUGU
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

// Vérification des permissions (Paiements visible pour super_admin et directeur uniquement)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Gestion des Paiements';

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
    die("Erreur de connexion : " . $e->getMessage());
}

// ============================================
// CHANGER LE STATUT D'UN PAIEMENT
// ============================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $statut = $_GET['statut'] ?? 'valide';
    
    if ($statut == 'valide') {
        $stmt = $pdo->prepare("UPDATE paiements SET statut = 'confirme' WHERE id = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("SELECT commande_id FROM paiements WHERE id = ?");
        $stmt->execute([$id]);
        $paiement = $stmt->fetch();
        
        if ($paiement && $paiement['commande_id']) {
            $stmt = $pdo->prepare("UPDATE commandes SET statut = 'confirmee' WHERE id = ?");
            $stmt->execute([$paiement['commande_id']]);
        }
        
        $_SESSION['message_paiement'] = 'Paiement validé avec succès !';
    } elseif ($statut == 'rejete') {
        $stmt = $pdo->prepare("UPDATE paiements SET statut = 'echoue' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['message_paiement'] = 'Paiement rejeté.';
    }
    
    header('Location: paiements.php');
    exit;
}

// ============================================
// RÉCUPÉRER TOUS LES PAIEMENTS
// ============================================
$paiements = $pdo->query("
    SELECT p.*, c.numero_commande, c.nom_client, c.total as commande_total,
           c.telephone, c.notes as commande_notes
    FROM paiements p
    LEFT JOIN commandes c ON p.commande_id = c.id
    ORDER BY p.created_at DESC
")->fetchAll();

// ============================================
// GROUPER LES PAIEMENTS PAR CLIENT
// ============================================
$clients = [];

foreach($paiements as $p) {
    $client_key = $p['client_id'] ?? null;
    if ($client_key) {
        $key = 'id_' . $client_key;
    } else {
        $telephone = $p['telephone'] ?? 'inconnu';
        $key = 'tel_' . $telephone;
    }
    
    if (!isset($clients[$key])) {
        $clients[$key] = [
            'id' => $p['client_id'] ?? null,
            'telephone' => $p['telephone'] ?? 'inconnu',
            'nom_client' => $p['nom_client'] ?? 'Inconnu',
            'paiements' => [],
            'total_paiements' => 0,
            'total_montant' => 0,
            'dernier_paiement' => $p['created_at'],
            'nom_deposant' => '-'
        ];
    }
    $clients[$key]['paiements'][] = $p;
    $clients[$key]['total_paiements']++;
    $clients[$key]['total_montant'] += $p['montant'];
    
    $nom_deposant = '-';
    if (!empty($p['commande_notes'])) {
        if (preg_match('/Nom:\s*([^\n]+)/', $p['commande_notes'], $matches)) {
            $nom_deposant = trim($matches[1]);
        }
    }
    if ($nom_deposant != '-') {
        $clients[$key]['nom_deposant'] = $nom_deposant;
    }
    
    if (strtotime($p['created_at']) > strtotime($clients[$key]['dernier_paiement'])) {
        $clients[$key]['dernier_paiement'] = $p['created_at'];
    }
}

usort($clients, function($a, $b) {
    return strtotime($b['dernier_paiement']) - strtotime($a['dernier_paiement']);
});

// Statistiques
$total_paiements = count($paiements);
$total_clients = count($clients);
$total_montant_global = 0;
$paiements_attente = 0;
$paiements_valides = 0;
$paiements_rejetes = 0;

foreach($paiements as $p) {
    if($p['statut'] == 'en_attente') $paiements_attente++;
    elseif($p['statut'] == 'confirme') $paiements_valides++;
    elseif($p['statut'] == 'echoue') $paiements_rejetes++;
    $total_montant_global += $p['montant'];
}

$message = $_SESSION['message_paiement'] ?? '';
unset($_SESSION['message_paiement']);

// ============================================
// PARAMÈTRES D'AFFICHAGE
// ============================================
$voir_client_id = isset($_GET['voir_client']) ? (int)$_GET['voir_client'] : 0;
$telephone_param = isset($_GET['telephone']) ? trim($_GET['telephone']) : '';
$show_detail = $voir_client_id > 0 || !empty($telephone_param);

$paiements_client = [];
$client_info = null;

if ($show_detail) {
    if ($voir_client_id > 0) {
        foreach($paiements as $p) {
            if (($p['client_id'] ?? 0) == $voir_client_id) {
                $paiements_client[] = $p;
                if (!$client_info) {
                    $client_info = $p;
                }
            }
        }
    } elseif (!empty($telephone_param) && $telephone_param != 'inconnu') {
        foreach($paiements as $p) {
            if (($p['telephone'] ?? '') == $telephone_param) {
                $paiements_client[] = $p;
                if (!$client_info) {
                    $client_info = $p;
                }
            }
        }
    }
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
            <div class="topbar-title">💳 Gestion des <span>Paiements</span></div>
            <div class="topbar-breadcrumb">Finances → Paiements</div>
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

        <?php if($show_detail && empty($paiements_client)): ?>
            <div class="alert-info">
                <i class="bi bi-info-circle"></i> 
                Aucun paiement trouvé pour ce client.
                <a href="paiements.php" style="color:#2980B9;font-weight:600;text-decoration:underline;">Retour à la liste</a>
            </div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-credit-card"></i></div>
                <div>
                    <div class="stat-val"><?= $total_paiements ?></div>
                    <div class="stat-lbl">Total paiements</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-orange"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-val"><?= $paiements_attente ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $paiements_valides ?></div>
                    <div class="stat-lbl">Validés</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-cash"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_montant_global, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Total des paiements</div>
                </div>
            </div>
        </div>

        <!-- ===== LISTE DES CLIENTS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-people"></i> Clients</div>
                <div style="font-size:0.7rem;color:#8A99AA;background:#F8F9FA;padding:4px 16px;border-radius:20px;border:1px solid #E8ECF0;">
                    <strong style="color:#C8922A;"><?= $total_clients ?></strong> client(s)
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-paiements" style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Client</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Téléphone</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Paiements</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:right;">Total</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Dernier paiement</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;width:100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($clients)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state" style="text-align:center;padding:40px;color:#8A99AA;">
                                            <i class="bi bi-credit-card" style="font-size:2.5rem;display:block;margin-bottom:10px;color:#D5D5D5;"></i>
                                            <p style="margin:0;font-size:0.85rem;">Aucun paiement</p>
                                            <span style="font-size:0.75rem;color:#bbb;display:block;margin-top:4px;">Les paiements apparaissent ici après validation</span>
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
                                                <?php if($client['nom_deposant'] != '-'): ?>
                                                    <div style="font-size:0.6rem;color:#8A99AA;margin-top:1px;">
                                                        <i class="bi bi-person" style="font-size:0.55rem;"></i> <?= htmlspecialchars($client['nom_deposant']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;font-size:0.8rem;">
                                        <?= htmlspecialchars($client['telephone']) ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <span style="display:inline-block;background:rgba(200,146,42,0.12);color:#C8922A;border-radius:50%;padding:2px 12px;font-size:0.7rem;font-weight:700;min-width:28px;">
                                            <?= $client['total_paiements'] ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:right;font-weight:600;color:#C8922A;font-size:0.85rem;">
                                        <?= number_format($client['total_montant'], 0, ',', ' ') ?> F
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;font-size:0.75rem;color:#8A99AA;">
                                        <i class="bi bi-calendar3" style="font-size:0.6rem;"></i>
                                        <?= date('d/m/Y H:i', strtotime($client['dernier_paiement'])) ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <?php if($client['id']): ?>
                                        <a href="paiements.php?voir_client=<?= $client['id'] ?>" class="btn-small blue" title="Voir les paiements du client" style="padding:4px 14px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:4px;background:rgba(41,128,185,0.1);color:#2980B9;transition:all 0.2s;border:none;cursor:pointer;">
                                            <i class="bi bi-eye"></i> Voir
                                        </a>
                                        <?php else: ?>
                                        <a href="paiements.php?telephone=<?= urlencode($client['telephone']) ?>" class="btn-small blue" title="Voir les paiements du client" style="padding:4px 14px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:4px;background:rgba(41,128,185,0.1);color:#2980B9;transition:all 0.2s;border:none;cursor:pointer;">
                                            <i class="bi bi-eye"></i> Voir
                                        </a>
                                        <?php endif; ?>
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
<?php if($show_detail && !empty($paiements_client) && $client_info): ?>
<div class="modal-overlay active" id="modalDetail" onclick="if(event.target===this) closeModal()">
    <div class="modal-content" style="max-width:800px;width:95%;max-height:90vh;overflow-y:auto;padding:30px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-family:'Playfair Display',serif;font-size:1.2rem;margin:0;">
                👤 Paiements de <span style="color:#C8922A;"><?= htmlspecialchars($client_info['nom_client'] ?? 'Client') ?></span>
            </h3>
            <button onclick="closeModal()" style="background:none;border:none;font-size:1.8rem;cursor:pointer;color:#999;transition:transform 0.3s;line-height:1;" onmouseover="this.style.transform='rotate(90deg)'" onmouseout="this.style.transform='rotate(0deg)'">&times;</button>
        </div>
        
        <div style="background:#F8F9FA;padding:12px 16px;border-radius:8px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:12px 20px;font-size:0.85rem;color:#5A6B7A;border:1px solid #E8ECF0;">
            <span><i class="bi bi-telephone" style="color:#C8922A;"></i> <?= htmlspecialchars($client_info['telephone'] ?? 'Non renseigné') ?></span>
            <span><i class="bi bi-credit-card" style="color:#C8922A;"></i> <?= count($paiements_client) ?> paiement(s)</span>
            <span style="font-weight:600;color:#C8922A;"><i class="bi bi-cash" style="color:#C8922A;"></i> Total : <?= number_format(array_sum(array_column($paiements_client, 'montant')), 0, ',', ' ') ?> F</span>
        </div>
        
        <?php foreach($paiements_client as $p): 
            $mode_label = '';
            $mode_color = '';
            if($p['mode'] == 'orange_money') { $mode_color = '#FF6600'; $mode_label = 'Orange Money'; }
            elseif($p['mode'] == 'wave') { $mode_color = '#1A7A4A'; $mode_label = 'Wave'; }
            elseif($p['mode'] == 'moov_money') { $mode_color = '#E63E2E'; $mode_label = 'Moov Money'; }
            else { $mode_label = $p['mode'] ?? 'Autre'; $mode_color = '#6C757D'; }
            
            $statut_label = '';
            $statut_class = '';
            if($p['statut'] == 'en_attente') { $statut_label = '⏳ En attente'; $statut_class = 'statut-en_attente'; }
            elseif($p['statut'] == 'confirme') { $statut_label = '✅ Confirmé'; $statut_class = 'statut-confirme'; }
            elseif($p['statut'] == 'echoue') { $statut_label = '❌ Échoué'; $statut_class = 'statut-annulee'; }
            else { $statut_label = $p['statut'] ?? 'Inconnu'; }
            
            $nom_deposant = '-';
            if (!empty($p['commande_notes'])) {
                if (preg_match('/Nom:\s*([^\n]+)/', $p['commande_notes'], $matches)) {
                    $nom_deposant = trim($matches[1]);
                }
            }
        ?>
        <div style="background:#FFFFFF;border-radius:10px;padding:14px 18px;margin-bottom:10px;border:1px solid #E8ECF0;transition:all 0.3s;" onmouseover="this.style.borderColor='#C8922A';this.style.boxShadow='0 4px 20px rgba(200,146,42,0.08)'" onmouseout="this.style.borderColor='#E8ECF0';this.style.boxShadow='none'">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="font-weight:700;color:#C8922A;font-size:0.9rem;">#<?= $p['id'] ?></span>
                    <span style="display:inline-block;padding:2px 12px;border-radius:4px;font-size:0.6rem;font-weight:600;color:#fff;background:<?= $mode_color ?>;">
                        <?= $mode_label ?>
                    </span>
                    <span class="badge-status <?= $statut_class ?>" style="padding:3px 12px;border-radius:20px;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;display:inline-flex;align-items:center;gap:4px;background:<?= $p['statut'] == 'confirme' ? '#E8F5E9' : ($p['statut'] == 'echoue' ? '#FBE9E7' : '#FEF6E6') ?>;color:<?= $p['statut'] == 'confirme' ? '#2E7D32' : ($p['statut'] == 'echoue' ? '#C62828' : '#E67E22') ?>;">
                        <?= $statut_label ?>
                    </span>
                </div>
                <div style="font-size:0.75rem;color:#8A99AA;">
                    <i class="bi bi-calendar3"></i> <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
                </div>
            </div>
            <div style="margin-top:6px;padding-top:8px;border-top:1px solid #F0F2F5;font-size:0.82rem;color:#5A6B7A;display:flex;flex-wrap:wrap;gap:8px 20px;">
                <span><strong>Commande :</strong> <?= htmlspecialchars($p['numero_commande'] ?? 'N/A') ?></span>
                <span><strong>Montant :</strong> <span style="color:#C8922A;font-weight:600;"><?= number_format($p['montant'], 0, ',', ' ') ?> F</span></span>
                <span><strong>Déposant :</strong> <?= htmlspecialchars($nom_deposant) ?></span>
                <?php if(!empty($p['telephone_paiement'])): ?>
                <span><strong>Tél :</strong> <?= htmlspecialchars($p['telephone_paiement']) ?></span>
                <?php endif; ?>
            </div>
            <?php if(!empty($p['reference_transaction'])): ?>
            <div style="margin-top:4px;font-size:0.7rem;color:#8A99AA;">
                <strong>Réf :</strong> <?= htmlspecialchars($p['reference_transaction']) ?>
            </div>
            <?php endif; ?>
            <?php if($p['statut'] == 'en_attente'): ?>
            <div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;">
                <a href="paiements.php?action=valider&id=<?= $p['id'] ?>&statut=valide" class="btn-small green" onclick="return confirm('Valider ce paiement ?')" style="padding:5px 16px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:5px;background:rgba(40,167,69,0.1);color:#28A745;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='#28A745';this.style.color='#fff'" onmouseout="this.style.background='rgba(40,167,69,0.1)';this.style.color='#28A745'">
                    <i class="bi bi-check2"></i> Valider
                </a>
                <a href="paiements.php?action=valider&id=<?= $p['id'] ?>&statut=rejete" class="btn-small red" onclick="return confirm('Rejeter ce paiement ?')" style="padding:5px 16px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:5px;background:rgba(231,76,60,0.1);color:#E74C3C;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='#E74C3C';this.style.color='#fff'" onmouseout="this.style.background='rgba(231,76,60,0.1)';this.style.color='#E74C3C'">
                    <i class="bi bi-x"></i> Rejeter
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align:center;margin-top:20px;">
            <a href="paiements.php" class="btn-admin btn-primary" style="padding:10px 28px;justify-content:center;">
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
    window.location.href = 'paiements.php';
}
</script>