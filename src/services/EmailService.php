<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/autoload.php';

final class EmailService
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $secure;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $this->host       = $this->env('MAIL_HOST');
        $this->port       = (int) $this->env('MAIL_PORT', '465');
        $this->username   = $this->env('MAIL_USERNAME');
        $this->password   = $this->env('MAIL_PASSWORD');
        $this->secure     = strtolower($this->env('MAIL_SMTP_SECURE', 'ssl'));
        $this->fromEmail  = $this->env('MAIL_FROM_EMAIL', $this->username);
        $this->fromName   = $this->env('MAIL_FROM_NAME', 'Salão Agenda');

        $this->validarConfiguracao();
    }

    /**
     * Envia um e-mail utilizando SMTP.
     *
     * @throws RuntimeException
     */
    public function enviar(
        string $email,
        string $nome,
        string $assunto,
        string $html,
        string $texto = ''
    ): void {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('E-mail do destinatário inválido.');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();

            $mail->Host       = $this->host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->username;
            $mail->Password   = $this->password;
            $mail->Port       = $this->port;

            $this->configurarSeguranca($mail);

            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->setFrom(
                $this->fromEmail,
                $this->fromName
            );

            $mail->addAddress(
                $email,
                $nome
            );

            $mail->isHTML(true);
            // $mail->SMTPDebug = 2;
            // $mail->Debugoutput = 'html';

            $mail->Subject = $assunto;
            $mail->Body    = $html;
            $mail->AltBody = $texto !== ''
                ? $texto
                : trim(strip_tags($html));

            $mail->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException(
                'Não foi possível enviar o e-mail: ' . $mail->ErrorInfo,
                0,
                $e
            );
        }
    }

    /**
     * Configura SSL, TLS ou conexão sem criptografia.
     */
    private function configurarSeguranca(PHPMailer $mail): void
    {
        switch ($this->secure) {
            case 'ssl':
            case 'smtps':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                break;

            case 'tls':
            case 'starttls':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                break;

            case '':
            case 'none':
                break;

            default:
                throw new RuntimeException(
                    'Valor inválido para MAIL_SMTP_SECURE.'
                );
        }
    }

    /**
     * Valida as configurações essenciais de SMTP.
     */
    private function validarConfiguracao(): void
    {
        if ($this->host === '') {
            throw new RuntimeException('MAIL_HOST não configurado.');
        }

        if ($this->username === '') {
            throw new RuntimeException('MAIL_USERNAME não configurado.');
        }

        if ($this->password === '') {
            throw new RuntimeException('MAIL_PASSWORD não configurado.');
        }

        if (
            $this->fromEmail === '' ||
            !filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException(
                'MAIL_FROM_EMAIL não configurado ou inválido.'
            );
        }

        if ($this->port <= 0 || $this->port > 65535) {
            throw new RuntimeException('MAIL_PORT inválido.');
        }
    }

    /**
     * Recupera uma variável de ambiente.
     */
    private function env(string $chave, string $padrao = ''): string
    {
        $valor = $_ENV[$chave]
            ?? $_SERVER[$chave]
            ?? getenv($chave);

        if ($valor === false || $valor === null || $valor === '') {
            return $padrao;
        }

        return trim((string) $valor);
    }
}