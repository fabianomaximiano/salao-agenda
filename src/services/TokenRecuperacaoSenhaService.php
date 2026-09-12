<?php

declare(strict_types=1);

final class TokenRecuperacaoSenhaService
{
    private const VALIDADE_MINUTOS = 30;
    private const COOLDOWN_SEGUNDOS = 60;
    private const MAX_SOLICITACOES_POR_HORA = 5;

    public function registrarSolicitacao(PDO $pdo, string $email, string $ipHash): bool
    {
        $emailNormalizado = mb_strtolower(trim($email));
        $emailHash = hash('sha256', $emailNormalizado);

        if (!preg_match('/^[a-f0-9]{64}$/', $ipHash)) {
            throw new InvalidArgumentException('Hash de IP inválido.');
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT
                    id,
                    tentativas,
                    TIMESTAMPDIFF(SECOND, janela_iniciada_em, NOW()) AS segundos_janela,
                    TIMESTAMPDIFF(SECOND, ultima_solicitacao_em, NOW()) AS segundos_ultima,
                    CASE
                        WHEN bloqueado_ate IS NOT NULL AND bloqueado_ate > NOW() THEN 1
                        ELSE 0
                    END AS bloqueado
                 FROM recuperacao_senha_solicitacoes
                 WHERE email_hash = :email_hash
                   AND ip_hash = :ip_hash
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([
                ':email_hash' => $emailHash,
                ':ip_hash' => $ipHash,
            ]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {
                $insert = $pdo->prepare(
                    'INSERT INTO recuperacao_senha_solicitacoes (
                        email_hash,
                        ip_hash,
                        tentativas,
                        janela_iniciada_em,
                        ultima_solicitacao_em
                     ) VALUES (
                        :email_hash,
                        :ip_hash,
                        1,
                        NOW(),
                        NOW()
                     )'
                );
                $insert->execute([
                    ':email_hash' => $emailHash,
                    ':ip_hash' => $ipHash,
                ]);
                $pdo->commit();
                return true;
            }

            $id = (int) $registro['id'];

            if ((int) $registro['bloqueado'] === 1) {
                $pdo->commit();
                return false;
            }

            $segundosJanela = (int) $registro['segundos_janela'];
            $segundosUltima = (int) $registro['segundos_ultima'];

            if ($segundosJanela >= 3600) {
                $reset = $pdo->prepare(
                    'UPDATE recuperacao_senha_solicitacoes
                     SET tentativas = 1,
                         janela_iniciada_em = NOW(),
                         ultima_solicitacao_em = NOW(),
                         bloqueado_ate = NULL
                     WHERE id = :id'
                );
                $reset->execute([':id' => $id]);
                $pdo->commit();
                return true;
            }

            if ($segundosUltima < self::COOLDOWN_SEGUNDOS) {
                $pdo->commit();
                return false;
            }

            $tentativas = (int) $registro['tentativas'];

            if ($tentativas >= self::MAX_SOLICITACOES_POR_HORA) {
                $bloquear = $pdo->prepare(
                    'UPDATE recuperacao_senha_solicitacoes
                     SET bloqueado_ate = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                     WHERE id = :id'
                );
                $bloquear->execute([':id' => $id]);
                $pdo->commit();
                return false;
            }

            $update = $pdo->prepare(
                'UPDATE recuperacao_senha_solicitacoes
                 SET tentativas = tentativas + 1,
                     ultima_solicitacao_em = NOW(),
                     bloqueado_ate = NULL
                 WHERE id = :id'
            );
            $update->execute([':id' => $id]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function gerar(PDO $pdo, int $usuarioId, string $ipHash): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $ipHash)) {
            throw new InvalidArgumentException('Hash de IP inválido.');
        }

        $pdo->beginTransaction();

        try {
            $stmtUsuario = $pdo->prepare(
                'SELECT id
                 FROM usuarios
                 WHERE id = :usuario_id
                   AND ativo = 1
                   AND senha_hash IS NOT NULL
                 FOR UPDATE'
            );
            $stmtUsuario->execute([':usuario_id' => $usuarioId]);

            if (!$stmtUsuario->fetchColumn()) {
                throw new RuntimeException('Usuário indisponível para recuperação.');
            }

            $revogar = $pdo->prepare(
                'UPDATE usuario_tokens_recuperacao_senha
                 SET revogado_em = NOW()
                 WHERE usuario_id = :usuario_id
                   AND utilizado_em IS NULL
                   AND revogado_em IS NULL'
            );
            $revogar->execute([':usuario_id' => $usuarioId]);

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $insert = $pdo->prepare(
                'INSERT INTO usuario_tokens_recuperacao_senha (
                    usuario_id,
                    token_hash,
                    solicitado_ip_hash,
                    expira_em
                 ) VALUES (
                    :usuario_id,
                    :token_hash,
                    :ip_hash,
                    DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                 )'
            );
            $insert->execute([
                ':usuario_id' => $usuarioId,
                ':token_hash' => $tokenHash,
                ':ip_hash' => $ipHash,
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

    public function validar(PDO $pdo, string $token): int
    {
        $token = trim($token);

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new RuntimeException('Link de recuperação inválido.');
        }

        $stmt = $pdo->prepare(
            'SELECT t.usuario_id
             FROM usuario_tokens_recuperacao_senha t
             INNER JOIN usuarios u ON u.id = t.usuario_id
             WHERE t.token_hash = :token_hash
               AND t.utilizado_em IS NULL
               AND t.revogado_em IS NULL
               AND t.expira_em > NOW()
               AND u.ativo = 1
               AND u.senha_hash IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute([':token_hash' => hash('sha256', $token)]);
        $usuarioId = $stmt->fetchColumn();

        if ($usuarioId === false) {
            throw new RuntimeException('Este link é inválido, expirou ou já foi utilizado.');
        }

        return (int) $usuarioId;
    }

    public function revogar(PDO $pdo, string $token): void
    {
        $token = trim($token);

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE usuario_tokens_recuperacao_senha
             SET revogado_em = NOW()
             WHERE token_hash = :token_hash
               AND utilizado_em IS NULL
               AND revogado_em IS NULL'
        );
        $stmt->execute([':token_hash' => hash('sha256', $token)]);
    }

    public function redefinirSenha(PDO $pdo, string $token, string $senhaHash): int
    {
        $token = trim($token);

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new RuntimeException('Link de recuperação inválido.');
        }

        $tokenHash = hash('sha256', $token);
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT t.id, t.usuario_id
                 FROM usuario_tokens_recuperacao_senha t
                 INNER JOIN usuarios u ON u.id = t.usuario_id
                 WHERE t.token_hash = :token_hash
                   AND t.utilizado_em IS NULL
                   AND t.revogado_em IS NULL
                   AND t.expira_em > NOW()
                   AND u.ativo = 1
                   AND u.senha_hash IS NOT NULL
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([':token_hash' => $tokenHash]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {
                throw new RuntimeException('Este link é inválido, expirou ou já foi utilizado.');
            }

            $usuarioId = (int) $registro['usuario_id'];

            $updateUsuario = $pdo->prepare(
                'UPDATE usuarios
                 SET senha_hash = :senha_hash
                 WHERE id = :usuario_id
                   AND ativo = 1'
            );
            $updateUsuario->execute([
                ':senha_hash' => $senhaHash,
                ':usuario_id' => $usuarioId,
            ]);

            if ($updateUsuario->rowCount() !== 1) {
                throw new RuntimeException('Não foi possível atualizar a senha.');
            }

            $usarToken = $pdo->prepare(
                'UPDATE usuario_tokens_recuperacao_senha
                 SET utilizado_em = NOW()
                 WHERE id = :id
                   AND utilizado_em IS NULL
                   AND revogado_em IS NULL'
            );
            $usarToken->execute([':id' => (int) $registro['id']]);

            if ($usarToken->rowCount() !== 1) {
                throw new RuntimeException('Não foi possível consumir o token de recuperação.');
            }

            $revogarOutros = $pdo->prepare(
                'UPDATE usuario_tokens_recuperacao_senha
                 SET revogado_em = NOW()
                 WHERE usuario_id = :usuario_id
                   AND id <> :id
                   AND utilizado_em IS NULL
                   AND revogado_em IS NULL'
            );
            $revogarOutros->execute([
                ':usuario_id' => $usuarioId,
                ':id' => (int) $registro['id'],
            ]);

            $limparBloqueios = $pdo->prepare(
                'DELETE FROM usuario_tentativas_login
                 WHERE usuario_id = :usuario_id'
            );
            $limparBloqueios->execute([':usuario_id' => $usuarioId]);

            $pdo->commit();
            return $usuarioId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
