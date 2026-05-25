<template>
  <div class="moderationStageTab" data-cy="moderationStageTab">
    <div v-if="isLoading" class="moderationStageTab__loading">
      {{ t("common.loading") }}
    </div>

    <div v-else-if="tabData && tabData.stageExists" class="moderationStageTab__body">
      <!-- Author-facing description of the current stage -->
      <div v-if="tabData.userIsAuthor" class="moderationStageTab__currentStageInfo">
        <h3>{{ t("plugins.generic.scieloModerationStages.currentStageInfo") }}</h3>
        <div v-html="workflowDescription"></div>
      </div>

      <!-- Stage entry dates -->
      <div
        v-for="field in dateFields"
        :key="field.name"
        class="moderationStageTab__dateField"
        :data-cy="field.name + 'Div'"
      >
        <label class="moderationStageTab__label">{{ t(field.labelKey) }}</label>
        <p class="moderationStageTab__description">
          {{ t("plugins.generic.scieloModerationStages.menuDates.fieldDescription") }}
        </p>
        <input
          type="date"
          :name="field.name"
          v-model="form[field.name]"
          :disabled="tabData.userIsAuthor"
        />
      </div>

      <!-- Advance to next stage -->
      <div
        v-if="!tabData.userIsAuthor && tabData.canAdvanceStage"
        class="moderationStageTab__sendNextStage"
      >
        <label class="moderationStageTab__label">
          {{ t("plugins.generic.scieloModerationStages.sendNextStageField") }}
        </label>
        <p class="moderationStageTab__description">
          {{
            t("plugins.generic.scieloModerationStages.checkboxSendNextStage", {
              currentStage: tabData.currentStageName,
              nextStage: tabData.nextStageName,
            })
          }}
        </p>
        <label class="moderationStageTab__radio">
          <input
            type="radio"
            name="sendNextStage"
            value="1"
            data-cy="sendNextStageYes"
            v-model="form.sendNextStage"
          />
          {{ t("common.yes") }}
        </label>
        <label class="moderationStageTab__radio">
          <input
            type="radio"
            name="sendNextStage"
            value="0"
            data-cy="sendNextStageNo"
            v-model="form.sendNextStage"
          />
          {{ t("common.no") }}
        </label>
      </div>

      <!-- Save -->
      <div v-if="!tabData.userIsAuthor" class="moderationStageTab__actions">
        <span data-cy="moderationStageSubmit">
          <PkpButton :is-disabled="isSaving" @click="save">
            {{ t("common.save") }}
          </PkpButton>
        </span>
        <span v-if="savedMessage" class="moderationStageTab__saved">
          {{ savedMessage }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";

const { useLocalize } = pkp.modules.useLocalize;
const { t } = useLocalize();

const props = defineProps({
  submission: { type: Object, required: true },
});

const tabData = ref(null);
const isLoading = ref(true);
const isSaving = ref(false);
const savedMessage = ref("");

const form = reactive({
  formatStageEntryDate: null,
  contentStageEntryDate: null,
  areaStageEntryDate: null,
  sendNextStage: "0",
});

const stageDateLabelKeys = {
  formatStageEntryDate: "plugins.generic.scieloModerationStages.stages.formatStage",
  contentStageEntryDate: "plugins.generic.scieloModerationStages.stages.contentStage",
  areaStageEntryDate: "plugins.generic.scieloModerationStages.stages.areaStage",
};

const stageWorkflowDescriptionKeys = {
  "plugins.generic.scieloModerationStages.stages.formatStage":
    "plugins.generic.scieloModerationStages.stages.formatStage.workflowDescription",
  "plugins.generic.scieloModerationStages.stages.contentStage":
    "plugins.generic.scieloModerationStages.stages.contentStage.workflowDescription",
  "plugins.generic.scieloModerationStages.stages.areaStage":
    "plugins.generic.scieloModerationStages.stages.areaStage.workflowDescription",
};

const dateFields = computed(() => {
  if (!tabData.value || !tabData.value.stageEntryDates) {
    return [];
  }
  return Object.keys(tabData.value.stageEntryDates).map((name) => ({
    name,
    labelKey: stageDateLabelKeys[name],
  }));
});

const workflowDescription = computed(() => {
  if (!tabData.value) {
    return "";
  }
  const descriptionKey = stageWorkflowDescriptionKeys[tabData.value.currentStageKey];
  return descriptionKey
    ? t(descriptionKey, { faqUrl: tabData.value.faqUrl })
    : "";
});

function handlerUrl(op) {
  return window.app.moderationStagesHandlerUrls[op];
}

onMounted(async () => {
  const url =
    handlerUrl("getModerationTabData") +
    `?submissionId=${props.submission.id}`;

  const response = await fetch(url, {
    headers: { Accept: "application/json" },
    credentials: "same-origin",
  });
  tabData.value = await response.json();

  if (tabData.value.stageEntryDates) {
    Object.entries(tabData.value.stageEntryDates).forEach(([name, value]) => {
      form[name] = value;
    });
  }

  isLoading.value = false;
});

async function save() {
  isSaving.value = true;
  savedMessage.value = "";

  const body = new URLSearchParams();
  body.append("submissionId", tabData.value.submissionId);
  body.append("csrfToken", tabData.value.csrfToken);

  dateFields.value.forEach((field) => {
    if (form[field.name]) {
      body.append(field.name, form[field.name]);
    }
  });

  if (tabData.value.canAdvanceStage) {
    body.append("sendNextStage", form.sendNextStage);
  }

  await fetch(handlerUrl("updateSubmissionStageData"), {
    method: "POST",
    body,
    credentials: "same-origin",
  });

  savedMessage.value = t("form.saved");
  isSaving.value = false;
}
</script>

<style scoped>
.moderationStageTab {
  margin-top: 1rem;
}

.moderationStageTab__currentStageInfo {
  margin-bottom: 1.5rem;
}

.moderationStageTab__dateField,
.moderationStageTab__sendNextStage {
  margin-bottom: 1.5rem;
}

.moderationStageTab__label {
  display: block;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.moderationStageTab__description {
  margin: 0 0 0.5rem;
  color: #555;
}

.moderationStageTab__radio {
  display: inline-block;
  margin-right: 1rem;
}

.moderationStageTab__actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.moderationStageTab__saved {
  color: #00833e;
  font-weight: 700;
}

.moderationStageTab__loading {
  padding: 2rem;
  text-align: center;
}
</style>
