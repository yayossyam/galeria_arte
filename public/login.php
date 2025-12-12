<?php
    require_once __DIR__ . '/../app/config/config.php';



    // Iniciar sesión para poder guardar los datos del usuario logueado
    session_start();

    // Inicializamos variable de error
    $error = "";


    //Si el método es POST, verificar credenciales
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        // En una variable, guardamos los datos ingresados
        $email = trim($_POST['correo']);
        $password = trim($_POST['pass']);


        // Verificar que los campos no estén vacíos
        if (empty($email) || empty($password)) {
            $error = "Por favor, completa todos los campos.";
        } else {
            // Usamos el try de protección
            try {
                // Buscamos si el usuario es cliente o administrativo
                $queryUser = $pdo->prepare("SELECT * FROM USERS WHERE EMAIL = :email LIMIT 1");
                $queryUser->execute([':email' => $email]);
                $usuario = $queryUser->fetch(PDO::FETCH_ASSOC);

                // Variable para identificar si se encontro un usuario existente
                $loginExitoso = false;

                // Verificar si se encontro un usuario
                if($usuario) {
                    // Verificar contraseña correcta
                    if($password == $usuario['PASS']) {
                        // -- GUARDAMOS DATOS DE SESIÓN --
                        $_SESSION['ID_USER'] = $usuario['ID_USER'];
                        $_SESSION['USER_NAME'] = $usuario['FULL_NAME'];
                        $_SESSION['ROLE'] = $usuario['ID_ROLE'];

                        // Identificamos el rol que tiene el usuario
                        if($usuario['ID_ROLE'] == 1 ) {
                            $_SESSION['USER_TYPE'] = 'ADMIN';
                        } else {
                            $_SESSION['USER_TYPE'] = 'CLIENT';
                        }

                        $loginExitoso = true;
                    }
                }

                // Si no es ni Usuario, ni cliente, es ARTISTA
                if(!$loginExitoso) {
                    $queryArtist = $pdo->prepare("SELECT * FROM ARTISTS WHERE EMAIL = :email LIMIT 1");
                    $queryArtist->execute([':email' => $email]);
                    $artista = $queryArtist->fetch(PDO::FETCH_ASSOC);

                    // Si se encuentra artista
                    if($artista) {
                        // Verificamos contraseña
                        if($password == $artista['PASS']) {
                            // -- GUARDAMOS DATOS DE SESIÓN --
                            $_SESSION['ID_USER'] = $artista['ID_ARTIST'];
                            $_SESSION['USER_NAME'] = $artista['FULL_NAME'];
                            $_SESSION['ROLE'] = isset($artista['ID_ROLE']) ? $artista['ID_ROLE'] : 3;
                            $_SESSION['USER_TYPE'] = 'ARTIST';

                            $loginExitoso = true;
                        }
                    }
                }

                // -- REDIRECCIÓN SEGÚN EL ROL --
                if($loginExitoso) {
                    /** 
                     * if($_SESSION['ROLE'] == 1) {
                     *  header("Location: ". BASE_URL . "/public/admin/dashboard.php");
                     * } elseif($_SESSION['ROLE'] == 3) {
                     *  header("Location: ". BASE_URL . "/public/artist/dashboard.php");
                     * } else {
                     *  header("Location: ". BASE_URL . "/public/index.php");
                     * }
                    */

                    header("Location: ". BASE_URL . "/public/index.php");
                } else {
                    $error = "Correo o contraseña incorrectos";
                }
            } catch (Exception $e) {
                $error = "Error en el sistema: ". $e->getMessage();
            }
        }
    }

    require_once __DIR__ . "/../app/views/layout/header.php";
?>

<div class="container d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="card shadow-lg p-4" style="width: 100%; max-width: 400px; border-radius: 15px;">
        <div class="card-body">
            <h3 class="text-center mb-4 font-weight-bold">Iniciar Sesión</h3>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger text-center p-2"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="correo" class="form-label fw-bold">Correo Electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" name="correo" id="correo" placeholder="ejemplo@correo.com" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="pass" class="form-label fw-bold">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="pass" id="pass" placeholder="********" required>
                    </div>
                </div>
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-dark btn-lg">Ingresar</button>
                </div>
            </form>
            <div class="mt-4 text-center">
                <p class="mb-1"><a href="<?= BASE_URL ?>public/recovery.php" class="text-decoration-none text-muted small">¿Olvidaste tu contraseña?</a></p>
                <p>¿No tienes cuenta? <a href="<?= BASE_URL ?>public/register.php" class="text-decoration-none fw-bold text-dark">Registrarse</a></p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../app/views/layout/footer.php'; ?>