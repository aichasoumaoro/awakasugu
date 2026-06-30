<?php
// ============================================
// FICHIER : includes/fonctions.php
// Fonctions utilisées partout dans le site
// ============================================

// ============================================
// FONCTIONS DE SESSION
// ============================================

// Démarrer la session publique (client)
function startPublicSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('PUBLIC_SESSION');
        session_start();
    }
}

// Démarrer la session admin
function startAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('ADMIN_SESSION');
        session_start();
    }
}

// Vérifier si l'admin est connecté
function isAdminLoggedIn() {
    $old_session_name = session_name();
    
    session_name('ADMIN_SESSION');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $is_logged = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
    
    session_write_close();
    if (!empty($old_session_name) && $old_session_name !== 'ADMIN_SESSION') {
        session_name($old_session_name);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    return $is_logged;
}

// Vérifier si le client est connecté
function isClientLoggedIn() {
    $old_session_name = session_name();
    
    session_name('PUBLIC_SESSION');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $is_logged = isset($_SESSION['client_id']) && !empty($_SESSION['client_id']);
    
    session_write_close();
    if (!empty($old_session_name) && $old_session_name !== 'PUBLIC_SESSION') {
        session_name($old_session_name);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    return $is_logged;
}

// ============================================
// VÉRIFICATION AVEC REDIRECTION
// ============================================

// Vérifier si l'admin est connecté - Redirige vers login si non connecté
function verifier_admin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit();
    }
}

// Vérifier si le client est connecté - Redirige vers connexion si non connecté
function verifier_client() {
    if (!isClientLoggedIn()) {
        header('Location: ' . SITE_URL . '/client/connexion.php');
        exit();
    }
}

// ============================================
// RÉCUPÉRATION DES INFORMATIONS
// ============================================
// NOTE : getClientInfo() est définie dans includes/session_check.php
// (version plus complète, avec getClientId/getClientName/getClientEmail)
// pour éviter une double déclaration fatale en PHP.
// Récupérer les infos de l'admin connecté
function getAdminInfo() {
    if (!isAdminLoggedIn()) return null;
    
    session_name('ADMIN_SESSION');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $info = [
        'id' => $_SESSION['admin_id'] ?? null,
        'nom' => $_SESSION['admin_nom'] ?? 'Admin',
        'email' => $_SESSION['admin_email'] ?? ''
    ];
    session_write_close();
    return $info;
}

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

// Formater un prix en FCFA
function formater_prix($montant) {
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

// Nettoyer les données saisies par l'utilisateur
function nettoyer($donnee) {
    return htmlspecialchars(strip_tags(trim($donnee)));
}

// Générer un numéro de commande unique
function generer_numero_commande($pdo) {
    $annee = date('Y');
    $stmt  = $pdo->query("SELECT COUNT(*) FROM commandes WHERE YEAR(created_at) = $annee");
    $total = $stmt->fetchColumn() + 1;
    return 'AWA-' . $annee . '-' . str_pad($total, 5, '0', STR_PAD_LEFT);
}

// Générer un numéro de commande restaurant
function generer_numero_commande_repas($pdo) {
    $annee = date('Y');
    $stmt  = $pdo->query("SELECT COUNT(*) FROM commandes_repas WHERE YEAR(created_at) = $annee");
    $total = $stmt->fetchColumn() + 1;
    return 'SOFIA-' . $annee . '-' . str_pad($total, 5, '0', STR_PAD_LEFT);
}

// Générer un numéro de facture unique
function generer_numero_facture($pdo) {
    $annee = date('Y');
    $stmt  = $pdo->query("SELECT COUNT(*) FROM factures WHERE YEAR(created_at) = $annee");
    $total = $stmt->fetchColumn() + 1;
    return 'FACT-' . $annee . '-' . str_pad($total, 5, '0', STR_PAD_LEFT);
}

// Uploader une image
function uploader_image($fichier, $dossier = 'produits') {
    $extensions_ok = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions_ok)) return false;
    if ($fichier['size'] > 5 * 1024 * 1024) return false;
    $nom = uniqid('img_') . '.' . $ext;
    $chemin = UPLOAD_DIR . $dossier . '/' . $nom;
    if (!is_dir(dirname($chemin))) {
        mkdir(dirname($chemin), 0777, true);
    }
    if (move_uploaded_file($fichier['tmp_name'], $chemin)) return $nom;
    return false;
}

// Uploader une vidéo
function uploader_video($fichier) {
    $extensions_ok = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mpg', 'mpeg'];
    $ext = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions_ok)) return false;
    if ($fichier['size'] > 100 * 1024 * 1024) return false;
    $nom = uniqid('video_') . '.' . $ext;
    $chemin = UPLOAD_DIR . 'videos/' . $nom;
    if (!is_dir(dirname($chemin))) {
        mkdir(dirname($chemin), 0777, true);
    }
    if (move_uploaded_file($fichier['tmp_name'], $chemin)) return $nom;
    return false;
}

// Extraire ID YouTube depuis une URL
function extraire_id_youtube($url) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m);
    return $m[1] ?? null;
}

// Calculer les points fidélité gagnés
function calculer_points($montant) {
    $points_par_1000 = defined('POINTS_PAR_1000_FCFA') ? POINTS_PAR_1000_FCFA : 1;
    return floor($montant / 1000) * $points_par_1000;
}

// Vérifier un code promo
function verifier_code_promo($pdo, $code, $montant) {
    $stmt = $pdo->prepare("
        SELECT * FROM codes_promo
        WHERE code = ? AND est_actif = 1
        AND (date_expiration IS NULL OR date_expiration >= CURDATE())
        AND (nb_utilisations_max IS NULL OR nb_utilisations < nb_utilisations_max)
    ");
    $stmt->execute([$code]);
    $promo = $stmt->fetch();
    if (!$promo) return ['valide' => false, 'message' => 'Code invalide ou expiré'];
    if ($montant < $promo['min_achat']) return ['valide' => false, 'message' => 'Montant minimum : ' . formater_prix($promo['min_achat'])];
    $reduction = $promo['type'] === 'pourcentage'
        ? ($montant * $promo['valeur']) / 100
        : $promo['valeur'];
    return ['valide' => true, 'reduction' => $reduction, 'promo' => $promo];
}

// Récupérer les alertes stock
function get_alertes_stock($pdo) {
    $stmt = $pdo->query("SELECT id, nom, stock, seuil_alerte FROM produits WHERE stock <= seuil_alerte AND est_visible = 1 ORDER BY stock ASC");
    return $stmt->fetchAll();
}

// ============================================
// FONCTIONS DE MESSAGES FLASH
// ============================================

// Enregistrer un message flash
function set_message($type, $texte) {
    startPublicSession();
    $_SESSION['flash_type']  = $type;
    $_SESSION['flash_texte'] = $texte;
}

// Récupérer et supprimer le message flash
function get_message() {
    startPublicSession();
    if (isset($_SESSION['flash_texte'])) {
        $msg = ['type' => $_SESSION['flash_type'], 'texte' => $_SESSION['flash_texte']];
        unset($_SESSION['flash_type'], $_SESSION['flash_texte']);
        return $msg;
    }
    return null;
}

// Rediriger vers une URL
function rediriger($url) {
    header('Location: ' . $url);
    exit();
}
?>