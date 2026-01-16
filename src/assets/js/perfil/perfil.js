document.addEventListener("DOMContentLoaded", function () {
  // 1. Inicializar iconos de Lucide
  if (window.lucide && typeof lucide.createIcons === "function") {
    lucide.createIcons();
  }

  // =====================================================
  // 📚 2. REFERENCIAS DOM (SIEMPRE AL PRINCIPIO)
  // =====================================================
  const modalPerfilVer = document.getElementById("modalPerfilVer");
  const modalPerfilEditar = document.getElementById("modalPerfilEditar");
  const modalPassword = document.getElementById("modalPassword");
  const modalDatosSensibles = document.getElementById("modalDatosSensibles");

  const btnVerPerfil = document.getElementById("btnVerPerfil");
  const btnEditarPerfil = document.getElementById("btnEditarPerfil");
  const btnInfoDatosSensibles = document.getElementById("btnInfoDatosSensibles");
  
  const formEditarPerfil = document.getElementById("formEditarPerfil");
  const formDatosSensibles = document.getElementById("formDatosSensibles");
  const formCambiarPassword = document.getElementById("formCambiarPassword");

  const avatarPerfilEditar = document.getElementById("avatarPerfilEditar");
  const inputFotoPerfilEditar = document.getElementById("inputFotoPerfilEditar");
  const btnGuardarPerfil = document.getElementById("btnGuardarPerfil");

  const sensibleChecks = document.querySelectorAll('input[type="checkbox"][data-sensible]');

  // =====================================================
  // ✅ 3. HELPERS DE ALERTAS
  // =====================================================
  function toastError(message) { showFlowbiteAlert("warning", message); }
  function toastSuccess(message) { showFlowbiteAlert("success", message); }

  function showFlowbiteAlert(type, message) {
    let container = document.getElementById("flowbite-alert-container");
    if (!container) {
      container = document.createElement("div");
      container.id = "flowbite-alert-container";
      container.className = "fixed top-6 right-4 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none";
      document.body.appendChild(container);
    }
    const wrapper = document.createElement("div");
    let borderColor = type === "success" ? "border-emerald-500" : "border-amber-500";
    wrapper.className = `relative flex items-center w-full p-4 mb-4 bg-white border-l-4 ${borderColor} shadow-md rounded-xl animate-fade-in-up pointer-events-auto`;
    wrapper.innerHTML = `<div class="text-sm font-medium text-slate-800">${message}</div>`;
    container.appendChild(wrapper);
    setTimeout(() => {
      wrapper.classList.add("opacity-0");
      setTimeout(() => wrapper.remove(), 500);
    }, 4000);
  }

  // =====================================================
  // ✅ 4. LÓGICA DE DATOS SENSIBLES
  // =====================================================
  function inicializarValoresActuales() {
    // Si inyectaste window.userData en el header, úsalo aquí
    if (!window.userData) return;
    
    const mapping = {
      'nombre': window.userData.nombre_completo,
      'tipo_documento': window.userData.tipo_documento,
      'numero_documento': window.userData.numero_documento,
      'correo': window.userData.correo
    };

    for (const [campo, valor] of Object.entries(mapping)) {
      const fieldWrap = document.getElementById(`field_${campo}`);
      if (fieldWrap) {
        const input = fieldWrap.querySelector("input, select");
        if (input) {
          input.setAttribute('data-valor-actual', valor || "");
          if (input.tagName === 'INPUT') input.placeholder = "Actual: " + (valor || "No registrado");
        }
      }
    }
  }

  // =====================================================
  // ✅ 5. GESTIÓN DE MODALES
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

  // Botones Menú Usuario
  if (btnVerPerfil) btnVerPerfil.onclick = () => { closeUserMenu(); openModal(modalPerfilVer); };
  if (btnEditarPerfil) btnEditarPerfil.onclick = () => { closeUserMenu(); openModal(modalPerfilEditar); };
  if (btnInfoDatosSensibles) btnInfoDatosSensibles.onclick = () => openModal(modalDatosSensibles);

  document.querySelectorAll('[id^="btnCerrar"], [id^="btnCancelar"]').forEach(btn => {
    btn.onclick = (e) => closeModal(e.target.closest('.fixed'));
  });

  // Checkboxes de datos sensibles
  sensibleChecks.forEach(chk => {
    chk.onchange = () => {
      const field = document.getElementById("field_" + chk.getAttribute("data-sensible"));
      if (field) field.classList.toggle("hidden", !chk.checked);
    };
  });

  // =====================================================
  // ✅ 6. ENVÍO DE FORMULARIOS
  // =====================================================

  // Guardar Perfil (Foto, Teléfono, Dirección)
  if (formEditarPerfil) {
    formEditarPerfil.onsubmit = async (e) => {
      e.preventDefault();
      try {
        btnGuardarPerfil.disabled = true;
        btnGuardarPerfil.innerHTML = 'Guardando...';
        const formData = new FormData(formEditarPerfil);
        
        const resp = await fetch("src/controllers/usuario_controller.php?accion=editar_perfil_usuario", {
          method: "POST",
          body: formData
        });
        const data = await resp.json();
        if (data.success) {
          toastSuccess("Perfil actualizado.");
          setTimeout(() => location.reload(), 1000);
        } else { toastError(data.error || "Error"); }
      } catch (err) { toastError("Error de conexión"); }
      finally { btnGuardarPerfil.disabled = false; btnGuardarPerfil.innerHTML = 'Guardar cambios'; }
    };
  }

  // Solicitar Cambio Datos Sensibles
  if (formDatosSensibles) {
    formDatosSensibles.onsubmit = async (e) => {
      e.preventDefault();
      const selected = Array.from(sensibleChecks).filter(c => c.checked);
      if (selected.length === 0) return toastError("Selecciona un campo.");

      const datosCambiados = {};
      for (const chk of selected) {
        const key = chk.getAttribute("data-sensible");
        const input = document.getElementById(`field_${key}`).querySelector("input, select");
        datosCambiados[key] = {
          anterior: input.getAttribute("data-valor-actual"),
          nuevo: input.value.trim(),
          campo_nombre: key.replace('_',' ').toUpperCase()
        };
      }

      const formData = new FormData();
      formData.append("datos_cambiados", JSON.stringify(datosCambiados));

      const resp = await fetch("src/controllers/usuario_controller.php?accion=solicitar_cambio_datos_sensibles", {
        method: "POST",
        body: formData
      });
      const res = await resp.json();
      if (res.success) {
        toastSuccess("Solicitud enviada.");
        closeModal(modalDatosSensibles);
      } else { toastError(res.error); }
    };
  }

  // =====================================================
  // ✅ 7. OTROS (FOTO, DROPDOWN)
  // =====================================================
  if (avatarPerfilEditar) {
    avatarPerfilEditar.onclick = () => inputFotoPerfilEditar.click();
    inputFotoPerfilEditar.onchange = (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => {
          let img = avatarPerfilEditar.querySelector("img");
          if (!img) {
            img = document.createElement("img");
            img.className = "h-full w-full object-cover rounded-full";
            avatarPerfilEditar.querySelector("div").appendChild(img);
          }
          img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
      }
    };
  }

  const btnUserMenu = document.getElementById("btnUserMenu");
  const userMenuDropdown = document.getElementById("userMenuDropdown");
  const closeUserMenu = () => userMenuDropdown?.classList.add("hidden");

  if (btnUserMenu) {
    btnUserMenu.onclick = (e) => {
      e.stopPropagation();
      userMenuDropdown.classList.toggle("hidden");
    };
  }
  document.addEventListener("click", closeUserMenu);
});

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
async function actualizarContadorNotificaciones() {
    try {
      const resp = await fetch('src/utils/notificaciones_sesion.php?accion=contar');
      const data = await resp.json();
      
      const badge = document.querySelector('.badge-notificaciones');
      if (badge && data.no_leidas > 0) {
        badge.textContent = data.no_leidas > 9 ? '9+' : data.no_leidas;
        badge.style.display = 'inline-block';
      } else if (badge) {
        badge.style.display = 'none';
      }
    } catch (error) {
      console.error('Error actualizando contador:', error);
    }
  }