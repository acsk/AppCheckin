-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 09/02/2026 às 17:27
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u304177849_api`
--

DELIMITER $$
--
-- Funções
--
DROP FUNCTION IF EXISTS `get_tenant_id_from_usuario`$$
CREATE DEFINER=`u304177849_api`@`127.0.0.1`
FUNCTION `get_tenant_id_from_usuario`(p_usuario_id INT)
RETURNS INT(11)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_tenant_id INT;

    -- Busca tenant_id do usuário na nova tabela
    SELECT tup.tenant_id
      INTO v_tenant_id
    FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = p_usuario_id
      AND tup.ativo = 1
    LIMIT 1;

    -- Se não encontrar, retorna tenant padrão
    IF v_tenant_id IS NULL THEN
        SET v_tenant_id = 1;
    END IF;

    RETURN v_tenant_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos`
--

CREATE TABLE IF NOT EXISTS `alunos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(32) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `foto_url` varchar(500) DEFAULT NULL,
  `foto_base64` longtext DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `foto_caminho` varchar(255) DEFAULT NULL COMMENT 'Caminho relativo da foto de perfil (ex: /uploads/fotos/aluno_123_1234567890.jpg)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `alunos`
--

INSERT INTO `alunos` (`id`, `usuario_id`, `nome`, `telefone`, `whatsapp`, `cpf`, `data_nascimento`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`, `foto_url`, `foto_base64`, `ativo`, `created_at`, `updated_at`, `foto_caminho`) VALUES
(1, 15, 'VISSIA VITÓRIA FERRO LIMA', '82996438378', '82996438378', '13586205473', '2006-01-10', '57303303', 'RUA DULCINEIA MARIA DA SILVA', '2', 'PORTÃO BRANCO', 'BOA VISTA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:14:50', '2026-02-07 05:18:40', '/uploads/fotos/aluno_1_1770441520.jpg'),
(2, 16, 'STEFANNY FERREIRA', '82998292529', '82998292529', '05542628435', '2000-02-05', '57312160', 'RUA MARECHAL RONDON', '189', NULL, 'SANTA ESMERALDA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:14:59', '2026-02-04 11:14:59', NULL),
(3, 17, 'THALIA GALDINO DA SILVA', '82999413362', '82999413362', '15145079435', '2007-03-05', '57318100', 'PEDRO ALEXANDRE', '359', NULL, 'CAVACO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:15:16', '2026-02-04 11:15:16', NULL),
(4, 18, 'MILENA KELMANY DA SILVA', '82991186643', '82991186643', '11903712432', '2000-01-09', '57320000', 'RUA SALUSTIANO NUNES', '241', 'CASA', 'SÃO JOÃO', 'CRAÍBAS', 'AL', NULL, NULL, 1, '2026-02-04 11:17:23', '2026-02-04 11:17:23', NULL),
(5, 19, 'ITAMARA DE SOUZA SANTOS', '82996194933', '82996194933', '12110106433', '1996-10-30', '5730000', 'RUA JOÃO BATISTA DA SILVA', '111', 'CASA', 'CANAÃ', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:18:10', '2026-02-04 11:18:10', NULL),
(6, 20, 'ANA PAULA FERREIRA', '82998015090', '82998015090', '11212541448', '1998-07-23', NULL, NULL, NULL, NULL, 'ARAPIRACA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:18:22', '2026-02-04 11:18:22', NULL),
(7, 21, 'FRANCYELLE FELISMINO DA SILVA', '82982249425', '82982249425', '09891502406', '1995-02-18', '57311660', 'RUA NOSSA SENHORA DAS DORES', '85', NULL, 'SENADOR TEOTÔNIO VILELA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:19:24', '2026-02-04 11:19:24', NULL),
(8, 22, 'MATEUS GONCALVES DE CASTILHO', '82998161122', '82998161122', '08954460496', '2002-04-17', '57306000', 'RUA EXPEDICIONÁRIOS BRASILEIROS', '54', NULL, 'ELDORADO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:19:25', '2026-02-04 11:19:25', NULL),
(9, 23, 'THAYNARA JESSYCA ESTEVAO CAVALCANTE', '82993632028', '82993632028', '09510436410', '1998-10-18', '57313200', 'RUA VEREADOR PEDRO ARISTIDES DA SILVA', '89', 'A', 'BRASÍLIA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:21:03', '2026-02-04 11:21:03', NULL),
(10, 24, 'CARLOS EDUARDO BARBOSA DOS SANTOS', '82998369832', '82998369832', '09769069450', '2001-12-29', '57303055', 'RUA ERCÍLIA BRANDÃO SILVA', '149', NULL, 'VERDES CAMPOS', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:21:07', '2026-02-04 11:21:07', NULL),
(11, 25, 'STEFFANY SOARES DA SILVA', '82981569712', '82981569712', '12023924405', '2001-05-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-04 11:23:12', '2026-02-04 11:23:12', NULL),
(12, 26, 'MARISA NATHYELLE OLIVEIRA SILVA', '82999835521', '82999835521', '06988928448', '1994-05-04', '57306420', 'RUA COSTA CAVALCANTE', '615A', NULL, 'CAVACO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:23:22', '2026-02-04 11:23:22', NULL),
(13, 27, 'VERANIA DOS SANTOS NUNES', '82999916695', '82999916695', '09778836477', '1992-06-12', '57307160', 'RUA MARGARITA PALMERINA DE ALMEIDA', '10', NULL, 'BAIXA GRANDE', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:28:26', '2026-02-04 11:28:26', NULL),
(14, 28, 'LETÍCIA DE OLIVEIRA MENDES', '82920006958', '82920006958', '11460354460', '2002-03-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-04 11:35:57', '2026-02-04 11:35:57', NULL),
(15, 29, 'NYCOLE FERREIRA AZEVEDO DE OLIVEIRA', '82996232833', '82996232833', '13649058413', '2000-10-08', '57303062', 'RUA JOSÉ FIRMINO NETO', NULL, NULL, 'VERDES CAMPOS', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 11:59:40', '2026-02-04 11:59:40', NULL),
(16, 30, 'ERIKA LARISSA S S ARAÚJO', '82999511362', '82999511362', '12407383400', '1998-10-15', '57304495', 'RUA MANOEL LÚCIO DA SILVA', '475', 'ATÉ 580/581', 'CACIMBAS', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:02:25', '2026-02-04 12:02:25', NULL),
(17, 31, 'DAG ANNE CORREIA CAJUEIRO', '82981556083', '82981556083', '10954722400', '1999-09-08', '57313160', 'RUA ANDRÉ LEÃO', '328', NULL, 'BRASÍLIA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:20:32', '2026-02-04 12:20:32', NULL),
(18, 32, 'YASMIN FAGUNDES DA SILVA LIMA', '82998097378', '82998097378', '09191177405', '1993-01-24', '57308714', 'RUA ENOQUE BEZERRA DE LIMA', '102', NULL, 'PLANALTO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:21:20', '2026-02-04 12:21:20', NULL),
(19, 33, 'ROSEANE  FERREIRA LIMA', '82996963904', '82996963904', '10088886484', '1992-08-17', '57312251', 'PRAÇA SANTA CRUZ', '86', NULL, 'ALTO DO CRUZEIRO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:22:29', '2026-02-04 12:22:29', NULL),
(20, 34, 'INGRID SOUSA VITAL', '82998001135', '82998001135', '11170545432', '1995-10-16', NULL, 'RUA CAMINHO DO SOL', '263', 'CASA', 'NILO COELHO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:24:19', '2026-02-04 12:24:19', NULL),
(21, 35, 'GIVALDO DE ALBUQUERQUE SILVA JÚNIOR', '82991739321', '82991739321', '13720370470', '2005-08-11', '57303320', 'RUA MANOEL SATURNINO DE ALMEIDA', '31', 'CASA', 'BOA VISTA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:25:27', '2026-02-04 12:25:27', NULL),
(22, 36, 'AMANDA NUNES DE OLIVEIRA', '0000000000', '82998000759', '08823894409', '1992-06-09', '57301452', 'RUA PROFESSORA AURORA DA CONCEIÇÃO SILVA COLAÇO', NULL, NULL, 'SÃO LUIZ', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:25:49', '2026-02-04 12:25:49', NULL),
(23, 37, 'MARIA BETANIA DA SILVA COSTA', '82996328520', '82996328520', '01020505460', '1980-08-03', '57312420', 'RUA MARCELINO MAGALHÃES', '399', NULL, 'ALTO DO CRUZEIRO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:26:04', '2026-02-04 12:26:04', NULL),
(24, 38, 'ESTÉFANY MARIA VITÓRIA DOS SANTOS', '79998650710', '79998650710', '06327719503', '2000-10-30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-04 12:26:13', '2026-02-04 12:26:13', NULL),
(25, 39, 'NANÁ DA SILVA CAMPOS', '21980753911', '21980753911', '00986408441', '1980-03-13', '57305370', 'RUA PADRE AMÉRICO', '475', NULL, 'BAIXÃO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:26:43', '2026-02-04 12:26:43', NULL),
(26, 40, 'ISABELLA GONÇALVES', '82996491824', '82996491824', '09983920492', '2004-07-30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-04 12:29:26', '2026-02-04 12:29:26', NULL),
(27, 41, 'ROSÂNGELA SILVA DE LIMA', '82999994945', '82999994945', '04390560476', '1983-06-17', NULL, NULL, NULL, NULL, NULL, 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:30:18', '2026-02-04 12:30:18', NULL),
(28, 42, 'WALMER VICTOR SILVA DOS SANTOS', '82991653656', '82991653656', '07550925461', '1991-07-11', '57313160', 'AVENIDA ANDRÉ LEÃO', '785', NULL, 'BRASÍLIA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:31:21', '2026-02-04 12:31:21', NULL),
(29, 43, 'LUIZ ANTÔNIO CAETANO NUNES', '82991092015', '82991092015', '09308115420', '1992-07-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-04 12:33:28', '2026-02-04 12:33:28', NULL),
(30, 44, 'VIVIANE SILVA', '82981744604', '82981744604', '08128786474', '1989-07-09', '57303240', 'RUA FREI DAMIÃO DE BOZZANO', '42', NULL, 'BOA VISTA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 12:49:31', '2026-02-04 12:49:31', NULL),
(31, 45, 'DIEGO RICHARD', '82999502257', '82999502257', '06366479437', '1985-04-12', NULL, NULL, NULL, NULL, NULL, 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 13:02:01', '2026-02-04 13:02:01', NULL),
(32, 46, 'NAYARA DA SILVA OLIVEIRA', '82999751604', '82999751604', '07715061476', '1990-06-22', '57313310', 'RUA NOSSA SENHORA DO Ó', '244', NULL, 'BRASÍLIA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 13:06:35', '2026-02-04 13:06:35', NULL),
(33, 47, 'KARLINNY H LUCENA', '82996124170', '82996124170', '06758256448', '1986-07-05', '57300030', 'RUA BOA VISTA', '221', NULL, 'CENTRO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 13:08:54', '2026-02-04 13:08:54', NULL),
(34, 48, 'CARLA DE JESUS VAZ LOPES', '75991099087', '75991099087', '02747144500', '1986-08-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-04 13:14:46', '2026-02-04 13:14:46', NULL),
(35, 49, 'RAFAELLE CAVALCANTI', '82999920739', '82999920739', '08306218493', '1995-05-17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-04 13:26:26', '2026-02-04 13:26:26', NULL),
(36, 50, 'NATALINE SANTOS FERREIRA', '82988932070', '82988932070', '08960471488', '1991-05-06', '57303018', 'RUA SEBASTIÃO FLORENTINO DOS SANTOS', '93', NULL, 'VERDES CAMPOS', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 13:27:46', '2026-02-04 13:27:46', NULL),
(37, 51, 'BRUNA BARBOZA DOS SANTOS', '82981416667', '82981416667', '07154498420', '1987-09-25', '57306200', 'RUA MANOEL BERNARDINO DOS SANTOS', '22', NULL, 'ELDORADO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 13:31:08', '2026-02-04 13:31:08', NULL),
(38, 52, 'NEUSVALDO PEDRO DA SILVA JUNIOR', '82991484561', '82991484561', '09922272407', '2003-07-03', '57318750', 'PV CAPIM', '9', NULL, 'CANAÃ', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 14:05:19', '2026-02-04 14:05:19', NULL),
(39, 53, 'KARLLOS EDUARDO', '82991365574', '82996142899', '12274718407', '2002-05-01', '57313070', 'RUA SENADOR RUI PALMEIRA', '117', NULL, 'BRASÍLIA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 14:13:10', '2026-02-04 14:13:10', NULL),
(40, 54, 'VITOR MANOEL IZIDIO SEVERIANO', '82999210585', '82981571143', '11118494474', '2000-01-09', '57316899', 'ÁREA RURAL', '41', 'POVOADO CAPIM', 'ÁREA RURAL DE ARAPIRACA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 14:15:44', '2026-02-04 14:15:44', NULL),
(41, 55, 'MARIA NEUZA DA SILVA', '82996539572', '82996539572', '06074794421', '1975-05-02', '57305570', 'RUA ANTÔNIO MENEZES NETO', '344', 'AP. 210 B2', 'ZÉLIA BARBOSA ROCHA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 14:32:24', '2026-02-04 14:32:24', NULL),
(42, 56, 'MARIA ANDÍVINE VITAL DE OLIVEIRA', '8296227175', '8296227175', '16970962474', '2008-05-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-04 14:50:00', '2026-02-04 14:50:00', NULL),
(43, 57, 'RAFAEL REZENDE DA SILVA', '82982177003', '82982177003', '12027050493', '1997-02-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-04 14:58:47', '2026-02-04 14:58:47', NULL),
(44, 58, 'ANTONIO MARTINS SILVA', '82981200339', '82981200339', '03023913471', '1979-04-17', '57303256', 'RUA ANTÔNIO ALFREDO SOARES', '176', '(LOT B NASCENTES)', 'BOA VISTA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 15:26:43', '2026-02-04 15:26:43', NULL),
(45, 59, 'JOÃO GUILHERME MENEZES DOS SANTOS', '82988508847', '82988508847', '16518915404', '2010-12-19', '57305814', 'RUA TOMÁZIA VENTURA DE FARIAS', '83', 'CASA', 'ZÉLIA BARBOSA ROCHA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 16:12:27', '2026-02-04 16:12:27', NULL),
(46, 60, 'ELVIS OLIVEIRA DOS SANTOS', '82981069262', '82981069262', '06453608480', '1985-09-04', '57310270', 'RUA JOÃO FERREIRA DE ALBUQUERQUE', '758', 'POR TRÁS DA IGREJA SANTA EDEWIRGES, DO BOSQUE.', 'ARAPIRACA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 16:12:37', '2026-02-04 16:12:37', NULL),
(47, 61, 'JULIO AQUA', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-04 18:58:21', '2026-02-05 13:51:13', NULL),
(48, 62, 'EVILY EMANUELLE DA SILVA', '82999936116', '82999936116', '70153625465', '1998-08-22', NULL, NULL, NULL, NULL, NULL, 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 20:13:42', '2026-02-04 20:13:42', NULL),
(49, 63, 'MARIA KAROLINA SAMPAIO MONTEIRO', '82996002238', '82982035615', '09559317466', '1994-06-10', '57309067', 'RUA JOSÉ LAELSON DE LIMA', '106', 'RES POR DO SOL', 'BOM SUCESSO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 23:20:23', '2026-02-04 23:20:23', NULL),
(50, 64, 'CLAUDILENE DA SILVA SANTOS', '82991395398', '82991395398', '70440278490', '1996-07-16', '57307155', 'RUA SANTA TEREZA', '78', NULL, 'BAIXA GRANDE', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 23:23:39', '2026-02-04 23:23:39', NULL),
(51, 65, 'HENRIQUE PEREIRA FREITAS DE MENDONÇA', '82999398995', '82999398995', '05174588458', '1989-12-10', '57307060', 'RUA FRANCISCO OTÍLIO DOS SANTOS', '157', NULL, 'BAIXA GRANDE', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-04 23:32:48', '2026-02-04 23:32:48', NULL),
(52, 66, 'JERLANE CAVALCANTE', '82999868311', '82999868311', '04949024426', '1985-07-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-05 02:31:57', '2026-02-05 02:31:57', NULL),
(54, 67, 'ARYENE PEREIRA', '82996161759', '82996161759', '11732445494', '1998-08-09', '57315746', 'RUA ANTONIO OTÁVIO DE OLIVEIRA', '180', NULL, 'SENADOR ARNON DE MELO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-05 14:25:59', '2026-02-05 14:25:59', NULL),
(55, 70, 'ARNON VICTOR SILVA DE OLIVEIRA', '82999139531', '82999139531', '14138730494', '2004-05-01', NULL, NULL, '201', NULL, 'CENTRO', 'IGACI', 'AL', NULL, NULL, 1, '2026-02-05 22:44:07', '2026-02-05 22:44:07', NULL),
(56, 71, 'JULIANA FERNANDA', '82996022920', '82996022920', '13379638447', '2000-01-17', '57305306', 'GREGÓRIO NEVES', '4', NULL, 'MANOEL TELES', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-06 12:48:37', '2026-02-06 12:48:37', NULL),
(57, 72, 'NATALY MARIA COSTA SILVA', '82999528221', '82999528221', '12279687445', '1999-12-09', '57318100', 'POVOADO CANGANDU DE CIMA', '45', NULL, 'CANGANDU', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-06 13:22:51', '2026-02-06 13:22:51', NULL),
(58, 73, 'RONEIDE SANTOS', '82996090345', '82996090345', '12305276486', '1999-06-04', '57314105', 'AVENIDA DEPUTADA CECI CUNHA', '922', 'DE 670 AO FIM - LADO PAR', 'ITAPOÃ', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 13:53:58', '2026-02-08 13:53:58', NULL),
(59, 74, 'LUCAS RAFAEL DOS SANTOS BATISTA', '82996315911', '82996315911', '07467957424', '1999-05-24', NULL, NULL, '16', 'CASA', 'ALTO DO CRUZEIRO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 13:56:23', '2026-02-08 13:56:23', NULL),
(60, 75, 'ANDREZA FERREIRA DE FARIAS', '82999317238', '82999317238', '10982001444', '1995-11-21', NULL, 'RUA', '21', NULL, NULL, 'TAQUARANA', 'AL', NULL, NULL, 1, '2026-02-08 13:58:13', '2026-02-08 13:58:13', NULL),
(61, 76, 'MAYCKONN DOUGLAS FREIRE BARBOSA', '82988360671', '82988360671', '04832910485', '1987-10-05', '57305610', 'RUA MIGUEL TERTULIANO DA SILVA', '08', 'RES RIVIERA DO LAGO, QD D, L 28', 'ZÉLIA BARBOSA ROCHA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 14:00:53', '2026-02-08 14:00:53', NULL),
(62, 77, 'LUIZ FILHO DA SILVA', '82996325051', '82996325051', '80441165400', '1971-10-21', '57305620', 'RUA COSTA CAVALCANTE', '615', 'CASA', 'ZÉLIA BARBOSA ROCHA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 14:01:38', '2026-02-08 14:01:38', NULL),
(63, 78, 'ADILSON MONTEIRO DA SILVA', '82998390128', '98390128', '24014923487', '1961-05-11', '57307762', 'RUA JOÃO LUCAS FARIAS PEREIRA', '133', '(LOT NOVO JARDIM)', 'JARDIM ESPERANÇA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 14:10:45', '2026-02-08 14:10:45', NULL),
(64, 79, 'JOSE RICARDO SALES SALES', '82996153330', '82996153330', '01368237428', '1986-09-29', '57312020', 'RUA OURO BRANCO', '1026', NULL, 'SANTA ESMERALDA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 15:45:35', '2026-02-08 15:45:35', NULL),
(65, 80, 'JANIEIDE DOS SANTOS SILVA', '82991734158', '82991434158', '07922585411', '1993-08-09', NULL, 'RUA EXPEDICIONÁRIOS BRASILEIROS', '611', 'SALA 3', 'ARAPIRACA- BAIXA GRANDE', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 16:38:13', '2026-02-08 16:38:13', NULL),
(66, 81, 'WERLISSON SANTOS DIAS', '82981059074', '82981059074', '06907769425', '1987-11-24', '57301030', 'RUA NOSSA SENHORA DO Ó', '244', NULL, 'ARAPIRACA', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 17:16:56', '2026-02-08 17:16:56', NULL),
(67, 82, 'ROSANGELA DA ROCHA SIQUEIRA TAVARES', '82996448407', '82996448407', '66237106472', '1969-09-11', '57306420', 'RUA COSTA CAVALCANTE', '277', 'ED.GRAND FIORI', 'CAVACO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 18:24:21', '2026-02-08 18:24:21', NULL),
(68, 83, 'ELICLEIDE RODRIGUES DA SILVA', '82999705959', '82999705959', '06145125497', '1985-05-30', '57306790', 'RUA MARTA JANAÍNA', '55', 'CASA', 'CAVACO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 20:09:45', '2026-02-08 20:09:45', NULL),
(69, 84, 'LAMENIA RODRIGUES GOMES DE OLIVEIRA', '82999335533', '82999335533', '10871491427', '1996-07-05', '57300520', 'RUA JORNALISTA JOSÉ OLAVO BISPO', '30', NULL, 'CENTRO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 20:34:16', '2026-02-08 20:34:16', NULL),
(70, 85, 'KELLY CRISTINA SANTOS COSTA BARBOSA', '82998212817', '82998212817', '15125477435', '2002-06-23', '57312460', 'RUA SANTA MARIA', '284', 'CASA', 'ALTO DO CRUZEIRO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-08 22:12:39', '2026-02-08 22:12:39', NULL),
(71, 86, 'JOSÉ MURILO FORTUNATO PEREIRA', '8299207234', '8299207234', '41580586864', '1997-06-27', '57300460', 'RUA SANTA TEREZINHA', NULL, NULL, 'CENTRO', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-09 08:47:36', '2026-02-09 08:47:36', NULL),
(72, 3, 'ANDRÉ CABRAL SILVA', '82988376381', '82988376381', '05809498426', '1984-03-30', '57307200', 'SEVERINO CATANHO', '134', NULL, 'BAIXA GRANDE', 'ARAPIRACA', 'AL', NULL, NULL, 1, '2026-02-09 15:09:57', '2026-02-09 17:26:06', '/uploads/fotos/aluno_72_1770657966.jpg'),
(74, 87, 'MANUELLY NUNES DA SILVA', '82991878075', '82991878075', '12474552464', '2010-10-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-09 15:37:41', '2026-02-09 15:37:41', NULL),
(75, 88, 'DALVAN FERREIRA', '82999436141', '82999436141', '07615828430', '1993-06-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-09 16:42:54', '2026-02-09 16:42:54', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `assinaturas`
--

CREATE TABLE IF NOT EXISTS `assinaturas` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `aluno_id` int(11) NOT NULL,
  `matricula_id` int(11) DEFAULT NULL,
  `plano_id` int(11) DEFAULT NULL,
  `gateway_id` tinyint(3) UNSIGNED NOT NULL,
  `gateway_assinatura_id` varchar(100) DEFAULT NULL COMMENT 'ID no gateway (preapproval_id, etc)',
  `gateway_preference_id` varchar(100) DEFAULT NULL,
  `external_reference` varchar(100) DEFAULT NULL,
  `payment_url` varchar(500) DEFAULT NULL,
  `gateway_cliente_id` varchar(100) DEFAULT NULL,
  `status_id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `status_gateway` varchar(50) DEFAULT NULL COMMENT 'Status original do gateway',
  `valor` decimal(10,2) NOT NULL,
  `moeda` varchar(3) DEFAULT 'BRL',
  `frequencia_id` tinyint(3) UNSIGNED NOT NULL DEFAULT 4,
  `dia_cobranca` tinyint(3) UNSIGNED DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `proxima_cobranca` date DEFAULT NULL,
  `ultima_cobranca` date DEFAULT NULL,
  `metodo_pagamento_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `tipo_cobranca` enum('avulso','recorrente') NOT NULL DEFAULT 'recorrente',
  `cartao_ultimos_digitos` varchar(4) DEFAULT NULL,
  `cartao_bandeira` varchar(20) DEFAULT NULL,
  `tentativas_cobranca` int(11) DEFAULT 0,
  `motivo_cancelamento` text DEFAULT NULL,
  `cancelado_por_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `assinaturas`
--

INSERT INTO `assinaturas` (`id`, `tenant_id`, `aluno_id`, `matricula_id`, `plano_id`, `gateway_id`, `gateway_assinatura_id`, `gateway_preference_id`, `external_reference`, `payment_url`, `gateway_cliente_id`, `status_id`, `status_gateway`, `valor`, `moeda`, `frequencia_id`, `dia_cobranca`, `data_inicio`, `data_fim`, `proxima_cobranca`, `ultima_cobranca`, `metodo_pagamento_id`, `tipo_cobranca`, `cartao_ultimos_digitos`, `cartao_bandeira`, `tentativas_cobranca`, `motivo_cancelamento`, `cancelado_por_id`, `criado_em`, `atualizado_em`) VALUES
(9, 2, 1, 47, 14, 1, NULL, '195078879-86d8b2c0-0829-40a1-98b1-4e820753754a', 'MAT-47-1770604193', 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=195078879-86d8b2c0-0829-40a1-98b1-4e820753754a', NULL, 6, 'approved', 0.50, 'BRL', 4, 8, '2026-02-08', '2026-03-10', NULL, '2026-02-08', 3, 'avulso', NULL, NULL, 0, NULL, NULL, '2026-02-09 02:29:54', '2026-02-09 02:30:38');

-- --------------------------------------------------------

--
-- Estrutura para tabela `assinaturas_mercadopago`
--

CREATE TABLE IF NOT EXISTS `assinaturas_mercadopago` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `matricula_id` int(11) NOT NULL,
  `aluno_id` int(11) NOT NULL,
  `plano_ciclo_id` int(11) DEFAULT NULL COMMENT 'Ciclo contratado',
  `mp_preapproval_id` varchar(100) DEFAULT NULL COMMENT 'ID da assinatura no MP',
  `mp_plan_id` varchar(100) DEFAULT NULL COMMENT 'ID do plano no MP (se usar plano pré-criado)',
  `mp_payer_id` varchar(100) DEFAULT NULL COMMENT 'ID do pagador no MP',
  `status` enum('pending','authorized','paused','cancelled','finished') DEFAULT 'pending',
  `valor` decimal(10,2) NOT NULL,
  `moeda` varchar(3) DEFAULT 'BRL',
  `dia_cobranca` int(11) DEFAULT 1 COMMENT 'Dia do mês para cobrança',
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL COMMENT 'Data de término (se não for indefinido)',
  `proxima_cobranca` date DEFAULT NULL,
  `ultima_cobranca` date DEFAULT NULL,
  `tentativas_falha` int(11) DEFAULT 0,
  `motivo_cancelamento` text DEFAULT NULL,
  `cancelado_por` int(11) DEFAULT NULL,
  `data_cancelamento` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Assinaturas recorrentes do MercadoPago';

-- --------------------------------------------------------

--
-- Estrutura para tabela `assinatura_cancelamento_tipos`
--

CREATE TABLE IF NOT EXISTS `assinatura_cancelamento_tipos` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `assinatura_cancelamento_tipos`
--

INSERT INTO `assinatura_cancelamento_tipos` (`id`, `codigo`, `nome`, `criado_em`) VALUES
(1, 'usuario', 'Cancelado pelo Usuário', '2026-02-07 20:29:28'),
(2, 'admin', 'Cancelado pelo Administrador', '2026-02-07 20:29:28'),
(3, 'gateway', 'Cancelado pelo Gateway', '2026-02-07 20:29:28'),
(4, 'sistema', 'Cancelado pelo Sistema', '2026-02-07 20:29:28');

-- --------------------------------------------------------

--
-- Estrutura para tabela `assinatura_frequencias`
--

CREATE TABLE IF NOT EXISTS `assinatura_frequencias` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nome` varchar(30) NOT NULL,
  `dias` int(11) NOT NULL COMMENT 'Quantidade de dias do ciclo',
  `meses` int(11) DEFAULT NULL COMMENT 'Quantidade de meses (alternativa)',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `ordem` int(11) DEFAULT 1,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `assinatura_frequencias`
--

INSERT INTO `assinatura_frequencias` (`id`, `codigo`, `nome`, `dias`, `meses`, `criado_em`, `ordem`, `ativo`) VALUES
(1, 'diario', 'Diário', 1, 1, '2026-02-07 20:29:27', 1, 1),
(2, 'semanal', 'Semanal', 7, 1, '2026-02-07 20:29:27', 1, 1),
(3, 'quinzenal', 'Quinzenal', 15, 1, '2026-02-07 20:29:27', 1, 1),
(4, 'mensal', 'Mensal', 30, 1, '2026-02-07 20:29:27', 1, 1),
(5, 'bimestral', 'Bimestral', 60, 2, '2026-02-07 20:29:27', 2, 1),
(6, 'trimestral', 'Trimestral', 90, 3, '2026-02-07 20:29:27', 3, 1),
(7, 'semestral', 'Semestral', 180, 6, '2026-02-07 20:29:27', 5, 1),
(8, 'anual', 'Anual', 365, 12, '2026-02-07 20:29:27', 6, 1),
(9, 'quadrimestral', 'Quadrimestral', 120, 4, '2026-02-07 20:29:27', 4, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `assinatura_gateways`
--

CREATE TABLE IF NOT EXISTS `assinatura_gateways` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `assinatura_gateways`
--

INSERT INTO `assinatura_gateways` (`id`, `codigo`, `nome`, `ativo`, `criado_em`) VALUES
(1, 'mercadopago', 'Mercado Pago', 1, '2026-02-07 20:29:27'),
(2, 'stripe', 'Stripe', 1, '2026-02-07 20:29:27'),
(3, 'pagseguro', 'PagSeguro', 1, '2026-02-07 20:29:27'),
(4, 'pagarme', 'Pagar.me', 1, '2026-02-07 20:29:27'),
(5, 'manual', 'Manual', 1, '2026-02-07 20:29:27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `assinatura_status`
--

CREATE TABLE IF NOT EXISTS `assinatura_status` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` varchar(100) DEFAULT NULL,
  `cor` varchar(7) DEFAULT NULL COMMENT 'Cor hex para UI',
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `assinatura_status`
--

INSERT INTO `assinatura_status` (`id`, `codigo`, `nome`, `descricao`, `cor`, `criado_em`) VALUES
(1, 'pendente', 'Pendente', 'Aguardando confirmação de pagamento', '#FFA500', '2026-02-07 20:29:27'),
(2, 'ativa', 'Ativa', 'Assinatura ativa e em dia', '#28A745', '2026-02-07 20:29:27'),
(3, 'pausada', 'Pausada', 'Assinatura temporariamente suspensa', '#6C757D', '2026-02-07 20:29:27'),
(4, 'cancelada', 'Cancelada', 'Assinatura cancelada', '#DC3545', '2026-02-07 20:29:27'),
(5, 'expirada', 'Expirada', 'Assinatura vencida', '#6C757D', '2026-02-07 20:29:27'),
(6, 'paga', 'Paga', 'Pagamento avulso confirmado', '#28A745', '2026-02-09 02:09:51');

-- --------------------------------------------------------

--
-- Estrutura para tabela `checkins`
--

CREATE TABLE IF NOT EXISTS `checkins` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `aluno_id` int(11) DEFAULT NULL COMMENT 'FK para alunos',
  `turma_id` int(11) DEFAULT NULL,
  `horario_id` int(11) DEFAULT NULL,
  `data_checkin` datetime NOT NULL DEFAULT current_timestamp(),
  `data_checkin_date` date GENERATED ALWAYS AS (cast(`data_checkin` as date)) STORED COMMENT 'Data do checkin (sem hora) - gerada automaticamente',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `registrado_por_admin` tinyint(1) DEFAULT 0 COMMENT 'TRUE se admin fez check-in manual do aluno',
  `admin_id` int(11) DEFAULT NULL COMMENT 'ID do admin que registrou (se aplicÃ¡vel)',
  `presente` tinyint(1) DEFAULT NULL COMMENT 'NULL=não verificado, 1=presente, 0=falta',
  `presenca_confirmada_em` datetime DEFAULT NULL COMMENT 'Data/hora que a presença foi confirmada',
  `presenca_confirmada_por` int(11) DEFAULT NULL COMMENT 'ID do professor/admin que confirmou'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `checkins`
--

INSERT INTO `checkins` (`id`, `tenant_id`, `aluno_id`, `turma_id`, `horario_id`, `data_checkin`, `created_at`, `updated_at`, `registrado_por_admin`, `admin_id`, `presente`, `presenca_confirmada_em`, `presenca_confirmada_por`) VALUES
(28, 3, 72, 87, NULL, '2026-02-09 14:25:03', '2026-02-09 17:25:03', '2026-02-09 17:25:03', 0, NULL, NULL, NULL, NULL);

--
-- Acionadores `checkins`
--
DELIMITER $$
DROP TRIGGER IF EXISTS `checkins_before_insert_tenant`$$
CREATE TRIGGER `checkins_before_insert_tenant` BEFORE INSERT ON `checkins` FOR EACH ROW BEGIN
    -- Agora obtém tenant_id a partir do aluno_id
    IF NEW.tenant_id IS NULL AND NEW.aluno_id IS NOT NULL THEN
        SET NEW.tenant_id = (
            SELECT tup.tenant_id 
            FROM tenant_usuario_papel tup 
            INNER JOIN alunos a ON a.usuario_id = tup.usuario_id
            WHERE a.id = NEW.aluno_id 
            AND tup.ativo = 1
            LIMIT 1
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `contas_receber`
--

CREATE TABLE IF NOT EXISTS `contas_receber` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `historico_plano_id` int(11) DEFAULT NULL COMMENT 'ReferÃªncia ao histÃ³rico que gerou esta conta',
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','vencido','cancelado') DEFAULT 'pendente',
  `status_id` int(11) DEFAULT NULL COMMENT 'FK para status_conta_receber',
  `forma_pagamento_id` int(11) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `referencia_mes` varchar(7) DEFAULT NULL COMMENT 'Formato YYYY-MM para controle mensal',
  `recorrente` tinyint(1) DEFAULT 0 COMMENT 'Se true, gera prÃ³xima parcela ao dar baixa',
  `intervalo_dias` int(11) DEFAULT NULL COMMENT 'Dias para gerar prÃ³xima parcela (30, 90, 180, 365)',
  `proxima_conta_id` int(11) DEFAULT NULL COMMENT 'ID da prÃ³xima conta gerada automaticamente',
  `conta_origem_id` int(11) DEFAULT NULL COMMENT 'ID da conta que originou esta (para rastreamento)',
  `criado_por` int(11) DEFAULT NULL COMMENT 'ID do admin que criou',
  `baixa_por` int(11) DEFAULT NULL COMMENT 'ID do admin que deu baixa',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `valor_liquido` decimal(10,2) DEFAULT NULL COMMENT 'Valor apÃ³s desconto da operadora',
  `valor_desconto` decimal(10,2) DEFAULT NULL COMMENT 'Valor do desconto da operadora'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Acionadores `contas_receber`
--
DELIMITER $$
DROP TRIGGER IF EXISTS `atualizar_status_vencido`$$
CREATE TRIGGER `atualizar_status_vencido` BEFORE UPDATE ON `contas_receber` FOR EACH ROW BEGIN
    IF NEW.status = 'pendente' AND NEW.data_vencimento < CURDATE() THEN
        SET NEW.status = 'vencido';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `dias`
--

CREATE TABLE IF NOT EXISTS `dias` (
  `id` int(11) NOT NULL,
  `data` date NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `dias`
--

INSERT INTO `dias` (`id`, `data`, `ativo`, `created_at`, `updated_at`) VALUES
(1, '2026-01-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(2, '2026-01-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(3, '2026-01-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(4, '2026-01-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(5, '2026-01-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(6, '2026-01-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(7, '2026-01-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(8, '2026-01-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(9, '2026-01-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(10, '2026-01-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(11, '2026-01-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(12, '2026-01-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(13, '2026-01-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(14, '2026-01-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(15, '2026-01-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(16, '2026-01-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(17, '2026-01-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(18, '2026-01-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(19, '2026-01-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(20, '2026-01-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(21, '2026-01-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(22, '2026-01-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(23, '2026-01-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(24, '2026-01-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(25, '2026-01-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(26, '2026-01-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(27, '2026-01-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(28, '2026-01-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(29, '2026-01-29', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(30, '2026-01-30', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(31, '2026-01-31', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(32, '2026-02-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(33, '2026-02-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(34, '2026-02-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(35, '2026-02-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(36, '2026-02-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(37, '2026-02-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(38, '2026-02-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(39, '2026-02-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(40, '2026-02-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(41, '2026-02-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(42, '2026-02-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(43, '2026-02-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(44, '2026-02-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(45, '2026-02-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(46, '2026-02-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(47, '2026-02-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(48, '2026-02-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(49, '2026-02-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(50, '2026-02-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(51, '2026-02-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(52, '2026-02-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(53, '2026-02-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(54, '2026-02-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(55, '2026-02-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(56, '2026-02-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(57, '2026-02-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(58, '2026-02-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(59, '2026-02-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(60, '2026-03-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(61, '2026-03-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(62, '2026-03-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(63, '2026-03-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(64, '2026-03-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(65, '2026-03-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(66, '2026-03-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(67, '2026-03-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(68, '2026-03-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(69, '2026-03-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(70, '2026-03-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(71, '2026-03-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(72, '2026-03-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(73, '2026-03-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(74, '2026-03-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(75, '2026-03-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(76, '2026-03-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(77, '2026-03-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(78, '2026-03-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(79, '2026-03-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(80, '2026-03-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(81, '2026-03-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(82, '2026-03-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(83, '2026-03-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(84, '2026-03-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(85, '2026-03-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(86, '2026-03-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(87, '2026-03-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(88, '2026-03-29', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(89, '2026-03-30', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(90, '2026-03-31', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(91, '2026-04-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(92, '2026-04-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(93, '2026-04-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(94, '2026-04-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(95, '2026-04-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(96, '2026-04-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(97, '2026-04-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(98, '2026-04-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(99, '2026-04-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(100, '2026-04-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(101, '2026-04-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(102, '2026-04-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(103, '2026-04-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(104, '2026-04-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(105, '2026-04-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(106, '2026-04-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(107, '2026-04-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(108, '2026-04-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(109, '2026-04-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(110, '2026-04-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(111, '2026-04-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(112, '2026-04-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(113, '2026-04-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(114, '2026-04-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(115, '2026-04-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(116, '2026-04-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(117, '2026-04-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(118, '2026-04-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(119, '2026-04-29', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(120, '2026-04-30', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(121, '2026-05-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(122, '2026-05-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(123, '2026-05-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(124, '2026-05-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(125, '2026-05-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(126, '2026-05-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(127, '2026-05-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(128, '2026-05-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(129, '2026-05-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(130, '2026-05-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(131, '2026-05-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(132, '2026-05-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(133, '2026-05-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(134, '2026-05-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(135, '2026-05-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(136, '2026-05-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(137, '2026-05-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(138, '2026-05-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(139, '2026-05-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(140, '2026-05-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(141, '2026-05-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(142, '2026-05-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(143, '2026-05-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(144, '2026-05-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(145, '2026-05-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(146, '2026-05-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(147, '2026-05-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(148, '2026-05-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(149, '2026-05-29', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(150, '2026-05-30', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(151, '2026-05-31', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(152, '2026-06-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(153, '2026-06-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(154, '2026-06-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(155, '2026-06-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(156, '2026-06-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(157, '2026-06-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(158, '2026-06-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(159, '2026-06-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(160, '2026-06-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(161, '2026-06-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(162, '2026-06-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(163, '2026-06-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(164, '2026-06-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(165, '2026-06-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(166, '2026-06-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(167, '2026-06-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(168, '2026-06-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(169, '2026-06-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(170, '2026-06-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(171, '2026-06-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(172, '2026-06-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(173, '2026-06-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(174, '2026-06-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(175, '2026-06-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(176, '2026-06-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(177, '2026-06-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(178, '2026-06-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(179, '2026-06-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(180, '2026-06-29', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(181, '2026-06-30', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(182, '2026-07-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(183, '2026-07-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(184, '2026-07-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(185, '2026-07-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(186, '2026-07-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(187, '2026-07-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(188, '2026-07-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(189, '2026-07-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(190, '2026-07-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(191, '2026-07-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(192, '2026-07-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(193, '2026-07-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(194, '2026-07-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(195, '2026-07-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(196, '2026-07-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(197, '2026-07-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(198, '2026-07-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(199, '2026-07-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(200, '2026-07-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(201, '2026-07-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(202, '2026-07-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(203, '2026-07-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(204, '2026-07-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(205, '2026-07-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(206, '2026-07-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(207, '2026-07-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(208, '2026-07-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(209, '2026-07-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(210, '2026-07-29', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(211, '2026-07-30', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(212, '2026-07-31', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(213, '2026-08-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(214, '2026-08-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(215, '2026-08-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(216, '2026-08-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(217, '2026-08-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(218, '2026-08-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(219, '2026-08-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(220, '2026-08-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(221, '2026-08-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(222, '2026-08-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(223, '2026-08-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(224, '2026-08-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(225, '2026-08-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(226, '2026-08-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(227, '2026-08-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(228, '2026-08-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(229, '2026-08-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(230, '2026-08-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(231, '2026-08-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(232, '2026-08-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(233, '2026-08-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(234, '2026-08-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(235, '2026-08-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(236, '2026-08-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(237, '2026-08-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(238, '2026-08-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(239, '2026-08-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(240, '2026-08-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(241, '2026-08-29', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(242, '2026-08-30', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(243, '2026-08-31', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(244, '2026-09-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(245, '2026-09-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(246, '2026-09-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(247, '2026-09-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(248, '2026-09-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(249, '2026-09-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(250, '2026-09-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(251, '2026-09-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(252, '2026-09-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(253, '2026-09-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(254, '2026-09-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(255, '2026-09-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(256, '2026-09-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(257, '2026-09-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(258, '2026-09-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(259, '2026-09-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(260, '2026-09-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(261, '2026-09-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(262, '2026-09-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(263, '2026-09-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(264, '2026-09-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(265, '2026-09-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(266, '2026-09-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(267, '2026-09-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(268, '2026-09-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(269, '2026-09-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(270, '2026-09-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(271, '2026-09-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(272, '2026-09-29', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(273, '2026-09-30', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(274, '2026-10-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(275, '2026-10-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(276, '2026-10-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(277, '2026-10-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(278, '2026-10-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(279, '2026-10-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(280, '2026-10-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(281, '2026-10-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(282, '2026-10-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(283, '2026-10-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(284, '2026-10-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(285, '2026-10-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(286, '2026-10-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(287, '2026-10-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(288, '2026-10-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(289, '2026-10-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(290, '2026-10-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(291, '2026-10-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(292, '2026-10-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(293, '2026-10-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(294, '2026-10-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(295, '2026-10-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(296, '2026-10-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(297, '2026-10-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(298, '2026-10-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(299, '2026-10-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(300, '2026-10-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(301, '2026-10-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(302, '2026-10-29', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(303, '2026-10-30', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(304, '2026-10-31', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(305, '2026-11-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(306, '2026-11-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(307, '2026-11-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(308, '2026-11-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(309, '2026-11-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(310, '2026-11-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(311, '2026-11-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(312, '2026-11-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(313, '2026-11-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(314, '2026-11-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(315, '2026-11-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(316, '2026-11-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(317, '2026-11-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(318, '2026-11-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(319, '2026-11-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(320, '2026-11-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(321, '2026-11-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(322, '2026-11-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(323, '2026-11-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(324, '2026-11-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(325, '2026-11-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(326, '2026-11-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(327, '2026-11-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(328, '2026-11-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(329, '2026-11-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(330, '2026-11-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(331, '2026-11-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(332, '2026-11-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(333, '2026-11-29', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(334, '2026-11-30', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(335, '2026-12-01', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(336, '2026-12-02', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(337, '2026-12-03', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(338, '2026-12-04', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(339, '2026-12-05', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(340, '2026-12-06', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(341, '2026-12-07', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(342, '2026-12-08', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(343, '2026-12-09', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(344, '2026-12-10', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(345, '2026-12-11', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(346, '2026-12-12', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(347, '2026-12-13', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(348, '2026-12-14', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(349, '2026-12-15', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(350, '2026-12-16', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(351, '2026-12-17', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(352, '2026-12-18', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(353, '2026-12-19', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(354, '2026-12-20', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(355, '2026-12-21', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(356, '2026-12-22', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(357, '2026-12-23', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(358, '2026-12-24', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(359, '2026-12-25', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(360, '2026-12-26', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(361, '2026-12-27', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(362, '2026-12-28', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(363, '2026-12-29', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(364, '2026-12-30', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34'),
(365, '2026-12-31', 1, '2026-01-20 17:56:34', '2026-01-20 17:56:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `email_logs`
--

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL COMMENT 'Tenant associado (se aplicável)',
  `usuario_id` int(11) DEFAULT NULL COMMENT 'Usuário destinatário (se aplicável)',
  `to_email` varchar(255) NOT NULL COMMENT 'Email de destino',
  `to_name` varchar(255) DEFAULT NULL COMMENT 'Nome do destinatário',
  `from_email` varchar(255) NOT NULL COMMENT 'Email remetente',
  `from_name` varchar(255) DEFAULT NULL COMMENT 'Nome do remetente',
  `subject` varchar(500) NOT NULL COMMENT 'Assunto do email',
  `email_type` varchar(50) NOT NULL DEFAULT 'generic' COMMENT 'Tipo: password_recovery, welcome, notification, etc',
  `body_preview` text DEFAULT NULL COMMENT 'Preview do corpo (primeiros 500 caracteres)',
  `status` enum('pending','sent','failed','bounced') NOT NULL DEFAULT 'pending' COMMENT 'Status do envio',
  `error_message` text DEFAULT NULL COMMENT 'Mensagem de erro se falhou',
  `provider` varchar(50) NOT NULL DEFAULT 'ses' COMMENT 'Provedor: ses, smtp, sendgrid',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP de origem da requisição',
  `user_agent` varchar(500) DEFAULT NULL COMMENT 'User agent se disponível',
  `message_id` varchar(255) DEFAULT NULL COMMENT 'ID da mensagem no provedor',
  `sent_at` datetime DEFAULT NULL COMMENT 'Data/hora do envio efetivo',
  `opened_at` datetime DEFAULT NULL COMMENT 'Data/hora da abertura (se rastreado)',
  `clicked_at` datetime DEFAULT NULL COMMENT 'Data/hora do clique (se rastreado)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoria de emails enviados';

--
-- Despejando dados para a tabela `email_logs`
--

INSERT INTO `email_logs` (`id`, `tenant_id`, `usuario_id`, `to_email`, `to_name`, `from_email`, `from_name`, `subject`, `email_type`, `body_preview`, `status`, `error_message`, `provider`, `ip_address`, `user_agent`, `message_id`, `sent_at`, `opened_at`, `clicked_at`, `created_at`, `updated_at`) VALUES
(1, NULL, 3, 'andrecabrall@gmail.com', 'ANDRE CABRAL SILVA', 'mail@appcheckin.com.br', 'App Check-in', '🔐 Código de Recuperação de Senha - App Check-in', 'password_recovery', '\n\n\n    \n    \n    \n    App Check-in\n    \n    \n        /* Reset */\n        body, table, td, p, a, li, blockquote {\n            -webkit-text-size-adjust: 100%;\n            -ms-text-size-adjust: 100%;\n        }\n        table, td {\n            mso-table-lspace: 0pt;\n            mso-table-rspace: 0pt;\n        }\n        img {\n            -ms-interpolation-mode: bicubic;\n            border: 0;\n            height: auto;\n            line-height: 100%;\n            outline: none;\n            text-decoration', 'sent', NULL, 'resend', '45.181.65.48', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'eaed4e30-2f8d-4db1-94b5-2f4c8c4929ee', '2026-01-25 15:01:59', NULL, NULL, '2026-01-25 15:01:58', '2026-01-25 15:01:59'),
(2, NULL, 3, 'andrecabrall@gmail.com', 'ANDRE CABRAL SILVA', 'mail@appcheckin.com.br', 'App Check-in', '🔐 Código de Recuperação de Senha - App Check-in', 'password_recovery', '\n\n\n    \n    \n    \n    App Check-in\n    \n    \n        /* Reset */\n        body, table, td, p, a, li, blockquote {\n            -webkit-text-size-adjust: 100%;\n            -ms-text-size-adjust: 100%;\n        }\n        table, td {\n            mso-table-lspace: 0pt;\n            mso-table-rspace: 0pt;\n        }\n        img {\n            -ms-interpolation-mode: bicubic;\n            border: 0;\n            height: auto;\n            line-height: 100%;\n            outline: none;\n            text-decoration', 'sent', NULL, 'resend', '45.181.65.47', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '39e9e2c0-8821-4e5c-9fe4-212e401a6d1a', '2026-01-29 00:20:42', NULL, NULL, '2026-01-29 03:20:41', '2026-01-29 03:20:42'),
(3, NULL, NULL, 'teste@teste1.com', 'Teste Teste', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '45.181.65.65', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'edc7fffb-a966-4ec0-935c-79a037680484', '2026-02-03 09:42:03', NULL, NULL, '2026-02-03 12:42:00', '2026-02-03 12:42:03'),
(4, NULL, NULL, 'teste@teste5.com', 'Teste Teste', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '45.181.65.65', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'c55ac642-f195-444e-8fca-320de5f4e8e2', '2026-02-03 13:07:57', NULL, NULL, '2026-02-03 16:07:57', '2026-02-03 16:07:57'),
(5, NULL, 15, 'vissiavitoria@gmail.com', 'Vissia Vitória Ferro Lima', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:788:522:af00:9572:aaf6:fd42:3c8b', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'bfa0e473-c90a-4b65-8dac-296eeb9f74e1', '2026-02-04 08:14:51', NULL, NULL, '2026-02-04 11:14:50', '2026-02-04 11:14:51'),
(6, NULL, 16, 'stefannyferreirasf47@gmail.com', 'Stefanny Ferreira', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '191.58.98.129', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '06906c4b-7b0b-4b2c-aadc-12c22a6f89c5', '2026-02-04 08:15:00', NULL, NULL, '2026-02-04 11:14:59', '2026-02-04 11:15:00'),
(7, NULL, 17, 'thaliagaldino1@gmail.com', 'Thalia Galdino da Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '177.131.233.140', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36 EdgA/144.0.0.0', '5256fe9b-bf8e-4098-bd86-edb9101722d9', '2026-02-04 08:15:16', NULL, NULL, '2026-02-04 11:15:16', '2026-02-04 11:15:16'),
(8, NULL, 18, 'millyalbuquer@gmail.com', 'Milena Kelmany Da Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '177.85.130.116', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '194a2dbe-80c7-40c1-bc28-4ee2693ad4c9', '2026-02-04 08:17:23', NULL, NULL, '2026-02-04 11:17:23', '2026-02-04 11:17:23'),
(9, NULL, 19, 'souza21s@hotmail.com', 'Itamara De Souza Santos', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '201.46.143.93', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.1 Mobile/15E148 Safari/604.1', 'f136dbb7-7975-46ac-b15e-fa1246c101ef', '2026-02-04 08:18:10', NULL, NULL, '2026-02-04 11:18:10', '2026-02-04 11:18:10'),
(10, NULL, 20, 'ana.ferreira.0798@gmail.com', 'Ana Paula Ferreira', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:1b2:2282:1788:5555:8eed:12c7:a852', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'e4ea285f-8c77-43ba-aacd-cf1f64282f98', '2026-02-04 08:18:22', NULL, NULL, '2026-02-04 11:18:22', '2026-02-04 11:18:22'),
(11, NULL, 21, 'francyfelismino@gmail.con', 'Francyelle Felismino Da Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '200.237.158.86', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7 Mobile/15E148 Safari/604.1', 'edc8a926-6112-4292-9134-d448e8917525', '2026-02-04 08:19:24', NULL, NULL, '2026-02-04 11:19:24', '2026-02-04 11:19:24'),
(12, NULL, 22, 'matheus-2011gc@hotmail.com', 'MATEUS GONCALVES DE CASTILHO', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:1dc:4089:1a00:41d9:14:a42:47f8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '37e876a3-69eb-4e8d-993b-1908e427624b', '2026-02-04 08:19:25', NULL, NULL, '2026-02-04 11:19:25', '2026-02-04 11:19:25'),
(13, NULL, 23, 'thaynaraestevaoc@gmail.com', 'Thaynara Jessyca Estevao Cavalcante', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '168.195.215.77', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', 'adb4a8ed-7ead-45c4-a242-908c0e3e0938', '2026-02-04 08:21:03', NULL, NULL, '2026-02-04 11:21:03', '2026-02-04 11:21:03'),
(14, NULL, 24, 'carloskadu86@gmail.com', 'Carlos Eduardo Barbosa dos Santos', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '168.195.215.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', 'fdb20945-2c00-412d-b8b4-5c69386c3eb8', '2026-02-04 08:21:08', NULL, NULL, '2026-02-04 11:21:07', '2026-02-04 11:21:08'),
(15, NULL, 25, 'steffanysoares85@gmail.com', 'Steffany Soares Da Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:788:5c8:c200:fd82:cc11:bf3c:7d4c', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '725c60da-2133-4ab9-b91b-8fdf93644521', '2026-02-04 08:23:12', NULL, NULL, '2026-02-04 11:23:12', '2026-02-04 11:23:12'),
(16, NULL, 26, 'marisa_manh@hotmail.com', 'Marisa Nathyelle Oliveira Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '177.85.129.133', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/144.0.7559.95 Mobile/15E148 Safari/604.1', '75ad1ae2-3ac2-4c4c-be1d-b7a7349cb4b0', '2026-02-04 08:23:22', NULL, NULL, '2026-02-04 11:23:22', '2026-02-04 11:23:22'),
(17, NULL, 27, 'veranianunesft@gmail.com', 'VERANIA DOS SANTOS NUNES', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:6080:3e9:c00:39e4:c2f2:fa1e:d34e', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'b2c113ad-3b12-4b27-b965-452a55e7df60', '2026-02-04 08:28:27', NULL, NULL, '2026-02-04 11:28:26', '2026-02-04 11:28:27'),
(18, NULL, 28, 'oleticiamendes@gmail.com', 'Letícia De Oliveira Mendes', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '201.46.148.87', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', 'ec40d16c-0288-4dcd-bbd0-5e4416318138', '2026-02-04 08:35:57', NULL, NULL, '2026-02-04 11:35:57', '2026-02-04 11:35:57'),
(19, NULL, 29, 'nycole.f@outlook.com', 'Nycole Ferreira Azevedo De Oliveira', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '201.46.204.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '14f1f7f0-524d-4362-b10d-3d46a62fb9ca', '2026-02-04 08:59:40', NULL, NULL, '2026-02-04 11:59:40', '2026-02-04 11:59:40'),
(20, NULL, 30, 'erikalarissasantosilva@outlook.com', 'Erika Larissa S S Araújo', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '170.83.12.212', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.1 Mobile/15E148 Safari/604.1', '1fe6a9a1-4eb9-4648-be7a-c1eb47bd13f3', '2026-02-04 09:02:25', NULL, NULL, '2026-02-04 12:02:25', '2026-02-04 12:02:25'),
(21, NULL, 31, 'daganne.cajueiro@gmail.com', 'Dag Anne Correia Cajueiro', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:788:516:3100:b944:6709:febd:18ae', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'ddc93058-7d81-400b-8694-677c338723ca', '2026-02-04 09:20:32', NULL, NULL, '2026-02-04 12:20:32', '2026-02-04 12:20:32'),
(22, NULL, 32, 'fagundesyasmin@hotmail.com', 'YASMIN FAGUNDES DA SILVA LIMA', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '45.237.94.25', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'cf5c0a8f-b83a-44d3-bd62-906e8bd2117e', '2026-02-04 09:21:20', NULL, NULL, '2026-02-04 12:21:20', '2026-02-04 12:21:20'),
(23, NULL, 33, 'roseanel781@gmail.com', 'Roseane  Ferreira Lima', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:3424:ff2a:6500:c498:4066:dffd:2485', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '966a5f24-0897-40de-94b1-94e6eccf5ca9', '2026-02-04 09:22:30', NULL, NULL, '2026-02-04 12:22:29', '2026-02-04 12:22:30'),
(24, NULL, 34, 'sousaingrid22@outlook.com', 'Ingrid Sousa Vital', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:214:81f6:71b9:e1de:2d2e:dec0:f623', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '09e8fb4c-9f0c-4504-a054-563c9adf23dd', '2026-02-04 09:24:19', NULL, NULL, '2026-02-04 12:24:19', '2026-02-04 12:24:19'),
(25, NULL, 35, 'givaldojunior4321@gmail.com', 'Givaldo de Albuquerque Silva Júnior', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:29b8:5064:e377:81d9:c145:c5a5:6de5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '38724615-fd25-45d4-bace-eb56496ba6a2', '2026-02-04 09:25:28', NULL, NULL, '2026-02-04 12:25:27', '2026-02-04 12:25:28'),
(26, NULL, 36, 'amanda.n.oliveira280@gmail.com', 'Amanda Nunes de Oliveira', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '181.77.7.114', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', 'b2e9cfcd-db66-48d3-96ad-e7351bd26f04', '2026-02-04 09:25:49', NULL, NULL, '2026-02-04 12:25:49', '2026-02-04 12:25:49'),
(27, NULL, 37, 'mbetaniacosta@icloud.com', 'Maria Betania Da Silva Costa', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '179.0.32.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '90df7a7d-19ed-42fa-97e0-f7eba23a2a32', '2026-02-04 09:26:05', NULL, NULL, '2026-02-04 12:26:04', '2026-02-04 12:26:05'),
(28, NULL, 38, 'estefanymvsants@outlook.com', 'Estéfany Maria Vitória dos Santos', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '187.19.173.172', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '38e8c6e4-bd0a-4d88-9ff8-66396b868447', '2026-02-04 09:26:13', NULL, NULL, '2026-02-04 12:26:13', '2026-02-04 12:26:13'),
(29, NULL, 39, 'nadjanecamposrj@gmail.com', 'Naná Da Silva Campos', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '168.195.215.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '84a4c827-ee22-4cfd-9be1-b6a95babf8b1', '2026-02-04 09:26:43', NULL, NULL, '2026-02-04 12:26:43', '2026-02-04 12:26:43'),
(30, NULL, 39, 'nadjanecamposrj@gmail.com', 'NANÁ DA SILVA CAMPOS', 'mail@appcheckin.com.br', 'App Check-in', '🔐 Código de Recuperação de Senha - App Check-in', 'password_recovery', '\n\n\n    \n    \n    \n    App Check-in\n    \n    \n        /* Reset */\n        body, table, td, p, a, li, blockquote {\n            -webkit-text-size-adjust: 100%;\n            -ms-text-size-adjust: 100%;\n        }\n        table, td {\n            mso-table-lspace: 0pt;\n            mso-table-rspace: 0pt;\n        }\n        img {\n            -ms-interpolation-mode: bicubic;\n            border: 0;\n            height: auto;\n            line-height: 100%;\n            outline: none;\n            text-decoration', 'sent', NULL, 'resend', '168.195.215.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '19050de3-6722-44d0-8eca-18b7345ffe93', '2026-02-04 09:27:32', NULL, NULL, '2026-02-04 12:27:31', '2026-02-04 12:27:32'),
(31, NULL, 39, 'nadjanecamposrj@gmail.com', 'NANÁ DA SILVA CAMPOS', 'mail@appcheckin.com.br', 'App Check-in', '🔐 Código de Recuperação de Senha - App Check-in', 'password_recovery', '\n\n\n    \n    \n    \n    App Check-in\n    \n    \n        /* Reset */\n        body, table, td, p, a, li, blockquote {\n            -webkit-text-size-adjust: 100%;\n            -ms-text-size-adjust: 100%;\n        }\n        table, td {\n            mso-table-lspace: 0pt;\n            mso-table-rspace: 0pt;\n        }\n        img {\n            -ms-interpolation-mode: bicubic;\n            border: 0;\n            height: auto;\n            line-height: 100%;\n            outline: none;\n            text-decoration', 'sent', NULL, 'resend', '168.195.215.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', 'e71c6a1f-18b2-407d-94ff-d258c1383ec5', '2026-02-04 09:29:05', NULL, NULL, '2026-02-04 12:29:05', '2026-02-04 12:29:05'),
(32, NULL, 40, 'isinhasilva2004@gmail.com', 'Isabella Gonçalves', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:788:512:6600:3b99:de6f:510a:16bf', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '279eec24-0f26-40b9-9fe3-4253ae1a3996', '2026-02-04 09:29:26', NULL, NULL, '2026-02-04 12:29:26', '2026-02-04 12:29:26'),
(33, NULL, 41, 'rosangelaue@gmail.com', 'Rosângela Silva De Lima', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:788:56c:de00:d869:71fd:e300:f744', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.1 Mobile/15E148 Safari/604.1', '0d2a00b1-c49d-4cb0-b676-6dcc70ba6ad0', '2026-02-04 09:30:18', NULL, NULL, '2026-02-04 12:30:18', '2026-02-04 12:30:18'),
(34, NULL, 42, 'walmervictor@gmail.com', 'Walmer Victor Silva dos Santos', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:389:71e0:f8b4:f080:6bef:2a29:b0af', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'e4cf97cf-2a02-4f95-9b12-d95be55a463b', '2026-02-04 09:31:21', NULL, NULL, '2026-02-04 12:31:21', '2026-02-04 12:31:21'),
(35, NULL, 43, 'luizantonio.bio@hotmail.com', 'Luiz Antônio Caetano Nunes', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '138.97.194.109', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '030a2ae7-4db9-437e-929a-892b5ec5751d', '2026-02-04 09:33:29', NULL, NULL, '2026-02-04 12:33:28', '2026-02-04 12:33:29'),
(36, NULL, 44, 'vivi12ane15@gmail.com', 'Viviane Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '179.0.32.108', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '9c76873f-c42b-4c5e-9441-2fc982eaacd7', '2026-02-04 09:49:31', NULL, NULL, '2026-02-04 12:49:31', '2026-02-04 12:49:31'),
(37, NULL, 45, 'diegorichard25@icloud.com', 'Diego Richard', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:1dc:4089:a000:14d5:398a:eaf4:13df', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '4a100483-a3be-4a91-a65b-1a9f2954fb0e', '2026-02-04 10:02:01', NULL, NULL, '2026-02-04 13:02:01', '2026-02-04 13:02:01'),
(38, NULL, 46, 'nayadm22@gmail.com', 'NAYARA DA SILVA OLIVEIRA', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '201.46.153.61', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'a89f9a3b-7f11-41ca-aa44-2b9ea999ae6f', '2026-02-04 10:06:35', NULL, NULL, '2026-02-04 13:06:35', '2026-02-04 13:06:35'),
(39, NULL, 47, 'karlinny@hotmail.com', 'Karlinny H Lucena', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2a09:bac3:931:266e::3d4:1a', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.1 Mobile/15E148 Safari/604.1', 'bae35213-ee26-4a33-8b01-a0168bba010d', '2026-02-04 10:08:54', NULL, NULL, '2026-02-04 13:08:54', '2026-02-04 13:08:54'),
(40, NULL, 48, 'nutricionista_vaz@hotmail.com', 'Carla De Jesus Vaz Lopes', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:14d:12b0:8149:cd8b:6f96:da17:5efc', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '9b9613ab-2c2e-41bb-b0e8-06e754be49ef', '2026-02-04 10:14:47', NULL, NULL, '2026-02-04 13:14:46', '2026-02-04 13:14:47'),
(41, NULL, 49, 'cavalcantirafaelle@gmail.com', 'Rafaelle Cavalcanti', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '181.77.47.33', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '82d66a7f-f873-410f-84c8-e0d82535c20d', '2026-02-04 10:26:27', NULL, NULL, '2026-02-04 13:26:26', '2026-02-04 13:26:27'),
(42, NULL, 50, 'tytanataline@gmail.com', 'nataline santos ferreira', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '177.131.225.35', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'd55a3bd9-b930-4c2b-ae37-e2b3e25ed30e', '2026-02-04 10:27:47', NULL, NULL, '2026-02-04 13:27:46', '2026-02-04 13:27:47'),
(43, NULL, 51, 'bruninha_al@hotmail.com', 'Bruna barboza Dos Santos', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:1dc:4087:c000:f962:f173:63c9:a333', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', 'ab31cef1-9dd3-4d0f-a741-ddf6a70c29b7', '2026-02-04 10:31:09', NULL, NULL, '2026-02-04 13:31:08', '2026-02-04 13:31:09'),
(44, NULL, 52, 'jr.gunswrk@icloud.com', 'Neusvaldo pedro da silva junior', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '190.102.52.153', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_0_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/144.0.7559.95 Mobile/15E148 Safari/604.1', '13a65b1a-d069-4ddf-aa1f-ad0ce982a790', '2026-02-04 11:05:19', NULL, NULL, '2026-02-04 14:05:19', '2026-02-04 14:05:19'),
(45, NULL, 53, 'karllose514@gmail.com', 'Karllos Eduardo', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '168.195.215.210', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', 'a53219f9-2fb8-4e3b-8feb-d429bb3f7e83', '2026-02-04 11:13:10', NULL, NULL, '2026-02-04 14:13:10', '2026-02-04 14:13:10'),
(46, NULL, 54, 'vitormanoel1897@gmail.com', 'VITOR MANOEL IZIDIO SEVERIANO', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:1dc:4080:7000:7c49:7dee:2deb:10bb', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2f735f0a-599a-4c38-8924-d5bc6bbfe852', '2026-02-04 11:15:44', NULL, NULL, '2026-02-04 14:15:44', '2026-02-04 14:15:44'),
(47, NULL, 55, 'marianeuza2006@gmail.com', 'Maria Neuza Da Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '45.164.54.113', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', 'e1c3b657-7852-48d9-9d5b-8611a64f4e29', '2026-02-04 11:32:25', NULL, NULL, '2026-02-04 14:32:24', '2026-02-04 14:32:25'),
(48, NULL, 56, 'mandivinevoliveira@gmail.com', 'Maria Andívine Vital De Oliveira', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '201.49.252.158', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '00992a1c-0450-4100-86f6-b714e6ec8a19', '2026-02-04 11:50:00', NULL, NULL, '2026-02-04 14:50:00', '2026-02-04 14:50:00'),
(49, NULL, 57, 'rezenderafael2014@gmail.com', 'Rafael Rezende Da Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '190.102.52.160', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '321a2d6e-dee4-4af2-8eae-d88d5b4c4e9a', '2026-02-04 11:58:47', NULL, NULL, '2026-02-04 14:58:47', '2026-02-04 14:58:47');
INSERT INTO `email_logs` (`id`, `tenant_id`, `usuario_id`, `to_email`, `to_name`, `from_email`, `from_name`, `subject`, `email_type`, `body_preview`, `status`, `error_message`, `provider`, `ip_address`, `user_agent`, `message_id`, `sent_at`, `opened_at`, `clicked_at`, `created_at`, `updated_at`) VALUES
(50, NULL, 58, 'martins11antonio2022@gmail.com', 'Antonio Martins Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:18:789d:3f3c:418c:4e17:1656:4d90', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.1 Mobile/15E148 Safari/604.1', '16911ea3-1c00-4615-a6a9-a537acbf63e9', '2026-02-04 12:26:43', NULL, NULL, '2026-02-04 15:26:43', '2026-02-04 15:26:43'),
(51, NULL, 59, 'joaoguilhermem12345@gmail.com', 'João Guilherme Menezes Dos Santos', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '45.175.230.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '1808db13-372f-4445-a38c-bf0be9ec9664', '2026-02-04 13:12:27', NULL, NULL, '2026-02-04 16:12:27', '2026-02-04 16:12:27'),
(52, NULL, 60, 'elvistecladista@hotmail.com', 'Elvis Oliveira dos Santos', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '190.102.52.157', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'cef7070b-f202-4ab6-b97c-a77c9f6f4ab7', '2026-02-04 13:12:37', NULL, NULL, '2026-02-04 16:12:37', '2026-02-04 16:12:37'),
(53, NULL, 60, 'elvistecladista@hotmail.com', 'ELVIS OLIVEIRA DOS SANTOS', 'mail@appcheckin.com.br', 'App Check-in', '🔐 Código de Recuperação de Senha - App Check-in', 'password_recovery', '\n\n\n    \n    \n    \n    App Check-in\n    \n    \n        /* Reset */\n        body, table, td, p, a, li, blockquote {\n            -webkit-text-size-adjust: 100%;\n            -ms-text-size-adjust: 100%;\n        }\n        table, td {\n            mso-table-lspace: 0pt;\n            mso-table-rspace: 0pt;\n        }\n        img {\n            -ms-interpolation-mode: bicubic;\n            border: 0;\n            height: auto;\n            line-height: 100%;\n            outline: none;\n            text-decoration', 'sent', NULL, 'resend', '190.102.52.157', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '6c2359a9-3bc7-4ac6-8221-65499454de23', '2026-02-04 13:13:34', NULL, NULL, '2026-02-04 16:13:34', '2026-02-04 16:13:34'),
(54, NULL, 62, 'evily.silva@arapiraca.ufal.br', 'Evily Emanuelle Da Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '45.175.230.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 'ba8bbaf9-274c-4bcb-a9eb-42b8e3cf63a3', '2026-02-04 17:13:43', NULL, NULL, '2026-02-04 20:13:42', '2026-02-04 20:13:43'),
(55, NULL, 63, 'karolinamonteiro23@hotmail.com', 'Maria Karolina Sampaio Monteiro', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '187.110.113.181', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '28f1e3d8-cc55-446a-af9a-579ea27d66a0', '2026-02-04 20:20:23', NULL, NULL, '2026-02-04 23:20:23', '2026-02-04 23:20:23'),
(56, NULL, 64, 'sslvcacau@gmail.com', 'Claudilene Da Silva Santos', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '45.181.65.85', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', 'ffd7475a-3e7b-4fb2-8df7-1dfcca67f3b1', '2026-02-04 20:23:40', NULL, NULL, '2026-02-04 23:23:39', '2026-02-04 23:23:40'),
(57, NULL, 65, 'henriquepereiraf@gmail.com', 'Henrique Pereira Freitas De Mendonça', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '45.181.65.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '9041200d-d53a-4c09-a784-a7c558069c4a', '2026-02-04 20:32:48', NULL, NULL, '2026-02-04 23:32:48', '2026-02-04 23:32:48'),
(58, NULL, 66, 'jerlane.cavalcante@hotmail.com', 'Jerlane Cavalcante', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '45.71.188.143', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '5ff0b5a7-d29a-4725-847a-588a4b2563ca', '2026-02-04 23:31:57', NULL, NULL, '2026-02-05 02:31:57', '2026-02-05 02:31:57'),
(59, NULL, 67, 'aryene.pereira.silva@gmail.com', 'Aryene Pereira', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '186.235.131.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '1e596696-071f-4c9e-bd5e-20bcd4d55c53', '2026-02-05 11:25:59', NULL, NULL, '2026-02-05 14:25:59', '2026-02-05 14:25:59'),
(60, NULL, 70, 'arnonvictor7@gmail.com', 'Arnon Victor Silva De Oliveira', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '179.0.33.190', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', 'a62b8e8e-cef1-48e0-816a-fba65824fd9a', '2026-02-05 19:44:08', NULL, NULL, '2026-02-05 22:44:07', '2026-02-05 22:44:08'),
(61, NULL, 70, 'arnonvictor7@gmail.com', 'ARNON VICTOR SILVA DE OLIVEIRA', 'mail@appcheckin.com.br', 'App Check-in', '🔐 Código de Recuperação de Senha - App Check-in', 'password_recovery', '\n\n\n    \n    \n    \n    App Check-in\n    \n    \n        /* Reset */\n        body, table, td, p, a, li, blockquote {\n            -webkit-text-size-adjust: 100%;\n            -ms-text-size-adjust: 100%;\n        }\n        table, td {\n            mso-table-lspace: 0pt;\n            mso-table-rspace: 0pt;\n        }\n        img {\n            -ms-interpolation-mode: bicubic;\n            border: 0;\n            height: auto;\n            line-height: 100%;\n            outline: none;\n            text-decoration', 'sent', NULL, 'resend', '179.0.33.190', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', 'b7aef590-8fff-421a-a4b8-0a354b6a404a', '2026-02-05 19:45:52', NULL, NULL, '2026-02-05 22:45:52', '2026-02-05 22:45:52'),
(62, NULL, 71, 'jjuhliana00@gmail.com', 'Juliana Fernanda', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '177.131.225.35', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '8d1fb9d3-80d4-4ed5-867c-2078f9357815', '2026-02-06 09:48:38', NULL, NULL, '2026-02-06 12:48:37', '2026-02-06 12:48:38'),
(63, NULL, 72, 'nataly.prof.efe@gmail.com', 'Nataly Maria Costa Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:1dc:1180:380:e4b1:1f51:8bcb:ada0', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'db47f55c-475f-4e34-a971-1bd47f428531', '2026-02-06 10:22:52', NULL, NULL, '2026-02-06 13:22:51', '2026-02-06 13:22:52'),
(64, NULL, 73, 'roneide4santos@gmail.com', 'Roneide Santos', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '45.190.254.82', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', 'ce2cd840-2de6-4f75-aa61-22ede6805bb2', '2026-02-08 10:53:58', NULL, NULL, '2026-02-08 13:53:58', '2026-02-08 13:53:58'),
(65, NULL, 74, 'lucasrafael.gostoso24@gmail.com', 'Lucas Rafael Dos Santos Batista', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '149.78.186.120', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'cdd1047c-3966-4554-af4e-962b25ada0d3', '2026-02-08 10:56:23', NULL, NULL, '2026-02-08 13:56:23', '2026-02-08 13:56:23'),
(66, NULL, 75, 'andreza-fox2010@hotmail.com', 'Andreza Ferreira de Farias', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:18:7894:42e4:1892:4738:cb2f:f238', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '8b15dfc2-5057-4871-88a2-e1c3db51d406', '2026-02-08 10:58:13', NULL, NULL, '2026-02-08 13:58:13', '2026-02-08 13:58:13'),
(67, NULL, 76, 'mayckonn@hotmail.com', 'MAYCKONN DOUGLAS FREIRE BARBOSA', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '189.94.31.84', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '5a48d636-3d69-4c16-8db1-9bcf48eec5c1', '2026-02-08 11:00:53', NULL, NULL, '2026-02-08 14:00:53', '2026-02-08 14:00:53'),
(68, NULL, 77, 'luizbola2915@gmail.com', 'Luiz Filho Da Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '177.131.233.134', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '7e129640-d803-4023-8789-c6b819129641', '2026-02-08 11:01:38', NULL, NULL, '2026-02-08 14:01:38', '2026-02-08 14:01:38'),
(69, NULL, 78, 'adilsonangela1961@gmail.com', 'Adilson Monteiro da Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:6080:3ed:b500:d610:3951:2aad:7866', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '899b38d8-b863-4b18-9282-8e6fed9cfde6', '2026-02-08 11:10:46', NULL, NULL, '2026-02-08 14:10:45', '2026-02-08 14:10:46'),
(70, NULL, 79, 'josericardosales09@gmail.com', 'Jose Ricardo Sales Sales', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:14d:12b2:84ce:8344:709f:8471:cab', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '93693fad-f6ce-43ce-8844-ac3e1e87f9f9', '2026-02-08 12:45:35', NULL, NULL, '2026-02-08 15:45:35', '2026-02-08 15:45:35'),
(71, NULL, 80, 'janny_god@hotmail.com', 'JANIEIDE DOS SANTOS SILVA', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:6080:3f7:3800:68ce:be0c:3414:40b2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36 EdgA/144.0.0.0', '51bac065-9f31-44b9-a5a6-863585b0c487', '2026-02-08 13:38:13', NULL, NULL, '2026-02-08 16:38:13', '2026-02-08 16:38:13'),
(72, NULL, 81, 'diaswerlisson@gmail.com', 'WERLISSON SANTOS DIAS', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '177.131.193.80', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '5def66a8-2c4a-41f0-bb67-ea326ed8c929', '2026-02-08 14:16:56', NULL, NULL, '2026-02-08 17:16:56', '2026-02-08 17:16:56'),
(73, NULL, 82, 'rosangelarochasiqueira11@gmail.com', 'Rosangela Da Rocha Siqueira Tavares', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '190.102.54.54', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0.0.0 Mobile Safari/537.36', 'fcdf97db-ac7d-4938-aec1-f3bcf60aedf2', '2026-02-08 15:24:21', NULL, NULL, '2026-02-08 18:24:21', '2026-02-08 18:24:21'),
(74, NULL, 83, 'elishalomrodrigues@gmail.com', 'Elicleide Rodrigues Da Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '45.181.65.72', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', 'b237e2f6-f416-42f4-96d7-8763f0ccfa24', '2026-02-08 17:09:45', NULL, NULL, '2026-02-08 20:09:45', '2026-02-08 20:09:45'),
(75, NULL, 84, 'lamenia.oliveira.35@outlook.com', 'Lamenia Rodrigues Gomes de oliveira', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '179.0.34.58', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/144.0.7559.95 Mobile/15E148 Safari/604.1', '49568583-b704-46a1-b118-1609d1c3c544', '2026-02-08 17:34:16', NULL, NULL, '2026-02-08 20:34:16', '2026-02-08 20:34:16'),
(76, NULL, 85, 'kellycscb@gmail.com', 'Kelly Cristina Santos Costa Barbosa', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '131.196.45.67', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '090d087e-c93b-4be0-85e2-6b0bb7bba0ae', '2026-02-08 19:12:39', NULL, NULL, '2026-02-08 22:12:39', '2026-02-08 22:12:39'),
(77, NULL, 86, 'jose97fortunato@gmail.com', 'José Murilo Fortunato Pereira', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '187.65.18.193', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '5478a61f-af37-416c-b909-36f71fa3d926', '2026-02-09 05:47:37', NULL, NULL, '2026-02-09 08:47:36', '2026-02-09 08:47:37'),
(78, NULL, 87, 'manuellysilva574@gmail.com', 'Manuelly Nunes da Silva', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '2804:788:51a:4d00:584d:729f:d875:d894', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '183c9226-3cfa-46b8-b68e-60d55160f2f1', '2026-02-09 12:37:42', NULL, NULL, '2026-02-09 15:37:41', '2026-02-09 15:37:42'),
(79, NULL, 88, 'gmexcursoes@gmail.com', 'Dalvan Ferreira', 'mail@appcheckin.com.br', 'App Check-in', '🎉 Bem-vindo ao AppCheckin - Seus Dados de Acesso', 'welcome_aluno', '\n\n\n    \n    \n    Bem-vindo ao AppCheckin\n    \n        body {\n            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;\n            line-height: 1.6;\n            color: #333;\n            margin: 0;\n            padding: 0;\n            background-color: #f4f4f4;\n        }\n        .container {\n            max-width: 600px;\n            margin: 20px auto;\n            background-color: #ffffff;\n            border-radius: 8px;\n            overfl', 'sent', NULL, 'resend', '187.110.90.112', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', '5db8f3f9-7fba-4d0a-a685-a22c10cf2f84', '2026-02-09 13:42:55', NULL, NULL, '2026-02-09 16:42:54', '2026-02-09 16:42:55');

-- --------------------------------------------------------

--
-- Estrutura para tabela `formas_pagamento`
--

CREATE TABLE IF NOT EXISTS `formas_pagamento` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `percentual_desconto` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentual que fica com operadora (ex: 3.50 para 3.5%)',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `formas_pagamento`
--

INSERT INTO `formas_pagamento` (`id`, `nome`, `descricao`, `percentual_desconto`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'Dinheiro', 'Pagamento em dinheiro', 0.00, 1, '2025-11-25 00:33:01', '2026-01-06 01:55:39'),
(2, 'Pix', 'Pagamento via PIX', 0.00, 1, '2025-11-25 00:33:01', '2026-01-06 01:55:39'),
(3, 'Débito', NULL, 2.50, 1, '2025-11-25 00:33:01', '2025-11-25 00:39:41'),
(4, 'Crédito á  vista', NULL, 3.50, 1, '2025-11-25 00:33:01', '2025-11-25 00:39:33'),
(5, 'Crédito parcelado 2x', NULL, 4.50, 1, '2025-11-25 00:33:01', '2025-11-25 00:39:48'),
(6, 'Crédito parcelado 3x', NULL, 5.00, 1, '2025-11-25 00:33:01', '2025-11-25 00:39:53'),
(7, 'Transferência bancária', NULL, 0.00, 1, '2025-11-25 00:33:01', '2025-11-25 00:40:12'),
(8, 'Boleto', 'Boleto bancário', 2.00, 1, '2025-11-25 00:33:01', '2026-01-06 01:55:39'),
(9, 'Cartão', 'Cartão de crédito ou débito', 0.00, 1, '2026-01-06 01:55:39', '2026-01-06 01:55:39'),
(10, 'Operadora', 'Pagamento via operadora de cartões', 0.00, 1, '2026-01-06 01:55:39', '2026-01-06 01:55:39'),
(11, 'Transferência', 'Transferência bancária', 0.00, 1, '2026-01-06 01:55:39', '2026-01-06 01:55:39'),
(12, 'Cheque', 'Pagamento via cheque', 0.00, 1, '2026-01-06 01:55:39', '2026-01-06 01:55:39'),
(13, 'Crédito Loja', 'Crédito da loja/academia', 0.00, 1, '2026-01-06 01:55:39', '2026-01-06 01:55:39');

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_planos`
--

CREATE TABLE IF NOT EXISTS `historico_planos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `plano_anterior_id` int(11) DEFAULT NULL,
  `plano_novo_id` int(11) DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_vencimento` date DEFAULT NULL,
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `motivo` varchar(100) DEFAULT NULL COMMENT 'Tipos: novo (primeiro plano), renovacao (mesmo plano), upgrade (plano melhor), downgrade (plano menor), cancelamento (removeu plano)',
  `observacoes` text DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL COMMENT 'ID do admin que fez a alteraÃ§Ã£o',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `historico_planos`
--

INSERT INTO `historico_planos` (`id`, `usuario_id`, `plano_anterior_id`, `plano_novo_id`, `data_inicio`, `data_vencimento`, `valor_pago`, `motivo`, `observacoes`, `criado_por`, `created_at`) VALUES
(7, 15, NULL, 2, '2026-02-04', '2026-03-06', 100.00, 'nova', NULL, 2, '2026-02-04 17:27:51'),
(8, 15, NULL, 2, '2026-02-04', '2026-03-06', 100.00, 'nova', NULL, 2, '2026-02-04 17:27:55'),
(9, 15, NULL, 2, '2026-02-04', '2026-03-06', 100.00, 'nova', NULL, 2, '2026-02-04 17:29:09'),
(10, 15, NULL, 2, '2026-02-04', '2026-03-06', 100.00, 'nova', NULL, 2, '2026-02-04 17:29:54'),
(11, 15, NULL, 15, '2026-02-07', '2026-03-09', 0.00, 'nova', NULL, 2, '2026-02-07 05:02:31'),
(12, 15, NULL, 15, '2026-02-08', '2026-03-10', 0.00, 'nova', NULL, 2, '2026-02-08 03:19:57'),
(13, 3, NULL, 17, '2026-02-09', '2026-03-11', 0.00, 'nova', NULL, 3, '2026-02-09 15:32:07');

-- --------------------------------------------------------

--
-- Estrutura para tabela `horarios`
--

CREATE TABLE IF NOT EXISTS `horarios` (
  `id` int(11) NOT NULL,
  `dia_id` int(11) NOT NULL,
  `hora` time NOT NULL,
  `horario_inicio` time NOT NULL DEFAULT '06:00:00',
  `horario_fim` time NOT NULL DEFAULT '07:00:00',
  `limite_alunos` int(11) NOT NULL DEFAULT 30,
  `tolerancia_minutos` int(11) NOT NULL DEFAULT 10,
  `tolerancia_antes_minutos` int(11) NOT NULL DEFAULT 480,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `inscricoes_turmas`
--

CREATE TABLE IF NOT EXISTS `inscricoes_turmas` (
  `id` int(11) NOT NULL,
  `turma_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_inscricao` timestamp NULL DEFAULT current_timestamp(),
  `data_conclusao` date DEFAULT NULL,
  `presencas` int(11) DEFAULT 0,
  `faltas` int(11) DEFAULT 0,
  `status` enum('ativa','finalizada','cancelada') DEFAULT 'ativa',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `matriculas`
--

CREATE TABLE IF NOT EXISTS `matriculas` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `aluno_id` int(11) DEFAULT NULL COMMENT 'FK para alunos (perfil do aluno)',
  `plano_id` int(11) NOT NULL,
  `plano_ciclo_id` int(11) DEFAULT NULL,
  `tipo_cobranca` enum('avulso','recorrente') DEFAULT 'avulso',
  `data_matricula` date NOT NULL,
  `data_inicio` date NOT NULL,
  `data_vencimento` date NOT NULL,
  `dia_vencimento` tinyint(2) DEFAULT NULL COMMENT 'Dia do mês para vencimento (1-31)',
  `periodo_teste` tinyint(1) DEFAULT 0 COMMENT '1 = período gratuito, 0 = cobrança normal',
  `data_inicio_cobranca` date DEFAULT NULL COMMENT 'Data que iniciará a cobrança (após período teste)',
  `proxima_data_vencimento` date DEFAULT NULL COMMENT 'Data real do próximo vencimento (controla acesso e bloqueio check-in)',
  `valor` decimal(10,2) NOT NULL,
  `status_id` int(11) DEFAULT NULL COMMENT 'FK para status_matricula',
  `motivo_id` int(11) DEFAULT NULL COMMENT 'FK para motivo_matricula',
  `matricula_anterior_id` int(11) DEFAULT NULL,
  `plano_anterior_id` int(11) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `cancelado_por` int(11) DEFAULT NULL,
  `data_cancelamento` date DEFAULT NULL,
  `motivo_cancelamento` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registra as matrÃ­culas dos alunos nos planos - separado do cadastro bÃ¡sico';

--
-- Despejando dados para a tabela `matriculas`
--

INSERT INTO `matriculas` (`id`, `tenant_id`, `aluno_id`, `plano_id`, `plano_ciclo_id`, `tipo_cobranca`, `data_matricula`, `data_inicio`, `data_vencimento`, `dia_vencimento`, `periodo_teste`, `data_inicio_cobranca`, `proxima_data_vencimento`, `valor`, `status_id`, `motivo_id`, `matricula_anterior_id`, `plano_anterior_id`, `observacoes`, `criado_por`, `cancelado_por`, `data_cancelamento`, `motivo_cancelamento`, `created_at`, `updated_at`) VALUES
(47, 2, 1, 14, 46, 'avulso', '2026-02-08', '2026-02-08', '2026-03-10', 5, 0, NULL, '2026-03-10', 0.50, 1, 1, NULL, NULL, NULL, 15, NULL, NULL, NULL, '2026-02-09 02:29:53', '2026-02-09 02:30:38'),
(48, 3, 72, 17, NULL, 'avulso', '2026-02-09', '2026-02-09', '2026-03-11', NULL, 1, '2026-03-01', '2026-03-11', 0.00, 1, 1, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-02-09 15:32:07', '2026-02-09 15:32:23');

-- --------------------------------------------------------

--
-- Estrutura para tabela `metodos_pagamento`
--

CREATE TABLE IF NOT EXISTS `metodos_pagamento` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `icone` varchar(50) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `metodos_pagamento`
--

INSERT INTO `metodos_pagamento` (`id`, `codigo`, `nome`, `icone`, `ativo`, `criado_em`) VALUES
(1, 'credit_card', 'Cartão de Crédito', NULL, 1, '2026-02-07 20:29:27'),
(2, 'debit_card', 'Cartão de Débito', NULL, 1, '2026-02-07 20:29:27'),
(3, 'pix', 'PIX', NULL, 1, '2026-02-07 20:29:27'),
(4, 'boleto', 'Boleto Bancário', NULL, 1, '2026-02-07 20:29:27'),
(5, 'account_money', 'Saldo em Conta', NULL, 1, '2026-02-07 20:29:27'),
(6, 'transfer', 'Transferência', NULL, 1, '2026-02-07 20:29:27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `modalidades`
--

CREATE TABLE IF NOT EXISTS `modalidades` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `cor` varchar(7) DEFAULT NULL COMMENT 'Cor em hexadecimal para identificação visual',
  `icone` varchar(50) DEFAULT NULL COMMENT 'Nome do ícone para exibição',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `modalidades`
--

INSERT INTO `modalidades` (`id`, `tenant_id`, `nome`, `descricao`, `cor`, `icone`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 2, 'Natação', 'Aulas de natação para todas as idades', '#3b82f6', 'swim', 1, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(2, 2, 'CrossFit', '', '#10b981', 'weight-lifter', 0, '2026-01-21 18:50:07', '2026-01-29 03:37:36'),
(3, 3, 'Natação', '', '#3b82f6', 'swim', 1, '2026-02-06 12:15:02', '2026-02-06 12:15:14');

-- --------------------------------------------------------

--
-- Estrutura para tabela `motivo_matricula`
--

CREATE TABLE IF NOT EXISTS `motivo_matricula` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `ordem` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `motivo_matricula`
--

INSERT INTO `motivo_matricula` (`id`, `codigo`, `nome`, `descricao`, `ordem`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'nova', 'Nova', 'Primeira matrícula do aluno na modalidade', 1, 1, '2026-01-29 02:56:15', '2026-01-29 02:56:15'),
(2, 'renovacao', 'Renovação', 'Renovação do mesmo plano', 2, 1, '2026-01-29 02:56:15', '2026-01-29 02:56:15'),
(3, 'upgrade', 'Upgrade', 'Mudança para um plano superior', 3, 1, '2026-01-29 02:56:15', '2026-01-29 02:56:15'),
(4, 'downgrade', 'Downgrade', 'Mudança para um plano inferior', 4, 1, '2026-01-29 02:56:15', '2026-01-29 02:56:15');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos_contrato`
--

CREATE TABLE IF NOT EXISTS `pagamentos_contrato` (
  `id` int(11) NOT NULL,
  `tenant_plano_id` int(11) NOT NULL COMMENT 'FK para tenant_planos_sistema (contrato da academia com plano do sistema)',
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status_pagamento_id` int(11) NOT NULL DEFAULT 1,
  `forma_pagamento_id` int(11) DEFAULT NULL,
  `comprovante` varchar(255) DEFAULT NULL COMMENT 'Caminho do arquivo de comprovante',
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `pagamentos_contrato`
--

INSERT INTO `pagamentos_contrato` (`id`, `tenant_plano_id`, `valor`, `data_vencimento`, `data_pagamento`, `status_pagamento_id`, `forma_pagamento_id`, `comprovante`, `observacoes`, `created_at`, `updated_at`) VALUES
(3, 6, 0.00, '2026-01-20', '2026-01-20', 2, 2, NULL, 'Baixa Manual', '2026-01-20 15:17:34', '2026-01-20 15:27:11'),
(4, 6, 0.00, '2026-02-19', '2026-01-20', 2, 2, NULL, 'Baixa Manual', '2026-01-20 15:27:11', '2026-01-20 17:28:01'),
(5, 6, 0.00, '2026-03-21', NULL, 1, 2, NULL, 'Pagamento gerado automaticamente (30 dias)', '2026-01-20 17:28:01', '2026-01-20 17:28:01');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos_mercadopago`
--

CREATE TABLE IF NOT EXISTS `pagamentos_mercadopago` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `matricula_id` int(11) NOT NULL,
  `aluno_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `payment_id` varchar(100) NOT NULL,
  `external_reference` varchar(100) DEFAULT NULL,
  `preference_id` varchar(100) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `status_detail` varchar(100) DEFAULT NULL,
  `transaction_amount` decimal(10,2) NOT NULL,
  `payment_method_id` varchar(50) DEFAULT NULL,
  `payment_type_id` varchar(50) DEFAULT NULL,
  `installments` int(11) DEFAULT 1,
  `date_approved` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `payer_email` varchar(255) DEFAULT NULL,
  `payer_identification_type` varchar(20) DEFAULT NULL,
  `payer_identification_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `pagamentos_mercadopago`
--

INSERT INTO `pagamentos_mercadopago` (`id`, `tenant_id`, `matricula_id`, `aluno_id`, `usuario_id`, `payment_id`, `external_reference`, `preference_id`, `status`, `status_detail`, `transaction_amount`, `payment_method_id`, `payment_type_id`, `installments`, `date_approved`, `date_created`, `payer_email`, `payer_identification_type`, `payer_identification_number`, `created_at`, `updated_at`) VALUES
(9, 2, 69, 6, 10, '145272097072', 'MAT-69-1770488840', NULL, 'cancelled', 'expired', 0.50, 'pix', 'bank_transfer', 1, NULL, '2026-02-07 14:32:48', NULL, NULL, NULL, '2026-02-08 18:35:42', '2026-02-08 18:35:42'),
(10, 2, 43, 1, 15, '144754044893', 'MAT-43-1770600530', NULL, 'approved', 'accredited', 0.50, 'pix', 'bank_transfer', 1, '2026-02-08 21:30:43', '2026-02-08 21:30:23', NULL, NULL, NULL, '2026-02-09 01:30:24', '2026-02-09 01:30:44'),
(11, 2, 44, 1, 15, '144757074039', 'MAT-44-1770602775', NULL, 'approved', 'accredited', 0.50, 'pix', 'bank_transfer', 1, '2026-02-08 22:07:17', '2026-02-08 22:06:47', NULL, NULL, NULL, '2026-02-09 02:06:48', '2026-02-09 02:07:18'),
(12, 2, 45, 1, 15, '145444403962', 'MAT-45-1770603100', NULL, 'approved', 'accredited', 0.50, 'pix', 'bank_transfer', 1, '2026-02-08 22:12:17', '2026-02-08 22:12:00', NULL, NULL, NULL, '2026-02-09 02:12:01', '2026-02-09 02:12:18'),
(13, 2, 46, 1, 15, '144758176239', 'MAT-46-1770603416', NULL, 'approved', 'accredited', 0.50, 'pix', 'bank_transfer', 1, '2026-02-08 22:18:14', '2026-02-08 22:17:16', NULL, NULL, NULL, '2026-02-09 02:17:17', '2026-02-09 02:18:15'),
(14, 2, 47, 1, 15, '145445665632', 'MAT-47-1770604193', NULL, 'approved', 'accredited', 0.50, 'pix', 'bank_transfer', 1, '2026-02-08 22:30:37', '2026-02-08 22:30:15', NULL, NULL, NULL, '2026-02-09 02:30:17', '2026-02-09 02:33:02');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos_plano`
--

CREATE TABLE IF NOT EXISTS `pagamentos_plano` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `matricula_id` int(11) NOT NULL,
  `aluno_id` int(11) DEFAULT NULL COMMENT 'FK para alunos',
  `plano_id` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status_pagamento_id` int(11) NOT NULL DEFAULT 1 COMMENT '1=Aguardando, 2=Pago, 3=Atrasado, 4=Cancelado',
  `forma_pagamento_id` int(11) DEFAULT NULL,
  `comprovante` varchar(255) DEFAULT NULL COMMENT 'Caminho do arquivo de comprovante',
  `observacoes` text DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL COMMENT 'Admin que criou',
  `baixado_por` int(11) DEFAULT NULL COMMENT 'Admin que deu baixa',
  `tipo_baixa_id` int(11) DEFAULT NULL COMMENT 'Tipo de baixa do pagamento',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `pagamentos_plano`
--

INSERT INTO `pagamentos_plano` (`id`, `tenant_id`, `matricula_id`, `aluno_id`, `plano_id`, `valor`, `data_vencimento`, `data_pagamento`, `status_pagamento_id`, `forma_pagamento_id`, `comprovante`, `observacoes`, `criado_por`, `baixado_por`, `tipo_baixa_id`, `created_at`, `updated_at`) VALUES
(29, 2, 47, 1, 14, 0.50, '2026-02-08', '2026-02-08', 2, 2, NULL, 'Pago via Mercado Pago - ID: 145445665632', NULL, NULL, 2, '2026-02-09 02:30:38', '2026-02-09 02:30:38'),
(30, 2, 47, 1, 14, 0.50, '2026-02-08', '2026-02-08', 2, 2, NULL, 'Pago via Mercado Pago - ID: 145445665632', NULL, NULL, 2, '2026-02-09 02:33:02', '2026-02-09 02:33:02');

-- --------------------------------------------------------

--
-- Estrutura para tabela `papeis`
--

CREATE TABLE IF NOT EXISTS `papeis` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `nivel` int(11) NOT NULL DEFAULT 0 COMMENT 'Nível hierárquico: maior = mais permissões',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Papéis que um usuário pode ter em cada tenant';

--
-- Despejando dados para a tabela `papeis`
--

INSERT INTO `papeis` (`id`, `nome`, `descricao`, `nivel`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'aluno', 'Aluno que faz check-in nas aulas', 10, 1, '2026-01-29 02:52:14', '2026-01-29 02:52:14'),
(2, 'professor', 'Professor que confirma presença dos alunos', 50, 1, '2026-01-29 02:52:14', '2026-01-29 02:52:14'),
(3, 'admin', 'Administrador do tenant com acesso total', 100, 1, '2026-01-29 02:52:14', '2026-01-29 02:52:14'),
(4, 'super_admin', 'Super administrador com acesso total ao sistema', 200, 1, '2026-01-29 02:52:26', '2026-01-29 02:52:26');

-- --------------------------------------------------------

--
-- Estrutura para tabela `planos`
--

CREATE TABLE IF NOT EXISTS `planos` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `modalidade_id` int(11) NOT NULL,
  `checkins_semanais` int(11) NOT NULL DEFAULT 3 COMMENT 'Quantidade de checkins permitidos por semana',
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL,
  `duracao_dias` int(11) NOT NULL COMMENT 'DuraÃ§Ã£o em dias (30, 90, 365, etc)',
  `ativo` tinyint(1) DEFAULT 1,
  `atual` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Indica se o plano estÃ¡ disponÃ­vel para novos contratos',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `planos`
--

INSERT INTO `planos` (`id`, `tenant_id`, `modalidade_id`, `checkins_semanais`, `nome`, `descricao`, `valor`, `duracao_dias`, `ativo`, `atual`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 7, '1x por Semana', '', 70.00, 30, 1, 1, '2026-01-20 17:44:09', '2026-01-29 19:56:04'),
(2, 2, 1, 2, '2x por Semana', '', 100.00, 30, 1, 1, '2026-01-20 17:44:43', '2026-01-20 17:44:43'),
(3, 2, 1, 3, '3x por Semana', '', 120.00, 30, 1, 1, '2026-01-20 17:45:01', '2026-01-20 17:45:01'),
(4, 2, 1, 1, 'teste', '', 70.00, 30, 0, 1, '2026-01-20 17:45:18', '2026-01-20 17:45:53'),
(5, 2, 2, 2, '2x por Semana', NULL, 150.00, 30, 0, 1, '2026-01-21 18:50:07', '2026-02-08 18:12:33'),
(6, 2, 1, 7, '7x por Semana', '', 220.00, 30, 1, 1, '2026-01-29 03:09:57', '2026-02-08 18:55:06'),
(7, 3, 3, 1, '1x por Semana', NULL, 70.00, 30, 1, 1, '2026-02-06 12:15:02', '2026-02-06 12:15:02'),
(8, 3, 3, 2, '2x por Semana', NULL, 120.00, 30, 1, 1, '2026-02-06 12:15:02', '2026-02-06 12:15:02'),
(9, 3, 3, 3, '3x por Semana', NULL, 150.00, 30, 1, 1, '2026-02-06 12:15:02', '2026-02-06 12:15:02'),
(10, 3, 3, 4, '4x por Semana', '', 190.00, 30, 0, 0, '2026-02-06 12:15:02', '2026-02-09 14:36:10'),
(11, 3, 3, 5, '5x por Semana', '', 220.00, 30, 0, 0, '2026-02-06 12:15:02', '2026-02-09 14:36:18'),
(12, 3, 3, 1, '1x Temp', '', 0.00, 30, 1, 1, '2026-02-06 12:17:35', '2026-02-09 15:20:31'),
(13, 2, 1, 3, '3x por Semana', 'Plano teste MP', 1.00, 30, 1, 1, '2026-02-07 02:29:40', '2026-02-07 02:29:40'),
(14, 2, 1, 1, '1x por Semana', '', 50.00, 30, 1, 1, '2026-02-07 03:10:57', '2026-02-07 18:46:57'),
(15, 2, 1, 1, '1x por Semana', 'Plano Temporario ', 0.00, 30, 1, 1, '2026-02-07 05:00:07', '2026-02-07 05:00:46'),
(16, 3, 3, 2, '2x Temp', '', 0.00, 30, 1, 1, '2026-02-09 15:20:13', '2026-02-09 15:20:24'),
(17, 3, 3, 3, '3x Temp', '', 0.00, 30, 1, 1, '2026-02-09 15:21:04', '2026-02-09 15:21:04');

-- --------------------------------------------------------

--
-- Estrutura para tabela `planos_sistema`
--

CREATE TABLE IF NOT EXISTS `planos_sistema` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL COMMENT 'Nome do plano (ex: Starter, Professional, Enterprise)',
  `descricao` text DEFAULT NULL COMMENT 'DescriÃ§Ã£o detalhada do plano',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor mensal do plano',
  `duracao_dias` int(11) NOT NULL DEFAULT 30 COMMENT 'DuraÃ§Ã£o em dias (30, 90, 365, etc)',
  `max_alunos` int(11) DEFAULT NULL COMMENT 'Capacidade mÃ¡xima de alunos (NULL = ilimitado)',
  `max_admins` int(11) DEFAULT 1 COMMENT 'NÃºmero mÃ¡ximo de administradores',
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Recursos inclusos no plano em formato JSON' CHECK (json_valid(`features`)),
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Plano ativo para venda',
  `atual` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Plano atual (disponÃ­vel para novos contratos)',
  `ordem` int(11) DEFAULT 0 COMMENT 'Ordem de exibiÃ§Ã£o',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planos de assinatura do sistema que as academias contratam';

--
-- Despejando dados para a tabela `planos_sistema`
--

INSERT INTO `planos_sistema` (`id`, `nome`, `descricao`, `valor`, `duracao_dias`, `max_alunos`, `max_admins`, `features`, `ativo`, `atual`, `ordem`, `created_at`, `updated_at`) VALUES
(1, 'Starter', 'Plano inicial para pequenas academias', 99.00, 30, 50, 1, '{\"checkins\": true, \"app_mobile\": true, \"relatorios_basicos\": true}', 1, 1, 1, '2025-12-29 02:08:51', '2025-12-29 02:08:51'),
(2, 'Professional', 'Plano completo para academias em crescimento', 199.00, 30, 150, 3, '{\"turmas\": true, \"checkins\": true, \"app_mobile\": true, \"multi_admin\": true, \"relatorios_avancados\": true}', 1, 1, 2, '2025-12-29 02:08:51', '2026-01-06 17:39:30'),
(3, 'Enterprise', 'Plano ilimitado para grandes academias', 250.00, 30, 100, 1, '{\"turmas\": true, \"checkins\": true, \"api_access\": true, \"app_mobile\": true, \"multi_admin\": true, \"suporte_prioritario\": true, \"relatorios_avancados\": true}', 1, 1, 3, '2025-12-29 02:08:51', '2026-01-05 14:40:47'),
(4, 'Livre', 'Livre para testes', 0.00, 30, 50, 1, NULL, 1, 1, 0, '2026-01-20 14:47:15', '2026-01-20 14:47:15');

-- --------------------------------------------------------

--
-- Estrutura para tabela `plano_ciclos`
--

CREATE TABLE IF NOT EXISTS `plano_ciclos` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `assinatura_frequencia_id` tinyint(3) UNSIGNED NOT NULL,
  `meses` int(11) NOT NULL DEFAULT 1 COMMENT 'Copiado de tipos_ciclo para cálculo',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor total do ciclo',
  `valor_mensal_equivalente` decimal(10,2) GENERATED ALWAYS AS (`valor` / `meses`) STORED COMMENT 'Valor mensal equivalente calculado',
  `desconto_percentual` decimal(5,2) DEFAULT 0.00 COMMENT 'Percentual de desconto em relação ao mensal',
  `permite_recorrencia` tinyint(1) DEFAULT 1 COMMENT 'Se permite cobrança recorrente (assinatura)',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ciclos de pagamento dos planos';

--
-- Despejando dados para a tabela `plano_ciclos`
--

INSERT INTO `plano_ciclos` (`id`, `tenant_id`, `plano_id`, `assinatura_frequencia_id`, `meses`, `valor`, `desconto_percentual`, `permite_recorrencia`, `ativo`, `created_at`, `updated_at`) VALUES
(32, 2, 6, 4, 1, 221.00, -0.45, 1, 1, '2026-02-08 18:44:43', '2026-02-08 19:28:35'),
(33, 2, 6, 5, 2, 396.00, 10.00, 1, 1, '2026-02-08 18:44:43', '2026-02-08 18:44:43'),
(34, 2, 6, 6, 3, 561.00, 15.00, 1, 1, '2026-02-08 18:44:43', '2026-02-08 18:44:43'),
(35, 2, 6, 7, 6, 990.00, 25.00, 1, 1, '2026-02-08 18:44:43', '2026-02-08 18:44:43'),
(36, 2, 6, 8, 12, 1848.00, 30.00, 1, 1, '2026-02-08 18:44:43', '2026-02-08 18:44:43'),
(40, 2, 14, 4, 1, 50.00, 0.00, 1, 1, '2026-02-08 19:01:37', '2026-02-08 19:01:37'),
(41, 2, 14, 5, 2, 90.00, 10.00, 1, 1, '2026-02-08 19:01:37', '2026-02-08 19:01:37'),
(42, 2, 14, 6, 3, 127.50, 15.00, 1, 1, '2026-02-08 19:01:37', '2026-02-08 19:01:37'),
(43, 2, 14, 7, 6, 225.00, 25.00, 1, 1, '2026-02-08 19:01:37', '2026-02-08 19:01:37'),
(44, 2, 14, 8, 12, 420.00, 30.00, 1, 1, '2026-02-08 19:01:37', '2026-02-08 19:01:37'),
(45, 2, 6, 4, 1, 221.00, -0.45, 0, 1, '2026-02-08 20:24:37', '2026-02-08 20:24:37'),
(46, 2, 14, 4, 1, 0.50, 99.00, 0, 1, '2026-02-09 01:28:32', '2026-02-09 01:28:32'),
(47, 3, 7, 4, 1, 70.00, 0.00, 1, 1, '2026-02-09 14:26:44', '2026-02-09 14:26:44'),
(48, 3, 7, 5, 2, 120.00, 14.29, 1, 1, '2026-02-09 14:28:09', '2026-02-09 14:28:09'),
(49, 3, 7, 9, 4, 200.00, 28.57, 1, 1, '2026-02-09 14:30:31', '2026-02-09 14:30:31'),
(50, 3, 8, 4, 1, 120.00, 0.00, 1, 1, '2026-02-09 14:32:28', '2026-02-09 14:32:28'),
(51, 3, 8, 5, 2, 200.00, 16.67, 1, 1, '2026-02-09 14:33:15', '2026-02-09 14:33:15'),
(52, 3, 8, 9, 4, 360.00, 25.00, 1, 1, '2026-02-09 14:33:35', '2026-02-09 14:33:35'),
(53, 3, 9, 4, 1, 150.00, 0.00, 1, 1, '2026-02-09 14:35:25', '2026-02-09 14:35:25'),
(54, 3, 9, 5, 2, 240.00, 20.00, 1, 1, '2026-02-09 14:35:37', '2026-02-09 14:35:37'),
(55, 3, 9, 9, 4, 400.00, 33.33, 1, 1, '2026-02-09 14:35:47', '2026-02-09 14:35:47'),
(56, 3, 12, 4, 1, 0.00, 0.00, 0, 1, '2026-02-09 15:19:11', '2026-02-09 15:19:11'),
(57, 3, 16, 4, 1, 0.00, 0.00, 0, 1, '2026-02-09 15:20:42', '2026-02-09 15:20:42'),
(58, 3, 17, 4, 1, 0.00, 0.00, 0, 1, '2026-02-09 15:21:13', '2026-02-09 15:21:13');

-- --------------------------------------------------------

--
-- Estrutura para tabela `professores`
--

CREATE TABLE IF NOT EXISTS `professores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL COMMENT 'FK para usuarios (vinculo com conta de login)',
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `foto_url` varchar(500) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `professores`
--

INSERT INTO `professores` (`id`, `usuario_id`, `nome`, `email`, `telefone`, `cpf`, `foto_url`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 6, 'Carlos Mendes', 'carlos.mendes@aquamasters.com.br', '(11) 99876-5432', NULL, NULL, 1, '2026-01-20 14:01:00', '2026-01-29 03:08:54'),
(2, 7, 'Marcela Oliveira', 'marcela.oliveira@aquamasters.com.br', '(11) 98765-4123', NULL, NULL, 1, '2026-01-20 14:01:00', '2026-01-29 03:09:10'),
(3, 61, 'JULIO AQUA', 'julio@aqua.com.br', NULL, '', NULL, 1, '2026-02-05 13:51:13', '2026-02-05 13:51:13'),
(4, 69, 'TIAGO RENAN', 'tiago.renan12345@gmail.com', NULL, '05408590445', NULL, 1, '2026-02-05 17:54:33', '2026-02-05 17:54:33'),
(7, 68, 'CARLOS ROCHA', 'jcarlosbr777@gmail.com', NULL, '02765759464', NULL, 1, '2026-02-05 17:54:33', '2026-02-09 15:50:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `rate_limits`
--

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `action` varchar(100) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `roles`
--

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `roles`
--

INSERT INTO `roles` (`id`, `nome`, `descricao`, `created_at`, `updated_at`) VALUES
(1, 'aluno', 'Usuário comum com acesso ao app', '2025-11-24 02:30:10', '2025-11-25 00:46:50'),
(2, 'admin', 'Administrador da academia', '2025-11-24 02:30:10', '2025-11-24 02:30:10'),
(3, 'super_admin', 'Super administrador com acesso total', '2025-11-24 02:30:10', '2025-11-24 02:30:10');

-- --------------------------------------------------------

--
-- Estrutura para tabela `status_checkin`
--

CREATE TABLE IF NOT EXISTS `status_checkin` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `cor` varchar(20) DEFAULT '#6b7280',
  `icone` varchar(50) DEFAULT NULL,
  `ordem` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `status_checkin`
--

INSERT INTO `status_checkin` (`id`, `codigo`, `nome`, `descricao`, `cor`, `icone`, `ordem`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'entrada', 'Entrada', 'Check-in de entrada', '#10b981', 'log-in', 1, 1, '2026-01-07 20:13:20', '2026-01-07 20:13:20'),
(2, 'saida', 'Saída', 'Check-in de saída', '#3b82f6', 'log-out', 2, 1, '2026-01-07 20:13:20', '2026-01-07 20:13:20');

-- --------------------------------------------------------

--
-- Estrutura para tabela `status_conta`
--

CREATE TABLE IF NOT EXISTS `status_conta` (
  `id` int(11) NOT NULL,
  `nome` varchar(20) NOT NULL,
  `cor` varchar(20) NOT NULL COMMENT 'Cor para exibiÃ§Ã£o no frontend',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `status_conta`
--

INSERT INTO `status_conta` (`id`, `nome`, `cor`, `created_at`) VALUES
(1, 'pendente', 'warning', '2025-11-25 00:33:01'),
(2, 'pago', 'success', '2025-11-25 00:33:01'),
(3, 'vencido', 'danger', '2025-11-25 00:33:01'),
(4, 'cancelado', 'medium', '2025-11-25 00:33:01');

-- --------------------------------------------------------

--
-- Estrutura para tabela `status_contrato`
--

CREATE TABLE IF NOT EXISTS `status_contrato` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL COMMENT 'Nome do status',
  `descricao` varchar(255) DEFAULT NULL COMMENT 'DescriÃ§Ã£o detalhada do status',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Status possÃ­veis para contratos de planos (tenant_planos_sistema)';

--
-- Despejando dados para a tabela `status_contrato`
--

INSERT INTO `status_contrato` (`id`, `nome`, `descricao`, `created_at`, `updated_at`) VALUES
(1, 'Ativo', 'Contrato ativo e em vigÃªncia', '2026-01-05 14:49:26', '2026-01-05 14:49:26'),
(2, 'Pendente', 'Contrato aguardando aprovaÃ§Ã£o ou pagamento', '2026-01-05 14:49:26', '2026-01-05 14:49:26'),
(3, 'Cancelado', 'Contrato cancelado pelo cliente ou administrador', '2026-01-05 14:49:26', '2026-01-05 14:49:26'),
(4, 'Bloqueado', 'Contrato bloqueado por falta de pagamento', '2026-01-05 17:58:06', '2026-01-05 17:58:06');

-- --------------------------------------------------------

--
-- Estrutura para tabela `status_matricula`
--

CREATE TABLE IF NOT EXISTS `status_matricula` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `cor` varchar(20) DEFAULT '#6b7280',
  `icone` varchar(50) DEFAULT NULL,
  `ordem` int(11) DEFAULT 0,
  `permite_checkin` tinyint(1) DEFAULT 1 COMMENT 'Permite fazer check-in com este status',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dias_tolerancia` int(11) DEFAULT 0 COMMENT 'Dias de tolerancia apos vencimento para mudar para este status',
  `automatico` tinyint(1) DEFAULT 0 COMMENT 'Se 1, o status e aplicado automaticamente pelo sistema'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `status_matricula`
--

INSERT INTO `status_matricula` (`id`, `codigo`, `nome`, `descricao`, `cor`, `icone`, `ordem`, `permite_checkin`, `ativo`, `created_at`, `updated_at`, `dias_tolerancia`, `automatico`) VALUES
(1, 'ativa', 'Ativa', 'MatrÃ­cula ativa - pagamento em dia', '#10b981', 'check-circle', 1, 1, 1, '2026-01-08 02:18:42', '2026-01-08 02:18:42', 0, 1),
(2, 'vencida', 'Vencida', 'Pagamento vencido - aguardando regularizaÃ§Ã£o', '#f59e0b', 'clock-alert', 2, 0, 1, '2026-01-08 02:18:42', '2026-01-08 02:18:42', 1, 1),
(3, 'cancelada', 'Cancelada', 'MatrÃ­cula cancelada por inadimplÃªncia', '#ef4444', 'close-circle', 3, 0, 1, '2026-01-08 02:18:42', '2026-01-08 02:18:42', 5, 1),
(4, 'finalizada', 'Finalizada', 'MatrÃ­cula encerrada pelo cliente ou academia', '#6b7280', 'flag-checkered', 4, 0, 1, '2026-01-08 02:18:42', '2026-01-08 02:18:42', NULL, 0),
(5, 'pendente', 'Pendente', 'Matrícula aguardando pagamento inicial', '#f59e0b', 'clock', 0, 0, 1, '2026-01-29 02:56:14', '2026-01-29 02:56:14', 0, 0),
(6, 'bloqueado', 'Bloqueado', 'Matrícula bloqueada por inadimplência prolongada', '#dc2626', 'lock', 5, 0, 1, '2026-01-29 02:56:15', '2026-01-29 02:56:15', 15, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `status_pagamento`
--

CREATE TABLE IF NOT EXISTS `status_pagamento` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` text DEFAULT NULL,
  `dias_tolerancia` int(11) DEFAULT 0 COMMENT 'NÃºmero de dias de tolerÃ¢ncia para este status',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `status_pagamento`
--

INSERT INTO `status_pagamento` (`id`, `nome`, `descricao`, `dias_tolerancia`, `created_at`, `updated_at`) VALUES
(1, 'Aguardando', 'Pagamento aguardando confirmaÃ§Ã£o', 0, '2026-01-05 17:57:49', '2026-01-08 04:39:44'),
(2, 'Pago', 'Pagamento confirmado', 0, '2026-01-05 17:57:49', '2026-01-05 17:57:49'),
(3, 'Atrasado', 'Pagamento em atraso', 1, '2026-01-05 17:57:49', '2026-01-08 04:39:30'),
(4, 'Cancelado', 'Pagamento cancelado', 0, '2026-01-05 17:57:49', '2026-01-05 17:57:49');

-- --------------------------------------------------------

--
-- Estrutura para tabela `status_usuario`
--

CREATE TABLE IF NOT EXISTS `status_usuario` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `cor` varchar(20) DEFAULT '#6b7280',
  `icone` varchar(50) DEFAULT NULL,
  `ordem` int(11) DEFAULT 0,
  `permite_login` tinyint(1) DEFAULT 1 COMMENT 'Permite login com este status',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `status_usuario`
--

INSERT INTO `status_usuario` (`id`, `codigo`, `nome`, `descricao`, `cor`, `icone`, `ordem`, `permite_login`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'ativo', 'Ativo', 'Usuário ativo no sistema', '#10b981', 'user-check', 1, 1, 1, '2026-01-07 20:13:20', '2026-01-07 20:13:20'),
(2, 'inativo', 'Inativo', 'Usuário temporariamente inativo', '#f59e0b', 'user-x', 2, 0, 1, '2026-01-07 20:13:20', '2026-01-07 20:13:20'),
(3, 'bloqueado', 'Bloqueado', 'Usuário bloqueado', '#ef4444', 'lock', 3, 0, 1, '2026-01-07 20:13:20', '2026-01-07 20:13:20');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tenants`
--

CREATE TABLE IF NOT EXISTS `tenants` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `telefone` varchar(50) DEFAULT NULL,
  `responsavel_nome` varchar(255) DEFAULT NULL,
  `responsavel_cpf` varchar(14) DEFAULT NULL,
  `responsavel_telefone` varchar(20) DEFAULT NULL,
  `responsavel_email` varchar(255) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `plano_id` int(11) DEFAULT NULL,
  `data_inicio_plano` date DEFAULT NULL,
  `data_fim_plano` date DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `tenants`
--

INSERT INTO `tenants` (`id`, `nome`, `slug`, `email`, `cnpj`, `telefone`, `responsavel_nome`, `responsavel_cpf`, `responsavel_telefone`, `responsavel_email`, `endereco`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`, `plano_id`, `data_inicio_plano`, `data_fim_plano`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'Sistema AppCheckin', 'sistema-appcheckin', 'admin@appcheckin.com', '', '(82) 9 8837-6381', NULL, NULL, NULL, NULL, NULL, '57307200', 'Rua Severino Catanho', '134', '', 'Baixa Grande', 'Arapiraca', 'AL', NULL, NULL, NULL, 1, '2025-12-26 17:58:37', '2025-12-28 19:27:43'),
(2, 'Aqua Masters', 'aqua-masters', 'contato@aquamasters.com.br', '12345678000190', '(11) 98765-4321', 'Ana Silva', '12345678901', '(11) 98765-4321', 'admin@aquamasters.com.br', 'Rua das Águas, 123 - Jardim das Flores', '01234-567', 'Rua das Águas', '123', 'Próximo ao parque', 'Jardim das Flores', 'São Paulo', 'SP', NULL, NULL, NULL, 1, '2026-01-20 14:01:00', '2026-02-04 15:26:54'),
(3, 'Cia da Natação', 'cia-da-natacao', 'jcarlosbr777@gmail.com', '63316819000182', '82982020644', 'José Carlos Barboza Rocha ', '02765759464', '82982020644', 'jcarlosbr777@gmail.com', NULL, '57305-470', 'Rua Marechal Floriano Peixoto', '', '', 'Baixão', 'Arapiraca', 'AL', NULL, NULL, NULL, 1, '2026-02-05 17:33:24', '2026-02-05 17:47:51');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tenant_formas_pagamento`
--

CREATE TABLE IF NOT EXISTS `tenant_formas_pagamento` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `forma_pagamento_id` int(11) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `taxa_percentual` decimal(5,2) DEFAULT 0.00 COMMENT 'Taxa em % cobrada pela operadora (ex: 3.99 = 3.99%)',
  `taxa_fixa` decimal(10,2) DEFAULT 0.00 COMMENT 'Taxa fixa por transaÃ§Ã£o em R$ (ex: 3.50)',
  `aceita_parcelamento` tinyint(1) DEFAULT 0 COMMENT '1 = permite parcelar',
  `parcelas_minimas` int(11) DEFAULT 1 COMMENT 'MÃ­nimo de parcelas permitidas',
  `parcelas_maximas` int(11) DEFAULT 12 COMMENT 'MÃ¡ximo de parcelas permitidas',
  `juros_parcelamento` decimal(5,2) DEFAULT 0.00 COMMENT 'Juros ao mÃªs em % (ex: 1.99 = 1.99%)',
  `parcelas_sem_juros` int(11) DEFAULT 1 COMMENT 'Quantidade de parcelas sem juros',
  `dias_compensacao` int(11) DEFAULT 0 COMMENT 'Dias Ãºteis para compensaÃ§Ã£o do pagamento',
  `valor_minimo` decimal(10,2) DEFAULT 0.00 COMMENT 'Valor mÃ­nimo para aceitar esta forma',
  `observacoes` text DEFAULT NULL COMMENT 'ObservaÃ§Ãµes internas sobre esta forma de pagamento',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `tenant_formas_pagamento`
--

INSERT INTO `tenant_formas_pagamento` (`id`, `tenant_id`, `forma_pagamento_id`, `ativo`, `taxa_percentual`, `taxa_fixa`, `aceita_parcelamento`, `parcelas_minimas`, `parcelas_maximas`, `juros_parcelamento`, `parcelas_sem_juros`, `dias_compensacao`, `valor_minimo`, `observacoes`, `created_at`, `updated_at`) VALUES
(16, 1, 1, 1, 0.00, 0.00, 0, 1, 1, 0.00, 1, 0, 0.00, NULL, '2026-01-06 02:16:47', '2026-01-06 03:02:15'),
(17, 1, 2, 1, 0.00, 0.00, 0, 1, 1, 0.00, 1, 0, 0.00, NULL, '2026-01-06 02:16:47', '2026-01-06 03:02:44'),
(18, 1, 3, 0, 0.00, 0.00, 0, 1, 1, 0.00, 1, 1, 0.00, NULL, '2026-01-06 02:16:47', '2026-01-06 02:44:20'),
(19, 1, 4, 0, 0.00, 0.00, 0, 1, 1, 0.00, 1, 1, 0.00, NULL, '2026-01-06 02:16:47', '2026-01-06 03:02:12'),
(20, 1, 5, 0, 0.00, 0.00, 0, 1, 1, 0.00, 1, 1, 0.00, NULL, '2026-01-06 02:16:47', '2026-01-06 02:43:30'),
(21, 1, 6, 0, 0.00, 0.00, 0, 1, 1, 0.00, 1, 1, 0.00, NULL, '2026-01-06 02:16:47', '2026-01-06 02:43:29'),
(22, 1, 7, 0, 0.00, 0.00, 0, 1, 1, 0.00, 1, 1, 0.00, NULL, '2026-01-06 02:16:47', '2026-01-06 02:44:03'),
(23, 1, 8, 0, 0.00, 0.00, 0, 1, 1, 0.00, 1, 3, 10.00, NULL, '2026-01-06 02:16:47', '2026-01-06 03:02:48'),
(24, 1, 9, 1, 0.00, 0.00, 1, 1, 3, 3.00, 1, 1, 100.00, NULL, '2026-01-06 02:16:47', '2026-01-06 03:00:43'),
(25, 1, 10, 0, 0.00, 0.00, 0, 1, 1, 0.00, 1, 1, 0.00, NULL, '2026-01-06 02:16:47', '2026-01-06 02:44:10'),
(26, 1, 11, 0, 0.00, 0.00, 0, 1, 1, 0.00, 1, 1, 0.00, NULL, '2026-01-06 02:16:47', '2026-01-06 02:44:06'),
(27, 1, 12, 1, 0.00, 0.00, 0, 1, 1, 0.00, 1, 1, 0.00, NULL, '2026-01-06 02:16:47', '2026-01-06 03:02:02'),
(28, 1, 13, 0, 0.00, 0.00, 0, 1, 1, 0.00, 1, 1, 0.00, NULL, '2026-01-06 02:16:47', '2026-01-06 03:00:49'),
(29, 2, 1, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(30, 2, 2, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(31, 2, 3, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(32, 2, 4, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(33, 2, 5, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(34, 2, 6, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(35, 2, 7, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(36, 2, 8, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(37, 2, 9, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(38, 2, 10, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(39, 2, 11, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(40, 2, 12, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(41, 2, 13, 1, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(44, 3, 1, 0, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(45, 3, 2, 0, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(46, 3, 3, 0, 2.50, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(47, 3, 4, 0, 3.50, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(48, 3, 5, 0, 4.50, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(49, 3, 6, 0, 5.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(50, 3, 7, 0, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(51, 3, 8, 0, 2.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(52, 3, 9, 0, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(53, 3, 10, 0, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(54, 3, 11, 0, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(55, 3, 12, 0, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24'),
(56, 3, 13, 0, 0.00, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00, NULL, '2026-02-05 17:33:24', '2026-02-05 17:33:24');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tenant_payment_credentials`
--

CREATE TABLE IF NOT EXISTS `tenant_payment_credentials` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'mercadopago',
  `environment` varchar(20) NOT NULL DEFAULT 'sandbox',
  `access_token_test` text DEFAULT NULL,
  `public_key_test` varchar(255) DEFAULT NULL,
  `access_token_prod` text DEFAULT NULL,
  `public_key_prod` varchar(255) DEFAULT NULL,
  `webhook_secret` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Credenciais de pagamento por tenant (Mercado Pago, etc)';

--
-- Despejando dados para a tabela `tenant_payment_credentials`
--

INSERT INTO `tenant_payment_credentials` (`id`, `tenant_id`, `provider`, `environment`, `access_token_test`, `public_key_test`, `access_token_prod`, `public_key_prod`, `webhook_secret`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 2, 'mercadopago', 'production', 'BgIWCglR1Nzk4skbJGC5BKIBvT4a7mUyT7cTJzI1wcPz1BexjqKhJZWRuZigY0Llajny6Ayab0y/wjROk3YMYWn6ZWCJax6Qnxtxe/N8XiD9ic+2CV9ON53OC5MilVlcXLyk', 'TEST-44f9e009-e7e5-434f-9ff0-7923fd394709', 'U2nGn9aJW0qXRjm3VSb17p9kW1WXhmOm3rhykmwcpMhQdxpepHawPUCMAdJ243Jsb0dO8YVWgL/GweL+WLaKAGP4JFb2FiSoRAvOZd7wDiuoRFemy7uLrMdHw5s3BeTYs+bbH6ck', 'APP_USR-3cac1a43-8526-4717-b3bf-a705e8628422', 'hW11lx1BPvykysuSAa1RF5dQFxwa1qYIFwWIBcM7YObsbXNTsbGOoqEW+nUPNRjEnh/sWPZp8/IeNA1FAOuEsc4E5j7wHXssdBsnHF4Bm+yLaQ==', 1, '2026-02-07 04:07:20', '2026-02-07 04:07:20');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tenant_planos_sistema`
--

CREATE TABLE IF NOT EXISTS `tenant_planos_sistema` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `plano_sistema_id` int(11) DEFAULT NULL,
  `status_id` int(11) NOT NULL,
  `data_inicio` date NOT NULL,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contratos das academias com planos do sistema. Cada academia pode ter apenas um contrato ativo por vez, mas mantÃ©m histÃ³rico de contratos anteriores.';

--
-- Despejando dados para a tabela `tenant_planos_sistema`
--

INSERT INTO `tenant_planos_sistema` (`id`, `tenant_id`, `plano_id`, `plano_sistema_id`, `status_id`, `data_inicio`, `observacoes`, `created_at`, `updated_at`) VALUES
(6, 2, 4, 4, 1, '2026-01-20', NULL, '2026-01-20 15:17:34', '2026-01-20 15:27:11');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tenant_professor`
--

CREATE TABLE IF NOT EXISTS `tenant_professor` (
  `id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL COMMENT 'FK para professores.id',
  `tenant_id` int(11) NOT NULL COMMENT 'FK para tenants.id',
  `cpf` varchar(14) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `plano_id` int(11) DEFAULT NULL COMMENT 'FK para planos.id (plano específico do professor no tenant)',
  `status` enum('ativo','inativo','suspenso','cancelado') DEFAULT 'ativo' COMMENT 'Status do professor no tenant',
  `data_inicio` date NOT NULL COMMENT 'Data de início do vínculo',
  `data_fim` date DEFAULT NULL COMMENT 'Data de término do vínculo (NULL se ativo)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vínculo entre professores e tenants com status e plano específico';

--
-- Despejando dados para a tabela `tenant_professor`
--

INSERT INTO `tenant_professor` (`id`, `professor_id`, `tenant_id`, `cpf`, `email`, `plano_id`, `status`, `data_inicio`, `data_fim`, `created_at`, `updated_at`) VALUES
(1, 1, 2, NULL, NULL, NULL, 'ativo', '2026-02-03', NULL, '2026-02-03 18:27:27', '2026-02-03 18:27:27'),
(2, 2, 2, NULL, NULL, NULL, 'ativo', '2026-02-03', NULL, '2026-02-03 18:27:27', '2026-02-03 18:27:27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tenant_usuario_papel`
--

CREATE TABLE IF NOT EXISTS `tenant_usuario_papel` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `papel_id` int(11) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Papéis de cada usuário em cada tenant. Um usuário pode ter múltiplos papéis no mesmo tenant.';

--
-- Despejando dados para a tabela `tenant_usuario_papel`
--

INSERT INTO `tenant_usuario_papel` (`id`, `tenant_id`, `usuario_id`, `papel_id`, `ativo`, `created_at`, `updated_at`) VALUES
(8, 2, 2, 3, 1, '2026-01-29 02:52:14', '2026-01-29 02:52:14'),
(9, 2, 6, 2, 1, '2026-01-29 03:08:15', '2026-01-29 03:08:15'),
(10, 2, 7, 2, 1, '2026-01-29 03:08:15', '2026-01-29 03:08:15'),
(75, 2, 15, 1, 1, '2026-02-04 16:52:50', '2026-02-04 16:52:50'),
(76, 1, 1, 4, 1, '2026-02-04 16:52:50', '2026-02-04 16:52:50'),
(80, 2, 61, 3, 1, '2026-02-05 13:51:13', '2026-02-05 13:51:13'),
(81, 2, 61, 1, 1, '2026-02-05 13:51:13', '2026-02-05 13:51:13'),
(82, 2, 61, 2, 1, '2026-02-05 13:51:13', '2026-02-05 13:51:13'),
(90, 3, 3, 3, 1, '2026-02-05 23:51:05', '2026-02-05 23:51:05'),
(91, 3, 3, 1, 1, '2026-02-05 23:51:05', '2026-02-05 23:51:05'),
(92, 3, 68, 3, 1, '2026-02-09 15:00:32', '2026-02-09 15:00:32'),
(93, 3, 68, 2, 1, '2026-02-09 15:00:32', '2026-02-09 15:00:32'),
(94, 3, 69, 3, 1, '2026-02-09 15:00:37', '2026-02-09 15:00:37'),
(95, 3, 69, 2, 1, '2026-02-09 15:00:37', '2026-02-09 15:00:37');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_baixa`
--

CREATE TABLE IF NOT EXISTS `tipos_baixa` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL COMMENT 'Nome do tipo de baixa: Manual, Automática, etc.',
  `descricao` varchar(255) DEFAULT NULL COMMENT 'Descrição detalhada do tipo de baixa',
  `ativo` tinyint(1) DEFAULT 1 COMMENT 'Se o tipo está ativo para uso',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `tipos_baixa`
--

INSERT INTO `tipos_baixa` (`id`, `nome`, `descricao`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'Manual', 'Baixa realizada manualmente pelo administrador do sistema', 1, '2026-01-07 16:34:26', '2026-01-07 16:34:26'),
(2, 'Automática', 'Baixa realizada automaticamente pelo sistema', 1, '2026-01-07 16:34:26', '2026-01-07 16:34:26'),
(3, 'Importação', 'Baixa realizada através de importação de dados', 1, '2026-01-07 16:34:26', '2026-01-07 16:34:26'),
(4, 'Integração', 'Baixa realizada através de integração com sistema externo', 1, '2026-01-07 16:34:26', '2026-01-07 16:34:26');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_ciclo`
--

CREATE TABLE IF NOT EXISTS `tipos_ciclo` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL COMMENT 'Mensal, Trimestral, Semestral, Anual',
  `codigo` varchar(20) NOT NULL COMMENT 'mensal, trimestral, semestral, anual',
  `meses` int(11) NOT NULL DEFAULT 1 COMMENT 'Quantidade de meses do ciclo',
  `ordem` int(11) DEFAULT 1 COMMENT 'Ordem de exibição',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos de ciclo de pagamento (mensal, trimestral, etc)';

--
-- Despejando dados para a tabela `tipos_ciclo`
--

INSERT INTO `tipos_ciclo` (`id`, `nome`, `codigo`, `meses`, `ordem`, `ativo`, `created_at`) VALUES
(1, 'Mensal', 'mensal', 1, 1, 1, '2026-02-07 18:36:35'),
(2, 'Bimestral', 'bimestral', 2, 2, 1, '2026-02-07 18:36:35'),
(3, 'Trimestral', 'trimestral', 3, 3, 1, '2026-02-07 18:36:35'),
(4, 'Quadrimestral', 'quadrimestral', 4, 4, 1, '2026-02-07 18:36:35'),
(5, 'Semestral', 'semestral', 6, 5, 1, '2026-02-07 18:36:35'),
(6, 'Anual', 'anual', 12, 6, 1, '2026-02-07 18:36:35');

-- --------------------------------------------------------

--
-- Estrutura para tabela `turmas`
--

CREATE TABLE IF NOT EXISTS `turmas` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `modalidade_id` int(11) NOT NULL,
  `dia_id` int(11) NOT NULL,
  `horario_inicio` time NOT NULL DEFAULT '06:00:00',
  `horario_fim` time NOT NULL DEFAULT '07:00:00',
  `nome` varchar(255) NOT NULL,
  `limite_alunos` int(11) NOT NULL DEFAULT 20,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tolerancia_minutos` int(11) NOT NULL DEFAULT 10 COMMENT 'TolerÃ¢ncia em minutos apÃ³s o horÃ¡rio',
  `tolerancia_antes_minutos` int(11) NOT NULL DEFAULT 480 COMMENT 'TolerÃ¢ncia em minutos antes do horÃ¡rio (8 horas = 480 min)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `turmas`
--

INSERT INTO `turmas` (`id`, `tenant_id`, `professor_id`, `modalidade_id`, `dia_id`, `horario_inicio`, `horario_fim`, `nome`, `limite_alunos`, `ativo`, `created_at`, `updated_at`, `tolerancia_minutos`, `tolerancia_antes_minutos`) VALUES
(1, 2, 1, 1, 20, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-20 18:01:52', '2026-01-20 18:50:47', 10, 600),
(3, 2, 1, 1, 20, '06:00:00', '07:00:00', 'Natação - 06:00 - Carlos Mendes', 10, 1, '2026-01-20 18:22:12', '2026-01-20 18:22:12', 10, 480),
(4, 2, 1, 1, 7, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-20 18:53:01', '2026-01-20 18:53:01', 10, 480),
(5, 2, 1, 1, 14, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-20 18:53:01', '2026-01-20 18:53:01', 10, 480),
(6, 2, 1, 1, 21, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-20 18:53:01', '2026-01-20 18:53:01', 10, 480),
(7, 2, 1, 1, 28, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-20 18:53:01', '2026-01-20 18:53:01', 10, 480),
(8, 2, 1, 1, 7, '06:00:00', '07:00:00', 'Natação - 06:00 - Carlos Mendes', 10, 1, '2026-01-20 18:53:01', '2026-01-20 18:53:01', 10, 480),
(9, 2, 1, 1, 14, '06:00:00', '07:00:00', 'Natação - 06:00 - Carlos Mendes', 10, 1, '2026-01-20 18:53:01', '2026-01-20 18:53:01', 10, 480),
(10, 2, 1, 1, 21, '06:00:00', '07:00:00', 'Natação - 06:00 - Carlos Mendes', 10, 1, '2026-01-20 18:53:01', '2026-01-20 18:53:01', 10, 480),
(11, 2, 1, 1, 28, '06:00:00', '07:00:00', 'Natação - 06:00 - Carlos Mendes', 10, 1, '2026-01-20 18:53:01', '2026-01-20 18:53:01', 10, 480),
(12, 2, 2, 1, 21, '17:00:00', '18:00:00', 'Natação - 17:00 - Marcela Oliveira', 10, 1, '2026-01-21 18:21:13', '2026-01-21 18:21:13', 10, 960),
(13, 2, 2, 2, 21, '18:00:00', '19:00:00', 'CrossFit - 18:00 - Marcela Oliveira', 25, 1, '2026-01-21 18:50:59', '2026-01-21 18:50:59', 10, 960),
(14, 2, 2, 2, 1, '18:00:00', '19:00:00', 'CrossFit - 18:00 - Marcela Oliveira', 25, 1, '2026-01-21 19:09:58', '2026-01-21 19:09:58', 10, 480),
(15, 2, 2, 2, 8, '18:00:00', '19:00:00', 'CrossFit - 18:00 - Marcela Oliveira', 25, 1, '2026-01-21 19:09:58', '2026-01-21 19:09:58', 10, 480),
(16, 2, 2, 2, 15, '18:00:00', '19:00:00', 'CrossFit - 18:00 - Marcela Oliveira', 25, 1, '2026-01-21 19:09:58', '2026-01-21 19:09:58', 10, 480),
(17, 2, 2, 2, 22, '18:00:00', '19:00:00', 'CrossFit - 18:00 - Marcela Oliveira', 25, 1, '2026-01-21 19:09:58', '2026-01-21 19:37:33', 10, 960),
(18, 2, 2, 2, 29, '18:00:00', '19:00:00', 'CrossFit - 18:00 - Marcela Oliveira', 25, 1, '2026-01-21 19:09:58', '2026-01-21 19:09:58', 10, 480),
(19, 2, 1, 1, 29, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-29 03:15:23', '2026-01-29 03:15:23', 30, 480),
(20, 2, 1, 1, 2, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-29 03:35:56', '2026-01-29 03:35:56', 10, 480),
(21, 2, 1, 1, 3, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-29 03:35:56', '2026-01-29 03:35:56', 10, 480),
(22, 2, 1, 1, 9, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-29 03:35:56', '2026-01-29 03:35:56', 10, 480),
(23, 2, 1, 1, 10, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-29 03:35:56', '2026-01-29 03:35:56', 10, 480),
(24, 2, 1, 1, 16, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-29 03:35:56', '2026-01-29 03:35:56', 10, 480),
(25, 2, 1, 1, 17, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-29 03:35:56', '2026-01-29 03:35:56', 10, 480),
(26, 2, 1, 1, 23, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-29 03:35:56', '2026-01-29 03:35:56', 10, 480),
(27, 2, 1, 1, 24, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-29 03:35:56', '2026-01-29 03:35:56', 10, 480),
(28, 2, 1, 1, 30, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-29 03:35:56', '2026-01-29 03:35:56', 10, 480),
(29, 2, 1, 1, 31, '05:00:00', '06:00:00', 'Natação - 05:00 - Carlos Mendes', 10, 1, '2026-01-29 03:35:56', '2026-01-29 03:35:56', 10, 480),
(30, 2, 1, 1, 29, '15:00:00', '16:00:00', 'Natação - 15:00 - Carlos Mendes', 15, 1, '2026-01-29 17:46:15', '2026-01-29 17:46:15', 10, 480),
(31, 2, 1, 1, 36, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:00', '2026-02-07 02:28:00', 10, 480),
(32, 2, 1, 1, 36, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:20', '2026-02-07 02:28:20', 10, 480),
(33, 2, 1, 1, 32, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(34, 2, 1, 1, 33, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(35, 2, 1, 1, 34, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(36, 2, 1, 1, 35, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(37, 2, 1, 1, 37, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(38, 2, 1, 1, 38, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(39, 2, 1, 1, 39, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 18:48:07', 10, 960),
(40, 2, 1, 1, 40, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(41, 2, 1, 1, 41, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(42, 2, 1, 1, 42, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(43, 2, 1, 1, 43, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(44, 2, 1, 1, 44, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(45, 2, 1, 1, 45, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(46, 2, 1, 1, 46, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(47, 2, 1, 1, 47, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(48, 2, 1, 1, 48, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(49, 2, 1, 1, 49, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(50, 2, 1, 1, 50, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(51, 2, 1, 1, 51, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(52, 2, 1, 1, 52, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(53, 2, 1, 1, 53, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(54, 2, 1, 1, 54, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(55, 2, 1, 1, 55, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(56, 2, 1, 1, 56, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(57, 2, 1, 1, 57, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(58, 2, 1, 1, 58, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(59, 2, 1, 1, 59, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(60, 2, 1, 1, 32, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(61, 2, 1, 1, 33, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(62, 2, 1, 1, 34, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(63, 2, 1, 1, 35, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(64, 2, 1, 1, 37, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(65, 2, 1, 1, 38, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(66, 2, 1, 1, 39, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 18:48:20', 10, 960),
(67, 2, 1, 1, 40, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(68, 2, 1, 1, 41, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(69, 2, 1, 1, 42, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(70, 2, 1, 1, 43, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(71, 2, 1, 1, 44, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(72, 2, 1, 1, 45, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(73, 2, 1, 1, 46, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(74, 2, 1, 1, 47, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(75, 2, 1, 1, 48, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(76, 2, 1, 1, 49, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(77, 2, 1, 1, 50, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(78, 2, 1, 1, 51, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(79, 2, 1, 1, 52, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(80, 2, 1, 1, 53, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(81, 2, 1, 1, 54, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(82, 2, 1, 1, 55, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(83, 2, 1, 1, 56, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(84, 2, 1, 1, 57, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(85, 2, 1, 1, 58, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(86, 2, 1, 1, 59, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS MENDES', 12, 1, '2026-02-07 02:28:25', '2026-02-07 02:28:25', 10, 480),
(87, 3, 7, 3, 41, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:56:55', '2026-02-09 16:23:10', 15, 960),
(88, 3, 7, 3, 41, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:57:10', '2026-02-09 16:23:17', 15, 960),
(89, 3, 4, 3, 41, '16:00:00', '17:00:00', 'Natação - 16:00 - TIAGO RENAN', 15, 1, '2026-02-09 15:57:51', '2026-02-09 16:23:24', 15, 960),
(90, 3, 4, 3, 41, '19:15:00', '20:15:00', 'Natação - 19:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:58:10', '2026-02-09 16:23:30', 15, 960),
(91, 3, 4, 3, 41, '20:15:00', '21:15:00', 'Natação - 20:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:58:25', '2026-02-09 16:23:39', 15, 960),
(92, 3, 7, 3, 34, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(93, 3, 7, 3, 36, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(94, 3, 7, 3, 43, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(95, 3, 7, 3, 48, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(96, 3, 7, 3, 50, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(97, 3, 7, 3, 55, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(98, 3, 7, 3, 57, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(99, 3, 7, 3, 34, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(100, 3, 7, 3, 36, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(101, 3, 7, 3, 43, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(102, 3, 7, 3, 48, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(103, 3, 7, 3, 50, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(104, 3, 7, 3, 55, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(105, 3, 7, 3, 57, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(106, 3, 4, 3, 34, '16:00:00', '17:00:00', 'Natação - 16:00 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(107, 3, 4, 3, 36, '16:00:00', '17:00:00', 'Natação - 16:00 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(108, 3, 4, 3, 43, '16:00:00', '17:00:00', 'Natação - 16:00 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(109, 3, 4, 3, 48, '16:00:00', '17:00:00', 'Natação - 16:00 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(110, 3, 4, 3, 50, '16:00:00', '17:00:00', 'Natação - 16:00 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(111, 3, 4, 3, 55, '16:00:00', '17:00:00', 'Natação - 16:00 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(112, 3, 4, 3, 57, '16:00:00', '17:00:00', 'Natação - 16:00 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(113, 3, 4, 3, 34, '19:15:00', '20:15:00', 'Natação - 19:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(114, 3, 4, 3, 36, '19:15:00', '20:15:00', 'Natação - 19:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(115, 3, 4, 3, 43, '19:15:00', '20:15:00', 'Natação - 19:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(116, 3, 4, 3, 48, '19:15:00', '20:15:00', 'Natação - 19:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(117, 3, 4, 3, 50, '19:15:00', '20:15:00', 'Natação - 19:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(118, 3, 4, 3, 55, '19:15:00', '20:15:00', 'Natação - 19:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(119, 3, 4, 3, 57, '19:15:00', '20:15:00', 'Natação - 19:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(120, 3, 4, 3, 34, '20:15:00', '21:15:00', 'Natação - 20:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(121, 3, 4, 3, 36, '20:15:00', '21:15:00', 'Natação - 20:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(122, 3, 4, 3, 43, '20:15:00', '21:15:00', 'Natação - 20:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(123, 3, 4, 3, 48, '20:15:00', '21:15:00', 'Natação - 20:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(124, 3, 4, 3, 50, '20:15:00', '21:15:00', 'Natação - 20:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(125, 3, 4, 3, 55, '20:15:00', '21:15:00', 'Natação - 20:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(126, 3, 4, 3, 57, '20:15:00', '21:15:00', 'Natação - 20:15 - TIAGO RENAN', 15, 1, '2026-02-09 15:59:08', '2026-02-09 15:59:08', 10, 480),
(127, 3, 7, 3, 42, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:00:24', '2026-02-09 16:00:24', 15, 600),
(128, 3, 4, 3, 42, '17:00:00', '18:00:00', 'Natação - 17:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:00:54', '2026-02-09 16:00:54', 15, 600),
(129, 3, 4, 3, 42, '18:00:00', '19:00:00', 'Natação - 18:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:22', '2026-02-09 16:01:22', 15, 600),
(130, 3, 7, 3, 35, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(131, 3, 7, 3, 37, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(132, 3, 7, 3, 44, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(133, 3, 7, 3, 49, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(134, 3, 7, 3, 51, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(135, 3, 7, 3, 56, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(136, 3, 7, 3, 58, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(137, 3, 4, 3, 35, '17:00:00', '18:00:00', 'Natação - 17:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(138, 3, 4, 3, 37, '17:00:00', '18:00:00', 'Natação - 17:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(139, 3, 4, 3, 44, '17:00:00', '18:00:00', 'Natação - 17:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(140, 3, 4, 3, 49, '17:00:00', '18:00:00', 'Natação - 17:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(141, 3, 4, 3, 51, '17:00:00', '18:00:00', 'Natação - 17:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(142, 3, 4, 3, 56, '17:00:00', '18:00:00', 'Natação - 17:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(143, 3, 4, 3, 58, '17:00:00', '18:00:00', 'Natação - 17:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(144, 3, 4, 3, 35, '18:00:00', '19:00:00', 'Natação - 18:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(145, 3, 4, 3, 37, '18:00:00', '19:00:00', 'Natação - 18:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(146, 3, 4, 3, 44, '18:00:00', '19:00:00', 'Natação - 18:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(147, 3, 4, 3, 49, '18:00:00', '19:00:00', 'Natação - 18:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(148, 3, 4, 3, 51, '18:00:00', '19:00:00', 'Natação - 18:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(149, 3, 4, 3, 56, '18:00:00', '19:00:00', 'Natação - 18:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(150, 3, 4, 3, 58, '18:00:00', '19:00:00', 'Natação - 18:00 - TIAGO RENAN', 15, 1, '2026-02-09 16:01:46', '2026-02-09 16:01:46', 10, 480),
(151, 3, 7, 3, 45, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:02:19', '2026-02-09 16:02:19', 15, 600),
(152, 3, 7, 3, 45, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:02:36', '2026-02-09 16:02:36', 15, 600),
(153, 3, 7, 3, 38, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:02:42', '2026-02-09 16:02:42', 10, 480),
(154, 3, 7, 3, 52, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:02:42', '2026-02-09 16:02:42', 10, 480),
(155, 3, 7, 3, 59, '05:00:00', '06:00:00', 'Natação - 05:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:02:42', '2026-02-09 16:02:42', 10, 480),
(156, 3, 7, 3, 38, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:02:42', '2026-02-09 16:02:42', 10, 480),
(157, 3, 7, 3, 52, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:02:42', '2026-02-09 16:02:42', 10, 480),
(158, 3, 7, 3, 59, '06:00:00', '07:00:00', 'Natação - 06:00 - CARLOS ROCHA', 15, 1, '2026-02-09 16:02:42', '2026-02-09 16:02:42', 10, 480);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL,
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
  `ativo` tinyint(1) DEFAULT 1,
  `role_id_bkp` int(11) DEFAULT 1,
  `foto_base64` longtext DEFAULT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires_at` datetime DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL COMMENT 'Token único para recuperação de senha',
  `reset_token_expiry` datetime DEFAULT NULL COMMENT 'Data/hora de expiração do token (geralmente 15 minutos após geração)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `foto_caminho` varchar(255) DEFAULT NULL COMMENT 'Caminho relativo da foto de perfil (ex: /uploads/fotos/usuario_123_1234567890.jpg)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `telefone`, `cpf`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`, `email_global`, `ativo`, `role_id_bkp`, `foto_base64`, `senha_hash`, `password_reset_token`, `password_reset_expires_at`, `reset_token`, `reset_token_expiry`, `created_at`, `updated_at`, `foto_caminho`) VALUES
(1, 'Super', 'sa@appcheckin.com.br', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sa@appcheckin.com.br', 1, 3, NULL, '$2y$10$jlDgnsP4k8f/Nj/6OLem.O7QJsEXrukUm9wi9xsd0ngDr0cINpyly', '2a29c701eee3fc4c36a9236306b3daf7feb096a08104c56c2a110e07001b0dd8', '2026-01-23 00:15:53', NULL, NULL, '2025-12-26 17:58:37', '2026-02-04 17:56:12', NULL),
(2, 'AQUA MASTERS', 'admin@aqua.com.br', '(11) 98765-4321', '93529120049', '57307200', 'RUA SEVERINO CATANHO', '135', '123', 'BAIXA GRANDE', 'ARAPIRACA', 'AL', NULL, 1, 3, NULL, '$2y$10$jlDgnsP4k8f/Nj/6OLem.O7QJsEXrukUm9wi9xsd0ngDr0cINpyly', NULL, NULL, NULL, NULL, '2026-01-20 14:01:00', '2026-01-29 02:52:26', NULL),
(3, 'ANDRE CABRAL SILVA', 'andrecabrall@gmail.com', '82988376381', '05809498426', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, '$2y$10$pxRlAZpz9Ci0YPDyH0rmaOO4Tn7Z4z6f7LsntnbLaQWUGE4zQxVoq', '4f183abcf04f4433a9f03f01a5d6e1318e4ba1a21fcf16524fe93fa93f6d25ac', '2026-01-29 01:20:41', NULL, NULL, '2026-01-20 17:42:31', '2026-01-29 03:20:41', '/uploads/fotos/usuario_3_1769561398.jpg'),
(6, 'Carlos Mendes', 'carlos.mendes@aquamasters.com.br', '11998765432', '52092219030', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 2, NULL, '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW', NULL, NULL, NULL, NULL, '2026-01-29 03:08:15', '2026-01-29 03:08:54', NULL),
(7, 'Marcela Oliveira', 'marcela.oliveira@aquamasters.com.br', '11987654123', '74722593060', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 2, NULL, '$2y$10$gHLUPI9VrE60CMi.TTj4cujkftMVWZe2RLC9JZCCGYHwCv73iaGmW', NULL, NULL, NULL, NULL, '2026-01-29 03:08:15', '2026-01-29 03:09:10', NULL),
(15, 'VISSIA VITÓRIA FERRO LIMA', 'vissiavitoria@gmail.com', '82996438378', '13586205473', '57303303', 'RUA DULCINEIA MARIA DA SILVA', '2', 'PORTÃO BRANCO', 'BOA VISTA', 'ARAPIRACA', 'AL', 'vissiavitoria@gmail.com', 1, 1, NULL, '$2y$10$N5RkVft4p3ghv4GLwXuEFOOx7sZcdq.eSF79HWHULE4VhrkPC.aue', NULL, NULL, NULL, NULL, '2026-02-04 11:14:50', '2026-02-04 11:14:50', NULL),
(16, 'STEFANNY FERREIRA', 'stefannyferreirasf47@gmail.com', '82998292529', '05542628435', '57312160', 'RUA MARECHAL RONDON', '189', NULL, 'SANTA ESMERALDA', 'ARAPIRACA', 'AL', 'stefannyferreirasf47@gmail.com', 1, 1, NULL, '$2y$10$.posIjWcEIc2zsntCdj23e6iO.K37xlQwU0H1kSB4kT1mCDAG6Njy', NULL, NULL, NULL, NULL, '2026-02-04 11:14:59', '2026-02-04 11:14:59', NULL),
(17, 'THALIA GALDINO DA SILVA', 'thaliagaldino1@gmail.com', '82999413362', '15145079435', '57318100', 'PEDRO ALEXANDRE', '359', NULL, 'CAVACO', 'ARAPIRACA', 'AL', 'thaliagaldino1@gmail.com', 1, 1, NULL, '$2y$10$dmedAl7HaUQnQqmV/fE9S.Exsa7JoxOOMvg4pjlnjCNBRy8TZUd2a', NULL, NULL, NULL, NULL, '2026-02-04 11:15:16', '2026-02-04 11:15:16', NULL),
(18, 'MILENA KELMANY DA SILVA', 'millyalbuquer@gmail.com', '82991186643', '11903712432', '57320000', 'RUA SALUSTIANO NUNES', '241', 'CASA', 'SÃO JOÃO', 'CRAÍBAS', 'AL', 'millyalbuquer@gmail.com', 1, 1, NULL, '$2y$10$xuc4RfJlN.wkvFi0InNjsOzGTyJXwPyLURYyWxa.SzYuA93MZ1vt.', NULL, NULL, NULL, NULL, '2026-02-04 11:17:23', '2026-02-04 11:17:23', NULL),
(19, 'ITAMARA DE SOUZA SANTOS', 'souza21s@hotmail.com', '82996194933', '12110106433', '5730000', 'RUA JOÃO BATISTA DA SILVA', '111', 'CASA', 'CANAÃ', 'ARAPIRACA', 'AL', 'souza21s@hotmail.com', 1, 1, NULL, '$2y$10$NrnAu/txb7C/k7L6rGzFj.6taRXYN2.sfXkEVQyRbsKkDNnBqY5Ka', NULL, NULL, NULL, NULL, '2026-02-04 11:18:10', '2026-02-04 11:18:10', NULL),
(20, 'ANA PAULA FERREIRA', 'ana.ferreira.0798@gmail.com', '82998015090', '11212541448', NULL, NULL, NULL, NULL, 'ARAPIRACA', 'ARAPIRACA', 'AL', 'ana.ferreira.0798@gmail.com', 1, 1, NULL, '$2y$10$IAE4E3kpD2kNZIK56.qY/.bgxpqiET1.gtPKgn4ZC0u.dgV9Fr4Ca', NULL, NULL, NULL, NULL, '2026-02-04 11:18:22', '2026-02-04 11:18:22', NULL),
(21, 'FRANCYELLE FELISMINO DA SILVA', 'francyfelismino@gmail.con', '82982249425', '09891502406', '57311660', 'RUA NOSSA SENHORA DAS DORES', '85', NULL, 'SENADOR TEOTÔNIO VILELA', 'ARAPIRACA', 'AL', 'francyfelismino@gmail.con', 1, 1, NULL, '$2y$10$PUawPZlOshdp/fHrS/wIQOnk5X1/I.pTbow4lIqgsvZB1r8vce6KC', NULL, NULL, NULL, NULL, '2026-02-04 11:19:24', '2026-02-04 11:19:24', NULL),
(22, 'MATEUS GONCALVES DE CASTILHO', 'matheus-2011gc@hotmail.com', '82998161122', '08954460496', '57306000', 'RUA EXPEDICIONÁRIOS BRASILEIROS', '54', NULL, 'ELDORADO', 'ARAPIRACA', 'AL', 'matheus-2011gc@hotmail.com', 1, 1, NULL, '$2y$10$prU0sRsOiGgpq0axLmaQleujAmpvK7GitraXeu2UFa1FWahZPnJ16', NULL, NULL, NULL, NULL, '2026-02-04 11:19:25', '2026-02-04 11:19:25', NULL),
(23, 'THAYNARA JESSYCA ESTEVAO CAVALCANTE', 'thaynaraestevaoc@gmail.com', '82993632028', '09510436410', '57313200', 'RUA VEREADOR PEDRO ARISTIDES DA SILVA', '89', 'A', 'BRASÍLIA', 'ARAPIRACA', 'AL', 'thaynaraestevaoc@gmail.com', 1, 1, NULL, '$2y$10$Uo9OXTKfsOvsR4ud7BmA/euJJhDpqlH2zJut0mKHUYkB/v.iUd37y', NULL, NULL, NULL, NULL, '2026-02-04 11:21:03', '2026-02-04 11:21:03', NULL),
(24, 'CARLOS EDUARDO BARBOSA DOS SANTOS', 'carloskadu86@gmail.com', '82998369832', '09769069450', '57303055', 'RUA ERCÍLIA BRANDÃO SILVA', '149', NULL, 'VERDES CAMPOS', 'ARAPIRACA', 'AL', 'carloskadu86@gmail.com', 1, 1, NULL, '$2y$10$Dcj33vKIxExR5k5GryRYI.A2/GfWsrXy4Esep5Paom73CaREcBcqS', NULL, NULL, NULL, NULL, '2026-02-04 11:21:07', '2026-02-04 11:21:07', NULL),
(25, 'STEFFANY SOARES DA SILVA', 'steffanysoares85@gmail.com', '82981569712', '12023924405', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'steffanysoares85@gmail.com', 1, 1, NULL, '$2y$10$iMdjMUCvZbSRc5Evi7RvlOuqd00G6mmicp5tbBVBFBZ.dbJ.rXo1i', NULL, NULL, NULL, NULL, '2026-02-04 11:23:12', '2026-02-04 11:23:12', NULL),
(26, 'MARISA NATHYELLE OLIVEIRA SILVA', 'marisa_manh@hotmail.com', '82999835521', '06988928448', '57306420', 'RUA COSTA CAVALCANTE', '615A', NULL, 'CAVACO', 'ARAPIRACA', 'AL', 'marisa_manh@hotmail.com', 1, 1, NULL, '$2y$10$VAs8WGjAvdHEyLbAAjdov.ZlgWozXHRcjZt9hLTPfvWgXJjPzTxOe', NULL, NULL, NULL, NULL, '2026-02-04 11:23:22', '2026-02-04 11:23:22', NULL),
(27, 'VERANIA DOS SANTOS NUNES', 'veranianunesft@gmail.com', '82999916695', '09778836477', '57307160', 'RUA MARGARITA PALMERINA DE ALMEIDA', '10', NULL, 'BAIXA GRANDE', 'ARAPIRACA', 'AL', 'veranianunesft@gmail.com', 1, 1, NULL, '$2y$10$Oqd8IbdADG87Oh.AzkEYHOXdcRjUYdkDZ3.x44ZZlyQ0lwAQFTJ9q', NULL, NULL, NULL, NULL, '2026-02-04 11:28:26', '2026-02-04 11:28:26', NULL),
(28, 'LETÍCIA DE OLIVEIRA MENDES', 'oleticiamendes@gmail.com', '82920006958', '11460354460', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'oleticiamendes@gmail.com', 1, 1, NULL, '$2y$10$ErVrDGjxEo9bkXDUaUkt7OJCRwOei4QUco34Kf8U7DhP1W53ZFWT6', NULL, NULL, NULL, NULL, '2026-02-04 11:35:57', '2026-02-04 11:35:57', NULL),
(29, 'NYCOLE FERREIRA AZEVEDO DE OLIVEIRA', 'nycole.f@outlook.com', '82996232833', '13649058413', '57303062', 'RUA JOSÉ FIRMINO NETO', NULL, NULL, 'VERDES CAMPOS', 'ARAPIRACA', 'AL', 'nycole.f@outlook.com', 1, 1, NULL, '$2y$10$1KUA3tkVQvTSqjuHYm/mwevtcSBx8XemzRSsqGmS3SU9UUuQjTgPC', NULL, NULL, NULL, NULL, '2026-02-04 11:59:40', '2026-02-04 11:59:40', NULL),
(30, 'ERIKA LARISSA S S ARAÚJO', 'erikalarissasantosilva@outlook.com', '82999511362', '12407383400', '57304495', 'RUA MANOEL LÚCIO DA SILVA', '475', 'ATÉ 580/581', 'CACIMBAS', 'ARAPIRACA', 'AL', 'erikalarissasantosilva@outlook.com', 1, 1, NULL, '$2y$10$8UCxCBQmzvIfI2qFV4VjR.QOwARIghVS/xI3pL0qpkhwMBpeT0ZEu', NULL, NULL, NULL, NULL, '2026-02-04 12:02:25', '2026-02-04 12:02:25', NULL),
(31, 'DAG ANNE CORREIA CAJUEIRO', 'daganne.cajueiro@gmail.com', '82981556083', '10954722400', '57313160', 'RUA ANDRÉ LEÃO', '328', NULL, 'BRASÍLIA', 'ARAPIRACA', 'AL', 'daganne.cajueiro@gmail.com', 1, 1, NULL, '$2y$10$UK/zst.vauAkc/re.CkOcONVOUe/AncM2qDCxbeRjVu7aua9SXtiq', NULL, NULL, NULL, NULL, '2026-02-04 12:20:32', '2026-02-04 12:20:32', NULL),
(32, 'YASMIN FAGUNDES DA SILVA LIMA', 'fagundesyasmin@hotmail.com', '82998097378', '09191177405', '57308714', 'RUA ENOQUE BEZERRA DE LIMA', '102', NULL, 'PLANALTO', 'ARAPIRACA', 'AL', 'fagundesyasmin@hotmail.com', 1, 1, NULL, '$2y$10$18hfvmn.xQgkHlEYzFKqN.ZYfXVQUpUFGCvus/SnjeGr5/dhvR58.', NULL, NULL, NULL, NULL, '2026-02-04 12:21:20', '2026-02-04 12:21:20', NULL),
(33, 'ROSEANE  FERREIRA LIMA', 'roseanel781@gmail.com', '82996963904', '10088886484', '57312251', 'PRAÇA SANTA CRUZ', '86', NULL, 'ALTO DO CRUZEIRO', 'ARAPIRACA', 'AL', 'roseanel781@gmail.com', 1, 1, NULL, '$2y$10$HwBBc8ZvDPFZ7Mhpy80.1uWUE3lV8T2g.YLaygCWRVtA/781dmi32', NULL, NULL, NULL, NULL, '2026-02-04 12:22:29', '2026-02-04 12:22:29', NULL),
(34, 'INGRID SOUSA VITAL', 'sousaingrid22@outlook.com', '82998001135', '11170545432', NULL, 'RUA CAMINHO DO SOL', '263', 'CASA', 'NILO COELHO', 'ARAPIRACA', 'AL', 'sousaingrid22@outlook.com', 1, 1, NULL, '$2y$10$HBF1cmOQ8X3b95940ePZPu01uhUEEqhonFK94ptL2mDsxEl2Ad4Ra', NULL, NULL, NULL, NULL, '2026-02-04 12:24:19', '2026-02-04 12:24:19', NULL),
(35, 'GIVALDO DE ALBUQUERQUE SILVA JÚNIOR', 'givaldojunior4321@gmail.com', '82991739321', '13720370470', '57303320', 'RUA MANOEL SATURNINO DE ALMEIDA', '31', 'CASA', 'BOA VISTA', 'ARAPIRACA', 'AL', 'givaldojunior4321@gmail.com', 1, 1, NULL, '$2y$10$YfVp2CcJZRgJqG/0t3vqgOoJc.KI77cwSopCEVR1nKRFHGS2G/t72', NULL, NULL, NULL, NULL, '2026-02-04 12:25:27', '2026-02-04 12:25:27', NULL),
(36, 'AMANDA NUNES DE OLIVEIRA', 'amanda.n.oliveira280@gmail.com', '0000000000', '08823894409', '57301452', 'RUA PROFESSORA AURORA DA CONCEIÇÃO SILVA COLAÇO', NULL, NULL, 'SÃO LUIZ', 'ARAPIRACA', 'AL', 'amanda.n.oliveira280@gmail.com', 1, 1, NULL, '$2y$10$37/JnaOnRFIOI3ibagqqZuSAlPzGzUdJ8b14dxGtUFmEFTIHBI2XC', NULL, NULL, NULL, NULL, '2026-02-04 12:25:49', '2026-02-04 12:25:49', NULL),
(37, 'MARIA BETANIA DA SILVA COSTA', 'mbetaniacosta@icloud.com', '82996328520', '01020505460', '57312420', 'RUA MARCELINO MAGALHÃES', '399', NULL, 'ALTO DO CRUZEIRO', 'ARAPIRACA', 'AL', 'mbetaniacosta@icloud.com', 1, 1, NULL, '$2y$10$isvxwlbhO5/WFBS1W1qOhOve/52lpxKqHLCDymR2kx5hfHiVLyWi2', NULL, NULL, NULL, NULL, '2026-02-04 12:26:04', '2026-02-04 12:26:04', NULL),
(38, 'ESTÉFANY MARIA VITÓRIA DOS SANTOS', 'estefanymvsants@outlook.com', '79998650710', '06327719503', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'estefanymvsants@outlook.com', 1, 1, NULL, '$2y$10$6HI3dxAyH/sQLBUUlbhep.F9JqySSuCwYiNJzcAfg7CczRqZRYZr6', NULL, NULL, NULL, NULL, '2026-02-04 12:26:13', '2026-02-04 12:26:13', NULL),
(39, 'NANÁ DA SILVA CAMPOS', 'nadjanecamposrj@gmail.com', '21980753911', '00986408441', '57305370', 'RUA PADRE AMÉRICO', '475', NULL, 'BAIXÃO', 'ARAPIRACA', 'AL', 'nadjanecamposrj@gmail.com', 1, 1, NULL, '$2y$10$gKrlrMMICOuzHuW903StneKb2jsEfbI486FM7yS2kiSK3wMDXxL/O', NULL, NULL, NULL, NULL, '2026-02-04 12:26:43', '2026-02-04 12:29:51', NULL),
(40, 'ISABELLA GONÇALVES', 'isinhasilva2004@gmail.com', '82996491824', '09983920492', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'isinhasilva2004@gmail.com', 1, 1, NULL, '$2y$10$4d7xPv1MD/aULCAsY7keqela6SZ12uo3Z33IDaRVeI9yfvnet6HGq', NULL, NULL, NULL, NULL, '2026-02-04 12:29:26', '2026-02-04 12:29:26', NULL),
(41, 'ROSÂNGELA SILVA DE LIMA', 'rosangelaue@gmail.com', '82999994945', '04390560476', NULL, NULL, NULL, NULL, NULL, 'ARAPIRACA', 'AL', 'rosangelaue@gmail.com', 1, 1, NULL, '$2y$10$8r5baYAUDkSFbr8UOJpuC.owdiF7NizFOY.MzJpy1M3nm0Gs3QMlu', NULL, NULL, NULL, NULL, '2026-02-04 12:30:18', '2026-02-04 12:30:18', NULL),
(42, 'WALMER VICTOR SILVA DOS SANTOS', 'walmervictor@gmail.com', '82991653656', '07550925461', '57313160', 'AVENIDA ANDRÉ LEÃO', '785', NULL, 'BRASÍLIA', 'ARAPIRACA', 'AL', 'walmervictor@gmail.com', 1, 1, NULL, '$2y$10$Ww.aEOrwPRV6VQQ26qIw3OY6cSgy2hhfIK93L/hCsGZT3ZCEYiZWG', NULL, NULL, NULL, NULL, '2026-02-04 12:31:21', '2026-02-04 12:31:21', NULL),
(43, 'LUIZ ANTÔNIO CAETANO NUNES', 'luizantonio.bio@hotmail.com', '82991092015', '09308115420', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'luizantonio.bio@hotmail.com', 1, 1, NULL, '$2y$10$HhxOT2BkCHVb1QvTwsWQjOUU9NTjW2QB7NlpqD1yfjkSmBh6am3vu', NULL, NULL, NULL, NULL, '2026-02-04 12:33:28', '2026-02-04 12:33:28', NULL),
(44, 'VIVIANE SILVA', 'vivi12ane15@gmail.com', '82981744604', '08128786474', '57303240', 'RUA FREI DAMIÃO DE BOZZANO', '42', NULL, 'BOA VISTA', 'ARAPIRACA', 'AL', 'vivi12ane15@gmail.com', 1, 1, NULL, '$2y$10$Hw5oaAAr3DnmsMiA/V4umORAC1o/w6LXN7.fTqfxrtT0yJ/muMaBi', NULL, NULL, NULL, NULL, '2026-02-04 12:49:31', '2026-02-04 12:49:31', NULL),
(45, 'DIEGO RICHARD', 'diegorichard25@icloud.com', '82999502257', '06366479437', NULL, NULL, NULL, NULL, NULL, 'ARAPIRACA', 'AL', 'diegorichard25@icloud.com', 1, 1, NULL, '$2y$10$dA89w1L1qGNLBLVqqo.I8OUkiM0TU6N5mD3BftAhl8akIdqcy1eZe', NULL, NULL, NULL, NULL, '2026-02-04 13:02:01', '2026-02-04 13:02:01', NULL),
(46, 'NAYARA DA SILVA OLIVEIRA', 'nayadm22@gmail.com', '82999751604', '07715061476', '57313310', 'RUA NOSSA SENHORA DO Ó', '244', NULL, 'BRASÍLIA', 'ARAPIRACA', 'AL', 'nayadm22@gmail.com', 1, 1, NULL, '$2y$10$U6ayw076ATu75GuIVkOm0OeBO0WZ.tJnSNWct56TLzj7Ogt/vPQKu', NULL, NULL, NULL, NULL, '2026-02-04 13:06:35', '2026-02-04 13:06:35', NULL),
(47, 'KARLINNY H LUCENA', 'karlinny@hotmail.com', '82996124170', '06758256448', '57300030', 'RUA BOA VISTA', '221', NULL, 'CENTRO', 'ARAPIRACA', 'AL', 'karlinny@hotmail.com', 1, 1, NULL, '$2y$10$mxW7VanbXZi0nTAG/MCMluVCxzg6vQFRBUgHq0glqPJKbd02E5cmi', NULL, NULL, NULL, NULL, '2026-02-04 13:08:54', '2026-02-04 13:08:54', NULL),
(48, 'CARLA DE JESUS VAZ LOPES', 'nutricionista_vaz@hotmail.com', '75991099087', '02747144500', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nutricionista_vaz@hotmail.com', 1, 1, NULL, '$2y$10$3Coi2t4CBQG01JO8p1L3GOoj0H1wcFvbRYE2/8GmmD/ZqVqh8hfRK', NULL, NULL, NULL, NULL, '2026-02-04 13:14:46', '2026-02-04 13:14:46', NULL),
(49, 'RAFAELLE CAVALCANTI', 'cavalcantirafaelle@gmail.com', '82999920739', '08306218493', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'cavalcantirafaelle@gmail.com', 1, 1, NULL, '$2y$10$xKjxx0/0KnSL/No022.S/uaCF4liUm0vd4Bp6HGoV2fgsxm699Hpm', NULL, NULL, NULL, NULL, '2026-02-04 13:26:26', '2026-02-04 13:26:26', NULL),
(50, 'NATALINE SANTOS FERREIRA', 'tytanataline@gmail.com', '82988932070', '08960471488', '57303018', 'RUA SEBASTIÃO FLORENTINO DOS SANTOS', '93', NULL, 'VERDES CAMPOS', 'ARAPIRACA', 'AL', 'tytanataline@gmail.com', 1, 1, NULL, '$2y$10$wqy04nHcIs4mEec3xB.tWOJRf9gjB.MHv9w0KZdocL0PsJpvr1F.e', NULL, NULL, NULL, NULL, '2026-02-04 13:27:46', '2026-02-04 13:27:46', NULL),
(51, 'BRUNA BARBOZA DOS SANTOS', 'bruninha_al@hotmail.com', '82981416667', '07154498420', '57306200', 'RUA MANOEL BERNARDINO DOS SANTOS', '22', NULL, 'ELDORADO', 'ARAPIRACA', 'AL', 'bruninha_al@hotmail.com', 1, 1, NULL, '$2y$10$6tUb3faE6R0Kn1o.QV.2u.t61W9sJw13GODAAaXQzPocOlg9YwL6u', NULL, NULL, NULL, NULL, '2026-02-04 13:31:08', '2026-02-04 13:31:08', NULL),
(52, 'NEUSVALDO PEDRO DA SILVA JUNIOR', 'jr.gunswrk@icloud.com', '82991484561', '09922272407', '57318750', 'PV CAPIM', '9', NULL, 'CANAÃ', 'ARAPIRACA', 'AL', 'jr.gunswrk@icloud.com', 1, 1, NULL, '$2y$10$7daodybxlyPimMHVsTFUbO.5UH7mIfCz1T.5p5jKZt5/12rP2g62.', NULL, NULL, NULL, NULL, '2026-02-04 14:05:19', '2026-02-04 14:05:19', NULL),
(53, 'KARLLOS EDUARDO', 'karllose514@gmail.com', '82991365574', '12274718407', '57313070', 'RUA SENADOR RUI PALMEIRA', '117', NULL, 'BRASÍLIA', 'ARAPIRACA', 'AL', 'karllose514@gmail.com', 1, 1, NULL, '$2y$10$usYqdyu09j2USRCCxCKD9Oe.9RAQnxrTuV0oinaOi.lz3UV36BCLa', NULL, NULL, NULL, NULL, '2026-02-04 14:13:10', '2026-02-04 14:13:10', NULL),
(54, 'VITOR MANOEL IZIDIO SEVERIANO', 'vitormanoel1897@gmail.com', '82999210585', '11118494474', '57316899', 'ÁREA RURAL', '41', 'POVOADO CAPIM', 'ÁREA RURAL DE ARAPIRACA', 'ARAPIRACA', 'AL', 'vitormanoel1897@gmail.com', 1, 1, NULL, '$2y$10$FPnEY.8BaMTaMMs2tyG9FuQKVvStj/MdfGoDbmqayJ5vibDDBNzrO', NULL, NULL, NULL, NULL, '2026-02-04 14:15:44', '2026-02-04 14:15:44', NULL),
(55, 'MARIA NEUZA DA SILVA', 'marianeuza2006@gmail.com', '82996539572', '06074794421', '57305570', 'RUA ANTÔNIO MENEZES NETO', '344', 'AP. 210 B2', 'ZÉLIA BARBOSA ROCHA', 'ARAPIRACA', 'AL', 'marianeuza2006@gmail.com', 1, 1, NULL, '$2y$10$22eBberh14T11Zs0Wef5puhbaUuf422ZeMSwd8Cbg5KlzSvVaQ8ju', NULL, NULL, NULL, NULL, '2026-02-04 14:32:24', '2026-02-04 14:32:24', NULL),
(56, 'MARIA ANDÍVINE VITAL DE OLIVEIRA', 'mandivinevoliveira@gmail.com', '8296227175', '16970962474', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'mandivinevoliveira@gmail.com', 1, 1, NULL, '$2y$10$vFR8n8QNJaYoVN7BbqR5Ee.OXNd8G2sJSCkAk1lL6kBeEveFok6im', NULL, NULL, NULL, NULL, '2026-02-04 14:50:00', '2026-02-04 14:50:00', NULL),
(57, 'RAFAEL REZENDE DA SILVA', 'rezenderafael2014@gmail.com', '82982177003', '12027050493', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'rezenderafael2014@gmail.com', 1, 1, NULL, '$2y$10$IVyBC99frO63GwIObQbfMebOhOflw/O9NMMX6.bbLcahn1piuLIXy', NULL, NULL, NULL, NULL, '2026-02-04 14:58:47', '2026-02-04 14:58:47', NULL),
(58, 'ANTONIO MARTINS SILVA', 'martins11antonio2022@gmail.com', '82981200339', '03023913471', '57303256', 'RUA ANTÔNIO ALFREDO SOARES', '176', '(LOT B NASCENTES)', 'BOA VISTA', 'ARAPIRACA', 'AL', 'martins11antonio2022@gmail.com', 1, 1, NULL, '$2y$10$zTu1FX1LZ/5AB/S8BMhwAO9/.munDK7cj3aX3lSWDSg5a4Rk6JVmm', NULL, NULL, NULL, NULL, '2026-02-04 15:26:43', '2026-02-04 15:26:43', NULL),
(59, 'JOÃO GUILHERME MENEZES DOS SANTOS', 'joaoguilhermem12345@gmail.com', '82988508847', '16518915404', '57305814', 'RUA TOMÁZIA VENTURA DE FARIAS', '83', 'CASA', 'ZÉLIA BARBOSA ROCHA', 'ARAPIRACA', 'AL', 'joaoguilhermem12345@gmail.com', 1, 1, NULL, '$2y$10$ZRDuLmt1IuI5s2HWR0ykGOCFkSOJlCkqXilYZrLqH0y7b9rVDy29i', NULL, NULL, NULL, NULL, '2026-02-04 16:12:27', '2026-02-04 16:12:27', NULL),
(60, 'ELVIS OLIVEIRA DOS SANTOS', 'elvistecladista@hotmail.com', '82981069262', '06453608480', '57310270', 'RUA JOÃO FERREIRA DE ALBUQUERQUE', '758', 'POR TRÁS DA IGREJA SANTA EDEWIRGES, DO BOSQUE.', 'ARAPIRACA', 'ARAPIRACA', 'AL', 'elvistecladista@hotmail.com', 1, 1, NULL, '$2y$10$.bOJ9M/iwvKX7Ico5lRHAO7ooE6/Iz/m728DALnxIN5oGhRhhpW5W', NULL, NULL, NULL, NULL, '2026-02-04 16:12:37', '2026-02-04 16:14:46', NULL),
(61, 'JULIO AQUA', 'julio@aqua.com.br', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'julio@aqua.com.br', 1, 1, NULL, '$2y$10$OPqyt4q5gCHGsI2Fg88GJ.9kHjBK2xjzyGort8X4g7QUFW62YwD6m', NULL, NULL, NULL, NULL, '2026-02-04 18:58:21', '2026-02-05 13:51:13', NULL),
(62, 'EVILY EMANUELLE DA SILVA', 'evily.silva@arapiraca.ufal.br', '82999936116', '70153625465', NULL, NULL, NULL, NULL, NULL, 'ARAPIRACA', 'AL', 'evily.silva@arapiraca.ufal.br', 1, 1, NULL, '$2y$10$cZAQYxDMsdqi0zGzBVpLz.S8sAV.wRK4hAickCpoyItbjcfLULCWa', NULL, NULL, NULL, NULL, '2026-02-04 20:13:42', '2026-02-04 20:13:42', NULL),
(63, 'MARIA KAROLINA SAMPAIO MONTEIRO', 'karolinamonteiro23@hotmail.com', '82996002238', '09559317466', '57309067', 'RUA JOSÉ LAELSON DE LIMA', '106', 'RES POR DO SOL', 'BOM SUCESSO', 'ARAPIRACA', 'AL', 'karolinamonteiro23@hotmail.com', 1, 1, NULL, '$2y$10$ZgsJASV3i4nZIx58J6NGbOLaNBMD08T0uOjF8btjufQGohxjEms6.', NULL, NULL, NULL, NULL, '2026-02-04 23:20:23', '2026-02-04 23:20:23', NULL),
(64, 'CLAUDILENE DA SILVA SANTOS', 'sslvcacau@gmail.com', '82991395398', '70440278490', '57307155', 'RUA SANTA TEREZA', '78', NULL, 'BAIXA GRANDE', 'ARAPIRACA', 'AL', 'sslvcacau@gmail.com', 1, 1, NULL, '$2y$10$n66vMBZ1fOrrlm17zn9jgOwTB/Ai8OZTC4S5NDpojqOIX2LfJt3Ja', NULL, NULL, NULL, NULL, '2026-02-04 23:23:39', '2026-02-04 23:23:39', NULL),
(65, 'HENRIQUE PEREIRA FREITAS DE MENDONÇA', 'henriquepereiraf@gmail.com', '82999398995', '05174588458', '57307060', 'RUA FRANCISCO OTÍLIO DOS SANTOS', '157', NULL, 'BAIXA GRANDE', 'ARAPIRACA', 'AL', 'henriquepereiraf@gmail.com', 1, 1, NULL, '$2y$10$yRA5xLTr0xEhlWCEXR1R5uNzSFgtRUG98tUyfRr4Cp0o0Vyz.gv9e', NULL, NULL, NULL, NULL, '2026-02-04 23:32:48', '2026-02-04 23:32:48', NULL),
(66, 'JERLANE CAVALCANTE', 'jerlane.cavalcante@hotmail.com', '82999868311', '04949024426', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jerlane.cavalcante@hotmail.com', 1, 1, NULL, '$2y$10$G4UUayvmszbKkWD0PJ4hMercmBTJCXIUzYZudngOaajiSnjhJAAku', NULL, NULL, NULL, NULL, '2026-02-05 02:31:57', '2026-02-05 02:31:57', NULL),
(67, 'ARYENE PEREIRA', 'aryene.pereira.silva@gmail.com', '82996161759', '11732445494', '57315746', 'RUA ANTONIO OTÁVIO DE OLIVEIRA', '180', NULL, 'SENADOR ARNON DE MELO', 'ARAPIRACA', 'AL', 'aryene.pereira.silva@gmail.com', 1, 1, NULL, '$2y$10$WQplHLIF5qswiWnlvCYmD.q8mUC3X1QJAphchtp5tHoKNaNGgi716', NULL, NULL, NULL, NULL, '2026-02-05 14:25:59', '2026-02-05 14:25:59', NULL),
(68, 'JOSÉ CARLOS BARBOZA ROCHA', 'jcarlosbr777@gmail.com', '82982020644', '02765759464', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, '$2y$10$EvM7S0YaANKry.PtkOIQYe7cR7j3tNfmbzZcMS.Aqe3uqLWPAPJdm', NULL, NULL, NULL, NULL, '2026-02-05 17:33:24', '2026-02-05 17:49:06', NULL),
(69, 'TIAGO RENAN', 'tiago.renan12345@gmail.com', '82999345152', '05408590445', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tiago.renan12345@gmail.com', 1, 1, NULL, '$2y$10$WpaXc2NVKghypJJcDnH90eJwBdQf23LZZA5MGdC1.O0YdhbQrXI/a', NULL, NULL, NULL, NULL, '2026-02-05 17:54:33', '2026-02-05 17:54:33', NULL),
(70, 'ARNON VICTOR SILVA DE OLIVEIRA', 'arnonvictor7@gmail.com', '82999139531', '14138730494', NULL, NULL, '201', NULL, 'CENTRO', 'IGACI', 'AL', 'arnonvictor7@gmail.com', 1, 1, NULL, '$2y$10$HDLv02X2LKR41PKwrhJtNOAgjouqoS8NhdK20oWeUw49PMw/6rK0W', '16784eb5c03bf5c940d5638b7ad58199d480f808eac8fc124e0f7dff4d97455c', '2026-02-05 20:45:52', NULL, NULL, '2026-02-05 22:44:07', '2026-02-05 22:45:52', NULL),
(71, 'JULIANA FERNANDA', 'jjuhliana00@gmail.com', '82996022920', '13379638447', '57305306', 'GREGÓRIO NEVES', '4', NULL, 'MANOEL TELES', 'ARAPIRACA', 'AL', 'jjuhliana00@gmail.com', 1, 1, NULL, '$2y$10$IgStGpiz8tAqtG4PDXRsYebsvO/E9FUkd2UU0Fns53aiRZZdm5tua', NULL, NULL, NULL, NULL, '2026-02-06 12:48:37', '2026-02-06 12:48:37', NULL),
(72, 'NATALY MARIA COSTA SILVA', 'nataly.prof.efe@gmail.com', '82999528221', '12279687445', '57318100', 'POVOADO CANGANDU DE CIMA', '45', NULL, 'CANGANDU', 'ARAPIRACA', 'AL', 'nataly.prof.efe@gmail.com', 1, 1, NULL, '$2y$10$tcxsxUFyG6MiyGOnenxdTedB.63ILhSApFqWd..icDg7MGa8ssjKG', NULL, NULL, NULL, NULL, '2026-02-06 13:22:51', '2026-02-06 13:22:51', NULL),
(73, 'RONEIDE SANTOS', 'roneide4santos@gmail.com', '82996090345', '12305276486', '57314105', 'AVENIDA DEPUTADA CECI CUNHA', '922', 'DE 670 AO FIM - LADO PAR', 'ITAPOÃ', 'ARAPIRACA', 'AL', 'roneide4santos@gmail.com', 1, 1, NULL, '$2y$10$t6yS9XdocYBNrCihCymDLuCK6U19AaOzRirYAtY4jY9ET41Sz8baG', NULL, NULL, NULL, NULL, '2026-02-08 13:53:58', '2026-02-08 13:53:58', NULL),
(74, 'LUCAS RAFAEL DOS SANTOS BATISTA', 'lucasrafael.gostoso24@gmail.com', '82996315911', '07467957424', NULL, NULL, '16', 'CASA', 'ALTO DO CRUZEIRO', 'ARAPIRACA', 'AL', 'lucasrafael.gostoso24@gmail.com', 1, 1, NULL, '$2y$10$xE7HYFqX2R9WZqM8KlndEe2V1Y6rIJelItZIMenGHb.WIsNmin3p2', NULL, NULL, NULL, NULL, '2026-02-08 13:56:23', '2026-02-08 13:56:23', NULL),
(75, 'ANDREZA FERREIRA DE FARIAS', 'andreza-fox2010@hotmail.com', '82999317238', '10982001444', NULL, 'RUA', '21', NULL, NULL, 'TAQUARANA', 'AL', 'andreza-fox2010@hotmail.com', 1, 1, NULL, '$2y$10$q8A1VJts/3Dnj2EanyBhWuc6amqXjlrKmEJCZhgcZ9QELDzZmoeBu', NULL, NULL, NULL, NULL, '2026-02-08 13:58:13', '2026-02-08 13:58:13', NULL),
(76, 'MAYCKONN DOUGLAS FREIRE BARBOSA', 'mayckonn@hotmail.com', '82988360671', '04832910485', '57305610', 'RUA MIGUEL TERTULIANO DA SILVA', '08', 'RES RIVIERA DO LAGO, QD D, L 28', 'ZÉLIA BARBOSA ROCHA', 'ARAPIRACA', 'AL', 'mayckonn@hotmail.com', 1, 1, NULL, '$2y$10$5PXBc2rMKDMnZrBH6quMbuLNZ2RxcIKjpxiZDr9S83z428vaYtgCG', NULL, NULL, NULL, NULL, '2026-02-08 14:00:53', '2026-02-08 14:00:53', NULL),
(77, 'LUIZ FILHO DA SILVA', 'luizbola2915@gmail.com', '82996325051', '80441165400', '57305620', 'RUA COSTA CAVALCANTE', '615', 'CASA', 'ZÉLIA BARBOSA ROCHA', 'ARAPIRACA', 'AL', 'luizbola2915@gmail.com', 1, 1, NULL, '$2y$10$A1PptWyqEYOdY8Hrh/tRL.fXH77.38lH/8GJfm5xFoflN5ZqJwAHm', NULL, NULL, NULL, NULL, '2026-02-08 14:01:38', '2026-02-08 14:01:38', NULL),
(78, 'ADILSON MONTEIRO DA SILVA', 'adilsonangela1961@gmail.com', '82998390128', '24014923487', '57307762', 'RUA JOÃO LUCAS FARIAS PEREIRA', '133', '(LOT NOVO JARDIM)', 'JARDIM ESPERANÇA', 'ARAPIRACA', 'AL', 'adilsonangela1961@gmail.com', 1, 1, NULL, '$2y$10$Yjp8xxRxoeJJTRErUXKrauBpGRb2WeEYJUy5B4pEb3KEvFSGvk6OK', NULL, NULL, NULL, NULL, '2026-02-08 14:10:45', '2026-02-08 14:10:45', NULL),
(79, 'JOSE RICARDO SALES SALES', 'josericardosales09@gmail.com', '82996153330', '01368237428', '57312020', 'RUA OURO BRANCO', '1026', NULL, 'SANTA ESMERALDA', 'ARAPIRACA', 'AL', 'josericardosales09@gmail.com', 1, 1, NULL, '$2y$10$ApPhSYJ16rcHB.WcKbAZkOAiA8tu20jJotGRiEFCS2y6aQsqs1YdW', NULL, NULL, NULL, NULL, '2026-02-08 15:45:35', '2026-02-08 15:45:35', NULL),
(80, 'JANIEIDE DOS SANTOS SILVA', 'janny_god@hotmail.com', '82991734158', '07922585411', NULL, 'RUA EXPEDICIONÁRIOS BRASILEIROS', '611', 'SALA 3', 'ARAPIRACA- BAIXA GRANDE', 'ARAPIRACA', 'AL', 'janny_god@hotmail.com', 1, 1, NULL, '$2y$10$gUt8VSzlUNt9E5wxME4KN.M07sC91CUm1D35L66bZPlVgovlguQny', NULL, NULL, NULL, NULL, '2026-02-08 16:38:13', '2026-02-08 16:38:13', NULL),
(81, 'WERLISSON SANTOS DIAS', 'diaswerlisson@gmail.com', '82981059074', '06907769425', '57301030', 'RUA NOSSA SENHORA DO Ó', '244', NULL, 'ARAPIRACA', 'ARAPIRACA', 'AL', 'diaswerlisson@gmail.com', 1, 1, NULL, '$2y$10$kmiPMou3Uv1Hs9EVCaMBqe9147RjePsX39FC4P3fN/3sk.I7/.FLu', NULL, NULL, NULL, NULL, '2026-02-08 17:16:56', '2026-02-08 17:16:56', NULL),
(82, 'ROSANGELA DA ROCHA SIQUEIRA TAVARES', 'rosangelarochasiqueira11@gmail.com', '82996448407', '66237106472', '57306420', 'RUA COSTA CAVALCANTE', '277', 'ED.GRAND FIORI', 'CAVACO', 'ARAPIRACA', 'AL', 'rosangelarochasiqueira11@gmail.com', 1, 1, NULL, '$2y$10$ZMOM0RxtzHfVd/AUnHDHT.wF4MWW9495NCZOF4F/XZ2YDe/yUyww.', NULL, NULL, NULL, NULL, '2026-02-08 18:24:21', '2026-02-08 18:24:21', NULL),
(83, 'ELICLEIDE RODRIGUES DA SILVA', 'elishalomrodrigues@gmail.com', '82999705959', '06145125497', '57306790', 'RUA MARTA JANAÍNA', '55', 'CASA', 'CAVACO', 'ARAPIRACA', 'AL', 'elishalomrodrigues@gmail.com', 1, 1, NULL, '$2y$10$zLg9/3W9joeTfXXxTxqpceUOL9hnUFZK3cll3JcaTVPQbDmRDzTB.', NULL, NULL, NULL, NULL, '2026-02-08 20:09:45', '2026-02-08 20:09:45', NULL),
(84, 'LAMENIA RODRIGUES GOMES DE OLIVEIRA', 'lamenia.oliveira.35@outlook.com', '82999335533', '10871491427', '57300520', 'RUA JORNALISTA JOSÉ OLAVO BISPO', '30', NULL, 'CENTRO', 'ARAPIRACA', 'AL', 'lamenia.oliveira.35@outlook.com', 1, 1, NULL, '$2y$10$uUcpyPcaNw5S3Qi/WYZWh.f0TB3t1RjDTX5vF.GDUL0SLvU1XW/8G', NULL, NULL, NULL, NULL, '2026-02-08 20:34:16', '2026-02-08 20:34:16', NULL),
(85, 'KELLY CRISTINA SANTOS COSTA BARBOSA', 'kellycscb@gmail.com', '82998212817', '15125477435', '57312460', 'RUA SANTA MARIA', '284', 'CASA', 'ALTO DO CRUZEIRO', 'ARAPIRACA', 'AL', 'kellycscb@gmail.com', 1, 1, NULL, '$2y$10$2vh9e6bUIaVs3v5174U7veBe/OkHvWHYDBHoyIhkE6g7uPtiFMY3G', NULL, NULL, NULL, NULL, '2026-02-08 22:12:39', '2026-02-08 22:12:39', NULL),
(86, 'JOSÉ MURILO FORTUNATO PEREIRA', 'jose97fortunato@gmail.com', '8299207234', '41580586864', '57300460', 'RUA SANTA TEREZINHA', NULL, NULL, 'CENTRO', 'ARAPIRACA', 'AL', 'jose97fortunato@gmail.com', 1, 1, NULL, '$2y$10$HtfitZhhjY5H1hjfa0e0cuZWk2NyubRtuDDmhQdxWnYUeRP7cNeCi', NULL, NULL, NULL, NULL, '2026-02-09 08:47:36', '2026-02-09 08:47:36', NULL),
(87, 'MANUELLY NUNES DA SILVA', 'manuellysilva574@gmail.com', '82991878075', '12474552464', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'manuellysilva574@gmail.com', 1, 1, NULL, '$2y$10$tbWBvTYc9Mu7T76Rcu3H1.k8oEd5h83JBYMNvzYeLyJzUW9i8BIDK', NULL, NULL, NULL, NULL, '2026-02-09 15:37:41', '2026-02-09 15:37:41', NULL),
(88, 'DALVAN FERREIRA', 'gmexcursoes@gmail.com', '82999436141', '07615828430', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'gmexcursoes@gmail.com', 1, 1, NULL, '$2y$10$ICaRummc6c3kKg1X1ql/NuBdXHIft8h5eIoMtj3ZBBlBwCuk0avSq', NULL, NULL, NULL, NULL, '2026-02-09 16:42:54', '2026-02-09 16:42:54', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario_tenant_backup`
--

CREATE TABLE IF NOT EXISTS `usuario_tenant_backup` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `plano_id` int(11) DEFAULT NULL,
  `status` enum('ativo','inativo','suspenso','cancelado') DEFAULT 'ativo',
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuario_tenant_backup`
--

INSERT INTO `usuario_tenant_backup` (`id`, `usuario_id`, `tenant_id`, `plano_id`, `status`, `data_inicio`, `data_fim`, `created_at`, `updated_at`) VALUES
(1, 2, 2, NULL, 'ativo', '2026-01-20', NULL, '2026-01-20 14:01:00', '2026-01-20 14:01:00'),
(2, 3, 2, NULL, 'ativo', '2026-01-20', NULL, '2026-01-20 17:42:31', '2026-01-20 17:42:31');

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_assinaturas`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE IF NOT EXISTS `vw_assinaturas` (
`id` int(11)
,`tenant_id` int(11)
,`aluno_id` int(11)
,`matricula_id` int(11)
,`plano_id` int(11)
,`gateway` varchar(30)
,`gateway_nome` varchar(50)
,`gateway_assinatura_id` varchar(100)
,`status` varchar(20)
,`status_nome` varchar(50)
,`status_cor` varchar(7)
,`valor` decimal(10,2)
,`moeda` varchar(3)
,`frequencia` varchar(20)
,`frequencia_nome` varchar(30)
,`frequencia_dias` int(11)
,`data_inicio` date
,`data_fim` date
,`proxima_cobranca` date
,`ultima_cobranca` date
,`metodo_pagamento` varchar(30)
,`metodo_pagamento_nome` varchar(50)
,`cartao_ultimos_digitos` varchar(4)
,`cartao_bandeira` varchar(20)
,`cancelado_por` varchar(20)
,`motivo_cancelamento` text
,`criado_em` timestamp
,`atualizado_em` timestamp
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_email_stats_daily`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE IF NOT EXISTS `vw_email_stats_daily` (
`data` date
,`tenant_id` int(11)
,`email_type` varchar(50)
,`status` enum('pending','sent','failed','bounced')
,`total` bigint(21)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_email_success_rate`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE IF NOT EXISTS `vw_email_success_rate` (
`tenant_id` int(11)
,`total_emails` bigint(21)
,`enviados` decimal(22,0)
,`falhas` decimal(22,0)
,`taxa_sucesso` decimal(28,2)
);

-- --------------------------------------------------------

--
-- Estrutura para tabela `wods`
--

CREATE TABLE IF NOT EXISTS `wods` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `modalidade_id` int(11) DEFAULT NULL,
  `data` date NOT NULL,
  `titulo` varchar(120) NOT NULL,
  `descricao` text DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `criado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `wods`
--

INSERT INTO `wods` (`id`, `tenant_id`, `modalidade_id`, `data`, `titulo`, `descricao`, `status`, `criado_por`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2026-01-21', 'Conquistando o munto', '', 'published', NULL, '2026-01-21 18:23:40', '2026-01-21 18:28:50'),
(2, 2, 2, '2026-01-21', 'Até que os Snatchs nos separe', '', 'published', NULL, '2026-01-21 18:53:17', '2026-01-21 18:53:17'),
(3, 2, 1, '2026-01-29', 'Modo Olimpiadas', '', 'published', NULL, '2026-01-29 03:14:18', '2026-01-29 03:14:18');

-- --------------------------------------------------------

--
-- Estrutura para tabela `wod_blocos`
--

CREATE TABLE IF NOT EXISTS `wod_blocos` (
  `id` int(11) NOT NULL,
  `wod_id` int(11) NOT NULL,
  `ordem` int(11) DEFAULT NULL,
  `tipo` enum('warmup','strength','metcon','accessory','cooldown','note') DEFAULT NULL,
  `titulo` varchar(120) DEFAULT NULL,
  `conteudo` text NOT NULL,
  `tempo_cap` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `wod_blocos`
--

INSERT INTO `wod_blocos` (`id`, `wod_id`, `ordem`, `tipo`, `titulo`, `conteudo`, `tempo_cap`, `created_at`, `updated_at`) VALUES
(1, 1, 0, 'warmup', '', '100m livre\n200m peito\n300m livre', '10 min', '2026-01-21 18:23:40', '2026-01-21 18:23:40'),
(2, 1, 0, 'metcon', '', 'Técnica de braçadas', '20 min', '2026-01-21 18:23:40', '2026-01-21 18:23:40'),
(3, 1, 0, 'metcon', '', '100m livre\n100m costa\n', '25 min', '2026-01-21 18:23:40', '2026-01-21 18:23:40'),
(4, 2, 0, 'warmup', '', '100m run', '10 min', '2026-01-21 18:53:17', '2026-01-21 18:53:17'),
(5, 2, 0, 'metcon', '', 'Snatch High Pull', '10 min', '2026-01-21 18:53:17', '2026-01-21 18:53:17'),
(6, 2, 0, 'metcon', '', '100 Snatchs', '10 min', '2026-01-21 18:53:17', '2026-01-21 18:53:17'),
(7, 3, 0, 'warmup', '', 'Segura na borda e bate só perna', '5 min', '2026-01-29 03:14:18', '2026-01-29 03:14:18'),
(8, 3, 0, 'strength', '', '100m com palmar', '10 min', '2026-01-29 03:14:18', '2026-01-29 03:14:18');

-- --------------------------------------------------------

--
-- Estrutura para tabela `wod_resultados`
--

CREATE TABLE IF NOT EXISTS `wod_resultados` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `wod_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `variacao_id` int(11) DEFAULT NULL,
  `resultado` varchar(50) DEFAULT NULL,
  `tempo_total` varchar(20) DEFAULT NULL,
  `repeticoes` int(11) DEFAULT NULL,
  `peso` decimal(10,2) DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `wod_variacoes`
--

CREATE TABLE IF NOT EXISTS `wod_variacoes` (
  `id` int(11) NOT NULL,
  `wod_id` int(11) NOT NULL,
  `nome` varchar(40) NOT NULL,
  `descricao` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `wod_variacoes`
--

INSERT INTO `wod_variacoes` (`id`, `wod_id`, `nome`, `descricao`, `created_at`, `updated_at`) VALUES
(2, 2, 'RX', '95lb', '2026-01-21 18:53:17', '2026-01-21 18:53:17'),
(3, 2, 'SC', '65lb', '2026-01-21 18:53:17', '2026-01-21 18:53:17'),
(4, 3, 'RX', NULL, '2026-01-29 03:14:18', '2026-01-29 03:14:18');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `alunos`
--
ALTER TABLE `alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_aluno_usuario` (`usuario_id`),
  ADD KEY `idx_alunos_foto_caminho` (`foto_caminho`);

--
-- Índices de tabela `assinaturas`
--
ALTER TABLE `assinaturas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricula_id` (`matricula_id`),
  ADD KEY `plano_id` (`plano_id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `frequencia_id` (`frequencia_id`),
  ADD KEY `metodo_pagamento_id` (`metodo_pagamento_id`),
  ADD KEY `cancelado_por_id` (`cancelado_por_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_matricula` (`matricula_id`),
  ADD KEY `idx_gateway` (`gateway_id`,`gateway_assinatura_id`),
  ADD KEY `idx_status` (`tenant_id`,`status_id`),
  ADD KEY `idx_proxima_cobranca` (`proxima_cobranca`,`status_id`),
  ADD KEY `idx_aluno_status` (`aluno_id`,`status_id`),
  ADD KEY `idx_tipo_cobranca` (`tipo_cobranca`),
  ADD KEY `idx_assinaturas_external_reference` (`external_reference`);

--
-- Índices de tabela `assinaturas_mercadopago`
--
ALTER TABLE `assinaturas_mercadopago`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assinaturas_tenant` (`tenant_id`),
  ADD KEY `idx_assinaturas_matricula` (`matricula_id`),
  ADD KEY `idx_assinaturas_aluno` (`aluno_id`),
  ADD KEY `idx_assinaturas_status` (`status`),
  ADD KEY `idx_assinaturas_mp_id` (`mp_preapproval_id`),
  ADD KEY `idx_assinaturas_proxima_cobranca` (`proxima_cobranca`),
  ADD KEY `plano_ciclo_id` (`plano_ciclo_id`),
  ADD KEY `cancelado_por` (`cancelado_por`),
  ADD KEY `idx_assinaturas_tenant_status` (`tenant_id`,`status`);

--
-- Índices de tabela `assinatura_cancelamento_tipos`
--
ALTER TABLE `assinatura_cancelamento_tipos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `assinatura_frequencias`
--
ALTER TABLE `assinatura_frequencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `assinatura_gateways`
--
ALTER TABLE `assinatura_gateways`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `assinatura_status`
--
ALTER TABLE `assinatura_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `checkins`
--
ALTER TABLE `checkins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_aluno_horario_data` (`aluno_id`,`horario_id`,`data_checkin_date`),
  ADD KEY `idx_checkins_horario_usuario` (`horario_id`),
  ADD KEY `fk_checkin_admin` (`admin_id`),
  ADD KEY `idx_checkins_admin` (`registrado_por_admin`,`admin_id`),
  ADD KEY `idx_checkins_data` (`data_checkin_date`),
  ADD KEY `idx_tenant_usuario_data` (`tenant_id`,`data_checkin_date`),
  ADD KEY `idx_tenant_horario_data` (`tenant_id`,`horario_id`,`data_checkin_date`),
  ADD KEY `idx_tenant_data` (`tenant_id`,`data_checkin_date`),
  ADD KEY `idx_checkins_turma` (`turma_id`),
  ADD KEY `idx_checkins_aluno_id` (`aluno_id`),
  ADD KEY `idx_checkins_horario_aluno` (`horario_id`,`aluno_id`),
  ADD KEY `idx_tenant_aluno_data` (`tenant_id`,`aluno_id`,`data_checkin_date`);

--
-- Índices de tabela `contas_receber`
--
ALTER TABLE `contas_receber`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_conta_mensal` (`tenant_id`,`usuario_id`,`plano_id`,`referencia_mes`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `historico_plano_id` (`historico_plano_id`),
  ADD KEY `proxima_conta_id` (`proxima_conta_id`),
  ADD KEY `conta_origem_id` (`conta_origem_id`),
  ADD KEY `criado_por` (`criado_por`),
  ADD KEY `baixa_por` (`baixa_por`),
  ADD KEY `idx_tenant_usuario` (`tenant_id`,`usuario_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_data_vencimento` (`data_vencimento`),
  ADD KEY `idx_plano` (`plano_id`),
  ADD KEY `idx_referencia` (`referencia_mes`),
  ADD KEY `idx_conta_forma_pagamento` (`forma_pagamento_id`);

--
-- Índices de tabela `dias`
--
ALTER TABLE `dias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `data` (`data`),
  ADD KEY `idx_dias_data` (`data`),
  ADD KEY `idx_tenant_data` (`data`);

--
-- Índices de tabela `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_logs_tenant` (`tenant_id`),
  ADD KEY `idx_email_logs_usuario` (`usuario_id`),
  ADD KEY `idx_email_logs_to_email` (`to_email`),
  ADD KEY `idx_email_logs_status` (`status`),
  ADD KEY `idx_email_logs_type` (`email_type`),
  ADD KEY `idx_email_logs_created` (`created_at`),
  ADD KEY `idx_email_logs_provider` (`provider`);

--
-- Índices de tabela `formas_pagamento`
--
ALTER TABLE `formas_pagamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_forma_pagamento_nome` (`nome`),
  ADD KEY `idx_forma_pagamento_ativo` (`ativo`);

--
-- Índices de tabela `historico_planos`
--
ALTER TABLE `historico_planos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `criado_por` (`criado_por`),
  ADD KEY `idx_usuario_historico` (`usuario_id`),
  ADD KEY `idx_plano_anterior` (`plano_anterior_id`),
  ADD KEY `idx_plano_novo` (`plano_novo_id`),
  ADD KEY `idx_data_inicio` (`data_inicio`);

--
-- Índices de tabela `horarios`
--
ALTER TABLE `horarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_dia_hora` (`dia_id`,`hora`),
  ADD KEY `idx_horarios_dia` (`dia_id`),
  ADD KEY `idx_horarios_dia_ativo` (`dia_id`,`ativo`),
  ADD KEY `idx_tenant_horarios` (`dia_id`);

--
-- Índices de tabela `inscricoes_turmas`
--
ALTER TABLE `inscricoes_turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_inscricoes_turma_usuario` (`turma_id`,`usuario_id`),
  ADD KEY `idx_inscricoes_turma` (`turma_id`),
  ADD KEY `idx_inscricoes_usuario` (`usuario_id`),
  ADD KEY `idx_inscricoes_status` (`status`);

--
-- Índices de tabela `matriculas`
--
ALTER TABLE `matriculas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_usuario` (`tenant_id`),
  ADD KEY `idx_data_vencimento` (`data_vencimento`),
  ADD KEY `idx_plano` (`plano_id`),
  ADD KEY `matricula_anterior_id` (`matricula_anterior_id`),
  ADD KEY `plano_anterior_id` (`plano_anterior_id`),
  ADD KEY `criado_por` (`criado_por`),
  ADD KEY `cancelado_por` (`cancelado_por`),
  ADD KEY `idx_motivo_id` (`motivo_id`),
  ADD KEY `idx_aluno_id` (`aluno_id`),
  ADD KEY `fk_matriculas_status` (`status_id`),
  ADD KEY `idx_vencimento` (`dia_vencimento`,`status_id`),
  ADD KEY `idx_cobranca` (`data_inicio_cobranca`,`periodo_teste`),
  ADD KEY `idx_proxima_vencimento` (`proxima_data_vencimento`,`status_id`),
  ADD KEY `fk_matriculas_plano_ciclo` (`plano_ciclo_id`);

--
-- Índices de tabela `metodos_pagamento`
--
ALTER TABLE `metodos_pagamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `modalidades`
--
ALTER TABLE `modalidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant_modalidade` (`tenant_id`,`nome`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `motivo_matricula`
--
ALTER TABLE `motivo_matricula`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_codigo` (`codigo`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `pagamentos_contrato`
--
ALTER TABLE `pagamentos_contrato`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contrato` (`tenant_plano_id`),
  ADD KEY `idx_status` (`status_pagamento_id`),
  ADD KEY `idx_vencimento` (`data_vencimento`),
  ADD KEY `idx_pagamento` (`data_pagamento`),
  ADD KEY `idx_forma_pagamento` (`forma_pagamento_id`),
  ADD KEY `idx_pagamentos_tenant_plano` (`tenant_plano_id`);

--
-- Índices de tabela `pagamentos_mercadopago`
--
ALTER TABLE `pagamentos_mercadopago`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_id` (`payment_id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_external_reference` (`external_reference`),
  ADD KEY `idx_matricula` (`matricula_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_date_created` (`date_created`);

--
-- Índices de tabela `pagamentos_plano`
--
ALTER TABLE `pagamentos_plano`
  ADD PRIMARY KEY (`id`),
  ADD KEY `forma_pagamento_id` (`forma_pagamento_id`),
  ADD KEY `criado_por` (`criado_por`),
  ADD KEY `baixado_por` (`baixado_por`),
  ADD KEY `idx_tenant_matricula` (`tenant_id`,`matricula_id`),
  ADD KEY `idx_tenant_usuario` (`tenant_id`),
  ADD KEY `idx_matricula` (`matricula_id`),
  ADD KEY `idx_plano` (`plano_id`),
  ADD KEY `idx_status` (`status_pagamento_id`),
  ADD KEY `idx_vencimento` (`data_vencimento`),
  ADD KEY `idx_pagamento` (`data_pagamento`),
  ADD KEY `idx_tenant_status` (`tenant_id`,`status_pagamento_id`),
  ADD KEY `idx_tenant_vencimento_status` (`tenant_id`,`data_vencimento`,`status_pagamento_id`),
  ADD KEY `idx_tipo_baixa` (`tipo_baixa_id`),
  ADD KEY `idx_pagamentos_aluno_id` (`aluno_id`);

--
-- Índices de tabela `papeis`
--
ALTER TABLE `papeis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`),
  ADD KEY `idx_nome` (`nome`),
  ADD KEY `idx_nivel` (`nivel`);

--
-- Índices de tabela `planos`
--
ALTER TABLE `planos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_planos` (`tenant_id`),
  ADD KEY `idx_ativo` (`ativo`),
  ADD KEY `idx_plano_modalidade` (`modalidade_id`);

--
-- Índices de tabela `planos_sistema`
--
ALTER TABLE `planos_sistema`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_disponiveis` (`atual`,`ativo`),
  ADD KEY `idx_ordem` (`ordem`);

--
-- Índices de tabela `plano_ciclos`
--
ALTER TABLE `plano_ciclos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_plano_frequencia_recorrencia` (`plano_id`,`assinatura_frequencia_id`,`permite_recorrencia`),
  ADD KEY `idx_plano_ciclos_tenant` (`tenant_id`),
  ADD KEY `idx_plano_ciclos_plano` (`plano_id`),
  ADD KEY `idx_plano_ciclos_ativo` (`ativo`),
  ADD KEY `idx_plano_ciclos_plano_ativo` (`plano_id`,`ativo`),
  ADD KEY `idx_plano_ciclos_frequencia` (`assinatura_frequencia_id`);

--
-- Índices de tabela `professores`
--
ALTER TABLE `professores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cpf` (`cpf`),
  ADD KEY `idx_professores_ativo` (`ativo`),
  ADD KEY `idx_professores_usuario_id` (`usuario_id`);

--
-- Índices de tabela `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_action` (`ip`,`action`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Índices de tabela `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `status_checkin`
--
ALTER TABLE `status_checkin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_codigo` (`codigo`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `status_conta`
--
ALTER TABLE `status_conta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_status_conta_nome` (`nome`);

--
-- Índices de tabela `status_contrato`
--
ALTER TABLE `status_contrato`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `status_matricula`
--
ALTER TABLE `status_matricula`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_codigo` (`codigo`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `status_pagamento`
--
ALTER TABLE `status_pagamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `status_usuario`
--
ALTER TABLE `status_usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_codigo` (`codigo`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `unique_tenant_nome` (`nome`),
  ADD UNIQUE KEY `unique_tenant_cnpj` (`cnpj`),
  ADD KEY `idx_tenants_cnpj` (`cnpj`);

--
-- Índices de tabela `tenant_formas_pagamento`
--
ALTER TABLE `tenant_formas_pagamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant_forma` (`tenant_id`,`forma_pagamento_id`),
  ADD KEY `forma_pagamento_id` (`forma_pagamento_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `tenant_payment_credentials`
--
ALTER TABLE `tenant_payment_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id` (`tenant_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_provider` (`provider`),
  ADD KEY `idx_active` (`is_active`);

--
-- Índices de tabela `tenant_planos_sistema`
--
ALTER TABLE `tenant_planos_sistema`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_status` (`tenant_id`),
  ADD KEY `idx_datas` (`data_inicio`),
  ADD KEY `idx_plano_sistema` (`plano_sistema_id`),
  ADD KEY `tenant_planos_sistema_plano_id_fk` (`plano_id`),
  ADD KEY `fk_tenant_planos_status` (`status_id`);

--
-- Índices de tabela `tenant_professor`
--
ALTER TABLE `tenant_professor`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_professor` (`tenant_id`,`professor_id`),
  ADD UNIQUE KEY `unique_tenant_email` (`tenant_id`,`email`),
  ADD KEY `idx_professor` (`professor_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_plano` (`plano_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `cpf` (`cpf`);

--
-- Índices de tabela `tenant_usuario_papel`
--
ALTER TABLE `tenant_usuario_papel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_usuario_papel` (`tenant_id`,`usuario_id`,`papel_id`),
  ADD KEY `idx_tenant_usuario` (`tenant_id`,`usuario_id`),
  ADD KEY `idx_usuario_papel` (`usuario_id`,`papel_id`),
  ADD KEY `idx_tenant_papel` (`tenant_id`,`papel_id`),
  ADD KEY `fk_tup_papel` (`papel_id`);

--
-- Índices de tabela `tipos_baixa`
--
ALTER TABLE `tipos_baixa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`),
  ADD KEY `idx_nome` (`nome`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `tipos_ciclo`
--
ALTER TABLE `tipos_ciclo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_tipos_ciclo_codigo` (`codigo`),
  ADD KEY `idx_tipos_ciclo_ativo` (`ativo`);

--
-- Índices de tabela `turmas`
--
ALTER TABLE `turmas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_turmas_tenant` (`tenant_id`),
  ADD KEY `idx_turmas_professor` (`professor_id`),
  ADD KEY `idx_turmas_modalidade` (`modalidade_id`),
  ADD KEY `idx_turmas_dia` (`dia_id`),
  ADD KEY `idx_turmas_ativo` (`ativo`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email_global` (`email_global`),
  ADD UNIQUE KEY `unique_cpf` (`cpf`),
  ADD KEY `role_id_bkp` (`role_id_bkp`),
  ADD KEY `idx_email_global` (`email_global`),
  ADD KEY `idx_usuarios_ativo` (`ativo`),
  ADD KEY `idx_usuarios_cpf` (`cpf`),
  ADD KEY `idx_usuarios_foto_caminho` (`foto_caminho`),
  ADD KEY `idx_reset_token` (`reset_token`),
  ADD KEY `idx_password_reset_token` (`password_reset_token`);

--
-- Índices de tabela `usuario_tenant_backup`
--
ALTER TABLE `usuario_tenant_backup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_usuario_tenant` (`usuario_id`,`tenant_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `plano_id` (`plano_id`);

--
-- Índices de tabela `wods`
--
ALTER TABLE `wods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tenant_data_modalidade` (`tenant_id`,`data`,`modalidade_id`),
  ADD KEY `idx_tenant_status_data` (`tenant_id`,`status`,`data`),
  ADD KEY `idx_data` (`data`),
  ADD KEY `fk_wods_criado_por` (`criado_por`),
  ADD KEY `idx_wods_modalidade` (`modalidade_id`);

--
-- Índices de tabela `wod_blocos`
--
ALTER TABLE `wod_blocos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_wod_blocos_wod_id` (`wod_id`);

--
-- Índices de tabela `wod_resultados`
--
ALTER TABLE `wod_resultados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_resultado` (`tenant_id`,`wod_id`,`usuario_id`);

--
-- Índices de tabela `wod_variacoes`
--
ALTER TABLE `wod_variacoes`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alunos`
--
ALTER TABLE `alunos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT de tabela `assinaturas`
--
ALTER TABLE `assinaturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `assinaturas_mercadopago`
--
ALTER TABLE `assinaturas_mercadopago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `assinatura_cancelamento_tipos`
--
ALTER TABLE `assinatura_cancelamento_tipos`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `assinatura_frequencias`
--
ALTER TABLE `assinatura_frequencias`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `assinatura_gateways`
--
ALTER TABLE `assinatura_gateways`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `assinatura_status`
--
ALTER TABLE `assinatura_status`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `checkins`
--
ALTER TABLE `checkins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de tabela `contas_receber`
--
ALTER TABLE `contas_receber`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `dias`
--
ALTER TABLE `dias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=366;

--
-- AUTO_INCREMENT de tabela `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT de tabela `formas_pagamento`
--
ALTER TABLE `formas_pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `historico_planos`
--
ALTER TABLE `historico_planos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `horarios`
--
ALTER TABLE `horarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `inscricoes_turmas`
--
ALTER TABLE `inscricoes_turmas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `matriculas`
--
ALTER TABLE `matriculas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT de tabela `metodos_pagamento`
--
ALTER TABLE `metodos_pagamento`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `modalidades`
--
ALTER TABLE `modalidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `motivo_matricula`
--
ALTER TABLE `motivo_matricula`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `pagamentos_contrato`
--
ALTER TABLE `pagamentos_contrato`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `pagamentos_mercadopago`
--
ALTER TABLE `pagamentos_mercadopago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `pagamentos_plano`
--
ALTER TABLE `pagamentos_plano`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de tabela `papeis`
--
ALTER TABLE `papeis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `planos`
--
ALTER TABLE `planos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `planos_sistema`
--
ALTER TABLE `planos_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `plano_ciclos`
--
ALTER TABLE `plano_ciclos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT de tabela `professores`
--
ALTER TABLE `professores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT de tabela `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `status_checkin`
--
ALTER TABLE `status_checkin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `status_conta`
--
ALTER TABLE `status_conta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `status_contrato`
--
ALTER TABLE `status_contrato`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `status_matricula`
--
ALTER TABLE `status_matricula`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `status_pagamento`
--
ALTER TABLE `status_pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `status_usuario`
--
ALTER TABLE `status_usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `tenant_formas_pagamento`
--
ALTER TABLE `tenant_formas_pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT de tabela `tenant_payment_credentials`
--
ALTER TABLE `tenant_payment_credentials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `tenant_planos_sistema`
--
ALTER TABLE `tenant_planos_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `tenant_professor`
--
ALTER TABLE `tenant_professor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `tenant_usuario_papel`
--
ALTER TABLE `tenant_usuario_papel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT de tabela `tipos_baixa`
--
ALTER TABLE `tipos_baixa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `tipos_ciclo`
--
ALTER TABLE `tipos_ciclo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `turmas`
--
ALTER TABLE `turmas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT de tabela `usuario_tenant_backup`
--
ALTER TABLE `usuario_tenant_backup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `wods`
--
ALTER TABLE `wods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `wod_blocos`
--
ALTER TABLE `wod_blocos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `wod_resultados`
--
ALTER TABLE `wod_resultados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `wod_variacoes`
--
ALTER TABLE `wod_variacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_assinaturas`
--
DROP TABLE IF EXISTS `vw_assinaturas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u304177849_api`@`127.0.0.1` SQL SECURITY DEFINER VIEW `vw_assinaturas`  AS SELECT `a`.`id` AS `id`, `a`.`tenant_id` AS `tenant_id`, `a`.`aluno_id` AS `aluno_id`, `a`.`matricula_id` AS `matricula_id`, `a`.`plano_id` AS `plano_id`, `g`.`codigo` AS `gateway`, `g`.`nome` AS `gateway_nome`, `a`.`gateway_assinatura_id` AS `gateway_assinatura_id`, `s`.`codigo` AS `status`, `s`.`nome` AS `status_nome`, `s`.`cor` AS `status_cor`, `a`.`valor` AS `valor`, `a`.`moeda` AS `moeda`, `f`.`codigo` AS `frequencia`, `f`.`nome` AS `frequencia_nome`, `f`.`dias` AS `frequencia_dias`, `a`.`data_inicio` AS `data_inicio`, `a`.`data_fim` AS `data_fim`, `a`.`proxima_cobranca` AS `proxima_cobranca`, `a`.`ultima_cobranca` AS `ultima_cobranca`, `mp`.`codigo` AS `metodo_pagamento`, `mp`.`nome` AS `metodo_pagamento_nome`, `a`.`cartao_ultimos_digitos` AS `cartao_ultimos_digitos`, `a`.`cartao_bandeira` AS `cartao_bandeira`, `ct`.`codigo` AS `cancelado_por`, `a`.`motivo_cancelamento` AS `motivo_cancelamento`, `a`.`criado_em` AS `criado_em`, `a`.`atualizado_em` AS `atualizado_em` FROM (((((`assinaturas` `a` join `assinatura_gateways` `g` on(`a`.`gateway_id` = `g`.`id`)) join `assinatura_status` `s` on(`a`.`status_id` = `s`.`id`)) join `assinatura_frequencias` `f` on(`a`.`frequencia_id` = `f`.`id`)) left join `metodos_pagamento` `mp` on(`a`.`metodo_pagamento_id` = `mp`.`id`)) left join `assinatura_cancelamento_tipos` `ct` on(`a`.`cancelado_por_id` = `ct`.`id`)) ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_email_stats_daily`
--
DROP TABLE IF EXISTS `vw_email_stats_daily`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u304177849_api`@`127.0.0.1` SQL SECURITY DEFINER VIEW `vw_email_stats_daily`  AS SELECT cast(`email_logs`.`created_at` as date) AS `data`, `email_logs`.`tenant_id` AS `tenant_id`, `email_logs`.`email_type` AS `email_type`, `email_logs`.`status` AS `status`, count(0) AS `total` FROM `email_logs` GROUP BY cast(`email_logs`.`created_at` as date), `email_logs`.`tenant_id`, `email_logs`.`email_type`, `email_logs`.`status` ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_email_success_rate`
--
DROP TABLE IF EXISTS `vw_email_success_rate`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u304177849_api`@`127.0.0.1` SQL SECURITY DEFINER VIEW `vw_email_success_rate`  AS SELECT `email_logs`.`tenant_id` AS `tenant_id`, count(0) AS `total_emails`, sum(case when `email_logs`.`status` = 'sent' then 1 else 0 end) AS `enviados`, sum(case when `email_logs`.`status` = 'failed' then 1 else 0 end) AS `falhas`, round(sum(case when `email_logs`.`status` = 'sent' then 1 else 0 end) * 100.0 / count(0),2) AS `taxa_sucesso` FROM `email_logs` GROUP BY `email_logs`.`tenant_id` ;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `alunos`
--
ALTER TABLE `alunos`
  ADD CONSTRAINT `fk_aluno_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `assinaturas`
--
ALTER TABLE `assinaturas`
  ADD CONSTRAINT `assinaturas_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assinaturas_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assinaturas_ibfk_3` FOREIGN KEY (`matricula_id`) REFERENCES `matriculas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `assinaturas_ibfk_4` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `assinaturas_ibfk_5` FOREIGN KEY (`gateway_id`) REFERENCES `assinatura_gateways` (`id`),
  ADD CONSTRAINT `assinaturas_ibfk_6` FOREIGN KEY (`status_id`) REFERENCES `assinatura_status` (`id`),
  ADD CONSTRAINT `assinaturas_ibfk_7` FOREIGN KEY (`frequencia_id`) REFERENCES `assinatura_frequencias` (`id`),
  ADD CONSTRAINT `assinaturas_ibfk_8` FOREIGN KEY (`metodo_pagamento_id`) REFERENCES `metodos_pagamento` (`id`),
  ADD CONSTRAINT `assinaturas_ibfk_9` FOREIGN KEY (`cancelado_por_id`) REFERENCES `assinatura_cancelamento_tipos` (`id`);

--
-- Restrições para tabelas `assinaturas_mercadopago`
--
ALTER TABLE `assinaturas_mercadopago`
  ADD CONSTRAINT `assinaturas_mercadopago_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assinaturas_mercadopago_ibfk_2` FOREIGN KEY (`matricula_id`) REFERENCES `matriculas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assinaturas_mercadopago_ibfk_3` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assinaturas_mercadopago_ibfk_4` FOREIGN KEY (`plano_ciclo_id`) REFERENCES `plano_ciclos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `assinaturas_mercadopago_ibfk_5` FOREIGN KEY (`cancelado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `checkins`
--
ALTER TABLE `checkins`
  ADD CONSTRAINT `checkins_ibfk_2` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_checkin_admin` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_checkins_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_checkins_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `contas_receber`
--
ALTER TABLE `contas_receber`
  ADD CONSTRAINT `contas_receber_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contas_receber_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contas_receber_ibfk_3` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`),
  ADD CONSTRAINT `contas_receber_ibfk_4` FOREIGN KEY (`historico_plano_id`) REFERENCES `historico_planos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contas_receber_ibfk_5` FOREIGN KEY (`proxima_conta_id`) REFERENCES `contas_receber` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contas_receber_ibfk_6` FOREIGN KEY (`conta_origem_id`) REFERENCES `contas_receber` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contas_receber_ibfk_7` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contas_receber_ibfk_8` FOREIGN KEY (`baixa_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contas_receber_ibfk_9` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `fk_email_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_email_logs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `historico_planos`
--
ALTER TABLE `historico_planos`
  ADD CONSTRAINT `historico_planos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `historico_planos_ibfk_2` FOREIGN KEY (`plano_anterior_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `historico_planos_ibfk_3` FOREIGN KEY (`plano_novo_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `historico_planos_ibfk_4` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `horarios_ibfk_1` FOREIGN KEY (`dia_id`) REFERENCES `dias` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `inscricoes_turmas`
--
ALTER TABLE `inscricoes_turmas`
  ADD CONSTRAINT `fk_inscricoes_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inscricoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `matriculas`
--
ALTER TABLE `matriculas`
  ADD CONSTRAINT `fk_matriculas_motivo` FOREIGN KEY (`motivo_id`) REFERENCES `motivo_matricula` (`id`),
  ADD CONSTRAINT `fk_matriculas_plano_ciclo` FOREIGN KEY (`plano_ciclo_id`) REFERENCES `plano_ciclos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_matriculas_status` FOREIGN KEY (`status_id`) REFERENCES `status_matricula` (`id`),
  ADD CONSTRAINT `matriculas_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `matriculas_ibfk_3` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`),
  ADD CONSTRAINT `matriculas_ibfk_4` FOREIGN KEY (`matricula_anterior_id`) REFERENCES `matriculas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `matriculas_ibfk_5` FOREIGN KEY (`plano_anterior_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `matriculas_ibfk_6` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `matriculas_ibfk_7` FOREIGN KEY (`cancelado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `modalidades`
--
ALTER TABLE `modalidades`
  ADD CONSTRAINT `modalidades_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pagamentos_contrato`
--
ALTER TABLE `pagamentos_contrato`
  ADD CONSTRAINT `fk_pagamento_forma_pagamento` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`),
  ADD CONSTRAINT `fk_pagamentos_tenant_plano` FOREIGN KEY (`tenant_plano_id`) REFERENCES `tenant_planos_sistema` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagamentos_contrato_ibfk_2` FOREIGN KEY (`status_pagamento_id`) REFERENCES `status_pagamento` (`id`);

--
-- Restrições para tabelas `pagamentos_plano`
--
ALTER TABLE `pagamentos_plano`
  ADD CONSTRAINT `pagamentos_plano_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagamentos_plano_ibfk_2` FOREIGN KEY (`matricula_id`) REFERENCES `matriculas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagamentos_plano_ibfk_4` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`),
  ADD CONSTRAINT `pagamentos_plano_ibfk_5` FOREIGN KEY (`status_pagamento_id`) REFERENCES `status_pagamento` (`id`),
  ADD CONSTRAINT `pagamentos_plano_ibfk_6` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`),
  ADD CONSTRAINT `pagamentos_plano_ibfk_7` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pagamentos_plano_ibfk_8` FOREIGN KEY (`baixado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pagamentos_plano_ibfk_9` FOREIGN KEY (`tipo_baixa_id`) REFERENCES `tipos_baixa` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `planos`
--
ALTER TABLE `planos`
  ADD CONSTRAINT `fk_plano_modalidade` FOREIGN KEY (`modalidade_id`) REFERENCES `modalidades` (`id`),
  ADD CONSTRAINT `planos_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `plano_ciclos`
--
ALTER TABLE `plano_ciclos`
  ADD CONSTRAINT `fk_plano_ciclos_assinatura_frequencia` FOREIGN KEY (`assinatura_frequencia_id`) REFERENCES `assinatura_frequencias` (`id`),
  ADD CONSTRAINT `plano_ciclos_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plano_ciclos_ibfk_2` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tenant_formas_pagamento`
--
ALTER TABLE `tenant_formas_pagamento`
  ADD CONSTRAINT `tenant_formas_pagamento_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tenant_formas_pagamento_ibfk_2` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tenant_planos_sistema`
--
ALTER TABLE `tenant_planos_sistema`
  ADD CONSTRAINT `tenant_planos_sistema_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tenant_planos_sistema_ibfk_3` FOREIGN KEY (`plano_sistema_id`) REFERENCES `planos_sistema` (`id`),
  ADD CONSTRAINT `tenant_planos_sistema_plano_id_fk` FOREIGN KEY (`plano_id`) REFERENCES `planos_sistema` (`id`);

--
-- Restrições para tabelas `tenant_professor`
--
ALTER TABLE `tenant_professor`
  ADD CONSTRAINT `fk_tenant_professor_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tenant_professor_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tenant_professor_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tenant_usuario_papel`
--
ALTER TABLE `tenant_usuario_papel`
  ADD CONSTRAINT `fk_tup_papel` FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`),
  ADD CONSTRAINT `fk_tup_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tup_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `turmas`
--
ALTER TABLE `turmas`
  ADD CONSTRAINT `turmas_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `turmas_ibfk_2` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `turmas_ibfk_3` FOREIGN KEY (`modalidade_id`) REFERENCES `modalidades` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `turmas_ibfk_4` FOREIGN KEY (`dia_id`) REFERENCES `dias` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`role_id_bkp`) REFERENCES `roles` (`id`);

--
-- Restrições para tabelas `usuario_tenant_backup`
--
ALTER TABLE `usuario_tenant_backup`
  ADD CONSTRAINT `usuario_tenant_backup_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_tenant_backup_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_tenant_backup_ibfk_3` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `wods`
--
ALTER TABLE `wods`
  ADD CONSTRAINT `fk_wods_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_wods_modalidade` FOREIGN KEY (`modalidade_id`) REFERENCES `modalidades` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_wods_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `wod_blocos`
--
ALTER TABLE `wod_blocos`
  ADD CONSTRAINT `fk_wod_blocos_wod_id` FOREIGN KEY (`wod_id`) REFERENCES `wods` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `wod_resultados`
--
ALTER TABLE `wod_resultados`
  ADD CONSTRAINT `fk_res_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`u304177849_api`@`127.0.0.1` EVENT `atualizar_matriculas_vencidas` ON SCHEDULE EVERY 1 DAY STARTS '2026-02-07 00:01:00' ON COMPLETION NOT PRESERVE ENABLE COMMENT 'Atualiza status de matrículas para vencida quando proxima_data_v' DO BEGIN
    UPDATE matriculas
    SET status_id = 2,
        updated_at = NOW()
    WHERE status_id = 1
    AND proxima_data_vencimento IS NOT NULL
    AND proxima_data_vencimento < CURDATE();
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
