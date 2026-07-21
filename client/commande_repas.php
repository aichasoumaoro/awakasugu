<?php
// ============================================
// GESTION DES COMMANDES REPAS & GÂTEAUX - ADMIN
// ============================================

require_once 'session_config.php';

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
    die("Erreur : " . $e->getMessage());
}

// ============================================
// FONCTION POUR AJOUTER DES POINTS
// ============================================
function ajouterPoints($pdo, $client_id, $commande_id, $points, $action) {
    if ($points <= 0 || !$client_id) return false;
    
    try {
        // Vérifier si le client existe dans fidelite
        $stmt = $pdo->prepare("SELECT id FROM fidelite WHERE client_id = ?");
        $stmt->execute([$client_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO fidelite (client_id, points, total_points_gagnes) VALUES (?, 0, 0)");
            $stmt->execute([$client_id]);
        }
        
        // Ajouter les points
        $stmt = $pdo->prepare("
            UPDATE fidelite 
            SET points = points + ?, 
                total_points_gagnes = total_points_gagnes + ?
            WHERE client_id = ?
        ");
        $stmt->execute([$points, $points, $client_id]);
        
        // Ajouter dans l'historique
        $stmt = $pdo->prepare("
            INSERT INTO fidelite_historique (client_id, commande_id, points, action, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$client_id, $commande_id, $points, $action]);
        
        return true;
    } catch(PDOException $e) {
        error_log("Erreur ajout points : " . $e->getMessage());
        return false;
    }
}

// ============================================
// FONCTION POUR RETIRER DES POINTS
// ============================================
function retirerPoints($pdo, $client_id, $commande_id, $points, $action) {
    if ($points <= 0 || !$client_id) return false;
    
    try {
        // Retirer les points
        $stmt = $pdo->prepare("
            UPDATE fidelite 
            SET points = GREATEST(points - ?, 0)
            WHERE client_id = ?
        ");
        $stmt->execute([$points, $client_id]);
        
        // Ajouter dans l'historique
        $stmt = $pdo->prepare("
            INSERT INTO fidelite_historique (client_id, commande_id, points, action, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$client_id, $commande_id, -$points, $action]);
        
        return true;
    } catch(PDOException $e) {
        error_log("Erreur retrait points : " . $e->getMessage());
        return false;
    }
}

// ============================================
// FONCTION POUR CALCULER LES POINTS
// ============================================
function calculerPoints($total) {
    return floor($total / 1000);
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
        // Récupérer l'ancien statut et les infos de la commande
        if ($type == 'repas') {
            $stmt = $pdo->prepare("SELECT * FROM commandes_repas WHERE id = ?");
            $stmt->execute([$id]);
            $commande = $stmt->fetch();
            $ancien_statut = $commande['statut'] ?? 'en_attente';
            $client_id = $commande['client_id'] ?? null;
            $telephone = $commande['telephone'] ?? null;
            $total = $commande['total'] ?? 0;
            
            // Mettre à jour le statut
            $stmt = $pdo->prepare("UPDATE commandes_repas SET statut = ? WHERE id = ?");
            $stmt->execute([$nouveau_statut, $id]);
            
        } elseif ($type == 'gateau') {
            $stmt = $pdo->prepare("SELECT * FROM commandes_gateaux WHERE id = ?");
            $stmt->execute([$id]);
            $commande = $stmt->fetch();
            $ancien_statut = $commande['statut'] ?? 'en_attente';
            $client_id = $commande['client_id'] ?? null;
            $telephone = $commande['telephone'] ?? null;
            $total = $commande['prix'] ?? 0;
            
            $stmt = $pdo->prepare("UPDATE commandes_gateaux SET statut = ? WHERE id = ?");
            $stmt->execute([$nouveau_statut, $id]);
        }
        
        // ============================================
        // GESTION DES POINTS
        // ============================================
        $statuts_avec_points = ['livree', 'terminee'];
        $statuts_sans_points = ['annulee'];
        
        // Si on n'a pas de client_id, essayer de le trouver via le téléphone
        if (!$client_id && !empty($telephone)) {
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE telephone = ?");
            $stmt->execute([$telephone]);
            $client = $stmt->fetch();
            if ($client) {
                $client_id = $client['id'];
                // Mettre à jour la commande avec le client_id
                if ($type == 'repas') {
                    $stmt = $pdo->prepare("UPDATE commandes_repas SET client_id = ? WHERE id = ?");
                    $stmt->execute([$client_id, $id]);
                } elseif ($type == 'gateau') {
                    $stmt = $pdo->prepare("UPDATE commandes_gateaux SET client_id = ? WHERE id = ?");
                    $stmt->execute([$client_id, $id]);
                }
            }
        }
        
        $message = 'Statut mis à jour !';
        
        // Ajouter des points si la commande est livrée ou terminée
        if ($client_id && in_array($nouveau_statut, $statuts_avec_points) && !in_array($ancien_statut, $statuts_avec_points)) {
            $points = calculerPoints($total);
            if ($points > 0) {
                $action = "Commande #{$id} - " . $type . " - " . $nouveau_statut;
                if (ajouterPoints($pdo, $client_id, $id, $points, $action)) {
                    $message .= ' +' . $points . ' points gagnés !';
                }
            }
        }
        
        // Retirer des points si la commande est annulée
        if ($client_id && in_array($nouveau_statut, $statuts_sans_points) && in_array($ancien_statut, $statuts_avec_points)) {
            $points = calculerPoints($total);
            if ($points > 0) {
                $action = "Annulation commande #{$id} - " . $type;
                if (retirerPoints($pdo, $client_id, $id, $points, $action)) {
                    $message .= ' -' . $points . ' points retirés.';
                }
            }
        }
        
        $_SESSION['message_commande'] = $message;
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
    $_SESSION['message_commande'] = 'Commande supprimée !';
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
    
    // Récupérer les commandes repas du client
    $stmt = $pdo->prepare("SELECT *, 'repas' as type FROM commandes_repas WHERE telephone = ? ORDER BY created_at DESC");
    $stmt->execute([$telephone]);
    $commandes_repas_client = $stmt->fetchAll();
    
    // Récupérer les commandes gâteaux du client
    $stmt = $pdo->prepare("SELECT *, 'gateau' as type FROM commandes_gateaux WHERE telephone = ? ORDER BY created_at DESC");
    $stmt->execute([$telephone]);
    $commandes_gateaux_client = $stmt->fetchAll();
    
    $commandes_client = array_merge($commandes_repas_client, $commandes_gateaux_client);
    
    // Trier par date
    usort($commandes_client, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Récupérer les infos du client
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

// Commandes repas
$commandes_repas = $pdo->query("SELECT *, 'repas' as type FROM commandes_repas ORDER BY created_at DESC")->fetchAll();

// Commandes gâteaux
$commandes_gateaux = $pdo->query("SELECT *, 'gateau' as type FROM commandes_gateaux ORDER BY created_at DESC")->fetchAll();

// Fusionner les deux tableaux
$commandes = array_merge($commandes_repas, $commandes_gateaux);

// Trier par date décroissante
usort($commandes, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// ============================================
// GROUPER LES COMMANDES PAR CLIENT (TÉLÉPHONE)
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
    $clients[$telephone]['total_depense'] += ($c['type'] == 'gateau') ? ($c['prix'] ?? 0) : ($c['total'] ?? 0);
    
    // Mettre à jour la dernière commande
    if (strtotime($c['created_at']) > strtotime($clients[$telephone]['derniere_commande'])) {
        $clients[$telephone]['derniere_commande'] = $c['created_at'];
    }
}

// Trier les clients par date de dernière commande
usort($clients, function($a, $b) {
    return strtotime($b['derniere_commande']) - strtotime($a['derniere_commande']);
});

// Statistiques
$total_commandes = count($commandes);
$commandes_attente = 0;
$ca_repas_mois = 0;

foreach($commandes as $c) {
    if($c['statut'] == 'en_attente') $commandes_attente++;
    if(date('m', strtotime($c['created_at'])) == date('m')) {
        $ca_repas_mois += ($c['type'] == 'gateau') ? ($c['prix'] ?? 0) : ($c['total'] ?? 0);
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

$modes_paiement = [
    'livraison' => 'Paiement à la livraison',
    'orange_money' => 'Orange Money',
    'wave' => 'Wave',
    'moov_money' => 'Moov Money'
];
?>

<!-- Le reste du HTML reste identique à votre version -->
<!-- ... (gardez votre HTML existant) ... -->

<?php require_once '../includes/footer.php'; ?>