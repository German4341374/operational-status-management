CREATE TABLE component_groups (
    id UUID PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(500),
    display_order INTEGER NOT NULL DEFAULT 0 CHECK (display_order >= 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE components (
    id UUID PRIMARY KEY,
    group_id UUID REFERENCES component_groups(id) ON DELETE SET NULL,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(500),
    status VARCHAR(32) NOT NULL CHECK (status IN ('OPERATIONAL', 'DEGRADED', 'PARTIAL_OUTAGE', 'MAJOR_OUTAGE', 'MAINTENANCE')),
    is_internal BOOLEAN NOT NULL DEFAULT FALSE,
    display_order INTEGER NOT NULL DEFAULT 0 CHECK (display_order >= 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE incidents (
    id UUID PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    severity VARCHAR(16) NOT NULL CHECK (severity IN ('MINOR', 'MAJOR', 'CRITICAL')),
    status VARCHAR(24) NOT NULL CHECK (status IN ('INVESTIGATING', 'IDENTIFIED', 'MONITORING', 'RESOLVED')),
    started_at TIMESTAMPTZ NOT NULL,
    resolved_at TIMESTAMPTZ,
    created_by VARCHAR(120) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    lock_version BIGINT NOT NULL DEFAULT 0,
    CONSTRAINT ck_incident_resolution CHECK (
        (status = 'RESOLVED' AND resolved_at IS NOT NULL) OR (status <> 'RESOLVED' AND resolved_at IS NULL)
    )
);

CREATE TABLE incident_components (
    incident_id UUID NOT NULL REFERENCES incidents(id) ON DELETE RESTRICT,
    component_id UUID NOT NULL REFERENCES components(id) ON DELETE RESTRICT,
    PRIMARY KEY (incident_id, component_id)
);

CREATE TABLE incident_updates (
    id UUID PRIMARY KEY,
    incident_id UUID NOT NULL REFERENCES incidents(id) ON DELETE RESTRICT,
    status VARCHAR(24) NOT NULL CHECK (status IN ('INVESTIGATING', 'IDENTIFIED', 'MONITORING', 'RESOLVED')),
    message TEXT NOT NULL,
    created_by VARCHAR(120) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE scheduled_maintenance (
    id UUID PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(20) NOT NULL CHECK (status IN ('SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED')),
    starts_at TIMESTAMPTZ NOT NULL,
    ends_at TIMESTAMPTZ NOT NULL,
    created_by VARCHAR(120) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_maintenance_window CHECK (ends_at > starts_at)
);

CREATE TABLE maintenance_components (
    maintenance_id UUID NOT NULL REFERENCES scheduled_maintenance(id) ON DELETE RESTRICT,
    component_id UUID NOT NULL REFERENCES components(id) ON DELETE RESTRICT,
    PRIMARY KEY (maintenance_id, component_id)
);

CREATE TABLE status_history (
    id BIGSERIAL PRIMARY KEY,
    component_id UUID NOT NULL REFERENCES components(id) ON DELETE RESTRICT,
    status VARCHAR(32) NOT NULL CHECK (status IN ('OPERATIONAL', 'DEGRADED', 'PARTIAL_OUTAGE', 'MAJOR_OUTAGE', 'MAINTENANCE')),
    source_type VARCHAR(24) NOT NULL CHECK (source_type IN ('INCIDENT', 'MAINTENANCE', 'MANUAL', 'SYSTEM')),
    source_id UUID,
    changed_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE subscriptions (
    id UUID PRIMARY KEY,
    subscriber_hash CHAR(64) NOT NULL UNIQUE,
    scope VARCHAR(120) NOT NULL DEFAULT 'all',
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE audit_log (
    id BIGSERIAL PRIMARY KEY,
    action VARCHAR(60) NOT NULL,
    aggregate_type VARCHAR(60) NOT NULL,
    aggregate_id UUID,
    actor VARCHAR(120) NOT NULL,
    details JSONB NOT NULL DEFAULT '{}'::jsonb,
    request_id VARCHAR(100),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE cache_generations (
    cache_key VARCHAR(80) PRIMARY KEY,
    generation BIGINT NOT NULL DEFAULT 1 CHECK (generation > 0),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO cache_generations (cache_key, generation) VALUES ('public_status', 1);

CREATE INDEX ix_components_group_order ON components (group_id, display_order, name);
CREATE INDEX ix_components_public_status ON components (is_internal, status);
CREATE INDEX ix_incidents_status_started ON incidents (status, started_at DESC);
CREATE INDEX ix_incident_updates_incident_time ON incident_updates (incident_id, created_at DESC);
CREATE INDEX ix_incident_components_component ON incident_components (component_id, incident_id);
CREATE INDEX ix_maintenance_window ON scheduled_maintenance (starts_at, ends_at) WHERE status IN ('SCHEDULED', 'IN_PROGRESS');
CREATE INDEX ix_status_history_component_time ON status_history (component_id, changed_at DESC);
CREATE INDEX ix_audit_aggregate_time ON audit_log (aggregate_type, aggregate_id, created_at DESC);

CREATE FUNCTION reject_immutable_row_change()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION '% is immutable', TG_TABLE_NAME;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER incident_updates_immutable
BEFORE UPDATE OR DELETE ON incident_updates
FOR EACH ROW EXECUTE FUNCTION reject_immutable_row_change();

CREATE TRIGGER audit_log_immutable
BEFORE UPDATE OR DELETE ON audit_log
FOR EACH ROW EXECUTE FUNCTION reject_immutable_row_change();
