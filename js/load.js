function insertAfter(newNode, referenceNode) {
    referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}

function createStageExhibitorNode() {
    var node = document.createElement('div');
    node.classList.add('listPanel__itemModerationStage');
    node.textContent = "Bolinha";
    return node;
}

function putBazinga() {
    var submissionSubtitle = document.getElementsByClassName('listPanel__itemSubtitle');
    for (let subtitle of submissionSubtitle) {
        var exhibitorNode = createStageExhibitorNode();
        subtitle.appendChild(exhibitorNode);
    }
}

function setToExecute() {
    setTimeout(putBazinga, 3000);
}

$(document).ready(setToExecute);