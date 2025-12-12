<?php
require_once __DIR__ . '/../app/config/config.php';

// 1. SEGURIDAD
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['ID_USER'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit;
}

// 2. OBTENER ID
$id_obra = isset($_GET['id']) ? $_GET['id'] : null;
if (!$id_obra) { echo "Obra no especificada."; exit; }

// 3. CONSULTA PRINCIPAL (Detalles de la obra)
$stmt = $pdo->prepare("
    SELECT a.*, ar.FULL_NAME as ARTIST_NAME, t.NAME as TECH_NAME 
    FROM ARTWORKS a
    JOIN ARTISTS ar ON a.ID_ARTIST = ar.ID_ARTIST
    LEFT JOIN TECHNIQUE t ON a.ID_TECHNIQUE = t.ID_TECHNIQUE
    WHERE a.ID_ARTWORK = ?
");
$stmt->execute([$id_obra]);
$obra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$obra) { echo "Obra no encontrada."; exit; }

// 4. CONSULTA ADICIONAL (Galería de imágenes extra)
$stmtImg = $pdo->prepare("SELECT * FROM ARTWORK_IMAGES WHERE ID_ARTWORK = ?");
$stmtImg->execute([$id_obra]);
$galeria = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

// Función auxiliar para rutas (Local)
function rutaImagen($img) {
    if (empty($img)) return BASE_URL . 'public/img/placeholder.jpg';
    if (filter_var($img, FILTER_VALIDATE_URL)) return $img;
    return BASE_URL . 'public/img/artworks/' . $img;
}

require_once __DIR__ . '/../app/views/layout/header.php';
?>

<style>
    .main-img {
        width: 100%;
        height: 500px;
        object-fit: contain; /* Muestra toda la imagen sin recortar */
        background-color: #f8f9fa;
        border-radius: 8px;
    }
    .thumb-img {
        width: 100%;
        height: 100px;
        object-fit: cover;
        cursor: pointer;
        border-radius: 6px;
        border: 2px solid transparent;
        transition: all 0.2s;
    }
    .thumb-img:hover {
        border-color: #333;
        opacity: 0.8;
    }
</style>

<div class="container my-5">
    
    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm mb-4">
        <i class="bi bi-arrow-left"></i> Volver
    </a>

    <div class="row">
        
        <div class="col-md-7 mb-4">
            <div class="mb-3 text-center">
                <img id="imgPrincipal" src="<?= rutaImagen($obra['IMAGE_COVER']) ?>" class="main-img shadow-sm" alt="Portada">
            </div>

            <?php if (!empty($galeria)): ?>
                <div class="row g-2">
                    <div class="col-3 col-sm-2">
                        <img src="<?= rutaImagen($obra['IMAGE_COVER']) ?>" 
                             class="thumb-img" 
                             onclick="cambiarImagen(this.src)">
                    </div>

                    <?php foreach ($galeria as $foto): ?>
                        <div class="col-3 col-sm-2">
                            <img src="<?= rutaImagen($foto['IMAGE_URL']) ?>" 
                                 class="thumb-img" 
                                 onclick="cambiarImagen(this.src)">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-5">
            <h6 class="text-muted text-uppercase mb-2">
                <?= htmlspecialchars($obra['TECH_NAME'] ?? 'Obra de Arte') ?>
            </h6>
            
            <h1 class="fw-bold display-6 mb-2"><?= htmlspecialchars($obra['TITLE']) ?></h1>
            
            <p class="fs-5 mb-4">
                Por: <a href="detalle_artista.php?id=<?= $obra['ID_ARTIST'] ?>" class="text-decoration-none fw-bold text-dark">
                    <?= htmlspecialchars($obra['ARTIST_NAME']) ?>
                </a>
            </p>

            <h2 class="text-success fw-bold mb-4">$<?= number_format($obra['PRICE'], 2) ?></h2>

            <div class="card bg-light border-0 mb-4">
                <div class="card-body">
                    <p class="mb-1"><strong>Dimensiones:</strong> <?= htmlspecialchars($obra['DIMENSIONS']) ?></p>
                    <p class="mb-1"><strong>Año de creación:</strong> <?= htmlspecialchars($obra['CREATION_YEAR']) ?></p>
                    <?php if(!$obra['FOR_SALE']): ?>
                        <p class="text-danger fw-bold mb-0"><i class="bi bi-x-circle"></i> No disponible para venta</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-4">
                <h5 class="fw-bold">Descripción</h5>
                <p class="text-muted" style="line-height: 1.8;">
                    <?= nl2br(htmlspecialchars($obra['DESCRIPTION'])) ?>
                </p>
            </div>

            <?php if ($obra['FOR_SALE']): ?>
                <div class="d-grid gap-2">
                    <button class="btn btn-dark btn-lg py-3">
                        <i class="bi bi-cart-plus me-2"></i> Añadir al Carrito
                    </button>
                    <button class="btn btn-outline-danger btn-lg">
                        <i class="bi bi-heart me-2"></i> Guardar en Deseos
                    </button>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    function cambiarImagen(src) {
        document.getElementById('imgPrincipal').src = src;
    }
</script>

<?php require_once __DIR__ . '/../app/views/layout/footer.php'; ?>