<script setup>
    const props = defineProps({
        modelValue: { type: Boolean, required: true },
        supplier: { type: Object, default: () => ({}) },
        laboratories: { type: Array, default: () => [] },
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
                        <VSelect
                            v-model="formData.laboratory_id"
                            label="Laboratorio"
                            :items="props.laboratories"
                            item-title="name"
                            item-value="id"
                            variant="outlined"
                            clearable
                            :error-messages="formErrors.laboratory_id"
                        />
                    </VCol>
                </VRow>
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
