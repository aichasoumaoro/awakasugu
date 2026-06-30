<?php
// ============================================
// SESSION PUBLIQUE SÉPARÉE
// ============================================
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// VÉRIFICATION MAINTENANCE
// ============================================
require_once '../includes/maintenance_check.php';

$titre_page = 'Vidéos - Awa Ka Sugu';
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

// Récupérer les vidéos actives
$videos = $pdo->query("SELECT * FROM videos WHERE est_active = 1 ORDER BY created_at DESC")->fetchAll();

function getYoutubeId($url) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/', $url, $matches);
    return $matches[1] ?? '';
}

function getYoutubeEmbedUrl($url) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/', $url, $matches);
    return isset($matches[1]) ? "https://www.youtube.com/embed/" . $matches[1] : $url;
}

function getVideoUrl($video) {
    if ($video['type'] == 'local' && !empty($video['fichier_video'])) {
        return '../uploads/videos/' . $video['fichier_video'];
    }
    return getYoutubeEmbedUrl($video['url_ou_fichier']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vidéos - Awa Ka Sugu</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Jost:wght@300;400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jost', sans-serif; background: #F8F7F5; color: #1A1A1A; }
        
        .banner {
            background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
            padding: 70px 20px 60px;
            text-align: center;
        }
        
        .banner h1 {
            font-size: 2.8rem;
            color: #C8922A;
            font-weight: 700;
        }
        
        .banner p {
            color: rgba(255,255,255,0.5);
            margin-top: 12px;
        }
        
        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 20px 60px;
        }
        
        .videos-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 40px;
        }
        
        .video-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.4s;
            cursor: pointer;
        }
        
        .video-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .video-thumbnail {
            position: relative;
            aspect-ratio: 16/9;
            overflow: hidden;
            background: #1A1A1A;
        }
        
        .video-thumbnail img, .video-thumbnail video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .video-card:hover .video-thumbnail img {
            transform: scale(1.05);
        }
        
        .play-btn {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background: rgba(200,146,42,0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .play-btn i {
            font-size: 1.8rem;
            color: white;
            margin-left: 5px;
        }
        
        .video-card:hover .play-btn {
            transform: translate(-50%, -50%) scale(1.1);
            background: #C8922A;
        }
        
        .video-info {
            padding: 20px;
        }
        
        .video-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1A1A1A;
            margin-bottom: 8px;
        }
        
        .video-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.7rem;
            color: #C8922A;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px;
            background: white;
            border-radius: 24px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #E8D5B0;
            margin-bottom: 20px;
        }
        
        .video-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .video-modal.active {
            display: flex;
        }
        
        .modal-content {
            position: relative;
            width: 90%;
            max-width: 1000px;
            background: #1A1A1A;
            border-radius: 20px;
            overflow: hidden;
        }
        
        .modal-video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
        }
        
        .modal-video-container video, .modal-video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .modal-close {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
        }
        
        .modal-info {
            padding: 20px;
            color: white;
        }
        
        @media (max-width: 900px) {
            .videos-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 600px) {
            .videos-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="banner">
    <h1>📹 VIDÉOS</h1>
    <p>Découvrez les actualités et conseils d'Awa Doumbia</p>
</div>

<div class="container">
    <?php if (empty($videos)): ?>
        <div class="empty-state">
            <i class="bi bi-camera-reels"></i>
            <p>Aucune vidéo disponible pour le moment</p>
            <p style="font-size: 0.8rem;">Revenez bientôt pour découvrir nos contenus exclusifs !</p>
        </div>
    <?php else: ?>
        <div class="videos-grid">
            <?php foreach ($videos as $video): ?>
                <?php
                // Détecter si c'est une vidéo locale ou YouTube
                $isLocal = ($video['type'] == 'local' && !empty($video['fichier_video']));
                $videoUrl = getVideoUrl($video);
                $thumbUrl = '';
                
                if ($isLocal) {
                    $thumbUrl = $videoUrl;
                } else {
                    $youtubeId = getYoutubeId($video['url_ou_fichier']);
                    $thumbUrl = "https://img.youtube.com/vi/" . $youtubeId . "/maxresdefault.jpg";
                }
                ?>
                <div class="video-card" onclick="openVideoModal('<?= $videoUrl ?>', '<?= htmlspecialchars($video['titre']) ?>', '<?= $video['type'] ?>')">
                    <div class="video-thumbnail">
                        <?php if($isLocal): ?>
                            <video src="<?= $thumbUrl ?>" muted></video>
                        <?php else: ?>
                            <img src="<?= $thumbUrl ?>" 
                                 alt="<?= htmlspecialchars($video['titre']) ?>"
                                 onerror="this.src='https://placehold.co/400x225/C8922A/FFF?text=<?= urlencode($video['titre']) ?>'">
                        <?php endif; ?>
                        <div class="play-btn">
                            <i class="bi bi-play-fill"></i>
                        </div>
                    </div>
                    <div class="video-info">
                        <div class="video-title"><?= htmlspecialchars($video['titre']) ?></div>
                        <div class="video-meta">
                            <span><i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($video['created_at'])) ?></span>
                            <span><i class="bi bi-camera-reels"></i> <?= $isLocal ? 'LOCAL' : strtoupper($video['type']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div id="videoModal" class="video-modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeVideoModal()">&times;</button>
        <div class="modal-video-container" id="modalVideoContainer">
        </div>
        <div class="modal-info">
            <h3 id="modalTitle"></h3>
        </div>
    </div>
</div>

<script>
    function getYoutubeId(url) {
        const match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/);
        return match ? match[1] : '';
    }
    
    function openVideoModal(url, title, type) {
        const container = document.getElementById('modalVideoContainer');
        if (type === 'local') {
            container.innerHTML = '<video controls autoplay><source src="' + url + '" type="video/mp4">Votre navigateur ne supporte pas la lecture vidéo.</video>';
        } else {
            container.innerHTML = '<iframe src="' + url + '" frameborder="0" allowfullscreen></iframe>';
        }
        document.getElementById('modalTitle').innerText = title;
        document.getElementById('videoModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeVideoModal() {
        document.getElementById('modalVideoContainer').innerHTML = '';
        document.getElementById('videoModal').classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    
    document.getElementById('videoModal').addEventListener('click', function(e) {
        if (e.target === this) closeVideoModal();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeVideoModal();
    });
</script>

<?php require_once '../includes/footer.php'; ?>