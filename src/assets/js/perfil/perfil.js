document.addEventListener("DOMContentLoaded", function () {
  if (window.lucide && typeof lucide.createIcons === "function") {
    lucide.createIcons();
  }

  const modalPerfilVer          = document.getElementById("modalPerfilVer");
  const modalPerfilEditar       = document.getElementById("modalPerfilEditar");
  const modalPassword           = document.getElementById("modalPassword");

  const btnVerPerfil            = document.getElementById("btnVerPerfil");
  const btnEditarPerfil         = document.getElementById("btnEditarPerfil");

  const btnCerrarPerfilVer      = document.getElementById("btnCerrarModalPerfilVer");
  const btnCerrarPerfilVerFooter= document.getElementById("btnCerrarPerfilVerFooter");

  const btnCerrarPerfilEditar   = document.getElementById("btnCerrarModalPerfilEditar");
  const btnCancelarPerfilEditar = document.getElementById("btnCancelarPerfilEditar");

  const btnAbrirCambiarPass     = document.getElementById("btnAbrirCambiarPassword");
  const btnCerrarPassword       = document.getElementById("btnCerrarPassword");
  const btnCancelarPassword     = document.getElementById("btnCancelarPassword");

  const avatarPerfilEditar      = document.getElementById("avatarPerfilEditar");
  const btnCambiarFotoEditar    = document.getElementById("btnCambiarFotoEditar");
  const inputFotoPerfilEditar   = document.getElementById("inputFotoPerfilEditar");

  // =============================
  // 🔥 Snapshot para detectar cambios en el perfil
  // =============================
  let originalPerfilSnapshot = null;

  // =====================================================
  // ✅ FLOWBITE-STYLE ALERTS (MISMO LOOK, PERO A LA DERECHA)
  // =====================================================
  function getOrCreateFlowbiteContainer() {
    let container = document.getElementById("flowbite-alert-container");

    if (!container) {
      container = document.createElement("div");
      container.id = "flowbite-alert-container";
      container.className =
        "fixed top-6 right-4 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none";
      document.body.appendChild(container);
    }
    return container;
  }

  function showFlowbiteAlert(type, message) {
    const container = getOrCreateFlowbiteContainer();
    const wrapper = document.createElement("div");

    let borderColor = "border-amber-500";
    let textColor = "text-amber-900";
    let titleText = "Advertencia";

    let iconSVG = `
      <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
           fill="currentColor" viewBox="0 0 20 20">
        <path d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.59A1.75 1.75 0 0 1 16.768 17H3.232a1.75 1.75 0 0 1-1.492-2.311L8.257 3.1z"/>
        <path d="M11 13H9V9h2zm0 3H9v-2h2z" fill="#fff"/>
      </svg>
    `;

    if (type === "success") {
      borderColor = "border-emerald-500";
      textColor = "text-emerald-900";
      titleText = "Éxito";
      iconSVG = `
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
             fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm-1 15-4-4 1.414-1.414L9 12.172l4.586-4.586L15 9z"/>
        </svg>
      `;
    }

    if (type === "info") {
      borderColor = "border-blue-500";
      textColor = "text-blue-900";
      titleText = "Información";
      iconSVG = `
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
             fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm1 15H9v-5h2Zm0-7H9V6h2Z"/>
        </svg>
      `;
    }

    wrapper.className = `
      relative flex items-center w-full pointer-events-auto
      rounded-2xl border-l-4 ${borderColor} bg-white shadow-md
      px-4 py-3 text-sm ${textColor}
      opacity-0 -translate-y-2
      transition-all duration-300 ease-out
      animate-fade-in-up
    `;

    wrapper.innerHTML = `
      <div class="flex-shrink-0 mr-3 text-current">
        ${iconSVG}
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-semibold">${titleText}</p>
        <p class="mt-0.5 text-sm">${message}</p>
      </div>
    `;

    container.appendChild(wrapper);

    requestAnimationFrame(() => {
      wrapper.classList.remove("opacity-0", "-translate-y-2");
      wrapper.classList.add("opacity-100", "translate-y-0");
    });

    setTimeout(() => {
      wrapper.classList.add("opacity-0", "-translate-y-2");
      wrapper.classList.remove("opacity-100", "translate-y-0");
      setTimeout(() => wrapper.remove(), 250);
    }, 4000);
  }

  function toastError(message) { showFlowbiteAlert("warning", message); }
  function toastSuccess(message) { showFlowbiteAlert("success", message); }
  function toastInfo(message) { showFlowbiteAlert("info", message); }

  // =====================================================
  // ✅ DROPDOWN MENÚ USUARIO (CLICK TOGGLE, NO HOVER)
  // =====================================================
  const btnUserMenu = document.getElementById("btnUserMenu");
  const userMenuDropdown = document.getElementById("userMenuDropdown");

  const openUserMenu = () => {
    if (!userMenuDropdown || !btnUserMenu) return;
    userMenuDropdown.classList.remove("hidden");
    btnUserMenu.setAttribute("aria-expanded", "true");
  };

  const closeUserMenu = () => {
    if (!userMenuDropdown || !btnUserMenu) return;
    userMenuDropdown.classList.add("hidden");
    btnUserMenu.setAttribute("aria-expanded", "false");
  };

  const toggleUserMenu = () => {
    if (!userMenuDropdown) return;
    const isOpen = !userMenuDropdown.classList.contains("hidden");
    if (isOpen) closeUserMenu();
    else openUserMenu();
  };

  if (btnUserMenu && userMenuDropdown) {
    // Click en foto/flecha (botón completo)
    btnUserMenu.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleUserMenu();
    });

    // Click dentro del dropdown no cierra
    userMenuDropdown.addEventListener("click", (e) => e.stopPropagation());

    // Click afuera cierra
    document.addEventListener("click", () => closeUserMenu());
  }

  // =====================================================
  // MODALS (tu base intacta)
  // =====================================================
  const openModal = (modal) => {
    if (!modal) return;
    modal.classList.remove("hidden");
    document.body.classList.add("overflow-hidden");
  };

  const closeModal = (modal) => {
    if (!modal) return;
    modal.classList.add("hidden");
    document.body.classList.remove("overflow-hidden");
  };

  if (btnVerPerfil) {
    btnVerPerfil.addEventListener("click", (e) => {
      e.preventDefault();
      closeUserMenu(); // ✅ cierra dropdown al abrir modal (no daña base)
      openModal(modalPerfilVer);
    });
  }

  if (btnCerrarPerfilVer) {
    btnCerrarPerfilVer.addEventListener("click", (e) => {
      e.preventDefault();
      closeModal(modalPerfilVer);
    });
  }

  if (btnCerrarPerfilVerFooter) {
    btnCerrarPerfilVerFooter.addEventListener("click", (e) => {
      e.preventDefault();
      closeModal(modalPerfilVer);
    });
  }

  if (modalPerfilVer) {
    modalPerfilVer.addEventListener("click", (e) => {
      if (e.target === modalPerfilVer) closeModal(modalPerfilVer);
    });
  }

  if (btnEditarPerfil) {
    btnEditarPerfil.addEventListener("click", (e) => {
      e.preventDefault();
      closeUserMenu(); // ✅ cierra dropdown al abrir modal (no daña base)
      openModal(modalPerfilEditar);

      const formEditarPerfil = document.getElementById("formEditarPerfil");
      if (formEditarPerfil) {
        const fd = new FormData(formEditarPerfil);
        originalPerfilSnapshot = {};
        for (const [k, v] of fd.entries()) {
          if (v instanceof File) continue;
          originalPerfilSnapshot[k] = String(v ?? "").trim();
        }
      }
    });
  }

  if (btnCerrarPerfilEditar) {
    btnCerrarPerfilEditar.addEventListener("click", (e) => {
      e.preventDefault();
      closeModal(modalPerfilEditar);
    });
  }

  if (btnCancelarPerfilEditar) {
    btnCancelarPerfilEditar.addEventListener("click", (e) => {
      e.preventDefault();
      closeModal(modalPerfilEditar);
    });
  }

  if (modalPerfilEditar) {
    modalPerfilEditar.addEventListener("click", (e) => {
      if (e.target === modalPerfilEditar) closeModal(modalPerfilEditar);
    });
  }

  if (btnAbrirCambiarPass) {
    btnAbrirCambiarPass.addEventListener("click", (e) => {
      e.preventDefault();
      openModal(modalPassword);
    });
  }

  if (btnCerrarPassword) {
    btnCerrarPassword.addEventListener("click", (e) => {
      e.preventDefault();
      closeModal(modalPassword);
    });
  }

  if (btnCancelarPassword) {
    btnCancelarPassword.addEventListener("click", (e) => {
      e.preventDefault();
      closeModal(modalPassword);
    });
  }

  if (modalPassword) {
    modalPassword.addEventListener("click", (e) => {
      if (e.target === modalPassword) closeModal(modalPassword);
    });
  }

  // =====================================================
  // ✅ MODAL DATOS SENSIBLES - MEJORADO CON FUNCIONALIDAD COMPLETA
  // =====================================================
  const btnInfoDatosSensibles       = document.getElementById("btnInfoDatosSensibles");
  const modalDatosSensibles         = document.getElementById("modalDatosSensibles");
  const btnCerrarDatosSensibles     = document.getElementById("btnCerrarDatosSensibles");
  const btnCancelarDatosSensibles   = document.getElementById("btnCancelarDatosSensibles");
  const formDatosSensibles          = document.getElementById("formDatosSensibles");
  const btnEnviarDatosSensibles     = document.getElementById("btnEnviarDatosSensibles");

  const openDatosSensibles = () => {
    if (!modalDatosSensibles) return;
    openModal(modalDatosSensibles);

    // Re-render lucide por si el icono no aparece
    if (window.lucide && typeof lucide.createIcons === "function") {
      lucide.createIcons();
    }

    // Inicializar valores actuales en los inputs
    inicializarValoresActuales();
  };

  const closeDatosSensibles = () => {
    if (!modalDatosSensibles) return;
    
    // Resetear formulario antes de cerrar
    if (formDatosSensibles) {
      formDatosSensibles.reset();
      
      // Ocultar todos los campos
      document.querySelectorAll('[id^="field_"]').forEach(field => {
        field.classList.add('hidden');
      });
    }
    
    closeModal(modalDatosSensibles);
  };

  // Inicializar valores actuales en los inputs del modal
  function inicializarValoresActuales() {
    // Obtener datos actuales del perfil desde el formulario de edición
    const formEditar = document.getElementById('formEditarPerfil');
    const datosActuales = {};
    
    if (formEditar) {
      const fd = new FormData(formEditar);
      for (const [key, value] of fd.entries()) {
        if (value instanceof File) continue;
        datosActuales[key] = String(value ?? "").trim();
      }
    }
    
    // También intentar obtener de elementos en el DOM que muestran los datos actuales
    const elementosDatos = {
      'nombre_completo': document.querySelector('[data-campo="nombre_completo"]'),
      'tipo_documento': document.querySelector('[data-campo="tipo_documento"]'),
      'numero_documento': document.querySelector('[data-campo="numero_documento"]'),
      'correo': document.querySelector('[data-campo="correo"]')
    };
    
    // Establecer placeholders con valores actuales
    for (const [campo, valor] of Object.entries(datosActuales)) {
      const input = document.getElementById(`input_${campo}`);
      if (input) {
        input.setAttribute('placeholder', `Actual: ${valor}`);
        input.setAttribute('data-valor-actual', valor);
      }
    }
  }

  if (btnInfoDatosSensibles) {
    btnInfoDatosSensibles.addEventListener("click", (e) => {
      e.preventDefault();
      openDatosSensibles();
    });
  }

  if (btnCerrarDatosSensibles) {
    btnCerrarDatosSensibles.addEventListener("click", (e) => {
      e.preventDefault();
      closeDatosSensibles();
    });
  }

  if (btnCancelarDatosSensibles) {
    btnCancelarDatosSensibles.addEventListener("click", (e) => {
      e.preventDefault();
      closeDatosSensibles();
    });
  }

  if (modalDatosSensibles) {
    modalDatosSensibles.addEventListener("click", (e) => {
      if (e.target === modalDatosSensibles) closeDatosSensibles();
    });
  }

  // Checklist -> mostrar/ocultar inputs
  const sensibleChecks = modalDatosSensibles
    ? modalDatosSensibles.querySelectorAll('input[type="checkbox"][data-sensible]')
    : [];

  const setFieldVisible = (key, show) => {
    const el = document.getElementById("field_" + key);
    if (!el) return;
    if (show) el.classList.remove("hidden");
    else el.classList.add("hidden");
  };

  // Inicializar: ocultar todos los campos primero
  document.querySelectorAll('[id^="field_"]').forEach(field => {
    field.classList.add('hidden');
  });

  if (sensibleChecks && sensibleChecks.length > 0) {
    sensibleChecks.forEach((chk) => {
      chk.addEventListener("change", () => {
        const key = chk.getAttribute("data-sensible");
        setFieldVisible(key, chk.checked);
        
        // Si se desmarca, limpiar el input correspondiente
        if (!chk.checked) {
          const fieldWrap = document.getElementById("field_" + key);
          if (fieldWrap) {
            const input = fieldWrap.querySelector("input, select, textarea");
            if (input) input.value = '';
          }
        }
      });
    });
  }

  // Envío del formulario de datos sensibles
  if (formDatosSensibles) {
    formDatosSensibles.addEventListener("submit", async (e) => {
      e.preventDefault();
      
      const datosSolicitados = {};
      const selected = Array.from(sensibleChecks || []).filter((c) => c.checked);

      if (selected.length === 0) {
        toastError("Selecciona al menos un dato sensible para continuar.");
        return;
      }

      // Recopilar datos actuales del formulario de edición
      const datosActuales = {};
      const formEditar = document.getElementById('formEditarPerfil');
      if (formEditar) {
        const fd = new FormData(formEditar);
        for (const [key, value] of fd.entries()) {
          if (value instanceof File) continue;
          datosActuales[key] = String(value ?? "").trim();
        }
      }

      // Verificar y recopilar datos sensibles seleccionados
      for (const chk of selected) {
        const key = chk.getAttribute("data-sensible");
        const fieldWrap = document.getElementById("field_" + key);
        if (!fieldWrap) continue;

        const input = fieldWrap.querySelector("input, select, textarea");
        if (!input) continue;

        const nuevoValor = String(input.value ?? "").trim();
        if (!nuevoValor) {
          toastError("Completa todos los campos seleccionados antes de continuar.");
          return;
        }
        
        // Obtener valor actual
        const valorActual = datosActuales[key] || input.getAttribute('data-valor-actual') || '';
        
        // Verificar si el valor realmente cambió
        if (nuevoValor === valorActual) {
          toastError(`El valor para "${key.replace('_', ' ')}" es igual al actual. No se necesita cambio.`);
          return;
        }
        
        // Validaciones específicas por campo
        if (key === 'correo' && !validarEmail(nuevoValor)) {
          toastError("El correo electrónico no es válido.");
          return;
        }
        
        if (key === 'numero_documento' && !validarDocumento(nuevoValor)) {
          toastError("El número de documento no es válido.");
          return;
        }
        
        datosSolicitados[key] = {
          anterior: valorActual,
          nuevo: nuevoValor,
          campo_nombre: obtenerNombreCampo(key)
        };
      }

      try {
        // Mostrar confirmación antes de enviar
        const confirmar = confirm("¿Estás seguro de enviar esta solicitud de cambio? Un administrador revisará tu petición.");
        if (!confirmar) return;

        const formData = new FormData();
        formData.append("datos_cambiados", JSON.stringify(datosSolicitados));
        formData.append("usuario_id", obtenerUsuarioId()); // Asegúrate de tener esta función

        const resp = await fetch("src/controllers/usuario_controller.php?accion=solicitar_cambio_datos_sensibles", {
          method: "POST",
          body: formData,
        });

        const data = await resp.json();

        if (data.error) {
          toastError(data.error);
          return;
        }

        toastSuccess(data.message || "✅ Solicitud registrada correctamente. Un administrador será notificado.");
        
        // Cerrar modal y limpiar formulario
        closeDatosSensibles();
        
        // Actualizar contador de notificaciones si existe
        if (typeof actualizarContadorNotificaciones === 'function') {
          actualizarContadorNotificaciones();
        }
        
      } catch (error) {
        console.error("Error enviando solicitud:", error);
        toastError("❌ Ocurrió un error al enviar la solicitud. Intenta nuevamente.");
      }
    });
  }

  // Si existe un botón específico para enviar, también lo configuramos
  if (btnEnviarDatosSensibles && formDatosSensibles) {
    btnEnviarDatosSensibles.addEventListener("click", (e) => {
      e.preventDefault();
      formDatosSensibles.dispatchEvent(new Event('submit'));
    });
  }

  // Funciones auxiliares
  function obtenerNombreCampo(key) {
    const nombres = {
      'nombre_completo': 'Nombre completo',
      'tipo_documento': 'Tipo de documento',
      'numero_documento': 'Número de documento',
      'correo': 'Correo electrónico',
      'telefono': 'Teléfono',
      'direccion': 'Dirección'
    };
    return nombres[key] || key.replace('_', ' ');
  }

  function validarEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  }

  function validarDocumento(doc) {
    // Validación básica: solo números y letras, mínimo 4 caracteres
    const re = /^[a-zA-Z0-9]{4,20}$/;
    return re.test(doc);
  }

  function obtenerUsuarioId() {
    // Obtener ID de usuario del DOM o de una variable global
    return document.querySelector('[data-usuario-id]')?.getAttribute('data-usuario-id') || 
           window.usuarioId || 
           '<?php echo $_SESSION["usuario_id"] ?? ""; ?>';
  }

  // =====================================================
  // MANEJO DE TECLA ESCAPE
  // =====================================================
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      // ✅ primero cierra dropdown si está abierto
      if (userMenuDropdown && !userMenuDropdown.classList.contains("hidden")) {
        closeUserMenu();
        return;
      }

      // ✅ Si modal datos sensibles está abierto, ciérralo primero
      if (modalDatosSensibles && !modalDatosSensibles.classList.contains("hidden")) {
        closeDatosSensibles();
        return;
      }

      if (modalPassword && !modalPassword.classList.contains("hidden")) {
        closeModal(modalPassword);
      } else if (modalPerfilEditar && !modalPerfilEditar.classList.contains("hidden")) {
        closeModal(modalPerfilEditar);
      } else if (modalPerfilVer && !modalPerfilVer.classList.contains("hidden")) {
        closeModal(modalPerfilVer);
      }
    }
  });

  const dispararSelectorFotoEditar = (e) => {
    e.preventDefault();
    e.stopPropagation(); // ✅ evita que el click del lápiz llegue al avatar y se dispare 2 veces
    if (inputFotoPerfilEditar) inputFotoPerfilEditar.click();
  };

  if (avatarPerfilEditar) avatarPerfilEditar.addEventListener("click", dispararSelectorFotoEditar);
  if (btnCambiarFotoEditar) btnCambiarFotoEditar.addEventListener("click", dispararSelectorFotoEditar);

  if (inputFotoPerfilEditar && avatarPerfilEditar) {
    inputFotoPerfilEditar.addEventListener("change", (e) => {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      // Validar tipo de archivo
      const tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (!tiposPermitidos.includes(file.type)) {
        toastError("Solo se permiten imágenes JPG, PNG, GIF o WebP.");
        inputFotoPerfilEditar.value = '';
        return;
      }

      // Validar tamaño (máximo 2MB)
      if (file.size > 2 * 1024 * 1024) {
        toastError("La imagen no debe superar los 2MB.");
        inputFotoPerfilEditar.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = (ev) => {
        const avatarInner = avatarPerfilEditar.querySelector("div.flex");
        if (!avatarInner) return;

        avatarInner.style.backgroundColor = "transparent";

        const inicialesSpan = avatarInner.querySelector("span");
        if (inicialesSpan) inicialesSpan.classList.add("hidden");

        let img = avatarInner.querySelector("img");
        if (!img) {
          img = document.createElement("img");
          img.className = "h-full w-full object-cover rounded-full";
          avatarInner.appendChild(img);
        }
        img.src = ev.target.result;
      };
      reader.readAsDataURL(file);
    });
  }

  // =============================
  // 🔥 GUARDAR PERFIL (CON FOTO)
  // =============================
  const formEditarPerfil = document.getElementById("formEditarPerfil");
  if (formEditarPerfil) {
    formEditarPerfil.addEventListener("submit", async (e) => {
      e.preventDefault();

      const currentFD = new FormData(formEditarPerfil);
      const currentSnapshot = {};
      for (const [k, v] of currentFD.entries()) {
        if (v instanceof File) continue;
        currentSnapshot[k] = String(v ?? "").trim();
      }

      const hayNuevaFoto =
        !!(inputFotoPerfilEditar && inputFotoPerfilEditar.files && inputFotoPerfilEditar.files[0]);

      const noHayCambiosDeTexto =
        originalPerfilSnapshot &&
        JSON.stringify(currentSnapshot) === JSON.stringify(originalPerfilSnapshot);

      if (noHayCambiosDeTexto && !hayNuevaFoto) {
        toastInfo("No se detectaron cambios. Para actualizar el perfil, modifique al menos un dato antes de guardar.");
        return;
      }

      const formData = new FormData(formEditarPerfil);

      if (hayNuevaFoto) {
        formData.append("foto_perfil", inputFotoPerfilEditar.files[0]);
      }

      try {
        const resp = await fetch("src/controllers/usuario_controller.php?accion=actualizar_perfil", {
          method: "POST",
          body: formData,
        });

        const contentType = resp.headers.get("content-type") || "";
        if (!contentType.includes("application/json")) {
          toastError("El servidor no devolvió JSON. Verifique errores PHP o la ruta del controlador.");
          return;
        }

        const data = await resp.json();

        if (data.error) {
          toastError(data.error);
          return;
        }

        toastSuccess("✅ Perfil actualizado correctamente.");
        closeModal(modalPerfilEditar);

        setTimeout(() => window.location.reload(), 700);
      } catch (error) {
        console.error("Error actualizando perfil:", error);
        toastError("❌ Ocurrió un error al actualizar el perfil. Inténtelo nuevamente.");
      }
    });
  }

  // =============================
  // 🔒 CAMBIAR CONTRASEÑA (MODAL)
  // =============================
  const formCambiarPassword = document.getElementById("formCambiarPassword");

  function resetPasswordForm() {
    if (!formCambiarPassword) return;
    formCambiarPassword.reset();
  }

  if (formCambiarPassword) {
    formCambiarPassword.addEventListener("submit", async (e) => {
      e.preventDefault();

      const fd = new FormData(formCambiarPassword);
      const actual = String(fd.get("password_actual") ?? "").trim();
      const nueva = String(fd.get("password_nueva") ?? "").trim();
      const confirmar = String(fd.get("password_confirmar") ?? "").trim();

      if (!actual || !nueva || !confirmar) {
        toastError("Complete todos los campos para cambiar la contraseña.");
        return;
      }

      if (nueva.length < 8) {
        toastError("La nueva contraseña debe tener mínimo 8 caracteres.");
        return;
      }

      // ✅ Debe tener número y carácter especial
      const tieneNumero = /[0-9]/.test(nueva);
      const tieneEspecial = /[!@#$%^&*()_\-+=\[\]{};:'",.<>\/?\\|`~]/.test(nueva);

      if (!tieneNumero || !tieneEspecial) {
        toastError("La nueva contraseña debe incluir al menos un número y un carácter especial.");
        return;
      }

      if (nueva !== confirmar) {
        toastError("La confirmación no coincide con la nueva contraseña.");
        return;
      }

      if (actual === nueva) {
        toastError("La nueva contraseña no puede ser igual a la actual.");
        return;
      }

      try {
        const resp = await fetch("src/controllers/usuario_controller.php?accion=cambiar_password", {
          method: "POST",
          body: fd,
        });

        const contentType = resp.headers.get("content-type") || "";
        if (!contentType.includes("application/json")) {
          toastError("El servidor no devolvió JSON. Verifique errores PHP o la ruta del controlador.");
          return;
        }

        const data = await resp.json();

        if (data.error) {
          toastError(data.error);
          return;
        }

        toastSuccess(data.message || "✅ Contraseña actualizada correctamente.");
        resetPasswordForm();
        closeModal(modalPassword);

      } catch (error) {
        console.error("Error cambiando contraseña:", error);
        toastError("❌ Ocurrió un error al cambiar la contraseña. Inténtelo nuevamente.");
      }
    });
  }

  if (btnCerrarPassword) {
    btnCerrarPassword.addEventListener("click", () => resetPasswordForm());
  }
  if (btnCancelarPassword) {
    btnCancelarPassword.addEventListener("click", () => resetPasswordForm());
  }
  if (modalPassword) {
    modalPassword.addEventListener("click", (e) => {
      if (e.target === modalPassword) resetPasswordForm();
    });
  }

  // =========================
  // TOGGLE "OJITOS" PASSWORD (mostrar/ocultar)
  // =========================
  document.addEventListener("DOMContentLoaded", () => {
    // Render icons (por si tu archivo no lo hace)
    if (window.lucide && typeof window.lucide.createIcons === "function") {
      window.lucide.createIcons();
    }

    document.querySelectorAll('button[data-toggle-password="true"]').forEach((btn) => {
      btn.addEventListener("click", () => {
        const wrapper = btn.closest(".relative");
        if (!wrapper) return;

        const input = wrapper.querySelector('input[type="password"], input[type="text"]');
        if (!input) return;

        const iconEye = btn.querySelector('[data-lucide="eye"]');
        const iconEyeOff = btn.querySelector('[data-lucide="eye-off"]');

        const isPassword = input.type === "password";
        input.type = isPassword ? "text" : "password";

        // Cambiar iconos
        if (iconEye && iconEyeOff) {
          if (isPassword) {
            iconEye.classList.add("hidden");
            iconEyeOff.classList.remove("hidden");
          } else {
            iconEye.classList.remove("hidden");
            iconEyeOff.classList.add("hidden");
          }
        }
      });
    });
  });

  // =====================================================
  // FUNCIÓN AUXILIAR: Actualizar contador de notificaciones
  // =====================================================
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
});