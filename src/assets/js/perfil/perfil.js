document.addEventListener("DOMContentLoaded", function () {
  // Inicializar iconos de Lucide
  if (window.lucide && typeof lucide.createIcons === "function") {
    lucide.createIcons();
  }

  // =====================================================
  // 📚 REFERENCIAS DOM
  // =====================================================
  const modalPerfilVer = document.getElementById("modalPerfilVer");
  const modalPerfilEditar = document.getElementById("modalPerfilEditar");
  const modalPassword = document.getElementById("modalPassword");
  const modalDatosSensibles = document.getElementById("modalDatosSensibles");

  const btnVerPerfil = document.getElementById("btnVerPerfil");
  const btnEditarPerfil = document.getElementById("btnEditarPerfil");
  const btnInfoDatosSensibles = document.getElementById("btnInfoDatosSensibles");
  const formDatosSensibles = document.getElementById("formDatosSensibles");
  const formEditarPerfil = document.getElementById("formEditarPerfil");
  const formCambiarPassword = document.getElementById("formCambiarPassword");

  const avatarPerfilEditar = document.getElementById("avatarPerfilEditar");
  const inputFotoPerfilEditar = document.getElementById("inputFotoPerfilEditar");

  // =====================================================
  // ✅ HELPERS DE ALERTAS (ESTILO FLOWBITE)
  // =====================================================
  function toastError(message) { showFlowbiteAlert("warning", message); }
  function toastSuccess(message) { showFlowbiteAlert("success", message); }
  function toastInfo(message) { showFlowbiteAlert("info", message); }

  function showFlowbiteAlert(type, message) {
    const container = getOrCreateFlowbiteContainer();
    const wrapper = document.createElement("div");
    let borderColor = "border-amber-500";
    if (type === "success") borderColor = "border-emerald-500";
    if (type === "info") borderColor = "border-blue-500";

    wrapper.className = `relative flex items-center w-full p-4 mb-4 bg-white border-l-4 ${borderColor} shadow-md rounded-xl animate-fade-in-up pointer-events-auto z-[9999]`;
    wrapper.innerHTML = `<div class="text-sm font-medium text-slate-800">${message}</div>`;
    container.appendChild(wrapper);

    setTimeout(() => {
      wrapper.classList.add("opacity-0");
      setTimeout(() => wrapper.remove(), 500);
    }, 4000);
  }

  function getOrCreateFlowbiteContainer() {
    let container = document.getElementById("flowbite-alert-container");
    if (!container) {
      container = document.createElement("div");
      container.id = "flowbite-alert-container";
      container.className = "fixed top-6 right-4 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none";
      document.body.appendChild(container);
    }
    return container;
  }

  // =====================================================
  // ✅ FUNCIÓN: CAPTURAR DATOS ACTUALES (LÓGICA CORREGIDA)
  // =====================================================
  function inicializarValoresActuales() {
    const datosActuales = {};
    
    // Capturar Nombre (está en el h2 del modal de visualización)
    const nombreVer = document.querySelector("#modalPerfilVer h2");
    if (nombreVer) datosActuales['nombre'] = nombreVer.textContent.trim();

    // Capturar otros datos buscando las etiquetas <p> en el modal de Ver Perfil
    const etiquetas = document.querySelectorAll("#modalPerfilVer p.text-xs.font-medium.text-slate-400");
    etiquetas.forEach(p => {
      const texto = p.textContent.toLowerCase();
      const valor = p.nextElementSibling ? p.nextElementSibling.textContent.trim() : "";
      
      if (texto.includes("tipo de documento")) datosActuales['tipo_documento'] = valor;
      if (texto.includes("número de documento")) datosActuales['numero_documento'] = valor;
      if (texto.includes("correo")) datosActuales['correo'] = valor;
    });

    // Inyectar valores actuales en los inputs del modal de solicitud para enviarlos luego
    for (const [campo, valor] of Object.entries(datosActuales)) {
      const inputDestino = document.querySelector(`#field_${campo} input, #field_${campo} select`);
      if (inputDestino) {
        inputDestino.setAttribute('data-valor-actual', valor);
        if (inputDestino.tagName === 'INPUT') inputDestino.placeholder = "Actual: " + valor;
      }
    }
    console.log("🔍 Datos actuales capturados:", datosActuales);
  }

  // =====================================================
  // ✅ GESTIÓN DE MODALES
  // =====================================================
  const openModal = (modal) => {
    if (!modal) return;
    modal.classList.remove("hidden");
    document.body.classList.add("overflow-hidden");
    if (modal === modalDatosSensibles) inicializarValoresActuales();
  };

  const closeModal = (modal) => {
    if (!modal) return;
    modal.classList.add("hidden");
    document.body.classList.remove("overflow-hidden");
  };

  if (btnVerPerfil) btnVerPerfil.onclick = () => { closeUserMenu(); openModal(modalPerfilVer); };
  if (btnEditarPerfil) btnEditarPerfil.onclick = () => { closeUserMenu(); openModal(modalPerfilEditar); };
  if (btnInfoDatosSensibles) btnInfoDatosSensibles.onclick = () => openModal(modalDatosSensibles);

  // Cerrar modales al hacer clic en botones de cerrar o cancelar
  document.querySelectorAll('[id^="btnCerrar"], [id^="btnCancelar"]').forEach(btn => {
    btn.addEventListener("click", (e) => {
      const modal = e.target.closest('.fixed');
      if (modal) closeModal(modal);
    });
  });

  // Mostrar/Ocultar campos en el modal de datos sensibles según el checkbox
  const sensibleChecks = document.querySelectorAll('input[type="checkbox"][data-sensible]');
  sensibleChecks.forEach(chk => {
    chk.addEventListener("change", () => {
      const fieldId = "field_" + chk.getAttribute("data-sensible");
      const field = document.getElementById(fieldId);
      if (field) field.classList.toggle("hidden", !chk.checked);
    });
  });

  // =====================================================
  // ✅ EVENTO: ENVIAR SOLICITUD DE CAMBIO (UNIFICADO)
  // =====================================================
  if (formDatosSensibles) {
    formDatosSensibles.addEventListener("submit", async (e) => {
      e.preventDefault();
      
      const btnSubmit = formDatosSensibles.querySelector('button[type="submit"]');
      const selected = Array.from(sensibleChecks).filter(c => c.checked);

      if (selected.length === 0) {
        toastError("Selecciona al menos un dato para cambiar.");
        return;
      }

      const datosSolicitados = {};
      for (const chk of selected) {
        const key = chk.getAttribute("data-sensible");
        const fieldWrap = document.getElementById("field_" + key);
        const inputNuevo = fieldWrap.querySelector("input, select");
        
        if (!inputNuevo || !inputNuevo.value.trim()) {
          toastError(`Ingresa el nuevo valor para ${key.replace('_',' ')}`);
          return;
        }

        const nuevoValor = inputNuevo.value.trim();
        const valorAnterior = inputNuevo.getAttribute('data-valor-actual') || "No registrado";

        datosSolicitados[key] = {
          anterior: valorAnterior,
          nuevo: nuevoValor,
          campo_nombre: obtenerNombreCampo(key)
        };
      }

      try {
        if (btnSubmit) { btnSubmit.disabled = true; btnSubmit.innerHTML = 'Enviando...'; }

        const formData = new FormData();
        formData.append("datos_cambiados", JSON.stringify(datosSolicitados));

        const resp = await fetch("src/controllers/usuario_controller.php?accion=solicitar_cambio_datos_sensibles", {
          method: "POST",
          body: formData
        });

        const data = await resp.json();
        if (data.success) {
          toastSuccess("✅ Solicitud enviada correctamente.");
          closeModal(modalDatosSensibles);
          setTimeout(() => location.reload(), 1000);
        } else {
          toastError(data.error || "Error al enviar");
        }
      } catch (err) {
        toastError("Error de conexión con el servidor.");
      } finally {
        if (btnSubmit) { btnSubmit.disabled = false; btnSubmit.innerHTML = 'Continuar'; }
      }
    });
  }

  // =====================================================
  // ✅ OTROS EVENTOS (FOTO, PASSWORD, MENÚ)
  // =====================================================

  // Cambiar foto de perfil (Previsualización)
  if (avatarPerfilEditar) {
    avatarPerfilEditar.onclick = () => inputFotoPerfilEditar.click();
    inputFotoPerfilEditar.onchange = (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => {
          const img = avatarPerfilEditar.querySelector("img") || document.createElement("img");
          img.src = ev.target.result;
          img.className = "h-full w-full object-cover rounded-full";
          if (!avatarPerfilEditar.querySelector("img")) avatarPerfilEditar.querySelector("div").appendChild(img);
        };
        reader.readAsDataURL(file);
      }
    };
  }

  // Dropdown Menú Usuario
  const btnUserMenu = document.getElementById("btnUserMenu");
  const userMenuDropdown = document.getElementById("userMenuDropdown");
  const closeUserMenu = () => userMenuDropdown?.classList.add("hidden");

  if (btnUserMenu) {
    btnUserMenu.onclick = (e) => {
      e.stopPropagation();
      userMenuDropdown.classList.toggle("hidden");
    };
  }
  document.addEventListener("click", () => closeUserMenu());

  // Helper para nombres de campo
  function obtenerNombreCampo(key) {
    const nombres = {
      'nombre': 'Nombre completo',
      'tipo_documento': 'Tipo de documento',
      'numero_documento': 'Número de documento',
      'correo': 'Correo electrónico'
    };
    return nombres[key] || key;
  }

  // Tecla Escape para cerrar modales
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeUserMenu();
      const modalAbierto = document.querySelector('.fixed:not(.hidden)');
      if (modalAbierto) closeModal(modalAbierto);
    }
  });

  // Toggle Password (Ojito)
  document.querySelectorAll('button[data-toggle-password="true"]').forEach(btn => {
    btn.onclick = function() {
      const input = this.parentElement.querySelector('input');
      const isPass = input.type === 'password';
      input.type = isPass ? 'text' : 'password';
      this.querySelectorAll('i').forEach(i => i.classList.toggle('hidden'));
    };
  });

});