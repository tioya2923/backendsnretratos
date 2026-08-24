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

/**
 * Devolve [horaInicio, horaFim] (formato H:i) da janela de confirmação de 1h
 * para cada variante de refeição. "Mais cedo"/"mais tarde" não têm hora
 * exata registada (são só uma marcação, sem hora) — usa-se por convenção
 * 1h de desvio face ao horário normal, em ambos os sentidos.
 */
function janelaConfirmacao(string $tipo, DateTime $data): array {
    $feriadoOuDomingo = ehFeriadoOuDomingo($data);

    switch ($tipo) {
        case 'almoco_mais_cedo':
            return ['12:30', '13:30'];
        case 'almoco_mais_tarde':
            return ['14:30', '15:30'];
        case 'almoco':
            return ['13:30', '14:30'];

        case 'jantar_mais_cedo':
            return $feriadoOuDomingo ? ['19:30', '20:30'] : ['19:00', '20:00'];
        case 'jantar_mais_tarde':
            return $feriadoOuDomingo ? ['21:30', '22:30'] : ['21:00', '22:00'];
        case 'jantar':
        case 'levar_refeicao':
            // Takeaway é levantado à hora normal do jantar — mas na véspera
            // da data da refeição (ver diaConfirmacao()).
            return $feriadoOuDomingo ? ['20:30', '21:30'] : ['20:00', '21:00'];

        default:
            return $feriadoOuDomingo ? ['20:30', '21:30'] : ['20:00', '21:00'];
    }
}

/**
 * Dia em que a confirmação de $tipo deve ser feita. Igual ao dia da
 * refeição para todos os tipos, exceto Takeaway — levantado na véspera.
 */
function diaConfirmacao(string $tipo, string $dataRefeicaoStr): string {
    if ($tipo !== 'levar_refeicao') return $dataRefeicaoStr;
    return (new DateTime($dataRefeicaoStr))->modify('-1 day')->format('Y-m-d');
}

/** true se a janela de confirmação de $tipo, para a refeição de $dataRefeicaoStr (Y-m-d), já fechou. */
function janelaJaFechou(string $tipo, string $dataRefeicaoStr): bool {
    $diaConf = diaConfirmacao($tipo, $dataRefeicaoStr);
    $data = new DateTime($diaConf);
    [, $horaFim] = janelaConfirmacao($tipo, $data);
    $fim = DateTime::createFromFormat('Y-m-d H:i', "$diaConf $horaFim");
    return new DateTime() > $fim;
}
