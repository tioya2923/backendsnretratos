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
    // DATABASE() dentro da própria query evita um pedido extra para
    // descobrir o nome da base de dados atual.
    $result = $conn->query("
        SELECT COUNT(*) AS total FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Grupos' AND COLUMN_NAME = 'numero_pessoas'
    ");
    if (!$result) return; // information_schema inacessível — não bloquear o pedido por causa disto.

    $linha  = $result->fetch_assoc();
    $existe = (int) ($linha['total'] ?? 0);

    if ($existe === 0) {
        $conn->query("ALTER TABLE Grupos ADD COLUMN numero_pessoas INT NOT NULL DEFAULT 0");
    }
}
