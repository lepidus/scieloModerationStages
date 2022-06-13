{*
  * Copyright (c) 2022 Lepidus Tecnologia
  * Copyright (c) 2022 SciELO
  * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
  *
  *}

<form class="pkp_form" id="moderationStageEntriesForm" action="{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.scieloModerationStages.controllers.ScieloScreeningHandler" op="updateStageEntryDates" escape=false}" method="post">
    {if $formatStageEntryDate}
        <div id="formatStageEntryDate" class="stageDateField">
            <label class="label">{translate key="plugins.generic.scieloModerationStages.stages.formatStage"}</label>
            <label class="description">{translate key="plugins.generic.scieloModerationStages.menuDates.fieldDescription"}</label>
            <input type="date" id='formatStageEntryDate' name='formatStageEntryDate' value="{$formatStageEntryDate}"/>
        </div>
    {/if}
    {if $contentStageEntryDate}
        <div id="contentStageEntryDate" class="stageDateField">
            <label class="label">{translate key="plugins.generic.scieloModerationStages.stages.contentStage"}</label>
            <label class="description">{translate key="plugins.generic.scieloModerationStages.menuDates.fieldDescription"}</label>
            <input type="date" id='contentStageEntryDate' name='contentStageEntryDate' value="{$contentStageEntryDate}"/>
        </div>
    {/if}
    {if $areaStageEntryDate}
        <div id="areaStageEntryDate" class="stageDateField">
            <label class="label">{translate key="plugins.generic.scieloModerationStages.stages.areaStage"}</label>
            <label class="description">{translate key="plugins.generic.scieloModerationStages.menuDates.fieldDescription"}</label>
            <input type="date" id='areaStageEntryDate' name='areaStageEntryDate' value="{$areaStageEntryDate}"/>
        </div>
    {/if}

    <div class="formButtons">
        <input class="pkp_button submitFormButton" type="submit" value="{translate key="common.save"}"/>
    </div>
</form>