<?php
// ============================================
// SESSION PUBLIQUE UNIQUEMENT
// ============================================
// IMPORTANT : certaines pages (client/mon_compte.php, boutique/panier.php,
// etc.) démarrent DÉJÀ leur propre session PUBLIC_SESSION avant d'inclure
// ce fichier. On ne doit JAMAIS fermer/relancer une session déjà active
// sous le bon nom, sinon les données ($_SESSION['client_id']...) se
// perdent silencieusement (nouvel ID de session généré, déconnecté du
// cookie envoyé par le navigateur).

if (session_status() === PHP_SESSION_ACTIVE) {
    // Une session est déjà active.
    if (session_name() === 'PUBLIC_SESSION') {
        // C'est déjà la bonne session publique : on ne touche à rien.
    } else {
        // C'est une session différente (ex: ADMIN_SESSION resté ouverte
        // par erreur) : on la ferme proprement avant de basculer.
        session_write_close();
        session_name('PUBLIC_SESSION');
        session_start();
    }
} else {
    // Aucune session active : on démarre normalement la session publique.
    session_name('PUBLIC_SESSION');
    session_start();
}

// ============================================
// ✅ INCLURE LA CONFIGURATION
// ============================================
require_once __DIR__ . '/config.php';

// ============================================
// ✅ VÉRIFICATION MAINTENANCE
// ============================================
require_once __DIR__ . '/maintenance_check.php';

// ============================================
// CHARGEMENT DES FONCTIONS
// ============================================
require_once __DIR__ . '/fonctions.php';

// ============================================
// ✅ CHARGER LES FONCTIONS DU PANIER
// ============================================
require_once __DIR__ . '/panier_fonctions.php';

// ============================================
// CHARGER LE PANIER DEPUIS LA BDD SI CLIENT CONNECTÉ
// ============================================
if (isset($_SESSION['client_id']) && isset($pdo)) {
    // Si le panier est vide en session mais existe en BDD, le charger
    if (empty($_SESSION['panier'])) {
        $_SESSION['panier'] = chargerPanierClient($_SESSION['client_id'], $pdo);
    }
}

// ============================================
// DÉTECTION DISCRÈTE DE L'ADMIN (Awa)
// ============================================
require_once __DIR__ . '/session_check.php';
$est_admin_connecte = checkAdminSession();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= isset($titre_page) ? $titre_page . ' — ' . SITE_NOM : SITE_NOM ?></title>
    <meta name="description" content="<?= isset($meta_desc) ? $meta_desc : 'Awa Ka Sugu — Boutique IBA Design et Restaurant SOFIA à Bamako, Mali' ?>">

    <!-- ===== BOOTSTRAP 5 CSS ===== -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- ===== BOOTSTRAP ICONS ===== -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- ===== GOOGLE FONTS ===== -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- ===== VOTRE CSS PERSONNALISÉ ===== -->
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">

    <script>const BASE_URL = '<?= SITE_URL ?>';</script>
</head>
<body>