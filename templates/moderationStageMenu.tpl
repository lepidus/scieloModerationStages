{*
  * Copyright (c) 2022 Lepidus Tecnologia
  * Copyright (c) 2022 SciELO
  * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
  *
  *}

<link rel="stylesheet" type="text/css" href="/plugins/generic/scieloModerationStages/styles/moderationStageStyleSheet.css">
{capture assign=updateStageEntryDates}{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.scieloModerationStages.controllers.ScieloModerationStagesHandler" op="updateStageEntryDates" escape=false}{/capture}

<div class="pkp_form" id="moderationStageEntriesForm">
    {if $formatStageEntryDate}
        <div id="formatStageEntryDateDiv" class="stageDateField">
            <label class="label">{translate key="plugins.generic.scieloModerationStages.stages.formatStage"}</label>
            <label class="description">{translate key="plugins.generic.scieloModerationStages.menuDates.fieldDescription"}</label>
            <input type="date" id='formatStageEntryDate' name='formatStageEntryDate' value="{$formatStageEntryDate}" {if $userIsAuthor}disabled{/if}/>
        </div>
    {/if}
    {if $contentStageEntryDate}
        <div id="contentStageEntryDateDiv" class="stageDateField">
            <label class="label">{translate key="plugins.generic.scieloModerationStages.stages.contentStage"}</label>
            <label class="description">{translate key="plugins.generic.scieloModerationStages.menuDates.fieldDescription"}</label>
            <input type="date" id='contentStageEntryDate' name='contentStageEntryDate' value="{$contentStageEntryDate}"{if $userIsAuthor}disabled{/if}/>
        </div>
    {/if}
    {if $areaStageEntryDate}
        <div id="areaStageEntryDateDiv" class="stageDateField">
            <label class="label">{translate key="plugins.generic.scieloModerationStages.stages.areaStage"}</label>
            <label class="description">{translate key="plugins.generic.scieloModerationStages.menuDates.fieldDescription"}</label>
            <input type="date" id='areaStageEntryDate' name='areaStageEntryDate' value="{$areaStageEntryDate}"{if $userIsAuthor}disabled{/if}/>
        </div>
    {/if}

    {if not $userIsAuthor}
        <div class="formButtons">
            <button id="moderationStageSubmit" type="button" class="pkp_button submitFormButton">{translate key="common.save"}</button>
        </div>
    {/if}
</div>

{if not $userIsAuthor}
    <script>
        function updateSuccess(){ldelim}
            alert("{translate key="form.saved"}");
        {rdelim}

        async function makeSubmit(e){ldelim}
            $.post(
                "{$updateStageEntryDates}",
                {ldelim}
                    submissionId: {$submissionId},
                    {if $formatStageEntryDate} formatStageEntryDate: $('#formatStageEntryDate').val(), {/if}
                    {if $contentStageEntryDate} contentStageEntryDate: $('#contentStageEntryDate').val(), {/if}
                    {if $areaStageEntryDate} areaStageEntryDate: $('#areaStageEntryDate').val(), {/if}
                {rdelim},
                updateSuccess()
            );
        {rdelim}

        $(function(){ldelim}
            $('#moderationStageSubmit').click(makeSubmit);
        {rdelim});
    </script>
{/if}