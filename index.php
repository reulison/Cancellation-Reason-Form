<?php

$envPath = __DIR__ . '/.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse key and value
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Remove wrapping quotes if present
        $value = trim($value, '"\'');

        // Force variable into system environment
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// Read and sanitize URL parameters
$url_name  = htmlspecialchars(trim($_GET['name']  ?? ''), ENT_QUOTES, 'UTF-8');
$url_email = htmlspecialchars(trim($_GET['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$url_phone = htmlspecialchars(trim($_GET['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
$cancel_type = trim($_GET['cancel_type'] ?? 'cancellation');
if (!in_array($cancel_type, ['cancellation', 'pause'], true)) {
    $cancel_type = 'cancellation';
}

$has_name  = $url_name  !== '';
$has_email = $url_email !== '';
$has_phone = $url_phone !== '';

$success = false;
$error   = '';
$submitted_reason = '';

$reasons = [
    'Não sabia que era uma assinatura',
    'Estou com produto acumulado em casa',
    'Questões financeiras',
    'Meu pet não aceitou o produto',
    'Prefiro comprar quando precisar (avulso)',
    'Recebi produto em duplicidade / erro no plano',
    'Por orientação do veterinário',
    'Meu pet faleceu',
    'Outro motivo',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_name   = trim($_POST['name']   ?? $url_name);
    $form_email  = trim($_POST['email']  ?? $url_email);
    $form_phone  = trim($_POST['phone']  ?? $url_phone);
    $form_reason = trim($_POST['reason'] ?? '');

    if (empty($form_email) || empty($form_reason)) {
        $error = 'Por favor, preencha todos os campos obrigatórios.';
    } elseif (!filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, informe um e-mail válido.';
    } else {
        // Format phone to E.164
        $formatted_phone = '';
        if (!empty($form_phone)) {
            $digits = preg_replace('/\D/', '', $form_phone);
            if (str_starts_with(ltrim($form_phone), '+')) {
                $formatted_phone = '+' . $digits;
            } else {
                if (strlen($digits) <= 11) {
                    $formatted_phone = '+55' . $digits;
                } else {
                    $formatted_phone = '+' . $digits;
                }
            }
        }

        // Split name
        $name_parts = explode(' ', $form_name, 2);
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';

        // Build Klaviyo payload
        $attributes = [
            'email'      => $form_email,
            'properties' => [
                'cancellation_reason'            => $form_reason,
                'product'                        => 'Condropure',
            ],
        ];
        if (!empty($first_name)) $attributes['first_name'] = $first_name;
        if (!empty($last_name))  $attributes['last_name']  = $last_name;
        if (!empty($formatted_phone)) $attributes['phone_number'] = $formatted_phone;

        $payload = json_encode([
            'data' => [
                'type'       => 'profile',
                'attributes' => $attributes,
            ],
        ]);

        $api_key = getenv('KLAVIYO_API_KEY') ?: '';
        $ch = curl_init('https://a.klaviyo.com/api/profile-import/');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Klaviyo-API-Key ' . $api_key,
                'Content-Type: application/json',
                'Accept: application/json',
                'revision: 2024-10-15',
            ],
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            $success = true;
            $submitted_reason = $form_reason;
        } else {
            $error = 'Ocorreu um erro ao processar sua solicitação. Por favor, tente novamente.';
        }
    }
}

$is_cancellation = $cancel_type === 'cancellation';
$first_name_display = '';
if ($has_name) {
    $parts = explode(' ', $url_name, 2);
    $first_name_display = $parts[0];
}

$page_title   = $is_cancellation ? 'Cancelamento de Assinatura – Condropure' : 'Pausa de Assinatura – Condropure';
$form_heading = $is_cancellation ? 'Cancelamento de Assinatura' : 'Pausa de Assinatura';
$form_sub     = $is_cancellation
    ? 'Sentimos muito que você deseja cancelar sua assinatura do Condropure. Por favor, nos conta o motivo para que possamos melhorar.'
    : 'Entendemos que às vezes precisamos de uma pausa. Por favor, nos conta o motivo para que possamos ajudar da melhor forma.';
$btn_label    = $is_cancellation ? 'Confirmar Cancelamento' : 'Confirmar Pausa';
$tag_label    = $is_cancellation ? 'Cancelamento' : 'Pausa';
$tag_color    = $is_cancellation ? '#dc2626' : '#d97706';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --yellow:  #FFCC03;
      --green:   #108474;
      --dark:    #111111;
      --gray:    #666666;
      --border:  #E5E7EB;
      --bg:      #F8F9FA;
      --white:   #FFFFFF;
      --red:     #dc2626;
      --radius:  10px;
    }

    html { font-size: 16px; }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg);
      color: var(--dark);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Header ── */
    header {
      background: var(--yellow);
      padding: 0 24px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 8px rgba(0,0,0,.08);
    }

    .logo-wrap {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
    }

    .logo-wrap img {
      height: 36px;
      width: auto;
    }

    .header-tag {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--white);
      border-radius: 20px;
      padding: 5px 14px;
      font-family: 'Sora', sans-serif;
      font-size: 13px;
      font-weight: 600;
      color: <?= $tag_color ?>;
    }

    .header-tag::before {
      content: '';
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: <?= $tag_color ?>;
    }

    /* ── Main ── */
    main {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 48px 16px 64px;
    }

    .card {
      background: var(--white);
      border-radius: 16px;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
      width: 100%;
      max-width: 600px;
      overflow: hidden;
    }

    /* ── Card top band ── */
    .card-band {
      background: var(--green);
      padding: 28px 36px;
      display: flex;
      align-items: center;
      gap: 18px;
    }

    .product-badge {
      width: 64px;
      height: 64px;
      border-radius: 12px;
      background: var(--yellow);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      overflow: hidden;
    }

    .product-badge img {
      width: 60px;
      height: 60px;
      object-fit: contain;
    }

    .product-badge svg {
      width: 36px;
      height: 36px;
    }

    .band-text h1 {
      font-family: 'Sora', sans-serif;
      font-size: 22px;
      font-weight: 700;
      color: var(--white);
      line-height: 1.2;
    }

    .band-text p {
      font-size: 14px;
      color: rgba(255,255,255,.75);
      margin-top: 4px;
    }

    /* ── Card body ── */
    .card-body {
      padding: 36px;
    }

    .greeting {
      background: #FFF9E6;
      border-left: 4px solid var(--yellow);
      border-radius: 0 8px 8px 0;
      padding: 14px 18px;
      margin-bottom: 28px;
      font-size: 15px;
      color: var(--dark);
    }

    .greeting strong {
      color: var(--green);
    }

    .form-sub {
      font-size: 15px;
      color: var(--gray);
      line-height: 1.6;
      margin-bottom: 28px;
    }

    /* ── Form fields ── */
    .field-group {
      display: flex;
      flex-direction: column;
      gap: 16px;
      margin-bottom: 28px;
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .field label {
      font-size: 13px;
      font-weight: 600;
      color: var(--dark);
      letter-spacing: .3px;
    }

    .field label .req {
      color: var(--red);
      margin-left: 2px;
    }

    .field input {
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 11px 14px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 15px;
      color: var(--dark);
      background: var(--white);
      transition: border-color .2s, box-shadow .2s;
      outline: none;
      width: 100%;
    }

    .field input:focus {
      border-color: var(--green);
      box-shadow: 0 0 0 3px rgba(16,132,116,.12);
    }

    .field input::placeholder { color: #B0B0B0; }

    /* ── Section label ── */
    .section-label {
      font-family: 'Sora', sans-serif;
      font-size: 14px;
      font-weight: 700;
      color: var(--green);
      text-transform: uppercase;
      letter-spacing: .8px;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .section-label::after {
      content: '';
      flex: 1;
      height: 1.5px;
      background: var(--border);
    }

    /* ── Reason cards ── */
    .reasons {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 32px;
    }

    .reason-card {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 16px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      cursor: pointer;
      transition: border-color .2s, background .2s, box-shadow .2s;
      position: relative;
    }

    .reason-card:hover {
      border-color: var(--green);
      background: #F0FAF9;
    }

    .reason-card input[type="radio"] {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    .radio-dot {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      border: 2px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: border-color .2s, background .2s;
    }

    .radio-dot::after {
      content: '';
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--white);
      transition: background .2s;
    }

    .reason-card.selected {
      border-color: var(--green);
      background: #F0FAF9;
      box-shadow: 0 0 0 3px rgba(16,132,116,.1);
    }

    .reason-card.selected .radio-dot {
      border-color: var(--green);
      background: var(--green);
    }

    .reason-card.selected .radio-dot::after {
      background: var(--white);
    }

    .reason-text {
      font-size: 15px;
      color: var(--dark);
      line-height: 1.4;
    }

    /* ── Submit ── */
    .btn-submit {
      width: 100%;
      background: var(--green);
      color: var(--white);
      font-family: 'Sora', sans-serif;
      font-size: 16px;
      font-weight: 700;
      border: none;
      border-radius: var(--radius);
      padding: 16px;
      cursor: pointer;
      transition: background .2s, transform .1s;
      letter-spacing: .3px;
    }

    .btn-submit:hover  { background: #0d6e60; }
    .btn-submit:active { transform: scale(.99); }

    .btn-submit:disabled {
      background: #9CA3AF;
      cursor: not-allowed;
    }

    /* ── Error ── */
    .alert-error {
      background: #FEF2F2;
      border: 1.5px solid #FECACA;
      border-radius: var(--radius);
      padding: 14px 18px;
      color: var(--red);
      font-size: 14px;
      margin-bottom: 24px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }

    .alert-error svg { flex-shrink: 0; margin-top: 1px; }

    /* ── Success ── */
    .success-wrap {
      padding: 56px 36px;
      text-align: center;
    }

    .success-icon {
      width: 80px;
      height: 80px;
      background: #F0FAF9;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 24px;
    }

    .success-wrap h2 {
      font-family: 'Sora', sans-serif;
      font-size: 26px;
      font-weight: 700;
      color: var(--green);
      margin-bottom: 12px;
    }

    .success-wrap p {
      font-size: 16px;
      color: var(--gray);
      line-height: 1.65;
      max-width: 420px;
      margin: 0 auto 8px;
    }

    .success-reason {
      display: inline-block;
      background: #FFF9E6;
      border: 1.5px solid var(--yellow);
      border-radius: 8px;
      padding: 10px 18px;
      font-size: 14px;
      color: var(--dark);
      margin-top: 20px;
      font-weight: 600;
    }

    /* ── Footer ── */
    footer {
      text-align: center;
      padding: 24px 16px;
      font-size: 13px;
      color: #9CA3AF;
    }

    footer a {
      color: var(--green);
      text-decoration: none;
    }

    /* ── Responsive ── */
    @media (max-width: 480px) {
      .card-band { padding: 22px 20px; }
      .card-body { padding: 24px 20px; }
      .band-text h1 { font-size: 18px; }
      .success-wrap { padding: 40px 20px; }
    }
  </style>
</head>
<body>

<header>
  <a href="https://petvi.com.br" class="logo-wrap" target="_blank">
    <img src="https://petvi.com.br/cdn/shop/files/Logo.svg?v=1754679113&width=200" alt="Petvi" onerror="this.style.display='none'">
  </a>
  <span class="header-tag"><?= htmlspecialchars($tag_label) ?> de Assinatura</span>
</header>

<main>
  <div class="card">

    <!-- Band -->
    <div class="card-band">
      <div class="product-badge">
        <img
          src="https://petvi.com.br/cdn/shop/files/0a.png?v=1773846225"
          alt="Condropure"
          onerror="this.style.display='none'; this.parentNode.innerHTML='<svg viewBox=\'0 0 36 36\' fill=\'none\' xmlns=\'http://www.w3.org/2000/svg\'><circle cx=\'18\' cy=\'18\' r=\'18\' fill=\'#108474\'/><text x=\'18\' y=\'23\' text-anchor=\'middle\' fill=\'white\' font-size=\'14\' font-weight=\'bold\' font-family=\'Sora,sans-serif\'>C</text></svg>'"
        >
      </div>
      <div class="band-text">
        <h1><?= $form_heading ?></h1>
        <p>Condropure – Suplemento articular para cães</p>
      </div>
    </div>

    <?php if ($success): ?>
    <!-- Success state -->
    <div class="success-wrap">
      <div class="success-icon">
        <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
          <circle cx="20" cy="20" r="20" fill="#108474" fill-opacity=".15"/>
          <path d="M12 21l6 6 11-12" stroke="#108474" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2>Solicitação Recebida!</h2>
      <?php if ($is_cancellation): ?>
        <p>Sua solicitação de <strong>cancelamento</strong> foi registrada com sucesso. Nossa equipe entrará em contato em breve para confirmar e orientar os próximos passos.</p>
      <?php else: ?>
        <p>Sua solicitação de <strong>pausa</strong> foi registrada com sucesso. Nossa equipe entrará em contato em breve para confirmar e orientar os próximos passos.</p>
      <?php endif; ?>
      <?php if (!empty($submitted_reason)): ?>
        <div class="success-reason">
          Motivo: <?= htmlspecialchars($submitted_reason) ?>
        </div>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- Form -->
    <div class="card-body">

      <?php if ($has_name): ?>
      <div class="greeting">
        Olá, <strong><?= $first_name_display ?></strong>! 👋 Vimos que você quer <?= $is_cancellation ? 'cancelar' : 'pausar' ?> sua assinatura. Pode nos contar o motivo?
      </div>
      <?php endif; ?>

      <p class="form-sub"><?= $form_sub ?></p>

      <?php if (!empty($error)): ?>
      <div class="alert-error">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
          <circle cx="9" cy="9" r="9" fill="#dc2626" fill-opacity=".15"/>
          <path d="M9 5v4M9 13h.01" stroke="#dc2626" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" id="form" novalidate>
        <!-- Hidden cancel_type -->
        <input type="hidden" name="cancel_type" value="<?= htmlspecialchars($cancel_type) ?>">

        <!-- Personal info fields -->
        <?php if (!$has_name || !$has_email || !$has_phone): ?>
        <div class="field-group">
          <?php if (!$has_name): ?>
          <div class="field">
            <label for="name">Nome completo</label>
            <input
              type="text"
              id="name"
              name="name"
              placeholder="Seu nome"
              value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
              autocomplete="name"
            >
          </div>
          <?php else: ?>
          <input type="hidden" name="name" value="<?= htmlspecialchars($url_name) ?>">
          <?php endif; ?>

          <?php if (!$has_email): ?>
          <div class="field">
            <label for="email">E-mail <span class="req">*</span></label>
            <input
              type="email"
              id="email"
              name="email"
              placeholder="seu@email.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required
              autocomplete="email"
            >
          </div>
          <?php else: ?>
          <input type="hidden" name="email" value="<?= htmlspecialchars($url_email) ?>">
          <?php endif; ?>

          <?php if (!$has_phone): ?>
          <div class="field">
            <label for="phone">Telefone / WhatsApp</label>
            <input
              type="tel"
              id="phone"
              name="phone"
              placeholder="(11) 99999-9999"
              value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
              autocomplete="tel"
            >
          </div>
          <?php else: ?>
          <input type="hidden" name="phone" value="<?= htmlspecialchars($url_phone) ?>">
          <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- All fields from URL — pass them as hidden -->
        <input type="hidden" name="name"  value="<?= htmlspecialchars($url_name) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($url_email) ?>">
        <input type="hidden" name="phone" value="<?= htmlspecialchars($url_phone) ?>">
        <?php endif; ?>

        <!-- Reason selection -->
        <div class="section-label">Motivo</div>
        <div class="reasons" id="reasons">
          <?php
          $post_reason = $_POST['reason'] ?? '';
          foreach ($reasons as $i => $reason):
            $id = 'reason_' . $i;
            $selected = ($post_reason === $reason) ? ' selected' : '';
          ?>
          <label class="reason-card<?= $selected ?>" for="<?= $id ?>">
            <input
              type="radio"
              id="<?= $id ?>"
              name="reason"
              value="<?= htmlspecialchars($reason) ?>"
              <?= $post_reason === $reason ? 'checked' : '' ?>
            >
            <span class="radio-dot"></span>
            <span class="reason-text"><?= htmlspecialchars($reason) ?></span>
          </label>
          <?php endforeach; ?>
        </div>

        <button type="submit" class="btn-submit" id="btn-submit">
          <?= htmlspecialchars($btn_label) ?>
        </button>
      </form>
    </div>
    <?php endif; ?>

  </div>
</main>

<footer>
  <p>Petvi &copy; <?= date('Y') ?> &middot; <a href="https://petvi.com.br" target="_blank">petvi.com.br</a></p>
</footer>

<script>
  // Reason card selection highlight
  document.querySelectorAll('.reason-card').forEach(function(card) {
    var radio = card.querySelector('input[type="radio"]');
    if (radio && radio.checked) card.classList.add('selected');
    card.addEventListener('click', function() {
      document.querySelectorAll('.reason-card').forEach(function(c) {
        c.classList.remove('selected');
      });
      card.classList.add('selected');
      if (radio) radio.checked = true;
    });
  });

  // Phone mask (Brazil)
  var phoneInput = document.getElementById('phone');
  if (phoneInput) {
    phoneInput.addEventListener('input', function() {
      var v = this.value.replace(/\D/g, '');
      if (v.length <= 10) {
        v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
      } else {
        v = v.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
      }
      this.value = v;
    });
  }

  // Disable button on submit
  var form = document.getElementById('form');
  var btn  = document.getElementById('btn-submit');
  if (form && btn) {
    form.addEventListener('submit', function() {
      btn.disabled = true;
      btn.textContent = 'Enviando...';
    });
  }
</script>
</body>
</html>
