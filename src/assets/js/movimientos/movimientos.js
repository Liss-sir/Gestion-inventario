(function () {
  const API_BASE = window.API_BASE || `${window.location.origin}/Gestion-inventario/src/controllers/`;
  const ID_USUARIO = window.ID_USUARIO || 0;

  const materialesAgregados = [];

  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
  }

  function renderMateriales() {
    const cont = document.getElementById("listaMateriales");
    if (!cont) return;

    cont.innerHTML = "";

    if (!materialesAgregados.length) {
      cont.innerHTML = "No hay materiales agregados.";
      return;
    }

    materialesAgregados.forEach((m, i) => {
      cont.insertAdjacentHTML(
        "beforeend",
        `
          <div class="flex justify-between items-center border rounded-lg p-3">
            <div>
              <p class="font-semibold">${escapeHtml(m.nombre)}</p>
              <p class="text-xs text-gray-500">
                Cant: ${escapeHtml(m.cantidad)} ${escapeHtml(m.unidad)} · Estado: ${escapeHtml(m.estado)}
              </p>
            </div>
            <button type="button"
              onclick="eliminarMaterial(${i})"
              class="text-red-500 hover:text-red-700 font-bold">
              ✕
            </button>
          </div>
        `
      );
    });

    const materialesInput = document.getElementById("materiales_json");
    if (materialesInput) materialesInput.value = JSON.stringify(materialesAgregados);
  }

  function agregarMaterial() {
    const materialSel = document.getElementById("material");
    const cantidadEl = document.getElementById("cantidad");
    const estadoSel = document.getElementById("estado_material");

    if (!materialSel || !cantidadEl || !estadoSel) return;

    const id = materialSel.value;
    const nombre = materialSel.options[materialSel.selectedIndex]?.text || "";
    const unidad = materialSel.options[materialSel.selectedIndex]?.dataset.unidad || "";
    const cantidad = parseInt(cantidadEl.value, 10);
    const estado = estadoSel.value;

    if (!id || !cantidad || cantidad < 1 || !estado) {
      alert("Debe completar todos los campos del material (Material, Cantidad y Estado)");
      return;
    }

    materialesAgregados.push({ id_material: id, nombre, cantidad, unidad, estado });
    renderMateriales();

    materialSel.value = "";
    cantidadEl.value = 1;
    estadoSel.value = "";
  }

  function eliminarMaterial(index) {
    materialesAgregados.splice(index, 1);
    renderMateriales();
  }

  function closeMovimientoModal() {
    const modal = document.getElementById("movimientoModal");
    if (!modal) return;

    modal.classList.add("hidden");
    modal.classList.remove("flex");
    document.body.classList.remove("overflow-hidden");

    materialesAgregados.length = 0;
    const lista = document.getElementById("listaMateriales");
    if (lista) lista.innerHTML = "No hay materiales agregados.";
    const materialesInput = document.getElementById("materiales_json");
    if (materialesInput) materialesInput.value = "";
  }

  function openMovimientoModal() {
    const modal = document.getElementById("movimientoModal");
    if (!modal) return;

    modal.classList.remove("hidden");
    modal.classList.add("flex");

    cargarBodegas();
    cargarMateriales();

    document.body.classList.add("overflow-hidden");

    const subSel = document.getElementById("subbodega");
    if (subSel) subSel.innerHTML = `<option value="">Seleccione</option>`;

    if (window.initTabsMovimiento) window.initTabsMovimiento();
    if (window.lucide) window.lucide.createIcons?.();
  }

  function showNA(val) {
    return val && String(val).trim() !== "" ? escapeHtml(String(val)) : "N/A";
  }

  function renderGridMovimientos(movimientos) {
    const grid = document.getElementById("gridView");
    if (!grid) return;

    grid.innerHTML = "";

    movimientos.forEach(m => {
      const tipo = (m.tipo_movimiento || "").toLowerCase();

      let badge = { texto: "Entrada", color: "bg-[#39A90020] text-slate-700", icon: "arrow-up-from-line" };
      if (tipo === "salida") badge = { texto: "Salida", color: "bg-lime-100 text-lime-700", icon: "arrow-down-up" };
      else if (tipo === "devolucion") badge = { texto: "Devolución", color: "bg-[#39A90020] text-slate-700", icon: "rotate-ccw" };

      const fecha = m.fecha_hora ? new Date(m.fecha_hora) : null;
      const fechaTexto = fecha
        ? `${fecha.toLocaleDateString("es-CO")} ${fecha.toLocaleTimeString("es-CO", { hour: "2-digit", minute: "2-digit" })}`
        : "N/A";

      const cantidadTotal = Array.isArray(m.materiales)
        ? m.materiales.reduce((sum, mat) => sum + (parseInt(mat.cantidad, 10) || 0), 0)
        : 0;

      grid.insertAdjacentHTML(
        "beforeend",
        `
          <div class="rounded-xl border border-border bg-card p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-3">
              <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${badge.color}">
                <i data-lucide="${badge.icon}" class="h-3 w-3"></i>
                ${badge.texto}
              </span>
              <span class="text-xs text-muted-foreground">${fechaTexto}</span>
            </div>

            <div class="space-y-1 text-sm">
              <p class="font-medium text-foreground truncate">Bodega: ${escapeHtml(m.bodega || "N/A")}</p>
              <p class="text-xs text-muted-foreground truncate">Subbodega: ${escapeHtml(m.subbodega || "N/A")}</p>
              ${tipo !== "entrada"
          ? `
                  <div class="mt-2 space-y-0.5 text-xs text-muted-foreground">
                    <p><span class="font-medium">Programa:</span> ${showNA(m.id_programa)}</p>
                    <p><span class="font-medium">Ficha:</span> ${showNA(m.id_ficha)}</p>
                    <p><span class="font-medium">RAE:</span> ${showNA(m.id_rae)}</p>
                    <p><span class="font-medium">Solicitud:</span> ${showNA(m.id_solicitud)}</p>
                  </div>
                `
          : `
                  <p class="mt-2 text-xs text-muted-foreground italic">No aplica información académica</p>
                `}
            </div>

            <div class="mt-3 flex items-center justify-between text-xs">
              <div class="flex items-center gap-1 text-muted-foreground">
                <i data-lucide="package" class="h-3 w-3"></i>
                ${cantidadTotal} items
              </div>
              <button type="button"
                onclick="openMaterialesModal(this)"
                data-materiales='${escapeHtml(JSON.stringify(m.materiales || []))}'
                class="inline-flex items-center gap-1 rounded-md border px-2 py-1 hover:bg-muted">
                <i data-lucide="eye" class="h-3 w-3"></i>
                Ver
              </button>
            </div>
          </div>
        `
      );
    });

    if (window.lucide) window.lucide.createIcons?.();
  }

  async function cargarMovimientosDelServidor() {
    try {
      const res = await fetch(`${API_BASE}movimiento_controller.php?accion=listar`, {
        headers: { Accept: "application/json" }
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();

      if (!json.success || !Array.isArray(json.data)) return;

      const tbody = document.getElementById("tbodyMovimientos");
      const gridView = document.getElementById("gridView");
      if (!tbody) return;

      tbody.innerHTML = "";
      if (gridView) gridView.innerHTML = "";

      json.data.forEach(m => {
        const tipo = (m.tipo_movimiento || "").toLowerCase();
        let labelTipo = "Entrada";
        let claseTipo = "bg-gray-100 text-gray-700";
        let iconTipo = "arrow-down-up";

        if (tipo === "entrada") {
          labelTipo = "Entrada";
          claseTipo = "bg-[#39A90020] text-slate-700";
          iconTipo = "arrow-up-from-line";
        } else if (tipo === "salida") {
          labelTipo = "Salida";
          claseTipo = "bg-lime-100 text-lime-700";
          iconTipo = "arrow-down-up";
        } else if (tipo === "devolucion") {
          labelTipo = "Devolución";
          claseTipo = "bg-[#39A90020] text-slate-700";
          iconTipo = "rotate-ccw";
        }

        const fecha = m.fecha_hora ? new Date(m.fecha_hora) : null;
        const fechaFormato = fecha ? fecha.toLocaleDateString("es-CO") : "-";
        const horaFormato = fecha ? fecha.toLocaleTimeString("es-CO") : "";

        const cantidadTotal = Array.isArray(m.materiales)
          ? m.materiales.reduce((sum, mat) => sum + (parseInt(mat.cantidad, 10) || 0), 0)
          : 0;
        const materialesJson = escapeHtml(JSON.stringify(m.materiales || []));

        tbody.insertAdjacentHTML(
          "beforeend",
          `
            <tr class="hover:bg-muted/60"
                data-tipo="${tipo}"
                data-programa="${m.id_programa || ''}"
                data-ficha="${(m.id_ficha || '').toString().toLowerCase()}">
              <td class="px-4 py-3 align-top">
                <div class="flex items-start gap-2">
                  <i data-lucide="calendar" class="h-4 w-4 mt-0.5 text-muted-foreground"></i>
                  <div class="flex flex-col">
                    <span class="text-sm font-medium text-foreground">${fechaFormato}</span>
                    <span class="text-xs text-muted-foreground">${horaFormato}</span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 align-top">
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${claseTipo}">
                  <i data-lucide="${iconTipo}" class="h-3 w-3"></i>
                  ${labelTipo}
                </span>
              </td>
              <td class="px-4 py-3 align-top">
                <button type="button"
                  class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 hover:bg-muted"
                  onclick="openMaterialesModal(this)"
                  data-materiales='${materialesJson}'>
                  <i data-lucide="eye" class="h-4 w-4"></i>
                  <span class="text-xs text-muted-foreground">Ver</span>
                </button>
              </td>
              <td class="px-4 py-3 align-top">
                <span class="text-sm font-medium text-foreground">${cantidadTotal}</span>
              </td>
              <td class="px-4 py-3 align-top"><span class="text-sm">${escapeHtml(m.bodega || 'N/A')}</span></td>
              <td class="px-4 py-3 align-top"><span class="text-sm">${escapeHtml(m.subbodega || 'N/A')}</span></td>
              <td class="px-4 py-3 align-top"><span class="text-sm">${escapeHtml(m.id_programa ? String(m.id_programa) : 'N/A')}</span></td>
              <td class="px-4 py-3 align-top">
                <span class="inline-flex items-center rounded-md border border-border px-2 py-1 text-xs font-medium">
                  ${escapeHtml(m.id_ficha ? String(m.id_ficha) : 'N/A')}
                </span>
              </td>
              <td class="px-4 py-3 align-top"><span class="text-sm">${escapeHtml(m.id_rae ? String(m.id_rae) : 'N/A')}</span></td>
              <td class="px-4 py-3 align-top">
                <div class="flex items-start gap-2">
                  <i data-lucide="users" class="h-4 w-4 mt-0.5 text-muted-foreground"></i>
                  <span class="text-sm truncate max-w-[220px]">${escapeHtml(m.id_instructor ? String(m.id_instructor) : 'N/A')}</span>
                </div>
              </td>
              <td class="px-4 py-3 align-top">
                <span class="text-sm text-muted-foreground">${escapeHtml(m.observaciones || 'N/A')}</span>
              </td>
              <td class="px-4 py-3 align-top"><span class="text-sm">N/A</span></td>
            </tr>
          `
        );
      });

      if (window.lucide && typeof window.lucide.createIcons === "function") window.lucide.createIcons();

      actualizarContadores(json.data);
      renderGridMovimientos(json.data);

      const totalRegistros = json.data.length;
      actualizarContadorTabla(totalRegistros, totalRegistros);

      const contadorTabla = document.getElementById("contadorTabla");
      if (contadorTabla) {
        contadorTabla.textContent = totalRegistros
          ? `Mostrando 1 - ${totalRegistros} de ${totalRegistros} registros`
          : "Mostrando 0 - 0 de 0 registros";
      }
    } catch (err) {
      console.error("Error cargando movimientos:", err);
    }
  }

  function actualizarContadorTabla(visibles, total) {
    const contador = document.getElementById("contadorTabla");
    if (!contador) return;
    contador.textContent = visibles === 0
      ? "Mostrando 0 - 0 de 0 registros"
      : `Mostrando 1 - ${visibles} de ${total} registros`;
  }

  function actualizarContadores(movimientos) {
    let contEntrada = 0;
    let contSalida = 0;
    let contDevolucion = 0;

    movimientos.forEach(m => {
      const tipo = (m.tipo_movimiento || "").toLowerCase();
      if (tipo === "entrada") contEntrada++;
      else if (tipo === "salida") contSalida++;
      else if (tipo === "devolucion") contDevolucion++;
    });

    const elEntrada = document.getElementById("contadorEntrada");
    const elSalida = document.getElementById("contadorSalida");
    const elDevolucion = document.getElementById("contadorDevolucion");

    if (elEntrada) elEntrada.textContent = contEntrada;
    if (elSalida) elSalida.textContent = contSalida;
    if (elDevolucion) elDevolucion.textContent = contDevolucion;
  }

  async function cargarBodegas() {
    const sel = document.getElementById("bodega");
    if (!sel) return;

    sel.innerHTML = `<option value="">Cargando...</option>`;
    try {
      const res = await fetch(`${API_BASE}bodega_controller.php?accion=listar`, { headers: { Accept: "application/json" } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();

      sel.innerHTML = `<option value="">Seleccione</option>`;
      if (!json.success || !Array.isArray(json.data)) return;

      json.data.forEach(b => {
        sel.insertAdjacentHTML("beforeend", `<option value="${b.id_bodega}">${b.nombre}</option>`);
      });

      sel.onchange = e => {
        const id = e.target.value;
        const sub = document.getElementById("subbodega");
        if (!id) {
          if (sub) sub.innerHTML = `<option value="">Seleccione bodega primero</option>`;
          return;
        }
        cargarSubbodegas(id);
      };
    } catch (e) {
      console.error("Error cargando bodegas:", e);
      sel.innerHTML = `<option value="">Error al cargar</option>`;
    }
  }

  async function cargarSubbodegas(idBodega) {
    const sel = document.getElementById("subbodega");
    if (!sel) return;
    if (!idBodega) {
      sel.innerHTML = `<option value="">Seleccione bodega primero</option>`;
      return;
    }

    sel.innerHTML = `<option value="">Cargando...</option>`;
    try {
      const res = await fetch(
        `${API_BASE}sub_bodega_controller.php?accion=por_bodega&id_bodega=${encodeURIComponent(idBodega)}`,
        { headers: { Accept: "application/json" } }
      );
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();

      sel.innerHTML = `<option value="">Seleccione</option>`;
      if (!json.success || !Array.isArray(json.data)) return;

      json.data.forEach(sb => {
        sel.insertAdjacentHTML("beforeend", `<option value="${sb.id_subbodega}">${sb.nombre_subbodega}</option>`);
      });
    } catch (e) {
      console.error("Error cargando subbodegas:", e);
      sel.innerHTML = `<option value="">Error al cargar</option>`;
    }
  }

  async function cargarMateriales() {
    const sel = document.getElementById("material");
    if (!sel) return;

    sel.innerHTML = `<option value="">Cargando...</option>`;
    try {
      const res = await fetch(`${API_BASE}material_formacion_controller.php?accion=listar`, { headers: { Accept: "application/json" } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();

      sel.innerHTML = `<option value="">Seleccione</option>`;
      const items = Array.isArray(json) ? json : json.data || [];
      if (!Array.isArray(items)) return;

      items.forEach(m => {
        sel.insertAdjacentHTML(
          "beforeend",
          `<option value="${m.id_material}" data-unidad="${m.unidad ?? ''}">${m.nombre}</option>`
        );
      });
    } catch (e) {
      console.error("Error cargando materiales:", e);
      sel.innerHTML = `<option value="">Error al cargar</option>`;
    }
  }

  function openMaterialesModal(btn) {
    const modal = document.getElementById("materialesModal");
    const body = document.getElementById("materialesBody");
    if (!modal || !body) return;

    let items = [];
    try {
      items = JSON.parse(btn.dataset.materiales || "[]");
    } catch (e) {
      items = [];
    }

    body.innerHTML = "";

    if (!items.length) {
      body.innerHTML = `
        <div class="rounded-lg border border-border p-4 text-sm text-muted-foreground">
          No hay materiales asociados a este movimiento.
        </div>`;
    } else {
      items.forEach((it, idx) => {
        body.insertAdjacentHTML(
          "beforeend",
          `
            <div class="rounded-xl border border-border p-4 flex items-start justify-between gap-4">
              <div class="flex items-start gap-3">
                <div class="h-9 w-9 rounded-lg bg-gray-100 flex items-center justify-center">
                  <i data-lucide="package" class="h-4 w-4 text-[#39A900]"></i>
                </div>
                <div>
                  <p class="text-sm font-semibold text-foreground">${escapeHtml(it.nombre || "Material #" + (it.id_material ?? idx + 1))}</p>
                  <p class="text-xs text-muted-foreground">ID material: ${escapeHtml(String(it.id_material ?? "-"))}</p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-sm font-medium">${escapeHtml(String(it.cantidad ?? "-"))} ${escapeHtml(it.unidad || "")}</p>
                <p class="text-xs text-muted-foreground">Cantidad</p>
              </div>
            </div>
          `
        );
      });
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");

    if (window.lucide && typeof window.lucide.createIcons === "function") window.lucide.createIcons();
  }

  function closeMaterialesModal() {
    const modal = document.getElementById("materialesModal");
    if (!modal) return;
    modal.classList.add("hidden");
    modal.classList.remove("flex");
  }

  function openDetalleFromDataset(btn) {
    const currentActionData = {
      id: btn.dataset.id,
      tipo: btn.dataset.tipo,
      fecha: btn.dataset.fecha,
      bodega: btn.dataset.bodega,
      subbodega: btn.dataset.subbodega,
      programa: btn.dataset.programa,
      ficha: btn.dataset.ficha,
      rae: btn.dataset.rae,
      instructor: btn.dataset.instructor,
      observaciones: btn.dataset.observaciones,
      solicitud: btn.dataset.solicitud,
      materiales: btn.dataset.materiales,
      fecha_hora: btn.dataset.fecha
    };
    openDetalleModal(currentActionData);
  }

  function openDetalleModal(data) {
    const modal = document.getElementById("detalleModal");
    if (!modal) return;

    document.getElementById("detTitulo").textContent = `Detalle del movimiento #${data.id || "-"}`;
    document.getElementById("detSubtitulo").textContent = "Información completa del registro";

    const tipo = (data.tipo || "").toLowerCase();
    const badgeTipo = document.getElementById("detBadgeTipo");
    let icon = "arrow-down-up";
    let cls = "bg-gray-100 text-gray-700";
    let label = data.tipo || "-";

    if (tipo === "entrada") {
      icon = "arrow-up-from-line";
      cls = "bg-[#39A90020] text-slate-700";
      label = "Entrada";
    } else if (tipo === "salida") {
      icon = "arrow-down-up";
      cls = "bg-lime-100 text-lime-700";
      label = "Salida";
    } else if (tipo === "devolucion") {
      icon = "rotate-ccw";
      cls = "bg-[#39A90020] text-slate-700";
      label = "Devolución";
    }

    if (badgeTipo) {
      badgeTipo.className = `inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium ${cls}`;
      badgeTipo.innerHTML = `<i data-lucide="${icon}" class="h-3 w-3"></i>${label}`;
    }

    const fecha = data.fecha_hora ? new Date(data.fecha_hora) : null;
    const detFecha = document.getElementById("detFecha");
    if (detFecha) detFecha.textContent = fecha ? fecha.toLocaleString() : "-";

    ["bodega", "subbodega", "programa", "ficha", "rae", "instructor", "observaciones", "solicitud"].forEach(f => {
      const el = document.getElementById(`det${f.charAt(0).toUpperCase() + f.slice(1)}`);
      if (el) el.textContent = data[f] || "-";
    });

    const contMateriales = document.getElementById("detMateriales");
    if (!contMateriales) return;
    contMateriales.innerHTML = "";

    let materiales = [];
    try {
      materiales = JSON.parse(data.materiales || "[]");
    } catch (e) {
      materiales = [];
    }

    if (!materiales.length) {
      contMateriales.innerHTML = `
        <div class="rounded-lg border border-border p-4 text-sm text-muted-foreground">
          No hay materiales asociados a este movimiento.
        </div>`;
    } else {
      materiales.forEach((m, idx) => {
        contMateriales.insertAdjacentHTML(
          "beforeend",
          `
            <div class="rounded-xl border border-border p-4 flex items-start justify-between gap-4">
              <div class="flex items-start gap-3">
                <div class="h-9 w-9 rounded-lg bg-gray-100 flex items-center justify-center">
                  <i data-lucide="package" class="h-4 w-4 text-[#39A900]"></i>
                </div>
                <div>
                  <p class="text-sm font-semibold text-foreground">${escapeHtml(m.nombre || "Material #" + (m.id_material ?? idx + 1))}</p>
                  <p class="text-xs text-muted-foreground">ID material: ${escapeHtml(String(m.id_material ?? "-"))}</p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-sm font-medium">${escapeHtml(String(m.cantidad ?? "-"))} ${escapeHtml(m.unidad || "")}</p>
                <p class="text-xs text-muted-foreground">Cantidad</p>
              </div>
            </div>
          `
        );
      });
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");

    if (window.lucide && typeof window.lucide.createIcons === "function") window.lucide.createIcons();
  }

  function registrarEntrada(ev) {
    ev.preventDefault();

    if (!materialesAgregados.length) {
      alert("Debe agregar al menos un material.");
      return;
    }

    const tipoMovimiento = document.getElementById("tipoMovimiento")?.value || "entrada";
    const idBodega = document.getElementById("bodega")?.value || "";
    const idSubbodega = document.getElementById("subbodega")?.value || "";
    const idPrograma = document.getElementById("programa")?.value || null;
    const idFicha = document.getElementById("ficha")?.value || null;
    const idRae = document.getElementById("rae")?.value || null;
    const idInstructor = document.getElementById("instructor")?.value || null;
    const idSolicitud = document.getElementById("solicitud")?.value || null;
    const observaciones = document.querySelector("textarea[name='observaciones']")?.value || "";

    if (!idBodega || !idSubbodega) {
      alert("Debe seleccionar bodega y subbodega.");
      return;
    }

    const dataToSend = {
      id_usuario: ID_USUARIO,
      id_bodega: idBodega,
      id_subbodega: idSubbodega,
      id_programa: idPrograma || null,
      id_ficha: idFicha || null,
      id_rae: idRae || null,
      id_instructor: idInstructor || null,
      id_solicitud: idSolicitud || null,
      observaciones,
      tipo_movimiento: tipoMovimiento,
      materiales: materialesAgregados
    };

    fetch(`${API_BASE}movimiento_controller.php?accion=crear`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(dataToSend)
    })
      .then(res => res.json())
      .then(json => {
        if (json.success) {
          alert("Movimiento registrado exitosamente: " + json.codigo_movimiento);
          materialesAgregados.length = 0;
          renderMateriales();
          document.getElementById("formMovimiento")?.reset();
          closeMovimientoModal();
          setTimeout(cargarMovimientosDelServidor, 500);
        } else {
          alert("Error: " + (json.message || "No se pudo registrar el movimiento"));
        }
      })
      .catch(err => {
        console.error("Error:", err);
        alert("Error al registrar movimiento: " + err.message);
      });
  }

  document.addEventListener("DOMContentLoaded", () => {
    cargarMovimientosDelServidor();

    if (window.lucide && typeof window.lucide.createIcons === "function") window.lucide.createIcons();

    const btnVistaTabla = document.getElementById("btnVistaTabla");
    const btnVistaTarjetas = document.getElementById("btnVistaTarjetas");
    const tableView = document.getElementById("tableView");
    const gridView = document.getElementById("gridView");

    if (btnVistaTabla && btnVistaTarjetas && tableView && gridView) {
      const setActiveBtn = (btnActive, btnInactive) => {
        btnActive.classList.add("bg-muted", "text-foreground");
        btnActive.classList.remove("text-muted-foreground");
        btnInactive.classList.remove("bg-muted", "text-foreground");
        btnInactive.classList.add("text-muted-foreground");
      };

      const showTable = () => {
        gridView.classList.add("hidden");
        tableView.classList.remove("hidden");
        setActiveBtn(btnVistaTabla, btnVistaTarjetas);
      };
      const showGrid = () => {
        tableView.classList.add("hidden");
        gridView.classList.remove("hidden");
        setActiveBtn(btnVistaTarjetas, btnVistaTabla);
        if (window.lucide && typeof window.lucide.createIcons === "function") window.lucide.createIcons();
      };

      btnVistaTabla.addEventListener("click", showTable);
      btnVistaTarjetas.addEventListener("click", showGrid);
      showTable();
    }

    const labelsPorTipo = { entrada: "Registrar entrada", devolucion: "Registrar devolución" };

    function initTabsMovimiento() {
      const tabsWrap = document.getElementById("tabsMovimiento");
      if (!tabsWrap) return;

      const tabs = tabsWrap.querySelectorAll(".tab-mov");
      const hiddenTipo = document.getElementById("tipoMovimiento");
      const btnSubmit = document.getElementById("btnRegistrarMovimiento");
      const entradaBtn = tabsWrap.querySelector('[data-tipo="entrada"]');

      const setActive = btn => {
        tabs.forEach(t => {
          t.classList.remove("bg-white", "shadow", "text-gray-900");
          t.classList.add("text-gray-600");
        });

        btn.classList.add("bg-white", "shadow", "text-gray-900");
        btn.classList.remove("text-gray-600");

        const cardDevolucion = document.querySelector('[data-field="programa"]');
        const tipo = btn.dataset.tipo;
        if (hiddenTipo) hiddenTipo.value = tipo;
        const isDev = tipo === "devolucion";

        if (cardDevolucion) cardDevolucion.classList.toggle("hidden", !isDev);
        if (btnSubmit) {
          btnSubmit.textContent = labelsPorTipo[tipo] || "Registrar";
          btnSubmit.classList.remove("bg-blue-600", "bg-secondary");
          btnSubmit.classList.add("bg-secondary");
        }

        if (!isDev) {
          ["programa", "ficha", "rae", "instructor", "solicitud", "entrega"].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = "";
          });
        } else {
          const entregaSel = document.getElementById("entrega");
          if (entregaSel) entregaSel.value = "";
        }
      };

      if (entradaBtn) setActive(entradaBtn);
      tabs.forEach(btn => (btn.onclick = () => setActive(btn)));
    }

    window.initTabsMovimiento = initTabsMovimiento;
    if (window.initTabsMovimiento) window.initTabsMovimiento();

    const cantidadEl = document.getElementById("cantidad");
    if (cantidadEl) {
      cantidadEl.addEventListener("keydown", ev => {
        if (["-", "e", "E", "+"].includes(ev.key)) ev.preventDefault();
      });
    }

    const filtroTipo = document.getElementById("filtroTipo");
    const filtroPrograma = document.getElementById("filtroPrograma");
    const buscarFicha = document.getElementById("buscarFicha");

    const aplicarFiltros = () => {
      const tbody = document.getElementById("tbodyMovimientos");
      const sinResultados = document.getElementById("sinResultados");
      if (!tbody) return;

      const filas = tbody.querySelectorAll("tr");
      const valorTipo = filtroTipo?.value.toLowerCase().trim() || "";
      const valorPrograma = filtroPrograma?.value.trim() || "";
      const valorFicha = buscarFicha?.value.toLowerCase().trim() || "";

      let filasVisibles = 0;

      filas.forEach(fila => {
        const tipo = (fila.dataset.tipo || "").toLowerCase().trim();
        const programa = (fila.dataset.programa || "").trim();
        const ficha = (fila.dataset.ficha || "").toLowerCase().trim();

        const cumpleTipo = !valorTipo || tipo === valorTipo;
        const cumplePrograma = !valorPrograma || programa === valorPrograma;
        const cumpleFicha = !valorFicha || ficha.includes(valorFicha);

        const mostrar = cumpleTipo && cumplePrograma && cumpleFicha;
        fila.style.display = mostrar ? "" : "none";
        if (mostrar) filasVisibles++;
      });

      if (sinResultados) {
        if (filasVisibles === 0) {
          sinResultados.classList.remove("hidden");
          sinResultados.style.display = "table-row-group";
        } else {
          sinResultados.classList.add("hidden");
          sinResultados.style.display = "none";
        }
      }

      actualizarContadorTabla(filasVisibles, filas.length);
    };

    if (filtroTipo) filtroTipo.addEventListener("change", aplicarFiltros);
    if (filtroPrograma) filtroPrograma.addEventListener("change", aplicarFiltros);
    if (buscarFicha) buscarFicha.addEventListener("input", aplicarFiltros);
  });

  window.agregarMaterial = agregarMaterial;
  window.eliminarMaterial = eliminarMaterial;
  window.closeMovimientoModal = closeMovimientoModal;
  window.openMovimientoModal = openMovimientoModal;
  window.cargarBodegas = cargarBodegas;
  window.cargarMateriales = cargarMateriales;
  window.openMaterialesModal = openMaterialesModal;
  window.closeMaterialesModal = closeMaterialesModal;
  window.openDetalleFromDataset = openDetalleFromDataset;
  window.openDetalleModal = openDetalleModal;
  window.closeDetalleModal = closeDetalleModal;
  window.registrarEntrada = registrarEntrada;
  window.cargarMovimientosDelServidor = cargarMovimientosDelServidor;
})();
