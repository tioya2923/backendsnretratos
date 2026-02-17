<?php
// Incluir o ficheiro de conexão

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';


// Atualizar WhatsApp do usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'updateWhatsapp') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $whatsapp = isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '';
    if ($id > 0 && !empty($whatsapp)) {
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
