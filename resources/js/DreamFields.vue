<script setup>
defineProps({ form: Object, categories: Object, prefix: String });
</script>
<template>
    <label class="dream-field" :for="`${prefix}-title`">Nome do sonho<input :id="`${prefix}-title`" v-model="form.title" name="title" required maxlength="255" placeholder="O que você quer conquistar?" /></label>
    <label class="dream-field" :for="`${prefix}-notes`">Descrição<textarea :id="`${prefix}-notes`" v-model="form.notes" name="notes" maxlength="10000" rows="3" placeholder="Dê forma ao seu sonho…" /></label>
    <div class="dream-field-grid">
        <label class="dream-field">Constelação<select v-model="form.category" name="category"><option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option></select></label>
        <label class="dream-field">Prioridade<select v-model="form.priority" name="priority"><option value="low">Baixa</option><option value="medium">Média</option><option value="high">Alta</option></select></label>
    </div>
    <label class="dream-field">Prazo desejado <small>opcional</small><input v-model="form.target_date" type="date" name="target_date" /></label>
    <details class="dream-planning"><summary>Planejamento financeiro e imagem</summary><div class="dream-field-grid">
        <label class="dream-field">Meta financeira (R$)<input v-model="form.financial_goal" type="number" min="0.01" max="999999999999.99" step="0.01" name="financial_goal" /></label>
        <label class="dream-field">Valor economizado (R$)<input v-model="form.saved_amount" type="number" min="0" max="999999999999.99" step="0.01" name="saved_amount" /></label>
    </div><label class="dream-field">Imagem (URL HTTPS)<input v-model="form.image_url" type="url" pattern="https://.*" maxlength="2048" name="image_url" /></label></details>
</template>
