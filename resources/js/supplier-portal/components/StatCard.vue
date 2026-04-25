<template>
  <div class="card shadow-sm" :class="color">
    <div class="card-body py-4 px-4">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-2xl font-bold text-base-content">{{ value }}</p>
          <p class="text-xs text-base-content/60 mt-0.5">{{ label }}</p>
        </div>
        <span class="text-3xl">{{ icon }}</span>
      </div>
    </div>
  </div>
</template>
<script setup>
defineProps({ label: String, value: [Number, String], icon: String, color: { type: String, default: 'bg-white' } })
</script>
