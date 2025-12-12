<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../layout/header.php';

try {
    //OBRAS DESTACADAS POR VISTAS
    $queryTopViews = $pdo -> query ("
        SELECT a.*, COUNT(v.ID_VIEW) AS total_vistas
        FROM ARTWORKS a
        LEFT JOIN ARTWORK_VIEWS v ON a.ID_ARTWORK = v.ID_ARTWORK
        GROUP BY a.ID_ARTWORK
        ORDER BY total_vistas DESC
        LIMIT 6
        ");
    $obrasDestacadas = $queryTopViews -> fetchAll(PDO::FETCH_ASSOC);


    //CATEGORÍAS DESTACAS
    $queryCategorias = $pdo -> query("
        SELECT c.ID_CATEGORY, c.NAME, COUNT(v.ID_VIEW) AS vistas_categoria
        FROM CATEGORIES c
        JOIN ARTWORKS_has_CATEGORIES ahc ON c.ID_CATEGORY = ahc.ID_CATEGORY
        JOIN ARTWORK_VIEWS v ON ahc.ID_ARTWORK = v.ID_ARTWORK
        GROUP BY c.ID_CATEGORY
        ORDER BY vistas_categoria DESC
        LIMIT 6
        ");
    $categoriasDestacadas = $queryCategorias -> fetchAll(PDO::FETCH_ASSOC);

    //ARTISTAS DESTACADOS
    $queryArtistas = $pdo -> query("
        SELECT ar.ID_ARTIST, ar.FULL_NAME, ar.PROFILE_IMAGE, COUNT(v.ID_VIEW) AS total_vistas
        FROM ARTISTS ar
        JOIN ARTWORKS a ON ar.ID_ARTIST = a.ID_ARTIST
        JOIN ARTWORK_VIEWS v ON a.ID_ARTWORK = v.ID_ARTWORK
        GROUP BY ar.ID_ARTIST
        ORDER BY total_vistas DESC
        LIMIT 6
        ");
    $artistasDestacados = $queryArtistas -> fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "<h3> Error cargando datos: ". $e->getMessage() . "</h3>";
}

// Inicializar valores
$obrasDestacadas = $obrasDestacadas ?? [];
$categoriasDestacadas = $categoriasDestacadas ?? [];
$artistasDestacados = $artistasDestacados ?? [];

// Mantener los 6 elementos
$obrasDestacadas = array_pad($obrasDestacadas, 6, ["placeholder" => true]);
$categoriasDestacadas = array_pad($categoriasDestacadas, 6, ["placeholder" => true]);
$artistasDestacados = array_pad($artistasDestacados, 6, ["placeholder" => true]);
?>


<?php
    //Función para los PLACEHOLDERS
    function generarPlaceholders($cantidad, $tipo) {
        $items = [];

        for($i = 0; $i < $cantidad; $i++) {
            $items[] = [
                "placeholder" => true,
                "tipo" => $tipo
            ];
        }

        return $items;
    }

    function dividirSlides($array, $porSlide = 3) {
        return array_chunk($array, $porSlide);
    }
?>





<!--Continuación del header de php-->
<div class="main-container">

    <h2 class="section-title">Bienvenido a nuestra Galería de Arte</h2>
    <p class="home-description">
        Explora las obras más destacadas, los artistas más influyentes y las categorías más vistas.
        Todo desde un entorno moderno y elegante diseñado para inspirarte.
    </p>


    <h2 class="section-title">Obras destacadas</h2>

    <div id="carruselObras" class="carousel slide multi-item" data-bs-ride="carousel">
        <div class="carousel-inner">

            <?php $slides = dividirSlides($obrasDestacadas, 3); ?>
            <?php foreach ($slides as $i => $grupo): ?>

                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <div class="row justify-content-center"> <?php foreach ($grupo as $obra): ?>
                            <div class="col-md-4">
                                <?php if (isset($obra["placeholder"])): ?>
                                    <div class="item-box">
                                        <i class="bi bi-image placeholder-icon"></i>
                                    </div>
                                <?php else: ?>
                                    <div style="padding: 0 10px;">
                                        <img src="<?= $obra['IMAGE_COVER'] ?>" class="carrusel-img">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

            <?php endforeach; ?>

        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#carruselObras" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>

        <button class="carousel-control-next" type="button" data-bs-target="#carruselObras" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>


    <h2 class="section-title">Categorías destacadas</h2>

    <div id="carruselCategorias" class="carousel slide multi-item" data-bs-ride="carousel">
        <div class="carousel-inner">

            <?php $slides = dividirSlides($categoriasDestacadas, 3); ?>
            <?php foreach ($slides as $i => $grupo): ?>

                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <div class="row justify-content-center">

                        <?php foreach ($grupo as $cat): ?>
                            <div class="col-md-4">
                                <div class="item-box">
                                    <?php if (isset($cat["placeholder"])): ?>
                                        <i class="bi bi-card-list placeholder-icon"></i>
                                    <?php else: ?>
                                        <h3 style="font-weight: bold;"><?= $cat['NAME'] ?></h3>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

            <?php endforeach; ?>

        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#carruselCategorias" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carruselCategorias" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>


    <h2 class="section-title">Artistas destacados</h2>

    <div id="carruselArtistas" class="carousel slide multi-item" data-bs-ride="carousel">
        <div class="carousel-inner">

            <?php $slides = dividirSlides($artistasDestacados, 3); ?>
            <?php foreach ($slides as $i => $grupo): ?>

                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <div class="row justify-content-center">

                        <?php foreach ($grupo as $artist): ?>
                            <div class="col-md-4">
                                <div class="item-box">
                                    <?php if (isset($artist["placeholder"])): ?>
                                        <i class="bi bi-person placeholder-icon"></i>
                                    <?php else: ?>
                                        <img src="<?= $artist['PROFILE_IMAGE'] ?>" class="artist-img">
                                        <h4 style="margin-top: 15px;"><?= $artist['FULL_NAME'] ?></h4>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

            <?php endforeach; ?>

        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#carruselArtistas" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carruselArtistas" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>

    </div>

</div> <div style="margin-bottom: 100px;"></div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>