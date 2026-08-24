<?php
/**
 * Garante que a coluna numero_pessoas existe em Grupos, sem depender de
 * uma migração manual no phpMyAdmin — mesmo padrão de auto-provisão em
 * runtime já usado para tabelas noutros sítios deste projeto (ex.:
 * admin_sessoes, confirmacoes_presenca, emails_pendentes).
 *
 * Verifica primeiro via information_schema em vez de usar
 * "ADD COLUMN IF NOT EXISTS" diretamente — essa sintaxe nem sempre está
 * disponível em versões mais antigas de MySQL/MariaDB, e isto funciona em
 * qualquer uma.
 */
function garantirColunaNumeroPessoas(mysqli $conn): void {
    $resDb = $conn->query("SELECT DATABASE()");
    $dbName = $resDb ? $resDb->fetch_row()[0] : null;
    if (!$dbName) return;

    $check = $conn->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'Grupos' AND COLUMN_NAME = 'numero_pessoas'
    ");
    $check->bind_param('s', $dbName);
    $check->execute();
    // stmt_get_result() (polyfill/wrapper de mysqli_stmt::get_result — este
    // servidor não tem mysqlnd nativo), não $check->get_result() direto.
    $existe = (int) stmt_get_result($check)->fetch_row()[0];
    $check->close();

    if ($existe === 0) {
        $conn->query("ALTER TABLE Grupos ADD COLUMN numero_pessoas INT NOT NULL DEFAULT 0");
    }
}
