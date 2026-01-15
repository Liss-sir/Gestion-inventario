document.addEventListener("DOMContentLoaded", () => {
  // =========================
  // CONFIGURATION: CONTROLLER ENDPOINTS
  // =========================
  const API_URL = "src/controllers/usuario_controller.php";
  const PROGRAMAS_API_URL = "src/controllers/programa_controller.php";

  // =========================
  // ROLE CONFIGURATION (LABELS AND BADGE STYLES)
  // =========================
  const roleLabels = {
    Coordinador: "Coordinador",
    Subcoordinador: "Subcoordinador",
    Instructor: "Instructor",
    Pasante: "Pasante",
    Aprendiz: "Aprendiz",
  };

  // Badge classes defined in globals.css
  const roleBadgeStyles = {
    Coordinador: "badge-role-coordinador",
    Subcoordinador: "badge-role-coordinador",
    Instructor: "badge-role-instructor",
    Pasante: "badge-role-pasante",
    // "Aprendiz" uses the same visual style as "Instructor"
    Aprendiz: "badge-role-parendiz",
  };

  // =========================
  // VALID VALUES BASED ON DATABASE RULES
  // =========================
  const VALID_TIPOS_DOCUMENTO = ["CC", "TI", "CE"];
  const VALID_CARGOS = ["Coordinador", "Subcoordinador", "Instructor", "Pasante", "Aprendiz"];

  // In-memory collection used to render both table rows and user cards
  let users = [];
  let originalEditData = null; // Stores the original snapshot for change detection in edit mode
  let selectedUser = null;
  let programas = [];
  let programasMap = {}; // Maps id_programa => nombre_programa

  // =========================
  // PAGINATION SETTINGS
  // =========================
  const PAGE_SIZE_TABLE = 10; // Items per page in table view
  const PAGE_SIZE_CARDS = 9; // Items per page in card view

  let currentPageTable = 1;
  let currentPageCards = 1;

  // =========================
  // FLOWBITE-STYLE ALERTS (WHITE BACKGROUND, WARNING STYLE, NO PROGRESS BAR)
  // =========================

  /**
   * Returns the Flowbite-style alert container if it exists,
   * or creates it once and reuses it across the module.
   */
  function getOrCreateFlowbiteContainer() {
    let container = document.getElementById("flowbite-alert-container");

    if (!container) {
      container = document.createElement("div");
      container.id = "flowbite-alert-container";

      container.className =
        "fixed top-6 left-1/2 -translate-x-1/2 z-[9999] flex flex-col gap-3 w-full max-w-md px-4 pointer-events-none";

      document.body.appendChild(container);
    }

    return container;
  }

  /**
   * Generic alert renderer with Flowbite-like visuals.
   * type: "warning" | "success" | "info"
   * message: user-facing text
   */
  function showFlowbiteAlert(type, message) {
    const container = getOrCreateFlowbiteContainer();
    const wrapper = document.createElement("div");

    // Default style: warning
    let borderColor = "border-amber-500";
    let textColor = "text-amber-900";
    let titleText = "Advertencia";

    // Default icon: warning triangle
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

    // Entry animation and base visual configuration
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
      <p class="font-semibold">${titleText}</p>
      <p class="mt-0.5 text-sm">${message}</p>
    </div>
  `;

    container.appendChild(wrapper);

    // Smooth fade-in using CSS transitions
    requestAnimationFrame(() => {
      wrapper.classList.remove("opacity-0", "-translate-y-2");
      wrapper.classList.add("opacity-100", "translate-y-0");
    });

    // Automatic fade-out and cleanup
    setTimeout(() => {
      wrapper.classList.add("opacity-0", "-translate-y-2");
      wrapper.classList.remove("opacity-100", "translate-y-0");
      setTimeout(() => wrapper.remove(), 250);
    }, 4000);
  }

  // Public helpers for consistent notification usage across the module
  function toastError(message) {
    showFlowbiteAlert("warning", message);
  }

  function toastSuccess(message) {
    showFlowbiteAlert("success", message);
  }

  function toastInfo(message) {
    showFlowbiteAlert("info", message);
  }

  // =========================
  // DOM REFERENCES
  // =========================
  const tbodyUsuarios = document.getElementById("tbodyUsuarios");
  const inputBuscar = document.getElementById("inputBuscar");
  const selectFiltroRol = document.getElementById("selectFiltroRol");

  const vistaTabla = document.getElementById("vistaTabla");
  const vistaTarjetas = document.getElementById("vistaTarjetas");
  const cardsContainer = document.getElementById("cardsContainer");
  const btnVistaTabla = document.getElementById("btnVistaTabla");
  const btnVistaTarjetas = document.getElementById("btnVistaTarjetas");

  const modalUsuario = document.getElementById("modalUsuario");
  const btnNuevoUsuario = document.getElementById("btnNuevoUsuario");
  const btnCerrarModalUsuario = document.getElementById("btnCerrarModalUsuario");
  const btnCancelarModalUsuario = document.getElementById("btnCancelarModalUsuario");

  const formUsuario = document.getElementById("formUsuario");
  const hiddenUserId = document.getElementById("hiddenUserId");
  const modalUsuarioTitulo = document.getElementById("modalUsuarioTitulo");
  const modalUsuarioDescripcion = document.getElementById("modalUsuarioDescripcion");

  const inputNombreCompleto = document.getElementById("nombre_completo");
  const inputTipoDocumento = document.getElementById("tipo_documento");
  const inputNumeroDocumento = document.getElementById("numero_documento");
  const inputTelefono = document.getElementById("telefono");
  const inputCargo = document.getElementById("cargo");
  const inputCorreo = document.getElementById("correo");
  const inputPassword = document.getElementById("password");
  const inputDireccion = document.getElementById("direccion");

  // Training program select and wrapper container
  const inputPrograma = document.getElementById("id_programa");
  const wrapperPrograma = document.getElementById("wrapper_programa");

  const modalVerUsuario = document.getElementById("modalVerUsuario");
  const btnCerrarModalVerUsuario = document.getElementById("btnCerrarModalVerUsuario");
  const detalleUsuarioContent = document.getElementById("detalleUsuarioContent");

  // =========================
  // SAFE DOM EVENT BINDING
  // Prevents runtime errors if an expected element ID is missing in the view
  // =========================
  const safeOn = (el, evt, fn) => {
    if (!el) return false;
    el.addEventListener(evt, fn);
    return true;
  };

  // =========================
  // SHARED PAGINATION CONTAINER
  // =========================
  let paginationTabla = document.getElementById("paginationTabla");

  /**
   * Ensures a single pagination container exists and is placed after the cards view.
   * This container is reused for both table and card view pagination.
   */
  function ensurePaginationContainer() {
    if (vistaTarjetas && vistaTarjetas.parentNode && !paginationTabla) {
      paginationTabla = document.createElement("div");
      paginationTabla.id = "paginationTabla";
      paginationTabla.className = "mt-4 flex justify-end gap-2";
      // Insert right after the cards view (applies to both views)
      vistaTarjetas.parentNode.insertBefore(paginationTabla, vistaTarjetas.nextSibling);
    }
  }

  ensurePaginationContainer();

  // =========================
  // EMPTY STATE CONTAINERS (OUTSIDE TABLE)
  // =========================
  let emptyStateContainer = document.getElementById("emptyStateUsuarios");
  let emptySearchContainer = document.getElementById("emptySearchUsuarios");

  // Global empty state: no users available in the system
  if (!emptyStateContainer && vistaTabla && vistaTabla.parentNode) {
    emptyStateContainer = document.createElement("div");
    emptyStateContainer.id = "emptyStateUsuarios";

    emptyStateContainer.className =
      "hidden mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full";

    emptyStateContainer.innerHTML = `
    <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
      <svg class="h-7 w-7 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none"
           viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 14v.01M8 9h8m-9 9h10a2 2 0 0 0 2-2V8.5A2.5 2.5 0 0 0 16.5 6h-9A2.5 2.5 0 0 0 5 8.5V16a2 2 0 0 0 2 2z" />
      </svg>
    </div>
    <h3 class="text-lg font-semibold mt-4">No hay usuarios registrados</h3>
    <p class="text-sm text-muted-foreground mt-1 max-w-md">
      Una vez agregue usuarios desde el botón <strong>“Nuevo usuario”</strong>, aparecerán listados en esta vista.
    </p>
  `;

    vistaTabla.parentNode.insertBefore(emptyStateContainer, vistaTabla);
  }

  // Search empty state: used when users exist but none match current filters
  if (!emptySearchContainer && vistaTabla && vistaTabla.parentNode) {
    emptySearchContainer = document.createElement("div");
    emptySearchContainer.id = "emptySearchUsuarios";

    emptySearchContainer.className =
      "hidden mt-10 mb-6 flex flex-col items-center justify-center text-center border border-border rounded-2xl p-10 w-full";

    emptySearchContainer.innerHTML = `
    <div class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-transparent">
      <svg class="h-7 w-7 text-muted-foreground"
           xmlns="http://www.w3.org/2000/svg"
           fill="none"
           viewBox="0 0 24 24"
           stroke="currentColor"
           stroke-width="1.8">
        <circle cx="11" cy="11" r="6" stroke-linecap="round" stroke-linejoin="round"></circle>
        <line x1="16" y1="16" x2="20" y2="20" stroke-linecap="round" stroke-linejoin="round"></line>
      </svg>
    </div>
    <h3 class="text-lg font-semibold mt-4">No se encontraron resultados</h3>
    <p class="text-sm text-muted-foreground mt-1 max-w-md">
      No se encontraron usuarios que coincidan con los criterios de búsqueda actuales.
    </p>
  `;

    // Place the search empty state right before the table for better context
    vistaTabla.parentNode.insertBefore(emptySearchContainer, vistaTabla);
  }

  // =========================
  // HELPER FUNCTIONS
  // =========================

  /**
   * Computes initials from a full name (up to two letters).
   */
  function getInitials(nombre) {
    return String(nombre || "")
      .split(" ")
      .filter(Boolean)
      .map((n) => n[0])
      .slice(0, 2)
      .join("")
      .toUpperCase();
  }

  // =========================
  // PROFILE PHOTO HELPERS (NON-INTRUSIVE)
  // =========================
  function getBaseUrlFromApi() {
    try {
      const u = new URL(API_URL, window.location.href);
      const idx = u.pathname.indexOf("/src/");
      const basePath =
        idx !== -1 ? u.pathname.slice(0, idx + 1) : u.pathname.replace(/\/[^/]*$/, "/");
      return u.origin + basePath; // must end with "/"
    } catch (e) {
      return window.location.origin + "/";
    }
  }

  function resolveFotoUrl(path) {
    if (!path) return null;

    const raw = String(path).trim();
    if (!raw) return null;

    // Already an absolute URL
    if (/^https?:\/\//i.test(raw)) return raw;

    // Normalize: remove leading slashes for proper concatenation
    const clean = raw.replace(/^\/+/, "");
    return getBaseUrlFromApi() + clean;
  }

  /**
   * Avatar rendering:
   * - If there is a profile photo, render <img>
   * - Otherwise, render the initials fallback
   */
  function renderAvatarHTML(user, sizeClass = "h-9 w-9", textClass = "text-sm") {
    const fotoUrl = resolveFotoUrl(user?.foto_perfil);

    if (fotoUrl) {
      // Fallback to initials if the image fails to load
      const initials = getInitials(user?.nombre_completo || "");
      const safeName = String(user?.nombre_completo || "usuario").replace(/"/g, "&quot;");

      return `
      <img
        src="${fotoUrl}"
        alt="Foto de ${safeName}"
        class="rounded-full ${sizeClass} object-cover border border-border"
        onerror="this.onerror=null; this.style.display='none'; this.insertAdjacentHTML('afterend','<div class=\\'flex ${sizeClass} items-center justify-center rounded-full bg-avatar-secondary-39 text-secondary ${textClass}\\'>${initials}</div>');"
      />
    `;
    }

    return `
    <div class="flex ${sizeClass} items-center justify-center rounded-full bg-avatar-secondary-39 text-secondary ${textClass}">
      ${getInitials(user?.nombre_completo || "")}
    </div>
  `;
  }

  // =========================
  // REGEX DEFINITIONS (USED BY VALIDATION)
  // =========================
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const numeroRegex = /^\d+$/;

  // =========================
  // PAYLOAD VALIDATION HELPER
  // =========================
  function validateUserPayload(payload, opts = {}) {
    const { isEdit = false, currentId = null } = opts;

    const doc = String(payload.numero_documento || "").trim();
    const mail = String(payload.correo || "").trim().toLowerCase();

    // Prevent duplicates locally to improve user experience before hitting the backend
    if (doc) {
      const dupDoc = users.find(
        (u) => String(u.id) !== String(currentId) && String(u.numero_documento || "").trim() === doc
      );
      if (dupDoc) {
        toastError("El número de documento ya está registrado.");
        return false;
      }
    }

    if (mail) {
      const dupMail = users.find(
        (u) =>
          String(u.id) !== String(currentId) &&
          String(u.correo || "").trim().toLowerCase() === mail
      );
      if (dupMail) {
        toastError("El correo ya está registrado.");
        return false;
      }
    }

    return true;
  }

  // =========================
  // GLOBAL GUARD: AVOID ReferenceError IN LEGACY / CACHED BUILDS
  // =========================
  if (typeof window.validateUserPayload !== "function") {
    window.validateUserPayload = validateUserPayload;
  } else {
    // If a validation function already exists globally, do not override it
  }

  /**
   * Shows or hides the training program field depending on the selected role.
   */
  function actualizarVisibilidadPrograma() {
    if (!inputPrograma || !wrapperPrograma || !inputCargo) return;
    const esInstructor = inputCargo.value === "Instructor";
    if (esInstructor) {
      wrapperPrograma.classList.remove("hidden");
    } else {
      wrapperPrograma.classList.add("hidden");
      inputPrograma.value = "";
    }
  }

  /**
   * Renders the training program options based on the loaded dataset.
   */
  function renderOpcionesPrograma() {
    if (!inputPrograma) return;

    // Clear the select before repopulating
    inputPrograma.innerHTML = "";

    // No programs available
    if (!Array.isArray(programas) || programas.length === 0) {
      inputPrograma.innerHTML = `
      <option value="">No hay programas disponibles</option>
    `;
      inputPrograma.disabled = true;
      return;
    }

    // Programs available
    inputPrograma.disabled = false;

    // Default placeholder option
    inputPrograma.innerHTML = `<option value="">Seleccione un programa</option>`;

    programas.forEach((p) => {
      const opt = document.createElement("option");
      opt.value = p.id_programa;
      opt.textContent = p.nombre_programa || p.nombre || "";
      inputPrograma.appendChild(opt);
    });
  }

  /**
   * Loads training programs from the backend and refreshes the select options.
   */
  async function cargarProgramas() {
    if (!inputPrograma) return;

    try {
      const res = await fetch(`${PROGRAMAS_API_URL}?accion=listar`);
      const text = await res.text();
      console.log("Respuesta listar programas (cruda):", text);

      let data;
      try {
        const start = text.indexOf("[");
        const end = text.lastIndexOf("]");
        if (start !== -1 && end !== -1 && end > start) {
          data = JSON.parse(text.slice(start, end + 1));
        } else {
          console.error("Respuesta inesperada al listar programas:", text);
          data = [];
        }
      } catch (e) {
        console.error("Error parseando listar programas:", e, text);
        data = [];
      }

      if (Array.isArray(data)) {
        programas = data.map((p) => ({
          id_programa: p.id_programa,
          nombre_programa: p.nombre_programa || p.nombre || "",
        }));

        programasMap = {};
        programas.forEach((p) => {
          programasMap[String(p.id_programa)] = p.nombre_programa;
        });
      } else {
        programas = [];
      }

      // Refresh program options after loading
      renderOpcionesPrograma();

      // If the modal is currently open in edit mode and role is Instructor, re-select the current program
      if (
        modalUsuario &&
        modalUsuario.classList.contains("active") &&
        selectedUser &&
        selectedUser.cargo === "Instructor" &&
        inputPrograma
      ) {
        const pid = selectedUser.id_programa ? String(selectedUser.id_programa) : "";
        if (pid) inputPrograma.value = pid;
      }

      // Inform the user if there are no programs to assign
      if (programas.length === 0) {
        toastInfo(
          "No hay programas de formación registrados aún. Registre al menos un programa antes de asignarlo a un Instructor."
        );
      }
    } catch (error) {
      console.error("Error al cargar programas:", error);
      programas = [];
      renderOpcionesPrograma();
      toastError("Ocurrió un error al cargar los programas de formación.");
    }
  }

  // =========================
  // PASSWORD GENERATOR (LETTERS + NUMBERS + SPECIAL CHARACTERS)
  // =========================
  const PASSWORD_LENGTH = 12; // adjust to 14 or 16 if higher entropy is required

  function getSecureRandomInt(max) {
    // Cryptographically secure randomness (modern browsers)
    if (window.crypto && window.crypto.getRandomValues) {
      const array = new Uint32Array(1);
      window.crypto.getRandomValues(array);
      return array[0] % max;
    }
    // Fallback
    return Math.floor(Math.random() * max);
  }

  function shuffleArray(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
      const j = getSecureRandomInt(i + 1);
      [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
  }

  function generateStrongPassword(length = PASSWORD_LENGTH) {
    const lettersLower = "abcdefghijklmnopqrstuvwxyz";
    const lettersUpper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const numbers = "0123456789";
    const specials = "!@#$%^&*()_+-=[]{},.<>?/|~";

    // Ensure at least one character from each category
    const required = [
      lettersLower[getSecureRandomInt(lettersLower.length)],
      lettersUpper[getSecureRandomInt(lettersUpper.length)],
      numbers[getSecureRandomInt(numbers.length)],
      specials[getSecureRandomInt(specials.length)],
    ];

    const all = lettersLower + lettersUpper + numbers + specials;

    const remaining = [];
    const remainingCount = Math.max(0, length - required.length);
    for (let i = 0; i < remainingCount; i++) {
      remaining.push(all[getSecureRandomInt(all.length)]);
    }

    return shuffleArray([...required, ...remaining]).join("");
  }

  function setGenericPasswordInInput() {
    if (!inputPassword) return;
    const pass = generateStrongPassword(PASSWORD_LENGTH);
    inputPassword.value = pass;
  }

  /**
   * Opens the create/edit user modal.
   * If "editUser" is provided, the modal is initialized in edit mode.
   */
  function openModalUsuario(editUser = null) {
    if (!modalUsuario || !formUsuario || !hiddenUserId || !modalUsuarioTitulo || !modalUsuarioDescripcion)
      return;

    // Required inputs for a consistent form state
    if (
      !inputNombreCompleto ||
      !inputTipoDocumento ||
      !inputNumeroDocumento ||
      !inputTelefono ||
      !inputCargo ||
      !inputCorreo ||
      !inputPassword ||
      !inputDireccion
    ) {
      return;
    }

    selectedUser = editUser;
    modalUsuario.classList.add("active");

    // Password field wrapper (found via the closest valid container)
    let passwordWrapper = null;
    if (inputPassword) {
      passwordWrapper =
        inputPassword.closest(".space-y-2") || inputPassword.closest(".grid") || inputPassword.closest("div");
    }

    if (editUser) {
      // Edit mode
      modalUsuarioTitulo.textContent = "Editar Usuario";
      modalUsuarioDescripcion.textContent = "Modifica la información del usuario";
      hiddenUserId.value = editUser.id;

      inputNombreCompleto.value = editUser.nombre_completo;
      inputTipoDocumento.value = editUser.tipo_documento;
      inputNumeroDocumento.value = editUser.numero_documento;
      inputTelefono.value = editUser.telefono;
      inputCargo.value = editUser.cargo;
      inputCorreo.value = editUser.correo;
      inputPassword.value = "";
      inputDireccion.value = editUser.direccion;

      // Preserve original snapshot for change detection
      originalEditData = {
        nombre_completo: editUser.nombre_completo?.trim() || "",
        tipo_documento: editUser.tipo_documento || "",
        numero_documento: String(editUser.numero_documento ?? "").trim(),
        telefono: String(editUser.telefono ?? "").trim(),
        cargo: editUser.cargo || "",
        correo: editUser.correo?.trim() || "",
        direccion: editUser.direccion?.trim() || "",
        id_programa:
          editUser.cargo === "Instructor" && editUser.id_programa ? String(editUser.id_programa) : null,
      };

      // In edit mode, the password field is hidden by default
      if (passwordWrapper) {
        passwordWrapper.classList.add("hidden");
      }

      if (inputPrograma && wrapperPrograma) {
        if (editUser.cargo === "Instructor") {
          wrapperPrograma.classList.remove("hidden");
          renderOpcionesPrograma();
          if (editUser.id_programa) {
            inputPrograma.value = editUser.id_programa;
          } else {
            inputPrograma.value = "";
          }
        } else {
          wrapperPrograma.classList.add("hidden");
          inputPrograma.value = "";
        }
      }
    } else {
      // Create mode
      modalUsuarioTitulo.textContent = "Crear Nuevo Usuario";
      modalUsuarioDescripcion.textContent = "Complete los datos para registrar un nuevo usuario";
      hiddenUserId.value = "";
      formUsuario.reset();
      if (inputTipoDocumento) inputTipoDocumento.value = "CC";
      if (inputCargo) inputCargo.value = "Aprendiz";
      if (inputPrograma) inputPrograma.value = "";
      actualizarVisibilidadPrograma();

      // No edit snapshot in create mode
      originalEditData = null;

      // Password field is required for new users
      if (passwordWrapper) {
        passwordWrapper.classList.remove("hidden");
      }

      // Auto-generate a secure default password for new records
      setGenericPasswordInInput();
    }
  }

  /**
   * Closes the create/edit user modal and resets relevant state.
   */
  function closeModalUsuario() {
    if (!modalUsuario) return;
    modalUsuario.classList.remove("active");
    selectedUser = null;
    if (hiddenUserId) hiddenUserId.value = "";
    originalEditData = null;
  }

  /**
   * Opens the user details modal for the provided user.
   */
  function openModalVerUsuario(user) {
    if (!modalVerUsuario || !detalleUsuarioContent) return;

    selectedUser = user;
    modalVerUsuario.classList.add("active");

    const estadoBadgeClass = user.estado ? "badge-estado-activo" : "badge-estado-inactivo";

    const programaNombre =
      user.cargo === "Instructor" && user.id_programa
        ? programasMap[String(user.id_programa)] || "Sin programa asignado"
        : null;

    detalleUsuarioContent.innerHTML = `
      <div class="flex items-center gap-4">
        ${renderAvatarHTML(user, "h-16 w-16", "text-xl")}
        <div>
          <h3 class="font-semibold text-lg">${user.nombre_completo}</h3>

          <!-- Role and status displayed side-by-side -->
          <div class="mt-1 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
              roleBadgeStyles[user.cargo] || "badge-role-default"
            }">
              ${roleLabels[user.cargo] || user.cargo}
            </span>

            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${estadoBadgeClass}">
              ${user.estado ? "Activo" : "Inactivo"}
            </span>
          </div>
        </div>
      </div>

      <div class="grid gap-3 text-sm">
        <div class="grid grid-cols-3 gap-2">
          <span class="text-muted-foreground">Documento:</span>
          <span class="col-span-2">${user.tipo_documento} ${user.numero_documento}</span>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <span class="text-muted-foreground">Teléfono:</span>
          <span class="col-span-2">${user.telefono}</span>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <span class="text-muted-foreground">Correo:</span>
          <span class="col-span-2">${user.correo}</span>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <span class="text-muted-foreground">Dirección:</span>
          <span class="col-span-2">${user.direccion}</span>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <span class="text-muted-foreground">Registrado:</span>
          <span class="col-span-2">${user.fecha_creacion || ""}</span>
        </div>

        ${
          programaNombre
            ? `
        <div class="grid grid-cols-3 gap-2">
          <span class="text-muted-foreground">Programa:</span>
          <span class="col-span-2 font-medium">${programaNombre}</span>
        </div>
        `
            : ""
        }
      </div>
    `;
  }

  /**
   * Closes the user details modal.
   */
  function closeModalVerUsuario() {
    if (!modalVerUsuario) return;
    modalVerUsuario.classList.remove("active");
    selectedUser = null;
  }

  // =========================
  // BACKEND COMMUNICATION
  // =========================

  /**
   * Generic helper for JSON-based POST endpoints.
   * It tolerates extra output by extracting the JSON object from the response.
   */
  async function callApi(url, payload) {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const text = await res.text();
    console.log("Respuesta cruda del servidor:", text);

    try {
      const start = text.indexOf("{");
      const end = text.lastIndexOf("}");
      if (start !== -1 && end !== -1 && end > start) {
        const jsonString = text.slice(start, end + 1);
        return JSON.parse(jsonString);
      }
      return { error: "Respuesta no válida del servidor: " + text };
    } catch (e) {
      console.error("Error parseando JSON:", e);
      return { error: "Respuesta no válida del servidor: " + text };
    }
  }

  /**
   * Loads users from the backend and maps them into the internal "users" structure.
   */
  async function cargarUsuarios() {
    try {
      const res = await fetch(`${API_URL}?accion=listar`);
      const text = await res.text();
      console.log("Respuesta listar (cruda):", text);

      let data;
      try {
        const start = text.indexOf("[");
        const end = text.lastIndexOf("]");
        if (start !== -1 && end !== -1 && end > start) {
          data = JSON.parse(text.slice(start, end + 1));
        } else {
          console.error("Respuesta inesperada al listar usuarios:", text);
          data = [];
        }
      } catch (e) {
        console.error("Error parseando listar:", e, text);
        data = [];
      }

      if (!Array.isArray(data)) {
        console.error("Respuesta inesperada al listar usuarios:", data);
        users = [];
      } else {
        users = data.map((u) => {
          let estadoBool = true;

          if (typeof u.estado !== "undefined" && u.estado !== null) {
            const raw = String(u.estado).toLowerCase().trim();

            if (raw === "activo" || raw === "1" || raw === "true") {
              estadoBool = true;
            } else if (raw === "inactivo" || raw === "0" || raw === "false") {
              estadoBool = false;
            }
          }

          return {
            id: u.id_usuario,
            nombre_completo: u.nombre_completo,
            tipo_documento: u.tipo_documento,
            numero_documento: u.numero_documento,
            telefono: u.telefono,
            cargo: u.cargo,
            correo: u.correo,
            direccion: u.direccion,
            estado: estadoBool,

            // Field mapping compatibility:
            // Backend returns "fecha_creacion" (not "created_at"), so both are stored to prevent empty views.
            fecha_creacion: u.fecha_creacion || u.created_at || "",
            created_at: u.created_at || u.fecha_creacion || "",

            id_programa: u.id_programa ?? null,

            // Optional profile photo from backend
            foto_perfil: u.foto_perfil ?? null,
          };
        });
      }

      renderTable();
    } catch (error) {
      console.error("Error al cargar usuarios:", error);
      users = [];
      renderTable();
    }
  }

  /**
   * Creates a new user via backend.
   */
  function crearUsuario(payload) {
    return callApi(`${API_URL}?accion=crear`, payload);
  }

  /**
   * Updates an existing user via backend.
   */
  function actualizarUsuario(payload) {
    return callApi(`${API_URL}?accion=actualizar`, payload);
  }

  /**
   * Optional endpoint to toggle user status (if implemented).
   * Includes automatic fallback for alternative action names.
   */
  async function cambiarEstadoUsuario(payload) {
    const posiblesAcciones = ["cambiar_estado", "cambiarEstado", "toggle_estado", "toggleEstado"];

    let last = null;

    for (const accion of posiblesAcciones) {
      const data = await callApi(`${API_URL}?accion=${accion}`, payload);
      last = data;

      // If no error is present, return immediately
      if (!data || !data.error) return data;

      // If error is not "invalid action", stop and return the real error
      const msg = String(data.error || "");
      const esAccionNoValida = /acción no válida|accion no valida|acción inválida|accion invalida/i.test(msg);
      if (!esAccionNoValida) return data;
    }

    return last || { error: "No se pudo cambiar el estado del usuario." };
  }

  /**
   * Toggles the active/inactive status of a user and persists changes in the backend.
   */
  async function toggleStatus(userId) {
    const user = users.find((u) => String(u.id) === String(userId));
    if (!user) return;

    const nuevoEstado = user.estado ? 0 : 1; // 1 = active, 0 = inactive

    try {
      const data = await cambiarEstadoUsuario({
        id_usuario: userId,
        estado: nuevoEstado,
      });

      console.log("Respuesta cambiar_estado:", data);

      if (data && data.error) {
        toastError(data.error || "No se pudo cambiar el estado del usuario.");
        return;
      }

      users = users.map((u) => (String(u.id) === String(userId) ? { ...u, estado: !!nuevoEstado } : u));
      renderTable();

      toastSuccess(nuevoEstado === 1 ? "Usuario activado correctamente." : "Usuario desactivado correctamente.");
    } catch (error) {
      console.error("Error al cambiar estado:", error);
      toastError("Ocurrió un error al cambiar el estado (red/servidor).");
    }
  }

  // Expose for inline onclick usage (cards)
  window.toggleStatus = toggleStatus;

  // =========================
  // VIEW MODE SWITCH: TABLE / CARDS
  // =========================

  /**
   * Activates the table view and re-renders content.
   */
  function setVistaTabla() {
    if (!vistaTabla || !vistaTarjetas || !btnVistaTabla || !btnVistaTarjetas) return;

    vistaTabla.classList.remove("hidden");
    vistaTarjetas.classList.add("hidden");

    btnVistaTabla.classList.add("bg-muted", "text-foreground");
    btnVistaTarjetas.classList.remove("bg-muted");
    btnVistaTarjetas.classList.add("text-muted-foreground");

    renderTable();
  }

  /**
   * Activates the cards view and re-renders content.
   */
  function setVistaTarjetas() {
    if (!vistaTabla || !vistaTarjetas || !btnVistaTabla || !btnVistaTarjetas) return;

    vistaTabla.classList.add("hidden");
    vistaTarjetas.classList.remove("hidden");

    btnVistaTarjetas.classList.add("bg-muted", "text-foreground");
    btnVistaTabla.classList.remove("bg-muted");
    btnVistaTabla.classList.add("text-muted-foreground");

    renderTable();
  }

  // =========================
  // PAGINATION RENDERING
  // =========================

  /**
   * Renders pagination controls and binds them to the provided callback.
   */
  function renderPaginationControls(container, totalItems, pageSize, currentPage, onPageChange) {
    if (!container) return;

    const totalPages = Math.ceil(totalItems / pageSize);

    if (totalPages <= 1) {
      container.innerHTML = "";
      return;
    }

    container.innerHTML = "";

    const btnPrev = document.createElement("button");
    btnPrev.type = "button";
    btnPrev.className =
      "px-3 py-1 text-sm rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40";
    btnPrev.textContent = "Anterior";
    btnPrev.disabled = currentPage === 1;
    btnPrev.addEventListener("click", () => {
      if (currentPage > 1) onPageChange(currentPage - 1);
    });
    container.appendChild(btnPrev);

    for (let i = 1; i <= totalPages; i++) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.textContent = i;
      btn.className =
        "px-3 py-1 text-sm rounded-lg border border-border " +
        (i === currentPage ? "bg-primary text-primary-foreground" : "bg-card hover:bg-muted");
      btn.addEventListener("click", () => {
        if (i !== currentPage) onPageChange(i);
      });
      container.appendChild(btn);
    }

    const btnNext = document.createElement("button");
    btnNext.type = "button";
    btnNext.className =
      "px-3 py-1 text-sm rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40";
    btnNext.textContent = "Siguiente";
    btnNext.disabled = currentPage === totalPages;
    btnNext.addEventListener("click", () => {
      if (currentPage < totalPages) onPageChange(currentPage + 1);
    });
    container.appendChild(btnNext);
  }

  // =========================
  // TABLE AND CARD RENDERING
  // =========================

  /**
   * Applies filters, handles empty states, and renders both table and card views with pagination.
   * Distinguishes between:
   *  - No visible users available
   *  - No results matching current search/filter criteria
   */
  function renderTable() {
    // Defensive check: if critical elements are missing, avoid breaking other views
    if (!vistaTabla || !vistaTarjetas || !cardsContainer || !paginationTabla || !tbodyUsuarios) {
      return;
    }

    const search = (inputBuscar ? inputBuscar.value : "").trim().toLowerCase();
    const rol = selectFiltroRol ? selectFiltroRol.value : "";

    // Base visibility filter (applied before search/role filters)
    // Ensures the UI does not show "no results" when users exist but are hidden by business rules.
    const visibleBase = users.filter((u) => {
      // Hide internal system user (id=1) unless the current authenticated user is that account
      if (typeof AUTH_USER_ID !== "undefined" && String(AUTH_USER_ID) !== "1" && String(u.id) === "1") {
        return false;
      }

      // Hide the currently authenticated user from the listing
      if (typeof AUTH_USER_ID !== "undefined" && String(u.id) === String(AUTH_USER_ID)) {
        return false;
      }

      return true;
    });

    // Apply search and role filter on top of base visibility
    const filtered = visibleBase.filter((u) => {
      const matchName = String(u.nombre_completo || "").toLowerCase().includes(search);
      const matchRol = rol ? u.cargo === rol : true;
      return matchName && matchRol;
    });

    const totalItems = filtered.length;

    // Helper to clear rendered content and pagination
    const clearRenderedContent = () => {
      tbodyUsuarios.innerHTML = "";
      cardsContainer.innerHTML = "";
      if (paginationTabla) paginationTabla.innerHTML = "";
    };

    // Case 1: no visible users exist (even if hidden users exist in DB)
    if (visibleBase.length === 0) {
      clearRenderedContent();

      // Hide both views
      vistaTabla.classList.add("hidden");
      vistaTarjetas.classList.add("hidden");

      // Show global empty state
      if (emptyStateContainer) emptyStateContainer.classList.remove("hidden");
      if (emptySearchContainer) emptySearchContainer.classList.add("hidden");

      return;
    }

    // Case 2: users exist, but current filter returns no results
    if (totalItems === 0) {
      clearRenderedContent();

      // Hide both views
      vistaTabla.classList.add("hidden");
      vistaTarjetas.classList.add("hidden");

      // Show search-specific empty state
      if (emptyStateContainer) emptyStateContainer.classList.add("hidden");
      if (emptySearchContainer) emptySearchContainer.classList.remove("hidden");

      return;
    }

    // Case 3: results exist
    if (emptyStateContainer) emptyStateContainer.classList.add("hidden");
    if (emptySearchContainer) emptySearchContainer.classList.add("hidden");

    // Respect the current selected view (table or cards)
    if (btnVistaTabla && btnVistaTabla.classList.contains("bg-muted")) {
      vistaTabla.classList.remove("hidden");
    }
    if (btnVistaTarjetas && btnVistaTarjetas.classList.contains("bg-muted")) {
      vistaTarjetas.classList.remove("hidden");
    }

    const totalPagesTable = Math.max(1, Math.ceil(totalItems / PAGE_SIZE_TABLE) || 1);
    const totalPagesCards = Math.max(1, Math.ceil(totalItems / PAGE_SIZE_CARDS) || 1);

    if (currentPageTable > totalPagesTable) currentPageTable = totalPagesTable;
    if (currentPageCards > totalPagesCards) currentPageCards = totalPagesCards;

    const startIndexTable = (currentPageTable - 1) * PAGE_SIZE_TABLE;
    const endIndexTable = startIndexTable + PAGE_SIZE_TABLE;
    const pageItemsTable = filtered.slice(startIndexTable, endIndexTable);

    const startIndexCards = (currentPageCards - 1) * PAGE_SIZE_CARDS;
    const endIndexCards = startIndexCards + PAGE_SIZE_CARDS;
    const pageItemsCards = filtered.slice(startIndexCards, endIndexCards);

    // Table rendering
    tbodyUsuarios.innerHTML = "";

    pageItemsTable.forEach((user) => {
      const tr = document.createElement("tr");
      tr.className = "hover:bg-muted/40";

      const estadoBadgeClass = user.estado ? "badge-estado-activo" : "badge-estado-inactivo";

      tr.innerHTML = `
        <td class="px-4 py-3 align-middle">
          <div class="flex items-center gap-3">
            ${renderAvatarHTML(user, "h-9 w-9", "text-sm")}
            <div>
              <p class="font-medium text-sm">${user.nombre_completo}</p>
              <p class="text-xs text-muted-foreground">${user.correo}</p>
            </div>
          </div>
        </td>
        <td class="px-4 py-3 align-middle">
          <span class="text-sm">${user.tipo_documento} ${user.numero_documento}</span>
        </td>
        <td class="px-4 py-3 align-middle">
          <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
            roleBadgeStyles[user.cargo] || "badge-role-default"
          }">
            ${roleLabels[user.cargo] || user.cargo}
          </span>
        </td>
        <td class="px-4 py-3 align-middle">
          <span class="text-sm">${user.telefono}</span>
        </td>
        <td class="px-4 py-3 align-middle">
          <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${estadoBadgeClass}">
            ${user.estado ? "Activo" : "Inactivo"}
          </span>
        </td>
        <td class="px-4 py-3 align-middle text-right">
          <div class="relative inline-block text-left">
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-muted text-slate-800"
              data-menu-trigger="${user.id}"
            >
              <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                   viewBox="0 0 24 24">
                <circle cx="5" cy="12" r="1.5"></circle>
                <circle cx="12" cy="12" r="1.5"></circle>
                <circle cx="19" cy="12" r="1.5"></circle>
              </svg>
            </button>
            <div
              class="dropdown-menu hidden absolute right-0 mt-2 w-48 rounded-xl border border-border bg-popover shadow-md py-1"
              data-menu="${user.id}"
            >
              <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted"
                data-action="ver"
                data-id="${user.id}"
              >
                <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M1 12S4.5 5 12 5s11 7 11 7-3.5 7-11 7S1 12 1 12z"/>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                Ver detalles
              </button>
              <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted"
                data-action="editar"
                data-id="${user.id}"
              >
                <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 20h9"/>
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16.5 3.5a2.121 2.121 0 0 1 3 3L9 17l-4 1 1-4 10.5-10.5z"/>
                </svg>
                Editar
              </button>
              <hr class="border-border my-1">
              <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-sm text-slate-700 hover:bg-muted"
                data-action="toggle"
                data-id="${user.id}"
              >
                ${
                  user.estado
                    ? `
                      <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                           viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <circle cx="9" cy="7" r="3"></circle>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 21v-1a4 4 0 0 1 4-4h2"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M17 17l4 4m0-4l-4 4"/>
                      </svg>
                      Desactivar
                    `
                    : `
                      <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                           viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <circle cx="9" cy="7" r="3"></circle>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 21v-1a4 4 0 0 1 4-4h2"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16 19l2 2 4-4"/>
                      </svg>
                      Activar
                    `
                }
              </button>
            </div>
          </div>
        </td>
      `;

      tbodyUsuarios.appendChild(tr);
    });

    // Cards rendering
    cardsContainer.innerHTML = "";

    pageItemsCards.forEach((user) => {
      const estadoBadgeClass = user.estado ? "badge-estado-activo" : "badge-estado-inactivo";

      const card = document.createElement("div");
      card.className = "rounded-2xl border border-border bg-card p-3 shadow-sm flex flex-col gap-2";

      card.innerHTML = `
        <div class="flex items-start justify-between gap-2">
          <div class="flex items-center gap-2">
            ${renderAvatarHTML(user, "h-10 w-10", "text-xs")}
            <div class="space-y-0.5">
              <p class="font-semibold text-xs sm:text-sm leading-snug">${user.nombre_completo}</p>
              <p class="text-[11px] sm:text-xs text-muted-foreground">${user.tipo_documento} ${user.numero_documento}</p>
            </div>
          </div>

          <div class="relative inline-block text-left">
            <button
              type="button"
              class="inline-flex h-6 w-6 items-center justify-center rounded-md hover:bg-muted text-slate-800"
              data-menu-trigger="${user.id}"
            >
              <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                   viewBox="0 0 24 24">
                <circle cx="5" cy="12" r="1.5"></circle>
                <circle cx="12" cy="12" r="1.5"></circle>
                <circle cx="19" cy="12" r="1.5"></circle>
              </svg>
            </button>
            <div
              class="dropdown-menu hidden absolute right-0 mt-2 w-40 rounded-xl border border-border bg-popover shadow-md py-1"
              data-menu="${user.id}"
            >
              <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-xs text-slate-700 hover:bg-muted"
                data-action="ver"
                data-id="${user.id}"
              >
                <svg class="mr-2 h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M1 12S4.5 5 12 5s11 7 11 7-3.5 7-11 7S1 12 1 12z"/>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                Ver detalles
              </button>
              <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-xs text-slate-700 hover:bg-muted"
                data-action="editar"
                data-id="${user.id}"
              >
                <svg class="mr-2 h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 20h9"/>
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16.5 3.5a2.121 2.121 0 0 1 3 3L9 17l-4 1 1-4 10.5-10.5z"/>
                </svg>
                Editar
              </button>
              <hr class="border-border my-1">
              <button
                type="button"
                class="flex w-full items-center px-3 py-2 text-xs text-slate-700 hover:bg-muted"
                data-action="toggle"
                data-id="${user.id}"
              >
                ${
                  user.estado
                    ? `
                      <svg class="mr-2 h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                           viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <circle cx="9" cy="7" r="3"></circle>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 21v-1a4 4 0 0 1 4-4h2"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M17 17l4 4m0-4l-4 4"/>
                      </svg>
                      Desactivar
                    `
                    : `
                      <svg class="mr-2 h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                           viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <circle cx="9" cy="7" r="3"></circle>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 21v-1a4 4 0 0 1 4-4h2"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16 19l2 2 4-4"/>
                      </svg>
                      Activar
                    `
                }
              </button>
            </div>
          </div>
        </div>

        <div class="space-y-1 text-[11px] sm:text-xs text-muted-foreground">
          <div class="flex items-center gap-2">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M4 6h16v12H4z"/>
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M4 6l8 6 8-6"/>
            </svg>
            <span>${user.correo}</span>
          </div>
          <div class="flex items-center gap-2">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 5a2 2 0 0 1 2-2h2l2 5-2 1c1 2.5 3 4.5 5.5 5.5l1-2 5 2v2a2 2 0 0 1-2 2h-1C9.82 19 5 14.18 5 8V7a2 2 0 0 1-2-2z"/>
            </svg>
            <span>${user.telefono}</span>
          </div>
        </div>

        <div class="flex items-center justify-between mt-1">
          <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium ${
              roleBadgeStyles[user.cargo] || "badge-role-default"
            }">
              ${roleLabels[user.cargo] || user.cargo}
            </span>
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium ${estadoBadgeClass}">
              ${user.estado ? "Activo" : "Inactivo"}
            </span>
          </div>
        </div>

        <hr class="border-border my-1" />

        <div class="flex justify-end">
          <button
            type="button"
            class="switch-siga ${user.estado ? "on" : "off"}"
            onclick="toggleStatus('${user.id}')"
          >
            <span class="thumb" style="transform: translateX(${user.estado ? "18px" : "0px"});"></span>
          </button>
        </div>
      `;

      cardsContainer.appendChild(card);
    });

    // Attach contextual menu behavior to newly rendered items
    attachMenuEvents();

    const tablaVisible = !vistaTabla.classList.contains("hidden");

    if (tablaVisible) {
      renderPaginationControls(paginationTabla, totalItems, PAGE_SIZE_TABLE, currentPageTable, (page) => {
        currentPageTable = page;
        renderTable();
      });
    } else {
      renderPaginationControls(paginationTabla, totalItems, PAGE_SIZE_CARDS, currentPageCards, (page) => {
        currentPageCards = page;
        renderTable();
      });
    }
  }

  // =========================
  // DROPDOWN MENU HANDLING
  // =========================

  // Prevent duplicate listeners across re-renders
  let _menuEventsAttached = false;

  /**
   * Registers global click handling for contextual menus in table and card views.
   * Uses event delegation to keep a single listener bound for the entire module lifecycle.
   */
  function attachMenuEvents() {
    if (_menuEventsAttached) return;
    _menuEventsAttached = true;

    const closeAllMenus = () => {
      document.querySelectorAll("[data-menu]").forEach((el) => {
        el.classList.add("hidden");
        el.classList.remove("show");
      });
    };

    document.addEventListener("click", (e) => {
      const trigger = e.target.closest("[data-menu-trigger]");
      const actionBtn = e.target.closest("[data-menu] [data-action]");
      const anyMenu = e.target.closest("[data-menu]");

      // 1) Action item click inside a menu
      if (actionBtn) {
        e.stopPropagation();

        const action = actionBtn.getAttribute("data-action");
        const id = actionBtn.getAttribute("data-id");
        const user = users.find((u) => String(u.id) === String(id));
        if (!user) {
          closeAllMenus();
          return;
        }

        if (action === "ver") {
          openModalVerUsuario(user);
        } else if (action === "editar") {
          openModalUsuario(user);
        } else if (action === "toggle") {
          toggleStatus(id);
        }

        // Close the menu after executing the action
        const menu = actionBtn.closest("[data-menu]");
        if (menu) {
          menu.classList.add("hidden");
          menu.classList.remove("show");
        }

        return;
      }

      // 2) Trigger click (three-dot button)
      if (trigger) {
        e.stopPropagation();

        const wrapper =
          trigger.closest(".relative") ||
          trigger.closest(".inline-block") ||
          trigger.closest("td") ||
          trigger.closest("div");

        if (!wrapper) return;

        const menu = wrapper.querySelector("[data-menu]");
        if (!menu) return;

        const isHidden = menu.classList.contains("hidden");

        // Close other menus first
        closeAllMenus();

        if (isHidden) {
          menu.classList.remove("hidden");
          requestAnimationFrame(() => {
            menu.classList.add("show");
          });
        } else {
          menu.classList.remove("show");
          setTimeout(() => {
            menu.classList.add("hidden");
          }, 150);
        }

        return;
      }

      // 3) Click outside triggers/menus closes everything
      if (!anyMenu) {
        closeAllMenus();
      }
    });
  }

  // =========================
  // GLOBAL EVENT LISTENERS
  // =========================

  // Search input filtering
  safeOn(inputBuscar, "input", () => {
    currentPageTable = 1;
    currentPageCards = 1;
    renderTable();
  });

  // Role select filtering
  safeOn(selectFiltroRol, "change", () => {
    currentPageTable = 1;
    currentPageCards = 1;
    renderTable();
  });

  // Modal actions
  safeOn(btnNuevoUsuario, "click", () => openModalUsuario(null));
  safeOn(btnCerrarModalUsuario, "click", closeModalUsuario);
  safeOn(btnCancelarModalUsuario, "click", closeModalUsuario);

  safeOn(btnCerrarModalVerUsuario, "click", closeModalVerUsuario);

  // Role change behavior for the training program selector
  safeOn(inputCargo, "change", actualizarVisibilidadPrograma);

  // View switch buttons
  safeOn(btnVistaTabla, "click", setVistaTabla);
  safeOn(btnVistaTarjetas, "click", setVistaTarjetas);

  // ================================
  // FORM VALIDATION AND SUBMISSION
  // ================================
  safeOn(formUsuario, "submit", async (e) => {
    e.preventDefault();

    const payload = {
      nombre_completo: inputNombreCompleto ? inputNombreCompleto.value.trim() : "",
      tipo_documento: inputTipoDocumento ? inputTipoDocumento.value : "",
      numero_documento: inputNumeroDocumento ? inputNumeroDocumento.value.trim() : "",
      telefono: inputTelefono ? inputTelefono.value.trim() : "",
      cargo: inputCargo ? inputCargo.value : "",
      correo: inputCorreo ? inputCorreo.value.trim() : "",
      password: inputPassword ? inputPassword.value.trim() : "",
      direccion: inputDireccion ? inputDireccion.value.trim() : "",
      id_programa: inputPrograma ? inputPrograma.value : null,
    };

    // Normalize program assignment: only applicable for "Instructor"
    if (payload.cargo !== "Instructor" || !payload.id_programa) {
      payload.id_programa = null;
    }

    const isEdit = !!(hiddenUserId && hiddenUserId.value);

    // Use the globally guarded validation function to avoid runtime errors in cached builds
    if (!window.validateUserPayload(payload, { isEdit, currentId: hiddenUserId ? hiddenUserId.value : null }))
      return;

    const allEmpty =
      !payload.nombre_completo &&
      !payload.numero_documento &&
      !payload.telefono &&
      !payload.correo &&
      !payload.password &&
      !payload.direccion &&
      (!inputPrograma || !payload.id_programa);

    if (allEmpty) {
      toastError("Todos los campos son obligatorios.");
      if (inputNombreCompleto) inputNombreCompleto.focus();
      return;
    }

    if (!payload.nombre_completo) {
      toastError("El nombre completo es obligatorio.");
      if (inputNombreCompleto) inputNombreCompleto.focus();
      return;
    }

    if (!payload.tipo_documento) {
      toastError("Debe seleccionar un tipo de documento.");
      if (inputTipoDocumento) inputTipoDocumento.focus();
      return;
    }

    if (!payload.numero_documento) {
      toastError("El número de documento es obligatorio.");
      if (inputNumeroDocumento) inputNumeroDocumento.focus();
      return;
    }

    if (!numeroRegex.test(payload.numero_documento)) {
      toastError("El número de documento solo puede contener números.");
      if (inputNumeroDocumento) inputNumeroDocumento.focus();
      return;
    }

    if (!payload.telefono) {
      toastError("El teléfono es obligatorio.");
      if (inputTelefono) inputTelefono.focus();
      return;
    }

    if (!numeroRegex.test(payload.telefono)) {
      toastError("El teléfono solo puede contener números.");
      if (inputTelefono) inputTelefono.focus();
      return;
    }

    if (!payload.correo) {
      toastError("El correo electrónico es obligatorio.");
      if (inputCorreo) inputCorreo.focus();
      return;
    }

    if (!emailRegex.test(payload.correo)) {
      toastError("Ingrese un correo electrónico válido (debe contener '@').");
      if (inputCorreo) inputCorreo.focus();
      return;
    }

    if (!payload.cargo) {
      toastError("Debe seleccionar un cargo.");
      if (inputCargo) inputCargo.focus();
      return;
    }

    if (!payload.direccion) {
      toastError("La dirección es obligatoria.");
      if (inputDireccion) inputDireccion.focus();
      return;
    }

    if (!isEdit && !payload.password) {
      toastError("La contraseña es obligatoria para crear un usuario nuevo.");
      if (inputPassword) inputPassword.focus();
      return;
    }

    if (!VALID_TIPOS_DOCUMENTO.includes(payload.tipo_documento)) {
      toastError("Tipo de documento no válido. Debe ser CC, TI o CE.");
      return;
    }

    if (!VALID_CARGOS.includes(payload.cargo)) {
      toastError("Cargo no válido. Debe ser Coordinador, Subcoordinador, Instructor, Pasante o Aprendiz.");
      return;
    }

    if (payload.cargo === "Instructor" && !payload.id_programa) {
      toastError("Debe seleccionar un programa de formación para el Instructor.");
      return;
    }

    // Edit mode validation: prevent saving if there are no changes
    if (isEdit && originalEditData) {
      const currentData = {
        nombre_completo: payload.nombre_completo,
        tipo_documento: payload.tipo_documento,
        numero_documento: payload.numero_documento,
        telefono: payload.telefono,
        cargo: payload.cargo,
        correo: payload.correo,
        direccion: payload.direccion,
        id_programa: payload.cargo === "Instructor" && payload.id_programa ? String(payload.id_programa) : null,
      };

      const noHayCambios =
        JSON.stringify(currentData) === JSON.stringify(originalEditData) && !payload.password;

      if (noHayCambios) {
        toastInfo("Para actualizar debes modificar al menos un dato.");
        return;
      }
    }

    if (isEdit && hiddenUserId) {
      payload.id_usuario = hiddenUserId.value;
    }

    try {
      const data = isEdit ? await actualizarUsuario(payload) : await crearUsuario(payload);

      console.log("Respuesta procesada:", data);

      if (data.error) {
        toastError(data.error || "Ocurrió un error al procesar la solicitud.");
        return;
      }

      toastSuccess(
        data.mensaje || (isEdit ? "Usuario actualizado correctamente." : "Usuario creado correctamente.")
      );

      closeModalUsuario();
      await cargarUsuarios();
    } catch (error) {
      console.error("Error de red al guardar usuario:", error);
      toastError("Ocurrió un error al guardar el usuario (red/servidor).");
    }
  });

  // ================================
  // KEYBOARD SHORTCUTS: ESC CLOSES ACTIVE MODALS
  // ================================
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" || e.key === "Esc" || e.keyCode === 27) {
      if (modalUsuario && modalUsuario.classList.contains("active")) {
        closeModalUsuario();
      }

      if (modalVerUsuario && modalVerUsuario.classList.contains("active")) {
        closeModalVerUsuario();
      }
    }
  });

  // =========================
  // PASSWORD VISIBILITY TOGGLE
  // =========================
  (function initPasswordToggle() {
    const inputPass = document.getElementById("password");
    const btnToggle = document.getElementById("btnTogglePassword");
    const iconEye = document.getElementById("iconEye");
    const iconEyeOff = document.getElementById("iconEyeOff");

    if (!inputPass || !btnToggle || !iconEye || !iconEyeOff) return;

    btnToggle.addEventListener("click", () => {
      const isHidden = inputPass.type === "password";
      inputPass.type = isHidden ? "text" : "password";

      iconEye.classList.toggle("hidden", isHidden);
      iconEyeOff.classList.toggle("hidden", !isHidden);

      btnToggle.title = isHidden ? "Ocultar contraseña" : "Ver contraseña";
      btnToggle.setAttribute("aria-label", btnToggle.title);

      // Keep focus and place the cursor at the end for better UX
      inputPass.focus();
      const len = inputPass.value.length;
      inputPass.setSelectionRange(len, len);
    });
  })();

  // ================================
  // INITIAL LOAD
  // ================================
  cargarUsuarios();
  cargarProgramas();
  setVistaTabla();
});
