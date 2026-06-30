<?php
// ============================================
// VÉRIFICATION DE SESSION POUR LE SITE PUBLIC
// ============================================

// Démarrer la session publique
if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

// Fonction pour vérifier si un client est connecté
function isClientConnected() {
    return isset($_SESSION['client_id']) && !empty($_SESSION['client_id']);
}

// Fonction pour vérifier si l'admin est connecté (depuis l'admin)
// Version robuste : on bascule proprement entre PUBLIC_SESSION et
// ADMIN_SESSION sans jamais régénérer d'ID de session par erreur
// (ce qui ferait perdre $_SESSION['client_id'] au retour).
function checkAdminSession() {
    $had_session = (session_status() === PHP_SESSION_ACTIVE);
    $public_session_name = $had_session ? session_name() : 'PUBLIC_SESSION';

    // Fermer proprement la session publique en cours (on garde son nom
    // en mémoire pour y revenir exactement à l'identique après).
    if ($had_session) {
        session_write_close();
    }

    // Ouvrir la session admin (PHP retrouve son ID via le cookie
    // ADMIN_SESSION envoyé par le navigateur, ou en crée un nouveau
    // s'il n'existe pas — sans toucher au cookie PUBLIC_SESSION).
    session_name('ADMIN_SESSION');
    session_start();

    $is_admin = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);

    session_write_close();

    // Revenir à la session publique d'origine. On NE TOUCHE PAS à
    // session_id() ici : PHP retrouve automatiquement le bon ID via
    // le cookie PUBLIC_SESSION existant dans le navigateur.
    session_name($public_session_name);
    session_start();

    return $is_admin;
}

// Récupérer les infos du client connecté
function getClientInfo() {
    if (isClientConnected()) {
        return [
            'id' => $_SESSION['client_id'],
            'nom' => $_SESSION['client_nom'] ?? 'Client',
            'email' => $_SESSION['client_email'] ?? '',
            'telephone' => $_SESSION['client_telephone'] ?? ''
        ];
    }
    return null;
}

// Récupérer l'ID du client connecté
function getClientId() {
    return $_SESSION['client_id'] ?? null;
}

// Récupérer le nom du client connecté
function getClientName() {
    return $_SESSION['client_nom'] ?? 'Client';
}

// Récupérer l'email du client connecté
function getClientEmail() {
    return $_SESSION['client_email'] ?? '';
}