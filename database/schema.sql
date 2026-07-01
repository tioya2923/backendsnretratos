-- Schema da base de dados do backend SNRetratos (Paróquia de São Nicolau).
--
-- Reconstruído a partir de todas as queries SQL existentes no código PHP
-- (backend-sn/components/*.php) em 2026-07-01, porque a base de dados de
-- produção original deixou de existir (app Heroku "snref-backend" foi
-- removida) e não havia nenhum dump/backup disponível.
--
-- Os nomes de tabelas e colunas têm de corresponder EXATAMENTE aos usados
-- no código (incluindo maiúsculas em `Grupos`/`Membros`), porque o MySQL
-- no Linux é case-sensitive para nomes de tabelas por omissão.
--
-- Aplicar com:
--   mysql --ssl-ca=ca.pem -h HOST -P PORTA -u avnadmin -p defaultdb < database/schema.sql

SET NAMES utf8mb4;

-- ── Utilizadores e administradores ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(20) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    approval_code VARCHAR(64) DEFAULT NULL,
    token VARCHAR(64) DEFAULT NULL,
    data_aniversario DATE DEFAULT NULL,
    data_aniversario_sacerdotal DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email),
    INDEX idx_token (token)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Nota: numa base de dados já existente sem estas colunas, correr manualmente
--   ALTER TABLE usuarios ADD COLUMN data_aniversario DATE DEFAULT NULL,
--                         ADD COLUMN data_aniversario_sacerdotal DATE DEFAULT NULL;
-- (esta versão do MySQL/Aiven não aceita ADD COLUMN IF NOT EXISTS combinado)

-- Idempotência dos avisos de aniversário (um aviso por utilizador, ano e tipo)
CREATE TABLE IF NOT EXISTS aniversario_avisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ano INT NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_ano_tipo (user_id, ano, tipo),
    CONSTRAINT fk_aniversario_avisos_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    name_admin VARCHAR(255) NOT NULL,
    email_admin VARCHAR(255) NOT NULL,
    password_admin VARCHAR(255) NOT NULL,
    is_super TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email_admin (email_admin)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ── Nomes (aniversários e confissões) ────────────────────────────────────

CREATE TABLE IF NOT EXISTS nomes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(255) NOT NULL,
    data_aniversario DATE DEFAULT NULL,
    data_aniversario_sacerdotal DATE DEFAULT NULL,
    UNIQUE KEY unique_nome_completo (nome_completo)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nomes_predefinidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY unique_nome (nome)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dia_semana VARCHAR(20) NOT NULL,
    horario_inicio VARCHAR(10) NOT NULL,
    horario_fim VARCHAR(10) NOT NULL,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    data DATE NOT NULL,
    INDEX idx_data (data)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ── Fotos / vídeos (metadados; ficheiros ficam no S3) ────────────────────

CREATE TABLE IF NOT EXISTS pastas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    arquivo VARCHAR(500) NOT NULL,
    tipo VARCHAR(10) NOT NULL,
    pasta_id INT NOT NULL,
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pasta (pasta_id),
    CONSTRAINT fk_fotos_pasta FOREIGN KEY (pasta_id) REFERENCES pastas(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ── Grupos e membros (nomes com maiúscula inicial — usados assim no código) ──

CREATE TABLE IF NOT EXISTS Grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_grupo VARCHAR(255) NOT NULL,
    UNIQUE KEY unique_nome_grupo (nome_grupo)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Membros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_membro VARCHAR(255) NOT NULL,
    grupo_id INT NOT NULL,
    INDEX idx_grupo (grupo_id),
    CONSTRAINT fk_membros_grupo FOREIGN KEY (grupo_id) REFERENCES Grupos(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS refeicoes_grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    tipo_refeicao VARCHAR(50) NOT NULL,
    data_refeicao DATE NOT NULL,
    hora_refeicao TIME NOT NULL,
    local_refeicao VARCHAR(255) NOT NULL,
    notificado TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_grupo (grupo_id),
    INDEX idx_data (data_refeicao, hora_refeicao),
    CONSTRAINT fk_refeicoes_grupos_grupo FOREIGN KEY (grupo_id) REFERENCES Grupos(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ── Inscrições em refeições ───────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS refeicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(255) NOT NULL,
    data DATE NOT NULL,
    levar_refeicao TINYINT(1) NOT NULL DEFAULT 0,
    almoco TINYINT(1) NOT NULL DEFAULT 0,
    almoco_mais_cedo TINYINT(1) NOT NULL DEFAULT 0,
    almoco_mais_tarde TINYINT(1) NOT NULL DEFAULT 0,
    jantar TINYINT(1) NOT NULL DEFAULT 0,
    jantar_mais_cedo TINYINT(1) NOT NULL DEFAULT 0,
    jantar_mais_tarde TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_nome_data (nome_completo, data)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ── Código secreto de acesso (verifyCode.php) ────────────────────────────

CREATE TABLE IF NOT EXISTS codigosecreto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    verificacao VARCHAR(255) NOT NULL,
    UNIQUE KEY unique_verificacao (verificacao)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Depois de criar, inserir o código secreto real, ex.:
-- INSERT INTO codigosecreto (verificacao) VALUES ('o-teu-codigo-aqui');

-- ── Tabelas que a própria app cria automaticamente no arranque ──────────
-- (copiadas aqui, verbatim, apenas para a BD ficar completa num único
--  passo; o código também as cria com CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS atividades_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(100),
    data_atividade DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultima_notificacao DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_data (data_atividade, hora_inicio, ativo)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id VARCHAR(40) DEFAULT NULL,
    remetente_id INT NOT NULL,
    destinatario_id INT DEFAULT NULL,
    corpo TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dest (destinatario_id),
    INDEX idx_rem (remetente_id),
    INDEX idx_grupo (grupo_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensagem_leituras (
    mensagem_id INT NOT NULL,
    utilizador_id INT NOT NULL,
    PRIMARY KEY (mensagem_id, utilizador_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensagem_lembretes (
    mensagem_id INT NOT NULL,
    utilizador_id INT NOT NULL,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (mensagem_id, utilizador_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lembretes_enviados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data DATE NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_data_tipo (data, tipo)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(512) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_endpoint (endpoint(500))
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
