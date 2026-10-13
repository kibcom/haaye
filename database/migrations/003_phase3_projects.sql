ALTER TABLE projects
    ADD COLUMN IF NOT EXISTS budget DECIMAL(12,2) NULL AFTER due_date,
    ADD COLUMN IF NOT EXISTS billing_type VARCHAR(30) NOT NULL DEFAULT 'fixed' AFTER budget,
    ADD COLUMN IF NOT EXISTS manager_user_id BIGINT UNSIGNED NULL AFTER billing_type,
    ADD COLUMN IF NOT EXISTS progress TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER manager_user_id,
    ADD COLUMN IF NOT EXISTS payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid' AFTER progress;

ALTER TABLE projects
    ADD INDEX IF NOT EXISTS idx_projects_due_date (due_date),
    ADD INDEX IF NOT EXISTS idx_projects_manager (manager_user_id),
    ADD UNIQUE INDEX IF NOT EXISTS uq_projects_org_code (organization_id, code);

CREATE TABLE IF NOT EXISTS project_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#64748B',
    sort_order INT NOT NULL DEFAULT 0,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_project_status_org_slug (organization_id, slug),
    KEY idx_project_status_org (organization_id, sort_order),
    CONSTRAINT fk_project_status_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    member_role VARCHAR(30) NOT NULL DEFAULT 'member',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_project_member (project_id, user_id),
    KEY idx_project_members_org (organization_id),
    KEY idx_project_members_user (user_id),
    CONSTRAINT fk_project_members_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_project_members_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_project_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS milestones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    due_date DATE NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'open',
    progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
    owner_user_id BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_milestones_org (organization_id),
    KEY idx_milestones_project (project_id),
    KEY idx_milestones_due (due_date),
    KEY idx_milestones_status (status),
    CONSTRAINT fk_milestones_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_milestones_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_milestones_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
