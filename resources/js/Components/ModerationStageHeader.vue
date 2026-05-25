<template>
  <span v-if="stageLabel" class="moderationStageHeader">
    <strong>{{ t("plugins.generic.scieloModerationStages.currentStageStatusLabel") }}</strong>
    {{ stageLabel }}
  </span>
</template>

<script setup>
import { computed } from "vue";

const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();

const props = defineProps({
  submission: { type: Object, required: true },
});

const stageLabelKeys = {
  1: "plugins.generic.scieloModerationStages.stages.formatStage",
  2: "plugins.generic.scieloModerationStages.stages.contentStage",
  3: "plugins.generic.scieloModerationStages.stages.areaStage",
};

const stageLabel = computed(() => {
  const stage = props.submission?.currentModerationStage;
  return stage && stageLabelKeys[stage] ? t(stageLabelKeys[stage]) : "";
});
</script>

<style scoped>
.moderationStageHeader {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.875rem;
}
</style>
