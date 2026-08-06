<script setup>
import EmployeeProfileHeader from "@/components/EmployeeProfileHeader.vue";
import EmployeePerformanceTab from "@/components/EmployeePerformanceTab.vue";
import EmployeePayrollTab from "@/components/EmployeePayrollTab.vue";
import EmployeeFormDialog from "@/components/dialogs/EmployeeFormDialog.vue";
import ImageCropperDialog from "@/components/dialogs/ImageCropperDialog.vue";
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
const authStore = useAuthStore();
const isAdmin = computed(() => authStore.isAdmin);
const isOwnProfile = computed(() => authStore.user?.employee?.id === Number(route.params.id) || authStore.user?.employee_id === Number(route.params.id));
const canEdit = computed(() => isAdmin.value || isOwnProfile.value);
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
const activeView = ref("performance");

const showCropper = ref(false);
const tempImageSource = ref('');
const originalFileName = ref('');

const isProfileCollapsed = ref(true);

const photo = ref(null);
const ci_file = ref(null);
const residence_letter = ref(null);
const rif = ref(null);
const cv = ref(null);

const documentLabels = {
  ci_file: 'Cédula de Identidad',
  rif: 'R.I.F.',
  residence_letter: 'Constancia de Residencia',
  cv: 'Currículum Vitae',
};

const docInputs = ref({});

const triggerPhotoInput = () => {
  if (canEdit.value) photoInput.value?.click();
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

  if (file.size > 10 * 1024 * 1024) {
    Swal.fire({ icon: 'error', title: 'Archivo muy grande', text: 'Máximo 10MB.' });
    event.target.value = '';
    return;
  }

  tempImageSource.value = URL.createObjectURL(file);
  originalFileName.value = file.name;
  showCropper.value = true;
  event.target.value = '';
};

const triggerDocInput = (type) => {
  docInputs.value[type]?.click();
};

const onDocChange = async (event, type) => {
  const file = event.target.files?.[0];
  if (!file) return;

  if (file.type !== 'application/pdf') {
    toast.error('Solo se permiten archivos PDF');
    event.target.value = '';
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    toast.error('El archivo no debe pesar más de 5MB');
    event.target.value = '';
    return;
  }

  const formData = new FormData();
  formData.append(type, file);

  try {
    photoUploading.value = true;
    await axios.post(`rrhh/employees/${employee.value.id}/documents`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    toast.success(`${documentLabels[type]} actualizado`);
    await fetchEmployee();
  } catch (error) {
    toast.error('Error al subir documento');
  } finally {
    photoUploading.value = false;
    event.target.value = '';
  }
};

const handleCroppedImage = async (blob) => {
  let file = new File([blob], originalFileName.value, { type: 'image/jpeg' });
  photoUploading.value = true;
  const previousPreview = photoPreview.value;

  try {
    const objectUrl = URL.createObjectURL(file);
    photoPreview.value = objectUrl;
    photo.value = file;
    const ok = await handleUpdateEmployeeDocument(true);
    if (!ok) photoPreview.value = previousPreview;
  } catch (error) {
    photoPreview.value = previousPreview;
  } finally {
    photoUploading.value = false;
    if (tempImageSource.value) {
      URL.revokeObjectURL(tempImageSource.value);
      tempImageSource.value = '';
    }
  }
};

const defaultPerformanceData = {
  salesMetrics: {
    historical: { totalAmount: 0, totalUnits: 0, ticketAverage: 0, unitsAverage: 0, totalOrders: 0, ordersWithSingleProduct: 0 },
    currentMonth: { totalAmount: 0, totalUnits: 0, ticketAverage: 0, unitsAverage: 0, totalOrders: 0, ordersWithSingleProduct: 0 },
  },
  profitabilityMetrics: {
    historical: { upsellRate: 0, avgOrderTime: 0, returnRate: 0 },
    currentMonth: { upsellRate: 0, avgOrderTime: 0, returnRate: 0 },
  },
  rankings: { topProductsByUnits: [], topProductsByAmount: [], topLabsByUnits: [], topLabsByAmount: [] },
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
  const roleMap = { admin: "Administrador", employee: "Empleado", supervisor: "Supervisor", hr: "Recursos Humanos" };
  const roleName = employee.value?.role?.name || employee.value?.user?.role?.name;
  if (!roleName) return "Empleado";
  return roleMap[String(roleName).toLowerCase()] ?? roleName;
});

const fetchRoles = async () => {
  try {
    const response = await axios.get('roles');
    roles.value = response.data?.data ?? [];
  } catch (error) {
    roles.value = [];
  }
};

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

const fetchEmployee = async () => {
  try {
    loading.value = true;
    const response = await axios.get(`rrhh/employees/${route.params.id}`);
    const emp = response?.data?.data;
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
    const data = response.data?.data;
    if (data) {
      performanceData.value = {
        ...performanceData.value,
        ...data,
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
  if (ci_file.value) formData.append('ci_file', ci_file.value);
  if (residence_letter.value) formData.append('residence_letter', residence_letter.value);
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
  <div class="employee-profile-page pb-12">
    <!-- Diálogos -->
    <EmployeeFormDialog
      v-model="showEditDialog"
      :roles="roles"
      :selectedEmployee="employee"
      :clear-data-on-close="false"
      @refresh-table="fetchEmployee"
    />

    <ImageCropperDialog
      v-model="showCropper"
      :image-source="tempImageSource"
      @confirm="handleCroppedImage"
    />

    <input ref="photoInput" type="file" accept="image/*" class="d-none" @change="onPhotoChange" />

    <input
      v-for="(vLabel, vField) in documentLabels"
      :key="vField"
      type="file"
      accept=".pdf"
      class="d-none"
      :ref="el => docInputs[vField] = el"
      @change="onDocChange($event, vField)"
    />

    <!-- Componente de Encabezado Principal Desacoplado -->
    <EmployeeProfileHeader
      :employee="employee"
      :can-edit="canEdit"
      :is-admin="isAdmin"
      :translated-role="translatedRole"
      :avatar-display-src="avatarDisplaySrc"
      :photo-uploading="photoUploading"
      :is-profile-collapsed="isProfileCollapsed"
      :mobile="mobile"
      :document-labels="documentLabels"
      @trigger-photo-input="triggerPhotoInput"
      @trigger-doc-input="triggerDocInput"
      @download-doc="handleDownloadFile"
      @open-edit="showEditDialog = true"
      @reset-2fa="handleReset2FA"
      @toggle-collapse="isProfileCollapsed = !isProfileCollapsed"
    />

    <!-- Selector de Pestañas Principales -->
    <VTabs v-model="activeView" color="primary" class="mb-6">
      <VTab value="performance">
        <VIcon icon="tabler-chart-bar" class="me-2" /> Desempeño Operativo
      </VTab>
      <VTab value="salary">
        <VIcon icon="tabler-wallet" class="me-2" /> Gestión Salarial y Nómina
      </VTab>
    </VTabs>

    <!-- Contenido de Vistas Desacopladas -->
    <VTabsWindow v-model="activeView">
      <VTabsWindowItem value="performance">
        <EmployeePerformanceTab
          :performance-data="performanceData"
          :cross-selling-rate="crossSellingRate"
          :mobile="mobile"
        />
      </VTabsWindowItem>

      <VTabsWindowItem value="salary">
        <EmployeePayrollTab
          :payment-history="paymentHistory"
          :payroll-employee="payrollEmployee"
          :distribution="distribution"
          :payment-form="paymentForm"
          :saving-package="savingPackage"
          :is-admin="isAdmin"
          :mobile="mobile"
          @save-package="savePackage"
        />
      </VTabsWindowItem>
    </VTabsWindow>
  </div>
</template>
