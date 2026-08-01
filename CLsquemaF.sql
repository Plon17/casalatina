/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.8-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: casalatinasmartsystem
-- ------------------------------------------------------
-- Server version	11.8.8-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario` varchar(20) DEFAULT NULL,
  `cod_empleado` varchar(10) DEFAULT NULL,
  `modulo` varchar(30) NOT NULL,
  `accion` varchar(50) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `compras`
--

DROP TABLE IF EXISTS `compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras` (
  `ID_compras` varchar(10) NOT NULL,
  `ID_Producto` varchar(10) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `monto_total` decimal(8,2) DEFAULT NULL,
  `ID_prov` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`ID_compras`),
  KEY `ID_Producto` (`ID_Producto`),
  KEY `compras_ibfk_2` (`ID_prov`),
  CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`ID_Producto`) REFERENCES `producto` (`ID_Producto`),
  CONSTRAINT `compras_ibfk_2` FOREIGN KEY (`ID_prov`) REFERENCES `proveedores` (`ID_prov`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `empleado`
--

DROP TABLE IF EXISTS `empleado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empleado` (
  `cod_empleado` varchar(10) NOT NULL,
  `nombre_empl` varchar(30) DEFAULT NULL,
  `puesto_empl` varchar(20) DEFAULT NULL,
  `salario_empl` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`cod_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `factura`
--

DROP TABLE IF EXISTS `factura`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `factura` (
  `ID_Factura` varchar(10) NOT NULL,
  `nombre_cliente` varchar(30) DEFAULT NULL,
  `cod_empleado` varchar(10) DEFAULT NULL,
  `ID_Pedido` varchar(10) DEFAULT NULL,
  `fecha_fac` date DEFAULT NULL,
  `impuesto` decimal(6,2) DEFAULT NULL,
  `total` decimal(7,2) DEFAULT NULL,
  `rtn_cliente` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`ID_Factura`),
  KEY `cod_empleado` (`cod_empleado`),
  KEY `ID_Pedido` (`ID_Pedido`),
  CONSTRAINT `factura_ibfk_1` FOREIGN KEY (`cod_empleado`) REFERENCES `empleado` (`cod_empleado`),
  CONSTRAINT `factura_ibfk_2` FOREIGN KEY (`ID_Pedido`) REFERENCES `pedido` (`ID_Pedido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gastos`
--

DROP TABLE IF EXISTS `gastos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gastos` (
  `ID_gastos` varchar(10) NOT NULL,
  `nombre` varchar(20) DEFAULT NULL,
  `tipo` varchar(10) DEFAULT NULL,
  `descripcion` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`ID_gastos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gastos_detalles`
--

DROP TABLE IF EXISTS `gastos_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gastos_detalles` (
  `ID_detg` varchar(10) NOT NULL,
  `fecha` date DEFAULT NULL,
  `monto` int(11) DEFAULT NULL,
  `ID_gastos` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`ID_detg`),
  KEY `ID_gastos` (`ID_gastos`),
  CONSTRAINT `gastos_detalles_ibfk_1` FOREIGN KEY (`ID_gastos`) REFERENCES `gastos` (`ID_gastos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu`
--

DROP TABLE IF EXISTS `menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu` (
  `ID_Menu` varchar(10) NOT NULL,
  `nombre` varchar(30) DEFAULT NULL,
  `precio` decimal(8,2) DEFAULT NULL,
  `tipo` varchar(15) DEFAULT NULL,
  `descripcion_men` varchar(50) DEFAULT NULL,
  `ID_Producto` varchar(10) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID_Menu`),
  KEY `ID_Producto` (`ID_Producto`),
  CONSTRAINT `menu_ibfk_1` FOREIGN KEY (`ID_Producto`) REFERENCES `producto` (`ID_Producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_ingredientes`
--

DROP TABLE IF EXISTS `menu_ingredientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_ingredientes` (
  `ID_Menu` varchar(10) NOT NULL,
  `ID_Producto` varchar(10) NOT NULL,
  `cantidad_necesaria` decimal(8,2) NOT NULL,
  PRIMARY KEY (`ID_Menu`,`ID_Producto`),
  KEY `ID_Producto` (`ID_Producto`),
  CONSTRAINT `menu_ingredientes_ibfk_1` FOREIGN KEY (`ID_Menu`) REFERENCES `menu` (`ID_Menu`) ON DELETE CASCADE,
  CONSTRAINT `menu_ingredientes_ibfk_2` FOREIGN KEY (`ID_Producto`) REFERENCES `producto` (`ID_Producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pedido`
--

DROP TABLE IF EXISTS `pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido` (
  `ID_Pedido` varchar(10) NOT NULL,
  `num_mesa` int(11) DEFAULT NULL,
  `cod_empleado` varchar(10) DEFAULT NULL,
  `tipo_ped` varchar(15) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `subtotal` decimal(8,2) DEFAULT NULL,
  `impuesto` decimal(8,2) DEFAULT NULL,
  `total` decimal(8,2) DEFAULT NULL,
  `monto_recibido` decimal(8,2) DEFAULT NULL,
  `cambio` decimal(8,2) DEFAULT NULL,
  `estado` varchar(15) DEFAULT 'Activo',
  `metodo_pago` varchar(20) DEFAULT 'Efectivo',
  `descuento_pct` decimal(5,2) DEFAULT 0.00,
  `descuento_monto` decimal(8,2) DEFAULT 0.00,
  PRIMARY KEY (`ID_Pedido`),
  KEY `cod_empleado` (`cod_empleado`),
  CONSTRAINT `pedido_ibfk_1` FOREIGN KEY (`cod_empleado`) REFERENCES `empleado` (`cod_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pedido_detalle`
--

DROP TABLE IF EXISTS `pedido_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_detalle` (
  `ID_detped` varchar(10) NOT NULL,
  `ID_Pedido` varchar(10) DEFAULT NULL,
  `ID_Menu` varchar(10) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `precio` decimal(8,2) DEFAULT NULL,
  `lote` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID_detped`),
  KEY `ID_Pedido` (`ID_Pedido`),
  KEY `ID_Menu` (`ID_Menu`),
  CONSTRAINT `pedido_detalle_ibfk_1` FOREIGN KEY (`ID_Pedido`) REFERENCES `pedido` (`ID_Pedido`),
  CONSTRAINT `pedido_detalle_ibfk_2` FOREIGN KEY (`ID_Menu`) REFERENCES `menu` (`ID_Menu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `producto`
--

DROP TABLE IF EXISTS `producto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto` (
  `ID_Producto` varchar(10) NOT NULL,
  `nombre_pro` varchar(20) DEFAULT NULL,
  `cantidad_pro` decimal(10,2) DEFAULT NULL,
  `precio_pro` decimal(6,2) DEFAULT NULL,
  `categoria_pro` varchar(10) DEFAULT NULL,
  `ID_prov` varchar(10) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID_Producto`),
  KEY `ID_prov` (`ID_prov`),
  CONSTRAINT `producto_ibfk_1` FOREIGN KEY (`ID_prov`) REFERENCES `proveedores` (`ID_prov`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proveedores` (
  `ID_prov` varchar(10) NOT NULL,
  `nom_prov` varchar(10) DEFAULT NULL,
  `tel_prov` int(11) DEFAULT NULL,
  `dir_prov` varchar(30) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID_prov`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `ID_usuario` varchar(10) NOT NULL,
  `usuario` varchar(20) DEFAULT NULL,
  `contrasena` varchar(255) DEFAULT NULL,
  `rol` varchar(20) DEFAULT NULL,
  `cod_empleado` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`ID_usuario`),
  KEY `cod_empleado` (`cod_empleado`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`cod_empleado`) REFERENCES `empleado` (`cod_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-07-31 23:25:52
