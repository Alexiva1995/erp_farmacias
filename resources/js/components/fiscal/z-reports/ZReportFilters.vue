<script setup>
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: { type: String, default: "" },
  startDate: { type: [String, null], default: null },
  endDate: { type: [String, null], default: null },
  loading: { type: Boolean, default: false },
  downloadingImages: { type: Boolean, default: false },
  isCeEnabled: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "clear",
  "export",
  "sort",
  "download-images",
]);

const sortOptions = [
  { title: "N° Reporte Mayor", icon: "tabler-sort-descending-numbers", key: "report_number", order: "desc" },
  { title: "N° Reporte Menor", icon: "tabler-sort-ascending-numbers", key: "report_number", order: "asc" },
  { title: "Monto Total Mayor", icon: "tabler-arrow-up", key: "total_amount", order: "desc" },
  { title: "Monto Total Menor", icon: "tabler-arrow-down", key: "total_amount", order: "asc" },
  { title: "Fecha Reciente", icon: "tabler-calendar-up", key: "report_date", order: "desc" },
  { title: "Fecha Antigua", icon: "tabler-calendar-down", key: "report_date", order: "asc" },
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

// Modo Mensual
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

// Modo Quincenal
const quincenasOfYear = computed(() => {
  const list = [];
  monthsOfYear.forEach((m) => {
    const q1Start = formatOffsetDate(new Date(currentYear, m.index, 1));
    const q1End = formatOffsetDate(new Date(currentYear, m.index, 15));
    list.push({
      key: `${m.index}-Q1`,
      title: `1ra Quincena ${m.name}`,
      shortTitle: `1Q ${m.abbr}`,
      startDate: q1Start,
      endDate: q1End,
      rangeText: `01 al 15`,
      isCurrent: m.index === currentMonthIndex && currentDay <= 15,
    });

    const q2Start = formatOffsetDate(new Date(currentYear, m.index, 16));
    const q2End = formatOffsetDate(new Date(currentYear, m.index + 1, 0));
    list.push({
      key: `${m.index}-Q2`,
      title: `2da Quincena ${m.name}`,
      shortTitle: `2Q ${m.abbr}`,
      startDate: q2Start,
      endDate: q2End,
      rangeText: `16 al fin`,
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
    :show-export="false"
    :loading="props.loading"
    search-placeholder="Buscar por N° Reporte Z, N° Factura..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @sort="emit('sort', $event)"
  >
    <template #search-extra>
      <div class="d-none d-lg-flex align-center gap-1 ms-3 border-s ps-3">
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

    <template #prepend-actions>
      <VBtn
        icon
        color="success"
        variant="tonal"
        size="38"
        rounded="circle"
        :loading="props.downloadingImages"
        :disabled="props.loading"
        @click="emit('download-images')"
      >
        <VIcon icon="tabler-photo-down" />
        <VTooltip activator="parent" location="top">Descargar Fotos Z (ZIP)</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
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
