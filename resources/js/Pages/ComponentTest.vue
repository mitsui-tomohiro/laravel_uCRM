<script setup>
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import GuestLayout from '@/Layouts/GuestLayout.vue';

</script>

<template>
    <GuestLayout>
        <InputLabel>タイトル</InputLabel>
        <TextInput modelValue="初期値です"></TextInput>
    </GuestLayout>
</template>