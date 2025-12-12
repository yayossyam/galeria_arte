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
// LÓGICA PHP
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $accion = $_POST['action'];

    try {
        // A. CREAR (INSERT)
        if ($accion == 'create') {
            $sql = "INSERT INTO EXHIBITIONS (NAME, START_DATE, END_DATE, DESCRIPTION, LOCATION, ID_CREATOR, IS_APPROVED) 
                    VALUES (:nom, :ini, :fin, :desc, :loc, :creator, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nom' => trim($_POST['nombre']), 
                ':ini' => $_POST['fecha_inicio'], 
                ':fin' => $_POST['fecha_fin'],
                ':desc' => trim($_POST['descripcion']), 
                ':loc' => trim($_POST['lugar']), 
                ':creator' => $id_artista
            ]);
            $_SESSION['temp_msg'] = "Exhibición creada. Espera la aprobación del administrador.";
            $_SESSION['temp_msg_type'] = "success";

        // B. EDITAR (UPDATE)
        } elseif ($accion == 'update') {
            $id_exh = $_POST['id'];
            $sql = "UPDATE EXHIBITIONS SET NAME=:nom, START_DATE=:ini, END_DATE=:fin, DESCRIPTION=:desc, LOCATION=:loc 
                    WHERE ID_EXHIBITION=:id AND ID_CREATOR=:creator";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nom' => trim($_POST['nombre']),
                ':ini' => $_POST['fecha_inicio'],
                ':fin' => $_POST['fecha_fin'],
                ':desc' => trim($_POST['descripcion']),
                ':loc' => trim($_POST['lugar']),
                ':id' => $id_exh,
                ':creator' => $id_artista
            ]);
            $_SESSION['temp_msg'] = "Evento actualizado correctamente.";
            $_SESSION['temp_msg_type'] = "success";

        // C. ELIMINAR PROPIO (DELETE)
        } elseif ($accion == 'delete') {
            $id_exh = $_POST['id'];
            $check = $pdo->prepare("SELECT ID_EXHIBITION FROM EXHIBITIONS WHERE ID_EXHIBITION = ? AND ID_CREATOR = ?");
            $check->execute([$id_exh, $id_artista]);

            if ($check->rowCount() > 0) {
                $pdo->prepare("DELETE FROM EXHIBITION_REQUESTS WHERE ID_EXHIBITION = ?")->execute([$id_exh]);
                $pdo->prepare("DELETE FROM ARTWORKS_has_EXHIBITIONS WHERE ID_EXHIBITION = ?")->execute([$id_exh]);
                $pdo->prepare("DELETE FROM EXHIBITIONS WHERE ID_EXHIBITION = ?")->execute([$id_exh]);
                
                $_SESSION['temp_msg'] = "Evento eliminado permanentemente.";
                $_SESSION['temp_msg_type'] = "success";
            }

        // D. UNIRSE (JOIN)
        } elseif ($accion == 'join') {
            $id_exh = $_POST['id_exhibition'];
            $check = $pdo->prepare("SELECT ID_REQUEST FROM EXHIBITION_REQUESTS WHERE ID_EXHIBITION = ? AND ID_ARTIST = ?");
            $check->execute([$id_exh, $id_artista]);

            if ($check->rowCount() == 0) {
                $sql_req = "INSERT INTO EXHIBITION_REQUESTS (ID_EXHIBITION, ID_ARTIST, STATUS, REQUEST_DATE) 
                            VALUES (:exh, :art, 'Pending', NOW())";
                $pdo->prepare($sql_req)->execute([':exh' => $id_exh, ':art' => $id_artista]);
                $_SESSION['temp_msg'] = "Solicitud enviada al organizador.";
                $_SESSION['temp_msg_type'] = "success";
            }

        // E. SALIR / CANCELAR SOLICITUD (LEAVE) -- ¡NUEVO!
        } elseif ($accion == 'leave') {
            $id_exh = $_POST['id_exhibition'];
            // Borramos la solicitud de la tabla
            $stmt = $pdo->prepare("DELETE FROM EXHIBITION_REQUESTS WHERE ID_EXHIBITION = ? AND ID_ARTIST = ?");
            $stmt->execute([$id_exh, $id_artista]);
            
            $_SESSION['temp_msg'] = "Has salido del evento / Cancelado solicitud.";
            $_SESSION['temp_msg_type'] = "warning";
        }

        header("Location: " . $url_actual);
        exit;

    } catch (Exception $e) {
        $msg = "Error: " . $e->getMessage();
        $msgType = "error";
    }
}

// ---------------------------------------------------------
// CONSULTAS
// ---------------------------------------------------------
$sql_mis = "SELECT * FROM EXHIBITIONS WHERE ID_CREATOR = :id ORDER BY START_DATE DESC";
$stmt_mis = $pdo->prepare($sql_mis);
$stmt_mis->execute([':id' => $id_artista]);
$mis_eventos = $stmt_mis->fetchAll(PDO::FETCH_ASSOC);

$sql_explore = "SELECT E.*, R.STATUS as REQUEST_STATUS 
                FROM EXHIBITIONS E
                LEFT JOIN EXHIBITION_REQUESTS R ON E.ID_EXHIBITION = R.ID_EXHIBITION AND R.ID_ARTIST = :id_art
                WHERE E.IS_APPROVED = 1 AND (E.ID_CREATOR != :id_art OR E.ID_CREATOR IS NULL)
                ORDER BY E.START_DATE ASC";
$stmt_exp = $pdo->prepare($sql_explore);
$stmt_exp->execute([':id_art' => $id_artista]);
$explorar = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../../app/views/layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container my-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-calendar-event"></i> Gestión de Exhibiciones</h2>
        <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-lg"></i> Crear Evento
        </button>
    </div>

    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="home-tab" data-bs-toggle="tab" data-bs-target="#mis-eventos" type="button">
                <i class="bi bi-person-workspace me-2"></i>Mis Eventos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="profile-tab" data-bs-toggle="tab" data-bs-target="#explorar" type="button">
                <i class="bi bi-search me-2"></i>Explorar y Unirse
            </button>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        
        <div class="tab-pane fade show active" id="mis-eventos">
            <?php if(empty($mis_eventos)): ?>
                <div class="alert alert-light text-center border py-5">
                    <i class="bi bi-calendar-x display-4 text-muted"></i>
                    <p class="mt-3 text-muted">No has organizado ninguna exhibición aún.</p>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach($mis_eventos as $evt): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                                <span><?= substr($evt['NAME'], 0, 20) ?><?= strlen($evt['NAME'])>20 ? '...' : '' ?></span>
                                <?php if($evt['IS_APPROVED']): ?>
                                    <span class="badge bg-success" style="font-size:0.6rem">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark" style="font-size:0.6rem">En Revisión</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title fw-bold mb-2"><?= $evt['NAME'] ?></h6>
                                <p class="card-text text-muted small mb-2">
                                    <i class="bi bi-geo-alt-fill text-danger"></i> <?= $evt['LOCATION'] ?>
                                </p>
                                <p class="card-text small mb-3">
                                    <strong>Desde:</strong> <?= date('d/m/Y', strtotime($evt['START_DATE'])) ?><br>
                                    <strong>Hasta:</strong> <?= date('d/m/Y', strtotime($evt['END_DATE'])) ?>
                                </p>
                                <p class="card-text small text-secondary">
                                    <?= substr($evt['DESCRIPTION'], 0, 80) ?>...
                                </p>
                            </div>
                            
                            <div class="card-footer bg-white border-0 pb-3 d-flex justify-content-between align-items-center">
                                <?php if($evt['IS_APPROVED']): ?>
                                    <a href="solicitudes.php?id=<?= $evt['ID_EXHIBITION'] ?>" class="btn btn-outline-dark btn-sm" title="Gestionar Participantes">
                                        <i class="bi bi-people"></i> Solicitudes
                                    </a>
                                <?php else: ?>
                                    <small class="text-muted fst-italic">Pendiente...</small>
                                <?php endif; ?>

                                <div>
                                    <button class="btn btn-sm btn-outline-warning me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal"
                                            data-id="<?= $evt['ID_EXHIBITION'] ?>"
                                            data-name="<?= $evt['NAME'] ?>"
                                            data-loc="<?= $evt['LOCATION'] ?>"
                                            data-start="<?= $evt['START_DATE'] ?>"
                                            data-end="<?= $evt['END_DATE'] ?>"
                                            data-desc="<?= $evt['DESCRIPTION'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirmDelete(event, this)">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $evt['ID_EXHIBITION'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="explorar">
            <?php if(empty($explorar)): ?>
                <div class="alert alert-light text-center border py-5">
                    <p class="text-muted">No hay exhibiciones públicas disponibles por ahora.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Exhibición</th>
                                <th>Ubicación</th>
                                <th>Fechas</th>
                                <th class="text-end" style="min-width: 180px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($explorar as $exp): ?>
                            <tr>
                                <td class="fw-bold"><?= $exp['NAME'] ?></td>
                                <td><i class="bi bi-geo-alt text-muted"></i> <?= $exp['LOCATION'] ?></td>
                                <td class="small">
                                    Del <?= date('d M', strtotime($exp['START_DATE'])) ?> <br>
                                    al <?= date('d M', strtotime($exp['END_DATE'])) ?>
                                </td>
                                <td class="text-end">
                                    
                                    <button class="btn btn-sm btn-outline-primary me-2" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#viewModal"
                                            data-name="<?= $exp['NAME'] ?>"
                                            data-desc="<?= $exp['DESCRIPTION'] ?>"
                                            data-loc="<?= $exp['LOCATION'] ?>"
                                            data-dates="Del <?= date('d/m/Y', strtotime($exp['START_DATE'])) ?> al <?= date('d/m/Y', strtotime($exp['END_DATE'])) ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <?php if($exp['REQUEST_STATUS'] == 'Pending'): ?>
                                        <div class="d-inline-block">
                                            <span class="badge bg-warning text-dark me-1">Pendiente</span>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Cancelar solicitud?');">
                                                <input type="hidden" name="action" value="leave">
                                                <input type="hidden" name="id_exhibition" value="<?= $exp['ID_EXHIBITION'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger p-0 px-1" title="Cancelar Solicitud"><i class="bi bi-x"></i></button>
                                            </form>
                                        </div>

                                    <?php elseif($exp['REQUEST_STATUS'] == 'Accepted'): ?>
                                        <div class="d-inline-block">
                                            <span class="badge bg-success me-1">Participando</span>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Salir del evento?');">
                                                <input type="hidden" name="action" value="leave">
                                                <input type="hidden" name="id_exhibition" value="<?= $exp['ID_EXHIBITION'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger py-0 px-2" style="font-size: 0.7rem;">Salir</button>
                                            </form>
                                        </div>

                                    <?php elseif($exp['REQUEST_STATUS'] == 'Rejected'): ?>
                                        <span class="badge bg-danger">Rechazado</span>
                                    
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="join">
                                            <input type="hidden" name="id_exhibition" value="<?= $exp['ID_EXHIBITION'] ?>">
                                            <button type="submit" class="btn btn-sm btn-primary px-3">Unirme</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Organizar Exhibición</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="fw-bold">Nombre del Evento:</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Lugar / Ubicación:</label>
                        <input type="text" name="lugar" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="fw-bold">Inicio:</label>
                            <input type="date" name="fecha_inicio" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="fw-bold">Fin:</label>
                            <input type="date" name="fecha_fin" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Descripción:</label>
                        <textarea name="descripcion" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark fw-bold px-4">Crear Evento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">Editar Exhibición</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="fw-bold">Nombre del Evento:</label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Lugar / Ubicación:</label>
                        <input type="text" name="lugar" id="edit_lugar" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="fw-bold">Inicio:</label>
                            <input type="date" name="fecha_inicio" id="edit_inicio" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="fw-bold">Fin:</label>
                            <input type="date" name="fecha_fin" id="edit_fin" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Descripción:</label>
                        <textarea name="descripcion" id="edit_desc" class="form-control" rows="3"></textarea>
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

<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="view_name"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <i class="bi bi-calendar-range me-1"></i> <span id="view_dates"></span> <br>
                    <i class="bi bi-geo-alt-fill me-1 text-danger"></i> <span id="view_loc"></span>
                </p>
                <h6 class="fw-bold">Descripción:</h6>
                <p id="view_desc" class="text-secondary"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    // JS para Modal Editar
    var editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        editModal.querySelector('#edit_id').value = btn.getAttribute('data-id');
        editModal.querySelector('#edit_nombre').value = btn.getAttribute('data-name');
        editModal.querySelector('#edit_lugar').value = btn.getAttribute('data-loc');
        editModal.querySelector('#edit_inicio').value = btn.getAttribute('data-start');
        editModal.querySelector('#edit_fin').value = btn.getAttribute('data-end');
        editModal.querySelector('#edit_desc').value = btn.getAttribute('data-desc');
    });

    // JS para Modal Ver Detalles
    var viewModal = document.getElementById('viewModal');
    viewModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        viewModal.querySelector('#view_name').textContent = btn.getAttribute('data-name');
        viewModal.querySelector('#view_loc').textContent = btn.getAttribute('data-loc');
        viewModal.querySelector('#view_dates').textContent = btn.getAttribute('data-dates');
        viewModal.querySelector('#view_desc').textContent = btn.getAttribute('data-desc');
    });

    // Confirmación Borrar
    function confirmDelete(e, form) {
        e.preventDefault();
        Swal.fire({
            title: '¿Eliminar evento?',
            text: "Se borrará y saldrán todos los participantes.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, borrar'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        })
    }

    <?php if($msg != ""): ?>
        Swal.fire({
            icon: '<?= $msgType == "success" ? "success" : "info" ?>',
            title: '<?= $msg ?>',
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../../app/views/layout/footer.php'; ?>