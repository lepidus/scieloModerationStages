import { resolve } from "path";
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import i18nExtractKeys from "./i18nExtractKeys.vite.js";

// https://vitejs.dev/config/
export default defineConfig({
  target: "es2016",
  plugins: [
    i18nExtractKeys({
      extraKeys: [
        "plugins.generic.scieloModerationStages.stages.formatStage",
        "plugins.generic.scieloModerationStages.stages.contentStage",
        "plugins.generic.scieloModerationStages.stages.areaStage",
        "plugins.generic.scieloModerationStages.stages.formatStage.workflowDescription",
        "plugins.generic.scieloModerationStages.stages.contentStage.workflowDescription",
        "plugins.generic.scieloModerationStages.stages.areaStage.workflowDescription",
      ],
    }),
    vue(),
  ],
  build: {
    lib: {
      entry: resolve(__dirname, "resources/js/main.js"),
      name: "ScieloModerationStagesPlugin",
      fileName: "build",
      formats: ["iife"],
    },
    outDir: resolve(__dirname, "public/build"),
    rollupOptions: {
      external: ["vue"],
      output: {
        globals: {
          vue: "pkp.modules.vue",
        },
      },
    },
  },
});
