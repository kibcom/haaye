ALTER TABLE users
    ADD COLUMN IF NOT EXISTS client_id BIGINT UNSIGNED NULL AFTER organization_id,
    ADD INDEX IF NOT EXISTS idx_users_client (client_id);

CREATE TABLE IF NOT EXISTS deliverable_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#64748B',
    sort_order INT NOT NULL DEFAULT 0,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_deliverable_status_org_slug (organization_id, slug),
    KEY idx_deliverable_status_org (organization_id, sort_order),
    CONSTRAINT fk_deliverable_status_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_transitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(30) NOT NULL,
    from_status VARCHAR(80) NOT NULL,
    to_status VARCHAR(80) NOT NULL,
    requires_approval VARCHAR(20) NOT NULL DEFAULT 'none',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_workflow_transition (organization_id, entity_type, from_status, to_status),
    KEY idx_workflow_org_type (organization_id, entity_type),
    CONSTRAINT fk_workflow_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approvals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    client_id BIGINT UNSIGNED NULL,
    entity_type VARCHAR(30) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    entity_label VARCHAR(180) NOT NULL,
    approval_kind VARCHAR(20) NOT NULL,
    from_status VARCHAR(80) NOT NULL,
    to_status VARCHAR(80) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    requested_by BIGINT UNSIGNED NULL,
    decided_by BIGINT UNSIGNED NULL,
    decision_comment TEXT NULL,
    decided_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    KEY idx_approvals_org_status (organization_id, status),
    KEY idx_approvals_entity (organization_id, entity_type, entity_id),
    KEY idx_approvals_client (client_id, approval_kind, status),
    KEY idx_approvals_project (project_id),
    CONSTRAINT fk_approvals_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_approvals_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_approvals_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_approvals_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_approvals_decided_by FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deliverables (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    due_date DATE NULL,
    assignee_user_id BIGINT UNSIGNED NULL,
    status VARCHAR(80) NOT NULL DEFAULT 'draft',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    internal_approval_status VARCHAR(30) NOT NULL DEFAULT 'none',
    client_approval_status VARCHAR(30) NOT NULL DEFAULT 'none',
    original_name VARCHAR(180) NULL,
    stored_path VARCHAR(255) NULL,
    mime_type VARCHAR(120) NULL,
    size_bytes INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_deliverables_org (organization_id),
    KEY idx_deliverables_project (project_id),
    KEY idx_deliverables_client (client_id),
    KEY idx_deliverables_task (task_id),
    KEY idx_deliverables_status (organization_id, status),
    CONSTRAINT fk_deliverables_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_deliverables_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_deliverables_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    CONSTRAINT fk_deliverables_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_deliverables_assignee FOREIGN KEY (assignee_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
