CREATE TABLE `usuario_tentativas_login` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `tentativas` tinyint unsigned NOT NULL DEFAULT '0',
  `bloqueado_ate` datetime DEFAULT NULL,
  `ultimo_erro_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_tentativas_login_usuario_ip` (`usuario_id`,`ip_hash`),
  KEY `idx_usuario_tentativas_login_bloqueio` (`bloqueado_ate`),
  CONSTRAINT `fk_usuario_tentativas_login_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
