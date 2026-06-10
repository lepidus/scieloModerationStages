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

      <!-- Moderation stage change -->
      <div
        v-if="!tabData.userIsAuthor && (tabData.canAdvanceStage || tabData.canRegressStage)"
        class="moderationStageTab__stageChange"
      >
        <label class="moderationStageTab__label">
          {{ t("plugins.generic.scieloModerationStages.stageChangeField") }}
        </label>
        <p class="moderationStageTab__description">
          {{
            t("plugins.generic.scieloModerationStages.stageChange.description", {
              currentStage: tabData.currentStageName,
            })
          }}
        </p>
        <label v-if="tabData.canAdvanceStage" class="moderationStageTab__radioBlock">
          <input
            type="radio"
            name="stageChange"
            value="advance"
            data-cy="stageChangeAdvance"
            v-model="form.stageChange"
          />
          {{
            t("plugins.generic.scieloModerationStages.stageChange.advance", {
              nextStage: tabData.nextStageName,
            })
          }}
        </label>
        <label v-if="tabData.canRegressStage" class="moderationStageTab__radioBlock">
          <input
            type="radio"
            name="stageChange"
            value="regress"
            data-cy="stageChangeRegress"
            v-model="form.stageChange"
          />
          {{
            t("plugins.generic.scieloModerationStages.stageChange.regress", {
              previousStage: tabData.previousStageName,
            })
          }}
        </label>
        <label class="moderationStageTab__radioBlock">
          <input
            type="radio"
            name="stageChange"
            value="stay"
            data-cy="stageChangeStay"
            v-model="form.stageChange"
          />
          {{ t("plugins.generic.scieloModerationStages.stageChange.stay") }}
        </label>
      </div>

      <!-- Save -->
      <div v-if="!tabData.userIsAuthor" class="moderationStageTab__actions">
        <span data-cy="moderationStageSubmit">
          <PkpButton :is-disabled="isSaving" @click="save">
            {{ t("common.save") }}
          </PkpButton>
        </span>
        <span
          v-if="savedMessage"
          :class="saveFailed ? 'moderationStageTab__saveError' : 'moderationStageTab__saved'"
          data-cy="moderationStageSaveMessage"
        >
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

const { useDataChanged } = pkp.modules.useDataChanged;
const { triggerDataChange } = useDataChanged();

const props = defineProps({
  submission: { type: Object, required: true },
});

const tabData = ref(null);
const isLoading = ref(true);
const isSaving = ref(false);
const savedMessage = ref("");
const saveFailed = ref(false);

const form = reactive({
  formatStageEntryDate: null,
  contentStageEntryDate: null,
  areaStageEntryDate: null,
  stageChange: "stay",
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

async function loadTabData() {
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

  form.stageChange = "stay";
}

onMounted(async () => {
  await loadTabData();
  isLoading.value = false;
});

async function save() {
  isSaving.value = true;
  savedMessage.value = "";
  saveFailed.value = false;

  const body = new URLSearchParams();
  body.append("submissionId", tabData.value.submissionId);
  body.append("csrfToken", tabData.value.csrfToken);

  dateFields.value.forEach((field) => {
    if (form[field.name]) {
      body.append(field.name, form[field.name]);
    }
  });

  if (tabData.value.canAdvanceStage) {
    body.append("sendNextStage", form.stageChange === "advance" ? "1" : "0");
  }

  if (tabData.value.canRegressStage) {
    body.append("sendPreviousStage", form.stageChange === "regress" ? "1" : "0");
  }

  try {
    const response = await fetch(handlerUrl("updateSubmissionStageData"), {
      method: "POST",
      body,
      credentials: "same-origin",
    });

    if (!response.ok) {
      throw new Error(`Saving failed with status ${response.status}`);
    }

    await Promise.all([loadTabData(), triggerDataChange()]);
    savedMessage.value = t("form.saved");
  } catch (error) {
    saveFailed.value = true;
    savedMessage.value = t(
      "plugins.generic.scieloModerationStages.stageChange.saveError"
    );
  } finally {
    isSaving.value = false;
  }
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
.moderationStageTab__stageChange {
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

.moderationStageTab__radioBlock {
  display: block;
  margin-bottom: 0.25rem;
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

.moderationStageTab__saveError {
  color: #d00a0a;
  font-weight: 700;
}

.moderationStageTab__loading {
  padding: 2rem;
  text-align: center;
}
</style>
