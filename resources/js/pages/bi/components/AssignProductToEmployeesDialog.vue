<script setup>
import { ref, computed, watch } from 'vue';
import { useDisplay } from 'vuetify';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const { mobile } = useDisplay();
const loadingEmployees = ref(false);
const saving = ref(false);
const employees = ref([]);
const selectedEmployeeIds = ref([]);
const searchFilter = ref('');

const filteredEmployees = computed(() => {
  if (!searchFilter.value.trim()) return employees.value;
  const term = searchFilter.value.toLowerCase().trim();
  return employees.value.filter((emp) =>
    emp.name.toLowerCase().includes(term) ||
    (emp.identification && emp.identification.toLowerCase().includes(term))
  );
});

const isAllSelected = computed(() => {
  return (
    filteredEmployees.value.length > 0 &&
    filteredEmployees.value.every((emp) =>
      selectedEmployeeIds.value.includes(emp.id)
    )
  );
});

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    const idsToRemove = new Set(filteredEmployees.value.map((e) => e.id));
    selectedEmployeeIds.value = selectedEmployeeIds.value.filter(
      (id) => !idsToRemove.has(id)
    );
  } else {
    const newIds = new Set(selectedEmployeeIds.value);
    filteredEmployees.value.forEach((e) => newIds.add(e.id));
    selectedEmployeeIds.value = Array.from(newIds);
  }
};

const fetchEmployees = async () => {
  loadingEmployees.value = true;
  try {
    const response = await axios.get('/rrhh/employees', {
      params: { itemsPerPage: 1000, active: true },
    });

    const list = Array.isArray(response.data?.data) ? response.data.data : [];
    employees.value = list
      .filter((emp) => emp.role_id === 3 || emp.role?.id === 3 || emp.is_active)
      .map((emp) => ({
        id: emp.id,
        name: `${emp.name} ${emp.last_name}`.trim(),
        identification: emp.identification,
        photo_url: emp.photo_url,
      }));
  } catch (error) {
    console.error('Error al cargar empleados:', error);
    toast.error('No se pudo cargar la lista de vendedores.');
  } finally {
    loadingEmployees.value = false;
  }
};

const getInitials = (name) => {
  if (!name) return 'N/A';
  return name
    .trim()
    .split(/\s+/)
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .substring(0, 2);
};

const handleSave = async () => {
  if (!props.product?.id || selectedEmployeeIds.value.length === 0) {
    toast.error('Debes seleccionar al menos un vendedor.');
    return;
  }

  saving.value = true;
  try {
    await axios.post('/employee-products/assign-to-employees', {
      product_id: props.product.id,
      employee_ids: selectedEmployeeIds.value,
    });

    toast.success(
      `Producto asignado con éxito a ${selectedEmployeeIds.value.length} vendedor(es).`
    );
    emit('update:modelValue', false);
    emit('saved');
  } catch (error) {
    console.error('Error al asignar producto a vendedores:', error);
    toast.error(
      error.response?.data?.message || 'Error al asignar el producto a los vendedores.'
    );
  } finally {
    saving.value = false;
  }
};

const handleClose = () => {
  emit('update:modelValue', false);
};

watch(
  () => props.modelValue,
  async (isOpen) => {
    if (isOpen) {
      selectedEmployeeIds.value = [];
      searchFilter.value = '';
      if (employees.value.length === 0) {
        await fetchEmployees();
      }
    }
  }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="mobile ? undefined : '650px'"
    :fullscreen="mobile"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    @keydown.esc="handleClose"
  >
    <VCard class="rounded-xl border-0 shadow-xl overflow-hidden bg-surface d-flex flex-column">
      <!-- Header -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-user-plus" size="24" color="info" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Asignar Producto a Vendedores
            </h2>
            <span class="text-white opacity-75 uppercase font-weight-bold mt-1" style="font-size: 0.65rem;">
              Gestión Comercial y Productividad de Ventas
            </span>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            :disabled="saving"
            @click="handleClose"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light flex-grow-1 overflow-y-auto" style="max-height: 70vh;">
        <!-- Tarjeta del Producto Seleccionado -->
        <VCard v-if="props.product" variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm mb-4">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="tonal" size="44" class="rounded-lg flex-shrink-0">
              <VIcon icon="tabler-pill" size="24" />
            </VAvatar>
            <div class="flex-grow-1 min-width-0">
              <div class="d-flex align-center gap-2 mb-1">
                <span class="text-xs font-weight-black text-primary uppercase">#{{ props.product.id }}</span>
                <span class="text-disabled">|</span>
                <span class="text-xs font-weight-bold text-medium-emphasis uppercase text-truncate">
                  {{ props.product.laboratory_name || 'Sin Laboratorio' }}
                </span>
              </div>
              <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight text-truncate">
                {{ props.product.name }}
              </h3>
            </div>
          </div>

          <VDivider class="my-3 border-opacity-10" />

          <div class="d-flex align-center justify-space-between flex-wrap gap-2 text-super-xs font-weight-bold">
            <div>
              <span class="text-disabled uppercase me-1">Stock Actual:</span>
              <span class="text-high-emphasis font-weight-black">{{ props.product.current_stock }} unds</span>
            </div>
            <div>
              <span class="text-disabled uppercase me-1">Costo Unit.:</span>
              <span class="text-high-emphasis font-weight-black">{{ formatCurrency(props.product.last_cost) }}</span>
            </div>
            <div>
              <span class="text-disabled uppercase me-1">Capital Parado:</span>
              <span class="text-error font-weight-black">{{ formatCurrency(props.product.inventory_value) }}</span>
            </div>
          </div>
        </VCard>

        <!-- Buscador y selección rápida de empleados -->
        <div class="d-flex align-center justify-space-between gap-2 mb-3">
          <AppTextField
            v-model="searchFilter"
            placeholder="Buscar vendedor por nombre..."
            density="compact"
            variant="outlined"
            prepend-inner-icon="tabler-search"
            hide-details
            clearable
            class="flex-grow-1 bg-white"
          />
          <VBtn
            size="small"
            variant="tonal"
            :color="isAllSelected ? 'error' : 'primary'"
            class="font-weight-black rounded-lg text-uppercase"
            :disabled="filteredEmployees.length === 0"
            @click="toggleSelectAll"
          >
            {{ isAllSelected ? 'Desmarcar Todos' : 'Marcar Todos' }}
          </VBtn>
        </div>

        <!-- Lista de Vendedores -->
        <VCard variant="flat" class="border rounded-xl bg-white shadow-sm overflow-hidden">
          <div v-if="loadingEmployees" class="py-8 text-center">
            <VProgressCircular indeterminate color="primary" size="32" />
            <p class="text-caption text-disabled mt-2 font-weight-bold uppercase">Cargando lista de vendedores...</p>
          </div>

          <div v-else-if="filteredEmployees.length === 0" class="py-8 text-center text-medium-emphasis">
            <VIcon icon="tabler-user-off" size="40" class="mb-2 opacity-30" />
            <p class="text-caption font-weight-bold uppercase mb-0">No se encontraron vendedores</p>
          </div>

          <VList v-else density="compact" class="pa-0">
            <template v-for="(emp, index) in filteredEmployees" :key="emp.id">
              <VListItem
                class="px-4 py-2 cursor-pointer"
                @click="() => {
                  const idx = selectedEmployeeIds.indexOf(emp.id);
                  if (idx > -1) selectedEmployeeIds.splice(idx, 1);
                  else selectedEmployeeIds.push(emp.id);
                }"
              >
                <template #prepend>
                  <VCheckboxBtn
                    :model-value="selectedEmployeeIds.includes(emp.id)"
                    color="primary"
                    class="me-2"
                  />
                  <VAvatar color="primary" variant="tonal" size="32" class="rounded me-2">
                    <VImg v-if="emp.photo_url" :src="emp.photo_url" cover />
                    <span v-else class="text-super-xs font-weight-black">{{ getInitials(emp.name) }}</span>
                  </VAvatar>
                </template>

                <VListItemTitle class="text-xs font-weight-black text-uppercase">
                  {{ emp.name }}
                </VListItemTitle>
                <VListItemSubtitle class="text-super-xs text-disabled uppercase">
                  ID: #{{ emp.id }} • {{ emp.identification || 'SIN DNI' }}
                </VListItemSubtitle>
              </VListItem>
              <VDivider v-if="index < filteredEmployees.length - 1" class="border-opacity-10" />
            </template>
          </VList>
        </VCard>
      </VCardText>

      <VDivider />

      <!-- Footer / Acciones -->
      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow dense class="w-100 ma-0">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              height="48"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              :disabled="saving"
              @click="handleClose"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="info"
              variant="flat"
              height="48"
              block
              class="font-weight-black rounded-lg shadow-sm text-button uppercase"
              :disabled="selectedEmployeeIds.length === 0 || saving"
              :loading="saving"
              @click="handleSave"
            >
              <VIcon start icon="tabler-check" size="18" />
              Asignar ({{ selectedEmployeeIds.length }})
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-info)) 0%, rgb(var(--v-theme-primary)) 100%);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>