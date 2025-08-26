<script setup>

</script>

<template>
  <v-container class="bg-gray-100 py-6">
    <v-row class="justify-center">
      <v-col
        v-for="(card, index) in 3"
        :key="index"
        cols="12"
        md="4"
        class="p-4"
      >
        <v-card class="h-full shadow-md bg-white rounded-lg">
          <v-card-text>
            <div class="mb-4">
              <label :for="`input-${index}`" class="block text-sm font-medium text-gray-700 mb-1">
                Etiqueta {{ index + 1 }}
              </label>
              <input
                :id="`input-${index}`"
                type="text"
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Ingresa texto"
              />
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>




