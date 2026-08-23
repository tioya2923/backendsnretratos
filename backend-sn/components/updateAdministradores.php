<?php
// Incluir o ficheiro de conexão

require_once __DIR__ . '/../../vendor/autoload.php';
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
        // output data of each row (sem o hash da password — nunca deve
        // sair do servidor, mesmo para chamadas autenticadas)
        while($row = $result->fetch_assoc()) {
            unset($row['password_admin']);
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
