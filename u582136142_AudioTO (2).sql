-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 31/05/2025 às 02:13
-- Versão do servidor: 10.11.10-MariaDB
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u582136142_AudioTO`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `assinaturas_utilizador`
--

CREATE TABLE `assinaturas_utilizador` (
  `id_assinatura` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `id_plano` int(11) NOT NULL,
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime DEFAULT NULL,
  `data_proxima_cobranca` datetime DEFAULT NULL,
  `estado_assinatura` enum('ativa','cancelada','expirada','pendente_pagamento','gratuita_teste') NOT NULL DEFAULT 'pendente_pagamento',
  `id_transacao_gateway` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `assinaturas_utilizador`
--

INSERT INTO `assinaturas_utilizador` (`id_assinatura`, `id_utilizador`, `id_plano`, `data_inicio`, `data_fim`, `data_proxima_cobranca`, `estado_assinatura`, `id_transacao_gateway`, `data_criacao`) VALUES
(35, 1, 2, '2025-05-25 04:36:01', NULL, NULL, 'pendente_pagamento', 'f5dda94c51cb414fa604c002b06f559c', '2025-05-25 04:36:01'),
(36, 26, 2, '2025-05-25 05:24:08', '2025-06-25 05:24:08', '2025-06-25 05:24:08', 'ativa', 'cf5177fb381a40bab3c2a9cd3eab0b81', '2025-05-25 04:57:18'),
(37, 26, 2, '2025-05-28 02:32:16', NULL, NULL, 'pendente_pagamento', '629b6901a43143b79be7c64a546273da', '2025-05-28 02:32:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `assuntos_podcast`
--

CREATE TABLE `assuntos_podcast` (
  `id_assunto` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `nome_assunto` varchar(200) NOT NULL,
  `descricao_assunto` text DEFAULT NULL,
  `icone_assunto` varchar(255) DEFAULT NULL,
  `cor_icone_assunto` varchar(20) DEFAULT NULL,
  `slug_assunto` varchar(200) NOT NULL,
  `url_audio` varchar(255) DEFAULT NULL,
  `url_pdf` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `assuntos_podcast`
--

INSERT INTO `assuntos_podcast` (`id_assunto`, `id_categoria`, `nome_assunto`, `descricao_assunto`, `icone_assunto`, `cor_icone_assunto`, `slug_assunto`, `url_audio`, `url_pdf`, `data_criacao`) VALUES
(5, 6, 'Exercícios para Reabilitação Pós-AVC', 'Técnicas para reabilitação física após Acidente Vascular Cerebral.', NULL, NULL, 'exercicios-reabilitacao-pos-avc', NULL, NULL, '2025-05-20 00:55:48'),
(6, 7, 'Prevenção de Doenças Crônicas', 'Educação em saúde para prevenção de diabetes e hipertensão.', NULL, NULL, 'prevencao-doencas-cronicas', NULL, NULL, '2025-05-20 00:55:48'),
(8, 9, 'Técnicas de Terapia Ocupacional para Idosos', 'Abordagens para promover autonomia em idosos.', NULL, NULL, 'tecnicas-to-idosos', NULL, NULL, '2025-05-20 00:55:48'),
(9, 9, 'A Utilização de Tecnologias Assistivas', 'Recursos tecnológicos para reabilitação.', NULL, NULL, 'tecnologias-assistivas', NULL, NULL, '2025-05-20 00:55:48'),
(10, 7, 'Higiene e Saúde Bucal', 'Educação sobre cuidados com a saúde bucal.', NULL, NULL, 'higiene-saude-bucal', NULL, NULL, '2025-05-20 00:55:48'),
(11, 6, 'Fisioterapia Respiratória', 'Exercícios e cuidados para saúde respiratória.', NULL, NULL, 'fisioterapia-respiratoria', NULL, NULL, '2025-05-20 00:55:48'),
(13, 9, 'Ergonomia no Trabalho', 'Técnicas para prevenção de lesões ocupacionais.', NULL, NULL, 'ergonomia-trabalho', NULL, NULL, '2025-05-20 00:55:48'),
(14, 7, 'Alimentação Saudável', 'Dicas para uma alimentação balanceada.', NULL, NULL, 'alimentacao-saudavel', NULL, NULL, '2025-05-20 00:55:48'),
(301, 6, 'Fortalecimento Muscular', 'Técnicas para ganho de força e mobilidade.', NULL, NULL, 'fortalecimento-muscular', NULL, NULL, '2025-05-20 02:37:34'),
(302, 6, 'Alongamentos e Flexibilidade', 'Importância do alongamento na reabilitação.', NULL, NULL, 'alongamentos-flexibilidade', NULL, NULL, '2025-05-20 02:37:34'),
(303, 7, 'Vacinação para Adultos', 'Importância das vacinas ao longo da vida.', NULL, NULL, 'vacinacao-adultos', NULL, NULL, '2025-05-20 02:37:34'),
(304, 7, 'Saúde Mental na Escola', 'Como identificar sinais de sofrimento mental em estudantes.', NULL, NULL, 'saude-mental-escola', NULL, NULL, '2025-05-20 02:37:34'),
(305, 9, 'Brinquedoteca Terapêutica', 'Uso do brincar no contexto terapêutico.', NULL, NULL, 'brinquedoteca-terapeutica', NULL, NULL, '2025-05-20 02:37:34'),
(306, 9, 'Adaptação de Atividades Cotidianas', 'Facilitadores para independência no dia a dia.', NULL, NULL, 'adaptacao-atividades-cotidianas', NULL, NULL, '2025-05-20 02:37:34'),
(307, 10, 'Introdução à Psicoterapia', 'Conceitos básicos e tipos de psicoterapia.', NULL, NULL, 'introducao-psicoterapia', NULL, NULL, '2025-05-20 02:37:34'),
(308, 10, 'Transtornos de Ansiedade', 'Diagnóstico e tratamento dos principais transtornos.', NULL, NULL, 'transtornos-ansiedade', NULL, NULL, '2025-05-20 02:37:34'),
(309, 10, 'Psicologia na Infância', 'Especificidades do atendimento clínico infantil.', NULL, NULL, 'psicologia-infancia', NULL, NULL, '2025-05-20 02:37:34'),
(310, 10, 'Terapia Cognitivo-Comportamental', 'Bases e aplicações da TCC.', NULL, NULL, 'tcc-psicologia-clinica', NULL, NULL, '2025-05-20 02:37:34'),
(311, 10, 'Ética na Prática Clínica', 'Princípios éticos fundamentais para psicólogos clínicos.', NULL, NULL, 'etica-psicologia-clinica', NULL, NULL, '2025-05-20 02:37:34'),
(312, 11, 'Bases da Neurociência', 'Fundamentos da neurociência para profissionais de saúde.', NULL, NULL, 'bases-neurociencia', NULL, NULL, '2025-05-20 02:37:34'),
(313, 11, 'Plasticidade Cerebral', 'Capacidade de adaptação do cérebro humano.', NULL, NULL, 'plasticidade-cerebral', NULL, NULL, '2025-05-20 02:37:34'),
(314, 11, 'Memória e Aprendizagem', 'Como nosso cérebro aprende e armazena informações.', NULL, NULL, 'memoria-aprendizagem', NULL, NULL, '2025-05-20 02:37:34'),
(315, 11, 'Neurotransmissores e Emoções', 'O papel dos neurotransmissores nas emoções.', NULL, NULL, 'neurotransmissores-emocoes', NULL, NULL, '2025-05-20 02:37:34'),
(316, 11, 'Transtornos Neurodegenerativos', 'Estudos sobre Alzheimer, Parkinson e outros.', NULL, NULL, 'transtornos-neurodegenerativos', NULL, NULL, '2025-05-20 02:37:34'),
(317, 12, 'Sinais Precoce do TEA', 'Como identificar o autismo na infância.', NULL, NULL, 'sinais-precoces-tea', NULL, NULL, '2025-05-20 02:37:34'),
(318, 12, 'Intervenção ABA', 'O que é e como funciona a intervenção ABA.', NULL, NULL, 'intervencao-aba', NULL, NULL, '2025-05-20 02:37:34'),
(319, 12, 'Inclusão Escolar', 'Desafios e estratégias para inclusão escolar.', NULL, NULL, 'inclusao-escolar-tea', NULL, NULL, '2025-05-20 02:37:34'),
(320, 12, 'Comunicação Alternativa', 'Recursos e estratégias para comunicação com autistas.', NULL, NULL, 'comunicacao-alternativa-tea', NULL, NULL, '2025-05-20 02:37:34'),
(321, 12, 'Família e Rede de Apoio', 'Como apoiar famílias de pessoas com TEA.', NULL, NULL, 'familia-apoio-tea', NULL, NULL, '2025-05-20 02:37:34'),
(322, 13, 'Transtorno de Ansiedade Infantil', 'Diagnóstico e manejo do transtorno de ansiedade em crianças.', NULL, NULL, 'transtorno-ansiedade-infantil', NULL, NULL, '2025-05-20 02:37:34'),
(323, 13, 'Bullying Escolar', 'Consequências psicológicas e estratégias de prevenção.', NULL, NULL, 'bullying-escolar', NULL, NULL, '2025-05-20 02:37:34'),
(324, 13, 'TDAH', 'Entendendo o Transtorno de Déficit de Atenção e Hiperatividade.', NULL, NULL, 'tdah-infantil', NULL, NULL, '2025-05-20 02:37:34'),
(325, 13, 'Importância do Brincar', 'Brincadeira como ferramenta terapêutica.', NULL, NULL, 'importancia-brincar', NULL, NULL, '2025-05-20 02:37:34'),
(326, 13, 'Depressão na Infância', 'Como identificar e tratar casos de depressão infantil.', NULL, NULL, 'depressao-infancia', NULL, NULL, '2025-05-20 02:37:34'),
(327, 14, 'Envelhecimento Ativo', 'Estratégias para promover qualidade de vida na terceira idade.', NULL, NULL, 'envelhecimento-ativo', NULL, NULL, '2025-05-20 02:37:34'),
(328, 14, 'Prevenção de Quedas', 'Como reduzir o risco de quedas em idosos.', NULL, NULL, 'prevencao-quedas-geriatria', NULL, NULL, '2025-05-20 02:37:34'),
(329, 14, 'Doença de Alzheimer', 'Cuidados e acompanhamento de pacientes com Alzheimer.', NULL, NULL, 'alzheimer-cuidados', NULL, NULL, '2025-05-20 02:37:34'),
(330, 14, 'Terapia Ocupacional na Geriatria', 'Benefícios das intervenções em idosos.', NULL, NULL, 'to-geriatria', NULL, NULL, '2025-05-20 02:37:34'),
(331, 14, 'Nutrição do Idoso', 'Cuidados alimentares essenciais para a terceira idade.', NULL, NULL, 'nutricao-idoso', NULL, NULL, '2025-05-20 02:37:34'),
(372, 15, 'Atraso no Desenvolvimento da Fala', 'Como identificar e tratar atrasos na fala.', NULL, NULL, 'atraso-desenvolvimento-fala', NULL, NULL, '2025-05-20 02:39:15'),
(373, 15, 'Distúrbios de Deglutição', 'Diagnóstico e reabilitação dos distúrbios.', NULL, NULL, 'disturbios-degluticao', NULL, NULL, '2025-05-20 02:39:15'),
(374, 15, 'Fonoterapia em Adultos', 'Tratamentos fonoaudiológicos para adultos.', NULL, NULL, 'fonoterapia-adultos', NULL, NULL, '2025-05-20 02:39:15'),
(375, 15, 'Voz Profissional', 'Cuidados com a voz em professores e cantores.', NULL, NULL, 'voz-profissional', NULL, NULL, '2025-05-20 02:39:15'),
(376, 15, 'Comunicação Alternativa', 'Recursos para pacientes com dificuldade de comunicação oral.', NULL, NULL, 'comunicacao-alternativa-fono', NULL, NULL, '2025-05-20 02:39:15'),
(394, 15, 'Atraso no Desenvolvimento da Fala', 'Como identificar e tratar atrasos na fala.', NULL, NULL, 'fonoaudiologia-atraso-desenvolvimento-fala', NULL, NULL, '2025-05-20 02:48:53'),
(395, 15, 'Distúrbios de Deglutição', 'Diagnóstico e reabilitação dos distúrbios.', NULL, NULL, 'fonoaudiologia-disturbios-degluticao', NULL, NULL, '2025-05-20 02:48:53'),
(396, 15, 'Fonoterapia em Adultos', 'Tratamentos fonoaudiológicos para adultos.', NULL, NULL, 'fonoaudiologia-fonoterapia-adultos', NULL, NULL, '2025-05-20 02:48:53'),
(397, 15, 'Voz Profissional', 'Cuidados com a voz em professores e cantores.', NULL, NULL, 'fonoaudiologia-voz-profissional', NULL, NULL, '2025-05-20 02:48:53'),
(398, 15, 'Comunicação Alternativa', 'Recursos para pacientes com dificuldade de comunicação oral.', NULL, NULL, 'fonoaudiologia-comunicacao-alternativa', NULL, NULL, '2025-05-20 02:48:53'),
(408, 26, 'Ergonomia no Ambiente de Trabalho', 'Princípios para melhorar o conforto e segurança.', NULL, NULL, 'saude-trabalhador-ergonomia', NULL, NULL, '2025-05-20 02:49:53'),
(409, 26, 'Prevenção de Lesões por Esforço Repetitivo', 'Técnicas para evitar lesões ocupacionais.', NULL, NULL, 'saude-trabalhador-prevencao-ler', NULL, NULL, '2025-05-20 02:49:53'),
(410, 26, 'Saúde Mental no Trabalho', 'Gerenciamento do estresse e saúde emocional.', NULL, NULL, 'saude-trabalhador-mental', NULL, NULL, '2025-05-20 02:49:53'),
(411, 26, 'Legislação Trabalhista em Saúde', 'Normas para proteção do trabalhador.', NULL, NULL, 'saude-trabalhador-legislacao', NULL, NULL, '2025-05-20 02:49:53'),
(412, 26, 'Promoção da Qualidade de Vida no Trabalho', 'Práticas para melhorar o bem-estar laboral.', NULL, NULL, 'saude-trabalhador-qualidade-vida', NULL, NULL, '2025-05-20 02:49:53'),
(434, 53, 'Assunto 1: Avaliação na Terapia Ocupacional Infantil e Juvenil', 'Evolução do uso de instrumentos próprios da TO e a importância da sistematização.', NULL, NULL, 'avaliacao-na-terapia-ocupacional-infantil-e-juvenil', '', NULL, '2025-05-21 04:01:09'),
(435, 53, 'Assunto 2: Avaliação Funcional do Comportamento', 'Uma explicação didática sobre o que é a avaliação funcional dentro da Análise do Comportamento.', NULL, NULL, 'assunto-2-avaliacao-funcional-do-comportamento', '', NULL, '2025-05-21 04:59:42'),
(436, 49, 'Papel do TO na Neonatologia', '', NULL, NULL, 'papel-do-to-na-neonatologia', '', NULL, '2025-05-28 04:06:19');

-- --------------------------------------------------------

--
-- Estrutura para tabela `audioto_emails`
--

CREATE TABLE `audioto_emails` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `audioto_emails`
--

INSERT INTO `audioto_emails` (`id`, `email`, `created_at`) VALUES
(1, 'allexandrinoinc@gmail.com', '2025-05-14 23:21:05'),
(3, 'dossantossilvadaniela3@gmail.com', '2025-05-19 15:15:14'),
(4, 'jessica.afonso.greve87@gmail.com', '2025-05-19 15:25:40'),
(5, 'rafaela95@outlook.com.br', '2025-05-19 15:36:43'),
(6, 'daniel-amaro08@hotmail.com', '2025-05-19 15:40:04'),
(7, 'nga250585@gmail.com', '2025-05-19 15:45:12'),
(8, 'marilourdesvieira@yahoo.com', '2025-05-19 16:31:41'),
(9, 'daguisouza1984@gmail.com', '2025-05-19 16:34:04'),
(10, 'rosetomazdecastro@gmail.com', '2025-05-19 17:24:10'),
(11, 'jacque.profa@gmail.com', '2025-05-19 18:43:08'),
(12, 'simonehartt@yahoo.com.br', '2025-05-19 20:36:28'),
(13, 'profeliese.smo@gmail.com', '2025-05-19 20:41:40'),
(14, 'fsadria@yahoo.com.br', '2025-05-19 20:45:43'),
(15, 'ana_angra@hotmail.com', '2025-05-19 20:51:09'),
(16, 'rosangela.tessalia@gmail.com', '2025-05-19 21:16:27'),
(17, 'fac.saulo@gmail.com', '2025-05-19 21:26:27'),
(18, 'marciaveiga385@gmail.com', '2025-05-19 22:13:39'),
(19, 'julianatriquez@gmail.com', '2025-05-19 23:28:17'),
(20, 'ernandorena.sheila@gmail.com', '2025-05-19 23:38:51'),
(21, 'reabilita.to.yris@gmail.com', '2025-05-20 15:46:27'),
(22, 'genirasouza19@gmail.com', '2025-05-20 15:54:50'),
(23, 'Rafaellarodrigues749@gmail.com', '2025-05-21 07:29:50'),
(24, 'suzanajk20@gmail.com', '2025-05-21 08:37:36'),
(25, 'ddeboracristina76@gmail.com', '2025-05-21 08:39:37'),
(26, 'iris.lahu@gmail.com', '2025-05-21 09:17:34'),
(27, 'leuyasmin46@gmail.com', '2025-05-21 09:19:49'),
(28, 'keniacardonski@hotmail.com', '2025-05-21 09:20:01'),
(29, 'alveskallyne46@gmail.com', '2025-05-21 09:39:33'),
(30, 'habilita.to.yris@gmail.com', '2025-05-21 09:42:49'),
(31, 'terapeutiando.lc@gmail.com', '2025-05-21 09:49:07'),
(32, 'naireslima1@gmail.com', '2025-05-21 09:56:51'),
(33, 'naireslimaandrade@gmail.com', '2025-05-21 09:57:04'),
(34, 'jaqueline@netscs.com.br', '2025-05-21 09:58:38'),
(35, 'evecj.souza@gmail.com', '2025-05-21 10:25:17'),
(36, 'jamissonr1405@gmail.com', '2025-05-21 11:00:48'),
(37, 'elisaanjosilva@gmail.com', '2025-05-21 11:02:42'),
(38, 'adenisalimoeiro@gmail.com', '2025-05-21 11:03:35'),
(39, 'fa.f.medeiros@hotmail.com', '2025-05-21 14:12:11'),
(40, 'marianadfa2015@gmail.com', '2025-05-21 18:59:24'),
(41, 'valdenia.f.azevedo@gmail.com', '2025-05-21 20:37:09'),
(42, 'tfachiolli@hotmail.com', '2025-05-21 20:37:42'),
(43, 'francineliayres@gmail.com', '2025-05-21 20:38:24'),
(44, 'karoline.jacques85@yahoo.com.br', '2025-05-21 20:38:40'),
(45, 'edneia.terapeutaocupacional@gmail.com', '2025-05-21 20:39:07'),
(46, 'jjowientais@gmail.com', '2025-05-21 20:42:59'),
(47, 'contato@vivairis.com', '2025-05-21 20:46:16'),
(48, 'aurianesv@hotmail.com', '2025-05-21 20:50:43'),
(49, 'thainmirandaaa@gmail.com', '2025-05-21 21:02:44'),
(50, 'denise_ico@hotmail.com', '2025-05-21 21:09:27'),
(51, 'jeannetorres9@outlook.com', '2025-05-21 21:14:03'),
(52, 'joeloliveira686@gmail.com', '2025-05-21 21:35:32'),
(53, 'angelica.souza93@gmail.com', '2025-05-21 21:40:19'),
(54, 'annamafarapereira@gmail.com', '2025-05-21 21:56:19'),
(55, 'giselemoura030811@gmail.com', '2025-05-21 22:02:39'),
(56, 'gresiribeiromotta@gmail.com', '2025-05-21 22:23:03'),
(57, 'tatiteixeira007@gmail.com', '2025-05-22 23:26:19'),
(58, 'sara77929@gmail.com', '2025-05-23 01:26:57'),
(59, 'monisefcrodrigues@outlook.com', '2025-05-23 10:25:50'),
(60, 'emilysantiagodasilva6@gmail.com', '2025-05-23 14:31:05'),
(61, 'annamafara04@gmail.com', '2025-05-23 16:42:57'),
(62, 'adenisaas2025@gamil.com', '2025-05-30 23:28:48');

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacoes_podcast`
--

CREATE TABLE `avaliacoes_podcast` (
  `id_avaliacao` int(11) NOT NULL,
  `id_podcast` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `nota` tinyint(4) NOT NULL CHECK (`nota` >= 1 and `nota` <= 5),
  `data_avaliacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `avaliacoes_podcast`
--

INSERT INTO `avaliacoes_podcast` (`id_avaliacao`, `id_podcast`, `id_utilizador`, `nota`, `data_avaliacao`) VALUES
(1, 19, 1, 5, '2025-05-20 03:16:36'),
(19, 23, 1, 3, '2025-05-25 01:56:24'),
(22, 27, 1, 5, '2025-05-24 08:38:17'),
(27, 24, 1, 2, '2025-05-25 02:12:54'),
(31, 19, 26, 5, '2025-05-25 04:56:21'),
(33, 15, 26, 5, '2025-05-26 02:57:04'),
(35, 27, 26, 5, '2025-05-28 02:31:46'),
(47, 26, 26, 4, '2025-05-26 04:40:12'),
(48, 21, 26, 5, '2025-05-26 05:34:56'),
(49, 20, 26, 5, '2025-05-26 05:08:13');

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias_podcast`
--

CREATE TABLE `categorias_podcast` (
  `id_categoria` int(11) NOT NULL,
  `nome_categoria` varchar(150) NOT NULL,
  `descricao_categoria` text DEFAULT NULL,
  `slug_categoria` varchar(150) NOT NULL,
  `icone_categoria` varchar(255) DEFAULT NULL,
  `cor_icone` varchar(7) DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `categorias_podcast`
--

INSERT INTO `categorias_podcast` (`id_categoria`, `nome_categoria`, `descricao_categoria`, `slug_categoria`, `icone_categoria`, `cor_icone`, `data_criacao`) VALUES
(6, 'Reabilitação Física', 'Conteúdo sobre técnicas e tratamentos para reabilitação física.', 'reabilitacao-fisica', 'fa-solid fa-dumbbell', '#009933', '2025-05-20 00:55:48'),
(7, 'Educação em Saúde', 'Podcasts focados em educação e prevenção em saúde.', 'educacao-em-saude', 'fas fa-chalkboard-teacher', '#ff9900', '2025-05-20 00:55:48'),
(9, 'Terapia Ocupacional', 'Especializado em práticas e técnicas de Terapia Ocupacional.', 'terapia-ocupacional', 'fa-solid fa-hands-helping', '#993399', '2025-05-20 00:55:48'),
(10, 'Psicologia Clínica', 'Conteúdos relacionados à prática clínica em psicologia.', 'psicologia-clinica', 'fa-solid fa-brain', '#6f42c1', '2025-05-20 02:25:25'),
(11, 'Neurociência', 'Estudos e avanços em neurociência aplicada.', 'neurociencia', 'fa-solid fa-brain', '#dc3545', '2025-05-20 02:25:25'),
(12, 'Autismo e TEA', 'Informações sobre Transtorno do Espectro Autista.', 'autismo-tea', 'fas fa-puzzle-piece', '#20c997', '2025-05-20 02:25:25'),
(13, 'Saúde Mental Infantil', 'Saúde mental voltada para crianças.', 'saude-mental-infantil', 'fa-solid fa-child', '#6610f2', '2025-05-20 02:25:25'),
(14, 'Geriatria e Gerontologia', 'Cuidados e terapias para idosos.', 'geriatria-gerontologia', 'fa-solid fa-user-clock', '#007bff', '2025-05-20 02:25:25'),
(15, 'Fonoaudiologia', 'Abordagens e terapias fonoaudiológicas.', 'fonoaudiologia', 'fas fa-microphone', '#28a745', '2025-05-20 02:25:25'),
(16, 'Psicopedagogia', 'Conteúdo voltado para dificuldades de aprendizagem.', 'psicopedagogia', 'fa-solid fa-book-reader', '#ffc107', '2025-05-20 02:25:25'),
(17, 'Educação Especial', 'Estratégias para educação inclusiva.', 'educacao-especial', 'fas fa-universal-access', '#e83e8c', '2025-05-20 02:25:25'),
(18, 'Saúde Pública', 'Políticas e práticas em saúde coletiva.', 'saude-publica', 'fa-solid fa-hospital', '#20c997', '2025-05-20 02:25:25'),
(19, 'Nutrição e Dietética', 'Alimentação saudável e dietas especiais.', 'nutricao-dietetica', 'fa-solid fa-apple-alt', '#fd7e14', '2025-05-20 02:25:25'),
(20, 'Psiquiatria', 'Estudos e práticas psiquiátricas.', 'psiquiatria', 'fa-solid fa-notes-medical', '#6610f2', '2025-05-20 02:25:25'),
(21, 'Medicina Preventiva', 'Prevenção e promoção da saúde.', 'medicina-preventiva', 'fa-solid fa-shield-alt', '#007bff', '2025-05-20 02:25:25'),
(22, 'Reabilitação Neurológica', 'Tratamentos para doenças neurológicas.', 'reabilitacao-neurologica', 'fa-solid fa-brain', '#28a745', '2025-05-20 02:25:25'),
(23, 'Terapias Alternativas', 'Abordagens não convencionais em saúde.', 'terapias-alternativas', 'fa-solid fa-leaf', '#17a2b8', '2025-05-20 02:25:25'),
(24, 'Psicomotricidade', 'Desenvolvimento motor e terapias.', 'psicomotricidade', 'fa-solid fa-running', '#ffc107', '2025-05-20 02:25:25'),
(25, 'Terapia Familiar', 'Intervenções na dinâmica familiar.', 'terapia-familiar', 'fa-solid fa-users', '#e83e8c', '2025-05-20 02:25:25'),
(26, 'Saúde do Trabalhador', 'Ergonomia e saúde ocupacional.', 'saude-trabalhador', 'fa-solid fa-hard-hat', '#20c997', '2025-05-20 02:25:25'),
(27, 'Tecnologias Assistivas', 'Recursos para acessibilidade e inclusão.', 'tecnologias-assistivas', 'fa-solid fa-wheelchair', '#fd7e14', '2025-05-20 02:25:25'),
(28, 'Psicologia do Esporte', 'Aspectos psicológicos no esporte.', 'psicologia-esporte', 'fa-solid fa-running', '#6610f2', '2025-05-20 02:25:25'),
(29, 'Saúde Mental na Adolescência', 'Desafios da saúde mental para adolescentes.', 'saude-mental-adolescencia', 'fa-solid fa-user-graduate', '#007bff', '2025-05-20 02:25:25'),
(30, 'Terapia Cognitivo-Comportamental', 'Técnicas e estudos em TCC.', 'terapia-cognitivo-comportamental', 'fa-solid fa-brain', '#28a745', '2025-05-20 02:25:25'),
(31, 'Saúde Bucal', 'Cuidados e prevenção em odontologia.', 'saude-bucal', 'fa-solid fa-tooth', '#17a2b8', '2025-05-20 02:25:25'),
(32, 'Psicologia Organizacional', 'Comportamento e dinâmica nas organizações.', 'psicologia-organizacional', 'fa-solid fa-briefcase', '#ffc107', '2025-05-20 02:25:25'),
(33, 'Medicina Integrativa', 'Combinação de práticas convencionais e alternativas.', 'medicina-integrativa', 'fa-solid fa-stethoscope', '#e83e8c', '2025-05-20 02:25:25'),
(34, 'Terapia de Casal', 'Intervenções para relacionamentos.', 'terapia-casal', 'fa-solid fa-heart', '#20c997', '2025-05-20 02:25:25'),
(35, 'Transtornos Alimentares', 'Informações sobre anorexia, bulimia e outros.', 'transtornos-alimentares', 'fa-solid fa-apple-alt', '#fd7e14', '2025-05-20 02:25:25'),
(36, 'Psicologia Educacional', 'Intervenções no ambiente escolar.', 'psicologia-educacional', 'fa-solid fa-school', '#6610f2', '2025-05-20 02:25:25'),
(37, 'Saúde Ambiental', 'Impactos ambientais na saúde.', 'saude-ambiental', 'fa-solid fa-tree', '#007bff', '2025-05-20 02:25:25'),
(38, 'Fisioterapia Respiratória', 'Tratamentos para saúde pulmonar.', 'fisioterapia-respiratoria', 'fas fa-lungs', '#28a745', '2025-05-20 02:25:25'),
(39, 'Terapia em Saúde Mental', 'Diversas abordagens terapêuticas.', 'terapia-saude-mental', 'fa-solid fa-head-side-medical', '#17a2b8', '2025-05-20 02:25:25'),
(40, 'Psicologia Social', 'Comportamento social e grupos.', 'psicologia-social', 'fa-solid fa-users', '#ffc107', '2025-05-20 02:25:25'),
(41, 'Terapia para Crianças', 'Abordagens específicas para o público infantil.', 'terapia-criancas', 'fa-solid fa-child', '#e83e8c', '2025-05-20 02:25:25'),
(42, 'Saúde Sexual e Reprodutiva', 'Temas relacionados à sexualidade e reprodução.', 'saude-sexual-reprodutiva', 'fa-solid fa-venus-mars', '#20c997', '2025-05-20 02:25:25'),
(43, 'Psicologia da Saúde', 'Relação entre saúde física e mental.', 'psicologia-da-saude', 'fa-solid fa-heartbeat', '#fd7e14', '2025-05-20 02:25:25'),
(44, 'Terapia Ocupacional Pediátrica', 'Intervenções para crianças com necessidades especiais.', 'terapia-ocupacional-pediatrica', 'fa-solid fa-baby', '#6610f2', '2025-05-20 02:25:25'),
(45, 'Psicologia do Desenvolvimento', 'Estudos do desenvolvimento humano.', 'psicologia-desenvolvimento', 'fa-solid fa-child', '#007bff', '2025-05-20 02:25:25'),
(46, 'Saúde Mental Comunitária', 'Intervenções e políticas públicas.', 'saude-mental-comunitaria', 'fa-solid fa-users', '#28a745', '2025-05-20 02:25:25'),
(47, 'Terapia Ocupacional em Saúde Mental', 'Práticas especializadas em saúde mental.', 'to-saude-mental', 'fa-solid fa-hands-helping', '#17a2b8', '2025-05-20 02:25:25'),
(48, 'Saúde e Bem-estar', 'Conteúdo geral sobre qualidade de vida.', 'saude-bem-estar', 'fa-solid fa-heart', '#ffc107', '2025-05-20 02:25:25'),
(49, 'Neonatologia', 'Cuidados e tratamentos para recém-nascidos.', 'neonatologia', 'fa-solid fa-baby', '#ff6699', '2025-05-20 02:25:25'),
(50, 'Terapia Aquática', 'Uso da água para fins terapêuticos.', 'terapia-aquatica', 'fa-solid fa-water', '#3399ff', '2025-05-20 02:25:25'),
(51, 'Psicofarmacologia', 'Uso de medicamentos na saúde mental.', 'psicofarmacologia', 'fa-solid fa-pills', '#cc3300', '2025-05-20 02:25:25'),
(52, 'Cuidados Paliativos', 'Atenção a pacientes com doenças crônicas.', 'cuidados-paliativos', 'fas fa-hand-holding-heart', '#993300', '2025-05-20 02:25:25'),
(53, 'Análise do Comportamento', 'Estudo do comportamento humano e intervenções.', 'analise-comportamento', 'fas fa-brain', '#006600', '2025-05-20 02:25:25'),
(54, 'Terapia da Fala', 'Abordagens para dificuldades na fala.', 'terapia-da-fala', 'fa-solid fa-comment', '#cc0066', '2025-05-20 02:25:25'),
(55, 'Musicoterapia', 'Uso terapêutico da música.', 'musicoterapia', 'fa-solid fa-music', '#ff6600', '2025-05-20 02:25:25'),
(56, 'Terapia Holística', 'Práticas integrativas para equilíbrio físico e mental.', 'terapia-holistica', 'fa-solid fa-spa', '#5a9e6f', '2025-05-20 02:25:58'),
(57, 'Psicologia Positiva', 'Estudos sobre emoções positivas e bem-estar.', 'psicologia-positiva', 'fa-solid fa-smile', '#f4b41a', '2025-05-20 02:25:58'),
(58, 'Terapia para Dependência Química', 'Abordagens para tratamento de vícios.', 'terapia-dependencia-quimica', 'fa-solid fa-hand-holding-medical', '#d94f4f', '2025-05-20 02:25:58'),
(59, 'Fisioterapia Pediátrica', 'Cuidados fisioterápicos para crianças.', 'fisioterapia-pediatrica', 'fas fa-child-reaching', '#52a7e0', '2025-05-20 02:25:58'),
(60, 'Avaliação Neuropsicológica', 'Diagnósticos e avaliações cognitivas.', 'avaliacao-neuropsicologica', 'fas fa-brain', '#874fa2', '2025-05-20 02:25:58'),
(61, 'Saúde Mental no Trabalho', 'Gestão do estresse e saúde emocional no ambiente profissional.', 'saude-mental-trabalho', 'fa-solid fa-briefcase-medical', '#ef7f22', '2025-05-20 02:25:58'),
(62, 'Psicologia Forense', 'Aplicações da psicologia no sistema judicial.', 'psicologia-forense', 'fa-solid fa-gavel', '#3e536b', '2025-05-20 02:25:58'),
(63, 'Terapia para Transtorno de Ansiedade', 'Técnicas para manejo da ansiedade.', 'terapia-transtorno-ansiedade', 'fa-solid fa-exclamation-triangle', '#ea5f5f', '2025-05-20 02:25:58'),
(64, 'Terapia de Grupo', 'Abordagens terapêuticas em grupo.', 'terapia-grupo', 'fa-solid fa-users', '#4b8bbe', '2025-05-20 02:25:58'),
(65, 'Terapia Ocupacional Geriátrica', 'Práticas para idosos em terapia ocupacional.', 'to-geriatrica', 'fa-solid fa-wheelchair', '#7c6bbf', '2025-05-20 02:25:58'),
(66, 'Psicologia Infantil', 'Desenvolvimento e psicologia para crianças.', 'psicologia-infantil', 'fa-solid fa-child', '#ff8c00', '2025-05-20 02:25:58'),
(67, 'Terapia Assistida por Animais', 'Uso de animais para fins terapêuticos.', 'terapia-assistida-animais', 'fa-solid fa-dog', '#a06040', '2025-05-20 02:25:58'),
(68, 'Psicologia da Arte', 'Uso da arte na prática terapêutica.', 'psicologia-da-arte', 'fa-solid fa-palette', '#db7093', '2025-05-20 02:25:58'),
(69, 'Psicologia Comunitária', 'Atuação em comunidades e grupos sociais.', 'psicologia-comunitaria', 'fa-solid fa-users', '#69b3a2', '2025-05-20 02:25:58'),
(70, 'Terapia para Transtorno Bipolar', 'Tratamento e manejo do transtorno bipolar.', 'terapia-transtorno-bipolar', 'fa-solid fa-chart-line', '#d47171', '2025-05-20 02:25:58'),
(71, 'Psicologia do Desenvolvimento Infantil', 'Estudo do crescimento e desenvolvimento da criança.', 'psicologia-desenvolvimento-infantil', 'fa-solid fa-baby', '#7d9ec0', '2025-05-20 02:25:58'),
(72, 'Neuropsicologia', 'Estudo das funções cognitivas e comportamento.', 'neuropsicologia', 'fa-solid fa-brain', '#3a6ea5', '2025-05-20 02:25:58'),
(73, 'Psicoterapia Online', 'Atendimento terapêutico via plataformas digitais.', 'psicoterapia-online', 'fa-solid fa-video', '#4a9f6f', '2025-05-20 02:25:58'),
(74, 'Saúde Mental e Exercício Físico', 'Relação entre atividade física e saúde mental.', 'saude-mental-exercicio', 'fa-solid fa-dumbbell', '#f3a712', '2025-05-20 02:25:58'),
(75, 'Saúde Mental na Infância', 'Cuidados e terapias para crianças.', 'saude-mental-infancia', 'fa-solid fa-child', '#6b8cce', '2025-05-20 02:25:58'),
(76, 'Transtorno do Déficit de Atenção', 'Abordagens para TDAH e transtornos relacionados.', 'transtorno-deficit-atencao', 'fa-solid fa-brain', '#c47f77', '2025-05-20 02:25:58'),
(77, 'Psicologia das Emoções', 'Estudo das emoções e seu impacto.', 'psicologia-das-emocoes', 'fa-solid fa-heart', '#c55f65', '2025-05-20 02:25:58'),
(78, 'Cuidados Intensivos Neonatais', 'Atenção especializada para recém-nascidos críticos.', 'cuidados-intensivos-neonatais', 'fas fa-hospital-user', '#5a879e', '2025-05-20 02:25:58'),
(79, 'Reabilitação Cardíaca', 'Tratamentos e cuidados para saúde do coração.', 'reabilitacao-cardiaca', 'fa-solid fa-heartbeat', '#cc3333', '2025-05-20 02:25:58'),
(80, 'Terapia Multissensorial', 'Estímulos para integração sensorial.', 'terapia-multissensorial', 'fa-solid fa-brain', '#7a8faf', '2025-05-20 02:25:58'),
(81, 'Saúde Mental e Alimentação', 'Influência da nutrição na saúde mental.', 'saude-mental-alimentacao', 'fa-solid fa-apple-whole', '#d4a255', '2025-05-20 02:25:58'),
(82, 'Psicologia da Saúde Pública', 'Políticas públicas e saúde mental.', 'psicologia-saude-publica', 'fa-solid fa-hospital', '#5c7a8f', '2025-05-20 02:25:58'),
(83, 'Terapia para Transtorno Obsessivo-Compulsivo', 'Manejo e tratamentos para TOC.', 'terapia-toc', 'fa-solid fa-brain', '#d14a4a', '2025-05-20 02:25:58'),
(84, 'Terapia Ocupacional Neurológica', 'Intervenções para pacientes neurológicos.', 'to-neurologica', 'fa-solid fa-brain', '#3f64a0', '2025-05-20 02:25:58'),
(85, 'Terapias Complementares', 'Terapias que complementam tratamentos convencionais.', 'terapias-complementares', 'fa-solid fa-leaf', '#7dbb6a', '2025-05-20 02:25:58'),
(86, 'Saúde Mental no Envelhecimento', 'Desafios e cuidados na saúde mental de idosos.', 'saude-mental-envelhecimento', 'fa-solid fa-user-clock', '#aa6c39', '2025-05-20 02:25:58');

-- --------------------------------------------------------

--
-- Estrutura para tabela `comentarios_conteudo`
--

CREATE TABLE `comentarios_conteudo` (
  `id_comentario` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `tipo_conteudo_principal` enum('podcast','oportunidade') NOT NULL,
  `id_conteudo_principal` int(11) NOT NULL,
  `id_comentario_pai` int(11) DEFAULT NULL,
  `texto_comentario` text NOT NULL,
  `data_comentario` timestamp NULL DEFAULT current_timestamp(),
  `data_ultima_edicao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `editado` tinyint(1) DEFAULT 0,
  `total_curtidas` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `comentarios_conteudo`
--

INSERT INTO `comentarios_conteudo` (`id_comentario`, `id_utilizador`, `tipo_conteudo_principal`, `id_conteudo_principal`, `id_comentario_pai`, `texto_comentario`, `data_comentario`, `data_ultima_edicao`, `editado`, `total_curtidas`, `ativo`) VALUES
(1, 1, 'podcast', 1, NULL, 'oi', '2025-05-18 02:20:33', '2025-05-18 02:20:37', 0, 0, 0),
(2, 1, 'podcast', 1, NULL, 'oi', '2025-05-18 02:35:06', '2025-05-18 02:35:11', 0, 0, 0),
(31, 1, 'podcast', 19, NULL, 'oi', '2025-05-20 02:45:16', NULL, 0, 0, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `curtidas_conteudo`
--

CREATE TABLE `curtidas_conteudo` (
  `id_curtida` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `tipo_conteudo` enum('podcast','oportunidade','comentario') NOT NULL,
  `id_conteudo` int(11) NOT NULL,
  `data_curtida` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `curtidas_conteudo`
--

INSERT INTO `curtidas_conteudo` (`id_curtida`, `id_utilizador`, `tipo_conteudo`, `id_conteudo`, `data_curtida`) VALUES
(59, 1, 'podcast', 27, '2025-05-24 08:38:14'),
(62, 26, 'podcast', 24, '2025-05-25 05:28:36'),
(63, 26, 'podcast', 15, '2025-05-26 02:56:59'),
(67, 26, 'podcast', 27, '2025-05-26 04:33:09'),
(69, 26, 'podcast', 26, '2025-05-26 04:40:09'),
(71, 26, 'podcast', 21, '2025-05-26 05:02:08'),
(72, 26, 'podcast', 20, '2025-05-26 05:08:09');

-- --------------------------------------------------------

--
-- Estrutura para tabela `favoritos`
--

CREATE TABLE `favoritos` (
  `id_favorito` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `tipo_conteudo` enum('podcast','oportunidade') NOT NULL,
  `id_conteudo` int(11) NOT NULL,
  `data_favoritado` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `favoritos_oportunidade`
--

CREATE TABLE `favoritos_oportunidade` (
  `id_favorito` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `id_oportunidade` int(11) NOT NULL,
  `data_favorito` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `fila_reproducao_utilizador`
--

CREATE TABLE `fila_reproducao_utilizador` (
  `id_fila` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `id_podcast` int(11) NOT NULL,
  `ordem` int(11) DEFAULT 0,
  `data_adicao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `fila_reproducao_utilizador`
--

INSERT INTO `fila_reproducao_utilizador` (`id_fila`, `id_utilizador`, `id_podcast`, `ordem`, `data_adicao`) VALUES
(1, 1, 19, 0, '2025-05-20 02:58:28'),
(6, 1, 23, 0, '2025-05-25 02:12:17');

-- --------------------------------------------------------

--
-- Estrutura para tabela `noticias`
--

CREATE TABLE `noticias` (
  `id_noticia` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `slug_noticia` varchar(255) NOT NULL,
  `excerto` text DEFAULT NULL,
  `conteudo_completo_html` longtext DEFAULT NULL,
  `url_imagem_destaque` varchar(512) DEFAULT NULL,
  `categoria_noticia` varchar(100) DEFAULT NULL,
  `autor_noticia` varchar(150) DEFAULT NULL,
  `id_utilizador_autor` int(11) DEFAULT NULL,
  `data_publicacao` datetime NOT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `data_ultima_modificacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ativo` tinyint(1) DEFAULT 1,
  `visibilidade` enum('publico','restrito_assinantes','rascunho') DEFAULT 'publico',
  `tags` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `noticias`
--

INSERT INTO `noticias` (`id_noticia`, `titulo`, `slug_noticia`, `excerto`, `conteudo_completo_html`, `url_imagem_destaque`, `categoria_noticia`, `autor_noticia`, `id_utilizador_autor`, `data_publicacao`, `data_criacao`, `data_ultima_modificacao`, `ativo`, `visibilidade`, `tags`) VALUES
(1, 'Resumo Semanal | 28/05/2025', 'resumo-semanal-28052025', 'Atualizações e Destaques em Terapia Ocupacional – Semana de 28 de maio de 2025', '<section class=\"to-noticias p-4 rounded shadow bg-white\">\r\n  <h2 class=\"fw-bold text-primary mb-4\" style=\"font-size:2rem;\">📰 Atualizações Recentes – Terapia Ocupacional</h2>\r\n  \r\n  <article class=\"mb-4\">\r\n    <h3 class=\"h5 fw-bold text-dark mb-2\">1. Avanços Legislativos: Piso Salarial Nacional em Debate</h3>\r\n    <p>\r\n      O <strong>Projeto de Lei nº 988/2015</strong>, que estabelece o piso salarial nacional para fisioterapeutas e terapeutas ocupacionais, avançou na Comissão de Constituição e Justiça da Câmara dos Deputados. O relator, deputado Duarte Jr. (PSB-MA), apresentou parecer favorável, destacando a valorização da profissão e a importância do direito à vida. O projeto aguarda votação na CCJ.\r\n    </p>\r\n    <p class=\"mb-0\"><a href=\"https://www.coffito.gov.br/nsite/?cat=5\" target=\"_blank\" rel=\"noopener\" class=\"link-primary text-decoration-underline\">Saiba mais no COFFITO</a></p>\r\n  </article>\r\n  \r\n  <article class=\"mb-4\">\r\n    <h3 class=\"h5 fw-bold text-dark mb-2\">2. Eventos Acadêmicos Reforçam a Profissão</h3>\r\n    <ul class=\"mb-2\">\r\n      <li>\r\n        <strong>II Semana Acadêmica de Terapia Ocupacional da UNINGÁ:</strong> Realizada em maio, discutiu temas como atenção primária, reabilitação neuroinfantil e adaptações de baixo custo para inclusão. Contou com palestras, oficinas e rodas de conversa.\r\n        <br>\r\n        <a href=\"https://www.uninga.br/noticia/ii-semana-academica-de-terapia-ocupacional-discute-os-caminhos-e-avancos-da-profissao-na-saude-e-na-inclusao/44418/\" target=\"_blank\" class=\"link-secondary\">Confira o evento</a>\r\n      </li>\r\n      <li class=\"mt-2\">\r\n        <strong>V Semana de Terapia Ocupacional da UFES:</strong> Inscrições abertas para submissão de trabalhos. O evento acontecerá de 12 a 16 de agosto, promovendo troca de experiências entre profissionais e estudantes.\r\n        <br>\r\n        <a href=\"https://terapiaocupacional.ufes.br/\" target=\"_blank\" class=\"link-secondary\">Acesse a programação</a>\r\n      </li>\r\n    </ul>\r\n  </article>\r\n\r\n  <article class=\"mb-4\">\r\n    <h3 class=\"h5 fw-bold text-dark mb-2\">3. Inovações Tecnológicas na Prática Terapêutica</h3>\r\n    <ul class=\"mb-2\">\r\n      <li>\r\n        <strong>Telereabilitação com Realidade Virtual:</strong> Revisão recente destaca o uso de realidade virtual na telereabilitação pós-AVC, apontando para melhores resultados e maior engajamento dos pacientes.\r\n        <br>\r\n        <a href=\"https://arxiv.org/abs/2501.06899\" target=\"_blank\" class=\"link-secondary\">Leia a revisão completa</a>\r\n      </li>\r\n      <li class=\"mt-2\">\r\n        <strong>Aplicativo de Terapia de Reminiscência:</strong> O app \"Recuerdame\" foi desenvolvido para apoiar terapeutas em intervenções para idosos com demência, trazendo usabilidade e eficácia aprimoradas.\r\n        <br>\r\n        <a href=\"https://arxiv.org/abs/2410.13556\" target=\"_blank\" class=\"link-secondary\">Veja o artigo sobre o app</a>\r\n      </li>\r\n    </ul>\r\n  </article>\r\n\r\n  <div class=\"alert alert-info mt-4 mb-0\" style=\"font-size: 1rem;\">\r\n    Acompanhe as novidades, participe dos eventos e fique por dentro dos avanços em Terapia Ocupacional! 💙\r\n  </div>\r\n</section>', '', '', 'Bruno Perdigão Alexandrino', 1, '2025-05-29 05:36:00', '2025-05-29 05:36:48', '2025-05-29 05:36:48', 1, 'publico', '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `oportunidades`
--

CREATE TABLE `oportunidades` (
  `id_oportunidade` int(11) NOT NULL,
  `tipo_oportunidade` enum('curso','webinar','artigo','vaga','evento','outro') NOT NULL,
  `titulo_oportunidade` varchar(255) NOT NULL,
  `descricao_oportunidade` text NOT NULL,
  `link_oportunidade` varchar(512) DEFAULT NULL,
  `data_publicacao` timestamp NULL DEFAULT current_timestamp(),
  `data_evento_inicio` datetime DEFAULT NULL,
  `data_evento_fim` datetime DEFAULT NULL,
  `local_evento` varchar(255) DEFAULT NULL,
  `fonte_oportunidade` varchar(255) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `total_curtidas` int(11) DEFAULT 0,
  `total_comentarios` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `status` varchar(30) DEFAULT 'aberta',
  `destaque` tinyint(1) DEFAULT 0,
  `data_cadastro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `oportunidades`
--

INSERT INTO `oportunidades` (`id_oportunidade`, `tipo_oportunidade`, `titulo_oportunidade`, `descricao_oportunidade`, `link_oportunidade`, `data_publicacao`, `data_evento_inicio`, `data_evento_fim`, `local_evento`, `fonte_oportunidade`, `tags`, `total_curtidas`, `total_comentarios`, `ativo`, `status`, `destaque`, `data_cadastro`) VALUES
(14, 'webinar', 'Los cuatro pilares del bienestar ocupacional de los terapeutas ocupacionales', 'Webinar focado no autocuidado profissional e desenvolvimento do bem-estar desde a Terapia Ocupacional.', 'https://coptocam.org/webinar-los-cuatro-pilares-del-bienestar-ocupacional-de-los-terapeutas-ocupacionales/', '2025-04-16 00:00:00', '2025-04-23 18:00:00', '2025-04-23 19:30:00', 'Online', 'COPTOCAM', 'webinar,terapia ocupacional,autocuidado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:32:54'),
(15, 'vaga', 'Terapeuta Ocupacional - AACD', 'Atendimento infantil na unidade Ibirapuera. Salário entre R$ 3.500,00 e R$ 9.000,00.', 'https://br.linkedin.com/jobs/terapeuta-ocupacional-vagas', '2025-05-20 00:00:00', NULL, NULL, 'São Paulo, SP', 'LinkedIn', 'vaga,terapia ocupacional,AACD', 0, 0, 1, 'ativo', 0, '2025-05-21 05:32:54'),
(16, 'vaga', 'Terapeuta Ocupacional - Hospital Placi', 'Atendimento hospitalar em unidade especializada no Rio de Janeiro.', 'https://br.linkedin.com/jobs/terapeuta-ocupacional-hospitalar-vagas', '2025-05-20 00:00:00', NULL, NULL, 'Rio de Janeiro, RJ', 'LinkedIn', 'vaga,terapia ocupacional,hospital', 0, 0, 1, 'ativo', 0, '2025-05-21 05:32:54'),
(17, 'vaga', 'Terapeuta Ocupacional - UNIBES', 'Atendimento a pacientes em instituição filantrópica em São Paulo.', 'https://br.indeed.com/q-terapeuta-ocupacional-vagas.html', '2025-05-20 00:00:00', NULL, NULL, 'São Paulo, SP', 'Indeed', 'vaga,terapia ocupacional,UNIBES', 0, 0, 1, 'ativo', 0, '2025-05-21 05:32:54'),
(18, 'vaga', 'Terapeuta Ocupacional - Prefeitura de Umbuzeiro', 'Atuação em programas municipais de saúde. Salário até R$ 3.637,50.', 'https://www.pciconcursos.com.br/vagas/terapeuta-ocupacional', '2025-05-20 00:00:00', NULL, NULL, 'Umbuzeiro, PB', 'PCI Concursos', 'vaga,terapia ocupacional,concurso público', 0, 0, 1, 'ativo', 0, '2025-05-21 05:32:54'),
(19, 'curso', 'Terapia Ocupacional – Edune Cursos', 'Curso online gratuito com carga horária de 10 horas, abordando fundamentos e práticas da terapia ocupacional. Certificado opcional mediante taxa.', 'https://www.edunecursos.com.br/curso/terapia-ocupacional', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Edune Cursos', 'terapia ocupacional,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:40:08'),
(20, 'curso', 'Terapia Ocupacional – Anglo Cursos', 'Curso gratuito com 80h sobre reabilitação de indivíduos com limitações físicas, sensoriais, cognitivas ou emocionais. Certificado gratuito em PDF.', 'https://anglocursos.com.br/cursos/de/educacao/terapia-ocupacional/gratis', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Anglo Cursos', 'terapia ocupacional,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:40:08'),
(21, 'curso', 'Capacitação em ABA para TEA – Ministério da Saúde', 'Curso gratuito com carga horária de 40h destinado a profissionais de saúde, com certificado. Conteúdo voltado ao Transtorno do Espectro Autista (TEA).', 'https://www.gov.br/saude/pt-br/assuntos/noticias/2022/abril/ministerio-da-saude-oferta-cursos-gratuitos-sobre-o-transtorno-do-espectro-autista', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Ministério da Saúde', 'TEA,autismo,curso,gratuito,certificado,ABA', 0, 0, 1, 'ativo', 0, '2025-05-21 05:40:08'),
(22, 'curso', 'TDAH na Prática – Instituto Neuro', 'Curso gratuito sobre estratégias práticas para o manejo do TDAH, com certificado.', 'https://www.institutoneuro.com.br/cursos/tdah-na-pratica-curso-gratuito/', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Instituto Neuro', 'TDAH,curso,gratuito,certificado,neurodesenvolvimento', 0, 0, 1, 'ativo', 0, '2025-05-21 05:40:08'),
(23, 'curso', 'Capacitação em TOD – Instituto Neuro', 'Curso gratuito sobre estratégias práticas para o manejo do Transtorno Opositivo Desafiador (TOD), com certificado.', 'https://www.institutoneuro.com.br/cursos/capacitacao-tod-transtorno-opositivo/', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Instituto Neuro', 'TOD,curso,gratuito,certificado,neurodesenvolvimento', 0, 0, 1, 'ativo', 0, '2025-05-21 05:40:08'),
(24, 'curso', 'Curso de Autismo – Anglo Cursos', 'Curso gratuito de 60h com princípios e práticas para educação de pessoas com TEA. Certificado disponível.', 'https://anglocursos.com.br/cursos/de/autismo/autismo/gratis', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Anglo Cursos', 'TEA,autismo,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:40:08'),
(25, 'curso', 'Terapia Ocupacional – Edune Cursos', 'Curso online gratuito com carga horária de 10 horas, abordando fundamentos e práticas da terapia ocupacional. Certificado opcional mediante taxa.', 'https://www.edunecursos.com.br/curso/terapia-ocupacional', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Edune Cursos', 'terapia ocupacional,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(26, 'curso', 'Terapia Ocupacional – Anglo Cursos', 'Curso gratuito com 80h sobre reabilitação de indivíduos com limitações físicas, sensoriais, cognitivas ou emocionais. Certificado gratuito em PDF.', 'https://anglocursos.com.br/cursos/de/educacao/terapia-ocupacional/gratis', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Anglo Cursos', 'terapia ocupacional,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(27, 'curso', 'Terapia Ocupacional – Unova Cursos', 'Curso online gratuito com carga horária de 10 horas, focado na capacitação em terapia ocupacional. Certificado opcional mediante taxa.', 'https://www.unovacursos.com.br/curso/terapia-ocupacional', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Unova Cursos', 'terapia ocupacional,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(28, 'curso', 'Terapia Ocupacional – EW Cursos', 'Curso gratuito abordando fundamentos da terapia ocupacional. Certificado opcional mediante taxa.', 'https://www.ewcursos.com/curso/terapia-ocupacional', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'EW Cursos', 'terapia ocupacional,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(29, 'curso', 'Terapia Ocupacional – Abrafordes', 'Curso gratuito com carga horária de 70 horas, focado em ajudar pessoas a superar desafios físicos, emocionais, cognitivos ou sociais. Certificado disponível.', 'https://www.cursosabrafordes.com.br/curso/terapiaocupacional', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Abrafordes', 'terapia ocupacional,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(30, 'curso', 'Terapia Ocupacional – Elevo Cursos', 'Curso gratuito com carga horária de 80 horas, abordando fundamentos e práticas da terapia ocupacional. Certificado disponível.', 'https://elevocursos.com.br/cursos/de/educacao/terapia-ocupacional/gratis', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Elevo Cursos', 'terapia ocupacional,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(31, 'curso', 'Terapia Ocupacional – WR Educacional', 'Curso gratuito com carga horária de 80 horas, abordando fundamentos e práticas da terapia ocupacional. Certificado disponível.', 'https://www.wreducacional.com.br/lista-de-cursos/terapia-ocupacional', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'WR Educacional', 'terapia ocupacional,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(32, 'curso', 'Terapia Ocupacional – UP Cursos Grátis', 'Curso gratuito de introdução à terapia ocupacional. Certificado disponível mediante taxa de emissão.', 'https://upcursosgratis.com.br/blog/curso-gratuito-de-introducao-a-terapia-ocupacional/', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'UP Cursos Grátis', 'terapia ocupacional,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(33, 'curso', 'Capacitação em ABA para TEA – Ministério da Saúde', 'Curso gratuito com carga horária de 40h destinado a profissionais de saúde, com certificado. Conteúdo voltado ao Transtorno do Espectro Autista (TEA).', 'https://www.gov.br/saude/pt-br/assuntos/noticias/2022/abril/ministerio-da-saude-oferta-cursos-gratuitos-sobre-o-transtorno-do-espectro-autista', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Ministério da Saúde', 'TEA,autismo,curso,gratuito,certificado,ABA', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(34, 'curso', 'Curso de Autismo – Anglo Cursos', 'Curso gratuito de 60h com princípios e práticas para educação de pessoas com TEA. Certificado disponível.', 'https://anglocursos.com.br/cursos/de/autismo/autismo/gratis', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Anglo Cursos', 'TEA,autismo,curso,gratuito,certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(35, 'curso', 'TDAH na Prática – Instituto Neuro', 'Curso gratuito sobre estratégias práticas para o manejo do TDAH, com certificado.', 'https://www.institutoneuro.com.br/cursos/tdah-na-pratica-curso-gratuito/', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Instituto Neuro', 'TDAH,curso,gratuito,certificado,neurodesenvolvimento', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(36, 'curso', 'Capacitação em TOD – Instituto Neuro', 'Curso gratuito sobre estratégias práticas para o manejo do Transtorno Opositivo Desafiador (TOD), com certificado.', 'https://www.institutoneuro.com.br/cursos/capacitacao-tod-transtorno-opositivo/', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Instituto Neuro', 'TOD,curso,gratuito,certificado,neurodesenvolvimento', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(37, 'curso', 'Enfermagem na Neurologia – Edune Cursos', 'Curso online gratuito com carga horária de 40 horas, abordando cuidados e tratamentos neurológicos. Certificado opcional mediante taxa.', 'https://www.edunecursos.com.br/curso/enfermagem-na-neurologia', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Edune Cursos', 'neurologia, enfermagem, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(38, 'curso', 'Neurociência do Desenvolvimento – PUCRS Online', 'Curso gratuito e 100% online, focando exclusivamente no conteúdo disponibilizado ao aluno.', 'https://online.pucrs.br/formacao-gratuita/neurociencia-do-desenvolvimento', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'PUCRS Online', 'neurociência, desenvolvimento, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(39, 'curso', 'Psiquiatria Forense – Elevo Cursos', 'Curso online gratuito com certificado opcional, abordando a interface entre psiquiatria e direito penal.', 'https://elevocursos.com.br/cursos/de/direito/psiquiatria-forense/gratis', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Elevo Cursos', 'psiquiatria, forense, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(40, 'curso', 'Saúde Mental e Atenção Psicossocial – Anglo Cursos', 'Curso online gratuito com certificado opcional, abordando práticas e estratégias de atenção psicossocial no contexto da saúde mental.', 'https://anglocursos.com.br/cursos/de/psicologia/saude-mental-e-atencao-psicossocial/gratis', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Anglo Cursos', 'saúde mental, psicossocial, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(41, 'curso', 'Fisioterapia Básica – GYN Cursos', 'Curso online gratuito com certificado digital gratuito, abordando fundamentos da fisioterapia, incluindo ergonomia, neuroanatomia e fisioterapia respiratória.', 'https://gyncursos.com.br/course/curso-de-fisioterapia-basica/', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'GYN Cursos', 'fisioterapia, básico, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(42, 'curso', 'Fisioterapia no Ambiente Ambulatorial – USCS', 'Curso online gratuito com carga horária de 40 horas, focado em pacientes com doenças cardiovasculares, metabólicas e pulmonares.', 'https://www.posuscs.com.br/conheca-3-cursos-online-gratuitos-de-fisioterapia-da-uscs/noticia/2901', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'USCS', 'fisioterapia, ambulatorial, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:42:31'),
(43, 'curso', 'Análise do Comportamento – Elevo Cursos', 'Curso gratuito online com certificado, indicado para psicólogos, educadores, terapeutas e profissionais da saúde interessados em compreender os mecanismos do comportamento humano.', 'https://elevocursos.com.br/cursos/de/psicologia/analise-do-comportamento/gratis', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Elevo Cursos', 'análise do comportamento, psicologia, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:44:59'),
(44, 'curso', 'Análise do Comportamento – Adequa Cursos', 'Curso online e gratuito com certificado, abordando conceitos como reforço, punição e extinção, aplicáveis em contextos terapêuticos, educacionais e sociais.', 'https://www.adequacursos.com.br/curso/psicologia/analise-do-comportamento', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Adequa Cursos', 'análise do comportamento, psicologia, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:44:59'),
(45, 'curso', 'Autismo – Impulso06', 'Curso gratuito de 50 horas sobre técnicas de intervenção para Transtorno do Espectro Autista e Síndrome de Asperger, com certificado.', 'https://impulso06.com/cursos/autismo/', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Impulso06', 'autismo, TEA, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:44:59'),
(46, 'curso', 'Avaliação Neuropsicológica do Adulto e Idoso – Portal IDEA', 'Curso gratuito online com certificado, abordando princípios básicos, processos cognitivos, testes neuropsicológicos e análise de dados clínicos.', 'https://portalidea.com.br/curso-gratuito-avaliacao-neuropsicologica-do-adulto-e-idoso', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Portal IDEA', 'avaliação neuropsicológica, neuropsicologia, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:44:59'),
(47, 'curso', 'Fundamentos do Cuidado Paliativo – OPS', 'Curso virtual gratuito que fornece uma introdução básica à prática dos cuidados paliativos, abordando avaliação e manejo do sofrimento multidimensional.', 'https://campus.paho.org/es/curso/Cuidado-Paliativo', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'OPS', 'cuidados paliativos, saúde, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:44:59'),
(48, 'curso', 'Educação em Saúde – Adequa Cursos', 'Curso gratuito online com certificado, abordando princípios da educação em saúde, estratégias de promoção da saúde e comunicação eficaz.', 'https://www.adequacursos.com.br/curso/saude-e-medicina/educacao-em-saude', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Adequa Cursos', 'educação em saúde, saúde pública, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:44:59'),
(49, 'curso', 'Educação Especial – EducaWeb', 'Curso online gratuito com certificado, abordando os principais fundamentos da educação especial em quatro módulos.', 'https://cursoseducaweb.com.br/curso-de-educacao-especial', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'EducaWeb', 'educação especial, inclusão, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:44:59'),
(50, 'curso', 'Fisioterapia em Pediatria – Cursa', 'Curso gratuito online com certificado, abordando fundamentos da fisioterapia pediátrica para iniciantes.', 'https://cursa.com.br/curso/fisioterapia-em-pediatria', '2025-05-21 00:00:00', NULL, NULL, 'Online', 'Cursa', 'fisioterapia pediátrica, fisioterapia, curso, gratuito, certificado', 0, 0, 1, 'ativo', 0, '2025-05-21 05:44:59'),
(51, '', 'Proibição do Ensino a Distância (EaD) na Enfermagem', 'O Ministério da Educação proibiu a oferta de cursos de Enfermagem na modalidade EaD. O Cofen celebrou a decisão, destacando a importância da formação prática presencial para garantir a qualidade dos profissionais de saúde.', 'https://www.cofen.gov.br/categoria/noticias/?utm_source=chatgpt.com', '2025-05-21 00:00:00', NULL, NULL, 'Nacional', 'Cofen', 'notícia, enfermagem, ensino a distância, cofen, presencial', 0, 0, 1, 'ativo', 0, '2025-05-21 05:48:05'),
(52, '', 'Semana da Enfermagem 2025', 'Durante a Semana da Enfermagem, o Cofen participou de sessões solenes e homenagens, ressaltando o papel essencial dos profissionais de enfermagem no cuidado à saúde.', 'https://www.cofen.gov.br/categoria/noticias/?utm_source=chatgpt.com', '2025-05-21 00:00:00', NULL, NULL, 'Nacional', 'Cofen', 'notícia, enfermagem, semana da enfermagem, homenagem', 0, 0, 1, 'ativo', 0, '2025-05-21 05:48:05'),
(53, '', 'CNS participa da 78ª Assembleia Mundial da Saúde', 'O CNS participou de debates sobre saúde global e mudanças climáticas, reforçando o compromisso com a participação social nas decisões de saúde pública.', 'https://www.gov.br/conselho-nacional-de-saude/pt-br/conselho-nacional-de-saude-participa-de-debates-sobre-saude-global-e-mudancas-climaticas-no-primeiro-dia-da-78a-assembleia-mundial-da-saude?utm_source=chatgpt.com', '2025-05-21 00:00:00', NULL, NULL, 'Nacional', 'CNS', 'notícia, saúde pública, CNS, assembleia mundial', 0, 0, 1, 'ativo', 0, '2025-05-21 05:48:05'),
(54, '', 'CNS destaca debate sobre Mortalidade Materna', 'Durante a 366ª Reunião Ordinária, o CNS destacou que nove em cada dez mortes maternas são evitáveis, enfatizando a necessidade de políticas públicas eficazes para reduzir esses índices.', 'https://www.gov.br/conselho-nacional-de-saude/pt-br/assuntos/noticias?utm_source=chatgpt.com', '2025-05-21 00:00:00', NULL, NULL, 'Nacional', 'CNS', 'notícia, mortalidade materna, políticas públicas, CNS', 0, 0, 1, 'ativo', 0, '2025-05-21 05:48:05'),
(55, '', 'Nota de Protesto contra o Novo Marco Regulatório da EaD (Farmácia)', 'O CFF manifestou indignação com o decreto que permite a continuidade de cursos de Farmácia na modalidade semipresencial, argumentando que a formação prática é essencial para a profissão.', 'https://site.cff.org.br/noticia/noticias-do-cff/20/05/2025/ead-nota-de-protesto-contra-o-novo-marco-regulatorio-da-educacao-a-distancia-em-saude?utm_source=chatgpt.com', '2025-05-21 00:00:00', NULL, NULL, 'Nacional', 'CFF', 'notícia, farmácia, EaD, ensino, CFF', 0, 0, 1, 'ativo', 0, '2025-05-21 05:48:05'),
(56, '', 'Suspensão da Prescrição de Medicamentos por Farmacêuticos', 'A Justiça Federal suspendeu a resolução do CFF que autorizava farmacêuticos a prescrever medicamentos, atendendo a um pedido do Conselho Federal de Medicina (CFM).', 'https://portal.cfm.org.br/noticias/vitoria-da-medicina-justica-suspende-prescricao-de-medicamentos-por-farmaceuticos/?utm_source=chatgpt.com', '2025-05-21 00:00:00', NULL, NULL, 'Nacional', 'CFF/CFM', 'notícia, farmácia, prescrição, justiça, CFF, CFM', 0, 0, 1, 'ativo', 0, '2025-05-21 05:48:05'),
(57, '', 'CFBM participa da XXVI Marcha a Brasília em Defesa dos Municípios', 'O CFBM participou do evento para discutir políticas públicas e fortalecer a atuação dos biomédicos nos municípios.', 'https://cfbm.gov.br/category/noticias/?utm_source=chatgpt.com', '2025-05-21 00:00:00', NULL, NULL, 'Nacional', 'CFBM', 'notícia, biomedicina, CFBM, municípios, políticas públicas', 0, 0, 1, 'ativo', 0, '2025-05-21 05:48:05'),
(58, '', 'CFBM lança formulário para estudo da realidade profissional', 'A Comissão da Valorização Biomédica do CFBM lançou um formulário para estudar a realidade profissional da categoria, visando melhorias nas condições de trabalho e formação.', 'https://crbm1.gov.br/category/noticias/?utm_source=chatgpt.com', '2025-05-21 00:00:00', NULL, NULL, 'Nacional', 'CFBM', 'notícia, biomedicina, valorização, CFBM, trabalho', 0, 0, 1, 'ativo', 0, '2025-05-21 05:48:05');

-- --------------------------------------------------------

--
-- Estrutura para tabela `planos_assinatura`
--

CREATE TABLE `planos_assinatura` (
  `id_plano` int(11) NOT NULL,
  `nome_plano` varchar(100) NOT NULL,
  `descricao_plano` text DEFAULT NULL,
  `preco_mensal` decimal(10,2) DEFAULT NULL,
  `preco_anual` decimal(10,2) DEFAULT NULL,
  `funcionalidades` text DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `planos_assinatura`
--

INSERT INTO `planos_assinatura` (`id_plano`, `nome_plano`, `descricao_plano`, `preco_mensal`, `preco_anual`, `funcionalidades`, `ativo`) VALUES
(1, 'Explorador', 'Perfeito para começar a explorar.', 0.00, NULL, 'Acesso a 10 novos podcasts por mês;Download dos PDFs correspondentes;Acesso à seção de Oportunidades', 1),
(2, 'TO Pro', 'Tudo que você precisa para se destacar.', 34.90, NULL, 'Acesso ILIMITADO a todos os podcasts;Download de todos os PDFs;Acesso prioritário a novas Oportunidades;Conteúdo exclusivo para membros Pro', 1),
(3, 'TO Master', 'Melhor custo-benefício com desconto (equivale ao Pro).', NULL, 397.00, 'Todos os benefícios do Plano Pro;Pagamento único anual com desconto;Suporte prioritário', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `podcasts`
--

CREATE TABLE `podcasts` (
  `id_podcast` int(11) NOT NULL,
  `id_assunto` int(11) NOT NULL,
  `titulo_podcast` varchar(255) NOT NULL,
  `descricao_podcast` text DEFAULT NULL,
  `url_audio` varchar(512) NOT NULL,
  `duracao_total_segundos` int(11) DEFAULT 0,
  `link_material_apoio` varchar(512) DEFAULT NULL,
  `imagem_capa_url` varchar(512) DEFAULT NULL,
  `data_publicacao` datetime DEFAULT current_timestamp(),
  `visibilidade` enum('publico','restrito_assinantes') DEFAULT 'restrito_assinantes',
  `id_plano_minimo` int(11) DEFAULT NULL,
  `slug_podcast` varchar(255) NOT NULL,
  `total_curtidas` int(11) DEFAULT 0,
  `total_comentarios` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `podcasts`
--

INSERT INTO `podcasts` (`id_podcast`, `id_assunto`, `titulo_podcast`, `descricao_podcast`, `url_audio`, `duracao_total_segundos`, `link_material_apoio`, `imagem_capa_url`, `data_publicacao`, `visibilidade`, `id_plano_minimo`, `slug_podcast`, `total_curtidas`, `total_comentarios`, `ativo`) VALUES
(4, 5, 'Reabilitação Pós-AVC: Primeiros Passos', 'Como iniciar a reabilitação física após um AVC.', 'uploads/audios/reabilitacao/post-avc/audio_04.ogg', 0, 'uploads/materiais/reabilitacao/post-avc/material_04.pdf', NULL, '2025-01-18 10:00:00', 'restrito_assinantes', 1, 'reabilitacao-pos-avc-primeiros-passos', 12, 4, 1),
(5, 6, 'Controlando Diabetes com Exercícios', 'Exercícios simples para ajudar no controle do diabetes.', 'uploads/audios/educacao/diabetes/audio_05.ogg', 0, 'uploads/materiais/educacao/diabetes/material_05.pdf', NULL, '2025-01-25 09:30:00', 'restrito_assinantes', 2, 'controlando-diabetes-exercicios', 8, 3, 1),
(7, 8, 'Autonomia para Idosos: Práticas de TO', 'Técnicas para melhorar a autonomia dos idosos.', 'uploads/audios/terapia-ocupacional/idosos/audio_07.ogg', 0, 'uploads/materiais/terapia-ocupacional/idosos/material_07.pdf', NULL, '2025-02-10 11:00:00', 'restrito_assinantes', 1, 'autonomia-idosos-praticas-to', 22, 5, 1),
(8, 9, 'Tecnologias Assistivas: Ferramentas Essenciais', 'Equipamentos que auxiliam na reabilitação.', 'uploads/audios/terapia-ocupacional/tecnologias/audio_08.ogg', 0, 'uploads/materiais/terapia-ocupacional/tecnologias/material_08.pdf', NULL, '2025-02-20 13:15:00', 'restrito_assinantes', 3, 'tecnologias-assistivas-ferramentas', 10, 2, 1),
(9, 10, 'Importância da Saúde Bucal', 'Cuidados essenciais para manter a saúde bucal.', 'uploads/audios/educacao/saude-bucal/audio_09.ogg', 0, 'uploads/materiais/educacao/saude-bucal/material_09.pdf', NULL, '2025-02-28 08:45:00', 'restrito_assinantes', 1, 'importancia-saude-bucal', 14, 4, 1),
(10, 11, 'Fisioterapia Respiratória para Crianças', 'Técnicas para melhorar a respiração infantil.', 'uploads/audios/reabilitacao/fisioterapia-respiratoria-criancas/audio_10.ogg', 0, 'uploads/materiais/reabilitacao/fisioterapia-respiratoria-criancas/material_10.pdf', NULL, '2025-03-05 16:30:00', 'restrito_assinantes', 2, 'fisioterapia-respiratoria-criancas', 19, 7, 1),
(12, 13, 'Ergonomia: Prevenção de Lesões', 'Como cuidar da postura para evitar problemas.', 'uploads/audios/terapia-ocupacional/ergonomia/audio_12.ogg', 0, 'uploads/materiais/terapia-ocupacional/ergonomia/material_12.pdf', NULL, '2025-03-15 09:15:00', 'restrito_assinantes', 2, 'ergonomia-prevencao-lesoes', 17, 3, 1),
(13, 14, 'Alimentação Saudável para Adultos', 'Dicas para manter uma dieta equilibrada.', 'uploads/audios/educacao/alimentacao-saudavel/audio_13.ogg', 0, 'uploads/materiais/educacao/alimentacao-saudavel/material_13.pdf', NULL, '2025-03-20 12:30:00', 'restrito_assinantes', 1, 'alimentacao-saudavel-adultos', 21, 6, 1),
(14, 5, 'Cuidados com a Saúde Mental', 'Estratégias para manter o equilíbrio emocional.', 'uploads/audios/saude-mental/cuidados-mentais/audio_14.ogg', 0, 'uploads/materiais/saude-mental/cuidados-mentais/material_14.pdf', NULL, '2025-03-25 14:00:00', 'restrito_assinantes', 2, 'cuidados-saude-mental', 13, 4, 1),
(15, 8, 'Intervenções precoces no Autismo', 'Importância da intervenção logo após o diagnóstico.', 'uploads/audios/autismo/intervencoes-precoces/audio_15.ogg', 0, 'uploads/materiais/autismo/intervencoes-precoces/material_15.pdf', NULL, '2025-03-30 16:45:00', 'restrito_assinantes', 3, 'intervencoes-precoces-autismo', 26, 8, 1),
(16, 9, 'Uso da Música na Terapia Ocupacional', 'Benefícios terapêuticos da música.', 'uploads/audios/terapia-ocupacional/musica/audio_16.ogg', 0, 'uploads/materiais/terapia-ocupacional/musica/material_16.pdf', NULL, '2025-04-05 11:30:00', 'restrito_assinantes', 1, 'musica-terapia-ocupacional', 18, 5, 1),
(18, 6, 'Exercícios para Melhorar a Memória', 'Dicas e exercícios para memória ativa.', 'uploads/audios/educacao/memoria/audio_18.ogg', 0, 'uploads/materiais/educacao/memoria/material_18.pdf', NULL, '2025-04-15 09:45:00', 'restrito_assinantes', 1, 'exercicios-memoria', 22, 9, 1),
(19, 319, 'Ensino de Habilidades Básicas para Autismo', 'Habilidades Básicas para Autismo', 'uploads/audios/autismo-tea/inclusao-escolar-tea/podcast_1747709016_682bec58ed3f9.mp3', 0, NULL, NULL, '2025-05-20 02:43:36', 'restrito_assinantes', NULL, 'ensino-de-habilidades-basicas-para-autismo', 0, 0, 1),
(20, 434, 'Episódio 1: “Por que avaliar é essencial? Desafios e avanços na Terapia Ocupacional Infantil”', 'Neste episódio, discutimos como a avaliação se tornou um pilar fundamental na prática da Terapia Ocupacional com crianças e adolescentes no Brasil. Exploramos os principais desafios enfrentados pelos profissionais e os avanços nas últimas décadas.', 'uploads/audios/analise-comportamento/avaliacao-na-terapia-ocupacional-infantil-e-juvenil/podcast_1747800140_682d504c8c4ec.mp3', 0, 'uploads/materiais/analise-comportamento/avaliacao-na-terapia-ocupacional-infantil-e-juvenil/material_1747800140_682d504c8dfba.pdf', NULL, '2025-05-21 04:02:20', 'restrito_assinantes', NULL, 'episodio-1-por-que-avaliar-e-essencial-desafios-e-avancos-na-terapia-ocupacional-infantil', 0, 0, 1),
(21, 434, 'Episódio 2: “Conheça os instrumentos brasileiros de avaliação em Terapia Ocupacional”', 'Um mergulho nos instrumentos de avaliação criados por terapeutas ocupacionais brasileiros para o público infantojuvenil. Vamos entender suas aplicações clínicas e como eles ajudam a construir planos terapêuticos mais eficazes.', 'uploads/audios/analise-comportamento/avaliacao-na-terapia-ocupacional-infantil-e-juvenil/podcast_1747800925_682d535d9ea99.mp3', 0, NULL, NULL, '2025-05-21 04:15:25', 'restrito_assinantes', NULL, 'episodio-2-conheca-os-instrumentos-brasileiros-de-avaliacao-em-terapia-ocupacional', 0, 0, 1),
(22, 434, 'Episódio 3: “Mando, Tato, Intraverbal: os pilares do comportamento verbal” Abordagem: explicar os principais operantes verbais com exemplos práticos. Descrição: Aprenda o que são os operantes verbais e como eles ajudam a ensinar habilidades essenciais com', 'Aprenda o que são os operantes verbais e como eles ajudam a ensinar habilidades essenciais como pedir, nomear objetos, responder perguntas e muito mais.', 'uploads/audios/analise-comportamento/avaliacao-na-terapia-ocupacional-infantil-e-juvenil/podcast_1747801878_682d571621bae.mp3', 0, NULL, NULL, '2025-05-21 04:31:18', 'restrito_assinantes', NULL, 'episodio-3-mando-tato-intraverbal-os-pilares-do-comportamento-verbal-abordagem-explicar-os-principais-operantes-verbais-com-exemplos-praticos-descricao-aprenda-o-que-sao-os-operantes-verbais-e-como-eles-ajudam-a-ensinar-habilidades-essenciais-como-pedir-n', 0, 0, 1),
(23, 434, 'Episódio 4: Desafios da prática: por que usamos pouco os instrumentos?', 'Apesar da relevância dos instrumentos próprios da TO, muitos profissionais ainda não os utilizam em sua rotina. Vamos entender os motivos por trás disso e como mudar esse cenário.', 'uploads/audios/analise-comportamento/avaliacao-na-terapia-ocupacional-infantil-e-juvenil/podcast_1747802628_682d5a04b59f0.mp3', 0, NULL, NULL, '2025-05-21 04:43:48', 'restrito_assinantes', NULL, 'episodio-4-desafios-da-pratica-por-que-usamos-pouco-os-instrumentos', 0, 0, 1),
(24, 434, 'Episódio 5: O Futuro da Avaliação em Terapia Ocupacional', 'Este podcast explora a prática avaliativa em Terapia Ocupacional no Brasil, tomando como base um artigo de 2021. Ele identifica a lacuna entre a pesquisa e a aplicação clínica de instrumentos de avaliação para crianças e adolescentes.', 'uploads/audios/analise-comportamento/avaliacao-na-terapia-ocupacional-infantil-e-juvenil/podcast_1747803214_682d5c4e1a122.mp3', 0, NULL, NULL, '2025-05-21 04:53:34', 'restrito_assinantes', NULL, 'episodio-5-o-futuro-da-avaliacao-em-terapia-ocupacional', 0, 0, 1),
(25, 435, 'Episódio 1: O que é avaliação funcional?', 'Para que serve e por que é um passo fundamental antes de qualquer intervenção comportamental.', 'uploads/audios/analise-comportamento/assunto-2-avaliacao-funcional-do-comportamento/podcast_1747804108_682d5fcc1caa1.mp3', 0, NULL, NULL, '2025-05-21 05:08:28', 'restrito_assinantes', NULL, 'episodio-1-o-que-e-avaliacao-funcional', 0, 0, 1),
(26, 435, 'Episódio 2: Identificando funções do comportamento: fugir, pedir atenção ou conseguir algo', 'Aprenda a identificar o que a criança “ganha” ou “evita” com determinados comportamentos.', 'uploads/audios/analise-comportamento/assunto-2-avaliacao-funcional-do-comportamento/podcast_1747804281_682d6079405f0.mp3', 0, NULL, NULL, '2025-05-21 05:11:21', 'restrito_assinantes', NULL, 'episodio-2-identificando-funcoes-do-comportamento-fugir-pedir-atencao-ou-conseguir-algo', 0, 0, 1),
(27, 435, 'Episódio 3: Ferramentas simples para quem está começando', 'Como usar registros, entrevistas e observação para montar um plano de intervenção, mesmo sendo aluno.', 'uploads/audios/analise-comportamento/assunto-2-avaliacao-funcional-do-comportamento/podcast_1747804366_682d60ce1cec9.mp3', 0, NULL, NULL, '2025-05-21 05:12:46', 'publico', NULL, 'episodio-3-ferramentas-simples-para-quem-esta-comecando', 0, 0, 1),
(28, 436, 'Episódio 1 – O que faz a Terapia Ocupacional na Neonatologia', 'Descubra o papel essencial do terapeuta ocupacional na neonatologia, como ele contribui para o desenvolvimento dos recém-nascidos e o apoio às famílias na UTI Neonatal.', 'uploads/podcasts/audio/podcast_audio_683788dfca04c_1748469983.m4a', 0, NULL, NULL, '2025-05-28 22:06:23', 'restrito_assinantes', NULL, 'episo-dio-1-o-que-faz-a-terapia-ocupacional-na-neonatologia', 0, 0, 1),
(29, 436, 'Episódio 4 – Apoio à amamentação e ao vínculo familiar  o papel do TO', 'Saiba como o posicionamento terapêutico auxilia no conforto, prevenção de complicações e promoção do desenvolvimento do bebê prematuro na UTI Neonatal.', 'uploads/podcasts/audio/podcast_audio_68378926b21fd_1748470054.m4a', 0, NULL, NULL, '2025-05-28 22:07:34', 'restrito_assinantes', NULL, 'episo-dio-4-apoio-a-amamentac-a-o-e-ao-vi-nculo-familiar-o-papel-do-to', 0, 0, 1),
(30, 436, 'Episódio 3 – Estimulação sensorial na UTI Neonatal quando  como e para quem', 'Entenda a importância da estimulação sensorial controlada na UTI Neonatal, quando deve ser realizada, como aplicar e quais bebês se beneficiam dessa prática.', 'uploads/podcasts/audio/podcast_audio_68378926b2e80_1748470054.m4a', 0, NULL, NULL, '2025-05-28 22:07:34', 'restrito_assinantes', NULL, 'episo-dio-3-estimulac-a-o-sensorial-na-uti-neonatal-quando-como-e-para-quem', 0, 0, 1),
(31, 436, 'Episódio 2 – Posicionamento terapêutico do recém nascido', 'Saiba como o posicionamento terapêutico auxilia no conforto, prevenção de complicações e promoção do desenvolvimento do bebê prematuro na UTI Neonatal.', 'uploads/podcasts/audio/podcast_audio_68378926b4150_1748470054.m4a', 0, NULL, NULL, '2025-05-28 22:07:34', 'restrito_assinantes', NULL, 'episo-dio-2-posicionamento-terape-utico-do-rece-m-nascido', 0, 0, 1),
(32, 436, 'Episódio 7 – Reflexos primitivos  o que observar e como intervir', 'Quais são os reflexos primitivos do recém-nascido, por que existem e em que momento devem desaparecer; orientações para registrar, monitorar e aplicar exercícios que integrem esses reflexos ao controle motor voluntário.', 'uploads/podcasts/audio/podcast_audio_6837d79d06a87_1748490141.m4a', 0, NULL, NULL, '2025-05-29 03:42:21', 'restrito_assinantes', NULL, 'episo-dio-7-reflexos-primitivos-o-que-observar-e-como-intervir', 0, 0, 1),
(33, 436, 'Episódio 6 – Cuidados com o desenvolvimento motor do recém nascido prematuro', 'Estratégias práticas para estimular marcos motores precoces (tummy time, uso de rolinhos, brinquedos de contraste), avaliação dos progressos e envolvimento ativo da família nas rotinas de estimulação.', 'uploads/podcasts/audio/podcast_audio_6837d79d07bcb_1748490141.m4a', 0, NULL, NULL, '2025-05-29 03:42:21', 'restrito_assinantes', NULL, 'episo-dio-6-cuidados-com-o-desenvolvimento-motor-do-rece-m-nascido-prematuro', 0, 0, 1),
(34, 436, 'Episódio 5 – Alta hospitalar  preparando a família para o cuidado em casa', 'Como o terapeuta ocupacional orienta e capacita os pais para a transição do ambiente hospitalar ao domicílio, incluindo adaptações de espaço, identificação de sinais de alerta e articulação da rede de apoio.', 'uploads/podcasts/audio/podcast_audio_6837d79d088fd_1748490141.m4a', 0, NULL, NULL, '2025-05-29 03:42:21', 'restrito_assinantes', NULL, 'episo-dio-5-alta-hospitalar-preparando-a-fami-lia-para-o-cuidado-em-casa', 0, 0, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `podcast_tags`
--

CREATE TABLE `podcast_tags` (
  `id_podcast_tag` int(11) NOT NULL,
  `id_podcast` int(11) NOT NULL,
  `id_tag` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `posicao_reproducao_utilizador`
--

CREATE TABLE `posicao_reproducao_utilizador` (
  `id_posicao` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `id_podcast` int(11) NOT NULL,
  `posicao_segundos` float NOT NULL,
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `posicao_reproducao_utilizador`
--

INSERT INTO `posicao_reproducao_utilizador` (`id_posicao`, `id_utilizador`, `id_podcast`, `posicao_segundos`, `data_atualizacao`) VALUES
(1, 1, 19, 140.23, '2025-05-21 05:27:13'),
(167, 1, 20, 18.7188, '2025-05-21 04:06:40'),
(170, 1, 21, 90.2228, '2025-05-21 04:30:18'),
(174, 1, 23, 408.384, '2025-05-25 04:52:43'),
(243, 26, 24, 1.74693, '2025-05-26 05:36:43'),
(246, 26, 27, 5.59103, '2025-05-31 01:12:47'),
(287, 26, 21, 34.533, '2025-05-26 05:35:34'),
(343, 1, 24, 1.19916, '2025-05-29 03:55:02');

-- --------------------------------------------------------

--
-- Estrutura para tabela `preferencias_notificacao`
--

CREATE TABLE `preferencias_notificacao` (
  `id_preferencia` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `notificar_novos_podcasts` tinyint(1) DEFAULT 1,
  `notificar_novas_oportunidades` tinyint(1) DEFAULT 1,
  `notificar_noticias_plataforma` tinyint(1) DEFAULT 0,
  `data_ultima_modificacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tags`
--

CREATE TABLE `tags` (
  `id_tag` int(11) NOT NULL,
  `nome_tag` varchar(100) NOT NULL,
  `slug_tag` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `utilizadores`
--

CREATE TABLE `utilizadores` (
  `id_utilizador` int(11) NOT NULL,
  `nome_completo` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `palavra_passe` varchar(255) NOT NULL,
  `profissao` varchar(100) DEFAULT NULL,
  `crefito` varchar(50) DEFAULT NULL,
  `avatar_url` varchar(512) DEFAULT NULL,
  `funcao` enum('utilizador','administrador') NOT NULL DEFAULT 'utilizador',
  `id_plano_assinatura_ativo` int(11) DEFAULT NULL,
  `status_sistema` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `data_registo` timestamp NULL DEFAULT current_timestamp(),
  `data_ultima_modificacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `token_reset_passe` varchar(255) DEFAULT NULL,
  `data_expiracao_token_reset` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `utilizadores`
--

INSERT INTO `utilizadores` (`id_utilizador`, `nome_completo`, `email`, `palavra_passe`, `profissao`, `crefito`, `avatar_url`, `funcao`, `id_plano_assinatura_ativo`, `status_sistema`, `data_registo`, `data_ultima_modificacao`, `token_reset_passe`, `data_expiracao_token_reset`) VALUES
(1, 'Bruno Perdigão Alexandrino', 'admin@audioto.com.br', '$2y$10$poLZjbQET0GmtVy1wmkZfeoDKW/ZV879LBkOFHJZGE1SG/UFPhSbu', '', '', NULL, 'administrador', 1, 'ativo', '2025-05-16 01:55:10', '2025-05-29 03:54:39', NULL, NULL),
(26, 'Erick Tedros', 'ericktedros@gmail.com', '$2y$10$.SemO/mcfofJM6ly3oCpn.SBN1y7rswABB10tZia0NnS9Jw8tfUrK', NULL, NULL, NULL, 'utilizador', 2, 'ativo', '2025-05-25 04:36:45', '2025-05-31 02:04:00', NULL, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `assinaturas_utilizador`
--
ALTER TABLE `assinaturas_utilizador`
  ADD PRIMARY KEY (`id_assinatura`),
  ADD KEY `id_utilizador` (`id_utilizador`),
  ADD KEY `id_plano` (`id_plano`);

--
-- Índices de tabela `assuntos_podcast`
--
ALTER TABLE `assuntos_podcast`
  ADD PRIMARY KEY (`id_assunto`),
  ADD UNIQUE KEY `slug_assunto` (`slug_assunto`),
  ADD KEY `id_categoria` (`id_categoria`);

--
-- Índices de tabela `audioto_emails`
--
ALTER TABLE `audioto_emails`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `avaliacoes_podcast`
--
ALTER TABLE `avaliacoes_podcast`
  ADD PRIMARY KEY (`id_avaliacao`),
  ADD UNIQUE KEY `idx_podcast_utilizador_avaliacao` (`id_podcast`,`id_utilizador`),
  ADD KEY `id_utilizador` (`id_utilizador`);

--
-- Índices de tabela `categorias_podcast`
--
ALTER TABLE `categorias_podcast`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nome_categoria` (`nome_categoria`),
  ADD UNIQUE KEY `slug_categoria` (`slug_categoria`);

--
-- Índices de tabela `comentarios_conteudo`
--
ALTER TABLE `comentarios_conteudo`
  ADD PRIMARY KEY (`id_comentario`),
  ADD KEY `id_utilizador` (`id_utilizador`),
  ADD KEY `id_comentario_pai` (`id_comentario_pai`);

--
-- Índices de tabela `curtidas_conteudo`
--
ALTER TABLE `curtidas_conteudo`
  ADD PRIMARY KEY (`id_curtida`),
  ADD UNIQUE KEY `uq_utilizador_curtida_conteudo` (`id_utilizador`,`tipo_conteudo`,`id_conteudo`);

--
-- Índices de tabela `favoritos`
--
ALTER TABLE `favoritos`
  ADD PRIMARY KEY (`id_favorito`),
  ADD UNIQUE KEY `uq_utilizador_conteudo_favorito` (`id_utilizador`,`tipo_conteudo`,`id_conteudo`);

--
-- Índices de tabela `favoritos_oportunidade`
--
ALTER TABLE `favoritos_oportunidade`
  ADD PRIMARY KEY (`id_favorito`),
  ADD UNIQUE KEY `id_utilizador` (`id_utilizador`,`id_oportunidade`);

--
-- Índices de tabela `fila_reproducao_utilizador`
--
ALTER TABLE `fila_reproducao_utilizador`
  ADD PRIMARY KEY (`id_fila`),
  ADD UNIQUE KEY `idx_utilizador_podcast_fila` (`id_utilizador`,`id_podcast`),
  ADD KEY `id_podcast` (`id_podcast`);

--
-- Índices de tabela `noticias`
--
ALTER TABLE `noticias`
  ADD PRIMARY KEY (`id_noticia`),
  ADD UNIQUE KEY `slug_noticia_unique` (`slug_noticia`),
  ADD KEY `idx_data_publicacao` (`data_publicacao`),
  ADD KEY `idx_categoria_noticia` (`categoria_noticia`),
  ADD KEY `fk_noticias_utilizador` (`id_utilizador_autor`);

--
-- Índices de tabela `oportunidades`
--
ALTER TABLE `oportunidades`
  ADD PRIMARY KEY (`id_oportunidade`);

--
-- Índices de tabela `planos_assinatura`
--
ALTER TABLE `planos_assinatura`
  ADD PRIMARY KEY (`id_plano`);

--
-- Índices de tabela `podcasts`
--
ALTER TABLE `podcasts`
  ADD PRIMARY KEY (`id_podcast`),
  ADD UNIQUE KEY `slug_podcast` (`slug_podcast`),
  ADD KEY `id_assunto` (`id_assunto`),
  ADD KEY `id_plano_minimo` (`id_plano_minimo`);

--
-- Índices de tabela `podcast_tags`
--
ALTER TABLE `podcast_tags`
  ADD PRIMARY KEY (`id_podcast_tag`),
  ADD UNIQUE KEY `idx_podcast_tag_unique` (`id_podcast`,`id_tag`),
  ADD KEY `id_tag` (`id_tag`);

--
-- Índices de tabela `posicao_reproducao_utilizador`
--
ALTER TABLE `posicao_reproducao_utilizador`
  ADD PRIMARY KEY (`id_posicao`),
  ADD UNIQUE KEY `id_utilizador` (`id_utilizador`,`id_podcast`),
  ADD KEY `id_podcast` (`id_podcast`);

--
-- Índices de tabela `preferencias_notificacao`
--
ALTER TABLE `preferencias_notificacao`
  ADD PRIMARY KEY (`id_preferencia`),
  ADD UNIQUE KEY `id_utilizador` (`id_utilizador`);

--
-- Índices de tabela `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id_tag`),
  ADD UNIQUE KEY `nome_tag` (`nome_tag`),
  ADD UNIQUE KEY `slug_tag` (`slug_tag`);

--
-- Índices de tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  ADD PRIMARY KEY (`id_utilizador`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_utilizador_plano_ativo` (`id_plano_assinatura_ativo`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `assinaturas_utilizador`
--
ALTER TABLE `assinaturas_utilizador`
  MODIFY `id_assinatura` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de tabela `assuntos_podcast`
--
ALTER TABLE `assuntos_podcast`
  MODIFY `id_assunto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=437;

--
-- AUTO_INCREMENT de tabela `audioto_emails`
--
ALTER TABLE `audioto_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT de tabela `avaliacoes_podcast`
--
ALTER TABLE `avaliacoes_podcast`
  MODIFY `id_avaliacao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT de tabela `categorias_podcast`
--
ALTER TABLE `categorias_podcast`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT de tabela `comentarios_conteudo`
--
ALTER TABLE `comentarios_conteudo`
  MODIFY `id_comentario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de tabela `curtidas_conteudo`
--
ALTER TABLE `curtidas_conteudo`
  MODIFY `id_curtida` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT de tabela `favoritos`
--
ALTER TABLE `favoritos`
  MODIFY `id_favorito` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de tabela `favoritos_oportunidade`
--
ALTER TABLE `favoritos_oportunidade`
  MODIFY `id_favorito` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `fila_reproducao_utilizador`
--
ALTER TABLE `fila_reproducao_utilizador`
  MODIFY `id_fila` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `noticias`
--
ALTER TABLE `noticias`
  MODIFY `id_noticia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `oportunidades`
--
ALTER TABLE `oportunidades`
  MODIFY `id_oportunidade` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT de tabela `planos_assinatura`
--
ALTER TABLE `planos_assinatura`
  MODIFY `id_plano` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `podcasts`
--
ALTER TABLE `podcasts`
  MODIFY `id_podcast` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT de tabela `podcast_tags`
--
ALTER TABLE `podcast_tags`
  MODIFY `id_podcast_tag` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `posicao_reproducao_utilizador`
--
ALTER TABLE `posicao_reproducao_utilizador`
  MODIFY `id_posicao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=355;

--
-- AUTO_INCREMENT de tabela `preferencias_notificacao`
--
ALTER TABLE `preferencias_notificacao`
  MODIFY `id_preferencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de tabela `tags`
--
ALTER TABLE `tags`
  MODIFY `id_tag` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  MODIFY `id_utilizador` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `assinaturas_utilizador`
--
ALTER TABLE `assinaturas_utilizador`
  ADD CONSTRAINT `assinaturas_utilizador_ibfk_1` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE CASCADE,
  ADD CONSTRAINT `assinaturas_utilizador_ibfk_2` FOREIGN KEY (`id_plano`) REFERENCES `planos_assinatura` (`id_plano`);

--
-- Restrições para tabelas `assuntos_podcast`
--
ALTER TABLE `assuntos_podcast`
  ADD CONSTRAINT `assuntos_podcast_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias_podcast` (`id_categoria`) ON DELETE CASCADE;

--
-- Restrições para tabelas `avaliacoes_podcast`
--
ALTER TABLE `avaliacoes_podcast`
  ADD CONSTRAINT `avaliacoes_podcast_ibfk_1` FOREIGN KEY (`id_podcast`) REFERENCES `podcasts` (`id_podcast`) ON DELETE CASCADE,
  ADD CONSTRAINT `avaliacoes_podcast_ibfk_2` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE CASCADE;

--
-- Restrições para tabelas `comentarios_conteudo`
--
ALTER TABLE `comentarios_conteudo`
  ADD CONSTRAINT `comentarios_conteudo_ibfk_1` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE CASCADE,
  ADD CONSTRAINT `comentarios_conteudo_ibfk_2` FOREIGN KEY (`id_comentario_pai`) REFERENCES `comentarios_conteudo` (`id_comentario`) ON DELETE CASCADE;

--
-- Restrições para tabelas `curtidas_conteudo`
--
ALTER TABLE `curtidas_conteudo`
  ADD CONSTRAINT `curtidas_conteudo_ibfk_1` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE CASCADE;

--
-- Restrições para tabelas `favoritos`
--
ALTER TABLE `favoritos`
  ADD CONSTRAINT `favoritos_ibfk_1` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE CASCADE;

--
-- Restrições para tabelas `fila_reproducao_utilizador`
--
ALTER TABLE `fila_reproducao_utilizador`
  ADD CONSTRAINT `fila_reproducao_utilizador_ibfk_1` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE CASCADE,
  ADD CONSTRAINT `fila_reproducao_utilizador_ibfk_2` FOREIGN KEY (`id_podcast`) REFERENCES `podcasts` (`id_podcast`) ON DELETE CASCADE;

--
-- Restrições para tabelas `noticias`
--
ALTER TABLE `noticias`
  ADD CONSTRAINT `fk_noticias_utilizador` FOREIGN KEY (`id_utilizador_autor`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `podcasts`
--
ALTER TABLE `podcasts`
  ADD CONSTRAINT `podcasts_ibfk_1` FOREIGN KEY (`id_assunto`) REFERENCES `assuntos_podcast` (`id_assunto`) ON DELETE CASCADE,
  ADD CONSTRAINT `podcasts_ibfk_2` FOREIGN KEY (`id_plano_minimo`) REFERENCES `planos_assinatura` (`id_plano`) ON DELETE SET NULL;

--
-- Restrições para tabelas `podcast_tags`
--
ALTER TABLE `podcast_tags`
  ADD CONSTRAINT `podcast_tags_ibfk_1` FOREIGN KEY (`id_podcast`) REFERENCES `podcasts` (`id_podcast`) ON DELETE CASCADE,
  ADD CONSTRAINT `podcast_tags_ibfk_2` FOREIGN KEY (`id_tag`) REFERENCES `tags` (`id_tag`) ON DELETE CASCADE;

--
-- Restrições para tabelas `posicao_reproducao_utilizador`
--
ALTER TABLE `posicao_reproducao_utilizador`
  ADD CONSTRAINT `posicao_reproducao_utilizador_ibfk_1` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE CASCADE,
  ADD CONSTRAINT `posicao_reproducao_utilizador_ibfk_2` FOREIGN KEY (`id_podcast`) REFERENCES `podcasts` (`id_podcast`) ON DELETE CASCADE;

--
-- Restrições para tabelas `preferencias_notificacao`
--
ALTER TABLE `preferencias_notificacao`
  ADD CONSTRAINT `preferencias_notificacao_ibfk_1` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE CASCADE;

--
-- Restrições para tabelas `utilizadores`
--
ALTER TABLE `utilizadores`
  ADD CONSTRAINT `fk_utilizador_plano_ativo` FOREIGN KEY (`id_plano_assinatura_ativo`) REFERENCES `planos_assinatura` (`id_plano`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
