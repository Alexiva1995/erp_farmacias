<script setup>
    const props = defineProps({
        modelValue: { type: Boolean, required: true },
        supplier: { type: Object, default: () => ({}) },
        laboratories: { type: Array, default: () => [] },
        laboratoryLinks: { type: Array, default: () => [] },
        errors: { type: Object, default: () => ({}) },
    });

    const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

    const formData = ref({});
    const formErrors = ref({});

    const submitForm = () => {
        formErrors.value = {};
        emit("clearErrors");

        emit("save", formData.value);
    };

    const closeDialog = () => {
        emit("update:modelValue", false);
        formErrors.value = {};
        emit("clearErrors");
    };

    watch(
        () => props.errors,
        (newErrors) => {
            formErrors.value = newErrors || {};
            formData.value = {};
        },
        { deep: true }
    );

    const supplierLaboratoryHeaders = [
      { title: "Número Representante", key: "phone", sortable: false },
      { title: "Laboratorio", key: "laboratory.name", sortable: false },
    ];
</script>

<template>
    <VDialog
        :model-value="props.modelValue"
        max-width="800px"
        persistent
        @update:model-value="closeDialog"
        :scrollable="true"
        content-class="d-flex"
    >
    <VCard v-if="formData" class="d-flex flex-column">
        <VCardTitle class="d-flex align-center">
            <span class="text-h5 font-weight-bold">Laboratorios</span>

            <VSpacer />
            
            <VBtn icon variant="text" @click="closeDialog">
                <VIcon>tabler-x</VIcon>
            </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="flex-grow-1" style="overflow-y: auto">
            <VForm @submit.prevent="submitForm">
                <VRow>
                    <VCol cols="12" md="6">
                        <VTextField
                            v-model="formData.phone"
                            label="Número Representante"
                            type="tel"
                            prefix="+"
                            variant="outlined"
                            :error-messages="formErrors.phone"
                        />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VAutocomplete
                            v-model="formData.laboratory_id"
                            :items="props.laboratories"
                            label="Laboratorio"
                            placeholder="Escribe para buscar un laboratorio"
                            item-title="name"
                            item-value="id"
                            clearable
                            variant="outlined"
                            :error-messages="formErrors.laboratory_id"
                        />
                    </VCol>
                </VRow>

                <VDivider class="my-6" />

                <div class="d-flex align-center mb-4">
                    <p class="text-h6 font-weight-medium">Laboratorios Asociados</p>
                    <VSpacer />
                </div>

                <VDataTable
                    :headers="supplierLaboratoryHeaders"
                    :items="props.laboratoryLinks || []"
                    density="compact"
                    no-data-text="Este proveedor no tiene laboratorios asociados."
                >
                    <template #item.phone="{ item }">
                        <VTooltip text="Contactar por WhatsApp" location="top">
                            <template #activator="{ props }">
                                <VBtn
                                    icon
                                    :disabled="!item.phone"
                                    :href="item.phone ? `https://wa.me/${item.phone.replace(/\D/g, '')}` : undefined"
                                    target="_blank"
                                    variant="text"
                                    v-bind="props"
                                >
                                    <VIcon :icon="item.phone ? 'tabler-phone-call' : 'tabler-phone-off'" />
                                </VBtn>
                            </template>
                        </VTooltip>
                    </template>
                </VDataTable>
            </VForm>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
            <VBtn
                color="secondary"
                variant="outlined"
                @click="closeDialog"
                class="flex-grow-1 w-0 mr-4"
            >
            Cancelar
            </VBtn>
            <VBtn
                color="primary"
                variant="flat"
                @click="submitForm"
                class="flex-grow-1 w-0"
            >
            Guardar
            </VBtn>
        </VCardActions>
    </VCard>
    </VDialog>
</template>
