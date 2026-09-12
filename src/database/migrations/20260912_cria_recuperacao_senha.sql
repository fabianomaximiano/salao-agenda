CREATE TABLE IF NOT EXISTS `recuperacao_senha_solicitacoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email_hash` char(64) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `tentativas` tinyint unsigned NOT NULL DEFAULT '0',
  `janela_iniciada_em` datetime NOT NULL,
  `ultima_solicitacao_em` datetime NOT NULL,
  `bloqueado_ate` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recuperacao_senha_email_ip` (`email_hash`,`ip_hash`),
  KEY `idx_recuperacao_senha_bloqueado_ate` (`bloqueado_ate`),
  KEY `idx_recuperacao_senha_atualizado_em` (`atualizado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `usuario_tokens_recuperacao_senha` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `solicitado_ip_hash` char(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `utilizado_em` datetime DEFAULT NULL,
  `revogado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_tokens_recuperacao_hash` (`token_hash`),
  KEY `idx_usuario_tokens_recuperacao_usuario_status` (`usuario_id`,`utilizado_em`,`revogado_em`,`expira_em`),
  KEY `idx_usuario_tokens_recuperacao_expiracao` (`expira_em`),
  CONSTRAINT `fk_usuario_tokens_recuperacao_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
