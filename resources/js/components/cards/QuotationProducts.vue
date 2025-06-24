<script setup>
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  color: {
    type: String,
    required: false,
    default: "primary",
  },
  icon: {
    type: String,
    required: true,
  },
  stats: {
    type: String,
    required: true,
  },
  height: {
    type: Number,
    required: true,
  },
  series: {
    type: Array,
    required: true,
  },
  chartOptions: {
    type: null,
    required: true,
  },
});
</script>

<template>
        <VCard>
          <VCardItem>
            <VCardTitle>Productos Cargados</VCardTitle>
            <template #append>
              <IconBtn
                size="small"
                :color="isCodeShown ? 'primary' : 'default'"
                :class="isCodeShown ? '' : 'text-disabled'"
                @click="isCodeShown = !isCodeShown"
              >
                <VIcon size="20" icon="tabler-code" />
              </IconBtn>
            </template>
          </VCardItem>

          <VCardText class="d-flex flex-column pb-0"> </VCardText>
        </VCard>
</template>
