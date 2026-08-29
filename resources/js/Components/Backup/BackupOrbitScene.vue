<script setup>

const chips = ['بسته کامل', 'تصاویر امن', 'بازیابی سریع']

const sparks = Array.from({ length: 14 }, (_, index) => ({
  id: index,
  x: 6 + Math.random() * 88,
  y: 6 + Math.random() * 86,
  delay: Math.random() * 2.8,
  scale: 0.6 + Math.random() * 0.8,
}))

function sparkStyle(spark) {
  return {
    insetInlineEnd: spark.x + '%',
    top: spark.y + '%',
    transform: 'scale(' + spark.scale + ')',
    animationDelay: spark.delay + 's',
  }
}
</script>

<template>
  <div class="backup-orb" aria-hidden="true">
    <span class="backup-orb__ring" />
    <span class="backup-orb__ring" />
    <span class="backup-orb__ring" />

    <div class="backup-orb__core">♛</div>

    <span v-for="chip in chips" :key="chip" class="backup-orb__chip">{{ chip }}</span>

    <i
      v-for="spark in sparks"
      :key="spark.id"
      class="backup-spark"
      :style="sparkStyle(spark)"
    />
  </div>
</template>
