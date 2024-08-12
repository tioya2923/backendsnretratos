<?php
// Incluir o ficheiro de conexão
require_once '../connect/server.php';
require_once '../connect/cors.php';

// Função para eliminar um usuário
function deleteUser($id) {
    global $conn;
    $sql = "DELETE FROM usuarios WHERE id = $id";
    if ($conn->query($sql) === TRUE) {
        echo "Usuário eliminado com sucesso";
    } else {
        echo "Erro ao eliminar o usuário: " . $conn->error;
    }
}

$sql = "SELECT * FROM usuarios";
$result = $conn->query($sql);

$users = array();

if ($result->num_rows > 0) {
    // output data of each row
    while($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} else {
    echo "0 resultados";
}
echo json_encode($users);


?>
