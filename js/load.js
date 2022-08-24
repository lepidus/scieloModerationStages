function insertAfter(newNode, referenceNode) {
    referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}

function createExhibitorNode(submissionId, type) {
    var node = document.createElement('div');
    node.classList.add('listPanel__item' + type);
    node.classList.add('submission' + type + '--' + submissionId);
    node.classList.add('withoutDataYet');
    return node;
}

function createStageExhibitorNode(submissionId) {
    return createExhibitorNode(submissionId, 'ModerationStage');
}

function createResponsibleExhibitorNode(submissionId) {
    return createExhibitorNode(submissionId, 'Responsible');
}

function createAreaModeratorExhibitorNode(submissionId) {
    return createExhibitorNode(submissionId, 'AreaModerator');
}

function updateExhibitorNode(classNamePrefix, text, submissionId) {
    var exhibitorNodes = document.getElementsByClassName(classNamePrefix + '--' + submissionId);
    for(let exhibitorNode of exhibitorNodes) {
        exhibitorNode.textContent = text;
        exhibitorNode.classList.remove('withoutDataYet');
    }
}

function updateExhibitorNodes(response) {
    response = JSON.parse(response);
    const submissionId = response['submissionId'];
    if(response['moderationStageName'] != '') {
        updateExhibitorNode('submissionModerationStage', response['moderationStageName'], submissionId);
    }

    if(response['responsibles'] != '') {
        updateExhibitorNode('submissionResponsible', response['responsibles'], submissionId);
    }

    if(response['areaModerators'] != '') {
        updateExhibitorNode('submissionAreaModerator', response['areaModerators'], submissionId);
    }
}

function getSubmissionIdFromDiv(parentDiv) {
    var id = parentDiv.textContent.trim().split(' ')[0];
    return id;
}

function addSubmissionExhibitors() {
    var submissionSubtitle = document.getElementsByClassName('listPanel__itemSubtitle');
    for (let subtitle of submissionSubtitle) {
        const hasExhibitors = subtitle.parentNode.getElementsByClassName('listPanel__itemModerationStage').length > 0;
        if(!hasExhibitors) {
            const submissionId = getSubmissionIdFromDiv(subtitle.parentNode);
            $.get(
                app.moderationStagesHandlerUrl + 'get-submission-exhibit-data',
                {
                    submissionId: submissionId,
                },
                updateExhibitorNodes
            );

            var stageExhibitorNode = createStageExhibitorNode(submissionId);
            var responsibleExhibitorNode = createResponsibleExhibitorNode(submissionId);
            var areaModeratorExhibitorNode = createAreaModeratorExhibitorNode(submissionId);
            insertAfter(stageExhibitorNode, subtitle);
            insertAfter(responsibleExhibitorNode, stageExhibitorNode);
            insertAfter(areaModeratorExhibitorNode, responsibleExhibitorNode);
        }
    }
}

function setExhibitorsToBeAddedAfterRequestsFinish() {
    var origOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function() {
        this.addEventListener('load', function() {
            var url = this.responseURL;
            if(url.search('_submissions') >= 0) {
                setTimeout(addSubmissionExhibitors, 500);
            }
        });
        origOpen.apply(this, arguments);
    };
}

$(document).ready(setExhibitorsToBeAddedAfterRequestsFinish);