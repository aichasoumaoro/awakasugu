<?php
// ============================================
// GESTION DES LIVRAISONS - AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion");
}

// ============================================
// CRÉER LA TABLE DE LIVRAISONS
// ============================================
$pdo->exec("
    CREATE TABLE IF NOT EXISTS livraisons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        commande_id INT NOT NULL,
        livreur_nom VARCHAR(100) DEFAULT NULL,
        livreur_telephone VARCHAR(20) DEFAULT NULL,
        statut ENUM('en_attente', 'en_cours', 'livree', 'probleme') DEFAULT 'en_attente',
        date_livraison DATE DEFAULT NULL,
        adresse_livraison TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ============================================
// AJOUTER UNE LIVRAISON
// ============================================
if (isset($_POST['ajouter_livraison'])) {
    $commande_id = (int)$_POST['commande_id'];
    $livreur_nom = trim($_POST['livreur_nom']);
    $livreur_telephone = trim($_POST['livreur_telephone']);
    $date_livraison = $_POST['date_livraison'];
    $adresse = trim($_POST['adresse_livraison']);
    $notes = trim($_POST['notes']);
    
    $stmt = $pdo->prepare("
        INSERT INTO livraisons (commande_id, livreur_nom, livreur_telephone, date_livraison, adresse_livraison, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$commande_id, $livreur_nom, $livreur_telephone, $date_livraison, $adresse, $notes]);
    
    // Mettre à jour le statut de la commande
    $pdo->prepare("UPDATE commandes SET statut = 'en_livraison' WHERE id = ?")->execute([$commande_id]);
    
    $_SESSION['message_livraison'] = 'Livraison planifiée avec succès !';
    header('Location: livraisons.php');
    exit;
}

// ============================================
// SUPPRIMER/MARQUER LIVRÉE
// ============================================
if (isset($_GET['livree']) && is_numeric($_GET['livree'])) {
    $id = (int)$_GET['livree'];
    $pdo->prepare("UPDATE livraisons SET statut = 'livree' WHERE id = ?")->execute([$id]);
    $stmt = $pdo->prepare("SELECT commande_id FROM livraisons WHERE id = ?");
    $stmt->execute([$id]);
    $liv = $stmt->fetch();
    if ($liv) {
        $pdo->prepare("UPDATE commandes SET statut = 'livree' WHERE id = ?")->execute([$liv['commande_id']]);
    }
    $_SESSION['message_livraison'] = 'Livraison marquée comme effectuée !';
    header('Location: livraisons.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================
$commandes = $pdo->query("
    SELECT c.*, cl.nom as client_nom, cl.telephone as client_telephone
    FROM commandes c
    LEFT JOIN clients cl ON cl.id = c.client_id
    WHERE c.statut IN ('confirmee', 'en_preparation', 'en_livraison')
    ORDER BY c.created_at DESC
    LIMIT 50
")->fetchAll();

$livraisons = $pdo->query("
    SELECT l.*, c.numero_commande, cl.nom as client_nom
    FROM livraisons l
    LEFT JOIN commandes c ON c.id = l.commande_id
    LEFT JOIN clients cl ON cl.id = c.client_id
    ORDER BY l.created_at DESC
")->fetchAll();

$message = $_SESSION['message_livraison'] ?? '';
unset($_SESSION['message_livraison']);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="main">

    <div class="topbar">
        <div>
            <div class="topbar-title">🚚 Gestion des <span>Livraisons</span></div>
            <div class="topbar-breadcrumb">Administration → Livraisons</div>
        </div>
    </div>

    <div class="content">

        <?php if($message): ?>
            <div class="alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <?php
        $total_en_attente = $pdo->query("SELECT COUNT(*) FROM livraisons WHERE statut = 'en_attente'")->fetchColumn();
        $total_en_cours = $pdo->query("SELECT COUNT(*) FROM livraisons WHERE statut = 'en_cours'")->fetchColumn();
        $total_livrees = $pdo->query("SELECT COUNT(*) FROM livraisons WHERE statut = 'livree'")->fetchColumn();
        ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-clock-history"></i></div>
                <div><div class="stat-val"><?= $total_en_attente ?></div><div class="stat-lbl">En attente</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-truck"></i></div>
                <div><div class="stat-val"><?= $total_en_cours ?></div><div class="stat-lbl">En cours</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-check-circle"></i></div>
                <div><div class="stat-val"><?= $total_livrees ?></div><div class="stat-lbl">Livrées</div></div>
            </div>
        </div>

        <!-- ===== PLANIFIER UNE LIVRAISON ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-plus-circle"></i> Planifier une livraison</div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:15px;">
                        <div class="form-group">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Commande</label>
                            <select name="commande_id" class="form-control" required style="padding:10px;border:1.5px solid #E0E0E0;border-radius:8px;width:100%;">
                                <option value="">Sélectionner une commande</option>
                                <?php foreach($commandes as $c): ?>
                                    <option value="<?= $c['id'] ?>">
                                        #<?= htmlspecialchars($c['numero_commande']) ?> - <?= htmlspecialchars($c['client_nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Date de livraison</label>
                            <input type="date" name="date_livraison" class="form-control" style="padding:10px;border:1.5px solid #E0E0E0;border-radius:8px;width:100%;" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                        </div>
                        <div class="form-group" style="display:flex;align-items:flex-end;">
                            <button type="submit" name="ajouter_livraison" class="btn-admin btn-primary" style="width:100%;padding:10px;">
                                <i class="bi bi-plus-circle"></i> Planifier
                            </button>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;margin-top:10px;">
                        <div class="form-group">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Livreur</label>
                            <input type="text" name="livreur_nom" class="form-control" placeholder="Nom du livreur" style="padding:10px;border:1.5px solid #E0E0E0;border-radius:8px;width:100%;">
                        </div>
                        <div class="form-group">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Téléphone livreur</label>
                            <input type="text" name="livreur_telephone" class="form-control" placeholder="Téléphone" style="padding:10px;border:1.5px solid #E0E0E0;border-radius:8px;width:100%;">
                        </div>
                        <div class="form-group">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Adresse de livraison</label>
                            <input type="text" name="adresse_livraison" class="form-control" placeholder="Adresse" style="padding:10px;border:1.5px solid #E0E0E0;border-radius:8px;width:100%;">
                        </div>
                    </div>
                    <div style="margin-top:10px;">
                        <input type="text" name="notes" class="form-control" placeholder="Notes sur la livraison..." style="padding:10px;border:1.5px solid #E0E0E0;border-radius:8px;width:100%;">
                    </div>
                </form>
            </div>
        </div>

        <!-- ===== LISTE DES LIVRAISONS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Historique des livraisons</div>
                <div class="text-muted"><?= count($livraisons) ?> livraison(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-commandes">
                        <thead>
                            <tr>
                                <th>N° Commande</th>
                                <th>Client</th>
                                <th>Livreur</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($livraisons)): ?>
                                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-truck"></i><p>Aucune livraison</p></div></td></tr>
                            <?php else: ?>
                                <?php foreach($livraisons as $l): 
                                    $statutLabels = [
                                        'en_attente' => 'En attente',
                                        'en_cours' => 'En cours',
                                        'livree' => 'Livrée',
                                        'probleme' => 'Problème'
                                    ];
                                    $statutColors = [
                                        'en_attente' => '#E67E22',
                                        'en_cours' => '#2980B9',
                                        'livree' => '#27AE60',
                                        'probleme' => '#E74C3C'
                                    ];
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($l['numero_commande'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($l['client_nom'] ?? 'Inconnu') ?></td>
                                    <td><?= htmlspecialchars($l['livreur_nom'] ?? '-') ?></td>
                                    <td><?= $l['date_livraison'] ? date('d/m/Y', strtotime($l['date_livraison'])) : '-' ?></td>
                                    <td>
                                        <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.65rem;font-weight:600;background:<?= $statutColors[$l['statut']] ?>20;color:<?= $statutColors[$l['statut']] ?>;">
                                            <?= $statutLabels[$l['statut']] ?? $l['statut'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:4px;">
                                            <?php if($l['statut'] != 'livree' && $l['statut'] != 'probleme'): ?>
                                                <a href="livraisons.php?livree=<?= $l['id'] ?>" class="btn-small green" onclick="return confirm('Marquer comme livrée ?')">
                                                    <i class="bi bi-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="commande_detail.php?id=<?= $l['commande_id'] ?>" class="btn-small blue">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>