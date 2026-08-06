<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'copy'])

const close = () => {
  emit('update:modelValue', false)
}
</script>

<template>
  <v-dialog :model-value="modelValue" max-width="600" @update:model-value="emit('update:modelValue', $event)">
    <v-card v-if="item" class="rounded-lg">
      <v-card-title class="bg-primary text-white d-flex align-center justify-space-between pa-4">
        <span class="text-h6 font-weight-bold">Detalle de Devolución #{{ item.invoice_number }}</span>
        <v-btn icon="tabler-x" variant="text" color="white" density="compact" @click="close" />
      </v-card-title>

      <v-card-text class="pa-6">
        <v-list density="compact">
          <v-list-item>
            <template #prepend>
              <v-icon icon="tabler-file-invoice" color="primary" class="mr-2" />
            </template>
            <v-list-item-title class="font-weight-bold">Factura:</v-list-item-title>
            <v-list-item-subtitle>#{{ item.invoice_number }}</v-list-item-subtitle>
          </v-list-item>

          <v-list-item>
            <template #prepend>
              <v-icon icon="tabler-building-store" color="primary" class="mr-2" />
            </template>
            <v-list-item-title class="font-weight-bold">Proveedor:</v-list-item-title>
            <v-list-item-subtitle>{{ item.supplier_name }} (RIF: {{ item.supplier_rif || 'N/A' }})</v-list-item-subtitle>
          </v-list-item>

          <v-divider class="my-3" />

          <v-list-item>
            <template #prepend>
              <v-icon icon="tabler-package" color="primary" class="mr-2" />
            </template>
            <v-list-item-title class="font-weight-bold">Producto a Devolver:</v-list-item-title>
            <v-list-item-subtitle>{{ item.product_name }}</v-list-item-subtitle>
          </v-list-item>

          <v-list-item>
            <template #prepend>
              <v-icon icon="tabler-barcode" color="primary" class="mr-2" />
            </template>
            <v-list-item-title class="font-weight-bold">Código / SKU:</v-list-item-title>
            <v-list-item-subtitle>{{ item.barcode || item.sku || 'N/A' }}</v-list-item-subtitle>
          </v-list-item>

          <v-list-item>
            <template #prepend>
              <v-icon icon="tabler-numbers" color="primary" class="mr-2" />
            </template>
            <v-list-item-title class="font-weight-bold">Cantidad Devuelta:</v-list-item-title>
            <v-list-item-subtitle>{{ item.quantity }} unidades</v-list-item-subtitle>
          </v-list-item>

          <v-list-item>
            <template #prepend>
              <v-icon icon="tabler-currency-dollar" color="success" class="mr-2" />
            </template>
            <v-list-item-title class="font-weight-bold">Monto Reembolso:</v-list-item-title>
            <v-list-item-subtitle class="text-success font-weight-black">
              ${{ parseFloat(item.amount_refunded).toFixed(2) }}
            </v-list-item-subtitle>
          </v-list-item>

          <v-list-item>
            <template #prepend>
              <v-icon icon="tabler-discount-2" color="warning" class="mr-2" />
            </template>
            <v-list-item-title class="font-weight-bold">Descuento Proveedor:</v-list-item-title>
            <v-list-item-subtitle>{{ item.supplier_discount_percentage }}%</v-list-item-subtitle>
          </v-list-item>

          <v-list-item>
            <template #prepend>
              <v-icon icon="tabler-box" color="primary" class="mr-2" />
            </template>
            <v-list-item-title class="font-weight-bold">Lote / Vencimiento:</v-list-item-title>
            <v-list-item-subtitle>Lote: {{ item.lot_number || 'N/A' }} | Venc: {{ item.expiration_date || 'N/A' }}</v-list-item-subtitle>
          </v-list-item>

          <v-list-item>
            <template #prepend>
              <v-icon icon="tabler-calendar" color="primary" class="mr-2" />
            </template>
            <v-list-item-title class="font-weight-bold">Fecha de Registro:</v-list-item-title>
            <v-list-item-subtitle>{{ item.return_date }}</v-list-item-subtitle>
          </v-list-item>
        </v-list>
      </v-card-text>

      <v-card-actions class="pa-4 bg-light flex-wrap gap-2">
        <v-btn
          color="primary"
          variant="elevated"
          prepend-icon="tabler-copy"
          @click="emit('copy', item)"
        >
          Copiar Datos
        </v-btn>

        <v-spacer />

        <v-btn variant="outlined" color="secondary" @click="close">
          Cerrar
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
