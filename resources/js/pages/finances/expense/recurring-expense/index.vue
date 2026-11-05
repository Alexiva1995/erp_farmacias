<script setup lang="js">
import { onMounted, ref, watch } from "vue";
import { useFixedExpenses } from "@/composables/useFixedExpenses";
import FixedExpenseDialog from "@/components/dialogs/FixedExpenseDialog.vue";
import PayFixedExpenseDialog from "@/components/dialogs/PayFixedExpenseDialog.vue";

const {
  // Cuotas
  occurrencesLoading,
  occurrences,
  occurrencesTotal,
  occurrencesPage,
  occurrencesPerPage,
  occurrencesScope,
  occurrencesSearch,
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
  templatesSearch,
  fetchTemplates,
  saveTemplate,
  toggleActive,
  deleteTemplate,

  // Categorías
  categories,
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
  occurrenceToSkip,
  openSkipDialog,
} = useFixedExpenses();

const currentTab = ref("pending"); // 'pending' | 'history' | 'templates'
const isActionLoading = ref(false);

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
    <!-- Encabezado Principal -->
    <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-4">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          Control de Gastos Fijos
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Compromisos periódicos de la operación (alquiler, luz, agua, internet, licencias y servicios)
        </p>
      </div>

      <div class="d-flex align-center gap-2">
        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-refresh"
          :loading="summaryLoading"
          @click="generateOccurrencesNow"
        >
          Actualizar Avisos
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          prepend-icon="tabler-plus"
          @click="openNewTemplateDialog"
        >
          Nuevo Gasto Fijo
        </VBtn>
      </div>
    </div>

    <!-- KPIs Rápidos -->
    <VRow dense class="mb-5">
      <!-- Vencidos -->
      <VCol cols="12" sm="4">
        <VCard class="pa-4 border-0 shadow-sm" style="border-left: 4px solid #ef4444 !important;">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-error uppercase">Vencidos</span>
            <VAvatar color="error" variant="tonal" size="32">
              <VIcon icon="tabler-alert-triangle" size="18" />
            </VAvatar>
          </div>
          <div class="text-h5 font-weight-bold text-error mb-1">
            {{ summary.overdue_count }} cuotas
          </div>
          <div class="text-caption text-medium-emphasis">
            Compromisos con fecha de pago anterior a hoy
          </div>
        </VCard>
      </VCol>

      <!-- Esta Semana -->
      <VCol cols="12" sm="4">
        <VCard class="pa-4 border-0 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-warning uppercase">Próximos 7 Días</span>
            <VAvatar color="warning" variant="tonal" size="32">
              <VIcon icon="tabler-clock" size="18" />
            </VAvatar>
          </div>
          <div class="text-h5 font-weight-bold text-warning mb-1">
            {{ summary.week_count }} cuotas
          </div>
          <div class="text-caption text-medium-emphasis">
            Compromisos a cancelar en los próximos 7 días
          </div>
        </VCard>
      </VCol>

      <!-- Total Pendiente -->
      <VCol cols="12" sm="4">
        <VCard class="pa-4 border-0 shadow-sm" style="border-left: 4px solid #3b82f6 !important;">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-info uppercase">Total Por Pagar</span>
            <VAvatar color="info" variant="tonal" size="32">
              <VIcon icon="tabler-calendar-due" size="18" />
            </VAvatar>
          </div>
          <div class="text-h5 font-weight-bold text-info mb-1">
            {{ summary.total_count }} cuotas
          </div>
          <div class="text-caption text-medium-emphasis">
            Cuotas pendientes generadas en el sistema
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Contenedor con Tabs -->
    <VCard variant="flat" class="border shadow-sm rounded-lg overflow-hidden">
      <VTabs v-model="currentTab" color="primary" class="px-4" density="comfortable">
        <VTab value="pending" class="font-weight-bold">
          <span>Avisos Por Pagar</span>
          <VChip
            v-if="summary.total_count > 0"
            size="x-small"
            color="warning"
            variant="tonal"
            class="ms-2 font-weight-bold"
          >
            {{ summary.total_count }}
          </VChip>
        </VTab>

        <VTab value="history" class="font-weight-bold">
          <span>Historial de Pagos</span>
        </VTab>

        <VTab value="templates" class="font-weight-bold">
          <span>Configuración de Gastos Fijos</span>
          <VChip
            v-if="templatesTotal > 0"
            size="x-small"
            color="primary"
            variant="tonal"
            class="ms-2 font-weight-bold"
          >
            {{ templatesTotal }}
          </VChip>
        </VTab>
      </VTabs>

      <VDivider />

      <!-- CONTENIDO TAB 1: Cuotas por Pagar -->
      <div v-if="currentTab === 'pending'" class="pa-4">
        <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
          <VTextField
            v-model="occurrencesSearch"
            placeholder="Buscar por nombre de gasto..."
            prepend-inner-icon="tabler-search"
            density="compact"
            variant="outlined"
            style="max-width: 320px;"
            clearable
            @update:model-value="fetchOccurrences"
          />

          <VBtn
            variant="text"
            color="secondary"
            prepend-icon="tabler-refresh"
            size="small"
            :loading="occurrencesLoading"
            @click="fetchOccurrences"
          >
            Recargar
          </VBtn>
        </div>

        <VDataTableServer
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
                {{ item.fixed_expense?.name || 'Gasto' }}
              </div>
              <div v-if="item.fixed_expense?.is_amount_variable" class="text-caption text-warning">
                Monto variable (confirmar al pagar)
              </div>
            </div>
          </template>

          <!-- Monto -->
          <template #item.amount="{ item }">
            <span class="font-weight-bold">
              {{ item.currency === 'USD' ? '$' : item.currency === 'BS' ? 'Bs.' : 'COP' }}
              {{ Number(item.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </span>
          </template>

          <!-- Estado -->
          <template #item.status="{ item }">
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
                variant="elevated"
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
      </div>

      <!-- CONTENIDO TAB 2: Historial -->
      <div v-if="currentTab === 'history'" class="pa-4">
        <VDataTableServer
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
              {{ item.status === 'Paid' ? 'Pagado' : 'Omitido' }}
            </VChip>
          </template>

          <template #item.amount="{ item }">
            <span class="font-weight-bold">
              {{ item.currency === 'USD' ? '$' : item.currency === 'BS' ? 'Bs.' : 'COP' }}
              {{ Number(item.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
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
      </div>

      <!-- CONTENIDO TAB 3: Plantillas de Gastos Fijos -->
      <div v-if="currentTab === 'templates'" class="pa-4">
        <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
          <VTextField
            v-model="templatesSearch"
            placeholder="Buscar por nombre..."
            prepend-inner-icon="tabler-search"
            density="compact"
            variant="outlined"
            style="max-width: 320px;"
            clearable
            @update:model-value="fetchTemplates"
          />

          <VBtn
            color="primary"
            variant="elevated"
            prepend-icon="tabler-plus"
            @click="openNewTemplateDialog"
          >
            Nuevo Gasto Fijo
          </VBtn>
        </div>

        <VDataTableServer
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
              {{ item.currency === 'USD' ? '$' : item.currency === 'BS' ? 'Bs.' : 'COP' }}
              {{ Number(item.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
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
              <VBtn color="primary" variant="elevated" @click="openNewTemplateDialog">
                Configurar Primer Gasto Fijo
              </VBtn>
            </div>
          </template>
        </VDataTableServer>
      </div>
    </VCard>

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
</style>
