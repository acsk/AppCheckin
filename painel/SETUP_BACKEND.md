# Setup Backend - Tabelas Necessárias

## ⚠️ Erro ao Carregar Turmas (HTTP 500)

O frontend está completo, mas o backend precisa das seguintes tabelas no banco de dados.

### **1️⃣ Tabela: `professores`**

```sql
CREATE TABLE IF NOT EXISTS professores (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  nome VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE,
  telefone VARCHAR(20),
  cpf VARCHAR(14) UNIQUE,
  foto_url VARCHAR(500),
  ativo BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant(tenant_id),
  INDEX idx_ativo(ativo)
);
```

### **2️⃣ Tabela: `turmas` (Aulas)**

```sql
CREATE TABLE IF NOT EXISTS turmas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  nome VARCHAR(255) NOT NULL,
  professor_id INT NOT NULL,
  modalidade_id INT NOT NULL,
  dia_id INT,
  horario_id INT,
  limite_alunos INT DEFAULT 30,
  ativo BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE RESTRICT,
  FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE RESTRICT,
  INDEX idx_tenant(tenant_id),
  INDEX idx_professor(professor_id),
  INDEX idx_modalidade(modalidade_id),
  INDEX idx_ativo(ativo)
);
```

### **3️⃣ Tabela: `dias` (Dias da Semana)**

```sql
CREATE TABLE IF NOT EXISTS dias (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(50) NOT NULL UNIQUE,
  dia_semana INT DEFAULT 0
);

-- Inserir dados
INSERT INTO dias (nome, dia_semana) VALUES
('Segunda-feira', 1),
('Terça-feira', 2),
('Quarta-feira', 3),
('Quinta-feira', 4),
('Sexta-feira', 5),
('Sábado', 6);
```

### **4️⃣ Tabela: `horarios` (Horários)**

```sql
CREATE TABLE IF NOT EXISTS horarios (
  id INT PRIMARY KEY AUTO_INCREMENT,
  hora TIME NOT NULL UNIQUE,
  descricao VARCHAR(100)
);

-- Inserir dados
INSERT INTO horarios (hora, descricao) VALUES
('06:00:00', '06:00'),
('07:00:00', '07:00'),
('08:00:00', '08:00'),
('09:00:00', '09:00'),
('10:00:00', '10:00'),
('11:00:00', '11:00'),
('14:00:00', '14:00'),
('15:00:00', '15:00'),
('16:00:00', '16:00'),
('17:00:00', '17:00'),
('18:00:00', '18:00'),
('19:00:00', '19:00'),
('20:00:00', '20:00');
```

---

## 📋 Endpoints Necessários no Backend

### **Professores**

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/admin/professores` | Listar professores |
| `GET` | `/admin/professores/{id}` | Buscar professor por ID |
| `POST` | `/admin/professores` | Criar professor |
| `PUT` | `/admin/professores/{id}` | Atualizar professor |
| `DELETE` | `/admin/professores/{id}` | Deletar professor |

### **Turmas**

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/admin/turmas` | Listar turmas |
| `GET` | `/admin/turmas/{id}` | Buscar turma por ID |
| `POST` | `/admin/turmas` | Criar turma |
| `PUT` | `/admin/turmas/{id}` | Atualizar turma |
| `DELETE` | `/admin/turmas/{id}` | Deletar turma |
| `GET` | `/admin/turmas/{id}/vagas` | Verificar vagas disponíveis |
| `GET` | `/admin/professores/{id}/turmas` | Listar turmas de um professor |

---

## ✅ Resposta Esperada

### **GET /admin/turmas**
```json
{
  "success": true,
  "turmas": [
    {
      "id": 1,
      "nome": "Yoga Segunda",
      "professor_id": 1,
      "professor_nome": "João Silva",
      "modalidade_id": 2,
      "dia_id": 1,
      "dia_nome": "Segunda-feira",
      "horario_id": 3,
      "horario_hora": "08:00:00",
      "limite_alunos": 30,
      "alunos_count": 15,
      "ativo": true,
      "created_at": "2026-01-09T10:00:00Z"
    }
  ]
}
```

### **POST /admin/turmas** (Criar)
```json
{
  "nome": "Yoga Segunda",
  "professor_id": 1,
  "modalidade_id": 2,
  "dia_id": 1,
  "horario_id": 3,
  "limite_alunos": 30,
  "ativo": true
}
```

---

## 🔄 Próximos Passos

1. ✅ Criar as tabelas no banco de dados usando os SQLs acima
2. ✅ Criar os controllers no backend (`ProfessorController`, `TurmaController`)
3. ✅ Implementar os endpoints listados
4. 🔄 Testar a integração no frontend
5. 🔄 Criar features de inscrição de alunos em turmas

---

**Nota**: O frontend já está 100% pronto. Todos os formulários, validações, busca e tabelas estão funcionando. Agora basta o backend ter as tabelas e endpoints implementados.
