<?php

declare(strict_types=1);

final class AutoAgendamentoService
{
    public function buscarDadosServico(
        PDO $pdo,
        int $empresaId,
        int $servicoId,
        int $profissionalId
    ): ?array {
        $stmt = $pdo->prepare(
            'SELECT
                s.nome,
                s.intervalo_minutos,
                COALESCE(ps.duracao_minutos, s.duracao_minutos) AS duracao,
                COALESCE(ps.preco, s.preco) AS preco
             FROM servicos s
             INNER JOIN profissional_servicos ps
                ON ps.servico_id = s.id
               AND ps.profissional_id = :profissional_id
               AND ps.ativo = 1
             INNER JOIN profissionais pr
                ON pr.id = ps.profissional_id
               AND pr.empresa_id = s.empresa_id
               AND pr.ativo = 1
             INNER JOIN pessoas p
                ON p.id = pr.pessoa_id
               AND p.empresa_id = pr.empresa_id
               AND p.ativo = 1
             WHERE s.id = :servico_id
               AND s.empresa_id = :empresa_id
               AND s.ativo = 1
               AND s.permite_agendamento_online = 1
             LIMIT 1'
        );

        $stmt->execute([
            ':profissional_id' => $profissionalId,
            ':servico_id' => $servicoId,
            ':empresa_id' => $empresaId,
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public function buscarHorariosDisponiveis(
        PDO $pdo,
        int $empresaId,
        int $servicoId,
        int $profissionalId,
        string $data,
        int $agendamentoIgnoradoId = 0
    ): array {
        $servico = $this->buscarDadosServico(
            $pdo,
            $empresaId,
            $servicoId,
            $profissionalId
        );

        if (
            !$servico
            || new DateTimeImmutable($data) < new DateTimeImmutable('today')
        ) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT id, tipo
             FROM empresa_excecoes
             WHERE empresa_id = :empresa_id
               AND data_excecao = :data
               AND ativo = 1
             LIMIT 1'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':data' => $data,
        ]);

        $excecao = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($excecao && $excecao['tipo'] === 'fechado') {
            return [];
        }

        $diaSemana = (int) (new DateTimeImmutable($data))->format('N');

        if ($excecao) {
            $stmt = $pdo->prepare(
                'SELECT hora_inicio, hora_fim
                 FROM empresa_excecao_periodos
                 WHERE empresa_excecao_id = :excecao_id
                 ORDER BY hora_inicio'
            );

            $stmt->execute([
                ':excecao_id' => $excecao['id'],
            ]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT hora_inicio, hora_fim
                 FROM empresa_horarios
                 WHERE empresa_id = :empresa_id
                   AND dia_semana = :dia_semana
                   AND ativo = 1
                 ORDER BY hora_inicio'
            );

            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':dia_semana' => $diaSemana,
            ]);
        }

        $periodosEmpresa = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$periodosEmpresa) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT hora_inicio, hora_fim
             FROM profissional_horarios
             WHERE profissional_id = :profissional_id
               AND dia_semana = :dia_semana
               AND ativo = 1
             ORDER BY hora_inicio'
        );

        $stmt->execute([
            ':profissional_id' => $profissionalId,
            ':dia_semana' => $diaSemana,
        ]);

        $periodosProfissional = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$periodosProfissional) {
            return [];
        }

        $inicioDia = $data . ' 00:00:00';

        $fimDia = (new DateTimeImmutable($inicioDia))
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'SELECT inicio, fim
             FROM profissional_bloqueios
             WHERE profissional_id = :profissional_id
               AND inicio < :fim_dia
               AND fim > :inicio_dia'
        );

        $stmt->execute([
            ':profissional_id' => $profissionalId,
            ':fim_dia' => $fimDia,
            ':inicio_dia' => $inicioDia,
        ]);

        $ocupados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql =
            "SELECT ags.inicio, ags.fim
             FROM agendamento_servicos ags
             INNER JOIN agendamentos a
                ON a.id = ags.agendamento_id
             WHERE ags.profissional_id = :profissional_id
               AND ags.inicio < :fim_dia
               AND ags.fim > :inicio_dia
               AND a.status NOT IN ('cancelado', 'nao_compareceu')
               AND ags.status NOT IN ('cancelado', 'nao_compareceu')";

        $params = [
            ':profissional_id' => $profissionalId,
            ':fim_dia' => $fimDia,
            ':inicio_dia' => $inicioDia,
        ];

        if ($agendamentoIgnoradoId > 0) {
            $sql .= ' AND a.id <> :agendamento_ignorado_id';
            $params[':agendamento_ignorado_id'] = $agendamentoIgnoradoId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $ocupados = array_merge(
            $ocupados,
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );

        $resultado = [];

        $duracao = (int) $servico['duracao'];
        $intervalo = (int) $servico['intervalo_minutos'];
        $agora = new DateTimeImmutable();

        foreach ($periodosEmpresa as $periodoEmpresa) {
            foreach ($periodosProfissional as $periodoProfissional) {
                $inicio = max(
                    new DateTimeImmutable(
                        $data . ' ' . $periodoEmpresa['hora_inicio']
                    ),
                    new DateTimeImmutable(
                        $data . ' ' . $periodoProfissional['hora_inicio']
                    )
                );

                $fim = min(
                    new DateTimeImmutable(
                        $data . ' ' . $periodoEmpresa['hora_fim']
                    ),
                    new DateTimeImmutable(
                        $data . ' ' . $periodoProfissional['hora_fim']
                    )
                );

                for (
                    $horario = $inicio;
                    ;
                    $horario = $horario->modify('+15 minutes')
                ) {
                    $fimServico = $horario->modify(
                        '+' . $duracao . ' minutes'
                    );

                    $fimReserva = $fimServico->modify(
                        '+' . $intervalo . ' minutes'
                    );

                    if ($fimReserva > $fim) {
                        break;
                    }

                    if ($horario <= $agora) {
                        continue;
                    }

                    $disponivel = true;

                    foreach ($ocupados as $ocupado) {
                        if (
                            $this->horariosSobrepostos(
                                $horario,
                                $fimReserva,
                                new DateTimeImmutable($ocupado['inicio']),
                                new DateTimeImmutable($ocupado['fim'])
                            )
                        ) {
                            $disponivel = false;
                            break;
                        }
                    }

                    if ($disponivel) {
                        $resultado[] = [
                            'hora' => $horario->format('H:i'),
                            'inicio' => $horario->format('Y-m-d H:i:s'),
                            'fim' => $fimServico->format('Y-m-d H:i:s'),
                        ];
                    }
                }
            }
        }

        return $resultado;
    }

    private function horariosSobrepostos(
        DateTimeImmutable $inicioA,
        DateTimeImmutable $fimA,
        DateTimeImmutable $inicioB,
        DateTimeImmutable $fimB
    ): bool {
        return $inicioA < $fimB && $fimA > $inicioB;
    }
}