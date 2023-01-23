<?php

/**
 * @defgroup plugins_generic_scieloModerationStages SciELO Moderation Stages Plugin
 */

/**
 * @file plugins/reports/scieloModerationStages/index.php
 *
 * Copyright (c) 2022 Lepidus Tecnologia
 * Copyright (c) 2022 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @ingroup plugins_generic_scieloModerationStages
 * @brief Wrapper for SciELO Moderation Stages plugin.
 *
 */

require_once('ScieloModerationStagesPlugin.inc.php');

return new ScieloModerationStagesPlugin();
