<script setup lang="js">
import { computed, onMounted, ref, watch } from "vue";
import { useFixedExpenses } from "@/composables/useFixedExpenses";
import FiltrosGastoRecurrente from "@/components/FiltrosGastoRecurrente.vue";
import FixedExpenseDialog from "@/components/dialogs/FixedExpenseDialog.vue";
import PayFixedExpenseDialog from "@/components/dialogs/PayFixedExpenseDialog.vue";

const {
  // Filtros
  search,
  categoryId,
  currency,
  dateFrom,
  dateTo,
  isActive,
  clearFilters,

  // Cuotas
  occurrencesLoading,
  occurrences,
  occurrencesTotal,
  occurrencesPage,
  occurrencesPerPage,
  occurrencesScope,
  fetchOccurrences,

  // Resumen
  summaryLoading,
  summary,
  fetchSummary,

  // Plantillas
  templatesLoading,
  templates,
  templatesTotal,
  templatesPage,
  templatesPerPage,
  fetchTemplates,
  saveTemplate,
  toggleActive,
  deleteTemplate,

  // Categorías
  categories,
  categoriesLoading,
  fetchCategories,

  // Acciones
  generateOccurrencesNow,
  payOccurrence,
  confirmSkipOccurrence,

  // Modales
  isTemplateDialogOpen,
  templateDialogTitle,
  selectedTemplate,
  openNewTemplateDialog,
  openEditTemplateDialog,

  isPayDialogOpen,
  selectedOccurrence,
  openPayDialog,

  isSkipDialogOpen,
  skipNote,
  openSkipDialog,
} = useFixedExpenses();

const currentTab = ref("pending"); // 'pending' | 'history' | 'templates'
const isActionLoading = ref(false);

const currencyAmounts = computed(() => {
  const map = { overdue: 0, week: 0, total: 0 };
  (summary.by_currency || []).forEach((row) => {
    if (!currency.value || row.currency === currency.value) {
      map.overdue += Number(row.overdue_amount) || 0;
      map.week += Number(row.week_amount) || 0;
      map.total += Number(row.total_amount) || 0;
    }
  });
  return map;
});

const kpis = computed(() => [
  {
    title: "Vencidos",
    count: summary.overdue_count || 0,
    amount: currencyAmounts.value.overdue,
    color: "error",
    icon: "tabler-alert-triangle",
  },
  {
    title: "Próximos 7 Días",
    count: summary.week_count || 0,
    amount: currencyAmounts.value.week,
    color: "warning",
    icon: "tabler-clock",
  },
  {
    title: "Total Por Pagar",
    count: summary.total_count || 0,
    amount: currencyAmounts.value.total,
    color: "info",
    icon: "tabler-calendar-due",
  },
]);

const occurrenceHeaders = [
  { title: "Vencimiento", key: "due_date", sortable: false },
  { title: "Gasto Fijo", key: "fixed_expense.name", sortable: false },
  { title: "Categoría", key: "fixed_expense.category.name", sortable: false },
  { title: "Monto", key: "amount", sortable: false },
  { title: "Moneda", key: "currency", sortable: false },
  { title: "Estado", key: "status", sortable: false },
  { title: "Acciones", key: "actions", sortable: false, align: "end" },
];

const templateHeaders = [
  { title: "Nombre", key: "name", sortable: false },
  { title: "Categoría", key: "category.name", sortable: false },
  { title: "Monto Base", key: "amount", sortable: false },
  { title: "Frecuencia", key: "frequency_label", sortable: false },
  { title: "Próximo Pago", key: "next_due_date", sortable: false },
  { title: "Aviso", key: "reminder_days_before", sortable: false },
  { title: "Estado", key: "is_active", sortable: false },
  { title: "Acciones", key: "actions", sortable: false, align: "end" },
];

onMounted(async () => {
  await Promise.all([
    fetchCategories(),
    fetchSummary(),
    fetchOccurrences(),
    fetchTemplates(),
  ]);
});

watch(currentTab, (newTab) => {
  if (newTab === "pending") {
    occurrencesScope.value = "pending";
    occurrencesPage.value = 1;
    fetchOccurrences();
  } else if (newTab === "history") {
    occurrencesScope.value = "history";
    occurrencesPage.value = 1;
    fetchOccurrences();
  } else if (newTab === "templates") {
    templatesPage.value = 1;
    fetchTemplates();
  }
});

async function handleSaveTemplate(payload) {
  isActionLoading.value = true;
  await saveTemplate(payload);
  isActionLoading.value = false;
}

async function handleConfirmPay({ occurrenceId, data }) {
  isActionLoading.value = true;
  await payOccurrence(occurrenceId, data);
  isActionLoading.value = false;
}

async function handleDeleteTemplate(item) {
  if (confirm(`¿Eliminar el gasto fijo "${item.name}"? Sus cuotas pasadas se conservarán como historial.`)) {
    await deleteTemplate(item);
  }
}
</script>

<template>
  <div class="fixed-expenses-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- === FILTROS === -->
      <div>
        <FiltrosGastoRecurrente
          v-model:search="search"
          v-model:currency="currency"
          v-model:category-id="categoryId"
          v-model:date-from="dateFrom"
          v-model:date-to="dateTo"
          v-model:is-active="isActive"
          :categories="categories"
          :loading="categoriesLoading"
          :refreshing="summaryLoading"
          class="mb-5"
          @clear="clearFilters"
          @add="openNewTemplateDialog"
          @refresh="generateOccurrencesNow"
        />
      </div>

      <!-- === KPIS RÁPIDOS === -->
      <VRow class="ma-0 mx-n1 mb-5" dense>
        <VCol v-for="kpi in kpis" :key="kpi.title" cols="12" sm="4" class="pa-1">
          <VCard
            class="stats-card h-100 border-0 overflow-hidden shadow-sm position-relative"
          >
            <div
              class="card-bg-decoration"
              :class="`bg-${kpi.color}-opacity-1`"
            ></div>
            <VCardText class="pa-4 relative-content h-100 d-flex flex-column">
              <div class="d-flex align-center gap-3 mb-3">
                <VAvatar
                  :color="kpi.color"
                  variant="tonal"
                  size="38"
                  class="rounded-lg"
                >
                  <VIcon :icon="kpi.icon" size="20" />
                </VAvatar>
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-widest"
                >
                  {{ kpi.title }}
                </span>
              </div>
              <div class="mt-auto">
                <div
                  class="text-h5 font-weight-black leading-none mb-1"
                  :class="kpi.color === 'warning' ? 'kpi-text-warning' : `text-${kpi.color}`"
                >
                  ${{ kpi.amount.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </div>
                <div class="d-flex align-center gap-1">
                  <span class="text-xs font-weight-black text-high-emphasis">
                    {{ kpi.count }}
                  </span>
                  <span class="text-super-xs text-disabled font-weight-black uppercase">
                    Cuotas
                  </span>
                </div>
              </div>
            </VCardText>
            <div class="accent-border" :class="`bg-${kpi.color}`"></div>
          </VCard>
        </VCol>
      </VRow>

      <!-- === TABLA CON TABS === -->
      <VCard
        variant="flat"
        class="rounded-lg border shadow-sm bg-surface overflow-hidden"
      >
        <VTabs
          v-model="currentTab"
          color="primary"
          class="px-4 bg-surface"
          align-tabs="start"
          density="comfortable"
        >
          <VTab
            value="pending"
            class="tab-with-badge font-weight-black text-xs uppercase letter-spacing-widest"
          >
            <span>Avisos Por Pagar</span>
            <VChip
              size="x-small"
              color="warning"
              variant="tonal"
              class="ms-2 font-weight-black"
            >
              {{ summary.total_count || 0 }}
            </VChip>
          </VTab>

          <VTab
            value="history"
            class="tab-with-badge font-weight-black text-xs uppercase letter-spacing-widest"
          >
            <span>Historial de Pagos</span>
          </VTab>

          <VTab
            value="templates"
            class="tab-with-badge font-weight-black text-xs uppercase letter-spacing-widest"
          >
            <span>Configuración de Gastos Fijos</span>
            <VChip
              size="x-small"
              color="primary"
              variant="tonal"
              class="ms-2 font-weight-black"
            >
              {{ templatesTotal || 0 }}
            </VChip>
          </VTab>
        </VTabs>

        <VDivider />

        <!-- CONTENIDO TAB 1: Cuotas por Pagar -->
        <VDataTableServer
          v-if="currentTab === 'pending'"
          :headers="occurrenceHeaders"
          :items="occurrences"
          :items-length="occurrencesTotal"
          :loading="occurrencesLoading"
          :items-per-page="occurrencesPerPage"
          :page="occurrencesPage"
          class="elevation-0"
          @update:options="(opt) => { occurrencesPage = opt.page; occurrencesPerPage = opt.itemsPerPage; fetchOccurrences(); }"
        >
          <!-- Vencimiento -->
          <template #item.due_date="{ item }">
            <div class="d-flex align-center gap-2">
              <span class="font-weight-medium">{{ item.due_date }}</span>
              <VChip
                v-if="item.is_overdue"
                size="x-small"
                color="error"
                variant="flat"
              >
                Vencido ({{ Math.abs(item.days_difference) }}d)
              </VChip>
              <VChip
                v-else-if="item.days_difference === 0"
                size="x-small"
                color="warning"
                variant="flat"
              >
                Hoy
              </VChip>
              <VChip
                v-else-if="item.days_difference <= 7"
                size="x-small"
                color="info"
                variant="tonal"
              >
                En {{ item.days_difference }}d
              </VChip>
            </div>
          </template>

          <!-- Gasto Fijo -->
          <template #item.fixed_expense.name="{ item }">
            <div>
              <div class="font-weight-bold text-high-emphasis">
                {{ item.fixed_expense?.name || "Gasto" }}
              </div>
              <div v-if="item.fixed_expense?.is_amount_variable" class="text-caption text-warning">
                Monto variable (confirmar al pagar)
              </div>
            </div>
          </template>

          <!-- Monto -->
          <template #item.amount="{ item }">
            <span class="font-weight-bold">
              {{ item.currency === "USD" ? "$" : item.currency === "BS" ? "Bs." : "COP" }}
              {{ Number(item.amount).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </span>
          </template>

          <!-- Estado -->
          <template #item.status>
            <VChip size="small" color="warning" variant="tonal">
              Por pagar
            </VChip>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-end gap-1">
              <VBtn
                size="small"
                color="success"
                variant="tonal"
                prepend-icon="tabler-cash"
                @click="openPayDialog(item)"
              >
                Pagar
              </VBtn>
              <VBtn
                size="small"
                color="secondary"
                variant="text"
                @click="openSkipDialog(item)"
              >
                Omitir
              </VBtn>
            </div>
          </template>

          <!-- Estado Vacío -->
          <template #no-data>
            <div class="text-center pa-8">
              <VIcon icon="tabler-circle-check" size="48" color="success" class="mb-2" />
              <div class="text-h6 font-weight-bold mb-1">¡Al día! No hay cuotas pendientes</div>
              <p class="text-body-2 text-medium-emphasis mb-3">
                No tienes compromisos por pagar en tu ventana de anticipación.
              </p>
              <VBtn color="primary" variant="tonal" size="small" @click="openNewTemplateDialog">
                Configurar Nuevo Gasto Fijo
              </VBtn>
            </div>
          </template>
        </VDataTableServer>

        <!-- CONTENIDO TAB 2: Historial -->
        <VDataTableServer
          v-if="currentTab === 'history'"
          :headers="occurrenceHeaders"
          :items="occurrences"
          :items-length="occurrencesTotal"
          :loading="occurrencesLoading"
          :items-per-page="occurrencesPerPage"
          :page="occurrencesPage"
          class="elevation-0"
          @update:options="(opt) => { occurrencesPage = opt.page; occurrencesPerPage = opt.itemsPerPage; fetchOccurrences(); }"
        >
          <template #item.status="{ item }">
            <VChip
              size="small"
              :color="item.status === 'Paid' ? 'success' : 'secondary'"
              variant="tonal"
            >
              {{ item.status === "Paid" ? "Pagado" : "Omitido" }}
            </VChip>
          </template>

          <template #item.amount="{ item }">
            <span class="font-weight-bold">
              {{ item.currency === "USD" ? "$" : item.currency === "BS" ? "Bs." : "COP" }}
              {{ Number(item.amount).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </span>
          </template>

          <template #item.actions="{ item }">
            <span v-if="item.paid_at" class="text-caption text-medium-emphasis">
              Pagado el {{ item.paid_at.substring(0, 10) }}
            </span>
            <span v-else-if="item.note" class="text-caption text-medium-emphasis">
              {{ item.note }}
            </span>
            <span v-else class="text-caption text-medium-emphasis">-</span>
          </template>

          <template #no-data>
            <div class="text-center pa-6 text-medium-emphasis">
              No hay historial registrado aún.
            </div>
          </template>
        </VDataTableServer>

        <!-- CONTENIDO TAB 3: Plantillas de Gastos Fijos -->
        <VDataTableServer
          v-if="currentTab === 'templates'"
          :headers="templateHeaders"
          :items="templates"
          :items-length="templatesTotal"
          :loading="templatesLoading"
          :items-per-page="templatesPerPage"
          :page="templatesPage"
          class="elevation-0"
          @update:options="(opt) => { templatesPage = opt.page; templatesPerPage = opt.itemsPerPage; fetchTemplates(); }"
        >
          <template #item.name="{ item }">
            <div>
              <span class="font-weight-bold">{{ item.name }}</span>
              <div v-if="item.is_amount_variable" class="text-caption text-warning">
                Monto variable
              </div>
            </div>
          </template>

          <template #item.amount="{ item }">
            <span class="font-weight-medium">
              {{ item.currency === "USD" ? "$" : item.currency === "BS" ? "Bs." : "COP" }}
              {{ Number(item.amount).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </span>
          </template>

          <template #item.reminder_days_before="{ item }">
            <span>{{ item.reminder_days_before }} días antes</span>
          </template>

          <template #item.is_active="{ item }">
            <VSwitch
              :model-value="item.is_active"
              color="primary"
              density="compact"
              hide-details
              @click.stop="toggleActive(item)"
            />
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-end gap-1">
              <VBtn
                size="small"
                variant="text"
                color="primary"
                icon="tabler-edit"
                @click="openEditTemplateDialog(item)"
              />
              <VBtn
                size="small"
                variant="text"
                color="error"
                icon="tabler-trash"
                @click="handleDeleteTemplate(item)"
              />
            </div>
          </template>

          <template #no-data>
            <div class="text-center pa-8">
              <VIcon icon="tabler-calendar-plus" size="48" color="primary" class="mb-2" />
              <div class="text-h6 font-weight-bold mb-1">No hay gastos fijos configurados</div>
              <p class="text-body-2 text-medium-emphasis mb-3">
                Agrega tus compromisos mensuales (alquiler, nómina fija, servicios) para recibir alertas de pago.
              </p>
              <VBtn color="primary" variant="tonal" @click="openNewTemplateDialog">
                Configurar Primer Gasto Fijo
              </VBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Diálogo Crear/Editar Gasto Fijo -->
    <FixedExpenseDialog
      v-model="isTemplateDialogOpen"
      :title="templateDialogTitle"
      :template-data="selectedTemplate"
      :categories="categories"
      :loading="isActionLoading"
      @save="handleSaveTemplate"
    />

    <!-- Diálogo Pagar Cuota -->
    <PayFixedExpenseDialog
      v-model="isPayDialogOpen"
      :occurrence="selectedOccurrence"
      :loading="isActionLoading"
      @confirm="handleConfirmPay"
    />

    <!-- Diálogo Confirmar Omitir Cuota -->
    <VDialog v-model="isSkipDialogOpen" max-width="480">
      <VCard class="pa-2">
        <VCardTitle class="font-weight-bold">
          ¿Omitir esta cuota?
        </VCardTitle>
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-3">
            La cuota se marcará como omitida y no se registrará ningún egreso. El gasto fijo continuará activo para el próximo mes.
          </p>
          <VTextField
            v-model="skipNote"
            label="Motivo u observación (opcional)"
            placeholder="Ej: Mes exonerado por propietario..."
            density="compact"
            variant="outlined"
          />
        </VCardText>
        <VCardActions class="px-4 pb-3">
          <VSpacer />
          <VBtn variant="text" color="secondary" @click="isSkipDialogOpen = false">
            Cancelar
          </VBtn>
          <VBtn color="warning" variant="elevated" @click="confirmSkipOccurrence">
            Omitir Cuota
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.fixed-expenses-view {
  background-color: #f8fafc;
  min-block-size: 100vh;
}

.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 90%) !important;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.stats-card:not(.no-hover):hover {
  transform: translateY(-4px);
  background: rgba(var(--v-theme-surface), 98%) !important;
  box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.15) !important;
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 60px;
  filter: blur(35px);
  inline-size: 60px;
  inset-block-start: -10px;
  inset-inline-end: -10px;
  pointer-events: none;
  opacity: 0.5;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 100%;
  inline-size: 4px;
  inset-block-start: 0;
  inset-inline-start: 0;
  opacity: 0.7;
}

.bg-info-opacity-1 {
  background: rgba(var(--v-theme-info), 0.1);
}
.bg-warning-opacity-1 {
  background: rgba(var(--v-theme-warning), 0.1);
}
.kpi-text-warning {
  color: #92400e !important;
}
.bg-error-opacity-1 {
  background: rgba(var(--v-theme-error), 0.1);
}

.letter-spacing-widest {
  letter-spacing: 0.1em !important;
}
.text-super-xs {
  font-size: 0.65rem !important;
}

:deep(.v-tabs) {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.1);
  min-height: 48px !important;
}

:deep(.v-tab) {
  min-block-size: 48px !important;
  height: 48px !important;
  padding-inline: 16px !important;
}

:deep(.v-tab .v-btn__content) {
  display: flex !important;
  align-items: center !important;
  line-height: 1 !important;
  padding-bottom: 0 !important;
}
</style>
