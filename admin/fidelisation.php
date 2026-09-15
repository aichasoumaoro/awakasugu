<?php
// ============================================
// FIDÉLISATION - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$admin_info = getAdminInfo();
$admin_role = $admin_info['role'] ?? 'admin';
$admin_nom = $admin_info['nom'] ?? 'Awa Doumbia';
$admin_id = $admin_info['id'] ?? 0;

$page_title = 'Fidélisation';

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
// CREER LES TABLES (CORRIGÉ)
// ============================================
try {
    // Table des points de fidélité
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS points_fidelite (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            points INT DEFAULT 0,
            points_utilises INT DEFAULT 0,
            total_points INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_client (client_id),
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Table de l'historique des points
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS historique_points (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            points INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            reference_id INT DEFAULT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_client (client_id),
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch(PDOException $e) {
    // Ignorer les erreurs si les tables existent déjà
}

// ============================================
// AJOUTER DES POINTS
// ============================================
if (isset($_POST['ajouter_points'])) {
    $client_id = (int)$_POST['client_id'];
    $points = (int)$_POST['points'];
    $description = trim($_POST['description'] ?? 'Points ajoutés manuellement');
    
    if ($client_id > 0 && $points > 0) {
        // Vérifier si le client existe
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        if (!$stmt->fetch()) {
            $_SESSION['message_fidelite'] = 'Client introuvable !';
            header('Location: fidelisation.php');
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id FROM points_fidelite WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $pdo->prepare("UPDATE points_fidelite SET points = points + ?, total_points = total_points + ? WHERE client_id = ?")
                ->execute([$points, $points, $client_id]);
        } else {
            $pdo->prepare("INSERT INTO points_fidelite (client_id, points, total_points) VALUES (?, ?, ?)")
                ->execute([$client_id, $points, $points]);
        }
        
        $pdo->prepare("INSERT INTO historique_points (client_id, points, type, description) VALUES (?, ?, 'ajout', ?)")
            ->execute([$client_id, $points, $description]);
        
        $_SESSION['message_fidelite'] = $points . ' points ajoutés au client !';
    }
    header('Location: fidelisation.php');
    exit;
}

// ============================================
// UTILISER DES POINTS
// ============================================
if (isset($_POST['utiliser_points'])) {
    $client_id = (int)$_POST['client_id'];
    $points = (int)$_POST['points_utilises'];
    $description = trim($_POST['description'] ?? 'Points utilisés');
    
    if ($client_id > 0 && $points > 0) {
        $stmt = $pdo->prepare("SELECT points FROM points_fidelite WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $pts = $stmt->fetch();
        
        if ($pts && $pts['points'] >= $points) {
            $pdo->prepare("UPDATE points_fidelite SET points = points - ?, points_utilises = points_utilises + ? WHERE client_id = ?")
                ->execute([$points, $points, $client_id]);
            
            $pdo->prepare("INSERT INTO historique_points (client_id, points, type, description) VALUES (?, ?, 'utilisation', ?)")
                ->execute([$client_id, -$points, $description]);
            
            $_SESSION['message_fidelite'] = $points . ' points utilisés !';
        } else {
            $_SESSION['message_fidelite'] = 'Points insuffisants !';
        }
    }
    header('Location: fidelisation.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================
$clients = $pdo->query("
    SELECT c.*, 
           pf.points, pf.total_points, pf.points_utilises,
           (SELECT COUNT(*) FROM commandes WHERE client_id = c.id) as nb_commandes,
           (SELECT COALESCE(SUM(total), 0) FROM commandes WHERE client_id = c.id AND statut IN ('confirmee', 'livree', 'terminee')) as total_achats
    FROM clients c
    LEFT JOIN points_fidelite pf ON pf.client_id = c.id
    ORDER BY (pf.points) DESC
    LIMIT 50
")->fetchAll();

$message = $_SESSION['message_fidelite'] ?? '';
unset($_SESSION['message_fidelite']);

// Statistiques
$total_clients_avec_points = $pdo->query("SELECT COUNT(*) FROM points_fidelite WHERE points > 0")->fetchColumn();
$total_points_distribues = $pdo->query("SELECT COALESCE(SUM(total_points), 0) FROM points_fidelite")->fetchColumn();
$total_points_utilises = $pdo->query("SELECT COALESCE(SUM(points_utilises), 0) FROM points_fidelite")->fetchColumn();

// ============================================
// INCLUSION DU HEADER ET DE LA SIDEBAR
// ============================================
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">

    <div class="topbar">
        <div>
            <div class="topbar-title">⭐ Programme <span>Fidélité</span></div>
            <div class="topbar-breadcrumb">Administration → Fidélisation</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <div class="content">

        <?php if($message): ?>
            <div class="alert-success" style="background:#D4EDDA;color:#155724;padding:12px 18px;border-radius:10px;margin-bottom:20px;border-left:4px solid #28A745;">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row" style="grid-template-columns: repeat(3, 1fr);">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-people"></i></div>
                <div>
                    <div class="stat-val"><?= $total_clients_avec_points ?></div>
                    <div class="stat-lbl">Clients fidélisés</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-star"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_points_distribues, 0, ',', ' ') ?></div>
                    <div class="stat-lbl">Points distribués</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-red"><i class="bi bi-star-half"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_points_utilises, 0, ',', ' ') ?></div>
                    <div class="stat-lbl">Points utilisés</div>
                </div>
            </div>
        </div>

        <!-- ===== LISTE DES CLIENTS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-trophy"></i> Classement des clients fidèles</div>
                <div style="font-size:0.7rem;color:#8A99AA;"><?= count($clients) ?> clients</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-commandes">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Téléphone</th>
                                <th>Commandes</th>
                                <th>Total achats</th>
                                <th>Points</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($clients)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state" style="text-align:center;padding:30px;">
                                            <i class="bi bi-people" style="font-size:2rem;color:#ccc;display:block;margin-bottom:10px;"></i>
                                            <p style="color:#999;">Aucun client enregistré</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($clients as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['nom']) ?></strong></td>
                                    <td><?= htmlspecialchars($c['telephone'] ?? '-') ?></td>
                                    <td><?= $c['nb_commandes'] ?? 0 ?></td>
                                    <td><?= number_format($c['total_achats'] ?? 0, 0, ',', ' ') ?> F</td>
                                    <td>
                                        <span style="font-weight:700;color:#C8922A;font-size:1.1rem;"><?= $c['points'] ?? 0 ?></span>
                                        <span style="font-size:0.7rem;color:#999;">pts</span>
                                    </td>
                                    <td style="text-align:center;">
                                        <button onclick="openModal(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nom']) ?>', <?= $c['points'] ?? 0 ?>)" 
                                                class="btn-small blue" style="padding:4px 10px;background:#2980B9;color:#fff;border:none;border-radius:4px;cursor:pointer;">
                                            <i class="bi bi-plus-circle"></i> Gérer
                                        </button>
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

<!-- ===== MODAL ===== -->
<div id="modalPoints" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:30px;max-width:400px;width:90%;">
        <h3 style="margin-bottom:15px;color:#C8922A;"><i class="bi bi-star"></i> Gérer les points</h3>
        <form method="POST">
            <input type="hidden" name="client_id" id="modalClientId">
            <div style="margin-bottom:15px;">
                <label style="display:block;font-size:0.8rem;font-weight:600;color:#666;">Client</label>
                <span id="modalClientNom" style="font-weight:600;"></span>
                <span id="modalPointsActuels" style="display:block;font-size:0.9rem;color:#888;">Points: 0</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div>
                    <label style="display:block;font-size:0.7rem;font-weight:600;color:#666;">Ajouter points</label>
                    <input type="number" name="points" style="width:100%;padding:8px;border:1.5px solid #E0E0E0;border-radius:8px;" value="10" min="1">
                    <button type="submit" name="ajouter_points" style="width:100%;margin-top:5px;padding:6px;background:#27AE60;color:#fff;border:none;border-radius:8px;cursor:pointer;">
                        <i class="bi bi-plus"></i> Ajouter
                    </button>
                </div>
                <div>
                    <label style="display:block;font-size:0.7rem;font-weight:600;color:#666;">Utiliser points</label>
                    <input type="number" name="points_utilises" style="width:100%;padding:8px;border:1.5px solid #E0E0E0;border-radius:8px;" value="5" min="1">
                    <button type="submit" name="utiliser_points" style="width:100%;margin-top:5px;padding:6px;background:#E67E22;color:#fff;border:none;border-radius:8px;cursor:pointer;">
                        <i class="bi bi-dash"></i> Utiliser
                    </button>
                </div>
            </div>
            <input type="text" name="description" style="width:100%;padding:8px;border:1.5px solid #E0E0E0;border-radius:8px;margin-top:10px;" placeholder="Description...">
            <button type="button" onclick="closeModal()" style="width:100%;margin-top:10px;padding:8px;border:1px solid #ddd;border-radius:8px;background:#fff;cursor:pointer;">Fermer</button>
        </form>
    </div>
</div>

<script>
function openModal(clientId, nom, points) {
    document.getElementById('modalClientId').value = clientId;
    document.getElementById('modalClientNom').textContent = nom;
    document.getElementById('modalPointsActuels').textContent = 'Points actuels: ' + points;
    document.getElementById('modalPoints').style.display = 'flex';
}
function closeModal() {
    document.getElementById('modalPoints').style.display = 'none';
}
document.addEventListener('click', function(e) {
    if (e.target === document.getElementById('modalPoints')) closeModal();
});
</script>

<?php include 'includes/footer.php'; ?>