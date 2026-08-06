<script setup>
const props = defineProps({
  productFormFields: {
    type: Array,
    required: true
  },
  isSaving: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:productFormFields', 'change'])

const formFieldsOptions = [
  { label: "Código de Barras",           value: "barcode"            },
  { label: "Nombre del Producto",        value: "name"               },
  { label: "Descripción",                value: "description"        },
  { label: "Principio Activo",           value: "active_ingredient"  },
  { label: "Laboratorio / Marca",        value: "laboratory_id"      },
  { label: "Origen",                     value: "origin_id"          },
  { label: "Categoría",                  value: "category_id"        },
  { label: "Costo Unitario",             value: "unit_cost"          },
  { label: "Precio de Venta",            value: "sale_price"         },
  { label: "Inventario Inicial",         value: "stock"              },
  { label: "Impuesto IVA",               value: "iva"                },
  { label: "Origen Colombiano (COL)",    value: "is_colombian_origin"},
  { label: "Novaventa Flag",             value: "is_novaventa"       },
  { label: "Control Psicotrópico",       value: "psychotropic"       },
  { label: "No PVP (Sin precio venta)",  value: "no_pvp"             },
  { label: "Presentación",               value: "presentation"       },
  { label: "Unidad de Medida",           value: "unit_of_measure"    },
  { label: "Imagen / Foto",              value: "photo_url"          },
]

const handleChange = (val) => {
  if (props.isSaving) return
  emit('update:productFormFields', val)
  emit('change')
}
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="py-5">
      <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
        <VIcon icon="tabler-forms" color="primary" size="28" />
        Campos de Formulario de Productos
      </VCardTitle>
      <p class="text-caption text-medium-emphasis mb-6">
        Selecciona los campos requeridos y visibles al registrar o modificar un producto en el sistema.
      </p>

      <VDivider class="mb-6" />

      <VRow>
        <VCol
          v-for="field in formFieldsOptions"
          :key="field.value"
          cols="12"
          sm="6"
          md="4"
        >
          <VCard variant="outlined" class="rounded-lg pa-3 transition-all border-color-light">
            <VCheckbox
              :model-value="productFormFields"
              :value="field.value"
              :label="field.label"
              color="primary"
              density="compact"
              hide-details
              :disabled="isSaving"
              @update:model-value="handleChange"
            />
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.border-color-light {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}
</style>
