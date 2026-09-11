<?php

declare(strict_types=1);

final class EmpresaService
{
    public function criar(PDO $pdo, array $dados): array
    {
        $dados = $this->normalizarDados($dados);

        $this->validarDados($pdo, $dados);

        $pdo->beginTransaction();

        try {
            $empresaId = $this->criarEmpresa(
                $pdo,
                $dados
            );

            $this->vincularSegmento(
                $pdo,
                $empresaId,
                $dados['segmento_id']
            );

            $usuarioId = $this->criarUsuario(
                $pdo,
                $dados['admin_email']
            );

            $administradorId = $this->criarAdministrador(
                $pdo,
                $empresaId,
                $usuarioId,
                $dados
            );

            $pdo->commit();

            return [
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'administrador_id' => $administradorId,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    private function normalizarDados(array $dados): array
    {
        return [
            'nome_fantasia' => trim(
                (string) ($dados['nome_fantasia'] ?? '')
            ),

            'segmento_id' => (int) (
                $dados['segmento_id'] ?? 0
            ),

            'razao_social' => $this->nullSeVazio(
                $dados['razao_social'] ?? null
            ),

            'tipo_documento' => strtolower(
                trim(
                    (string) ($dados['tipo_documento'] ?? '')
                )
            ),

            'documento' => $this->somenteNumeros(
                (string) ($dados['documento'] ?? '')
            ),

            'email_empresa' => $this->normalizarEmail(
                $dados['email_empresa'] ?? null
            ),

            'telefone_empresa' => $this->nullSeVazio(
                $dados['telefone_empresa'] ?? null
            ),

            'whatsapp_empresa' => $this->nullSeVazio(
                $dados['whatsapp_empresa'] ?? null
            ),

            'cep' => $this->somenteNumeros(
                (string) ($dados['cep'] ?? '')
            ),

            'logradouro' => trim(
                (string) ($dados['logradouro'] ?? '')
            ),

            'numero' => trim(
                (string) ($dados['numero'] ?? '')
            ),

            'complemento' => $this->nullSeVazio(
                $dados['complemento'] ?? null
            ),

            'bairro' => trim(
                (string) ($dados['bairro'] ?? '')
            ),

            'cidade' => trim(
                (string) ($dados['cidade'] ?? '')
            ),

            'estado' => strtoupper(
                trim(
                    (string) ($dados['estado'] ?? '')
                )
            ),

            'admin_nome' => trim(
                (string) ($dados['admin_nome'] ?? '')
            ),

            'admin_cpf' => $this->somenteNumeros(
                (string) ($dados['admin_cpf'] ?? '')
            ),

            'admin_telefone' => $this->nullSeVazio(
                $dados['admin_telefone'] ?? null
            ),

            'admin_email' => strtolower(
                trim(
                    (string) ($dados['admin_email'] ?? '')
                )
            ),
        ];
    }

    private function validarDados(
        PDO $pdo,
        array $dados
    ): void {
        if ($dados['nome_fantasia'] === '') {
            throw new RuntimeException(
                'Informe o nome fantasia.'
            );
        }

        if ($dados['segmento_id'] <= 0) {
            throw new RuntimeException(
                'Selecione um segmento válido.'
            );
        }

        if (
            !in_array(
                $dados['tipo_documento'],
                ['cpf', 'cnpj'],
                true
            )
        ) {
            throw new RuntimeException(
                'Selecione CPF ou CNPJ para a empresa.'
            );
        }

        if (
            $dados['tipo_documento'] === 'cpf'
            && !$this->validarCPF($dados['documento'])
        ) {
            throw new RuntimeException(
                'CPF da empresa inválido.'
            );
        }

        if (
            $dados['tipo_documento'] === 'cnpj'
            && !$this->validarCNPJ($dados['documento'])
        ) {
            throw new RuntimeException(
                'CNPJ inválido.'
            );
        }

        if (
            $dados['email_empresa'] !== null
            && !filter_var(
                $dados['email_empresa'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'E-mail da empresa inválido.'
            );
        }

        if (strlen($dados['cep']) !== 8) {
            throw new RuntimeException(
                'CEP inválido.'
            );
        }

        if (
            $dados['logradouro'] === ''
            || $dados['numero'] === ''
            || $dados['bairro'] === ''
            || $dados['cidade'] === ''
            || strlen($dados['estado']) !== 2
        ) {
            throw new RuntimeException(
                'Preencha corretamente o endereço da empresa.'
            );
        }

        if ($dados['admin_nome'] === '') {
            throw new RuntimeException(
                'Informe o nome do administrador.'
            );
        }

        if (!$this->validarCPF($dados['admin_cpf'])) {
            throw new RuntimeException(
                'CPF do administrador inválido.'
            );
        }

        if (
            !filter_var(
                $dados['admin_email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'E-mail do administrador inválido.'
            );
        }

        $this->validarSegmentoExiste(
            $pdo,
            $dados['segmento_id']
        );

        $this->validarDocumentoEmpresaDisponivel(
            $pdo,
            $dados['documento']
        );

        $this->validarEmailUsuarioDisponivel(
            $pdo,
            $dados['admin_email']
        );

        $this->validarAdministradorDisponivel(
            $pdo,
            $dados['admin_cpf'],
            $dados['admin_email']
        );
    }

    private function validarSegmentoExiste(
        PDO $pdo,
        int $segmentoId
    ): void {
        $stmt = $pdo->prepare(
            '
            SELECT id
            FROM segmentos
            WHERE id = :id
              AND ativo = 1
            LIMIT 1
            '
        );

        $stmt->execute([
            ':id' => $segmentoId,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'Segmento não encontrado ou inativo.'
            );
        }
    }

    private function validarDocumentoEmpresaDisponivel(
        PDO $pdo,
        string $documento
    ): void {
        $stmt = $pdo->prepare(
            '
            SELECT id
            FROM empresas
            WHERE documento = :documento
            LIMIT 1
            '
        );

        $stmt->execute([
            ':documento' => $documento,
        ]);

        if ($stmt->fetchColumn()) {
            throw new RuntimeException(
                'Já existe uma empresa cadastrada com este documento.'
            );
        }
    }

    private function validarEmailUsuarioDisponivel(
        PDO $pdo,
        string $email
    ): void {
        $stmt = $pdo->prepare(
            '
            SELECT id
            FROM usuarios
            WHERE email = :email
            LIMIT 1
            '
        );

        $stmt->execute([
            ':email' => $email,
        ]);

        if ($stmt->fetchColumn()) {
            throw new RuntimeException(
                'Este e-mail já possui uma conta no sistema.'
            );
        }
    }

    private function validarAdministradorDisponivel(
        PDO $pdo,
        string $cpf,
        string $email
    ): void {
        $stmt = $pdo->prepare(
            '
            SELECT id
            FROM administradores
            WHERE cpf = :cpf
               OR email = :email
            LIMIT 1
            '
        );

        $stmt->execute([
            ':cpf' => $cpf,
            ':email' => $email,
        ]);

        if ($stmt->fetchColumn()) {
            throw new RuntimeException(
                'Este administrador já está vinculado a uma empresa.'
            );
        }
    }

    private function criarEmpresa(
        PDO $pdo,
        array $dados
    ): int {
        $slug = $this->gerarSlugUnico(
            $pdo,
            $dados['nome_fantasia']
        );

        $stmt = $pdo->prepare(
            '
            INSERT INTO empresas (
                nome_fantasia,
                razao_social,
                tipo_documento,
                documento,
                slug,
                email,
                telefone,
                whatsapp,
                cep,
                logradouro,
                numero,
                complemento,
                bairro,
                cidade,
                estado,
                ativo
            )
            VALUES (
                :nome_fantasia,
                :razao_social,
                :tipo_documento,
                :documento,
                :slug,
                :email,
                :telefone,
                :whatsapp,
                :cep,
                :logradouro,
                :numero,
                :complemento,
                :bairro,
                :cidade,
                :estado,
                0
            )
            '
        );

        $stmt->execute([
            ':nome_fantasia' => $dados['nome_fantasia'],
            ':razao_social' => $dados['razao_social'],
            ':tipo_documento' => $dados['tipo_documento'],
            ':documento' => $dados['documento'],
            ':slug' => $slug,
            ':email' => $dados['email_empresa'],
            ':telefone' => $dados['telefone_empresa'],
            ':whatsapp' => $dados['whatsapp_empresa'],
            ':cep' => $dados['cep'],
            ':logradouro' => $dados['logradouro'],
            ':numero' => $dados['numero'],
            ':complemento' => $dados['complemento'],
            ':bairro' => $dados['bairro'],
            ':cidade' => $dados['cidade'],
            ':estado' => $dados['estado'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function vincularSegmento(
        PDO $pdo,
        int $empresaId,
        int $segmentoId
    ): void {
        $stmt = $pdo->prepare(
            '
            INSERT INTO empresa_segmentos (
                empresa_id,
                segmento_id
            )
            VALUES (
                :empresa_id,
                :segmento_id
            )
            '
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':segmento_id' => $segmentoId,
        ]);
    }

    private function criarUsuario(
        PDO $pdo,
        string $email
    ): int {
        $stmt = $pdo->prepare(
            '
            INSERT INTO usuarios (
                email,
                senha_hash,
                ativo
            )
            VALUES (
                :email,
                NULL,
                0
            )
            '
        );

        $stmt->execute([
            ':email' => $email,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function criarAdministrador(
        PDO $pdo,
        int $empresaId,
        int $usuarioId,
        array $dados
    ): int {
        $telefone = $dados['admin_telefone'];

        $stmt = $pdo->prepare(
            '
            INSERT INTO administradores (
                empresa_id,
                usuario_id,
                nome_completo,
                cpf,
                email,
                telefone,
                whatsapp,
                ativo
            )
            VALUES (
                :empresa_id,
                :usuario_id,
                :nome_completo,
                :cpf,
                :email,
                :telefone,
                :whatsapp,
                0
            )
            '
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':usuario_id' => $usuarioId,
            ':nome_completo' => $dados['admin_nome'],
            ':cpf' => $dados['admin_cpf'],
            ':email' => $dados['admin_email'],
            ':telefone' => $telefone,
            ':whatsapp' => $telefone,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function gerarSlugUnico(
        PDO $pdo,
        string $nome
    ): string {
        $base = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $nome
        );

        if ($base === false) {
            $base = $nome;
        }

        $base = strtolower($base);

        $base = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $base
        ) ?? '';

        $base = trim($base, '-');

        if ($base === '') {
            $base = 'empresa';
        }

        $base = substr($base, 0, 150);

        $slug = $base;
        $sufixo = 2;

        while ($this->slugExiste($pdo, $slug)) {
            $final = '-' . $sufixo;

            $slug =
                substr(
                    $base,
                    0,
                    160 - strlen($final)
                )
                . $final;

            $sufixo++;
        }

        return $slug;
    }

    private function slugExiste(
        PDO $pdo,
        string $slug
    ): bool {
        $stmt = $pdo->prepare(
            '
            SELECT id
            FROM empresas
            WHERE slug = :slug
            LIMIT 1
            '
        );

        $stmt->execute([
            ':slug' => $slug,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function validarCPF(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf)) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;

            for ($i = 0; $i < $t; $i++) {
                $soma +=
                    (int) $cpf[$i]
                    * (($t + 1) - $i);
            }

            $digito =
                ((10 * $soma) % 11) % 10;

            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    private function validarCNPJ(string $cnpj): bool
    {
        if (!preg_match('/^\d{14}$/', $cnpj)) {
            return false;
        }

        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos1 = [
            5, 4, 3, 2,
            9, 8, 7, 6,
            5, 4, 3, 2,
        ];

        $pesos2 = [
            6, 5, 4, 3,
            2, 9, 8, 7,
            6, 5, 4, 3, 2,
        ];

        $calcularDigito = static function (
            string $numero,
            array $pesos
        ): int {
            $soma = 0;

            foreach ($pesos as $i => $peso) {
                $soma +=
                    (int) $numero[$i]
                    * $peso;
            }

            $resto = $soma % 11;

            return $resto < 2
                ? 0
                : 11 - $resto;
        };

        $digito1 = $calcularDigito(
            $cnpj,
            $pesos1
        );

        if ((int) $cnpj[12] !== $digito1) {
            return false;
        }

        $digito2 = $calcularDigito(
            $cnpj,
            $pesos2
        );

        return (int) $cnpj[13] === $digito2;
    }

    private function somenteNumeros(
        string $valor
    ): string {
        return preg_replace(
            '/\D+/',
            '',
            $valor
        ) ?? '';
    }

    private function nullSeVazio(
        mixed $valor
    ): ?string {
        $valor = trim(
            (string) ($valor ?? '')
        );

        return $valor === ''
            ? null
            : $valor;
    }

    private function normalizarEmail(
        mixed $valor
    ): ?string {
        $email = $this->nullSeVazio(
            $valor
        );

        return $email === null
            ? null
            : strtolower($email);
    }
}