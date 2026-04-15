<script setup>
import EmployeeFormDialog from "@/components/dialogs/EmployeeFormDialog.vue";
import { useAuthStore } from "@/stores/auth";
import defaultAvatarImg from "@images/avatars/avatar-1.png";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useDisplay } from "vuetify";

const route = useRoute();
const router = useRouter();
const { isAdmin } = useAuthStore();
const { mobile } = useDisplay();

const loading = ref(false);
const employee = ref({ user: { role: {} } });
const roles = ref([]);
const isEditing = ref(false);
const showEditDialog = ref(false);
const photoInput = ref(null);
const photoUploading = ref(false);
const photoLoadFailed = ref(false);
const photoPreview = ref(null);
const activeView = ref("performance"); // "performance" o "salary"

// Configuración de documentos
const photo = ref(null);
const residenceLetter = ref(null);
const rif = ref(null);
const cv = ref(null);

const triggerPhotoInput = () => {
  if (isAdmin) photoInput.value?.click();
};

/** Compresión de imagen usando Canvas */
const compressImage = (file, maxWidth = 1200, quality = 0.8) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (event) => {
      const img = new Image();
      img.src = event.target.result;
      img.onload = () => {
        const canvas = document.createElement('canvas');
        let width = img.width;
        let height = img.height;

        if (width > height) {
          if (width > maxWidth) {
            height *= maxWidth / width;
            width = maxWidth;
          }
        } else {
          if (height > maxWidth) {
            width *= maxWidth / height;
            height = maxWidth;
          }
        }

        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);

        canvas.toBlob((blob) => {
          if (blob) {
            // Convertir blob a File para mantener compatibilidad
            const compressedFile = new File([blob], file.name, {
              type: 'image/jpeg',
              lastModified: Date.now(),
            });
            resolve(compressedFile);
          } else {
            reject(new Error('Error al comprimir'));
          }
        }, 'image/jpeg', quality);
      };
      img.onerror = (e) => reject(e);
    };
    reader.onerror = (e) => reject(e);
  });
};

const onPhotoChange = async (event) => {
  let file = event.target.files?.[0];
  if (!file) return;

  const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  if (!validTypes.includes(file.type)) {
    Swal.fire({ icon: 'error', title: 'Formato no válido', text: 'Use JPG o PNG.' });
    event.target.value = '';
    return;
  }

  // Aumentar límite a 10MB
  if (file.size > 10 * 1024 * 1024) {
    Swal.fire({ icon: 'error', title: 'Archivo muy grande', text: 'Máximo 10MB.' });
    event.target.value = '';
    return;
  }

  const previousPreview = photoPreview.value;
  photoUploading.value = true;

  try {
    // Aplicar compresión si es mayor a 500KB o simplemente para optimizar
    if (file.size > 500 * 1024 || file.type === 'image/png') {
      try {
        file = await compressImage(file);
      } catch (err) {
        console.error("Fallo la compresión, se usará original", err);
      }
    }

    const objectUrl = URL.createObjectURL(file);
    photoPreview.value = objectUrl;
    photo.value = file;
    
    const ok = await handleUpdateEmployeeDocument(true);
    if (!ok) {
      photoPreview.value = previousPreview;
    }
    // No limpiamos photoPreview inmediatamente para evitar el parpadeo
    // URL.revokeObjectURL(objectUrl); // Se recomienda revocar después de cargar o al desmontar
  } catch (error) {
    photoPreview.value = previousPreview;
  } finally {
    photoUploading.value = false;
    event.target.value = '';
  }
};

// Métricas de Desempeño
const defaultPerformanceData = {
  salesMetrics: {
    historical: { totalAmount: 0, totalUnits: 0, ticketAverage: 0, unitsAverage: 0, totalOrders: 0, ordersWithSingleProduct: 0 },
    currentMonth: { totalAmount: 0, totalUnits: 0, ticketAverage: 0, unitsAverage: 0, totalOrders: 0, ordersWithSingleProduct: 0 },
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
  topProducts: [],
  topLaboratories: [],
  inventoryCounts: { total: 0, discrepancies: 0 },
};

const performanceData = ref(structuredClone(defaultPerformanceData));

const crossSellingRate = computed(() => {
  const salesData = performanceData.value.salesMetrics.currentMonth;
  if (!salesData.totalOrders || salesData.totalOrders === 0) return 0;
  const ordersWithSingleProduct = salesData.ordersWithSingleProduct || 0;
  const ordersWithMultipleProducts = salesData.totalOrders - ordersWithSingleProduct;
  return (Math.max(0, ordersWithMultipleProducts) / salesData.totalOrders) * 100;
});

// Nómina
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

const translatedRole = computed(() => {
  const roleMap = {
    admin: "Administrador",
    employee: "Empleado",
    supervisor: "Supervisor",
    hr: "Recursos Humanos",
  };
  const roleName = employee.value?.user?.role?.name;
  if (!roleName) return "N/A";
  const normalized = String(roleName).toLowerCase();
  return roleMap[normalized] ?? roleName;
});

const roleItems = computed(() =>
  roles.value.map((role) => ({
    title: role.name === 'Admin' ? 'Administrador' : role.name === 'Employee' ? 'Empleado' : role.name === 'Supervisor' ? 'Supervisor' : role.name === 'HR' || role.name === 'Hr' ? 'Recursos Humanos' : role.name,
    value: Number(role.id),
  }))
);

const fetchRoles = async () => {
  try {
    const response = await axios.get('roles');
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

const API_BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '');
const resolveImageUrl = (urlOrPath) => {
  if (!urlOrPath || typeof urlOrPath !== 'string') return null;
  const s = urlOrPath.trim();
  if (!s || s.toLowerCase() === 'null' || s === ' NULL') return null;
  if (s.startsWith('http://') || s.startsWith('https://')) return s;
  const path = s.startsWith('/') ? s : `/${s}`;
  return API_BASE_URL ? `${API_BASE_URL}${path}` : path;
};

const employeePhotoUrl = computed(() => {
  const emp = employee.value;
  if (!emp) return null;
  const url = resolveImageUrl(emp.photo_url) ?? resolveImageUrl(emp.photo ? `/storage/${emp.photo}` : null);
  return url ? `${url}?t=${Date.now()}` : null;
});

const avatarDisplaySrc = computed(() => {
  if (photoLoadFailed.value || !employeePhotoUrl.value) return defaultAvatarImg;
  return employeePhotoUrl.value;
});

const getEmployeeFromResponse = (response) => {
  const d = response?.data?.data;
  if (!d || typeof d !== 'object') return null;
  if (d.data && typeof d.data === 'object' && 'id' in d.data) return d.data;
  return d;
};

const fetchEmployee = async () => {
  try {
    loading.value = true;
    const response = await axios.get(`rrhh/employees/${route.params.id}`);
    const emp = getEmployeeFromResponse(response) ?? response?.data?.data;
    if (emp && emp.id) {
      employee.value = emp;
    }
  } catch (error) {
    employee.value = { id: route.params.id, name: 'Error', last_name: '', user: { role: {} } };
  } finally {
    loading.value = false;
  }
};

const fetchPerformanceData = async () => {
  try {
    const response = await axios.get(`rrhh/employees/${route.params.id}/performance`);
    // La API retorna ApiResponse::success que envuelve en data.data
    const data = response.data?.data;

    if (data) {
      performanceData.value = {
        ...performanceData.value,
        ...data,
        salesMetrics: {
          ...performanceData.value.salesMetrics,
          ...(data.salesMetrics || {}),
          historical: {
            ...performanceData.value.salesMetrics.historical,
            ...(data.salesMetrics?.historical || {}),
          },
          currentMonth: {
            ...performanceData.value.salesMetrics.currentMonth,
            ...(data.salesMetrics?.currentMonth || {}),
          },
        },
        // La API retorna data.rankings.topProductsByUnits y data.rankings.topLabsByUnits
        topProducts: data.rankings?.topProductsByUnits ?? data.topProducts ?? [],
        topLaboratories: data.rankings?.topLabsByUnits ?? data.topLaboratories ?? [],
      };
    }
  } catch (error) {
    performanceData.value = structuredClone(defaultPerformanceData);
  }
};

const handleReset2FA = async () => {
  const result = await Swal.fire({
    title: "¿Reiniciar 2FA?",
    text: "Esto obligará al empleado a configurar nuevamente el 2FA en su próximo acceso.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, reiniciar",
    cancelButtonText: "Cancelar",
  });
  if (!result.isConfirmed) return;
  try {
    await axios.put(`rrhh/employees/${employee.value.id}/reset-2fa`);
    toast.success("2FA reiniciado correctamente");
  } catch (error) {
    toast.error("Error al reiniciar 2FA");
  }
};

const saveProfileChanges = async () => {
  try {
    loading.value = true;
    const roleId = employee.value.user?.role_id ?? employee.value.user?.role?.id;
    await axios.put(`rrhh/employees/${employee.value.id}`, {
      name: employee.value.name,
      last_name: employee.value.last_name,
      identification: employee.value.identification,
      email: employee.value.email || employee.value.user?.email,
      role: roleId,
    });
    toast.success("Perfil actualizado");
    isEditing.value = false;
    await fetchEmployee();
  } catch (error) {
    toast.error("Error al actualizar perfil");
  } finally {
    loading.value = false;
  }
};

const formatCurrency = (value) => {
  const n = Number(value);
  return Number.isFinite(n) ? n.toLocaleString('es-VE', { style: 'currency', currency: 'USD' }) : '—';
};

const fetchPayments = async () => {
  if (!employee.value?.id) return;
  payrollLoading.value = true;
  try {
    const now = new Date();
    const params = { month: now.getMonth() + 1, year: now.getFullYear() };
    const { data } = await axios.get(`rrhh/employees/${employee.value.id}/payments`, { params });
    const res = data?.data;
    paymentHistory.value = res.history ?? [];
    payrollEmployee.value = res.employee ?? { total_package_usd: null, saldo_deuda: 0 };
    distribution.value = res.distribution ?? null;
    if (paymentForm.value.total_package_usd == null) paymentForm.value.total_package_usd = Number(res.employee?.total_package_usd);
  } catch {
    paymentHistory.value = [];
  } finally {
    payrollLoading.value = false;
  }
};

const savePackage = async () => {
  const pkg = paymentForm.value.total_package_usd;
  if (!pkg) return;
  savingPackage.value = true;
  try {
    await axios.put(`rrhh/employees/${employee.value.id}/payroll-settings`, { total_package_usd: Number(pkg) });
    toast.success("Paquete actualizado");
    fetchPayments();
  } catch {
    toast.error("Error al guardar paquete");
  } finally {
    savingPackage.value = false;
  }
};

const handleRefreshTable = async () => {
  await fetchEmployee();
};

const handleDownloadFile = async (file) => {
  try {
    const response = await axios.get(`rrhh/employees/${employee.value.id}/download/${file}`, { responseType: "blob" });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `${file}-employee-${employee.value.id}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
  } catch (error) {
    toast.error("No se pudo descargar el archivo");
  }
};

const handleUpdateEmployeeDocument = async (photoOnly = false) => {
  const formData = new FormData();
  if (photo.value) formData.append('photo', photo.value);
  if (residenceLetter.value) formData.append('residence_letter', residenceLetter.value);
  if (rif.value) formData.append('rif', rif.value);
  if (cv.value) formData.append('cv', cv.value);

  try {
    photoUploading.value = true;
    await axios.post(`rrhh/employees/${employee.value.id}/documents`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    toast.success(photoOnly ? "Foto actualizada" : "Documentos actualizados");
    await fetchEmployee();
    return true;
  } catch (error) {
    toast.error("Error al actualizar");
    return false;
  } finally {
    photoUploading.value = false;
  }
};

onMounted(() => {
  fetchEmployee();
  fetchRoles();
  fetchPerformanceData();
});

watch(activeView, (view) => {
  if (view === 'salary') fetchPayments();
});
</script>

<template>
  <div class="employee-profile-page">
    <EmployeeFormDialog
      v-model="showEditDialog"
      :roles="roles"
      :selectedEmployee="employee"
      :clear-data-on-close="false"
      @refresh-table="handleRefreshTable"
    />

    <VRow>
      <!-- Sidebar de Perfil (Sticky) -->
      <VCol cols="12" md="3">
        <VCard class="rounded-lg border-0 shadow-sm sticky-sidebar overflow-visible mb-6">
          <!-- Header con Gradiente Premium -->
          <div class="header-gradient rounded-t-lg pa-6 d-flex flex-column align-center position-relative">
            <div class="position-relative mb-4">
              <VAvatar size="100" class="elevation-12 rounded-lg photo-avatar" border="3px solid white">
                <img v-if="photoPreview || employeePhotoUrl" :src="photoPreview || avatarDisplaySrc" @error="photoLoadFailed = true" />
                <span v-else class="text-h4 font-weight-black text-white">{{ employeeInitials }}</span>
                
                <div v-if="photoUploading" class="upload-overlay d-flex flex-column align-center justify-center">
                  <VProgressCircular indeterminate color="white" size="24" width="3" />
                </div>
              </VAvatar>
              <VBtn
                v-if="isAdmin && !photoUploading"
                icon="tabler-camera"
                color="primary"
                size="34"
                class="avatar-camera-btn elevation-4"
                @click="triggerPhotoInput"
              />
              <input ref="photoInput" type="file" accept="image/*" class="d-none" @change="onPhotoChange" />
            </div>

            <div class="text-center">
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-1 uppercase tracking-tight">
                {{ employee.name }} {{ employee.last_name }}
              </h2>
              <VChip size="x-small" color="white" variant="flat" class="font-weight-black text-primary px-3 rounded">
                {{ translatedRole }}
              </VChip>
            </div>
          </div>

          <VCardText class="pa-5 bg-surface">
            <!-- Navegación de Vistas -->
            <VList class="premium-nav-list pa-0 mb-6">
              <VListItem
                :active="activeView === 'performance'"
                prepend-icon="tabler-chart-bar"
                title="DESEMPEÑO"
                @click="activeView = 'performance'"
                class="rounded-lg mb-2"
                color="primary"
                variant="tonal"
              />
              <VListItem
                :active="activeView === 'salary'"
                prepend-icon="tabler-wallet"
                title="GESTIÓN SALARIAL"
                @click="activeView = 'salary'"
                class="rounded-lg"
                color="primary"
                variant="tonal"
              />
            </VList>

            <VDivider class="border-dashed mb-6 opacity-30" />

            <!-- Información Básica -->
            <div class="d-flex flex-column gap-5">
              <div class="info-group">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Identificación</span>
                <div v-if="!isEditing" class="text-xs font-weight-black text-high-emphasis tracking-wide tabular-nums">
                  {{ employee.identification || '—' }}
                </div>
                <VTextField v-else v-model="employee.identification" density="compact" hide-details class="premium-input-compact" />
              </div>

              <div class="info-group">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Correo Electrónico</span>
                <div v-if="!isEditing" class="text-xs font-weight-black text-high-emphasis lowercase">
                  {{ employee.user?.email || employee.email || '—' }}
                </div>
                <VTextField v-else v-model="employee.email" density="compact" hide-details class="premium-input-compact" />
              </div>
            </div>

            <!-- Botones de Acción Global -->
            <div class="mt-8 d-flex flex-column gap-3">
              <VBtn
                v-if="isAdmin"
                block
                :color="isEditing ? 'success' : 'primary'"
                variant="tonal"
                size="large"
                class="rounded-lg font-weight-black text-button"
                :loading="loading"
                @click="isEditing ? saveProfileChanges() : isEditing = true"
              >
                <VIcon start :icon="isEditing ? 'tabler-check' : 'tabler-pencil'" size="18" />
                {{ isEditing ? 'GUARDAR' : 'EDITAR PERFIL' }}
              </VBtn>

              <VBtn v-if="isAdmin && isEditing" block color="secondary" variant="tonal" class="rounded-lg" @click="isEditing = false">
                CANCELAR
              </VBtn>

              <VBtn v-if="isAdmin && !isEditing" block color="secondary" variant="tonal" class="rounded-lg" @click="handleReset2FA">
                <VIcon start icon="tabler-refresh" size="18" />
                REINICIAR 2FA
              </VBtn>

              <VBtn 
                v-if="isAdmin && !isEditing" 
                block 
                color="secondary" 
                variant="text" 
                size="small" 
                class="rounded-lg mt-2 font-weight-bold" 
                @click="showEditDialog = true"
              >
                Configuración Avanzada
                <VIcon end icon="tabler-chevron-right" size="14" />
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <!-- Sección de Documentos -->
        <VCard class="rounded-lg border-1 shadow-sm overflow-hidden">
          <div class="pa-4 bg-light font-weight-black text-super-xs text-primary uppercase letter-spacing-1 border-b d-flex align-center">
            <VIcon icon="tabler-files" size="16" class="me-2" />
            Documentación Digital
          </div>
          <VCardText class="pa-4 d-flex flex-column gap-2">
            <div v-for="(file, label) in { residence_letter: 'Cédula / Rif', cv: 'Currículum Vitae' }" :key="file" class="document-item rounded-lg border pa-3 d-flex align-center justify-space-between transition-all">
              <div class="d-flex align-center gap-3">
                <VAvatar color="primary" variant="tonal" size="32" class="rounded">
                  <VIcon icon="tabler-file-type-pdf" size="18" />
                </VAvatar>
                <span class="text-xs font-weight-bold text-high-emphasis">{{ label }}</span>
              </div>
              <VBtn 
                v-if="employee[file]" 
                icon="tabler-download" 
                size="28" 
                variant="tonal" 
                color="primary" 
                class="rounded" 
                @click="handleDownloadFile(file)"
              />
              <VBtn icon="tabler-upload" size="28" variant="tonal" color="secondary" class="rounded" v-else />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Área de Contenido Principal -->
      <VCol cols="12" md="9">
        <VTabsWindow v-model="activeView">
          <!-- DASHBOARD DE DESEMPEÑO -->
          <VTabsWindowItem value="performance">
            <div class="d-flex align-center justify-space-between mb-6">
              <h2 class="text-h5 font-weight-black text-high-emphasis tracking-tight uppercase">Dashboard Operativo</h2>
              <VChip color="primary" variant="flat" class="font-weight-black px-4">MES ACTUAL</VChip>
            </div>

            <!-- KPIs Principales -->
            <VRow class="mb-4">
              <VCol v-for="kpi in [
                { label: 'VENTAS USD', value: performanceData.salesMetrics.currentMonth.totalAmount, icon: 'tabler-cash', color: 'primary', format: 'currency' },
                { label: 'UNIDADES', value: performanceData.salesMetrics.currentMonth.totalUnits, icon: 'tabler-package', color: 'success', format: 'number' },
                { label: 'TICKET PROM', value: performanceData.salesMetrics.currentMonth.ticketAverage, icon: 'tabler-receipt', color: 'warning', format: 'currency' },
                { label: 'CROSS-SELLING', value: crossSellingRate, icon: 'tabler-trending-up', color: 'info', format: 'percent' }
              ]" :key="kpi.label" cols="12" sm="6" lg="3">
                <VCard class="rounded-lg border-0 shadow-sm kpi-card overflow-hidden h-100">
                  <div :class="`kpi-glow bg-${kpi.color}`" />
                  <VCardText class="pa-5">
                    <div class="d-flex justify-space-between align-start mb-4">
                      <VAvatar :color="kpi.color" variant="tonal" size="44" class="rounded-lg">
                        <VIcon :icon="kpi.icon" size="24" />
                      </VAvatar>
                    </div>
                    <div class="text-super-xs font-weight-black text-disabled uppercase mb-1">{{ kpi.label }}</div>
                    <div class="text-h5 font-weight-black text-high-emphasis tabular-nums leading-none">
                      {{ kpi.format === 'currency' ? formatCurrency(kpi.value) : kpi.format === 'percent' ? kpi.value.toFixed(1) + '%' : kpi.value.toLocaleString() }}
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>

            <!-- Comparativo Histórico (Estilo Compacto) -->
            <VCard class="rounded-lg border-0 shadow-sm mb-8">
              <div class="pa-4 bg-light border-b font-weight-black text-super-xs text-primary uppercase letter-spacing-1">
                Acumulado Histórico
              </div>
              <VCardText class="pa-0">
                <VRow no-gutters>
                  <VCol v-for="(metric, idx) in [
                    { label: 'Ventas Totales', value: formatCurrency(performanceData.salesMetrics.historical.totalAmount) },
                    { label: 'Unidades Totales', value: performanceData.salesMetrics.historical.totalUnits.toLocaleString() },
                    { label: 'Ticket Prom. Hist', value: formatCurrency(performanceData.salesMetrics.historical.ticketAverage) },
                    { label: 'Unds. Prom. Hist', value: performanceData.salesMetrics.historical.unitsAverage.toFixed(1) }
                  ]" :key="idx" cols="6" md="3">
                    <div class="pa-4 text-center" :class="idx < 3 ? 'border-r-sm' : ''">
                      <div class="text-super-xs font-weight-black text-disabled uppercase mb-1">{{ metric.label }}</div>
                      <div class="text-subtitle-1 font-weight-black text-high-emphasis tabular-nums">{{ metric.value }}</div>
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>

            <!-- Rankings y Detalles -->
            <VRow>
              <VCol cols="12" lg="6">
                <VCard class="rounded-lg border-0 shadow-sm h-100 overflow-hidden">
                  <div class="pa-4 bg-light border-b d-flex align-center justify-space-between">
                    <span class="font-weight-black text-super-xs text-primary uppercase letter-spacing-1">Top 10 Productos (Unidades)</span>
                    <VIcon icon="tabler-crown" size="18" color="warning" />
                  </div>
                  <VCardText class="pa-2">
                    <VList density="compact" class="ranking-list">
                      <VListItem v-for="(product, i) in performanceData.topProducts.slice(0, 10)" :key="i" class="rounded-lg mb-1">
                        <template #prepend>
                          <div :class="`rank-number rank-${i+1} font-weight-black text-xs me-3`">{{ i + 1 }}</div>
                        </template>
                        <VListItemTitle class="text-xs font-weight-black text-high-emphasis uppercase">{{ product.name || product.product_name }}</VListItemTitle>
                        <template #append>
                          <div class="text-right d-flex flex-column align-end">
                            <span class="text-xs font-weight-black text-primary">{{ product.units || product.total_units }} unds</span>
                            <span class="text-super-xs text-disabled">{{ formatCurrency(product.amount || product.total_amount) }}</span>
                          </div>
                        </template>
                      </VListItem>
                    </VList>
                  </VCardText>
                </VCard>
              </VCol>

              <VCol cols="12" lg="6">
                <VCard class="rounded-xl border-0 shadow-sm h-100 overflow-hidden">
                  <div class="pa-4 bg-light border-b d-flex align-center justify-space-between">
                    <span class="font-weight-black text-super-xs text-primary uppercase letter-spacing-1">Top 5 Laboratorios</span>
                    <VIcon icon="tabler-flask" size="18" color="info" />
                  </div>
                  <VCardText class="pa-4 text-center d-flex flex-column gap-3">
                    <div v-for="(lab, i) in performanceData.topLaboratories.slice(0, 5)" :key="i" class="lab-row rounded-lg pa-3 border d-flex align-center justify-space-between shadow-xs transition-all">
                      <div class="d-flex align-center gap-3">
                         <VAvatar :color="i < 2 ? 'primary' : 'secondary'" variant="tonal" size="32" class="rounded font-weight-black text-xs">
                           {{ i + 1 }}
                         </VAvatar>
                         <span class="text-xs font-weight-black text-high-emphasis uppercase">{{ lab.name || lab.laboratory }}</span>
                      </div>
                      <div class="text-right">
                        <div class="text-xs font-weight-black text-primary">{{ lab.units || lab.total_units }} unds</div>
                      </div>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VTabsWindowItem>

          <!-- GESTIÓN SALARIAL -->
          <VTabsWindowItem value="salary">
             <div class="d-flex align-center justify-space-between mb-6">
              <h2 class="text-h5 font-weight-black text-high-emphasis tracking-tight uppercase">Nómina e Incentivos</h2>
              <VChip color="success" variant="flat" class="font-weight-black px-4">ACTIVO</VChip>
            </div>

            <VCard class="rounded-lg border-0 shadow-sm mb-6 overflow-hidden">
               <div class="pa-5 header-gradient d-flex align-center">
                  <VAvatar color="white" variant="flat" size="48" class="me-4 rounded-lg shadow-sm">
                    <VIcon icon="tabler-piggy-bank" color="primary" size="24" />
                  </VAvatar>
                  <div class="flex-grow-1">
                    <div class="text-super-xs text-white opacity-70 font-weight-black uppercase letter-spacing-1 mb-1">Paquete Mensual Acordado</div>
                    <div class="text-h4 font-weight-black text-white tabular-nums leading-none">{{ formatCurrency(paymentForm.total_package_usd) }}</div>
                  </div>
                  <VBtn v-if="isAdmin" color="white" variant="flat" class="font-weight-black text-primary rounded-lg px-6" :loading="savingPackage" @click="savePackage">ACTUALIZAR</VBtn>
               </div>
               
               <VCardText class="pa-6">
                  <div class="max-600 mx-auto">
                    <div class="text-xs font-weight-bold text-medium-emphasis mb-6 text-center italic">
                      "La distribución de conceptos se calcula según la ley vigente y las políticas de la farmacia."
                    </div>
                    
                    <div v-if="distribution" class="premium-invoice rounded-lg pa-8 border-1 shadow-sm position-relative overflow-hidden">
                      <div class="invoice-watermark">PAYROLL</div>
                      <div class="d-flex justify-space-between align-center mb-8">
                        <span class="text-h6 font-weight-black text-primary uppercase">Detalle de Cobro</span>
                        <VIcon icon="tabler-file-invoice" size="32" class="text-primary opacity-20" />
                      </div>

                      <div class="concept-list">
                        <div v-for="c in distribution.concepts" :key="c.name" class="d-flex justify-space-between py-3 border-dashed-b last:border-0">
                          <span class="text-xs font-weight-black text-high-emphasis uppercase">{{ c.name }}</span>
                          <span class="text-xs font-weight-black text-high-emphasis tabular-nums">{{ formatCurrency(c.amount) }}</span>
                        </div>
                      </div>

                      <VDivider class="my-6 border-solid opacity-10" />

                      <div class="d-flex justify-space-between align-center">
                        <div class="d-flex flex-column">
                          <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Neto Estimado</span>
                          <span class="text-h4 font-weight-black text-primary tabular-nums">{{ formatCurrency(distribution.total_a_cobrar) }}</span>
                        </div>
                        <VIcon icon="tabler-currency-dollar" size="48" class="text-primary opacity-10" />
                      </div>
                    </div>
                  </div>
               </VCardText>
            </VCard>

            <!-- Historial -->
            <VCard class="rounded-lg border-0 shadow-sm overflow-hidden mt-8">
              <div class="pa-4 bg-light border-b font-weight-black text-super-xs text-primary uppercase letter-spacing-1">
                Historial de Pagos Procesados
              </div>
              <VDataTableServer
                :items="paymentHistory"
                :headers="[
                  { title: 'PERIODO', key: 'fecha' },
                  { title: 'NETO (USD)', key: 'total_pagado_usd', align: 'end' },
                  { title: 'EQUIVALENTE (VES)', key: 'total_pagado_ves', align: 'end' }
                ]"
                class="premium-table"
                hide-default-footer
              >
                <template #item.fecha="{ item }">
                  <div class="d-flex align-center gap-3 py-2">
                    <VAvatar color="primary" variant="tonal" size="32" class="rounded-lg font-weight-black text-super-xs">
                      {{ new Date(item.fecha).getMonth() + 1 }}
                    </VAvatar>
                    <span class="text-xs font-weight-black uppercase">{{ new Date(item.fecha).toLocaleString('es-VE', { month: 'long', year: 'numeric' }) }}</span>
                  </div>
                </template>
                <template #item.total_pagado_usd="{ item }">
                  <span class="text-xs font-weight-black text-primary tabular-nums">{{ formatCurrency(item.total_pagado_usd) }}</span>
                </template>
                <template #item.total_pagado_ves="{ item }">
                  <span class="text-xs font-weight-bold text-medium-emphasis tabular-nums">{{ item.total_pagado_ves.toLocaleString('es-VE') }} Bs</span>
                </template>
              </VDataTableServer>
            </VCard>
          </VTabsWindowItem>
        </VTabsWindow>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.sticky-sidebar {
  position: sticky;
  inset-block-start: 1rem;
}

.photo-avatar {
  border: 4px solid rgba(255, 255, 255, 40%);
  transition: all 0.3s ease;
}

.avatar-camera-btn {
  position: absolute;
  border-radius: 50% !important;
  inset-block-end: 0;
  inset-inline-end: -6px;
}

.upload-overlay {
  position: absolute;
  z-index: 2;
  inset: 0;
  background: rgba(0, 0, 0, 60%);
}

.premium-nav-list :deep(.v-list-item--active) {
  border: 1px solid rgba(var(--v-theme-primary), 20%);
  background-color: rgba(var(--v-theme-primary), 10%) !important;
}

.premium-input-compact :deep(.v-field) {
  background-color: white !important;
  border-radius: 8px !important;
  min-block-size: 34px !important;
  font-size: 0.75rem !important;
}

.bg-light { background-color: #f8fafc !important; }

.kpi-card {
  position: relative;
  transition: transform 0.3s ease;
}

.kpi-card:hover {
  transform: translateY(-4px);
}

.kpi-glow {
  position: absolute;
  inset-block-start: 0;
  inset-inline-start: 0;
  block-size: 4px;
  inline-size: 100%;
  opacity: 0.8;
}

.premium-invoice {
  border: 1px solid rgba(var(--v-theme-primary), 10%);
  background: white;
}

.invoice-watermark {
  position: absolute;
  color: rgba(var(--v-theme-primary), 3%);
  inset-block-end: -10px;
  inset-inline-end: 10px;
  font-size: 5rem;
  font-weight: 900;
  pointer-events: none;
  transform: rotate(-5deg);
}

.rank-number {
  display: flex;
  align-items: center;
  justify-content: center;
  block-size: 24px;
  inline-size: 24px;
  border-radius: 50%;
  background-color: #f1f5f9;
  color: #64748b;
}

.rank-1 { background-color: #fef3c7; color: #d97706; }
.rank-2 { background-color: #f1f5f9; color: #475569; }
.rank-3 { background-color: #ffedd5; color: #9a3412; }

.lab-row:hover {
  border-color: rgba(var(--v-theme-primary), 20%) !important;
  background-color: #f8fafc;
}

.shadow-xs { box-shadow: 0 1px 3px rgba(0, 0, 0, 5%) !important; }
.shadow-sm { box-shadow: 0 4px 12px rgba(0, 0, 0, 5%) !important; }

.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }
.tracking-tight { letter-spacing: -0.5px !important; }
.tabular-nums { font-variant-numeric: tabular-nums; }
.text-super-xs { font-size: 0.65rem !important; }
.uppercase { text-transform: uppercase !important; }

.border-dashed-b { border-block-end: 1px dashed rgba(0, 0, 0, 8%) !important; }
.border-dashed { border-width: 2px !important; border-style: dashed !important; }

.document-item:hover {
  border-color: rgb(var(--v-theme-primary)) !important;
  background-color: rgba(var(--v-theme-primary), 2%);
}

.transition-all { transition: all 0.2s ease; }

@media (max-width: 960px) {
  .sticky-sidebar { position: static; }
}
</style>
