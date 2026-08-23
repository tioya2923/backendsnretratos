<?php
/**
 * Funções partilhadas entre updateAdministradores.php e
 * deleteAdministrador.php. Ficheiro sem código executável ao nível de
 * topo — só definições — para os dois poderem incluí-lo sem correrem
 * a lógica um do outro por acidente.
 */

function getAdmins() {
    global $conn;

    $sql = "SELECT * FROM admins";
    $result = $conn->query($sql);

    $users = array();

    if ($result->num_rows > 0) {
        // output data of each row (sem o hash da password — nunca deve
        // sair do servidor, mesmo para chamadas autenticadas)
        while ($row = $result->fetch_assoc()) {
            unset($row['password_admin']);
            $users[] = $row;
        }
    } else {
        echo json_encode(array('message' => '0 resultados'));
        return;
    }
    echo json_encode($users);
}

/**
 * Apaga um administrador. $currentAdminId tem de vir sempre da sessão
 * autenticada (requireAdmin()), nunca de um valor lido do pedido.
 */
function deleteAdmin(int $id, int $currentAdminId) {
    global $conn;

    if ($id === $currentAdminId) {
        echo json_encode(["status" => "error", "message" => "Não podes eliminar a tua própria conta"]);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM admins WHERE id_admin = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Administrador eliminado com sucesso"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Administrador não encontrado"]);
    }
    $stmt->close();
}
