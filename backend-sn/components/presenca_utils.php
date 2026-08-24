<?php
/**
 * Lógica partilhada sobre janelas de confirmação de presença — usada por
 * confirmar_presenca.php (para aceitar/recusar uma confirmação) e por
 * enviar_lembretes.php (para saber, no relatório quinzenal, se a janela de
 * uma refeição já fechou antes de a contar como "confirmou" ou "faltou").
 */

/**
 * Domingo de Páscoa — mesma fórmula (aproximada) já usada no frontend
 * (InscritosRefeicoes.jsx) para decidir o horário do jantar; replicada
 * aqui só para os horários (mostrado e confirmável) baterem sempre certo.
 */
function calcularPascoa(int $ano): DateTime {
    $pascoa = new DateTime("$ano-03-31");
    $diaSemana = (int) $pascoa->format('w'); // 0 = domingo, igual ao getDay() do JS
    $pascoa->modify('+' . (7 - $diaSemana) . ' days');
    return $pascoa;
}

function ehFeriadoOuDomingo(DateTime $data): bool {
    if ((int) $data->format('w') === 0) return true;

    $fixos = ['01/01', '25/04', '01/05', '10/06', '13/06', '15/08', '05/10', '01/11', '01/12', '08/12', '25/12'];
    if (in_array($data->format('d/m'), $fixos, true)) return true;

    $pascoa     = calcularPascoa((int) $data->format('Y'));
    $carnaval   = (clone $pascoa)->modify('-47 days')->format('Y-m-d');
    $sextaSanta = (clone $pascoa)->modify('-2 days')->format('Y-m-d');
    $dataStr    = $data->format('Y-m-d');

    return $dataStr === $carnaval || $dataStr === $sextaSanta;
}

/** Devolve [horaInicio, horaFim] (formato H:i) da janela de confirmação de 1h. */
function janelaConfirmacao(string $tipo, DateTime $data): array {
    if ($tipo === 'almoco') {
        return ['13:30', '14:30'];
    }
    return ehFeriadoOuDomingo($data) ? ['20:30', '21:30'] : ['20:00', '21:00'];
}

/** true se a janela de confirmação de $tipo, no dia $dataStr (Y-m-d), já fechou. */
function janelaJaFechou(string $tipo, string $dataStr): bool {
    $data = new DateTime($dataStr);
    [, $horaFim] = janelaConfirmacao($tipo, $data);
    $fim = DateTime::createFromFormat('Y-m-d H:i', "$dataStr $horaFim");
    return new DateTime() > $fim;
}
