-- ================================================================
-- MJR GROUP ERP - RBAC + MULTI-COMPANY PERMISSION SEED
-- Run once if you want to align the database manually.
-- The application also bootstraps these permissions at runtime.
-- ================================================================

-- Normalize legacy admin accounts into the new hierarchy label.
UPDATE users
SET role = 'super_admin'
WHERE role = 'admin';

-- Core RBAC permissions
INSERT IGNORE INTO permissions (name, description) VALUES
('view_inventory', 'View inventory items and stock levels'),
('manage_inventory', 'Create, edit, and manage inventory records'),
('view_finance', 'View finance data and reports'),
('manage_finance', 'Create and manage finance transactions'),
('view_sales', 'View sales records and orders'),
('manage_sales', 'Create and manage sales records'),
('view_procurement', 'View procurement documents and suppliers'),
('manage_procurement', 'Create and manage procurement records'),
('view_production', 'View production orders and BOM data'),
('manage_production', 'Create and manage production orders'),
('view_projects', 'View project information'),
('manage_projects', 'Create and manage projects'),
('view_analytics', 'View dashboards and analytics'),
('manage_companies', 'Manage company records within allowed scope'),
('manage_users', 'Create and manage users within allowed scope'),
('assign_user_permissions', 'Assign custom permissions to lower-level users'),
('manage_permissions', 'Maintain the global role permission matrix'),
('switch_company', 'Switch active company context');

-- Company Admin defaults
INSERT IGNORE INTO role_permissions (role, permission_id)
SELECT 'company_admin', id FROM permissions
WHERE name IN (
    'view_inventory', 'manage_inventory',
    'view_finance', 'manage_finance', 'view_sales', 'manage_sales',
    'view_procurement', 'manage_procurement', 'view_production', 'manage_production',
    'view_projects', 'manage_projects', 'view_analytics',
    'manage_companies', 'manage_users', 'assign_user_permissions'
);

-- Manager defaults
INSERT IGNORE INTO role_permissions (role, permission_id)
SELECT 'manager', id FROM permissions
WHERE name IN (
    'view_inventory', 'manage_inventory', 'view_sales', 'manage_sales',
    'view_production', 'manage_production', 'view_projects', 'view_analytics',
    'manage_users', 'assign_user_permissions'
);

-- User defaults
INSERT IGNORE INTO role_permissions (role, permission_id)
SELECT 'user', id FROM permissions
WHERE name IN (
    'view_inventory', 'view_sales', 'view_production', 'view_projects', 'view_analytics'
);
