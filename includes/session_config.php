<?php
// ============================================
// CONFIGURATION SESSION PUBLIQUE (CLIENTS)
// ============================================

// Nom de session différent pour le site public
session_name('PUBLIC_SESSION');

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si le client est connecté
function isClientLoggedIn() {
    return isset($_SESSION['client_id']) && !empty($_SESSION['client_id']);
}

// Récupérer les infos du client connecté
function getClientInfo() {
    if (isClientLoggedIn()) {
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

// Vérifier si l'utilisateur est admin (via la session admin)
// Cette fonction est utilisée par le site public pour savoir si l'admin est connecté DANS UN AUTRE ONGLET
function isAdminConnected() {
    // Sauvegarder la session publique
    $old_session_name = session_name();
    $old_session_id = session_id();
    session_write_close();
    
    // Vérifier la session admin
    session_name('ADMIN_SESSION');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $is_admin = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
    
    // Restaurer la session publique
    session_write_close();
    if (!empty($old_session_name)) {
        session_name($old_session_name);
    } else {
        session_name('PUBLIC_SESSION');
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $is_admin;
}