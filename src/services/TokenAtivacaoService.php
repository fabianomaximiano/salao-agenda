<?php

declare(strict_types=1);

final class TokenAtivacaoService
{
    private const VALIDADE_MINUTOS = 30;

    /**
     * Gera um novo token para criação da senha.
     *
     * O token puro é retornado apenas para compor o link enviado
     * ao administrador.
     *
     * No banco fica somente o SHA-256.
     */
    public function gerar(PDO $pdo, int $usuarioId): string
    {
        $pdo->beginTransaction();

        try {
            /*
             * Trava o usuário para evitar geração simultânea
             * de múltiplos tokens.
             */
            $stmtUsuario = $pdo->prepare(
                '
                SELECT id
                FROM usuarios
                WHERE id = :usuario_id
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

            /*
             * Revoga qualquer token anterior ainda pendente.
             */
            $stmtRevogar = $pdo->prepare(
                '
                UPDATE usuario_tokens_ativacao
                SET revogado_em = NOW()
                WHERE usuario_id = :usuario_id
                  AND utilizado_em IS NULL
                  AND revogado_em IS NULL
                '
            );

            $stmtRevogar->execute([
                ':usuario_id' => $usuarioId,
            ]);

            /*
             * 32 bytes aleatórios = 256 bits.
             *
             * bin2hex transforma em 64 caracteres seguros
             * para utilização na URL.
             */
            $token = bin2hex(random_bytes(32));

            $tokenHash = hash(
                'sha256',
                $token
            );

            $stmtInsert = $pdo->prepare(
                '
                INSERT INTO usuario_tokens_ativacao (
                    usuario_id,
                    token_hash,
                    expira_em
                )
                VALUES (
                    :usuario_id,
                    :token_hash,
                    DATE_ADD(
                        NOW(),
                        INTERVAL 30 MINUTE
                    )
                )
                '
            );

            $stmtInsert->execute([
                ':usuario_id' => $usuarioId,
                ':token_hash' => $tokenHash,
            ]);

            $pdo->commit();

            return $token;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Localiza e valida um token recebido pela URL.
     *
     * Retorna o usuario_id correspondente.
     */
    public function validar(
        PDO $pdo,
        string $token
    ): int {
        $token = trim($token);

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new RuntimeException(
                'Token de ativação inválido.'
            );
        }

        $tokenHash = hash(
            'sha256',
            $token
        );

        $stmt = $pdo->prepare(
            '
            SELECT
                usuario_id
            FROM usuario_tokens_ativacao
            WHERE token_hash = :token_hash
              AND utilizado_em IS NULL
              AND revogado_em IS NULL
              AND expira_em > NOW()
            LIMIT 1
            '
        );

        $stmt->execute([
            ':token_hash' => $tokenHash,
        ]);

        $usuarioId = $stmt->fetchColumn();

        if ($usuarioId === false) {
            throw new RuntimeException(
                'Este link é inválido ou expirou.'
            );
        }

        return (int) $usuarioId;
    }

    /**
     * Marca o token como utilizado.
     *
     * Deve ser chamado somente depois que a nova senha
     * tiver sido gravada com sucesso.
     */
    public function utilizar(
        PDO $pdo,
        string $token
    ): void {
        $tokenHash = hash(
            'sha256',
            trim($token)
        );

        $stmt = $pdo->prepare(
            '
            UPDATE usuario_tokens_ativacao
            SET utilizado_em = NOW()
            WHERE token_hash = :token_hash
              AND utilizado_em IS NULL
              AND revogado_em IS NULL
              AND expira_em > NOW()
            '
        );

        $stmt->execute([
            ':token_hash' => $tokenHash,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Não foi possível utilizar o token de ativação.'
            );
        }
    }
}