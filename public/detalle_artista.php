<?php
require_once __DIR__ . '/../app/config/config.php';

// 1. SEGURIDAD: SI NO ESTÁ LOGUEADO -> REDIRIGIR AL LOGIN
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['ID_USER'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit;
}

// 2. RECIBIR ID DEL ARTISTA
$id_artista = isset($_GET['id']) ? $_GET['id'] : null;

if (!$id_artista) {
    echo "<div class='container my-5 alert alert-danger'>Artista no especificado.</div>";
    require_once __DIR__ . '/../app/views/layout/footer.php';
    exit;
}

// 3. CONSULTA DATOS DEL ARTISTA (Incluyendo Nacionalidad)
$stmt = $pdo->prepare("
    SELECT a.*, n.NAME as COUNTRY 
    FROM ARTISTS a
    LEFT JOIN NATIONALITY n ON a.ID_NATIONALITY = n.ID_NATIONALITY
    WHERE a.ID_ARTIST = ?
");
$stmt->execute([$id_artista]);
$artista = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artista) {
    echo "<div class='container my-5 alert alert-danger'>Artista no encontrado.</div>";
    require_once __DIR__ . '/../app/views/layout/footer.php';
    exit;
}

// 4. CONSULTA OBRAS DEL ARTISTA (Solo Aprobadas)
$stmtObras = $pdo->prepare("
    SELECT * FROM ARTWORKS 
    WHERE ID_ARTIST = ? AND IS_APPROVED = 1 
    ORDER BY ID_ARTWORK DESC
");
$stmtObras->execute([$id_artista]);
$obras = $stmtObras->fetchAll(PDO::FETCH_ASSOC);

// Función auxiliar para imágenes
function rutaPerfil($img) {
    if (empty($img)) return BASE_URL . 'public/img/default_user.png';
    if (filter_var($img, FILTER_VALIDATE_URL)) return $img;
    return BASE_URL . 'public/img/profiles/' . $img;
}

function rutaObra($img) {
    if (empty($img)) return BASE_URL . 'public/img/placeholder_art.jpg';
    if (filter_var($img, FILTER_VALIDATE_URL)) return $img;
    return BASE_URL . 'public/img/artworks/' . $img;
}

require_once __DIR__ . '/../app/views/layout/header.php';
?>

<div class="container my-5">
    
    <a href="<?= BASE_URL ?>public/index.php" class="btn btn-outline-secondary btn-sm mb-4">
        <i class="bi bi-arrow-left"></i> Volver al Inicio
    </a>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-5 text-center bg-light rounded">
            
            <img src="<?= rutaPerfil($artista['PROFILE_IMAGE']) ?>" 
                 class="rounded-circle shadow mb-3" 
                 alt="Foto Perfil"
                 style="width: 180px; height: 180px; object-fit: cover; border: 5px solid white;">
            
            <h1 class="fw-bold mb-1"><?= htmlspecialchars($artista['FULL_NAME']) ?></h1>
            
            <?php if(!empty($artista['COUNTRY'])): ?>
                <p class="text-uppercase text-primary fw-bold small mb-3">
                    <i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($artista['COUNTRY']) ?>
                </p>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <p class="text-muted lead" style="font-size: 1.1rem;">
                        <?= !empty($artista['BIOGRAPHY']) ? nl2br(htmlspecialchars($artista['BIOGRAPHY'])) : 'Este artista aún no ha agregado una biografía.' ?>
                    </p>
                </div>
            </div>

            <div class="mt-4 pt-4 border-top w-50 mx-auto d-flex justify-content-around">
                <div>
                    <h3 class="fw-bold mb-0"><?= count($obras) ?></h3>
                    <small class="text-muted">Obras</small>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?= date('Y', strtotime($artista['DATE_CREATED'])) ?></h3>
                    <small class="text-muted">Miembro desde</small>
                </div>
            </div>

        </div>
    </div>

    <h3 class="fw-bold mb-4 border-bottom pb-2">Portafolio de Obras</h3>

    <?php if (empty($obras)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-palette display-4 mb-3 d-block"></i>
            Este artista aún no tiene obras publicadas.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($obras as $obra): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 card-hover-effect">
                        
                        <a href="<?= BASE_URL ?>public/detalle_obra.php?id=<?= $obra['ID_ARTWORK'] ?>" class="text-decoration-none text-dark">
                            <div style="overflow: hidden; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                                <img src="<?= rutaObra($obra['IMAGE_COVER']) ?>" 
                                     class="card-img-top zoom-img" 
                                     alt="Obra" 
                                     style="height: 300px; object-fit: cover; transition: transform 0.3s;">
                            </div>
                            
                            <div class="card-body">
                                <h5 class="card-title fw-bold mb-1"><?= htmlspecialchars($obra['TITLE']) ?></h5>
                                <p class="text-muted small mb-2"><?= htmlspecialchars($obra['DIMENSIONS']) ?> • <?= htmlspecialchars($obra['CREATION_YEAR']) ?></p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <span class="text-success fw-bold fs-5">$<?= number_format($obra['PRICE'], 2) ?></span>
                                    
                                    <?php if($obra['FOR_SALE']): ?>
                                        <span class="badge bg-dark">En Venta</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No Disponible</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<style>
    .card-hover-effect:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        transition: all 0.3s ease;
    }
    .card-hover-effect:hover .zoom-img {
        transform: scale(1.05);
    }
</style>

<?php require_once __DIR__ . '/../app/views/layout/footer.php'; ?>