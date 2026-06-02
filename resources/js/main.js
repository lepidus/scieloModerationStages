import ModerationStageTab from "./Components/ModerationStageTab.vue";
import ModerationStageHeader from "./Components/ModerationStageHeader.vue";
import DashboardCellModerationTitle from "./Components/DashboardCellModerationTitle.vue";

// The native circular count badge for SideNav dashboard views is populated from
// the _submissions/viewsCount endpoint, which only knows about core views and
// exposes no hook for custom ones. To render the moderation-stage badges exactly
// like the native ones, we intercept that response and merge in the plugin counts
// (fetched from our own handler). Only the editorial dashboard submenu references
// these ids, so merging them everywhere is harmless.
(function patchViewsCountFetch() {
  if (window.__moderationStagesFetchPatched) {
    return;
  }
  window.__moderationStagesFetchPatched = true;

  const originalFetch = window.fetch.bind(window);
  const VIEWS_COUNT_PATH = "/_submissions/viewsCount";

  let inFlightCounts = null;
  function fetchModerationStageCounts() {
    const url = window.app?.moderationStagesHandlerUrls?.getModerationStageCounts;
    if (!url) {
      return Promise.resolve({});
    }
    if (inFlightCounts) {
      return inFlightCounts;
    }
    inFlightCounts = originalFetch(url, {
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    })
      .then((response) => response.json())
      .catch(() => ({}))
      .finally(() => {
        inFlightCounts = null;
      });
    return inFlightCounts;
  }

  window.fetch = async function (input, init) {
    const url = typeof input === "string" ? input : input?.url || "";
    if (!url.includes(VIEWS_COUNT_PATH)) {
      return originalFetch(input, init);
    }

    const response = await originalFetch(input, init);
    try {
      const [data, moderationCounts] = await Promise.all([
        response.clone().json(),
        fetchModerationStageCounts(),
      ]);
      const merged = { ...data, ...moderationCounts };
      return new Response(JSON.stringify(merged), {
        status: response.status,
        statusText: response.statusText,
        headers: { "Content-Type": "application/json" },
      });
    } catch (error) {
      return response;
    }
  };
})();

pkp.registry.registerComponent("ModerationStageTab", ModerationStageTab);
pkp.registry.registerComponent("ModerationStageHeader", ModerationStageHeader);
pkp.registry.registerComponent(
  "DashboardCellModerationTitle",
  DashboardCellModerationTitle
);

pkp.registry.storeExtend("workflow", (piniaContext) => {
  const workflowStore = piniaContext.store;
  const { useLocalize } = pkp.modules.useLocalize;
  const { t } = useLocalize();

  workflowStore.extender.extendFn("getMenuItems", (menuItems) => {
    return menuItems.map((menuItem) => {
      if (menuItem.key === "publication" && menuItem.items) {
        return {
          ...menuItem,
          items: [
            ...menuItem.items,
            {
              key: "publication_scieloModerationStages",
              label: t("plugins.generic.scieloModerationStages.displayNameWorkflow"),
              state: {
                primaryMenuItem: "publication",
                secondaryMenuItem: "scieloModerationStages",
                title: t("plugins.generic.scieloModerationStages.displayNameWorkflow"),
              },
            },
          ],
        };
      }
      return menuItem;
    });
  });

  workflowStore.extender.extendFn("getPrimaryItems", (primaryItems, args) => {
    if (
      args?.selectedMenuState?.primaryMenuItem === "publication" &&
      args?.selectedMenuState?.secondaryMenuItem === "scieloModerationStages"
    ) {
      return [
        {
          component: "ModerationStageTab",
          props: { submission: args.submission },
        },
      ];
    }
    return primaryItems;
  });

  workflowStore.extender.extendFn("getHeaderItems", (actions, args) => {
    if (args?.submission?.currentModerationStage) {
      return [
        {
          component: "ModerationStageHeader",
          props: { submission: args.submission },
        },
        ...actions,
      ];
    }
    return actions;
  });
});

pkp.registry.storeExtend("dashboard", (piniaContext) => {
  const dashboardStore = piniaContext.store;

  dashboardStore.extender.extendFn("getColumns", (columns) => {
    return columns.map((column) => {
      if (column.id === "title") {
        return {
          ...column,
          component: "DashboardCellModerationTitle",
        };
      }
      return column;
    });
  });
});
