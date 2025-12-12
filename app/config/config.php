<?php

    //Definimos URL base
    define('BASE_URL', 'http://localhost:8012/proyectofinal/');

    try {
        // Se crea una nueva conexion a la base de datos. $pdo es tu OBJETO de conexion.
        $pdo = new PDO("mysql:host=localhost;port=3309;dbname=galeria_arte;charset=utf8", "root", "");

        //Es configurado por PDO, por si ocurre un error lance una excepcion
        $pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    } catch (PDOException $e) {
        die("Error en la conexion: " . $e->getMessage());
    }
?>