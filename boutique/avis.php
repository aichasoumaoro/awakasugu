<?php
// ============================================
// AVIS CLIENTS - Awa Ka Sugu
// ============================================

// ============================================
// SESSION PUBLIQUE SÉPARÉE
// ============================================
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// VÉRIFICATION MAINTENANCE
// ============================================
require_once '../includes/maintenance_check.php';

// ============================================
// CONNEXION BDD + RÉCUPÉRATION PRODUIT
// (déplacé avant header.php pour pouvoir rediriger
// sans déclencher "headers already sent")
// ============================================
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

// Récupérer l'ID du produit
$produit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($produit_id <= 0) {
    header('Location: catalogue.php');
    exit;
}

// Récupérer le produit
$stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND est_visible = 1");
$stmt->execute([$produit_id]);
$produit = $stmt->fetch();

if (!$produit) {
    header('Location: catalogue.php');
    exit;
}

$titre_page = 'Avis - IBA Design';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$client_id = $_SESSION['client_id'] ?? null;
$error = '';
$success = '';

// Ajouter un avis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $client_id) {
    $note = (int)$_POST['note'];
    $commentaire = trim($_POST['commentaire'] ?? '');
    $nom_client = trim($_POST['nom_client'] ?? $_SESSION['client_nom'] ?? 'Client');
    
    if ($note < 1 || $note > 5) {
        $error = 'La note doit être comprise entre 1 et 5.';
    } elseif (empty($commentaire)) {
        $error = 'Veuillez écrire un commentaire.';
    } else {
        // Vérifier si le client a déjà acheté ce produit
        $stmt = $pdo->prepare("
            SELECT * FROM details_commande dc
            JOIN commandes c ON c.id = dc.commande_id
            WHERE dc.produit_id = ? AND c.client_id = ? AND c.statut = 'livree'
            LIMIT 1
        ");
        $stmt->execute([$produit_id, $client_id]);
        $aAchete = $stmt->fetch();
        
        if (!$aAchete) {
            $error = 'Vous devez avoir acheté ce produit pour laisser un avis.';
        } else {
            // Vérifier si un avis existe déjà
            $stmt = $pdo->prepare("SELECT * FROM avis_clients WHERE client_id = ? AND produit_id = ?");
            $stmt->execute([$client_id, $produit_id]);
            if ($stmt->fetch()) {
                $error = 'Vous avez déjà laissé un avis sur ce produit.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO avis_clients (client_id, produit_id, nom_client, note, commentaire, est_valide, created_at)
                    VALUES (?, ?, ?, ?, ?, 0, NOW())
                ");
                $stmt->execute([$client_id, $produit_id, $nom_client, $note, $commentaire]);
                $success = 'Merci pour votre avis ! Il sera visible après validation.';
            }
        }
    }
}

// Récupérer les avis validés
$stmt = $pdo->prepare("
    SELECT * FROM avis_clients 
    WHERE produit_id = ? AND est_valide = 1 
    ORDER BY created_at DESC
");
$stmt->execute([$produit_id]);
$avis_liste = $stmt->fetchAll();

// Calculer la note moyenne
$note_moyenne = 0;
if (!empty($avis_liste)) {
    $total_notes = 0;
    foreach ($avis_liste as $a) {
        $total_notes += $a['note'];
    }
    $note_moyenne = $total_notes / count($avis_liste);
}
?>

<style>
/* ========== PAGE AVIS ========== */
.avis-header {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
    padding: 50px 0 40px;
    text-align: center;
    margin-bottom: 40px;
}
.avis-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    color: #C8922A;
    margin-bottom: 10px;
}
.avis-header p {
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
}
.container-custom {
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 20px 60px;
}
.btn-retour {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #6C757D;
    color: white;
    padding: 10px 22px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    margin-bottom: 25px;
    font-size: 0.85rem;
}
.btn-retour:hover {
    background: #5A6268;
    color: white;
}
.avis-card {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.05);
    border: 1px solid rgba(200,146,42,0.08);
    margin-bottom: 25px;
}
.note-moyenne {
    font-family: 'Playfair Display', serif;
    font-size: 2.5rem;
    font-weight: 700;
    color: #C8922A;
    line-height: 1;
}
.etoiles {
    color: #FFD700;
    font-size: 1.2rem;
}
.etoiles-vide {
    color: #ddd;
}
.rating-input {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
}
.rating-star {
    font-size: 2.2rem;
    cursor: pointer;
    color: #ddd;
    transition: all 0.2s;
}
.rating-star:hover,
.rating-star.selected {
    color: #FFD700;
    transform: scale(1.1);
}
.btn-envoyer {
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    border: none;
    padding: 12px 35px;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-envoyer:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
    color: white;
}
.btn-connexion {
    background: #C8922A;
    color: white;
    padding: 12px 35px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    display: inline-block;
    transition: all 0.3s;
}
.btn-connexion:hover {
    background: #9A6E1A;
    color: white;
}
.avis-item {
    border-bottom: 1px solid #F0F2F5;
    padding-bottom: 18px;
    margin-bottom: 18px;
}
.avis-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}
.avis-item .avis-header-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.avis-item .avis-nom {
    font-weight: 600;
    color: #0D0D0D;
    font-size: 0.95rem;
}
.avis-item .avis-date {
    font-size: 0.75rem;
    color: #8A99AA;
}
.avis-item .avis-commentaire {
    color: #4A5568;
    font-size: 0.9rem;
    margin-top: 8px;
    line-height: 1.6;
}
.empty-avis {
    text-align: center;
    padding: 30px;
    color: #8A99AA;
}
.empty-avis i {
    font-size: 2.5rem;
    display: block;
    margin-bottom: 10px;
    color: #E0E6ED;
}
.form-card .form-label {
    font-weight: 600;
    font-size: 0.8rem;
    color: #0D0D0D;
}
.form-card .form-control {
    border: 1.5px solid #E0E6ED;
    border-radius: 10px;
    padding: 12px 16px;
    font-family: 'Jost', sans-serif;
    transition: all 0.3s;
}
.form-card .form-control:focus {
    border-color: #C8922A;
    box-shadow: 0 0 0 3px rgba(200,146,42,0.1);
}
.alert {
    border-radius: 10px;
    border: none;
    border-left: 4px solid;
}
.alert-danger { border-left-color: #E74C3C; background: #FEF3F2; color: #721C24; }
.alert-success { border-left-color: #27AE60; background: #D4EDDA; color: #0A3622; }
@media (max-width: 600px) {
    .avis-header h1 { font-size: 1.5rem; }
    .avis-card { padding: 20px; }
    .rating-star { font-size: 1.8rem; }
}
</style>

<!-- Header -->
<div class="avis-header">
    <div class="container-custom" style="padding-bottom:0;">
        <h1>⭐ Avis sur "<?= htmlspecialchars($produit['nom']) ?>"</h1>
        <p>Ce que nos clientes pensent de ce produit</p>
    </div>
</div>

<div class="container-custom">
    <a href="produit.php?id=<?= $produit_id ?>" class="btn-retour">
        <i class="bi bi-arrow-left"></i> Retour au produit
    </a>

    <!-- Résumé des notes -->
    <div class="avis-card">
        <div class="row align-items-center">
            <div class="col-md-4 text-center">
                <div class="note-moyenne"><?= number_format($note_moyenne, 1) ?> / 5</div>
                <div class="etoiles mt-2">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <?php if($i <= round($note_moyenne)): ?>
                            <i class="bi bi-star-fill"></i>
                        <?php else: ?>
                            <i class="bi bi-star"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <p class="text-muted mt-2" style="font-size:0.85rem;"><?= count($avis_liste) ?> avis</p>
            </div>
            <div class="col-md-8">
                <p style="color:#4A5568; margin-bottom:0;">
                    <i class="bi bi-chat-quote" style="color:#C8922A;"></i>
                    Partagez votre expérience avec ce produit. Votre avis nous aide à nous améliorer et aide les autres clientes à faire leur choix.
                </p>
            </div>
        </div>
    </div>

    <!-- Formulaire d'avis -->
    <?php if($client_id): ?>
    <div class="avis-card form-card">
        <h4 style="font-family:'Playfair Display',serif; color:#0D0D0D; margin-bottom:20px;">
            ✍️ Donnez votre avis
        </h4>
        
        <?php if($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Votre note *</label>
                <div class="rating-input" id="ratingStars">
                    <i class="bi bi-star rating-star" data-note="1"></i>
                    <i class="bi bi-star rating-star" data-note="2"></i>
                    <i class="bi bi-star rating-star" data-note="3"></i>
                    <i class="bi bi-star rating-star" data-note="4"></i>
                    <i class="bi bi-star rating-star" data-note="5"></i>
                </div>
                <input type="hidden" name="note" id="note" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Votre commentaire *</label>
                <textarea name="commentaire" class="form-control" rows="4" placeholder="Partagez votre expérience avec ce produit..." required></textarea>
            </div>
            <button type="submit" class="btn-envoyer">
                <i class="bi bi-send"></i> Envoyer mon avis
            </button>
        </form>
    </div>
    <?php else: ?>
    <div class="avis-card text-center" style="padding:40px;">
        <i class="bi bi-person-circle" style="font-size:3.5rem; color:#E0E6ED;"></i>
        <h4 style="margin-top:15px; color:#0D0D0D;">Connectez-vous pour laisser un avis</h4>
        <p style="color:#8A99AA; margin-bottom:20px;">
            Seuls les clients ayant acheté ce produit peuvent laisser un avis.
        </p>
        <a href="../client/connexion.php?redirect=avis.php?id=<?= $produit_id ?>" class="btn-connexion">
            <i class="bi bi-box-arrow-in-right"></i> Se connecter
        </a>
    </div>
    <?php endif; ?>

    <!-- Liste des avis -->
    <?php if(!empty($avis_liste)): ?>
    <div class="avis-card">
        <h4 style="font-family:'Playfair Display',serif; color:#0D0D0D; margin-bottom:20px;">
            📝 Avis des clientes
        </h4>
        <?php foreach($avis_liste as $a): ?>
        <div class="avis-item">
            <div class="avis-header-info">
                <div>
                    <span class="avis-nom"><?= htmlspecialchars($a['nom_client']) ?></span>
                    <div class="etoiles" style="font-size:0.9rem;">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <?php if($i <= $a['note']): ?>
                                <i class="bi bi-star-fill text-warning"></i>
                            <?php else: ?>
                                <i class="bi bi-star text-muted"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                <span class="avis-date"><i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($a['created_at'])) ?></span>
            </div>
            <div class="avis-commentaire"><?= nl2br(htmlspecialchars($a['commentaire'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Gestion des étoiles pour la note
const stars = document.querySelectorAll('.rating-star');
const noteInput = document.getElementById('note');

stars.forEach(star => {
    star.addEventListener('click', function() {
        const note = parseInt(this.dataset.note);
        noteInput.value = note;
        
        stars.forEach(s => {
            const sNote = parseInt(s.dataset.note);
            if(sNote <= note) {
                s.classList.remove('bi-star');
                s.classList.add('bi-star-fill');
                s.classList.add('selected');
            } else {
                s.classList.remove('bi-star-fill');
                s.classList.add('bi-star');
                s.classList.remove('selected');
            }
        });
    });
    
    // Effet hover
    star.addEventListener('mouseenter', function() {
        const note = parseInt(this.dataset.note);
        stars.forEach(s => {
            const sNote = parseInt(s.dataset.note);
            if(sNote <= note) {
                s.classList.add('bi-star-fill');
                s.classList.remove('bi-star');
            } else {
                s.classList.remove('bi-star-fill');
                s.classList.add('bi-star');
            }
        });
    });
    
    star.addEventListener('mouseleave', function() {
        const selected = parseInt(noteInput.value) || 0;
        stars.forEach(s => {
            const sNote = parseInt(s.dataset.note);
            if(sNote <= selected) {
                s.classList.add('bi-star-fill');
                s.classList.remove('bi-star');
            } else {
                s.classList.remove('bi-star-fill');
                s.classList.add('bi-star');
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>