<?php
// ============================================
// AVIS CLIENTS - Tous les avis (avec réponses + likes)
// ============================================

session_name('PUBLIC_SESSION');
session_start();

// Vérification maintenance
require_once '../includes/maintenance_check.php';

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
// TRAITEMENT AJOUT D'UNE RÉPONSE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_repondre'])) {
    $avis_parent_id = (int)($_POST['parent_id'] ?? 0);
    $commentaire = trim($_POST['commentaire'] ?? '');
    
    if ($avis_parent_id > 0 && !empty($commentaire)) {
        $client_id = $_SESSION['client_id'] ?? null;
        $admin_id = $_SESSION['admin_id'] ?? null;
        
        if ($admin_id) {
            $auteur_type = 'admin';
            $nom_auteur = 'Administration IBA';
            $admin_id_val = $admin_id;
            $client_id_val = null;
        } elseif ($client_id) {
            $auteur_type = 'client';
            $stmt = $pdo->prepare("SELECT nom FROM clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch();
            $nom_auteur = $client['nom'] ?? 'Client';
            $admin_id_val = null;
            $client_id_val = $client_id;
        } else {
            $auteur_type = 'client';
            $nom_auteur = 'Anonyme';
            $client_id_val = null;
            $admin_id_val = null;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO avis_clients (client_id, produit_id, parent_id, nom_client, note, commentaire, est_valide, est_visible, recommandation, auteur_type, admin_id, created_at)
            VALUES (?, ?, ?, ?, 5, ?, 1, 1, 0, ?, ?, NOW())
        ");
        $stmt_parent = $pdo->prepare("SELECT produit_id FROM avis_clients WHERE id = ?");
        $stmt_parent->execute([$avis_parent_id]);
        $produit_id_parent = $stmt_parent->fetchColumn();
        
        $stmt->execute([
            $client_id_val,
            $produit_id_parent,
            $avis_parent_id,
            $nom_auteur,
            $commentaire,
            $auteur_type,
            $admin_id_val
        ]);
        
        header('Location: avis.php?success=1#avis-' . $avis_parent_id);
        exit;
    }
}

// ============================================
// TRAITEMENT AJOUT / RETRAIT D'UN LIKE (AJAX)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_like'])) {
    header('Content-Type: application/json');
    
    $avis_id = (int)($_POST['avis_id'] ?? 0);
    $client_id = $_SESSION['client_id'] ?? null;
    $admin_id = $_SESSION['admin_id'] ?? null;
    
    if ($avis_id <= 0 || (!$client_id && !$admin_id)) {
        echo json_encode(['success' => false, 'message' => 'Action impossible']);
        exit;
    }
    
    $user_id = $client_id ?: $admin_id;
    
    $stmt = $pdo->prepare("SELECT id FROM avis_clients WHERE id = ?");
    $stmt->execute([$avis_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Avis introuvable']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM avis_clients_likes WHERE avis_id = ? AND client_id = ?");
    $stmt->execute([$avis_id, $user_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $pdo->prepare("DELETE FROM avis_clients_likes WHERE id = ?");
        $stmt->execute([$existing['id']]);
        $liked = false;
    } else {
        $stmt = $pdo->prepare("INSERT INTO avis_clients_likes (avis_id, client_id) VALUES (?, ?)");
        $stmt->execute([$avis_id, $user_id]);
        $liked = true;
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM avis_clients_likes WHERE avis_id = ?");
    $stmt->execute([$avis_id]);
    $count = (int)$stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'count' => $count
    ]);
    exit;
}

// ============================================
// RÉCUPÉRATION DES AVIS PRINCIPAUX ET RÉPONSES
// ============================================
$sql = "
    SELECT a.*, p.nom as produit_nom, p.image_principale as produit_image
    FROM avis_clients a
    LEFT JOIN produits p ON a.produit_id = p.id
    WHERE a.est_valide = 1 AND a.est_visible = 1 AND a.parent_id IS NULL
    ORDER BY a.created_at DESC
";
$stmt = $pdo->query($sql);
$avis_principaux = $stmt->fetchAll();

// CORRECTION ICI : jointure correcte pour les réponses
$sql_reponses = "
    SELECT a.*, p.nom as produit_nom
    FROM avis_clients a
    LEFT JOIN produits p ON a.produit_id = p.id
    WHERE a.parent_id IS NOT NULL
    ORDER BY a.created_at ASC
";
$stmt_reponses = $pdo->query($sql_reponses);
$reponses = $stmt_reponses->fetchAll();

$reponses_par_parent = [];
foreach ($reponses as $r) {
    $parent = $r['parent_id'];
    if (!isset($reponses_par_parent[$parent])) {
        $reponses_par_parent[$parent] = [];
    }
    $reponses_par_parent[$parent][] = $r;
}

$all_avis_ids = array_merge(
    array_column($avis_principaux, 'id'),
    array_column($reponses, 'id')
);
$likes_map = [];
$user_likes = [];

if (!empty($all_avis_ids)) {
    $in = implode(',', array_fill(0, count($all_avis_ids), '?'));
    $stmt = $pdo->prepare("SELECT avis_id, COUNT(*) as nb FROM avis_clients_likes WHERE avis_id IN ($in) GROUP BY avis_id");
    $stmt->execute($all_avis_ids);
    $likes_data = $stmt->fetchAll();
    foreach ($likes_data as $row) {
        $likes_map[$row['avis_id']] = (int)$row['nb'];
    }
    
    $client_id = $_SESSION['client_id'] ?? null;
    $admin_id = $_SESSION['admin_id'] ?? null;
    $user_id = $client_id ?: $admin_id;
    
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT avis_id FROM avis_clients_likes WHERE client_id = ? AND avis_id IN ($in)");
        $stmt->execute(array_merge([$user_id], $all_avis_ids));
        $user_likes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $user_likes = array_flip($user_likes);
    }
}

$total_avis = count($avis_principaux);
$note_moyenne_globale = 0;
if ($total_avis > 0) {
    $somme = 0;
    foreach ($avis_principaux as $a) {
        $somme += $a['note'];
    }
    $note_moyenne_globale = $somme / $total_avis;
}

$titre_page = 'Tous les avis clients';

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
.avis-page { padding: 40px 0 60px; background: #F8F9FA; }
.container-custom { max-width: 1000px; margin: 0 auto; padding: 0 20px; }

.avis-page-header { text-align: center; margin-bottom: 40px; background: white; padding: 30px 20px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid rgba(200,146,42,0.08); }
.avis-page-header h1 { font-family: 'Playfair Display', serif; font-size: 2.2rem; color: #0D0D0D; margin-bottom: 8px; }
.avis-page-header p { color: #8A99AA; font-size: 0.95rem; }
.avis-stats { display: flex; align-items: center; justify-content: center; gap: 25px; margin-top: 15px; flex-wrap: wrap; }
.avis-stats .note { font-family: 'Playfair Display', serif; font-size: 2.5rem; font-weight: 700; color: #C8922A; line-height: 1; }
.avis-stats .etoiles { color: #F1C40F; font-size: 1.3rem; }
.avis-stats .count { color: #8A99AA; font-size: 0.9rem; }

.avis-list { display: flex; flex-direction: column; gap: 20px; }

.avis-item { background: white; border-radius: 16px; padding: 24px 28px; border: 1px solid #E8ECF0; transition: all 0.3s ease; }
.avis-item:hover { border-color: rgba(200,146,42,0.3); box-shadow: 0 6px 20px rgba(0,0,0,0.04); }
.avis-item .avis-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 8px; }
.avis-item .avis-nom { font-weight: 600; color: #0D0D0D; font-size: 1rem; }
.avis-item .avis-date { color: #8A99AA; font-size: 0.75rem; }
.avis-item .avis-etoiles { color: #F1C40F; font-size: 0.95rem; margin-bottom: 6px; }
.avis-item .avis-commentaire { color: #4A5568; font-size: 0.95rem; line-height: 1.6; margin-top: 4px; }
.avis-item .avis-produit { display: flex; align-items: center; gap: 12px; margin-top: 14px; padding-top: 14px; border-top: 1px solid #F0F2F5; }
.avis-item .avis-produit img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #F0F2F5; }
.avis-item .avis-produit span { font-size: 0.8rem; color: #8A99AA; }
.avis-item .avis-produit strong { color: #1A1A1A; font-weight: 600; }
.avis-item .avis-produit a { color: #C8922A; text-decoration: none; font-weight: 500; transition: color 0.2s; }
.avis-item .avis-produit a:hover { color: #9A6E1A; text-decoration: underline; }

/* Styles pour les réponses */
.avis-reponses { margin-top: 15px; padding-left: 20px; border-left: 3px solid #C8922A; }
.avis-reponse { background: #F8F9FA; border-radius: 12px; padding: 12px 16px; margin-bottom: 10px; }
.avis-reponse .reponse-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; flex-wrap: wrap; }
.avis-reponse .reponse-auteur { font-weight: 700; color: #C8922A; font-size: 0.85rem; }
.avis-reponse .reponse-auteur.admin { background: rgba(200,146,42,0.1); padding: 2px 8px; border-radius: 12px; }
.avis-reponse .reponse-date { font-size: 0.7rem; color: #8A99AA; }
.avis-reponse .reponse-texte { font-size: 0.85rem; color: #333; line-height: 1.5; }

/* Formulaire de réponse */
.form-reponse { margin-top: 12px; padding: 10px; background: #F8F9FA; border-radius: 12px; display: none; }
.form-reponse textarea { width: 100%; border: 1px solid #E8ECF0; border-radius: 8px; padding: 8px; font-family: inherit; font-size: 0.85rem; resize: vertical; min-height: 60px; }
.form-reponse button { margin-top: 8px; background: #C8922A; color: white; border: none; padding: 8px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-repondre { display: inline-block; margin-top: 10px; background: transparent; border: 1px solid #C8922A; color: #C8922A; padding: 5px 14px; border-radius: 20px; font-size: 0.75rem; cursor: pointer; transition: all 0.2s; }
.btn-repondre:hover { background: #C8922A; color: white; }

/* Styles pour le bouton like */
.avis-actions { display: flex; align-items: center; gap: 12px; margin-top: 10px; }
.btn-like { display: inline-flex; align-items: center; gap: 6px; background: transparent; border: 1px solid #E0E0E0; border-radius: 20px; padding: 5px 12px; font-size: 0.8rem; color: #666; cursor: pointer; transition: all 0.2s; }
.btn-like:hover { border-color: #C8922A; color: #C8922A; }
.btn-like.liked { background: rgba(231,76,60,0.1); border-color: #E74C3C; color: #E74C3C; }
.btn-like i { font-size: 1rem; }
.btn-like .like-count { font-weight: 600; }

.empty-avis { text-align: center; padding: 50px 0; color: #8A99AA; }
.empty-avis i { font-size: 3.5rem; display: block; margin-bottom: 15px; color: #D5D5D5; }
.empty-avis p { font-size: 1.1rem; }
.btn-retour { display: inline-flex; align-items: center; gap: 8px; background: #F0F2F5; color: #5A6B7A; padding: 10px 22px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s; margin-top: 30px; }
.btn-retour:hover { background: #E0E6ED; color: #333; }

@media (max-width: 700px) {
    .avis-page-header h1 { font-size: 1.6rem; }
    .avis-stats { flex-direction: column; gap: 12px; }
    .avis-item { padding: 18px 16px; }
}
</style>

<div class="avis-page">
    <div class="container-custom">

        <!-- En-tête -->
        <div class="avis-page-header">
            <h1>⭐ Tous les avis clients</h1>
            <p>Découvrez ce que nos clientes pensent de leurs achats</p>
            <div class="avis-stats">
                <span class="note"><?= number_format($note_moyenne_globale, 1) ?></span>
                <span class="etoiles">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <i class="bi bi-star<?= $i <= round($note_moyenne_globale) ? '-fill' : '' ?>"></i>
                    <?php endfor; ?>
                </span>
                <span class="count">(<?= $total_avis ?> avis)</span>
            </div>
        </div>

        <!-- Liste des avis -->
        <?php if(!empty($avis_principaux)): ?>
        <div class="avis-list">
            <?php foreach($avis_principaux as $a): 
                $initiale = strtoupper(mb_substr($a['nom_client'] ?? 'C', 0, 1));
                $date_avis = date('d/m/Y', strtotime($a['created_at']));
                $image_produit = '';
                if(!empty($a['produit_image'])) {
                    $image_produit = '../uploads/produits/' . $a['produit_image'];
                    if(!file_exists($image_produit)) {
                        $image_produit = '';
                    }
                }
                if(empty($image_produit)) {
                    $image_produit = 'https://placehold.co/60x60/F5F5F5/C8922A?text=P';
                }
                $reponses_avis = $reponses_par_parent[$a['id']] ?? [];
                $nb_likes = $likes_map[$a['id']] ?? 0;
                $has_liked = isset($user_likes[$a['id']]);
            ?>
            <div class="avis-item" id="avis-<?= $a['id'] ?>">
                <div class="avis-header">
                    <span class="avis-nom"><?= htmlspecialchars($a['nom_client'] ?? 'Anonyme') ?></span>
                    <span class="avis-date"><i class="bi bi-calendar3"></i> <?= $date_avis ?></span>
                </div>
                <div class="avis-etoiles">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <i class="bi bi-star<?= $i <= $a['note'] ? '-fill' : '' ?>"></i>
                    <?php endfor; ?>
                </div>
                <?php if(!empty($a['commentaire'])): ?>
                    <div class="avis-commentaire">"<?= nl2br(htmlspecialchars($a['commentaire'])) ?>"</div>
                <?php endif; ?>
                <?php if(!empty($a['recommandation']) && $a['recommandation'] == 1): ?>
                    <div class="avis-recommandation">👍 Je recommande</div>
                <?php endif; ?>
                <div class="avis-produit">
                    <img src="<?= htmlspecialchars($image_produit) ?>" alt="<?= htmlspecialchars($a['produit_nom'] ?? 'Produit') ?>">
                    <span>
                        Sur <a href="produit.php?id=<?= $a['produit_id'] ?>">
                            <strong><?= htmlspecialchars($a['produit_nom'] ?? 'un produit') ?></strong>
                        </a>
                    </span>
                </div>

                <!-- Actions : Répondre + Like -->
                <div class="avis-actions">
                    <button class="btn-like <?= $has_liked ? 'liked' : '' ?>" 
                            onclick="toggleLike(<?= $a['id'] ?>, this)"
                            data-avis-id="<?= $a['id'] ?>">
                        <i class="bi <?= $has_liked ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                        <span class="like-count"><?= $nb_likes ?></span>
                    </button>

                    <?php if (isset($_SESSION['client_id']) || isset($_SESSION['admin_id'])): ?>
                        <button class="btn-repondre" onclick="toggleFormReponse(<?= $a['id'] ?>)">
                            <i class="bi bi-chat-left-text"></i> Répondre
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Formulaire de réponse -->
                <div class="form-reponse" id="form-reponse-<?= $a['id'] ?>">
                    <form method="POST">
                        <input type="hidden" name="action_repondre" value="1">
                        <input type="hidden" name="parent_id" value="<?= $a['id'] ?>">
                        <textarea name="commentaire" placeholder="Votre réponse..." required></textarea>
                        <button type="submit"><i class="bi bi-send"></i> Envoyer</button>
                    </form>
                </div>

                <!-- Affichage des réponses -->
                <?php if (!empty($reponses_avis)): ?>
                <div class="avis-reponses">
                    <?php foreach ($reponses_avis as $r): 
                        $date_reponse = date('d/m/Y', strtotime($r['created_at']));
                        $auteur = $r['nom_client'] ?? 'Anonyme';
                        $is_admin = ($r['auteur_type'] ?? '') == 'admin';
                        $nb_likes_r = $likes_map[$r['id']] ?? 0;
                        $has_liked_r = isset($user_likes[$r['id']]);
                    ?>
                    <div class="avis-reponse" id="reponse-<?= $r['id'] ?>">
                        <div class="reponse-header">
                            <span class="reponse-auteur <?= $is_admin ? 'admin' : '' ?>">
                                <i class="bi <?= $is_admin ? 'bi-shield-check' : 'bi-person' ?>"></i>
                                <?= htmlspecialchars($auteur) ?>
                                <?php if ($is_admin): ?><span style="font-size:0.6rem;color:#C8922A;"> (Admin)</span><?php endif; ?>
                            </span>
                            <span class="reponse-date"><?= $date_reponse ?></span>
                        </div>
                        <div class="reponse-texte"><?= nl2br(htmlspecialchars($r['commentaire'])) ?></div>
                        
                        <!-- Like sur réponse -->
                        <div class="avis-actions" style="margin-top:6px;">
                            <button class="btn-like <?= $has_liked_r ? 'liked' : '' ?>" 
                                    onclick="toggleLike(<?= $r['id'] ?>, this)"
                                    data-avis-id="<?= $r['id'] ?>">
                                <i class="bi <?= $has_liked_r ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                                <span class="like-count"><?= $nb_likes_r ?></span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-avis">
            <i class="bi bi-chat-dots"></i>
            <p>Aucun avis client pour le moment.</p>
            <p style="font-size:0.85rem;color:#bbb;">Soyez le premier à donner votre avis !</p>
        </div>
        <?php endif; ?>

        <!-- Lien retour -->
        <div style="text-align:center;">
            <a href="../index.php" class="btn-retour">
                <i class="bi bi-house"></i> Retour à l'accueil
            </a>
        </div>

    </div>
</div>

<script>
// Toggle like AJAX
function toggleLike(avisId, button) {
    const btn = button;
    const icon = btn.querySelector('i');
    const countSpan = btn.querySelector('.like-count');
    
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.6';
    
    const formData = new FormData();
    formData.append('action_like', '1');
    formData.append('avis_id', avisId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
        
        if (data.success) {
            countSpan.textContent = data.count;
            if (data.liked) {
                btn.classList.add('liked');
                icon.className = 'bi bi-heart-fill';
            } else {
                btn.classList.remove('liked');
                icon.className = 'bi bi-heart';
            }
        } else {
            alert(data.message || 'Erreur');
        }
    })
    .catch(error => {
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
        console.error('Erreur:', error);
    });
}

// Toggle formulaire réponse
function toggleFormReponse(avisId) {
    const form = document.getElementById('form-reponse-' + avisId);
    if (form) {
        form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
    }
}

// Scroll si réponse envoyée
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        const hash = window.location.hash;
        if (hash) {
            const element = document.querySelector(hash);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth' });
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>