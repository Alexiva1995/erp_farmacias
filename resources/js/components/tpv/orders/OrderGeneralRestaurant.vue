<script setup>
import AppFilterBase from "@/components/AppFilterBase.vue";
import OrderTable from "@/components/OrderTable.vue";
import OrderTicket from "@/components/OrderTicket.vue";
import OrderTicketThermal54 from "@/components/OrderTicketThermal54.vue";
import OrderViewModal from "@/components/dialogs/OrderViewModal.vue";
import FiscalInvoicePreviewDialog from "@/components/dialogs/FiscalInvoicePreviewDialog.vue";
import { useOrderGeneral } from "@/composables/useOrderGeneral";
import { formatAmountOnly } from "@/utils/currencyFormatter";

const {
  isVendedor,
  activeTab,
  orders,
  totalOrders,
  loadingOrders,
  page,
  itemsPerPage,
  sortBy,
  orderBy,
  summaryLoading,
  tabCounts,
  kpis,
  sellers,
  diningTables,
  filterSearchQueryId,
  filterSearchQuery,
  currencyFilter,
  sellerFilter,
  stateFilterAll,
  hasFiscalFilter,
  paymentMethodFilter,
  diningTableFilter,
  hasActiveAdvancedFilters,
  globalStartDate,
  globalEndDate,
  setDateHoy,
  setDateAyer,
  setDateSemana,
  setDateMes,
  setDateAno,
  viewModal,
  isPrinting,
  processingFiscalIds,
  isFiscalPreviewVisible,
  fiscalPreviewData,
  loadingFiscalPreview,
  isConfirmingFiscal,
  paymentsForPrint,
  changeAmountForPrint,
  creditAmountForPrint,
  amountForPrint,
  creditForPrint,
  currency,
  orderData,
  orderItems,
  selectedDiscountType,
  totalCompanyDiscountAmount,
  totalDoctorDiscountAmount,
  totalRecipeDiscountAmount,
  isSpecialTaxpayer,
  speSurchargeAmount,
  sellerDisplayName,
  handleUpdateTableOptions,
  handleClearFilters,
  handleViewOrder,
  handleCloseViewModal,
  handleOpenFiscalPreview,
  handleConfirmFiscalInvoice,
  handleCancelOrder,
  printOrder,
  printOrderThermal54,
} = useOrderGeneral({ mode: "restaurant", defaultTab: "completed" });

const headersBase = [
  { title: "ID", key: "id", sortable: true },
  { title: "Mesa / Canal", key: "dining_table", sortable: false },
  { title: "Comensales", key: "guests_count", sortable: false },
  { title: "Mesero / Cajero", key: "seller.username", sortable: true },
  { title: "Cliente", key: "client_full_name", sortable: true },
  { title: "Factura Fiscal", key: "fiscal_invoice", sortable: false },
  { title: "Monto", key: "total_amount", sortable: true, align: "end" },
  { title: "Moneda", key: "currency", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Acción", key: "actions", sortable: false },
];

const headersAll = [
  { title: "ID", key: "id", sortable: true },
  { title: "Mesa / Canal", key: "dining_table", sortable: false },
  { title: "Comensales", key: "guests_count", sortable: false },
  { title: "Mesero / Cajero", key: "seller.username", sortable: true },
  { title: "Cliente", key: "client_full_name", sortable: true },
  { title: "Factura Fiscal", key: "fiscal_invoice", sortable: false },
  { title: "Monto", key: "total_amount", sortable: true, align: "end" },
  { title: "Moneda", key: "currency", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Estado", key: "status", sortable: true },
  { title: "Acción", key: "actions", sortable: false },
];

const currencyOptions = [
  { title: "BS", value: "BS" },
  { title: "USD", value: "USD" },
  { title: "COP", value: "COP" },
];

const stateOptions = [
  { title: "En Mesa / Abierta", value: "open" },
  { title: "Completada", value: "Completed" },
  { title: "Abandonada", value: "Abandoned" },
  { title: "Cancelada", value: "Cancelled" },
];

const fiscalOptions = [
  { title: "Con Factura Fiscal", value: "yes" },
  { title: "Pendiente Fiscal", value: "pending" },
];

const paymentMethodOptions = [
  { title: "Efectivo USD", value: "cash_usd" },
  { title: "Efectivo BS", value: "cash_bs" },
  { title: "Efectivo COP", value: "cash_cop" },
  { title: "Pago Móvil", value: "mobile_payment" },
  { title: "Tarjeta Débito", value: "debit_card" },
  { title: "Tarjeta Crédito", value: "credit_card" },
  { title: "Transferencia BS", value: "bank_transfer_bs" },
  { title: "Transferencia COP", value: "bank_transfer" },
  { title: "Binance", value: "binance" },
  { title: "Crédito", value: "credit" },
];
</script>

<template>
  <div>
    <!-- KPIs de Restaurante -->
    <VRow dense class="mb-3">
      <VCol cols="12" sm="6" md="3">
        <VCard variant="flat" border class="pa-3 rounded-lg h-100">
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold">
                En Mesa / Abiertas
              </div>
              <VSkeletonLoader v-if="summaryLoading" type="text" width="80" />
              <div v-else class="text-h5 font-weight-black text-info">
                {{ tabCounts.open }}
              </div>
              <div class="text-caption text-disabled">Cuentas activas</div>
            </div>
            <VAvatar color="info" variant="tonal" size="40" rounded="lg">
              <VIcon icon="tabler-tools-kitchen-2" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="flat" border class="pa-3 rounded-lg h-100">
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold">
                Cuentas Cobradas
              </div>
              <VSkeletonLoader v-if="summaryLoading" type="text" width="90" />
              <div v-else class="text-h5 font-weight-black text-primary">
                {{ kpis.completed_count }}
              </div>
              <div class="text-caption text-disabled">
                Ticket prom: {{ formatAmountOnly(kpis.avg_ticket_usd, "USD") }}
              </div>
            </div>
            <VAvatar color="primary" variant="tonal" size="40" rounded="lg">
              <VIcon icon="tabler-receipt-2" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="flat" border class="pa-3 rounded-lg h-100">
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold">
                Ventas USD / Servicio
              </div>
              <VSkeletonLoader v-if="summaryLoading" type="text" width="110" />
              <div v-else class="text-h5 font-weight-black text-success">
                {{ formatAmountOnly(kpis.total_usd, "USD") }}
              </div>
              <div class="text-caption text-disabled">
                Servicio: {{ formatAmountOnly(kpis.total_service_usd, "USD") }}
              </div>
            </div>
            <VAvatar color="success" variant="tonal" size="40" rounded="lg">
              <VIcon icon="tabler-currency-dollar" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="flat" border class="pa-3 rounded-lg h-100">
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold">
                Control Fiscal SENIAT
              </div>
              <VSkeletonLoader v-if="summaryLoading" type="text" width="100" />
              <div v-else class="d-flex align-center gap-2">
                <span class="text-h5 font-weight-black text-secondary">{{ kpis.fiscalized_count }}</span>
                <VChip size="x-small" color="warning" variant="tonal" class="font-weight-bold">
                  {{ kpis.pending_fiscal_count }} pend.
                </VChip>
              </div>
              <div class="text-caption text-disabled">
                {{ tabCounts.cancelled }} anuladas
              </div>
            </div>
            <VAvatar color="warning" variant="tonal" size="40" rounded="lg">
              <VIcon icon="tabler-file-invoice" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filtros -->
    <AppFilterBase
      :search="filterSearchQuery"
      :has-advanced-filters="hasActiveAdvancedFilters"
      :show-export="false"
      search-placeholder="Buscar por ID, Mesa, Factura Fiscal, Cliente o Mesero..."
      class="py-1"
      @update:search="filterSearchQuery = $event"
      @clear="handleClearFilters"
    >
      <template #search-extra>
        <div class="d-none d-lg-flex align-center gap-2 ms-4 border-s ps-4">
          <span class="text-caption font-weight-bold text-uppercase text-disabled me-1">Rango:</span>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateHoy">Hoy</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateAyer">Ayer</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateSemana">Semana</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateMes">Mes</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateAno">Año</VBtn>
        </div>
      </template>

      <template #advanced-filters>
        <VCol cols="12" sm="3" md="2">
          <AppDateTimePicker
            v-model="globalStartDate"
            placeholder="Fecha Inicial"
            prepend-inner-icon="tabler-calendar"
            clearable
            hide-details
            density="compact"
            :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <AppDateTimePicker
            v-model="globalEndDate"
            placeholder="Fecha Final"
            prepend-inner-icon="tabler-calendar"
            clearable
            hide-details
            density="compact"
            :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <AppTextField
            v-model="filterSearchQueryId"
            placeholder="ID Comanda"
            prepend-inner-icon="tabler-hash"
            clearable
            hide-details
            density="compact"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <VSelect
            v-model="diningTableFilter"
            placeholder="Mesa"
            prepend-inner-icon="tabler-armchair"
            :items="diningTables"
            item-title="title"
            item-value="id"
            clearable
            hide-details
            density="compact"
            variant="outlined"
          />
        </VCol>
        <VCol v-if="!isVendedor" cols="12" sm="3" md="2">
          <VSelect
            v-model="sellerFilter"
            placeholder="Mesero / Cajero"
            prepend-inner-icon="tabler-user-check"
            :items="sellers"
            :item-title="sellerDisplayName"
            item-value="id"
            clearable
            hide-details
            density="compact"
            variant="outlined"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <VSelect
            v-model="currencyFilter"
            placeholder="Moneda"
            prepend-inner-icon="tabler-coin"
            :items="currencyOptions"
            clearable
            hide-details
            density="compact"
            variant="outlined"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <VSelect
            v-model="hasFiscalFilter"
            placeholder="Estado Fiscal"
            prepend-inner-icon="tabler-file-invoice"
            :items="fiscalOptions"
            clearable
            hide-details
            density="compact"
            variant="outlined"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <VSelect
            v-model="paymentMethodFilter"
            placeholder="Método de Pago"
            prepend-inner-icon="tabler-credit-card"
            :items="paymentMethodOptions"
            clearable
            hide-details
            density="compact"
            variant="outlined"
          />
        </VCol>
        <VCol v-if="activeTab === 'all'" cols="12" sm="3" md="2">
          <VSelect
            v-model="stateFilterAll"
            placeholder="Estado"
            prepend-inner-icon="tabler-adjustments-horizontal"
            :items="stateOptions"
            clearable
            hide-details
            density="compact"
            variant="outlined"
          />
        </VCol>
      </template>
    </AppFilterBase>

    <!-- Pestañas Restaurante -->
    <VTabs v-model="activeTab" class="mb-4" density="comfortable">
      <VTab value="open">
        <span class="d-inline-flex align-center gap-2">
          En Mesa / Abiertas
          <VChip size="x-small" variant="tonal" color="info">{{ tabCounts.open }}</VChip>
        </span>
      </VTab>
      <VTab value="completed">
        <span class="d-inline-flex align-center gap-2">
          Completadas
          <VChip size="x-small" variant="tonal" color="success">{{ tabCounts.completed }}</VChip>
        </span>
      </VTab>
      <VTab value="all">
        <span class="d-inline-flex align-center gap-2">
          Todas
          <VChip size="x-small" variant="tonal" color="primary">{{ tabCounts.all }}</VChip>
        </span>
      </VTab>
      <VTab value="cancelled">
        <span class="d-inline-flex align-center gap-2">
          Canceladas
          <VChip size="x-small" variant="tonal" color="error">{{ tabCounts.cancelled }}</VChip>
        </span>
      </VTab>
      <VTab value="abandoned">
        <span class="d-inline-flex align-center gap-2">
          Abandonadas
          <VChip size="x-small" variant="tonal" color="warning">{{ tabCounts.abandoned }}</VChip>
        </span>
      </VTab>
    </VTabs>

    <VWindow v-model="activeTab" class="overflow-visible">
      <VWindowItem :value="activeTab">
        <OrderTable
          :orders="orders"
          :loading="loadingOrders"
          :total-orders="totalOrders"
          :items-per-page="itemsPerPage"
          :page="page"
          :headers="activeTab === 'all' ? headersAll : headersBase"
          :sort-by="sortBy"
          :order-by="orderBy || 'desc'"
          :show-print-actions="true"
          :show-thermal-print="true"
          :processing-fiscal-ids="processingFiscalIds"
          @update:options="handleUpdateTableOptions"
          @print-order="printOrder"
          @print-order-thermal="printOrderThermal54"
          @view-order="handleViewOrder"
          @cancel-order="handleCancelOrder"
          @create-fiscal-invoice="handleOpenFiscalPreview"
        />
      </VWindowItem>
    </VWindow>

    <!-- Contenedores ocultos de impresión -->
    <div id="orderPrint" :class="{ 'd-none': !isPrinting, 'print-container': true }">
      <OrderTicket
        v-if="isPrinting && orderData"
        :order-data="orderData"
        :order-products="orderItems"
        :total-amount="amountForPrint"
        :selected-currency="currency"
        :payments="paymentsForPrint"
        :change-amount="changeAmountForPrint"
        :credit-amount="creditAmountForPrint"
        :credit="creditForPrint"
        :company-discount-total="totalCompanyDiscountAmount"
        :selected-discount-type="selectedDiscountType"
        :doctor-discount-total="totalDoctorDiscountAmount"
        :recipe-discount-total="totalRecipeDiscountAmount"
        :is-special-taxpayer="isSpecialTaxpayer"
        :spe-surcharge-amount="speSurchargeAmount"
      />
    </div>

    <div id="orderPrintThermal54" :class="{ 'd-none': !isPrinting, 'print-container': true }">
      <OrderTicketThermal54
        v-if="isPrinting && orderData"
        :order-data="orderData"
        :order-products="orderItems"
        :total-amount="amountForPrint"
        :selected-currency="currency"
        :payments="paymentsForPrint"
        :change-amount="changeAmountForPrint"
        :credit-amount="creditAmountForPrint"
        :credit="creditForPrint"
        :company-discount-total="totalCompanyDiscountAmount"
        :selected-discount-type="selectedDiscountType"
        :doctor-discount-total="totalDoctorDiscountAmount"
        :recipe-discount-total="totalRecipeDiscountAmount"
        :is-special-taxpayer="isSpecialTaxpayer"
        :spe-surcharge-amount="speSurchargeAmount"
      />
    </div>

    <OrderViewModal
      v-model:isDialogVisible="viewModal"
      mode="restaurant"
      :order-data="orderData"
      :order-products="orderItems"
      :total-amount="amountForPrint"
      :selected-currency="currency"
      :payments="paymentsForPrint"
      :change-amount="changeAmountForPrint"
      :credit-amount="creditAmountForPrint"
      :credit="creditForPrint"
      :is-processing-fiscal="processingFiscalIds.includes(orderData?.id)"
      :is-special-taxpayer="isSpecialTaxpayer"
      @close="handleCloseViewModal"
      @cancel-order="handleCancelOrder"
      @create-fiscal-invoice="handleOpenFiscalPreview"
      @print-order="printOrder"
      @print-order-thermal="printOrderThermal54"
    />

    <FiscalInvoicePreviewDialog
      v-model="isFiscalPreviewVisible"
      :preview-data="fiscalPreviewData"
      :loading="loadingFiscalPreview"
      :is-confirming="isConfirmingFiscal"
      @confirm="handleConfirmFiscalInvoice"
    />
  </div>
</template>
