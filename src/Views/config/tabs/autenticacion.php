<?php
$google = $configData['access']['google_workspace'] ?? [];
$isGoogleEnabled = (bool) ($google['enabled'] ?? false);
$domain = (string) ($google['corporate_domain'] ?? 'aossas.com');
$isConfigured = trim((string) ($google['client_id'] ?? '')) !== ''
    && trim((string) ($google['client_secret'] ?? '')) !== ''
    && trim((string) ($google['project_id'] ?? '')) !== ''
    && $domain !== '';
?>

<div class="card config-card governance-block">
    <div class="card-content">
        <header class="governance-block-header">
            <div class="governance-block-title-line">
                <span class="governance-block-icon" aria-hidden="true">🔐</span>
                <h3 class="governance-block-title">Autenticación con Google Workspace</h3>
            </div>
            <p class="governance-block-subtitle">Controla el acceso corporativo sin tocar código.</p>
        </header>
        <form method="POST" action="/project/public/config/google-workspace" class="governance-card-body">
            <div class="governance-rules">
                <div class="governance-rule">
                    <div class="governance-rule-info">
                        <span class="governance-rule-title">Habilitar autenticación Google</span>
                        <p class="governance-rule-desc">Si está deshabilitado, nadie podrá entrar por Google aunque esté enrolado.</p>
                    </div>
                    <label class="toggle-switch toggle-switch--state">
                        <span class="toggle-label">Google</span>
                        <input type="checkbox" name="google_enabled" <?= $isGoogleEnabled ? 'checked' : '' ?>>
                        <span class="toggle-track" aria-hidden="true"></span>
                        <span class="toggle-state" aria-hidden="true"></span>
                    </label>
                </div>
            </div>

            <div class="config-form-grid" style="margin-top: 16px;">
                <label>Dominio corporativo permitido
                    <input name="google_corporate_domain" value="<?= htmlspecialchars($domain) ?>" placeholder="aossas.com" required>
                </label>
                <label>Google Client ID
                    <input name="google_client_id" value="<?= htmlspecialchars((string) ($google['client_id'] ?? '')) ?>" placeholder="xxxx.apps.googleusercontent.com">
                </label>
                <label>Google Client Secret
                    <input name="google_client_secret" value="<?= htmlspecialchars((string) ($google['client_secret'] ?? '')) ?>" placeholder="********">
                </label>
                <label>Google Project ID
                    <input name="google_project_id" value="<?= htmlspecialchars((string) ($google['project_id'] ?? '')) ?>" placeholder="mi-proyecto-google">
                </label>
            </div>

            <div class="governance-tag-chips" style="margin-top: 12px;">
                <span class="governance-tag-chip">Estado integración: <?= $isGoogleEnabled ? 'Habilitada' : 'Deshabilitada' ?></span>
                <span class="governance-tag-chip">Conexión: <?= $isConfigured ? 'Configurado' : 'No configurado' ?></span>
            </div>

            <div class="governance-document-footer" style="display:flex;">
                <small class="text-muted">Los cambios se aplican de inmediato al login.</small>
                <button class="btn-primary" type="submit">Guardar configuración</button>
            </div>
        </form>
    </div>
</div>
