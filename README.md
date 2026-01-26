# 🏗️ Sistema de Gestión de Inventario – SIGA

Aplicativo web desarrollado para la **gestión de inventario de equipos e insumos de formación** del sector de **Construcción e Infraestructura del SENA – CDITI**, orientado a registrar, controlar y realizar seguimiento detallado de los materiales almacenados en bodegas.

El sistema está diseñado para ejecutarse **en entorno local**, facilitando la digitalización y optimización de los procesos actuales de inventario.

---

## 📖 Descripción

El **Sistema de Gestión de Inventario SIGA** permite a los usuarios:

- Registrar entradas y salidas de materiales e insumos de formación.
- Gestionar bodegas y sub-bodegas según su ubicación y especialización.
- Controlar el consumo de materiales por ficha de formación.
- Consultar el historial de movimientos para auditorías y seguimiento.
- Visualizar el estado del inventario en tiempo real.

El aplicativo busca reemplazar el control manual en hojas de cálculo (Excel), centralizando la información en una única plataforma digital que garantice **trazabilidad, control y evidencia del uso de los recursos**.

---

## ✨ Características

- 📦 Registro y control de materiales de formación (consumibles e inventariados).
- 🏬 Gestión de bodegas y sub-bodegas.
- 🔄 Control de entradas, salidas y devoluciones de material.
- 📊 Seguimiento del consumo por ficha, competencia y resultado de aprendizaje.
- ⚠️ Alertas visuales para niveles bajos de inventario.
- 🧾 Historial de movimientos para soporte en auditorías.
- 👥 Gestión de roles (instructor, coordinador, administrador).
- 🧱 Arquitectura basada en un enfoque modular y escalable.

---

## 🛠️ Instalación

### Requisitos previos
- Servidor local (XAMPP, WAMP o similar).
- PHP 8.x
- MySQL / MariaDB
- Navegador web moderno.

### Pasos de instalación

1. Clonar el repositorio:

```bash
git clone https://github.com/Jhonatan1217/Gestion-inventario.git

```

2. Copiar el proyecto en el directorio del servidor local:
 - htdocs/   (XAMPP)
 - www/      (WAMP)

3. Importar la base de datos en phpMyAdmin.

4. Configurar las credenciales de conexión a la base de datos.

5. Iniciar el servidor local.

6. Acceder al sistema desde el navegador:
   http://localhost/senlock-inventario/

---

⚙️ Configuración del Backend (Local)

El sistema está configurado para ejecutarse en entorno local, utilizando un servidor PHP y una base de datos MySQL.

La conexión a la base de datos se configura en el archivo:

config/database.php

  $host = 'localhost';
  
  $dbname = 'gestion_inventario';
  
  $user = 'root';
  
  $pass = 'Aqui va tu contraseña de mysql'; 

---

🔐 Configuración de Red (HTTP)

El proyecto funciona exclusivamente en HTTP, ya que está diseñado para ejecutarse en local.

Y se ejecuta entrando al navegador web y en la barra de busqueda ingresa: 
  http://localhost/Gestion-inventario/

---

🧰 Tecnologías

- PHP – Backend, Frontend y lógica del servidor.

- MySQL / MariaDB – Base de datos.

- HTML5 / CSS3 – Estructura y estilos.

- JavaScript – Interacción y lógica del frontend.

- Tailwind CSS – Diseño de interfaz.

- Arquitectura MVC – Organización del proyecto.

- Servidor local (XAMPP / WAMP).

---

📌 Contexto del Proyecto

Este proyecto se desarrolla como parte de la Fábrica de Software Jr del SENA, para el Centro de Diseño e Innovación Tecnológica Industrial (CDITI), con el objetivo de mejorar la gestión del inventario en las áreas de Construcción e Infraestructura.

El sistema responde a problemáticas reales identificadas mediante:

Entrevistas a instructores.

Análisis de procesos actuales.

Requerimientos funcionales y técnicos documentados.

---

👤 Equipo de Desarrollo

Equipo SENLOCK
Fábrica de Software SENLOCk – SENA
Centro de Diseño e Innovación Tecnológica Industrial (CDITI)

### Integrantes
| Nombre | Correo | Usuario |
| :--- | :--- | :--- |
| Jhonatan Stiven Acevedo Mendoza | [jhonatanacevedo1215@gmail.com](mailto:jhonatanacevedo1215@gmail.com) | [Jhonatan1217](https://github.com/Jhonatan1217)  |
| Juan José Candamil Pérez | [yomacjc@gmail.com](mailto:yomacjc@gmail.com) | [Candamil-Print](https://github.com/Candamil-Print) |
| Kevin Andrés Duarte Hurtado| [hh.andress78@gmail.com](mailto:hh.andress78@gmail.com) | [Kev-dev-v](https://github.com/Kev-dev-v) |
| Isaac Echeverry García | [isaacecheverry53@gmail.com](mailto:isaacecheverry53@gmail.com) | [EGIsaac](https://github.com/EGIsaac) |
| Luis Carlos Hernández Henao| [lchernandez4474@gmail.com](mailto:lchernandez4474@gmail.com) | [Astherdev](https://github.com/Astherdev) |
| Samuel Monsalve Gomez | [Monsalvegomezsamuel2@gmail.com](mailto:Monsalvegomezsamuel2@gmail.com) | [SamuelMG1088](https://github.com/SamuelMG1088) |
| Kevin Leandro Muñoz Duque | [kevinx7276@gmail.com](mailto:kevinx7276@gmail.com) | [Kevinx7276](https://github.com/Kevinx7276) |
| Laura Catalina Rubio Villa | [lauracrubiov@gmail.com](mailto:lauracrubiov@gmail.com) | [Laucrv](https://github.com/Laucrv) |
| Juan Esteban Soto Cardona | [juanestebansotoc8@gmail.com](mailto:juanestebansotoc8@gmail.com) | [juanessoto17](https://github.com/juanessoto17) |
| Julian Osorio González | [julianchin1226@gmail.com](mailto:julianchin1226@gmail.com) | [Osopardo1226](https://github.com/Osopardo1226) |

---

📌 Notas Finales

El sistema está pensado para uso interno.

Puede escalarse a otros centros del SENA.

Permite mejorar la trazabilidad, reducir pérdidas y optimizar auditorías.

Toda la información queda centralizada y digitalizada.


---
