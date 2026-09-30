let labeledExhibitorNodes = ['ModerationStage', 'Responsibles', 'AreaModerators'];

function insertAfter(newNode, referenceNode) {
    referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}

function createExhibitorNode(submissionId, exhibitorName, text) {
    let node = document.createElement('div');
    node.classList.add('moderationStagesExhibitor');
    node.classList.add('listPanel__item' + exhibitorName);
    node.classList.add('submission' + exhibitorName + '--' + submissionId);
    if(labeledExhibitorNodes.includes(exhibitorName)) {
        addTextToLabeledExhibitor(node, text);
    }
    else {
        node.textContent = text;
    }
    return node;
}

function createExhibitorsSeparator(submissionId) {
    var node = document.createElement('hr');
    node.classList.add('exhibitorsSeparator');
    node.classList.add('submissionExhibitorSeparator' + '--' + submissionId);
    return node;
}

function addLineBreakAfterExhibitor(exhibitorNode) {
    let br = document.createElement('br');
    insertAfter(br, exhibitorNode);
    return br;
}

function addTextToLabeledExhibitor(exhibitorNode, text) {
    let [labelText, contentText] = text.split(':');
    var labelStrong = document.createElement('strong');

    exhibitorNode.appendChild(labelStrong);
    labelStrong.textContent = labelText+':';
    exhibitorNode.appendChild(document.createTextNode(contentText));
}

function addSubmissionExhibitorNodes(response) {
    response = JSON.parse(response);
    const submissionId = response['submissionId'];
    const submissionIdNodes = [...document.querySelectorAll('div.listPanel__item--submission__id')]
        .filter(div => div.textContent.trim() == submissionId);
    delete response['submissionId'];
    
    for (let idNode of submissionIdNodes) {
        const submissionIdentityNode = idNode.parentNode;
        const alreadyHasExhibitors = submissionIdentityNode.getElementsByClassName('moderationStagesExhibitor').length > 0;
        let previousNode = submissionIdentityNode.getElementsByClassName('listPanel__itemSubtitle')[0];

        if (alreadyHasExhibitors) {
            continue;
        }

        for (const exhibitorName in response) {
            if (response[exhibitorName] == '' || exhibitorName.includes('RedFlag')) {
                continue;
            }

            if(exhibitorName.includes('ExhibitorsSeparator')) {
                newExhibitorNode = createExhibitorsSeparator(submissionId);
            } else {
                newExhibitorNode = createExhibitorNode(submissionId, exhibitorName, response[exhibitorName]);
                if(exhibitorName+'RedFlag' in response) {
                    newExhibitorNode.classList.add('itemTimeRed')
                }
            }
            insertAfter(newExhibitorNode, previousNode);
            previousNode = newExhibitorNode;

            if (!exhibitorName.includes('ExhibitorsSeparator')) {
                previousNode = addLineBreakAfterExhibitor(newExhibitorNode);
            }
        }
    }
}

function getSubmissionIdFromDiv(parentDiv) {
    var id = parentDiv.textContent.trim().split(' ')[0];
    return id;
}

async function addSubmissionExhibitors() {
    let submissionSubtitles = document.getElementsByClassName('listPanel__itemSubtitle');
    for (let subtitle of submissionSubtitles) {
        const hasExhibitors = subtitle.parentNode.getElementsByClassName('moderationStagesExhibitor').length > 0;
        if(!hasExhibitors) {
            const submissionId = getSubmissionIdFromDiv(subtitle.parentNode);
            $.get(
                app.moderationStagesHandlerUrl + 'get-submission-exhibit-data',
                {submissionId: submissionId},
                addSubmissionExhibitorNodes
            );
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