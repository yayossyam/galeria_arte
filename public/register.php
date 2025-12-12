<?php
    require_once __DIR__ . '/../app/config/config.php';


    session_start();



    // Inicializamos variables para capturar error y variable de éxito
    $error = "";
    $success = "";

    // Obtener las NACIONALIDADES para mostrarlas en el form
    $nacionalidades = [];

    try {
        $stmNat = $pdo->query("SELECT * FROM NATIONALITY");
        $nacionalidades = $stmNat->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {

    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        // Guardamos de forma local los valores ingresados
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['correo']);
        $pass = trim($_POST['pass']);
        $confirmPass = trim($_POST['confirm_pass']);
        $rol = (int) $_POST['rol'];

        // Expresiones REGULARES

        // Nombre: Inicia Mayúscula, sigue minúsculas
        $regexNombre = "/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(\s[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)*$/u";

        // Password: Inicia Mayúscula, contiene al menos 1 especial, longitud total 8+
        $regexPass = "/^[A-Z](?=.*[\W_]).{7,}$/";


        // Validación básica
        if(empty($nombre) || empty($email) || empty($pass) || empty($confirmPass)) {
            $error = "Por favor, completa todos los campos obligatorios";
        } elseif (!preg_match($regexNombre, $nombre)) {
            $error = "El nombre debe iniciar con mayúscula en cada palabra y no contener números.";
        } elseif (!preg_match($regexPass, $pass)) {
            $error = "La contraseña debe iniciar con Mayúscula, tener un carácter especial y ser mayor a 8 caracteres.";
        } elseif ($pass != $confirmPass) {
            $error = "Las contraseñas no coinciden";
        } else {
            try {
                // Verificar que el correo no exista en la BD
                $checkUser = $pdo->prepare("SELECT EMAIL FROM USERS WHERE EMAIL = ? UNION SELECT EMAIL FROM ARTISTS WHERE EMAIL = ?");
                $checkUser->execute([$email, $email]);

                if($checkUser->rowCount() > 0) {
                    $error = "Este correo electrónico ya esta registrado";
                } else {
                    // Realizar INSERT según el rol

                    // Rol CLIENTE
                    if ($rol == 2) {
                        $sql = "INSERT INTO USERS (ID_ROLE, FULL_NAME, EMAIL, PASS, DATE_CREATED, PROFILE_IMAGE) VALUES (2, :nombre, :email, :pass, NOW(), 'default_user.png')";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':nombre' => $nombre,
                            ':email' => $email,
                            ':pass' => $pass
                        ]);
                    } elseif ($rol == 3) {
                        $bio = trim($_POST['biografia']);
                        $nacionalidad = !empty($_POST['nacionalidad']) ? $_POST['nacionalidad'] : 1;

                        $sql = "INSERT INTO ARTISTS (ID_ROLE, ID_NATIONALITY, FULL_NAME, BIOGRAPHY, EMAIL, PASS, DATE_CREATED, PROFILE_IMAGE) VALUES (3, :nacionalidad, :nombre, :bio, :email, :pass, NOW(), 'default_artist.png')";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':nacionalidad' => $nacionalidad,
                            ':nombre' => $nombre,
                            ':bio' => $bio,
                            ':email' => $email,
                            ':pass' => $pass
                        ]);
                    }

                    // Register éxitoso
                    $success = "¡Cuenta creada con éxito!";
                }
            } catch (Exception $e) {
                $error = "Error al registrarse: " . $e->getMessage();
            }
        }
    }
    
    require_once __DIR__ . '/../app/views/layout/header.php';
?>

<!-- Cuerpo del HTML -->
<div class="container d-flex justify-content-center align-items-center my-5">
    <div class="card shadow-lg p-4" style="width: 100%; max-width:500px; border-radius: 15px;">
        <div class="card-body">
            <h3 class="text-center mb-4 font-weight-bold">Crear Cuenta</h3>

            <?php if(!empty($error)): ?>
                <div class="alert alert-danger fade show d-flex align-items-center justify-content-between p-3" role="alert">
                    <div class="flex-grow-1 text-center">
                        <strong class="me-2">¡Error!</strong> <?= $error ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if(!empty($success)): ?>
                <div class="alert alert-success text-center p-2">
                    <?= $success ?> <br>
                    <a href="<?= BASE_URL ?>public/login.php" class="fw-bold">Ir a Iniciar Sesión</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="mb-3 text-center">
                        <label class="form-label fw-bold d-block">¿Qué tipo de cuenta deseas registrar?</label>

                        <div class="btn-group" role="group" aria-label="Rol">
                            <input type="radio" class="btn-check" name="rol" id="rol_cliente" value="2" checked onchange="toggleArtistFields()">
                            <label class="btn btn-outline-dark" for="rol_cliente">Cliente</label>

                            <input type="radio" class="btn-check" name="rol" id="rol_artista" value="3" onchange="toggleArtistFields()">
                            <label class="btn btn-outline-dark" for="rol_artista">Artista</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" name="nombre" id="nombre" required>
                        <small id="helpNombre" class="form-text text-muted" style="font-size: 0.8rem; display: block; margin-top: 5px;"></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" name="correo" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" class="form-control" name="pass" id="pass" required>
                            <small id="helpPass" class="form-text text-muted" style="font-size: 0.7rem; display: block; margin-top: 5px; line-height: 1.2;"></small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirmar Contraseña</label>
                            <input type="password" class="form-control" name="confirm_pass" id="confirm_pass" required>
                            <small id="helpConfirm" class="form-text text-muted" style="font-size: 0.7rem; display: block; margin-top: 5px;"></small>
                        </div>
                    </div>


                    <!-- ARTISTA -->
                    <div id="campos-artista" style="display:none; background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                        <h6 class="text-muted border-bottom pb-2">Datos del Artista</h6>

                        <div class="mb-3">
                            <label class="form-label">Nacionalidad</label>
                            <select class="form-select" name="nacionalidad">
                                <?php if(empty($nacionalidades)): ?>
                                    <option value="1">Internacional (Default) </option>
                                <?php else: ?>
                                    <?php foreach($nacionalidades as $pais): ?>
                                        <option value="<?= $pais['ID_NATIONALITY'] ?>">
                                            <?= $pais['NAME'] ?? $pais['ID_NATIONALITY'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Biografía / Descripción </label>
                            <textarea class="form-control" name="biografia" rows="3" placeholder="Cuéntanos un poco sobre tu arte..."></textarea>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" class="btn btn-dark btn-lg">Registrarse</button>
                    </div>
                </form>

                <div class="mt-4 text-center">
                    <p>¿Ya tienes cuenta? <a href="<?= BASE_URL ?>public/login.php" class="text-decoration-none fw-bold text-dark">Iniciar Sesión</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
    // Función para mostrar / Ocultar los campos del artista
    function toggleArtistFields() {
        const isArtist = document.getElementById('rol_artista').checked;
        const fields = document.getElementById('campos-artista');

        if(isArtist) {
            fields.style.display = 'block';
        } else {
            fields.style.display = 'none';
        }
    }

    // Validaciones
    document.addEventListener("DOMContentLoaded", function() {
        const inputNombre = document.getElementById('nombre');
        const helpNombre = document.getElementById('helpNombre');

        const inputPass = document.getElementById('pass');
        const helpPass = document.getElementById('helpPass');

        const inputConfirm = document.getElementById('confirm_pass');
        const helpConfirm = document.getElementById('helpConfirm');


        // REGEX JAVASCRIPT
        // Nombre: Inicia con Mayúscula, siguen minúsculas. Permite espacios seguidos de Mayúscula.
        const regexNombre = /^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(\s[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)*$/;

        // Pass: Inicia Mayúscula, contiene especial, longitud > 7 (total 8)
        const regexPass = /^[A-Z](?=.*[\W_]).{7,}$/;
        

        // VALIDAR NOMBRE
        inputNombre.addEventListener('keyup', function() {
            const val = inputNombre.value;
            if (regexNombre.test(val)) {
                inputNombre.classList.remove('is-invalid');
                inputNombre.classList.add('is-valid');
                helpNombre.classList.remove('text-danger');
                helpNombre.classList.add('text-success');
                helpNombre.innerText = "Formato de nombre correcto.";
            } else {
                inputNombre.classList.remove('is-valid');
                inputNombre.classList.add('is-invalid');
                helpNombre.classList.remove('text-success');
                helpNombre.classList.add('text-danger');
                helpNombre.innerText = "Ej: Juan Perez (Sin números, primera mayúscula).";
            }
        });

        // VALIDAR PASSWORD
        inputPass.addEventListener('keyup', function() {
            const val = inputPass.value;
            if (regexPass.test(val)) {
                inputPass.classList.remove('is-invalid');
                inputPass.classList.add('is-valid');
                helpPass.classList.remove('text-danger');
                helpPass.classList.add('text-success');
                helpPass.innerText = "Contraseña segura.";
            } else {
                inputPass.classList.remove('is-valid');
                inputPass.classList.add('is-invalid');
                helpPass.classList.remove('text-success');
                helpPass.classList.add('text-danger');
                helpPass.innerText = "Debe iniciar con Mayúscula, tener 1 carácter especial y min 8 caracteres.";
            }
        });

        // VALIDAR CONFIRMACIÓN DE PASSWORD
        inputConfirm.addEventListener('keyup', function() {
            const val = inputConfirm.value;
            // Compara con el valor actual del campo contraseña
            if (val === inputPass.value && val.length > 0) {
                inputConfirm.classList.remove('is-invalid');
                inputConfirm.classList.add('is-valid');
                helpConfirm.classList.remove('text-danger');
                helpConfirm.classList.add('text-success');
                helpConfirm.innerText = "Las contraseñas coinciden.";
            } else {
                inputConfirm.classList.remove('is-valid');
                inputConfirm.classList.add('is-invalid');
                helpConfirm.classList.remove('text-success');
                helpConfirm.classList.add('text-danger');
                helpConfirm.innerText = "Las contraseñas no coinciden.";
            }
        });
    });
</script>


<?php 
    require_once __DIR__ . '/../app/views/layout/footer.php';
?>