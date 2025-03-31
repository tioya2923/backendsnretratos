<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../../vendor/autoload.php';

// Verificar a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Buscar Grupos com o total de membros
if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['grupo_id'])) {
    $sql = "SELECT g.*, COUNT(m.id) as total_membros 
            FROM Grupos g 
            LEFT JOIN Membros m ON g.id = m.grupo_id 
            GROUP BY g.id";
    $result = $conn->query($sql);

    $grupos = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $grupos[] = $row;
        }
    }
    echo json_encode($grupos);
    $conn->close();
    exit();
}

// Buscar Membros de um Grupo
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['grupo_id'])) {
    $grupo_id = $_GET['grupo_id'];
    $sql = "SELECT * FROM Membros WHERE grupo_id = ? ORDER BY nome_membro ASC"; // Adicione ORDER BY nome_membro ASC
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $grupo_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $membros = [];
    while ($row = $result->fetch_assoc()) {
        $membros[] = $row;
    }
    echo json_encode($membros);
    $stmt->close();
    $conn->close();
    exit();
}

// Adicionar, Atualizar ou Eliminar Grupo
$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($data['nome_grupo'])) {
        $nome_grupo = $data['nome_grupo'];

        // Verificar se o grupo já existe
        $check_sql = "SELECT COUNT(*) FROM Grupos WHERE nome_grupo = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $nome_grupo);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($count > 0) {
            echo json_encode(["message" => "Grupo já existe"]);
        } else {
            $sql = "INSERT INTO Grupos (nome_grupo) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $nome_grupo);

            if ($stmt->execute()) {
                echo json_encode(["message" => "Grupo adicionado com sucesso"]);
            } else {
                echo json_encode(["message" => "Erro ao adicionar grupo: " . $stmt->error]);
            }
            $stmt->close();
        }
    } elseif (isset($data['nome_membro']) && isset($data['grupo_id'])) {
        $nome_membro = $data['nome_membro'];
        $grupo_id = $data['grupo_id'];

        $sql = "INSERT INTO Membros (nome_membro, grupo_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $nome_membro, $grupo_id);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Membro adicionado com sucesso"]);
        } else {
            echo json_encode(["message" => "Erro ao adicionar membro: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["message" => "Dados incompletos"]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    if (isset($data['id']) && isset($data['nome_grupo'])) {
        $id = $data['id'];
        $nome_grupo = $data['nome_grupo'];

        $sql = "UPDATE Grupos SET nome_grupo = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $nome_grupo, $id);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Grupo atualizado com sucesso"]);
        } else {
            echo json_encode(["message" => "Erro ao atualizar grupo: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["message" => "Dados incompletos"]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    if (isset($data['id'])) {
        $id = $data['id'];

        $sql = "DELETE FROM Grupos WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Grupo eliminado com sucesso"]);
        } else {
            echo json_encode(["message" => "Erro ao eliminar grupo: " . $stmt->error]);
        }
        $stmt->close();
    } elseif (isset($data['membro_id'])) {
        $membro_id = $data['membro_id'];

        $sql = "DELETE FROM Membros WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $membro_id);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Membro eliminado com sucesso"]);
        } else {
            echo json_encode(["message" => "Erro ao eliminar membro: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["message" => "Dados incompletos"]);
    }
}

$conn->close();
?>
