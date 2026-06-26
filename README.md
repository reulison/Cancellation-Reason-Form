# Formulário de Cancelamento/Pausa — Condropure

Formulário em PHP para solicitar pausa ou cancelamento de assinatura do produto Condropure, com integração à API Klaviyo, validação de cliente e máscara de telefone (formato Brasil).

## Como funciona

- A página `index.php` exibe o formulário e aceita parâmetros via URL: `name`, `email`, `phone` e `cancel_type`.
- Se `name`, `email` ou `phone` forem passados como query params, os campos são preenchidos automaticamente, escondidos do usuário e o cumprimento usa o primeiro nome.
- `cancel_type` aceita `pause` ou `cancellation` (padrão `cancellation`). O texto e mensagens mudam conforme o tipo.
- Validação cliente: nome, email, telefone (DDI/DDD Brasil) e motivo obrigatórios.
- Máscara de telefone em tempo real: aceita números com ou sem DDI 55, formata para `+55 (AA) NNNNN-NNNN` ou `(AA) NNNN-NNNN`.
- Ao enviar, o servidor chama a API Create/Update Profile do Klaviyo usando a chave em `KLAVIYO_API_KEY`.
- Resposta do Klaviyo exibida em JSON para debug.

## Instalação rápida

1. Coloque o arquivo `index.php` no seu servidor PHP (pasta pública) e adicione `.env` com suas variáveis de ambiente, ou defina `KLAVIYO_API_KEY` no servidor.
2. Defina a variável de ambiente `KLAVIYO_API_KEY` com sua chave privada do Klaviyo.
   - Exemplo (Linux/macOS): `export KLAVIYO_API_KEY=sk_...`
   - No Windows PowerShell: `$env:KLAVIYO_API_KEY = 'sk_...'`
   - Ou crie um arquivo `.env` (não commit para Git — veja `.gitignore`).

3. Acesse a página com parâmetros opcionais de pré-preenchimento:

### Exemplos de URLs

**Cancelamento com pré-preenchimento:**
```
https://exemplo.com.br/condropure-cancel/?name=Joao%20Silva&email=joao%40exemplo.com&phone=5511999999999&cancel_type=cancellation
```

**Pausa com pré-preenchimento:**
```
https://exemplo.com.br/condropure-cancel/?name=Maria%20Silva&email=maria%40exemplo.com&phone=5521987654321&cancel_type=pause
```

**Sem pré-preenchimento (formulário vazio):**
```
https://exemplo.com.br/condropure-cancel/
```

## Arquivos

- `index.php` — Aplicação completa em um único arquivo, com formulário, validação cliente, máscara de telefone e integração Klaviyo.
- `.env.example` — Exemplo de variáveis de ambiente.
- `.gitignore` — Ignora `.env` (segredo) e pastas desnecessárias.
- `README.md` — Este arquivo.

## Detalhes técnicos

- **Endpoint Klaviyo:** `https://a.klaviyo.com/api/profile-import`
- **Método:** POST com cabeçalhos `Accept`, `Content-Type`, `Authorization` e `Revision`.
- **Payload:** Envia `email`, `phone_number`, `first_name`, `last_name` e `properties` (cancel_type, cancel_reason, product).
- **Resposta:** Retorna status HTTP e body JSON decodificado ou bruto, útil para debug.

## Desenvolvimento local

```bash
# Clone o repositório
git clone <repo-url>
cd "Cancellation Reason Form"

# Configure a variável de ambiente
export KLAVIYO_API_KEY='sk_..'

# Rode um servidor PHP local (requer PHP 7.4+)
php -S localhost:8000

# Acesse http://localhost:8000
```

## Observações

- Personalize o armazenamento/gravação do motivo do cancelamento conforme sua necessidade (banco de dados, webhook, ticket).
- O formulário envia dados apenas se email ou telefone forem informados; caso contrário, retorna erro.
- Certifique-se de que sua chave Klaviyo possui permissão para Create/Update Profiles.

