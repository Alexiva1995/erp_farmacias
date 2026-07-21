<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()

const enabledSupplierViews = ref([])
const enabledSupplierTypes = ref([])
const supplierFormFields = ref([])
const expenseSupplierFormFields = ref([])

const supplierViewOptions = [
  { label: "Lista de Proveedores", value: "list", description: "Habilita el catálogo principal y panel comercial de proveedores." },
  { label: "Órdenes de Compra", value: "purchase_orders", description: "Habilita la gestión y trazabilidad de órdenes de compra." },
]

const supplierTypeOptions = [
  { label: "Proveedores de Inventario", value: "inventory", description: "Proveedores de mercancía y productos para stock." },
  { label: "Proveedores de Gastos", value: "expenses", description: "Proveedores de servicios y gastos operativos con formulario rápido." },
]

const supplierFormFieldOptions = [
  { label: "Nombre Comercial", value: "name" },
  { label: "Razón Social", value: "social_reason" },
  { label: "RIF / Identificación Fiscal", value: "rif" },
  { label: "Dirección Física", value: "address" },
  { label: "Teléfono de Ventas", value: "sales_phone" },
  { label: "Teléfono de Cobranza", value: "collections_phone" },
  { label: "Tipo de Vencimiento de Pago", value: "payment_due_type" },
  { label: "Referencia de Fecha de Factura", value: "invoice_date_reference" },
  { label: "Días de Crédito Personalizado", value: "custom_due_days" },
  { label: "Referencia de Vencimiento", value: "payment_due_reference" },
  { label: "Método de Pago Habitual", value: "payment_method" },
  { label: "Indexado en Dólares (USD)", value: "is_indexed" },
  { label: "Logística y Despacho", value: "logistics_dispatch" },
]

// Campos disponibles para Proveedores de Gastos (formulario simplificado)
const expenseSupplierFormFieldOptions = [
  { label: "Nombre Comercial", value: "name" },
  { label: "RIF / Identificación Fiscal", value: "rif" },
  { label: "Dirección Física", value: "address" },
  { label: "Teléfono de Ventas", value: "sales_phone" },
  { label: "Teléfono de Cobranza", value: "collections_phone" },
  { label: "Método de Pago Habitual", value: "payment_method" },
  { label: "Indexado en Dólares (USD)", value: "is_indexed" },
]

const fetchSettings = async () => {
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data

    enabledSupplierViews.value = settings.enabled_supplier_views ?? supplierViewOptions.map(o => o.value)
    enabledSupplierTypes.value = settings.enabled_supplier_types ?? supplierTypeOptions.map(o => o.value)
    supplierFormFields.value = settings.supplier_form_fields ?? supplierFormFieldOptions.map(o => o.value)
    expenseSupplierFormFields.value = settings.expense_supplier_form_fields ?? expenseSupplierFormFieldOptions.map(o => o.value)
  } catch (error) {
    console.error("Error cargando configuración de proveedores:", error)
    toast.error("Error al cargar la configuración")
  }
}

const updateSettings = async () => {
  try {
    await axios.post('/general-settings', {
      enabled_supplier_views: enabledSupplierViews.value,
      enabled_supplier_types: enabledSupplierTypes.value,
      supplier_form_fields: supplierFormFields.value,
      expense_supplier_form_fields: expenseSupplierFormFields.value,
    })
    await brandingStore.fetchSettings()
    toast.success("Configuración de proveedores actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar configuración de proveedores:", error)
    toast.error("Error al actualizar la configuración")
  }
}

onMounted(() => fetchSettings())
</script>

<template>
  <div class="d-flex flex-column gap-6 pb-12">
    <!-- CARD 1: Vistas y Módulos de Proveedores -->
    <VCard class="rounded-lg border shadow-sm">
      <VCardItem class="py-4">
        <VCardTitle class="text-h5 font-weight-medium d-flex align-center gap-2">
          <VIcon icon="tabler-truck" class="text-primary" />
          Vistas y Tipos de Proveedores
        </VCardTitle>
        <div class="text-body-2 text-medium-emphasis mt-1">
          Si deshabilitas ambas vistas, la opción principal "Proveedores" desaparecerá automáticamente del menú lateral.
        </div>
      </VCardItem>

      <VDivider />

      <VCardItem class="py-5">
        <VRow>
          <!-- Vistas -->
          <VCol
            v-for="viewItem in supplierViewOptions"
            :key="viewItem.value"
            cols="12" sm="6" md="3"
          >
            <VCard variant="outlined" class="pa-4 h-full">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="font-weight-bold text-high-emphasis text-sm">{{ viewItem.label }}</span>
                <VSwitch
                  v-model="enabledSupplierViews"
                  :value="viewItem.value"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
              <span class="text-caption text-medium-emphasis">{{ viewItem.description }}</span>
            </VCard>
          </VCol>

          <!-- Tipos -->
          <VCol
            v-for="typeItem in supplierTypeOptions"
            :key="typeItem.value"
            cols="12" sm="6" md="3"
          >
            <VCard variant="outlined" class="pa-4 h-full">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="font-weight-bold text-high-emphasis text-sm">{{ typeItem.label }}</span>
                <VSwitch
                  v-model="enabledSupplierTypes"
                  :value="typeItem.value"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
              <span class="text-caption text-medium-emphasis">{{ typeItem.description }}</span>
            </VCard>
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>

    <!-- CARD 2: Campos del Formulario de Proveedores -->
    <VCard class="rounded-lg border shadow-sm">
      <VCardItem class="py-4">
        <VCardTitle class="text-h6 font-weight-medium d-flex align-center gap-2">
          <VIcon icon="tabler-forms" class="text-primary" />
          Campos y Secciones del Formulario de Proveedor
        </VCardTitle>
        <div class="text-body-2 text-medium-emphasis mt-2">
          Selecciona qué campos y secciones estarán visibles al crear o editar un proveedor en el sistema.
        </div>
      </VCardItem>

      <VDivider />

      <VCardText class="py-6">
        <VRow>
          <VCol
            v-for="field in supplierFormFieldOptions"
            :key="field.value"
            cols="12" sm="6" md="3"
          >
            <VSwitch
              v-model="supplierFormFields"
              :value="field.value"
              :label="field.label"
              color="primary"
              density="compact"
              @update:model-value="updateSettings"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- CARD 3: Campos del Formulario de Proveedores de Gastos -->
    <VCard class="rounded-lg border shadow-sm">
      <VCardItem class="py-4">
        <VCardTitle class="text-h6 font-weight-medium d-flex align-center gap-2">
          <VIcon icon="tabler-receipt-tax" class="text-warning" />
          Campos del Formulario de Proveedor de Gastos
        </VCardTitle>
        <div class="text-body-2 text-medium-emphasis mt-2">
          Selecciona qué campos estarán visibles al crear o editar un proveedor de tipo <strong>Gasto</strong> (formulario simplificado).
        </div>
      </VCardItem>

      <VDivider />

      <VCardText class="py-6">
        <VRow>
          <VCol
            v-for="field in expenseSupplierFormFieldOptions"
            :key="field.value"
            cols="12" sm="6" md="3"
          >
            <VSwitch
              v-model="expenseSupplierFormFields"
              :value="field.value"
              :label="field.label"
              color="warning"
              density="compact"
              @update:model-value="updateSettings"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>
