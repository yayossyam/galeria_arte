<?php
    require_once __DIR__ . '/../../../app/config/config.php';
    
    session_start();

    // Verificar si el usuario es administrador
    if(!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 1) {
        header("Location: " . BASE_URL . "public/login.php");
        exit;
    }

    // URL actual limpia
    $url_actual = $_SERVER['PHP_SELF'];

    // Variables para mensajes
    $msg = "";
    $msgType ="";

    // RECUPERAR FLASH MESSAGE
    if(isset($_SESSION['temp_msg'])) {
        $msg = $_SESSION['temp_msg'];
        $msgType = $_SESSION['temp_msg_type'];
        
        if($msgType == 'danger') $msgType = 'error'; 
        
        unset($_SESSION['temp_msg']);
        unset($_SESSION['temp_msg_type']);
    }

    // Update (Solo actualizamos el Estatus de la Orden)
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
        
        $id_order = (int) $_POST['id'];
        $id_status = (int) $_POST['id_status'];
        
        if($id_order > 0 && $id_status > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE ORDERS SET ID_STATUS = :status WHERE ID_ORDER = :id");
                $stmt->execute([':status' => $id_status, ':id' => $id_order]);

                $_SESSION['temp_msg'] = "Estatus de la orden actualizado correctamente.";
                $_SESSION['temp_msg_type'] = "success";

            } catch (Exception $e) {
                $msg = "Error al actualizar: " . $e->getMessage();
                $msgType = "error";
            }
            
            // Redirección para evitar reenvío
            header("Location: " . $url_actual);
            exit;
        } else {
            $msg = "Datos inválidos.";
            $msgType = "error";
        }
    }

    // Delete (Eliminar orden y sus ítems)
    if(isset($_GET['delete_id'])) {
        $id = (int) $_GET['delete_id'];

        try {
            // Primero eliminamos los detalles para mantener integridad
            $pdo->prepare("DELETE FROM ORDER_ITEMS WHERE ID_ORDER = :id")->execute([':id' => $id]);
            // Luego la orden
            $stmt = $pdo->prepare("DELETE FROM ORDERS WHERE ID_ORDER = :id");
            $stmt->execute([':id' => $id]);

            $_SESSION['temp_msg'] = "Orden eliminada.";
            $_SESSION['temp_msg_type'] = "success";

        } catch (Exception $e) {
            $_SESSION['temp_msg'] = "No se puede eliminar la orden.";
            $_SESSION['temp_msg_type'] = "error";
        }

        header("Location: " . $url_actual);
        exit;
    }

    // Select (Traer Órdenes con JOIN a Usuarios y Estatus)
    $registros = [];
    try {
        $sql = "SELECT 
                    O.ID_ORDER, 
                    O.ORDER_DATE, 
                    O.TOTAL, 
                    O.ID_STATUS,
                    U.FULL_NAME AS CLIENTE, 
                    S.NAME AS ESTATUS_NAME
                FROM ORDERS O
                JOIN USERS U ON O.ID_USER = U.ID_USER
                JOIN STATUS_ORDERS S ON O.ID_STATUS = S.ID_STATUS
                ORDER BY O.ORDER_DATE DESC"; // Importante ordenar por fecha para agrupar
        
        $stmt = $pdo->query($sql);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $msg = "Error al cargar los datos";
        $msgType = "error";
    }

    // Select Estatus (Para el dropdown del modal)
    $lista_estatus = [];
    try {
        $lista_estatus = $pdo->query("SELECT * FROM STATUS_ORDERS")->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {}


    require_once __DIR__ . '/../../../app/views/layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-receipt"></i> Gestión de Órdenes</h2>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-dark text-white">
                    <tr>
                        <th scope="col" class="ps-4">ID</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Fecha</th>
                        <th scope="col">Total</th>
                        <th scope="col">Estatus</th>
                        <th scope="col" class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($registros)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No hay órdenes registradas</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $mes_anterior = "";
                        // Arrays para traducir meses al español si lo deseas
                        $meses_es = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
                                    '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
                        
                        foreach($registros as $row): 
                            // Lógica para agrupar por mes
                            $timestamp = strtotime($row['ORDER_DATE']);
                            $mes_numero = date('m', $timestamp);
                            $anio = date('Y', $timestamp);
                            $mes_actual_texto = $meses_es[$mes_numero] . " " . $anio;

                            // Si cambiamos de mes, imprimimos la fila separadora
                            if($mes_anterior != $mes_actual_texto):
                                $mes_anterior = $mes_actual_texto;
                        ?>
                                <tr class="table-secondary">
                                    <td colspan="6" class="fw-bold text-uppercase ps-4 py-2 text-primary">
                                        <i class="bi bi-calendar-event"></i> <?= $mes_actual_texto ?>
                                    </td>
                                </tr>
                        <?php endif; ?>

                            <tr>
                                <td class="ps-4 fw-bold text-secondary">#<?= $row['ID_ORDER'] ?></td>
                                <td class="fw-bold"><?= $row['CLIENTE'] ?></td>
                                <td><?= date('d/m/Y', $timestamp) ?></td>
                                <td class="text-success fw-bold">$<?= number_format($row['TOTAL'], 2) ?></td>
                                <td>
                                    <span class="badge rounded-pill bg-info text-dark">
                                        <?= $row['ESTATUS_NAME'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-warning me-1" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editModal" 
                                        data-id="<?= $row['ID_ORDER'] ?>" 
                                        data-status="<?= $row['ID_STATUS'] ?>">
                                        <i class="bi bi-pencil"></i> Estado
                                    </button>
                                    
                                    <a href="#" 
                                        onclick="confirmDelete(event, '?delete_id=<?= $row['ID_ORDER'] ?>')" 
                                        class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Actualizar Estatus de Orden</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form method="POST" action="">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="order_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nuevo Estatus:</label>
                        <select name="id_status" id="select_status" class="form-select form-select-lg">
                            <?php foreach($lista_estatus as $st): ?>
                                <option value="<?= $st['ID_STATUS'] ?>"><?= $st['NAME'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning px-4">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // 1. Script para el Modal de Edición
    var editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        
        var id = button.getAttribute('data-id');
        var status = button.getAttribute('data-status');
        
        var modalInputId = editModal.querySelector('#order_id');
        var modalSelectStatus = editModal.querySelector('#select_status');
        
        modalInputId.value = id;
        modalSelectStatus.value = status;
    });

    // 2. Alerta SweetAlert para Eliminar
    function confirmDelete(e, url) {
        e.preventDefault();
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Se eliminará la orden y su historial.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        })
    }

    // 3. Mostrar Mensajes PHP con SweetAlert (Toast)
    <?php if($msg != ""): ?>
        Swal.fire({
            icon: '<?= $msgType ?>',
            title: '<?= $msg ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../../app/views/layout/footer.php'; ?>