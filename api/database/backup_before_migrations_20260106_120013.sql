-- MySQL dump 10.13  Distrib 8.0.44, for Linux (aarch64)
--
-- Host: localhost    Database: appcheckin
-- ------------------------------------------------------
-- Server version	8.0.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `checkins`
--

DROP TABLE IF EXISTS `checkins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checkins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `horario_id` int NOT NULL,
  `data_checkin` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `registrado_por_admin` tinyint(1) DEFAULT '0' COMMENT 'TRUE se admin fez check-in manual do aluno',
  `admin_id` int DEFAULT NULL COMMENT 'ID do admin que registrou (se aplicÃ¡vel)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_horario` (`usuario_id`,`horario_id`),
  KEY `idx_checkins_usuario` (`usuario_id`),
  KEY `idx_checkins_horario` (`horario_id`),
  KEY `idx_checkins_horario_usuario` (`horario_id`,`usuario_id`),
  KEY `fk_checkin_admin` (`admin_id`),
  KEY `idx_checkins_admin` (`registrado_por_admin`,`admin_id`),
  CONSTRAINT `checkins_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `checkins_ibfk_2` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_checkin_admin` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `checkins`
--

LOCK TABLES `checkins` WRITE;
/*!40000 ALTER TABLE `checkins` DISABLE KEYS */;
/*!40000 ALTER TABLE `checkins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contas_receber`
--

DROP TABLE IF EXISTS `contas_receber`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contas_receber` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `plano_id` int NOT NULL,
  `historico_plano_id` int DEFAULT NULL COMMENT 'ReferÃªncia ao histÃ³rico que gerou esta conta',
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','vencido','cancelado') DEFAULT 'pendente',
  `forma_pagamento_id` int DEFAULT NULL,
  `observacoes` text,
  `referencia_mes` varchar(7) DEFAULT NULL COMMENT 'Formato YYYY-MM para controle mensal',
  `recorrente` tinyint(1) DEFAULT '0' COMMENT 'Se true, gera prÃ³xima parcela ao dar baixa',
  `intervalo_dias` int DEFAULT NULL COMMENT 'Dias para gerar prÃ³xima parcela (30, 90, 180, 365)',
  `proxima_conta_id` int DEFAULT NULL COMMENT 'ID da prÃ³xima conta gerada automaticamente',
  `conta_origem_id` int DEFAULT NULL COMMENT 'ID da conta que originou esta (para rastreamento)',
  `criado_por` int DEFAULT NULL COMMENT 'ID do admin que criou',
  `baixa_por` int DEFAULT NULL COMMENT 'ID do admin que deu baixa',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `valor_liquido` decimal(10,2) DEFAULT NULL COMMENT 'Valor apÃ³s desconto da operadora',
  `valor_desconto` decimal(10,2) DEFAULT NULL COMMENT 'Valor do desconto da operadora',
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `historico_plano_id` (`historico_plano_id`),
  KEY `proxima_conta_id` (`proxima_conta_id`),
  KEY `conta_origem_id` (`conta_origem_id`),
  KEY `criado_por` (`criado_por`),
  KEY `baixa_por` (`baixa_por`),
  KEY `idx_tenant_usuario` (`tenant_id`,`usuario_id`),
  KEY `idx_status` (`status`),
  KEY `idx_data_vencimento` (`data_vencimento`),
  KEY `idx_plano` (`plano_id`),
  KEY `idx_referencia` (`referencia_mes`),
  KEY `idx_conta_forma_pagamento` (`forma_pagamento_id`),
  CONSTRAINT `contas_receber_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contas_receber_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contas_receber_ibfk_3` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `contas_receber_ibfk_4` FOREIGN KEY (`historico_plano_id`) REFERENCES `historico_planos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contas_receber_ibfk_5` FOREIGN KEY (`proxima_conta_id`) REFERENCES `contas_receber` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contas_receber_ibfk_6` FOREIGN KEY (`conta_origem_id`) REFERENCES `contas_receber` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contas_receber_ibfk_7` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contas_receber_ibfk_8` FOREIGN KEY (`baixa_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contas_receber_ibfk_9` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contas_receber`
--

LOCK TABLES `contas_receber` WRITE;
/*!40000 ALTER TABLE `contas_receber` DISABLE KEYS */;
/*!40000 ALTER TABLE `contas_receber` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `atualizar_status_vencido` BEFORE UPDATE ON `contas_receber` FOR EACH ROW BEGIN
    IF NEW.status = 'pendente' AND NEW.data_vencimento < CURDATE() THEN
        SET NEW.status = 'vencido';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `dias`
--

DROP TABLE IF EXISTS `dias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL DEFAULT '1',
  `data` date NOT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data` (`data`),
  KEY `idx_dias_data` (`data`),
  KEY `idx_tenant_dias` (`tenant_id`),
  KEY `idx_tenant_data` (`tenant_id`,`data`),
  CONSTRAINT `fk_dias_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dias`
--

LOCK TABLES `dias` WRITE;
/*!40000 ALTER TABLE `dias` DISABLE KEYS */;
/*!40000 ALTER TABLE `dias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `formas_pagamento`
--

DROP TABLE IF EXISTS `formas_pagamento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `formas_pagamento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percentual_desconto` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Percentual que fica com operadora (ex: 3.50 para 3.5%)',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_forma_pagamento_nome` (`nome`),
  KEY `idx_forma_pagamento_ativo` (`ativo`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `formas_pagamento`
--

LOCK TABLES `formas_pagamento` WRITE;
/*!40000 ALTER TABLE `formas_pagamento` DISABLE KEYS */;
INSERT INTO `formas_pagamento` VALUES (1,'Dinheiro','Pagamento em dinheiro',0.00,1,'2025-11-25 00:33:01','2026-01-06 01:55:39'),(2,'Pix','Pagamento via PIX',0.00,1,'2025-11-25 00:33:01','2026-01-06 01:55:39'),(3,'Débito',NULL,2.50,1,'2025-11-25 00:33:01','2025-11-25 00:39:41'),(4,'Crédito á  vista',NULL,3.50,1,'2025-11-25 00:33:01','2025-11-25 00:39:33'),(5,'Crédito parcelado 2x',NULL,4.50,1,'2025-11-25 00:33:01','2025-11-25 00:39:48'),(6,'Crédito parcelado 3x',NULL,5.00,1,'2025-11-25 00:33:01','2025-11-25 00:39:53'),(7,'Transferência bancária',NULL,0.00,1,'2025-11-25 00:33:01','2025-11-25 00:40:12'),(8,'Boleto','Boleto bancário',2.00,1,'2025-11-25 00:33:01','2026-01-06 01:55:39'),(9,'Cartão','Cartão de crédito ou débito',0.00,1,'2026-01-06 01:55:39','2026-01-06 01:55:39'),(10,'Operadora','Pagamento via operadora de cartões',0.00,1,'2026-01-06 01:55:39','2026-01-06 01:55:39'),(11,'Transferência','Transferência bancária',0.00,1,'2026-01-06 01:55:39','2026-01-06 01:55:39'),(12,'Cheque','Pagamento via cheque',0.00,1,'2026-01-06 01:55:39','2026-01-06 01:55:39'),(13,'Crédito Loja','Crédito da loja/academia',0.00,1,'2026-01-06 01:55:39','2026-01-06 01:55:39');
/*!40000 ALTER TABLE `formas_pagamento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historico_planos`
--

DROP TABLE IF EXISTS `historico_planos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historico_planos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `plano_anterior_id` int DEFAULT NULL,
  `plano_novo_id` int DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_vencimento` date DEFAULT NULL,
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `motivo` varchar(100) DEFAULT NULL COMMENT 'Tipos: novo (primeiro plano), renovacao (mesmo plano), upgrade (plano melhor), downgrade (plano menor), cancelamento (removeu plano)',
  `observacoes` text,
  `criado_por` int DEFAULT NULL COMMENT 'ID do admin que fez a alteraÃ§Ã£o',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `criado_por` (`criado_por`),
  KEY `idx_usuario_historico` (`usuario_id`),
  KEY `idx_plano_anterior` (`plano_anterior_id`),
  KEY `idx_plano_novo` (`plano_novo_id`),
  KEY `idx_data_inicio` (`data_inicio`),
  CONSTRAINT `historico_planos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `historico_planos_ibfk_2` FOREIGN KEY (`plano_anterior_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `historico_planos_ibfk_3` FOREIGN KEY (`plano_novo_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `historico_planos_ibfk_4` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historico_planos`
--

LOCK TABLES `historico_planos` WRITE;
/*!40000 ALTER TABLE `historico_planos` DISABLE KEYS */;
/*!40000 ALTER TABLE `historico_planos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horarios`
--

DROP TABLE IF EXISTS `horarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `horarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dia_id` int NOT NULL,
  `hora` time NOT NULL,
  `horario_inicio` time NOT NULL DEFAULT '06:00:00',
  `horario_fim` time NOT NULL DEFAULT '07:00:00',
  `limite_alunos` int NOT NULL DEFAULT '30',
  `tolerancia_minutos` int NOT NULL DEFAULT '10',
  `tolerancia_antes_minutos` int NOT NULL DEFAULT '480',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dia_hora` (`dia_id`,`hora`),
  KEY `idx_horarios_dia` (`dia_id`),
  KEY `idx_horarios_dia_ativo` (`dia_id`,`ativo`),
  KEY `idx_tenant_horarios` (`dia_id`),
  CONSTRAINT `horarios_ibfk_1` FOREIGN KEY (`dia_id`) REFERENCES `dias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horarios`
--

LOCK TABLES `horarios` WRITE;
/*!40000 ALTER TABLE `horarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `horarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `matriculas`
--

DROP TABLE IF EXISTS `matriculas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matriculas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `plano_id` int NOT NULL,
  `data_matricula` date NOT NULL,
  `data_inicio` date NOT NULL,
  `data_vencimento` date NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `status` enum('ativa','vencida','cancelada','finalizada') COLLATE utf8mb4_unicode_ci DEFAULT 'ativa',
  `motivo` enum('nova','renovacao','upgrade','downgrade') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nova',
  `matricula_anterior_id` int DEFAULT NULL,
  `plano_anterior_id` int DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `criado_por` int DEFAULT NULL,
  `cancelado_por` int DEFAULT NULL,
  `data_cancelamento` date DEFAULT NULL,
  `motivo_cancelamento` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_usuario` (`tenant_id`,`usuario_id`),
  KEY `idx_status` (`status`),
  KEY `idx_data_vencimento` (`data_vencimento`),
  KEY `idx_plano` (`plano_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `matricula_anterior_id` (`matricula_anterior_id`),
  KEY `plano_anterior_id` (`plano_anterior_id`),
  KEY `criado_por` (`criado_por`),
  KEY `cancelado_por` (`cancelado_por`),
  CONSTRAINT `matriculas_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matriculas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matriculas_ibfk_3` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `matriculas_ibfk_4` FOREIGN KEY (`matricula_anterior_id`) REFERENCES `matriculas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `matriculas_ibfk_5` FOREIGN KEY (`plano_anterior_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `matriculas_ibfk_6` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `matriculas_ibfk_7` FOREIGN KEY (`cancelado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registra as matrÃ­culas dos alunos nos planos - separado do cadastro bÃ¡sico';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `matriculas`
--

LOCK TABLES `matriculas` WRITE;
/*!40000 ALTER TABLE `matriculas` DISABLE KEYS */;
/*!40000 ALTER TABLE `matriculas` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `update_matricula_vencida` BEFORE UPDATE ON `matriculas` FOR EACH ROW BEGIN
    IF NEW.data_vencimento < CURDATE() AND NEW.status = 'ativa' THEN
        SET NEW.status = 'vencida';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `modalidades`
--

DROP TABLE IF EXISTS `modalidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modalidades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `valor_mensalidade` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cor` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cor em hexadecimal para identificação visual',
  `icone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nome do ícone para exibição',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_modalidade` (`tenant_id`,`nome`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_ativo` (`ativo`),
  CONSTRAINT `modalidades_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modalidades`
--

LOCK TABLES `modalidades` WRITE;
/*!40000 ALTER TABLE `modalidades` DISABLE KEYS */;
INSERT INTO `modalidades` VALUES (1,1,'CrossFit',NULL,0.00,'#f97316','activity',1,'2026-01-06 01:25:28','2026-01-06 01:30:46'),(2,4,'Natação',NULL,0.00,'#f97316','activity',1,'2026-01-06 01:25:28','2026-01-06 01:30:55');
/*!40000 ALTER TABLE `modalidades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagamentos_contrato`
--

DROP TABLE IF EXISTS `pagamentos_contrato`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagamentos_contrato` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contrato_id` int NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status_pagamento_id` int NOT NULL DEFAULT '1',
  `forma_pagamento_id` int DEFAULT NULL,
  `comprovante` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Caminho do arquivo de comprovante',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contrato` (`contrato_id`),
  KEY `idx_status` (`status_pagamento_id`),
  KEY `idx_vencimento` (`data_vencimento`),
  KEY `idx_pagamento` (`data_pagamento`),
  KEY `idx_forma_pagamento` (`forma_pagamento_id`),
  CONSTRAINT `fk_pagamento_forma_pagamento` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pagamentos_contrato_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `tenant_planos_sistema` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pagamentos_contrato_ibfk_2` FOREIGN KEY (`status_pagamento_id`) REFERENCES `status_pagamento` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagamentos_contrato`
--

LOCK TABLES `pagamentos_contrato` WRITE;
/*!40000 ALTER TABLE `pagamentos_contrato` DISABLE KEYS */;
INSERT INTO `pagamentos_contrato` VALUES (3,2,250.00,'2026-01-05','2026-01-05',2,1,NULL,'Terceiro pagamento do contrato','2026-01-05 17:58:39','2026-01-05 18:42:25'),(4,2,250.00,'2026-04-05','2026-01-05',2,1,'','','2026-01-05 18:26:05','2026-01-05 18:58:37'),(5,2,250.00,'2026-02-05','2026-01-05',2,1,'','','2026-01-05 18:26:41','2026-01-05 18:58:15'),(6,2,250.00,'2026-03-05','2026-01-05',2,1,'','','2026-01-05 18:26:41','2026-01-05 18:58:25'),(7,2,250.00,'2026-05-05','2026-01-05',2,4,'','','2026-01-05 18:58:37','2026-01-06 00:10:57'),(8,2,250.00,'2026-06-05','2026-01-05',2,4,'','Baixa Manual','2026-01-05 19:02:06','2026-01-06 00:11:00'),(9,2,250.00,'2026-07-05','2026-01-05',2,4,'','Baixa Manual','2026-01-05 19:04:12','2026-01-06 00:11:03'),(10,2,250.00,'2026-08-05','2026-01-06',2,4,'','Baixa Manual','2026-01-05 20:00:57','2026-01-06 00:10:16'),(11,2,250.00,'2026-09-05','2026-01-06',2,2,'','Baixa Manual','2026-01-06 00:10:16','2026-01-06 02:49:51'),(12,2,250.00,'2026-10-05','2026-01-06',2,13,'','Baixa Manual','2026-01-06 02:49:51','2026-01-06 02:53:14'),(13,2,250.00,'2026-11-05','2026-01-06',2,9,'','Baixa Manual','2026-01-06 02:53:14','2026-01-06 02:53:26'),(14,2,250.00,'2026-12-05','2026-01-06',2,1,'','Baixa Manual','2026-01-06 02:53:26','2026-01-06 14:11:55'),(15,2,250.00,'2027-01-05',NULL,1,NULL,NULL,'Pagamento gerado automaticamente após confirmação','2026-01-06 14:11:55','2026-01-06 14:11:55');
/*!40000 ALTER TABLE `pagamentos_contrato` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planos`
--

DROP TABLE IF EXISTS `planos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `planos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `modalidade_id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `valor` decimal(10,2) NOT NULL,
  `duracao_dias` int NOT NULL COMMENT 'DuraÃ§Ã£o em dias (30, 90, 365, etc)',
  `checkins_mensais` int DEFAULT NULL COMMENT 'Limite de checkins por mÃªs (NULL = ilimitado)',
  `max_alunos` int DEFAULT NULL COMMENT 'Capacidade máxima de alunos (NULL = ilimitado)',
  `ativo` tinyint(1) DEFAULT '1',
  `atual` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indica se o plano estÃ¡ disponÃ­vel para novos contratos',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_planos` (`tenant_id`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_plano_modalidade` (`modalidade_id`),
  CONSTRAINT `fk_plano_modalidade` FOREIGN KEY (`modalidade_id`) REFERENCES `modalidades` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `planos_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planos`
--

LOCK TABLES `planos` WRITE;
/*!40000 ALTER TABLE `planos` DISABLE KEYS */;
INSERT INTO `planos` VALUES (13,4,1,'Max300','Plano mensal com 12 checkins',300.00,30,12,300,1,1,'2025-12-28 00:45:03','2026-01-06 01:50:49'),(14,4,1,'Max100','Plano mensal com checkins ilimitados',100.00,30,NULL,100,1,1,'2025-12-28 00:45:03','2026-01-06 01:50:52'),(15,4,1,'Max200','Plano trimestral com checkins ilimitados',200.00,90,NULL,200,1,1,'2025-12-28 00:45:03','2026-01-06 01:50:55'),(16,4,1,'Max400','Plano anual com checkins ilimitados e desconto',400.00,365,NULL,400,1,1,'2025-12-28 00:45:03','2026-01-06 01:50:58');
/*!40000 ALTER TABLE `planos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planos_sistema`
--

DROP TABLE IF EXISTS `planos_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `planos_sistema` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome do plano (ex: Starter, Professional, Enterprise)',
  `descricao` text COLLATE utf8mb4_unicode_ci COMMENT 'DescriÃ§Ã£o detalhada do plano',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor mensal do plano',
  `duracao_dias` int NOT NULL DEFAULT '30' COMMENT 'DuraÃ§Ã£o em dias (30, 90, 365, etc)',
  `max_alunos` int DEFAULT NULL COMMENT 'Capacidade mÃ¡xima de alunos (NULL = ilimitado)',
  `max_admins` int DEFAULT '1' COMMENT 'NÃºmero mÃ¡ximo de administradores',
  `features` json DEFAULT NULL COMMENT 'Recursos inclusos no plano em formato JSON',
  `ativo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Plano ativo para venda',
  `atual` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Plano atual (disponÃ­vel para novos contratos)',
  `ordem` int DEFAULT '0' COMMENT 'Ordem de exibiÃ§Ã£o',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_disponiveis` (`atual`,`ativo`),
  KEY `idx_ordem` (`ordem`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planos de assinatura do sistema que as academias contratam';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planos_sistema`
--

LOCK TABLES `planos_sistema` WRITE;
/*!40000 ALTER TABLE `planos_sistema` DISABLE KEYS */;
INSERT INTO `planos_sistema` VALUES (1,'Starter','Plano inicial para pequenas academias',99.00,30,50,1,'{\"checkins\": true, \"app_mobile\": true, \"relatorios_basicos\": true}',1,1,1,'2025-12-29 02:08:51','2025-12-29 02:08:51'),(2,'Professional','Plano completo para academias em crescimento',199.00,30,150,3,'{\"turmas\": true, \"checkins\": true, \"app_mobile\": true, \"multi_admin\": true, \"relatorios_avancados\": true}',1,1,2,'2025-12-29 02:08:51','2026-01-05 17:12:58'),(3,'Enterprise','Plano ilimitado para grandes academias',250.00,30,100,1,'{\"turmas\": true, \"checkins\": true, \"api_access\": true, \"app_mobile\": true, \"multi_admin\": true, \"suporte_prioritario\": true, \"relatorios_avancados\": true}',1,1,3,'2025-12-29 02:08:51','2026-01-05 14:40:47');
/*!40000 ALTER TABLE `planos_sistema` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'aluno','Usuário comum com acesso ao app','2025-11-24 02:30:10','2025-11-25 00:46:50'),(2,'admin','Administrador da academia','2025-11-24 02:30:10','2025-11-24 02:30:10'),(3,'super_admin','Super administrador com acesso total','2025-11-24 02:30:10','2025-11-24 02:30:10');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `status_conta`
--

DROP TABLE IF EXISTS `status_conta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_conta` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cor` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Cor para exibiÃ§Ã£o no frontend',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_status_conta_nome` (`nome`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `status_conta`
--

LOCK TABLES `status_conta` WRITE;
/*!40000 ALTER TABLE `status_conta` DISABLE KEYS */;
INSERT INTO `status_conta` VALUES (1,'pendente','warning','2025-11-25 00:33:01'),(2,'pago','success','2025-11-25 00:33:01'),(3,'vencido','danger','2025-11-25 00:33:01'),(4,'cancelado','medium','2025-11-25 00:33:01');
/*!40000 ALTER TABLE `status_conta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `status_contrato`
--

DROP TABLE IF EXISTS `status_contrato`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_contrato` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome do status',
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'DescriÃ§Ã£o detalhada do status',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Status possÃ­veis para contratos de planos (tenant_planos_sistema)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `status_contrato`
--

LOCK TABLES `status_contrato` WRITE;
/*!40000 ALTER TABLE `status_contrato` DISABLE KEYS */;
INSERT INTO `status_contrato` VALUES (1,'Ativo','Contrato ativo e em vigÃªncia','2026-01-05 14:49:26','2026-01-05 14:49:26'),(2,'Pendente','Contrato aguardando aprovaÃ§Ã£o ou pagamento','2026-01-05 14:49:26','2026-01-05 14:49:26'),(3,'Cancelado','Contrato cancelado pelo cliente ou administrador','2026-01-05 14:49:26','2026-01-05 14:49:26'),(4,'Bloqueado','Contrato bloqueado por falta de pagamento','2026-01-05 17:58:06','2026-01-05 17:58:06');
/*!40000 ALTER TABLE `status_contrato` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `status_pagamento`
--

DROP TABLE IF EXISTS `status_pagamento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_pagamento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `descricao` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `status_pagamento`
--

LOCK TABLES `status_pagamento` WRITE;
/*!40000 ALTER TABLE `status_pagamento` DISABLE KEYS */;
INSERT INTO `status_pagamento` VALUES (1,'Aguardando','Pagamento aguardando confirmaÃ§Ã£o','2026-01-05 17:57:49','2026-01-05 17:57:49'),(2,'Pago','Pagamento confirmado','2026-01-05 17:57:49','2026-01-05 17:57:49'),(3,'Atrasado','Pagamento em atraso','2026-01-05 17:57:49','2026-01-05 17:57:49'),(4,'Cancelado','Pagamento cancelado','2026-01-05 17:57:49','2026-01-05 17:57:49');
/*!40000 ALTER TABLE `status_pagamento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_formas_pagamento`
--

DROP TABLE IF EXISTS `tenant_formas_pagamento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_formas_pagamento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `forma_pagamento_id` int NOT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `taxa_percentual` decimal(5,2) DEFAULT '0.00' COMMENT 'Taxa em % cobrada pela operadora (ex: 3.99 = 3.99%)',
  `taxa_fixa` decimal(10,2) DEFAULT '0.00' COMMENT 'Taxa fixa por transaÃ§Ã£o em R$ (ex: 3.50)',
  `aceita_parcelamento` tinyint(1) DEFAULT '0' COMMENT '1 = permite parcelar',
  `parcelas_minimas` int DEFAULT '1' COMMENT 'MÃ­nimo de parcelas permitidas',
  `parcelas_maximas` int DEFAULT '12' COMMENT 'MÃ¡ximo de parcelas permitidas',
  `juros_parcelamento` decimal(5,2) DEFAULT '0.00' COMMENT 'Juros ao mÃªs em % (ex: 1.99 = 1.99%)',
  `parcelas_sem_juros` int DEFAULT '1' COMMENT 'Quantidade de parcelas sem juros',
  `dias_compensacao` int DEFAULT '0' COMMENT 'Dias Ãºteis para compensaÃ§Ã£o do pagamento',
  `valor_minimo` decimal(10,2) DEFAULT '0.00' COMMENT 'Valor mÃ­nimo para aceitar esta forma',
  `observacoes` text COLLATE utf8mb4_unicode_ci COMMENT 'ObservaÃ§Ãµes internas sobre esta forma de pagamento',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_forma` (`tenant_id`,`forma_pagamento_id`),
  KEY `forma_pagamento_id` (`forma_pagamento_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_ativo` (`ativo`),
  CONSTRAINT `tenant_formas_pagamento_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_formas_pagamento_ibfk_2` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_formas_pagamento`
--

LOCK TABLES `tenant_formas_pagamento` WRITE;
/*!40000 ALTER TABLE `tenant_formas_pagamento` DISABLE KEYS */;
INSERT INTO `tenant_formas_pagamento` VALUES (1,4,1,1,0.00,0.00,0,1,1,0.00,1,0,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(2,4,2,1,0.00,0.00,0,1,1,0.00,1,0,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(3,4,3,1,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(4,4,4,1,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(5,4,5,1,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(6,4,6,1,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(7,4,7,1,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(8,4,8,1,0.00,3.50,0,1,1,0.00,1,3,10.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(9,4,9,1,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(10,4,10,1,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(11,4,11,1,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(12,4,12,1,0.00,0.00,0,1,1,0.00,1,3,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(13,4,13,1,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:03:13','2026-01-06 02:03:13'),(16,1,1,1,0.00,0.00,0,1,1,0.00,1,0,0.00,NULL,'2026-01-06 02:16:47','2026-01-06 03:02:15'),(17,1,2,1,0.00,0.00,0,1,1,0.00,1,0,0.00,NULL,'2026-01-06 02:16:47','2026-01-06 03:02:44'),(18,1,3,0,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:16:47','2026-01-06 02:44:20'),(19,1,4,0,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:16:47','2026-01-06 03:02:12'),(20,1,5,0,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:16:47','2026-01-06 02:43:30'),(21,1,6,0,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:16:47','2026-01-06 02:43:29'),(22,1,7,0,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:16:47','2026-01-06 02:44:03'),(23,1,8,0,0.00,0.00,0,1,1,0.00,1,3,10.00,NULL,'2026-01-06 02:16:47','2026-01-06 03:02:48'),(24,1,9,1,0.00,0.00,1,1,3,3.00,1,1,100.00,NULL,'2026-01-06 02:16:47','2026-01-06 03:00:43'),(25,1,10,0,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:16:47','2026-01-06 02:44:10'),(26,1,11,0,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:16:47','2026-01-06 02:44:06'),(27,1,12,1,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:16:47','2026-01-06 03:02:02'),(28,1,13,0,0.00,0.00,0,1,1,0.00,1,1,0.00,NULL,'2026-01-06 02:16:47','2026-01-06 03:00:49');
/*!40000 ALTER TABLE `tenant_formas_pagamento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenant_planos_sistema`
--

DROP TABLE IF EXISTS `tenant_planos_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_planos_sistema` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `plano_id` int NOT NULL,
  `plano_sistema_id` int DEFAULT NULL,
  `status_id` int NOT NULL,
  `data_inicio` date NOT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`),
  KEY `idx_datas` (`data_inicio`),
  KEY `idx_plano_sistema` (`plano_sistema_id`),
  KEY `tenant_planos_sistema_plano_id_fk` (`plano_id`),
  KEY `fk_tenant_planos_status` (`status_id`),
  CONSTRAINT `tenant_planos_sistema_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_planos_sistema_ibfk_3` FOREIGN KEY (`plano_sistema_id`) REFERENCES `planos_sistema` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tenant_planos_sistema_plano_id_fk` FOREIGN KEY (`plano_id`) REFERENCES `planos_sistema` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contratos das academias com planos do sistema. Cada academia pode ter apenas um contrato ativo por vez, mas mantÃ©m histÃ³rico de contratos anteriores.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_planos_sistema`
--

LOCK TABLES `tenant_planos_sistema` WRITE;
/*!40000 ALTER TABLE `tenant_planos_sistema` DISABLE KEYS */;
INSERT INTO `tenant_planos_sistema` VALUES (2,4,3,3,1,'2026-01-05','Contrato de teste com pagamentos','2026-01-05 17:58:39','2026-01-05 18:42:25');
/*!40000 ALTER TABLE `tenant_planos_sistema` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `telefone` varchar(50) DEFAULT NULL,
  `responsavel_nome` varchar(255) DEFAULT NULL,
  `responsavel_cpf` varchar(14) DEFAULT NULL,
  `responsavel_telefone` varchar(20) DEFAULT NULL,
  `responsavel_email` varchar(255) DEFAULT NULL,
  `endereco` text,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `plano_id` int DEFAULT NULL,
  `data_inicio_plano` date DEFAULT NULL,
  `data_fim_plano` date DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_tenants_cnpj` (`cnpj`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'Sistema AppCheckin','sistema-appcheckin','admin@appcheckin.com','','(82) 9 8837-6381',NULL,NULL,NULL,NULL,NULL,'57307200','Rua Severino Catanho','134','','Baixa Grande','Arapiraca','AL',NULL,NULL,NULL,1,'2025-12-26 17:58:37','2025-12-28 19:27:43'),(4,'Sporte e Saúde - Baixa Grande','sporte-e-saude-baixa-grande','rodolfo@gmail.com','68521091000144','82995289855','Rodolfo ','39156933088','82988859292','rodolfo.esporte@gmail.com',NULL,'57306250','Rua Antônio Bernardino de Sena','500','De Esquina','Eldorado','Arapiraca','AL',NULL,NULL,NULL,1,'2026-01-05 12:52:11','2026-01-05 13:23:32'),(5,'Fitpro 7','fitpro-7','fitpro@gmail.com','75312211000169','11335359022','Jonas Amaro','59403688084','11998025255','jonas.fitpro@gmail.com',NULL,'03580070','Praça Desterro de Malta','7895','','Jardim Fernandes','São Paulo','SP',NULL,NULL,NULL,1,'2026-01-06 13:05:43','2026-01-06 13:05:43');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_tenant`
--

DROP TABLE IF EXISTS `usuario_tenant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_tenant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `tenant_id` int NOT NULL,
  `plano_id` int DEFAULT NULL,
  `status` enum('ativo','inativo','suspenso','cancelado') DEFAULT 'ativo',
  `data_inicio` date NOT NULL DEFAULT (curdate()),
  `data_fim` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_tenant` (`usuario_id`,`tenant_id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `plano_id` (`plano_id`),
  CONSTRAINT `usuario_tenant_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuario_tenant_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuario_tenant_ibfk_3` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_tenant`
--

LOCK TABLES `usuario_tenant` WRITE;
/*!40000 ALTER TABLE `usuario_tenant` DISABLE KEYS */;
INSERT INTO `usuario_tenant` VALUES (1,1,1,NULL,'inativo','2025-12-26','2025-12-28','2025-12-26 17:58:37','2025-12-28 18:32:51'),(8,8,4,NULL,'ativo','2026-01-05',NULL,'2026-01-05 12:52:11','2026-01-05 12:52:11'),(9,9,5,NULL,'ativo','2026-01-06',NULL,'2026-01-06 13:05:43','2026-01-06 13:05:43');
/*!40000 ALTER TABLE `usuario_tenant` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL DEFAULT '1',
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `email_global` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `role_id` int DEFAULT '1',
  `plano_id` int DEFAULT NULL,
  `data_vencimento_plano` date DEFAULT NULL,
  `foto_base64` longtext,
  `senha_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_usuarios` (`tenant_id`),
  KEY `idx_tenant_email` (`tenant_id`,`email`),
  KEY `plano_id` (`plano_id`),
  KEY `role_id` (`role_id`),
  KEY `idx_email_global` (`email_global`),
  KEY `idx_usuarios_ativo` (`ativo`),
  KEY `idx_usuarios_cpf` (`cpf`),
  CONSTRAINT `fk_usuarios_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,1,'Super Administrador','superadmin@appcheckin.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'superadmin@appcheckin.com',1,3,NULL,NULL,NULL,'$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW','2025-12-26 17:58:37','2025-12-26 18:03:18'),(8,4,'Sporte e Saúde - Baixa Grande','rodolfo@gmail.com','','','','','','','','','',NULL,1,2,NULL,NULL,NULL,'$2y$10$901csKCay2EvMGl0l4ocFeFaJi2xxZ5aOnCu8kQGJGR08Lafh9rw.','2026-01-05 12:52:11','2026-01-06 12:57:41'),(9,5,'Jonas Amaro','jonas.fitpro@gmail.com','11998025255','594.036.880-84','13480-706','Via Capri','500','','Villa San Marino','Limeira','SP',NULL,1,2,NULL,NULL,NULL,'$2y$10$bp3dTdHRuAYv0Ra9Lh2L9O3WiZfKmgGIFbW35x6n0nqvfrE1NSRru','2026-01-06 13:05:43','2026-01-06 13:56:48');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-06 15:00:13
