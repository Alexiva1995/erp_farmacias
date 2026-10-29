<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';

// ─── Estados Principales ───────────────────────────────────────────────────────
const loading = ref(false);
const submitting = ref(false);
const searchClientsQuery = ref('');
const clientsList = ref([]);
const selectedClient = ref(null);

const productsList = ref([]);
const searchProductQuery = ref('');
const loadingProducts = ref(false);

const cartItems = ref([]);
const orderNotes = ref('');
const orderResultsDialog = ref(false);
const orderResultsData = ref(null);

// ─── Carga de Clientes (Cartera) ───────────────────────────────────────────────
const fetchPortfolioClients = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/wholesale/portfolio', {
      params: { search: searchClientsQuery.value, per_page: 50 },
    });
    clientsList.value = response.data?.data?.data || [];
  } catch (error) {
    toast.error('Error al cargar la cartera de clientes');
  } finally {
    loading.value = false;
  }
};

// ─── Búsqueda de Productos Mayoristas ──────────────────────────────────────────
const fetchWholesaleProducts = async () => {
  if (!selectedClient.value) return;
  loadingProducts.value = true;
  try {
    const response = await axios.get('/wholesale/marketplace/catalog', {
      params: {
        client_id: selectedClient.value.id,
        search: searchProductQuery.value,
        per_page: 25,
      },
    });
    productsList.value = response.data?.data?.data || [];
  } catch (error) {
    toast.error('Error al consultar catálogo de productos');
  } finally {
    loadingProducts.value = false;
  }
};

const selectClient = (client) => {
  selectedClient.value = client;
  cartItems.value = [];
  fetchWholesaleProducts();
};

// ─── Carrito y Cálculos ────────────────────────────────────────────────────────
const addToCart = (product) => {
  const existing = cartItems.value.find(item => item.product_id === product.id);
  if (existing) {
    existing.quantity += 1;
  } else {
    cartItems.value.push({
      product_id: product.id,
      name: product.name,
      laboratory_name: product.laboratory_name,
      credit_days: product.credit_days,
      credit_term_label: product.credit_term_label,
      price: product.final_price_usd,
      quantity: 1,
      stock: product.stock,
      max_units_per_order: product.max_units_per_order,
    });
  }
  toast.success(`${product.name} añadido al pedido`);
};

const removeFromCart = (index) => {
  cartItems.value.splice(index, 1);
};

const cartTotalUSD = computed(() => {
  return cartItems.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
});

// Agrupación visual por plazo de crédito (Opción A)
const cartGroupedByCreditTerm = computed(() => {
  const groups = {};
  cartItems.value.forEach(item => {
    const term = item.credit_days || 0;
    if (!groups[term]) {
      groups[term] = {
        label: item.credit_term_label,
        items: [],
        total: 0,
      };
    }
    groups[term].items.push(item);
    groups[term].total += (item.price * item.quantity);
  });
  return groups;
});

// ─── Envío del Pedido ──────────────────────────────────────────────────────────
const submitWholesaleOrder = async () => {
  if (!selectedClient.value || cartItems.value.length === 0) return;

  submitting.value = true;
  try {
    const payload = {
      client_id: selectedClient.value.id,
      items: cartItems.value.map(item => ({
        product_id: item.product_id,
        quantity: item.quantity,
      })),
      notes: orderNotes.value,
    };

    const response = await axios.post('/wholesale/orders', payload);
    orderResultsData.value = response.data?.data;
    orderResultsDialog.value = true;
    cartItems.value = [];
    orderNotes.value = '';
    toast.success('Pedido procesado exitosamente.');
  } catch (error) {
    const msg = error.response?.data?.message || 'Error al procesar pedido';
    toast.error(msg);
  } finally {
    submitting.value = false;
  }
};

onMounted(() => {
  fetchPortfolioClients();
});
</script>

<template>
  <div class="wholesale-workstation">
    <v-row>
      <!-- Panel Izquierdo: Selección de Farmacia y Catálogo -->
      <v-col cols="12" md="7">
        <v-card class="mb-4" elevation="1">
          <v-card-title class="d-flex align-center justify-space-between">
            <span class="text-h6 font-weight-bold">Estación de Telemarketing / Asesores B2B</span>
            <v-chip v-if="selectedClient" color="primary" variant="flat">
              {{ selectedClient.name }} (Desc: {{ selectedClient.commercial_discount_pct || 0 }}%)
            </v-chip>
          </v-card-title>

          <v-card-text>
            <v-autocomplete
              v-model="selectedClient"
              :items="clientsList"
              item-title="name"
              return-object
              label="Seleccionar Farmacia de la Cartera"
              placeholder="Buscar por nombre, RIF o SICM..."
              variant="outlined"
              density="compact"
              prepend-inner-icon="mdi-hospital-box"
              @update:model-value="selectClient"
            >
              <template #item="{ props, item }">
                <v-list-item v-bind="props" :subtitle="`RIF: ${item.raw.identification} | Cupo: $${item.raw.credit_limit_usd || 0}`" />
              </template>
            </v-autocomplete>

            <!-- Semáforo de Crédito del Cliente Seleccionado -->
            <v-sheet v-if="selectedClient" class="pa-3 rounded bg-grey-lighten-4 mb-3">
              <v-row dense>
                <v-col cols="4">
                  <div class="text-caption text-grey">Límite de Crédito:</div>
                  <div class="text-subtitle-2 font-weight-bold">${{ Number(selectedClient.credit_limit_usd || 0).toFixed(2) }}</div>
                </v-col>
                <v-col cols="4">
                  <div class="text-caption text-grey">Días Base Cliente:</div>
                  <div class="text-subtitle-2 font-weight-bold">{{ selectedClient.default_credit_days || 0 }} días</div>
                </v-col>
                <v-col cols="4">
                  <div class="text-caption text-grey">Código SICM:</div>
                  <div class="text-subtitle-2 font-weight-bold text-primary">{{ selectedClient.sicm_code || 'N/A' }}</div>
                </v-col>
              </v-row>
            </v-sheet>

            <!-- Buscador de Productos Mayoristas -->
            <v-text-field
              v-if="selectedClient"
              v-model="searchProductQuery"
              label="Buscar Medicamentos / Insumos"
              placeholder="Principio activo, nombre o código..."
              variant="outlined"
              density="compact"
              prepend-inner-icon="mdi-magnify"
              class="mb-2"
              @keyup.enter="fetchWholesaleProducts"
            />

            <!-- Tabla de Catálogo -->
            <v-data-table
              v-if="selectedClient"
              :headers="[
                { title: 'Producto', key: 'name' },
                { title: 'Laboratorio', key: 'laboratory_name' },
                { title: 'Plazo', key: 'credit_term_label' },
                { title: 'Stock', key: 'stock' },
                { title: 'Precio Mayorista', key: 'final_price_usd' },
                { title: 'Acción', key: 'actions', sortable: false },
              ]"
              :items="productsList"
              :loading="loadingProducts"
              density="compact"
              hover
            >
              <template #item.credit_term_label="{ item }">
                <v-chip size="x-small" :color="item.credit_days === 0 ? 'grey' : 'info'" variant="flat">
                  {{ item.credit_term_label }}
                </v-chip>
              </template>
              <template #item.final_price_usd="{ item }">
                <span class="font-weight-bold text-success">${{ item.final_price_usd.toFixed(2) }}</span>
              </template>
              <template #item.actions="{ item }">
                <v-btn size="x-small" color="primary" variant="outlined" icon="mdi-plus" @click="addToCart(item)" />
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Panel Derecho: Carrito B2B con División Automática por Plazo -->
      <v-col cols="12" md="5">
        <v-card elevation="1">
          <v-card-title class="d-flex align-center justify-space-between">
            <span class="text-h6 font-weight-bold">Orden Mayorista</span>
            <v-chip color="secondary" variant="flat">
              Total: ${{ cartTotalUSD.toFixed(2) }}
            </v-chip>
          </v-card-title>

          <v-card-text>
            <div v-if="cartItems.length === 0" class="text-center py-6 text-grey">
              <v-icon size="48" color="grey-lighten-1">mdi-cart-outline</v-icon>
              <p class="mt-2 text-caption">No hay productos agregados en el pedido.</p>
            </div>

            <div v-else>
              <!-- Desglose por Sub-Órdenes según Plazo (Opción A) -->
              <div v-for="(group, term) in cartGroupedByCreditTerm" :key="term" class="mb-4">
                <v-card variant="outlined" density="compact" class="pa-2 border-primary">
                  <div class="d-flex justify-space-between align-center mb-2">
                    <span class="text-caption font-weight-bold text-primary">
                      Sub-Factura: {{ group.label }}
                    </span>
                    <span class="text-caption font-weight-bold">Subtotal: ${{ group.total.toFixed(2) }}</span>
                  </div>

                  <v-list density="compact" class="pa-0">
                    <v-list-item
                      v-for="(item, idx) in group.items"
                      :key="idx"
                      class="px-1"
                    >
                      <v-list-item-title class="text-caption font-weight-bold">
                        {{ item.name }}
                      </v-list-item-title>
                      <v-list-item-subtitle class="text-caption d-flex align-center justify-space-between">
                        <span>${{ item.price.toFixed(2) }} c/u</span>
                        <div class="d-flex align-center">
                          <v-text-field
                            v-model.number="item.quantity"
                            type="number"
                            min="1"
                            density="compact"
                            hide-details
                            style="width: 70px"
                            variant="outlined"
                          />
                          <v-btn size="x-small" color="error" variant="text" icon="mdi-delete" @click="removeFromCart(idx)" />
                        </div>
                      </v-list-item-subtitle>
                    </v-list-item>
                  </v-list>
                </v-card>
              </div>

              <v-textarea
                v-model="orderNotes"
                label="Notas de Despacho"
                rows="2"
                variant="outlined"
                density="compact"
                class="mt-3"
              />

              <v-btn
                block
                color="primary"
                size="large"
                class="mt-3"
                :loading="submitting"
                :disabled="cartItems.length === 0"
                prepend-icon="mdi-check-decagram"
                @click="submitWholesaleOrder"
              >
                Procesar Pedido B2B
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Diálogo de Resultado de División por Plazos -->
    <v-dialog v-model="orderResultsDialog" max-width="600px">
      <v-card>
        <v-card-title class="bg-primary text-white">
          Órdenes Generadas Exitosamente
        </v-card-title>
        <v-card-text class="pa-4">
          <p class="text-subtitle-2 mb-3">
            El pedido ha sido dividido automáticamente por plazos de vencimiento (Opción A):
          </p>
          <v-list density="compact">
            <v-list-item
              v-for="subOrder in orderResultsData?.orders || []"
              :key="subOrder.order_id"
              class="border rounded mb-2"
            >
              <v-list-item-title class="font-weight-bold">
                Orden #{{ subOrder.order_id }} — {{ subOrder.term_label }}
              </v-list-item-title>
              <v-list-item-subtitle class="d-flex justify-space-between">
                <span>Total: ${{ Number(subOrder.total_usd).toFixed(2) }}</span>
                <v-chip size="x-small" :color="subOrder.requires_credit_approval ? 'warning' : 'success'">
                  {{ subOrder.workflow_status }}
                </v-chip>
              </v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn color="primary" variant="flat" @click="orderResultsDialog = false">Aceptar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
