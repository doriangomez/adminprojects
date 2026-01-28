<?php
$tabs = [
    'identidad' => 'Identidad',
    'apariencia' => 'Apariencia',
    'operacion' => 'Operación',
    'gobierno' => 'Gobierno',
    'catalogos' => 'Catálogos',
    'notificaciones' => 'Notificaciones',
];
$activeTab = $_GET['tab'] ?? 'identidad';
if (!array_key_exists($activeTab, $tabs)) {
    $activeTab = 'identidad';
}
?>

<style>
    .config-tabs {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .tab-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 8px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface);
    }
    .tab-nav a {
        cursor: pointer;
        padding: 8px 14px;
        border-radius: 10px;
        font-weight: 600;
        color: var(--text-primary);
        background: var(--surface);
        border: 1px solid var(--background);
        text-decoration: none;
    }
    .tab-nav a.active {
        background: var(--primary);
        color: var(--text-primary);
        border-color: color-mix(in srgb, var(--primary) 85%, var(--secondary) 15%);
    }
    .config-card {
        background: var(--surface);
        border: 1px solid var(--border);
        box-shadow: none;
    }
    .section-header {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-bottom: 12px;
    }
    .section-header p {
        margin: 0;
    }
    .section-grid-two {
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 0.6fr);
        align-items: start;
    }
    .preview-card {
        position: sticky;
        top: 16px;
    }
    .toggle-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .toggle-switch {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        color: var(--text-primary);
    }
    .toggle-switch .toggle-label {
        font-weight: 600;
        color: var(--text-primary);
    }
    .toggle-switch input {
        display: none;
    }
    .toggle-switch .toggle-track {
        width: 42px;
        height: 24px;
        background: var(--border);
        border-radius: 999px;
        position: relative;
        transition: background 0.2s ease;
        border: 1px solid color-mix(in srgb, var(--border) 80%, var(--background));
    }
    .toggle-switch .toggle-track::after {
        content: '';
        position: absolute;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: var(--surface);
        border: 1px solid var(--border);
        top: 2px;
        left: 2px;
        transition: transform 0.2s ease;
        box-shadow: 0 1px 2px color-mix(in srgb, var(--text-primary) 18%, var(--background));
    }
    .toggle-switch input:checked + .toggle-track {
        background: var(--primary);
    }
    .toggle-switch input:checked + .toggle-track::after {
        transform: translateX(18px);
    }
    .governance-panel {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    .governance-blocks {
        display: flex;
        flex-direction: column;
        gap: 28px;
    }
    .governance-block {
        border-radius: 18px;
    }
    .governance-block-header {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 16px;
    }
    .governance-block-title-line {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .governance-block-icon {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        background: color-mix(in srgb, var(--primary) 14%, var(--surface));
        color: var(--primary);
        border: 1px solid color-mix(in srgb, var(--primary) 30%, var(--border));
        flex: 0 0 auto;
    }
    .governance-block-title {
        font-size: 20px;
        margin: 0;
        color: var(--text-primary);
    }
    .governance-block-subtitle {
        margin: 0;
        color: var(--text-secondary);
        font-size: 14px;
    }
    .governance-card-body {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .governance-switches,
    .governance-modules,
    .governance-access-section,
    .governance-document-section {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .governance-rules {
        display: grid;
        gap: 12px;
    }
    .governance-rule {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
    }
    .governance-rule-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .governance-rule-title {
        font-weight: 600;
        color: var(--text-primary);
    }
    .governance-rule-desc {
        margin: 0;
        color: var(--text-secondary);
        font-size: 13px;
    }
    .governance-module {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .governance-module-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
    }
    .governance-module-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .governance-module-title {
        font-weight: 600;
        color: var(--text-primary);
    }
    .governance-module-desc {
        margin: 0;
        color: var(--text-secondary);
        font-size: 13px;
    }
    .toggle-switch--solo {
        margin-left: auto;
    }
    .toggle-switch--solo .toggle-track {
        width: 50px;
        height: 28px;
    }
    .toggle-switch--solo .toggle-track::after {
        width: 22px;
        height: 22px;
        top: 2px;
        left: 2px;
    }
    .toggle-switch--solo input:checked + .toggle-track::after {
        transform: translateX(22px);
    }
    .governance-block--critical {
        padding: 6px;
    }
    .governance-block--critical .card-content {
        padding: 24px;
    }
    .governance-block--critical .governance-block-title {
        font-size: 22px;
    }
    .governance-panel .form-footer {
        border-top: 1px solid var(--border);
        margin-top: 16px;
        padding-top: 16px;
    }
    .permission-groups {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .permission-group {
        border-radius: 14px;
        border: 1px dashed var(--border);
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: color-mix(in srgb, var(--surface) 95%, var(--background) 5%);
    }
    .permission-group-title {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: var(--text-primary);
    }
    .permission-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .permission-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        color: var(--text-primary);
        margin: 0;
        line-height: 1.4;
        white-space: normal;
    }
    .permission-item input {
        margin: 0;
        flex: 0 0 auto;
    }
    .role-accordion {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .role-panel {
        border-radius: 16px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 96%, var(--background) 4%);
        padding: 0;
        overflow: hidden;
    }
    .role-panel summary {
        list-style: none;
        cursor: pointer;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        font-weight: 700;
    }
    .role-panel summary::-webkit-details-marker {
        display: none;
    }
    .role-panel-body {
        padding: 16px;
        border-top: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .user-accordion {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .user-card {
        border-radius: 16px;
        border: 1px solid var(--border);
        background: var(--surface);
        overflow: hidden;
    }
    .user-card summary {
        list-style: none;
        cursor: pointer;
        padding: 14px 16px;
        display: grid;
        grid-template-columns: minmax(140px, 1.2fr) minmax(180px, 1.4fr) minmax(120px, 1fr) minmax(90px, 0.6fr) auto;
        gap: 12px;
        align-items: center;
    }
    .user-card summary::-webkit-details-marker {
        display: none;
    }
    .user-card summary span {
        font-size: 14px;
        color: var(--text-primary);
    }
    .user-expand {
        font-weight: 600;
        color: var(--primary);
    }
    .user-details {
        border-top: 1px solid var(--border);
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .user-section-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        align-items: start;
    }
    .user-section-card {
        border-radius: 16px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 96%, var(--background) 4%);
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .user-section-header {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .user-section-header h4 {
        margin: 0;
        font-size: 16px;
        color: var(--text-primary);
    }
    .user-section-header p {
        margin: 0;
        color: var(--text-secondary);
        font-size: 13px;
    }
    .user-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: flex-end;
    }
    .json-collapse {
        border-radius: 14px;
        border: 1px dashed var(--border);
        background: color-mix(in srgb, var(--surface) 95%, var(--background) 5%);
        padding: 12px 14px;
    }
    .json-collapse summary {
        cursor: pointer;
        font-weight: 600;
        color: var(--text-primary);
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .json-collapse summary::-webkit-details-marker {
        display: none;
    }
    .json-collapse .input-stack {
        margin-top: 12px;
    }
    @media (max-width: 980px) {
        .section-grid-two {
            grid-template-columns: 1fr;
        }
        .preview-card {
            position: static;
        }
        .user-card summary {
            grid-template-columns: 1fr;
        }
        .user-expand {
            justify-self: start;
        }
        .user-section-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="section-grid">
    <div class="config-tabs">

        <div class="toolbar">
            <div>
                <p class="badge neutral" style="margin:0;">Configuración</p>
                <h2 style="margin:6px 0 2px 0;">Ordena la experiencia de administración</h2>
                <small class="text-muted">Identidad, apariencia, operación y gobierno en una sola vista clara.</small>
            </div>
            <?php if(!empty($savedMessage)): ?>
                <span class="badge success" data-theme-saved>Guardado</span>
            <?php else: ?>
                <span class="badge success" data-theme-saved hidden>Guardado</span>
            <?php endif; ?>
        </div>
        <div class="tab-nav">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="?tab=<?= htmlspecialchars($key) ?>" class="<?= $activeTab === $key ? 'active' : '' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="tab-panels">
            <?php
            switch ($activeTab) {
                case 'identidad':
                    include __DIR__ . '/tabs/identidad.php';
                    break;
                case 'apariencia':
                    include __DIR__ . '/tabs/apariencia.php';
                    break;
                case 'operacion':
                    include __DIR__ . '/tabs/operacion.php';
                    break;
                case 'gobierno':
                    include __DIR__ . '/tabs/gobierno.php';
                    break;
                case 'catalogos':
                    include __DIR__ . '/tabs/catalogos.php';
                    break;
                case 'notificaciones':
                    include __DIR__ . '/tabs/notificaciones.php';
                    break;
            }
            ?>
        </div>
    </div>
</section>
<script>
    const themeForms = document.querySelectorAll('form[action="/project/public/config/theme"]');
    themeForms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Accept: 'application/json',
                    },
                });
                if (!response.ok) {
                    form.submit();
                    return;
                }
                const data = await response.json();
                if (data && data.theme) {
                    window.__APP_THEME__ = data.theme;
                    if (typeof window.applyTheme === 'function') {
                        window.applyTheme(data.theme);
                    }
                }
                const savedBadge = document.querySelector('[data-theme-saved]');
                if (savedBadge) {
                    savedBadge.hidden = false;
                }
            } catch (error) {
                form.submit();
            }
        });
    });
</script>
