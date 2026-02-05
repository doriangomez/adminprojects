<?php
$tabs = [
    'identidad' => 'Identidad',
    'apariencia' => 'Apariencia',
    'operacion' => 'Operación',
    'gobierno' => 'Gobierno',
    'catalogos' => 'Catálogos',
    'notificaciones' => 'Notificaciones',
    'autenticacion' => 'Autenticación',
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
    .operacion-grid {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 20px;
    }
    .operacion-column {
        gap: 14px;
    }
    .operacion-cards {
        display: grid;
        gap: 12px;
    }
    .operacion-card {
        border-radius: 14px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .operacion-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: var(--text-primary);
    }
    .operacion-card-icon {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: color-mix(in srgb, var(--primary) 16%, var(--surface));
        font-size: 16px;
    }
    .operacion-card-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px 14px;
    }
    .operacion-card-grid label {
        display: flex;
        flex-direction: column;
        gap: 6px;
        font-weight: 600;
        color: var(--text-secondary);
    }
    .operacion-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .methodology-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        border: 1px solid color-mix(in srgb, var(--primary) 30%, var(--border));
        background: color-mix(in srgb, var(--primary) 12%, var(--surface));
        font-weight: 600;
        font-size: 13px;
        color: var(--text-primary);
    }
    .methodology-icon {
        font-size: 14px;
    }
    .operacion-textarea {
        background: color-mix(in srgb, var(--surface) 82%, var(--background) 18%);
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        border-radius: 10px;
        min-height: 180px;
    }
    .info-callout {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid color-mix(in srgb, var(--primary) 25%, var(--border));
        background: color-mix(in srgb, var(--primary) 10%, var(--surface));
        color: var(--text-secondary);
    }
    .info-callout p {
        margin: 0;
    }
    .info-callout-icon {
        font-size: 16px;
    }
    .operacion-footer {
        justify-content: flex-end;
        border-top: 1px solid var(--border);
        padding-top: 14px;
    }
    .operacion-footer .text-muted {
        margin-right: auto;
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
    .toggle-switch--state {
        gap: 8px;
    }
    .toggle-switch--state .toggle-state {
        font-size: 11px;
        font-weight: 700;
        color: var(--text-secondary);
        letter-spacing: 0.06em;
        min-width: 28px;
        text-align: right;
    }
    .toggle-switch--state .toggle-state::before {
        content: 'OFF';
    }
    .toggle-switch--state input:checked ~ .toggle-state::before {
        content: 'ON';
        color: var(--primary);
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
    .governance-document-block {
        border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
    }
    .governance-document-block .card-content {
        background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
    }
    .governance-flow-roles {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
    }
    .governance-flow-role-card {
        border-radius: 18px;
        border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));
        background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%);
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        box-shadow: 0 12px 22px rgba(15, 23, 42, 0.08);
    }
    .governance-flow-role-header {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: var(--text-primary);
        font-size: 15px;
    }
    .governance-flow-role-icon {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: color-mix(in srgb, var(--primary) 20%, var(--surface));
        border: 1px solid color-mix(in srgb, var(--primary) 35%, var(--border));
    }
    .governance-flow-role-title {
        font-weight: 700;
        letter-spacing: 0.01em;
    }
    .governance-inline-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 6px;
        font-size: 14px;
    }
    .governance-json-textarea {
        min-height: 180px;
        background: color-mix(in srgb, var(--surface) 85%, var(--background) 15%);
        border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));
        border-radius: 12px;
        padding: 12px;
        font-family: "SFMono-Regular", "Menlo", "Monaco", "Courier New", monospace;
    }
    .governance-tag-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }
    .governance-tag-chip {
        padding: 4px 10px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--primary) 12%, var(--surface));
        border: 1px solid color-mix(in srgb, var(--primary) 24%, var(--border));
        color: var(--text-secondary);
        font-size: 12px;
        font-weight: 600;
    }
    .governance-document-footer {
        justify-content: flex-end;
        gap: 12px;
        border-top: 1px solid var(--border);
        margin-top: 18px;
        padding-top: 16px;
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
        gap: 12px;
    }
    .permission-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .permission-group + .permission-group {
        border-top: 1px solid var(--border);
        padding-top: 12px;
        margin-top: 4px;
    }
    .permission-group-title {
        margin: 0;
        font-size: 13px;
        font-weight: 700;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .permission-list {
        display: grid;
        grid-template-columns: repeat(3, minmax(220px, 1fr));
        gap: 14px;
    }
    .permission-item {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        font-size: 14px;
        color: var(--text-primary);
        margin: 0;
        line-height: 1.4;
        padding: 14px;
        white-space: normal;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--background);
        min-height: 84px;
    }
    .permission-item + .permission-item {
        border-top: none;
    }
    .permission-name {
        flex: 1;
        font-weight: 600;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .permission-toggle {
        margin-left: auto;
        align-self: flex-end;
    }
    .toggle-switch--compact .toggle-track {
        width: 34px;
        height: 20px;
    }
    .toggle-switch--compact .toggle-track::after {
        width: 14px;
        height: 14px;
        top: 2px;
        left: 2px;
    }
    .toggle-switch--compact input:checked + .toggle-track::after {
        transform: translateX(14px);
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
    .user-permission-groups {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .user-permission-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .user-permission-list {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .user-permission-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--background);
        font-size: 14px;
        color: var(--text-primary);
    }
    .user-permission-item .permission-name {
        font-weight: 600;
        flex: 1;
        display: block;
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
    .notification-stack {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .notification-section-header {
        margin-bottom: 16px;
    }
    .notification-title-row {
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }
    .notification-icon {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: color-mix(in srgb, var(--primary) 16%, var(--surface));
        font-size: 18px;
    }
    .notification-smtp-grid {
        display: grid;
        grid-template-columns: minmax(0, 0.7fr) minmax(0, 1.3fr);
        gap: 16px;
        align-items: start;
    }
    .notification-smtp-block {
        background: color-mix(in srgb, var(--surface) 95%, var(--background) 5%);
    }
    .notification-smtp-fields {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }
    .notification-actions {
        margin-top: 14px;
    }
    .notification-domain-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .notification-domain-card {
        border: 1px solid var(--border);
        border-radius: 14px;
        background: color-mix(in srgb, var(--surface) 96%, var(--background) 4%);
        padding: 12px 14px;
    }
    .notification-domain-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        list-style: none;
        cursor: pointer;
        font-weight: 700;
        color: var(--text-primary);
    }
    .notification-domain-summary::-webkit-details-marker {
        display: none;
    }
    .notification-domain-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .notification-domain-icon {
        width: 30px;
        height: 30px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: color-mix(in srgb, var(--primary) 12%, var(--surface));
    }
    .notification-domain-count {
        font-size: 12px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--primary) 18%, var(--surface));
        color: var(--text-primary);
    }
    .notification-event-list {
        margin-top: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .notification-event-card {
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--surface);
        padding: 10px 12px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .notification-event-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .notification-event-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .notification-event-description {
        font-size: 12px;
    }
    .notification-event-details {
        border-top: 1px solid var(--border);
        padding-top: 8px;
    }
    .notification-event-details summary {
        list-style: none;
        cursor: pointer;
        font-weight: 600;
        color: var(--text-primary);
    }
    .notification-event-details summary::-webkit-details-marker {
        display: none;
    }
    .notification-event-body {
        margin-top: 10px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .notification-event-row {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .notification-field-label {
        font-size: 12px;
        font-weight: 700;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .notification-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .notification-chip {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        border: 1px solid color-mix(in srgb, var(--primary) 35%, var(--border));
        background: color-mix(in srgb, var(--primary) 14%, var(--surface));
        font-size: 12px;
        font-weight: 600;
        color: var(--text-primary);
    }
    .notification-recipient-controls {
        display: grid;
        gap: 10px;
    }
    .notification-recipient-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 8px 12px;
    }
    .notification-log-table table {
        font-size: 13px;
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
        .notification-smtp-grid {
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
                case 'autenticacion':
                    include __DIR__ . '/tabs/autenticacion.php';
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
