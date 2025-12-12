<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../layout/header.php';

try {
    // 1. OBRAS DESTACADAS (CORREGIDO: LEFT JOIN para que salgan aunque no tengan vistas)
    $queryTopViews = $pdo->query("
        SELECT a.ID_ARTWORK, a.TITLE, a.PRICE, a.IMAGE_COVER, COUNT(v.ID_VIEW) AS total_vistas
        FROM ARTWORKS a
        LEFT JOIN ARTWORK_VIEWS v ON a.ID_ARTWORK = v.ID_ARTWORK
        WHERE a.IS_APPROVED = 1 AND a.FOR_SALE = 1
        GROUP BY a.ID_ARTWORK
        ORDER BY total_vistas DESC
        LIMIT 6
    ");
    $obrasDestacadas = $queryTopViews->fetchAll(PDO::FETCH_ASSOC);

    // 2. CATEGORÍAS DESTACADAS
    $queryCategorias = $pdo->query("
        SELECT c.ID_CATEGORY, c.NAME, COUNT(v.ID_VIEW) AS vistas_categoria
        FROM CATEGORIES c
        JOIN ARTWORKS_has_CATEGORIES ahc ON c.ID_CATEGORY = ahc.ID_CATEGORY
        LEFT JOIN ARTWORK_VIEWS v ON ahc.ID_ARTWORK = v.ID_ARTWORK
        GROUP BY c.ID_CATEGORY
        ORDER BY vistas_categoria DESC
        LIMIT 6
    ");
    $categoriasDestacadas = $queryCategorias->fetchAll(PDO::FETCH_ASSOC);

    // 3. ARTISTAS DESTACADOS (CORREGIDO: LEFT JOIN CRÍTICO)
    // Antes tenías JOIN estricto, por eso si no había vistas, no salía el artista.
    $queryArtistas = $pdo->query("
        SELECT ar.ID_ARTIST, ar.FULL_NAME, ar.PROFILE_IMAGE, COUNT(v.ID_VIEW) AS total_vistas
        FROM ARTISTS ar
        LEFT JOIN ARTWORKS a ON ar.ID_ARTIST = a.ID_ARTIST
        LEFT JOIN ARTWORK_VIEWS v ON a.ID_ARTWORK = v.ID_ARTWORK
        GROUP BY ar.ID_ARTIST
        ORDER BY total_vistas DESC
        LIMIT 6
    ");
    $artistasDestacados = $queryArtistas->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error cargando datos: " . $e->getMessage() . "</div>";
}

// Inicializar y Rellenar arrays
$obrasDestacadas = $obrasDestacadas ?? [];
$categoriasDestacadas = $categoriasDestacadas ?? [];
$artistasDestacados = $artistasDestacados ?? [];

$obrasDestacadas = array_pad($obrasDestacadas, 6, ["placeholder" => true]);
$categoriasDestacadas = array_pad($categoriasDestacadas, 6, ["placeholder" => true]);
$artistasDestacados = array_pad($artistasDestacados, 6, ["placeholder" => true]);

// Funciones Helper
function dividirSlides($array, $porSlide = 3) {
    return array_chunk($array, $porSlide);
}

// FUNCIÓN PARA ARREGLAR RUTAS DE IMÁGENES
function getImgPath($imgName, $folder) {
    if (empty($imgName)) return null;
    // Si ya es una URL completa (http...), la dejamos igual
    if (filter_var($imgName, FILTER_VALIDATE_URL)) return $imgName;
    // Si no, le pegamos la ruta base y la carpeta
    return BASE_URL . 'public/img/' . $folder . '/' . $imgName;
}
?>

<div class="main-container container my-5">

    <div class="text-center mb-5">
        <h2 class="display-5 fw-bold text-uppercase">Bienvenido a nuestra Galería de Arte</h2>
        <p class="lead text-muted mx-auto" style="max-width: 700px;">
            Explora las obras más destacadas, los artistas más influyentes y las categorías más vistas.
        </p>
    </div>

    <h3 class="fw-bold text-uppercase mb-4 text-center">Obras Destacadas</h3>
    <div id="carruselObras" class="carousel slide multi-item mb-5" data-bs-ride="carousel">
        <div class="carousel-inner">
            <?php $slides = dividirSlides($obrasDestacadas, 3); ?>
            <?php foreach ($slides as $i => $grupo): ?>
                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <div class="row justify-content-center">
                        <?php foreach ($grupo as $obra): ?>
                            <div class="col-md-4 mb-3">
                                <?php if (isset($obra["placeholder"])): ?>
                                    <div class="item-box d-flex align-items-center justify-content-center" style="height: 320px;">
                                        <i class="bi bi-image placeholder-icon" style="font-size: 3rem; color: #ccc;"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="item-box p-2" style="border:1px solid #eee; border-radius:10px; background:#fff; height:100%;">
                                        <a href="<?= BASE_URL ?>public/detalle_obra.php?id=<?= $obra['ID_ARTWORK'] ?>" class="text-decoration-none text-dark">
                                            
                                            <?php 
                                                // Intentamos armar la ruta correcta. Asumimos carpeta 'artworks'
                                                $imgUrl = getImgPath($obra['IMAGE_COVER'], 'artworks');
                                                // Si sigue vacía, ponemos placeholder
                                                if(!$imgUrl) $imgUrl = BASE_URL . 'public/img/placeholder_art.jpg'; 
                                            ?>
                                            <img src="<?= htmlspecialchars($imgUrl) ?>" 
                                                 class="carrusel-img mb-3" 
                                                 style="width:100%; height:250px; object-fit:cover; border-radius:8px;" 
                                                 alt="Obra">
                                            
                                            <h5 class="fw-bold mb-1 text-truncate"><?= htmlspecialchars($obra['TITLE']) ?></h5>
                                            <p class="text-success fw-bold fs-5 mb-0">$<?= number_format($obra['PRICE'], 2) ?></p>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <button class="carousel-control-prev" type="button" data-bs-target="#carruselObras" data-bs-slide="prev" style="width: 5%;">
            <span class="carousel-control-prev-icon bg-dark rounded-circle p-3"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carruselObras" data-bs-slide="next" style="width: 5%;">
            <span class="carousel-control-next-icon bg-dark rounded-circle p-3"></span>
        </button>
    </div>


    <h3 class="fw-bold text-uppercase mb-4 text-center">Categorías Destacadas</h3>
    <div id="carruselCategorias" class="carousel slide multi-item mb-5" data-bs-ride="carousel">
        <div class="carousel-inner">
            <?php $slides = dividirSlides($categoriasDestacadas, 3); ?>
            <?php foreach ($slides as $i => $grupo): ?>
                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <div class="row justify-content-center">
                        <?php foreach ($grupo as $cat): ?>
                            <div class="col-md-4 mb-3">
                                <div class="item-box d-flex align-items-center justify-content-center bg-light" style="height: 150px; border-radius:10px;">
                                    <?php if (isset($cat["placeholder"])): ?>
                                        <i class="bi bi-card-list placeholder-icon" style="font-size: 2rem; color: #ccc;"></i>
                                    <?php else: ?>
                                        <div class="text-center">
                                            <i class="bi bi-tag-fill fs-1 text-secondary mb-2"></i>
                                            <h4 class="fw-bold m-0"><?= htmlspecialchars($cat['NAME']) ?></h4>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#carruselCategorias" data-bs-slide="prev" style="width: 5%;">
            <span class="carousel-control-prev-icon bg-dark rounded-circle p-3"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carruselCategorias" data-bs-slide="next" style="width: 5%;">
            <span class="carousel-control-next-icon bg-dark rounded-circle p-3"></span>
        </button>
    </div>


    <h3 class="fw-bold text-uppercase mb-4 text-center">Artistas Destacados</h3>
    <div id="carruselArtistas" class="carousel slide multi-item mb-5" data-bs-ride="carousel">
        <div class="carousel-inner">
            <?php $slides = dividirSlides($artistasDestacados, 3); ?>
            <?php foreach ($slides as $i => $grupo): ?>
                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <div class="row justify-content-center">
                        <?php foreach ($grupo as $artist): ?>
                            <div class="col-md-4 mb-3">
                                <div class="item-box py-4 text-center" style="border:1px solid #eee; border-radius:10px; background:#fff;">
                                    <?php if (isset($artist["placeholder"])): ?>
                                        <i class="bi bi-person placeholder-icon" style="font-size: 3rem; color: #ccc;"></i>
                                    <?php else: ?>
                                        <?php 
                                            // Asumimos carpeta 'profiles'
                                            $imgArt = getImgPath($artist['PROFILE_IMAGE'], 'profiles');
                                            if(!$imgArt) $imgArt = BASE_URL . 'public/img/default_user.png'; 
                                        ?>
                                        <img src="<?= htmlspecialchars($imgArt) ?>" 
                                             class="artist-img shadow-sm" 
                                             style="width:100px; height:100px; object-fit:cover; border-radius:50%; margin-bottom:10px;">
                                        
                                        <h5 class="fw-bold mt-3"><?= htmlspecialchars($artist['FULL_NAME']) ?></h5>
                                        <a href="<?= BASE_URL ?>public/detalle_artista.php?id=<?= $artist['ID_ARTIST'] ?>" class="btn btn-sm btn-outline-dark mt-2">Ver Perfil</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#carruselArtistas" data-bs-slide="prev" style="width: 5%;">
            <span class="carousel-control-prev-icon bg-dark rounded-circle p-3"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carruselArtistas" data-bs-slide="next" style="width: 5%;">
            <span class="carousel-control-next-icon bg-dark rounded-circle p-3"></span>
        </button>
    </div>

</div> 
<div style="margin-bottom: 100px;"></div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>