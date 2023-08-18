<script setup>
import { Chart, registerables } from "chart.js"
import { computed, reactive } from "vue";
import { BarChart } from "vue-chart-3"

Chart.register(...registerables)

const props = defineProps({ 'data': Object })

const labels = computed(() => props.data.labels)
const totals = computed(() => props.data.totals)

const barData = reactive({
    labels: labels,
    datasets: [
        {
            label: "売上",
            data: totals,
            backgroundColor: "rgb(75, 192, 192)",
            tension: 0.1,
        }
    ]
})
</script>

<template>
    <div v-show="props.data">
        <BarChart :chart-data="barData"/>
    </div>
</template>