<script setup>
import axios from "@/plugins/axios";
import { onMounted, ref } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedStatus: [String, null],
  selectedEmployee: [Number, null],
  dateFrom: String,
  dateTo: String,
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedStatus",
  "update:selectedEmployee",
  "update:dateFrom",
  "update:dateTo",
  "clear",
  "sort",
]);

const employees = ref([]);
const loadingEmployees = ref(false);

// Estados disponibles
const statusOptions = [
  { title: "Procesada (Pendiente revisión)", value: "Procesada" },
  { title: "Completada (Aprobada)", value: "Completada" },
  { title: "Vencida", value: "Vencida" },
  { title: "Cancelada", value: "Cancelada" },
];

// Opciones de ordenamiento
const sortOptions = [
  {
    title: "Más Urgente (Procesadas primero)",
    icon: "tabler-clock-exclamation",
    key: "status",
    order: "asc",
  },
  {
    title: "Empleado A-Z",
    icon: "tabler-sort-ascending-letters",
    key: "employee_name",
    order: "asc",
  },
  {
    title: "Empleado Z-A",
    icon: "tabler-sort-descending-letters",
    key: "employee_name",
    order: "desc",
  },
  {
    title: "Actividad A-Z",
    icon: "tabler-sort-ascending",
    key: "activity_name",
    order: "asc",
  },
  {
    title: "Actividad Z-A",
    icon: "tabler-sort-descending",
    key: "activity_name",
    order: "desc",
  },
  {
    title: "Más Recientes",
    icon: "tabler-calendar-down",
    key: "completed_date",
    order: "desc",
  },
  {
    title: "Más Antiguas",
    icon: "tabler-calendar-up",
    key: "completed_date",
    order: "asc",
  },
];

const selectedSort = ref(null);

// Obtener empleados para el filtro
const fetchEmployees = async () => {
  loadingEmployees.value = true;
  try {
    const response = await axios.get("/rrhh/employees", {
      params: { itemsPerPage: 1000, active: true },
    });
    employees.value = response.data.data.map((emp) => ({
      title: `${emp.name} ${emp.last_name}`,
      value: emp.id,
    }));
  } catch (error) {
    console.error("Error al obtener empleados:", error);
  } finally {
    loadingEmployees.value = false;
  }
};

onMounted(() => {
  fetchEmployees();
});

const handleSortClick = (option) => {
  const sortFilter = { key: option.key, order: option.order };
  selectedSort.value = sortFilter;
  emit("sort", sortFilter);
};

const clearSortFilter = () => {
  selectedSort.value = null;
  emit("sort", { key: undefined, order: undefined });
};

const getSelectedSortTitle = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
  );
  return option ? option.title : null;
};

const getSelectedSortIcon = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
  );
  return option ? option.icon : null;
};

const isOptionSelected = (option) => {
  return (
    selectedSort.value &&
    selectedSort.value.key === option.key &&
    selectedSort.value.order === option.order
  );
};

const handleClear = () => {
  emit("clear");
  clearSortFilter();
};

const hasActiveFilters = () => {
  return (
    props.searchQuery ||
    props.selectedEmployee ||
    props.dateFrom ||
    props.dateTo ||
    (props.selectedStatus && props.selectedStatus !== "Procesada")
  );
};
</script>

<template>
  <VCard title="Revisión de Actividades de Limpieza" class="mb-6">
    <VCardText>
      <VRow>
        <!-- Búsqueda -->
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar empleado o actividad..."
            prepend-inner-icon="tabler-search"
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <!-- Filtro por Estado -->
        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.selectedStatus"
            :items="statusOptions"
            :loading="props.loading"
            label="Estado"
            placeholder="Filtrar por estado"
            prepend-inner-icon="tabler-flag"
            clearable
            @update:model-value="emit('update:selectedStatus', $event)"
          />
        </VCol>

        <!-- Filtro por Empleado -->
        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.selectedEmployee"
            :items="employees"
            :loading="loadingEmployees"
            label="Empleado"
            placeholder="Filtrar por empleado"
            prepend-inner-icon="tabler-user"
            clearable
            @update:model-value="emit('update:selectedEmployee', $event)"
          />
        </VCol>

        <!-- Fecha Desde -->
        <VCol cols="12" sm="6" md="6">
          <AppDateTimePicker
            :model-value="props.dateFrom"
            label="Fecha Desde"
            placeholder="Selecciona fecha inicial"
            prepend-inner-icon="tabler-calendar"
            clearable
            @update:model-value="emit('update:dateFrom', $event)"
          />
        </VCol>

        <!-- Fecha Hasta -->
        <VCol cols="12" sm="6" md="6">
          <AppDateTimePicker
            :model-value="props.dateTo"
            label="Fecha Hasta"
            placeholder="Selecciona fecha final"
            prepend-inner-icon="tabler-calendar"
            clearable
            @update:model-value="emit('update:dateTo', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <!-- Botón Limpiar Filtros -->
      <VBtn
        color="secondary"
        variant="outlined"
        prepend-icon="tabler-filter-off"
        @click="handleClear"
      >
        Limpiar Filtros
        <VBadge
          v-if="hasActiveFilters()"
          color="error"
          :content="
            [
              props.searchQuery,
              props.selectedEmployee,
              props.dateFrom,
              props.dateTo,
              props.selectedStatus !== 'Procesada'
                ? props.selectedStatus
                : null,
            ].filter(Boolean).length
          "
          inline
          class="ms-2"
        />
      </VBtn>

      <!-- Menú de Ordenamiento -->
      <div class="d-flex align-center gap-2">
        <VMenu>
          <template #activator="{ props: menuProps }">
            <VBtn v-bind="menuProps" variant="tonal">
              Ordenar Por
              <VIcon end icon="tabler-chevron-down" />
            </VBtn>
          </template>
          <VList>
            <VListItem
              v-for="(option, index) in sortOptions"
              :key="index"
              :class="{ 'bg-primary-lighten-5': isOptionSelected(option) }"
              @click="handleSortClick(option)"
            >
              <template #prepend>
                <VIcon :icon="option.icon" size="20" class="me-2" />
              </template>
              <VListItemTitle>{{ option.title }}</VListItemTitle>
              <template #append>
                <VIcon
                  v-if="isOptionSelected(option)"
                  icon="tabler-check"
                  size="16"
                  color="primary"
                />
              </template>
            </VListItem>
          </VList>
        </VMenu>

        <!-- Chip de ordenamiento activo -->
        <VChip
          v-if="selectedSort"
          color="primary"
          variant="tonal"
          size="small"
          closable
          @click:close="clearSortFilter"
        >
          <VIcon :icon="getSelectedSortIcon()" size="14" class="me-1" />
          {{ getSelectedSortTitle() }}
        </VChip>
      </div>

      <VSpacer />

      <!-- Indicador de filtros activos -->
      <div v-if="hasActiveFilters()" class="d-flex align-center gap-2">
        <VIcon icon="tabler-filter" size="20" color="primary" />
        <span class="text-sm text-primary">
          {{
            [
              props.searchQuery,
              props.selectedEmployee,
              props.dateFrom,
              props.dateTo,
              props.selectedStatus !== "Procesada"
                ? props.selectedStatus
                : null,
            ].filter(Boolean).length
          }}
          filtro(s) activo(s)
        </span>
      </div>
    </VCardActions>
  </VCard>
</template>
