<?php
// ============================================
// RÉSERVATIONS - ADMIN AWA KA SUGU
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

$page_title = 'Gestion des Réservations';

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
// CHANGER LE STATUT D'UNE RÉSERVATION
// ============================================
if (isset($_GET['statut']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $statut = $_GET['statut'];
    $statuts_valides = ['en_attente', 'confirmee', 'terminee', 'annulee'];
    
    if (in_array($statut, $statuts_valides)) {
        $pdo->prepare("UPDATE reservations SET statut = ? WHERE id = ?")->execute([$statut, $id]);
        $_SESSION['message_reservation'] = 'Statut mis à jour avec succès !';
        header('Location: reservations.php');
        exit;
    }
}

// ============================================
// SUPPRIMER UNE RÉSERVATION
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM reservations WHERE id = ?")->execute([$id]);
    $_SESSION['message_reservation'] = 'Réservation supprimée avec succès !';
    header('Location: reservations.php');
    exit;
}

// ============================================
// VOIR TOUTES LES RÉSERVATIONS D'UN CLIENT
// ============================================
$detail_client = null;
$reservations_client = [];
if (isset($_GET['voir_client']) && isset($_GET['telephone'])) {
    $telephone = $_GET['telephone'];
    
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE telephone = ? ORDER BY date_reservation DESC, heure_reservation DESC");
    $stmt->execute([$telephone]);
    $reservations_client = $stmt->fetchAll();
    
    if (!empty($reservations_client)) {
        $detail_client = $reservations_client[0];
    }
}

// ============================================
// RÉCUPÉRER TOUTES LES RÉSERVATIONS
// ============================================
$reservations = $pdo->query("SELECT * FROM reservations ORDER BY date_reservation DESC, heure_reservation DESC")->fetchAll();

// ============================================
// GROUPER LES RÉSERVATIONS PAR CLIENT (TÉLÉPHONE)
// ============================================
$clients = [];
foreach($reservations as $r) {
    $telephone = $r['telephone'] ?? 'inconnu';
    if (!isset($clients[$telephone])) {
        $clients[$telephone] = [
            'telephone' => $telephone,
            'nom_client' => $r['nom_client'] ?? 'Inconnu',
            'reservations' => [],
            'total_reservations' => 0,
            'derniere_reservation' => $r['date_reservation'] . ' ' . $r['heure_reservation'],
            'statut_global' => 'en_attente'
        ];
    }
    $clients[$telephone]['reservations'][] = $r;
    $clients[$telephone]['total_reservations']++;
    
    // Mettre à jour la dernière réservation
    $date_resa = $r['date_reservation'] . ' ' . $r['heure_reservation'];
    if ($date_resa > $clients[$telephone]['derniere_reservation']) {
        $clients[$telephone]['derniere_reservation'] = $date_resa;
    }
    
    // Déterminer le statut global (le plus avancé)
    $statut_priority = ['terminee' => 4, 'confirmee' => 3, 'en_attente' => 2, 'annulee' => 0];
    if (!isset($clients[$telephone]['statut_global']) || 
        $statut_priority[$r['statut']] > $statut_priority[$clients[$telephone]['statut_global']]) {
        $clients[$telephone]['statut_global'] = $r['statut'];
    }
}

// Trier les clients par date de dernière réservation
usort($clients, function($a, $b) {
    return strtotime($b['derniere_reservation']) - strtotime($a['derniere_reservation']);
});

// Statistiques
$total_reservations = count($reservations);
$reservations_attente = $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn();
$reservations_aujourdhui = $pdo->query("SELECT COUNT(*) FROM reservations WHERE date_reservation = CURDATE()")->fetchColumn();

$message = $_SESSION['message_reservation'] ?? '';
unset($_SESSION['message_reservation']);

$statuts = [
    'en_attente' => ['label' => 'En attente', 'class' => 'statut-en_attente', 'icon' => 'bi-clock-history'],
    'confirmee' => ['label' => 'Confirmée', 'class' => 'statut-confirmee', 'icon' => 'bi-check-circle'],
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
            <div class="topbar-title">📅 Gestion des <span>Réservations</span></div>
            <div class="topbar-breadcrumb">Restaurant → Réservations</div>
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
                <div class="stat-icon ic-or"><i class="bi bi-calendar-check"></i></div>
                <div>
                    <div class="stat-val"><?= $total_reservations ?></div>
                    <div class="stat-lbl">Total réservations</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="stat-val"><?= $reservations_attente ?></div>
                    <div class="stat-lbl">En attente</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-calendar-day"></i></div>
                <div>
                    <div class="stat-val"><?= $reservations_aujourdhui ?></div>
                    <div class="stat-lbl">Aujourd'hui</div>
                </div>
            </div>
        </div>

        <!-- ===== LISTE DES CLIENTS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-people"></i> Clients avec leurs réservations</div>
                <div style="font-size:0.7rem;color:#8A99AA;"><?= count($clients) ?> client(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-produits">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Téléphone</th>
                                <th style="text-align:center;">Réservations</th>
                                <th>Dernière réservation</th>
                                <th>Statut</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($clients)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="bi bi-calendar-x"></i>
                                            <p>Aucune réservation</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($clients as $client): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($client['nom_client']) ?></strong></td>
                                    <td><?= htmlspecialchars($client['telephone']) ?></td>
                                    <td style="text-align:center;">
                                        <span class="badge-nb-reservations"><?= $client['total_reservations'] ?></span>
                                    </td>
                                    <td style="font-size:0.8rem;color:#8A99AA;">
                                        <?= date('d/m/Y H:i', strtotime($client['derniere_reservation'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= $statuts[$client['statut_global']]['class'] ?? '' ?>">
                                            <i class="bi <?= $statuts[$client['statut_global']]['icon'] ?? 'bi-circle' ?>"></i>
                                            <?= $statuts[$client['statut_global']]['label'] ?? $client['statut_global'] ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="reservations.php?voir_client=1&telephone=<?= urlencode($client['telephone']) ?>" 
                                           class="btn-small blue" title="Voir toutes les réservations">
                                            <i class="bi bi-eye"></i>
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
<?php if($detail_client && !empty($reservations_client)): ?>
<div class="modal-overlay active" id="modalDetail" onclick="if(event.target===this) closeModal()">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
        <div class="modal-title">
            👤 Réservations de <span><?= htmlspecialchars($detail_client['nom_client'] ?? 'Client') ?></span>
        </div>
        <div class="modal-subtitle">
            <i class="bi bi-telephone"></i> <?= htmlspecialchars($detail_client['telephone'] ?? 'Téléphone non renseigné') ?>
            <span style="margin-left:20px;">
                <i class="bi bi-calendar-check"></i> <?= count($reservations_client) ?> réservation(s)
            </span>
        </div>
        
        <?php foreach($reservations_client as $r): ?>
        <div class="reservation-item">
            <div class="reservation-header">
                <div>
                    <span class="badge-status <?= $statuts[$r['statut']]['class'] ?? '' ?>">
                        <i class="bi <?= $statuts[$r['statut']]['icon'] ?? 'bi-circle' ?>"></i>
                        <?= $statuts[$r['statut']]['label'] ?? $r['statut'] ?>
                    </span>
                    <span style="font-weight:600;color:#C8922A;margin-left:10px;">
                        <?= $r['nb_personnes'] ?? 1 ?> personne(s)
                    </span>
                </div>
                <div class="reservation-date"><?= date('d/m/Y H:i', strtotime($r['date_reservation'] . ' ' . $r['heure_reservation'])) ?></div>
            </div>
            <div class="reservation-details">
                <i class="bi bi-geo-alt" style="color:#8A99AA;"></i> 
                <?= htmlspecialchars($r['notes'] ?? 'Aucune remarque') ?>
                <?php if(!empty($r['email'])): ?>
                    <br><i class="bi bi-envelope" style="color:#8A99AA;"></i> <?= htmlspecialchars($r['email']) ?>
                <?php endif; ?>
            </div>
            
            <!-- Actions : Changer le statut -->
            <div class="statut-actions">
                <?php foreach($statuts as $key => $s): ?>
                    <?php if($key != $r['statut']): ?>
                        <a href="reservations.php?statut=<?= $key ?>&id=<?= $r['id'] ?>" 
                           class="btn-statut <?= $s['class'] ?>"
                           onclick="return confirm('Changer le statut en <?= $s['label'] ?> ?')">
                            <?= $s['label'] ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <a href="reservations.php?supprimer=<?= $r['id'] ?>" 
                   class="btn-statut statut-annulee" 
                   onclick="return confirm('Supprimer cette réservation #<?= $r['id'] ?> ?')">
                    <i class="bi bi-trash3"></i> Supprimer
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align:center;margin-top:20px;">
            <a href="reservations.php" class="btn-admin btn-primary" style="justify-content:center;">
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
    window.location.href = 'reservations.php';
}
</script>