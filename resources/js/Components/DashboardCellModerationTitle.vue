<template>
  <PkpTableCell
    :id="'submission-title-' + item.id"
    :is-row-header="true"
  >
    <div class="max-w-[25em] truncate">
      <span class="text-base-bold">
        {{ currentPublication.authorsStringShort }}
      </span>
      <template v-if="currentPublication.authorsStringShort">—</template>
      <span
        v-strip-unsafe-html="
          localizeSubmission(
            currentPublication.fullTitle,
            currentPublication.locale
          )
        "
        class="text-base-normal"
      ></span>
    </div>

    <div
      v-if="exhibit.ModerationStage || hasExtraData"
      class="moderationStageCell"
      data-cy="moderationStageCell"
    >
      <div v-if="exhibit.ModerationStage" class="moderationStageCell__stage">
        {{ exhibit.ModerationStage }}
      </div>

      <template v-if="hasExtraData">
        <div v-if="hasPeople" class="moderationStageCell__group">
          <div v-if="exhibit.AreaModerators" class="moderationStageCell__line">
            {{ exhibit.AreaModerators }}
          </div>
        </div>

        <div v-if="hasTimes" class="moderationStageCell__group">
          <div
            v-for="field in timeFields"
            :key="field"
            v-show="exhibit[field]"
            class="moderationStageCell__line"
            :class="{ 'moderationStageCell__line--red': exhibit[field + 'RedFlag'] }"
          >
            {{ exhibit[field] }}
          </div>
        </div>
      </template>
    </div>
  </PkpTableCell>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";

const { useSubmission } = pkp.modules.useSubmission;
const { useLocalize } = pkp.modules.useLocalize;

const props = defineProps({ item: { type: Object, required: true } });

const { getCurrentPublication } = useSubmission();
const { localizeSubmission } = useLocalize();
const currentPublication = computed(() => getCurrentPublication(props.item));

const exhibit = ref({});
const timeFields = ["TimeResponsible", "TimeAreaModerator"];

const hasPeople = computed(() => exhibit.value.AreaModerators);
const hasTimes = computed(() =>
  timeFields.some((field) => exhibit.value[field])
);
const hasExtraData = computed(() => hasPeople.value || hasTimes.value);

// Shared across all cells so the author check runs only once per dashboard.
let userIsAuthorPromise = null;
function fetchUserIsAuthor() {
  if (!userIsAuthorPromise) {
    userIsAuthorPromise = fetch(window.app.moderationStagesHandlerUrls.getUserIsAuthor, {
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    }).then((response) => response.json());
  }
  return userIsAuthorPromise;
}

onMounted(async () => {
  const userIsAuthor = await fetchUserIsAuthor();
  const url =
    window.app.moderationStagesHandlerUrls.getSubmissionExhibitData +
    `?submissionId=${props.item.id}&userIsAuthor=${userIsAuthor}`;

  const response = await fetch(url, {
    headers: { Accept: "application/json" },
    credentials: "same-origin",
  });
  exhibit.value = await response.json();
});
</script>

<style scoped>
.moderationStageCell {
  font-size: 0.8125rem;
  line-height: 1.4;
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px solid #e0e0e0;
}

.moderationStageCell__stage {
  font-weight: 700;
}

.moderationStageCell__group {
  margin-top: 0.5rem;
}

.moderationStageCell__line {
  margin-bottom: 0.25rem;
}

.moderationStageCell__line:last-child {
  margin-bottom: 0;
}

.moderationStageCell__line--red {
  color: #d00a0a;
  font-weight: 700;
}
</style>
