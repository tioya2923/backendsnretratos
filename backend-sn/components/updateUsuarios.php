<?php
// Incluir o ficheiro de conexão

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';


// Atualizar WhatsApp do usuário — chamado a meio do login (antes de
// existir sessão), por isso confirma-se a password em vez de um token.
// Sem isto, bastava adivinhar o id (inteiro sequencial pequeno) de
// outra pessoa para sequestrar as notificações dela.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'updateWhatsapp') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $whatsapp = isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($id > 0 && !empty($whatsapp) && !empty($password)) {
        $check = $conn->prepare("SELECT password FROM usuarios WHERE id = ?");
        $check->bind_param('i', $id);
        $check->execute();
        $row = stmt_get_result($check)->fetch_assoc();
        $check->close();

        if (!$row || !password_verify($password, $row['password'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
            exit();
        }

        $sql = "UPDATE usuarios SET whatsapp = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $whatsapp, $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'WhatsApp atualizado com sucesso']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar WhatsApp']);
        }
        $stmt->close();
        exit();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
        exit();
    }
}

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
