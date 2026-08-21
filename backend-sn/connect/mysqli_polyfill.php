<?php
/**
 * Substituto de mysqli_stmt::get_result() para servidores sem a extensão
 * mysqlnd ativa (comum em alguns planos de hosting partilhado com PHP
 * Selector da CloudLinux, onde "mysqlnd" é uma extensão à parte de
 * "mysqli"). Sem mysqlnd, get_result() nem existe na classe — a única
 * forma de ler os resultados de um prepared statement é bind_result()
 * + fetch(), que é o que esta função faz por nós.
 *
 * Uso: em vez de $stmt->get_result(), usar stmt_get_result($stmt).
 * O objeto devolvido tem a mesma interface mínima já usada em todo o
 * projeto: fetch_assoc(), fetch_all() e num_rows.
 */

if (!class_exists('PolyfillStmtResult')) {
    class PolyfillStmtResult
    {
        /** @var array<int, array<string, mixed>> */
        private array $rows;
        private int $pointer = 0;
        public int $num_rows;

        /** @param array<int, array<string, mixed>> $rows */
        public function __construct(array $rows)
        {
            $this->rows = $rows;
            $this->num_rows = count($rows);
        }

        public function fetch_assoc(): ?array
        {
            if ($this->pointer >= count($this->rows)) {
                return null;
            }
            return $this->rows[$this->pointer++];
        }

        /** @return array<int, array<string, mixed>> */
        public function fetch_all(int $mode = MYSQLI_ASSOC): array
        {
            $remaining = array_slice($this->rows, $this->pointer);
            $this->pointer = count($this->rows);
            return $remaining;
        }
    }
}

if (!function_exists('stmt_get_result')) {
    function stmt_get_result(mysqli_stmt $stmt): PolyfillStmtResult
    {
        // Se o mysqlnd estiver disponível, usar sempre a versão nativa —
        // mais rápida, e sem qualquer diferença de comportamento.
        if (method_exists($stmt, 'get_result')) {
            $native = $stmt->get_result();
            if ($native !== false) {
                return new PolyfillStmtResult($native->fetch_all(MYSQLI_ASSOC));
            }
        }

        $meta = $stmt->result_metadata();
        if ($meta === false) {
            // Statement sem result set (ex.: INSERT/UPDATE) — nada a devolver.
            return new PolyfillStmtResult([]);
        }

        $fields = [];
        while ($field = $meta->fetch_field()) {
            $fields[] = $field->name;
        }
        $meta->free();

        $bound = array_fill(0, count($fields), null);
        $bindRefs = [];
        foreach ($bound as $i => &$value) {
            $bindRefs[] = &$value;
        }
        unset($value);

        call_user_func_array([$stmt, 'bind_result'], $bindRefs);

        $rows = [];
        while ($stmt->fetch()) {
            $row = [];
            foreach ($fields as $i => $name) {
                $row[$name] = $bound[$i];
            }
            $rows[] = $row;
        }

        return new PolyfillStmtResult($rows);
    }
}
