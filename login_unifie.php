<?php
// ============================================
// LOGIN UNIFIÉ (Client + Admin) - Awa Ka Sugu
// ============================================

// Démarrer les sessions
session_name('PUBLIC_SESSION');
session_start();

require_once 'includes/fonctions.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        
        // ============================================
        // 1. VÉRIFICATION ADMIN (Prioritaire)
        // ============================================
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['mot_de_passe'])) {
            // C'est un admin ! On lance la session admin
            session_name('ADMIN_SESSION');
            session_start();
            
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_nom'] = $admin['nom'];
            $_SESSION['admin_email'] = $admin['email'];
            
            // Redirection vers le dashboard admin
            header('Location: admin/dashboard.php');
            exit;
        }
        
        // ============================================
        // 2. VÉRIFICATION CLIENT (Si ce n'est pas un admin)
        // ============================================
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        $client = $stmt->fetch();
        
        if ($client && password_verify($password, $client['mot_de_passe'])) {
            // C'est un client classique
            session_name('PUBLIC_SESSION');
            session_start();
            
            $_SESSION['client_id'] = $client['id'];
            $_SESSION['client_nom'] = $client['nom'];
            $_SESSION['client_email'] = $client['email'];
            $_SESSION['client_telephone'] = $client['telephone'];
            
            // Redirection vers le compte client
            header('Location: client/mon_compte.php');
            exit;
        }
        
        // ============================================
        // 3. SI RIEN NE CORRESPOND
        // ============================================
        $error = 'Email ou mot de passe incorrect.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Utilisez vos CSS existants ou celui-ci -->
    <style>
        body {
            font-family: 'Jost', sans-serif;
            background: #F8F7F5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.06);
            max-width: 400px;
            width: 100%;
            border: 1px solid rgba(200,146,42,0.1);
        }
        .login-box h2 {
            font-family: 'Playfair Display', serif;
            color: #C8922A;
            text-align: center;
            margin-bottom: 25px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { font-weight: 600; font-size: 0.85rem; color: #333; }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #E8E0D8;
            border-radius: 8px;
            font-family: 'Jost', sans-serif;
        }
        .form-control:focus { border-color: #C8922A; outline: none; }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(200,146,42,0.3); }
        .alert-error {
            background: #FDE8E8;
            border-left: 4px solid #E74C3C;
            padding: 10px 15px;
            border-radius: 6px;
            color: #721C24;
            margin-bottom: 15px;
            font-size: 0.85rem;
        }
        .back-link { text-align: center; margin-top: 15px; font-size: 0.85rem; }
        .back-link a { color: #8A99AA; text-decoration: none; }
        .back-link a:hover { color: #C8922A; }
    </style>
</head>
<body>

<div class="login-box">
    <h2>✦ Connexion ✦</h2>

    <?php if($error): ?>
        <div class="alert-error"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" placeholder="votre@email.com" required>
        </div>
        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-login"><i class="bi bi-box-arrow-in-right"></i> Se connecter</button>
    </form>

    <div class="back-link">
        <a href="index.php"><i class="bi bi-arrow-left"></i> Retour à l'accueil</a>
    </div>
</div>

</body>
</html>