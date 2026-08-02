INSERT INTO component_groups (id, name, description, display_order) VALUES
('10000000-0000-0000-0000-000000000001', 'Customer experience', 'Public services used by customers.', 10),
('10000000-0000-0000-0000-000000000002', 'Internal operations', 'Private services used by support teams.', 20);

INSERT INTO components (id, group_id, name, description, status, is_internal, display_order) VALUES
('20000000-0000-0000-0000-000000000001', '10000000-0000-0000-0000-000000000001', 'Customer Portal', 'Primary customer self-service portal.', 'OPERATIONAL', FALSE, 10),
('20000000-0000-0000-0000-000000000002', '10000000-0000-0000-0000-000000000001', 'Public API', 'External REST API.', 'OPERATIONAL', FALSE, 20),
('20000000-0000-0000-0000-000000000003', '10000000-0000-0000-0000-000000000001', 'Authentication', 'Customer sign-in service.', 'DEGRADED', FALSE, 30),
('20000000-0000-0000-0000-000000000004', '10000000-0000-0000-0000-000000000002', 'Support Console', 'Internal support operations console.', 'OPERATIONAL', TRUE, 10);

INSERT INTO incidents (id, title, description, severity, status, started_at, created_by, created_at, updated_at) VALUES
('30000000-0000-0000-0000-000000000001', 'Intermittent sign-in latency', 'Some sign-in requests are slower than normal.', 'MINOR', 'MONITORING', NOW() - INTERVAL '45 minutes', 'demo-admin', NOW() - INTERVAL '45 minutes', NOW() - INTERVAL '10 minutes');

INSERT INTO incident_components (incident_id, component_id) VALUES
('30000000-0000-0000-0000-000000000001', '20000000-0000-0000-0000-000000000003');

INSERT INTO incident_updates (id, incident_id, status, message, created_by, created_at) VALUES
('40000000-0000-0000-0000-000000000001', '30000000-0000-0000-0000-000000000001', 'INVESTIGATING', 'The operations team is investigating increased authentication latency.', 'demo-admin', NOW() - INTERVAL '45 minutes'),
('40000000-0000-0000-0000-000000000002', '30000000-0000-0000-0000-000000000001', 'MONITORING', 'Capacity was adjusted and latency is returning to normal.', 'demo-admin', NOW() - INTERVAL '10 minutes');

INSERT INTO status_history (component_id, status, source_type, source_id, changed_at) VALUES
('20000000-0000-0000-0000-000000000001', 'OPERATIONAL', 'SYSTEM', NULL, NOW() - INTERVAL '90 days'),
('20000000-0000-0000-0000-000000000002', 'OPERATIONAL', 'SYSTEM', NULL, NOW() - INTERVAL '90 days'),
('20000000-0000-0000-0000-000000000003', 'OPERATIONAL', 'SYSTEM', NULL, NOW() - INTERVAL '90 days'),
('20000000-0000-0000-0000-000000000003', 'DEGRADED', 'INCIDENT', '30000000-0000-0000-0000-000000000001', NOW() - INTERVAL '45 minutes'),
('20000000-0000-0000-0000-000000000004', 'OPERATIONAL', 'SYSTEM', NULL, NOW() - INTERVAL '90 days');

INSERT INTO scheduled_maintenance (id, title, description, status, starts_at, ends_at, created_by) VALUES
('50000000-0000-0000-0000-000000000001', 'Public API database maintenance', 'Routine database index maintenance.', 'SCHEDULED', date_trunc('day', NOW()) + INTERVAL '2 days 01:00', date_trunc('day', NOW()) + INTERVAL '2 days 02:00', 'demo-admin');

INSERT INTO maintenance_components (maintenance_id, component_id) VALUES
('50000000-0000-0000-0000-000000000001', '20000000-0000-0000-0000-000000000002');

INSERT INTO audit_log (action, aggregate_type, aggregate_id, actor, details, request_id) VALUES
('DEMO_DATA_CREATED', 'System', NULL, 'migration', '{"fictional":true}', 'migration-v002');
