<script setup>
import { useAuthStore } from "@/stores/auth";
import defaultAvatarImg from "@images/avatars/avatar-1.png";
import axios from "axios";
import Swal from "sweetalert2";
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const { isAdmin } = useAuthStore();

const loading = ref(false);
const employee = ref({ user: { role: {} } });
const roles = ref([]);
const isEditing = ref(false);
const photoInput = ref(null);
const photoUploading = ref(false);
const photoLoadFailed = ref(false);

const triggerPhotoInput = () => {
  photoInput.value?.click();
};

const onPhotoChange = async (event) => {
  const file = event.target.files?.[0];
  if (!file) return;

  // Validación cliente: formato y tamaño (2MB)
  const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!validTypes.includes(file.type)) {
    Swal.fire({ icon: 'error', title: 'Formato no válido', text: 'Use JPG o PNG.' });
    event.target.value = '';
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    Swal.fire({ icon: 'error', title: 'Archivo muy grande', text: 'Máximo 2MB.' });
    event.target.value = '';
    return;
  }

  const previousPreview = photoPreview.value;
  const objectUrl = URL.createObjectURL(file);
  photoPreview.value = objectUrl;
  photo.value = file;
  photoUploading.value = true;

  try {
    const ok = await handleUpdateEmployeeDocument(true);
    if (ok) {
      photoPreview.value = null;
    } else {
      photoPreview.value = previousPreview;
    }
  } catch {
    photoPreview.value = previousPreview;
  } finally {
    URL.revokeObjectURL(objectUrl);
    photoUploading.value = false;
  }
  event.target.value = '';
};

const defaultPerformanceData = {
  salesMetrics: {
    historical: { totalAmount: 0, totalUnits: 0, ticketAverage: 0, unitsAverage: 0, totalOrders: 0 },
    currentMonth: { totalAmount: 0, totalUnits: 0, ticketAverage: 0, unitsAverage: 0, totalOrders: 0 },
  },
  profitabilityMetrics: {
    historical: { upsellRate: 0, avgOrderTime: 0, returnRate: 0 },
    currentMonth: { upsellRate: 0, avgOrderTime: 0, returnRate: 0 },
  },
  rankings: {
    topProductsByUnits: [],
    topProductsByAmount: [],
    topLabsByUnits: [],
    topLabsByAmount: [],
  },
  inventoryCounts: { total: 0, discrepancies: 0 },
};

const performanceData = ref(structuredClone ? structuredClone(defaultPerformanceData) : JSON.parse(JSON.stringify(defaultPerformanceData)));

const photoPreview = ref(null);

const activeTab = ref("profile");
const showEditDialog = ref(false);
const activeView = ref("performance"); // "performance" o "salary"

// Nómina: conceptos predefinidos (solo visual) e historial
const paymentHistory = ref([]);
const payrollEmployee = ref({ total_package_usd: null, saldo_deuda: 0 });
const distribution = ref(null);
const payrollLoading = ref(false);
const paymentForm = ref({
  month: new Date().getMonth() + 1,
  year: new Date().getFullYear(),
  total_package_usd: null,
});
const savingPackage = ref(false);

// Form data
const photo = ref(null);
const residenceLetter = ref(null);
const rif = ref(null);
const cv = ref(null);

// Computed properties: mapeo de roles con manejo seguro de nulos
const translatedRole = computed(() => {
  const roleMap = {
    admin: "Administrador",
    employee: "Empleado",
    supervisor: "Supervisor",
    hr: "Recursos Humanos",
  };
  const roleName = employee.value?.user?.role?.name;
  if (roleName == null) return "N/A";
  const normalized = String(roleName).toLowerCase();
  return roleMap[normalized] ?? roleName;
});

const fetchRoles = async () => {
  try {
    const response = await axios.get('/api/roles');
    roles.value = response.data?.data ?? [];
  } catch (error) {
    roles.value = [];
  }
};

const employeeInitials = computed(() => {
  const name = employee.value.name || "";
  const lastName = employee.value.last_name || "";
  return (name.charAt(0) + lastName.charAt(0)).toUpperCase();
});

/** URL base del backend para resolver rutas relativas (ej: http://farmacia-vue.test) */
const API_BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '');

/**
 * Resuelve la URL de la imagen: si es relativa, antepone API_BASE_URL.
 * Si es absoluta (http/https), la usa tal cual.
 */
const resolveImageUrl = (urlOrPath) => {
  if (!urlOrPath || typeof urlOrPath !== 'string') return null;
  const s = urlOrPath.trim();
  if (!s || s.toLowerCase() === 'null' || s === ' NULL') return null;
  if (s.startsWith('http://') || s.startsWith('https://')) return s;
  const base = API_BASE_URL || '';
  const path = s.startsWith('/') ? s : `/${s}`;
  return base ? `${base}${path}` : path;
};

/**
 * Obtiene la URL visualizable de la foto del empleado.
 * Prioriza data.photo_url; si no, construye desde data.photo.
 * Devuelve null si no hay imagen válida (fallback a iniciales).
 */
const employeePhotoUrl = computed(() => {
  const emp = employee.value;
  if (!emp) return null;
  const url = resolveImageUrl(emp.photo_url) ?? resolveImageUrl(emp.photo ? `/storage/${emp.photo}` : null);
  return url ? `${url}?t=${Date.now()}` : null;
});

/** Imagen por defecto cuando no hay foto o falla la carga */
const defaultAvatar = defaultAvatarImg;

/** URL a mostrar: foto, fallback por error, o imagen por defecto */
const avatarDisplaySrc = computed(() => {
  if (photoLoadFailed.value || !employeePhotoUrl.value) return defaultAvatar;
  return employeePhotoUrl.value;
});

/**
 * Extrae el objeto empleado de la respuesta API de forma segura.
 * Soporta profile: response.data.data y storeDocuments: response.data.data.data
 */
const getEmployeeFromResponse = (response) => {
  const d = response?.data?.data;
  if (!d || typeof d !== 'object') return null;
  if (d.data && typeof d.data === 'object' && 'id' in d.data) return d.data;
  return d;
};

// Methods
const fetchEmployee = async () => {
  try {
    const response = await axios.get(`/api/rrhh/employees/${route.params.id}`);

    // Validar respuesta (profile: employee en data.data)
    const emp = getEmployeeFromResponse(response) ?? response?.data?.data;
    if (emp && emp.id) {
      employee.value = emp;
    } else {
      employee.value = { 
        id: route.params.id,
        name: 'Error en respuesta',
        last_name: '',
        user: { role: {} } 
      };
    }
  } catch (error) {
    // No redirigir automáticamente, solo inicializar con objeto vacío
    employee.value = { 
      id: route.params.id,
      name: 'Empleado no encontrado',
      last_name: '',
      user: { role: {} } 
    };
  }
};

const fetchPerformanceData = async () => {
  try {
    const response = await axios.get(`/api/rrhh/employees/${route.params.id}/performance`);
    const data = response.data?.data;
    const fallback = structuredClone ? structuredClone(defaultPerformanceData) : JSON.parse(JSON.stringify(defaultPerformanceData));

    performanceData.value = {
      ...fallback,
      ...(data ?? {}),
      salesMetrics: {
        ...fallback.salesMetrics,
        ...(data?.salesMetrics ?? {}),
        historical: {
          ...fallback.salesMetrics.historical,
          ...(data?.salesMetrics?.historical ?? {}),
        },
        currentMonth: {
          ...fallback.salesMetrics.currentMonth,
          ...(data?.salesMetrics?.currentMonth ?? {}),
        },
      },
      profitabilityMetrics: {
        ...fallback.profitabilityMetrics,
        ...(data?.profitabilityMetrics ?? {}),
        historical: {
          ...fallback.profitabilityMetrics.historical,
          ...(data?.profitabilityMetrics?.historical ?? {}),
        },
        currentMonth: {
          ...fallback.profitabilityMetrics.currentMonth,
          ...(data?.profitabilityMetrics?.currentMonth ?? {}),
        },
      },
      rankings: {
        ...fallback.rankings,
        ...(data?.rankings ?? {}),
      },
      inventoryCounts: {
        ...fallback.inventoryCounts,
        ...(data?.inventoryCounts ?? {}),
      },
    };
  } catch (error) {
    performanceData.value = structuredClone ? structuredClone(defaultPerformanceData) : JSON.parse(JSON.stringify(defaultPerformanceData));
    loading.value = false;
  }
};

const handleMonthFilterChange = ({ month, year }) => {
  fetchPerformanceData(month, year);
};

const handleReset2FA = async () => {
  if (!employee.value?.id) return;

  const result = await Swal.fire({
    title: "¿Reiniciar 2FA?",
    text: "Esto obligará al empleado a configurar nuevamente el 2FA en su próximo acceso.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Sí, reiniciar",
    cancelButtonText: "Cancelar",
  });

  if (!result.isConfirmed) return;

  try {
    const { data } = await axios.put(`/api/rrhh/employees/${employee.value.id}/reset-2fa`);
    const ok = data?.data?.status ?? false;

    if (ok) {
      Swal.fire({
        icon: "success",
        title: "Listo",
        text: "2FA reiniciado correctamente",
      });
    } else {
      Swal.fire({
        icon: "error",
        title: "Error",
        text: "No se pudo reiniciar el 2FA",
      });
    }
  } catch (error) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: error.response?.data?.message || "No se pudo reiniciar el 2FA",
    });
  }
};

const handleDeleteEmployee = async () => {
  const result = await Swal.fire({
    title: "¿Está seguro?",
    text: "Esta acción eliminará la cuenta del empleado del sistema",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/api/rrhh/employees/${employee.value.id}`);
      if (response.data.status) {
        Swal.fire({
          icon: "success",
          title: "Eliminado",
          text: "El empleado ha sido eliminado correctamente",
        });
        router.push("/rrhh/employees");
      }
    } catch (error) {
      Swal.fire({
        icon: "error",
        title: "Error",
        text: "No se pudo eliminar el empleado",
      });
    }
  }
};

const handleUploadDocument = async (type, file) => {
  if (!file) return;

  const formData = new FormData();
  formData.append(type, file);

  try {
    await axios.post(`/rrhh/employees/${employee.value.id}/upload-document`, formData);
    await fetchEmployee();
    await Swal.fire({
      icon: "success",
      title: "Documento subido",
      text: "El documento ha sido subido exitosamente",
    });
  } catch (error) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "No se pudo subir el documento",
    });
  }
};

const handleDownloadDocument = async (type, filename) => {
  try {
    const response = await axios.get(`/rrhh/employees/${employee.value.id}/download-document/${type}`, {
      responseType: 'blob'
    });
    
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
  } catch (error) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "No se pudo descargar el documento",
    });
  }
};

const handleEditEmployee = () => { showEditDialog.value = true; };
const handleCloseEditDialog = () => { showEditDialog.value = false; };
const handleRefreshTable = async () => { fetchEmployee(); };

const toggleEditMode = () => {
  isEditing.value = !isEditing.value;
};

const saveProfileChanges = async () => {
  try {
    loading.value = true;
    const roleId = employee.value.user?.role_id ?? employee.value.user?.role?.id;
    const response = await axios.put(`/api/rrhh/employees/${employee.value.id}`, {
      name: employee.value.name,
      last_name: employee.value.last_name,
      identification: employee.value.identification,
      email: employee.value.user?.email,
      role: roleId,
    });
    
    if (response.data.status || response.data.success) {
      await Swal.fire({
        icon: "success",
        title: "Éxito",
        text: "Perfil actualizado correctamente",
      });
      isEditing.value = false;
      await fetchEmployee();
    }
  } catch (error) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: error.response?.data?.message || "No se pudo actualizar el perfil",
    });
  } finally {
    loading.value = false;
  }
};

const fetchPayments = async () => {
  if (!employee.value?.id) return;
  payrollLoading.value = true;
  try {
    const params = {
      month: paymentForm.value.month,
      year: paymentForm.value.year,
    };
    const pkg = paymentForm.value.total_package_usd ?? payrollEmployee.value.total_package_usd;
    if (pkg != null && pkg !== '') params.total_package_usd = Number(pkg);
    const { data } = await axios.get(`/api/rrhh/employees/${employee.value.id}/payments`, { params });
    const res = data?.data ?? data;
    paymentHistory.value = res.history ?? [];
    payrollEmployee.value = res.employee ?? { total_package_usd: null, saldo_deuda: 0 };
    distribution.value = res.distribution ?? null;
    if (paymentForm.value.total_package_usd == null && payrollEmployee.value.total_package_usd != null) {
      paymentForm.value.total_package_usd = Number(payrollEmployee.value.total_package_usd);
    }
  } catch {
    paymentHistory.value = [];
    payrollEmployee.value = { total_package_usd: null, saldo_deuda: 0 };
    distribution.value = null;
  } finally {
    payrollLoading.value = false;
  }
};

const savePackage = async () => {
  if (!employee.value?.id) return;
  const pkg = paymentForm.value.total_package_usd ?? payrollEmployee.value.total_package_usd;
  if (pkg == null || pkg === '') {
    Swal.fire({ icon: 'warning', title: 'Dato requerido', text: 'Indique el paquete total (USD).' });
    return;
  }
  savingPackage.value = true;
  try {
    await axios.put(`/api/rrhh/employees/${employee.value.id}/payroll-settings`, {
      total_package_usd: Number(pkg),
    });
    await Swal.fire({ icon: 'success', title: 'Guardado', text: 'Paquete actualizado en el perfil.' });
    await fetchPayments();
    await fetchEmployee();
  } catch {
    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar el paquete.' });
  } finally {
    savingPackage.value = false;
  }
};

const formatCurrency = (value) => {
  const n = Number(value);
  return Number.isFinite(n) ? n.toLocaleString('en-US', { style: 'currency', currency: 'USD' }) : '—';
};

const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
const formatPeriod = (year, month) => `${monthNames[Number(month) - 1]} ${year}`;
const formatFecha = (fechaStr) => {
  if (!fechaStr) return '—';
  const d = new Date(fechaStr);
  return Number.isFinite(d.getTime()) ? d.toLocaleDateString('es-VE', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
};
const formatVes = (value) => {
  const n = Number(value);
  return Number.isFinite(n) ? n.toLocaleString('es-VE', { style: 'decimal', minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' Bs.S' : '—';
};


const handleDownloadFile = async (file) => {
  try {
    const response = await axios.get(`/api/rrhh/employees/${employee.value.id}/download/${file}`, { responseType: "blob" });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `${file}-employee-${employee.value.id}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
  } catch (error) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "No se pudo descargar el archivo",
    });
  }
};

  const handleUpdateEmployeeDocument = async (photoOnly = false) => {
    // Validar que el empleado existe
  if (!employee.value || !employee.value.id) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "No se encontró información del empleado. Por favor recargue la página.",
    });
    return false;
  }

  if (!photo.value && !residenceLetter.value && !rif.value && !cv.value) {
    Swal.fire({
      icon: "warning",
      title: "Advertencia",
      text: "Por favor seleccione al menos un archivo para actualizar",
    });
    return false;
  }

  const normalizeFile = (value) => {
    if (!value) return null;
    if (Array.isArray(value)) return value[0] ?? null;
    return value;
  };

  const formData = new FormData();

  const photoFile = normalizeFile(photo.value);
  const residenceLetterFile = normalizeFile(residenceLetter.value);
  const rifFile = normalizeFile(rif.value);
  const cvFile = normalizeFile(cv.value);

  if (photoFile) formData.append('photo', photoFile);
  if (residenceLetterFile) formData.append('residence_letter', residenceLetterFile);
  if (rifFile) formData.append('rif', rifFile);
  if (cvFile) formData.append('cv', cvFile);

  try {
    loading.value = true;
    const response = await axios.post(
      `/api/rrhh/employees/${employee.value.id}/documents`,
      formData,
      {
        transformRequest: [(data, headers) => {
          if (data instanceof FormData) {
            delete headers['Content-Type'];
          }
          return data;
        }],
      }
    );

    if (response.data?.status === 'success' || response.data?.success) {
      if (photoOnly) {
        Swal.fire({
          icon: "success",
          title: "Foto actualizada",
          text: "La foto de perfil se ha guardado correctamente",
          timer: 2000,
          showConfirmButton: false,
        });
      } else {
        await Swal.fire({
          icon: "success",
          title: "Éxito",
          text: "Documentos actualizados correctamente",
        });
      }
      
      // Sincronización reactiva: actualizar employee con la respuesta (photo_url) inmediatamente
      const updatedEmployee = getEmployeeFromResponse(response);
      if (updatedEmployee && (updatedEmployee.photo_url ?? updatedEmployee.photo)) {
        employee.value = { ...employee.value, ...updatedEmployee };
      }
      // Recargar empleado para persistencia tras F5
      await fetchEmployee();
      
      // Limpiar después de recargar (para que employeePhotoUrl muestre la nueva imagen)
      photo.value = null;
      residenceLetter.value = null;
      rif.value = null;
      cv.value = null;
      if (!photoOnly) photoPreview.value = null;
      return true;
    } else {
      throw new Error(response.data.message || 'Error al actualizar documentos');
    }
  } catch (error) {
    console.error('Error updating documents:', error);
    let errorMessage = "No se pudieron actualizar los documentos";
    
    if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    } else if (error.response?.data?.errors) {
      errorMessage = Object.values(error.response.data.errors).flat().join(', ');
    } else if (error.message) {
      errorMessage = error.message;
    }
    
    Swal.fire({
      icon: "error",
      title: "Error",
      text: errorMessage,
    });
    return false;
  } finally {
    loading.value = false;
  }
};

// Sincronizar role_id desde role cuando el empleado se carga (por si la API devuelve solo el objeto role)
watch(
  () => employee.value?.id,
  () => { photoLoadFailed.value = false; },
  { immediate: false }
);

watch(
  () => employee.value?.user,
  (user) => {
    if (user?.role?.id && user.role_id == null) {
      user.role_id = user.role.id;
    }
  },
  { immediate: true, deep: true }
);

watch(activeTab, (tab) => {
  if (tab === 'salary' && employee.value?.id) fetchPayments();
});
watch([() => paymentForm.value.month, () => paymentForm.value.year], () => {
  if (activeTab.value === 'salary' && employee.value?.id) fetchPayments();
});

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchEmployee(),
    fetchRoles(),
    fetchPerformanceData()
  ]);
});

</script>

<template>
  <div>
    <EmployeeFormDialog
      v-model="showEditDialog"
      :roles="roles"
      :selectedEmployee="employee"
      :clear-data-on-close="false"
      @refresh-table="handleRefreshTable"
      @close="handleCloseEditDialog"
    />

    <VRow>
      <VCol cols="12" md="3">
        <!-- Botones de Navegación -->
        <div class="d-flex flex-column gap-2 mb-3">
          <VBtn
            :color="activeView === 'performance' ? 'primary' : 'secondary'"
            :variant="activeView === 'performance' ? 'flat' : 'tonal'"
            block
            prepend-icon="tabler-chart-bar"
            @click="activeView = 'performance'"
          >
            Resumen de Desempeño
          </VBtn>
          <VBtn
            :color="activeView === 'salary' ? 'primary' : 'secondary'"
            :variant="activeView === 'salary' ? 'flat' : 'tonal'"
            block
            prepend-icon="tabler-wallet"
            @click="activeView = 'salary'"
          >
            Gestión Salarial
          </VBtn>
        </div>

        <VCard class="pb-3">
          <VCardText class="text-center pt-4">
            <div class="position-relative d-inline-block mb-3">
              <input
                ref="photoInput"
                type="file"
                accept="image/jpeg,image/jpg,image/png"
                class="d-none"
                @change="onPhotoChange"
              />
              <div class="avatar-wrapper position-relative">
                <VAvatar
                  size="100"
                  variant="tonal"
                  rounded="circle"
                  class="elevation-2"
                >
                  <img
                    v-if="photoPreview"
                    :src="photoPreview"
                    alt=""
                    class="v-avatar__img"
                  >
                  <img
                    v-else-if="employeePhotoUrl || photoLoadFailed"
                    :src="avatarDisplaySrc"
                    alt=""
                    class="v-avatar__img"
                    @error="photoLoadFailed = true"
                  >
                  <template v-else>
                    {{ employeeInitials }}
                  </template>
                </VAvatar>
                <!-- Overlay "Subiendo..." durante la carga -->
                <div
                  v-if="photoUploading"
                  class="avatar-upload-overlay"
                >
                  <VProgressCircular indeterminate color="white" size="32" width="3" />
                  <span class="text-caption text-white font-weight-medium">Subiendo...</span>
                </div>
              </div>
              <div
                v-if="isAdmin && !photoUploading"
                class="avatar-camera-badge"
                @click="triggerPhotoInput"
              >
                <VIcon icon="tabler-camera" size="18" color="white" />
              </div>
            </div>

            <div class="mb-3">
              <div v-if="!isEditing">
                <h3 class="text-h5 font-weight-bold mb-1">
                  {{ employee.name }} {{ employee.last_name }}
                </h3>
                <VChip
                  :color="employee.is_active ? 'success' : 'error'"
                  variant="tonal"
                  size="small"
                  class="mb-2"
                >
                  {{ employee.is_active ? 'Activo' : 'Inactivo' }}
                </VChip>
              </div>
              <div v-else>
                <VTextField
                  v-model="employee.name"
                  label="Nombre"
                  density="compact"
                  class="mb-2"
                />
                <VTextField
                  v-model="employee.last_name"
                  label="Apellido"
                  density="compact"
                  class="mb-2"
                />
              </div>
            </div>

            <VDivider class="my-3" />

            <div class="text-left px-2">
              <div class="mb-3">
                <div class="text-caption text-disabled mb-1">IDENTIFICACIÓN</div>
                <div v-if="!isEditing" class="text-body-2 text-high-emphasis">
                  {{ employee?.identification ?? '—' }}
                </div>
                <VTextField
                  v-else
                  v-model="employee.identification"
                  label="Cédula"
                  density="compact"
                  hide-details
                />
              </div>

              <div class="mb-3">
                <div class="text-caption text-disabled mb-1">CORREO</div>
                <div v-if="!isEditing" class="text-body-2 text-high-emphasis">
                  {{ employee?.user?.email ?? '—' }}
                </div>
                <VTextField
                  v-else
                  v-model="employee.user.email"
                  label="Email"
                  density="compact"
                  hide-details
                />
              </div>

              <div class="mb-3">
                <div class="text-caption text-disabled mb-1">ROL</div>
                <div v-if="!isEditing" class="text-body-2 font-weight-medium text-primary d-flex align-center">
                  <VIcon icon="tabler-shield-check" size="16" class="mr-1" />
                  {{ translatedRole }}
                </div>
                <VSelect
                  v-else
                  v-model="employee.user.role_id"
                  :items="roles"
                  item-title="name"
                  item-value="id"
                  label="Rol"
                  density="compact"
                  hide-details
                  prepend-inner-icon="tabler-shield-check"
                />
              </div>
            </div>

            <VDivider class="my-3" />

            <!-- Botones de Acción -->
            <div class="d-flex flex-column gap-2">
              <VBtn
                v-if="isAdmin"
                :color="isEditing ? 'success' : 'primary'"
                :variant="isEditing ? 'flat' : 'tonal'"
                block
                size="small"
                :prepend-icon="isEditing ? 'tabler-check' : 'tabler-pencil'"
                @click="isEditing ? saveProfileChanges() : toggleEditMode()"
                :loading="loading"
              >
                {{ isEditing ? 'Guardar Cambios' : 'Editar Perfil' }}
              </VBtn>

              <VBtn
                v-if="isAdmin && isEditing"
                color="secondary"
                variant="tonal"
                block
                size="small"
                prepend-icon="tabler-x"
                @click="toggleEditMode()"
              >
                Cancelar
              </VBtn>

              <VBtn
                v-if="isAdmin"
                color="warning"
                variant="tonal"
                block
                size="small"
                prepend-icon="tabler-refresh"
                :disabled="!employee || !employee.id"
                @click="handleReset2FA"
              >
                Reiniciar 2FA
              </VBtn>

              <VBtn
                v-if="isAdmin"
                color="error"
                variant="tonal"
                block
                size="small"
                prepend-icon="tabler-trash"
                @click="handleDeleteEmployee"
              >
                Eliminar cuenta
              </VBtn>
            </div>

            <!-- Sección de Documentos -->
            <VDivider class="my-3" />
            <div class="text-left">
              <div class="text-caption text-disabled mb-2">DOCUMENTOS</div>
              <div class="d-flex flex-column gap-1">
                <div class="d-flex align-center justify-between">
                  <span class="text-body-2">Identificación</span>
                  <VBtn
                    v-if="employee.residence_letter"
                    icon="tabler-download"
                    size="x-small"
                    variant="text"
                    @click="handleDownloadFile('residence_letter')"
                  />
                </div>
                <div class="d-flex align-center justify-between">
                  <span class="text-body-2">RIF</span>
                  <VBtn
                    v-if="employee.rif"
                    icon="tabler-download"
                    size="x-small"
                    variant="text"
                    @click="handleDownloadFile('rif')"
                  />
                </div>
                <div class="d-flex align-center justify-between">
                  <span class="text-body-2">CV</span>
                  <VBtn
                    v-if="employee.cv"
                    icon="tabler-download"
                    size="x-small"
                    variant="text"
                    @click="handleDownloadFile('cv')"
                  />
                </div>
              </div>
              
              <div v-if="isAdmin" class="mt-2">
                <VFileInput
                  v-model="residenceLetter"
                  label="Subir Identificación"
                  accept=".pdf"
                  density="compact"
                  hide-details
                  prepend-icon="tabler-upload"
                  @change="(file) => {
                    if (file) {
                      handleUpdateEmployeeDocument();
                    }
                  }"
                />
                <VFileInput
                  v-model="rif"
                  label="Subir RIF"
                  accept=".pdf"
                  density="compact"
                  hide-details
                  prepend-icon="tabler-upload"
                  class="mt-1"
                  @change="(file) => {
                    if (file) {
                      handleUpdateEmployeeDocument();
                    }
                  }"
                />
                <VFileInput
                  v-model="cv"
                  label="Subir CV"
                  accept=".pdf"
                  density="compact"
                  hide-details
                  prepend-icon="tabler-upload"
                  class="mt-1"
                  @change="(file) => {
                    if (file) {
                      handleUpdateEmployeeDocument();
                    }
                  }"
                />
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="9">
        
        <!-- Vista de Resumen de Desempeño -->
        <div v-if="activeView === 'performance'">
          <h2 class="text-h5 mb-4 font-weight-bold">Dashboard de Rendimiento Avanzado</h2>
          
          <!-- KPIs Principales -->
          <VRow class="mb-4">
            <VCol cols="12" sm="6" md="3">
              <VCard color="primary" theme="dark" height="120">
                <VCardText class="d-flex align-center justify-center text-center">
                  <div>
                    <VAvatar color="white" variant="tonal" class="mb-2">
                      <VIcon icon="tabler-cash" color="white" />
                    </VAvatar>
                    <div class="text-h5 font-weight-bold">{{ performanceData.salesMetrics.currentMonth.totalAmount.toLocaleString('en-US', { style: 'currency', currency: 'USD' }) }}</div>
                    <div class="text-caption">Ventas Mes</div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VCard variant="tonal" color="success" height="120">
                <VCardText class="d-flex align-center justify-center text-center">
                  <div>
                    <VAvatar color="success" variant="tonal" class="mb-2">
                      <VIcon icon="tabler-package" />
                    </VAvatar>
                    <div class="text-h5 font-weight-bold">{{ performanceData.salesMetrics.currentMonth.totalUnits.toLocaleString() }}</div>
                    <div class="text-caption">Unidades</div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VCard variant="tonal" color="warning" height="120">
                <VCardText class="d-flex align-center justify-center text-center">
                  <div>
                    <VAvatar color="warning" variant="tonal" class="mb-2">
                      <VIcon icon="tabler-receipt" />
                    </VAvatar>
                    <div class="text-h5 font-weight-bold">{{ performanceData.salesMetrics.currentMonth.ticketAverage.toLocaleString('en-US', { style: 'currency', currency: 'USD' }) }}</div>
                    <div class="text-caption">Ticket Prom.</div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VCard variant="tonal" color="info" height="120">
                <VCardText class="d-flex align-center justify-center text-center">
                  <div>
                    <VAvatar color="info" variant="tonal" class="mb-2">
                      <VIcon icon="tabler-box-multiple" />
                    </VAvatar>
                    <div class="text-h5 font-weight-bold">{{ performanceData.salesMetrics.currentMonth.unitsAverage.toFixed(1) }}</div>
                    <div class="text-caption">Unds. Prom.</div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- KPIs Históricos -->
          <VRow class="mb-4">
            <VCol cols="12">
              <VCard>
                <VCardTitle class="text-subtitle-1">
                  <VIcon icon="tabler-history" class="mr-2" />
                  Datos Históricos Acumulados
                </VCardTitle>
                <VDivider />
                <VCardText>
                  <VRow>
                    <VCol cols="12" sm="6" md="3">
                      <VCard variant="tonal" color="primary" height="100">
                        <VCardText class="d-flex align-center justify-center text-center">
                          <div>
                            <VAvatar color="primary" variant="tonal" class="mb-2">
                              <VIcon icon="tabler-cash-bank" />
                            </VAvatar>
                            <div class="text-h6 font-weight-bold">{{ performanceData.salesMetrics.historical.totalAmount.toLocaleString('en-US', { style: 'currency', currency: 'USD' }) }}</div>
                            <div class="text-caption">Ventas Hist.</div>
                          </div>
                        </VCardText>
                      </VCard>
                    </VCol>
                    <VCol cols="12" sm="6" md="3">
                      <VCard variant="tonal" color="success" height="100">
                        <VCardText class="d-flex align-center justify-center text-center">
                          <div>
                            <VAvatar color="success" variant="tonal" class="mb-2">
                              <VIcon icon="tabler-stack" />
                            </VAvatar>
                            <div class="text-h6 font-weight-bold">{{ performanceData.salesMetrics.historical.totalUnits.toLocaleString() }}</div>
                            <div class="text-caption">Unidades Hist.</div>
                          </div>
                        </VCardText>
                      </VCard>
                    </VCol>
                    <VCol cols="12" sm="6" md="3">
                      <VCard variant="tonal" color="warning" height="100">
                        <VCardText class="d-flex align-center justify-center text-center">
                          <div>
                            <VAvatar color="warning" variant="tonal" class="mb-2">
                              <VIcon icon="tabler-chart-bar" />
                            </VAvatar>
                            <div class="text-h6 font-weight-bold">{{ performanceData.salesMetrics.historical.ticketAverage.toLocaleString('en-US', { style: 'currency', currency: 'USD' }) }}</div>
                            <div class="text-caption">Ticket Hist.</div>
                          </div>
                        </VCardText>
                      </VCard>
                    </VCol>
                    <VCol cols="12" sm="6" md="3">
                      <VCard variant="tonal" color="info" height="100">
                        <VCardText class="d-flex align-center justify-center text-center">
                          <div>
                            <VAvatar color="info" variant="tonal" class="mb-2">
                              <VIcon icon="tabler-chart-dots-3" />
                            </VAvatar>
                            <div class="text-h6 font-weight-bold">{{ performanceData.salesMetrics.historical.unitsAverage.toFixed(1) }}</div>
                            <div class="text-caption">Unds. Hist.</div>
                          </div>
                        </VCardText>
                      </VCard>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- KPIs de Rentabilidad -->
          <VRow class="mb-4">
            <VCol cols="12" md="6">
              <VCard height="180">
                <VCardTitle class="text-subtitle-1">Métricas de Rentabilidad</VCardTitle>
                <VDivider />
                <VCardText>
                  <VRow>
                    <VCol cols="6">
                      <div class="text-center mb-3">
                        <VAvatar color="success" variant="tonal" class="mb-2">
                          <VIcon icon="tabler-trending-up" />
                        </VAvatar>
                        <div class="text-h5 font-weight-bold text-success">{{ performanceData.profitabilityMetrics.currentMonth.upsellRate }}%</div>
                        <div class="text-caption">UP-selling</div>
                      </div>
                    </VCol>
                    <VCol cols="6">
                      <div class="text-center mb-3">
                        <VAvatar color="warning" variant="tonal" class="mb-2">
                          <VIcon icon="tabler-clock" />
                        </VAvatar>
                        <div class="text-h5 font-weight-bold text-warning">{{ performanceData.profitabilityMetrics.currentMonth.avgOrderTime }}m</div>
                        <div class="text-caption">Tiempo Orden</div>
                      </div>
                    </VCol>
                    <VCol cols="12">
                      <div class="text-center">
                        <VAvatar color="error" variant="tonal" class="mb-2">
                          <VIcon icon="tabler-arrow-back-up" />
                        </VAvatar>
                        <div class="text-h5 font-weight-bold text-error">{{ performanceData.profitabilityMetrics.currentMonth.returnRate }}%</div>
                        <div class="text-caption">Devoluciones</div>
                      </div>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="6">
              <VCard variant="tonal" color="error" height="180">
                <VCardTitle class="text-subtitle-1">Control de Inventario</VCardTitle>
                <VDivider />
                <VCardText class="text-center d-flex flex-column justify-center" style="block-size: 100px;">
                  <VAvatar color="white" variant="tonal" class="mb-3">
                    <VIcon icon="tabler-package" color="error" />
                  </VAvatar>
                  <div class="text-h3 font-weight-bold">{{ performanceData.inventoryCounts.total }}</div>
                  <div class="text-caption">Conteos Realizados</div>
                  <VDivider class="my-2" />
                  <div class="text-h6 font-weight-bold text-error">
                    {{ performanceData.inventoryCounts.discrepancies }} Discrepancias
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- Rankings -->
          <VRow>
            <VCol cols="12" md="6">
              <VCard>
                <VCardTitle class="text-subtitle-1">Top 10 Productos por Unidades</VCardTitle>
                <VDivider />
                <VCardText style="max-block-size: 300px; overflow-y: auto;">
                  <VList density="compact">
                    <VListItem v-for="(product, i) in performanceData.rankings.topProductsByUnits" :key="i">
                      <template #prepend>
                        <VChip :color="i < 3 ? 'primary' : 'grey-lighten-2'" size="small" class="mr-2">
                          {{ i + 1 }}
                        </VChip>
                      </template>
                      <VListItemTitle class="text-body-2">{{ product.name }}</VListItemTitle>
                      <template #append>
                        <div class="text-right">
                          <div class="text-caption font-weight-bold">{{ product.units }} unds</div>
                          <div class="text-caption text-disabled">${{ product.amount.toLocaleString() }}</div>
                        </div>
                      </template>
                    </VListItem>
                  </VList>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="6">
              <VCard>
                <VCardTitle class="text-subtitle-1">Top 10 Productos por Monto</VCardTitle>
                <VDivider />
                <VCardText style="max-block-size: 300px; overflow-y: auto;">
                  <VList density="compact">
                    <VListItem v-for="(product, i) in performanceData.rankings.topProductsByAmount" :key="i">
                      <template #prepend>
                        <VChip :color="i < 3 ? 'success' : 'grey-lighten-2'" size="small" class="mr-2">
                          {{ i + 1 }}
                        </VChip>
                      </template>
                      <VListItemTitle class="text-body-2">{{ product.name }}</VListItemTitle>
                      <template #append>
                        <div class="text-right">
                          <div class="text-caption font-weight-bold">${{ product.amount.toLocaleString() }}</div>
                          <div class="text-caption text-disabled">{{ product.units }} unds</div>
                        </div>
                      </template>
                    </VListItem>
                  </VList>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- Rankings de Laboratorios -->
          <VRow class="mt-4">
            <VCol cols="12" md="6">
              <VCard>
                <VCardTitle class="text-subtitle-1">Top 5 Laboratorios por Unidades</VCardTitle>
                <VDivider />
                <VCardText>
                  <VList density="compact">
                    <VListItem v-for="(lab, i) in performanceData.rankings.topLabsByUnits" :key="i">
                      <template #prepend>
                        <VChip :color="i < 2 ? 'warning' : 'grey-lighten-2'" size="small" class="mr-2">
                          {{ i + 1 }}
                        </VChip>
                      </template>
                      <VListItemTitle class="text-body-2">{{ lab.name }}</VListItemTitle>
                      <template #append>
                        <div class="text-right">
                          <div class="text-caption font-weight-bold">{{ lab.units }} unds</div>
                          <div class="text-caption text-disabled">${{ lab.amount.toLocaleString() }}</div>
                        </div>
                      </template>
                    </VListItem>
                  </VList>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="6">
              <VCard>
                <VCardTitle class="text-subtitle-1">Top 5 Laboratorios por Monto</VCardTitle>
                <VDivider />
                <VCardText>
                  <VList density="compact">
                    <VListItem v-for="(lab, i) in performanceData.rankings.topLabsByAmount" :key="i">
                      <template #prepend>
                        <VChip :color="i < 2 ? 'info' : 'grey-lighten-2'" size="small" class="mr-2">
                          {{ i + 1 }}
                        </VChip>
                      </template>
                      <VListItemTitle class="text-body-2">{{ lab.name }}</VListItemTitle>
                      <template #append>
                        <div class="text-right">
                          <div class="text-caption font-weight-bold">${{ lab.amount.toLocaleString() }}</div>
                          <div class="text-caption text-disabled">{{ lab.units }} unds</div>
                        </div>
                      </template>
                    </VListItem>
                  </VList>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <!-- Vista de Gestión Salarial -->
        <div v-else-if="activeView === 'salary'">
          <h2 class="text-h5 mb-4 font-weight-bold">Gestión Salarial</h2>
          
          <!-- Conceptos predefinidos: solo paquete editable; el resto se distribuye según el mes (solo visual, no se cierra nómina aquí) -->
          <VCard>
            <VCardTitle class="d-flex align-center gap-2">
              <VIcon icon="tabler-list" />
              Conceptos predefinidos
            </VCardTitle>
            <VDivider />
            <VCardText v-if="payrollLoading">
              <VProgressLinear indeterminate color="primary" />
            </VCardText>
            <VCardText v-else>
              <p class="text-body-2 text-medium-emphasis mb-3">
                El único valor editable es el <strong>paquete total</strong>. Los demás conceptos se distribuyen según ese mes (consumo salud y deuda anterior). Solo visual; aquí no se cierra nómina.
              </p>
              <VRow dense>
                <VCol cols="12">
                  <div class="d-flex align-stretch">
                    <VTextField
                      v-model.number="paymentForm.total_package_usd"
                      label="Paquete total (USD)"
                      type="number"
                      min="0"
                      step="0.01"
                      variant="outlined"
                      density="comfortable"
                      placeholder="Ej. 200"
                      hide-details
                      class="flex-grow-1"
                    />
                    <VBtn
                      v-if="isAdmin"
                      color="primary"
                      variant="flat"
                      :loading="savingPackage"
                      @click="savePackage"
                      class="ml-2"
                      height="48"
                    >
                      Guardar paquete en perfil
                    </VBtn>
                  </div>
                </VCol>
                <VCol cols="12" sm="4" md="2">
                  <VSelect
                    v-model.number="paymentForm.month"
                    :items="Array.from({ length: 12 }, (_, i) => ({ title: monthNames[i], value: i + 1 }))"
                    label="Mes"
                    variant="outlined"
                    density="comfortable"
                  />
                </VCol>
                <VCol cols="12" sm="4" md="2">
                  <VTextField
                    v-model.number="paymentForm.year"
                    label="Año"
                    type="number"
                    min="2020"
                    variant="outlined"
                    density="comfortable"
                  />
                </VCol>
                <VCol cols="12" sm="4" md="2" class="d-flex align-end">
                  <VBtn variant="tonal" color="secondary" @click="fetchPayments">
                    Actualizar distribución
                  </VBtn>
                </VCol>
              </VRow>
              <VCard v-if="distribution" variant="tonal" class="mt-4" density="comfortable">
                <VCardTitle class="text-subtitle-2">
                  Distribución para {{ formatPeriod(distribution.year, distribution.month) }}
                </VCardTitle>
                <VCardText>
                  <VList density="compact">
                    <VListItem
                      v-for="(c, i) in distribution.concepts"
                      :key="i"
                      class="px-0"
                    >
                      <template #prepend>
                        <VIcon v-if="c.fixed" icon="tabler-lock" size="18" class="text-medium-emphasis mr-2" />
                        <VIcon v-else icon="tabler-calculator" size="18" class="text-medium-emphasis mr-2" />
                      </template>
                      <VListItemTitle>{{ c.name }}</VListItemTitle>
                      <template #append>
                        <span class="font-weight-medium text-high-emphasis">{{ formatCurrency(c.amount) }}</span>
                      </template>
                    </VListItem>
                    <VDivider class="my-2" />
                    <VListItem class="px-0">
                      <VListItemTitle class="font-weight-bold">Total a cobrar (Salario + Bono + Incentivo)</VListItemTitle>
                      <template #append>
                        <span class="font-weight-bold text-primary text-high-emphasis">{{ formatCurrency(distribution.total_a_cobrar) }}</span>
                      </template>
                    </VListItem>
                  </VList>
                </VCardText>
              </VCard>
              <p v-else-if="paymentForm.total_package_usd != null && paymentForm.total_package_usd > 0" class="text-medium-emphasis mt-4 mb-3">
                Seleccione mes y año para ver la distribución.
              </p>
              <p v-else class="text-medium-emphasis mt-4 mb-3">
                Defina el paquete total y seleccione mes y año para ver cómo se distribuyen los conceptos.
              </p>
            </VCardText>
          </VCard>

          <!-- Historial de pagos: sin acciones, columnas obligatorias, orden descendente -->
          <VCard v-if="!payrollLoading" class="mt-4">
            <VCardTitle class="d-flex align-center gap-2">
              <VIcon icon="tabler-history" />
              Historial de pagos
            </VCardTitle>
            <VDivider />
            <VCardText>
              <VTable v-if="paymentHistory.length > 0">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th class="text-right">Salario Básico Mensual</th>
                    <th class="text-right">Cestaticket Socialista de Ley</th>
                    <th class="text-right">Asistencia Social de Salud (Art. 105 LOTTT)</th>
                    <th class="text-right">Gratificación Extraordinaria por Rendimiento</th>
                    <th class="text-right">Total Pagado (USD)</th>
                    <th class="text-right">Total Pagado (VES)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in paymentHistory" :key="row.id">
                    <td class="text-high-emphasis">{{ formatFecha(row.fecha) }}</td>
                    <td class="text-right text-high-emphasis">{{ formatCurrency(row.salario_base) }}</td>
                    <td class="text-right text-high-emphasis">{{ formatCurrency(row.bono_alimentacion) }}</td>
                    <td class="text-right text-high-emphasis">{{ formatCurrency(row.beneficio_salud) }}</td>
                    <td class="text-right text-high-emphasis">{{ formatCurrency(row.incentivo_metas) }}</td>
                    <td class="text-right text-high-emphasis">{{ formatCurrency(row.total_pagado_usd) }}</td>
                    <td class="text-right text-high-emphasis">{{ formatVes(row.total_pagado_ves) }}</td>
                  </tr>
                </tbody>
              </VTable>
              <p v-else class="text-medium-emphasis mb-0">No hay pagos procesados.</p>
            </VCardText>
          </VCard>
        </div>



      </VCol>
    </VRow>
    
  </div>
</template>

<style scoped>
.cursor-pointer { cursor: pointer; }

.avatar-wrapper {
  display: inline-block;
}

.avatar-wrapper .v-avatar__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-upload-overlay {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.avatar-camera-badge {
  position: absolute;
  inset-block-end: 0;
  inset-inline-end: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgb(var(--v-theme-primary));
  border-radius: 50%;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  transition: opacity 0.2s ease;
}
.avatar-camera-badge:hover {
  opacity: 0.9;
}

/* Estilos para la card izquierda compacta */
.compact-card .v-card-text {
  padding: 1rem !important;
}

.compact-field .v-field {
  font-size: 0.875rem !important;
}

/* Estilos para los botones de navegación */
.nav-btn {
  transition: all 0.2s ease;
}

.nav-btn.active {
  box-shadow: 0 4px 8px rgba(var(--v-theme-primary), 0.2);
}

/* Estilos para la sección de documentos */
.document-item {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding-block: 0.5rem;
  padding-inline: 0;
}

.document-item:last-child {
  border-block-end: none;
}

/* Estilos responsivos */
@media (max-width: 960px) {
  .v-col-md-3 {
    margin-block-end: 1.5rem;
  }

  .nav-btn {
    font-size: 0.875rem;
    padding-block: 0.5rem;
    padding-inline: 0.75rem;
  }
}

@media (max-width: 600px) {
  .v-avatar {
    block-size: 80px !important;
    inline-size: 80px !important;
  }

  .text-h5 {
    font-size: 1.25rem !important;
  }
}
</style>
