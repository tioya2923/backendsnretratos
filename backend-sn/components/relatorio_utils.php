<?php
/**
 * Monta o HTML do relatório quinzenal para um período — separado de
 * enviar_lembretes.php para poder ser reutilizado por um envio de
 * pré-visualização, sem duplicar a lógica (e sem correr o risco de as
 * duas versões divergirem com o tempo). Depende de janelaJaFechou()
 * (presenca_utils.php) e de stmt_get_result() (mysqli_polyfill.php),
 * já carregados por quem inclui este ficheiro.
 */
function gerarCorpoRelatorioQuinzenal(mysqli $conn, string $inicio, string $fim, string $periodoFormatado): array {
    // Todas as inscrições do período, para cruzar com as confirmações de
    // presença — cada linha da tabela refeicoes pode cobrir várias
    // variantes ao mesmo tempo (ex.: almoço normal + takeaway), por isso
    // trata-se cada uma separadamente.
    $stmtR = $conn->prepare("
        SELECT id, nome_completo, data, almoco, almoco_mais_cedo, almoco_mais_tarde,
               jantar, jantar_mais_cedo, jantar_mais_tarde, levar_refeicao
        FROM refeicoes
        WHERE data BETWEEN ? AND ?
        ORDER BY data, nome_completo
    ");
    $stmtR->bind_param("ss", $inicio, $fim);
    $stmtR->execute();
    $refeicoesPeriodo = stmt_get_result($stmtR)->fetch_all(MYSQLI_ASSOC);
    $stmtR->close();

    // Confirmações já feitas no período — carregadas todas de uma vez
    // (refeicao_id|tipo => true) para consulta rápida no ciclo abaixo.
    $confirmadosSet = [];
    $stmtC = $conn->prepare("
        SELECT c.refeicao_id, c.tipo
        FROM confirmacoes_presenca c
        JOIN refeicoes r ON r.id = c.refeicao_id
        WHERE r.data BETWEEN ? AND ?
    ");
    $stmtC->bind_param("ss", $inicio, $fim);
    $stmtC->execute();
    $resC = stmt_get_result($stmtC);
    while ($row = $resC->fetch_assoc()) {
        $confirmadosSet[$row['refeicao_id'] . '|' . $row['tipo']] = true;
    }
    $stmtC->close();

    // Cada variante é tratada como o seu próprio "tipo" de confirmação —
    // o nome de cada uma é exatamente o nome da coluna correspondente em
    // `refeicoes` (mesma convenção usada em confirmar_presenca.php).
    $tiposPossiveis = [
        'almoco'            => 'Almoço',
        'almoco_mais_cedo'  => 'Almoço mais cedo',
        'almoco_mais_tarde' => 'Almoço mais tarde',
        'jantar'            => 'Jantar',
        'jantar_mais_cedo'  => 'Jantar mais cedo',
        'jantar_mais_tarde' => 'Jantar mais tarde',
        'levar_refeicao'    => 'Takeaway',
    ];

    $confirmaram       = [];
    $faltaram          = [];
    $nomesComInscricao = [];

    foreach ($refeicoesPeriodo as $r) {
        $nome = trim($r['nome_completo']);
        $nomesComInscricao[mb_strtolower($nome, 'UTF-8')] = true;

        foreach ($tiposPossiveis as $tipo => $tipoLabel) {
            if (!$r[$tipo]) continue;

            // Só conta como "confirmou" ou "faltou" depois de a janela
            // fechar — uma refeição de hoje ainda por acontecer (ou um
            // takeaway cuja véspera ainda não chegou) fica de fora (não se
            // pode dizer que alguém faltou a algo que ainda não teve
            // oportunidade de confirmar).
            if (!janelaJaFechou($tipo, $r['data'])) continue;

            $dataFormatada = date('d/m/Y', strtotime($r['data']));
            $linha         = [$nome, $dataFormatada, $tipoLabel];

            if (isset($confirmadosSet["{$r['id']}|$tipo"])) {
                $confirmaram[] = $linha;
            } else {
                $faltaram[] = $linha;
            }
        }
    }

    // Não se inscreveram nenhuma vez no período (nenhuma linha em refeicoes)
    $naoInscritos = [];
    $resU = $conn->query("SELECT name FROM usuarios WHERE status = 'aprovado' ORDER BY name");
    while ($row = $resU->fetch_assoc()) {
        $nome = trim($row['name']);
        if (!isset($nomesComInscricao[mb_strtolower($nome, 'UTF-8')])) {
            $naoInscritos[] = $nome;
        }
    }

    // Tabela em vez de lista simples — mais fácil de ler quando há muitas
    // linhas (o relatório já teve mais de 70 na secção "Faltaram"). Estilos
    // sempre inline (style="...", nunca <style> à parte): a maioria dos
    // clientes de email, incluindo o Gmail, ignora ou corta blocos <style>.
    //
    // Cor de TEXTO explícita em todo o lado onde há cor de FUNDO explícita
    // — nunca só uma das duas. O Gmail (e outros) têm modo escuro que
    // reescreve as cores de emails sem indicação clara de tema; ao definir
    // sempre o par completo (fundo claro + texto escuro), evita-se a
    // combinação ilegível de inverter só uma das duas. Contraste
    // verificado (WCAG): #4b0303 sobre #f4f1ea ≈ 14:1, #222 sobre #fff e
    // sobre #f4f1ea ≈ 16:1 e 15:1 — muito acima do mínimo de 4.5:1.
    $paraTabela = function (array $linhas, array $colunas) {
        if (empty($linhas)) return '<p style="color:#222222;"><em>Ninguém.</em></p>';
        $th = implode('', array_map(
            fn($c) => '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #4b0303;background-color:#f4f1ea;color:#4b0303;">' . htmlspecialchars($c) . '</th>',
            $colunas
        ));
        $corpoLinhas = '';
        foreach ($linhas as $linha) {
            $tds = implode('', array_map(
                fn($v) => '<td style="padding:6px 10px;border-bottom:1px solid #ddd;background-color:#ffffff;color:#222222;">' . htmlspecialchars($v) . '</td>',
                $linha
            ));
            $corpoLinhas .= "<tr>$tds</tr>";
        }
        return '<table style="border-collapse:collapse;width:100%;font-size:14px;margin-bottom:16px;" cellpadding="0" cellspacing="0">'
             . "<thead><tr>$th</tr></thead><tbody>$corpoLinhas</tbody></table>";
    };

    $body = "
        <div style=\"background-color:#ffffff;color:#222222;font-family:Arial,Helvetica,sans-serif;\">
            <h2 style=\"color:#4b0303;\">Relatório quinzenal de inscrições</h2>
            <p>Período: <strong>$periodoFormatado</strong></p>
            <h3 style=\"color:#4b0303;\">Confirmaram presença (" . count($confirmaram) . ")</h3>
            " . $paraTabela($confirmaram, ['Nome', 'Data', 'Refeição']) . "
            <h3 style=\"color:#4b0303;\">Faltaram — inscreveram-se mas não confirmaram presença (" . count($faltaram) . ")</h3>
            " . $paraTabela($faltaram, ['Nome', 'Data', 'Refeição']) . "
            <h3 style=\"color:#4b0303;\">Não se inscreveram em nenhuma refeição (" . count($naoInscritos) . ")</h3>
            " . $paraTabela(array_map(fn($n) => [$n], $naoInscritos), ['Nome']) . "
        </div>
    ";

    return [
        'body' => $body,
        'totalConfirmaram' => count($confirmaram),
        'totalFaltaram' => count($faltaram),
        'totalNaoInscritos' => count($naoInscritos),
    ];
}
