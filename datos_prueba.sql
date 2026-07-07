-- Datos de prueba para Casa Latina Smart System
-- Ejecutar con: sudo mariadb -u root -p casalatinasmartsystem < datos_prueba.sql

USE casalatinasmartsystem;

-- Proveedor extra (ya existe '01', agregamos otro por si acaso)
INSERT INTO proveedores (ID_prov, nom_prov, tel_prov, dir_prov) VALUES
('02', 'lacteos', 89502222, 'bodega central');

-- Productos base (insumos), enlazados a proveedores
INSERT INTO producto (ID_Producto, nombre_pro, cantidad_pro, precio_pro, categoria_pro, ID_prov) VALUES
('PR001', 'Tortilla maiz', 200, 1.50, 'Insumo', '01'),
('PR002', 'Pollo crudo', 50, 45.00, 'Carne', '01'),
('PR003', 'Queso', 30, 60.00, 'Lacteo', '02'),
('PR004', 'Refresco lata', 100, 12.00, 'Bebida', '02');

-- Menu (lo que se vende y aparece en el buscador de pedido)
INSERT INTO menu (ID_Menu, nombre, precio, tipo, descripcion_men, ID_Producto) VALUES
('M001', 'Baleada sencilla', 35.00, 'Comida', 'Baleada con frijoles y queso', 'PR001'),
('M002', 'Baleada especial', 55.00, 'Comida', 'Baleada con pollo, queso y aguacate', 'PR002'),
('M003', 'Pollo con tajadas', 95.00, 'Comida', 'Pollo frito con tajadas de platano', 'PR002'),
('M004', 'Tacos (3 unid)', 60.00, 'Comida', 'Tacos de pollo con queso', 'PR001'),
('M005', 'Coca-Cola 12oz', 20.00, 'Bebida', 'Refresco embotellado', 'PR004'),
('M006', 'Horchata', 25.00, 'Bebida', 'Bebida tradicional', NULL),
('M007', 'Cafe negro', 18.00, 'Bebida', 'Cafe caliente', NULL);
