<script>
    $(function() {ldelim}
        $('#sendModerationReminderForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<form class="pkp_form" id="sendModerationReminderForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="sendModerationReminder" save=true}">
    <div id="sendModerationReminder">
        {csrf}
        {include file="controllers/notification/inPlaceNotification.tpl" notificationId="sendModerationReminderFormNotification"}
        {fbvFormButtons}
        <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
    </div>
</form>

