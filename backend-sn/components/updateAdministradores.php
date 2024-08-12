<?php
// Incluir o ficheiro de conexão
require_once '../connect/server.php';
require_once '../connect/cors.php';

// Função para buscar os dados dos administradores
function getAdmins() {
    global $conn;
    $response = array();

    // Buscar os dados dos administradores
    $sql = "SELECT * FROM admins";
    $result = $conn->query($sql);

    $users = array();

    if ($result->num_rows > 0) {
        // output data of each row
        while($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    } else {
        echo json_encode(array('message' => '0 resultados'));
    }
    echo json_encode($users);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    getAdmins();
}
?>
