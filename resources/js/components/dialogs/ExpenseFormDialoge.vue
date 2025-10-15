<script setup lang="js">

const props= defineProps({
  type_of_expense:{type:String, required: true, default: () => 'normal'},
  modalFormulario: {type: Boolean, required: true},
  titulo: {type: String, required: true},
  formData: {type: Object, default: () => {}},
  formError: {type: Object, default: () => []},
  categorias: {type: Array, default: () => []},
})

const emit= defineEmits(["modalClose", 'save', 'clearErrorForm'])

const bs=[
      "Efectivo",
      "Tarjeta",
      "Pago móvil",
      "Transferencia",
    ]

const usd=[
      "Efectivo",
      "Binance",
      "PayPal",
    ]

const cop=[
      "Efectivo",
      "Transferencia",
    ]

const currencies=["BS","USD", "COP"];

const recurrencia=[
  "Mensual","Semestral","Anual"
];


function close(){
  emit("modalClose",false)
}

// function generarFormData(estado){

//   let formData = new FormData();

//   Object.entries(estado).forEach(([key, value]) => {
//     if (value instanceof File) {
//       formData.append(key, value); // Archivo (Blob/File)
//     } else if (typeof value === 'object' && value !== null) {
//       formData.append(key, JSON.stringify(value)); // Objetos anidados
//     } else if (value === true || value === false) {
//       formData.append(key, value);
//     } else {
//       formData.append(key, value); // Strings/números
//     }
//   });

//   return formData
// }


function submitForm(){
  console.log("data XD => ",props.formData)
  emit("clearErrorForm")
  // let data=generarFormData(props.formData)
  emit("save",props.formData)
}
</script>
<template>
  <VDialog :model-value="props.modalFormulario" max-width="800px" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">{{ props.titulo }}</span>
        <VSpacer />
        <VBtn icon variant="text" @click="close">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VContainer>
        <VRow>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VTextField
              v-model="props.formData.name"
              :error-messages="props.formError.name"
              label="Nombre"
              type="text"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VSelect
              v-model="props.formData.category_id"
              label="Categoria"
              :items="props.categorias"
              :error-messages="props.formError.category_id"
              item-title="name"
              item-value="id"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VTextField
              v-model="props.formData.amount"
              :error-messages="props.formError.amount"
              label="Monto"
              type="number"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VTextField
              v-model="props.formData.amount_usd"
              :error-messages="props.formError.amount_usd"
              label="Monto USD"
              type="number"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VSelect
              v-model="props.formData.currency"
              label="Moneda"
              :items="currencies"
              :error-messages="props.formError.currency"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VSelect
              v-if="props.formData.currency == 'BS'"
              v-model="props.formData.count"
              label="Cuenta"
              :items="bs"
              :error-messages="props.formError.count"
            />
            <VSelect
              v-if="props.formData.currency == 'USD'"
              v-model="props.formData.count"
              label="Cuenta"
              :items="usd"
              :error-messages="props.formError.count"
            />
            <VSelect
              v-if="props.formData.currency == 'COP'"
              v-model="props.formData.count"
              label="Cuenta"
              :items="cop"
              :error-messages="props.formError.count"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" v-if="type_of_expense == 'recurrente'">
            <VSelect
              v-model="props.formData.recurrence"
              label="Recurrencia"
              :items="recurrencia"
              :error-messages="props.formError.recurrencia"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" v-if="type_of_expense == 'normal'">
            <AppDateTimePicker
              v-model="props.formData.expense_date"
              :error-messages="props.formError.expense_date"
              label="Fecha"
              variant="outlined"
              :config="{
                altInput: true,
                altFormat: 'Y-m-d',
                dateFormat: 'Y-m-d',
              }"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <div class="d-flex ga-4 align-center fill-height">
              <VCheckbox
                v-model="props.formData.is_deductible"
                class="mt-0 pt-0"
              >
                <template v-slot:label> Es Deducible </template>
              </VCheckbox>

              <VCheckbox v-model="props.formData.iva" class="mt-0 pt-0">
                <template v-slot:label>IVA</template>
              </VCheckbox>
            </div>
          </VCol>
        </VRow>
        <VRow>
          <VCol
            cols="12"
            sm="12"
            md="12"
            lg="12"
            v-if="type_of_expense == 'normal'"
          >
            <VCheckbox v-model="props.formData.has_invoice">
              <template v-slot:label> Tiene Factura </template>
            </VCheckbox>
          </VCol>
          <VCol
            cols="12"
            sm="12"
            md="12"
            lg="12"
            v-if="props.formData.has_invoice == true"
          >
            <v-file-input
              v-if="type_of_expense == 'normal'"
              v-model="props.formData.file_factura"
              :error-messages="props.formError.file_factura"
              accept="image/png, image/jpeg, image/jpg"
              clearable
              label="Factura"
              variant="outlined"
            />
          </VCol>
        </VRow>
      </VContainer>
      <VDivider />
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="close"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
          >Cancelar</VBtn
        >
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
          >Guardar Cambios</VBtn
        >
      </VCardActions>
    </VCard>
  </VDialog>
</template>
