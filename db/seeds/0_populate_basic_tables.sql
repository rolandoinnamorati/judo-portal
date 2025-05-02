INSERT INTO modules (name) VALUES ('Base');
INSERT INTO environments (name, module_id) VALUES ('Dashboard', 1);
INSERT INTO roles (name) VALUES ('Admin'),('Club');
INSERT INTO permissions (operation, role_id, environment_id) VALUES
    (0, 1, 1),
    (1, 1, 1),
    (2, 1, 1),
    (3, 1, 1),
    (0,2,1),
    (1,2,1),
    (2,2,1),
    (3,2,1);
