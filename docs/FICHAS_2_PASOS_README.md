# 📋 Sistema de Fichas con Formulario en 2 Pasos

## ✅ Implementación Completada

Se ha implementado exitosamente un sistema de formulario en 2 pasos para el módulo de fichas, permitiendo agregar estudiantes (aprendices) después de crear/editar una ficha.

---

## 🗄️ Paso 1: Crear la Tabla en la Base de Datos

**IMPORTANTE**: Antes de usar el sistema, debes ejecutar el siguiente script SQL en tu base de datos:

```sql
-- Ejecutar este script en MySQL/phpMyAdmin
-- Ubicación: docs/sql/create_fichas_estudiantes_table.sql

CREATE TABLE IF NOT EXISTS fichas_estudiantes (
    id_ficha_estudiante INT AUTO_INCREMENT PRIMARY KEY,
    id_ficha INT NOT NULL,
    id_estudiante INT NOT NULL,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (id_ficha) REFERENCES fichas(id_ficha) ON DELETE CASCADE,
    FOREIGN KEY (id_estudiante) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    
    -- Evitar duplicados
    UNIQUE KEY unique_ficha_estudiante (id_ficha, id_estudiante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices para mejorar rendimiento
CREATE INDEX idx_id_ficha ON fichas_estudiantes(id_ficha);
CREATE INDEX idx_id_estudiante ON fichas_estudiantes(id_estudiante);
```

---

## 🎯 Características Implementadas

### **Paso 1: Datos de la Ficha**
- ✅ Número de ficha (validado, solo números)
- ✅ Programa de formación (select dinámico desde BD)
- ✅ Jornada (Mañana/Tarde/Noche)
- ✅ Modalidad (Presencial/Virtual/Mixta)
- ✅ Fecha de inicio
- ✅ Fecha de fin (validada para ser mayor a fecha inicio)

### **Paso 2: Agregar Estudiantes**
- ✅ Selector de aprendices (carga solo usuarios con cargo='Aprendiz' y estado='activo')
- ✅ Agregar estudiantes a la lista
- ✅ Visualizar estudiantes seleccionados con sus datos
- ✅ Eliminar estudiantes de la lista
- ✅ Contador de estudiantes totales
- ✅ Al editar una ficha, carga los estudiantes ya asignados

---

## 📁 Archivos Modificados

### **Backend - PHP**

1. **`src/models/ficha.php`**
   - ✅ `obtenerAprendices()` - Obtiene usuarios con cargo 'Aprendiz'
   - ✅ `agregarEstudiantes()` - Asigna estudiantes a una ficha
   - ✅ `obtenerEstudiantesDeFicha()` - Lista estudiantes de una ficha
   - ✅ `crear()` - Ahora retorna el ID de la ficha creada

2. **`src/controllers/ficha_controller.php`**
   - ✅ Endpoint: `?accion=aprendices` - Lista aprendices disponibles
   - ✅ Endpoint: `?accion=agregarEstudiantes` - Asigna estudiantes a ficha
   - ✅ Endpoint: `?accion=estudiantesFicha&id_ficha=X` - Obtiene estudiantes de una ficha

### **Frontend**

3. **`src/view/fichas/fichas.php`**
   - ✅ Modal convertido en 2 pasos
   - ✅ Paso 1: Formulario de datos de la ficha
   - ✅ Paso 2: Selector y lista de estudiantes
   - ✅ Botones de navegación (Siguiente, Atrás)

4. **`src/assets/js/fichas/fichas.js`**
   - ✅ Variables para estudiantes: `aprendices`, `estudiantesSeleccionados`
   - ✅ `cargarAprendices()` - Carga lista de aprendices desde BD
   - ✅ `renderOpcionesAprendices()` - Renderiza selector excluyendo seleccionados
   - ✅ `agregarEstudiante()` - Agrega estudiante a la lista
   - ✅ `eliminarEstudiante()` - Elimina estudiante de la lista
   - ✅ `renderEstudiantesSeleccionados()` - Muestra lista con detalles
   - ✅ `validarPaso1()` - Valida campos antes de ir a paso 2
   - ✅ Navegación entre pasos
   - ✅ Submit actualizado para guardar ficha + estudiantes

---

## 🔄 Flujo de Trabajo

### **Crear Nueva Ficha:**
1. Click en "Nueva Ficha"
2. **Paso 1**: Completar datos de la ficha
3. Click en "Siguiente" (valida campos)
4. **Paso 2**: Agregar estudiantes (opcional)
5. Click en "Guardar Ficha"
6. Se crea la ficha y se asignan los estudiantes

### **Editar Ficha Existente:**
1. Click en "Editar" en una ficha
2. **Paso 1**: Datos cargados automáticamente
3. Click en "Siguiente"
4. **Paso 2**: Estudiantes previamente asignados aparecen en la lista
5. Puede agregar/eliminar estudiantes
6. Click en "Guardar Ficha"
7. Se actualiza ficha y estudiantes

---

## 🎨 Interfaz de Usuario

### **Paso 2 - Lista de Estudiantes**

```
┌─────────────────────────────────────────────┐
│ Estudiantes (Aprendices)                    │
├─────────────────────────────────────────────┤
│ [Seleccione un estudiante... ▼] [+]        │
├─────────────────────────────────────────────┤
│ ┌─────────────────────────────────────────┐ │
│ │ Estudiante      Documento      Acción   │ │
│ ├─────────────────────────────────────────┤ │
│ │ Juan Pérez      1234567890     [🗑️]     │ │
│ │ maria@email.com                          │ │
│ ├─────────────────────────────────────────┤ │
│ │ María García    9876543210     [🗑️]     │ │
│ │ juan@email.com                           │ │
│ ├─────────────────────────────────────────┤ │
│ │ Total estudiantes:              2        │ │
│ └─────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

---

## 🧪 Validaciones Implementadas

### **Paso 1:**
- ✅ Número de ficha obligatorio y solo números
- ✅ Programa obligatorio
- ✅ Jornada obligatoria
- ✅ Modalidad obligatoria
- ✅ Fecha inicio obligatoria
- ✅ Fecha fin obligatoria y mayor a fecha inicio

### **Paso 2:**
- ✅ Estudiantes es opcional (puede crear ficha sin estudiantes)
- ✅ No permite duplicados
- ✅ Filtra estudiantes ya seleccionados del selector

---

## 📊 Relación en Base de Datos

```
┌──────────────┐         ┌──────────────────────┐         ┌──────────────┐
│    fichas    │────┬────│ fichas_estudiantes   │────┬────│  usuarios    │
├──────────────┤    │    ├──────────────────────┤    │    ├──────────────┤
│ id_ficha (PK)│◄───┘    │ id_ficha (FK)        │    └───►│ id_usuario   │
│ numero_ficha │         │ id_estudiante (FK)   │         │ cargo =      │
│ id_programa  │         │ fecha_asignacion     │         │ 'Aprendiz'   │
│ jornada      │         └──────────────────────┘         └──────────────┘
│ modalidad    │
│ fecha_inicio │
│ fecha_fin    │
│ estado       │
└──────────────┘
```

---

## 🚀 Cómo Usar

### **Para Desarrolladores:**

1. Ejecutar el script SQL de creación de tabla
2. Asegurarse de tener usuarios con `cargo = 'Aprendiz'` en la tabla usuarios
3. Navegar al módulo de fichas
4. Crear o editar una ficha
5. En el paso 2, agregar estudiantes

### **Para Usuarios Finales:**

1. Ir a "Fichas de Formación"
2. Click en "Nueva Ficha"
3. Llenar los datos básicos
4. Click en "Siguiente"
5. Seleccionar estudiantes del menú desplegable
6. Click en el botón "+" para agregarlos
7. Verificar la lista
8. Click en "Guardar Ficha"

---

## 🔍 API Endpoints

### **Nuevos Endpoints:**

```
GET  /src/controllers/ficha_controller.php?accion=aprendices
→ Retorna lista de usuarios con cargo='Aprendiz' activos

POST /src/controllers/ficha_controller.php?accion=agregarEstudiantes
Body: { "id_ficha": 123, "estudiantes": [1, 2, 3] }
→ Asigna estudiantes a la ficha

GET  /src/controllers/ficha_controller.php?accion=estudiantesFicha&id_ficha=123
→ Retorna estudiantes de una ficha específica
```

---

## 📝 Notas Importantes

1. **Requisito de Usuarios**: Para que aparezcan estudiantes, debe haber usuarios con:
   - `cargo = 'Aprendiz'`
   - `estado = 'activo'`

2. **Edición**: Al editar una ficha, los estudiantes previamente asignados se cargan automáticamente

3. **Validación de Duplicados**: La tabla tiene constraint UNIQUE para evitar asignar el mismo estudiante dos veces a la misma ficha

4. **CASCADE Delete**: Si se elimina una ficha, automáticamente se eliminan sus relaciones con estudiantes

5. **Opcional**: Agregar estudiantes es completamente opcional, puedes crear una ficha sin estudiantes

---

## ✨ Mejoras Futuras Sugeridas

- [ ] Búsqueda de estudiantes por nombre/documento en tiempo real
- [ ] Selección múltiple de estudiantes
- [ ] Importar estudiantes desde CSV/Excel
- [ ] Ver ficha asignada desde el perfil del estudiante
- [ ] Historial de cambios en asignaciones
- [ ] Notificaciones a estudiantes cuando son asignados

---

## 🐛 Troubleshooting

### **No aparecen estudiantes en el selector**
- Verificar que existan usuarios con `cargo = 'Aprendiz'` en la BD
- Verificar que el `estado = 'activo'`
- Revisar consola del navegador para errores

### **Error al guardar estudiantes**
- Verificar que la tabla `fichas_estudiantes` esté creada
- Revisar logs PHP en `logs/php_errors.log`

### **Modal no cambia de paso**
- Verificar que todos los campos del paso 1 estén completos
- Revisar validaciones en consola

---

## 📞 Soporte

Para reportar problemas o sugerencias:
- Revisar logs en `logs/php_errors.log`
- Verificar consola del navegador (F12)
- Revisar estructura de BD

---

**Fecha de Implementación**: 14 de Enero de 2026  
**Versión**: 1.0.0  
**Estado**: ✅ Completo y funcional
