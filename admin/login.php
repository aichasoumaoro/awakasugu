<?php
// ============================================
// SESSION ADMIN SÉPARÉE
// ============================================
session_name('ADMIN_SESSION');
session_start();

// Si déjà connecté, rediriger
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

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
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['mot_de_passe'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_nom'] = $admin['nom'];
            $_SESSION['admin_email'] = $admin['email'];
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Jost', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at 30% 50%, rgba(200,146,42,0.05), transparent 70%);
            pointer-events: none;
        }
        .login-container {
            background: rgba(26, 26, 26, 0.95);
            border-radius: 24px;
            padding: 45px 40px;
            max-width: 420px;
            width: 100%;
            border: 1px solid rgba(200,146,42,0.15);
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
        }
        .login-container .logo {
            text-align: center;
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: #C8922A;
            letter-spacing: 4px;
            margin-bottom: 5px;
        }
        .login-container .logo-sub {
            text-align: center;
            color: rgba(255,255,255,0.2);
            font-size: 0.6rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 30px;
        }
        .decor-line {
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #C8922A, transparent);
            margin: 0 auto 25px;
        }
        .login-container .form-group {
            margin-bottom: 20px;
        }
        .login-container label {
            display: block;
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 6px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .login-container label i {
            color: #C8922A;
            margin-right: 6px;
        }
        .login-container input {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            background: rgba(255,255,255,0.04);
            color: #fff;
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: 'Jost', sans-serif;
        }
        .login-container input::placeholder {
            color: rgba(255,255,255,0.2);
        }
        .login-container input:focus {
            outline: none;
            border-color: #C8922A;
            background: rgba(255,255,255,0.07);
            box-shadow: 0 0 0 3px rgba(200,146,42,0.1);
        }
        .login-container .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .login-container .btn-login:hover {
            background: linear-gradient(135deg, #9A6E1A, #C8922A);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(200,146,42,0.3);
        }
        .login-container .btn-login:active {
            transform: translateY(0);
        }
        .login-container .error {
            background: rgba(231,76,60,0.12);
            border-left: 3px solid #E74C3C;
            color: #E74C3C;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .login-container .error i {
            font-size: 1.1rem;
        }
        .session-info {
            background: rgba(200,146,42,0.05);
            border: 1px solid rgba(200,146,42,0.08);
            border-radius: 10px;
            padding: 10px 14px;
            text-align: center;
            color: rgba(255,255,255,0.2);
            font-size: 0.6rem;
            margin-top: 20px;
            letter-spacing: 0.5px;
        }
        .session-info i { 
            color: #C8922A; 
            margin-right: 6px;
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.15);
        }
        .login-footer a {
            color: rgba(200,146,42,0.5);
            text-decoration: none;
            transition: color 0.3s;
        }
        .login-footer a:hover {
            color: #C8922A;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo">✦ AWA KA SUGU</div>
    <div class="logo-sub">Espace Administration</div>
    <div class="decor-line"></div>

    <?php if($error): ?>
        <div class="error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label><i class="bi bi-envelope"></i> Email</label>
            <input type="email" name="email" placeholder="admin@awakasugu.ml" required>
        </div>
        <div class="form-group">
            <label><i class="bi bi-lock"></i> Mot de passe</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right"></i> Se connecter
        </button>
    </form>

    <div class="session-info">
        <i class="bi bi-shield-lock-fill"></i> 
        Session admin séparée du site public
    </div>

    <div class="login-footer">
        <a href="../index.php">← Retour au site</a>
    </div>
</div>

</body>
</html>