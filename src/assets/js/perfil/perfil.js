/* ============================================================
   PROFILE UI CONTROLLER (perfil.js)
   - Safe Lucide rendering
   - User menu dropdown (click toggle)
   - Notifications dropdown (click toggle)
   - Profile modals: View / Edit / Sensitive Data / Password
   - Form submissions: Edit profile + Sensitive data request
   - Avatar preview
============================================================ */

/* Guard to prevent double execution */
if (!window.__perfilJSLoaded) {
  window.__perfilJSLoaded = true;

  document.addEventListener("DOMContentLoaded", function () {
    // =====================================================
    // 1) LUCIDE ICONS - SAFE RENDER (RETRY)
    // =====================================================
    function renderLucideIconsSafe() {
      if (window.lucide && typeof window.lucide.createIcons === "function") {
        window.lucide.createIcons();
        return true;
      }
      return false;
    }

    renderLucideIconsSafe();

    let lucideTry = 0;
    const lucideInterval = setInterval(() => {
      lucideTry++;
      const ok = renderLucideIconsSafe();
      if (ok || lucideTry >= 10) clearInterval(lucideInterval);
    }, 150);

    window.renderLucideIconsSafe = renderLucideIconsSafe;

    // =====================================================
    // 2) DOM REFERENCES
    // =====================================================
    const modalPerfilVer = document.getElementById("modalPerfilVer");
    const modalPerfilEditar = document.getElementById("modalPerfilEditar");
    const modalPassword = document.getElementById("modalPassword");
    const modalDatosSensibles = document.getElementById("modalDatosSensibles");

    const btnVerPerfil = document.getElementById("btnVerPerfil");
    const btnEditarPerfil = document.getElementById("btnEditarPerfil");
    const btnInfoDatosSensibles = document.getElementById("btnInfoDatosSensibles");

    const btnAbrirCambiarPassword = document.getElementById("btnAbrirCambiarPassword");

    const formEditarPerfil = document.getElementById("formEditarPerfil");
    const formDatosSensibles = document.getElementById("formDatosSensibles");
    const formCambiarPassword = document.getElementById("formCambiarPassword");

    const avatarPerfilEditar = document.getElementById("avatarPerfilEditar");
    const inputFotoPerfilEditar = document.getElementById("inputFotoPerfilEditar");
    const btnGuardarPerfil = document.getElementById("btnGuardarPerfil") || document.getElementById("btnGuardarEditarPerfil");


    const sensibleChecks = document.querySelectorAll('input[type="checkbox"][data-sensible]');

    // User menu
    const btnUserMenu = document.getElementById("btnUserMenu");
    const userMenuDropdown = document.getElementById("userMenuDropdown");

    // Notifications
    const contenedorNotificaciones = document.getElementById("contenedor-notificaciones");
    const btnCampana = contenedorNotificaciones?.querySelector("button");
    const dropdownNotificaciones = document.getElementById("dropdown-notificaciones");

    // =====================================================
    // ✅ INTERNAL LOCKS (AVOID DOUBLE SUBMIT / DOUBLE FETCH)
    // =====================================================
    const __locks = {
      editarPerfil: false,
      datosSensibles: false,
      cambiarPassword: false,
      refrescarNotifs: false,
      contadorNotifs: false,
    };

    // =====================================================
    // ✅ BIND ONCE HELPER (PREVENT DOUBLE EVENT LISTENERS)
    // ✅ (NO TOCA TU BASE, SOLO EVITA QUE SE DUPLIQUEN EVENTOS)
    // =====================================================
    function bindOnce(el, key, handler, options) {
      if (!el) return;
      const k = "__bound_" + key;
      if (el.dataset && el.dataset[k] === "1") return;
      if (el.dataset) el.dataset[k] = "1";
      el.addEventListener(handler.__type || key, handler, options);
    }

    /* ============================================================
       SIGA GLOBAL TOASTS (Flowbite-style)
       - Same design as your "Usuarios" module
       - Prevents duplicated toasts
       - Prevents function override between modules
    ============================================================ */

    if (!window.__SIGA_TOASTS_READY__) {
      window.__SIGA_TOASTS_READY__ = true;

      // ✅ Anti-duplicate (same msg in <900ms)
      let __lastToast = { msg: "", ts: 0 };

      function getOrCreateFlowbiteContainer() {
        let container = document.getElementById("flowbite-alert-container");

        if (!container) {
          container = document.createElement("div");
          container.id = "flowbite-alert-container";

          container.className =
            "fixed top-6 left-1/2 -translate-x-1/2 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none";

          // ✅ Force top-right position like you already did
          container.style.left = "auto";
          container.style.right = "1.5rem";
          container.style.transform = "none";

          document.body.appendChild(container);
        }

        return container;
      }

      // ✅ Ensures animation exists even if class not defined
      function ensureFadeAnimationClass() {
        if (document.getElementById("__siga_toast_anim__")) return;

        const st = document.createElement("style");
        st.id = "__siga_toast_anim__";
        st.textContent = `
          @keyframes sigaFadeUp {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0px); }
          }
          .animate-fade-in-up {
            animation: sigaFadeUp .25s ease-out forwards;
          }
        `;
        document.head.appendChild(st);
      }

      /**
       * showFlowbiteAlert(type, message)
       * showFlowbiteAlert(type, title, message)  ✅ compatible
       */
      window.showFlowbiteAlert = function (type, a, b) {
        ensureFadeAnimationClass();

        // ✅ allow both signatures
        let message = "";
        let titleText = "";

        if (typeof b === "undefined") {
          message = String(a || "");
        } else {
          titleText = String(a || "");
          message = String(b || "");
        }

        // ✅ prevent duplicates
        const now = Date.now();
        if (message === __lastToast.msg && now - __lastToast.ts < 900) return;
        __lastToast = { msg: message, ts: now };

        const container = getOrCreateFlowbiteContainer();
        const wrapper = document.createElement("div");

        // Default style: warning
        let borderColor = "border-amber-500";
        let textColor = "text-amber-900";
        let title = titleText || "Advertencia";

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
          title = titleText || "Éxito";
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
          title = titleText || "Información";
          iconSVG = `
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
                fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm1 15H9v-5h2Zm0-7H9V6h2Z"/>
            </svg>
          `;
        }

        if (type === "danger" || type === "error") {
          borderColor = "border-red-500";
          textColor = "text-red-900";
          title = titleText || "Error";
          iconSVG = `
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg"
                fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 0a10 10 0 1 0 10 10A10.011 10.011 0 0 0 10 0Zm3.536 13.536-1.414 1.414L10 11.414 7.879 14.95l-1.414-1.414L8.586 10 6.465 7.879l1.414-1.414L10 8.586l2.121-2.121 1.414 1.414L11.414 10l2.122 3.536Z"/>
            </svg>
          `;
        }

        wrapper.className = `
          relative flex items-center w-full mx-auto pointer-events-auto
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
            <p class="font-semibold">${title}</p>
            <p class="mt-0.5 text-sm">${message}</p>
          </div>
        `;

        container.appendChild(wrapper);

        // Smooth fade-in
        requestAnimationFrame(() => {
          wrapper.classList.remove("opacity-0", "-translate-y-2");
          wrapper.classList.add("opacity-100", "translate-y-0");
        });

        // Auto hide
        setTimeout(() => {
          wrapper.classList.add("opacity-0", "-translate-y-2");
          wrapper.classList.remove("opacity-100", "translate-y-0");
          setTimeout(() => wrapper.remove(), 250);
        }, 4000);
      };

      // ✅ Global helpers
      window.toastError = (msg) => window.showFlowbiteAlert("warning", msg);
      window.toastWarning = (msg) => window.showFlowbiteAlert("warning", msg); // ✅ FIX: EXISTIA LLAMADO PERO NO FUNCIÓN
      window.toastSuccess = (msg) => window.showFlowbiteAlert("success", msg);
      window.toastInfo = (msg) => window.showFlowbiteAlert("info", msg);
      window.toastDanger = (msg) => window.showFlowbiteAlert("danger", msg);
    }

    // =====================================================
    // ✅ ALERTA INLINE DENTRO DEL MODAL DE DATOS SENSIBLES
    // ✅ MISMO DISEÑO FLOWBITE PRO QUE ME MOSTRASTE
    // ✅ NO TOCA TU LOGICA, SOLO UI
    // =====================================================
    function clearAlertDatosSensibles() {
      // ✅ elimina si existe el alert inyectado
      const existing = modalDatosSensibles?.querySelector("#alert-datos-sensibles");
      if (existing) existing.remove();

      // ✅ si existe contenedor fijo (si lo tienes en HTML), lo limpia
      const container = document.getElementById("alertaDatosSensiblesContainer");
      if (container) container.innerHTML = "";
    }


    /**
     * ✅ Compatibilidad total con tu base:
     * - Si lo llamas con 2 params: showAlertDatosSensibles(type, message)
     * - Si lo llamas con 3 params: showAlertDatosSensibles(type, title, message)
     */
    function showAlertDatosSensibles(type, a, b) {
      if (!modalDatosSensibles || !formDatosSensibles) return;

      // ✅ Detectar firma (2 o 3 argumentos)
      let title = "Revisa tu solicitud";
      let message = "";

      if (typeof b === "undefined") {
        // (type, message)
        message = String(a || "");
      } else {
        // (type, title, message)
        title = String(a || "Aviso");
        message = String(b || "");
      }

      // ✅ Limpia lo anterior sin tocar nada de tu base
      clearAlertDatosSensibles();

      // ✅ Config visual EXACTO al que me enviaste
      const map = {
        success: {
          base: "bg-emerald-50 text-emerald-800 border-emerald-200",
          icon: "check-circle",
        },
        danger: {
          base: "bg-red-50 text-red-800 border-red-200",
          icon: "x-circle",
        },
        warning: {
          base: "bg-amber-50 text-amber-800 border-amber-200",
          icon: "alert-triangle",
        },
        info: {
          base: "bg-blue-50 text-blue-800 border-blue-200",
          icon: "info",
        },
      };

      const cfg = map[type] || map.info;

      // ✅ Si existe el contenedor fijo en HTML, lo usamos
      let container = document.getElementById("alertaDatosSensiblesContainer");

      // ✅ Si no existe, mantenemos tu comportamiento base: insert beforebegin del form
      const wrap = document.createElement("div");
      wrap.id = "alert-datos-sensibles";
      wrap.className = container ? "" : "px-4 pt-4";

      wrap.innerHTML = `
        <div class="flex items-start gap-3 rounded-xl border ${cfg.base} px-4 py-3">
          <div class="mt-0.5 flex h-9 w-9 items-center justify-center rounded-full bg-white/60">
            <i data-lucide="${cfg.icon}" class="h-5 w-5"></i>
          </div>
          <div class="flex-1">
            <p class="text-sm font-semibold leading-5">${title}</p>
            <p class="text-xs leading-4 mt-1 break-words">${message}</p>
          </div>
          <button type="button"
            class="h-8 w-8 inline-flex items-center justify-center rounded-lg hover:bg-white/50 transition"
            aria-label="Cerrar"
            data-close-inline="1"
          >
            <i data-lucide="x" class="h-4 w-4"></i>
          </button>
        </div>
      `;

      // ✅ Insert sin romper tu base
      if (container) {
        container.innerHTML = "";
        container.appendChild(wrap);
      } else {
        formDatosSensibles.insertAdjacentElement("beforebegin", wrap);
      }

      wrap.querySelector('[data-close-inline="1"]')?.addEventListener("click", () => {
        clearAlertDatosSensibles();
      });

      renderLucideIconsSafe();
    }

    // =====================================================
    // ✅ FIELD VALIDATION HELPERS (INLINE + TOAST)
    // =====================================================
    function clearFieldError(input) {
      if (!input) return;
      input.classList.remove("border-red-500", "ring-2", "ring-red-200");

      const wrap =
        input.closest(".field-wrap") || input.closest("[id^='field_']") || input.parentElement;
      const err = wrap?.querySelector(".field-error-msg");
      if (err) err.remove();
    }

    function setFieldError(input, message) {
      if (!input) return;

      clearFieldError(input);

      input.classList.add("border-red-500", "ring-2", "ring-red-200");

      const wrap =
        input.closest(".field-wrap") || input.closest("[id^='field_']") || input.parentElement;
      if (!wrap) return;

      const p = document.createElement("p");
      p.className = "field-error-msg mt-1 text-[11px] text-red-600 font-medium";
      p.textContent = message;

      wrap.appendChild(p);
    }

    function normalizeText(value) {
      return String(value || "").trim().replace(/\s+/g, " ");
    }

    function isValidEmail(value) {
      const v = String(value || "").trim();
      return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(v);
    }

    function isValidDocumento(value) {
      const v = String(value || "").trim();
      return /^[0-9]{6,12}$/.test(v);
    }

    function isStrongPassword(value) {
      const v = String(value || "");
      return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(v);
    }

    function allowedTipoDocumento(value) {
      const v = String(value || "").trim().toUpperCase();
      return ["CC", "TI", "CE"].includes(v);
    }

    // =====================================================
    // ✅ NUEVAS VALIDACIONES PARA EDITAR PERFIL (INTEGRADAS)
    // ✅ SIN TOCAR TU BASE, SOLO SE AÑADEN HELPERS
    // =====================================================
    function isValidNombreApellido(value) {
      const v = normalizeText(value);
      // ✅ Letras + espacios + acentos
      return /^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]{2,40}$/.test(v);
    }

    function isValidTelefono(value) {
      const v = String(value || "").trim();

      // ✅ permite vacío (no obligatorio)
      if (!v) return true;

      // ✅ 7 a 10 dígitos
      if (!/^[0-9]{7,10}$/.test(v)) return false;

      // ✅ si es 10 dígitos (celular), inicia por 3
      if (v.length === 10 && !v.startsWith("3")) return false;

      return true;
    }

    function isValidDireccion(value) {
      const v = normalizeText(value);

      // ✅ permite vacío (no obligatorio)
      if (!v) return true;

      // ✅ longitud razonable
      return v.length >= 5 && v.length <= 80;
    }

    function formHasChanges(form) {
      if (!form) return true;

      const inputs = form.querySelectorAll("input, select, textarea");
      for (const el of inputs) {
        const original = el.getAttribute("data-original");
        if (original === null) continue;

        const current = normalizeText(el.value);
        if (current !== normalizeText(original)) return true;
      }
      return false;
    }

    // =====================================================
    // ✅ SAFE JSON PARSER (PREVENTS "Unexpected token <")
    // =====================================================
    async function safeJson(resp) {
      try {
        return await resp.json();
      } catch (e) {
        return { success: false, error: "Respuesta inválida del servidor." };
      }
    }

    // =====================================================
    // 4) SENSITIVE DATA LOGIC (PLACEHOLDERS + CURRENT VALUES)
    // =====================================================
    function inicializarValoresActuales() {
      if (!window.userData) return;

      const nombreCompleto =
        window.userData.nombre_completo ||
        window.userData.nombreCompleto ||
        normalizeText((window.userData.nombre || "") + " " + (window.userData.apellido || ""));

      const mapping = {
        nombre: nombreCompleto,
        tipo_documento: window.userData.tipo_documento,
        numero_documento: window.userData.numero_documento,
        correo: window.userData.correo,
      };

      for (const [campo, valor] of Object.entries(mapping)) {
        const fieldWrap = document.getElementById(`field_${campo}`);
        if (!fieldWrap) continue;

        const input = fieldWrap.querySelector("input, select");
        if (!input) continue;

        input.setAttribute("data-valor-actual", valor || "");

        if (input.tagName === "INPUT") {
          input.placeholder = "Actual: " + (valor || "No registrado");
        }
      }
    }

    // =====================================================
    // ✅ FIX 1: CARGAR DATOS EN MODAL EDITAR PERFIL (SI VIENEN VACÍOS)
    // ✅ NO TOCA TU BASE: SOLO PRELLENA SI HAY window.userData Y EL CAMPO ESTÁ VACÍO
    // =====================================================
    function inicializarEditarPerfilValores() {
      if (!formEditarPerfil) return;
      if (!window.userData) return;

      const map = {
        nombre: window.userData.nombre,
        apellido: window.userData.apellido,
        correo: window.userData.correo,
        telefono: window.userData.telefono,
        direccion: window.userData.direccion,
      };

      Object.entries(map).forEach(([name, value]) => {
        const input = formEditarPerfil.querySelector(`[name="${name}"]`);
        if (!input) return;

        const current = normalizeText(input.value);
        const incoming = normalizeText(value);

        // ✅ SOLO prellenar si el input está vacío
        if (!current && incoming) {
          input.value = incoming;
        }
      });
    }

    // ✅ Reset sensitive modal state to prevent stale alerts / values
    function resetDatosSensiblesUI() {
      try {
        // ✅ limpiar alerta inline del modal
        clearAlertDatosSensibles();

        sensibleChecks.forEach((chk) => {
          chk.checked = false;
          const key = chk.getAttribute("data-sensible");
          const field = document.getElementById("field_" + key);
          if (field) field.classList.add("hidden");
        });

        if (modalDatosSensibles) {
          modalDatosSensibles.querySelectorAll("input, select").forEach((el) => {
            clearFieldError(el);
            if (el.tagName === "INPUT") el.value = "";
            if (el.tagName === "SELECT") el.value = "";
          });
        }

        if (formDatosSensibles) formDatosSensibles.reset();
      } catch {
        // silent
      }
    }

    // =====================================================
    // 5) MODAL HELPERS
    // =====================================================
    const openModal = (modal) => {
      if (!modal) return;
      modal.classList.remove("hidden");
      document.body.classList.add("overflow-hidden");

      if (modal === modalDatosSensibles) {
        inicializarValoresActuales();
      }

      // ✅ FIX 1: asegurar que el modal editar perfil SIEMPRE tenga datos
      if (modal === modalPerfilEditar) {
        inicializarEditarPerfilValores();
      }

      // ✅ NUEVO: guardar valores originales al abrir editar perfil (SIN TOCAR TU BASE)
      if (modal === modalPerfilEditar && formEditarPerfil) {
        formEditarPerfil.querySelectorAll("input, select, textarea").forEach((el) => {
          if (!el.name) return;
          el.setAttribute("data-original", normalizeText(el.value || ""));
          clearFieldError(el);
        });
      }

      renderLucideIconsSafe();
    };

    const closeModal = (modal) => {
      if (!modal) return;
      modal.classList.add("hidden");
      document.body.classList.remove("overflow-hidden");

      // ✅ Clean sensitive modal each time it closes
      if (modal === modalDatosSensibles) {
        resetDatosSensiblesUI();
      }
    };

    function bindBackdropClose(modal) {
      if (!modal) return;
      if (modal.dataset && modal.dataset.__backdropBound === "1") return;
      if (modal.dataset) modal.dataset.__backdropBound = "1";

      modal.addEventListener("click", (e) => {
        if (e.target === modal) {
          closeModal(modal);
        }
      });
    }

    bindBackdropClose(modalPerfilVer);
    bindBackdropClose(modalPerfilEditar);
    bindBackdropClose(modalPassword);
    bindBackdropClose(modalDatosSensibles);

    // =====================================================
    // 6) USER MENU DROPDOWN (CLICK TOGGLE)
    // =====================================================
    function closeUserMenu() {
      if (userMenuDropdown) userMenuDropdown.classList.add("hidden");
    }

    function toggleUserMenu() {
      userMenuDropdown?.classList.toggle("hidden");
    }

    if (btnUserMenu && !btnUserMenu.dataset.__bound_click_user) {
      btnUserMenu.dataset.__bound_click_user = "1";
      btnUserMenu.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggleUserMenu();
      });
    }

    if (userMenuDropdown && !userMenuDropdown.dataset.__bound_click_userdd) {
      userMenuDropdown.dataset.__bound_click_userdd = "1";
      userMenuDropdown.addEventListener("click", (e) => {
        e.stopPropagation();
      });
    }

    // =====================================================
    // 6.1) NOTIFICATIONS DROPDOWN (CLICK TOGGLE)
    // =====================================================
    function abrirNotificacionesDropdown() {
      if (!dropdownNotificaciones) return;
      dropdownNotificaciones.classList.remove("hidden");

      refrescarDropdownNotificaciones();
      renderLucideIconsSafe();
    }

    function cerrarNotificacionesDropdown() {
      if (!dropdownNotificaciones) return;
      dropdownNotificaciones.classList.add("hidden");
    }

    if (btnCampana && !btnCampana.dataset.__bound_click_bell) {
      btnCampana.dataset.__bound_click_bell = "1";
      btnCampana.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();

        if (!dropdownNotificaciones) return;

        const estaAbierto = !dropdownNotificaciones.classList.contains("hidden");
        if (estaAbierto) {
          cerrarNotificacionesDropdown();
        } else {
          abrirNotificacionesDropdown();
        }
      });
    }

    if (dropdownNotificaciones && !dropdownNotificaciones.dataset.__bound_click_drop) {
      dropdownNotificaciones.dataset.__bound_click_drop = "1";
      dropdownNotificaciones.addEventListener("click", (e) => {
        e.stopPropagation();
      });
    }

    if (!document.body.dataset.__bound_doc_click_global) {
      document.body.dataset.__bound_doc_click_global = "1";
      document.addEventListener("click", () => {
        closeUserMenu();
        cerrarNotificacionesDropdown();
      });
    }

    // =====================================================
    // 7) BUTTONS: OPEN MODALS
    // =====================================================
    // Fetch latest profile from server and render modal before showing it.
    async function fetchUserLatest(id) {
      if (!id) return null;
      try {
        const resp = await fetch(`src/controllers/usuario_controller.php?accion=obtener&id_usuario=${encodeURIComponent(id)}&t=${Date.now()}`, { cache: 'no-store' });
        const data = await safeJson(resp);

        // Accept either { ...user } or { success:true, ... }
        if (!data) return null;

        // If backend returns wrapper like { usuario: {...} } or { data: {...} }
        const possible = data.usuario || data.data || data.usuario || data;

        // Some endpoints may return success boolean; ensure object has id
        if (possible && (possible.id_usuario || possible.id)) return possible;

        // If data itself contains user fields
        if (data.id_usuario || data.id) return data;

        return null;
      } catch (e) {
        console.error('Error fetching latest user:', e);
        return null;
      }
    }

    function renderPerfilModalFromUser(user) {
      // Update the existing modal elements in-place to preserve original markup and styles
      if (!modalPerfilVer || !user) return;

      try {
        // Avatar
        const avatarWrap = modalPerfilVer.querySelector('.avatar-wrapper');
        if (avatarWrap) {
          const foto = user.foto_perfil || user.foto || user.foto_url || user.avatar || null;
          // remove images/spans but keep classes
          avatarWrap.querySelectorAll('img, span:not(.sr-only)').forEach(n => n.remove());
          // reset inline background styles
          avatarWrap.style.backgroundImage = '';
          avatarWrap.style.backgroundSize = '';
          avatarWrap.style.backgroundPosition = '';
          avatarWrap.style.backgroundRepeat = '';

          if (foto) {
            avatarWrap.style.backgroundImage = `url('${foto}')`;
            avatarWrap.style.backgroundSize = 'cover';
            avatarWrap.style.backgroundPosition = 'center center';
            avatarWrap.style.backgroundRepeat = 'no-repeat';
            // ensure sr-only label exists
            if (!avatarWrap.querySelector('.sr-only')) {
              const sr = document.createElement('span');
              sr.className = 'sr-only';
              sr.textContent = `Foto de perfil de ${user.nombre_completo || ''}`;
              avatarWrap.appendChild(sr);
            }
          } else {
            const nombre = (user.nombre_completo || ((user.nombre || '') + ' ' + (user.apellido || ''))).trim();
            const initials = String(nombre || '').split(' ').map(s => s[0] || '').filter(Boolean).slice(0,2).join('').toUpperCase();
            const span = document.createElement('span');
            span.className = 'text-xl font-semibold text-primary';
            span.textContent = initials || '';
            avatarWrap.appendChild(span);
          }
        }

        // Replace the modal body with the original HTML structure (keeps original classes/styles)
        const container = modalPerfilVer.querySelector('.p-6');
        if (!container) return;

        // Normalize fields and compute labels/classes similar to header.php
        const nombreCompleto = user.nombre_completo || ((user.nombre || '') + ' ' + (user.apellido || '')).trim() || '';
        const tipoDocumento = user.tipo_documento || '';
        const numeroDocumento = user.numero_documento || '';
        const telefono = user.telefono || '';
        const direccion = user.direccion || '';
        const correo = user.correo || '';
        const fechaCreacion = user.fecha_creacion || user.created_at || '';
        const cargoRaw = user.cargo || '';
        const cargoLabel = cargoRaw ? (String(cargoRaw).replaceAll('_', ' ').split(/\s+/).map(s => s.charAt(0).toUpperCase()+s.slice(1)).join(' ')) : '';

        // rol funcional: accept several possible fields
        const rolRaw = user.rol_funcional || user.nombre_rol_funcional || user.rol_funcional_nombre || '';
        const rolLabel = rolRaw ? String(rolRaw).replaceAll('_', ' ').split(/\s+/).map(s => s.charAt(0).toUpperCase()+s.slice(1)).join(' ') : 'Sin rol asignado';
        const rolNorm = rolRaw ? String(rolRaw).toLowerCase().replaceAll(' ', '_') : '';
        let rolBadgeClass = 'badge-rolfunc-default';
        if (rolNorm === 'encargado_inventario' || (rolRaw && String(rolRaw).toLowerCase().includes('inventario'))) rolBadgeClass = 'badge-rolfunc-inventario';
        else if (rolNorm === 'encargado_bodega' || (rolRaw && String(rolRaw).toLowerCase().includes('bodega'))) rolBadgeClass = 'badge-rolfunc-bodega';

        const estadoBool = (String(user.estado || '').toLowerCase() === 'activo' || String(user.estado) === '1' || user.estado === true);
        const estadoText = estadoBool ? 'Activo' : (user.estado || 'Inactivo');

        const foto = user.foto_perfil || user.foto || user.foto_url || user.avatar || null;

        // Build HTML that mirrors the server-side modal markup
        const html = `
          <div class="flex items-start gap-4 mb-6">
            <div class="h-16 w-16 rounded-full overflow-hidden flex items-center justify-center bg-slate-100 shrink-0 avatar-wrapper" style="${foto ? `background-image: url('${foto}'); background-size: cover; background-position: center center; background-repeat: no-repeat; background-color: transparent;` : `background-color: color-mix(in srgb, var(--secondary) 39%, #ffffff 61%);`}">
              ${!foto ? `<span class="text-xl font-semibold text-primary">${(nombreCompleto.split(' ').map(s=>s[0]).filter(Boolean).slice(0,2).join('')).toUpperCase()}</span>` : `<span class="sr-only">Foto de perfil</span>`}
            </div>

            <div class="flex-1 min-w-0 pr-10">
              <h2 class="text-lg md:text-xl font-semibold text-slate-900 truncate">${escapeHTML(nombreCompleto)}</h2>

              <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium ${escapeHTML(getCargoBadgeClass(cargoRaw))}">${escapeHTML(cargoLabel)}</span>
                <span class="badge-rolfunc-base ${escapeHTML(rolBadgeClass)}">${escapeHTML(rolLabel)}</span>
                <span class="inline-flex rounded-full px-3 py-0.5 text-[11px] font-semibold ${estadoBool ? 'badge-estado-activo' : 'badge-estado-inactivo'}">${escapeHTML(estadoText)}</span>
              </div>
            </div>
          </div>

          <div class="space-y-6 text-sm">
            <div>
              <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">Datos personales</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-5">
                <div>
                  <p class="text-xs font-medium text-slate-400">Tipo de documento</p>
                  <p class="text-sm text-slate-800">${escapeHTML(tipoDocumento)}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-400">Número de documento</p>
                  <p class="text-sm text-slate-800">${escapeHTML(numeroDocumento)}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-400">Teléfono</p>
                  <p class="text-sm text-slate-800">${escapeHTML(telefono)}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-400">Dirección</p>
                  <p class="text-sm text-slate-800 break-words">${escapeHTML(direccion)}</p>
                </div>
              </div>
            </div>

            <div>
              <h3 class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">Datos de la cuenta</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-5">
                <div>
                  <p class="text-xs font-medium text-slate-400">Correo</p>
                  <p class="text-sm text-slate-800 break-all">${escapeHTML(correo)}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-400">Fecha de creación</p>
                  <p class="text-sm text-slate-800">${escapeHTML(fechaCreacion)}</p>
                </div>
              </div>
            </div>
          </div>
        `;

        container.innerHTML = html;
        // Labeled fields: Documento, Teléfono, Correo, Dirección, Registrado
        const labelEls = modalPerfilVer.querySelectorAll('.grid .text-muted-foreground');
        labelEls.forEach((labelEl) => {
          const labelText = (labelEl.textContent || '').toLowerCase().trim();
          const valueEl = labelEl.nextElementSibling; // expected to be <span class="col-span-2">...
          if (!valueEl) return;

          if (labelText.startsWith('document')) {
            valueEl.textContent = `${user.tipo_documento || ''} ${user.numero_documento || ''}`.trim();
          } else if (labelText.startsWith('tel') || labelText.includes('teléfono')) {
            valueEl.textContent = user.telefono || '';
          } else if (labelText.startsWith('correo')) {
            valueEl.textContent = user.correo || '';
          } else if (labelText.startsWith('direc') || labelText.includes('dirección')) {
            valueEl.textContent = user.direccion || '';
          } else if (labelText.startsWith('registr')) {
            valueEl.textContent = user.fecha_creacion || user.created_at || '';
          } else if (labelText.includes('programa')) {
            valueEl.textContent = user.nombre_programa || user.programa || '';
          }
        });

        // If the modal includes a dedicated program row that is hidden when empty, show/hide accordingly
        try {
          const progLabel = Array.from(modalPerfilVer.querySelectorAll('.grid .text-muted-foreground')).find(n => (n.textContent || '').toLowerCase().includes('program'));
          if (progLabel) {
            const progRow = progLabel.closest('.grid');
            if (progRow) {
              const progVal = user.nombre_programa || user.programa || '';
              progRow.style.display = progVal ? '' : 'none';
            }
          }
        } catch (e) {
          // ignore
        }

        // Update global userData so edit modal and other features preload correctly
        window.userData = window.userData || {};
        window.userData = Object.assign({}, window.userData, {
          id_usuario: user.id_usuario || user.id || window.userData.id_usuario,
          nombre_completo: user.nombre_completo || window.userData.nombre_completo,
          correo: user.correo || window.userData.correo,
          telefono: user.telefono || window.userData.telefono,
          direccion: user.direccion || window.userData.direccion,
          tipo_documento: user.tipo_documento || window.userData.tipo_documento,
          numero_documento: user.numero_documento || window.userData.numero_documento
        });
      } catch (e) {
        // no-fatal; preserve original markup
        console.error('[perfil] error actualizando modal en-place', e);
      }
    }

    // Update the small header avatar / name / role without reloading
    function updateHeaderFromUser(user) {
      try {
        const btn = document.getElementById('btnUserMenu');
        if (!btn || !user) return;

        // Avatar wrapper (first child div inside the button)
        const avatarWrap = btn.querySelector('.avatar-wrapper');
        if (avatarWrap) {
          const foto = user.foto_perfil || user.foto || user.foto_url || user.avatar || null;
          // Clear existing content
          avatarWrap.style.backgroundImage = '';
          avatarWrap.style.backgroundSize = '';
          avatarWrap.style.backgroundPosition = '';
          avatarWrap.style.backgroundRepeat = '';
          avatarWrap.innerHTML = '';

          if (foto) {
            // prefer absolute or relative URL as provided by backend
            avatarWrap.style.backgroundImage = `url('${foto}')`;
            avatarWrap.style.backgroundSize = 'cover';
            avatarWrap.style.backgroundPosition = 'center center';
            avatarWrap.style.backgroundRepeat = 'no-repeat';
          } else {
            // render initials
            const nombre = (user.nombre_completo || (user.nombre || '') + ' ' + (user.apellido || '')).trim();
            const initials = String(nombre || '').split(' ').map(s => s[0] || '').filter(Boolean).slice(0,2).join('').toUpperCase();
            const span = document.createElement('span');
            span.className = 'text-xs font-semibold text-primary';
            span.textContent = initials || '';
            avatarWrap.appendChild(span);
            // ensure a neutral background if none
            avatarWrap.style.backgroundColor = avatarWrap.style.backgroundColor || "";
          }
        }

        // Name & role (the hidden md:flex block inside the button)
        const infoBlock = btn.querySelector('div.hidden.flex-col, div.md\\:flex');
        if (infoBlock) {
          const spans = infoBlock.querySelectorAll('span');
          const nombreSpan = spans[0];
          const rolSpan = spans[1];

          const nombre = user.nombre_completo || (user.nombre ? ((user.nombre || '') + ' ' + (user.apellido || '')).trim() : window.userData?.nombre_completo);
          if (nombreSpan) nombreSpan.textContent = String(nombre || '').split(' ').slice(0,2).join(' ');

          const rolText = user.cargo || user.rol || user.cargo_label || '';
          if (rolSpan) rolSpan.textContent = rolText;
        }
      } catch (e) {
        // silent
      }
    }

    if (btnVerPerfil && !btnVerPerfil.dataset.__bound_click_ver) {
      btnVerPerfil.dataset.__bound_click_ver = "1";
      btnVerPerfil.addEventListener("click", async (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeUserMenu();

        const id = window.userData?.id_usuario || window.userData?.id || null;
        const user = await fetchUserLatest(id);
        if (user) {
          renderPerfilModalFromUser(user);
        }
        openModal(modalPerfilVer);
      });
    }

    if (btnEditarPerfil && !btnEditarPerfil.dataset.__bound_click_edit) {
      btnEditarPerfil.dataset.__bound_click_edit = "1";
      btnEditarPerfil.addEventListener("click", async (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeUserMenu();

        // Ensure we have the freshest data before opening the edit modal
        const id = window.userData?.id_usuario || window.userData?.id || null;
        const user = await fetchUserLatest(id);
        if (user) {
          // merge into window.userData so inicializarEditarPerfilValores can use it
          window.userData = Object.assign({}, window.userData || {}, {
            nombre: user.nombre || user.nombre_completo || window.userData?.nombre,
            apellido: user.apellido || '',
            correo: user.correo || window.userData?.correo,
            telefono: user.telefono || window.userData?.telefono,
            direccion: user.direccion || window.userData?.direccion
          });
        }

        openModal(modalPerfilEditar);
      });
    }

    if (btnInfoDatosSensibles && !btnInfoDatosSensibles.dataset.__bound_click_sens) {
      btnInfoDatosSensibles.dataset.__bound_click_sens = "1";
      btnInfoDatosSensibles.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        openModal(modalDatosSensibles);
      });
    }

    if (btnAbrirCambiarPassword && !btnAbrirCambiarPassword.dataset.__bound_click_pass) {
      btnAbrirCambiarPassword.dataset.__bound_click_pass = "1";
      btnAbrirCambiarPassword.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        openModal(modalPassword);
      });
    }

    // =====================================================
    // 8) CLOSE BUTTONS
    // ✅ FIX: usar currentTarget (evita fallas cuando clickeas el <i>)
    // =====================================================
    const closeButtons = document.querySelectorAll('[id^="btnCerrar"], [id^="btnCancelar"]');

    closeButtons.forEach((btn) => {
      if (btn.dataset.__bound_click_close === "1") return;
      btn.dataset.__bound_click_close = "1";

      btn.addEventListener("click", (e) => {
        e.preventDefault();
        const modal = btn.closest(".fixed");
        if (modal) closeModal(modal);
      });
    });

    // =====================================================
    // 9) SENSITIVE CHECKBOXES SHOW/HIDE FIELDS
    // =====================================================
    sensibleChecks.forEach((chk) => {
      if (chk.dataset.__bound_change_sensible === "1") return;
      chk.dataset.__bound_change_sensible = "1";

      chk.addEventListener("change", () => {
        const key = chk.getAttribute("data-sensible");
        const field = document.getElementById("field_" + key);
        if (field) field.classList.toggle("hidden", !chk.checked);
      });
    });

    // ✅ Optional live validations for sensitive inputs (when visible)
    if (modalDatosSensibles && !modalDatosSensibles.dataset.__bound_input_sensible) {
      modalDatosSensibles.dataset.__bound_input_sensible = "1";

      modalDatosSensibles.addEventListener("input", (e) => {
        const el = e.target;
        if (!(el instanceof HTMLElement)) return;
        if (el.tagName !== "INPUT" && el.tagName !== "SELECT") return;

        // ✅ limpiar error inline del campo
        clearFieldError(el);

        // ✅ si el usuario corrige, también limpiamos el alert general del modal
        clearAlertDatosSensibles();
      });
    }

    // ✅ NUEVO: limpiar errores mientras escribe en editar perfil (SIN TOCAR TU BASE)
    if (modalPerfilEditar && !modalPerfilEditar.dataset.__bound_input_editperfil) {
      modalPerfilEditar.dataset.__bound_input_editperfil = "1";

      modalPerfilEditar.addEventListener("input", (e) => {
        const el = e.target;
        if (!(el instanceof HTMLElement)) return;
        if (el.tagName !== "INPUT" && el.tagName !== "SELECT" && el.tagName !== "TEXTAREA") return;
        clearFieldError(el);
      });
    }

    // =====================================================
    // ✅ VALIDATION: EDIT PROFILE FORM (MEJORADA E INTEGRADA)
    // =====================================================
    function validarFormularioEditarPerfil() {
      if (!formEditarPerfil) return true;

      formEditarPerfil.querySelectorAll("input, select, textarea").forEach(clearFieldError);

      let ok = true;
      let firstInvalid = null;

      const nombre = formEditarPerfil.querySelector('[name="nombre"]');
      const apellido = formEditarPerfil.querySelector('[name="apellido"]');
      const correo = formEditarPerfil.querySelector('[name="correo"]');
      const telefono = formEditarPerfil.querySelector('[name="telefono"]');
      const direccion = formEditarPerfil.querySelector('[name="direccion"]'); // ✅ opcional (si existe)

      // ✅ NOMBRE
      if (nombre) {
        const v = normalizeText(nombre.value);
        if (!v || v.length < 2) {
          ok = false;
          setFieldError(nombre, "El nombre debe tener al menos 2 caracteres.");
          if (!firstInvalid) firstInvalid = nombre;
        } else if (!isValidNombreApellido(v)) {
          ok = false;
          setFieldError(nombre, "El nombre solo debe contener letras y espacios.");
          if (!firstInvalid) firstInvalid = nombre;
        }
      }

      // ✅ APELLIDO
      if (apellido) {
        const v = normalizeText(apellido.value);
        if (!v || v.length < 2) {
          ok = false;
          setFieldError(apellido, "El apellido debe tener al menos 2 caracteres.");
          if (!firstInvalid) firstInvalid = apellido;
        } else if (!isValidNombreApellido(v)) {
          ok = false;
          setFieldError(apellido, "El apellido solo debe contener letras y espacios.");
          if (!firstInvalid) firstInvalid = apellido;
        }
      }

      // ✅ CORREO
      if (correo) {
        const v = String(correo.value || "").trim();
        if (!isValidEmail(v)) {
          ok = false;
          setFieldError(correo, "Ingresa un correo válido (ej: usuario@dominio.com).");
          if (!firstInvalid) firstInvalid = correo;
        }
      }

      // ✅ TELÉFONO (AHORA OBLIGATORIO)
      if (telefono) {
        const v = String(telefono.value || "").trim();
        if (!v) {
          ok = false;
          setFieldError(telefono, "El teléfono es obligatorio.");
          if (!firstInvalid) firstInvalid = telefono;
        } else if (!isValidTelefono(v)) {
          ok = false;
          setFieldError(
            telefono,
            "Teléfono inválido. Debe ser 7-10 dígitos (si es celular, inicia en 3)."
          );
          if (!firstInvalid) firstInvalid = telefono;
        }
      }

      // ✅ DIRECCIÓN (AHORA OBLIGATORIA)
      if (direccion) {
        const v = normalizeText(direccion.value);
        if (!v) {
          ok = false;
          setFieldError(direccion, "La dirección es obligatoria.");
          if (!firstInvalid) firstInvalid = direccion;
        } else if (!isValidDireccion(v)) {
          ok = false;
          setFieldError(direccion, "La dirección debe tener entre 5 y 80 caracteres.");
          if (!firstInvalid) firstInvalid = direccion;
        }
      }

      // ✅ Si todo ok pero no cambió nada (esto debe ser INFO como imagen 1)
      if (ok) {
        let externalFileSelected = false;
        try {
          const externalFileInput = document.getElementById('inputFotoPerfilEditar');
          externalFileSelected = !!(externalFileInput && externalFileInput.files && externalFileInput.files.length > 0);
        } catch (e) {
          externalFileSelected = false;
        }

        if (!formHasChanges(formEditarPerfil) && !externalFileSelected) {
          toastInfo("Para actualizar debes modificar al menos un dato.");
          return false;
        }
      }

      if (!ok) {
        toastWarning("Verifica los campos marcados antes de guardar.");
        if (firstInvalid) {
          firstInvalid.focus();
          firstInvalid.scrollIntoView({ behavior: "smooth", block: "center" });
        }
      }

      return ok;
    }

    // =====================================================
    // ✅ VALIDATION: CHANGE PASSWORD FORM
    // =====================================================
    function validarFormularioPassword() {
      if (!formCambiarPassword) return true;

      formCambiarPassword.querySelectorAll("input").forEach(clearFieldError);

      let ok = true;

      const actual =
        formCambiarPassword.querySelector('[name="password_actual"]') ||
        formCambiarPassword.querySelector('[name="passwordActual"]');

      const nueva =
        formCambiarPassword.querySelector('[name="password_nueva"]') ||
        formCambiarPassword.querySelector('[name="passwordNueva"]') ||
        formCambiarPassword.querySelector('[name="nueva_password"]');

      const confirmar =
        formCambiarPassword.querySelector('[name="password_confirmar"]') ||
        formCambiarPassword.querySelector('[name="passwordConfirmar"]') ||
        formCambiarPassword.querySelector('[name="confirmar_password"]');

      if (actual) {
        const v = String(actual.value || "").trim();
        if (v.length < 3) {
          ok = false;
          setFieldError(actual, "Ingresa tu contraseña actual.");
        }
      }

      if (nueva) {
        const v = String(nueva.value || "");
        if (!isStrongPassword(v)) {
          ok = false;
          setFieldError(nueva, "Mínimo 8 caracteres, 1 mayúscula, 1 minúscula y 1 número.");
        }
      }

      if (confirmar && nueva) {
        const v1 = String(nueva.value || "");
        const v2 = String(confirmar.value || "");
        if (v1 !== v2) {
          ok = false;
          setFieldError(confirmar, "Las contraseñas no coinciden.");
        }
      }

      if (!ok) {
        toastWarning("Corrige los errores antes de actualizar la contraseña.");
      }

      return ok;
    }

    // =====================================================
    // ✅ VALIDATION: SENSITIVE DATA FORM (MEJORADA)
    // - Now returns fieldErrors to highlight inputs
    // =====================================================
    function validarCambiosDatosSensibles(datosCambiados) {
      const cleaned = {};
      const fieldErrors = {}; // ✅ NEW
      let count = 0;

      for (const [key, obj] of Object.entries(datosCambiados || {})) {
        const anterior = String(obj.anterior || "").trim();
        const nuevo = String(obj.nuevo || "").trim();

        // ✅ If selected but empty
        if (!nuevo) {
          fieldErrors[key] = "Este campo es obligatorio para enviar la solicitud.";
          continue;
        }

        // ✅ If selected but same as current value
        if (nuevo === anterior) {
          fieldErrors[key] = "Debe ser diferente al valor actual.";
          continue;
        }

        // ✅ Specific validations
        if (key === "correo") {
          if (!isValidEmail(nuevo)) {
            fieldErrors[key] = "El correo ingresado no es válido (ej: usuario@dominio.com).";
            continue;
          }
        }

        if (key === "numero_documento") {
          if (!isValidDocumento(nuevo)) {
            fieldErrors[key] = "Debe tener entre 6 y 12 dígitos (solo números).";
            continue;
          }
        }

        if (key === "tipo_documento") {
          if (!allowedTipoDocumento(nuevo)) {
            fieldErrors[key] = "Debe ser CC, TI o CE.";
            continue;
          }
        }

        // ✅ If everything is ok, accept field
        cleaned[key] = { ...obj, nuevo };
        count++;
      }

      // ✅ If there are any field errors, return them
      if (Object.keys(fieldErrors).length > 0) {
        return {
          ok: false,
          cleaned: {},
          reason: "Corrige los campos marcados para poder enviar la solicitud.",
          fieldErrors,
        };
      }

      // ✅ No valid changes detected
      if (count === 0) {
        return {
          ok: false,
          cleaned: {},
          reason: "No hay cambios válidos para enviar.",
          fieldErrors: {},
        };
      }

      return { ok: true, cleaned };
    }

    // =====================================================
    // 10) FORM SUBMIT: EDIT PROFILE
    // =====================================================
    if (formEditarPerfil && formEditarPerfil.dataset.__bound_submit_editperfil !== "1") {
      formEditarPerfil.dataset.__bound_submit_editperfil = "1";

      formEditarPerfil.addEventListener("submit", async (e) => {
        e.preventDefault();

        if (__locks.editarPerfil) return;
        __locks.editarPerfil = true;

        if (!validarFormularioEditarPerfil()) {
          __locks.editarPerfil = false;
          return;
        }

        try {
          if (btnGuardarPerfil) {
            btnGuardarPerfil.disabled = true;
            btnGuardarPerfil.innerHTML = "Guardando...";
          }

          const formData = new FormData(formEditarPerfil);

          // If the file input is outside the <form>, append the file manually
          try {
            const externalFileInput = document.getElementById('inputFotoPerfilEditar');
            if (externalFileInput && externalFileInput.files && externalFileInput.files.length > 0) {
              formData.set('foto_perfil', externalFileInput.files[0]);
            }
          } catch (e) {
            // ignore
          }

          // ✅ PROTECCIÓN: nunca enviar strings vacíos (evita sobrescribir BD con "")
          for (const [key, value] of Array.from(formData.entries())) {
            if (typeof value === "string" && value.trim() === "") {
              formData.delete(key);
            }
          }

          const resp = await fetch(
            "src/controllers/usuario_controller.php?accion=editar_perfil_usuario",
            {
              method: "POST",
              body: formData,
            }
          );

          const data = await safeJson(resp);

          if (data.success) {
            toastSuccess("Perfil actualizado.");

            // Fetch latest user and update UI in-place (no reload)
            try {
              const id = window.userData?.id_usuario || window.userData?.id || null;
              const latest = await fetchUserLatest(id);
              if (latest) {
                renderPerfilModalFromUser(latest);
                updateHeaderFromUser(latest);
              }
            } catch (e) {
              // ignore fetch errors, not fatal
            }

            // Close edit modal and open view modal to show changes
            try {
              closeModal(modalPerfilEditar);
              openModal(modalPerfilVer);
            } catch (e) {}
          } else {
            toastDanger(data.error || "Error al actualizar el perfil.");
          }
        } catch (err) {
          toastDanger("Error de conexión.");
        } finally {
          __locks.editarPerfil = false;

          if (btnGuardarPerfil) {
            btnGuardarPerfil.disabled = false;
            btnGuardarPerfil.innerHTML = "Guardar cambios";
          }
        }
      });
    }

    // =====================================================
    // 11) FORM SUBMIT: REQUEST SENSITIVE DATA CHANGE
    // ✅ ALERTAS DE ERRORES DENTRO DEL MODAL ✅
    // ✅ ÉXITO "Solicitud enviada" COMO TOAST ✅
    // =====================================================
    if (formDatosSensibles && formDatosSensibles.dataset.__bound_submit_sensible !== "1") {
      formDatosSensibles.dataset.__bound_submit_sensible = "1";

      formDatosSensibles.addEventListener("submit", async (e) => {
        e.preventDefault();

        if (__locks.datosSensibles) return;
        __locks.datosSensibles = true;

        // ✅ limpiar alert general del modal antes de validar
        clearAlertDatosSensibles();

        const selected = Array.from(sensibleChecks).filter((c) => c.checked);
        if (selected.length === 0) {
          __locks.datosSensibles = false;

          // ✅ Esta alerta va dentro del modal (no toast)
          showAlertDatosSensibles(
            "warning",
            "Selección requerida",
            "Selecciona al menos un campo para solicitar el cambio."
          );
          return;
        }

        selected.forEach((chk) => {
          const key = chk.getAttribute("data-sensible");
          const wrap = document.getElementById(`field_${key}`);
          const input = wrap?.querySelector("input, select");
          if (input) clearFieldError(input);
        });

        const datosCambiados = {};

        for (const chk of selected) {
          const key = chk.getAttribute("data-sensible");
          const wrap = document.getElementById(`field_${key}`);
          const input = wrap?.querySelector("input, select");
          if (!input) continue;

          datosCambiados[key] = {
            anterior: input.getAttribute("data-valor-actual") || "",
            nuevo: (input.value || "").trim(),
            campo_nombre: key.replaceAll("_", " ").toUpperCase(),
          };
        }

        const valid = validarCambiosDatosSensibles(datosCambiados);

        if (!valid.ok) {
          // ✅ Alert general inside modal
          showAlertDatosSensibles(
            "danger",
            "Corrige los campos",
            valid.reason || "Revisa los campos antes de enviar."
          );

          // ✅ Mark exact fields with errors
          if (valid.fieldErrors && Object.keys(valid.fieldErrors).length > 0) {
            let firstInput = null;

            Object.entries(valid.fieldErrors).forEach(([key, msg]) => {
              const wrap = document.getElementById("field_" + key);
              const input = wrap?.querySelector("input, select");

              if (input) {
                setFieldError(input, msg);

                // ✅ remember the first failing input for focus
                if (!firstInput) firstInput = input;
              }
            });

            // ✅ focus the first failing input
            if (firstInput) {
              firstInput.focus();
              firstInput.scrollIntoView({ behavior: "smooth", block: "center" });
            }
          }

          __locks.datosSensibles = false;
          return;
        }

        const formData = new FormData();
        formData.append("datos_cambiados", JSON.stringify(valid.cleaned));

        try {
          const resp = await fetch(
            "src/controllers/usuario_controller.php?accion=solicitar_cambio_datos_sensibles",
            {
              method: "POST",
              body: formData,
            }
          );

          const res = await safeJson(resp);

          if (res.success) {
            // ✅ ÉXITO: SOLO TOAST (como lo pediste)
            toastSuccess("Solicitud enviada.");

            // ✅ FIX 3: cerrar y resetear SIEMPRE (evita que queden alertas / checks al volver a abrir)
            closeModal(modalDatosSensibles);
          } else {
            // ✅ Error: dentro del modal
            showAlertDatosSensibles(
              "danger",
              "No se pudo enviar",
              res.error || "No se pudo enviar la solicitud."
            );
          }
        } catch (err) {
          // ✅ Error: dentro del modal
          showAlertDatosSensibles(
            "danger",
            "Error de conexión",
            "Error de conexión. Intenta nuevamente."
          );
        } finally {
          __locks.datosSensibles = false;
        }
      });
    }

    // =====================================================
    // 12) FORM SUBMIT: CHANGE PASSWORD
    // =====================================================
    if (formCambiarPassword && formCambiarPassword.dataset.__bound_submit_pass !== "1") {
      formCambiarPassword.dataset.__bound_submit_pass = "1";

      formCambiarPassword.addEventListener("submit", async (e) => {
        e.preventDefault();

        if (__locks.cambiarPassword) return;
        __locks.cambiarPassword = true;

        if (!validarFormularioPassword()) {
          __locks.cambiarPassword = false;
          return;
        }

        try {
          const formData = new FormData(formCambiarPassword);

          const resp = await fetch("src/controllers/usuario_controller.php?accion=cambiar_password", {
            method: "POST",
            body: formData,
          });

          const res = await safeJson(resp);

          if (res.success) {
            toastSuccess("Contraseña actualizada.");
            closeModal(modalPassword);
            formCambiarPassword.reset();
          } else {
            toastDanger(res.error || "No se pudo cambiar la contraseña.");
          }
        } catch (err) {
          toastDanger("Error de conexión.");
        } finally {
          __locks.cambiarPassword = false;
        }
      });
    }

    // =====================================================
    // 13) PHOTO PREVIEW (EDIT MODAL)
    // =====================================================
    if (avatarPerfilEditar && inputFotoPerfilEditar && !avatarPerfilEditar.dataset.__bound_photo) {
      avatarPerfilEditar.dataset.__bound_photo = "1";

      avatarPerfilEditar.addEventListener("click", () => {
        inputFotoPerfilEditar.click();
      });

      inputFotoPerfilEditar.addEventListener("change", (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const validTypes = ["image/png", "image/jpeg", "image/jpg", "image/webp"];
        if (!validTypes.includes(file.type)) {
          toastWarning("Formato no válido. Usa PNG, JPG o WEBP.");
          inputFotoPerfilEditar.value = "";
          return;
        }

        if (file.size > 2 * 1024 * 1024) {
          toastWarning("La imagen supera 2MB. Usa una más ligera.");
          inputFotoPerfilEditar.value = "";
          return;
        }

        const reader = new FileReader();
        reader.onload = (ev) => {
          console.log('[perfil] preview loaded, size=', (ev.target.result || '').slice(0,80));
          // Prefer the avatar wrapper that lives inside the Edit modal to avoid
          // touching any other .avatar-wrapper instances (header, other modals, etc).
          const wrapper = (modalPerfilEditar && modalPerfilEditar.querySelector("#avatarPerfilEditar .avatar-wrapper")) || avatarPerfilEditar.querySelector(".avatar-wrapper") || avatarPerfilEditar.querySelector("div");
          if (wrapper) {
            // Remove initials (visible spans) or existing <img> so background shows cleanly
            wrapper.querySelectorAll('img, span:not(.sr-only)').forEach(n => n.remove());

            // Set background on wrapper
            wrapper.style.backgroundImage = `url('${ev.target.result}')`;
            wrapper.style.backgroundSize = 'cover';
            wrapper.style.backgroundPosition = 'center center';
            wrapper.style.backgroundRepeat = 'no-repeat';

            // NOTE: intentionally DO NOT set background on the parent (#avatarPerfilEditar)
            // to avoid accidentally updating the header/user-menu avatar. Preview must
            // remain inside the Edit modal only.

            // Force a tiny fade to ensure the browser repaints with the new background
            wrapper.style.transition = 'opacity 0.18s ease';
            wrapper.style.opacity = '0.98';
            setTimeout(() => { wrapper.style.opacity = '1'; }, 50);

            // ensure accessible label
            if (!wrapper.querySelector('.sr-only')) {
              const s = document.createElement('span');
              s.className = 'sr-only';
              s.textContent = `Foto de perfil de ${window.userData?.nombre_completo || ''}`;
              wrapper.appendChild(s);
            }
          }

          // Do NOT update the header avatar during preview — only preview inside the edit modal.
        };

        reader.readAsDataURL(file);
      });
    }

    // =====================================================
    // 14) ESC CLOSE
    // =====================================================
    if (!document.body.dataset.__bound_keydown_esc) {
      document.body.dataset.__bound_keydown_esc = "1";

      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
          closeUserMenu();
          cerrarNotificacionesDropdown();

          const modalAbierto = document.querySelector(".fixed:not(.hidden)");
          if (modalAbierto) closeModal(modalAbierto);
        }
      });
    }

    // =====================================================
    // 15) TOGGLE PASSWORD (EYE BUTTONS)
    // =====================================================
    document.querySelectorAll('button[data-toggle-password="true"]').forEach((btn) => {
      if (btn.dataset.__bound_toggle_pass === "1") return;
      btn.dataset.__bound_toggle_pass = "1";

      btn.addEventListener("click", function () {
        const input = this.parentElement?.querySelector("input");
        if (!input) return;

        const isPass = input.type === "password";
        input.type = isPass ? "text" : "password";

        this.querySelectorAll("i").forEach((i) => i.classList.toggle("hidden"));
      });
    });

    // =====================================================
    // 16) NOTIFICATIONS COUNTER + BADGE
    // =====================================================
    async function actualizarContadorNotificaciones() {
      if (__locks.contadorNotifs) return null;
      __locks.contadorNotifs = true;

      try {
        const resp = await fetch("src/utils/notificaciones_sesion.php?accion=contar", {
          cache: "no-store",
        });

        const data = await safeJson(resp);

        const btnBell = document.querySelector("#contenedor-notificaciones button");
        const existingBadge = document.querySelector(".badge-notificaciones");

        if (data.no_leidas > 0) {
          if (!existingBadge && btnBell) {
            const newBadge = document.createElement("span");
            newBadge.className =
              "absolute top-[2px] right-[2px] z-[2] h-4 w-4 rounded-full bg-[#ff4b4b] ring-2 ring-card flex items-center justify-center text-[9px] font-bold text-white badge-notificaciones pointer-events-none";
            newBadge.textContent = data.no_leidas > 9 ? "9+" : data.no_leidas;
            btnBell.appendChild(newBadge);
          } else if (existingBadge) {
            existingBadge.textContent = data.no_leidas > 9 ? "9+" : data.no_leidas;
            existingBadge.style.display = "flex";
          }
        } else {
          if (existingBadge) existingBadge.remove();
        }

        renderLucideIconsSafe();

        return data;
      } catch (error) {
        console.error("Error actualizando contador:", error);
        return null;
      } finally {
        __locks.contadorNotifs = false;
      }
    }

    // =====================================================
    // ✅ REFRESH DROPDOWN LIST
    // =====================================================
    async function refrescarDropdownNotificaciones() {
      if (__locks.refrescarNotifs) return;
      __locks.refrescarNotifs = true;

      try {
        const resp = await fetch("src/utils/notificaciones_sesion.php?accion=fetch&limit=5", {
          cache: "no-store",
        });

        const data = await safeJson(resp);
        if (!data.success) return;

        const dropdown = document.getElementById("dropdown-notificaciones");

        const badgeTotal = dropdown?.querySelector(".bg-muted");
        if (badgeTotal && data.resumen?.total !== undefined) {
          badgeTotal.textContent = data.resumen.total;
        }

        if (!dropdown) return;

        if (!data.notificaciones || data.notificaciones.length === 0) {
          const listaUI = document.getElementById("lista-notificaciones");
          if (listaUI) listaUI.remove();

          const existente = document.getElementById("estado-vacio-notificaciones");
          if (existente) existente.remove();

          const emptyHTML = `
            <div id="estado-vacio-notificaciones" class="px-3 py-6 text-center">
              <i data-lucide="bell-off" class="h-8 w-8 text-slate-300 mx-auto mb-2"></i>
              <p class="text-xs text-muted-foreground">No hay notificaciones nuevas.</p>
            </div>
          `;

          dropdown.insertAdjacentHTML("beforeend", emptyHTML);

          if (window.lucide) lucide.createIcons();
          return;
        }

        const emptyState = document.getElementById("estado-vacio-notificaciones");
        if (emptyState) emptyState.remove();

        let listaUI = document.getElementById("lista-notificaciones");
        if (!listaUI) {
          const wrap = document.createElement("div");
          wrap.className = "max-h-96 overflow-y-auto";
          wrap.id = "lista-notificaciones";
          dropdown.appendChild(wrap);
          listaUI = wrap;
        }

        listaUI.innerHTML = data.notificaciones
          .map((notif) => {
            const esNoLeida = !notif.leido;

            const clsColor = (() => {
              switch (notif.color) {
                case "warning":
                  return "bg-amber-100 text-amber-600";
                case "danger":
                  return "bg-red-100 text-red-600";
                case "success":
                  return "bg-emerald-100 text-emerald-600";
                default:
                  return "bg-blue-100 text-blue-600";
              }
            })();

            const fecha = (() => {
              try {
                const d = new Date(notif.fecha);
                const dia = String(d.getDate()).padStart(2, "0");
                const mes = String(d.getMonth() + 1).padStart(2, "0");
                const h = String(d.getHours()).padStart(2, "0");
                const m = String(d.getMinutes()).padStart(2, "0");
                return `${dia}/${mes} ${h}:${m}`;
              } catch {
                return "";
              }
            })();

            return `
              <div
                class="flex flex-col gap-0.5 px-3 py-2 hover:bg-muted/50 border-b border-border last:border-b-0 transition-all duration-200
                      ${esNoLeida ? "bg-blue-50 no-leida border-l-2 border-l-blue-500" : "leida"}"
                data-notif-id="${notif.id}"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-start gap-2 flex-1 min-w-0">
                    <div class="h-7 w-7 rounded-full flex items-center justify-center flex-shrink-0 ${clsColor}">
                      <i data-lucide="${notif.icono}" class="h-3.5 w-3.5"></i>
                    </div>

                    <div class="flex-1 min-w-0">
                      <div class="flex items-baseline justify-between gap-2">
                        <p class="text-xs font-semibold text-slate-800 truncate flex-1">
                          ${escapeHTML(notif.titulo || "")}
                        </p>
                        <p class="text-[10px] text-slate-500 whitespace-nowrap">
                          ${fecha}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div class="flex items-center gap-1 flex-shrink-0">
                    ${esNoLeida ? `<span class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse"></span>` : ""}
                    <button
                      onclick="eliminarNotificacion('${notif.id}')"
                      class="h-5 w-5 flex items-center justify-center text-slate-400 hover:text-red-500"
                      title="Eliminar"
                    >
                      <i data-lucide="x" class="h-3 w-3"></i>
                    </button>
                  </div>
                </div>

                <div class="flex items-center justify-between gap-2 text-[10px] pl-9">
                  <span class="text-slate-500 truncate flex-1">
                    Usuario: ${escapeHTML((notif.mensaje || "Sin detalles").trim())}
                  </span>

                  ${
                    esNoLeida
                      ? `<button
                            onclick="marcarNotificacionLeida('${notif.id}')"
                            class="text-blue-600 hover:text-blue-800 hover:underline whitespace-nowrap"
                          >
                            Marcar leído
                          </button>`
                      : ``
                  }
                </div>
              </div>
            `;
          })
          .join("");

        if (window.lucide) lucide.createIcons();
      } catch (err) {
        console.error("Error refrescando dropdown notificaciones:", err);
      } finally {
        __locks.refrescarNotifs = false;
      }
    }

    function escapeHTML(str) {
      return String(str)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    }

    // Map cargo (role) to the badge CSS class used in header.php
    function getCargoBadgeClass(cargoRaw) {
      if (!cargoRaw) return 'badge-role-default';
      const key = String(cargoRaw).toLowerCase().trim().replaceAll('\\s+', '_');

      const map = {
        'coordinador': 'badge-role-coordinador',
        'subcoordinador': 'badge-role-subcoordinador',
        'instructor': 'badge-role-instructor',
        'pasante': 'badge-role-pasante',
        'aprendiz': 'badge-role-parendiz',
        'encargado_inventario': 'badge-role-encargado-inventario',
        'encargado_bodega': 'badge-role-encargado-bodega'
      };

      return map[key] || 'badge-role-default';
    }

    // =====================================================
    // ✅ 18) AUTO REFRESH (MENOS SEGUNDOS + DETECTA CAMBIOS)
    // =====================================================
    let __lastNotifsState = { total: null, no_leidas: null };
    let __pollIntervalId = null;

    function dropdownEstaAbierto() {
      return dropdownNotificaciones && !dropdownNotificaciones.classList.contains("hidden");
    }

    function iniciarAutoRefreshNotificaciones(ms = 2000) {
      if (__pollIntervalId) clearInterval(__pollIntervalId);

      __pollIntervalId = setInterval(async () => {
        if (document.hidden) return;

        const contador = await actualizarContadorNotificaciones();
        if (!contador) return;

        const cambio =
          __lastNotifsState.total !== contador.total ||
          __lastNotifsState.no_leidas !== contador.no_leidas;

        if (cambio || dropdownEstaAbierto()) {
          __lastNotifsState = { total: contador.total, no_leidas: contador.no_leidas };
          refrescarDropdownNotificaciones();
        }
      }, ms);
    }

    actualizarContadorNotificaciones();
    refrescarDropdownNotificaciones();
    iniciarAutoRefreshNotificaciones(2000);

    // =====================================================
    // 19) GLOBAL FUNCTIONS FOR onclick="" IN HEADER
    // =====================================================
    window.marcarNotificacionLeida = async function (notifId) {
      try {
        const resp = await fetch("src/utils/notificaciones_sesion.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "accion=marcar_leido&notificacion_id=" + encodeURIComponent(notifId),
        });

        const data = await safeJson(resp);

        if (data.success) {
          toastSuccess("Notificación marcada como leída.");
          await actualizarContadorNotificaciones();
          await refrescarDropdownNotificaciones();
        } else {
          toastDanger(data.message || "No se pudo marcar como leída.");
        }
      } catch (err) {
        toastDanger("Error de conexión.");
      }
    };

    window.eliminarNotificacion = async function (notifId) {
      try {
        const resp = await fetch("src/utils/notificaciones_sesion.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "accion=eliminar&notificacion_id=" + encodeURIComponent(notifId),
        });

        const data = await safeJson(resp);

        if (data.success) {
          toastSuccess("Notificación eliminada.");
          await actualizarContadorNotificaciones();
          await refrescarDropdownNotificaciones();
        } else {
          toastDanger(data.message || "No se pudo eliminar.");
        }
      } catch (err) {
        toastDanger("Error de conexión.");
      }
    };

    window.marcarTodasLeidas = async function () {
      try {
        const resp = await fetch("src/utils/notificaciones_sesion.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "accion=marcar_todas_leidas",
        });

        const data = await safeJson(resp);

        if (data.success) {
          toastSuccess("Notificaciones marcadas como leídas.");
          await actualizarContadorNotificaciones();
          await refrescarDropdownNotificaciones();
        } else {
          toastDanger(data.message || "No se pudo marcar como leídas.");
        }
      } catch (err) {
        toastDanger("Error de conexión.");
      }
    };

    window.limpiarNotificaciones = async function () {
      try {
        const resp = await fetch("src/utils/notificaciones_sesion.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "accion=eliminar_todas",
        });

        const data = await safeJson(resp);

        if (data.success) {
          toastSuccess("Notificaciones eliminadas.");
          await actualizarContadorNotificaciones();
          await refrescarDropdownNotificaciones();
        } else {
          toastDanger(data.message || "No se pudieron eliminar.");
        }
      } catch (err) {
        toastDanger("Error de conexión.");
      }
    };

    // =====================================================
    // ✅ NUEVO: APROBAR DIRECTO DESDE ICONO CHECK (SIN MODAL)
    // =====================================================
    async function procesarAccionDirecta(accion, notifId, btnRef) {
      try {
        const fd = new FormData();
        fd.append("notificacion_id", notifId);

        const resp = await fetch(`src/controllers/usuario_controller.php?accion=${accion}`, {
          method: "POST",
          body: fd,
        });

        const res = await safeJson(resp);

        if (res.success) {
          toastSuccess(res.message || "Solicitud aprobada ✅");

          const card = btnRef?.closest(".flex.items-start.justify-between");
          if (card) card.remove();

          await actualizarContadorNotificaciones();
          await refrescarDropdownNotificaciones();
        } else {
          toastDanger(res.message || "No se pudo aprobar la solicitud.");
        }
      } catch (err) {
        console.error("Error aprobar directo:", err);
        toastDanger("Error inesperado aprobando la solicitud.");
      }
    }

    const botonesAprobarDirecto = document.querySelectorAll(".btn-aprobar-directo");
    if (botonesAprobarDirecto && botonesAprobarDirecto.length > 0) {
      botonesAprobarDirecto.forEach((btn) => {
        if (btn.dataset.__bound_click_aprobar === "1") return;
        btn.dataset.__bound_click_aprobar = "1";

        btn.onclick = async (e) => {
          e.preventDefault();
          e.stopPropagation();

          const notifId = btn.getAttribute("data-notif-id");
          if (!notifId) return;

          const ok = confirm("¿Aprobar esta solicitud y notificar al usuario?");
          if (!ok) return;

          await procesarAccionDirecta("aprobar_cambio_datos", notifId, btn);
        };
      });
    }
  });
}
