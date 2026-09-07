<?php
// ============================================
// MESSAGERIE ADMIN - Réception et réponse
// ============================================

// Session admin
session_name('ADMIN_SESSION');
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$admin_id = $_SESSION['admin_id'];
$admin_nom = $_SESSION['admin_nom'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

// Connexion BDD
$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// ============================================
// TRAITEMENT : ENVOI D'UN MESSAGE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_envoyer_message'])) {
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if ($conversation_id > 0 && !empty($message)) {
        $stmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, expediteur_type, expediteur_id, contenu, envoye_le)
            VALUES (?, 'admin', ?, ?, NOW())
        ");
        $stmt->execute([$conversation_id, $admin_id, $message]);
        
        $stmt = $pdo->prepare("UPDATE conversations SET mis_a_jour = NOW() WHERE id = ?");
        $stmt->execute([$conversation_id]);
        
        header('Location: messagerie.php?conv=' . $conversation_id);
        exit;
    }
}

// ============================================
// TRAITEMENT : FERMER UNE CONVERSATION
// ============================================
if (isset($_GET['fermer'])) {
    $conv_id = (int)$_GET['fermer'];
    $stmt = $pdo->prepare("UPDATE conversations SET statut = 'ferme' WHERE id = ?");
    $stmt->execute([$conv_id]);
    header('Location: messagerie.php');
    exit;
}

// ============================================
// RÉCUPÉRATION DES CONVERSATIONS
// ============================================
$conversations = [];
$stmt = $pdo->query("
    SELECT c.*, cl.nom as client_nom, cl.email as client_email,
           (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.lu = 0 AND m.expediteur_type = 'client') as non_lus
    FROM conversations c
    LEFT JOIN clients cl ON c.client_id = cl.id
    ORDER BY c.mis_a_jour DESC
");
$conversations = $stmt->fetchAll();

// ============================================
// RÉCUPÉRATION DE LA CONVERSATION ACTIVE
// ============================================
$conversation_active = null;
$messages = [];

if (isset($_GET['conv'])) {
    $conv_id = (int)$_GET['conv'];
    
    $stmt = $pdo->prepare("
        SELECT c.*, cl.nom as client_nom, cl.email as client_email, cl.telephone as client_telephone
        FROM conversations c
        LEFT JOIN clients cl ON c.client_id = cl.id
        WHERE c.id = ?
    ");
    $stmt->execute([$conv_id]);
    $conversation_active = $stmt->fetch();
    
    if ($conversation_active) {
        $stmt = $pdo->prepare("UPDATE messages SET lu = 1 WHERE conversation_id = ? AND expediteur_type = 'client' AND lu = 0");
        $stmt->execute([$conv_id]);
        
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   CASE WHEN m.expediteur_type = 'client' THEN cl.nom
                   ELSE ? END as expediteur_nom
            FROM messages m
            LEFT JOIN clients cl ON m.expediteur_id = cl.id
            WHERE m.conversation_id = ?
            ORDER BY m.envoye_le ASC
        ");
        $stmt->execute([$admin_nom, $conv_id]);
        $messages = $stmt->fetchAll();
    }
}

// ============================================
// INCLUSION DU HEADER ADMIN
// ============================================
$current_page = basename($_SERVER['PHP_SELF']);

$role_labels = [
    'super_admin' => 'Super Administrateur',
    'directeur' => 'Directrice',
    'admin' => 'Administratrice',
    'admin2' => 'Agente'
];

$role_colors = [
    'super_admin' => '#8E44AD',
    'directeur' => '#C8922A',
    'admin' => '#2980B9',
    'admin2' => '#7F8C8D'
];

$role_label_header = $role_labels[$admin_role] ?? 'Administrateur';
$role_color_header = $role_colors[$admin_role] ?? '#C8922A';

require_once 'includes/header.php';
?>

<div class="msg-wrapper">
    <!-- En-tête -->
    <div class="msg-header">
        <div class="msg-header-left">
            <i class="bi bi-chat-dots"></i>
            <div>
                <h2>Messagerie</h2>
                <span>Gérez vos conversations clients</span>
            </div>
        </div>
        <div class="msg-header-right">
            <span class="msg-count"><?= count($conversations) ?> conversations</span>
        </div>
    </div>

    <!-- Corps principal -->
    <div class="msg-body">
        
        <!-- Liste des conversations -->
        <div class="msg-sidebar">
            <div class="msg-sidebar-header">
                <span>Conversations</span>
                <span class="msg-badge-total"><?= count($conversations) ?></span>
            </div>
            
            <div class="msg-list">
                <?php if (!empty($conversations)): ?>
                    <?php foreach ($conversations as $conv): ?>
                        <?php
                        $stmt_dernier = $pdo->prepare("SELECT contenu, envoye_le FROM messages WHERE conversation_id = ? ORDER BY envoye_le DESC LIMIT 1");
                        $stmt_dernier->execute([$conv['id']]);
                        $dernier = $stmt_dernier->fetch();
                        
                        $dernier_msg = $dernier['contenu'] ?? 'Aucun message';
                        $dernier_date = date('d/m/Y H:i', strtotime($dernier['envoye_le'] ?? $conv['cree_le']));
                        ?>
                        <a href="messagerie.php?conv=<?= $conv['id'] ?>" class="msg-item <?= isset($conversation_active) && $conversation_active['id'] == $conv['id'] ? 'active' : '' ?>">
                            <div class="msg-item-top">
                                <span class="msg-client-name"><?= htmlspecialchars($conv['client_nom'] ?? 'Client') ?></span>
                                <?php if ($conv['non_lus'] > 0): ?>
                                    <span class="msg-non-lus"><?= $conv['non_lus'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="msg-item-bottom">
                                <span class="msg-preview"><?= htmlspecialchars(mb_substr($dernier_msg, 0, 35)) ?>...</span>
                                <span class="msg-date"><?= $dernier_date ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="msg-vide">
                        <i class="bi bi-inbox"></i>
                        <p>Aucune conversation</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Zone de chat -->
        <div class="msg-chat">
            <?php if ($conversation_active): ?>
                <!-- En-tête du chat -->
                <div class="msg-chat-header">
                    <div class="msg-chat-user">
                        <div class="msg-avatar"><?= strtoupper(mb_substr($conversation_active['client_nom'] ?? 'C', 0, 1)) ?></div>
                        <div>
                            <div class="msg-chat-nom"><?= htmlspecialchars($conversation_active['client_nom'] ?? 'Client') ?></div>
                            <div class="msg-chat-email"><?= htmlspecialchars($conversation_active['client_email'] ?? '') ?></div>
                        </div>
                    </div>
                    <button class="msg-fermer" onclick="confirm('Fermer cette conversation ?') ? window.location.href='messagerie.php?fermer=<?= $conversation_active['id'] ?>' : false">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>

                <!-- Messages -->
                <div class="msg-messages" id="msgMessages">
                    <?php foreach ($messages as $msg): ?>
                        <div class="msg-bulle <?= $msg['expediteur_type'] == 'client' ? 'client' : 'admin' ?>">
                            <div class="msg-contenu">
                                <?= nl2br(htmlspecialchars($msg['contenu'])) ?>
                            </div>
                            <div class="msg-meta">
                                <?= htmlspecialchars($msg['expediteur_nom']) ?> • <?= date('d/m/Y H:i', strtotime($msg['envoye_le'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Zone de saisie -->
                <div class="msg-input">
                    <form method="POST" class="msg-form">
                        <input type="hidden" name="action_envoyer_message" value="1">
                        <input type="hidden" name="conversation_id" value="<?= $conversation_active['id'] ?>">
                        <textarea name="message" id="msgTextarea" placeholder="Écrivez votre message..." required></textarea>
                        <button type="submit" class="msg-envoyer">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- État vide -->
                <div class="msg-vide-chat">
                    <i class="bi bi-chat-dots"></i>
                    <h3>Sélectionnez une conversation</h3>
                    <p>Choisissez une conversation à gauche pour voir les messages et répondre.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* ===== FIX : PLACER LE CONTENU À DROITE DE LA SIDEBAR NOIRE ===== */
.msg-wrapper {
    margin-left: 250px; /* Largeur de la sidebar admin noire */
    padding: 20px;
    background: #F5F6FA;
    min-height: calc(100vh - 60px);
    box-sizing: border-box;
}

/* En-tête */
.msg-header {
    background: white;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.msg-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.msg-header-left i {
    font-size: 1.5rem;
    color: #C8922A;
}

.msg-header-left h2 {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1A1A1A;
    margin: 0;
}

.msg-header-left span {
    font-size: 0.75rem;
    color: #8A99AA;
}

.msg-header-right .msg-count {
    background: #C8922A;
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Corps */
.msg-body {
    display: flex;
    gap: 15px;
    height: calc(100vh - 160px);
    min-height: 500px;
    max-height: 750px;
}

/* Sidebar */
.msg-sidebar {
    width: 300px;
    flex-shrink: 0;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #E8ECF0;
    display: flex;
    flex-direction: column;
}

.msg-sidebar-header {
    padding: 12px 15px;
    background: linear-gradient(135deg, #1A1A1A, #2D2D2D);
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.9rem;
    font-weight: 600;
}

.msg-badge-total {
    background: #C8922A;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
}

.msg-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.msg-item {
    display: block;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 4px;
    text-decoration: none;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.msg-item:hover {
    background: #F8F9FA;
}

.msg-item.active {
    background: rgba(200,146,42,0.08);
    border-color: rgba(200,146,42,0.2);
}

.msg-item-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.msg-client-name {
    font-weight: 600;
    color: #1A1A1A;
    font-size: 0.9rem;
}

.msg-non-lus {
    background: #E74C3C;
    color: white;
    font-size: 0.6rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 8px;
}

.msg-item-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.7rem;
    color: #8A99AA;
}

.msg-preview {
    max-width: 170px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.msg-date {
    font-size: 0.65rem;
    color: #B0B0B0;
    white-space: nowrap;
}

.msg-vide {
    text-align: center;
    padding: 30px 10px;
    color: #B0B0B0;
}

.msg-vide i {
    font-size: 2rem;
    margin-bottom: 8px;
    display: block;
}

.msg-vide p {
    font-size: 0.8rem;
}

/* Chat */
.msg-chat {
    flex: 1;
    background: white;
    border-radius: 10px;
    border: 1px solid #E8ECF0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.msg-chat-header {
    padding: 10px 15px;
    border-bottom: 1px solid #F0F2F5;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.msg-chat-user {
    display: flex;
    align-items: center;
    gap: 10px;
}

.msg-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
}

.msg-chat-nom {
    font-weight: 600;
    font-size: 0.9rem;
    color: #1A1A1A;
}

.msg-chat-email {
    font-size: 0.7rem;
    color: #8A99AA;
}

.msg-fermer {
    background: transparent;
    border: 1px solid #E0E0E0;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.75rem;
    color: #666;
    cursor: pointer;
    transition: all 0.2s;
}

.msg-fermer:hover {
    border-color: #E74C3C;
    color: #E74C3C;
}

/* Messages */
.msg-messages {
    flex: 1;
    padding: 15px;
    overflow-y: auto;
    background: #F8F9FA;
}

.msg-bulle {
    display: flex;
    flex-direction: column;
    margin-bottom: 10px;
}

.msg-bulle.client {
    align-items: flex-start;
}

.msg-bulle.admin {
    align-items: flex-end;
}

.msg-contenu {
    max-width: 75%;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 0.85rem;
    line-height: 1.4;
}

.msg-bulle.client .msg-contenu {
    background: white;
    color: #1A1A1A;
    border: 1px solid #E8ECF0;
    border-bottom-left-radius: 4px;
}

.msg-bulle.admin .msg-contenu {
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    border-bottom-right-radius: 4px;
}

.msg-meta {
    font-size: 0.6rem;
    color: #8A99AA;
    margin-top: 3px;
    padding: 0 4px;
}

.msg-bulle.admin .msg-meta {
    text-align: right;
}

/* Input */
.msg-input {
    padding: 10px 15px;
    border-top: 1px solid #F0F2F5;
    background: white;
}

.msg-form {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.msg-form textarea {
    flex: 1;
    border: 1.5px solid #E0E0E0;
    border-radius: 18px;
    padding: 8px 14px;
    font-size: 0.85rem;
    font-family: inherit;
    resize: none;
    min-height: 38px;
    max-height: 80px;
    outline: none;
}

.msg-form textarea:focus {
    border-color: #C8922A;
}

.msg-envoyer {
    background: #C8922A;
    color: white;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s;
    flex-shrink: 0;
}

.msg-envoyer:hover {
    background: #9A6E1A;
}

/* État vide */
.msg-vide-chat {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px;
    text-align: center;
}

.msg-vide-chat i {
    font-size: 3rem;
    color: #D5D5D5;
    margin-bottom: 10px;
}

.msg-vide-chat h3 {
    color: #8A99AA;
    font-size: 1rem;
    margin-bottom: 5px;
}

.msg-vide-chat p {
    color: #B0B0B0;
    font-size: 0.8rem;
    max-width: 250px;
}

/* Responsive */
@media (max-width: 900px) {
    .msg-wrapper {
        margin-left: 0;
    }
    
    .msg-body {
        flex-direction: column;
        height: auto;
    }
    
    .msg-sidebar {
        width: 100%;
        max-height: 200px;
    }
    
    .msg-chat {
        height: 500px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const msgMessages = document.getElementById('msgMessages');
    if (msgMessages) {
        msgMessages.scrollTop = msgMessages.scrollHeight;
    }
    
    const msgTextarea = document.getElementById('msgTextarea');
    if (msgTextarea) {
        msgTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            if (this.value === '') {
                this.style.height = '38px';
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>