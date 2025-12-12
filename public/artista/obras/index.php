<?php
require_once __DIR__ . '/../../../app/config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. SEGURIDAD
if (!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 3) {
    header("Location: " . BASE_URL . "public/login.php");
    exit;
}

$id_artista = $_SESSION['ID_USER']; 
$url_actual = $_SERVER['PHP_SELF'];
$msg = ""; $msgType = "";

if (isset($_SESSION['temp_msg'])) {
    $msg = $_SESSION['temp_msg'];
    $msgType = $_SESSION['temp_msg_type'];
    unset($_SESSION['temp_msg']); unset($_SESSION['temp_msg_type']);
}

// ---------------------------------------------------------
// LÓGICA PHP (CREAR, EDITAR, ELIMINAR)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $accion = $_POST['action'];
    $permitidos = ['jpg', 'jpeg', 'png', 'webp'];

    try {
        // --- CREAR (INSERT) ---
        if ($accion == 'create') {
            
            $imgName = 'default.png';
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
                $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $permitidos)) throw new Exception("Formato de portada inválido.");
                $imgName = uniqid('cover_') . '.' . $ext; 
                move_uploaded_file($_FILES['imagen']['tmp_name'], __DIR__ . '/../../../public/img/artworks/' . $imgName);
            }

            $sql = "INSERT INTO ARTWORKS (ID_ARTIST, ID_TECHNIQUE, TITLE, DESCRIPTION, CREATION_YEAR, PRICE, DIMENSIONS, FOR_SALE, IMAGE_COVER, IS_APPROVED) 
                    VALUES (:artist, :tech, :title, :desc, :year, :price, :dim, :sale, :img, 0)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':artist' => $id_artista,
                ':tech'   => $_POST['tecnica'],
                ':title'  => trim($_POST['titulo']),
                ':desc'   => $_POST['descripcion'],
                ':year'   => $_POST['year'],
                ':price'  => $_POST['precio'],
                ':dim'    => $_POST['dimensiones'],
                ':sale'   => isset($_POST['en_venta']) ? 1 : 0,
                ':img'    => $imgName
            ]);
            
            $id_obra = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO ARTWORKS_has_CATEGORIES (ID_ARTWORK, ID_CATEGORY) VALUES (?, ?)")
                ->execute([$id_obra, $_POST['categoria']]);

            // Galería
            if (isset($_FILES['galeria'])) {
                $total = count($_FILES['galeria']['name']);
                $stmt_gal = $pdo->prepare("INSERT INTO ARTWORK_IMAGES (ID_ARTWORK, IMAGE_URL, IS_MAIN) VALUES (?, ?, 0)");
                for ($i = 0; $i < $total; $i++) {
                    if ($_FILES['galeria']['error'][$i] == 0) {
                        $ext_g = strtolower(pathinfo($_FILES['galeria']['name'][$i], PATHINFO_EXTENSION));
                        if (in_array($ext_g, $permitidos)) {
                            $name_g = uniqid('gallery_') . '_' . $i . '.' . $ext_g;
                            move_uploaded_file($_FILES['galeria']['tmp_name'][$i], __DIR__ . '/../../../public/img/artworks/' . $name_g);
                            $stmt_gal->execute([$id_obra, $name_g]);
                        }
                    }
                }
            }
            $_SESSION['temp_msg'] = "Obra creada exitosamente.";
            $_SESSION['temp_msg_type'] = "success";

        // --- ACTUALIZAR (UPDATE) ---
        } elseif ($accion == 'update') {
            $id_obra = $_POST['id'];

            // 1. Actualizar datos básicos
            $sql = "UPDATE ARTWORKS SET 
                    ID_TECHNIQUE = :tech,
                    TITLE = :title,
                    DESCRIPTION = :desc,
                    CREATION_YEAR = :year,
                    PRICE = :price,
                    DIMENSIONS = :dim,
                    FOR_SALE = :sale
                    WHERE ID_ARTWORK = :id AND ID_ARTIST = :artist";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':tech'   => $_POST['tecnica'],
                ':title'  => trim($_POST['titulo']),
                ':desc'   => $_POST['descripcion'],
                ':year'   => $_POST['year'],
                ':price'  => $_POST['precio'],
                ':dim'    => $_POST['dimensiones'],
                ':sale'   => isset($_POST['en_venta']) ? 1 : 0,
                ':id'     => $id_obra,
                ':artist' => $id_artista
            ]);

            // 2. Actualizar Categoría
            $pdo->prepare("DELETE FROM ARTWORKS_has_CATEGORIES WHERE ID_ARTWORK = ?")->execute([$id_obra]);
            $pdo->prepare("INSERT INTO ARTWORKS_has_CATEGORIES (ID_ARTWORK, ID_CATEGORY) VALUES (?, ?)")
                ->execute([$id_obra, $_POST['categoria']]);

            // 3. Cambiar Portada
            if (isset($_FILES['imagen_edit']) && $_FILES['imagen_edit']['error'] == 0) {
                $ext = strtolower(pathinfo($_FILES['imagen_edit']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $permitidos)) {
                    $newCover = uniqid('cover_') . '.' . $ext;
                    move_uploaded_file($_FILES['imagen_edit']['tmp_name'], __DIR__ . '/../../../public/img/artworks/' . $newCover);
                    $pdo->prepare("UPDATE ARTWORKS SET IMAGE_COVER = ? WHERE ID_ARTWORK = ?")->execute([$newCover, $id_obra]);
                }
            }

            // 4. Agregar a Galería
            if (isset($_FILES['galeria_edit'])) {
                $total = count($_FILES['galeria_edit']['name']);
                $stmt_gal = $pdo->prepare("INSERT INTO ARTWORK_IMAGES (ID_ARTWORK, IMAGE_URL, IS_MAIN) VALUES (?, ?, 0)");
                
                for ($i = 0; $i < $total; $i++) {
                    if ($_FILES['galeria_edit']['error'][$i] == 0) {
                        $ext_g = strtolower(pathinfo($_FILES['galeria_edit']['name'][$i], PATHINFO_EXTENSION));
                        if (in_array($ext_g, $permitidos)) {
                            $name_g = uniqid('gallery_') . '_' . $i . '.' . $ext_g;
                            move_uploaded_file($_FILES['galeria_edit']['tmp_name'][$i], __DIR__ . '/../../../public/img/artworks/' . $name_g);
                            $stmt_gal->execute([$id_obra, $name_g]);
                        }
                    }
                }
            }

            $_SESSION['temp_msg'] = "Obra actualizada correctamente.";
            $_SESSION['temp_msg_type'] = "success";

        // --- ELIMINAR ---
        } elseif ($accion == 'delete') {
            $id = $_POST['id'];
            $check = $pdo->prepare("SELECT ID_ARTWORK FROM ARTWORKS WHERE ID_ARTWORK = ? AND ID_ARTIST = ?");
            $check->execute([$id, $id_artista]);
            
            if($check->rowCount() > 0) {
                $pdo->prepare("DELETE FROM ARTWORKS_has_CATEGORIES WHERE ID_ARTWORK = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM ARTWORK_IMAGES WHERE ID_ARTWORK = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM ARTWORKS_has_EXHIBITIONS WHERE ID_ARTWORK = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM ARTWORKS WHERE ID_ARTWORK = ?")->execute([$id]);
                $_SESSION['temp_msg'] = "Obra eliminada.";
                $_SESSION['temp_msg_type'] = "success";
            }
        }
        header("Location: " . $url_actual);
        exit;

    } catch (Exception $e) {
        $msg = "Error: " . $e->getMessage();
        $msgType = "error";
    }
}

// CONSULTAS
$sql_mis_obras = "SELECT A.*, T.NAME as NOM_TECNICA, AC.ID_CATEGORY 
                  FROM ARTWORKS A 
                  LEFT JOIN TECHNIQUE T ON A.ID_TECHNIQUE = T.ID_TECHNIQUE
                  LEFT JOIN ARTWORKS_has_CATEGORIES AC ON A.ID_ARTWORK = AC.ID_ARTWORK
                  WHERE A.ID_ARTIST = :id 
                  GROUP BY A.ID_ARTWORK 
                  ORDER BY A.ID_ARTWORK DESC";

$stmt = $pdo->prepare($sql_mis_obras);
$stmt->execute([':id' => $id_artista]);
$mis_obras = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tecnicas = $pdo->query("SELECT * FROM TECHNIQUE ORDER BY NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $pdo->query("SELECT * FROM CATEGORIES ORDER BY NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

// Preparar statement para traer galería en el loop
$stmt_gallery_fetch = $pdo->prepare("SELECT IMAGE_URL FROM ARTWORK_IMAGES WHERE ID_ARTWORK = ? ORDER BY ID_IMAGE ASC");

require_once __DIR__ . '/../../../app/views/layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-palette2"></i> Mi Portafolio</h2>
        <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg"></i> Subir Nueva Obra
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark text-white">
                    <tr>
                        <th class="ps-4">Portada</th>
                        <th>Título</th>
                        <th>Precio</th>
                        <th>Estado</th>
                        <th class="text-end pe-4" style="min-width: 150px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($mis_obras)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No tienes obras registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach($mis_obras as $obra): 
                            // Fetch galería para este item
                            $stmt_gallery_fetch->execute([$obra['ID_ARTWORK']]);
                            $gallery_images = $stmt_gallery_fetch->fetchAll(PDO::FETCH_COLUMN);
                            $gallery_json = htmlspecialchars(json_encode($gallery_images), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td class="ps-4">
                                <img src="<?= BASE_URL ?>public/img/artworks/<?= $obra['IMAGE_COVER'] ?>" class="rounded border shadow-sm" width="60" height="60" style="object-fit: cover;">
                            </td>
                            <td>
                                <div class="fw-bold"><?= $obra['TITLE'] ?></div>
                                <span class="text-muted small"><?= $obra['NOM_TECNICA'] ?></span>
                            </td>
                            <td class="fw-bold text-success">$<?= number_format($obra['PRICE'], 2) ?></td>
                            <td>
                                <?php if($obra['IS_APPROVED']): ?>
                                    <span class="badge bg-success">Publicada</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Revisión</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary me-1" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewModal"
                                        data-title="<?= $obra['TITLE'] ?>"
                                        data-cover="<?= $obra['IMAGE_COVER'] ?>"
                                        data-gallery='<?= $gallery_json ?>'>
                                    <i class="bi bi-eye"></i>
                                </button>

                                <button class="btn btn-sm btn-outline-warning me-1" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editModal"
                                        data-id="<?= $obra['ID_ARTWORK'] ?>"
                                        data-title="<?= $obra['TITLE'] ?>"
                                        data-year="<?= $obra['CREATION_YEAR'] ?>"
                                        data-tech="<?= $obra['ID_TECHNIQUE'] ?>"
                                        data-cat="<?= $obra['ID_CATEGORY'] ?>"
                                        data-price="<?= $obra['PRICE'] ?>"
                                        data-dim="<?= $obra['DIMENSIONS'] ?>"
                                        data-desc="<?= $obra['DESCRIPTION'] ?>"
                                        data-sale="<?= $obra['FOR_SALE'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <form method="POST" class="d-inline" onsubmit="return confirmDelete(event, this)">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $obra['ID_ARTWORK'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Subir Nueva Obra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="fw-bold">Portada Principal:</label>
                            <input type="file" name="imagen" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Galería (Extras):</label>
                            <input type="file" name="galeria[]" class="form-control" multiple>
                        </div>
                        <div class="col-md-8">
                            <label class="fw-bold">Título:</label>
                            <input type="text" name="titulo" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold">Año:</label>
                            <input type="number" name="year" class="form-control" required value="<?= date('Y') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Técnica:</label>
                            <select name="tecnica" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($tecnicas as $t): ?><option value="<?= $t['ID_TECHNIQUE'] ?>"><?= $t['NAME'] ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Categoría:</label>
                            <select name="categoria" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($categorias as $c): ?><option value="<?= $c['ID_CATEGORY'] ?>"><?= $c['NAME'] ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Precio ($):</label>
                            <input type="number" name="precio" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Dimensiones:</label>
                            <input type="text" name="dimensiones" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="fw-bold">Descripción:</label>
                            <textarea name="descripcion" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="en_venta" id="checkVenta" checked>
                                <label class="form-check-label" for="checkVenta">Disponible para venta</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark px-4">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">Editar Obra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="fw-bold">Cambiar Portada (Opcional):</label>
                            <input type="file" name="imagen_edit" class="form-control">
                            <small class="text-muted">Si seleccionas una, reemplazará la actual.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Agregar más fotos a galería:</label>
                            <input type="file" name="galeria_edit[]" class="form-control" multiple>
                            <small class="text-muted">Se añadirán a las existentes.</small>
                        </div>

                        <div class="col-md-8">
                            <label class="fw-bold">Título:</label>
                            <input type="text" name="titulo" id="edit_titulo" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold">Año:</label>
                            <input type="number" name="year" id="edit_year" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Técnica:</label>
                            <select name="tecnica" id="edit_tecnica" class="form-select" required>
                                <?php foreach($tecnicas as $t): ?><option value="<?= $t['ID_TECHNIQUE'] ?>"><?= $t['NAME'] ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Categoría:</label>
                            <select name="categoria" id="edit_categoria" class="form-select" required>
                                <?php foreach($categorias as $c): ?><option value="<?= $c['ID_CATEGORY'] ?>"><?= $c['NAME'] ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Precio ($):</label>
                            <input type="number" name="precio" id="edit_precio" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Dimensiones:</label>
                            <input type="text" name="dimensiones" id="edit_dim" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="fw-bold">Descripción:</label>
                            <textarea name="descripcion" id="edit_desc" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="en_venta" id="edit_venta">
                                <label class="form-check-label" for="edit_venta">Disponible para venta</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold px-4">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="viewModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="carouselArtwork" class="carousel slide bg-dark" data-bs-ride="carousel">
                    <div class="carousel-indicators" id="carouselIndicators"></div>
                    <div class="carousel-inner" id="carouselInner" style="max-height: 500px;">
                        </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselArtwork" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carouselArtwork" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Script para llenar el modal de EDICIÓN
    var editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        editModal.querySelector('#edit_id').value = btn.getAttribute('data-id');
        editModal.querySelector('#edit_titulo').value = btn.getAttribute('data-title');
        editModal.querySelector('#edit_year').value = btn.getAttribute('data-year');
        editModal.querySelector('#edit_tecnica').value = btn.getAttribute('data-tech');
        editModal.querySelector('#edit_categoria').value = btn.getAttribute('data-cat'); 
        editModal.querySelector('#edit_precio').value = btn.getAttribute('data-price');
        editModal.querySelector('#edit_dim').value = btn.getAttribute('data-dim');
        editModal.querySelector('#edit_desc').value = btn.getAttribute('data-desc');
        editModal.querySelector('#edit_venta').checked = btn.getAttribute('data-sale') == 1;
    });

    // Script para llenar el modal de VER DETALLES (Carrusel)
    var viewModal = document.getElementById('viewModal');
    viewModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        var title = btn.getAttribute('data-title');
        var coverBtn = btn.getAttribute('data-cover');
        var galleryData = JSON.parse(btn.getAttribute('data-gallery'));
        
        viewModal.querySelector('#viewModalTitle').textContent = title;
        
        var indicators = document.getElementById('carouselIndicators');
        var inner = document.getElementById('carouselInner');
        indicators.innerHTML = '';
        inner.innerHTML = '';

        // Combinar portada y galería en un solo array
        var allImages = [];
        if(coverBtn) allImages.push(coverBtn);
        allImages = allImages.concat(galleryData);

        if(allImages.length === 0) {
            inner.innerHTML = '<div class="carousel-item active p-5 text-center text-white">Sin imágenes disponibles.</div>';
            return;
        }

        allImages.forEach(function(imgName, index) {
            var isActive = index === 0 ? 'active' : '';
            var imgPath = '<?= BASE_URL ?>public/img/artworks/' + imgName;
            
            // Crear indicador
            indicators.innerHTML += `<button type="button" data-bs-target="#carouselArtwork" data-bs-slide-to="${index}" class="${isActive}" aria-current="${isActive === 'active'}"></button>`;
            
            // Crear slide
            inner.innerHTML += `
                <div class="carousel-item ${isActive} h-100">
                    <div class="d-flex justify-content-center align-items-center h-100" style="min-height: 400px; background: #212529;">
                        <img src="${imgPath}" class="d-block" style="max-height: 500px; max-width: 100%; object-fit: contain;" alt="${title}">
                    </div>
                </div>
            `;
        });
    });

    // Script de SweetAlert para eliminar
    function confirmDelete(e, form) {
        e.preventDefault();
        Swal.fire({
            title: '¿Eliminar?',
            text: "Se borrará permanentemente.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, borrar'
        }).then((result) => { if(result.isConfirmed) form.submit(); });
    }

    // Mensajes Flash
    <?php if($msg != ""): ?>
        Swal.fire({
            icon: '<?= $msgType == "error" ? "error" : "success" ?>',
            title: '<?= $msg ?>',
            confirmButtonColor: '#212529'
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../../app/views/layout/footer.php'; ?>