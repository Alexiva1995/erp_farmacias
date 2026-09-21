<script setup>
// Filtros de Historial Fiscal (Soporte para Modo Mensual y Modo Quincenal CE)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: { type: String, default: "" },
  startDate: { type: [String, null], default: null },
  endDate: { type: [String, null], default: null },
  loading: { type: Boolean, default: false },
  isCeEnabled: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "clear",
  "export",
  "sort",
]);

const sortOptions = [
  { title: "Precio Mayor", icon: "tabler-arrow-up", key: "total_amount", order: "desc" },
  { title: "Precio Menor", icon: "tabler-arrow-down", key: "total_amount", order: "asc" },
  { title: "Fecha Reciente", icon: "tabler-calendar-up", key: "invoice_date", order: "desc" },
  { title: "Fecha Antigua", icon: "tabler-calendar-down", key: "invoice_date", order: "asc" },
];

const hasAdvancedFilters = computed(() => !!(props.startDate || props.endDate));

// Utilidades para fechas del año en curso
const now = new Date();
const currentYear = now.getFullYear();
const currentMonthIndex = now.getMonth();
const currentDay = now.getDate();

const monthsOfYear = [
  { name: "Enero", abbr: "Ene", index: 0 },
  { name: "Febrero", abbr: "Feb", index: 1 },
  { name: "Marzo", abbr: "Mar", index: 2 },
  { name: "Abril", abbr: "Abr", index: 3 },
  { name: "Mayo", abbr: "May", index: 4 },
  { name: "Junio", abbr: "Jun", index: 5 },
  { name: "Julio", abbr: "Jul", index: 6 },
  { name: "Agosto", abbr: "Ago", index: 7 },
  { name: "Septiembre", abbr: "Sep", index: 8 },
  { name: "Octubre", abbr: "Oct", index: 9 },
  { name: "Noviembre", abbr: "Nov", index: 10 },
  { name: "Diciembre", abbr: "Dic", index: 11 },
];

const formatOffsetDate = (date) => {
  const d = new Date(date);
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().split("T")[0];
};

// --- MODO MENSUAL ---
const setSpecificMonth = (monthIndex) => {
  const start = new Date(currentYear, monthIndex, 1);
  const end = new Date(currentYear, monthIndex + 1, 0);

  emit("update:startDate", formatOffsetDate(start));
  emit("update:endDate", formatOffsetDate(end));
};

const isMonthActive = (monthIndex) => {
  if (!props.startDate || !props.endDate) return false;
  const start = formatOffsetDate(new Date(currentYear, monthIndex, 1));
  const end = formatOffsetDate(new Date(currentYear, monthIndex + 1, 0));
  return props.startDate === start && props.endDate === end;
};

const activeMonthLabel = computed(() => {
  for (const m of monthsOfYear) {
    if (isMonthActive(m.index)) {
      return `${m.name} ${currentYear}`;
    }
  }
  return null;
});

// --- MODO QUINCENAL (CE ACTIVADO) ---
const quincenasOfYear = computed(() => {
  const list = [];
  monthsOfYear.forEach((m) => {
    // 1ra Quincena: 01 al 15
    const q1Start = formatOffsetDate(new Date(currentYear, m.index, 1));
    const q1End = formatOffsetDate(new Date(currentYear, m.index, 15));
    list.push({
      key: `${m.index}-Q1`,
      title: `1ra Quincena ${m.name}`,
      shortTitle: `1Q ${m.abbr}`,
      monthName: m.name,
      monthAbbr: m.abbr,
      quincena: 1,
      rangeText: `01 al 15`,
      startDate: q1Start,
      endDate: q1End,
      isCurrent: m.index === currentMonthIndex && currentDay <= 15,
    });

    // 2da Quincena: 16 al último día
    const q2Start = formatOffsetDate(new Date(currentYear, m.index, 16));
    const q2End = formatOffsetDate(new Date(currentYear, m.index + 1, 0));
    list.push({
      key: `${m.index}-Q2`,
      title: `2da Quincena ${m.name}`,
      shortTitle: `2Q ${m.abbr}`,
      monthName: m.name,
      monthAbbr: m.abbr,
      quincena: 2,
      rangeText: `16 al fin`,
      startDate: q2Start,
      endDate: q2End,
      isCurrent: m.index === currentMonthIndex && currentDay > 15,
    });
  });
  return list;
});

const setQuincena = (q) => {
  emit("update:startDate", q.startDate);
  emit("update:endDate", q.endDate);
};

const isQuincenaActive = (q) => {
  return props.startDate === q.startDate && props.endDate === q.endDate;
};

const currentActiveQuincena = computed(() => {
  return quincenasOfYear.value.find((q) => isQuincenaActive(q));
});

const currentQuincenaObj = computed(() => {
  return quincenasOfYear.value.find((q) => q.isCurrent);
});

// --- ACCIONES GENERALES ---
const setDateHoy = () => {
  const t = new Date();
  const str = formatOffsetDate(t);
  emit("update:startDate", str);
  emit("update:endDate", str);
};

const setDateAnoCompleto = () => {
  emit("update:startDate", `${currentYear}-01-01`);
  emit("update:endDate", `${currentYear}-12-31`);
};
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-export="true"
    :loading="props.loading"
    search-placeholder="Buscar por ID, Razón Social, N° Factura..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @export="(fmt) => emit('export', fmt)"
    @sort="emit('sort', $event)"
  >
    <!-- Rango Rápido de Fechas (Mensual o Quincenal según CE) -->
    <template #search-extra>
      <div class="d-none d-lg-flex align-center gap-1 ms-2 border-s ps-2">
        <!-- CASO 1: MODO QUINCENAL (CE ACTIVADO) -->
        <template v-if="props.isCeEnabled">
          <VMenu transition="scale-transition">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                :disabled="props.loading"
                color="primary"
                variant="tonal"
                size="small"
                class="font-weight-bold me-1"
                prepend-icon="tabler-calendar-time"
                append-icon="tabler-chevron-down"
              >
                {{ currentActiveQuincena?.title || `Quincenas ${currentYear}` }}
              </VBtn>
            </template>
            <VList class="rounded-lg shadow-lg pa-1" min-width="240" max-height="360">
              <div class="px-3 py-1 text-super-xs font-weight-black text-uppercase text-disabled">
                Quincenas Fiscales {{ currentYear }} (CE)
              </div>
              <VListItem
                v-for="q in quincenasOfYear"
                :key="q.key"
                :active="isQuincenaActive(q)"
                class="rounded-md mb-1"
                @click="setQuincena(q)"
              >
                <template #prepend>
                  <VIcon
                    :icon="isQuincenaActive(q) ? 'tabler-circle-check' : 'tabler-calendar'"
                    size="18"
                    :color="isQuincenaActive(q) ? 'primary' : 'disabled'"
                    class="me-2"
                  />
                </template>
                <VListItemTitle class="text-caption font-weight-bold">
                  {{ q.title }}
                </VListItemTitle>
                <VListItemSubtitle class="text-super-xs text-medium-emphasis">
                  {{ q.rangeText }}
                </VListItemSubtitle>
                <template #append>
                  <VChip
                    v-if="q.isCurrent"
                    size="x-small"
                    color="info"
                    variant="tonal"
                    class="font-weight-bold"
                  >
                    Actual
                  </VChip>
                </template>
              </VListItem>
            </VList>
          </VMenu>
        </template>

        <!-- CASO 2: MODO MENSUAL ESTÁNDAR (CE DESACTIVADO) -->
        <template v-else>
          <VMenu transition="scale-transition">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                :disabled="props.loading"
                color="primary"
                variant="tonal"
                size="small"
                class="font-weight-bold me-1"
                prepend-icon="tabler-calendar-month"
                append-icon="tabler-chevron-down"
              >
                {{ activeMonthLabel || `Meses ${currentYear}` }}
              </VBtn>
            </template>
            <VList class="rounded-lg shadow-lg pa-1" min-width="200" max-height="340">
              <div class="px-3 py-1 text-super-xs font-weight-black text-uppercase text-disabled">
                Meses {{ currentYear }}
              </div>
              <VListItem
                v-for="m in monthsOfYear"
                :key="m.index"
                :active="isMonthActive(m.index)"
                class="rounded-md mb-1"
                @click="setSpecificMonth(m.index)"
              >
                <template #prepend>
                  <VIcon
                    :icon="isMonthActive(m.index) ? 'tabler-circle-check' : 'tabler-calendar'"
                    size="18"
                    :color="isMonthActive(m.index) ? 'primary' : 'disabled'"
                    class="me-2"
                  />
                </template>
                <VListItemTitle class="text-caption font-weight-bold">
                  {{ m.name }}
                </VListItemTitle>
                <template #append>
                  <VChip
                    v-if="m.index === currentMonthIndex"
                    size="x-small"
                    color="info"
                    variant="tonal"
                    class="font-weight-bold"
                  >
                    Actual
                  </VChip>
                </template>
              </VListItem>
            </VList>
          </VMenu>
        </template>

        <!-- Botón rápido Hoy -->
        <VBtn
          :disabled="props.loading"
          color="secondary"
          variant="tonal"
          size="x-small"
          class="rounded-pill px-3 font-weight-medium"
          @click="setDateHoy"
        >
          Hoy
        </VBtn>

        <!-- Botón Año Completo -->
        <VBtn
          :disabled="props.loading"
          color="secondary"
          variant="tonal"
          size="x-small"
          class="rounded-pill px-3 font-weight-medium"
          @click="setDateAnoCompleto"
        >
          Todo {{ currentYear }}
        </VBtn>
      </div>
    </template>

    <template #advanced-filters>
      <!-- Fecha Desde -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Desde"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha Hasta -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Hasta"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
