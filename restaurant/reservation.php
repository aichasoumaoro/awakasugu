<?php
// ============================================
// RÉSERVATION DE TABLE - Restaurant Sofia
// ============================================

$titre_page = 'Réservation - Restaurant Sofia';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

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

$error = '';
$success = '';

// Récupérer les horaires disponibles
$horaires = [
    '11:00', '11:30', '12:00', '12:30', '13:00', '13:30',
    '14:00', '14:30', '15:00', '18:00', '18:30', '19:00',
    '19:30', '20:00', '20:30', '21:00'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $date = $_POST['date'] ?? '';
    $heure = $_POST['heure'] ?? '';
    $nb_personnes = (int)($_POST['nb_personnes'] ?? 1);
    $occasion = trim($_POST['occasion'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($nom) || empty($telephone) || empty($date) || empty($heure)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif ($nb_personnes < 1 || $nb_personnes > 50) {
        $error = 'Nombre de personnes invalide (1-50).';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO reservations (nom_client, telephone, email, date_reservation, heure_reservation, nb_personnes, occasion, allergies, notes, statut, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW())
        ");
        $stmt->execute([$nom, $telephone, $email, $date, $heure, $nb_personnes, $occasion, $allergies, $notes]);
        $success = '✅ Votre réservation a été enregistrée avec succès !';
    }
}
?>

<style>
.reservation-hero {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #2A1A0A 100%);
    padding: 50px 0 40px;
    text-align: center;
}
.reservation-hero h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.5rem;
    color: #C8922A;
}
.reservation-hero p {
    color: rgba(255,255,255,0.5);
}
.reservation-container {
    max-width: 700px;
    margin: -20px auto 60px;
    padding: 0 20px;
}
.reservation-card {
    background: white;
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.1);
}
.reservation-card .form-group {
    margin-bottom: 18px;
}
.reservation-card label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: #0D0D0D;
    margin-bottom: 5px;
}
.reservation-card label .required {
    color: #E74C3C;
}
.reservation-card .form-control,
.reservation-card .form-select {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid #E0E6ED;
    border-radius: 10px;
    font-size: 0.9rem;
    transition: all 0.3s;
}
.reservation-card .form-control:focus,
.reservation-card .form-select:focus {
    border-color: #C8922A;
    box-shadow: 0 0 0 3px rgba(200,146,42,0.08);
}
.btn-reserver {
    width: 100%;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    border: none;
    padding: 14px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-reserver:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
}
.alert-error {
    background: #FEF3F2;
    border-left: 4px solid #E74C3C;
    color: #721C24;
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.alert-success {
    background: #D4EDDA;
    border-left: 4px solid #27AE60;
    color: #155724;
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
}
.info-reservation {
    background: #FEFBF5;
    border-radius: 12px;
    padding: 15px 20px;
    margin-top: 20px;
    border: 1px solid rgba(200,146,42,0.1);
    font-size: 0.85rem;
    color: #8A99AA;
}
.info-reservation i {
    color: #C8922A;
    margin-right: 6px;
}
@media (max-width: 600px) {
    .reservation-card { padding: 25px 20px; }
    .reservation-hero h1 { font-size: 1.8rem; }
}
</style>

<div class="reservation-hero">
    <div class="container">
        <h1>📅 Réservation de table</h1>
        <p>Réservez votre table au Restaurant Sofia</p>
    </div>
</div>

<div class="reservation-container">
    <div class="reservation-card">
        <?php if($error): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert-success">
                <i class="bi bi-check-circle-fill"></i> <?= $success ?>
                <br><small>Vous recevrez une confirmation par SMS/email.</small>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nom complet <span class="required">*</span></label>
                        <input type="text" name="nom" class="form-control" placeholder="Votre nom" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Téléphone <span class="required">*</span></label>
                        <input type="tel" name="telephone" class="form-control" placeholder="77 00 00 00" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" placeholder="votre@email.com">
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Date <span class="required">*</span></label>
                        <input type="date" name="date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Heure <span class="required">*</span></label>
                        <select name="heure" class="form-select" required>
                            <option value="">Sélectionner une heure</option>
                            <?php foreach($horaires as $h): ?>
                                <option value="<?= $h ?>"><?= $h ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nombre de personnes <span class="required">*</span></label>
                        <input type="number" name="nb_personnes" class="form-control" value="2" min="1" max="50" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Occasion</label>
                        <select name="occasion" class="form-select">
                            <option value="">Aucune</option>
                            <option value="anniversaire">🎂 Anniversaire</option>
                            <option value="mariage">💍 Mariage</option>
                            <option value="entreprise">💼 Entreprise</option>
                            <option value="famille">👨‍👩‍👧‍👦 Famille</option>
                            <option value="romantique">❤️ Romantique</option>
                            <option value="autre">📌 Autre</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Allergies / Régime alimentaire</label>
                <input type="text" name="allergies" class="form-control" placeholder="Ex: Sans gluten, végétarien...">
            </div>
            
            <div class="form-group">
                <label>Notes supplémentaires</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Demandes particulières..."></textarea>
            </div>
            
            <button type="submit" class="btn-reserver">
                <i class="bi bi-calendar-check"></i> Réserver ma table
            </button>
        </form>
        
        <div class="info-reservation">
            <i class="bi bi-clock"></i> Horaires d'ouverture : <strong>Lun - Sam : 8h - 21h</strong><br>
            <i class="bi bi-telephone"></i> Pour toute annulation, contactez-nous au +223 74 74 03 03
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>