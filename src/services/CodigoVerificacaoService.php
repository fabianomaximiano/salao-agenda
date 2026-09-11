<?php

declare(strict_types=1);

final class CodigoVerificacaoService
{
    private const VALIDADE_MINUTOS = 10;
    private const COOLDOWN_SEGUNDOS = 60;
    private const MAX_TENTATIVAS = 5;
    private const MAX_CODIGOS_POR_HORA = 5;

    /**
     * Gera o primeiro código de verificação.
     *
     * O código puro é retornado apenas ao chamador para envio imediato
     * por e-mail. No banco é persistido somente password_hash().
     */
    public function gerar(
        PDO $pdo,
        int $usuarioId
    ): string {
        return $this->criarCodigo(
            $pdo,
            $usuarioId,
            false
        );
    }

    /**
     * Gera um novo código respeitando cooldown e limite por hora.
     */
    public function reenviar(
        PDO $pdo,
        int $usuarioId
    ): string {
        return $this->criarCodigo(
            $pdo,
            $usuarioId,
            true
        );
    }

    /**
     * Valida e consome um código de seis dígitos.
     *
     * Em caso de código inválido, incrementa o contador de tentativas.
     * Ao atingir o limite máximo, o código é revogado.
     */
    public function validar(
        PDO $pdo,
        int $usuarioId,
        string $codigo
    ): bool {
        $codigo = trim($codigo);

        if (
            $usuarioId <= 0
            || preg_match('/^\d{6}$/', $codigo) !== 1
        ) {
            throw new RuntimeException(
                'Informe um código de verificação válido.'
            );
        }

        $transacaoIniciadaAqui = false;

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $transacaoIniciadaAqui = true;
            }

            $stmt = $pdo->prepare(
                '
                SELECT
                    id,
                    codigo_hash,
                    tentativas,
                    expira_em,
                    utilizado_em,
                    revogado_em,
                    CASE
                        WHEN expira_em <= NOW() THEN 1
                        ELSE 0
                    END AS expirado
                FROM usuario_codigos_verificacao
                WHERE usuario_id = :usuario_id
                  AND utilizado_em IS NULL
                  AND revogado_em IS NULL
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
                '
            );

            $stmt->execute([
                ':usuario_id' => $usuarioId,
            ]);

            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {
                throw new RuntimeException(
                    'Não há código de verificação ativo. Solicite um novo código.'
                );
            }

            if ((int) $registro['expirado'] === 1) {
                $this->revogarCodigo(
                    $pdo,
                    (int) $registro['id']
                );

                if ($transacaoIniciadaAqui) {
                    $pdo->commit();
                }

                throw new RuntimeException(
                    'Este código expirou. Solicite um novo código.'
                );
            }

            $tentativas = (int) $registro['tentativas'];

            if ($tentativas >= self::MAX_TENTATIVAS) {
                $this->revogarCodigo(
                    $pdo,
                    (int) $registro['id']
                );

                if ($transacaoIniciadaAqui) {
                    $pdo->commit();
                }

                throw new RuntimeException(
                    'Este código foi bloqueado por excesso de tentativas. Solicite um novo código.'
                );
            }

            if (
                !password_verify(
                    $codigo,
                    (string) $registro['codigo_hash']
                )
            ) {
                $novaQuantidadeTentativas =
                    $tentativas + 1;

                $revogar =
                    $novaQuantidadeTentativas
                    >= self::MAX_TENTATIVAS;

                $stmtErro = $pdo->prepare(
                    '
                    UPDATE usuario_codigos_verificacao
                    SET
                        tentativas = :tentativas,
                        revogado_em = CASE
                            WHEN :revogar = 1
                            THEN NOW()
                            ELSE revogado_em
                        END
                    WHERE id = :id
                    '
                );

                $stmtErro->execute([
                    ':tentativas' =>
                        $novaQuantidadeTentativas,
                    ':revogar' =>
                        $revogar ? 1 : 0,
                    ':id' =>
                        (int) $registro['id'],
                ]);

                if ($transacaoIniciadaAqui) {
                    $pdo->commit();
                }

                if ($revogar) {
                    throw new RuntimeException(
                        'Código inválido. O limite de tentativas foi atingido. Solicite um novo código.'
                    );
                }

                $restantes =
                    self::MAX_TENTATIVAS
                    - $novaQuantidadeTentativas;

                throw new RuntimeException(
                    'Código inválido. Você ainda possui '
                    . $restantes
                    . ' tentativa(s).'
                );
            }

            $stmtSucesso = $pdo->prepare(
                '
                UPDATE usuario_codigos_verificacao
                SET utilizado_em = NOW()
                WHERE id = :id
                  AND utilizado_em IS NULL
                  AND revogado_em IS NULL
                  AND expira_em > NOW()
                '
            );

            $stmtSucesso->execute([
                ':id' => (int) $registro['id'],
            ]);

            if ($stmtSucesso->rowCount() !== 1) {
                throw new RuntimeException(
                    'O código não pôde ser confirmado. Solicite um novo código.'
                );
            }

            if ($transacaoIniciadaAqui) {
                $pdo->commit();
            }

            return true;
        } catch (Throwable $e) {
            if (
                $transacaoIniciadaAqui
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Retorna quantos segundos ainda faltam para permitir novo envio.
     *
     * O cálculo é feito pelo MySQL para manter a mesma referência temporal
     * utilizada pela regra de cooldown do backend.
     */
    public function segundosRestantesCooldown(
        PDO $pdo,
        int $usuarioId
    ): int {
        if ($usuarioId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare(
            '
            SELECT TIMESTAMPDIFF(
                SECOND,
                criado_em,
                NOW()
            ) AS segundos
            FROM usuario_codigos_verificacao
            WHERE usuario_id = :usuario_id
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
        ]);

        $segundos = $stmt->fetchColumn();

        if ($segundos === false) {
            return 0;
        }

        return max(
            0,
            self::COOLDOWN_SEGUNDOS - max(0, (int) $segundos)
        );
    }

    /**
     * Cria um código de verificação.
     *
     * Quando $aplicarLimites é true, aplica:
     * - cooldown de 60 segundos;
     * - no máximo 5 códigos por hora.
     *
     * Um novo código revoga qualquer código pendente anterior.
     */
    private function criarCodigo(
        PDO $pdo,
        int $usuarioId,
        bool $aplicarLimites
    ): string {
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException(
                'Usuário inválido para geração de código.'
            );
        }

        $transacaoIniciadaAqui = false;

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $transacaoIniciadaAqui = true;
            }

            /*
             * Bloqueia a identidade para serializar gerações concorrentes
             * do mesmo usuário.
             */
            $stmtUsuario = $pdo->prepare(
                '
                SELECT id
                FROM usuarios
                WHERE id = :usuario_id
                LIMIT 1
                FOR UPDATE
                '
            );

            $stmtUsuario->execute([
                ':usuario_id' => $usuarioId,
            ]);

            if (!$stmtUsuario->fetchColumn()) {
                throw new RuntimeException(
                    'Usuário não encontrado.'
                );
            }

            if ($aplicarLimites) {
                $this->validarCooldown(
                    $pdo,
                    $usuarioId
                );

                $this->validarLimitePorHora(
                    $pdo,
                    $usuarioId
                );
            }

            /*
             * Revoga qualquer código anterior ainda pendente.
             */
            $stmtRevogar = $pdo->prepare(
                '
                UPDATE usuario_codigos_verificacao
                SET revogado_em = NOW()
                WHERE usuario_id = :usuario_id
                  AND utilizado_em IS NULL
                  AND revogado_em IS NULL
                '
            );

            $stmtRevogar->execute([
                ':usuario_id' => $usuarioId,
            ]);

            $codigo = (string) random_int(
                100000,
                999999
            );

            $codigoHash = password_hash(
                $codigo,
                PASSWORD_DEFAULT
            );

            if ($codigoHash === false) {
                throw new RuntimeException(
                    'Não foi possível proteger o código de verificação.'
                );
            }

            $stmtInserir = $pdo->prepare(
                '
                INSERT INTO usuario_codigos_verificacao (
                    usuario_id,
                    codigo_hash,
                    tentativas,
                    expira_em
                ) VALUES (
                    :usuario_id,
                    :codigo_hash,
                    0,
                    DATE_ADD(
                        NOW(),
                        INTERVAL '
                        . self::VALIDADE_MINUTOS .
                        ' MINUTE
                    )
                )
                '
            );

            $stmtInserir->execute([
                ':usuario_id' => $usuarioId,
                ':codigo_hash' => $codigoHash,
            ]);

            if ($transacaoIniciadaAqui) {
                $pdo->commit();
            }

            return $codigo;
        } catch (Throwable $e) {
            if (
                $transacaoIniciadaAqui
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Impede novo envio antes de 60 segundos.
     */
    private function validarCooldown(
        PDO $pdo,
        int $usuarioId
    ): void {
        $stmt = $pdo->prepare(
            '
            SELECT
                criado_em,
                TIMESTAMPDIFF(
                    SECOND,
                    criado_em,
                    NOW()
                ) AS segundos
            FROM usuario_codigos_verificacao
            WHERE usuario_id = :usuario_id
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
            '
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
        ]);

        $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ultimo) {
            return;
        }

        $segundos = (int) (
            $ultimo['segundos']
            ?? 0
        );

        if ($segundos < self::COOLDOWN_SEGUNDOS) {
            $aguarde =
                self::COOLDOWN_SEGUNDOS
                - max(0, $segundos);

            throw new RuntimeException(
                'Aguarde '
                . $aguarde
                . ' segundo(s) antes de solicitar outro código.'
            );
        }
    }

    /**
     * Limita a quantidade de códigos gerados na última hora.
     */
    private function validarLimitePorHora(
        PDO $pdo,
        int $usuarioId
    ): void {
        $stmt = $pdo->prepare(
            '
            SELECT COUNT(*)
            FROM usuario_codigos_verificacao
            WHERE usuario_id = :usuario_id
              AND criado_em >= DATE_SUB(
                  NOW(),
                  INTERVAL 1 HOUR
              )
            '
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
        ]);

        $quantidade = (int) $stmt->fetchColumn();

        if (
            $quantidade
            >= self::MAX_CODIGOS_POR_HORA
        ) {
            throw new RuntimeException(
                'Limite de códigos por hora atingido. Tente novamente mais tarde.'
            );
        }
    }

    /**
     * Revoga um código ainda pendente.
     */
    private function revogarCodigo(
        PDO $pdo,
        int $codigoId
    ): void {
        $stmt = $pdo->prepare(
            '
            UPDATE usuario_codigos_verificacao
            SET revogado_em = NOW()
            WHERE id = :id
              AND utilizado_em IS NULL
              AND revogado_em IS NULL
            '
        );

        $stmt->execute([
            ':id' => $codigoId,
        ]);
    }
}
