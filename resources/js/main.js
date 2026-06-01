import ModerationStageTab from "./Components/ModerationStageTab.vue";
import ModerationStageHeader from "./Components/ModerationStageHeader.vue";
import DashboardCellModerationTitle from "./Components/DashboardCellModerationTitle.vue";

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
