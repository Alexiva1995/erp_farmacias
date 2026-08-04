<script setup>
// Filtros Revisión de Limpieza (Supervisión)
import AppFilterBase from "@/components/AppFilterBase.vue";
import axios from "@/plugins/axios";
import { computed, onMounted, ref } from "vue";

const props = defineProps({
  searchQuery:      String,
  selectedStatus:   [String, null],
  selectedEmployee: [Number, null],
  dateFrom:         String,
  dateTo:           String,
  loading:          { type: Boolean, default: false },
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

const statusOptions = [
  { title: "Procesada (Pendiente revisión)", value: "Procesada"  },
  { title: "Completada (Aprobada)",          value: "Completada" },
  { title: "Vencida",                        value: "Vencida"    },
  { title: "Cancelada",                      value: "Cancelada"  },
];

const sortOptions = [
  { title: "Más Urgente (Procesadas)", icon: "tabler-clock-exclamation",       key: "status",          order: "asc"  },
  { title: "Empleado A-Z",             icon: "tabler-sort-ascending-letters",  key: "employee_name",   order: "asc"  },
  { title: "Empleado Z-A",             icon: "tabler-sort-descending-letters", key: "employee_name",   order: "desc" },
  { title: "Actividad A-Z",            icon: "tabler-sort-ascending",          key: "activity_name",   order: "asc"  },
  { title: "Actividad Z-A",            icon: "tabler-sort-descending",         key: "activity_name",   order: "desc" },
  { title: "Más Recientes",            icon: "tabler-calendar-down",           key: "completed_date",  order: "desc" },
  { title: "Más Antiguas",             icon: "tabler-calendar-up",             key: "completed_date",  order: "asc"  },
];

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

onMounted(() => fetchEmployees());

const hasAdvancedFilters = computed(() => {
  return !!(
    props.selectedEmployee ||
    props.dateFrom ||
    props.dateTo ||
    (props.selectedStatus && props.selectedStatus !== "Procesada")
  );
});
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    search-placeholder="Buscar empleado o actividad..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @sort="(sortFilter) => emit('sort', sortFilter)"
  >
    <template #advanced-filters>
      <!-- Filtro por Estado -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.selectedStatus"
          :items="statusOptions"
          :loading="props.loading"
          placeholder="Estado"
          prepend-inner-icon="tabler-flag"
          clearable
          density="compact"
          hide-details
          variant="outlined"
          @update:model-value="emit('update:selectedStatus', $event)"
        />
      </VCol>

      <!-- Filtro por Empleado -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.selectedEmployee"
          :items="employees"
          :loading="loadingEmployees"
          placeholder="Empleado"
          prepend-inner-icon="tabler-user"
          clearable
          density="compact"
          hide-details
          variant="outlined"
          @update:model-value="emit('update:selectedEmployee', $event)"
        />
      </VCol>

      <!-- Fecha Desde -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.dateFrom"
          placeholder="Fecha Desde"
          prepend-inner-icon="tabler-calendar"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:dateFrom', $event)"
        />
      </VCol>

      <!-- Fecha Hasta -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.dateTo"
          placeholder="Fecha Hasta"
          prepend-inner-icon="tabler-calendar"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:dateTo', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
