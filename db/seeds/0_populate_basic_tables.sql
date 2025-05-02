INSERT INTO modules (name) VALUES ('Base');
INSERT INTO environments (name, module_id) VALUES ('Dashboard', 1);
INSERT INTO roles (name) VALUES ('Admin');
INSERT INTO permissions (operation, role_id, environment_id) VALUES (0, 1, 1);