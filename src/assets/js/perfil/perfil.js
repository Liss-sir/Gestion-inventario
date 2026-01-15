document.addEventListener("DOMContentLoaded", function () {
  if (window.lucide && typeof lucide.createIcons === "function") {
    lucide.createIcons();
  }

  const modalPerfilVer = document.getElementById("modalPerfilVer");
  const modalPerfilEditar = document.getElementById("modalPerfilEditar");
  const modalPassword = document.getElementById("modalPassword");

  const btnVerPerfil = document.getElementById("btnVerPerfil");
  const btnEditarPerfil = document.getElementById("btnEditarPerfil");

  const btnCerrarPerfilVer = document.getElementById("btnCerrarModalPerfilVer");
  const btnCerrarPerfilVerFooter = document.getElementById("btnCerrarPerfilVerFooter");

  const btnCerrarPerfilEditar = document.getElementById("btnCerrarModalPerfilEditar");
  const btnCancelarPerfilEditar = document.getElementById("btnCancelarPerfilEditar");

  const btnAbrirCambiarPass = document.getElementById("btnAbrirCambiarPassword");
  const btnCerrarPassword = document.getElementById("btnCerrarPassword");
  const btnCancelarPassword = document.getElementById("btnCancelarPassword");

  const avatarPerfilEditar = document.getElementById("avatarPerfilEditar");
  const btnCambiarFotoEditar = document.getElementById("btnCambiarFotoEditar");
  const inputFotoPerfilEditar = document.getElementById("inputFotoPerfilEditar");

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
  const btnInfoDatosSensibles = document.getElementById("btnInfoDatosSensibles");
  const modalDatosSensibles = document.getElementById("modalDatosSensibles");
  const btnCerrarDatosSensibles = document.getElementById("btnCerrarDatosSensibles");
  const btnCancelarDatosSensibles = document.getElementById("btnCancelarDatosSensibles");
  const formDatosSensibles = document.getElementById("formDatosSensibles");
  const btnEnviarDatosSensibles = document.getElementById("btnEnviarDatosSensibles");

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
// 1. Esta función captura lo que el aprendiz tiene actualmente en su perfil
// 1. Esta función captura lo que el aprendiz tiene actualmente en su perfil
function inicializarValoresActuales() {
    const datosActuales = {};
    
    // Capturamos el Nombre (está en el h2 del modal Ver Perfil)
    const nombreVer = document.querySelector("#modalPerfilVer h2");
    if (nombreVer) datosActuales['nombre'] = nombreVer.textContent.trim();

    // Capturamos los demás datos buscando por sus etiquetas en el modal de Ver Perfil
    const etiquetas = document.querySelectorAll("#modalPerfilVer p.text-xs.font-medium.text-slate-400");
    etiquetas.forEach(p => {
        const textoEtiqueta = p.textContent.toLowerCase();
        const valorActual = p.nextElementSibling ? p.nextElementSibling.textContent.trim() : "";
        
        if (textoEtiqueta.includes("tipo de documento")) datosActuales['tipo_documento'] = valorActual;
        if (textoEtiqueta.includes("número de documento")) datosActuales['numero_documento'] = valorActual;
        if (textoEtiqueta.includes("correo")) datosActuales['correo'] = valorActual;
    });

    // Guardamos esos valores en los "datos-valor-actual" de los inputs del modal de solicitud
    for (const [campo, valor] of Object.entries(datosActuales)) {
        const inputSolicitud = document.querySelector(`#field_${campo} input, #field_${campo} select`);
        if (inputSolicitud) {
            inputSolicitud.setAttribute('data-valor-actual', valor);
            // Esto ayuda visualmente al usuario
            inputSolicitud.placeholder = "Actual: " + valor; 
        }
    }
}

// 2. El evento de envío (Submit) corregido
if (formDatosSensibles) {
    formDatosSensibles.addEventListener("submit", async (e) => {
        e.preventDefault();
        
        const btnSubmit = formDatosSensibles.querySelector('button[type="submit"]');
        const textoOriginal = btnSubmit ? btnSubmit.innerHTML : '';
        
        try {
            const datosSolicitados = {};
            const selected = Array.from(sensibleChecks || []).filter((c) => c.checked);

            if (selected.length === 0) {
                toastError("Selecciona al menos un dato para cambiar.");
                return;
            }

            for (const chk of selected) {
                const key = chk.getAttribute("data-sensible");
                const fieldWrap = document.getElementById("field_" + key);
                const inputNuevo = fieldWrap.querySelector("input, select");
                
                if (!inputNuevo || !inputNuevo.value.trim()) {
                    toastError(`Ingresa el nuevo valor para ${obtenerNombreCampo(key)}`);
                    return;
                }

                const nuevoValor = inputNuevo.value.trim();
                // ✅ LEEMOS EL DATO QUE CAPTURAMOS AL ABRIR EL MODAL
                const valorAnterior = inputNuevo.getAttribute('data-valor-actual') || "";

                if (nuevoValor === valorAnterior && valorAnterior !== "") {
                    toastError(`El valor de ${obtenerNombreCampo(key)} es igual al actual.`);
                    return;
                }

                datosSolicitados[key] = {
                    anterior: valorAnterior,
                    nuevo: nuevoValor,
                    campo_nombre: obtenerNombreCampo(key)
                };
            }

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
                closeDatosSensibles();
                setTimeout(() => location.reload(), 1000);
            } else {
                toastError(data.error);
            }

        } catch (error) {
            toastError("Error al conectar con el servidor.");
        } finally {
            if (btnSubmit) { btnSubmit.disabled = false; btnSubmit.innerHTML = textoOriginal; }
        }
    });
}

  // Envío del formulario de datos sensibles
  // Envío del formulario de datos sensibles - VERSIÓN MEJORADA
  if (formDatosSensibles) {
    formDatosSensibles.addEventListener("submit", async (e) => {
      e.preventDefault();
      const btnSubmit = formDatosSensibles.querySelector('button[type="submit"]');
      const textoOriginal = btnSubmit ? btnSubmit.innerHTML : '';

      try {
        const datosSolicitados = {};
        const selected = Array.from(sensibleChecks || []).filter((c) => c.checked);

        if (selected.length === 0) {
          toastError("Selecciona al menos un dato para cambiar.");
          return;
        }

        for (const chk of selected) {
          const key = chk.getAttribute("data-sensible");
          const fieldWrap = document.getElementById("field_" + key);
          const inputNuevo = fieldWrap.querySelector("input, select");

          if (!inputNuevo || !inputNuevo.value.trim()) {
            toastError(`Por favor ingresa el nuevo valor para ${obtenerNombreCampo(key)}`);
            return;
          }

          const nuevoValor = inputNuevo.value.trim();
          // ✅ AQUÍ ESTÁ LA MAGIA: Usamos el valor que guardamos al abrir el modal
          const valorAnterior = inputNuevo.getAttribute('data-valor-actual') || "";

          if (nuevoValor === valorAnterior) {
            toastError(`El valor de ${obtenerNombreCampo(key)} es igual al que ya tienes.`);
            return;
          }

          datosSolicitados[key] = {
            anterior: valorAnterior,
            nuevo: nuevoValor,
            campo_nombre: obtenerNombreCampo(key)
          };
        }

        if (btnSubmit) { btnSubmit.disabled = true; btnSubmit.innerHTML = 'Enviando...'; }

        const formData = new FormData();
        formData.append("datos_cambiados", JSON.stringify(datosSolicitados));

        const resp = await fetch("src/controllers/usuario_controller.php?accion=solicitar_cambio_datos_sensibles", {
          method: "POST",
          body: formData
        });

        const data = await resp.json();
        if (data.success) {
          toastSuccess("✅ Solicitud enviada con éxito.");
          closeDatosSensibles();
          setTimeout(() => location.reload(), 1500);
        } else {
          toastError(data.error);
        }

      } catch (error) {
        toastError("Error de conexión");
      } finally {
        if (btnSubmit) { btnSubmit.disabled = false; btnSubmit.innerHTML = textoOriginal; }
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
    console.log('🔍 Buscando ID de usuario en múltiples fuentes...');

    // 1. Intentar desde sesión PHP (si está disponible en el DOM)
    const elementosConId = [
      // Buscar elementos con atributos data
      document.querySelector('[data-usuario-id]'),
      document.querySelector('[data-user-id]'),
      document.querySelector('[data-id-usuario]'),
      document.body,
      document.getElementById('usuario_id'),
      document.querySelector('input[name="usuario_id"], input[name="user_id"]')
    ];

    for (const el of elementosConId) {
      if (el) {
        const id = el.getAttribute?.('data-usuario-id') ||
          el.getAttribute?.('data-user-id') ||
          el.getAttribute?.('data-id-usuario') ||
          el.value;
        if (id) {
          console.log('✅ ID encontrado en elemento:', id);
          return id;
        }
      }
    }

    // 2. Intentar desde variable global
    if (window.usuarioId) {
      console.log('✅ ID encontrado en window.usuarioId:', window.usuarioId);
      return window.usuarioId;
    }

    // 3. Intentar desde localStorage (último recurso)
    try {
      const usuarioData = localStorage.getItem('usuario_data');
      if (usuarioData) {
        const parsed = JSON.parse(usuarioData);
        const id = parsed.id || parsed.usuario_id || parsed.user_id;
        if (id) {
          console.log('✅ ID encontrado en localStorage:', id);
          return id;
        }
      }
    } catch (e) {
      console.warn('No se pudo leer localStorage:', e);
    }

    // 4. Enviar una petición al servidor para obtener el ID de la sesión
    console.warn('⚠️ No se pudo obtener el ID de usuario automáticamente.');
    console.warn('Intentando obtener del servidor...');

    // Intentar obtener desde una API
    fetch('src/controllers/usuario_controller.php?accion=obtener_id_usuario')
      .then(resp => resp.json())
      .then(data => {
        if (data.id_usuario) {
          window.usuarioId = data.id_usuario;
          console.log('✅ ID obtenido del servidor:', window.usuarioId);
          return window.usuarioId;
        }
      })
      .catch(() => {
        console.error('No se pudo obtener ID del servidor');
      });

    // Retornar vacío como fallback
    console.error('❌ No se pudo obtener el ID de usuario');
    return '';
  }

  function obtenerUsuarioNombre() {
    // Buscar en múltiples lugares
    const elementos = [
      document.querySelector('[data-usuario-nombre]'),
      document.querySelector('[data-user-name]'),
      document.querySelector('[data-nombre-usuario]'),
      document.querySelector('.user-menu span.text-sm.font-medium')
    ];

    for (const el of elementos) {
      if (el) {
        const nombre = el.getAttribute?.('data-usuario-nombre') ||
          el.getAttribute?.('data-user-name') ||
          el.getAttribute?.('data-nombre-usuario') ||
          el.textContent;
        if (nombre && nombre.trim()) {
          return nombre.trim();
        }
      }
    }

    // Buscar en variable global
    if (window.usuarioNombre) return window.usuarioNombre;

    // Último recurso: buscar en localStorage
    try {
      const usuarioData = localStorage.getItem('usuario_data');
      if (usuarioData) {
        const parsed = JSON.parse(usuarioData);
        return parsed.nombre || parsed.nombre_completo || parsed.usuario_nombre || 'Usuario';
      }
    } catch (e) {
      // Ignorar error
    }

    return 'Usuario';
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
  // =====================================================
  // FUNCIÓN AUXILIAR: Actualizar contador de notificaciones
  // =====================================================
  async function actualizarContadorNotificaciones() {
    try {
      // Apuntamos directo al controlador de usuario que ya arreglamos
      const resp = await fetch('src/controllers/usuario_controller.php?accion=contador');
      const data = await resp.json();

      const badge = document.querySelector('.badge-notificaciones');
      if (badge && data.success) {
        if (data.no_leidas > 0) {
          badge.textContent = data.no_leidas > 9 ? '9+' : data.no_leidas;
          badge.style.display = 'inline-block';
          badge.classList.remove('hidden');
        } else {
          badge.style.display = 'none';
          badge.classList.add('hidden');
        }
      }
    } catch (error) {
      console.log("Error silencioso en contador:", error);
    }
  }
  // =====================================================
  // FUNCIÓN DE FALBACK: Usar WebSocket o Polling simple
  // =====================================================
  async function actualizarContadorNotificacionesFallback() {
    console.log('🔧 Usando método fallback para notificaciones');

    try {
      // Intentar obtener desde una variable global
      if (window.notificacionesContador !== undefined) {
        console.log('✅ Usando contador desde window.notificacionesContador:', window.notificacionesContador);
        actualizarBadge(window.notificacionesContador);
        return;
      }

      // Intentar desde localStorage (último valor guardado)
      const lastCount = localStorage.getItem('notificaciones_contador');
      if (lastCount) {
        const count = parseInt(lastCount);
        if (!isNaN(count)) {
          console.log('✅ Usando contador desde localStorage:', count);
          actualizarBadge(count);
          return;
        }
      }

      // Valor por defecto
      console.log('⚠️ Usando valor por defecto: 0');
      actualizarBadge(0);

    } catch (error) {
      console.error('Error en método fallback:', error);
      actualizarBadge(0);
    }
  }

  function actualizarBadge(count) {
    const badge = document.querySelector('.badge-notificaciones');
    if (!badge) return;

    if (count > 0) {
      badge.textContent = count > 9 ? '9+' : count.toString();
      badge.style.display = 'inline-block';
      badge.classList.remove('hidden');

      // Guardar en localStorage para futuras referencias
      localStorage.setItem('notificaciones_contador', count.toString());
    } else {
      badge.style.display = 'none';
      badge.classList.add('hidden');
      localStorage.removeItem('notificaciones_contador');
    }
  }

  // =====================================================
  // FUNCIÓN INTELIGENTE QUE PRUEBA AMBOS MÉTODOS
  // =====================================================
  async function actualizarContadorNotificacionesInteligente() {
    console.log('🤖 Actualización inteligente de notificaciones');

    // Primero intentar el método principal
    const resultado = await actualizarContadorNotificaciones();

    // Si el método principal falló completamente, usar fallback
    if (resultado.error && resultado.error.includes('No se pudieron obtener')) {
      console.log('🔧 Cambiando a método fallback');
      await actualizarContadorNotificacionesFallback();
    }
  }

  // =====================================================
  // CONFIGURAR ACTUALIZACIÓN AUTOMÁTICA (MEJORADA)
  // =====================================================
  let intervaloContador = null;
  let intentosFallidos = 0;
  const MAX_INTENTOS_FALLIDOS = 3;

  function iniciarActualizacionAutomatica() {
    // Limpiar intervalo previo si existe
    if (intervaloContador) {
      clearInterval(intervaloContador);
      intervaloContador = null;
    }

    // Actualizar inmediatamente
    actualizarContadorNotificacionesInteligente().then(() => {
      console.log('✅ Contador actualizado inicialmente');
    }).catch(error => {
      console.error('❌ Error en actualización inicial:', error);
      intentosFallidos++;
    });

    // Configurar actualización periódica con backoff
    const intervalo = intentosFallidos > 0 ? 60000 : 30000; // 60s si ha fallado, 30s normal
    intervaloContador = setInterval(async () => {
      try {
        await actualizarContadorNotificacionesInteligente();
        intentosFallidos = 0; // Resetear contador de fallos si funciona
      } catch (error) {
        console.error('❌ Error en actualización periódica:', error);
        intentosFallidos++;

        // Si falla muchas veces, aumentar intervalo
        if (intentosFallidos >= MAX_INTENTOS_FALLIDOS) {
          console.warn(`⚠️ Demasiados fallos (${intentosFallidos}), deteniendo actualización automática`);
          detenerActualizacionAutomatica();
        }
      }
    }, intervalo);
  }

  function detenerActualizacionAutomatica() {
    if (intervaloContador) {
      clearInterval(intervaloContador);
      intervaloContador = null;
    }
    console.log('🛑 Actualización automática detenida');
  }

  // Iniciar cuando se carga la página
  document.addEventListener('DOMContentLoaded', function () {
    // Pequeño delay para asegurar que todo esté cargado
    setTimeout(() => {
      iniciarActualizacionAutomatica();
    }, 1000);

    // Actualizar después de ciertas acciones
    document.addEventListener('notificacion-actualizada', () => {
      setTimeout(actualizarContadorNotificacionesInteligente, 500);
    });
  });

  // Detener al salir de la página
  window.addEventListener('beforeunload', detenerActualizacionAutomatica);

  // =====================================================
  // FUNCIÓN DE DIAGNÓSTICO MEJORADA
  // =====================================================
  async function diagnosticarContadorCompleto() {
    console.log('=== DIAGNÓSTICO COMPLETO DEL CONTADOR ===');

    const endpoints = [
      'src/controllers/usuario_controller.php?accion=contar_notificaciones',
      'src/utils/notificaciones_sesion.php?accion=contar',
      'src/controllers/usuario_controller.php?accion=contador'
    ];

    for (const endpoint of endpoints) {
      console.log(`\n🔍 Analizando endpoint: ${endpoint}`);

      try {
        const resp = await fetch(endpoint);
        console.log(`Status: ${resp.status} ${resp.statusText}`);
        console.log(`Content-Type: ${resp.headers.get('content-type')}`);

        const text = await resp.text();

        if (text.length === 0) {
          console.log('❌ RESPUESTA VACÍA');
        } else if (text.length > 500) {
          console.log(`📄 Primeros 500 chars:\n${text.substring(0, 500)}`);
        } else {
          console.log(`📄 Respuesta completa:\n${text}`);
        }

        // Detectar problemas comunes
        if (text.includes('<!DOCTYPE') || text.includes('<html')) {
          console.log('⚠️ PROBLEMA: El endpoint devuelve HTML completo');

          // Buscar mensajes de error específicos
          if (text.includes('login') || text.includes('iniciar sesión')) {
            console.log('⚠️ POSIBLE CAUSA: Redirección a página de login (sesión expirada)');
          }
        }

        if (text.includes('Parse error') || text.includes('Fatal error')) {
          console.log('❌ ERROR PHP ENCONTRADO');
          const errorMatch = text.match(/<b>(.*?)<\/b>:(.*?)(?:<br|$)/);
          if (errorMatch) {
            console.log(`   Tipo: ${errorMatch[1]}`);
            console.log(`   Mensaje: ${errorMatch[2].trim()}`);
          }
        }

        // Intentar extraer JSON
        try {
          const jsonMatch = text.match(/\{[\s\S]*\}/);
          if (jsonMatch) {
            const data = JSON.parse(jsonMatch[0]);
            console.log('✅ JSON EXTRAÍDO EXITOSAMENTE:', data);
          }
        } catch (jsonError) {
          console.log('❌ NO SE PUDO EXTRAER JSON:', jsonError.message);
        }

      } catch (error) {
        console.log(`❌ ERROR DE CONEXIÓN: ${error.message}`);
      }
    }

    console.log('\n=== FIN DEL DIAGNÓSTICO ===');

    // Mostrar consejos
    console.log('\n💡 CONSEJOS PARA SOLUCIONAR:');
    console.log('1. Verifica que los archivos PHP existan');
    console.log('2. Revisa los logs de errores de PHP');
    console.log('3. Verifica que la sesión esté iniciada');
    console.log('4. Asegúrate que los scripts no tengan salida antes del JSON');
    console.log('5. Revisa si hay redirecciones automáticas');

    // =====================================================
    // CONFIGURAR ACTUALIZACIÓN AUTOMÁTICA DEL CONTADOR
    // =====================================================

    // Actualizar cada 30 segundos (ajustable)
    let intervaloContador = null;

    function iniciarActualizacionAutomatica() {
      // Limpiar intervalo previo si existe
      if (intervaloContador) {
        clearInterval(intervaloContador);
      }

      // Actualizar inmediatamente
      actualizarContadorNotificaciones();

      // Configurar actualización periódica
      intervaloContador = setInterval(actualizarContadorNotificaciones, 30000); // 30 segundos
    }

    // Detener la actualización automática
    function detenerActualizacionAutomatica() {
      if (intervaloContador) {
        clearInterval(intervaloContador);
        intervaloContador = null;
      }
    }

    // Iniciar cuando se carga la página
    document.addEventListener('DOMContentLoaded', function () {
      iniciarActualizacionAutomatica();

      // También actualizar después de ciertas acciones
      document.addEventListener('notificacion-actualizada', actualizarContadorNotificaciones);
    });

    // Para páginas que no necesitan el contador
    window.addEventListener('beforeunload', detenerActualizacionAutomatica);

    // =====================================================
    // FUNCIÓN AUXILIAR: Para disparar eventos personalizados
    // =====================================================
    function dispararEventoNotificacionActualizada() {
      const event = new CustomEvent('notificacion-actualizada');
      document.dispatchEvent(event);
    }

    // =====================================================
    // VERSIÓN ALTERNATIVA SI EL ENDPOINT NO FUNCIONA
    // =====================================================

    async function actualizarContadorNotificacionesAlternativo() {
      try {
        // Intentar diferentes endpoints posibles
        const endpoints = [
          'src/utils/notificaciones_sesion.php?accion=contar',
          'src/controllers/usuario_controller.php?accion=contador',
          'src/controllers/usuario_controller.php?accion=contar_notificaciones'
        ];

        for (const endpoint of endpoints) {
          try {
            const resp = await fetch(endpoint);
            if (resp.ok) {
              const text = await resp.text();
              if (text && text.trim() !== '') {
                const data = JSON.parse(text);
                const badge = document.querySelector('.badge-notificaciones');
                if (badge) {
                  const count = data.no_leidas || data.total || data.count || 0;
                  if (count > 0) {
                    badge.textContent = count > 9 ? '9+' : count;
                    badge.style.display = 'inline-block';
                  } else {
                    badge.style.display = 'none';
                  }
                }
                return; // Salir si encontró un endpoint que funciona
              }
            }
          } catch (e) {
            console.log(`Endpoint ${endpoint} no funciona:`, e.message);
            continue; // Intentar el siguiente endpoint
          }
        }

        // Si ningún endpoint funciona, ocultar el badge
        const badge = document.querySelector('.badge-notificaciones');
        if (badge) {
          badge.style.display = 'none';
        }

      } catch (error) {
        console.error('Error en contador alternativo:', error);
      }
    }

    // =====================================================
    // FUNCIÓN DE PRUEBA PARA DIAGNOSTICAR EL PROBLEMA
    // =====================================================

    async function diagnosticarContador() {
      console.log('=== DIAGNÓSTICO DEL CONTADOR DE NOTIFICACIONES ===');

      try {
        const url = 'src/utils/notificaciones_sesion.php?accion=contar';
        console.log('URL solicitada:', url);

        const resp = await fetch(url);
        console.log('Estado HTTP:', resp.status, resp.statusText);
        console.log('Headers:', Object.fromEntries(resp.headers.entries()));

        const text = await resp.text();
        console.log('Longitud de respuesta:', text.length);
        console.log('Primeros 500 caracteres:', text.substring(0, 500));

        if (text.length === 0) {
          console.error('ERROR: La respuesta está completamente vacía');
          console.log('Posibles causas:');
          console.log('1. El archivo PHP no existe');
          console.log('2. Hay un error de sintaxis en el PHP');
          console.log('3. La sesión no está iniciada');
          console.log('4. El script PHP se detuvo antes de generar salida');
        } else {
          console.log('Intento de parsear JSON...');
          try {
            const data = JSON.parse(text);
            console.log('JSON parseado exitosamente:', data);
          } catch (jsonError) {
            console.error('Error parseando JSON:', jsonError.message);

            // Buscar errores PHP en la respuesta
            if (text.includes('Parse error') || text.includes('Fatal error') || text.includes('Warning')) {
              console.error('SE DETECTÓ UN ERROR DE PHP EN LA RESPUESTA');
              const errorMatch = text.match(/<b>(.*?)<\/b>:(.*?)<br/);
              if (errorMatch) {
                console.error('Error PHP:', errorMatch[1], '-', errorMatch[2]);
              }
            }
          }
        }

        console.log('=== FIN DEL DIAGNÓSTICO ===');
      } catch (error) {
        console.error('Error en diagnóstico:', error);
      }


      // Para ejecutar el diagnóstico, en la consola del navegador:
      diagnosticarContador();
    }
  }
});