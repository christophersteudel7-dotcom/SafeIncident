-- Garante que acentos sejam lidos corretamente, qualquer que seja o programa usado para executar
SET NAMES utf8mb4;

-- Cria o banco (se ainda não existir) com suporte a acentos
CREATE DATABASE IF NOT EXISTS safeincident
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE safeincident;

-- Usuários (profissionais de TI/Segurança com acesso ao sistema)
CREATE TABLE usuarios (
  id_usuario   INT AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(100) NOT NULL,
  email        VARCHAR(150) NOT NULL UNIQUE,
  senha_hash   VARCHAR(255) NOT NULL,
  departamento VARCHAR(100)
);

-- Tipos de ataque (lista que pode crescer com o tempo)
CREATE TABLE tipos_ataque (
  id_tipo INT AUTO_INCREMENT PRIMARY KEY,
  nome    VARCHAR(50) NOT NULL UNIQUE
);

-- Incidentes de segurança
CREATE TABLE incidentes (
  id_incidente        INT AUTO_INCREMENT PRIMARY KEY,
  titulo              VARCHAR(150) NOT NULL,
  id_tipo             INT NOT NULL,
  sistema_afetado     VARCHAR(100) NOT NULL,
  prioridade          ENUM('Baixa','Media','Alta','Critica') NOT NULL,
  descricao           TEXT NOT NULL,
  data_ocorrencia     DATE NOT NULL,
  data_criacao        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_resolucao      DATETIME NULL,
  excluido_em         DATETIME NULL,
  status              ENUM('Pendente','Em andamento','Resolvido') NOT NULL DEFAULT 'Pendente',
  id_usuario_registro INT NOT NULL,
  FOREIGN KEY (id_tipo) REFERENCES tipos_ataque(id_tipo),
  FOREIGN KEY (id_usuario_registro) REFERENCES usuarios(id_usuario)
);

-- Dados iniciais: tipos de ataque
INSERT INTO tipos_ataque (nome) VALUES
  ('Phishing'),
  ('Malware'),
  ('Ransomware'),
  ('Engenharia Social'),
  ('Acesso Não Autorizado'),
  ('Vazamento de Dados'),
  ('Outro');
