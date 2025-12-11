<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, reactive, ref } from "vue";

const balanceData = reactive({
  activo: {
    efectivo: 0,
    inventario: 0,
    mobiliario: 0,
  },
  pasivo: {
    deudasProveedores: 0,
    prestamos: 0,
    depreciacion: 0,
  },
});

const loading = ref(false);
const cashData = ref({});
const inventoryData = ref({});
const supplierDebtsData = ref({});
const furnitureData = ref({});
const loansData = ref({});
const depreciationData = ref({});

const totalActivo = computed(() => {
  return (
    balanceData.activo.efectivo +
    balanceData.activo.inventario +
    balanceData.activo.mobiliario
  );
});

const totalPasivo = computed(() => {
  return (
    balanceData.pasivo.deudasProveedores +
    balanceData.pasivo.prestamos +
    balanceData.pasivo.depreciacion
  );
});

const patrimonioNeto = computed(() => {
  return totalActivo.value - totalPasivo.value;
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(amount);
};

const fetchCashData = async () => {
  try {
    const { data } = await axios.get("/finances/transactions/stats");
    cashData.value = data.data;
    balanceData.activo.efectivo = cashData.value.total_value || 0;
  } catch (error) {
    console.error("Error al cargar datos de efectivo:", error);
    toast.error("No se pudieron cargar los datos de efectivo.");
  }
};

const fetchInventoryData = async () => {
  try {
    const { data } = await axios.get("/products/inventory/value");
    inventoryData.value = data.data;
    balanceData.activo.inventario = inventoryData.value.total_value || 0;
  } catch (error) {
    console.error("Error al cargar datos de inventario:", error);
    toast.error("No se pudieron cargar los datos de inventario.");
  }
};

const fetchFurnitureData = async () => {
  try {
    const { data } = await axios.get("/furniture/value");
    furnitureData.value = data.data;
    balanceData.activo.mobiliario = furnitureData.value.total_value || 0;
  } catch (error) {
    console.error("Error al cargar datos de mobiliario:", error);
    toast.error("No se pudieron cargar los datos de mobiliario.");
  }
};

const fetchSupplierDebtsData = async () => {
  try {
    const { data } = await axios.get("/invoices/supplier/debts");
    supplierDebtsData.value = data.data;
    balanceData.pasivo.deudasProveedores =
      supplierDebtsData.value.total_debts || 0;
  } catch (error) {
    console.error("Error al cargar datos de deudas con proveedores:", error);
    toast.error("No se pudieron cargar los datos de deudas con proveedores.");
  }
};

const fetchLoansData = async () => {
  try {
    const { data } = await axios.get("/loans/balance");
    loansData.value = data.data;
    balanceData.pasivo.prestamos = loansData.value.total_balance || 0;
  } catch (error) {
    console.error("Error al cargar datos de préstamos:", error);
    toast.error("No se pudieron cargar los datos de préstamos.");
  }
};

const fetchDepreciationData = async () => {
  try {
    const { data } = await axios.get("/furniture/depreciation");
    depreciationData.value = data.data;
    balanceData.pasivo.depreciacion =
      depreciationData.value.total_depreciation || 0;
  } catch (error) {
    console.error("Error al cargar datos de depreciación:", error);
    toast.error("No se pudieron cargar los datos de depreciación.");
  }
};

const fetchBalanceData = async () => {
  loading.value = true;
  try {
    await Promise.all([
      fetchCashData(),
      fetchInventoryData(),
      fetchFurnitureData(),
      fetchSupplierDebtsData(),
      fetchLoansData(),
      fetchDepreciationData(),
    ]);

    toast.success("Datos del balance actualizados");
  } catch (error) {
    console.error("Error al cargar datos del balance:", error);
    toast.error("No se pudieron cargar los datos del balance.");
  } finally {
    loading.value = false;
  }
};

const handleExport = async (format) => {
  toast.info(`Exportando balance a ${format}...`);
};

const handleRefresh = () => {
  fetchBalanceData();
};

onMounted(() => {
  fetchBalanceData();
});
</script>

<template>
  <div>
    <VRow>
      <!-- Card de Activos -->
      <VCol cols="12" md="6">
        <VCard class="h-100" elevation="2">
          <VCardTitle class="d-flex align-center bg-success text-white">
            <VIcon class="mr-2">mdi-trending-up</VIcon>
            Activos
            <VSpacer />
            <span class="text-h6">{{ formatCurrency(totalActivo) }}</span>
          </VCardTitle>

          <VCardText class="pa-0">
            <VList>
              <VListItem class="px-4 py-3">
                <template #prepend>
                  <VIcon color="green" class="mr-3">mdi-cash</VIcon>
                </template>
                <VListItemTitle class="font-weight-medium">
                  Efectivo
                  <VChip
                    v-if="cashData.total_value"
                    color="success"
                    variant="outlined"
                    size="x-small"
                    class="ml-2"
                  >
                    Real-time
                  </VChip>
                </VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  Datos en tiempo real del flujo de caja
                </VListItemSubtitle>
                <template #append>
                  <VChip color="success" variant="flat" size="small">
                    {{ formatCurrency(balanceData.activo.efectivo) }}
                  </VChip>
                </template>
              </VListItem>

              <VDivider />

              <VListItem class="px-4 py-3">
                <template #prepend>
                  <VIcon color="blue" class="mr-3">mdi-package-variant</VIcon>
                </template>
                <VListItemTitle class="font-weight-medium">
                  Inventario
                  <VChip
                    v-if="inventoryData.total_value"
                    color="info"
                    variant="outlined"
                    size="x-small"
                    class="ml-2"
                  >
                    Real-time
                  </VChip>
                </VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  Stock × Costo unitario de todos los productos
                </VListItemSubtitle>
                <template #append>
                  <VChip color="info" variant="flat" size="small">
                    {{ formatCurrency(balanceData.activo.inventario) }}
                  </VChip>
                </template>
              </VListItem>

              <VDivider />

              <VListItem class="px-4 py-3">
                <template #prepend>
                  <VIcon color="orange" class="mr-3">mdi-sofa</VIcon>
                </template>
                <VListItemTitle class="font-weight-medium">
                  Mobiliario
                  <VChip
                    v-if="furnitureData.total_value"
                    color="warning"
                    variant="outlined"
                    size="x-small"
                    class="ml-2"
                  >
                    Real-time
                  </VChip>
                </VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  Valor actual con depreciación aplicada
                </VListItemSubtitle>
                <template #append>
                  <VChip color="warning" variant="flat" size="small">
                    {{ formatCurrency(balanceData.activo.mobiliario) }}
                  </VChip>
                </template>
              </VListItem>
            </VList>
          </VCardText>

          <VCardActions class="px-4 py-3 bg-grey-lighten-5">
            <VIcon color="success" size="small">mdi-plus-circle</VIcon>
            <span class="text-body-2 text-medium-emphasis ml-1">
              Total de activos disponibles
            </span>
          </VCardActions>
        </VCard>
      </VCol>

      <!-- Card de Pasivos -->
      <VCol cols="12" md="6">
        <VCard class="h-100" elevation="2">
          <VCardTitle class="d-flex align-center bg-error text-white">
            <VIcon class="mr-2">mdi-trending-down</VIcon>
            Pasivos
            <VSpacer />
            <span class="text-h6">{{ formatCurrency(totalPasivo) }}</span>
          </VCardTitle>

          <VCardText class="pa-0">
            <VList>
              <VListItem class="px-4 py-3">
                <template #prepend>
                  <VIcon color="red" class="mr-3">mdi-account-group</VIcon>
                </template>
                <VListItemTitle class="font-weight-medium">
                  Deudas con Proveedores
                  <VChip
                    v-if="supplierDebtsData.total_debts !== undefined"
                    color="error"
                    variant="outlined"
                    size="x-small"
                    class="ml-2"
                  >
                    Real-time
                  </VChip>
                </VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  Facturas pendientes de pago
                </VListItemSubtitle>
                <template #append>
                  <VChip color="error" variant="flat" size="small">
                    {{ formatCurrency(balanceData.pasivo.deudasProveedores) }}
                  </VChip>
                </template>
              </VListItem>

              <VDivider />

              <VListItem class="px-4 py-3">
                <template #prepend>
                  <VIcon color="purple" class="mr-3">mdi-bank</VIcon>
                </template>
                <VListItemTitle class="font-weight-medium">
                  Préstamos
                  <VChip
                    v-if="loansData.total_balance !== undefined"
                    color="secondary"
                    variant="outlined"
                    size="x-small"
                    class="ml-2"
                  >
                    Real-time
                  </VChip>
                </VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  Saldo pendiente de todos los préstamos
                </VListItemSubtitle>
                <template #append>
                  <VChip color="secondary" variant="flat" size="small">
                    {{ formatCurrency(balanceData.pasivo.prestamos) }}
                  </VChip>
                </template>
              </VListItem>

              <VDivider />

              <VListItem class="px-4 py-3">
                <template #prepend>
                  <VIcon color="orange" class="mr-3">mdi-trending-down-2</VIcon>
                </template>
                <VListItemTitle class="font-weight-medium">
                  Depreciación
                  <VChip
                    v-if="depreciationData.total_depreciation !== undefined"
                    color="warning"
                    variant="outlined"
                    size="x-small"
                    class="ml-2"
                  >
                    Real-time
                  </VChip>
                </VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  Valor total depreciado del mobiliario
                </VListItemSubtitle>
                <template #append>
                  <VChip color="warning" variant="flat" size="small">
                    {{ formatCurrency(balanceData.pasivo.depreciacion) }}
                  </VChip>
                </template>
              </VListItem>
            </VList>
          </VCardText>

          <VCardActions class="px-4 py-3 bg-grey-lighten-5">
            <VIcon color="error" size="small">mdi-minus-circle</VIcon>
            <span class="text-body-2 text-medium-emphasis ml-1">
              Total de obligaciones pendientes
            </span>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <!-- Resumen del Patrimonio Neto -->
    <VRow class="mt-4">
      <VCol cols="12">
        <VCard
          elevation="3"
          :color="patrimonioNeto >= 0 ? 'success' : 'error'"
          variant="tonal"
        >
          <VCardText class="d-flex align-center justify-center py-6">
            <div class="text-center">
              <VIcon
                :icon="
                  patrimonioNeto >= 0 ? 'mdi-trending-up' : 'mdi-trending-down'
                "
                size="48"
                :color="patrimonioNeto >= 0 ? 'success' : 'error'"
                class="mb-2"
              />
              <h3 class="text-h5 font-weight-bold mb-1">Patrimonio Neto</h3>
              <div class="text-h4 font-weight-bold">
                {{ formatCurrency(patrimonioNeto) }}
              </div>
              <p class="text-body-2 mt-2 opacity-80">
                {{
                  patrimonioNeto >= 0
                    ? "Situación financiera positiva"
                    : "Revisar obligaciones pendientes"
                }}
              </p>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Loading Overlay -->
    <VOverlay v-model="loading" class="align-center justify-center">
      <VProgressCircular color="primary" indeterminate size="64" />
    </VOverlay>
  </div>
</template>
