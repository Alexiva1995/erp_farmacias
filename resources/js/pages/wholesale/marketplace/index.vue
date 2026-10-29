<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';

// ─── Pestañas y Estados ────────────────────────────────────────────────────────
const activeTab = ref('catalog');
const loadingCatalog = ref(false);
const loadingStatement = ref(false);
const submittingPayment = ref(false);

const catalogItems = ref([]);
const catalogSearch = ref('');
const accountStatementData = ref(null);

// Formulario de Abono / Retención
const paymentDialog = ref(false);
const paymentForm = reactive({
  amount: 0,
  currency: 'USD',
  payment_method: 'bank_transfer',
  reference_number: '',
  bank_name: '',
  withheld_iva_amount: 0,
  withheld_islr_amount: 0,
  retention_voucher_number: '',
  receipt_photo_url: '',
});

// ─── Carga de Catálogo ─────────────────────────────────────────────────────────
const fetchCatalog = async () => {
  loadingCatalog.value = true;
  try {
    const response = await axios.get('/wholesale/marketplace/catalog', {
      params: { search: catalogSearch.value, per_page: 30 },
    });
    catalogItems.value = response.data?.data?.data || [];
  } catch (error) {
    toast.error('Error al cargar catálogo de productos mayoristas');
  } finally {
    loadingCatalog.value = false;
  }
};

// ─── Carga de Estado de Cuenta ────────────────────────────────────────────────
const fetchAccountStatement = async () => {
  loadingStatement.value = true;
  try {
    const response = await axios.get('/wholesale/marketplace/account-statement');
    accountStatementData.value = response.data?.data || null;
  } catch (error) {
    toast.error('Error al cargar estado de cuenta');
  } finally {
    loadingStatement.value = false;
  }
};

// ─── Envío de Pago / Retención ────────────────────────────────────────────────
const openPaymentModal = () => {
  paymentForm.amount = 0;
  paymentForm.reference_number = '';
  paymentForm.withheld_iva_amount = 0;
  paymentForm.withheld_islr_amount = 0;
  paymentForm.retention_voucher_number = '';
  paymentDialog.value = true;
};

const submitPayment = async () => {
  if (paymentForm.amount <= 0 && paymentForm.withheld_iva_amount <= 0) {
    toast.warning('Ingresa un monto de pago o retención válido.');
    return;
  }

  submittingPayment.value = true;
  try {
    await axios.post('/wholesale/marketplace/payments', paymentForm);
    toast.success('Pago / Retención registrado con éxito. Pasó a verificación por Cobranzas.');
    paymentDialog.value = false;
    fetchAccountStatement();
  } catch (error) {
    const msg = error.response?.data?.message || 'Error al registrar el pago';
    toast.error(msg);
  } finally {
    submittingPayment.value = false;
  }
};

onMounted(() => {
  fetchCatalog();
  fetchAccountStatement();
});
</script>

<template>
  <div class="wholesale-marketplace">
    <v-card elevation="1">
      <v-tabs v-model="activeTab" bg-color="primary" slider-color="white">
        <v-tab value="catalog" prepend-icon="mdi-storefront">Catálogo Mayorista B2B</v-tab>
        <v-tab value="statement" prepend-icon="mdi-file-document-outline">Mi Estado de Cuenta y Pagos</v-tab>
      </v-tabs>

      <v-window v-model="activeTab">
        <!-- Pestaña 1: Catálogo Mayorista -->
        <v-window-item value="catalog">
          <v-card-text>
            <v-row class="mb-2 align-center">
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="catalogSearch"
                  label="Buscar medicamentos por principio activo, nombre o código..."
                  variant="outlined"
                  density="compact"
                  prepend-inner-icon="mdi-magnify"
                  clearable
                  @keyup.enter="fetchCatalog"
                />
              </v-col>
              <v-col cols="12" md="6" class="text-right">
                <v-btn color="primary" variant="outlined" prepend-icon="mdi-refresh" @click="fetchCatalog">
                  Actualizar Lista
                </v-btn>
              </v-col>
            </v-row>

            <v-data-table
              :headers="[
                { title: 'Producto', key: 'name' },
                { title: 'Laboratorio', key: 'laboratory_name' },
                { title: 'Plazo Crédito', key: 'credit_term_label' },
                { title: 'Próx. Vencimiento', key: 'next_expiration' },
                { title: 'Stock Disp.', key: 'stock' },
                { title: 'Precio USD', key: 'final_price_usd' },
                { title: 'Precio Bs (BCV)', key: 'final_price_bs' },
              ]"
              :items="catalogItems"
              :loading="loadingCatalog"
              density="compact"
              hover
            >
              <template #item.credit_term_label="{ item }">
                <v-chip size="x-small" :color="item.credit_days === 0 ? 'grey' : 'info'" variant="flat">
                  {{ item.credit_term_label }}
                </v-chip>
              </template>
              <template #item.final_price_usd="{ item }">
                <span class="font-weight-bold text-success">${{ Number(item.final_price_usd).toFixed(2) }}</span>
              </template>
              <template #item.final_price_bs="{ item }">
                <span class="font-weight-bold">Bs. {{ Number(item.final_price_bs).toFixed(2) }}</span>
              </template>
            </v-data-table>
          </v-card-text>
        </v-window-item>

        <!-- Pestaña 2: Estado de Cuenta y Abonos -->
        <v-window-item value="statement">
          <v-card-text>
            <!-- KPIs Financieros -->
            <v-row v-if="accountStatementData" class="mb-4">
              <v-col cols="12" sm="3">
                <v-card variant="outlined" class="pa-3 text-center border-primary">
                  <div class="text-caption text-grey">Límite de Crédito</div>
                  <div class="text-h6 font-weight-bold text-primary">
                    ${{ Number(accountStatementData.credit_limit_usd).toFixed(2) }}
                  </div>
                </v-card>
              </v-col>
              <v-col cols="12" sm="3">
                <v-card variant="outlined" class="pa-3 text-center border-warning">
                  <div class="text-caption text-grey">Deuda Total USD</div>
                  <div class="text-h6 font-weight-bold text-warning">
                    ${{ Number(accountStatementData.total_debt_usd).toFixed(2) }}
                  </div>
                  <div class="text-caption text-grey">Bs. {{ Number(accountStatementData.total_debt_bs).toFixed(2) }}</div>
                </v-card>
              </v-col>
              <v-col cols="12" sm="3">
                <v-card variant="outlined" class="pa-3 text-center border-error">
                  <div class="text-caption text-grey">Deuda Vencida (Mora)</div>
                  <div class="text-h6 font-weight-bold text-error">
                    ${{ Number(accountStatementData.total_overdue_usd).toFixed(2) }}
                  </div>
                </v-card>
              </v-col>
              <v-col cols="12" sm="3">
                <v-card variant="outlined" class="pa-3 text-center border-success">
                  <div class="text-caption text-grey">Cupo Disponible</div>
                  <div class="text-h6 font-weight-bold text-success">
                    ${{ Number(accountStatementData.available_credit_usd).toFixed(2) }}
                  </div>
                </v-card>
              </v-col>
            </v-row>

            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-subtitle-1 font-weight-bold">Facturas de Crédito Pendientes</span>
              <v-btn color="primary" prepend-icon="mdi-cash-plus" @click="openPaymentModal">
                Reportar Pago / Retención
              </v-btn>
            </div>

            <!-- Tabla de Facturas Pendientes -->
            <v-table density="compact" hover class="border rounded">
              <thead>
                <tr>
                  <th>Factura</th>
                  <th>Emisión</th>
                  <th>Vencimiento</th>
                  <th>Estado</th>
                  <th>Monto Original</th>
                  <th>Saldo Pendiente USD</th>
                  <th>Equivalente Bs (BCV)</th>
                  <th>Pronto Pago</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="inv in accountStatementData?.invoices || []" :key="inv.credit_id">
                  <td class="font-weight-bold">{{ inv.invoice_number }}</td>
                  <td>{{ inv.issue_date }}</td>
                  <td>{{ inv.due_date }}</td>
                  <td>
                    <v-chip size="x-small" :color="inv.is_overdue ? 'error' : 'success'">
                      {{ inv.is_overdue ? `Vencida (${inv.days_overdue}d)` : 'Al día' }}
                    </v-chip>
                  </td>
                  <td>${{ Number(inv.credit_amount_usd).toFixed(2) }}</td>
                  <td class="font-weight-bold">${{ Number(inv.pending_amount_usd).toFixed(2) }}</td>
                  <td>Bs. {{ Number(inv.pending_amount_bs).toFixed(2) }}</td>
                  <td>
                    <v-chip v-if="inv.early_payment_eligible" size="x-small" color="secondary" variant="flat">
                      Ahorra ${{ Number(inv.early_payment_savings_usd).toFixed(2) }}
                    </v-chip>
                    <span v-else class="text-caption text-grey">N/A</span>
                  </td>
                </tr>
                <tr v-if="!accountStatementData?.invoices?.length">
                  <td colspan="8" class="text-center py-4 text-grey">
                    No tienes facturas de crédito pendientes de pago.
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card-text>
        </v-window-item>
      </v-window>
    </v-card>

    <!-- Modal de Reporte de Pago / Retención -->
    <v-dialog v-model="paymentDialog" max-width="550px">
      <v-card>
        <v-card-title class="bg-primary text-white">
          Reportar Abono, Pago o Retención
        </v-card-title>
        <v-card-text class="pa-4">
          <v-row dense>
            <v-col cols="6">
              <v-select
                v-model="paymentForm.currency"
                :items="['USD', 'BS', 'COP']"
                label="Moneda del Pago"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model.number="paymentForm.amount"
                type="number"
                min="0"
                label="Monto Pagado"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="paymentForm.reference_number"
                label="Nro. Referencia Bancaria / Confirmación"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="paymentForm.bank_name"
                label="Banco Emisor"
                variant="outlined"
                density="compact"
              />
            </v-col>

            <v-divider class="my-2" />
            <v-col cols="12">
              <span class="text-caption font-weight-bold text-primary">Comprobante de Retención SENIAT (Opcional)</span>
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model.number="paymentForm.withheld_iva_amount"
                type="number"
                label="Retención IVA ($)"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model="paymentForm.retention_voucher_number"
                label="Nro. Comprobante Retención"
                placeholder="20261000000001"
                variant="outlined"
                density="compact"
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="paymentDialog = false">Cancelar</v-btn>
          <v-btn color="primary" variant="flat" :loading="submittingPayment" @click="submitPayment">
            Enviar Pago a Conciliar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
