<?php
// ============================================
// SESSION PUBLIQUE SÉPARÉE
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

// ============================================
// VÉRIFICATION MAINTENANCE
// ============================================
require_once '../includes/maintenance_check.php';

$titre_page = 'Vidéos - Awa Ka Sugu';
require_once '../includes/header.php';

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

// ============================================
// FONCTIONS
// ============================================
function getYoutubeId($url) {
    if (empty($url)) return '';
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/', $url, $matches);
    return $matches[1] ?? '';
}

function getYoutubeEmbedUrl($url) {
    if (empty($url)) return '#';
    $id = getYoutubeId($url);
    return !empty($id) ? "https://www.youtube.com/embed/" . $id : $url;
}

function getVideoUrl($video) {
    if (!empty($video['fichier_video'])) {
        $path = '../uploads/videos/' . $video['fichier_video'];
        if (file_exists($path)) {
            return $path;
        }
    }
    if ($video['type'] == 'local' && !empty($video['fichier_video'])) {
        return '../uploads/videos/' . $video['fichier_video'];
    }
    if (!empty($video['url_ou_fichier'])) {
        return getYoutubeEmbedUrl($video['url_ou_fichier']);
    }
    return '#';
}

function isLocalVideo($video) {
    if (!empty($video['fichier_video'])) {
        $path = '../uploads/videos/' . $video['fichier_video'];
        if (file_exists($path)) {
            return true;
        }
    }
    if ($video['type'] == 'local' && !empty($video['fichier_video'])) {
        return true;
    }
    return false;
}

// ============================================
// RÉCUPÉRATION DES VIDÉOS
// ============================================
$videos = [];
try {
    $stmt = $pdo->query("SELECT * FROM videos WHERE est_active = 1 ORDER BY created_at DESC");
    $videos = $stmt->fetchAll();
} catch(PDOException $e) {
    $videos = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vidéos - Awa Ka Sugu</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Jost:wght@300;400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Jost', sans-serif; 
            background: #000; 
            color: #fff;
            overflow: hidden;
            height: 100vh;
        }
        
        /* ===== HEADER TIKTOK ===== */
        .tiktok-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(180deg, rgba(0,0,0,0.8) 0%, transparent 100%);
            pointer-events: none;
        }
        .tiktok-header > * { pointer-events: auto; }
        .tiktok-header .logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: #C8922A;
            text-decoration: none;
        }
        .tiktok-header .logo span { color: #fff; }
        .tiktok-header .actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .tiktok-header .actions .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1.5px solid rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.7);
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
        }
        .tiktok-header .actions .btn-nav i {
            font-size: 0.85rem;
        }
        .tiktok-header .actions .btn-nav:hover {
            background: rgba(200,146,42,0.2);
            border-color: #C8922A;
            color: #C8922A;
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(200,146,42,0.15);
        }
        .tiktok-header .actions .btn-nav.btn-shop {
            background: linear-gradient(135deg, rgba(200,146,42,0.2), rgba(200,146,42,0.05));
            border-color: rgba(200,146,42,0.3);
            color: #C8922A;
        }
        .tiktok-header .actions .btn-nav.btn-shop:hover {
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            border-color: #C8922A;
            color: #1A1A1A;
            box-shadow: 0 4px 25px rgba(200,146,42,0.3);
        }
        .tiktok-header .actions .btn-nav.btn-home {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.6);
        }
        .tiktok-header .actions .btn-nav.btn-home:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
            color: #fff;
        }

        /* ===== CONTAINER VIDÉO ===== */
        .video-container {
            height: 100vh;
            overflow-y: scroll;
            scroll-snap-type: y mandatory;
            scroll-behavior: smooth;
        }
        .video-container::-webkit-scrollbar { display: none; }

        /* ===== CHAQUE VIDÉO ===== */
        .video-item {
            position: relative;
            height: 100vh;
            width: 100%;
            scroll-snap-align: start;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
            overflow: hidden;
        }

        /* ===== LECTEUR VIDÉO ===== */
        .video-player {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0D0D0D;
        }
        .video-player video,
        .video-player iframe {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border: none;
            background: #000;
        }

        /* ===== OVERLAY INFOS (style TikTok) ===== */
        .video-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px 20px 30px;
            background: linear-gradient(0deg, rgba(0,0,0,0.85) 0%, transparent 100%);
            pointer-events: none;
        }
        .video-overlay > * { pointer-events: auto; }

        /* Info utilisateur */
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #C8922A;
            flex-shrink: 0;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
        }
        .user-name span { color: #C8922A; }
        .user-handle {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.5);
        }

        /* Titre et description */
        .video-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 4px;
        }
        .video-desc {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.6);
            line-height: 1.4;
        }

        /* Indicateur de progression */
        .video-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(255,255,255,0.1);
            z-index: 5;
        }
        .video-progress .bar {
            height: 100%;
            background: #C8922A;
            width: 0%;
            transition: width 0.5s linear;
        }

        /* ===== SIDE ACTIONS (simplifié) ===== */
        .side-actions {
            position: absolute;
            right: 16px;
            bottom: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
            pointer-events: none;
            z-index: 10;
        }
        .side-actions > * { pointer-events: auto; }
        .side-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
        }
        .side-action:hover { color: #C8922A; transform: scale(1.05); }
        .side-action i { font-size: 1.6rem; }
        .side-action span {
            font-size: 0.6rem;
            font-weight: 600;
        }
        .side-action .badge {
            background: #C8922A;
            color: #000;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 700;
        }

        /* ===== INDICATEUR DE SCROLL ===== */
        .scroll-indicator {
            position: fixed;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 50;
            display: flex;
            flex-direction: column;
            gap: 4px;
            pointer-events: none;
        }
        .scroll-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
            cursor: pointer;
            pointer-events: auto;
        }
        .scroll-dot.active {
            background: #C8922A;
            transform: scale(1.3);
            box-shadow: 0 0 12px rgba(200,146,42,0.5);
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
            color: rgba(255,255,255,0.5);
        }
        .empty-state i {
            font-size: 4rem;
            color: rgba(200,146,42,0.3);
            margin-bottom: 20px;
        }
        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: #fff;
            margin-bottom: 8px;
        }
        .empty-state p { font-size: 0.9rem; }

        /* ===== BADGE "VIDÉO" ===== */
        .badge-video {
            display: inline-block;
            background: rgba(200,146,42,0.15);
            color: #C8922A;
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
            border: 1px solid rgba(200,146,42,0.1);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .tiktok-header .logo { font-size: 1rem; }
            .tiktok-header .actions .btn-nav {
                padding: 5px 12px;
                font-size: 0.6rem;
            }
            .tiktok-header .actions .btn-nav i { font-size: 0.7rem; }
            .user-avatar { width: 32px; height: 32px; }
            .user-name { font-size: 0.8rem; }
            .video-title { font-size: 0.78rem; }
            .video-desc { font-size: 0.7rem; }
            .side-actions { right: 10px; bottom: 80px; gap: 14px; }
            .side-action i { font-size: 1.3rem; }
            .side-action span { font-size: 0.5rem; }
            .video-overlay { padding: 14px 14px 20px; }
        }
        @media (max-width: 480px) {
            .tiktok-header .actions .btn-nav {
                padding: 4px 10px;
                font-size: 0.5rem;
            }
            .tiktok-header .actions .btn-nav i { font-size: 0.6rem; }
            .tiktok-header .actions .btn-nav span { display: none; }
            .side-actions { right: 6px; bottom: 70px; gap: 10px; }
            .side-action i { font-size: 1.1rem; }
            .video-overlay { padding: 10px 10px 16px; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header class="tiktok-header">
    <a href="../index.php" class="logo">AWA KA <span>SUGU</span></a>
    <div class="actions">
        <a href="../index.php" class="btn-nav btn-home" title="Accueil">
            <i class="bi bi-house-heart-fill"></i>
            <span>Accueil</span>
        </a>
        <a href="../boutique/catalogue.php" class="btn-nav btn-shop" title="Boutique">
            <i class="bi bi-bag-fill"></i>
            <span>Boutique</span>
        </a>
    </div>
</header>

<!-- ===== INDICATEUR DE SCROLL ===== -->
<div class="scroll-indicator" id="scrollIndicator"></div>

<!-- ===== CONTAINER VIDÉOS ===== -->
<div class="video-container" id="videoContainer">

    <?php if (empty($videos)): ?>
        <div class="video-item">
            <div class="empty-state">
                <i class="bi bi-camera-reels"></i>
                <h3>Aucune vidéo disponible</h3>
                <p>Revenez bientôt pour découvrir nos contenus exclusifs.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($videos as $index => $video): 
            $isLocal = isLocalVideo($video);
            $videoUrl = getVideoUrl($video);
            $titre = htmlspecialchars($video['titre'] ?? 'Vidéo');
            $description = htmlspecialchars($video['description'] ?? '');
            $created_at = date('d/m/Y', strtotime($video['created_at'] ?? 'now'));
            $vues = number_format($video['vues'] ?? 0, 0, ',', ' ');
            $type_label = $isLocal ? '📹 Local' : '▶️ YouTube';
        ?>
        <div class="video-item" data-index="<?= $index ?>">
            <!-- Lecteur vidéo -->
            <div class="video-player">
                <?php if($isLocal): ?>
                    <video muted playsinline preload="metadata" poster="">
                        <source src="<?= $videoUrl ?>" type="video/mp4">
                        Votre navigateur ne supporte pas la lecture vidéo.
                    </video>
                <?php else: ?>
                    <iframe src="<?= $videoUrl ?>?autoplay=0&rel=0&controls=1" frameborder="0" allowfullscreen allow="encrypted-media"></iframe>
                <?php endif; ?>
            </div>

            <!-- Overlay infos -->
            <div class="video-overlay">
                <div class="user-info">
                    <div class="user-avatar">
                        <img src="../assets/images/awa1.jpeg" alt="Awa Doumbia" onerror="this.src='https://placehold.co/40x40/C8922A/FFF?text=A'">
                    </div>
                    <div>
                        <div class="user-name">Awa <span>Doumbia</span></div>
                        <div class="user-handle">@awadoumbia223 • <?= $type_label ?></div>
                    </div>
                </div>
                <div class="video-title"><?= $titre ?></div>
                <?php if (!empty($description)): ?>
                    <div class="video-desc"><?= $description ?></div>
                <?php endif; ?>
            </div>

            <!-- Actions latérales (Lecture + Date + Partager) -->
            <div class="side-actions">
                <div class="side-action" onclick="togglePlay(this)">
                    <i class="bi bi-play-fill"></i>
                    <span>Lecture</span>
                </div>
                <div class="side-action">
                    <i class="bi bi-calendar3"></i>
                    <span><?= $created_at ?></span>
                </div>
                <div class="side-action" onclick="partager()">
                    <i class="bi bi-share"></i>
                    <span>Partager</span>
                </div>
            </div>

            <!-- Barre de progression -->
            <div class="video-progress">
                <div class="bar" id="progressBar-<?= $index ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    // ============================================
    // VARIABLES
    // ============================================
    const container = document.getElementById('videoContainer');
    const videos = container.querySelectorAll('.video-item');
    let currentIndex = 0;
    let isPlaying = false;
    let videoPlayers = [];

    // ============================================
    // CRÉER LES INDICATEURS DE SCROLL
    // ============================================
    const indicator = document.getElementById('scrollIndicator');
    videos.forEach((_, i) => {
        const dot = document.createElement('div');
        dot.className = 'scroll-dot' + (i === 0 ? ' active' : '');
        dot.dataset.index = i;
        dot.addEventListener('click', () => scrollToVideo(i));
        indicator.appendChild(dot);
    });

    // ============================================
    // INITIALISER LES LECTEURS VIDÉO
    // ============================================
    videos.forEach((item, index) => {
        const video = item.querySelector('video');
        const iframe = item.querySelector('iframe');
        
        if (video) {
            videoPlayers[index] = video;
            if (index === 0) {
                video.muted = false;
                video.play().catch(() => {});
                isPlaying = true;
                updatePlayIcon(index, true);
            }
            
            video.addEventListener('timeupdate', () => {
                if (video.duration) {
                    const progress = (video.currentTime / video.duration) * 100;
                    const bar = document.getElementById('progressBar-' + index);
                    if (bar) bar.style.width = progress + '%';
                }
            });
        } else if (iframe) {
            videoPlayers[index] = iframe;
        }
    });

    // ============================================
    // DÉTECTION DE LA VIDÉO ACTIVE (SCROLL)
    // ============================================
    let isScrolling = false;
    container.addEventListener('scroll', () => {
        if (isScrolling) return;
        isScrolling = true;
        requestAnimationFrame(() => {
            const containerRect = container.getBoundingClientRect();
            let activeIndex = 0;
            
            videos.forEach((item, index) => {
                const rect = item.getBoundingClientRect();
                const center = rect.top + rect.height / 2;
                const containerCenter = containerRect.top + containerRect.height / 2;
                
                if (Math.abs(center - containerCenter) < Math.abs(rect.height / 2)) {
                    activeIndex = index;
                }
            });
            
            if (activeIndex !== currentIndex) {
                const oldVideo = videoPlayers[currentIndex];
                if (oldVideo && oldVideo.tagName === 'VIDEO') {
                    oldVideo.pause();
                    updatePlayIcon(currentIndex, false);
                }
                
                currentIndex = activeIndex;
                const newVideo = videoPlayers[currentIndex];
                if (newVideo && newVideo.tagName === 'VIDEO') {
                    newVideo.muted = false;
                    newVideo.play().catch(() => {});
                    updatePlayIcon(currentIndex, true);
                    isPlaying = true;
                }
                
                document.querySelectorAll('.scroll-dot').forEach((dot, i) => {
                    dot.classList.toggle('active', i === currentIndex);
                });
            }
            
            isScrolling = false;
        });
    }, { passive: true });

    // ============================================
    // TOGGLE PLAY/PAUSE
    // ============================================
    function togglePlay(element) {
        const item = element.closest('.video-item');
        const index = parseInt(item.dataset.index);
        const video = videoPlayers[index];
        
        if (!video || video.tagName !== 'VIDEO') return;
        
        if (video.paused) {
            video.play().catch(() => {});
            isPlaying = true;
            updatePlayIcon(index, true);
        } else {
            video.pause();
            isPlaying = false;
            updatePlayIcon(index, false);
        }
    }

    function updatePlayIcon(index, playing) {
        const item = videos[index];
        if (!item) return;
        const playBtn = item.querySelector('.side-action:first-child i');
        const label = item.querySelector('.side-action:first-child span');
        if (playBtn) {
            playBtn.className = playing ? 'bi bi-pause-fill' : 'bi bi-play-fill';
        }
        if (label) {
            label.textContent = playing ? 'Pause' : 'Lecture';
        }
    }

    // ============================================
    // CLIC SUR LA VIDÉO (PLAY/PAUSE)
    // ============================================
    videos.forEach((item, index) => {
        const video = item.querySelector('video');
        if (video) {
            video.addEventListener('click', () => {
                if (video.paused) {
                    video.play().catch(() => {});
                    isPlaying = true;
                    updatePlayIcon(index, true);
                } else {
                    video.pause();
                    isPlaying = false;
                    updatePlayIcon(index, false);
                }
            });
        }
    });

    // ============================================
    // NAVIGATION AU CLAVIER (FLÈCHES HAUT/BAS)
    // ============================================
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const nextIndex = Math.min(currentIndex + 1, videos.length - 1);
            scrollToVideo(nextIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prevIndex = Math.max(currentIndex - 1, 0);
            scrollToVideo(prevIndex);
        }
    });

    function scrollToVideo(index) {
        if (index === currentIndex) return;
        const item = videos[index];
        if (item) {
            item.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ============================================
    // PARTAGER
    // ============================================
    function partager() {
        const url = window.location.href;
        if (navigator.share) {
            navigator.share({ 
                title: 'Awa Ka Sugu - Vidéos', 
                text: 'Découvrez cette vidéo sur Awa Ka Sugu !',
                url: url 
            });
        } else {
            navigator.clipboard.writeText(url).then(() => {
                alert('Lien copié dans le presse-papiers !');
            }).catch(() => {
                alert('Copiez le lien : ' + url);
            });
        }
    }

    // ============================================
    // INITIALISATION - Démarrer la première vidéo
    // ============================================
    setTimeout(() => {
        const firstVideo = videoPlayers[0];
        if (firstVideo && firstVideo.tagName === 'VIDEO') {
            firstVideo.muted = false;
            firstVideo.play().catch(() => {});
            updatePlayIcon(0, true);
            isPlaying = true;
        }
    }, 500);
</script>

<?php require_once '../includes/footer.php'; ?>