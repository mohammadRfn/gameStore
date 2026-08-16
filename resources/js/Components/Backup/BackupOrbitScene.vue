<template>
  <div class="backup-orb-stage" aria-hidden="true">
    <span
      v-for="spark in sparks"
      :key="spark.id"
      class="backup-spark"
      :style="sparkStyle(spark)"
    ></span>

    <div class="backup-orb">
      <span class="backup-orb__ring"></span>
      <span class="backup-orb__ring"></span>
      <span class="backup-orb__ring"></span>
      <div class="backup-orb__core">♛</div>
      <span class="backup-orb__chip">CSV Vault</span>
      <span class="backup-orb__chip">Media Safe</span>
      <span class="backup-orb__chip">SQLite Core</span>
    </div>
  </div>
</template>

<script setup>
const sparks = Array.from({ length: 14 }, (_, index) => ({
  id: index,
  x: 8 + Math.random() * 84,
  y: 8 + Math.random() * 82,
  delay: Math.random() * 2.6,
  scale: 0.72 + Math.random() * 0.8,
}))

function sparkStyle(spark) {
  return {
    right: `${spark.x}%`,
    top: `${spark.y}%`,
    transform: `scale(${spark.scale})`,
    animationDelay: `${spark.delay}s`,
  }
}
</script>
