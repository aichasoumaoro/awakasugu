<?php
// includes/roles.php

/**
 * Définition des permissions par rôle
 * Chaque rôle a un tableau de permissions (true = autorisé)
 */
function getRolePermissions($role) {
    // Définition des permissions disponibles
    $allPermissions = [
        // Dashboard
        'view_dashboard' => true,
        
        // Produits
        'manage_products' => true,
        'view_products' => true,
        'create_products' => true,
        'edit_products' => true,
        'delete_products' => true,
        
        // Commandes
        'manage_orders' => true,
        'view_orders' => true,
        'update_order_status' => true,
        'delete_orders' => true,
        'view_all_orders' => true, // Voir les commandes des autres
        
        // Clients
        'manage_clients' => true,
        'view_clients' => true,
        'edit_clients' => true,
        
        // Utilisateurs (ADMIN)
        'manage_users' => true,       // Créer/modifier/supprimer des admins
        'view_users' => true,
        'assign_roles' => true,       // Changer le rôle d'un autre admin
        
        // Restaurant
        'manage_restaurant' => true,
        'manage_plats' => true,
        'manage_reservations' => true,
        
        // Finances
        'manage_finances' => true,
        'view_finances' => true,
        'manage_payments' => true,
        
        // Contenu
        'manage_content' => true,
        'manage_videos' => true,
        'manage_promotions' => true,
        
        // Paramètres
        'manage_settings' => true,    // Configuration du site
        'manage_maintenance' => true, // Mode maintenance
        
        // Logs
        'view_logs' => true,
        'delete_logs' => true,
    ];
    
    // Définition des rôles avec leurs permissions
    $roles = [
        'super_admin' => array_fill_keys(array_keys($allPermissions), true), // TOUT autorisé
        
        'directeur' => [
            'view_dashboard' => true,
            'view_products' => true,
            'manage_products' => true,
            'create_products' => true,
            'edit_products' => true,
            'delete_products' => true,
            'manage_orders' => true,
            'view_orders' => true,
            'update_order_status' => true,
            'view_all_orders' => true,
            'manage_clients' => true,
            'view_clients' => true,
            'edit_clients' => true,
            'view_users' => true,           // Peut voir les admins
            'manage_restaurant' => true,
            'manage_plats' => true,
            'manage_reservations' => true,
            'manage_finances' => true,
            'view_finances' => true,
            'manage_payments' => true,
            'manage_content' => true,
            'manage_videos' => true,
            'manage_promotions' => true,
            'manage_settings' => false,      // PAS accès aux paramètres
            'manage_maintenance' => false,   // PAS le mode maintenance
            'manage_users' => false,         // PAS de gestion des admins
            'assign_roles' => false,
            'view_logs' => false,
            'delete_logs' => false,
        ],
        
        'admin' => [
            'view_dashboard' => true,
            'view_products' => true,
            'manage_products' => false,      // Ne peut pas créer/supprimer
            'create_products' => false,
            'edit_products' => true,         // Peut modifier
            'delete_products' => false,
            'manage_orders' => true,
            'view_orders' => true,
            'update_order_status' => true,
            'view_all_orders' => false,      // Ne voit que ses commandes
            'manage_clients' => false,
            'view_clients' => true,
            'edit_clients' => false,
            'view_users' => false,
            'manage_restaurant' => true,
            'manage_plats' => true,
            'manage_reservations' => true,
            'manage_finances' => false,
            'view_finances' => true,
            'manage_payments' => false,
            'manage_content' => false,
            'manage_videos' => true,
            'manage_promotions' => true,
            // Tous les autres à false
        ],
        
        'admin2' => [
            'view_dashboard' => true,
            'view_orders' => true,
            'update_order_status' => true,   // Peut changer statut
            'view_products' => true,
            'view_clients' => true,
            // Tous les autres à false
        ],
    ];
    
    // Pour admin2, on remplit avec false
    if ($role === 'admin2') {
        $perms = [];
        foreach (array_keys($allPermissions) as $perm) {
            $perms[$perm] = false;
        }
        // On autorise les permissions spécifiques
        $perms['view_dashboard'] = true;
        $perms['view_orders'] = true;
        $perms['update_order_status'] = true;
        $perms['view_products'] = true;
        $perms['view_clients'] = true;
        return $perms;
    }
    
    return $roles[$role] ?? [];
}

/**
 * Vérifie si un utilisateur a une permission
 */
function hasPermission($pdo, $admin_id, $permission) {
    if (!$admin_id) return false;
    
    // Récupérer le rôle de l'admin
    $stmt = $pdo->prepare("SELECT role FROM admin WHERE id = ? AND is_active = 1");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) return false;
    
    $permissions = getRolePermissions($admin['role']);
    return $permissions[$permission] ?? false;
}

/**
 * Redirige si l'utilisateur n'a pas la permission
 */
function requirePermission($pdo, $permission, $redirect = 'dashboard.php') {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
    
    if (!hasPermission($pdo, $_SESSION['admin_id'], $permission)) {
        $_SESSION['error'] = "Vous n'avez pas la permission d'accéder à cette page.";
        header('Location: ' . $redirect);
        exit;
    }
}