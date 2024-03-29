let labeledExhibitorNodes = ['ModerationStage', 'Responsibles', 'AreaModerators'];
let exhibitorNodesAdmin = ['exhibitorsSeparator', 'Responsibles', 'AreaModerators', 'TimeSubmitted', 'TimeResponsible', 'TimeAreaModerator'];
var userIsAuthor = '1';

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

function createExhibitorsSeparator(submissionId) {
    var node = document.createElement('hr');
    node.classList.add('exhibitorsSeparator');
    node.classList.add('submissionExhibitorSeparator' + '--' + submissionId);
    node.style.display = 'none';
    return node;
}

function addLineBreakAfterExhibitor(exhibitorNode) {
    var br = document.createElement('br');
    insertAfter(br, exhibitorNode);
}

function addTextToLabeledExhibitor(exhibitorNode, text) {
    let [labelText, contentText] = text.split(':');
    var labelStrong = document.createElement('strong');

    exhibitorNode.appendChild(labelStrong);
    labelStrong.textContent = labelText+':';
    exhibitorNode.innerHTML += contentText;
}

function updateExhibitorNode(exhibitorNodeName, text, submissionId) {
    var exhibitorNodes = document.getElementsByClassName('submission' + exhibitorNodeName + '--' + submissionId);
    for(let exhibitorNode of exhibitorNodes) {
        if(exhibitorNode.classList.contains('withoutDataYet')) {
            exhibitorNode.classList.remove('withoutDataYet');
        
            if(labeledExhibitorNodes.includes(exhibitorNodeName)) {
                addTextToLabeledExhibitor(exhibitorNode, text);
            }
            else {
                exhibitorNode.textContent = text;
            }
            addLineBreakAfterExhibitor(exhibitorNode);
        }
    }
}

function updateExhibitorsSeparator(submissionId) {
    var exhibitorsSeparators = document.getElementsByClassName('submissionExhibitorSeparator--' + submissionId);
    for(let separator of exhibitorsSeparators) {
        separator.style.display = 'block';
    }
}

function addRedColorToTimeExhibitor(exhibitorNodeName, submissionId) {
    var exhibitorNodes = document.getElementsByClassName('submission' + exhibitorNodeName + '--' + submissionId);
    for(let exhibitorNode of exhibitorNodes) {
        exhibitorNode.classList.add('itemTimeRed');
    }
}

function updateExhibitorNodes(response) {
    response = JSON.parse(response);
    const submissionId = response['submissionId'];

    if(response['ModerationStage'] != '') {
        updateExhibitorNode('ModerationStage', response['ModerationStage'], submissionId);
    }

    if(userIsAuthor == false) {
        for (const exhibitorNodeName of exhibitorNodesAdmin) {
            if(exhibitorNodeName == 'exhibitorsSeparator') {
                updateExhibitorsSeparator(submissionId);
            }
            else if(response[exhibitorNodeName] != '') {
                updateExhibitorNode(exhibitorNodeName, response[exhibitorNodeName], submissionId);

                if(exhibitorNodeName+'RedFlag' in response) {
                    addRedColorToTimeExhibitor(exhibitorNodeName, submissionId);
                }
            }
        }
    }
}

function getSubmissionIdFromDiv(parentDiv) {
    var id = parentDiv.textContent.trim().split(' ')[0];
    return id;
}

async function addSubmissionExhibitors() {
    userIsAuthor = await $.get(
        app.moderationStagesHandlerUrl + 'get-user-is-author'
    );

    var submissionSubtitle = document.getElementsByClassName('listPanel__itemSubtitle');
    for (let subtitle of submissionSubtitle) {
        const hasExhibitors = subtitle.parentNode.getElementsByClassName('listPanel__itemModerationStage').length > 0;
        if(!hasExhibitors) {
            const submissionId = getSubmissionIdFromDiv(subtitle.parentNode);
            $.get(
                app.moderationStagesHandlerUrl + 'get-submission-exhibit-data',
                {
                    submissionId: submissionId,
                    userIsAuthor: userIsAuthor
                },
                updateExhibitorNodes
            );

            var newExhibitorNode = createExhibitorNode(submissionId, 'ModerationStage');
            insertAfter(newExhibitorNode, subtitle);
            var previousNode = newExhibitorNode;
            
            if(userIsAuthor == false) {
                for(const exhibitorNodeName of exhibitorNodesAdmin) {
                    if(exhibitorNodeName == 'exhibitorsSeparator')
                        newExhibitorNode = createExhibitorsSeparator(submissionId);
                    else
                        newExhibitorNode = createExhibitorNode(submissionId, exhibitorNodeName);
                    insertAfter(newExhibitorNode, previousNode);
                    previousNode = newExhibitorNode;
                }
            }
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