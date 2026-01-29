<?php
/**
 * ============================================================
 * PERMISOS DEL SISTEMA (SIGA) - POR CARGO Y ROL FUNCIONAL
 * ============================================================
 * ✅ Aquí defines QUÉ puede hacer cada cargo.
 * ✅ Coordinador suele tener acceso total.
 * ✅ El rol funcional se suma al cargo (se agregan permisos).
 */

return [

    // =====================================================
    // ✅ COORDINADOR / SUBCOORDINADOR (ADMIN GENERAL)
    // =====================================================
   "coordinador" => [
    // ✅ Dashboard + Notificaciones
    "dashboard.ver",
    "notificaciones.ver",

    // Gestionar usuarios
    "usuarios.gestionar",
    "usuarios.registrar",
    "usuarios.editar",
    "usuarios.activar_desactivar",
    "usuarios.listar",
    "usuarios.asignar_rol",

    // Gestionar fichas
    "fichas.gestionar",
    "fichas.registrar",
    "fichas.modificar",
    "fichas.activar_desactivar",
    "fichas.consultar",

    // Gestionar RAEs
    "raes.gestionar",
    "raes.crear",
    "raes.editar",
    "raes.activar_desactivar",
    "raes.listar",
    "raes.consultar",

    // Obras
    "obras.gestionar",
    "obras.crear",
    "obras.editar",
    "obras.activar_desactivar",
    "obras.listar",
    "obras.consultar",

    // Programas
    "programas.gestionar",
    "programas.crear",
    "programas.editar",
    "programas.activar_desactivar",
    "programas.listar",

    // ✅ Bodegas
    "bodegas.gestionar",
    "bodegas.crear",
    "bodegas.actualizar",
    "bodegas.cambiar_estado",
    "bodegas.listar",

    // ✅ Materiales
    "materiales.gestionar",
    "materiales.crear",
    "materiales.editar",
    "materiales.habilitar_deshabilitar",
    "materiales.asignar_bodegas",
    "materiales.cambiar_estado",
    "materiales.consultar",

    // ✅ Stock
    "stock.controlar",
    "stock.definir_minimo",
    "stock.listar_riesgo",

    // Movimientos
    "movimientos.gestionar",
    "movimientos.entradas",
    "movimientos.salidas",
    "movimientos.devoluciones",

    // Solicitudes
    "solicitudes.gestionar",
    "solicitudes.consultar",
    "solicitudes.aceptar",
    "solicitudes.rechazar",

    // ✅ Reportes
    "reportes.consumo.generar",
    "reportes.pdf.generar",

    // Evidencias
    "evidencias.subir",
    "evidencias.consultar",

    // Historial
    "historial.ver",
],


    "subcoordinador" => [
    // ✅ Dashboard + Notificaciones
    "dashboard.ver",
    "notificaciones.ver",

    // ✅ Usuarios
    "usuarios.gestionar",
    "usuarios.registrar",
    "usuarios.editar",
    "usuarios.activar_desactivar",
    "usuarios.listar",
    "usuarios.asignar_rol",

    // ✅ Fichas
    "fichas.gestionar",
    "fichas.registrar",
    "fichas.modificar",
    "fichas.activar_desactivar",
    "fichas.consultar",

    // ✅ RAEs
    "raes.gestionar",
    "raes.crear",
    "raes.editar",
    "raes.activar_desactivar",
    "raes.listar",
    "raes.consultar",

    // ✅ Obras
    "obras.gestionar",
    "obras.crear",
    "obras.editar",
    "obras.activar_desactivar",
    "obras.listar",
    "obras.consultar",

    // ✅ Programas
    "programas.gestionar",
    "programas.crear",
    "programas.editar",
    "programas.activar_desactivar",
    "programas.listar",

    // ✅ Bodegas
    "bodegas.gestionar",
    "bodegas.crear",
    "bodegas.actualizar",
    "bodegas.cambiar_estado",
    "bodegas.listar",

    // ✅ Materiales
    "materiales.gestionar",
    "materiales.crear",
    "materiales.editar",
    "materiales.habilitar_deshabilitar",
    "materiales.asignar_bodegas",
    "materiales.cambiar_estado",
    "materiales.consultar",

    // ✅ Stock
    "stock.controlar",
    "stock.definir_minimo",
    "stock.listar_riesgo",

    // ✅ Movimientos
    "movimientos.gestionar",
    "movimientos.entradas",
    "movimientos.salidas",
    "movimientos.devoluciones",

    // ✅ Solicitudes
    "solicitudes.gestionar",
    "solicitudes.consultar",
    "solicitudes.aceptar",
    "solicitudes.rechazar",

    // ✅ Reportes
    "reportes.consumo.generar",
    "reportes.pdf.generar",

    // ✅ Evidencias
    "evidencias.subir",
    "evidencias.consultar",

    // ✅ Historial
    "historial.ver",
],


    // =====================================================
    // ✅ ENCARGADO DE INVENTARIO (BODEGAS + MATERIALES + STOCK)
    // =====================================================
    "encargado_inventario" => [

        // Dashboard + Notificaciones
        "dashboard.ver",
        "notificaciones.ver",

        // Bodegas
        "bodegas.gestionar",
        "bodegas.crear",
        "bodegas.actualizar",
        "bodegas.cambiar_estado",
        "bodegas.listar",

        // Materiales
        "materiales.gestionar",
        "materiales.crear",
        "materiales.editar",
        "materiales.habilitar_deshabilitar",
        "materiales.asignar_bodegas",
        "materiales.cambiar_estado",

        // Stock
        "stock.controlar",
        "stock.definir_minimo",
        "stock.listar_riesgo",

        // Notificaciones de stock
        "stock.notificar_proximo_agotarse",
        "stock.notificar_agotamiento_ficha",

        // Mantenimiento
        "materiales.bloquear_mantenimiento",
        "prestamos.restringir_bloqueo",

        // Reportes
        "reportes.consumo.generar",
        "reportes.pdf.generar",

        // Movimientos
        "movimientos.gestionar",
        "movimientos.entradas",
        "movimientos.salidas",
        "movimientos.devoluciones",
    ],


    // =====================================================
    // ✅ ENCARGADO DE BODEGA (TU PEDIDO COMPLETO)
    // =====================================================
    "encargado_bodega" => [

        // Dashboard + Notificaciones
        "dashboard.ver",
        "notificaciones.ver",

        // Movimientos
        "movimientos.gestionar",
        "movimientos.entradas",
        "movimientos.salidas",
        "movimientos.devoluciones",

        // Solicitudes
        "solicitudes.gestionar",
        "solicitudes.consultar",
        "solicitudes.aceptar",
        "solicitudes.rechazar",
        "salidas.registrar",
        "solicitudes.registrar_salida",

        // Materiales (consultar stock)
        "materiales.consultar",
        "stock.controlar",

        // Reportes
        "reportes.consumo.generar",
        "reportes.pdf.generar",
    ],

    "encargado_subbodega" => [

        // Dashboard + Notificaciones
        "dashboard.ver",
        "notificaciones.ver",

        // Movimientos
        "movimientos.gestionar",
        "movimientos.entradas",
        "movimientos.salidas",
        "movimientos.devoluciones",

        // Solicitudes
        "solicitudes.gestionar",
        "solicitudes.consultar",
        "solicitudes.aceptar",
        "solicitudes.rechazar",
        "salidas.registrar",
        "solicitudes.registrar_salida",

        // Materiales (consultar stock)
        "materiales.consultar",
        "stock.controlar",

        // Reportes
        "reportes.consumo.generar",
        "reportes.pdf.generar",
    ],


    // =====================================================
    // ✅ INSTRUCTOR (AHORA CON OBRAS + TODOS LOS RAEs EN OBRAS)
    // =====================================================
    "instructor" => [
        // Dashboard
        "dashboard.ver",

        // ✅ OBRAS Y ACTIVIDADES (para que el módulo exista y pueda entrar)
           "obras.gestionar",
            "obras.crear",
            "obras.editar",
            "obras.activar_desactivar",
            "obras.listar",
            "obras.consultar",

        // ✅ RAEs (para que dentro de Obras pueda cargar TODOS los RAEs)
        "raes.listar",
        "raes.consultar",

        // Solicitudes (crear / consultar)
        "solicitudes.crear",
        "solicitudes.consultar",
        "solicitudes.seleccionar_materiales",
        "solicitudes.enviar",

        // Evidencias
        "evidencias.registrar",
        "evidencias.consultar",
        "evidencias.adjuntar_foto",
        "evidencias.registrar_obra",
        "evidencias.registrar_material_consumido",

        // Notificaciones
        "notificaciones.ver",

        // Reglas de negocio
        "evidencias.bloquear_solicitudes_pendientes",
    ],


    // =====================================================
    // ✅ PASANTE
    // =====================================================
    "pasante" => [
        "inventario.consultar",

        "solicitudes.crear",
        "solicitudes.seleccionar_materiales",
        "solicitudes.enviar",

        "entregas.consultar_ficha",
        "entregas.seleccionar_ficha_asignada",

        "devoluciones.material",
        "devoluciones.registrar_cantidad_estado",

        "evidencias.registrar",
        "evidencias.adjuntar_foto",
        "evidencias.registrar_obra",
        "evidencias.registrar_material_consumido",

        "evidencias.bloquear_solicitudes_pendientes",

        "notificaciones.ver",
    ],

    // =====================================================
// ✅ APRENDIZ (SEGÚN DIAGRAMA HU113 - HU116)
// =====================================================
    "aprendiz" => [

    // ✅ Entrada / cargar vista principal
    "dashboard.ver",

    // ✅ HU115: Consultar notificaciones
    "notificaciones.ver",

    // ✅ HU116: Consultar obras (listar + detalle opcional)
    "obras.listar",
    "obras.consultar",

    // ✅ HU113: Consultar evidencias (listar + detalle opcional)
    "evidencias.consultar",

    // ✅ HU114: Subir evidencias (adjuntar, validar, guardar, confirmar)
    "evidencias.subir",
],

];
