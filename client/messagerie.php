<?php
// ============================================
// MESSAGERIE - Client (discussion avec admin)
// ============================================

session_name('PUBLIC_SESSION');
session_start();

// Vérification maintenance
require_once '../includes/maintenance_check.php';

// Vérifier si le client est connecté
if (!isset($_SESSION['client_id'])) {
    header('Location: connexion.php?redirect=messagerie');
    exit;
}

$client_id = $_SESSION['client_id'];

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
// CRÉATION D'UNE NOUVELLE CONVERSATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_nouvelle_conversation'])) {
    $sujet = trim($_POST['sujet'] ?? '');
    $message_initial = trim($_POST['message_initial'] ?? '');
    
    if (!empty($sujet) && !empty($message_initial)) {
        $stmt = $pdo->prepare("
            INSERT INTO conversations (client_id, sujet, cree_le, mis_a_jour)
            VALUES (?, ?, NOW(), NOW())
        ");
        $stmt->execute([$client_id, $sujet]);
        $conversation_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, expediteur_type, expediteur_id, contenu, envoye_le)
            VALUES (?, 'client', ?, ?, NOW())
        ");
        $stmt->execute([$conversation_id, $client_id, $message_initial]);
        
        header('Location: messagerie.php?conv=' . $conversation_id);
        exit;
    }
}

// ============================================
// ENVOI D'UN MESSAGE DANS UNE CONVERSATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_envoyer_message'])) {
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND client_id = ?");
    $stmt->execute([$conversation_id, $client_id]);
    $conv = $stmt->fetch();
    
    if ($conv && !empty($message)) {
        $stmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, expediteur_type, expediteur_id, contenu, envoye_le)
            VALUES (?, 'client', ?, ?, NOW())
        ");
        $stmt->execute([$conversation_id, $client_id, $message]);
        
        $stmt = $pdo->prepare("UPDATE conversations SET mis_a_jour = NOW() WHERE id = ?");
        $stmt->execute([$conversation_id]);
        
        header('Location: messagerie.php?conv=' . $conversation_id);
        exit;
    }
}

// ============================================
// RÉCUPÉRATION DES CONVERSATIONS DU CLIENT
// ============================================
$conversations = [];
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.lu = 0 AND m.expediteur_type = 'admin') as non_lus
    FROM conversations c
    WHERE c.client_id = ?
    ORDER BY c.mis_a_jour DESC
");
$stmt->execute([$client_id]);
$conversations = $stmt->fetchAll();

// ============================================
// RÉCUPÉRATION DE LA CONVERSATION ACTIVE
// ============================================
$conversation_active = null;
$messages = [];

if (isset($_GET['conv'])) {
    $conv_id = (int)$_GET['conv'];
    
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND client_id = ?");
    $stmt->execute([$conv_id, $client_id]);
    $conversation_active = $stmt->fetch();
    
    if ($conversation_active) {
        $stmt = $pdo->prepare("
            UPDATE messages SET lu = 1 
            WHERE conversation_id = ? AND expediteur_type = 'admin' AND lu = 0
        ");
        $stmt->execute([$conv_id]);
        
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   CASE 
                       WHEN m.expediteur_type = 'client' THEN cl.nom
                       ELSE 'Administration IBA'
                   END as expediteur_nom
            FROM messages m
            LEFT JOIN clients cl ON m.expediteur_id = cl.id
            WHERE m.conversation_id = ?
            ORDER BY m.envoye_le ASC
        ");
        $stmt->execute([$conv_id]);
        $messages = $stmt->fetchAll();
    }
}

// Titre de la page
$titre_page = 'Messagerie';

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
/* ===== MESSAGERIE CLIENT - RESPONSIVE ===== */
.messagerie-page {
    padding: 20px 0;
    background: #F8F9FA;
    min-height: 80vh;
}

.messagerie-container {
    max-width: 100%;
    margin: 0 auto;
    padding: 0 10px;
    display: flex;
    gap: 10px;
    height: calc(100vh - 160px);
    min-height: 500px;
}

/* ===== SIDEBAR CONVERSATIONS ===== */
.conversations-sidebar {
    width: 300px;
    flex-shrink: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #E8ECF0;
}

.conversations-header {
    padding: 15px;
    border-bottom: 1px solid #F0F2F5;
    background: linear-gradient(135deg, #1A1A1A, #2D2D2D);
    color: white;
}

.conversations-header h2 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 3px;
}

.conversations-header p {
    font-size: 0.7rem;
    opacity: 0.7;
}

.conversations-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.conversation-item {
    display: block;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 4px;
    text-decoration: none;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.conversation-item:hover {
    background: #F8F9FA;
}

.conversation-item.active {
    background: rgba(200,146,42,0.08);
    border-color: rgba(200,146,42,0.2);
}

.conv-titre {
    font-weight: 600;
    color: #1A1A1A;
    font-size: 0.85rem;
    margin-bottom: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.conv-dernier {
    font-size: 0.7rem;
    color: #8A99AA;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.conv-badge {
    background: #C8922A;
    color: white;
    font-size: 0.6rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
}

/* ===== ZONE CHAT ===== */
.chat-area {
    flex: 1;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #E8ECF0;
}

.chat-header {
    padding: 12px 15px;
    border-bottom: 1px solid #F0F2F5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
}

.chat-titre {
    font-size: 1rem;
    font-weight: 700;
    color: #1A1A1A;
}

.chat-sous-titre {
    font-size: 0.7rem;
    color: #8A99AA;
    margin-top: 2px;
}

.chat-actions {
    display: flex;
    gap: 10px;
}

.btn-fermer-conv {
    background: transparent;
    border: 1px solid #E0E0E0;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.75rem;
    color: #666;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-fermer-conv:hover {
    border-color: #E74C3C;
    color: #E74C3C;
}

.chat-messages {
    flex: 1;
    padding: 15px;
    overflow-y: auto;
    background: #F8F9FA;
}

.message {
    display: flex;
    margin-bottom: 12px;
    flex-direction: column;
}

.message.client {
    align-items: flex-end;
}

.message.admin {
    align-items: flex-start;
}

.message-bubble {
    max-width: 75%;
    padding: 10px 14px;
    border-radius: 14px;
    font-size: 0.85rem;
    line-height: 1.5;
    word-wrap: break-word;
}

.message.client .message-bubble {
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    border-bottom-right-radius: 4px;
}

.message.admin .message-bubble {
    background: white;
    color: #1A1A1A;
    border: 1px solid #E8ECF0;
    border-bottom-left-radius: 4px;
}

.message-meta {
    font-size: 0.6rem;
    color: #8A99AA;
    margin-top: 3px;
    padding: 0 4px;
}

.message.client .message-meta {
    text-align: right;
}

/* ===== FORMULAIRE ===== */
.chat-input-area {
    padding: 10px 15px;
    border-top: 1px solid #F0F2F5;
    background: white;
}

.chat-input-form {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.chat-input-form textarea {
    flex: 1;
    border: 1.5px solid #E0E0E0;
    border-radius: 20px;
    padding: 10px 15px;
    font-size: 0.85rem;
    font-family: inherit;
    resize: none;
    min-height: 40px;
    max-height: 80px;
    outline: none;
}

.chat-input-form textarea:focus {
    border-color: #C8922A;
}

.btn-envoyer {
    background: #C8922A;
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
}

.btn-envoyer:hover {
    background: #9A6E1A;
}

/* ===== MODALE ===== */
.modale-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}

.modale-overlay.active {
    display: flex;
}

.modale-contenu {
    background: white;
    border-radius: 20px;
    padding: 25px;
    width: 90%;
    max-width: 450px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}

.modale-contenu h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1A1A1A;
    margin-bottom: 20px;
}

.modale-contenu .form-group {
    margin-bottom: 15px;
}

.modale-contenu label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
}

.modale-contenu input,
.modale-contenu textarea {
    width: 100%;
    padding: 10px;
    border: 1.5px solid #E0E0E0;
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: inherit;
    outline: none;
}

.modale-contenu input:focus,
.modale-contenu textarea:focus {
    border-color: #C8922A;
}

.modale-contenu .modal-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.modale-contenu .btn-annuler {
    background: #F0F2F5;
    border: none;
    padding: 10px 20px;
    border-radius: 20px;
    font-size: 0.85rem;
    cursor: pointer;
}

.modale-contenu .btn-creer {
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    border: none;
    padding: 10px 24px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
    cursor: pointer;
}

/* ===== BOUTON NOUVELLE CONVERSATION ===== */
.btn-nouvelle-conv {
    width: 100%;
    padding: 10px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
    margin-top: 10px;
}

.btn-nouvelle-conv:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(200,146,42,0.3);
}

/* ===== ÉTAT VIDE ===== */
.chat-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px;
    text-align: center;
}

.chat-empty i {
    font-size: 3rem;
    color: #D5D5D5;
    margin-bottom: 10px;
}

.chat-empty h3 {
    color: #8A99AA;
    font-size: 1rem;
    margin-bottom: 5px;
}

.chat-empty p {
    color: #B0B0B0;
    font-size: 0.8rem;
    max-width: 250px;
}

/* ===== RESPONSIVE MOBILE : CÔTE À CÔTE ===== */
@media (max-width: 768px) {
    .messagerie-container {
        flex-direction: row; /* FORCE CÔTE À CÔTE */
        gap: 8px;
        height: calc(100vh - 140px);
    }
    
    .conversations-sidebar {
        width: 40%; /* 40% de l'écran pour la liste */
        min-width: 140px;
        max-width: 200px;
        flex-shrink: 0;
    }
    
    .conversations-header {
        padding: 10px;
    }
    
    .conversations-header h2 {
        font-size: 0.9rem;
    }
    
    .conversations-header p {
        font-size: 0.65rem;
    }
    
    .conversation-item {
        padding: 8px;
    }
    
    .conv-titre {
        font-size: 0.75rem;
    }
    
    .conv-dernier {
        font-size: 0.6rem;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .chat-area {
        flex: 1; /* Le chat prend le reste */
        min-width: 0;
    }
    
    .chat-header {
        padding: 10px;
    }
    
    .chat-titre {
        font-size: 0.85rem;
    }
    
    .chat-messages {
        padding: 10px;
    }
    
    .message-bubble {
        max-width: 85%;
        padding: 8px 10px;
        font-size: 0.8rem;
    }
    
    .chat-input-area {
        padding: 8px 10px;
    }
    
    .chat-input-form textarea {
        padding: 8px 12px;
        font-size: 0.8rem;
        min-height: 35px;
    }
    
    .btn-envoyer {
        width: 35px;
        height: 35px;
        font-size: 1rem;
    }
}
</style>

<div class="messagerie-page">
    <div class="messagerie-container">
        
        <!-- ===== SIDEBAR CONVERSATIONS (GAUCHE) ===== -->
        <div class="conversations-sidebar">
            <div class="conversations-header">
                <h2>💬 Messagerie</h2>
                <p>Conversations</p>
            </div>
            
            <div style="padding: 8px;">
                <button class="btn-nouvelle-conv" onclick="ouvrirModale()">
                    <i class="bi bi-plus-circle"></i> Nouvelle
                </button>
            </div>
            
            <div class="conversations-list">
                <?php if (!empty($conversations)): ?>
                    <?php foreach ($conversations as $conv): ?>
                        <?php
                        $stmt_dernier = $pdo->prepare("SELECT contenu, envoye_le FROM messages WHERE conversation_id = ? ORDER BY envoye_le DESC LIMIT 1");
                        $stmt_dernier->execute([$conv['id']]);
                        $dernier = $stmt_dernier->fetch();
                        
                        $dernier_msg = $dernier['contenu'] ?? 'Aucun message';
                        $dernier_date = date('d/m/Y H:i', strtotime($dernier['envoye_le'] ?? $conv['cree_le']));
                        ?>
                        <a href="messagerie.php?conv=<?= $conv['id'] ?>" class="conversation-item <?= isset($conversation_active) && $conversation_active['id'] == $conv['id'] ? 'active' : '' ?>">
                            <div class="conv-titre">
                                <span><?= htmlspecialchars(mb_substr($conv['sujet'] ?? 'Sans sujet', 0, 20)) ?></span>
                                <?php if ($conv['non_lus'] > 0): ?>
                                    <span class="conv-badge"><?= $conv['non_lus'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="conv-dernier">
                                <span><?= htmlspecialchars(mb_substr($dernier_msg, 0, 20)) ?>...</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:20px;color:#B0B0B0;">
                        <i class="bi bi-chat-square-text" style="font-size:1.5rem;"></i>
                        <p style="margin-top:8px;font-size:0.75rem;">Aucune conversation</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ===== ZONE DE CHAT (DROITE) ===== -->
        <div class="chat-area">
            <?php if ($conversation_active): ?>
                <div class="chat-header">
                    <div>
                        <div class="chat-titre"><?= htmlspecialchars($conversation_active['sujet']) ?></div>
                        <div class="chat-sous-titre"><?= date('d/m/Y H:i', strtotime($conversation_active['cree_le'])) ?></div>
                    </div>
                    <div class="chat-actions">
                        <button class="btn-fermer-conv" onclick="return confirm('Fermer cette conversation ?') ? window.location.href='messagerie.php?fermer=<?= $conversation_active['id'] ?>' : false">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message <?= $msg['expediteur_type'] == 'client' ? 'client' : 'admin' ?>">
                            <div class="message-bubble">
                                <?= nl2br(htmlspecialchars($msg['contenu'])) ?>
                            </div>
                            <div class="message-meta">
                                <?= htmlspecialchars($msg['expediteur_nom']) ?> • <?= date('d/m/Y H:i', strtotime($msg['envoye_le'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="chat-input-area">
                    <form class="chat-input-form" method="POST">
                        <input type="hidden" name="action_envoyer_message" value="1">
                        <input type="hidden" name="conversation_id" value="<?= $conversation_active['id'] ?>">
                        <textarea name="message" id="messageInput" placeholder="Écrivez..." required></textarea>
                        <button type="submit" class="btn-envoyer" title="Envoyer">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="chat-empty">
                    <i class="bi bi-chat-square-dots"></i>
                    <h3>Sélectionnez une conversation</h3>
                    <p>Choisissez une conversation à gauche.</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- ===== MODALE ===== -->
<div class="modale-overlay" id="modaleNouvelle">
    <div class="modale-contenu">
        <h3>Nouvelle conversation</h3>
        <form method="POST">
            <input type="hidden" name="action_nouvelle_conversation" value="1">
            <div class="form-group">
                <label for="sujet">Sujet</label>
                <input type="text" id="sujet" name="sujet" placeholder="Ex: Question sur ma commande" required>
            </div>
            <div class="form-group">
                <label for="message_initial">Premier message</label>
                <textarea id="message_initial" name="message_initial" rows="4" placeholder="Décrivez votre demande..." required></textarea>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn-annuler" onclick="fermerModale()">Annuler</button>
                <button type="submit" class="btn-creer"><i class="bi bi-send"></i> Envoyer</button>
            </div>
        </form>
    </div>
</div>

<script>
function ouvrirModale() {
    document.getElementById('modaleNouvelle').classList.add('active');
}

function fermerModale() {
    document.getElementById('modaleNouvelle').classList.remove('active');
}

document.getElementById('modaleNouvelle').addEventListener('click', function(e) {
    if (e.target === this) {
        fermerModale();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            if (this.value === '') {
                this.style.height = '40px';
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>