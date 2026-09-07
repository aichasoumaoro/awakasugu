<?php
// ============================================
// FACTURES - ADMIN AWA KA SUGU
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

// Vérification des permissions (Factures visible pour super_admin et directeur uniquement)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Gestion des Factures';

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
// METTRE À JOUR LE STATUT DES FACTURES SELON LES COMMANDES
// ============================================
$pdo->exec("
    UPDATE factures f
    JOIN commandes c ON c.id = f.commande_id
    SET f.statut_paiement = 'payee'
    WHERE c.statut IN ('livree', 'terminee', 'confirmee')
    AND f.statut_paiement != 'payee'
");

$pdo->exec("
    UPDATE factures f
    JOIN commandes c ON c.id = f.commande_id
    SET f.statut_paiement = 'annulee'
    WHERE c.statut = 'annulee'
    AND f.statut_paiement != 'annulee'
");

// ============================================
// VOIR TOUTES LES FACTURES D'UN CLIENT
// ============================================
$detail_client = null;
$factures_client = [];
if (isset($_GET['voir_client']) && isset($_GET['telephone'])) {
    $telephone = $_GET['telephone'];
    
    $stmt = $pdo->prepare("
        SELECT f.*, c.numero_commande, c.nom_client, c.total as commande_total, c.email, c.telephone, c.statut as commande_statut
        FROM factures f 
        JOIN commandes c ON c.id = f.commande_id 
        WHERE c.telephone = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$telephone]);
    $factures_client = $stmt->fetchAll();
    
    if (!empty($factures_client)) {
        $detail_client = $factures_client[0];
    }
}

// ============================================
// RÉCUPÉRER TOUTES LES FACTURES
// ============================================
$factures = $pdo->query("
    SELECT f.*, c.numero_commande, c.nom_client, c.total as commande_total, c.email, c.telephone, c.statut as commande_statut
    FROM factures f 
    JOIN commandes c ON c.id = f.commande_id 
    ORDER BY f.created_at DESC
")->fetchAll();

// ============================================
// GROUPER LES FACTURES PAR CLIENT
// ============================================
$clients = [];
foreach($factures as $f) {
    $telephone = $f['telephone'] ?? 'inconnu';
    if (!isset($clients[$telephone])) {
        $clients[$telephone] = [
            'telephone' => $telephone,
            'nom_client' => $f['nom_client'] ?? 'Inconnu',
            'factures' => [],
            'total_factures' => 0,
            'total_montant' => 0,
            'derniere_facture' => $f['created_at'],
            'email_client' => $f['email'] ?? ''
        ];
    }
    $clients[$telephone]['factures'][] = $f;
    $clients[$telephone]['total_factures']++;
    $clients[$telephone]['total_montant'] += $f['montant_total'];
    
    if (strtotime($f['created_at']) > strtotime($clients[$telephone]['derniere_facture'])) {
        $clients[$telephone]['derniere_facture'] = $f['created_at'];
    }
}

usort($clients, function($a, $b) {
    return strtotime($b['derniere_facture']) - strtotime($a['derniere_facture']);
});

// Statistiques
$total_factures = count($factures);
$total_clients = count($clients);
$total_montant_global = 0;
$factures_payees = 0;
$factures_attente = 0;
$factures_annulees = 0;

foreach($factures as $f) {
    if($f['statut_paiement'] == 'payee') {
        $factures_payees++;
    } elseif($f['statut_paiement'] == 'annulee') {
        $factures_annulees++;
    } else {
        $factures_attente++;
    }
    $total_montant_global += $f['montant_total'];
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
            <div class="topbar-title">📄 Gestion des <span>Factures</span></div>
            <div class="topbar-breadcrumb">Finances → Factures</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <!-- ===== STATUS UPDATE ===== -->
        <div style="background:#F0F7FF;border-left:4px solid #2980B9;padding:12px 18px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;color:#1A3A5A;font-size:0.82rem;flex-wrap:wrap;">
            <i class="bi bi-arrow-repeat" style="color:#2980B9;font-size:1.2rem;"></i>
            <span><strong>Mise à jour automatique :</strong> Les factures sont marquées comme <strong>payées</strong> lorsque la commande est <strong>livrée, terminée ou confirmée</strong>.</span>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-file-earmark-text"></i></div>
                <div>
                    <div class="stat-val"><?= $total_factures ?></div>
                    <div class="stat-lbl">Total factures</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-val"><?= $factures_payees ?></div>
                    <div class="stat-lbl">Payées</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-orange"><i class="bi bi-clock"></i></div>
                <div>
                    <div class="stat-val"><?= $factures_attente ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-blue"><i class="bi bi-cash-coin"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_montant_global, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Montant total</div>
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
                    <table class="table-factures" style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Client</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Téléphone</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;">Factures</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:right;">Total</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:left;">Dernière facture</th>
                                <th style="padding:10px 14px;background:#F8F9FA;color:#5A6B7A;font-weight:600;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #E8ECF0;text-align:center;width:100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($clients)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state" style="text-align:center;padding:40px;color:#8A99AA;">
                                            <i class="bi bi-file-earmark-text" style="font-size:2.5rem;display:block;margin-bottom:10px;color:#D5D5D5;"></i>
                                            <p style="margin:0;font-size:0.85rem;">Aucune facture générée pour le moment</p>
                                            <span style="font-size:0.75rem;color:#bbb;display:block;margin-top:4px;">Les factures sont générées automatiquement après chaque commande</span>
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
                                                <?php if(!empty($client['email_client'])): ?>
                                                    <div style="font-size:0.6rem;color:#8A99AA;margin-top:1px;">
                                                        <i class="bi bi-envelope" style="font-size:0.55rem;"></i> <?= htmlspecialchars($client['email_client']) ?>
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
                                            <?= $client['total_factures'] ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:right;font-weight:600;color:#C8922A;font-size:0.85rem;">
                                        <?= number_format($client['total_montant'], 0, ',', ' ') ?> F
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;font-size:0.75rem;color:#8A99AA;">
                                        <i class="bi bi-calendar3" style="font-size:0.6rem;"></i>
                                        <?= date('d/m/Y H:i', strtotime($client['derniere_facture'])) ?>
                                    </td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #F0F2F5;vertical-align:middle;text-align:center;">
                                        <a href="factures.php?voir_client=1&telephone=<?= urlencode($client['telephone']) ?>" 
                                           class="btn-small blue" title="Voir toutes les factures du client"
                                           style="padding:4px 14px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:4px;background:rgba(41,128,185,0.1);color:#2980B9;transition:all 0.2s;border:none;cursor:pointer;">
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
<?php if($detail_client && !empty($factures_client)): ?>
<div class="modal-overlay active" id="modalDetail" onclick="if(event.target===this) closeModal()">
    <div class="modal-content" style="max-width:750px;width:95%;max-height:90vh;overflow-y:auto;padding:30px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-family:'Playfair Display',serif;font-size:1.3rem;margin:0;">
                👤 Factures de <span style="color:#C8922A;"><?= htmlspecialchars($detail_client['nom_client'] ?? 'Client') ?></span>
            </h3>
            <button onclick="closeModal()" style="background:none;border:none;font-size:1.8rem;cursor:pointer;color:#999;transition:transform 0.3s;line-height:1;" onmouseover="this.style.transform='rotate(90deg)'" onmouseout="this.style.transform='rotate(0deg)'">&times;</button>
        </div>
        
        <div style="background:#F8F9FA;padding:12px 16px;border-radius:8px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:12px 24px;font-size:0.85rem;color:#5A6B7A;border:1px solid #E8ECF0;">
            <span><i class="bi bi-telephone" style="color:#C8922A;"></i> <?= htmlspecialchars($detail_client['telephone'] ?? 'Non renseigné') ?></span>
            <?php if(!empty($detail_client['email'])): ?>
            <span><i class="bi bi-envelope" style="color:#C8922A;"></i> <?= htmlspecialchars($detail_client['email']) ?></span>
            <?php endif; ?>
            <span><i class="bi bi-file-earmark-text" style="color:#C8922A;"></i> <?= count($factures_client) ?> facture(s)</span>
            <span style="font-weight:600;color:#C8922A;"><i class="bi bi-cash" style="color:#C8922A;"></i> Total : <?= number_format(array_sum(array_column($factures_client, 'montant_total')), 0, ',', ' ') ?> F</span>
        </div>
        
        <?php foreach($factures_client as $f): 
            $badge_class = 'statut-en_attente';
            $badge_icon = 'bi-clock';
            $badge_text = 'En attente';
            if($f['statut_paiement'] == 'payee') {
                $badge_class = 'statut-confirmee';
                $badge_icon = 'bi-check-circle-fill';
                $badge_text = 'Payée';
            } elseif($f['statut_paiement'] == 'annulee') {
                $badge_class = 'statut-annulee';
                $badge_icon = 'bi-x-circle-fill';
                $badge_text = 'Annulée';
            }
        ?>
        <div style="background:#FFFFFF;border-radius:12px;padding:16px 20px;margin-bottom:12px;border:1px solid #E8ECF0;transition:all 0.3s;" onmouseover="this.style.borderColor='#C8922A';this.style.boxShadow='0 4px 20px rgba(200,146,42,0.08)'" onmouseout="this.style.borderColor='#E8ECF0';this.style.boxShadow='none'">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:#C8922A;"><?= htmlspecialchars($f['numero_facture']) ?></span>
                    <span class="badge-status <?= $badge_class ?>" style="padding:3px 12px;border-radius:20px;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;display:inline-flex;align-items:center;gap:4px;background:<?= $f['statut_paiement'] == 'payee' ? '#E8F5E9' : ($f['statut_paiement'] == 'annulee' ? '#FBE9E7' : '#FEF6E6') ?>;color:<?= $f['statut_paiement'] == 'payee' ? '#2E7D32' : ($f['statut_paiement'] == 'annulee' ? '#C62828' : '#E67E22') ?>;">
                        <i class="bi <?= $badge_icon ?>"></i> <?= $badge_text ?>
                    </span>
                    <span style="font-size:0.65rem;color:#8A99AA;background:#F8F9FA;padding:2px 10px;border-radius:10px;">
                        <i class="bi bi-box"></i> <?= $f['commande_statut'] ?? 'inconnu' ?>
                    </span>
                </div>
                <div style="font-size:0.75rem;color:#8A99AA;">
                    <i class="bi bi-calendar3"></i> <?= date('d/m/Y H:i', strtotime($f['created_at'])) ?>
                </div>
            </div>
            
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid #F0F2F5;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:10px;">
                <div style="font-size:0.85rem;color:#5A6B7A;">
                    <strong>Commande :</strong> <?= htmlspecialchars($f['numero_commande'] ?? 'N/A') ?>
                </div>
                <div style="font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;color:#C8922A;">
                    <?= number_format($f['montant_total'], 0, ',', ' ') ?> F
                </div>
            </div>
            
            <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
                <?php 
                $pdf_existe = !empty($f['fichier_pdf']) && file_exists('../uploads/factures/'.$f['fichier_pdf']);
                ?>
                <?php if($pdf_existe): ?>
                <a href="../uploads/factures/<?= $f['fichier_pdf'] ?>" target="_blank" class="btn-small red" style="padding:6px 16px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:5px;background:rgba(231,76,60,0.1);color:#E74C3C;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='#E74C3C';this.style.color='#fff'" onmouseout="this.style.background='rgba(231,76,60,0.1)';this.style.color='#E74C3C'">
                        <i class="bi bi-file-pdf"></i> Télécharger PDF
                    </a>
                <?php else: ?>
                <a href="generer_facture.php?id=<?= $f['commande_id'] ?>" class="btn-small or" style="padding:6px 16px;border-radius:6px;font-size:0.7rem;text-decoration:none;display:inline-flex;align-items:center;gap:5px;background:rgba(200,146,42,0.1);color:#C8922A;transition:all 0.2s;border:none;cursor:pointer;" onmouseover="this.style.background='#C8922A';this.style.color='#fff'" onmouseout="this.style.background='rgba(200,146,42,0.1)';this.style.color='#C8922A'">
                        <i class="bi bi-plus-circle"></i> Générer la facture
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align:center;margin-top:20px;">
            <a href="factures.php" class="btn-admin btn-primary" style="padding:10px 28px;justify-content:center;">
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
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>