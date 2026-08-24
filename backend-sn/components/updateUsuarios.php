<?php
// Incluir o ficheiro de conexão

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';


/**
 * Apaga um utilizador — chamado a partir de deleteUsuario.php, já com
 * a sessão de administrador confirmada.
 */
function deleteUser(int $id) {
    global $conn;
    header('Content-Type: application/json');
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Utilizador eliminado com sucesso"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Utilizador não encontrado"]);
    }
    $stmt->close();
}

// Listagem completa de utilizadores — só para o painel de administração.
// Verificação de método explícita: este ficheiro é incluído por
// deleteUsuario.php (pedido DELETE) só para reaproveitar deleteUser();
// sem este "if", a listagem correria também nesse pedido.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireAdmin($conn);

    $sql = "SELECT * FROM usuarios";
    $result = $conn->query($sql);

    $users = array();

    // output data of each row (sem o hash da password — nunca deve
    // sair do servidor, mesmo para chamadas autenticadas)
    while ($row = $result->fetch_assoc()) {
        unset($row['password']);
        $users[] = $row;
    }
    echo json_encode($users);
}


?>
